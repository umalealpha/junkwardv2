<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProRateColumnsToMotorTradersTables extends Migration
{
    public function up()
    {
        foreach (['motor_traders', 'motor_traders_internal'] as $tbl) {
            if (!Schema::hasTable($tbl)) continue;
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (!Schema::hasColumn($tbl, 'pro_rate_premium')) {
                    $table->decimal('pro_rate_premium', 20, 2)->default(0)->nullable();
                }
                if (!Schema::hasColumn($tbl, 'previousActionIdCov')) {
                    $table->unsignedBigInteger('previousActionIdCov')->default(0)->nullable();
                }
                if (!Schema::hasColumn($tbl, 'endors_flag')) {
                    $table->tinyInteger('endors_flag')->default(0)->nullable();
                }
            });
        }
    }

    public function down()
    {
        foreach (['motor_traders', 'motor_traders_internal'] as $tbl) {
            if (!Schema::hasTable($tbl)) continue;
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                foreach (['pro_rate_premium', 'previousActionIdCov', 'endors_flag'] as $col) {
                    if (Schema::hasColumn($tbl, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
}
