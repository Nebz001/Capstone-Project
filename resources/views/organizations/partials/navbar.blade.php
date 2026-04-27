<nav id="organization-navbar" class="sticky top-0 z-50 border-b-2 border-[#F5C400] bg-[#003E9F] shadow-md">
  <div class="mx-auto flex w-full max-w-7xl min-w-0 items-center justify-between px-4 py-3 sm:px-6 lg:px-8">

    {{-- Left: Logo + Title --}}
    <a href="{{ route('organizations.index') }}" class="flex min-w-0 items-center gap-3">
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

    {{-- Right: Active term (display only) + user controls --}}
    <div class="ml-4 flex min-w-0 shrink items-center gap-1 sm:gap-2">

      <x-active-term-status variant="navbar" />

      <div class="mx-0.5 hidden h-6 w-px shrink-0 bg-white/20 sm:mx-1 sm:block" aria-hidden="true"></div>

      <x-academic-year-status variant="navbar" />

      <div class="mx-0.5 hidden h-6 w-px shrink-0 bg-white/20 sm:mx-1 sm:block" aria-hidden="true"></div>

      {{-- Notifications --}}
      <details class="relative z-[60]" data-org-navbar-panel data-org-announcements>
        <summary
          class="relative inline-flex cursor-pointer list-none items-center justify-center rounded-xl p-2 text-white transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/30 [&::-webkit-details-marker]:hidden"
          aria-label="Open notifications"
          aria-haspopup="true"
          aria-expanded="false"
        >
          <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
          </svg>
          @if (($navbarUnreadNotificationCount ?? 0) > 0)
            <span class="absolute -right-1 -top-1 inline-flex min-h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#F5C400] px-1.5 text-[10px] font-bold leading-none text-[#003E9F] ring-2 ring-[#003E9F]">
              {{ $navbarUnreadNotificationCount > 99 ? '99+' : $navbarUnreadNotificationCount }}
            </span>
          @endif
        </summary>

        <div
          class="org-announcements-popover fixed inset-x-4 top-[calc(env(safe-area-inset-top,0px)+4.75rem)] z-[70] mx-auto flex w-full max-w-[26rem] max-h-[min(85dvh,28rem)] flex-col overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xl shadow-slate-900/15 ring-1 ring-slate-900/[0.04] origin-top lg:absolute lg:inset-x-auto lg:left-auto lg:right-0 lg:top-full lg:mx-0 lg:mt-3 lg:w-[min(calc(100dvw-1.5rem),26rem)] lg:max-h-[min(78vh,28rem)] lg:max-w-none lg:origin-top-right"
          role="region"
          aria-labelledby="org-announcements-heading"
        >
          {{-- Header --}}
          <div class="relative shrink-0 border-b border-slate-200/80 bg-gradient-to-br from-[#003E9F]/[0.07] via-white to-slate-50/90 px-5 pb-5 pt-5">
            <button
              type="button"
              data-org-announcements-close
              class="absolute right-2 top-2 inline-flex items-center justify-center rounded-lg p-2 text-slate-500 transition hover:bg-slate-100/90 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#003E9F]/25 active:bg-slate-100"
              aria-label="Close announcements"
            >
              <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
              </svg>
            </button>

            <div class="flex gap-4 pr-10">
              <div class="flex h-12 w-12 flex-none items-center justify-center rounded-2xl bg-[#003E9F] text-white shadow-md shadow-[#003E9F]/25 ring-2 ring-white/25">
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
              </div>
              <div class="min-w-0 flex-1 pt-0.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#003E9F]/90">Latest updates</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                  <h2 id="org-announcements-heading" class="text-xl font-bold tracking-tight text-slate-900">
                    Notifications
                  </h2>
                  @if (($navbarUnreadNotificationCount ?? 0) > 0)
                    <span class="inline-flex items-center rounded-full bg-[#F5C400]/90 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide text-[#003E9F] shadow-sm">
                      {{ $navbarUnreadNotificationCount }} unread
                    </span>
                  @endif
                </div>
                <p class="mt-1.5 text-sm font-medium leading-snug text-slate-600">
                  <span class="text-slate-800">From SDAO and system activity.</span>
                  Click any item to open and mark it as read.
                </p>
              </div>
            </div>
          </div>

          {{-- Body (scrolls when tall; flex-1 keeps panel within max-h on small screens) --}}
          <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden bg-slate-50/60 px-3 py-4 sm:px-4 lg:max-h-[min(52vh,20rem)] lg:flex-none">
            @forelse (($navbarNotifications ?? collect()) as $notification)
              @php
                $isUnread = $notification->read_at === null;
                $type = (string) ($notification->type ?? 'info');
                $dotClass = match ($type) {
                  'success' => 'bg-emerald-500',
                  'warning' => 'bg-amber-500',
                  'error' => 'bg-rose-500',
                  default => 'bg-sky-500',
                };
              @endphp
              <article class="mb-3 overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.03] last:mb-0 {{ $isUnread ? 'border-sky-200 bg-sky-50/40' : '' }}">
                <div class="p-4">
                  <a href="{{ route('organizations.notifications.open', $notification) }}" class="block">
                    <div class="flex items-start gap-2.5">
                      <span class="mt-1 inline-flex h-2.5 w-2.5 rounded-full {{ $isUnread ? $dotClass : 'bg-slate-300' }}"></span>
                      <div class="min-w-0 flex-1">
                        <h3 class="text-[15px] font-bold leading-snug text-slate-900">{{ $notification->title }}</h3>
                        @if ($notification->body)
                          <p class="mt-1 line-clamp-3 text-sm font-normal leading-relaxed text-slate-600">{{ $notification->body }}</p>
                        @endif
                        <p class="mt-1.5 text-xs text-slate-500">{{ optional($notification->created_at)->diffForHumans() }}</p>
                      </div>
                    </div>
                  </a>
                  @if ($isUnread)
                    <form method="POST" action="{{ route('organizations.notifications.mark-read', $notification) }}" class="mt-2.5">
                      @csrf
                      <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Mark as read</button>
                    </form>
                  @endif
                </div>
              </article>
            @empty
              <div class="flex flex-col items-center justify-center gap-4 px-4 py-10 text-center">
                <svg
                  class="h-11 w-11 shrink-0 text-[#003E9F]/75"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  aria-hidden="true"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <div class="max-w-[16rem] space-y-2">
                  <p class="text-base font-bold text-slate-900">You’re up to date</p>
                  <p class="text-sm font-medium leading-relaxed text-slate-600">
                    When SDAO posts something new, it will show up here and on your next login.
                  </p>
                </div>
              </div>
            @endforelse
          </div>
          @if (($navbarNotifications ?? collect())->count() > 5)
            <div class="border-t border-slate-200/80 bg-white px-4 py-3">
              <a href="{{ route('organizations.notifications.index') }}" class="mx-auto inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-500/20">
                View All Notifications
              </a>
            </div>
          @endif
        </div>
      </details>

      {{-- Vertical divider --}}
      <div class="mx-1 h-6 w-px bg-white/20" aria-hidden="true"></div>

      {{-- Profile menu (dropdown) --}}
      <details class="relative z-[60]" data-org-navbar-panel>
        <summary
          class="flex cursor-pointer list-none items-center gap-2 rounded-xl py-1 pl-1 pr-2 text-white transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/30 [&::-webkit-details-marker]:hidden"
        >
          <div class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-white/20 text-sm font-bold text-white shadow-sm ring-2 ring-white/30">
            {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
          </div>
          <span class="hidden max-w-[10rem] truncate text-sm font-medium sm:block">
            {{ auth()->user()->full_name }}
          </span>
          <svg class="h-4 w-4 flex-none text-white/80" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
          </svg>
        </summary>

        <div
          class="absolute right-0 top-full mt-2 w-60 overflow-hidden rounded-2xl border border-slate-200 bg-white py-1 shadow-xl shadow-slate-900/15 ring-1 ring-slate-900/5"
          role="menu"
        >
          <div class="border-b border-slate-100 px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Signed in as</p>
            <p class="mt-0.5 truncate text-sm font-semibold text-slate-900">{{ auth()->user()->full_name }}</p>
            <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
          </div>
          <a
            href="{{ route('organizations.profile') }}"
            class="block px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            role="menuitem"
          >
            Organization profile
          </a>
          <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
            @csrf
            <button
              type="submit"
              class="w-full px-4 py-2.5 text-left text-sm font-semibold text-rose-600 transition hover:bg-rose-50"
              role="menuitem"
            >
              Log out
            </button>
          </form>
        </div>
      </details>

    </div>

  </div>
</nav>
