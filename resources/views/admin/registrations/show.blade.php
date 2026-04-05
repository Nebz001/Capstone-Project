@extends('layouts.admin')

@section('title', 'Registration Submission Details — NU Lipa SDAO')

@section('content')
@php
  $defaultDecision = old(
    'decision',
    in_array($registration->registration_status, ['APPROVED', 'REJECTED', 'REVISION'], true)
      ? $registration->registration_status
      : ''
  );
  $status = $registration->registration_status ?? 'PENDING';
  $statusClass = match ($status) {
    'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
    'UNDER_REVIEW', 'REVIEWED' => 'bg-blue-100 text-blue-700 border border-blue-200',
    'APPROVED' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    'REJECTED' => 'bg-rose-100 text-rose-700 border border-rose-200',
    'REVISION', 'REVISION_REQUIRED' => 'bg-orange-100 text-orange-700 border border-orange-200',
    default => 'bg-slate-100 text-slate-700 border border-slate-200',
  };
  $org = $registration->organization;
  $reqLabels = [
    'letter_of_intent' => 'Letter of Intent',
    'application_form' => 'Application Form',
    'by_laws' => 'By Laws of the Organization',
    'updated_list_of_officers_founders' => 'Updated List of Officers/Founders',
    'dean_endorsement_faculty_adviser' => 'Letter from the School Dean endorsing the Faculty Adviser',
    'proposed_projects_budget' => 'List of Proposed Projects with Proposed Budget for the AY',
    'others' => 'Others',
  ];
  $reqColumns = [
    'letter_of_intent' => 'req_letter_of_intent',
    'application_form' => 'req_application_form',
    'by_laws' => 'req_by_laws',
    'updated_list_of_officers_founders' => 'req_officers_list',
    'dean_endorsement_faculty_adviser' => 'req_dean_endorsement',
    'proposed_projects_budget' => 'req_proposed_projects',
    'others' => 'req_others',
  ];
  $requirementFiles = is_array($registration->requirement_files) ? $registration->requirement_files : [];
  $orgTypeRaw = strtolower((string) ($org?->organization_type ?? ''));
  $orgTypeLabel = match ($orgTypeRaw) {
    'co_curricular' => 'Co-Curricular Organization',
    'extra_curricular' => 'Extra-Curricular Organization / Interest Club',
    default => $org?->organization_type ? (string) $org->organization_type : 'N/A',
  };
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Registration Submission Details</h1>
    <p class="mt-1 text-sm text-slate-500">Review the full application by section, add section comments when sending for revision, then record a decision.</p>
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

<form
  id="registration-review-form"
  method="POST"
  action="{{ route('admin.registrations.update-status', $registration) }}"
  class="space-y-6"
  data-confirmed="0"
