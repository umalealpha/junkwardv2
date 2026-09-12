<?php

namespace Tests\Feature\AccountStatement;

use AlphaDirect\Services\AccountStatementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression cover for the Account Statement refund fix.
 *
 * Reported on policy COMG2024118407 (DomCom, product_id 7): a refund of
 * P 199.89 printed in the statement's Refund column while the Running Balance
 * carried on unchanged. Root cause — a Refund row carries its value in `debit`,
 * but `debit` was only ever read for `Invoice` and `Credit Note` rows, so the
 * refund was invisible to every accumulator. Only the MIS blade had a Refund
 * branch; DomCom did not, and understated the balance by the refunded amount.
 *
 * The fix makes refunds raise the balance on EVERY tier, and simultaneously
 * stops a refund's trans_ref from excluding the original Payment — doing both
 * would move the balance twice for a same-reference refund.
 *
 * SAFETY (same two hazards as tests/Feature/SpecifiedItems/SpecifiedItemsCrudTest.php):
 *
 *  1. backend/.env's default connection points at a live RDS and phpunit.xml's
 *     sqlite lines are commented out. setUp() forcibly rebinds the "sqlite"
 *     connection to :memory:, makes it the default, and FAILS LOUDLY if the
 *     resulting connection isn't actually sqlite :memory:.
 *
 *  2. Auditing writes to the separate 'mysql_system' connection, which falls
 *     back to the same live host when DB_HOST_SYSTEM is unset. Neutralised via
 *     audit.enabled=false and audit.drivers.database.connection=sqlite. This
 *     test only touches DB::table() directly (no Auditable models), so there is
 *     no observer to fire, but the config is pinned anyway.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit tests/Feature/AccountStatement/RefundBalanceTest.php
 */
class RefundBalanceTest extends TestCase
{
    private const POLICY_ID = 700;

    protected function setUp(): void
    {
        parent::setUp();

        // ── HARD SAFETY #1: force in-memory sqlite; refuse anything else. ──
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.driver' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['database.connections.sqlite.foreign_key_constraints' => false]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $conn = DB::connection();
        if ($conn->getDriverName() !== 'sqlite' || $conn->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing to run: expected an in-memory sqlite connection, got '
                . $conn->getDriverName() . ' / ' . $conn->getDatabaseName());
        }

        // ── HARD SAFETY #2: neutralise the mysql_system audit-write hazard. ──
        config(['audit.enabled' => false]);
        config(['audit.drivers.database.connection' => 'sqlite']);

