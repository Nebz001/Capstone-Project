<nav class="sticky top-0 z-50 border-b border-slate-200 bg-white shadow-sm">
    <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">

        {{-- Left: Logo + Institutional Title --}}
        <a href="{{ url('/') }}" class="flex items-center gap-3 min-w-0">
            <img
                src="{{ asset('images/logos/nu-logo.png') }}"
                alt="National University Lipa"
                class="h-10 w-auto flex-none"
            >
            <div class="min-w-0 border-l border-slate-200 pl-3">
                <p class="text-[10px] font-bold uppercase leading-none tracking-[0.2em] text-[#003E9F]">NU Lipa</p>
                <p class="mt-0.5 truncate text-sm font-semibold leading-snug text-slate-900">
                    SDAO Document Management System
                </p>
            </div>
        </a>

        {{-- Right: Auth State --}}
        <div class="ml-4 flex flex-none items-center gap-2">
            @auth
                <div class="flex items-center gap-2.5">
                    <div class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-[#003E9F] text-sm font-bold text-white shadow-sm">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <span class="hidden text-sm font-medium text-slate-700 sm:block">
                        {{ auth()->user()->name }}
                    </span>
                </div>
            @else
                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center justify-center rounded-xl border border-[#003E9F] bg-[#003E9F] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/20"
                >
                    Login
                </a>
                <a
                    href="{{ route('register-organization') }}"
                    class="hidden items-center justify-center rounded-xl border border-[#E7C663] bg-[#FFF8DF] px-4 py-2 text-sm font-semibold text-[#6A5200] shadow-sm transition hover:bg-[#FDF2C4] focus:outline-none focus:ring-4 focus:ring-[#F5C400]/25 sm:inline-flex"
                >
                    Register Organization
                </a>
            @endauth
        </div>

    </div>
</nav>
