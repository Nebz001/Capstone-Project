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
  $totalReviewableFields = is_countable($reviewableFields ?? null) ? count($reviewableFields) : 0;
  $approvedReviewableFields = collect($fieldReviews ?? [])
    ->filter(fn ($review) => (($review['status'] ?? 'pending') === 'approved'))
    ->count();
  $allFieldsApproved = $totalReviewableFields > 0 && $approvedReviewableFields === $totalReviewableFields;
@endphp

<x-ui.card padding="p-6" class="mb-6">
  <h2 class="text-base font-bold text-slate-900">Account snapshot</h2>
  <div class="mt-4 grid grid-cols-1 gap-3.5 md:grid-cols-2">
    <div class="rounded-xl border border-slate-200 bg-slate-50/85 px-4 py-3">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Full name</p>
      <p class="mt-1.5 text-sm font-semibold text-slate-900">{{ $account->full_name }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-slate-50/85 px-4 py-3">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">School ID</p>
      <p class="mt-1.5 text-sm font-semibold text-slate-900">{{ $account->school_id ?: '—' }}</p>
    </div>
  </div>
</x-ui.card>

<x-ui.card padding="p-6">
  <div class="mb-7 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
    <div>
      <h2 class="text-lg font-bold text-slate-900">Field Review Checklist</h2>
      <p class="mt-1 text-sm text-slate-500">Review each field individually and request revisions when needed.</p>
    </div>
    @php
      $reviewProgressText = $allFieldsApproved
        ? 'Approved'
        : ($approvedReviewableFields.'/'.$totalReviewableFields.' Approved');
      $reviewProgressClass = $allFieldsApproved
        ? 'border border-emerald-300 bg-emerald-50 text-emerald-800'
        : 'border border-amber-300 bg-amber-50 text-amber-800';
    @endphp
    <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-xs font-semibold leading-none {{ $reviewProgressClass }}">
      {{ $reviewProgressText }}
    </span>
  </div>

  <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
    @foreach ($reviewableFields as $fieldKey => $field)
      @php
        $review = $fieldReviews[$fieldKey] ?? ['status' => 'pending', 'message' => null];
        $isApprovedField = (($review['status'] ?? 'pending') === 'approved');
        $fieldCardClass = $isApprovedField
          ? 'border border-emerald-300 bg-emerald-100/60'
          : 'border border-slate-200 bg-slate-50/90';
      @endphp
      <div class="rounded-2xl p-5 md:col-span-2 {{ $fieldCardClass }}">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0 space-y-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $field['label'] }}</dt>
            <dd class="text-base font-semibold leading-snug text-slate-900">{{ $field['value'] }}</dd>
          </div>
          <div class="flex flex-wrap items-center justify-end gap-2">
            <form method="POST" action="{{ route('admin.accounts.field-review', $account) }}">
              @csrf
              @method('PATCH')
              <input type="hidden" name="field_key" value="{{ $fieldKey }}">
              <input type="hidden" name="action_type" value="approve">
              <button
                type="submit"
                @disabled($isApprovedField)
                style="{{ $isApprovedField ? 'background-color:#047857;border-color:#047857;color:#FFFFFF;' : '' }}"
                class="inline-flex items-center rounded-xl border px-4 py-2.5 text-xs font-semibold shadow-sm focus:outline-none {{ $isApprovedField ? 'cursor-default' : 'border-transparent bg-emerald-600 text-white transition hover:bg-emerald-700 active:bg-emerald-800 focus:ring-4 focus:ring-emerald-500/25' }}"
              >
                @if ($isApprovedField)
                  <svg class="mr-1.5 h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                  </svg>
                  Approved
                @else
                  Approve
                @endif
              </button>
            </form>
            <button
              type="button"
              class="inline-flex items-center rounded-xl bg-orange-500 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-orange-600 active:bg-orange-700 focus:outline-none focus:ring-4 focus:ring-orange-500/25"
              data-revision-open
              data-field-key="{{ $fieldKey }}"
              data-field-label="{{ $field['label'] }}"
              data-current-message="{{ $review['message'] ?? '' }}"
            >
              Revision
            </button>
          </div>
        </div>

        @if (! empty($review['message']))
          <div class="mt-4 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3.5">
            <p class="text-[11px] font-bold uppercase tracking-wide text-orange-700">REVISION FEEDBACK</p>
            <p class="mt-2 text-sm leading-relaxed text-orange-800">{{ $review['message'] }}</p>
          </div>
        @endif
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

<div id="account-field-revision-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-950/50 px-4">
  <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/40">
    <h3 class="text-lg font-bold text-slate-900">Field revision feedback</h3>
    <p class="mt-1 text-sm text-slate-500">Add revision notes for this field.</p>
    <p id="account-field-revision-modal-field" class="mt-3 text-sm font-semibold text-slate-800"></p>

    <form method="POST" action="{{ route('admin.accounts.field-review', $account) }}" class="mt-4 space-y-4">
      @csrf
      @method('PATCH')
      <input type="hidden" name="field_key" id="account-field-revision-field-key" value="">
      <input type="hidden" name="action_type" value="revision">
      <div>
        <x-forms.label for="account-field-revision-message" :required="true">Revision message</x-forms.label>
        <x-forms.textarea id="account-field-revision-message" name="revision_message" :rows="4" required />
      </div>
      <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
        <button type="button" id="account-field-revision-cancel" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20">
          Cancel
        </button>
        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/40">
          Save revision
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  (() => {
    const modal = document.getElementById('account-field-revision-modal');
    const openButtons = document.querySelectorAll('[data-revision-open]');
    const cancelButton = document.getElementById('account-field-revision-cancel');
    const fieldLabelEl = document.getElementById('account-field-revision-modal-field');
    const fieldKeyInput = document.getElementById('account-field-revision-field-key');
    const messageInput = document.getElementById('account-field-revision-message');
    if (!modal || !cancelButton || !fieldLabelEl || !fieldKeyInput || !messageInput || openButtons.length === 0) return;

    const closeModal = () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    };
    const openModal = (button) => {
      fieldKeyInput.value = button.dataset.fieldKey || '';
      fieldLabelEl.textContent = button.dataset.fieldLabel || '';
      messageInput.value = button.dataset.currentMessage || '';
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      setTimeout(() => messageInput.focus(), 0);
    };

    openButtons.forEach((button) => {
      button.addEventListener('click', () => openModal(button));
    });
    cancelButton.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
      if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') closeModal();
    });
  })();
</script>
@endsection
