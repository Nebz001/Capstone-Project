@props([
    'maxWidth' => 'max-w-6xl',
])

<div class="relative min-h-screen overflow-hidden bg-slate-100">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(59,130,246,0.16),_transparent_36%),radial-gradient(circle_at_bottom_left,_rgba(15,118,110,0.14),_transparent_32%)]"></div>

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
