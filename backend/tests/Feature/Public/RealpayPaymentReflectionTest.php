<?php

namespace Tests\Feature\Public;

use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayReflectionException;
use AlphaDirect\Services\RealpayPaymentRecorder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A successful RealPay debit must always end up as a Graphite payment record —
 * or, when it cannot, as an open reconciliation item. Never as nothing.
 *
 * The reported failure was: RealPay creates the contract, debits the customer,
 * and no payment_transactions row appears. Every test here is one of the
 * concrete ways that happened, plus the invariants the fix has to hold:
 *
 *   1. plain success                — 'S' writes the payment
 *   2. ClientNumber with a /n suffix — the shape that used to miss the policy
 *   3. contract-only linkage        — ClientNumber is a quote number, and only
 *                                     realpay_client_contracts.policy_id knows
 *   4. unresolvable policy          — nothing written, exception ledgered,
 *                                     caller told it failed (so the webhook is
 *                                     retried rather than consumed)
 *   5. replay                       — a redelivered debit produces exactly ONE
 *                                     payment row (no duplicate debit record)
 *   6. pending instalment           — 'W' settles nothing and raises nothing
 *   7. recovery                     — a later successful delivery closes the
 *                                     open exception
 *   8. failed instalment            — 'F' is recorded as FAILED, not SUCCESS
 *
 * Runs against an in-memory SQLite schema. The recorder makes no outbound
 * RealPay call, so a throwaway database exercises it completely — and it keeps
 * a test that writes payment rows off a live database.
 */
class RealpayPaymentReflectionTest extends TestCase
{
    /**
     * PaymentTransaction pins `protected $connection = 'mysql'`, while Policy
     * and the RealPay models follow the default. A separate test connection
     * would put them in two different `:memory:` databases, so the throwaway
     * SQLite is registered under the name 'mysql' itself and made the default —
     * one database, every model in it. The real config is restored in tearDown.
     */
    private const CONNECTION = 'mysql';

    private RealpayPaymentRecorder $recorder;
    private string $previousDefault;
    private $previousMysqlConfig;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('audit.enabled', false);
        Policy::disableAuditing();
        PaymentTransaction::disableAuditing();
        RealpayClientContracts::disableAuditing();
        RealpayContractInstallments::disableAuditing();

        $this->previousDefault     = (string) Config::get('database.default');
        $this->previousMysqlConfig = Config::get('database.connections.' . self::CONNECTION);

        Config::set('database.connections.' . self::CONNECTION, [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => false,
        ]);
        Config::set('database.default', self::CONNECTION);
        DB::purge(self::CONNECTION);

        $this->buildSchema();

        $this->recorder = new RealpayPaymentRecorder();
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        Config::set('database.connections.' . self::CONNECTION, $this->previousMysqlConfig);
        Config::set('database.default', $this->previousDefault);

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────
    // 1. The ordinary case
    // ──────────────────────────────────────────────────────────────

