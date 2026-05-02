<?php

test('MfFormField shows the inertia error and hides helper text when an error is present', function () {
    $source = file_get_contents(resource_path('js/components/MfFormField.vue'));

    expect($source)
        ->toContain('page.props.errors?.[props.name]')
        ->toContain('text-red-600')
        ->toContain('v-else-if="help"')
        ->toContain('usePage');
});

test('MfMoneyInput stores cents and renders dollars via PrimeVue currency mode', function () {
    $source = file_get_contents(resource_path('js/components/MfMoneyInput.vue'));

    expect($source)
        ->toContain('mode="currency"')
        ->toContain('currency="USD"')
        ->toContain('Math.round(next * 100)')
        ->toContain('props.modelValue / 100')
        ->toContain(':min-fraction-digits="2"');
});

test('MfQtyInput is integer-only with 44px tap-target plus and minus buttons', function () {
    $source = file_get_contents(resource_path('js/components/MfQtyInput.vue'));

    expect($source)
        ->toContain(':use-grouping="false"')
        ->toContain(':show-buttons="true"')
        ->toContain('min-h-[44px] min-w-[44px]');
});

test('MfDatePicker emits ISO YYYY-MM-DD strings and supports range mode', function () {
    $source = file_get_contents(resource_path('js/components/MfDatePicker.vue'));

    expect($source)
        ->toContain('date-format="yy-mm-dd"')
        ->toContain("range ? 'range' : 'single'")
        ->toContain('[toIso(list[0]), toIso(list[1])]');
});
