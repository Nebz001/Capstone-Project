@extends('layouts.organization-portal')

@section('title', 'Organization Profile — NU Lipa SDAO')

@section('content')

@php
    $statusColors = [
        'ACTIVE'    => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-800', 'dot' => 'bg-emerald-500', 'border' => 'border-emerald-200'],
        'PENDING'   => ['bg' => 'bg-amber-50', 'text' => 'text-amber-800', 'dot' => 'bg-amber-500', 'border' => 'border-amber-200'],
        'INACTIVE'  => ['bg' => 'bg-slate-50', 'text' => 'text-slate-700', 'dot' => 'bg-slate-400', 'border' => 'border-slate-200'],
        'SUSPENDED' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-800', 'dot' => 'bg-rose-500', 'border' => 'border-rose-200'],
    ];
    $status = $organization ? $organization->normalizedOrganizationStatus() : 'PENDING';
    $color  = $statusColors[$status] ?? $statusColors['PENDING'];

    $typeLabels = [
        'co_curricular'    => 'Co-Curricular Organization',
        'extra_curricular' => 'Extra-Curricular Organization / Interest Club',
        'CO_CURRICULAR'    => 'Co-Curricular Organization',
        'EXTRA_CURRICULAR' => 'Extra-Curricular Organization / Interest Club',
    ];

    $statusLabel = match ($status) {
        'ACTIVE' => 'Active',
        'PENDING' => 'Pending',
        'INACTIVE' => 'Inactive',
        'SUSPENDED' => 'Suspended',
        default => ucfirst(strtolower($status)),
    };

    $canEditProfile = $canEditProfile ?? false;
    $profileEditBlockedMessage = $profileEditBlockedMessage ?? '';
    $activeApplication = $activeApplication ?? null;
    $applicationTypeLabel = $applicationTypeLabel ?? null;
    $applicationWorkflowStatus = $applicationWorkflowStatus ?? null;
    $workflowDisplay = $applicationWorkflowStatus
        ? ucwords(strtolower(str_replace('_', ' ', $applicationWorkflowStatus)))
        : null;
    $applicationNotes = $activeApplication
        ? ($activeApplication instanceof \App\Models\OrganizationSubmission
            ? $activeApplication->notes
            : ($activeApplication instanceof \App\Models\OrganizationRenewal
                ? $activeApplication->renewal_notes
                : $activeApplication->registration_notes))
        : null;
    $profileRevisionSummary = is_array($profileRevisionSummary ?? null) ? $profileRevisionSummary : ['groups' => [], 'field_notes' => [], 'general_remarks' => null];
    $revisionFieldNotes = is_array($profileRevisionSummary['field_notes'] ?? null) ? $profileRevisionSummary['field_notes'] : [];
    $revisionGroups = is_array($profileRevisionSummary['groups'] ?? null) ? $profileRevisionSummary['groups'] : [];
    $revisionGeneralRemarks = is_string($profileRevisionSummary['general_remarks'] ?? null) ? $profileRevisionSummary['general_remarks'] : null;
    $hasActiveProfileRevisionItems = count($revisionGroups) > 0;
    $revisionEditMode = (bool) ($revisionEditMode ?? false);
    $revisionEditableFields = is_array($revisionEditableFields ?? null) ? $revisionEditableFields : [];
    $shouldShowField = static fn (string $field) => ! $revisionEditMode || in_array($field, $revisionEditableFields, true);
    $submittedByDefault = trim((string) old('submitted_by_display', $activeApplication?->submittedBy?->full_name ?? ''));
    $showRevisionRegistrationCard = $shouldShowField('submitted_by_display');
    $showRevisionApplicationCard = $shouldShowField('organization_name');
    $showRevisionOrganizationCard = $shouldShowField('organization_type')
        || $shouldShowField('college_department')
        || $shouldShowField('purpose');
    $showRevisionContactCard = $shouldShowField('contact_person')
        || $shouldShowField('contact_no')
        || $shouldShowField('contact_email');
    $showRevisionAdviserCard = $shouldShowField('adviser_name')
        || $shouldShowField('adviser_email')
        || $shouldShowField('adviser_school_id');
    $registrationAdviserNomination = $registrationAdviserNomination ?? null;
    $hasRevisionEditableCards = $showRevisionRegistrationCard || $showRevisionApplicationCard || $showRevisionOrganizationCard || $showRevisionContactCard || $showRevisionAdviserCard;
    $revisionAnchorId = static fn (string $sectionKey, string $fieldKey): string => 'revision-field-'.\Illuminate\Support\Str::of($sectionKey)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-').'-'.\Illuminate\Support\Str::of($fieldKey)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-');
    $revisionNoteFor = static function (array $keys) use ($revisionFieldNotes): ?string {
        foreach ($keys as $key) {
            $raw = $revisionFieldNotes[$key] ?? null;
            if (! is_scalar($raw)) {
                continue;
            }
            $note = trim((string) $raw);
            if ($note === '' || preg_match('/^(0+)(\\.0+)?$/', $note) === 1) {
                continue;
            }

            return $note;
        }

        return null;
    };
    $profileRevisionNotesByFormField = is_array($profileRevisionNotesByFormField ?? null) ? $profileRevisionNotesByFormField : [];
    $formRevisionNote = static function (string $formKey) use ($profileRevisionNotesByFormField): ?string {
        $note = trim((string) ($profileRevisionNotesByFormField[$formKey] ?? ''));
        if ($note === '' || preg_match('/^(0+)(\\.0+)?$/', $note) === 1) {
            return null;
        }

        return $note;
    };
    $saOrgId = isset($superAdminOrganizationId) && $superAdminOrganizationId ? (int) $superAdminOrganizationId : null;
    $saQ = $saOrgId ? '?organization_id='.$saOrgId : '';
    $fromDashboard = strtolower((string) request()->query('from', '')) === 'dashboard';
    $backHref = $fromDashboard
        ? route('organizations.index')
        : ($editing
            ? route('organizations.profile', array_filter(['organization_id' => auth()->user()->isSuperAdmin() && $organization ? $organization->id : null]))
            : route('organizations.manage').$saQ);
    $backLabel = $fromDashboard
        ? 'Back to Organization Dashboard'
        : ($editing ? 'Back to Organization Profile' : 'Back to Manage Organization');
    $isProfileRevisionRequested = (bool) ($organization?->isProfileRevisionRequested());
    $reviewWorkflowStatus = strtoupper((string) ($applicationWorkflowStatus ?? ''));
    $isApprovedActiveState = $status === 'ACTIVE' && $reviewWorkflowStatus === 'APPROVED';
    $isRevisionReviewState = ! $isApprovedActiveState
        && ($hasActiveProfileRevisionItems || $isProfileRevisionRequested)
        && (
            in_array($reviewWorkflowStatus, ['REVISION', 'REVISION_REQUIRED'], true)
            || $isProfileRevisionRequested
        );
    $isPendingReviewState = ! $isApprovedActiveState
        && ! $isRevisionReviewState
        && (
            $status === 'PENDING'
            || in_array($reviewWorkflowStatus, ['PENDING', 'UNDER_REVIEW', 'REVIEWED'], true)
        );
    $profileBlockedMessageVariant = $isApprovedActiveState ? 'success' : ($isPendingReviewState ? 'info' : 'info');
    $profileBlockedMessageText = $isApprovedActiveState
        ? 'Your organization profile has been approved and is now active.'
        : ($isPendingReviewState
            ? 'Your updated information has been submitted and is now under SDAO review. Editing is temporarily locked while SDAO verifies your updates.'
            : $profileEditBlockedMessage);
    $approvedGeneralRemarks = 'Organization Approved!';
    $readonlyItemClass = $readonlyItemClass ?? 'rounded-xl border border-slate-200 bg-slate-100/70 px-4 py-3';
    $readonlyLabelClass = $readonlyLabelClass ?? 'text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500';
    $readonlyValueClass = $readonlyValueClass ?? 'mt-1.5 text-sm font-bold text-slate-900';
    $readonlyValueLongClass = $readonlyValueLongClass ?? 'mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-slate-800';
