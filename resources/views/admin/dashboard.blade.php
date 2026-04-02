@extends('layouts.admin')

@section('title', 'SDAO Admin Dashboard — NU Lipa SDAO')

@section('content')
@php
  $overview = [
    ['label' => 'Total Pending Registrations', 'value' => $counts['registrations'], 'route' => route('admin.registrations.index')],
    ['label' => 'Total Pending Renewals', 'value' => $counts['renewals'], 'route' => route('admin.renewals.index')],
    ['label' => 'Total Pending Activity Calendars', 'value' => $counts['calendars'], 'route' => route('admin.calendars.index')],
    ['label' => 'Total Pending Activity Proposals', 'value' => $counts['proposals'], 'route' => route('admin.proposals.index')],
    ['label' => 'Total Pending After Activity Reports', 'value' => $counts['reports'], 'route' => route('admin.reports.index')],
  ];
@endphp

<header class="mb-8">
  <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">SDAO Admin Dashboard</h1>
  <p class="mt-1 text-sm text-slate-500">Review and monitor all major student organization submissions.</p>
</header>

<section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
  @foreach ($overview as $item)
    <a href="{{ $item['route'] }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $item['label'] }}</p>
      <p class="mt-2 text-3xl font-bold text-[#003E9F]">{{ $item['value'] }}</p>
      <p class="mt-2 text-xs font-semibold text-[#003E9F]">Open module</p>
    </a>
  @endforeach
</section>

<section class="mt-8">
  <div class="mb-4">
    <h2 class="text-lg font-bold text-slate-900">Centralized Activity Calendar</h2>
    <p class="mt-1 text-sm text-slate-500">Live monitoring view for all organization events and related submissions.</p>
  </div>
  @include('admin.partials.centralized-calendar')
</section>
@endsection

