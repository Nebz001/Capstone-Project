<?php

namespace App\Http\Controllers;

use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\ActivityReport;
use App\Models\ActivityRequestForm;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\OrganizationAdviser;
use App\Models\OrganizationOfficer;
use App\Models\OrganizationRegistration;
use App\Models\OrganizationRenewal;
use App\Models\OrganizationRevisionFieldUpdate;
use App\Models\OrganizationSubmission;
use App\Models\SubmissionRequirement;
use App\Models\User;
use App\Services\OrganizationNotificationService;
use App\Support\SubmissionRoutingProgress;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationSubmittedDocumentsController extends Controller
{
    private const REPLACEMENT_FILE_MAX_KB = 2048;
    private const REPLACEMENT_FILE_MAX_MB = 2;

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
        if ($user && $user->effectiveRoleType() === 'ORG_OFFICER') {
            $hasAnyOfficerRecord = $user->organizationOfficers()->exists();
            $activeOfficer = $user->organizationOfficers()
                ->where('status', 'active')
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

    public function showSubmittedRegistration(Request $request, OrganizationSubmission $submission): View
    {
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);
        $this->ensureOfficerOwnsOrganization($request, (int) $submission->organization_id);

        $pendingUpdateRows = OrganizationRevisionFieldUpdate::query()
            ->where('organization_submission_id', $submission->id)
            ->whereNull('acknowledged_at')
            ->get(['section_key', 'field_key', 'new_file_meta']);
        $pendingRequirementUpdateKeys = $pendingUpdateRows
            ->filter(fn ($row): bool => (string) $row->section_key === 'requirements' && is_array($row->new_file_meta))
            ->pluck('field_key')
            ->filter(fn ($key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
        $pendingRevisionItemSet = [];
        foreach ($pendingUpdateRows as $row) {
            $pendingRevisionItemSet[(string) $row->section_key.'.'.(string) $row->field_key] = true;
        }
        $pendingRequirementUpdateKeySet = array_fill_keys($pendingRequirementUpdateKeys, true);
        $rawRevisionSections = $this->moduleRevisionSections(is_array($submission->registration_field_reviews) ? $submission->registration_field_reviews : []);
        $revisionSections = [];
        foreach ($rawRevisionSections as $section) {
            $sectionKey = (string) ($section['section_key'] ?? '');
            $items = collect((array) ($section['items'] ?? []))
                ->filter(function ($item) use ($sectionKey, $pendingRequirementUpdateKeySet, $pendingRevisionItemSet): bool {
                    if (! is_array($item)) {
                        return false;
                    }
                    $fieldKey = (string) ($item['field_key'] ?? '');
                    if ($fieldKey !== '' && isset($pendingRevisionItemSet[$sectionKey.'.'.$fieldKey])) {
                        return false;
                    }
                    if ($sectionKey !== 'requirements') {
                        return true;
                    }

                    return $fieldKey === '' || ! isset($pendingRequirementUpdateKeySet[$fieldKey]);
                })
                ->values()
                ->all();
            if ($items === []) {
                continue;
            }
            $section['items'] = $items;
            $revisionSections[] = $section;
        }
        $hasOpenRevisionItems = $revisionSections !== [];
        $isResubmittedPendingReview = ! $hasOpenRevisionItems && $pendingRevisionItemSet !== [];
        $statusForView = $isResubmittedPendingReview ? 'UNDER_REVIEW' : $submission->legacyStatus();
        $sp = $this->submissionStatusPresentation($statusForView);
        $savedUpdatedKeys = OrganizationRevisionFieldUpdate::query()
            ->where('organization_submission_id', $submission->id)
            ->where('section_key', 'requirements')
            ->whereNotNull('new_file_meta')
            ->pluck('field_key')
            ->filter(fn ($key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
        $recentlyReplacedKeys = collect((array) $request->session()->get('replaced_requirement_keys', []))
            ->filter(fn ($key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
        $fileLinks = $this->registrationFileLinksFromSubmission($submission, $revisionSections, $savedUpdatedKeys, $recentlyReplacedKeys, $pendingRequirementUpdateKeys);

        $backNav = $this->registrationDetailBackNavigation($request);
        $adviserNomination = $this->submissionAdviserNomination($submission);
        return view('organizations.submitted-documents.detail', [
            'backRoute' => $backNav['route'],
            'backLabel' => $backNav['label'],
            'pageTitle' => 'Registration submission',
            'subtitle' => 'Organization registration application on file with SDAO.',
            'statusLabel' => $sp['label'],
            'statusClass' => $sp['badge_class'],
            'metaRows' => $this->registrationMetaRowsFromSubmission($submission),
            'remarkHighlight' => $this->truncatePreview($submission->additional_remarks ?: $submission->notes, 220),
            'revisionSections' => $revisionSections,
            'isResubmittedPendingReview' => $isResubmittedPendingReview,
            'fileLinks' => $fileLinks,
            'workflowLinks' => [],
            'adviserNomination' => $adviserNomination,
            'canRenominateAdviser' => $adviserNomination?->status === 'rejected',
            'adviserRenominateActionUrl' => route('organizations.submitted-documents.adviser.renominate', $submission),
            'submitActionUrl' => route('organizations.submitted-documents.registrations.resubmit', $submission),
            'canSubmitFileRevision' => collect($fileLinks)->contains(fn (array $row): bool => (bool) ($row['can_replace'] ?? false)),
            'calendarEntries' => null,
            'progressDocumentLabel' => 'Organization registration',
            'progressStages' => SubmissionRoutingProgress::stagesForSimpleSdaoPipeline($statusForView),
            'progressSummary' => SubmissionRoutingProgress::summaryForSimpleSdao($statusForView),
        ]);
    }

    public function streamSubmittedRegistrationRequirementFile(Request $request, OrganizationSubmission $submission, string $key): Response
    {
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);
        $this->authorizeSubmissionFileView($request, $submission);

        if (! in_array($key, self::REGISTRATION_FILE_KEYS, true)) {
            abort(404, 'Unknown registration requirement.');
        }

        return $this->streamSubmissionRequirementAttachment($submission, $key);
    }

    public function replaceSubmittedRegistrationRequirementFile(Request $request, OrganizationSubmission $submission, string $key): RedirectResponse
    {
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);
        $this->ensureOfficerOwnsOrganization($request, (int) $submission->organization_id);
        abort_unless(in_array($key, self::REGISTRATION_FILE_KEYS, true), 404);

        $fieldReviews = is_array($submission->registration_field_reviews) ? $submission->registration_field_reviews : [];
        $status = (string) data_get($fieldReviews, 'requirements.'.$key.'.status', 'pending');
        abort_unless($status === 'flagged', 403);

        $validated = $request->validate([
            'replacement_file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:'.self::REPLACEMENT_FILE_MAX_KB],
        ], [
            'replacement_file.mimes' => 'Only PDF, Word, or image files are allowed.',
            'replacement_file.max' => 'The selected file is too large. Maximum allowed file size is '.self::REPLACEMENT_FILE_MAX_MB.' MB.',
        ]);

        /** @var User $user */
        $user = $request->user();
        $upload = $validated['replacement_file'];
        $this->applyRegistrationRequirementReplacement($submission, $user, $key, $upload);
        $this->notifyRegistrationFileReplacementToAdmins($submission);

        return back()
            ->with('success', 'Replacement file uploaded successfully.')
            ->with('replaced_requirement_keys', [$key]);
    }

    public function resubmitRegistrationRevisionFiles(Request $request, OrganizationSubmission $submission): RedirectResponse
    {
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);
        $this->ensureOfficerOwnsOrganization($request, (int) $submission->organization_id);

        $fieldReviews = is_array($submission->registration_field_reviews) ? $submission->registration_field_reviews : [];
        $revisionKeys = collect(self::REGISTRATION_FILE_KEYS)
            ->filter(fn (string $key): bool => (string) data_get($fieldReviews, 'requirements.'.$key.'.status', 'pending') === 'flagged')
            ->values()
            ->all();
        if ($revisionKeys === []) {
            return back()->with('error', 'No revised files are pending for replacement.');
        }

        $validated = $request->validate([
            'replacement_files' => ['required', 'array'],
            'replacement_files.*' => ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:'.self::REPLACEMENT_FILE_MAX_KB],
        ], [
            'replacement_files.*.mimes' => 'Only PDF, Word, or image files are allowed.',
            'replacement_files.*.max' => 'The selected file is too large. Maximum allowed file size is '.self::REPLACEMENT_FILE_MAX_MB.' MB.',
        ]);
        $incoming = is_array($validated['replacement_files'] ?? null) ? $validated['replacement_files'] : [];
        $changedKeys = array_values(array_filter($revisionKeys, fn (string $key): bool => isset($incoming[$key]) && $incoming[$key] instanceof \Illuminate\Http\UploadedFile));
        if ($changedKeys === []) {
            return back()->with('error', 'Replace at least one revised file before submitting.');
        }

        /** @var User $user */
        $user = $request->user();
        foreach ($changedKeys as $key) {
            /** @var \Illuminate\Http\UploadedFile $upload */
            $upload = $incoming[$key];
            $this->applyRegistrationRequirementReplacement($submission, $user, $key, $upload);
        }
        $this->notifyRegistrationFileReplacementToAdmins($submission);

        return back()
            ->with('success', 'Updated file(s) submitted for review.')
            ->with('replaced_requirement_keys', $changedKeys);
    }

    public function showSubmittedRenewal(Request $request, OrganizationSubmission $submission): View
    {
        abort_unless($submission->type === OrganizationSubmission::TYPE_RENEWAL, 404);
        $this->ensureOfficerOwnsOrganization($request, (int) $submission->organization_id);

        $sp = $this->submissionStatusPresentation($submission->legacyStatus());
        $fileLinks = $this->renewalFileLinksFromSubmission($submission);
        $revisionSections = $this->moduleRevisionSections(is_array($submission->renewal_field_reviews) ? $submission->renewal_field_reviews : []);
        $adviserNomination = $this->submissionAdviserNomination($submission);

        return view('organizations.submitted-documents.detail', [
            'backRoute' => $this->submittedDocumentsListUrl($request),
            'pageTitle' => 'Renewal submission',
            'subtitle' => 'Organization renewal application on file with SDAO.',
            'statusLabel' => $sp['label'],
            'statusClass' => $sp['badge_class'],
            'metaRows' => $this->renewalMetaRowsFromSubmission($submission),
            'remarkHighlight' => $this->truncatePreview($submission->additional_remarks ?: $submission->notes, 160),
            'revisionSections' => $revisionSections,
            'fileLinks' => $fileLinks,
            'workflowLinks' => $this->submissionWorkflowLinks($submission, route('organizations.renew')),
            'adviserNomination' => $adviserNomination,
            'canRenominateAdviser' => $adviserNomination?->status === 'rejected',
            'adviserRenominateActionUrl' => route('organizations.submitted-documents.adviser.renominate', $submission),
            'calendarEntries' => null,
            'progressDocumentLabel' => 'Organization renewal',
            'progressStages' => SubmissionRoutingProgress::stagesForSimpleSdaoPipeline($submission->legacyStatus()),
            'progressSummary' => SubmissionRoutingProgress::summaryForSimpleSdao($submission->legacyStatus()),
        ]);
    }

    public function streamSubmittedRenewalRequirementFile(Request $request, OrganizationSubmission $submission, string $key): Response
    {
        abort_unless($submission->type === OrganizationSubmission::TYPE_RENEWAL, 404);
        $this->authorizeSubmissionFileView($request, $submission);

        if (! in_array($key, self::RENEWAL_FILE_KEYS, true)) {
            abort(404, 'Unknown renewal requirement.');
        }

        return $this->streamSubmissionRequirementAttachment($submission, $key);
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

        $sp = $this->submissionStatusPresentation($calendar->status);
        $fileLinks = [];
        if ($calendar->calendar_file) {
            $fileLinks[] = [
                'label' => 'Uploaded calendar file (PDF / document)',
                'url' => route('organizations.submitted-documents.calendars.file', $calendar),
            ];
        }

        $revisionSections = $this->moduleRevisionSections(is_array($calendar->admin_field_reviews) ? $calendar->admin_field_reviews : []);
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
            'remarkHighlight' => $this->revisionSectionsPreview($revisionSections),
            'revisionSections' => $revisionSections,
            'fileLinks' => $fileLinks,
            'workflowLinks' => $this->activityCalendarWorkflowLinks($calendar),
            'calendarEntries' => $calendar->entries,
            'progressDocumentLabel' => 'Activity calendar',
            'progressStages' => SubmissionRoutingProgress::stagesForSimpleSdaoPipeline($calendar->status),
            'progressSummary' => SubmissionRoutingProgress::summaryForSimpleSdao($calendar->status),
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
        $proposal->load(['calendar', 'calendarEntry', 'academicTerm', 'organization', 'budgetItems']);

        $sp = $this->submissionStatusPresentation($proposal->status, true);
        $revisionSections = $this->moduleRevisionSections(is_array($proposal->admin_field_reviews) ? $proposal->admin_field_reviews : []);
        $requestForm = $this->relatedRequestFormForProposal($proposal);
        $resolvedFilePaths = [];
        foreach (['logo', 'request_letter', 'speaker_resume', 'post_survey_form', 'external', 'resume'] as $fileKey) {
            $resolvedPath = $this->proposalResolvedFilePathByKey($proposal, $requestForm, $fileKey);
            if ($resolvedPath !== null) {
                $resolvedFilePaths[$fileKey] = $resolvedPath;
            }
        }
        $fileUrls = [];
        foreach ($resolvedFilePaths as $fileKey => $resolvedPath) {
            $fileUrls[$fileKey] = route('organizations.submitted-documents.proposals.file', [
                'proposal' => $proposal,
                'key' => $fileKey,
                'p' => $this->encodePathParam($resolvedPath),
            ]);
        }

        $school = $this->proposalSchoolLabel($proposal);
        $proposalTime = $this->proposalTimeRangeLabel($proposal);
        $proposalDates = trim(collect([
            optional($proposal->proposed_start_date)->format('M j, Y'),
            optional($proposal->proposed_end_date)->format('M j, Y'),
        ])->filter()->implode(' → ')) ?: '—';
        $nature = $this->requestFormOptionLabels(
            is_array($requestForm?->nature_of_activity) ? $requestForm->nature_of_activity : [],
            [
                'co_curricular' => 'Co-Curricular',
                'non_curricular' => 'Non-Curricular',
                'community_extension' => 'Community Extension',
                'others' => 'Others',
            ],
            $requestForm?->nature_other
        );
        $types = $this->requestFormOptionLabels(
            is_array($requestForm?->activity_types) ? $requestForm->activity_types : [],
            [
                'seminar_workshop' => 'Seminar / Workshop',
                'general_assembly' => 'General Assembly',
                'orientation' => 'Orientation',
                'competition' => 'Competition',
                'recruitment_audition' => 'Recruitment / Audition',
                'donation_drive_fundraising' => 'Donation Drive / Fundraising Activity',
                'outreach_donation' => 'Outreach (Donation)',
                'fundraising_activity' => 'Fundraising Activity',
                'off_campus_activity' => 'Off-campus Activity',
                'others' => 'Others',
            ],
            $requestForm?->activity_type_other
        );
        $targetSdg = $requestForm?->target_sdg
            ?? $proposal->target_sdg
            ?? $proposal->calendarEntry?->target_sdg
            ?? $proposal->calendarEntry?->sdg
            ?? '—';
        $linkedCalendarLabel = $proposal->calendar
            ? trim(($proposal->calendar->academic_year ?? '—').' · '.$this->activityCalendarTermLabel($proposal->calendar->semester))
            : '—';
        $calendarRowLabel = $proposal->calendarEntry
            ? trim(($proposal->calendarEntry->activity_name ?? '—').' · '.(optional($proposal->calendarEntry->activity_date)->format('M j, Y') ?? ''))
            : '—';
        $isCalendarLinkedProposal = $proposal->activity_calendar_entry_id !== null;
        $step1ActivityDate = $requestForm?->activity_date
            ? optional($requestForm->activity_date)->format('M j, Y')
            : null;

        $step1Rows = [
            ['key' => 'step1_proposal_option', 'label' => 'Proposal option', 'value' => $proposal->activity_calendar_entry_id ? 'From submitted Activity Calendar' : 'Activity not in submitted calendar'],
            ['key' => 'step1_rso_name', 'label' => 'RSO name', 'value' => $requestForm?->rso_name ?: ($proposal->organization?->organization_name ?? '—')],
            ['key' => 'step1_activity_title', 'label' => 'Title of activity', 'value' => $requestForm?->activity_title ?: '—'],
            ['key' => 'step1_partner_entities', 'label' => 'Partner entities', 'value' => $requestForm?->partner_entities ?: '—'],
            ['key' => 'step1_nature_of_activity', 'label' => 'Nature of activity', 'value' => $nature],
            ['key' => 'step1_type_of_activity', 'label' => 'Type of activity', 'value' => $types],
            ['key' => 'step1_target_sdg', 'label' => 'Target SDG', 'value' => $targetSdg ?: '—'],
            ['key' => 'step1_proposed_budget', 'label' => 'Step 1 proposed budget', 'value' => $requestForm?->proposed_budget !== null ? number_format((float) $requestForm->proposed_budget, 2) : '—'],
            ['key' => 'step1_budget_source', 'label' => 'Step 1 budget source', 'value' => $requestForm?->budget_source ?: '—'],
            ['key' => 'step1_activity_date', 'label' => 'Date of activity', 'value' => $step1ActivityDate ?: '—'],
            ['key' => 'step1_venue', 'label' => 'Venue', 'value' => $requestForm?->venue ?: '—'],
        ];
        if ($isCalendarLinkedProposal) {
            array_splice($step1Rows, 2, 0, [
                ['key' => 'step1_linked_activity_calendar', 'label' => 'Linked activity calendar', 'value' => $linkedCalendarLabel],
                ['key' => 'step1_calendar_activity_row', 'label' => 'Calendar activity row', 'value' => $calendarRowLabel],
            ]);
        }

        $step1AttachmentRows = [];
        if (isset($resolvedFilePaths['request_letter'])) {
            $step1AttachmentRows[] = [
                'key' => 'step1_request_letter',
                'label' => 'Request letter',
                'value' => 'Submitted file attached.',
                'link_url' => $fileUrls['request_letter'] ?? null,
            ];
        } else {
            $step1AttachmentRows[] = [
                'key' => 'step1_request_letter_unavailable',
                'label' => 'Request letter',
                'value' => 'File unavailable',
            ];
        }
        if (isset($resolvedFilePaths['speaker_resume'])) {
            $step1AttachmentRows[] = [
                'key' => 'step1_speaker_resume',
                'label' => 'Resume of speaker',
                'value' => 'Submitted file attached.',
                'link_url' => $fileUrls['speaker_resume'] ?? null,
            ];
        }
        if (isset($resolvedFilePaths['post_survey_form'])) {
            $step1AttachmentRows[] = [
                'key' => 'step1_post_survey_form',
                'label' => 'Sample post-survey form',
                'value' => 'Submitted file attached.',
                'link_url' => $fileUrls['post_survey_form'] ?? null,
            ];
        }

        $budgetRows = $proposal->budgetItems->values();
        $budgetRowsTotal = $budgetRows->sum(fn ($row) => (float) ($row->total_cost ?? 0));
        $sourceOfFunding = (string) ($proposal->source_of_funding ?? '');
        $isExternalFunding = strtoupper($sourceOfFunding) === 'EXTERNAL';
        $step2Rows = [
            [
                'key' => 'step2_organization_logo',
                'label' => 'Organization logo',
                'value' => isset($resolvedFilePaths['logo']) ? 'Submitted file attached.' : 'File unavailable',
                'link_url' => isset($resolvedFilePaths['logo'])
                    ? ($fileUrls['logo'] ?? null)
                    : null,
            ],
            ['key' => 'step2_organization', 'label' => 'Organization (form)', 'value' => $proposal->organization?->organization_name ?? '—'],
            ['key' => 'step2_academic_year', 'label' => 'Academic year', 'value' => $proposal->academicTerm?->academic_year ?? $proposal->calendar?->academic_year ?? '—'],
            ['key' => 'step2_department', 'label' => 'Department', 'value' => $school],
            ['key' => 'step2_program', 'label' => 'Program', 'value' => $proposal->program ?: '—'],
            ['key' => 'step2_activity_title', 'label' => 'Project / activity title', 'value' => $proposal->activity_title ?? '—'],
            ['key' => 'step2_proposed_dates', 'label' => 'Proposed dates', 'value' => $proposalDates],
            ['key' => 'step2_proposed_time', 'label' => 'Proposed time', 'value' => $proposalTime],
            ['key' => 'step2_venue', 'label' => 'Venue', 'value' => $proposal->venue ?? '—'],
            ['key' => 'step2_overall_goal', 'label' => 'Overall goal', 'value' => $proposal->overall_goal ?: '—', 'wide' => true],
            ['key' => 'step2_specific_objectives', 'label' => 'Specific objectives', 'value' => $proposal->specific_objectives ?: '—', 'wide' => true],
            ['key' => 'step2_criteria_mechanics', 'label' => 'Criteria / mechanics', 'value' => $proposal->criteria_mechanics ?: '—', 'wide' => true],
            ['key' => 'step2_program_flow', 'label' => 'Program flow', 'value' => $proposal->program_flow ?: '—', 'wide' => true],
            ['key' => 'step2_budget_total', 'label' => 'Proposed budget (total)', 'value' => $proposal->estimated_budget !== null ? number_format((float) $proposal->estimated_budget, 2) : '—'],
            ['key' => 'step2_source_of_funding', 'label' => 'Source of funding', 'value' => $sourceOfFunding !== '' ? $sourceOfFunding : '—'],
            [
                'key' => 'step2_budget_table',
                'label' => 'Detailed budget table',
                'value' => $budgetRows->count() > 0 ? ('Rows: '.$budgetRows->count().' · Total: '.number_format((float) $budgetRowsTotal, 2)) : 'No rows submitted.',
                'table' => $budgetRows->map(function ($row): array {
                    return [
                        'material' => (string) ($row->item_description ?? '—'),
                        'quantity' => $row->quantity !== null ? (string) $row->quantity : '—',
                        'unit_price' => $row->unit_cost !== null ? number_format((float) $row->unit_cost, 2) : '—',
                        'price' => $row->total_cost !== null ? number_format((float) $row->total_cost, 2) : '—',
                    ];
                })->all(),
                'wide' => true,
            ],
            ['key' => 'step2_submitted', 'label' => 'Submitted', 'value' => optional($proposal->submission_date)->format('M j, Y') ?? '—'],
        ];
        if ($isExternalFunding && isset($resolvedFilePaths['external'])) {
            $step2Rows[] = [
                'key' => 'step2_external_funding_support',
                'label' => 'External funding support',
                'value' => 'Submitted file attached.',
                'link_url' => $fileUrls['external'] ?? null,
            ];
        } elseif ($isExternalFunding) {
            $step2Rows[] = [
                'key' => 'step2_external_funding_support_unavailable',
                'label' => 'External funding support',
                'value' => 'File unavailable',
            ];
        }

        $additionalRows = [];
        if (isset($resolvedFilePaths['resume'])) {
            $additionalRows[] = [
                'key' => 'additional_resume_resource_persons',
                'label' => 'Resume / resource persons',
                'value' => 'Submitted file attached.',
                'link_url' => $fileUrls['resume'] ?? null,
            ];
        }

        $fileLinks = [];
        foreach (array_merge($step1AttachmentRows, array_filter($step2Rows, fn (array $row): bool => ! empty($row['link_url'])), $additionalRows) as $row) {
            $fileLinks[] = [
                'label' => (string) ($row['label'] ?? 'File'),
                'url' => (string) ($row['link_url'] ?? ''),
            ];
        }
        $fileLinks = array_values(array_filter($fileLinks, static fn (array $row): bool => ($row['url'] ?? '') !== ''));

        $metaSections = [
            ['title' => 'Step 1: Activity Request Form', 'rows' => array_merge($step1Rows, $step1AttachmentRows)],
            ['title' => 'Step 2: Proposal Submission', 'rows' => $step2Rows],
        ];
        if ($additionalRows !== []) {
            $metaSections[] = ['title' => 'Additional', 'rows' => $additionalRows];
        }

        $nav = $this->detailBackNavigation($request);

        return view('organizations.submitted-documents.detail', [
            'backRoute' => $nav['route'],
            'backLabel' => $nav['label'],
            'pageTitle' => 'Activity proposal',
            'subtitle' => $proposal->activity_title ?? 'Submitted proposal',
            'statusLabel' => $sp['label'],
            'statusClass' => $sp['badge_class'],
            'metaRows' => $step2Rows,
            'metaSections' => $metaSections,
            'remarkHighlight' => $this->revisionSectionsPreview($revisionSections) ?: $this->truncatePreview($proposal->overall_goal, 220),
            'revisionSections' => $revisionSections,
            'fileLinks' => $fileLinks,
            'workflowLinks' => $this->activityProposalWorkflowLinks($request, $proposal),
            'calendarEntries' => null,
            'progressDocumentLabel' => 'Activity proposal',
            'progressStages' => SubmissionRoutingProgress::stagesForActivityProposal($proposal),
            'progressSummary' => SubmissionRoutingProgress::summaryForActivityProposal($proposal->status),
        ]);
    }

    public function streamSubmittedActivityProposalFile(Request $request, ActivityProposal $proposal, string $key): StreamedResponse
    {
        $this->ensureOfficerOwnsOrganization($request, (int) $proposal->organization_id);
        $requestForm = $this->relatedRequestFormForProposal($proposal);
        $relativePath = $this->decodePathParam((string) $request->query('p'));
        if (! $relativePath || ! $this->isPathAllowedForProposalOrganization($relativePath, (int) $proposal->organization_id)) {
            $relativePath = $this->proposalResolvedFilePathByKey($proposal, $requestForm, $key);
        }

        if (! is_string($relativePath) || $relativePath === '') {
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

        $sp = $this->submissionStatusPresentation($report->status, false, 'report');
        $revisionSections = $this->moduleRevisionSections(is_array($report->admin_field_reviews) ? $report->admin_field_reviews : []);

        $fileLinks = [];
        if ($this->reportFilePathByKey($report, 'poster')) {
            $fileLinks[] = ['label' => 'Poster image', 'url' => route('organizations.submitted-documents.reports.file', ['report' => $report, 'key' => 'poster'])];
        }
        $supportingPhotoKeys = $this->reportSupportingPhotoKeys($report);
        foreach ($supportingPhotoKeys as $i => $key) {
            $fileLinks[] = [
                'label' => 'Supporting photo '.($i + 1),
                'url' => route('organizations.submitted-documents.reports.file', ['report' => $report, 'key' => $key]),
            ];
        }
        if ($this->reportFilePathByKey($report, 'certificate')) {
            $fileLinks[] = ['label' => 'Certificate sample', 'url' => route('organizations.submitted-documents.reports.file', ['report' => $report, 'key' => 'certificate'])];
        }
        if ($this->reportFilePathByKey($report, 'evaluation_form')) {
            $fileLinks[] = ['label' => 'Evaluation form sample', 'url' => route('organizations.submitted-documents.reports.file', ['report' => $report, 'key' => 'evaluation_form'])];
        }
        if ($this->reportFilePathByKey($report, 'attendance')) {
            $fileLinks[] = ['label' => 'Attendance sheet', 'url' => route('organizations.submitted-documents.reports.file', ['report' => $report, 'key' => 'attendance'])];
        }

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
            'remarkHighlight' => $this->revisionSectionsPreview($revisionSections) ?: $this->truncatePreview($report->evaluation_report, 200),
            'revisionSections' => $revisionSections,
            'fileLinks' => $fileLinks,
            'workflowLinks' => [
                ['label' => 'Submit another report', 'href' => $this->withSuperAdminOrgQuery($request, route('organizations.after-activity-report')), 'variant' => 'secondary'],
            ],
            'calendarEntries' => null,
            'progressDocumentLabel' => 'After activity report',
            'progressStages' => SubmissionRoutingProgress::stagesForActivityReport($report->status),
            'progressSummary' => SubmissionRoutingProgress::summaryForActivityReport($report->status),
        ]);
    }

    public function streamSubmittedAfterActivityReportFile(Request $request, ActivityReport $report, string $key): StreamedResponse
    {
        $this->ensureOfficerOwnsOrganization($request, (int) $report->organization_id);

        $organizationId = (int) $report->organization_id;
        $expectedPrefixes = [
            'activity-reports/'.$organizationId.'/',
        ];

        $relativePath = $this->reportFilePathByKey($report, $key);

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

        if (! $user || $user->effectiveRoleType() !== 'ORG_OFFICER') {
            abort(403, 'Only organization officers can access this feature.');
        }

        $officer = $user->organizationOfficers()
            ->where('status', 'active')
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
                'REVISION', 'REVISION_REQUIRED' => ['label' => 'For revision', 'badge_class' => 'bg-orange-100 text-orange-700 border border-orange-200', 'filter' => 'REVISION'],
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

    /**
     * @param  array<string, mixed>  $fieldReviews
     * @return list<array{title: string, items: list<array{field: string, note: string}>}>
     */
    private function moduleRevisionSections(array $fieldReviews): array
    {
        $sections = [];
        foreach ($fieldReviews as $sectionKey => $fields) {
            if (! is_array($fields)) {
                continue;
            }
            $items = [];
            foreach ($fields as $fieldKey => $row) {
                if (! is_array($row) || ($row['status'] ?? null) !== 'flagged') {
                    continue;
                }
                $note = trim((string) ($row['note'] ?? ''));
                if ($note === '') {
                    continue;
                }
                $items[] = [
                    'field' => trim((string) ($row['label'] ?? 'Field')) ?: 'Field',
                    'note' => $note,
                    'section_key' => (string) $sectionKey,
                    'field_key' => (string) $fieldKey,
                    'anchor_id' => (string) $sectionKey === 'requirements'
                        ? $this->revisionTargetAnchorId((string) $sectionKey, (string) $fieldKey)
                        : '',
                ];
            }
            if ($items !== []) {
                $title = match ((string) $sectionKey) {
                    'requirements' => 'Requirements Attached',
                    default => ucwords(str_replace('_', ' ', (string) $sectionKey)),
                };
                $sections[] = [
                    'title' => $title,
                    'section_key' => (string) $sectionKey,
                    'items' => $items,
                ];
            }
        }

        return $sections;
    }

    /**
     * @param  list<array{title: string, items: list<array{field: string, note: string}>}>  $sections
     */
    private function revisionSectionsPreview(array $sections): ?string
    {
        $notes = [];
        foreach ($sections as $section) {
            foreach (($section['items'] ?? []) as $item) {
                $notes[] = ($item['field'] ?? 'Field').': '.($item['note'] ?? '');
            }
        }
        if ($notes === []) {
            return null;
        }

        return $this->truncatePreview(implode(' — ', $notes), 160);
    }

    private function registrationOfficerRevisionNotesPreview(OrganizationSubmission $submission): ?string
    {
        $fieldReviews = is_array($submission->registration_field_reviews) ? $submission->registration_field_reviews : [];
        $notes = [];
        foreach ($fieldReviews as $fields) {
            if (! is_array($fields)) {
                continue;
            }
            foreach ($fields as $field) {
                if (! is_array($field) || ($field['status'] ?? null) !== 'flagged') {
                    continue;
                }
                $label = trim((string) ($field['label'] ?? ''));
                $note = trim((string) ($field['note'] ?? ''));
                if ($note === '') {
                    continue;
                }
                $notes[] = ($label !== '' ? $label.': ' : '').$note;
            }
        }

        if ($notes !== []) {
            return $this->truncatePreview(implode(' — ', $notes), 160);
        }

        return $this->truncatePreview($submission->additional_remarks ?: $submission->notes, 160);
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
    private function submissionWorkflowLinks(OrganizationSubmission $submission, string $resubmitHref): array
    {
        $links = [];
        $status = strtoupper((string) $submission->legacyStatus());
        if (in_array($status, ['REVISION', 'REVISION_REQUIRED'], true)) {
            $links[] = ['label' => 'Edit / resubmit', 'href' => $resubmitHref, 'variant' => 'primary'];
        }

        return $links;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function registrationMetaRowsFromSubmission(OrganizationSubmission $submission): array
    {
        return [
            ['label' => 'Contact person', 'value' => $submission->contact_person ?? '—'],
            ['label' => 'Adviser name', 'value' => $submission->adviser_name ?? '—'],
            ['label' => 'Contact number', 'value' => $submission->contact_no ?? '—'],
            ['label' => 'Contact email', 'value' => $submission->contact_email ?? '—'],
            ['label' => 'Academic year', 'value' => $submission->academicTerm?->academic_year ?? '—'],
            ['label' => 'Submitted', 'value' => optional($submission->submission_date)->format('M j, Y') ?? '—'],
            ['label' => 'Last updated', 'value' => optional($submission->updated_at)->format('M j, Y g:i A') ?? '—'],
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function renewalMetaRowsFromSubmission(OrganizationSubmission $submission): array
    {
        return [
            ['label' => 'Contact person', 'value' => $submission->contact_person ?? '—'],
            ['label' => 'Adviser name', 'value' => $submission->adviser_name ?? '—'],
            ['label' => 'Contact number', 'value' => $submission->contact_no ?? '—'],
            ['label' => 'Contact email', 'value' => $submission->contact_email ?? '—'],
            ['label' => 'Academic year', 'value' => $submission->academicTerm?->academic_year ?? '—'],
            ['label' => 'Submitted', 'value' => optional($submission->submission_date)->format('M j, Y') ?? '—'],
            ['label' => 'Last updated', 'value' => optional($submission->updated_at)->format('M j, Y g:i A') ?? '—'],
        ];
    }

    /**
     * @return list<array{label: string, url: ?string, missing?: bool}>
     */
    private function registrationFileLinksFromSubmission(
        OrganizationSubmission $submission,
        array $revisionSections = [],
        array $savedUpdatedKeys = [],
        array $recentlyReplacedKeys = [],
        array $pendingRequirementUpdateKeys = []
    ): array
    {
        $revisionByKey = $this->revisionNoteMapByRequirementKey($revisionSections);
        return $this->submissionRequirementFileLinks(
            $submission,
            self::REGISTRATION_FILE_KEYS,
            self::REGISTRATION_FILE_LABELS,
            Attachment::TYPE_REGISTRATION_REQUIREMENT,
            'organizations.submitted-documents.registrations.file',
            $revisionByKey,
            $savedUpdatedKeys,
            $recentlyReplacedKeys,
            $pendingRequirementUpdateKeys
        );
    }

    /**
     * @return list<array{label: string, url: ?string, missing?: bool}>
     */
    private function renewalFileLinksFromSubmission(OrganizationSubmission $submission): array
    {
        return $this->submissionRequirementFileLinks(
            $submission,
            self::RENEWAL_FILE_KEYS,
            self::RENEWAL_FILE_LABELS,
            Attachment::TYPE_RENEWAL_REQUIREMENT,
            'organizations.submitted-documents.renewals.file'
        );
    }

    /**
     * Build the read-only "Submitted files" list. For each requirement we
     * either include a working file URL or a `missing => true` row so the
     * Blade view can render "No file uploaded" instead of a broken link.
     *
     * Only requirements that the submitter actually selected (is_submitted) or
     * that have a real attachment row are included — purely-unselected
     * requirements stay hidden from the list.
     *
     * @param  array<int, string>  $requirementKeys
     * @param  array<string, string>  $labelMap
     * @return list<array{label: string, url: ?string, missing?: bool}>
     */
    private function submissionRequirementFileLinks(
        OrganizationSubmission $submission,
        array $requirementKeys,
        array $labelMap,
        string $fileTypePrefix,
        string $routeName,
        array $revisionByKey = [],
        array $savedUpdatedKeys = [],
        array $recentlyReplacedKeys = [],
        array $pendingRequirementUpdateKeys = []
    ): array {
        $submittedKeys = SubmissionRequirement::query()
            ->where('submission_id', $submission->id)
            ->where('is_submitted', true)
            ->pluck('requirement_key')
            ->all();

        $links = [];
        foreach ($requirementKeys as $key) {
            $attachment = $submission->attachments()
                ->where('file_type', $fileTypePrefix.':'.$key)
                ->latest('id')
                ->first();

            $isSubmitted = in_array($key, $submittedKeys, true);
            $label = $labelMap[$key] ?? $key;
            $isPendingReviewAfterResubmit = in_array($key, $pendingRequirementUpdateKeys, true);
            $isRevised = isset($revisionByKey[$key]);
            $canReplace = $isRevised && ! $isPendingReviewAfterResubmit;

            if ($attachment) {
                $storedPath = (string) ($attachment->file_path ?? '');
                $previousFileName = trim((string) ($attachment->original_name ?? ''));
                if ($previousFileName === '' && $storedPath !== '') {
                    $previousFileName = basename($storedPath);
                }
                $links[] = [
                    'label' => $label,
                    'url' => route($routeName, [$submission, $key]),
                    'key' => $key,
                    'previous_file_name' => $previousFileName !== '' ? $previousFileName : 'No previous file',
                    'anchor_id' => $this->revisionTargetAnchorId('requirements', $key),
                    'is_revised' => $isRevised,
                    'revision_note' => $canReplace ? ($revisionByKey[$key] ?? null) : null,
                    'can_replace' => $canReplace,
                    'replace_url' => $submission->type === OrganizationSubmission::TYPE_REGISTRATION
                        ? route('organizations.submitted-documents.registrations.file.replace', ['submission' => $submission, 'key' => $key])
                        : null,
                    'is_changed_saved' => in_array($key, $savedUpdatedKeys, true),
                    'is_changed_recent' => in_array($key, $recentlyReplacedKeys, true),
                ];
            } elseif ($isSubmitted) {
                $links[] = [
                    'label' => $label,
                    'url' => null,
                    'missing' => true,
                    'key' => $key,
                    'previous_file_name' => 'No previous file',
                    'anchor_id' => $this->revisionTargetAnchorId('requirements', $key),
                    'is_revised' => $isRevised,
                    'revision_note' => $canReplace ? ($revisionByKey[$key] ?? null) : null,
                    'can_replace' => $canReplace,
                    'replace_url' => $submission->type === OrganizationSubmission::TYPE_REGISTRATION
                        ? route('organizations.submitted-documents.registrations.file.replace', ['submission' => $submission, 'key' => $key])
                        : null,
                    'is_changed_saved' => in_array($key, $savedUpdatedKeys, true),
                    'is_changed_recent' => in_array($key, $recentlyReplacedKeys, true),
                ];
            }
        }

        return $links;
    }

    /**
     * Shared streaming helper for registration / renewal requirement files.
     *
     * Resolves the attachment via two indexes:
     *   1. submission_requirements (submission_id, requirement_key) — used to
     *      detect the "uploaded but missing" / "not yet uploaded" cases so we
     *      can return a user-friendly 404 message instead of a bare error.
     *   2. attachments (attachable_type, attachable_id, file_type) — the
     *      authoritative pointer to the stored file.
     *
     * Inline disposition is used for browser-renderable types (PDF, images);
     * everything else (Word, Excel, etc.) is sent as an attachment so the user
     * gets a real download. The original uploaded filename is preserved in the
     * Content-Disposition so users see "by-laws.pdf" rather than the random
     * disk basename.
     */
    private function streamSubmissionRequirementAttachment(OrganizationSubmission $submission, string $requirementKey): Response
    {
        $requirement = SubmissionRequirement::query()
            ->where('submission_id', $submission->id)
            ->where('requirement_key', $requirementKey)
            ->first();

        // Attachments may be stored either with the bare requirement key
        // (e.g. "by_laws") or with the namespaced form
        // ("registration_requirement:by_laws" / "renewal_requirement:by_laws").
        // Match both shapes so legacy and current rows resolve correctly.
        $attachment = Attachment::query()
            ->where('attachable_type', OrganizationSubmission::class)
            ->where('attachable_id', $submission->id)
            ->where(function ($query) use ($requirementKey): void {
                $query->where('file_type', $requirementKey)
                    ->orWhere('file_type', 'like', '%:'.$requirementKey);
            })
            ->latest('id')
            ->first();

        if (! $attachment) {
            $message = $requirement && ! $requirement->is_submitted
                ? 'No file has been uploaded for this requirement yet.'
                : 'File not found for this requirement.';

            abort(404, $message);
        }

        $relativePath = (string) $attachment->stored_path;
        if ($relativePath === ''
            || str_contains($relativePath, '..')
            || str_starts_with($relativePath, '/')
        ) {
            Log::warning('Submission attachment has an invalid stored_path.', [
                'attachment_id' => $attachment->id,
                'submission_id' => $submission->id,
                'requirement_key' => $requirementKey,
                'stored_path' => $attachment->stored_path,
            ]);

            abort(404, 'File not found.');
        }

        $disk = Storage::disk('supabase');
        $exists = $disk->exists($relativePath);

        Log::info('Resolving submission requirement attachment from Supabase Storage.', [
            'attachment_id' => $attachment->id,
            'submission_id' => $submission->id,
            'requirement_key' => $requirementKey,
            'stored_path' => $relativePath,
            'exists_in_supabase_storage' => $exists,
        ]);

        if (! $exists) {
            Log::warning('File missing from Supabase Storage', [
                'attachment_id' => $attachment->id,
                'stored_path' => $relativePath,
            ]);

            abort(404, 'The file could not be found in Supabase Storage.');
        }

        $publicUrl = rtrim((string) env('SUPABASE_STORAGE_PUBLIC_URL'), '/')
            .'/'.trim((string) env('SUPABASE_STORAGE_BUCKET'), '/')
            .'/'.ltrim($relativePath, '/');

        return redirect()->away($publicUrl);
    }

    /**
     * Resolve which filesystem disk holds submission attachments.
     *
     * Defaults to the local `public` disk (where files are uploaded by the
     * organization controllers). Operators that move uploads to Supabase
     * Storage / S3 just have to set SUBMISSIONS_STORAGE_DISK in .env without
     * touching application code.
     */
    private static function submissionStorageDisk(): string
    {
        $disk = (string) config('filesystems.submissions_disk', env('SUBMISSIONS_STORAGE_DISK', 'public'));

        return $disk !== '' ? $disk : 'public';
    }

    private function resolveAttachmentMimeType(Attachment $attachment, $disk, string $relativePath): string
    {
        $mime = trim((string) ($attachment->mime_type ?? ''));
        if ($mime !== '') {
            return $mime;
        }

        try {
            $detected = method_exists($disk, 'mimeType') ? (string) $disk->mimeType($relativePath) : '';
            if ($detected !== '') {
                return $detected;
            }
        } catch (\Throwable $e) {
            // Fall through to extension-based guess.
        }

        return $this->mimeFromExtension($relativePath);
    }

    private function resolveAttachmentDownloadName(Attachment $attachment, string $relativePath): string
    {
        $original = trim((string) ($attachment->original_name ?? ''));
        if ($original !== '') {
            return $original;
        }

        return basename($relativePath);
    }

    private function isInlineRenderableMimeType(string $mimeType): bool
    {
        $mimeType = strtolower(trim($mimeType));
        if ($mimeType === '') {
            return false;
        }

        if (str_starts_with($mimeType, 'image/')) {
            return true;
        }

        return in_array($mimeType, [
            'application/pdf',
            'text/plain',
        ], true);
    }

    private function mimeFromExtension(string $path): string
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }

    /**
     * Gate-check submission file viewing using OrganizationSubmissionPolicy.
     *
     * Replaces the old officer-only `ensureOfficerOwnsOrganization` check for
     * the file-stream routes so admins, advisers, and officers (each within
     * their proper scope) can all reach the file. Falls back to a 403 for
     * unauthorized users instead of a misleading 404 / redirect.
     */
    private function authorizeSubmissionFileView(Request $request, OrganizationSubmission $submission): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'You must be logged in to view this file.');
        }

        try {
            Gate::forUser($user)->authorize('viewFile', $submission);
        } catch (AuthorizationException $e) {
            abort(403, 'You do not have permission to view this file.');
        }
    }

    /**
     * @return list<array{label: string, href: string, variant: string}>
     */
    private function activityCalendarWorkflowLinks(ActivityCalendar $calendar): array
    {
        $links = [];
        $status = strtoupper((string) ($calendar->status ?? ''));
        if ($status === 'REVISION') {
            $links[] = ['label' => 'Edit / resubmit activity calendar', 'href' => route('organizations.activity-calendar-submission'), 'variant' => 'primary'];
        }

        return $links;
    }

    /**
     * @return list<array{label: string, href: string, variant: string}>
     */
    private function activityProposalWorkflowLinks(Request $request, ActivityProposal $proposal): array
    {
        $links = [];
        if (strtoupper((string) ($proposal->status ?? '')) === 'REVISION') {
            $links[] = [
                'label' => 'Address revision (proposal form)',
                'href' => $this->withSuperAdminOrgQuery($request, route('organizations.activity-proposal-submission')),
                'variant' => 'primary',
            ];
        }
        $links[] = [
            'label' => 'Submit another proposal',
            'href' => $this->withSuperAdminOrgQuery($request, route('organizations.activity-proposal-request')),
            'variant' => 'secondary',
        ];

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
        $source = strtolower((string) $request->query('from', ''));
        if ($source === 'submitted-documents') {
            return [
                'route' => $this->submittedDocumentsListUrl($request),
                'label' => 'Back to Submitted Documents',
            ];
        }

        $suffix = $this->superAdminOrganizationQuerySuffix($request);

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

    private function withQueryParam(string $url, string $key, string $value): string
    {
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.urlencode($key).'='.urlencode($value);
    }

    private function submittedDocumentsListUrl(Request $request): string
    {
        return $this->withSuperAdminOrgQuery($request, route('organizations.submitted-documents'));
    }

    /**
     * @return array{route:string,label:string}
     */
    private function registrationDetailBackNavigation(Request $request): array
    {
        $source = strtolower((string) $request->query('from', ''));
        if ($source === 'dashboard') {
            return [
                'route' => route('organizations.index'),
                'label' => 'Back to Organization Dashboard',
            ];
        }

        return [
            'route' => $this->submittedDocumentsListUrl($request),
            'label' => 'Back to Submitted Documents',
        ];
    }

    private function buildSubmittedDocumentRows(Organization $organization, Request $request): Collection
    {
        $q = fn (string $url): string => $this->withSuperAdminOrgQuery($request, $url);
        $qDetail = fn (string $url): string => $this->withQueryParam($q($url), 'from', 'submitted-documents');
        $rows = collect();

        foreach (OrganizationSubmission::query()->registrations()->where('organization_id', $organization->id)->orderByDesc('updated_at')->get() as $submission) {
            $sp = $this->submissionStatusPresentation($submission->legacyStatus());
            $nFiles = $submission->attachments()->where('file_type', 'like', Attachment::TYPE_REGISTRATION_REQUIREMENT.':%')->count();
            $detail = $qDetail(route('organizations.submitted-documents.registrations.show', $submission));
            $rows->push([
                'type_key' => 'registration',
                'type_label' => 'Registration',
                'title' => 'Organization Registration Application',
                'submitted_display' => optional($submission->submission_date)->format('M j, Y') ?? '—',
                'updated_display' => optional($submission->updated_at)->format('M j, Y') ?? '—',
                'status_raw' => $submission->legacyStatus(),
                'status_label' => $sp['label'],
                'status_variant' => $this->variantKeyFromBadge($sp['badge_class']),
                'academic_year' => $submission->academicTerm?->academic_year,
                'academic_context' => $submission->academicTerm?->academic_year ? 'AY '.$submission->academicTerm?->academic_year : null,
                'remarks_preview' => $this->registrationOfficerRevisionNotesPreview($submission),
                'detail_href' => $detail,
                'has_files' => $nFiles > 0,
                'files_href' => $detail.'#submitted-files',
                'sort_timestamp' => $submission->updated_at?->getTimestamp() ?? 0,
                'row_actions' => $this->listRowActions(
                    $detail,
                    $nFiles > 0,
                    $detail.'#submitted-files',
                    [],
                ),
            ]);
        }

        foreach (OrganizationSubmission::query()->renewals()->where('organization_id', $organization->id)->orderByDesc('updated_at')->get() as $submission) {
            $sp = $this->submissionStatusPresentation($submission->legacyStatus());
            $nFiles = $submission->attachments()->where('file_type', 'like', Attachment::TYPE_RENEWAL_REQUIREMENT.':%')->count();
            $detail = $qDetail(route('organizations.submitted-documents.renewals.show', $submission));
            $rows->push([
                'type_key' => 'renewal',
                'type_label' => 'Renewal',
                'title' => 'Organization Renewal Application',
                'submitted_display' => optional($submission->submission_date)->format('M j, Y') ?? '—',
                'updated_display' => optional($submission->updated_at)->format('M j, Y') ?? '—',
                'status_raw' => $submission->legacyStatus(),
                'status_label' => $sp['label'],
                'status_variant' => $this->variantKeyFromBadge($sp['badge_class']),
                'academic_year' => $submission->academicTerm?->academic_year,
                'academic_context' => $submission->academicTerm?->academic_year ? 'AY '.$submission->academicTerm?->academic_year : null,
                'remarks_preview' => $this->revisionSectionsPreview($this->moduleRevisionSections(is_array($submission->renewal_field_reviews) ? $submission->renewal_field_reviews : []))
                    ?: $this->truncatePreview($submission->additional_remarks ?: $submission->notes, 160),
                'detail_href' => $detail,
                'has_files' => $nFiles > 0,
                'files_href' => $detail.'#submitted-files',
                'sort_timestamp' => $submission->updated_at?->getTimestamp() ?? 0,
                'row_actions' => $this->listRowActions(
                    $detail,
                    $nFiles > 0,
                    $detail.'#submitted-files',
                    [],
                ),
            ]);
        }

        foreach (ActivityCalendar::query()->where('organization_id', $organization->id)->orderByDesc('updated_at')->get() as $cal) {
            $sp = $this->submissionStatusPresentation($cal->status);
            $term = $this->activityCalendarTermLabel($cal->semester);
            $title = trim(($cal->academic_year ?? 'AY —').' · '.$term.' Activity Calendar');
            $hasFile = is_string($cal->calendar_file) && $cal->calendar_file !== '';
            // Keep this record under "Manage Organization -> Submitted Documents"
            // (do not jump back into the Activity Submission workflow module).
            $detail = $qDetail(route('organizations.submitted-documents.calendars.show', $cal));
            $rows->push([
                'type_key' => 'activity_calendar',
                'type_label' => 'Activity Calendar',
                'title' => $title,
                'submitted_display' => optional($cal->submission_date)->format('M j, Y') ?? '—',
                'updated_display' => optional($cal->updated_at)->format('M j, Y') ?? '—',
                'status_raw' => $cal->status,
                'status_label' => $sp['label'],
                'status_variant' => $this->variantKeyFromBadge($sp['badge_class']),
                'academic_year' => $cal->academic_year,
                'academic_context' => trim(collect([$cal->academic_year ? 'AY '.$cal->academic_year : null, $term !== '—' ? $term : null])->filter()->implode(' · ')) ?: null,
                'remarks_preview' => $this->revisionSectionsPreview($this->moduleRevisionSections(is_array($cal->admin_field_reviews) ? $cal->admin_field_reviews : [])),
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
            $sp = $this->submissionStatusPresentation($proposal->status, true);
            $nAttach = $proposal->attachments()
                ->whereIn('file_type', [
                    Attachment::TYPE_PROPOSAL_LOGO,
                    Attachment::TYPE_PROPOSAL_RESOURCE_RESUME,
                    Attachment::TYPE_PROPOSAL_EXTERNAL_FUNDING,
                ])
                ->count();
            if ($nAttach === 0) {
                $nAttach = ($proposal->organization_logo_path ? 1 : 0)
                    + ($proposal->resume_resource_persons_path ? 1 : 0)
                    + ($proposal->external_funding_support_path ? 1 : 0);
            }
            // Keep this record under "Manage Organization -> Submitted Documents"
            // so Back navigation remains in the Submitted Documents flow.
            $detail = $qDetail(route('organizations.submitted-documents.proposals.show', $proposal));
            $rows->push([
                'type_key' => 'activity_proposal',
                'type_label' => 'Activity Proposal',
                'title' => ($proposal->activity_title ?: 'Activity Proposal').' — Proposal',
                'submitted_display' => optional($proposal->submission_date)->format('M j, Y') ?? '—',
                'updated_display' => optional($proposal->updated_at)->format('M j, Y') ?? '—',
                'status_raw' => $proposal->status,
                'status_label' => $sp['label'],
                'status_variant' => $this->variantKeyFromBadge($sp['badge_class']),
                'academic_year' => $proposal->academic_year,
                'academic_context' => $proposal->academic_year ? 'AY '.$proposal->academic_year : null,
                'remarks_preview' => $this->revisionSectionsPreview($this->moduleRevisionSections(is_array($proposal->admin_field_reviews) ? $proposal->admin_field_reviews : []))
                    ?: $this->truncatePreview($proposal->overall_goal ?? $proposal->activity_description, 120),
                'detail_href' => $detail,
                'has_files' => $nAttach > 0,
                'files_href' => $detail.'#submitted-files',
                'sort_timestamp' => $proposal->updated_at?->getTimestamp() ?? 0,
                'row_actions' => $this->listRowActions(
                    $detail,
                    $nAttach > 0,
                    $detail.'#submitted-files',
                    [],
                ),
            ]);
        }

        foreach (ActivityReport::query()->where('organization_id', $organization->id)->orderByDesc('updated_at')->get() as $report) {
            $sp = $this->submissionStatusPresentation($report->status, false, 'report');
            $nFiles = $report->attachments()
                ->where(function ($q): void {
                    $q->whereIn('file_type', [
                        Attachment::TYPE_REPORT_POSTER,
                        Attachment::TYPE_REPORT_CERTIFICATE,
                        Attachment::TYPE_REPORT_EVALUATION_FORM,
                        Attachment::TYPE_REPORT_ATTENDANCE,
                    ])->orWhere('file_type', 'like', Attachment::TYPE_REPORT_SUPPORTING_PHOTO.':%');
                })
                ->count();
            if ($nFiles === 0) {
                $photoCount = is_array($report->supporting_photo_paths) ? count(array_filter($report->supporting_photo_paths, fn ($p) => is_string($p) && $p !== '')) : 0;
                $nFiles = ($report->poster_image_path ? 1 : 0) + $photoCount
                    + ($report->certificate_sample_path ? 1 : 0)
                    + ($report->evaluation_form_sample_path ? 1 : 0)
                    + ($report->attendance_sheet_path ? 1 : 0);
            }
            $eventTitle = $report->event_name ?? $report->activity_event_title ?? 'Event';
            $detail = $qDetail(route('organizations.submitted-documents.reports.show', $report));
            $rows->push([
                'type_key' => 'after_activity_report',
                'type_label' => 'After Activity Report',
                'title' => 'After Activity Report — '.$eventTitle,
                'submitted_display' => optional($report->report_submission_date)->format('M j, Y') ?? '—',
                'updated_display' => optional($report->updated_at)->format('M j, Y') ?? '—',
                'status_raw' => $report->status,
                'status_label' => $sp['label'],
                'status_variant' => $this->variantKeyFromBadge($sp['badge_class']),
                'academic_year' => null,
                'academic_context' => null,
                'remarks_preview' => $this->revisionSectionsPreview($this->moduleRevisionSections(is_array($report->admin_field_reviews) ? $report->admin_field_reviews : []))
                    ?: $this->truncatePreview($report->evaluation_report, 120),
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

    private function proposalFilePathByKey(ActivityProposal $proposal, ?ActivityRequestForm $requestForm, string $key): ?string
    {
        $fileType = match ($key) {
            'logo' => Attachment::TYPE_PROPOSAL_LOGO,
            'resume' => Attachment::TYPE_PROPOSAL_RESOURCE_RESUME,
            'external' => Attachment::TYPE_PROPOSAL_EXTERNAL_FUNDING,
            'request_letter' => Attachment::TYPE_REQUEST_LETTER,
            'speaker_resume' => Attachment::TYPE_REQUEST_SPEAKER_RESUME,
            'post_survey_form' => Attachment::TYPE_REQUEST_POST_SURVEY,
            default => null,
        };

        if (in_array($key, ['request_letter', 'speaker_resume', 'post_survey_form'], true) && $requestForm !== null && $fileType !== null) {
            $attachment = $requestForm->attachments()
                ->where('file_type', $fileType)
                ->latest('id')
                ->first();
            if ($attachment && is_string($attachment->stored_path) && $attachment->stored_path !== '') {
                return $attachment->stored_path;
            }
        }

        if (in_array($key, ['request_letter', 'speaker_resume', 'post_survey_form'], true) && $fileType !== null) {
            $requestFormFallback = ActivityRequestForm::query()
                ->where('organization_id', $proposal->organization_id)
                ->where('submitted_by', $proposal->submitted_by)
                ->whereNotNull('promoted_at')
                ->where(function ($q) use ($proposal): void {
                    $q->where('promoted_to_proposal_id', $proposal->id)
                        ->orWhere(function ($nested) use ($proposal): void {
                            if ($proposal->activity_calendar_entry_id !== null) {
                                $nested->where('activity_calendar_entry_id', $proposal->activity_calendar_entry_id);
                            } else {
                                $nested->where('activity_title', (string) ($proposal->activity_title ?? ''));
                            }
                        });
                })
                ->latest('promoted_at')
                ->latest('id')
                ->first();
            if ($requestFormFallback) {
                $attachment = $requestFormFallback->attachments()
                    ->where('file_type', $fileType)
                    ->latest('id')
                    ->first();
                if ($attachment && is_string($attachment->stored_path) && $attachment->stored_path !== '') {
                    return $attachment->stored_path;
                }
            }

            // Last-resort fallback: pick the latest request form by this officer/org
            // that actually has the requested file type attached.
            $latestWithRequestedAttachment = ActivityRequestForm::query()
                ->where('organization_id', $proposal->organization_id)
                ->where('submitted_by', $proposal->submitted_by)
                ->whereHas('attachments', fn ($q) => $q->where('file_type', $fileType))
                ->latest('updated_at')
                ->latest('id')
                ->first();
            if ($latestWithRequestedAttachment) {
                $attachment = $latestWithRequestedAttachment->attachments()
                    ->where('file_type', $fileType)
                    ->latest('id')
                    ->first();
                if ($attachment && is_string($attachment->stored_path) && $attachment->stored_path !== '') {
                    return $attachment->stored_path;
                }
            }
        }

        if ($fileType !== null && ! in_array($key, ['request_letter', 'speaker_resume', 'post_survey_form'], true)) {
            $attachment = $proposal->attachments()
                ->where('file_type', $fileType)
                ->latest('id')
                ->first();
            if ($attachment && is_string($attachment->stored_path) && $attachment->stored_path !== '') {
                return $attachment->stored_path;
            }
        }

        return match ($key) {
            'logo' => $proposal->organization_logo_path,
            'resume' => $proposal->resume_resource_persons_path,
            'external' => $proposal->external_funding_support_path,
            default => null,
        };
    }

    private function proposalResolvedFilePathByKey(ActivityProposal $proposal, ?ActivityRequestForm $requestForm, string $key): ?string
    {
        $relativePath = $this->normalizeStoredPublicPath($this->proposalFilePathByKey($proposal, $requestForm, $key));
        if (! $relativePath || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            return null;
        }

        return $relativePath;
    }

    private function normalizeStoredPublicPath(?string $rawPath): ?string
    {
        if (! is_string($rawPath)) {
            return null;
        }

        $path = trim($rawPath);
        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            if (is_string($parsedPath) && $parsedPath !== '') {
                $path = $parsedPath;
            }
        }

        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        } elseif (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }
        $path = ltrim($path, '/');

        return $path !== '' ? $path : null;
    }

    private function encodePathParam(string $path): string
    {
        return rtrim(strtr(base64_encode($path), '+/', '-_'), '=');
    }

    private function decodePathParam(string $encoded): ?string
    {
        $trimmed = trim($encoded);
        if ($trimmed === '') {
            return null;
        }

        $padded = str_pad(strtr($trimmed, '-_', '+/'), (int) ceil(strlen($trimmed) / 4) * 4, '=', STR_PAD_RIGHT);
        $decoded = base64_decode($padded, true);
        if (! is_string($decoded) || $decoded === '') {
            return null;
        }

        return $this->normalizeStoredPublicPath($decoded);
    }

    private function isPathAllowedForProposalOrganization(string $relativePath, int $organizationId): bool
    {
        $normalized = $this->normalizeStoredPublicPath($relativePath);
        if (! $normalized || str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
            return false;
        }

        return str_starts_with($normalized, 'activity-proposals/'.$organizationId.'/')
            || str_starts_with($normalized, 'activity-request-forms/'.$organizationId.'/');
    }

    /**
     * @return list<string>
     */
    private function reportSupportingPhotoKeys(ActivityReport $report): array
    {
        $attachmentKeys = $report->attachments()
            ->where('file_type', 'like', Attachment::TYPE_REPORT_SUPPORTING_PHOTO.':%')
            ->orderBy('file_type')
            ->get()
            ->map(function (Attachment $attachment): ?string {
                $suffix = str_replace(Attachment::TYPE_REPORT_SUPPORTING_PHOTO.':', '', (string) $attachment->file_type);
                return ctype_digit($suffix) ? 'supporting_'.$suffix : null;
            })
            ->filter()
            ->values()
            ->all();

        if ($attachmentKeys !== []) {
            return $attachmentKeys;
        }

        $legacyPhotos = is_array($report->supporting_photo_paths) ? $report->supporting_photo_paths : [];
        $keys = [];
        foreach ($legacyPhotos as $idx => $path) {
            if (is_string($path) && $path !== '') {
                $keys[] = 'supporting_'.$idx;
            }
        }

        return $keys;
    }

    private function reportFilePathByKey(ActivityReport $report, string $key): ?string
    {
        $fileType = match ($key) {
            'poster' => Attachment::TYPE_REPORT_POSTER,
            'certificate' => Attachment::TYPE_REPORT_CERTIFICATE,
            'evaluation_form' => Attachment::TYPE_REPORT_EVALUATION_FORM,
            'attendance' => Attachment::TYPE_REPORT_ATTENDANCE,
            default => null,
        };

        if (preg_match('/^supporting_(\d+)$/', $key, $m) === 1) {
            $attachment = $report->attachments()
                ->where('file_type', Attachment::TYPE_REPORT_SUPPORTING_PHOTO.':'.((int) $m[1]))
                ->latest('id')
                ->first();
            if ($attachment && is_string($attachment->stored_path) && $attachment->stored_path !== '') {
                return $attachment->stored_path;
            }

            $legacyPhotos = is_array($report->supporting_photo_paths) ? $report->supporting_photo_paths : [];
            return $legacyPhotos[(int) $m[1]] ?? null;
        }

        if ($fileType !== null) {
            $attachment = $report->attachments()
                ->where('file_type', $fileType)
                ->latest('id')
                ->first();
            if ($attachment && is_string($attachment->stored_path) && $attachment->stored_path !== '') {
                return $attachment->stored_path;
            }
        }

        return match ($key) {
            'poster' => $report->poster_image_path,
            'certificate' => $report->certificate_sample_path,
            'evaluation_form' => $report->evaluation_form_sample_path,
            'attendance' => $report->attendance_sheet_path,
            default => null,
        };
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

    private function proposalTimeRangeLabel(ActivityProposal $proposal): string
    {
        $start = $this->formatTimeValue($proposal->proposed_start_time);
        $end = $this->formatTimeValue($proposal->proposed_end_time);
        if ($start && $end) {
            return $start.' - '.$end;
        }

        return $start ?: '—';
    }

    private function formatTimeValue(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);
        $normalized = strlen($trimmed) >= 5 ? substr($trimmed, 0, 5) : $trimmed;
        $dt = \DateTime::createFromFormat('H:i', $normalized);

        return $dt ? $dt->format('g:i A') : $trimmed;
    }

    private function proposalSchoolLabel(ActivityProposal $proposal): string
    {
        $code = (string) ($proposal->school_code ?? $proposal->organization?->college_school ?? '');
        if ($code !== '' && array_key_exists($code, self::SCHOOL_LABELS)) {
            return self::SCHOOL_LABELS[$code];
        }

        return $code !== '' ? $code : '—';
    }

    private function relatedRequestFormForProposal(ActivityProposal $proposal): ?ActivityRequestForm
    {
        $query = ActivityRequestForm::query()
            ->where('organization_id', $proposal->organization_id)
            ->where('submitted_by', $proposal->submitted_by)
            ->whereNotNull('promoted_at');

        $linked = (clone $query)
            ->where('promoted_to_proposal_id', $proposal->id)
            ->latest('promoted_at')
            ->latest('id')
            ->first();
        if ($linked) {
            return $linked;
        }

        if ($proposal->activity_calendar_entry_id) {
            $hit = (clone $query)
                ->where('activity_calendar_entry_id', $proposal->activity_calendar_entry_id)
                ->latest('promoted_at')
                ->latest('id')
                ->first();
            if ($hit) {
                return $hit;
            }
        }

        return $query
            ->where('activity_title', (string) ($proposal->activity_title ?? ''))
            ->latest('promoted_at')
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<string, string>  $labelMap
     */
    private function requestFormOptionLabels(array $values, array $labelMap, ?string $otherText = null): string
    {
        if ($values === []) {
            return '—';
        }

        $labels = collect($values)
            ->map(function (string $key) use ($labelMap): string {
                return $labelMap[$key] ?? ucfirst(str_replace('_', ' ', $key));
            })
            ->values()
            ->all();

        if (in_array('Others', $labels, true) && is_string($otherText) && trim($otherText) !== '') {
            $labels = array_map(
                static fn (string $label): string => $label === 'Others' ? 'Others: '.trim($otherText) : $label,
                $labels
            );
        }

        return implode(', ', $labels);
    }

    private function revisionTargetAnchorId(string $sectionKey, string $fieldKey): string
    {
        return 'revision-file-'.$sectionKey.'-'.$fieldKey;
    }

    /**
     * @param  list<array<string, mixed>>  $revisionSections
     * @return array<string, string>
     */
    private function revisionNoteMapByRequirementKey(array $revisionSections): array
    {
        $map = [];
        foreach ($revisionSections as $section) {
            if (($section['section_key'] ?? '') !== 'requirements') {
                continue;
            }
            foreach ((array) ($section['items'] ?? []) as $item) {
                $key = (string) ($item['field_key'] ?? '');
                $note = trim((string) ($item['note'] ?? ''));
                if ($key !== '' && $note !== '') {
                    $map[$key] = $note;
                }
            }
        }

        return $map;
    }

    private function submissionAdviserNomination(OrganizationSubmission $submission): ?OrganizationAdviser
    {
        return OrganizationAdviser::query()
            ->with('user')
            ->where('organization_id', $submission->organization_id)
            ->where('submission_id', (int) $submission->id)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function attachmentMeta(Attachment $attachment): array
    {
        return [
            'original_name' => (string) ($attachment->original_name ?? ''),
            'stored_path' => (string) ($attachment->stored_path ?? ''),
            'mime_type' => (string) ($attachment->mime_type ?? ''),
            'file_size_kb' => (int) ($attachment->file_size_kb ?? 0),
        ];
    }

    private function applyRegistrationRequirementReplacement(
        OrganizationSubmission $submission,
        User $user,
        string $key,
        \Illuminate\Http\UploadedFile $upload
    ): void {
        $fileType = Attachment::TYPE_REGISTRATION_REQUIREMENT.':'.$key;
        $oldAttachment = $submission->attachments()
            ->where('file_type', $fileType)
            ->latest('id')
            ->first();
        $oldMeta = $oldAttachment ? $this->attachmentMeta($oldAttachment) : null;
        // Fail fast if the Supabase S3 disk is misconfigured, otherwise the
        // AWS SDK falls back to InstanceProfileProvider and the request hangs
        // until PHP's max_execution_time elapses.
        OrganizationController::assertSupabaseDiskIsConfigured();
        // Upload the replacement file to Supabase Storage. The bucket is set
        // on the `supabase` disk (SUPABASE_STORAGE_BUCKET), so the stored path
        // stays bucket-relative — e.g. "{organizationId}/registration/<rand>.pdf".
        $storedPath = $upload->store($submission->organization_id.'/registration', 'supabase');

        DB::transaction(function () use ($submission, $user, $key, $fileType, $upload, $storedPath, $oldMeta): void {
            Attachment::query()->create([
                'attachable_type' => OrganizationSubmission::class,
                'attachable_id' => (int) $submission->id,
                'uploaded_by' => (int) $user->id,
                'file_type' => $fileType,
                'original_name' => (string) $upload->getClientOriginalName(),
                'stored_path' => (string) $storedPath,
                'mime_type' => (string) ($upload->getClientMimeType() ?: ''),
                'file_size_kb' => (int) ceil(((int) $upload->getSize()) / 1024),
            ]);

            OrganizationRevisionFieldUpdate::query()->updateOrCreate(
                [
                    'organization_submission_id' => (int) $submission->id,
                    'section_key' => 'requirements',
                    'field_key' => $key,
                ],
                [
                    'old_file_meta' => $oldMeta,
                    'new_file_meta' => [
                        'original_name' => (string) $upload->getClientOriginalName(),
                        'stored_path' => (string) $storedPath,
                        'mime_type' => (string) ($upload->getClientMimeType() ?: ''),
                        'file_size_kb' => (int) ceil(((int) $upload->getSize()) / 1024),
                    ],
                    'resubmitted_at' => now(),
                    'resubmitted_by' => (int) $user->id,
                    'acknowledged_at' => null,
                    'acknowledged_by' => null,
                ]
            );

            $submission->touch();
        });
    }

    private function notifyRegistrationFileReplacementToAdmins(OrganizationSubmission $submission): void
    {
        $adminUsers = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'admin'))
            ->get();
        app(OrganizationNotificationService::class)->createForUsers(
            $adminUsers,
            'Registration File Replaced',
            'A revised registration requirement file was uploaded and is ready for review.',
            'info',
            route('admin.registrations.show', $submission),
            $submission
        );
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
