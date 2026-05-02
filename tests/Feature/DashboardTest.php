<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard renders the Dashboard inertia component wrapped by the MfAppLayout shell', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Dashboard'));

    expect(file_get_contents(resource_path('js/layouts/MfAppLayout.vue')))
        ->toContain('MfTopNav')
        ->toContain('MfPageContainer')
        ->toContain('MfToast')
        ->toContain('MfConfirmDialog');

    expect(file_get_contents(resource_path('js/components/MfPageContainer.vue')))
        ->toContain('max-w-7xl');
});