    public function test_successful_instalment_writes_a_payment_record(): void
    {
        $policy = $this->makePolicy('MIS2026215341');

        $result = $this->recorder->record($this->webhook([
            'client_number'   => 'MIS2026215341',
            'contract_number' => $policy->id . '/1',
        ]));

        $this->assertTrue($result['ok']);
        $this->assertSame('recorded', $result['outcome']);

        $payment = PaymentTransaction::where('referenceNumber', '10524831620001')->first();
        $this->assertNotNull($payment, 'A collected RealPay instalment must produce a payment record.');
        $this->assertSame('SUCCESS', $payment->status);
        $this->assertSame('RealPay', $payment->paymentMethod);
        $this->assertSame('MIS2026215341', $payment->policyNumber);
        $this->assertSame((int) $policy->id, (int) $payment->policy_id);
        $this->assertEqualsWithDelta(288.04, (float) $payment->amount, 0.001);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. The shape that dropped payments
    // ──────────────────────────────────────────────────────────────

    public function test_client_number_carrying_a_contract_suffix_still_resolves_the_policy(): void
    {
        // updateInstallment() wrote the raw ClientNumber into
        // payment_transactions.policyNumber. 'MIS…/1' matches no policy, so the
        // write failed and the debit was never reflected.
        $policy = $this->makePolicy('MIS2026215350');

        $result = $this->recorder->record($this->webhook([
            'client_number'   => 'MIS2026215350/1',
            'contract_number' => $policy->id . '/1',
            'instalment_reference' => 'REF-SUFFIX-001',
        ]));

        $this->assertTrue($result['ok'], 'A /n-suffixed ClientNumber must still resolve to its policy.');

        $payment = PaymentTransaction::where('referenceNumber', 'REF-SUFFIX-001')->first();
        $this->assertNotNull($payment);
        // The policy's own number — never the raw webhook string.
        $this->assertSame('MIS2026215350', $payment->policyNumber);
        $this->assertSame((int) $policy->id, (int) $payment->policy_id);
    }

    public function test_policy_resolves_through_the_contract_table_when_the_client_number_is_a_quote(): void
    {
        // Contracts created before the policy exists (the public quote journey)
        // carry the quote number as ClientNumber. Only the contract row knows
        // which policy they became.
        $policy = $this->makePolicy('MIS2026214543');
        RealpayClientContracts::create([
            'policy_id'       => $policy->id,
            'client_number'   => 'MQ-20260430-AB12CD',
            'contract_number' => 'MQ-20260430-AB12CD/1',
            'status'          => 1,
        ]);

        $result = $this->recorder->record($this->webhook([
            'client_number'        => 'MQ-20260430-AB12CD',
            'contract_number'      => 'MQ-20260430-AB12CD/1',
            'instalment_reference' => 'REF-QUOTE-001',
        ]));

        $this->assertTrue($result['ok']);
        $this->assertSame('MIS2026214543', $result['policy_number']);
        $this->assertSame(1, PaymentTransaction::where('referenceNumber', 'REF-QUOTE-001')->count());
    }

    // ──────────────────────────────────────────────────────────────
    // 3. Failure must be visible, never silent
    // ──────────────────────────────────────────────────────────────

    public function test_unresolvable_policy_writes_no_payment_and_raises_a_reconciliation_item(): void
    {
        $result = $this->recorder->record($this->webhook([
            'client_number'        => 'UNKNOWN-CLIENT-999',
            'contract_number'      => 'UNKNOWN-CLIENT-999/1',
            'instalment_reference' => 'REF-ORPHAN-001',
        ]));

        // The caller must learn this failed — that is what keeps the buffered
        // webhook queued instead of being marked processed and discarded.
        $this->assertFalse($result['ok']);
        $this->assertSame('policy_unresolved', $result['outcome']);

        $this->assertSame(0, PaymentTransaction::where('referenceNumber', 'REF-ORPHAN-001')->count(),
            'An unattributable debit must not be written against a guessed policy.');

        $exception = RealpayReflectionException::where('instalment_reference', 'REF-ORPHAN-001')->first();
        $this->assertNotNull($exception, 'A debit that could not be reflected must become an open item.');
        $this->assertSame(RealpayReflectionException::REASON_POLICY_UNRESOLVED, $exception->reason);
        $this->assertNull($exception->resolved_at);
        $this->assertEqualsWithDelta(288.04, (float) $exception->amount, 0.001);
        // The payload survives, so the payment can be rebuilt from this row alone.
        $this->assertStringContainsString('UNKNOWN-CLIENT-999', (string) $exception->payload);
    }

    public function test_repeated_failures_bump_one_row_rather_than_growing_the_ledger(): void
    {
        $context = $this->webhook([
            'client_number'        => 'UNKNOWN-CLIENT-998',
            'contract_number'      => 'UNKNOWN-CLIENT-998/1',
            'instalment_reference' => 'REF-ORPHAN-002',
        ]);

        $this->recorder->record($context);
        $this->recorder->record($context);
        $this->recorder->record($context);

        $rows = RealpayReflectionException::where('instalment_reference', 'REF-ORPHAN-002')->get();
        $this->assertCount(1, $rows);
        $this->assertSame(3, (int) $rows->first()->occurrences);
    }

    // ──────────────────────────────────────────────────────────────
    // 4. Idempotency — the property that prevents a double record
    // ──────────────────────────────────────────────────────────────

    public function test_a_redelivered_debit_produces_exactly_one_payment_record(): void
    {
        $policy = $this->makePolicy('MIS2026215336');
        $context = $this->webhook([
            'client_number'        => 'MIS2026215336',
            'contract_number'      => $policy->id . '/1',
            'instalment_reference' => 'REF-REPLAY-001',
        ]);

        $first  = $this->recorder->record($context);
        $second = $this->recorder->record($context);
        $third  = $this->recorder->record($context);

        $this->assertSame('recorded',  $first['outcome']);
        $this->assertSame('duplicate', $second['outcome']);
        $this->assertSame('duplicate', $third['outcome']);
        $this->assertTrue($second['ok'], 'A replay is a successful no-op, not a failure.');

        $this->assertSame(1, PaymentTransaction::where('referenceNumber', 'REF-REPLAY-001')->count(),
            'The every-minute buffer replay must never duplicate a payment.');
        $this->assertSame(1, RealpayContractInstallments::where('InstalmentReferenceNumber', 'REF-REPLAY-001')->count());
    }

    public function test_an_existing_installment_row_is_updated_not_duplicated(): void
    {
        $policy = $this->makePolicy('MIS2026215335');
        $existing = new RealpayContractInstallments();
        $existing->policy_id                 = $policy->id;
        $existing->clientNumber              = 'MIS2026215335';
        $existing->contractNumber            = $policy->id . '/1';
        $existing->InstalmentReferenceNumber = 'REF-EXISTING-001';
        $existing->InstalmentSequence        = '1';
        $existing->InstalmentAmount          = '288.04';
        $existing->InstalmentStatus          = 'A';   // scheduled, not yet collected
        $existing->save();

        $this->recorder->record($this->webhook([
            'client_number'        => 'MIS2026215335',
            'contract_number'      => $policy->id . '/1',
            'instalment_reference' => 'REF-EXISTING-001',
        ]));

        $rows = RealpayContractInstallments::where('InstalmentReferenceNumber', 'REF-EXISTING-001')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('S', $rows->first()->InstalmentStatus);
    }

    // ──────────────────────────────────────────────────────────────
    // 5. Only settled outcomes become payments
    // ──────────────────────────────────────────────────────────────

    public function test_a_pending_instalment_settles_nothing_and_raises_nothing(): void
    {
        $policy = $this->makePolicy('MIS2026215334');

        $result = $this->recorder->record($this->webhook([
            'client_number'        => 'MIS2026215334',
            'contract_number'      => $policy->id . '/1',
            'instalment_reference' => 'REF-PENDING-001',
            'instalment_status'    => 'W',
        ]));

        $this->assertTrue($result['ok']);
        $this->assertSame('not_settling', $result['outcome']);
        $this->assertSame(0, PaymentTransaction::where('referenceNumber', 'REF-PENDING-001')->count());
        $this->assertSame(0, RealpayReflectionException::where('instalment_reference', 'REF-PENDING-001')->count(),
            'A still-processing instalment is not a reconciliation exception.');
    }

    public function test_a_declined_instalment_is_recorded_as_failed(): void
    {
        $policy = $this->makePolicy('MIS2026215333');

        $this->recorder->record($this->webhook([
            'client_number'        => 'MIS2026215333',
            'contract_number'      => $policy->id . '/1',
            'instalment_reference' => 'REF-FAILED-001',
            'instalment_status'    => 'F',
        ]));

        $payment = PaymentTransaction::where('referenceNumber', 'REF-FAILED-001')->first();
        $this->assertNotNull($payment);
        $this->assertSame('FAILED', $payment->status);
        $this->assertSame('0', (string) $payment->numberOfInstalmentsPaid);
    }

    // ──────────────────────────────────────────────────────────────
    // 6. Recovery closes the loop
    // ──────────────────────────────────────────────────────────────

    public function test_a_later_successful_delivery_closes_the_open_exception(): void
    {
        // First delivery: the policy does not exist yet (materialisation had not
        // run), so the debit is ledgered as an open item.
        $context = $this->webhook([
            'client_number'        => 'MIS2026215332',
            'contract_number'      => 'MIS2026215332/1',
            'instalment_reference' => 'REF-RECOVER-001',
        ]);

        $this->recorder->record($context);
        $this->assertSame(1, RealpayReflectionException::where('instalment_reference', 'REF-RECOVER-001')->open()->count());

        // The policy now exists; the same payload is replayed.
        $this->makePolicy('MIS2026215332');
        $result = $this->recorder->record($context);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, PaymentTransaction::where('referenceNumber', 'REF-RECOVER-001')->count());
        $this->assertSame(0, RealpayReflectionException::where('instalment_reference', 'REF-RECOVER-001')->open()->count(),
            'Once the payment is on the books the exception must stop being outstanding.');
    }

