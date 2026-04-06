@extends('layouts.organization-portal')

@section('title', 'Manage Organization — NU Lipa SDAO')

@section('content')

@php
  $saOrgId = isset($superAdminOrganizationId) && $superAdminOrganizationId ? (int) $superAdminOrganizationId : null;
  $saQ = $saOrgId ? '?organization_id='.$saOrgId : '';
@endphp

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">

  {{-- ── Page Header ──────────────────────────────────────────────── --}}
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
          Manage Organization
        </h1>
        <p class="mt-1 text-sm text-slate-500">
          Choose an action to manage your student organization.
        </p>
      </div>
    </div>
  </header>

  {{-- ── Action Cards ────────────────────────────────────────────── --}}
  <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">

    {{-- Register Organization --}}
    <a
      href="{{ route('organizations.register') }}"
      class="group flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/40 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-[#003E9F]/15">
      <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#003E9F]/10 text-[#003E9F] transition group-hover:bg-[#003E9F]/15">
        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
      </div>
      <h3 class="mt-4 text-sm font-bold text-slate-900 transition group-hover:text-[#003E9F]">
        Register Organization
      </h3>
      <p class="mt-1.5 flex-1 text-xs leading-relaxed text-slate-500">
        Submit a new organization application for recognition and accreditation with SDAO.
      </p>
      <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-[#003E9F] transition-all duration-150 group-hover:gap-2">
        Open
        <svg class="h-3.5 w-3.5 transition-transform duration-150 group-hover:translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
      </span>
    </a>

    {{-- Renew Organization --}}
    <a
      href="{{ route('organizations.renew') }}{{ $saQ }}"
      class="group flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/40 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-[#003E9F]/15">
      <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#003E9F]/10 text-[#003E9F] transition group-hover:bg-[#003E9F]/15">
        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
        </svg>
      </div>
      <h3 class="mt-4 text-sm font-bold text-slate-900 transition group-hover:text-[#003E9F]">
        Renew Organization
      </h3>
      <p class="mt-1.5 flex-1 text-xs leading-relaxed text-slate-500">
        Submit a renewal application for the current academic year to maintain active status.
      </p>
      <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-[#003E9F] transition-all duration-150 group-hover:gap-2">
        Open
        <svg class="h-3.5 w-3.5 transition-transform duration-150 group-hover:translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
      </span>
    </a>

    {{-- Organization Profile --}}
    <a
      href="{{ route('organizations.profile') }}{{ $saQ }}"
      class="group flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/40 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-[#003E9F]/15">
      <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#003E9F]/10 text-[#003E9F] transition group-hover:bg-[#003E9F]/15">
        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
        </svg>
      </div>
      <h3 class="mt-4 text-sm font-bold text-slate-900 transition group-hover:text-[#003E9F]">
        Organization Profile
      </h3>
      <p class="mt-1.5 flex-1 text-xs leading-relaxed text-slate-500">
        View and update your organization's profile information and details.
      </p>
      <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-[#003E9F] transition-all duration-150 group-hover:gap-2">
        Open
        <svg class="h-3.5 w-3.5 transition-transform duration-150 group-hover:translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
      </span>
    </a>

    {{-- Manage Officers --}}
    <a
      href="#"
      class="group flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/40 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-[#003E9F]/15">
      <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#003E9F]/10 text-[#003E9F] transition group-hover:bg-[#003E9F]/15">
        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
        </svg>
      </div>
      <h3 class="mt-4 text-sm font-bold text-slate-900 transition group-hover:text-[#003E9F]">
        Manage Officers
      </h3>
      <p class="mt-1.5 flex-1 text-xs leading-relaxed text-slate-500">
        Add, edit, or remove your organization's officers and their roles.
      </p>
      <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-[#003E9F] transition-all duration-150 group-hover:gap-2">
        Open
        <svg class="h-3.5 w-3.5 transition-transform duration-150 group-hover:translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
      </span>
    </a>

    {{-- Submitted Documents --}}
    <a
      href="{{ route('organizations.submitted-documents') }}{{ $saQ }}"
      class="group flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/40 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-[#003E9F]/15">
      <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#003E9F]/10 text-[#003E9F] transition group-hover:bg-[#003E9F]/15">
        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
        </svg>
      </div>
      <h3 class="mt-4 text-sm font-bold text-slate-900 transition group-hover:text-[#003E9F]">
        Submitted Documents
      </h3>
      <p class="mt-1.5 flex-1 text-xs leading-relaxed text-slate-500">
        View all documents submitted for your organization's records.
      </p>
      <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-[#003E9F] transition-all duration-150 group-hover:gap-2">
        Open
        <svg class="h-3.5 w-3.5 transition-transform duration-150 group-hover:translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
      </span>
    </a>

  </div>

</div>

@endsection