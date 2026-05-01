@extends('layouts.organization-portal')

@section('title', 'Submitted Documents — NU Lipa SDAO')

@section('content')

@php
  $saOrgId = isset($superAdminOrganizationId) && $superAdminOrganizationId ? (int) $superAdminOrganizationId : null;
  $saQ = $saOrgId ? '?organization_id='.$saOrgId : '';
  $badgeByVariant = [
    'draft' => 'bg-slate-200 text-slate-800 border border-slate-300',
    'pending' => 'bg-amber-100 text-amber-800 border border-amber-200',
    'revision' => 'bg-orange-100 text-orange-800 border border-orange-200',
    'approved' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
    'rejected' => 'bg-rose-100 text-rose-800 border border-rose-200',
    'review' => 'bg-blue-100 text-blue-800 border border-blue-200',
    'neutral' => 'bg-slate-100 text-slate-700 border border-slate-200',
  ];
@endphp

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">

  <header class="mb-6">
    <a href="{{ route('organizations.manage') }}{{ $saQ }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
      <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
      </svg>
      Back to Manage Organization
    </a>
    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
      Submitted Documents
    </h1>
    <p class="mt-1 text-sm text-slate-500">
      Review every submission your organization has filed with SDAO—registration, renewal, activity calendars, proposals, and after-activity reports—with current status and quick access to details and files.
    </p>
  </header>

  @if (session('error'))
    <x-feedback.blocked-message variant="error" class="mb-6" :message="session('error')" />
  @endif

  @if (isset($blockedMessage) && $blockedMessage)
    <x-feedback.blocked-message variant="error" class="mb-6" :message="$blockedMessage" />
  @elseif (! $organization && auth()->user()->isSuperAdmin())
    <x-ui.card padding="p-0" class="mb-6">
      <div class="px-6 py-10 text-center text-sm text-slate-600">
        Choose an organization above to load this RSO&rsquo;s submitted documents.
      </div>
    </x-ui.card>
  @else

  <x-ui.card padding="p-0" class="mb-6">
    <x-ui.card-section-header
      title="Filter & sort"
      subtitle="Narrow the list by type, status, or academic year. Results are grouped by submission type."
      content-padding="px-6" />
    <div class="border-t border-slate-100 px-6 py-5">
      <form method="get" action="{{ route('organizations.submitted-documents') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5 lg:items-end">
        @if (auth()->user()->isSuperAdmin() && $organization)
          <input type="hidden" name="organization_id" value="{{ $organization->id }}" />
        @endif
        <div>
          <x-forms.label for="filter_type">Submission type</x-forms.label>
          <x-forms.select id="filter_type" name="type">
            <option value="all" @selected(($filters['type'] ?? 'all') === 'all')>All types</option>
            <option value="registration" @selected(($filters['type'] ?? '') === 'registration')>Registration</option>
            <option value="renewal" @selected(($filters['type'] ?? '') === 'renewal')>Renewal</option>
            <option value="activity_calendar" @selected(($filters['type'] ?? '') === 'activity_calendar')>Activity Calendar</option>
            <option value="activity_proposal" @selected(($filters['type'] ?? '') === 'activity_proposal')>Activity Proposal</option>
            <option value="after_activity_report" @selected(($filters['type'] ?? '') === 'after_activity_report')>After Activity Report</option>
          </x-forms.select>
        </div>
        <div>
          <x-forms.label for="filter_status">Status</x-forms.label>
          <x-forms.select id="filter_status" name="status">
            <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All statuses</option>
            <option value="DRAFT" @selected(($filters['status'] ?? '') === 'DRAFT')>Draft</option>
            <option value="PENDING" @selected(($filters['status'] ?? '') === 'PENDING')>Pending</option>
            <option value="UNDER_REVIEW" @selected(($filters['status'] ?? '') === 'UNDER_REVIEW')>Under review</option>
            <option value="REVIEWED" @selected(($filters['status'] ?? '') === 'REVIEWED')>Reviewed</option>
            <option value="REVISION" @selected(($filters['status'] ?? '') === 'REVISION')>For revision</option>
            <option value="APPROVED" @selected(($filters['status'] ?? '') === 'APPROVED')>Approved</option>
            <option value="REJECTED" @selected(($filters['status'] ?? '') === 'REJECTED')>Rejected</option>
          </x-forms.select>
        </div>
        <div>
          <x-forms.label for="filter_year">Academic year</x-forms.label>
          <x-forms.select id="filter_year" name="academic_year">
            <option value="">All years</option>
            @foreach ($academicYearOptions as $year)
              <option value="{{ $year }}" @selected(($filters['academic_year'] ?? '') === $year)>{{ $year }}</option>
            @endforeach
          </x-forms.select>
        </div>
        <div>
          <x-forms.label for="filter_sort">Sort by date</x-forms.label>
          <x-forms.select id="filter_sort" name="sort">
            <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>Latest first</option>
            <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest first</option>
          </x-forms.select>
        </div>
        <div class="flex flex-wrap gap-2">
          <x-ui.button type="submit" class="w-full sm:w-auto">Apply</x-ui.button>
          <a href="{{ route('organizations.submitted-documents', array_filter(['organization_id' => auth()->user()->isSuperAdmin() && $organization ? $organization->id : null])) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto">
            Reset
          </a>
        </div>
      </form>
    </div>
  </x-ui.card>

  @if (! $hasAnyRecords)
    <x-ui.card padding="p-0">
      <div class="px-6 py-12 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
          <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12H9m1.5 0H7.5m3 0h3m6.75 0h1.5m-9 0v-9M9 12v9" />
          </svg>
        </div>
        <h2 class="mt-4 text-lg font-semibold text-slate-900">No submissions yet</h2>
        <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
          When your organization files registration, renewal, activity calendars, proposals, or after-activity reports, they will appear here with status and links to details.
        </p>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
          <a href="{{ route('organizations.manage') }}{{ $saQ }}" class="inline-flex rounded-xl bg-[#003E9F] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#00327F]">
            Back to Manage Organization
          </a>
          <a href="{{ route('organizations.activity-submission') }}{{ $saQ }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
            Activity submission
          </a>
        </div>
      </div>
    </x-ui.card>
  @elseif (count($groupedRecords) === 0)
    <x-ui.card padding="p-0">
      <div class="px-6 py-12 text-center text-sm text-slate-600">
        No records match your filters.
        <a href="{{ route('organizations.submitted-documents', array_filter(['organization_id' => auth()->user()->isSuperAdmin() && $organization ? $organization->id : null])) }}" class="ml-1 font-semibold text-[#003E9F] hover:text-[#00327F]">Clear filters</a>
      </div>
    </x-ui.card>
  @else
    <div class="flex flex-col">
      @foreach ($groupedRecords as $group)
        <section
          @class([
            'scroll-mt-6',
            'border-t border-slate-200/80 pt-6 sm:pt-7' => ! $loop->first,
          ])
        >
          <div class="mb-3.5 flex flex-wrap items-baseline justify-between gap-x-8 gap-y-2 sm:mb-4">
            <h2 class="text-lg font-bold tracking-tight text-slate-900">{{ $group['type_label'] }}</h2>
            <span class="shrink-0 text-sm font-medium tabular-nums text-slate-500">{{ $group['rows']->count() }} record{{ $group['rows']->count() === 1 ? '' : 's' }}</span>
          </div>

          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-300/30">
            <div class="px-3 py-3 sm:px-5 sm:py-4 lg:px-6 lg:py-5">
              <div class="overflow-x-auto rounded-xl border border-slate-200">
              <table class="min-w-5xl w-full divide-y divide-slate-200 text-left text-sm md:min-w-full">
                <thead>
                  <tr class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th class="whitespace-nowrap px-4 py-3 sm:px-5 lg:pl-6">Submission</th>
                    <th class="whitespace-nowrap px-4 py-3 sm:px-5">Submitted</th>
                    <th class="whitespace-nowrap px-4 py-3 sm:px-5">Last updated</th>
                    <th class="whitespace-nowrap px-4 py-3 sm:px-5">Status</th>
                    <th class="whitespace-nowrap px-4 py-3 sm:px-5">AY / term</th>
                    <th class="whitespace-nowrap px-4 py-3 text-right sm:px-5 lg:pr-6">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                  @foreach ($group['rows'] as $row)
                    <tr class="align-top">
                      <td class="px-4 py-3.5 align-top sm:px-5 lg:pl-6 lg:pr-5">
                        <p class="text-sm font-semibold leading-snug text-slate-900">{{ $row['title'] }}</p>
                        <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">{{ $row['type_label'] }}</p>
                      </td>
                      <td class="whitespace-nowrap px-4 py-3.5 align-top font-medium text-slate-700 sm:px-5">{{ $row['submitted_display'] }}</td>
                      <td class="px-4 py-3.5 align-top font-medium text-slate-700 sm:px-5">
                        @if (! empty($row['last_updated_date'] ?? null) && ! empty($row['last_updated_time'] ?? null))
                          <div class="flex flex-col">
                            <span class="font-medium text-slate-900">{{ $row['last_updated_date'] }}</span>
                            <span class="text-sm text-slate-500">{{ $row['last_updated_time'] }}</span>
                          </div>
                        @else
                          <span class="whitespace-nowrap">{{ $row['updated_display'] }}</span>
                        @endif
                      </td>
                      <td class="px-4 py-3.5 align-top sm:px-5">
                        <span class="inline-flex shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold leading-none {{ $badgeByVariant[$row['status_variant']] ?? $badgeByVariant['neutral'] }}">
                          {{ $row['status_label'] }}
                        </span>
                      </td>
                      <td class="px-4 py-3.5 align-top font-medium text-slate-700 sm:px-5">{{ $row['academic_context'] ?? '—' }}</td>
                      <td class="px-4 py-3.5 text-right align-top sm:px-5 lg:pr-6">
                        <div class="flex min-w-38 flex-col items-end justify-start gap-2">
                          @foreach ($row['row_actions'] as $action)
                            <a
                              href="{{ $action['href'] }}"
                              @class([
                                'inline-flex w-full max-w-[11.5rem] justify-center rounded-lg px-3.5 py-2 text-xs font-semibold transition sm:w-auto sm:min-w-[9.25rem]',
                                'bg-[#003E9F] text-white hover:bg-[#00327F]' => ($action['style'] ?? '') === 'primary',
                                'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' => ($action['style'] ?? '') === 'secondary',
                              ])
                            >
                              {{ $action['label'] }}
                            </a>
                          @endforeach
                        </div>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
              </div>
            </div>
          </div>
        </section>
      @endforeach
    </div>
  @endif

  @endif

</div>

@endsection
