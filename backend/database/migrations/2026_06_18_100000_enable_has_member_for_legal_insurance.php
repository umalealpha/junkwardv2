<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Legal Insurance (product_id=4) ships with a fully product-agnostic
 * Members/Beneficiaries stack already — PolicyController::members(),
 * PolicyCreateController::add/update/deleteMember(Beneficiary)(), and the
 * Policy Detail page's "Members / Beneficiaries" tab all key off policy_id,
 * not product_id. The only thing missing was data: `products.has_member`
 * is 1 for Accidental Death Insurance (product_id=1) but 0 for Legal
 * Insurance, and the FE only shows the tab when
 * `policy.hasMember || policy.product.hasMember` is true.
 *
 * For ADI the product-level flag means the tab is always available, so
 * staff can add the first beneficiary on any policy regardless of whether
 * one was captured at quote time. Legal Insurance only auto-captures a
 * spouse as a policy_beneficiary row when the applicant is married
 * (LegalInsuranceController::createPolicy) and had has_member=0 at the
 * product level, so any Legal policy without a spouse captured at signup
 * (or any married applicant who needs a beneficiary correction) could never
 * show the tab at all — there was no way to reach the existing "+ Add
 * Beneficiary" button.
 *
 * Setting the product-level flag is the same shape of fix as ADI and
 * unlocks the tab without touching any application code.
 */
return new class extends Migration {
    private const PRODUCT_ID = 4; // Legal Insurance

    public function up(): void
    {
        DB::table('products')->where('id', self::PRODUCT_ID)->update(['has_member' => 1]);
    }

    public function down(): void
    {
        DB::table('products')->where('id', self::PRODUCT_ID)->update(['has_member' => 0]);
    }
};
