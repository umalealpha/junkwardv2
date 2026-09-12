<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make payment_refunds.graphite_ref UNIQUE.
 *
 * graphite_ref is the engine's idempotency key: OmniHandoffService::
 * ensurePaymentRefund() looks the row up and inserts it if absent. That is a
 * check-then-insert with no lock, so two concurrent callers (an approval racing
 * the reconcile sweep, or a retried Omni callback) could both miss and both
 * insert — giving one refund TWO money-execution rows, two 'succeeded' updates
 * and two candidate ledger postings. The index makes the database refuse the
 * second insert outright, which is the only place that race can be closed
 * reliably.
 *
 * NULLs are unaffected: MySQL permits many NULLs in a unique index, so the
 * legacy DPO refund rows (which carry no graphite_ref) are untouched.
 *
 * Safe to run: verified on PROD 2026-08-12 that payment_refunds holds no rows
 * with a duplicate graphite_ref. Idempotent — no-ops if already applied.
 */
return new class extends Migration
{
    private const OLD = 'payment_refunds_graphite_ref_index';
    private const NEW = 'payment_refunds_graphite_ref_unique';

    public function up(): void
    {
        if (!Schema::hasTable('payment_refunds') || !Schema::hasColumn('payment_refunds', 'graphite_ref')) {
            return;
        }
        if ($this->hasIndex(self::NEW)) {
            return;
        }
        // Refuse rather than corrupt: if duplicates somehow exist, leave the
        // schema alone and fail loudly so they can be resolved first.
        $dupes = DB::selectOne(
            'SELECT COUNT(*) AS c FROM (
                SELECT graphite_ref FROM payment_refunds
                WHERE graphite_ref IS NOT NULL
                GROUP BY graphite_ref HAVING COUNT(*) > 1
             ) d'
        );
        if ($dupes && (int) $dupes->c > 0) {
            throw new \RuntimeException(
                'payment_refunds has ' . $dupes->c . ' duplicated graphite_ref value(s). '
                . 'Resolve them before adding the unique index.');
        }

        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->unique('graphite_ref', self::NEW);
        });
        // Drop the now-redundant non-unique index (the unique one serves the
        // same lookups).
        if ($this->hasIndex(self::OLD)) {
            Schema::table('payment_refunds', function (Blueprint $table) {
                $table->dropIndex(self::OLD);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_refunds')) {
            return;
        }
        if ($this->hasIndex(self::NEW)) {
            Schema::table('payment_refunds', function (Blueprint $table) {
                $table->dropUnique(self::NEW);
            });
        }
        if (!$this->hasIndex(self::OLD)) {
            Schema::table('payment_refunds', function (Blueprint $table) {
                $table->index('graphite_ref', self::OLD);
            });
        }
    }

    private function hasIndex(string $index): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['payment_refunds', $index]
        );
        return $row && (int) $row->c > 0;
    }
};
