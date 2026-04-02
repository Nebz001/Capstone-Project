<nav class="sticky top-0 z-50 border-b-2 border-[#F5C400] bg-[#003E9F] shadow-md">
  <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">

    {{-- Left: Logo + Title --}}
    <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3">
      <img
        src="{{ asset('images/logos/nu-logo-onlyy.png') }}"
        alt="National University Lipa"
        class="h-10 w-auto flex-none">
      <div class="min-w-0 border-l border-white/20 pl-3">
        <p class="text-[10px] font-bold uppercase leading-none tracking-[0.2em] text-[#F5C400]">NU Lipa</p>
        <p class="mt-0.5 truncate text-sm font-semibold leading-snug text-white">
          Organization Dashboard
        </p>
      </div>
    </a>

    {{-- Right: Authenticated user controls --}}
    <div class="ml-4 flex flex-none items-center gap-1 sm:gap-2">

      {{-- Bell notification icon --}}
      <button
        type="button"
        class="relative inline-flex items-center justify-center rounded-xl p-2 text-white transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/30"
        aria-label="Notifications"
      >
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        {{-- Unread indicator --}}
        <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-[#F5C400] ring-2 ring-[#003E9F]" aria-hidden="true"></span>
      </button>

      {{-- Vertical divider --}}
      <div class="mx-1 h-6 w-px bg-white/20" aria-hidden="true"></div>

      {{-- User name + avatar --}}
      <div class="flex items-center gap-2.5">
        <span class="hidden text-sm font-medium text-white sm:block">
          {{ auth()->user()->full_name }}
        </span>
        <div class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-white/20 text-sm font-bold text-white shadow-sm ring-2 ring-white/30">
          {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
        </div>
      </div>

    </div>

  </div>
</nav>
