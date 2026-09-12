<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add claim_sub_status to the claims table (2026-06-30).
     *
     * The 'Open' main status carries a working sub-status (e.g. "Awaiting
     * Invoice"), written by ClaimsController::updateStatus and read back by
     * the claim detail endpoint. The pre-existing claim_sub_status column
     * lives on the separate new_claims table, NOT on claims — so this adds
     * the dedicated column on claims for the V2 Open-status flow.
     *
     * Plain nullable varchar; forward-only; existing rows keep NULL.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('claims', 'claim_sub_status')) {
            Schema::table('claims', function (Blueprint $table) {
                $table->string('claim_sub_status')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('claims', 'claim_sub_status')) {
            Schema::table('claims', function (Blueprint $table) {
                $table->dropColumn('claim_sub_status');
            });
        }
    }
};
