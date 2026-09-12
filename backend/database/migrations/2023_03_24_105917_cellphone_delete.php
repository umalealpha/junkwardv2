<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CellphoneDelete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('cellphone_delete')) {
            Schema::create('cellphone_delete', function (Blueprint $table) {
                $table->id();
                $table->integer('policy_id')->nullable();
                $table->integer('customer_id')->nullable();
                $table->integer('policy_cellphone_id')->nullable();
                $table->text('cell_phone_front')->nullable();
                $table->text('cell_phone_back')->nullable();
                $table->text('cell_phone_left')->nullable();
                $table->text('cell_phone_right')->nullable();
                $table->text('cell_phone_top')->nullable();
                $table->text('cell_phone_bottom')->nullable();

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cellphone_delete');
    }
}
