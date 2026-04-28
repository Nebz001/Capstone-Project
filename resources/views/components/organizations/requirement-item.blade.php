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
    class="requirement-item {{ $colSpan }} rounded-md px-1.5 py-1 hover:bg-white/60"
    data-requirement-key="{{ $fk }}"
>
    <div class="flex min-w-0 items-start gap-1.5">
        <div class="min-w-0 flex-1">
            <x-forms.choice
                :id="$checkboxId"
                name="requirements[]"
                type="checkbox"
                :value="$value"
                :checked="$isChecked"
                wrapper-class="flex items-start gap-2"
            >
                {{ $label }}
            </x-forms.choice>
        </div>
        <div class="req-attach-toolbar flex min-w-0 max-w-[min(100%,20rem)] shrink-0 items-center justify-end gap-1.5 self-start pt-0.5 sm:max-w-[24rem]">
            <input
                type="file"
                id="req_file_{{ $checkboxId }}"
                name="requirement_files[{{ $fk }}]"
                class="req-file-input sr-only"
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                tabindex="-1"
                aria-hidden="true"
            />
            <span
                class="req-file-name min-w-0 flex-1 truncate text-left text-[10px] font-medium text-slate-600 sm:text-xs"
                aria-live="polite"
            ></span>
            <span class="req-attached-badge hidden shrink-0 text-[10px] font-medium leading-none text-emerald-600" aria-hidden="true">Attached</span>
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
        </div>
    </div>
    @error('requirement_files.'.$fk)
        <p class="req-file-error mt-1.5 text-xs text-rose-600">{{ $message }}</p>
    @enderror
    @error('requirements.'.$value)
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
    <p class="req-client-msg mt-1 hidden text-xs text-rose-600" role="alert"></p>
</div>
