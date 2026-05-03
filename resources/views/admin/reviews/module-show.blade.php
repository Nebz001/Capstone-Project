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
  $persistedFieldReviews = is_array($persistedFieldReviews ?? null) ? $persistedFieldReviews : [];
  $persistedSectionReviews = is_array($persistedSectionReviews ?? null) ? $persistedSectionReviews : [];
  $statusBadge = static function (string $status): array {
    return match ($status) {
      'verified' => ['label' => 'Verified', 'class' => 'border border-emerald-200 bg-emerald-50 text-emerald-700'],
      'needs_revision' => ['label' => 'Needs Revision', 'class' => 'border border-amber-200 bg-amber-50 text-amber-700'],
      default => ['label' => 'Pending', 'class' => 'border border-slate-200 bg-slate-100 text-slate-700'],
    };
  };
  $moduleReviewBlockType = (string) session('review_block_type', '');
  $moduleReviewBlockTitle = (string) session('review_block_title', '');
  $moduleReviewBlockMessage = (string) session('review_block_message', '');
  $moduleReviewBlockBackUrl = session('review_block_back_url');
  $moduleReviewBlockBackLabel = (string) session('review_block_back_label', '');
  $hasModuleReviewOutcomeFlash = $moduleReviewBlockTitle !== '' || $moduleReviewBlockMessage !== '';
  $moduleReviewBlockVariant = match ($moduleReviewBlockType) {
    'success' => 'success',
    'warning', 'pending' => 'warning',
    'error' => 'error',
    default => 'info',
  };
  $moduleApprovedBanner = match ($moduleLabel ?? '') {
    'Renewal' => [
      'title' => 'Organization renewal has been approved',
      'body' => 'The organization renewal submission has been approved successfully.',
      'back_label' => 'Back to Renewals',
    ],
    'Activity Calendar' => [
      'title' => 'Activity calendar has been approved',
      'body' => 'The activity calendar submission has been approved successfully.',
      'back_label' => 'Back to Activity Calendars',
    ],
    'Activity Proposal' => [
      'title' => 'Activity proposal has been approved',
      'body' => 'The activity proposal submission has been approved successfully.',
      'back_label' => 'Back to Activity Proposals',
    ],
    'After Activity Report' => [
      'title' => 'After activity report has been approved',
      'body' => 'The after activity report submission has been approved successfully.',
      'back_label' => 'Back to After Activity Reports',
    ],
    default => null,
  };
@endphp

@if ($hasModuleReviewOutcomeFlash)
  <x-feedback.blocked-message variant="{{ $moduleReviewBlockVariant }}" class="mb-6 items-start">
    <p class="font-semibold">{{ $moduleReviewBlockTitle }}</p>
    <p class="mt-1 text-sm font-normal">{{ $moduleReviewBlockMessage }}</p>
    <p class="mt-2">
      <a
        href="{{ is_string($moduleReviewBlockBackUrl) && $moduleReviewBlockBackUrl !== '' ? $moduleReviewBlockBackUrl : ($backRoute ?? '#') }}"
        class="text-sm font-semibold underline underline-offset-2 transition"
      >
        {{ $moduleReviewBlockBackLabel !== '' ? $moduleReviewBlockBackLabel : ($backLabel ?? 'Back') }}
      </a>
    </p>
  </x-feedback.blocked-message>
@elseif (($status ?? '') === 'APPROVED' && is_array($moduleApprovedBanner))
  <x-feedback.blocked-message variant="success" class="mb-6 items-start">
    <p class="font-semibold">{{ $moduleApprovedBanner['title'] }}</p>
    <p class="mt-1 text-sm font-normal">{{ $moduleApprovedBanner['body'] }}</p>
    <p class="mt-2">
      <a
        href="{{ $backRoute ?? '#' }}"
        class="text-sm font-semibold text-emerald-800 underline decoration-emerald-700/60 underline-offset-2 transition hover:text-emerald-900 hover:decoration-emerald-900"
      >
        {{ $moduleApprovedBanner['back_label'] }}
      </a>
    </p>
  </x-feedback.blocked-message>
@endif

<form
  id="module-review-form"
  method="POST"
  action="{{ $saveRoute }}"
  class="space-y-4"
  data-confirmed="0"
  data-review-draft-url="{{ $draftRoute }}"
