@props([
    'for' => null,
    'required' => false,
])

<label @if($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'block text-sm font-medium text-slate-900']) }}>
    {{ $slot }}
    @if ($required)
        <span class="text-rose-600">*</span>
    @endif
</label>
