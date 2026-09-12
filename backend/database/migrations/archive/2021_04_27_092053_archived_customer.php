<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ArchivedCustomer extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('archived_customer', function (Blueprint $table) {
            $table->integer('id')->nullable(true);
            $table->string('firstName')->nullable(true);
            $table->string('middleName')->nullable(true);
            $table->string('lastName')->nullable(true);
            $table->string('email')->nullable(true);
            $table->string('cellphone')->nullable(true);
            $table->string('password')->nullable(true);
            $table->string('email_verified_at')->nullable(true);
            $table->timestamp('created_at')->nullable(true);;
            $table->timestamp('updated_at')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('archived_customer');
    }
}