>
  @csrf
  @method('PATCH')
  @error('adviser')
    <x-feedback.blocked-message variant="error" :message="$message" />
  @enderror

  @php
    $updatedInformationGroups = is_array($updatedInformationGroups ?? null) ? $updatedInformationGroups : [];
  @endphp
  @if (($moduleLabel ?? '') === 'Activity Calendar' && $updatedInformationGroups !== [])
    <x-feedback.blocked-message variant="info" :icon="false" class="mb-6">
      <div class="flex items-start gap-3">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-100/90" aria-hidden="true">
          <svg class="h-4.5 w-4.5 text-sky-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25 11.25 16.5M12 7.5h.008v.008H12V7.5Zm0 13.5c-4.97 0-9-4.03-9-9s4.03-9 9-9 9 4.03 9 9-4.03 9-9 9Z" />
          </svg>
        </div>
        <div class="min-w-0">
          <p class="font-semibold">Updated information submitted</p>
          <p class="mt-1 text-sm font-normal">The organization officer has updated the fields below. Review each updated field and provide a new response.</p>
        </div>
      </div>
      <div class="mt-4 space-y-3">
        @foreach ($updatedInformationGroups as $group)
          <div class="rounded-lg border border-sky-200/90 bg-white/60 px-3 py-3">
            <p class="text-xs font-bold uppercase tracking-wide text-sky-950">{{ $group['label'] }} ({{ count($group['fields']) }})</p>
            <ul class="mt-2 space-y-1.5">
              @foreach ($group['fields'] as $uf)
                <li>
                  <button
                    type="button"
                    class="updated-summary-link inline-flex w-full items-start gap-1 rounded-md px-2 py-1 text-left text-xs text-sky-950 transition hover:bg-sky-100/70 focus:outline-none focus:ring-2 focus:ring-sky-400/60"
                    data-scroll-target="{{ $uf['anchor'] }}"
                  >
                    <span class="font-semibold underline underline-offset-2">{{ $uf['label'] }}</span>
                  </button>
                </li>
              @endforeach
            </ul>
          </div>
        @endforeach
      </div>
    </x-feedback.blocked-message>
  @endif

  <x-ui.card padding="p-5" class="border border-slate-200 bg-slate-50/70">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm font-semibold text-slate-900">{{ $moduleLabel }} review progress</p>
      </div>
      <div id="module-review-progress" class="inline-flex flex-wrap items-center gap-2 text-xs font-semibold">
        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-emerald-700" data-progress="verified">Verified: 0</span>
        <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-700" data-progress="needs_revision">Needs Revision: 0</span>
        <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-slate-700" data-progress="pending">Pending: 0</span>
      </div>
    </div>
  </x-ui.card>

  @php
    $fieldUpdateDiffs = is_array($fieldUpdateDiffs ?? null) ? $fieldUpdateDiffs : [];
    $fieldUpdateFor = static fn (string $sectionKey, string $fieldKey): ?array => data_get($fieldUpdateDiffs, $sectionKey.'.'.$fieldKey);
  @endphp
  @foreach ($sections as $section)
    @php
      $sectionReviewable = (bool) ($section['reviewable'] ?? true);
      $badge = $statusBadge((string) (data_get($persistedSectionReviews, ($section['key'] ?? '').'.status', 'pending')));
      $readonlyItemClass = 'field-review-card rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3.5';
      $readonlyLabelClass = 'text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500';
      $isRequirementsListLayout = (($moduleLabel ?? '') === 'Renewal' && ($section['key'] ?? '') === 'requirements')
        || (($moduleLabel ?? '') === 'Activity Calendar' && ($section['key'] ?? '') === 'submitted_files')
        || (($section['is_requirements_attached'] ?? false) === true);
      $synthReqBadge = null;
      if (($section['is_requirements_attached'] ?? false) && ! $sectionReviewable) {
        $reviewableReqFields = collect($section['fields'] ?? [])->filter(fn ($f) => (bool) ($f['show_review_controls'] ?? true));
        $reqStatuses = $reviewableReqFields->map(function ($f) use ($persistedFieldReviews) {
          $sk = (string) ($f['review_section_key'] ?? '');
          $fk = (string) ($f['review_field_key'] ?? $f['key'] ?? '');

          return (string) data_get($persistedFieldReviews, $sk.'.'.$fk.'.status', 'pending');
        });
        if ($reqStatuses->isEmpty()) {
          $synthStatusKey = 'pending';
        } elseif ($reqStatuses->every(fn (string $s) => $s === 'passed')) {
          $synthStatusKey = 'verified';
        } elseif ($reqStatuses->contains(fn (string $s) => $s === 'flagged')) {
          $synthStatusKey = 'needs_revision';
        } else {
          $synthStatusKey = 'pending';
        }
        $synthReqBadge = $statusBadge($synthStatusKey === 'verified' ? 'verified' : ($synthStatusKey === 'needs_revision' ? 'needs_revision' : 'pending'));
      }
      $requirementsDefaultReviewSection = ($section['key'] ?? '') === 'requirements' ? 'requirements' : ((($section['key'] ?? '') === 'submitted_files') ? 'submitted_files' : '');
    @endphp
    <x-ui.card padding="p-0" class="overflow-hidden" data-review-section-card data-section-key="{{ $section['key'] }}" data-section-label="{{ $section['title'] }}">
      <div class="border-b border-slate-100 bg-white px-6 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold tracking-tight text-slate-900">{{ $section['title'] }}</h2>
            @if (! empty($section['subtitle'] ?? ''))
              <p class="mt-1 text-sm text-slate-500">{{ $section['subtitle'] }}</p>
            @endif
          </div>
          @if ($synthReqBadge)
            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $synthReqBadge['class'] }}" data-section-status-badge="{{ $section['key'] }}" data-synth-requirements-badge="1">{{ $synthReqBadge['label'] }}</span>
          @elseif ($sectionReviewable)
            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge['class'] }}" data-section-status-badge="{{ $section['key'] }}">{{ $badge['label'] }}</span>
          @endif
        </div>
      </div>
      <div class="bg-white px-6 py-5">
        @if ($isRequirementsListLayout)
          @include('admin.reviews.partials.requirements-attached-rows', [
            'fields' => $section['fields'] ?? [],
            'fieldUpdateFor' => $fieldUpdateFor,
            'persistedFieldReviews' => $persistedFieldReviews,
            'persistedSectionReviews' => $persistedSectionReviews,
            'defaultReviewSectionKey' => $requirementsDefaultReviewSection,
          ])
          @if (($moduleLabel ?? '') === 'Activity Proposal' && ($section['key'] ?? '') === 'requirements_attached')
            @include('admin.registrations.partials.section-submit-control', ['sectionKey' => 'additional', 'persistedSectionReviews' => $persistedSectionReviews])
          @endif
        @else
        <dl class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
          @foreach (($section['fields'] ?? []) as $field)
            @if (($moduleLabel ?? '') === 'Renewal' && ($section['key'] ?? '') === 'adviser' && ! empty($field['renewal_synthetic_adviser_status']))
              @php
                $synthKey = strtolower(trim((string) ($field['synthetic_status_key'] ?? 'pending')));
                $renewalAdviserChip = match (true) {
                  $synthKey === 'approved' => ['border' => 'border-emerald-200', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500'],
                  default => ['border' => 'border-amber-200', 'bg' => 'bg-amber-50', 'text' => 'text-amber-800', 'dot' => 'bg-amber-500'],
                };
                $renewalAdviserLabel = $synthKey === 'approved' ? 'Approved' : 'Pending';
              @endphp
              <div class="{{ $readonlyItemClass }} {{ ($field['wide'] ?? false) ? 'md:col-span-2' : '' }}">
                <dt class="{{ $readonlyLabelClass }}">{{ $field['label'] }}</dt>
                <dd class="mt-2">
                  <span class="inline-flex items-center gap-1.5 rounded-full border {{ $renewalAdviserChip['border'] }} {{ $renewalAdviserChip['bg'] }} px-3 py-1 text-xs font-semibold {{ $renewalAdviserChip['text'] }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $renewalAdviserChip['dot'] }}" aria-hidden="true"></span>
                    {{ $renewalAdviserLabel }}
                  </span>
                </dd>
              </div>
              @continue
            @endif
            @php
              $isCalendarEntry = ($moduleLabel ?? '') === 'Activity Calendar' && str_starts_with((string) ($section['key'] ?? ''), 'entry_');
              $calAnchor = '';
              if ($isCalendarEntry && preg_match('/^entry_(\d+)_(.+)$/', (string) ($field['key'] ?? ''), $calM)) {
                $calSuffix = match ($calM[2]) {
                  'name' => 'activity_name',
                  'date' => 'date',
                  'venue' => 'venue',
                  'sdg' => 'sdgs',
                  'participants' => 'participants',
                  'budget' => 'budget',
                  'program' => 'program',
                  default => $calM[2],
                };
                $calAnchor = 'activity-calendar-entry-'.$calM[1].'-'.$calSuffix;
              }
              $fieldUpdate = $fieldUpdateFor((string) ($section['key'] ?? ''), (string) ($field['key'] ?? ''));
              $isFieldUpdated = (bool) data_get($fieldUpdate, 'is_updated', false);
            @endphp
            <div
              class="{{ $readonlyItemClass }} {{ ($field['wide'] ?? false) ? 'md:col-span-2' : '' }}"
              @if ($calAnchor !== '') id="{{ $calAnchor }}" @endif
            >
              <dt class="{{ $readonlyLabelClass }}">{{ $field['label'] }}
                @if ($isFieldUpdated && $isCalendarEntry)
                  <span class="ml-2 inline-flex rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700">Updated</span>
                @endif
              </dt>
              <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
                <dd class="text-sm font-semibold text-slate-900">{{ $field['value'] }}</dd>
                @if ($isFieldUpdated && ! $isCalendarEntry)
                  <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700">Updated</span>
                @endif
                @if (! empty($field['action'] ?? null))
                  <a href="{{ $field['action']['href'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-xl border border-[#003E9F] bg-white px-3.5 py-2 text-xs font-semibold text-[#003E9F] transition hover:bg-slate-50">View file</a>
                @endif
                @if ($sectionReviewable && ($field['reviewable'] ?? true))
                  @include('admin.registrations.partials.field-review-control', [
                    'sectionKey' => $section['key'],
                    'fieldKey' => $field['key'],
                    'fieldLabel' => $field['label'],
                    'persistedFieldReviews' => $persistedFieldReviews,
                    'persistedSectionReviews' => $persistedSectionReviews,
                    'cardScrollId' => $calAnchor !== '' ? $calAnchor : null,
                  ])
                @endif
              </div>
              @if ($isFieldUpdated && $isCalendarEntry)
                @include('admin.registrations.partials.field-update-inline', ['update' => $fieldUpdate])
              @elseif ($isFieldUpdated)
                <p class="mt-2 text-xs text-sky-800">
                  <span class="font-semibold">Updated value:</span>
                  {{ data_get($fieldUpdate, 'old_value') ?: '—' }} → {{ data_get($fieldUpdate, 'new_value') ?: '—' }}
                </p>
              @endif
              @if (! empty($field['table']) && is_array($field['table']))
                <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                  <table class="min-w-160 w-full divide-y divide-slate-200 text-left text-xs sm:text-sm">
                    <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                      <tr>
                        <th class="px-3 py-2.5">Material / Item</th>
                        <th class="px-3 py-2.5">Quantity</th>
                        <th class="px-3 py-2.5">Unit Price</th>
                        <th class="px-3 py-2.5">Price</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      @foreach ($field['table'] as $budgetRow)
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
        @endif
        @if (! empty($section['actions']) && is_array($section['actions']))
          <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            @foreach ($section['actions'] as $action)
              @if (($action['status'] ?? '') === 'approved')
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-3">
                  <button type="button" class="inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700" data-adviser-review-action="{{ $action['action'] }}" data-adviser-review-status="approved">{{ $action['label'] ?? 'Approve' }}</button>
                </div>
              @elseif (($action['status'] ?? '') === 'rejected')
                <div class="rounded-xl border border-rose-200 bg-rose-50/40 p-3 space-y-2">
                  <x-forms.label for="adviser-rejection-notes-{{ $section['key'] }}">Rejection notes</x-forms.label>
                  <x-forms.textarea id="adviser-rejection-notes-{{ $section['key'] }}" name="rejection_notes" rows="2" placeholder="Reason for rejection...">{{ old('rejection_notes') }}</x-forms.textarea>
                  <button type="button" class="inline-flex w-full items-center justify-center rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-700" data-adviser-review-action="{{ $action['action'] }}" data-adviser-review-status="rejected" data-adviser-rejection-notes-id="adviser-rejection-notes-{{ $section['key'] }}">{{ $action['label'] ?? 'Reject' }}</button>
                </div>
              @endif
            @endforeach
          </div>
        @endif
      </div>
      @if ($sectionReviewable)
        @include('admin.registrations.partials.section-submit-control', ['sectionKey' => $section['key'], 'persistedSectionReviews' => $persistedSectionReviews])
      @endif
    </x-ui.card>
  @endforeach

  <x-ui.card padding="p-0" class="overflow-hidden">
    <div class="border-b border-slate-100 bg-white px-6 py-4">
      <h2 class="text-lg font-bold tracking-tight text-slate-900">Finalize review</h2>
      <p class="mt-1 text-sm text-slate-500">Saving auto-approves when all sections are verified; otherwise the submission is returned for revision with field notes.</p>
    </div>
    <div class="bg-white px-6 py-5">
      <div id="revision-summary-box" class="rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-4 text-sm text-amber-900">
        <p id="revision-summary-title" class="text-sm font-bold uppercase tracking-widest text-amber-900">Revision Summary</p>
      <p id="revision-summary-helper" class="mt-1 text-xs text-amber-800/90">Click a revision item below to jump to the section that needs updates.</p>
        <ul id="revision-summary-list" class="mt-3 space-y-3"></ul>
      </div>
      <div class="mt-6">
        <x-forms.label for="module-remarks">General remarks / instructions <span class="font-normal normal-case text-slate-400">(optional)</span></x-forms.label>
        <x-forms.textarea id="module-remarks" name="remarks" :rows="4" placeholder="Optional overall context. Field-level flagged notes are captured from each section.">{{ old('remarks', $persistedRemarks ?? '') }}</x-forms.textarea>
      </div>
      <div class="mt-6 flex flex-wrap gap-3 border-t border-slate-100 pt-5">
        <button id="save-review-btn" type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] disabled:cursor-not-allowed disabled:opacity-60">Save review</button>
        <a href="{{ $backRoute }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">{{ $backLabel }}</a>
      </div>
      <p id="save-review-helper" class="mt-2 text-xs text-slate-500"></p>
    </div>
  </x-ui.card>
