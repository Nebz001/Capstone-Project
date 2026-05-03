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
        $dashWorkflows = is_array($dashboardWorkflows ?? null) ? $dashboardWorkflows : [];
        $hasAnyWorkflow = count($dashWorkflows) > 0;
        $dashWorkflowCount = count($dashWorkflows);
    @endphp

    <section class="mb-6" aria-labelledby="dashboard-submission-status-heading">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-300/35">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 id="dashboard-submission-status-heading" class="text-base font-bold text-slate-900">Submission Status &amp; Required Actions</h2>
                <p class="mt-1 text-xs text-slate-500">Track your submitted forms and complete required revisions in one place.</p>
            </div>

            @if (! $hasAnyWorkflow)
                <div class="px-5 py-5 sm:px-6">
                    <p class="text-sm font-semibold text-slate-900">No submitted documents yet.</p>
                    <p class="mt-1 text-xs text-slate-600">Start registration to submit organization documents and track approval routing here.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('organizations.register') }}{{ $saQ }}" class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#00327F]">Start registration</a>
                        <a href="{{ route('organizations.manage') }}{{ $saQ }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">Manage Organization</a>
                    </div>
                </div>
            @else
                <div class="px-5 py-4 sm:px-6">
                    @if ($dashWorkflowCount > 1)
                        <div class="mb-5">
                            <label for="dashboard-workflow-selector" class="mb-1.5 block text-xs font-semibold text-slate-700">View workflow for</label>
                            <select
                                id="dashboard-workflow-selector"
                                class="block w-full max-w-xl rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-sky-500 focus:outline-none focus:ring-4 focus:ring-sky-500/15"
                            >
                                @foreach ($dashWorkflows as $w)
                                    @if (! empty($w['id']))
                                        <option value="{{ $w['id'] }}" @selected(! empty($w['selected']))>
                                            {{ $w['selector_option_text'] ?? (($w['selector_label'] ?? $w['title'] ?? '').' — '.($w['status_label'] ?? '')) }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @foreach ($dashWorkflows as $w)
                        @if (empty($w['id']))
                            @continue
                        @endif
                        @php
                            $wSelected = ! empty($w['selected']);
                            $infoRevisions = is_array($w['info_revisions'] ?? null) ? $w['info_revisions'] : [];
                            $fileRevisions = is_array($w['file_revisions'] ?? null) ? $w['file_revisions'] : [];
                            $updatedInfo = is_array($w['info_updated_under_review'] ?? null) ? $w['info_updated_under_review'] : [];
                            $updatedFiles = is_array($w['file_updated_under_review'] ?? null) ? $w['file_updated_under_review'] : [];
                            $wTitle = $w['title'] ?? 'Submission';
                        @endphp
                        <div
                            data-dashboard-workflow-card="{{ $w['id'] }}"
                            class="space-y-4 {{ $dashWorkflowCount > 1 && ! $wSelected ? 'hidden' : '' }}"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <p class="text-sm font-bold text-slate-900">{{ $wTitle }}</p>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $w['status_badge_class'] ?? 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                                    {{ $w['status_label'] ?? 'Status' }}
                                </span>
                            </div>

                            <x-submission-progress-card
                                variant="embed"
                                :document-label="strtoupper($wTitle.' · Approval Routing')"
                                :stages="$w['stages'] ?? []"
                                :summary="$w['status_message'] ?? ''"
                            />

                            @include('organizations.partials.dashboard-revision-cards', [
                                '__infoRevisions' => $infoRevisions,
                                '__fileRevisions' => $fileRevisions,
                                '__updatedInfo' => $updatedInfo,
                                '__updatedFiles' => $updatedFiles,
                            ])

                            @if (! empty($w['details_url']))
                                <div>
                                    <a href="{{ $w['details_url'] }}?from=dashboard" class="inline-flex items-center gap-1 text-xs font-semibold text-[#003E9F] underline-offset-2 hover:underline">
                                        View Submission Details
                                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($dashWorkflowCount > 1)
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            var selector = document.getElementById('dashboard-workflow-selector');
                            var cards = document.querySelectorAll('[data-dashboard-workflow-card]');
                            if (!selector || !cards.length) return;

                            function showSelectedWorkflow() {
                                var selectedId = selector.value;
                                cards.forEach(function (card) {
                                    var isMatch = card.getAttribute('data-dashboard-workflow-card') === selectedId;
                                    card.classList.toggle('hidden', !isMatch);
                                });
                                try {
                                    var url = new URL(window.location.href);
                                    url.searchParams.set('workflow', selectedId);
                                    window.history.replaceState({}, '', url);
                                } catch (e) {}
                            }

                            selector.addEventListener('change', showSelectedWorkflow);
                        });
                    </script>
                @endif
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
