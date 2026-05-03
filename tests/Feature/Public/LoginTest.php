<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

test('GET /login renders the public Login Inertia page', function () {
    $response = $this->get(route('login'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/Login'));
});

test('an authenticated visitor hitting /login is redirected to /dashboard server-side', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('login'));

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('valid credentials log the user in and redirect to /dashboard', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('invalid credentials keep the visitor unauthenticated and return validation errors', function () {
    $user = User::factory()->create();

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('the public Login page does not include forgot-password, register, remember-me, or 2FA UI', function () {
    $source = file_get_contents(resource_path('js/pages/public/Login.vue'));

    expect($source)
        ->not->toContain('Forgot')
        ->not->toContain('Register')
        ->not->toContain('Remember')
        ->not->toContain('Two-factor')
        ->not->toContain('2FA')
        ->not->toContain('two-factor');
});

test('the public Login page wraps email and password inputs in MfFormField with the correct attributes', function () {
    $source = file_get_contents(resource_path('js/pages/public/Login.vue'));

    expect($source)
        ->toContain("import MfFormField from '@/components/MfFormField.vue'")
        ->toContain('<MfFormField label="Email" name="email"')
        ->toContain('<MfFormField label="Password" name="password"')
        ->toContain('autocomplete="email"')
        ->toContain('autocomplete="current-password"')
        ->toContain('autofocus');
});

test('the public Login page uses Wayfinder login.store and posts only email and password', function () {
    $source = file_get_contents(resource_path('js/pages/public/Login.vue'));

    expect($source)
        ->toContain("import { store } from '@/routes/login'")
        ->toContain('v-bind="store.form()"')
        ->not->toContain('name="remember"')
        ->not->toContain('Remember');
});

test('the public Login page surfaces the generic credential-error banner', function () {
    $source = file_get_contents(resource_path('js/pages/public/Login.vue'));

    expect($source)->toContain('Email or password incorrect.');
});

test('the public Login page implements the rate-limit countdown and disables submit until it expires', function () {
    $source = file_get_contents(resource_path('js/pages/public/Login.vue'));

    expect($source)
        ->toContain('countdown')
        ->toContain('startCountdown')
        ->toContain('setInterval')
        ->toContain('Too many attempts — try again in ')
        ->toContain('submitBlocked')
        ->toContain(':disabled="processing || submitBlocked"');
});

test('the public Login page surfaces a generic server-error banner for non-validation failures', function () {
    $source = file_get_contents(resource_path('js/pages/public/Login.vue'));

    expect($source)
        ->toContain('Something went wrong. Try again.')
        ->toContain("router.on('error'")
        ->toContain('serverError');
});

test('Fortify login throttling returns 429 (rate-limit handling stays in place)', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
