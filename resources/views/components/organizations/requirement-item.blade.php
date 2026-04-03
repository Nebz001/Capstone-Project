@props([
    'checkboxId',
    'value',
    'label',
    'fileKey' => null,
    'colSpan' => 'sm:col-span-1',
])

@php
    $fk = $fileKey ?? $value;
    $oldReqs = old('requirements', []);
    $oldReqs = is_array($oldReqs) ? $oldReqs : [];
    $isChecked = in_array($value, $oldReqs, true);
@endphp

<div
    class="requirement-item {{ $colSpan }} rounded-md p-2 hover:bg-white/60"
    data-requirement-key="{{ $fk }}"
>
    <div class="flex items-start gap-2">
        <div class="min-w-0 flex-1">
            <x-forms.choice
                :id="$checkboxId"
                name="requirements[]"
                type="checkbox"
                :value="$value"
                :checked="$isChecked"
                wrapper-class="flex items-start gap-3"
            >
                {{ $label }}
            </x-forms.choice>
        </div>
        <div class="flex shrink-0 flex-col items-center gap-0.5 pt-0.5">
            <input
                type="file"
                id="req_file_{{ $checkboxId }}"
                name="requirement_files[{{ $fk }}]"
                class="req-file-input sr-only"
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                tabindex="-1"
                aria-hidden="true"
            />
            <button
                type="button"
                class="req-attach-btn inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-white/90 hover:text-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-500/30 disabled:cursor-not-allowed disabled:opacity-40"
                aria-label="Attach file: {{ $label }}"
                title="Attach file"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
            </button>
            <span class="req-attached-badge hidden text-[10px] font-medium leading-none text-emerald-600" aria-hidden="true">Attached</span>
            <span class="req-file-name max-w-[5.5rem] truncate text-center text-[10px] leading-tight text-slate-500 sm:max-w-[7rem]" aria-live="polite"></span>
        </div>
    </div>
    @error('requirement_files.'.$fk)
        <p class="req-file-error mt-1.5 text-xs text-rose-600">{{ $message }}</p>
    @enderror
    <p class="req-client-msg mt-1 hidden text-xs text-rose-600" role="alert"></p>
</div>
