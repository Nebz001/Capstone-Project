@props([
    'variant' => 'primary',
    'fullWidth' => false,
])

@php
    $variants = [
        'primary' => 'bg-sky-700 text-white shadow-lg shadow-sky-800/30 hover:bg-sky-800 active:bg-sky-900 focus:ring-sky-500/25 disabled:hover:bg-sky-700 disabled:active:bg-sky-700',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50 active:bg-slate-100 focus:ring-sky-500/20 disabled:hover:bg-white disabled:active:bg-white',
        'danger' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-700 active:bg-rose-800 focus:ring-rose-500/25 disabled:hover:bg-rose-600 disabled:active:bg-rose-600',
        'warning' => 'bg-amber-500 text-white shadow-sm hover:bg-amber-600 active:bg-amber-700 focus:ring-amber-500/25 disabled:hover:bg-amber-500 disabled:active:bg-amber-500',
    ];

    $widthClass = $fullWidth ? 'w-full' : '';
    $variantClass = $variants[$variant] ?? $variants['primary'];
@endphp

<button {{ $attributes->merge(['class' => "inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-4 disabled:cursor-not-allowed disabled:opacity-75 disabled:shadow-none {$widthClass} {$variantClass}"]) }}>
    {{ $slot }}
</button>
