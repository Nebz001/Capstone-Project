@extends('layouts.app')

@section('title', 'Student Login')

@section('content')
  @if (session('success') || session('status'))
    <div
      id="login-success-alert-data"
      data-message="{{ session('success') ?? session('status') ?? 'Welcome back!' }}"
      @if (session('alert_title'))
        data-title="{{ session('alert_title') }}"
      @endif
      hidden
    ></div>
  @endif

  @if (session('error') || $errors->has('email') || $errors->has('password'))
    <div
      id="login-error-alert-data"
      data-message="{{ session('error') ?? 'Invalid email or password.' }}"
      hidden
    ></div>
  @endif

  <x-layout.page-shell>
    <div class="mx-auto w-full max-w-3xl">
      <x-ui.card>
        <x-ui.section-header
          title="Login Details"
          subtitle="Sign in with your school account credentials."
          helper='Fields marked with <span class="text-red-600">*</span> are required.'
          :helper-html="true"
        />

        <form id="student-login-form" method="POST" action="{{ route('login') }}" class="mt-5 space-y-5 sm:mt-6">
          @csrf
          <section class="space-y-4">
            <div>
              <x-forms.label for="email" required>School Email</x-forms.label>
              <x-forms.input
                id="email"
                name="email"
                type="email"
                required
                autocomplete="email"
                value="{{ old('email') }}"
                placeholder="sample@students.nu-lipa.edu.ph"
              />
              <x-forms.helper>Use your official NU Lipa student email.</x-forms.helper>
              @error('email')
                <x-forms.error>{{ $message }}</x-forms.error>
              @enderror
            </div>

            <div>
              <x-forms.label for="password" required>Password</x-forms.label>
              <div class="relative mt-2">
                <x-forms.input
                  id="password"
                  name="password"
                  type="password"
                  required
                  autocomplete="current-password"
                  placeholder="Enter your password"
                  class="mt-0 pr-12"
                />
                <button
                  type="button"
                  data-password-toggle
                  data-password-target="password"
                  aria-label="Show password"
                  title="Show password"
                  class="absolute right-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500/25"
                >
                  <svg data-icon-eye-open xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                  <svg data-icon-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="m3 3 18 18" />
                    <path d="M10.477 10.488a3 3 0 0 0 4.242 4.242" />
                    <path d="M9.88 5.09A10.94 10.94 0 0 1 12 4.875c5.523 0 10 3.358 10 7.5a6.87 6.87 0 0 1-1.333 3.895" />
                    <path d="M6.61 6.61C4.149 7.97 2.5 10.044 2.5 12.375c0 4.142 4.477 7.5 10 7.5a10.93 10.93 0 0 0 4.84-1.13" />
                  </svg>
                </button>
              </div>
              <x-forms.helper>Use the password linked to your school account.</x-forms.helper>
              @error('password')
                <x-forms.error>{{ $message }}</x-forms.error>
              @enderror
            </div>
          </section>

          <section class="space-y-3 border-t border-slate-100 pt-5">
            <label class="inline-flex items-center gap-3 text-sm text-slate-600" for="remember">
              <input
                id="remember"
                name="remember"
                type="checkbox"
                class="h-4 w-4 rounded border-slate-300 text-sky-700 focus:ring-sky-500/20"
              />
              <span>Remember me</span>
            </label>

            <div class="pt-1">
              <x-ui.button type="submit" :full-width="true">Login</x-ui.button>
            </div>

            <p class="text-center text-sm text-slate-600">
              Don&apos;t have an account?
              <a href="{{ route('register') }}" class="font-semibold text-sky-700 hover:underline">Create one</a>
            </p>
          </section>
        </form>
      </x-ui.card>
    </div>
  </x-layout.page-shell>
@endsection
