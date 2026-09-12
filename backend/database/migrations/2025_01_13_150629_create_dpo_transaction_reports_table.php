<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDpoTransactionReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dpo_transaction_reports', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('cellphone')->nullable();
            $table->string('status')->nullable();
            $table->decimal('premium', 10, 2)->nullable();
            $table->string('email')->nullable();
            $table->integer('retry_count')->nullable();
            $table->text('reason')->nullable();
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
        Schema::dropIfExists('dpo_transaction_reports');
    }
}
