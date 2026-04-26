@extends('layouts.admin')

@section('title', 'Account details — NU Lipa SDAO')

@section('content')
@php
  $isOfficer = $account->role_type === 'ORG_OFFICER';
  $roleBadgeClass = match ($account->role_type) {
    'ORG_OFFICER' => 'bg-sky-100 text-sky-900 border border-sky-200',
    'APPROVER' => 'bg-violet-100 text-violet-900 border border-violet-200',
    'ADMIN' => 'bg-slate-200 text-slate-800 border border-slate-300',
    default => 'bg-slate-100 text-slate-700 border border-slate-200',
  };
@endphp

<div class="mb-5">
  <a href="{{ route('admin.accounts.index') }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
    </svg>
    Back to User Accounts
  </a>
</div>

<x-ui.card padding="p-6">
  <div class="mb-7 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
    <div>
      <h2 class="text-2xl font-bold text-slate-900">Field Review Checklist</h2>
      <p class="mt-1 text-sm text-slate-500">Read-only account details for admin review.</p>
    </div>
  </div>

  <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
    @foreach ($reviewableFields as $fieldKey => $field)
      <div class="rounded-2xl border border-slate-200 bg-slate-50/90 p-5 md:col-span-2">
        <div class="min-w-0 space-y-2">
          <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $field['label'] }}</dt>
          <dd class="text-base font-semibold leading-snug text-slate-900">{{ $field['value'] }}</dd>
        </div>
      </div>
    @endforeach
  </dl>
</x-ui.card>

@if ($isOfficer)
  <x-ui.card padding="p-6" class="mt-6">
    <h2 class="text-base font-bold text-slate-900">Officer validation</h2>
    <p class="mt-1 text-sm text-slate-500">Set validation outcome for this student officer&rsquo;s organization access.</p>

    <form method="POST" action="{{ route('admin.accounts.update', $account) }}" class="mt-5 space-y-5">
      @csrf
      @method('PATCH')

      <div>
        <x-forms.label for="validation_status" :required="true">Decision</x-forms.label>
        <x-forms.select id="validation_status" name="validation_status">
          <option value="APPROVED" @selected(old('validation_status', $account->officer_validation_status) === 'APPROVED')>Approved</option>
          <option value="ACTIVE" @selected(old('validation_status', $account->officer_validation_status) === 'ACTIVE')>Active</option>
          <option value="REJECTED" @selected(old('validation_status', $account->officer_validation_status) === 'REJECTED')>Rejected</option>
          <option value="REVISION_REQUIRED" @selected(old('validation_status', $account->officer_validation_status) === 'REVISION_REQUIRED')>Revision Required</option>
        </x-forms.select>
        @error('validation_status')
          <x-forms.error>{{ $message }}</x-forms.error>
        @enderror
      </div>

      <div>
        <x-forms.label for="validation_notes">Review notes</x-forms.label>
        <x-forms.textarea id="validation_notes" name="validation_notes" :rows="4">{{ old('validation_notes', $account->officer_validation_notes) }}</x-forms.textarea>
        @error('validation_notes')
          <x-forms.error>{{ $message }}</x-forms.error>
        @enderror
      </div>

      <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-5">
        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/40">
          Save validation decision
        </button>
      </div>
    </form>
  </x-ui.card>
@endif

@endsection
