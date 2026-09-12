<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notification_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('customer_id')->index();
            $table->integer('policy_id')->nullable();
            $table->enum('channel', ['sms', 'whatsapp', 'email']);
            $table->string('notification_type', 50); // payment_failed, payment_success, policy_activated, etc.
            $table->text('message');
            $table->enum('status', ['sent', 'failed'])->default('sent');
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['customer_id', 'notification_type']);
            $table->index(['channel', 'status', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_log');
    }
};
