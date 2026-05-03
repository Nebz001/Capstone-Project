@extends('layouts.organization-portal')

@section('title', $pageTitle.' — NU Lipa SDAO')

@section('content')

@php
  $resolvedBackRoute = $backRoute ?? route('organizations.submitted-documents');
  $readonlyItemClass = 'rounded-xl border border-slate-200 bg-slate-100/70 px-4 py-3';
  $readonlyLabelClass = 'text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500';
  $readonlyValueClass = 'mt-1.5 whitespace-pre-line text-sm font-bold text-slate-900';
  $requiredReplacementFileKeys = collect($fileLinks ?? [])
      ->filter(fn (array $row): bool => (bool) ($row['can_replace'] ?? false))
      ->map(fn (array $row): string => (string) ($row['key'] ?? ''))
      ->filter(fn (string $k): bool => $k !== '')
      ->values()
      ->all();
@endphp
<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">

  <header class="mb-6">
    <a href="{{ $resolvedBackRoute }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
      <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
      </svg>
      {{ $backLabel ?? 'Back to Submitted Documents' }}
    </a>
    <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $pageTitle }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
      </div>
      <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
        {{ $statusLabel }}
      </span>
    </div>
  </header>

  @if (session('activity_calendar_success_redirect'))
    <div
      id="activity-calendar-submitted-success-alert-data"
      data-success-title="Activity Calendar Submitted"
      data-success-message="Your activity calendar has been submitted successfully. You will be redirected to Submitted Documents."
      data-success-redirect-url="{{ session('activity_calendar_success_redirect') }}"
      data-success-redirect-delay="1800"
      hidden
    ></div>
  @endif

  @if (session('activity_calendar_revision_resubmit_ok'))
    <x-feedback.blocked-message variant="success" :icon="false" class="mb-5">
      <p class="font-semibold">Activity calendar resubmitted for review</p>
      <p class="mt-1 text-sm font-normal">SDAO is reviewing your updated planned activities.</p>
    </x-feedback.blocked-message>
  @endif

  @if (! empty($progressStages ?? null))
    <x-submission-progress-card
      variant="embed"
      :document-label="$progressDocumentLabel ?? ''"
      :stages="$progressStages"
      :summary="$progressSummary ?? ''"
    />
  @endif

  @if (! empty($isResubmittedPendingReview ?? false))
    <x-feedback.blocked-message variant="info" :icon="false" class="mb-5">
      <div class="flex items-start gap-3">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-100/90" aria-hidden="true">
          <svg class="h-4.5 w-4.5 text-sky-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.06.852l-.708 2.836a.75.75 0 0 0 1.06.852l.041-.02M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
          </svg>
        </div>
        <div class="min-w-0">
          <p class="font-semibold">Submission resubmitted successfully.</p>
          <p class="mt-1 text-sm font-normal">Your updated file is now under review by SDAO.</p>
        </div>
      </div>
    </x-feedback.blocked-message>
  @elseif (! empty($revisionSections ?? []))
    <x-feedback.blocked-message variant="warning" :icon="false" class="mb-5">
      <div class="flex items-start gap-3">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-yellow-100/90" aria-hidden="true">
          <svg class="h-4.5 w-4.5 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
          </svg>
        </div>
        <div class="min-w-0">
          @if (! empty($isActivityCalendarDetail ?? false))
            <p class="font-semibold">Activity calendar — For revision</p>
            <p class="mt-1 text-sm font-normal">SDAO requested changes. Follow each link to the field, edit the activity row, then use <span class="font-semibold">Submit Activity Calendar</span> when every flagged field has been updated.</p>
          @else
            <p class="font-semibold">Profile / Submission Information — For revision</p>
            <p class="mt-1 text-sm font-normal">SDAO has requested updates to your registration submission. Update the sections noted below and resubmit for review.</p>
          @endif
        </div>
      </div>
      <div class="mt-4 space-y-3">
        @foreach ($revisionSections as $section)
          <div class="rounded-lg border border-yellow-200/90 bg-white/60 px-3 py-3">
            <p class="text-xs font-bold uppercase tracking-wide text-yellow-950">{{ strtoupper((string) ($section['title'] ?? 'Section')) }} ({{ count($section['items'] ?? []) }})</p>
            <ul class="mt-2 space-y-1.5">
              @foreach (($section['items'] ?? []) as $item)
                <li>
                  @if (! empty($item['target_url']))
                    <a
                      href="{{ $item['target_url'] }}"
                      class="inline-flex w-full items-start gap-1 rounded-md px-2 py-1 text-left text-xs text-yellow-950 transition hover:bg-yellow-100/70 focus:outline-none focus:ring-2 focus:ring-yellow-400/60"
                    >
                      <span class="font-semibold underline underline-offset-2">{{ $item['field'] ?? 'Field' }}</span>
                      <span>— {{ $item['note'] ?? '' }}</span>
                    </a>
                  @elseif (! empty($item['anchor_id']))
                    <button
                      type="button"
                      class="inline-flex w-full items-start gap-1 rounded-md px-2 py-1 text-left text-xs text-yellow-950 transition hover:bg-yellow-100/70 focus:outline-none focus:ring-2 focus:ring-yellow-400/60"
                      data-revision-target-id="{{ $item['anchor_id'] }}"
                    >
                      <span class="font-semibold underline underline-offset-2">{{ $item['field'] ?? 'Field' }}</span>
                      <span>— {{ $item['note'] ?? '' }}</span>
                    </button>
                  @else
                    <p class="inline-flex w-full items-start gap-1 rounded-md px-2 py-1 text-xs text-yellow-950">
                      <span class="font-semibold">{{ $item['field'] ?? 'Field' }}</span>
                      <span>— {{ $item['note'] ?? '' }}</span>
                    </p>
                  @endif
                </li>
              @endforeach
            </ul>
          </div>
        @endforeach
        <div class="rounded-lg border border-yellow-200/90 bg-white/60 px-3 py-3">
          <p class="text-xs font-bold uppercase tracking-wide text-yellow-950">GENERAL REMARKS</p>
          <p class="mt-1.5 whitespace-pre-wrap text-sm font-normal leading-relaxed text-yellow-950/90">{{ $remarkHighlight ?: 'No general remarks provided.' }}</p>
        </div>
      </div>
    </x-feedback.blocked-message>
  @endif

  @if ($errors->has('replacement_file') || $errors->has('replacement_files') || $errors->has('replacement_files.*'))
    <x-feedback.blocked-message variant="warning" class="mb-5" :message="$errors->first('replacement_file') ?: ($errors->first('replacement_files') ?: $errors->first('replacement_files.*'))" />
  @endif

  @error('activity_calendar')
    <x-feedback.blocked-message variant="warning" class="mb-5">{{ $message }}</x-feedback.blocked-message>
  @enderror

  <x-feedback.blocked-message variant="warning" class="mb-5 hidden" id="replacement-file-warning-message" />

  @if (! empty($adviserNomination ?? null))
    <x-ui.card padding="p-0" class="mb-5">
      <x-ui.card-section-header
        title="Faculty Adviser Nomination"
        subtitle="Nomination status for this submission."
        content-padding="px-6"
      />
      <div class="border-t border-slate-100 px-6 py-4.5">
        <dl class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
          <div class="{{ $readonlyItemClass }}">
            <dt class="{{ $readonlyLabelClass }}">Full name</dt>
            <dd class="{{ $readonlyValueClass }}">{{ $adviserNomination->user?->full_name ?? 'N/A' }}</dd>
          </div>
          <div class="{{ $readonlyItemClass }}">
            <dt class="{{ $readonlyLabelClass }}">School ID</dt>
            <dd class="{{ $readonlyValueClass }}">{{ $adviserNomination->user?->school_id ?? 'N/A' }}</dd>
          </div>
          <div class="{{ $readonlyItemClass }}">
            <dt class="{{ $readonlyLabelClass }}">Email</dt>
            <dd class="{{ $readonlyValueClass }}">{{ $adviserNomination->user?->email ?? 'N/A' }}</dd>
          </div>
          <div class="{{ $readonlyItemClass }}">
            <dt class="{{ $readonlyLabelClass }}">Status</dt>
            <dd class="mt-1.5">
              @if (($renewalAdviserPortalStatus ?? null) !== null)
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $renewalAdviserPortalStatus['badge_class'] }}">
                  {{ $renewalAdviserPortalStatus['label'] }}
                </span>
              @else
                <span class="{{ $readonlyValueClass }}">{{ ucfirst((string) ($adviserNomination->status ?? 'pending')) }}</span>
              @endif
            </dd>
          </div>
          <div class="{{ $readonlyItemClass }} md:col-span-2">
            <dt class="{{ $readonlyLabelClass }}">Rejection reason</dt>
            <dd class="{{ $readonlyValueClass }}">{{ $adviserNomination->rejection_notes ?: '—' }}</dd>
          </div>
        </dl>

        @if (! empty($canRenominateAdviser ?? false))
          <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <p class="font-semibold">Previous adviser was rejected.</p>
            <p class="mt-1">Please nominate a new adviser to continue review.</p>
          </div>
          <form
            method="POST"
            action="{{ $adviserRenominateActionUrl ?? '#' }}"
            class="mt-4 space-y-2"
            @if (! empty($adviserSearchExceptOrganizationId))
              data-adviser-except-organization-id="{{ (int) $adviserSearchExceptOrganizationId }}"
            @endif
          >
            @csrf
            <input type="hidden" name="adviser_user_id" id="detail_adviser_user_id" value="">
            <x-forms.label for="detail_adviser_search" required>Nominate New Adviser</x-forms.label>
            <x-forms.input id="detail_adviser_search" name="detail_adviser_search" type="text" placeholder="Search by name, school ID, or email" autocomplete="off" required />
            <div id="detail_adviser_results" class="hidden rounded-xl border border-slate-200 bg-white p-2 shadow-lg"></div>
            @error('adviser_user_id') <x-forms.error>{{ $message }}</x-forms.error> @enderror
            <p id="detail_adviser_client_error" class="hidden text-sm text-red-600" role="status" aria-live="polite"></p>
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#00327F]">
              Nominate New Adviser
            </button>
          </form>
        @endif
      </div>
    </x-ui.card>
  @endif

  <x-ui.card padding="p-0" class="mb-5">
    <x-ui.card-section-header
      title="Submission details"
      subtitle="Read-only details from your submitted record."
      content-padding="px-6"
    />
    <div class="border-t border-slate-100 px-6 py-4.5">
      @if (! empty($metaSections ?? []))
        <div class="space-y-3.5">
          @foreach ($metaSections as $section)
            <section>
              <h3 class="mb-2 text-sm font-semibold text-slate-900">{{ $section['title'] ?? 'Details' }}</h3>
              <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach (($section['rows'] ?? []) as $row)
                  <div class="{{ $readonlyItemClass }} {{ !empty($row['wide']) || !empty($row['table']) ? 'md:col-span-2' : '' }}">
                    @if (! empty($row['link_url']))
                      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                          <dt class="{{ $readonlyLabelClass }}">{{ $row['label'] }}</dt>
                          <dd class="{{ $readonlyValueClass }}">{{ $row['value'] }}</dd>
                        </div>
                        <a
                          href="{{ $row['link_url'] }}"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="mt-2 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-[#003E9F] transition hover:border-[#003E9F]/35 hover:bg-[#003E9F]/5 hover:text-[#00327F]"
                        >
                          View file
                        </a>
                      </div>
                    @else
                      <dt class="{{ $readonlyLabelClass }}">{{ $row['label'] }}</dt>
                      <dd class="{{ $readonlyValueClass }}">{{ $row['value'] }}</dd>
                    @endif
                    @if (! empty($row['table']) && is_array($row['table']))
                      <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                        <table class="min-w-160 w-full divide-y divide-slate-200 text-left text-xs sm:text-sm">
                          <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                              <th class="px-3 py-2.5">Material / item</th>
                              <th class="px-3 py-2.5">Quantity</th>
                              <th class="px-3 py-2.5">Unit price</th>
                              <th class="px-3 py-2.5">Price</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-slate-100">
                            @foreach ($row['table'] as $budgetRow)
                              <tr class="align-top">
                                <td class="px-3 py-2.5 font-medium text-slate-800">{{ $budgetRow['material'] ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-slate-700">{{ $budgetRow['quantity'] ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-slate-700">{{ $budgetRow['unit_price'] ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-slate-700">{{ $budgetRow['price'] ?? '—' }}</td>
                              </tr>
                            @endforeach
                          </tbody>
                        </table>
                      </div>
                    @endif
                  </div>
                @endforeach
              </dl>
            </section>
          @endforeach
        </div>
      @else
        <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
          @foreach ($metaRows as $row)
            <div class="{{ $readonlyItemClass }}">
              <dt class="{{ $readonlyLabelClass }}">{{ $row['label'] }}</dt>
              <dd class="{{ $readonlyValueClass }}">{{ $row['value'] }}</dd>
            </div>
          @endforeach
        </dl>
      @endif
    </div>
  </x-ui.card>

  @if (isset($calendarEntries) && $calendarEntries->isNotEmpty())
    @php
      $acFlags = $activityCalendarEntryRevisionFlags ?? [];
      $acNotes = $activityCalendarEntryRevisionNotes ?? [];
      $acEntryState = $activityCalendarEntryUpdateState ?? [];
      $acCanRev = ! empty($canSubmitActivityCalendarEntryRevisions ?? false);
      $showAcPlanActions = ! empty($isActivityCalendarDetail ?? false);
    @endphp
    @if ($acCanRev)
      <form method="POST" action="{{ $activityCalendarEntryRevisionSubmitUrl }}" class="mb-5">
        @csrf
    @endif
    <x-ui.card padding="p-0" class="mb-5">
      <x-ui.card-section-header
        title="Planned activities (saved)"
        subtitle="{{ $acCanRev ? 'Use Edit to open the revision form for each flagged activity, update every field SDAO noted, then submit for review.' : 'Each row is one calendar activity. Open Submit Proposal to add or edit full details for that activity only.' }}"
        content-padding="px-6" />
      <div class="border-t border-slate-100 px-6 py-4.5">
        <div class="overflow-x-auto rounded-xl border border-slate-200">
          <table class="min-w-200 w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="whitespace-nowrap px-4 py-3 sm:px-5">Date</th>
                <th class="px-4 py-3 sm:px-5">Activity</th>
                <th class="px-4 py-3 sm:px-5">Participant / Program Assigned</th>
                <th class="whitespace-nowrap px-4 py-3 sm:px-5">SDGs</th>
                <th class="px-4 py-3 sm:px-5">Venue</th>
                <th class="whitespace-nowrap px-4 py-3 sm:px-5">Budget</th>
                <th class="whitespace-nowrap px-4 py-3 sm:px-5">Proposal</th>
                @if ($showAcPlanActions)
                  <th class="whitespace-nowrap px-4 py-3 sm:px-5">Actions</th>
                @endif
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              @foreach ($calendarEntries as $entry)
                @php
                  $prop = $entry->proposal;
                  $erf = $acFlags[$entry->id] ?? [];
                  $rName = ! empty($erf['activity_name']);
                  $rDate = ! empty($erf['activity_date']);
                  $rVenue = ! empty($erf['venue']);
                  $rSdg = ! empty($erf['target_sdg']);
                  $rPart = ! empty($erf['target_participants']);
                  $rBudget = ! empty($erf['estimated_budget']);
                  $rProg = ! empty($erf['target_program']);
                  $rowRev = $rName || $rDate || $rVenue || $rSdg || $rPart || $rBudget || $rProg;
                  $budgetDisplay = $entry->estimated_budget !== null ? number_format((float) $entry->estimated_budget, 2) : '—';
                  $isRowUpdatedTbl = (bool) ($acEntryState[$entry->id]['is_updated'] ?? false);
                @endphp
                <tr class="align-top">
                  <td class="whitespace-nowrap px-4 py-4 text-slate-800 sm:px-5">{{ optional($entry->activity_date)->format('M j, Y') ?? '—' }}</td>
                  <td class="px-4 py-4 text-slate-800 sm:px-5">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="font-semibold">{{ $entry->activity_name }}</span>
                      @if ($isRowUpdatedTbl)
                        <span class="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-blue-700">Updated</span>
                      @endif
                    </div>
                  </td>
                  <td class="px-4 py-4 text-slate-800 sm:px-5">
                    {{ filled($entry->target_participants) ? $entry->target_participants : '—' }}
                    @if (filled($entry->target_program))
                      <div class="mt-1 text-xs text-slate-600"><span class="font-semibold text-slate-700">Program:</span> {{ $entry->target_program }}</div>
                    @endif
                  </td>
                  <td class="whitespace-nowrap px-4 py-4 text-slate-800 sm:px-5">{{ $entry->target_sdg ?? '—' }}</td>
                  <td class="px-4 py-4 text-slate-800 sm:px-5">{{ $entry->venue }}</td>
                  <td class="whitespace-nowrap px-4 py-4 text-slate-800 sm:px-5">{{ $budgetDisplay }}</td>
                  <td class="whitespace-nowrap px-4 py-4 sm:px-5">
                    @if (! $prop)
                      <span class="inline-flex rounded-full border border-dashed border-slate-300 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">No proposal yet</span>
                    @else
                      @php
                        $ps = strtoupper((string) ($prop->status ?? $prop->proposal_status ?? ''));
                        $proposalBadge = match ($ps) {
                          'DRAFT' => 'bg-slate-200 text-slate-800 border border-slate-300',
                          'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
                          'UNDER_REVIEW' => 'bg-blue-100 text-blue-800 border border-blue-200',
                          'REVISION' => 'bg-orange-100 text-orange-800 border border-orange-200',
                          'APPROVED' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
                          'REJECTED' => 'bg-rose-100 text-rose-800 border border-rose-200',
                          default => 'bg-slate-100 text-slate-700 border border-slate-200',
                        };
                        $proposalLabel = match ($ps) {
                          'DRAFT' => 'Draft',
                          'PENDING' => 'Pending',
                          'UNDER_REVIEW' => 'Under review',
                          'REVISION' => 'For revision',
                          'APPROVED' => 'Approved',
                          'REJECTED' => 'Rejected',
                          default => ($prop->status ?? $prop->proposal_status) ?: '—',
                        };
                      @endphp
                      <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $proposalBadge }}">{{ $proposalLabel }}</span>
                    @endif
                  </td>
                  @if ($showAcPlanActions)
                    <td class="whitespace-nowrap px-4 py-4 align-middle sm:px-5">
                      @if ($acCanRev && $rowRev)
                        <button
                          type="button"
                          data-open-activity-revision-editor="{{ $entry->id }}"
                          @disabled($isRowUpdatedTbl)
                          class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-[#003DA5] shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-[#003DA5]/20 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-white"
                        >
                          Edit
                        </button>
                      @elseif ($isRowUpdatedTbl)
                        <button
                          type="button"
                          disabled
                          class="inline-flex cursor-not-allowed items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-400 opacity-60"
                        >
                          Edit
                        </button>
                      @else
                        —
                      @endif
                    </td>
                  @endif
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @if ($acCanRev)
          @foreach ($calendarEntries as $entry)
            @php
              $erfEd = $acFlags[$entry->id] ?? [];
              $rNameEd = ! empty($erfEd['activity_name']);
              $rDateEd = ! empty($erfEd['activity_date']);
              $rVenueEd = ! empty($erfEd['venue']);
              $rSdgEd = ! empty($erfEd['target_sdg']);
              $rPartEd = ! empty($erfEd['target_participants']);
              $rBudgetEd = ! empty($erfEd['estimated_budget']);
              $rProgEd = ! empty($erfEd['target_program']);
              $rowRevEd = $rNameEd || $rDateEd || $rVenueEd || $rSdgEd || $rPartEd || $rBudgetEd || $rProgEd;
              $rowNotesEd = $acNotes[$entry->id] ?? [];
              $budgetOrigEd = $entry->estimated_budget !== null ? number_format((float) $entry->estimated_budget, 2, '.', '') : '';
            @endphp
            @if ($rowRevEd)
              <div id="activity-revision-editor-{{ $entry->id }}" data-activity-revision-editor="{{ $entry->id }}" class="mt-6 hidden overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <h3 class="text-xl font-bold tracking-tight text-slate-900">Edit Activity Revision</h3>
                    <p class="mt-1 text-sm text-slate-500">Only fields requested by SDAO are shown in this revision form.</p>
                  </div>
                  <button type="button" data-close-activity-revision-editor="{{ $entry->id }}" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400/25">
                    Close
                  </button>
                </div>
                <div class="grid grid-cols-1 gap-5 px-6 py-6 md:grid-cols-2">
                  @if ($rDateEd)
                    <div id="activity-calendar-entry-{{ $entry->id }}-date" data-ac-scroll-entry-id="{{ $entry->id }}" class="rounded-2xl border border-slate-200 bg-slate-50/90 p-4">
                      <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-slate-800">Date</span>
                        <span class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700" data-updated-badge="{{ $entry->id }}-activity_date">Updated</span>
                      </div>
                      <input type="date" name="activities[{{ $entry->id }}][activity_date]" value="{{ old('activities.'.$entry->id.'.activity_date', $entry->activity_date?->format('Y-m-d') ?? '') }}" data-activity-revision-field data-revision-required="1" data-entry-id="{{ $entry->id }}" data-revision-key="activity_date" data-original-value="{{ $entry->activity_date?->format('Y-m-d') ?? '' }}" class="mt-2 w-full min-w-[130px] rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-inner shadow-slate-900/5" />
                      @if (filled($rowNotesEd['activity_date'] ?? null))
                        <p class="mt-2 rounded-xl border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs font-semibold leading-relaxed text-yellow-900 wrap-break-word">Revision note: {{ $rowNotesEd['activity_date'] }}</p>
                      @endif
                    </div>
                  @endif
                  @if ($rNameEd)
                    <div id="activity-calendar-entry-{{ $entry->id }}-activity_name" data-ac-scroll-entry-id="{{ $entry->id }}" class="rounded-2xl border border-slate-200 bg-slate-50/90 p-4">
                      <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-slate-800">Activity name</span>
                        <span class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700" data-updated-badge="{{ $entry->id }}-activity_name">Updated</span>
                      </div>
                      <input type="text" name="activities[{{ $entry->id }}][activity_name]" value="{{ old('activities.'.$entry->id.'.activity_name', $entry->activity_name) }}" data-activity-revision-field data-revision-required="1" data-entry-id="{{ $entry->id }}" data-revision-key="activity_name" data-original-value="{{ $entry->activity_name }}" class="mt-2 w-full min-w-[130px] rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 shadow-inner shadow-slate-900/5" />
                      @if (filled($rowNotesEd['activity_name'] ?? null))
                        <p class="mt-2 rounded-xl border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs font-semibold leading-relaxed text-yellow-900 wrap-break-word">Revision note: {{ $rowNotesEd['activity_name'] }}</p>
                      @endif
                    </div>
                  @endif
                  @if ($rPartEd)
                    <div id="activity-calendar-entry-{{ $entry->id }}-participants" data-ac-scroll-entry-id="{{ $entry->id }}" class="rounded-2xl border border-slate-200 bg-slate-50/90 p-4">
                      <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-slate-800">Participants</span>
                        <span class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700" data-updated-badge="{{ $entry->id }}-target_participants">Updated</span>
                      </div>
                      <input type="text" name="activities[{{ $entry->id }}][target_participants]" value="{{ old('activities.'.$entry->id.'.target_participants', $entry->target_participants) }}" data-activity-revision-field data-revision-required="1" data-entry-id="{{ $entry->id }}" data-revision-key="target_participants" data-original-value="{{ $entry->target_participants }}" class="mt-2 w-full min-w-[130px] rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-inner shadow-slate-900/5" />
                      @if (filled($rowNotesEd['target_participants'] ?? null))
                        <p class="mt-2 rounded-xl border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs font-semibold leading-relaxed text-yellow-900 wrap-break-word">Revision note: {{ $rowNotesEd['target_participants'] }}</p>
                      @endif
                    </div>
                  @endif
                  @if ($rProgEd)
                    <div id="activity-calendar-entry-{{ $entry->id }}-program" data-ac-scroll-entry-id="{{ $entry->id }}" class="rounded-2xl border border-slate-200 bg-slate-50/90 p-4">
                      <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-slate-800">Program assigned</span>
                        <span class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700" data-updated-badge="{{ $entry->id }}-target_program">Updated</span>
                      </div>
                      <input type="text" name="activities[{{ $entry->id }}][target_program]" value="{{ old('activities.'.$entry->id.'.target_program', $entry->target_program) }}" data-activity-revision-field data-revision-required="1" data-entry-id="{{ $entry->id }}" data-revision-key="target_program" data-original-value="{{ $entry->target_program }}" class="mt-2 w-full min-w-[130px] rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-inner shadow-slate-900/5" />
                      @if (filled($rowNotesEd['target_program'] ?? null))
                        <p class="mt-2 rounded-xl border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs font-semibold leading-relaxed text-yellow-900 wrap-break-word">Revision note: {{ $rowNotesEd['target_program'] }}</p>
                      @endif
                    </div>
                  @endif
                  @if ($rSdgEd)
                    <div id="activity-calendar-entry-{{ $entry->id }}-sdgs" data-ac-scroll-entry-id="{{ $entry->id }}" class="rounded-2xl border border-slate-200 bg-slate-50/90 p-4">
                      <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-slate-800">Target SDG</span>
                        <span class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700" data-updated-badge="{{ $entry->id }}-target_sdg">Updated</span>
                      </div>
                      <input type="text" name="activities[{{ $entry->id }}][target_sdg]" value="{{ old('activities.'.$entry->id.'.target_sdg', $entry->target_sdg) }}" data-activity-revision-field data-revision-required="1" data-entry-id="{{ $entry->id }}" data-revision-key="target_sdg" data-original-value="{{ $entry->target_sdg }}" class="mt-2 w-full min-w-[130px] rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-inner shadow-slate-900/5" />
                      @if (filled($rowNotesEd['target_sdg'] ?? null))
                        <p class="mt-2 rounded-xl border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs font-semibold leading-relaxed text-yellow-900 wrap-break-word">Revision note: {{ $rowNotesEd['target_sdg'] }}</p>
                      @endif
                    </div>
                  @endif
                  @if ($rVenueEd)
                    <div id="activity-calendar-entry-{{ $entry->id }}-venue" data-ac-scroll-entry-id="{{ $entry->id }}" class="rounded-2xl border border-slate-200 bg-slate-50/90 p-4">
                      <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-slate-800">Venue</span>
                        <span class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700" data-updated-badge="{{ $entry->id }}-venue">Updated</span>
                      </div>
                      <input type="text" name="activities[{{ $entry->id }}][venue]" value="{{ old('activities.'.$entry->id.'.venue', $entry->venue) }}" data-activity-revision-field data-revision-required="1" data-entry-id="{{ $entry->id }}" data-revision-key="venue" data-original-value="{{ $entry->venue }}" class="mt-2 w-full min-w-[130px] rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-inner shadow-slate-900/5" />
                      @if (filled($rowNotesEd['venue'] ?? null))
                        <p class="mt-2 rounded-xl border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs font-semibold leading-relaxed text-yellow-900 wrap-break-word">Revision note: {{ $rowNotesEd['venue'] }}</p>
                      @endif
                    </div>
                  @endif
                  @if ($rBudgetEd)
                    <div id="activity-calendar-entry-{{ $entry->id }}-budget" data-ac-scroll-entry-id="{{ $entry->id }}" class="rounded-2xl border border-slate-200 bg-slate-50/90 p-4">
                      <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-slate-800">Estimated budget</span>
                        <span class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700" data-updated-badge="{{ $entry->id }}-estimated_budget">Updated</span>
                      </div>
                      <input type="number" step="0.01" min="0" name="activities[{{ $entry->id }}][estimated_budget]" value="{{ old('activities.'.$entry->id.'.estimated_budget', $budgetOrigEd) }}" data-activity-revision-field data-revision-required="1" data-entry-id="{{ $entry->id }}" data-revision-key="estimated_budget" data-original-value="{{ $budgetOrigEd }}" class="mt-2 w-full min-w-[130px] rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-inner shadow-slate-900/5" />
                      @if (filled($rowNotesEd['estimated_budget'] ?? null))
                        <p class="mt-2 rounded-xl border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs font-semibold leading-relaxed text-yellow-900 wrap-break-word">Revision note: {{ $rowNotesEd['estimated_budget'] }}</p>
                      @endif
                    </div>
                  @endif
                </div>
              </div>
            @endif
          @endforeach
          <div class="mt-6 flex justify-end">
            <button
              type="submit"
              data-submit-activity-calendar-revisions
              disabled
              class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/40 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-[#003E9F]"
            >
              Submit Activity Calendar
            </button>
          </div>
        @endif
      </div>
    </x-ui.card>
    @if ($acCanRev)
      </form>
    @endif
  @endif

  @if (($hasFileAttachments ?? true) && ($calendarEntries === null || ! empty($alwaysShowSubmittedFilesCard ?? false)))
  <x-ui.card padding="p-0" class="mb-5" id="submitted-files">
    <x-ui.card-section-header
      title="Submitted files"
      subtitle="Open or download documents you uploaded (opens in the browser when supported)."
      content-padding="px-6" />
    <div class="border-t border-slate-100 px-6 py-4.5">
      @if (count($fileLinks) === 0)
        <p class="text-sm text-slate-500">No file attachments are stored for this submission, or the submission did not include uploads.</p>
      @else
        <form id="registration-revision-resubmit-form" method="POST" action="{{ $submitActionUrl ?? '#' }}" enctype="multipart/form-data" data-has-revision-files="{{ ! empty($canSubmitFileRevision ?? false) ? '1' : '0' }}">
          @csrf
          <ul class="space-y-2">
          @foreach ($fileLinks as $link)
            @php
              $isMissing = ! empty($link['missing'] ?? false) || empty($link['url'] ?? '');
              $currentFileLabel = $link['previous_file_name'] ?? '';
              $showCurrentFileMeta = ! $isMissing
                && $currentFileLabel !== ''
                && ! in_array($currentFileLabel, ['No previous file', 'Previously uploaded file'], true);
              $revKey = (string) ($link['key'] ?? '');
            @endphp
            <li
              @if(! empty($link['anchor_id'])) id="{{ $link['anchor_id'] }}" @endif
              class="rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-3 sm:px-4"
              data-file-row
              data-file-revision-row="{{ $revKey }}"
              data-revision-key="{{ $revKey }}"
              data-file-label="{{ $link['label'] ?? '' }}"
              data-original-file-name="{{ $showCurrentFileMeta ? $currentFileLabel : '' }}"
              data-download-url="{{ $link['download_url'] ?? $link['url'] ?? '' }}"
            >
              <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 flex items-start gap-2.5">
                  <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $isMissing ? 'bg-slate-200 text-slate-500' : 'bg-[#003E9F]/10 text-[#003E9F]' }}">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 0H5.625C5.004 2.25 4.5 2.754 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                  </span>
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <p class="wrap-break-word text-sm font-semibold leading-relaxed text-slate-800">{{ $link['label'] }}</p>
                      @if (! empty($link['is_changed_saved']) || ! empty($link['is_changed_recent']))
                        <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700" data-changed-badge data-revision-key="{{ $link['key'] ?? '' }}">
                          Updated
                        </span>
                      @elseif (! empty($link['is_revised']))
                        <span class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700" data-changed-badge data-js-replacement-selected-badge data-revision-key="{{ $link['key'] ?? '' }}">
                          NEW FILE SELECTED
                        </span>
                      @endif
                    </div>
                    @if ($showCurrentFileMeta)
                      <p class="mt-1 text-xs text-slate-700">
                        <span class="font-semibold text-slate-800">Current file:</span>
                        @if ($revKey !== '' && ! empty($link['can_replace']))
                          <span class="wrap-break-word font-medium" data-current-file-name="{{ $revKey }}">{{ $currentFileLabel }}</span>
                        @else
                          <span class="wrap-break-word font-medium">{{ $currentFileLabel }}</span>
                        @endif
                      </p>
                    @endif
                    @if (! empty($link['can_replace']) && $showCurrentFileMeta && $revKey !== '' && ! empty($link['download_url'] ?? $link['url'] ?? null))
                      <div class="mt-1 hidden text-xs text-slate-600" data-previous-file-line="{{ $revKey }}">
                        <span class="font-semibold text-slate-800">Previous file:</span>
                        <span class="wrap-break-word font-medium" data-previous-saved-name>{{ $currentFileLabel }}</span>
                        <a href="{{ $link['download_url'] ?? $link['url'] }}" class="ml-1 font-semibold text-[#003E9F] underline hover:text-[#00327F]" data-previous-file-download-link>Download</a>
                      </div>
                    @endif
                    @if (! empty($link['is_revised']) && ! empty($link['revision_note']))
                      <p class="mt-1 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs text-amber-800">
                        <span class="font-semibold">Revision notes:</span> {{ $link['revision_note'] }}
                      </p>
                    @endif
                  </div>
                </div>
                @if ($isMissing)
                  <div class="flex w-full shrink-0 flex-wrap items-center gap-2 sm:w-auto">
                    <span class="inline-flex w-full items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-500 sm:w-auto sm:min-w-29">
                      No file uploaded
                    </span>
                    @if (! empty($link['can_replace']) && ! empty($link['replace_url']))
                        <input type="file" name="replacement_files[{{ $link['key'] }}]" class="hidden" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" data-replace-file-input data-file-revision-input="{{ $link['key'] }}" data-revision-key="{{ $link['key'] }}">
                        <button type="button" class="inline-flex h-8 w-full items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-3 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 sm:w-auto sm:min-w-29" data-replace-file-trigger>
                          Replace file
                        </button>
                    @endif
                  </div>
                @else
                  <div class="flex w-full shrink-0 flex-wrap items-center gap-2 sm:w-auto">
                    <a
                      href="{{ $link['url'] }}"
                      target="_blank"
                      rel="noopener noreferrer"
                      data-view-file-link
                      data-view-file-button="{{ $revKey }}"
                      data-original-view-url="{{ $link['url'] }}"
                      data-revision-key="{{ $link['key'] ?? '' }}"
                      data-original-href="{{ $link['url'] }}"
                      class="inline-flex h-8 w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-[#003E9F] transition hover:border-[#003E9F]/35 hover:bg-[#003E9F]/5 hover:text-[#00327F] sm:w-auto sm:min-w-29"
                    >
                      View file
                    </a>
                    @if (! empty($link['can_replace']) && ! empty($link['replace_url']))
                      <input type="file" name="replacement_files[{{ $link['key'] }}]" class="hidden" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" data-replace-file-input data-file-revision-input="{{ $link['key'] }}" data-revision-key="{{ $link['key'] }}">
                        <button type="button" class="inline-flex h-8 w-full items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-3 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 sm:w-auto sm:min-w-29" data-replace-file-trigger>
                          Replace file
                        </button>
                    @endif
                  </div>
                @endif
              </div>
              @if ($isMissing && ! empty($link['can_replace']))
                <div class="mt-2 hidden" data-local-view-wrap data-revision-key="{{ $link['key'] ?? '' }}">
                  <a
                    href="#"
                    target="_blank"
                    rel="noopener noreferrer"
                    data-view-file-link
                    data-view-file-button="{{ $link['key'] ?? '' }}"
                    data-original-view-url=""
                    data-revision-key="{{ $link['key'] ?? '' }}"
                    data-original-href=""
                    class="inline-flex h-8 w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-[#003E9F] transition hover:border-[#003E9F]/35 hover:bg-[#003E9F]/5 hover:text-[#00327F] sm:w-auto sm:min-w-29"
                  >
                    View file
                  </a>
                </div>
              @endif
            </li>
          @endforeach
          </ul>
        </form>
      @endif
    </div>
  </x-ui.card>
  @endif

  @if (count($workflowLinks) > 0)
    <div class="flex flex-wrap gap-3">
      @foreach ($workflowLinks as $link)
        @if (($link['variant'] ?? 'secondary') === 'primary')
          <a href="{{ $link['href'] }}" class="inline-flex rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#00327F]">
            {{ $link['label'] }}
          </a>
        @else
          <a href="{{ $link['href'] }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
            {{ $link['label'] }}
          </a>
        @endif
      @endforeach
    </div>
  @endif

  @if (! empty($canSubmitFileRevision ?? false))
    <div class="mt-5 flex flex-col items-end gap-2">
      <p id="registration-file-revision-submit-hint" class="max-w-md text-right text-xs text-slate-600">
        Replace all files requested for revision before submitting.
      </p>
      <button
        type="button"
        id="registration-revision-submit-btn"
        class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/40 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-[#003E9F]"
        disabled
      >
        Submit
      </button>
    </div>
  @endif

  @if (! empty($canSubmitFileRevision ?? false))
    <div id="registration-file-confirm-modal" class="fixed inset-0 z-90 hidden items-center justify-center bg-slate-950/60 px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="registration-file-confirm-title">
      <div class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-900/20">
        <div class="border-b border-slate-100 px-6 py-5">
          <h3 id="registration-file-confirm-title" class="text-xl font-bold tracking-tight text-slate-900">Confirm File Changes</h3>
          <p class="mt-1.5 text-sm leading-relaxed text-slate-500">Review the replaced files below before submitting your revised registration.</p>
        </div>
        <div class="border-b border-slate-100 bg-slate-50/40 px-6 py-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Changes Summary</p>
          <div class="mt-3 max-h-[52vh] overflow-y-auto pr-1">
            <div id="registration-file-confirm-list" class="space-y-3"></div>
          </div>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
          <button type="button" id="registration-file-confirm-cancel" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20">
            Cancel
          </button>
          <button type="button" id="registration-file-confirm-submit" class="inline-flex items-center justify-center rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-sky-800/25 transition hover:bg-sky-800 focus:outline-none focus:ring-4 focus:ring-sky-500/25">
            Confirm Submit
          </button>
        </div>
      </div>
    </div>
  @endif

</div>

@endsection

@if (! empty($revisionSections ?? []) || ! empty($canSubmitFileRevision ?? false) || ! empty($canRenominateAdviser ?? false) || ! empty($canSubmitActivityCalendarEntryRevisions ?? false))
  @section('scripts')
    <script>
      (() => {
        const MAX_REPLACEMENT_FILE_MB = 2;
        const MAX_REPLACEMENT_FILE_BYTES = MAX_REPLACEMENT_FILE_MB * 1024 * 1024;
        const REPLACEMENT_ACCEPT_RE = /\.(pdf|doc|docx|jpe?g|png)$/i;
        let highlightTimer = null;
        const replacementWarningEl = document.getElementById('replacement-file-warning-message');
        const setReplacementWarning = (message) => {
          if (!replacementWarningEl) return;
          if (!message) {
            replacementWarningEl.classList.add('hidden');
            replacementWarningEl.textContent = '';
            return;
          }
          replacementWarningEl.classList.remove('hidden');
          replacementWarningEl.textContent = message;
        };
        const normalizeTargetId = (value) => String(value || '')
          .trim()
          .toLowerCase()
          .replace(/[^a-z0-9_-]+/g, '-')
          .replace(/-+/g, '-')
          .replace(/^[-_]+|[-_]+$/g, '');
        const resolveRevisionTargetElement = (targetId) => {
          const raw = String(targetId || '').trim();
          if (raw) {
            try {
              const direct = document.getElementById(raw);
              if (direct) return direct;
            } catch (_err) {}
          }
          const normalized = normalizeTargetId(targetId);
          if (!normalized) return null;
          const requirementKey = normalized
            .replace(/^revision-file-requirements-/, '')
            .replace(/^revision-file-/, '');
          const candidates = [
            normalized,
            normalized.replaceAll('_', '-'),
            normalized.replaceAll('-', '_'),
            normalized.startsWith('revision-file-') && !normalized.startsWith('revision-file-requirements-')
              ? normalized.replace('revision-file-', 'revision-file-requirements-')
              : '',
            normalized.startsWith('revision-file-requirements-')
              ? normalized.replace('revision-file-requirements-', 'revision-file-')
              : '',
            requirementKey ? `revision-file-requirements-${requirementKey}` : '',
            requirementKey ? `revision-file-requirements-${requirementKey.replaceAll('-', '_')}` : '',
            requirementKey ? `revision-file-requirements-${requirementKey.replaceAll('_', '-')}` : '',
            requirementKey ? `revision-file-${requirementKey}` : '',
            requirementKey ? `revision-file-${requirementKey.replaceAll('-', '_')}` : '',
            requirementKey ? `revision-file-${requirementKey.replaceAll('_', '-')}` : '',
          ].filter(Boolean);

          for (const candidate of candidates) {
            const target = document.getElementById(candidate);
            if (target) return target;
          }

          return null;
        };
        const scrollToTarget = (targetId) => {
          if (!targetId) return;
          const target = resolveRevisionTargetElement(targetId);
          if (!target) return;
          const runHighlight = () => {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.classList.add('ring-2', 'ring-amber-400', 'ring-offset-2', 'ring-offset-white', 'transition', 'rounded-2xl');
            if (highlightTimer) window.clearTimeout(highlightTimer);
            highlightTimer = window.setTimeout(() => {
              target.classList.remove('ring-2', 'ring-amber-400', 'ring-offset-2', 'ring-offset-white', 'transition', 'rounded-2xl');
            }, 1800);
          };
          if (target.id && target.id.startsWith('activity-calendar-entry-')) {
            const eid = target.getAttribute('data-ac-scroll-entry-id');
            if (eid) {
              document.querySelectorAll('[data-activity-revision-editor]').forEach((el) => {
                el.classList.add('hidden');
              });
              const card = document.getElementById(`activity-revision-editor-${eid}`);
              if (card) {
                card.classList.remove('hidden');
              }
              window.setTimeout(runHighlight, 260);
              return;
            }
          }
          runHighlight();
        };
        const initialTarget = new URLSearchParams(window.location.search).get('revision_target');
        if (initialTarget) {
          window.setTimeout(() => scrollToTarget(initialTarget), 120);
        }
        if (window.location.hash && window.location.hash.length > 1) {
          const hashId = decodeURIComponent(window.location.hash.slice(1));
          window.setTimeout(() => scrollToTarget(hashId), 160);
        }

        document.querySelectorAll('[data-revision-target-id]').forEach((button) => {
          button.addEventListener('click', () => {
            scrollToTarget(button.dataset.revisionTargetId || '');
          });
        });

        const adviserSearch = document.getElementById('detail_adviser_search');
        const adviserHidden = document.getElementById('detail_adviser_user_id');
        const adviserResults = document.getElementById('detail_adviser_results');
        const detailAdviserForm = adviserSearch?.closest('form');
        const detailAdviserClientErr = document.getElementById('detail_adviser_client_error');
        const detailAdviserUnavailableMsg = 'This adviser is already assigned to another organization.';
        const escapeDetailAttr = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        if (adviserSearch && adviserHidden && adviserResults) {
          let adviserTimer = null;
          const hideAdviserResults = () => {
            adviserResults.classList.add('hidden');
            adviserResults.innerHTML = '';
          };
          const showDetailAdviserErr = (msg) => {
            if (detailAdviserClientErr) {
              detailAdviserClientErr.textContent = msg;
              detailAdviserClientErr.classList.remove('hidden');
            } else {
              window.alert(msg);
            }
          };
          const clearDetailAdviserErr = () => {
            if (detailAdviserClientErr) {
              detailAdviserClientErr.textContent = '';
              detailAdviserClientErr.classList.add('hidden');
            }
          };
          const exceptOrgId = (detailAdviserForm?.getAttribute('data-adviser-except-organization-id') || '').trim();
          const runAdviserSearch = async (q) => {
            if (!q || q.trim().length < 2) {
              hideAdviserResults();
              return;
            }
            try {
              const params = new URLSearchParams({ q: q.trim() });
              if (exceptOrgId !== '' && parseInt(exceptOrgId, 10) > 0) {
                params.set('except_organization_id', exceptOrgId);
              }
              const res = await fetch(`/api/users/search-advisers?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
              });
              if (!res.ok) {
                hideAdviserResults();
                return;
              }
              const rows = await res.json();
              if (!Array.isArray(rows) || rows.length === 0) {
                hideAdviserResults();
                return;
              }
              adviserResults.innerHTML = rows.map((row) => {
                const name = row.full_name || 'N/A';
                const sub = `${row.school_id || 'No school ID'} • ${row.email || 'No email'}`;
                const available = row.is_available !== false;
                const reason = (row.unavailable_reason && String(row.unavailable_reason).trim()) || 'Already assigned to another RSO';
                if (!available) {
                  return `<div class="flex w-full cursor-not-allowed flex-col items-start rounded-lg px-3 py-2 text-left text-xs text-slate-700 opacity-60" data-detail-adviser-blocked="1"><span class="font-semibold text-slate-900">${name}</span><span>${sub}</span><p class="mt-1 text-xs text-red-600">${reason}</p></div>`;
                }
                const textAttr = escapeDetailAttr(`${row.full_name || ''} | ${row.school_id || ''} | ${row.email || ''}`);
                return `<button type="button" class="flex w-full flex-col items-start rounded-lg px-3 py-2 text-left text-xs text-slate-700 transition hover:bg-slate-100" data-detail-adviser-id="${row.id}" data-detail-adviser-text="${textAttr}"><span class="font-semibold text-slate-900">${name}</span><span>${sub}</span></button>`;
              }).join('');
              adviserResults.classList.remove('hidden');
            } catch (_e) {
              hideAdviserResults();
            }
          };
          adviserSearch.addEventListener('input', () => {
            clearDetailAdviserErr();
            adviserHidden.value = '';
            if (adviserTimer) window.clearTimeout(adviserTimer);
            adviserTimer = window.setTimeout(() => runAdviserSearch(adviserSearch.value || ''), 220);
          });
          adviserResults.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;
            if (target.closest('[data-detail-adviser-blocked]')) {
              event.preventDefault();
              showDetailAdviserErr(detailAdviserUnavailableMsg);
              return;
            }
            const btn = target.closest('[data-detail-adviser-id]');
            if (!(btn instanceof HTMLElement)) return;
            clearDetailAdviserErr();
            adviserHidden.value = btn.getAttribute('data-detail-adviser-id') || '';
            adviserSearch.value = btn.getAttribute('data-detail-adviser-text') || '';
            hideAdviserResults();
          });
        }

        document.querySelectorAll('[data-replace-file-trigger]').forEach((trigger) => {
          trigger.addEventListener('click', () => {
            const row = trigger.closest('[data-file-row]');
            const input = row?.querySelector('[data-replace-file-input]');
            if (!(input instanceof HTMLInputElement)) return;
            input.click();
          });
        });

        const selectedRevisionKeys = new Set();
        const requiredFileRevisionKeys = @json($requiredReplacementFileKeys);
        const submitBtn = document.getElementById('registration-revision-submit-btn');
        const submitForm = document.getElementById('registration-revision-resubmit-form');
        const submitHint = document.getElementById('registration-file-revision-submit-hint');
        const confirmModal = document.getElementById('registration-file-confirm-modal');
        const confirmList = document.getElementById('registration-file-confirm-list');
        const confirmCancel = document.getElementById('registration-file-confirm-cancel');
        const confirmSubmit = document.getElementById('registration-file-confirm-submit');
        const selectedReplacementNames = new Map();
        let submitting = false;
        let pendingChanges = [];
        const escapeHtml = (value) => String(value ?? '')
          .replaceAll('&', '&amp;')
          .replaceAll('<', '&lt;')
          .replaceAll('>', '&gt;')
          .replaceAll('"', '&quot;')
          .replaceAll("'", '&#39;');
        const closeConfirmModal = () => {
          if (!confirmModal) return;
          confirmModal.classList.add('hidden');
          confirmModal.classList.remove('flex');
        };
        const setConfirmLoadingState = (loading) => {
          if (!confirmSubmit || !confirmCancel || !submitBtn) return;
          if (loading) {
            confirmSubmit.disabled = true;
            confirmSubmit.textContent = 'Submitting...';
            confirmSubmit.classList.add('opacity-75', 'cursor-not-allowed');
            confirmCancel.disabled = true;
            confirmCancel.classList.add('opacity-60', 'cursor-not-allowed');
            submitBtn.disabled = true;
          } else {
            confirmSubmit.disabled = false;
            confirmSubmit.textContent = 'Confirm Submit';
            confirmSubmit.classList.remove('opacity-75', 'cursor-not-allowed');
            confirmCancel.disabled = false;
            confirmCancel.classList.remove('opacity-60', 'cursor-not-allowed');
            syncSubmitState();
          }
        };
        const buildChangedFileSummary = () => {
          return Array.from(selectedRevisionKeys)
            .map((key) => {
              const row = document.querySelector(`[data-file-row][data-revision-key="${key}"]`);
              if (!(row instanceof HTMLElement)) return null;
              const next = selectedReplacementNames.get(key);
              if (!next) return null;
              return {
                key,
                label: row.dataset.fileLabel || 'Requirement',
                previous: row.dataset.originalFileName || 'No previous file',
                next,
              };
            })
            .filter((entry) => entry !== null);
        };
        const openConfirmModal = (changes) => {
          if (!confirmModal || !confirmList) return;
          const blocks = changes.map((change) => `
            <article class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5 shadow-sm">
              <p class="text-sm font-semibold text-slate-900">${escapeHtml(change.label)}</p>
              <div class="mt-2 space-y-1.5">
                <p class="text-xs text-slate-600">Previous: <span class="font-medium text-slate-800">${escapeHtml(change.previous)}</span></p>
                <p class="text-xs text-sky-700">New: <span class="font-semibold text-sky-800">${escapeHtml(change.next)}</span></p>
              </div>
            </article>
          `).join('');
          confirmList.innerHTML = blocks;
          confirmModal.classList.remove('hidden');
          confirmModal.classList.add('flex');
        };
        const syncSubmitState = () => {
          if (!submitBtn) return;
          const allRequiredSelected =
            requiredFileRevisionKeys.length > 0 &&
            requiredFileRevisionKeys.every((key) => selectedRevisionKeys.has(key));
          submitBtn.disabled = submitting || !allRequiredSelected;
          if (submitHint) {
            if (submitBtn.disabled && !submitting && requiredFileRevisionKeys.length > 0) {
              submitHint.classList.remove('hidden');
            } else {
              submitHint.classList.add('hidden');
            }
          }
        };

        const revisionFilePreviewObjectUrls = {};
        const revokeRevisionFilePreview = (key) => {
          const url = revisionFilePreviewObjectUrls[key];
          if (!url) return;
          URL.revokeObjectURL(url);
          delete revisionFilePreviewObjectUrls[key];
        };
        const eachRevisionViewLink = (key, callback) => {
          document.querySelectorAll('[data-view-file-button]').forEach((el) => {
            if ((el.getAttribute('data-view-file-button') || '') !== key) return;
            callback(el);
          });
        };
        const restoreRevisionViewFileLinks = (key) => {
          revokeRevisionFilePreview(key);
          eachRevisionViewLink(key, (el) => {
            if (!(el instanceof HTMLAnchorElement)) return;
            const original =
              el.dataset.originalViewUrl ||
              el.getAttribute('data-original-view-url') ||
              el.dataset.originalHref ||
              el.getAttribute('data-original-href') ||
              '';
            el.href = original || '#';
          });
        };
        const attachRevisionFilePreview = (key, file) => {
          revokeRevisionFilePreview(key);
          const objectUrl = URL.createObjectURL(file);
          revisionFilePreviewObjectUrls[key] = objectUrl;
          eachRevisionViewLink(key, (el) => {
            if (el instanceof HTMLAnchorElement) {
              el.href = objectUrl;
            }
          });
        };
        window.addEventListener('beforeunload', () => {
          Object.keys(revisionFilePreviewObjectUrls).forEach((k) => {
            const url = revisionFilePreviewObjectUrls[k];
            if (url) URL.revokeObjectURL(url);
          });
        });

        const restoreRevisionRowUi = (row, key) => {
          if (!(row instanceof HTMLElement) || !key) return;
          document.querySelectorAll(`[data-js-replacement-selected-badge][data-revision-key="${key}"]`).forEach((badge) => {
            badge.classList.add('hidden');
            badge.classList.remove('inline-flex');
          });
          const original = row.dataset.originalFileName || '';
          const currentNameEl = row.querySelector(`[data-current-file-name="${key}"]`);
          if (currentNameEl) {
            currentNameEl.textContent = original;
          }
          const prevLine = row.querySelector(`[data-previous-file-line="${key}"]`);
          if (prevLine) {
            prevLine.classList.add('hidden');
          }
          document.querySelectorAll(`[data-local-view-wrap][data-revision-key="${key}"]`).forEach((wrap) => {
            wrap.classList.add('hidden');
          });
          restoreRevisionViewFileLinks(key);
        };

        document.querySelectorAll('[data-replace-file-input]').forEach((input) => {
          input.addEventListener('change', () => {
            const fileInput = input;
            const key = fileInput.getAttribute('data-revision-key') || '';
            const row = fileInput.closest('[data-file-row]');
            const file = fileInput.files && fileInput.files.length > 0 ? fileInput.files[0] : null;
            if (file && !REPLACEMENT_ACCEPT_RE.test(file.name || '')) {
              fileInput.value = '';
              setReplacementWarning('Only PDF, Word, or image files are allowed.');
              if (key) {
                selectedRevisionKeys.delete(key);
                selectedReplacementNames.delete(key);
                restoreRevisionRowUi(row, key);
              }
              syncSubmitState();
              return;
            }
            if (file && file.size > MAX_REPLACEMENT_FILE_BYTES) {
              fileInput.value = '';
              const label = document.querySelector(`[data-file-row][data-revision-key="${key}"]`)?.getAttribute('data-file-label') || 'Selected file';
              setReplacementWarning(`${label} is too large. Maximum allowed file size is ${MAX_REPLACEMENT_FILE_MB} MB.`);
              if (key) {
                selectedRevisionKeys.delete(key);
                selectedReplacementNames.delete(key);
                restoreRevisionRowUi(row, key);
              }
              syncSubmitState();
              return;
            }
            if (key && file) {
              setReplacementWarning('');
              selectedRevisionKeys.add(key);
              selectedReplacementNames.set(key, file.name || 'Selected file');
              document.querySelectorAll(`[data-js-replacement-selected-badge][data-revision-key="${key}"]`).forEach((badge) => {
                badge.classList.remove('hidden');
                badge.classList.add('inline-flex');
                badge.textContent = 'NEW FILE SELECTED';
              });
              const currentNameEl = row?.querySelector(`[data-current-file-name="${key}"]`);
              if (currentNameEl) {
                currentNameEl.textContent = file.name || '';
              }
              const prevLine = row?.querySelector(`[data-previous-file-line="${key}"]`);
              if (prevLine) {
                prevLine.classList.remove('hidden');
              }
              attachRevisionFilePreview(key, file);
              document.querySelectorAll(`[data-local-view-wrap][data-revision-key="${key}"]`).forEach((wrap) => {
                wrap.classList.remove('hidden');
              });
            } else if (key) {
              selectedRevisionKeys.delete(key);
              selectedReplacementNames.delete(key);
              setReplacementWarning('');
              restoreRevisionRowUi(row, key);
            }
            syncSubmitState();
          });
        });

        submitBtn?.addEventListener('click', () => {
          if (
            !submitForm ||
            submitting ||
            !requiredFileRevisionKeys.every((k) => selectedRevisionKeys.has(k))
          ) {
            return;
          }
          pendingChanges = buildChangedFileSummary();
          if (pendingChanges.length === 0) {
            syncSubmitState();
            return;
          }
          openConfirmModal(pendingChanges);
        });

        submitForm?.addEventListener('submit', (event) => {
          if (!submitting) {
            event.preventDefault();
          }
        });

        confirmCancel?.addEventListener('click', () => {
          if (submitting) return;
          closeConfirmModal();
          syncSubmitState();
        });

        confirmSubmit?.addEventListener('click', () => {
          if (!submitForm || submitting || pendingChanges.length === 0) return;
          submitting = true;
          setConfirmLoadingState(true);
          submitForm.submit();
        });

        @if (! empty($canSubmitActivityCalendarEntryRevisions ?? false))
        const normalizeAcCal = (value) => String(value ?? '').trim();
        const normalizeAcCalBudget = (value) => {
          const n = Number.parseFloat(String(value ?? '').replace(/,/g, ''));
          if (!Number.isFinite(n)) return '';
          return n.toFixed(2);
        };
        const fieldCurrentNormalized = (field) => {
          if (field instanceof HTMLInputElement && field.type === 'date') {
            return normalizeAcCal(field.value);
          }
          if (field instanceof HTMLInputElement && field.type === 'number') {
            return normalizeAcCalBudget(field.value);
          }
          return normalizeAcCal(field.value);
        };
        const fieldOriginalNormalized = (field) => {
          const o = field.dataset.originalValue ?? '';
          if (field instanceof HTMLInputElement && field.type === 'number') {
            return normalizeAcCalBudget(o);
          }
          return normalizeAcCal(o);
        };
        const openActivityRevisionEditor = (entryId) => {
          document.querySelectorAll('[data-activity-revision-editor]').forEach((el) => {
            el.classList.add('hidden');
          });
          const card = document.getElementById(`activity-revision-editor-${entryId}`);
          if (card) {
            card.classList.remove('hidden');
            window.setTimeout(() => {
              card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 50);
          }
        };
        const refreshActivityRevisionFieldState = (entryId) => {
          document.querySelectorAll(`[data-activity-revision-field][data-entry-id="${entryId}"]`).forEach((field) => {
            const key = field.getAttribute('data-revision-key') || '';
            const badge = document.querySelector(`[data-updated-badge="${entryId}-${key}"]`);
            const isUpdated = fieldCurrentNormalized(field) !== fieldOriginalNormalized(field);
            if (badge) {
              badge.classList.toggle('hidden', !isUpdated);
            }
          });
        };
        const refreshSubmitActivityCalendarButton = () => {
          const requiredFields = document.querySelectorAll('[data-activity-revision-field][data-revision-required="1"]');
          const submitButton = document.querySelector('[data-submit-activity-calendar-revisions]');
          const allUpdated = Array.from(requiredFields).every((field) => {
            return fieldCurrentNormalized(field) !== fieldOriginalNormalized(field);
          });
          if (submitButton instanceof HTMLButtonElement) {
            submitButton.disabled = !allUpdated;
            submitButton.classList.toggle('opacity-50', !allUpdated);
            submitButton.classList.toggle('cursor-not-allowed', !allUpdated);
          }
        };
        document.querySelectorAll('[data-open-activity-revision-editor]').forEach((btn) => {
          btn.addEventListener('click', () => {
            if (btn instanceof HTMLButtonElement && btn.disabled) return;
            const id = btn.getAttribute('data-open-activity-revision-editor') || '';
            if (!id) return;
            openActivityRevisionEditor(id);
          });
        });
        document.querySelectorAll('[data-close-activity-revision-editor]').forEach((btn) => {
          btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-close-activity-revision-editor') || '';
            document.getElementById(`activity-revision-editor-${id}`)?.classList.add('hidden');
          });
        });
        document.querySelectorAll('[data-activity-revision-field]').forEach((field) => {
          const entryId = field.getAttribute('data-entry-id') || '';
          const onFieldChange = () => {
            if (entryId) refreshActivityRevisionFieldState(entryId);
            refreshSubmitActivityCalendarButton();
          };
          field.addEventListener('input', onFieldChange);
          field.addEventListener('change', onFieldChange);
        });
        refreshSubmitActivityCalendarButton();
        @endif
      })();
    </script>
  @endsection
@endif
