<?php

use App\Jobs\ImportOrdersJob;
use App\Jobs\ImportPricingCustomExportJob;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::forget(ImportPricingCustomExportJob::IN_FLIGHT_CACHE_KEY);
    Cache::forget(ImportPricingCustomExportJob::LAST_RESULT_CACHE_KEY);
    Cache::forget(ImportOrdersJob::IN_FLIGHT_CACHE_KEY);
    Cache::forget(ImportOrdersJob::LAST_RESULT_CACHE_KEY);
});

afterEach(function () {
    Cache::forget(ImportPricingCustomExportJob::IN_FLIGHT_CACHE_KEY);
    Cache::forget(ImportPricingCustomExportJob::LAST_RESULT_CACHE_KEY);
    Cache::forget(ImportOrdersJob::IN_FLIGHT_CACHE_KEY);
    Cache::forget(ImportOrdersJob::LAST_RESULT_CACHE_KEY);
});

test('AdminLayout file declares all required shell pieces', function () {
    expect(file_get_contents(resource_path('js/layouts/AdminLayout.vue')))
        ->toContain('<MfTopNav')
        ->toContain('<GlobalImportModal')
        ->toContain('<MfPageContainer')
        ->toContain('<MfToast')
        ->toContain('<MfConfirmDialog');

    expect(file_get_contents(resource_path('js/components/MfToast.vue')))
        ->toContain('position="top-right"');
});

test('anonymous inertia responses do not expose global import status props', function () {
    $this->get(route('login'))->assertInertia(
        fn ($page) => $page->missing('global_imports')
    );
});

test('GlobalImportModal exposes catalog and orders import panels', function () {
    $source = file_get_contents(resource_path('js/components/GlobalImportModal.vue'));

    expect($source)
        ->toContain('data-test="global-import-modal"')
        ->toContain('data-test="global-import-tab-catalog"')
        ->toContain('data-test="global-import-tab-orders"')
        ->toContain('PricingCustomExport.csv')
        ->toContain("key: 'orderlist'")
        ->toContain("key: 'shipping_export'")
        ->toContain("key: 'pull_sheet'")
        ->toContain("key: 'packing_slips'")
        ->toContain('uploadCatalog')
        ->toContain('importOrders');
});

test('admin layout exposes global import status props', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Cache::put(ImportPricingCustomExportJob::IN_FLIGHT_CACHE_KEY, true, now()->addHour());
    Cache::put(
        ImportOrdersJob::LAST_RESULT_CACHE_KEY,
        [
            'success' => true,
            'orders_inserted' => 2,
            'orders_updated' => 1,
            'line_items_unmatched_to_inventory' => 0,
            'completed_at' => '2026-05-05T01:00:00+00:00',
        ],
        now()->addHour(),
    );

    $this->get(route('dashboard'))->assertInertia(
        fn ($page) => $page
            ->where('global_imports.catalog.in_flight', true)
            ->where('global_imports.catalog.last_result', null)
            ->where('global_imports.orders.in_flight', false)
            ->where('global_imports.orders.last_result.success', true)
            ->where('global_imports.orders.last_result.orders_inserted', 2)
    );
});

test('anonymous request to a route using AdminLayout redirects to login', function () {
    // /dashboard renders the Dashboard inertia component which is wrapped in AdminLayout.
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated request to a route using AdminLayout returns 200 and exposes the user name to the layout', function () {
    $user = User::factory()->create(['name' => 'Test Operator']);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    // MfTopNav reads the user name from the shared `auth.user` Inertia prop.
    $response->assertInertia(
        fn ($page) => $page
            ->where('auth.user.name', 'Test Operator')
            ->where('auth.user.email', $user->email)
    );
});

test('logout POST destroys the session and redirects to login', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(auth()->check())->toBeTrue();

    $response = $this->post(route('logout'));

    $response->assertRedirect();
    expect(auth()->check())->toBeFalse();
});
