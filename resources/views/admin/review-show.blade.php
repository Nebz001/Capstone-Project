@extends('layouts.admin')

@section('title', $pageTitle . ' — NU Lipa SDAO')

@section('content')
@php
  $statusClass = match ($status) {
    'PENDING' => 'bg-amber-100 text-amber-800 border border-amber-200',
    'UNDER_REVIEW', 'REVIEWED' => 'bg-blue-100 text-blue-700 border border-blue-200',
    'APPROVED' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    'REJECTED' => 'bg-rose-100 text-rose-700 border border-rose-200',
    'REVISION', 'REVISION_REQUIRED' => 'bg-orange-100 text-orange-700 border border-orange-200',
    default => 'bg-slate-100 text-slate-700 border border-slate-200',
  };
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $pageTitle }}</h1>
    <p class="mt-1 text-sm text-slate-500">Inspect key submission details for review readiness.</p>
  </div>
  <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
    {{ str_replace('_', ' ', $status) }}
  </span>
</div>

<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
  <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
    @foreach ($details as $label => $value)
      <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
        <dd class="mt-1 text-sm text-slate-800">{{ $value }}</dd>
      </div>
    @endforeach
  </dl>

  <div class="mt-6">
    <a href="{{ $backRoute }}" class="inline-flex rounded-lg border border-[#003E9F] px-4 py-2 text-sm font-semibold text-[#003E9F] transition hover:bg-[#003E9F] hover:text-white">
      Back to Review List
    </a>
  </div>
</section>
@endsection

