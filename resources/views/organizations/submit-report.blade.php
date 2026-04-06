@extends('layouts.organization-portal')

@section('title', 'Submit Report — NU Lipa SDAO')

@section('content')

@php
  $saOrgId = isset($superAdminOrganizationId) && $superAdminOrganizationId ? (int) $superAdminOrganizationId : null;
  $saQ = $saOrgId ? '?organization_id='.$saOrgId : '';
@endphp

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">

    <header class="mb-8">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('organizations.index') }}{{ $saQ }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Back to Dashboard
                </a>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    Submit Report
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Choose a report type to submit for your organization.
                </p>
            </div>
        </div>
    </header>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">

        <a
            href="{{ route('organizations.after-activity-report') }}{{ $saQ }}"
            class="group flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/40 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-[#003E9F]/15"
        >
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#003E9F]/10 text-[#003E9F] transition group-hover:bg-[#003E9F]/15">
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </div>
            <h3 class="mt-4 text-sm font-bold text-slate-900 transition group-hover:text-[#003E9F]">
                Submit Activity Report
            </h3>
            <p class="mt-1.5 flex-1 text-xs leading-relaxed text-slate-500">
                File a structured after-activity report with documentation, evaluation, and attendance for a completed event.
            </p>
            <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-[#003E9F] transition-all duration-150 group-hover:gap-2">
                Open
                <svg class="h-3.5 w-3.5 transition-transform duration-150 group-hover:translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </span>
        </a>

    </div>

</div>

@endsection
