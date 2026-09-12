<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OTP e-signature records for the No Claims Declaration.
 *
 * One row per OTP issued. The code is stored only as a salted SHA-256 hash;
 * `signed_at` + `attachment_id` are set when the customer's OTP is verified
 * and the signed PDF has been generated. Rows are append-only evidence: they
 * are never deleted when the declaration is replaced.
 */
class CreatePolicyNcdOtpSignaturesTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('policy_ncd_otp_signatures')) {
            Schema::create('policy_ncd_otp_signatures', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('policy_id');
                $table->unsignedBigInteger('customer_id')->nullable();
                // E.164 digits without '+', e.g. 26771234567. Kept on the row so
                // the signed PDF states the exact number the OTP went to even if
                // the customer record changes later.
                $table->string('cellphone', 20);
                $table->char('code_hash', 64);
                $table->timestamp('expires_at');
                $table->unsignedTinyInteger('attempts')->default(0);
                // Set when the OTP is spent: verified, locked out or superseded.
                $table->timestamp('consumed_at')->nullable();
                // Set only on a successful verification.
                $table->timestamp('signed_at')->nullable();
                $table->unsignedBigInteger('attachment_id')->nullable();
                $table->string('pdf_path', 500)->nullable();
                // Staff member who requested the OTP on the customer's behalf.
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->string('request_ip', 45)->nullable();
                $table->string('sms_status', 30)->nullable();
                $table->timestamps();

                $table->index(['policy_id', 'id'], 'pnos_policy_idx');
                $table->index(['policy_id', 'consumed_at'], 'pnos_open_idx');
                $table->index('attachment_id', 'pnos_attachment_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_ncd_otp_signatures');
    }
}
