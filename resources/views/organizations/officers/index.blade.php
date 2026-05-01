@extends('layouts.organization-portal')

@section('title', 'Manage Officers — NU Lipa SDAO')

@section('content')
@php
  $presidentUser = $presidentOfficer?->user;
  $secretaryUser = $activeSecretary?->user;
@endphp

<div class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-10">
  <header class="mb-6">
    <a href="{{ route('organizations.manage') }}" class="inline-flex items-center gap-1 text-xs font-medium text-[#003E9F] transition hover:text-[#00327F]">
      <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
      </svg>
      Back to Manage Organization
    </a>
    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Manage Officers</h1>
    <p class="mt-1 text-sm text-slate-500">Manage the official President and Secretary accounts linked to your organization.</p>
  </header>

  @if (session('success'))
    <x-feedback.blocked-message variant="success" class="mb-5" :message="session('success')" />
  @endif
  @if ($errors->any())
    <x-feedback.blocked-message variant="error" class="mb-5" :message="$errors->first()" />
  @endif

  <section class="mb-5">
    <x-ui.card padding="p-0" class="overflow-hidden">
      <div class="border-b border-slate-100 bg-white px-6 py-4">
        <h2 class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">Officer Accounts</h2>
      </div>
      <div class="bg-white px-6 py-5">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
              <h3 class="text-sm font-bold text-slate-900">President</h3>
              <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">Primary Officer</span>
            </div>
            <dl class="space-y-2 text-sm">
              <div><dt class="text-xs uppercase tracking-wide text-slate-500">Name</dt><dd class="font-semibold text-slate-900">{{ $presidentUser?->full_name ?? '—' }}</dd></div>
              <div><dt class="text-xs uppercase tracking-wide text-slate-500">Email</dt><dd class="font-medium text-slate-800">{{ $presidentUser?->email ?? '—' }}</dd></div>
              <div><dt class="text-xs uppercase tracking-wide text-slate-500">School ID</dt><dd class="font-medium text-slate-800">{{ $presidentUser?->school_id ?? '—' }}</dd></div>
              <div><dt class="text-xs uppercase tracking-wide text-slate-500">Status</dt><dd class="font-medium text-slate-800">{{ strtoupper((string) ($presidentOfficer?->status ?? 'active')) }}</dd></div>
            </dl>
          </article>

          <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
              <h3 class="text-sm font-bold text-slate-900">Secretary</h3>
              @if ($activeSecretary)
                <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800">Active</span>
              @endif
            </div>

            @if ($activeSecretary)
              <dl class="space-y-2 text-sm">
                <div><dt class="text-xs uppercase tracking-wide text-slate-500">Name</dt><dd class="font-semibold text-slate-900">{{ $secretaryUser?->full_name ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-slate-500">Email</dt><dd class="font-medium text-slate-800">{{ $secretaryUser?->email ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-slate-500">School ID</dt><dd class="font-medium text-slate-800">{{ $secretaryUser?->school_id ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-slate-500">Status</dt><dd class="font-medium text-slate-800">{{ strtoupper((string) ($activeSecretary->status ?? 'active')) }}</dd></div>
              </dl>

              @if ($canManageSecretary)
                <div class="mt-4 flex flex-wrap gap-2">
                  <form method="POST" action="{{ route('organizations.officers.secretary.deactivate', $activeSecretary) }}" onsubmit="return confirm('Deactivate current secretary account?')">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex rounded-xl border border-rose-300 bg-rose-50 px-3.5 py-2 text-xs font-semibold text-rose-800 transition hover:bg-rose-100">
                      Deactivate Secretary
                    </button>
                  </form>
                </div>
              @endif
            @else
              <p class="text-sm text-slate-600">No secretary account linked yet.</p>
            @endif
          </article>
        </div>
      </div>
    </x-ui.card>
  </section>

  @if ($canManageSecretary)
    <section class="mb-5">
      <x-ui.card padding="p-0" class="overflow-hidden">
        <div class="border-b border-slate-100 bg-white px-6 py-4">
          <h2 class="text-base font-bold text-slate-900">{{ $activeSecretary ? 'Replace Secretary' : 'Add Secretary Account' }}</h2>
          @if ($activeSecretary)
            <p class="mt-1 text-xs text-slate-500">Replacing the secretary will deactivate the current secretary account for this organization.</p>
          @endif
        </div>
        <div class="bg-white px-6 py-5">
          <form method="POST" action="{{ $activeSecretary ? route('organizations.officers.secretary.replace') : route('organizations.officers.secretary.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @csrf
            <div>
              <x-forms.label for="first_name" required>First Name</x-forms.label>
              <x-forms.input id="first_name" name="first_name" :value="old('first_name')" required />
            </div>
            <div>
              <x-forms.label for="last_name" required>Last Name</x-forms.label>
              <x-forms.input id="last_name" name="last_name" :value="old('last_name')" required />
            </div>
            <div>
              <x-forms.label for="school_id" required>School ID</x-forms.label>
              <x-forms.input id="school_id" name="school_id" :value="old('school_id')" required />
            </div>
            <div>
              <x-forms.label for="email" required>NU Email</x-forms.label>
              <x-forms.input id="email" name="email" type="email" :value="old('email')" required />
            </div>
            <div>
              <x-forms.label for="password" required>Temporary Password</x-forms.label>
              <x-forms.input id="password" name="password" type="password" required />
            </div>
            <div>
              <x-forms.label for="password_confirmation" required>Confirm Temporary Password</x-forms.label>
              <x-forms.input id="password_confirmation" name="password_confirmation" type="password" required />
            </div>
            <div class="md:col-span-2">
              <x-ui.button type="submit">{{ $activeSecretary ? 'Replace Secretary' : 'Add Secretary Account' }}</x-ui.button>
            </div>
          </form>
        </div>
      </x-ui.card>
    </section>
  @else
    <x-feedback.blocked-message variant="info" class="mb-5" message="You can view officer accounts, but only the organization President can manage the secretary account." />
  @endif

  @if (($inactiveSecretaryHistory?->count() ?? 0) > 0)
    <section>
      <x-ui.card padding="p-0" class="overflow-hidden">
        <div class="border-b border-slate-100 bg-white px-6 py-4">
          <h2 class="text-base font-bold text-slate-900">Secretary History</h2>
        </div>
        <div class="bg-white px-6 py-5">
          <ul class="space-y-2 text-sm">
            @foreach ($inactiveSecretaryHistory as $entry)
              <li class="rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3">
                <p class="font-semibold text-slate-900">{{ $entry->user?->full_name ?? 'Unknown account' }}</p>
                <p class="text-slate-600">{{ $entry->user?->email ?? '—' }} · {{ $entry->user?->school_id ?? '—' }}</p>
                <p class="text-xs text-slate-500">Deactivated: {{ $entry->updated_at?->format('M d, Y h:i A') ?? '—' }}</p>
              </li>
            @endforeach
          </ul>
        </div>
      </x-ui.card>
    </section>
  @endif
</div>
@endsection

