<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHasCompanyToTbCvgpccoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tb_cvgpccoverages', function (Blueprint $table) {
            if (!Schema::hasColumn('tb_cvgpccoverages', 'has_company')) {
                $table->boolean('has_company')->after('has_device')->nullable();
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
            $table->dropColumn('has_company');
        });
    }
}
