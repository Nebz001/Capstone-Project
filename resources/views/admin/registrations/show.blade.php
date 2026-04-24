@extends('layouts.admin')

@section('title', 'Registration Submission Details — NU Lipa SDAO')

@section('content')
@php
  $registration = $submission ?? $registration ?? null;
  $registrationMissing = $registration === null;
  $initialSectionReviewState = $initialSectionReviewState ?? [
    'application' => 'pending',
    'contact' => 'pending',
    'organizational' => 'pending',
    'requirements' => 'pending',
  ];
  $defaultDecision = old(
    'decision',
    strtolower((string) ($registration?->status ?? '')) === 'rejected' ? 'REJECTED' : 'APPROVED',
  );
  $status = strtoupper((string) ($registration?->status ?? 'PENDING'));
  $statusClass = match ($status) {
    'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
    'UNDER_REVIEW', 'REVIEWED' => 'bg-blue-100 text-blue-700 border border-blue-200',
    'APPROVED' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    'REJECTED' => 'bg-rose-100 text-rose-700 border border-rose-200',
    'REVISION', 'REVISION_REQUIRED' => 'bg-orange-100 text-orange-700 border border-orange-200',
    default => 'bg-slate-100 text-slate-700 border border-slate-200',
  };
  $org = $registration?->organization;
  $reqLabels = [
    'letter_of_intent' => 'Letter of Intent',
    'application_form' => 'Application Form',
    'by_laws' => 'By Laws of the Organization',
    'updated_list_of_officers_founders' => 'Updated List of Officers/Founders',
    'dean_endorsement_faculty_adviser' => 'Letter from the School Dean endorsing the Faculty Adviser',
    'proposed_projects_budget' => 'List of Proposed Projects with Proposed Budget for the AY',
    'others' => 'Others',
  ];
  $requirementRows = $registration?->requirements?->keyBy('requirement_key') ?? collect();
  $requirementKeys = \App\Models\SubmissionRequirement::requirementKeysForType(\App\Models\OrganizationSubmission::TYPE_REGISTRATION);
  $requirementAttachmentKeys = ($registration?->attachments ?? collect())
    ->pluck('file_type')
    ->filter(fn ($type) => is_string($type) && str_starts_with($type, \App\Models\Attachment::TYPE_REGISTRATION_REQUIREMENT.':'))
    ->map(fn (string $type): string => (string) \Illuminate\Support\Str::after($type, \App\Models\Attachment::TYPE_REGISTRATION_REQUIREMENT.':'))
    ->values()
    ->all();
  $orgTypeRaw = strtolower((string) ($org?->organization_type ?? ''));
  $orgTypeLabel = match ($orgTypeRaw) {
    'co_curricular' => 'Co-Curricular Organization',
    'extra_curricular' => 'Extra-Curricular Organization / Interest Club',
    default => $org?->organization_type ? (string) $org->organization_type : 'N/A',
  };
@endphp

@if ($registrationMissing)
  <x-feedback.blocked-message
    variant="error"
    class="mb-6"
    message="Registration submission data is unavailable for this record."
  />
  <x-ui.card padding="p-6">
    <a href="{{ route('admin.registrations.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20">
      Back to list
    </a>
  </x-ui.card>
@else
<form
  id="registration-review-form"
  method="POST"
  action="{{ route('admin.registrations.update-status', $submission ?? $registration) }}"
  class="space-y-4"
  data-confirmed="0"
