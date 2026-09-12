<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add a nullable customer_kyc_id pointer to kyc_activity_log so the
     * MIS Customer KYC review page can scope its Activity Log tab to the
     * specific customer_kyc row a reviewer is looking at (V8 parity).
     *
     * Historically the log was keyed only on customer_id, which collides
     * when a customer has multiple customer_kyc rows (registration stub +
     * later policy-time row) — the activity log surfaced entries from
     * both rows under whichever one the reviewer opened. New inserts
     * write customer_kyc_id additively; legacy rows stay readable via
     * the existing customer_id column.
     */
    public function up(): void
    {
        if (!Schema::hasTable('kyc_activity_log')) return;
        if (Schema::hasColumn('kyc_activity_log', 'customer_kyc_id')) return;

        Schema::table('kyc_activity_log', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_kyc_id')->nullable()->after('customer_id');
            $table->index('customer_kyc_id', 'kyc_activity_log_customer_kyc_id_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('kyc_activity_log')) return;
        if (!Schema::hasColumn('kyc_activity_log', 'customer_kyc_id')) return;

        Schema::table('kyc_activity_log', function (Blueprint $table) {
            $table->dropIndex('kyc_activity_log_customer_kyc_id_idx');
            $table->dropColumn('customer_kyc_id');
        });
    }
};
