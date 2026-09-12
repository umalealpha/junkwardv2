<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeliveryFieldsToSmsEmailLogs extends Migration
{
    public function up()
    {
        if (Schema::hasTable('sms_email_log')) {
            Schema::table('sms_email_log', function (Blueprint $table) {
                if (!Schema::hasColumn('sms_email_log', 'status')) {
                    $table->string('status', 30)->nullable()->after('content_type');
                }
                if (!Schema::hasColumn('sms_email_log', 'delivery_at')) {
                    $table->timestamp('delivery_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn('sms_email_log', 'to_email')) {
                    $table->string('to_email', 200)->nullable()->after('delivery_at');
                }
                if (!Schema::hasColumn('sms_email_log', 'to_cellphone')) {
                    $table->string('to_cellphone', 50)->nullable()->after('to_email');
                }
                if (!Schema::hasColumn('sms_email_log', 'message_id')) {
                    $table->string('message_id', 200)->nullable()->after('to_cellphone');
                    $table->index('message_id');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('sms_email_log')) {
            Schema::table('sms_email_log', function (Blueprint $table) {
                $cols = ['status', 'delivery_at', 'to_email', 'to_cellphone'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('sms_email_log', $col)) $table->dropColumn($col);
                }
                if (Schema::hasColumn('sms_email_log', 'message_id')) {
                    $table->dropIndex(['message_id']);
                    $table->dropColumn('message_id');
                }
            });
        }
    }
}