>
  @csrf
  @method('PATCH')

  @error('decision')
    <p class="text-sm text-rose-600">{{ $message }}</p>
  @enderror

  {{-- Application Information --}}
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-base font-bold text-slate-900">Application Information</h2>
    <p class="mt-1 text-sm text-slate-600">Academic year and submission context for this registration.</p>
    <dl class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Academic Year</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $registration->academic_year ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submission Date</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ optional($registration->submission_date)->format('M d, Y') ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted By</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $registration->user?->full_name ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Organization</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $org?->organization_name ?? 'N/A' }}</dd>
      </div>
      @if ($registration->approved_by_sdao || $registration->approval_date)
        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
          <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last reviewed by (SDAO)</dt>
          <dd class="mt-1 text-sm text-slate-800">{{ $registration->approved_by_sdao ?? 'N/A' }}</dd>
        </div>
        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
          <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Decision date</dt>
          <dd class="mt-1 text-sm text-slate-800">{{ optional($registration->approval_date)->format('M d, Y') ?? 'N/A' }}</dd>
        </div>
      @endif
    </dl>
    <div class="mt-6 border-t border-slate-100 pt-5">
      <label for="revision-comment-application" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Comment for Application Information <span class="font-normal normal-case text-slate-400">(for revision)</span>
      </label>
      <textarea
        id="revision-comment-application"
        name="revision_comment_application"
        rows="3"
        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
        placeholder="Section-specific feedback for the RSO officer (e.g. academic year format, submission details)."
      >{{ old('revision_comment_application', $registration->revision_comment_application) }}</textarea>
      @error('revision_comment_application')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
      @enderror
    </div>
  </section>

  {{-- Contact Information --}}
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-base font-bold text-slate-900">Contact Information</h2>
    <p class="mt-1 text-sm text-slate-600">Primary contact details as submitted on the registration form.</p>
    <dl class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 md:col-span-2">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Organization Name</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $org?->organization_name ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contact Person</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $registration->contact_person ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contact No.</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $registration->contact_no ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 md:col-span-2">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email Address</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $registration->contact_email ?? 'N/A' }}</dd>
      </div>
    </dl>
    <div class="mt-6 border-t border-slate-100 pt-5">
      <label for="revision-comment-contact" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Comment for Contact Information <span class="font-normal normal-case text-slate-400">(for revision)</span>
      </label>
      <textarea
        id="revision-comment-contact"
        name="revision_comment_contact"
        rows="3"
        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
        placeholder="Section-specific feedback (e.g. contact person, phone format, email domain)."
      >{{ old('revision_comment_contact', $registration->revision_comment_contact) }}</textarea>
      @error('revision_comment_contact')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
      @enderror
    </div>
  </section>

  {{-- Organizational Details --}}
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-base font-bold text-slate-900">Organizational Details</h2>
    <p class="mt-1 text-sm text-slate-600">Organization profile data at the time of submission (from the linked organization record).</p>
    <dl class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date Organized</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $org?->founded_date?->format('M d, Y') ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Type of Organization</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $orgTypeLabel }}</dd>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 md:col-span-2">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">School</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $org?->college_department ?? 'N/A' }}</dd>
      </div>
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 md:col-span-2">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Purpose of Organization</dt>
        <dd class="mt-1 whitespace-pre-wrap text-sm text-slate-800">{{ $org?->purpose ?? 'N/A' }}</dd>
      </div>
    </dl>
    <div class="mt-6 border-t border-slate-100 pt-5">
      <label for="revision-comment-organizational" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Comment for Organizational Details <span class="font-normal normal-case text-slate-400">(for revision)</span>
      </label>
      <textarea
        id="revision-comment-organizational"
        name="revision_comment_organizational"
        rows="3"
        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
        placeholder="Section-specific feedback (e.g. purpose clarity, school selection, organization type)."
      >{{ old('revision_comment_organizational', $registration->revision_comment_organizational) }}</textarea>
      @error('revision_comment_organizational')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
      @enderror
    </div>
  </section>

  {{-- Requirements Attached --}}
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-base font-bold text-slate-900">Requirements Attached</h2>
    <p class="mt-1 text-sm text-slate-600">Checklist and uploaded files as declared on the application.</p>
    <ul class="mt-4 space-y-2">
      @foreach ($reqColumns as $key => $col)
        @php
          $checked = (bool) $registration->{$col};
          $filePath = $requirementFiles[$key] ?? null;
          $hasFile = $filePath && \Illuminate\Support\Facades\Storage::disk('public')->exists($filePath);
        @endphp
        <li class="flex flex-col gap-1 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="min-w-0">
            <p class="text-sm font-medium text-slate-900">{{ $reqLabels[$key] ?? $key }}</p>
            <p class="text-xs text-slate-500">Marked as submitted: <span class="font-semibold text-slate-700">{{ $checked ? 'Yes' : 'No' }}</span></p>
            @if ($key === 'others' && $registration->req_others_specify)
              <p class="mt-1 text-xs text-slate-600">Specified: {{ $registration->req_others_specify }}</p>
            @endif
          </div>
          <div class="shrink-0">
            @if ($hasFile)
              <a
                href="{{ route('admin.registrations.requirement-file', ['registration' => $registration, 'key' => $key]) }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex rounded-lg border border-[#003E9F] bg-white px-3 py-1.5 text-xs font-semibold text-[#003E9F] transition hover:bg-slate-50"
              >
                View file
              </a>
              <span class="ml-2 text-xs text-slate-500" title="{{ $filePath }}">{{ $reqLabels[$key] ?? $key }} — {{ basename($filePath) }}</span>
            @elseif ($checked)
              <span class="text-xs text-amber-700">Marked yes — no file on record</span>
            @endif
          </div>
        </li>
      @endforeach
    </ul>
    <div class="mt-6 border-t border-slate-100 pt-5">
      <label for="revision-comment-requirements" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Comment for Requirements Attached <span class="font-normal normal-case text-slate-400">(for revision)</span>
      </label>
      <textarea
        id="revision-comment-requirements"
        name="revision_comment_requirements"
        rows="3"
        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
        placeholder="Section-specific feedback (e.g. missing documents, wrong file, unclear scans)."
      >{{ old('revision_comment_requirements', $registration->revision_comment_requirements) }}</textarea>
      @error('revision_comment_requirements')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
      @enderror
    </div>
  </section>

  {{-- Review decision --}}
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-base font-bold text-slate-900">Review decision</h2>
    <p class="mt-1 text-sm text-slate-600">Choose an outcome and add remarks where required. For revision, use section comments above and/or general remarks below.</p>
    <p class="mt-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs text-slate-600">
      <span class="font-semibold text-slate-700">For revision:</span> saves section comments and general remarks, sets the registration to revision, and <span class="font-semibold text-slate-700">unlocks organization profile editing</span> for the RSO officer. Officers see each section comment on their profile.
    </p>

    <fieldset class="mt-6">
      <legend class="text-xs font-semibold uppercase tracking-wide text-slate-500">Decision</legend>
      <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <label class="relative flex flex-1 cursor-pointer items-center gap-3 rounded-xl border-2 border-slate-200 bg-white px-4 py-3 transition has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
          <input type="radio" name="decision" value="APPROVED" class="sr-only" {{ $defaultDecision === 'APPROVED' ? 'checked' : '' }} />
          <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
          </span>
          <span>
            <span class="block text-sm font-bold text-slate-900">Approved</span>
            <span class="block text-xs text-slate-500">Recognize this registration</span>
          </span>
        </label>
        <label class="relative flex flex-1 cursor-pointer items-center gap-3 rounded-xl border-2 border-slate-200 bg-white px-4 py-3 transition has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50">
          <input type="radio" name="decision" value="REJECTED" class="sr-only" {{ $defaultDecision === 'REJECTED' ? 'checked' : '' }} />
          <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-rose-100 text-rose-700">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
          </span>
          <span>
            <span class="block text-sm font-bold text-slate-900">Rejected</span>
            <span class="block text-xs text-slate-500">Decline with reason</span>
          </span>
        </label>
        <label class="relative flex flex-1 cursor-pointer items-center gap-3 rounded-xl border-2 border-slate-200 bg-white px-4 py-3 transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
          <input type="radio" name="decision" value="REVISION" class="sr-only" {{ $defaultDecision === 'REVISION' ? 'checked' : '' }} />
          <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-amber-100 text-amber-800">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 11.664 0 8.25 8.25 0 0 0 0-11.664m-11.664 0L2.985 16.65" /></svg>
          </span>
          <span>
            <span class="block text-sm font-bold text-slate-900">For revision</span>
            <span class="block text-xs text-slate-500">Request updates from the org</span>
          </span>
        </label>
      </div>
    </fieldset>

    <div class="mt-6">
      <label for="registration-remarks" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        General remarks / instructions
        <span id="registration-remarks-required" class="hidden font-normal normal-case text-rose-600">(required for rejection)</span>
        <span id="registration-remarks-revision" class="hidden font-normal normal-case text-amber-700">(optional if you added section comments — otherwise at least 3 characters required)</span>
        <span id="registration-remarks-optional" class="font-normal normal-case text-slate-400">(optional for approved)</span>
      </label>
      <textarea
        id="registration-remarks"
        name="remarks"
        rows="4"
        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
        placeholder="Overall notes, or use section comments above for targeted feedback when sending for revision."
      >{{ old('remarks', $registration->additional_remarks) }}</textarea>
      @error('remarks')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
      @enderror
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
      <button type="submit" class="inline-flex rounded-lg bg-[#003E9F] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#00327F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/40">
        Submit decision
      </button>
      <a href="{{ route('admin.registrations.index') }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
        Back to list
      </a>
    </div>
  </section>
