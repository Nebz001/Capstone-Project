@extends('layouts.organization-portal')

@section('title', $pageTitle.' — NU Lipa SDAO')

@section('content')

@php
  $resolvedBackRoute = $backRoute ?? route('organizations.submitted-documents');
  $readonlyItemClass = 'rounded-xl border border-slate-200 bg-slate-100/70 px-4 py-3';
  $readonlyLabelClass = 'text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500';
  $readonlyValueClass = 'mt-1.5 whitespace-pre-line text-sm font-bold text-slate-900';
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
          <p class="font-semibold">Profile / Submission Information — For revision</p>
          <p class="mt-1 text-sm font-normal">SDAO has requested updates to your registration submission. Update the sections noted below and resubmit for review.</p>
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
            <dd class="{{ $readonlyValueClass }}">{{ ucfirst((string) ($adviserNomination->status ?? 'pending')) }}</dd>
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
          <form method="POST" action="{{ $adviserRenominateActionUrl ?? '#' }}" class="mt-4 space-y-2">
            @csrf
            <input type="hidden" name="adviser_user_id" id="detail_adviser_user_id" value="">
            <x-forms.label for="detail_adviser_search" required>Nominate New Adviser</x-forms.label>
            <x-forms.input id="detail_adviser_search" name="detail_adviser_search" type="text" placeholder="Search by name, school ID, or email" autocomplete="off" required />
            <div id="detail_adviser_results" class="hidden rounded-xl border border-slate-200 bg-white p-2 shadow-lg"></div>
            @error('adviser_user_id') <x-forms.error>{{ $message }}</x-forms.error> @enderror
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
                    <dt class="{{ $readonlyLabelClass }}">{{ $row['label'] }}</dt>
                    <dd class="{{ $readonlyValueClass }}">{{ $row['value'] }}</dd>
                    @if (! empty($row['link_url']))
                      <a
                        href="{{ $row['link_url'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-2 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-[#003E9F] transition hover:border-[#003E9F]/35 hover:bg-[#003E9F]/5 hover:text-[#00327F]"
                      >
                        View file
                      </a>
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
    <x-ui.card padding="p-0" class="mb-5">
      <x-ui.card-section-header
        title="Planned activities (saved)"
        subtitle="Each row is one calendar activity. Open Submit Proposal to add or edit full details for that activity only."
        content-padding="px-6" />
      <div class="border-t border-slate-100 px-6 py-4.5">
        <div class="overflow-x-auto rounded-xl border border-slate-200">
          <table class="min-w-184 w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="whitespace-nowrap px-4 py-3 sm:px-5">Date</th>
                <th class="px-4 py-3 sm:px-5">Activity</th>
                <th class="whitespace-nowrap px-4 py-3 sm:px-5">SDGs</th>
                <th class="px-4 py-3 sm:px-5">Venue</th>
                <th class="whitespace-nowrap px-4 py-3 sm:px-5">Proposal</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              @foreach ($calendarEntries as $entry)
                @php
                  $prop = $entry->proposal;
                @endphp
                <tr class="align-top">
                  <td class="whitespace-nowrap px-4 py-3.5 font-medium text-slate-800 sm:px-5">{{ optional($entry->activity_date)->format('M j, Y') ?? '—' }}</td>
                  <td class="px-4 py-3.5 text-slate-800 sm:px-5">
                    <span class="font-semibold">{{ $entry->activity_name }}</span>
                    @if ($entry->target_participants)
                      <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $entry->target_participants }}</p>
                    @endif
                  </td>
                  <td class="whitespace-nowrap px-4 py-3.5 font-medium text-slate-800 sm:px-5">{{ $entry->target_sdg ?? '—' }}</td>
                  <td class="px-4 py-3.5 font-medium text-slate-800 sm:px-5">{{ $entry->venue }}</td>
                  <td class="whitespace-nowrap px-4 py-3.5 sm:px-5">
                    @if (! $prop)
                      <span class="inline-flex rounded-full border border-dashed border-slate-300 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">No proposal yet</span>
                    @else
                      @php
                        $ps = strtoupper((string) $prop->proposal_status);
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
                          default => $prop->proposal_status ?? '—',
                        };
                      @endphp
                      <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $proposalBadge }}">{{ $proposalLabel }}</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </x-ui.card>
  @endif

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
            @php $isMissing = ! empty($link['missing'] ?? false) || empty($link['url'] ?? ''); @endphp
            <li
              @if(! empty($link['anchor_id'])) id="{{ $link['anchor_id'] }}" @endif
              class="rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-3 sm:px-4"
              data-file-row
              data-revision-key="{{ $link['key'] ?? '' }}"
              data-file-label="{{ $link['label'] ?? '' }}"
              data-previous-file-name="{{ $link['previous_file_name'] ?? 'No previous file' }}"
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
                        <span class="hidden rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700" data-changed-badge data-revision-key="{{ $link['key'] ?? '' }}">
                          New file selected
                        </span>
                      @endif
                    </div>
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
                        <input type="file" name="replacement_files[{{ $link['key'] }}]" class="hidden" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" data-replace-file-input data-revision-key="{{ $link['key'] }}">
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
                      data-revision-key="{{ $link['key'] ?? '' }}"
                      data-original-href="{{ $link['url'] }}"
                      class="inline-flex h-8 w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-[#003E9F] transition hover:border-[#003E9F]/35 hover:bg-[#003E9F]/5 hover:text-[#00327F] sm:w-auto sm:min-w-29"
                    >
                      View file
                    </a>
                    @if (! empty($link['can_replace']) && ! empty($link['replace_url']))
                      <input type="file" name="replacement_files[{{ $link['key'] }}]" class="hidden" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" data-replace-file-input data-revision-key="{{ $link['key'] }}">
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
    <div class="mt-5 flex justify-end">
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

