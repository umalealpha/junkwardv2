<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Swiftly Finance integration — supporting tables (V2-only).
 *
 *  - swiftly_invoice_submissions: outbound audit + idempotency. invoice_id is
 *    unique so the same invoice can never be double-submitted to Swiftly.
 *  - swiftly_webhook_events: inbound early-payment notifications. event_id is
 *    unique for idempotent dedupe of provider retries; the raw payload is kept
 *    so events captured before the processing logic lands can be replayed.
 *
 * Guarded per the self-healing migration standard. Runs on the default
 * connection (the V2 ops DB / mysql_system in deployed envs). Subject to the
 * audit-log retention policy.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('swiftly_invoice_submissions')) {
            Schema::create('swiftly_invoice_submissions', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_id')->unique();          // our invoice reference — never double-submit
                $table->string('program_id')->nullable();
                $table->string('supplier_id')->nullable()->index();
                $table->unsignedBigInteger('amount_minor')->default(0); // smallest unit (thebe), no decimals
                $table->string('currency', 8)->default('BWP');
                $table->date('due_at')->nullable();
                $table->string('status', 20)->default('pending')->index(); // pending | submitted | failed
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->string('swiftly_reference')->nullable();  // reference Swiftly returns on accept
                $table->string('error', 500)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }

        // Generic per-integration on/off switch (reusable beyond Swiftly).
        // Authoritative runtime flag an authorised operator can toggle from
        // the admin UI without a redeploy. Each toggle is also written to the
        // Spatie activity log (who/when/old→new) — see IntegrationSettings.
        if (!Schema::hasTable('integration_settings')) {
            Schema::create('integration_settings', function (Blueprint $table) {
                $table->id();
                $table->string('integration')->unique();           // slug, e.g. 'swiftly'
                $table->boolean('enabled')->default(false);
                $table->unsignedBigInteger('updated_by')->nullable(); // user id of last toggler
                $table->string('updated_by_name')->nullable();        // email/name snapshot
                $table->string('notes', 500)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('swiftly_webhook_events')) {
            Schema::create('swiftly_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_id')->nullable()->unique();  // provider event id — idempotent dedupe
                $table->string('event_type', 64)->nullable()->index();
                $table->boolean('signature_valid')->default(false);
                $table->string('status', 20)->default('received')->index(); // received | processed | failed
                $table->longText('payload')->nullable();           // raw verified JSON body
                $table->string('error', 500)->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('swiftly_webhook_events');
        Schema::dropIfExists('integration_settings');
        Schema::dropIfExists('swiftly_invoice_submissions');
    }
};
