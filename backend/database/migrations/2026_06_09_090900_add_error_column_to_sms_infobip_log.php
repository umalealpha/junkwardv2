<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sms_infobip_log')) {
            Schema::table('sms_infobip_log', function (Blueprint $table) {
                if (!Schema::hasColumn('sms_infobip_log', 'error')) {
                    $table->text('error')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sms_infobip_log')) {
            Schema::table('sms_infobip_log', function (Blueprint $table) {
                if (Schema::hasColumn('sms_infobip_log', 'error')) {
                    $table->dropColumn('error');
                }
            });
        }
    }
};
