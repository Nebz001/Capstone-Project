@extends('layouts.organization')

@section('title', 'Notifications — NU Lipa SDAO')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
  <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold tracking-tight text-slate-900">Notifications</h1>
      <p class="mt-1 text-sm text-slate-600">Stay updated with account, organization, and submission activity.</p>
    </div>
    <form method="POST" action="{{ route('organizations.notifications.mark-all-read') }}">
      @csrf
      <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20">
        Mark all as read
      </button>
    </form>
  </div>

  <x-ui.card padding="p-4" class="mb-4">
    <form method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-3">
      <div>
        <x-forms.label for="filter">Status</x-forms.label>
        <select id="filter" name="filter" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20">
          <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All</option>
          <option value="unread" {{ $filter === 'unread' ? 'selected' : '' }}>Unread</option>
          <option value="read" {{ $filter === 'read' ? 'selected' : '' }}>Read</option>
        </select>
      </div>
      <div>
        <x-forms.label for="type">Category</x-forms.label>
        <select id="type" name="type" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-[#003E9F] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20">
          <option value="">All categories</option>
          @foreach ($typeOptions as $option)
            <option value="{{ $option }}" {{ $type === $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
          @endforeach
        </select>
      </div>
      <div class="flex items-end">
        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-[#003E9F] px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#003E9F]/25 transition hover:bg-[#00327F] focus:outline-none focus:ring-4 focus:ring-[#003E9F]/40">
          Apply filters
        </button>
      </div>
    </form>
  </x-ui.card>

  <div class="space-y-3">
    @forelse ($notifications as $notification)
      @php
        $isUnread = $notification->read_at === null;
      @endphp
      <x-ui.card padding="p-0" class="overflow-hidden {{ $isUnread ? 'border-sky-200 bg-sky-50/30' : '' }}">
        <div class="flex flex-wrap items-start justify-between gap-3 px-4 py-4">
          <a href="{{ route('organizations.notifications.open', $notification) }}" class="min-w-0 flex-1">
            <div class="flex items-start gap-2">
              <span class="mt-0.5 inline-flex h-2 w-2 rounded-full {{ $isUnread ? 'bg-sky-500' : 'bg-slate-300' }}"></span>
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">{{ $notification->title }}</p>
                @if ($notification->body)
                  <p class="mt-1 text-sm text-slate-600">{{ $notification->body }}</p>
                @endif
                <p class="mt-1 text-xs text-slate-500">{{ optional($notification->created_at)->diffForHumans() }}</p>
              </div>
            </div>
          </a>
          @if ($isUnread)
            <form method="POST" action="{{ route('organizations.notifications.mark-read', $notification) }}">
              @csrf
              <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Mark read</button>
            </form>
          @endif
        </div>
      </x-ui.card>
    @empty
      <x-ui.card padding="p-8">
        <div class="text-center">
          <p class="text-base font-semibold text-slate-900">You’re up to date</p>
          <p class="mt-1 text-sm text-slate-600">When SDAO posts something new, it will show up here and on your next login.</p>
        </div>
      </x-ui.card>
    @endforelse
  </div>

  <div class="mt-6">
    {{ $notifications->links() }}
  </div>
</div>
@endsection