</form>

<script>
(() => {
  const form = document.getElementById('module-review-form');
  if (!form) return;
  const progressEl = document.getElementById('module-review-progress');
  const revisionSummaryBox = document.getElementById('revision-summary-box');
  const revisionSummaryTitle = document.getElementById('revision-summary-title');
  const revisionSummaryList = document.getElementById('revision-summary-list');
  const revisionSummaryHelper = document.getElementById('revision-summary-helper');
  const saveReviewBtn = document.getElementById('save-review-btn');
  const saveReviewHelper = document.getElementById('save-review-helper');
  const remarks = document.getElementById('module-remarks');
  const reviewDraftUrl = form.dataset.reviewDraftUrl || '';
  const sectionOrder = Array.from(form.querySelectorAll('[data-section-submit]')).map((r) => r.dataset.sectionKey || '');
  const moduleLabelLower = @json(strtolower((string) ($moduleLabel ?? 'submission')));

  const normalizeStatus = (status) => {
    const value = String(status || 'pending');
    if (value === 'revision' || value === 'needs_revision') return 'flagged';
    if (value === 'passed' || value === 'flagged' || value === 'pending') return value;
    return 'pending';
  };

  const getFieldControls = (sectionKey) => Array.from(form.querySelectorAll(`[data-field-review][data-section-key="${sectionKey}"]`));
  const allSectionRoots = () => Array.from(form.querySelectorAll('[data-section-submit]'));
  const csrfToken = () => form.querySelector('input[name="_token"]')?.value || '';

  document.querySelectorAll('[data-adviser-review-action]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const actionUrl = btn.getAttribute('data-adviser-review-action') || '';
      const status = btn.getAttribute('data-adviser-review-status') || '';
      const token = csrfToken();
      if (!actionUrl || !status || !token) return;
      if (status === 'rejected') {
        const notesId = btn.getAttribute('data-adviser-rejection-notes-id') || '';
        const notesEl = notesId ? document.getElementById(notesId) : null;
        if (!notesEl || String(notesEl.value || '').trim() === '') {
          alert('Rejection notes are required.');
          return;
        }
      }
      const submitForm = document.createElement('form');
      submitForm.method = 'POST';
      submitForm.action = actionUrl;
      submitForm.style.display = 'none';
      submitForm.innerHTML = `<input type="hidden" name="_token" value="${token}"><input type="hidden" name="_method" value="PATCH"><input type="hidden" name="action" value="${status}">`;
      if (status === 'rejected') {
        const notesId = btn.getAttribute('data-adviser-rejection-notes-id') || '';
        const notesEl = notesId ? document.getElementById(notesId) : null;
        const notesInput = document.createElement('input');
        notesInput.type = 'hidden';
        notesInput.name = 'rejection_notes';
        notesInput.value = String(notesEl?.value || '');
        submitForm.appendChild(notesInput);
      }
      document.body.appendChild(submitForm);
      submitForm.submit();
    });
  });

  function syncFieldControl(control) {
    const statusInput = control.querySelector('.field-review-status');
    const noteWrap = control.dataset.noteWrapId ? document.getElementById(control.dataset.noteWrapId) : control.querySelector('.field-review-note');
    const noteInput = control.dataset.noteInputId ? document.getElementById(control.dataset.noteInputId) : control.querySelector('.field-review-note-input');
    const noteError = noteWrap?.querySelector('.field-review-note-error');
    const status = normalizeStatus(statusInput?.value || 'pending');
    if (statusInput) statusInput.value = status;
    control.querySelectorAll('.field-review-btn').forEach((btn) => {
      const active = btn.dataset.statusValue === status;
      btn.classList.toggle('ring-2', active);
      btn.classList.toggle('ring-emerald-400', active && status === 'passed');
      btn.classList.toggle('ring-amber-400', active && status === 'flagged');
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    if (noteWrap) noteWrap.classList.toggle('hidden', status !== 'flagged');
    const hasNote = (noteInput?.value || '').trim() !== '';
    if (noteError) noteError.classList.toggle('hidden', status !== 'flagged' || hasNote);
  }

  function computeSection(sectionKey) {
    const controls = getFieldControls(sectionKey);
    let pending = 0;
    let flagged = 0;
    let invalidFlagged = 0;
    controls.forEach((control) => {
      const status = normalizeStatus(control.querySelector('.field-review-status')?.value || 'pending');
      if (status === 'pending') pending += 1;
      if (status === 'flagged') {
        flagged += 1;
        const noteInput = control.dataset.noteInputId ? document.getElementById(control.dataset.noteInputId) : control.querySelector('.field-review-note-input');
        if ((noteInput?.value || '').trim() === '') invalidFlagged += 1;
      }
    });
    return { pending, flagged, invalidFlagged, total: controls.length };
  }

  function applySectionBadge(sectionKey, status) {
    const badge = form.querySelector(`[data-section-status-badge="${sectionKey}"]:not([data-synth-requirements-badge])`);
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

  function refreshSyntheticRequirementsBadges() {
    form.querySelectorAll('[data-synth-requirements-badge]').forEach((badge) => {
      const card = badge.closest('[data-review-section-card]');
      if (!card) return;
      const controls = card.querySelectorAll('[data-field-review]');
      let pending = 0;
      let flagged = 0;
      controls.forEach((control) => {
        const status = normalizeStatus(control.querySelector('.field-review-status')?.value || 'pending');
        if (status === 'pending') pending += 1;
        if (status === 'flagged') flagged += 1;
      });
      const st = controls.length === 0 ? 'pending' : (pending > 0 ? 'pending' : (flagged > 0 ? 'needs_revision' : 'verified'));
      if (st === 'verified') {
        badge.className = 'inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700';
        badge.textContent = 'Verified';
      } else if (st === 'needs_revision') {
        badge.className = 'inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700';
        badge.textContent = 'Needs Revision';
      } else {
        badge.className = 'inline-flex rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700';
        badge.textContent = 'Pending';
      }
    });
  }

  function syncSection(sectionRoot) {
    const sectionKey = sectionRoot.dataset.sectionKey || '';
    const stateInput = sectionRoot.querySelector('.section-review-state');
    const submittedInput = sectionRoot.querySelector('.section-review-submitted');
    const stats = computeSection(sectionKey);
    const status = stats.pending > 0 ? 'pending' : (stats.flagged > 0 ? 'needs_revision' : 'verified');
    if (stateInput) stateInput.value = status;
    if (submittedInput) submittedInput.value = (stats.total > 0 && stats.pending === 0 && stats.invalidFlagged === 0) ? '1' : '0';
    applySectionBadge(sectionKey, status);
  }

  function refreshSummary() {
    let verified = 0, needsRevision = 0, pending = 0;
    allSectionRoots().forEach((root) => {
      const state = root.querySelector('.section-review-state')?.value || 'pending';
      if (state === 'verified') verified += 1;
      else if (state === 'needs_revision') needsRevision += 1;
      else pending += 1;
    });
    progressEl.querySelector('[data-progress="verified"]').textContent = `Verified: ${verified}`;
    progressEl.querySelector('[data-progress="needs_revision"]').textContent = `Needs Revision: ${needsRevision}`;
    progressEl.querySelector('[data-progress="pending"]').textContent = `Pending: ${pending}`;
  }

  function updateSaveReviewAvailability() {
    let hasPending = false;
    let missingNote = false;
    allSectionRoots().forEach((root) => {
      const stats = computeSection(root.dataset.sectionKey || '');
      if (stats.pending > 0) hasPending = true;
      if (stats.invalidFlagged > 0) missingNote = true;
    });
    const canSave = !hasPending && !missingNote;
    saveReviewBtn.disabled = !canSave;
    if (hasPending) saveReviewHelper.textContent = 'All sections must be fully reviewed before saving.';
    else if (missingNote) saveReviewHelper.textContent = 'Revision note is required for each field marked Revision.';
    else saveReviewHelper.textContent = '';
  }

  function applyRevisionSummaryState(state) {
    if (state === 'success') {
      revisionSummaryBox.className = 'rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900';
      if (revisionSummaryTitle) {
        revisionSummaryTitle.className = 'text-sm font-bold uppercase tracking-widest text-emerald-700';
        revisionSummaryTitle.textContent = 'Review Summary';
      }
      revisionSummaryHelper.className = 'mt-1 text-xs text-emerald-600';
      return;
    }
    if (state === 'pending') {
      revisionSummaryBox.className = 'rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-4 text-sm text-amber-900';
      if (revisionSummaryTitle) {
        revisionSummaryTitle.className = 'text-sm font-bold uppercase tracking-widest text-amber-900';
        revisionSummaryTitle.textContent = 'Revision Summary';
      }
      revisionSummaryHelper.className = 'mt-1 text-xs text-amber-800/90';
      return;
    }
    revisionSummaryBox.className = 'rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-4 text-sm text-amber-900';
    if (revisionSummaryTitle) {
      revisionSummaryTitle.className = 'text-sm font-bold uppercase tracking-widest text-amber-900';
      revisionSummaryTitle.textContent = 'Revision Summary';
    }
    revisionSummaryHelper.className = 'mt-1 text-xs text-amber-800/90';
  }

  function refreshRevisionSummary() {
    const rows = [];
    const sectionRoots = allSectionRoots();
    const sectionStates = sectionRoots.map((root) => root.querySelector('.section-review-state')?.value || 'pending');
    const pendingCount = sectionStates.filter((state) => state === 'pending').length;
    const verifiedCount = sectionStates.filter((state) => state === 'verified').length;
    form.querySelectorAll('[data-field-review]').forEach((control) => {
      const status = normalizeStatus(control.querySelector('.field-review-status')?.value || 'pending');
      if (status !== 'flagged') return;
      const sectionKey = control.dataset.sectionKey || '';
      const sectionRoot = control.closest('[data-review-section-card]');
      const section = sectionRoot?.dataset.sectionLabel || sectionKey;
      const field = control.dataset.fieldLabel || 'Field';
      const noteInput = control.dataset.noteInputId ? document.getElementById(control.dataset.noteInputId) : control.querySelector('.field-review-note-input');
      const note = (noteInput?.value || '').trim();
      const target = control.closest('.field-review-card');
      if (target && !target.id) target.id = `review-target-${sectionKey}-${control.dataset.fieldKey || 'field'}`;
      rows.push({ sectionKey, section, field, note: note || 'No note provided yet.', targetId: target?.id || '' });
    });

    if (pendingCount > 0) {
      applyRevisionSummaryState('pending');
      revisionSummaryHelper.textContent = 'Complete all pending fields first, then finalize the review.';
      revisionSummaryList.innerHTML = `<li class="text-sm text-slate-700">${pendingCount} section(s) pending. Complete all field reviews first.</li>`;
      return;
    }

    if (rows.length === 0 && verifiedCount === sectionRoots.length && sectionRoots.length > 0) {
      applyRevisionSummaryState('success');
      revisionSummaryHelper.textContent = 'Every section is fully reviewed and verified.';
      revisionSummaryList.innerHTML = `<li class="text-sm text-emerald-800">All sections are verified. This ${moduleLabelLower} is ready for approval.</li>`;
      return;
    }

    applyRevisionSummaryState('revision');
    revisionSummaryHelper.textContent = 'Click a revision item below to jump to the field that needs updates.';
    revisionSummaryList.innerHTML = '';
    const grouped = {};
    rows.forEach((row) => {
      grouped[row.sectionKey] = grouped[row.sectionKey] || [];
      grouped[row.sectionKey].push(row);
    });
    sectionOrder.forEach((sectionKey) => {
      const sectionRows = grouped[sectionKey] || [];
      if (sectionRows.length === 0) return;
      const item = document.createElement('li');
      item.className = 'rounded-xl border border-amber-200/70 bg-white/70 px-3 py-2.5';
      item.innerHTML = `<p class="font-semibold text-amber-900">${sectionRows[0].section} (${sectionRows.length})</p>`;
      const list = document.createElement('ul');
      list.className = 'mt-1.5 space-y-1.5';
      sectionRows.forEach((row) => {
        const li = document.createElement('li');
        li.innerHTML = `<button type="button" class="inline-flex w-full items-start gap-2 rounded-lg px-2 py-1.5 text-left text-xs text-amber-900/95 transition hover:bg-amber-100/70" data-target-id="${row.targetId}"><span class="font-semibold underline underline-offset-2">${row.field}</span><span>- ${row.note}</span></button>`;
        list.appendChild(li);
      });
      item.appendChild(list);
      revisionSummaryList.appendChild(item);
    });
  }

  function buildFieldReviewPayload() {
    const payload = {};
    form.querySelectorAll('[data-field-review]').forEach((control) => {
      const sectionKey = control.dataset.sectionKey || '';
      const fieldKey = control.dataset.fieldKey || '';
      if (!payload[sectionKey]) payload[sectionKey] = {};
      const noteInput = control.dataset.noteInputId ? document.getElementById(control.dataset.noteInputId) : control.querySelector('.field-review-note-input');
      payload[sectionKey][fieldKey] = {
        status: normalizeStatus(control.querySelector('.field-review-status')?.value || 'pending'),
        note: noteInput?.value || '',
      };
    });
    return payload;
  }

  let draftTimer = null;
  let draftRequestSeq = 0;
  let latestAppliedDraftSeq = 0;
  let activeDraftController = null;
  let isPersistingDraft = false;
  function scheduleDraftPersist() {
    if (!reviewDraftUrl) return;
    if (draftTimer) window.clearTimeout(draftTimer);
    draftTimer = window.setTimeout(async () => {
      if (isPersistingDraft && activeDraftController) {
        activeDraftController.abort();
      }
      const requestSeq = ++draftRequestSeq;
      const controller = new AbortController();
      activeDraftController = controller;
      isPersistingDraft = true;
      try {
        await fetch(reviewDraftUrl, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
          body: JSON.stringify({ field_review: buildFieldReviewPayload() }),
          signal: controller.signal,
        });
        if (requestSeq > latestAppliedDraftSeq) {
          latestAppliedDraftSeq = requestSeq;
        }
      } catch (e) {
      } finally {
        if (activeDraftController === controller) {
          activeDraftController = null;
        }
        isPersistingDraft = false;
      }
    }, 350);
  }

  form.querySelectorAll('[data-field-review]').forEach((control) => {
    const parentCard = control.closest('.field-review-card');
    const requirementCard = control.closest('.requirement-review-item');
    const anchorId = control.dataset.anchorId || '';
    if (anchorId) {
      const anchorTarget = parentCard || requirementCard || control;
      if (anchorTarget && (!anchorTarget.id || anchorTarget.id === anchorId)) {
        anchorTarget.id = anchorId;
      }
    }
    const noteWrap = control.querySelector('.field-review-note');
    const noteInput = control.querySelector('.field-review-note-input');
    if (noteWrap) {
      const noteWrapId = `field-review-note-${control.dataset.sectionKey || 'section'}-${control.dataset.fieldKey || 'field'}`;
      noteWrap.id = noteWrapId;
      control.dataset.noteWrapId = noteWrapId;
      if (noteInput) {
        const noteInputId = `${noteWrapId}-input`;
        noteInput.id = noteInputId;
        control.dataset.noteInputId = noteInputId;
      }
      const container = control.closest('.field-review-card');
      if (container) container.appendChild(noteWrap);
    }
    syncFieldControl(control);
    control.querySelectorAll('.field-review-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const statusInput = control.querySelector('.field-review-status');
        if (!statusInput) return;
        statusInput.value = btn.dataset.statusValue || 'pending';
        syncFieldControl(control);
        const sectionRoot = form.querySelector(`[data-section-submit][data-section-key="${control.dataset.sectionKey}"]`);
        if (sectionRoot) syncSection(sectionRoot);
        refreshSummary();
        refreshSyntheticRequirementsBadges();
        refreshRevisionSummary();
        updateSaveReviewAvailability();
        scheduleDraftPersist();
      });
    });
    const mappedNoteInput = control.dataset.noteInputId ? document.getElementById(control.dataset.noteInputId) : noteInput;
    mappedNoteInput?.addEventListener('input', () => {
      const sectionRoot = form.querySelector(`[data-section-submit][data-section-key="${control.dataset.sectionKey}"]`);
      if (sectionRoot) syncSection(sectionRoot);
      refreshSummary();
      refreshSyntheticRequirementsBadges();
      refreshRevisionSummary();
      updateSaveReviewAvailability();
      scheduleDraftPersist();
    });
  });

  allSectionRoots().forEach((root) => syncSection(root));
  refreshSummary();
  refreshSyntheticRequirementsBadges();
  refreshRevisionSummary();
  updateSaveReviewAvailability();

  revisionSummaryList.addEventListener('click', (event) => {
    const action = event.target.closest('button[data-target-id]');
    if (!action) return;
    const target = document.getElementById(action.dataset.targetId || '');
    if (!target) return;
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    target.classList.add('ring-2', 'ring-amber-300', 'bg-amber-50/80');
    window.setTimeout(() => target.classList.remove('ring-2', 'ring-amber-300', 'bg-amber-50/80'), 1800);
  });

  let activeUpdatedFlashTimer = null;
  function scrollToUpdatedTarget(targetId) {
    if (!targetId) return;
    const target = document.getElementById(targetId);
    if (!target) return;
    const highlightTarget = target.closest('.field-review-card') || target.closest('.requirement-review-item') || target;
    highlightTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
    highlightTarget.classList.add('ring-2', 'ring-sky-300', 'bg-sky-50/70', 'transition');
    if (activeUpdatedFlashTimer) {
      window.clearTimeout(activeUpdatedFlashTimer);
    }
    activeUpdatedFlashTimer = window.setTimeout(() => {
      highlightTarget.classList.remove('ring-2', 'ring-sky-300', 'bg-sky-50/70', 'transition');
    }, 1800);
  }

  form.querySelectorAll('.updated-summary-link').forEach((action) => {
    action.addEventListener('click', () => {
      const targetId = action.dataset.scrollTarget || '';
      scrollToUpdatedTarget(targetId);
    });
  });

  remarks?.addEventListener('input', scheduleDraftPersist);
})();
</script>
@endsection
