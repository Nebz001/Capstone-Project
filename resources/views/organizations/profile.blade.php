@extends('layouts.organization')

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
        ? ($activeApplication instanceof \App\Models\OrganizationRenewal
            ? $activeApplication->renewal_notes
            : $activeApplication->registration_notes)
        : null;
@endphp

<div class="mx-auto max-w-4xl px-4 pb-24 pt-8 sm:px-6 lg:px-10">

    <header class="mb-8">
        <a href="{{ route('organizations.manage') }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
            Back to Manage Organization
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
                        href="{{ route('organizations.profile', ['edit' => 1]) }}"
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

        @if ($organization && ! $editing && ! $canEditProfile)
            <x-feedback.blocked-message :message="$profileEditBlockedMessage" class="mt-4" />
        @endif
    </header>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm" role="alert">
            {{ session('success') }}
        </div>
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

            <div class="space-y-6">

                <section aria-labelledby="profile-section-status-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden !border-slate-100 !shadow-sm shadow-slate-200/30">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-section-status-heading" class="text-base font-bold text-slate-900">Status Information</h2>
                            <p class="mt-0.5 text-xs font-medium leading-snug text-slate-600">Accreditation status managed by SDAO.</p>
                        </div>
                        <div class="bg-white px-6 pt-6 pb-10">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Organization Status</p>
                                    <div class="mt-2">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border {{ $color['border'] }} {{ $color['bg'] }} px-3 py-1 text-xs font-semibold {{ $color['text'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $color['dot'] }}" aria-hidden="true"></span>
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Last Updated</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">
                                        {{ $organization->updated_at?->format('F j, Y — g:i A') ?? '—' }}
                                    </p>
                                </div>
                                @if ($workflowDisplay)
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5 sm:col-span-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Latest application review status</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $workflowDisplay }}</p>
                                    </div>
                                @endif
                                @if ($organization->profile_revision_notes)
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5 sm:col-span-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">SDAO remarks / revision notes</p>
                                        <p class="mt-2 text-sm font-medium leading-relaxed text-slate-900 whitespace-pre-wrap">{{ $organization->profile_revision_notes }}</p>
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
                    <x-ui.card padding="p-0" class="overflow-hidden !border-slate-100 !shadow-sm shadow-slate-200/30">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-section-registration-heading" class="text-base font-bold text-slate-900">Registration Information</h2>
                            <p class="mt-0.5 text-xs font-medium leading-snug text-slate-600">Details from your latest registration or renewal submission.</p>
                        </div>
                        <div class="bg-white px-6 pt-6 pb-10">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Application type</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $applicationTypeLabel ?? '—' }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Academic year</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $activeApplication?->academic_year ?? '—' }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Submission date</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">
                                        {{ $activeApplication?->submission_date?->format('F j, Y') ?? '—' }}
                                    </p>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5 sm:col-span-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Submission notes</p>
                                    <p class="mt-2 text-sm font-medium leading-relaxed text-slate-900 whitespace-pre-wrap">{{ $applicationNotes ? trim($applicationNotes) : '—' }}</p>
                                </div>
                            </div>
                        </div>
                    </x-ui.card>
                </section>

                <section aria-labelledby="profile-section-contact-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden !border-slate-100 !shadow-sm shadow-slate-200/30">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-section-contact-heading" class="text-base font-bold text-slate-900">Contact Information</h2>
                            <p class="mt-0.5 text-xs font-medium leading-snug text-slate-600">Contact person on file for your latest application.</p>
                        </div>
                        <div class="bg-white px-6 pt-6 pb-10">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Contact person</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $activeApplication?->contact_person ?? '—' }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Contact number</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $activeApplication?->contact_no ?? '—' }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5 sm:col-span-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Contact email</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $activeApplication?->contact_email ?? '—' }}</p>
                                </div>
                            </div>
                        </div>
                    </x-ui.card>
                </section>

                <section aria-labelledby="profile-section-org-details-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden !border-slate-100 !shadow-sm shadow-slate-200/30">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-section-org-details-heading" class="text-base font-bold text-slate-900">Organization Details</h2>
                            <p class="mt-0.5 text-xs font-medium leading-snug text-slate-600">Core information about your organization.</p>
                        </div>
                        <div class="bg-white px-6 pt-6 pb-10">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Organization Name</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $organization->organization_name }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Organization Type</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $typeLabels[$organization->organization_type] ?? $organization->organization_type }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">College / Department</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $organization->college_department }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Founded Date</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">
                                        {{ $organization->founded_date?->format('F j, Y') ?? '—' }}
                                    </p>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5 sm:col-span-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Purpose</p>
                                    <p class="mt-2 text-sm font-medium leading-relaxed text-slate-900 whitespace-pre-wrap">{{ $organization->purpose ?? '—' }}</p>
                                </div>
                            </div>
                        </div>
                    </x-ui.card>
                </section>

                <section aria-labelledby="profile-section-adviser-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden !border-slate-100 !shadow-sm shadow-slate-200/30">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-section-adviser-heading" class="text-base font-bold text-slate-900">Adviser Information</h2>
                            <p class="mt-0.5 text-xs font-medium leading-snug text-slate-600">Faculty adviser assigned to this organization.</p>
                        </div>
                        <div class="bg-white px-6 pt-6 pb-10">
                            <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5 sm:max-w-xl">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Adviser Name</p>
                                <p class="mt-2 text-sm font-semibold text-slate-900">{{ $organization->adviser_name ?? '—' }}</p>
                            </div>
                        </div>
                    </x-ui.card>
                </section>

            </div>

        @else

            <form method="POST" action="{{ route('organizations.profile.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                <section aria-labelledby="profile-edit-section-status-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden !border-slate-100 !shadow-sm shadow-slate-200/30">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-edit-section-status-heading" class="text-base font-bold text-slate-900">Status Information</h2>
                            <p class="mt-0.5 text-xs font-medium leading-snug text-slate-600">Read-only — managed by SDAO.</p>
                        </div>
                        <div class="bg-white px-6 pt-6 pb-10">
                            <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 sm:p-5 sm:max-w-md">
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

                <section aria-labelledby="profile-edit-section-org-details-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden !border-slate-100 !shadow-sm shadow-slate-200/30">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-edit-section-org-details-heading" class="text-base font-bold text-slate-900">Organization Details</h2>
                            <p class="mt-0.5 text-xs font-medium leading-snug text-slate-600">Update your organization’s core information.</p>
                        </div>
                    <div class="bg-white px-6 pt-6 pb-10">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <x-forms.label for="organization_name" required>Organization Name</x-forms.label>
                                <x-forms.input
                                    id="organization_name"
                                    name="organization_name"
                                    type="text"
                                    :value="old('organization_name', $organization->organization_name)"
                                    required
                                />
                                @error('organization_name') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                            </div>

                            <div>
                                <x-forms.label for="organization_type" required>Organization Type</x-forms.label>
                                <x-forms.select id="organization_type" name="organization_type" required>
                                    <option value="" disabled>Select type</option>
                                    <option value="co_curricular" @selected(old('organization_type', $organization->organization_type) === 'co_curricular' || old('organization_type', $organization->organization_type) === 'CO_CURRICULAR')>
                                        Co-Curricular Organization
                                    </option>
                                    <option value="extra_curricular" @selected(old('organization_type', $organization->organization_type) === 'extra_curricular' || old('organization_type', $organization->organization_type) === 'EXTRA_CURRICULAR')>
                                        Extra-Curricular Organization / Interest Club
                                    </option>
                                </x-forms.select>
                                @error('organization_type') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                            </div>

                            <div>
                                <x-forms.label for="college_department" required>College / Department</x-forms.label>
                                <x-forms.input
                                    id="college_department"
                                    name="college_department"
                                    type="text"
                                    :value="old('college_department', $organization->college_department)"
                                    required
                                />
                                @error('college_department') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                            </div>

                            <div>
                                <x-forms.label for="founded_date">Founded Date</x-forms.label>
                                <x-forms.input
                                    id="founded_date"
                                    name="founded_date"
                                    type="date"
                                    :value="old('founded_date', $organization->founded_date?->format('Y-m-d'))"
                                />
                                @error('founded_date') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <x-forms.label for="purpose" required>Purpose</x-forms.label>
                                <x-forms.textarea id="purpose" name="purpose" rows="4" required>{{ old('purpose', $organization->purpose) }}</x-forms.textarea>
                                @error('purpose') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                            </div>
                        </div>
                    </div>
                </x-ui.card>
                </section>

                <section aria-labelledby="profile-edit-section-adviser-heading">
                    <x-ui.card padding="p-0" class="overflow-hidden !border-slate-100 !shadow-sm shadow-slate-200/30">
                        <div class="border-b border-slate-100 bg-white px-6 py-4">
                            <h2 id="profile-edit-section-adviser-heading" class="text-base font-bold text-slate-900">Adviser Information</h2>
                            <p class="mt-0.5 text-xs font-medium leading-snug text-slate-600">Update the faculty adviser for this organization.</p>
                        </div>
                        <div class="bg-white px-6 pt-6 pb-10">
                            <div class="max-w-xl">
                                <x-forms.label for="adviser_name">Adviser Name</x-forms.label>
                                <x-forms.input
                                    id="adviser_name"
                                    name="adviser_name"
                                    type="text"
                                    placeholder="e.g., Prof. Juan Dela Cruz"
                                    :value="old('adviser_name', $organization->adviser_name)"
                                />
                                @error('adviser_name') <x-forms.error>{{ $message }}</x-forms.error> @enderror
                            </div>
                        </div>
                    </x-ui.card>
                </section>

                <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <a
                        href="{{ route('organizations.profile') }}"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20"
                    >
                        Cancel
                    </a>
                    <x-ui.button type="submit">
                        Save Changes
                    </x-ui.button>
                </div>
            </form>

        @endif

    @endif

</div>

@endsection