        $this->buildSchema();
    }

    private function buildSchema(): void
    {
        Schema::create('policy_ledger', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->integer('action_id')->nullable();
            $t->string('trans_type')->nullable();
            $t->string('trans_ref')->nullable();
            $t->string('invoice_no')->nullable();
            $t->string('status')->nullable();
            $t->date('invoice_date')->nullable();
            $t->date('accounting_date')->nullable();
            $t->decimal('invoice_amount', 15, 2)->nullable();
            $t->decimal('debit', 15, 2)->nullable();
            $t->decimal('credit', 15, 2)->nullable();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('payment_transactions', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->string('policyNumber')->nullable();
            $t->string('referenceNumber')->nullable();
            $t->decimal('amount', 15, 2)->nullable();
            $t->string('status')->nullable();
            $t->string('CompanyRef')->nullable();
            $t->integer('is_refund')->nullable();
            $t->integer('is_reverse')->nullable();
            $t->string('reveral_transaction_id')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });

        // The refund filter is tier-scoped (AccountStatementService::isDomCom),
        // so the row set now depends on the policy's product. These tests were
        // written against COMG2024118407, a DomCom policy — default to that tier
        // and let the MIS cases opt in explicitly via asMis().
        Schema::create('policies', function ($t) {
            $t->increments('id');
            $t->integer('product_id')->nullable();
            $t->string('policyNumber')->nullable();
        });
        DB::table('policies')->insert([
            'id'           => self::POLICY_ID,
            'product_id'   => 7,                  // Commercial — DomCom
            'policyNumber' => 'COMGTEST700',
        ]);
    }

    /** Move the fixture policy onto the MIS tier (product 4 — Legal). */
    private function asMis(): void
    {
        DB::table('policies')->where('id', self::POLICY_ID)->update([
            'product_id'   => 4,
            'policyNumber' => 'MISTEST700',
        ]);
    }

    /** Register a reference as a successful payment_transactions row. */
    private function tx(string $ref, float $amount, int $isRefund = 0): void
    {
        DB::table('payment_transactions')->insert([
            'policy_id'       => self::POLICY_ID,
            'policyNumber'    => 'COMGTEST700',
            'referenceNumber' => $ref,
            'amount'          => $amount,
            'status'          => 'SUCCESS',
            'is_refund'       => $isRefund,
        ]);
    }

    private function ledger(string $type, string $ref, ?float $debit, ?float $credit, string $date): void
    {
        DB::table('policy_ledger')->insert([
            'policy_id'       => self::POLICY_ID,
            'trans_type'      => $type,
            'trans_ref'       => $ref,
            'status'          => 'Paid',
            'accounting_date' => $date,
            'invoice_date'    => $date,
            'invoice_amount'  => $type === 'Invoice' ? $debit : null,
            'debit'           => $debit,
            'credit'          => $credit,
        ]);
    }

    /**
     * The reported bug, reduced: invoice fully paid, then a refund paid out
     * under its OWN reference. The refund re-opens the amount owed.
     * Before the fix this returned 0.00 on DomCom — the refund was ignored.
     */
    public function test_refund_with_distinct_reference_raises_the_balance(): void
    {
        $this->tx('PAY-1', 100.00);
        $this->tx('REF-1', 50.00, 1);

        $this->ledger('Invoice', 'INV-1', 100.00, null, '2026-01-01');
        $this->ledger('Payment', 'PAY-1', null, 100.00, '2026-01-02');
        $this->ledger('Refund',  'REF-1', 50.00, null, '2026-01-03');

        $this->assertSame(
            50.00,
            AccountStatementService::closingBalance(self::POLICY_ID),
            'A refund paid out must re-open the amount owed by the refunded value'
        );
    }

    /**
     * A refund booked under the SAME reference as its payment must move the
     * balance exactly once. The payment credit stays in the set and the refund
     * debit nets it — it must NOT also drop the payment (that would be 2x).
     */
    public function test_same_reference_refund_does_not_double_count(): void
    {
        $this->tx('SAME-1', 100.00);
        $this->tx('SAME-1', 100.00, 1);

        $this->ledger('Invoice', 'INV-1',  100.00, null, '2026-01-01');
        $this->ledger('Payment', 'SAME-1', null, 100.00, '2026-01-02');
        $this->ledger('Refund',  'SAME-1', 100.00, null, '2026-01-03');

        $this->assertSame(
            100.00,
            AccountStatementService::closingBalance(self::POLICY_ID),
            'Same-ref refund must net to 100, not double-count to 200'
        );
    }

    /**
     * The real COMG2024118407 shape: a credit note lowers the premium and the
     * matching refund hands that money back, so the pair nets to zero. Before
     * the fix only the credit note landed, understating the balance by 199.89.
     */
    public function test_credit_note_and_matching_refund_net_to_zero(): void
    {
        $this->tx('PAY-1', 1000.00);
        $this->tx('CRN-1', 199.89, 1);

        $this->ledger('Invoice',     'INV-1', 1000.00, null, '2026-01-01');
        $this->ledger('Payment',     'PAY-1', null, 1000.00, '2026-01-02');
        $this->ledger('Credit Note', 'CRN-1', 199.89, null, '2026-01-03');
        $this->ledger('Refund',      'CRN-1', 199.89, null, '2026-01-04');

        $this->assertSame(
            0.00,
            AccountStatementService::closingBalance(self::POLICY_ID),
            'Credit note (-199.89) and its refund (+199.89) must cancel out'
        );
    }

    /** Guard: a policy with no refunds must be completely unaffected. */
    public function test_policy_without_refunds_is_unchanged(): void
    {
        $this->tx('PAY-1', 400.00);

        $this->ledger('Invoice', 'INV-1', 1000.00, null, '2026-01-01');
        $this->ledger('Payment', 'PAY-1', null, 400.00, '2026-01-02');

        $this->assertSame(
            600.00,
            AccountStatementService::closingBalance(self::POLICY_ID),
            'Refund-free policies must keep their existing balance'
        );
    }

    /** The Refund row must still be present in the rendered row set. */
    public function test_refund_row_is_included_in_statement_rows(): void
    {
        $this->tx('PAY-1', 100.00);
        $this->tx('REF-1', 50.00, 1);

        $this->ledger('Invoice', 'INV-1', 100.00, null, '2026-01-01');
        $this->ledger('Payment', 'PAY-1', null, 100.00, '2026-01-02');
        $this->ledger('Refund',  'REF-1', 50.00, null, '2026-01-03');

        $rows = AccountStatementService::rows(self::POLICY_ID);

        $this->assertCount(3, $rows, 'Payment must NOT be dropped by the refund reference');
        $this->assertSame(
            1,
            $rows->where('trans_type', 'Refund')->count(),
            'The Refund row must still render on the statement'
        );
    }

    /**
     * Reported on policy 79959 (product 4): a P 49.00 refund with trans_ref ''
     * showed in the Account View tab but was missing from the exported Account
     * Statement PDF. The Refund branch was `whereIn('trans_ref', $validPayments)`
     * — a refund had to name a valid payment to survive, and an empty reference
     * never can.
     */
    public function test_refund_with_empty_reference_is_included(): void
    {
        $this->asMis();
        $this->tx('PAY-1', 100.00);

        $this->ledger('Invoice', 'INV-1', 100.00, null, '2026-01-01');
        $this->ledger('Payment', 'PAY-1', null, 100.00, '2026-01-02');
        $this->ledger('Refund',  '',      49.00, null, '2026-01-03');

        $rows = AccountStatementService::rows(self::POLICY_ID);

        $this->assertSame(
            1,
            $rows->where('trans_type', 'Refund')->count(),
            'A refund with an empty trans_ref must still reach the statement'
        );
        $this->assertSame(
            49.00,
            AccountStatementService::closingBalance(self::POLICY_ID),
            'Its debit must re-open the amount owed'
        );
    }

    /** Same failure with a NULL reference rather than an empty string. */
    public function test_refund_with_null_reference_is_included(): void
    {
        $this->asMis();
        $this->tx('PAY-1', 100.00);

        $this->ledger('Invoice', 'INV-1', 100.00, null, '2026-01-01');
        $this->ledger('Payment', 'PAY-1', null, 100.00, '2026-01-02');
        DB::table('policy_ledger')->insert([
            'policy_id'       => self::POLICY_ID,
            'trans_type'      => 'Refund',
            'trans_ref'       => null,
            'status'          => 'Paid',
            'accounting_date' => '2026-01-03',
            'invoice_date'    => '2026-01-03',
            'debit'           => 25.00,
        ]);

        $this->assertSame(
            1,
            AccountStatementService::rows(self::POLICY_ID)->where('trans_type', 'Refund')->count(),
            'A refund with a NULL trans_ref must still reach the statement'
        );
    }

    /**
     * Backlog/manual refunds carry an operational reference of their own that
     * was never a payment reference (e.g. 'REFUND-BACKLOG-2026'). Those are
     * real accounting entries and must print.
     */
    public function test_refund_with_non_payment_reference_is_included(): void
    {
        $this->asMis();
        $this->tx('PAY-1', 100.00);

        $this->ledger('Invoice', 'INV-1', 100.00, null, '2026-01-01');
        $this->ledger('Payment', 'PAY-1', null, 100.00, '2026-01-02');
        $this->ledger('Refund',  'REFUND-BACKLOG-2026', 98.00, null, '2026-01-03');

        $this->assertSame(
            1,
            AccountStatementService::rows(self::POLICY_ID)->where('trans_type', 'Refund')->count(),
            'A refund under its own operational reference must reach the statement'
        );
    }

    /**
     * The original intent of the filter, preserved: a refund booked against a
     * payment that the statement already threw away (failed / test / reversed)
     * must stay out. This is what stops the fix from becoming "print everything".
     */
    public function test_refund_against_a_discarded_payment_stays_excluded(): void
    {
        $this->asMis();
        $this->tx('PAY-1', 100.00);
        // Failed payment — not in the valid set, so its refund must not print.
        DB::table('payment_transactions')->insert([
            'policy_id'       => self::POLICY_ID,
            'policyNumber'    => 'COMGTEST700',
            'referenceNumber' => 'BAD-1',
            'amount'          => 100.00,
            'status'          => 'Failed',
            'is_refund'       => 0,
        ]);

        $this->ledger('Invoice', 'INV-1', 100.00, null, '2026-01-01');
        $this->ledger('Payment', 'PAY-1', null, 100.00, '2026-01-02');
        $this->ledger('Refund',  'BAD-1', 100.00, null, '2026-01-03');

        $this->assertSame(
            0,
            AccountStatementService::rows(self::POLICY_ID)->where('trans_type', 'Refund')->count(),
            'A refund hanging off a discarded payment must stay off the statement'
        );
    }

    /**
     * SCOPE GUARD. The un-referenced-refund fix is MIS-only by decision: DomCom
     * keeps the original inclusion test so its Total Dues do not move. The
     * fixture policy stays on product 7 here — the identical data that now
     * prints on MIS must still be excluded.
     *
     * If this test starts failing, the fix has leaked onto the DomCom book.
     * That needs finance sign-off, not a test update.
     */
    public function test_domcom_refund_with_empty_reference_is_still_excluded(): void
    {
        $this->tx('PAY-1', 100.00);

        $this->ledger('Invoice', 'INV-1', 100.00, null, '2026-01-01');
        $this->ledger('Payment', 'PAY-1', null, 100.00, '2026-01-02');
        $this->ledger('Refund',  '',      49.00, null, '2026-01-03');

        $this->assertSame(
            0,
            AccountStatementService::rows(self::POLICY_ID)->where('trans_type', 'Refund')->count(),
            'DomCom refund filtering must be unchanged by the MIS fix'
        );
        $this->assertSame(
            0.00,
            AccountStatementService::closingBalance(self::POLICY_ID),
            'DomCom Total Dues must not move'
        );
    }

    /** The same data on the MIS tier — the fix, side by side with the guard above. */
    public function test_mis_refund_with_empty_reference_is_included_where_domcom_is_not(): void
    {
        $this->asMis();
        $this->tx('PAY-1', 100.00);

        $this->ledger('Invoice', 'INV-1', 100.00, null, '2026-01-01');
        $this->ledger('Payment', 'PAY-1', null, 100.00, '2026-01-02');
        $this->ledger('Refund',  '',      49.00, null, '2026-01-03');

        $this->assertSame(
            1,
            AccountStatementService::rows(self::POLICY_ID)->where('trans_type', 'Refund')->count(),
            'MIS must include the refund DomCom excludes'
        );
        $this->assertSame(49.00, AccountStatementService::closingBalance(self::POLICY_ID));
    }
}
