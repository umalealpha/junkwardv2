<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RecreatePolicySpecifiedItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('policy_specified_items')) {
            Schema::create('policy_specified_items', function (Blueprint $table) {
                $table->id();
                $table->integer('policy_id');
                $table->integer('term_id')->nullable();
                $table->bigInteger('coverage_id')->nullable();
                $table->bigInteger('specified_coverage_id')->nullable();
                $table->decimal('sum_insured',10,4);
                $table->decimal('rate',10,4);
                $table->decimal('calculated_value',10,4);
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
        Schema::dropIfExists('policy_specified_items');
    }
}
