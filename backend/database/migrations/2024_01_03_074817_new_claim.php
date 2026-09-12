<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NewClaim extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('new_claims', function (Blueprint $table) {
            $table->id();
            $table->string('policy_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->string('claim_number')->nullable();
            $table->string('location_id')->nullable(); // Assuming Location is a foreign key
            $table->boolean('is_motor_claim');
            $table->string('vehicle_plate')->nullable();
            $table->boolean('co_attorney_involved');
            $table->boolean('attorney_involved');
            $table->string('claim_reported_by');
            $table->integer('claim_type');
            $table->integer('claim_sub_type_id');    //new table for claim type
            $table->integer('type_of_loss');
            $table->date('date_of_loss');
            $table->integer('service_representative_id')->nullable();// Assuming it's a foreign key
            $table->integer('reportedByBrokerAgent')->nullable();
            $table->boolean('catastrophe_loss');
            $table->string('event_name')->nullable(); // Assuming it's a foreign key
            $table->integer('primary_attorney_assigned_id')->nullable(); // Assuming it's a foreign key
            $table->date('p_a_assigned_date')->nullable();
            $table->integer('co_attorney_assigned_id')->nullable(); // Assuming it's a foreign key
            $table->date('c_a_assigned_date')->nullable();
            $table->boolean('dfs_complaint');
            $table->string('claim_allocated_to')->nullable(); // Assuming it's a foreign key
            $table->date('claims_allocated_on')->nullable();
            $table->text('note')->nullable();
            $table->string('reason')->nullable();
            $table->string('document')->nullable();
            $table->integer('is_salvage_yard')->nullable();
            $table->integer('salvage_yard_id')->nullable();
            $table->string('notice_of_demand')->nullable();
            $table->string('final_demand_letter')->nullable();
            $table->string('debt_acknowledgment')->nullable();
            $table->string('kyc_form')->nullable();
            $table->integer('paid_amount')->nullable();
            $table->integer('reserve_amount')->nullable();
            $table->date('date_first_visited')->nullable();
            $table->integer('driver_as_insured')->nullable();
            $table->integer('third_party_insured_elsewhere')->nullable();
            $table->string('tp_insured_elsewhere_email')->nullable();

            $table->string('claim_approved');
            $table->string('status');
            $table->string('claim_sub_status')->nullable();
            $table->string('created_by')->nullable();

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
        Schema::dropIfExists('new_claims');
    }
}
