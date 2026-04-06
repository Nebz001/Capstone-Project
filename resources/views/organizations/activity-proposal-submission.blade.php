@extends('layouts.organization')

@section('title', 'Submit Proposal — NU Lipa SDAO')

@section('content')

@php
  $officerValidationPending = $officerValidationPending ?? false;
  $calendarEntry = $calendarEntry ?? null;
  $linkedProposal = $linkedProposal ?? null;
  $proposalCalendar = $proposalCalendar ?? null;
  $proposalCalendarEntries = $proposalCalendar?->entries ?? collect();
  $prefill = is_array($prefill ?? null) ? $prefill : [];
  $hasExistingLogo = (bool) ($linkedProposal?->organization_logo_path);
  $sourceOfFundingValue = old('source_of_funding', $prefill['source_of_funding'] ?? '');
  if ($sourceOfFundingValue === '' || $sourceOfFundingValue === null || ! in_array((string) $sourceOfFundingValue, ['Internal', 'External'], true)) {
    $sourceOfFundingValue = 'Internal';
  }
  $hasExternalFundingFile = (bool) ($linkedProposal?->external_funding_support_path ?? false);
  // inline-block + w-auto: keeps the native file control only as wide as the visible picker so
  // browser validation popovers anchor near "Choose File" instead of a full-width hit box.
  $fileClass = 'inline-block w-auto max-w-full cursor-pointer text-sm text-slate-600 file:mr-4 file:cursor-pointer file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-slate-800 hover:file:bg-slate-200/80';
