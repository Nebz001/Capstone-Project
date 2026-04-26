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
use App\Models\SubmissionRequirement;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
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
    private const RENEWAL_REQUIREMENT_FILE_KEYS = [
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
        $stored = is_array($submission->registration_section_reviews) ? $submission->registration_section_reviews : null;
        if (is_array($stored)) {
            $states = [];
            foreach (self::REGISTRATION_REVIEW_SECTION_KEYS as $sectionKey) {
                $raw = (string) data_get($stored, $sectionKey.'.status', 'pending');
                $states[$sectionKey] = match ($raw) {
                    'verified' => 'validated',
                    'needs_revision' => 'revision',
                    default => 'pending',
                };
            }

            return $states;
        }

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
            'persistedFieldReviews' => is_array($submission->registration_field_reviews) ? $submission->registration_field_reviews : [],
            'persistedSectionReviews' => is_array($submission->registration_section_reviews) ? $submission->registration_section_reviews : [],
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
            'field_review' => ['nullable', 'array'],
            'section_review' => ['nullable', 'array'],
            'section_submitted' => ['nullable', 'array'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $validated = $validator->validate();

        /** @var User $admin */
        $admin = $request->user();
        $fieldReviewInput = is_array($validated['field_review'] ?? null) ? $validated['field_review'] : [];
        $sectionSubmittedInput = is_array($validated['section_submitted'] ?? null) ? $validated['section_submitted'] : [];
        $sectionSchema = $this->registrationReviewFieldSchema();

        $normalizedFieldReviews = [];
        $normalizedSectionReviews = [];
        foreach ($sectionSchema as $sectionKey => $fields) {
            $sectionInput = is_array($fieldReviewInput[$sectionKey] ?? null) ? $fieldReviewInput[$sectionKey] : [];
            $fieldStates = [];
            foreach ($fields as $fieldKey => $fieldLabel) {
                $row = is_array($sectionInput[$fieldKey] ?? null) ? $sectionInput[$fieldKey] : [];
                $status = (string) ($row['status'] ?? 'pending');
                if (! in_array($status, ['pending', 'passed', 'flagged'], true)) {
                    $status = 'pending';
                }
                $note = trim((string) ($row['note'] ?? ''));
                if ($status !== 'flagged') {
                    $note = '';
                }
                $fieldStates[$fieldKey] = [
                    'label' => $fieldLabel,
                    'status' => $status,
                    'note' => $note !== '' ? $note : null,
                    'reviewed_by' => $status === 'pending' ? null : $admin->id,
                    'reviewed_at' => $status === 'pending' ? null : now()->toDateTimeString(),
                ];
            }

            $isSubmitted = (string) ($sectionSubmittedInput[$sectionKey] ?? '0') === '1';
            $hasPending = collect($fieldStates)->contains(fn (array $row): bool => ($row['status'] ?? 'pending') === 'pending');
            $hasFlagged = collect($fieldStates)->contains(fn (array $row): bool => ($row['status'] ?? 'pending') === 'flagged');
            $missingFlaggedNotes = collect($fieldStates)->contains(
                fn (array $row): bool => ($row['status'] ?? '') === 'flagged' && trim((string) ($row['note'] ?? '')) === ''
            );

            if ($isSubmitted && ($hasPending || $missingFlaggedNotes)) {
                return back()->withErrors([
                    'section_review' => 'All fields must be reviewed and every flagged field needs a note before submitting a section.',
                ])->withInput();
            }

            $normalizedFieldReviews[$sectionKey] = $fieldStates;
            $normalizedSectionReviews[$sectionKey] = [
                'status' => ! $isSubmitted ? 'pending' : ($hasFlagged ? 'needs_revision' : 'verified'),
                'submitted' => $isSubmitted,
                'reviewed_by' => $isSubmitted ? $admin->id : null,
                'reviewed_at' => $isSubmitted ? now()->toDateTimeString() : null,
            ];
        }

        $allSectionsSubmitted = collect($normalizedSectionReviews)->every(fn (array $row): bool => (bool) ($row['submitted'] ?? false));
        $allSectionsVerified = collect($normalizedSectionReviews)->every(fn (array $row): bool => ($row['status'] ?? 'pending') === 'verified');
        $hasNeedsRevision = collect($normalizedSectionReviews)->contains(fn (array $row): bool => ($row['status'] ?? 'pending') === 'needs_revision');

        $remarks = trim((string) ($validated['remarks'] ?? ''));
        if (! $allSectionsSubmitted || (! $allSectionsVerified && ! $hasNeedsRevision)) {
            return back()->withErrors([
                'section_review' => 'Review all fields and provide notes for every revision-marked field before finalizing.',
            ])->withInput();
        }

        $flaggedNotes = $this->composeRegistrationFlaggedFieldNotes($normalizedFieldReviews);
        if ($hasNeedsRevision && $flaggedNotes === null) {
            return back()->withErrors([
                'section_review' => 'Flagged fields must include notes before submitting for revision.',
            ])->withInput();
        }

        DB::transaction(function () use ($submission, $remarks, $admin, $normalizedFieldReviews, $normalizedSectionReviews, $allSectionsVerified, $hasNeedsRevision, $flaggedNotes): void {
            $nextStatus = match (true) {
                $allSectionsVerified => OrganizationSubmission::STATUS_APPROVED,
                $hasNeedsRevision => OrganizationSubmission::STATUS_REVISION,
                default => OrganizationSubmission::STATUS_PENDING,
            };
            $effectiveRemarks = $remarks;
            $effectiveRevisionNotes = $nextStatus === OrganizationSubmission::STATUS_REVISION
                ? implode("\n\n", array_filter([
                    $flaggedNotes,
                    $effectiveRemarks !== '' ? "General remarks\n".$effectiveRemarks : null,
                ]))
                : '';
            $submission->update([
                'status' => $nextStatus,
                'approval_decision' => $nextStatus === OrganizationSubmission::STATUS_APPROVED ? 'approved' : null,
                'additional_remarks' => $effectiveRemarks !== '' ? $effectiveRemarks : null,
                'notes' => $nextStatus === OrganizationSubmission::STATUS_REVISION
                    ? ($effectiveRevisionNotes !== '' ? $effectiveRevisionNotes : $submission->notes)
                    : ($effectiveRemarks !== '' ? $effectiveRemarks : $submission->notes),
                'current_approval_step' => 0,
                'registration_field_reviews' => $normalizedFieldReviews,
                'registration_section_reviews' => $normalizedSectionReviews,
            ]);

            if ($nextStatus === OrganizationSubmission::STATUS_APPROVED) {
                $submission->organization?->update(['status' => 'active']);
            }

            if ($nextStatus === OrganizationSubmission::STATUS_REVISION && $effectiveRevisionNotes !== '') {
                OrganizationProfileRevision::query()->create([
                    'organization_id' => $submission->organization_id,
                    'requested_by' => $admin->id,
                    'revision_notes' => $effectiveRevisionNotes,
                    'status' => 'open',
                ]);
            }
        });

        return redirect()
            ->route('admin.registrations.show', $submission)
            ->with('success', 'Registration review saved successfully.')
            ->with('success_html', 'Registration review saved successfully. <a href="'.route('admin.registrations.index').'" class="ml-1 inline-flex items-center font-semibold underline decoration-emerald-700/50 underline-offset-2 hover:decoration-emerald-900">Back to Registrations</a>');
    }

    public function saveRegistrationReviewDraft(Request $request, OrganizationSubmission $submission): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_REGISTRATION, 404);

        $validated = $request->validate([
            'field_review' => ['nullable', 'array'],
        ]);

        /** @var User $admin */
        $admin = $request->user();
        $fieldReviewInput = is_array($validated['field_review'] ?? null) ? $validated['field_review'] : [];
        $sectionSchema = $this->registrationReviewFieldSchema();

        $normalizedFieldReviews = [];
        $normalizedSectionReviews = [];
        foreach ($sectionSchema as $sectionKey => $fields) {
            $sectionInput = is_array($fieldReviewInput[$sectionKey] ?? null) ? $fieldReviewInput[$sectionKey] : [];
            $fieldStates = [];
            foreach ($fields as $fieldKey => $fieldLabel) {
                $row = is_array($sectionInput[$fieldKey] ?? null) ? $sectionInput[$fieldKey] : [];
                $status = (string) ($row['status'] ?? 'pending');
                if (in_array($status, ['revision', 'needs_revision'], true)) {
                    $status = 'flagged';
                }
                if (! in_array($status, ['pending', 'passed', 'flagged'], true)) {
                    $status = 'pending';
                }
                $note = trim((string) ($row['note'] ?? ''));
                if ($status !== 'flagged') {
                    $note = '';
                }
                $fieldStates[$fieldKey] = [
                    'label' => $fieldLabel,
                    'status' => $status,
                    'note' => $note !== '' ? $note : null,
                    'reviewed_by' => $status === 'pending' ? null : $admin->id,
                    'reviewed_at' => $status === 'pending' ? null : now()->toDateTimeString(),
                ];
            }

            $hasPending = collect($fieldStates)->contains(fn (array $row): bool => ($row['status'] ?? 'pending') === 'pending');
            $hasFlagged = collect($fieldStates)->contains(fn (array $row): bool => ($row['status'] ?? 'pending') === 'flagged');
            $missingFlaggedNotes = collect($fieldStates)->contains(
                fn (array $row): bool => ($row['status'] ?? '') === 'flagged' && trim((string) ($row['note'] ?? '')) === ''
            );
            $isSubmitted = ! $hasPending && ! $missingFlaggedNotes;

            $normalizedFieldReviews[$sectionKey] = $fieldStates;
            $normalizedSectionReviews[$sectionKey] = [
                'status' => ! $isSubmitted ? 'pending' : ($hasFlagged ? 'needs_revision' : 'verified'),
                'submitted' => $isSubmitted,
                'reviewed_by' => $isSubmitted ? $admin->id : null,
                'reviewed_at' => $isSubmitted ? now()->toDateTimeString() : null,
            ];
        }

        $submission->update([
            'registration_field_reviews' => $normalizedFieldReviews,
            'registration_section_reviews' => $normalizedSectionReviews,
        ]);

        return response()->json([
            'ok' => true,
            'field_reviews' => $normalizedFieldReviews,
            'section_reviews' => $normalizedSectionReviews,
        ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function registrationReviewFieldSchema(): array
    {
        $requirementLabels = [
            'letter_of_intent' => 'Letter of Intent',
            'application_form' => 'Application Form',
            'by_laws' => 'By Laws of the Organization',
            'updated_list_of_officers_founders' => 'Updated List of Officers/Founders',
            'dean_endorsement_faculty_adviser' => 'Letter from the School Dean endorsing the Faculty Adviser',
            'proposed_projects_budget' => 'List of Proposed Projects with Proposed Budget for the AY',
            'others' => 'Others',
        ];
        $requirementFields = [];
        foreach (SubmissionRequirement::requirementKeysForType(OrganizationSubmission::TYPE_REGISTRATION) as $key) {
            $requirementFields[$key] = $requirementLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
        }

        return [
            'application' => [
                'academic_year' => 'Academic Year',
                'submission_date' => 'Submission Date',
                'submitted_by' => 'Submitted By',
                'organization' => 'Organization',
            ],
            'contact' => [
                'organization_name' => 'Organization Name',
                'contact_person' => 'Contact Person',
                'contact_no' => 'Contact Number',
                'contact_email' => 'Email Address',
            ],
            'organizational' => [
                'date_organized' => 'Date Organized',
                'organization_type' => 'Type of Organization',
                'school' => 'School',
                'purpose' => 'Purpose of Organization',
            ],
            'requirements' => $requirementFields,
        ];
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $fieldReviews
     */
    private function composeRegistrationFlaggedFieldNotes(array $fieldReviews): ?string
    {
        $sectionTitles = [
            'application' => 'Application Information',
            'contact' => 'Account and Contact Information',
            'organizational' => 'Organization Details',
            'requirements' => 'Requirements Attached',
        ];

        $blocks = [];
        foreach ($fieldReviews as $sectionKey => $fields) {
            $items = [];
            foreach ($fields as $field) {
                if (($field['status'] ?? '') !== 'flagged') {
                    continue;
                }
                $label = (string) ($field['label'] ?? 'Field');
                $note = trim((string) ($field['note'] ?? ''));
                if ($note !== '') {
                    $items[] = "- {$label}: {$note}";
                }
            }
            if ($items !== []) {
                $title = $sectionTitles[$sectionKey] ?? ucwords(str_replace('_', ' ', $sectionKey));
                $blocks[] = $title."\n".implode("\n", $items);
            }
        }

        return $blocks === [] ? null : implode("\n\n—\n\n", $blocks);
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

        $submission->load(['organization', 'submittedBy', 'academicTerm', 'attachments']);
        $sections = $this->renewalReviewSections($submission);

        return view('admin.reviews.module-show', [
            'pageTitle' => 'Renewal Submission Details',
            'moduleLabel' => 'Renewal',
            'status' => $submission->legacyStatus(),
            'sections' => $sections,
            'persistedFieldReviews' => is_array($submission->renewal_field_reviews) ? $submission->renewal_field_reviews : [],
            'persistedSectionReviews' => is_array($submission->renewal_section_reviews) ? $submission->renewal_section_reviews : [],
            'persistedRemarks' => $submission->additional_remarks ?? '',
            'saveRoute' => route('admin.renewals.update-status', $submission),
            'draftRoute' => route('admin.renewals.review-draft', $submission),
            'backRoute' => route('admin.renewals.index'),
            'backLabel' => 'Back to Renewals',
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

        return view('admin.reviews.module-show', [
            'pageTitle' => 'Activity Calendar Submission Details',
            'moduleLabel' => 'Activity Calendar',
            'status' => strtoupper((string) ($calendar->status ?? 'pending')),
            'sections' => $this->calendarReviewSections($calendar, $termLabel),
            'persistedFieldReviews' => is_array($calendar->admin_field_reviews) ? $calendar->admin_field_reviews : [],
            'persistedSectionReviews' => is_array($calendar->admin_section_reviews) ? $calendar->admin_section_reviews : [],
            'persistedRemarks' => $calendar->admin_review_remarks ?? '',
            'saveRoute' => route('admin.calendars.update-status', $calendar),
            'draftRoute' => route('admin.calendars.review-draft', $calendar),
            'backRoute' => route('admin.calendars.index'),
            'backLabel' => 'Back to Activity Calendars',
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

        return view('admin.reviews.module-show', [
            'pageTitle' => 'Activity Proposal Submission Details',
            'moduleLabel' => 'Activity Proposal',
            'status' => strtoupper((string) ($proposal->status ?? 'pending')),
            'sections' => $this->proposalReviewSections($proposal, $details),
            'persistedFieldReviews' => is_array($proposal->admin_field_reviews) ? $proposal->admin_field_reviews : [],
            'persistedSectionReviews' => is_array($proposal->admin_section_reviews) ? $proposal->admin_section_reviews : [],
            'persistedRemarks' => $proposal->admin_review_remarks ?? '',
            'saveRoute' => route('admin.proposals.update-status', $proposal),
            'draftRoute' => route('admin.proposals.review-draft', $proposal),
            'backRoute' => route('admin.proposals.index'),
            'backLabel' => 'Back to Activity Proposals',
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

        return view('admin.reviews.module-show', [
            'pageTitle' => 'After Activity Report Submission Details',
            'moduleLabel' => 'After Activity Report',
            'status' => strtoupper((string) ($report->status ?? 'pending')),
            'sections' => $this->reportReviewSections($report, $details),
            'persistedFieldReviews' => is_array($report->admin_field_reviews) ? $report->admin_field_reviews : [],
            'persistedSectionReviews' => is_array($report->admin_section_reviews) ? $report->admin_section_reviews : [],
            'persistedRemarks' => $report->admin_review_remarks ?? '',
            'saveRoute' => route('admin.reports.update-status', $report),
            'draftRoute' => route('admin.reports.review-draft', $report),
            'backRoute' => route('admin.reports.index'),
            'backLabel' => 'Back to After Activity Reports',
        ]);
    }

    public function showRenewalRequirementFile(Request $request, OrganizationSubmission $submission, string $key): StreamedResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_RENEWAL, 404);
        if (! in_array($key, self::RENEWAL_REQUIREMENT_FILE_KEYS, true)) {
            abort(404);
        }
        $attachment = $submission->attachments()
            ->where('file_type', Attachment::TYPE_RENEWAL_REQUIREMENT.':'.$key)
            ->latest('id')
            ->first();
        if (! $attachment) {
            abort(404);
        }
        return Storage::disk('public')->response((string) $attachment->stored_path, basename((string) $attachment->stored_path), [], 'inline');
    }

    public function saveRenewalReviewDraft(Request $request, OrganizationSubmission $submission): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_RENEWAL, 404);
        return $this->saveModuleDraft($request, $submission, $this->renewalReviewSchema(), 'renewal_field_reviews', 'renewal_section_reviews');
    }

    public function updateRenewalStatus(Request $request, OrganizationSubmission $submission): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($submission->type === OrganizationSubmission::TYPE_RENEWAL, 404);
        return $this->finalizeModuleReview(
            $request,
            $submission,
            $this->renewalReviewSchema(),
            'renewal_field_reviews',
            'renewal_section_reviews',
            'additional_remarks',
            route('admin.renewals.show', $submission),
            route('admin.renewals.index'),
            'Back to Renewals'
        );
    }

    public function saveCalendarReviewDraft(Request $request, ActivityCalendar $calendar): JsonResponse
    {
        $this->authorizeAdmin($request);
        return $this->saveModuleDraft($request, $calendar, $this->calendarReviewSchema($calendar), 'admin_field_reviews', 'admin_section_reviews');
    }

    public function updateCalendarStatus(Request $request, ActivityCalendar $calendar): RedirectResponse
    {
        $this->authorizeAdmin($request);
        return $this->finalizeModuleReview(
            $request,
            $calendar,
            $this->calendarReviewSchema($calendar),
            'admin_field_reviews',
            'admin_section_reviews',
            'admin_review_remarks',
            route('admin.calendars.show', $calendar),
            route('admin.calendars.index'),
            'Back to Activity Calendars'
        );
    }

    public function saveProposalReviewDraft(Request $request, ActivityProposal $proposal): JsonResponse
    {
        $this->authorizeAdmin($request);
        return $this->saveModuleDraft($request, $proposal, $this->proposalReviewSchema(), 'admin_field_reviews', 'admin_section_reviews');
    }

    public function updateProposalStatus(Request $request, ActivityProposal $proposal): RedirectResponse
    {
        $this->authorizeAdmin($request);
        return $this->finalizeModuleReview(
            $request,
            $proposal,
            $this->proposalReviewSchema(),
            'admin_field_reviews',
            'admin_section_reviews',
            'admin_review_remarks',
            route('admin.proposals.show', $proposal),
            route('admin.proposals.index'),
            'Back to Activity Proposals'
        );
    }

    public function saveReportReviewDraft(Request $request, ActivityReport $report): JsonResponse
    {
        $this->authorizeAdmin($request);
        return $this->saveModuleDraft($request, $report, $this->reportReviewSchema(), 'admin_field_reviews', 'admin_section_reviews');
    }

    public function updateReportStatus(Request $request, ActivityReport $report): RedirectResponse
    {
        $this->authorizeAdmin($request);
        return $this->finalizeModuleReview(
            $request,
            $report,
            $this->reportReviewSchema(),
            'admin_field_reviews',
            'admin_section_reviews',
            'admin_review_remarks',
            route('admin.reports.show', $report),
            route('admin.reports.index'),
            'Back to After Activity Reports'
        );
    }

    private function saveModuleDraft(Request $request, Model $record, array $schema, string $fieldColumn, string $sectionColumn): JsonResponse
    {
        $validated = $request->validate(['field_review' => ['nullable', 'array']]);
        /** @var User $admin */
        $admin = $request->user();
        [$fieldReviews, $sectionReviews] = $this->normalizeModuleFieldReviews(
            is_array($validated['field_review'] ?? null) ? $validated['field_review'] : [],
            $schema,
            $admin
        );
        $record->update([$fieldColumn => $fieldReviews, $sectionColumn => $sectionReviews]);

        return response()->json(['ok' => true, 'field_reviews' => $fieldReviews, 'section_reviews' => $sectionReviews]);
    }

    private function finalizeModuleReview(Request $request, Model $record, array $schema, string $fieldColumn, string $sectionColumn, string $remarksColumn, string $showRoute, string $backRoute, string $backLabel): RedirectResponse
    {
        $validated = $request->validate([
            'field_review' => ['nullable', 'array'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var User $admin */
        $admin = $request->user();
        [$fieldReviews, $sectionReviews, $hasPending, $hasMissingNotes] = $this->normalizeModuleFieldReviews(
            is_array($validated['field_review'] ?? null) ? $validated['field_review'] : [],
            $schema,
            $admin
        );
        if ($hasPending || $hasMissingNotes) {
            return back()->withErrors(['field_review' => 'Review all fields and provide notes for every revision-marked field before finalizing.'])->withInput();
        }
        $allVerified = collect($sectionReviews)->every(fn (array $row): bool => ($row['status'] ?? 'pending') === 'verified');
        $nextStatus = $allVerified ? 'approved' : 'revision';
        $remarks = trim((string) ($validated['remarks'] ?? ''));
        $notes = $this->composeGenericFlaggedNotes($fieldReviews);
        $payload = [
            'status' => $nextStatus,
            $fieldColumn => $fieldReviews,
            $sectionColumn => $sectionReviews,
            $remarksColumn => $remarks !== '' ? $remarks : null,
        ];
        if ($record instanceof OrganizationSubmission) {
            $payload['notes'] = $nextStatus === 'revision'
                ? ($notes ?? ($remarks !== '' ? $remarks : $record->notes))
                : ($remarks !== '' ? $remarks : $record->notes);
            $payload['approval_decision'] = $nextStatus === 'approved' ? 'approved' : null;
        }
        $record->update($payload);

        return redirect()
            ->to($showRoute)
            ->with('success', 'Review saved successfully.')
            ->with('success_html', 'Review saved successfully. <a href="'.$backRoute.'" class="ml-1 inline-flex items-center font-semibold underline decoration-emerald-700/50 underline-offset-2 hover:decoration-emerald-900">'.$backLabel.'</a>');
    }

    private function normalizeModuleFieldReviews(array $fieldReviewInput, array $schema, User $admin): array
    {
        $normalizedFieldReviews = [];
        $normalizedSectionReviews = [];
        $hasPending = false;
        $hasMissingNotes = false;
        foreach ($schema as $sectionKey => $fields) {
            $sectionInput = is_array($fieldReviewInput[$sectionKey] ?? null) ? $fieldReviewInput[$sectionKey] : [];
            $fieldStates = [];
            $sectionPending = false;
            $sectionFlagged = false;
            foreach ($fields as $fieldKey => $fieldLabel) {
                $row = is_array($sectionInput[$fieldKey] ?? null) ? $sectionInput[$fieldKey] : [];
                $status = (string) ($row['status'] ?? 'pending');
                if (in_array($status, ['revision', 'needs_revision'], true)) {
                    $status = 'flagged';
                }
                if (! in_array($status, ['pending', 'passed', 'flagged'], true)) {
                    $status = 'pending';
                }
                $note = trim((string) ($row['note'] ?? ''));
                if ($status !== 'flagged') {
                    $note = '';
                }
                if ($status === 'pending') {
                    $sectionPending = true;
                }
                if ($status === 'flagged') {
                    $sectionFlagged = true;
                    if ($note === '') {
                        $hasMissingNotes = true;
                    }
                }
                $fieldStates[$fieldKey] = [
                    'label' => $fieldLabel,
                    'status' => $status,
                    'note' => $note !== '' ? $note : null,
                    'reviewed_by' => $status === 'pending' ? null : $admin->id,
                    'reviewed_at' => $status === 'pending' ? null : now()->toDateTimeString(),
                ];
            }
            if ($sectionPending) {
                $hasPending = true;
            }
            $normalizedFieldReviews[$sectionKey] = $fieldStates;
            $normalizedSectionReviews[$sectionKey] = [
                'status' => $sectionPending ? 'pending' : ($sectionFlagged ? 'needs_revision' : 'verified'),
                'submitted' => ! $sectionPending && ! collect($fieldStates)->contains(fn (array $row): bool => ($row['status'] ?? '') === 'flagged' && trim((string) ($row['note'] ?? '')) === ''),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->toDateTimeString(),
            ];
        }
        return [$normalizedFieldReviews, $normalizedSectionReviews, $hasPending, $hasMissingNotes];
    }

    private function composeGenericFlaggedNotes(array $fieldReviews): ?string
    {
        $blocks = [];
        foreach ($fieldReviews as $sectionKey => $fields) {
            $items = [];
            foreach ($fields as $field) {
                if (($field['status'] ?? '') !== 'flagged') {
                    continue;
                }
                $note = trim((string) ($field['note'] ?? ''));
                if ($note !== '') {
                    $items[] = '- '.((string) ($field['label'] ?? 'Field')).': '.$note;
                }
            }
            if ($items !== []) {
                $blocks[] = ucwords(str_replace('_', ' ', (string) $sectionKey))."\n".implode("\n", $items);
            }
        }
        return $blocks === [] ? null : implode("\n\n—\n\n", $blocks);
    }

    private function renewalReviewSchema(): array
    {
        return [
            'overview' => [
                'organization' => 'Organization',
                'submitted_by' => 'Submitted By',
                'academic_year' => 'Academic Year',
                'submission_date' => 'Submission Date',
            ],
            'contact' => [
                'contact_person' => 'Contact Person',
                'contact_no' => 'Contact Number',
                'contact_email' => 'Contact Email',
            ],
            'requirements' => [
                'letter_of_intent' => 'Letter of Intent',
                'application_form' => 'Application Form',
                'by_laws_updated_if_applicable' => 'By-Laws Updated (if applicable)',
                'updated_list_of_officers_founders_ay' => 'Updated Officers/Founders (AY)',
                'dean_endorsement_faculty_adviser' => 'Dean Endorsement',
                'proposed_projects_budget' => 'Proposed Projects and Budget',
                'past_projects' => 'Past Projects',
                'financial_statement_previous_ay' => 'Financial Statement (Previous AY)',
                'evaluation_summary_past_projects' => 'Evaluation Summary (Past Projects)',
                'others' => 'Others',
            ],
        ];
    }

    private function renewalReviewSections(OrganizationSubmission $submission): array
    {
        $sections = [
            ['key' => 'overview', 'title' => 'Submission Overview', 'subtitle' => 'Renewal context and submitter details.', 'fields' => []],
            ['key' => 'contact', 'title' => 'Contact Details', 'subtitle' => 'Primary point-of-contact for this renewal.', 'fields' => []],
            ['key' => 'requirements', 'title' => 'Requirements Attached', 'subtitle' => 'Uploaded requirement files for renewal.', 'fields' => []],
        ];
        $sections[0]['fields'] = [
            ['key' => 'organization', 'label' => 'Organization', 'value' => $submission->organization?->organization_name ?? 'N/A'],
            ['key' => 'submitted_by', 'label' => 'Submitted By', 'value' => $submission->submittedBy?->full_name ?? 'N/A'],
            ['key' => 'academic_year', 'label' => 'Academic Year', 'value' => $submission->academicTerm?->academic_year ?? 'N/A'],
            ['key' => 'submission_date', 'label' => 'Submission Date', 'value' => optional($submission->submission_date)->format('M d, Y') ?? 'N/A'],
        ];
        $sections[1]['fields'] = [
            ['key' => 'contact_person', 'label' => 'Contact Person', 'value' => $submission->contact_person ?? 'N/A'],
            ['key' => 'contact_no', 'label' => 'Contact Number', 'value' => $submission->contact_no ?? 'N/A'],
            ['key' => 'contact_email', 'label' => 'Contact Email', 'value' => $submission->contact_email ?? 'N/A', 'wide' => true],
        ];
        foreach (self::RENEWAL_REQUIREMENT_FILE_KEYS as $key) {
            $attachment = $submission->attachments()->where('file_type', Attachment::TYPE_RENEWAL_REQUIREMENT.':'.$key)->latest('id')->first();
            $sections[2]['fields'][] = [
                'key' => $key,
                'label' => ucwords(str_replace('_', ' ', $key)),
                'value' => $attachment ? 'File uploaded' : 'Not uploaded',
                'action' => $attachment ? ['href' => route('admin.renewals.requirement-file', ['submission' => $submission, 'key' => $key])] : null,
                'wide' => true,
            ];
        }
        return $sections;
    }

    private function calendarReviewSchema(ActivityCalendar $calendar): array
    {
        $schema = [
            'overview' => [
                'organization_profile' => 'Organization (profile)',
                'organization_form' => 'RSO Name (form)',
                'academic_year' => 'Academic Year',
                'term' => 'Term',
                'submission_date' => 'Submission Date',
            ],
            'entries' => [],
        ];
        foreach ($calendar->entries as $entry) {
            $schema['entries']['entry_'.$entry->id] = 'Activity: '.($entry->activity_name ?? 'Untitled');
        }
        return $schema;
    }

    private function calendarReviewSections(ActivityCalendar $calendar, string $termLabel): array
    {
        $sections = [
            ['key' => 'overview', 'title' => 'Calendar Overview', 'subtitle' => 'Core activity calendar submission details.', 'fields' => [
                ['key' => 'organization_profile', 'label' => 'Organization (profile)', 'value' => $calendar->organization?->organization_name ?? 'N/A'],
                ['key' => 'organization_form', 'label' => 'RSO Name (form)', 'value' => $calendar->submitted_organization_name ?? 'N/A'],
                ['key' => 'academic_year', 'label' => 'Academic Year', 'value' => $calendar->academic_year ?? 'N/A'],
                ['key' => 'term', 'label' => 'Term', 'value' => $termLabel],
                ['key' => 'submission_date', 'label' => 'Submission Date', 'value' => optional($calendar->submission_date)->format('M d, Y') ?? 'N/A'],
            ]],
            ['key' => 'entries', 'title' => 'Submitted Activities', 'subtitle' => 'Each listed activity should be reviewed.', 'fields' => []],
        ];
        foreach ($calendar->entries as $entry) {
            $sections[1]['fields'][] = [
                'key' => 'entry_'.$entry->id,
                'label' => 'Activity: '.($entry->activity_name ?? 'Untitled'),
                'value' => trim((optional($entry->activity_date)->format('M d, Y') ?? 'N/A').' · '.($entry->venue ?? 'No venue')),
                'wide' => true,
            ];
        }
        return $sections;
    }

    private function proposalReviewSchema(): array
    {
        return [
            'overview' => [
                'organization' => 'Organization (profile)',
                'submitted_by' => 'Submitted By',
                'submission_date' => 'Submission Date',
                'academic_year' => 'Academic Year',
            ],
            'activity' => [
                'activity_title' => 'Activity Title',
                'proposed_start' => 'Proposed Start',
                'proposed_end' => 'Proposed End',
                'proposed_time' => 'Proposed Time',
                'venue' => 'Venue',
                'overall_goal' => 'Overall Goal',
                'specific_objectives' => 'Specific Objectives',
                'criteria_mechanics' => 'Criteria / Mechanics',
                'program_flow' => 'Program Flow',
            ],
            'budget_files' => [
                'proposed_budget_total' => 'Proposed Budget (total)',
                'source_of_funding' => 'Source of Funding',
                'organization_logo' => 'Organization Logo',
                'resource_resume' => 'Resume (resource persons)',
                'external_funding' => 'External Funding Support',
            ],
        ];
    }

    private function proposalReviewSections(ActivityProposal $proposal, array $details): array
    {
        $logoPath = $this->attachmentPathOrLegacy($proposal, Attachment::TYPE_PROPOSAL_LOGO, $proposal->organization_logo_path);
        $resumePath = $this->attachmentPathOrLegacy($proposal, Attachment::TYPE_PROPOSAL_RESOURCE_RESUME, $proposal->resume_resource_persons_path);
        $externalPath = $this->attachmentPathOrLegacy($proposal, Attachment::TYPE_PROPOSAL_EXTERNAL_FUNDING, $proposal->external_funding_support_path);
        return [
            ['key' => 'overview', 'title' => 'Submission Overview', 'subtitle' => 'Organization and proposal submission context.', 'fields' => [
                ['key' => 'organization', 'label' => 'Organization (profile)', 'value' => $details['Organization (profile)'] ?? 'N/A'],
                ['key' => 'submitted_by', 'label' => 'Submitted By', 'value' => $details['Submitted By'] ?? 'N/A'],
                ['key' => 'submission_date', 'label' => 'Submission Date', 'value' => $details['Submission Date'] ?? 'N/A'],
                ['key' => 'academic_year', 'label' => 'Academic Year', 'value' => $details['Academic Year'] ?? 'N/A'],
            ]],
            ['key' => 'activity', 'title' => 'Activity Details', 'subtitle' => 'Event schedule, rationale, and mechanics.', 'fields' => [
                ['key' => 'activity_title', 'label' => 'Activity Title', 'value' => $details['Activity Title'] ?? 'N/A'],
                ['key' => 'proposed_start', 'label' => 'Proposed Start', 'value' => $details['Proposed Start'] ?? 'N/A'],
                ['key' => 'proposed_end', 'label' => 'Proposed End', 'value' => $details['Proposed End'] ?? 'N/A'],
                ['key' => 'proposed_time', 'label' => 'Proposed Time', 'value' => $details['Proposed Time'] ?? 'N/A'],
                ['key' => 'venue', 'label' => 'Venue', 'value' => $details['Venue'] ?? 'N/A'],
                ['key' => 'overall_goal', 'label' => 'Overall Goal', 'value' => $details['Overall Goal'] ?? 'N/A', 'wide' => true],
                ['key' => 'specific_objectives', 'label' => 'Specific Objectives', 'value' => $details['Specific Objectives'] ?? 'N/A', 'wide' => true],
                ['key' => 'criteria_mechanics', 'label' => 'Criteria / Mechanics', 'value' => $details['Criteria / Mechanics'] ?? 'N/A', 'wide' => true],
                ['key' => 'program_flow', 'label' => 'Program Flow', 'value' => $details['Program Flow'] ?? 'N/A', 'wide' => true],
            ]],
            ['key' => 'budget_files', 'title' => 'Budget and Files', 'subtitle' => 'Budget figures and required uploaded files.', 'fields' => [
                ['key' => 'proposed_budget_total', 'label' => 'Proposed Budget (total)', 'value' => $details['Proposed Budget (total)'] ?? 'N/A'],
                ['key' => 'source_of_funding', 'label' => 'Source of Funding', 'value' => $details['Source of Funding'] ?? 'N/A'],
                ['key' => 'organization_logo', 'label' => 'Organization Logo', 'value' => $logoPath ? 'File uploaded' : 'N/A', 'action' => $logoPath ? ['href' => Storage::disk('public')->url($logoPath)] : null],
                ['key' => 'resource_resume', 'label' => 'Resume (resource persons)', 'value' => $resumePath ? 'File uploaded' : 'N/A', 'action' => $resumePath ? ['href' => Storage::disk('public')->url($resumePath)] : null],
                ['key' => 'external_funding', 'label' => 'External Funding Support', 'value' => $externalPath ? 'File uploaded' : 'N/A', 'action' => $externalPath ? ['href' => Storage::disk('public')->url($externalPath)] : null],
            ]],
        ];
    }

    private function reportReviewSchema(): array
    {
        return [
            'overview' => [
                'organization' => 'Organization',
                'submitted_by' => 'Submitted By',
                'submission_date' => 'Submission Date',
                'event_title' => 'Activity / Event Title',
                'event_datetime' => 'Event Date & Time',
            ],
            'content' => [
                'program' => 'Program',
                'summary' => 'Summary / Description',
                'evaluation' => 'Activity Evaluation',
                'participants_reached' => 'Participants Reached (%)',
            ],
            'files' => [
                'poster_file' => 'Poster file',
                'attendance_file' => 'Attendance file',
            ],
        ];
    }

    private function reportReviewSections(ActivityReport $report, array $details): array
    {
        return [
            ['key' => 'overview', 'title' => 'Submission Overview', 'subtitle' => 'Core after-activity report details.', 'fields' => [
                ['key' => 'organization', 'label' => 'Organization', 'value' => $details['Organization'] ?? 'N/A'],
                ['key' => 'submitted_by', 'label' => 'Submitted By', 'value' => $details['Submitted By'] ?? 'N/A'],
                ['key' => 'submission_date', 'label' => 'Submission Date', 'value' => $details['Submission Date'] ?? 'N/A'],
                ['key' => 'event_title', 'label' => 'Activity / Event Title', 'value' => $details['Activity / Event Title'] ?? 'N/A'],
                ['key' => 'event_datetime', 'label' => 'Event Date & Time', 'value' => $details['Event Date & Time'] ?? 'N/A'],
            ]],
            ['key' => 'content', 'title' => 'Narrative Content', 'subtitle' => 'Submitted report narrative and evaluation.', 'fields' => [
                ['key' => 'program', 'label' => 'Program', 'value' => $details['Program'] ?? 'N/A', 'wide' => true],
                ['key' => 'summary', 'label' => 'Summary / Description', 'value' => $details['Summary / Description'] ?? 'N/A', 'wide' => true],
                ['key' => 'evaluation', 'label' => 'Activity Evaluation', 'value' => $details['Activity Evaluation'] ?? 'N/A', 'wide' => true],
                ['key' => 'participants_reached', 'label' => 'Participants Reached (%)', 'value' => $details['Participants Reached (%)'] ?? 'N/A'],
            ]],
            ['key' => 'files', 'title' => 'Attached Files', 'subtitle' => 'Poster and attendance support files.', 'fields' => [
                ['key' => 'poster_file', 'label' => 'Poster file', 'value' => (string) ($details['Poster file'] ?? 'N/A')],
                ['key' => 'attendance_file', 'label' => 'Attendance file', 'value' => (string) ($details['Attendance file'] ?? 'N/A')],
            ]],
        ];
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
