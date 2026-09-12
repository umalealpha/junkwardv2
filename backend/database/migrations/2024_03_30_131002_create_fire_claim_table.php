<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFireClaimTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fire_claim', function (Blueprint $table) {
            $table->id();
            $table->string('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->integer('claim_sub_type_id')->nullable();
            $table->string('address_of_theft_occurred')->nullable();
            $table->string('location_article_stolen_removed')->nullable();
            $table->string('property_last_seen')->nullable();
            $table->text('date_time_of_theft')->nullable();
            $table->text('date_time_loss_discovered')->nullable();
            $table->longText('brief_description_incident')->nullable();
            $table->text('date_time_police_advised')->nullable();
            $table->string('police_station_name')->nullable();
            $table->integer('anyone_during_burglary')->nullable();
            $table->string('details_during_burglary')->nullable();
            $table->string('days_premises_unoccupied')->nullable();
            $table->integer('premises_guarded_by_watchman')->nullable();
            $table->string('name_of_guard')->nullable();
            $table->string('telephone_of_guard')->nullable();
            $table->string('guard_during_fire')->nullable();
            $table->string('name_of_security_agent')->nullable();
            $table->longText('contract_of_agreement')->nullable();
            $table->integer('premises_properly_secured')->nullable();
            $table->integer('suspect_any_person')->nullable();
            $table->longText('suspect_person_details')->nullable();
            $table->text('total_value_premises_buildings')->nullable();
            $table->integer('other_insurance_against_fire')->nullable();
            $table->longText('insurance_against_fire_details')->nullable();
            $table->text('estimated_amount_of_damaged')->nullable();
            $table->longText('details_of_previous_loss')->nullable();
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
        Schema::dropIfExists('fire_claim');
    }
}
