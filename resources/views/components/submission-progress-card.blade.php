@props([
    'mode' => 'default',
    'variant' => 'full',
    'documentLabel' => '',
    'hubHref' => null,
    'hubLabel' => null,
    'title' => null,
    'subtitle' => null,
    'statusLabel' => '',
    'statusBadgeClass' => 'bg-slate-100 text-slate-700 border border-slate-200',
    'stages' => [],
    'summary' => '',
    'meta' => [],
    'primaryAction' => null,
    'secondaryAction' => null,
    'tertiaryLinks' => [],
    'selector' => null,
    'helperNote' => null,
    'headingId' => 'submission-progress-heading',
    'stepCount' => null,
])

@php
    $isEmpty = $mode === 'empty';
    $steps = is_array($stages) ? $stages : [];
    $nSteps = $stepCount ?? count($steps);
    $compactStepper = $nSteps >= 6;
@endphp

@if ($isEmpty)
    <x-ui.card {{ $attributes->class(['overflow-hidden border-dashed bg-slate-50/70']) }} padding="px-6 py-8 sm:px-8 sm:py-9">
        <div class="text-center">
            @if ($documentLabel)
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#003E9F]">{{ $documentLabel }}</p>
            @endif
            <h2 id="{{ $headingId }}" class="mt-1.5 text-base font-bold tracking-tight text-slate-900">
                {{ $title ?? 'Nothing to show yet' }}
            </h2>
            @if (! empty($subtitle))
                <p class="mx-auto mt-2 max-w-md text-xs leading-relaxed text-slate-600 sm:text-sm">{{ $subtitle }}</p>
            @endif
            <div class="mt-5 flex flex-col items-stretch justify-center gap-2 sm:flex-row sm:justify-center">
                @if (is_array($primaryAction) && ! empty($primaryAction['href']))
                    <a href="{{ $primaryAction['href'] }}" class="inline-flex items-center justify-center rounded-lg bg-[#003E9F] px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#003286] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/30 sm:text-sm">
                        {{ $primaryAction['label'] }}
                    </a>
                @endif
                @if (is_array($secondaryAction) && ! empty($secondaryAction['href']))
                    <a href="{{ $secondaryAction['href'] }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20 sm:text-sm">
                        {{ $secondaryAction['label'] }}
                    </a>
                @endif
            </div>
        </div>
    </x-ui.card>
