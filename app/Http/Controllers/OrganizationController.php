<?php

namespace App\Http\Controllers;

use App\Models\ActivityCalendar;
use App\Models\ActivityCalendarEntry;
use App\Models\ActivityProposal;
use App\Models\ActivityReport;
use App\Models\Organization;
use App\Models\OrganizationOfficer;
use App\Models\OrganizationRegistration;
use App\Models\OrganizationRenewal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    /** Checkbox values for new registration — must match `requirements[]` and `requirement_files` keys. */
    private const REGISTRATION_REQUIREMENT_KEYS = [
        'letter_of_intent',
        'application_form',
        'by_laws',
        'updated_list_of_officers_founders',
        'dean_endorsement_faculty_adviser',
        'proposed_projects_budget',
        'others',
    ];

    /** Checkbox values for renewal — must match `requirements[]` and `requirement_files` keys. */
    private const RENEWAL_REQUIREMENT_KEYS = [
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

    private const REQUIREMENTS_MIN_ONE_MESSAGE = 'Select at least one requirement you are submitting.';

    /** Form `school` select values → stored `organizations.college_department` label. */
    private const SCHOOL_CODE_LABELS = [
        'sace' => 'School of Architecture, Computer and Engineering',
        'sahs' => 'School of Allied Health and Sciences',
        'sabm' => 'School of Accounting and Business Management',
        'shs' => 'Senior High School',
    ];

    /** Stored in `college_department` when organization type is extra-curricular (non-academic). */
    private const NON_ACADEMIC_DEPARTMENT_LABEL = 'Non-academic (Extra-Curricular)';

    private function schoolDepartmentLabel(string $code): string
    {
        return self::SCHOOL_CODE_LABELS[$code] ?? $code;
    }

    /**
     * Reverse map for renewal form: stored label → `school` select value.
     */
    private function schoolCodeFromDepartment(?string $department): ?string
    {
        if ($department === null || $department === '') {
            return null;
        }
        if ($department === self::NON_ACADEMIC_DEPARTMENT_LABEL) {
            return null;
        }
        foreach (self::SCHOOL_CODE_LABELS as $code => $label) {
            if ($label === $department) {
                return $code;
            }
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    private function schoolRules(Request $request): array
    {
        return [
            Rule::requiredIf(fn () => $request->input('organization_type') === 'co_curricular'),
            'nullable',
            'string',
            Rule::excludeIf(fn () => $request->input('organization_type') === 'extra_curricular'),
            Rule::in(array_keys(self::SCHOOL_CODE_LABELS)),
        ];
    }

    private function collegeDepartmentForOrganizationType(array $validated): string
    {
        if (($validated['organization_type'] ?? '') === 'extra_curricular') {
            return self::NON_ACADEMIC_DEPARTMENT_LABEL;
        }

        return $this->schoolDepartmentLabel($validated['school']);
    }

    /**
     * Philippine mobile: exactly 11 digits including trunk 0 and 9 (09XXXXXXXXX).
     * Also accepts +639… / 639… (normalized to 09…). Letters and invalid symbols rejected.
     *
     * @return array<int, mixed>
     */
    private function contactNoRules(): array
    {
        return [
            'required',
            'string',
            'max:25',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $raw = trim((string) $value);
                if ($raw === '') {
                    $fail('Contact number is required.');

                    return;
                }
                if (preg_match('/[a-zA-Z]/', $raw)) {
                    $fail('Contact number may not contain letters. Use exactly 11 digits starting with 09 (e.g. 09123456789).');

                    return;
                }
                if (preg_match('/[^\d\s+\-().]/', $raw)) {
                    $fail('Contact number may only include digits and optional spaces, dashes, or parentheses.');

                    return;
                }
                $digits = preg_replace('/\D/', '', $raw);
                if ($digits === '') {
                    $fail('Enter exactly 11 digits starting with 09 (e.g. 09123456789).');

                    return;
                }
                if (str_starts_with($digits, '63')) {
                    $digits = substr($digits, 2);
                }
                if (str_starts_with($digits, '0')) {
                    $digits = substr($digits, 1);
                }
                if (! preg_match('/^9\d{9}$/', $digits)) {
                    $fail('Contact number must be exactly 11 digits including 09 (e.g. 09123456789).');
                }
            },
        ];
    }

    /** Store as 09XXXXXXXXX (11 digits). */
    private function normalizePhilippineContactNo(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);
        if (str_starts_with($digits, '63')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return '0'.$digits;
    }

    public function index(Request $request)
    {
        $calendarEvents = [];
        $user = $request->user();
        if ($user) {
            $organization = $user->currentOrganization();
            if ($organization) {
                $calendarEvents = $this->buildOrganizationDashboardCalendarEvents($organization);
            }
        }

        return view('organizations.index', [
            'calendarEvents' => $calendarEvents,
        ]);
    }

    /**
     * @return list<array{title: string, start: string, end: string|null, status: string, time: string|null, venue: string|null}>
     */
    private function buildOrganizationDashboardCalendarEvents(Organization $organization): array
    {
        $events = [];

        $proposals = $organization->activityProposals()
            ->whereNotIn('proposal_status', ['DRAFT'])
            ->whereNotNull('proposed_start_date')
            ->orderBy('proposed_start_date')
            ->orderBy('id')
            ->get();

        foreach ($proposals as $proposal) {
            $start = $proposal->proposed_start_date->toDateString();

            $end = null;
            if ($proposal->proposed_end_date) {
                if ($proposal->proposed_end_date->gt($proposal->proposed_start_date)) {
                    $end = $proposal->proposed_end_date->copy()->addDay()->toDateString();
                }
            }

            $normalizedStatus = strtoupper((string) ($proposal->proposal_status ?? 'PENDING'));
            $calendarStatus = $normalizedStatus === 'APPROVED' ? 'scheduled' : 'pending';

            $events[] = [
                'title' => (string) $proposal->activity_title,
                'start' => $start,
                'end' => $end,
                'status' => $calendarStatus,
                'time' => $proposal->proposed_time ? (string) $proposal->proposed_time : null,
                'venue' => $proposal->venue ? (string) $proposal->venue : null,
            ];
        }

        return $events;
    }

    public function manage()
    {
        return view('organizations.manage');
    }

    public function showSubmitReportHub()
    {
        return view('organizations.submit-report');
    }

    public function showActivitySubmissionHub()
    {
        return view('organizations.activity-submission');
    }

    // ── Registration ────────────────────────────────────────────

    public function showRegistrationForm(Request $request)
    {
        $user = $request->user();
        $officerValidationPending = $user && ! $user->isOfficerValidated();
        $alreadyLinkedToOrganization = $user && $user->currentOrganization() !== null;

        return view('organizations.register', compact('officerValidationPending', 'alreadyLinkedToOrganization'));
    }

    public function storeRegistration(Request $request)
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return redirect()
                ->route('login')
                ->with('error', 'Please log in to submit organization registration.');
        }

        if ($user->role_type !== 'ORG_OFFICER') {
            abort(403, 'Only organization officers can submit organization registration.');
        }

        if (! $user->isOfficerValidated()) {
            return back()->with('error', 'Your student officer account is pending SDAO validation.');
        }

        if ($user->currentOrganization()) {
            return back()
                ->with('error', 'Your account is already linked to an organization.')
                ->withInput();
        }

        $validated = $request->validate(array_merge([
            'organization_name' => ['required', 'string', 'max:150'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_no' => $this->contactNoRules(),
            'email_address' => ['required', 'email', 'max:150'],
            'academic_year' => ['required', 'string', 'max:20'],
            'date_organized' => ['required', 'date'],
            'organization_type' => ['required', 'in:co_curricular,extra_curricular'],
            'purpose' => ['required', 'string'],
            'school' => $this->schoolRules($request),
            'requirements' => ['required', 'array', 'min:1'],
            'requirements.*' => ['string', Rule::in(self::REGISTRATION_REQUIREMENT_KEYS)],
            'requirements_other' => [
                Rule::requiredIf(fn () => in_array('others', $request->input('requirements', []) ?? [], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'requirement_files' => ['nullable', 'array'],
        ], $this->requirementFileRules($request, self::REGISTRATION_REQUIREMENT_KEYS)), [
            'requirements.required' => self::REQUIREMENTS_MIN_ONE_MESSAGE,
            'requirements.min' => self::REQUIREMENTS_MIN_ONE_MESSAGE,
        ]);

        $validated['contact_no'] = $this->normalizePhilippineContactNo($validated['contact_no']);

        $reqs = collect($validated['requirements'] ?? []);

        DB::transaction(function () use ($validated, $reqs, $user, $request): void {
            $organization = Organization::create([
                'organization_name' => $validated['organization_name'],
                'organization_type' => $validated['organization_type'],
                'college_department' => $this->collegeDepartmentForOrganizationType($validated),
                'purpose' => $validated['purpose'],
                'founded_date' => $validated['date_organized'],
                'organization_status' => 'PENDING',
            ]);

            OrganizationOfficer::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'position_title' => 'President',
                'officer_status' => 'ACTIVE',
            ]);

            $filePaths = $this->storeRequirementFilesForKeys(
                $request,
                $reqs,
                self::REGISTRATION_REQUIREMENT_KEYS,
                "organization-requirements/{$organization->id}/registration"
            );

            OrganizationRegistration::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'academic_year' => $validated['academic_year'],
                'contact_person' => $validated['contact_person'],
                'contact_no' => $validated['contact_no'],
                'contact_email' => $validated['email_address'],
                'submission_date' => now()->toDateString(),
                'req_letter_of_intent' => $reqs->contains('letter_of_intent'),
                'req_application_form' => $reqs->contains('application_form'),
                'req_by_laws' => $reqs->contains('by_laws'),
                'req_officers_list' => $reqs->contains('updated_list_of_officers_founders'),
                'req_dean_endorsement' => $reqs->contains('dean_endorsement_faculty_adviser'),
                'req_proposed_projects' => $reqs->contains('proposed_projects_budget'),
                'req_others' => $reqs->contains('others'),
                'req_others_specify' => $validated['requirements_other'] ?? null,
                'requirement_files' => $filePaths === [] ? null : $filePaths,
            ]);
        });

        return redirect()
            ->route('organizations.register')
            ->with('success', 'Registration application submitted successfully.')
            ->with('registration_redirect_to', route('organizations.profile'));
    }

    // ── Renewal ─────────────────────────────────────────────────

    public function showRenewalForm(Request $request)
    {
        $organization = $request->user()?->currentOrganization();
        $schoolCodeDefault = null;
        if ($organization) {
            $schoolCodeDefault = $this->schoolCodeFromDepartment($organization->college_department);
        }

        $officerValidationPending = $request->user() && ! $request->user()->isOfficerValidated();
        $renewalBlockedNoOrganization = $organization === null;

        return view('organizations.renew', compact('organization', 'schoolCodeDefault', 'officerValidationPending', 'renewalBlockedNoOrganization'));
    }

    public function storeRenewal(Request $request)
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return redirect()
                ->route('login')
                ->with('error', 'Please log in to submit a renewal application.');
        }

        $organization = $user->currentOrganization();

        if (! $user->isOfficerValidated()) {
            return back()->with('error', 'Your student officer account is pending SDAO validation.');
        }

        if (! $organization) {
            return back()
                ->with('error', 'No organization found for your account. Please register first.')
                ->withInput();
        }

        $validated = $request->validate(array_merge([
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_no' => $this->contactNoRules(),
            'email_address' => ['required', 'email', 'max:150'],
            'academic_year' => ['required', 'string', 'max:20'],
            'purpose' => ['required', 'string'],
            'organization_type' => ['required', 'in:co_curricular,extra_curricular'],
            'school' => $this->schoolRules($request),
            'requirements' => ['required', 'array', 'min:1'],
            'requirements.*' => ['string', Rule::in(self::RENEWAL_REQUIREMENT_KEYS)],
            'requirements_other' => [
                Rule::requiredIf(fn () => in_array('others', $request->input('requirements', []) ?? [], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'requirement_files' => ['nullable', 'array'],
        ], $this->requirementFileRules($request, self::RENEWAL_REQUIREMENT_KEYS)), [
            'requirements.required' => self::REQUIREMENTS_MIN_ONE_MESSAGE,
            'requirements.min' => self::REQUIREMENTS_MIN_ONE_MESSAGE,
        ]);

        $validated['contact_no'] = $this->normalizePhilippineContactNo($validated['contact_no']);

        $reqs = collect($validated['requirements'] ?? []);

        $organization->update([
            'purpose' => $validated['purpose'],
            'organization_type' => $validated['organization_type'],
            'college_department' => $this->collegeDepartmentForOrganizationType($validated),
        ]);

        $renewal = OrganizationRenewal::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'academic_year' => $validated['academic_year'],
            'contact_person' => $validated['contact_person'],
            'contact_no' => $validated['contact_no'],
            'contact_email' => $validated['email_address'],
            'submission_date' => now()->toDateString(),
            'req_letter_of_intent' => $reqs->contains('letter_of_intent'),
            'req_application_form' => $reqs->contains('application_form'),
            'req_by_laws' => $reqs->contains('by_laws_updated_if_applicable'),
            'req_officers_list' => $reqs->contains('updated_list_of_officers_founders_ay'),
            'req_dean_endorsement' => $reqs->contains('dean_endorsement_faculty_adviser'),
            'req_proposed_projects' => $reqs->contains('proposed_projects_budget'),
            'req_past_projects' => $reqs->contains('past_projects'),
            'req_financial_statement' => $reqs->contains('financial_statement_previous_ay'),
            'req_evaluation_summary' => $reqs->contains('evaluation_summary_past_projects'),
            'req_others' => $reqs->contains('others'),
            'req_others_specify' => $validated['requirements_other'] ?? null,
        ]);

        $filePaths = $this->storeRequirementFilesForKeys(
            $request,
            $reqs,
            self::RENEWAL_REQUIREMENT_KEYS,
            "organization-requirements/{$organization->id}/renewals/{$renewal->id}"
        );

        if ($filePaths !== []) {
            $renewal->update(['requirement_files' => $filePaths]);
        }

        return redirect()
            ->route('organizations.renew')
            ->with('success', 'Renewal application submitted successfully.')
            ->with('renewal_redirect_to', route('organizations.profile'));
    }

    // ── Organization Profile ────────────────────────────────────

    public function profile(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user?->currentOrganization();

        $canEditProfile = $organization?->canEditProfile() ?? false;
        $profileEditBlockedMessage = $organization?->profileEditBlockedMessage() ?? '';

        if ($organization && $request->query('edit') && ! $canEditProfile) {
            return redirect()
                ->route('organizations.profile')
                ->with('error', $profileEditBlockedMessage);
        }

        [$activeApplication, $applicationTypeLabel] = $this->resolveProfileActiveApplication($organization);
        $applicationWorkflowStatus = $this->workflowStatusFromApplication($activeApplication);

        $revisionRegistration = null;
        if ($organization && $organization->isProfileRevisionRequested()) {
            $revisionRegistration = $organization->registrations()
                ->where('registration_status', 'REVISION')
                ->latest('updated_at')
                ->latest('id')
                ->first();
        }

        return view('organizations.profile', [
            'organization' => $organization,
            'editing' => (bool) ($request->query('edit') && $canEditProfile && $organization),
            'canEditProfile' => $organization ? $canEditProfile : false,
            'profileEditBlockedMessage' => $organization ? $profileEditBlockedMessage : '',
            'activeApplication' => $activeApplication,
            'applicationTypeLabel' => $applicationTypeLabel,
            'applicationWorkflowStatus' => $applicationWorkflowStatus,
            'revisionRegistration' => $revisionRegistration,
        ]);
    }

    /**
     * @return array{0: OrganizationRegistration|OrganizationRenewal|null, 1: string|null}
     */
    private function resolveProfileActiveApplication(?Organization $organization): array
    {
        if (! $organization) {
            return [null, null];
        }

        $registration = $organization->registrations()
            ->latest('submission_date')
            ->latest('id')
            ->first();

        $renewal = $organization->renewals()
            ->latest('submission_date')
            ->latest('id')
            ->first();

        $renTs = $renewal?->submission_date?->timestamp ?? 0;
        $regTs = $registration?->submission_date?->timestamp ?? 0;

        if ($renewal && (! $registration || $renTs >= $regTs)) {
            return [$renewal, 'Renewal'];
        }

        if ($registration) {
            return [$registration, 'New registration'];
        }

        return [null, null];
    }

    private function workflowStatusFromApplication(OrganizationRegistration|OrganizationRenewal|null $application): ?string
    {
        if (! $application) {
            return null;
        }

        if ($application instanceof OrganizationRenewal) {
            return $application->renewal_status;
        }

        return $application->registration_status;
    }

    public function updateProfile(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user?->currentOrganization();

        if (! $organization) {
            return back()->with('error', 'No organization found for your account.');
        }

        if (! $organization->canEditProfile()) {
            return redirect()
                ->route('organizations.profile')
                ->with('error', $organization->profileEditBlockedMessage());
        }

        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:150'],
            'organization_type' => ['required', 'string', 'max:50'],
            'college_department' => ['required', 'string', 'max:100'],
            'purpose' => ['required', 'string'],
            'adviser_name' => ['nullable', 'string', 'max:100'],
            'founded_date' => ['nullable', 'date'],
        ]);

        $organization->update(array_merge($validated, [
            'profile_information_revision_requested' => false,
            'profile_revision_notes' => null,
        ]));

        return redirect()
            ->route('organizations.profile')
            ->with('success', 'Organization profile updated successfully.');
    }

    // ── Activity Calendar Submission ─────────────────────────

    public function showActivityCalendarSubmission(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $activeOfficer = $this->resolveActiveOfficer($request);
        $organization = $activeOfficer
          ? Organization::query()->find($activeOfficer->organization_id)
          : null;

        if (! $organization) {
            return redirect()
                ->route('organizations.profile')
                ->with('error', 'Your account is not linked to an active organization.');
        }

        $latestCalendar = $organization->activityCalendars()
            ->with([
                'entries' => fn ($query) => $query->orderBy('activity_date')->orderBy('id'),
            ])
            ->latest('submission_date')
            ->latest('id')
            ->first();

        $calendarSubmittedLocked = $latestCalendar !== null
            && strtoupper((string) $latestCalendar->calendar_status) !== 'REVISION';

        return view('organizations.activity-calendar-submission', [
            'organization' => $organization,
            'latestCalendar' => $latestCalendar,
            'calendarSubmittedLocked' => $calendarSubmittedLocked,
            'officerValidationPending' => ! $user->isOfficerValidated(),
        ]);
    }

    public function storeActivityCalendarSubmission(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $activeOfficer = $this->resolveActiveOfficer($request);
        $organization = $activeOfficer
          ? Organization::query()->find($activeOfficer->organization_id)
          : null;

        if (! $organization) {
            return redirect()
                ->route('organizations.profile')
                ->with('error', 'Your account is not linked to an active organization.');
        }

        if (! $user->isOfficerValidated()) {
            return redirect()
                ->route('organizations.activity-calendar-submission')
                ->with('error', 'Your student officer account is pending SDAO validation.');
        }

        $latestLockedCalendar = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->latest('submission_date')
            ->latest('id')
            ->first();

        if ($latestLockedCalendar && strtoupper((string) $latestLockedCalendar->calendar_status) !== 'REVISION') {
            return redirect()
                ->route('organizations.activity-calendar-submission')
                ->with('error', 'This activity calendar has already been submitted and can no longer be edited.');
        }

        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:50'],
            'term' => ['required', 'string', Rule::in(['term_1', 'term_2', 'term_3'])],
            'organization_name' => ['required', 'string', 'max:255'],
            'date_submitted' => ['required', 'date'],
            'activities' => ['required', 'array', 'min:5'],
            'activities.*.date' => ['required', 'date'],
            'activities.*.name' => ['required', 'string', 'max:500'],
            'activities.*.sdg' => ['required', 'string', 'max:64'],
            'activities.*.venue' => ['required', 'string', 'max:255'],
            'activities.*.participant_program' => ['required', 'string', 'max:2000'],
            'activities.*.budget' => ['required', 'string', 'max:255'],
        ], [
            'activities.min' => 'Add at least five activities before submitting the activity calendar.',
        ]);

        $hasPendingSubmission = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->where('academic_year', $validated['academic_year'])
            ->where('semester', $validated['term'])
            ->where('calendar_status', 'PENDING')
            ->exists();

        if ($hasPendingSubmission) {
            return back()
                ->withErrors([
                    'academic_year' => 'A pending activity calendar already exists for this academic year and term.',
                ])
                ->withInput();
        }

        DB::transaction(function () use ($organization, $validated): void {
            $calendar = ActivityCalendar::create([
                'organization_id' => $organization->id,
                'submitted_organization_name' => $validated['organization_name'],
                'academic_year' => $validated['academic_year'],
                'semester' => $validated['term'],
                'calendar_file' => null,
                'submission_date' => $validated['date_submitted'],
                'calendar_status' => 'PENDING',
            ]);

            foreach ($validated['activities'] as $row) {
                ActivityCalendarEntry::query()->create([
                    'activity_calendar_id' => $calendar->id,
                    'activity_date' => $row['date'],
                    'activity_name' => $row['name'],
                    'sdg' => $row['sdg'],
                    'venue' => $row['venue'],
                    'participant_program' => $row['participant_program'],
                    'budget' => $row['budget'],
                ]);
            }
        });

        return redirect()
            ->route('organizations.activity-calendar-submission')
            ->with('activity_calendar_submitted', true);
    }

    // ── Activity Proposal Submission ──────────────────────────

    public function showActivityProposalSubmission(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $activeOfficer = $this->resolveActiveOfficer($request);
        $organization = $activeOfficer
            ? Organization::query()->find($activeOfficer->organization_id)
            : null;

        if (! $organization) {
            return redirect()
                ->route('organizations.profile')
                ->with('error', 'Your account is not linked to an active organization.');
        }

        $schoolPrefill = $this->schoolCodeFromDepartment($organization->college_department);
        $calendarEntry = null;
        $linkedProposal = null;
        $proposalCalendar = null;

        if ($request->filled('calendar_entry')) {
            $calendarEntry = ActivityCalendarEntry::query()
                ->whereKey($request->integer('calendar_entry'))
                ->whereHas('activityCalendar', function ($q) use ($organization): void {
                    $q->where('organization_id', $organization->id);
                })
                ->with('activityCalendar')
                ->first();

            if (! $calendarEntry) {
                return redirect()
                    ->route('organizations.activity-proposal-submission')
                    ->with('error', 'That calendar activity was not found or does not belong to your organization.');
            }

            $linkedProposal = ActivityProposal::query()
                ->where('organization_id', $organization->id)
                ->where('activity_calendar_entry_id', $calendarEntry->id)
                ->first();

            if ($linkedProposal && ! in_array($linkedProposal->proposal_status, ['DRAFT', 'REVISION'], true)) {
                return redirect()
                    ->route('organizations.activity-submission.proposals.show', $linkedProposal)
                    ->with('error', 'This activity already has a submitted proposal. Open it from Activity Submission to view details.');
            }

            $proposalCalendar = $calendarEntry->activityCalendar()
                ->with([
                    'entries' => fn ($q) => $q->orderBy('activity_date')->orderBy('id')->with('proposal'),
                ])
                ->first();
        }

        if (! $proposalCalendar) {
            $proposalCalendar = ActivityCalendar::query()
                ->where('organization_id', $organization->id)
                ->with([
                    'entries' => fn ($q) => $q->orderBy('activity_date')->orderBy('id')->with('proposal'),
                ])
                ->latest('submission_date')
                ->latest('id')
                ->first();
        }

        $prefill = $this->buildActivityProposalFormPrefill($organization, $calendarEntry, $linkedProposal, $schoolPrefill);

        return view('organizations.activity-proposal-submission', [
            'organization' => $organization,
            'schoolOptions' => self::SCHOOL_CODE_LABELS,
            'schoolPrefill' => $schoolPrefill,
            'officerValidationPending' => ! $user->isOfficerValidated(),
            'calendarEntry' => $calendarEntry,
            'linkedProposal' => $linkedProposal,
            'proposalCalendar' => $proposalCalendar,
            'prefill' => $prefill,
        ]);
    }

    public function storeActivityProposalSubmission(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $activeOfficer = $this->resolveActiveOfficer($request);
        $organization = $activeOfficer
            ? Organization::query()->find($activeOfficer->organization_id)
            : null;

        if (! $organization) {
            return redirect()
                ->route('organizations.profile')
                ->with('error', 'Your account is not linked to an active organization.');
        }

        if (! $user->isOfficerValidated()) {
            return redirect()
                ->route('organizations.activity-proposal-submission')
                ->with('error', 'Your student officer account is pending SDAO validation.');
        }

        $calendarEntry = null;
        if ($request->filled('activity_calendar_entry_id')) {
            $calendarEntry = ActivityCalendarEntry::query()
                ->whereKey((int) $request->input('activity_calendar_entry_id'))
                ->whereHas('activityCalendar', function ($q) use ($organization): void {
                    $q->where('organization_id', $organization->id);
                })
                ->first();

            if (! $calendarEntry) {
                return back()
                    ->withInput()
                    ->withErrors(['activity_calendar_entry_id' => 'Invalid calendar activity for this organization.']);
            }
        }

        $existing = null;
        if ($calendarEntry) {
            $existing = ActivityProposal::query()
                ->where('organization_id', $organization->id)
                ->where('activity_calendar_entry_id', $calendarEntry->id)
                ->first();
        }

        if ($existing && ! in_array($existing->proposal_status, ['DRAFT', 'REVISION'], true)) {
            return redirect()
                ->route('organizations.activity-submission.proposals.show', $existing)
                ->with('error', 'This proposal can no longer be edited from the submission form. View it under Activity Submission.');
        }

        $proposalAction = (string) $request->input('proposal_action', 'submit');
        $asDraft = $proposalAction === 'draft';

        $validated = $request->validate([
            'activity_calendar_entry_id' => ['nullable', 'integer'],
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_logo' => [
                Rule::requiredIf(fn () => $existing === null || ! $existing->organization_logo_path),
                'nullable',
                'file',
                'image',
                'max:5120',
            ],
            'school' => ['required', 'string', Rule::in(array_keys(self::SCHOOL_CODE_LABELS))],
            'department_program' => ['required', 'string', 'max:255'],
            'academic_year' => ['required', 'string', 'max:50'],
            'project_activity_title' => ['required', 'string', 'max:200'],
            'proposed_start_date' => ['required', 'date'],
            'proposed_end_date' => ['required', 'date', 'after_or_equal:proposed_start_date'],
            'proposed_time' => ['required', 'string', 'max:32'],
            'venue' => ['required', 'string', 'max:255'],
            'overall_goal' => ['required', 'string', 'max:5000'],
            'specific_objectives' => ['required', 'string', 'max:5000'],
            'criteria_mechanics' => ['required', 'string', 'max:5000'],
            'program_flow' => ['required', 'string', 'max:5000'],
            'proposed_budget' => ['required', 'numeric', 'min:0'],
            'source_of_funding' => ['required', 'string', Rule::in(['Internal', 'External'])],
            'external_funding_support' => [
                Rule::requiredIf(fn () => ! $asDraft
                    && $request->input('source_of_funding') === 'External'
                    && ($existing === null || ! $existing->external_funding_support_path)),
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:10240',
            ],
            'materials_supplies' => ['required', 'numeric', 'min:0'],
            'food_beverage' => ['required', 'numeric', 'min:0'],
            'other_expenses' => ['required', 'numeric', 'min:0'],
            'resume_resource_persons' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'proposal_action' => ['nullable', 'string', Rule::in(['draft', 'submit'])],
        ], [
            'proposed_end_date.after_or_equal' => 'The proposed end date must be on or after the start date.',
        ]);

        $proposedTotal = round((float) $validated['proposed_budget'], 2);
        $expenseSum = round(
            (float) $validated['materials_supplies'] + (float) $validated['food_beverage'] + (float) $validated['other_expenses'],
            2
        );
        if (abs($proposedTotal - $expenseSum) > 0.01) {
            return back()
                ->withInput()
                ->withErrors([
                    'budget_breakdown' => 'Proposed Budget (total) must equal Materials and Supplies + Food and Beverage + Other Expenses. Current total is '.number_format($proposedTotal, 2).' but the expense lines sum to '.number_format($expenseSum, 2).'.',
                ]);
        }

        if ($asDraft) {
            if ($existing && $existing->proposal_status === 'REVISION') {
                $proposalStatus = 'REVISION';
                $submissionDate = $existing->submission_date;
            } else {
                $proposalStatus = 'DRAFT';
                $submissionDate = null;
            }
        } else {
            $proposalStatus = 'PENDING';
            $submissionDate = now()->toDateString();
        }

        $basePath = 'activity-proposals/'.$organization->id;

        $logoPath = $existing?->organization_logo_path;
        if ($request->hasFile('organization_logo')) {
            $logoPath = $request->file('organization_logo')->store($basePath, 'public');
        }

        $resumePath = $existing?->resume_resource_persons_path;
        if ($request->hasFile('resume_resource_persons')) {
            $resumePath = $request->file('resume_resource_persons')->store($basePath, 'public');
        }

        $externalFundingPath = $existing?->external_funding_support_path;
        if ($validated['source_of_funding'] === 'Internal') {
            $externalFundingPath = null;
        } elseif ($request->hasFile('external_funding_support')) {
            $externalFundingPath = $request->file('external_funding_support')->store($basePath, 'public');
        }

        $summary = mb_substr(trim(strip_tags($validated['overall_goal'])), 0, 500);

        $payload = [
            'organization_id' => $organization->id,
            'calendar_id' => $calendarEntry?->activity_calendar_id,
            'activity_calendar_entry_id' => $calendarEntry?->id,
            'user_id' => $user->id,
            'form_organization_name' => $validated['organization_name'],
            'organization_logo_path' => $logoPath,
            'school_code' => $validated['school'],
            'department_program' => $validated['department_program'],
            'academic_year' => $validated['academic_year'],
            'activity_title' => $validated['project_activity_title'],
            'activity_description' => $summary !== '' ? $summary : null,
            'proposed_start_date' => $validated['proposed_start_date'],
            'proposed_end_date' => $validated['proposed_end_date'],
            'proposed_time' => $validated['proposed_time'],
            'venue' => $validated['venue'],
            'overall_goal' => $validated['overall_goal'],
            'specific_objectives' => $validated['specific_objectives'],
            'criteria_mechanics' => $validated['criteria_mechanics'],
            'program_flow' => $validated['program_flow'],
            'estimated_budget' => $validated['proposed_budget'],
            'source_of_funding' => $validated['source_of_funding'],
            'external_funding_support_path' => $externalFundingPath,
            'budget_materials_supplies' => $validated['materials_supplies'],
            'budget_food_beverage' => $validated['food_beverage'],
            'budget_other_expenses' => $validated['other_expenses'],
            'resume_resource_persons_path' => $resumePath,
            'submission_date' => $submissionDate,
            'proposal_status' => $proposalStatus,
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            ActivityProposal::create($payload);
        }

        $successMessage = $asDraft
            ? 'Proposal saved as draft. You can continue editing anytime.'
            : 'Activity proposal submitted successfully for SDAO review.';

        if ($calendarEntry) {
            return redirect()
                ->route('organizations.activity-proposal-submission', ['calendar_entry' => $calendarEntry->id])
                ->with('success', $successMessage);
        }

        return redirect()
            ->route('organizations.activity-proposal-submission')
            ->with('success', $successMessage);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildActivityProposalFormPrefill(
        Organization $organization,
        ?ActivityCalendarEntry $entry,
        ?ActivityProposal $linked,
        ?string $schoolPrefill
    ): array {
        if ($linked !== null) {
            $time = $linked->proposed_time ?? '';
            if (is_string($time) && strlen($time) >= 8 && str_contains($time, ':')) {
                $time = substr($time, 0, 5);
            }

            return [
                'organization_name' => $linked->form_organization_name ?? $organization->organization_name,
                'academic_year' => $linked->academic_year ?? '',
                'school' => $linked->school_code ?? $schoolPrefill,
                'department_program' => $linked->department_program ?? $organization->college_department ?? '',
                'project_activity_title' => $linked->activity_title ?? '',
                'proposed_start_date' => optional($linked->proposed_start_date)?->toDateString() ?? '',
                'proposed_end_date' => optional($linked->proposed_end_date)?->toDateString() ?? '',
                'proposed_time' => $time,
                'venue' => $linked->venue ?? '',
                'overall_goal' => $linked->overall_goal ?? '',
                'specific_objectives' => $linked->specific_objectives ?? '',
                'criteria_mechanics' => $linked->criteria_mechanics ?? '',
                'program_flow' => $linked->program_flow ?? '',
                'proposed_budget' => $linked->estimated_budget !== null ? (string) $linked->estimated_budget : '',
                'source_of_funding' => in_array((string) ($linked->source_of_funding ?? ''), ['Internal', 'External'], true)
                    ? (string) $linked->source_of_funding
                    : 'Internal',
                'materials_supplies' => $linked->budget_materials_supplies !== null ? (string) $linked->budget_materials_supplies : '',
                'food_beverage' => $linked->budget_food_beverage !== null ? (string) $linked->budget_food_beverage : '',
                'other_expenses' => $linked->budget_other_expenses !== null ? (string) $linked->budget_other_expenses : '',
            ];
        }

        if ($entry !== null) {
            $cal = $entry->activityCalendar;
            $dateStr = optional($entry->activity_date)?->toDateString() ?? '';

            return [
                'organization_name' => $organization->organization_name,
                'academic_year' => $cal?->academic_year ?? '',
                'school' => $schoolPrefill,
                'department_program' => $organization->college_department ?? '',
                'project_activity_title' => $entry->activity_name ?? '',
                'proposed_start_date' => $dateStr,
                'proposed_end_date' => $dateStr,
                'proposed_time' => '',
                'venue' => $entry->venue ?? '',
                'overall_goal' => '',
                'specific_objectives' => '',
                'criteria_mechanics' => '',
                'program_flow' => '',
                'proposed_budget' => '',
                'source_of_funding' => '',
                'materials_supplies' => '',
                'food_beverage' => '',
                'other_expenses' => '',
            ];
        }

        return [
            'organization_name' => $organization->organization_name,
            'academic_year' => '',
            'school' => $schoolPrefill,
            'department_program' => $organization->college_department ?? '',
            'project_activity_title' => '',
            'proposed_start_date' => '',
            'proposed_end_date' => '',
            'proposed_time' => '',
            'venue' => '',
            'overall_goal' => '',
            'specific_objectives' => '',
            'criteria_mechanics' => '',
            'program_flow' => '',
            'proposed_budget' => '',
            'source_of_funding' => '',
            'materials_supplies' => '',
            'food_beverage' => '',
            'other_expenses' => '',
        ];
    }

    // ── After Activity Report ─────────────────────────────────

    public function showAfterActivityReportForm(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $activeOfficer = $this->resolveActiveOfficer($request);
        $organization = $activeOfficer
            ? Organization::query()->find($activeOfficer->organization_id)
            : null;

        if (! $organization) {
            return redirect()
                ->route('organizations.manage')
                ->with('error', 'Your account is not linked to an active organization.');
        }

        return view('organizations.after-activity-report', [
            'organization' => $organization,
            'schoolOptions' => self::SCHOOL_CODE_LABELS,
            'optionalProposals' => $organization->activityProposals()->latest('id')->limit(100)->get(),
            'prefillPreparedBy' => $user->full_name,
            'officerValidationPending' => ! $user->isOfficerValidated(),
        ]);
    }

    public function storeAfterActivityReport(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $activeOfficer = $this->resolveActiveOfficer($request);
        $organization = $activeOfficer
            ? Organization::query()->find($activeOfficer->organization_id)
            : null;

        if (! $organization) {
            return redirect()
                ->route('organizations.manage')
                ->with('error', 'Your account is not linked to an active organization.');
        }

        if (! $user->isOfficerValidated()) {
            return redirect()
                ->route('organizations.after-activity-report')
                ->with('error', 'Your student officer account is pending SDAO validation.');
        }

        $validated = $request->validate([
            'proposal_id' => [
                'nullable',
                'integer',
                Rule::exists('activity_proposals', 'id')->where('organization_id', $organization->id),
            ],
            'activity_event_title' => ['required', 'string', 'max:255'],
            'school' => ['required', 'string', Rule::in(array_keys(self::SCHOOL_CODE_LABELS))],
            'department' => ['required', 'string', 'max:150'],
            'poster_image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'event_name' => ['required', 'string', 'max:255'],
            'event_starts_at' => ['required', 'date'],
            'activity_chairs' => ['required', 'string', 'max:500'],
            'prepared_by' => ['required', 'string', 'max:255'],
            'summary_description' => ['required', 'string'],
            'program_content' => ['required', 'string'],
            'photos' => ['nullable', 'array', 'max:15'],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'certificate_sample' => ['nullable', 'file', 'mimes:pdf,jpeg,jpg,png,webp,doc,docx', 'max:10240'],
            'evaluation_report' => ['required', 'string'],
            'participants_reached_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'evaluation_form_sample' => ['nullable', 'file', 'mimes:pdf,jpeg,jpg,png,webp,doc,docx', 'max:10240'],
            'attendance_sheet' => ['required', 'file', 'mimes:pdf,jpeg,jpg,png,webp,doc,docx', 'max:10240'],
        ]);

        $base = 'activity-reports/'.$organization->id.'/'.Str::uuid()->toString();
        $posterPath = $request->file('poster_image')->store($base, 'public');

        $photoPaths = [];
        $uploadedPhotos = $request->file('photos');
        $uploadedPhotos = is_array($uploadedPhotos) ? $uploadedPhotos : ($uploadedPhotos ? [$uploadedPhotos] : []);
        foreach ($uploadedPhotos as $photo) {
            if ($photo && $photo->isValid()) {
                $photoPaths[] = $photo->store($base.'/photos', 'public');
            }
        }

        $certificatePath = $request->hasFile('certificate_sample')
            ? $request->file('certificate_sample')->store($base, 'public')
            : null;

        $evaluationFormPath = $request->hasFile('evaluation_form_sample')
            ? $request->file('evaluation_form_sample')->store($base, 'public')
            : null;

        $attendancePath = $request->file('attendance_sheet')->store($base, 'public');

        ActivityReport::create([
            'proposal_id' => $validated['proposal_id'] ?? null,
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'report_submission_date' => now()->toDateString(),
            'report_file' => null,
            'accomplishment_summary' => $validated['summary_description'],
            'report_status' => 'PENDING',
            'activity_event_title' => $validated['activity_event_title'],
            'school_code' => $validated['school'],
            'department' => $validated['department'],
            'poster_image_path' => $posterPath,
            'event_name' => $validated['event_name'],
            'event_starts_at' => $validated['event_starts_at'],
            'activity_chairs' => $validated['activity_chairs'],
            'prepared_by' => $validated['prepared_by'],
            'program_content' => $validated['program_content'],
            'supporting_photo_paths' => $photoPaths === [] ? null : $photoPaths,
            'certificate_sample_path' => $certificatePath,
            'evaluation_report' => $validated['evaluation_report'],
            'participants_reached_percent' => $validated['participants_reached_percent'],
            'evaluation_form_sample_path' => $evaluationFormPath,
            'attendance_sheet_path' => $attendancePath,
        ]);

        return redirect()
            ->route('organizations.after-activity-report')
            ->with('success', 'After activity report submitted successfully.')
            ->with('after_activity_report_redirect_to', route('organizations.index'));
    }

    private function resolveActiveOfficer(Request $request): ?OrganizationOfficer
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $user->role_type !== 'ORG_OFFICER') {
            abort(403, 'Only organization officers can access this feature.');
        }

        return $user->organizationOfficers()
            ->where('officer_status', 'ACTIVE')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<int, string>  $allowedKeys
     * @return array<string, array<int, string>>
     */
    private function requirementFileRules(Request $request, array $allowedKeys): array
    {
        $selected = $request->input('requirements', []);
        if (! is_array($selected)) {
            $selected = [];
        }

        $rules = [];
        foreach ($allowedKeys as $key) {
            if (! in_array($key, $selected, true)) {
                continue;
            }
            $rules["requirement_files.$key"] = [
                'required',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png',
                'max:10240',
            ];
        }

        return $rules;
    }

    /**
     * @param  Collection<int, string>  $reqs
     * @param  array<int, string>  $allowedKeys
     * @return array<string, string>
     */
    private function storeRequirementFilesForKeys(
        Request $request,
        Collection $reqs,
        array $allowedKeys,
        string $storageBasePath
    ): array {
        $paths = [];
        foreach ($allowedKeys as $key) {
            if (! $reqs->contains($key)) {
                continue;
            }
            $file = $request->file("requirement_files.$key");
            if ($file && $file->isValid()) {
                $paths[$key] = $file->store($storageBasePath, 'public');
            }
        }

        return $paths;
    }
}
