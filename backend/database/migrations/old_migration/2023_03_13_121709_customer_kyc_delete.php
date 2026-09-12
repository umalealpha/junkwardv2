<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CustomerKycDelete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_kyc_delete', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id')->nullable();
            $table->text('passport')->nullable();
            $table->text('driving_license')->nullable();
            $table->text('omang')->nullable();
            $table->text('omangBack')->nullable();
            $table->text('proof_residence')->nullable();
            $table->text('proof_income')->nullable();
            $table->text('added_by')->nullable();
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

        Schema::dropIfExists('customer_kyc_delete');
    }
}
