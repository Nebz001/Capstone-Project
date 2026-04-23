@extends('layouts.admin')

@section('title', ($approverLabel ?? 'Approver').' Dashboard — NU Lipa SDAO')

@section('content')
@php
  $summaryCards = [
    ['label' => 'Pending Approvals', 'value' => $summary['pending_approvals'] ?? 0],
    ['label' => 'For Revision Follow-up', 'value' => $summary['revision_follow_up'] ?? 0],
    ['label' => 'Approved This Week', 'value' => $summary['approved_week'] ?? 0],
    ['label' => 'Rejected / Returned This Week', 'value' => $summary['rejected_or_returned_week'] ?? 0],
    ['label' => 'Overdue Items', 'value' => $summary['overdue_items'] ?? 0],
  ];
@endphp

<header class="mb-8">
  <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#003E9F]">Approval Work Queue</p>
  <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $approverLabel ?? 'Approver' }} Dashboard</h1>
  <p class="mt-1 text-sm text-slate-500">Review, approve, reject, or request revisions for routed documents assigned to your role stage.</p>
</header>

<section class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
  @foreach ($summaryCards as $card)
    <x-ui.card padding="p-5">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
      <p class="mt-2 text-3xl font-bold text-[#003E9F]">{{ $card['value'] }}</p>
    </x-ui.card>
  @endforeach
</section>

