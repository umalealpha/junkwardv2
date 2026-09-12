<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalFieldsToTravelCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add approval fields to travel_coverages table
        Schema::table('travel_coverages', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by')->nullable()->after('total');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('policy_period_months')->nullable()->after('expiry');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove approval fields from travel_coverages table
        Schema::table('travel_coverages', function (Blueprint $table) {
            $table->dropColumn(['approved_by', 'approved_at', 'policy_period_months']);
        });
    }
}
