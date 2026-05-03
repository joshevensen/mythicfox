<?php

use App\Models\User;

test('AdminLayout file declares all required shell pieces', function () {
    expect(file_get_contents(resource_path('js/layouts/AdminLayout.vue')))
        ->toContain('<MfTopNav')
        ->toContain('<MfPageContainer')
        ->toContain('<MfToast')
        ->toContain('<MfConfirmDialog');

    expect(file_get_contents(resource_path('js/components/MfToast.vue')))
        ->toContain('position="top-right"');
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
