<?php

test('MfTopNav declares all five section links', function () {
    $source = file_get_contents(resource_path('js/components/MfTopNav.vue'));

    foreach (['Dashboard', 'Orders', 'Catalog', 'Inventory', 'Settings'] as $label) {
        expect($source)->toContain("label: '{$label}'");
    }
});

test('MfTopNav highlights the active route via prefix-based matching with brand color', function () {
    $source = file_get_contents(resource_path('js/components/MfTopNav.vue'));

    expect($source)
        ->toContain('isActive')
        ->toContain('startsWith')
        ->toContain('bg-mf-orange/10 text-mf-orange');
});

test('MfTopNav is sticky and exposes a mobile drawer trigger', function () {
    $source = file_get_contents(resource_path('js/components/MfTopNav.vue'));

    expect($source)
        ->toContain('sticky top-0 z-40')
        ->toContain('topnav-hamburger')
        ->toContain('Drawer');
});
