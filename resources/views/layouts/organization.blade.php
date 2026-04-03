<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title', config('app.name'))</title>
  <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

  @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  @endif
</head>

<body class="min-h-screen bg-slate-100 antialiased">

  @include('partials.organizations.navbar')

  <main>
    @yield('content')
  </main>

  <x-feedback.toast />

  @isset($loginAnnouncements)
    @if ($loginAnnouncements->isNotEmpty())
      <x-announcements.login-modal :announcements="$loginAnnouncements" />
    @endif
  @endisset

  @yield('scripts')

</body>

</html>
