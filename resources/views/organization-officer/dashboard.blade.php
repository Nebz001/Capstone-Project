@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Overview of your organization submissions and pending actions.')

@section('content')

@php
  $userName      = auth()->check() ? (auth()->user()->first_name ?? 'Officer') : 'Maria';
  $orgName       = 'PSITS – NU Lipa Chapter';
  $today         = now()->format('l, F j, Y');

  $summary = [
    [
      'title'       => 'Pending Submissions',
      'count'       => 3,
      'description' => 'Awaiting reviewer action',
      'color'       => 'amber',
      'bg'          => 'bg-amber-50',
      'border'      => 'border-amber-200',
      'iconBg'      => 'bg-amber-100',
      'iconText'    => 'text-amber-600',
      'countText'   => 'text-amber-700',
    ],
    [
      'title'       => 'Approved Documents',
      'count'       => 12,
      'description' => 'Fully processed this semester',
      'color'       => 'emerald',
      'bg'          => 'bg-emerald-50',
      'border'      => 'border-emerald-200',
      'iconBg'      => 'bg-emerald-100',
      'iconText'    => 'text-emerald-600',
      'countText'   => 'text-emerald-700',
    ],
    [
      'title'       => 'Needs Revision',
      'count'       => 1,
      'description' => 'Returned by reviewer',
      'color'       => 'rose',
      'bg'          => 'bg-rose-50',
      'border'      => 'border-rose-200',
      'iconBg'      => 'bg-rose-100',
      'iconText'    => 'text-rose-600',
      'countText'   => 'text-rose-700',
    ],
    [
      'title'       => 'Upcoming Activities',
      'count'       => 5,
      'description' => 'Scheduled this month',
      'color'       => 'blue',
      'bg'          => 'bg-blue-50',
      'border'      => 'border-blue-200',
      'iconBg'      => 'bg-blue-100',
      'iconText'    => 'text-blue-600',
      'countText'   => 'text-blue-700',
    ],
  ];

  $recentSubmissions = [
    [
      'title'    => 'Annual Registration 2025',
      'type'     => 'Registration',
      'date'     => 'Jan 15, 2025',
      'reviewer' => 'Program Chair',
      'status'   => 'Under Review',
    ],
    [
      'title'    => 'Sportsfest 2025 Proposal',
      'type'     => 'Activity Request',
      'date'     => 'Jan 20, 2025',
      'reviewer' => 'Adviser',
      'status'   => 'Approved',
    ],
    [
      'title'    => 'Q1 Renewal Documents',
      'type'     => 'Renewal',
      'date'     => 'Jan 10, 2025',
      'reviewer' => 'President',
      'status'   => 'Needs Revision',
    ],
    [
      'title'    => 'Leadership Summit Proposal',
      'type'     => 'Activity Request',
      'date'     => 'Jan 24, 2025',
      'reviewer' => 'Adviser',
      'status'   => 'Under Review',
    ],
  ];

  $approvalStages = [
    ['name' => 'President',          'status' => 'approved'],
    ['name' => 'Adviser',            'status' => 'approved'],
    ['name' => 'Program Chair',      'status' => 'current'],
    ['name' => 'Dean',               'status' => 'pending'],
    ['name' => 'Academic Director',  'status' => 'pending'],
    ['name' => 'Executive Director', 'status' => 'pending'],
  ];

  $revisionRemarks = [
    [
      'document'  => 'Q1 Renewal Documents',
      'reviewer'  => 'Prof. M. Santos',
      'returned'  => 'Jan 22, 2025',
      'remarks'   => 'Please update the budget breakdown and attach the faculty adviser confirmation letter. The submitted version is missing key signatures.',
    ],
  ];

  $upcomingActivities = [
    [
      'title'   => 'Sportsfest 2025',
      'date'    => 'Feb 14, 2025',
      'venue'   => 'Cultural Hall',
      'status'  => 'Approved',
    ],
    [
      'title'   => 'Leadership Summit',
      'date'    => 'Mar 5, 2025',
      'venue'   => 'Audio Visual Room',
      'status'  => 'Pending Review',
    ],
    [
      'title'   => 'Acquaintance Party',
      'date'    => 'Mar 22, 2025',
      'venue'   => 'Covered Court',
      'status'  => 'Pending Review',
    ],
  ];

  $notifications = [
    [
      'type'    => 'warning',
      'title'   => 'Renewal Deadline Approaching',
      'message' => 'Your organization renewal submission deadline is on March 31, 2025. You have 25 days remaining.',
      'time'    => '2 hours ago',
    ],
    [
      'type'    => 'error',
      'title'   => 'Document Returned for Revision',
      'message' => 'Q1 Renewal Documents was returned by Prof. M. Santos with revision remarks.',
      'time'    => '1 day ago',
    ],
    [
      'type'    => 'info',
      'title'   => 'Event Schedule Conflict Detected',
      'message' => 'Leadership Summit (Mar 5) overlaps with another approved event. Review and adjust the date.',
      'time'    => '2 days ago',
    ],
    [
      'type'    => 'success',
      'title'   => 'Submission Approved',
      'message' => 'Sportsfest 2025 Proposal has been fully approved and is now on the calendar.',
      'time'    => '3 days ago',
    ],
  ];

  $statusBadge = [
    'Under Review'   => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
    'Approved'       => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
    'Needs Revision' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
    'Pending'        => 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
    'Pending Review' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
  ];

  $activityStatusBadge = [
    'Approved'       => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
    'Pending Review' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
  ];

  $notificationStyles = [
    'warning' => ['border' => 'border-amber-200',  'bg' => 'bg-amber-50',  'icon' => 'text-amber-500',  'title' => 'text-amber-800'],
    'error'   => ['border' => 'border-rose-200',   'bg' => 'bg-rose-50',   'icon' => 'text-rose-500',   'title' => 'text-rose-800'],
    'info'    => ['border' => 'border-blue-200',   'bg' => 'bg-blue-50',   'icon' => 'text-blue-500',   'title' => 'text-blue-800'],
    'success' => ['border' => 'border-emerald-200','bg' => 'bg-emerald-50','icon' => 'text-emerald-500','title' => 'text-emerald-800'],
  ];
