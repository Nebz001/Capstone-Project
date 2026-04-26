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
@endphp

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

  @foreach ($sections as $section)
    @php
      $badge = $statusBadge((string) (data_get($persistedSectionReviews, ($section['key'] ?? '').'.status', 'pending')));
      $readonlyItemClass = 'field-review-card rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3.5';
      $readonlyLabelClass = 'text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500';
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
          <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge['class'] }}" data-section-status-badge="{{ $section['key'] }}">{{ $badge['label'] }}</span>
        </div>
      </div>
      <div class="bg-white px-6 py-5">
        <dl class="grid grid-cols-1 gap-3.5 md:grid-cols-2">
          @foreach (($section['fields'] ?? []) as $field)
            <div class="{{ $readonlyItemClass }} {{ ($field['wide'] ?? false) ? 'md:col-span-2' : '' }}">
              <dt class="{{ $readonlyLabelClass }}">{{ $field['label'] }}</dt>
              <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
                <dd class="text-sm font-semibold text-slate-900">{{ $field['value'] }}</dd>
                @if (! empty($field['action'] ?? null))
                  <a href="{{ $field['action']['href'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-xl border border-[#003E9F] bg-white px-3.5 py-2 text-xs font-semibold text-[#003E9F] transition hover:bg-slate-50">View file</a>
                @endif
                @include('admin.registrations.partials.field-review-control', [
                  'sectionKey' => $section['key'],
                  'fieldKey' => $field['key'],
                  'fieldLabel' => $field['label'],
                  'persistedFieldReviews' => $persistedFieldReviews,
                  'persistedSectionReviews' => $persistedSectionReviews,
                ])
              </div>
            </div>
          @endforeach
        </dl>
      </div>
      @include('admin.registrations.partials.section-submit-control', ['sectionKey' => $section['key'], 'persistedSectionReviews' => $persistedSectionReviews])
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
        <p id="revision-summary-helper" class="mt-1 text-xs text-amber-800/90">Click a revision item below to jump to the field that needs updates.</p>
        <ul id="revision-summary-list" class="mt-3 space-y-3"></ul>
      </div>
      <div class="mt-6">
        <x-forms.label for="module-remarks">General remarks / instructions <span class="font-normal normal-case text-slate-400">(optional)</span></x-forms.label>
        <x-forms.textarea id="module-remarks" name="remarks" :rows="4">{{ old('remarks', $persistedRemarks ?? '') }}</x-forms.textarea>
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

  const normalizeStatus = (status) => {
    const value = String(status || 'pending');
    if (value === 'revision' || value === 'needs_revision') return 'flagged';
    if (value === 'passed' || value === 'flagged' || value === 'pending') return value;
    return 'pending';
  };

  const getFieldControls = (sectionKey) => Array.from(form.querySelectorAll(`[data-field-review][data-section-key="${sectionKey}"]`));
  const allSectionRoots = () => Array.from(form.querySelectorAll('[data-section-submit]'));
  const csrfToken = () => form.querySelector('input[name="_token"]')?.value || '';

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
    if (status !== 'flagged' && noteInput) noteInput.value = '';
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
    const badge = form.querySelector(`[data-section-status-badge="${sectionKey}"]`);
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
      revisionSummaryBox.className = 'rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-800';
      if (revisionSummaryTitle) {
        revisionSummaryTitle.className = 'text-sm font-bold uppercase tracking-widest text-slate-800';
        revisionSummaryTitle.textContent = 'Review Summary';
      }
      revisionSummaryHelper.className = 'mt-1 text-xs text-slate-600';
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
      revisionSummaryList.innerHTML = '<li class="text-sm text-emerald-800">All sections are verified. This registration is ready for approval.</li>';
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
  function scheduleDraftPersist() {
    if (!reviewDraftUrl) return;
    if (draftTimer) window.clearTimeout(draftTimer);
    draftTimer = window.setTimeout(async () => {
      try {
        await fetch(reviewDraftUrl, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
          body: JSON.stringify({ field_review: buildFieldReviewPayload() }),
        });
      } catch (e) {}
    }, 350);
  }

  form.querySelectorAll('[data-field-review]').forEach((control) => {
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
      refreshRevisionSummary();
      updateSaveReviewAvailability();
      scheduleDraftPersist();
    });
  });

  allSectionRoots().forEach((root) => syncSection(root));
  refreshSummary();
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

  remarks?.addEventListener('input', scheduleDraftPersist);
})();
</script>
@endsection
