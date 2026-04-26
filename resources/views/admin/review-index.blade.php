@extends('layouts.admin')

@section('title', $pageTitle . ' — NU Lipa SDAO')

@section('content')
@php
  $statusClass = function (string $status): string {
    return match ($status) {
      'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
      'UNDER_REVIEW', 'REVIEWED' => 'bg-blue-100 text-blue-700 border border-blue-200',
      'APPROVED' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
      'REJECTED' => 'bg-rose-100 text-rose-700 border border-rose-200',
      'REVISION', 'REVISION_REQUIRED' => 'bg-orange-100 text-orange-700 border border-orange-200',
      default => 'bg-slate-100 text-slate-700 border border-slate-200',
    };
  };

  $statusLabel = function (string $status): string {
    return str_replace('_', ' ', $status);
  };
@endphp

<x-ui.card padding="p-0" class="overflow-hidden">
  <x-ui.card-section-header
    :title="$pageTitle"
    :subtitle="$pageSubtitle ?? 'Review submitted records and open a row for full details.'"
    content-padding="px-6"
  />
  <div class="border-t border-slate-100 px-3 py-3 sm:px-5 sm:py-4">
    <div class="overflow-x-auto rounded-xl border border-slate-200">
    <table class="min-w-4xl w-full divide-y divide-slate-200 text-left text-sm">
      <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
        <tr>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Organization Name</th>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Submitted By</th>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Submission Date</th>
          <th class="whitespace-nowrap px-4 py-3 sm:px-5">Status</th>
          <th class="whitespace-nowrap px-4 py-3 text-right sm:px-5">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 bg-white">
        @forelse ($rows as $row)
          <tr class="align-top hover:bg-slate-50/80">
            <td class="px-4 py-3.5 font-semibold text-slate-900 sm:px-5">{{ $row['organization'] }}</td>
            <td class="px-4 py-3.5 font-medium text-slate-700 sm:px-5">{{ $row['submitted_by'] }}</td>
            <td class="whitespace-nowrap px-4 py-3.5 font-medium text-slate-700 sm:px-5">{{ $row['submission_date'] }}</td>
            <td class="px-4 py-3.5 sm:px-5">
              <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass($row['status']) }}">
                {{ $statusLabel($row['status']) }}
              </span>
            </td>
            <td class="px-4 py-3.5 text-right sm:px-5">
              <a href="{{ route($routeBase, $row['id']) }}" class="inline-flex rounded-xl border border-[#003E9F] px-3.5 py-2 text-xs font-semibold text-[#003E9F] transition hover:bg-[#003E9F] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#003E9F]/30">
                View
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="px-5 py-12 text-center sm:px-6">
              <div class="flex flex-col items-center gap-2">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
                  <svg class="h-7 w-7 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12H9.75m3 0H9.75m0 0v3m0-3v3m3-3v3M5.625 5.25H9.75m-4.125 0A2.625 2.625 0 0 0 3 7.875v8.25A2.625 2.625 0 0 0 5.625 18.75h12.75A2.625 2.625 0 0 0 21 16.125V7.875A2.625 2.625 0 0 0 18.375 5.25H5.625Z" />
                  </svg>
                </div>
                <p class="text-sm font-medium text-slate-700">No submissions found</p>
                <p class="text-sm text-slate-500">No submissions found yet for this module.</p>
              </div>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
    </div>
  </div>

  <div class="border-t border-slate-100 px-5 py-3 sm:px-6">
    {{ $rows->links() }}
  </div>
</x-ui.card>
@endsection
