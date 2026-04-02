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

<header class="mb-6">
  <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $pageTitle }}</h1>
  <p class="mt-1 text-sm text-slate-500">{{ $pageSubtitle }}</p>
</header>

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Organization Name</th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted By</th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Submission Date</th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
          <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        @forelse ($rows as $row)
          <tr class="hover:bg-slate-50/80">
            <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $row['organization'] }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $row['submitted_by'] }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ $row['submission_date'] }}</td>
            <td class="px-4 py-3">
              <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass($row['status']) }}">
                {{ $statusLabel($row['status']) }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <a href="{{ route($routeBase, $row['id']) }}" class="inline-flex rounded-lg border border-[#003E9F] px-3 py-1.5 text-xs font-semibold text-[#003E9F] transition hover:bg-[#003E9F] hover:text-white">
                View
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">No submissions found yet for this module.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="border-t border-slate-100 px-4 py-3">
    {{ $rows->links() }}
  </div>
</section>
@endsection

