@extends('layouts.admin')

@section('title', 'Officer Validation Review — NU Lipa SDAO')

@section('content')
@php
  $statusClass = match ($studentOfficer->officer_validation_status) {
    'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
    'APPROVED' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    'REJECTED' => 'bg-rose-100 text-rose-700 border border-rose-200',
    'REVISION_REQUIRED' => 'bg-orange-100 text-orange-700 border border-orange-200',
    default => 'bg-slate-100 text-slate-700 border border-slate-200',
  };
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Officer Validation Review</h1>
    <p class="mt-1 text-sm text-slate-500">Validate whether this student account should be granted officer-level access.</p>
  </div>
  <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
    {{ str_replace('_', ' ', $studentOfficer->officer_validation_status) }}
  </span>
</div>

<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
  <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Student Name</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ $studentOfficer->full_name }}</dd>
    </div>
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">School ID</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ $studentOfficer->school_id }}</dd>
    </div>
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">School Email</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ $studentOfficer->email }}</dd>
    </div>
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Linked Organization</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ $latestOfficerRecord?->organization?->organization_name ?? 'Not linked' }}</dd>
    </div>
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Position / Officer Role</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ $latestOfficerRecord?->position_title ?? 'N/A' }}</dd>
    </div>
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Account Status</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ $studentOfficer->account_status }}</dd>
    </div>
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Validation Status</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ str_replace('_', ' ', $studentOfficer->officer_validation_status) }}</dd>
    </div>
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date Registered</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ optional($studentOfficer->created_at)->format('M d, Y h:i A') ?? 'N/A' }}</dd>
    </div>
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 md:col-span-2">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Validation Notes</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ $studentOfficer->officer_validation_notes ?: 'No notes provided yet.' }}</dd>
    </div>
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 md:col-span-2">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last Reviewed By</dt>
      <dd class="mt-1 text-sm text-slate-800">
        {{ $studentOfficer->validatedBy?->full_name ?? 'Not reviewed yet' }}
        @if ($studentOfficer->officer_validated_at)
          · {{ $studentOfficer->officer_validated_at->format('M d, Y h:i A') }}
        @endif
      </dd>
    </div>
  </dl>
</section>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
  <h2 class="text-lg font-bold text-slate-900">Validation Decision</h2>
  <p class="mt-1 text-sm text-slate-500">Set account validation outcome for officer-level organization access.</p>

  <form method="POST" action="{{ route('admin.officer-accounts.update', $studentOfficer) }}" class="mt-4 space-y-4">
    @csrf
    @method('PATCH')

    <div>
      <label for="validation_status" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Decision</label>
      <select id="validation_status" name="validation_status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20">
        <option value="APPROVED" @selected(old('validation_status', $studentOfficer->officer_validation_status) === 'APPROVED')>Approved</option>
        <option value="REJECTED" @selected(old('validation_status', $studentOfficer->officer_validation_status) === 'REJECTED')>Rejected</option>
        <option value="REVISION_REQUIRED" @selected(old('validation_status', $studentOfficer->officer_validation_status) === 'REVISION_REQUIRED')>Revision Required</option>
      </select>
      @error('validation_status')
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
      @enderror
    </div>

    <div>
      <label for="validation_notes" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Review Notes</label>
      <textarea id="validation_notes" name="validation_notes" rows="4" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20">{{ old('validation_notes', $studentOfficer->officer_validation_notes) }}</textarea>
      @error('validation_notes')
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
      @enderror
    </div>

    <div class="flex flex-wrap gap-3">
      <button type="submit" class="inline-flex rounded-lg border border-[#003E9F] bg-[#003E9F] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#00327f]">
        Save Validation Decision
      </button>
      <a href="{{ route('admin.officer-accounts.index') }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
        Back to Officer Accounts
      </a>
    </div>
  </form>
</section>
@endsection

