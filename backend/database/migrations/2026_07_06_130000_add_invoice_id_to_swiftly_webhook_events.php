<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Swiftly's early-payment webhook carries no event id — the natural
 * idempotency key is invoice_id (one early-payment per invoice). Add an
 * indexed invoice_id column so the receiver can dedupe provider retries
 * (previously it only deduped on the always-null event_id → retries created
 * duplicate rows). Guarded per the self-healing migration standard.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('swiftly_webhook_events') && !Schema::hasColumn('swiftly_webhook_events', 'invoice_id')) {
            Schema::table('swiftly_webhook_events', function (Blueprint $table) {
                $table->string('invoice_id')->nullable()->after('event_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('swiftly_webhook_events') && Schema::hasColumn('swiftly_webhook_events', 'invoice_id')) {
            Schema::table('swiftly_webhook_events', function (Blueprint $table) {
                $table->dropColumn('invoice_id');
            });
        }
    }
};
