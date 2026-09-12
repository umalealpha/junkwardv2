<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRekycCampaignsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rekyc_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->json('target_criteria')->nullable(); // Customer selection criteria
            $table->json('notification_settings')->nullable(); // WhatsApp/Email settings
            $table->json('kyc_fields')->nullable(); // Fields to be verified/updated
            $table->boolean('ocr_enabled')->default(false);
            $table->boolean('fraud_detection_enabled')->default(false);
            $table->integer('link_expiry_hours')->default(72); // Link validity period
            $table->integer('otp_expiry_minutes')->default(10); // OTP validity period
            $table->integer('max_attempts')->default(3); // Max OTP attempts
            $table->integer('escalation_days')->default(7); // Days before escalation
            $table->json('reminder_days')->nullable(); // Days for reminders [3, 7, 14]
            $table->json('settings')->nullable(); // Additional campaign settings
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'start_date']);
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rekyc_campaigns');
    }
}
