<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTbCvgpccoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tb_cvgpccoverages', function (Blueprint $table) {
            // if (!Schema::hasColumn('tb_cvgpccoverages', 'has_risk_address')) {
            //     $table->boolean('has_risk_address')->after('n_EditVersion')->nullable();
            // }
            if (!Schema::hasColumn('tb_cvgpccoverages', 'has_vehicle')) {
                $table->boolean('has_vehicle')->nullable();
            }
            if (!Schema::hasColumn('tb_cvgpccoverages', 'has_member')) {
                $table->boolean('has_member')->nullable();
            }
            if (!Schema::hasColumn('tb_cvgpccoverages', 'has_device')) {
                $table->boolean('has_device')->nullable();
            }
            if (!Schema::hasColumn('tb_cvgpccoverages', 'created_at')||!Schema::hasColumn('tb_cvgpccoverages', 'updated_at')) {
                $table->timestamps();
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
        // Schema::dropIfExists('tb_cvgpccoverages');
        Schema::table('tb_cvgpccoverages', function (Blueprint $table) {
            // $table->dropColumn('has_risk_address');
            $table->dropColumn('has_vehicle');
            $table->dropColumn('has_member');
            $table->dropColumn('has_device');
        });
    }
}
