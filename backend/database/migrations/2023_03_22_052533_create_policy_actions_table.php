<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyActionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('policy_actions')) {
            Schema::create('policy_actions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('policy_id');
                $table->unsignedInteger('term_id')->nullable();
                $table->double('premium',8,2)->nullable();
                $table->enum('status',['activated','completed'])->default('activated')->comment('once the status is completed then there is no option for add/edit record');
                $table->unsignedInteger('created_by');
                $table->unsignedInteger('updated_by')->nullable();
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
        Schema::dropIfExists('policy_actions');
    }
}
