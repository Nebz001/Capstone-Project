<?php

namespace App\Http\Controllers;

use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\ActivityReport;
use App\Models\ApprovalLog;
use App\Models\ApprovalWorkflowStep;
use App\Models\Notification;
use App\Models\OrganizationSubmission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApproverDashboardController extends Controller
{
    private const OVERDUE_DAYS = 5;

    public function dashboard(Request $request): View
    {
        $user = $this->requireApprover($request);

        $documentType = (string) $request->string('document_type', '');
        $status = strtolower((string) $request->string('status', ''));
        $organizationSearch = trim((string) $request->string('organization', ''));
        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');

        $pendingSteps = $this->basePendingQueueQuery($user)
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
            'status' => strtoupper((string) ($approvable->status ?? 'pending')),
            'details' => $details['details'],
            'calendarEntries' => $details['calendar_entries'],
            'workflowSteps' => $approvable->workflowSteps,
            'workflowLogs' => $approvable->approvalLogs()->with('actor:id,first_name,last_name')->latest('created_at')->limit(15)->get(),
            'workflowCurrentStep' => $step,
            'workflowActionRoute' => route('approver.assignments.decide', $step),
        ]);
    }

    public function decide(Request $request, ApprovalWorkflowStep $step): RedirectResponse
    {
        $user = $this->requireApprover($request);
        $currentStep = $this->resolveAuthorizedCurrentStep($step, $user);
        $approvable = $currentStep->approvable;

        if (! $approvable) {
            abort(404, 'Assigned document no longer exists.');
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'revision'])],
            'comments' => ['nullable', 'string', 'max:5000'],
        ]);

        $action = (string) $validated['action'];
        $comments = trim((string) ($validated['comments'] ?? ''));
        $fromStatus = strtoupper((string) ($approvable->status ?? 'PENDING'));

        DB::transaction(function () use ($approvable, $currentStep, $action, $comments, $fromStatus, $user): void {
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
                    $nextStep->update(['is_current_step' => true]);
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
        });

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

    private function basePendingQueueQuery(User $user)
    {
        return ApprovalWorkflowStep::query()
            ->where('role_id', (int) $user->role_id)
            ->where('is_current_step', true)
            ->whereIn('status', ['pending', 'under_review', 'revision_required'])
            ->where(function ($query) use ($user): void {
                $query->whereNull('assigned_to')
                    ->orWhere('assigned_to', $user->id);
            });
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
            $base['Activity Title'] = $approvable->activity_title ?? 'N/A';
            $base['Proposed Dates'] = trim(collect([
                optional($approvable->proposed_start_date)->format('M d, Y'),
                optional($approvable->proposed_end_date)->format('M d, Y'),
            ])->filter()->implode(' - ')) ?: 'N/A';
            $base['Venue'] = $approvable->venue ?? 'N/A';
        } elseif ($approvable instanceof ActivityReport) {
            $base['Event Title'] = $approvable->event_title ?? 'N/A';
            $base['Event Starts'] = optional($approvable->event_starts_at)->format('M d, Y g:i A') ?? 'N/A';
            $base['Prepared By'] = $approvable->prepared_by ?? 'N/A';
        }

        return [
            'page_title' => $this->resolveDocumentType($approvable)['label'].' Review',
            'details' => $base,
            'calendar_entries' => $approvable instanceof ActivityCalendar ? $approvable->entries : collect(),
        ];
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

