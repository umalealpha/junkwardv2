<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReinsuranceFormulaDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('reinsurance_formula_details')) {
            Schema::create('reinsurance_formula_details', function (Blueprint $table) {
                $table->id();
                $table->integer('formula_id')->nullable();
                $table->integer('group_id')->nullable();
                $table->string('vehicle_type')->nullable();
                $table->string('operator')->nullable();
                $table->string('si_allocation')->nullable();
                $table->string('n_ValueLimitsBetween')->nullable();
                $table->string('percentage')->nullable();
                $table->date('date_from')->nullable();
                $table->date('date_to')->nullable();
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
        Schema::dropIfExists('reinsurance_formula_details');
    }
}
