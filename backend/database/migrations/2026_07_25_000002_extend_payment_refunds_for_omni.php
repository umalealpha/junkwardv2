<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Refund Engine — extend payment_refunds so the Omni money leg reuses
 * the existing, idempotent RefundService::postRefundToLedger() unchanged.
 *
 *  - source:            'dpo' (existing tool) vs 'omni' (Refund Engine handoff)
 *  - refund_request_id: link back to the governance record
 *  - graphite_ref:      the engine's idempotency key (RFND-000123), matched on
 *                       the Omni paid callback and by settlement reconciliation
 *  - omni_paid_ref:     Omni's fnb_reference from the paid callback
 *
 * payment_transaction_id becomes NULLABLE: an Omni premium refund is not always
 * tied to one original charge. postRefundToLedger() already tolerates a null
 * original tx — it resolves the policy from payment_refunds.policy_number,
 * which the Omni path always sets.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_refunds')) {
            return;
        }

        Schema::table('payment_refunds', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_refunds', 'source')) {
                $table->string('source', 12)->default('dpo')
                      ->comment('dpo = legacy refund tool · omni = Customer Refund Engine handoff');
            }
            if (!Schema::hasColumn('payment_refunds', 'refund_request_id')) {
                $table->unsignedBigInteger('refund_request_id')->nullable()->index();
            }
            if (!Schema::hasColumn('payment_refunds', 'graphite_ref')) {
                $table->string('graphite_ref', 64)->nullable()->index()
                      ->comment('refund_requests.graphite_ref — Omni idempotency key');
            }
            if (!Schema::hasColumn('payment_refunds', 'omni_paid_ref')) {
                $table->string('omni_paid_ref', 120)->nullable();
            }
        });

        // Raw ALTER (not ->change()) so we don't need doctrine/dbal. Guarded:
        // only run when the column is still NOT NULL.
        $col = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_refunds'
               AND COLUMN_NAME = 'payment_transaction_id'"
        );
        if ($col && strtoupper((string) $col->IS_NULLABLE) === 'NO') {
            DB::statement(
                "ALTER TABLE payment_refunds
                 MODIFY payment_transaction_id BIGINT UNSIGNED NULL
                 COMMENT 'The original transaction being refunded — NULL for Omni engine refunds'"
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_refunds')) {
            return;
        }
        Schema::table('payment_refunds', function (Blueprint $table) {
            foreach (['source', 'refund_request_id', 'graphite_ref', 'omni_paid_ref'] as $col) {
                if (Schema::hasColumn('payment_refunds', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        // payment_transaction_id is deliberately left nullable on rollback —
        // restoring NOT NULL would fail if omni rows (NULL tx) exist.
    }
};
