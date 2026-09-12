<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDropCoverageValuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('has_risk_address', 'has_company')){
            Schema::table('tb_cvgpccoverages', function (Blueprint $table) {
                $table->dropColumn('has_risk_address');
                $table->dropColumn('has_company');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tb_cvgpccoverages', function($table) {
            $table->string('has_risk_address');
            $table->string('has_company');
        });
    }
}
