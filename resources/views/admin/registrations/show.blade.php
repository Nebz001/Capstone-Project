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
  $readonlyItemClass = 'rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3.5';
  $readonlyLabelClass = 'text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500';
  $readonlyValueClass = 'mt-1.5 text-sm font-semibold text-slate-900';
  $persistedFieldReviews = is_array($persistedFieldReviews ?? null) ? $persistedFieldReviews : [];
  $persistedSectionReviews = is_array($persistedSectionReviews ?? null) ? $persistedSectionReviews : [];
  $reviewStatusFromState = static function (string $state): string {
    return match ($state) {
      'validated' => 'verified',
      'revision' => 'needs_revision',
      default => 'pending',
    };
  };
  $effectiveSectionStatus = [
    'application' => old('section_review.application', data_get($persistedSectionReviews, 'application.status', $reviewStatusFromState((string) ($initialSectionReviewState['application'] ?? 'pending')))),
    'contact' => old('section_review.contact', data_get($persistedSectionReviews, 'contact.status', $reviewStatusFromState((string) ($initialSectionReviewState['contact'] ?? 'pending')))),
    'organizational' => old('section_review.organizational', data_get($persistedSectionReviews, 'organizational.status', $reviewStatusFromState((string) ($initialSectionReviewState['organizational'] ?? 'pending')))),
    'requirements' => old('section_review.requirements', data_get($persistedSectionReviews, 'requirements.status', $reviewStatusFromState((string) ($initialSectionReviewState['requirements'] ?? 'pending')))),
  ];
  $statusBadge = static function (string $status): array {
    return match ($status) {
      'verified' => ['label' => 'Verified', 'class' => 'border border-emerald-200 bg-emerald-50 text-emerald-700'],
      'needs_revision' => ['label' => 'Needs Revision', 'class' => 'border border-amber-200 bg-amber-50 text-amber-700'],
      default => ['label' => 'Pending Review', 'class' => 'border border-slate-200 bg-slate-100 text-slate-700'],
    };
  };
  $progressCount = [
    'verified' => collect($effectiveSectionStatus)->filter(fn ($s) => $s === 'verified')->count(),
    'needs_revision' => collect($effectiveSectionStatus)->filter(fn ($s) => $s === 'needs_revision')->count(),
    'pending' => collect($effectiveSectionStatus)->filter(fn ($s) => $s === 'pending')->count(),
  ];
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
  @error('field_review')
    <x-feedback.blocked-message variant="error" :message="$message" />
  @enderror

  <x-ui.card padding="p-5" class="border border-slate-200 bg-slate-50/70">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm font-semibold text-slate-900">Registration review progress</p>
        <p class="mt-1 text-xs text-slate-600">Sections must be submitted first, and all must be verified before approval.</p>
      </div>
      <div id="registration-review-progress" class="inline-flex flex-wrap items-center gap-2 text-xs font-semibold">
        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700" data-progress="verified">Verified: {{ $progressCount['verified'] }}</span>
        <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-700" data-progress="needs_revision">Needs Revision: {{ $progressCount['needs_revision'] }}</span>
        <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-slate-700" data-progress="pending">Pending: {{ $progressCount['pending'] }}</span>
      </div>
    </div>
  </x-ui.card>

  {{-- Application Information --}}
  <x-ui.card padding="p-0" class="overflow-hidden">
    <div class="border-b border-slate-100 bg-white px-6 py-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold tracking-tight text-slate-900">Application Information</h2>
          <p class="mt-1 text-sm text-slate-500">Academic year and submission context for this registration.</p>
        </div>
        @php
          $applicationBadge = $statusBadge((string) ($effectiveSectionStatus['application'] ?? 'pending'));
        @endphp
        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $applicationBadge['class'] }}">{{ $applicationBadge['label'] }}</span>
      </div>
    </div>
    <div class="bg-white px-6 py-5">
    <dl class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Academic Year</dt>
        <dd class="{{ $readonlyValueClass }}">{{ $registration->academicTerm?->academic_year ?? 'N/A' }}</dd>
        @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'application', 'fieldKey' => 'academic_year', 'fieldLabel' => 'Academic Year', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
      </div>
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Submission Date</dt>
        <dd class="{{ $readonlyValueClass }}">{{ optional($registration->submission_date)->format('M d, Y') ?? 'N/A' }}</dd>
        @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'application', 'fieldKey' => 'submission_date', 'fieldLabel' => 'Submission Date', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
      </div>
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Submitted By</dt>
        <dd class="{{ $readonlyValueClass }}">{{ $registration->user?->full_name ?? 'N/A' }}</dd>
        @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'application', 'fieldKey' => 'submitted_by', 'fieldLabel' => 'Submitted By', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
      </div>
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Organization</dt>
        <dd class="{{ $readonlyValueClass }}">{{ $org?->organization_name ?? 'N/A' }}</dd>
        @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'application', 'fieldKey' => 'organization', 'fieldLabel' => 'Organization', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
      </div>
    </dl>
    </div>
    @include('admin.registrations.partials.section-submit-control', ['sectionKey' => 'application', 'persistedSectionReviews' => $persistedSectionReviews])
  </x-ui.card>

  {{-- Contact Information --}}
  <x-ui.card padding="p-0" class="overflow-hidden">
    <div class="border-b border-slate-100 bg-white px-6 py-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold tracking-tight text-slate-900">Account and Contact Information</h2>
          <p class="mt-1 text-sm text-slate-500">Primary contact details as submitted on the registration form.</p>
        </div>
        @php
          $contactBadge = $statusBadge((string) ($effectiveSectionStatus['contact'] ?? 'pending'));
        @endphp
        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $contactBadge['class'] }}">{{ $contactBadge['label'] }}</span>
      </div>
    </div>
    <div class="bg-white px-6 py-5">
    <dl class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
      <div class="{{ $readonlyItemClass }} md:col-span-2">
        <dt class="{{ $readonlyLabelClass }}">Organization Name</dt>
        <dd class="{{ $readonlyValueClass }}">{{ $org?->organization_name ?? 'N/A' }}</dd>
        @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'contact', 'fieldKey' => 'organization_name', 'fieldLabel' => 'Organization Name', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
      </div>
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Contact Person</dt>
        <dd class="{{ $readonlyValueClass }}">{{ $registration->contact_person ?? 'N/A' }}</dd>
        @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'contact', 'fieldKey' => 'contact_person', 'fieldLabel' => 'Contact Person', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
      </div>
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Contact No.</dt>
        <dd class="{{ $readonlyValueClass }}">{{ $registration->contact_no ?? 'N/A' }}</dd>
        @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'contact', 'fieldKey' => 'contact_no', 'fieldLabel' => 'Contact Number', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
      </div>
      <div class="{{ $readonlyItemClass }} md:col-span-2">
        <dt class="{{ $readonlyLabelClass }}">Email Address</dt>
        <dd class="{{ $readonlyValueClass }}">{{ $registration->contact_email ?? 'N/A' }}</dd>
        @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'contact', 'fieldKey' => 'contact_email', 'fieldLabel' => 'Email Address', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
      </div>
    </dl>
    </div>
    @include('admin.registrations.partials.section-submit-control', ['sectionKey' => 'contact', 'persistedSectionReviews' => $persistedSectionReviews])
  </x-ui.card>

  {{-- Organizational Details --}}
  <x-ui.card padding="p-0" class="overflow-hidden">
    <div class="border-b border-slate-100 bg-white px-6 py-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold tracking-tight text-slate-900">Organization Details</h2>
          <p class="mt-1 text-sm text-slate-500">Organization profile data at the time of submission (from the linked organization record).</p>
        </div>
        @php
          $organizationalBadge = $statusBadge((string) ($effectiveSectionStatus['organizational'] ?? 'pending'));
        @endphp
        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $organizationalBadge['class'] }}">{{ $organizationalBadge['label'] }}</span>
      </div>
    </div>
    <div class="bg-white px-6 py-5">
    <dl class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Date Organized</dt>
        <dd class="{{ $readonlyValueClass }}">{{ $org?->founded_date?->format('M d, Y') ?? 'N/A' }}</dd>
        @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'organizational', 'fieldKey' => 'date_organized', 'fieldLabel' => 'Date Organized', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
      </div>
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Type of Organization</dt>
        <dd class="{{ $readonlyValueClass }}">{{ $orgTypeLabel }}</dd>
        @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'organizational', 'fieldKey' => 'organization_type', 'fieldLabel' => 'Type of Organization', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
      </div>
      <div class="{{ $readonlyItemClass }} md:col-span-2">
        <dt class="{{ $readonlyLabelClass }}">School</dt>
        <dd class="{{ $readonlyValueClass }}">{{ $org?->college_department ?? 'N/A' }}</dd>
        @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'organizational', 'fieldKey' => 'school', 'fieldLabel' => 'School', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
      </div>
      <div class="{{ $readonlyItemClass }} md:col-span-2">
        <dt class="{{ $readonlyLabelClass }}">Purpose of Organization</dt>
        <dd class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-slate-900">{{ $org?->purpose ?? 'N/A' }}</dd>
        @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'organizational', 'fieldKey' => 'purpose', 'fieldLabel' => 'Purpose of Organization', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
      </div>
    </dl>
    </div>
    @include('admin.registrations.partials.section-submit-control', ['sectionKey' => 'organizational', 'persistedSectionReviews' => $persistedSectionReviews])
  </x-ui.card>

  {{-- Requirements Attached --}}
  <x-ui.card padding="p-0" class="overflow-hidden">
    <div class="border-b border-slate-100 bg-white px-6 py-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold tracking-tight text-slate-900">Requirements Attached</h2>
          <p class="mt-1 text-sm text-slate-500">Checklist and uploaded files as declared on the application.</p>
        </div>
        @php
          $requirementsBadge = $statusBadge((string) ($effectiveSectionStatus['requirements'] ?? 'pending'));
        @endphp
        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $requirementsBadge['class'] }}">{{ $requirementsBadge['label'] }}</span>
      </div>
    </div>
    <div class="bg-white px-6 py-5">
    <ul class="space-y-3">
      @foreach ($requirementKeys as $key)
        @php
          $requirement = $requirementRows->get($key);
          $checked = (bool) ($requirement?->is_submitted ?? false);
          $hasFile = $checked && in_array($key, $requirementAttachmentKeys, true);
        @endphp
        <li class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4">
          <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-900">{{ $requirement?->label ?? ($reqLabels[$key] ?? $key) }}</p>
            <p class="mt-0.5 text-xs text-slate-500">Marked as submitted: <span class="font-semibold text-slate-700">{{ $checked ? 'Yes' : 'No' }}</span></p>
          </div>
          <div class="flex flex-wrap items-start justify-between gap-3">
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
            <div class="ml-auto">
              @php
                $requirementFieldLabel = (string) (($requirement?->label) ?? ($reqLabels[$key] ?? $key));
              @endphp
              @include('admin.registrations.partials.field-review-control', [
                'sectionKey' => 'requirements',
                'fieldKey' => $key,
                'fieldLabel' => $requirementFieldLabel,
                'persistedFieldReviews' => $persistedFieldReviews,
                'persistedSectionReviews' => $persistedSectionReviews,
              ])
            </div>
          </div>
        </li>
      @endforeach
    </ul>
    </div>
    @include('admin.registrations.partials.section-submit-control', ['sectionKey' => 'requirements', 'persistedSectionReviews' => $persistedSectionReviews])
  </x-ui.card>

  {{-- Review decision --}}
  <x-ui.card padding="p-0" class="overflow-hidden">
    <div class="border-b border-slate-100 bg-white px-6 py-4">
    <h2 class="text-lg font-bold tracking-tight text-slate-900">Finalize review</h2>
    <p class="mt-1 text-sm text-slate-500">
      With <span class="font-semibold text-slate-800">Submit review</span>, the system <span class="font-semibold text-emerald-700">approves</span> only when every section is Verified. If any section is Need revision (with feedback), the registration returns for updates and profile editing is unlocked for the officer.
    </p>
    </div>
    <div class="bg-white px-6 py-5">
    <p id="section-review-summary" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" role="status"></p>

    <fieldset class="mt-6">
      <legend class="text-xs font-semibold uppercase tracking-wide text-slate-700">Outcome</legend>
      <div class="mt-3 flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-100/70 p-3 sm:flex-row sm:flex-wrap sm:gap-3">
        <label class="relative flex flex-1 cursor-pointer items-center gap-3 rounded-2xl border-2 border-slate-200 bg-white px-4 py-3 transition has-checked:border-[#003E9F] has-checked:bg-blue-50">
          <input type="radio" name="decision" value="APPROVED" class="sr-only" {{ $defaultDecision === 'APPROVED' ? 'checked' : '' }} />
          <span class="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-blue-100 text-[#003E9F]">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15l3-3m6 3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
          </span>
          <span>
            <span class="block text-sm font-bold text-slate-900">Submit review</span>
            <span class="block text-xs text-slate-500">Auto-approve if all verified, or request revision</span>
          </span>
        </label>
        <label class="relative flex flex-1 cursor-pointer items-center gap-3 rounded-2xl border-2 border-slate-200 bg-white px-4 py-3 transition has-checked:border-rose-500 has-checked:bg-rose-50">
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
        <span id="registration-remarks-optional" class="font-normal normal-case text-slate-400">(optional when all sections are verified)</span>
      </x-forms.label>
      <x-forms.textarea
        id="registration-remarks"
        name="remarks"
        :rows="4"
        placeholder="Optional overall context. Field-level flagged notes are captured from each section."
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
    </div>
  </x-ui.card>
