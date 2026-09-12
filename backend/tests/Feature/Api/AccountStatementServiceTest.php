<?php

namespace Tests\Feature\Api;

use AlphaDirect\Customer;
use AlphaDirect\Services\AccountStatementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Statement of Account / Total Dues must include a successful payment
 * regardless of how the gateway cased its status string.
 *
 * payment_transactions.status casing is inconsistent across gateways —
 * VCS/Flutterwave/DPO write 'SUCCESS', Orange Money predominantly writes
 * 'Success', legacy rows use 'S'. AccountStatementService::rows() used to
 * filter on an exact `status = 'SUCCESS'`, silently dropping any payment
 * recorded with different casing from both the Statement of Account PDF
 * and the on-screen Total Dues figure for MIS policies — even though the
 * same payment is fully visible in Transaction Logs. This covers the fix:
 * SUCCESS_STATUSES now accepts all known casings.
 */
class AccountStatementServiceTest extends TestCase
{
    private ?int $customerId = null;
    private ?int $policyId = null;

    /**
     * Runs on in-memory sqlite, and FAILS LOUDLY against anything else.
     *
     * This file used to run against whatever .env resolves — which is
     * `Graphite_live`. It called Customer::create() and inserted into `policies`,
     * `payment_transactions` and `policy_ledger` on the PRODUCTION database, then
     * tidied up in tearDown. Two things wrong with that: a test that fails part
     * way through leaves orphan rows in the live customer and ledger tables, and
     * `Customer implements Auditable`, so every create also wrote an audit row to
     * mysql_system. On a machine that cannot reach prod it simply errored — all
     * three tests, 133s.
     *
     * The behaviour under test is arithmetic over four tables, so there is no
     * reason for it to touch a real database at all.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
            ],
            'database.connections.mysql_system' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
            ],
            'cache.default'                    => 'array',
            'audit.enabled'                    => false,
            'audit.drivers.database.connection' => 'sqlite',
        ]);

        DB::purge();
        Customer::disableAuditing();

        $conn = DB::connection();
        if ($conn->getDriverName() !== 'sqlite' || $conn->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing to run against ' . $conn->getDriverName() . ' / '
                . $conn->getDatabaseName() . ' — this file creates customers and ledger rows.');
        }

        $this->buildSchema();
    }

    protected function tearDown(): void
    {
        Customer::enableAuditing();
        parent::tearDown();
    }

    /**
     * Only the columns the service queries and the tests set. The tests already
     * intersect their payloads against getColumnListing(), so they adapt to this
     * without change.
     *
     * `policy_ledger_archive` is deliberately NOT created: rows() guards it with
     * Schema::hasTable because "some envs don't have it", and that absence is a
     * real production shape worth exercising.
     */
    private function buildSchema(): void
    {
        Schema::create('customer', function ($t) {
            $t->bigIncrements('id');
            $t->string('firstName')->nullable();
            $t->string('lastName')->nullable();
            $t->string('email')->nullable();
            $t->string('cellphone')->nullable();
            $t->timestamps();
        });

        Schema::create('policies', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('policyNumber')->nullable();
            $t->integer('status')->nullable();
            $t->decimal('premium', 15, 2)->nullable();
            $t->timestamps();
        });

