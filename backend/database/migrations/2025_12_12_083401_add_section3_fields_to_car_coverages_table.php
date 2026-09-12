<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSection3FieldsToCarCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('car_coverages', function (Blueprint $table) {
            // Add Section 3 - Insured Interest and Rate
            $table->string('section3_insured_interest')->nullable()->after('section3_time_excess');
            $table->decimal('section3_rate', 10, 4)->nullable()->after('section3_insured_interest');
            
            // Add Section 3 - Limits of Indemnity
            $table->decimal('section3_limit_indemnity_each_loss', 15, 2)->nullable()->after('section3_rate');
            $table->decimal('section3_limit_indemnity_one_accident', 15, 2)->nullable()->after('section3_limit_indemnity_each_loss');
            
            // Add Section 3 - Scheduled Dates
            $table->date('section3_scheduled_date_completion')->nullable()->after('section3_limit_indemnity_one_accident');
            $table->date('section3_scheduled_date_commencement')->nullable()->after('section3_scheduled_date_completion');
            
            // Add Section 3 - Contract Works (JSON array)
            $table->json('section3_contract_works')->nullable()->after('section3_scheduled_date_commencement');
            
            // Change period_insurance fields from string to date (if needed, uncomment)
            // Note: This might require data migration if existing data exists
            // $table->date('section3_period_insurance_from')->nullable()->change();
            // $table->date('section3_period_insurance_to')->nullable()->change();
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
                'section3_insured_interest',
                'section3_rate',
                'section3_limit_indemnity_each_loss',
                'section3_limit_indemnity_one_accident',
                'section3_scheduled_date_completion',
                'section3_scheduled_date_commencement',
                'section3_contract_works',
            ]);
        });
    }
}
