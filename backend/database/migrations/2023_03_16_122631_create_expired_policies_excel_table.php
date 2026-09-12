<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExpiredPoliciesExcelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('expired_policies_excel')) {
            Schema::create('expired_policies_excel', function (Blueprint $table) {
                $table->id();
                $table->integer('policy_id')->nullable();
                $table->integer('customer_id')->nullable();
                $table->string('policyNumber')->nullable();
                $table->string('email')->nullable();
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
        Schema::dropIfExists('expired_policies_excel');
    }
}
