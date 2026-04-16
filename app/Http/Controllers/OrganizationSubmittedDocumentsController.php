<?php

namespace App\Http\Controllers;

use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\ActivityReport;
use App\Models\Organization;
use App\Models\OrganizationOfficer;
use App\Models\OrganizationRegistration;
use App\Models\OrganizationRenewal;
use App\Models\User;
use App\Support\SubmissionRoutingProgress;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationSubmittedDocumentsController extends Controller
{
    private const REGISTRATION_FILE_KEYS = [
        'letter_of_intent',
        'application_form',
        'by_laws',
        'updated_list_of_officers_founders',
        'dean_endorsement_faculty_adviser',
        'proposed_projects_budget',
        'others',
    ];

    private const RENEWAL_FILE_KEYS = [
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

    private const SCHOOL_LABELS = [
        'sace' => 'School of Architecture, Computer and Engineering',
        'sahs' => 'School of Allied Health and Sciences',
        'sabm' => 'School of Accounting and Business Management',
        'shs' => 'Senior High School',
    ];

    private const REGISTRATION_FILE_LABELS = [
        'letter_of_intent' => 'Letter of intent',
        'application_form' => 'Application form',
        'by_laws' => 'By-laws',
        'updated_list_of_officers_founders' => 'Updated list of officers / founders',
        'dean_endorsement_faculty_adviser' => 'Dean endorsement (faculty adviser)',
        'proposed_projects_budget' => 'Proposed projects and budget',
        'others' => 'Other requirement',
    ];

    private const RENEWAL_FILE_LABELS = [
        'letter_of_intent' => 'Letter of intent',
        'application_form' => 'Application form',
        'by_laws_updated_if_applicable' => 'By-laws (if updated)',
        'updated_list_of_officers_founders_ay' => 'Updated list of officers / founders (AY)',
        'dean_endorsement_faculty_adviser' => 'Dean endorsement (faculty adviser)',
        'proposed_projects_budget' => 'Proposed projects and budget',
        'past_projects' => 'Past projects',
        'financial_statement_previous_ay' => 'Financial statement (previous AY)',
        'evaluation_summary_past_projects' => 'Evaluation summary (past projects)',
        'others' => 'Other requirement',
    ];

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $filterType = (string) $request->query('type', 'all');
        $filterStatus = (string) $request->query('status', 'all');
        $filterYear = (string) $request->query('academic_year', '');
        $sort = (string) $request->query('sort', 'latest');

        // If the account doesn't yet have an active linked organization/officer record,
        // we still show the normal submitted-documents page layout, but block the content.
        $filters = [
            'type' => $filterType,
            'status' => $filterStatus,
            'academic_year' => $filterYear,
            'sort' => $sort,
        ];

        $activeOfficer = null;
        $hasAnyOfficerRecord = false;
        if ($user && ($user->role_type ?? null) === 'ORG_OFFICER') {
            $hasAnyOfficerRecord = $user->organizationOfficers()->exists();
            $activeOfficer = $user->organizationOfficers()
                ->where('officer_status', 'ACTIVE')
                ->orderByDesc('id')
                ->first();
        }

        if (! $activeOfficer) {
            // For new accounts without an active officer/org, avoid aborting with a raw 403.
            // Instead, render the page shell and show the blocked message component.
            $blockedMessage = $hasAnyOfficerRecord
                ? 'Account is pending SDAO validation. No Submitted Documents are available until your officer record is activated.'
                : 'Account is not yet linked to an active organization. No Submitted Documents are available yet because your account is not eligible.';

            return view('organizations.submitted-documents.index', [
                'organization' => null,
                'groupedRecords' => [],
                'hasAnyRecords' => false,
                'academicYearOptions' => collect(),
                'filters' => $filters,
                'blockedMessage' => $blockedMessage,
            ]);
        }

        $organization = Organization::query()->find($activeOfficer->organization_id);
        if (! $organization) {
            return view('organizations.submitted-documents.index', [
                'organization' => null,
                'groupedRecords' => [],
                'hasAnyRecords' => false,
                'academicYearOptions' => collect(),
                'filters' => $filters,
                'blockedMessage' => 'Account is not yet linked to an active organization. No Submitted Documents are available yet because your account is not eligible.',
            ]);
        }

        $allRows = $this->buildSubmittedDocumentRows($organization, $request);
        $academicYearOptions = $allRows->pluck('academic_year')->filter()->unique()->sort()->values();

        $rows = $allRows;
        if ($filterType !== 'all') {
            $rows = $rows->filter(fn (array $r): bool => $r['type_key'] === $filterType);
        }
        if ($filterStatus !== 'all') {
            $rows = $rows->filter(function (array $r) use ($filterStatus): bool {
                if ($filterStatus === 'REVISION') {
                    return in_array(strtoupper((string) $r['status_raw']), ['REVISION', 'REVISION_REQUIRED'], true);
                }

                return strtoupper((string) $r['status_raw']) === strtoupper($filterStatus);
            });
        }
        if ($filterYear !== '') {
            $rows = $rows->filter(fn (array $r): bool => ($r['academic_year'] ?? '') === $filterYear);
        }

        $rows = $rows->sortBy(function (array $r) use ($sort): float {
            $ts = (float) ($r['sort_timestamp'] ?? 0);

            return $sort === 'oldest' ? $ts : -$ts;
        })->values();

        $typeOrder = ['registration', 'renewal', 'activity_calendar', 'activity_proposal', 'after_activity_report'];
        $grouped = [];
        foreach ($typeOrder as $tk) {
            $slice = $rows->filter(fn (array $r): bool => $r['type_key'] === $tk)->values();
            if ($slice->isNotEmpty()) {
                $grouped[] = [
                    'type_key' => $tk,
                    'type_label' => $slice->first()['type_label'],
                    'rows' => $slice,
                ];
            }
        }

        return view('organizations.submitted-documents.index', [
            'organization' => $organization,
            'groupedRecords' => $grouped,
            'hasAnyRecords' => $allRows->isNotEmpty(),
            'academicYearOptions' => $academicYearOptions,
            'filters' => $filters,
        ]);
    }

    public function showSubmittedRegistration(Request $request, OrganizationRegistration $registration): View
    {
        $this->ensureOfficerOwnsOrganization($request, (int) $registration->organization_id);

        $sp = $this->submissionStatusPresentation($registration->registration_status);
        $fileLinks = $this->registrationFileLinks($registration);

        return view('organizations.submitted-documents.detail', [
            'backRoute' => $this->submittedDocumentsListUrl($request),
            'pageTitle' => 'Registration submission',
            'subtitle' => 'Organization registration application on file with SDAO.',
            'statusLabel' => $sp['label'],
            'statusClass' => $sp['badge_class'],
            'metaRows' => $this->registrationMetaRows($registration),
            'remarkHighlight' => $this->registrationRemarksPreview($registration),
            'fileLinks' => $fileLinks,
            'workflowLinks' => $this->registrationWorkflowLinks($registration),
            'calendarEntries' => null,
            'progressDocumentLabel' => 'Organization registration',
            'progressStages' => SubmissionRoutingProgress::stagesForSimpleSdaoPipeline($registration->registration_status),
            'progressSummary' => SubmissionRoutingProgress::summaryForSimpleSdao($registration->registration_status),
        ]);
    }

    public function streamSubmittedRegistrationRequirementFile(Request $request, OrganizationRegistration $registration, string $key): StreamedResponse
    {
        $this->ensureOfficerOwnsOrganization($request, (int) $registration->organization_id);

        if (! in_array($key, self::REGISTRATION_FILE_KEYS, true)) {
            abort(404);
        }

        return $this->streamRequirementPath(
            $registration->requirement_files,
            $key,
            'organization-requirements/'.(int) $registration->organization_id.'/registration/'
        );
    }

    public function showSubmittedRenewal(Request $request, OrganizationRenewal $renewal): View
    {
        $this->ensureOfficerOwnsOrganization($request, (int) $renewal->organization_id);

        $sp = $this->submissionStatusPresentation($renewal->renewal_status);
        $fileLinks = $this->renewalFileLinks($renewal);

        return view('organizations.submitted-documents.detail', [
            'backRoute' => $this->submittedDocumentsListUrl($request),
            'pageTitle' => 'Renewal submission',
            'subtitle' => 'Organization renewal application on file with SDAO.',
            'statusLabel' => $sp['label'],
            'statusClass' => $sp['badge_class'],
            'metaRows' => $this->renewalMetaRows($renewal),
            'remarkHighlight' => $this->renewalRemarksPreview($renewal),
            'fileLinks' => $fileLinks,
            'workflowLinks' => $this->renewalWorkflowLinks($renewal),
            'calendarEntries' => null,
            'progressDocumentLabel' => 'Organization renewal',
            'progressStages' => SubmissionRoutingProgress::stagesForSimpleSdaoPipeline($renewal->renewal_status),
            'progressSummary' => SubmissionRoutingProgress::summaryForSimpleSdao($renewal->renewal_status),
        ]);
    }

    public function streamSubmittedRenewalRequirementFile(Request $request, OrganizationRenewal $renewal, string $key): StreamedResponse
    {
        $this->ensureOfficerOwnsOrganization($request, (int) $renewal->organization_id);

        if (! in_array($key, self::RENEWAL_FILE_KEYS, true)) {
            abort(404);
        }

        $prefix = 'organization-requirements/'.(int) $renewal->organization_id.'/renewals/'.$renewal->id.'/';

        return $this->streamRequirementPath($renewal->requirement_files, $key, $prefix);
    }

    public function showSubmittedActivityCalendar(Request $request, ActivityCalendar $calendar): View
    {
        $this->ensureOfficerOwnsOrganization($request, (int) $calendar->organization_id);
        $calendar->load([
            'organization',
            'entries' => function ($q): void {
                $q->orderBy('activity_date')->orderBy('id')->with('proposal');
            },
        ]);

        $sp = $this->submissionStatusPresentation($calendar->calendar_status);
        $fileLinks = [];
        if ($calendar->calendar_file) {
            $fileLinks[] = [
                'label' => 'Uploaded calendar file (PDF / document)',
                'url' => route('organizations.submitted-documents.calendars.file', $calendar),
            ];
        }

        $term = $this->activityCalendarTermLabel($calendar->semester);
        $titleLine = trim(($calendar->academic_year ?? 'Academic year N/A').' · '.$term.' activity calendar');

        $nav = $this->detailBackNavigation($request);

        return view('organizations.submitted-documents.detail', [
            'backRoute' => $nav['route'],
            'backLabel' => $nav['label'],
            'pageTitle' => 'Activity calendar submission',
            'subtitle' => $titleLine,
            'statusLabel' => $sp['label'],
            'statusClass' => $sp['badge_class'],
            'metaRows' => [
                ['label' => 'RSO name (form)', 'value' => $calendar->submitted_organization_name ?? '—'],
                ['label' => 'Organization (profile)', 'value' => $calendar->organization?->organization_name ?? '—'],
                ['label' => 'Academic year', 'value' => $calendar->academic_year ?? '—'],
                ['label' => 'Term', 'value' => $term],
                ['label' => 'Date submitted', 'value' => optional($calendar->submission_date)->format('M j, Y') ?? '—'],
                ['label' => 'Activities listed', 'value' => (string) $calendar->entries->count()],
            ],
            'remarkHighlight' => null,
            'fileLinks' => $fileLinks,
            'workflowLinks' => $this->activityCalendarWorkflowLinks($calendar),
            'calendarEntries' => $calendar->entries,
            'progressDocumentLabel' => 'Activity calendar',
            'progressStages' => SubmissionRoutingProgress::stagesForSimpleSdaoPipeline($calendar->calendar_status),
            'progressSummary' => SubmissionRoutingProgress::summaryForSimpleSdao($calendar->calendar_status),
        ]);
    }

    public function streamSubmittedActivityCalendarMainFile(Request $request, ActivityCalendar $calendar): StreamedResponse
    {
        $this->ensureOfficerOwnsOrganization($request, (int) $calendar->organization_id);

        $relativePath = $calendar->calendar_file;
        if (! is_string($relativePath) || $relativePath === '') {
            abort(404);
        }

        $organizationId = (int) $calendar->organization_id;
        $expectedPrefix = 'activity-calendars/'.$organizationId.'/';
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/') || ! str_starts_with($relativePath, $expectedPrefix)) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            abort(404);
        }

        return $disk->response($relativePath, basename($relativePath), [], 'inline');
    }

    public function showSubmittedActivityProposal(Request $request, ActivityProposal $proposal): View
    {
        $this->ensureOfficerOwnsOrganization($request, (int) $proposal->organization_id);
        $proposal->load(['calendar', 'calendarEntry']);

        $sp = $this->submissionStatusPresentation($proposal->proposal_status, true);
        $fileLinks = [];
        if ($proposal->organization_logo_path) {
            $fileLinks[] = [
                'label' => 'Organization logo',
                'url' => route('organizations.submitted-documents.proposals.file', ['proposal' => $proposal, 'key' => 'logo']),
            ];
        }
        if ($proposal->external_funding_support_path) {
            $fileLinks[] = [
                'label' => 'External funding support (letter / proof)',
                'url' => route('organizations.submitted-documents.proposals.file', ['proposal' => $proposal, 'key' => 'external']),
            ];
        }
        if ($proposal->resume_resource_persons_path) {
            $fileLinks[] = [
                'label' => 'Résumé / resource persons',
                'url' => route('organizations.submitted-documents.proposals.file', ['proposal' => $proposal, 'key' => 'resume']),
            ];
        }

        $school = $proposal->school_code ? (self::SCHOOL_LABELS[$proposal->school_code] ?? $proposal->school_code) : '—';

        $nav = $this->detailBackNavigation($request);

        return view('organizations.submitted-documents.detail', [
            'backRoute' => $nav['route'],
            'backLabel' => $nav['label'],
            'pageTitle' => 'Activity proposal',
            'subtitle' => $proposal->activity_title ?? 'Submitted proposal',
            'statusLabel' => $sp['label'],
            'statusClass' => $sp['badge_class'],
            'metaRows' => [
                ['label' => 'Activity title', 'value' => $proposal->activity_title ?? '—'],
                ['label' => 'Organization (form)', 'value' => $proposal->form_organization_name ?? '—'],
                ['label' => 'Academic year', 'value' => $proposal->academic_year ?? '—'],
                ['label' => 'School', 'value' => $school],
                ['label' => 'Department / program', 'value' => $proposal->department_program ?? '—'],
                ['label' => 'Proposed dates', 'value' => trim(collect([
                    optional($proposal->proposed_start_date)->format('M j, Y'),
                    optional($proposal->proposed_end_date)->format('M j, Y'),
                ])->filter()->implode(' → ')) ?: '—'],
                ['label' => 'Time', 'value' => $proposal->proposed_time ?? '—'],
                ['label' => 'Venue', 'value' => $proposal->venue ?? '—'],
                ['label' => 'Submitted', 'value' => optional($proposal->submission_date)->format('M j, Y') ?? '—'],
                ['label' => 'Linked activity calendar', 'value' => $proposal->calendar
                    ? (($proposal->calendar->academic_year ?? '').' · '.$this->activityCalendarTermLabel($proposal->calendar->semester))
                    : '—'],
                ['label' => 'Calendar activity row', 'value' => $proposal->calendarEntry
                    ? trim(($proposal->calendarEntry->activity_name ?? '—').' · '.(optional($proposal->calendarEntry->activity_date)->format('M j, Y') ?? ''))
                    : '—'],
            ],
            'remarkHighlight' => $this->truncatePreview($proposal->overall_goal, 220),
            'fileLinks' => $fileLinks,
            'workflowLinks' => $this->activityProposalWorkflowLinks($proposal),
            'calendarEntries' => null,
            'progressDocumentLabel' => 'Activity proposal',
            'progressStages' => SubmissionRoutingProgress::stagesForActivityProposal($proposal),
            'progressSummary' => SubmissionRoutingProgress::summaryForActivityProposal($proposal->proposal_status),
        ]);
    }

    public function streamSubmittedActivityProposalFile(Request $request, ActivityProposal $proposal, string $key): StreamedResponse
    {
        $this->ensureOfficerOwnsOrganization($request, (int) $proposal->organization_id);

        $organizationId = (int) $proposal->organization_id;
        $expectedPrefix = 'activity-proposals/'.$organizationId.'/';

        $relativePath = match ($key) {
            'logo' => $proposal->organization_logo_path,
            'resume' => $proposal->resume_resource_persons_path,
            'external' => $proposal->external_funding_support_path,
            default => null,
        };

        if (! is_string($relativePath) || $relativePath === '') {
            abort(404);
        }

        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/') || ! str_starts_with($relativePath, $expectedPrefix)) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            abort(404);
        }

        return $disk->response($relativePath, basename($relativePath), [], 'inline');
    }

    public function showSubmittedAfterActivityReport(Request $request, ActivityReport $report): View
    {
        $this->ensureOfficerOwnsOrganization($request, (int) $report->organization_id);
        $report->load('proposal');

        $sp = $this->submissionStatusPresentation($report->report_status, false, 'report');

        $fileLinks = [];
        if ($report->poster_image_path) {
            $fileLinks[] = ['label' => 'Poster image', 'url' => route('organizations.submitted-documents.reports.file', ['report' => $report, 'key' => 'poster'])];
        }
        $photos = is_array($report->supporting_photo_paths) ? $report->supporting_photo_paths : [];
        foreach ($photos as $i => $path) {
            if (is_string($path) && $path !== '') {
                $fileLinks[] = [
                    'label' => 'Supporting photo '.($i + 1),
                    'url' => route('organizations.submitted-documents.reports.file', ['report' => $report, 'key' => 'supporting_'.$i]),
                ];
            }
        }
        if ($report->certificate_sample_path) {
            $fileLinks[] = ['label' => 'Certificate sample', 'url' => route('organizations.submitted-documents.reports.file', ['report' => $report, 'key' => 'certificate'])];
        }
        if ($report->evaluation_form_sample_path) {
            $fileLinks[] = ['label' => 'Evaluation form sample', 'url' => route('organizations.submitted-documents.reports.file', ['report' => $report, 'key' => 'evaluation_form'])];
        }
        $fileLinks[] = ['label' => 'Attendance sheet', 'url' => route('organizations.submitted-documents.reports.file', ['report' => $report, 'key' => 'attendance'])];

        $school = $report->school_code ? (self::SCHOOL_LABELS[$report->school_code] ?? $report->school_code) : '—';

        return view('organizations.submitted-documents.detail', [
            'backRoute' => $this->submittedDocumentsListUrl($request),
            'pageTitle' => 'After activity report',
            'subtitle' => 'After Activity Report — '.($report->event_name ?? $report->activity_event_title ?? 'Event'),
            'statusLabel' => $sp['label'],
            'statusClass' => $sp['badge_class'],
            'metaRows' => [
                ['label' => 'Activity / event title', 'value' => $report->activity_event_title ?? '—'],
                ['label' => 'Event name', 'value' => $report->event_name ?? '—'],
                ['label' => 'School', 'value' => $school],
                ['label' => 'Department', 'value' => $report->department ?? '—'],
                ['label' => 'Event date & time', 'value' => optional($report->event_starts_at)->format('M j, Y g:i A') ?? '—'],
                ['label' => 'Prepared by', 'value' => $report->prepared_by ?? '—'],
                ['label' => 'Submitted', 'value' => optional($report->report_submission_date)->format('M j, Y') ?? '—'],
                ['label' => 'Linked proposal', 'value' => $report->proposal?->activity_title ?? '—'],
            ],
            'remarkHighlight' => $this->truncatePreview($report->evaluation_report, 200),
            'fileLinks' => $fileLinks,
            'workflowLinks' => [
                ['label' => 'Submit another report', 'href' => $this->withSuperAdminOrgQuery($request, route('organizations.after-activity-report')), 'variant' => 'secondary'],
            ],
            'calendarEntries' => null,
            'progressDocumentLabel' => 'After activity report',
            'progressStages' => SubmissionRoutingProgress::stagesForActivityReport($report->report_status),
            'progressSummary' => SubmissionRoutingProgress::summaryForActivityReport($report->report_status),
        ]);
    }

    public function streamSubmittedAfterActivityReportFile(Request $request, ActivityReport $report, string $key): StreamedResponse
    {
        $this->ensureOfficerOwnsOrganization($request, (int) $report->organization_id);

        $organizationId = (int) $report->organization_id;
        $expectedPrefixes = [
            'activity-reports/'.$organizationId.'/',
        ];

        $relativePath = null;
        if ($key === 'poster') {
            $relativePath = $report->poster_image_path;
        } elseif ($key === 'certificate') {
            $relativePath = $report->certificate_sample_path;
        } elseif ($key === 'evaluation_form') {
            $relativePath = $report->evaluation_form_sample_path;
        } elseif ($key === 'attendance') {
            $relativePath = $report->attendance_sheet_path;
        } elseif (preg_match('/^supporting_(\d+)$/', $key, $m)) {
            $photos = is_array($report->supporting_photo_paths) ? $report->supporting_photo_paths : [];
            $idx = (int) $m[1];
            $relativePath = $photos[$idx] ?? null;
        }

        if (! is_string($relativePath) || $relativePath === '') {
            abort(404);
        }

        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            abort(404);
        }

        $ok = false;
        foreach ($expectedPrefixes as $pfx) {
            if (str_starts_with($relativePath, $pfx)) {
                $ok = true;
                break;
            }
        }
        if (! $ok) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            abort(404);
        }

        return $disk->response($relativePath, basename($relativePath), [], 'inline');
    }

    private function resolveActiveOfficer(Request $request): OrganizationOfficer
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $user->role_type !== 'ORG_OFFICER') {
            abort(403, 'Only organization officers can access this feature.');
        }

        $officer = $user->organizationOfficers()
            ->where('officer_status', 'ACTIVE')
            ->orderByDesc('id')
            ->first();

        if (! $officer) {
            abort(403, 'No active organization officer record found.');
        }

        return $officer;
    }

    private function organizationForActiveOfficer(Request $request): Organization
    {
        $officer = $this->resolveActiveOfficer($request);
        $organization = Organization::query()->find($officer->organization_id);
        if (! $organization) {
            abort(404);
        }

        return $organization;
    }

    private function ensureOfficerOwnsOrganization(Request $request, int $organizationId): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user && $user->isSuperAdmin()) {
            $qid = (int) $request->integer('organization_id');
            if ($qid > 0 && $qid !== $organizationId) {
                abort(403);
            }

            return;
        }

        $officer = $this->resolveActiveOfficer($request);
        if ((int) $officer->organization_id !== $organizationId) {
            abort(403);
        }
    }

    /**
     * @return array{label: string, badge_class: string, filter: string}
     */
    private function submissionStatusPresentation(?string $raw, bool $isProposal = false, string $context = 'default'): array
    {
        $u = strtoupper((string) $raw);

        if ($context === 'report') {
            return match ($u) {
                'PENDING' => ['label' => 'Pending', 'badge_class' => 'bg-amber-100 text-amber-800 border border-amber-200', 'filter' => 'PENDING'],
                'REVIEWED' => ['label' => 'Reviewed', 'badge_class' => 'bg-blue-100 text-blue-700 border border-blue-200', 'filter' => 'REVIEWED'],
                'APPROVED' => ['label' => 'Approved', 'badge_class' => 'bg-emerald-100 text-emerald-700 border border-emerald-200', 'filter' => 'APPROVED'],
                'REJECTED' => ['label' => 'Rejected', 'badge_class' => 'bg-rose-100 text-rose-700 border border-rose-200', 'filter' => 'REJECTED'],
                default => ['label' => $raw ?: 'Unknown', 'badge_class' => 'bg-slate-100 text-slate-700 border border-slate-200', 'filter' => $u],
            };
        }

        $approvedLabel = $isProposal ? 'Approved / scheduled' : 'Approved';

        return match ($u) {
            'DRAFT' => ['label' => 'Draft', 'badge_class' => 'bg-slate-200 text-slate-800 border border-slate-300', 'filter' => 'DRAFT'],
            'PENDING' => ['label' => 'Pending', 'badge_class' => 'bg-amber-100 text-amber-800 border border-amber-200', 'filter' => 'PENDING'],
            'UNDER_REVIEW' => ['label' => 'Under review', 'badge_class' => 'bg-blue-100 text-blue-700 border border-blue-200', 'filter' => 'UNDER_REVIEW'],
            'APPROVED' => ['label' => $approvedLabel, 'badge_class' => 'bg-emerald-100 text-emerald-700 border border-emerald-200', 'filter' => 'APPROVED'],
            'REJECTED' => ['label' => 'Rejected', 'badge_class' => 'bg-rose-100 text-rose-700 border border-rose-200', 'filter' => 'REJECTED'],
            'REVISION', 'REVISION_REQUIRED' => ['label' => 'For revision', 'badge_class' => 'bg-orange-100 text-orange-700 border border-orange-200', 'filter' => 'REVISION'],
            default => ['label' => $raw ?: 'Unknown', 'badge_class' => 'bg-slate-100 text-slate-700 border border-slate-200', 'filter' => $u],
        };
    }

    private function activityCalendarTermLabel(?string $semester): string
    {
        return match ($semester) {
            'term_1' => 'Term 1',
            'term_2' => 'Term 2',
            'term_3' => 'Term 3',
            default => $semester ? $semester : '—',
        };
    }

    private function truncatePreview(?string $text, int $max = 140): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $t = preg_replace('/\s+/', ' ', trim(strip_tags($text)));

        return strlen($t) > $max ? substr($t, 0, $max).'…' : $t;
    }

    private function registrationRemarksPreview(OrganizationRegistration $r): ?string
    {
        $parts = array_filter([
            $r->additional_remarks,
            $r->revision_comment_application,
            $r->revision_comment_contact,
            $r->revision_comment_organizational,
            $r->revision_comment_requirements,
            $r->registration_notes,
        ], fn ($v) => is_string($v) && trim($v) !== '');

        if ($parts === []) {
            return null;
        }

        return $this->truncatePreview(implode(' — ', $parts), 160);
    }

    private function renewalRemarksPreview(OrganizationRenewal $r): ?string
    {
        $parts = array_filter([
            $r->additional_remarks,
            $r->renewal_notes,
        ], fn ($v) => is_string($v) && trim($v) !== '');

        if ($parts === []) {
            return null;
        }

        return $this->truncatePreview(implode(' — ', $parts), 160);
    }

    private function countRequirementFiles(?array $files): int
    {
        if (! is_array($files)) {
            return 0;
        }

        return count(array_filter($files, fn ($p) => is_string($p) && $p !== ''));
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function registrationFileLinks(OrganizationRegistration $registration): array
    {
        $links = [];
        $files = is_array($registration->requirement_files) ? $registration->requirement_files : [];
        foreach (self::REGISTRATION_FILE_KEYS as $key) {
            if (! empty($files[$key]) && is_string($files[$key])) {
                $label = self::REGISTRATION_FILE_LABELS[$key] ?? $key;
                $links[] = [
                    'label' => $label,
                    'url' => route('organizations.submitted-documents.registrations.file', [$registration, $key]),
                ];
            }
        }

        return $links;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function renewalFileLinks(OrganizationRenewal $renewal): array
    {
        $links = [];
        $files = is_array($renewal->requirement_files) ? $renewal->requirement_files : [];
        foreach (self::RENEWAL_FILE_KEYS as $key) {
            if (! empty($files[$key]) && is_string($files[$key])) {
                $label = self::RENEWAL_FILE_LABELS[$key] ?? $key;
                $links[] = [
                    'label' => $label,
                    'url' => route('organizations.submitted-documents.renewals.file', [$renewal, $key]),
                ];
            }
        }

        return $links;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function registrationMetaRows(OrganizationRegistration $registration): array
    {
        return [
            ['label' => 'Contact person', 'value' => $registration->contact_person ?? '—'],
            ['label' => 'Contact number', 'value' => $registration->contact_no ?? '—'],
            ['label' => 'Contact email', 'value' => $registration->contact_email ?? '—'],
            ['label' => 'Academic year', 'value' => $registration->academic_year ?? '—'],
            ['label' => 'Submitted', 'value' => optional($registration->submission_date)->format('M j, Y') ?? '—'],
            ['label' => 'Last updated', 'value' => optional($registration->updated_at)->format('M j, Y g:i A') ?? '—'],
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function renewalMetaRows(OrganizationRenewal $renewal): array
    {
        return [
            ['label' => 'Contact person', 'value' => $renewal->contact_person ?? '—'],
            ['label' => 'Contact number', 'value' => $renewal->contact_no ?? '—'],
            ['label' => 'Contact email', 'value' => $renewal->contact_email ?? '—'],
            ['label' => 'Academic year', 'value' => $renewal->academic_year ?? '—'],
            ['label' => 'Submitted', 'value' => optional($renewal->submission_date)->format('M j, Y') ?? '—'],
            ['label' => 'Last updated', 'value' => optional($renewal->updated_at)->format('M j, Y g:i A') ?? '—'],
        ];
    }

    /**
     * @return list<array{label: string, href: string, variant: string}>
     */
    private function registrationWorkflowLinks(OrganizationRegistration $registration): array
    {
        $links = [];
        $status = strtoupper((string) $registration->registration_status);
        if (in_array($status, ['REVISION', 'REVISION_REQUIRED'], true)) {
            $links[] = ['label' => 'Edit / resubmit (registration form)', 'href' => route('organizations.register'), 'variant' => 'primary'];
        }

        return $links;
    }

    /**
     * @return list<array{label: string, href: string, variant: string}>
     */
    private function renewalWorkflowLinks(OrganizationRenewal $renewal): array
    {
        $links = [];
        $status = strtoupper((string) $renewal->renewal_status);
        if (in_array($status, ['REVISION', 'REVISION_REQUIRED'], true)) {
            $links[] = ['label' => 'Edit / resubmit (renewal form)', 'href' => route('organizations.renew'), 'variant' => 'primary'];
        }

        return $links;
    }

    /**
     * @return list<array{label: string, href: string, variant: string}>
     */
    private function activityCalendarWorkflowLinks(ActivityCalendar $calendar): array
    {
        $links = [];
        $status = strtoupper((string) ($calendar->calendar_status ?? ''));
        if ($status === 'REVISION') {
            $links[] = ['label' => 'Edit / resubmit activity calendar', 'href' => route('organizations.activity-calendar-submission'), 'variant' => 'primary'];
        }

        return $links;
    }

    /**
     * @return list<array{label: string, href: string, variant: string}>
     */
    private function activityProposalWorkflowLinks(ActivityProposal $proposal): array
    {
        $links = [];
        if (strtoupper((string) ($proposal->proposal_status ?? '')) === 'REVISION') {
            $links[] = ['label' => 'Address revision (proposal form)', 'href' => route('organizations.activity-proposal-submission'), 'variant' => 'primary'];
        }
        $links[] = ['label' => 'Submit another proposal', 'href' => route('organizations.activity-proposal-submission'), 'variant' => 'secondary'];

        return $links;
    }

    /**
     * @param  array<string, mixed>|null  $files
     */
    private function streamRequirementPath(?array $files, string $key, string $expectedPrefix): StreamedResponse
    {
        if (! is_array($files) || empty($files[$key]) || ! is_string($files[$key])) {
            abort(404);
        }

        $relativePath = $files[$key];
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            abort(404);
        }

        if (! str_starts_with($relativePath, $expectedPrefix)) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            abort(404);
        }

        return $disk->response($relativePath, basename($relativePath), [], 'inline');
    }

    /**
     * Activity calendar / proposal detail pages are reachable from both Submitted Documents (Manage org)
     * and Activity Submission. Back navigation follows the route used to open the page.
     *
     * @return array{route: string, label: string}
     */
    private function detailBackNavigation(Request $request): array
    {
        $suffix = $this->superAdminOrganizationQuerySuffix($request);

        // These detail pages (Activity Calendar / Submit Proposal) belong to the
        // Activity Submission module. Always keep Back navigation inside it.
        return [
            'route' => route('organizations.activity-submission').$suffix,
            'label' => 'Back to Activity Submission',
        ];
    }

    private function superAdminOrganizationQuerySuffix(Request $request): string
    {
        if (! $request->user()?->isSuperAdmin()) {
            return '';
        }
        $id = (int) $request->integer('organization_id');
        if ($id < 1) {
            return '';
        }

        return '?organization_id='.$id;
    }

    private function withSuperAdminOrgQuery(Request $request, string $url): string
    {
        $suffix = $this->superAdminOrganizationQuerySuffix($request);
        if ($suffix === '') {
            return $url;
        }

        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.ltrim($suffix, '?');
    }

    private function submittedDocumentsListUrl(Request $request): string
    {
        return $this->withSuperAdminOrgQuery($request, route('organizations.submitted-documents'));
    }

    private function buildSubmittedDocumentRows(Organization $organization, Request $request): Collection
    {
        $q = fn (string $url): string => $this->withSuperAdminOrgQuery($request, $url);
        $rows = collect();

        foreach (OrganizationRegistration::query()->where('organization_id', $organization->id)->orderByDesc('updated_at')->get() as $reg) {
            $sp = $this->submissionStatusPresentation($reg->registration_status);
            $nFiles = $this->countRequirementFiles(is_array($reg->requirement_files) ? $reg->requirement_files : null);
            $detail = $q(route('organizations.submitted-documents.registrations.show', $reg));
            $rows->push([
                'type_key' => 'registration',
                'type_label' => 'Registration',
                'title' => 'Organization Registration Application',
                'submitted_display' => optional($reg->submission_date)->format('M j, Y') ?? '—',
                'updated_display' => optional($reg->updated_at)->format('M j, Y') ?? '—',
                'status_raw' => $reg->registration_status,
                'status_label' => $sp['label'],
                'status_variant' => $this->variantKeyFromBadge($sp['badge_class']),
                'academic_year' => $reg->academic_year,
                'academic_context' => $reg->academic_year ? 'AY '.$reg->academic_year : null,
                'remarks_preview' => $this->registrationRemarksPreview($reg),
                'detail_href' => $detail,
                'has_files' => $nFiles > 0,
                'files_href' => $detail.'#submitted-files',
                'sort_timestamp' => $reg->updated_at?->getTimestamp() ?? 0,
                'row_actions' => $this->listRowActions(
                    $detail,
                    $nFiles > 0,
                    $detail.'#submitted-files',
                    [],
                ),
            ]);
        }

        foreach (OrganizationRenewal::query()->where('organization_id', $organization->id)->orderByDesc('updated_at')->get() as $renewal) {
            $sp = $this->submissionStatusPresentation($renewal->renewal_status);
            $nFiles = $this->countRequirementFiles(is_array($renewal->requirement_files) ? $renewal->requirement_files : null);
            $detail = $q(route('organizations.submitted-documents.renewals.show', $renewal));
            $rows->push([
                'type_key' => 'renewal',
                'type_label' => 'Renewal',
                'title' => 'Organization Renewal Application',
                'submitted_display' => optional($renewal->submission_date)->format('M j, Y') ?? '—',
                'updated_display' => optional($renewal->updated_at)->format('M j, Y') ?? '—',
                'status_raw' => $renewal->renewal_status,
                'status_label' => $sp['label'],
                'status_variant' => $this->variantKeyFromBadge($sp['badge_class']),
                'academic_year' => $renewal->academic_year,
                'academic_context' => $renewal->academic_year ? 'AY '.$renewal->academic_year : null,
                'remarks_preview' => $this->renewalRemarksPreview($renewal),
                'detail_href' => $detail,
                'has_files' => $nFiles > 0,
                'files_href' => $detail.'#submitted-files',
                'sort_timestamp' => $renewal->updated_at?->getTimestamp() ?? 0,
                'row_actions' => $this->listRowActions(
                    $detail,
                    $nFiles > 0,
                    $detail.'#submitted-files',
                    [],
                ),
            ]);
        }

        foreach (ActivityCalendar::query()->where('organization_id', $organization->id)->orderByDesc('updated_at')->get() as $cal) {
            $sp = $this->submissionStatusPresentation($cal->calendar_status);
            $term = $this->activityCalendarTermLabel($cal->semester);
            $title = trim(($cal->academic_year ?? 'AY —').' · '.$term.' Activity Calendar');
            $hasFile = is_string($cal->calendar_file) && $cal->calendar_file !== '';
            // Keep this record under "Manage Organization -> Submitted Documents"
            // (do not jump back into the Activity Submission workflow module).
            $detail = $q(route('organizations.submitted-documents.calendars.show', $cal));
            $rows->push([
                'type_key' => 'activity_calendar',
                'type_label' => 'Activity Calendar',
                'title' => $title,
                'submitted_display' => optional($cal->submission_date)->format('M j, Y') ?? '—',
                'updated_display' => optional($cal->updated_at)->format('M j, Y') ?? '—',
                'status_raw' => $cal->calendar_status,
                'status_label' => $sp['label'],
                'status_variant' => $this->variantKeyFromBadge($sp['badge_class']),
                'academic_year' => $cal->academic_year,
                'academic_context' => trim(collect([$cal->academic_year ? 'AY '.$cal->academic_year : null, $term !== '—' ? $term : null])->filter()->implode(' · ')) ?: null,
                'remarks_preview' => null,
                'detail_href' => $detail,
                'has_files' => $hasFile,
                'files_href' => $detail.'#submitted-files',
                'sort_timestamp' => $cal->updated_at?->getTimestamp() ?? 0,
                'row_actions' => $this->listRowActions(
                    $detail,
                    $hasFile,
                    $detail.'#submitted-files',
                    [],
                ),
            ]);
        }

        foreach (ActivityProposal::query()->where('organization_id', $organization->id)->orderByDesc('updated_at')->get() as $proposal) {
            $sp = $this->submissionStatusPresentation($proposal->proposal_status, true);
            $nAttach = ($proposal->organization_logo_path ? 1 : 0)
                + ($proposal->resume_resource_persons_path ? 1 : 0)
                + ($proposal->external_funding_support_path ? 1 : 0);
            // Keep this record under "Manage Organization -> Submitted Documents"
            // so Back navigation remains in the Submitted Documents flow.
            $detail = $q(route('organizations.submitted-documents.proposals.show', $proposal));
            $rows->push([
                'type_key' => 'activity_proposal',
                'type_label' => 'Activity Proposal',
                'title' => ($proposal->activity_title ?: 'Activity Proposal').' — Proposal',
                'submitted_display' => optional($proposal->submission_date)->format('M j, Y') ?? '—',
                'updated_display' => optional($proposal->updated_at)->format('M j, Y') ?? '—',
                'status_raw' => $proposal->proposal_status,
                'status_label' => $sp['label'],
                'status_variant' => $this->variantKeyFromBadge($sp['badge_class']),
                'academic_year' => $proposal->academic_year,
                'academic_context' => $proposal->academic_year ? 'AY '.$proposal->academic_year : null,
                'remarks_preview' => $this->truncatePreview($proposal->overall_goal ?? $proposal->activity_description, 120),
                'detail_href' => $detail,
                'has_files' => $nAttach > 0,
                'files_href' => $detail.'#submitted-files',
                'sort_timestamp' => $proposal->updated_at?->getTimestamp() ?? 0,
                'row_actions' => $this->listRowActions(
                    $detail,
                    $nAttach > 0,
                    $detail.'#submitted-files',
                    [
                        ['label' => 'Submit another proposal', 'href' => $q(route('organizations.activity-proposal-submission'))],
                    ],
                ),
            ]);
        }

        foreach (ActivityReport::query()->where('organization_id', $organization->id)->orderByDesc('updated_at')->get() as $report) {
            $sp = $this->submissionStatusPresentation($report->report_status, false, 'report');
            $photoCount = is_array($report->supporting_photo_paths) ? count(array_filter($report->supporting_photo_paths, fn ($p) => is_string($p) && $p !== '')) : 0;
            $nFiles = ($report->poster_image_path ? 1 : 0) + $photoCount
                + ($report->certificate_sample_path ? 1 : 0)
                + ($report->evaluation_form_sample_path ? 1 : 0)
                + ($report->attendance_sheet_path ? 1 : 0);
            $eventTitle = $report->event_name ?? $report->activity_event_title ?? 'Event';
            $detail = $q(route('organizations.submitted-documents.reports.show', $report));
            $rows->push([
                'type_key' => 'after_activity_report',
                'type_label' => 'After Activity Report',
                'title' => 'After Activity Report — '.$eventTitle,
                'submitted_display' => optional($report->report_submission_date)->format('M j, Y') ?? '—',
                'updated_display' => optional($report->updated_at)->format('M j, Y') ?? '—',
                'status_raw' => $report->report_status,
                'status_label' => $sp['label'],
                'status_variant' => $this->variantKeyFromBadge($sp['badge_class']),
                'academic_year' => null,
                'academic_context' => null,
                'remarks_preview' => $this->truncatePreview($report->evaluation_report, 120),
                'detail_href' => $detail,
                'has_files' => $nFiles > 0,
                'files_href' => $detail.'#submitted-files',
                'sort_timestamp' => $report->updated_at?->getTimestamp() ?? 0,
                'row_actions' => $this->listRowActions(
                    $detail,
                    $nFiles > 0,
                    $detail.'#submitted-files',
                    [
                        ['label' => 'Submit another report', 'href' => $q(route('organizations.after-activity-report'))],
                    ],
                ),
            ]);
        }

        return $rows;
    }

    private function variantKeyFromBadge(string $badgeClass): string
    {
        if (str_contains($badgeClass, 'slate-200')) {
            return 'draft';
        }
        if (str_contains($badgeClass, 'amber')) {
            return 'pending';
        }
        if (str_contains($badgeClass, 'orange')) {
            return 'revision';
        }
        if (str_contains($badgeClass, 'emerald')) {
            return 'approved';
        }
        if (str_contains($badgeClass, 'rose')) {
            return 'rejected';
        }
        if (str_contains($badgeClass, 'blue')) {
            return 'review';
        }

        return 'neutral';
    }

    /**
     * @param  list<array{label: string, href: string}>  $extra
     * @return list<array{label: string, href: string, style: string}>
     */
    private function listRowActions(string $detailHref, bool $hasFiles, string $filesHref, array $extra = []): array
    {
        $actions = [
            ['label' => 'View details', 'href' => $detailHref, 'style' => 'primary'],
        ];
        if ($hasFiles) {
            $actions[] = ['label' => 'View files', 'href' => $filesHref, 'style' => 'secondary'];
        }
        foreach ($extra as $e) {
            $actions[] = ['label' => $e['label'], 'href' => $e['href'], 'style' => 'secondary'];
        }

        return $actions;
    }
}
