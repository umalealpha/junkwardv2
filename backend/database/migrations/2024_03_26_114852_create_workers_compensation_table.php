<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkersCompensationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workers_compensation', function (Blueprint $table) {
            $table->id();
            $table->integer('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->string('employer_name')->nullable();
            $table->string('employer_policy_no')->nullable();
            $table->text('employer_Address')->nullable();
            $table->string('employer_phone_no')->nullable();
            $table->string('employer_trade_or_business')->nullable();
            $table->string('injured_name')->nullable();
            $table->string('injured_age')->nullable();
            $table->text('injured_address')->nullable();
            $table->string('injured_status')->nullable();
            $table->string('injured_occupation')->nullable();
            $table->string('injured_nationality')->nullable();
            $table->string('injured_service_period')->nullable();
            $table->string('your_direct_employ')->nullable();
            $table->text('address_of_contractor')->nullable();
            $table->text('time_of_accident')->nullable();
            $table->date('date')->nullable();
            $table->string('time')->nullable();
            $table->string('place')->nullable();
            $table->string('injured_person_ceased_work')->nullable();
            $table->string('how_accident_occur')->nullable();
            $table->string('first_report_accident')->nullable();
            $table->string('machine_state_part_causing')->nullable();
            $table->string('state_names_witness')->nullable();
            $table->string('state_nature_injuries')->nullable();
            $table->string('influence_drugs_or_drink')->nullable();
            $table->string('please_explain')->nullable();
            $table->string('anyone_negligence')->nullable();
            $table->string('give_particulars')->nullable();
            $table->string('perform_any_part_duties')->nullable();
            $table->string('period_of_disablement')->nullable();
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
        Schema::dropIfExists('workers_compensation');
    }
}
