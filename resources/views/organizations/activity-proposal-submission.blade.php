@extends('layouts.organization')

@section('title', 'Submit Proposal — NU Lipa SDAO')

@section('content')

@php
  $officerValidationPending = $officerValidationPending ?? false;
  $calendarEntry = $calendarEntry ?? null;
  $linkedProposal = $linkedProposal ?? null;
  $prefill = is_array($prefill ?? null) ? $prefill : [];
  $hasExistingLogo = (bool) ($linkedProposal?->organization_logo_path);
  // inline-block + w-auto: keeps the native file control only as wide as the visible picker so
  // browser validation popovers anchor near "Choose File" instead of a full-width hit box.
  $fileClass = 'inline-block w-auto max-w-full cursor-pointer text-sm text-slate-600 file:mr-4 file:cursor-pointer file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-slate-800 hover:file:bg-slate-200/80';
@endphp

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">

  <header class="mb-8">
    @if ($calendarEntry)
      <a href="{{ route('organizations.submitted-documents.calendars.show', $calendarEntry->activity_calendar_id) }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
        Back to activity calendar
      </a>
    @else
      <a href="{{ route('organizations.activity-submission') }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
        Back to Activity Submission
      </a>
    @endif
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

  @if ($calendarEntry)
    <div class="mb-6 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-4 text-sm text-sky-950 shadow-sm">
      <p class="font-semibold text-sky-900">Calendar activity (read-only summary)</p>
      <dl class="mt-3 grid gap-2 text-sky-900/90 sm:grid-cols-2">
        <div>
          <dt class="text-xs font-medium uppercase tracking-wide text-sky-800/80">Title</dt>
          <dd class="mt-0.5 font-medium">{{ $calendarEntry->activity_name }}</dd>
        </div>
        <div>
          <dt class="text-xs font-medium uppercase tracking-wide text-sky-800/80">Date</dt>
          <dd class="mt-0.5">{{ optional($calendarEntry->activity_date)->format('M j, Y') ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-xs font-medium uppercase tracking-wide text-sky-800/80">Venue</dt>
          <dd class="mt-0.5">{{ $calendarEntry->venue ?: '—' }}</dd>
        </div>
        <div>
          <dt class="text-xs font-medium uppercase tracking-wide text-sky-800/80">SDG</dt>
          <dd class="mt-0.5">{{ $calendarEntry->sdg ?: '—' }}</dd>
        </div>
      </dl>
    </div>
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
            <div>
              <x-forms.label for="source_of_funding" required>Source of Funding</x-forms.label>
              <x-forms.input
                id="source_of_funding"
                name="source_of_funding"
                type="text"
                placeholder="e.g., Organization funds, sponsorship"
                :value="old('source_of_funding', $prefill['source_of_funding'] ?? '')"
                required
              />
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
  </script>
@endunless

@endsection
