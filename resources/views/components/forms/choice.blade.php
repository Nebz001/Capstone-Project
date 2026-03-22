@props([
    'id',
    'name',
    'type' => 'checkbox',
    'value' => null,
    'required' => false,
    'wrapperClass' => 'flex items-start gap-3',
    'labelClass' => 'text-sm text-slate-700',
])

@php
    $controlClass = $type === 'radio'
        ? 'mt-1 h-4 w-4 border-slate-300 text-sky-600 focus:ring-sky-600'
        : 'mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-sky-600 focus:ring-sky-600';
@endphp

<label for="{{ $id }}" class="{{ $wrapperClass }}">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if(!is_null($value)) value="{{ $value }}" @endif
        @if($required) required @endif
        {{ $attributes->class([$controlClass]) }}
    />
    <span class="{{ $labelClass }}">{{ $slot }}</span>
</label>
