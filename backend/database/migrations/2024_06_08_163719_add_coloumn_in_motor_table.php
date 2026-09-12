<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColoumnInMotorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('motor', function (Blueprint $table) {
            if (!Schema::hasColumn('motor', 'unorthorised_passanger_liability')) {
                $table->string('unorthorised_passanger_liability')->after('specified_accessories')->nullable();
            }
            if (!Schema::hasColumn('motor', 'parking_facilities')) {
                $table->string('parking_facilities')->after('unorthorised_passanger_liability')->nullable();
            }
            if (!Schema::hasColumn('motor', 'com_windscreen')) {
                $table->string('com_windscreen')->after('parking_facilities')->nullable();
            }
            if (!Schema::hasColumn('motor', 'premium_unorthorised_passanger_liability')) {
                $table->string('premium_unorthorised_passanger_liability')->after('com_windscreen')->nullable();
            }
            if (!Schema::hasColumn('motor', 'premium_parking_facilities')) {
                $table->string('premium_parking_facilities')->after('premium_unorthorised_passanger_liability')->nullable();
            }
            if (!Schema::hasColumn('motor', 'premium_com_windscreen')) {
                $table->string('premium_com_windscreen')->after('premium_parking_facilities')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tb_cvgpccoverages', function (Blueprint $table) {
            $table->dropColumn('premium_parking_facilities')->nullable();
        });
    }
}
