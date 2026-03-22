@props([
    'variant' => 'primary',
    'fullWidth' => false,
])

@php
    $variants = [
        'primary' => 'bg-sky-700 text-white shadow-lg shadow-sky-800/30 hover:bg-sky-800 focus:ring-sky-500/25',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50 focus:ring-sky-500/20',
        'danger' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-700 focus:ring-rose-500/25',
        'warning' => 'bg-amber-500 text-white shadow-sm hover:bg-amber-600 focus:ring-amber-500/25',
    ];

    $widthClass = $fullWidth ? 'w-full' : '';
    $variantClass = $variants[$variant] ?? $variants['primary'];
@endphp

<button {{ $attributes->merge(['class' => "inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-4 {$widthClass} {$variantClass}"]) }}>
    {{ $slot }}
</button>