@endphp

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">

    <header class="mb-6">
        <a
            href="{{ $backHref }}"
            class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]"
        >
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
            {{ $backLabel }}
        </a>
        <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    Organization Profile
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    View your organization’s registered information.
                </p>
            </div>

            @if ($organization && !$editing)
                @if ($canEditProfile)
                    <a
                        href="{{ route('organizations.profile', array_filter(['edit' => 1, 'organization_id' => auth()->user()->isSuperAdmin() && $organization ? $organization->id : null])) }}"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/40 active:bg-[#002d75]"
                    >
                        <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                        Edit Profile
                    </a>
                @else
                    <span
                        class="inline-flex shrink-0 cursor-not-allowed select-none items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-400 shadow-none"
                        role="status"
                        aria-disabled="true"
                        tabindex="-1"
                        title="{{ $profileEditBlockedMessage }}"
                    >
                        <svg class="h-4 w-4 shrink-0 opacity-60" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                        Edit Profile
                    </span>
                @endif
            @endif
        </div>

        @if ($organization && ! $editing && ! $canEditProfile && ! $isApprovedActiveState)
            <x-feedback.blocked-message variant="{{ $profileBlockedMessageVariant }}" :message="$profileBlockedMessageText" class="mt-4" />
        @endif

        @if ($organization && ! $editing && $isApprovedActiveState)
            <x-feedback.blocked-message variant="success" :icon="false" class="mt-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100/90" aria-hidden="true">
                        <svg class="h-4.5 w-4.5 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold">Organization profile approved</p>
                        <p class="mt-1 text-sm font-normal">Your organization profile has been approved and is now active.</p>
                    </div>
                </div>
                <div class="mt-4 rounded-lg border border-emerald-300/90 bg-white px-3 py-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-900">General Remarks</p>
                    <p class="mt-1.5 whitespace-pre-wrap text-sm font-semibold leading-relaxed text-emerald-900">{{ $approvedGeneralRemarks }}</p>
                </div>
            </x-feedback.blocked-message>
        @endif

        @if ($organization && ! $editing && $isRevisionReviewState)
            <x-feedback.blocked-message variant="warning" :icon="false" class="mt-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-yellow-100/90" aria-hidden="true">
                        <svg class="h-4.5 w-4.5 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold">Profile information — For revision</p>
                        <p class="mt-1 text-sm font-normal">SDAO has requested updates to your organization profile. Use <strong>Edit Profile</strong> to correct the sections noted below and save your changes.</p>
                    </div>
                </div>
                @if (count($revisionGroups) > 0)
                    <div class="mt-4 space-y-3">
                        @foreach ($revisionGroups as $group)
                            <div class="rounded-lg border border-yellow-200/90 bg-white/60 px-3 py-3">
                                <p class="text-xs font-bold uppercase tracking-wide text-yellow-950">{{ $group['section_title'] ?? 'Section' }} ({{ count($group['items'] ?? []) }})</p>
                                <ul class="mt-2 space-y-1.5">
                                    @foreach (($group['items'] ?? []) as $item)
                                        <li>
                                            <button
                                                type="button"
                                                class="inline-flex w-full items-start gap-1 rounded-md px-2 py-1 text-left text-xs text-yellow-950 transition hover:bg-yellow-100/70 focus:outline-none focus:ring-2 focus:ring-yellow-400/60"
                                                data-revision-target-id="{{ $item['anchor_id'] ?? '' }}"
                                                data-revision-href="{{ $item['href'] ?? '' }}"
                                                data-revision-section-key="{{ $group['section_key'] ?? '' }}"
                                                data-revision-field-key="{{ $item['field_key'] ?? '' }}"
                                                data-revision-field-label="{{ $item['field_label'] ?? '' }}"
                                            >
                                                <span class="font-semibold underline underline-offset-2">{{ $item['field_label'] ?? 'Field' }}</span>
                                                <span>— {{ $item['note'] ?? '' }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="mt-4 rounded-lg border border-yellow-200/90 bg-white/60 px-3 py-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-yellow-950">General Remarks</p>
                    <p class="mt-1.5 whitespace-pre-wrap text-sm font-normal leading-relaxed text-yellow-950/90">{{ $revisionGeneralRemarks ?: 'No general remarks provided.' }}</p>
                </div>
            </x-feedback.blocked-message>
        @endif
    </header>

    @if (session('success'))
        <x-feedback.blocked-message variant="success" class="mb-6" :message="session('success')" />
    @endif

    @if (session('error'))
        <x-feedback.blocked-message variant="error" class="mb-6" :message="session('error')" />
    @endif

    @if (!$organization)

        <x-ui.card>
            <div class="flex flex-col items-center py-8 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                    <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                    </svg>
                </div>
                <h2 class="mt-4 text-base font-semibold text-slate-900">No Organization Found</h2>
                <p class="mt-1 max-w-sm text-sm text-slate-500">
                    Your account is not linked to any organization yet. Register a new organization to get started.
                </p>
                <a
                    href="{{ route('organizations.register') }}"
                    class="mt-6 inline-flex items-center justify-center rounded-xl bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-800/30 transition hover:bg-sky-800 focus:outline-none focus:ring-4 focus:ring-sky-500/25"
                >
                    Register Organization
                </a>
            </div>
        </x-ui.card>

    @else

        @if (!$editing)

            <div class="space-y-4">

                <section aria-labelledby="profile-section-status-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-section-status-heading" class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Status Information</h2>
                            <p class="mt-0.5 text-xs leading-snug text-slate-500">Accreditation status managed by SDAO.</p>
                        </div>
                        <div class="bg-white px-6 py-4.5">
                            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 sm:gap-x-5">
                                <div class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Organization Status</p>
                                    <div class="mt-2">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border {{ $color['border'] }} {{ $color['bg'] }} px-3 py-1 text-xs font-semibold {{ $color['text'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $color['dot'] }}" aria-hidden="true"></span>
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                </div>
                                <div class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Last Updated</p>
                                    <p class="{{ $readonlyValueClass }}">
                                        {{ $organization->updated_at?->format('F j, Y — g:i A') ?? '—' }}
                                    </p>
                                </div>
                                @if ($workflowDisplay)
                                    <div class="{{ $readonlyItemClass }} sm:col-span-2">
                                        <p class="{{ $readonlyLabelClass }}">Latest application review status</p>
                                        <p class="{{ $readonlyValueClass }}">{{ $workflowDisplay }}</p>
                                    </div>
                                @endif
                                @if ($organization->profile_revision_notes)
                                    <div class="{{ $readonlyItemClass }} sm:col-span-2">
                                        <p class="{{ $readonlyLabelClass }}">SDAO remarks / revision notes</p>
                                        <p class="{{ $readonlyValueLongClass }}">{{ $organization->profile_revision_notes }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="border-t border-slate-100 bg-slate-50/95 px-6 py-4">
                            <p class="text-xs font-medium leading-relaxed text-slate-700">
                                Organization status is managed by the SDAO office and cannot be changed here.
                            </p>
                        </div>
                    </x-ui.card>
                </section>

                <section aria-labelledby="profile-section-registration-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-section-registration-heading" class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Registration Information</h2>
                            <p class="mt-0.5 text-xs leading-snug text-slate-500">Details from your latest registration or renewal submission.</p>
                        </div>
                        <div class="bg-white px-6 py-4.5">
                            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 sm:gap-x-5">
                                <div id="{{ $revisionAnchorId('application', 'application_type') }}" class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Application type</p>
                                    <p class="{{ $readonlyValueClass }}">{{ $applicationTypeLabel ?? '—' }}</p>
                                </div>
                                <div id="{{ $revisionAnchorId('application', 'academic_year') }}" class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Academic year</p>
                                    <p class="{{ $readonlyValueClass }}">{{ $activeApplication?->academic_year ?? '—' }}</p>
                                    @php $academicYearNote = $revisionNoteFor(['application.academic_year', 'overview.academic_year']); @endphp
                                    @if ($academicYearNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $academicYearNote }}</p>
                                    @endif
                                </div>
                                <div id="{{ $revisionAnchorId('application', 'submission_date') }}" class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Submission date</p>
                                    <p class="{{ $readonlyValueClass }}">
                                        {{ $activeApplication?->submission_date?->format('F j, Y') ?? '—' }}
                                    </p>
                                    @php $submissionDateNote = $revisionNoteFor(['application.submission_date', 'overview.submission_date']); @endphp
                                    @if ($submissionDateNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $submissionDateNote }}</p>
                                    @endif
                                </div>
                                <div id="{{ $revisionAnchorId('application', 'submitted_by') }}" class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Submitted by</p>
                                    <p class="{{ $readonlyValueClass }}">
                                        {{ $activeApplication?->submittedBy?->full_name ?? $activeApplication?->user?->full_name ?? '—' }}
                                    </p>
                                    @php $submittedByNote = $formRevisionNote('submitted_by_display') ?? $revisionNoteFor(['application.submitted_by', 'overview.submitted_by']); @endphp
                                    @if ($submittedByNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $submittedByNote }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </x-ui.card>
                </section>

                <section aria-labelledby="profile-section-contact-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-section-contact-heading" class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Contact Information</h2>
                            <p class="mt-0.5 text-xs leading-snug text-slate-500">Contact person on file for your latest application.</p>
                        </div>
                        <div class="bg-white px-6 py-4.5">
                            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 sm:gap-x-5">
                                <div id="{{ $revisionAnchorId('contact', 'contact_person') }}" class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Contact person</p>
                                    <p class="{{ $readonlyValueClass }}">{{ $activeApplication?->contact_person ?? '—' }}</p>
                                    @php $contactPersonNote = $formRevisionNote('contact_person'); @endphp
                                    @if ($contactPersonNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $contactPersonNote }}</p>
                                    @endif
                                </div>
                                <div id="{{ $revisionAnchorId('contact', 'contact_no') }}" class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Contact Person Contact No.</p>
                                    <p class="{{ $readonlyValueClass }}">{{ $activeApplication?->contact_no ?? '—' }}</p>
                                    @php $contactNoNote = $formRevisionNote('contact_no'); @endphp
                                    @if ($contactNoNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $contactNoNote }}</p>
                                    @endif
                                </div>
                                <div id="{{ $revisionAnchorId('contact', 'contact_email') }}" class="{{ $readonlyItemClass }} sm:col-span-2">
                                    <p class="{{ $readonlyLabelClass }}">Contact Person Email Address</p>
                                    <p class="{{ $readonlyValueClass }}">{{ $activeApplication?->contact_email ?? '—' }}</p>
                                    @php $contactEmailNote = $formRevisionNote('contact_email'); @endphp
                                    @if ($contactEmailNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $contactEmailNote }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </x-ui.card>
                </section>

                <section aria-labelledby="profile-section-org-details-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-section-org-details-heading" class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Organization Details</h2>
                            <p class="mt-0.5 text-xs leading-snug text-slate-500">Core information about your organization.</p>
                        </div>
                        <div class="bg-white px-6 py-4.5">
                            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 sm:gap-x-5">
                                <div id="{{ $revisionAnchorId('application', 'organization') }}" class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Organization Name</p>
                                    <p class="{{ $readonlyValueClass }}">{{ $organization->organization_name }}</p>
                                    @php $orgNameNote = $formRevisionNote('organization_name') ?? $revisionNoteFor(['application.organization']); @endphp
                                    @if ($orgNameNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $orgNameNote }}</p>
                                    @endif
                                </div>
                                <div id="{{ $revisionAnchorId('organizational', 'organization_type') }}" class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Organization Type</p>
                                    <p class="{{ $readonlyValueClass }}">{{ $typeLabels[$organization->organization_type] ?? $organization->organization_type }}</p>
                                    @php $orgTypeNote = $formRevisionNote('organization_type'); @endphp
                                    @if ($orgTypeNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $orgTypeNote }}</p>
                                    @endif
                                </div>
                                <div id="{{ $revisionAnchorId('organizational', 'school') }}" class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">College / Department</p>
                                    <p class="{{ $readonlyValueClass }}">{{ $organization->college_department }}</p>
                                    @php $schoolNote = $formRevisionNote('college_department'); @endphp
                                    @if ($schoolNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $schoolNote }}</p>
                                    @endif
                                </div>
                                <div id="{{ $revisionAnchorId('organizational', 'date_organized') }}" class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Founded Date</p>
                                    <p class="{{ $readonlyValueClass }}">
                                        {{ $organization->founded_date?->format('F j, Y') ?? '—' }}
                                    </p>
                                </div>
                                <div id="{{ $revisionAnchorId('organizational', 'purpose') }}" class="{{ $readonlyItemClass }} sm:col-span-2">
                                    <p class="{{ $readonlyLabelClass }}">Purpose</p>
                                    <p class="{{ $readonlyValueLongClass }}">{{ $organization->purpose ?? '—' }}</p>
                                    @php $purposeNote = $formRevisionNote('purpose'); @endphp
                                    @if ($purposeNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $purposeNote }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </x-ui.card>
                </section>

                <section aria-labelledby="profile-section-adviser-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-section-adviser-heading" class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Adviser Information</h2>
                            <p class="mt-0.5 text-xs leading-snug text-slate-500">Faculty adviser assigned to this organization.</p>
                        </div>
                        <div class="bg-white px-6 py-4.5">
                            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 sm:gap-x-5">
                                <div id="{{ $revisionAnchorId('adviser', 'full_name') }}" class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Adviser Name</p>
                                    <p class="{{ $readonlyValueClass }}">{{ $adviser['name'] ?? $activeAdviser?->user?->full_name ?? 'No adviser assigned.' }}</p>
                                    @php $adviserNameNote = $formRevisionNote('adviser_name'); @endphp
                                    @if ($adviserNameNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $adviserNameNote }}</p>
                                    @endif
                                </div>
                                <div id="{{ $revisionAnchorId('adviser', 'school_id') }}" class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Adviser School ID</p>
                                    <p class="{{ $readonlyValueClass }}">{{ $adviser['school_id'] ?? $activeAdviser?->user?->school_id ?? 'No adviser assigned.' }}</p>
                                    @php $adviserSchoolNote = $formRevisionNote('adviser_school_id'); @endphp
                                    @if ($adviserSchoolNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $adviserSchoolNote }}</p>
                                    @endif
                                </div>
                                <div id="{{ $revisionAnchorId('adviser', 'email') }}" class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Adviser Email</p>
                                    <p class="{{ $readonlyValueClass }}">{{ $adviser['email'] ?? $activeAdviser?->user?->email ?? 'No adviser assigned.' }}</p>
                                    @php $adviserEmailNote = $formRevisionNote('adviser_email'); @endphp
                                    @if ($adviserEmailNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $adviserEmailNote }}</p>
                                    @endif
                                </div>
                                @php
                                    $profileAdviserWorkflowStatus = $registrationAdviserNomination?->status ?? $activeAdviser?->status;
                                    $profileAdviserWorkflowLabel = $profileAdviserWorkflowStatus !== null && $profileAdviserWorkflowStatus !== '' ? ucfirst((string) $profileAdviserWorkflowStatus) : '—';
                                    $profileAdviserStatusKey = strtolower(trim((string) ($profileAdviserWorkflowStatus ?? '')));
                                    $profileAdviserStatusChip = match (true) {
                                        in_array($profileAdviserStatusKey, ['approved', 'active'], true) => ['border' => 'border-emerald-200', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500'],
                                        $profileAdviserStatusKey === 'rejected' => ['border' => 'border-rose-200', 'bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'dot' => 'bg-rose-500'],
                                        $profileAdviserStatusKey === 'pending' => ['border' => 'border-amber-200', 'bg' => 'bg-amber-50', 'text' => 'text-amber-800', 'dot' => 'bg-amber-500'],
                                        default => ['border' => 'border-slate-200', 'bg' => 'bg-slate-50', 'text' => 'text-slate-700', 'dot' => 'bg-slate-400'],
                                    };
                                @endphp
                                <div class="{{ $readonlyItemClass }}">
                                    <p class="{{ $readonlyLabelClass }}">Adviser status</p>
                                    <div class="mt-2">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border {{ $profileAdviserStatusChip['border'] }} {{ $profileAdviserStatusChip['bg'] }} px-3 py-1 text-xs font-semibold {{ $profileAdviserStatusChip['text'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $profileAdviserStatusChip['dot'] }}" aria-hidden="true"></span>
                                            {{ $profileAdviserWorkflowLabel }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-ui.card>
                </section>

            </div>

        @else

            @if ($organization->isProfileRevisionRequested())
                <x-feedback.blocked-message variant="warning" class="mb-6">
                    <p class="font-semibold">You are editing under an SDAO revision request</p>
                    @if (filled($organization->profile_revision_notes))
                        <p class="mt-2 whitespace-pre-wrap text-sm font-normal text-yellow-900/90">{{ $organization->profile_revision_notes }}</p>
                    @endif
                </x-feedback.blocked-message>
            @endif

            @if ($revisionEditMode)
                <x-feedback.blocked-message variant="info" class="mb-6">
                    <p class="font-semibold">Edit Required Revisions</p>
                    <p class="mt-1 text-sm font-normal">Only fields requested for revision are shown below.</p>
                </x-feedback.blocked-message>
            @endif

            <form method="POST" action="{{ route('organizations.profile.update', array_filter(['from' => $fromDashboard ? 'dashboard' : null])) }}" class="space-y-4" data-revision-edit-mode="{{ $revisionEditMode ? '1' : '0' }}">
                @csrf
                @method('PUT')
                @if (auth()->user()->isSuperAdmin() && $organization)
                    <input type="hidden" name="organization_id" value="{{ $organization->id }}" />
                @endif
                @error('revision_changes')
                    <x-feedback.blocked-message variant="error" :message="$message" />
                @enderror

                @if (! $revisionEditMode)
                    <section aria-labelledby="profile-edit-section-status-heading">
                        <x-ui.card padding="p-0" class="overflow-hidden">
                            <div class="border-b border-slate-100 bg-white px-6 py-4">
                                <h2 id="profile-edit-section-status-heading" class="text-base font-bold text-slate-900">Status Information</h2>
                                <p class="mt-0.5 text-xs font-medium leading-snug text-slate-600">Read-only — managed by SDAO.</p>
                            </div>
                            <div class="bg-white px-6 py-5">
                                <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Organization Status</p>
                                    <div class="mt-2">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border {{ $color['border'] }} {{ $color['bg'] }} px-3 py-1 text-xs font-semibold {{ $color['text'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $color['dot'] }}" aria-hidden="true"></span>
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="border-t border-slate-100 bg-slate-50/95 px-6 py-4">
                                <p class="text-xs font-medium leading-relaxed text-slate-700">
                                    Organization status is managed by the SDAO office and cannot be changed here.
                                </p>
                            </div>
                        </x-ui.card>
                    </section>
                @endif

                @if ($showRevisionRegistrationCard)
                <section aria-labelledby="profile-edit-section-registration-info-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-edit-section-registration-info-heading" class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Registration Information</h2>
                            <p class="mt-0.5 text-xs leading-snug text-slate-500">Only requested fields are shown in this revision form.</p>
                        </div>
                        <div class="bg-white px-6 py-4.5">
                            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 sm:gap-x-5">
                                <div id="{{ $revisionAnchorId('application', 'submitted_by') }}" data-profile-revision-field="submitted_by_display" class="{{ $readonlyItemClass }} transition duration-150">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <x-forms.label for="submitted_by_display" required class="!mb-0">Submitted By</x-forms.label>
                                    </div>
                                    <x-forms.input
                                        id="submitted_by_display"
                                        name="submitted_by_display"
                                        class="mt-1.5"
                                        type="text"
                                        :value="$submittedByDefault"
                                        data-revision-original="{{ $submittedByDefault }}"
                                        required
                                    />
                                    <input type="hidden" name="submitted_by_display_original" value="{{ $submittedByDefault }}">
                                    @php $submittedByRevisionNote = $formRevisionNote('submitted_by_display') ?? $revisionNoteFor(['application.submitted_by', 'overview.submitted_by']); @endphp
                                    @if ($submittedByRevisionNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $submittedByRevisionNote }}</p>
                                    @endif
                                    @error('submitted_by_display') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                                </div>
                            </div>
                        </div>
                    </x-ui.card>
                </section>
                @endif

                @if ($showRevisionApplicationCard)
                <section aria-labelledby="profile-edit-section-org-details-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-edit-section-org-details-heading" class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Application Information</h2>
                            <p class="mt-0.5 text-xs leading-snug text-slate-500">Only requested fields are shown in this revision form.</p>
                        </div>
                    <div class="bg-white px-6 py-4.5">
                        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 sm:gap-x-5">
                            <div id="{{ $revisionAnchorId('application', 'organization') }}" data-profile-revision-field="organization_name" class="{{ $readonlyItemClass }} transition duration-150">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <x-forms.label for="organization_name" required class="!mb-0">Organization Name</x-forms.label>
                                    <span data-revision-updated-badge class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700">Updated</span>
                                </div>
                                <x-forms.input
                                    id="organization_name"
                                    name="organization_name"
                                    class="mt-1.5"
                                    type="text"
                                    :value="old('organization_name', $organization->organization_name)"
                                    data-revision-original="{{ old('organization_name', $organization->organization_name) }}"
                                    required
                                />
                                @php $editOrgNameNote = $formRevisionNote('organization_name') ?? $revisionNoteFor(['application.organization']); @endphp
                                @if ($editOrgNameNote)
                                    <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $editOrgNameNote }}</p>
                                @endif
                                @error('organization_name') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                            </div>
                        </div>
                    </div>
                </x-ui.card>
                </section>
                @endif

                @if ($showRevisionOrganizationCard)
                <section aria-labelledby="profile-edit-section-org-information-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-edit-section-org-information-heading" class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Organization Information</h2>
                            <p class="mt-0.5 text-xs leading-snug text-slate-500">Only requested fields are shown in this revision form.</p>
                        </div>
                    <div class="bg-white px-6 py-4.5">
                        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 sm:gap-x-5">
                            @if ($shouldShowField('organization_type'))
                            <div id="{{ $revisionAnchorId('organizational', 'organization_type') }}" data-profile-revision-field="organization_type" class="{{ $readonlyItemClass }} transition duration-150">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <x-forms.label for="organization_type" required class="!mb-0">Organization Type</x-forms.label>
                                    <span data-revision-updated-badge class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700">Updated</span>
                                </div>
                                <x-forms.select id="organization_type" name="organization_type" class="mt-1.5" required>
                                    <option value="" disabled>Select type</option>
                                    <option value="co_curricular" @selected(old('organization_type', $organization->organization_type) === 'co_curricular' || old('organization_type', $organization->organization_type) === 'CO_CURRICULAR')>
                                        Co-Curricular Organization
                                    </option>
                                    <option value="extra_curricular" @selected(old('organization_type', $organization->organization_type) === 'extra_curricular' || old('organization_type', $organization->organization_type) === 'EXTRA_CURRICULAR')>
                                        Extra-Curricular Organization / Interest Club
                                    </option>
                                </x-forms.select>
                                <input type="hidden" data-revision-original-for="organization_type" value="{{ old('organization_type', $organization->organization_type) }}">
                                @php $editOrgTypeNote = $formRevisionNote('organization_type'); @endphp
                                @if ($editOrgTypeNote)
                                    <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $editOrgTypeNote }}</p>
                                @endif
                                @error('organization_type') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                            </div>
                            @endif

                            @if ($shouldShowField('college_department'))
                            <div id="{{ $revisionAnchorId('organizational', 'school') }}" data-profile-revision-field="college_department" class="{{ $readonlyItemClass }} transition duration-150">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <x-forms.label for="college_department" required class="!mb-0">College / Department</x-forms.label>
                                    <span data-revision-updated-badge class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700">Updated</span>
                                </div>
                                <x-forms.input
                                    id="college_department"
                                    name="college_department"
                                    class="mt-1.5"
                                    type="text"
                                    :value="old('college_department', $organization->college_department)"
                                    data-revision-original="{{ old('college_department', $organization->college_department) }}"
                                    required
                                />
                                @php $editSchoolNote = $formRevisionNote('college_department'); @endphp
                                @if ($editSchoolNote)
                                    <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $editSchoolNote }}</p>
                                @endif
                                @error('college_department') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                            </div>
                            @endif

                            @if ($shouldShowField('purpose'))
                            <div id="{{ $revisionAnchorId('organizational', 'purpose') }}" data-profile-revision-field="purpose" class="{{ $readonlyItemClass }} transition duration-150 sm:col-span-2">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <x-forms.label for="purpose" required class="!mb-0">Purpose</x-forms.label>
                                    <span data-revision-updated-badge class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700">Updated</span>
                                </div>
                                <x-forms.textarea id="purpose" name="purpose" class="mt-1.5" rows="4" required data-revision-original="{{ old('purpose', $organization->purpose) }}">{{ old('purpose', $organization->purpose) }}</x-forms.textarea>
                                @php $editPurposeNote = $formRevisionNote('purpose'); @endphp
                                @if ($editPurposeNote)
                                    <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $editPurposeNote }}</p>
                                @endif
                                @error('purpose') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                            </div>
                            @endif
                        </div>
                    </div>
                </x-ui.card>
                </section>
                @endif

                @if ($showRevisionContactCard)
                <section aria-labelledby="profile-edit-section-contact-revision-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-edit-section-contact-revision-heading" class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Contact Information</h2>
                            <p class="mt-0.5 text-xs leading-snug text-slate-500">Only requested fields are shown in this revision form.</p>
                        </div>
                        <div class="bg-white px-6 py-4.5">
                            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 sm:gap-x-5">
                                @if ($shouldShowField('contact_person'))
                                <div id="{{ $revisionAnchorId('contact', 'contact_person') }}" data-profile-revision-field="contact_person" class="{{ $readonlyItemClass }} transition duration-150">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <x-forms.label for="contact_person" class="!mb-0">Contact Person</x-forms.label>
                                        <span data-revision-updated-badge class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700">Updated</span>
                                    </div>
                                    <x-forms.input
                                        id="contact_person"
                                        name="contact_person"
                                        class="mt-1.5"
                                        type="text"
                                        :value="old('contact_person', $activeApplication?->contact_person ?? '')"
                                        data-revision-original="{{ old('contact_person', $activeApplication?->contact_person ?? '') }}"
                                    />
                                    @php $editContactPersonNote = $formRevisionNote('contact_person'); @endphp
                                    @if ($editContactPersonNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $editContactPersonNote }}</p>
                                    @endif
                                    @error('contact_person') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                                </div>
                                @endif
                                @if ($shouldShowField('contact_no'))
                                <div id="{{ $revisionAnchorId('contact', 'contact_no') }}" data-profile-revision-field="contact_no" class="{{ $readonlyItemClass }} transition duration-150">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <x-forms.label for="contact_no" class="!mb-0">Contact Person Contact No.</x-forms.label>
                                        <span data-revision-updated-badge class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700">Updated</span>
                                    </div>
                                    <x-forms.input
                                        id="contact_no"
                                        name="contact_no"
                                        class="mt-1.5"
                                        type="text"
                                        :value="old('contact_no', $activeApplication?->contact_no ?? '')"
                                        data-revision-original="{{ old('contact_no', $activeApplication?->contact_no ?? '') }}"
                                    />
                                    @php $editContactNoNote = $formRevisionNote('contact_no'); @endphp
                                    @if ($editContactNoNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $editContactNoNote }}</p>
                                    @endif
                                    @error('contact_no') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                                </div>
                                @endif
                                @if ($shouldShowField('contact_email'))
                                <div id="{{ $revisionAnchorId('contact', 'contact_email') }}" data-profile-revision-field="contact_email" class="{{ $readonlyItemClass }} transition duration-150 sm:col-span-2">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <x-forms.label for="contact_email" class="!mb-0">Contact Person Email Address</x-forms.label>
                                        <span data-revision-updated-badge class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700">Updated</span>
                                    </div>
                                    <x-forms.input
                                        id="contact_email"
                                        name="contact_email"
                                        class="mt-1.5"
                                        type="email"
                                        :value="old('contact_email', $activeApplication?->contact_email ?? '')"
                                        data-revision-original="{{ old('contact_email', $activeApplication?->contact_email ?? '') }}"
                                    />
                                    @php $editContactEmailNote = $formRevisionNote('contact_email'); @endphp
                                    @if ($editContactEmailNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $editContactEmailNote }}</p>
                                    @endif
                                    @error('contact_email') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                                </div>
                                @endif
                            </div>
                        </div>
                    </x-ui.card>
                </section>
                @endif

                @if ($showRevisionAdviserCard)
                <section aria-labelledby="profile-edit-section-adviser-revision-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-edit-section-adviser-revision-heading" class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Adviser Information</h2>
                            <p class="mt-0.5 text-xs leading-snug text-slate-500">Only requested fields are shown in this revision form.</p>
                        </div>
                        <div class="bg-white px-6 py-4.5">
                            <div class="grid grid-cols-1 gap-3.5 md:grid-cols-2 md:gap-x-5">
                                @if ($shouldShowField('adviser_name'))
                                <div id="{{ $revisionAnchorId('adviser', 'full_name') }}" data-profile-revision-field="adviser_name" class="{{ $readonlyItemClass }} transition duration-150">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <x-forms.label for="adviser_name" class="!mb-0">Adviser Name</x-forms.label>
                                        <span data-revision-updated-badge class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700">Updated</span>
                                    </div>
                                    <x-forms.input
                                        id="adviser_name"
                                        name="adviser_name"
                                        class="mt-1.5"
                                        type="text"
                                        placeholder="e.g., Prof. Juan Dela Cruz"
                                        :value="old('adviser_name', $adviser['name'] ?? $activeAdviser?->user?->full_name ?? '')"
                                        data-revision-original="{{ old('adviser_name', $adviser['name'] ?? $activeAdviser?->user?->full_name ?? '') }}"
                                    />
                                    @php $editAdviserNameNote = $formRevisionNote('adviser_name'); @endphp
                                    @if ($editAdviserNameNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $editAdviserNameNote }}</p>
                                    @endif
                                    @error('adviser_name') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                                </div>
                                @endif
                                @if ($shouldShowField('adviser_email'))
                                <div id="{{ $revisionAnchorId('adviser', 'email') }}" data-profile-revision-field="adviser_email" class="{{ $readonlyItemClass }} transition duration-150">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <x-forms.label for="adviser_email" class="!mb-0">Adviser Email</x-forms.label>
                                        <span data-revision-updated-badge class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700">Updated</span>
                                    </div>
                                    <x-forms.input
                                        id="adviser_email"
                                        name="adviser_email"
                                        class="mt-1.5"
                                        type="email"
                                        :value="old('adviser_email', $adviser['email'] ?? $activeAdviser?->user?->email ?? '')"
                                        data-revision-original="{{ old('adviser_email', $adviser['email'] ?? $activeAdviser?->user?->email ?? '') }}"
                                    />
                                    @php $editAdviserEmailNote = $formRevisionNote('adviser_email'); @endphp
                                    @if ($editAdviserEmailNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $editAdviserEmailNote }}</p>
                                    @endif
                                    @error('adviser_email') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                                </div>
                                @endif
                                @if ($shouldShowField('adviser_school_id'))
                                <div id="{{ $revisionAnchorId('adviser', 'school_id') }}" data-profile-revision-field="adviser_school_id" class="{{ $readonlyItemClass }} transition duration-150">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <x-forms.label for="adviser_school_id" class="!mb-0">Adviser School ID</x-forms.label>
                                        <span data-revision-updated-badge class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700">Updated</span>
                                    </div>
                                    <x-forms.input
                                        id="adviser_school_id"
                                        name="adviser_school_id"
                                        class="mt-1.5"
                                        type="text"
                                        :value="old('adviser_school_id', $adviser['school_id'] ?? $activeAdviser?->user?->school_id ?? '')"
                                        data-revision-original="{{ old('adviser_school_id', $adviser['school_id'] ?? $activeAdviser?->user?->school_id ?? '') }}"
                                    />
                                    @php $editAdviserSchoolNote = $formRevisionNote('adviser_school_id'); @endphp
                                    @if ($editAdviserSchoolNote)
                                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900"><span class="font-semibold">Revision note:</span> {{ $editAdviserSchoolNote }}</p>
                                    @endif
                                    @error('adviser_school_id') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                                </div>
                                @endif
                            </div>
                        </div>
                    </x-ui.card>
                </section>
                @endif

                @if ($revisionEditMode && ! $hasRevisionEditableCards)
                    <x-feedback.blocked-message
                        variant="info"
                        message="No editable profile fields are currently marked for revision. File-related revisions can be addressed in Submitted Documents."
                    />
                @endif

                @if (! $revisionEditMode)
                <section aria-labelledby="profile-edit-section-adviser-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-edit-section-adviser-heading" class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Adviser Information</h2>
                            <p class="mt-0.5 text-xs leading-snug text-slate-500">Update the faculty adviser for this organization.</p>
                        </div>
                        <div class="bg-white px-6 py-4.5">
                            <div class="grid grid-cols-1 gap-3.5 md:grid-cols-2 md:gap-x-5">
                                <div>
                                    <x-forms.label for="adviser_name">Adviser Name</x-forms.label>
                                    <x-forms.input
                                        id="adviser_name"
                                        name="adviser_name"
                                        type="text"
                                        placeholder="e.g., Prof. Juan Dela Cruz"
                                        :value="old('adviser_name', $adviser['name'] ?? $activeAdviser?->user?->full_name ?? '')"
                                    />
                                    @error('adviser_name') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                                </div>
                            </div>
                        </div>
                    </x-ui.card>
                </section>
                @endif

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end sm:gap-3">
                    <a
                        href="{{ $backHref }}"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20"
                    >
                        Cancel
                    </a>
                    <x-ui.button type="submit" id="revision-save-btn" :disabled="$revisionEditMode && ! $hasRevisionEditableCards">
                        Save Changes
                    </x-ui.button>
                </div>
                @if ($revisionEditMode && $hasRevisionEditableCards)
                    <p id="revision-save-all-fields-hint" class="hidden text-right text-xs text-slate-600 sm:w-full">
                        Update all requested fields before saving changes.
                    </p>
                @endif
            </form>

        @endif

    @endif

