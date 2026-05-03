<?php

test('MfSearchInput debounces emissions on the documented 300ms window via @vueuse/core', function () {
    $source = file_get_contents(resource_path('js/components/MfSearchInput.vue'));

    expect($source)
        ->toContain("import { useDebounceFn } from '@vueuse/core';")
        ->toContain('debounceMs: 300')
        ->toContain('useDebounceFn');
});

test('MfFilterPanel renders all five filter kinds and emits Inertia URL updates', function () {
    $source = file_get_contents(resource_path('js/components/MfFilterPanel.vue'));

    expect($source)
        ->toContain("kind === 'text'")
        ->toContain("kind === 'enum'")
        ->toContain("kind === 'range'")
        ->toContain("kind === 'date'")
        ->toContain("kind === 'boolean'")
        ->toContain('preserveState: true')
        ->toContain('preserveScroll: true')
        ->toContain("list.join(',')");
});

test('MfFilterPanel renders chips via MfFilterChips and wires Clear all + per-chip remove', function () {
    $source = file_get_contents(resource_path('js/components/MfFilterPanel.vue'));

    expect($source)
        ->toContain('MfFilterChips')
        ->toContain('clearAll')
        ->toContain('@remove="removeFilter"')
        ->toContain('@clear-all="clearAll"');
});

test('MfFilterChips exposes Clear all and emits remove + clear-all events', function () {
    $source = file_get_contents(resource_path('js/components/MfFilterChips.vue'));

    expect($source)
        ->toContain('Clear all')
        ->toContain("(e: 'remove', key: string): void")
        ->toContain("(e: 'clear-all'): void");
});

test('MfFilterChip emits remove on click and renders label and value', function () {
    $source = file_get_contents(resource_path('js/components/MfFilterChip.vue'));

    expect($source)
        ->toContain("(e: 'remove'): void")
        ->toContain("@click=\"\$emit('remove')\"")
        ->toContain('{{ label }}')
        ->toContain('{{ value }}');
});
