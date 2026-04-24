@extends($layout ?? 'layouts.organization-portal')

@section('title', ($pageTitle ?? 'Submit Proposal').' — NU Lipa SDAO')

@section('content')

@php
  $officerValidationPending = $officerValidationPending ?? false;
  $blockedMessage = $blockedMessage ?? null;
  $proposalAccessBlocked = $officerValidationPending || (is_string($blockedMessage) && trim($blockedMessage) !== '');
  if (! $blockedMessage && $officerValidationPending) {
    $blockedMessage = 'Your student officer account is pending SDAO validation. You cannot submit proposals until validation is complete.';
  }
  $calendarEntry = $calendarEntry ?? null;
  $linkedProposal = $linkedProposal ?? null;
  $proposalCalendar = $proposalCalendar ?? null;
  $proposalCalendarEntries = $proposalCalendar?->entries ?? collect();
  $prefill = is_array($prefill ?? null) ? $prefill : [];
  $prefillBudgetItems = old('budget_items_payload');
  if ($prefillBudgetItems === null || $prefillBudgetItems === '') {
    $prefillBudgetItems = json_encode($prefill['budget_items'] ?? []);
  }
  $hasExistingLogo = (bool) ($linkedProposal?->organization_logo_path);
  $sourceOfFundingValue = old('source_of_funding', $prefill['source_of_funding'] ?? '');
  if ($sourceOfFundingValue === '' || $sourceOfFundingValue === null || ! in_array((string) $sourceOfFundingValue, ['RSO Fund', 'RSO Savings', 'External'], true)) {
    $sourceOfFundingValue = 'RSO Fund';
  }
  $hasExternalFundingFile = (bool) ($linkedProposal?->external_funding_support_path ?? false);
  // inline-block + w-auto: keeps the native file control only as wide as the visible picker so
  // browser validation popovers anchor near "Choose File" instead of a full-width hit box.
  $fileClass = 'inline-block w-auto max-w-full cursor-pointer text-sm text-slate-600 file:mr-4 file:cursor-pointer file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-slate-800 hover:file:bg-slate-200/80';
  $isAdminSubmission = ($submissionContext ?? '') === 'admin';
  $proposalGetRoute = $activityProposalGetRoute ?? 'organizations.activity-proposal-submission';
  $activeAcademicYear = \App\Models\SystemSetting::activeAcademicYear();
  $requestForm = $requestForm ?? null;
  $hasStep1Autofill = $requestForm !== null;
  $proposalSource = $proposalSource ?? request('proposal_source', 'calendar');
  if (! in_array($proposalSource, ['calendar', 'unlisted'], true)) {
    $proposalSource = 'calendar';
  }
  $showPageIntro = $showPageIntro ?? (! $isAdminSubmission && (($layout ?? 'layouts.organization-portal') !== 'layouts.admin'));
