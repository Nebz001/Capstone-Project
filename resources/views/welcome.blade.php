@extends('layouts.guest')

@section('title', 'NU Lipa Student Development and Activities Office Document Management System')

@section('content')
@php
$portalModules = [
[
'title' => 'Organization Registration',
'description' => 'Submit new organization registration requirements for Student Development and Activities Office review and validation.',
'action' => 'Start registration',
'href' => route('organizations.register'),
'icon' => 'OR',
],
[
'title' => 'Renewal Submission',
'description' => 'Upload annual renewal documents and monitor compliance checklist completion.',
'action' => 'Submit renewal',
'href' => route('organizations.renew'),
'icon' => 'RN',
],
[
'title' => 'Activity Request',
'description' => 'File activity and event-related requests with complete document attachments.',
'action' => 'Request activity',
'href' => route('organizations.activity-submission'),
'icon' => 'AR',
],
];
@endphp

{{-- ───────────────────────── HERO ───────────────────────── --}}
<section class="relative flex min-h-[600px] items-center overflow-hidden lg:min-h-[680px]">

  {{-- Background image --}}
  <div class="absolute inset-0" aria-hidden="true">
    <img
      src="{{ asset('images/landing/nulp-building.png') }}"
      alt=""
      class="h-full w-full object-cover object-center">
    {{-- Layered gradient: strong left → fading right --}}
    <div class="absolute inset-0 bg-gradient-to-r from-[#001A4D]/92 via-[#003E9F]/72 to-[#003E9F]/25"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-[#001A4D]/30"></div>
  </div>

  {{-- Content --}}
  <div class="relative z-10 mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8 lg:py-28">
    <div class="max-w-2xl">

      <p class="mb-4 text-xs font-semibold uppercase tracking-[0.22em] text-[#F5C400]/90">
        National University – Lipa
      </p>

      <h1 class="text-3xl font-bold leading-tight tracking-tight text-white sm:text-4xl lg:text-[2.75rem] lg:leading-[1.18]">
        NU Lipa Student Development and Activities Office Document<br class="hidden sm:block"> Management System
      </h1>

      <p class="mt-5 max-w-xl text-base leading-7 text-blue-100/85 sm:text-lg">
        A centralized portal for submission, review, approval tracking, and
        organization document management for Student Development and Activities Office staff and recognized student organizations.
      </p>

      <div class="mt-8 flex flex-wrap gap-3">
        <a
          href="{{ route('login') }}"
          class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-[#003E9F] px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-white/20">
          Access Portal
        </a>
        <a
          href="{{ route('organizations.register') }}"
          class="inline-flex items-center justify-center rounded-xl border border-[#F5C400]/60 bg-[#F5C400]/15 px-6 py-3 text-sm font-semibold text-[#F5C400] backdrop-blur-sm transition hover:bg-[#F5C400]/25 focus:outline-none focus:ring-4 focus:ring-[#F5C400]/25">
          Register Organization
        </a>
      </div>

    </div>
  </div>

</section>

{{-- ───────────── RECOGNIZED STUDENT ORGANIZATIONS (PUBLIC) ───────────── --}}
<section id="recognized-rsos" class="border-t border-slate-200/80 bg-slate-50 px-4 py-14 sm:px-6 sm:py-16 lg:px-8 lg:py-20">
  <div class="mx-auto w-full max-w-7xl">
    <div class="border-l-4 border-[#003E9F] pl-4">
      <div class="flex items-center gap-2 text-[#003E9F]">
        <svg class="h-4 w-4 flex-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.09 9.09 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-.679 1.133c-.481.19-.99.299-1.513.299m0 0A3 3 0 0 1 6 18.719m12 0a3 3 0 0 1-3-3v-1.5m-6 0v1.5a3 3 0 0 1-3 3" />
        </svg>
        <span class="text-[10px] font-bold uppercase tracking-[0.2em]">Directory</span>
      </div>
      <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Recognized student organizations</h2>
      <p class="mt-1.5 max-w-2xl text-sm text-slate-600">
        Student organizations officially recognized by the Student Development and Activities Office and active in the current system. This list is updated as organizations complete accreditation.
      </p>
    </div>

    @if ($approvedOrganizations->isEmpty())
      <div class="mt-8 rounded-2xl border border-dashed border-slate-200 bg-white/80 px-6 py-12 text-center">
        <p class="text-sm text-slate-600">There are no active recognized organizations listed yet. Check back after new groups complete the registration process.</p>
      </div>
    @else
      <p class="mt-6 text-xs font-medium text-slate-500">
        @php $n = $approvedOrganizations->count(); @endphp
        {{ $n }} organization{{ $n === 1 ? '' : 's' }}
      </p>
      <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($approvedOrganizations as $org)
          @php
            $typeLine = match ($org->organization_type ?? '') {
              'extra_curricular' => 'Extra-curricular',
              'co_curricular' => 'Co-curricular',
              default => null,
            };
          @endphp
          <article class="flex flex-col rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:border-[#003E9F]/25 hover:shadow-md hover:shadow-[#003E9F]/5">
            <h3 class="text-base font-semibold leading-snug text-slate-900">{{ $org->organization_name }}</h3>
            @if (filled($org->college_department))
              <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $org->college_department }}</p>
            @endif
            @if ($typeLine)
              <p class="mt-3 text-xs font-medium uppercase tracking-wide text-slate-400">{{ $typeLine }}</p>
            @endif
          </article>
        @endforeach
      </div>
    @endif
  </div>