@endphp

{{-- ─────────────────────────────────────────────────────── --}}
{{-- WELCOME HEADER                                          --}}
{{-- ─────────────────────────────────────────────────────── --}}
<div class="mb-7 flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
  <div>
    <h2 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
      Welcome back, {{ $userName }}!
    </h2>
    <p class="mt-1 text-sm font-medium text-[#003E9F]">{{ $orgName }}</p>
    <p class="mt-1 text-sm text-slate-500">
      Here's a quick overview of your organization's document status and pending actions.
    </p>
  </div>
  <p class="mt-2 flex-none text-xs text-slate-400 sm:mt-0">{{ $today }}</p>
</div>

{{-- ─────────────────────────────────────────────────────── --}}
{{-- SUMMARY CARDS                                           --}}
{{-- ─────────────────────────────────────────────────────── --}}
<div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

  {{-- Pending Submissions --}}
  <div class="flex items-start gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm transition hover:shadow-md">
    <div class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-amber-100 text-amber-600">
      <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
      </svg>
    </div>
    <div>
      <p class="text-sm font-medium text-amber-700">Pending Submissions</p>
      <p class="mt-0.5 text-3xl font-bold leading-none text-amber-700">3</p>
      <p class="mt-1.5 text-xs text-amber-600/80">Awaiting reviewer action</p>
    </div>
  </div>

  {{-- Approved Documents --}}
  <div class="flex items-start gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm transition hover:shadow-md">
    <div class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
      <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
      </svg>
    </div>
    <div>
      <p class="text-sm font-medium text-emerald-700">Approved Documents</p>
      <p class="mt-0.5 text-3xl font-bold leading-none text-emerald-700">12</p>
      <p class="mt-1.5 text-xs text-emerald-600/80">Fully processed this semester</p>
    </div>
  </div>

  {{-- Needs Revision --}}
  <div class="flex items-start gap-4 rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm transition hover:shadow-md">
    <div class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-rose-100 text-rose-600">
      <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
      </svg>
    </div>
    <div>
      <p class="text-sm font-medium text-rose-700">Needs Revision</p>
      <p class="mt-0.5 text-3xl font-bold leading-none text-rose-700">1</p>
      <p class="mt-1.5 text-xs text-rose-600/80">Returned by reviewer</p>
    </div>
  </div>

  {{-- Upcoming Activities --}}
  <div class="flex items-start gap-4 rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm transition hover:shadow-md">
    <div class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-blue-100 text-blue-600">
      <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
      </svg>
    </div>
    <div>
      <p class="text-sm font-medium text-blue-700">Upcoming Activities</p>
      <p class="mt-0.5 text-3xl font-bold leading-none text-blue-700">5</p>
      <p class="mt-1.5 text-xs text-blue-600/80">Scheduled this month</p>
    </div>
  </div>

