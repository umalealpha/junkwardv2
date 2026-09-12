<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyCoverageEntitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('policy_coverage_entities')) {
            Schema::create('policy_coverage_entities', function (Blueprint $table) {
                $table->id();
                $table->integer('policy_id');
                $table->bigInteger('risk_address_id');
                $table->integer('term_id');
                $table->integer('action_id');
                $table->integer('coverage_id');
                $table->enum('entity_type',['Vehicle','Device','Member']);
                $table->bigInteger('entity_id');
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
        Schema::dropIfExists('policy_coverage_entities');
    }
}
