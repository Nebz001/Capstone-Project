<header class="flex flex-none items-center justify-between border-b border-slate-200 bg-white px-5 py-3.5 shadow-sm sm:px-7">

    <div class="flex items-center gap-3">
        {{-- Mobile hamburger --}}
        <button
            type="button"
            class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20 lg:hidden"
            onclick="openSidebar()"
            aria-label="Open navigation"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>

        {{-- Page title --}}
        <div>
            <h1 class="text-base font-semibold tracking-tight text-slate-900">
                @yield('page-title', 'Dashboard')
            </h1>
            @hasSection('page-subtitle')
                <p class="mt-0.5 text-xs text-slate-500">@yield('page-subtitle')</p>
            @endif
        </div>
    </div>

    {{-- Right side: search + actions --}}
    <div class="flex items-center gap-2">

        {{-- Notification bell --}}
        <button
            type="button"
            class="relative inline-flex items-center justify-center rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
            aria-label="View notifications"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
            <span class="absolute right-1.5 top-1.5 flex h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white" aria-hidden="true"></span>
        </button>

        {{-- Divider --}}
        <div class="h-6 w-px bg-slate-200"></div>

        {{-- Avatar + name --}}
        @auth
        <button
            type="button"
            class="flex items-center gap-2.5 rounded-xl px-2.5 py-1.5 text-sm transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
            aria-label="Account menu"
        >
            <div class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-[#003E9F] text-xs font-bold text-white">
                {{ strtoupper(substr(auth()->user()->first_name ?? 'O', 0, 1)) }}
            </div>
            <div class="hidden text-left sm:block">
                <p class="text-xs font-semibold text-slate-900 leading-none">
                    {{ auth()->user()->full_name ?? (auth()->user()->first_name . ' ' . auth()->user()->last_name) }}
                </p>
                <p class="mt-0.5 text-[10px] text-slate-500">Organization Officer</p>
            </div>
            <svg class="hidden h-4 w-4 text-slate-400 sm:block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>
        @endauth

    </div>

</header>
