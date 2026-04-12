@php
  $initial = $initialSectionReviewState[$sectionKey] ?? 'pending';
  $state = old('section_review.'.$sectionKey, $initial);
  if (! in_array($state, ['validated', 'revision', 'pending'], true)) {
      $state = 'pending';
  }
  $revBody = old($revisionFieldName, $registration->{$revisionFieldName} ?? '');
@endphp
<div
  class="section-review mt-5 border-t border-slate-100 pt-5"
  data-section-key="{{ $sectionKey }}"
  data-section-title="{{ $sectionTitle }}"
  data-revision-field="{{ $revisionFieldName }}"
  data-revision-input-id="{{ $revisionTextareaId }}"
>
  <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0 flex-1">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Section review</p>
      <p class="section-review-status mt-1 text-sm font-medium text-slate-800"></p>
      <p class="section-review-hint mt-0.5 text-xs text-slate-500"></p>
    </div>
    <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
      <button
        type="button"
        class="section-review-btn-verified inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-900"
      >
        Verified
      </button>
      <button
        type="button"
        class="section-review-btn-revision inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-amber-300 hover:bg-amber-50 hover:text-amber-900"
      >
        Need revision
      </button>
      <button
        type="button"
        class="section-review-btn-edit hidden rounded-lg border border-dashed border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-[#003E9F] hover:text-[#003E9F]"
      >
        Edit note
      </button>
    </div>
  </div>
  <input type="hidden" name="section_review[{{ $sectionKey }}]" value="{{ $state }}" class="section-review-state" />
  <textarea
    name="{{ $revisionFieldName }}"
    id="{{ $revisionTextareaId }}"
    class="hidden"
    rows="1"
    autocomplete="off"
  >{{ $revBody }}</textarea>
  @error($revisionFieldName)
    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
  @enderror
</div>
