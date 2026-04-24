@extends('layouts.organization-portal')

@section('title', 'Step 1: Activity Request Form — NU Lipa SDAO')

@section('content')
@php
  $officerValidationPending = $officerValidationPending ?? false;
  $blockedMessage = $blockedMessage ?? null;
  $proposalAccessBlocked = $officerValidationPending || (is_string($blockedMessage) && trim($blockedMessage) !== '');
  if (! $blockedMessage && $officerValidationPending) {
    $blockedMessage = 'Your student officer account is pending SDAO validation. You cannot submit proposals until validation is complete.';
  }
  $natureOptions = $natureOptions ?? [];
  $typeOptions = $typeOptions ?? [];
  $requestForm = $requestForm ?? null;
  $requestAttachmentLinks = is_array($requestAttachmentLinks ?? null) ? $requestAttachmentLinks : [];
  $requestLetterLink = $requestAttachmentLinks['request_letter'] ?? null;
  $speakerResumeLink = $requestAttachmentLinks['speaker_resume'] ?? null;
  $postSurveyLink = $requestAttachmentLinks['post_survey_form'] ?? null;
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
  $sdgOptions = array_map(fn ($n) => 'SDG '.$n, range(1, 17));
  $selectedTargetSdgsRaw = old('target_sdg', $requestForm?->target_sdg ?? []);
  if (is_array($selectedTargetSdgsRaw)) {
    $selectedTargetSdgs = array_values(array_filter($selectedTargetSdgsRaw, fn ($value) => is_string($value) && $value !== ''));
  } elseif (is_string($selectedTargetSdgsRaw) && trim($selectedTargetSdgsRaw) !== '') {
    $selectedTargetSdgs = array_values(array_filter(array_map('trim', explode(',', $selectedTargetSdgsRaw))));
  } else {
    $selectedTargetSdgs = [];
  }
@endphp

