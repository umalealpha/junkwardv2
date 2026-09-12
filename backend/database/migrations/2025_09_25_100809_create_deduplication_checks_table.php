<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeduplicationChecksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('deduplication_checks', function (Blueprint $table) {
            $table->id();
            
            // Customer Information
            $table->integer('customer_id');
            $table->foreign('customer_id')->references('id')->on('customer')->onDelete('cascade');
            
            // Unique Customer ID Generation
            $table->string('unique_customer_id')->unique();
            
            // Document Information for Deduplication
            $table->string('omang_number')->nullable()->index();
            $table->string('passport_number')->nullable()->index();
            $table->string('bank_account_number')->nullable()->index();
            
            // Contact Information
            $table->string('cellphone')->nullable()->index();
            $table->string('email')->nullable()->index();
            
            // Bank Statement Upload
            $table->string('bank_statement_upload_url')->nullable();
            $table->string('bank_statement_file_path')->nullable();
            $table->string('bank_statement_file_name')->nullable();
            $table->string('bank_statement_mime_type')->nullable();
            $table->bigInteger('bank_statement_file_size')->nullable();
            $table->string('bank_statement_file_hash')->nullable();
            
            // OTP Verification
            $table->string('otp_code', 6)->nullable();
            $table->timestamp('otp_sent_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->integer('otp_attempts')->default(0);
            $table->timestamp('otp_expires_at')->nullable();
            
            // Link Access and Tracking
            $table->string('unique_access_token')->unique();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('link_opened_at')->nullable();
            $table->timestamp('link_expires_at')->nullable();
            
            // Document Status and Verification
            $table->enum('document_upload_status', ['pending', 'uploaded', 'processing', 'verified', 'rejected', 'failed'])->default('pending');
            $table->enum('manual_verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('verification_notes')->nullable();
            $table->integer('verified_by')->nullable();
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            
            // Policy Suspension Logic
            $table->integer('policy_id')->nullable();
            $table->foreign('policy_id')->references('id')->on('policy')->onDelete('cascade');
            $table->timestamp('policy_created_at')->nullable();
            $table->timestamp('suspension_due_date')->nullable(); // 90 days from policy creation
            $table->boolean('is_suspended')->default(false);
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            
            // Overall Status
            $table->enum('status', ['active', 'suspended', 'completed', 'expired', 'cancelled'])->default('active');
            
            // Additional tracking
            $table->json('access_logs')->nullable(); // Store multiple access attempts
            $table->json('device_info')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['customer_id', 'status']);
            $table->index(['omang_number', 'passport_number']);
            $table->index(['bank_account_number']);
            $table->index(['cellphone']);
            $table->index(['email']);
            $table->index(['policy_id', 'is_suspended']);
            $table->index(['suspension_due_date']);
            $table->index(['unique_access_token']);
            $table->index(['otp_code', 'otp_expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('deduplication_checks');
    }
}
