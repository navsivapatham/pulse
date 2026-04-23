<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetPassword extends Command
{
    protected $signature = 'user:reset-password {email} {password}';
    protected $description = 'Reset a user password from the CLI';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('User not found.');
            return 1;
        }

        $user->update(['password' => Hash::make($this->argument('password'))]);
        $this->info("Password reset for {$user->email}.");

        return 0;
    }
}
