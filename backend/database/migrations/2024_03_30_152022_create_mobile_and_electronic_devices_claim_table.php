<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMobileAndElectronicDevicesClaimTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mobile_and_electronic_devices_claim', function (Blueprint $table) {
            $table->id();
            $table->string('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->integer('claim_sub_type_id')->nullable();
            $table->string('insured_name')->nullable();
            $table->string('email_address')->nullable();
            $table->string('address')->nullable();
            $table->string('telephone_no')->nullable();
            $table->integer('property_stolen_damaged')->nullable();
            $table->string('premises_address')->nullable();
            $table->string('date_time_loss_discovered')->nullable();
            $table->string('whom_discovered')->nullable();
            $table->string('articles_last_seen')->nullable();
            $table->string('whom_last_seen_and_where')->nullable();
            $table->string('when_police_notified')->nullable();
            $table->string('police_station_name')->nullable();
            $table->string('officer_name')->nullable();
            $table->string('report_number')->nullable();
            $table->longText('circumstances_loss_damage')->nullable();
            $table->integer('thorough_search_made_for_article');
            $table->integer('loss_cause')->nullable();
            $table->longText('loss_by_other_cause');
            $table->integer('property_insured_against');
            $table->integer('preinspection_images_uploaded');
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
        Schema::dropIfExists('mobile_and_electronic_devices_claim');
    }
}
