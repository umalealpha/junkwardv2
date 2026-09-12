<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRiskAddressSubcompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('risk_address', function (Blueprint $table) {
            if (!Schema::hasColumn('risk_address', 'company_id')) {
                $table->string('company_id')->after('policy_id')->nullable()->comment('This is subcompany id');
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
        Schema::table('risk_address', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }
}
