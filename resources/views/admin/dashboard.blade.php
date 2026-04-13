@extends('layouts.admin')

@section('title', 'Student Development and Activities Office Admin Dashboard — NU Lipa')

@section('content')
@php
  $overview = [
    ['label' => 'Total Pending Registrations', 'value' => $counts['registrations'], 'route' => route('admin.registrations.index')],
    ['label' => 'Total Pending Renewals', 'value' => $counts['renewals'], 'route' => route('admin.renewals.index')],
    ['label' => 'Total Pending Activity Calendars', 'value' => $counts['calendars'], 'route' => route('admin.calendars.index')],
    ['label' => 'Total Pending Activity Proposals', 'value' => $counts['proposals'], 'route' => route('admin.proposals.index')],
    ['label' => 'Total Pending After Activity Reports', 'value' => $counts['reports'], 'route' => route('admin.reports.index')],
  ];
@endphp

<header class="mb-8">
  <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Student Development and Activities Office Admin Dashboard</h1>
  <p class="mt-1 text-sm text-slate-500">Review and monitor all major student organization submissions.</p>
</header>

<section class="mb-8 rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-baseline sm:justify-between">
      <div>
        <h2 class="text-lg font-bold text-slate-900">Registered organizations</h2>
        <p class="mt-0.5 text-sm text-slate-500">RSOs currently on file in the system. Status reflects each organization&rsquo;s accreditation record.</p>
      </div>
      <p class="shrink-0 text-xs font-semibold uppercase tracking-wide text-slate-600">
        <span class="text-[#003E9F]">{{ $registeredOrganizations->count() }}</span>
        <span class="text-slate-500"> total</span>
      </p>
    </div>
  </div>

  <div class="max-h-[min(28rem,55vh)] overflow-y-auto">
    @if ($registeredOrganizations->isEmpty())
      <div class="px-5 py-12 text-center text-sm text-slate-500 sm:px-6">
        No organizations registered yet. New RSOs will appear here after they are approved and created in the system.
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
          <thead class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur">
            <tr>
              <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">Organization</th>
              <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">Department / school</th>
              <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">Type</th>
              <th scope="col" class="whitespace-nowrap px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @foreach ($registeredOrganizations as $org)
              @php
                $typeLabel = match ($org->organization_type ?? '') {
                  'extra_curricular' => 'Extra-curricular',
                  'co_curricular' => 'Co-curricular',
                  default => '—',
                };
                $orgStatus = strtoupper((string) ($org->organization_status ?? 'PENDING'));
                $statusBadge = match ($orgStatus) {
                  'ACTIVE' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
                  'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
                  'INACTIVE' => 'bg-slate-100 text-slate-700 border border-slate-200',
                  'SUSPENDED' => 'bg-rose-100 text-rose-800 border border-rose-200',
                  default => 'bg-slate-100 text-slate-700 border border-slate-200',
                };
              @endphp
              <tr class="hover:bg-slate-50/80">
                <td class="px-5 py-3 font-medium text-slate-900 sm:px-6">{{ $org->organization_name }}</td>
                <td class="max-w-[14rem] truncate px-5 py-3 text-slate-600 sm:px-6" title="{{ $org->college_department }}">{{ $org->college_department ?: '—' }}</td>
                <td class="whitespace-nowrap px-5 py-3 text-slate-600 sm:px-6">{{ $typeLabel }}</td>
                <td class="whitespace-nowrap px-5 py-3 sm:px-6">
                  <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge }}">{{ ucfirst(strtolower($orgStatus)) }}</span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</section>

@if (auth()->user()?->isSuperAdmin())
  <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-sm font-bold text-slate-900">Student Development and Activities Office submissions</h2>
    <p class="mt-1 text-xs text-slate-500">
      File registrations, renewals, activity calendars, and proposals from the admin portal. Submissions are recorded under your Student Development and Activities Office admin account. For renewals, calendars, and proposals, enter each organization&rsquo;s registered name exactly as stored in the directory.
    </p>
    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
      <a href="{{ route('admin.submissions.register') }}" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-[#003E9F] transition hover:bg-slate-100">Register organization</a>
      <a href="{{ route('admin.submissions.renew') }}" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-[#003E9F] transition hover:bg-slate-100">Renew organization</a>
      <a href="{{ route('admin.submissions.activity-calendar') }}" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-[#003E9F] transition hover:bg-slate-100">Submit activity calendar</a>
      <a href="{{ route('admin.submissions.activity-proposal') }}" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-[#003E9F] transition hover:bg-slate-100">Submit activity proposal</a>
    </div>
  </section>
@endif

<section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
  @foreach ($overview as $item)
    <a href="{{ $item['route'] }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $item['label'] }}</p>
      <p class="mt-2 text-3xl font-bold text-[#003E9F]">{{ $item['value'] }}</p>
      <p class="mt-2 text-xs font-semibold text-[#003E9F]">Open module</p>
    </a>
  @endforeach
</section>

<section class="mt-8">
  <div class="mb-4">
    <h2 class="text-lg font-bold text-slate-900">Centralized Activity Calendar</h2>
    <p class="mt-1 text-sm text-slate-500">Live monitoring view for all organization events and related submissions.</p>
  </div>
  @include('admin.partials.centralized-calendar')
</section>
@endsection

