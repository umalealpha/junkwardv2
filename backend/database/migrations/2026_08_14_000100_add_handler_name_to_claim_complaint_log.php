<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editable "Name of Employee Handling the Complaint" for the Complaints
 * Register. `handler_user_id` links to the claim's allocated handler (the
 * default), but the register field is a NAME and must be editable (the person
 * handling the complaint isn't always the claim's allocated handler). Store
 * the typed name alongside the id; the register displays this when present.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('claim_complaint_log', 'handler_name')) {
            Schema::table('claim_complaint_log', function (Blueprint $t) {
                $t->string('handler_name', 200)->nullable()->after('handler_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('claim_complaint_log', 'handler_name')) {
            Schema::table('claim_complaint_log', function (Blueprint $t) {
                $t->dropColumn('handler_name');
            });
        }
    }
};
