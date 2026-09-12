<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRekycLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rekyc_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rekyc_link_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('level', 20)->default('info'); // info, warning, error, debug
            $table->string('action', 100); // email_sent, sms_sent, whatsapp_sent, link_created, link_opened, link_completed, etc.
            $table->text('message');
            $table->json('context')->nullable(); // Additional context data
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('delivery_method')->nullable(); // email, sms, whatsapp, all_channels
            $table->string('status')->nullable(); // sent, failed, pending, completed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['rekyc_link_id']);
            $table->index(['customer_id']);
            $table->index(['campaign_id']);
            $table->index(['level']);
            $table->index(['action']);
            $table->index(['created_at']);
            $table->index(['status']);
            
            // Foreign key constraints - commented out for now due to type mismatch
            // $table->foreign('rekyc_link_id')->references('id')->on('rekyc_links')->onDelete('cascade');
            // $table->foreign('customer_id')->references('id')->on('customer')->onDelete('cascade');
            // $table->foreign('campaign_id')->references('id')->on('rekyc_campaigns')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rekyc_logs');
    }
}
