@php
  $update = is_array($update ?? null) ? $update : null;
@endphp

@if ($update && ($update['is_updated'] ?? false))
  <div class="mt-2.5 w-full rounded-lg border border-sky-200/90 bg-sky-50/70 px-3 py-2.5 text-xs text-sky-950">
    <div class="space-y-1.5">
      <p><span class="font-semibold">Previous:</span> <span class="whitespace-pre-wrap">{{ filled((string) ($update['old_value'] ?? '')) ? (string) $update['old_value'] : 'No previous value captured.' }}</span></p>
      <p><span class="font-semibold">Updated:</span> <span class="whitespace-pre-wrap">{{ filled((string) ($update['new_value'] ?? '')) ? (string) $update['new_value'] : 'No updated value captured.' }}</span></p>
      <p><span class="font-semibold">Resubmitted by:</span> {{ $update['resubmitted_by'] ?? 'Unknown' }}</p>
      <p><span class="font-semibold">Resubmitted on:</span> {{ !empty($update['resubmitted_at']) ? \Illuminate\Support\Carbon::parse((string) $update['resubmitted_at'])->format('M d, Y, g:i A') : 'Unknown date' }}</p>
    </div>
  </div>
@endif
