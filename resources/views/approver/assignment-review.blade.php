@extends('layouts.admin')

@section('title', $pageTitle . ' — NU Lipa SDAO')

@section('content')
@php
  $statusClass = match ($status) {
    'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
    'UNDER_REVIEW', 'REVIEWED' => 'bg-blue-100 text-blue-700 border border-blue-200',
    'APPROVED' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    'REJECTED' => 'bg-rose-100 text-rose-700 border border-rose-200',
    'REVISION', 'REVISION_REQUIRED' => 'bg-orange-100 text-orange-700 border border-orange-200',
    default => 'bg-slate-100 text-slate-700 border border-slate-200',
  };
@endphp

<div class="mb-6 flex items-center justify-between gap-3">
  <a href="{{ route('approver.dashboard') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-[#003E9F] hover:text-[#00327F]">
    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
    </svg>
    Back to approver dashboard
  </a>
  <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $status }}</span>
</div>

<x-ui.card padding="p-6">
  <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
    @foreach ($details as $label => $value)
      <div class="rounded-2xl border border-slate-200 bg-slate-50/90 p-5">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">{{ $label }}</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $value }}</dd>
      </div>
    @endforeach
  </dl>

  @if (($calendarEntries?->count() ?? 0) > 0)
    <div class="mt-8 border-t border-slate-100 pt-8">
      <h2 class="text-base font-bold text-slate-900">Submitted activities</h2>
      <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
          <thead class="bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3">Date</th>
              <th class="px-5 py-3">Activity</th>
              <th class="px-5 py-3">SDG</th>
              <th class="px-5 py-3">Venue</th>
              <th class="px-5 py-3">Participants / Program</th>
              <th class="px-5 py-3">Budget</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            @foreach ($calendarEntries as $row)
              <tr>
                <td class="px-5 py-3">{{ optional($row->activity_date)->format('M d, Y') ?? '—' }}</td>
                <td class="px-5 py-3">{{ $row->activity_name }}</td>
                <td class="px-5 py-3">{{ $row->sdg }}</td>
                <td class="px-5 py-3">{{ $row->venue }}</td>
                <td class="px-5 py-3">{{ $row->participant_program }}</td>
                <td class="px-5 py-3">{{ $row->budget }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  <div class="mt-8 border-t border-slate-100 pt-8">
    <h2 class="text-base font-bold text-slate-900">Approval workflow</h2>
    <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
        <thead class="bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-5 py-3">Step</th>
            <th class="px-5 py-3">Role</th>
            <th class="px-5 py-3">Status</th>
            <th class="px-5 py-3">Reviewer</th>
            <th class="px-5 py-3">Acted At</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
          @foreach ($workflowSteps as $step)
            <tr>
              <td class="px-5 py-3">#{{ $step->step_order }}{{ $step->is_current_step ? ' (current)' : '' }}</td>
              <td class="px-5 py-3">{{ $step->role?->display_name ?? $step->role?->name ?? '—' }}</td>
              <td class="px-5 py-3">{{ strtoupper((string) $step->status) }}</td>
              <td class="px-5 py-3">{{ $step->assignedTo?->full_name ?? '—' }}</td>
              <td class="px-5 py-3">{{ optional($step->acted_at)->format('M d, Y g:i A') ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <form method="POST" action="{{ $workflowActionRoute }}" class="mt-6 space-y-4">
      @csrf
      @method('PATCH')
      <div>
        <x-forms.label for="workflow_comments">Comments (optional)</x-forms.label>
        <x-forms.textarea id="workflow_comments" name="comments" rows="3" placeholder="Add rationale for your decision...">{{ old('comments') }}</x-forms.textarea>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="submit" name="action" value="approve" class="inline-flex rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve</button>
        <button type="submit" name="action" value="revision" class="inline-flex rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600">Request revision</button>
        <button type="submit" name="action" value="reject" class="inline-flex rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Reject</button>
      </div>
    </form>

    @if (($workflowLogs?->count() ?? 0) > 0)
      <div class="mt-6">
        <h3 class="text-sm font-semibold text-slate-900">Recent workflow logs</h3>
        <ul class="mt-2 space-y-2 text-sm">
          @foreach ($workflowLogs as $log)
            <li class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
              <span class="font-semibold">{{ strtoupper((string) $log->action) }}</span>
              · {{ $log->from_status ?? '—' }} → {{ $log->to_status ?? '—' }}
              · {{ $log->actor?->full_name ?? 'System' }}
              · {{ optional($log->created_at)->format('M d, Y g:i A') ?? '—' }}
              @if ($log->comments)
                <div class="mt-1 text-slate-700">{{ $log->comments }}</div>
              @endif
            </li>
          @endforeach
        </ul>
      </div>
    @endif
  </div>
</x-ui.card>
@endsection

