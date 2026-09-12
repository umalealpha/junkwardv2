<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalFieldsToCommissionLedger extends Migration
{
    /**
     * Run the migrations.
     *
     * The CommissionController references approved_by, approved_at, and
     * reference columns that were missing from the original create migration.
     * This adds them so the approve / bulkApprove / ledger list endpoints work.
     */
    public function up()
    {
        Schema::table('commission_ledger', function (Blueprint $table) {
            if (!Schema::hasColumn('commission_ledger', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('paid_batch_id');
            }
            if (!Schema::hasColumn('commission_ledger', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('commission_ledger', 'reference')) {
                $table->string('reference', 100)->nullable()->after('approved_at');
                $table->index('reference');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('commission_ledger', function (Blueprint $table) {
            if (Schema::hasColumn('commission_ledger', 'reference')) {
                $table->dropIndex(['reference']);
                $table->dropColumn('reference');
            }
            if (Schema::hasColumn('commission_ledger', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('commission_ledger', 'approved_by')) {
                $table->dropColumn('approved_by');
            }
        });
    }
}