@else
    @if ($variant === 'embed')
        <div {{ $attributes->class(['mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-300/25']) }}>
            <div class="px-4 py-3 sm:px-5 sm:py-4">
                <p class="text-center text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">
                    @if ($documentLabel)
                        {{ $documentLabel }}
                        <span class="font-normal text-slate-300"> · </span>
                    @endif
                    Approval routing
                </p>
                @include('components.partials.submission-stepper-track', [
                    'steps' => $steps,
                    'compactStepper' => $compactStepper,
                    'spreadAcrossWidth' => true,
                ])
                @if (! empty($summary))
                    <div class="mt-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                        <p class="text-xs leading-relaxed text-slate-600">
                            <span class="font-semibold text-slate-700">What this means:</span>
                            {{ $summary }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
    @else
    <x-ui.card {{ $attributes->class(['overflow-hidden']) }} padding="p-0">
        {{-- Header: match Organization Activity Calendar card (index) --}}
        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
                @if ($documentLabel)
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#003E9F]">{{ $documentLabel }}</p>
                        @if ($hubHref && $hubLabel)
                            <span class="text-slate-300" aria-hidden="true">·</span>
                            <a href="{{ $hubHref }}" class="text-[10px] font-semibold text-[#003E9F] underline-offset-2 hover:underline">{{ $hubLabel }}</a>
                        @endif
                    </div>
                @endif
                @if ($title)
                    <h2 id="{{ $headingId }}" class="text-base font-bold text-slate-900 {{ $documentLabel ? 'mt-1' : '' }}">
                        {{ $title }}
                    </h2>
                @endif
                @if (! empty($subtitle))
                    <p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>
                @endif
            </div>
            <div class="flex shrink-0 flex-col items-end gap-1.5 self-start border-l border-slate-100 py-0.5 pl-4 text-right sm:pl-5">
                <span class="text-[9px] font-semibold uppercase tracking-[0.14em] text-slate-400">Status</span>
                <span class="inline-flex items-center justify-center rounded-full px-3 py-1 text-xs font-bold tracking-wide {{ $statusBadgeClass }}">
                    {{ $statusLabel }}
                </span>
            </div>
        </div>

        <div class="px-5 py-4 sm:px-6">
            <p class="text-center text-[10px] font-bold uppercase tracking-wide text-slate-400">Approval routing</p>

            @include('components.partials.submission-stepper-track', [
                'steps' => $steps,
                'compactStepper' => $compactStepper,
                'spreadAcrossWidth' => $compactStepper,
            ])

            @if (! empty($summary))
                <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <p class="text-xs leading-relaxed text-slate-600">
                        <span class="font-semibold text-slate-700">What this means:</span>
                        {{ $summary }}
                    </p>
                </div>
            @endif

            @if (! empty($meta) && is_array($meta))
                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
                    @foreach ($meta as $row)
                        <div class="flex flex-col gap-0.5">
                            <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-400">{{ $row['label'] }}</dt>
                            <dd class="text-sm font-semibold leading-tight text-slate-800">{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            @php
                $hasPrimary = is_array($primaryAction) && ! empty($primaryAction['href']);
                $hasSecondary = is_array($secondaryAction) && ! empty($secondaryAction['href']);
                $tertiary = is_array($tertiaryLinks) ? $tertiaryLinks : [];
            @endphp
            @if ($hasPrimary || $hasSecondary || $tertiary !== [])
                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                    @if ($hasPrimary)
                        <a href="{{ $primaryAction['href'] }}" class="inline-flex items-center justify-center rounded-lg bg-[#003E9F] px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#003286] focus:outline-none focus:ring-2 focus:ring-[#003E9F]/30">
                            {{ $primaryAction['label'] }}
                        </a>
                    @endif
                    @if ($hasSecondary)
                        <a href="{{ $secondaryAction['href'] }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#003E9F]/20">
                            {{ $secondaryAction['label'] }}
                        </a>
                    @endif
                    @foreach ($tertiary as $link)
                        @if (is_array($link) && ! empty($link['href']))
                            <a href="{{ $link['href'] }}" class="inline-flex items-center justify-center rounded-lg px-2 py-2 text-xs font-semibold text-[#003E9F] underline-offset-2 hover:underline">
                                {{ $link['label'] ?? 'More' }}
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif

            @if (is_array($selector) && ! empty($selector['options']) && is_array($selector['options']))
                <div class="mt-3 flex justify-end">
                    <form method="GET" action="{{ $selector['action'] ?? '' }}" class="w-full max-w-sm">
                        @foreach (($selector['hidden'] ?? []) as $hiddenKey => $hiddenValue)
                            @if ($hiddenValue !== null && $hiddenValue !== '')
                                <input type="hidden" name="{{ $hiddenKey }}" value="{{ $hiddenValue }}">
                            @endif
                        @endforeach
                        <label for="dashboard-proposal-selector" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            {{ $selector['label'] ?? 'Choose proposal' }}
                        </label>
                        <select
                            id="dashboard-proposal-selector"
                            name="{{ $selector['name'] ?? 'proposal_id' }}"
                            onchange="this.form.submit()"
                            class="block w-full appearance-none rounded-xl border border-slate-300 bg-white px-3 py-2.5 pr-9 text-xs text-slate-900 shadow-sm transition focus:border-sky-500 focus:outline-none focus:ring-4 focus:ring-sky-500/15"
                        >
                            @foreach ($selector['options'] as $option)
                                <option value="{{ $option['id'] }}" @selected((int) ($selector['selected'] ?? 0) === (int) $option['id'])>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            @endif

            @if (is_string($helperNote) && trim($helperNote) !== '')
                <p class="mt-2 text-xs text-slate-500">{{ $helperNote }}</p>
            @endif
        </div>
    </x-ui.card>
    @endif
@endif