    public function test_a_debit_with_no_reference_number_is_ledgered_rather_than_written(): void
    {
        // Without RealPay's reference there is no idempotency key, so writing
        // would risk a duplicate payment on the next delivery.
        $policy = $this->makePolicy('MIS2026215331');

        $result = $this->recorder->record($this->webhook([
            'client_number'        => 'MIS2026215331',
            'contract_number'      => $policy->id . '/1',
            'instalment_reference' => '',
        ]));

        $this->assertFalse($result['ok']);
        $this->assertSame('no_reference', $result['outcome']);
        $this->assertSame(0, PaymentTransaction::count());
    }

    // ──────────────────────────────────────────────────────────────
    // Fixtures
    // ──────────────────────────────────────────────────────────────

    /** A RealPay instalment webhook body, flattened to recorder context. */
    private function webhook(array $overrides = []): array
    {
        return array_merge([
            'client_number'        => 'MIS2026215341',
            'contract_number'      => '215341/1',
            'instalment_reference' => '10524831620001',
            'sequence'             => '1',
            'instalment_status'    => 'S',
            'amount'               => '288.04',
            'action_date'          => '2026-07-24',
            'tracking_code'        => '44',
            'instalment_response'  => 'Success',
            'payload'              => ['ResponseCode' => '00'],
        ], $overrides);
    }

