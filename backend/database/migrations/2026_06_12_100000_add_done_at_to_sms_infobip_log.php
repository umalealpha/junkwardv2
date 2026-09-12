<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `done_at` column to sms_infobip_log.
 *
 * Why this is needed: the Infobip delivery-receipt webhook handler in the
 * application code INSERTs / UPDATEs `done_at` on every callback, but no
 * migration was ever shipped to add the column. PROD log group has been
 * throwing SQLSTATE 42S22 "Unknown column 'done_at'" on every SMS DLR for
 * weeks because the column simply doesn't exist.
 *
 * Idempotent — guarded against re-runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sms_infobip_log') && !Schema::hasColumn('sms_infobip_log', 'done_at')) {
            Schema::table('sms_infobip_log', function (Blueprint $table) {
                // Webhook payload is ISO-8601 with timezone; storing as nullable
                // datetime so the Eloquent cast (if any) renders correctly.
                $table->dateTime('done_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sms_infobip_log') && Schema::hasColumn('sms_infobip_log', 'done_at')) {
            Schema::table('sms_infobip_log', function (Blueprint $table) {
                $table->dropColumn('done_at');
            });
        }
    }
};