>
  @csrf
  @method('PATCH')

  @error('decision')
    <x-forms.error>{{ $message }}</x-forms.error>
  @enderror
  @error('section_review')
    <x-feedback.blocked-message variant="error" :message="$message" />
  @enderror

  {{-- Application Information --}}
  <x-ui.card padding="p-5">
    <h2 class="text-base font-bold text-slate-900">Application Information</h2>
    <p class="mt-1 text-sm text-slate-500">Academic year and submission context for this registration.</p>
    <dl class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
      <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">Academic Year</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $registration->academicTerm?->academic_year ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">Submission Date</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ optional($registration->submission_date)->format('M d, Y') ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">Submitted By</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $registration->user?->full_name ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">Organization</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $org?->organization_name ?? 'N/A' }}</dd>
      </div>
    </dl>
    @include('admin.registrations.partials.section-review-toolbar', [
      'sectionKey' => 'application',
      'sectionTitle' => 'Application Information',
      'revisionFieldName' => 'revision_comment_application',
      'revisionTextareaId' => 'revision-comment-application',
      'registration' => $registration,
      'initialSectionReviewState' => $initialSectionReviewState,
    ])
  </x-ui.card>

  {{-- Contact Information --}}
  <x-ui.card padding="p-5">
    <h2 class="text-base font-bold text-slate-900">Contact Information</h2>
    <p class="mt-1 text-sm text-slate-500">Primary contact details as submitted on the registration form.</p>
    <dl class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
      <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4 md:col-span-2">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">Organization Name</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $org?->organization_name ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">Contact Person</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $registration->contact_person ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">Contact No.</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $registration->contact_no ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4 md:col-span-2">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">Email Address</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $registration->contact_email ?? 'N/A' }}</dd>
      </div>
    </dl>
    @include('admin.registrations.partials.section-review-toolbar', [
      'sectionKey' => 'contact',
      'sectionTitle' => 'Contact Information',
      'revisionFieldName' => 'revision_comment_contact',
      'revisionTextareaId' => 'revision-comment-contact',
      'registration' => $registration,
      'initialSectionReviewState' => $initialSectionReviewState,
    ])
  </x-ui.card>

  {{-- Organizational Details --}}
  <x-ui.card padding="p-5">
    <h2 class="text-base font-bold text-slate-900">Organizational Details</h2>
    <p class="mt-1 text-sm text-slate-500">Organization profile data at the time of submission (from the linked organization record).</p>
    <dl class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
      <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">Date Organized</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $org?->founded_date?->format('M d, Y') ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">Type of Organization</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $orgTypeLabel }}</dd>
      </div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4 md:col-span-2">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">School</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $org?->college_department ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4 md:col-span-2">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">Purpose of Organization</dt>
        <dd class="mt-2 whitespace-pre-wrap text-sm font-medium text-slate-900">{{ $org?->purpose ?? 'N/A' }}</dd>
      </div>
    </dl>
    @include('admin.registrations.partials.section-review-toolbar', [
      'sectionKey' => 'organizational',
      'sectionTitle' => 'Organizational Details',
      'revisionFieldName' => 'revision_comment_organizational',
      'revisionTextareaId' => 'revision-comment-organizational',
      'registration' => $registration,
      'initialSectionReviewState' => $initialSectionReviewState,
    ])
  </x-ui.card>

  {{-- Requirements Attached --}}
  <x-ui.card padding="p-5">
    <h2 class="text-base font-bold text-slate-900">Requirements Attached</h2>
    <p class="mt-1 text-sm text-slate-500">Checklist and uploaded files as declared on the application.</p>
    <ul class="mt-4 space-y-3">
      @foreach ($requirementKeys as $key)
        @php
          $requirement = $requirementRows->get($key);
          $checked = (bool) ($requirement?->is_submitted ?? false);
          $hasFile = $checked && in_array($key, $requirementAttachmentKeys, true);
        @endphp
        <li class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-50/90 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-900">{{ $requirement?->label ?? ($reqLabels[$key] ?? $key) }}</p>
            <p class="mt-0.5 text-xs text-slate-500">Marked as submitted: <span class="font-semibold text-slate-700">{{ $checked ? 'Yes' : 'No' }}</span></p>
          </div>
          <div class="shrink-0">
            @if ($hasFile)
              <a
                href="{{ route('admin.registrations.requirement-file', ['submission' => ($submission ?? $registration), 'key' => $key]) }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex rounded-xl border border-[#003E9F] bg-white px-3.5 py-2 text-xs font-semibold text-[#003E9F] transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#003E9F]/30"
              >
                View file
              </a>
            @elseif ($checked)
              <span class="text-xs font-medium text-amber-700">Marked yes — no file on record</span>
            @endif
          </div>
        </li>
      @endforeach
    </ul>
    @include('admin.registrations.partials.section-review-toolbar', [
      'sectionKey' => 'requirements',
      'sectionTitle' => 'Requirements Attached',
      'revisionFieldName' => 'revision_comment_requirements',
      'revisionTextareaId' => 'revision-comment-requirements',
      'registration' => $registration,
      'initialSectionReviewState' => $initialSectionReviewState,
    ])
  </x-ui.card>

  {{-- Review decision --}}
  <x-ui.card padding="p-5">
    <h2 class="text-base font-bold text-slate-900">Finalize review</h2>
    <p class="mt-1 text-sm text-slate-500">
      With <span class="font-semibold text-slate-800">Submit review</span>, the system <span class="font-semibold text-emerald-700">approves</span> only when every section is Verified. If any section is Need revision (with feedback), the registration returns for updates and profile editing is unlocked for the officer.
    </p>
    <p id="section-review-summary" class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" role="status"></p>

    <fieldset class="mt-6">
      <legend class="text-xs font-semibold uppercase tracking-wide text-slate-700">Outcome</legend>
      <div class="mt-3 flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-100/70 p-3 sm:flex-row sm:flex-wrap sm:gap-3">
        <label class="relative flex flex-1 cursor-pointer items-center gap-3 rounded-2xl border-2 border-slate-200 bg-white px-4 py-3 transition has-[:checked]:border-[#003E9F] has-[:checked]:bg-blue-50">
          <input type="radio" name="decision" value="APPROVED" class="sr-only" {{ $defaultDecision === 'APPROVED' ? 'checked' : '' }} />
          <span class="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-blue-100 text-[#003E9F]">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15l3-3m6 3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
          </span>
          <span>
            <span class="block text-sm font-bold text-slate-900">Submit review</span>
            <span class="block text-xs text-slate-500">Auto-approve if all verified, or request revision</span>
          </span>
        </label>
        <label class="relative flex flex-1 cursor-pointer items-center gap-3 rounded-2xl border-2 border-slate-200 bg-white px-4 py-3 transition has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50">
          <input type="radio" name="decision" value="REJECTED" class="sr-only" {{ $defaultDecision === 'REJECTED' ? 'checked' : '' }} />
          <span class="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-rose-100 text-rose-700">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
          </span>
          <span>
            <span class="block text-sm font-bold text-slate-900">Reject</span>
            <span class="block text-xs text-slate-500">Decline with a reason (section review not required)</span>
          </span>
        </label>
      </div>
    </fieldset>

    <div class="mt-6">
      <x-forms.label for="registration-remarks">
        General remarks / instructions
        <span id="registration-remarks-required" class="hidden font-normal normal-case text-rose-600">(required for rejection)</span>
        <span id="registration-revision-hint" class="hidden font-normal normal-case text-amber-700">(optional if every Need revision section has feedback — otherwise at least 3 characters)</span>
        <span id="registration-remarks-optional" class="font-normal normal-case text-slate-400">(optional when all sections are verified)</span>
      </x-forms.label>
      <x-forms.textarea
        id="registration-remarks"
        name="remarks"
        :rows="4"
        placeholder="Optional overall context. Section-specific feedback is added via Need revision on each section."
      >{{ old('remarks', $registration->additional_remarks ?? $registration->notes) }}</x-forms.textarea>
      @error('remarks')
        <x-forms.error>{{ $message }}</x-forms.error>
      @enderror
    </div>

    <div class="mt-6 flex flex-wrap gap-3 border-t border-slate-100 pt-5">
      <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/40">
        Save outcome
      </button>
      <a href="{{ route('admin.registrations.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20">
        Back to list
      </a>
    </div>
  </x-ui.card>
