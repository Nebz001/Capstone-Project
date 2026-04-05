<?php

namespace App\Http\Controllers;

use App\Models\ActivityCalendar;
use App\Models\ActivityCalendarEntry;
use App\Models\ActivityProposal;
use App\Models\ActivityReport;
use App\Models\Organization;
use App\Models\OrganizationRegistration;
use App\Models\OrganizationRenewal;
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

        return view('admin.dashboard', compact('counts', 'calendarEvents'));
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

    public function officerAccounts(Request $request): View
    {
        $this->authorizeAdmin($request);

        $accounts = User::query()
            ->where('role_type', 'ORG_OFFICER')
            ->with([
                'organizationOfficers' => fn ($query) => $query
                    ->latest('id')
                    ->with('organization'),
            ])
            ->latest('created_at')
            ->paginate(12);

        return view('admin.officer-accounts.index', compact('accounts'));
    }

    public function showOfficerAccount(Request $request, User $user): View
    {
        $this->authorizeAdmin($request);

        abort_if($user->role_type !== 'ORG_OFFICER', 404);

        $user->load([
            'organizationOfficers' => fn ($query) => $query->latest('id')->with('organization'),
            'validatedBy',
        ]);

        $latestOfficerRecord = $user->organizationOfficers->first();

        return view('admin.officer-accounts.show', [
            'studentOfficer' => $user,
            'latestOfficerRecord' => $latestOfficerRecord,
        ]);
    }

    public function updateOfficerValidation(Request $request, User $user)
    {
        $this->authorizeAdmin($request);

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
            ->route('admin.officer-accounts.show', $user)
            ->with('success', 'Student officer validation has been updated.');
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

        return view('admin.registrations.show', compact('registration'));
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

        $validator = Validator::make($request->all(), [
            'decision' => ['required', Rule::in(['APPROVED', 'REJECTED', 'REVISION'])],
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
        ]);

        $validator->after(function ($validator) use ($request): void {
            if ($request->input('decision') !== 'REVISION') {
                return;
            }
            $generalOk = strlen(trim((string) $request->input('remarks', ''))) >= 3;
            $sectionTexts = [
                $request->input('revision_comment_application'),
                $request->input('revision_comment_contact'),
                $request->input('revision_comment_organizational'),
                $request->input('revision_comment_requirements'),
            ];
            $anySection = collect($sectionTexts)->contains(fn ($t) => strlen(trim((string) $t)) >= 3);
            if (! $generalOk && ! $anySection) {
                $validator->errors()->add(
                    'remarks',
                    'For revision, add general remarks (at least 3 characters) and/or at least one section comment (at least 3 characters).',
                );
            }
        });

        $validated = $validator->validate();

        /** @var User $admin */
        $admin = $request->user();
        $decision = $validated['decision'];
        $remarks = trim((string) ($validated['remarks'] ?? ''));

        $sectionFields = [
            'revision_comment_application' => trim((string) $request->input('revision_comment_application', '')),
            'revision_comment_contact' => trim((string) $request->input('revision_comment_contact', '')),
            'revision_comment_organizational' => trim((string) $request->input('revision_comment_organizational', '')),
            'revision_comment_requirements' => trim((string) $request->input('revision_comment_requirements', '')),
        ];

        DB::transaction(function () use ($registration, $decision, $remarks, $admin, $sectionFields): void {
            $registration->registration_status = $decision;
            $registration->approved_by_sdao = $admin->full_name;
            $registration->approval_date = now()->toDateString();

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
            ->with(['organization', 'user'])
            ->latest('submission_date')
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

        $termLabels = [
            'term_1' => 'Term 1',
            'term_2' => 'Term 2',
            'term_3' => 'Term 3',
        ];

        $calendarActivityEvents = ActivityCalendarEntry::query()
            ->with(['activityCalendar.organization'])
            ->orderBy('activity_date')
            ->orderBy('id')
            ->get()
            ->map(function (ActivityCalendarEntry $entry) use ($termLabels): array {
                $calendar = $entry->activityCalendar;
                $orgName = $calendar->submitted_organization_name
                    ?: ($calendar->organization?->organization_name ?? 'N/A');
                $termKey = $calendar->semester ?? '';
                $termLabel = $termLabels[$termKey] ?? ($termKey !== '' ? $termKey : 'N/A');

                return [
                    'title' => $entry->activity_name,
                    'start' => optional($entry->activity_date)?->toDateString(),
                    'end' => null,
                    'status' => $calendar->calendar_status ?? 'PENDING',
                    'organization_name' => $orgName,
                    'submitted_by' => 'Organization Submission',
                    'date' => optional($entry->activity_date)->format('M d, Y') ?? 'N/A',
                    'time' => 'Not specified',
                    'venue' => $entry->venue,
                    'submission_type' => 'Activity Calendar ('.$termLabel.')',
                    'submission_date' => optional($calendar->submission_date)->format('M d, Y') ?? 'N/A',
                    'detail_route' => route('admin.calendars.show', $calendar),
                ];
            });

        return $proposalEvents
            ->concat($calendarActivityEvents)
            ->values();
    }
}
