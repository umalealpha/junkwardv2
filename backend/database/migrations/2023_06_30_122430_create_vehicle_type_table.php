<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vehicle', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicle', 'vehicle_type')) {
                $table->string('vehicle_type')->after('risk_id')->nullable();
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
        // Schema::dropIfExists('vehicle_type');
        Schema::table('vehicle', function (Blueprint $table) {
            $table->dropColumn('vehicle_type');
        });
    }
}
