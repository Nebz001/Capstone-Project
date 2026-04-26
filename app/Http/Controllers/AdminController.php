<?php

namespace App\Http\Controllers;

use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\ActivityReport;
use App\Models\ApprovalLog;
use App\Models\ApprovalWorkflowStep;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\OrganizationProfileRevision;
use App\Models\OrganizationSubmission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
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
    private function initialRegistrationSectionReviewState(OrganizationSubmission $submission): array
    {
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

        return view('admin.review-index', [
            'pageTitle' => 'Registration Review',
            'pageSubtitle' => 'Monitor submitted RSO registration applications.',
            'routeBase' => 'admin.registrations.show',
            'rows' => $records->through(function (OrganizationSubmission $record): array {
                return [
                    'organization' => $record->organization?->organization_name ?? 'N/A',
                    'submitted_by' => $record->submittedBy?->full_name ?? 'N/A',
                    'submission_date' => optional($record->submission_date)->format('M d, Y') ?? 'N/A',
                    'status' => $record->legacyStatus(),
                    'id' => $record->id,
                ];
            }),
        ]);
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

        return view('admin.review-index', [
            'pageTitle' => 'Renewal Review',
            'pageSubtitle' => 'Monitor submitted RSO renewal applications.',
            'routeBase' => 'admin.renewals.show',
            'rows' => $records->through(function (OrganizationSubmission $record): array {
                return [
                    'organization' => $record->organization?->organization_name ?? 'N/A',
                    'submitted_by' => $record->submittedBy?->full_name ?? 'N/A',
                    'submission_date' => optional($record->submission_date)->format('M d, Y') ?? 'N/A',
                    'status' => $record->legacyStatus(),
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
        $submission->load(['organization', 'submittedBy', 'academicTerm', 'requirements', 'attachments']);
        return view('admin.registrations.show', [
            'registration' => $submission,
            'submission' => $submission,
            'initialSectionReviewState' => $this->initialRegistrationSectionReviewState($submission),
        ]);
    }

    /**
     * Stream a registration requirement file from the public disk (auth + path scoped to this org).
     * Avoids relying on the public/storage symlink, which often causes 404s when the link is missing.
     */
    public function showRegistrationRequirementFile(Request $request, OrganizationSubmission $submission, string $key): StreamedResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);

        if (! in_array($key, self::REGISTRATION_REQUIREMENT_FILE_KEYS, true)) {
            abort(404);
        }

        $attachment = $submission->attachments()
            ->where('file_type', Attachment::TYPE_REGISTRATION_REQUIREMENT.':'.$key)
            ->latest('id')
            ->first();
        if (! $attachment) {
            abort(404);
        }
        $relativePath = (string) $attachment->stored_path;

        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            abort(404);
        }

        $filename = basename($relativePath);

        return $disk->response($relativePath, $filename, [], 'inline');
    }

    public function updateRegistrationStatus(Request $request, OrganizationSubmission $submission): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);

        $validator = Validator::make($request->all(), [
            'decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])],
            'remarks' => [
                Rule::when(
                    $request->input('decision') === 'REJECTED',
                    ['required', 'string', 'min:3', 'max:5000'],
                    ['nullable', 'string', 'max:5000'],
                ),
            ],
        ]);

        $validated = $validator->validate();

        /** @var User $admin */
        $admin = $request->user();
        $decision = $validated['decision'];

        $remarks = trim((string) ($validated['remarks'] ?? ''));
        DB::transaction(function () use ($submission, $decision, $remarks, $admin): void {
            $submission->update([
                'status' => match ($decision) {
                    'APPROVED' => OrganizationSubmission::STATUS_APPROVED,
                    'REJECTED' => OrganizationSubmission::STATUS_REJECTED,
                    default => OrganizationSubmission::STATUS_PENDING,
                },
                'approval_decision' => $decision === 'APPROVED' ? 'approved' : null,
                'additional_remarks' => $remarks !== '' ? $remarks : null,
                'notes' => $remarks !== '' ? $remarks : $submission->notes,
                'current_approval_step' => 0,
            ]);

            if ($decision === 'APPROVED') {
                $submission->organization?->update(['status' => 'active']);
            }

            if ($decision === 'REJECTED' && $remarks !== '') {
                OrganizationProfileRevision::query()->create([
                    'organization_id' => $submission->organization_id,
                    'requested_by' => $admin->id,
                    'revision_notes' => $remarks,
                    'status' => 'open',
                ]);
            }
        });

        return redirect()
            ->route('admin.registrations.show', $submission)
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

    public function showRenewal(Request $request, OrganizationSubmission $submission): View
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_RENEWAL, 404);

        $submission->load(['organization', 'submittedBy', 'academicTerm']);

        return view('admin.review-show', [
            'pageTitle' => 'Renewal Submission Details',
            'backRoute' => route('admin.renewals.index'),
            'status' => $submission->legacyStatus(),
            'details' => [
                'Organization' => $submission->organization?->organization_name ?? 'N/A',
                'Submitted By' => $submission->submittedBy?->full_name ?? 'N/A',
                'Academic Year' => $submission->academicTerm?->academic_year ?? 'N/A',
                'Contact Person' => $submission->contact_person ?? 'N/A',
                'Submission Date' => optional($submission->submission_date)->format('M d, Y') ?? 'N/A',
                'Contact Email' => $submission->contact_email ?? 'N/A',
            ],
            'organization' => $submission->organization,
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
            'status' => strtoupper((string) ($calendar->status ?? 'pending')),
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

        $proposal->load(['organization', 'user', 'submittedBy', 'academicTerm', 'budgetItems', 'workflowSteps.role', 'approvalLogs.actor']);
        $this->ensureProposalWorkflow($proposal);
        $proposal->load(['workflowSteps.role', 'approvalLogs.actor']);

        $schoolLabels = [
            'sace' => 'School of Architecture, Computer and Engineering',
            'sahs' => 'School of Allied Health and Sciences',
            'sabm' => 'School of Accounting and Business Management',
            'shs' => 'Senior High School',
        ];

        $logoPath = $this->attachmentPathOrLegacy($proposal, Attachment::TYPE_PROPOSAL_LOGO, $proposal->organization_logo_path);
        $logoUrl = $logoPath
            ? Storage::disk('public')->url($logoPath)
            : null;
        $resumePath = $this->attachmentPathOrLegacy($proposal, Attachment::TYPE_PROPOSAL_RESOURCE_RESUME, $proposal->resume_resource_persons_path);
        $resumeUrl = $resumePath
            ? Storage::disk('public')->url($resumePath)
            : null;
        $externalPath = $this->attachmentPathOrLegacy($proposal, Attachment::TYPE_PROPOSAL_EXTERNAL_FUNDING, $proposal->external_funding_support_path);
        $externalUrl = $externalPath ? Storage::disk('public')->url($externalPath) : null;
        $proposalTime = $proposal->proposed_start_time
            ? trim($proposal->proposed_start_time.($proposal->proposed_end_time ? ' - '.$proposal->proposed_end_time : ''))
            : ($proposal->proposed_time ?? 'N/A');
        $budgetRows = $proposal->budgetItems;
        $budgetRowsCount = $budgetRows->count();
        $budgetRowsTotal = $budgetRowsCount > 0
            ? number_format((float) $budgetRows->sum('total_cost'), 2)
            : 'N/A';

        $details = [
            'Organization (profile)' => $proposal->organization?->organization_name ?? 'N/A',
            'Submitted By' => $proposal->submittedBy?->full_name ?? $proposal->user?->full_name ?? 'N/A',
            'Submission Date' => optional($proposal->submission_date)->format('M d, Y') ?? 'N/A',
            'Organization name (form)' => $proposal->form_organization_name ?? 'N/A',
            'Academic Year' => $proposal->academicTerm?->academic_year ?? $proposal->academic_year ?? 'N/A',
            'School' => $proposal->school_code ? ($schoolLabels[$proposal->school_code] ?? $proposal->school_code) : 'N/A',
            'Department / Program' => $proposal->department_program ?? 'N/A',
            'Organization logo' => $logoUrl ?? 'N/A',
            'Activity Title' => $proposal->activity_title ?? 'N/A',
            'Proposed Start' => optional($proposal->proposed_start_date)->format('M d, Y') ?? 'N/A',
            'Proposed End' => optional($proposal->proposed_end_date)->format('M d, Y') ?? 'N/A',
            'Proposed Time' => $proposalTime,
            'Venue' => $proposal->venue ?? 'N/A',
            'Overall Goal' => $proposal->overall_goal ?? ($proposal->activity_description ?? 'N/A'),
            'Specific Objectives' => $proposal->specific_objectives ?? 'N/A',
            'Criteria / Mechanics' => $proposal->criteria_mechanics ?? 'N/A',
            'Program Flow' => $proposal->program_flow ?? 'N/A',
            'Proposed Budget (total)' => $proposal->estimated_budget !== null ? number_format((float) $proposal->estimated_budget, 2) : 'N/A',
            'Budget Items Rows' => $budgetRowsCount > 0 ? (string) $budgetRowsCount : 'N/A',
            'Budget Items Total' => $budgetRowsTotal,
            'Source of Funding' => $proposal->source_of_funding ?? 'N/A',
            'Materials and Supplies' => $proposal->budget_materials_supplies !== null ? number_format((float) $proposal->budget_materials_supplies, 2) : 'N/A',
            'Food and Beverage' => $proposal->budget_food_beverage !== null ? number_format((float) $proposal->budget_food_beverage, 2) : 'N/A',
            'Other Expenses' => $proposal->budget_other_expenses !== null ? number_format((float) $proposal->budget_other_expenses, 2) : 'N/A',
            'Resume (resource persons)' => $resumeUrl ?? 'N/A',
            'External funding support' => $externalUrl ?? 'N/A',
        ];

        return view('admin.review-show', [
            'pageTitle' => 'Activity Proposal Submission Details',
            'backRoute' => route('admin.proposals.index'),
            'status' => strtoupper((string) ($proposal->status ?? 'pending')),
            'details' => $details,
            'organization' => $proposal->organization,
            'workflowSteps' => $proposal->workflowSteps,
            'workflowLogs' => $proposal->approvalLogs()->latest('created_at')->limit(15)->get(),
            'workflowActionRoute' => route('admin.proposals.workflow', $proposal),
            'workflowCurrentStep' => $proposal->workflowSteps->firstWhere('is_current_step', true),
        ]);
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

        $posterPath = $this->attachmentPathOrLegacy($report, Attachment::TYPE_REPORT_POSTER, $report->poster_image_path);
        $attendancePath = $this->attachmentPathOrLegacy($report, Attachment::TYPE_REPORT_ATTENDANCE, $report->attendance_sheet_path);

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
            'Poster file' => $posterPath ? Storage::disk('public')->url($posterPath) : 'N/A',
            'Attendance file' => $attendancePath ? Storage::disk('public')->url($attendancePath) : 'N/A',
        ];

        return view('admin.review-show', [
            'pageTitle' => 'After Activity Report Submission Details',
            'backRoute' => route('admin.reports.index'),
            'status' => strtoupper((string) ($report->status ?? 'pending')),
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
