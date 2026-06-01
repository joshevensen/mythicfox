<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'josh@mythicfoxgames.com'],
            [
                'name' => 'Josh Evensen',
                'password' => Hash::make('Xk7$mP2#nQ9@'),
            ]
        );
    }
}