</form>

<div id="registration-decision-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-950/50 px-4">
  <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
    <h3 class="text-lg font-bold text-slate-900">Confirm decision</h3>
    <p id="registration-decision-modal-text" class="mt-1 text-sm text-slate-600"></p>
    <div class="mt-5 flex items-center justify-end gap-2">
      <button type="button" id="registration-decision-cancel" class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
        Cancel
      </button>
      <button type="button" id="registration-decision-confirm" class="rounded-lg border border-[#003E9F] bg-[#003E9F] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#00327F]">
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
    const reqRevision = document.getElementById('registration-remarks-revision');
    const optLabel = document.getElementById('registration-remarks-optional');
    const sectionCommentIds = [
      'revision-comment-application',
      'revision-comment-contact',
      'revision-comment-organizational',
      'revision-comment-requirements',
    ];

    if (!form || !modal || !modalText || !cancel || !confirmBtn || !remarks || !reqReject || !reqRevision || !optLabel) return;

    const decisionLabels = { APPROVED: 'approve this registration', REJECTED: 'reject this registration', REVISION: 'mark this registration for revision' };

    function selectedDecision() {
      const r = form.querySelector('input[name="decision"]:checked');
      return r ? r.value : '';
    }

    function anySectionCommentOk() {
      return sectionCommentIds.some((id) => {
        const el = document.getElementById(id);
        return el && el.value.trim().length >= 3;
      });
    }

    function updateRemarksHint() {
      const d = selectedDecision();
      reqReject.classList.toggle('hidden', d !== 'REJECTED');
      reqRevision.classList.toggle('hidden', d !== 'REVISION');
      optLabel.classList.toggle('hidden', d === 'REJECTED' || d === 'REVISION');
    }

    form.querySelectorAll('input[name="decision"]').forEach((el) => el.addEventListener('change', updateRemarksHint));
    updateRemarksHint();

    const close = () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    };

    cancel.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

    form.addEventListener('submit', (e) => {
      if (form.dataset.confirmed === '1') return;

      e.preventDefault();
      const d = selectedDecision();
      if (!d) {
        alert('Please select a decision before submitting.');
        return;
      }
      const text = remarks.value.trim();
      if (d === 'REJECTED' && text.length < 3) {
        alert('Please provide rejection remarks (at least a few characters).');
        remarks.focus();
        return;
      }
      if (d === 'REVISION' && text.length < 3 && !anySectionCommentOk()) {
        alert('For revision, add general remarks (at least 3 characters) or at least one section comment (at least 3 characters).');
        remarks.focus();
        return;
      }

      modalText.textContent = `Are you sure you want to ${decisionLabels[d] ?? 'update this registration'}? This will be saved and visible on refresh.`;
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    });

    confirmBtn.addEventListener('click', () => {
      form.dataset.confirmed = '1';
      close();
      form.submit();
    });
  })();
</script>
@endsection
