<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Run ClaimsStatusPendingBackfillSeeder as part of `php artisan migrate`.
 *
 * Why a migration instead of just running the seeder
 * --------------------------------------------------
 * Server deploys run `migrate` automatically; a separate `db:seed --class=...`
 * has to be remembered and has been missed before. Wrapping the seed in a
 * migration ties the data change to the code change so they ship together.
 * (Same rationale as 2026_06_12_090000_seed_policy_kyc_documents_upload_permission.)
 *
 * The seeder is left intact so it can still be re-run standalone against any
 * environment without this migration.
 *
 * Idempotent: the seeder only touches rows still at status='' (the MySQL
 * empty-string enum error-value on legacy claims), so re-running is a no-op.
 *
 * --force is required because deploys run in the production environment,
 * where db:seed otherwise prompts for interactive confirmation.
 */
return new class extends Migration {
    public function up(): void
    {
        Artisan::call('db:seed', [
            '--class' => \Database\Seeders\ClaimsStatusPendingBackfillSeeder::class,
            '--force' => true,
        ]);

        // Surface the seeder's output in the migrate log.
        echo Artisan::output();
    }

    public function down(): void
    {
        // No-op: a status='' -> 'Pending' backfill cannot be safely reversed.
        // Once applied, seeded rows are indistinguishable from claims that
        // were legitimately 'Pending', so we do not attempt to roll back.
    }
};
