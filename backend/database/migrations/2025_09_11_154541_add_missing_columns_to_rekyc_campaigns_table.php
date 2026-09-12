<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToRekycCampaignsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rekyc_campaigns', function (Blueprint $table) {
            $table->integer('escalation_days')->default(7)->after('max_attempts');
            $table->json('reminder_days')->nullable()->after('escalation_days');
            $table->json('settings')->nullable()->after('reminder_days');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rekyc_campaigns', function (Blueprint $table) {
            $table->dropColumn(['escalation_days', 'reminder_days', 'settings']);
        });
    }
}
