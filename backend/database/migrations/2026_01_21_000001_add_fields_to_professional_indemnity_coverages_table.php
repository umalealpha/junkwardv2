<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToProfessionalIndemnityCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('professional_indemnity_coverages', function (Blueprint $table) {
            if (!Schema::hasColumn('professional_indemnity_coverages', 'policy_inception_date')) {
                $table->date('policy_inception_date')->nullable();
            }
            if (!Schema::hasColumn('professional_indemnity_coverages', 'policy_expiry_date')) {
                $table->date('policy_expiry_date')->nullable();
            }
            if (!Schema::hasColumn('professional_indemnity_coverages', 'today_date')) {
                $table->date('today_date')->nullable();
            }
            if (!Schema::hasColumn('professional_indemnity_coverages', 'new_altered')) {
                $table->string('new_altered')->nullable();
            }
            if (!Schema::hasColumn('professional_indemnity_coverages', 'is_renewable')) {
                $table->string('is_renewable')->nullable();
            }
            if (!Schema::hasColumn('professional_indemnity_coverages', 'is_project_specific')) {
                $table->string('is_project_specific')->nullable();
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
        Schema::table('professional_indemnity_coverages', function (Blueprint $table) {
            if (Schema::hasColumn('professional_indemnity_coverages', 'policy_inception_date')) {
                $table->dropColumn('policy_inception_date');
            }
            if (Schema::hasColumn('professional_indemnity_coverages', 'policy_expiry_date')) {
                $table->dropColumn('policy_expiry_date');
            }
            if (Schema::hasColumn('professional_indemnity_coverages', 'today_date')) {
                $table->dropColumn('today_date');
            }
            if (Schema::hasColumn('professional_indemnity_coverages', 'new_altered')) {
                $table->dropColumn('new_altered');
            }
            if (Schema::hasColumn('professional_indemnity_coverages', 'is_renewable')) {
                $table->dropColumn('is_renewable');
            }
            if (Schema::hasColumn('professional_indemnity_coverages', 'is_project_specific')) {
                $table->dropColumn('is_project_specific');
            }
        });
    }
}
