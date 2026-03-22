@props([
    'containerId' => 'toast-container',
    'toastId' => 'toast',
    'dotId' => 'toast-dot',
    'messageId' => 'toast-message',
    'closeId' => 'toast-close',
])

<div id="{{ $containerId }}" class="pointer-events-none fixed right-4 top-4 z-50">
    <div id="{{ $toastId }}" class="pointer-events-auto invisible w-80 max-w-[calc(100vw-2rem)] translate-y-2 rounded-xl border border-slate-200 bg-white p-4 opacity-0 shadow-xl shadow-slate-300/30 ring-1 ring-slate-100 transition-opacity transition-transform duration-200 ease-out">
        <div class="flex items-start gap-3">
            <span id="{{ $dotId }}" class="mt-1 h-2.5 w-2.5 flex-none rounded-full bg-emerald-500"></span>
            <p id="{{ $messageId }}" class="text-sm font-medium text-slate-900"></p>
            <button id="{{ $closeId }}" type="button" class="ml-auto inline-flex h-7 w-7 flex-none items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500/20" aria-label="Close toast">
                <span aria-hidden="true">×</span>
            </button>
        </div>
    </div>
</div>
