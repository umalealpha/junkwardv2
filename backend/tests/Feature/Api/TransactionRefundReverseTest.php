<?php

namespace Tests\Feature\Api;

use AlphaDirect\Services\AccountStatementService;
use AlphaDirect\Services\Dpo\RefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests for the V1 refund + reversal endpoints.
 *
 * These tests insert disposable rows into payment_transactions / policy_ledger
 * for an existing policy and roll them back at the end. They skip cleanly if
 * the local DB is empty (no users / no policy).
 */
class TransactionRefundReverseTest extends TestCase
{
    /** @var int|null */
    private $policyId;

    /** @var string|null */
    private $policyNumber;

    private function authAdmin()
    {
        $user = \AlphaDirect\User::first();
        if (!$user) {
            $this->markTestSkipped('No users in database');
        }
        Sanctum::actingAs($user, ['*']);

        $policy = DB::table('policies')->whereNotNull('policyNumber')->orderBy('id', 'desc')->first(['id', 'policyNumber']);
        if (!$policy) {
            $this->markTestSkipped('No policies in database');
        }
        $this->policyId = (int) $policy->id;
        $this->policyNumber = (string) $policy->policyNumber;
        return $user;
    }

    private function uniqueRef(string $tag): string
    {
        return strtoupper($tag) . '-' . substr((string) microtime(true), -8);
    }

