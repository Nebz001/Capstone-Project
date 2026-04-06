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
@elseif ($variant === 'organization')
  <div class="border-b border-[#003E9F]/20 bg-[#E8F0FF] px-4 py-2 sm:px-6 lg:px-8" role="status" aria-live="polite">
    <div class="mx-auto flex max-w-7xl items-center justify-center gap-2 sm:justify-start">
      <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#003E9F]">Active term</span>
      <span class="text-xs font-semibold text-slate-900">{{ $label }}</span>
    </div>
  </div>
@endif
