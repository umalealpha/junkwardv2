<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAllRiskAndElectronicEquipmentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('all_risk_and_electronic_equipment', function (Blueprint $table) {
            $table->id();
            $table->string('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->integer('claim_sub_type_id')->nullable();
            $table->integer('property_stolen_damaged')->nullable();
            $table->longText('circumstances_loss_damage')->nullable();
            $table->integer('thorough_search_made_for_article');
            $table->integer('loss_cause')->nullable();
            $table->longText('loss_by_other_cause');
            $table->longText('stolenfromcar_unlockedpremises');
            $table->text('sole_owner_of_property');
            $table->integer('is_sole_owner_of_property');
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
        Schema::dropIfExists('all_risk_and_electronic_equipment');
    }
}
