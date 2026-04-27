@php
  $update = is_array($update ?? null) ? $update : null;
@endphp

@if ($update && ($update['is_updated'] ?? false))
  @php
    $sectionKey = (string) ($update['section_key'] ?? '');
    $oldFile = is_array($update['old_file'] ?? null) ? $update['old_file'] : ['name' => '', 'view_url' => null, 'download_url' => null];
    $newFile = is_array($update['new_file'] ?? null) ? $update['new_file'] : ['name' => '', 'view_url' => null, 'download_url' => null];
    $isFileUpdate = $sectionKey === 'requirements' || filled((string) ($oldFile['name'] ?? '')) || filled((string) ($newFile['name'] ?? ''));
  @endphp
  <div class="mt-2.5 w-full rounded-lg border border-sky-200/90 bg-sky-50/70 px-3 py-2.5 text-xs text-sky-950">
    <div class="space-y-1.5">
      @if ($isFileUpdate)
        <div>
          <p class="min-w-0">
            <span class="font-semibold">Previous:</span>
            <span class="whitespace-pre-wrap">{{ filled((string) ($oldFile['name'] ?? '')) ? (string) $oldFile['name'] : 'No previous file captured.' }}</span>
            @if (filled((string) ($oldFile['download_url'] ?? '')))
              <span> — </span>
              <a href="{{ (string) $oldFile['download_url'] }}" class="font-semibold text-sky-800 underline underline-offset-2 hover:text-sky-900">download this file</a>
            @endif
          </p>
        </div>
        <div>
          <p class="min-w-0">
            <span class="font-semibold">Updated:</span>
            <span class="whitespace-pre-wrap">{{ filled((string) ($newFile['name'] ?? '')) ? (string) $newFile['name'] : 'No updated file captured.' }}</span>
          </p>
        </div>
      @else
        <p><span class="font-semibold">Previous:</span> <span class="whitespace-pre-wrap">{{ filled((string) ($update['old_value'] ?? '')) ? (string) $update['old_value'] : 'No previous value captured.' }}</span></p>
        <p><span class="font-semibold">Updated:</span> <span class="whitespace-pre-wrap">{{ filled((string) ($update['new_value'] ?? '')) ? (string) $update['new_value'] : 'No updated value captured.' }}</span></p>
      @endif
      <p><span class="font-semibold">Resubmitted by:</span> {{ $update['resubmitted_by'] ?? 'Unknown' }}</p>
      <p><span class="font-semibold">Resubmitted on:</span> {{ !empty($update['resubmitted_at']) ? \Illuminate\Support\Carbon::parse((string) $update['resubmitted_at'])->format('M d, Y, g:i A') : 'Unknown date' }}</p>
    </div>
  </div>
@endif
