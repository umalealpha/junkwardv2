<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToMedicalMalpracticeCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('medical_malpractice_coverages', function (Blueprint $table) {
            $table->date('policy_inception_date')->nullable()->after('period_of_insurance');
            $table->date('policy_expiry_date')->nullable()->after('policy_inception_date');
            $table->date('today_date')->nullable()->after('policy_expiry_date');
            $table->string('new_altered')->nullable()->after('today_date');
            $table->string('is_renewable')->nullable()->after('new_altered');
            $table->string('is_project_specific')->nullable()->after('is_renewable');
            $table->json('risk_details')->nullable()->after('additional_reporting_period');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('medical_malpractice_coverages', function (Blueprint $table) {
            $table->dropColumn([
                'policy_inception_date',
                'policy_expiry_date',
                'today_date',
                'new_altered',
                'is_renewable',
                'is_project_specific',
                'risk_details',
            ]);
        });
    }
}
