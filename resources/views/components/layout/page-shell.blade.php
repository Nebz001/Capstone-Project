@props([
    'maxWidth' => 'max-w-6xl',
])

<div class="relative min-h-screen overflow-hidden bg-[#001A4D]">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <img
            src="{{ asset('images/landing/nulp-building.png') }}"
            alt=""
            class="h-full w-full object-cover object-center"
        >
        <div class="absolute inset-0 bg-gradient-to-r from-[#001A4D]/92 via-[#003E9F]/72 to-[#003E9F]/25"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-[#001A4D]/35"></div>
    </div>

    <div class="relative mx-auto flex min-h-screen w-full {{ $maxWidth }} items-center px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        @if (isset($hero))
            <div class="grid w-full gap-8 lg:grid-cols-12 lg:gap-10">
                <aside class="lg:col-span-5">
                    {{ $hero }}
                </aside>
                <main class="lg:col-span-7">
                    {{ $slot }}
                </main>
            </div>
        @else
            <div class="w-full">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
