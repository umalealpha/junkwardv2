<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPolicyWordingFieldsToMedicalMalpracticeCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('medical_malpractice_coverages', function (Blueprint $table) {
            if (!Schema::hasColumn('medical_malpractice_coverages', 'policy_wording_path')) {
                $table->string('policy_wording_path')->nullable()->after('policy_wording');
            }
            if (!Schema::hasColumn('medical_malpractice_coverages', 'policy_wording_filename')) {
                $table->string('policy_wording_filename')->nullable()->after('policy_wording_path');
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
        Schema::table('medical_malpractice_coverages', function (Blueprint $table) {
            if (Schema::hasColumn('medical_malpractice_coverages', 'policy_wording_filename')) {
                $table->dropColumn('policy_wording_filename');
            }
            if (Schema::hasColumn('medical_malpractice_coverages', 'policy_wording_path')) {
                $table->dropColumn('policy_wording_path');
            }
        });
    }
}
