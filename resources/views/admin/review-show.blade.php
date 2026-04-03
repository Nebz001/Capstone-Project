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

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $pageTitle }}</h1>
    <p class="mt-1 text-sm text-slate-500">Inspect key submission details for review readiness.</p>
  </div>
  <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
    {{ str_replace('_', ' ', $status) }}
  </span>
</div>

@if (session('success'))
  <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="alert">
    {{ session('success') }}
  </div>
@endif

<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
  <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
    @foreach ($details as $label => $value)
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $value }}</dd>
      </div>
    @endforeach
  </dl>

  <div class="mt-6 flex flex-wrap gap-3">
    <a href="{{ $backRoute }}" class="inline-flex rounded-lg border border-[#003E9F] px-4 py-2 text-sm font-semibold text-[#003E9F] transition hover:bg-[#003E9F] hover:text-white">
      Back to Review List
    </a>
  </div>

  @isset($organization)
    @if ($organization)
      <div class="mt-10 border-t border-slate-200 pt-8">
        <h2 class="text-base font-semibold text-slate-900">Organization profile (SDAO)</h2>
        <p class="mt-1 text-sm text-slate-600">
          Request a profile revision when the organization must update registered organization details or adviser information. Officers can edit only while this is active and the organization is not pending review.
        </p>
        @if ($organization->profile_information_revision_requested)
          <p class="mt-2 text-sm font-medium text-amber-800">A profile revision is currently <span class="font-semibold">open</span> for this organization.</p>
        @endif
        <form method="POST" action="{{ route('admin.organizations.request-profile-revision', $organization) }}" class="mt-4 space-y-3">
          @csrf
          <div>
            <label for="profile_revision_notes" class="mb-1 block text-xs font-medium text-slate-600">Optional notes to the organization (shown on their profile)</label>
            <textarea
              id="profile_revision_notes"
              name="profile_revision_notes"
              rows="3"
              class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
              placeholder="e.g., Please update your college department and adviser name to match current records."
            >{{ old('profile_revision_notes', $organization->profile_revision_notes) }}</textarea>
          </div>
          <button type="submit" class="inline-flex rounded-lg bg-[#003E9F] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#00327F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/40">
            Request organization profile revision
          </button>
        </form>
      </div>
    @endif
  @endisset
</section>
@endsection