<x-ui.card padding="p-0" class="mb-8">
  <x-ui.card-section-header
    title="My Pending Approvals"
    subtitle="Only documents currently routed to your role are shown."
    content-padding="px-6"
  />
  <div class="border-t border-slate-100 px-6 py-5">
    <form method="GET" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
      <div>
        @if ($allowMultiDocumentTypes ?? false)
          <x-forms.label for="filter_document_type">Document type</x-forms.label>
          <x-forms.select id="filter_document_type" name="document_type">
            <option value="">All</option>
            <option value="organization_registration" @selected(($filters['document_type'] ?? '') === 'organization_registration')>Organization Registration</option>
            <option value="organization_renewal" @selected(($filters['document_type'] ?? '') === 'organization_renewal')>Organization Renewal</option>
            <option value="activity_calendar" @selected(($filters['document_type'] ?? '') === 'activity_calendar')>Activity Calendar</option>
            <option value="activity_proposal" @selected(($filters['document_type'] ?? '') === 'activity_proposal')>Activity Proposal</option>
            <option value="activity_report" @selected(($filters['document_type'] ?? '') === 'activity_report')>Activity Report</option>
          </x-forms.select>
        @else
          <x-forms.label>Document type</x-forms.label>
          <input type="hidden" name="document_type" value="activity_proposal" />
          <div class="mt-2 inline-flex min-h-[46px] w-full items-center rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700">
            Activity Proposal
          </div>
        @endif
      </div>
      <div>
        <x-forms.label for="filter_status">Status</x-forms.label>
        <x-forms.select id="filter_status" name="status">
          <option value="">All</option>
          <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
          <option value="under_review" @selected(($filters['status'] ?? '') === 'under_review')>Under Review</option>
          <option value="revision" @selected(($filters['status'] ?? '') === 'revision')>Revision</option>
          <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>Approved</option>
          <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Rejected</option>
        </x-forms.select>
      </div>
      <div>
        <x-forms.label for="filter_organization">Organization</x-forms.label>
        <x-forms.input id="filter_organization" name="organization" type="text" :value="$filters['organization'] ?? ''" placeholder="Search organization..." />
      </div>
      <div>
        <x-forms.label for="filter_date_from">Date from</x-forms.label>
        <x-forms.input id="filter_date_from" name="date_from" type="date" :value="$filters['date_from'] ?? ''" />
      </div>
      <div>
        <x-forms.label for="filter_date_to">Date to</x-forms.label>
        <x-forms.input id="filter_date_to" name="date_to" type="date" :value="$filters['date_to'] ?? ''" />
      </div>
      <div class="md:col-span-2 xl:col-span-5 flex justify-end gap-2">
        <a href="{{ route('approver.dashboard') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">Reset</a>
        <x-ui.button type="submit">Apply Filters</x-ui.button>
      </div>
    </form>
  </div>
  <div class="overflow-x-auto border-t border-slate-100">
    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
      <thead class="bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500">
        <tr>
          <th class="px-5 py-3">Document Type</th>
          <th class="px-5 py-3">Organization</th>
          <th class="px-5 py-3">Submitted By</th>
          <th class="px-5 py-3">Date Submitted</th>
          <th class="px-5 py-3">Current Step</th>
          <th class="px-5 py-3">Pending For</th>
          <th class="px-5 py-3">Status</th>
          <th class="px-5 py-3 text-right">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 bg-white">
        @forelse ($pendingItems as $item)
          <tr class="hover:bg-slate-50/80">
            <td class="px-5 py-3 text-slate-800">{{ $item['document_type_label'] }}</td>
            <td class="px-5 py-3 font-medium text-slate-900">{{ $item['organization_name'] }}</td>
            <td class="px-5 py-3 text-slate-700">{{ $item['submitted_by'] }}</td>
            <td class="px-5 py-3 text-slate-700">{{ $item['submitted_at_label'] }}</td>
            <td class="px-5 py-3 text-slate-700">Step {{ $item['current_approval_step'] }}</td>
            <td class="px-5 py-3 text-slate-700">{{ $item['pending_for_label'] }}</td>
            <td class="px-5 py-3">
              <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $item['status_class'] }}">{{ $item['status_label'] }}</span>
            </td>
            <td class="px-5 py-3 text-right">
              <a href="{{ $item['review_url'] }}" class="inline-flex rounded-xl border border-[#003E9F] px-3.5 py-2 text-xs font-semibold text-[#003E9F] transition hover:bg-[#003E9F] hover:text-white">View / Review</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="px-5 py-10 text-center text-sm text-slate-500">No pending approvals match your filters.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-ui.card>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
  <x-ui.card padding="p-0" class="xl:col-span-2">
    <x-ui.card-section-header title="Needs Attention / Overdue" subtitle="Oldest pending items, overdue approvals, and revision follow-ups." content-padding="px-6" />
    <div class="divide-y divide-slate-100">
      @forelse ($needsAttentionItems as $item)
        <div class="px-6 py-4">
          <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-semibold text-slate-900">{{ $item['document_type_label'] }} · {{ $item['organization_name'] }}</p>
            <span class="text-xs font-medium text-slate-500">{{ $item['pending_days'] }} day(s) pending</span>
          </div>
          <p class="mt-1 text-xs text-slate-600">Submitted by {{ $item['submitted_by'] }} on {{ $item['submitted_at_label'] }}</p>
          <a href="{{ $item['review_url'] }}" class="mt-2 inline-flex text-xs font-semibold text-[#003E9F] hover:text-[#00327F]">Review now</a>
        </div>
      @empty
        <div class="px-6 py-10 text-center text-sm text-slate-500">No overdue or urgent items right now.</div>
      @endforelse
    </div>
  </x-ui.card>

  <x-ui.card padding="p-0">
    <x-ui.card-section-header title="Recent Alerts" subtitle="Approval-related notifications for your queue." content-padding="px-6" />
    <div class="divide-y divide-slate-100">
      @forelse ($notifications as $notification)
        <div class="px-6 py-4">
          <p class="text-sm font-semibold text-slate-900">{{ $notification->title }}</p>
          <p class="mt-1 text-xs text-slate-600">{{ $notification->body ?: 'No details provided.' }}</p>
          <p class="mt-1 text-[11px] text-slate-500">{{ optional($notification->created_at)->diffForHumans() }}</p>
        </div>
      @empty
        <div class="px-6 py-10 text-center text-sm text-slate-500">No approval alerts available.</div>
      @endforelse
    </div>
  </x-ui.card>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
  <x-ui.card padding="p-0">
    <x-ui.card-section-header title="Recent Routed Documents" subtitle="Most recently assigned or updated items for your role." content-padding="px-6" />
    <div class="divide-y divide-slate-100">
      @forelse ($recentRoutedItems as $item)
        <div class="px-6 py-4">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-slate-900">{{ $item['document_type_label'] }}</p>
            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $item['status_class'] }}">{{ $item['status_label'] }}</span>
          </div>
          <p class="mt-1 text-xs text-slate-600">{{ $item['organization_name'] }} · {{ $item['submitted_at_label'] }}</p>
          <a href="{{ $item['review_url'] }}" class="mt-2 inline-flex text-xs font-semibold text-[#003E9F] hover:text-[#00327F]">Open</a>
        </div>
      @empty
        <div class="px-6 py-10 text-center text-sm text-slate-500">No recent routed documents.</div>
      @endforelse
    </div>
  </x-ui.card>

  <x-ui.card padding="p-0">
    <x-ui.card-section-header title="Recent Actions / Approval History" subtitle="Your latest decisions across routed documents." content-padding="px-6" />
    <div class="divide-y divide-slate-100">
      @forelse ($recentActions as $action)
        <div class="px-6 py-4">
          <p class="text-sm font-semibold text-slate-900">{{ strtoupper((string) $action->action) }} · {{ $action->from_status ?? 'N/A' }} → {{ $action->to_status ?? 'N/A' }}</p>
          <p class="mt-1 text-xs text-slate-600">{{ optional($action->created_at)->format('M d, Y g:i A') ?? 'N/A' }}</p>
          @if ($action->comments)
            <p class="mt-1 text-xs text-slate-600">{{ $action->comments }}</p>
          @endif
        </div>
      @empty
        <div class="px-6 py-10 text-center text-sm text-slate-500">No recent actions recorded yet.</div>
      @endforelse
    </div>
  </x-ui.card>
</div>

<x-ui.card padding="p-0" class="mt-6">
  <x-ui.card-section-header title="Schedule Context (Under Review)" subtitle="Upcoming proposal/report activity dates from your current queue." content-padding="px-6" />
  <div class="divide-y divide-slate-100">
    @forelse ($scheduleContext as $item)
      <div class="px-6 py-4">
        <p class="text-sm font-semibold text-slate-900">{{ $item['document_type_label'] }} · {{ $item['organization_name'] }}</p>
        <p class="mt-1 text-xs text-slate-600">Event date: {{ optional($item['event_date'])->format('M d, Y g:i A') ?? optional($item['event_date'])->format('M d, Y') ?? 'N/A' }}</p>
      </div>
    @empty
      <div class="px-6 py-10 text-center text-sm text-slate-500">No upcoming event context available from current routed items.</div>
    @endforelse
  </div>
</x-ui.card>
@endsection

