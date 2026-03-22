@props([
    'id' => null,
    'name' => null,
    'rows' => 4,
])

<textarea
    @if($id) id="{{ $id }}" @endif
    @if($name) name="{{ $name }}" @endif
    rows="{{ $rows }}"
    {{ $attributes->merge([
        'class' => 'mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-sky-500 focus:outline-none focus:ring-4 focus:ring-sky-500/15',
    ]) }}
>{{ $slot }}</textarea>
