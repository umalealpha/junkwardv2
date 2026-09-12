<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnWhatsAppLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Idempotent — guarded against re-runs when this migration is not
        // recorded in the `migrations` tracking table but the column already
        // exists on the live DB (post-cutover state on PROD where the
        // migrations history drifted from the actual schema).
        if (Schema::hasTable('whats_app_log') && !Schema::hasColumn('whats_app_log', 'WA_cellphone')) {
            Schema::table('whats_app_log', function ($table) {
                $table->string('WA_cellphone')->nullable()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('whats_app_log') && Schema::hasColumn('whats_app_log', 'WA_cellphone')) {
            Schema::table('whats_app_log', function ($table) {
                $table->dropColumn('WA_cellphone');
            });
        }
    }
}
