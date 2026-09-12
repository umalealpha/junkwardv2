<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeEntityTypePolicyCoverageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE `policy_coverage` CHANGE `entity_type` `entity_type` ENUM('Vehicle','Risk Address','Devices','Members','Company') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NULL DEFAULT NULL;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        DB::statement("ALTER TABLE `policy_coverage` CHANGE `entity_type` `entity_type` ENUM('Vehicle','Risk Address','Devices','Members') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NULL DEFAULT NULL;");
    }
}
