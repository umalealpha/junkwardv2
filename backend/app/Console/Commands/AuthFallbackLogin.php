<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\AuthGate;
use Illuminate\Console\Command;

/**
 * Emergency toggle for password-login fallback.
 *
 * Run via the deploy.yml `run-artisan` workflow target when SSO is
 * down and operations need a path back in. Remember to disable the
 * moment SSO is restored — leaving fallback enabled defeats the
 * SSO-only policy.
 */
class AuthFallbackLogin extends Command
{
    protected $signature = 'auth:fallback {action : enable | disable | status}';
    protected $description = 'Toggle the emergency password-login fallback (use when SSO is down)';

    public function handle(): int
    {
        $action = strtolower((string) $this->argument('action'));

        switch ($action) {
            case 'enable':
                AuthGate::enableFallback();
                $this->warn('PASSWORD-LOGIN FALLBACK ENABLED.');
                $this->warn('All users can now authenticate with username + password.');
                $this->warn('Disable as soon as SSO is restored:');
                $this->warn('  php artisan auth:fallback disable');
                return self::SUCCESS;

            case 'disable':
                AuthGate::disableFallback();
                $this->info('Password-login fallback DISABLED.');
                $this->info('Only admin-equivalent roles (Super Admin / Manager / Admin) may now use password login.');
                $this->info('All other users must sign in via Microsoft SSO.');
                return self::SUCCESS;

            case 'status':
                $enabled = AuthGate::isFallbackEnabled();
                if ($enabled) {
                    $this->warn('Status: FALLBACK ENABLED — password login is open to everyone.');
                } else {
                    $this->info('Status: FALLBACK DISABLED — password login is restricted to admin roles only.');
                }
                return self::SUCCESS;

            default:
                $this->error("Unknown action: '{$action}'. Valid options: enable | disable | status");
                return self::INVALID;
        }
    }
}
