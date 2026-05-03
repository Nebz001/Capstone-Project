@php
  $sdgOptions = array_map(fn ($n) => 'SDG '.$n, range(1, 17));
  $selectedTargetSdgs = is_array($selectedTargetSdgs ?? null) ? $selectedTargetSdgs : [];
  $originalSdgSignature = (string) ($originalSdgSignature ?? '');
@endphp
<div
  class="mt-3"
  data-revision-sdg-root
  data-original-sdg-signature="{{ $originalSdgSignature }}"
>
  <x-forms.label for="revise-target-sdg-trigger" required class="{{ $errors->has('field_step1_target_sdg') ? '!text-rose-700' : '' }}">Target SDG</x-forms.label>
  <div class="relative mt-2" data-revise-sdg-dropdown>
    <button
      type="button"
      id="revise-target-sdg-trigger"
      class="flex w-full items-center justify-between rounded-xl border bg-white px-4 py-3 text-left text-sm text-slate-900 shadow-sm transition hover:border-slate-400 focus:outline-none focus:ring-4 {{ $errors->has('field_step1_target_sdg') ? 'border-rose-400 focus:ring-rose-500/20' : 'border-slate-300 focus:ring-sky-500/15' }}"
      aria-haspopup="true"
      aria-expanded="false"
    >
      <span id="revise-target-sdg-trigger-text" class="{{ count($selectedTargetSdgs) > 0 ? 'text-slate-900' : 'text-slate-500' }}">
        {{ count($selectedTargetSdgs) > 0 ? implode(', ', $selectedTargetSdgs) : 'Select one or more SDGs' }}
      </span>
      <svg class="h-5 w-5 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
      </svg>
    </button>
    <div
      id="revise-target-sdg-menu"
      class="absolute left-0 right-0 z-20 mt-2 hidden max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-lg"
      role="menu"
    >
      @foreach ($sdgOptions as $index => $sdgOption)
        <label class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
          <input
            type="checkbox"
            id="revise_target_sdg_{{ $index + 1 }}"
            name="field_step1_target_sdg[]"
            value="{{ $sdgOption }}"
            class="revise-sdg-checkbox h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500/30"
            @checked(in_array($sdgOption, $selectedTargetSdgs, true))
          />
          <span>{{ $sdgOption }}</span>
        </label>
      @endforeach
    </div>
  </div>
  <x-forms.helper class="mt-1.5!">Click the dropdown and check all SDGs that apply.</x-forms.helper>
  @error('field_step1_target_sdg')
    <x-forms.error>{{ $message }}</x-forms.error>
  @enderror
  @error('field_step1_target_sdg.*')
    <x-forms.error>{{ $message }}</x-forms.error>
  @enderror
  <div id="revise-target-sdg-selected-wrap" class="mt-2 {{ count($selectedTargetSdgs) > 0 ? '' : 'hidden' }}">
    <p class="text-xs font-medium text-slate-700">Selected SDGs</p>
    <div id="revise-target-sdg-selected-list" class="mt-2 flex flex-wrap gap-2">
      @foreach ($selectedTargetSdgs as $selectedSdg)
        <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">{{ $selectedSdg }}</span>
      @endforeach
    </div>
  </div>
</div>
