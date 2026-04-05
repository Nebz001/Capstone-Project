@extends('layouts.organization')

@section('title', 'Organization Dashboard — NU Lipa SDAO')

@section('content')

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">

    {{-- ── Page Header ──────────────────────────────────────────────── --}}
    <header class="mb-8">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#003E9F]">
                    NU Lipa · SDAO Portal
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
                Organization Officer
            </span>
        </div>
    </header>

    {{-- ── Two-column Grid ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[7fr_3fr] lg:items-start">

        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- LEFT  — Organization Activity Calendar (main focus)       --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-300/40">

                {{-- Card header --}}
                <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
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
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-b border-slate-100 px-6 py-3">
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
                <div class="px-5 py-4 sm:px-6">
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
        <div class="flex flex-col gap-6">

            {{-- ── Manage Organization ─────────────────────────────── --}}
            <a
                href="{{ route('organizations.manage') }}"
                class="group flex items-start gap-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/40 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-[#003E9F]/15"
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
                href="{{ route('organizations.activity-submission') }}"
                class="group flex items-start gap-5 rounded-3xl border border-[#E7C663]/60 bg-gradient-to-br from-[#FFF8DF] via-[#FFFBF0] to-[#FFFEF8] p-6 shadow-xl shadow-amber-200/50 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-[#003E9F]/15"
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
                href="{{ route('organizations.submit-report') }}"
                class="group flex items-start gap-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/40 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-[#003E9F]/15"
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

            {{-- ── Approval Workflow Status ─────────────────────────── --}}
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/40">
                <h3 class="text-sm font-bold text-slate-900">Approval Workflow</h3>
                <p class="mt-0.5 text-xs text-slate-500">Current document routing progress.</p>

                @php
                    $stages = [
                        ['name' => 'President',      'status' => 'completed'],
                        ['name' => 'Adviser',        'status' => 'current'],
                        ['name' => 'Program Chair',  'status' => 'pending'],
                        ['name' => 'Dean',           'status' => 'pending'],
                        ['name' => 'Acad. Director', 'status' => 'pending'],
                        ['name' => 'Exec. Director', 'status' => 'pending'],
                    ];
                @endphp

                <div class="mt-5 flex flex-wrap items-start justify-center gap-x-1 gap-y-4 sm:flex-nowrap sm:justify-between">

                    @foreach ($stages as $stage)
                        <div class="flex flex-col items-center" style="min-width: 52px;">

                            @if ($stage['status'] === 'completed')
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500 text-white shadow-sm">
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                            @elseif ($stage['status'] === 'current')
                                <div class="relative flex h-8 w-8 items-center justify-center rounded-full border-2 border-[#003E9F] bg-[#003E9F]/5">
                                    <span class="absolute h-3 w-3 animate-ping rounded-full bg-[#003E9F] opacity-25"></span>
                                    <span class="h-3 w-3 rounded-full bg-[#003E9F]"></span>
                                </div>
                            @else
                                <div class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-slate-200 bg-slate-50">
                                    <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                                </div>
                            @endif

                            <p class="mt-1.5 max-w-[56px] text-center text-[9px] font-semibold leading-tight
                                {{ $stage['status'] === 'completed' ? 'text-emerald-600' : ($stage['status'] === 'current' ? 'text-[#003E9F]' : 'text-slate-400') }}">
                                {{ $stage['name'] }}
                            </p>
                        </div>

                        @if (!$loop->last)
                            <div class="mt-4 hidden h-0.5 flex-1 sm:block {{ $stage['status'] === 'completed' ? 'bg-emerald-300' : 'bg-slate-200' }}"></div>
                        @endif
                    @endforeach

                </div>

                <p class="mt-4 rounded-xl border border-slate-100 bg-slate-50 px-4 py-2.5 text-xs leading-5 text-slate-600">
                    <span class="font-semibold text-slate-800">Status:</span>
                    Document approved by President, forwarded to Adviser.
                </p>
            </div>

        </div>
        {{-- ── End right column ──────────────────────────────────── --}}

    </div>
</div>

@endsection