</section>

{{-- ─────────────────── PORTAL SERVICES ─────────────────── --}}
<section id="services" class="bg-white px-4 py-14 sm:px-6 sm:py-16 lg:px-8 lg:py-20">
  <div class="mx-auto w-full max-w-7xl">

    {{-- Section header --}}
    <div class="border-l-4 border-[#003E9F] pl-4">
      <div class="flex items-center gap-2 text-[#003E9F]">
        <svg class="h-4 w-4 flex-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
        </svg>
        <span class="text-[10px] font-bold uppercase tracking-[0.2em]">Available Modules</span>
      </div>
      <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Portal Services</h2>
      <p class="mt-1.5 text-sm text-slate-600">
        Main modules for organization compliance, activity processing, and submission monitoring.
      </p>
    </div>

    {{-- Service Cards --}}
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">

      {{-- Organization Registration --}}
      <article class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:border-[#003E9F]/30 hover:shadow-lg hover:shadow-[#003E9F]/8 sm:p-6">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-[#D8E3F8] bg-[#EDF3FF] text-[#003E9F] transition-colors duration-200 group-hover:border-[#003E9F] group-hover:bg-[#003E9F] group-hover:text-white">
          <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
          </svg>
        </div>
        <h3 class="mt-4 text-base font-semibold text-slate-900">Organization Registration</h3>
        <p class="mt-2 flex-1 text-sm leading-6 text-slate-600">Submit new organization registration requirements for Student Development and Activities Office review and validation.</p>
        <a
          href="{{ route('organizations.register') }}"
          class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-[#003E9F] transition-all hover:gap-2.5 hover:text-[#00327F]">
          Start registration
          <svg class="h-4 w-4 flex-none" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 8h10M9 4l4 4-4 4" />
          </svg>
        </a>
      </article>

      {{-- Renewal Submission --}}
      <article class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:border-[#003E9F]/30 hover:shadow-lg hover:shadow-[#003E9F]/8 sm:p-6">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-[#D8E3F8] bg-[#EDF3FF] text-[#003E9F] transition-colors duration-200 group-hover:border-[#003E9F] group-hover:bg-[#003E9F] group-hover:text-white">
          <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
        </div>
        <h3 class="mt-4 text-base font-semibold text-slate-900">Renewal Submission</h3>
        <p class="mt-2 flex-1 text-sm leading-6 text-slate-600">Upload annual renewal documents and monitor compliance checklist completion.</p>
        <a
          href="{{ route('login') }}"
          class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-[#003E9F] transition-all hover:gap-2.5 hover:text-[#00327F]">
          Submit renewal
          <svg class="h-4 w-4 flex-none" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 8h10M9 4l4 4-4 4" />
          </svg>
        </a>
      </article>

      {{-- Activity Request --}}
      <article class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:border-[#003E9F]/30 hover:shadow-lg hover:shadow-[#003E9F]/8 sm:p-6">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-[#D8E3F8] bg-[#EDF3FF] text-[#003E9F] transition-colors duration-200 group-hover:border-[#003E9F] group-hover:bg-[#003E9F] group-hover:text-white">
          <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
          </svg>
        </div>
        <h3 class="mt-4 text-base font-semibold text-slate-900">Activity Request</h3>
        <p class="mt-2 flex-1 text-sm leading-6 text-slate-600">File activity and event-related requests with complete document attachments.</p>
        <a
          href="{{ route('organizations.activity-submission') }}"
          class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-[#003E9F] transition-all hover:gap-2.5 hover:text-[#00327F]">
          Request activity
          <svg class="h-4 w-4 flex-none" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 8h10M9 4l4 4-4 4" />
          </svg>
        </a>
      </article>

    </div>

  </div>
</section>

{{-- ──────────────────── ABOUT THE SYSTEM ──────────────────── --}}
<section id="about" class="border-t border-slate-100 bg-slate-50 px-4 py-14 sm:px-6 sm:py-16 lg:px-8 lg:py-20">
  <div class="mx-auto w-full max-w-7xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

      {{-- Top accent bar --}}
      <div class="h-1 w-full bg-gradient-to-r from-[#003E9F] via-[#0047B8] to-[#F5C400]"></div>

      <div class="px-8 py-10 sm:px-10 sm:py-12">

        {{-- Icon badge + heading --}}
        <div class="flex flex-col items-center text-center">
          <div class="flex h-13 w-13 items-center justify-center rounded-full border border-[#D8E3F8] bg-[#EDF3FF] text-[#003E9F]">
            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
            </svg>
          </div>

          <h2 class="mt-4 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">About the System</h2>

          <p class="mt-3 max-w-2xl text-base leading-7 text-slate-600">
            The NU Lipa Student Development and Activities Office Document Management System centralizes document submission and approval
            workflows for recognized student organizations, supporting efficient processing, consistent
            compliance checks, and reliable records across Student Development and Activities Office operations.
          </p>
        </div>

        {{-- Feature highlights --}}
        <div class="mt-10 grid gap-4 border-t border-slate-100 pt-8 sm:grid-cols-2 lg:grid-cols-4">

          <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 flex-none items-center justify-center rounded-lg border border-[#D8E3F8] bg-[#EDF3FF] text-[#003E9F]">
              <svg class="h-4.5 w-4.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-slate-900">Centralized Submissions</p>
              <p class="mt-0.5 text-xs leading-5 text-slate-500">All document uploads in one official portal.</p>
            </div>
          </div>

          <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 flex-none items-center justify-center rounded-lg border border-[#D8E3F8] bg-[#EDF3FF] text-[#003E9F]">
              <svg class="h-4.5 w-4.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-slate-900">Approval Workflow Tracking</p>
              <p class="mt-0.5 text-xs leading-5 text-slate-500">Live status updates at every review stage.</p>
            </div>
          </div>

          <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 flex-none items-center justify-center rounded-lg border border-[#D8E3F8] bg-[#EDF3FF] text-[#003E9F]">
              <svg class="h-4.5 w-4.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-slate-900">Organized Compliance Records</p>
              <p class="mt-0.5 text-xs leading-5 text-slate-500">Structured filing for every organization requirement.</p>
            </div>
          </div>

          <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 flex-none items-center justify-center rounded-lg border border-[#D8E3F8] bg-[#EDF3FF] text-[#003E9F]">
              <svg class="h-4.5 w-4.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 3.741-1.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-slate-900">Institutional Visibility</p>
              <p class="mt-0.5 text-xs leading-5 text-slate-500">Student Development and Activities Office oversight across all registered organizations.</p>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>

<x-feedback.toast />

@endsection