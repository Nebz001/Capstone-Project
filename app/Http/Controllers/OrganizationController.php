<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\ActivityCalendar;
use App\Models\ActivityCalendarEntry;
use App\Models\ActivityProposal;
use App\Models\ActivityReport;
use App\Models\ActivityRequestForm;
use App\Models\ApprovalLog;
use App\Models\ApprovalWorkflowStep;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\OrganizationAdviser;
use App\Models\OrganizationOfficer;
use App\Models\OrganizationProfileRevision;
use App\Models\ModuleRevisionFieldUpdate;
use App\Models\OrganizationRevisionFieldUpdate;
use App\Models\OrganizationSubmission;
use App\Models\ProposalBudgetItem;
use App\Models\Role;
use App\Models\SubmissionRequirement;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AdviserAssignmentAvailability;
use App\Services\OrganizationNotificationService;
use App\Services\OrganizationRegistrationRevisionSummaryService;
use App\Support\OrganizationStoragePath;
use App\Support\SubmissionRoutingProgress;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    /** Adviser-eligible role names in `roles.name`. */
    private const ADVISER_ROLE_NAMES = ['adviser'];

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

    /** Registration requirements that must always be selected and uploaded. */
    private const REGISTRATION_REQUIRED_REQUIREMENT_KEYS = [
        'letter_of_intent',
        'application_form',
        'by_laws',
        'updated_list_of_officers_founders',
        'dean_endorsement_faculty_adviser',
        'proposed_projects_budget',
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

    /** Renewal requirements that must always be selected and uploaded. */
    private const RENEWAL_REQUIRED_REQUIREMENT_KEYS = [
        'letter_of_intent',
        'application_form',
        'by_laws_updated_if_applicable',
        'updated_list_of_officers_founders_ay',
        'dean_endorsement_faculty_adviser',
        'proposed_projects_budget',
    ];

    private const REQUIREMENT_FILE_MAX_KB = 2048;

    private const REQUIREMENT_FILE_MAX_MB = 2;

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
        if ($request->user()?->isRoleBasedApprover()) {
            return redirect()->route('approver.dashboard');
        }

        $calendarEvents = [];
        $proposalDashboard = ['empty' => true];
        $submissionDashboard = ['empty' => true];
        $dashboardWorkflows = [];
        $dashboardSelectedWorkflowId = '';
        $user = $request->user();
        if ($user) {
            $organization = $user->currentOrganization();
            if ($organization) {
                $calendarEvents = $this->buildOrganizationDashboardCalendarEvents($organization);
                $submissionDashboard = $this->buildSubmissionStatusDashboard($organization);
                $moduleWorkflows = $this->buildAllModuleWorkflows($organization);
                $presentation = $this->buildDashboardWorkflowPresentation($request, $submissionDashboard, $moduleWorkflows);
                $dashboardWorkflows = $presentation['workflows'];
                $dashboardSelectedWorkflowId = $presentation['selected_id'];
                $selectedProposalId = (int) $request->integer('proposal_id');
                [$featuredProposal, $usedDefaultOldestActive] = $this->resolveDashboardFeaturedProposal($organization, $selectedProposalId);
                $proposalDashboard = $this->buildProposalDashboardProgress($featuredProposal);
                $proposalDashboard['selector_options'] = $this->dashboardProposalSelectorOptions($organization);
                $proposalDashboard['selected_proposal_id'] = $featuredProposal?->id;
                $proposalDashboard['default_note'] = $usedDefaultOldestActive
                    ? 'Default view: oldest proposal still in the approval process.'
                    : null;
            }
        }

        return view('organizations.index', [
            'calendarEvents' => $calendarEvents,
            'proposalDashboard' => $proposalDashboard,
            'submissionDashboard' => $submissionDashboard,
            'dashboardWorkflows' => $dashboardWorkflows,
            'dashboardSelectedWorkflowId' => $dashboardSelectedWorkflowId,
        ]);
    }

    /**
     * Registration application section fields that are system context only (must not drive org-side revisions).
     */
    private function isNonReviewableApplicationRegistrationField(string $sectionKey, string $fieldKey): bool
    {
        return $sectionKey === 'application'
            && in_array($fieldKey, ['academic_year', 'submission_date', 'submitted_by'], true);
    }

    private function isNonReviewableOrganizationalRegistrationField(string $sectionKey, string $fieldKey): bool
    {
        return $sectionKey === 'organizational'
            && in_array($fieldKey, ['date_organized', 'founded_date', 'founded_at', 'date_created', 'created_at'], true);
    }

    /**
     * @return array{
     *   empty: bool,
     *   workflow_id?: string,
     *   selector_label?: string,
     *   effective_status_key?: string,
     *   details_url?: string,
     *   sort_ts?: int,
     *   title?: string,
     *   status_label?: string,
     *   status_badge_class?: string,
     *   stages?: list<array{label:string,state:string}>,
     *   status_message?: string,
     *   info_revisions?: list<array{field:string,note:string,href:string}>,
     *   file_revisions?: list<array{field:string,note:string,href:string}>,
     *   info_updated_under_review?: list<array{field:string,href:string,old_value:string,new_value:string}>,
     *   file_updated_under_review?: list<array{field:string,href:string,file_name:string}>,
     *   actions?: list<array{label:string,href:string,variant:string}>
     * }
     */
    private function buildSubmissionStatusDashboard(Organization $organization): array
    {
        $submission = $organization->submissions()
            ->whereIn('type', [OrganizationSubmission::TYPE_REGISTRATION, OrganizationSubmission::TYPE_RENEWAL])
            ->whereNotIn('status', [OrganizationSubmission::STATUS_DRAFT])
            ->with('academicTerm')
            ->latest('submission_date')
            ->latest('id')
            ->first();
        if (! $submission) {
            return ['empty' => true];
        }

        $fieldReviews = $submission->isRenewal()
            ? (is_array($submission->renewal_field_reviews) ? $submission->renewal_field_reviews : [])
            : (is_array($submission->registration_field_reviews) ? $submission->registration_field_reviews : []);
        $pendingUpdateRows = OrganizationRevisionFieldUpdate::query()
            ->where('organization_submission_id', $submission->id)
            ->whereNull('acknowledged_at')
            ->get(['section_key', 'field_key', 'old_value', 'new_value', 'new_file_meta']);
        $pendingSet = [];
        $pendingMetaByKey = [];
        foreach ($pendingUpdateRows as $row) {
            if ($this->isNonReviewableApplicationRegistrationField((string) ($row->section_key ?? ''), (string) ($row->field_key ?? ''))) {
                continue;
            }
            if ($this->isNonReviewableOrganizationalRegistrationField((string) ($row->section_key ?? ''), (string) ($row->field_key ?? ''))) {
                continue;
            }
            $compound = (string) $row->section_key.'.'.(string) $row->field_key;
            $pendingSet[$compound] = true;
            $pendingMetaByKey[$compound] = [
                'old_value' => trim((string) ($row->old_value ?? '')),
                'new_value' => trim((string) ($row->new_value ?? '')),
                'new_file_meta' => is_array($row->new_file_meta) ? $row->new_file_meta : [],
            ];
        }

        $infoRevisionsByKey = [];
        $fileRevisionsByKey = [];
        $infoUpdatedByKey = [];
        $fileUpdatedByKey = [];
        foreach ($pendingUpdateRows as $row) {
            $sectionKey = (string) ($row->section_key ?? '');
            $fieldKey = (string) ($row->field_key ?? '');
            if ($this->isNonReviewableApplicationRegistrationField($sectionKey, $fieldKey)) {
                continue;
            }
            if ($this->isNonReviewableOrganizationalRegistrationField($sectionKey, $fieldKey)) {
                continue;
            }
            $compoundKey = $sectionKey.'.'.$fieldKey;
            $label = trim((string) data_get($fieldReviews, $sectionKey.'.'.$fieldKey.'.label', ucwords(str_replace('_', ' ', $fieldKey))));
            if ($sectionKey === 'requirements') {
                $fileHref = route('organizations.submitted-documents.registrations.show', $submission).'?from=dashboard&revision_target=revision-file-'.$this->sanitizeAnchorSegment($fieldKey);
                $newFileMeta = is_array($row->new_file_meta) ? $row->new_file_meta : [];
                $newFileName = trim((string) ($newFileMeta['original_name'] ?? $newFileMeta['file_name'] ?? $newFileMeta['filename'] ?? ''));
                $fileUpdatedByKey[$fieldKey] = [
                    'field' => $label !== '' ? $label : ucwords(str_replace('_', ' ', $fieldKey)),
                    'href' => $fileHref,
                    'file_name' => $newFileName !== '' ? $newFileName : 'Updated file uploaded',
                ];

                continue;
            }

            $anchor = 'revision-field-'.$this->sanitizeAnchorSegment($sectionKey).'-'.$this->sanitizeAnchorSegment($fieldKey);
            $infoHref = route('organizations.profile', ['edit' => 1, 'from' => 'dashboard']).'#'.$anchor;
            $infoUpdatedByKey[$compoundKey] = [
                'field' => $label !== '' ? $label : ucwords(str_replace('_', ' ', $fieldKey)),
                'href' => $infoHref,
                'old_value' => trim((string) ($row->old_value ?? '')),
                'new_value' => trim((string) ($row->new_value ?? '')),
            ];
        }
        foreach ($fieldReviews as $sectionKey => $fields) {
            if (! is_array($fields)) {
                continue;
            }
            foreach ($fields as $fieldKey => $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (str_contains(strtolower((string) $sectionKey), 'adviser')
                    && in_array(strtolower((string) $fieldKey), ['status', 'adviser_status'], true)) {
                    continue;
                }
                if ($this->isNonReviewableApplicationRegistrationField((string) $sectionKey, (string) $fieldKey)) {
                    continue;
                }
                if ($this->isNonReviewableOrganizationalRegistrationField((string) $sectionKey, (string) $fieldKey)) {
                    continue;
                }
                $status = strtolower(trim((string) ($row['status'] ?? 'pending')));
                if (! in_array($status, ['flagged', 'revision', 'needs_revision', 'for_revision'], true)) {
                    continue;
                }
                $note = $row['note'] ?? null;
                if (! $this->isNonEmptyProfileRevisionNote($note)) {
                    continue;
                }
                $note = trim((string) $note);
                $label = trim((string) ($row['label'] ?? ucwords(str_replace('_', ' ', (string) $fieldKey))));
                [$compoundKey, $anchor] = $this->organizationDashboardRevisionIdentity((string) $sectionKey, (string) $fieldKey, $label);

                if ((string) $sectionKey === 'requirements') {
                    $fileHref = route('organizations.submitted-documents.registrations.show', $submission).'?from=dashboard&revision_target=revision-file-'.$this->sanitizeAnchorSegment((string) $fieldKey);
                    if (isset($pendingSet[$compoundKey])) {
                        // Keep as completed/updated under review; do not duplicate as active.
                    } else {
                        $fileRevisionsByKey[(string) $fieldKey] = [
                            'field' => $label,
                            'note' => $note,
                            'href' => $fileHref,
                        ];
                    }

                    continue;
                }

                $infoHref = route('organizations.profile', ['edit' => 1, 'from' => 'dashboard']).'#'.$anchor;
                if (isset($pendingSet[$compoundKey])) {
                    // Keep as completed/updated under review; do not duplicate as active.
                } else {
                    $infoRevisionsByKey[$compoundKey] = [
                        'field' => $label,
                        'note' => $note,
                        'href' => $infoHref,
                    ];
                }
            }
        }

        $hasActiveInfo = $infoRevisionsByKey !== [];
        $hasActiveFiles = $fileRevisionsByKey !== [];
        $hasUpdatedUnderReview = $infoUpdatedByKey !== [] || $fileUpdatedByKey !== [];
        $statusKey = strtoupper((string) $submission->legacyStatus());
        if ($hasActiveInfo || $hasActiveFiles) {
            $statusKey = 'REVISION';
        } elseif ($hasUpdatedUnderReview) {
            // Persisted officer resubmissions exist, so this remains in SDAO review
            // until an admin saves a new review decision.
            $statusKey = 'RESUBMITTED';
        }
        $presentation = $this->submissionDashboardStatusPresentation($statusKey);
        $routingKey = $statusKey === 'RESUBMITTED' ? 'UNDER_REVIEW' : $statusKey;

        $actions = [];
        if ($hasActiveInfo) {
            $actions[] = [
                'label' => 'Edit Required Information',
                'href' => route('organizations.profile', ['edit' => 1, 'from' => 'dashboard']),
                'variant' => 'primary',
            ];
        }
        if ($hasActiveFiles) {
            $firstFileHref = reset($fileRevisionsByKey)['href'] ?? route('organizations.submitted-documents.registrations.show', $submission).'?from=dashboard';
            $actions[] = [
                'label' => 'Replace Required Files',
                'href' => $firstFileHref,
                'variant' => 'secondary',
            ];
        }
        $detailsUrl = route($submission->isRenewal() ? 'organizations.submitted-documents.renewals.show' : 'organizations.submitted-documents.registrations.show', $submission);

        $actions[] = [
            'label' => 'View Submission Details',
            'href' => $detailsUrl.'?from=dashboard',
            'variant' => $actions === [] ? 'primary' : 'secondary',
        ];

        $selectorLabel = $submission->isRenewal() ? 'Organization Renewal' : 'Organization Registration';
        if ($submission->academicTerm) {
            $ay = trim((string) ($submission->academicTerm->academic_year ?? ''));
            $sem = trim((string) ($submission->academicTerm->semester ?? ''));
            if ($ay !== '' || $sem !== '') {
                $selectorLabel .= ' — '.trim($ay.($sem !== '' ? ' / '.$sem : ''));
            }
        }

        return [
            'empty' => false,
            'workflow_id' => $submission->isRenewal() ? 'renewal-'.$submission->id : 'registration-'.$submission->id,
            'selector_label' => $selectorLabel,
            'effective_status_key' => $statusKey,
            'details_url' => $detailsUrl,
            'sort_ts' => $submission->updated_at?->getTimestamp() ?? $submission->submission_date?->getTimestamp() ?? 0,
            'title' => $submission->isRenewal() ? 'Organization Renewal' : 'Organization Registration',
            'status_label' => $presentation['label'],
            'status_badge_class' => $presentation['badge_class'],
            'stages' => SubmissionRoutingProgress::stagesForSimpleSdaoPipeline($routingKey),
            'status_message' => $this->submissionDashboardStatusMessage($statusKey),
            'info_revisions' => array_values($infoRevisionsByKey),
            'file_revisions' => array_values($fileRevisionsByKey),
            'info_updated_under_review' => array_values($infoUpdatedByKey),
            'file_updated_under_review' => array_values($fileUpdatedByKey),
            'actions' => $actions,
        ];
    }

    /**
     * @return array{label:string,badge_class:string}
     */
    private function submissionDashboardStatusPresentation(string $raw): array
    {
        return match (strtoupper(trim($raw))) {
            'REVISION', 'REVISION_REQUIRED' => ['label' => 'For Revision', 'badge_class' => 'bg-orange-100 text-orange-800 border border-orange-200'],
            'RESUBMITTED' => ['label' => 'Resubmitted', 'badge_class' => 'bg-blue-100 text-blue-800 border border-blue-200'],
            'PENDING', 'UNDER_REVIEW' => ['label' => 'Pending SDAO Review', 'badge_class' => 'bg-amber-100 text-amber-800 border border-amber-200'],
            'APPROVED' => ['label' => 'Approved', 'badge_class' => 'bg-emerald-100 text-emerald-800 border border-emerald-200'],
            default => ['label' => 'Pending SDAO Review', 'badge_class' => 'bg-slate-100 text-slate-700 border border-slate-200'],
        };
    }

    private function submissionDashboardStatusMessage(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'REVISION', 'REVISION_REQUIRED' => 'SDAO requested changes. Update the listed fields or files and resubmit for review.',
            'RESUBMITTED' => 'SDAO is currently reviewing your updated submission.',
            'APPROVED' => 'Your organization registration has been approved.',
            default => 'SDAO is reviewing your submission.',
        };
    }

    /**
     * Build workflow cards for every submitted module (calendars, proposals, reports).
     *
     * Registration/renewal is still handled separately by buildSubmissionStatusDashboard()
     * and rendered as the first card in the view via $submissionDashboard.
     *
     * @return list<array{
     *     id: string,
     *     selector_label: string,
     *     effective_status_key: string,
     *     sort_ts: int,
     *     module: string,
     *     title: string,
     *     status_label: string,
     *     status_badge_class: string,
     *     stages: list<array{label: string, state: string}>,
     *     status_message: string,
     *     details_url: string|null,
     *     info_revisions: list<array{field: string, note: string, href: string}>,
     *     file_revisions: list<array{field: string, note: string, href: string}>,
     *     info_updated_under_review: list<array{field: string, href: string, old_value: string, new_value: string}>,
     *     file_updated_under_review: list<array{field: string, href: string, file_name: string}>,
     * }>
     */
    private function buildAllModuleWorkflows(Organization $organization): array
    {
        $workflows = [];

        $calendars = $organization->activityCalendars()
            ->whereNotIn('status', ['draft'])
            ->with('academicTerm')
            ->latest('submission_date')
            ->latest('id')
            ->get();

        foreach ($calendars as $calendar) {
            $calTitle = $this->activityCalendarCardTitle($calendar);
            $workflows[] = $this->buildModuleWorkflowCard(
                module: 'activity_calendar',
                title: $calTitle,
                record: $calendar,
                statusKey: strtoupper((string) ($calendar->status ?? 'PENDING')),
                workflowId: 'activity-calendar-'.$calendar->id,
                selectorLabel: $calTitle,
                detailsUrl: route('organizations.submitted-documents.calendars.show', $calendar->id),
            );
        }

        $proposals = $organization->activityProposals()
            ->whereNotIn('status', ['draft'])
            ->latest('submission_date')
            ->latest('id')
            ->get();

        foreach ($proposals as $proposal) {
            $statusKey = strtoupper((string) ($proposal->status ?? 'PENDING'));
            $propTitle = 'Activity Proposal — '.($proposal->activity_title ?: 'Untitled');
            $workflows[] = $this->buildModuleWorkflowCard(
                module: 'activity_proposal',
                title: $propTitle,
                record: $proposal,
                statusKey: $statusKey,
                workflowId: 'activity-proposal-'.$proposal->id,
                selectorLabel: $propTitle,
                detailsUrl: route('organizations.submitted-documents.proposals.show', $proposal->id),
                stagesOverride: SubmissionRoutingProgress::stagesForActivityProposal($proposal),
                messageOverride: SubmissionRoutingProgress::summaryForActivityProposal($statusKey),
            );
        }

        $reports = $organization->activityReports()
            ->whereNotIn('status', ['draft'])
            ->latest('report_submission_date')
            ->latest('id')
            ->get();

        foreach ($reports as $report) {
            $statusKey = strtoupper((string) ($report->status ?? 'PENDING'));
            $reportTitle = 'After Activity Report — '.($report->event_title ?: 'Untitled');
            $workflows[] = $this->buildModuleWorkflowCard(
                module: 'activity_report',
                title: $reportTitle,
                record: $report,
                statusKey: $statusKey,
                workflowId: 'after-activity-report-'.$report->id,
                selectorLabel: $reportTitle,
                detailsUrl: route('organizations.submitted-documents.reports.show', $report->id),
                stagesOverride: SubmissionRoutingProgress::stagesForActivityReport($statusKey),
                messageOverride: SubmissionRoutingProgress::summaryForActivityReport($statusKey),
            );
        }

        return $workflows;
    }

    /**
     * @param  list<array{label: string, state: string}>|null  $stagesOverride
     * @return array{
     *     id: string,
     *     selector_label: string,
     *     effective_status_key: string,
     *     sort_ts: int,
     *     module: string,
     *     title: string,
     *     status_label: string,
     *     status_badge_class: string,
     *     stages: list<array{label: string, state: string}>,
     *     status_message: string,
     *     details_url: string|null,
     *     info_revisions: list<array{field: string, note: string, href: string}>,
     *     file_revisions: list<array{field: string, note: string, href: string}>,
     *     info_updated_under_review: list<array{field: string, href: string, old_value: string, new_value: string}>,
     *     file_updated_under_review: list<array{field: string, href: string, file_name: string}>,
     * }
     */
    private function buildModuleWorkflowCard(
        string $module,
        string $title,
        Model $record,
        string $statusKey,
        string $workflowId,
        string $selectorLabel,
        ?string $detailsUrl = null,
        ?array $stagesOverride = null,
        ?string $messageOverride = null,
    ): array {
        $revisionData = $this->buildModuleRevisionData($record, $detailsUrl ?? '#');

        $effectiveStatus = $statusKey;
        if ($revisionData['has_active_info'] || $revisionData['has_active_files']) {
            $effectiveStatus = 'REVISION';
        } elseif ($revisionData['has_updated']) {
            $effectiveStatus = 'RESUBMITTED';
        }

        $presentation = $this->submissionDashboardStatusPresentation($effectiveStatus);
        $routingKey = $effectiveStatus === 'RESUBMITTED' ? 'UNDER_REVIEW' : $effectiveStatus;

        return [
            'id' => $workflowId,
            'selector_label' => $selectorLabel,
            'effective_status_key' => $effectiveStatus,
            'sort_ts' => $this->dashboardWorkflowSortTimestamp($record),
            'module' => $module,
            'title' => $title,
            'status_label' => $presentation['label'],
            'status_badge_class' => $presentation['badge_class'],
            'stages' => $stagesOverride ?? SubmissionRoutingProgress::stagesForSimpleSdaoPipeline($routingKey),
            'status_message' => $messageOverride ?? $this->moduleWorkflowStatusMessage($module, $effectiveStatus),
            'details_url' => $detailsUrl,
            'info_revisions' => $revisionData['info_revisions'],
            'file_revisions' => $revisionData['file_revisions'],
            'info_updated_under_review' => $revisionData['info_updated'],
            'file_updated_under_review' => $revisionData['file_updated'],
        ];
    }

    private function dashboardWorkflowSortTimestamp(Model $record): int
    {
        if ($record instanceof ActivityCalendar) {
            return $record->updated_at?->getTimestamp()
                ?? $record->submission_date?->getTimestamp()
                ?? 0;
        }
        if ($record instanceof ActivityProposal) {
            return $record->updated_at?->getTimestamp()
                ?? $record->submission_date?->getTimestamp()
                ?? 0;
        }
        if ($record instanceof ActivityReport) {
            return $record->updated_at?->getTimestamp()
                ?? $record->report_submission_date?->getTimestamp()
                ?? 0;
        }

        return (int) ($record->updated_at?->getTimestamp() ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $moduleWorkflows
     * @return array{workflows: list<array<string, mixed>>, selected_id: string}
     */
    private function buildDashboardWorkflowPresentation(Request $request, array $submissionDashboard, array $moduleWorkflows): array
    {
        $rows = [];
        if (empty($submissionDashboard['empty'])) {
            $rows[] = [
                'id' => (string) ($submissionDashboard['workflow_id'] ?? ''),
                'selector_label' => (string) ($submissionDashboard['selector_label'] ?? $submissionDashboard['title'] ?? ''),
                'effective_status_key' => (string) ($submissionDashboard['effective_status_key'] ?? ''),
                'sort_ts' => (int) ($submissionDashboard['sort_ts'] ?? 0),
                'kind' => 'organization_submission',
                'title' => $submissionDashboard['title'] ?? '',
                'status_label' => $submissionDashboard['status_label'] ?? '',
                'status_badge_class' => $submissionDashboard['status_badge_class'] ?? '',
                'stages' => $submissionDashboard['stages'] ?? [],
                'status_message' => $submissionDashboard['status_message'] ?? '',
                'info_revisions' => $submissionDashboard['info_revisions'] ?? [],
                'file_revisions' => $submissionDashboard['file_revisions'] ?? [],
                'info_updated_under_review' => $submissionDashboard['info_updated_under_review'] ?? [],
                'file_updated_under_review' => $submissionDashboard['file_updated_under_review'] ?? [],
                'details_url' => $submissionDashboard['details_url'] ?? null,
            ];
        }
        foreach ($moduleWorkflows as $mw) {
            if (! is_array($mw)) {
                continue;
            }
            $rows[] = array_merge($mw, ['kind' => 'module']);
        }

        $validIds = array_values(array_filter(array_map(fn ($r) => is_array($r) ? ($r['id'] ?? null) : null, $rows)));

        $selected = trim((string) $request->query('workflow', ''));
        if ($selected === '' || ! in_array($selected, $validIds, true)) {
            $selected = $this->resolveDefaultDashboardWorkflowId($rows) ?? ($validIds[0] ?? '');
        }

        foreach ($rows as $i => $row) {
            if (! is_array($rows[$i])) {
                continue;
            }
            $rows[$i]['selected'] = (($rows[$i]['id'] ?? '') === $selected) && $selected !== '';
            $sl = trim((string) ($rows[$i]['selector_label'] ?? $rows[$i]['title'] ?? ''));
            $badge = trim((string) ($rows[$i]['status_label'] ?? ''));
            $rows[$i]['selector_option_text'] = trim($sl.($badge !== '' ? ' — '.$badge : ''));
        }

        return ['workflows' => $rows, 'selected_id' => $selected];
    }

    /**
     * @param  list<array<string, mixed>>  $workflows
     */
    private function resolveDefaultDashboardWorkflowId(array $workflows): ?string
    {
        if ($workflows === []) {
            return null;
        }

        $pickFirst = function (callable $predicate) use ($workflows): ?string {
            foreach ($workflows as $w) {
                if (! is_array($w) || empty($w['id'])) {
                    continue;
                }
                if ($predicate($w)) {
                    return (string) $w['id'];
                }
            }

            return null;
        };

        $id = $pickFirst(function (array $w): bool {
            $k = strtoupper(trim((string) ($w['effective_status_key'] ?? '')));

            return in_array($k, ['REVISION', 'REVISION_REQUIRED'], true);
        });
        if ($id !== null) {
            return $id;
        }

        $id = $pickFirst(fn (array $w): bool => strtoupper(trim((string) ($w['effective_status_key'] ?? ''))) === 'RESUBMITTED');
        if ($id !== null) {
            return $id;
        }

        $id = $pickFirst(function (array $w): bool {
            $k = strtoupper(trim((string) ($w['effective_status_key'] ?? '')));

            return in_array($k, ['PENDING', 'UNDER_REVIEW', 'REVIEWED'], true);
        });
        if ($id !== null) {
            return $id;
        }

        $id = $pickFirst(fn (array $w): bool => strtoupper(trim((string) ($w['effective_status_key'] ?? ''))) === 'REJECTED');
        if ($id !== null) {
            return $id;
        }

        usort($workflows, function ($a, $b): int {
            $ta = is_array($a) ? (int) ($a['sort_ts'] ?? 0) : 0;
            $tb = is_array($b) ? (int) ($b['sort_ts'] ?? 0) : 0;

            return $tb <=> $ta;
        });

        foreach ($workflows as $w) {
            if (is_array($w) && ! empty($w['id'])) {
                return (string) $w['id'];
            }
        }

        return null;
    }

    /**
     * Build revision/updated item lists for a non-registration reviewable module.
     *
     * @return array{
     *     has_active_info: bool,
     *     has_active_files: bool,
     *     has_updated: bool,
     *     info_revisions: list<array{field: string, note: string, href: string}>,
     *     file_revisions: list<array{field: string, note: string, href: string}>,
     *     info_updated: list<array{field: string, href: string, old_value: string, new_value: string}>,
     *     file_updated: list<array{field: string, href: string, file_name: string}>,
     * }
     */
    private function buildModuleRevisionData(Model $record, string $baseHref): array
    {
        $fieldReviews = is_array($record->admin_field_reviews ?? null) ? $record->admin_field_reviews : [];
        $pendingUpdateRows = ModuleRevisionFieldUpdate::query()
            ->where('reviewable_type', $record->getMorphClass())
            ->where('reviewable_id', (int) $record->getKey())
            ->whereNull('acknowledged_at')
            ->get(['section_key', 'field_key', 'old_value', 'new_value', 'new_file_meta']);

        $pendingSet = [];
        foreach ($pendingUpdateRows as $row) {
            $pendingSet[(string) $row->section_key.'.'.(string) $row->field_key] = true;
        }

        $infoRevisions = [];
        $fileRevisions = [];
        $infoUpdated = [];
        $fileUpdated = [];

        foreach ($pendingUpdateRows as $row) {
            $sectionKey = (string) ($row->section_key ?? '');
            $fieldKey = (string) ($row->field_key ?? '');
            $label = ucwords(str_replace('_', ' ', $fieldKey));
            $href = $baseHref.'?from=dashboard';

            if (in_array($sectionKey, ['requirements', 'files', 'attachments'], true)) {
                $newFileMeta = is_array($row->new_file_meta) ? $row->new_file_meta : [];
                $newFileName = trim((string) ($newFileMeta['original_name'] ?? $newFileMeta['file_name'] ?? $newFileMeta['filename'] ?? ''));
                $fileUpdated[] = [
                    'field' => $label,
                    'href' => $href,
                    'file_name' => $newFileName !== '' ? $newFileName : 'Updated file uploaded',
                ];
            } else {
                $infoUpdated[] = [
                    'field' => $label,
                    'href' => $href,
                    'old_value' => trim((string) ($row->old_value ?? '')),
                    'new_value' => trim((string) ($row->new_value ?? '')),
                ];
            }
        }

        foreach ($fieldReviews as $sectionKey => $fields) {
            if (! is_array($fields)) {
                continue;
            }
            foreach ($fields as $fieldKey => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $status = strtolower(trim((string) ($row['status'] ?? 'pending')));
                if (! in_array($status, ['flagged', 'revision', 'needs_revision', 'for_revision'], true)) {
                    continue;
                }
                $note = $row['note'] ?? null;
                $noteText = is_scalar($note) ? trim((string) $note) : '';
                if ($noteText === '' || (preg_match('/^(0+)(\\.0+)?$/', $noteText) === 1)) {
                    continue;
                }
                $label = trim((string) ($row['label'] ?? ucwords(str_replace('_', ' ', (string) $fieldKey))));
                $compoundKey = (string) $sectionKey.'.'.(string) $fieldKey;
                $href = $baseHref.'?from=dashboard';

                if (isset($pendingSet[$compoundKey])) {
                    continue;
                }

                if (in_array((string) $sectionKey, ['requirements', 'files', 'attachments'], true)) {
                    $fileRevisions[] = ['field' => $label, 'note' => $noteText, 'href' => $href];
                } else {
                    $infoRevisions[] = ['field' => $label, 'note' => $noteText, 'href' => $href];
                }
            }
        }

        return [
            'has_active_info' => $infoRevisions !== [],
            'has_active_files' => $fileRevisions !== [],
            'has_updated' => $infoUpdated !== [] || $fileUpdated !== [],
            'info_revisions' => $infoRevisions,
            'file_revisions' => $fileRevisions,
            'info_updated' => $infoUpdated,
            'file_updated' => $fileUpdated,
        ];
    }

    private function moduleWorkflowStatusMessage(string $module, string $status): string
    {
        $u = strtoupper(trim($status));

        if (in_array($u, ['REVISION', 'REVISION_REQUIRED'], true)) {
            return 'SDAO requested changes. Update the listed fields or files and resubmit for review.';
        }
        if ($u === 'RESUBMITTED') {
            return 'Your updated submission has been submitted and is now awaiting review.';
        }
        if ($u === 'APPROVED') {
            return 'This submission has been approved.';
        }
        if ($u === 'REJECTED') {
            return 'This submission was not approved. Check remarks for details.';
        }

        return match ($module) {
            'activity_calendar' => 'SDAO is currently reviewing your submitted activity calendar.',
            'activity_proposal' => 'Your activity proposal is currently being reviewed by the assigned approver.',
            'activity_report' => 'Your after activity report is currently under review.',
            default => 'SDAO is reviewing your submission.',
        };
    }

    private function activityCalendarCardTitle(ActivityCalendar $calendar): string
    {
        $label = 'Activity Calendar';
        if ($calendar->relationLoaded('academicTerm') && $calendar->academicTerm) {
            $term = $calendar->academicTerm;
            $ay = $term->academic_year ?? '';
            $sem = $term->semester ?? '';
            if ($ay !== '' || $sem !== '') {
                $label .= ' — '.trim($ay.($sem !== '' ? ' / '.$sem : ''));
            }
        }

        return $label;
    }

    /**
     * @return list<array{title: string, start: string, end: string|null, status: string, time: string|null, venue: string|null}>
     */
    private function buildOrganizationDashboardCalendarEvents(Organization $organization): array
    {
        $events = [];

        $proposals = $organization->activityProposals()
            ->whereNotIn('status', ['draft'])
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

            $normalizedStatus = strtoupper((string) ($proposal->status ?? 'PENDING'));
            $calendarStatus = $normalizedStatus === 'APPROVED' ? 'scheduled' : 'pending';

            $events[] = [
                'title' => (string) $proposal->activity_title,
                'start' => $start,
                'end' => $end,
                'status' => $calendarStatus,
                'time' => $proposal->proposed_start_time ? (string) $proposal->proposed_start_time : null,
                'venue' => $proposal->venue ? (string) $proposal->venue : null,
            ];
        }

        return $events;
    }

    /**
     * Latest proposal the officer should monitor: prefer the most recently updated non-draft, else most recent draft.
     */
    /**
     * @return array{0: ?ActivityProposal, 1: bool}
     */
    private function resolveDashboardFeaturedProposal(Organization $organization, int $selectedProposalId = 0): array
    {
        $base = ActivityProposal::query()
            ->where('organization_id', $organization->id)
            ->whereNotIn('status', ['draft']);

        if ($selectedProposalId > 0) {
            $selected = (clone $base)
                ->whereKey($selectedProposalId)
                ->first();
            if ($selected) {
                return [$selected, false];
            }
        }

        $oldestActive = (clone $base)
            ->whereIn('status', ['pending', 'under_review', 'revision', 'revision_required'])
            ->orderBy('submission_date')
            ->orderBy('id')
            ->first();
        if ($oldestActive) {
            return [$oldestActive, true];
        }

        $fallback = (clone $base)
            ->orderBy('submission_date')
            ->orderBy('id')
            ->first();

        return [$fallback, false];
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    private function dashboardProposalSelectorOptions(Organization $organization): array
    {
        $proposals = ActivityProposal::query()
            ->where('organization_id', $organization->id)
            ->whereNotIn('status', ['draft'])
            ->orderBy('submission_date')
            ->orderBy('id')
            ->get();

        return $proposals->map(function (ActivityProposal $proposal): array {
            $status = $this->activityProposalStatusPresentation($proposal->status)['label'];

            return [
                'id' => (int) $proposal->id,
                'label' => sprintf('%s — %s', (string) $proposal->activity_title, $status),
            ];
        })->values()->all();
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

        $presentation = $this->activityProposalStatusPresentation($proposal->status);
        $summary = SubmissionRoutingProgress::summaryForActivityProposal($proposal->status);

        $stages = SubmissionRoutingProgress::stagesForActivityProposal($proposal);

        $statusUpper = strtoupper((string) ($proposal->status ?? ''));

        $primaryAction = null;
        if (in_array($statusUpper, ['REVISION', 'REVISION_REQUIRED'], true)) {
            $primaryAction = [
                'label' => 'Address revision',
                'href' => route('organizations.submitted-documents.activity-proposals.revise', $proposal),
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
            'status_raw' => $proposal->status,
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

        return view('organizations.register', compact(
            'officerValidationPending',
            'alreadyLinkedToOrganization'
        ));
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

        if ($user->effectiveRoleType() !== 'ORG_OFFICER') {
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
            'adviser_user_id' => ['required', 'integer', 'exists:users,id'],
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
        ], $this->requirementFileRules($request, self::REGISTRATION_REQUIREMENT_KEYS)), array_merge([
            'requirements.required' => self::REQUIREMENTS_MIN_ONE_MESSAGE,
            'requirements.min' => self::REQUIREMENTS_MIN_ONE_MESSAGE,
        ], $this->requirementFileValidationMessages()));

        if (! $this->isEligibleAdviserUserId((int) $validated['adviser_user_id'])) {
            return back()->withErrors([
                'adviser_user_id' => 'Selected faculty adviser is not eligible.',
            ])->withInput();
        }

        if (app(AdviserAssignmentAvailability::class)->isAdviserUnavailable((int) $validated['adviser_user_id'], null)) {
            return back()->withErrors([
                'adviser_user_id' => AdviserAssignmentAvailability::VALIDATION_MESSAGE,
            ])->withInput();
        }

        $this->enforceRequiredRequirementSelectionsAndFiles(
            $request,
            self::REGISTRATION_REQUIRED_REQUIREMENT_KEYS
        );

        $validated['contact_no'] = $this->normalizePhilippineContactNo($validated['contact_no']);

        $reqs = collect($validated['requirements'] ?? []);

        $submission = null;

        DB::transaction(function () use ($validated, $reqs, $user, $request, $forAdmin, &$submission): void {
            $organization = Organization::create([
                'organization_name' => $validated['organization_name'],
                'organization_type' => $validated['organization_type'],
                'college_department' => $this->collegeDepartmentForOrganizationType($validated),
                'purpose' => $validated['purpose'],
                'founded_date' => $validated['date_organized'],
                'status' => 'pending',
            ]);

            if (! $forAdmin) {
                OrganizationOfficer::create([
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'position_title' => 'President',
                    'status' => 'active',
                ]);
            }

            $filePaths = $this->storeRequirementFilesForKeys(
                $request,
                $reqs,
                self::REGISTRATION_REQUIREMENT_KEYS,
                app(OrganizationStoragePath::class)->registrationFolder($organization)
            );

            $submission = OrganizationSubmission::create([
                'organization_id' => $organization->id,
                'submitted_by' => $user->id,
                'academic_term_id' => $this->resolveOrCreateAcademicTermId((string) $validated['academic_year']),
                'type' => OrganizationSubmission::TYPE_REGISTRATION,
                'contact_person' => $validated['contact_person'],
                'adviser_name' => $this->adviserDisplayNameByUserId((int) $validated['adviser_user_id']),
                'contact_no' => $validated['contact_no'],
                'contact_email' => $validated['email_address'],
                'submission_date' => now()->toDateString(),
                'notes' => null,
                'status' => OrganizationSubmission::STATUS_PENDING,
                'current_approval_step' => 0,
            ]);

            $this->syncSubmissionRequirements(
                $submission,
                self::REGISTRATION_REQUIREMENT_KEYS,
                $reqs,
                (string) ($validated['requirements_other'] ?? '')
            );
            $this->syncSubmissionAdviserNomination(
                $organization->id,
                (int) $submission->id,
                (int) $validated['adviser_user_id']
            );
            $this->attachRequirementFiles($submission, $filePaths, $user->id);

        });

        if ($forAdmin) {
            return redirect()
                ->route('admin.registrations.show', $submission)
                ->with('success', 'Registration application submitted successfully. File under the submitting SDAO admin account.');
        }

        $this->createOfficerNotification(
            $user,
            'Registration Submitted',
            'Your organization registration has been submitted for SDAO review.',
            'info',
            route('organizations.submitted-documents.registrations.show', $submission),
            $submission
        );
        $this->createOfficerNotification(
            $user,
            'Account Linked to Organization',
            'Your account has been linked to your organization profile.',
            'success',
            route('organizations.profile'),
            $submission->organization ?? null
        );

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
        $renewalIsBlocked = $officerValidationPending || $renewalBlockedNoOrganization || ! ($renewalAccess['allowed'] ?? false);
        $renewalBlockedReason = $officerValidationPending
            ? 'Your student officer account is pending SDAO validation. You cannot submit or edit organization forms until validation is complete.'
            : ($renewalBlockedNoOrganization
                ? 'No organization is linked to your account. Register your organization before submitting a renewal application.'
                : (string) ($renewalAccess['message'] ?? ''));

        $adviserNominationNotice = null;
        if ($organization) {
            $latestRenewalSubmission = $organization->submissions()
                ->renewals()
                ->where('status', OrganizationSubmission::STATUS_REVISION)
                ->latest('updated_at')
                ->latest('id')
                ->first();
            if ($latestRenewalSubmission) {
                $latestRejectedNomination = OrganizationAdviser::query()
                    ->where('organization_id', $organization->id)
                    ->where('submission_id', (int) $latestRenewalSubmission->id)
                    ->where('status', 'rejected')
                    ->latest('reviewed_at')
                    ->latest('id')
                    ->first();
                if ($latestRejectedNomination) {
                    $adviserNominationNotice = 'Previous adviser was rejected - please nominate a new one.';
                }
            }
        }

        return view('organizations.renew', compact(
            'organization',
            'schoolCodeDefault',
            'officerValidationPending',
            'renewalBlockedNoOrganization',
            'renewalAccess',
            'renewalIsBlocked',
            'renewalBlockedReason',
            'adviserNominationNotice',
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
            'adviser_user_id' => ['required', 'integer', 'exists:users,id'],
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
        ], $this->requirementFileRules($request, self::RENEWAL_REQUIREMENT_KEYS)), array_merge([
            'requirements.required' => self::REQUIREMENTS_MIN_ONE_MESSAGE,
            'requirements.min' => self::REQUIREMENTS_MIN_ONE_MESSAGE,
        ], $this->requirementFileValidationMessages()));

        if (! $this->isEligibleAdviserUserId((int) $validated['adviser_user_id'])) {
            return back()->withErrors([
                'adviser_user_id' => 'Selected faculty adviser is not eligible.',
            ])->withInput();
        }

        $this->enforceRequiredRequirementSelectionsAndFiles(
            $request,
            self::RENEWAL_REQUIRED_REQUIREMENT_KEYS
        );

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

        if (app(AdviserAssignmentAvailability::class)->isAdviserUnavailable(
            (int) $validated['adviser_user_id'],
            (int) $organization->id
        )) {
            return back()->withErrors([
                'adviser_user_id' => AdviserAssignmentAvailability::VALIDATION_MESSAGE,
            ])->withInput();
        }

        $validated['contact_no'] = $this->normalizePhilippineContactNo($validated['contact_no']);

        $reqs = collect($validated['requirements'] ?? []);

        $organization->update([
            'purpose' => $validated['purpose'],
            'organization_type' => $validated['organization_type'],
            'college_department' => $this->collegeDepartmentForOrganizationType($validated),
        ]);

        $submission = OrganizationSubmission::create([
            'organization_id' => $organization->id,
            'submitted_by' => $user->id,
            'academic_term_id' => $this->resolveOrCreateAcademicTermId((string) $validated['academic_year']),
            'type' => OrganizationSubmission::TYPE_RENEWAL,
            'contact_person' => $validated['contact_person'],
            'adviser_name' => $this->adviserDisplayNameByUserId((int) $validated['adviser_user_id']),
            'contact_no' => $validated['contact_no'],
            'contact_email' => $validated['email_address'],
            'submission_date' => now()->toDateString(),
            'notes' => null,
            'status' => OrganizationSubmission::STATUS_PENDING,
            'current_approval_step' => 0,
        ]);

        $filePaths = $this->storeRequirementFilesForKeys(
            $request,
            $reqs,
            self::RENEWAL_REQUIREMENT_KEYS,
            app(OrganizationStoragePath::class)->renewalFolder($organization, (int) $submission->id)
        );

        $this->syncSubmissionRequirements(
            $submission,
            self::RENEWAL_REQUIREMENT_KEYS,
            $reqs,
            (string) ($validated['requirements_other'] ?? '')
        );
        $this->syncSubmissionAdviserNomination(
            $organization->id,
            (int) $submission->id,
            (int) $validated['adviser_user_id']
        );
        $this->attachRequirementFiles($submission, $filePaths, $user->id);

        if ($isAdminRenewal) {
            return redirect()
                ->route('admin.renewals.show', $submission)
                ->with('success', 'Renewal application submitted successfully. Recorded under your SDAO admin account.');
        }

        $this->createOfficerNotification(
            $user,
            'Renewal Submitted',
            'Your organization renewal has been submitted for SDAO review.',
            'info',
            route('organizations.submitted-documents.renewals.show', $submission),
            $submission
        );

        $renewProfileUrl = route('organizations.profile');
        $renewBackUrl = route('organizations.renew');

        return redirect()
            ->to($renewBackUrl)
            ->with('success', 'Renewal application submitted successfully.')
            ->with('renewal_redirect_to', $renewProfileUrl);
    }

    /**
     * @return array{
     *   allowed: bool,
     *   message: string,
     *   blocked_by_term: bool,
     *   blocked_by_existing_renewal: bool,
     *   blocked_by_no_registration: bool,
     *   blocked_by_officer_validation: bool
     * }
     */
    private function renewalAccessForRso(?User $user): array
    {
        $defaultBlocked = [
            'allowed' => false,
            'message' => 'Renew Organization is currently unavailable because your account is not linked to an active organization.',
            'blocked_by_term' => false,
            'blocked_by_existing_renewal' => false,
            'blocked_by_no_registration' => false,
            'blocked_by_officer_validation' => false,
        ];

        if (! $user || $user->isSuperAdmin()) {
            return $defaultBlocked;
        }

        if (! $user->isOfficerValidated()) {
            return array_merge($defaultBlocked, [
                'message' => 'Your student officer account is pending SDAO validation. You cannot renew your organization until validation is complete.',
                'blocked_by_officer_validation' => true,
            ]);
        }

        $organization = $user->currentOrganization();
        if (! $organization) {
            return $defaultBlocked;
        }

        // Renewal presupposes a previously approved registration record. Without an approved
        // registration (organizations.status = 'active' and an approved submission of type
        // 'registration'), the officer should not see or be able to submit a renewal.
        if (! $organization->hasApprovedRegistration()) {
            return array_merge($defaultBlocked, [
                'message' => 'Renew Organization becomes available only after your organization has an approved registration on file. Please wait for SDAO to approve your registration first.',
                'blocked_by_no_registration' => true,
            ]);
        }

        if (! $organization->isEligibleForRenewal()) {
            $message = (string) ($organization->renewalIneligibilityReason() ?? 'Your organization is not eligible for renewal at this time.');
            $blockedByTerm = str_contains($message, '1st Term');
            $blockedByExistingRenewal = str_contains(strtolower($message), 'already submitted a renewal')
                || str_contains(strtolower($message), 'renewal submission in progress');
            $blockedByNoRegistration = str_contains($message, 'approved registration on file');

            return [
                'allowed' => false,
                'message' => $message,
                'blocked_by_term' => $blockedByTerm,
                'blocked_by_existing_renewal' => $blockedByExistingRenewal,
                'blocked_by_no_registration' => $blockedByNoRegistration,
                'blocked_by_officer_validation' => false,
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'blocked_by_term' => false,
            'blocked_by_existing_renewal' => false,
            'blocked_by_no_registration' => false,
            'blocked_by_officer_validation' => false,
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

        $registrationAdviserNomination = null;
        if ($organization && $activeApplication instanceof OrganizationSubmission && $activeApplication->isRegistration()) {
            $registrationAdviserNomination = OrganizationAdviser::query()
                ->where('organization_id', (int) $organization->id)
                ->where('submission_id', (int) $activeApplication->id)
                ->latest('id')
                ->first();
        }

        $revisionRegistration = null;
        $profileRevisionSummary = ['groups' => [], 'field_notes' => [], 'general_remarks' => null];
        $revisionEditableFields = [];
        $profileRevisionNotesByFormField = [];
        if ($organization && $organization->isProfileRevisionRequested()) {
            $revisionRegistration = $organization->submissions()
                ->registrations()
                ->where('status', OrganizationSubmission::STATUS_REVISION)
                ->latest('updated_at')
                ->latest('id')
                ->first();
            $latestRevisionSubmission = $organization->submissions()
                ->where('status', OrganizationSubmission::STATUS_REVISION)
                ->latest('updated_at')
                ->latest('id')
                ->first();
            $profileRevisionSummary = $this->buildProfileRevisionSummary($latestRevisionSubmission);
            $rawFieldNotes = is_array($profileRevisionSummary['field_notes'] ?? null) ? $profileRevisionSummary['field_notes'] : [];
            $profileRevisionNotesByFormField = $this->mergeProfileRevisionNotesForForm($profileRevisionSummary);
            $revisionEditableFields = array_values(array_unique(array_merge(
                array_keys($profileRevisionNotesByFormField),
                $this->profileRevisionEditableFieldsFromNotes($rawFieldNotes)
            )));
        }
        $editingRequested = (bool) $request->query('edit');
        $editingAllowed = $editingRequested && $canEditProfile && $organization;
        $revisionEditMode = $editingAllowed
            && (bool) ($organization?->isProfileRevisionRequested() ?? false)
            && count($revisionEditableFields) > 0;
        $activeAdviser = null;
        $adviserPayload = null;
        if ($organization) {
            $organization->load(['currentAdviser.user']);
            $activeAdviser = $organization->currentAdviser;

            Log::info('Organization profile adviser investigation', [
                'organization_id' => (int) $organization->id,
                'organization_attributes' => method_exists($organization, 'getAttributes')
                    ? $organization->getAttributes()
                    : null,
                'has_current_adviser_relation' => (bool) $activeAdviser,
                'current_adviser_relation_id' => $activeAdviser?->id,
                'current_adviser_relation_user_id' => $activeAdviser?->user_id,
                'has_current_adviser_relation_user' => (bool) $activeAdviser?->user,
            ]);

            if (Schema::hasTable('organization_advisers')) {
                $adviserRows = DB::table('organization_advisers')
                    ->where('organization_id', $organization->id)
                    ->orderByDesc('id')
                    ->get();

                Log::info('organization_advisers rows for profile', [
                    'organization_id' => (int) $organization->id,
                    'rows' => $adviserRows->map(static fn ($row): array => [
                        'id' => $row->id ?? null,
                        'organization_id' => $row->organization_id ?? null,
                        'user_id' => $row->user_id ?? null,
                        'status' => $row->status ?? null,
                        'relieved_at' => $row->relieved_at ?? null,
                    ])->values()->all(),
                ]);

                $adviserJoinedRows = DB::table('organization_advisers as oa')
                    ->leftJoin('users as u', 'u.id', '=', 'oa.user_id')
                    ->where('oa.organization_id', $organization->id)
                    ->select(
                        'oa.id as adviser_record_id',
                        'oa.organization_id',
                        'oa.user_id',
                        'oa.status',
                        'oa.relieved_at',
                        'u.id as user_id_from_users',
                        'u.first_name',
                        'u.last_name',
                        'u.email',
                        'u.school_id'
                    )
                    ->orderByDesc('oa.id')
                    ->get();

                Log::info('organization profile adviser joined rows', [
                    'organization_id' => (int) $organization->id,
                    'rows' => $adviserJoinedRows->values()->all(),
                ]);

                $adviserQuery = DB::table('organization_advisers as oa')
                    ->leftJoin('users as u', 'u.id', '=', 'oa.user_id')
                    ->where('oa.organization_id', $organization->id);

                if (Schema::hasColumn('organization_advisers', 'relieved_at')) {
                    $adviserQuery->whereNull('oa.relieved_at');
                }
                if (Schema::hasColumn('organization_advisers', 'status')) {
                    $adviserQuery->whereRaw('LOWER(COALESCE(oa.status, \'\')) NOT IN (?, ?, ?)', [
                        'rejected',
                        'relieved',
                        'inactive',
                    ]);
                }

                $adviser = $adviserQuery
                    ->select(
                        'oa.id as adviser_record_id',
                        'oa.user_id',
                        'u.first_name',
                        'u.last_name',
                        'u.email',
                        'u.school_id'
                    )
                    ->orderByDesc('oa.id')
                    ->first();

                if ($adviser && $adviser->user_id) {
                    $adviserPayload = [
                        'id' => (int) $adviser->user_id,
                        'name' => trim((string) ($adviser->first_name ?? '').' '.(string) ($adviser->last_name ?? '')),
                        'email' => $adviser->email,
                        'school_id' => $adviser->school_id,
                    ];
                }
            }

            if (! $adviserPayload && $activeAdviser?->user) {
                $adviserPayload = [
                    'id' => (int) $activeAdviser->user->id,
                    'name' => trim((string) ($activeAdviser->user->first_name ?? '').' '.(string) ($activeAdviser->user->last_name ?? '')),
                    'email' => $activeAdviser->user->email,
                    'school_id' => $activeAdviser->user->school_id,
                ];
            }

            if (! $adviserPayload) {
                $organizationAttributes = method_exists($organization, 'getAttributes')
                    ? $organization->getAttributes()
                    : [];
                $possibleAdviserColumns = ['adviser_id', 'faculty_adviser_id', 'adviser_user_id', 'assigned_adviser_id'];

                foreach ($possibleAdviserColumns as $column) {
                    if (! array_key_exists($column, $organizationAttributes) || empty($organizationAttributes[$column])) {
                        continue;
                    }

                    $userFromOrganizationColumn = User::query()->find((int) $organizationAttributes[$column]);
                    if (! $userFromOrganizationColumn) {
                        continue;
                    }

                    $adviserPayload = [
                        'id' => (int) $userFromOrganizationColumn->id,
                        'name' => trim((string) ($userFromOrganizationColumn->first_name ?? '').' '.(string) ($userFromOrganizationColumn->last_name ?? '')),
                        'email' => $userFromOrganizationColumn->email,
                        'school_id' => $userFromOrganizationColumn->school_id,
                    ];
                    break;
                }
            }

            Log::info('organization adviser columns', [
                'organization_id' => (int) $organization->id,
                'adviser_id' => $organization->adviser_id ?? null,
                'faculty_adviser_id' => $organization->faculty_adviser_id ?? null,
                'adviser_user_id' => $organization->adviser_user_id ?? null,
            ]);
            Log::info('final adviser payload for organization profile', [
                'organization_id' => (int) $organization->id,
                'has_adviser_payload' => (bool) $adviserPayload,
                'adviser_user_id' => $adviserPayload['id'] ?? null,
            ]);
        }

        return view('organizations.profile', [
            'organization' => $organization,
            'editing' => (bool) $editingAllowed,
            'revisionEditMode' => $revisionEditMode,
            'revisionEditableFields' => $revisionEditableFields,
            'canEditProfile' => $organization ? $canEditProfile : false,
            'profileEditBlockedMessage' => $organization ? $profileEditBlockedMessage : '',
            'activeApplication' => $activeApplication,
            'applicationTypeLabel' => $applicationTypeLabel,
            'applicationWorkflowStatus' => $applicationWorkflowStatus,
            'revisionRegistration' => $revisionRegistration,
            'profileRevisionSummary' => $profileRevisionSummary,
            'profileRevisionNotesByFormField' => $profileRevisionNotesByFormField,
            'activeAdviser' => $activeAdviser,
            'adviser' => $adviserPayload,
            'registrationAdviserNomination' => $registrationAdviserNomination,
        ]);
    }

    /**
     * @return array{0: OrganizationSubmission|null, 1: string|null}
     */
    private function resolveProfileActiveApplication(?Organization $organization): array
    {
        if (! $organization) {
            return [null, null];
        }

        $registration = $organization->submissions()
            ->registrations()
            ->with('academicTerm')
            ->latest('submission_date')
            ->latest('id')
            ->first();

        $renewal = $organization->submissions()
            ->renewals()
            ->with('academicTerm')
            ->latest('submission_date')
            ->latest('id')
            ->first();

        if ($registration) {
            $registration->setAttribute('academic_year', $registration->academicTerm?->academic_year);
        }
        if ($renewal) {
            $renewal->setAttribute('academic_year', $renewal->academicTerm?->academic_year);
        }

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

    private function workflowStatusFromApplication(?OrganizationSubmission $application): ?string
    {
        if (! $application) {
            return null;
        }

        return $application->legacyStatus();
    }

    /**
     * @param  array<string, string>  $fieldNotes
     * @return array<int, string>
     */
    /**
     * Treat empty strings, numeric zero, and other non-text placeholders as "no note".
     */
    private function isNonEmptyProfileRevisionNote(mixed $note): bool
    {
        if ($note === null || $note === false) {
            return false;
        }
        if (is_int($note) || is_float($note)) {
            return $note !== 0;
        }
        $s = trim((string) $note);
        if ($s === '') {
            return false;
        }
        if (preg_match('/^(0+)(\\.0+)?$/', $s) === 1) {
            return false;
        }
        $lower = strtolower($s);

        return ! in_array($lower, ['null', 'undefined', 'n/a'], true);
    }

    /**
     * Map admin review section/field (and optional label) to organization profile form input names.
     * Section-aware so generic keys like "email" resolve to contact vs adviser correctly.
     */
    private function normalizeProfileRevisionFieldToFormKey(?string $sectionKey, ?string $fieldKey, ?string $fieldLabel = null): ?string
    {
        $section = strtolower(trim((string) $sectionKey));
        $field = strtolower(trim((string) $fieldKey));
        $field = str_replace([' ', '-', '/'], '_', $field);
        $label = strtolower(trim((string) $fieldLabel));
        $label = str_replace([' ', '-', '/'], '_', $label);

        $sectionHas = static fn (string $needle): bool => $needle !== '' && str_contains($section, $needle);

        if ($sectionHas('contact')) {
            if ($field === 'adviser_name'
                || $field === 'adviser_full_name'
                || ($label !== '' && str_contains($label, 'adviser') && str_contains($label, 'name'))) {
                return 'adviser_name';
            }
            if ($field === 'adviser_email'
                || ($label !== '' && str_contains($label, 'adviser') && str_contains($label, 'email'))) {
                return 'adviser_email';
            }
            if ($field === 'adviser_school_id'
                || ($label !== '' && str_contains($label, 'adviser') && str_contains($label, 'school'))) {
                return 'adviser_school_id';
            }
            if (in_array($field, ['contact_person', 'contact_name', 'person'], true) || str_contains($label, 'contact_person')) {
                return 'contact_person';
            }
            if (in_array($field, ['contact_no', 'contact_number', 'phone', 'number'], true) || str_contains($label, 'contact_number') || str_contains($label, 'contact_no')) {
                return 'contact_no';
            }
            if (in_array($field, ['contact_email', 'email_address', 'email'], true)
                || str_contains($label, 'contact_email')
                || (str_contains($label, 'email') && ! str_contains($label, 'adviser'))) {
                return 'contact_email';
            }
            if ($field === 'organization_name' || str_contains($label, 'organization_name')) {
                return 'organization_name';
            }

            return null;
        }

        if ($sectionHas('adviser')) {
            if (in_array($field, ['full_name', 'adviser_full_name', 'adviser_name', 'name'], true)
                || str_contains($label, 'full_name')
                || (str_contains($label, 'adviser') && str_contains($label, 'name'))) {
                return 'adviser_name';
            }
            if (in_array($field, ['school_id', 'adviser_school_id'], true) || str_contains($label, 'school_id')) {
                return 'adviser_school_id';
            }
            if (in_array($field, ['email', 'adviser_email'], true) || str_contains($label, 'email')) {
                return 'adviser_email';
            }

            return null;
        }

        if ($sectionHas('application') || $sectionHas('overview')) {
            if (in_array($field, ['organization', 'organization_name'], true) || str_contains($label, 'organization')) {
                return 'organization_name';
            }
            if ($field === 'submitted_by' || str_contains($label, 'submitted_by')) {
                return 'submitted_by_display';
            }

            return null;
        }

        if ($sectionHas('organizational') || $section === 'organization') {
            if (in_array($field, ['organization_type'], true) || str_contains($label, 'organization_type') || str_contains($label, 'type_of_organization')) {
                return 'organization_type';
            }
            if (in_array($field, ['school', 'college_department', 'college'], true) || str_contains($label, 'school') || str_contains($label, 'department')) {
                return 'college_department';
            }
            if ($field === 'purpose' || str_contains($label, 'purpose')) {
                return 'purpose';
            }

            return null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $profileRevisionSummary
     * @return array<string, string>
     */
    private function mergeProfileRevisionNotesForForm(array $profileRevisionSummary): array
    {
        $merged = [];
        $fieldNotes = is_array($profileRevisionSummary['field_notes'] ?? null) ? $profileRevisionSummary['field_notes'] : [];
        foreach ($fieldNotes as $path => $note) {
            if (! $this->isNonEmptyProfileRevisionNote($note) || ! str_contains((string) $path, '.')) {
                continue;
            }
            [$s, $f] = explode('.', (string) $path, 2);
            $formKey = $this->normalizeProfileRevisionFieldToFormKey($s, $f, null);
            if ($formKey === null) {
                continue;
            }
            if (! isset($merged[$formKey])) {
                $merged[$formKey] = trim((string) $note);
            }
        }

        $groups = is_array($profileRevisionSummary['groups'] ?? null) ? $profileRevisionSummary['groups'] : [];
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            $sectionKey = (string) ($group['section_key'] ?? '');
            $items = is_array($group['items'] ?? null) ? $group['items'] : [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $note = $item['note'] ?? null;
                if (! $this->isNonEmptyProfileRevisionNote($note)) {
                    continue;
                }
                $fieldKey = (string) ($item['field_key'] ?? '');
                $label = (string) ($item['field_label'] ?? $item['field'] ?? '');
                $formKey = $this->normalizeProfileRevisionFieldToFormKey($sectionKey, $fieldKey, $label);
                if ($formKey === null) {
                    continue;
                }
                if (! isset($merged[$formKey])) {
                    $merged[$formKey] = trim((string) $note);
                }
            }
        }

        return $merged;
    }

    private function profileRevisionEditableFieldsFromNotes(array $fieldNotes): array
    {
        $map = [
            'application.organization' => 'organization_name',
            'contact.organization_name' => 'organization_name',
            'contact.contact_person' => 'contact_person',
            'contact.contact_no' => 'contact_no',
            'contact.contact_email' => 'contact_email',
            'contact.email_address' => 'contact_email',
            'contact.email' => 'contact_email',
            'contact.adviser_name' => 'adviser_name',
            'contact.adviser_full_name' => 'adviser_name',
            'contact.adviser_email' => 'adviser_email',
            'contact.adviser_school_id' => 'adviser_school_id',
            'overview.submitted_by' => 'submitted_by_display',
            'organizational.organization_type' => 'organization_type',
            'organizational.school' => 'college_department',
            'organizational.purpose' => 'purpose',
            'adviser.full_name' => 'adviser_name',
            'adviser.adviser_full_name' => 'adviser_name',
            'adviser.adviser_name' => 'adviser_name',
            'adviser.email' => 'adviser_email',
            'adviser.adviser_email' => 'adviser_email',
            'adviser.school_id' => 'adviser_school_id',
            'adviser.adviser_school_id' => 'adviser_school_id',
        ];
        $editable = [];
        foreach ($fieldNotes as $fieldPath => $note) {
            if (! $this->isNonEmptyProfileRevisionNote($note)) {
                continue;
            }
            if (isset($map[$fieldPath])) {
                $editable[] = $map[$fieldPath];

                continue;
            }
            if (! str_contains((string) $fieldPath, '.')) {
                continue;
            }
            [$s, $f] = explode('.', (string) $fieldPath, 2);
            $formKey = $this->normalizeProfileRevisionFieldToFormKey($s, $f, null);
            if ($formKey !== null) {
                $editable[] = $formKey;
            }
        }

        return array_values(array_unique($editable));
    }

    /**
     * @return array{
     *   groups: array<int, array{section_key: string, section_title: string, items: array<int, array{field_key: string, field_label: string, note: string, anchor_id: string}>}>,
     *   field_notes: array<string, string>,
     *   general_remarks: string|null
     * }
     */
    private function buildProfileRevisionSummary(?OrganizationSubmission $submission): array
    {
        if (! $submission) {
            return ['groups' => [], 'field_notes' => [], 'general_remarks' => null];
        }

        $built = app(OrganizationRegistrationRevisionSummaryService::class)->buildForSubmission($submission);
        $built['groups'] = $this->enrichOrganizationProfileRevisionGroups(
            is_array($built['groups'] ?? null) ? $built['groups'] : []
        );

        return $built;
    }

    /**
     * Organization dashboard uses section.field keys for pending dedupe; align contact/overview adviser
     * revisions with adviser.* keys and profile scroll anchors (revision-field-adviser-*).
     *
     * @return array{0: string, 1: string}
     */
    private function organizationDashboardRevisionIdentity(string $sectionKey, string $fieldKey, string $label): array
    {
        $formKey = $this->normalizeProfileRevisionFieldToFormKey($sectionKey, $fieldKey, $label);
        $map = [
            'adviser_name' => 'full_name',
            'adviser_email' => 'email',
            'adviser_school_id' => 'school_id',
        ];
        if ($formKey !== null && isset($map[$formKey])) {
            $canonical = $map[$formKey];

            return [
                'adviser.'.$canonical,
                'revision-field-'.$this->sanitizeAnchorSegment('adviser').'-'.$this->sanitizeAnchorSegment($canonical),
            ];
        }

        return [
            $sectionKey.'.'.$fieldKey,
            'revision-field-'.$this->sanitizeAnchorSegment($sectionKey).'-'.$this->sanitizeAnchorSegment($fieldKey),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function withAdviserRevisionDisplayItem(array $item, string $canonicalFieldSlug): array
    {
        $anchorId = 'revision-field-'.$this->sanitizeAnchorSegment('adviser').'-'.$this->sanitizeAnchorSegment($canonicalFieldSlug);

        return array_merge($item, [
            'field_key' => $canonicalFieldSlug,
            'anchor_id' => $anchorId,
        ]);
    }

    /**
     * Merge adviser / adviser_information / contact (renewal) adviser revisions into one Adviser Information
     * group with anchors that match Organization Profile (revision-field-adviser-full-name, etc.).
     *
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function enrichOrganizationProfileRevisionGroups(array $groups): array
    {
        $formToSlug = [
            'adviser_name' => 'full_name',
            'adviser_email' => 'email',
            'adviser_school_id' => 'school_id',
        ];
        $adviserBySlug = [];
        $out = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            $sectionKey = (string) ($group['section_key'] ?? '');
            $skLower = strtolower($sectionKey);
            $isAdviserSection = str_contains($skLower, 'adviser');

            if ($isAdviserSection) {
                $leftover = [];
                foreach ((array) ($group['items'] ?? []) as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $fk = (string) ($item['field_key'] ?? '');
                    $fl = (string) ($item['field_label'] ?? $item['field'] ?? '');
                    if (in_array(strtolower($fk), ['status', 'adviser_status'], true)) {
                        continue;
                    }
                    $formKey = $this->normalizeProfileRevisionFieldToFormKey($sectionKey, $fk, $fl);
                    if ($formKey !== null && isset($formToSlug[$formKey])) {
                        $slug = $formToSlug[$formKey];
                        $adviserBySlug[$slug] = $this->withAdviserRevisionDisplayItem($item, $slug);

                        continue;
                    }
                    $leftover[] = $item;
                }
                if ($leftover !== []) {
                    $out[] = array_merge($group, ['items' => array_values($leftover)]);
                }

                continue;
            }

            $kept = [];
            foreach ((array) ($group['items'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $fk = (string) ($item['field_key'] ?? '');
                $fl = (string) ($item['field_label'] ?? $item['field'] ?? '');
                $formKey = $this->normalizeProfileRevisionFieldToFormKey($sectionKey, $fk, $fl);
                if ($formKey !== null && isset($formToSlug[$formKey])) {
                    $slug = $formToSlug[$formKey];
                    $adviserBySlug[$slug] = $this->withAdviserRevisionDisplayItem($item, $slug);

                    continue;
                }
                $kept[] = $item;
            }
            if ($kept !== []) {
                $out[] = array_merge($group, ['items' => array_values($kept)]);
            }
        }

        if ($adviserBySlug === []) {
            return $out === [] ? $groups : $out;
        }

        $order = ['full_name', 'school_id', 'email'];
        $items = [];
        foreach ($order as $slug) {
            if (isset($adviserBySlug[$slug])) {
                $items[] = $adviserBySlug[$slug];
            }
        }
        foreach ($adviserBySlug as $slug => $item) {
            if (! in_array($slug, $order, true)) {
                $items[] = $item;
            }
        }

        $insertAt = count($out);
        foreach ($out as $i => $g) {
            if (strtolower((string) ($g['section_key'] ?? '')) === 'requirements') {
                $insertAt = $i;
                break;
            }
        }
        array_splice($out, $insertAt, 0, [[
            'section_key' => 'adviser',
            'section_title' => 'Adviser Information',
            'title' => 'Adviser Information',
            'items' => $items,
        ]]);

        return $out;
    }

    private function sanitizeAnchorSegment(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: 'field';

        return trim($value, '-') ?: 'field';
    }

    private function normalizeRevisionComparableValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $string = preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';

        return mb_strtolower($string);
    }

    private function profileCurrentFieldValue(Organization $organization, string $field): mixed
    {
        return match ($field) {
            'organization_name' => (string) ($organization->organization_name ?? ''),
            'organization_type' => (string) ($organization->organization_type ?? ''),
            'college_department' => (string) ($organization->college_department ?? ''),
            'purpose' => (string) ($organization->purpose ?? ''),
            'submitted_by_display' => '',
            'adviser_name' => (string) ($organization->currentAdviser?->user?->full_name ?? ''),
            'adviser_email' => (string) ($organization->currentAdviser?->user?->email ?? ''),
            'adviser_school_id' => (string) ($organization->currentAdviser?->user?->school_id ?? ''),
            default => '',
        };
    }

    /**
     * Current value for profile revision compare/save (submission columns for contact fields).
     */
    private function profileRevisionFieldCurrentValue(?OrganizationSubmission $submission, Organization $organization, string $field): mixed
    {
        return match ($field) {
            'contact_person' => (string) ($submission?->contact_person ?? ''),
            'contact_no' => (string) ($submission?->contact_no ?? ''),
            'contact_email' => (string) ($submission?->contact_email ?? ''),
            default => $this->profileCurrentFieldValue($organization, $field),
        };
    }

    /**
     * @return list<string>
     */
    private function profileSubmissionColumnFieldKeys(): array
    {
        return ['contact_person', 'contact_no', 'contact_email'];
    }

    private function profileNewValueAfterSave(Organization $organization, array $validated, string $field): string
    {
        if ($field === 'submitted_by_display') {
            return (string) ($validated[$field] ?? '');
        }
        if (in_array($field, $this->profileSubmissionColumnFieldKeys(), true)) {
            return (string) ($validated[$field] ?? '');
        }
        if (in_array($field, ['adviser_name', 'adviser_email', 'adviser_school_id'], true)) {
            return (string) ($validated[$field] ?? '');
        }

        return (string) $this->profileCurrentFieldValue($organization, $field);
    }

    /**
     * @return array<string, array{section_key: string, field_key: string}>
     */
    private function profileFieldToRevisionKeyMap(): array
    {
        return [
            'organization_name' => ['section_key' => 'application', 'field_key' => 'organization'],
            'submitted_by_display' => ['section_key' => 'application', 'field_key' => 'submitted_by'],
            'organization_type' => ['section_key' => 'organizational', 'field_key' => 'organization_type'],
            'college_department' => ['section_key' => 'organizational', 'field_key' => 'school'],
            'purpose' => ['section_key' => 'organizational', 'field_key' => 'purpose'],
            'contact_person' => ['section_key' => 'contact', 'field_key' => 'contact_person'],
            'contact_no' => ['section_key' => 'contact', 'field_key' => 'contact_no'],
            'contact_email' => ['section_key' => 'contact', 'field_key' => 'contact_email'],
            'adviser_name' => ['section_key' => 'adviser', 'field_key' => 'full_name'],
            'adviser_email' => ['section_key' => 'adviser', 'field_key' => 'email'],
            'adviser_school_id' => ['section_key' => 'adviser', 'field_key' => 'school_id'],
        ];
    }

    /**
     * @return array{first_name: string, last_name: string}
     */
    private function splitAdviserDisplayNameIntoUserNames(string $displayName): array
    {
        $displayName = trim(preg_replace('/\s+/u', ' ', $displayName) ?? '');
        if ($displayName === '') {
            return ['first_name' => '', 'last_name' => ''];
        }
        $parts = preg_split('/\s+/u', $displayName) ?: [];
        if (count($parts) === 1) {
            return ['first_name' => $parts[0], 'last_name' => ''];
        }
        $last = (string) array_pop($parts);
        $first = trim(implode(' ', $parts));

        return ['first_name' => $first !== '' ? $first : $last, 'last_name' => $last];
    }

    /**
     * Persist adviser revision fields on the linked faculty user (and submission denormalized name when applicable).
     */
    private function applyProfileRevisionAdviserUpdates(Organization $organization, ?OrganizationSubmission $latestRevisionSubmission, array $validated): void
    {
        $touched = array_key_exists('adviser_name', $validated)
            || array_key_exists('adviser_email', $validated)
            || array_key_exists('adviser_school_id', $validated);
        if (! $touched) {
            return;
        }

        $organization->loadMissing(['currentAdviser.user']);
        $user = $organization->currentAdviser?->user;
        if (! $user) {
            return;
        }

        $payload = [];
        if (array_key_exists('adviser_email', $validated)) {
            $payload['email'] = (string) ($validated['adviser_email'] ?? '');
        }
        if (array_key_exists('adviser_school_id', $validated)) {
            $payload['school_id'] = (string) ($validated['adviser_school_id'] ?? '');
        }
        if (array_key_exists('adviser_name', $validated)) {
            $payload = array_merge($payload, $this->splitAdviserDisplayNameIntoUserNames((string) ($validated['adviser_name'] ?? '')));
        }
        if ($payload !== []) {
            $user->update($payload);
        }

        if ($latestRevisionSubmission && array_key_exists('adviser_name', $validated) && Schema::hasColumn('organization_submissions', 'adviser_name')) {
            $display = trim((string) ($validated['adviser_name'] ?? ''));
            $latestRevisionSubmission->update(['adviser_name' => $display !== '' ? $display : null]);
        }
    }

    /**
     * True when every field the officer must fix in this revision has a value different from the pre-edit baseline.
     *
     * @param  array<int, string>  $fields
     */
    private function allProfileRevisionFieldsChanged(
        Organization $organization,
        array $validated,
        array $fields,
        ?OrganizationSubmission $submission,
        string $submittedByDisplayOriginal
    ): bool {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $validated)) {
                return false;
            }
            if ($field === 'submitted_by_display') {
                $before = $this->normalizeRevisionComparableValue($submittedByDisplayOriginal);
                $after = $this->normalizeRevisionComparableValue($validated[$field] ?? '');

                if ($before === $after) {
                    return false;
                }

                continue;
            }
            $before = $this->normalizeRevisionComparableValue(
                $this->profileRevisionFieldCurrentValue($submission, $organization, $field)
            );
            $after = $this->normalizeRevisionComparableValue($validated[$field] ?? '');
            if ($before === $after) {
                return false;
            }
        }

        return true;
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

        $organization->load(['currentAdviser.user']);

        $defaultRules = [
            'organization_name' => ['required', 'string', 'max:150'],
            'organization_type' => ['required', 'string', 'max:50'],
            'college_department' => ['required', 'string', 'max:100'],
            'purpose' => ['required', 'string'],
        ];
        $validated = [];
        $latestRevisionSubmission = null;
        $editableComparedFields = [];
        if ($organization->isProfileRevisionRequested()) {
            $latestRevisionSubmission = $organization->submissions()
                ->where('status', OrganizationSubmission::STATUS_REVISION)
                ->latest('updated_at')
                ->latest('id')
                ->first();
            $revisionSummary = $this->buildProfileRevisionSummary($latestRevisionSubmission);
            $editableFields = $this->profileRevisionEditableFieldsFromNotes(
                is_array($revisionSummary['field_notes'] ?? null) ? $revisionSummary['field_notes'] : []
            );
            if ($editableFields !== []) {
                $revisionRules = [];
                foreach ($editableFields as $field) {
                    if ($field === 'submitted_by_display') {
                        $revisionRules[$field] = ['required', 'string', 'max:150'];

                        continue;
                    }
                    if ($field === 'adviser_name') {
                        $revisionRules[$field] = ['nullable', 'string', 'max:100'];

                        continue;
                    }
                    if ($field === 'adviser_email') {
                        $revisionRules[$field] = ['nullable', 'string', 'email', 'max:255'];

                        continue;
                    }
                    if ($field === 'adviser_school_id') {
                        $revisionRules[$field] = ['nullable', 'string', 'max:50'];

                        continue;
                    }
                    if ($field === 'contact_person') {
                        $revisionRules[$field] = ['nullable', 'string', 'max:255'];

                        continue;
                    }
                    if ($field === 'contact_no') {
                        $revisionRules[$field] = ['nullable', 'string', 'max:50'];

                        continue;
                    }
                    if ($field === 'contact_email') {
                        $revisionRules[$field] = ['nullable', 'string', 'email', 'max:255'];

                        continue;
                    }
                    if (isset($defaultRules[$field])) {
                        $revisionRules[$field] = $defaultRules[$field];
                    }
                }
                $validated = $revisionRules !== [] ? $request->validate($revisionRules) : [];
                $editableComparedFields = array_keys($revisionRules);
                $submittedByOriginal = (string) $request->input('submitted_by_display_original', '');
                $allRevisedFieldsChanged = $editableComparedFields === [] || $this->allProfileRevisionFieldsChanged(
                    $organization,
                    $validated,
                    $editableComparedFields,
                    $latestRevisionSubmission,
                    $submittedByOriginal
                );
                if ($editableComparedFields !== [] && ! $allRevisedFieldsChanged) {
                    return back()
                        ->withErrors(['revision_changes' => 'Please update all fields requested for revision before saving changes.'])
                        ->withInput();
                }
            } else {
                $validated = $request->validate($defaultRules);
            }
        } else {
            $validated = $request->validate($defaultRules);
        }

        $beforeValues = [];
        foreach (array_keys($validated) as $field) {
            if ($field === 'submitted_by_display') {
                $beforeValues[$field] = (string) $request->input('submitted_by_display_original', '');

                continue;
            }
            $beforeValues[$field] = $this->profileRevisionFieldCurrentValue($latestRevisionSubmission, $organization, $field);
        }

        $organizationPayloadKeys = ['organization_name', 'organization_type', 'college_department', 'purpose'];
        $organizationPayload = array_intersect_key($validated, array_flip($organizationPayloadKeys));
        if ($organizationPayload !== []) {
            $organization->update($organizationPayload);
        }

        $this->applyProfileRevisionAdviserUpdates($organization, $latestRevisionSubmission, $validated);

        $submissionColumnPayload = array_intersect_key($validated, array_flip($this->profileSubmissionColumnFieldKeys()));
        if ($latestRevisionSubmission && $submissionColumnPayload !== []) {
            $latestRevisionSubmission->update($submissionColumnPayload);
        }

        if ($latestRevisionSubmission && $editableComparedFields !== []) {
            $map = $this->profileFieldToRevisionKeyMap();
            $createdRevisionUpdates = false;
            foreach ($editableComparedFields as $field) {
                if (! array_key_exists($field, $validated) || ! isset($map[$field])) {
                    continue;
                }
                $oldValue = (string) ($beforeValues[$field] ?? '');
                $newValue = $this->profileNewValueAfterSave($organization, $validated, $field);
                if ($this->normalizeRevisionComparableValue($oldValue) === $this->normalizeRevisionComparableValue($newValue)) {
                    continue;
                }
                OrganizationRevisionFieldUpdate::query()->create([
                    'organization_submission_id' => $latestRevisionSubmission->id,
                    'section_key' => $map[$field]['section_key'],
                    'field_key' => $map[$field]['field_key'],
                    'old_value' => $oldValue !== '' ? $oldValue : null,
                    'new_value' => $newValue !== '' ? $newValue : null,
                    'resubmitted_at' => now(),
                    'resubmitted_by' => $user->id,
                ]);
                $createdRevisionUpdates = true;
            }

            if ($createdRevisionUpdates && (string) $latestRevisionSubmission->status === OrganizationSubmission::STATUS_REVISION) {
                $latestRevisionSubmission->update([
                    'status' => OrganizationSubmission::STATUS_UNDER_REVIEW,
                ]);
            }
        }

        OrganizationProfileRevision::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'open')
            ->update([
                'status' => 'addressed',
                'addressed_at' => now(),
            ]);

        $this->createOfficerNotification(
            $user,
            'Profile Updated',
            'Your organization profile changes were submitted successfully.',
            'success',
            route('organizations.profile'),
            $organization
        );

        $fromDashboard = strtolower((string) $request->query('from', '')) === 'dashboard';
        $redirectUrl = $fromDashboard ? route('organizations.index') : route('organizations.profile');

        return redirect($redirectUrl)
            ->with('success', $fromDashboard ? 'Profile updates submitted. Returning to dashboard.' : 'Organization profile updated successfully.');
    }

    // ── Activity Calendar Submission ─────────────────────────

    public function showActivityCalendarSubmission(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.submissions.activity-calendar');
        }

        $officerAccess = $this->officerSubmissionAccessContext($request);
        if (! $officerAccess['authorized']) {
            abort(403, 'Only organization officers can access this feature.');
        }

        $organization = $officerAccess['organization'];
        $officerValidationPending = $officerAccess['officer_validation_pending'];
        $registrationPending = $officerAccess['registration_pending'];

        if (! $organization || ! $officerAccess['organization_approved']) {
            return view('organizations.activity-calendar-submission', [
                'organization' => $organization,
                'latestCalendar' => null,
                'calendarSubmittedLocked' => false,
                'officerValidationPending' => $officerValidationPending,
                'registrationPending' => $registrationPending,
                'blockedMessage' => $officerAccess['blocked_message'],
                'isBlocked' => true,
                'blockedReason' => $officerAccess['blocked_message'],
                'activityCalendarInitialActivities' => [],
            ]);
        }

        $latestCalendar = $organization->activityCalendars()
            ->with([
                'academicTerm',
                'entries' => fn ($query) => $query->orderBy('activity_date')->orderBy('id'),
            ])
            ->latest('submission_date')
            ->latest('id')
            ->first();

        $latestStatusLower = strtolower((string) ($latestCalendar->status ?? ''));
        $calendarSubmittedLocked = $latestCalendar !== null
            && ! in_array($latestStatusLower, ['revision', 'draft'], true);
        $isBlocked = $officerValidationPending || $calendarSubmittedLocked;
        $blockedReason = $officerValidationPending
            ? 'Your student officer account is pending SDAO validation. You cannot submit activity calendars until validation is complete.'
            : ($calendarSubmittedLocked
                ? 'This activity calendar has already been submitted and can no longer be edited.'
                : null);

        $activityCalendarInitialActivities = [];
        if (! $isBlocked && ! $calendarSubmittedLocked) {
            $termId = $this->resolveOrCreateAcademicTermId($this->activeAcademicYear());
            $draftCal = ActivityCalendar::query()
                ->where('organization_id', $organization->id)
                ->where('academic_term_id', $termId)
                ->where('status', 'draft')
                ->with(['entries' => fn ($query) => $query->orderBy('activity_date')->orderBy('id')])
                ->first();
            if ($draftCal && $draftCal->entries->isNotEmpty()) {
                $activityCalendarInitialActivities = $this->mapCalendarEntriesToInitialActivities($draftCal->entries);
            } elseif ($latestCalendar && $latestStatusLower === 'revision' && $latestCalendar->entries->isNotEmpty()) {
                $activityCalendarInitialActivities = $this->mapCalendarEntriesToInitialActivities($latestCalendar->entries);
            }
        }

        return view('organizations.activity-calendar-submission', [
            'organization' => $organization,
            'latestCalendar' => $latestCalendar,
            'calendarSubmittedLocked' => $calendarSubmittedLocked,
            'officerValidationPending' => $officerValidationPending,
            'registrationPending' => $registrationPending,
            'isBlocked' => $isBlocked,
            'blockedReason' => $blockedReason,
            'activityCalendarInitialActivities' => $activityCalendarInitialActivities,
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
            $officerAccess = $this->officerSubmissionAccessContext($request);
            if (! $officerAccess['authorized']) {
                abort(403, 'Only organization officers can access this feature.');
            }
            if ($officerAccess['officer_validation_pending']) {
                return redirect()
                    ->route('organizations.activity-calendar-submission')
                    ->with('error', 'Your student officer account is pending SDAO validation.');
            }

            $organization = $officerAccess['organization'];
            if (! $organization) {
                return $this->redirectWhenNoOrganizationForWorkflow($request, 'organizations.profile');
            }
            if (! $officerAccess['organization_approved']) {
                return redirect()
                    ->route('organizations.activity-calendar-submission')
                    ->with('error', 'Your organization registration is not yet approved by SDAO. You cannot submit an activity calendar until your registration is approved.');
            }
        }

        $latestLockedCalendar = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->latest('submission_date')
            ->latest('id')
            ->first();

        $latestLockedStatus = strtolower((string) ($latestLockedCalendar->status ?? ''));
        if ($latestLockedCalendar && ! in_array($latestLockedStatus, ['revision', 'draft'], true)) {
            $calUrl = $isAdminCalendar
                ? route('admin.submissions.activity-calendar')
                : route('organizations.activity-calendar-submission');

            return redirect()
                ->to($calUrl)
                ->with('error', 'This activity calendar has already been submitted and can no longer be edited.');
        }

        $validated = $request->validate([
            'activities' => ['required', 'array'],
            'activities.*.date' => ['required', 'date'],
            'activities.*.name' => ['required', 'string'],
            'activities.*.sdg' => ['required', 'array', 'min:1'],
            'activities.*.sdg.*' => ['required', 'string', Rule::in(array_map(static fn (int $n) => 'SDG '.$n, range(1, 17)))],
            'activities.*.venue' => ['required', 'string'],
            'activities.*.participant_program' => ['required', 'string'],
            'activities.*.budget' => ['required', 'numeric'],
        ]);

        $trustedAcademicYear = $this->activeAcademicYear();
        $trustedDateSubmitted = now()->toDateString();

        $hasPendingSubmission = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->where('academic_term_id', $this->resolveOrCreateAcademicTermId($trustedAcademicYear))
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingSubmission) {
            return back()
                ->withErrors([
                    'activities' => 'A pending activity calendar already exists for the active academic year and term.',
                ])
                ->withInput();
        }

        $termId = $this->resolveOrCreateAcademicTermId($trustedAcademicYear);
        $draft = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->where('academic_term_id', $termId)
            ->where('status', 'draft')
            ->first();

        DB::transaction(function () use ($organization, $validated, $user, $trustedDateSubmitted, $termId, $draft): void {
            if ($draft) {
                $draft->update([
                    'submitted_by' => $user->id,
                    'submission_date' => $trustedDateSubmitted,
                    'status' => 'pending',
                ]);
                $calendar = $draft;
                $calendar->entries()->delete();
            } else {
                $calendar = ActivityCalendar::create([
                    'organization_id' => $organization->id,
                    'submitted_by' => $user->id,
                    'academic_term_id' => $termId,
                    'submission_date' => $trustedDateSubmitted,
                    'status' => 'pending',
                ]);
            }

            foreach ($validated['activities'] as $row) {
                $selectedSdgs = array_values(array_unique(array_filter(
                    array_map(static fn ($value) => trim((string) $value), (array) ($row['sdg'] ?? [])),
                    static fn ($value) => $value !== ''
                )));

                ActivityCalendarEntry::query()->create([
                    'activity_calendar_id' => $calendar->id,
                    'activity_date' => $row['date'],
                    'activity_name' => $row['name'],
                    'target_sdg' => implode(', ', $selectedSdgs),
                    'venue' => $row['venue'],
                    'target_participants' => $row['participant_program'],
                    'target_program' => null,
                    'estimated_budget' => $row['budget'],
                ]);
            }
        });

        if ($isAdminCalendar) {
            return redirect()
                ->route('admin.calendars.index')
                ->with('success', 'Activity calendar submitted successfully. Recorded under your SDAO admin account.');
        }

        $latestCalendar = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->latest('id')
            ->first();
        $submittedDocumentsCalendarUrl = $latestCalendar
            ? route('organizations.submitted-documents.calendars.show', $latestCalendar)
            : route('organizations.submitted-documents');
        if ($latestCalendar) {
            $this->createOfficerNotification(
                $user,
                'Activity Calendar Submitted',
                'Your activity calendar has been submitted for review.',
                'info',
                $submittedDocumentsCalendarUrl,
                $latestCalendar
            );
        }

        if (! $latestCalendar) {
            return redirect()
                ->route('organizations.activity-calendar-submission')
                ->with(
                    'activity_calendar_success_redirect',
                    route('organizations.activity-calendar-submission')
                );
        }

        return redirect()
            ->route('organizations.activity-calendar-submission')
            ->with('activity_calendar_success_redirect', $submittedDocumentsCalendarUrl);
    }

    public function storeActivityCalendarDraftEntry(Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'Use the admin submission flow.'], 403);
        }

        $payload = $this->validatedSingleActivityCalendarActivityPayload($request);
        $organization = $this->organizationForWritableActivityCalendarEntries($request);
        if ($organization === null) {
            return response()->json(['message' => 'You cannot add activities right now.'], 403);
        }

        $calendar = $this->getOrCreateWritableActivityCalendar($user, $organization);

        $selectedSdgs = array_values(array_unique(array_filter(
            array_map(static fn ($value) => trim((string) $value), (array) ($payload['sdg'] ?? [])),
            static fn ($value) => $value !== ''
        )));

        $entry = ActivityCalendarEntry::query()->create([
            'activity_calendar_id' => $calendar->id,
            'activity_date' => $payload['date'],
            'activity_name' => $payload['name'],
            'target_sdg' => implode(', ', $selectedSdgs),
            'venue' => $payload['venue'],
            'target_participants' => $payload['participant_program'],
            'target_program' => null,
            'estimated_budget' => $payload['budget'],
        ]);

        return response()->json([
            'entry' => $this->calendarEntryToInitialPayload($entry),
        ], 201);
    }

    public function updateActivityCalendarDraftEntry(Request $request, ActivityCalendarEntry $entry): \Illuminate\Http\JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'Use the admin submission flow.'], 403);
        }

        $organization = $this->organizationForWritableActivityCalendarEntries($request);
        if ($organization === null) {
            return response()->json(['message' => 'You cannot edit activities right now.'], 403);
        }

        if (! $this->calendarEntryWritableForOrganization($organization, $entry)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $payload = $this->validatedSingleActivityCalendarActivityPayload($request);

        $selectedSdgs = array_values(array_unique(array_filter(
            array_map(static fn ($value) => trim((string) $value), (array) ($payload['sdg'] ?? [])),
            static fn ($value) => $value !== ''
        )));

        $entry->update([
            'activity_date' => $payload['date'],
            'activity_name' => $payload['name'],
            'target_sdg' => implode(', ', $selectedSdgs),
            'venue' => $payload['venue'],
            'target_participants' => $payload['participant_program'],
            'estimated_budget' => $payload['budget'],
        ]);

        return response()->json([
            'entry' => $this->calendarEntryToInitialPayload($entry->fresh()),
        ]);
    }

    public function destroyActivityCalendarDraftEntry(Request $request, ActivityCalendarEntry $entry): \Illuminate\Http\JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'Use the admin submission flow.'], 403);
        }

        $organization = $this->organizationForWritableActivityCalendarEntries($request);
        if ($organization === null) {
            return response()->json(['message' => 'You cannot delete activities right now.'], 403);
        }

        if (! $this->calendarEntryWritableForOrganization($organization, $entry)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $entry->delete();

        return response()->json(['ok' => true]);
    }

    // ── Activity Proposal Submission ──────────────────────────

    public function showActivityProposalRequest(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.submissions.activity-proposal');
        }

        $officerAccess = $this->officerSubmissionAccessContext($request);
        if (! $officerAccess['authorized']) {
            abort(403, 'Only organization officers can access this feature.');
        }

        $organization = $officerAccess['organization'];
        $officerValidationPending = $officerAccess['officer_validation_pending'];
        $registrationPending = $officerAccess['registration_pending'];

        if (! $organization || ! $officerAccess['organization_approved']) {
            return view('organizations.activity-proposal-request', [
                'organization' => $organization,
                'officerValidationPending' => $officerValidationPending,
                'registrationPending' => $registrationPending,
                'blockedMessage' => $officerAccess['blocked_message'],
                'natureOptions' => self::ACTIVITY_REQUEST_NATURE_OPTIONS,
                'typeOptions' => self::ACTIVITY_REQUEST_TYPE_OPTIONS,
                'requestForm' => null,
                'requestAttachmentLinks' => [],
                'hasCalendarActivities' => false,
                'calendarEntries' => collect(),
            ]);
        }

        $requestForm = $this->resolveEditableActivityRequestForm($request, $organization);
        $submittedCalendarQuery = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->where('status', '!=', 'draft')
            ->whereHas('entries');
        $hasCalendarActivities = (clone $submittedCalendarQuery)
            ->exists();
        $latestCalendar = (clone $submittedCalendarQuery)
            ->with([
                'entries' => fn ($q) => $q->with('proposal')->orderBy('activity_date')->orderBy('id'),
            ])
            ->latest('submission_date')
            ->latest('id')
            ->first();
        $calendarEntries = $latestCalendar?->entries ?? collect();
        if ($calendarEntries->isEmpty()) {
            $hasCalendarActivities = false;
        }

        return view('organizations.activity-proposal-request', [
            'organization' => $organization,
            'officerValidationPending' => $officerValidationPending,
            'registrationPending' => $registrationPending,
            'natureOptions' => self::ACTIVITY_REQUEST_NATURE_OPTIONS,
            'typeOptions' => self::ACTIVITY_REQUEST_TYPE_OPTIONS,
            'requestForm' => $requestForm,
            'requestAttachmentLinks' => $this->activityRequestAttachmentLinks($requestForm),
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

        $officerAccess = $this->officerSubmissionAccessContext($request);
        if (! $officerAccess['authorized']) {
            abort(403, 'Only organization officers can access this feature.');
        }

        $organization = $officerAccess['organization'];
        if (! $organization) {
            return $this->redirectWhenNoOrganizationForWorkflow($request, 'organizations.profile');
        }

        if ($officerAccess['officer_validation_pending']) {
            return redirect()
                ->route('organizations.activity-proposal-request')
                ->with('error', 'Your student officer account is pending SDAO validation.');
        }

        if (! $officerAccess['organization_approved']) {
            return redirect()
                ->route('organizations.activity-proposal-request')
                ->with('error', 'Your organization registration is not yet approved by SDAO. You cannot submit an activity proposal until your registration is approved.');
        }

        $hasSubmittedCalendarActivities = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->where('status', '!=', 'draft')
            ->whereHas('entries')
            ->exists();

        // Always bind RSO name to the logged-in officer's linked organization.
        $request->merge([
            'rso_name' => (string) ($organization->organization_name ?? ''),
        ]);

        $editableRequest = $this->resolveEditableActivityRequestForm($request, $organization);
        $existingRequestLetterPath = $this->attachmentPath($editableRequest, Attachment::TYPE_REQUEST_LETTER);
        $existingSpeakerResumePath = $this->attachmentPath($editableRequest, Attachment::TYPE_REQUEST_SPEAKER_RESUME);
        $existingPostSurveyPath = $this->attachmentPath($editableRequest, Attachment::TYPE_REQUEST_POST_SURVEY);
        $targetSdgOptions = array_map(static fn ($n) => 'SDG '.$n, range(1, 17));

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
            'target_sdg' => [
                Rule::requiredIf(fn () => (string) $request->input('proposal_source') !== 'calendar'),
                'nullable',
                'array',
                'min:1',
            ],
            'target_sdg.*' => ['string', Rule::in($targetSdgOptions)],
            'proposed_budget' => ['required', 'numeric', 'min:0'],
            'budget_source' => ['required', 'string', Rule::in(['RSO Fund', 'RSO Savings', 'External'])],
            'activity_date' => ['required', 'date'],
            'venue' => ['required', 'string', 'max:255'],
            'request_letter' => [
                Rule::requiredIf(fn () => ! $editableRequest || ! $existingRequestLetterPath),
                'nullable',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png,webp',
                'max:10240',
            ],
            'speaker_resume' => [
                Rule::requiredIf(fn () => in_array('seminar_workshop', (array) $request->input('activity_types', []), true)
                    && ! $existingSpeakerResumePath),
                'nullable',
                'file',
                'mimes:pdf,doc,docx',
                'max:10240',
            ],
            'post_survey_form' => [
                Rule::requiredIf(fn () => ! $editableRequest || ! $existingPostSurveyPath),
                'nullable',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png,webp',
                'max:10240',
            ],
        ], [
            'nature_of_activity.size' => 'Select exactly one Nature of Activity option.',
            'activity_types.size' => 'Select exactly one Type of Activity option.',
        ]);

        if ((string) ($validated['proposal_source'] ?? '') === 'calendar' && ! $hasSubmittedCalendarActivities) {
            return back()
                ->withInput()
                ->withErrors([
                    'proposal_source' => 'No submitted activity calendar is available to link. Submit an activity calendar first before using this option.',
                ]);
        }

        $selectedCalendarEntry = null;
        if ((string) $validated['proposal_source'] === 'calendar') {
            $selectedCalendarEntry = ActivityCalendarEntry::query()
                ->whereKey((int) ($validated['activity_calendar_entry_id'] ?? 0))
                ->whereHas('activityCalendar', function ($q) use ($organization): void {
                    $q->where('organization_id', $organization->id)
                        ->where('status', '!=', 'draft');
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

        // Calendar-linked requests always inherit SDGs from the selected calendar entry.
        // Ignore any posted target_sdg[] payload in this case.
        $resolvedTargetSdg = (string) (implode(', ', (array) ($validated['target_sdg'] ?? [])));
        if ($selectedCalendarEntry) {
            $resolvedTargetSdg = trim((string) ($selectedCalendarEntry->target_sdg ?? ''));
            if ($resolvedTargetSdg === '') {
                return back()
                    ->withInput()
                    ->withErrors([
                        'activity_calendar_entry_id' => 'The selected calendar activity has no SDGs on record. Update the activity calendar entry SDGs first.',
                    ]);
            }
        }

        self::assertSupabaseDiskIsConfigured();

        $payload = [
            'organization_id' => $organization->id,
            'submitted_by' => $user->id,
            'activity_calendar_entry_id' => $selectedCalendarEntry?->id,
            'activity_title' => $validated['activity_title'],
            'partner_entities' => $validated['partner_entities'] ?? null,
            'nature_of_activity' => $validated['nature_of_activity'],
            'nature_other' => $validated['nature_other'] ?? null,
            'activity_types' => $validated['activity_types'],
            'activity_type_other' => $validated['activity_type_other'] ?? null,
            'target_sdg' => $resolvedTargetSdg,
            'proposed_budget' => $validated['proposed_budget'],
            'budget_source' => $validated['budget_source'],
            'activity_date' => $validated['activity_date'],
            'venue' => $validated['venue'],
            'promoted_to_proposal_id' => null,
            'promoted_at' => null,
        ];

        // Persist the request form first so the storage helper can include
        // its primary key in the per-form folder. New uploads then land
        // under "{org-slug}-org-{org_id}/activity-request-forms/{form_id}/{field_key}/...".
        if ($editableRequest) {
            $editableRequest->update($payload);
            $activityRequest = $editableRequest;
        } else {
            $activityRequest = ActivityRequestForm::create($payload);
        }

        $storagePath = app(OrganizationStoragePath::class);

        Log::info('Activity request form file upload debug', [
            'organization_id' => $organization->id,
            'activity_request_form_id' => $activityRequest->id,
            'file_keys' => array_keys($request->allFiles()),
            'organization_folder' => $storagePath->organizationFolder($organization),
        ]);

        $requestLetterPath = null;
        if ($request->hasFile('request_letter')) {
            $requestLetterPath = $request->file('request_letter')->store(
                $storagePath->activityRequestFormFolder($organization, $activityRequest, 'request_letter'),
                'supabase'
            );
        }

        $speakerResumePath = null;
        if ($request->hasFile('speaker_resume')) {
            $speakerResumePath = $request->file('speaker_resume')->store(
                $storagePath->activityRequestFormFolder($organization, $activityRequest, 'speaker_resume'),
                'supabase'
            );
        }

        $postSurveyPath = null;
        if ($request->hasFile('post_survey_form')) {
            $postSurveyPath = $request->file('post_survey_form')->store(
                $storagePath->activityRequestFormFolder($organization, $activityRequest, 'post_survey_form'),
                'supabase'
            );
        }

        if ($request->hasFile('request_letter')) {
            $this->upsertSingleAttachment(
                $activityRequest,
                $user->id,
                Attachment::TYPE_REQUEST_LETTER,
                (string) $requestLetterPath,
                $request->file('request_letter')
            );
        }
        if ($request->hasFile('speaker_resume')) {
            $this->upsertSingleAttachment(
                $activityRequest,
                $user->id,
                Attachment::TYPE_REQUEST_SPEAKER_RESUME,
                (string) $speakerResumePath,
                $request->file('speaker_resume')
            );
        }
        if ($request->hasFile('post_survey_form')) {
            $this->upsertSingleAttachment(
                $activityRequest,
                $user->id,
                Attachment::TYPE_REQUEST_POST_SURVEY,
                (string) $postSurveyPath,
                $request->file('post_survey_form')
            );
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

        $officerAccess = $this->officerSubmissionAccessContext($request);
        if (! $officerAccess['authorized']) {
            abort(403, 'Only organization officers can access this feature.');
        }

        $organization = $officerAccess['organization'];
        $officerValidationPending = $officerAccess['officer_validation_pending'];
        $registrationPending = $officerAccess['registration_pending'];

        if (! $organization || ! $officerAccess['organization_approved']) {
            return view('organizations.activity-proposal-submission', [
                'organization' => $organization,
                'officerValidationPending' => $officerValidationPending,
                'registrationPending' => $registrationPending,
                'blockedMessage' => $officerAccess['blocked_message'],
                'isBlocked' => true,
                'blockedReason' => $officerAccess['blocked_message'],
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
            $viewData['officerValidationPending'] = $officerValidationPending;
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
    ) {
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
        $resolvedCalendarEntryId = null;

        if (! $forAdminPortal && $requestForm?->activity_calendar_entry_id) {
            $resolvedCalendarEntryId = (int) $requestForm->activity_calendar_entry_id;
        } elseif ($forAdminPortal && $request->filled('calendar_entry')) {
            $resolvedCalendarEntryId = (int) $request->integer('calendar_entry');
        }

        if ($resolvedCalendarEntryId) {
            $calendarEntry = ActivityCalendarEntry::query()
                ->whereKey($resolvedCalendarEntryId)
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
                ->with('budgetItems')
                ->first();

            if ($linkedProposal && ! in_array(strtoupper((string) $linkedProposal->status), ['DRAFT', 'REVISION'], true)) {
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
            'proposalStep2FileLinks' => $this->proposalStep2AttachmentLinksForForm($linkedProposal),
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
            $officerAccess = $this->officerSubmissionAccessContext($request);
            if (! $officerAccess['authorized']) {
                abort(403, 'Only organization officers can access this feature.');
            }
            if ($officerAccess['officer_validation_pending']) {
                return redirect()
                    ->route('organizations.activity-proposal-submission')
                    ->with('error', 'Your student officer account is pending SDAO validation.');
            }
            if ($officerAccess['organization'] && ! $officerAccess['organization_approved']) {
                return redirect()
                    ->route('organizations.activity-proposal-submission')
                    ->with('error', 'Your organization registration is not yet approved by SDAO. You cannot submit an activity proposal until your registration is approved.');
            }

            $organization = $officerAccess['organization'];
            if (! $organization) {
                return $this->redirectWhenNoOrganizationForWorkflow($request, 'organizations.profile');
            }
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
        if (! $isAdminProposal) {
            $lockedCalendarEntryId = (int) ($requestForm->activity_calendar_entry_id ?? 0);
            if ($lockedCalendarEntryId > 0) {
                $proposalSourceInput = 'calendar';
                $request->merge([
                    'proposal_source' => 'calendar',
                    'activity_calendar_entry_id' => $lockedCalendarEntryId,
                ]);
            } else {
                $proposalSourceInput = 'unlisted';
                $request->merge([
                    'proposal_source' => 'unlisted',
                    'activity_calendar_entry_id' => null,
                ]);
            }
        }

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

        if ($existing && ! in_array(strtoupper((string) $existing->status), ['DRAFT', 'REVISION'], true)) {
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
                Rule::requiredIf(fn () => $existing === null || ! $this->attachmentPath($existing, Attachment::TYPE_PROPOSAL_LOGO)),
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
            'proposed_start_time' => ['required', 'date_format:H:i'],
            'proposed_end_time' => ['required', 'date_format:H:i', 'after:proposed_start_time'],
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
                    && ($existing === null || ! $this->attachmentPath($existing, Attachment::TYPE_PROPOSAL_EXTERNAL_FUNDING))),
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
            'proposed_end_time.after' => 'The proposed end time must be later than the proposed start time.',
        ]);

        $academicTermId = $this->resolveOrCreateAcademicTermId((string) $validated['academic_year']);
        $normalizedTitle = mb_strtolower(trim((string) $validated['project_activity_title']));
        $duplicateTitleExists = ActivityProposal::query()
            ->where('organization_id', $organization->id)
            ->where('academic_term_id', $academicTermId)
            ->whereRaw('LOWER(TRIM(activity_title)) = ?', [$normalizedTitle])
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
            ->exists();
        if ($duplicateTitleExists) {
            return back()
                ->withInput()
                ->withErrors([
                    'project_activity_title' => 'A proposal with this activity title already exists.',
                ]);
        }

        if ($calendarEntry && $existing === null) {
            $submittedProposalExists = ActivityProposal::query()
                ->where('organization_id', $organization->id)
                ->where('activity_calendar_entry_id', $calendarEntry->id)
                ->whereNotIn('status', ['draft'])
                ->exists();
            if ($submittedProposalExists) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'activity_calendar_entry_id' => 'This activity already has a submitted proposal.',
                    ]);
            }
        }

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
            $price = round($quantity * $unitPrice, 2);

            if ($material === '' || $quantity <= 0 || $unitPrice < 0) {
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
            if ($existing && strtoupper((string) $existing->status) === 'REVISION') {
                $proposalStatus = 'revision';
                $submissionDate = $existing->submission_date;
            } else {
                $proposalStatus = 'draft';
                $submissionDate = null;
            }
        } else {
            $proposalStatus = 'pending';
            $submissionDate = now()->toDateString();
        }

        self::assertSupabaseDiskIsConfigured();

        $summary = mb_substr(trim(strip_tags($validated['overall_goal'])), 0, 500);
        $currentApprovalStep = $existing?->current_approval_step ?? 0;

        $payload = [
            'organization_id' => $organization->id,
            'activity_calendar_id' => $calendarEntry?->activity_calendar_id,
            'activity_calendar_entry_id' => $calendarEntry?->id,
            'submitted_by' => $user->id,
            'academic_term_id' => $academicTermId,
            'school_code' => $validated['school'],
            'program' => $validated['department_program'],
            'activity_title' => $validated['project_activity_title'],
            'activity_description' => $summary !== '' ? $summary : null,
            'proposed_start_date' => $validated['proposed_start_date'],
            'proposed_end_date' => $validated['proposed_end_date'],
            'proposed_start_time' => $validated['proposed_start_time'],
            'proposed_end_time' => $validated['proposed_end_time'],
            'venue' => $validated['venue'],
            'overall_goal' => $validated['overall_goal'],
            'specific_objectives' => $validated['specific_objectives'],
            'criteria_mechanics' => $validated['criteria_mechanics'],
            'program_flow' => $validated['program_flow'],
            'target_sdg' => null,
            'estimated_budget' => $validated['proposed_budget'],
            'source_of_funding' => $validated['source_of_funding'],
            'submission_date' => $submissionDate,
            'status' => $proposalStatus,
            'current_approval_step' => $currentApprovalStep,
        ];

        // Persist the proposal before any uploads so the storage helper can
        // include its primary key in the per-proposal folder. New uploads
        // then land under
        // "{org-slug}-org-{org_id}/activity-proposals/{proposal_id}/{field_key}/...".
        if ($existing) {
            $existing->update($payload);
            $proposal = $existing;
        } else {
            $proposal = ActivityProposal::create($payload);
        }
        $this->syncProposalBudgetItems($proposal, $normalizedBudgetItems);

        $storagePath = app(OrganizationStoragePath::class);

        Log::info('Activity proposal file upload debug', [
            'proposal_id' => $proposal->id,
            'organization_id' => $organization->id,
            'file_keys' => array_keys($request->allFiles()),
            'organization_folder' => $storagePath->organizationFolder($organization),
        ]);

        $logoPath = $this->attachmentPath($proposal, Attachment::TYPE_PROPOSAL_LOGO);
        if ($request->hasFile('organization_logo')) {
            $logoPath = $request->file('organization_logo')->store(
                $storagePath->activityProposalFolder($organization, $proposal, 'logo'),
                'supabase'
            );
        }

        $resumePath = $this->attachmentPath($proposal, Attachment::TYPE_PROPOSAL_RESOURCE_RESUME);
        if ($request->hasFile('resume_resource_persons')) {
            $resumePath = $request->file('resume_resource_persons')->store(
                $storagePath->activityProposalFolder($organization, $proposal, 'resume_resource_persons'),
                'supabase'
            );
        }

        $externalFundingPath = $this->attachmentPath($proposal, Attachment::TYPE_PROPOSAL_EXTERNAL_FUNDING);
        if ($validated['source_of_funding'] !== 'External') {
            $externalFundingPath = null;
        } elseif ($request->hasFile('external_funding_support')) {
            $externalFundingPath = $request->file('external_funding_support')->store(
                $storagePath->activityProposalFolder($organization, $proposal, 'external_funding_support'),
                'supabase'
            );
        }

        if ($request->hasFile('organization_logo')) {
            $this->upsertSingleAttachment(
                $proposal,
                $user->id,
                Attachment::TYPE_PROPOSAL_LOGO,
                (string) $logoPath,
                $request->file('organization_logo')
            );
        }
        if ($request->hasFile('resume_resource_persons')) {
            $this->upsertSingleAttachment(
                $proposal,
                $user->id,
                Attachment::TYPE_PROPOSAL_RESOURCE_RESUME,
                (string) $resumePath,
                $request->file('resume_resource_persons')
            );
        }
        if ($validated['source_of_funding'] === 'External' && $request->hasFile('external_funding_support')) {
            $this->upsertSingleAttachment(
                $proposal,
                $user->id,
                Attachment::TYPE_PROPOSAL_EXTERNAL_FUNDING,
                (string) $externalFundingPath,
                $request->file('external_funding_support')
            );
        } elseif ($validated['source_of_funding'] !== 'External') {
            $proposal->attachments()
                ->where('file_type', Attachment::TYPE_PROPOSAL_EXTERNAL_FUNDING)
                ->delete();
        }

        if (! $isAdminProposal && ! $asDraft) {
            $requestForm = $this->resolvePendingActivityRequestForm($request, $organization);
            if ($requestForm) {
                $requestForm->update([
                    'promoted_at' => now(),
                ]);
            }
        }

        if (! $asDraft) {
            $this->initializeProposalApprovalWorkflow(
                $proposal,
                $user->id,
                $existing !== null && strtoupper((string) ($existing->status ?? '')) === 'REVISION'
            );
        }

        $successMessage = $asDraft
            ? 'Proposal saved as draft. You can continue editing anytime.'
            : 'Activity proposal submitted successfully for SDAO review.';

        if (! $isAdminProposal && ! $asDraft) {
            $this->createOfficerNotification(
                $user,
                'Activity Proposal Submitted',
                'Your activity proposal has been submitted for review.',
                'info',
                route('organizations.activity-submission.proposals.show', $proposal),
                $proposal
            );
        }

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
            $startTime = $linked->proposed_start_time ?: '';
            if (is_string($startTime) && strlen($startTime) >= 8 && str_contains($startTime, ':')) {
                $startTime = substr($startTime, 0, 5);
            }
            $endTime = $linked->proposed_end_time ?: '';
            if (is_string($endTime) && strlen($endTime) >= 8 && str_contains($endTime, ':')) {
                $endTime = substr($endTime, 0, 5);
            }

            return [
                'organization_name' => $organization->organization_name,
                'academic_year' => $linked->academic_year ?? $this->activeAcademicYear(),
                'school' => $linked->school_code ?: $schoolPrefill,
                'department_program' => $linked->program ?? '',
                'project_activity_title' => $linked->activity_title ?? '',
                'proposed_start_date' => optional($linked->proposed_start_date)?->toDateString() ?? '',
                'proposed_end_date' => optional($linked->proposed_end_date)?->toDateString() ?? '',
                'proposed_start_time' => $startTime,
                'proposed_end_time' => $endTime,
                'venue' => $linked->venue ?? '',
                'overall_goal' => $linked->overall_goal ?? '',
                'specific_objectives' => $linked->specific_objectives ?? '',
                'criteria_mechanics' => $linked->criteria_mechanics ?? '',
                'program_flow' => $linked->program_flow ?? '',
                'proposed_budget' => $linked->estimated_budget !== null ? (string) $linked->estimated_budget : '',
                'source_of_funding' => in_array((string) ($linked->source_of_funding ?? ''), ['RSO Fund', 'RSO Savings', 'External'], true)
                    ? (string) $linked->source_of_funding
                    : 'RSO Fund',
                'budget_items' => $this->proposalBudgetItemsForPrefill($linked),
            ];
        }

        if ($entry !== null) {
            $cal = $entry->activityCalendar;
            $dateStr = optional($entry->activity_date)?->toDateString() ?? '';

            $prefill = [
                'organization_name' => $organization->organization_name,
                'academic_year' => $cal?->academic_year ?? $this->activeAcademicYear(),
                'school' => $schoolPrefill,
                'department_program' => '',
                'project_activity_title' => $entry->activity_name ?? '',
                'proposed_start_date' => $dateStr,
                'proposed_end_date' => $dateStr,
                'proposed_start_time' => '',
                'proposed_end_time' => '',
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
            'department_program' => '',
            'project_activity_title' => '',
            'proposed_start_date' => '',
            'proposed_end_date' => '',
            'proposed_start_time' => '',
            'proposed_end_time' => '',
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
     * @param  array<int, array{material:string,quantity:float,unit_price:float,price:float}>  $normalizedBudgetItems
     */
    private function syncProposalBudgetItems(ActivityProposal $proposal, array $normalizedBudgetItems): void
    {
        DB::transaction(function () use ($proposal, $normalizedBudgetItems): void {
            ProposalBudgetItem::query()
                ->where('activity_proposal_id', $proposal->id)
                ->delete();

            foreach ($normalizedBudgetItems as $item) {
                ProposalBudgetItem::query()->create([
                    'activity_proposal_id' => $proposal->id,
                    'category' => 'general',
                    'item_description' => (string) $item['material'],
                    'quantity' => round((float) $item['quantity'], 2),
                    'unit_cost' => round((float) $item['unit_price'], 2),
                    'total_cost' => round((float) $item['price'], 2),
                ]);
            }
        });
    }

    /**
     * @param  array<int, array{material:string,quantity:float,unit_price:float,price:float}>  $normalizedBudgetItems
     * @return array<int, array{line_no:int,material:string,quantity:float,unit_price:float,price:float}>
     */
    private function serializeLegacyBudgetItems(array $normalizedBudgetItems): array
    {
        return collect($normalizedBudgetItems)
            ->values()
            ->map(function (array $item, int $idx): array {
                return [
                    'line_no' => $idx + 1,
                    'material' => (string) $item['material'],
                    'quantity' => round((float) $item['quantity'], 2),
                    'unit_price' => round((float) $item['unit_price'], 2),
                    'price' => round((float) $item['price'], 2),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{material:string,quantity:float,unit_price:float,price:float}>
     */
    private function proposalBudgetItemsForPrefill(ActivityProposal $proposal): array
    {
        $normalized = $proposal->budgetItems()
            ->orderBy('id')
            ->get()
            ->map(fn (ProposalBudgetItem $item): array => [
                'material' => (string) $item->item_description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_cost,
                'price' => (float) $item->total_cost,
            ])
            ->all();

        if ($normalized !== []) {
            return $normalized;
        }

        return [];
    }

    private function initializeProposalApprovalWorkflow(ActivityProposal $proposal, int $actorId, bool $isResubmission): void
    {
        $existingSteps = $proposal->workflowSteps()->count();
        if ($existingSteps === 0) {
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
            $proposal->update(['current_approval_step' => 1]);
        } else {
            // Preserve prior approvals on revision resubmission; re-open only revision-required step.
            if ($isResubmission) {
                $proposal->workflowSteps()
                    ->where('status', 'revision_required')
                    ->update([
                        'status' => 'pending',
                        'is_current_step' => true,
                        'review_comments' => null,
                        'acted_at' => null,
                    ]);
                $proposal->update([
                    'current_approval_step' => (int) ($proposal->workflowSteps()->where('is_current_step', true)->value('step_order') ?? 1),
                ]);
            }
        }

        ApprovalLog::query()->create([
            'approvable_type' => ActivityProposal::class,
            'approvable_id' => $proposal->id,
            'workflow_step_id' => $proposal->workflowSteps()->where('is_current_step', true)->value('id'),
            'actor_id' => $actorId,
            'action' => $isResubmission ? 'resubmitted' : 'submitted',
            'from_status' => $isResubmission ? 'REVISION' : 'DRAFT',
            'to_status' => 'PENDING',
            'comments' => null,
            'created_at' => now(),
        ]);
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

        $report = ActivityReport::create([
            'activity_proposal_id' => $validated['proposal_id'],
            'organization_id' => $organization->id,
            'submitted_by' => $user->id,
            'report_submission_date' => now()->toDateString(),
            'status' => 'pending',
            'accomplishment_summary' => $validated['summary_description'],
            'event_title' => $validated['activity_event_title'],
            'event_starts_at' => $validated['event_starts_at'],
            'event_ends_at' => $validated['event_starts_at'],
            'activity_chairs' => $validated['activity_chairs'],
            'prepared_by' => $validated['prepared_by'],
            'program_content' => $validated['program_content'],
            'evaluation_report' => $validated['evaluation_report'],
            'participants_reached_percent' => $validated['participants_reached_percent'],
        ]);

        $this->upsertSingleAttachment(
            $report,
            $user->id,
            Attachment::TYPE_REPORT_POSTER,
            $posterPath,
            $request->file('poster_image')
        );
        $this->syncIndexedAttachments(
            $report,
            $user->id,
            Attachment::TYPE_REPORT_SUPPORTING_PHOTO,
            $photoPaths,
            $uploadedPhotos
        );
        if ($certificatePath !== null && $request->hasFile('certificate_sample')) {
            $this->upsertSingleAttachment(
                $report,
                $user->id,
                Attachment::TYPE_REPORT_CERTIFICATE,
                $certificatePath,
                $request->file('certificate_sample')
            );
        }
        if ($evaluationFormPath !== null && $request->hasFile('evaluation_form_sample')) {
            $this->upsertSingleAttachment(
                $report,
                $user->id,
                Attachment::TYPE_REPORT_EVALUATION_FORM,
                $evaluationFormPath,
                $request->file('evaluation_form_sample')
            );
        }
        $this->upsertSingleAttachment(
            $report,
            $user->id,
            Attachment::TYPE_REPORT_ATTENDANCE,
            $attendancePath,
            $request->file('attendance_sheet')
        );

        $this->createOfficerNotification(
            $user,
            'After-Activity Report Submitted',
            'Your after-activity report has been submitted for review.',
            'info',
            route('organizations.submitted-documents.reports.show', $report),
            $report
        );

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
            ->whereNotIn('status', ['draft'])
            ->with('activityReport')
            ->orderByDesc('proposed_start_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $activities = [];
        $eligible = [];
        $dueReminders = [];

        foreach ($proposals as $proposal) {
            $proposalStatus = strtoupper((string) ($proposal->status ?? 'PENDING'));

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
                    'activity_proposal_id' => $proposal->id,
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
                'proposal_status_label' => $this->activityProposalStatusPresentation($proposal->status)['label'],
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

    /**
     * Central officer-side access context for submission pages.
     * - true 403 only when user is not in officer flow at all
     * - officer business-rule blocks are surfaced as page-level messages
     *
     * Activity calendar and activity proposal submissions additionally require the
     * officer's organization to have an SDAO-approved registration. We compute that
     * here so routes, controllers, and blade templates share a single source of truth
     * and no caller accidentally relies on the `pending` organization status alone
     * (which is case-sensitive under PostgreSQL).
     *
     * @return array{
     *   authorized: bool,
     *   officer_validation_pending: bool,
     *   organization: ?Organization,
     *   organization_approved: bool,
     *   registration_pending: bool,
     *   blocked_message: ?string
     * }
     */
    private function officerSubmissionAccessContext(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return [
                'authorized' => false,
                'officer_validation_pending' => false,
                'organization' => null,
                'organization_approved' => false,
                'registration_pending' => false,
                'blocked_message' => null,
            ];
        }

        $hasOfficerRecord = $user->organizationOfficers()->exists();
        $isOfficerRole = $user->effectiveRoleType() === 'ORG_OFFICER';
        $authorized = $isOfficerRole || $hasOfficerRecord;

        if (! $authorized) {
            return [
                'authorized' => false,
                'officer_validation_pending' => false,
                'organization' => null,
                'organization_approved' => false,
                'registration_pending' => false,
                'blocked_message' => null,
            ];
        }

        $officerValidationPending = ! $user->isOfficerValidated();
        $organization = $this->resolveOrganizationForWorkflows($request);
        $organizationApproved = (bool) $organization
            && ($organization->isApprovedOrganization() || $organization->hasApprovedRegistration());
        $registrationPending = (bool) $organization && ! $organizationApproved;

        $blockedMessage = null;
        if ($officerValidationPending) {
            $blockedMessage = 'Your student officer account is pending SDAO validation. You cannot submit or edit forms until validation is complete.';
        } elseif (! $organization) {
            $blockedMessage = 'Your account is not yet linked to an active organization. You cannot submit or edit forms until your officer record is activated.';
        } elseif (! $organizationApproved) {
            $blockedMessage = 'Your organization registration is not yet approved by SDAO. Activity submissions become available once your registration is approved.';
        }

        return [
            'authorized' => true,
            'officer_validation_pending' => $officerValidationPending,
            'organization' => $organization,
            'organization_approved' => $organizationApproved,
            'registration_pending' => $registrationPending,
            'blocked_message' => $blockedMessage,
        ];
    }

    private function resolveActiveOfficer(Request $request): ?OrganizationOfficer
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $user->effectiveRoleType() !== 'ORG_OFFICER') {
            return null;
        }

        return $user->organizationOfficers()
            ->where('status', 'active')
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
            ->whereNull('promoted_at')
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
            ->whereNull('promoted_at')
            ->first();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ActivityCalendarEntry>|array<int, ActivityCalendarEntry>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function mapCalendarEntriesToInitialActivities(Collection $entries): array
    {
        return $entries->map(fn (ActivityCalendarEntry $e) => $this->calendarEntryToInitialPayload($e))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function calendarEntryToInitialPayload(ActivityCalendarEntry $e): array
    {
        $sdgs = array_values(array_filter(array_map('trim', explode(',', (string) ($e->target_sdg ?? '')))));

        return [
            'entryId' => $e->id,
            'date' => $e->activity_date?->format('Y-m-d'),
            'name' => $e->activity_name,
            'sdgs' => $sdgs,
            'venue' => $e->venue,
            'participantProgram' => (string) ($e->target_participants ?? ''),
            'budget' => $e->estimated_budget !== null ? (string) $e->estimated_budget : '',
        ];
    }

    private function validatedSingleActivityCalendarActivityPayload(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string'],
            'sdg' => ['required', 'array', 'min:1'],
            'sdg.*' => ['required', 'string', Rule::in(array_map(static fn (int $n) => 'SDG '.$n, range(1, 17)))],
            'venue' => ['required', 'string'],
            'participant_program' => ['required', 'string'],
            'budget' => ['required', 'numeric'],
        ]);
    }

    private function organizationForWritableActivityCalendarEntries(Request $request): ?Organization
    {
        $officerAccess = $this->officerSubmissionAccessContext($request);
        if (! $officerAccess['authorized'] || $officerAccess['officer_validation_pending']) {
            return null;
        }
        $organization = $officerAccess['organization'];
        if (! $organization || ! $officerAccess['organization_approved']) {
            return null;
        }

        $latestCalendar = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->latest('submission_date')
            ->latest('id')
            ->first();

        $st = strtolower((string) ($latestCalendar->status ?? ''));
        $calendarSubmittedLocked = $latestCalendar !== null
            && ! in_array($st, ['revision', 'draft'], true);

        if ($calendarSubmittedLocked) {
            return null;
        }

        return $organization;
    }

    private function getOrCreateWritableActivityCalendar(User $user, Organization $organization): ActivityCalendar
    {
        $termId = $this->resolveOrCreateAcademicTermId($this->activeAcademicYear());

        $draft = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->where('academic_term_id', $termId)
            ->where('status', 'draft')
            ->first();

        if ($draft) {
            return $draft;
        }

        $latest = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->latest('submission_date')
            ->latest('id')
            ->first();

        if (
            $latest
            && strtolower((string) $latest->status) === 'revision'
            && (int) $latest->academic_term_id === $termId
        ) {
            return $latest;
        }

        $hasNonEditableForTerm = ActivityCalendar::query()
            ->where('organization_id', $organization->id)
            ->where('academic_term_id', $termId)
            ->whereNotIn('status', ['draft', 'revision'])
            ->exists();

        if ($hasNonEditableForTerm) {
            abort(403, 'This activity calendar has already been submitted and can no longer be edited.');
        }

        return ActivityCalendar::create([
            'organization_id' => $organization->id,
            'submitted_by' => $user->id,
            'academic_term_id' => $termId,
            'submission_date' => null,
            'status' => 'draft',
        ]);
    }

    private function calendarEntryWritableForOrganization(Organization $organization, ActivityCalendarEntry $entry): bool
    {
        $calendar = $entry->activityCalendar;
        if ((int) $calendar->organization_id !== (int) $organization->id) {
            return false;
        }

        $st = strtolower((string) $calendar->status);

        return in_array($st, ['draft', 'revision'], true);
    }

    private function resolveOrCreateAcademicTermId(string $academicYear): int
    {
        $term = AcademicTerm::query()
            ->where('academic_year', $academicYear)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $term) {
            $term = AcademicTerm::query()
                ->where('academic_year', $academicYear)
                ->orderBy('id')
                ->first();
        }

        if ($term) {
            return (int) $term->id;
        }

        $startYear = (int) now()->format('Y');
        if (preg_match('/^(\d{4})-(\d{4})$/', $academicYear, $m) === 1) {
            $startYear = (int) $m[1];
        }

        return (int) AcademicTerm::query()->create([
            'academic_year' => $academicYear,
            'semester' => 'first',
            'starts_at' => sprintf('%d-06-01', $startYear),
            'ends_at' => sprintf('%d-10-31', $startYear),
            'is_active' => false,
        ])->id;
    }

    private function syncSubmissionRequirements(
        OrganizationSubmission $submission,
        array $allowedKeys,
        Collection $selectedKeys,
        string $requirementsOther
    ): void {
        $labels = $this->requirementLabelMap();

        foreach ($allowedKeys as $key) {
            $label = $labels[$key] ?? str_replace('_', ' ', $key);
            if ($key === 'others' && trim($requirementsOther) !== '') {
                $label .= ' ('.trim($requirementsOther).')';
            }

            SubmissionRequirement::query()->updateOrCreate(
                [
                    'submission_id' => $submission->id,
                    'requirement_key' => $key,
                ],
                [
                    'label' => $label,
                    'is_submitted' => $selectedKeys->contains($key),
                ]
            );
        }
    }

    /**
     * Persist (or refresh) one Attachment row per uploaded requirement file.
     *
     * Accepts either the legacy `[key => path]` shape or the richer
     * `[key => ['path' => string, 'file' => UploadedFile]]` shape returned by
     * `storeRequirementFilesForKeys`. The richer shape is preferred because it
     * preserves the original filename, mime type, and size — all of which the
     * file-streaming controller relies on for sane Content-Disposition and
     * Content-Type headers when serving the file back.
     *
     * @param  array<string, string|array{path: string, file?: UploadedFile}>  $filePaths
     */
    private function attachRequirementFiles(OrganizationSubmission $submission, array $filePaths, int $uploadedBy): void
    {
        foreach ($filePaths as $requirementKey => $entry) {
            if (is_string($entry)) {
                $storedPath = $entry;
                $uploadedFile = null;
            } elseif (is_array($entry)) {
                $storedPath = (string) ($entry['path'] ?? '');
                $candidate = $entry['file'] ?? null;
                $uploadedFile = $candidate instanceof UploadedFile ? $candidate : null;
            } else {
                continue;
            }

            if (trim($storedPath) === '') {
                continue;
            }

            $originalName = $uploadedFile?->getClientOriginalName() ?: basename($storedPath);
            $mimeType = null;
            $fileSizeKb = null;
            if ($uploadedFile !== null) {
                $mimeType = $uploadedFile->getClientMimeType() ?: $uploadedFile->getMimeType();
                $size = (int) $uploadedFile->getSize();
                if ($size > 0) {
                    $fileSizeKb = (int) ceil($size / 1024);
                }
            }

            Attachment::query()->updateOrCreate(
                [
                    'attachable_type' => OrganizationSubmission::class,
                    'attachable_id' => $submission->id,
                    'file_type' => Attachment::fileTypeForSubmissionRequirementKey($submission->type, $requirementKey),
                ],
                [
                    'uploaded_by' => $uploadedBy,
                    'original_name' => $originalName,
                    'stored_path' => $storedPath,
                    'mime_type' => $mimeType,
                    'file_size_kb' => $fileSizeKb,
                ]
            );
        }
    }

    private function upsertSingleAttachment(
        Model $attachable,
        int $uploadedBy,
        string $fileType,
        string $storedPath,
        ?UploadedFile $uploadedFile = null
    ): void {
        if (trim($storedPath) === '') {
            return;
        }

        Attachment::query()->updateOrCreate(
            [
                'attachable_type' => $attachable::class,
                'attachable_id' => (int) $attachable->getKey(),
                'file_type' => $fileType,
            ],
            [
                'uploaded_by' => $uploadedBy,
                'original_name' => $uploadedFile?->getClientOriginalName() ?: basename($storedPath),
                'stored_path' => $storedPath,
                'mime_type' => $uploadedFile?->getClientMimeType(),
                'file_size_kb' => $uploadedFile ? (int) ceil(((int) $uploadedFile->getSize()) / 1024) : null,
            ]
        );
    }

    /**
     * @param  array<int, string>  $storedPaths
     * @param  array<int, UploadedFile>  $uploadedFiles
     */
    private function syncIndexedAttachments(
        Model $attachable,
        int $uploadedBy,
        string $fileTypePrefix,
        array $storedPaths,
        array $uploadedFiles = []
    ): void {
        foreach ($storedPaths as $idx => $storedPath) {
            if (! is_string($storedPath) || trim($storedPath) === '') {
                continue;
            }

            $uploadedFile = $uploadedFiles[$idx] ?? null;
            if ($uploadedFile !== null && ! $uploadedFile instanceof UploadedFile) {
                $uploadedFile = null;
            }

            $this->upsertSingleAttachment(
                $attachable,
                $uploadedBy,
                $fileTypePrefix.':'.$idx,
                $storedPath,
                $uploadedFile
            );
        }
    }

    private function attachmentPath(?Model $attachable, string $fileType): ?string
    {
        if (! $attachable || ! method_exists($attachable, 'attachments')) {
            return null;
        }

        $attachment = $attachable->attachments()
            ->where('file_type', $fileType)
            ->latest('id')
            ->first();

        return ($attachment && is_string($attachment->stored_path) && $attachment->stored_path !== '')
            ? $attachment->stored_path
            : null;
    }

    /**
     * @return array<string, array{path: string, name: string, url: string}>
     */
    private function activityRequestAttachmentLinks(?ActivityRequestForm $requestForm): array
    {
        if (! $requestForm) {
            return [];
        }

        $map = [
            'request_letter' => Attachment::TYPE_REQUEST_LETTER,
            'speaker_resume' => Attachment::TYPE_REQUEST_SPEAKER_RESUME,
            'post_survey_form' => Attachment::TYPE_REQUEST_POST_SURVEY,
        ];

        $links = [];
        foreach ($map as $key => $fileType) {
            $attachment = $requestForm->attachments()
                ->where('file_type', $fileType)
                ->latest('id')
                ->first();

            if (! $attachment) {
                continue;
            }

            $storedPath = (string) ($attachment->stored_path ?? '');
            if ($storedPath === '') {
                continue;
            }

            $links[$key] = [
                'path' => $storedPath,
                'name' => (string) ($attachment->original_name ?: basename($storedPath)),
                'url' => route('organizations.submitted-documents.activity-request-forms.file', [$requestForm, $key]),
                'mime_type' => $attachment->mime_type ? (string) $attachment->mime_type : null,
                'file_size_kb' => $attachment->file_size_kb,
            ];
        }

        return $links;
    }

    /**
     * Step 2 proposal file inputs: existing rows on ActivityProposal.attachments (not legacy *_path columns).
     *
     * @return array<string, array{name: string, url: string, mime_type: ?string, file_size_kb: ?int}>
     */
    private function proposalStep2AttachmentLinksForForm(?ActivityProposal $proposal): array
    {
        if (! $proposal) {
            return [];
        }

        $map = [
            'organization_logo' => ['routeKey' => 'logo', 'file_type' => Attachment::TYPE_PROPOSAL_LOGO],
            'resume_resource_persons' => ['routeKey' => 'resume', 'file_type' => Attachment::TYPE_PROPOSAL_RESOURCE_RESUME],
            'external_funding_support' => ['routeKey' => 'external', 'file_type' => Attachment::TYPE_PROPOSAL_EXTERNAL_FUNDING],
        ];

        $out = [];
        foreach ($map as $inputName => $meta) {
            $attachment = $proposal->attachments()
                ->where('file_type', $meta['file_type'])
                ->latest('id')
                ->first();

            if (! $attachment) {
                continue;
            }

            $storedPath = (string) ($attachment->stored_path ?? '');
            if ($storedPath === '') {
                continue;
            }

            $out[$inputName] = [
                'name' => (string) ($attachment->original_name ?: basename($storedPath)),
                'url' => route('organizations.submitted-documents.proposals.file', [$proposal, $meta['routeKey']]),
                'mime_type' => $attachment->mime_type ? (string) $attachment->mime_type : null,
                'file_size_kb' => $attachment->file_size_kb,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function requirementLabelMap(): array
    {
        return [
            'letter_of_intent' => 'Letter of intent',
            'application_form' => 'Application form',
            'by_laws' => 'By-laws',
            'updated_list_of_officers_founders' => 'Updated list of officers / founders',
            'dean_endorsement_faculty_adviser' => 'Dean endorsement (faculty adviser)',
            'proposed_projects_budget' => 'Proposed projects and budget',
            'others' => 'Other requirement',
            'by_laws_updated_if_applicable' => 'By-laws (if updated)',
            'updated_list_of_officers_founders_ay' => 'Updated list of officers / founders (AY)',
            'past_projects' => 'Past projects',
            'financial_statement_previous_ay' => 'Financial statement (previous AY)',
            'evaluation_summary_past_projects' => 'Evaluation summary (past projects)',
        ];
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
                'max:'.self::REQUIREMENT_FILE_MAX_KB,
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function requirementFileValidationMessages(): array
    {
        return [
            'requirement_files.*.mimes' => 'Only PDF, Word, or image files are allowed.',
            'requirement_files.*.max' => 'The selected file is too large. Maximum allowed file size is '.self::REQUIREMENT_FILE_MAX_MB.' MB.',
        ];
    }

    /**
     * @param  array<int, string>  $requiredKeys
     */
    private function enforceRequiredRequirementSelectionsAndFiles(Request $request, array $requiredKeys): void
    {
        $selected = $request->input('requirements', []);
        if (! is_array($selected)) {
            $selected = [];
        }

        $errors = [];
        foreach ($requiredKeys as $key) {
            if (! in_array($key, $selected, true)) {
                $errors["requirements.$key"] = 'This requirement must be selected and must have an attached file.';

                continue;
            }

            $file = $request->file("requirement_files.$key");
            if (! ($file instanceof UploadedFile) || ! $file->isValid()) {
                $errors["requirement_files.$key"] = 'Please attach a file for this requirement.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function isEligibleAdviserUserId(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return User::query()
            ->whereKey($userId)
            ->whereHas('role', function ($query): void {
                $query->whereIn('name', self::ADVISER_ROLE_NAMES);
            })
            ->exists();
    }

    private function adviserDisplayNameByUserId(int $userId): ?string
    {
        $adviser = User::query()->find($userId);
        if (! $adviser) {
            return null;
        }

        return trim((string) $adviser->full_name) !== ''
            ? (string) $adviser->full_name
            : trim(((string) $adviser->first_name).' '.((string) $adviser->last_name));
    }

    private function syncSubmissionAdviserNomination(int $organizationId, int $submissionId, int $adviserUserId): void
    {
        OrganizationAdviser::query()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'submission_id' => $submissionId,
            ],
            [
                'user_id' => $adviserUserId,
                'assigned_at' => now()->toDateString(),
                'status' => 'pending',
                'relieved_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_notes' => null,
            ]
        );
    }

    /**
     * Stores each uploaded requirement file on the public disk and returns
     * both the resulting relative path AND the originating UploadedFile so
     * the caller can persist the original filename, mime type, and size on
     * the matching attachment row.
     *
     * @param  Collection<int, string>  $reqs
     * @param  array<int, string>  $allowedKeys
     * @return array<string, array{path: string, file: UploadedFile}>
     */
    private function storeRequirementFilesForKeys(
        Request $request,
        Collection $reqs,
        array $allowedKeys,
        string $storageBasePath
    ): array {
        self::assertSupabaseDiskIsConfigured();

        $stored = [];
        foreach ($allowedKeys as $key) {
            if (! $reqs->contains($key)) {
                continue;
            }
            $file = $request->file("requirement_files.$key");
            if ($file && $file->isValid()) {
                // Persist to Supabase Storage. The bucket itself is configured
                // on the `supabase` disk (SUPABASE_STORAGE_BUCKET), so the
                // object path stays bucket-relative — e.g.
                //   "{organizationId}/registration/<random>.pdf"
                // or
                //   "{organizationId}/renewals/{submissionId}/<random>.pdf"
                $path = $file->store($storageBasePath, 'supabase');
                if (is_string($path) && $path !== '') {
                    $stored[$key] = ['path' => $path, 'file' => $file];
                }
            }
        }

        return $stored;
    }

    /**
     * Fail fast if the Supabase S3 disk is missing required values.
     *
     * Without this, an empty `key` / `secret` makes the AWS SDK fall back to
     * `InstanceProfileProvider`, which silently tries the EC2 metadata
     * endpoint and hangs the request until PHP's max_execution_time elapses
     * (the "Maximum execution time of 30 seconds exceeded" error coming out
     * of vendor/aws/aws-sdk-php/src/Credentials/InstanceProfileProvider.php).
     *
     * Only non-secret values are logged (presence flags, region, bucket,
     * endpoint) — never the key id or secret itself.
     */
    public static function assertSupabaseDiskIsConfigured(): void
    {
        $diskConfig = config('filesystems.disks.supabase', []);
        if (! is_array($diskConfig)) {
            $diskConfig = [];
        }

        Log::info('Supabase storage config check', [
            'disk' => 'supabase',
            'has_key' => ! empty($diskConfig['key']),
            'has_secret' => ! empty($diskConfig['secret']),
            'region' => $diskConfig['region'] ?? null,
            'bucket' => $diskConfig['bucket'] ?? null,
            'endpoint' => $diskConfig['endpoint'] ?? null,
        ]);

        foreach (['key', 'secret', 'region', 'bucket', 'endpoint'] as $required) {
            if (empty($diskConfig[$required])) {
                throw new \RuntimeException(
                    "Supabase storage disk is missing required config: {$required}. "
                    .'Set SUPABASE_STORAGE_ACCESS_KEY_ID, SUPABASE_STORAGE_SECRET_ACCESS_KEY, '
                    .'SUPABASE_STORAGE_REGION, SUPABASE_STORAGE_BUCKET, and SUPABASE_STORAGE_ENDPOINT '
                    .'in .env, then run `php artisan config:clear`.'
                );
            }
        }
    }

    private function createOfficerNotification(
        User $user,
        string $title,
        ?string $message,
        string $type = 'info',
        ?string $linkUrl = null,
        ?Model $related = null
    ): void {
        app(OrganizationNotificationService::class)->createForUser(
            $user,
            $title,
            $message,
            $type,
            $linkUrl,
            $related
        );
    }
}
