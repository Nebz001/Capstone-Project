@props([
    'message' => '',
    'variant' => 'warning',
    'icon' => true,
])

@php
    $v = in_array($variant, ['warning', 'info', 'error', 'success'], true) ? $variant : 'warning';

    $paths = [
        'warning' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
        'info' => 'm11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
        'error' => 'M12 9v3.75m0 3.75h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'success' => 'M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    ];
@endphp

<div {{ $attributes->class([
    'flex w-full max-w-full items-center gap-3 rounded-2xl border px-4 py-4 text-sm font-medium leading-relaxed shadow-sm',
    'border-yellow-200 bg-yellow-50 text-yellow-800 shadow-yellow-900/5' => $v === 'warning',
    'border-sky-200 bg-sky-50 text-sky-800 shadow-sky-900/5' => $v === 'info',
    'border-rose-200 bg-rose-50 text-rose-800 shadow-rose-900/5' => $v === 'error',
    'border-emerald-200 bg-emerald-50 text-emerald-900 shadow-emerald-900/5' => $v === 'success',
]) }} role="status">
    @if ($icon)
        <div
            @class([
                'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                'bg-yellow-100/90' => $v === 'warning',
                'bg-sky-100/90' => $v === 'info',
                'bg-rose-100/90' => $v === 'error',
                'bg-emerald-100/90' => $v === 'success',
            ])
            aria-hidden="true"
        >
            <svg
                @class([
                    'h-[1.125rem] w-[1.125rem]',
                    'text-yellow-600' => $v === 'warning',
                    'text-sky-600' => $v === 'info',
                    'text-rose-600' => $v === 'error',
                    'text-emerald-600' => $v === 'success',
                ])
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $paths[$v] }}" />
            </svg>
        </div>
    @endif
    <div class="min-w-0 flex-1 [&>p:first-child]:mt-0 [&>p+p]:mt-1.5 [&>p]:leading-relaxed">
        @if ($message)
            {{ $message }}
        @else
            {{ $slot }}
        @endif
    </div>
</div>
