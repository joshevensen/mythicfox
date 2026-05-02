<?php

test('MfTable accepts the documented props for endpoint, columns, and rows', function () {
    $source = file_get_contents(resource_path('js/components/MfTable.vue'));

    expect($source)
        ->toContain('endpoint: string')
        ->toContain('columns: ColumnDef<TRow>[]')
        ->toContain('rows: TRow[]')
        ->toContain('total: number')
        ->toContain('selectable?: boolean')
        ->toContain('expandable?: boolean')
        ->toContain('rowAction?: RowAction');
});

test('MfTable serializes lazy state into the documented query string', function () {
    $source = file_get_contents(resource_path('js/components/MfTable.vue'));

    expect($source)
        ->toContain('page: page.value')
        ->toContain('per_page: perPage.value')
        ->toContain('params.sort = sort.value.column')
        ->toContain('params.dir = sort.value.dir')
        ->toContain('preserveState: true')
        ->toContain('preserveScroll: true');
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
