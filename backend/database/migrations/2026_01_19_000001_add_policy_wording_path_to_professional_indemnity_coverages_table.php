<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPolicyWordingPathToProfessionalIndemnityCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('professional_indemnity_coverages', function (Blueprint $table) {
            $table->string('policy_wording_path')->nullable()->after('notes');
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
            $table->dropColumn('policy_wording_path');
        });
    }
}
