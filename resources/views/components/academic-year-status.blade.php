@props(['variant' => 'admin'])

@php
  $academicYear = \App\Models\SystemSetting::activeAcademicYear();
@endphp

@if ($variant === 'admin')
  <p class="mt-2">
    <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-800 shadow-sm">
      <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Academic year</span>
      {{ $academicYear }}
    </span>
  </p>
@elseif ($variant === 'navbar')
  <div class="flex min-w-0 flex-col items-end pr-1 text-right sm:pr-2" role="status" aria-live="polite">
    <span class="text-[10px] font-bold uppercase leading-none tracking-[0.2em] text-[#F5C400]/90">Academic year</span>
    <span class="mt-0.5 max-w-[11rem] truncate text-sm font-bold leading-snug tracking-tight text-white sm:max-w-[14rem] md:max-w-[18rem]">{{ $academicYear }}</span>
  </div>
@endif
