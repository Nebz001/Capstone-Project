<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Student Development and Activities Office Admin')</title>
  <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

  @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  @endif
</head>
<body class="min-h-screen bg-slate-100 antialiased">
  @php
    $authUser = auth()->user();
    $isSuperAdmin = $authUser?->isSuperAdmin();
    $isRoleApprover = $authUser?->isRoleBasedApprover();
    $dashboardRoute = $isRoleApprover ? route('approver.dashboard') : route('admin.dashboard');
    $isSubmissionsGroupActive = request()->routeIs('admin.submissions.register')
        || request()->routeIs('admin.submissions.renew')
        || request()->routeIs('admin.submissions.activity-calendar')
        || request()->routeIs('admin.submissions.activity-proposal');
    $isReviewGroupActive = request()->routeIs('admin.registrations.*')
        || request()->routeIs('admin.renewals.*')
        || request()->routeIs('admin.calendars.*')
        || request()->routeIs('admin.proposals.*')
        || request()->routeIs('admin.reports.*');
    $isAccountsGroupActive = request()->routeIs('admin.accounts.*');
  @endphp
  <div class="min-h-screen lg:pl-[260px]">
    <aside class="relative border-b border-slate-200 bg-[#003E9F] text-white lg:fixed lg:inset-y-0 lg:left-0 lg:w-[260px] lg:border-b-0 lg:border-r lg:border-white/10 lg:overflow-hidden">
      <div class="pointer-events-none absolute inset-y-0 right-0 hidden w-px bg-[#F5C400]/70 lg:block" aria-hidden="true"></div>
      <div class="px-5 py-5 lg:pb-4">
        <a href="{{ $dashboardRoute }}" class="flex items-center gap-3 border-b border-[#F5C400]/25 pb-4">
          <img src="{{ asset('images/logos/nu-logo-onlyy.png') }}" alt="NU Lipa" class="h-10 w-auto" />
          <div class="min-w-0">
            <p class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.2em] text-[#F5C400]">
              <span class="inline-block h-1.5 w-1.5 rounded-full bg-[#F5C400]" aria-hidden="true"></span>
              NU Lipa
            </p>
            <p class="truncate text-sm font-semibold">
              @if ($isSuperAdmin)
                Super Admin
              @elseif ($isRoleApprover)
                {{ $authUser?->role?->display_name ?? 'Approver' }}
              @else
                Student Development and Activities Office Admin
              @endif
            </p>
          </div>
        </a>
      </div>

      <nav class="space-y-4 px-3 pb-4 lg:h-[calc(100vh-96px)] lg:overflow-y-auto" aria-label="Admin navigation">
        <div class="space-y-1.5">
          <p class="inline-flex items-center gap-1.5 px-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-[#F5C400]/90">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-[#F5C400]/90" aria-hidden="true"></span>
            Overview
          </p>
          <a
            href="{{ $dashboardRoute }}"
            class="group flex items-center gap-2.5 rounded-xl border-l-2 px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.dashboard') || request()->routeIs('approver.dashboard') ? 'border-[#F5C400] bg-linear-to-r from-white/28 to-white/12 text-white shadow-sm ring-1 ring-white/15' : 'border-transparent text-blue-100 hover:border-[#F5C400]/55 hover:bg-white/10 hover:text-white' }}"
          >
            <svg class="h-4 w-4 shrink-0 {{ request()->routeIs('admin.dashboard') || request()->routeIs('approver.dashboard') ? 'text-[#F5C400]' : 'text-blue-200 group-hover:text-[#F5C400]' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h7.5v7.5h-7.5v-7.5Zm9 0h7.5v4.5h-7.5v-4.5Zm0 6h7.5v10.5h-7.5V9.75Zm-9 3h7.5v7.5h-7.5v-7.5Z" />
            </svg>
            <span>Dashboard</span>
          </a>
        </div>

        @if (auth()->user()?->isSuperAdmin())
          <div class="space-y-1.5 border-t border-white/10 pt-4">
            <button
              type="button"
              class="flex w-full items-center justify-between px-3 text-left"
              data-sidebar-toggle="sdao-submissions"
              aria-controls="sidebar-group-sdao-submissions"
              aria-expanded="{{ $isSubmissionsGroupActive ? 'true' : 'false' }}"
            >
              <p class="inline-flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.2em] text-[#F5C400]/90">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-[#F5C400]/90" aria-hidden="true"></span>
                SDAO Submissions
              </p>
              <svg
                class="h-4 w-4 text-[#F5C400]/90 transition-transform duration-200 {{ $isSubmissionsGroupActive ? 'rotate-180' : '' }}"
                data-sidebar-chevron="sdao-submissions"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.9"
                stroke="currentColor"
                aria-hidden="true"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
              </svg>
            </button>
            <div id="sidebar-group-sdao-submissions" class="space-y-1 pl-2 {{ $isSubmissionsGroupActive ? '' : 'hidden' }}">
              <a href="{{ route('admin.submissions.register') }}" class="group flex items-center gap-2.5 rounded-xl border-l-2 px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.submissions.register') ? 'border-[#F5C400] bg-linear-to-r from-white/28 to-white/12 text-white shadow-sm ring-1 ring-white/15' : 'border-transparent text-blue-100 hover:border-[#F5C400]/55 hover:bg-white/10 hover:text-white' }}">
                <svg class="h-4 w-4 shrink-0 {{ request()->routeIs('admin.submissions.register') ? 'text-[#F5C400]' : 'text-blue-200 group-hover:text-[#F5C400]' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                <span>Register organization</span>
              </a>
              <a href="{{ route('admin.submissions.renew') }}" class="group flex items-center gap-2.5 rounded-xl border-l-2 px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.submissions.renew') ? 'border-[#F5C400] bg-linear-to-r from-white/28 to-white/12 text-white shadow-sm ring-1 ring-white/15' : 'border-transparent text-blue-100 hover:border-[#F5C400]/55 hover:bg-white/10 hover:text-white' }}">
                <svg class="h-4 w-4 shrink-0 {{ request()->routeIs('admin.submissions.renew') ? 'text-[#F5C400]' : 'text-blue-200 group-hover:text-[#F5C400]' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>
                <span>Renew organization</span>
              </a>
              <a href="{{ route('admin.submissions.activity-calendar') }}" class="group flex items-center gap-2.5 rounded-xl border-l-2 px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.submissions.activity-calendar') ? 'border-[#F5C400] bg-linear-to-r from-white/28 to-white/12 text-white shadow-sm ring-1 ring-white/15' : 'border-transparent text-blue-100 hover:border-[#F5C400]/55 hover:bg-white/10 hover:text-white' }}">
                <svg class="h-4 w-4 shrink-0 {{ request()->routeIs('admin.submissions.activity-calendar') ? 'text-[#F5C400]' : 'text-blue-200 group-hover:text-[#F5C400]' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75" /></svg>
                <span>Submit activity calendar</span>
              </a>
              <a href="{{ route('admin.submissions.activity-proposal') }}" class="group flex items-center gap-2.5 rounded-xl border-l-2 px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.submissions.activity-proposal') ? 'border-[#F5C400] bg-linear-to-r from-white/28 to-white/12 text-white shadow-sm ring-1 ring-white/15' : 'border-transparent text-blue-100 hover:border-[#F5C400]/55 hover:bg-white/10 hover:text-white' }}">
                <svg class="h-4 w-4 shrink-0 {{ request()->routeIs('admin.submissions.activity-proposal') ? 'text-[#F5C400]' : 'text-blue-200 group-hover:text-[#F5C400]' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6 9-3 3m0 0-3-3m3 3V9m-5.25 9.75h12.75A2.25 2.25 0 0 0 21 16.5V7.5a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                <span>Submit activity proposal</span>
              </a>
            </div>
          </div>
        @endif

        @if (! $isRoleApprover)
        <div class="space-y-1.5 border-t border-white/10 pt-4">
          <button
            type="button"
            class="flex w-full items-center justify-between px-3 text-left"
            data-sidebar-toggle="review-modules"
            aria-controls="sidebar-group-review-modules"
            aria-expanded="{{ $isReviewGroupActive ? 'true' : 'false' }}"
          >
            <p class="inline-flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.2em] text-[#F5C400]/90"><span class="inline-block h-1.5 w-1.5 rounded-full bg-[#F5C400]/90" aria-hidden="true"></span>Review Modules</p>
            <svg
              class="h-4 w-4 text-[#F5C400]/90 transition-transform duration-200 {{ $isReviewGroupActive ? 'rotate-180' : '' }}"
              data-sidebar-chevron="review-modules"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.9"
              stroke="currentColor"
              aria-hidden="true"
            ><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
          </button>
          <div id="sidebar-group-review-modules" class="space-y-1 pl-2 {{ $isReviewGroupActive ? '' : 'hidden' }}">
          <a href="{{ route('admin.registrations.index') }}" class="group flex items-center gap-2.5 rounded-xl border-l-2 px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.registrations.*') ? 'border-[#F5C400] bg-linear-to-r from-white/28 to-white/12 text-white shadow-sm ring-1 ring-white/15' : 'border-transparent text-blue-100 hover:border-[#F5C400]/55 hover:bg-white/10 hover:text-white' }}">
            <svg class="h-4 w-4 shrink-0 {{ request()->routeIs('admin.registrations.*') ? 'text-[#F5C400]' : 'text-blue-200 group-hover:text-[#F5C400]' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2.25 4.5H6.75A2.25 2.25 0 0 1 4.5 18.25V5.75A2.25 2.25 0 0 1 6.75 3.5h7.5L19.5 8.75v9.5a2.25 2.25 0 0 1-2.25 2.25Z" />
            </svg>
            <span>Registrations</span>
          </a>
          <a href="{{ route('admin.renewals.index') }}" class="group flex items-center gap-2.5 rounded-xl border-l-2 px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.renewals.*') ? 'border-[#F5C400] bg-linear-to-r from-white/28 to-white/12 text-white shadow-sm ring-1 ring-white/15' : 'border-transparent text-blue-100 hover:border-[#F5C400]/55 hover:bg-white/10 hover:text-white' }}">
            <svg class="h-4 w-4 shrink-0 {{ request()->routeIs('admin.renewals.*') ? 'text-[#F5C400]' : 'text-blue-200 group-hover:text-[#F5C400]' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
            </svg>
            <span>Renewals</span>
          </a>
          <a href="{{ route('admin.calendars.index') }}" class="group flex items-center gap-2.5 rounded-xl border-l-2 px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.calendars.*') ? 'border-[#F5C400] bg-linear-to-r from-white/28 to-white/12 text-white shadow-sm ring-1 ring-white/15' : 'border-transparent text-blue-100 hover:border-[#F5C400]/55 hover:bg-white/10 hover:text-white' }}">
            <svg class="h-4 w-4 shrink-0 {{ request()->routeIs('admin.calendars.*') ? 'text-[#F5C400]' : 'text-blue-200 group-hover:text-[#F5C400]' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
            <span>Activity Calendars</span>
          </a>
          <a href="{{ route('admin.proposals.index') }}" class="group flex items-center gap-2.5 rounded-xl border-l-2 px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.proposals.*') ? 'border-[#F5C400] bg-linear-to-r from-white/28 to-white/12 text-white shadow-sm ring-1 ring-white/15' : 'border-transparent text-blue-100 hover:border-[#F5C400]/55 hover:bg-white/10 hover:text-white' }}">
            <svg class="h-4 w-4 shrink-0 {{ request()->routeIs('admin.proposals.*') ? 'text-[#F5C400]' : 'text-blue-200 group-hover:text-[#F5C400]' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 0H5.625C5.004 2.25 4.5 2.754 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            <span>Activity Proposals</span>
          </a>
          <a href="{{ route('admin.reports.index') }}" class="group flex items-center gap-2.5 rounded-xl border-l-2 px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.reports.*') ? 'border-[#F5C400] bg-linear-to-r from-white/28 to-white/12 text-white shadow-sm ring-1 ring-white/15' : 'border-transparent text-blue-100 hover:border-[#F5C400]/55 hover:bg-white/10 hover:text-white' }}">
            <svg class="h-4 w-4 shrink-0 {{ request()->routeIs('admin.reports.*') ? 'text-[#F5C400]' : 'text-blue-200 group-hover:text-[#F5C400]' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            <span>After Activity Reports</span>
          </a>
          </div>
        </div>
        @else
        <div class="space-y-1.5 border-t border-white/10 pt-4">
          <p class="inline-flex items-center gap-1.5 px-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-[#F5C400]/90">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-[#F5C400]/90" aria-hidden="true"></span>
            Approvals
          </p>
          <a
            href="{{ route('approver.dashboard') }}"
            class="group flex items-center gap-2.5 rounded-xl border-l-2 px-3 py-2 text-sm font-medium transition {{ request()->routeIs('approver.*') ? 'border-[#F5C400] bg-linear-to-r from-white/28 to-white/12 text-white shadow-sm ring-1 ring-white/15' : 'border-transparent text-blue-100 hover:border-[#F5C400]/55 hover:bg-white/10 hover:text-white' }}"
          >
            <svg class="h-4 w-4 shrink-0 {{ request()->routeIs('approver.*') ? 'text-[#F5C400]' : 'text-blue-200 group-hover:text-[#F5C400]' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 6.75A2.25 2.25 0 0 1 5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5A2.25 2.25 0 0 1 18.75 19.5H5.25A2.25 2.25 0 0 1 3 17.25V6.75Zm3.75 2.25h10.5m-10.5 3h6m-6 3h4.5" />
            </svg>
            <span>My approval queue</span>
          </a>
        </div>
        @endif

        @if (! $isRoleApprover)
        <div class="space-y-1.5 border-t border-white/10 pt-4">
          <button
            type="button"
            class="flex w-full items-center justify-between px-3 text-left"
            data-sidebar-toggle="account-management"
            aria-controls="sidebar-group-account-management"
            aria-expanded="{{ $isAccountsGroupActive ? 'true' : 'false' }}"
          >
            <p class="inline-flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.2em] text-[#F5C400]/90"><span class="inline-block h-1.5 w-1.5 rounded-full bg-[#F5C400]/90" aria-hidden="true"></span>Account Management</p>
            <svg
              class="h-4 w-4 text-[#F5C400]/90 transition-transform duration-200 {{ $isAccountsGroupActive ? 'rotate-180' : '' }}"
              data-sidebar-chevron="account-management"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.9"
              stroke="currentColor"
              aria-hidden="true"
            ><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
          </button>
          <div id="sidebar-group-account-management" class="space-y-1 pl-2 {{ $isAccountsGroupActive ? '' : 'hidden' }}">
          <a href="{{ route('admin.accounts.index') }}" class="group flex items-center gap-2.5 rounded-xl border-l-2 px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.accounts.*') ? 'border-[#F5C400] bg-linear-to-r from-white/28 to-white/12 text-white shadow-sm ring-1 ring-white/15' : 'border-transparent text-blue-100 hover:border-[#F5C400]/55 hover:bg-white/10 hover:text-white' }}">
            <svg class="h-4 w-4 shrink-0 {{ request()->routeIs('admin.accounts.*') ? 'text-[#F5C400]' : 'text-blue-200 group-hover:text-[#F5C400]' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.964 0a9 9 0 1 0-11.964 0m11.964 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            <span>User accounts</span>
          </a>
          </div>
        </div>
        @endif

        <div class="space-y-1.5 border-t border-white/15 pt-4">
          <p class="px-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-red-200/90">Session</p>
          <button
            type="button"
            id="admin-logout-trigger"
            class="group flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-sm font-semibold text-red-200 transition hover:bg-red-500/20 hover:text-red-100 focus:outline-none focus:ring-2 focus:ring-red-200/40"
          >
            <svg class="h-4 w-4 shrink-0 text-red-200 transition group-hover:text-red-100" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-7.5A2.25 2.25 0 0 0 3.75 5.25v13.5A2.25 2.25 0 0 0 6 21h7.5a2.25 2.25 0 0 0 2.25-2.25V15m-3 0 3-3m0 0 3 3m-3-3H9" />
            </svg>
            <span>Log out</span>
          </button>
          <form method="POST" action="{{ route('logout') }}" id="admin-logout-form" class="hidden">
            @csrf
          </form>
        </div>
      </nav>
    </aside>

    <div class="flex min-h-screen flex-col">
      <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex w-full max-w-screen-2xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
          <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#003E9F]">
              @if ($isSuperAdmin)
                Student Development and Activities Office Super Admin Portal
              @elseif ($isRoleApprover)
                Student Development and Activities Office Approver Portal
              @else
                Student Development and Activities Office Admin Portal
              @endif
            </p>
            <p class="text-xs text-slate-500">{{ now()->format('l, F j, Y') }}</p>
            @if (! $isSuperAdmin && ! $isRoleApprover)
              <x-active-term-status variant="admin" />
              <x-academic-year-status variant="admin" />
            @endif
          </div>
          <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
            @if ($isSuperAdmin)
              @php
                $headerActiveSemester = \App\Models\SystemSetting::activeSemester();
                $headerActiveAcademicYear = \App\Models\SystemSetting::activeAcademicYear();
              @endphp
              <form
                method="POST"
                action="{{ route('admin.settings.active-term') }}"
                class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 shadow-sm"
                id="admin-active-term-form"
              >
                @csrf
                @method('PATCH')
                <label for="admin-active-term" class="whitespace-nowrap text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">Active term</label>
                <select
                  id="admin-active-term"
                  name="active_semester"
                  class="min-w-[8.5rem] cursor-pointer rounded-lg border border-slate-300 bg-white py-1.5 pl-2.5 pr-8 text-xs font-semibold text-slate-800 shadow-sm focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
                  onchange="this.form.submit()"
                  title="Official active term for the current academic year (system-wide)"
                >
                  <option value="term_1" @selected($headerActiveSemester === 'term_1')>1st Term</option>
                  <option value="term_2" @selected($headerActiveSemester === 'term_2')>2nd Term</option>
                  <option value="term_3" @selected($headerActiveSemester === 'term_3')>3rd Term</option>
                </select>
              </form>
              <form
                method="POST"
                action="{{ route('admin.settings.academic-year') }}"
                class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 shadow-sm"
                id="admin-academic-year-form"
              >
                @csrf
                @method('PATCH')
                <label for="admin-academic-year" class="whitespace-nowrap text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">Academic year</label>
                <input
                  id="admin-academic-year"
                  name="active_academic_year"
                  type="text"
                  pattern="^\d{4}-\d{4}$"
                  class="min-w-[8.5rem] rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-sm focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
                  value="{{ $headerActiveAcademicYear }}"
                  title="Official academic year in YYYY-YYYY format (e.g., 2025-2026)"
                  onchange="this.form.submit()"
                />
              </form>
            @endif
            <span class="hidden text-sm font-medium text-slate-700 sm:block">{{ auth()->user()?->full_name }}</span>
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-[#003E9F]/10 text-sm font-bold text-[#003E9F]">
              {{ strtoupper(substr(auth()->user()?->first_name ?? 'A', 0, 1)) }}
            </div>
          </div>
        </div>
      </header>

      <main class="mx-auto w-full max-w-screen-2xl flex-1 px-4 py-8 sm:px-6 lg:px-10">
        @if (session('success'))
          <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-sm" role="status">
            {{ session('success') }}
          </div>
        @endif
        @yield('content')
      </main>
    </div>
  </div>

  <x-feedback.toast />

  @isset($loginAnnouncements)
    @if ($loginAnnouncements->isNotEmpty())
      <x-announcements.login-modal :announcements="$loginAnnouncements" />
    @endif
  @endisset

  <div id="admin-logout-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-950/50 px-4">
    <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/40">
      <h3 class="text-lg font-bold text-slate-900">Confirm logout</h3>
      <p class="mt-1 text-sm text-slate-600">Are you sure you want to log out of the Student Development and Activities Office Admin portal?</p>
      <div class="mt-5 flex items-center justify-end gap-2">
        <button
          type="button"
          id="admin-logout-cancel"
          class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20"
        >
          Cancel
        </button>
        <button
          type="button"
          id="admin-logout-confirm"
          class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-4 focus:ring-rose-500/25"
        >
          Log out
        </button>
      </div>
    </div>
  </div>

  <script>
    (() => {
      const toggles = document.querySelectorAll('[data-sidebar-toggle]');
      toggles.forEach((btn) => {
        const key = btn.getAttribute('data-sidebar-toggle');
        const panelId = btn.getAttribute('aria-controls');
        const panel = panelId ? document.getElementById(panelId) : null;
        const chevron = key ? document.querySelector(`[data-sidebar-chevron="${key}"]`) : null;
        if (!panel) return;

        btn.addEventListener('click', () => {
          const isExpanded = btn.getAttribute('aria-expanded') === 'true';
          const next = !isExpanded;
          btn.setAttribute('aria-expanded', next ? 'true' : 'false');
          panel.classList.toggle('hidden', !next);
          if (chevron) {
            chevron.classList.toggle('rotate-180', next);
          }
        });
      });

      const trigger = document.getElementById('admin-logout-trigger');
      const modal = document.getElementById('admin-logout-modal');
      const cancel = document.getElementById('admin-logout-cancel');
      const confirmBtn = document.getElementById('admin-logout-confirm');
      const form = document.getElementById('admin-logout-form');

      if (!trigger || !modal || !cancel || !confirmBtn || !form) return;

      const close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      };

      trigger.addEventListener('click', () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
      });

      cancel.addEventListener('click', close);
      modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
      });

      confirmBtn.addEventListener('click', () => form.submit());
    })();
  </script>
</body>
</html>

