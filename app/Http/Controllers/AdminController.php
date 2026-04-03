<?php

namespace App\Http\Controllers;

use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\ActivityReport;
use App\Models\Organization;
use App\Models\OrganizationRegistration;
use App\Models\OrganizationRenewal;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
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

        return view('admin.review-show', [
            'pageTitle' => 'Registration Submission Details',
            'backRoute' => route('admin.registrations.index'),
            'status' => $registration->registration_status ?? 'PENDING',
            'details' => [
                'Organization' => $registration->organization?->organization_name ?? 'N/A',
                'Submitted By' => $registration->user?->full_name ?? 'N/A',
                'Academic Year' => $registration->academic_year ?? 'N/A',
                'Contact Person' => $registration->contact_person ?? 'N/A',
                'Submission Date' => optional($registration->submission_date)->format('M d, Y') ?? 'N/A',
                'Contact Email' => $registration->contact_email ?? 'N/A',
            ],
            'organization' => $registration->organization,
        ]);
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

        $calendar->load('organization');

        return view('admin.review-show', [
            'pageTitle' => 'Activity Calendar Submission Details',
            'backRoute' => route('admin.calendars.index'),
            'status' => $calendar->calendar_status ?? 'PENDING',
            'details' => [
                'Organization' => $calendar->organization?->organization_name ?? 'N/A',
                'Academic Year' => $calendar->academic_year ?? 'N/A',
                'Semester' => $calendar->semester ?? 'N/A',
                'Submission Date' => optional($calendar->submission_date)->format('M d, Y') ?? 'N/A',
                'Calendar File' => $calendar->calendar_file ?? 'N/A',
            ],
        ]);
    }

    public function showProposal(Request $request, ActivityProposal $proposal): View
    {
        $this->authorizeAdmin($request);

        $proposal->load(['organization', 'user']);

        return view('admin.review-show', [
            'pageTitle' => 'Activity Proposal Submission Details',
            'backRoute' => route('admin.proposals.index'),
            'status' => $proposal->proposal_status ?? 'PENDING',
            'details' => [
                'Organization' => $proposal->organization?->organization_name ?? 'N/A',
                'Submitted By' => $proposal->user?->full_name ?? 'N/A',
                'Activity Title' => $proposal->activity_title ?? 'N/A',
                'Proposed Start' => optional($proposal->proposed_start_date)->format('M d, Y') ?? 'N/A',
                'Proposed End' => optional($proposal->proposed_end_date)->format('M d, Y') ?? 'N/A',
                'Submission Date' => optional($proposal->submission_date)->format('M d, Y') ?? 'N/A',
            ],
            'organization' => $proposal->organization,
        ]);
    }

    public function showReport(Request $request, ActivityReport $report): View
    {
        $this->authorizeAdmin($request);

        $report->load(['organization', 'user']);

        return view('admin.review-show', [
            'pageTitle' => 'After Activity Report Submission Details',
            'backRoute' => route('admin.reports.index'),
            'status' => $report->report_status ?? 'PENDING',
            'details' => [
                'Organization' => $report->organization?->organization_name ?? 'N/A',
                'Submitted By' => $report->user?->full_name ?? 'N/A',
                'Submission Date' => optional($report->report_submission_date)->format('M d, Y') ?? 'N/A',
                'Report File' => $report->report_file ?? 'N/A',
                'Summary' => $report->accomplishment_summary ?? 'N/A',
            ],
            'organization' => $report->organization,
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user || !$user->isSdaoAdmin()) {
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

        $calendarSubmissionEvents = ActivityCalendar::query()
            ->with('organization')
            ->latest('submission_date')
            ->get()
            ->map(function (ActivityCalendar $calendar): array {
                return [
                    'title' => 'Calendar Submission: ' . ($calendar->semester ?? 'Semester'),
                    'start' => optional($calendar->submission_date)?->toDateString(),
                    'end' => null,
                    'status' => $calendar->calendar_status ?? 'PENDING',
                    'organization_name' => $calendar->organization?->organization_name ?? 'N/A',
                    'submitted_by' => 'Organization Submission',
                    'date' => optional($calendar->submission_date)->format('M d, Y') ?? 'N/A',
                    'time' => 'N/A',
                    'venue' => 'N/A',
                    'submission_type' => 'Activity Calendar',
                    'submission_date' => optional($calendar->submission_date)->format('M d, Y') ?? 'N/A',
                    'detail_route' => route('admin.calendars.show', $calendar),
                ];
            });

        return $proposalEvents
            ->concat($calendarSubmissionEvents)
            ->values();
    }
}

