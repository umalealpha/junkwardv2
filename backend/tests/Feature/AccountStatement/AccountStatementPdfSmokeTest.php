<?php

namespace Tests\Feature\AccountStatement;

use AlphaDirect\Services\AccountStatementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * End-to-end smoke cover for GET /api/v1/policies/{id}/account-statement-pdf —
 * the route behind the "Export Account Statement (PDF)" button.
 *
 * RefundBalanceTest covers the row set and the balance arithmetic in isolation.
 * This walks the whole path a user's click takes: route -> auth:sanctum ->
 * PolicyCreateController::generateAccountStatementPdf -> blade -> PDF bytes,
 * and asserts the refund a customer was shown on screen actually reaches the
 * exported document.
 *
 * Reported on policy 79959 (product 4): a P 49.00 refund with trans_ref ''
 * printed in the Account View tab and was silently missing from the PDF,
 * because the Refund branch required trans_ref to name a valid payment.
 *
 * SAFETY — the same two hazards as RefundBalanceTest:
 *
 *  1. backend/.env's default connection points at a shared RDS and phpunit.xml's
 *     sqlite lines are commented out. setUp() forcibly rebinds the "sqlite"
 *     connection to :memory:, makes it the default, and FAILS LOUDLY if the
 *     resulting connection isn't actually sqlite :memory:.
 *
 *  2. Auditing writes to the separate 'mysql_system' connection, which falls
 *     back to the same shared host when DB_HOST_SYSTEM is unset. Neutralised
 *     via audit.enabled=false and audit.drivers.database.connection=sqlite.
 *     Policy IS an Auditable model here (unlike RefundBalanceTest, which only
 *     touches DB::table()), so this one genuinely matters.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit tests/Feature/AccountStatement/AccountStatementPdfSmokeTest.php
 */
