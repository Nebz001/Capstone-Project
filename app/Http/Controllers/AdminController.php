<?php

namespace App\Http\Controllers;

use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\ActivityReport;
use App\Models\ActivityRequestForm;
use App\Models\ApprovalLog;
use App\Models\ApprovalWorkflowStep;
use App\Models\Attachment;
use App\Models\ModuleRevisionFieldUpdate;
use App\Models\Organization;
use App\Models\OrganizationAdviser;
use App\Models\OrganizationProfileRevision;
use App\Models\OrganizationRevisionFieldUpdate;
use App\Models\OrganizationSubmission;
use App\Models\Role;
use App\Models\SubmissionRequirement;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\OrganizationNotificationService;
use App\Services\ReviewWorkflow\ReviewableUpdateRecorder;
use App\Support\ManilaDateTime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    /** @see OrganizationController::REGISTRATION_REQUIREMENT_KEYS */
    private const REGISTRATION_REQUIREMENT_FILE_KEYS = [
        'letter_of_intent',
        'application_form',
        'by_laws',
        'updated_list_of_officers_founders',
        'dean_endorsement_faculty_adviser',
        'proposed_projects_budget',
        'others',
    ];

    private const RENEWAL_REQUIREMENT_FILE_KEYS = [
        'letter_of_intent',
        'application_form',
        'by_laws_updated_if_applicable',
        'updated_list_of_officers_founders_ay',
        'dean_endorsement_faculty_adviser',
        'proposed_projects_budget',
        'past_projects',
        'financial_statement_previous_ay',
        'evaluation_summary_past_projects',
        'others',
    ];

    /** Admin per-section review keys (registration show page). */
    private const REGISTRATION_REVIEW_SECTION_KEYS = ['application', 'contact', 'adviser', 'organizational', 'requirements'];

    /** Registration adviser nomination row `organization_advisers.status` is synced from these field reviews only. */
    private const REGISTRATION_ADVISER_INFORMATION_REVIEW_KEYS = ['full_name', 'school_id', 'email'];

    /** @return array<string, string> section_key => revision_comment column */
    private function registrationReviewSectionRevisionColumns(): array
    {
        return [
            'application' => 'revision_comment_application',
            'contact' => 'revision_comment_contact',
            'organizational' => 'revision_comment_organizational',
            'requirements' => 'revision_comment_requirements',
        ];
    }

    /**
     * @return array<string, 'pending'|'validated'|'revision'>
     */
    private function initialRegistrationSectionReviewState(OrganizationSubmission $submission): array
    {
        $stored = is_array($submission->registration_section_reviews) ? $submission->registration_section_reviews : null;
        if (is_array($stored)) {
            $states = [];
            foreach (self::REGISTRATION_REVIEW_SECTION_KEYS as $sectionKey) {
                $raw = (string) data_get($stored, $sectionKey.'.status', 'pending');
                $states[$sectionKey] = match ($raw) {
                    'verified' => 'validated',
                    'needs_revision' => 'revision',
                    default => 'pending',
                };
            }

            return $states;
        }

        if ($submission->status === OrganizationSubmission::STATUS_APPROVED) {
            return array_fill_keys(self::REGISTRATION_REVIEW_SECTION_KEYS, 'validated');
        }

        if ($submission->status === OrganizationSubmission::STATUS_REVISION) {
            return array_fill_keys(self::REGISTRATION_REVIEW_SECTION_KEYS, 'revision');
        }

        return array_fill_keys(self::REGISTRATION_REVIEW_SECTION_KEYS, 'pending');
    }

    public function dashboard(Request $request): View
    {
        $this->authorizeAdmin($request);

        $counts = [
            'registrations' => OrganizationSubmission::query()->registrations()->where('status', OrganizationSubmission::STATUS_PENDING)->count(),
            'renewals' => OrganizationSubmission::query()->renewals()->where('status', OrganizationSubmission::STATUS_PENDING)->count(),
            'calendars' => ActivityCalendar::query()->where('status', 'pending')->count(),
            'proposals' => ActivityProposal::query()->where('status', 'pending')->count(),
            'reports' => ActivityReport::query()->where('status', 'pending')->count(),
        ];

        $calendarEvents = $this->buildCentralizedCalendarEvents();

        $registeredOrganizations = Organization::query()
            ->orderBy('organization_name')
            ->get(['id', 'organization_name', 'status', 'college_department', 'organization_type']);

        return view('admin.dashboard', compact('counts', 'calendarEvents', 'registeredOrganizations'));
    }

    public function updateActiveTerm(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        abort_unless($request->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'active_semester' => ['required', 'string', Rule::in(['term_1', 'term_2', 'term_3'])],
        ]);

        SystemSetting::put('active_semester', $validated['active_semester']);

        return redirect()
            ->back()
            ->with('success', 'The active academic term has been updated for the entire system.');
    }

    public function updateAcademicYear(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        abort_unless($request->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'active_academic_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
        ]);

        [$start, $end] = array_map('intval', explode('-', $validated['active_academic_year'], 2));
        if ($end !== ($start + 1)) {
            return redirect()
                ->back()
                ->withErrors(['active_academic_year' => 'Academic year must follow YYYY-YYYY and advance by one year (e.g., 2025-2026).']);
        }

        SystemSetting::put('active_academic_year', $validated['active_academic_year']);

        return redirect()
            ->back()
            ->with('success', 'The active academic year has been updated for the entire system.');
    }

    public function registrations(Request $request): View
    {
        $this->authorizeAdmin($request);

        $records = OrganizationSubmission::query()
            ->registrations()
            ->with(['organization', 'submittedBy'])
            ->latest('submission_date')
            ->latest('id')
            ->paginate(10);
        $submissionIds = $records->getCollection()->pluck('id')->all();
        $updateAggregates = OrganizationRevisionFieldUpdate::query()
            ->selectRaw('organization_submission_id, SUM(CASE WHEN acknowledged_at IS NULL THEN 1 ELSE 0 END) as pending_updates_count, MAX(resubmitted_at) as last_resubmitted_at')
            ->whereIn('organization_submission_id', $submissionIds)
            ->groupBy('organization_submission_id')
            ->get()
            ->keyBy('organization_submission_id');

        return view('admin.review-index', [
            'pageTitle' => 'Registration Review',
            'pageSubtitle' => 'Monitor submitted RSO registration applications.',
            'routeBase' => 'admin.registrations.show',
            'rows' => $records->through(function (OrganizationSubmission $record) use ($updateAggregates): array {
                $legacyStatus = $record->legacyStatus();
                $update = $updateAggregates->get($record->id);
                $pendingUpdatesCount = $update ? (int) ($update->pending_updates_count ?? 0) : 0;
                $isUpdated = $pendingUpdatesCount > 0 && in_array($legacyStatus, ['UNDER_REVIEW', 'PENDING', 'REVIEWED'], true);
                $status = $legacyStatus === 'REVISION'
                    ? 'REVISION'
                    : ($isUpdated ? 'UPDATED' : $legacyStatus);
                $statusLabel = $status === 'UPDATED' ? 'UPDATED' : str_replace('_', ' ', $status);
                $lastResubmittedAt = $update && $update->last_resubmitted_at
                    ? Carbon::parse((string) $update->last_resubmitted_at)
                    : null;
                $latestAdminReviewedAt = $this->latestRegistrationReviewTimestamp(
                    is_array($record->registration_section_reviews) ? $record->registration_section_reviews : []
                );
                $lastUpdatedAt = $lastResubmittedAt
                    ?: $latestAdminReviewedAt
                    ?: $record->updated_at;

                return [
                    'organization' => $record->organization?->organization_name ?? 'N/A',
                    'submitted_by' => $record->submittedBy?->full_name ?? 'N/A',
                    'submission_date' => ManilaDateTime::formatSubmissionDate($record->submission_date),
                    'last_updated_date' => ManilaDateTime::formatLastUpdatedDateLine($lastUpdatedAt),
                    'last_updated_time' => ManilaDateTime::formatLastUpdatedTimeLine($lastUpdatedAt),
                    'last_updated' => $lastUpdatedAt
                        ? ManilaDateTime::inManila($lastUpdatedAt)->format('M d, Y, h:i A').' PHT'
                        : '—',
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'is_updated' => $isUpdated,
                    'updates_count' => $pendingUpdatesCount,
                    'action_label' => $isUpdated ? 'View Updates' : 'View',
                    'id' => $record->id,
                ];
            }),
        ]);
    }

    /**
     * @param  array<string, mixed>  $sectionReviews
     */
    private function latestRegistrationReviewTimestamp(array $sectionReviews): ?Carbon
    {
        $latest = null;
        foreach ($sectionReviews as $section) {
            if (! is_array($section)) {
                continue;
            }
            $reviewedAt = trim((string) ($section['reviewed_at'] ?? ''));
            if ($reviewedAt === '') {
                continue;
            }
            try {
                $ts = Carbon::parse($reviewedAt);
            } catch (\Throwable) {
                continue;
            }
            if ($latest === null || $ts->gt($latest)) {
                $latest = $ts;
            }
        }

        return $latest;
    }

    public function renewals(Request $request): View
    {
        $this->authorizeAdmin($request);

        $records = OrganizationSubmission::query()
            ->renewals()
            ->with(['organization', 'submittedBy'])
            ->latest('submission_date')
            ->latest('id')
            ->paginate(10);
        $submissionIds = $records->getCollection()->pluck('id')->all();
        $updateAggregates = OrganizationRevisionFieldUpdate::query()
            ->selectRaw('organization_submission_id, SUM(CASE WHEN acknowledged_at IS NULL THEN 1 ELSE 0 END) as pending_updates_count, MAX(resubmitted_at) as last_resubmitted_at')
            ->whereIn('organization_submission_id', $submissionIds)
            ->groupBy('organization_submission_id')
            ->get()
            ->keyBy('organization_submission_id');

        return view('admin.review-index', [
            'pageTitle' => 'Renewal Review',
            'pageSubtitle' => 'Monitor submitted RSO renewal applications.',
            'routeBase' => 'admin.renewals.show',
            'rows' => $records->through(function (OrganizationSubmission $record) use ($updateAggregates): array {
                $legacyStatus = $record->legacyStatus();
                $update = $updateAggregates->get($record->id);
                $pendingUpdatesCount = $update ? (int) ($update->pending_updates_count ?? 0) : 0;
                $isUpdated = $legacyStatus === 'REVISION' && $pendingUpdatesCount > 0;
                $status = $isUpdated ? 'UPDATED' : $legacyStatus;
                $statusLabel = $isUpdated ? 'UPDATED' : str_replace('_', ' ', $legacyStatus);
                $lastResubmittedAt = $update && $update->last_resubmitted_at
                    ? Carbon::parse((string) $update->last_resubmitted_at)
                    : null;
                $latestAdminReviewedAt = $this->latestRegistrationReviewTimestamp(
                    is_array($record->renewal_section_reviews) ? $record->renewal_section_reviews : []
                );
                $lastUpdatedAt = $lastResubmittedAt
                    ?: $latestAdminReviewedAt
                    ?: $record->updated_at;

                return [
                    'organization' => $record->organization?->organization_name ?? 'N/A',
                    'submitted_by' => $record->submittedBy?->full_name ?? 'N/A',
                    'submission_date' => optional($record->submission_date)->format('M d, Y') ?? 'N/A',
                    'last_updated' => $lastUpdatedAt
                        ? $lastUpdatedAt->format('M d, Y, g:i A')
                        : '—',
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'is_updated' => $isUpdated,
                    'updates_count' => $pendingUpdatesCount,
                    'action_label' => $isUpdated ? 'View Updates' : 'View',
                    'id' => $record->id,
                ];
            }),
        ]);
    }

    public function calendars(Request $request): View
    {
        $this->authorizeAdmin($request);

        $records = ActivityCalendar::query()
            ->with('organization')
            ->latest('submission_date')
            ->latest('id')
            ->paginate(10);
        $calendarIds = $records->getCollection()->pluck('id')->all();
        $lastResubmittedByCalendar = collect();
        if ($calendarIds !== []) {
            $lastResubmittedByCalendar = ModuleRevisionFieldUpdate::query()
                ->where('reviewable_type', (new ActivityCalendar)->getMorphClass())
                ->whereIn('reviewable_id', $calendarIds)
                ->selectRaw('reviewable_id, MAX(resubmitted_at) as last_resubmitted_at')
                ->groupBy('reviewable_id')
                ->get()
                ->keyBy('reviewable_id');
        }

        return view('admin.review-index', [
            'pageTitle' => 'Activity Calendar Review',
            'pageSubtitle' => 'Monitor submitted activity calendars by organization.',
            'routeBase' => 'admin.calendars.show',
            'rows' => $records->through(function (ActivityCalendar $record) use ($lastResubmittedByCalendar): array {
                $agg = $lastResubmittedByCalendar->get($record->id);
                $lastResubmittedAt = $agg && $agg->last_resubmitted_at
                    ? Carbon::parse((string) $agg->last_resubmitted_at)
                    : null;
                $submittedAt = $record->submission_date
                    ? Carbon::parse($record->submission_date->format('Y-m-d'))->startOfDay()
                    : null;
                $lastUpdatedAt = $lastResubmittedAt
                    ?? $record->updated_at
                    ?? $submittedAt
                    ?? $record->created_at;

                return [
                    'organization' => $record->organization?->organization_name ?? 'N/A',
                    'submitted_by' => 'Organization Submission',
                    'submission_date' => optional($record->submission_date)->format('M d, Y') ?? 'N/A',
                    'last_updated_date' => ManilaDateTime::formatLastUpdatedDateLine($lastUpdatedAt),
                    'last_updated_time' => ManilaDateTime::formatLastUpdatedTimeLine($lastUpdatedAt),
                    'last_updated' => $lastUpdatedAt
                        ? ManilaDateTime::inManila($lastUpdatedAt)->format('M d, Y, h:i A').' PHT'
                        : '—',
                    'status' => strtoupper((string) ($record->status ?? 'pending')),
                    'id' => $record->id,
                ];
            }),
        ]);
    }

    public function proposals(Request $request): View
    {
        $this->authorizeAdmin($request);

        $records = ActivityProposal::query()
            ->with(['organization', 'user'])
            ->latest('submission_date')
            ->latest('id')
            ->paginate(10);

        return view('admin.review-index', [
            'pageTitle' => 'Activity Proposal Review',
            'pageSubtitle' => 'Monitor submitted activity proposals for approval flow.',
            'routeBase' => 'admin.proposals.show',
            'rows' => $records->through(function (ActivityProposal $record): array {
                return [
                    'organization' => $record->organization?->organization_name ?? 'N/A',
                    'submitted_by' => $record->submittedBy?->full_name ?? 'N/A',
                    'submission_date' => optional($record->submission_date)->format('M d, Y') ?? 'N/A',
                    'status' => strtoupper((string) ($record->status ?? 'pending')),
                    'id' => $record->id,
                ];
            }),
        ]);
    }

    public function reports(Request $request): View
    {
        $this->authorizeAdmin($request);

        $records = ActivityReport::query()
            ->with(['organization', 'submittedBy'])
            ->latest('report_submission_date')
            ->latest('id')
            ->paginate(10);

        return view('admin.review-index', [
            'pageTitle' => 'After Activity Report Review',
            'pageSubtitle' => 'Monitor submitted after-activity accomplishment reports.',
            'routeBase' => 'admin.reports.show',
            'rows' => $records->through(function (ActivityReport $record): array {
                return [
                    'organization' => $record->organization?->organization_name ?? 'N/A',
                    'submitted_by' => $record->submittedBy?->full_name ?? 'N/A',
                    'submission_date' => optional($record->report_submission_date)->format('M d, Y') ?? 'N/A',
                    'status' => strtoupper((string) ($record->status ?? 'pending')),
                    'id' => $record->id,
                ];
            }),
        ]);
    }

    public function userAccounts(Request $request): View
    {
        $this->authorizeAdmin($request);

        $accounts = User::query()
            ->withoutSdaoAdminAccounts()
            ->with([
                'role',
                'organizationOfficers' => fn ($query) => $query
                    ->latest('id')
                    ->with('organization'),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);

        return view('admin.user-accounts.index', compact('accounts'));
    }

    public function showUserAccount(Request $request, User $user): View
    {
        $this->authorizeAdmin($request);

        abort_if($user->isSdaoAdmin(), 404);

        $user->load([
            'role',
            'organizationOfficers' => fn ($query) => $query->latest('id')->with('organization'),
            'validatedBy',
        ]);

        $latestOfficerRecord = $user->organizationOfficers->first();
        $reviewableFields = $this->accountReviewableFields($user, $latestOfficerRecord);

        return view('admin.user-accounts.show', [
            'account' => $user,
            'latestOfficerRecord' => $latestOfficerRecord,
            'reviewableFields' => $reviewableFields,
        ]);
    }

    public function updateUserAccountOfficerValidation(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        abort_if($user->isSdaoAdmin(), 404);
        abort_if($user->effectiveRoleType() !== 'ORG_OFFICER', 404);

        $validated = $request->validate([
            'validation_status' => ['required', 'in:APPROVED,ACTIVE,REJECTED,REVISION_REQUIRED'],
            'validation_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $user->update([
            'officer_validation_status' => $validated['validation_status'],
            'officer_validation_notes' => $validated['validation_notes'] ?: null,
            'officer_validated_at' => now(),
            'officer_validated_by' => $request->user()?->id,
        ]);
        $this->notifyOfficerValidationResult($user, (string) $validated['validation_status']);

        return redirect()
            ->route('admin.accounts.show', $user)
            ->with('success', 'Officer validation has been updated.');
    }

    /**
     * @return array<string, array{label: string, value: string}>
     */
    private function accountReviewableFields(User $account, mixed $latestOfficerRecord): array
    {
        $fields = [
            'full_name' => ['label' => 'Full name', 'value' => $account->full_name],
            'school_id' => ['label' => 'School ID', 'value' => (string) $account->school_id],
            'email' => ['label' => 'Email', 'value' => (string) $account->email],
            'account_status' => ['label' => 'Account status', 'value' => (string) $account->account_status],
            'date_registered' => [
                'label' => 'Date registered',
                'value' => optional($account->created_at)->format('M d, Y h:i A') ?? 'N/A',
            ],
        ];

        if ($account->effectiveRoleType() === 'ORG_OFFICER') {
            $fields['linked_organization'] = [
                'label' => 'Linked organization',
                'value' => $latestOfficerRecord?->organization?->organization_name ?? 'Not linked',
            ];
            $fields['position_title'] = [
                'label' => 'Position / officer role',
                'value' => $latestOfficerRecord?->position_title ?? 'N/A',
            ];
            $fields['officer_validation_status'] = [
                'label' => 'Officer validation status',
                'value' => str_replace('_', ' ', (string) $account->officer_validation_status),
            ];
            $fields['officer_validation_notes'] = [
                'label' => 'Validation notes',
                'value' => $account->officer_validation_notes ?: 'No notes provided yet.',
            ];
            $lastReviewed = $account->validatedBy?->full_name ?? 'Not reviewed yet';
            if ($account->officer_validated_at) {
                $lastReviewed .= ' · '.$account->officer_validated_at->format('M d, Y h:i A');
            }
            $fields['last_reviewed_by'] = [
                'label' => 'Last reviewed by',
                'value' => $lastReviewed,
            ];
        }

        return $fields;
    }

    public function centralizedCalendar(Request $request): View
    {
        $this->authorizeAdmin($request);

        $calendarEvents = $this->buildCentralizedCalendarEvents();

        return view('admin.calendar', compact('calendarEvents'));
    }

    public function showRegistration(Request $request, OrganizationSubmission $submission): View
    {
        $this->authorizeAdmin($request);

        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);
        $submission->load([
            'organization',
            'submittedBy:id,first_name,last_name,email,school_id',
            'academicTerm',
            'requirements',
            'attachments',
        ]);
        $latestUpdates = OrganizationRevisionFieldUpdate::query()
            ->where('organization_submission_id', $submission->id)
            ->with(['resubmittedBy:id,first_name,last_name', 'acknowledgedBy:id,first_name,last_name'])
            ->orderByDesc('id')
            ->get()
            ->unique(fn (OrganizationRevisionFieldUpdate $row): string => $row->section_key.'.'.$row->field_key)
            ->values();
        $updatesByField = [];
        $storedFieldReviews = is_array($submission->registration_field_reviews) ? $submission->registration_field_reviews : [];
        foreach ($latestUpdates as $row) {
            if (! isset($updatesByField[$row->section_key])) {
                $updatesByField[$row->section_key] = [];
            }
            $oldFileMeta = is_array($row->old_file_meta) ? $row->old_file_meta : [];
            $newFileMeta = is_array($row->new_file_meta) ? $row->new_file_meta : [];
            $oldFile = $this->fileMetaViewPayload($oldFileMeta, $submission, $row, 'old');
            $newFile = $this->fileMetaViewPayload($newFileMeta, $submission, $row, 'new');
            $updatesByField[$row->section_key][$row->field_key] = [
                'id' => (int) $row->id,
                'section_key' => (string) $row->section_key,
                'field_key' => (string) $row->field_key,
                'old_value' => $row->old_value ?? ($oldFile['name'] ?: null),
                'new_value' => $row->new_value ?? ($newFile['name'] ?: null),
                'old_file_meta' => $oldFileMeta,
                'new_file_meta' => $newFileMeta,
                'old_file' => $oldFile,
                'new_file' => $newFile,
                'resubmitted_at' => optional($row->resubmitted_at)->toDateTimeString(),
                'resubmitted_by' => $row->resubmittedBy?->full_name ?? 'Unknown',
                'acknowledged_at' => optional($row->acknowledged_at)->toDateTimeString(),
                'acknowledged_by' => $row->acknowledgedBy?->full_name ?? null,
                'is_updated' => $row->acknowledged_at === null,
            ];
        }
        $effectiveFieldReviews = $this->stripNonReviewableOrganizationalRegistrationFieldReviews(
            $this->stripNonReviewableApplicationRegistrationFieldReviews(
                $this->resetUpdatedFieldReviewStates($storedFieldReviews, $updatesByField)
            )
        );

        return view('admin.registrations.show', [
            'registration' => $submission,
            'submission' => $submission,
            'adviserNomination' => $this->submissionAdviserNomination($submission),
            'initialSectionReviewState' => $this->initialRegistrationSectionReviewState($submission),
            'persistedFieldReviews' => $effectiveFieldReviews,
            'persistedSectionReviews' => is_array($submission->registration_section_reviews) ? $submission->registration_section_reviews : [],
            'fieldUpdateDiffs' => $updatesByField,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{name:string,view_url:?string,download_url:?string}
     */
    private function fileMetaViewPayload(array $meta, OrganizationSubmission $submission, OrganizationRevisionFieldUpdate $update, string $version): array
    {
        $storedPath = trim((string) ($meta['stored_path'] ?? ''));
        $name = trim((string) ($meta['original_name'] ?? ''));
        if ($name === '' && $storedPath !== '') {
            $name = basename($storedPath);
        }
        if ($storedPath === '') {
            return [
                'name' => $name,
                'view_url' => null,
                'download_url' => null,
            ];
        }

        return [
            'name' => $name,
            'view_url' => route('admin.registrations.field-updates.file', [
                'submission' => $submission,
                'fieldUpdate' => $update,
                'version' => $version,
            ]),
            'download_url' => route('admin.registrations.field-updates.file.download', [
                'submission' => $submission,
                'fieldUpdate' => $update,
                'version' => $version,
            ]),
        ];
    }

    private function submissionAdviserNomination(OrganizationSubmission $submission): ?OrganizationAdviser
    {
        return OrganizationAdviser::query()
            ->with(['user', 'reviewer'])
            ->where('organization_id', $submission->organization_id)
            ->where('submission_id', (int) $submission->id)
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{name:string,view_url:?string,download_url:?string}
     */
    public function showRegistrationFieldUpdateFile(Request $request, OrganizationSubmission $submission, OrganizationRevisionFieldUpdate $fieldUpdate, string $version): Response
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);
        abort_unless((int) $fieldUpdate->organization_submission_id === (int) $submission->id, 404);
        abort_unless((string) $fieldUpdate->section_key === 'requirements', 404);

        $meta = $version === 'old'
            ? (is_array($fieldUpdate->old_file_meta) ? $fieldUpdate->old_file_meta : [])
            : (is_array($fieldUpdate->new_file_meta) ? $fieldUpdate->new_file_meta : []);
        $storedPath = trim((string) ($meta['stored_path'] ?? ''));
        if ($storedPath === '' || str_contains($storedPath, '..') || str_starts_with($storedPath, '/')) {
            abort(404);
        }

        $disk = Storage::disk('supabase');
        $exists = $disk->exists($storedPath);

        Log::info('Resolving registration field-update file from Supabase Storage.', [
            'field_update_id' => $fieldUpdate->id,
            'submission_id' => $submission->id,
            'version' => $version,
            'stored_path' => $storedPath,
            'exists_in_supabase_storage' => $exists,
        ]);

        if (! $exists) {
            Log::warning('Registration field-update file missing from Supabase Storage', [
                'field_update_id' => $fieldUpdate->id,
                'stored_path' => $storedPath,
            ]);

            abort(404, 'The file could not be found in Supabase Storage.');
        }

        $publicUrl = rtrim((string) env('SUPABASE_STORAGE_PUBLIC_URL'), '/')
            .'/'.trim((string) env('SUPABASE_STORAGE_BUCKET'), '/')
            .'/'.ltrim($storedPath, '/');

        return redirect()->away($publicUrl);
    }

    /**
     * Force-download an old/new revision file (no preview, no modal).
     *
     * Mirrors `showRegistrationFieldUpdateFile` for authorization and path
     * resolution, but forces a download with the original filename rather
     * than redirecting to the public Supabase URL where the browser would
     * preview the file inline.
     */
    public function downloadRegistrationFieldUpdateFile(Request $request, OrganizationSubmission $submission, OrganizationRevisionFieldUpdate $fieldUpdate, string $version): Response
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);
        abort_unless((int) $fieldUpdate->organization_submission_id === (int) $submission->id, 404);
        abort_unless((string) $fieldUpdate->section_key === 'requirements', 404);

        $meta = $version === 'old'
            ? (is_array($fieldUpdate->old_file_meta) ? $fieldUpdate->old_file_meta : [])
            : (is_array($fieldUpdate->new_file_meta) ? $fieldUpdate->new_file_meta : []);
        $storedPath = trim((string) ($meta['stored_path'] ?? ''));
        if ($storedPath === '' || str_contains($storedPath, '..') || str_starts_with($storedPath, '/')) {
            abort(404);
        }

        $originalName = trim((string) ($meta['original_name'] ?? ''));
        if ($originalName === '') {
            $originalName = basename($storedPath);
        }
        $mimeType = trim((string) ($meta['mime_type'] ?? ''));

        return $this->forceDownloadSupabaseObject(
            $storedPath,
            $originalName,
            $mimeType !== '' ? $mimeType : null,
            [
                'field_update_id' => $fieldUpdate->id,
                'submission_id' => $submission->id,
                'version' => $version,
            ]
        );
    }

    public function acknowledgeRegistrationFieldUpdate(Request $request, OrganizationSubmission $submission, OrganizationRevisionFieldUpdate $fieldUpdate): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);
        abort_unless((int) $fieldUpdate->organization_submission_id === (int) $submission->id, 404);
        if ($fieldUpdate->acknowledged_at === null) {
            $fieldUpdate->update([
                'acknowledged_at' => now(),
                'acknowledged_by' => (int) $request->user()->id,
            ]);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $fieldUpdate->id,
            'acknowledged_at' => optional($fieldUpdate->fresh()->acknowledged_at)->toDateTimeString(),
        ]);
    }

    /**
     * Resolve and view a registration requirement file from Supabase Storage.
     *
     * Uploads land in the `supabase` disk under bucket-relative paths like
     * `{organization_id}/registration/<random>.pdf`. We look the row up via
     * the `attachments` table, verify the object exists in the bucket, then
     * redirect the browser to the public Supabase URL so it can render or
     * download the file directly.
     */
    public function showRegistrationRequirementFile(Request $request, OrganizationSubmission $submission, string $key): Response
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);

        if (! in_array($key, self::REGISTRATION_REQUIREMENT_FILE_KEYS, true)) {
            abort(404);
        }

        return $this->redirectToSupabaseRequirementFile($submission, $key);
    }

    /**
     * Force-download a registration requirement file (no preview, no modal).
     *
     * Reuses the same admin authorization, requirement-key whitelist, and
     * attachment lookup as `showRegistrationRequirementFile`, but emits the
     * file with `Content-Disposition: attachment` so the browser saves it
     * directly using the original filename from `attachments.original_name`.
     */
    public function downloadRegistrationRequirementFile(Request $request, OrganizationSubmission $submission, string $key): Response
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);

        if (! in_array($key, self::REGISTRATION_REQUIREMENT_FILE_KEYS, true)) {
            abort(404);
        }

        return $this->forceDownloadSupabaseRequirementFile($submission, $key);
    }

    public function updateRegistrationStatus(Request $request, OrganizationSubmission $submission): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);

        $validator = Validator::make($request->all(), [
            'field_review' => ['nullable', 'array'],
            'section_review' => ['nullable', 'array'],
            'section_submitted' => ['nullable', 'array'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $validated = $validator->validate();

        /** @var User $admin */
        $admin = $request->user();
        $fieldReviewInput = is_array($validated['field_review'] ?? null) ? $validated['field_review'] : [];
        if (isset($fieldReviewInput['adviser']['status'])) {
            unset($fieldReviewInput['adviser']['status']);
        }
        $sectionSubmittedInput = is_array($validated['section_submitted'] ?? null) ? $validated['section_submitted'] : [];
        $sectionSchema = $this->registrationReviewFieldSchema();

        $normalizedFieldReviews = [];
        $normalizedSectionReviews = [];
        foreach ($sectionSchema as $sectionKey => $fields) {
            $sectionInput = is_array($fieldReviewInput[$sectionKey] ?? null) ? $fieldReviewInput[$sectionKey] : [];
            $fieldStates = [];
            foreach ($fields as $fieldKey => $fieldLabel) {
                $row = is_array($sectionInput[$fieldKey] ?? null) ? $sectionInput[$fieldKey] : [];
                $status = (string) ($row['status'] ?? 'pending');
                if (! in_array($status, ['pending', 'passed', 'flagged'], true)) {
                    $status = 'pending';
                }
                $note = trim((string) ($row['note'] ?? ''));
                if ($status !== 'flagged') {
                    $note = '';
                }
                $fieldStates[$fieldKey] = [
                    'label' => $fieldLabel,
                    'status' => $status,
                    'note' => $note !== '' ? $note : null,
                    'reviewed_by' => $status === 'pending' ? null : $admin->id,
                    'reviewed_at' => $status === 'pending' ? null : now()->toDateTimeString(),
                ];
            }

            $isSubmitted = (string) ($sectionSubmittedInput[$sectionKey] ?? '0') === '1';
            $hasPending = collect($fieldStates)->contains(fn (array $row): bool => ($row['status'] ?? 'pending') === 'pending');
            $hasFlagged = collect($fieldStates)->contains(fn (array $row): bool => ($row['status'] ?? 'pending') === 'flagged');
            $missingFlaggedNotes = collect($fieldStates)->contains(
                fn (array $row): bool => ($row['status'] ?? '') === 'flagged' && trim((string) ($row['note'] ?? '')) === ''
            );

            if ($isSubmitted && ($hasPending || $missingFlaggedNotes)) {
                return back()->withErrors([
                    'section_review' => 'All fields must be reviewed and every flagged field needs a note before submitting a section.',
                ])->withInput();
            }

            $normalizedFieldReviews[$sectionKey] = $fieldStates;
            $normalizedSectionReviews[$sectionKey] = [
                'status' => ! $isSubmitted ? 'pending' : ($hasFlagged ? 'needs_revision' : 'verified'),
                'submitted' => $isSubmitted,
                'reviewed_by' => $isSubmitted ? $admin->id : null,
                'reviewed_at' => $isSubmitted ? now()->toDateTimeString() : null,
            ];
        }

        if (isset($normalizedFieldReviews['adviser']['status'])) {
            unset($normalizedFieldReviews['adviser']['status']);
        }

        $allSectionsSubmitted = collect($normalizedSectionReviews)->every(fn (array $row): bool => (bool) ($row['submitted'] ?? false));
        $allSectionsVerified = collect($normalizedSectionReviews)->every(fn (array $row): bool => ($row['status'] ?? 'pending') === 'verified');
        $hasNeedsRevision = collect($normalizedSectionReviews)->contains(fn (array $row): bool => ($row['status'] ?? 'pending') === 'needs_revision');

        $remarks = trim((string) ($validated['remarks'] ?? ''));
        if (! $allSectionsSubmitted || (! $allSectionsVerified && ! $hasNeedsRevision)) {
            return back()->withErrors([
                'section_review' => 'Review all fields and provide notes for every revision-marked field before finalizing.',
            ])->withInput();
        }

        $flaggedNotes = $this->composeRegistrationFlaggedFieldNotes($normalizedFieldReviews);
        if ($hasNeedsRevision && $flaggedNotes === null) {
            return back()->withErrors([
                'section_review' => 'Flagged fields must include notes before submitting for revision.',
            ])->withInput();
        }

        DB::transaction(function () use ($submission, $remarks, $admin, $normalizedFieldReviews, $normalizedSectionReviews, $allSectionsVerified, $hasNeedsRevision, $flaggedNotes): void {
            $nextStatus = match (true) {
                $allSectionsVerified => OrganizationSubmission::STATUS_APPROVED,
                $hasNeedsRevision => OrganizationSubmission::STATUS_REVISION,
                default => OrganizationSubmission::STATUS_PENDING,
            };
            $effectiveRemarks = $remarks;
            $effectiveRevisionNotes = $nextStatus === OrganizationSubmission::STATUS_REVISION
                ? implode("\n\n", array_filter([
                    $flaggedNotes,
                    $effectiveRemarks !== '' ? "General remarks\n".$effectiveRemarks : null,
                ]))
                : '';
            $submissionPayload = [
                'status' => $nextStatus,
                'approval_decision' => $nextStatus === OrganizationSubmission::STATUS_APPROVED ? 'approved' : null,
                'additional_remarks' => $effectiveRemarks !== '' ? $effectiveRemarks : null,
                'notes' => $nextStatus === OrganizationSubmission::STATUS_REVISION
                    ? ($effectiveRevisionNotes !== '' ? $effectiveRevisionNotes : $submission->notes)
                    : ($effectiveRemarks !== '' ? $effectiveRemarks : $submission->notes),
                'current_approval_step' => 0,
                'registration_field_reviews' => $normalizedFieldReviews,
                'registration_section_reviews' => $normalizedSectionReviews,
            ];
            if (Schema::hasColumn('organization_submissions', 'review_status')) {
                $submissionPayload['review_status'] = $nextStatus === OrganizationSubmission::STATUS_APPROVED
                    ? 'approved'
                    : ($nextStatus === OrganizationSubmission::STATUS_REVISION ? 'revision' : 'pending');
            }
            if ($nextStatus === OrganizationSubmission::STATUS_APPROVED) {
                if (Schema::hasColumn('organization_submissions', 'approved_at')) {
                    $submissionPayload['approved_at'] = now();
                }
                if (Schema::hasColumn('organization_submissions', 'approved_by')) {
                    $submissionPayload['approved_by'] = (int) $admin->id;
                }
            }
            $submission->update($submissionPayload);

            $this->syncAdviserNominationStatusFromRegistrationFieldReviews($submission, $normalizedFieldReviews, (int) $admin->id);

            if ($nextStatus === OrganizationSubmission::STATUS_APPROVED) {
                $organizationPayload = ['status' => 'active'];
                if (Schema::hasColumn('organizations', 'active_at')) {
                    $organizationPayload['active_at'] = now();
                }
                $submission->organization?->update($organizationPayload);
                OrganizationProfileRevision::query()
                    ->where('organization_id', $submission->organization_id)
                    ->where('status', 'open')
                    ->update([
                        'status' => 'addressed',
                        'addressed_at' => now(),
                    ]);
            }

            if ($nextStatus === OrganizationSubmission::STATUS_REVISION && $effectiveRevisionNotes !== '') {
                OrganizationProfileRevision::query()->create([
                    'organization_id' => $submission->organization_id,
                    'requested_by' => $admin->id,
                    'revision_notes' => $effectiveRevisionNotes,
                    'status' => 'open',
                ]);
            }
        });

        $this->autoAcknowledgeReviewedRegistrationFieldUpdates($submission, $normalizedFieldReviews, (int) $admin->id);
        $this->notifyRegistrationStatusChange($submission);

        $submission->refresh();
        $finalStatus = (string) ($submission->status ?? OrganizationSubmission::STATUS_PENDING);

        $blockType = 'pending';
        $blockTitle = 'Registration review saved';
        $blockMessage = 'Your review progress has been saved.';
        if ($finalStatus === OrganizationSubmission::STATUS_REVISION) {
            $blockType = 'warning';
            $blockTitle = 'Revision request sent';
            $blockMessage = 'The organization registration has been returned for revision.';
        } elseif ($finalStatus === OrganizationSubmission::STATUS_APPROVED) {
            $blockType = 'success';
            $blockTitle = 'Organization has been approved';
            $blockMessage = 'The organization registration has been approved successfully.';
        }

        return redirect()
            ->route('admin.registrations.show', $submission)
            ->with([
                'review_block_type' => $blockType,
                'review_block_title' => $blockTitle,
                'review_block_message' => $blockMessage,
            ]);
    }

    public function saveRegistrationReviewDraft(Request $request, OrganizationSubmission $submission): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);

        // Registration review changes must not touch the database until the admin finalizes via POST
        // (updateRegistrationStatus / save review). This endpoint remains for compatibility but is a no-op.
        return response()->json([
            'ok' => true,
            'persisted' => false,
        ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function registrationReviewFieldSchema(): array
    {
        $requirementLabels = [
            'letter_of_intent' => 'Letter of Intent',
            'application_form' => 'Application Form',
            'by_laws' => 'By Laws of the Organization',
            'updated_list_of_officers_founders' => 'Updated List of Officers/Founders',
            'dean_endorsement_faculty_adviser' => 'Letter from the School Dean endorsing the Faculty Adviser',
            'proposed_projects_budget' => 'List of Proposed Projects with Proposed Budget for the AY',
            'others' => 'Others',
        ];
        $requirementFields = [];
        foreach (SubmissionRequirement::requirementKeysForType(OrganizationSubmission::TYPE_REGISTRATION) as $key) {
            $requirementFields[$key] = $requirementLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
        }

        return [
            // Application Information: only Organization is reviewable; academic year, submission date,
            // and submitter are system context (see stripNonReviewableApplicationRegistrationFieldReviews).
            'application' => [
                'organization' => 'Organization',
            ],
            'contact' => [
                'contact_person' => 'Contact Person',
                'contact_no' => 'Contact Number',
                'contact_email' => 'Email Address',
            ],
            'adviser' => [
                'full_name' => 'Full Name',
                'school_id' => 'School ID',
                'email' => 'Email',
            ],
            'organizational' => [
                'organization_type' => 'Type of Organization',
                'school' => 'School',
                'purpose' => 'Purpose of Organization',
            ],
            'requirements' => $requirementFields,
        ];
    }

    /**
     * Registration "application" section keys that are system context only (not officer-revisable).
     *
     * @return list<string>
     */
    private function nonReviewableApplicationRegistrationFieldKeys(): array
    {
        return ['academic_year', 'submission_date', 'submitted_by'];
    }

    private function isNonReviewableApplicationRegistrationField(string $sectionKey, string $fieldKey): bool
    {
        return $sectionKey === 'application'
            && in_array($fieldKey, $this->nonReviewableApplicationRegistrationFieldKeys(), true);
    }

    /**
     * @param  array<string, mixed>  $fieldReviews
     * @return array<string, mixed>
     */
    private function stripNonReviewableApplicationRegistrationFieldReviews(array $fieldReviews): array
    {
        $section = $fieldReviews['application'] ?? null;
        if (! is_array($section)) {
            return $fieldReviews;
        }
        foreach ($this->nonReviewableApplicationRegistrationFieldKeys() as $key) {
            unset($fieldReviews['application'][$key]);
        }

        return $fieldReviews;
    }

    /**
     * Registration "organizational" keys that are system context only (not officer-revisable).
     *
     * @return list<string>
     */
    private function nonReviewableOrganizationalRegistrationFieldKeys(): array
    {
        return ['date_organized', 'founded_date', 'founded_at', 'date_created', 'created_at'];
    }

    private function isNonReviewableOrganizationalRegistrationField(string $sectionKey, string $fieldKey): bool
    {
        return $sectionKey === 'organizational'
            && in_array($fieldKey, $this->nonReviewableOrganizationalRegistrationFieldKeys(), true);
    }

    /**
     * @param  array<string, mixed>  $fieldReviews
     * @return array<string, mixed>
     */
    private function stripNonReviewableOrganizationalRegistrationFieldReviews(array $fieldReviews): array
    {
        $section = $fieldReviews['organizational'] ?? null;
        if (! is_array($section)) {
            return $fieldReviews;
        }
        foreach ($this->nonReviewableOrganizationalRegistrationFieldKeys() as $key) {
            unset($fieldReviews['organizational'][$key]);
        }

        return $fieldReviews;
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $fieldReviews
     */
    private function composeRegistrationFlaggedFieldNotes(array $fieldReviews): ?string
    {
        $sectionTitles = [
            'application' => 'Application Information',
            'contact' => 'Account and Contact Information',
            'adviser' => 'Adviser Information',
            'organizational' => 'Organization Details',
            'requirements' => 'Requirements Attached',
        ];

        $blocks = [];
        foreach ($fieldReviews as $sectionKey => $fields) {
            $items = [];
            foreach ($fields as $fieldKey => $field) {
                if ((string) $sectionKey === 'adviser' && (string) $fieldKey === 'status') {
                    continue;
                }
                if ($this->isNonReviewableApplicationRegistrationField((string) $sectionKey, (string) $fieldKey)) {
                    continue;
                }
                if ($this->isNonReviewableOrganizationalRegistrationField((string) $sectionKey, (string) $fieldKey)) {
                    continue;
                }
                if (($field['status'] ?? '') !== 'flagged') {
                    continue;
                }
                $label = (string) ($field['label'] ?? 'Field');
                $note = trim((string) ($field['note'] ?? ''));
                if ($note !== '') {
                    $items[] = "- {$label}: {$note}";
                }
            }
            if ($items !== []) {
                $title = $sectionTitles[$sectionKey] ?? ucwords(str_replace('_', ' ', $sectionKey));
                $blocks[] = $title."\n".implode("\n", $items);
            }
        }

        return $blocks === [] ? null : implode("\n\n—\n\n", $blocks);
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $normalizedFieldReviews
     */
    private function syncAdviserNominationStatusFromRegistrationFieldReviews(OrganizationSubmission $submission, array $normalizedFieldReviews, int $adminUserId): void
    {
        $nomination = OrganizationAdviser::query()
            ->where('organization_id', $submission->organization_id)
            ->where('submission_id', (int) $submission->id)
            ->latest('id')
            ->first();

        if (! $nomination) {
            return;
        }

        if (strtolower((string) $nomination->status) === 'rejected') {
            return;
        }

        $adviser = is_array($normalizedFieldReviews['adviser'] ?? null) ? $normalizedFieldReviews['adviser'] : [];
        $statuses = [];
        foreach (self::REGISTRATION_ADVISER_INFORMATION_REVIEW_KEYS as $key) {
            $row = is_array($adviser[$key] ?? null) ? $adviser[$key] : [];
            $s = strtolower(trim((string) ($row['status'] ?? 'pending')));
            if (! in_array($s, ['pending', 'passed', 'flagged'], true)) {
                $s = 'pending';
            }
            $statuses[] = $s;
        }

        $allPassed = $statuses === ['passed', 'passed', 'passed'];
        $hasFlagged = in_array('flagged', $statuses, true);

        if ($allPassed) {
            $nomination->update([
                'status' => 'approved',
                'reviewed_by' => $adminUserId,
                'reviewed_at' => now(),
                'rejection_notes' => null,
                'relieved_at' => null,
            ]);

            return;
        }

        if ($hasFlagged) {
            $nomination->update([
                'status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            return;
        }

        $nomination->update([
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    /**
     * @param  array<string, string>  $sectionFields  revision_comment_* => trimmed text
     */
    private function composeRegistrationRevisionNotesForProfile(string $generalRemarks, array $sectionFields): ?string
    {
        $blocks = [];
        if ($generalRemarks !== '') {
            $blocks[] = "General remarks\n".$generalRemarks;
        }
        $titles = [
            'revision_comment_application' => 'Application Information',
            'revision_comment_contact' => 'Contact Information',
            'revision_comment_organizational' => 'Organizational Details',
            'revision_comment_requirements' => 'Requirements Attached',
        ];
        foreach ($titles as $key => $title) {
            $body = $sectionFields[$key] ?? '';
            if ($body !== '') {
                $blocks[] = $title."\n".$body;
            }
        }

        return $blocks === [] ? null : implode("\n\n—\n\n", $blocks);
    }

    public function showRenewal(Request $request, OrganizationSubmission $submission): View
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_RENEWAL, 404);

        $submission->load(['organization', 'submittedBy', 'academicTerm', 'attachments', 'requirements']);
        $latestUpdates = OrganizationRevisionFieldUpdate::query()
            ->where('organization_submission_id', $submission->id)
            ->with(['resubmittedBy:id,first_name,last_name', 'acknowledgedBy:id,first_name,last_name'])
            ->orderByDesc('id')
            ->get()
            ->unique(fn (OrganizationRevisionFieldUpdate $row): string => $row->section_key.'.'.$row->field_key)
            ->values();
        $updatesByField = [];
        $storedFieldReviews = is_array($submission->renewal_field_reviews) ? $submission->renewal_field_reviews : [];
        foreach ($latestUpdates as $row) {
            if (! isset($updatesByField[$row->section_key])) {
                $updatesByField[$row->section_key] = [];
            }
            $updatesByField[$row->section_key][$row->field_key] = [
                'id' => (int) $row->id,
                'section_key' => (string) $row->section_key,
                'field_key' => (string) $row->field_key,
                'old_value' => $row->old_value,
                'new_value' => $row->new_value,
                'resubmitted_at' => optional($row->resubmitted_at)->toDateTimeString(),
                'resubmitted_by' => $row->resubmittedBy?->full_name ?? 'Unknown',
                'acknowledged_at' => optional($row->acknowledged_at)->toDateTimeString(),
                'acknowledged_by' => $row->acknowledgedBy?->full_name ?? null,
                'is_updated' => $row->acknowledged_at === null,
            ];
        }
        $effectiveFieldReviews = $this->resetUpdatedFieldReviewStates($storedFieldReviews, $updatesByField);
        $sections = $this->renewalReviewSections($submission);

        return view('admin.reviews.module-show', [
            'pageTitle' => 'Renewal Submission Details',
            'moduleLabel' => 'Renewal',
            'status' => $submission->legacyStatus(),
            'sections' => $sections,
            'persistedFieldReviews' => $effectiveFieldReviews,
            'persistedSectionReviews' => is_array($submission->renewal_section_reviews) ? $submission->renewal_section_reviews : [],
            'persistedRemarks' => $submission->additional_remarks ?? '',
            'saveRoute' => route('admin.renewals.update-status', $submission),
            'draftRoute' => route('admin.renewals.review-draft', $submission),
            'backRoute' => route('admin.renewals.index'),
            'backLabel' => 'Back to Renewals',
            'fieldUpdateDiffs' => $updatesByField,
        ]);
    }

    public function requestOrganizationProfileRevision(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'profile_revision_notes' => ['required', 'string', 'max:2000'],
        ]);

        OrganizationProfileRevision::query()->create([
            'organization_id' => $organization->id,
            'requested_by' => (int) $request->user()->id,
            'revision_notes' => (string) $validated['profile_revision_notes'],
            'status' => 'open',
        ]);
        $this->notifyOrganizationOfficers(
            $organization,
            'Profile Update Requested',
            'SDAO requested updates to your organization profile.',
            'warning',
            route('organizations.profile'),
            $organization
        );

        return back()->with(
            'success',
            'Organization profile revision has been requested. The organization officer may edit their profile.',
        );
    }

    public function showCalendar(Request $request, ActivityCalendar $calendar): View
    {
        $this->authorizeAdmin($request);

        $calendar->load([
            'organization',
            'submittedBy',
            'academicTerm',
            'entries' => fn ($query) => $query->orderBy('activity_date')->orderBy('id'),
        ]);

        [$effectiveFieldReviews, $fieldUpdateDiffs] = $this->loadModuleFieldUpdateContext($calendar, 'admin_field_reviews');

        return view('admin.reviews.module-show', [
            'pageTitle' => 'Activity Calendar Submission Details',
            'moduleLabel' => 'Activity Calendar',
            'status' => strtoupper((string) ($calendar->status ?? 'pending')),
            'sections' => $this->calendarReviewSections($calendar),
            'persistedFieldReviews' => $effectiveFieldReviews,
            'persistedSectionReviews' => is_array($calendar->admin_section_reviews) ? $calendar->admin_section_reviews : [],
            'persistedRemarks' => $calendar->admin_review_remarks ?? '',
            'saveRoute' => route('admin.calendars.update-status', $calendar),
            'draftRoute' => route('admin.calendars.review-draft', $calendar),
            'backRoute' => route('admin.calendars.index'),
            'backLabel' => 'Back to Activity Calendars',
            'fieldUpdateDiffs' => $fieldUpdateDiffs,
            'updatedInformationGroups' => $this->activityCalendarUpdatedInformationGroups($fieldUpdateDiffs, $calendar),
        ]);
    }

    public function streamCalendarFile(Request $request, ActivityCalendar $calendar): StreamedResponse
    {
        $this->authorizeAdmin($request);
        $relativePath = (string) ($calendar->calendar_file ?? '');
        if ($relativePath === '') {
            abort(404);
        }

        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            abort(404);
        }

        return $disk->response($relativePath, basename($relativePath), [], 'inline');
    }

    public function downloadCalendarFile(Request $request, ActivityCalendar $calendar): Response
    {
        $this->authorizeAdmin($request);
        $relativePath = trim((string) ($calendar->calendar_file ?? ''));
        if ($relativePath === '') {
            abort(404);
        }
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            abort(404);
        }

        $safeFilename = basename($relativePath);
        $safeFilename = (string) preg_replace('/[\x00-\x1F"\\\\]/u', '', $safeFilename);
        if ($safeFilename === '') {
            $safeFilename = 'calendar-file';
        }

        $mime = 'application/octet-stream';
        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($relativePath)) {
            try {
                $detected = $publicDisk->mimeType($relativePath);
                if (is_string($detected) && trim($detected) !== '') {
                    $mime = $detected;
                }
            } catch (\Throwable $e) {
            }
        }

        return $this->downloadStoredPathPreferSupabase($relativePath, $safeFilename, $mime);
    }

    public function streamReportFile(Request $request, ActivityReport $report, string $key): StreamedResponse
    {
        $this->authorizeAdmin($request);
        $allowed = in_array($key, ['poster', 'attendance', 'certificate', 'evaluation_form'], true)
            || preg_match('/^supporting_\d+$/', $key) === 1;
        abort_unless($allowed, 404);

        $relativePath = $this->reportFilePathForAdmin($report, $key);
        if (! is_string($relativePath) || $relativePath === '') {
            abort(404);
        }
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            abort(404);
        }

        $organizationId = (int) $report->organization_id;
        $expectedPrefixes = [
            'activity-reports/'.$organizationId.'/',
        ];
        $ok = false;
        foreach ($expectedPrefixes as $pfx) {
            if (str_starts_with($relativePath, $pfx)) {
                $ok = true;
                break;
            }
        }
        abort_unless($ok, 404);

        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            abort(404);
        }

        return $disk->response($relativePath, basename($relativePath), [], 'inline');
    }

    public function downloadReportFile(Request $request, ActivityReport $report, string $key): Response
    {
        $this->authorizeAdmin($request);
        $allowed = in_array($key, ['poster', 'attendance', 'certificate', 'evaluation_form'], true)
            || preg_match('/^supporting_\d+$/', $key) === 1;
        abort_unless($allowed, 404);

        $report->loadMissing('attachments');
        $attachment = $this->reportAttachmentForAdminKey($report, $key);
        if ($attachment && is_string($attachment->stored_path) && trim($attachment->stored_path) !== '') {
            return $this->redirectAttachmentDownload($attachment);
        }

        $relativePath = $this->reportFilePathForAdmin($report, $key);
        if (! is_string($relativePath) || $relativePath === '') {
            abort(404);
        }
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            abort(404);
        }

        $organizationId = (int) $report->organization_id;
        $expectedPrefixes = [
            'activity-reports/'.$organizationId.'/',
        ];
        $ok = false;
        foreach ($expectedPrefixes as $pfx) {
            if (str_starts_with($relativePath, $pfx)) {
                $ok = true;
                break;
            }
        }
        abort_unless($ok, 404);

        $safeFilename = basename($relativePath);
        $safeFilename = (string) preg_replace('/[\x00-\x1F"\\\\]/u', '', $safeFilename);
        if ($safeFilename === '') {
            $safeFilename = 'download';
        }

        $mime = 'application/octet-stream';
        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($relativePath)) {
            try {
                $detected = $publicDisk->mimeType($relativePath);
                if (is_string($detected) && trim($detected) !== '') {
                    $mime = $detected;
                }
            } catch (\Throwable $e) {
            }
        }

        return $this->downloadStoredPathPreferSupabase($relativePath, $safeFilename, $mime);
    }

    public function showProposal(Request $request, ActivityProposal $proposal): View
    {
        $this->authorizeAdmin($request);

        $proposal->load([
            'organization',
            'user',
            'submittedBy',
            'academicTerm',
            'budgetItems',
            'workflowSteps.role',
            'approvalLogs.actor',
            'calendar',
            'calendarEntry',
            'attachments',
        ]);
        $this->ensureProposalWorkflow($proposal);
        $proposal->load(['workflowSteps.role', 'approvalLogs.actor']);
        $requestForm = $this->relatedRequestFormForProposal($proposal);

        $proposalTime = $this->proposalTimeRangeLabel($proposal);
        $budgetRows = $proposal->budgetItems->values();
        $budgetRowsTotal = $budgetRows->sum(fn ($row) => (float) ($row->total_cost ?? 0));
        $linkedCalendar = $proposal->calendar
            ? trim(($proposal->calendar->academic_year ?? '—').' · '.(string) ($proposal->calendar->semester ?? '—'))
            : '—';
        $calendarRow = $proposal->calendarEntry
            ? trim(($proposal->calendarEntry->activity_name ?? '—').' · '.(optional($proposal->calendarEntry->activity_date)->format('M j, Y') ?? ''))
            : '—';
        $department = (string) ($proposal->school_code ?: ($proposal->organization?->college_school ?? ''));
        $step1ActivityDate = $requestForm?->activity_date
            ? optional($requestForm->activity_date)->format('M d, Y')
            : null;
        $sourceOfFunding = (string) ($proposal->source_of_funding ?? '');
        $isExternalFunding = strtoupper($sourceOfFunding) === 'EXTERNAL';

        $step1Rows = [
            ['key' => 'step1_proposal_option', 'label' => 'Proposal Option', 'value' => $proposal->activity_calendar_entry_id ? 'From submitted Activity Calendar' : 'Activity not in submitted calendar'],
            ['key' => 'step1_rso_name', 'label' => 'RSO Name', 'value' => $requestForm?->rso_name ?: ($proposal->organization?->organization_name ?? '—')],
            ['key' => 'step1_activity_title', 'label' => 'Title of Activity', 'value' => $requestForm?->activity_title ?: '—'],
            ['key' => 'step1_partner_entities', 'label' => 'Partner Entities', 'value' => $requestForm?->partner_entities ?: '—'],
            ['key' => 'step1_nature_of_activity', 'label' => 'Nature of Activity', 'value' => $this->requestFormOptionsLabel((array) ($requestForm?->nature_of_activity ?? []), $requestForm?->nature_other), 'wide' => true],
            ['key' => 'step1_type_of_activity', 'label' => 'Type of Activity', 'value' => $this->requestFormOptionsLabel((array) ($requestForm?->activity_types ?? []), $requestForm?->activity_type_other), 'wide' => true],
            ['key' => 'step1_target_sdg', 'label' => 'Target SDG', 'value' => $requestForm?->target_sdg ?: ($proposal->target_sdg ?: '—')],
            ['key' => 'step1_proposed_budget', 'label' => 'Step 1 Proposed Budget', 'value' => $requestForm?->proposed_budget !== null ? number_format((float) $requestForm->proposed_budget, 2) : '—'],
            ['key' => 'step1_budget_source', 'label' => 'Step 1 Budget Source', 'value' => $requestForm?->budget_source ?: '—'],
            ['key' => 'step1_activity_date', 'label' => 'Date of Activity', 'value' => $step1ActivityDate ?: '—'],
            ['key' => 'step1_venue', 'label' => 'Venue', 'value' => $requestForm?->venue ?: '—'],
        ];
        if ($proposal->activity_calendar_entry_id) {
            array_splice($step1Rows, 2, 0, [
                ['key' => 'step1_linked_activity_calendar', 'label' => 'Linked Activity Calendar', 'value' => $linkedCalendar],
                ['key' => 'step1_calendar_activity_row', 'label' => 'Calendar Activity Row', 'value' => $calendarRow],
            ]);
        }

        $proposalRequirementsFields = $this->proposalRequirementsAttachmentFields($proposal, $requestForm, $isExternalFunding);

        $step2Rows = [
            ['key' => 'step2_organization', 'label' => 'Organization (Form)', 'value' => $proposal->organization?->organization_name ?: '—'],
            ['key' => 'step2_academic_year', 'label' => 'Academic Year', 'value' => $proposal->academicTerm?->academic_year ?: '—'],
            ['key' => 'step2_department', 'label' => 'Department', 'value' => $department !== '' ? $department : '—'],
            ['key' => 'step2_program', 'label' => 'Program', 'value' => $proposal->program ?: '—'],
            ['key' => 'step2_activity_title', 'label' => 'Project / Activity Title', 'value' => $proposal->activity_title ?: '—'],
            ['key' => 'step2_proposed_dates', 'label' => 'Proposed Dates', 'value' => trim(collect([
                optional($proposal->proposed_start_date)->format('M d, Y'),
                optional($proposal->proposed_end_date)->format('M d, Y'),
            ])->filter()->implode(' - ')) ?: '—'],
            ['key' => 'step2_proposed_time', 'label' => 'Proposed Time', 'value' => $proposalTime],
            ['key' => 'step2_venue', 'label' => 'Venue', 'value' => $proposal->venue ?: '—'],
            ['key' => 'step2_overall_goal', 'label' => 'Overall Goal', 'value' => $proposal->overall_goal ?: '—', 'wide' => true],
            ['key' => 'step2_specific_objectives', 'label' => 'Specific Objectives', 'value' => $proposal->specific_objectives ?: '—', 'wide' => true],
            ['key' => 'step2_criteria_mechanics', 'label' => 'Criteria / Mechanics', 'value' => $proposal->criteria_mechanics ?: '—', 'wide' => true],
            ['key' => 'step2_program_flow', 'label' => 'Program Flow', 'value' => $proposal->program_flow ?: '—', 'wide' => true],
            ['key' => 'step2_budget_total', 'label' => 'Proposed Budget (Total)', 'value' => $proposal->estimated_budget !== null ? number_format((float) $proposal->estimated_budget, 2) : '—'],
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
                'wide' => true,
            ],
        ];

        [$effectiveFieldReviews, $fieldUpdateDiffs] = $this->loadModuleFieldUpdateContext($proposal, 'admin_field_reviews');

        return view('admin.reviews.module-show', [
            'pageTitle' => 'Activity Proposal Submission Details',
            'moduleLabel' => 'Activity Proposal',
            'status' => strtoupper((string) ($proposal->status ?? 'pending')),
            'sections' => $this->proposalReviewSections($step1Rows, $step2Rows, $proposalRequirementsFields),
            'persistedFieldReviews' => $effectiveFieldReviews,
            'persistedSectionReviews' => is_array($proposal->admin_section_reviews) ? $proposal->admin_section_reviews : [],
            'persistedRemarks' => $proposal->admin_review_remarks ?? '',
            'saveRoute' => route('admin.proposals.update-status', $proposal),
            'draftRoute' => route('admin.proposals.review-draft', $proposal),
            'backRoute' => route('admin.proposals.index'),
            'backLabel' => 'Back to Activity Proposals',
            'fieldUpdateDiffs' => $fieldUpdateDiffs,
        ]);
    }

    public function streamProposalFile(Request $request, ActivityProposal $proposal, string $key): Response
    {
        $this->authorizeAdmin($request);
        $proposal->loadMissing('attachments');
        $requestForm = $this->relatedRequestFormForProposal($proposal);
        $relativePath = $this->proposalReviewFilePathByKey($proposal, $requestForm, $key);

        Log::info('Admin: activity proposal file view attempt', [
            'proposal_id' => $proposal->id,
            'key' => $key,
            'stored_path' => $relativePath,
        ]);

        if (! is_string($relativePath) || trim($relativePath) === '') {
            abort(404, 'No file has been uploaded for this proposal attachment.');
        }
        $relativePath = trim($relativePath);
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            abort(404);
        }

        $disk = Storage::disk('supabase');
        $existsInSupabase = false;

        try {
            $existsInSupabase = $disk->exists($relativePath);
        } catch (\Throwable $e) {
            Log::warning('Admin: proposal attachment exists() check failed on Supabase.', [
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
                Log::warning('Admin: failed to generate Supabase temporaryUrl for proposal attachment.', [
                    'proposal_id' => $proposal->id,
                    'stored_path' => $relativePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($relativePath)) {
            Log::warning('Admin: proposal attachment found on local public disk only (legacy).', [
                'proposal_id' => $proposal->id,
                'stored_path' => $relativePath,
            ]);

            return $publicDisk->response($relativePath, basename($relativePath), [], 'inline');
        }

        abort(404, 'The file could not be found in Supabase Storage.');
    }

    public function downloadProposalFile(Request $request, ActivityProposal $proposal, string $key): Response
    {
        $this->authorizeAdmin($request);
        $proposal->loadMissing('attachments');
        $requestForm = $this->relatedRequestFormForProposal($proposal);
        $attachment = $this->proposalAttachmentForReviewKey($proposal, $requestForm, $key);
        if (! $attachment) {
            abort(404, 'No file has been uploaded for this proposal attachment.');
        }

        return $this->redirectAttachmentDownload($attachment);
    }

    public function updateProposalWorkflow(Request $request, ActivityProposal $proposal): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $this->ensureProposalWorkflow($proposal);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'revision'])],
            'comments' => ['nullable', 'string', 'max:5000'],
        ]);

        $action = (string) $validated['action'];
        $comments = trim((string) ($validated['comments'] ?? ''));
        $fromStatus = strtoupper((string) ($proposal->status ?? 'PENDING'));

        /** @var User $admin */
        $admin = $request->user();

        DB::transaction(function () use ($proposal, $action, $comments, $fromStatus, $admin): void {
            $currentStep = $proposal->workflowSteps()
                ->where('is_current_step', true)
                ->orderBy('step_order')
                ->first();

            if (! $currentStep) {
                $currentStep = $proposal->workflowSteps()->orderBy('step_order')->first();
                if ($currentStep) {
                    $currentStep->update(['is_current_step' => true]);
                }
            }

            if (! $currentStep) {
                abort(422, 'No workflow steps available for this proposal.');
            }

            $toStatus = $fromStatus;
            $logAction = 'approved';

            $currentApprovalStep = (int) $currentStep->step_order;
            if ($action === 'approve') {
                $currentStep->update([
                    'assigned_to' => $admin->id,
                    'status' => 'approved',
                    'is_current_step' => false,
                    'review_comments' => $comments !== '' ? $comments : null,
                    'acted_at' => now(),
                ]);

                $nextStep = $proposal->workflowSteps()
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
                $logAction = 'approved';
            } elseif ($action === 'revision') {
                $currentStep->update([
                    'assigned_to' => $admin->id,
                    'status' => 'revision_required',
                    'is_current_step' => false,
                    'review_comments' => $comments !== '' ? $comments : null,
                    'acted_at' => now(),
                ]);
                $toStatus = 'REVISION';
                $logAction = 'revision_requested';
            } else {
                $currentStep->update([
                    'assigned_to' => $admin->id,
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
                'approvable_type' => ActivityProposal::class,
                'approvable_id' => $proposal->id,
                'workflow_step_id' => $currentStep->id,
                'actor_id' => $admin->id,
                'action' => $logAction,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'comments' => $comments !== '' ? $comments : null,
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('admin.proposals.show', $proposal)
            ->with('success', 'Proposal workflow decision saved.');
    }

    public function showReport(Request $request, ActivityReport $report): View
    {
        $this->authorizeAdmin($request);

        $report->load(['organization', 'user', 'attachments']);

        $schoolLabels = [
            'sace' => 'School of Architecture, Computer and Engineering',
            'sahs' => 'School of Allied Health and Sciences',
            'sabm' => 'School of Accounting and Business Management',
            'shs' => 'Senior High School',
        ];

        $details = [
            'Organization' => $report->organization?->organization_name ?? 'N/A',
            'Submitted By' => $report->user?->full_name ?? 'N/A',
            'Submission Date' => optional($report->report_submission_date)->format('M d, Y') ?? 'N/A',
            'Activity / Event Title' => $report->activity_event_title ?? 'N/A',
            'School' => $report->school_code ? ($schoolLabels[$report->school_code] ?? $report->school_code) : 'N/A',
            'Department' => $report->department ?? 'N/A',
            'Event Name' => $report->event_name ?? 'N/A',
            'Event Date & Time' => optional($report->event_starts_at)->format('M d, Y g:i A') ?? 'N/A',
            'Activity Chair/s' => $report->activity_chairs ?? 'N/A',
            'Prepared By' => $report->prepared_by ?? 'N/A',
            'Program' => $report->program_content ?? 'N/A',
            'Summary / Description' => $report->accomplishment_summary ?? 'N/A',
            'Activity Evaluation' => $report->evaluation_report ?? 'N/A',
            'Participants Reached (%)' => $report->participants_reached_percent !== null ? (string) $report->participants_reached_percent.'%' : 'N/A',
            'Legacy Report File' => $report->report_file ?? 'N/A',
        ];

        [$effectiveFieldReviews, $fieldUpdateDiffs] = $this->loadModuleFieldUpdateContext($report, 'admin_field_reviews');

        return view('admin.reviews.module-show', [
            'pageTitle' => 'After Activity Report Submission Details',
            'moduleLabel' => 'After Activity Report',
            'status' => strtoupper((string) ($report->status ?? 'pending')),
            'sections' => $this->reportReviewSections($report, $details),
            'persistedFieldReviews' => $effectiveFieldReviews,
            'persistedSectionReviews' => is_array($report->admin_section_reviews) ? $report->admin_section_reviews : [],
            'persistedRemarks' => $report->admin_review_remarks ?? '',
            'saveRoute' => route('admin.reports.update-status', $report),
            'draftRoute' => route('admin.reports.review-draft', $report),
            'backRoute' => route('admin.reports.index'),
            'backLabel' => 'Back to After Activity Reports',
            'fieldUpdateDiffs' => $fieldUpdateDiffs,
        ]);
    }

    public function showRenewalRequirementFile(Request $request, OrganizationSubmission $submission, string $key): Response
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_RENEWAL, 404);
        if (! in_array($key, self::RENEWAL_REQUIREMENT_FILE_KEYS, true)) {
            abort(404);
        }

        return $this->redirectToSupabaseRequirementFile($submission, $key);
    }

    /**
     * Force-download a renewal requirement file (no preview, no modal).
     *
     * Mirrors `showRenewalRequirementFile` for authorization and lookup, but
     * emits the file with `Content-Disposition: attachment` so the browser
     * saves it directly using `attachments.original_name`.
     */
    public function downloadRenewalRequirementFile(Request $request, OrganizationSubmission $submission, string $key): Response
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_RENEWAL, 404);
        if (! in_array($key, self::RENEWAL_REQUIREMENT_FILE_KEYS, true)) {
            abort(404);
        }

        return $this->forceDownloadSupabaseRequirementFile($submission, $key);
    }

    /**
     * Look up a registration/renewal requirement attachment and redirect the
     * caller to its public Supabase Storage URL.
     *
     * The lookup tolerates both legacy bare keys (e.g. `by_laws`) and
     * namespaced keys (`registration_requirement:by_laws`,
     * `renewal_requirement:by_laws`) so older rows resolve correctly without
     * any data migration.
     */
    private function redirectToSupabaseRequirementFile(OrganizationSubmission $submission, string $requirementKey): Response
    {
        $attachment = Attachment::query()
            ->where('attachable_type', OrganizationSubmission::class)
            ->where('attachable_id', $submission->id)
            ->where(function ($query) use ($requirementKey): void {
                $query->where('file_type', $requirementKey)
                    ->orWhere('file_type', 'like', '%:'.$requirementKey);
            })
            ->latest('id')
            ->first();

        if (! $attachment) {
            abort(404, 'No file has been uploaded for this requirement yet.');
        }

        $relativePath = (string) $attachment->stored_path;
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            Log::warning('Submission attachment has an invalid stored_path.', [
                'attachment_id' => $attachment->id,
                'submission_id' => $submission->id,
                'requirement_key' => $requirementKey,
                'stored_path' => $attachment->stored_path,
            ]);

            abort(404, 'File not found.');
        }

        $disk = Storage::disk('supabase');
        $exists = $disk->exists($relativePath);

        Log::info('Resolving submission requirement attachment from Supabase Storage.', [
            'attachment_id' => $attachment->id,
            'submission_id' => $submission->id,
            'requirement_key' => $requirementKey,
            'stored_path' => $relativePath,
            'exists_in_supabase_storage' => $exists,
        ]);

        if (! $exists) {
            Log::warning('File missing from Supabase Storage', [
                'attachment_id' => $attachment->id,
                'stored_path' => $relativePath,
            ]);

            abort(404, 'The file could not be found in Supabase Storage.');
        }

        $publicUrl = rtrim((string) env('SUPABASE_STORAGE_PUBLIC_URL'), '/')
            .'/'.trim((string) env('SUPABASE_STORAGE_BUCKET'), '/')
            .'/'.ltrim($relativePath, '/');

        return redirect()->away($publicUrl);
    }

    /**
     * Look up a registration/renewal requirement attachment and emit it as a
     * forced download instead of an inline preview.
     *
     * Uses the same prefixed/exact `file_type` lookup as the view path so
     * legacy bare-key rows still resolve.
     */
    private function forceDownloadSupabaseRequirementFile(OrganizationSubmission $submission, string $requirementKey): Response
    {
        $attachment = Attachment::query()
            ->where('attachable_type', OrganizationSubmission::class)
            ->where('attachable_id', $submission->id)
            ->where(function ($query) use ($requirementKey): void {
                $query->where('file_type', $requirementKey)
                    ->orWhere('file_type', 'like', '%:'.$requirementKey);
            })
            ->latest('id')
            ->first();

        if (! $attachment) {
            abort(404, 'No file has been uploaded for this requirement yet.');
        }

        $relativePath = (string) $attachment->stored_path;
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            Log::warning('Submission attachment has an invalid stored_path during download.', [
                'attachment_id' => $attachment->id,
                'submission_id' => $submission->id,
                'requirement_key' => $requirementKey,
                'stored_path' => $attachment->stored_path,
            ]);

            abort(404, 'File not found.');
        }

        $originalName = trim((string) ($attachment->original_name ?? ''));
        if ($originalName === '') {
            $originalName = basename($relativePath);
        }

        return $this->forceDownloadSupabaseObject(
            $relativePath,
            $originalName,
            $attachment->mime_type ?: null,
            [
                'attachment_id' => $attachment->id,
                'submission_id' => $submission->id,
                'requirement_key' => $requirementKey,
            ]
        );
    }

    /**
     * Force-download a bucket-relative object from the `supabase` disk.
     *
     * Strategy:
     *   1. Verify the object exists (so we 404 cleanly instead of leaking a
     *      broken signed URL).
     *   2. Try a 15-minute presigned URL with `ResponseContentDisposition` +
     *      `ResponseContentType` so Supabase serves the response with
     *      `Content-Disposition: attachment` and the correct content type —
     *      this works for both public and private buckets without any
     *      Laravel-side proxying.
     *   3. If presigned URL generation fails (e.g. the underlying Flysystem
     *      adapter doesn't support `temporaryUrl`), fall back to streaming
     *      the bytes through the framework with the same headers so the
     *      browser still saves the file.
     *
     * @param  array<string, mixed>  $logContext  Non-secret IDs to attach to log lines (attachment id / submission id / etc.).
     */
    private function forceDownloadSupabaseObject(string $storedPath, string $originalName, ?string $mimeType, array $logContext = []): Response
    {
        $disk = Storage::disk('supabase');

        if (! $disk->exists($storedPath)) {
            Log::warning('File missing from Supabase Storage during download request', array_merge([
                'stored_path' => $storedPath,
            ], $logContext));

            abort(404, 'The file could not be found in Supabase Storage.');
        }

        $safeFilename = (string) preg_replace('/[\x00-\x1F"\\\\]/u', '', $originalName);
        if ($safeFilename === '') {
            $safeFilename = basename($storedPath);
        }
        $resolvedMime = ($mimeType !== null && trim($mimeType) !== '') ? $mimeType : 'application/octet-stream';

        try {
            $temporaryUrl = $disk->temporaryUrl(
                $storedPath,
                now()->addMinutes(15),
                [
                    'ResponseContentDisposition' => 'attachment; filename="'.$safeFilename.'"',
                    'ResponseContentType' => $resolvedMime,
                ]
            );

            return redirect()->away($temporaryUrl);
        } catch (\Throwable $e) {
            Log::warning('Falling back to streamed Supabase download (temporaryUrl failed).', array_merge([
                'stored_path' => $storedPath,
                'error' => $e->getMessage(),
                'exception' => class_basename($e),
            ], $logContext));
        }

        try {
            return response()->streamDownload(function () use ($disk, $storedPath): void {
                echo $disk->get($storedPath);
            }, $safeFilename, [
                'Content-Type' => $resolvedMime,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to stream download Supabase file', array_merge([
                'stored_path' => $storedPath,
                'error' => $e->getMessage(),
                'exception' => class_basename($e),
            ], $logContext));

            abort(500, 'Unable to download file.');
        }
    }

    public function saveRenewalReviewDraft(Request $request, OrganizationSubmission $submission): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_RENEWAL, 404);

        return $this->saveModuleDraft($request, $submission, $this->renewalReviewSchema(), 'renewal_field_reviews', 'renewal_section_reviews');
    }

    public function updateRenewalStatus(Request $request, OrganizationSubmission $submission): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_RENEWAL, 404);
        $validated = $request->validate([
            'field_review' => ['nullable', 'array'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var User $admin */
        $admin = $request->user();
        [$fieldReviews, $sectionReviews, $hasPending, $hasMissingNotes] = $this->normalizeModuleFieldReviews(
            is_array($validated['field_review'] ?? null) ? $validated['field_review'] : [],
            $this->renewalReviewSchema(),
            $admin
        );
        if ($hasPending || $hasMissingNotes) {
            return back()->withErrors(['field_review' => 'Review all fields and provide notes for every revision-marked field before finalizing.'])->withInput();
        }

        $allVerified = collect($sectionReviews)->every(fn (array $row): bool => ($row['status'] ?? 'pending') === 'verified');
        $nextStatus = $allVerified ? OrganizationSubmission::STATUS_APPROVED : OrganizationSubmission::STATUS_REVISION;
        $remarks = trim((string) ($validated['remarks'] ?? ''));
        $notes = $this->composeGenericFlaggedNotes($fieldReviews);

        DB::transaction(function () use ($submission, $fieldReviews, $sectionReviews, $remarks, $nextStatus, $notes, $admin): void {
            $payload = [
                'status' => $nextStatus,
                'renewal_field_reviews' => $fieldReviews,
                'renewal_section_reviews' => $sectionReviews,
                'additional_remarks' => $remarks !== '' ? $remarks : null,
                'approval_decision' => $nextStatus === OrganizationSubmission::STATUS_APPROVED ? 'approved' : null,
                'notes' => $nextStatus === OrganizationSubmission::STATUS_REVISION
                    ? ($notes ?? ($remarks !== '' ? $remarks : $submission->notes))
                    : ($remarks !== '' ? $remarks : $submission->notes),
                'current_approval_step' => 0,
            ];
            if (Schema::hasColumn('organization_submissions', 'review_status')) {
                $payload['review_status'] = $nextStatus === OrganizationSubmission::STATUS_APPROVED ? 'approved' : 'revision';
            }
            if ($nextStatus === OrganizationSubmission::STATUS_APPROVED) {
                if (Schema::hasColumn('organization_submissions', 'approved_at')) {
                    $payload['approved_at'] = now();
                }
                if (Schema::hasColumn('organization_submissions', 'approved_by')) {
                    $payload['approved_by'] = (int) $admin->id;
                }
            }
            $submission->update($payload);

            if ($nextStatus === OrganizationSubmission::STATUS_APPROVED) {
                $organizationPayload = ['status' => 'active'];
                if (Schema::hasColumn('organizations', 'active_at')) {
                    $organizationPayload['active_at'] = now();
                }
                $submission->organization?->update($organizationPayload);
            }
        });

        $this->autoAcknowledgeReviewedRegistrationFieldUpdates($submission, $fieldReviews, (int) $admin->id);
        $this->notifyModuleReviewResult($submission, $nextStatus);

        $renewalBackUrl = route('admin.renewals.index');
        $renewalBackLabel = 'Back to Renewals';
        $reviewFlash = $this->moduleReviewOutcomeReviewBlock(
            $nextStatus === OrganizationSubmission::STATUS_APPROVED ? 'approved' : 'revision',
            $renewalBackUrl,
            $renewalBackLabel,
            [
                'approved_title' => 'Organization renewal has been approved',
                'approved_body' => 'The organization renewal submission has been approved successfully.',
                'revision_title' => 'Revision request sent',
                'revision_body' => 'The organization renewal submission has been returned for revision.',
            ]
        );

        return redirect()
            ->route('admin.renewals.show', $submission)
            ->with($reviewFlash);
    }

    public function saveCalendarReviewDraft(Request $request, ActivityCalendar $calendar): JsonResponse
    {
        $this->authorizeAdmin($request);

        return $this->saveModuleDraft($request, $calendar, $this->calendarReviewSchema($calendar), 'admin_field_reviews', 'admin_section_reviews');
    }

    public function updateCalendarStatus(Request $request, ActivityCalendar $calendar): RedirectResponse
    {
        $this->authorizeAdmin($request);

        return $this->finalizeModuleReview(
            $request,
            $calendar,
            $this->calendarReviewSchema($calendar),
            'admin_field_reviews',
            'admin_section_reviews',
            'admin_review_remarks',
            route('admin.calendars.show', $calendar),
            route('admin.calendars.index'),
            'Back to Activity Calendars',
            [
                'approved_title' => 'Activity calendar has been approved',
                'approved_body' => 'The activity calendar submission has been approved successfully.',
                'revision_title' => 'Revision request sent',
                'revision_body' => 'The activity calendar submission has been returned for revision.',
            ]
        );
    }

    public function saveProposalReviewDraft(Request $request, ActivityProposal $proposal): JsonResponse
    {
        $this->authorizeAdmin($request);

        return $this->saveModuleDraft($request, $proposal, $this->proposalReviewSchema(), 'admin_field_reviews', 'admin_section_reviews');
    }

    public function updateProposalStatus(Request $request, ActivityProposal $proposal): RedirectResponse
    {
        $this->authorizeAdmin($request);

        return $this->finalizeModuleReview(
            $request,
            $proposal,
            $this->proposalReviewSchema(),
            'admin_field_reviews',
            'admin_section_reviews',
            'admin_review_remarks',
            route('admin.proposals.show', $proposal),
            route('admin.proposals.index'),
            'Back to Activity Proposals',
            [
                'approved_title' => 'Activity proposal has been approved',
                'approved_body' => 'The activity proposal submission has been approved successfully.',
                'revision_title' => 'Revision request sent',
                'revision_body' => 'The activity proposal submission has been returned for revision.',
            ]
        );
    }

    public function saveReportReviewDraft(Request $request, ActivityReport $report): JsonResponse
    {
        $this->authorizeAdmin($request);

        return $this->saveModuleDraft($request, $report, $this->reportReviewSchema(), 'admin_field_reviews', 'admin_section_reviews');
    }

    public function updateReportStatus(Request $request, ActivityReport $report): RedirectResponse
    {
        $this->authorizeAdmin($request);

        return $this->finalizeModuleReview(
            $request,
            $report,
            $this->reportReviewSchema(),
            'admin_field_reviews',
            'admin_section_reviews',
            'admin_review_remarks',
            route('admin.reports.show', $report),
            route('admin.reports.index'),
            'Back to After Activity Reports',
            [
                'approved_title' => 'After activity report has been approved',
                'approved_body' => 'The after activity report submission has been approved successfully.',
                'revision_title' => 'Revision request sent',
                'revision_body' => 'The after activity report submission has been returned for revision.',
            ]
        );
    }

    private function saveModuleDraft(Request $request, Model $record, array $schema, string $fieldColumn, string $sectionColumn): JsonResponse
    {
        $validated = $request->validate(['field_review' => ['nullable', 'array']]);
        /** @var User $admin */
        $admin = $request->user();
        $storedFieldReviews = $record->getAttribute($fieldColumn);
        $mergedFieldReviewInput = $this->mergeFieldReviewInputWithStored(
            is_array($validated['field_review'] ?? null) ? $validated['field_review'] : [],
            is_array($storedFieldReviews) ? $storedFieldReviews : [],
            $schema
        );
        [$fieldReviews, $sectionReviews] = $this->normalizeModuleFieldReviews(
            $mergedFieldReviewInput,
            $schema,
            $admin
        );
        $record->update([$fieldColumn => $fieldReviews, $sectionColumn => $sectionReviews]);

        return response()->json(['ok' => true, 'field_reviews' => $fieldReviews, 'section_reviews' => $sectionReviews]);
    }

    /**
     * Load polymorphic field-update audit rows for $reviewable and merge them
     * into the shape the admin module-show view expects. Same intent as the
     * Renewal/Registration flow that uses OrganizationRevisionFieldUpdate, but
     * works for any model via the polymorphic ReviewableUpdateRecorder.
     *
     * Returns:
     *   [
     *     $effectiveFieldReviews,  // raw JSON with re-pended states for any flagged-then-resubmitted field
     *     $fieldUpdateDiffs        // section_key => field_key => {is_updated, old_value, new_value, ...}
     *   ]
     *
     * @return array{0: array<string, mixed>, 1: array<string, array<string, array<string, mixed>>>}
     */
    private function loadModuleFieldUpdateContext(Model $reviewable, string $fieldColumn): array
    {
        $stored = $reviewable->getAttribute($fieldColumn);
        $stored = is_array($stored) ? $stored : [];

        $recorder = app(ReviewableUpdateRecorder::class);
        $latest = $recorder->latestForReviewable($reviewable);
        if ($latest->isNotEmpty()) {
            $latest->loadMissing(['resubmittedBy:id,first_name,last_name']);
        }
        $diffs = $recorder->diffMapFromLatestUpdates($latest);
        foreach ($latest as $row) {
            $sk = (string) $row->section_key;
            $fk = (string) $row->field_key;
            if (! isset($diffs[$sk][$fk])) {
                continue;
            }
            $diffs[$sk][$fk]['section_key'] = $sk;
            $diffs[$sk][$fk]['field_key'] = $fk;
            $diffs[$sk][$fk]['resubmitted_by'] = $row->resubmittedBy?->full_name ?? 'Unknown';
        }
        $effective = $this->resetUpdatedFieldReviewStates($stored, $diffs);

        return [$effective, $diffs];
    }

    /**
     * @param  array{approved_title: string, approved_body: string, revision_title: string, revision_body: string}  $outcomeCopy
     * @return array<string, string>
     */
    private function moduleReviewOutcomeReviewBlock(string $outcome, string $backUrl, string $backLabel, array $outcomeCopy): array
    {
        if ($outcome === 'revision') {
            return [
                'review_block_type' => 'warning',
                'review_block_title' => $outcomeCopy['revision_title'],
                'review_block_message' => $outcomeCopy['revision_body'],
                'review_block_back_url' => $backUrl,
                'review_block_back_label' => $backLabel,
            ];
        }

        return [
            'review_block_type' => 'success',
            'review_block_title' => $outcomeCopy['approved_title'],
            'review_block_message' => $outcomeCopy['approved_body'],
            'review_block_back_url' => $backUrl,
            'review_block_back_label' => $backLabel,
        ];
    }

    private function finalizeModuleReview(Request $request, Model $record, array $schema, string $fieldColumn, string $sectionColumn, string $remarksColumn, string $showRoute, string $backRoute, string $backLabel, array $outcomeCopy): RedirectResponse
    {
        $validated = $request->validate([
            'field_review' => ['nullable', 'array'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var User $admin */
        $admin = $request->user();
        [$fieldReviews, $sectionReviews, $hasPending, $hasMissingNotes] = $this->normalizeModuleFieldReviews(
            is_array($validated['field_review'] ?? null) ? $validated['field_review'] : [],
            $schema,
            $admin
        );
        if ($hasPending || $hasMissingNotes) {
            return back()->withErrors(['field_review' => 'Review all fields and provide notes for every revision-marked field before finalizing.'])->withInput();
        }
        $allVerified = collect($sectionReviews)->every(fn (array $row): bool => ($row['status'] ?? 'pending') === 'verified');
        $nextStatus = $allVerified ? 'approved' : 'revision';
        $remarks = trim((string) ($validated['remarks'] ?? ''));
        $notes = $this->composeGenericFlaggedNotes($fieldReviews);
        $payload = [
            'status' => $nextStatus,
            $fieldColumn => $fieldReviews,
            $sectionColumn => $sectionReviews,
            $remarksColumn => $remarks !== '' ? $remarks : null,
        ];
        if ($record instanceof OrganizationSubmission) {
            $payload['notes'] = $nextStatus === 'revision'
                ? ($notes ?? ($remarks !== '' ? $remarks : $record->notes))
                : ($remarks !== '' ? $remarks : $record->notes);
            $payload['approval_decision'] = $nextStatus === 'approved' ? 'approved' : null;
        }
        $record->update($payload);

        /*
         * Auto-acknowledge any officer field updates whose matching review row
         * just left `pending`. Mirrors
         * `autoAcknowledgeReviewedRegistrationFieldUpdates` for the renewal
         * flow but works polymorphically for Calendar / Proposal / Report via
         * ReviewableUpdateRecorder.
         */
        app(ReviewableUpdateRecorder::class)->acknowledgeReviewedFields(
            $record,
            $fieldReviews,
            (int) $request->user()->id
        );

        $this->notifyModuleReviewResult($record, $nextStatus);

        $reviewFlash = $this->moduleReviewOutcomeReviewBlock(
            $nextStatus === 'approved' ? 'approved' : 'revision',
            $backRoute,
            $backLabel,
            $outcomeCopy
        );

        return redirect()
            ->to($showRoute)
            ->with($reviewFlash);
    }

    private function normalizeModuleFieldReviews(array $fieldReviewInput, array $schema, User $admin): array
    {
        $normalizedFieldReviews = [];
        $normalizedSectionReviews = [];
        $hasPending = false;
        $hasMissingNotes = false;
        foreach ($schema as $sectionKey => $fields) {
            $sectionInput = is_array($fieldReviewInput[$sectionKey] ?? null) ? $fieldReviewInput[$sectionKey] : [];
            $fieldStates = [];
            $sectionPending = false;
            $sectionFlagged = false;
            foreach ($fields as $fieldKey => $fieldLabel) {
                $row = is_array($sectionInput[$fieldKey] ?? null) ? $sectionInput[$fieldKey] : [];
                $status = (string) ($row['status'] ?? 'pending');
                if (in_array($status, ['revision', 'needs_revision'], true)) {
                    $status = 'flagged';
                }
                if (! in_array($status, ['pending', 'passed', 'flagged'], true)) {
                    $status = 'pending';
                }
                $note = trim((string) ($row['note'] ?? ''));
                if ($status !== 'flagged') {
                    $note = '';
                }
                if ($status === 'pending') {
                    $sectionPending = true;
                }
                if ($status === 'flagged') {
                    $sectionFlagged = true;
                    if ($note === '') {
                        $hasMissingNotes = true;
                    }
                }
                $fieldStates[$fieldKey] = [
                    'label' => $fieldLabel,
                    'status' => $status,
                    'note' => $note !== '' ? $note : null,
                    'reviewed_by' => $status === 'pending' ? null : $admin->id,
                    'reviewed_at' => $status === 'pending' ? null : now()->toDateTimeString(),
                ];
            }
            if ($sectionPending) {
                $hasPending = true;
            }
            $normalizedFieldReviews[$sectionKey] = $fieldStates;
            $normalizedSectionReviews[$sectionKey] = [
                'status' => $sectionPending ? 'pending' : ($sectionFlagged ? 'needs_revision' : 'verified'),
                'submitted' => ! $sectionPending && ! collect($fieldStates)->contains(fn (array $row): bool => ($row['status'] ?? '') === 'flagged' && trim((string) ($row['note'] ?? '')) === ''),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->toDateTimeString(),
            ];
        }

        return [$normalizedFieldReviews, $normalizedSectionReviews, $hasPending, $hasMissingNotes];
    }

    /**
     * Merge draft payload with persisted field reviews so partial draft saves
     * never reset untouched fields back to pending.
     *
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $stored
     * @param  array<string, array<string, string>>  $schema
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function mergeFieldReviewInputWithStored(array $incoming, array $stored, array $schema): array
    {
        $merged = [];
        foreach ($schema as $sectionKey => $fields) {
            $incomingSection = is_array($incoming[$sectionKey] ?? null) ? $incoming[$sectionKey] : [];
            $storedSection = is_array($stored[$sectionKey] ?? null) ? $stored[$sectionKey] : [];
            $merged[$sectionKey] = [];
            foreach ($fields as $fieldKey => $_fieldLabel) {
                $incomingRow = is_array($incomingSection[$fieldKey] ?? null) ? $incomingSection[$fieldKey] : null;
                $storedRow = is_array($storedSection[$fieldKey] ?? null) ? $storedSection[$fieldKey] : [];
                if ($incomingRow !== null) {
                    $merged[$sectionKey][$fieldKey] = $incomingRow;
                } else {
                    $merged[$sectionKey][$fieldKey] = [
                        'status' => $storedRow['status'] ?? 'pending',
                        'note' => $storedRow['note'] ?? '',
                    ];
                }
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $fieldReviews
     * @param  array<string, mixed>  $updatesByField
     * @return array<string, mixed>
     */
    private function resetUpdatedFieldReviewStates(array $fieldReviews, array $updatesByField): array
    {
        foreach ($updatesByField as $sectionKey => $fields) {
            if (! is_array($fields)) {
                continue;
            }
            foreach ($fields as $fieldKey => $update) {
                if (! ((bool) data_get($update, 'is_updated', false))) {
                    continue;
                }
                if (! isset($fieldReviews[$sectionKey]) || ! is_array($fieldReviews[$sectionKey])) {
                    $fieldReviews[$sectionKey] = [];
                }
                $label = (string) data_get($fieldReviews, $sectionKey.'.'.$fieldKey.'.label', ucwords(str_replace('_', ' ', (string) $fieldKey)));
                $fieldReviews[$sectionKey][$fieldKey] = [
                    'label' => $label,
                    'status' => 'pending',
                    'note' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ];
            }
        }

        return $fieldReviews;
    }

    /**
     * @param  array<string, mixed>  $fieldReviews
     */
    private function autoAcknowledgeReviewedRegistrationFieldUpdates(OrganizationSubmission $submission, array $fieldReviews, int $adminId): void
    {
        $pendingUpdates = OrganizationRevisionFieldUpdate::query()
            ->where('organization_submission_id', $submission->id)
            ->whereNull('acknowledged_at')
            ->get();
        foreach ($pendingUpdates as $update) {
            $sectionKey = (string) ($update->section_key ?? '');
            $fieldKey = (string) ($update->field_key ?? '');
            if ($this->isNonReviewableApplicationRegistrationField($sectionKey, $fieldKey)) {
                $update->update([
                    'acknowledged_at' => now(),
                    'acknowledged_by' => $adminId,
                ]);

                continue;
            }
            if ($this->isNonReviewableOrganizationalRegistrationField($sectionKey, $fieldKey)) {
                $update->update([
                    'acknowledged_at' => now(),
                    'acknowledged_by' => $adminId,
                ]);

                continue;
            }
            $status = (string) data_get($fieldReviews, $sectionKey.'.'.$fieldKey.'.status', 'pending');
            if ($status === 'pending') {
                continue;
            }
            $update->update([
                'acknowledged_at' => now(),
                'acknowledged_by' => $adminId,
            ]);
        }
    }

    private function composeGenericFlaggedNotes(array $fieldReviews): ?string
    {
        $blocks = [];
        foreach ($fieldReviews as $sectionKey => $fields) {
            $items = [];
            foreach ($fields as $field) {
                if (($field['status'] ?? '') !== 'flagged') {
                    continue;
                }
                $note = trim((string) ($field['note'] ?? ''));
                if ($note !== '') {
                    $items[] = '- '.((string) ($field['label'] ?? 'Field')).': '.$note;
                }
            }
            if ($items !== []) {
                $blocks[] = ucwords(str_replace('_', ' ', (string) $sectionKey))."\n".implode("\n", $items);
            }
        }

        return $blocks === [] ? null : implode("\n\n—\n\n", $blocks);
    }

    private function renewalReviewSchema(): array
    {
        return [
            'overview' => [
                'organization' => 'Organization',
                'submitted_by' => 'Submitted By',
                'academic_year' => 'Academic Year',
                'submission_date' => 'Submission Date',
            ],
            'contact' => [
                'contact_person' => 'Contact Person',
                'adviser_name' => 'Adviser Name',
                'contact_no' => 'Contact Number',
                'contact_email' => 'Contact Email',
            ],
            'adviser' => [
                'adviser_full_name' => 'Full Name',
                'adviser_school_id' => 'School ID',
                'adviser_email' => 'Email',
                'adviser_status' => 'Status',
            ],
            'requirements' => [
                'letter_of_intent' => 'Letter of Intent',
                'application_form' => 'Application Form',
                'by_laws_updated_if_applicable' => 'By-laws, if applicable',
                'updated_list_of_officers_founders_ay' => 'Updated List of Officers / Founders',
                'dean_endorsement_faculty_adviser' => 'Dean Endorsement / Faculty Adviser',
                'proposed_projects_budget' => 'Proposed Projects and Budget',
                'past_projects' => 'Past Projects',
                'financial_statement_previous_ay' => 'Financial Statement (Previous AY)',
                'evaluation_summary_past_projects' => 'Evaluation Summary (Past Projects)',
                'others' => 'Others',
            ],
        ];
    }

    private function renewalReviewSections(OrganizationSubmission $submission): array
    {
        $adviserNomination = $this->submissionAdviserNomination($submission);
        $requirementRows = $submission->requirements->keyBy('requirement_key');
        $sections = [
            ['key' => 'overview', 'title' => 'Submission Overview', 'subtitle' => 'Renewal context and submitter details.', 'fields' => []],
            ['key' => 'contact', 'title' => 'Contact Details', 'subtitle' => 'Primary point-of-contact for this renewal.', 'fields' => []],
            ['key' => 'adviser', 'title' => 'Adviser Information', 'subtitle' => 'Faculty adviser nomination linked to this submission.', 'fields' => []],
            ['key' => 'requirements', 'title' => 'Requirements Attached', 'subtitle' => 'Checklist and uploaded files as declared on the application.', 'fields' => []],
        ];
        $sections[0]['fields'] = [
            ['key' => 'organization', 'label' => 'Organization', 'value' => $submission->organization?->organization_name ?? 'N/A'],
            ['key' => 'submitted_by', 'label' => 'Submitted By', 'value' => $submission->submittedBy?->full_name ?? 'N/A'],
            ['key' => 'academic_year', 'label' => 'Academic Year', 'value' => $submission->academicTerm?->academic_year ?? 'N/A'],
            ['key' => 'submission_date', 'label' => 'Submission Date', 'value' => optional($submission->submission_date)->format('M d, Y') ?? 'N/A'],
        ];
        $sections[1]['fields'] = [
            ['key' => 'contact_person', 'label' => 'Contact Person', 'value' => $submission->contact_person ?? 'N/A'],
            ['key' => 'adviser_name', 'label' => 'Adviser Name', 'value' => $submission->adviser_name ?? 'N/A'],
            ['key' => 'contact_no', 'label' => 'Contact Number', 'value' => $submission->contact_no ?? 'N/A'],
            ['key' => 'contact_email', 'label' => 'Contact Email', 'value' => $submission->contact_email ?? 'N/A', 'wide' => true],
        ];
        $sections[2]['fields'] = [
            ['key' => 'adviser_full_name', 'label' => 'Full Name', 'value' => $adviserNomination?->user?->full_name ?? 'N/A'],
            ['key' => 'adviser_school_id', 'label' => 'School ID', 'value' => (string) ($adviserNomination?->user?->school_id ?? 'N/A')],
            ['key' => 'adviser_email', 'label' => 'Email', 'value' => (string) ($adviserNomination?->user?->email ?? 'N/A')],
            ['key' => 'adviser_status', 'label' => 'Status', 'value' => $adviserNomination ? ucfirst((string) $adviserNomination->status) : 'No nomination'],
        ];
        foreach (self::RENEWAL_REQUIREMENT_FILE_KEYS as $key) {
            $attachment = $submission->attachments()->where('file_type', Attachment::TYPE_RENEWAL_REQUIREMENT.':'.$key)->latest('id')->first();
            $requirement = $requirementRows->get($key);
            $checked = (bool) ($requirement?->is_submitted ?? false);
            $extension = strtoupper((string) pathinfo((string) ($attachment?->original_name ?: $attachment?->stored_path ?: ''), PATHINFO_EXTENSION));
            $badgeLabel = in_array($extension, ['PDF', 'DOCX', 'PNG', 'JPG', 'JPEG'], true) ? $extension : 'FILE';
            $badgeClass = match ($badgeLabel) {
                'PDF' => 'border-red-200 bg-red-50 text-red-700',
                'DOCX' => 'border-blue-200 bg-blue-50 text-blue-700',
                'PNG' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                'JPG', 'JPEG' => 'border-amber-200 bg-amber-50 text-amber-700',
                default => 'border-slate-200 bg-slate-100 text-slate-700',
            };
            $sections[3]['fields'][] = [
                'key' => $key,
                'label' => (string) data_get($this->renewalReviewSchema(), 'requirements.'.$key, ucwords(str_replace('_', ' ', $key))),
                'value' => $attachment ? 'File uploaded' : 'Not uploaded',
                'action' => $attachment ? ['href' => route('admin.renewals.requirement-file', ['submission' => $submission, 'key' => $key])] : null,
                'download_action' => $attachment ? ['href' => route('admin.renewals.requirement-file.download', ['submission' => $submission, 'key' => $key])] : null,
                'submitted' => $checked,
                'file_badge_label' => $badgeLabel,
                'file_badge_class' => $badgeClass,
                'wide' => true,
            ];
        }

        return $sections;
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $fieldUpdateDiffs
     * @return list<array{label: string, fields: list<array{label: string, anchor: string}>}>
     */
    private function activityCalendarUpdatedInformationGroups(array $fieldUpdateDiffs, ActivityCalendar $calendar): array
    {
        $bySection = [];
        foreach ($fieldUpdateDiffs as $sectionKey => $fields) {
            if (! is_array($fields) || ! str_starts_with((string) $sectionKey, 'entry_')) {
                continue;
            }
            $items = [];
            foreach ($fields as $fieldKey => $update) {
                if (! ((bool) data_get($update, 'is_updated', false))) {
                    continue;
                }
                $anchor = $this->activityCalendarReviewFieldAnchor((string) $fieldKey);
                if ($anchor === '') {
                    continue;
                }
                $items[] = [
                    'label' => $this->activityCalendarReviewFieldLabel((string) $fieldKey),
                    'anchor' => $anchor,
                ];
            }
            if ($items !== []) {
                $bySection[(string) $sectionKey] = $items;
            }
        }
        $groups = [];
        foreach ($calendar->entries as $index => $entry) {
            $sk = 'entry_'.$entry->id;
            if (! isset($bySection[$sk])) {
                continue;
            }
            $num = $index + 1;
            $actLabel = strtoupper(trim((string) ($entry->activity_name ?? '')));
            if ($actLabel === '') {
                $actLabel = 'ACTIVITY';
            }
            $groups[] = [
                'label' => 'ACTIVITY '.$num.': '.$actLabel,
                'fields' => $bySection[$sk],
            ];
        }

        return $groups;
    }

    private function activityCalendarReviewFieldAnchor(string $fieldKey): string
    {
        if (! preg_match('/^entry_(\d+)_(.+)$/', $fieldKey, $m)) {
            return '';
        }
        $suffix = match ($m[2]) {
            'name' => 'activity_name',
            'date' => 'date',
            'venue' => 'venue',
            'sdg' => 'sdgs',
            'participants' => 'participants',
            'budget' => 'budget',
            'program' => 'program',
            default => $m[2],
        };

        return 'activity-calendar-entry-'.$m[1].'-'.$suffix;
    }

    private function activityCalendarReviewFieldLabel(string $fieldKey): string
    {
        if (! preg_match('/^entry_\d+_(.+)$/', $fieldKey, $m)) {
            return ucwords(str_replace('_', ' ', $fieldKey));
        }

        return match ($m[1]) {
            'name' => 'Activity Name',
            'date' => 'Date',
            'venue' => 'Venue',
            'sdg' => 'Target SDG',
            'participants' => 'Participants',
            'budget' => 'Estimated Budget',
            'program' => 'Program',
            default => ucwords(str_replace('_', ' ', $m[1])),
        };
    }

    private function calendarReviewSchema(ActivityCalendar $calendar): array
    {
        $calendarFilePath = trim((string) ($calendar->calendar_file ?? ''));
        $schema = [
        ];
        if ($calendarFilePath !== '') {
            $schema['submitted_files'] = [
                'calendar_file' => 'Calendar file',
            ];
        }

        foreach ($calendar->entries as $entry) {
            $entryPrefix = 'entry_'.$entry->id;
            $entrySchema = [
                $entryPrefix.'_name' => 'Activity Name',
                $entryPrefix.'_date' => 'Date',
                $entryPrefix.'_venue' => 'Venue',
                $entryPrefix.'_sdg' => 'Target SDG',
                $entryPrefix.'_participants' => 'Participants',
                $entryPrefix.'_budget' => 'Estimated Budget',
            ];
            if (trim((string) ($entry->target_program ?? '')) !== '') {
                $entrySchema[$entryPrefix.'_program'] = 'Program';
            }
            $schema[$entryPrefix] = $entrySchema;
        }

        return $schema;
    }

    private function calendarReviewSections(ActivityCalendar $calendar): array
    {
        $termLabel = $this->termDisplayLabel(
            (string) ($calendar->academicTerm?->semester ?? ''),
            SystemSetting::activeSemester()
        );
        $academicYearValue = (string) ($calendar->academicTerm?->academic_year ?? '');
        if ($academicYearValue === '') {
            $academicYearValue = (string) SystemSetting::activeAcademicYear();
        }
        $organizationFormValue = trim((string) ($calendar->submitted_organization_name ?? ''));
        if ($organizationFormValue === '') {
            $organizationFormValue = (string) ($calendar->organization?->organization_name ?? '');
        }
        $calendarFilePath = trim((string) ($calendar->calendar_file ?? ''));

        $sections = [
            ['key' => 'overview', 'title' => 'Calendar Overview', 'subtitle' => 'Core activity calendar submission details.', 'reviewable' => false, 'fields' => [
                ['key' => 'organization_profile', 'label' => 'Organization (profile)', 'value' => $calendar->organization?->organization_name ?? '—'],
                ['key' => 'organization_form', 'label' => 'RSO Name (form)', 'value' => $organizationFormValue !== '' ? $organizationFormValue : '—'],
                ['key' => 'submitted_by', 'label' => 'Submitted By', 'value' => $calendar->submittedBy?->full_name ?? '—'],
                ['key' => 'academic_year', 'label' => 'Academic Year', 'value' => $academicYearValue !== '' ? $academicYearValue : '—'],
                ['key' => 'term', 'label' => 'Term', 'value' => $termLabel],
                ['key' => 'submission_date', 'label' => 'Submission Date', 'value' => optional($calendar->submission_date)->format('M d, Y') ?? '—'],
            ]],
        ];
        if ($calendarFilePath !== '') {
            $calBadge = $this->fileBadgeFromStoredPath($calendarFilePath);
            $sections[] = [
                'key' => 'submitted_files',
                'title' => 'Requirements Attached',
                'subtitle' => 'Checklist and uploaded files as declared on the application.',
                'fields' => [[
                    'key' => 'calendar_file',
                    'label' => 'Calendar file',
                    'value' => 'File uploaded',
                    'submitted' => true,
                    'file_badge_label' => $calBadge['label'],
                    'file_badge_class' => $calBadge['class'],
                    'action' => ['href' => route('admin.calendars.file', $calendar)],
                    'download_action' => ['href' => route('admin.calendars.file.download', $calendar)],
                    'wide' => true,
                ]],
            ];
        }

        foreach ($calendar->entries->values() as $index => $entry) {
            $entryPrefix = 'entry_'.$entry->id;
            $entryFields = [
                ['key' => $entryPrefix.'_name', 'label' => 'Activity Name', 'value' => $entry->activity_name ?? '—', 'wide' => true],
                ['key' => $entryPrefix.'_date', 'label' => 'Date', 'value' => optional($entry->activity_date)->format('M d, Y') ?? '—'],
                ['key' => $entryPrefix.'_venue', 'label' => 'Venue', 'value' => $entry->venue ?? '—'],
                ['key' => $entryPrefix.'_sdg', 'label' => 'Target SDG', 'value' => $entry->target_sdg ?? '—', 'wide' => true],
                ['key' => $entryPrefix.'_participants', 'label' => 'Participants', 'value' => $entry->target_participants ?? '—', 'wide' => true],
            ];
            $programValue = trim((string) ($entry->target_program ?? ''));
            if ($programValue !== '') {
                $entryFields[] = ['key' => $entryPrefix.'_program', 'label' => 'Program', 'value' => $programValue, 'wide' => true];
            }
            $entryFields[] = [
                'key' => $entryPrefix.'_budget',
                'label' => 'Estimated Budget',
                'value' => $entry->estimated_budget !== null ? number_format((float) $entry->estimated_budget, 2) : '—',
                'wide' => $programValue === '',
            ];

            $sections[] = [
                'key' => $entryPrefix,
                'title' => 'Submitted Activity #'.($index + 1),
                'subtitle' => 'Review all submitted details for this activity item.',
                'fields' => $entryFields,
            ];
        }

        return $sections;
    }

    private function termDisplayLabel(string $semesterValue, string $fallbackSetting = ''): string
    {
        $termLabels = [
            'term_1' => 'Term 1',
            'term_2' => 'Term 2',
            'term_3' => 'Term 3',
            'first' => 'Term 1',
            'second' => 'Term 2',
            'midyear' => 'Term 3',
        ];
        $normalized = strtolower(trim($semesterValue));
        if ($normalized !== '' && isset($termLabels[$normalized])) {
            return $termLabels[$normalized];
        }

        $fallbackNormalized = strtolower(trim($fallbackSetting));
        if ($fallbackNormalized !== '' && isset($termLabels[$fallbackNormalized])) {
            return $termLabels[$fallbackNormalized];
        }

        return $normalized !== '' ? $semesterValue : '—';
    }

    private function proposalReviewSchema(): array
    {
        return [
            'step1_request_form' => [
                'step1_proposal_option' => 'Proposal Option',
                'step1_rso_name' => 'RSO Name',
                'step1_activity_title' => 'Title of Activity',
                'step1_linked_activity_calendar' => 'Linked Activity Calendar',
                'step1_calendar_activity_row' => 'Calendar Activity Row',
                'step1_partner_entities' => 'Partner Entities',
                'step1_nature_of_activity' => 'Nature of Activity',
                'step1_type_of_activity' => 'Type of Activity',
                'step1_target_sdg' => 'Target SDG',
                'step1_proposed_budget' => 'Step 1 Proposed Budget',
                'step1_budget_source' => 'Step 1 Budget Source',
                'step1_activity_date' => 'Date of Activity',
                'step1_venue' => 'Venue',
                'step1_request_letter' => 'Upload Request Letter',
                'step1_speaker_resume' => 'Resume of Speaker',
                'step1_post_survey_form' => 'Sample Post-Survey Form',
            ],
            'step2_submission' => [
                'step2_organization_logo' => 'Organization Logo',
                'step2_organization' => 'Organization (Form)',
                'step2_academic_year' => 'Academic Year',
                'step2_department' => 'Department',
                'step2_program' => 'Program',
                'step2_activity_title' => 'Project / Activity Title',
                'step2_proposed_dates' => 'Proposed Dates',
                'step2_proposed_time' => 'Proposed Time',
                'step2_venue' => 'Venue',
                'step2_overall_goal' => 'Overall Goal',
                'step2_specific_objectives' => 'Specific Objectives',
                'step2_criteria_mechanics' => 'Criteria / Mechanics',
                'step2_program_flow' => 'Program Flow',
                'step2_budget_total' => 'Proposed Budget (Total)',
                'step2_source_of_funding' => 'Source of Funding',
                'step2_budget_table' => 'Detailed Budget Table',
                'step2_external_funding_support' => 'External Funding Support',
            ],
            'additional' => [
                'step2_resume_resource_persons' => 'Resume of Resource Person/s',
            ],
        ];
    }

    private function proposalReviewSections(array $step1Rows, array $step2Rows, array $requirementsAttachmentFields): array
    {
        $sections = [
            [
                'key' => 'step1_request_form',
                'title' => 'Step 1: Activity Request Form',
                'subtitle' => 'Officer-submitted request form fields used to promote this proposal.',
                'fields' => $step1Rows,
            ],
            [
                'key' => 'step2_submission',
                'title' => 'Step 2: Proposal Submission',
                'subtitle' => 'Complete proposal content submitted by the officer.',
                'fields' => $step2Rows,
            ],
        ];
        if ($requirementsAttachmentFields !== []) {
            $sections[] = [
                'key' => 'requirements_attached',
                'title' => 'Requirements Attached',
                'subtitle' => 'Checklist and uploaded files as declared on the application.',
                'reviewable' => false,
                'is_requirements_attached' => true,
                'fields' => $requirementsAttachmentFields,
            ];
        }

        return $sections;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function proposalRequirementsAttachmentFields(ActivityProposal $proposal, ?ActivityRequestForm $requestForm, bool $isExternalFunding): array
    {
        $rows = [];
        foreach ([
            ['file_key' => 'request_letter', 'review_section_key' => 'step1_request_form', 'review_field_key' => 'step1_request_letter', 'label' => 'Upload Request Letter'],
            ['file_key' => 'speaker_resume', 'review_section_key' => 'step1_request_form', 'review_field_key' => 'step1_speaker_resume', 'label' => 'Resume of Speaker'],
            ['file_key' => 'post_survey_form', 'review_section_key' => 'step1_request_form', 'review_field_key' => 'step1_post_survey_form', 'label' => 'Sample Post-Survey Form'],
            ['file_key' => 'organization_logo', 'review_section_key' => 'step2_submission', 'review_field_key' => 'step2_organization_logo', 'label' => 'Organization Logo'],
        ] as $def) {
            $path = $this->proposalReviewFilePathByKey($proposal, $requestForm, $def['file_key']);
            $badge = $this->fileBadgeFromStoredPath($path);
            $rows[] = [
                'key' => $def['review_field_key'],
                'label' => $def['label'],
                'review_section_key' => $def['review_section_key'],
                'review_field_key' => $def['review_field_key'],
                'submitted' => $path !== null,
                'file_badge_label' => $badge['label'],
                'file_badge_class' => $badge['class'],
                'action' => $path !== null ? ['href' => route('admin.proposals.file', ['proposal' => $proposal, 'key' => $def['file_key']])] : null,
                'download_action' => $path !== null ? ['href' => route('admin.proposals.file.download', ['proposal' => $proposal, 'key' => $def['file_key']])] : null,
                'show_review_controls' => true,
            ];
        }
        if ($isExternalFunding) {
            $path = $this->proposalReviewFilePathByKey($proposal, $requestForm, 'external_funding');
            $badge = $this->fileBadgeFromStoredPath($path);
            $rows[] = [
                'key' => 'step2_external_funding_support',
                'label' => 'External Funding Support',
                'review_section_key' => 'step2_submission',
                'review_field_key' => 'step2_external_funding_support',
                'submitted' => $path !== null,
                'file_badge_label' => $badge['label'],
                'file_badge_class' => $badge['class'],
                'action' => $path !== null ? ['href' => route('admin.proposals.file', ['proposal' => $proposal, 'key' => 'external_funding'])] : null,
                'download_action' => $path !== null ? ['href' => route('admin.proposals.file.download', ['proposal' => $proposal, 'key' => 'external_funding'])] : null,
                'show_review_controls' => true,
            ];
        }
        $resPath = $this->proposalReviewFilePathByKey($proposal, $requestForm, 'resource_resume');
        $resBadge = $this->fileBadgeFromStoredPath($resPath);
        $rows[] = [
            'key' => 'step2_resume_resource_persons',
            'label' => 'Resume of Resource Person/s',
            'review_section_key' => 'additional',
            'review_field_key' => 'step2_resume_resource_persons',
            'submitted' => $resPath !== null,
            'file_badge_label' => $resBadge['label'],
            'file_badge_class' => $resBadge['class'],
            'action' => $resPath !== null ? ['href' => route('admin.proposals.file', ['proposal' => $proposal, 'key' => 'resource_resume'])] : null,
            'download_action' => $resPath !== null ? ['href' => route('admin.proposals.file.download', ['proposal' => $proposal, 'key' => 'resource_resume'])] : null,
            'show_review_controls' => true,
        ];

        return $rows;
    }

    /**
     * @return array{label: string, class: string}
     */
    private function fileBadgeFromStoredPath(?string $storedPath): array
    {
        $extension = strtoupper((string) pathinfo((string) ($storedPath ?? ''), PATHINFO_EXTENSION));
        $badgeLabel = in_array($extension, ['PDF', 'DOCX', 'PNG', 'JPG', 'JPEG'], true) ? $extension : 'FILE';
        $badgeClass = match ($badgeLabel) {
            'PDF' => 'border-red-200 bg-red-50 text-red-700',
            'DOCX' => 'border-blue-200 bg-blue-50 text-blue-700',
            'PNG' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'JPG', 'JPEG' => 'border-amber-200 bg-amber-50 text-amber-700',
            default => 'border-slate-200 bg-slate-100 text-slate-700',
        };

        return ['label' => $badgeLabel, 'class' => $badgeClass];
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
                ->with('attachments')
                ->latest('promoted_at')
                ->latest('id')
                ->first();
            if ($hit) {
                return $hit;
            }
        }

        return (clone $base)
            ->where('activity_title', (string) ($proposal->activity_title ?? ''))
            ->with('attachments')
            ->latest('promoted_at')
            ->latest('id')
            ->first();
    }

    /**
     * Supabase signed download URL when the object exists there; otherwise
     * streams from the public disk — mirrors the registration renewal
     * download intent without changing officer-facing routes.
     */
    private function redirectAttachmentDownload(Attachment $attachment): Response
    {
        $relativePath = trim((string) $attachment->stored_path);
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            abort(404);
        }

        $safeFilename = str_replace(['"', "\r", "\n"], '', (string) ($attachment->original_name ?: basename($relativePath)));
        $safeFilename = (string) preg_replace('/[\x00-\x1F\\\\]/u', '', $safeFilename);
        if ($safeFilename === '') {
            $safeFilename = basename($relativePath);
        }

        $resolvedMime = ($attachment->mime_type !== null && trim((string) $attachment->mime_type) !== '')
            ? trim((string) $attachment->mime_type)
            : 'application/octet-stream';

        $disk = Storage::disk('supabase');
        try {
            if ($disk->exists($relativePath)) {
                $temporaryUrl = $disk->temporaryUrl(
                    $relativePath,
                    now()->addMinutes(15),
                    [
                        'ResponseContentDisposition' => 'attachment; filename="'.$safeFilename.'"',
                        'ResponseContentType' => $resolvedMime,
                    ]
                );

                return redirect()->away($temporaryUrl);
            }
        } catch (\Throwable $e) {
            Log::warning('Admin: attachment download temporaryUrl failed.', [
                'attachment_id' => $attachment->id,
                'stored_path' => $relativePath,
                'error' => $e->getMessage(),
            ]);
        }

        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($relativePath)) {
            return $publicDisk->download($relativePath, $safeFilename);
        }

        abort(404, 'The file could not be found.');
    }

    /**
     * Path-only download (e.g. activity calendar main file) — tries Supabase
     * first, then public disk.
     */
    private function downloadStoredPathPreferSupabase(string $relativePath, string $safeFilename, ?string $mimeType = null): Response
    {
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            abort(404);
        }

        $safeFilename = (string) preg_replace('/[\x00-\x1F"\\\\]/u', '', $safeFilename);
        if ($safeFilename === '') {
            $safeFilename = basename($relativePath);
        }

        $resolvedMime = ($mimeType !== null && trim($mimeType) !== '') ? trim($mimeType) : 'application/octet-stream';

        $disk = Storage::disk('supabase');
        try {
            if ($disk->exists($relativePath)) {
                $temporaryUrl = $disk->temporaryUrl(
                    $relativePath,
                    now()->addMinutes(15),
                    [
                        'ResponseContentDisposition' => 'attachment; filename="'.$safeFilename.'"',
                        'ResponseContentType' => $resolvedMime,
                    ]
                );

                return redirect()->away($temporaryUrl);
            }
        } catch (\Throwable $e) {
            Log::warning('Admin: stored-path download temporaryUrl failed.', [
                'stored_path' => $relativePath,
                'error' => $e->getMessage(),
            ]);
        }

        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($relativePath)) {
            return $publicDisk->download($relativePath, $safeFilename);
        }

        abort(404, 'The file could not be found.');
    }

    private function proposalAttachmentForReviewKey(ActivityProposal $proposal, ?ActivityRequestForm $requestForm, string $key): ?Attachment
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
                return $attachment;
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
                    return $attachment;
                }
            }
        }

        return null;
    }

    private function proposalReviewFilePathByKey(ActivityProposal $proposal, ?ActivityRequestForm $requestForm, string $key): ?string
    {
        $attachment = $this->proposalAttachmentForReviewKey($proposal, $requestForm, $key);

        return $attachment ? trim((string) $attachment->stored_path) : null;
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

    private function reportReviewSchema(): array
    {
        return [
            'overview' => [
                'organization' => 'Organization',
                'submitted_by' => 'Submitted By',
                'submission_date' => 'Submission Date',
                'event_title' => 'Activity / Event Title',
                'event_datetime' => 'Event Date & Time',
            ],
            'content' => [
                'program' => 'Program',
                'summary' => 'Summary / Description',
                'evaluation' => 'Activity Evaluation',
                'participants_reached' => 'Participants Reached (%)',
            ],
            'files' => [
                'poster_file' => 'Poster file',
                'attendance_file' => 'Attendance file',
            ],
        ];
    }

    private function reportReviewSections(ActivityReport $report, array $details): array
    {
        $sections = [
            ['key' => 'overview', 'title' => 'Submission Overview', 'subtitle' => 'Core after-activity report details.', 'fields' => [
                ['key' => 'organization', 'label' => 'Organization', 'value' => $details['Organization'] ?? 'N/A'],
                ['key' => 'submitted_by', 'label' => 'Submitted By', 'value' => $details['Submitted By'] ?? 'N/A'],
                ['key' => 'submission_date', 'label' => 'Submission Date', 'value' => $details['Submission Date'] ?? 'N/A'],
                ['key' => 'event_title', 'label' => 'Activity / Event Title', 'value' => $details['Activity / Event Title'] ?? 'N/A'],
                ['key' => 'event_datetime', 'label' => 'Event Date & Time', 'value' => $details['Event Date & Time'] ?? 'N/A'],
            ]],
            ['key' => 'content', 'title' => 'Narrative Content', 'subtitle' => 'Submitted report narrative and evaluation.', 'fields' => [
                ['key' => 'program', 'label' => 'Program', 'value' => $details['Program'] ?? 'N/A', 'wide' => true],
                ['key' => 'summary', 'label' => 'Summary / Description', 'value' => $details['Summary / Description'] ?? 'N/A', 'wide' => true],
                ['key' => 'evaluation', 'label' => 'Activity Evaluation', 'value' => $details['Activity Evaluation'] ?? 'N/A', 'wide' => true],
                ['key' => 'participants_reached', 'label' => 'Participants Reached (%)', 'value' => $details['Participants Reached (%)'] ?? 'N/A'],
            ]],
        ];
        $attachmentFields = $this->reportRequirementsAttachmentFields($report);
        if ($attachmentFields !== []) {
            $sections[] = [
                'key' => 'requirements_attached',
                'title' => 'Requirements Attached',
                'subtitle' => 'Checklist and uploaded files as declared on the application.',
                'reviewable' => false,
                'is_requirements_attached' => true,
                'fields' => $attachmentFields,
            ];
        }

        return $sections;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reportRequirementsAttachmentFields(ActivityReport $report): array
    {
        $rows = [];
        $fileUrl = static fn (string $k) => route('admin.reports.file', ['report' => $report, 'key' => $k]);
        $downloadUrl = static fn (string $k) => route('admin.reports.file.download', ['report' => $report, 'key' => $k]);

        $posterPath = $this->reportFilePathForAdmin($report, 'poster');
        $badge = $this->fileBadgeFromStoredPath($posterPath);
        $rows[] = [
            'key' => 'poster_file',
            'review_section_key' => 'files',
            'review_field_key' => 'poster_file',
            'label' => 'Poster image',
            'submitted' => $posterPath !== null,
            'file_badge_label' => $badge['label'],
            'file_badge_class' => $badge['class'],
            'action' => $posterPath !== null ? ['href' => $fileUrl('poster')] : null,
            'download_action' => $posterPath !== null ? ['href' => $downloadUrl('poster')] : null,
            'show_review_controls' => true,
        ];

        foreach ($this->reportSupportingPhotoStreamKeys($report) as $idx => $streamKey) {
            $path = $this->reportFilePathForAdmin($report, $streamKey);
            if ($path === null) {
                continue;
            }
            $pBadge = $this->fileBadgeFromStoredPath($path);
            $rows[] = [
                'key' => 'supporting_photo_'.$idx,
                'label' => 'Supporting photo '.($idx + 1),
                'submitted' => true,
                'file_badge_label' => $pBadge['label'],
                'file_badge_class' => $pBadge['class'],
                'action' => ['href' => $fileUrl($streamKey)],
                'download_action' => ['href' => $downloadUrl($streamKey)],
                'show_review_controls' => false,
            ];
        }

        $certPath = $this->reportFilePathForAdmin($report, 'certificate');
        if ($certPath !== null) {
            $cBadge = $this->fileBadgeFromStoredPath($certPath);
            $rows[] = [
                'key' => 'certificate_sample',
                'label' => 'Certificate sample',
                'submitted' => true,
                'file_badge_label' => $cBadge['label'],
                'file_badge_class' => $cBadge['class'],
                'action' => ['href' => $fileUrl('certificate')],
                'download_action' => ['href' => $downloadUrl('certificate')],
                'show_review_controls' => false,
            ];
        }

        $evalPath = $this->reportFilePathForAdmin($report, 'evaluation_form');
        if ($evalPath !== null) {
            $eBadge = $this->fileBadgeFromStoredPath($evalPath);
            $rows[] = [
                'key' => 'evaluation_form_sample',
                'label' => 'Evaluation form sample',
                'submitted' => true,
                'file_badge_label' => $eBadge['label'],
                'file_badge_class' => $eBadge['class'],
                'action' => ['href' => $fileUrl('evaluation_form')],
                'download_action' => ['href' => $downloadUrl('evaluation_form')],
                'show_review_controls' => false,
            ];
        }

        $attPath = $this->reportFilePathForAdmin($report, 'attendance');
        $aBadge = $this->fileBadgeFromStoredPath($attPath);
        $rows[] = [
            'key' => 'attendance_file',
            'review_section_key' => 'files',
            'review_field_key' => 'attendance_file',
            'label' => 'Attendance sheet',
            'submitted' => $attPath !== null,
            'file_badge_label' => $aBadge['label'],
            'file_badge_class' => $aBadge['class'],
            'action' => $attPath !== null ? ['href' => $fileUrl('attendance')] : null,
            'download_action' => $attPath !== null ? ['href' => $downloadUrl('attendance')] : null,
            'show_review_controls' => true,
        ];

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function reportSupportingPhotoStreamKeys(ActivityReport $report): array
    {
        $keys = [];
        $seen = [];
        foreach ($report->attachments()->orderBy('id')->get() as $attachment) {
            $ft = (string) $attachment->file_type;
            if (preg_match('/^'.preg_quote(Attachment::TYPE_REPORT_SUPPORTING_PHOTO, '/').':(\d+)$/', $ft, $m) === 1) {
                $k = 'supporting_'.$m[1];
                if (! isset($seen[$k]) && is_string($attachment->stored_path) && $attachment->stored_path !== '') {
                    $seen[$k] = true;
                    $keys[] = $k;
                }
            }
        }
        if ($keys !== []) {
            return $keys;
        }
        $legacyPhotos = $report->getAttribute('supporting_photo_paths');
        $legacyPhotos = is_array($legacyPhotos) ? $legacyPhotos : [];
        foreach ($legacyPhotos as $idx => $path) {
            if (is_string($path) && $path !== '') {
                $keys[] = 'supporting_'.(int) $idx;
            }
        }

        return $keys;
    }

    private function reportAttachmentForAdminKey(ActivityReport $report, string $key): ?Attachment
    {
        if (preg_match('/^supporting_(\d+)$/', $key, $m) === 1) {
            $attachment = $report->attachments()
                ->where('file_type', Attachment::TYPE_REPORT_SUPPORTING_PHOTO.':'.((int) $m[1]))
                ->latest('id')
                ->first();
            if ($attachment && is_string($attachment->stored_path) && trim($attachment->stored_path) !== '') {
                return $attachment;
            }

            return null;
        }

        $fileType = match ($key) {
            'poster' => Attachment::TYPE_REPORT_POSTER,
            'certificate' => Attachment::TYPE_REPORT_CERTIFICATE,
            'evaluation_form' => Attachment::TYPE_REPORT_EVALUATION_FORM,
            'attendance' => Attachment::TYPE_REPORT_ATTENDANCE,
            default => null,
        };
        if ($fileType === null) {
            return null;
        }

        $attachment = $report->attachments()
            ->where('file_type', $fileType)
            ->latest('id')
            ->first();
        if ($attachment && is_string($attachment->stored_path) && trim($attachment->stored_path) !== '') {
            return $attachment;
        }

        return null;
    }

    private function reportFilePathForAdmin(ActivityReport $report, string $key): ?string
    {
        $fileType = match ($key) {
            'poster' => Attachment::TYPE_REPORT_POSTER,
            'certificate' => Attachment::TYPE_REPORT_CERTIFICATE,
            'evaluation_form' => Attachment::TYPE_REPORT_EVALUATION_FORM,
            'attendance' => Attachment::TYPE_REPORT_ATTENDANCE,
            default => null,
        };

        if (preg_match('/^supporting_(\d+)$/', $key, $m) === 1) {
            $attachment = $report->attachments()
                ->where('file_type', Attachment::TYPE_REPORT_SUPPORTING_PHOTO.':'.((int) $m[1]))
                ->latest('id')
                ->first();
            if ($attachment && is_string($attachment->stored_path) && $attachment->stored_path !== '') {
                return $attachment->stored_path;
            }
            $legacyPhotos = $report->getAttribute('supporting_photo_paths');
            $legacyPhotos = is_array($legacyPhotos) ? $legacyPhotos : [];

            return $legacyPhotos[(int) $m[1]] ?? null;
        }

        if ($fileType !== null) {
            $attachment = $report->attachments()
                ->where('file_type', $fileType)
                ->latest('id')
                ->first();
            if ($attachment && is_string($attachment->stored_path) && $attachment->stored_path !== '') {
                return $attachment->stored_path;
            }
        }

        return match ($key) {
            'poster' => $report->poster_image_path ?? null,
            'certificate' => $report->certificate_sample_path ?? null,
            'evaluation_form' => $report->evaluation_form_sample_path ?? null,
            'attendance' => $report->attendance_sheet_path ?? null,
            default => null,
        };
    }

    private function authorizeAdmin(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! $user->isSdaoAdmin()) {
            abort(403, 'Only authorized SDAO admins can access this section.');
        }
    }

    private function buildCentralizedCalendarEvents()
    {
        $proposalEvents = ActivityProposal::query()
            ->where('status', '!=', 'draft')
            ->with(['organization', 'submittedBy'])
            ->latest('submission_date')
            ->latest('id')
            ->get()
            ->map(function (ActivityProposal $proposal): array {
                return [
                    'title' => $proposal->activity_title ?? 'Untitled Activity',
                    'start' => optional($proposal->proposed_start_date)?->toDateString(),
                    'end' => optional($proposal->proposed_end_date)?->addDay()->toDateString(),
                    'status' => strtoupper((string) ($proposal->status ?? 'pending')),
                    'organization_name' => $proposal->organization?->organization_name ?? 'N/A',
                    'submitted_by' => $proposal->submittedBy?->full_name ?? 'N/A',
                    'date' => trim(
                        collect([
                            optional($proposal->proposed_start_date)->format('M d, Y'),
                            optional($proposal->proposed_end_date)->format('M d, Y'),
                        ])->filter()->implode(' - ')
                    ) ?: 'N/A',
                    'time' => 'Not specified',
                    'venue' => $proposal->venue ?? 'Not specified',
                    'submission_type' => 'Activity Proposal',
                    'submission_date' => optional($proposal->submission_date)->format('M d, Y') ?? 'N/A',
                    'detail_route' => route('admin.proposals.show', $proposal),
                ];
            });

        return $proposalEvents->values();
    }

    private function attachmentPathOrLegacy(Model $attachable, string $fileType, ?string $legacyPath): ?string
    {
        if (method_exists($attachable, 'attachments')) {
            $attachment = $attachable->attachments()
                ->where('file_type', $fileType)
                ->latest('id')
                ->first();
            if ($attachment && is_string($attachment->stored_path) && $attachment->stored_path !== '') {
                return $attachment->stored_path;
            }
        }

        return $legacyPath;
    }

    private function notificationService(): OrganizationNotificationService
    {
        return app(OrganizationNotificationService::class);
    }

    private function notifyOrganizationOfficers(
        Organization $organization,
        string $title,
        ?string $message,
        string $type = 'info',
        ?string $linkUrl = null,
        ?Model $related = null
    ): void {
        $this->notificationService()->createForOrganization(
            $organization,
            $title,
            $message,
            $type,
            $linkUrl,
            $related
        );
    }

    private function notifyOfficerValidationResult(User $user, string $status): void
    {
        $normalized = strtoupper($status);
        $title = match ($normalized) {
            'APPROVED', 'ACTIVE' => 'Officer Validation Approved',
            'REJECTED' => 'Officer Validation Rejected',
            default => 'Officer Validation Updated',
        };
        $type = match ($normalized) {
            'APPROVED', 'ACTIVE' => 'success',
            'REJECTED' => 'error',
            default => 'info',
        };
        $message = match ($normalized) {
            'APPROVED', 'ACTIVE' => 'Your officer account has been approved by SDAO.',
            'REJECTED' => 'Your officer account was rejected. Please review the validation notes.',
            'REVISION_REQUIRED' => 'SDAO requested additional updates for your officer validation.',
            default => 'Your officer validation status has changed.',
        };

        $this->notificationService()->createForUser(
            $user,
            $title,
            $message,
            $type,
            route('organizations.profile'),
            $user
        );
    }

    private function notifyRegistrationStatusChange(OrganizationSubmission $submission): void
    {
        $status = strtoupper((string) $submission->status);
        $title = match ($status) {
            'APPROVED' => 'Registration Approved',
            'REVISION' => 'Registration Returned for Revision',
            default => 'Registration Review Updated',
        };
        $message = match ($status) {
            'APPROVED' => 'Your organization registration has been approved by SDAO.',
            'REVISION' => 'Your organization registration needs updates and was returned for revision.',
            default => 'Your registration review status has changed.',
        };
        $type = match ($status) {
            'APPROVED' => 'success',
            'REVISION' => 'warning',
            default => 'info',
        };
        $link = route('organizations.submitted-documents.registrations.show', $submission);

        if ($submission->submittedBy) {
            $this->notificationService()->createForUser($submission->submittedBy, $title, $message, $type, $link, $submission);
        }
        if ($submission->organization) {
            $this->notifyOrganizationOfficers($submission->organization, $title, $message, $type, $link, $submission);
        }
    }

    private function notifyModuleReviewResult(Model $record, string $nextStatus): void
    {
        $normalized = strtoupper($nextStatus);
        $type = $normalized === 'APPROVED' ? 'success' : ($normalized === 'REVISION' ? 'warning' : 'info');

        if ($record instanceof OrganizationSubmission) {
            $label = $record->type === OrganizationSubmission::TYPE_RENEWAL ? 'Renewal' : 'Registration';
            $title = $label.' '.($normalized === 'APPROVED' ? 'Approved' : 'Returned for Revision');
            $message = $normalized === 'APPROVED'
                ? "Your {$label} has been approved by SDAO."
                : "Your {$label} needs updates and was returned for revision.";
            $link = $record->type === OrganizationSubmission::TYPE_RENEWAL
                ? route('organizations.submitted-documents.renewals.show', $record)
                : route('organizations.submitted-documents.registrations.show', $record);
            if ($record->submittedBy) {
                $this->notificationService()->createForUser($record->submittedBy, $title, $message, $type, $link, $record);
            }
            if ($record->organization) {
                $this->notifyOrganizationOfficers($record->organization, $title, $message, $type, $link, $record);
            }

            return;
        }

        if ($record instanceof ActivityCalendar) {
            $title = $normalized === 'APPROVED' ? 'Activity Calendar Approved' : 'Activity Calendar Returned for Revision';
            $message = $normalized === 'APPROVED'
                ? 'Your activity calendar has been approved by SDAO.'
                : 'Your activity calendar needs updates and was returned for revision.';
            $link = route('organizations.submitted-documents.calendars.show', $record);
        } elseif ($record instanceof ActivityProposal) {
            $title = $normalized === 'APPROVED' ? 'Activity Proposal Approved' : 'Activity Proposal Returned for Revision';
            $message = $normalized === 'APPROVED'
                ? 'Your activity proposal has been approved by SDAO.'
                : 'Your activity proposal needs updates and was returned for revision.';
            $link = route('organizations.activity-submission.proposals.show', $record);
        } elseif ($record instanceof ActivityReport) {
            $title = $normalized === 'APPROVED' ? 'After-Activity Report Approved' : 'After-Activity Report Returned for Revision';
            $message = $normalized === 'APPROVED'
                ? 'Your after-activity report has been approved by SDAO.'
                : 'Your after-activity report needs updates and was returned for revision.';
            $link = route('organizations.submitted-documents.reports.show', $record);
        } else {
            return;
        }

        if ($record->submittedBy) {
            $this->notificationService()->createForUser($record->submittedBy, $title, $message, $type, $link, $record);
        }
        if ($record->organization) {
            $this->notifyOrganizationOfficers($record->organization, $title, $message, $type, $link, $record);
        }
    }

    private function ensureProposalWorkflow(ActivityProposal $proposal): void
    {
        $existing = $proposal->workflowSteps()->count();
        if ($existing > 0) {
            return;
        }

        $roles = Role::query()
            ->whereNotNull('approval_level')
            ->orderBy('approval_level')
            ->get(['id']);

        $order = 1;
        foreach ($roles as $role) {
            ApprovalWorkflowStep::query()->create([
                'approvable_type' => ActivityProposal::class,
                'approvable_id' => $proposal->id,
                'step_order' => $order,
                'role_id' => $role->id,
                'assigned_to' => null,
                'status' => 'pending',
                'is_current_step' => $order === 1,
                'review_comments' => null,
                'acted_at' => null,
            ]);
            $order++;
        }
    }
}
