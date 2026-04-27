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
  $requirementAttachmentsByKey = ($registration?->attachments ?? collect())
    ->filter(fn ($attachment) => is_string($attachment->file_type) && str_starts_with($attachment->file_type, \App\Models\Attachment::TYPE_REGISTRATION_REQUIREMENT.':'))
    ->sortByDesc('id')
    ->unique('file_type')
    ->mapWithKeys(function ($attachment): array {
      $key = (string) \Illuminate\Support\Str::after((string) $attachment->file_type, \App\Models\Attachment::TYPE_REGISTRATION_REQUIREMENT.':');
      return [$key => $attachment];
    });
  $orgTypeRaw = strtolower((string) ($org?->organization_type ?? ''));
  $orgTypeLabel = match ($orgTypeRaw) {
    'co_curricular' => 'Co-Curricular Organization',
    'extra_curricular' => 'Extra-Curricular Organization / Interest Club',
    default => $org?->organization_type ? (string) $org->organization_type : 'N/A',
  };
  $readonlyItemClass = 'field-review-card rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3.5';
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
  $sectionFieldKeys = [
    'application' => ['academic_year', 'submission_date', 'submitted_by', 'organization'],
    'contact' => ['contact_person', 'contact_no', 'contact_email'],
    'organizational' => ['date_organized', 'organization_type', 'school', 'purpose'],
    'requirements' => $requirementKeys,
  ];
  $deriveSectionStatusFromFields = static function (string $sectionKey, array $fieldKeys) use ($persistedFieldReviews): string {
    $sectionRows = data_get($persistedFieldReviews, $sectionKey, []);
    if (! is_array($sectionRows) || $fieldKeys === []) {
      return 'pending';
    }
    $hasPending = false;
    $hasRevision = false;
    foreach ($fieldKeys as $fieldKey) {
      $status = (string) data_get($sectionRows, $fieldKey.'.status', 'pending');
      if (! in_array($status, ['pending', 'passed', 'flagged'], true)) {
        $status = 'pending';
      }
      if ($status === 'pending') {
        $hasPending = true;
      } elseif ($status === 'flagged') {
        $hasRevision = true;
      }
    }
    if ($hasPending) {
      return 'pending';
    }
    return $hasRevision ? 'needs_revision' : 'verified';
  };
  $effectiveSectionStatus = [
    'application' => old('section_review.application', $deriveSectionStatusFromFields('application', $sectionFieldKeys['application'])),
    'contact' => old('section_review.contact', $deriveSectionStatusFromFields('contact', $sectionFieldKeys['contact'])),
    'organizational' => old('section_review.organizational', $deriveSectionStatusFromFields('organizational', $sectionFieldKeys['organizational'])),
    'requirements' => old('section_review.requirements', $deriveSectionStatusFromFields('requirements', $sectionFieldKeys['requirements'])),
  ];
  $statusBadge = static function (string $status): array {
    return match ($status) {
      'verified' => ['label' => 'Verified', 'class' => 'border border-emerald-200 bg-emerald-50 text-emerald-700'],
      'needs_revision' => ['label' => 'Needs Revision', 'class' => 'border border-amber-200 bg-amber-50 text-amber-700'],
      default => ['label' => 'Pending', 'class' => 'border border-slate-200 bg-slate-100 text-slate-700'],
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
  data-submission-id="{{ $registration?->id }}"
  data-review-draft-url="{{ route('admin.registrations.review-draft', $submission ?? $registration) }}"
>
  @csrf
  @method('PATCH')

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
  <x-ui.card padding="p-0" class="overflow-hidden" data-review-section-card data-section-key="application">
    <div class="border-b border-slate-100 bg-white px-6 py-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold tracking-tight text-slate-900">Application Information</h2>
          <p class="mt-1 text-sm text-slate-500">Academic year and submission context for this registration.</p>
        </div>
        @php
          $applicationBadge = $statusBadge((string) ($effectiveSectionStatus['application'] ?? 'pending'));
        @endphp
        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $applicationBadge['class'] }}" data-section-status-badge="application">{{ $applicationBadge['label'] }}</span>
      </div>
    </div>
    <div class="bg-white px-6 py-5">
    <dl class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Academic Year</dt>
        <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
          <dd class="text-sm font-semibold text-slate-900">{{ $registration->academicTerm?->academic_year ?? 'N/A' }}</dd>
          @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'application', 'fieldKey' => 'academic_year', 'fieldLabel' => 'Academic Year', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
        </div>
      </div>
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Submission Date</dt>
        <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
          <dd class="text-sm font-semibold text-slate-900">{{ optional($registration->submission_date)->format('M d, Y') ?? 'N/A' }}</dd>
          @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'application', 'fieldKey' => 'submission_date', 'fieldLabel' => 'Submission Date', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
        </div>
      </div>
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Submitted By</dt>
        <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
          <dd class="text-sm font-semibold text-slate-900">{{ $registration->user?->full_name ?? 'N/A' }}</dd>
          @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'application', 'fieldKey' => 'submitted_by', 'fieldLabel' => 'Submitted By', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
        </div>
      </div>
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Organization</dt>
        <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
          <dd class="text-sm font-semibold text-slate-900">{{ $org?->organization_name ?? 'N/A' }}</dd>
          @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'application', 'fieldKey' => 'organization', 'fieldLabel' => 'Organization', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
        </div>
      </div>
    </dl>
    </div>
    @include('admin.registrations.partials.section-submit-control', ['sectionKey' => 'application', 'persistedSectionReviews' => $persistedSectionReviews])
  </x-ui.card>

  {{-- Contact Information --}}
  <x-ui.card padding="p-0" class="overflow-hidden" data-review-section-card data-section-key="contact">
    <div class="border-b border-slate-100 bg-white px-6 py-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold tracking-tight text-slate-900">Account and Contact Information</h2>
          <p class="mt-1 text-sm text-slate-500">Primary contact details as submitted on the registration form.</p>
        </div>
        @php
          $contactBadge = $statusBadge((string) ($effectiveSectionStatus['contact'] ?? 'pending'));
        @endphp
        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $contactBadge['class'] }}" data-section-status-badge="contact">{{ $contactBadge['label'] }}</span>
      </div>
    </div>
    <div class="bg-white px-6 py-5">
    <dl class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Contact Person</dt>
        <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
          <dd class="text-sm font-semibold text-slate-900">{{ $registration->contact_person ?? 'N/A' }}</dd>
          @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'contact', 'fieldKey' => 'contact_person', 'fieldLabel' => 'Contact Person', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
        </div>
      </div>
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Contact No.</dt>
        <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
          <dd class="text-sm font-semibold text-slate-900">{{ $registration->contact_no ?? 'N/A' }}</dd>
          @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'contact', 'fieldKey' => 'contact_no', 'fieldLabel' => 'Contact Number', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
        </div>
      </div>
      <div class="{{ $readonlyItemClass }} md:col-span-2">
        <dt class="{{ $readonlyLabelClass }}">Email Address</dt>
        <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
          <dd class="text-sm font-semibold text-slate-900">{{ $registration->contact_email ?? 'N/A' }}</dd>
          @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'contact', 'fieldKey' => 'contact_email', 'fieldLabel' => 'Email Address', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
        </div>
      </div>
    </dl>
    </div>
    @include('admin.registrations.partials.section-submit-control', ['sectionKey' => 'contact', 'persistedSectionReviews' => $persistedSectionReviews])
  </x-ui.card>

  {{-- Organizational Details --}}
  <x-ui.card padding="p-0" class="overflow-hidden" data-review-section-card data-section-key="organizational">
    <div class="border-b border-slate-100 bg-white px-6 py-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold tracking-tight text-slate-900">Organization Details</h2>
          <p class="mt-1 text-sm text-slate-500">Organization profile data at the time of submission (from the linked organization record).</p>
        </div>
        @php
          $organizationalBadge = $statusBadge((string) ($effectiveSectionStatus['organizational'] ?? 'pending'));
        @endphp
        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $organizationalBadge['class'] }}" data-section-status-badge="organizational">{{ $organizationalBadge['label'] }}</span>
      </div>
    </div>
    <div class="bg-white px-6 py-5">
    <dl class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Date Organized</dt>
        <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
          <dd class="text-sm font-semibold text-slate-900">{{ $org?->founded_date?->format('M d, Y') ?? 'N/A' }}</dd>
          @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'organizational', 'fieldKey' => 'date_organized', 'fieldLabel' => 'Date Organized', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
        </div>
      </div>
      <div class="{{ $readonlyItemClass }}">
        <dt class="{{ $readonlyLabelClass }}">Type of Organization</dt>
        <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
          <dd class="text-sm font-semibold text-slate-900">{{ $orgTypeLabel }}</dd>
          @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'organizational', 'fieldKey' => 'organization_type', 'fieldLabel' => 'Type of Organization', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
        </div>
      </div>
      <div class="{{ $readonlyItemClass }} md:col-span-2">
        <dt class="{{ $readonlyLabelClass }}">School</dt>
        <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
          <dd class="text-sm font-semibold text-slate-900">{{ $org?->college_department ?? 'N/A' }}</dd>
          @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'organizational', 'fieldKey' => 'school', 'fieldLabel' => 'School', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
        </div>
      </div>
      <div class="{{ $readonlyItemClass }} md:col-span-2">
        <dt class="{{ $readonlyLabelClass }}">Purpose of Organization</dt>
        <div class="mt-1.5 flex flex-wrap items-start justify-between gap-2">
          <dd class="whitespace-pre-wrap text-sm leading-relaxed text-slate-900">{{ $org?->purpose ?? 'N/A' }}</dd>
          @include('admin.registrations.partials.field-review-control', ['sectionKey' => 'organizational', 'fieldKey' => 'purpose', 'fieldLabel' => 'Purpose of Organization', 'persistedFieldReviews' => $persistedFieldReviews, 'persistedSectionReviews' => $persistedSectionReviews])
        </div>
      </div>
    </dl>
    </div>
    @include('admin.registrations.partials.section-submit-control', ['sectionKey' => 'organizational', 'persistedSectionReviews' => $persistedSectionReviews])
  </x-ui.card>

  {{-- Requirements Attached --}}
  <x-ui.card padding="p-0" class="overflow-hidden" data-review-section-card data-section-key="requirements">
    <div class="border-b border-slate-100 bg-white px-6 py-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold tracking-tight text-slate-900">Requirements Attached</h2>
          <p class="mt-1 text-sm text-slate-500">Checklist and uploaded files as declared on the application.</p>
        </div>
        @php
          $requirementsBadge = $statusBadge((string) ($effectiveSectionStatus['requirements'] ?? 'pending'));
        @endphp
        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $requirementsBadge['class'] }}" data-section-status-badge="requirements">{{ $requirementsBadge['label'] }}</span>
      </div>
    </div>
    <div class="bg-white px-6 py-5">
    <ul class="space-y-3">
      @foreach ($requirementKeys as $key)
        @php
          $requirement = $requirementRows->get($key);
          $checked = (bool) ($requirement?->is_submitted ?? false);
          $hasFile = $checked && in_array($key, $requirementAttachmentKeys, true);
          $attachment = $requirementAttachmentsByKey->get($key);
          $extension = strtoupper((string) pathinfo((string) ($attachment?->original_name ?: $attachment?->stored_path ?: ''), PATHINFO_EXTENSION));
          $badgeLabel = in_array($extension, ['PDF', 'DOCX', 'PNG', 'JPG', 'JPEG'], true) ? $extension : 'FILE';
          $badgeClass = match ($badgeLabel) {
            'PDF' => 'border-red-200 bg-red-50 text-red-700',
            'DOCX' => 'border-blue-200 bg-blue-50 text-blue-700',
            'PNG' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'JPG', 'JPEG' => 'border-amber-200 bg-amber-50 text-amber-700',
            default => 'border-slate-200 bg-slate-100 text-slate-700',
          };
          $fileUrl = $hasFile
            ? route('admin.registrations.requirement-file', ['submission' => ($submission ?? $registration), 'key' => $key])
            : null;
          $downloadUrl = $hasFile
            ? route('admin.registrations.requirement-file', ['submission' => ($submission ?? $registration), 'key' => $key, 'download' => 1])
            : null;
        @endphp
        <li class="requirement-review-item flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-50/80 p-3.5 lg:flex-row lg:items-center lg:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <p class="text-sm font-semibold text-slate-900">{{ $requirement?->label ?? ($reqLabels[$key] ?? $key) }}</p>
              <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $badgeClass }}">{{ $badgeLabel }}</span>
            </div>
            <p class="mt-0.5 text-xs text-slate-500">Marked as submitted: <span class="font-semibold text-slate-700">{{ $checked ? 'Yes' : 'No' }}</span></p>
          </div>
          <div class="requirement-review-top-row flex w-full flex-wrap items-center gap-3 lg:w-auto lg:justify-end">
            <div class="shrink-0">
              @if ($hasFile)
                <div class="inline-flex items-center rounded-lg border border-slate-200 bg-white p-1" role="group" aria-label="File actions for {{ $requirement?->label ?? ($reqLabels[$key] ?? $key) }}">
                  <a
                    href="{{ $fileUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="rounded-md px-2.5 py-1 text-[11px] font-semibold text-[#003E9F] transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#003E9F]/30"
                  >
                    View file
                  </a>
                  <span class="mx-1 h-4 w-px bg-slate-200"></span>
                  <a
                    href="{{ $downloadUrl }}"
                    class="rounded-md px-2.5 py-1 text-[11px] font-semibold text-[#003E9F] transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#003E9F]/30"
                  >
                    Download
                  </a>
                </div>
              @elseif ($checked)
                <span class="text-xs font-medium text-amber-700">Marked yes — no file on record</span>
              @endif
            </div>
            <div class="ml-auto shrink-0 lg:ml-0">
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
      Saving this review automatically approves the registration when all sections are verified. If any field is marked for revision, the registration is returned for updates using the recorded field-level revision notes.
    </p>
    </div>
    <div class="bg-white px-6 py-5">
    <div id="revision-summary-box" class="rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-4 text-sm text-amber-900">
      <p id="revision-summary-title" class="text-sm font-bold uppercase tracking-widest text-amber-900">Revision Summary</p>
      <p id="revision-summary-helper" class="mt-1 text-xs text-amber-800/90">Click a revision item below to jump to the section that needs updates.</p>
      <ul id="revision-summary-list" class="mt-3 space-y-3"></ul>
    </div>

    <div class="mt-6">
      <x-forms.label for="registration-remarks">
        General remarks / instructions
        <span class="font-normal normal-case text-slate-400">(optional)</span>
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
      <button id="save-review-btn" type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus:ring-4 focus:ring-[#003E9F]/40">
        Save review
      </button>
      <a href="{{ route('admin.registrations.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20">
        Back to list
      </a>
    </div>
    <p id="save-review-helper" class="mt-2 text-xs text-slate-500"></p>
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
    const progressEl = document.getElementById('registration-review-progress');
    const revisionSummaryBox = document.getElementById('revision-summary-box');
    const revisionSummaryTitle = document.getElementById('revision-summary-title');
    const revisionSummaryHelper = document.getElementById('revision-summary-helper');
    const revisionSummaryList = document.getElementById('revision-summary-list');
    const saveReviewBtn = document.getElementById('save-review-btn');
    const saveReviewHelper = document.getElementById('save-review-helper');
    const submissionId = form?.dataset.submissionId || 'unknown';
    const reviewDraftUrl = form?.dataset.reviewDraftUrl || '';
    const reviewDraftStorageKey = `registration-review-draft:${submissionId}`;

    if (!form || !modal || !modalText || !cancel || !confirmBtn || !remarks || !progressEl || !revisionSummaryBox || !revisionSummaryList || !revisionSummaryHelper || !saveReviewBtn || !saveReviewHelper) return;

    function readDraftState() {
      try {
        const raw = window.localStorage.getItem(reviewDraftStorageKey);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : null;
      } catch (e) {
        return null;
      }
    }

    function csrfToken() {
      return form.querySelector('input[name="_token"]')?.value || '';
    }

    function buildFieldReviewPayload() {
      const payload = {};
      form.querySelectorAll('[data-field-review]').forEach((control) => {
        const sectionKey = control.dataset.sectionKey || '';
        const fieldKey = control.dataset.fieldKey || '';
        if (!sectionKey || !fieldKey) return;
        if (!payload[sectionKey]) payload[sectionKey] = {};
        const status = normalizeFieldStatus(control.querySelector('.field-review-status')?.value || 'pending');
        const noteInput = control.dataset.noteInputId
          ? document.getElementById(control.dataset.noteInputId)
          : control.querySelector('.field-review-note-input');
        payload[sectionKey][fieldKey] = {
          status,
          note: noteInput?.value || '',
        };
      });
      return payload;
    }

    let saveDraftTimer = null;
    let savingDraft = false;
    let draftRequestSeq = 0;
    let latestAppliedDraftSeq = 0;
    let activeDraftController = null;
    async function persistDraftNow() {
      if (!reviewDraftUrl) return;
      if (savingDraft && activeDraftController) {
        activeDraftController.abort();
      }
      const requestSeq = ++draftRequestSeq;
      const controller = new AbortController();
      activeDraftController = controller;
      savingDraft = true;
      try {
        const response = await fetch(reviewDraftUrl, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
          },
          body: JSON.stringify({
            field_review: buildFieldReviewPayload(),
          }),
          signal: controller.signal,
        });
        if (!response.ok) return;
        if (requestSeq > latestAppliedDraftSeq) {
          latestAppliedDraftSeq = requestSeq;
        }
      } catch (e) {
        // No-op. UI still keeps local draft state.
      } finally {
        if (activeDraftController === controller) {
          activeDraftController = null;
        }
        savingDraft = false;
      }
    }

    function scheduleDraftPersist() {
      if (saveDraftTimer) {
        window.clearTimeout(saveDraftTimer);
      }
      saveDraftTimer = window.setTimeout(() => {
        persistDraftNow();
      }, 350);
    }

    function writeDraftState() {
      const draft = {
        fields: {},
        remarks: remarks.value || '',
      };
      form.querySelectorAll('[data-field-review]').forEach((control) => {
        const sectionKey = control.dataset.sectionKey || '';
        const fieldKey = control.dataset.fieldKey || '';
        if (!sectionKey || !fieldKey) return;
        const status = normalizeFieldStatus(control.querySelector('.field-review-status')?.value || 'pending');
        const noteInput = control.dataset.noteInputId
          ? document.getElementById(control.dataset.noteInputId)
          : control.querySelector('.field-review-note-input');
        const note = noteInput?.value || '';
        draft.fields[`${sectionKey}.${fieldKey}`] = { status, note };
      });
      window.localStorage.setItem(reviewDraftStorageKey, JSON.stringify(draft));
    }

    function applyDraftState() {
      const draft = readDraftState();
      if (!draft || typeof draft !== 'object') return;
      const fields = draft.fields && typeof draft.fields === 'object' ? draft.fields : {};
      form.querySelectorAll('[data-field-review]').forEach((control) => {
        const sectionKey = control.dataset.sectionKey || '';
        const fieldKey = control.dataset.fieldKey || '';
        const key = `${sectionKey}.${fieldKey}`;
        const state = fields[key];
        if (!state || typeof state !== 'object') return;
        const statusInput = control.querySelector('.field-review-status');
        if (statusInput) {
          statusInput.value = normalizeFieldStatus(state.status || 'pending');
        }
        const noteInput = control.dataset.noteInputId
          ? document.getElementById(control.dataset.noteInputId)
          : control.querySelector('.field-review-note-input');
        if (noteInput && typeof state.note === 'string') {
          noteInput.value = state.note;
        }
      });
      if (typeof draft.remarks === 'string') {
        remarks.value = draft.remarks;
      }
    }

    function allSectionRoots() {
      return Array.from(form.querySelectorAll('[data-section-submit]'));
    }

    function normalizeFieldStatus(status) {
      const value = String(status || 'pending');
      if (value === 'revision' || value === 'needs_revision') return 'flagged';
      if (value === 'passed' || value === 'flagged' || value === 'pending') return value;
      return 'pending';
    }

    function getFieldControls(sectionKey) {
      return Array.from(form.querySelectorAll(`[data-field-review][data-section-key="${sectionKey}"]`));
    }

    function syncFieldControl(control) {
      const statusInput = control.querySelector('.field-review-status');
      const noteWrap = control.dataset.noteWrapId
        ? document.getElementById(control.dataset.noteWrapId)
        : control.querySelector('.field-review-note');
      const noteInput = control.dataset.noteInputId
        ? document.getElementById(control.dataset.noteInputId)
        : control.querySelector('.field-review-note-input');
      const noteError = control.dataset.noteWrapId
        ? document.querySelector(`#${control.dataset.noteWrapId} .field-review-note-error`)
        : control.querySelector('.field-review-note-error');
      const status = normalizeFieldStatus(statusInput?.value || 'pending');
      if (statusInput) statusInput.value = status;
      control.querySelectorAll('.field-review-btn').forEach((btn) => {
        const active = btn.dataset.statusValue === status;
        btn.classList.toggle('ring-2', active);
        btn.classList.toggle('ring-emerald-400', active && status === 'passed');
        btn.classList.toggle('ring-amber-400', active && status === 'flagged');
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      if (noteWrap) noteWrap.classList.toggle('hidden', status !== 'flagged');
      const hasNote = (noteInput?.value || '').trim() !== '';
      if (noteError) noteError.classList.toggle('hidden', status !== 'flagged' || hasNote);
    }

    function evaluateReviewReadiness() {
      const roots = allSectionRoots();
      let hasPending = false;
      let hasMissingRevisionNote = false;
      roots.forEach((root) => {
        const stats = computeSection(root.dataset.sectionKey || '');
        if (stats.pending > 0) hasPending = true;
        if (stats.invalidFlagged > 0) hasMissingRevisionNote = true;
      });
      return {
        canSave: !hasPending && !hasMissingRevisionNote,
        hasPending,
        hasMissingRevisionNote,
      };
    }

    function updateSaveReviewAvailability() {
      const state = evaluateReviewReadiness();
      saveReviewBtn.disabled = !state.canSave;
      if (state.hasPending) {
        saveReviewBtn.title = 'Review all fields before saving.';
        saveReviewHelper.textContent = 'All sections must be resolved before saving the review.';
      } else if (state.hasMissingRevisionNote) {
        saveReviewBtn.title = 'Add required revision notes before saving.';
        saveReviewHelper.textContent = 'Revision note is required for each field marked Revision.';
      } else {
        saveReviewBtn.title = 'Save review';
        saveReviewHelper.textContent = '';
      }
    }

    function applySectionBadge(sectionKey, status) {
      const badge = form.querySelector(`[data-section-status-badge="${sectionKey}"]`);
      if (!badge) return;
      if (status === 'verified') {
        badge.className = 'inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700';
        badge.textContent = 'Verified';
      } else if (status === 'needs_revision') {
        badge.className = 'inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700';
        badge.textContent = 'Needs Revision';
      } else {
        badge.className = 'inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700';
        badge.textContent = 'Pending';
      }
    }

    function computeSection(sectionKey) {
      const controls = getFieldControls(sectionKey);
      let pending = 0;
      let flagged = 0;
      let invalidFlagged = 0;
      controls.forEach((control) => {
        const status = normalizeFieldStatus(control.querySelector('.field-review-status')?.value || 'pending');
        if (status === 'pending') pending += 1;
        if (status === 'flagged') {
          flagged += 1;
          const noteInput = control.dataset.noteInputId
            ? document.getElementById(control.dataset.noteInputId)
            : control.querySelector('.field-review-note-input');
          const note = (noteInput?.value || '').trim();
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
      const controls = getFieldControls(sectionKey);
      const stats = computeSection(sectionKey);

      const derivedStatus = stats.pending > 0 ? 'pending' : (stats.flagged > 0 ? 'needs_revision' : 'verified');
      if (sectionStateInput) sectionStateInput.value = derivedStatus;
      applySectionBadge(sectionKey, derivedStatus);

      const canSubmit = stats.total > 0 && stats.pending === 0 && stats.invalidFlagged === 0;
      if (sectionSubmittedInput) sectionSubmittedInput.value = canSubmit ? '1' : '0';
      controls.forEach((control) => {
        control.querySelectorAll('button').forEach((el) => { el.disabled = false; });
        const noteInput = control.dataset.noteInputId
          ? document.getElementById(control.dataset.noteInputId)
          : control.querySelector('.field-review-note-input');
        if (noteInput) noteInput.disabled = false;
      });
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
    }

    function refreshRevisionSummary() {
      const sectionLabels = {
        application: 'Application Information',
        contact: 'Account and Contact Information',
        organizational: 'Organization Details',
        requirements: 'Requirements Attached',
      };
      const sectionOrder = ['application', 'contact', 'organizational', 'requirements'];
      const revisionRows = [];
      const sectionRoots = allSectionRoots();
      const sectionStates = sectionRoots.map((root) => root.querySelector('.section-review-state')?.value || 'pending');
      const pendingCount = sectionStates.filter((state) => state === 'pending').length;
      const verifiedCount = sectionStates.filter((state) => state === 'verified').length;
      form.querySelectorAll('[data-field-review]').forEach((control) => {
        const status = normalizeFieldStatus(control.querySelector('.field-review-status')?.value || 'pending');
        if (status !== 'flagged') return;
        const sectionKey = control.dataset.sectionKey || '';
        const fieldLabel = control.dataset.fieldLabel || 'Field';
        const noteInput = control.dataset.noteInputId
          ? document.getElementById(control.dataset.noteInputId)
          : control.querySelector('.field-review-note-input');
        const note = (noteInput?.value || '').trim();
        const targetElement = control.closest('.field-review-card') || control.closest('.requirement-review-item');
        if (targetElement && !targetElement.id) {
          targetElement.id = `review-target-${sectionKey}-${control.dataset.fieldKey || 'field'}`;
        }
        revisionRows.push({
          sectionKey,
          section: sectionLabels[sectionKey] || sectionKey,
          field: fieldLabel,
          note: note !== '' ? note : 'No note provided yet.',
          targetId: targetElement?.id || '',
        });
      });

      if (pendingCount > 0) {
        revisionSummaryBox.className = 'rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-4 text-sm text-amber-900';
        if (revisionSummaryTitle) {
          revisionSummaryTitle.className = 'text-sm font-bold uppercase tracking-widest text-amber-900';
          revisionSummaryTitle.textContent = 'Revision Summary';
        }
        revisionSummaryHelper.className = 'mt-1 text-xs text-amber-800/90';
        revisionSummaryHelper.textContent = 'Complete all pending fields first, then finalize the review.';
        revisionSummaryList.innerHTML = `<li class="text-sm">${pendingCount} section(s) pending. Complete all field reviews first.</li>`;
        return;
      }

      if (revisionRows.length === 0 && verifiedCount === sectionRoots.length && sectionRoots.length > 0) {
        revisionSummaryBox.className = 'rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900';
        if (revisionSummaryTitle) {
          revisionSummaryTitle.className = 'text-sm font-bold uppercase tracking-widest text-emerald-700';
          revisionSummaryTitle.textContent = 'Review Summary';
        }
        revisionSummaryHelper.className = 'mt-1 text-xs text-emerald-600';
        revisionSummaryHelper.textContent = 'Every section is fully reviewed and verified.';
        revisionSummaryList.innerHTML = '<li class="text-sm text-emerald-800">All sections are verified. This registration is ready for approval.</li>';
        return;
      }

      revisionSummaryBox.className = 'rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-4 text-sm text-amber-900';
      if (revisionSummaryTitle) {
        revisionSummaryTitle.className = 'text-sm font-bold uppercase tracking-widest text-amber-900';
        revisionSummaryTitle.textContent = 'Revision Summary';
      }
      revisionSummaryHelper.className = 'mt-1 text-xs text-amber-800/90';
      revisionSummaryHelper.textContent = 'Click a revision item below to jump to the section that needs updates.';
      revisionSummaryList.innerHTML = '';
      const grouped = {};
      revisionRows.forEach((row) => {
        grouped[row.sectionKey] = grouped[row.sectionKey] || [];
        grouped[row.sectionKey].push(row);
      });
      sectionOrder.forEach((sectionKey) => {
        const rows = grouped[sectionKey] || [];
        if (rows.length === 0) return;
        const item = document.createElement('li');
        item.className = 'rounded-xl border border-amber-200/70 bg-white/70 px-3 py-2.5';
        const title = document.createElement('p');
        title.className = 'font-semibold text-amber-900';
        title.textContent = `${rows[0].section} (${rows.length})`;
        item.appendChild(title);
        const list = document.createElement('ul');
        list.className = 'mt-1.5 space-y-1.5';
        rows.forEach((row) => {
          const listItem = document.createElement('li');
          const action = document.createElement('button');
          action.type = 'button';
          action.className = 'inline-flex w-full items-start gap-2 rounded-lg px-2 py-1.5 text-left text-xs text-amber-900/95 transition hover:bg-amber-100/70 focus:outline-none focus:ring-2 focus:ring-amber-400/50';
          if (row.targetId) {
            action.dataset.targetId = row.targetId;
          }
          action.innerHTML = `<span class="font-semibold underline underline-offset-2">${row.field}</span><span class="text-amber-900/80">- ${row.note}</span>`;
          listItem.appendChild(action);
          list.appendChild(listItem);
        });
        item.appendChild(list);
        revisionSummaryList.appendChild(item);
      });
    }

    let activeFlashTimer = null;
    function scrollToRevisionTarget(targetId) {
      if (!targetId) return;
      const target = document.getElementById(targetId);
      if (!target) return;
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      target.classList.add('ring-2', 'ring-amber-300', 'bg-amber-50/80', 'transition');
      if (activeFlashTimer) {
        window.clearTimeout(activeFlashTimer);
      }
      activeFlashTimer = window.setTimeout(() => {
        target.classList.remove('ring-2', 'ring-amber-300', 'bg-amber-50/80', 'transition');
      }, 1800);
    }

    form.querySelectorAll('[data-field-review]').forEach((control) => {
      const parentCard = control.closest('.field-review-card');
      const requirementCard = control.closest('.requirement-review-item');
      const noteWrap = control.querySelector('.field-review-note');
      const noteInput = control.querySelector('.field-review-note-input');
      const valueRow = control.parentElement;
      const fieldLabel = parentCard?.querySelector(':scope > dt');
      const fieldValue = valueRow?.querySelector(':scope > dd');

      if (parentCard && valueRow && fieldLabel && fieldValue) {
        const leftCol = document.createElement('div');
        leftCol.className = 'min-w-0 flex-1';
        leftCol.appendChild(fieldLabel);
        leftCol.appendChild(fieldValue);

        valueRow.className = 'field-review-top-row mt-1.5 flex flex-wrap items-start justify-between gap-3';
        valueRow.insertBefore(leftCol, valueRow.firstChild);
        control.className = 'field-review-control shrink-0';
      }

      if (parentCard && noteWrap) {
        const noteWrapId = `field-review-note-${control.dataset.sectionKey || 'section'}-${control.dataset.fieldKey || 'field'}`;
        noteWrap.id = noteWrapId;
        control.dataset.noteWrapId = noteWrapId;
        if (noteInput) {
          const noteInputId = `${noteWrapId}-input`;
          noteInput.id = noteInputId;
          control.dataset.noteInputId = noteInputId;
        }
        noteWrap.classList.add('w-full', 'border-t', 'border-slate-200/70', 'pt-2.5');
        parentCard.appendChild(noteWrap);
      }
      if (requirementCard && noteWrap) {
        const noteWrapId = `field-review-note-${control.dataset.sectionKey || 'section'}-${control.dataset.fieldKey || 'field'}`;
        noteWrap.id = noteWrapId;
        control.dataset.noteWrapId = noteWrapId;
        if (noteInput) {
          const noteInputId = `${noteWrapId}-input`;
          noteInput.id = noteInputId;
          control.dataset.noteInputId = noteInputId;
        }
        noteWrap.classList.add('w-full', 'border-t', 'border-slate-200/70', 'pt-2.5');
        requirementCard.appendChild(noteWrap);
        control.className = 'field-review-control shrink-0';
      }

      syncFieldControl(control);
      control.querySelectorAll('.field-review-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          const statusInput = control.querySelector('.field-review-status');
          if (!statusInput) return;
          statusInput.value = btn.dataset.statusValue || 'pending';
          syncFieldControl(control);
          const sectionRoot = form.querySelector(`[data-section-submit][data-section-key="${control.dataset.sectionKey}"]`);
          if (sectionRoot) {
            syncSection(sectionRoot);
            refreshSummary();
            refreshRevisionSummary();
            updateSaveReviewAvailability();
            writeDraftState();
            scheduleDraftPersist();
          }
        });
      });
      const mappedNoteInput = control.dataset.noteInputId
        ? document.getElementById(control.dataset.noteInputId)
        : control.querySelector('.field-review-note-input');
      mappedNoteInput?.addEventListener('input', () => {
        const sectionRoot = form.querySelector(`[data-section-submit][data-section-key="${control.dataset.sectionKey}"]`);
        if (sectionRoot) {
          syncSection(sectionRoot);
          refreshSummary();
          refreshRevisionSummary();
          updateSaveReviewAvailability();
          writeDraftState();
          scheduleDraftPersist();
        }
      });
    });

    applyDraftState();
    allSectionRoots().forEach((sectionRoot) => {
      syncSection(sectionRoot);
    });
    refreshSummary();
    refreshRevisionSummary();
    updateSaveReviewAvailability();
    remarks.addEventListener('input', writeDraftState);
    revisionSummaryList.addEventListener('click', (event) => {
      const action = event.target.closest('button[data-target-id]');
      if (!action) return;
      const { targetId } = action.dataset;
      if (targetId) {
        scrollToRevisionTarget(targetId);
      }
    });

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
      updateSaveReviewAvailability();
      if (saveReviewBtn.disabled) {
        return;
      }
      const sectionRoots = allSectionRoots();
      if (sectionRoots.some((root) => root.querySelector('.section-review-submitted')?.value !== '1')) {
        alert('Review all fields and provide notes for revision-marked fields before saving.');
        return;
      }
      const states = sectionRoots.map((root) => root.querySelector('.section-review-state')?.value || 'pending');
      modalText.textContent = states.every((state) => state === 'verified')
        ? 'Finalize this review and approve the registration?'
        : 'Finalize this review and return the registration for revision with field notes?';

      modal.classList.remove('hidden');
      modal.classList.add('flex');
    });

    confirmBtn.addEventListener('click', () => {
      form.dataset.confirmed = '1';
      if (saveDraftTimer) {
        window.clearTimeout(saveDraftTimer);
        saveDraftTimer = null;
      }
      window.localStorage.removeItem(reviewDraftStorageKey);
      closeDecision();
      form.submit();
    });
  })();
</script>
@endif
@endsection