</form>

<div id="section-revision-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-950/50 px-4">
  <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/40">
    <h3 id="section-revision-modal-title" class="text-lg font-bold text-slate-900">Section feedback</h3>
    <p class="mt-1 text-sm text-slate-500">Describe what must be corrected for this section only. The submitter will see it labeled by section.</p>
    <div class="mt-4">
      <x-forms.label for="section-revision-modal-body" :required="true">Revision details</x-forms.label>
      <x-forms.textarea
        id="section-revision-modal-body"
        :rows="5"
        placeholder="Be specific (e.g. upload a clearer scan, fix the contact number format, expand the purpose statement)."
      />
    </div>
    <div class="mt-5 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4">
      <button type="button" id="section-revision-modal-cancel" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20">
        Cancel
      </button>
      <button type="button" id="section-revision-modal-save" class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/40">
        Save for this section
      </button>
    </div>
  </div>
</div>

<div id="registration-decision-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-950/50 px-4">
  <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/40">
    <h3 class="text-lg font-bold text-slate-900">Confirm</h3>
    <p id="registration-decision-modal-text" class="mt-1 text-sm text-slate-600"></p>
    <div class="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
      <button type="button" id="registration-decision-cancel" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20">
        Cancel
      </button>
      <button type="button" id="registration-decision-confirm" class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/40">
        Confirm &amp; save
      </button>
    </div>
  </div>
</div>

