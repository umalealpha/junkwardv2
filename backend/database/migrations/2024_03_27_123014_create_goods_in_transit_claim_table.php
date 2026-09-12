<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGoodsInTransitClaimTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('goods_in_transit_claim', function (Blueprint $table) {
            $table->id();
            $table->string('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->integer('claim_sub_type_id')->nullable();
            $table->string('address_of_premises_loss')->nullable();
            $table->string('details_of_driver')->nullable();
            $table->string('property_last_seen')->nullable();
            $table->text('date_time_of_loss')->nullable();
            $table->longText('brief_description_incident')->nullable();
            $table->text('date_time_police_advised')->nullable();
            $table->string('police_station_name')->nullable();
            $table->string('witnesses_name')->nullable();
            $table->string('witnesses_mobile_number')->nullable();
            $table->string('total_value_of_loss')->nullable();
            $table->string('consignment_transported_to')->nullable();
            $table->string('consignment_from')->nullable();
            $table->string('vehicle_registration_number')->nullable();
            $table->integer('is_carrier_contracted')->nullable();
            $table->longText('copy_of_contract')->nullable();
            $table->integer('carrier_has_own_GIT_ins')->nullable();
            $table->integer('other_insurance_against_theft')->nullable();
            $table->longText('insurance_against_theft_details')->nullable();
            $table->longText('details_of_previous_loss_records')->nullable();
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
        Schema::dropIfExists('goods_in_transit_claim');
    }
}
