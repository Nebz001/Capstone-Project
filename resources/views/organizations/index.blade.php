@extends('layouts.organization-portal')

@section('title', 'Organization Dashboard — NU Lipa Student Development and Activities Office')

@section('content')

@php
  $saOrgId = isset($superAdminOrganizationId) && $superAdminOrganizationId ? (int) $superAdminOrganizationId : null;
  $saQ = $saOrgId ? '?organization_id='.$saOrgId : '';
@endphp

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">

    {{-- ── Page Header ──────────────────────────────────────────────── --}}
    <header class="mb-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#003E9F]">
                    NU Lipa · Student Development and Activities Office Portal
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    Organization Dashboard
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    @auth
                        Welcome, {{ auth()->user()->first_name ?? 'Officer' }} &mdash;
                    @endauth
                    {{ now()->format('l, F j, Y') }}
                </p>
            </div>
            <span class="inline-flex w-fit items-center gap-1.5 rounded-full border border-[#E7C663]/80 bg-[#FFF8DF] px-3 py-1.5 text-xs font-semibold text-[#6A5200]">
                <span class="h-2 w-2 rounded-full bg-[#F5C400]" aria-hidden="true"></span>
                @if (auth()->user()?->isSuperAdmin())
                    Super Admin
                @else
                    Organization Officer
                @endif
            </span>
        </div>
    </header>

    @php
        $sd = $submissionDashboard ?? ['empty' => true];
        $sdEmpty = ! empty($sd['empty']);
        $infoRevisions = is_array($sd['info_revisions'] ?? null) ? $sd['info_revisions'] : [];
        $fileRevisions = is_array($sd['file_revisions'] ?? null) ? $sd['file_revisions'] : [];
        $updatedInfo = is_array($sd['info_updated_under_review'] ?? null) ? $sd['info_updated_under_review'] : [];
        $updatedFiles = is_array($sd['file_updated_under_review'] ?? null) ? $sd['file_updated_under_review'] : [];
    @endphp

    <section class="mb-6" aria-labelledby="dashboard-submission-status-heading">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-300/35">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 id="dashboard-submission-status-heading" class="text-base font-bold text-slate-900">Submission Status &amp; Required Actions</h2>
                <p class="mt-1 text-xs text-slate-500">Track your submitted forms and complete required revisions in one place.</p>
            </div>

            @if ($sdEmpty)
                <div class="px-5 py-5 sm:px-6">
                    <p class="text-sm font-semibold text-slate-900">No registration submission on file yet</p>
                    <p class="mt-1 text-xs text-slate-600">Start registration to view approval routing and revision requests in this dashboard card.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('organizations.register') }}{{ $saQ }}" class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#00327F]">Start registration</a>
                        <a href="{{ route('organizations.manage') }}{{ $saQ }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">Manage Organization</a>
                    </div>
                </div>
            @else
                <div class="space-y-4 px-5 py-4.5 sm:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="text-sm font-bold text-slate-900">{{ $sd['title'] ?? 'Organization Registration' }}</p>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $sd['status_badge_class'] ?? 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                            {{ $sd['status_label'] ?? 'Status' }}
                        </span>
                    </div>

                    <x-submission-progress-card
                        variant="embed"
                        :document-label="strtoupper(($sd['title'] ?? 'Organization Registration').' · Approval Routing')"
                        :stages="$sd['stages'] ?? []"
                        :summary="$sd['status_message'] ?? ''"
                    />

                    @php
                        $hasInfoPending = count($infoRevisions) > 0;
                        $hasInfoUpdated = count($updatedInfo) > 0;
                        $hasFilePending = count($fileRevisions) > 0;
                        $hasFileUpdated = count($updatedFiles) > 0;
                        $showInfoPanel = $hasInfoPending || $hasInfoUpdated;
                        $showFilePanel = $hasFilePending || $hasFileUpdated;
                    @endphp

                    @if ($showInfoPanel || $showFilePanel)
                        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                            @if ($showInfoPanel)
                            <div class="rounded-xl border bg-white px-3.5 py-3 {{ $hasInfoPending ? 'border-yellow-300 border-l-4 border-l-yellow-400' : 'border-emerald-300 border-l-4 border-l-emerald-400' }}">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[11px] font-bold uppercase tracking-wide {{ $hasInfoPending ? 'text-yellow-900' : 'text-emerald-800' }}">
                                        {{ $hasInfoPending ? 'Information to Update' : 'Information Updated' }}
                                    </p>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $hasInfoPending ? 'bg-yellow-100 text-yellow-800' : 'bg-emerald-100 text-emerald-800' }}">
                                        @if ($hasInfoPending)
                                            Pending
                                        @else
                                            <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                            Updated
                                        @endif
                                    </span>
                                </div>
                                <ul class="mt-2 space-y-1.5">
                                    @foreach (($hasInfoPending ? $infoRevisions : $updatedInfo) as $item)
                                        <li class="rounded-md px-2 py-1 text-xs {{ $hasInfoPending ? 'text-yellow-950 bg-yellow-50/60' : 'text-emerald-900 bg-emerald-50/60' }}">
                                            <a href="{{ $item['href'] ?? '#' }}" class="inline-flex w-full items-start gap-1 text-left">
                                                <span class="font-semibold underline underline-offset-2">{{ $item['field'] ?? 'Field' }}</span>
                                                @if ($hasInfoPending)
                                                    <span>— {{ $item['note'] ?? '' }}</span>
                                                @else
                                                    @php
                                                        $oldVal = trim((string) ($item['old_value'] ?? ''));
                                                        $newVal = trim((string) ($item['new_value'] ?? ''));
                                                    @endphp
                                                    <span>— {{ $oldVal !== '' || $newVal !== '' ? ($oldVal !== '' ? $oldVal.' → '.$newVal : $newVal) : 'Updated value submitted' }}</span>
                                                @endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                                @if (! $hasInfoPending && $hasInfoUpdated)
                                    <p class="mt-2 text-xs text-emerald-800">Your updated information has been submitted and is now awaiting SDAO review.</p>
                                @endif
                            </div>
                            @endif

                            @if ($showFilePanel)
                            <div class="rounded-xl border bg-white px-3.5 py-3 {{ $hasFilePending ? 'border-yellow-300 border-l-4 border-l-yellow-400' : 'border-emerald-300 border-l-4 border-l-emerald-400' }}">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[11px] font-bold uppercase tracking-wide {{ $hasFilePending ? 'text-yellow-900' : 'text-emerald-800' }}">
                                        {{ $hasFilePending ? 'Files to Replace' : 'Files Updated' }}
                                    </p>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $hasFilePending ? 'bg-yellow-100 text-yellow-800' : 'bg-emerald-100 text-emerald-800' }}">
                                        @if ($hasFilePending)
                                            Pending
                                        @else
                                            <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                            Updated
                                        @endif
                                    </span>
                                </div>
                                <ul class="mt-2 space-y-1.5">
                                    @foreach (($hasFilePending ? $fileRevisions : $updatedFiles) as $item)
                                        <li class="rounded-md px-2 py-1 text-xs {{ $hasFilePending ? 'text-yellow-950 bg-yellow-50/60' : 'text-emerald-900 bg-emerald-50/60' }}">
                                            <a href="{{ $item['href'] ?? '#' }}" class="inline-flex w-full items-start gap-1 text-left">
                                                <span class="font-semibold underline underline-offset-2">{{ $item['field'] ?? 'File' }}</span>
                                                @if ($hasFilePending)
                                                    <span>— {{ $item['note'] ?? '' }}</span>
                                                @else
                                                    <span>— {{ $item['file_name'] ?? 'New file uploaded' }}</span>
                                                @endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                                @if (! $hasFilePending && $hasFileUpdated)
                                    <p class="mt-2 text-xs text-emerald-800">Your updated file has been submitted and is now awaiting SDAO review.</p>
                                @endif
                            </div>
                            @endif
                        </div>
                    @endif

                </div>
            @endif
        </div>
    </section>

    {{-- ── Two-column Grid ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[7fr_3fr] lg:items-start">

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- LEFT  — Organization Activity Calendar (main focus)       --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-300/35">

                {{-- Card header --}}
                <div class="flex flex-col gap-2.5 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">
                            Organization Activity Calendar
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Shared campus schedule — check dates before filing proposals.
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#003E9F]/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-[#003E9F]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#003E9F]" aria-hidden="true"></span>
                        Live
                    </span>
                </div>

                {{-- Legend (pending vs finalized / scheduled only) --}}
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-slate-100 px-5 py-3">
                    <span class="flex items-center gap-2 text-xs text-slate-600">
                        <span class="inline-block h-3 w-3 rounded border-l-[3px] border-amber-400 bg-amber-100" aria-hidden="true"></span>
                        Pending for Approval
                    </span>
                    <span class="flex items-center gap-2 text-xs text-slate-600">
                        <span class="inline-block h-3 w-3 rounded border-l-[3px] border-blue-400 bg-blue-100" aria-hidden="true"></span>
                        Scheduled
                    </span>
                </div>

                {{-- FullCalendar mount --}}
                <div class="px-4 py-4 sm:px-5">
                    <div id="activity-calendar"></div>
                </div>

                <script id="calendar-events-data" type="application/json">
                    @json($calendarEvents ?? [])
                </script>

            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- RIGHT — Quick actions & organization modules              --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="flex flex-col gap-4">

            {{-- ── Manage Organization ─────────────────────────────── --}}
            <a
                href="{{ route('organizations.manage') }}{{ $saQ }}"
                class="group flex items-start gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-300/40 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-[#003E9F]/15"
            >
                <div class="flex h-12 w-12 flex-none items-center justify-center rounded-2xl bg-[#003E9F]/10 text-[#003E9F] transition group-hover:bg-[#003E9F]/15">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-bold text-slate-900 transition group-hover:text-[#003E9F]">
                        Manage Organization
                    </h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Register, renew, and manage your organization's profile and documents.
                    </p>
                    <span class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-[#003E9F] transition-all duration-150 group-hover:gap-2">
                        Open
                        <svg class="h-3.5 w-3.5 transition-transform duration-150 group-hover:translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </span>
                </div>
            </a>

            {{-- ── Activity Submission (accent / main action) ──────── --}}
            <a
                href="{{ route('organizations.activity-submission') }}{{ $saQ }}"
                class="group flex items-start gap-4 rounded-3xl border border-[#E7C663]/60 bg-linear-to-br from-[#FFF8DF] via-[#FFFBF0] to-[#FFFEF8] p-5 shadow-xl shadow-amber-200/50 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-[#003E9F]/15"
            >
                <div class="flex h-12 w-12 flex-none items-center justify-center rounded-2xl bg-[#F5C400]/25 text-[#8A6500] transition group-hover:bg-[#F5C400]/35">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-900 transition group-hover:text-[#003E9F]">
                            Activity Submission
                        </h3>
                        <span class="inline-flex items-center rounded-full bg-[#F5C400]/30 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#7A5900]">
                            Main
                        </span>
                    </div>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Submit your Calendar of Activities and prepare Activity Proposals for review.
                    </p>
                    <span class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-[#8A6500] transition-all duration-150 group-hover:gap-2">
                        Open
                        <svg class="h-3.5 w-3.5 transition-transform duration-150 group-hover:translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </span>
                </div>
            </a>

            {{-- ── Submit Report ────────────────────────────────────── --}}
            <a
                href="{{ route('organizations.submit-report') }}{{ $saQ }}"
                class="group flex items-start gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-300/40 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-[#003E9F]/15"
            >
                <div class="flex h-12 w-12 flex-none items-center justify-center rounded-2xl bg-[#003E9F]/10 text-[#003E9F] transition group-hover:bg-[#003E9F]/15">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-bold text-slate-900 transition group-hover:text-[#003E9F]">
                        Submit Report
                    </h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Submit activity and after-event reports for completed organization events.
                    </p>
                    <span class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-[#003E9F] transition-all duration-150 group-hover:gap-2">
                        Open
                        <svg class="h-3.5 w-3.5 transition-transform duration-150 group-hover:translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </span>
                </div>
            </a>

        </div>
        {{-- ── End right column ──────────────────────────────────── --}}

    </div>
</div>

@endsection
