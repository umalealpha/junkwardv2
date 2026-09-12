<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ArchivedPolicyCellphone extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('archived_policy_cellphone', function (Blueprint $table) {
            $table->integer('id')->nullable(false);
            $table->string('policy_id')->nullable(true);
            $table->string('customer_id')->nullable(true);
            $table->string('device_type')->nullable(true);

            $table->string('imei')->nullable(true);
            $table->string('phone_value')->nullable(true);
            $table->string('cell_phone_make')->nullable(true);
            $table->string('cell_phone_model')->nullable(true);

            $table->string('cell_phone_front')->nullable(true);
            $table->string('cell_phone_back')->nullable(true);
            $table->string('cell_phone_left')->nullable(true);
            $table->string('cell_phone_right')->nullable(true);

            $table->string('cell_phone_top')->nullable(true);
            $table->string('cell_phone_bottom')->nullable(true);
            $table->string('status')->nullable(true);
            $table->string('reason')->nullable(true);

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
        Schema::dropIfExists('archived_policy_cellphone');
    }
}