    private function makePolicy(string $policyNumber): Policy
    {
        $policy = new Policy();
        $policy->policyNumber = $policyNumber;
        $policy->product_id   = 2;
        $policy->customer_id  = 1;
        $policy->status       = 0;
        $policy->save();

        return $policy;
    }

    private function buildSchema(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->create('policies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->integer('status')->nullable();
            // Policy's model hooks stamp the term/expiry columns on save.
            $table->date('term_start_date')->nullable();
            $table->date('term_end_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('policyActivatedDate')->nullable();
            $table->timestamps();
        });

        $schema->create('payment_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('referenceNumber');
            $table->string('amount')->nullable();
            $table->string('status')->nullable();
            $table->string('paymentDate')->nullable();
            $table->date('new_payment_date')->nullable();
            $table->string('paymentMethod')->nullable();
            $table->string('note', 500)->nullable();
            $table->integer('is_ledger')->nullable()->default(0);
            $table->string('numberOfInstalmentsPaid')->nullable();
            $table->string('paymentFrequency')->nullable();
            $table->integer('request_type')->nullable()->default(0);
            $table->string('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('realpay_client_contracts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('client_number')->nullable();
            $table->string('contract_number')->nullable();
            $table->string('cellphone')->nullable();
            $table->integer('status')->nullable();
            $table->timestamps();
        });

        $schema->create('realpay_contract_installments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('clientNumber')->nullable();
            $table->string('contractNumber')->nullable();
            $table->string('InstalmentReferenceNumber')->nullable();
            $table->string('InstalmentSequence')->nullable();
            $table->string('CTCAmount')->nullable();
            $table->string('InstalmentActionDate')->nullable();
            $table->string('TrackingCode')->nullable();
            $table->string('InstalmentAmount')->nullable();
            $table->string('InstalmentStatus')->nullable();
            $table->string('instalmentResponse')->nullable();
            $table->integer('retry_count')->nullable()->default(0);
            $table->timestamps();
        });

        $schema->create('realpay_reflection_exceptions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('instalment_reference');
            $table->string('instalment_sequence')->nullable();
            $table->string('client_number')->nullable();
            $table->string('contract_number')->nullable();
            $table->string('policy_number')->nullable();
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('instalment_status')->nullable();
            $table->decimal('amount', 13, 2)->nullable();
            $table->date('action_date')->nullable();
            $table->string('reason');
            $table->text('error')->nullable();
            $table->text('payload')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable();
            $table->timestamps();
        });
    }
}
