<?php

test('MfTopNav declares the maintained section links', function () {
    $source = file_get_contents(resource_path('js/components/MfTopNav.vue'));

    foreach (['Dashboard', 'Orders', 'Catalog', 'Settings'] as $label) {
        expect($source)->toContain("label: '{$label}'");
    }

    expect($source)->not->toContain("href: '/decks'");
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

test('MfTopNav exposes a global import button before the user menu', function () {
    $source = file_get_contents(resource_path('js/components/MfTopNav.vue'));

    expect($source)
        ->toContain('importModal.open()')
        ->toContain('data-test="topnav-import-button"')
        ->toContain('aria-label="Open import modal"');

    expect(strpos($source, 'data-test="topnav-import-button"'))
        ->toBeLessThan(strpos($source, 'data-test="topnav-user-menu"'));
});