<div class="proposal-request-page mx-auto max-w-screen-2xl overflow-x-hidden px-4 py-8 sm:px-6 lg:px-10">
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

  @if ($proposalAccessBlocked)
    <x-feedback.blocked-message
      variant="error"
      class="mb-6"
      :message="$blockedMessage"
    />
  @else

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

  <form
    id="activity-proposal-request-form"
    method="POST"
    action="{{ route('organizations.activity-proposal-request.store') }}"
    enctype="multipart/form-data"
    class="space-y-4"
  >
    @csrf
    @if ($requestForm)
      <input type="hidden" name="request_id" value="{{ $requestForm->id }}">
    @endif
    @if ($errors->any())
      <x-feedback.blocked-message
        variant="error"
        :message="'Please complete the required fields marked below before continuing to Step 2.'"
      />
    @endif

    <fieldset
      @disabled($officerValidationPending)
      @class([
        'min-w-0 space-y-4 border-0 p-0 m-0',
        'pointer-events-none opacity-50' => $officerValidationPending,
      ])
    >
      <x-ui.card padding="p-0">
        <x-ui.card-section-header title="Proposal Option" subtitle="Choose how you want to prepare this proposal." content-padding="px-6" header-class="!pb-3 pt-3" />
        <div class="px-6 py-5">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label
              data-proposal-option="calendar"
              class="proposal-source-option cursor-pointer rounded-xl border px-4 py-3 transition duration-150 {{ $proposalSource === 'calendar' ? 'border-2 border-[#003E9F] bg-[#003E9F]/10 shadow-sm ring-1 ring-[#003E9F]/20' : 'border-slate-200 bg-white hover:border-slate-300' }}"
            >
              <input type="radio" name="proposal_source" value="calendar" class="sr-only" @checked($proposalSource === 'calendar') />
              <div class="flex items-start justify-between gap-3">
                <p data-option-title class="text-sm font-semibold {{ $proposalSource === 'calendar' ? 'text-[#003E9F]' : 'text-slate-900' }}">From submitted Activity Calendar</p>
                <span
                  data-option-check
                  class="inline-flex h-5 w-5 items-center justify-center rounded-full border text-[11px] font-bold transition {{ $proposalSource === 'calendar' ? 'border-[#003E9F] bg-[#003E9F] text-white' : 'border-slate-300 bg-white text-transparent' }}"
                  aria-hidden="true"
                >
                  ✓
                </span>
              </div>
              <p data-option-helper class="mt-1 text-xs {{ $proposalSource === 'calendar' ? 'text-[#003E9F]/80' : 'text-slate-600' }}">Link this proposal to an activity already listed in your submitted calendar.</p>
            </label>
            <label
              data-proposal-option="unlisted"
              class="proposal-source-option cursor-pointer rounded-xl border px-4 py-3 transition duration-150 {{ $proposalSource === 'unlisted' ? 'border-2 border-[#003E9F] bg-[#003E9F]/10 shadow-sm ring-1 ring-[#003E9F]/20' : 'border-slate-200 bg-white hover:border-slate-300' }}"
            >
              <input type="radio" name="proposal_source" value="unlisted" class="sr-only" @checked($proposalSource === 'unlisted') />
              <div class="flex items-start justify-between gap-3">
                <p data-option-title class="text-sm font-semibold {{ $proposalSource === 'unlisted' ? 'text-[#003E9F]' : 'text-slate-900' }}">Activity not in submitted calendar</p>
                <span
                  data-option-check
                  class="inline-flex h-5 w-5 items-center justify-center rounded-full border text-[11px] font-bold transition {{ $proposalSource === 'unlisted' ? 'border-[#003E9F] bg-[#003E9F] text-white' : 'border-slate-300 bg-white text-transparent' }}"
                  aria-hidden="true"
                >
                  ✓
                </span>
              </div>
              <p data-option-helper class="mt-1 text-xs {{ $proposalSource === 'unlisted' ? 'text-[#003E9F]/80' : 'text-slate-600' }}">Use this for unplanned activities not included in the original calendar (Biglaan).</p>
            </label>
          </div>
          <div id="calendar-link-wrap" class="mt-3 {{ $proposalSource === 'calendar' ? '' : 'hidden' }}">
            <x-forms.label for="activity_calendar_entry_id" required class="{{ $errors->has('activity_calendar_entry_id') ? '!text-rose-700' : '' }}">Select submitted calendar activity to link</x-forms.label>
            <x-forms.select id="activity_calendar_entry_id" name="activity_calendar_entry_id" :required="$proposalSource === 'calendar'" class="{{ $errors->has('activity_calendar_entry_id') ? '!border-rose-400 !ring-rose-500/20 focus:!border-rose-500 focus:!ring-rose-500/20' : '' }}">
              <option value="" disabled @selected($selectedCalendarEntryId <= 0)>Select activity</option>
              @foreach ($calendarEntries as $entry)
                @php
                  $entryProposalStatus = strtoupper((string) ($entry->proposal->status ?? $entry->proposal->proposal_status ?? ''));
                  $entryUnavailable = $entry->proposal && ! in_array($entryProposalStatus, ['DRAFT', 'REVISION'], true);
                @endphp
                <option
                  value="{{ $entry->id }}"
                  @disabled($entryUnavailable)
                  @selected($selectedCalendarEntryId === (int) $entry->id)
                  data-title="{{ $entry->activity_name }}"
                  data-date="{{ optional($entry->activity_date)->toDateString() }}"
                  data-venue="{{ $entry->venue }}"
                  data-sdg="{{ $entry->target_sdg }}"
                  data-budget="{{ $entry->estimated_budget }}"
                >
                  {{ optional($entry->activity_date)->format('M j, Y') ?? 'No date' }} — {{ $entry->activity_name }}{{ $entryUnavailable ? ' (Already has submitted proposal)' : '' }}
                </option>
              @endforeach
            </x-forms.select>
            <x-forms.helper>Only activities from your submitted Activity Calendar are listed here. Activities with already submitted proposals are disabled.</x-forms.helper>
            @error('activity_calendar_entry_id')
              <x-forms.error>{{ $message }}</x-forms.error>
            @enderror
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header title="Basic Information" subtitle="Provide the summary details for this activity request." content-padding="px-6" header-class="!pb-3 pt-3" />
        <div class="px-6 py-5">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-x-5">
            <div>
              <x-forms.label for="rso_name" required class="{{ $errors->has('rso_name') ? '!text-rose-700' : '' }}">Name of RSO</x-forms.label>
              <x-forms.input
                id="rso_name"
                name="rso_name"
                type="text"
                :value="$organization?->organization_name ?? ''"
                readonly
                required
                class="{{ $errors->has('rso_name') ? '!border-rose-400 !ring-rose-500/20 focus:!border-rose-500 focus:!ring-rose-500/20' : '' }} bg-slate-100 text-slate-700 cursor-not-allowed"
              />
              <x-forms.helper class="mt-1.5!">This value is auto-filled from your linked officer organization.</x-forms.helper>
              @error('rso_name')
                <x-forms.error>{{ $message }}</x-forms.error>
              @enderror
            </div>
            <div>
              <x-forms.label for="activity_title" required class="{{ $errors->has('activity_title') ? '!text-rose-700' : '' }}">Title of Activity</x-forms.label>
              <x-forms.input id="activity_title" name="activity_title" type="text" :value="old('activity_title', $requestForm?->activity_title)" required class="{{ $errors->has('activity_title') ? '!border-rose-400 !ring-rose-500/20 focus:!border-rose-500 focus:!ring-rose-500/20' : '' }}" />
              @error('activity_title')
                <x-forms.error>{{ $message }}</x-forms.error>
              @enderror
            </div>
            <div class="md:col-span-2">
              <x-forms.label for="partner_entities">Partner Organization(s) / School(s) / RSO</x-forms.label>
              <x-forms.input id="partner_entities" name="partner_entities" type="text" :value="old('partner_entities', $requestForm?->partner_entities)" />
            </div>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header title="Nature and Type of Activity" subtitle="Select all applicable options." content-padding="px-6" header-class="!pb-3 pt-3" />
        <div class="px-6 py-5 space-y-4">
          <div>
            <p class="text-sm font-semibold {{ $errors->has('nature_of_activity') ? 'text-rose-700' : 'text-slate-900' }}">Nature of Activity <span class="text-rose-600">*</span></p>
            <div id="nature-options-wrap" class="mt-2 grid grid-cols-1 gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 {{ $errors->has('nature_of_activity') ? 'border-rose-300 bg-rose-50/40' : '' }} sm:grid-cols-2">
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
            @error('nature_of_activity')
              <x-forms.error>Please select at least one option.</x-forms.error>
            @enderror
            <p id="nature-required-reminder" class="mt-2 hidden text-xs font-medium text-amber-700">Please fill out this field.</p>
            <div id="nature-other-wrap" class="mt-2 {{ in_array('others', $selectedNature, true) ? '' : 'hidden' }}">
              <x-forms.label for="nature_other" class="{{ $errors->has('nature_other') ? '!text-rose-700' : '' }}">Nature of Activity (Others)</x-forms.label>
              <x-forms.input
                id="nature_other"
                name="nature_other"
                type="text"
                :value="old('nature_other', $requestForm?->nature_other)"
                :disabled="! in_array('others', $selectedNature, true)"
                class="{{ $errors->has('nature_other') ? '!border-rose-400 !ring-rose-500/20 focus:!border-rose-500 focus:!ring-rose-500/20' : '' }}"
              />
              @error('nature_other')
                <x-forms.error>{{ $message }}</x-forms.error>
              @enderror
            </div>
          </div>

          <div>
            <p class="text-sm font-semibold {{ $errors->has('activity_types') ? 'text-rose-700' : 'text-slate-900' }}">Type of Activity <span class="text-rose-600">*</span></p>
            <div id="type-options-wrap" class="mt-2 grid grid-cols-1 gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 {{ $errors->has('activity_types') ? 'border-rose-300 bg-rose-50/40' : '' }} sm:grid-cols-2">
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
            @error('activity_types')
              <x-forms.error>Please select at least one option.</x-forms.error>
            @enderror
            <p id="type-required-reminder" class="mt-2 hidden text-xs font-medium text-amber-700">Please fill out this field.</p>
            <div id="activity-type-other-wrap" class="mt-2 {{ in_array('others', $selectedTypes, true) ? '' : 'hidden' }}">
              <x-forms.label for="activity_type_other" class="{{ $errors->has('activity_type_other') ? '!text-rose-700' : '' }}">Type of Activity (Others)</x-forms.label>
              <x-forms.input
                id="activity_type_other"
                name="activity_type_other"
                type="text"
                :value="old('activity_type_other', $requestForm?->activity_type_other)"
                :disabled="! in_array('others', $selectedTypes, true)"
                class="{{ $errors->has('activity_type_other') ? '!border-rose-400 !ring-rose-500/20 focus:!border-rose-500 focus:!ring-rose-500/20' : '' }}"
              />
              @error('activity_type_other')
                <x-forms.error>{{ $message }}</x-forms.error>
              @enderror
            </div>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header title="Activity Details" subtitle="Provide key planning details for this request." content-padding="px-6" header-class="!pb-3 pt-3" />
        <div class="px-6 py-5">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-x-5">
            <div>
              <x-forms.label for="target-sdg-trigger" required class="{{ $errors->has('target_sdg') ? '!text-rose-700' : '' }}">Target SDG</x-forms.label>
              <div id="target-sdg-dropdown" class="relative mt-2">
                <button
                  type="button"
                  id="target-sdg-trigger"
                  class="flex w-full items-center justify-between rounded-xl border bg-white px-4 py-3 text-left text-sm text-slate-900 shadow-sm transition hover:border-slate-400 focus:outline-none focus:ring-4 {{ $errors->has('target_sdg') ? 'border-rose-400 focus:ring-rose-500/20' : 'border-slate-300 focus:ring-sky-500/15' }}"
                  aria-haspopup="true"
                  aria-expanded="false"
                >
                  <span id="target-sdg-trigger-text" class="{{ count($selectedTargetSdgs) > 0 ? 'text-slate-900' : 'text-slate-500' }}">
                    {{ count($selectedTargetSdgs) > 0 ? implode(', ', $selectedTargetSdgs) : 'Select one or more SDGs' }}
                  </span>
                  <svg class="h-5 w-5 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                  </svg>
                </button>
                <div
                  id="target-sdg-menu"
                  class="absolute left-0 right-0 z-20 mt-2 hidden max-h-64 overflow-y-auto rounded-xl border {{ $errors->has('target_sdg') ? 'border-rose-300' : 'border-slate-200' }} bg-white p-2 shadow-lg"
                  role="menu"
                >
                  @foreach ($sdgOptions as $index => $sdgOption)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                      <input
                        type="checkbox"
                        id="target_sdg_{{ $index + 1 }}"
                        name="target_sdg[]"
                        value="{{ $sdgOption }}"
                        class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500/30"
                        @checked(in_array($sdgOption, $selectedTargetSdgs, true))
                      />
                      <span>{{ $sdgOption }}</span>
                    </label>
                  @endforeach
                </div>
              </div>
              <x-forms.input id="target_sdg_required_proxy" type="text" class="sr-only" tabindex="-1" aria-hidden="true" />
              <x-forms.helper id="target-sdg-helper-manual" class="mt-1.5! {{ $proposalSource === 'calendar' ? 'hidden' : '' }}">Click the dropdown and check all SDGs that apply.</x-forms.helper>
              <x-forms.helper id="target-sdg-helper-locked" class="mt-1.5! {{ $proposalSource === 'calendar' ? '' : 'hidden' }}">Target SDGs are automatically filled from the selected activity calendar entry.</x-forms.helper>
              @error('target_sdg')
                <x-forms.error>Please select at least one option.</x-forms.error>
              @enderror
              <p id="sdg-required-reminder" class="mt-2 hidden text-xs font-medium text-amber-700">Please fill out this field.</p>
              <div id="target-sdg-selected-wrap" class="mt-2 {{ count($selectedTargetSdgs) > 0 ? '' : 'hidden' }}">
                <p class="text-xs font-medium text-slate-700">Selected SDGs</p>
                <div id="target-sdg-selected-list" class="mt-2 flex flex-wrap gap-2">
                  @foreach ($selectedTargetSdgs as $selectedSdg)
                    <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">{{ $selectedSdg }}</span>
                  @endforeach
                </div>
              </div>
            </div>
            <div>
              <x-forms.label for="proposed_budget" required class="{{ $errors->has('proposed_budget') ? '!text-rose-700' : '' }}">Proposed Budget</x-forms.label>
              <x-forms.input id="proposed_budget" name="proposed_budget" type="number" step="0.01" min="0" :value="old('proposed_budget', $requestForm?->proposed_budget)" required class="{{ $errors->has('proposed_budget') ? '!border-rose-400 !ring-rose-500/20 focus:!border-rose-500 focus:!ring-rose-500/20' : '' }}" />
              @error('proposed_budget')
                <x-forms.error>{{ $message }}</x-forms.error>
              @enderror
            </div>
            <div>
              <x-forms.label for="budget_source" required class="{{ $errors->has('budget_source') ? '!text-rose-700' : '' }}">Budget Source</x-forms.label>
              <x-forms.select id="budget_source" name="budget_source" required class="{{ $errors->has('budget_source') ? '!border-rose-400 !ring-rose-500/20 focus:!border-rose-500 focus:!ring-rose-500/20' : '' }}">
                <option value="" disabled @selected(old('budget_source') === null || old('budget_source') === '')>Select source</option>
                <option value="RSO Fund" @selected(old('budget_source', $requestForm?->budget_source) === 'RSO Fund')>RSO Fund</option>
                <option value="RSO Savings" @selected(old('budget_source', $requestForm?->budget_source) === 'RSO Savings')>RSO Savings</option>
                <option value="External" @selected(old('budget_source', $requestForm?->budget_source) === 'External')>External</option>
              </x-forms.select>
              @error('budget_source')
                <x-forms.error>{{ $message }}</x-forms.error>
              @enderror
            </div>
            <div>
              <x-forms.label for="activity_date" required class="{{ $errors->has('activity_date') ? '!text-rose-700' : '' }}">Date of Activity</x-forms.label>
              <x-forms.input id="activity_date" name="activity_date" type="date" :value="old('activity_date', optional($requestForm?->activity_date)->toDateString())" required class="{{ $errors->has('activity_date') ? '!border-rose-400 !ring-rose-500/20 focus:!border-rose-500 focus:!ring-rose-500/20' : '' }}" />
              @error('activity_date')
                <x-forms.error>{{ $message }}</x-forms.error>
              @enderror
            </div>
            <div class="md:col-span-2">
              <x-forms.label for="venue" required class="{{ $errors->has('venue') ? '!text-rose-700' : '' }}">Venue</x-forms.label>
              <x-forms.input id="venue" name="venue" type="text" :value="old('venue', $requestForm?->venue)" required class="{{ $errors->has('venue') ? '!border-rose-400 !ring-rose-500/20 focus:!border-rose-500 focus:!ring-rose-500/20' : '' }}" />
              @error('venue')
                <x-forms.error>{{ $message }}</x-forms.error>
              @enderror
            </div>
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <x-ui.card-section-header title="Required Attachments and Checklist" subtitle="All required items must be completed before you can continue to Step 2." content-padding="px-6" header-class="!pb-3 pt-3" />
        <div class="px-6 py-5 space-y-4">
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 sm:p-4">
            <p class="text-sm font-semibold text-slate-900">Request Letter <span class="text-rose-600">*</span></p>
            <p class="mt-1 text-xs text-slate-600">The request letter must include rationale, objectives, and program.</p>
            <div class="mt-2 space-y-2 rounded-lg border border-slate-200 bg-slate-100/70 p-2.5">
              <x-forms.choice id="request_letter_has_rationale" name="request_letter_has_rationale" type="checkbox" value="1" :checked="old('request_letter_has_rationale', $requestForm?->request_letter_has_rationale ? '1' : '') == '1'">
                Includes Rationale
              </x-forms.choice>
              <x-forms.choice id="request_letter_has_objectives" name="request_letter_has_objectives" type="checkbox" value="1" :checked="old('request_letter_has_objectives', $requestForm?->request_letter_has_objectives ? '1' : '') == '1'">
                Includes Objectives
              </x-forms.choice>
              <x-forms.choice id="request_letter_has_program" name="request_letter_has_program" type="checkbox" value="1" :checked="old('request_letter_has_program', $requestForm?->request_letter_has_program ? '1' : '') == '1'">
                Includes Program
              </x-forms.choice>
            </div>
            <div class="mt-2">
              @if ($requestLetterLink)
                <div class="mb-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                  <p class="font-semibold">Uploaded file: {{ $requestLetterLink['name'] }}</p>
                  <a href="{{ $requestLetterLink['url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex text-[#003E9F] underline">View uploaded file</a>
                </div>
              @endif
              <x-forms.label for="request_letter" :required="! $requestLetterLink" class="{{ $errors->has('request_letter') ? '!text-rose-700' : '' }}">
                {{ $requestLetterLink ? 'Replace Request Letter (optional)' : 'Upload Request Letter' }}
              </x-forms.label>
              <x-forms.input id="request_letter" name="request_letter" type="file" :required="! $requestLetterLink" class="{{ $errors->has('request_letter') ? '!border-rose-400 !ring-rose-500/20 focus:!border-rose-500 focus:!ring-rose-500/20' : '' }}" />
              @error('request_letter')
                <x-forms.error>{{ $message }}</x-forms.error>
              @enderror
            </div>
          </div>

          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 sm:p-4">
            @if ($speakerResumeLink)
              <div class="mb-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                <p class="font-semibold">Uploaded file: {{ $speakerResumeLink['name'] }}</p>
                <a href="{{ $speakerResumeLink['url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex text-[#003E9F] underline">View uploaded file</a>
              </div>
            @endif
            <x-forms.label for="speaker_resume" class="{{ $errors->has('speaker_resume') ? '!text-rose-700' : '' }}">
              {{ $speakerResumeLink ? 'Replace Resume of Speaker (optional)' : 'Resume of Speaker (required if Seminar / Workshop is selected)' }}
            </x-forms.label>
            <x-forms.input id="speaker_resume" name="speaker_resume" type="file" class="{{ $errors->has('speaker_resume') ? '!border-rose-400 !ring-rose-500/20 focus:!border-rose-500 focus:!ring-rose-500/20' : '' }}" />
            @error('speaker_resume')
              <x-forms.error>{{ $message }}</x-forms.error>
            @enderror
          </div>

          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 sm:p-4">
            @if ($postSurveyLink)
              <div class="mb-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                <p class="font-semibold">Uploaded file: {{ $postSurveyLink['name'] }}</p>
                <a href="{{ $postSurveyLink['url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex text-[#003E9F] underline">View uploaded file</a>
              </div>
            @endif
            <x-forms.label for="post_survey_form" :required="! $postSurveyLink" class="{{ $errors->has('post_survey_form') ? '!text-rose-700' : '' }}">
              {{ $postSurveyLink ? 'Replace Sample Post-Survey Form (optional)' : 'Sample Post-Survey Form' }}
            </x-forms.label>
            <x-forms.input id="post_survey_form" name="post_survey_form" type="file" :required="! $postSurveyLink" class="{{ $errors->has('post_survey_form') ? '!border-rose-400 !ring-rose-500/20 focus:!border-rose-500 focus:!ring-rose-500/20' : '' }}" />
            @error('post_survey_form')
              <x-forms.error>{{ $message }}</x-forms.error>
            @enderror
          </div>
        </div>
      </x-ui.card>

      <x-ui.card padding="p-0">
        <div class="px-6 py-4 sm:py-5">
          <div class="flex justify-end">
            <x-ui.button type="submit" class="w-full sm:w-auto">Save Step 1 and Continue to Step 2</x-ui.button>
          </div>
        </div>
      </x-ui.card>
    </fieldset>
  </form>
  @endif