<script>
  (() => {
    const form = document.getElementById('registration-review-form');
    const modal = document.getElementById('registration-decision-modal');
    const modalText = document.getElementById('registration-decision-modal-text');
    const cancel = document.getElementById('registration-decision-cancel');
    const confirmBtn = document.getElementById('registration-decision-confirm');
    const remarks = document.getElementById('registration-remarks');
    const reqReject = document.getElementById('registration-remarks-required');
    const reqRevision = document.getElementById('registration-revision-hint');
    const optLabel = document.getElementById('registration-remarks-optional');
    const summaryEl = document.getElementById('section-review-summary');
    const revModal = document.getElementById('section-revision-modal');
    const revTitle = document.getElementById('section-revision-modal-title');
    const revBody = document.getElementById('section-revision-modal-body');
    const revCancel = document.getElementById('section-revision-modal-cancel');
    const revSave = document.getElementById('section-revision-modal-save');

    if (!form || !modal || !modalText || !cancel || !confirmBtn || !remarks || !reqReject || !reqRevision || !optLabel || !summaryEl || !revModal || !revTitle || !revBody || !revCancel || !revSave) return;

    let activeSectionRoot = null;

    function selectedDecision() {
      const r = form.querySelector('input[name="decision"]:checked');
      return r ? r.value : '';
    }

    function getStateInputs() {
      return Array.from(form.querySelectorAll('.section-review-state'));
    }

    function getHiddenRevisionTextarea(root) {
      const id = root.dataset.revisionInputId;
      return id ? document.getElementById(id) : null;
    }

    function refreshSection(root) {
      const stateEl = root.querySelector('.section-review-state');
      const statusEl = root.querySelector('.section-review-status');
      const hintEl = root.querySelector('.section-review-hint');
      const btnV = root.querySelector('.section-review-btn-verified');
      const btnR = root.querySelector('.section-review-btn-revision');
      const btnE = root.querySelector('.section-review-btn-edit');
      const card = root.closest('section, .rounded-3xl');
      const state = stateEl ? stateEl.value : 'pending';
      const ta = getHiddenRevisionTextarea(root);
      const text = ta ? ta.value.trim() : '';

      const title = root.dataset.sectionTitle || 'This section';

      if (statusEl) {
        if (state === 'validated') statusEl.textContent = 'Verified — no issues noted for this section.';
        else if (state === 'revision') statusEl.textContent = 'Need revision — feedback recorded for this section.';
        else statusEl.textContent = 'Pending — choose Verified or Need revision.';
      }
      if (hintEl) {
        if (state === 'validated') hintEl.textContent = 'You can change your choice anytime before saving.';
        else if (state === 'revision') {
          hintEl.textContent = text.length >= 3
            ? 'Submitter will see this note under "' + title + '".'
            : 'Add at least 3 characters of feedback (use Need revision or Edit note).';
        } else hintEl.textContent = 'Both options are required for every section before you can submit review.';
      }

      if (btnV) {
        btnV.classList.toggle('ring-2', state === 'validated');
        btnV.classList.toggle('ring-emerald-500', state === 'validated');
        btnV.classList.toggle('bg-emerald-50', state === 'validated');
      }
      if (btnR) {
        btnR.classList.toggle('ring-2', state === 'revision');
        btnR.classList.toggle('ring-amber-500', state === 'revision');
        btnR.classList.toggle('bg-amber-50', state === 'revision');
      }
      if (btnE) btnE.classList.toggle('hidden', state !== 'revision');

      if (card) {
        card.classList.toggle('border-amber-300', state === 'revision');
        card.classList.toggle('shadow-md', state === 'revision');
      }
    }

    function refreshAllSections() {
      form.querySelectorAll('.section-review').forEach(refreshSection);
      updateSummary();
      updateRemarksHint();
    }

    function updateSummary() {
      const inputs = getStateInputs();
      let v = 0;
      let r = 0;
      let p = 0;
      inputs.forEach((el) => {
        if (el.value === 'validated') v += 1;
        else if (el.value === 'revision') r += 1;
        else p += 1;
      });
      const total = inputs.length || 4;
      if (p > 0) {
        summaryEl.textContent = `${p} section(s) still pending. Mark each as Verified or Need revision before saving. (${v} verified, ${r} need revision)`;
        summaryEl.className = 'mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900';
      } else if (r > 0) {
        summaryEl.textContent = `Ready to send for revision: ${r} section(s) need changes. Add section feedback (and optional general remarks), then save. (${v} verified)`;
        summaryEl.className = 'mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900';
      } else {
        summaryEl.textContent = `All ${total} sections verified. Saving will approve this registration (unless you choose Reject).`;
        summaryEl.className = 'mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900';
      }
    }

    function anyRevisionSection() {
      return getStateInputs().some((el) => el.value === 'revision');
    }

    function everyRevisionHasComment() {
      const roots = form.querySelectorAll('.section-review');
      let ok = true;
      roots.forEach((root) => {
        const stateEl = root.querySelector('.section-review-state');
        if (stateEl && stateEl.value === 'revision') {
          const ta = getHiddenRevisionTextarea(root);
          if (!ta || ta.value.trim().length < 3) ok = false;
        }
      });
      return ok;
    }

    function revisionRemarksSatisfied() {
      const g = remarks.value.trim().length >= 3;
      return g || everyRevisionHasComment();
    }

    function updateRemarksHint() {
      const d = selectedDecision();
      const rev = d === 'APPROVED' && anyRevisionSection();
      reqReject.classList.toggle('hidden', d !== 'REJECTED');
      reqRevision.classList.toggle('hidden', !rev);
      optLabel.classList.toggle('hidden', d === 'REJECTED' || rev);
    }

    function openRevModal(root) {
      activeSectionRoot = root;
      const title = root.dataset.sectionTitle || 'Section';
      revTitle.textContent = 'Feedback: ' + title;
      const ta = getHiddenRevisionTextarea(root);
      revBody.value = ta ? ta.value : '';
      revModal.classList.remove('hidden');
      revModal.classList.add('flex');
      revBody.focus();
    }

    function closeRevModal() {
      activeSectionRoot = null;
      revModal.classList.add('hidden');
      revModal.classList.remove('flex');
      revBody.value = '';
    }

    form.querySelectorAll('.section-review').forEach((root) => {
      root.querySelector('.section-review-btn-verified')?.addEventListener('click', () => {
        const stateEl = root.querySelector('.section-review-state');
        const ta = getHiddenRevisionTextarea(root);
        if (stateEl) stateEl.value = 'validated';
        if (ta) ta.value = '';
        refreshSection(root);
        updateSummary();
        updateRemarksHint();
      });
      root.querySelector('.section-review-btn-revision')?.addEventListener('click', () => openRevModal(root));
      root.querySelector('.section-review-btn-edit')?.addEventListener('click', () => openRevModal(root));
    });

    revCancel.addEventListener('click', closeRevModal);
    revModal.addEventListener('click', (e) => { if (e.target === revModal) closeRevModal(); });
    revSave.addEventListener('click', () => {
      if (!activeSectionRoot) return;
      const stateEl = activeSectionRoot.querySelector('.section-review-state');
      const ta = getHiddenRevisionTextarea(activeSectionRoot);
      if (stateEl) stateEl.value = 'revision';
      if (ta) ta.value = revBody.value;
      refreshSection(activeSectionRoot);
      updateSummary();
      updateRemarksHint();
      closeRevModal();
    });

    form.querySelectorAll('input[name="decision"]').forEach((el) => el.addEventListener('change', () => {
      updateRemarksHint();
    }));
    refreshAllSections();

    const closeDecision = () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    };

    cancel.addEventListener('click', closeDecision);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeDecision(); });
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      if (!revModal.classList.contains('hidden')) closeRevModal();
      else closeDecision();
    });

    form.addEventListener('submit', (e) => {
      if (form.dataset.confirmed === '1') return;

      e.preventDefault();
      const d = selectedDecision();
      if (!d) {
        alert('Please choose Submit review or Reject before saving.');
        return;
      }
      const text = remarks.value.trim();
      if (d === 'REJECTED' && text.length < 3) {
        alert('Please provide rejection remarks (at least a few characters).');
        remarks.focus();
        return;
      }
      if (d === 'APPROVED') {
        if (getStateInputs().some((el) => el.value === 'pending')) {
          alert('Review every section: mark each as Verified or Need revision before submitting.');
          return;
        }
        if (anyRevisionSection() && !everyRevisionHasComment()) {
          alert('Each section marked Need revision needs feedback (at least 3 characters). Open the modal for that section or use Edit note.');
          return;
        }
        if (anyRevisionSection() && !revisionRemarksSatisfied()) {
          alert('For revision, add general remarks (at least 3 characters) or ensure every Need revision section has feedback (at least 3 characters).');
          remarks.focus();
          return;
        }
        if (anyRevisionSection()) {
          modalText.textContent = 'Send this registration back for revision using your section feedback? Profile editing will be unlocked for the officer.';
        } else {
          modalText.textContent = 'Approve this registration? All sections are verified.';
        }
      } else {
        modalText.textContent = 'Reject this registration? This cannot be undone from this screen without a new submission flow.';
      }

      modal.classList.remove('hidden');
      modal.classList.add('flex');
    });

    confirmBtn.addEventListener('click', () => {
      form.dataset.confirmed = '1';
      closeDecision();
      form.submit();
    });
  })();
</script>
@endif
@endsection
