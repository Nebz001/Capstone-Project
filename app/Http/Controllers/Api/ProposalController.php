<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityProposal;
use App\Models\ApprovalWorkflowStep;
use App\Models\Attachment;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ProposalController extends Controller
{
    public function __construct(
        private readonly ApprovalWorkflowService $workflow
    ) {
    }

    /**
     * GET /api/proposals/{proposal}
     */
    public function show(Request $request, ActivityProposal $proposal): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (! $this->workflow->canViewProposal($user, $proposal)) {
            return response()->json(['message' => 'You do not have access to this proposal.'], 403);
        }

        $proposal->load([
            'organization:id,organization_name,college_school',
            'submittedBy:id,first_name,last_name,email',
            'academicTerm:id,academic_year,semester',
            'workflowSteps.role:id,name,display_name',
            'workflowSteps.assignedTo:id,first_name,last_name',
            'attachments',
            'fieldReviews.reviewer:id,first_name,last_name',
        ]);

        $currentStep = $proposal->workflowSteps->firstWhere('is_current_step', true);

        $revisionComments = $proposal->workflowSteps
            ->filter(fn (ApprovalWorkflowStep $step): bool => $step->status === 'revision_required'
                && is_string($step->review_comments)
                && trim($step->review_comments) !== '')
            ->map(fn (ApprovalWorkflowStep $step): array => [
                'workflow_step_id' => (int) $step->id,
                'step_order' => (int) $step->step_order,
                'role_name' => (string) ($step->role?->display_name ?? $step->role?->name ?? ''),
                'comment' => (string) $step->review_comments,
                'acted_at' => optional($step->acted_at)->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'id' => (int) $proposal->id,
                'activity_title' => $proposal->activity_title,
                'activity_description' => $proposal->activity_description,
                'venue' => $proposal->venue,
                'overall_goal' => $proposal->overall_goal,
                'specific_objectives' => $proposal->specific_objectives,
                'criteria_mechanics' => $proposal->criteria_mechanics,
                'program_flow' => $proposal->program_flow,
                'target_sdg' => $proposal->target_sdg,
                'estimated_budget' => $proposal->estimated_budget,
                'source_of_funding' => $proposal->source_of_funding,
                'school_code' => $proposal->school_code,
                'program' => $proposal->program,
                'proposed_start_date' => optional($proposal->proposed_start_date)->toDateString(),
                'proposed_end_date' => optional($proposal->proposed_end_date)->toDateString(),
                'proposed_start_time' => $proposal->proposed_start_time,
                'proposed_end_time' => $proposal->proposed_end_time,
                'submission_date' => optional($proposal->submission_date)->toDateString(),
                'status' => strtolower((string) ($proposal->status ?? 'pending')),
                'current_approval_step' => (int) ($proposal->current_approval_step ?? 0),

                'organization' => $proposal->organization ? [
                    'id' => (int) $proposal->organization->id,
                    'name' => $proposal->organization->organization_name,
                    'college_school' => $proposal->organization->college_school,
                ] : null,
                'submitted_by' => $proposal->submittedBy ? [
                    'id' => (int) $proposal->submittedBy->id,
                    'name' => trim($proposal->submittedBy->first_name.' '.$proposal->submittedBy->last_name),
                    'email' => $proposal->submittedBy->email,
                ] : null,
                'academic_term' => $proposal->academicTerm ? [
                    'id' => (int) $proposal->academicTerm->id,
                    'academic_year' => $proposal->academicTerm->academic_year,
                    'semester' => $proposal->academicTerm->semester,
                ] : null,

                'current_step' => $currentStep ? [
                    'workflow_step_id' => (int) $currentStep->id,
                    'step_order' => (int) $currentStep->step_order,
                    'role_name' => (string) ($currentStep->role?->display_name ?? $currentStep->role?->name ?? ''),
                    'status' => (string) $currentStep->status,
                    'assigned_to' => $currentStep->assignedTo ? trim($currentStep->assignedTo->first_name.' '.$currentStep->assignedTo->last_name) : null,
                ] : null,

                'workflow_summary' => $proposal->workflowSteps
                    ->map(fn (ApprovalWorkflowStep $step): array => $this->mapWorkflowStep($step))
                    ->values(),

                'field_reviews' => $proposal->fieldReviews
                    ->map(fn ($review): array => [
                        'field_key' => (string) $review->field_key,
                        'field_label' => (string) $review->field_label,
                        'status' => (string) $review->status,
                        'comment' => $review->comment,
                        'reviewer_name' => $review->reviewer
                            ? trim($review->reviewer->first_name.' '.$review->reviewer->last_name)
                            : null,
                        'reviewed_at' => optional($review->reviewed_at)->toIso8601String(),
                    ])
                    ->values(),

                'revision_comments' => $revisionComments,

                'attachments' => $proposal->attachments
                    ->map(fn (Attachment $attachment): array => $this->mapAttachmentSummary($attachment))
                    ->values(),
            ],
        ]);
    }

    /**
     * GET /api/proposals/{proposal}/workflow
     */
    public function workflow(Request $request, ActivityProposal $proposal): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (! $this->workflow->canViewProposal($user, $proposal)) {
            return response()->json(['message' => 'You do not have access to this proposal.'], 403);
        }

        $proposal->load([
            'workflowSteps.role:id,name,display_name',
            'workflowSteps.assignedTo:id,first_name,last_name',
        ]);

        $data = $proposal->workflowSteps
            ->sortBy('step_order')
            ->map(fn (ApprovalWorkflowStep $step): array => $this->mapWorkflowStep($step))
            ->values();

        return response()->json(['data' => $data]);
    }

    private function mapWorkflowStep(ApprovalWorkflowStep $step): array
    {
        return [
            'step_id' => (int) $step->id,
            'step_order' => (int) $step->step_order,
            'role_name' => (string) ($step->role?->display_name ?? $step->role?->name ?? ''),
            'status' => (string) $step->status,
            'is_current_step' => (bool) $step->is_current_step,
            'assigned_to' => $step->assignedTo
                ? trim($step->assignedTo->first_name.' '.$step->assignedTo->last_name)
                : null,
            'review_comments' => $step->review_comments,
            'acted_at' => optional($step->acted_at)->toIso8601String(),
        ];
    }

    private function mapAttachmentSummary(Attachment $attachment): array
    {
        return [
            'id' => (int) $attachment->id,
            'file_type' => (string) $attachment->file_type,
            'original_name' => (string) $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'file_size_kb' => $attachment->file_size_kb !== null ? (int) $attachment->file_size_kb : null,
            'view_url' => URL::to('/api/attachments/'.$attachment->id.'/view'),
            'download_url' => URL::to('/api/attachments/'.$attachment->id.'/download'),
        ];
    }
}
