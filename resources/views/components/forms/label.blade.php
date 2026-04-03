@props([
    'for' => null,
    'required' => false,
])

<label @if($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'block text-sm font-semibold leading-snug text-slate-800']) }}>
    {{ $slot }}
    @if ($required)
        <span class="text-rose-600">*</span>
    @endif
</label>
