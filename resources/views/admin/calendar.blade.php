@extends('layouts.admin')

@section('title', 'Centralized Activity Calendar — SDAO Admin')

@section('content')
<header class="mb-6">
  <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Centralized Activity Calendar</h1>
  <p class="mt-1 text-sm text-slate-500">Monitor event timelines and submission records across all organizations.</p>
</header>

@include('admin.partials.centralized-calendar')
@endsection