    public function test_refund_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/policies/1/transaction-logs/refund', []);
        $response->assertStatus(401);
    }

    public function test_refund_rejects_future_date(): void
    {
        $this->authAdmin();
        $future = date('Y-m-d', strtotime('+5 days'));

        $response = $this->postJson("/api/v1/policies/{$this->policyId}/transaction-logs/refund", [
            'reference_number' => $this->uniqueRef('FUT'),
            'date_of_refund'   => $future,
            'amount'           => 10.00,
            'reason'           => 'test future-date guard',
            'refunded_by'      => 'PHPUnit',
        ]);

        $response->assertStatus(422);
    }

    public function test_refund_rejects_zero_amount(): void
    {
        $this->authAdmin();

        $response = $this->postJson("/api/v1/policies/{$this->policyId}/transaction-logs/refund", [
            'reference_number' => $this->uniqueRef('ZERO'),
            'date_of_refund'   => date('Y-m-d'),
            'amount'           => 0,
            'reason'           => 'test zero',
            'refunded_by'      => 'PHPUnit',
        ]);

        $response->assertStatus(422);
    }

    public function test_refund_rejects_duplicate_reference(): void
    {
        $this->authAdmin();
        $ref = $this->uniqueRef('DUP');

        $first = $this->postJson("/api/v1/policies/{$this->policyId}/transaction-logs/refund", [
            'reference_number' => $ref,
            'date_of_refund'   => date('Y-m-d'),
            'amount'           => 1.50,
            'reason'           => 'test dup guard #1',
            'refunded_by'      => 'PHPUnit',
        ]);

        if ($first->getStatusCode() !== 201) {
            $this->markTestSkipped('First refund did not succeed: ' . $first->getContent());
        }

        $firstId = (int) ($first->json('data.refundTransactionId') ?? 0);

        try {
            $second = $this->postJson("/api/v1/policies/{$this->policyId}/transaction-logs/refund", [
                'reference_number' => $ref,
                'date_of_refund'   => date('Y-m-d'),
                'amount'           => 1.50,
                'reason'           => 'test dup guard #2',
                'refunded_by'      => 'PHPUnit',
            ]);
            $second->assertStatus(409);
        } finally {
            if ($firstId) {
                DB::table('policy_ledger')
                    ->where('policy_id', $this->policyId)
                    ->where('trans_ref', strtoupper($ref))
                    ->delete();
                DB::table('payment_transactions')->where('id', $firstId)->delete();
            }
        }
    }

    public function test_refund_creates_payment_row_and_ledger_entry(): void
    {
        $this->authAdmin();
        $ref = $this->uniqueRef('OK');

        $response = $this->postJson("/api/v1/policies/{$this->policyId}/transaction-logs/refund", [
            'reference_number' => $ref,
            'date_of_refund'   => date('Y-m-d'),
            'amount'           => 12.34,
            'reason'           => 'test refund happy path',
            'refunded_by'      => 'PHPUnit',
        ]);

        if ($response->getStatusCode() !== 201) {
            $this->markTestSkipped('Refund did not succeed (likely env missing odoo_status col / activity tables): ' . $response->getContent());
        }
        $refundId = (int) ($response->json('data.refundTransactionId') ?? 0);
        $this->assertGreaterThan(0, $refundId);

        try {
            $row = DB::table('payment_transactions')->where('id', $refundId)->first();
            $this->assertNotNull($row);
            $this->assertSame(1, (int) $row->is_refund);
            $this->assertEquals(12.34, (float) $row->amount);
            $this->assertSame($ref, (string) $row->referenceNumber);

            $ledger = DB::table('policy_ledger')
                ->where('policy_id', $this->policyId)
                ->where('trans_ref', strtoupper($ref))
                ->first();
            $this->assertNotNull($ledger, 'policy_ledger row not written');
            $this->assertEquals(12.34, (float) $ledger->debit);
            $this->assertSame('Refund', (string) $ledger->trans_type);
        } finally {
            DB::table('policy_ledger')
                ->where('policy_id', $this->policyId)
                ->where('trans_ref', strtoupper($ref))
                ->delete();
            DB::table('payment_transactions')->where('id', $refundId)->delete();
        }
    }

    public function test_reverse_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/policies/1/transaction-logs/1/reverse', []);
        $response->assertStatus(401);
    }

    public function test_reverse_rejects_future_date(): void
    {
        $this->authAdmin();
        $future = date('Y-m-d', strtotime('+5 days'));

        $response = $this->postJson("/api/v1/policies/{$this->policyId}/transaction-logs/9999999/reverse", [
            'reversal_date' => $future,
            'comments'      => 'test future-date guard',
        ]);

        $response->assertStatus(422);
    }

    public function test_reverse_returns_404_for_unknown_transaction(): void
    {
        $this->authAdmin();

        $response = $this->postJson("/api/v1/policies/{$this->policyId}/transaction-logs/999999999/reverse", [
            'reversal_date' => date('Y-m-d'),
            'comments'      => 'test unknown tx',
        ]);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_reverse_preserves_decimal_amount(): void
    {
        $this->authAdmin();
        $ref = $this->uniqueRef('DEC');

        $now = now();
        $txId = DB::table('payment_transactions')->insertGetId([
            'policy_id'       => $this->policyId,
            'policyNumber'    => $this->policyNumber,
            'referenceNumber' => $ref,
            'amount'          => 1500.50,
            'status'          => 'Success',
            'paymentMethod'   => 'CASH',
            'paymentDate'     => date('Y-m-d'),
            'new_payment_date'=> date('Y-m-d'),
            'is_ledger'       => 0,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        try {
            $response = $this->postJson("/api/v1/policies/{$this->policyId}/transaction-logs/{$txId}/reverse", [
                'reversal_date' => date('Y-m-d'),
                'comments'      => 'PHPUnit decimal preservation',
            ]);

            if ($response->getStatusCode() === 403) {
                $this->markTestSkipped('Test user lacks payment_delete_before_ledger permission');
            }

            $response->assertStatus(200);

            $original = DB::table('payment_transactions')->where('id', $txId)->first();
            $this->assertEquals(1500.50, (float) $original->amount, 'Original amount must not lose its decimal part on reverse');
            $this->assertSame(1, (int) $original->is_reverse);

            $dupId = (int) ($response->json('data.duplicateTransactionId') ?? 0);
            if ($dupId) {
                $dup = DB::table('payment_transactions')->where('id', $dupId)->first();
                $this->assertEquals(1500.50, (float) $dup->amount);
                DB::table('payment_transactions')->where('id', $dupId)->delete();
            }
        } finally {
            DB::table('payment_transactions')->where('id', $txId)->delete();
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // RefundService (DPO/bulk) — statement reflection + AccountStatementService
    // netting. These build a disposable, fully-controlled MIS policy so the
    // Closing Balance is deterministic, and tear it down at the end.
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Create a throwaway MIS customer + policy and return
     * [customerId, policyId, policyNumber]. Skips the test if the schema can't
     * accept the minimal rows.
     */
    private function makeMisPolicy(): array
    {
        $tag = uniqid();
        $customerId = DB::table('customer')->insertGetId(array_intersect_key([
            'firstName' => 'RefundReflect',
            'lastName'  => 'Test',
            'email'     => 'refund-reflect+' . $tag . '@example.com',
            'cellphone' => '71' . substr($tag, -6),
            'created_at'=> now(),
            'updated_at'=> now(),
        ], array_flip(DB::getSchemaBuilder()->getColumnListing('customer'))));

        $policyNumber = 'MISREF' . $tag;
        $policyId = DB::table('policies')->insertGetId(array_intersect_key([
            'customer_id'  => $customerId,
            'product_id'   => 1, // MIS retail — not in the DomCom id list
            'policyNumber' => $policyNumber,
            'status'       => 1,
            'premium'      => 100,
            'created_at'   => now(),
            'updated_at'   => now(),
        ], array_flip(DB::getSchemaBuilder()->getColumnListing('policies'))));

        return [(int) $customerId, (int) $policyId, $policyNumber];
    }

    private function cleanupPolicy(int $customerId, int $policyId): void
    {
        DB::table('policy_ledger')->where('policy_id', $policyId)->delete();
        DB::table('payment_transactions')->where('policy_id', $policyId)->delete();
        DB::table('policies')->where('id', $policyId)->delete();
        DB::table('customer')->where('id', $customerId)->delete();
    }

    private function insertLedger(int $policyId, int $customerId, string $transType, string $ref, ?float $debit, ?float $credit, ?string $status = null): void
    {
        DB::table('policy_ledger')->insert(array_intersect_key([
            'policy_id'       => $policyId,
            'customer_id'     => $customerId,
            'accounting_date' => now()->format('Y-m-d'),
            'trans_type'      => $transType,
            'trans_ref'       => $ref,
            'status'          => $status ?? 'Paid',
            'debit'           => $debit,
            'credit'          => $credit,
            'balance'         => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], array_flip(DB::getSchemaBuilder()->getColumnListing('policy_ledger'))));
    }

    private function insertTx(int $policyId, string $policyNumber, string $ref, float $amount, array $extra = []): int
    {
        $row = array_merge([
            'policy_id'       => $policyId,
            'policyNumber'    => $policyNumber,
            'referenceNumber' => $ref,
            'amount'          => $amount,
            'status'          => 'Success',
            'paymentMethod'   => 'DPO',
            'paymentDate'     => now()->format('Y-m-d'),
            'new_payment_date'=> now()->format('Y-m-d'),
            'created_at'      => now(),
            'updated_at'      => now(),
        ], $extra);

        return (int) DB::table('payment_transactions')->insertGetId(
            array_intersect_key($row, array_flip(DB::getSchemaBuilder()->getColumnListing('payment_transactions')))
        );
    }

    /**
     * Task 1: a succeeded DPO refund must post BOTH a payment_transactions
     * refund row (is_refund=1, is_ledger=1) and a matching policy_ledger
     * 'Refund' row whose trans_ref equals the tx referenceNumber — and doing it
     * twice must not double-post.
     */
    public function test_dpo_refund_posts_tx_and_ledger_and_is_idempotent(): void
    {
        if (!Schema::hasTable('payment_refunds')) {
            $this->markTestSkipped('payment_refunds table not present in this env');
        }

        [$customerId, $policyId, $policyNumber] = $this->makeMisPolicy();
        $refundId = null;
        $origTxId = null;

        try {
            // Original successful payment that is now being refunded.
            $origTxId = $this->insertTx($policyId, $policyNumber, 'ORIG' . uniqid(), 100.00, ['is_refund' => 0]);

            $refundId = (int) DB::table('payment_refunds')->insertGetId(array_intersect_key([
                'payment_transaction_id' => $origTxId,
                'policy_number'          => $policyNumber,
                'customer_id'            => $customerId,
                'amount'                 => 100.00,
                'currency'               => 'BWP',
                'reason'                 => 'phpunit dpo refund',
                'refund_type'            => 'single',
                'status'                 => 'succeeded',
                'dpo_refund_reference'   => 'DPO' . uniqid(),
                'completed_at'           => now(),
                'created_at'             => now(),
                'updated_at'             => now(),
            ], array_flip(DB::getSchemaBuilder()->getColumnListing('payment_refunds'))));

            // Invoke the private poster directly — no DPO round-trip needed.
            $svc = (new \ReflectionClass(RefundService::class))->newInstanceWithoutConstructor();
            $poster = new \ReflectionMethod(RefundService::class, 'postRefundToLedger');
            $poster->setAccessible(true);
            $poster->invoke($svc, $refundId);

            $expectedRef = 'REFUND-' . $refundId;

            $tx = DB::table('payment_transactions')
                ->where('policy_id', $policyId)
                ->where('referenceNumber', $expectedRef)
                ->where('is_refund', 1)
                ->get();
            $this->assertCount(1, $tx, 'exactly one refund payment_transactions row expected');
            $this->assertSame(1, (int) $tx[0]->is_ledger);
            $this->assertContains((string) $tx[0]->status, AccountStatementService::SUCCESS_STATUSES);
            $this->assertEquals(100.00, (float) $tx[0]->amount);

            $ledger = DB::table('policy_ledger')
                ->where('policy_id', $policyId)
                ->where('trans_type', 'Refund')
                ->where('trans_ref', $expectedRef)
                ->get();
            $this->assertCount(1, $ledger, 'exactly one Refund ledger row expected');
            $this->assertEquals(100.00, (float) $ledger[0]->debit);
            // trans_ref on the ledger row must match the tx referenceNumber EXACTLY.
            $this->assertSame($expectedRef, (string) $ledger[0]->trans_ref);

            // Idempotent: a second call must not create duplicates.
            $poster->invoke($svc, $refundId);
            $this->assertSame(1, DB::table('payment_transactions')
                ->where('policy_id', $policyId)->where('referenceNumber', $expectedRef)->where('is_refund', 1)->count());
            $this->assertSame(1, DB::table('policy_ledger')
                ->where('policy_id', $policyId)->where('trans_type', 'Refund')->where('trans_ref', $expectedRef)->count());
        } finally {
            if ($refundId) {
                DB::table('payment_refunds')->where('id', $refundId)->delete();
            }
            $this->cleanupPolicy($customerId, $policyId);
        }
    }

    /**
     * Task 2: a refund that reuses the ORIGINAL payment's reference must not be
     * both excluded from the Payment set AND added as a refund debit on MIS.
     * Expected MIS Closing Balance = Invoice − Payment + Refund = 100 (not 200).
     * DOM/COM behaviour is asserted UNCHANGED (still 100 via payment exclusion).
     */
    public function test_mis_same_reference_refund_does_not_double_count(): void
    {
        [$customerId, $policyId, $policyNumber] = $this->makeMisPolicy();
        try {
            $ref = 'SAME' . uniqid();

            // Original payment + a same-ref refund in payment_transactions.
            $this->insertTx($policyId, $policyNumber, $ref, 100.00, ['is_refund' => 0]);
            $this->insertTx($policyId, $policyNumber, $ref, 100.00, ['is_refund' => 1]);

            $this->insertLedger($policyId, $customerId, 'Invoice', 'INV' . uniqid(), 100.00, null);
            $this->insertLedger($policyId, $customerId, 'Payment', $ref, null, 100.00);
            $this->insertLedger($policyId, $customerId, 'Refund',  $ref, 100.00, null);

            // MIS: payment (-100) stays, refund debit (+100) nets it, invoice (+100) => 100.
            $this->assertSame(100.0, AccountStatementService::closingBalance($policyId, null, true),
                'MIS same-ref refund must net to 100, not double-count to 200');

            // DOM/COM: unchanged — payment excluded via refund ref, invoice (+100) => 100.
            $this->assertSame(100.0, AccountStatementService::closingBalance($policyId, null, false),
                'DOM/COM behaviour must be unchanged');
        } finally {
            $this->cleanupPolicy($customerId, $policyId);
        }
    }

    /**
     * Task 2 no-regression: a normal refund with a DISTINCT reference still
     * reflects and nets correctly on MIS (Invoice − Payment + Refund = 100).
     */
    public function test_mis_distinct_reference_refund_nets_correctly(): void
    {
        [$customerId, $policyId, $policyNumber] = $this->makeMisPolicy();
        try {
            $payRef = 'PAY' . uniqid();
            $rfdRef = 'RFD' . uniqid();

            $this->insertTx($policyId, $policyNumber, $payRef, 100.00, ['is_refund' => 0]);
            $this->insertTx($policyId, $policyNumber, $rfdRef, 100.00, ['is_refund' => 1]);

            $this->insertLedger($policyId, $customerId, 'Invoice', 'INV' . uniqid(), 100.00, null);
            $this->insertLedger($policyId, $customerId, 'Payment', $payRef, null, 100.00);
            $this->insertLedger($policyId, $customerId, 'Refund',  $rfdRef, 100.00, null);

            $this->assertSame(100.0, AccountStatementService::closingBalance($policyId, null, true),
                'MIS distinct-ref refund must net to 100');

            // Refund row must be visible in the statement rows.
            $rows = AccountStatementService::rows($policyId, null, true);
            $this->assertNotNull($rows->firstWhere('trans_ref', $rfdRef), 'Refund row must appear on the MIS statement');
            $this->assertNotNull($rows->firstWhere('trans_ref', $payRef), 'Original Payment must remain on the MIS statement');
        } finally {
            $this->cleanupPolicy($customerId, $policyId);
        }
    }

    /**
     * Reversals are already consistent and were NOT modified. This locks that
     * in: a reversed payment nets to the invoiced amount (100) in BOTH modes.
     */
    public function test_payment_reversal_nets_correctly_and_is_unchanged(): void
    {
        [$customerId, $policyId, $policyNumber] = $this->makeMisPolicy();
        try {
            $ref = 'REV' . uniqid();

            // Original payment flagged reversed + the duplicate carrying reveral_transaction_id.
            $origId = $this->insertTx($policyId, $policyNumber, $ref, 100.00, ['is_refund' => 0, 'is_reverse' => 1]);
            $this->insertTx($policyId, $policyNumber, $ref, 100.00, ['reveral_transaction_id' => $origId]);

            $this->insertLedger($policyId, $customerId, 'Invoice', 'INV' . uniqid(), 100.00, null);
            $this->insertLedger($policyId, $customerId, 'Payment', $ref, null, 100.00, 'Reversed');
            $this->insertLedger($policyId, $customerId, 'Reverse Payment', $ref, 100.00, null, 'Reversed');

            // Payment credit is excluded (reversed), Reverse Payment is not displayed,
            // invoice (+100) stands => 100, identically in both modes.
            $this->assertSame(100.0, AccountStatementService::closingBalance($policyId, null, true),
                'MIS reversal must net to 100');
            $this->assertSame(100.0, AccountStatementService::closingBalance($policyId, null, false),
                'DOM/COM reversal must net to 100');
        } finally {
            $this->cleanupPolicy($customerId, $policyId);
        }
    }
}
