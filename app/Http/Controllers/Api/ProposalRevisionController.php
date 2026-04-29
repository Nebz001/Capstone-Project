<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityProposal;
use App\Models\ApprovalLog;
use App\Models\ApprovalWorkflowStep;
use App\Models\ProposalFieldReview;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProposalRevisionController extends Controller
{
    public function __construct(
        private readonly ApprovalWorkflowService $workflow
    ) {
    }

    /**
     * GET /api/proposals/{proposal}/revisions
     *
     * Combines field-level revisions, revision-related approval logs, and
     * workflow steps that are currently in revision_required state. Sorted
     * newest-first so the mobile UI can render a single revision timeline.
     */
    public function index(Request $request, ActivityProposal $proposal): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (! $this->workflow->canViewProposal($user, $proposal)) {
            return response()->json(['message' => 'You do not have access to this proposal.'], 403);
        }

        $rows = collect();

        $fieldReviews = ProposalFieldReview::query()
            ->where('activity_proposal_id', $proposal->id)
            ->where('status', 'revision')
            ->with('reviewer:id,first_name,last_name')
            ->orderByDesc('reviewed_at')
            ->get();

        foreach ($fieldReviews as $review) {
            $rows->push([
                'id' => 'field_'.(int) $review->id,
                'type' => 'field_revision',
                'field_key' => (string) $review->field_key,
                'field_label' => (string) $review->field_label,
                'comment' => $review->comment,
                'status' => (string) $review->status,
                'reviewer_name' => $review->reviewer
                    ? trim($review->reviewer->first_name.' '.$review->reviewer->last_name)
                    : null,
                'created_at' => optional($review->reviewed_at ?? $review->created_at)->toIso8601String(),
            ]);
        }

        $logs = ApprovalLog::query()
            ->where('approvable_type', ActivityProposal::class)
            ->where('approvable_id', $proposal->id)
            ->where('action', 'revision_requested')
            ->with('actor:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->get();

        foreach ($logs as $log) {
            $rows->push([
                'id' => 'log_'.(int) $log->id,
                'type' => 'workflow_revision',
                'field_key' => null,
                'field_label' => null,
                'comment' => $log->comments,
                'status' => 'revision',
                'reviewer_name' => $log->actor
                    ? trim($log->actor->first_name.' '.$log->actor->last_name)
                    : null,
                'created_at' => optional($log->created_at)->toIso8601String(),
            ]);
        }

        $revisionSteps = $proposal->workflowSteps()
            ->where('status', 'revision_required')
            ->with(['role:id,name,display_name', 'assignedTo:id,first_name,last_name'])
            ->orderByDesc('acted_at')
            ->get();

        foreach ($revisionSteps as $step) {
            $rows->push([
                'id' => 'step_'.(int) $step->id,
                'type' => 'workflow_step_revision',
                'field_key' => null,
                'field_label' => (string) ($step->role?->display_name ?? $step->role?->name ?? ''),
                'comment' => $step->review_comments,
                'status' => 'revision_required',
                'reviewer_name' => $step->assignedTo
                    ? trim($step->assignedTo->first_name.' '.$step->assignedTo->last_name)
                    : null,
                'created_at' => optional($step->acted_at)->toIso8601String(),
            ]);
        }

        $sorted = $rows
            ->sortByDesc(fn (array $row) => $row['created_at'] ?? '')
            ->values();

        return response()->json(['data' => $sorted]);
    }

    /**
     * GET /api/proposals/{proposal}/field-reviews
     */
    public function fieldReviews(Request $request, ActivityProposal $proposal): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (! $this->workflow->canViewProposal($user, $proposal)) {
            return response()->json(['message' => 'You do not have access to this proposal.'], 403);
        }

        $reviews = ProposalFieldReview::query()
            ->where('activity_proposal_id', $proposal->id)
            ->with('reviewer:id,first_name,last_name')
            ->orderBy('field_key')
            ->get();

        $data = $reviews->map(fn ($review): array => [
            'field_key' => (string) $review->field_key,
            'field_label' => (string) $review->field_label,
            'status' => (string) $review->status,
            'comment' => $review->comment,
            'reviewer_name' => $review->reviewer
                ? trim($review->reviewer->first_name.' '.$review->reviewer->last_name)
                : null,
            'reviewed_at' => optional($review->reviewed_at)->toIso8601String(),
        ])->values();

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/proposals/{proposal}/field-reviews
     *
     * Saves field reviews for the current step using the same persistence
     * primitive as the web flow (ProposalFieldReview::updateOrCreate). This
     * does NOT advance the workflow — see /api/proposals/{p}/return or
     * /approve for that.
     */
    public function storeFieldReviews(Request $request, ActivityProposal $proposal): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $currentStep = $this->workflow->currentStepForUser($proposal, $user);
        if (! $currentStep instanceof ApprovalWorkflowStep) {
            return response()->json([
                'message' => 'You are not the assigned approver for the current step of this proposal.',
            ], 403);
        }

        $validated = $request->validate([
            'field_reviews' => ['required', 'array', 'min:1'],
            'field_reviews.*.field_key' => ['required', 'string', 'max:120'],
            'field_reviews.*.field_label' => ['required', 'string', 'max:255'],
            'field_reviews.*.status' => ['required', Rule::in(['passed', 'revision'])],
            'field_reviews.*.comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $rows = [];
        foreach ((array) $validated['field_reviews'] as $review) {
            $status = (string) $review['status'];
            $comment = isset($review['comment']) ? trim((string) $review['comment']) : null;
            if ($status === 'revision' && ($comment === null || $comment === '')) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        "field_reviews.{$review['field_key']}.comment" => ['Revision note is required when marking a field as Revision.'],
                    ],
                ], 422);
            }
            $rows[] = [
                'field_key' => trim((string) $review['field_key']),
                'field_label' => trim((string) $review['field_label']),
                'status' => $status,
                'comment' => $comment,
            ];
        }

        $persisted = $this->workflow->saveFieldReviews($proposal, $currentStep, $user, $rows);

        $payload = $persisted
            ->map(fn ($review): array => [
                'field_key' => (string) $review->field_key,
                'field_label' => (string) $review->field_label,
                'status' => (string) $review->status,
                'comment' => $review->comment,
                'reviewed_at' => optional($review->reviewed_at)->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'message' => 'Field reviews saved.',
            'data' => $payload,
        ]);
    }
}
