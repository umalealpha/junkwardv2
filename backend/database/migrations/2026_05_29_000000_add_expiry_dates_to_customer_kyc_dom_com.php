<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-DOMCOM-KYC-EXPIRY: surface a per-document expiry date on the
 * DOM/COM KYC review page for the four ID/passport docs that have one
 * but no dedicated column yet.
 *
 *   - directorsIDExp           (Directors ID expiry)
 *   - directorsPassportExp     (Directors passport expiry)
 *   - shareholdersIDExp        (Shareholders ID expiry)
 *   - shareholdersPassportExp  (Shareholders passport expiry)
 *
 * The detail() response already reads these columns optimistically
 * via `?? null`, so production rows without the columns just returned
 * NULL — adding them lets reviewers actually write a value.
 *
 * Omang / Passport / Driving License expiries already live on
 * customer_kyc (omangExpiry / passportExpiry / licenseExpiry, V8
 * legacy) — no new columns needed there.
 *
 * Column name casing matches V8's camelCase neighbours on this table
 * (directorsIDFrontStatus, shareholdersPassportRemark, etc.) so the
 * Eloquent model and raw DB::table() reads stay consistent.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customer_kyc_dom_com')) return;

        Schema::table('customer_kyc_dom_com', function (Blueprint $table) {
            foreach ([
                'directorsIDExp',
                'directorsPassportExp',
                'shareholdersIDExp',
                'shareholdersPassportExp',
            ] as $col) {
                if (!Schema::hasColumn('customer_kyc_dom_com', $col)) {
                    $table->date($col)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_kyc_dom_com')) return;

        Schema::table('customer_kyc_dom_com', function (Blueprint $table) {
            foreach ([
                'directorsIDExp',
                'directorsPassportExp',
                'shareholdersIDExp',
                'shareholdersPassportExp',
            ] as $col) {
                if (Schema::hasColumn('customer_kyc_dom_com', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
