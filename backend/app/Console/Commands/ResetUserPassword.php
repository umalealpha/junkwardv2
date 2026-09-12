<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetUserPassword extends Command
{
    protected $signature = 'user:reset-password {email} {password}';
    protected $description = 'Reset a user\'s password (for initial setup / recovery)';

    public function handle()
    {
        $email    = $this->argument('email');
        $password = $this->argument('password');

        $user = User::where('email', $email)->first();

        if (!$user) {
            // Try case-insensitive
            $user = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
        }

        if (!$user) {
            $this->error("User not found: {$email}");
            // List users with similar email for debugging
            $similar = User::whereRaw('LOWER(email) LIKE ?', ['%' . strtolower(explode('@', $email)[0]) . '%'])
                ->select('id', 'email', 'firstName', 'lastName')
                ->limit(5)
                ->get();
            if ($similar->count()) {
                $this->warn('Similar users found:');
                foreach ($similar as $u) {
                    $this->line("  [{$u->id}] {$u->email} ({$u->firstName} {$u->lastName})");
                }
            }
            return 1;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->info("Password updated for: {$user->email} (ID: {$user->id}, Name: {$user->firstName} {$user->lastName})");
        return 0;
    }
}
