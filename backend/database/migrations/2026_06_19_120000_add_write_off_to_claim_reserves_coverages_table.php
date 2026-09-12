<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-coverage "write off" flag on claim reserve line items.
 *
 * When a Loss Reserve (transaction_type 42) is logged against the
 * "Claim Expense" sub-type, the user can mark an individual coverage /
 * claimed item as a write-off. The flag is stored per coverage row (not
 * per reserve header) because a single reserve submission can allocate
 * across several coverages and only some may be written off.
 *
 * Setting the flag also fires Finance + Underwriting notifications — see
 * ClaimsV2Controller::storeReserve() and NotificationDispatcher::claimWriteOff().
 * write_off_at / write_off_by give us an audit trail of who flagged it and when.
 */
class AddWriteOffToClaimReservesCoveragesTable extends Migration
{
    public function up(): void
    {
        Schema::table('claim_reserves_coverages', function (Blueprint $table) {
            if (! Schema::hasColumn('claim_reserves_coverages', 'write_off')) {
                $table->boolean('write_off')->default(false)->after('balance');
            }
            if (! Schema::hasColumn('claim_reserves_coverages', 'write_off_at')) {
                $table->timestamp('write_off_at')->nullable()->after('write_off');
            }
            if (! Schema::hasColumn('claim_reserves_coverages', 'write_off_by')) {
                $table->unsignedBigInteger('write_off_by')->nullable()->after('write_off_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('claim_reserves_coverages', function (Blueprint $table) {
            foreach (['write_off_by', 'write_off_at', 'write_off'] as $col) {
                if (Schema::hasColumn('claim_reserves_coverages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
