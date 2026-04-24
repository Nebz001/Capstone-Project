@extends($layout ?? 'layouts.organization-portal')

@section('title', ($pageTitle ?? 'Activity Calendar Submission').' — NU Lipa SDAO')

@section('content')

@php
  $officerValidationPending = $officerValidationPending ?? false;
  $calendarSubmittedLocked = $calendarSubmittedLocked ?? false;
  $blockedMessage = $blockedMessage ?? null;
  $isBlocked = $isBlocked ?? ($officerValidationPending || $calendarSubmittedLocked || (is_string($blockedMessage) && trim($blockedMessage) !== ''));
  $blockedReason = $blockedReason ?? null;
  if (! $blockedReason) {
    $blockedReason = $officerValidationPending
      ? 'Your student officer account is pending SDAO validation. You cannot submit activity calendars until validation is complete.'
      : ($calendarSubmittedLocked
        ? 'This activity calendar has already been submitted and can no longer be edited.'
        : (is_string($blockedMessage) ? $blockedMessage : null));
  }
  $activityCalendarFormBlocked = $isBlocked;
  $calLock = $calendarSubmittedLocked && ($latestCalendar ?? null);
  $academicYearVal = $calLock
      ? (string) ($latestCalendar->academic_year ?? '')
      : \App\Models\SystemSetting::activeAcademicYear();
  $termVal = $calLock
      ? (string) ($latestCalendar->semester ?? '')
      : \App\Models\SystemSetting::activeSemester();
  $termLabelMap = [
      'term_1' => 'Term 1',
      'term_2' => 'Term 2',
      'term_3' => 'Term 3',
  ];
  $termLabel = $termLabelMap[$termVal] ?? $termVal;
  $orgNameVal = $calLock
      ? (string) ($latestCalendar->submitted_organization_name ?? '')
      : (string) (($organization ?? null)?->organization_name ?? '');
  $dateSubmittedVal = $calLock && $latestCalendar->submission_date
      ? $latestCalendar->submission_date->format('Y-m-d')
      : now()->toDateString();
  $isAdminSubmission = ($submissionContext ?? '') === 'admin';
  $showPageIntro = $showPageIntro ?? (! $isAdminSubmission && (($layout ?? 'layouts.organization-portal') !== 'layouts.admin'));
