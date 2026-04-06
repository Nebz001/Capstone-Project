@extends('layouts.admin')

@section('title', 'Account details — NU Lipa SDAO')

@section('content')
@php
  $isOfficer = $account->role_type === 'ORG_OFFICER';
  $statusClass = match ($account->officer_validation_status) {
    'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
    'APPROVED' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    'ACTIVE' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    'REJECTED' => 'bg-rose-100 text-rose-700 border border-rose-200',
    'REVISION_REQUIRED' => 'bg-orange-100 text-orange-700 border border-orange-200',
    default => 'bg-slate-100 text-slate-700 border border-slate-200',
  };
  $roleBadgeClass = match ($account->role_type) {
    'ORG_OFFICER' => 'bg-sky-100 text-sky-900 border border-sky-200',
    'APPROVER' => 'bg-violet-100 text-violet-900 border border-violet-200',
    'ADMIN' => 'bg-slate-200 text-slate-800 border border-slate-300',
    default => 'bg-slate-100 text-slate-700 border border-slate-200',
  };
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Account details</h1>
    <p class="mt-1 text-sm text-slate-500">
      @if ($isOfficer)
        Review student officer validation and organization linkage.
      @else
        View account information and role assignment.
      @endif
    </p>
  </div>
  <div class="flex flex-wrap items-center gap-2">
    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $roleBadgeClass }}">
      {{ $account->roleDisplayLabel() }}
    </span>
    @if ($isOfficer)
      <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
        {{ str_replace('_', ' ', $account->officer_validation_status) }}
      </span>
    @endif
  </div>
</div>

<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
  <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Full name</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ $account->full_name }}</dd>
    </div>
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">School ID</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ $account->school_id }}</dd>
    </div>
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ $account->email }}</dd>
    </div>
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Account status</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ $account->account_status }}</dd>
    </div>
    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 md:col-span-2">
      <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date registered</dt>
      <dd class="mt-1 text-sm text-slate-800">{{ optional($account->created_at)->format('M d, Y h:i A') ?? 'N/A' }}</dd>
    </div>

    @if ($isOfficer)
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Linked organization</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $latestOfficerRecord?->organization?->organization_name ?? 'Not linked' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Position / officer role</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $latestOfficerRecord?->position_title ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Officer validation status</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ str_replace('_', ' ', $account->officer_validation_status) }}</dd>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 md:col-span-2">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Validation notes</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $account->officer_validation_notes ?: 'No notes provided yet.' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 md:col-span-2">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last reviewed by</dt>
        <dd class="mt-1 text-sm text-slate-800">
          {{ $account->validatedBy?->full_name ?? 'Not reviewed yet' }}
          @if ($account->officer_validated_at)
            · {{ $account->officer_validated_at->format('M d, Y h:i A') }}
          @endif
        </dd>
      </div>
    @endif
  </dl>
</section>

@if ($isOfficer)
  <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-bold text-slate-900">Officer validation</h2>
    <p class="mt-1 text-sm text-slate-500">Set validation outcome for this student officer&rsquo;s organization access.</p>

    <form method="POST" action="{{ route('admin.accounts.update', $account) }}" class="mt-4 space-y-4">
      @csrf
      @method('PATCH')

      <div>
        <label for="validation_status" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Decision</label>
        <select id="validation_status" name="validation_status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20">
          <option value="APPROVED" @selected(old('validation_status', $account->officer_validation_status) === 'APPROVED')>Approved</option>
          <option value="ACTIVE" @selected(old('validation_status', $account->officer_validation_status) === 'ACTIVE')>Active</option>
          <option value="REJECTED" @selected(old('validation_status', $account->officer_validation_status) === 'REJECTED')>Rejected</option>
          <option value="REVISION_REQUIRED" @selected(old('validation_status', $account->officer_validation_status) === 'REVISION_REQUIRED')>Revision Required</option>
        </select>
        @error('validation_status')
          <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label for="validation_notes" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Review notes</label>
        <textarea id="validation_notes" name="validation_notes" rows="4" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20">{{ old('validation_notes', $account->officer_validation_notes) }}</textarea>
        @error('validation_notes')
          <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
      </div>

      <div class="flex flex-wrap gap-3">
        <button type="submit" class="inline-flex rounded-lg border border-[#003E9F] bg-[#003E9F] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#00327f]">
          Save validation decision
        </button>
        <a href="{{ route('admin.accounts.index') }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
          Back to Account Management
        </a>
      </div>
    </form>
  </section>
@else
  <div class="mt-6">
    <a href="{{ route('admin.accounts.index') }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
      Back to Account Management
    </a>
  </div>
@endif
@endsection
