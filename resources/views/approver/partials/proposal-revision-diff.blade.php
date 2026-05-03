@php
  $rd = $row['revision_diff'] ?? null;
@endphp
@if (is_array($rd) && ! empty($rd['is_updated']))
  @php
    $oldMeta = is_array($rd['old_file_meta'] ?? null) ? $rd['old_file_meta'] : [];
    $newMeta = is_array($rd['new_file_meta'] ?? null) ? $rd['new_file_meta'] : [];
    $oldFile = trim((string) ($oldMeta['original_name'] ?? ''));
    if ($oldFile === '' && ($oldMeta['stored_path'] ?? '') !== '') {
      $oldFile = basename((string) $oldMeta['stored_path']);
    }
    $newFile = trim((string) ($newMeta['original_name'] ?? ''));
    if ($newFile === '' && ($newMeta['stored_path'] ?? '') !== '') {
      $newFile = basename((string) $newMeta['stored_path']);
    }
    $at = $rd['resubmitted_at'] ?? null;
    $atLabel = '';
    if ($at instanceof \Carbon\Carbon) {
      $atLabel = $at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A').' PHT';
    } elseif (is_string($at) && $at !== '') {
      try {
        $atLabel = \Carbon\Carbon::parse($at)->timezone('Asia/Manila')->format('M d, Y g:i A').' PHT';
      } catch (\Throwable) {
        $atLabel = '';
      }
    }
  @endphp
  <div class="mt-3 rounded-xl border border-sky-200 bg-sky-50/90 px-3 py-2.5 text-xs text-slate-800">
    <p class="font-bold text-sky-900">
      <span class="inline-flex rounded-full border border-sky-300 bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-800">Updated</span>
    </p>
    @if ($oldFile !== '' || $newFile !== '')
      <p class="mt-2"><span class="font-semibold text-slate-700">Previous file:</span> {{ $oldFile !== '' ? $oldFile : '—' }}</p>
      <p class="mt-1"><span class="font-semibold text-slate-700">Updated file:</span> {{ $newFile !== '' ? $newFile : '—' }}</p>
    @else
      <p class="mt-2 whitespace-pre-line"><span class="font-semibold text-slate-700">Previous:</span> {{ trim((string) ($rd['old_value'] ?? '')) !== '' ? $rd['old_value'] : '—' }}</p>
      <p class="mt-1 whitespace-pre-line"><span class="font-semibold text-slate-700">Updated:</span> {{ trim((string) ($rd['new_value'] ?? '')) !== '' ? $rd['new_value'] : '—' }}</p>
    @endif
    <p class="mt-2 text-slate-600"><span class="font-semibold text-slate-700">Resubmitted by:</span> {{ $rd['resubmitted_by_name'] ?? '—' }}</p>
    @if ($atLabel !== '')
      <p class="mt-0.5 text-slate-600"><span class="font-semibold text-slate-700">Resubmitted on:</span> {{ $atLabel }}</p>
    @endif
  </div>
@endif
