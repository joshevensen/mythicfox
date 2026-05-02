<?php

test('MfEmptyState exposes title, body, ctaLabel, ctaRoute, and icon props', function () {
    $source = file_get_contents(resource_path('js/components/MfEmptyState.vue'));

    expect($source)
        ->toContain('title: string')
        ->toContain('body?: string')
        ->toContain('ctaLabel?: string')
        ->toContain('ctaRoute?: string')
        ->toContain('icon?: string');
});

test('MfEmptyState renders the CTA only when both label and route are set', function () {
    $source = file_get_contents(resource_path('js/components/MfEmptyState.vue'));

    expect($source)
        ->toContain('v-if="ctaLabel && ctaRoute"')
        ->toContain('<Link')
        ->toContain('<Button');
});

test('MfEmptyState prepends pi pi- to the icon prop', function () {
    $source = file_get_contents(resource_path('js/components/MfEmptyState.vue'));

    expect($source)->toContain('`pi pi-${props.icon}`');
});

test('MfTable renders MfEmptyState as the default empty state', function () {
    $source = file_get_contents(resource_path('js/components/MfTable.vue'));

    expect($source)
        ->toContain('<MfEmptyState title="No results"')
        ->toContain('Skeleton');
});
