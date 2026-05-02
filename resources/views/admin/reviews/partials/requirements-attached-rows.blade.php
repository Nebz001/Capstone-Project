@php
  $fields = $fields ?? [];
  $fieldUpdateFor = $fieldUpdateFor ?? static fn (): ?array => null;
  $persistedFieldReviews = is_array($persistedFieldReviews ?? null) ? $persistedFieldReviews : [];
  $persistedSectionReviews = is_array($persistedSectionReviews ?? null) ? $persistedSectionReviews : [];
  $defaultReviewSectionKey = (string) ($defaultReviewSectionKey ?? '');
@endphp
<ul class="space-y-3">
  @foreach ($fields as $field)
    @php
      $reviewSectionKey = (string) ($field['review_section_key'] ?? $defaultReviewSectionKey);
      $reviewFieldKey = (string) ($field['review_field_key'] ?? $field['key'] ?? '');
      $fieldUpdate = is_callable($fieldUpdateFor) ? $fieldUpdateFor($reviewSectionKey, $reviewFieldKey) : null;
      $isFieldUpdated = (bool) data_get($fieldUpdate, 'is_updated', false);
      $hasFile = ! empty(data_get($field, 'action.href'));
      $fileUrl = data_get($field, 'action.href');
      $downloadUrl = data_get($field, 'download_action.href');
      $showReview = (bool) ($field['show_review_controls'] ?? true);
    @endphp
    <li class="requirement-review-item field-review-card rounded-xl border border-slate-200 bg-slate-50/80 p-3.5">
      <div class="requirement-review-main-row flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <p class="text-sm font-semibold text-slate-900">{{ $field['label'] }}</p>
            @if ($isFieldUpdated)
              <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700">Updated</span>
            @endif
            <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $field['file_badge_class'] ?? 'border-slate-200 bg-slate-100 text-slate-700' }}">{{ $field['file_badge_label'] ?? 'FILE' }}</span>
          </div>
          <p class="mt-0.5 text-xs text-slate-500">Marked as submitted: <span class="font-semibold text-slate-700">{{ ! empty($field['submitted']) ? 'Yes' : 'No' }}</span></p>
        </div>
        <div class="requirement-review-top-row flex w-full flex-wrap items-center gap-3 lg:w-auto lg:justify-end">
          <div class="shrink-0">
            @if ($hasFile)
              <div class="inline-flex items-center rounded-lg border border-slate-200 bg-white p-1" role="group" aria-label="File actions for {{ $field['label'] }}">
                <a href="{{ $fileUrl }}" target="_blank" rel="noopener noreferrer" class="rounded-md px-2.5 py-1 text-[11px] font-semibold text-[#003E9F] transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#003E9F]/30">View file</a>
                @if (! empty($downloadUrl))
                  <span class="mx-1 h-4 w-px bg-slate-200"></span>
                  <a href="{{ $downloadUrl }}" class="rounded-md px-2.5 py-1 text-[11px] font-semibold text-[#003E9F] transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#003E9F]/30">Download</a>
                @endif
              </div>
            @elseif (! empty($field['submitted']))
              <span class="text-xs font-medium text-amber-700">Marked yes — no file on record</span>
            @endif
          </div>
          @if ($showReview)
            <div class="ml-auto shrink-0 lg:ml-0">
              @include('admin.registrations.partials.field-review-control', [
                'sectionKey' => $reviewSectionKey,
                'fieldKey' => $reviewFieldKey,
                'fieldLabel' => (string) ($field['label'] ?? $reviewFieldKey),
                'persistedFieldReviews' => $persistedFieldReviews,
                'persistedSectionReviews' => $persistedSectionReviews,
              ])
            </div>
          @endif
        </div>
      </div>
      @if ($isFieldUpdated)
        <p class="mt-2 text-xs text-sky-800"><span class="font-semibold">Updated value:</span> {{ data_get($fieldUpdate, 'old_value') ?: '—' }} → {{ data_get($fieldUpdate, 'new_value') ?: '—' }}</p>
      @endif
    </li>
  @endforeach
</ul>
