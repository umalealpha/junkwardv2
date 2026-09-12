<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClaimComplaintLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('claim_complaint_log', function (Blueprint $table) {
            $table->id();
            $table->integer('policy_id')->nullable();
            $table->integer('claim_id')->nullable();
            $table->string('complaint_of')->nullable();
            $table->longText('complaint_details')->nullable();
            $table->integer('added_by')->nullable();
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
        Schema::dropIfExists('claim_complaint_log');
    }
}
