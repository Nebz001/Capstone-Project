@extends('layouts.organization-portal')

@section('title', 'Step 1: Activity Request Form — NU Lipa SDAO')

@section('content')
@php
  $officerValidationPending = $officerValidationPending ?? false;
  $natureOptions = $natureOptions ?? [];
  $typeOptions = $typeOptions ?? [];
  $requestForm = $requestForm ?? null;
  $calendarEntries = $calendarEntries ?? collect();
  $selectedTypes = old('activity_types', $requestForm?->activity_types ?? []);
  $selectedTypes = is_array($selectedTypes) ? $selectedTypes : [];
  $selectedNature = old('nature_of_activity', $requestForm?->nature_of_activity ?? []);
  $selectedNature = is_array($selectedNature) ? $selectedNature : [];
  $step2Unlocked = $requestForm !== null;
  $hasCalendarActivities = $hasCalendarActivities ?? false;
  $proposalSource = old('proposal_source', request('proposal_source', $hasCalendarActivities ? 'calendar' : 'unlisted'));
  if (! in_array($proposalSource, ['calendar', 'unlisted'], true)) {
    $proposalSource = $hasCalendarActivities ? 'calendar' : 'unlisted';
  }
  $selectedCalendarEntryId = (int) old('activity_calendar_entry_id', $requestForm?->activity_calendar_entry_id);
