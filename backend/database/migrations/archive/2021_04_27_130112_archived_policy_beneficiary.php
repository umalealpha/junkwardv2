<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ArchivedPolicyBeneficiary extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('archived_policy_beneficiary', function (Blueprint $table) {
            $table->integer('id')->nullable(false);
            $table->string('policy_id')->nullable(true);
            $table->string('relation')->nullable(true);
            $table->string('first_name')->nullable(true);

            $table->string('middle_name')->nullable(true);
            $table->string('last_name')->nullable(true);
            $table->string('dob')->nullable(true);
            $table->string('gender')->nullable(true);

            $table->string('payment')->nullable(true);
            $table->string('omang')->nullable(true);
            $table->string('passport')->nullable(true);
            $table->string('created_at')->nullable(true);
            $table->string('updated_at')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('archived_policy_beneficiary');
    }
}
