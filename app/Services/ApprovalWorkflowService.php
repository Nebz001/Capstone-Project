<?php

namespace App\Services;

use App\Models\ActivityProposal;
use App\Models\ApprovalLog;
use App\Models\ApprovalWorkflowStep;
use App\Models\ProposalFieldReview;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Extracts the proposal-side approval decision logic from
 * ApproverDashboardController::decide so it can be reused by both the web
 * controller (untouched) and the mobile API controller without duplicating
 * workflow transitions, log writes, or notification dispatch.
 *
 * The web controller continues to own its richer field-review snapshot logic
 * (built off Blade detail sections). This service intentionally implements
 * the workflow transition primitive that does NOT depend on the web
 * controller's view-layer concerns, so the API can drive the same state
 * machine without rendering Blade.
 */
class ApprovalWorkflowService
{
    public function __construct(
        private readonly OrganizationNotificationService $notifications
    ) {
    }

    /**
     * Resolve the current ApprovalWorkflowStep that the given user is allowed
     * to act on for the proposal, or null if they are not the assigned
     * approver for the current step.
     */
    public function currentStepForUser(ActivityProposal $proposal, User $user): ?ApprovalWorkflowStep
    {
        $step = $proposal->workflowSteps()
            ->where('is_current_step', true)
            ->where('role_id', (int) $user->role_id)
            ->where(function ($query) use ($user): void {
                $query->whereNull('assigned_to')
                    ->orWhere('assigned_to', $user->id);
            })
            ->orderBy('step_order')
            ->first();

        return $step;
    }

    /**
     * Lightweight read-only authorization check used by the API to decide
     * whether the given user may even *view* the proposal in the mobile UI.
     * Mirrors the access pattern the web side already grants implicitly via
     * its various controllers (admin / SDAO / approver / officer / submitter).
     */
    public function canViewProposal(User $user, ActivityProposal $proposal): bool
    {
        if ($user->isAdminRole()) {
            return true;
        }

        if (in_array((string) $user->role?->name, ['sdao_staff', 'academic_director', 'executive_director'], true)) {
            return true;
        }

        if ((int) $proposal->submitted_by === (int) $user->id) {
            return true;
        }

        $isOfficerOfOrg = $user->organizationOfficers()
            ->where('organization_id', $proposal->organization_id)
            ->where('status', 'active')
            ->exists();
        if ($isOfficerOfOrg) {
            return true;
        }

        return $proposal->workflowSteps()
            ->where(function ($query) use ($user): void {
                $query->where('role_id', (int) $user->role_id)
                    ->orWhere('assigned_to', (int) $user->id);
            })
            ->exists();
    }

    /**
     * Save (or update) field reviews for the current step. Returns the
     * persisted reviews keyed by field_key.
     *
     * @param  array<int, array{field_key: string, field_label: string, status: string, comment: ?string}>  $fieldReviews
     */
    public function saveFieldReviews(
        ActivityProposal $proposal,
        ApprovalWorkflowStep $currentStep,
        User $reviewer,
        array $fieldReviews
    ): Collection {
        return DB::transaction(function () use ($proposal, $currentStep, $reviewer, $fieldReviews) {
            foreach ($fieldReviews as $row) {
                $status = $row['status'] === 'passed' ? 'approved' : 'revision';
                $comment = isset($row['comment']) && trim((string) $row['comment']) !== ''
                    ? trim((string) $row['comment'])
                    : null;

                ProposalFieldReview::query()->updateOrCreate(
                    [
                        'activity_proposal_id' => $proposal->id,
                        'workflow_step_id' => $currentStep->id,
                        'field_key' => (string) $row['field_key'],
                    ],
                    [
                        'reviewer_id' => $reviewer->id,
                        'field_label' => (string) ($row['field_label'] ?? $row['field_key']),
                        'status' => $status,
                        'comment' => $comment,
                        'reviewed_at' => now(),
                    ]
                );
            }

            return ProposalFieldReview::query()
                ->where('activity_proposal_id', $proposal->id)
                ->where('workflow_step_id', $currentStep->id)
                ->get()
                ->keyBy('field_key');
        });
    }

