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
  $readonlyItemClass = 'rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3.5';
  $readonlyLabelClass = 'text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500';
  $readonlyValueClass = 'mt-1.5 text-sm font-semibold text-slate-900';
@endphp

<x-ui.card padding="p-0" class="overflow-hidden">
  <x-ui.card-section-header
    :title="$pageTitle"
    subtitle="Read-only submission details from the selected review module."
    content-padding="px-6"
  />
  <div class="border-t border-slate-100 px-6 py-5">
    <dl class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
      @foreach ($details as $label => $value)
        <div class="{{ $readonlyItemClass }}">
          <dt class="{{ $readonlyLabelClass }}">{{ $label }}</dt>
          <dd class="{{ $readonlyValueClass }}">{{ $value }}</dd>
        </div>
      @endforeach
    </dl>
  </div>

  @isset($calendarEntries)
    @if ($calendarEntries->isNotEmpty())
      <div class="mt-6 border-t border-slate-100 pt-6">
        <h2 class="px-6 text-lg font-bold text-slate-900">Submitted activities</h2>
        <p class="mt-1 px-6 text-sm text-slate-500">Each row is a saved record linked to this activity calendar.</p>
        <div class="mt-4 mx-6 overflow-x-auto rounded-xl border border-slate-200">
          <table class="min-w-3xl w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="whitespace-nowrap px-5 py-3">Date</th>
                <th class="min-w-40 px-5 py-3">Activity</th>
                <th class="whitespace-nowrap px-5 py-3">SDG</th>
                <th class="min-w-32 px-5 py-3">Venue</th>
                <th class="min-w-48 px-5 py-3">Participants / program</th>
                <th class="whitespace-nowrap px-5 py-3">Budget</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              @foreach ($calendarEntries as $row)
                <tr class="align-top hover:bg-slate-50/80">
                  <td class="whitespace-nowrap px-5 py-3.5 font-medium text-slate-800">{{ optional($row->activity_date)->format('M d, Y') ?? '—' }}</td>
                  <td class="px-5 py-3.5 font-semibold text-slate-900">{{ $row->activity_name }}</td>
                  <td class="whitespace-nowrap px-5 py-3.5 font-medium text-slate-800">{{ $row->sdg }}</td>
                  <td class="px-5 py-3.5 font-medium text-slate-800">{{ $row->venue }}</td>
                  <td class="px-5 py-3.5 text-slate-700">{{ $row->participant_program }}</td>
                  <td class="whitespace-nowrap px-5 py-3.5 font-medium text-slate-800">{{ $row->budget }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
  @endisset

  @isset($organization)
    @if ($organization)
      <div class="mt-6 border-t border-slate-100 pt-6">
        <h2 class="px-6 text-lg font-bold text-slate-900">Organization profile (SDAO)</h2>
        <p class="mt-1 px-6 text-sm text-slate-500">
          Request a profile revision when the organization must update registered organization details or adviser information. Officers can edit only while this is active and the organization is not pending review.
        </p>
        @if ($organization->profile_information_revision_requested)
          <x-feedback.blocked-message class="mx-6 mt-4">
            A profile revision is currently <span class="font-semibold">open</span> for this organization.
          </x-feedback.blocked-message>
        @endif
        <form method="POST" action="{{ route('admin.organizations.request-profile-revision', $organization) }}" class="mx-6 mt-4 space-y-4">
          @csrf
          <div>
            <x-forms.label for="profile_revision_notes">Optional notes to the organization (shown on their profile)</x-forms.label>
            <x-forms.textarea
              id="profile_revision_notes"
              name="profile_revision_notes"
              :rows="3"
              placeholder="e.g., Please update your college department and adviser name to match current records."
            >{{ old('profile_revision_notes', $organization->profile_revision_notes) }}</x-forms.textarea>
          </div>
          <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/40">
            Request organization profile revision
          </button>
        </form>
      </div>
    @endif
  @endisset

  @isset($workflowSteps)
    @if (($workflowSteps?->count() ?? 0) > 0)
      <div class="mt-6 border-t border-slate-100 pt-6">
        <h2 class="px-6 text-lg font-bold text-slate-900">Approval Workflow</h2>
        <p class="mt-1 px-6 text-sm text-slate-500">Step-based review for this proposal. Every decision is logged for traceability.</p>

        @if (isset($workflowCurrentStep) && $workflowCurrentStep)
          <div class="mx-6 mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800">
            Current step:
            <span class="font-semibold">#{{ $workflowCurrentStep->step_order }} — {{ $workflowCurrentStep->role?->display_name ?? $workflowCurrentStep->role?->name ?? 'Unassigned role' }}</span>
          </div>
        @endif

        <div class="mx-6 mt-4 overflow-x-auto rounded-xl border border-slate-200">
          <table class="min-w-2xl w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
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
                <tr class="align-top">
                  <td class="px-5 py-3.5 font-medium text-slate-800">#{{ $step->step_order }}{{ $step->is_current_step ? ' (current)' : '' }}</td>
                  <td class="px-5 py-3.5 font-medium text-slate-800">{{ $step->role?->display_name ?? $step->role?->name ?? '—' }}</td>
                  <td class="px-5 py-3.5">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ match (strtoupper((string) $step->status)) {
                      'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
                      'APPROVED' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
                      'REJECTED' => 'bg-rose-100 text-rose-700 border border-rose-200',
                      'REVISION', 'REVISION_REQUIRED' => 'bg-orange-100 text-orange-700 border border-orange-200',
                      default => 'bg-slate-100 text-slate-700 border border-slate-200',
                    } }}">{{ strtoupper((string) $step->status) }}</span>
                  </td>
                  <td class="px-5 py-3.5 text-slate-700">{{ $step->assignedTo?->full_name ?? '—' }}</td>
                  <td class="whitespace-nowrap px-5 py-3.5 font-medium text-slate-700">{{ optional($step->acted_at)->format('M d, Y g:i A') ?? '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @isset($workflowActionRoute)
          <form method="POST" action="{{ $workflowActionRoute }}" class="mx-6 mt-5 space-y-4">
            @csrf
            @method('PATCH')
            <div>
              <x-forms.label for="workflow_comments">Comments (optional)</x-forms.label>
              <x-forms.textarea id="workflow_comments" name="comments" rows="3" placeholder="Add rationale for this decision...">{{ old('comments') }}</x-forms.textarea>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="submit" name="action" value="approve" class="inline-flex rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve step</button>
              <button type="submit" name="action" value="revision" class="inline-flex rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600">Request revision</button>
              <button type="submit" name="action" value="reject" class="inline-flex rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Reject</button>
            </div>
          </form>
        @endisset

        @isset($workflowLogs)
          @if (($workflowLogs?->count() ?? 0) > 0)
            <div class="mx-6 mt-6">
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
        @endisset
      </div>
    @endif
  @endisset
</x-ui.card>
@endsection
