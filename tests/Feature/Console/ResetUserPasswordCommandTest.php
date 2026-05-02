<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('user:reset-password updates the password for an existing user', function () {
    $user = User::factory()->create(['email' => 'admin@example.com']);

    $this->artisan('user:reset-password', ['email' => 'admin@example.com'])
        ->expectsQuestion('New password', 'brand-new-secret')
        ->expectsQuestion('Confirm new password', 'brand-new-secret')
        ->expectsOutputToContain('Password updated for [admin@example.com].')
        ->assertSuccessful();

    expect(Hash::check('brand-new-secret', $user->refresh()->password))->toBeTrue();
});

test('user:reset-password rejects an unknown email', function () {
    $this->artisan('user:reset-password', ['email' => 'nobody@example.com'])
        ->expectsOutputToContain('No user found with email [nobody@example.com].')
        ->assertFailed();
});

test('user:reset-password rejects when password confirmation does not match', function () {
    $user = User::factory()->create(['email' => 'admin@example.com']);
    $originalHash = $user->password;

    $this->artisan('user:reset-password', ['email' => 'admin@example.com'])
        ->expectsQuestion('New password', 'brand-new-secret')
        ->expectsQuestion('Confirm new password', 'mismatch')
        ->expectsOutputToContain('Passwords do not match.')
        ->assertFailed();

    expect($user->refresh()->password)->toBe($originalHash);
});