@endphp

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">
  <header class="mb-8">
    <a href="{{ route('organizations.activity-submission') }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
      <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
      </svg>
      Back to Activity Submission
    </a>
    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Submit Proposal</h1>
    <p class="mt-1 text-sm text-slate-500">Complete Step 1 first before continuing to the full proposal submission form.</p>
  </header>

  <x-ui.card padding="p-0" class="mb-6">
    <div class="px-6 py-5">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-stretch">
        <div class="flex-1 rounded-2xl border-2 border-[#003E9F] bg-[#003E9F] px-4 py-3 text-white shadow-sm ring-2 ring-[#003E9F]/20">
          <div class="flex items-center gap-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white text-xs font-extrabold text-[#003E9F]">1</span>
            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-white/90">Current Step</p>
          </div>
          <p class="mt-1.5 text-sm font-bold">Activity Request Form</p>
        </div>
        @if ($step2Unlocked)
          <a
            href="{{ route('organizations.activity-proposal-submission', ['request_id' => $requestForm->id, 'proposal_source' => $proposalSource]) }}"
            class="flex-1 rounded-2xl border-2 border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-900 transition hover:bg-emerald-100"
          >
            <div class="flex items-center gap-2">
              <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-white">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
              </span>
              <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-emerald-700">Next Step (Unlocked)</p>
            </div>
            <p class="mt-1.5 text-sm font-bold">Proposal Submission</p>
          </a>
        @else
          <div class="flex-1 rounded-2xl border-2 border-slate-200 bg-slate-50 px-4 py-3 text-slate-600 opacity-90">
            <div class="flex items-center gap-2">
              <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-300 text-xs font-extrabold text-slate-700">2</span>
              <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Locked Until Step 1 Complete</p>
            </div>
            <p class="mt-1.5 text-sm font-semibold">Proposal Submission</p>
          </div>
        @endif
      </div>
    </div>
  </x-ui.card>

  @if (session('error'))
    <x-feedback.blocked-message variant="error" class="mb-6" :message="session('error')" />
  @endif

  @if ($officerValidationPending)
    <x-feedback.blocked-message
      class="mb-6"
      message="Your student officer account is pending SDAO validation. You cannot submit proposals until validation is complete."
    />
  @endif

  <form
    method="POST"
    action="{{ route('organizations.activity-proposal-request.store') }}"
    enctype="multipart/form-data"
    class="space-y-6"
  >
    @csrf
    @if ($requestForm)
      <input type="hidden" name="request_id" value="{{ $requestForm->id }}">
    @endif

    <fieldset
      @disabled($officerValidationPending)
      @class([
        'min-w-0 space-y-6 border-0 p-0 m-0',
        'pointer-events-none opacity-50' => $officerValidationPending,
      ])
    >
      <x-ui.card padding="p-0">
        <x-ui.card-section-header title="Proposal Option" subtitle="Choose how you want to prepare this proposal." content-padding="px-6" />
        <div class="px-6 py-6">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label class="cursor-pointer rounded-xl border px-4 py-3 transition {{ $proposalSource === 'calendar' ? 'border-[#003E9F] bg-[#003E9F]/5' : 'border-slate-200 bg-white hover:border-slate-300' }}">
              <input type="radio" name="proposal_source" value="calendar" class="sr-only" @checked($proposalSource === 'calendar') />
              <p class="text-sm font-semibold text-slate-900">From submitted Activity Calendar</p>
              <p class="mt-1 text-xs text-slate-600">Link this proposal to an activity already listed in your submitted calendar.</p>
            </label>
            <label class="cursor-pointer rounded-xl border px-4 py-3 transition {{ $proposalSource === 'unlisted' ? 'border-[#003E9F] bg-[#003E9F]/5' : 'border-slate-200 bg-white hover:border-slate-300' }}">
              <input type="radio" name="proposal_source" value="unlisted" class="sr-only" @checked($proposalSource === 'unlisted') />
              <p class="text-sm font-semibold text-slate-900">Activity not in submitted calendar</p>
              <p class="mt-1 text-xs text-slate-600">Use this for biglaan/unplanned activities or activities not included in the original calendar.</p>
            </label>
          </div>
          <div id="calendar-link-wrap" class="mt-4 {{ $proposalSource === 'calendar' ? '' : 'hidden' }}">
            <x-forms.label for="activity_calendar_entry_id" required>Select submitted calendar activity to link</x-forms.label>
            <x-forms.select id="activity_calendar_entry_id" name="activity_calendar_entry_id" :required="$proposalSource === 'calendar'">
              <option value="" disabled @selected($selectedCalendarEntryId <= 0)>Select activity</option>
              @foreach ($calendarEntries as $entry)
                <option
                  value="{{ $entry->id }}"
                  @selected($selectedCalendarEntryId === (int) $entry->id)
                  data-title="{{ $entry->activity_name }}"
                  data-date="{{ optional($entry->activity_date)->toDateString() }}"
                  data-venue="{{ $entry->venue }}"
                  data-sdg="{{ $entry->sdg }}"
                  data-budget="{{ $entry->budget }}"
                >
                  {{ optional($entry->activity_date)->format('M j, Y') ?? 'No date' }} — {{ $entry->activity_name }}
                </option>
              @endforeach
            </x-forms.select>
            <x-forms.helper>Only activities from your submitted Activity Calendar are listed here.</x-forms.helper>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header title="Basic Information" subtitle="Provide the summary details for this activity request." content-padding="px-6" />
        <div class="px-6 py-6">
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
              <x-forms.label for="rso_name" required>Name of RSO</x-forms.label>
              <x-forms.input id="rso_name" name="rso_name" type="text" :value="old('rso_name', $requestForm?->rso_name ?? ($organization->organization_name ?? ''))" required />
            </div>
            <div>
              <x-forms.label for="activity_title" required>Title of Activity</x-forms.label>
              <x-forms.input id="activity_title" name="activity_title" type="text" :value="old('activity_title', $requestForm?->activity_title)" required />
            </div>
            <div class="md:col-span-2">
              <x-forms.label for="partner_entities">Partner Organization(s) / School(s) / RSO</x-forms.label>
              <x-forms.input id="partner_entities" name="partner_entities" type="text" :value="old('partner_entities', $requestForm?->partner_entities)" />
            </div>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header title="Nature and Type of Activity" subtitle="Select all applicable options." content-padding="px-6" />
        <div class="px-6 py-6 space-y-6">
          <div>
            <p class="text-sm font-semibold text-slate-900">Nature of Activity <span class="text-rose-600">*</span></p>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
              @foreach ($natureOptions as $key)
                @php
                  $label = match ($key) {
                    'co_curricular' => 'Co-Curricular',
                    'non_curricular' => 'Non-Curricular',
                    'community_extension' => 'Community Extension',
                    'others' => 'Others',
                    default => ucfirst(str_replace('_', ' ', $key)),
                  };
                @endphp
                <x-forms.choice id="nature_{{ $key }}" name="nature_of_activity[]" type="checkbox" :value="$key" :checked="in_array($key, $selectedNature, true)">
                  {{ $label }}
                </x-forms.choice>
              @endforeach
            </div>
            <div class="mt-3">
              <x-forms.label for="nature_other">Nature of Activity (Others)</x-forms.label>
              <x-forms.input
                id="nature_other"
                name="nature_other"
                type="text"
                :value="old('nature_other', $requestForm?->nature_other)"
                :disabled="! in_array('others', $selectedNature, true)"
              />
            </div>
          </div>

          <div>
            <p class="text-sm font-semibold text-slate-900">Type of Activity <span class="text-rose-600">*</span></p>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
              @foreach ($typeOptions as $key)
                @php
                  $label = match ($key) {
                    'seminar_workshop' => 'Seminar / Workshop',
                    'general_assembly' => 'General Assembly',
                    'orientation' => 'Orientation',
                    'competition' => 'Competition',
                    'recruitment_audition' => 'Recruitment / Audition',
                    'donation_drive_fundraising' => 'Donation Drive / Fundraising Activity',
                    'outreach_donation' => 'Outreach (Donation)',
                    'fundraising_activity' => 'Fundraising Activity',
                    'off_campus_activity' => 'Off-campus Activity',
                    'others' => 'Others',
                    default => ucfirst(str_replace('_', ' ', $key)),
                  };
                @endphp
                <x-forms.choice id="activity_type_{{ $key }}" name="activity_types[]" type="checkbox" :value="$key" :checked="in_array($key, $selectedTypes, true)">
                  {{ $label }}
                </x-forms.choice>
              @endforeach
            </div>
            <div class="mt-3">
              <x-forms.label for="activity_type_other">Type of Activity (Others)</x-forms.label>
              <x-forms.input
                id="activity_type_other"
                name="activity_type_other"
                type="text"
                :value="old('activity_type_other', $requestForm?->activity_type_other)"
                :disabled="! in_array('others', $selectedTypes, true)"
              />
            </div>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header title="Activity Details" subtitle="Provide key planning details for this request." content-padding="px-6" />
        <div class="px-6 py-6">
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
              <x-forms.label for="target_sdg" required>Target SDG</x-forms.label>
              <x-forms.input id="target_sdg" name="target_sdg" type="text" :value="old('target_sdg', $requestForm?->target_sdg)" required />
            </div>
            <div>
              <x-forms.label for="proposed_budget" required>Proposed Budget</x-forms.label>
              <x-forms.input id="proposed_budget" name="proposed_budget" type="number" step="0.01" min="0" :value="old('proposed_budget', $requestForm?->proposed_budget)" required />
            </div>
            <div>
              <x-forms.label for="budget_source" required>Budget Source</x-forms.label>
              <x-forms.select id="budget_source" name="budget_source" required>
                <option value="" disabled @selected(old('budget_source') === null || old('budget_source') === '')>Select source</option>
                <option value="RSO Fund" @selected(old('budget_source', $requestForm?->budget_source) === 'RSO Fund')>RSO Fund</option>
                <option value="RSO Savings" @selected(old('budget_source', $requestForm?->budget_source) === 'RSO Savings')>RSO Savings</option>
                <option value="External" @selected(old('budget_source', $requestForm?->budget_source) === 'External')>External</option>
              </x-forms.select>
            </div>
            <div>
              <x-forms.label for="activity_date" required>Date of Activity</x-forms.label>
              <x-forms.input id="activity_date" name="activity_date" type="date" :value="old('activity_date', optional($requestForm?->activity_date)->toDateString())" required />
            </div>
            <div class="md:col-span-2">
              <x-forms.label for="venue" required>Venue</x-forms.label>
              <x-forms.input id="venue" name="venue" type="text" :value="old('venue', $requestForm?->venue)" required />
            </div>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header title="Required Attachments and Checklist" subtitle="All required items must be completed before you can continue to Step 2." content-padding="px-6" />
        <div class="px-6 py-6 space-y-5">
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">Request Letter <span class="text-rose-600">*</span></p>
            <p class="mt-1 text-xs text-slate-600">The request letter must include rationale, objectives, and program.</p>
            <div class="mt-3 space-y-2">
              <x-forms.choice id="request_letter_has_rationale" name="request_letter_has_rationale" type="checkbox" value="1" :checked="old('request_letter_has_rationale') == '1'">
                Includes Rationale
              </x-forms.choice>
              <x-forms.choice id="request_letter_has_objectives" name="request_letter_has_objectives" type="checkbox" value="1" :checked="old('request_letter_has_objectives') == '1'">
                Includes Objectives
              </x-forms.choice>
              <x-forms.choice id="request_letter_has_program" name="request_letter_has_program" type="checkbox" value="1" :checked="old('request_letter_has_program') == '1'">
                Includes Program
              </x-forms.choice>
            </div>
            <div class="mt-3">
              <x-forms.label for="request_letter" :required="! $requestForm?->request_letter_path">Upload Request Letter</x-forms.label>
              <x-forms.input id="request_letter" name="request_letter" type="file" :required="! $requestForm?->request_letter_path" />
            </div>
          </div>

          <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <x-forms.label for="speaker_resume">Resume of Speaker (required if Seminar / Workshop is selected)</x-forms.label>
            <x-forms.input id="speaker_resume" name="speaker_resume" type="file" />
          </div>

          <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <x-forms.label for="post_survey_form" :required="! $requestForm?->post_survey_form_path">Sample Post-Survey Form</x-forms.label>
            <x-forms.input id="post_survey_form" name="post_survey_form" type="file" :required="! $requestForm?->post_survey_form_path" />
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <div class="px-6 py-6">
          <div class="flex justify-end">
            <x-ui.button type="submit" class="w-full sm:w-auto">Save Step 1 and Continue to Step 2</x-ui.button>
          </div>
        </div>
      </x-ui.card>
    </fieldset>
  </form>
