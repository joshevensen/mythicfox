<?php

use App\Models\User;

test('guests are redirected to login from the settings tabs', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['settings.products.index', 'settings.files.index']);

test('the settings root redirects to the products tab', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('settings'))->assertRedirect(route('settings.products.index'));
});

test('the combined settings page and controller are gone', function () {
    expect(file_exists(resource_path('js/pages/Settings.vue')))->toBeFalse()
        ->and(file_exists(app_path('Http/Controllers/Settings/SettingsController.php')))->toBeFalse()
        ->and(file_get_contents(resource_path('js/pages/settings/Products.vue')))->not->toContain('settings-toc');
});

test('the settings sidebar links to the Products and Files tabs', function () {
    $source = file_get_contents(resource_path('js/layouts/settings/Layout.vue'));

    expect($source)
        ->toContain("title: 'Products'")
        ->toContain("title: 'Files'")
        ->toContain("from '@/routes/settings/products'")
        ->toContain("from '@/routes/settings/files'");
});

test('file history uses the files tab route', function () {
    $source = file_get_contents(resource_path('js/components/settings/FileHistorySection.vue'));

    expect($source)
        ->toContain("from '@/routes/settings/files'")
        ->not->toContain('settings().url');
});
