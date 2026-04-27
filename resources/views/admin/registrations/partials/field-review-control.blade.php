@php
  $saved = data_get($persistedFieldReviews ?? [], "$sectionKey.$fieldKey", []);
  $status = old("field_review.$sectionKey.$fieldKey.status", $saved['status'] ?? 'pending');
  if (in_array($status, ['revision', 'needs_revision'], true)) {
    $status = 'flagged';
  }
  if (! in_array($status, ['pending', 'passed', 'flagged'], true)) {
    $status = 'pending';
  }
  $note = old("field_review.$sectionKey.$fieldKey.note", $saved['note'] ?? '');
  $locked = data_get($persistedSectionReviews ?? [], "$sectionKey.submitted", false) ? '1' : '0';
@endphp
<div
  id="updated-field-{{ $sectionKey }}-{{ $fieldKey }}"
  class="field-review-control flex flex-wrap items-center justify-end gap-2"
  data-field-review
  data-section-key="{{ $sectionKey }}"
  data-field-key="{{ $fieldKey }}"
  data-field-label="{{ $fieldLabel }}"
  data-anchor-id="updated-field-{{ $sectionKey }}-{{ $fieldKey }}"
  data-locked="{{ $locked }}"
>
  <div class="inline-flex flex-wrap items-center gap-1 rounded-lg border border-slate-200 bg-white p-1" role="group" aria-label="Field review for {{ $fieldLabel }}">
    <button
      type="button"
      class="field-review-btn rounded-md px-2.5 py-1 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 {{ $status === 'passed' ? 'ring-2 ring-emerald-400' : '' }}"
      data-status-value="passed"
      aria-pressed="{{ $status === 'passed' ? 'true' : 'false' }}"
      aria-label="Mark {{ $fieldLabel }} as passed"
    >Passed</button>
    <button
      type="button"
      class="field-review-btn rounded-md px-2.5 py-1 text-[11px] font-semibold text-amber-700 transition hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-500/40 {{ $status === 'flagged' ? 'ring-2 ring-amber-400' : '' }}"
      data-status-value="flagged"
      aria-pressed="{{ $status === 'flagged' ? 'true' : 'false' }}"
      aria-label="Mark {{ $fieldLabel }} as revision"
    >Revision</button>
  </div>
  <input type="hidden" class="field-review-status" name="field_review[{{ $sectionKey }}][{{ $fieldKey }}][status]" value="{{ $status }}">
  <div class="field-review-note mt-2 w-full {{ $status === 'flagged' ? '' : 'hidden' }}">
    <x-forms.textarea
      class="field-review-note-input"
      name="field_review[{{ $sectionKey }}][{{ $fieldKey }}][note]"
      :rows="2"
      placeholder="Add revision note for this field..."
    >{{ $note }}</x-forms.textarea>
    <p class="mt-1 text-xs text-slate-500">Required when this field needs revision.</p>
    <p class="field-review-note-error mt-1 hidden text-xs font-medium text-rose-700">Revision note is required.</p>
  </div>
</div>
