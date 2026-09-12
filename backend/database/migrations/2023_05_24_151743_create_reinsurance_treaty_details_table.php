<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReinsuranceTreatyDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('reinsurance_treaty_details')) {
            Schema::create('reinsurance_treaty_details', function (Blueprint $table) {
                $table->id();
                $table->integer('treaty_id')->nullable();
                $table->string('formula_attached')->nullable();
                $table->timestamps();
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
        // Schema::dropIfExists('reinsurance_treaty_details');
        Schema::table('reinsurance_treaty_details', function (Blueprint $table) {
            $table->dropColumn('treaty_id');
            $table->dropColumn('formula_attached');
        });
    }
}
