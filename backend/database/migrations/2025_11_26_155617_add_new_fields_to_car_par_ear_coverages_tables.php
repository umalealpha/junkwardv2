<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewFieldsToCarParEarCoveragesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add new fields to car_coverages table
        Schema::table('car_coverages', function (Blueprint $table) {
            $table->string('project_name')->nullable()->after('title_of_contract');
            $table->string('policy_period_months')->nullable()->after('city_town_village');
            $table->string('authorization_management')->nullable()->after('policy_period_months');
            $table->string('is_renewable')->nullable()->after('authorization_management');
            $table->string('is_project_specific')->nullable()->after('is_renewable');
            $table->string('maintenance_period_months')->nullable()->after('is_project_specific');
            $table->boolean('reinsurance_fire_treaty')->default(false)->after('maintenance_period_months');
            $table->json('plant_list_items')->nullable()->after('section3_items');
            $table->decimal('section1_total_premium', 15, 2)->nullable()->after('section1_total_sum_insured');
            $table->decimal('section2_total_limit', 15, 2)->nullable()->after('section2_property_damage_premium');
            $table->decimal('section2_total_premium', 15, 2)->nullable()->after('section2_total_limit');
            $table->decimal('section3_total_annual_sum', 15, 2)->nullable()->after('section3_time_excess');
            $table->decimal('section3_total_premium', 15, 2)->nullable()->after('section3_total_annual_sum');
        });

        // Add new fields to par_coverages table
        Schema::table('par_coverages', function (Blueprint $table) {
            $table->string('project_name')->nullable()->after('coverage_id');
            $table->string('policy_period_months')->nullable()->after('project_name');
            $table->string('authorization_management')->nullable()->after('policy_period_months');
            $table->string('is_renewable')->nullable()->after('authorization_management');
            $table->string('is_project_specific')->nullable()->after('is_renewable');
            $table->string('maintenance_period_months')->nullable()->after('is_project_specific');
            $table->boolean('reinsurance_fire_treaty')->default(false)->after('maintenance_period_months');
            $table->decimal('total_sum_insured', 15, 2)->nullable()->after('reinsurance_fire_treaty');
            $table->decimal('total_premium', 15, 2)->nullable()->after('total_sum_insured');
        });

        // Add new fields to ear_coverages table
        Schema::table('ear_coverages', function (Blueprint $table) {
            $table->string('project_name')->nullable()->after('site_of_erection');
            $table->string('policy_period_months')->nullable()->after('project_name');
            $table->string('authorization_management')->nullable()->after('policy_period_months');
            $table->string('is_renewable')->nullable()->after('authorization_management');
            $table->string('is_project_specific')->nullable()->after('is_renewable');
            $table->string('maintenance_period_months')->nullable()->after('is_project_specific');
            $table->boolean('reinsurance_fire_treaty')->default(false)->after('maintenance_period_months');
            $table->decimal('section1_total_premium', 15, 2)->nullable()->after('section1_total_sum_insured');
            $table->decimal('section3_total_limit', 15, 2)->nullable()->after('section3_items');
            $table->decimal('section3_total_premium', 15, 2)->nullable()->after('section3_total_limit');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove fields from car_coverages table
        Schema::table('car_coverages', function (Blueprint $table) {
            $table->dropColumn([
                'project_name',
                'policy_period_months',
                'authorization_management',
                'is_renewable',
                'is_project_specific',
                'maintenance_period_months',
                'reinsurance_fire_treaty',
                'plant_list_items',
                'section1_total_premium',
                'section2_total_limit',
                'section2_total_premium',
                'section3_total_annual_sum',
                'section3_total_premium',
            ]);
        });

        // Remove fields from par_coverages table
        Schema::table('par_coverages', function (Blueprint $table) {
            $table->dropColumn([
                'project_name',
                'policy_period_months',
                'authorization_management',
                'is_renewable',
                'is_project_specific',
                'maintenance_period_months',
                'reinsurance_fire_treaty',
                'total_sum_insured',
                'total_premium',
            ]);
        });

        // Remove fields from ear_coverages table
        Schema::table('ear_coverages', function (Blueprint $table) {
            $table->dropColumn([
                'project_name',
                'policy_period_months',
                'authorization_management',
                'is_renewable',
                'is_project_specific',
                'maintenance_period_months',
                'reinsurance_fire_treaty',
                'section1_total_premium',
                'section3_total_limit',
                'section3_total_premium',
            ]);
        });
    }
}
