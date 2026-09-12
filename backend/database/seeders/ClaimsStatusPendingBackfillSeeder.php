<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Backfills claims.status = 'Pending' for legacy rows where status is an
 * empty string ('').
 *
 * Context: older claims (pre-2025) were created without a status value and
 * carry status = '' in the DB. New claims are created with status = 'Pending'
 * (see WhatsAppClaimBot / Helper). This seeder brings the legacy rows in line
 * so status filters and reports don't miss them.
 *
 * Idempotent — safe to run multiple times; only touches rows still at ''.
 * Run after deployment:
 *   php artisan db:seed --class=Database\\Seeders\\ClaimsStatusPendingBackfillSeeder
 *
 * NOTE: This is a bulk UPDATE and does NOT write per-row entries to the
 * OwenIt audit trail (Claim is Auditable). That is intentional for a
 * one-time data backfill — a per-row Eloquent save would flood the audits
 * table. If an audit record of the backfill is required, it is captured by
 * the console output / this seeder's presence in version control.
 */
class ClaimsStatusPendingBackfillSeeder extends Seeder
{
    public function run(): void
    {
        // Only empty-string statuses, per the requirement. NULLs (if any)
        // are left untouched — flip the query to add ->orWhereNull('status')
        // if those should be backfilled too.
        $affected = DB::table('claims')
            ->where('status', '')
            ->update([
                'status'     => 'Pending',
                'updated_at' => now(),
            ]);

        // $this->command is null when the seeder is instantiated directly
        // (e.g. from a migration without the console context); guard it so
        // the backfill never fails on the log line.
        $message = "ClaimsStatusPendingBackfillSeeder: set status='Pending' on {$affected} claim(s).";
        if ($this->command) {
            $this->command->info($message);
        } else {
            echo $message . "\n";
        }
    }
}
