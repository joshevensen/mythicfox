<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateUserCommand extends Command
{
    protected $signature = 'user:create {email} {name}';

    protected $description = 'Create the single admin user (refuses if a user already exists)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) $this->argument('name');

        if (User::count() > 0) {
            $this->error('A user already exists. This is a single-user app — use `user:reset-password` instead.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password');
        $confirm = (string) $this->secret('Confirm password');

        if ($password === '' || $password !== $confirm) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("User [{$email}] created.");

        return self::SUCCESS;
    }
}
