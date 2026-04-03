<?php

namespace App\Http\Controllers;

use App\Models\ActivityCalendar;
use App\Models\Organization;
use App\Models\OrganizationOfficer;
use App\Models\OrganizationRegistration;
use App\Models\OrganizationRenewal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

  public function index()
  {
    return view('organizations.index');
  }

  public function manage()
  {
    return view('organizations.manage');
  }

  // ── Registration ────────────────────────────────────────────

  public function showRegistrationForm()
  {
    return view('organizations.register');
  }

  public function storeRegistration(Request $request)
  {
    /** @var \App\Models\User|null $user */
    $user = $request->user();

    if (!$user) {
      return redirect()
        ->route('login')
        ->with('error', 'Please log in to submit organization registration.');
    }

    if ($user->role_type !== 'ORG_OFFICER') {
      abort(403, 'Only organization officers can submit organization registration.');
    }

    if (!$user->isOfficerValidated()) {
      return back()->with('error', 'Your student officer account is pending SDAO validation.');
    }

    if ($user->currentOrganization()) {
      return back()
        ->with('error', 'Your account is already linked to an organization.')
        ->withInput();
    }

    $validated = $request->validate(array_merge([
      'organization_name'  => ['required', 'string', 'max:150'],
      'contact_person'     => ['required', 'string', 'max:255'],
      'contact_no'         => ['required', 'string', 'max:20'],
      'email_address'      => ['required', 'email', 'max:150'],
      'academic_year'      => ['required', 'string', 'max:20'],
      'date_organized'     => ['required', 'date'],
      'organization_type'  => ['required', 'in:co_curricular,extra_curricular'],
      'purpose'            => ['required', 'string'],
      'college'            => ['required', 'string', 'max:100'],
      'requirements'       => ['nullable', 'array'],
      'requirements.*'     => ['string', Rule::in(self::REGISTRATION_REQUIREMENT_KEYS)],
      'requirements_other' => [
        Rule::requiredIf(fn () => in_array('others', $request->input('requirements', []) ?? [], true)),
        'nullable',
        'string',
        'max:255',
      ],
      'requirement_files'   => ['nullable', 'array'],
    ], $this->requirementFileRules($request, self::REGISTRATION_REQUIREMENT_KEYS)));

    $reqs = collect($validated['requirements'] ?? []);

    DB::transaction(function () use ($validated, $reqs, $user, $request): void {
      $organization = Organization::create([
        'organization_name'   => $validated['organization_name'],
        'organization_type'   => $validated['organization_type'],
        'college_department'  => $validated['college'],
        'purpose'             => $validated['purpose'],
        'founded_date'        => $validated['date_organized'],
        'organization_status' => 'PENDING',
      ]);

      OrganizationOfficer::create([
        'organization_id' => $organization->id,
        'user_id'         => $user->id,
        'position_title'  => 'President',
        'officer_status'  => 'ACTIVE',
      ]);

      $filePaths = $this->storeRequirementFilesForKeys(
        $request,
        $reqs,
        self::REGISTRATION_REQUIREMENT_KEYS,
        "organization-requirements/{$organization->id}/registration"
      );

      OrganizationRegistration::create([
        'organization_id'      => $organization->id,
        'user_id'              => $user->id,
        'academic_year'        => $validated['academic_year'],
        'contact_person'       => $validated['contact_person'],
        'contact_no'           => $validated['contact_no'],
        'contact_email'        => $validated['email_address'],
        'submission_date'      => now()->toDateString(),
        'req_letter_of_intent' => $reqs->contains('letter_of_intent'),
        'req_application_form' => $reqs->contains('application_form'),
        'req_by_laws'          => $reqs->contains('by_laws'),
        'req_officers_list'    => $reqs->contains('updated_list_of_officers_founders'),
        'req_dean_endorsement' => $reqs->contains('dean_endorsement_faculty_adviser'),
        'req_proposed_projects' => $reqs->contains('proposed_projects_budget'),
        'req_others'           => $reqs->contains('others'),
        'req_others_specify'   => $validated['requirements_other'] ?? null,
        'requirement_files'    => $filePaths === [] ? null : $filePaths,
      ]);
    });

    return redirect()
      ->route('organizations.register')
      ->with('success', 'Registration application submitted successfully.')
      ->with('registration_redirect_to', route('organizations.profile'));
  }

  // ── Renewal ─────────────────────────────────────────────────

  public function showRenewalForm()
  {
    return view('organizations.renew');
  }

  public function storeRenewal(Request $request)
  {
    /** @var \App\Models\User|null $user */
    $user = $request->user();

    if (!$user) {
      return redirect()
        ->route('login')
        ->with('error', 'Please log in to submit a renewal application.');
    }

    $organization = $user->currentOrganization();

    if (!$user->isOfficerValidated()) {
      return back()->with('error', 'Your student officer account is pending SDAO validation.');
    }

    if (!$organization) {
      return back()
        ->with('error', 'No organization found for your account. Please register first.')
        ->withInput();
    }

    $validated = $request->validate(array_merge([
      'contact_person'     => ['required', 'string', 'max:255'],
      'contact_no'         => ['required', 'string', 'max:20'],
      'email_address'      => ['required', 'email', 'max:150'],
      'academic_year'      => ['required', 'string', 'max:20'],
      'purpose'            => ['required', 'string'],
      'requirements'       => ['nullable', 'array'],
      'requirements.*'     => ['string', Rule::in(self::RENEWAL_REQUIREMENT_KEYS)],
      'requirements_other' => [
        Rule::requiredIf(fn () => in_array('others', $request->input('requirements', []) ?? [], true)),
        'nullable',
        'string',
        'max:255',
      ],
      'requirement_files'   => ['nullable', 'array'],
    ], $this->requirementFileRules($request, self::RENEWAL_REQUIREMENT_KEYS)));

    $reqs = collect($validated['requirements'] ?? []);

    $organization->update(['purpose' => $validated['purpose']]);

    $renewal = OrganizationRenewal::create([
      'organization_id'       => $organization->id,
      'user_id'               => $user->id,
      'academic_year'         => $validated['academic_year'],
      'contact_person'        => $validated['contact_person'],
      'contact_no'            => $validated['contact_no'],
      'contact_email'         => $validated['email_address'],
      'submission_date'       => now()->toDateString(),
      'req_letter_of_intent'  => $reqs->contains('letter_of_intent'),
      'req_application_form'  => $reqs->contains('application_form'),
      'req_by_laws'           => $reqs->contains('by_laws_updated_if_applicable'),
      'req_officers_list'     => $reqs->contains('updated_list_of_officers_founders_ay'),
      'req_dean_endorsement'  => $reqs->contains('dean_endorsement_faculty_adviser'),
      'req_proposed_projects' => $reqs->contains('proposed_projects_budget'),
      'req_past_projects'     => $reqs->contains('past_projects'),
      'req_financial_statement' => $reqs->contains('financial_statement_previous_ay'),
      'req_evaluation_summary' => $reqs->contains('evaluation_summary_past_projects'),
      'req_others'            => $reqs->contains('others'),
      'req_others_specify'    => $validated['requirements_other'] ?? null,
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
      ->route('organizations.profile')
      ->with('success', 'Renewal application submitted successfully.');
  }

  // ── Organization Profile ────────────────────────────────────

  public function profile(Request $request)
  {
    /** @var \App\Models\User $user */
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
      'organization'              => $organization,
      'editing'                   => (bool) ($request->query('edit') && $canEditProfile && $organization),
      'canEditProfile'            => $organization ? $canEditProfile : false,
      'profileEditBlockedMessage' => $organization ? $profileEditBlockedMessage : '',
    ]);
  }

  public function updateProfile(Request $request)
  {
    /** @var \App\Models\User $user */
    $user = $request->user();
    $organization = $user?->currentOrganization();

    if (!$organization) {
      return back()->with('error', 'No organization found for your account.');
    }

    if (! $organization->canEditProfile()) {
      return redirect()
        ->route('organizations.profile')
        ->with('error', $organization->profileEditBlockedMessage());
    }

    $validated = $request->validate([
      'organization_name'  => ['required', 'string', 'max:150'],
      'organization_type'  => ['required', 'string', 'max:50'],
      'college_department' => ['required', 'string', 'max:100'],
      'purpose'            => ['required', 'string'],
      'adviser_name'       => ['nullable', 'string', 'max:100'],
      'founded_date'       => ['nullable', 'date'],
    ]);

    $organization->update(array_merge($validated, [
      'profile_information_revision_requested' => false,
      'profile_revision_notes'                 => null,
    ]));

    return redirect()
      ->route('organizations.profile')
      ->with('success', 'Organization profile updated successfully.');
  }

  // ── Activity Calendar Submission ─────────────────────────

  public function showActivityCalendarSubmission(Request $request)
  {
    /** @var \App\Models\User $user */
    $user = $request->user();
    $activeOfficer = $this->resolveActiveOfficer($request);
    $organization = $activeOfficer
      ? Organization::query()->find($activeOfficer->organization_id)
      : null;

    if (!$organization) {
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
    /** @var \App\Models\User $user */
    $user = $request->user();
    $activeOfficer = $this->resolveActiveOfficer($request);
    $organization = $activeOfficer
      ? Organization::query()->find($activeOfficer->organization_id)
      : null;

    if (!$organization) {
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
      'activity-calendars/' . $organization->id,
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

  private function resolveActiveOfficer(Request $request): ?OrganizationOfficer
  {
    /** @var \App\Models\User|null $user */
    $user = $request->user();

    if (!$user || $user->role_type !== 'ORG_OFFICER') {
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
   * @param  \Illuminate\Support\Collection<int, string>  $reqs
   * @param  array<int, string>  $allowedKeys
   * @return array<string, string>
   */
  private function storeRequirementFilesForKeys(
    Request $request,
    \Illuminate\Support\Collection $reqs,
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
