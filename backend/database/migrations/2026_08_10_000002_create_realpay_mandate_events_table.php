<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only log of every RealPay payload that touched a mandate.
 *
 * Written BEFORE any state mutation, so a mapping bug loses the interpretation
 * but never the evidence. This is what makes replay safe (ProcessWebhookBuffer
 * re-runs the same instalment payloads every minute) and what makes a DebiCheck
 * dispute answerable months later.
 *
 * `realpay_webhook_response` already captures instalment webhooks for the
 * accounts team's report; this table is deliberately separate because it records
 * what the *mandate* layer decided about each payload, not just that it arrived.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('realpay_mandate_events')) {
            Schema::create('realpay_mandate_events', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('mandate_id')->nullable()->index();

                // Deterministic natural key for RealPay instalment webhooks, which
                // carry no provider event id — see
                // RealPayMandateService::instalmentEventId(). Unique so a replayed
                // payload is recognised instead of appended twice.
                $table->string('event_id', 128)->nullable();

                $table->string('event_type', 64);
                $table->string('policy_number', 32)->nullable()->index();
                $table->string('contract_number', 64)->nullable();

                $table->json('payload');

                // pending | applied | duplicate | ignored | failed
                $table->string('processing_status', 24)->default('pending');
                $table->text('error')->nullable();

                // How many times this same payload has been delivered/replayed.
                $table->unsignedSmallInteger('deliveries')->default(1);

                $table->timestamps();

                $table->unique('event_id', 'uq_realpay_mandate_events_event_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('realpay_mandate_events');
    }
};
