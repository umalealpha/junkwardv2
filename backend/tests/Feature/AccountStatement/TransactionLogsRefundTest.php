<?php

namespace Tests\Feature\AccountStatement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cover for the Transaction Logs tab showing refunds that were only ever
 * posted to the policy ledger.
 *
 * Reported on MIS2024079959 (policy 79959, product 4): two refunds — P 98.00
 * under 'REFUND-BACKLOG-2026' and P 49.00 with an empty reference — printed on
 * the Account View and, after the statement fix, on the exported PDF, but were
 * absent from Transaction Logs and from its Refund totals.
 *
 * Cause is different from the statement bug: no filter was dropping them. This
 * tab reads payment_transactions, and refunds raised through
 * PaymentController::refundTransaction write BOTH a payment row (is_refund=1)
 * and a ledger row. These two were posted straight to policy_ledger with no
 * payment row at all, so there was nothing for the tab to read. They are now
 * surfaced read-only as source='ledger'.
 *
 * MIS only, matching the statement fix — see AccountStatementService::isDomCom().
 *
 * SAFETY — the same two hazards as RefundBalanceTest: setUp() forces an
 * in-memory sqlite connection and fails loudly if it isn't one, and pins the
 * audit driver off so nothing reaches the separate mysql_system connection.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit tests/Feature/AccountStatement/TransactionLogsRefundTest.php
 */
class TransactionLogsRefundTest extends TestCase
{
    private const POLICY_ID = 79959;
    private const POLICY_NO = 'MIS2024079959';

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

        // archiveQuery() memoises per database name in a static, which outlives
        // an individual test. Clear it so each test decides for itself whether
        // an archive exists.
        \AlphaDirect\Services\AccountStatementService::forgetArchiveAvailability();

        // The endpoint builds logged_by_name with MySQL's CONCAT, which SQLite
        // has no equivalent for. Shim it here rather than rewriting production
        // SQL for the benefit of the harness.
        $conn->getPdo()->sqliteCreateFunction(
            'CONCAT',
            fn(...$parts) => implode('', array_map(fn($p) => (string) $p, $parts)),
            -1
        );

