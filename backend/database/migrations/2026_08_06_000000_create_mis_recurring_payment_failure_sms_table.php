<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Failed-recurring-payment SMS ledger for MIS (Instant Insurance) policies.
 *
 * One row per (gateway, event_reference) failed recurring collection. The
 * UNIQUE index is the duplicate guard, not an application-level "have we sent
 * this?" SELECT: RealPay installment webhooks are replayed by
 * ProcessWebhookBuffer and the DPO recurring crons can be re-run by hand, so
 * the only safe dedup is one the database enforces.
 *
 * The SMS body / recipient / status / timestamp still land in `sms_email_log`
 * (written by SendSmsFired). This table is the idempotency key plus a pointer
 * back to that log row, so Finance can answer "which failed debit did this
 * SMS belong to?".
 */
return new class extends Migration {
    private const TABLE = 'mis_recurring_payment_failure_sms';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            echo '[' . self::TABLE . "] already exists; skipping create.\n";
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('policyNumber', 100)->index();
            $table->integer('policy_id')->nullable();
            $table->string('customer_id', 111)->nullable();

            // 'DPO' | 'RealPay'
            $table->string('gateway', 20);

            // Gateway-specific identifier of the ONE failed collection event:
            //   DPO     -> scheduled_transactions id + installment + retry_count
            //   RealPay -> InstalmentReferenceNumber + InstalmentSequence
            $table->string('event_reference', 190);

            $table->string('to_cellphone', 50)->nullable();
            $table->text('message')->nullable();

            // SENT | SUPPRESSED (SmsControls off) | NO_CELLPHONE | POLICY_NOT_FOUND | ERROR
            $table->string('status', 30)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('sms_email_log_id')->nullable();

            $table->timestamps();

            $table->unique(['gateway', 'event_reference'], 'mis_recur_fail_gateway_event_unique');
        });

        echo '[' . self::TABLE . "] created.\n";
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
