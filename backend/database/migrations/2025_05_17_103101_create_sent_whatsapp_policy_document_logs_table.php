<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSentWhatsappPolicyDocumentLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sent_whatsapp_policy_document_logs', function (Blueprint $table) {
                $table->id();
                $table->string('policy_number');
                $table->string('phone_number');
                $table->string('sent_by')->nullable();
                $table->string('doc')->nullable();
                $table->text('documents')->nullable();
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
        Schema::dropIfExists('sent_whatsapp_policy_document_logs');
    }
}
