<?php

namespace Tests\Feature\Public;

use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\Services\PayNowService;
use AlphaDirect\Services\RealpayService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The REPROCESSING half of "RealPay debited the customer, Graphite has no
 * transaction".
 *
 * RealpayWebhookReflectionSmokeTest covers the recurring-collection webhook.
 * This covers "Collect Now" (PayNowService), which is the other way a RealPay
 * debit is initiated — and which settles differently: RealPay only ACCEPTS the
 * one-off instalment synchronously, and the bank result arrives on the webhook
 * up to a day later.
 *
 * Two defects specific to that asynchrony are asserted here:
 *
 *   1. settleSelection() parks collected premiums at scheduled_transactions
 *      status 1, which is still in OUTSTANDING_STATUSES. They become selectable
 *      again immediately, and claimSelection() only guards genuinely concurrent
 *      clicks — so a second Collect Now before the webhook lands sent RealPay a
 *      second OOFF post and debited the customer twice.
 *
 *   2. The credential platform was hardcoded to legacy. Instant MIS products
 *      (the family the four reported policies belong to) hold their contracts
 *      on the START merchant, which the legacy merchant cannot see.
 *
 * Runs against a throwaway file-backed SQLite database, for the same reason the
 * webhook smoke test does: the code under test spans three connection names.
 * No test here reaches the network — realpay.base_url is pointed at the discard
 * port so the OAuth call fails immediately and locally.
 */
class RealpayCollectNowReflectionTest extends TestCase
{
    private const CONNECTIONS = ['mysql', 'mysql_write', 'mysql_system'];

    private string $dbPath;
    private array $previousConfig = [];
    private string $previousDefault;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('audit.enabled', false);
        Policy::disableAuditing();
        PaymentTransaction::disableAuditing();
        RealpayClientContracts::disableAuditing();
        RealpayContractInstallments::disableAuditing();

        // Any RealPay call must fail locally and instantly. Port 9 (discard) is
        // refused rather than routed, so no test can reach a real merchant.
        Config::set('realpay.base_url', 'http://127.0.0.1:9');
        Config::set('realpay.start.base_url', 'http://127.0.0.1:9');
        Config::set('realpay.collect_now_inflight_hours', 72);

        $this->dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rp_collectnow_' . getmypid() . '.sqlite';
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
        touch($this->dbPath);

        $sqlite = [
            'driver'                  => 'sqlite',
            'database'                => $this->dbPath,
            'prefix'                  => '',
            'foreign_key_constraints' => false,
        ];

        $this->previousDefault = (string) Config::get('database.default');
        foreach (self::CONNECTIONS as $name) {
            $this->previousConfig[$name] = Config::get('database.connections.' . $name);
            Config::set('database.connections.' . $name, $sqlite);
            DB::purge($name);
        }
        Config::set('database.default', 'mysql');

