<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPolicyIdInTbcvgpcoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tb_cvgpccoverages', function (Blueprint $table) {
            $table->integer('policy_id')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tb_cvgpccoverages', function (Blueprint $table) {
            $table->dropColumn('policy_id')->nullable();
        });
    }
}
