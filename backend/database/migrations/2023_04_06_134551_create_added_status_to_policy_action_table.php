<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateAddedStatusToPolicyActionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE `policy_actions` CHANGE `status` `status` ENUM('QUOTE','ISSUED','IN_APPROVAL','APPROVED','REJECTED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'QUOTE'; ");
        // Schema::table('policy_actions', function (Blueprint $table) {
        //     $table->enum('status',['QUOTE','ISSUED','IN_APPROVAL','APPROVED','REJECTED'])->change();
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `policy_actions` CHANGE `status` `status` ENUM('QUOTE','ISSUED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'QUOTE'; ");

        // Schema::table('policy_actions', function (Blueprint $table) {
        //     $table->enum('status',['QUOTE','ISSUED','IN_APPROVAL','APPROVED','REJECTED'])->change();
        // });
    }
}