class AccountStatementPdfSmokeTest extends TestCase
{
    private const POLICY_ID   = 79959;
    private const CUSTOMER_ID = 4242;

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
        $this->seedPolicy();
    }

    private function buildSchema(): void
    {
        Schema::create('policy_ledger', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->integer('action_id')->nullable();
            $t->integer('customer_id')->nullable();
            $t->string('trans_type')->nullable();
            $t->string('trans_ref')->nullable();
            $t->string('orig_trans')->nullable();
            $t->string('invoice_no')->nullable();
            $t->string('invoice_file')->nullable();
            $t->string('status')->nullable();
            $t->date('invoice_date')->nullable();
            $t->date('accounting_date')->nullable();
            $t->date('due_date')->nullable();
            $t->timestamp('system_date')->nullable();
            $t->decimal('invoice_amount', 15, 2)->nullable();
            $t->decimal('unallocated', 15, 2)->nullable();
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
            $t->string('status')->nullable();
            $t->string('CompanyRef')->nullable();
            $t->integer('is_refund')->nullable();
            $t->integer('is_reverse')->nullable();
            $t->string('reveral_transaction_id')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('policies', function ($t) {
            $t->increments('id');
            $t->integer('customer_id')->nullable();
            $t->integer('product_id')->nullable();
            $t->string('policyNumber')->nullable();
            $t->integer('premium_freq')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('products', function ($t) {
            $t->increments('id');
            $t->string('name')->nullable();
        });

        Schema::create('customer', function ($t) {
            $t->increments('id');
            $t->string('firstName')->nullable();
            $t->string('lastName')->nullable();
            $t->string('email')->nullable();
            $t->string('cellphone')->nullable();
        });

        Schema::create('customer_profile', function ($t) {
            $t->increments('id');
            $t->integer('customer_id')->nullable();
            $t->integer('company_id')->nullable();
            $t->string('entity_type')->nullable();
            $t->string('address')->nullable();
        });

        // The blade resolves an originating invoice number for every non-Invoice
        // row by looking its trans_ref up as a credit-note number, so the table
        // has to exist even on a policy that has no credit notes.
        Schema::create('credit_note', function ($t) {
            $t->increments('id');
            $t->string('credit_note_no')->nullable();
            $t->integer('invoice_id')->nullable();
        });
    }

    /**
     * assertOk() on a 500 just prints "expected 200, got 500". The endpoint
     * catches its own exceptions and returns the reason as JSON, so surface it
     * — otherwise every render regression costs a debugging round-trip.
     */
    private function assertPdfOk(\Illuminate\Testing\TestResponse $response): void
    {
        if ($response->status() !== 200) {
            $this->fail('Export returned ' . $response->status() . ': '
                . substr((string) $response->getContent(), 0, 1000));
        }
    }

    private function seedPolicy(): void
    {
        DB::table('products')->insert(['id' => 4, 'name' => 'Legal']);
        // Deliberately a fabricated customer — no real Omang, address or
        // contact detail belongs in a test fixture.
        DB::table('customer')->insert([
            'id'         => self::CUSTOMER_ID,
            'firstName'  => 'Test',
            'lastName'   => 'Customer',
            'email'      => 'test.customer@example.invalid',
            'cellphone'  => '00000000',
        ]);
        DB::table('customer_profile')->insert([
            'id'          => 1,
            'customer_id' => self::CUSTOMER_ID,
            'company_id'  => null,
            'entity_type' => 'Individual',
            'address'     => 'Test Address',
        ]);
        DB::table('policies')->insert([
            'id'           => self::POLICY_ID,
            'customer_id'  => self::CUSTOMER_ID,
            'product_id'   => 4,           // Legal — MIS, uses account_statement blade
            'policyNumber' => 'MIS2024TEST79959',
            'premium_freq' => 12,
        ]);
    }

    private function ledger(string $type, ?string $ref, ?float $debit, ?float $credit, string $date): void
    {
        DB::table('policy_ledger')->insert([
            'policy_id'       => self::POLICY_ID,
            'customer_id'     => self::CUSTOMER_ID,
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

    private function tx(string $ref, float $amount, string $status = 'SUCCESS'): void
    {
        DB::table('payment_transactions')->insert([
            'policy_id'       => self::POLICY_ID,
            'policyNumber'    => 'MIS2024TEST79959',
            'referenceNumber' => $ref,
            'amount'          => $amount,
            'status'          => $status,
            'is_refund'       => 0,
        ]);
    }

    /** Authenticate against the same guard the route's middleware uses. */
    private function asUser(): self
    {
        $user = (new \AlphaDirect\User)->forceFill(['id' => 1, 'email' => 'smoke@example.invalid']);
        $user->exists = true;

        return $this->actingAs($user, 'sanctum');
    }

    /** The statement HTML the PDF is rendered from, via the service row set. */
    private function renderedStatementHtml(): string
    {
        $policy = \AlphaDirect\Policy::find(self::POLICY_ID);

        return view('admin.notes.account_statement', [
            'data'                  => AccountStatementService::rows(self::POLICY_ID)->values()->all(),
            'balance'               => 0,
            'opening_balance'       => 0,
            'sumInvoiceAmount'      => 0,
            'customer'              => \AlphaDirect\Customer::find(self::CUSTOMER_ID),
            'customer_profile'      => \AlphaDirect\CustomerProfile::where('customer_id', self::CUSTOMER_ID)->first(),
            'company'               => null,
            'policy'                => $policy,
            'policyNumber'          => $policy->policyNumber,
            'premium_freq'          => $policy->premium_freq,
            'final_invoiced_amount' => 0,
            'final_payment'         => 0,
        ])->render();
    }

    /**
     * The reported bug, end to end: click Export with a refund on the policy
     * and the refund must be in the document.
     */
    public function test_export_endpoint_returns_a_pdf_containing_the_refund(): void
    {
        $this->tx('ae1d6a77-6ad1-4f57-aea7-8d75d28e0624', 294.00);

        $this->ledger('Invoice', 'INV-1', 294.00, null, '2024-01-01');
        $this->ledger('Payment', 'ae1d6a77-6ad1-4f57-aea7-8d75d28e0624', null, 294.00, '2024-02-01');
        // The exact shape from policy 79959: refund booked with an empty ref.
        $this->ledger('Refund',  '', 49.00, null, '2024-10-30');

        $response = $this->asUser()->get('/api/v1/policies/' . self::POLICY_ID . '/account-statement-pdf');

        $this->assertPdfOk($response);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF', $body, 'Endpoint must return real PDF bytes');
        $this->assertGreaterThan(1000, strlen($body), 'PDF looks empty');

        // The bytes are compressed, so assert the refund on the HTML the PDF is
        // rendered from — same data, same blade, readable.
        $html = $this->renderedStatementHtml();
        $this->assertStringContainsString('Refund', $html);
        $this->assertStringContainsString('49.00', $html, 'The refunded amount must print on the statement');
    }

    /** The document must agree with the Account View tab it was exported from. */
    public function test_exported_document_matches_the_on_screen_ledger(): void
    {
        $this->tx('PAY-1', 294.00);

        $this->ledger('Invoice', 'INV-1', 294.00, null, '2024-01-01');
        $this->ledger('Payment', 'PAY-1', null, 294.00, '2024-02-01');
        $this->ledger('Refund',  '',                   49.00, null, '2024-10-30');
        $this->ledger('Refund',  'REFUND-BACKLOG-2026', 98.00, null, '2024-04-10');

        $onScreen = DB::table('policy_ledger')
            ->where('policy_id', self::POLICY_ID)
            ->where('trans_type', 'Refund')
            ->whereNull('deleted_at')
            ->count();

        $exported = AccountStatementService::rows(self::POLICY_ID)
            ->where('trans_type', 'Refund')
            ->count();

        $this->assertSame(2, $onScreen, 'fixture sanity');
        $this->assertSame(
            $onScreen,
            $exported,
            'Every refund the Account View shows must also reach the exported statement'
        );

        $this->assertPdfOk($this->asUser()
            ->get('/api/v1/policies/' . self::POLICY_ID . '/account-statement-pdf'));
    }

    /**
     * Guard on the fix: restoring un-referenced refunds must not turn into
     * "print every refund". One booked against a payment the statement already
     * discarded stays out.
     */
    public function test_refund_against_a_discarded_payment_is_not_exported(): void
    {
        $this->tx('PAY-1', 294.00);
        $this->tx('BAD-1', 49.00, 'Failed');

        $this->ledger('Invoice', 'INV-1', 294.00, null, '2024-01-01');
        $this->ledger('Payment', 'PAY-1', null, 294.00, '2024-02-01');
        $this->ledger('Refund',  'BAD-1', 49.00, null, '2024-10-30');

        $this->assertSame(
            0,
            AccountStatementService::rows(self::POLICY_ID)->where('trans_type', 'Refund')->count(),
            'A refund hanging off a failed payment must stay off the statement'
        );

        $this->assertPdfOk($this->asUser()
            ->get('/api/v1/policies/' . self::POLICY_ID . '/account-statement-pdf'));
    }

    /** A refund-free policy must export exactly as it did before the fix. */
    public function test_policy_without_refunds_still_exports(): void
    {
        $this->tx('PAY-1', 294.00);

        $this->ledger('Invoice', 'INV-1', 294.00, null, '2024-01-01');
        $this->ledger('Payment', 'PAY-1', null, 294.00, '2024-02-01');

        $response = $this->asUser()->get('/api/v1/policies/' . self::POLICY_ID . '/account-statement-pdf');

        $this->assertPdfOk($response);
        $this->assertSame(0.00, AccountStatementService::closingBalance(self::POLICY_ID));
        $this->assertStringNotContainsString(
            'REFUND-BACKLOG',
            $this->renderedStatementHtml(),
            'No refund row should appear on a refund-free policy'
        );
    }

    /**
     * SCOPE GUARD. The fix is MIS-only by decision. Move the same policy and the
     * same ledger rows onto the DomCom tier and the un-referenced refund must
     * still be absent from the export, so DomCom Total Dues do not move.
     */
    public function test_domcom_export_still_excludes_an_unreferenced_refund(): void
    {
        DB::table('products')->insert(['id' => 7, 'name' => 'Commercial Insurance']);
        DB::table('policies')->where('id', self::POLICY_ID)->update([
            'product_id'   => 7,
            'policyNumber' => 'COMG2024TEST79959',
        ]);

        $this->tx('PAY-1', 294.00);

        $this->ledger('Invoice', 'INV-1', 294.00, null, '2024-01-01');
        $this->ledger('Payment', 'PAY-1', null, 294.00, '2024-02-01');
        $this->ledger('Refund',  '', 49.00, null, '2024-10-30');

        $this->assertSame(
            0,
            AccountStatementService::rows(self::POLICY_ID)->where('trans_type', 'Refund')->count(),
            'DomCom must keep excluding refunds that name no valid payment'
        );

        $this->assertPdfOk(
            $this->asUser()->get('/api/v1/policies/' . self::POLICY_ID . '/account-statement-pdf')
        );
    }

    /** The endpoint is behind auth:sanctum — unauthenticated callers get nothing. */
    public function test_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/policies/' . self::POLICY_ID . '/account-statement-pdf')
            ->assertUnauthorized();
    }
}
