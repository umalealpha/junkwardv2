<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUpdateTbCvgpccoverages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tb_cvgpccoverages', function (Blueprint $table) {
            if (!Schema::hasColumn('tb_cvgpccoverages', 'isDocDisplay')) {
                $table->string('isDocDisplay')->after('s_PermitDuplication')->default('Y');
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
        Schema::table('tb_cvgpccoverages', function (Blueprint $table) {
            $table->dropColumn('isDocDisplay');
        });
    }
}