</div>
<script>
  (() => {
    const proposalSourceRadios = Array.from(document.querySelectorAll('input[name="proposal_source"]'));
    const calendarLinkWrap = document.getElementById('calendar-link-wrap');
    const calendarSelect = document.getElementById('activity_calendar_entry_id');
    const titleInput = document.getElementById('activity_title');
    const dateInput = document.getElementById('activity_date');
    const venueInput = document.getElementById('venue');
    const sdgInput = document.getElementById('target_sdg');
    const budgetInput = document.getElementById('proposed_budget');

    const selectedProposalSource = () => {
      const hit = proposalSourceRadios.find((r) => r.checked);
      return hit ? hit.value : 'calendar';
    };

    const syncProposalSourceUi = () => {
      const source = selectedProposalSource();
      const isCalendar = source === 'calendar';

      if (calendarLinkWrap) {
        calendarLinkWrap.classList.toggle('hidden', !isCalendar);
      }
      if (calendarSelect) {
        calendarSelect.required = isCalendar;
        if (!isCalendar) {
          calendarSelect.value = '';
        }
      }
    };

    const autofillFromCalendarSelection = () => {
      if (!calendarSelect) return;
      const option = calendarSelect.options[calendarSelect.selectedIndex];
      if (!option || !option.value) return;

      const title = option.getAttribute('data-title') || '';
      const date = option.getAttribute('data-date') || '';
      const venue = option.getAttribute('data-venue') || '';
      const sdg = option.getAttribute('data-sdg') || '';
      const budget = option.getAttribute('data-budget') || '';

      if (titleInput) titleInput.value = title;
      if (dateInput) dateInput.value = date;
      if (venueInput) venueInput.value = venue;
      if (sdgInput) sdgInput.value = sdg;
      if (budgetInput) budgetInput.value = budget;
    };

    proposalSourceRadios.forEach((radio) => {
      radio.addEventListener('change', syncProposalSourceUi);
    });
    if (calendarSelect) {
      calendarSelect.addEventListener('change', autofillFromCalendarSelection);
    }

    syncProposalSourceUi();
    if (calendarSelect && calendarSelect.value) {
      autofillFromCalendarSelection();
    }

    const setupSingleSelectCheckboxGroup = (selector, othersCheckboxId, othersInputId) => {
      const checkboxes = Array.from(document.querySelectorAll(selector));
      if (checkboxes.length === 0) return;
      const othersCheckbox = document.getElementById(othersCheckboxId);
      const othersInput = document.getElementById(othersInputId);

      const syncOthersInput = () => {
        if (!othersInput || !othersCheckbox) return;
        const enabled = othersCheckbox.checked;
        othersInput.disabled = !enabled;
        if (!enabled) {
          othersInput.value = '';
        }
      };

      checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
          if (checkbox.checked) {
            checkboxes.forEach((other) => {
              if (other !== checkbox) other.checked = false;
            });
          }
          syncOthersInput();
        });
      });

      syncOthersInput();
    };

    setupSingleSelectCheckboxGroup('input[name="nature_of_activity[]"]', 'nature_others', 'nature_other');
    setupSingleSelectCheckboxGroup('input[name="activity_types[]"]', 'activity_type_others', 'activity_type_other');
  })();
</script>
@endsection
