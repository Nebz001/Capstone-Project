@props([
    'title',
    'subtitle' => null,
    'helper' => null,
    'helperHtml' => false,
    'contentPadding' => 'px-6 sm:px-8 lg:px-10',
    'headerClass' => 'pt-4',
    'requiredMark' => false,
])

<div {{ $attributes->merge(['class' => $contentPadding]) }}>
    <x-ui.section-header
        :title="$title"
        :subtitle="$subtitle"
        :helper="$helper"
        :helper-html="$helperHtml"
        :required-mark="$requiredMark"
        :class="$headerClass"
    />
</div>
