@props([
    'title',
    'subtitle' => null,
    'helper' => null,
    'helperHtml' => false,
    'requiredMark' => false,
])

<div {{ $attributes->merge(['class' => 'border-b border-slate-100 pb-5']) }}>
    <h2 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">
        {{ $title }}
        @if ($requiredMark)
            <span class="text-rose-600" aria-hidden="true">*</span>
            <span class="sr-only"> (required)</span>
        @endif
    </h2>

    @if ($subtitle)
        <p class="mt-2 text-sm text-slate-600">{{ $subtitle }}</p>
    @endif

    @if ($helper)
        @if ($helperHtml)
            <p class="mt-3 text-xs text-slate-500">{!! $helper !!}</p>
        @else
            <p class="mt-3 text-xs text-slate-500">{{ $helper }}</p>
        @endif
    @endif
</div>
