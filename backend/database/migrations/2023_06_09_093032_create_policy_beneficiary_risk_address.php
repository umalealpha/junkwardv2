<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyBeneficiaryRiskAddress extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_beneficiary', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_beneficiary', 'risk_id')) {
                $table->string('risk_id')->after('action_id')->nullable();
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
        Schema::table('policy_beneficiary', function (Blueprint $table) {
            $table->dropColumn('risk_id');
        });
    }
}
