<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FidelityGuarantee extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fidelity_guarantee', function (Blueprint $table) {
            $table->id();
            $table->string('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->text('defaulting_employees_name')->nullable();
          //  $table->text('defaulting_employees_position')->nullable();
            $table->boolean('employees_been_involved')->nullable();
            $table->boolean('police_been_notified')->nullable();
            $table->string('name_of_police_station')->nullable();
            $table->string('date_of_notification')->nullable();
            $table->string('person_name_who_notified_police')->nullable();
            $table->text('circumstances')->nullable();
            $table->timestamps();

            // Foreign key constraints

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fidelity_guarantee');
    }
}
