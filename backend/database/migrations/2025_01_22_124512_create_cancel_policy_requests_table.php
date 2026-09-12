<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCancelPolicyRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cancel_policy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('policyNumber');
            $table->text('reason')->nullable();
            $table->text('circumstances')->nullable();
            $table->string('other_company')->nullable();
            $table->string('status')->nullable();
            $table->integer('action_by')->nullable();
            $table->text('requestdata')->nullable();
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
        Schema::dropIfExists('cancel_policy_requests');
    }
}
