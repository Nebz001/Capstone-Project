@props([
    'id' => null,
    'name' => null,
    'type' => 'text',
    'variant' => 'default',
])

@php
    $variants = [
        'default' => 'mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-sky-500 focus:outline-none focus:ring-4 focus:ring-sky-500/15',
        'underline' => 'block w-full border-0 border-b border-slate-400 bg-transparent px-0 py-1 text-sm text-slate-900 placeholder:text-slate-400 shadow-none focus:border-slate-600 focus:outline-none focus:ring-0',
        'digit' => 'h-11 w-10 rounded-lg border border-slate-300 bg-white text-center text-base font-semibold text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-500/15 sm:h-12 sm:w-11',
    ];

    $variantClass = $variants[$variant] ?? $variants['default'];
@endphp

<input
    @if($id) id="{{ $id }}" @endif
    @if($name) name="{{ $name }}" @endif
    type="{{ $type }}"
    {{ $attributes->merge([
        'class' => $variantClass,
    ]) }}
/>
