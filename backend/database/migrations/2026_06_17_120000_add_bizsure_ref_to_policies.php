<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency key for BizSure createPolicy.
 *
 * BizSure's Rails client retries createPolicy on timeout/5xx. Without a
 * stable key per quote, each retry created a duplicate policy + invoice +
 * RealPay intent. We store the partner-supplied reference here and the
 * orchestrator short-circuits when a policy with the same ref already
 * exists. The UNIQUE index is the concurrency backstop (two simultaneous
 * first-time retries) — nullable, so non-BizSure policies stay NULL and
 * MariaDB permits many NULLs under a unique index.
 */
class AddBizsureRefToPolicies extends Migration
{
    public function up(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            if (! Schema::hasColumn('policies', 'bizsure_ref')) {
                $table->string('bizsure_ref', 100)->nullable()->after('leadSource');
                $table->unique('bizsure_ref', 'idx_policies_bizsure_ref_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            if (Schema::hasColumn('policies', 'bizsure_ref')) {
                $table->dropUnique('idx_policies_bizsure_ref_unique');
                $table->dropColumn('bizsure_ref');
            }
        });
    }
}
