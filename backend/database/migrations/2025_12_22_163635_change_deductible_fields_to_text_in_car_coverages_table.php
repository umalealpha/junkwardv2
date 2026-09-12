<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeDeductibleFieldsToTextInCarCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('car_coverages', function (Blueprint $table) {
            // Change deductible fields from decimal to string to accept both numbers and text
            $table->string('section1_earthquake_deductible')->nullable()->change();
            $table->string('section1_storm_deductible')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('car_coverages', function (Blueprint $table) {
            // Revert back to decimal (note: this may cause data loss if text values exist)
            $table->decimal('section1_earthquake_deductible', 15, 2)->nullable()->change();
            $table->decimal('section1_storm_deductible', 15, 2)->nullable()->change();
        });
    }
}
