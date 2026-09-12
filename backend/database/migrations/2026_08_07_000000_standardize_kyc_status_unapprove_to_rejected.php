<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Standardize the customer KYC rejected status to a single value.
     *
     * The rejected state was historically stored two ways: the MIS React
     * flow writes lowercase 'rejected', while the DOM/COM reviewer, the
     * legacy admin compliance helpers and the compliance crons wrote
     * capitalized 'Unapprove'. Both mean the same thing (compliance = 2).
     * The writers have now been changed to emit 'rejected'; this backfills
     * the existing rows so the vocabulary is uniform ('Unapprove' gone).
     *
     * Notes:
     *  - Uses the query builder (NOT Eloquent) deliberately: the KYC models
     *    are OwenIt-audited, so an Eloquent save() would spray audit rows and
     *    bump updated_at — and the KYC report stored-procs key on
     *    CAST(updated_at AS DATE) = CURDATE(). A raw update touches neither.
     *  - The activity log keeps a past-tense vocabulary ('Approved'/'Rejected'),
     *    so its 'Unapprove' rows are normalized to 'Rejected' (matching the MIS
     *    log label), NOT lowercase 'rejected'.
     *  - Idempotent: the WHERE clauses match nothing on re-run.
     *  - One-way: after backfill a 'rejected' row can't be distinguished from a
     *    natively-MIS 'rejected' row, so down() is intentionally a no-op.
     */
    public function up(): void
    {
        if (Schema::hasTable('customer_kyc') && Schema::hasColumn('customer_kyc', 'status')) {
            DB::table('customer_kyc')->where('status', 'Unapprove')->update(['status' => 'rejected']);
        }

        if (Schema::hasTable('customer_kyc_dom_com') && Schema::hasColumn('customer_kyc_dom_com', 'status')) {
            DB::table('customer_kyc_dom_com')->where('status', 'Unapprove')->update(['status' => 'rejected']);
        }

        // Activity-log vocabulary is past-tense: normalize 'Unapprove' → 'Rejected'
        // (parity with the MIS log label), not lowercase 'rejected'.
        if (Schema::hasTable('kyc_activity_log') && Schema::hasColumn('kyc_activity_log', 'status')) {
            DB::table('kyc_activity_log')->where('status', 'Unapprove')->update(['status' => 'Rejected']);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: once 'Unapprove' is folded into 'rejected'
        // there is no way to tell the backfilled rows apart from rows that were
        // always 'rejected' (MIS). Reverting would wrongly relabel MIS rows.
    }
};
