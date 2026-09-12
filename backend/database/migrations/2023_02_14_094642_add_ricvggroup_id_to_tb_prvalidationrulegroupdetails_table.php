<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRicvggroupIdToTbPrvalidationrulegroupdetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tb_prvalidationrulegroupdetails', function (Blueprint $table) {
            if (!Schema::hasColumn('tb_prvalidationrulegroupdetails', 'ricvggroup_id')) {
                $table->string('ricvggroup_id')->after('n_PrValidationRuleGroupMasters_FK')->nullable();
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
        Schema::table('tb_prvalidationrulegroupdetails', function (Blueprint $table) {
            $table->dropColumn('ricvggroup_id');
        });
    }
}
