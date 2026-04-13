@php
  $loginBtnClasses = 'items-center justify-center rounded-xl border border-[#003E9F] bg-[#003E9F] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/20';
  $registerBtnClasses = 'items-center justify-center rounded-xl border border-[#E7C663] bg-[#FFF8DF] px-4 py-2 text-sm font-semibold text-[#6A5200] shadow-sm transition hover:bg-[#FDF2C4] focus:outline-none focus:ring-4 focus:ring-[#F5C400]/25';
@endphp

<nav class="sticky top-0 z-50 border-b border-slate-200 bg-white shadow-sm">
  <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">

    {{-- Left: Logo + Institutional Title --}}
    <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3">
      <img
        src="{{ asset('images/logos/nu-logo.png') }}"
        alt="National University Lipa"
        class="h-10 w-auto flex-none">
      <div class="min-w-0 border-l border-slate-200 pl-3">
        <p class="text-[10px] font-bold uppercase leading-none tracking-[0.2em] text-[#003E9F]">NU Lipa</p>
        <p class="mt-0.5 truncate text-sm font-semibold leading-snug text-slate-900">
          Student Development and Activities Office Document Management System
        </p>
      </div>
    </a>

    {{-- Right: Login + Register from lg up; hamburger only below lg (true small / narrow viewports) --}}
    <div id="guest-navbar-auth" class="relative ml-4 flex flex-none items-center">

      <div data-guest-nav-desktop class="hidden items-center gap-2 lg:flex">
        <a
          href="{{ route('login') }}"
          class="inline-flex {{ $loginBtnClasses }}"
        >
          Login
        </a>
        <a
          href="{{ route('register') }}"
          class="inline-flex {{ $registerBtnClasses }}"
        >
          Register
        </a>
      </div>

      <div data-guest-nav-mobile class="relative lg:hidden">
        <button
          type="button"
          data-guest-nav-toggle
          class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-[#003E9F]/20"
          aria-expanded="false"
          aria-controls="guest-navbar-auth-panel"
          aria-label="Open account menu"
        >
          <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>

        <div
          id="guest-navbar-auth-panel"
          data-guest-nav-panel
          class="absolute right-0 top-full z-[70] mt-2 w-[min(calc(100vw-2rem),16rem)] rounded-2xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-900/10 ring-1 ring-slate-900/5"
          role="menu"
          aria-label="Account"
        >
          <a
            href="{{ route('login') }}"
            role="menuitem"
            class="flex w-full {{ $loginBtnClasses }}"
          >
            Login
          </a>
          <a
            href="{{ route('register') }}"
            role="menuitem"
            class="mt-2 flex w-full {{ $registerBtnClasses }}"
          >
            Register
          </a>
        </div>
      </div>
    </div>

  </div>
</nav>
