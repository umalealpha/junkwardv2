<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Specialist coverage tables — ensure the base columns the UI saves actually
 * exist in the DB. On envs where earlier migrations haven't run
 * (eg. DB restored from a prod snapshot that pre-dates the columns),
 * buildPayload() drops them silently and users see "saved successfully"
 * but the data doesn't round-trip (the exact complaint on the PDF
 * screenshots: Policy Number on Medical Malpractice, Inception/Expiry/Today
 * dates on Professional Indemnity).
 *
 * Idempotent — each check is hasColumn-gated so reruns are safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Medical Malpractice — Policy Number + core document fields + dates
        if (Schema::hasTable('medical_malpractice_coverages')) {
            Schema::table('medical_malpractice_coverages', function (Blueprint $t) {
                $table = 'medical_malpractice_coverages';
                if (!Schema::hasColumn($table, 'policy_number'))              $t->string('policy_number')->nullable();
                if (!Schema::hasColumn($table, 'type_of_document'))           $t->string('type_of_document')->nullable();
                if (!Schema::hasColumn($table, 'insured'))                    $t->string('insured')->nullable();
                if (!Schema::hasColumn($table, 'insured_vat_number'))         $t->string('insured_vat_number')->nullable();
                if (!Schema::hasColumn($table, 'company_registration_number'))$t->string('company_registration_number')->nullable();
                if (!Schema::hasColumn($table, 'insured_business_description')) $t->text('insured_business_description')->nullable();
                if (!Schema::hasColumn($table, 'insured_postal_address'))     $t->text('insured_postal_address')->nullable();
                if (!Schema::hasColumn($table, 'intermediary'))               $t->string('intermediary')->nullable();
                if (!Schema::hasColumn($table, 'policy_inception_date'))      $t->date('policy_inception_date')->nullable();
                if (!Schema::hasColumn($table, 'policy_expiry_date'))         $t->date('policy_expiry_date')->nullable();
                if (!Schema::hasColumn($table, 'today_date'))                 $t->date('today_date')->nullable();
                if (!Schema::hasColumn($table, 'retroactive_date'))           $t->date('retroactive_date')->nullable();
                if (!Schema::hasColumn($table, 'anniversary_renewal_date'))   $t->date('anniversary_renewal_date')->nullable();
                if (!Schema::hasColumn($table, 'period_of_insurance'))        $t->string('period_of_insurance')->nullable();
                if (!Schema::hasColumn($table, 'notes'))                      $t->text('notes')->nullable();
                if (!Schema::hasColumn($table, 'extensions'))                 $t->json('extensions')->nullable();
                if (!Schema::hasColumn($table, 'specific_deductibles'))       $t->json('specific_deductibles')->nullable();
                if (!Schema::hasColumn($table, 'risk_details'))               $t->json('risk_details')->nullable();
                if (!Schema::hasColumn($table, 'policy_wording_path'))        $t->string('policy_wording_path')->nullable();
            });
        }

        // Professional Indemnity — dates (covered by earlier migration but
        // re-check here for envs where that migration hadn't run yet).
        if (Schema::hasTable('professional_indemnity_coverages')) {
            Schema::table('professional_indemnity_coverages', function (Blueprint $t) {
                $table = 'professional_indemnity_coverages';
                if (!Schema::hasColumn($table, 'policy_inception_date')) $t->date('policy_inception_date')->nullable();
                if (!Schema::hasColumn($table, 'policy_expiry_date'))    $t->date('policy_expiry_date')->nullable();
                if (!Schema::hasColumn($table, 'today_date'))            $t->date('today_date')->nullable();
                if (!Schema::hasColumn($table, 'notes'))                 $t->text('notes')->nullable();
                if (!Schema::hasColumn($table, 'policy_wording_path'))   $t->string('policy_wording_path')->nullable();
            });
        }

        // Marine Cargo Once-Off + Open — policy_number and date fields
        foreach (['marine_cargo_once_off_coverages', 'marine_cargo_open_coverages'] as $table) {
            if (!Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'policy_number'))        $t->string('policy_number')->nullable();
                if (!Schema::hasColumn($table, 'inception_date'))       $t->date('inception_date')->nullable();
                if (!Schema::hasColumn($table, 'expiry_date'))          $t->date('expiry_date')->nullable();
                if (!Schema::hasColumn($table, 'today_date'))           $t->date('today_date')->nullable();
                if (!Schema::hasColumn($table, 'notes'))                $t->text('notes')->nullable();
                if (!Schema::hasColumn($table, 'policy_wording_path'))  $t->string('policy_wording_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        // No-op: these are additive safety columns and dropping them would
        // destroy legitimate data. If a clean rollback is needed, drop the
        // whole table via the original create migration's down().
    }
};
