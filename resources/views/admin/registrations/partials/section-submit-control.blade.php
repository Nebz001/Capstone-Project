@php
  $saved = data_get($persistedSectionReviews ?? [], $sectionKey, []);
  $savedStatus = (string) ($saved['status'] ?? 'pending');
  if (! in_array($savedStatus, ['pending', 'verified', 'needs_revision'], true)) {
    $savedStatus = 'pending';
  }
  $submitted = (bool) ($saved['submitted'] ?? false);
@endphp
<div class="section-submit mt-5 border-t border-slate-100 px-6 pb-5 pt-5" data-section-submit data-section-key="{{ $sectionKey }}">
  <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3">
    <p class="text-xs text-slate-500">Review all fields before submitting this section.</p>
    <div class="flex items-center gap-2">
      <span class="section-submitted-badge {{ $submitted ? '' : 'hidden' }} inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"></span>
      <button type="button" class="section-edit-btn {{ $submitted ? '' : 'hidden' }} inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300/60">Edit Review</button>
      <button type="button" class="section-submit-btn inline-flex rounded-xl bg-[#003E9F] px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-[#00327F] disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-[#003E9F]/40" title="All fields must be reviewed before submitting">Submit Review</button>
    </div>
  </div>
  <input type="hidden" class="section-review-state" name="section_review[{{ $sectionKey }}]" value="{{ $savedStatus }}">
  <input type="hidden" class="section-review-submitted" name="section_submitted[{{ $sectionKey }}]" value="{{ $submitted ? '1' : '0' }}">
</div>
