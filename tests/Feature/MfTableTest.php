<?php

test('MfTable accepts controlled-mode props from the page composable', function () {
    $source = file_get_contents(resource_path('js/components/MfTable.vue'));

    expect($source)
        ->toContain('columns: ColumnDef<TRow>[]')
        ->toContain('rows: TRow[]')
        ->toContain('total: number')
        ->toContain('page: number')
        ->toContain('perPage: number')
        ->toContain('sort: SortState')
        ->toContain('selectable?: boolean')
        ->toContain('expandable?: boolean')
        ->toContain('rowAction?: RowAction');
});

test('MfTable emits state-change events for the page composable to handle', function () {
    $source = file_get_contents(resource_path('js/components/MfTable.vue'));

    expect($source)
        ->toContain("(e: 'update:page', value: number): void")
        ->toContain("(e: 'update:perPage', value: number): void")
        ->toContain("(e: 'update:sort', value: SortState): void");
});

test('MfTable types module exports the expected page-size defaults', function () {
    $source = file_get_contents(resource_path('js/components/MfTable.types.ts'));

    expect($source)
        ->toContain('PAGE_SIZE_OPTIONS = [25, 50, 100, 200]')
        ->toContain('DEFAULT_PAGE_SIZE = 50');
});

test('MfTable exposes the documented slots', function () {
    $source = file_get_contents(resource_path('js/components/MfTable.vue'));

    foreach (['filters', 'bulk-actions', 'empty', 'expand-row', 'mobile-row'] as $slot) {
        expect($source)->toContain('name="'.$slot.'"');
    }
});
