<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Idempotency log for kyc:expiry-reminders so two runs on the
     * same day don't re-blast the same customer.
     */
    public function up(): void
    {
        Schema::create('kyc_reminder_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('document_kind', 16); // omang | passport | license
            $table->string('cadence', 16);       // d30 / d7 / d1 / d0 / escalated
            $table->timestamp('sent_at');
            $table->timestamp('created_at');

            $table->index(['customer_id', 'document_kind', 'cadence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_reminder_log');
    }
};
