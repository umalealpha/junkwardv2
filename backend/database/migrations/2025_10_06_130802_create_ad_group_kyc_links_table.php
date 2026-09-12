<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdGroupKycLinksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ad_group_kyc_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('ad_group_kyc_campaigns')->onDelete('cascade');
            $table->integer('customer_id');
            $table->foreign('customer_id')->references('id')->on('customer')->onDelete('cascade');
            $table->integer('policy_id')->nullable();
            $table->foreign('policy_id')->references('id')->on('policies')->onDelete('cascade');
            $table->string('unique_token', 64)->unique(); // Secure token for link access
            $table->string('otp_code', 6); // 6-digit OTP
            $table->enum('status', ['pending', 'sent', 'opened', 'otp_verified', 'completed', 'expired', 'failed'])->default('pending');
            $table->enum('delivery_method', ['whatsapp', 'email', 'sms'])->nullable();
            $table->string('delivery_reference')->nullable(); // Message ID or email reference
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');
            $table->integer('otp_attempts')->default(0);
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('device_info')->nullable(); // Device fingerprinting data
            $table->json('consent_data')->nullable(); // Customer consent information
            $table->timestamps();
            
            $table->index(['unique_token']);
            $table->index(['customer_id', 'campaign_id']);
            $table->index(['status', 'expires_at']);
            $table->index('otp_code');
            $table->index('policy_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_group_kyc_links');
    }
}