        Schema::create('payment_transactions', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->string('policyNumber')->nullable();
            $t->string('referenceNumber')->nullable();
            $t->decimal('amount', 15, 2)->nullable();
            $t->string('status')->nullable();
            $t->string('paymentMethod')->nullable();
            $t->dateTime('paymentDate')->nullable();
            // The exclusion flags rows() reads. Each one is a way a payment can be
            // money that did NOT stay received.
            $t->tinyInteger('is_refund')->nullable();
            $t->tinyInteger('is_reverse')->nullable();
            $t->string('CompanyRef')->nullable();
            $t->unsignedBigInteger('reveral_transaction_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('policy_ledger', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('action_id')->nullable();
            $t->date('accounting_date')->nullable();
            $t->date('invoice_date')->nullable();
            $t->string('invoice_file')->nullable();
            $t->string('trans_type')->nullable();
            $t->string('trans_ref')->nullable();
            $t->string('status')->nullable();
            $t->decimal('credit', 15, 2)->nullable();
            $t->decimal('debit', 15, 2)->nullable();
            $t->decimal('balance', 15, 2)->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
    }

    public function test_mixed_case_success_status_payment_is_included_in_statement_rows_and_total_dues(): void
    {
        $customer = Customer::create([
            'firstName' => 'StatementCasing',
            'lastName'  => 'Test',
            'email'     => 'statement-casing-test+'.uniqid().'@example.com',
            'cellphone' => '71000002',
        ]);
        $this->customerId = $customer->id;

        $policiesCols = DB::getSchemaBuilder()->getColumnListing('policies');
        $policyRow = array_intersect_key([
            'customer_id'  => $customer->id,
            'product_id'   => 1, // MIS retail (ADI) — not in the DomCom id list
            'policyNumber' => 'MISTEST' . uniqid(),
            'status'       => 1,
            'premium'      => 100,
            'created_at'   => now(),
            'updated_at'   => now(),
        ], array_flip($policiesCols));
        $this->policyId = DB::table('policies')->insertGetId($policyRow);

        $ref = 'REF' . uniqid();

        // Orange Money writes 'Success' (mixed case), not 'SUCCESS'.
        $ptCols = DB::getSchemaBuilder()->getColumnListing('payment_transactions');
        $ptRow = array_intersect_key([
            'policy_id'      => $this->policyId,
            'policyNumber'   => $policyRow['policyNumber'] ?? null,
            'referenceNumber'=> $ref,
            'amount'         => 250,
            'status'         => 'Success',
            'paymentMethod'  => 'Orange Money',
            'paymentDate'    => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ], array_flip($ptCols));
        DB::table('payment_transactions')->insert($ptRow);

        $ledgerCols = DB::getSchemaBuilder()->getColumnListing('policy_ledger');
        $ledgerRow = array_intersect_key([
            'policy_id'       => $this->policyId,
            'customer_id'     => $customer->id,
            'accounting_date' => now()->format('Y-m-d'),
            'trans_type'      => 'Payment',
            'trans_ref'       => $ref,
            'status'          => 'Paid',
            'credit'          => 250,
            'balance'         => 250,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], array_flip($ledgerCols));
        DB::table('policy_ledger')->insert($ledgerRow);

        $rows = AccountStatementService::rows($this->policyId);
        $payment = $rows->firstWhere('trans_ref', $ref);

        $this->assertNotNull($payment, 'Payment with status=Success (mixed case) should appear in statement rows.');
        $this->assertSame('Payment', $payment->trans_type);

        // MIS: refundsRaiseBalance=true (closingBalance's 3rd arg) — no refund
        // here, so this just confirms the payment's credit is actually
        // subtracted into the running total rather than being silently 0.
        $totalDues = AccountStatementService::closingBalance($this->policyId, null, true);
        $this->assertSame(-250.0, $totalDues);
    }

    /**
     * CompanyRef is an overloaded gateway-reference field — on real
     * production rows it's sometimes just the policy's own number, not a
     * reversal marker. whereNull('CompanyRef') used to drop these
     * perfectly valid payments from the statement entirely.
     */
    public function test_companyref_populated_with_non_reversed_value_does_not_exclude_payment(): void
    {
        $customer = Customer::create([
            'firstName' => 'CompanyRefCasing', 'lastName' => 'Test',
            'email' => 'companyref-test+'.uniqid().'@example.com', 'cellphone' => '71000003',
        ]);
        $this->customerId = $customer->id;

        $policiesCols = DB::getSchemaBuilder()->getColumnListing('policies');
        $policyNumber = 'MISTEST' . uniqid();
        $policyRow = array_intersect_key([
            'customer_id' => $customer->id, 'product_id' => 1,
            'policyNumber' => $policyNumber, 'status' => 1, 'premium' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ], array_flip($policiesCols));
        $this->policyId = DB::table('policies')->insertGetId($policyRow);

        $ref = 'REF' . uniqid();
        $ptCols = DB::getSchemaBuilder()->getColumnListing('payment_transactions');
        DB::table('payment_transactions')->insert(array_intersect_key([
            'policy_id' => $this->policyId, 'policyNumber' => $policyNumber,
            'referenceNumber' => $ref, 'amount' => 49, 'status' => 'SUCCESS',
            // Real production rows: CompanyRef gets set to the policy's own
            // number by some gateway flows — not a reversal marker.
            'CompanyRef' => $policyNumber,
            'paymentDate' => now(), 'created_at' => now(), 'updated_at' => now(),
        ], array_flip($ptCols)));

        $ledgerCols = DB::getSchemaBuilder()->getColumnListing('policy_ledger');
        DB::table('policy_ledger')->insert(array_intersect_key([
            'policy_id' => $this->policyId, 'customer_id' => $customer->id,
            'accounting_date' => now()->format('Y-m-d'), 'trans_type' => 'Payment',
            'trans_ref' => $ref, 'status' => 'Paid', 'credit' => 49, 'balance' => 49,
            'created_at' => now(), 'updated_at' => now(),
        ], array_flip($ledgerCols)));

        $rows = AccountStatementService::rows($this->policyId);
        $payment = $rows->firstWhere('trans_ref', $ref);

        $this->assertNotNull($payment, 'Payment with CompanyRef=<policyNumber> should not be excluded — only CompanyRef=Reversed should exclude.');
    }

    /** The actual 'Reversed' sentinel must still exclude the payment. */
    public function test_companyref_reversed_sentinel_still_excludes_payment(): void
    {
        $customer = Customer::create([
            'firstName' => 'CompanyRefReversed', 'lastName' => 'Test',
            'email' => 'companyref-reversed-test+'.uniqid().'@example.com', 'cellphone' => '71000004',
        ]);
        $this->customerId = $customer->id;

        $policiesCols = DB::getSchemaBuilder()->getColumnListing('policies');
        $policyNumber = 'MISTEST' . uniqid();
        $policyRow = array_intersect_key([
            'customer_id' => $customer->id, 'product_id' => 1,
            'policyNumber' => $policyNumber, 'status' => 1, 'premium' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ], array_flip($policiesCols));
        $this->policyId = DB::table('policies')->insertGetId($policyRow);

        $ref = 'REF' . uniqid();
        $ptCols = DB::getSchemaBuilder()->getColumnListing('payment_transactions');
        DB::table('payment_transactions')->insert(array_intersect_key([
            'policy_id' => $this->policyId, 'policyNumber' => $policyNumber,
            'referenceNumber' => $ref, 'amount' => 49, 'status' => 'SUCCESS',
            'CompanyRef' => 'Reversed',
            'paymentDate' => now(), 'created_at' => now(), 'updated_at' => now(),
        ], array_flip($ptCols)));

        $rows = AccountStatementService::rows($this->policyId);
        $this->assertNull($rows->firstWhere('trans_ref', $ref), 'CompanyRef=Reversed must still exclude the payment.');
    }
}
