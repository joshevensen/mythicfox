<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('user:create creates a user when none exists', function () {
    $this->artisan('user:create', ['email' => 'admin@example.com', 'name' => 'Admin', 'password' => 'super-secret'])
        ->expectsOutputToContain('User [admin@example.com] created.')
        ->assertSuccessful();

    $user = User::where('email', 'admin@example.com')->firstOrFail();

    expect($user->name)->toBe('Admin');
    expect(Hash::check('super-secret', $user->password))->toBeTrue();
});

test('user:create rejects when a user already exists', function () {
    User::factory()->create();

    $this->artisan('user:create', ['email' => 'second@example.com', 'name' => 'Second', 'password' => 'super-secret'])
        ->expectsOutputToContain('A user already exists.')
        ->assertFailed();

    expect(User::count())->toBe(1);
});