        $this->buildSchema();
    }

    private function buildSchema(): void
    {
        Schema::create('policy_ledger', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->integer('customer_id')->nullable();
            $t->string('trans_type')->nullable();
            $t->string('trans_ref')->nullable();
            $t->string('status')->nullable();
            $t->string('description')->nullable();
            $t->date('accounting_date')->nullable();
            $t->decimal('debit', 15, 2)->nullable();
            $t->decimal('credit', 15, 2)->nullable();
            $t->decimal('balance', 15, 2)->nullable();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('payment_transactions', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->string('policyNumber')->nullable();
            $t->string('referenceNumber')->nullable();
            $t->decimal('amount', 15, 2)->nullable();
            $t->string('paymentMethod')->nullable();
            $t->date('paymentDate')->nullable();
            $t->date('new_payment_date')->nullable();
            $t->integer('numberOfInstalmentsPaid')->nullable();
            $t->string('note')->nullable();
            $t->string('status')->nullable();
            $t->string('cashRecipient')->nullable();
            $t->string('payment_proof_link')->nullable();
            $t->integer('is_ledger')->nullable();
            $t->string('CompanyRef')->nullable();
            $t->integer('is_reverse')->nullable();
            $t->integer('is_refund')->nullable();
            $t->integer('paymentLoggedBy')->nullable();
            $t->string('reveral_transaction_id')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('policies', function ($t) {
            $t->increments('id');
            $t->integer('customer_id')->nullable();
            $t->integer('product_id')->nullable();
            $t->string('policyNumber')->nullable();
        });

        Schema::create('users', function ($t) {
            $t->increments('id');
            $t->string('firstName')->nullable();
            $t->string('lastName')->nullable();
            $t->string('email')->nullable();
        });

        // AuthGate::canReversePayment() resolves Spatie roles/permissions for
        // the caller. Left empty on purpose — the test user has no reverse
        // rights, which is irrelevant to refund listing and keeps the fixture
        // honest about what it is asserting.
        Schema::create('roles', function ($t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->string('guard_name')->nullable();
        });
        Schema::create('permissions', function ($t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->string('guard_name')->nullable();
        });
        Schema::create('model_has_roles', function ($t) {
            $t->integer('role_id')->nullable();
            $t->string('model_type')->nullable();
            $t->integer('model_id')->nullable();
        });
        Schema::create('model_has_permissions', function ($t) {
            $t->integer('permission_id')->nullable();
            $t->string('model_type')->nullable();
            $t->integer('model_id')->nullable();
        });
        Schema::create('role_has_permissions', function ($t) {
            $t->integer('permission_id')->nullable();
            $t->integer('role_id')->nullable();
        });

        DB::table('policies')->insert([
            'id'           => self::POLICY_ID,
            'customer_id'  => 4242,
            'product_id'   => 4,               // Legal — MIS
            'policyNumber' => self::POLICY_NO,
        ]);
    }

    /** Move the fixture policy onto the DomCom tier. */
    private function asDomCom(): void
    {
        DB::table('policies')->where('id', self::POLICY_ID)->update([
            'product_id'   => 7,
            'policyNumber' => 'COMG2024079959',
        ]);
    }

    private function payment(string $ref, float $amount, array $overrides = []): void
    {
        DB::table('payment_transactions')->insert(array_merge([
            'policy_id'       => self::POLICY_ID,
            'policyNumber'    => DB::table('policies')->where('id', self::POLICY_ID)->value('policyNumber'),
            'referenceNumber' => $ref,
            'amount'          => $amount,
            'paymentMethod'   => 'N-Genius',
            'paymentDate'     => '2024-05-30',
            'status'          => 'SUCCESS',
            'is_ledger'       => 1,
        ], $overrides));
    }

    private function ledgerRefund(?string $ref, float $debit, string $date): void
    {
        DB::table('policy_ledger')->insert([
            'policy_id'       => self::POLICY_ID,
            'customer_id'     => 4242,
            'trans_type'      => 'Refund',
            'trans_ref'       => $ref,
            'status'          => 'Paid',
            'description'     => 'Refund: backlog',
            'accounting_date' => $date,
            'debit'           => $debit,
        ]);
    }

    private function logs(): array
    {
        $user = (new \AlphaDirect\User)->forceFill(['id' => 1, 'email' => 'smoke@example.invalid']);
        $user->exists = true;

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/policies/' . self::POLICY_ID . '/transaction-logs');

        if ($response->status() !== 200) {
            $this->fail('Transaction Logs returned ' . $response->status() . ': '
                . substr((string) $response->getContent(), 0, 1000));
        }

        return $response->json();
    }

    /** The reported bug: both refunds must be listed. */
    public function test_ledger_only_refunds_are_listed(): void
    {
        $this->payment('ae1d6a77-6ad1-4f57-aea7-8d75d28e0624', 49.00);
        $this->ledgerRefund('REFUND-BACKLOG-2026', 98.00, '2024-04-10');
        $this->ledgerRefund('', 49.00, '2024-10-30');

        $body    = $this->logs();
        $ledger  = array_values(array_filter($body['data'], fn($r) => ($r['source'] ?? null) === 'ledger'));

        $this->assertCount(3, $body['data'], 'One payment plus both ledger refunds');
        $this->assertCount(2, $ledger, 'Both ledger-only refunds must be listed');

        $refs = array_column($ledger, 'referenceNumber');
        $this->assertContains('REFUND-BACKLOG-2026', $refs);
        $this->assertContains(null, $refs, 'The empty reference is surfaced as null, not dropped');
    }

    /** They must count into the Refund totals, and through them Total Balance. */
    public function test_ledger_only_refunds_reach_the_summary(): void
    {
        $this->payment('PAY-1', 294.00);
        $this->ledgerRefund('REFUND-BACKLOG-2026', 98.00, '2024-04-10');
        $this->ledgerRefund('', 49.00, '2024-10-30');

        $summary = $this->logs()['summary'];

        $this->assertSame(2, $summary['refundCount']);
        $this->assertEqualsWithDelta(147.0, $summary['refundAmount'], 0.001);
        // V8 formula: success - refund - failed - reversal
        $this->assertEqualsWithDelta(147.0, $summary['totalBalance'], 0.001, '294.00 received less 147.00 refunded');
    }

    /** Read-only: there is no payment transaction for the reverse endpoints to act on. */
    public function test_ledger_rows_are_not_reversible(): void
    {
        $this->ledgerRefund('REFUND-BACKLOG-2026', 98.00, '2024-04-10');

        $row = $this->logs()['data'][0];

        $this->assertSame('ledger', $row['source']);
        $this->assertFalse($row['canReverseBeforeLedger']);
        $this->assertFalse($row['canReverseAfterLedger']);
        $this->assertTrue($row['isRefunded']);
    }

    /**
     * A refund raised in the app writes BOTH rows, and stores the reference
     * as typed on the payment row but upper-cased on the ledger row. It must be
     * listed once, from the payment side — not twice.
     */
    public function test_an_app_raised_refund_is_not_duplicated(): void
    {
        $this->payment('PAY-1', 294.00);
        $this->payment('rf-2026-01', 49.00, ['is_refund' => 1, 'paymentMethod' => 'Cash']);
        $this->ledgerRefund('RF-2026-01', 49.00, '2024-10-30');   // upper-cased, as refundTransaction writes it

        $body = $this->logs();

        $this->assertCount(2, $body['data'], 'The refund must appear once, not once per table');
        $this->assertSame(
            0,
            count(array_filter($body['data'], fn($r) => ($r['source'] ?? null) === 'ledger')),
            'The ledger row is the duplicate and must be suppressed'
        );
        $this->assertSame(1, $body['summary']['refundCount']);
        $this->assertEqualsWithDelta(49.0, $body['summary']['refundAmount'], 0.001);
    }

    /**
     * SCOPE GUARD. MIS only, matching the Account Statement fix. If this starts
     * failing the change has leaked onto the DomCom book — that needs finance
     * sign-off, not a test update.
     */
    public function test_domcom_does_not_show_ledger_only_refunds(): void
    {
        $this->asDomCom();
        $this->payment('PAY-1', 294.00);
        $this->ledgerRefund('REFUND-BACKLOG-2026', 98.00, '2024-04-10');

        $body = $this->logs();

        $this->assertCount(1, $body['data'], 'DomCom keeps showing payment rows only');
        $this->assertSame(0, $body['summary']['refundCount']);
        $this->assertEqualsWithDelta(294.0, $body['summary']['totalBalance'], 0.001, 'DomCom Total Balance must not move');
    }

    /** Payment rows are labelled too, so the UI can tell the two apart. */
    public function test_payment_rows_are_labelled_as_payments(): void
    {
        $this->payment('PAY-1', 294.00);

        $this->assertSame('payment', $this->logs()['data'][0]['source']);
    }

    /** The endpoint is behind auth:sanctum. */
    public function test_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/policies/' . self::POLICY_ID . '/transaction-logs')
            ->assertUnauthorized();
    }

    /**
     * Mount an in-memory sqlite database on the 'mysql3' connection with the
     * archived ledger table on it.
     *
     * This is the shape production has: the archive is the table
     * `policy_ledger` inside a SEPARATE database (graphite_archive), reached on
     * 'mysql3' — see AlphaDirect\Models\LedgerArchive. It is emphatically NOT a
     * 'policy_ledger_archive' table on the primary connection, which is what
     * every V2 archive read used to probe for, so the merge never ran anywhere.
     *
     * SAFETY: this points 'mysql3' at :memory:, which also guarantees the test
     * cannot reach the real graphite_archive database.
     */
    private function withArchive(): void
    {
        config([
            'database.connections.mysql3.driver'                  => 'sqlite',
            'database.connections.mysql3.database'                => ':memory:',
            'database.connections.mysql3.foreign_key_constraints' => false,
        ]);
        DB::purge('mysql3');
        \AlphaDirect\Services\AccountStatementService::forgetArchiveAvailability();

        $archive = DB::connection('mysql3');
        if ($archive->getDriverName() !== 'sqlite' || $archive->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing to run: the archive connection must be in-memory sqlite.');
        }

        $archive->getSchemaBuilder()->create('policy_ledger', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->integer('customer_id')->nullable();
            $t->string('trans_type')->nullable();
            $t->string('trans_ref')->nullable();
            $t->string('status')->nullable();
            $t->string('description')->nullable();
            $t->date('accounting_date')->nullable();
            $t->decimal('debit', 15, 2)->nullable();
            $t->decimal('credit', 15, 2)->nullable();
            $t->decimal('balance', 15, 2)->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
    }

    private function archivedRefund(?string $ref, float $debit, string $date): void
    {
        DB::connection('mysql3')->table('policy_ledger')->insert([
            'policy_id'       => self::POLICY_ID,
            'customer_id'     => 4242,
            'trans_type'      => 'Refund',
            'trans_ref'       => $ref,
            'status'          => 'Paid',
            'description'     => 'Refund: archived backlog',
            'accounting_date' => $date,
            'debit'           => $debit,
        ]);
    }

    /**
     * THE REGRESSION. A refund that only exists in the archive database must be
     * listed, and must count towards the Refund totals.
     *
     * Before the fix the archive branch was gated on
     * Schema::hasTable('policy_ledger_archive') against the PRIMARY connection.
     * No such table exists in any environment, so the branch was dead and
     * archived refunds stayed invisible on this tab — which is why refunds were
     * still reported missing on MIS policies after the live-ledger fix.
     */
    public function test_archived_ledger_refunds_are_listed(): void
    {
        $this->withArchive();
        $this->payment('PAY-1', 294.00);
        $this->archivedRefund('REFUND-BACKLOG-2026', 98.00, '2024-04-10');

        $body = $this->logs();

        $archived = array_values(array_filter(
            $body['data'],
            fn($r) => ($r['source'] ?? null) === 'ledger-archive'
        ));

        $this->assertCount(1, $archived, 'The archived refund must reach the tab');
        $this->assertSame('REFUND-BACKLOG-2026', $archived[0]['referenceNumber']);
        $this->assertTrue($archived[0]['isRefunded']);
        $this->assertFalse($archived[0]['canReverseBeforeLedger'], 'Archived refunds are read-only');
        $this->assertFalse($archived[0]['canReverseAfterLedger'], 'Archived refunds are read-only');

        $this->assertSame(1, $body['summary']['refundCount']);
        $this->assertEqualsWithDelta(98.0, $body['summary']['refundAmount'], 0.001);
        $this->assertEqualsWithDelta(196.0, $body['summary']['totalBalance'], 0.001);
    }

    /**
     * Archiving copies rather than moves, so the same refund can sit in both
     * tables. Ids are not comparable across two databases, so the endpoint
     * collapses on reference + date + amount and keeps the live row.
     */
    public function test_a_refund_in_both_the_live_ledger_and_the_archive_is_listed_once(): void
    {
        $this->withArchive();
        $this->payment('PAY-1', 294.00);
        $this->ledgerRefund('REFUND-BACKLOG-2026', 98.00, '2024-04-10');
        $this->archivedRefund('REFUND-BACKLOG-2026', 98.00, '2024-04-10');

        $body = $this->logs();

        $refunds = array_values(array_filter(
            $body['data'],
            fn($r) => str_starts_with((string) ($r['source'] ?? ''), 'ledger')
        ));

        $this->assertCount(1, $refunds, 'The archived twin must be collapsed into the live row');
        $this->assertSame('ledger', $refunds[0]['source'], 'The live copy is the one kept');
        $this->assertSame(1, $body['summary']['refundCount'], 'and it must only be counted once');
        $this->assertEqualsWithDelta(98.0, $body['summary']['refundAmount'], 0.001);
    }

    /**
     * A refund raised through refundTransaction() writes a payment row AND a
     * ledger row; if that ledger row has since been archived it must still be
     * suppressed, or the refund is listed twice under two different sources.
     */
    public function test_an_archived_copy_of_an_app_raised_refund_is_not_listed_twice(): void
    {
        $this->withArchive();
        $this->payment('PAY-1', 294.00);
        $this->payment('ref-49', 49.00, ['is_refund' => 1, 'paymentMethod' => 'Cash']);
        $this->archivedRefund('REF-49', 49.00, '2024-06-01');

        $body = $this->logs();

        $this->assertCount(
            0,
            array_filter($body['data'], fn($r) => str_starts_with((string) ($r['source'] ?? ''), 'ledger')),
            'The archived row duplicates the payment-backed refund and must be suppressed'
        );
        $this->assertSame(1, $body['summary']['refundCount']);
        $this->assertEqualsWithDelta(49.0, $body['summary']['refundAmount'], 0.001);
    }

    /** SCOPE GUARD. The archive read sits inside the MIS-only branch too. */
    public function test_domcom_does_not_show_archived_ledger_refunds(): void
    {
        $this->withArchive();
        $this->asDomCom();
        $this->payment('PAY-1', 294.00);
        $this->archivedRefund('REFUND-BACKLOG-2026', 98.00, '2024-04-10');

        $body = $this->logs();

        $this->assertCount(1, $body['data'], 'DomCom keeps showing payment rows only');
        $this->assertSame(0, $body['summary']['refundCount']);
        $this->assertEqualsWithDelta(294.0, $body['summary']['totalBalance'], 0.001, 'DomCom Total Balance must not move');
    }

    /**
     * The archive/live collapse must be one-directional. Two distinct
     * reference-less refunds posted to the LIVE ledger on the same day for the
     * same amount are two refunds, not one — a blanket unique() on the shared
     * signature would swallow the second, and reference-less refunds are the
     * exact shape this whole fix exists to surface.
     */
    public function test_two_identical_live_refunds_are_both_listed(): void
    {
        $this->withArchive();
        $this->payment('PAY-1', 294.00);
        $this->ledgerRefund(null, 49.00, '2024-04-10');
        $this->ledgerRefund(null, 49.00, '2024-04-10');

        $body = $this->logs();

        $this->assertCount(
            2,
            array_filter($body['data'], fn($r) => ($r['source'] ?? null) === 'ledger'),
            'Both live refunds must survive'
        );
        $this->assertSame(2, $body['summary']['refundCount']);
        $this->assertEqualsWithDelta(98.0, $body['summary']['refundAmount'], 0.001);
    }

    /** No archive configured must still work — it degrades to live rows only. */
    public function test_missing_archive_degrades_to_the_live_ledger(): void
    {
        $this->payment('PAY-1', 294.00);
        $this->ledgerRefund('REFUND-BACKLOG-2026', 98.00, '2024-04-10');

        $body = $this->logs();

        $this->assertSame(1, $body['summary']['refundCount']);
        $this->assertEqualsWithDelta(98.0, $body['summary']['refundAmount'], 0.001);
    }
}
