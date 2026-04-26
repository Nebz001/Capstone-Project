@php
  $saved = data_get($persistedSectionReviews ?? [], $sectionKey, []);
  $savedStatus = (string) ($saved['status'] ?? 'pending');
  if (! in_array($savedStatus, ['pending', 'verified', 'needs_revision'], true)) {
    $savedStatus = 'pending';
  }
  $submitted = (bool) ($saved['submitted'] ?? false);
@endphp
<div class="section-submit hidden" data-section-submit data-section-key="{{ $sectionKey }}">
  <input type="hidden" class="section-review-state" name="section_review[{{ $sectionKey }}]" value="{{ $savedStatus }}">
  <input type="hidden" class="section-review-submitted" name="section_submitted[{{ $sectionKey }}]" value="{{ $submitted ? '1' : '0' }}">
</div>
