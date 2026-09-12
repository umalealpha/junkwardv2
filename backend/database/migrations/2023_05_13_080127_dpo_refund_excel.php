<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DpoRefundExcel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('dpo_refund_excel')) {
            Schema::create('dpo_refund_excel', function (Blueprint $table) {
                $table->id();
                $table->integer('policy_id')->nullable();
                $table->text('dpo_ref')->nullable();
                $table->string('action')->nullable();
                $table->string('added_by')->nullable();
                $table->string('status')->nullable();
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
        Schema::dropIfExists('dpo_refund_excel');
    }
}
