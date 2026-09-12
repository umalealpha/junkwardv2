<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalFieldsToCoveragesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add approval fields to car_coverages table
        Schema::table('car_coverages', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by')->nullable()->after('authorization_management');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        // Add approval fields to ear_coverages table
        Schema::table('ear_coverages', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by')->nullable()->after('authorization_management');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        // Add approval fields to par_coverages table
        Schema::table('par_coverages', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by')->nullable()->after('authorization_management');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove approval fields from car_coverages table
        Schema::table('car_coverages', function (Blueprint $table) {
            $table->dropColumn(['approved_by', 'approved_at']);
        });

        // Remove approval fields from ear_coverages table
        Schema::table('ear_coverages', function (Blueprint $table) {
            $table->dropColumn(['approved_by', 'approved_at']);
        });

        // Remove approval fields from par_coverages table
        Schema::table('par_coverages', function (Blueprint $table) {
            $table->dropColumn(['approved_by', 'approved_at']);
        });
    }
}
