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

<x-ui.card padding="p-6">
  <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
    @foreach ($details as $label => $value)
      <div class="rounded-2xl border border-slate-200 bg-slate-50/90 p-5">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">{{ $label }}</dt>
        <dd class="mt-2 text-sm font-medium text-slate-900">{{ $value }}</dd>
      </div>
    @endforeach
  </dl>

  @if (!empty($detailSections))
    <div class="mt-8 space-y-8 border-t border-slate-100 pt-8">
      @foreach ($detailSections as $section)
        <section>
          <h2 class="text-base font-bold text-slate-900">{{ $section['title'] }}</h2>
          <dl class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach (($section['rows'] ?? []) as $row)
              <div class="rounded-2xl border border-slate-200 bg-slate-50/90 p-5">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-700">{{ $row['label'] }}</dt>
                <dd class="mt-2 whitespace-pre-line text-sm font-medium text-slate-900">{{ $row['value'] }}</dd>
                @if (! empty($row['link_url']))
                  <a href="{{ $row['link_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex text-xs font-semibold text-[#003E9F] hover:text-[#00327F]">
                    Open / Download file
                    <span class="ml-1 text-slate-400" aria-hidden="true">↗</span>
                  </a>
                @endif
                @if ($isProposalReview ?? false)
                  @php
                    $fieldKey = (string) ($row['key'] ?? '');
                    $fieldStatus = old("field_reviews.{$fieldKey}.status", $row['review']['status'] ?? '');
                    $fieldComment = old("field_reviews.{$fieldKey}.comment", $row['review']['comment'] ?? '');
                  @endphp
                  <div class="mt-4 rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Field review</p>
                    <input type="hidden" name="field_reviews[{{ $fieldKey }}][label]" value="{{ $row['label'] }}" form="proposal-field-review-form" />
                    <div class="mt-2 flex flex-wrap gap-2">
                      <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                        <input type="radio" name="field_reviews[{{ $fieldKey }}][status]" value="approved" form="proposal-field-review-form" class="h-3.5 w-3.5 border-emerald-300 text-emerald-600 focus:ring-emerald-400/30" @checked($fieldStatus === 'approved') />
                        Approved / OK
                      </label>
                      <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-orange-300 bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-700">
                        <input type="radio" name="field_reviews[{{ $fieldKey }}][status]" value="revision" form="proposal-field-review-form" class="h-3.5 w-3.5 border-orange-300 text-orange-600 focus:ring-orange-400/30" @checked($fieldStatus === 'revision') />
                        For Revision
                      </label>
                      <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
                        <input type="radio" name="field_reviews[{{ $fieldKey }}][status]" value="rejected" form="proposal-field-review-form" class="h-3.5 w-3.5 border-rose-300 text-rose-600 focus:ring-rose-400/30" @checked($fieldStatus === 'rejected') />
                        Rejected
                      </label>
                    </div>
                    <input type="hidden" name="field_reviews[{{ $fieldKey }}][comment]" value="{{ $fieldComment }}" form="proposal-field-review-form" data-field-comment-input data-field-key="{{ $fieldKey }}" />
                    <p class="mt-2 hidden text-xs text-slate-600" data-field-comment-preview data-field-key="{{ $fieldKey }}"></p>
                    @error("field_reviews.{$fieldKey}.comment")
                      <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                  </div>
                @endif
              </div>
            @endforeach
          </dl>
        </section>
      @endforeach
    </div>
  @endif

  @if (!empty($proposalFileLinks) && !($isProposalReview ?? false))
    <div class="mt-8 border-t border-slate-100 pt-8">
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
      <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200">
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

  <div class="mt-8 border-t border-slate-100 pt-8">
    <h2 class="text-base font-bold text-slate-900">Approval workflow</h2>
    @php
      $submittedByLabel = (string) ($details['Submitted By'] ?? 'System');
      $submittedOnLabel = (string) ($details['Submitted On'] ?? '—');
      $selectedStageId = old('workflow_stage_id', 'submitted');
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
              'completed' => 'border-emerald-500 bg-emerald-500 text-white',
              'current' => 'border-[#003E9F] bg-[#003E9F]/10 text-[#003E9F]',
              'warning' => 'border-orange-400 bg-orange-50 text-orange-700',
              'danger' => 'border-rose-400 bg-rose-50 text-rose-700',
              default => 'border-slate-300 bg-slate-50 text-slate-500',
            };
            $lineClass = in_array($nodeState, ['completed', 'current'], true) ? 'bg-emerald-300' : 'bg-slate-200';
          @endphp
          <div class="flex min-w-0 flex-1 flex-col items-center px-1">
            <button
              type="button"
              class="workflow-stage-btn group flex w-full flex-col items-center"
              data-stage-id="{{ $node['id'] }}"
              aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
            >
              <span class="flex h-9 w-9 items-center justify-center rounded-full border text-xs font-bold shadow-sm transition {{ $circleClass }} {{ $isSelected ? 'ring-2 ring-[#003E9F]/25' : '' }}">
                @if ($nodeState === 'completed')
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

    <form id="proposal-field-review-form" method="POST" action="{{ $workflowActionRoute }}" class="mt-6 space-y-4">
      @csrf
      @method('PATCH')
      @if ($isProposalReview ?? false)
        <input type="hidden" name="action" value="approve" />
        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-800">
          Mark each field as Approved / For Revision / Rejected. Final outcome is computed automatically from field-level statuses.
        </div>
        @error('field_reviews')
          <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-700">
            {{ $message }}
          </div>
        @enderror
        <div class="flex flex-wrap gap-2">
          <button type="submit" class="inline-flex rounded-xl bg-[#003E9F] px-4 py-2 text-sm font-semibold text-white hover:bg-[#00327F]">Submit Field Review</button>
        </div>
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
@if ($isProposalReview ?? false)
  <div id="field-comment-modal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/40 px-4">
    <div class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
      <p class="text-sm font-bold text-slate-900" id="field-comment-modal-title">Add review comment</p>
      <p class="mt-1 text-xs text-slate-600">Explain what needs to be changed or why this field is rejected.</p>
      <textarea id="field-comment-modal-input" rows="4" class="mt-3 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20" placeholder="Enter required comment..."></textarea>
      <div class="mt-4 flex justify-end gap-2">
        <button type="button" id="field-comment-modal-cancel" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
        <button type="button" id="field-comment-modal-save" class="rounded-lg bg-[#003E9F] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#00327F]">Save Comment</button>
      </div>
    </div>
  </div>
