<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetUserPasswordCommand extends Command
{
    protected $signature = 'user:reset-password {email}';

    protected $description = 'Reset the password for an existing user';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password');
        $confirm = (string) $this->secret('Confirm new password');

        if ($password === '' || $password !== $confirm) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $user->forceFill(['password' => Hash::make($password)])->save();

        $this->info("Password updated for [{$email}].");

        return self::SUCCESS;
    }
}
