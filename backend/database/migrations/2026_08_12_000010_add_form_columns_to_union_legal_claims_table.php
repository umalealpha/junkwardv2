<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the dedicated per-union form identity columns to `union_legal_claims`.
 *
 * These were also added to the create migration
 * (2026_08_05_000010_create_union_legal_claims_table) for fresh environments,
 * but on envs where that migration had already run the new columns were never
 * applied — a filing then 500s with
 *   SQLSTATE[42S22] Unknown column 'form_code' in 'INSERT INTO union_legal_claims'.
 * This standalone ALTER backfills those envs. Idempotent (hasColumn-guarded).
 *
 *   form_code : union code the form belongs to (e.g. BONU / BOWASEWU).
 *   form_name : printed form title (e.g. "BOWASEWU LEGAL CLAIM FORM").
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('union_legal_claims')) {
            return; // create migration will add the columns on fresh envs
        }

        Schema::table('union_legal_claims', function (Blueprint $table) {
            if (!Schema::hasColumn('union_legal_claims', 'form_code')) {
                $table->string('form_code', 50)->nullable()->index()->after('claim_number');
            }
            if (!Schema::hasColumn('union_legal_claims', 'form_name')) {
                $table->string('form_name', 150)->nullable()->after('form_code');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('union_legal_claims')) {
            return;
        }
        Schema::table('union_legal_claims', function (Blueprint $table) {
            foreach (['form_name', 'form_code'] as $col) {
                if (Schema::hasColumn('union_legal_claims', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
