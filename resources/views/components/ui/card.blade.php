@props([
    'padding' => 'p-5 sm:p-7 lg:p-8',
])

<div {{ $attributes->merge(['class' => "rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-300/40 {$padding}"]) }}>
    {{ $slot }}
</div>
