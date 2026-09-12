<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMessageIdToWhatsAppLog extends Migration
{
    public function up()
    {
        if (Schema::hasTable('whats_app_log') && !Schema::hasColumn('whats_app_log', 'message_id')) {
            Schema::table('whats_app_log', function (Blueprint $table) {
                $table->string('message_id', 200)->nullable()->after('status')->index();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('whats_app_log') && Schema::hasColumn('whats_app_log', 'message_id')) {
            Schema::table('whats_app_log', function (Blueprint $table) {
                $table->dropIndex(['message_id']);
                $table->dropColumn('message_id');
            });
        }
    }
}
