<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityProposal;
use App\Models\ApprovalWorkflowStep;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalWorkflowService $workflow
    ) {
    }

    /**
     * GET /api/approvals/pending
     *
     * Returns the logged-in approver's pending proposal queue using the same
     * filtering rules as the web ApproverDashboardController (current step,
     * matching role, assigned to user or unassigned, status in pending /
     * under_review / revision_required).
     */
    public function pending(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return $this->unauthorized();
        }

        if (! $user->isRoleBasedApprover() && ! $user->isAdminRole() && (string) $user->role?->name !== 'sdao_staff') {
            return response()->json(['data' => []]);
        }

        $allowMultiDocumentTypes = in_array((string) $user->role?->name, [
            'sdao_staff', 'academic_director', 'executive_director', 'admin',
        ], true);

        $steps = ApprovalWorkflowStep::query()
            ->where('role_id', (int) $user->role_id)
            ->where('is_current_step', true)
            ->whereIn('status', ['pending', 'under_review', 'revision_required'])
            ->when(! $allowMultiDocumentTypes, fn ($q) => $q->where('approvable_type', ActivityProposal::class))
            ->where('approvable_type', ActivityProposal::class)
            ->where(function ($query) use ($user): void {
                $query->whereNull('assigned_to')
                    ->orWhere('assigned_to', $user->id);
            })
            ->orderBy('created_at')
            ->get();

        $steps->load([
            'role:id,name,display_name',
            'approvable' => function ($morphTo): void {
                $morphTo->morphWith([
                    ActivityProposal::class => ['organization:id,organization_name', 'submittedBy:id,first_name,last_name'],
                ]);
            },
        ]);

        $data = $steps
            ->map(function (ApprovalWorkflowStep $step): ?array {
                $proposal = $step->approvable;
                if (! $proposal instanceof ActivityProposal) {
                    return null;
                }

                $submittedAt = $proposal->submission_date?->toIso8601String();

                return [
                    'workflow_step_id' => (int) $step->id,
                    'proposal_id' => (int) $proposal->id,
                    'proposal_title' => (string) ($proposal->activity_title ?? 'Untitled proposal'),
                    'organization_name' => (string) ($proposal->organization?->organization_name ?? 'N/A'),
                    'current_stage' => (string) ($step->role?->display_name ?? $step->role?->name ?? 'Approver'),
                    'status' => strtolower((string) ($proposal->status ?? 'pending')),
                    'submitted_at' => $submittedAt,
                    'pending_since' => $submittedAt,
                ];
            })
            ->filter()
            ->values();

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/proposals/{proposal}/approve
     */
    public function approve(Request $request, ActivityProposal $proposal): JsonResponse
    {
        $validated = $request->validate([
            'comments' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->dispatchDecision(
            $request,
            $proposal,
            'approve',
            (string) ($validated['comments'] ?? ''),
            [],
            'Proposal approved successfully.'
        );
    }

    /**
     * POST /api/proposals/{proposal}/return
     */
    public function returnForRevision(Request $request, ActivityProposal $proposal): JsonResponse
    {
        $validated = $request->validate([
            'comments' => ['required', 'string', 'max:5000'],
            'field_reviews' => ['nullable', 'array'],
            'field_reviews.*.field_key' => ['required_with:field_reviews', 'string', 'max:120'],
            'field_reviews.*.field_label' => ['required_with:field_reviews', 'string', 'max:255'],
            'field_reviews.*.status' => ['required_with:field_reviews', Rule::in(['passed', 'revision'])],
            'field_reviews.*.comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $fieldReviews = $this->normalizeFieldReviews((array) ($validated['field_reviews'] ?? []));
        $missing = collect($fieldReviews)
            ->first(fn (array $row): bool => $row['status'] === 'revision' && ($row['comment'] === null || trim((string) $row['comment']) === ''));
        if ($missing) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => [
                    "field_reviews.{$missing['field_key']}.comment" => ['Revision note is required when marking a field as Revision.'],
                ],
            ], 422);
        }

        return $this->dispatchDecision(
            $request,
            $proposal,
            'revision',
            (string) $validated['comments'],
            $fieldReviews,
            'Proposal returned for revision.'
        );
    }

    /**
     * POST /api/proposals/{proposal}/reject
     */
    public function reject(Request $request, ActivityProposal $proposal): JsonResponse
    {
        $validated = $request->validate([
            'comments' => ['required', 'string', 'max:5000'],
        ]);

        return $this->dispatchDecision(
            $request,
            $proposal,
            'reject',
            (string) $validated['comments'],
            [],
            'Proposal rejected.'
        );
    }

    /**
     * @param  array<int, array{field_key: string, field_label: string, status: string, comment: ?string}>  $fieldReviews
     */
    private function dispatchDecision(
        Request $request,
        ActivityProposal $proposal,
        string $action,
        string $comments,
        array $fieldReviews,
        string $successMessage
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return $this->unauthorized();
        }

        $currentStep = $this->workflow->currentStepForUser($proposal, $user);
        if (! $currentStep) {
            return response()->json([
                'message' => 'You are not the assigned approver for the current step of this proposal.',
            ], 403);
        }

        $result = $this->workflow->decideOnProposal(
            $proposal,
            $currentStep,
            $user,
            $action,
            trim($comments),
            $fieldReviews
        );

        $newStatusKey = $this->statusToApiKey($result['to_status'], $result['action']);

        return response()->json([
            'message' => $successMessage,
            'data' => [
                'proposal_id' => (int) $proposal->id,
                'workflow_step_id' => (int) $result['workflow_step_id'],
                'new_status' => $newStatusKey,
                'next_step' => $result['next_step'],
            ],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawReviews
     * @return array<int, array{field_key: string, field_label: string, status: string, comment: ?string}>
     */
    private function normalizeFieldReviews(array $rawReviews): array
    {
        $rows = [];
        foreach ($rawReviews as $review) {
            $key = isset($review['field_key']) ? trim((string) $review['field_key']) : '';
            if ($key === '') {
                continue;
            }
            $status = (string) ($review['status'] ?? '');
            if (! in_array($status, ['passed', 'revision'], true)) {
                continue;
            }
            $rows[] = [
                'field_key' => $key,
                'field_label' => trim((string) ($review['field_label'] ?? $key)),
                'status' => $status,
                'comment' => isset($review['comment']) ? trim((string) $review['comment']) : null,
            ];
        }

        return $rows;
    }

    private function statusToApiKey(string $toStatus, string $action): string
    {
        return match (strtoupper($toStatus)) {
            'APPROVED' => 'final_approved',
            'UNDER_REVIEW' => 'pending_next_approval',
            'REVISION' => 'revision_required',
            'REJECTED' => 'rejected',
            default => strtolower($toStatus ?: $action),
        };
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['message' => 'Unauthorized.'], 401);
    }
}