@endphp

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">

  @if ($showPageIntro)
    <header class="mb-8">
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
        {{ $pageHeading ?? 'Activity Calendar Submission' }}
      </h1>
      <p class="mt-1 text-sm text-slate-500">
        {{ $pageSubheading ?? 'Submit your organization\'s term activity calendar for review.' }}
      </p>
    </header>
  @endif

  @if ($isAdminSubmission)
    <x-ui.card padding="p-0" class="mb-6">
      <x-ui.card-section-header
        title="Optional: load existing calendar status"
        subtitle="Enter the exact registered organization name to preview lock status and past rows before filing a new calendar."
        content-padding="px-6"
      />
      <div class="border-t border-slate-100 px-6 py-5">
        <form method="GET" action="{{ route('admin.submissions.activity-calendar') }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
          <div class="min-w-[220px] flex-1">
            <x-forms.label for="lookup_organization_name">Registered organization name</x-forms.label>
            <x-forms.input
              id="lookup_organization_name"
              name="lookup_organization_name"
              type="text"
              :value="$lookupOrganizationName ?? ''"
              placeholder="e.g., Computer Society"
            />
          </div>
          <x-ui.button type="submit" class="w-full sm:w-auto">Load calendar status</x-ui.button>
        </form>
        @if (! empty($lookupOrganizationNameError))
          <p class="mt-3 text-sm font-medium text-red-700">{{ $lookupOrganizationNameError }}</p>
        @endif
      </div>
    </x-ui.card>
  @endif

  @if (session('error'))
    <x-feedback.blocked-message variant="error" class="mb-6" :message="session('error')" />
  @endif

  @if ($calendarSubmittedLocked && ! $isBlocked)
    <x-feedback.blocked-message
      class="mb-6"
      message="This activity calendar has already been submitted and can no longer be edited."
    />
  @endif

  @if (session('activity_calendar_submitted'))
    <script id="activity-calendar-submitted-flash" type="application/json">
      @json([
        'activitySubmissionUrl' => $isAdminSubmission ? route('admin.dashboard') : route('organizations.activity-submission'),
        'proposalSubmissionUrl' => $isAdminSubmission ? route('admin.submissions.activity-proposal') : route('organizations.activity-proposal-request'),
      ])
    </script>
  @endif

  @if ($isBlocked && $blockedReason)
    <x-feedback.blocked-message
      variant="error"
      class="mb-6"
      :message="$blockedReason"
    />
  @endif

  @if (($organization !== null || $isAdminSubmission) && ! $isBlocked)
  <form
    id="activity-calendar-form"
    method="POST"
    action="{{ route($calendarStoreRoute ?? 'organizations.activity-calendar-submission.store') }}"
    class="space-y-4"
    novalidate
    data-officer-validation-pending="{{ $officerValidationPending ? 'true' : 'false' }}"
    data-activity-calendar-form-blocked="{{ $activityCalendarFormBlocked ? 'true' : 'false' }}"
  >
    @csrf

    <fieldset class="min-w-0 space-y-4 border-0 p-0 m-0">

    <x-ui.card padding="p-0">
      <x-ui.card-section-header
        title="Organization Information"
        subtitle="Provide the details for this submission."
        helper='Fields marked with <span class="text-red-600">*</span> are required.'
        :helper-html="true"
        content-padding="px-6"
        header-class="!pb-3 pt-3" />

      <div class="px-6 py-5">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-x-5">
          <div>
            <x-forms.label for="academic_year" required>Academic Year</x-forms.label>
            <x-forms.input
              id="academic_year"
              name="academic_year"
              type="text"
              placeholder="2025-2026"
              :value="$academicYearVal"
              readonly
              class="bg-slate-100 text-slate-700 cursor-not-allowed"
              required />
          </div>

          <div>
            <x-forms.label for="term" required>Term</x-forms.label>
            <x-forms.input
              id="term"
              name="term"
              type="text"
              :value="$termLabel"
              readonly
              class="bg-slate-100 text-slate-700 cursor-not-allowed"
              required />
          </div>

          <div>
            <x-forms.label for="organization_name" required>RSO Name / Organization Name</x-forms.label>
            <x-forms.input
              id="organization_name"
              name="organization_name"
              type="text"
              placeholder="e.g., Computer Society"
              :value="$orgNameVal"
              readonly
              class="bg-slate-100 text-slate-700 cursor-not-allowed"
              required />
          </div>

          <div>
            <x-forms.label for="date_submitted" required>Date Submitted</x-forms.label>
            <x-forms.input
              id="date_submitted"
              name="date_submitted"
              type="date"
              :value="$dateSubmittedVal"
              readonly
              class="bg-slate-100 text-slate-700 cursor-not-allowed"
              required />
          </div>
        </div>
      </div>
    </x-ui.card>

    <x-ui.card padding="p-0">
      <x-ui.card-section-header
        title="Activity Calendar"
        subtitle="Status and Date Received are for admin use."
        content-padding="px-6"
        header-class="!pb-3 pt-3" />

      <div class="px-6 py-5">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 sm:p-4">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-baseline sm:justify-between">
            <h3 id="activity-entry-title" class="text-sm font-semibold text-slate-900">Enter One Activity</h3>
            <p class="text-xs text-slate-600">Add activities one at a time; they&rsquo;ll appear below.</p>
          </div>

          <div class="mt-3 grid grid-cols-1 gap-4">
            <div class="rounded-xl border border-slate-200 bg-slate-100/70 p-3">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
              <div class="md:col-span-2">
                <x-forms.label for="activity_date" required>Date</x-forms.label>
                <x-forms.input id="activity_date" type="date" required />
              </div>

              <div class="md:col-span-2">
                <x-forms.label for="activity-sdg-trigger" required>SDG</x-forms.label>
                <div id="activity-sdg-dropdown" class="relative mt-2">
                  <button
                    type="button"
                    id="activity-sdg-trigger"
                    class="flex w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-4 py-3 text-left text-sm text-slate-900 shadow-sm transition hover:border-slate-400 focus:outline-none focus:ring-4 focus:ring-sky-500/15"
                    aria-haspopup="true"
                    aria-expanded="false"
                  >
                    <span id="activity-sdg-trigger-text" class="text-slate-500">Select one or more SDGs</span>
                    <svg class="h-5 w-5 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                  </button>
                  <div
                    id="activity-sdg-menu"
                    class="absolute left-0 right-0 z-20 mt-2 hidden max-h-52 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-lg"
                    role="menu"
                  >
                    @foreach (range(1, 17) as $sdgNum)
                      <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                        <input type="checkbox" class="activity-sdg-option h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500/30" value="SDG {{ $sdgNum }}" />
                        <span>SDG {{ $sdgNum }}</span>
                      </label>
                    @endforeach
                  </div>
                </div>
                <div id="activity-sdg-selected-wrap" class="mt-2 hidden">
                  <div id="activity-sdg-selected-list" class="flex flex-wrap gap-2"></div>
                </div>
                <x-forms.helper class="!mt-1.5">Select one or more SDGs for this activity.</x-forms.helper>
                <p id="activity-sdg-required-reminder" class="mt-1 hidden text-xs font-medium text-amber-700">Please select at least one SDG.</p>
              </div>

              <div class="md:col-span-2">
                <x-forms.label for="activity_budget" required>Budget</x-forms.label>
                <x-forms.input id="activity_budget" type="text" required placeholder="e.g., P1,500 or No Expenses" />
              </div>
            </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-100/70 p-3">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
              <div class="md:col-span-4">
                <x-forms.label for="activity_name" required>Activity Name</x-forms.label>
                <x-forms.input id="activity_name" type="text" required placeholder="e.g., Orientation Seminar" />
              </div>
              <div class="md:col-span-2">
                <x-forms.label for="activity_venue" required>Venue</x-forms.label>
                <x-forms.input id="activity_venue" type="text" required placeholder="e.g., University Auditorium" />
              </div>
            </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-100/70 p-3">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
              <div class="md:col-span-6">
                <x-forms.label for="activity_participant_program" required>Participant / Program Assigned</x-forms.label>
                <x-forms.input id="activity_participant_program" type="text" required placeholder="e.g., 2nd Year CS / Program Committee" />
              </div>
            </div>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <p class="text-xs text-slate-600">Status and Date Received will be set by the reviewing office.</p>
              <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                <x-ui.button id="cancel-edit" type="button" variant="secondary" class="hidden w-full sm:w-auto">Cancel Edit</x-ui.button>
                <x-ui.button id="add-activity" type="button" class="w-full sm:w-auto">Add Activity</x-ui.button>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white shadow-sm" id="added-activities-section">
          <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
              <h3 class="text-sm font-semibold text-slate-900">Added Activities</h3>
              <p class="text-xs text-slate-600">Preview of activities included in this submission.</p>
            </div>
          </div>

          <div class="px-4 py-4 sm:px-5">
            <input type="hidden" name="activities_json" id="activities_json" value="[]" />
            <div id="activities-hidden-inputs"></div>

            <div class="overflow-x-hidden rounded-xl border border-slate-200">
              <table class="w-full table-auto border-collapse text-left text-sm">
                <thead class="bg-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-600">
                  <tr>
                    <th scope="col" class="px-4 py-3">Date</th>
                    <th scope="col" class="px-4 py-3">Activity Name</th>
                    <th scope="col" class="px-4 py-3">SDGs</th>
                    <th scope="col" class="px-4 py-3">Venue</th>
                    <th scope="col" class="px-4 py-3">Participant / Program Assigned</th>
                    <th scope="col" class="px-4 py-3">Budget</th>
                    <th scope="col" class="px-4 py-3">Status</th>
                    <th scope="col" class="px-4 py-3">Date Received</th>
                    <th scope="col" class="px-4 py-3">Actions</th>
                  </tr>
                </thead>
                <tbody id="activities-preview-body" class="divide-y divide-slate-200 bg-white">
                  @if ($calLock && $latestCalendar->entries->isNotEmpty())
                    @foreach ($latestCalendar->entries as $entry)
                      <tr class="align-top">
                        <td class="px-4 py-3 text-slate-900">{{ optional($entry->activity_date)->format('M j, Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $entry->activity_name }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $entry->target_sdg ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $entry->venue }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $entry->target_participants ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $entry->estimated_budget !== null ? number_format((float) $entry->estimated_budget, 2) : '—' }}</td>
                        <td class="px-4 py-3">
                          @php
                            $calSt = strtoupper((string) ($latestCalendar->calendar_status ?? ''));
                            $rowStatusLabel = match ($calSt) {
                              'PENDING' => 'Pending review',
                              'UNDER_REVIEW' => 'Under review',
                              'APPROVED' => 'Approved',
                              'REJECTED' => 'Rejected',
                              default => $latestCalendar->calendar_status ?? 'Submitted',
                            };
                          @endphp
                          <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $rowStatusLabel }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-500">For admin use</td>
                        <td class="px-4 py-3 text-sm text-slate-400">—</td>
                      </tr>
                    @endforeach
                  @elseif ($calLock)
                    <tr>
                      <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-600">
                        No activities are stored for this submission.
                      </td>
                    </tr>
                  @else
                    <tr id="activities-empty-state">
                      <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-600">
                        No activities added yet.
                      </td>
                    </tr>
                  @endif
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </x-ui.card>

    <x-ui.card padding="p-0">
      <x-ui.card-section-header
        title="Notes / Reminders"
        subtitle="Please review before submitting."
        content-padding="px-6"
        header-class="!pb-3 pt-3" />
      <div class="px-6 py-5">
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
          <ul class="list-disc space-y-1.5 pl-5">
            <li>Ensure all activities are aligned with the organization&rsquo;s plan.</li>
            <li>Budget entries may indicate &ldquo;No Expenses&rdquo; when applicable.</li>
            <li>Status and Date Received will be completed by the reviewing office.</li>
          </ul>
        </div>
      </div>
    </x-ui.card>

    <x-ui.card padding="p-0">
      <div class="px-6 py-4 sm:py-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end sm:gap-3">
          <x-ui.button type="reset" variant="secondary" class="w-full sm:w-auto">Reset Form</x-ui.button>
          <x-ui.button
            id="submit-activity-calendar"
            type="submit"
            class="w-full sm:w-auto"
          >
            Submit Activity Calendar
          </x-ui.button>
        </div>
      </div>
    </x-ui.card>

    </fieldset>
  </form>
  @endif

</div>

@endsection