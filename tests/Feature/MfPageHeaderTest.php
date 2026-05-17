<?php

test('MfPageHeader renders the title in an h1 and stacks actions on mobile', function () {
    $source = file_get_contents(resource_path('js/components/MfPageHeader.vue'));

    expect($source)
        ->toContain('<h1')
        ->toContain('text-2xl font-semibold')
        ->toContain('flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between');
});

test('MfPageHeader supports breadcrumbs with chevron separators', function () {
    $source = file_get_contents(resource_path('js/components/MfPageHeader.vue'));

    expect($source)
        ->toContain('breadcrumbs?: Crumb[]')
        ->toContain('pi pi-chevron-right')
        ->toContain('aria-current="page"')
        ->toContain("import { Link } from '@inertiajs/vue3';");
});
