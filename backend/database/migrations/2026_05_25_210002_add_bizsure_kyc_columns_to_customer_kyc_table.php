<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds nullable BizSure commercial KYC columns to customer_kyc:
 *   - company_reg      (Certificate of Incorporation S3 path)
 *   - company_extract  (CIPA Company Standard Extract)
 *   - bors             (BORS Certificate)
 *   - tin              (TIN Certificate)
 *
 * Additive. Existing callers untouched.
 */
class AddBizsureKycColumnsToCustomerKycTable extends Migration
{
    public function up(): void
    {
        Schema::table('customer_kyc', function (Blueprint $table) {
            foreach (['company_reg', 'company_extract', 'bors', 'tin'] as $col) {
                if (! Schema::hasColumn('customer_kyc', $col)) {
                    $table->string($col)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_kyc', function (Blueprint $table) {
            foreach (['company_reg', 'company_extract', 'bors', 'tin'] as $col) {
                if (Schema::hasColumn('customer_kyc', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
