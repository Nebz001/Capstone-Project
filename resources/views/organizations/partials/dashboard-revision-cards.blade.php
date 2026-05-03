@php
    $infoRevisions = is_array($__infoRevisions ?? null) ? $__infoRevisions : [];
    $fileRevisions = is_array($__fileRevisions ?? null) ? $__fileRevisions : [];
    $updatedInfo = is_array($__updatedInfo ?? null) ? $__updatedInfo : [];
    $updatedFiles = is_array($__updatedFiles ?? null) ? $__updatedFiles : [];

    $hasInfoPending = count($infoRevisions) > 0;
    $hasInfoUpdated = count($updatedInfo) > 0;
    $hasFilePending = count($fileRevisions) > 0;
    $hasFileUpdated = count($updatedFiles) > 0;
    $showInfoPanel = $hasInfoPending || $hasInfoUpdated;
    $showFilePanel = $hasFilePending || $hasFileUpdated;
@endphp

@if ($showInfoPanel || $showFilePanel)
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
        @if ($showInfoPanel)
        @php
            $infoCardUpdatedOnly = ! $hasInfoPending && $hasInfoUpdated;
            $infoCardHybrid = $hasInfoPending && $hasInfoUpdated;
            $infoCardUseEmeraldStyle = $infoCardUpdatedOnly;
        @endphp
        <div class="rounded-xl border bg-white px-3.5 py-3 {{ $infoCardUseEmeraldStyle ? 'border-emerald-300 border-l-4 border-l-emerald-400' : 'border-yellow-300 border-l-4 border-l-yellow-400' }}">
            <div class="flex items-center justify-between gap-2">
                <p class="text-[11px] font-bold uppercase tracking-wide {{ $infoCardUseEmeraldStyle ? 'text-emerald-800' : 'text-yellow-900' }}">
                    @if ($infoCardHybrid || $hasInfoPending)
                        Information to Update
                    @else
                        Information Updated
                    @endif
                </p>
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $infoCardUseEmeraldStyle ? 'bg-emerald-100 text-emerald-800' : 'bg-yellow-100 text-yellow-800' }}">
                    @if ($infoCardUseEmeraldStyle)
                        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        Updated
                    @else
                        Pending
                    @endif
                </span>
            </div>
            @if ($hasInfoPending)
                <ul class="mt-2 space-y-1.5">
                    @foreach ($infoRevisions as $item)
                        @php
                            $__rawNote = $item['note'] ?? null;
                            $__noteText = is_scalar($__rawNote) ? trim((string) $__rawNote) : '';
                            $__hasNote = $__noteText !== '' && preg_match('/^(0+)(\\.0+)?$/', $__noteText) !== 1;
                        @endphp
                        <li class="rounded-md px-2 py-1 text-xs text-yellow-950 bg-yellow-50/60">
                            <a href="{{ $item['href'] ?? '#' }}" class="inline-flex w-full items-start gap-1 text-left">
                                <span class="font-semibold underline underline-offset-2">{{ $item['field'] ?? 'Field' }}</span>
                                @if ($__hasNote)
                                    <span>— {{ $__noteText }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($hasInfoUpdated)
                @if ($infoCardHybrid)
                    <p class="mt-3 text-[10px] font-bold uppercase tracking-wide text-emerald-800">Submitted for review</p>
                @endif
                <ul class="{{ $infoCardHybrid ? 'mt-1.5' : 'mt-2' }} space-y-1.5">
                    @foreach ($updatedInfo as $item)
                        <li class="rounded-md px-2 py-1 text-xs text-emerald-900 bg-emerald-50/60">
                            <a href="{{ $item['href'] ?? '#' }}" class="inline-flex w-full items-start gap-1 text-left">
                                <span class="font-semibold underline underline-offset-2">{{ $item['field'] ?? 'Field' }}</span>
                                @php
                                    $oldVal = trim((string) ($item['old_value'] ?? ''));
                                    $newVal = trim((string) ($item['new_value'] ?? ''));
                                @endphp
                                <span>— {{ $oldVal !== '' || $newVal !== '' ? ($oldVal !== '' ? $oldVal.' → '.$newVal : $newVal) : 'Updated value submitted' }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($hasInfoUpdated)
                <p class="mt-2 text-xs {{ $infoCardHybrid ? 'text-slate-600' : 'text-emerald-800' }}">Your updated information has been submitted and is now awaiting SDAO review.</p>
            @endif
        </div>
        @endif

        @if ($showFilePanel)
        <div class="rounded-xl border bg-white px-3.5 py-3 {{ $hasFilePending ? 'border-yellow-300 border-l-4 border-l-yellow-400' : 'border-emerald-300 border-l-4 border-l-emerald-400' }}">
            <div class="flex items-center justify-between gap-2">
                <p class="text-[11px] font-bold uppercase tracking-wide {{ $hasFilePending ? 'text-yellow-900' : 'text-emerald-800' }}">
                    {{ $hasFilePending ? 'Files to Replace' : 'Files Updated' }}
                </p>
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $hasFilePending ? 'bg-yellow-100 text-yellow-800' : 'bg-emerald-100 text-emerald-800' }}">
                    @if ($hasFilePending)
                        Pending
                    @else
                        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        Updated
                    @endif
                </span>
            </div>
            <ul class="mt-2 space-y-1.5">
                @foreach (($hasFilePending ? $fileRevisions : $updatedFiles) as $item)
                    @php
                        $__rawFileNote = $hasFilePending ? ($item['note'] ?? null) : null;
                        $__fileNoteText = is_scalar($__rawFileNote) ? trim((string) $__rawFileNote) : '';
                        $__hasFileNote = $__fileNoteText !== '' && preg_match('/^(0+)(\\.0+)?$/', $__fileNoteText) !== 1;
                    @endphp
                    <li class="rounded-md px-2 py-1 text-xs {{ $hasFilePending ? 'text-yellow-950 bg-yellow-50/60' : 'text-emerald-900 bg-emerald-50/60' }}">
                        <a href="{{ $item['href'] ?? '#' }}" class="inline-flex w-full items-start gap-1 text-left">
                            <span class="font-semibold underline underline-offset-2">{{ $item['field'] ?? 'File' }}</span>
                            @if ($hasFilePending)
                                @if ($__hasFileNote)
                                    <span>— {{ $__fileNoteText }}</span>
                                @endif
                            @else
                                <span>— {{ $item['file_name'] ?? 'New file uploaded' }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            @if (! $hasFilePending && $hasFileUpdated)
                <p class="mt-2 text-xs text-emerald-800">Your updated file has been submitted and is now awaiting SDAO review.</p>
            @endif
        </div>
        @endif
    </div>
@endif