</div>

{{-- ─────────────────────────────────────────────────────── --}}
{{-- QUICK ACTIONS                                           --}}
{{-- ─────────────────────────────────────────────────────── --}}
<div class="mb-7">
  <h3 class="mb-3 text-sm font-semibold text-slate-700">Quick Actions</h3>
  <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">

    <a href="{{ route('register-organization') }}"
       class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-[#003E9F]/30 hover:shadow-md">
      <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#003E9F]/10 text-[#003E9F]">
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
        </svg>
      </div>
      <div>
        <p class="text-sm font-semibold text-slate-900">Submit Requirement</p>
        <p class="mt-0.5 text-xs text-slate-500">File registration docs</p>
      </div>
    </a>

    <a href="{{ route('activity-calendar-submission') }}"
       class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-[#003E9F]/30 hover:shadow-md">
      <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#003E9F]/10 text-[#003E9F]">
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
      </div>
      <div>
        <p class="text-sm font-semibold text-slate-900">Create Activity Proposal</p>
        <p class="mt-0.5 text-xs text-slate-500">New event submission</p>
      </div>
    </a>

    <a href="#"
       class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-[#003E9F]/30 hover:shadow-md">
      <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#003E9F]/10 text-[#003E9F]">
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
        </svg>
      </div>
      <div>
        <p class="text-sm font-semibold text-slate-900">Upload After-Activity Report</p>
        <p class="mt-0.5 text-xs text-slate-500">Post-event documents</p>
      </div>
    </a>

    <a href="{{ route('activity-calendar-submission') }}"
       class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-[#003E9F]/30 hover:shadow-md">
      <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#003E9F]/10 text-[#003E9F]">
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
        </svg>
      </div>
      <div>
        <p class="text-sm font-semibold text-slate-900">View Calendar</p>
        <p class="mt-0.5 text-xs text-slate-500">Activities schedule</p>
      </div>
    </a>

  </div>
</div>

{{-- ─────────────────────────────────────────────────────── --}}
{{-- MAIN TWO-COLUMN CONTENT AREA                            --}}
{{-- ─────────────────────────────────────────────────────── --}}
<div class="mb-7 grid grid-cols-1 gap-6 lg:grid-cols-5">

  {{-- LEFT COLUMN (3/5) --}}
  <div class="space-y-6 lg:col-span-3">

    {{-- Recent Submissions --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
        <div>
          <h3 class="text-sm font-semibold text-slate-900">Recent Submissions</h3>
          <p class="mt-0.5 text-xs text-slate-500">Your latest submitted documents and their current status.</p>
        </div>
        <a href="#" class="text-xs font-semibold text-[#003E9F] transition hover:text-[#00327F]">View all →</a>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/60">
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Document</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewer</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @foreach ($recentSubmissions as $submission)
            <tr class="transition hover:bg-slate-50/50">
              <td class="px-5 py-3.5">
                <p class="font-medium text-slate-900">{{ $submission['title'] }}</p>
              </td>
              <td class="px-4 py-3.5">
                <span class="text-xs text-slate-500">{{ $submission['type'] }}</span>
              </td>
              <td class="px-4 py-3.5">
                <span class="text-xs text-slate-500">{{ $submission['date'] }}</span>
              </td>
              <td class="px-4 py-3.5">
                <span class="text-xs text-slate-600">{{ $submission['reviewer'] }}</span>
              </td>
              <td class="px-4 py-3.5">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusBadge[$submission['status']] ?? 'bg-slate-100 text-slate-600' }}">
                  {{ $submission['status'] }}
                </span>
              </td>
              <td class="px-4 py-3.5">
                @if ($submission['status'] === 'Needs Revision')
                  <a href="#" class="text-xs font-semibold text-rose-600 transition hover:text-rose-700">Revise</a>
                @else
                  <a href="#" class="text-xs font-semibold text-[#003E9F] transition hover:text-[#00327F]">View</a>
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    {{-- Approval Progress Tracker --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-5 border-b border-slate-100 pb-4">
        <h3 class="text-sm font-semibold text-slate-900">Approval Progress Tracker</h3>
        <p class="mt-0.5 text-xs text-slate-500">Q1 Renewal Documents — current approval workflow stage.</p>
      </div>

      <div class="overflow-x-auto">
        <div class="flex min-w-max items-center gap-0">
          @foreach ($approvalStages as $i => $stage)
            {{-- Stage circle --}}
            <div class="flex flex-col items-center">
              @if ($stage['status'] === 'approved')
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-500 text-white shadow-sm">
                  <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                  </svg>
                </div>
              @elseif ($stage['status'] === 'current')
                <div class="relative flex h-9 w-9 items-center justify-center rounded-full border-2 border-amber-400 bg-amber-50">
                  <span class="absolute h-3 w-3 animate-ping rounded-full bg-amber-400 opacity-60"></span>
                  <span class="h-3 w-3 rounded-full bg-amber-400"></span>
                </div>
              @else
                <div class="flex h-9 w-9 items-center justify-center rounded-full border-2 border-slate-200 bg-white">
                  <span class="h-2.5 w-2.5 rounded-full bg-slate-200"></span>
                </div>
              @endif
              <p class="mt-2 max-w-[72px] text-center text-[10px] font-medium leading-tight {{ $stage['status'] === 'approved' ? 'text-emerald-600' : ($stage['status'] === 'current' ? 'text-amber-600' : 'text-slate-400') }}">
                {{ $stage['name'] }}
              </p>
            </div>

            {{-- Connector line --}}
            @if (!$loop->last)
              <div class="mx-1 mb-5 h-0.5 w-8 flex-none {{ $stage['status'] === 'approved' ? 'bg-emerald-300' : 'bg-slate-200' }}"></div>
            @endif
          @endforeach
        </div>
      </div>
    </div>

  </div>

  {{-- RIGHT COLUMN (2/5) --}}
  <div class="space-y-6 lg:col-span-2">

    {{-- Revision Remarks --}}
    <div class="rounded-2xl border border-rose-200 bg-white shadow-sm">
      <div class="flex items-center justify-between border-b border-rose-100 bg-rose-50/50 px-5 py-4 rounded-t-2xl">
        <div>
          <h3 class="text-sm font-semibold text-rose-800">Revision Remarks</h3>
          <p class="mt-0.5 text-xs text-rose-600">Documents returned for revision.</p>
        </div>
        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 text-xs font-bold text-rose-700">
          {{ count($revisionRemarks) }}
        </span>
      </div>

      <div class="divide-y divide-slate-100">
        @forelse ($revisionRemarks as $remark)
          <div class="p-5">
            <p class="text-sm font-semibold text-slate-900">{{ $remark['document'] }}</p>
            <div class="mt-1.5 flex items-center gap-3 text-xs text-slate-500">
              <span>{{ $remark['reviewer'] }}</span>
              <span class="h-1 w-1 rounded-full bg-slate-300"></span>
              <span>Returned {{ $remark['returned'] }}</span>
            </div>
            <p class="mt-3 rounded-xl border border-rose-100 bg-rose-50/60 px-3.5 py-3 text-xs leading-5 text-rose-800">
              "{{ $remark['remarks'] }}"
            </p>
            <a
              href="#"
              class="mt-4 inline-flex items-center gap-1.5 rounded-xl border border-rose-300 bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-4 focus:ring-rose-500/20"
            >
              <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
              </svg>
              Revise Submission
            </a>
          </div>
        @empty
          <div class="flex flex-col items-center py-8 text-center">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
              <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
              </svg>
            </div>
            <p class="mt-2 text-xs font-medium text-slate-600">No active revision remarks</p>
          </div>
        @endforelse
      </div>
    </div>

    {{-- Upcoming Activities --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
        <div>
          <h3 class="text-sm font-semibold text-slate-900">Upcoming Activities</h3>
          <p class="mt-0.5 text-xs text-slate-500">Filed events this month.</p>
        </div>
        <a href="{{ route('activity-calendar-submission') }}" class="text-xs font-semibold text-[#003E9F] transition hover:text-[#00327F]">View calendar →</a>
      </div>

      <ul class="divide-y divide-slate-100">
        @foreach ($upcomingActivities as $activity)
          <li class="flex items-start gap-4 px-5 py-4">
            <div class="flex-none rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-center">
              <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">
                {{ \Carbon\Carbon::parse($activity['date'])->format('M') }}
              </p>
              <p class="text-lg font-bold leading-none text-slate-900">
                {{ \Carbon\Carbon::parse($activity['date'])->format('d') }}
              </p>
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-slate-900">{{ $activity['title'] }}</p>
              <p class="mt-0.5 text-xs text-slate-500">{{ $activity['venue'] }}</p>
              <span class="mt-1.5 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $activityStatusBadge[$activity['status']] ?? 'bg-slate-100 text-slate-600 ring-1 ring-slate-200' }}">
                {{ $activity['status'] }}
              </span>
            </div>
          </li>
        @endforeach
      </ul>
    </div>

  </div>

</div>

{{-- ─────────────────────────────────────────────────────── --}}
{{-- NOTIFICATIONS / DEADLINES                               --}}
{{-- ─────────────────────────────────────────────────────── --}}
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="border-b border-slate-100 px-5 py-4">
    <h3 class="text-sm font-semibold text-slate-900">Notifications &amp; Deadlines</h3>
    <p class="mt-0.5 text-xs text-slate-500">Recent alerts, warnings, and status updates for your organization.</p>
  </div>

  <ul class="divide-y divide-slate-100">
    @foreach ($notifications as $notif)
      @php $s = $notificationStyles[$notif['type']]; @endphp
      <li class="flex items-start gap-4 px-5 py-4 transition hover:bg-slate-50/50">
        <div class="flex h-8 w-8 flex-none items-center justify-center rounded-xl {{ $s['bg'] }} {{ $s['icon'] }}">
          @if ($notif['type'] === 'warning')
            <svg class="h-4.5 w-4.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
          @elseif ($notif['type'] === 'error')
            <svg class="h-4.5 w-4.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
          @elseif ($notif['type'] === 'info')
            <svg class="h-4.5 w-4.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
          @else
            <svg class="h-4.5 w-4.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
          @endif
        </div>
        <div class="min-w-0 flex-1">
          <div class="flex flex-col gap-0.5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-semibold {{ $s['title'] }}">{{ $notif['title'] }}</p>
            <span class="text-[10px] text-slate-400">{{ $notif['time'] }}</span>
          </div>
          <p class="mt-1 text-xs leading-5 text-slate-600">{{ $notif['message'] }}</p>
        </div>
      </li>
    @endforeach
  </ul>
</div>

@endsection
