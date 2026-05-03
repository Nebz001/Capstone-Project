<?php

namespace App\Http\Controllers;

use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\ActivityReport;
use App\Models\ActivityRequestForm;
use App\Models\ApprovalLog;
use App\Models\ApprovalWorkflowStep;
use App\Models\Attachment;
use App\Models\Notification;
use App\Models\OrganizationSubmission;
use App\Models\ProposalFieldReview;
use App\Models\User;
use App\Services\OrganizationNotificationService;
use App\Services\ReviewWorkflow\ActivityProposalAdminFieldReviewSync;
use App\Services\ReviewWorkflow\ReviewableUpdateRecorder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ApproverDashboardController extends Controller
{
    private const OVERDUE_DAYS = 5;

    public function dashboard(Request $request): View
    {
        $user = $this->requireApprover($request);
        $allowMultiDocumentTypes = $this->approverCanReviewAllDocumentTypes($user);

        $documentType = $allowMultiDocumentTypes
            ? (string) $request->string('document_type', '')
            : 'activity_proposal';
        $status = strtolower((string) $request->string('status', ''));
        $organizationSearch = trim((string) $request->string('organization', ''));
        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');

        $pendingSteps = $this->basePendingQueueQuery($user, $allowMultiDocumentTypes)
            ->orderBy('created_at')
            ->get();
        $this->loadApprovableRelations($pendingSteps);

        $pendingItems = $pendingSteps
            ->map(fn (ApprovalWorkflowStep $step): ?array => $this->mapStepToQueueItem($step))
            ->filter()
            ->values();

        $filteredPendingItems = $pendingItems->filter(function (array $item) use ($documentType, $status, $organizationSearch, $dateFrom, $dateTo): bool {
            if ($documentType !== '' && $item['document_type_key'] !== $documentType) {
                return false;
            }
            if ($status !== '' && $item['status_key'] !== $status) {
                return false;
            }
            if ($organizationSearch !== '' && ! str_contains(strtolower($item['organization_name']), strtolower($organizationSearch))) {
                return false;
            }

            $submittedAt = $item['submitted_at'] instanceof Carbon ? $item['submitted_at'] : null;
            if ($dateFrom && $submittedAt && $submittedAt->lt($dateFrom->copy()->startOfDay())) {
                return false;
            }
            if ($dateTo && $submittedAt && $submittedAt->gt($dateTo->copy()->endOfDay())) {
                return false;
            }

            return true;
        })->values();

        $approvablesForPending = $pendingSteps
            ->map(fn (ApprovalWorkflowStep $step): string => $step->approvable_type.'#'.$step->approvable_id)
            ->unique()
            ->values();
        $resubmittedKeys = $this->resolveResubmittedKeys($approvablesForPending);

        $overdueItems = $pendingItems
            ->filter(fn (array $item): bool => $item['pending_days'] >= self::OVERDUE_DAYS)
            ->sortByDesc('pending_days')
            ->take(8)
            ->values();

        $needsAttentionItems = $pendingItems
            ->filter(function (array $item) use ($resubmittedKeys): bool {
                return $item['pending_days'] >= self::OVERDUE_DAYS
                    || in_array($item['approvable_key'], $resubmittedKeys, true);
            })
            ->sortByDesc('pending_days')
            ->take(10)
            ->values();

        $recentRoutedSteps = ApprovalWorkflowStep::query()
            ->where('role_id', $user->role_id)
            ->where(function ($query) use ($user): void {
                $query->whereNull('assigned_to')
                    ->orWhere('assigned_to', $user->id);
            })
            ->when(! $allowMultiDocumentTypes, fn ($q) => $q->where('approvable_type', ActivityProposal::class))
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get();
        $this->loadApprovableRelations($recentRoutedSteps);

        $recentRoutedItems = $recentRoutedSteps
            ->map(fn (ApprovalWorkflowStep $step): ?array => $this->mapStepToQueueItem($step))
            ->filter()
            ->values();

        $recentActions = ApprovalLog::query()
            ->with(['actor:id,first_name,last_name'])
            ->where('actor_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->where(function ($query): void {
                $query->where('type', 'like', '%approval%')
                    ->orWhere('type', 'like', '%workflow%')
                    ->orWhere('title', 'like', '%assigned%')
                    ->orWhere('title', 'like', '%revision%')
                    ->orWhere('title', 'like', '%pending%');
            })
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $approvedWeekCount = ApprovalLog::query()
            ->where('actor_id', $user->id)
            ->where('action', 'approved')
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $rejectedReturnedWeekCount = ApprovalLog::query()
            ->where('actor_id', $user->id)
            ->whereIn('action', ['rejected', 'revision_requested'])
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $scheduleContext = $pendingItems
            ->filter(fn (array $item): bool => $item['event_date'] instanceof Carbon)
            ->sortBy('event_date')
            ->take(5)
            ->values();

        return view('approver.dashboard', [
            'approverLabel' => $user->role?->display_name ?? 'Approver',
            'summary' => [
                'pending_approvals' => $pendingItems->count(),
                'revision_follow_up' => count($resubmittedKeys),
                'approved_week' => $approvedWeekCount,
                'rejected_or_returned_week' => $rejectedReturnedWeekCount,
                'overdue_items' => $overdueItems->count(),
            ],
            'pendingItems' => $filteredPendingItems,
            'needsAttentionItems' => $needsAttentionItems,
            'overdueItems' => $overdueItems,
            'recentRoutedItems' => $recentRoutedItems,
            'recentActions' => $recentActions,
            'notifications' => $notifications,
            'scheduleContext' => $scheduleContext,
            'filters' => [
                'document_type' => $documentType,
                'status' => $status,
                'organization' => $organizationSearch,
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
            ],
            'allowMultiDocumentTypes' => $allowMultiDocumentTypes,
        ]);
    }

    public function showAssignment(Request $request, ApprovalWorkflowStep $step): View
    {
        $user = $this->requireApprover($request);
        $step = $this->resolveAuthorizedCurrentStep($step, $user);
        $approvable = $step->approvable;

        if (! $approvable) {
            abort(404, 'Assigned document no longer exists.');
        }

        $this->loadApprovableWithWorkflow($approvable);
        $details = $this->buildReviewDetails($approvable, $step);

        return view('approver.assignment-review', [
            'pageTitle' => $details['page_title'],
            'status' => strtoupper((string) ($details['scoped_status'] ?? ($approvable->status ?? 'pending'))),
            'details' => $details['details'],
            'detailSections' => $details['detail_sections'] ?? [],
            'proposalFileLinks' => $details['proposal_file_links'] ?? [],
            'isProposalReview' => (bool) ($details['is_proposal_review'] ?? false),
            'calendarEntries' => $details['calendar_entries'],
            'workflowSteps' => $approvable->workflowSteps,
            'workflowLogs' => $approvable->approvalLogs()->with('actor:id,first_name,last_name')->latest('created_at')->limit(15)->get(),
            'workflowCurrentStep' => $step,
            'workflowActionRoute' => route('approver.assignments.decide', $step),
        ]);
    }

    public function streamAssignmentProposalFile(Request $request, ApprovalWorkflowStep $step, string $key): Response
    {
        $user = $this->requireApprover($request);
        $step = $this->resolveAuthorizedCurrentStep($step, $user);
        $approvable = $step->approvable;
        if (! $approvable instanceof ActivityProposal) {
            abort(404);
        }

        $proposal = $approvable;
        $requestForm = $this->relatedRequestFormForProposal($proposal);
        $relativePath = $this->proposalReviewFilePathByKey($proposal, $requestForm, $key);
        if (! is_string($relativePath) || trim($relativePath) === '') {
            abort(404);
        }

        $relativePath = $this->normalizeStoredPublicPathApprover($relativePath) ?? '';
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            abort(404);
        }

        Log::info('Approver: activity proposal file view attempt', [
            'proposal_id' => $proposal->id,
            'key' => $key,
            'stored_path' => $relativePath,
        ]);

        $disk = Storage::disk('supabase');
        $existsInSupabase = false;

        try {
            $existsInSupabase = $disk->exists($relativePath);
        } catch (\Throwable $e) {
            Log::warning('Approver: proposal attachment exists() check failed on Supabase.', [
                'proposal_id' => $proposal->id,
                'stored_path' => $relativePath,
                'error' => $e->getMessage(),
            ]);
        }

        if ($existsInSupabase) {
            try {
                $temporaryUrl = $disk->temporaryUrl($relativePath, now()->addMinutes(15));

                return redirect()->away($temporaryUrl);
            } catch (\Throwable $e) {
                Log::warning('Approver: failed to generate Supabase temporaryUrl for proposal attachment.', [
                    'proposal_id' => $proposal->id,
                    'stored_path' => $relativePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($relativePath)) {
            Log::warning('Approver: proposal attachment found on local public disk only (legacy).', [
                'proposal_id' => $proposal->id,
                'stored_path' => $relativePath,
            ]);

            return $publicDisk->response($relativePath, basename($relativePath), [], 'inline');
        }

        abort(404, 'The file could not be found in Supabase Storage.');
    }

    public function downloadAssignmentProposalFile(Request $request, ApprovalWorkflowStep $step, string $key): Response
    {
        $user = $this->requireApprover($request);
        $step = $this->resolveAuthorizedCurrentStep($step, $user);
        $approvable = $step->approvable;
        if (! $approvable instanceof ActivityProposal) {
            abort(404);
        }

        $proposal = $approvable;
        $requestForm = $this->relatedRequestFormForProposal($proposal);
        [$attachment, $relativePath] = $this->proposalReviewAttachmentAndNormalizedPath($proposal, $requestForm, $key);
        if (! is_string($relativePath) || $relativePath === '') {
            abort(404);
        }

        $downloadName = trim((string) ($attachment?->original_name ?? ''));
        if ($downloadName === '') {
            $downloadName = basename($relativePath);
        }
        $mime = $attachment && $attachment->mime_type ? (string) $attachment->mime_type : null;

        Log::info('Approver: activity proposal file download attempt', [
            'proposal_id' => $proposal->id,
            'key' => $key,
            'stored_path' => $relativePath,
        ]);

        return $this->redirectApproverProposalAttachmentDownload(
            $relativePath,
            $downloadName,
            $mime,
            [
                'proposal_id' => $proposal->id,
                'key' => $key,
                'download' => true,
            ]
        );
    }

    public function decide(Request $request, ApprovalWorkflowStep $step): RedirectResponse
    {
        $user = $this->requireApprover($request);
        $currentStep = $this->resolveAuthorizedCurrentStep($step, $user);
        $approvable = $currentStep->approvable;

        if (! $approvable) {
            abort(404, 'Assigned document no longer exists.');
        }

        if ($approvable instanceof ActivityProposal) {
            if (! Schema::hasTable('proposal_field_reviews')) {
                return back()->withErrors([
                    'field_reviews' => 'Field-level review storage is unavailable. Please run migrations and try again.',
                ]);
            }

            $this->loadApprovableWithWorkflow($approvable);
            $detailSnapshot = $this->buildReviewDetails($approvable, $currentStep);
            $reviewableKeys = collect((array) ($detailSnapshot['detail_sections'] ?? []))
                ->flatMap(fn (array $section): array => (array) ($section['rows'] ?? []))
                ->filter(fn (array $row): bool => (bool) ($row['reviewable'] ?? true))
                ->map(fn (array $row): string => (string) ($row['key'] ?? ''))
                ->filter(fn (string $key): bool => $key !== '')
                ->values()
                ->all();

            $validated = $request->validate([
                'field_reviews' => ['nullable', 'array'],
                'field_reviews.*.status' => ['nullable', Rule::in(['passed', 'revision'])],
                'field_reviews.*.label' => ['nullable', 'string', 'max:255'],
                'field_reviews.*.comment' => ['nullable', 'string', 'max:2000'],
            ]);

            $fieldReviews = collect((array) ($validated['field_reviews'] ?? []))
                ->filter(fn (array $review, string $fieldKey): bool => in_array((string) $fieldKey, $reviewableKeys, true))
                ->map(function (array $review, string $fieldKey): array {
                    $status = (string) ($review['status'] ?? 'pending');
                    if (! in_array($status, ['pending', 'passed', 'revision'], true)) {
                        $status = 'pending';
                    }

                    return [
                        'field_key' => (string) $fieldKey,
                        'field_label' => trim((string) ($review['label'] ?? $fieldKey)),
                        'status' => $status,
                        'comment' => trim((string) ($review['comment'] ?? '')),
                    ];
                })
                ->filter(fn (array $row): bool => $row['status'] !== 'pending')
                ->values();

            $missingReasonField = $fieldReviews
                ->first(fn (array $row): bool => $row['status'] === 'revision' && $row['comment'] === '');
            if ($missingReasonField) {
                return back()
                    ->withInput()
                    ->withErrors([
                        "field_reviews.{$missingReasonField['field_key']}.comment" => 'Revision note is required when marking a field as Revision.',
                    ]);
            }

            $action = 'approve';
            $comments = '';
        } else {
            $validated = $request->validate([
                'action' => ['required', Rule::in(['approve', 'reject', 'revision'])],
                'comments' => ['nullable', 'string', 'max:5000'],
            ]);
            $action = (string) $validated['action'];
            $comments = trim((string) ($validated['comments'] ?? ''));
            $fieldReviews = collect();
            $reviewableKeys = [];
        }

        $fromStatus = strtoupper((string) ($approvable->status ?? 'PENDING'));
        $resultMode = 'final';
        $missingFieldLabels = [];

        $resolvedToStatus = strtoupper((string) ($approvable->status ?? 'PENDING'));
        DB::transaction(function () use ($approvable, $currentStep, $action, $comments, $fromStatus, $user, $fieldReviews, $reviewableKeys, &$resultMode, &$missingFieldLabels, &$resolvedToStatus): void {
            if ($approvable instanceof ActivityProposal && Schema::hasTable('proposal_field_reviews')) {
                if (count($reviewableKeys) === 0) {
                    $resultMode = 'incomplete';
                    $missingFieldLabels = ['No reviewable fields were found for this step.'];

                    return;
                }

                foreach ($fieldReviews as $row) {
                    ProposalFieldReview::query()->updateOrCreate(
                        [
                            'activity_proposal_id' => $approvable->id,
                            'workflow_step_id' => $currentStep->id,
                            'field_key' => $row['field_key'],
                        ],
                        [
                            'reviewer_id' => $user->id,
                            'field_label' => $row['field_label'],
                            'status' => $row['status'] === 'passed' ? 'approved' : 'revision',
                            'comment' => $row['comment'] !== '' ? $row['comment'] : null,
                            'reviewed_at' => now(),
                        ]
                    );
                }

                $persistedReviews = ProposalFieldReview::query()
                    ->where('activity_proposal_id', $approvable->id)
                    ->where('workflow_step_id', $currentStep->id)
                    ->whereIn('field_key', $reviewableKeys)
                    ->get()
                    ->keyBy('field_key');

                $statuses = collect($reviewableKeys)
                    ->map(fn (string $key): ?string => $persistedReviews->get($key)?->status)
                    ->filter()
                    ->values();
                $unreviewedCount = count($reviewableKeys) - $statuses->count();

                if ($unreviewedCount > 0) {
                    $resultMode = 'incomplete';
                    $missingFieldLabels = collect($reviewableKeys)
                        ->filter(fn (string $key): bool => ! $persistedReviews->has($key))
                        ->map(function (string $key) use ($fieldReviews): string {
                            $incoming = $fieldReviews->firstWhere('field_key', $key);

                            return (string) ($incoming['field_label'] ?? str_replace('_', ' ', $key));
                        })
                        ->values()
                        ->all();

                    return;
                }

                $hasRevision = $statuses->contains('revision');
                $action = $hasRevision ? 'revision' : 'approve';
                $comments = $fieldReviews
                    ->filter(fn (array $row): bool => $row['status'] === 'revision')
                    ->map(fn (array $row): string => "{$row['field_label']}: {$row['comment']}")
                    ->values()
                    ->implode(PHP_EOL);
                if ($comments === '' && $action !== 'approve') {
                    $comments = 'Field-level review marked this proposal for '.$action.'.';
                }

                app(ActivityProposalAdminFieldReviewSync::class)->syncFromProposalFieldReviews(
                    $approvable,
                    $persistedReviews,
                    $reviewableKeys,
                );
            }

            $toStatus = $fromStatus;
            $logAction = 'approved';
            $currentApprovalStep = (int) $currentStep->step_order;

            if ($action === 'approve') {
                $currentStep->update([
                    'assigned_to' => $user->id,
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
                } else {
                    $toStatus = 'APPROVED';
                }
            } elseif ($action === 'revision') {
                $currentStep->update([
                    'assigned_to' => $user->id,
                    'status' => 'revision_required',
                    'is_current_step' => false,
                    'review_comments' => $comments !== '' ? $comments : null,
                    'acted_at' => now(),
                ]);
                $toStatus = 'REVISION';
                $logAction = 'revision_requested';
            } else {
                $currentStep->update([
                    'assigned_to' => $user->id,
                    'status' => 'rejected',
                    'is_current_step' => false,
                    'review_comments' => $comments !== '' ? $comments : null,
                    'acted_at' => now(),
                ]);
                $toStatus = 'REJECTED';
                $logAction = 'rejected';
            }

            $approvable->update([
                'status' => strtolower($toStatus),
                'current_approval_step' => $currentApprovalStep,
            ]);

            ApprovalLog::query()->create([
                'approvable_type' => $currentStep->approvable_type,
                'approvable_id' => $currentStep->approvable_id,
                'workflow_step_id' => $currentStep->id,
                'actor_id' => $user->id,
                'action' => $logAction,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'comments' => $comments !== '' ? $comments : null,
                'created_at' => now(),
            ]);
            $resolvedToStatus = $toStatus;
        });

        if ($resultMode === 'incomplete') {
            $message = 'All required fields must be reviewed before submitting this approver step.';
            if (count($missingFieldLabels) > 0) {
                $message .= ' Missing: '.implode(', ', $missingFieldLabels).'.';
            }

            return back()
                ->withInput()
                ->withErrors(['field_reviews' => $message]);
        }

        $this->notifyOrganizationSubmissionResult($approvable, $resolvedToStatus);

        return redirect()
            ->route('approver.dashboard')
            ->with('success', 'Approval decision saved.');
    }

    private function requireApprover(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user || ! $user->isRoleBasedApprover()) {
            abort(403, 'Only adviser, program chair, and dean accounts can access this dashboard.');
        }

        return $user;
    }

    private function basePendingQueueQuery(User $user, bool $allowMultiDocumentTypes)
    {
        return ApprovalWorkflowStep::query()
            ->where('role_id', (int) $user->role_id)
            ->where('is_current_step', true)
            ->whereIn('status', ['pending', 'under_review', 'revision_required'])
            ->when(! $allowMultiDocumentTypes, fn ($q) => $q->where('approvable_type', ActivityProposal::class))
            ->where(function ($query) use ($user): void {
                $query->whereNull('assigned_to')
                    ->orWhere('assigned_to', $user->id);
            });
    }

    private function approverCanReviewAllDocumentTypes(User $user): bool
    {
        return in_array((string) $user->role?->name, ['sdao_staff', 'academic_director', 'executive_director', 'admin'], true);
    }

    private function loadApprovableRelations(Collection $steps): void
    {
        $steps->load('role:id,name,display_name');
        $steps->loadMorph('approvable', [
            OrganizationSubmission::class => ['organization:id,organization_name', 'submittedBy:id,first_name,last_name'],
            ActivityCalendar::class => ['organization:id,organization_name', 'submittedBy:id,first_name,last_name'],
            ActivityProposal::class => ['organization:id,organization_name', 'submittedBy:id,first_name,last_name'],
            ActivityReport::class => ['organization:id,organization_name', 'submittedBy:id,first_name,last_name'],
        ]);
    }

    private function mapStepToQueueItem(ApprovalWorkflowStep $step): ?array
    {
        $approvable = $step->approvable;
        if (! $approvable) {
            return null;
        }

        $documentType = $this->resolveDocumentType($approvable);
        $submittedAt = $this->resolveSubmissionDate($approvable);
        $status = strtolower((string) ($approvable->status ?? 'pending'));
        $organizationName = (string) ($approvable->organization?->organization_name ?? 'N/A');
        $submittedBy = (string) ($approvable->submittedBy?->full_name ?? 'N/A');
        $pendingDays = $submittedAt ? (int) $submittedAt->startOfDay()->diffInDays(now()->startOfDay()) : 0;

        return [
            'workflow_step_id' => (int) $step->id,
            'approvable_key' => $step->approvable_type.'#'.$step->approvable_id,
            'document_type_key' => $documentType['key'],
            'document_type_label' => $documentType['label'],
            'organization_name' => $organizationName,
            'submitted_by' => $submittedBy,
            'submitted_at' => $submittedAt,
            'submitted_at_label' => $submittedAt ? $submittedAt->format('M d, Y') : 'N/A',
            'current_approval_step' => (int) ($approvable->current_approval_step ?? $step->step_order),
            'pending_for_label' => $submittedAt ? $submittedAt->diffForHumans(now(), true) : 'N/A',
            'pending_days' => $pendingDays,
            'status_key' => $status,
            'status_label' => strtoupper(str_replace('_', ' ', $status)),
            'status_class' => $this->statusClass($status),
            'review_url' => route('approver.assignments.show', $step),
            'event_date' => $this->resolveEventDate($approvable),
        ];
    }

    private function resolveAuthorizedCurrentStep(ApprovalWorkflowStep $step, User $user): ApprovalWorkflowStep
    {
        $step->load('approvable');

        if (! $step->is_current_step || (int) $step->role_id !== (int) $user->role_id) {
            abort(403, 'This document is not currently assigned to your role stage.');
        }

        if ($step->assigned_to !== null && (int) $step->assigned_to !== (int) $user->id) {
            abort(403, 'This document is assigned to a different approver.');
        }

        return $step;
    }

    private function loadApprovableWithWorkflow(Model $approvable): void
    {
        $approvable->load([
            'organization:id,organization_name',
            'submittedBy:id,first_name,last_name',
            'workflowSteps.role:id,name,display_name',
            'workflowSteps.assignedTo:id,first_name,last_name',
        ]);

        if ($approvable instanceof ActivityCalendar) {
            $approvable->load(['entries' => fn ($query) => $query->orderBy('activity_date')->orderBy('id')]);
        } elseif ($approvable instanceof ActivityProposal) {
            $approvable->load(['calendar', 'calendarEntry', 'academicTerm', 'attachments', 'budgetItems']);
        }
    }

    private function buildReviewDetails(Model $approvable, ApprovalWorkflowStep $step): array
    {
        $base = [
            'Document Type' => $this->resolveDocumentType($approvable)['label'],
            'Organization' => $approvable->organization?->organization_name ?? 'N/A',
            'Submitted By' => $approvable->submittedBy?->full_name ?? 'N/A',
            'Submitted On' => $this->resolveSubmissionDate($approvable)?->format('M d, Y') ?? 'N/A',
            'Current Step' => '#'.$step->step_order.' — '.($step->role?->display_name ?? $step->role?->name ?? 'Unassigned'),
        ];

        if ($approvable instanceof OrganizationSubmission) {
            $base['Submission Type'] = strtoupper((string) ($approvable->type ?? ''));
            $base['Contact Person'] = $approvable->contact_person ?? 'N/A';
            $base['Contact Email'] = $approvable->contact_email ?? 'N/A';
            $base['Contact No'] = $approvable->contact_no ?? 'N/A';
        } elseif ($approvable instanceof ActivityCalendar) {
            $base['Activities Count'] = (string) $approvable->entries->count();
        } elseif ($approvable instanceof ActivityProposal) {
            $existingFieldReviews = Schema::hasTable('proposal_field_reviews')
                ? ProposalFieldReview::query()
                    ->where('activity_proposal_id', $approvable->id)
                    ->where('workflow_step_id', $step->id)
                    ->get()
                    ->keyBy('field_key')
                : collect();
            $requestForm = $this->relatedRequestFormForProposal($approvable);
            $linkedCalendar = $approvable->calendar
                ? trim(($approvable->calendar->academic_year ?? '—').' · '.(string) ($approvable->calendar->semester ?? '—'))
                : '—';
            $calendarRow = $approvable->calendarEntry
                ? trim(($approvable->calendarEntry->activity_name ?? '—').' · '.(optional($approvable->calendarEntry->activity_date)->format('M j, Y') ?? ''))
                : '—';
            $proposalTime = $this->proposalTimeRangeLabel($approvable);
            $department = (string) ($approvable->school_code ?: ($approvable->organization?->college_school ?? ''));
            $step1ActivityDate = $requestForm?->activity_date
                ? optional($requestForm->activity_date)->format('M d, Y')
                : null;
            $sourceOfFunding = (string) ($approvable->source_of_funding ?? '');
            $isExternalFunding = strtoupper($sourceOfFunding) === 'EXTERNAL';
            $budgetRows = $approvable->budgetItems->values();
            $budgetRowsTotal = $budgetRows->sum(fn ($row) => (float) ($row->total_cost ?? 0));

            $base['Activity Title'] = $approvable->activity_title ?? 'N/A';
            $base['Proposed Dates'] = trim(collect([
                optional($approvable->proposed_start_date)->format('M d, Y'),
                optional($approvable->proposed_end_date)->format('M d, Y'),
            ])->filter()->implode(' - ')) ?: 'N/A';
            $base['Venue'] = $approvable->venue ?? 'N/A';

            $step1Rows = [
                [
                    'key' => 'step1_proposal_option',
                    'label' => 'Proposal Option',
                    'value' => $approvable->activity_calendar_entry_id ? 'From submitted Activity Calendar' : 'Activity not in submitted calendar',
                    'reviewable' => false,
                ],
                [
                    'key' => 'step1_rso_name',
                    'label' => 'RSO Name',
                    'value' => $requestForm?->rso_name ?: ($approvable->organization?->organization_name ?? '—'),
                    'reviewable' => false,
                ],
                ['key' => 'step1_activity_title', 'label' => 'Title of Activity', 'value' => $requestForm?->activity_title ?: '—'],
                ['key' => 'step1_partner_entities', 'label' => 'Partner Entities', 'value' => $requestForm?->partner_entities ?: '—'],
                ['key' => 'step1_nature_of_activity', 'label' => 'Nature of Activity', 'value' => $this->requestFormOptionsLabel((array) ($requestForm?->nature_of_activity ?? []), $requestForm?->nature_other)],
                ['key' => 'step1_type_of_activity', 'label' => 'Type of Activity', 'value' => $this->requestFormOptionsLabel((array) ($requestForm?->activity_types ?? []), $requestForm?->activity_type_other)],
                ['key' => 'step1_target_sdg', 'label' => 'Target SDG', 'value' => $requestForm?->target_sdg ?: ($approvable->target_sdg ?: '—')],
                ['key' => 'step1_proposed_budget', 'label' => 'Step 1 Proposed Budget', 'value' => $requestForm?->proposed_budget !== null ? number_format((float) $requestForm->proposed_budget, 2) : '—'],
                ['key' => 'step1_budget_source', 'label' => 'Step 1 Budget Source', 'value' => $requestForm?->budget_source ?: '—'],
                ['key' => 'step1_activity_date', 'label' => 'Date of Activity', 'value' => $step1ActivityDate ?: '—'],
                ['key' => 'step1_venue', 'label' => 'Venue', 'value' => $requestForm?->venue ?: '—'],
            ];
            if ($approvable->activity_calendar_entry_id) {
                array_splice($step1Rows, 2, 0, [
                    ['key' => 'step1_linked_activity_calendar', 'label' => 'Linked Activity Calendar', 'value' => $linkedCalendar],
                    ['key' => 'step1_calendar_activity_row', 'label' => 'Calendar Activity Row', 'value' => $calendarRow],
                ]);
            }

            $step2Rows = [
                [
                    'key' => 'step2_organization',
                    'label' => 'RSO Name',
                    'value' => $approvable->organization?->organization_name ?: '—',
                    'reviewable' => false,
                ],
                [
                    'key' => 'step2_academic_year',
                    'label' => 'Academic Year',
                    'value' => $approvable->academicTerm?->academic_year ?: '—',
                    'reviewable' => false,
                ],
                ['key' => 'step2_department', 'label' => 'Department', 'value' => $department !== '' ? $department : '—'],
                ['key' => 'step2_program', 'label' => 'Program', 'value' => $approvable->program ?: '—'],
                ['key' => 'step2_activity_title', 'label' => 'Project / Activity Title', 'value' => $approvable->activity_title ?: '—'],
                ['key' => 'step2_proposed_dates', 'label' => 'Proposed Dates', 'value' => trim(collect([
                    optional($approvable->proposed_start_date)->format('M d, Y'),
                    optional($approvable->proposed_end_date)->format('M d, Y'),
                ])->filter()->implode(' - ')) ?: '—'],
                ['key' => 'step2_proposed_time', 'label' => 'Proposed Time', 'value' => $proposalTime],
                ['key' => 'step2_venue', 'label' => 'Venue', 'value' => $approvable->venue ?: '—'],
                ['key' => 'step2_overall_goal', 'label' => 'Overall Goal', 'value' => $approvable->overall_goal ?: '—'],
                ['key' => 'step2_specific_objectives', 'label' => 'Specific Objectives', 'value' => $approvable->specific_objectives ?: '—'],
                ['key' => 'step2_criteria_mechanics', 'label' => 'Criteria / Mechanics', 'value' => $approvable->criteria_mechanics ?: '—'],
                ['key' => 'step2_program_flow', 'label' => 'Program Flow', 'value' => $approvable->program_flow ?: '—'],
                ['key' => 'step2_budget_total', 'label' => 'Proposed Budget (Total)', 'value' => $approvable->estimated_budget !== null ? number_format((float) $approvable->estimated_budget, 2) : '—'],
                ['key' => 'step2_source_of_funding', 'label' => 'Source of Funding', 'value' => $sourceOfFunding !== '' ? $sourceOfFunding : '—'],
                [
                    'key' => 'step2_budget_table',
                    'label' => 'Detailed Budget Table',
                    'value' => $budgetRows->count() > 0 ? ('Rows: '.$budgetRows->count().' · Total: '.number_format((float) $budgetRowsTotal, 2)) : 'No rows submitted.',
                    'table' => $budgetRows->map(function ($row): array {
                        $material = trim((string) ($row->item_description ?? $row->particulars ?? ''));

                        return [
                            'material' => $material !== '' ? $material : '—',
                            'quantity' => $row->quantity !== null ? (string) $row->quantity : '—',
                            'unit_price' => $row->unit_cost !== null ? number_format((float) $row->unit_cost, 2) : '—',
                            'price' => $row->total_cost !== null ? number_format((float) $row->total_cost, 2) : '—',
                        ];
                    })->all(),
                ],
            ];

            $updateRecorder = app(ReviewableUpdateRecorder::class);
            $pendingFieldUpdates = $updateRecorder->pendingForReviewable($approvable);
            $pendingFieldUpdates->loadMissing('resubmittedBy:id,first_name,last_name');
            $pendingDiffBySectionKey = [];
            foreach ($pendingFieldUpdates as $upd) {
                $pendingDiffBySectionKey[(string) $upd->section_key][(string) $upd->field_key] = [
                    'is_updated' => true,
                    'old_value' => $upd->old_value,
                    'new_value' => $upd->new_value,
                    'old_file_meta' => is_array($upd->old_file_meta) ? $upd->old_file_meta : null,
                    'new_file_meta' => is_array($upd->new_file_meta) ? $upd->new_file_meta : null,
                    'resubmitted_at' => $upd->resubmitted_at,
                    'resubmitted_by_name' => $upd->resubmittedBy?->full_name ?? '—',
                ];
            }

            $attachPendingOfficerDiff = function (array $row) use ($pendingDiffBySectionKey): array {
                $key = (string) ($row['key'] ?? '');
                $section = ActivityProposalAdminFieldReviewSync::adminSectionForFieldKey($key);
                if ($section === null) {
                    return $row;
                }
                $cell = $pendingDiffBySectionKey[$section][$key] ?? null;
                if (! is_array($cell)) {
                    return $row;
                }
                $row['revision_diff'] = $cell;

                return $row;
            };

            $mapReview = function (array $row) use ($existingFieldReviews, $attachPendingOfficerDiff): array {
                $review = $existingFieldReviews->get($row['key']);
                $row['review'] = [
                    'status' => (string) ($review?->status ?? ''),
                    'comment' => (string) ($review?->comment ?? ''),
                ];

                return $attachPendingOfficerDiff($row);
            };
            $step1Rows = array_map($mapReview, $step1Rows);
            $step2Rows = array_map($mapReview, $step2Rows);

            $submittedFileRows = $this->buildApproverProposalSubmittedFileRows(
                $approvable,
                $requestForm,
                $step,
                $mapReview,
                $isExternalFunding
            );

            $detailSections = [
                ['title' => 'Step 1: Activity Request Form', 'rows' => $step1Rows],
                ['title' => 'Step 2: Proposal Submission', 'rows' => $step2Rows],
                [
                    'title' => 'Submitted files',
                    'subtitle' => 'Open or download documents uploaded for this proposal.',
                    'rows' => $submittedFileRows,
                ],
            ];

            $reviewableKeys = collect($detailSections)
                ->flatMap(fn (array $section): array => (array) ($section['rows'] ?? []))
                ->filter(fn (array $row): bool => (bool) ($row['reviewable'] ?? true))
                ->map(fn (array $row): string => (string) ($row['key'] ?? ''))
                ->filter(fn (string $key): bool => $key !== '')
                ->values()
                ->all();
            $stageStatus = $this->proposalStageStatusFromFieldReviews($existingFieldReviews, $reviewableKeys);

            return [
                'page_title' => $this->resolveDocumentType($approvable)['label'].' Review',
                'details' => $base,
                'detail_sections' => $detailSections,
                'proposal_file_links' => [],
                'is_proposal_review' => true,
                'scoped_status' => $stageStatus,
                'calendar_entries' => collect(),
            ];
        } elseif ($approvable instanceof ActivityReport) {
            $base['Event Title'] = $approvable->event_title ?? 'N/A';
            $base['Event Starts'] = optional($approvable->event_starts_at)->format('M d, Y g:i A') ?? 'N/A';
            $base['Prepared By'] = $approvable->prepared_by ?? 'N/A';
        }

        return [
            'page_title' => $this->resolveDocumentType($approvable)['label'].' Review',
            'details' => $base,
            'detail_sections' => [],
            'proposal_file_links' => [],
            'is_proposal_review' => false,
            'scoped_status' => strtoupper((string) ($approvable->status ?? 'pending')),
            'calendar_entries' => $approvable instanceof ActivityCalendar ? $approvable->entries : collect(),
        ];
    }

    private function proposalStageStatusFromFieldReviews(Collection $existingFieldReviews, array $reviewableKeys): string
    {
        if (count($reviewableKeys) === 0) {
            return 'PENDING';
        }

        $statuses = collect($reviewableKeys)
            ->map(fn (string $key): ?string => $existingFieldReviews->get($key)?->status)
            ->filter()
            ->values();

        if ($statuses->count() < count($reviewableKeys)) {
            return 'PENDING';
        }
        if ($statuses->contains('revision')) {
            return 'REVISION_REQUIRED';
        }

        return 'APPROVED';
    }

    private function relatedRequestFormForProposal(ActivityProposal $proposal): ?ActivityRequestForm
    {
        $base = ActivityRequestForm::query()
            ->where('organization_id', $proposal->organization_id)
            ->where('submitted_by', $proposal->submitted_by)
            ->whereNotNull('promoted_at');

        if ($proposal->activity_calendar_entry_id) {
            $hit = (clone $base)
                ->where('activity_calendar_entry_id', $proposal->activity_calendar_entry_id)
                ->latest('promoted_at')
                ->latest('id')
                ->first();
            if ($hit) {
                return $hit;
            }
        }

        return (clone $base)
            ->where('activity_title', (string) ($proposal->activity_title ?? ''))
            ->latest('promoted_at')
            ->latest('id')
            ->first();
    }

    /**
     * @param  callable(array): array  $mapReview
     * @return list<array<string, mixed>>
     */
    private function buildApproverProposalSubmittedFileRows(
        ActivityProposal $proposal,
        ?ActivityRequestForm $requestForm,
        ApprovalWorkflowStep $step,
        callable $mapReview,
        bool $isExternalFunding
    ): array {
        $rows = [];
        $pushFile = function (
            string $reviewFieldKey,
            string $label,
            string $routeKey,
            bool $includeWhenNoFile = false
        ) use (&$rows, $proposal, $requestForm, $step, $mapReview): void {
            [$attachment, $path] = $this->proposalReviewAttachmentAndNormalizedPath($proposal, $requestForm, $routeKey);
            if ($path === null || $path === '') {
                if (! $includeWhenNoFile) {
                    return;
                }
                $rows[] = $mapReview([
                    'key' => $reviewFieldKey,
                    'label' => $label,
                    'value' => 'No file uploaded.',
                    'file_row' => true,
                    'file_name' => '',
                    'view_url' => null,
                    'download_url' => null,
                    'reviewable' => true,
                ]);

                return;
            }
            $displayName = trim((string) ($attachment?->original_name ?? ''));
            if ($displayName === '') {
                $displayName = basename($path);
            }
            $rows[] = $mapReview([
                'key' => $reviewFieldKey,
                'label' => $label,
                'value' => 'Current file: '.$displayName,
                'file_row' => true,
                'file_name' => $displayName,
                'view_url' => route('approver.assignments.proposals.file', ['step' => $step->id, 'key' => $routeKey]),
                'download_url' => route('approver.assignments.proposals.file.download', ['step' => $step->id, 'key' => $routeKey]),
                'reviewable' => true,
            ]);
        };

        $pushFile('step1_request_letter', 'Request letter', 'request_letter');
        $pushFile('step1_speaker_resume', 'Resume of speaker', 'speaker_resume');
        $pushFile('step1_post_survey_form', 'Sample post-survey form', 'post_survey_form');
        $pushFile('step2_organization_logo', 'Organization logo', 'organization_logo');

        if ($isExternalFunding) {
            $pushFile('step2_external_funding_support', 'External funding support', 'external_funding', true);
        }

        $pushFile('step2_resume_resource_persons', 'Resume of resource person/s', 'resource_resume');

        return $rows;
    }

    private function proposalReviewAttachmentByKey(ActivityProposal $proposal, ?ActivityRequestForm $requestForm, string $key): ?Attachment
    {
        $proposalType = match ($key) {
            'organization_logo' => Attachment::TYPE_PROPOSAL_LOGO,
            'resource_resume' => Attachment::TYPE_PROPOSAL_RESOURCE_RESUME,
            'external_funding' => Attachment::TYPE_PROPOSAL_EXTERNAL_FUNDING,
            default => null,
        };
        if ($proposalType !== null) {
            return $proposal->attachments()
                ->where('file_type', $proposalType)
                ->latest('id')
                ->first();
        }

        if ($requestForm) {
            $requestType = match ($key) {
                'request_letter' => Attachment::TYPE_REQUEST_LETTER,
                'speaker_resume' => Attachment::TYPE_REQUEST_SPEAKER_RESUME,
                'post_survey_form' => Attachment::TYPE_REQUEST_POST_SURVEY,
                default => null,
            };
            if ($requestType !== null) {
                return $requestForm->attachments()
                    ->where('file_type', $requestType)
                    ->latest('id')
                    ->first();
            }
        }

        return null;
    }

    /**
     * @return array{0: ?Attachment, 1: ?string}
     */
    private function proposalReviewAttachmentAndNormalizedPath(ActivityProposal $proposal, ?ActivityRequestForm $requestForm, string $key): array
    {
        $attachment = $this->proposalReviewAttachmentByKey($proposal, $requestForm, $key);
        if ($attachment && is_string($attachment->stored_path) && trim($attachment->stored_path) !== '') {
            $normalized = $this->normalizeStoredPublicPathApprover(trim($attachment->stored_path));

            return [$attachment, $normalized];
        }

        $legacy = $this->proposalReviewFilePathByKey($proposal, $requestForm, $key);
        $normalized = $this->normalizeStoredPublicPathApprover($legacy ?? '');

        return [null, ($normalized !== null && $normalized !== '') ? $normalized : null];
    }

    private function normalizeStoredPublicPathApprover(?string $rawPath): ?string
    {
        if (! is_string($rawPath)) {
            return null;
        }

        $path = trim($rawPath);
        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            if (is_string($parsedPath) && $parsedPath !== '') {
                $path = $parsedPath;
            }
        }

        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        } elseif (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }
        $path = ltrim($path, '/');

        return $path !== '' ? $path : null;
    }

    /**
     * @param  array<string, mixed>  $logContext
     */
    private function redirectApproverProposalAttachmentDownload(
        string $relativePath,
        string $downloadFilename,
        ?string $mimeType,
        array $logContext = []
    ): Response {
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            abort(404);
        }

        $disk = Storage::disk('supabase');
        $existsInSupabase = false;

        try {
            $existsInSupabase = $disk->exists($relativePath);
        } catch (\Throwable $e) {
            Log::warning('Approver attachment exists() check failed on Supabase.', array_merge($logContext, [
                'stored_path' => $relativePath,
                'error' => $e->getMessage(),
            ]));
        }

        $safeFilename = (string) preg_replace('/[\x00-\x1F"\\\\]/u', '', $downloadFilename);
        if ($safeFilename === '') {
            $safeFilename = basename($relativePath);
        }
        $resolvedMime = ($mimeType !== null && trim($mimeType) !== '') ? trim($mimeType) : 'application/octet-stream';

        if ($existsInSupabase) {
            try {
                $temporaryUrl = $disk->temporaryUrl($relativePath, now()->addMinutes(15), [
                    'ResponseContentDisposition' => 'attachment; filename="'.$safeFilename.'"',
                    'ResponseContentType' => $resolvedMime,
                ]);

                Log::info('Approver attachment download via Supabase signed URL.', array_merge($logContext, [
                    'stored_path' => $relativePath,
                ]));

                return redirect()->away($temporaryUrl);
            } catch (\Throwable $e) {
                Log::warning('Approver: failed to generate Supabase temporaryUrl for download.', array_merge($logContext, [
                    'stored_path' => $relativePath,
                    'error' => $e->getMessage(),
                ]));
            }
        }

        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($relativePath)) {
            return $publicDisk->download($relativePath, $safeFilename);
        }

        abort(404, 'The file could not be found in Supabase Storage.');
    }

    private function proposalReviewFilePathByKey(ActivityProposal $proposal, ?ActivityRequestForm $requestForm, string $key): ?string
    {
        $proposalType = match ($key) {
            'organization_logo' => Attachment::TYPE_PROPOSAL_LOGO,
            'resource_resume' => Attachment::TYPE_PROPOSAL_RESOURCE_RESUME,
            'external_funding' => Attachment::TYPE_PROPOSAL_EXTERNAL_FUNDING,
            default => null,
        };
        if ($proposalType !== null) {
            $attachment = $proposal->attachments()
                ->where('file_type', $proposalType)
                ->latest('id')
                ->first();
            if ($attachment && is_string($attachment->stored_path) && trim($attachment->stored_path) !== '') {
                return trim($attachment->stored_path);
            }
        }

        if ($requestForm) {
            $requestType = match ($key) {
                'request_letter' => Attachment::TYPE_REQUEST_LETTER,
                'speaker_resume' => Attachment::TYPE_REQUEST_SPEAKER_RESUME,
                'post_survey_form' => Attachment::TYPE_REQUEST_POST_SURVEY,
                default => null,
            };
            if ($requestType !== null) {
                $attachment = $requestForm->attachments()
                    ->where('file_type', $requestType)
                    ->latest('id')
                    ->first();
                if ($attachment && is_string($attachment->stored_path) && trim($attachment->stored_path) !== '') {
                    return trim($attachment->stored_path);
                }
            }
        }

        return null;
    }

    private function proposalTimeRangeLabel(ActivityProposal $proposal): string
    {
        $start = $this->formatTimeValue($proposal->proposed_start_time);
        $end = $this->formatTimeValue($proposal->proposed_end_time);
        if ($start && $end) {
            return $start.' - '.$end;
        }

        return $start ?: '—';
    }

    private function formatTimeValue(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $trimmed = trim($value);
        $normalized = strlen($trimmed) >= 5 ? substr($trimmed, 0, 5) : $trimmed;
        $dt = \DateTime::createFromFormat('H:i', $normalized);

        return $dt ? $dt->format('g:i A') : $trimmed;
    }

    /**
     * @param  array<int, string>  $values
     */
    private function requestFormOptionsLabel(array $values, ?string $otherText = null): string
    {
        $values = array_values(array_filter($values, fn ($v) => is_string($v) && trim($v) !== ''));
        if ($values === []) {
            return '—';
        }

        $labels = array_map(
            fn (string $value): string => ucfirst(str_replace('_', ' ', $value)),
            $values
        );
        if (in_array('Others', $labels, true) && is_string($otherText) && trim($otherText) !== '') {
            $labels = array_map(
                fn (string $label): string => $label === 'Others' ? 'Others: '.trim($otherText) : $label,
                $labels
            );
        }

        return implode(', ', $labels);
    }

    private function resolveDocumentType(Model $approvable): array
    {
        if ($approvable instanceof OrganizationSubmission) {
            if ($approvable->type === OrganizationSubmission::TYPE_RENEWAL) {
                return ['key' => 'organization_renewal', 'label' => 'Organization Renewal'];
            }

            return ['key' => 'organization_registration', 'label' => 'Organization Registration'];
        }
        if ($approvable instanceof ActivityCalendar) {
            return ['key' => 'activity_calendar', 'label' => 'Activity Calendar'];
        }
        if ($approvable instanceof ActivityProposal) {
            return ['key' => 'activity_proposal', 'label' => 'Activity Proposal'];
        }
        if ($approvable instanceof ActivityReport) {
            return ['key' => 'activity_report', 'label' => 'Activity Report'];
        }

        return ['key' => 'unknown', 'label' => class_basename($approvable)];
    }

    private function resolveSubmissionDate(Model $approvable): ?Carbon
    {
        if ($approvable instanceof OrganizationSubmission) {
            return $approvable->submission_date;
        }
        if ($approvable instanceof ActivityCalendar) {
            return $approvable->submission_date;
        }
        if ($approvable instanceof ActivityProposal) {
            return $approvable->submission_date;
        }
        if ($approvable instanceof ActivityReport) {
            return $approvable->report_submission_date;
        }

        return null;
    }

    private function resolveEventDate(Model $approvable): ?Carbon
    {
        if ($approvable instanceof ActivityProposal) {
            return $approvable->proposed_start_date;
        }
        if ($approvable instanceof ActivityReport) {
            return $approvable->event_starts_at;
        }

        return null;
    }

    private function resolveResubmittedKeys(Collection $approvableKeys): array
    {
        if ($approvableKeys->isEmpty()) {
            return [];
        }

        $pairs = $approvableKeys
            ->map(function (string $value): array {
                [$type, $id] = explode('#', $value, 2);

                return ['type' => $type, 'id' => (int) $id];
            })
            ->values();

        $query = ApprovalLog::query()
            ->select(['approvable_type', 'approvable_id'])
            ->where('action', 'revision_requested');

        $query->where(function ($outer) use ($pairs): void {
            foreach ($pairs as $pair) {
                $outer->orWhere(function ($inner) use ($pair): void {
                    $inner->where('approvable_type', $pair['type'])
                        ->where('approvable_id', $pair['id']);
                });
            }
        });

        return $query->get()
            ->map(fn (ApprovalLog $log): string => $log->approvable_type.'#'.$log->approvable_id)
            ->unique()
            ->values()
            ->all();
    }

    private function notifyOrganizationSubmissionResult(Model $approvable, string $toStatus): void
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

        if ($approvable instanceof ActivityProposal) {
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
            $link = route('organizations.activity-submission.proposals.show', $approvable);
        } elseif ($approvable instanceof ActivityCalendar) {
            $title = match ($status) {
                'APPROVED' => 'Activity Calendar Approved',
                'REVISION' => 'Activity Calendar Returned for Revision',
                default => 'Activity Calendar Rejected',
            };
            $message = match ($status) {
                'APPROVED' => 'Your activity calendar has been approved.',
                'REVISION' => 'Your activity calendar needs updates and was returned for revision.',
                default => 'Your activity calendar was rejected.',
            };
            $link = route('organizations.submitted-documents.calendars.show', $approvable);
        } elseif ($approvable instanceof ActivityReport) {
            $title = match ($status) {
                'APPROVED' => 'After-Activity Report Approved',
                'REVISION' => 'After-Activity Report Returned for Revision',
                default => 'After-Activity Report Rejected',
            };
            $message = match ($status) {
                'APPROVED' => 'Your after-activity report has been approved.',
                'REVISION' => 'Your after-activity report needs updates and was returned for revision.',
                default => 'Your after-activity report was rejected.',
            };
            $link = route('organizations.submitted-documents.reports.show', $approvable);
        } else {
            return;
        }

        $service = app(OrganizationNotificationService::class);
        if ($approvable->submittedBy) {
            $service->createForUser($approvable->submittedBy, $title, $message, $type, $link, $approvable);
        }
        if ($approvable->organization) {
            $service->createForOrganization($approvable->organization, $title, $message, $type, $link, $approvable);
        }
    }

    private function statusClass(string $status): string
    {
        return match (strtoupper($status)) {
            'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
            'UNDER_REVIEW', 'REVIEWED' => 'bg-blue-100 text-blue-700 border border-blue-200',
            'APPROVED' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
            'REJECTED' => 'bg-rose-100 text-rose-700 border border-rose-200',
            'REVISION', 'REVISION_REQUIRED' => 'bg-orange-100 text-orange-700 border border-orange-200',
            default => 'bg-slate-100 text-slate-700 border border-slate-200',
        };
    }
}
