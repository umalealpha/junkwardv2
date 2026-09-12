<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the ServiceRef + refund tracking columns onto payment_transactions.
 *
 * service_ref   — stable internal identifier sent to DPO as CompanyRef /
 *                 ServiceRef. Format: "POL-{policy_id}-{customer_id}".
 *                 Makes each transaction traceable back to a policy + customer
 *                 even if customer email is later edited or mis-entered.
 * refund_status — tracks whether this payment has been refunded.
 * refunded_amount — running total of refunds issued (supports partial refunds).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_transactions', 'service_ref')) {
                $table->string('service_ref', 80)->nullable()->after('paymentMethod')
                    ->comment('Stable internal ref sent to DPO as CompanyRef — POL-{policy_id}-{customer_id}');
                $table->index('service_ref', 'idx_payment_transactions_service_ref');
            }
            if (!Schema::hasColumn('payment_transactions', 'refund_status')) {
                $table->enum('refund_status', ['none', 'partial', 'full'])
                      ->default('none')->after('status')
                      ->comment('none|partial|full refund state');
            }
            if (!Schema::hasColumn('payment_transactions', 'refunded_amount')) {
                $table->decimal('refunded_amount', 13, 2)->default(0)
                      ->after('refund_status')
                      ->comment('Running total of refunds issued against this tx');
            }
            if (!Schema::hasColumn('payment_transactions', 'last_refund_at')) {
                $table->timestamp('last_refund_at')->nullable()
                      ->after('refunded_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('payment_transactions', 'last_refund_at'))   $table->dropColumn('last_refund_at');
            if (Schema::hasColumn('payment_transactions', 'refunded_amount'))  $table->dropColumn('refunded_amount');
            if (Schema::hasColumn('payment_transactions', 'refund_status'))    $table->dropColumn('refund_status');
            if (Schema::hasColumn('payment_transactions', 'service_ref')) {
                try { $table->dropIndex('idx_payment_transactions_service_ref'); } catch (\Throwable $e) { /* ignore */ }
                $table->dropColumn('service_ref');
            }
        });
    }
};
