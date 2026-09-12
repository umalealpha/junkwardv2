<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add WhatsApp AI access control fields to user_profile.
 *
 * whatsapp_access     — YES / NO  (NULL = not set / use default role logic)
 * whatsapp_visibility — Admin / Full / Only department / Self
 *
 * Admin       → full BI + DevOps tools (same as exco)
 * Full        → full BI access (execute_query, charts, stats)
 * Only department → BI data scoped to user's department
 * Self        → own policies / claims only (like agent view)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profile', function (Blueprint $table) {
            $table->enum('whatsapp_access',     ['YES', 'NO'])->nullable()->default(null)->after('work_phone_inner');
            $table->enum('whatsapp_visibility', ['Admin', 'Full', 'Only department', 'Self'])->nullable()->default(null)->after('whatsapp_access');
        });
    }

    public function down(): void
    {
        Schema::table('user_profile', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_access', 'whatsapp_visibility']);
        });
    }
};