</form>

<div id="registration-decision-modal" class="fixed inset-0 z-80 hidden items-center justify-center bg-slate-950/50 px-4">
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
    const optLabel = document.getElementById('registration-remarks-optional');
    const summaryEl = document.getElementById('section-review-summary');
    const progressEl = document.getElementById('registration-review-progress');

    if (!form || !modal || !modalText || !cancel || !confirmBtn || !remarks || !reqReject || !optLabel || !summaryEl || !progressEl) return;

    function selectedDecision() {
      const r = form.querySelector('input[name="decision"]:checked');
      return r ? r.value : '';
    }

    function allSectionRoots() {
      return Array.from(form.querySelectorAll('[data-section-submit]'));
    }

    function getFieldControls(sectionKey) {
      return Array.from(form.querySelectorAll(`[data-field-review][data-section-key="${sectionKey}"]`));
    }

    function syncFieldControl(control) {
      const statusInput = control.querySelector('.field-review-status');
      const noteWrap = control.querySelector('.field-review-note');
      const noteInput = control.querySelector('.field-review-note-input');
      const status = statusInput?.value || 'pending';
      control.querySelectorAll('.field-review-btn').forEach((btn) => {
        const active = btn.dataset.statusValue === status;
        btn.classList.toggle('ring-2', active);
        btn.classList.toggle('ring-slate-300', active && status === 'pending');
        btn.classList.toggle('ring-emerald-400', active && status === 'passed');
        btn.classList.toggle('ring-rose-400', active && status === 'flagged');
      });
      if (noteWrap) noteWrap.classList.toggle('hidden', status !== 'flagged');
      if (status !== 'flagged' && noteInput) noteInput.value = '';
    }

    function computeSection(sectionKey) {
      const controls = getFieldControls(sectionKey);
      let pending = 0;
      let flagged = 0;
      let invalidFlagged = 0;
      controls.forEach((control) => {
        const status = control.querySelector('.field-review-status')?.value || 'pending';
        if (status === 'pending') pending += 1;
        if (status === 'flagged') {
          flagged += 1;
          const note = (control.querySelector('.field-review-note-input')?.value || '').trim();
          if (note === '') invalidFlagged += 1;
        }
      });
      return { pending, flagged, invalidFlagged, total: controls.length };
    }

    function syncSection(sectionRoot) {
      const sectionKey = sectionRoot.dataset.sectionKey;
      if (!sectionKey) return;
      const sectionStateInput = sectionRoot.querySelector('.section-review-state');
      const sectionSubmittedInput = sectionRoot.querySelector('.section-review-submitted');
      const submitBtn = sectionRoot.querySelector('.section-submit-btn');
      const editBtn = sectionRoot.querySelector('.section-edit-btn');
      const badge = sectionRoot.querySelector('.section-submitted-badge');
      const controls = getFieldControls(sectionKey);
      const stats = computeSection(sectionKey);
      const isSubmitted = sectionSubmittedInput?.value === '1';

      const derivedStatus = stats.pending > 0 ? 'pending' : (stats.flagged > 0 ? 'needs_revision' : 'verified');
      if (sectionStateInput) sectionStateInput.value = derivedStatus;

      const canSubmit = stats.total > 0 && stats.pending === 0 && stats.invalidFlagged === 0;
      if (submitBtn) {
        submitBtn.disabled = !canSubmit || isSubmitted;
        submitBtn.title = canSubmit ? 'Submit section review' : 'All fields must be reviewed before submitting';
      }
      controls.forEach((control) => {
        const disabled = isSubmitted;
        control.querySelectorAll('button, textarea').forEach((el) => {
          el.disabled = disabled;
        });
      });
      if (badge) {
        badge.classList.toggle('hidden', !isSubmitted);
        if (isSubmitted) {
          if (derivedStatus === 'verified') {
            badge.className = 'section-submitted-badge inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700';
            badge.textContent = 'Verified';
          } else {
            badge.className = 'section-submitted-badge inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700';
            badge.textContent = 'Needs Revision';
          }
        }
      }
      if (editBtn) editBtn.classList.toggle('hidden', !isSubmitted);
    }

    function refreshSummary() {
      const roots = allSectionRoots();
      let verified = 0;
      let needsRevision = 0;
      let pending = 0;
      roots.forEach((root) => {
        const state = root.querySelector('.section-review-state')?.value || 'pending';
        if (state === 'verified') verified += 1;
        else if (state === 'needs_revision') needsRevision += 1;
        else pending += 1;
      });
      progressEl.querySelector('[data-progress="verified"]').textContent = `Verified: ${verified}`;
      progressEl.querySelector('[data-progress="needs_revision"]').textContent = `Needs Revision: ${needsRevision}`;
      progressEl.querySelector('[data-progress="pending"]').textContent = `Pending: ${pending}`;
      if (pending > 0) {
        summaryEl.textContent = `${pending} section(s) pending. Submit each section review before finalizing.`;
        summaryEl.className = 'rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900';
      } else if (needsRevision > 0) {
        summaryEl.textContent = `${needsRevision} section(s) need revision. Submitting review will send this registration back with flagged field notes.`;
        summaryEl.className = 'rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900';
      } else {
        summaryEl.textContent = 'All sections verified. Registration can be approved.';
        summaryEl.className = 'rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900';
      }
    }

    function updateRemarksHint() {
      const d = selectedDecision();
      reqReject.classList.toggle('hidden', d !== 'REJECTED');
      optLabel.classList.toggle('hidden', d === 'REJECTED');
    }

    form.querySelectorAll('[data-field-review]').forEach((control) => {
      syncFieldControl(control);
      control.querySelectorAll('.field-review-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          const statusInput = control.querySelector('.field-review-status');
          if (!statusInput) return;
          statusInput.value = btn.dataset.statusValue || 'pending';
          syncFieldControl(control);
          const sectionRoot = form.querySelector(`[data-section-submit][data-section-key="${control.dataset.sectionKey}"]`);
          if (sectionRoot) {
            const submitted = sectionRoot.querySelector('.section-review-submitted');
            if (submitted?.value === '1') return;
            syncSection(sectionRoot);
            refreshSummary();
          }
        });
      });
      control.querySelector('.field-review-note-input')?.addEventListener('input', () => {
        const sectionRoot = form.querySelector(`[data-section-submit][data-section-key="${control.dataset.sectionKey}"]`);
        if (sectionRoot) {
          syncSection(sectionRoot);
          refreshSummary();
        }
      });
    });

    allSectionRoots().forEach((sectionRoot) => {
      sectionRoot.querySelector('.section-submit-btn')?.addEventListener('click', () => {
        const stats = computeSection(sectionRoot.dataset.sectionKey || '');
        if (stats.pending > 0 || stats.invalidFlagged > 0) {
          return;
        }
        const submitted = sectionRoot.querySelector('.section-review-submitted');
        if (submitted) submitted.value = '1';
        syncSection(sectionRoot);
        refreshSummary();
      });
      sectionRoot.querySelector('.section-edit-btn')?.addEventListener('click', () => {
        const submitted = sectionRoot.querySelector('.section-review-submitted');
        if (submitted) submitted.value = '0';
        syncSection(sectionRoot);
        refreshSummary();
      });
      syncSection(sectionRoot);
    });

    form.querySelectorAll('input[name="decision"]').forEach((el) => el.addEventListener('change', () => {
      updateRemarksHint();
    }));
    refreshSummary();
    updateRemarksHint();

    const closeDecision = () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    };

    cancel.addEventListener('click', closeDecision);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeDecision(); });
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      closeDecision();
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
        const sectionRoots = allSectionRoots();
        if (sectionRoots.some((root) => root.querySelector('.section-review-submitted')?.value !== '1')) {
          alert('Submit all section reviews before finalizing.');
          return;
        }
        const states = sectionRoots.map((root) => root.querySelector('.section-review-state')?.value || 'pending');
        modalText.textContent = states.every((state) => state === 'verified')
          ? 'Approve this registration? All sections are verified.'
          : 'Send this registration for revision? Flagged field notes will be shared with the submitter.';
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