@endphp

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">

  @if ($showPageIntro)
    <header class="mb-6">
      <a href="{{ $backRoute ?? route('organizations.activity-submission') }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
        @if ($isAdminSubmission)
          Back to Admin Dashboard
        @else
          Back to Activity Submission
        @endif
      </a>
      <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
        {{ $pageHeading ?? 'Submit Proposal' }}
      </h1>
      <p class="mt-1 text-sm text-slate-500">
        @if ($pageSubheading ?? null)
          {{ $pageSubheading }}
        @elseif ($calendarEntry)
          You are completing the detailed proposal for one calendar activity. Other activities keep their own proposal records.
        @else
          Submit a detailed activity proposal for SDAO review.
        @endif
      </p>
    </header>
  @endif

  @if ($proposalAccessBlocked)
    <x-feedback.blocked-message
      variant="error"
      class="mb-6"
      :message="$blockedMessage"
    />
  @else

  @unless ($isAdminSubmission)
    <x-ui.card padding="p-0" class="mb-6">
      <div class="px-6 py-4.5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-stretch">
          <a
            href="{{ route('organizations.activity-proposal-request', ['request_id' => (int) request('request_id'), 'proposal_source' => $proposalSource]) }}"
            class="flex-1 rounded-2xl border-2 border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-900 transition hover:bg-emerald-100"
          >
            <div class="flex items-center gap-2">
              <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-white">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
              </span>
              <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-emerald-700">Completed</p>
            </div>
            <p class="mt-1.5 text-sm font-bold text-emerald-900">Step 1: Activity Request Form</p>
          </a>
          <div class="flex-1 rounded-2xl border-2 border-[#003E9F] bg-[#003E9F] px-4 py-3 text-white shadow-sm ring-2 ring-[#003E9F]/20">
            <div class="flex items-center gap-2">
              <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white text-xs font-extrabold text-[#003E9F]">2</span>
              <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-white/90">Current Step</p>
            </div>
            <p class="mt-1.5 text-sm font-bold">Step 2: Proposal Submission</p>
          </div>
        </div>
      </div>
    </x-ui.card>
  @endunless

  @if ($isAdminSubmission && ! $organization)
    <x-ui.card padding="p-0" class="mb-6">
      <x-ui.card-section-header
        title="Load organization"
        subtitle="Enter the exact registered organization name to load submitted calendar activities and the proposal form."
        content-padding="px-6"
      />
      <div class="border-t border-slate-100 px-6 py-5">
        <form method="GET" action="{{ route('admin.submissions.activity-proposal') }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
          <div class="min-w-[220px] flex-1">
            <x-forms.label for="lookup_organization_name_proposal" required>Registered organization name</x-forms.label>
            <x-forms.input
              id="lookup_organization_name_proposal"
              name="lookup_organization_name"
              type="text"
              :value="$lookupOrganizationName ?? ''"
              placeholder="e.g., Computer Society"
              required
            />
          </div>
          <x-ui.button type="submit" class="w-full sm:w-auto">Continue</x-ui.button>
        </form>
        @if (! empty($lookupOrganizationNameError))
          <p class="mt-3 text-sm font-medium text-red-700">{{ $lookupOrganizationNameError }}</p>
        @endif
      </div>
    </x-ui.card>
  @endif

  @if (! $isAdminSubmission && $organization && $proposalSource === 'calendar' && $calendarEntry)
    <x-feedback.blocked-message
      variant="info"
      class="mb-6"
      message="This proposal is linked to the activity selected in Step 1. To change the linked activity, go back to Step 1."
    />
  @endif

  @if ($organization && $proposalSource === 'calendar' && (! $proposalCalendar || $proposalCalendarEntries->isEmpty()))
    <x-feedback.blocked-message
      variant="info"
      class="mb-6"
      message="No submitted activity calendar entries are available to select. You may switch to 'Activity not in submitted calendar' to continue with a new/unlisted proposal."
    />
  @endif

  @if (session('success'))
    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm" role="alert">
      {{ session('success') }}
    </div>
  @endif

  @if (session('error'))
    <x-feedback.blocked-message variant="error" class="mb-6" :message="session('error')" />
  @endif

  @if ($errors->any())
    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm" role="alert">
      <p class="font-semibold">Unable to submit — please review the issues below.</p>
      <ul class="mt-2 list-disc space-y-1 pl-5">
        @foreach ($errors->all() as $message)
          <li>{{ $message }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if ($organization)
  <form
    id="activity-proposal-form"
    method="POST"
    action="{{ route($proposalStoreRoute ?? 'organizations.activity-proposal-submission.store') }}"
    enctype="multipart/form-data"
    class="space-y-4"
    data-officer-validation-pending="{{ $officerValidationPending ? 'true' : 'false' }}"
  >
    @csrf
    @if (! $isAdminSubmission)
      <input type="hidden" name="request_id" value="{{ (int) request('request_id') }}" />
    @endif
    <input type="hidden" name="proposal_source" value="{{ $proposalSource }}" />
    @if ($proposalSource === 'calendar' && $calendarEntry)
      <input type="hidden" name="activity_calendar_entry_id" value="{{ $calendarEntry->id }}" />
    @endif

    <fieldset
      @disabled($officerValidationPending)
      @class([
        'min-w-0 space-y-4 border-0 p-0 m-0',
        'pointer-events-none opacity-50' => $officerValidationPending,
      ])
    >

      <x-ui.card padding="p-0">
        <x-ui.card-section-header
          title="Organization Information"
          subtitle="Identify your organization for this proposal."
          helper='Fields marked with <span class="text-red-600">*</span> are required.'
          :helper-html="true"
          content-padding="px-6"
          header-class="!pb-3 pt-3"
        />
        <div class="px-6 py-5">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-x-5">
            <div class="md:col-span-2">
              <x-forms.label for="organization_logo" :required="! $hasExistingLogo">Organization Logo</x-forms.label>
              <div class="mt-2 w-fit max-w-full">
                <input
                  id="organization_logo"
                  name="organization_logo"
                  type="file"
                  accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                  class="{{ $fileClass }}"
                  @unless($officerValidationPending || $hasExistingLogo) required @endunless
                />
              </div>
              <x-forms.helper>
                JPEG, PNG, or WebP. Max 5&nbsp;MB.
                @if ($hasExistingLogo)
                  A logo is already on file; upload a new file only if you want to replace it.
                @endif
              </x-forms.helper>
            </div>
            <div>
              <x-forms.label for="organization_name" required>Organization Name</x-forms.label>
              <x-forms.input
                id="organization_name"
                name="organization_name"
                type="text"
                placeholder="e.g., Computer Society"
                :value="old('organization_name', $prefill['organization_name'] ?? $organization?->organization_name)"
                :readonly="$hasStep1Autofill"
                class="{{ $hasStep1Autofill ? 'bg-slate-100 text-slate-700 cursor-not-allowed' : '' }}"
                required
              />
            </div>
            <div>
              <x-forms.label for="academic_year" required>Academic Year</x-forms.label>
              <x-forms.input
                id="academic_year"
                name="academic_year"
                type="text"
                placeholder="e.g., 2025-2026"
                :value="old('academic_year', $prefill['academic_year'] ?? $activeAcademicYear)"
                readonly
                required
              />
            </div>
            <div>
              <x-forms.label for="school" required>Department</x-forms.label>
              <x-forms.select id="school" name="school" required>
                @php $schoolVal = old('school', $prefill['school'] ?? $schoolPrefill); @endphp
                <option value="" disabled @selected($schoolVal === null || $schoolVal === '')>Select school</option>
                @foreach ($schoolOptions as $code => $label)
                  <option value="{{ $code }}" @selected($schoolVal === $code)>{{ $label }}</option>
                @endforeach
              </x-forms.select>
            </div>
            <div>
              <x-forms.label for="department_program" required>Program</x-forms.label>
              <x-forms.input
                id="department_program"
                name="department_program"
                type="text"
                placeholder="e.g., Computer Engineering"
                :value="old('department_program', $prefill['department_program'] ?? '')"
                required
              />
            </div>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header
          title="Proposal Details"
          subtitle="When and where you plan to hold the activity."
          content-padding="px-6"
          header-class="!pb-3 pt-3"
        />
        <div class="px-6 py-5">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-x-5">
            <div class="md:col-span-2">
              <x-forms.label for="project_activity_title" required>Project / Activity Title</x-forms.label>
              <x-forms.input
                id="project_activity_title"
                name="project_activity_title"
                type="text"
                :value="old('project_activity_title', $prefill['project_activity_title'] ?? '')"
                :readonly="$hasStep1Autofill"
                class="{{ $hasStep1Autofill ? 'bg-slate-100 text-slate-700 cursor-not-allowed' : '' }}"
                required
              />
            </div>
            <div>
              <x-forms.label for="proposed_start_date" required>Proposed Start Date</x-forms.label>
              <x-forms.input
                id="proposed_start_date"
                name="proposed_start_date"
                type="date"
                :value="old('proposed_start_date', $prefill['proposed_start_date'] ?? '')"
                :readonly="$hasStep1Autofill"
                class="{{ $hasStep1Autofill ? 'bg-slate-100 text-slate-700 cursor-not-allowed' : '' }}"
                required
              />
            </div>
            <div>
              <x-forms.label for="proposed_end_date" required>Proposed End Date</x-forms.label>
              <x-forms.input
                id="proposed_end_date"
                name="proposed_end_date"
                type="date"
                :value="old('proposed_end_date', $prefill['proposed_end_date'] ?? '')"
                :min="old('proposed_start_date', $prefill['proposed_start_date'] ?? '')"
                required
              />
            </div>
            <div>
              <x-forms.label for="proposed_start_time" required>Proposed Start Time</x-forms.label>
              <x-forms.input
                id="proposed_start_time"
                name="proposed_start_time"
                type="time"
                :value="old('proposed_start_time', $prefill['proposed_start_time'] ?? '')"
                required
              />
            </div>
            <div>
              <x-forms.label for="proposed_end_time" required>Proposed End Time</x-forms.label>
              <x-forms.input
                id="proposed_end_time"
                name="proposed_end_time"
                type="time"
                :value="old('proposed_end_time', $prefill['proposed_end_time'] ?? '')"
                required
              />
            </div>
            <div>
              <x-forms.label for="venue" required>Venue</x-forms.label>
              <x-forms.input
                id="venue"
                name="venue"
                type="text"
                placeholder="e.g., University Auditorium"
                :value="old('venue', $prefill['venue'] ?? '')"
                :readonly="$hasStep1Autofill"
                class="{{ $hasStep1Autofill ? 'bg-slate-100 text-slate-700 cursor-not-allowed' : '' }}"
                required
              />
            </div>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header
          title="Objectives"
          subtitle="State the purpose and intended outcomes."
          content-padding="px-6"
          header-class="!pb-3 pt-3"
        />
        <div class="px-6 py-5 space-y-4">
          <div>
            <x-forms.label for="overall_goal" required>Overall Goal</x-forms.label>
            <x-forms.textarea id="overall_goal" name="overall_goal" rows="4" required>{{ old('overall_goal', $prefill['overall_goal'] ?? '') }}</x-forms.textarea>
          </div>
          <div>
            <x-forms.label for="specific_objectives" required>Specific Objectives</x-forms.label>
            <x-forms.textarea
              id="specific_objectives"
              name="specific_objectives"
              rows="5"
              placeholder="List concrete, measurable objectives (one per line or short paragraphs)."
              required
            >{{ old('specific_objectives', $prefill['specific_objectives'] ?? '') }}</x-forms.textarea>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header
          title="Activity Description"
          subtitle="How the activity will run."
          content-padding="px-6"
          header-class="!pb-3 pt-3"
        />
        <div class="px-6 py-5 space-y-4">
          <div>
            <x-forms.label for="criteria_mechanics" required>Criteria / Mechanics</x-forms.label>
            <x-forms.textarea id="criteria_mechanics" name="criteria_mechanics" rows="4" required>{{ old('criteria_mechanics', $prefill['criteria_mechanics'] ?? '') }}</x-forms.textarea>
          </div>
          <div>
            <x-forms.label for="program_flow" required>Program Flow</x-forms.label>
            <x-forms.textarea
              id="program_flow"
              name="program_flow"
              rows="5"
              placeholder="Outline the sequence of sessions, segments, or flow of the activity."
              required
            >{{ old('program_flow', $prefill['program_flow'] ?? '') }}</x-forms.textarea>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header
          title="Budget"
          subtitle="Estimated costs and funding."
          content-padding="px-6"
          header-class="!pb-3 pt-3"
        />
        <div class="px-6 py-5">
          @error('budget_breakdown')
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm" role="alert">
              {{ $message }}
            </div>
          @enderror
          <div id="budget-breakdown-client-error" class="mb-4 hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm" role="alert" aria-live="polite"></div>
          <div class="grid grid-cols-1 gap-4">
            <div>
              <x-forms.label for="proposed_budget" required>Proposed Budget (total)</x-forms.label>
              <x-forms.input
                id="proposed_budget"
                name="proposed_budget"
                type="number"
                step="0.01"
                min="0"
                placeholder="0.00"
                :value="old('proposed_budget', $prefill['proposed_budget'] ?? '')"
                :readonly="$hasStep1Autofill"
                class="{{ $hasStep1Autofill ? 'bg-slate-100 text-slate-700 cursor-not-allowed' : '' }}"
                required
              />
            </div>
            <div>
              <span class="block text-sm font-semibold leading-snug text-slate-800">
                Source of Funding
                <span class="text-rose-600">*</span>
              </span>
              @if ($hasStep1Autofill)
                <input type="hidden" id="source_of_funding_locked" name="source_of_funding" value="{{ $sourceOfFundingValue }}" />
                <div class="mt-2 inline-flex items-center rounded-xl border border-slate-300 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">
                  {{ $sourceOfFundingValue }}
                </div>
              @else
                <div class="mt-2 flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-slate-100/70 p-3">
                  <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-800">
                    <input
                      type="radio"
                      name="source_of_funding"
                      value="RSO Fund"
                      class="size-4 border-slate-300 text-[#003E9F] focus:ring-[#003E9F]/25"
                      @checked($sourceOfFundingValue === 'RSO Fund')
                    />
                    RSO Fund
                  </label>
                  <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-800">
                    <input
                      type="radio"
                      name="source_of_funding"
                      value="RSO Savings"
                      class="size-4 border-slate-300 text-[#003E9F] focus:ring-[#003E9F]/25"
                      @checked($sourceOfFundingValue === 'RSO Savings')
                    />
                    RSO Savings
                  </label>
                  <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-800">
                    <input
                      type="radio"
                      name="source_of_funding"
                      value="External"
                      class="size-4 border-slate-300 text-[#003E9F] focus:ring-[#003E9F]/25"
                      @checked($sourceOfFundingValue === 'External')
                    />
                    External
                  </label>
                </div>
              @endif
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <div class="mb-3 flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Detailed Budget Table</p>
                <button
                  type="button"
                  id="add-budget-row"
                  class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                >
                  Add row
                </button>
              </div>
              <input type="hidden" id="budget_items_payload" name="budget_items_payload" value="{{ $prefillBudgetItems }}" />
              <div class="max-h-[18.5rem] overflow-x-auto overflow-y-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full min-w-[640px] table-fixed text-sm">
                  <thead class="sticky top-0 z-10 bg-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                      <th class="px-3 py-2 text-left">Material</th>
                      <th class="w-28 px-3 py-2 text-left">Quantity</th>
                      <th class="w-36 px-3 py-2 text-left">Unit Price</th>
                      <th class="w-36 px-3 py-2 text-left">Price</th>
                      <th class="w-20 px-3 py-2 text-center">Action</th>
                    </tr>
                  </thead>
                  <tbody id="budget-items-body" class="divide-y divide-slate-100"></tbody>
                  <tfoot class="bg-slate-50">
                    <tr>
                      <td colspan="3" class="px-3 py-2 text-right text-sm font-bold text-slate-700">TOTAL</td>
                      <td class="px-3 py-2 text-sm font-bold text-slate-900" id="budget-items-total">0.00</td>
                      <td class="px-3 py-2"></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <p class="mt-2 text-xs text-slate-500">Step 2 detailed breakdown of the budget declared in Step 1.</p>
            </div>
            <div
              id="external-funding-attachment"
              class="{{ $sourceOfFundingValue === 'External' ? '' : 'hidden' }}"
            >
              <x-forms.label for="external_funding_support" :required="! $hasExternalFundingFile">External funding support</x-forms.label>
              <div class="mt-2 w-fit max-w-full">
                <input
                  id="external_funding_support"
                  name="external_funding_support"
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"
                  class="{{ $fileClass }}"
                />
              </div>
              <x-forms.helper>
                Upload a letter, proof, or supporting document from the external funding source.
                @if ($hasExternalFundingFile)
                  A file is already on file; upload a new one only to replace it.
                @else
                  Required when you submit for review with External funding (optional while saving a draft).
                @endif
              </x-forms.helper>
            </div>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header
          title="Additional"
          subtitle="Optional supporting document."
          content-padding="px-6"
          header-class="!pb-3 pt-3"
        />
        <div class="px-6 py-5">
          <div>
            <x-forms.label for="resume_resource_persons">Resume of Resource Person/s</x-forms.label>
            <div class="mt-2 w-fit max-w-full">
              <input
                id="resume_resource_persons"
                name="resume_resource_persons"
                type="file"
                accept=".pdf,.doc,.docx,application/pdf"
                class="{{ $fileClass }}"
              />
            </div>
            <x-forms.helper>Optional. PDF or Word. Max 10&nbsp;MB.</x-forms.helper>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <div class="px-6 py-4 sm:py-5">
          {{-- DOM: first type=submit stays "Submit for review" (Enter key). Visual order via flex reverse on sm+. --}}
          <div class="flex flex-col gap-2 sm:flex-row sm:items-stretch sm:gap-3">
            <x-ui.button
              type="reset"
              variant="secondary"
              class="w-full shrink-0 sm:h-10 sm:w-auto sm:self-center"
              :disabled="$officerValidationPending"
            >
              Reset form
            </x-ui.button>
            <div class="flex w-full flex-col-reverse gap-2.5 sm:ml-auto sm:w-auto sm:flex-row-reverse sm:items-center sm:gap-3">
              <x-ui.button
                type="submit"
                class="w-full sm:w-auto sm:min-w-[10.5rem]"
                name="proposal_action"
                value="submit"
                :disabled="$officerValidationPending"
              >
                Submit for review
              </x-ui.button>
              <x-ui.button
                type="submit"
                variant="secondary"
                class="w-full sm:w-auto sm:min-w-[10.5rem]"
                name="proposal_action"
                value="draft"
                :disabled="$officerValidationPending"
              >
                Save as draft
              </x-ui.button>
            </div>
          </div>
          <p class="mt-5 max-w-3xl border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-500">
            <span class="font-medium text-slate-600">Save as draft</span> keeps your work without sending it to SDAO.
            <span class="font-medium text-slate-600">Submit for review</span> locks the form until staff respond (unless the proposal is returned for revision).
          </p>
        </div>
      </x-ui.card>

    </fieldset>
  </form>
  @endif
  @endif

</div>

@unless ($officerValidationPending)
  <script>
    (function () {
      var start = document.getElementById('proposed_start_date');
      var end = document.getElementById('proposed_end_date');
      if (!start || !end) return;
      function syncEndMin() {
        if (start.value) {
          end.min = start.value;
        } else {
          end.removeAttribute('min');
        }
      }
      start.addEventListener('change', syncEndMin);
      start.addEventListener('input', syncEndMin);
      syncEndMin();
    })();
    (function () {
      var startTime = document.getElementById('proposed_start_time');
      var endTime = document.getElementById('proposed_end_time');
      if (!startTime || !endTime) return;
      function syncEndTimeMin() {
        if (startTime.value) {
          endTime.min = startTime.value;
        } else {
          endTime.removeAttribute('min');
        }
      }
      startTime.addEventListener('change', syncEndTimeMin);
      startTime.addEventListener('input', syncEndTimeMin);
      syncEndTimeMin();
    })();
    (function () {
      var wrap = document.getElementById('external-funding-attachment');
      if (!wrap) return;
      var radios = document.querySelectorAll('input[name="source_of_funding"]');
      var lockedFunding = document.getElementById('source_of_funding_locked');
      function sync() {
        var v = '';
        radios.forEach(function (r) {
          if (r.checked) v = r.value;
        });
        if (!v && lockedFunding) {
          v = lockedFunding.value || '';
        }
        if (v === 'External') {
          wrap.classList.remove('hidden');
        } else {
          wrap.classList.add('hidden');
        }
      }
      radios.forEach(function (r) {
        r.addEventListener('change', sync);
      });
      sync();
    })();
    (function () {
      var form = document.getElementById('activity-proposal-form');
      var clientErr = document.getElementById('budget-breakdown-client-error');
      var body = document.getElementById('budget-items-body');
      var totalEl = document.getElementById('budget-items-total');
      var addRowBtn = document.getElementById('add-budget-row');
      var payloadInput = document.getElementById('budget_items_payload');
      var proposedBudgetEl = document.getElementById('proposed_budget');
      if (!form || !clientErr || !body || !totalEl || !addRowBtn || !payloadInput || !proposedBudgetEl) return;

      function money(v) {
        var n = parseFloat(String(v || '').replace(',', '.'));
        return isNaN(n) ? 0 : n;
      }

      function buildRow(item) {
        var tr = document.createElement('tr');
        tr.className = 'align-top';
        tr.innerHTML =
          '<td class="px-3 py-2"><input type="text" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm" data-col="material" value="' + (item.material || '') + '" /></td>' +
          '<td class="px-3 py-2"><input type="number" min="0" step="0.01" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm" data-col="quantity" value="' + (item.quantity || '') + '" /></td>' +
          '<td class="px-3 py-2"><input type="number" min="0" step="0.01" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm" data-col="unit_price" value="' + (item.unit_price || '') + '" /></td>' +
          '<td class="px-3 py-2"><input type="number" min="0" step="0.01" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1.5 text-sm font-medium text-slate-700" data-col="price" value="' + (item.price || '') + '" readonly tabindex="-1" /></td>' +
          '<td class="px-3 py-2 text-center"><button type="button" class="rounded-md border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" data-remove-row>Remove</button></td>';
        return tr;
      }

      function readRows() {
        var rows = Array.from(body.querySelectorAll('tr'));
        return rows.map(function (row) {
          var quantity = money(row.querySelector('[data-col="quantity"]').value);
          var unitPrice = money(row.querySelector('[data-col="unit_price"]').value);
          var computedPrice = quantity * unitPrice;
          var priceInput = row.querySelector('[data-col="price"]');
          if (priceInput) {
            priceInput.value = computedPrice > 0 ? computedPrice.toFixed(2) : '';
          }
          return {
            material: row.querySelector('[data-col="material"]').value.trim(),
            quantity: quantity,
            unit_price: unitPrice,
            price: computedPrice,
          };
        }).filter(function (r) {
          return r.material !== '' || r.quantity > 0 || r.unit_price > 0;
        });
      }

      function syncBudget() {
        var rows = readRows();
        var sum = rows.reduce(function (acc, row) { return acc + money(row.price); }, 0);
        totalEl.textContent = sum.toFixed(2);
        payloadInput.value = JSON.stringify(rows);

        var proposed = money(proposedBudgetEl.value);
        if (rows.length === 0) {
          clientErr.textContent = 'Add at least one budget row.';
          clientErr.classList.remove('hidden');
          return;
        }
        if (Math.abs(proposed - sum) > 0.01) {
          clientErr.textContent = 'Proposed Budget (total) must equal the detailed table total. Your total is ' + proposed.toFixed(2) + ' but rows sum to ' + sum.toFixed(2) + '.';
          clientErr.classList.remove('hidden');
          return;
        }
        clientErr.textContent = '';
        clientErr.classList.add('hidden');
      }

      function addRow(item) {
        var row = buildRow(item || {});
        body.appendChild(row);
        row.querySelectorAll('input').forEach(function (input) {
          input.addEventListener('input', syncBudget);
          input.addEventListener('change', syncBudget);
        });
        row.querySelector('[data-remove-row]').addEventListener('click', function () {
          row.remove();
          syncBudget();
        });
        syncBudget();
      }

      var seed = [];
      try {
        var parsed = JSON.parse(payloadInput.value || '[]');
        if (Array.isArray(parsed)) seed = parsed;
      } catch (e) {}
      if (seed.length === 0) seed = [{}];
      seed.forEach(function (item) { addRow(item); });

      addRowBtn.addEventListener('click', function () { addRow({}); });
      proposedBudgetEl.addEventListener('input', syncBudget);
      proposedBudgetEl.addEventListener('change', syncBudget);

      form.addEventListener('submit', function (e) {
        syncBudget();
        if (!clientErr.classList.contains('hidden')) {
          e.preventDefault();
          clientErr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    })();
  </script>
@endunless

@endsection
