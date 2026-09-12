<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSection3GrossProfitAndIncreasedCostFieldsToCarCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('car_coverages', function (Blueprint $table) {
            // Add Gross Profit fields
            $table->decimal('section3_gross_profit_annual_sum_insured', 15, 2)->nullable()->after('section3_premium');
            $table->decimal('section3_gross_profit_rate', 10, 4)->nullable()->after('section3_gross_profit_annual_sum_insured');
            $table->decimal('section3_gross_profit_premium', 15, 2)->nullable()->after('section3_gross_profit_rate');
            
            // Add Increased Cost of Working fields
            $table->decimal('section3_increased_cost_sum_insured', 15, 2)->nullable()->after('section3_gross_profit_premium');
            $table->decimal('section3_increased_cost_rate', 10, 4)->nullable()->after('section3_increased_cost_sum_insured');
            $table->decimal('section3_increased_cost_premium', 15, 2)->nullable()->after('section3_increased_cost_rate');
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
            $table->dropColumn([
                'section3_gross_profit_annual_sum_insured',
                'section3_gross_profit_rate',
                'section3_gross_profit_premium',
                'section3_increased_cost_sum_insured',
                'section3_increased_cost_rate',
                'section3_increased_cost_premium',
            ]);
        });
    }
}
