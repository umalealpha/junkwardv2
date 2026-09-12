<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * customer_email_history — audit log of every email change on customer rows.
 * Populated by a model observer in CustomerObserver. Used to detect the
 * "swapped email" pattern that was causing wrong customers to be debited
 * via DPO's email-based card lookup.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('customer_email_history')) return;

        Schema::create('customer_email_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('customer_id');
            $table->string('old_email', 190)->nullable();
            $table->string('new_email', 190)->nullable();
            $table->unsignedInteger('changed_by_user_id')->nullable()
                  ->comment('users.id who triggered the change (null = system/cli)');
            $table->string('source', 40)->nullable()
                  ->comment('web | api | import | cli | system');
            $table->string('reason', 400)->nullable()
                  ->comment('Agent-provided reason if required by UI');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 400)->nullable();

            // OTP verification markers — populated when a verification flow is used
            $table->boolean('otp_verified')->default(false);
            $table->string('otp_channel', 16)->nullable()
                  ->comment('sms | email — used for the verification');
            $table->timestamp('otp_verified_at')->nullable();

            $table->timestamps();

            $table->index('customer_id', 'idx_email_history_customer');
            $table->index('new_email', 'idx_email_history_new_email');
            $table->index('created_at', 'idx_email_history_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_email_history');
    }
};
