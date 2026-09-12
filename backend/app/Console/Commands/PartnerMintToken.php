<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Mints a scoped, PRE-MINTED Sanctum personal access token for a partner /
 * service-to-service integration (the alpha-finance ERP pattern).
 *
 * WHY a pre-minted token instead of a login:
 *   V2 /api/v1/auth/login is SSO-gated (AuthGate::shouldAllowPasswordLogin —
 *   only admin-equivalent roles may authenticate by password). A partner
 *   integration such as BizSure never has a human at an SSO prompt, so it
 *   cannot use that path. Instead we issue a long-lived Sanctum token up
 *   front, carrying scoped ABILITIES (e.g. bizsure:read, bizsure:write).
 *   The partner presents it as a Bearer token and the target route group is
 *   gated with the `ability:` / `abilities:` middleware (see
 *   app/Http/Kernel.php:94-100). This sidesteps the SSO gate entirely and
 *   needs NO admin role on the service account — the token's abilities, not
 *   the user's Spatie roles, are what authorize the request.
 *
 * PERSISTENCE / DB CONNECTIVITY:
 *   Sanctum is bound to AlphaDirect\Models\LocalPersonalAccessToken via
 *   Sanctum::usePersonalAccessTokenModel(...) in AppServiceProvider
 *   (line ~47). That model pins the personal_access_tokens table to the
 *   'mysql_write' connection (V2 master, no read/write split). Minting a
 *   token therefore performs a WRITE to the master DB — this command needs
 *   write-DB connectivity when run. Run it on an environment whose
 *   'mysql_write' connection resolves to the correct master.
 *
 * USAGE:
 *   php artisan partner:mint-token bizsure
 *   php artisan partner:mint-token bizsure --abilities=bizsure:read,bizsure:write
 *   php artisan partner:mint-token bizsure --rotate   # revoke old bizsure-s2s tokens first
 *
 * The plaintext token is printed ONCE and never recoverable afterwards
 * (only its hash is stored). Capture it at mint time.
 */
class PartnerMintToken extends Command
{
    protected $signature = 'partner:mint-token
        {partner : slug e.g. bizsure}
        {--abilities=bizsure:read,bizsure:write : comma-separated Sanctum abilities the token carries}
        {--email= : override the service-account email (default svc-{partner}@alphadirect.co.bw)}
        {--name= : override the service-account firstName (default = partner slug)}
        {--rotate : revoke existing tokens of this name ({partner}-s2s) before minting}';

    protected $description = 'Mint a scoped, pre-minted Sanctum service token for a partner /api/v1 integration (e.g. BizSure).';

    public function handle(): int
    {
        $partner = trim((string) $this->argument('partner'));
        if ($partner === '') {
            $this->error('Partner slug is required, e.g. `partner:mint-token bizsure`.');
            return self::FAILURE;
        }

        // Normalise abilities: trim each, drop blanks.
        $abilities = collect(explode(',', (string) $this->option('abilities')))
            ->map(fn ($a) => trim($a))
            ->filter()
            ->values()
            ->all();

        if (empty($abilities)) {
            $this->error('At least one ability is required (--abilities=bizsure:read,bizsure:write).');
            return self::FAILURE;
        }

        $email = trim((string) $this->option('email')) ?: "svc-{$partner}@alphadirect.co.bw";
        $firstName = trim((string) $this->option('name')) ?: $partner;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid service-account email: {$email}");
            return self::FAILURE;
        }

        // Resolve/create the dedicated service-account user. firstOrCreate
        // matches on email; the create-only attributes (2nd arg) are applied
        // only when the row does not yet exist, so re-running never rewrites
        // the password of an existing service account.
        //
        // NOTE: deliberately NO assignRole() — a service token authorizes via
        // its abilities (Kernel `ability`/`abilities` middleware), not via the
        // user's Spatie roles. Keeping the account role-less means it cannot
        // do anything through the SSO/role-gated UI paths.
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'firstName' => $firstName,
                'lastName'  => 'Service Account',
                // Strong random password the service account never uses to log
                // in (password login is SSO-gated anyway). It exists only to
                // satisfy the NOT NULL password column.
                'password'  => Hash::make(Str::random(40)),
                'active'    => 1,
            ]
        );

        $tokenName = "{$partner}-s2s";

        // --rotate: revoke any existing tokens of this name first, so the old
        // credential stops working the moment the new one is issued.
        if ($this->option('rotate')) {
            $revoked = $user->tokens()->where('name', $tokenName)->delete();
            $this->warn("Rotated: revoked {$revoked} existing '{$tokenName}' token(s).");
        }

        // Mint the scoped token. Sanctum stores only the SHA-256 hash; the
        // plaintext below is the only time the full token is visible.
        $token = $user->createToken($tokenName, $abilities);

        $this->newLine();
        $this->info('Partner service token minted.');
        $this->line('  Partner       : ' . $partner);
        $this->line('  Service user  : ' . $email . ' (id ' . $user->id . ')');
        $this->line('  Token name    : ' . $tokenName);
        $this->line('  Abilities     : ' . implode(', ', $abilities));
        $this->newLine();

        $this->line('  Plaintext token (store now — not recoverable later):');
        $this->line('  ' . $token->plainTextToken);
        $this->newLine();

        $this->line('  Authorization header the partner sends on every /api/v1 request:');
        $this->line('  Authorization: Bearer ' . $token->plainTextToken);
        $this->newLine();

        return self::SUCCESS;
    }
}