</div>

@if ($revisionEditMode)
<div id="revision-confirm-modal" class="fixed inset-0 z-90 hidden items-center justify-center bg-slate-950/60 px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="revision-confirm-title">
    <div class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-900/20">
        <div class="border-b border-slate-100 px-6 py-5">
            <h3 id="revision-confirm-title" class="text-xl font-bold tracking-tight text-slate-900">Confirm Profile Changes</h3>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-500">Review the changes below before submitting your revised profile.</p>
        </div>
        <div class="border-b border-slate-100 bg-slate-50/40 px-6 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Changes Summary</p>
            <div class="mt-3 max-h-[52vh] overflow-y-auto pr-1">
                <div id="revision-confirm-list" class="space-y-3"></div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
            <button type="button" id="revision-confirm-cancel" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20">
                Cancel
            </button>
            <button type="button" id="revision-confirm-submit" class="inline-flex items-center justify-center rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-sky-800/25 transition hover:bg-sky-800 focus:outline-none focus:ring-4 focus:ring-sky-500/25">
                Confirm Changes
            </button>
        </div>
    </div>
</div>
@endif

<script>
(() => {
    const actions = document.querySelectorAll('[data-revision-target-id]');
    const headerOffset = 96;
    const flashClass = ['ring-2', 'ring-amber-300', 'bg-amber-50/80'];
    const normalizeKey = (value) => String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    const aliasToTargetId = {
        organization: 'revision-field-application-organization',
        organization_name: 'revision-field-application-organization',
        org_name: 'revision-field-application-organization',
        name: 'revision-field-application-organization',
        adviser: 'revision-field-adviser-full-name',
        adviser_information: 'revision-field-adviser-full-name',
        faculty_adviser: 'revision-field-adviser-full-name',
        adviser_name: 'revision-field-adviser-full-name',
        adviser_full_name: 'revision-field-adviser-full-name',
        adviser_email: 'revision-field-adviser-email',
        adviser_school_id: 'revision-field-adviser-school-id',
        organization_type: 'revision-field-organizational-organization-type',
        school: 'revision-field-organizational-school',
        college_department: 'revision-field-organizational-school',
        date_organized: 'revision-field-organizational-date-organized',
        founded_date: 'revision-field-organizational-date-organized',
        purpose: 'revision-field-organizational-purpose',
        contact_person: 'revision-field-contact-contact-person',
        contact_no: 'revision-field-contact-contact-no',
        contact_email: 'revision-field-contact-contact-email',
        submitted_by: 'revision-field-application-submitted-by',
        submitted_by_display: 'revision-field-application-submitted-by',
    };
    const resolveRevisionTarget = (button) => {
        const explicitTargetId = button.getAttribute('data-revision-target-id') || '';
        const fieldKeyRaw = button.getAttribute('data-revision-field-key') || '';
        const fieldLabelRaw = button.getAttribute('data-revision-field-label') || '';
        const sectionKeyRaw = button.getAttribute('data-revision-section-key') || '';
        const fieldKey = normalizeKey(fieldKeyRaw).replace(/-/g, '_');
        const fieldLabel = normalizeKey(fieldLabelRaw).replace(/-/g, '_');
        const sectionKey = normalizeKey(sectionKeyRaw);
        const inferredKey = fieldKey || fieldLabel;
        const candidates = [
            explicitTargetId,
            sectionKey && inferredKey ? `revision-field-${sectionKey}-${normalizeKey(inferredKey)}` : '',
            inferredKey ? aliasToTargetId[inferredKey] || '' : '',
            inferredKey ? `revision-field-application-${normalizeKey(inferredKey)}` : '',
            inferredKey ? `revision-field-organizational-${normalizeKey(inferredKey)}` : '',
            inferredKey ? `revision-field-contact-${normalizeKey(inferredKey)}` : '',
            inferredKey ? `revision-field-adviser-${normalizeKey(inferredKey)}` : '',
        ].filter((id) => id !== '');

        for (const candidateId of candidates) {
            const target = document.getElementById(candidateId);
            if (target) {
                return target;
            }
        }

        return null;
    };
    actions.forEach((button) => {
        button.addEventListener('click', () => {
            const href = button.getAttribute('data-revision-href') || '';
            if (href) {
                window.location.href = href;
                return;
            }
            const target = resolveRevisionTarget(button);
            if (!target) return;

            const top = target.getBoundingClientRect().top + window.scrollY - headerOffset;
            window.scrollTo({ top: Math.max(top, 0), behavior: 'smooth' });
            target.classList.add(...flashClass);
            window.setTimeout(() => target.classList.remove(...flashClass), 1800);
        });
    });

    const form = document.querySelector('form[data-revision-edit-mode="1"]');
    if (!form) return;
    const saveBtn = document.getElementById('revision-save-btn');
    if (!saveBtn) return;
    const confirmModal = document.getElementById('revision-confirm-modal');
    const confirmList = document.getElementById('revision-confirm-list');
    const confirmCancel = document.getElementById('revision-confirm-cancel');
    const confirmSubmit = document.getElementById('revision-confirm-submit');
    let pendingChanges = [];
    let submitting = false;
    const draftKey = `profile-revision-draft:${form.querySelector('input[name="organization_id"]')?.value || 'self'}`;
    const hadSaveSuccess = {{ session('success') ? 'true' : 'false' }};

    const normalizeValue = (value) => String(value ?? '')
        .trim()
        .replace(/\s+/g, ' ');
    const normalizeDisplayValue = (value) => String(value ?? '')
        .trim()
        .replace(/\s+/g, ' ');
    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');

    const watched = Array.from(form.querySelectorAll('[data-revision-original]'))
        .map((el) => ({ el, original: el.getAttribute('data-revision-original') || '' }));
    const selectOriginals = Array.from(form.querySelectorAll('[data-revision-original-for]'));
    selectOriginals.forEach((source) => {
        const name = source.getAttribute('data-revision-original-for') || '';
        if (!name) return;
        const select = form.querySelector(`[name="${name}"]`);
        if (!select) return;
        watched.push({ el: select, original: source.getAttribute('value') || '' });
    });

    const requiredRevisionFormFields = @json($revisionEditableFields);
    const watchedForRevisionGate = Array.isArray(requiredRevisionFormFields) && requiredRevisionFormFields.length
        ? watched.filter(({ el }) => {
            const name = el.getAttribute('name');
            return Boolean(name && requiredRevisionFormFields.includes(name));
        })
        : watched;

    const allRevisedFieldsUpdated = () => {
        if (!Array.isArray(requiredRevisionFormFields) || requiredRevisionFormFields.length === 0) {
            return false;
        }
        if (watchedForRevisionGate.length === 0) {
            return false;
        }
        return watchedForRevisionGate.every(({ el, original }) => {
            const current = (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement || el instanceof HTMLSelectElement)
                ? el.value
                : '';
            return normalizeValue(current) !== normalizeValue(original);
        });
    };
    const readableFieldName = (el) => {
        const id = el.getAttribute('id');
        if (id) {
            const label = form.querySelector(`label[for="${id}"]`);
            if (label) {
                const cleaned = normalizeDisplayValue((label.textContent || '').replaceAll('*', ''));
                return cleaned || 'Field';
            }
        }
        const name = el.getAttribute('name') || 'field';
        return normalizeDisplayValue(name.replaceAll('_', ' ').replaceAll('*', ''));
    };
    const displayValueForElement = (el, rawValue) => {
        const normalized = normalizeDisplayValue(rawValue);
        if (el instanceof HTMLSelectElement) {
            const option = Array.from(el.options).find((o) => o.value === rawValue);
            const label = normalizeDisplayValue(option?.textContent || normalized);
            return label !== '' ? label : '—';
        }
        return normalized !== '' ? normalized : '—';
    };
    const summarizeMeaningfulChanges = () => watchedForRevisionGate
        .map(({ el, original }) => {
            const currentRaw = (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement || el instanceof HTMLSelectElement)
                ? el.value
                : '';
            if (normalizeValue(currentRaw) === normalizeValue(original)) return null;
            return {
                field: readableFieldName(el),
                previous: displayValueForElement(el, original),
                next: displayValueForElement(el, currentRaw),
            };
        })
        .filter((row) => row !== null);
    const writeDraftState = () => {
        const payload = {};
        watched.forEach(({ el }) => {
            const name = el.getAttribute('name');
            if (!name) return;
            const current = (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement || el instanceof HTMLSelectElement)
                ? el.value
                : '';
            payload[name] = current;
        });
        window.localStorage.setItem(draftKey, JSON.stringify(payload));
    };
    const hydrateDraftState = () => {
        if (hadSaveSuccess) {
            window.localStorage.removeItem(draftKey);
            return;
        }
        let parsed = null;
        try {
            parsed = JSON.parse(window.localStorage.getItem(draftKey) || 'null');
        } catch {
            parsed = null;
        }
        if (!parsed || typeof parsed !== 'object') return;
        watched.forEach(({ el, original }) => {
            const name = el.getAttribute('name');
            if (!name || !Object.prototype.hasOwnProperty.call(parsed, name)) return;
            const currentRaw = (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement || el instanceof HTMLSelectElement)
                ? el.value
                : '';
            // Prefer explicit in-page value (e.g., old()) over draft when already changed.
            if (normalizeValue(currentRaw) !== normalizeValue(original)) return;
            const draftValue = parsed[name];
            if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement || el instanceof HTMLSelectElement) {
                el.value = String(draftValue ?? '');
            }
        });
    };
    const closeConfirmModal = () => {
        if (!confirmModal) return;
        confirmModal.classList.add('hidden');
        confirmModal.classList.remove('flex');
    };
    const openConfirmModal = (changes) => {
        if (!confirmModal || !confirmList) return;
        const blocks = changes.map((change) => `
            <article class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5 shadow-sm">
                <p class="text-sm font-semibold text-slate-900">${escapeHtml(change.field)}</p>
                <div class="mt-2 space-y-1.5">
                    <p class="text-xs text-slate-600">Previous: <span class="font-medium text-slate-800">${escapeHtml(change.previous)}</span></p>
                    <p class="text-xs text-sky-700">New: <span class="font-semibold text-sky-800">${escapeHtml(change.next)}</span></p>
                </div>
            </article>
        `).join('');
        confirmList.innerHTML = blocks;
        confirmModal.classList.remove('hidden');
        confirmModal.classList.add('flex');
    };

    const revisionFieldCards = Array.from(form.querySelectorAll('[data-profile-revision-field]'));
    const refreshUpdatedBadges = () => {
        revisionFieldCards.forEach((wrap) => {
            const key = wrap.getAttribute('data-profile-revision-field') || '';
            const input = key ? form.querySelector(`[name="${key}"]`) : null;
            const badge = wrap.querySelector('[data-revision-updated-badge]');
            if (!input || !badge || !(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement || input instanceof HTMLSelectElement)) {
                return;
            }
            let original = '';
            if (input.hasAttribute('data-revision-original')) {
                original = input.getAttribute('data-revision-original') || '';
            } else {
                const hiddenOrig = form.querySelector(`[data-revision-original-for="${key}"]`);
                original = hiddenOrig ? (hiddenOrig.getAttribute('value') || '') : '';
            }
            const updated = normalizeValue(input.value) !== normalizeValue(original);
            badge.classList.toggle('hidden', !updated);
            badge.classList.toggle('inline-flex', updated);
            wrap.classList.toggle('ring-1', updated);
            wrap.classList.toggle('ring-sky-300/70', updated);
            wrap.classList.toggle('bg-sky-50/40', updated);
        });
    };

    const syncSaveState = () => {
        const saveHelp = document.getElementById('revision-save-all-fields-hint');
        const enabled = !submitting && allRevisedFieldsUpdated();
        saveBtn.disabled = !enabled;
        if (saveHelp) {
            if (!enabled && !submitting) {
                saveHelp.classList.remove('hidden');
            } else {
                saveHelp.classList.add('hidden');
            }
        }
        refreshUpdatedBadges();
    };

    watched.forEach(({ el }) => {
        el.addEventListener('input', syncSaveState);
        el.addEventListener('change', syncSaveState);
        el.addEventListener('input', writeDraftState);
        el.addEventListener('change', writeDraftState);
    });
    hydrateDraftState();
    refreshUpdatedBadges();
    form.addEventListener('submit', (event) => {
        if (submitting) {
            event.preventDefault();
            return;
        }
        if (!allRevisedFieldsUpdated()) {
            event.preventDefault();
            syncSaveState();
            return;
        }
        const changes = summarizeMeaningfulChanges();
        if (changes.length === 0) {
            event.preventDefault();
            syncSaveState();
            return;
        }
        event.preventDefault();
        pendingChanges = changes;
        openConfirmModal(changes);
    });
    confirmCancel?.addEventListener('click', () => {
        if (submitting) return;
        closeConfirmModal();
        syncSaveState();
    });
    confirmSubmit?.addEventListener('click', () => {
        if (submitting || pendingChanges.length === 0) return;
        submitting = true;
        saveBtn.disabled = true;
        if (confirmSubmit) {
            confirmSubmit.disabled = true;
            confirmSubmit.textContent = 'Saving...';
            confirmSubmit.classList.add('opacity-75', 'cursor-not-allowed');
        }
        if (confirmCancel) {
            confirmCancel.disabled = true;
            confirmCancel.classList.add('opacity-60', 'cursor-not-allowed');
        }
        window.localStorage.removeItem(draftKey);
        form.submit();
    });
    syncSaveState();
})();
</script>

@endsection