@endphp

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">

  <header class="mb-8">
    <a href="{{ route('organizations.activity-submission') }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
      <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
      </svg>
      Back to Activity Submission
    </a>
    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
      Submit Proposal
    </h1>
    <p class="mt-1 text-sm text-slate-500">
      @if ($calendarEntry)
        You are completing the detailed proposal for one calendar activity. Other activities keep their own proposal records.
      @else
        Submit a detailed activity proposal for SDAO review.
      @endif
    </p>
  </header>

  @if ($proposalCalendar && $proposalCalendarEntries->isNotEmpty())
    <x-ui.card padding="p-0" class="mb-6">
      <x-ui.card-section-header
        title="Select activity from submitted calendar"
        subtitle="Choose one submitted activity. The proposal form below will load and save details for that selected row only."
        content-padding="px-6"
      />
      <div class="border-t border-slate-100 px-6 py-5">
        <form method="GET" action="{{ route('organizations.activity-proposal-submission') }}" class="max-w-4xl">
          <div>
            <x-forms.label for="calendar_entry_picker" required>Submitted calendar activity</x-forms.label>
            <x-forms.select id="calendar_entry_picker" name="calendar_entry" required onchange="this.form.submit()">
              <option value="" disabled @selected(! $calendarEntry)>Select an activity to load proposal fields</option>
              @foreach ($proposalCalendarEntries as $entry)
                @php
                  $entryStatus = strtoupper((string) ($entry->proposal?->proposal_status ?? ''));
                  $entryStatusLabel = match ($entryStatus) {
                    'DRAFT' => 'Draft',
                    'PENDING' => 'Submitted',
                    'UNDER_REVIEW' => 'Under review',
                    'REVISION' => 'For revision',
                    'APPROVED' => 'Approved',
                    'REJECTED' => 'Rejected',
                    default => $entry->proposal ? 'No status' : 'No proposal yet',
                  };
                @endphp
                <option value="{{ $entry->id }}" @selected((int) old('activity_calendar_entry_id', $calendarEntry?->id) === (int) $entry->id)>
                  {{ optional($entry->activity_date)->format('M j, Y') ?? 'No date' }} — {{ $entry->activity_name }} ({{ $entryStatusLabel }})
                </option>
              @endforeach
            </x-forms.select>
            <x-forms.helper>
              @php
                $calendarTermLabel = match ((string) ($proposalCalendar->semester ?? '')) {
                  'term_1' => 'Term 1',
                  'term_2' => 'Term 2',
                  'term_3' => 'Term 3',
                  default => 'Term —',
                };
              @endphp
              Calendar: {{ ($proposalCalendar->academic_year ?? '—') }} · {{ $calendarTermLabel }}.
              Switch activities anytime to work on proposals one by one.
            </x-forms.helper>
          </div>
        </form>

        @if ($calendarEntry)
          <div class="mt-5 rounded-xl border border-sky-200 bg-sky-50 px-5 py-5 text-sm text-sky-950 shadow-sm sm:px-6">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-sky-700">Selected activity summary</p>
            <p class="mt-2 text-lg font-bold leading-snug text-sky-950 sm:text-xl">{{ $calendarEntry->activity_name }}</p>
            <dl class="mt-5 grid grid-cols-1 gap-x-8 gap-y-4 text-sky-900/90 sm:grid-cols-2">
              <div>
                <dt class="text-[11px] font-medium uppercase tracking-wide text-sky-700/75">Date</dt>
                <dd class="mt-1 text-sm font-semibold text-sky-950">{{ optional($calendarEntry->activity_date)->format('M j, Y') ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-[11px] font-medium uppercase tracking-wide text-sky-700/75">Venue</dt>
                <dd class="mt-1 text-sm font-semibold text-sky-950">{{ $calendarEntry->venue ?: '—' }}</dd>
              </div>
              <div class="sm:col-span-2">
                <dt class="text-[11px] font-medium uppercase tracking-wide text-sky-700/75">SDG</dt>
                <dd class="mt-1 text-sm font-semibold text-sky-950">{{ $calendarEntry->sdg ?: '—' }}</dd>
              </div>
            </dl>
          </div>
        @endif
      </div>
    </x-ui.card>
  @endif

  @if (session('success'))
    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm" role="alert">
      {{ session('success') }}
    </div>
  @endif

  @if (session('error'))
    <x-feedback.blocked-message variant="error" class="mb-6" :message="session('error')" />
  @endif

  @if ($officerValidationPending)
    <x-feedback.blocked-message
      class="mb-6"
      message="Your student officer account is pending SDAO validation. You cannot submit proposals until validation is complete."
    />
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

  <form
    id="activity-proposal-form"
    method="POST"
    action="{{ route('organizations.activity-proposal-submission.store') }}"
    enctype="multipart/form-data"
    class="space-y-6"
    data-officer-validation-pending="{{ $officerValidationPending ? 'true' : 'false' }}"
  >
    @csrf
    @if ($calendarEntry)
      <input type="hidden" name="activity_calendar_entry_id" value="{{ $calendarEntry->id }}" />
    @endif

    <fieldset
      @disabled($officerValidationPending)
      @class([
        'min-w-0 space-y-6 border-0 p-0 m-0',
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
        />
        <div class="px-6 py-6">
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
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
                :value="old('organization_name', $prefill['organization_name'] ?? $organization->organization_name)"
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
                :value="old('academic_year', $prefill['academic_year'] ?? '')"
                required
              />
            </div>
            <div>
              <x-forms.label for="school" required>School</x-forms.label>
              <x-forms.select id="school" name="school" required>
                @php $schoolVal = old('school', $prefill['school'] ?? $schoolPrefill); @endphp
                <option value="" disabled @selected($schoolVal === null || $schoolVal === '')>Select school</option>
                @foreach ($schoolOptions as $code => $label)
                  <option value="{{ $code }}" @selected($schoolVal === $code)>{{ $label }}</option>
                @endforeach
              </x-forms.select>
            </div>
            <div>
              <x-forms.label for="department_program" required>Department / Program</x-forms.label>
              <x-forms.input
                id="department_program"
                name="department_program"
                type="text"
                placeholder="e.g., Computer Engineering"
                :value="old('department_program', $prefill['department_program'] ?? $organization->college_department)"
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
        />
        <div class="px-6 py-6">
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div class="md:col-span-2">
              <x-forms.label for="project_activity_title" required>Project / Activity Title</x-forms.label>
              <x-forms.input
                id="project_activity_title"
                name="project_activity_title"
                type="text"
                :value="old('project_activity_title', $prefill['project_activity_title'] ?? '')"
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
              <x-forms.label for="proposed_time" required>Proposed Time</x-forms.label>
              <x-forms.input
                id="proposed_time"
                name="proposed_time"
                type="time"
                :value="old('proposed_time', $prefill['proposed_time'] ?? '')"
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
        />
        <div class="px-6 py-6 space-y-6">
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
        />
        <div class="px-6 py-6 space-y-6">
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
        />
        <div class="px-6 py-6">
          @error('budget_breakdown')
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm" role="alert">
              {{ $message }}
            </div>
          @enderror
          <div id="budget-breakdown-client-error" class="mb-4 hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm" role="alert" aria-live="polite"></div>
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
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
                required
              />
            </div>
            <div class="md:col-span-2">
              <span class="block text-sm font-semibold leading-snug text-slate-800">
                Source of Funding
                <span class="text-rose-600">*</span>
              </span>
              <div class="mt-2 flex flex-wrap gap-6">
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-800">
                  <input
                    type="radio"
                    name="source_of_funding"
                    value="Internal"
                    class="size-4 border-slate-300 text-[#003E9F] focus:ring-[#003E9F]/25"
                    @checked($sourceOfFundingValue === 'Internal')
                  />
                  Internal
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
            </div>
            <div
              id="external-funding-attachment"
              class="md:col-span-2 {{ $sourceOfFundingValue === 'External' ? '' : 'hidden' }}"
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
            <div>
              <x-forms.label for="materials_supplies" required>Materials and Supplies</x-forms.label>
              <x-forms.input
                id="materials_supplies"
                name="materials_supplies"
                type="number"
                step="0.01"
                min="0"
                placeholder="0.00"
                :value="old('materials_supplies', $prefill['materials_supplies'] ?? '')"
                required
              />
            </div>
            <div>
              <x-forms.label for="food_beverage" required>Food and Beverage</x-forms.label>
              <x-forms.input
                id="food_beverage"
                name="food_beverage"
                type="number"
                step="0.01"
                min="0"
                placeholder="0.00"
                :value="old('food_beverage', $prefill['food_beverage'] ?? '')"
                required
              />
            </div>
            <div class="md:col-span-2">
              <x-forms.label for="other_expenses" required>Other Expenses</x-forms.label>
              <x-forms.input
                id="other_expenses"
                name="other_expenses"
                type="number"
                step="0.01"
                min="0"
                placeholder="0.00"
                :value="old('other_expenses', $prefill['other_expenses'] ?? '')"
                required
              />
            </div>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header
          title="Additional"
          subtitle="Optional supporting document."
          content-padding="px-6"
        />
        <div class="px-6 py-6">
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
        <div class="px-6 py-6 sm:py-7">
          {{-- DOM: first type=submit stays "Submit for review" (Enter key). Visual order via flex reverse on sm+. --}}
          <div class="flex flex-col gap-3 sm:flex-row sm:items-stretch sm:gap-4">
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
      var wrap = document.getElementById('external-funding-attachment');
      if (!wrap) return;
      var radios = document.querySelectorAll('input[name="source_of_funding"]');
      function sync() {
        var v = '';
        radios.forEach(function (r) {
          if (r.checked) v = r.value;
        });
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
      if (!form || !clientErr) return;

      var budgetFieldIds = ['proposed_budget', 'materials_supplies', 'food_beverage', 'other_expenses'];

      function parseAmount(id) {
        var el = document.getElementById(id);
        if (!el) return 0;
        var raw = String(el.value || '').trim().replace(',', '.');
        if (raw === '') return 0;
        var n = parseFloat(raw);
        return isNaN(n) ? 0 : n;
      }

      function syncBudgetValidation() {
        var total = Math.round(parseAmount('proposed_budget') * 100) / 100;
        var sum =
          Math.round(
            (parseAmount('materials_supplies') + parseAmount('food_beverage') + parseAmount('other_expenses')) * 100
          ) / 100;
        if (Math.abs(total - sum) > 0.01) {
          clientErr.textContent =
            'Proposed Budget (total) must equal Materials and Supplies + Food and Beverage + Other Expenses. Your total is ' +
            total.toFixed(2) +
            ' but the expense lines sum to ' +
            sum.toFixed(2) +
            '.';
          clientErr.classList.remove('hidden');
        } else {
          clientErr.textContent = '';
          clientErr.classList.add('hidden');
        }
      }

      budgetFieldIds.forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
          el.addEventListener('input', syncBudgetValidation);
          el.addEventListener('change', syncBudgetValidation);
        }
      });

      syncBudgetValidation();

      form.addEventListener('submit', function (e) {
        syncBudgetValidation();
        if (!clientErr.classList.contains('hidden')) {
          e.preventDefault();
          clientErr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    })();
  </script>
@endunless

@endsection
