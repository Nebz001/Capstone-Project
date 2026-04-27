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

  @if (! empty($revisionSections ?? []))
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
                  @if (! empty($item['anchor_id']))
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
        @if ($remarkHighlight)
          <div class="rounded-lg border border-yellow-200/90 bg-white/60 px-3 py-3">
            <p class="text-xs font-bold uppercase tracking-wide text-yellow-950">GENERAL REMARKS</p>
            <p class="mt-1.5 whitespace-pre-wrap text-sm font-normal leading-relaxed text-yellow-950/90">{{ $remarkHighlight }}</p>
          </div>
        @endif
      </div>
    </x-feedback.blocked-message>
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
        <ul class="space-y-2">
          @foreach ($fileLinks as $link)
            @php $isMissing = ! empty($link['missing'] ?? false) || empty($link['url'] ?? ''); @endphp
            <li @if(! empty($link['anchor_id'])) id="{{ $link['anchor_id'] }}" @endif class="rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-3 sm:px-4">
              <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 flex items-start gap-2.5">
                  <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $isMissing ? 'bg-slate-200 text-slate-500' : 'bg-[#003E9F]/10 text-[#003E9F]' }}">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 0H5.625C5.004 2.25 4.5 2.754 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                  </span>
                  <div class="min-w-0">
                    <p class="wrap-break-word text-sm font-semibold leading-relaxed text-slate-800">{{ $link['label'] }}</p>
                    @if (! empty($link['is_revised']) && ! empty($link['revision_note']))
                      <p class="mt-1 text-xs text-amber-700">{{ $link['revision_note'] }}</p>
                    @endif
                  </div>
                </div>
                @if ($isMissing)
                  <div class="flex w-full shrink-0 flex-wrap items-center gap-2 sm:w-auto">
                    <span class="inline-flex w-full items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-500 sm:w-auto sm:min-w-29">
                      No file uploaded
                    </span>
                    @if (! empty($link['is_revised']) && ! empty($link['replace_url']))
                      <form method="POST" action="{{ $link['replace_url'] }}" enctype="multipart/form-data" class="inline-flex w-full items-center gap-2 sm:w-auto">
                        @csrf
                        <input type="file" name="replacement_file" class="hidden" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" data-replace-file-input>
                        <button type="button" class="inline-flex w-full items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 sm:w-auto" data-replace-file-trigger>
                          Replace file
                        </button>
                      </form>
                    @endif
                  </div>
                @else
                  <div class="flex w-full shrink-0 flex-wrap items-center gap-2 sm:w-auto">
                    <a
                      href="{{ $link['url'] }}"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-[#003E9F] transition hover:border-[#003E9F]/35 hover:bg-[#003E9F]/5 hover:text-[#00327F] sm:w-auto sm:min-w-29"
                    >
                      View file
                    </a>
                    @if (! empty($link['is_revised']) && ! empty($link['replace_url']))
                      <form method="POST" action="{{ $link['replace_url'] }}" enctype="multipart/form-data" class="inline-flex w-full items-center gap-2 sm:w-auto">
                        @csrf
                        <input type="file" name="replacement_file" class="hidden" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" data-replace-file-input>
                        <button type="button" class="inline-flex w-full items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 sm:w-auto" data-replace-file-trigger>
                          Replace file
                        </button>
                      </form>
                    @endif
                  </div>
                @endif
              </div>
            </li>
          @endforeach
        </ul>
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

</div>

@endsection

@if (! empty($revisionSections ?? []))
  @section('scripts')
    <script>
      (() => {
        let highlightTimer = null;
        const scrollToTarget = (targetId) => {
          if (!targetId) return;
          const target = document.getElementById(targetId);
          if (!target) return;
          target.scrollIntoView({ behavior: 'smooth', block: 'center' });
          target.classList.add('ring-2', 'ring-amber-300', 'bg-amber-50/80', 'transition');
          if (highlightTimer) window.clearTimeout(highlightTimer);
          highlightTimer = window.setTimeout(() => {
            target.classList.remove('ring-2', 'ring-amber-300', 'bg-amber-50/80', 'transition');
          }, 1800);
        };

        document.querySelectorAll('[data-revision-target-id]').forEach((button) => {
          button.addEventListener('click', () => {
            scrollToTarget(button.dataset.revisionTargetId || '');
          });
        });

        document.querySelectorAll('[data-replace-file-trigger]').forEach((trigger) => {
          trigger.addEventListener('click', () => {
            const form = trigger.closest('form');
            const input = form?.querySelector('[data-replace-file-input]');
            if (!(input instanceof HTMLInputElement)) return;
            input.click();
          });
        });

        document.querySelectorAll('[data-replace-file-input]').forEach((input) => {
          input.addEventListener('change', () => {
            const fileInput = input;
            if (!fileInput.files || fileInput.files.length === 0) return;
            const form = fileInput.closest('form');
            if (!form) return;
            form.submit();
          });
        });
      })();
    </script>
  @endsection
@endif
