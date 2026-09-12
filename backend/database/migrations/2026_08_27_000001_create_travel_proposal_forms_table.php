<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Digital Travel Insurance Proposal Form — the electronic replacement for the
 * signed paper form ("Travel Insurance Proposal Form", MAPFRE partnership,
 * rev. 2022) that the customer used to sign in ink.
 *
 * One row per proposal. The row IS the evidence: it holds the declared answers
 * exactly as the customer submitted them, the declaration they accepted, and
 * the OTP verification that stands in for the Insured's Signature block —
 * ECTA 2014 s.17 treats that verified one-time code as the customer's
 * electronic signature, so the moment of signing has to be provable:
 *
 *   otp_id / otp_cellphone   which code, sent to which number
 *   otp_sent_at              when it went out
 *   otp_verified_at          the SIGNING moment
 *   otp_channel / attempts   how it was delivered and how many tries it took
 *   evidence_hash            tamper-evident digest over all of the above
 *
 * Lives on the V2 ops DB (mysql_system) beside public_otps and
 * mapfre_quote_submissions — the OTP row it references is there, and this is
 * audit data subject to the audit-log retention policy.
 *
 * DPA 2024: the medical answers are special-category data. They are written
 * here and rendered into the generated PDF only; they are never logged, never
 * echoed to a third party, and never sent upstream to MAPFRE (whose /contract
 * call takes no health fields).
 *
 * Guarded per the self-healing migration standard — safe to re-run.
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('mysql_system');

        if (!$schema->hasTable('travel_proposal_forms')) {
            $schema->create('travel_proposal_forms', function (Blueprint $table) {
                $table->id();

                // Our own proposal reference — quoted to the customer and
                // printed on the generated form. Unique so a resubmission
                // updates the draft rather than creating a second proposal.
                $table->string('reference', 40)->unique();
                $table->string('agent_id', 16)->nullable()->index();
                $table->enum('status', ['draft', 'signed'])->default('draft')->index();

                // ─ Particulars of proposer (page 1 of the paper form) ─
                $table->string('surname', 60);
                $table->string('first_names', 120);
                $table->date('dob');
                $table->string('passport_no', 40)->nullable();
                $table->string('occupation', 80)->nullable();
                $table->string('address', 250)->nullable();
                $table->string('email', 160);
                $table->string('mobile', 24)->index();

                // ─ Trip ─
                $table->date('departure_date');
                $table->date('return_date');
                $table->string('destination', 120);
                $table->string('trip_type', 60)->nullable();

                // ─ Next of kin ─
                $table->string('next_of_kin_name', 120)->nullable();
                $table->string('next_of_kin_phone', 24)->nullable();
                $table->string('next_of_kin_relationship', 60)->nullable();

                // ─ Other persons travelling: [{full_name, dob, passport_no,
                //   relationship}] — the paper form's 5-row table, unbounded
                //   up to the journey's MAX_TRAVELLERS.
                $table->json('travellers')->nullable();

                // ─ Health questions 1-4: { accidents: {answer, details}, … }
                $table->json('medical_answers')->nullable();
                // True when ANY question was answered Yes — the flag
                // underwriting filters on without opening the JSON.
                $table->boolean('has_medical_disclosure')->default(false)->index();

                // ─ Declaration (page 2 of the paper form) ─
                $table->boolean('declaration_accepted')->default(false);
                $table->string('declaration_version', 16)->nullable();
                $table->timestamp('declaration_accepted_at')->nullable();

                // ─ OTP-as-signature evidence ─
                $table->unsignedBigInteger('otp_id')->nullable()->index();
                $table->string('otp_cellphone', 24)->nullable();
                $table->timestamp('otp_sent_at')->nullable();
                $table->timestamp('otp_verified_at')->nullable();
                $table->string('otp_channel', 16)->nullable();
                $table->unsignedTinyInteger('otp_attempts')->nullable();
                $table->timestamp('signed_at')->nullable()->index();
                $table->string('signature_method', 16)->nullable();
                $table->string('evidence_hash', 64)->nullable();

                // ─ Linkage to the quote / policy this proposal supports ─
                $table->string('quote_id', 64)->nullable()->index();
                $table->string('mapfre_reference', 64)->nullable();
                $table->string('contract_number', 64)->nullable()->index();
                $table->unsignedBigInteger('policy_id')->nullable()->index();
                $table->string('product_code', 64)->nullable();
                $table->string('product_name', 120)->nullable();
                $table->decimal('premium', 12, 2)->nullable();
                $table->string('currency', 8)->nullable();

                // ─ Generated document (StorageService: s3 with local fallback) ─
                $table->string('document_path', 255)->nullable();
                $table->string('document_disk', 16)->nullable();
                $table->string('document_url', 512)->nullable();
                $table->timestamp('document_generated_at')->nullable();

                // ─ Forensics ─
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_system')->dropIfExists('travel_proposal_forms');
    }
};