        $this->buildSchema();
    }

    protected function tearDown(): void
    {
        foreach (self::CONNECTIONS as $name) {
            DB::purge($name);
            Config::set('database.connections.' . $name, $this->previousConfig[$name]);
        }
        Config::set('database.default', $this->previousDefault);

        if (file_exists($this->dbPath)) {
            @unlink($this->dbPath);
        }

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────
    // The platform split
    // ──────────────────────────────────────────────────────────────

    public function test_instant_mis_products_resolve_to_the_start_platform(): void
    {
        // The four reported policies are MIS instant products; their contracts
        // live on the START merchant (19413).
        foreach ([1, 2, 4, 5, 6, 9, 10] as $productId) {
            $this->assertSame('start', RealpayService::platformForProduct($productId),
                "Instant product {$productId} must collect on the START platform.");
        }

        // Motor comprehensive and the DOMG/COMG family stay on legacy.
        foreach ([3, 7, 8, 16, 20, 22] as $productId) {
            $this->assertSame('legacy', RealpayService::platformForProduct($productId),
                "Product {$productId} must stay on the legacy platform.");
        }
    }

    public function test_platform_config_reads_the_selected_platforms_credentials(): void
    {
        Config::set('realpay.merchant', '16244');
        Config::set('realpay.start.merchant', '19413');

        $legacy = (new RealpayService())->usePlatform('legacy');
        $start  = (new RealpayService())->usePlatform('start');

        $this->assertSame('16244', $legacy->platformConfig('merchant'));
        $this->assertSame('19413', $start->platformConfig('merchant'));

        // FNB's product code is shared across both platforms (V8 parity).
        Config::set('realpay.fnb_product', 'FNBNDOBW');
        $this->assertSame('FNBNDOBW', $start->platformConfig('fnb_product'));
        $this->assertSame('FNBNDOBW', $legacy->platformConfig('fnb_product'));
    }

    // ──────────────────────────────────────────────────────────────
    // The duplicate-debit guard
    // ──────────────────────────────────────────────────────────────

    public function test_a_second_collect_now_is_refused_while_the_first_debit_is_unsettled(): void
    {
        $policy = $this->makePolicy('MIS2026215341');
        $this->makeContract($policy);

        // An accepted OOFF debit from an hour ago. RealPay has it; the bank
        // result has not come back, so no payment_transactions row exists.
        $this->makeCollectionEvent($policy, 'RP-INFLIGHT-001', 288.04, now()->subHour());

        $result = app(PayNowService::class)->collect($policy, 7);

        $this->assertFalse($result['success']);
        $this->assertSame('NONE', $result['method']);
        $this->assertStringContainsString('RP-INFLIGHT-001', $result['message'],
            'The refusal must name the debit it is protecting, so ops can go and look at it.');
        $this->assertStringContainsString('twice', $result['message']);

        // Nothing new was submitted to RealPay.
        $this->assertSame(0, RealpayContractInstallments::count(),
            'A refused collection must not create an instalment.');
    }

    public function test_collect_now_proceeds_once_the_earlier_debit_has_settled(): void
    {
        $policy = $this->makePolicy('MIS2026215350');
        $this->makeContract($policy);
        $this->makeCollectionEvent($policy, 'RP-SETTLED-001', 288.04, now()->subHour());

        // The webhook has since recorded that debit — so a further collection
        // is a genuinely new one, not a duplicate of the same premium.
        $this->makePayment($policy, 'RP-SETTLED-001', '288.04');

        $result = app(PayNowService::class)->collect($policy, 7);

        // It gets past the guard and fails at the (deliberately unreachable)
        // RealPay endpoint instead. That is the assertion: NOT the refusal.
        $this->assertFalse($result['success']);
        $this->assertSame('REALPAY', $result['method'],
            'Passing the guard means the attempt is made through the RealPay path.');
        $this->assertStringNotContainsString('debit the customer twice', $result['message']);
    }

    public function test_an_accepted_debit_with_no_reference_blocks_collection(): void
    {
        $policy = $this->makePolicy('MIS2026214543');
        $this->makeContract($policy);

        // Success logged but no reference captured — we cannot prove it settled,
        // so the safe reading is "still in flight".
        $this->makeCollectionEvent($policy, null, 512.75, now()->subHours(2));

        $result = app(PayNowService::class)->collect($policy, 7);

        $this->assertFalse($result['success']);
        $this->assertSame('NONE', $result['method']);
        $this->assertStringContainsString('awaiting its bank result', $result['message']);
    }

    public function test_the_guard_only_looks_back_over_its_configured_window(): void
    {
        $policy = $this->makePolicy('MIS2026215336');
        $this->makeContract($policy);

        // Older than the 72h window — a stale unsettled debit must not block the
        // policy forever.
        $this->makeCollectionEvent($policy, 'RP-ANCIENT-001', 288.04, now()->subDays(10));

        $result = app(PayNowService::class)->collect($policy, 7);

        $this->assertSame('REALPAY', $result['method'],
            'A debit outside the window must not block collection.');
    }

    public function test_the_guard_can_be_disabled_by_config(): void
    {
        Config::set('realpay.collect_now_inflight_hours', 0);

        $policy = $this->makePolicy('MIS2026215337');
        $this->makeContract($policy);
        $this->makeCollectionEvent($policy, 'RP-INFLIGHT-002', 288.04, now()->subHour());

        $result = app(PayNowService::class)->collect($policy, 7);

        $this->assertSame('REALPAY', $result['method']);
    }

    public function test_failed_collection_events_do_not_block_a_retry(): void
    {
        $policy = $this->makePolicy('MIS2026215338');
        $this->makeContract($policy);

        // A failed attempt never debited anyone, so retrying is the whole point
        // of the button.
        $this->makeCollectionEvent($policy, null, 288.04, now()->subMinutes(5), 'failed');

        $result = app(PayNowService::class)->collect($policy, 7);

        $this->assertSame('REALPAY', $result['method']);
    }

    public function test_the_guard_does_not_apply_to_dpo_policies(): void
    {
        $policy = $this->makePolicy('MIS2026215339');
        $this->makeContract($policy);
        $this->makeCollectionEvent($policy, 'RP-INFLIGHT-003', 288.04, now()->subHour());

        // A DPO mandate settles synchronously; the RealPay in-flight reasoning
        // does not apply, and the policy must stay collectable.
        $this->makeDpoToken($policy);

        // The DPO attempt itself then fails inside collectViaDpo (this harness
        // deliberately stubs none of the DPO token tables, so nothing reaches a
        // gateway). Routing is the assertion — that the RealPay guard did not
        // intercept a DPO policy.
        $result = app(PayNowService::class)->collect($policy, 7);

        $this->assertSame('DPO', $result['method'],
            'A DPO policy must route to DPO and ignore the RealPay in-flight guard.');
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function makePolicy(string $policyNumber, int $productId = 2): Policy
    {
        $policy = new Policy();
        $policy->policyNumber = $policyNumber;
        $policy->product_id   = $productId;
        $policy->customer_id  = 1;
        $policy->status       = 1;
        $policy->premium      = 288.04;
        $policy->save();

        return $policy;
    }

    private function makeContract(Policy $policy): void
    {
        $contract = new RealpayClientContracts();
        $contract->policy_id       = $policy->id;
        $contract->client_number   = $policy->policyNumber;
        $contract->contract_number = $policy->id . '/1';
        $contract->status          = 1;
        $contract->save();
    }

    /** A Collect Now attempt as PayNowService::logEvent() records it. */
    private function makeCollectionEvent(
        Policy $policy,
        ?string $reference,
        float $amount,
        $createdAt,
        string $status = 'success'
    ): void {
        DB::table('payment_collection_events')->insert([
            'policy_id'         => $policy->id,
            'policy_number'     => $policy->policyNumber,
            'customer_id'       => $policy->customer_id,
            'payment_method'    => 'REALPAY',
            'amount'            => $amount,
            'status'            => $status,
            'gateway_reference' => $reference,
            'triggered_by'      => 7,
            'created_at'        => $createdAt,
            'updated_at'        => $createdAt,
        ]);
    }

    private function makePayment(Policy $policy, string $reference, string $amount): void
    {
        $payment = new PaymentTransaction();
        $payment->policyNumber    = $policy->policyNumber;
        $payment->policy_id       = $policy->id;
        $payment->referenceNumber = $reference;
        $payment->amount          = $amount;
        $payment->status          = 'SUCCESS';
        $payment->paymentMethod   = 'RealPay';
        $payment->save();
    }

    private function makeDpoToken(Policy $policy): void
    {
        DB::table('scheduled_transactions')->insert([
            'policy_id'          => $policy->id,
            'policy_number'      => $policy->policyNumber,
            'premium'            => 288.04,
            'installment'        => 1,
            'status'             => 2,
            'subscription_token' => 'SUB-TOKEN',
            'token'              => 'TRANS-TOKEN',
            'customer_token'     => 'CUST-TOKEN',
            'email'              => 'customer@example.com',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    private function buildSchema(): void
    {
        $schema = Schema::connection('mysql');

        $schema->create('policies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->integer('status')->nullable();
            $table->decimal('premium', 12, 2)->nullable();
            $table->decimal('first_premium_wvat', 12, 2)->nullable();
            // Stamped by the Policy model on save.
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
            $table->string('TransactionToken')->nullable();
            $table->string('amount')->nullable();
            $table->string('status')->nullable();
            $table->string('paymentDate')->nullable();
            $table->string('paymentMethod')->nullable();
            $table->string('note', 500)->nullable();
            $table->integer('is_ledger')->nullable()->default(0);
            $table->string('numberOfInstalmentsPaid')->nullable();
            $table->string('paymentFrequency')->nullable();
            $table->integer('request_type')->nullable()->default(0);
            $table->unsignedInteger('payment_transaction_id')->nullable();
            $table->string('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('payment_collection_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id');
            $table->string('policy_number', 60);
            $table->unsignedInteger('customer_id')->nullable();
            $table->string('payment_method', 20);
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->string('gateway_reference', 120)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->unsignedInteger('triggered_by')->nullable();
            $table->unsignedInteger('premium_count')->nullable();
            $table->text('schedule_ids')->nullable();
            $table->timestamps();
        });

        $schema->create('realpay_client_contracts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('client_number')->nullable();
            $table->string('contract_number')->nullable();
            $table->integer('status')->nullable();
            $table->text('rp_response')->nullable();
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

        $schema->create('realpay_contracts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ClientNumber')->nullable();
            $table->string('ContractNumber')->nullable();
            $table->string('ContractSequence')->nullable();
            $table->string('TrackingCode')->nullable();
            $table->timestamps();
        });

        $schema->create('scheduled_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('policy_number')->nullable();
            $table->decimal('premium', 12, 2)->nullable();
            $table->integer('installment')->nullable();
            $table->integer('status')->nullable();
            $table->integer('retry_count')->nullable()->default(0);
            $table->string('reason')->nullable();
            $table->string('payment_method')->nullable();
            $table->date('billing_date')->nullable();
            $table->string('subscription_token')->nullable();
            $table->string('token')->nullable();
            $table->string('customer_token')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        $schema->create('customer_banking', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('bankName')->nullable();
            $table->string('accountNumber')->nullable();
            $table->timestamps();
        });

        $schema->create('customer', function (Blueprint $table) {
            $table->increments('id');
            $table->string('firstName')->nullable();
            $table->string('lastName')->nullable();
            $table->string('cellphone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        $schema->create('realpay_reflection_exceptions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('instalment_reference', 100)->nullable();
            $table->string('instalment_sequence', 40)->nullable();
            $table->string('client_number', 100)->nullable();
            $table->string('contract_number', 100)->nullable();
            $table->string('policy_number', 60)->nullable();
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('instalment_status', 10)->nullable();
            $table->string('amount', 40)->nullable();
            $table->date('action_date')->nullable();
            $table->string('reason', 60)->nullable();
            $table->text('error')->nullable();
            $table->text('payload')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by', 64)->nullable();
            $table->timestamps();
        });
    }
}
