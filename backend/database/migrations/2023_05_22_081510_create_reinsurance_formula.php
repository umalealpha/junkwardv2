<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReinsuranceFormula extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('reinsurance_formula')) {
            Schema::create('reinsurance_formula', function (Blueprint $table) {
                $table->id();
                $table->integer('product_id')->nullable();
                $table->integer('type_id')->nullable();
                $table->integer('reinsurance_type_id')->nullable();
                $table->string('formula_code')->nullable();
                $table->string('formula_name')->nullable();
                $table->string('s_FormulaType')->nullable();
                $table->integer('status')->nullable();
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
        Schema::dropIfExists('reinsurance_formula');
    }
}
