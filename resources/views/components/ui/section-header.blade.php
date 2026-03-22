@props([
    'title',
    'subtitle' => null,
    'helper' => null,
])

<div {{ $attributes->merge(['class' => 'border-b border-slate-100 pb-5']) }}>
    <h2 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">{{ $title }}</h2>

    @if ($subtitle)
        <p class="mt-2 text-sm text-slate-600">{{ $subtitle }}</p>
    @endif

    @if ($helper)
        <p class="mt-3 text-xs text-slate-500">{{ $helper }}</p>
    @endif
</div>
