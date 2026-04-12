@php
    $steps = is_array($steps ?? null) ? $steps : [];
    $compactStepper = (bool) ($compactStepper ?? false);
    $spreadAcrossWidth = (bool) ($spreadAcrossWidth ?? false);
@endphp

@if ($spreadAcrossWidth)
    {{-- Full-width, evenly distributed (e.g. 8-step activity proposal on dashboard) --}}
    <div class="mt-4 w-full overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:thin] sm:overflow-x-visible sm:pb-0 [&::-webkit-scrollbar]:h-1 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-200">
        <div class="flex w-full min-w-[38rem] items-start sm:min-w-0">
            @foreach ($steps as $stage)
                @php
                    $st = $stage['state'] ?? 'pending';
                    $circle = 'h-8 w-8 sm:h-9 sm:w-9';
                    $iconSvg = 'h-4 w-4 sm:h-[1.125rem] sm:w-[1.125rem]';
                @endphp
                <div class="flex min-w-0 flex-1 flex-col items-center gap-1.5 px-0.5 sm:gap-2 sm:px-1">
                    @if ($st === 'completed' || $st === 'success')
                        <div class="flex {{ $circle }} flex-none items-center justify-center rounded-full bg-emerald-500 text-white shadow-sm" aria-hidden="true">
                            <svg class="{{ $iconSvg }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                    @elseif ($st === 'current')
                        <div class="relative flex {{ $circle }} flex-none items-center justify-center rounded-full border-2 border-[#003E9F] bg-[#003E9F]/10" aria-hidden="true">
                            <span class="absolute h-3.5 w-3.5 animate-ping rounded-full bg-[#003E9F] opacity-15"></span>
                            <span class="relative h-2.5 w-2.5 rounded-full bg-[#003E9F]"></span>
                        </div>
                    @elseif ($st === 'warning')
                        <div class="flex {{ $circle }} flex-none items-center justify-center rounded-full border border-orange-300 bg-orange-50 text-orange-700" aria-hidden="true">
                            <svg class="{{ $iconSvg }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </div>
                    @elseif ($st === 'danger')
                        <div class="flex {{ $circle }} flex-none items-center justify-center rounded-full border border-rose-300 bg-rose-50 text-rose-700" aria-hidden="true">
                            <svg class="{{ $iconSvg }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </div>
                    @else
                        <div class="flex {{ $circle }} flex-none items-center justify-center rounded-full border border-slate-200 bg-slate-50" aria-hidden="true">
                            <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                        </div>
                    @endif
                    <p class="w-full hyphens-auto text-center text-[10px] font-semibold leading-snug text-slate-600 sm:text-[11px] sm:leading-tight {{ $st === 'current' ? '!text-[#003E9F]' : '' }} {{ $st === 'completed' || $st === 'success' ? '!text-emerald-700' : '' }} {{ $st === 'warning' ? '!text-orange-800' : '' }} {{ $st === 'danger' ? '!text-rose-800' : '' }}">
                        {{ $stage['label'] }}
                    </p>
                </div>
                @if (! $loop->last)
                    @php
                        $prev = $steps[$loop->index]['state'] ?? 'pending';
                        $progressed = in_array($prev, ['completed', 'success'], true);
                        $lineClass = $progressed ? 'bg-emerald-300' : 'bg-slate-200';
                        $arrowClass = $progressed ? 'text-emerald-500' : 'text-slate-300';
                    @endphp
                    <div class="mt-4 flex min-w-[1.25rem] flex-[1.25] basis-0 items-center sm:mt-[18px]" role="presentation" aria-hidden="true">
                        <div class="h-0.5 min-w-[8px] flex-1 rounded-full {{ $lineClass }}"></div>
                        <svg class="{{ $arrowClass }} ml-px h-3 w-3 shrink-0 sm:h-3.5 sm:w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@else
    <div class="mt-3 @if ($compactStepper) -mx-1 overflow-x-auto pb-0.5 @endif">
        <div class="flex @if ($compactStepper) min-w-min flex-nowrap justify-start px-1 sm:justify-center @else justify-center @endif items-start">
            @foreach ($steps as $stage)
                @php
                    $st = $stage['state'] ?? 'pending';
                    $circle = $compactStepper ? 'h-7 w-7' : 'h-8 w-8';
                    $iconSvg = $compactStepper ? 'h-3.5 w-3.5' : 'h-4 w-4';
                    $connMt = $compactStepper ? 'mt-3' : 'mt-3.5';
                @endphp
                <div class="flex @if ($compactStepper) w-[3.35rem] shrink-0 @else w-16 shrink-0 @endif sm:w-[4.25rem] flex-col items-center">
                    @if ($st === 'completed' || $st === 'success')
                        <div class="flex {{ $circle }} items-center justify-center rounded-full bg-emerald-500 text-white shadow-sm" aria-hidden="true">
                            <svg class="{{ $iconSvg }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                    @elseif ($st === 'current')
                        <div class="relative flex {{ $circle }} items-center justify-center rounded-full border-2 border-[#003E9F] bg-[#003E9F]/10" aria-hidden="true">
                            <span class="absolute h-3 w-3 animate-ping rounded-full bg-[#003E9F] opacity-15"></span>
                            <span class="relative h-2 w-2 rounded-full bg-[#003E9F]"></span>
                        </div>
                    @elseif ($st === 'warning')
                        <div class="flex {{ $circle }} items-center justify-center rounded-full border border-orange-300 bg-orange-50 text-orange-700" aria-hidden="true">
                            <svg class="{{ $iconSvg }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </div>
                    @elseif ($st === 'danger')
                        <div class="flex {{ $circle }} items-center justify-center rounded-full border border-rose-300 bg-rose-50 text-rose-700" aria-hidden="true">
                            <svg class="{{ $iconSvg }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </div>
                    @else
                        <div class="flex {{ $circle }} items-center justify-center rounded-full border border-slate-200 bg-slate-50" aria-hidden="true">
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                        </div>
                    @endif
                    <p class="mt-1.5 max-w-full px-0.5 text-center text-[9px] font-semibold leading-tight text-slate-500 sm:text-[10px] {{ $st === 'current' ? '!text-[#003E9F]' : '' }} {{ $st === 'completed' || $st === 'success' ? '!text-emerald-700' : '' }} {{ $st === 'warning' ? '!text-orange-800' : '' }} {{ $st === 'danger' ? '!text-rose-800' : '' }}">
                        {{ $stage['label'] }}
                    </p>
                </div>
                @if (! $loop->last)
                    @php
                        $prev = $steps[$loop->index]['state'] ?? 'pending';
                        $lineClass = in_array($prev, ['completed', 'success'], true) ? 'bg-emerald-300' : 'bg-slate-200';
                        $segW = $compactStepper ? 'w-2 sm:w-3' : 'w-3 sm:w-5';
                    @endphp
                    <div class="{{ $connMt }} {{ $segW }} shrink-0 {{ $lineClass }} h-0.5 rounded-full" role="presentation"></div>
                @endif
            @endforeach
        </div>
    </div>
@endif