    /**
     * Apply an approval decision (`approve`, `revision`, or `reject`) for the
     * given proposal step. Mirrors the transition logic from
     * ApproverDashboardController::decide while letting the API caller pass
     * pre-validated comments + (optional) field reviews.
     *
     * @param  array<int, array{field_key: string, field_label: string, status: string, comment: ?string}>  $fieldReviews
     * @return array{
     *     proposal: ActivityProposal,
     *     workflow_step_id: int,
     *     from_status: string,
     *     to_status: string,
     *     action: string,
     *     log_action: string,
     *     next_step: array{step_order: int, role_name: ?string}|null,
     * }
     */
    public function decideOnProposal(
        ActivityProposal $proposal,
        ApprovalWorkflowStep $currentStep,
        User $actor,
        string $action,
        string $comments,
        array $fieldReviews = []
    ): array {
        if (! in_array($action, ['approve', 'revision', 'reject'], true)) {
            throw new \InvalidArgumentException('Unsupported approval action: '.$action);
        }

        $fromStatus = strtoupper((string) ($proposal->status ?? 'PENDING'));
        $resultRef = [
            'to_status' => $fromStatus,
            'log_action' => 'approved',
            'next_step' => null,
        ];

        DB::transaction(function () use (
            $proposal,
            $currentStep,
            $actor,
            $action,
            $comments,
            $fromStatus,
            $fieldReviews,
            &$resultRef
        ): void {
            if ($fieldReviews !== []) {
                $this->saveFieldReviews($proposal, $currentStep, $actor, $fieldReviews);
            }

            $toStatus = $fromStatus;
            $logAction = 'approved';
            $currentApprovalStep = (int) $currentStep->step_order;
            $nextStepResolved = null;

            if ($action === 'approve') {
                $currentStep->update([
                    'assigned_to' => $actor->id,
                    'status' => 'approved',
                    'is_current_step' => false,
                    'review_comments' => $comments !== '' ? $comments : null,
                    'acted_at' => now(),
                ]);

                $nextStep = ApprovalWorkflowStep::query()
                    ->where('approvable_type', $currentStep->approvable_type)
                    ->where('approvable_id', $currentStep->approvable_id)
                    ->where('step_order', '>', $currentStep->step_order)
                    ->orderBy('step_order')
                    ->first();

                if ($nextStep) {
                    $nextStep->update([
                        'status' => 'pending',
                        'is_current_step' => true,
                        'assigned_to' => null,
                        'acted_at' => null,
                        'review_comments' => null,
                    ]);
                    $toStatus = 'UNDER_REVIEW';
                    $currentApprovalStep = (int) $nextStep->step_order;
                    $nextStep->loadMissing('role:id,name,display_name');
                    $nextStepResolved = [
                        'step_order' => (int) $nextStep->step_order,
                        'role_name' => $nextStep->role?->display_name ?? $nextStep->role?->name,
                    ];
                } else {
                    $toStatus = 'APPROVED';
                }
            } elseif ($action === 'revision') {
                $currentStep->update([
                    'assigned_to' => $actor->id,
                    'status' => 'revision_required',
                    'is_current_step' => false,
                    'review_comments' => $comments !== '' ? $comments : null,
                    'acted_at' => now(),
                ]);
                $toStatus = 'REVISION';
                $logAction = 'revision_requested';
            } else {
                $currentStep->update([
                    'assigned_to' => $actor->id,
                    'status' => 'rejected',
                    'is_current_step' => false,
                    'review_comments' => $comments !== '' ? $comments : null,
                    'acted_at' => now(),
                ]);
                $toStatus = 'REJECTED';
                $logAction = 'rejected';
            }

            $proposal->update([
                'status' => strtolower($toStatus),
                'current_approval_step' => $currentApprovalStep,
            ]);

            ApprovalLog::query()->create([
                'approvable_type' => $currentStep->approvable_type,
                'approvable_id' => $currentStep->approvable_id,
                'workflow_step_id' => $currentStep->id,
                'actor_id' => $actor->id,
                'action' => $logAction,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'comments' => $comments !== '' ? $comments : null,
                'created_at' => now(),
            ]);

            $resultRef['to_status'] = $toStatus;
            $resultRef['log_action'] = $logAction;
            $resultRef['next_step'] = $nextStepResolved;
        });

        $this->notifyOnProposalResult($proposal, $resultRef['to_status']);

        return [
            'proposal' => $proposal->fresh(),
            'workflow_step_id' => (int) $currentStep->id,
            'from_status' => $fromStatus,
            'to_status' => $resultRef['to_status'],
            'action' => $action,
            'log_action' => (string) $resultRef['log_action'],
            'next_step' => $resultRef['next_step'],
        ];
    }

    /**
     * Mirrors ApproverDashboardController::notifyOrganizationSubmissionResult,
     * scoped to ActivityProposal only. Intentionally re-used so the mobile API
     * fires the exact same in-app notifications the web flow already sends.
     */
    private function notifyOnProposalResult(ActivityProposal $proposal, string $toStatus): void
    {
        $status = strtoupper($toStatus);
        if (! in_array($status, ['APPROVED', 'REVISION', 'REJECTED'], true)) {
            return;
        }

        $type = match ($status) {
            'APPROVED' => 'success',
            'REVISION' => 'warning',
            'REJECTED' => 'error',
            default => 'info',
        };

        $title = match ($status) {
            'APPROVED' => 'Activity Proposal Approved',
            'REVISION' => 'Activity Proposal Returned for Revision',
            default => 'Activity Proposal Rejected',
        };
        $message = match ($status) {
            'APPROVED' => 'Your activity proposal has been approved.',
            'REVISION' => 'Your activity proposal needs updates and was returned for revision.',
            default => 'Your activity proposal was rejected.',
        };

        try {
            $link = route('organizations.activity-submission.proposals.show', $proposal);
        } catch (\Throwable) {
            $link = null;
        }

        $proposal->loadMissing(['submittedBy', 'organization']);

        if ($proposal->submittedBy) {
            $this->notifications->createForUser($proposal->submittedBy, $title, $message, $type, $link, $proposal);
        }
        if ($proposal->organization) {
            $this->notifications->createForOrganization($proposal->organization, $title, $message, $type, $link, $proposal);
        }
    }
}
