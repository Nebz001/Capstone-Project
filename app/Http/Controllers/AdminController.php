<?php

namespace App\Http\Controllers;

use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\ActivityReport;
use App\Models\Organization;
use App\Models\OrganizationRegistration;
use App\Models\OrganizationRenewal;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

    /** Admin per-section review keys (registration show page). */
    private const REGISTRATION_REVIEW_SECTION_KEYS = ['application', 'contact', 'organizational', 'requirements'];

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
    private function initialRegistrationSectionReviewState(OrganizationRegistration $registration): array
    {
        $stored = $registration->section_review_state;
        if (is_array($stored)) {
            $out = [];
            foreach (self::REGISTRATION_REVIEW_SECTION_KEYS as $k) {
                $v = $stored[$k] ?? 'pending';
                $out[$k] = in_array($v, ['validated', 'revision', 'pending'], true) ? $v : 'pending';
            }

            return $out;
        }

        if (strtoupper((string) $registration->registration_status) === 'APPROVED') {
            return array_fill_keys(self::REGISTRATION_REVIEW_SECTION_KEYS, 'validated');
        }

        $out = [];
        foreach ($this->registrationReviewSectionRevisionColumns() as $key => $col) {
            $out[$key] = trim((string) ($registration->{$col} ?? '')) !== '' ? 'revision' : 'pending';
        }

        return $out;
    }

    public function dashboard(Request $request): View
    {
        $this->authorizeAdmin($request);

        $counts = [
            'registrations' => OrganizationRegistration::query()->where('registration_status', 'PENDING')->count(),
            'renewals' => OrganizationRenewal::query()->where('renewal_status', 'PENDING')->count(),
            'calendars' => ActivityCalendar::query()->where('calendar_status', 'PENDING')->count(),
            'proposals' => ActivityProposal::query()->where('proposal_status', 'PENDING')->count(),
            'reports' => ActivityReport::query()->where('report_status', 'PENDING')->count(),
        ];

        $calendarEvents = $this->buildCentralizedCalendarEvents();

        $registeredOrganizations = Organization::query()
            ->orderBy('organization_name')
            ->get(['id', 'organization_name', 'organization_status', 'college_department', 'organization_type']);

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

        $records = OrganizationRegistration::query()
            ->with(['organization', 'user'])
            ->latest('submission_date')
            ->latest('id')
            ->paginate(10);

        return view('admin.review-index', [
            'pageTitle' => 'Registration Review',
            'pageSubtitle' => 'Monitor submitted RSO registration applications.',
            'routeBase' => 'admin.registrations.show',
            'rows' => $records->through(function (OrganizationRegistration $record): array {
                return [
                    'organization' => $record->organization?->organization_name ?? 'N/A',
                    'submitted_by' => $record->user?->full_name ?? 'N/A',
                    'submission_date' => optional($record->submission_date)->format('M d, Y') ?? 'N/A',
                    'status' => $record->registration_status ?? 'PENDING',
                    'id' => $record->id,
                ];
            }),
        ]);
    }

    public function renewals(Request $request): View
    {
        $this->authorizeAdmin($request);

        $records = OrganizationRenewal::query()
            ->with(['organization', 'user'])
            ->latest('submission_date')
            ->latest('id')
            ->paginate(10);

        return view('admin.review-index', [
            'pageTitle' => 'Renewal Review',
            'pageSubtitle' => 'Monitor submitted RSO renewal applications.',
            'routeBase' => 'admin.renewals.show',
            'rows' => $records->through(function (OrganizationRenewal $record): array {
                return [
                    'organization' => $record->organization?->organization_name ?? 'N/A',
                    'submitted_by' => $record->user?->full_name ?? 'N/A',
                    'submission_date' => optional($record->submission_date)->format('M d, Y') ?? 'N/A',
                    'status' => $record->renewal_status ?? 'PENDING',
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

        return view('admin.review-index', [
            'pageTitle' => 'Activity Calendar Review',
            'pageSubtitle' => 'Monitor submitted activity calendars by organization.',
            'routeBase' => 'admin.calendars.show',
            'rows' => $records->through(function (ActivityCalendar $record): array {
                return [
                    'organization' => $record->organization?->organization_name ?? 'N/A',
                    'submitted_by' => 'Organization Submission',
                    'submission_date' => optional($record->submission_date)->format('M d, Y') ?? 'N/A',
                    'status' => $record->calendar_status ?? 'PENDING',
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
                    'submitted_by' => $record->user?->full_name ?? 'N/A',
                    'submission_date' => optional($record->submission_date)->format('M d, Y') ?? 'N/A',
                    'status' => $record->proposal_status ?? 'PENDING',
                    'id' => $record->id,
                ];
            }),
        ]);
    }

    public function reports(Request $request): View
    {
        $this->authorizeAdmin($request);

        $records = ActivityReport::query()
            ->with(['organization', 'user'])
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
                    'submitted_by' => $record->user?->full_name ?? 'N/A',
                    'submission_date' => optional($record->report_submission_date)->format('M d, Y') ?? 'N/A',
                    'status' => $record->report_status ?? 'PENDING',
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
                'organizationOfficers' => fn ($query) => $query
                    ->latest('id')
                    ->with('organization'),
            ])
            ->orderByRaw("CASE role_type WHEN 'ORG_OFFICER' THEN 0 WHEN 'APPROVER' THEN 1 WHEN 'ADMIN' THEN 2 ELSE 3 END")
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
            'organizationOfficers' => fn ($query) => $query->latest('id')->with('organization'),
            'validatedBy',
        ]);

        $latestOfficerRecord = $user->organizationOfficers->first();
        $reviewableFields = $this->accountReviewableFields($user, $latestOfficerRecord);

        return view('admin.user-accounts.show', [
            'account' => $user,
            'latestOfficerRecord' => $latestOfficerRecord,
            'reviewableFields' => $reviewableFields,
            'fieldReviews' => $this->normalizedAccountFieldReviews($user, array_keys($reviewableFields)),
        ]);
    }

    public function updateUserAccountOfficerValidation(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        abort_if($user->isSdaoAdmin(), 404);
        abort_if($user->role_type !== 'ORG_OFFICER', 404);

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

        return redirect()
            ->route('admin.accounts.show', $user)
            ->with('success', 'Officer validation has been updated.');
    }

    public function updateUserAccountFieldReview(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        abort_if($user->isSdaoAdmin(), 404);

        $latestOfficerRecord = $user->organizationOfficers()->latest('id')->with('organization')->first();
        $allowedKeys = array_keys($this->accountReviewableFields($user, $latestOfficerRecord));

        $validated = $request->validate([
            'field_key' => ['required', Rule::in($allowedKeys)],
            'action_type' => ['required', Rule::in(['approve', 'revision'])],
            'revision_message' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['action_type'] === 'revision' && trim((string) ($validated['revision_message'] ?? '')) === '') {
            return redirect()
                ->route('admin.accounts.show', $user)
                ->withErrors(['revision_message' => 'Revision message is required when requesting revision.'])
                ->withInput();
        }

        $fieldKey = (string) $validated['field_key'];
        $actionType = (string) $validated['action_type'];
        $reviews = is_array($user->account_field_reviews) ? $user->account_field_reviews : [];

        $reviews[$fieldKey] = [
            'status' => $actionType === 'approve' ? 'approved' : 'revision',
            'message' => $actionType === 'revision' ? trim((string) $validated['revision_message']) : null,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now()->toIso8601String(),
        ];

        $user->update([
            'account_field_reviews' => $reviews,
        ]);

        return redirect()
            ->route('admin.accounts.show', $user)
            ->with('success', $actionType === 'approve' ? 'Field marked as reviewed.' : 'Field revision feedback saved.');
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

        if ($account->role_type === 'ORG_OFFICER') {
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

    /**
     * @param  list<string>  $allowedKeys
     * @return array<string, array{status: string, message: ?string, reviewed_by: ?int, reviewed_at: ?string}>
     */
    private function normalizedAccountFieldReviews(User $account, array $allowedKeys): array
    {
        $stored = is_array($account->account_field_reviews) ? $account->account_field_reviews : [];
        $normalized = [];

        foreach ($allowedKeys as $key) {
            $raw = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $status = (string) ($raw['status'] ?? '');
            if (! in_array($status, ['approved', 'revision'], true)) {
                $status = 'pending';
            }
            $normalized[$key] = [
                'status' => $status,
                'message' => isset($raw['message']) && is_string($raw['message']) && trim($raw['message']) !== '' ? trim($raw['message']) : null,
                'reviewed_by' => isset($raw['reviewed_by']) ? (int) $raw['reviewed_by'] : null,
                'reviewed_at' => isset($raw['reviewed_at']) && is_string($raw['reviewed_at']) ? $raw['reviewed_at'] : null,
            ];
        }

        return $normalized;
    }

    public function centralizedCalendar(Request $request): View
    {
        $this->authorizeAdmin($request);

        $calendarEvents = $this->buildCentralizedCalendarEvents();

        return view('admin.calendar', compact('calendarEvents'));
    }

    public function showRegistration(Request $request, OrganizationRegistration $registration): View
    {
        $this->authorizeAdmin($request);

        $registration->load(['organization', 'user']);

        return view('admin.registrations.show', [
            'registration' => $registration,
            'initialSectionReviewState' => $this->initialRegistrationSectionReviewState($registration),
        ]);
    }

    /**
     * Stream a registration requirement file from the public disk (auth + path scoped to this org).
     * Avoids relying on the public/storage symlink, which often causes 404s when the link is missing.
     */
    public function showRegistrationRequirementFile(Request $request, OrganizationRegistration $registration, string $key): StreamedResponse
    {
        $this->authorizeAdmin($request);

        if (! in_array($key, self::REGISTRATION_REQUIREMENT_FILE_KEYS, true)) {
            abort(404);
        }

        $files = $registration->requirement_files;
        if (! is_array($files) || empty($files[$key]) || ! is_string($files[$key])) {
            abort(404);
        }

        $relativePath = $files[$key];
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            abort(404);
        }

        $organizationId = (int) $registration->organization_id;
        $expectedPrefix = 'organization-requirements/'.$organizationId.'/registration/';
        if (! str_starts_with($relativePath, $expectedPrefix)) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            abort(404);
        }

        $filename = basename($relativePath);

        return $disk->response($relativePath, $filename, [], 'inline');
    }

    public function updateRegistrationStatus(Request $request, OrganizationRegistration $registration): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $sectionKeys = self::REGISTRATION_REVIEW_SECTION_KEYS;
        $sectionReviewRules = [];
        foreach ($sectionKeys as $k) {
            $sectionReviewRules['section_review.'.$k] = [
                Rule::requiredIf(fn () => $request->input('decision') !== 'REJECTED'),
                Rule::in(['validated', 'revision', 'pending']),
            ];
        }

        $validator = Validator::make($request->all(), array_merge([
            'decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])],
            'remarks' => [
                Rule::when(
                    $request->input('decision') === 'REJECTED',
                    ['required', 'string', 'min:3', 'max:5000'],
                    ['nullable', 'string', 'max:5000'],
                ),
            ],
            'revision_comment_application' => ['nullable', 'string', 'max:5000'],
            'revision_comment_contact' => ['nullable', 'string', 'max:5000'],
            'revision_comment_organizational' => ['nullable', 'string', 'max:5000'],
            'revision_comment_requirements' => ['nullable', 'string', 'max:5000'],
            'section_review' => [
                Rule::requiredIf(fn () => $request->input('decision') !== 'REJECTED'),
                'array',
            ],
        ], $sectionReviewRules));

        $validator->after(function ($validator) use ($request, $sectionKeys): void {
            if ($request->input('decision') === 'REJECTED') {
                return;
            }

            $sections = $request->input('section_review', []);
            if (! is_array($sections)) {
                $validator->errors()->add('section_review', 'Section review data is missing.');

                return;
            }

            foreach ($sectionKeys as $k) {
                if (($sections[$k] ?? 'pending') === 'pending') {
                    $validator->errors()->add(
                        'section_review',
                        'Review every section: mark each as Verified or Need revision before submitting.',
                    );
                    break;
                }
            }

            $colMap = $this->registrationReviewSectionRevisionColumns();
            foreach ($colMap as $sk => $column) {
                if (($sections[$sk] ?? '') === 'revision') {
                    $text = trim((string) $request->input($column, ''));
                    if (strlen($text) < 3) {
                        $validator->errors()->add(
                            $column,
                            'Add section feedback (at least 3 characters) for this part of the form.',
                        );
                    }
                }
            }

            $anyRevision = collect($sectionKeys)->contains(fn ($k) => ($sections[$k] ?? '') === 'revision');
            if ($anyRevision) {
                $generalOk = strlen(trim((string) $request->input('remarks', ''))) >= 3;
                $sectionTexts = collect($colMap)->map(fn ($col) => $request->input($col))->all();
                $anySectionText = collect($sectionTexts)->contains(fn ($t) => strlen(trim((string) $t)) >= 3);
                if (! $generalOk && ! $anySectionText) {
                    $validator->errors()->add(
                        'remarks',
                        'For revision, add general remarks (at least 3 characters) and/or section feedback for each part marked Need revision.',
                    );
                }
            }
        });

        $validated = $validator->validate();

        /** @var User $admin */
        $admin = $request->user();
        $decision = $validated['decision'];

        /** @var array<string, string> $sectionStates */
        $sectionStates = $validated['section_review'] ?? [];

        if ($decision !== 'REJECTED') {
            $anyRevision = in_array('revision', $sectionStates, true);
            $decision = $anyRevision ? 'REVISION' : 'APPROVED';
        }

        $remarks = trim((string) ($validated['remarks'] ?? ''));

        $colMap = $this->registrationReviewSectionRevisionColumns();
        $sectionFields = [];
        foreach ($colMap as $sk => $column) {
            $raw = trim((string) $request->input($column, ''));
            if ($decision === 'REVISION' && ($sectionStates[$sk] ?? '') === 'revision') {
                $sectionFields[$column] = $raw;
            } else {
                $sectionFields[$column] = '';
            }
        }

        DB::transaction(function () use ($registration, $decision, $remarks, $admin, $sectionFields, $sectionStates): void {
            $registration->registration_status = $decision;
            $registration->approved_by_sdao = $admin->full_name;
            $registration->approval_date = now()->toDateString();

            if ($decision === 'REJECTED') {
                $registration->section_review_state = null;
            } else {
                $registration->section_review_state = $sectionStates;
            }

            if ($decision === 'REVISION') {
                foreach ($sectionFields as $column => $value) {
                    $registration->{$column} = $value !== '' ? $value : null;
                }
            } else {
                foreach (array_keys($sectionFields) as $column) {
                    $registration->{$column} = null;
                }
            }

            if ($decision === 'APPROVED') {
                $registration->approval_decision = 'APPROVED';
                $registration->additional_remarks = $remarks !== '' ? $remarks : null;
                $registration->organization?->update([
                    'organization_status' => 'ACTIVE',
                    'profile_information_revision_requested' => false,
                    'profile_revision_notes' => null,
                ]);
            } else {
                $registration->approval_decision = null;
                $registration->additional_remarks = $remarks !== '' ? $remarks : null;
            }

            if ($decision === 'REVISION') {
                $registration->organization?->update([
                    'profile_information_revision_requested' => true,
                    'profile_revision_notes' => $this->composeRegistrationRevisionNotesForProfile($remarks, $sectionFields),
                ]);
            }

            if ($decision === 'REJECTED') {
                $registration->organization?->update([
                    'profile_information_revision_requested' => false,
                    'profile_revision_notes' => null,
                ]);
            }

            $registration->save();
        });

        return redirect()
            ->route('admin.registrations.show', $registration)
            ->with('success', 'Registration decision saved successfully.');
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

    public function showRenewal(Request $request, OrganizationRenewal $renewal): View
    {
        $this->authorizeAdmin($request);

        $renewal->load(['organization', 'user']);

        return view('admin.review-show', [
            'pageTitle' => 'Renewal Submission Details',
            'backRoute' => route('admin.renewals.index'),
            'status' => $renewal->renewal_status ?? 'PENDING',
            'details' => [
                'Organization' => $renewal->organization?->organization_name ?? 'N/A',
                'Submitted By' => $renewal->user?->full_name ?? 'N/A',
                'Academic Year' => $renewal->academic_year ?? 'N/A',
                'Contact Person' => $renewal->contact_person ?? 'N/A',
                'Submission Date' => optional($renewal->submission_date)->format('M d, Y') ?? 'N/A',
                'Contact Email' => $renewal->contact_email ?? 'N/A',
            ],
            'organization' => $renewal->organization,
        ]);
    }

    public function requestOrganizationProfileRevision(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'profile_revision_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $organization->update([
            'profile_information_revision_requested' => true,
            'profile_revision_notes' => $validated['profile_revision_notes'] ?? null,
        ]);

        return back()->with(
            'success',
            'Organization profile revision has been requested. The organization officer may edit their profile.',
        );
    }

    public function showCalendar(Request $request, ActivityCalendar $calendar): View
    {
        $this->authorizeAdmin($request);

        $calendar->load(['organization', 'entries']);

        $termLabels = [
            'term_1' => 'Term 1',
            'term_2' => 'Term 2',
            'term_3' => 'Term 3',
        ];
        $termKey = $calendar->semester ?? '';
        $termLabel = $termLabels[$termKey] ?? ($termKey !== '' ? $termKey : 'N/A');

        $calendarFile = $calendar->calendar_file;
        $calendarFileDisplay = $calendarFile
            ? Storage::disk('public')->url($calendarFile)
            : 'None (activities submitted via form)';

        return view('admin.review-show', [
            'pageTitle' => 'Activity Calendar Submission Details',
            'backRoute' => route('admin.calendars.index'),
            'status' => $calendar->calendar_status ?? 'PENDING',
            'details' => [
                'Organization (profile)' => $calendar->organization?->organization_name ?? 'N/A',
                'RSO name (form)' => $calendar->submitted_organization_name ?? 'N/A',
                'Academic Year' => $calendar->academic_year ?? 'N/A',
                'Term' => $termLabel,
                'Submission Date' => optional($calendar->submission_date)->format('M d, Y') ?? 'N/A',
                'Calendar File' => $calendarFileDisplay,
            ],
            'calendarEntries' => $calendar->entries,
        ]);
    }

    public function showProposal(Request $request, ActivityProposal $proposal): View
    {
        $this->authorizeAdmin($request);

        $proposal->load(['organization', 'user']);

        $schoolLabels = [
            'sace' => 'School of Architecture, Computer and Engineering',
            'sahs' => 'School of Allied Health and Sciences',
            'sabm' => 'School of Accounting and Business Management',
            'shs' => 'Senior High School',
        ];

        $logoUrl = $proposal->organization_logo_path
            ? Storage::disk('public')->url($proposal->organization_logo_path)
            : null;
        $resumeUrl = $proposal->resume_resource_persons_path
            ? Storage::disk('public')->url($proposal->resume_resource_persons_path)
            : null;

        $details = [
            'Organization (profile)' => $proposal->organization?->organization_name ?? 'N/A',
            'Submitted By' => $proposal->user?->full_name ?? 'N/A',
            'Submission Date' => optional($proposal->submission_date)->format('M d, Y') ?? 'N/A',
            'Organization name (form)' => $proposal->form_organization_name ?? 'N/A',
            'Academic Year' => $proposal->academic_year ?? 'N/A',
            'School' => $proposal->school_code ? ($schoolLabels[$proposal->school_code] ?? $proposal->school_code) : 'N/A',
            'Department / Program' => $proposal->department_program ?? 'N/A',
            'Organization logo' => $logoUrl ?? 'N/A',
            'Activity Title' => $proposal->activity_title ?? 'N/A',
            'Proposed Start' => optional($proposal->proposed_start_date)->format('M d, Y') ?? 'N/A',
            'Proposed End' => optional($proposal->proposed_end_date)->format('M d, Y') ?? 'N/A',
            'Proposed Time' => $proposal->proposed_time ?? 'N/A',
            'Venue' => $proposal->venue ?? 'N/A',
            'Overall Goal' => $proposal->overall_goal ?? ($proposal->activity_description ?? 'N/A'),
            'Specific Objectives' => $proposal->specific_objectives ?? 'N/A',
            'Criteria / Mechanics' => $proposal->criteria_mechanics ?? 'N/A',
            'Program Flow' => $proposal->program_flow ?? 'N/A',
            'Proposed Budget (total)' => $proposal->estimated_budget !== null ? number_format((float) $proposal->estimated_budget, 2) : 'N/A',
            'Source of Funding' => $proposal->source_of_funding ?? 'N/A',
            'Materials and Supplies' => $proposal->budget_materials_supplies !== null ? number_format((float) $proposal->budget_materials_supplies, 2) : 'N/A',
            'Food and Beverage' => $proposal->budget_food_beverage !== null ? number_format((float) $proposal->budget_food_beverage, 2) : 'N/A',
            'Other Expenses' => $proposal->budget_other_expenses !== null ? number_format((float) $proposal->budget_other_expenses, 2) : 'N/A',
            'Resume (resource persons)' => $resumeUrl ?? 'N/A',
        ];

        return view('admin.review-show', [
            'pageTitle' => 'Activity Proposal Submission Details',
            'backRoute' => route('admin.proposals.index'),
            'status' => $proposal->proposal_status ?? 'PENDING',
            'details' => $details,
            'organization' => $proposal->organization,
        ]);
    }

    public function showReport(Request $request, ActivityReport $report): View
    {
        $this->authorizeAdmin($request);

        $report->load(['organization', 'user']);

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

        return view('admin.review-show', [
            'pageTitle' => 'After Activity Report Submission Details',
            'backRoute' => route('admin.reports.index'),
            'status' => $report->report_status ?? 'PENDING',
            'details' => $details,
            'organization' => $report->organization,
        ]);
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
            ->where('proposal_status', '!=', 'DRAFT')
            ->with(['organization', 'user'])
            ->latest('submission_date')
            ->latest('id')
            ->get()
            ->map(function (ActivityProposal $proposal): array {
                return [
                    'title' => $proposal->activity_title ?? 'Untitled Activity',
                    'start' => optional($proposal->proposed_start_date)?->toDateString(),
                    'end' => optional($proposal->proposed_end_date)?->addDay()->toDateString(),
                    'status' => $proposal->proposal_status ?? 'PENDING',
                    'organization_name' => $proposal->organization?->organization_name ?? 'N/A',
                    'submitted_by' => $proposal->user?->full_name ?? 'N/A',
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
}
