<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SplitSection3InsuredInterestOnCarCoveragesTable extends Migration
{
    public function up()
    {
        Schema::table('car_coverages', function (Blueprint $table) {
            $table->string('section3_gross_profit_insured_interest')->nullable()->after('section3_gross_profit_premium');
            $table->string('section3_increased_cost_insured_interest')->nullable()->after('section3_increased_cost_premium');
        });

        // Migrate existing single section3_insured_interest value into the new
        // gross-profit-specific column so historical records keep their value
        // on the (now split) Section 3 form.
        DB::statement('UPDATE car_coverages SET section3_gross_profit_insured_interest = section3_insured_interest WHERE section3_insured_interest IS NOT NULL');
    }

    public function down()
    {
        Schema::table('car_coverages', function (Blueprint $table) {
            $table->dropColumn([
                'section3_gross_profit_insured_interest',
                'section3_increased_cost_insured_interest',
            ]);
        });
    }
}
