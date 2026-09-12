<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the claim Complaint Log into a regulatory Complaints Register.
 *
 * Adds the fields the quarterly regulator register requires (complainant
 * identity/contact, date filed, reference, nature, handler, escalation,
 * status, rejection reason, resolution) to `claim_complaint_log`, and a
 * `complaint_id` link on `claim_attachments` so supporting correspondence
 * reuses the existing claim-document uploader.
 *
 * All new columns are nullable so existing complaint rows are untouched.
 * Idempotent (hasColumn guards) — safe to re-run.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('claim_complaint_log', function (Blueprint $t) {
            // Complainant identity + contact (regulator-mandated PII)
            if (!Schema::hasColumn('claim_complaint_log', 'complainant_name'))            $t->string('complainant_name', 200)->nullable()->after('claim_id');
            if (!Schema::hasColumn('claim_complaint_log', 'complainant_id_type'))         $t->string('complainant_id_type', 20)->nullable()->after('complainant_name'); // Omang | Passport
            if (!Schema::hasColumn('claim_complaint_log', 'complainant_omang'))           $t->string('complainant_omang', 50)->nullable()->after('complainant_id_type');
            if (!Schema::hasColumn('claim_complaint_log', 'complainant_passport'))        $t->string('complainant_passport', 50)->nullable()->after('complainant_omang');
            if (!Schema::hasColumn('claim_complaint_log', 'complainant_phone'))           $t->string('complainant_phone', 50)->nullable()->after('complainant_passport');
            if (!Schema::hasColumn('claim_complaint_log', 'complainant_email'))           $t->string('complainant_email', 150)->nullable()->after('complainant_phone');
            if (!Schema::hasColumn('claim_complaint_log', 'complainant_postal_address'))  $t->text('complainant_postal_address')->nullable()->after('complainant_email');

            // Complaint particulars
            if (!Schema::hasColumn('claim_complaint_log', 'date_filed'))                  $t->date('date_filed')->nullable()->after('complaint_details');
            if (!Schema::hasColumn('claim_complaint_log', 'reference_number'))            $t->string('reference_number', 100)->nullable()->after('date_filed');
            if (!Schema::hasColumn('claim_complaint_log', 'nature'))                      $t->string('nature', 200)->nullable()->after('reference_number');

            // Handling / lifecycle
            if (!Schema::hasColumn('claim_complaint_log', 'handler_user_id'))             $t->unsignedBigInteger('handler_user_id')->nullable()->after('nature');
            if (!Schema::hasColumn('claim_complaint_log', 'escalation_level'))            $t->string('escalation_level', 150)->nullable()->after('handler_user_id');
            if (!Schema::hasColumn('claim_complaint_log', 'status'))                      $t->string('status', 50)->nullable()->after('escalation_level');
            if (!Schema::hasColumn('claim_complaint_log', 'rejection_reason'))            $t->text('rejection_reason')->nullable()->after('status');
            if (!Schema::hasColumn('claim_complaint_log', 'resolution'))                  $t->text('resolution')->nullable()->after('rejection_reason');
            if (!Schema::hasColumn('claim_complaint_log', 'closed_at'))                   $t->date('closed_at')->nullable()->after('resolution');
            if (!Schema::hasColumn('claim_complaint_log', 'updated_by'))                  $t->unsignedBigInteger('updated_by')->nullable()->after('added_by');
        });

        if (Schema::hasTable('claim_attachments') && !Schema::hasColumn('claim_attachments', 'complaint_id')) {
            Schema::table('claim_attachments', function (Blueprint $t) {
                $t->unsignedBigInteger('complaint_id')->nullable()->index()->after('claim_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('claim_complaint_log', function (Blueprint $t) {
            foreach ([
                'complainant_name', 'complainant_id_type', 'complainant_omang', 'complainant_passport',
                'complainant_phone', 'complainant_email', 'complainant_postal_address',
                'date_filed', 'reference_number', 'nature',
                'handler_user_id', 'escalation_level', 'status', 'rejection_reason', 'resolution', 'closed_at', 'updated_by',
            ] as $c) {
                if (Schema::hasColumn('claim_complaint_log', $c)) $t->dropColumn($c);
            }
        });

        if (Schema::hasTable('claim_attachments') && Schema::hasColumn('claim_attachments', 'complaint_id')) {
            Schema::table('claim_attachments', function (Blueprint $t) {
                $t->dropColumn('complaint_id');
            });
        }
    }
};
