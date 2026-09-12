<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappMessageLogTable extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_message_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->unsignedInteger('policy_id')->nullable()->index();
            $table->unsignedBigInteger('ledger_id')->nullable()->index();
            $table->string('phone_number', 20);
            $table->string('message_type', 30)->default('text')->comment('text, document, template');
            $table->string('purpose', 50)->index()->comment('invoice_delivery, notification, reminder');
            $table->text('message_body')->nullable();
            $table->string('document_url', 500)->nullable();
            $table->string('wa_message_id', 200)->nullable()->comment('wamid from Meta API response');
            $table->enum('status', ['sent', 'delivered', 'read', 'failed'])->default('sent');
            $table->unsignedSmallInteger('http_code')->nullable();
            $table->text('api_response')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_message_log');
    }
}
