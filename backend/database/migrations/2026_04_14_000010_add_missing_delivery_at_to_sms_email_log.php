<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds delivery_at column if it was skipped by the earlier
 * 2026_04_14_000003 migration (which used an after() clause that
 * sometimes fails on tables with certain MySQL versions).
 */
class AddMissingDeliveryAtToSmsEmailLog extends Migration
{
    public function up()
    {
        if (Schema::hasTable('sms_email_log') && !Schema::hasColumn('sms_email_log', 'delivery_at')) {
            Schema::table('sms_email_log', function (Blueprint $table) {
                $table->timestamp('delivery_at')->nullable()->after('status');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('sms_email_log') && Schema::hasColumn('sms_email_log', 'delivery_at')) {
            Schema::table('sms_email_log', function (Blueprint $table) {
                $table->dropColumn('delivery_at');
            });
        }
    }
}
