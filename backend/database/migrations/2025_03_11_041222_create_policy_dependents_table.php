<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyDependentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policy_dependents', function (Blueprint $table) {
            $table->id();
            $table->integer('policy_id');
            $table->boolean('is_self')->default(0);
            $table->boolean('is_dependent')->default(0);
            $table->integer('relation');
            $table->string('first_name',255);
            $table->string('middle_name',255)->nullable();
            $table->string('last_name',255);
            $table->date('dob')->nullable();
            $table->tinyInteger('gender')->nullable();
            $table->string('email',255)->nullable();
            $table->string('omang',10)->nullable();
            $table->string('passport',50)->nullable();
            $table->decimal('premium_without_vat',11,2)->nullable();
            $table->decimal('vat_amount',11,2)->nullable();
            $table->decimal('vat_percent',11,2)->nullable();
            $table->softDeletes();
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
        Schema::dropIfExists('policy_dependents');
    }
}