@if (! empty($revisionSections ?? []) || ! empty($canSubmitFileRevision ?? false) || ! empty($canRenominateAdviser ?? false))
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
          target.scrollIntoView({ behavior: 'smooth', block: 'center' });
          target.classList.add('ring-2', 'ring-amber-300', 'bg-amber-50/80', 'transition');
          if (highlightTimer) window.clearTimeout(highlightTimer);
          highlightTimer = window.setTimeout(() => {
            target.classList.remove('ring-2', 'ring-amber-300', 'bg-amber-50/80', 'transition');
          }, 1800);
        };
        const initialTarget = new URLSearchParams(window.location.search).get('revision_target');
        if (initialTarget) {
          window.setTimeout(() => scrollToTarget(initialTarget), 120);
        }

        document.querySelectorAll('[data-revision-target-id]').forEach((button) => {
          button.addEventListener('click', () => {
            scrollToTarget(button.dataset.revisionTargetId || '');
          });
        });

        const adviserSearch = document.getElementById('detail_adviser_search');
        const adviserHidden = document.getElementById('detail_adviser_user_id');
        const adviserResults = document.getElementById('detail_adviser_results');
        if (adviserSearch && adviserHidden && adviserResults) {
          let adviserTimer = null;
          const hideAdviserResults = () => {
            adviserResults.classList.add('hidden');
            adviserResults.innerHTML = '';
          };
          const runAdviserSearch = async (q) => {
            if (!q || q.trim().length < 2) {
              hideAdviserResults();
              return;
            }
            try {
              const res = await fetch(`/api/users/search-advisers?q=${encodeURIComponent(q.trim())}`, {
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
              adviserResults.innerHTML = rows.map((row) => `
                <button
                  type="button"
                  class="flex w-full flex-col items-start rounded-lg px-3 py-2 text-left text-xs text-slate-700 transition hover:bg-slate-100"
                  data-detail-adviser-id="${row.id}"
                  data-detail-adviser-text="${row.full_name || ''} | ${row.school_id || ''} | ${row.email || ''}"
                >
                  <span class="font-semibold text-slate-900">${row.full_name || 'N/A'}</span>
                  <span>${row.school_id || 'No school ID'} • ${row.email || 'No email'}</span>
                </button>
              `).join('');
              adviserResults.classList.remove('hidden');
            } catch (_e) {
              hideAdviserResults();
            }
          };
          adviserSearch.addEventListener('input', () => {
            adviserHidden.value = '';
            if (adviserTimer) window.clearTimeout(adviserTimer);
            adviserTimer = window.setTimeout(() => runAdviserSearch(adviserSearch.value || ''), 220);
          });
          adviserResults.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;
            const btn = target.closest('[data-detail-adviser-id]');
            if (!(btn instanceof HTMLElement)) return;
            adviserHidden.value = btn.getAttribute('data-detail-adviser-id') || '';
            adviserSearch.value = btn.getAttribute('data-detail-adviser-text') || '';
            hideAdviserResults();
          });
        }

        document.querySelectorAll('[data-replace-file-trigger]').forEach((trigger) => {
          trigger.addEventListener('click', () => {
            const input = trigger.parentElement?.querySelector('[data-replace-file-input]');
            if (!(input instanceof HTMLInputElement)) return;
            input.click();
          });
        });

        const selectedRevisionKeys = new Set();
        const submitBtn = document.getElementById('registration-revision-submit-btn');
        const submitForm = document.getElementById('registration-revision-resubmit-form');
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
                previous: row.dataset.previousFileName || 'No previous file',
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
          submitBtn.disabled = submitting || selectedRevisionKeys.size === 0;
        };

        document.querySelectorAll('[data-replace-file-input]').forEach((input) => {
          input.addEventListener('change', () => {
            const fileInput = input;
            const key = fileInput.getAttribute('data-revision-key') || '';
            const file = fileInput.files && fileInput.files.length > 0 ? fileInput.files[0] : null;
            if (file && !REPLACEMENT_ACCEPT_RE.test(file.name || '')) {
              fileInput.value = '';
              setReplacementWarning('Only PDF, Word, or image files are allowed.');
              if (key) {
                selectedRevisionKeys.delete(key);
                selectedReplacementNames.delete(key);
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
              }
              syncSubmitState();
              return;
            }
            if (key && file) {
              setReplacementWarning('');
              selectedRevisionKeys.add(key);
              selectedReplacementNames.set(key, file.name || 'Selected file');
              document.querySelectorAll(`[data-changed-badge][data-revision-key="${key}"]`).forEach((badge) => {
                badge.classList.remove('hidden');
                badge.classList.add('inline-flex');
                badge.textContent = 'New file selected';
              });
              const blobUrl = URL.createObjectURL(file);
              document.querySelectorAll(`[data-view-file-link][data-revision-key="${key}"]`).forEach((link) => {
                if (!(link instanceof HTMLAnchorElement)) return;
                link.href = blobUrl;
              });
              document.querySelectorAll(`[data-local-view-wrap][data-revision-key="${key}"]`).forEach((wrap) => {
                wrap.classList.remove('hidden');
              });
            } else if (key) {
              selectedRevisionKeys.delete(key);
              selectedReplacementNames.delete(key);
              setReplacementWarning('');
            }
            syncSubmitState();
          });
        });

        submitBtn?.addEventListener('click', () => {
          if (!submitForm || submitting || selectedRevisionKeys.size === 0) return;
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
      })();
    </script>
  @endsection
@endif
