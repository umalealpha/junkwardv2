<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Claims Tracker integration columns to the `claims` table:
 *
 *   - external_ref   caller-supplied idempotency key (Claims Tracker's own
 *                    claim ID). Unique index lets us reject duplicate
 *                    POSTs by returning the existing claim_number.
 *   - source         origin tag ("claims-tracker", "admin", "mobile", ...).
 *                    Lets ops slice claims by origin without log archaeology.
 *
 * Also makes `created_by` nullable so external API callers (which have no
 * authenticated user) can insert. Existing admin flow still writes the
 * authed user id, so nothing changes for it.
 *
 * Port of graphiteBWV8 migration 2026_05_22_000001 (feat/claims-tracker-api)
 * onto Graphite V2. Additive + nullable. Down() leaves created_by nullable
 * because tightening it back up requires knowing the original DDL (the
 * `claims` table predates Laravel migrations in this repo).
 */
class AddClaimsTrackerColumnsToClaimsTable extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            if (! Schema::hasColumn('claims', 'external_ref')) {
                $table->string('external_ref', 80)->nullable()->after('claim_number');
            }
            if (! Schema::hasColumn('claims', 'source')) {
                $table->string('source', 40)->nullable()->after('external_ref');
            }
        });

        // Unique index on external_ref so concurrent Claims Tracker retries
        // collide at the DB level. NULLs are allowed multiple times under
        // MySQL's default unique-index semantics, so existing rows (which
        // have NULL external_ref) are unaffected.
        $indexes = collect(DB::select("SHOW INDEX FROM claims WHERE Key_name = 'claims_external_ref_unique'"));
        if ($indexes->isEmpty()) {
            Schema::table('claims', function (Blueprint $table) {
                $table->unique('external_ref', 'claims_external_ref_unique');
            });
        }

        // Make created_by nullable. The legacy column type is varchar (the
        // admin flow writes auth()->user()->id stringified into it), so we
        // mirror that here. Wrapped in a try because some environments may
        // already have it nullable.
        try {
            DB::statement("ALTER TABLE claims MODIFY created_by VARCHAR(255) NULL");
        } catch (\Throwable $e) {
            // Non-fatal — column may already be nullable, or doctrine/dbal
            // is unavailable. The new endpoint guards against this by
            // writing a sentinel string when the column rejects NULL.
        }
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $indexes = collect(DB::select("SHOW INDEX FROM claims WHERE Key_name = 'claims_external_ref_unique'"));
            if ($indexes->isNotEmpty()) {
                $table->dropUnique('claims_external_ref_unique');
            }

            foreach (['external_ref', 'source'] as $col) {
                if (Schema::hasColumn('claims', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
