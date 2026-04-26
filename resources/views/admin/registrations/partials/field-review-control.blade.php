@php
  $saved = data_get($persistedFieldReviews ?? [], "$sectionKey.$fieldKey", []);
  $status = old("field_review.$sectionKey.$fieldKey.status", $saved['status'] ?? 'pending');
  if (! in_array($status, ['pending', 'passed', 'flagged'], true)) {
    $status = 'pending';
  }
  $note = old("field_review.$sectionKey.$fieldKey.note", $saved['note'] ?? '');
  $locked = data_get($persistedSectionReviews ?? [], "$sectionKey.submitted", false) ? '1' : '0';
@endphp
<div class="field-review-control mt-3" data-field-review data-section-key="{{ $sectionKey }}" data-field-key="{{ $fieldKey }}" data-locked="{{ $locked }}">
  <div class="inline-flex flex-wrap items-center gap-1 rounded-lg border border-slate-200 bg-white p-1" role="group" aria-label="Field review for {{ $fieldLabel }}">
    <button type="button" class="field-review-btn rounded-md px-2 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-300/60" data-status-value="pending" aria-label="Mark {{ $fieldLabel }} as pending">Pending</button>
    <button type="button" class="field-review-btn rounded-md px-2 py-1 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/40" data-status-value="passed" aria-label="Mark {{ $fieldLabel }} as passed">Passed</button>
    <button type="button" class="field-review-btn rounded-md px-2 py-1 text-[11px] font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500/40" data-status-value="flagged" aria-label="Mark {{ $fieldLabel }} as flagged">Flagged</button>
  </div>
  <input type="hidden" class="field-review-status" name="field_review[{{ $sectionKey }}][{{ $fieldKey }}][status]" value="{{ $status }}">
  <div class="field-review-note mt-2 {{ $status === 'flagged' ? '' : 'hidden' }}">
    <x-forms.textarea
      class="field-review-note-input"
      name="field_review[{{ $sectionKey }}][{{ $fieldKey }}][note]"
      :rows="2"
      placeholder="Add revision note for this field..."
    >{{ $note }}</x-forms.textarea>
    <p class="mt-1 text-xs text-slate-500">Required when this field is flagged.</p>
  </div>
</div>