@endif
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
    const modal = document.getElementById('field-comment-modal');
    const modalTitle = document.getElementById('field-comment-modal-title');
    const modalInput = document.getElementById('field-comment-modal-input');
    const saveBtn = document.getElementById('field-comment-modal-save');
    const cancelBtn = document.getElementById('field-comment-modal-cancel');
    if (!modal || !modalTitle || !modalInput || !saveBtn || !cancelBtn) return;

    let currentFieldKey = '';
    let currentLabel = '';

    const getCommentInput = (fieldKey) => document.querySelector(`[data-field-comment-input][data-field-key="${fieldKey}"]`);
    const getCommentPreview = (fieldKey) => document.querySelector(`[data-field-comment-preview][data-field-key="${fieldKey}"]`);

    const renderCommentPreview = (fieldKey) => {
      const input = getCommentInput(fieldKey);
      const preview = getCommentPreview(fieldKey);
      if (!input || !preview) return;
      const value = (input.value || '').trim();
      preview.textContent = value ? `Comment: ${value}` : '';
      preview.classList.toggle('hidden', value.length === 0);
    };

    const openModal = (fieldKey, label) => {
      currentFieldKey = fieldKey;
      currentLabel = label;
      modalTitle.textContent = `Comment for: ${label}`;
      const currentInput = getCommentInput(fieldKey);
      modalInput.value = currentInput ? currentInput.value : '';
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      modalInput.focus();
    };

    const closeModal = () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      currentFieldKey = '';
      currentLabel = '';
    };

    saveBtn.addEventListener('click', () => {
      const input = getCommentInput(currentFieldKey);
      if (!input) {
        closeModal();
        return;
      }
      const value = modalInput.value.trim();
      if (value.length === 0) {
        modalInput.focus();
        return;
      }
      input.value = value;
      renderCommentPreview(currentFieldKey);
      closeModal();
    });

    cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });

    const statusRadios = Array.from(document.querySelectorAll('input[name^="field_reviews["][name$="[status]"]'));
    const statusBadge = document.getElementById('proposal-stage-status-badge');
    const statusClassMap = {
      PENDING: 'bg-amber-100 text-amber-800 border border-amber-200',
      UNDER_REVIEW: 'bg-blue-100 text-blue-700 border border-blue-200',
      REVIEWED: 'bg-blue-100 text-blue-700 border border-blue-200',
      APPROVED: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
      REJECTED: 'bg-rose-100 text-rose-700 border border-rose-200',
      REVISION: 'bg-orange-100 text-orange-700 border border-orange-200',
      REVISION_REQUIRED: 'bg-orange-100 text-orange-700 border border-orange-200',
    };
    const knownStatusClasses = Array.from(new Set(Object.values(statusClassMap).join(' ').split(' ')));

    const computeLiveStatus = () => {
      const grouped = new Map();
      statusRadios.forEach((radio) => {
        if (!grouped.has(radio.name)) grouped.set(radio.name, null);
        if (radio.checked) grouped.set(radio.name, radio.value);
      });
      const values = Array.from(grouped.values()).filter((value) => typeof value === 'string');
      if (values.length < grouped.size) return 'PENDING';
      if (values.includes('rejected')) return 'REJECTED';
      if (values.includes('revision')) return 'REVISION_REQUIRED';
      return 'APPROVED';
    };

    const renderLiveStatusBadge = () => {
      if (!statusBadge) return;
      const nextStatus = computeLiveStatus();
      statusBadge.textContent = nextStatus;
      statusBadge.dataset.status = nextStatus;
      knownStatusClasses.forEach((className) => statusBadge.classList.remove(className));
      (statusClassMap[nextStatus] || statusClassMap.PENDING).split(' ').forEach((className) => statusBadge.classList.add(className));
    };

    statusRadios.forEach((radio) => {
      const fieldKey = radio.name.replace(/^field_reviews\[/, '').replace(/\]\[status\]$/, '');
      renderCommentPreview(fieldKey);
      radio.addEventListener('change', () => {
        const labelInput = document.querySelector(`input[name="field_reviews[${fieldKey}][label]"]`);
        const label = labelInput ? labelInput.value : fieldKey;
        if (radio.value === 'revision' || radio.value === 'rejected') {
          openModal(fieldKey, label);
        } else {
          const commentInput = getCommentInput(fieldKey);
          if (commentInput) commentInput.value = '';
          renderCommentPreview(fieldKey);
        }
        renderLiveStatusBadge();
      });
    });
    renderLiveStatusBadge();

    const reviewForm = document.querySelector(`form[action="{{ $workflowActionRoute }}"]`);
    if (reviewForm) {
      reviewForm.addEventListener('submit', (e) => {
        const unresolved = Array.from(document.querySelectorAll('input[name^="field_reviews["][name$="[status]"]:checked'))
          .some((checked) => {
            if (checked.value === 'approved') return false;
            const fieldKey = checked.name.replace(/^field_reviews\[/, '').replace(/\]\[status\]$/, '');
            const commentInput = getCommentInput(fieldKey);
            return !commentInput || commentInput.value.trim().length === 0;
          });
        if (unresolved) {
          e.preventDefault();
        }
      });
    }
  })();
</script>
@endif
@endsection

