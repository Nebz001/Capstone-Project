@extends('layouts.organization')

@section('title', 'Submit Proposal — NU Lipa SDAO')

@section('content')

@php
  $officerValidationPending = $officerValidationPending ?? false;
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
      Submit a detailed activity proposal for SDAO review.
    </p>
  </header>

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
              <x-forms.label for="organization_logo" required>Organization Logo</x-forms.label>
              <div class="mt-2 w-fit max-w-full">
                <input
                  id="organization_logo"
                  name="organization_logo"
                  type="file"
                  accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                  class="{{ $fileClass }}"
                  @unless($officerValidationPending) required @endunless
                />
              </div>
              <x-forms.helper>JPEG, PNG, or WebP. Max 5&nbsp;MB.</x-forms.helper>
            </div>
            <div>
              <x-forms.label for="organization_name" required>Organization Name</x-forms.label>
              <x-forms.input
                id="organization_name"
                name="organization_name"
                type="text"
                placeholder="e.g., Computer Society"
                :value="old('organization_name', $organization->organization_name)"
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
                :value="old('academic_year')"
                required
              />
            </div>
            <div>
              <x-forms.label for="school" required>School</x-forms.label>
              <x-forms.select id="school" name="school" required>
                <option value="" disabled @selected(old('school', $schoolPrefill) === null || old('school', $schoolPrefill) === '')>Select school</option>
                @foreach ($schoolOptions as $code => $label)
                  <option value="{{ $code }}" @selected(old('school', $schoolPrefill) === $code)>{{ $label }}</option>
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
                :value="old('department_program', $organization->college_department)"
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
                :value="old('project_activity_title')"
                required
              />
            </div>
            <div>
              <x-forms.label for="proposed_start_date" required>Proposed Start Date</x-forms.label>
              <x-forms.input
                id="proposed_start_date"
                name="proposed_start_date"
                type="date"
                :value="old('proposed_start_date')"
                required
              />
            </div>
            <div>
              <x-forms.label for="proposed_end_date" required>Proposed End Date</x-forms.label>
              <x-forms.input
                id="proposed_end_date"
                name="proposed_end_date"
                type="date"
                :value="old('proposed_end_date')"
                :min="old('proposed_start_date')"
                required
              />
            </div>
            <div>
              <x-forms.label for="proposed_time" required>Proposed Time</x-forms.label>
              <x-forms.input
                id="proposed_time"
                name="proposed_time"
                type="time"
                :value="old('proposed_time')"
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
                :value="old('venue')"
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
            <x-forms.textarea id="overall_goal" name="overall_goal" rows="4" required>{{ old('overall_goal') }}</x-forms.textarea>
          </div>
          <div>
            <x-forms.label for="specific_objectives" required>Specific Objectives</x-forms.label>
            <x-forms.textarea
              id="specific_objectives"
              name="specific_objectives"
              rows="5"
              placeholder="List concrete, measurable objectives (one per line or short paragraphs)."
              required
            >{{ old('specific_objectives') }}</x-forms.textarea>
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
            <x-forms.textarea id="criteria_mechanics" name="criteria_mechanics" rows="4" required>{{ old('criteria_mechanics') }}</x-forms.textarea>
          </div>
          <div>
            <x-forms.label for="program_flow" required>Program Flow</x-forms.label>
            <x-forms.textarea
              id="program_flow"
              name="program_flow"
              rows="5"
              placeholder="Outline the sequence of sessions, segments, or flow of the activity."
              required
            >{{ old('program_flow') }}</x-forms.textarea>
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
                :value="old('proposed_budget')"
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
                :value="old('source_of_funding')"
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
                :value="old('materials_supplies')"
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
                :value="old('food_beverage')"
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
                :value="old('other_expenses')"
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
        <div class="px-6 py-6">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
            <x-ui.button type="reset" variant="secondary" class="w-full sm:w-auto" :disabled="$officerValidationPending">Reset Form</x-ui.button>
            <x-ui.button type="submit" class="w-full sm:w-auto" :disabled="$officerValidationPending">
              Submit Proposal
            </x-ui.button>
          </div>
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
