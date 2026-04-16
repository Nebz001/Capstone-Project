<?php

namespace App\Http\Controllers;

use App\Models\ActivityCalendar;
use App\Models\ActivityCalendarEntry;
use App\Models\ActivityProposal;
use App\Models\ActivityRequestForm;
use App\Models\ActivityReport;
use App\Models\Organization;
use App\Models\OrganizationOfficer;
use App\Models\OrganizationRegistration;
use App\Models\OrganizationRenewal;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\SubmissionRoutingProgress;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
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

    /** Step 1 request form: nature of activity options. */
    private const ACTIVITY_REQUEST_NATURE_OPTIONS = [
        'co_curricular',
        'non_curricular',
        'community_extension',
        'others',
    ];

    /** Step 1 request form: activity type options. */
    private const ACTIVITY_REQUEST_TYPE_OPTIONS = [
        'seminar_workshop',
        'general_assembly',
        'orientation',
        'competition',
        'recruitment_audition',
        'donation_drive_fundraising',
        'outreach_donation',
        'fundraising_activity',
        'off_campus_activity',
        'others',
    ];

    /**
     * @return array<string, string>
     */
    public static function schoolCodeLabelMap(): array
    {
        return self::SCHOOL_CODE_LABELS;
    }

    /** Stored in `college_department` when organization type is extra-curricular (non-academic). */
    private const NON_ACADEMIC_DEPARTMENT_LABEL = 'Non-academic (Extra-Curricular)';

    private function activeAcademicYear(): string
    {
        return SystemSetting::activeAcademicYear();
    }

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

    /**
     * Match a submitted organization name to a registered organization (case-insensitive, trimmed).
     */
    public function resolveOrganizationByRegisteredName(string $name): ?Organization
    {
        $normalized = mb_strtolower(trim($name));

        if ($normalized === '') {
            return null;
        }

        return Organization::query()
            ->whereRaw('LOWER(TRIM(organization_name)) = ?', [$normalized])
            ->first();
    }

    public function index(Request $request)
    {
        if ($request->user()?->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $calendarEvents = [];
        $proposalDashboard = ['empty' => true];
        $user = $request->user();
        if ($user) {
            $organization = $user->currentOrganization();
            if ($organization) {
                $calendarEvents = $this->buildOrganizationDashboardCalendarEvents($organization);
                $featuredProposal = $this->resolveDashboardFeaturedProposal($organization);
                $proposalDashboard = $this->buildProposalDashboardProgress($featuredProposal);
            }
        }

        return view('organizations.index', [
            'calendarEvents' => $calendarEvents,
            'proposalDashboard' => $proposalDashboard,
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

    /**
     * Latest proposal the officer should monitor: prefer the most recently updated non-draft, else most recent draft.
     */
    private function resolveDashboardFeaturedProposal(Organization $organization): ?ActivityProposal
    {
        $base = ActivityProposal::query()->where('organization_id', $organization->id);

        $nonDraft = (clone $base)
            ->whereNotIn('proposal_status', ['DRAFT'])
            ->orderByDesc('updated_at')
            ->first();

        return $nonDraft ?? (clone $base)->orderByDesc('updated_at')->first();
    }

    /**
     * @return array{
     *     empty: bool,
     *     proposal?: ActivityProposal,
     *     status_raw?: string|null,
     *     status_label?: string,
     *     status_badge_class?: string,
     *     stages?: list<array{label: string, state: string}>,
     *     summary?: string,
     *     detail_href?: string|null,
     *     primary_action?: array{label: string, href: string}|null,
     *     secondary_action?: array{label: string, href: string}|null,
     *     meta?: list<array{label: string, value: string}>,
     *     subtitle?: string|null
     * }
     */
    private function buildProposalDashboardProgress(?ActivityProposal $proposal): array
    {
        if ($proposal === null) {
            return [
                'empty' => true,
                'primary_action' => [
                    'label' => 'Start activity proposal',
                    'href' => route('organizations.activity-proposal-request'),
                ],
                'secondary_action' => [
                    'label' => 'Activity submission hub',
                    'href' => route('organizations.activity-submission'),
                ],
            ];
        }

        $presentation = $this->activityProposalStatusPresentation($proposal->proposal_status);
        $summary = SubmissionRoutingProgress::summaryForActivityProposal($proposal->proposal_status);

        $stages = SubmissionRoutingProgress::stagesForActivityProposal($proposal);

        $statusUpper = strtoupper((string) ($proposal->proposal_status ?? ''));

        $primaryAction = null;
        if (in_array($statusUpper, ['REVISION', 'REVISION_REQUIRED'], true)) {
            $primaryAction = [
                'label' => 'Address revision',
                'href' => route('organizations.activity-proposal-submission'),
            ];
        } elseif ($statusUpper === 'DRAFT') {
            $primaryAction = [
                'label' => 'Continue proposal',
                'href' => route('organizations.activity-proposal-submission'),
            ];
        }

        $secondaryAction = [
            'label' => 'View full record',
            'href' => route('organizations.activity-submission.proposals.show', $proposal),
        ];

        $meta = array_values(array_filter([
            $proposal->academic_year ? [
                'label' => 'Academic year',
                'value' => 'AY '.$proposal->academic_year,
            ] : null,
            $proposal->submission_date ? [
                'label' => 'Submitted',
                'value' => $proposal->submission_date->format('M j, Y'),
            ] : null,
            [
                'label' => 'Last updated',
                'value' => optional($proposal->updated_at)->format('M j, Y \a\t g:i A') ?? '—',
            ],
        ]));

        return [
            'empty' => false,
            'proposal' => $proposal,
            'status_raw' => $proposal->proposal_status,
            'status_label' => $presentation['label'],
            'status_badge_class' => $presentation['badge_class'],
            'stages' => $stages,
            'summary' => $summary,
            'detail_href' => route('organizations.activity-submission.proposals.show', $proposal),
            'primary_action' => $primaryAction,
            'secondary_action' => $secondaryAction,
            'meta' => $meta,
            'subtitle' => $this->activityProposalDashboardSubtitle($proposal),
        ];
    }

    private function activityProposalDashboardSubtitle(ActivityProposal $proposal): ?string
    {
        if (! $proposal->proposed_start_date) {
            return null;
        }

        $line = 'Proposed '.$proposal->proposed_start_date->format('M j, Y');
        if ($proposal->proposed_end_date && $proposal->proposed_end_date->ne($proposal->proposed_start_date)) {
            $line .= ' – '.$proposal->proposed_end_date->format('M j, Y');
        }
        if ($proposal->venue) {
            $line .= ' · '.$proposal->venue;
        }

        return $line;
    }

    /**
     * @return array{label: string, badge_class: string}
     */
    private function activityProposalStatusPresentation(?string $raw): array
    {
        $u = strtoupper((string) $raw);

        return match ($u) {
            'DRAFT' => ['label' => 'Draft', 'badge_class' => 'bg-slate-200 text-slate-800 border border-slate-300'],
            'PENDING' => ['label' => 'Pending', 'badge_class' => 'bg-amber-100 text-amber-900 border border-amber-200'],
            'UNDER_REVIEW' => ['label' => 'Under review', 'badge_class' => 'bg-blue-100 text-blue-800 border border-blue-200'],
            'APPROVED' => ['label' => 'Approved / scheduled', 'badge_class' => 'bg-emerald-100 text-emerald-800 border border-emerald-200'],
            'REJECTED' => ['label' => 'Rejected', 'badge_class' => 'bg-rose-100 text-rose-800 border border-rose-200'],
            'REVISION', 'REVISION_REQUIRED' => ['label' => 'Returned for revision', 'badge_class' => 'bg-orange-100 text-orange-900 border border-orange-200'],
            default => ['label' => $raw ?: 'Unknown', 'badge_class' => 'bg-slate-100 text-slate-700 border border-slate-200'],
        };
    }

    public function manage(Request $request)
    {
        if ($request->user()?->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $renewalAccess = $this->renewalAccessForRso($request->user());

        return view('organizations.manage', [
            'renewalAccess' => $renewalAccess,
        ]);
    }

    public function showSubmitReportHub(Request $request)
    {
        if ($request->user()?->isSuperAdmin()) {
            return redirect()->route('admin.reports.index');
        }

        $organization = $this->resolveOrganizationForWorkflows($request);
        $reportStatusData = $organization ? $this->buildAfterActivityReportStatusData($organization) : null;

        return view('organizations.submit-report', [
            'reportStatusData' => $reportStatusData,
        ]);
    }

    public function showActivitySubmissionHub(Request $request)
    {
        if ($request->user()?->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('organizations.activity-submission');
    }

    // ── Registration ────────────────────────────────────────────

    public function showRegistrationForm(Request $request)
    {
        if ($request->user()?->isSuperAdmin()) {
            return redirect()->route('admin.submissions.register');
        }

        $user = $request->user();
        $officerValidationPending = $user && ! $user->isOfficerValidated();
        $alreadyLinkedToOrganization = $user && $user->currentOrganization() !== null;

        return view('organizations.register', compact('officerValidationPending', 'alreadyLinkedToOrganization'));
    }

    /**
     * SDAO admin registration: creates org + registration under the admin account without linking the admin as an organization officer.
     */
    public function storeRegistrationForAdmin(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! $user->isSdaoAdmin()) {
            abort(403, 'Only authorized SDAO admins can submit registration from the admin portal.');
        }

        return $this->processRegistrationSubmission($request, forAdmin: true);
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

        if ($user->isSuperAdmin()) {
            return redirect()
                ->route('admin.submissions.register')
                ->with('error', 'Use the Register Organization form in the admin portal.');
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

        return $this->processRegistrationSubmission($request, forAdmin: false);
    }

    private function processRegistrationSubmission(Request $request, bool $forAdmin): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return redirect()
                ->route('login')
                ->with('error', 'Please log in to submit organization registration.');
        }

        $request->merge(['academic_year' => $this->activeAcademicYear()]);

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

        $registration = null;

        DB::transaction(function () use ($validated, $reqs, $user, $request, $forAdmin, &$registration): void {
            $organization = Organization::create([
                'organization_name' => $validated['organization_name'],
                'organization_type' => $validated['organization_type'],
                'college_department' => $this->collegeDepartmentForOrganizationType($validated),
                'purpose' => $validated['purpose'],
                'founded_date' => $validated['date_organized'],
                'organization_status' => 'PENDING',
            ]);

            if (! $forAdmin) {
                OrganizationOfficer::create([
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'position_title' => 'President',
                    'officer_status' => 'ACTIVE',
                ]);
            }

            $filePaths = $this->storeRequirementFilesForKeys(
                $request,
                $reqs,
                self::REGISTRATION_REQUIREMENT_KEYS,
                "organization-requirements/{$organization->id}/registration"
            );

            $registration = OrganizationRegistration::create([
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

        if ($forAdmin) {
            return redirect()
                ->route('admin.registrations.show', $registration)
                ->with('success', 'Registration application submitted successfully. File under the submitting SDAO admin account.');
        }

        return redirect()
            ->route('organizations.register')
            ->with('success', 'Registration application submitted successfully.')
            ->with('registration_redirect_to', route('organizations.profile'));
    }

    // ── Renewal ─────────────────────────────────────────────────

    public function showRenewalForm(Request $request)
    {
        if ($request->user()?->isSuperAdmin()) {
            return redirect()->route('admin.submissions.renew');
        }

        $user = $request->user();
        $organization = $user?->currentOrganization();
        $schoolCodeDefault = null;
        if ($organization) {
            $schoolCodeDefault = $this->schoolCodeFromDepartment($organization->college_department);
        }

        $officerValidationPending = $user && ! $user->isOfficerValidated();
        $renewalAccess = $this->renewalAccessForRso($user);
        $renewalBlockedNoOrganization = $organization === null;

        return view('organizations.renew', compact(
            'organization',
            'schoolCodeDefault',
            'officerValidationPending',
            'renewalBlockedNoOrganization',
            'renewalAccess',
        ));
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

        $isAdminRenewal = $request->routeIs('admin.submissions.renew.store');

        if ($user->isSuperAdmin() && ! $isAdminRenewal) {
            return redirect()
                ->route('admin.submissions.renew')
                ->with('error', 'Use the Renew Organization form in the admin portal.');
        }

        if (! $user->isOfficerValidated()) {
            return back()->with('error', 'Your student officer account is pending SDAO validation.');
        }

        if (! $user->isSuperAdmin()) {
            $renewalAccess = $this->renewalAccessForRso($user);
            if (! $renewalAccess['allowed']) {
                return redirect()
                    ->route('organizations.renew')
                    ->with('error', $renewalAccess['message']);
            }
        }

        $request->merge(['academic_year' => $this->activeAcademicYear()]);

        $validated = $request->validate(array_merge([
            'organization_name' => ['required', 'string', 'max:150'],
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

        if ($user->isSuperAdmin()) {
            $organization = $this->resolveOrganizationByRegisteredName($validated['organization_name']);
            if (! $organization) {
                return back()
                    ->withErrors(['organization_name' => 'No registered organization matches this name.'])
                    ->withInput();
            }
        } else {
            $organization = $user->currentOrganization();
            if (! $organization) {
                return back()
                    ->with('error', 'No organization found for your account. Please register first.')
                    ->withInput();
            }
            if (mb_strtolower(trim($validated['organization_name'])) !== mb_strtolower(trim($organization->organization_name))) {
                return back()
                    ->withErrors(['organization_name' => 'Organization name must match your registered organization.'])
                    ->withInput();
            }
        }

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

        if ($isAdminRenewal) {
            return redirect()
                ->route('admin.renewals.show', $renewal)
                ->with('success', 'Renewal application submitted successfully. Recorded under your SDAO admin account.');
        }

        $renewProfileUrl = route('organizations.profile');
        $renewBackUrl = route('organizations.renew');

        return redirect()
            ->to($renewBackUrl)
            ->with('success', 'Renewal application submitted successfully.')
            ->with('renewal_redirect_to', $renewProfileUrl);
    }

    /**
     * @return array{allowed: bool, message: string, blocked_by_term: bool, blocked_by_existing_renewal: bool}
     */
    private function renewalAccessForRso(?User $user): array
    {
        $defaultBlocked = [
            'allowed' => false,
            'message' => 'Renew Organization is currently unavailable because your account is not linked to an active organization.',
            'blocked_by_term' => false,
            'blocked_by_existing_renewal' => false,
        ];

        if (! $user || $user->isSuperAdmin()) {
            return $defaultBlocked;
        }

        $organization = $user->currentOrganization();
        if (! $organization) {
            return $defaultBlocked;
        }

        $activeAcademicYear = $this->activeAcademicYear();
        $activeTerm = SystemSetting::activeSemester();
        if ($activeTerm !== 'term_1') {
            return [
                'allowed' => false,
                'message' => 'Renew Organization is only available during 1st Term. Renewal is closed for the current term.',
                'blocked_by_term' => true,
                'blocked_by_existing_renewal' => false,
            ];
        }

        $alreadyRenewedThisYear = OrganizationRenewal::query()
            ->where('organization_id', $organization->id)
            ->where('academic_year', $activeAcademicYear)
            ->exists();

        if ($alreadyRenewedThisYear) {
            return [
                'allowed' => false,
                'message' => 'Your organization has already submitted a renewal for '.$activeAcademicYear.'. Only one renewal is allowed per academic year.',
                'blocked_by_term' => false,
                'blocked_by_existing_renewal' => true,
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'blocked_by_term' => false,
            'blocked_by_existing_renewal' => false,
        ];
    }

    // ── Organization Profile ────────────────────────────────────

    public function profile(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $organization = $user->currentOrganization();

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

        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $organization = $user->currentOrganization();

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
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.submissions.activity-calendar');
        }

        $organization = $this->resolveOrganizationForWorkflows($request);

        if (! $organization) {
            return view('organizations.activity-calendar-submission', [
                'organization' => null,
                'latestCalendar' => null,
                'calendarSubmittedLocked' => false,
                // Reuse the existing blocked-message UI on the feature page.
                'officerValidationPending' => true,
                'blockedMessage' => 'Your account is not yet linked to an active organization. You cannot submit an activity calendar until your officer record is activated.',
            ]);
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
        $isAdminCalendar = $request->routeIs('admin.submissions.activity-calendar.store');

        if ($user->isSuperAdmin() && ! $isAdminCalendar) {
            return redirect()
                ->route('admin.submissions.activity-calendar')
                ->with('error', 'Use the Submit Activity Calendar form in the admin portal.');
        }

        if (! $user->isOfficerValidated()) {
            return redirect()
                ->route('organizations.activity-calendar-submission')
                ->with('error', 'Your student officer account is pending SDAO validation.');
        }

        if ($user->isSuperAdmin()) {
            $request->validate([
                'organization_name' => ['required', 'string', 'max:255'],
            ]);
            $organization = $this->resolveOrganizationByRegisteredName($request->string('organization_name')->toString());
            if (! $organization) {
                return back()
                    ->withErrors(['organization_name' => 'No registered organization matches this name.'])
                    ->withInput();
            }
        } else {
            $organization = $this->resolveOrganizationForWorkflows($request);
            if (! $organization) {
                return $this->redirectWhenNoOrganizationForWorkflow($request, 'organizations.profile');
            }
        }

        $latestLockedCalendar = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->latest('submission_date')
            ->latest('id')
            ->first();

        if ($latestLockedCalendar && strtoupper((string) $latestLockedCalendar->calendar_status) !== 'REVISION') {
            $calUrl = $isAdminCalendar
                ? route('admin.submissions.activity-calendar')
                : route('organizations.activity-calendar-submission');

            return redirect()
                ->to($calUrl)
                ->with('error', 'This activity calendar has already been submitted and can no longer be edited.');
        }

        if (! $request->filled('term')) {
            $request->merge(['term' => SystemSetting::activeSemester()]);
        }
        $request->merge(['academic_year' => $this->activeAcademicYear()]);

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

        if ($isAdminCalendar) {
            return redirect()
                ->route('admin.calendars.index')
                ->with('success', 'Activity calendar submitted successfully. Recorded under your SDAO admin account.');
        }

        return redirect()
            ->route('organizations.activity-calendar-submission')
            ->with('activity_calendar_submitted', true);
    }

    // ── Activity Proposal Submission ──────────────────────────

    public function showActivityProposalRequest(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.submissions.activity-proposal');
        }

        $organization = $this->resolveOrganizationForWorkflows($request);
        if (! $organization) {
            return view('organizations.activity-proposal-request', [
                'organization' => null,
                'officerValidationPending' => true,
                // Reuse the existing blocked-message UI on the feature page.
                'blockedMessage' => 'Your account is not yet linked to an active organization. You cannot submit proposals until your officer record is activated.',
                'natureOptions' => self::ACTIVITY_REQUEST_NATURE_OPTIONS,
                'typeOptions' => self::ACTIVITY_REQUEST_TYPE_OPTIONS,
                'requestForm' => null,
                'hasCalendarActivities' => false,
                'calendarEntries' => collect(),
            ]);
        }

        $requestForm = $this->resolveEditableActivityRequestForm($request, $organization);
        $hasCalendarActivities = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->whereHas('entries')
            ->exists();
        $latestCalendar = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->with([
                'entries' => fn ($q) => $q->orderBy('activity_date')->orderBy('id'),
            ])
            ->latest('submission_date')
            ->latest('id')
            ->first();
        $calendarEntries = $latestCalendar?->entries ?? collect();

        return view('organizations.activity-proposal-request', [
            'organization' => $organization,
            'officerValidationPending' => ! $user->isOfficerValidated(),
            'natureOptions' => self::ACTIVITY_REQUEST_NATURE_OPTIONS,
            'typeOptions' => self::ACTIVITY_REQUEST_TYPE_OPTIONS,
            'requestForm' => $requestForm,
            'hasCalendarActivities' => $hasCalendarActivities,
            'calendarEntries' => $calendarEntries,
        ]);
    }

    public function storeActivityProposalRequest(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.submissions.activity-proposal');
        }

        $organization = $this->resolveOrganizationForWorkflows($request);
        if (! $organization) {
            return $this->redirectWhenNoOrganizationForWorkflow($request, 'organizations.profile');
        }

        if (! $user->isOfficerValidated()) {
            return redirect()
                ->route('organizations.activity-proposal-request')
                ->with('error', 'Your student officer account is pending SDAO validation.');
        }

        $editableRequest = $this->resolveEditableActivityRequestForm($request, $organization);

        $validated = $request->validate([
            'request_id' => ['nullable', 'integer'],
            'proposal_source' => ['required', Rule::in(['calendar', 'unlisted'])],
            'activity_calendar_entry_id' => [
                Rule::requiredIf(fn () => (string) $request->input('proposal_source') === 'calendar'),
                'nullable',
                'integer',
            ],
            'rso_name' => ['required', 'string', 'max:255'],
            'activity_title' => ['required', 'string', 'max:255'],
            'partner_entities' => ['nullable', 'string', 'max:255'],
            'nature_of_activity' => ['required', 'array', 'size:1'],
            'nature_of_activity.*' => ['string', Rule::in(self::ACTIVITY_REQUEST_NATURE_OPTIONS)],
            'nature_other' => [
                Rule::requiredIf(fn () => in_array('others', (array) $request->input('nature_of_activity', []), true)),
                'nullable',
                'string',
                'max:255',
            ],
            'activity_types' => ['required', 'array', 'size:1'],
            'activity_types.*' => ['string', Rule::in(self::ACTIVITY_REQUEST_TYPE_OPTIONS)],
            'activity_type_other' => [
                Rule::requiredIf(fn () => in_array('others', (array) $request->input('activity_types', []), true)),
                'nullable',
                'string',
                'max:255',
            ],
            'target_sdg' => ['required', 'string', 'max:64'],
            'proposed_budget' => ['required', 'numeric', 'min:0'],
            'budget_source' => ['required', 'string', Rule::in(['RSO Fund', 'RSO Savings', 'External'])],
            'activity_date' => ['required', 'date'],
            'venue' => ['required', 'string', 'max:255'],
            'request_letter_has_rationale' => ['accepted'],
            'request_letter_has_objectives' => ['accepted'],
            'request_letter_has_program' => ['accepted'],
            'request_letter' => [
                Rule::requiredIf(fn () => ! $editableRequest || ! $editableRequest->request_letter_path),
                'nullable',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png,webp',
                'max:10240',
            ],
            'speaker_resume' => [
                Rule::requiredIf(fn () => in_array('seminar_workshop', (array) $request->input('activity_types', []), true)),
                'nullable',
                'file',
                'mimes:pdf,doc,docx',
                'max:10240',
            ],
            'post_survey_form' => [
                Rule::requiredIf(fn () => ! $editableRequest || ! $editableRequest->post_survey_form_path),
                'nullable',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png,webp',
                'max:10240',
            ],
        ], [
            'nature_of_activity.size' => 'Select exactly one Nature of Activity option.',
            'activity_types.size' => 'Select exactly one Type of Activity option.',
        ]);

        $selectedCalendarEntry = null;
        if ((string) $validated['proposal_source'] === 'calendar') {
            $selectedCalendarEntry = ActivityCalendarEntry::query()
                ->whereKey((int) ($validated['activity_calendar_entry_id'] ?? 0))
                ->whereHas('activityCalendar', function ($q) use ($organization): void {
                    $q->where('organization_id', $organization->id);
                })
                ->first();

            if (! $selectedCalendarEntry) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'activity_calendar_entry_id' => 'Select a valid activity from your submitted activity calendar.',
                    ]);
            }
        }

        $basePath = 'activity-request-forms/'.$organization->id.'/'.Str::uuid()->toString();
        $requestLetterPath = $editableRequest?->request_letter_path;
        if ($request->hasFile('request_letter')) {
            $requestLetterPath = $request->file('request_letter')->store($basePath, 'public');
        }

        $speakerResumePath = $editableRequest?->speaker_resume_path;
        if ($request->hasFile('speaker_resume')) {
            $speakerResumePath = $request->file('speaker_resume')->store($basePath, 'public');
        }
        if (! in_array('seminar_workshop', (array) $validated['activity_types'], true)) {
            $speakerResumePath = null;
        }

        $postSurveyPath = $editableRequest?->post_survey_form_path;
        if ($request->hasFile('post_survey_form')) {
            $postSurveyPath = $request->file('post_survey_form')->store($basePath, 'public');
        }

        $payload = [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'activity_calendar_entry_id' => $selectedCalendarEntry?->id,
            'rso_name' => $validated['rso_name'],
            'activity_title' => $validated['activity_title'],
            'partner_entities' => $validated['partner_entities'] ?? null,
            'nature_of_activity' => $validated['nature_of_activity'],
            'nature_other' => $validated['nature_other'] ?? null,
            'activity_types' => $validated['activity_types'],
            'activity_type_other' => $validated['activity_type_other'] ?? null,
            'target_sdg' => $validated['target_sdg'],
            'proposed_budget' => $validated['proposed_budget'],
            'budget_source' => $validated['budget_source'],
            'activity_date' => $validated['activity_date'],
            'venue' => $validated['venue'],
            'request_letter_has_rationale' => true,
            'request_letter_has_objectives' => true,
            'request_letter_has_program' => true,
            'request_letter_path' => $requestLetterPath,
            'speaker_resume_path' => $speakerResumePath,
            'post_survey_form_path' => $postSurveyPath,
            'used_for_proposal_at' => null,
        ];

        if ($editableRequest) {
            $editableRequest->update($payload);
            $activityRequest = $editableRequest;
        } else {
            $activityRequest = ActivityRequestForm::create($payload);
        }

        return redirect()
            ->route('organizations.activity-proposal-submission', [
                'request_id' => $activityRequest->id,
                'proposal_source' => $validated['proposal_source'],
                'calendar_entry' => $selectedCalendarEntry?->id,
            ])
            ->with('success', 'Step 1 complete. Continue to Step 2: Proposal Submission.');
    }

    public function showActivityProposalSubmission(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.submissions.activity-proposal');
        }

        $organization = $this->resolveOrganizationForWorkflows($request);

        if (! $organization) {
            return view('organizations.activity-proposal-submission', [
                'organization' => null,
                'officerValidationPending' => true,
                // Reuse the existing blocked-message UI on the feature page.
                'blockedMessage' => 'Your account is not yet linked to an active organization. You cannot submit proposals until your officer record is activated.',
                'proposalSource' => 'unlisted',
                'calendarEntry' => null,
                'linkedProposal' => null,
                'proposalCalendar' => null,
                'requestForm' => null,
            ]);
        }

        $requestForm = null;
        if (! $user->isSuperAdmin()) {
            $requestForm = $this->resolvePendingActivityRequestForm($request, $organization);
            if (! $requestForm) {
                return redirect()
                    ->route('organizations.activity-proposal-request')
                    ->with('error', 'Complete Step 1 (Activity Request Form) before you can continue to Proposal Submission.');
            }
            if (! $request->filled('calendar_entry') && $requestForm->activity_calendar_entry_id) {
                $request->merge([
                    'calendar_entry' => (string) $requestForm->activity_calendar_entry_id,
                ]);
            }
        }

        $proposalSource = $requestForm?->activity_calendar_entry_id ? 'calendar' : 'unlisted';

        $viewData = $this->activityProposalFormViewData($request, $organization, false, $requestForm);
        if (! $viewData instanceof RedirectResponse) {
            $viewData['proposalSource'] = $proposalSource;
        }

        return $viewData instanceof RedirectResponse
            ? $viewData
            : view('organizations.activity-proposal-submission', $viewData);
    }

    /**
     * Shared data for the activity proposal form (student portal or admin submission module).
     *
     * @return array<string, mixed>|RedirectResponse
     */
    public function activityProposalFormViewData(
        Request $request,
        Organization $organization,
        bool $forAdminPortal = false,
        ?ActivityRequestForm $requestForm = null
    )
    {
        /** @var User $user */
        $user = $request->user();

        $proposalIndexRoute = $forAdminPortal ? 'admin.submissions.activity-proposal' : 'organizations.activity-proposal-submission';
        $proposalShowRoute = $forAdminPortal ? 'admin.proposals.show' : 'organizations.activity-submission.proposals.show';

        $proposalIndexQuery = $forAdminPortal
            ? array_filter([
                'lookup_organization_name' => $request->query('lookup_organization_name') ?: $organization->organization_name,
            ], fn ($v) => $v !== null && $v !== '')
            : array_filter([
                'request_id' => $request->query('request_id'),
            ], fn ($v) => $v !== null && $v !== '');

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
                    ->route($proposalIndexRoute, $proposalIndexQuery)
                    ->with('error', 'That calendar activity was not found or does not belong to your organization.');
            }

            $linkedProposal = ActivityProposal::query()
                ->where('organization_id', $organization->id)
                ->where('activity_calendar_entry_id', $calendarEntry->id)
                ->first();

            if ($linkedProposal && ! in_array($linkedProposal->proposal_status, ['DRAFT', 'REVISION'], true)) {
                return redirect()
                    ->route($proposalShowRoute, $linkedProposal)
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

        $prefill = $this->buildActivityProposalFormPrefill($organization, $calendarEntry, $linkedProposal, $schoolPrefill, $requestForm);

        return [
            'organization' => $organization,
            'schoolOptions' => self::SCHOOL_CODE_LABELS,
            'schoolPrefill' => $schoolPrefill,
            'officerValidationPending' => ! $user->isOfficerValidated(),
            'requestForm' => $requestForm,
            'calendarEntry' => $calendarEntry,
            'linkedProposal' => $linkedProposal,
            'proposalCalendar' => $proposalCalendar,
            'prefill' => $prefill,
        ];
    }

    public function storeActivityProposalSubmission(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $isAdminProposal = $request->routeIs('admin.submissions.activity-proposal.store');

        if ($user->isSuperAdmin() && ! $isAdminProposal) {
            return redirect()
                ->route('admin.submissions.activity-proposal')
                ->with('error', 'Use the Submit Activity Proposal form in the admin portal.');
        }

        if ($user->isSuperAdmin()) {
            $request->validate([
                'organization_name' => ['required', 'string', 'max:255'],
            ]);
            $organization = $this->resolveOrganizationByRegisteredName($request->string('organization_name')->toString());
            if (! $organization) {
                return back()
                    ->withErrors(['organization_name' => 'No registered organization matches this name.'])
                    ->withInput();
            }
        } else {
            $organization = $this->resolveOrganizationForWorkflows($request);
            if (! $organization) {
                return $this->redirectWhenNoOrganizationForWorkflow($request, 'organizations.profile');
            }
        }

        if (! $user->isOfficerValidated()) {
            return redirect()
                ->route('organizations.activity-proposal-submission')
                ->with('error', 'Your student officer account is pending SDAO validation.');
        }

        if (! $isAdminProposal) {
            $requestForm = $this->resolvePendingActivityRequestForm($request, $organization);
            if (! $requestForm) {
                return redirect()
                    ->route('organizations.activity-proposal-request')
                    ->with('error', 'Complete Step 1 (Activity Request Form) before submitting a proposal.');
            }
        }

        $proposalSourceInput = (string) $request->input('proposal_source', 'calendar');

        $calendarEntry = null;
        if ($proposalSourceInput === 'calendar' && $request->filled('activity_calendar_entry_id')) {
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
                ->route($isAdminProposal ? 'admin.proposals.show' : 'organizations.activity-submission.proposals.show', $existing)
                ->with('error', 'This proposal can no longer be edited from the submission form. View it under Activity Submission.');
        }

        $proposalAction = (string) $request->input('proposal_action', 'submit');
        $asDraft = $proposalAction === 'draft';

        $request->merge(['academic_year' => $this->activeAcademicYear()]);

        $validated = $request->validate([
            'proposal_source' => ['required', Rule::in(['calendar', 'unlisted'])],
            'request_id' => ['nullable', 'integer'],
            'activity_calendar_entry_id' => [
                Rule::requiredIf(fn () => (string) $request->input('proposal_source') === 'calendar'),
                'nullable',
                'integer',
            ],
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
            'source_of_funding' => ['required', 'string', Rule::in(['RSO Fund', 'RSO Savings', 'External'])],
            'external_funding_support' => [
                Rule::requiredIf(fn () => ! $asDraft
                    && $request->input('source_of_funding') === 'External'
                    && ($existing === null || ! $existing->external_funding_support_path)),
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:10240',
            ],
            'budget_items_payload' => ['required', 'string'],
            'resume_resource_persons' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'proposal_action' => ['nullable', 'string', Rule::in(['draft', 'submit'])],
        ], [
            'proposed_end_date.after_or_equal' => 'The proposed end date must be on or after the start date.',
        ]);

        $budgetItems = json_decode((string) ($validated['budget_items_payload'] ?? '[]'), true);
        if (! is_array($budgetItems) || $budgetItems === []) {
            return back()
                ->withInput()
                ->withErrors([
                    'budget_breakdown' => 'Add at least one budget row in the detailed budget table.',
                ]);
        }

        $normalizedBudgetItems = [];
        foreach ($budgetItems as $idx => $item) {
            if (! is_array($item)) {
                return back()->withInput()->withErrors(['budget_breakdown' => 'Invalid budget row format.']);
            }

            $material = trim((string) ($item['material'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $price = (float) ($item['price'] ?? 0);

            if ($material === '' || $quantity <= 0 || $unitPrice < 0 || $price < 0) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'budget_breakdown' => 'Each budget row needs material, quantity, unit price, and price.',
                    ]);
            }

            $normalizedBudgetItems[] = [
                'line_no' => $idx + 1,
                'material' => $material,
                'quantity' => $quantity,
                'unit_price' => round($unitPrice, 2),
                'price' => round($price, 2),
            ];
        }

        $proposedTotal = round((float) $validated['proposed_budget'], 2);
        $expenseSum = round((float) collect($normalizedBudgetItems)->sum('price'), 2);
        if (abs($proposedTotal - $expenseSum) > 0.01) {
            return back()
                ->withInput()
                ->withErrors([
                    'budget_breakdown' => 'Proposed Budget (total) must equal the total of all detailed budget rows. Current total is '.number_format($proposedTotal, 2).' but the rows sum to '.number_format($expenseSum, 2).'.',
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
        if ($validated['source_of_funding'] !== 'External') {
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
            'budget_materials_supplies' => $validated['proposed_budget'],
            'budget_food_beverage' => 0,
            'budget_other_expenses' => 0,
            'budget_breakdown_items' => $normalizedBudgetItems,
            'resume_resource_persons_path' => $resumePath,
            'submission_date' => $submissionDate,
            'proposal_status' => $proposalStatus,
        ];

        if ($existing) {
            $existing->update($payload);
            $proposal = $existing;
        } else {
            $proposal = ActivityProposal::create($payload);
        }

        if (! $isAdminProposal && ! $asDraft) {
            $requestForm = $this->resolvePendingActivityRequestForm($request, $organization);
            if ($requestForm) {
                $requestForm->update([
                    'used_for_proposal_at' => now(),
                ]);
            }
        }

        $successMessage = $asDraft
            ? 'Proposal saved as draft. You can continue editing anytime.'
            : 'Activity proposal submitted successfully for SDAO review.';

        if ($isAdminProposal) {
            return redirect()
                ->route('admin.proposals.show', $proposal)
                ->with('success', $successMessage.' Recorded under your SDAO admin account.');
        }

        if ($calendarEntry) {
            if (! $asDraft) {
                return redirect()
                    ->route('organizations.activity-submission.proposals.show', $proposal)
                    ->with('success', $successMessage);
            }

            return redirect()
                ->route('organizations.activity-proposal-submission', [
                    'calendar_entry' => $calendarEntry->id,
                    'request_id' => (int) $request->integer('request_id'),
                    'proposal_source' => (string) $request->input('proposal_source', 'calendar'),
                ])
                ->with('success', $successMessage);
        }

        if (! $asDraft) {
            return redirect()
                ->route('organizations.activity-submission.proposals.show', $proposal)
                ->with('success', $successMessage);
        }

        return redirect()
            ->route('organizations.activity-proposal-submission', [
                'request_id' => (int) $request->integer('request_id'),
                'proposal_source' => (string) $request->input('proposal_source', 'calendar'),
            ])
            ->with('success', $successMessage);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildActivityProposalFormPrefill(
        Organization $organization,
        ?ActivityCalendarEntry $entry,
        ?ActivityProposal $linked,
        ?string $schoolPrefill,
        ?ActivityRequestForm $requestForm = null
    ): array {
        if ($linked !== null) {
            $time = $linked->proposed_time ?? '';
            if (is_string($time) && strlen($time) >= 8 && str_contains($time, ':')) {
                $time = substr($time, 0, 5);
            }

            return [
                'organization_name' => $linked->form_organization_name ?? $organization->organization_name,
                'academic_year' => $linked->academic_year ?? $this->activeAcademicYear(),
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
                'source_of_funding' => in_array((string) ($linked->source_of_funding ?? ''), ['RSO Fund', 'RSO Savings', 'External'], true)
                    ? (string) $linked->source_of_funding
                    : 'RSO Fund',
                'budget_items' => is_array($linked->budget_breakdown_items) ? $linked->budget_breakdown_items : [],
            ];
        }

        if ($entry !== null) {
            $cal = $entry->activityCalendar;
            $dateStr = optional($entry->activity_date)?->toDateString() ?? '';

            $prefill = [
                'organization_name' => $organization->organization_name,
                'academic_year' => $cal?->academic_year ?? $this->activeAcademicYear(),
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
                'source_of_funding' => 'RSO Fund',
                'budget_items' => [],
            ];

            return $this->overlayRequestFormPrefill($prefill, $requestForm);
        }

        $prefill = [
            'organization_name' => $organization->organization_name,
            'academic_year' => $this->activeAcademicYear(),
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
            'source_of_funding' => 'RSO Fund',
            'budget_items' => [],
        ];

        return $this->overlayRequestFormPrefill($prefill, $requestForm);
    }

    /**
     * @param  array<string, mixed>  $prefill
     * @return array<string, mixed>
     */
    private function overlayRequestFormPrefill(array $prefill, ?ActivityRequestForm $requestForm): array
    {
        if (! $requestForm) {
            return $prefill;
        }

        $prefill['organization_name'] = (string) ($requestForm->rso_name ?: $prefill['organization_name']);
        $prefill['project_activity_title'] = (string) ($requestForm->activity_title ?: $prefill['project_activity_title']);
        $prefill['proposed_start_date'] = $requestForm->activity_date?->toDateString() ?: $prefill['proposed_start_date'];
        $prefill['proposed_end_date'] = $requestForm->activity_date?->toDateString() ?: $prefill['proposed_end_date'];
        $prefill['venue'] = (string) ($requestForm->venue ?: $prefill['venue']);
        $prefill['proposed_budget'] = $requestForm->proposed_budget !== null
            ? (string) $requestForm->proposed_budget
            : $prefill['proposed_budget'];
        if (in_array((string) $requestForm->budget_source, ['RSO Fund', 'RSO Savings', 'External'], true)) {
            $prefill['source_of_funding'] = (string) $requestForm->budget_source;
        }

        return $prefill;
    }

    // ── After Activity Report ─────────────────────────────────

    public function showAfterActivityReportForm(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.reports.index');
        }

        $organization = $this->resolveOrganizationForWorkflows($request);

        if (! $organization) {
            return view('organizations.after-activity-report', [
                'organization' => null,
                'schoolOptions' => self::SCHOOL_CODE_LABELS,
                'reportStatusData' => [
                    'activities' => [],
                    'eligibleProposals' => [],
                    'dueReminders' => [],
                    'hasAnyTrackedActivity' => false,
                    'hasEligibleProposal' => false,
                    'blockedMessage' => 'Your account is not yet linked to an active organization. You cannot submit activity reports until your officer record is activated.',
                ],
                'prefillPreparedBy' => $user->full_name,
                'officerValidationPending' => false,
            ]);
        }

        $reportStatusData = $this->buildAfterActivityReportStatusData($organization);

        return view('organizations.after-activity-report', [
            'organization' => $organization,
            'schoolOptions' => self::SCHOOL_CODE_LABELS,
            'reportStatusData' => $reportStatusData,
            'prefillPreparedBy' => $user->full_name,
            'officerValidationPending' => ! $user->isOfficerValidated(),
        ]);
    }

    public function storeAfterActivityReport(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return redirect()
                ->route('admin.reports.index')
                ->with('error', 'After-activity reports are submitted by student officers from the organization portal.');
        }

        $organization = $this->resolveOrganizationForWorkflows($request);

        if (! $organization) {
            return redirect()
                ->route('organizations.after-activity-report')
                ->with('error', 'Your account is not linked to an active organization.');
        }

        if (! $user->isOfficerValidated()) {
            return redirect()
                ->route('organizations.after-activity-report')
                ->with('error', 'Your student officer account is pending SDAO validation.');
        }

        $reportStatusData = $this->buildAfterActivityReportStatusData($organization);
        $eligibleProposalIds = collect($reportStatusData['eligibleProposals'])
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($eligibleProposalIds === []) {
            return redirect()
                ->route('organizations.after-activity-report')
                ->with('error', 'No completed and approved activity is currently eligible for an after activity report.');
        }

        $validated = $request->validate([
            'proposal_id' => [
                'required',
                'integer',
                Rule::in($eligibleProposalIds),
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
            'proposal_id' => $validated['proposal_id'],
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

    /**
     * @return array{
     *     activities: list<array<string, mixed>>,
     *     eligibleProposals: list<array<string, mixed>>,
     *     dueReminders: list<array<string, mixed>>,
     *     hasAnyTrackedActivity: bool,
     *     hasEligibleProposal: bool,
     *     blockedMessage: string
     * }
     */
    private function buildAfterActivityReportStatusData(Organization $organization): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        $proposals = $organization->activityProposals()
            ->whereNotIn('proposal_status', ['DRAFT'])
            ->with('activityReport')
            ->orderByDesc('proposed_start_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $activities = [];
        $eligible = [];
        $dueReminders = [];

        foreach ($proposals as $proposal) {
            $proposalStatus = strtoupper((string) ($proposal->proposal_status ?? 'PENDING'));

            $startDate = $proposal->proposed_start_date?->copy()->startOfDay();
            $endDate = ($proposal->proposed_end_date ?? $proposal->proposed_start_date)?->copy()->startOfDay();
            $reportDeadline = $endDate?->copy()->addWeek();

            $lifecycleStatus = 'pending';
            $lifecycleLabel = 'Pending';

            if ($proposalStatus === 'APPROVED' && $startDate && $endDate) {
                if ($today->lt($startDate)) {
                    $lifecycleStatus = 'pending';
                    $lifecycleLabel = 'Pending';
                } elseif ($today->gt($endDate)) {
                    $lifecycleStatus = 'completed';
                    $lifecycleLabel = 'Completed / Done';
                } else {
                    $lifecycleStatus = 'in_progress';
                    $lifecycleLabel = 'In Progress';
                }
            }

            $hasReport = $proposal->activityReport !== null;
            $canSubmit = $proposalStatus === 'APPROVED'
                && $lifecycleStatus === 'completed'
                && ! $hasReport;

            $deadlineMeta = null;
            if ($canSubmit && $reportDeadline) {
                $daysRemaining = (int) $today->diffInDays($reportDeadline, false);
                $deadlineMeta = [
                    'date' => $reportDeadline,
                    'days_remaining' => $daysRemaining,
                    'is_overdue' => $daysRemaining < 0,
                    'is_due_soon' => $daysRemaining >= 0 && $daysRemaining <= 2,
                ];

                $dueReminders[] = [
                    'proposal_id' => $proposal->id,
                    'activity_title' => (string) $proposal->activity_title,
                    'deadline' => $reportDeadline,
                    'days_remaining' => $daysRemaining,
                ];
            }

            $readinessReason = match (true) {
                $proposalStatus !== 'APPROVED' => 'Proposal must be approved before reporting is allowed.',
                ! $startDate || ! $endDate => 'Activity dates are incomplete. Please update proposal schedule first.',
                $lifecycleStatus === 'pending' => 'Activity has not started yet. Report opens after completion.',
                $lifecycleStatus === 'in_progress' => 'Activity is still in progress. Submit report once completed.',
                $hasReport => 'Report already submitted for this activity.',
                default => 'Ready for report submission.',
            };

            $row = [
                'id' => $proposal->id,
                'activity_title' => (string) $proposal->activity_title,
                'proposal_status' => $proposalStatus,
                'proposal_status_label' => $this->activityProposalStatusPresentation($proposal->proposal_status)['label'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'lifecycle_status' => $lifecycleStatus,
                'lifecycle_label' => $lifecycleLabel,
                'has_report' => $hasReport,
                'can_submit' => $canSubmit,
                'readiness_reason' => $readinessReason,
                'deadline' => $deadlineMeta,
            ];

            $activities[] = $row;
            if ($canSubmit) {
                $eligible[] = $row;
            }
        }

        $hasAnyTrackedActivity = count($activities) > 0;
        $hasEligibleProposal = count($eligible) > 0;
        $blockedMessage = $hasAnyTrackedActivity
            ? 'No completed approved activities are currently eligible for after activity reporting.'
            : 'No submitted activity proposals are available yet for after activity reporting.';

        return [
            'activities' => $activities,
            'eligibleProposals' => $eligible,
            'dueReminders' => $dueReminders,
            'hasAnyTrackedActivity' => $hasAnyTrackedActivity,
            'hasEligibleProposal' => $hasEligibleProposal,
            'blockedMessage' => $blockedMessage,
        ];
    }

    private function redirectWhenNoOrganizationForWorkflow(Request $request, string $studentFallbackRoute): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->isSuperAdmin()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', 'Use the admin submission forms or the student officer portal with an organization-linked account.');
        }

        return redirect()
            ->route($studentFallbackRoute)
            ->with('error', 'Your account is not linked to an active organization.');
    }

    /**
     * Organization for calendar, proposals, reports, etc. (student officers only).
     */
    private function resolveOrganizationForWorkflows(Request $request): ?Organization
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $officer = $this->resolveActiveOfficer($request);
        if (! $officer) {
            return null;
        }

        return Organization::query()->find($officer->organization_id);
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

    private function resolvePendingActivityRequestForm(Request $request, Organization $organization): ?ActivityRequestForm
    {
        $requestId = (int) $request->integer('request_id');
        if ($requestId <= 0) {
            return null;
        }

        return ActivityRequestForm::query()
            ->whereKey($requestId)
            ->where('organization_id', $organization->id)
            ->whereNull('used_for_proposal_at')
            ->first();
    }

    private function resolveEditableActivityRequestForm(Request $request, Organization $organization): ?ActivityRequestForm
    {
        $requestId = (int) $request->integer('request_id');
        if ($requestId <= 0) {
            return null;
        }

        return ActivityRequestForm::query()
            ->whereKey($requestId)
            ->where('organization_id', $organization->id)
            ->whereNull('used_for_proposal_at')
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
