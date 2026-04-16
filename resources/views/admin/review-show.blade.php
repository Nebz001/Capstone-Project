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

<x-ui.card padding="p-6">
  <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
    @foreach ($details as $label => $value)
      <div class="rounded-2xl border border-slate-200 bg-slate-50/90 p-5">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">{{ $label }}</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $value }}</dd>
      </div>
    @endforeach
  </dl>

  @isset($calendarEntries)
    @if ($calendarEntries->isNotEmpty())
      <div class="mt-8 border-t border-slate-100 pt-8">
        <h2 class="text-base font-bold text-slate-900">Submitted activities</h2>
        <p class="mt-1 text-sm text-slate-500">Each row is a saved record linked to this activity calendar.</p>
        <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="whitespace-nowrap px-5 py-3">Date</th>
                <th class="min-w-[10rem] px-5 py-3">Activity</th>
                <th class="whitespace-nowrap px-5 py-3">SDG</th>
                <th class="min-w-[8rem] px-5 py-3">Venue</th>
                <th class="min-w-[12rem] px-5 py-3">Participants / program</th>
                <th class="whitespace-nowrap px-5 py-3">Budget</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              @foreach ($calendarEntries as $row)
                <tr class="align-top hover:bg-slate-50/80">
                  <td class="whitespace-nowrap px-5 py-3 text-slate-800">{{ optional($row->activity_date)->format('M d, Y') ?? '—' }}</td>
                  <td class="px-5 py-3 text-slate-800">{{ $row->activity_name }}</td>
                  <td class="whitespace-nowrap px-5 py-3 text-slate-800">{{ $row->sdg }}</td>
                  <td class="px-5 py-3 text-slate-800">{{ $row->venue }}</td>
                  <td class="px-5 py-3 text-slate-700">{{ $row->participant_program }}</td>
                  <td class="whitespace-nowrap px-5 py-3 text-slate-800">{{ $row->budget }}</td>
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
      <div class="mt-8 border-t border-slate-100 pt-8">
        <h2 class="text-base font-bold text-slate-900">Organization profile (SDAO)</h2>
        <p class="mt-1 text-sm text-slate-500">
          Request a profile revision when the organization must update registered organization details or adviser information. Officers can edit only while this is active and the organization is not pending review.
        </p>
        @if ($organization->profile_information_revision_requested)
          <x-feedback.blocked-message class="mt-4">
            A profile revision is currently <span class="font-semibold">open</span> for this organization.
          </x-feedback.blocked-message>
        @endif
        <form method="POST" action="{{ route('admin.organizations.request-profile-revision', $organization) }}" class="mt-4 space-y-4">
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
</x-ui.card>
@endsection
