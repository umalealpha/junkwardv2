<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyLossDamageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_loss_damage', function (Blueprint $table) {
            $table->id();
            $table->integer('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->string('broker_agent')->nullable();
            $table->string('policy_no')->nullable();
            $table->string('id_number')->nullable();
            $table->text('name_occupation')->nullable();
            $table->text('address_tele_no')->nullable();
            $table->text('date_time_of_loss_damage')->nullable();
            $table->text('loss_damage_discovered')->nullable();
            $table->text('loss_damage_occurred')->nullable();
            $table->text('premises_occupied')->nullable();
            $table->text('last_occupied')->nullable();
            $table->text('purpose_of_occupation')->nullable();
            $table->text('nature_interruption')->nullable();
            $table->text('loss_for_each_item')->nullable();
            $table->text('previously_suffered_loss')->nullable();
            $table->text('give_details')->nullable();
            $table->text('name_of_insurer')->nullable();
            $table->text('reference_no_station')->nullable();
            $table->text('interest_insured_property')->nullable();
            $table->text('other_insurance_covering')->nullable();
            $table->text('give_name_insurer')->nullable();
            $table->text('value_all_property')->nullable();
            $table->text('when_last_valued')->nullable();
            $table->string('name_of_bank')->nullable();
            $table->string('branch')->nullable();
            $table->string('name_of_account')->nullable();
            $table->string('account_number')->nullable();
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
        Schema::dropIfExists('property_loss_damage');
    }
}
