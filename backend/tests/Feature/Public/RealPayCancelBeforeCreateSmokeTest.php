<?php

namespace Tests\Feature\Public;

use AlphaDirect\Customer;
use AlphaDirect\CustomerBanking;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\Services\RealPayDuplicateContractGuard;
use AlphaDirect\Transaction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * END-TO-END SMOKE TEST for cancel-before-create.
 *
 * RealPayDuplicateContractGuardTest exercises the guard in isolation. This one
 * drives the two real agent journeys the way production does:
 *
 *     POST /api/updateExpiredCardDetailsFromStart
 *     POST /api/redoPaymentFromStart
 *       → Admin\PolicyController
 *         → RealPayDuplicateContractGuard      (identify → cancel → verify)
 *           → RealPayController                (the RealPay wire — faked here)
 *         → addRealpayPaymentForInstantProduct (contract creation)
 *
 * That wiring is where the bug actually lived. Every piece "worked" on its own;
 * updateExpiredCardDetailsFromStart simply called them in the wrong ORDER —
 * create, then cancel — so the assertions here are as much about the sequence
 * of events as about the rows left behind. Specifically:
 *
 *   - "existing contract cancelled" must be logged BEFORE "creating replacement
 *     contract". That single ordering is the whole ticket.
 *   - a cancellation that fails must mean the creation is never even attempted.
 *   - pressing the button twice must produce one cancellation, not two contracts.
 *
 * NOTHING LEAVES THIS MACHINE. Two boundaries are closed off:
 *   - the guard's RealPay calls go to a fake RealPayController bound into the
 *     container, so no contract is read or cancelled on a real portal;
 *   - realpay.start.base_url / client_auth are blanked, so the creation leg
 *     stops at clientAuthForInstantProduct()'s own config check and returns null
 *     without opening a socket.
 *
 * The creation leg therefore always fails here, which is deliberate: it proves
 * the cancellation still happened first, was recorded, and that a creation
 * failure after a successful cancellation surfaces as a clean 401 rather than
 * the fatal on getData() this code used to throw.
 *
 * Runs against a throwaway file-backed SQLite database — no shared RDS, no live
 * policy, nothing to clean up.
 */
class RealPayCancelBeforeCreateSmokeTest extends TestCase
{
    private const CONNECTIONS = ['mysql', 'mysql_write', 'mysql_system'];

    private const POLICY_NUMBER = 'AD-SMOKE-0001';

    private string $dbPath;
    private string $previousDefault;
    private array $previousConfig = [];

    /** @var array<int, array{level: string, message: string}> */
    private array $logLines = [];

    /** @var array<int, string> every SQL statement, in the order it ran */
    private array $queries = [];

    private FakeRealPayWire $wire;
    private Policy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        // PolicyController does ini_set('max_execution_time', 180) at FILE scope,
        // so merely autoloading it caps this whole PHP process. Force the
        // autoload, then undo it — in that order; set_time_limit() before the
        // autoload is simply overwritten.
        class_exists(\AlphaDirect\Http\Controllers\Admin\PolicyController::class);
        set_time_limit(0);

        Config::set('audit.enabled', false);
        foreach ([Policy::class, Customer::class, CustomerProfile::class, CustomerBanking::class,
                  RealpayClientContracts::class, RealpayCancelRequests::class,
                  RealpayContractInstallments::class, Transaction::class] as $model) {
            if (method_exists($model, 'disableAuditing')) {
                $model::disableAuditing();
            }
        }

        // No RealPay credentials → clientAuthForInstantProduct() returns null at
        // its own config gate, before any socket is opened.
        Config::set('realpay.start.base_url', null);
        Config::set('realpay.start.client_auth', null);
        Config::set('realpay.base_url', null);
        Config::set('realpay.client_auth', null);

        Config::set('realpay.duplicate_guard.enabled', true);
        Config::set('realpay.duplicate_guard.verify_after_cancel', true);
        Config::set('realpay.duplicate_guard.block_when_unverifiable', true);
        Config::set('realpay.mandate.enabled', false);
        Config::set('cache.default', 'array');

        $this->bootDatabase();
        $this->seedFixtures();
        $this->captureLogs();
        $this->captureQueries();

