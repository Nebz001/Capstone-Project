@props([
    'announcements',
])

@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Announcement> $announcements */
    $payload = $announcements->map(fn ($a) => $a->toModalPayload())->values();
@endphp

<script type="application/json" id="login-announcements-payload">
{!! $payload->toJson(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
<script type="application/json" id="login-announcements-dismiss-url">
{!! json_encode(route('announcements.dismiss')) !!}
</script>

<div
    id="login-announcements-modal"
    class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-950/60 px-3 py-4 sm:px-6"
    role="dialog"
    aria-modal="true"
    aria-labelledby="login-announcements-title"
    aria-hidden="true"
>
    <div
        class="relative flex max-h-[min(90vh,720px)] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
    >
        <div class="flex items-start justify-between border-b border-slate-100 px-4 py-3 sm:px-5">
            <div class="min-w-0 flex-1 pr-2">
                <p id="login-announcements-counter" class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400"></p>
                <h2 id="login-announcements-title" class="mt-1 text-lg font-bold leading-tight text-slate-900 sm:text-xl"></h2>
            </div>
            <button
                type="button"
                id="login-announcements-close"
                class="inline-flex h-9 w-9 flex-none items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20"
                aria-label="Close announcement"
            >
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 pb-4 pt-4 sm:px-5">
            <div id="login-announcements-image-wrap" class="hidden overflow-hidden rounded-xl border border-slate-100 bg-slate-50">
                <img
                    id="login-announcements-image"
                    src=""
                    alt=""
                    class="mx-auto max-h-[min(40vh,320px)] w-full object-contain"
                />
            </div>
            <div id="login-announcements-body" class="mt-4 whitespace-pre-wrap text-sm leading-relaxed text-slate-600"></div>
            <div id="login-announcements-link-wrap" class="mt-4 hidden">
                <a
                    id="login-announcements-link"
                    href="#"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 text-sm font-semibold text-[#003E9F] underline-offset-2 hover:underline"
                ></a>
            </div>
        </div>

        <div
            id="login-announcements-nav"
            class="hidden flex items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-5"
        >
            <button
                type="button"
                id="login-announcements-prev"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                Previous
            </button>
            <button
                type="button"
                id="login-announcements-next"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
            >
                Next
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </button>
        </div>
    </div>
</div>
