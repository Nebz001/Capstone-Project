<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') — NU Lipa SDAO</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-50 antialiased">

    <div class="flex h-screen overflow-hidden">

        {{-- Mobile sidebar backdrop --}}
        <div
            id="sidebar-backdrop"
            class="fixed inset-0 z-20 hidden bg-slate-900/50 lg:hidden"
            onclick="closeSidebar()"
            aria-hidden="true"
        ></div>

        {{-- Sidebar --}}
        @include('partials.sidebar')

        {{-- Main column --}}
        <div class="flex flex-1 flex-col overflow-hidden">

            {{-- Topbar --}}
            @include('partials.topbar')

            {{-- Scrollable page content --}}
            <main class="flex-1 overflow-y-auto bg-slate-50 px-5 py-6 sm:px-7 sm:py-7">
                @yield('content')
            </main>

        </div>
    </div>

    <x-feedback.toast />

    @yield('scripts')

    <script>
        function openSidebar() {
            document.getElementById('sidebar').classList.remove('-translate-x-full');
            document.getElementById('sidebar-backdrop').classList.remove('hidden');
        }
        function closeSidebar() {
            document.getElementById('sidebar').classList.add('-translate-x-full');
            document.getElementById('sidebar-backdrop').classList.add('hidden');
        }
    </script>

</body>
</html>