        // The guard is the one collaborator resolved through the container, which
        // is what lets the RealPay wire be replaced without touching the
        // controllers' hardcoded `new RealPayController()`.
        $this->wire = new FakeRealPayWire();
        $this->app->bind(
            RealPayDuplicateContractGuard::class,
            fn () => new RealPayDuplicateContractGuard($this->wire)
        );
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection($this->previousDefault);
        foreach (self::CONNECTIONS as $name) {
            DB::purge($name);
        }
        DB::purge('smoke');

        if (isset($this->dbPath) && is_file($this->dbPath)) {
            @unlink($this->dbPath);
        }

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────
    // Update Expired Card Details
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function update_expired_card_cancels_the_old_contract_before_creating_the_new_one(): void
    {
        $this->wire->portal = [
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            ['reachable' => true, 'contracts' => []],
        ];

        $response = $this->postJson('/api/updateExpiredCardDetailsFromStart', $this->cardPayload());

        // THE ticket, in one assertion: the old contract is cancelled before the
        // creation begins. Before this change the order was reversed.
        $cancelledAt = $this->cancellationRecordedAt();
        $creatingAt  = $this->creationStartedAt();

        $this->assertNotNull($cancelledAt, 'the existing contract must be cancelled');
        $this->assertNotNull($creatingAt, 'the creation must be attempted after it');
        $this->assertLessThan($creatingAt, $cancelledAt, 'CANCEL MUST PRECEDE CREATE');

        // And the audit trail the ticket asks for says the same thing.
        $this->assertNotNull($this->logIndex('existing contract cancelled'));
        $this->assertNotNull($this->logIndex('creating replacement contract'));

        $this->assertSame(['C1001'], $this->wire->deleted);

        // …and it is recorded, not just done.
        $this->assertSame(0, (int) RealpayClientContracts::where('contract_number', 'C1001')->value('status'));
        $this->assertSame(1, (int) RealpayCancelRequests::where('contract', 'C1001')->value('cancel_status'));
        $this->assertSame('Yes', DB::table('update_contract')
            ->where('policyNumber', self::POLICY_NUMBER)->value('old_contract_cancel'));

        // The creation leg cannot authenticate here, and that must surface as a
        // clean refusal rather than the fatal this path used to throw.
        $response->assertStatus(401);
        $this->assertFalse($response->json('status'));
        $this->assertStringContainsString('Could not authenticate with RealPay', $response->json('message'));
    }