</div>
<style>
  .proposal-request-page .grid > * {
    min-width: 0;
  }

  .proposal-request-page input[type="file"] {
    max-width: 100%;
  }
</style>
<script>
  (() => {
    const proposalSourceRadios = Array.from(document.querySelectorAll('input[name="proposal_source"]'));
    const proposalForm = document.getElementById('activity-proposal-request-form');
    const calendarLinkWrap = document.getElementById('calendar-link-wrap');
    const proposalSourceCards = Array.from(document.querySelectorAll('[data-proposal-option]'));
    const calendarSelect = document.getElementById('activity_calendar_entry_id');
    const titleInput = document.getElementById('activity_title');
    const dateInput = document.getElementById('activity_date');
    const venueInput = document.getElementById('venue');
    const sdgDropdown = document.getElementById('target-sdg-dropdown');
    const sdgTrigger = document.getElementById('target-sdg-trigger');
    const sdgTriggerText = document.getElementById('target-sdg-trigger-text');
    const sdgMenu = document.getElementById('target-sdg-menu');
    const sdgCheckboxes = Array.from(document.querySelectorAll('input[name="target_sdg[]"]'));
    const sdgRequiredProxy = document.getElementById('target_sdg_required_proxy');
    const selectedSdgWrap = document.getElementById('target-sdg-selected-wrap');
    const selectedSdgList = document.getElementById('target-sdg-selected-list');
    const natureOptionsWrap = document.getElementById('nature-options-wrap');
    const typeOptionsWrap = document.getElementById('type-options-wrap');
    const natureRequiredReminder = document.getElementById('nature-required-reminder');
    const typeRequiredReminder = document.getElementById('type-required-reminder');
    const sdgRequiredReminder = document.getElementById('sdg-required-reminder');
    const sdgHelperManual = document.getElementById('target-sdg-helper-manual');
    const sdgHelperLocked = document.getElementById('target-sdg-helper-locked');
    const natureCheckboxes = Array.from(document.querySelectorAll('input[name="nature_of_activity[]"]'));
    const typeCheckboxes = Array.from(document.querySelectorAll('input[name="activity_types[]"]'));
    const budgetInput = document.getElementById('proposed_budget');
    let hasAttemptedSubmit = false;

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
      if (sdgTrigger) {
        sdgTrigger.disabled = isCalendar;
        sdgTrigger.classList.toggle('bg-slate-100', isCalendar);
        sdgTrigger.classList.toggle('text-slate-700', isCalendar);
        sdgTrigger.classList.toggle('cursor-not-allowed', isCalendar);
      }
      if (sdgCheckboxes.length > 0) {
        sdgCheckboxes.forEach((checkbox) => {
          checkbox.disabled = isCalendar;
        });
      }
      if (sdgHelperManual) {
        sdgHelperManual.classList.toggle('hidden', isCalendar);
      }
      if (sdgHelperLocked) {
        sdgHelperLocked.classList.toggle('hidden', !isCalendar);
      }

      proposalSourceCards.forEach((card) => {
        const isActive = card.getAttribute('data-proposal-option') === source;
        card.classList.toggle('border-2', isActive);
        card.classList.toggle('border-[#003E9F]', isActive);
        card.classList.toggle('bg-[#003E9F]/10', isActive);
        card.classList.toggle('shadow-sm', isActive);
        card.classList.toggle('ring-1', isActive);
        card.classList.toggle('ring-[#003E9F]/20', isActive);
        card.classList.toggle('border-slate-200', !isActive);
        card.classList.toggle('bg-white', !isActive);
        card.classList.toggle('hover:border-slate-300', !isActive);

        const title = card.querySelector('[data-option-title]');
        if (title) {
          title.classList.toggle('text-[#003E9F]', isActive);
          title.classList.toggle('text-slate-900', !isActive);
        }

        const helper = card.querySelector('[data-option-helper]');
        if (helper) {
          helper.classList.toggle('text-[#003E9F]/80', isActive);
          helper.classList.toggle('text-slate-600', !isActive);
        }

        const check = card.querySelector('[data-option-check]');
        if (check) {
          check.classList.toggle('border-[#003E9F]', isActive);
          check.classList.toggle('bg-[#003E9F]', isActive);
          check.classList.toggle('text-white', isActive);
          check.classList.toggle('border-slate-300', !isActive);
          check.classList.toggle('bg-white', !isActive);
          check.classList.toggle('text-transparent', !isActive);
        }
      });
    };

    const normalizeSdgValues = (value) => {
      if (!value || typeof value !== 'string') return [];
      return value
        .split(',')
        .map((item) => item.trim())
        .filter((item) => item.length > 0);
    };

    const syncSelectedSdgBadges = () => {
      if (!selectedSdgList || !selectedSdgWrap) return;
      const selected = sdgCheckboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
      selectedSdgWrap.classList.toggle('hidden', selected.length === 0);
      selectedSdgList.innerHTML = selected
        .map((sdg) => `<span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">${sdg}</span>`)
        .join('');
      if (sdgTriggerText) {
        sdgTriggerText.textContent = selected.length > 0 ? selected.join(', ') : 'Select one or more SDGs';
        sdgTriggerText.classList.toggle('text-slate-500', selected.length === 0);
        sdgTriggerText.classList.toggle('text-slate-900', selected.length > 0);
      }
      if (sdgRequiredProxy) {
        sdgRequiredProxy.value = selected.length > 0 ? 'selected' : '';
      }
      if (hasAttemptedSubmit && sdgRequiredReminder) {
        sdgRequiredReminder.classList.toggle('hidden', selected.length > 0);
      }
      if (hasAttemptedSubmit && sdgTrigger) {
        sdgTrigger.classList.toggle('border-rose-400', selected.length === 0);
      }
    };

    const setSelectedSdgs = (sdgText) => {
      const selectedValues = normalizeSdgValues(sdgText);
      const selectedSet = new Set(selectedValues);
      sdgCheckboxes.forEach((checkbox) => {
        checkbox.checked = selectedSet.has(checkbox.value);
      });
      syncSelectedSdgBadges();
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
      setSelectedSdgs(sdg);
      if (budgetInput) budgetInput.value = budget;
    };

    proposalSourceRadios.forEach((radio) => {
      radio.addEventListener('change', () => {
        const isCalendar = selectedProposalSource() === 'calendar';
        if (!isCalendar) {
          if (titleInput) titleInput.value = '';
          if (dateInput) dateInput.value = '';
          if (venueInput) venueInput.value = '';
          if (budgetInput) budgetInput.value = '';
          setSelectedSdgs('');
          if (sdgMenu) {
            sdgMenu.classList.add('hidden');
          }
          if (sdgTrigger) {
            sdgTrigger.setAttribute('aria-expanded', 'false');
          }
        }
        syncProposalSourceUi();
      });
    });
    if (calendarSelect) {
      calendarSelect.addEventListener('change', autofillFromCalendarSelection);
    }
    if (sdgTrigger && sdgMenu) {
      sdgTrigger.addEventListener('click', () => {
        const isOpen = !sdgMenu.classList.contains('hidden');
        sdgMenu.classList.toggle('hidden', isOpen);
        sdgTrigger.setAttribute('aria-expanded', String(!isOpen));
      });
      document.addEventListener('click', (event) => {
        if (!sdgDropdown) return;
        if (sdgDropdown.contains(event.target)) return;
        sdgMenu.classList.add('hidden');
        sdgTrigger.setAttribute('aria-expanded', 'false');
      });
    }
    if (sdgCheckboxes.length > 0) {
      sdgCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', syncSelectedSdgBadges);
      });
      syncSelectedSdgBadges();
    }

    syncProposalSourceUi();
    if (calendarSelect && calendarSelect.value) {
      autofillFromCalendarSelection();
    }

    const setupSingleSelectCheckboxGroup = (selector, othersCheckboxId, othersInputId, othersWrapId) => {
      const checkboxes = Array.from(document.querySelectorAll(selector));
      if (checkboxes.length === 0) return;
      const othersCheckbox = document.getElementById(othersCheckboxId);
      const othersInput = document.getElementById(othersInputId);
      const othersWrap = document.getElementById(othersWrapId);

      const syncOthersInput = () => {
        if (!othersInput || !othersCheckbox) return;
        const enabled = othersCheckbox.checked;
        if (othersWrap) {
          othersWrap.classList.toggle('hidden', !enabled);
        }
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

    setupSingleSelectCheckboxGroup('input[name="nature_of_activity[]"]', 'nature_others', 'nature_other', 'nature-other-wrap');
    setupSingleSelectCheckboxGroup('input[name="activity_types[]"]', 'activity_type_others', 'activity_type_other', 'activity-type-other-wrap');

    const showGroupReminderState = (wrapEl, reminderEl, hasSelection) => {
      if (reminderEl) {
        reminderEl.classList.toggle('hidden', hasSelection);
      }
      if (wrapEl) {
        wrapEl.classList.toggle('border', !hasSelection);
        wrapEl.classList.toggle('border-rose-300', !hasSelection);
        wrapEl.classList.toggle('bg-rose-50/40', !hasSelection);
        wrapEl.classList.toggle('p-3', !hasSelection);
      }
    };

    const scrollToInvalidTarget = (target) => {
      if (!target || typeof target.scrollIntoView !== 'function') return;
      target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
    };

    const firstInvalidByDomOrder = (a, b) => {
      if (!a) return b || null;
      if (!b) return a || null;
      const pos = a.compareDocumentPosition(b);
      if (pos & Node.DOCUMENT_POSITION_FOLLOWING) return a;
      if (pos & Node.DOCUMENT_POSITION_PRECEDING) return b;
      return a;
    };

    const getFirstCustomInvalidTarget = () => {
      const hasNature = natureCheckboxes.some((checkbox) => checkbox.checked);
      if (!hasNature) return natureOptionsWrap || natureRequiredReminder;

      const hasType = typeCheckboxes.some((checkbox) => checkbox.checked);
      if (!hasType) return typeOptionsWrap || typeRequiredReminder;

      const hasSdg = sdgCheckboxes.some((checkbox) => checkbox.checked);
      if (!hasSdg) return sdgTrigger || sdgRequiredReminder;

      return null;
    };

    const validateCustomRequiredSections = () => {
      const hasNature = natureCheckboxes.some((checkbox) => checkbox.checked);
      const hasType = typeCheckboxes.some((checkbox) => checkbox.checked);
      const hasSdg = sdgCheckboxes.some((checkbox) => checkbox.checked);

      showGroupReminderState(natureOptionsWrap, natureRequiredReminder, hasNature);
      showGroupReminderState(typeOptionsWrap, typeRequiredReminder, hasType);

      if (sdgRequiredReminder) {
        sdgRequiredReminder.classList.toggle('hidden', hasSdg);
      }
      if (sdgTrigger) {
        sdgTrigger.classList.toggle('border-rose-400', !hasSdg);
        sdgTrigger.classList.toggle('focus:ring-rose-500/20', !hasSdg);
        sdgTrigger.classList.toggle('border-slate-300', hasSdg);
        sdgTrigger.classList.toggle('focus:ring-sky-500/15', hasSdg);
      }

      return hasNature && hasType && hasSdg;
    };

    if (natureCheckboxes.length > 0) {
      natureCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
          if (!hasAttemptedSubmit) return;
          const hasNature = natureCheckboxes.some((item) => item.checked);
          showGroupReminderState(natureOptionsWrap, natureRequiredReminder, hasNature);
        });
      });
    }
    if (typeCheckboxes.length > 0) {
      typeCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
          if (!hasAttemptedSubmit) return;
          const hasType = typeCheckboxes.some((item) => item.checked);
          showGroupReminderState(typeOptionsWrap, typeRequiredReminder, hasType);
        });
      });
    }

    if (proposalForm) {
      proposalForm.addEventListener('submit', (event) => {
        hasAttemptedSubmit = true;
        const customSectionsValid = validateCustomRequiredSections();
        const nativeValid = proposalForm.checkValidity();
        const nativeInvalidTarget = nativeValid ? null : proposalForm.querySelector(':invalid');
        const customInvalidTarget = customSectionsValid ? null : getFirstCustomInvalidTarget();
        const firstInvalidTarget = firstInvalidByDomOrder(customInvalidTarget, nativeInvalidTarget);

        if (!customSectionsValid || !nativeValid) {
          event.preventDefault();
          scrollToInvalidTarget(firstInvalidTarget);
          if (nativeInvalidTarget && firstInvalidTarget === nativeInvalidTarget && typeof nativeInvalidTarget.focus === 'function') {
            nativeInvalidTarget.focus({ preventScroll: true });
            if (typeof nativeInvalidTarget.reportValidity === 'function') {
              nativeInvalidTarget.reportValidity();
            } else {
              proposalForm.reportValidity();
            }
          } else if (firstInvalidTarget && typeof firstInvalidTarget.focus === 'function') {
            firstInvalidTarget.focus({ preventScroll: true });
          }
        }
      });
    }
  })();
</script>
@endsection
