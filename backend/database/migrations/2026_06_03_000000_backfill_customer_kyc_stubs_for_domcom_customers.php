<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill empty `customer_kyc` anchor rows for every customer who
 * lacks one but should be visible in the unified Customer KYC list.
 *
 * Why
 * ---
 * V2's list (CustomerKycController::index) mirrors graphiteBWV8's
 * CustomerKyc\Table::builder() — anchored on `customer_kyc` with an
 * INNER JOIN on `policies`. V8 production has 6,413 DOM/COM rows
 * because every such customer has a `customer_kyc` row in V8 (V8
 * created one upstream — at customer registration / first policy
 * issue / first agent app upload). V2 never wired that creation up,
 * so DOM/COM customers with only a `customer_kyc_dom_com` upload
 * (e.g. COMG2026213575 after a single Certificate of Incorporation
 * upload) had no anchor row and were invisible in the listing.
 *
 * The fix going forward lives in PolicyCreateController::uploadKycDocuments
 * (creates a stub at upload-time). This migration handles the
 * historical data so the existing thousands of customers surface
 * immediately without needing a fresh upload.
 *
 * Scope
 * -----
 * Inserts a stub for every customer_id that satisfies any of:
 *   - has a row in customer_kyc_dom_com but not customer_kyc
 *   - holds any policy with a DOM/COM product (matches V8's
 *     CustomerKyc\Table policy filter) but has no customer_kyc row
 *
 * Each stub carries the minimum fields to match what
 * uploadKycDocuments creates: status='Unchecked', compliance=0.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customer_kyc')) return;

        // V8 DOM/COM product ids — matches KycDomComProducts::IDS but
        // hard-coded here so the migration is independent of app code.
        $domComProductIds = [7, 8, 16, 17, 18, 19, 20, 22];
        $now = now();

        // Customers with DomCom rows but no customer_kyc row.
        $missingFromDomCom = collect();
        if (Schema::hasTable('customer_kyc_dom_com')) {
            $missingFromDomCom = DB::table('customer_kyc_dom_com as kd')
                ->leftJoin('customer_kyc as k', 'k.customer_id', '=', 'kd.customer_id')
                ->whereNull('k.id')
                ->select('kd.customer_id')
                ->distinct()
                ->pluck('customer_id');
        }

        // Customers with DOM/COM policies but no customer_kyc row.
        $missingFromPolicies = DB::table('policies as p')
            ->leftJoin('customer_kyc as k', 'k.customer_id', '=', 'p.customer_id')
            ->whereIn('p.product_id', $domComProductIds)
            ->whereNull('k.id')
            ->select('p.customer_id')
            ->distinct()
            ->pluck('customer_id');

        $customerIds = $missingFromDomCom
            ->merge($missingFromPolicies)
            ->filter()
            ->unique()
            ->values();

        if ($customerIds->isEmpty()) return;

        // Only insert stubs for customer_ids that actually exist in the
        // `customer` table — otherwise we'd create dangling KYC rows.
        $existingCustomerIds = DB::table('customer')
            ->whereIn('id', $customerIds->all())
            ->pluck('id');

        if ($existingCustomerIds->isEmpty()) return;

        // Probe customer_kyc for which optional columns we can fill —
        // the minimum is customer_id; status / compliance / timestamps
        // are added only when they exist on this env.
        $kycCols = Schema::getColumnListing('customer_kyc');
        $hasStatus     = in_array('status',     $kycCols, true);
        $hasCompliance = in_array('compliance', $kycCols, true);
        $hasCreatedAt  = in_array('created_at', $kycCols, true);
        $hasUpdatedAt  = in_array('updated_at', $kycCols, true);

        // Batched insert — 500 rows per INSERT to keep MySQL happy on
        // envs with thousands of orphan customers (live has ~6k).
        $batches = $existingCustomerIds->chunk(500);
        foreach ($batches as $batch) {
            $rows = [];
            foreach ($batch as $cid) {
                $row = ['customer_id' => $cid];
                if ($hasStatus)     $row['status']     = 'Unchecked';
                if ($hasCompliance) $row['compliance'] = 0;
                if ($hasCreatedAt)  $row['created_at'] = $now;
                if ($hasUpdatedAt)  $row['updated_at'] = $now;
                $rows[] = $row;
            }
            DB::table('customer_kyc')->insert($rows);
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: identifying which stub rows came from
        // this backfill (vs ones operators have since touched) is
        // ambiguous — the safe rollback is to leave them in place. The
        // stubs carry no real data and don't affect compliance counts.
    }
};