    /** @test */
    public function update_expired_card_creates_nothing_when_the_cancellation_fails(): void
    {
        $this->wire->portal        = [['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]]];
        $this->wire->deleteSucceeds = false;

        $response = $this->postJson('/api/updateExpiredCardDetailsFromStart', $this->cardPayload());

        $this->assertNull(
            $this->creationStartedAt(),
            'a failed cancellation must not be followed by a creation attempt'
        );

        $response->assertStatus(401);
        $this->assertStringContainsString('Could not cancel the existing RealPay contract', $response->json('message'));
        $this->assertStringContainsString('C1001', $response->json('message'));

        // The live contract is untouched — not silently marked dead.
        $this->assertSame(1, (int) RealpayClientContracts::where('contract_number', 'C1001')->value('status'));
        $this->assertSame(2, (int) RealpayCancelRequests::where('contract', 'C1001')->value('cancel_status'));
        $this->assertSame('No', DB::table('update_contract')
            ->where('policyNumber', self::POLICY_NUMBER)->value('old_contract_cancel'));
    }

    // ──────────────────────────────────────────────────────────────────
    // Reprocess Payment
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function reprocess_payment_cancels_the_old_contract_before_creating_the_new_one(): void
    {
        $this->wire->portal = [
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            ['reachable' => true, 'contracts' => []],
        ];

        $response = $this->postJson('/api/redoPaymentFromStart', $this->redoPayload());

        $cancelledAt = $this->cancellationRecordedAt();
        $creatingAt  = $this->creationStartedAt();

        $this->assertNotNull($cancelledAt);
        $this->assertNotNull($creatingAt, 'the guard proved the policy clear, so the creation must proceed');
        $this->assertLessThan($creatingAt, $cancelledAt, 'CANCEL MUST PRECEDE CREATE');

        $this->assertNotNull($this->logIndex('existing contract cancelled'));
        $this->assertNotNull($this->logIndex('creating replacement contract'));

        $this->assertSame(['C1001'], $this->wire->deleted);
        $this->assertSame(0, (int) RealpayClientContracts::where('contract_number', 'C1001')->value('status'));
        $this->assertSame('Yes', DB::table('update_contract')
            ->where('policyNumber', self::POLICY_NUMBER)->value('old_contract_cancel'));

        $response->assertStatus(401);
        $this->assertStringContainsString('Could not authenticate with RealPay', $response->json('message'));
    }

    /** @test */
    public function reprocess_payment_creates_nothing_when_the_cancellation_fails(): void
    {
        $this->wire->portal         = [['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]]];
        $this->wire->deleteSucceeds = false;

        $response = $this->postJson('/api/redoPaymentFromStart', $this->redoPayload());

        $this->assertNull($this->creationStartedAt(), 'no creation may be attempted after a failed cancellation');

        $response->assertStatus(401);
        $this->assertStringContainsString('Could not replace the existing RealPay contract', $response->json('message'));
        $this->assertSame(1, (int) RealpayClientContracts::where('contract_number', 'C1001')->value('status'));
    }

    /** @test */
    public function reprocess_payment_no_longer_reports_success_without_applying_the_new_details(): void
    {
        // The old behaviour: a contract already on the portal short-circuited to
        // HTTP 200 "already exists, synced", with the agent's freshly captured
        // bank details silently discarded and the stale contract left live.
        $this->wire->portal = [
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            ['reachable' => true, 'contracts' => []],
        ];

        $response = $this->postJson('/api/redoPaymentFromStart', $this->redoPayload());

        $this->assertNotSame(200, $response->status(), 'an existing contract must no longer short-circuit to success');
        $this->assertStringNotContainsString('already exists', (string) $response->json('message'));
        $this->assertSame(['C1001'], $this->wire->deleted, 'it must be replaced, not merely synced');
    }

    // ──────────────────────────────────────────────────────────────────
    // Repeated execution — the operational risk in the ticket
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function pressing_reprocess_twice_cancels_once_and_never_stacks_contracts(): void
    {
        $this->wire->portal = [
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            // Every read from here on: the policy is clear.
            ['reachable' => true, 'contracts' => []],
        ];

        $this->postJson('/api/redoPaymentFromStart', $this->redoPayload());
        $this->postJson('/api/redoPaymentFromStart', $this->redoPayload());

        $this->assertSame(['C1001'], $this->wire->deleted, 'exactly one cancellation across two presses');
        $this->assertSame(
            1,
            RealpayClientContracts::where('policy_id', $this->policy->id)->count(),
            'no second contract row appeared'
        );
        $this->assertSame(
            0,
            RealpayClientContracts::where('policy_id', $this->policy->id)->where('status', 1)->count(),
            'and nothing is left active on the policy'
        );
    }

    /** @test */
    public function pressing_update_expired_card_twice_cancels_once(): void
    {
        $this->wire->portal = [
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            ['reachable' => true, 'contracts' => []],
        ];

        $this->postJson('/api/updateExpiredCardDetailsFromStart', $this->cardPayload());
        $this->postJson('/api/updateExpiredCardDetailsFromStart', $this->cardPayload());

        $this->assertSame(['C1001'], $this->wire->deleted, 'exactly one cancellation across two presses');
    }

    // ──────────────────────────────────────────────────────────────────
    // RealPay unreachable
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function an_unreachable_realpay_stops_both_journeys_rather_than_risking_a_duplicate(): void
    {
        $this->wire->portal         = [['reachable' => false, 'contracts' => []]];
        $this->wire->deleteSucceeds = false;

        $card = $this->postJson('/api/updateExpiredCardDetailsFromStart', $this->cardPayload());
        $redo = $this->postJson('/api/redoPaymentFromStart', $this->redoPayload());

        $card->assertStatus(401);
        $redo->assertStatus(401);
        $this->assertNull(
            $this->creationStartedAt(),
            'no creation may be attempted while the portal state is unknown'
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Fixtures
    // ──────────────────────────────────────────────────────────────────

    private function cardPayload(): array
    {
        return [
            'policyNumber' => self::POLICY_NUMBER,
            'leadSource'   => 'start.alphadirect.co.bw',
            'requestType'  => 'updateCard',
            'billing_date' => '25/10/2026',
            'bankName'     => '3',
            'branchCode'   => '123456',
            'accountType'  => '1',
            'accountNumber' => '1234567890',
        ];
    }

    private function redoPayload(): array
    {
        return [
            'policyNumber'  => self::POLICY_NUMBER,
            'billing_date'  => '25/10/2026',
            'BillingStart'  => 'Now',
            'bankName'      => '3',
            'branchCode'    => '123456',
            'accountType'   => '1',
            'accountNumber' => '1234567890',
        ];
    }

    private function portalContract(string $number, string $contractStatus, string $instalmentStatus = 'I'): array
    {
        return [
            'ContractNumber'      => $number,
            'ClientNumber'        => self::POLICY_NUMBER,
            'ContractStatus'      => $contractStatus,
            'ContractInstalments' => [
                ['InstalmentSequence' => 1, 'InstalmentStatus' => $instalmentStatus],
            ],
        ];
    }

    /** Position of the first log line containing $needle, or null if never logged. */
    private function logIndex(string $needle): ?int
    {
        foreach ($this->logLines as $i => $line) {
            if (str_contains($line['message'], $needle)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Query-order capture. The ordering assertions below rest on this rather
     * than on log lines: a log line lives in the same block as the guard, so
     * moving the guard moves the marker with it and the assertion goes on
     * passing. The SQL footprint of each leg cannot be moved that way — only the
     * contract-creation leg reads the `customer` table — so it is an
     * independent observation of when creation actually began.
     */
    private function captureQueries(): void
    {
        $this->queries = [];

        DB::listen(function ($query) {
            $this->queries[] = $query->sql;
        });
    }

    /** Position of the first statement containing $needle, or null if never run. */
    private function queryIndex(string $needle): ?int
    {
        foreach ($this->queries as $i => $sql) {
            if (str_contains($sql, $needle)) {
                return $i;
            }
        }

        return null;
    }

    /** The guard's cancellation bookkeeping — the old contract being marked dead. */
    private function cancellationRecordedAt(): ?int
    {
        return $this->queryIndex('update "realpay_client_contracts"');
    }

    /**
     * The contract-creation leg, observed by its own first query.
     * addRealpayPaymentForInstantProduct() reads `customer` before it does
     * anything else of consequence, and nothing else in either journey touches
     * that table.
     */
    private function creationStartedAt(): ?int
    {
        return $this->queryIndex('from "customer" where');
    }

    private function captureLogs(): void
    {
        $this->logLines = [];

        Log::listen(function ($event) {
            $this->logLines[] = [
                'level'   => $event->level,
                'message' => $event->message,
            ];
        });
    }

    private function seedFixtures(): void
    {
        $customerId = DB::table('customer')->insertGetId([
            'firstName' => 'Smoke',
            'lastName'  => 'Test',
            'cellphone' => '26770000000',
            'email'     => 'smoke@example.test',
        ]);

        DB::table('customer_profile')->insert([
            'customer_id' => $customerId,
            'omang'       => '000000000',
        ]);

        $policyId = DB::table('policies')->insertGetId([
            'policyNumber' => self::POLICY_NUMBER,
            'product_id'   => 1,          // instant product (not motor comp)
            'customer_id'  => $customerId,
            'status'       => 0,
        ]);

        DB::table('customer_banking')->insert([
            'policy_id'     => $policyId,
            'customer_id'   => $customerId,
            'billing'       => 'RealPay',
            'bankName'      => 3,
            'branchCode'    => '123456',
            'accountType'   => 1,
            'accountNumber' => '1234567890',
        ]);

        DB::table('realpay_client_contracts')->insert([
            'policy_id'       => $policyId,
            'contract_number' => 'C1001',
            'client_number'   => self::POLICY_NUMBER,
            'status'          => 1,
        ]);

        $this->policy = Policy::find($policyId);
    }

    private function bootDatabase(): void
    {
        $this->dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'realpay_smoke_' . getmypid() . '.sqlite';
        if (is_file($this->dbPath)) {
            @unlink($this->dbPath);
        }
        touch($this->dbPath);

        $connection = [
            'driver'                  => 'sqlite',
            'database'                => $this->dbPath,
            'prefix'                  => '',
            'foreign_key_constraints' => false,
        ];

        // File-backed, and every named connection points at the SAME file: the
        // controllers reach models on more than one connection name, and separate
        // :memory: handles would be separate databases.
        Config::set('database.connections.smoke', $connection);
        foreach (self::CONNECTIONS as $name) {
            Config::set('database.connections.' . $name, $connection);
            DB::purge($name);
        }

        $this->previousDefault = (string) Config::get('database.default');
        DB::purge('smoke');
        DB::setDefaultConnection('smoke');

        $this->buildSchema();
    }

    private function buildSchema(): void
    {
        $schema = Schema::connection('smoke');

        $schema->create('policies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->integer('status')->nullable();
            $table->date('billingStartDate')->nullable();
            $table->string('BillingStart')->nullable();
            $table->integer('isVirtualBox')->nullable();
            $table->integer('isPaymentCancel')->nullable();
            $table->date('policyActivatedDate')->nullable();
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

        $schema->create('customer_profile', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('customer_id')->nullable();
            $table->string('omang')->nullable();
            $table->string('passport')->nullable();
            $table->timestamps();
        });

        $schema->create('customer_banking', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->string('billing')->nullable();
            $table->integer('bankName')->nullable();
            $table->string('branchCode')->nullable();
            $table->integer('accountType')->nullable();
            $table->string('accountNumber')->nullable();
            $table->timestamps();
        });

        $schema->create('realpay_client_contracts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('contract_number')->nullable();
            $table->string('client_number')->nullable();
            $table->integer('status')->nullable();
            $table->timestamps();
        });

        $schema->create('realpay_cancel_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('contract')->nullable();
            $table->string('leftout_premium_contract')->nullable();
            $table->integer('cancel_status')->nullable();
            $table->timestamps();
        });

        $schema->create('realpay_contract_installments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('clientNumber')->nullable();
            $table->string('contractNumber')->nullable();
            $table->string('InstalmentReferenceNumber')->nullable();
            $table->string('InstalmentSequence')->nullable();
            $table->string('InstalmentStatus')->nullable();
            $table->string('TrackingCode')->nullable();
            $table->timestamps();
        });

        $schema->create('realpay_payment_request', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('contract')->nullable();
            $table->integer('contractCreated')->nullable();
            $table->integer('status')->nullable();
            $table->timestamps();
        });

        $schema->create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('realPayTransaction_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        $schema->create('update_contract', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber')->nullable();
            $table->string('new_payment_method')->nullable();
            $table->string('old_payment_method')->nullable();
            $table->string('old_contract_cancel')->nullable();
            $table->string('agent')->nullable();
            $table->integer('status')->nullable();
            $table->timestamps();
        });
    }
}

/**
 * The RealPay wire, stubbed at exactly the two methods the guard uses to talk to
 * the portal. Everything else about RealPayController — and every line of the
 * guard and the controllers — is the real thing.
 *
 * Portal responses are consumed in order and the last one repeats, so a single
 * fake expresses "one contract live, then nothing".
 */
class FakeRealPayWire extends RealPayController
{
    /** @var array<int, array{reachable: bool, contracts: array}> */
    public array $portal = [];

    /** @var array<int, string> every contract number a DELETE was issued for */
    public array $deleted = [];

    public bool $deleteSucceeds = true;

    public function fetchPortalContracts($policy_id, $clientNumber): array
    {
        if (count($this->portal) > 1) {
            return array_shift($this->portal);
        }

        return $this->portal[0] ?? ['reachable' => false, 'contracts' => []];
    }

    public function deleteRealpayContractByNumber($policy, $contractNumber): array
    {
        if (! $this->deleteSucceeds) {
            return ['ok' => false, 'message' => 'RealPay refused the cancellation', 'attempts' => []];
        }

        $this->deleted[] = (string) $contractNumber;

        return ['ok' => true, 'message' => 'cancelled', 'attempts' => []];
    }
}
