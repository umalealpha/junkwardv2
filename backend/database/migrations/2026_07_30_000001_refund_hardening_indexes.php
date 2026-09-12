<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund engine hardening — indexes the fraud scan needs, and the CR-number
 * uniqueness the credit-note generator assumed but never had.
 *
 * Why the indexes: RefundFraudService's serial-refunder and agent-concentration
 * signals filter on customer_name / agent_name, neither of which was indexed.
 * As refund_requests grows those become full scans; the scan is wrapped in a
 * try/catch at submit, so a statement timeout would silently leave fraud_flags
 * NULL and the request would look screened-and-clean when it was never screened.
 *
 * The credit_note unique index is intentionally NOT added here: PROD already
 * contains duplicate credit_note_no values (CR000385 x3 and others, created by
 * the legacy read-max-then-increment generator), so adding it would fail the
 * migration. Cleaning those duplicates is a separate Finance-scoped data fix;
 * RefundCreditNoteService::nextCreditNoteNo() now allocates under a row lock and
 * skips any number already taken, so new notes cannot add to the problem.
 *
 * Additive + idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('refund_requests')) {
            return;
        }
        Schema::table('refund_requests', function (Blueprint $table) {
            foreach (['customer_name', 'agent_name'] as $col) {
                $idx = 'refund_requests_' . $col . '_idx';
                if (Schema::hasColumn('refund_requests', $col) && !$this->hasIndex('refund_requests', $idx)) {
                    $table->index($col, $idx);
                }
            }
            // The fraud scan and the in-flight duplicate check both filter on
            // customer_id; it was only ever written, never queried by.
            $custIdx = 'refund_requests_customer_id_idx';
            if (Schema::hasColumn('refund_requests', 'customer_id') && !$this->hasIndex('refund_requests', $custIdx)) {
                $table->index('customer_id', $custIdx);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('refund_requests')) {
            return;
        }
        Schema::table('refund_requests', function (Blueprint $table) {
            foreach ([
                'refund_requests_customer_name_idx',
                'refund_requests_agent_name_idx',
                'refund_requests_customer_id_idx',
            ] as $idx) {
                if ($this->hasIndex('refund_requests', $idx)) {
                    $table->dropIndex($idx);
                }
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $row = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );
        return $row && (int) $row->c > 0;
    }
};
