@props(['variant' => 'admin'])

@php
  $label = \App\Models\SystemSetting::activeSemesterLabel();
@endphp

@if ($variant === 'admin')
  <p class="mt-2">
    <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-800 shadow-sm">
      <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Active term</span>
      {{ $label }}
    </span>
  </p>
@elseif ($variant === 'navbar')
  {{-- Matches navbar title stack: 10px gold eyebrow + sm primary line (#003E9F bar palette only) --}}
  <div class="flex min-w-0 flex-col items-end pr-1 text-right sm:pr-2" role="status" aria-live="polite">
    <span class="text-[10px] font-bold uppercase leading-none tracking-[0.2em] text-[#F5C400]/90">Active term</span>
    <span class="mt-0.5 max-w-[11rem] truncate text-sm font-bold leading-snug tracking-tight text-white sm:max-w-[14rem] md:max-w-[18rem]">{{ $label }}</span>
  </div>
@endif
