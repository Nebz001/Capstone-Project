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
  <div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
    <aside class="border-b border-slate-200 bg-[#003E9F] text-white lg:min-h-screen lg:border-b-0 lg:border-r lg:border-white/10">
      <div class="px-5 py-5">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
          <img src="{{ asset('images/logos/nu-logo-onlyy.png') }}" alt="NU Lipa" class="h-10 w-auto" />
          <div class="min-w-0">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#F5C400]">NU Lipa</p>
            <p class="truncate text-sm font-semibold">{{ auth()->user()?->isSuperAdmin() ? 'Super Admin' : 'Student Development and Activities Office Admin' }}</p>
          </div>
        </a>
      </div>

      <nav class="space-y-5 px-3 pb-4" aria-label="Admin navigation">
        <div class="space-y-1">
          <p class="px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-blue-200/80">Overview</p>
          <a
            href="{{ route('admin.dashboard') }}"
            class="block rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.dashboard') ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
          >
            Dashboard
          </a>
        </div>

        @if (auth()->user()?->isSuperAdmin())
          <div class="space-y-1">
            <p class="px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-blue-200/80">Submissions (Student Development and Activities Office)</p>
            <a
              href="{{ route('admin.submissions.register') }}"
              class="block rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.submissions.register') ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
            >
              Register organization
            </a>
            <a
              href="{{ route('admin.submissions.renew') }}"
              class="block rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.submissions.renew') ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
            >
              Renew organization
            </a>
            <a
              href="{{ route('admin.submissions.activity-calendar') }}"
              class="block rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.submissions.activity-calendar') ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
            >
              Submit activity calendar
            </a>
            <a
              href="{{ route('admin.submissions.activity-proposal') }}"
              class="block rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.submissions.activity-proposal') ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
            >
              Submit activity proposal
            </a>
          </div>
        @endif

        <div class="space-y-1">
          <p class="px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-blue-200/80">Review Modules</p>
          <a
            href="{{ route('admin.registrations.index') }}"
            class="block rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.registrations.*') ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
          >
            Registrations
          </a>
          <a
            href="{{ route('admin.renewals.index') }}"
            class="block rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.renewals.*') ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
          >
            Renewals
          </a>
          <a
            href="{{ route('admin.calendars.index') }}"
            class="block rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.calendars.*') ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
          >
            Activity Calendars
          </a>
          <a
            href="{{ route('admin.proposals.index') }}"
            class="block rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.proposals.*') ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
          >
            Activity Proposals
          </a>
          <a
            href="{{ route('admin.reports.index') }}"
            class="block rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.reports.*') ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
          >
            After Activity Reports
          </a>
        </div>

        <div class="space-y-1">
          <p class="px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-blue-200/80">Account Management</p>
          <a
            href="{{ route('admin.accounts.index') }}"
            class="block rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.accounts.*') ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
          >
            User accounts
          </a>
        </div>

        <div class="space-y-1 border-t border-white/10 pt-3">
          <p class="px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-red-200/90">Session</p>
          <button
            type="button"
            id="admin-logout-trigger"
            class="block w-full rounded-xl px-3 py-2 text-left text-sm font-semibold text-red-200 transition hover:bg-red-500/20 hover:text-red-100 focus:outline-none focus:ring-2 focus:ring-red-200/40"
          >
            Log out
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
            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#003E9F]">{{ auth()->user()?->isSuperAdmin() ? 'Student Development and Activities Office Super Admin Portal' : 'Student Development and Activities Office Admin Portal' }}</p>
            <p class="text-xs text-slate-500">{{ now()->format('l, F j, Y') }}</p>
            @if (! auth()->user()?->isSuperAdmin())
              <x-active-term-status variant="admin" />
              <x-academic-year-status variant="admin" />
            @endif
          </div>
          <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
            @if (auth()->user()?->isSuperAdmin())
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

