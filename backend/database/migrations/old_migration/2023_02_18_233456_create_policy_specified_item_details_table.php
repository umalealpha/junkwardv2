<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicySpecifiedItemDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policy_specified_item_details', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('policy_specified_item_id');
            $table->integer('specified_coverage_id');
            $table->string('sum_insured');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('policy_specified_item_details');
    }
}
