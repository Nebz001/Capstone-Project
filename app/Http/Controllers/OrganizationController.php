<?php

namespace App\Http\Controllers;

use App\Models\ActivityCalendar;
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
     * Philippine mobile: 09XXXXXXXXX, +639…, 639…, or grouped with spaces/dashes.
     * Letters and invalid symbols are rejected; value is normalized after validation.
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
                    $fail('Contact number may not contain letters. Use digits only (e.g. 09XXXXXXXXX).');

                    return;
                }
                if (preg_match('/[^\d\s+\-().]/', $raw)) {
                    $fail('Contact number may only include digits and optional spaces, dashes, or parentheses.');

                    return;
                }
                $digits = preg_replace('/\D/', '', $raw);
                if ($digits === '') {
                    $fail('Enter a valid Philippine mobile number (e.g. 09XXXXXXXXX).');

                    return;
                }
                if (str_starts_with($digits, '63')) {
                    $digits = substr($digits, 2);
                }
                if (str_starts_with($digits, '0')) {
                    $digits = substr($digits, 1);
                }
                if (! preg_match('/^9\d{9}$/', $digits)) {
                    $fail('Enter a valid Philippine mobile number: 11 digits starting with 09 (e.g. 09XXXXXXXXX).');
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

    public function index()
    {
        return view('organizations.index');
    }

    public function manage()
    {
        return view('organizations.manage');
    }

    public function showSubmitReportHub()
    {
        return view('organizations.submit-report');
    }

    // ── Registration ────────────────────────────────────────────

    public function showRegistrationForm()
    {
        return view('organizations.register');
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
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['string', Rule::in(self::REGISTRATION_REQUIREMENT_KEYS)],
            'requirements_other' => [
                Rule::requiredIf(fn () => in_array('others', $request->input('requirements', []) ?? [], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'requirement_files' => ['nullable', 'array'],
        ], $this->requirementFileRules($request, self::REGISTRATION_REQUIREMENT_KEYS)));

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

        return view('organizations.renew', compact('organization', 'schoolCodeDefault'));
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
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['string', Rule::in(self::RENEWAL_REQUIREMENT_KEYS)],
            'requirements_other' => [
                Rule::requiredIf(fn () => in_array('others', $request->input('requirements', []) ?? [], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'requirement_files' => ['nullable', 'array'],
        ], $this->requirementFileRules($request, self::RENEWAL_REQUIREMENT_KEYS)));

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

        return view('organizations.profile', [
            'organization' => $organization,
            'editing' => (bool) ($request->query('edit') && $canEditProfile && $organization),
            'canEditProfile' => $organization ? $canEditProfile : false,
            'profileEditBlockedMessage' => $organization ? $profileEditBlockedMessage : '',
        ]);
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
            ->latest('submission_date')
            ->latest('id')
            ->first();

        return view('organizations.activity-calendar-submission', [
            'organization' => $organization,
            'latestCalendar' => $latestCalendar,
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

        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:50'],
            'semester' => ['required', 'string', 'max:50'],
            'calendar_file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
        ]);

        $hasPendingSubmission = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->where('academic_year', $validated['academic_year'])
            ->where('semester', $validated['semester'])
            ->where('calendar_status', 'PENDING')
            ->exists();

        if ($hasPendingSubmission) {
            return back()
                ->withErrors([
                    'academic_year' => 'A pending activity calendar already exists for this academic year and semester.',
                ])
                ->withInput();
        }

        $calendarFilePath = $request->file('calendar_file')->store(
            'activity-calendars/'.$organization->id,
            'public'
        );

        ActivityCalendar::create([
            'organization_id' => $organization->id,
            'academic_year' => $validated['academic_year'],
            'semester' => $validated['semester'],
            'calendar_file' => $calendarFilePath,
            'submission_date' => now()->toDateString(),
        ]);

        return redirect()
            ->route('organizations.activity-calendar-submission')
            ->with('success', 'Activity calendar submitted successfully.');
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
