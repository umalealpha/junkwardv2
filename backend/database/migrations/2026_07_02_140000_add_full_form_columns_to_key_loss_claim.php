<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add the full graphiteBWV8 key-loss form columns to key_loss_claim
     * (2026-07-02). Mirrors admin/policy/key_loss.blade.php — Insured Details,
     * Vehicle Details and two Quotes (with uploaded quote files). The
     * pre-existing columns (financial_interest, chassis_num, estimate, purpose,
     * reason, lossDate, descriptionofLoss, registered_claim, police_affidavit)
     * are left as-is. All additive + nullable + hasColumn-guarded.
     */
    private const COLUMNS = [
        // Insured Details
        'name_of_insured',
        'insured_address',
        'insured_occupation',
        'insured_email',
        'insured_contact_no',
        // Vehicle Details
        'vehicle_plate',
        'is_imported',
        'make',
        'year',
        'model',
        // Quotation — Quote 1 & 2 (quote_1 / quote_2 hold S3 file paths)
        'company_1',
        'amount_quote_1',
        'quote_1',
        'company_2',
        'amount_quote_2',
        'quote_2',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $col) {
            if (! Schema::hasColumn('key_loss_claim', $col)) {
                Schema::table('key_loss_claim', function (Blueprint $table) use ($col) {
                    $table->string($col)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $col) {
            if (Schema::hasColumn('key_loss_claim', $col)) {
                Schema::table('key_loss_claim', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
