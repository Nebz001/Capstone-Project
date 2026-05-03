@extends('layouts.admin')

@section('title', $pageTitle . ' — NU Lipa SDAO')

@section('content')
@php
  $statusClass = match ($status) {
    'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
    'UNDER_REVIEW', 'REVIEWED' => 'bg-blue-100 text-blue-700 border border-blue-200',
    'APPROVED' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    'REJECTED' => 'bg-rose-100 text-rose-700 border border-rose-200',
    'REVISION', 'REVISION_REQUIRED' => 'bg-orange-100 text-orange-700 border border-orange-200',
    default => 'bg-slate-100 text-slate-700 border border-slate-200',
  };
@endphp

<div class="mb-6 flex items-center justify-between gap-3">
  <a href="{{ route('approver.dashboard') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-[#003E9F] hover:text-[#00327F]">
    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
    </svg>
    Back to approver dashboard
  </a>
  <span
    id="proposal-stage-status-badge"
    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}"
    data-status="{{ $status }}"
  >{{ $status }}</span>
</div>

<x-ui.card padding="p-5">
  <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
    @foreach ($details as $label => $value)
      <div class="rounded-xl border border-slate-200 bg-slate-50/90 p-4">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">{{ $label }}</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $value }}</dd>
      </div>
    @endforeach
  </dl>

  @if (($isProposalReview ?? false) && !empty($detailSections))
    <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm font-semibold text-slate-900">Field review progress</p>
        <div id="proposal-review-progress" class="inline-flex flex-wrap items-center gap-2 text-xs font-semibold">
          <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700" data-progress="verified">Verified: 0</span>
          <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-700" data-progress="needs_revision">Needs Revision: 0</span>
          <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-slate-700" data-progress="pending">Pending: 0</span>
        </div>
      </div>
    </div>
  @endif

  @if (!empty($detailSections))
    <div class="mt-6 space-y-6 border-t border-slate-100 pt-6">
      @foreach ($detailSections as $section)
        <section class="{{ ($isProposalReview ?? false) ? 'rounded-xl border border-slate-200 bg-white p-4' : '' }}" data-review-section-card="{{ ($isProposalReview ?? false) ? '1' : '0' }}" data-section-key="section_{{ $loop->index }}">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="text-base font-bold text-slate-900">{{ $section['title'] }}</h2>
              @if (! empty($section['subtitle'] ?? ''))
                <p class="mt-1 text-xs text-slate-500">{{ $section['subtitle'] }}</p>
              @endif
            </div>
            @if ($isProposalReview ?? false)
              <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700" data-section-status-badge="section_{{ $loop->index }}">Pending</span>
            @endif
          </div>
          @if (($section['rows'] ?? []) === [] && ($section['title'] ?? '') === 'Submitted files')
            <p class="mt-4 text-sm text-slate-500">No file attachments uploaded for this proposal.</p>
          @elseif (($section['title'] ?? '') === 'Submitted files')
            <div class="mt-4 space-y-3">
              @foreach (($section['rows'] ?? []) as $row)
                @if (! empty($row['file_row']))
                  <div class="field-review-card rounded-2xl border border-slate-200 bg-slate-50/60 px-4 py-3">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                      <div class="flex min-w-0 items-start gap-3 sm:items-center">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#003E9F]/10 text-[#003E9F]" aria-hidden="true">
                          <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 0H5.625C5.004 2.25 4.5 2.754 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                          </svg>
                        </div>
                        <div class="min-w-0">
                          <p class="text-sm font-bold text-slate-900">{{ $row['label'] }}</p>
                          @if (trim((string) ($row['file_name'] ?? '')) !== '')
                            <p class="mt-0.5 truncate text-sm text-slate-700">
                              <span class="font-semibold text-slate-800">Current file:</span>
                              {{ $row['file_name'] }}
                            </p>
                          @else
                            <p class="mt-0.5 text-sm text-slate-700">{{ $row['value'] }}</p>
                          @endif
                          @include('approver.partials.proposal-revision-diff', ['row' => $row])
                        </div>
                      </div>
                      <div class="flex w-full min-w-0 shrink-0 flex-wrap items-center gap-2 lg:w-auto lg:justify-end">
                        @if (! empty($row['view_url']))
                          <a
                            href="{{ $row['view_url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-[#003DA5] transition hover:bg-blue-50"
                          >View file</a>
                        @endif
                        @if (! empty($row['download_url']))
                          <a
                            href="{{ $row['download_url'] }}"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-[#003DA5] transition hover:bg-blue-50"
                          >Download</a>
                        @endif
                        @if (($isProposalReview ?? false) && ($row['reviewable'] ?? true))
                          @php
                            $fieldKey = (string) ($row['key'] ?? '');
                            $savedStatus = (string) ($row['review']['status'] ?? '');
                            $fieldStatus = old("field_reviews.{$fieldKey}.status", $savedStatus === 'approved' ? 'passed' : ($savedStatus === 'revision' ? 'revision' : 'pending'));
                            if (! in_array($fieldStatus, ['pending', 'passed', 'revision'], true)) {
                              $fieldStatus = 'pending';
                            }
                            $fieldComment = old("field_reviews.{$fieldKey}.comment", $row['review']['comment'] ?? '');
                          @endphp
                          <div class="field-review-control min-w-0" data-field-review data-section-key="section_{{ $loop->parent->index }}" data-field-key="{{ $fieldKey }}" data-field-label="{{ $row['label'] }}">
                            <input type="hidden" name="field_reviews[{{ $fieldKey }}][label]" value="{{ $row['label'] }}" form="proposal-field-review-form" />
                            <div class="inline-flex flex-wrap items-center gap-1 rounded-lg border border-slate-200 bg-white p-1" role="group" aria-label="Field review for {{ $row['label'] }}">
                              <button type="button" class="field-review-btn rounded-md px-2.5 py-1 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-50 {{ $fieldStatus === 'passed' ? 'ring-2 ring-emerald-400' : '' }}" data-status-value="passed" aria-pressed="{{ $fieldStatus === 'passed' ? 'true' : 'false' }}">Passed</button>
                              <button type="button" class="field-review-btn rounded-md px-2.5 py-1 text-[11px] font-semibold text-amber-700 transition hover:bg-amber-50 {{ $fieldStatus === 'revision' ? 'ring-2 ring-amber-400' : '' }}" data-status-value="revision" aria-pressed="{{ $fieldStatus === 'revision' ? 'true' : 'false' }}">Revision</button>
                            </div>
                            <input type="hidden" class="field-review-status" name="field_reviews[{{ $fieldKey }}][status]" value="{{ $fieldStatus }}" form="proposal-field-review-form" />
                            <div class="field-review-note mt-2 w-full min-w-0 sm:min-w-[18rem] {{ $fieldStatus === 'revision' ? '' : 'hidden' }}">
                              <x-forms.textarea class="field-review-note-input" name="field_reviews[{{ $fieldKey }}][comment]" form="proposal-field-review-form" :rows="2" placeholder="Add revision note for this field...">{{ $fieldComment }}</x-forms.textarea>
                              <p class="mt-1 text-xs text-slate-500">Required when this field needs revision.</p>
                              <p class="field-review-note-error mt-1 hidden text-xs font-medium text-rose-700">Revision note is required.</p>
                            </div>
                            @error("field_reviews.{$fieldKey}.comment")
                              <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                          </div>
                        @endif
                      </div>
                    </div>
                  </div>
                @endif
              @endforeach
            </div>
          @else
          <dl class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach (($section['rows'] ?? []) as $row)
              <div class="field-review-card rounded-xl border border-slate-200 bg-slate-50/90 p-4 {{ !empty($row['table']) || ($row['wide'] ?? false) ? 'md:col-span-2' : '' }}">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">{{ $row['label'] }}</dt>
                <div class="mt-1.5 field-review-top-row flex flex-wrap items-start justify-between gap-3">
                  <div class="min-w-0 flex-1">
                    <dd class="whitespace-pre-line text-sm font-medium text-slate-900">{{ $row['value'] }}</dd>
                    @if (! empty($row['table']) && is_array($row['table']))
                      <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                        <table class="min-w-full divide-y divide-slate-200 text-xs">
                          <thead class="bg-slate-50 text-slate-500">
                            <tr>
                              <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide">Material</th>
                              <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide">Quantity</th>
                              <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide">Unit Price</th>
                              <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide">Price</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ($row['table'] as $tableRow)
                              <tr>
                                <td class="px-3 py-2">{{ $tableRow['material'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $tableRow['quantity'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $tableRow['unit_price'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $tableRow['price'] ?? '—' }}</td>
                              </tr>
                            @endforeach
                          </tbody>
                        </table>
                      </div>
                    @endif
                    @if (! empty($row['link_url']))
                      <a href="{{ $row['link_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex text-xs font-semibold text-[#003E9F] hover:text-[#00327F]">
                        Open / Download file
                        <span class="ml-1 text-slate-400" aria-hidden="true">↗</span>
                      </a>
                    @endif
                    @include('approver.partials.proposal-revision-diff', ['row' => $row])
                  </div>
                @if (($isProposalReview ?? false) && ($row['reviewable'] ?? true))
                  @php
                    $fieldKey = (string) ($row['key'] ?? '');
                    $savedStatus = (string) ($row['review']['status'] ?? '');
                    $fieldStatus = old("field_reviews.{$fieldKey}.status", $savedStatus === 'approved' ? 'passed' : ($savedStatus === 'revision' ? 'revision' : 'pending'));
                    if (! in_array($fieldStatus, ['pending', 'passed', 'revision'], true)) {
                      $fieldStatus = 'pending';
                    }
                    $fieldComment = old("field_reviews.{$fieldKey}.comment", $row['review']['comment'] ?? '');
                  @endphp
                  <div class="field-review-control shrink-0" data-field-review data-section-key="section_{{ $loop->parent->index }}" data-field-key="{{ $fieldKey }}" data-field-label="{{ $row['label'] }}">
                    <input type="hidden" name="field_reviews[{{ $fieldKey }}][label]" value="{{ $row['label'] }}" form="proposal-field-review-form" />
                    <div class="inline-flex flex-wrap items-center gap-1 rounded-lg border border-slate-200 bg-white p-1" role="group" aria-label="Field review for {{ $row['label'] }}">
                      <button type="button" class="field-review-btn rounded-md px-2.5 py-1 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-50 {{ $fieldStatus === 'passed' ? 'ring-2 ring-emerald-400' : '' }}" data-status-value="passed" aria-pressed="{{ $fieldStatus === 'passed' ? 'true' : 'false' }}">Passed</button>
                      <button type="button" class="field-review-btn rounded-md px-2.5 py-1 text-[11px] font-semibold text-amber-700 transition hover:bg-amber-50 {{ $fieldStatus === 'revision' ? 'ring-2 ring-amber-400' : '' }}" data-status-value="revision" aria-pressed="{{ $fieldStatus === 'revision' ? 'true' : 'false' }}">Revision</button>
                    </div>
                    <input type="hidden" class="field-review-status" name="field_reviews[{{ $fieldKey }}][status]" value="{{ $fieldStatus }}" form="proposal-field-review-form" />
                    <div class="field-review-note mt-2 {{ $fieldStatus === 'revision' ? '' : 'hidden' }}">
                      <x-forms.textarea class="field-review-note-input" name="field_reviews[{{ $fieldKey }}][comment]" form="proposal-field-review-form" :rows="2" placeholder="Add revision note for this field...">{{ $fieldComment }}</x-forms.textarea>
                      <p class="mt-1 text-xs text-slate-500">Required when this field needs revision.</p>
                      <p class="field-review-note-error mt-1 hidden text-xs font-medium text-rose-700">Revision note is required.</p>
                    </div>
                    @error("field_reviews.{$fieldKey}.comment")
                      <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                  </div>
                @endif
                </div>
              </div>
            @endforeach
          </dl>
          @endif
        </section>
      @endforeach
    </div>
  @endif

  @if (!empty($proposalFileLinks) && !($isProposalReview ?? false))
    <div class="mt-6 border-t border-slate-100 pt-6">
      <h2 class="text-base font-bold text-slate-900">Submitted files</h2>
      <p class="mt-1 text-xs text-slate-500">Open or download attached proposal files for review.</p>
      <ul class="mt-4 space-y-2">
        @foreach ($proposalFileLinks as $file)
          <li>
            <a href="{{ $file['url'] }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-[#003E9F] hover:text-[#00327F]">
              {{ $file['label'] }}
              <span class="text-slate-400" aria-hidden="true">↗</span>
            </a>
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  @if (($calendarEntries?->count() ?? 0) > 0)
    <div class="mt-8 border-t border-slate-100 pt-8">
      <h2 class="text-base font-bold text-slate-900">Submitted activities</h2>
      <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
          <thead class="bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3">Date</th>
              <th class="px-5 py-3">Activity</th>
              <th class="px-5 py-3">SDG</th>
              <th class="px-5 py-3">Venue</th>
              <th class="px-5 py-3">Participants / Program</th>
              <th class="px-5 py-3">Budget</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            @foreach ($calendarEntries as $row)
              <tr>
                <td class="px-5 py-3">{{ optional($row->activity_date)->format('M d, Y') ?? '—' }}</td>
                <td class="px-5 py-3">{{ $row->activity_name }}</td>
                <td class="px-5 py-3">{{ $row->sdg }}</td>
                <td class="px-5 py-3">{{ $row->venue }}</td>
                <td class="px-5 py-3">{{ $row->participant_program }}</td>
                <td class="px-5 py-3">{{ $row->budget }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  <div class="mt-6 border-t border-slate-100 pt-6">
    <h2 class="text-base font-bold text-slate-900">Approval workflow</h2>
    @php
      $submittedByLabel = (string) ($details['Submitted By'] ?? 'System');
      $submittedOnLabel = (string) ($details['Submitted On'] ?? '—');
      $selectedStageId = old('workflow_stage_id', 'submitted');
      $approvableModel = $workflowCurrentStep->approvable ?? null;

      if ($approvableModel instanceof \App\Models\ActivityProposal) {
        $proposalStages = \App\Support\SubmissionRoutingProgress::stagesForActivityProposal($approvableModel);
        $workflowStageNodes = [];
        foreach ($proposalStages as $idx => $stage) {
          $state = (string) ($stage['state'] ?? 'pending');
          $label = (string) ($stage['label'] ?? '—');
          if ($idx === 0) {
            $workflowStageNodes[] = [
              'id' => 'submitted',
              'label' => $label,
              'status' => 'SUBMITTED',
              'reviewer' => $submittedByLabel,
              'acted_at' => $submittedOnLabel,
              'is_current' => $state === 'current',
              'state' => $state,
              'step_order' => 0,
            ];
            continue;
          }
          $stepRow = $workflowSteps->first(fn ($s) => (int) $s->step_order === $idx);
          $statusText = $stepRow
            ? (strtoupper((string) $stepRow->status) ?: 'PENDING')
            : match ($state) {
              'success' => 'APPROVED',
              'danger' => 'REJECTED',
              'warning' => 'REVISION_REQUIRED',
              'completed' => 'APPROVED',
              'current' => 'PENDING',
              default => 'PENDING',
            };
          $workflowStageNodes[] = [
            'id' => $stepRow ? 'step_'.$stepRow->id : ('canonical_'.$idx),
            'label' => $label,
            'status' => $statusText,
            'reviewer' => $stepRow ? (string) ($stepRow->assignedTo?->full_name ?? '—') : '—',
            'acted_at' => $stepRow ? (string) (optional($stepRow->acted_at)->format('M d, Y g:i A') ?? '—') : '—',
            'is_current' => $state === 'current',
            'state' => $state,
            'step_order' => $idx,
          ];
        }
      } else {
        $workflowStageNodes = [[
          'id' => 'submitted',
          'label' => 'Submitted',
          'status' => 'SUBMITTED',
          'reviewer' => $submittedByLabel,
          'acted_at' => $submittedOnLabel,
          'is_current' => false,
          'state' => 'completed',
          'step_order' => 0,
        ]];
        foreach ($workflowSteps as $step) {
          $statusText = strtoupper((string) $step->status);
          $isCurrent = (bool) $step->is_current_step;
          $state = match ($statusText) {
            'APPROVED' => 'completed',
            'REJECTED' => 'danger',
            'REVISION_REQUIRED' => 'warning',
            default => ($isCurrent ? 'current' : 'pending'),
          };
          $workflowStageNodes[] = [
            'id' => 'step_'.$step->id,
            'label' => (string) ($step->role?->display_name ?? $step->role?->name ?? ('Step #'.$step->step_order)),
            'status' => $statusText !== '' ? $statusText : 'PENDING',
            'reviewer' => (string) ($step->assignedTo?->full_name ?? '—'),
            'acted_at' => (string) (optional($step->acted_at)->format('M d, Y g:i A') ?? '—'),
            'is_current' => $isCurrent,
            'state' => $state,
            'step_order' => (int) $step->step_order,
          ];
        }
      }
      $hasCurrent = collect($workflowStageNodes)->contains(fn ($node) => $node['is_current']);
      if (! collect($workflowStageNodes)->contains(fn ($node) => $node['id'] === $selectedStageId)) {
        $selectedStageId = $hasCurrent
          ? (string) collect($workflowStageNodes)->first(fn ($node) => $node['is_current'])['id']
          : 'submitted';
      }
    @endphp

    <div class="mt-4 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:thin] [&::-webkit-scrollbar]:h-1 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-200">
      <div class="flex min-w-[52rem] items-start">
        @foreach ($workflowStageNodes as $node)
          @php
            $nodeState = (string) $node['state'];
            $isSelected = $selectedStageId === $node['id'];
            $circleClass = match ($nodeState) {
              'completed', 'success' => 'border-emerald-500 bg-emerald-500 text-white',
              'current' => 'border-[#003E9F] bg-[#003E9F]/10 text-[#003E9F]',
              'warning' => 'border-orange-400 bg-orange-50 text-orange-700',
              'danger' => 'border-rose-400 bg-rose-50 text-rose-700',
              default => 'border-slate-300 bg-slate-50 text-slate-500',
            };
            $lineClass = in_array($nodeState, ['completed', 'current', 'success'], true) ? 'bg-emerald-300' : 'bg-slate-200';
          @endphp
          <div class="flex min-w-0 flex-1 flex-col items-center px-1">
            <button
              type="button"
              class="workflow-stage-btn group flex w-full flex-col items-center"
              data-stage-id="{{ $node['id'] }}"
              aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
            >
              <span class="flex h-9 w-9 items-center justify-center rounded-full border text-xs font-bold shadow-sm transition {{ $circleClass }} {{ $isSelected ? 'ring-2 ring-[#003E9F]/25' : '' }}">
                @if ($nodeState === 'completed' || $nodeState === 'success')
                  ✓
                @elseif ($nodeState === 'current')
                  <span class="h-2.5 w-2.5 rounded-full bg-[#003E9F]"></span>
                @else
                  •
                @endif
              </span>
              <span class="mt-2 text-center text-[11px] font-semibold leading-tight {{ $isSelected ? 'text-[#003E9F]' : 'text-slate-600' }}">{{ $node['label'] }}</span>
            </button>
          </div>
          @if (! $loop->last)
            <div class="mt-4 flex min-w-[1.3rem] flex-[1.2] basis-0 items-center" aria-hidden="true">
              <div class="h-0.5 min-w-[8px] flex-1 rounded-full {{ $lineClass }}"></div>
              <svg class="ml-px h-3 w-3 shrink-0 {{ $lineClass === 'bg-emerald-300' ? 'text-emerald-500' : 'text-slate-300' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
              </svg>
            </div>
          @endif
        @endforeach
      </div>
    </div>

    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 sm:p-5" id="workflow-stage-detail-card">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500" id="workflow-stage-detail-label">Selected Stage</p>
      <p class="mt-1 text-sm font-bold text-slate-900" id="workflow-stage-detail-name">—</p>
      <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div>
          <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Status</dt>
          <dd class="mt-1 text-sm font-medium text-slate-900" id="workflow-stage-detail-status">—</dd>
        </div>
        <div>
          <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Reviewer</dt>
          <dd class="mt-1 text-sm font-medium text-slate-900" id="workflow-stage-detail-reviewer">—</dd>
        </div>
        <div>
          <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Acted At</dt>
          <dd class="mt-1 text-sm font-medium text-slate-900" id="workflow-stage-detail-acted-at">—</dd>
        </div>
      </dl>
    </div>

    <form id="proposal-field-review-form" method="POST" action="{{ $workflowActionRoute }}" class="mt-5 space-y-4">
      @csrf
      @method('PATCH')
      @if ($isProposalReview ?? false)
        <input type="hidden" name="action" value="approve" />
        <div id="revision-summary-box" class="rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-4 text-sm text-amber-900">
          <p id="revision-summary-title" class="text-sm font-bold uppercase tracking-widest text-amber-900">Revision Summary</p>
          <p id="revision-summary-helper" class="mt-1 text-xs text-amber-800/90">Click a revision item below to jump to the field that needs updates.</p>
          <ul id="revision-summary-list" class="mt-3 space-y-3"></ul>
        </div>
        @error('field_reviews')
          <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
            {{ $message }}
          </div>
        @enderror
        <div class="flex flex-wrap gap-2">
          <button id="save-review-btn" type="submit" class="inline-flex rounded-xl bg-[#003E9F] px-4 py-2 text-sm font-semibold text-white hover:bg-[#00327F] disabled:cursor-not-allowed disabled:opacity-60">Submit Field Review</button>
        </div>
        <p id="save-review-helper" class="text-xs text-slate-500"></p>
      @else
        <div>
          <x-forms.label for="workflow_comments">Comments (optional)</x-forms.label>
          <x-forms.textarea id="workflow_comments" name="comments" rows="3" placeholder="Add rationale for your decision...">{{ old('comments') }}</x-forms.textarea>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="submit" name="action" value="approve" class="inline-flex rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve</button>
          <button type="submit" name="action" value="revision" class="inline-flex rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600">Request revision</button>
          <button type="submit" name="action" value="reject" class="inline-flex rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Reject</button>
        </div>
      @endif
    </form>

    @if (($workflowLogs?->count() ?? 0) > 0)
      <div class="mt-6">
        <h3 class="text-sm font-semibold text-slate-900">Recent workflow logs</h3>
        <ul class="mt-2 space-y-2 text-sm">
          @foreach ($workflowLogs as $log)
            <li class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
              <span class="font-semibold">{{ strtoupper((string) $log->action) }}</span>
              · {{ $log->from_status ?? '—' }} → {{ $log->to_status ?? '—' }}
              · {{ $log->actor?->full_name ?? 'System' }}
              · {{ optional($log->created_at)->format('M d, Y g:i A') ?? '—' }}
              @if ($log->comments)
                <div class="mt-1 text-slate-700">{{ $log->comments }}</div>
              @endif
            </li>
          @endforeach
        </ul>
      </div>
    @endif
  </div>
</x-ui.card>
<script id="approver-workflow-stage-nodes" type="application/json">@json($workflowStageNodes)</script>
<script id="approver-workflow-selected-stage" type="application/json">@json($selectedStageId)</script>
<script>
  (() => {
    const stageNodesEl = document.getElementById('approver-workflow-stage-nodes');
    const selectedStageEl = document.getElementById('approver-workflow-selected-stage');
    const stageNodes = stageNodesEl ? JSON.parse(stageNodesEl.textContent || '[]') : [];
    const selectedStageId = selectedStageEl ? JSON.parse(selectedStageEl.textContent || '"submitted"') : 'submitted';
    const detailName = document.getElementById('workflow-stage-detail-name');
    const detailStatus = document.getElementById('workflow-stage-detail-status');
    const detailReviewer = document.getElementById('workflow-stage-detail-reviewer');
    const detailActedAt = document.getElementById('workflow-stage-detail-acted-at');
    const stageButtons = Array.from(document.querySelectorAll('.workflow-stage-btn'));

    const renderStage = (stageId) => {
      const stage = stageNodes.find((node) => node.id === stageId) || stageNodes[0];
      if (!stage || !detailName || !detailStatus || !detailReviewer || !detailActedAt) return;

      detailName.textContent = stage.label || '—';
      detailStatus.textContent = stage.status || '—';
      detailReviewer.textContent = stage.reviewer || '—';
      detailActedAt.textContent = stage.acted_at || '—';

      stageButtons.forEach((btn) => {
        const isActive = btn.getAttribute('data-stage-id') === stage.id;
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        const label = btn.querySelector('span:last-child');
        const circle = btn.querySelector('span:first-child');
        if (label) {
          label.classList.toggle('text-[#003E9F]', isActive);
          label.classList.toggle('text-slate-600', !isActive);
        }
        if (circle) {
          circle.classList.toggle('ring-2', isActive);
          circle.classList.toggle('ring-[#003E9F]/25', isActive);
        }
      });
    };

    stageButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        renderStage(btn.getAttribute('data-stage-id') || '');
      });
    });

    renderStage(selectedStageId);
  })();
</script>
@if ($isProposalReview ?? false)
<script>
  (() => {
    const statusBadge = document.getElementById('proposal-stage-status-badge');
    const progressEl = document.getElementById('proposal-review-progress');
    const saveReviewBtn = document.getElementById('save-review-btn');
    const saveReviewHelper = document.getElementById('save-review-helper');
    const revisionSummaryBox = document.getElementById('revision-summary-box');
    const revisionSummaryTitle = document.getElementById('revision-summary-title');
    const revisionSummaryHelper = document.getElementById('revision-summary-helper');
    const revisionSummaryList = document.getElementById('revision-summary-list');
    const reviewForm = document.getElementById('proposal-field-review-form');
    if (!statusBadge || !progressEl || !saveReviewBtn || !saveReviewHelper || !revisionSummaryBox || !revisionSummaryHelper || !revisionSummaryList || !reviewForm) return;

    const normalizeStatus = (status) => {
      const value = String(status || 'pending');
      if (value === 'approved') return 'passed';
      if (value === 'passed' || value === 'revision' || value === 'pending') return value;
      return 'pending';
    };

    const allSectionRoots = () => Array.from(document.querySelectorAll('[data-review-section-card="1"]'));
    const getFieldControls = (sectionKey) => Array.from(document.querySelectorAll(`[data-field-review][data-section-key="${sectionKey}"]`));
    const statusClassMap = {
      PENDING: 'bg-amber-100 text-amber-800 border border-amber-200',
      APPROVED: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
      REVISION_REQUIRED: 'bg-orange-100 text-orange-700 border border-orange-200',
    };
    const knownStatusClasses = Array.from(new Set(Object.values(statusClassMap).join(' ').split(' ')));

    function applyRevisionSummaryState(state) {
      if (!revisionSummaryTitle) return;
      if (state === 'success') {
        revisionSummaryBox.className = 'rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900';
        revisionSummaryTitle.className = 'text-sm font-bold uppercase tracking-widest text-emerald-900';
        revisionSummaryHelper.className = 'mt-1 text-xs text-emerald-800/90';
        return;
      }
      if (state === 'pending') {
        revisionSummaryBox.className = 'rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-800';
        revisionSummaryTitle.className = 'text-sm font-bold uppercase tracking-widest text-slate-800';
        revisionSummaryHelper.className = 'mt-1 text-xs text-slate-600';
        return;
      }
      revisionSummaryBox.className = 'rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-4 text-sm text-amber-900';
      revisionSummaryTitle.className = 'text-sm font-bold uppercase tracking-widest text-amber-900';
      revisionSummaryHelper.className = 'mt-1 text-xs text-amber-800/90';
    }

    function syncFieldControl(control) {
      const statusInput = control.querySelector('.field-review-status');
      const noteWrap = control.dataset.noteWrapId
        ? document.getElementById(control.dataset.noteWrapId)
        : control.querySelector('.field-review-note');
      const noteInput = control.dataset.noteInputId
        ? document.getElementById(control.dataset.noteInputId)
        : control.querySelector('.field-review-note-input');
      const noteError = noteWrap?.querySelector('.field-review-note-error');
      const status = normalizeStatus(statusInput?.value || 'pending');
      if (statusInput) statusInput.value = status;
      control.querySelectorAll('.field-review-btn').forEach((btn) => {
        const active = btn.dataset.statusValue === status;
        btn.classList.toggle('ring-2', active);
        btn.classList.toggle('ring-emerald-400', active && status === 'passed');
        btn.classList.toggle('ring-amber-400', active && status === 'revision');
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      if (noteWrap) noteWrap.classList.toggle('hidden', status !== 'revision');
      const hasNote = (noteInput?.value || '').trim() !== '';
      if (noteError) noteError.classList.toggle('hidden', status !== 'revision' || hasNote);
      if (status !== 'revision' && noteInput) noteInput.value = '';
    }

    function computeSection(sectionKey) {
      const controls = getFieldControls(sectionKey);
      let pending = 0;
      let revision = 0;
      let invalidRevision = 0;
      controls.forEach((control) => {
        const status = normalizeStatus(control.querySelector('.field-review-status')?.value || 'pending');
        if (status === 'pending') pending += 1;
        if (status === 'revision') {
          revision += 1;
          const noteInput = control.dataset.noteInputId
            ? document.getElementById(control.dataset.noteInputId)
            : control.querySelector('.field-review-note-input');
          if ((noteInput?.value || '').trim() === '') invalidRevision += 1;
        }
      });
      return { pending, revision, invalidRevision, total: controls.length };
    }

    function applySectionBadge(sectionKey, status) {
      const badge = document.querySelector(`[data-section-status-badge="${sectionKey}"]`);
      if (!badge) return;
      if (status === 'verified') {
        badge.className = 'inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700';
        badge.textContent = 'Verified';
      } else if (status === 'needs_revision') {
        badge.className = 'inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700';
        badge.textContent = 'Needs Revision';
      } else {
        badge.className = 'inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700';
        badge.textContent = 'Pending';
      }
    }

    function refreshSummaryAndBadge() {
      let verified = 0;
      let needsRevision = 0;
      let pending = 0;
      allSectionRoots().forEach((sectionRoot) => {
        const sectionKey = sectionRoot.dataset.sectionKey || '';
        const stats = computeSection(sectionKey);
        const status = stats.pending > 0 ? 'pending' : (stats.revision > 0 ? 'needs_revision' : 'verified');
        applySectionBadge(sectionKey, status);
        if (status === 'verified') verified += 1;
        else if (status === 'needs_revision') needsRevision += 1;
        else pending += 1;
      });
      progressEl.querySelector('[data-progress="verified"]').textContent = `Verified: ${verified}`;
      progressEl.querySelector('[data-progress="needs_revision"]').textContent = `Needs Revision: ${needsRevision}`;
      progressEl.querySelector('[data-progress="pending"]').textContent = `Pending: ${pending}`;
      if (pending > 0) {
        statusBadge.textContent = 'PENDING';
      } else if (needsRevision > 0) {
        statusBadge.textContent = 'REVISION_REQUIRED';
      } else {
        statusBadge.textContent = 'APPROVED';
      }
      const nextStatus = statusBadge.textContent?.trim() || 'PENDING';
      knownStatusClasses.forEach((className) => statusBadge.classList.remove(className));
      (statusClassMap[nextStatus] || statusClassMap.PENDING).split(' ').forEach((className) => statusBadge.classList.add(className));
    }

    function refreshRevisionSummary() {
      const rows = [];
      const sectionRoots = allSectionRoots();
      const pendingSections = sectionRoots.filter((sectionRoot) => {
        const sectionKey = sectionRoot.dataset.sectionKey || '';
        const stats = computeSection(sectionKey);
        return stats.pending > 0;
      }).length;
      const allVerified = sectionRoots.length > 0 && sectionRoots.every((sectionRoot) => {
        const sectionKey = sectionRoot.dataset.sectionKey || '';
        const stats = computeSection(sectionKey);
        return stats.pending === 0 && stats.revision === 0;
      });
      document.querySelectorAll('[data-field-review]').forEach((control) => {
        const status = normalizeStatus(control.querySelector('.field-review-status')?.value || 'pending');
        if (status !== 'revision') return;
        const sectionRoot = control.closest('[data-review-section-card="1"]');
        const section = sectionRoot?.querySelector('h2')?.textContent || 'Section';
        const field = control.dataset.fieldLabel || 'Field';
        const noteInput = control.dataset.noteInputId
          ? document.getElementById(control.dataset.noteInputId)
          : control.querySelector('.field-review-note-input');
        const note = (noteInput?.value || '').trim();
        const target = control.closest('.field-review-card');
        if (target && !target.id) target.id = `review-target-${control.dataset.fieldKey || 'field'}`;
        rows.push({ section, field, note: note || 'No note provided yet.', targetId: target?.id || '' });
      });

      if (pendingSections > 0) {
        applyRevisionSummaryState('pending');
        revisionSummaryHelper.textContent = 'Complete all pending fields first, then submit the review.';
        revisionSummaryList.innerHTML = `<li class="text-sm text-slate-700">${pendingSections} section(s) pending. Complete all field reviews first.</li>`;
        return;
      }

      if (rows.length === 0 && allVerified) {
        applyRevisionSummaryState('success');
        revisionSummaryHelper.textContent = 'Every section is fully reviewed and verified.';
        revisionSummaryList.innerHTML = '<li class="text-sm text-emerald-800">All sections are verified. No revision notes required. This submission is ready for finalization.</li>';
        return;
      }

      applyRevisionSummaryState('revision');
      revisionSummaryHelper.textContent = 'Click a revision item below to jump to the field that needs updates.';
      revisionSummaryList.innerHTML = '';
      const grouped = {};
      rows.forEach((row) => {
        grouped[row.section] = grouped[row.section] || [];
        grouped[row.section].push(row);
      });
      Object.entries(grouped).forEach(([section, sectionRows]) => {
        const item = document.createElement('li');
        item.className = 'rounded-xl border border-amber-200/70 bg-white/70 px-3 py-2.5';
        item.innerHTML = `<p class="font-semibold text-amber-900">${section} (${sectionRows.length})</p>`;
        const list = document.createElement('ul');
        list.className = 'mt-1.5 space-y-1.5';
        sectionRows.forEach((row) => {
          const li = document.createElement('li');
          li.innerHTML = `<button type="button" class="inline-flex w-full items-start gap-2 rounded-lg px-2 py-1.5 text-left text-xs text-amber-900/95 transition hover:bg-amber-100/70 focus:outline-none focus:ring-2 focus:ring-amber-400/50" data-target-id="${row.targetId}"><span class="font-semibold underline underline-offset-2">${row.field}</span><span>- ${row.note}</span></button>`;
          list.appendChild(li);
        });
        item.appendChild(list);
        revisionSummaryList.appendChild(item);
      });
    }

    function updateSaveState() {
      let hasPending = false;
      let hasMissingNote = false;
      allSectionRoots().forEach((sectionRoot) => {
        const stats = computeSection(sectionRoot.dataset.sectionKey || '');
        if (stats.pending > 0) hasPending = true;
        if (stats.invalidRevision > 0) hasMissingNote = true;
      });
      saveReviewBtn.disabled = hasPending || hasMissingNote;
      if (hasPending) {
        saveReviewHelper.textContent = 'All fields must be reviewed before submitting.';
      } else if (hasMissingNote) {
        saveReviewHelper.textContent = 'Revision note is required for each field marked Revision.';
      } else {
        saveReviewHelper.textContent = '';
      }
    }

    document.querySelectorAll('[data-field-review]').forEach((control) => {
      const parentCard = control.closest('.field-review-card');
      const noteWrap = control.querySelector('.field-review-note');
      const noteInput = control.querySelector('.field-review-note-input');
      if (parentCard && noteWrap) {
        const noteWrapId = `field-review-note-${control.dataset.sectionKey || 'section'}-${control.dataset.fieldKey || 'field'}`;
        noteWrap.id = noteWrapId;
        control.dataset.noteWrapId = noteWrapId;
        if (noteInput) {
          const noteInputId = `${noteWrapId}-input`;
          noteInput.id = noteInputId;
          control.dataset.noteInputId = noteInputId;
        }
        noteWrap.classList.add('w-full', 'border-t', 'border-slate-200/70', 'pt-2.5');
        parentCard.appendChild(noteWrap);
      }

      syncFieldControl(control);
      control.querySelectorAll('.field-review-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          const statusInput = control.querySelector('.field-review-status');
          if (!statusInput) return;
          statusInput.value = btn.dataset.statusValue || 'pending';
          syncFieldControl(control);
          refreshSummaryAndBadge();
          refreshRevisionSummary();
          updateSaveState();
        });
      });
      const mappedNoteInput = control.dataset.noteInputId
        ? document.getElementById(control.dataset.noteInputId)
        : control.querySelector('.field-review-note-input');
      mappedNoteInput?.addEventListener('input', () => {
        syncFieldControl(control);
        refreshSummaryAndBadge();
        refreshRevisionSummary();
        updateSaveState();
      });
    });

    revisionSummaryList.addEventListener('click', (event) => {
      const action = event.target.closest('button[data-target-id]');
      if (!action) return;
      const target = document.getElementById(action.dataset.targetId || '');
      if (!target) return;
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      target.classList.add('ring-2', 'ring-amber-300', 'bg-amber-50/80');
      window.setTimeout(() => target.classList.remove('ring-2', 'ring-amber-300', 'bg-amber-50/80'), 1800);
    });

    reviewForm.addEventListener('submit', (e) => {
      updateSaveState();
      if (saveReviewBtn.disabled) {
        e.preventDefault();
      }
    });

    refreshSummaryAndBadge();
    refreshRevisionSummary();
    updateSaveState();
  })();
</script>
@endif
@endsection

