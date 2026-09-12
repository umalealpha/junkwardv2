<?php

namespace Tests\Feature\Public;

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
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cancel-before-create for RealPay contracts.
 *
 * The bug: Reprocess Payment and Update Expired Card Details both end in
 * "create a RealPay contract", and neither cancelled the live one first —
 * Update Expired Card Details cancelled it *after* creating the replacement.
 * Two live contracts is two debits off one customer.
 *
 * What has to hold, and is asserted below:
 *
 *   1. an existing active contract is identified before anything is created
 *   2. it is cancelled, and the local records follow (client contract inactive,
 *      cancel request 1, transaction CANCELLED)
 *   3. a cancellation that fails refuses the creation — cancel request 2
 *   4. a contract RealPay still reports active after the cancel refuses it too
 *   5. running the journey again is a no-op, not a second contract
 *   6. RealPay being unreachable never silently allows a duplicate
 *
 * Runs against in-memory SQLite with a fake RealPayController: the guard's own
 * logic is pure decision + bookkeeping, and every HTTP call it makes goes
 * through two controller methods that are stubbed here.
 */
class RealPayDuplicateContractGuardTest extends TestCase
{
    private const CONNECTION = 'realpay_guard_test';

    private string $previousConnection;
    private Policy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('audit.enabled', false);
        Policy::disableAuditing();
        RealpayClientContracts::disableAuditing();
        RealpayCancelRequests::disableAuditing();
        RealpayContractInstallments::disableAuditing();
        Transaction::disableAuditing();

        // The guard serialises per policy through the cache; an in-process store
        // keeps that real without needing redis.
        Config::set('cache.default', 'array');

        Config::set('realpay.duplicate_guard.enabled', true);
        Config::set('realpay.duplicate_guard.verify_after_cancel', true);
        Config::set('realpay.duplicate_guard.block_when_unverifiable', true);
        // The mandate tables are not built here; the service no-ops without them.
        Config::set('realpay.mandate.enabled', false);

        Config::set('database.connections.' . self::CONNECTION, [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => false,
        ]);

        $this->previousConnection = (string) Config::get('database.default');
        DB::purge(self::CONNECTION);
        DB::setDefaultConnection(self::CONNECTION);

        $this->buildSchema();
        $this->policy = $this->makePolicy();
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection($this->previousConnection);
        DB::purge(self::CONNECTION);

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────
    // Nothing to cancel
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function a_policy_with_no_contract_on_realpay_is_clear_to_create(): void
    {
        $realpay = $this->fakeRealpay(['reachable' => true, 'contracts' => []]);

        $result = (new RealPayDuplicateContractGuard($realpay))
            ->ensureSingleActiveContract($this->policy, 'reprocess-payment');

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['identified']);
        $this->assertSame([], $result['cancelled']);
        // Proven clear by RealPay itself — the caller can skip its own probe.
        $this->assertFalse($result['active_after']);
        $this->assertSame([], $realpay->deleted, 'nothing should have been cancelled');
    }

    // ──────────────────────────────────────────────────────────────────
    // The main path: identify → cancel → verify
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function an_existing_active_contract_is_identified_and_cancelled_before_creation(): void
    {
        $this->seedLocalContract('C1001');

        $realpay = $this->fakeRealpay(
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            // After the DELETE, RealPay reports the contract cancelled.
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'C')]]
        );

        $result = (new RealPayDuplicateContractGuard($realpay))
            ->ensureSingleActiveContract($this->policy, 'update-expired-card');

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame(['C1001'], $result['identified']);
        $this->assertSame(['C1001'], $result['cancelled']);
        $this->assertSame([], $result['failed']);
        $this->assertFalse($result['active_after'], 'the post-cancel read must prove the policy clear');
        $this->assertSame(['C1001'], $realpay->deleted);
    }

    /** @test */
    public function a_successful_cancellation_writes_the_local_trail(): void
    {
        $this->seedLocalContract('C1001');
        $this->seedActiveInstalment('C1001');
        $transactionId = DB::table('transactions')->insertGetId([
            'realPayTransaction_id' => $this->policy->id,
            'policyNumber'          => $this->policy->policyNumber,
            'status'                => 'SUCCESS',
        ]);

        $realpay = $this->fakeRealpay(
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            ['reachable' => true, 'contracts' => []]
        );

        (new RealPayDuplicateContractGuard($realpay))
            ->ensureSingleActiveContract($this->policy, 'update-expired-card');

        $this->assertSame(0, (int) RealpayClientContracts::where('contract_number', 'C1001')->value('status'));
        $this->assertSame(1, (int) RealpayCancelRequests::where('contract', 'C1001')->value('cancel_status'));
        $this->assertSame('CANCELLED', Transaction::find($transactionId)->status);
        $this->assertSame(
            'I',
            RealpayContractInstallments::where('contractNumber', 'C1001')->value('InstalmentStatus'),
            'the cached instalment must stop reading as active'
        );
    }

    /** @test */
    public function a_contract_with_only_an_active_instalment_still_counts_as_live(): void
    {
        // RealPay marks the contract itself 'C' but leaves an instalment 'A' —
        // that instalment still debits the customer, so it must be cancelled.
        $realpay = $this->fakeRealpay(
            ['reachable' => true, 'contracts' => [$this->portalContract('C2002', 'C', 'A')]],
            ['reachable' => true, 'contracts' => []]
        );

        $result = (new RealPayDuplicateContractGuard($realpay))
            ->ensureSingleActiveContract($this->policy, 'reprocess-payment');

        $this->assertSame(['C2002'], $result['identified']);
        $this->assertSame(['C2002'], $result['cancelled']);
    }

    // ──────────────────────────────────────────────────────────────────
    // Failure closes the door
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function a_failed_cancellation_refuses_the_creation(): void
    {
        $this->seedLocalContract('C1001');

        $realpay = $this->fakeRealpay(['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]]);
        $realpay->deleteSucceeds = false;

        $result = (new RealPayDuplicateContractGuard($realpay))
            ->ensureSingleActiveContract($this->policy, 'reprocess-payment');

        $this->assertFalse($result['ok']);
        $this->assertSame(['C1001'], $result['failed']);
        $this->assertSame([], $result['cancelled']);
        $this->assertStringContainsString('C1001', $result['message']);

        $this->assertSame(2, (int) RealpayCancelRequests::where('contract', 'C1001')->value('cancel_status'));
        $this->assertSame(1, (int) RealpayClientContracts::where('contract_number', 'C1001')->value('status'),
            'a contract that was not cancelled must not be marked inactive');
    }

    /** @test */
    public function a_contract_still_active_after_cancellation_refuses_the_creation(): void
    {
        // RealPay accepted the DELETE but still reports the contract active.
        $realpay = $this->fakeRealpay(
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]]
        );

        $result = (new RealPayDuplicateContractGuard($realpay))
            ->ensureSingleActiveContract($this->policy, 'reprocess-payment');

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['active_after']);
        $this->assertSame(['C1001'], $result['failed']);
        $this->assertStringContainsString('still reports', $result['message']);
    }

    /** @test */
    public function a_guard_failure_never_reports_clear(): void
    {
        $realpay = new class extends RealPayController {
            public function fetchPortalContracts($policy_id, $clientNumber): array
            {
                throw new \RuntimeException('RealPay exploded');
            }
        };

        $result = (new RealPayDuplicateContractGuard($realpay))
            ->ensureSingleActiveContract($this->policy, 'reprocess-payment');

        $this->assertFalse($result['ok'], 'an exception must fail closed, not open');
        $this->assertStringContainsString('RealPay exploded', $result['message']);
    }

    // ──────────────────────────────────────────────────────────────────
    // RealPay unreachable
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function an_unreachable_realpay_falls_back_to_local_records_and_refuses(): void
    {
        $this->seedLocalContract('C1001');

        $realpay = $this->fakeRealpay(['reachable' => false, 'contracts' => []]);
        // Unreachable means the DELETE cannot land either.
        $realpay->deleteSucceeds = false;

        $result = (new RealPayDuplicateContractGuard($realpay))
            ->ensureSingleActiveContract($this->policy, 'reprocess-payment');

        $this->assertFalse($result['ok']);
        $this->assertSame(['C1001'], $result['identified'], 'the local record is the only evidence left');
        $this->assertSame(['C1001'], $result['failed']);
    }

    /** @test */
    public function an_unreachable_realpay_refuses_a_policy_with_contract_history(): void
    {
        // A contract row exists but is already marked inactive, so the local
        // fallback finds nothing live — and RealPay cannot confirm that.
        RealpayClientContracts::insert([
            'policy_id'       => $this->policy->id,
            'contract_number' => 'C0999',
            'client_number'   => $this->policy->policyNumber,
            'status'          => 0,
        ]);

        $realpay = $this->fakeRealpay(['reachable' => false, 'contracts' => []]);

        $result = (new RealPayDuplicateContractGuard($realpay))
            ->ensureSingleActiveContract($this->policy, 'reprocess-payment');

        $this->assertFalse($result['ok']);
        $this->assertNull($result['active_after']);
        $this->assertSame([], $realpay->deleted);
    }

    /** @test */
    public function an_unreachable_realpay_still_allows_a_policys_first_contract(): void
    {
        // No contract has ever existed for this policy, so there is nothing to
        // duplicate — a RealPay outage must not block a first debit order.
        $realpay = $this->fakeRealpay(['reachable' => false, 'contracts' => []]);

        $result = (new RealPayDuplicateContractGuard($realpay))
            ->ensureSingleActiveContract($this->policy, 'reprocess-payment');

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertNull($result['active_after'], 'nothing was verified, so nothing is claimed');
    }

    // ──────────────────────────────────────────────────────────────────
    // Repeated execution
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function running_the_journey_twice_cancels_once_and_creates_no_duplicate(): void
    {
        $this->seedLocalContract('C1001');

        $realpay = $this->fakeRealpay(
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            ['reachable' => true, 'contracts' => []]
        );

        $guard = new RealPayDuplicateContractGuard($realpay);

        $first = $guard->ensureSingleActiveContract($this->policy, 'reprocess-payment');
        $this->assertTrue($first['ok']);
        $this->assertSame(['C1001'], $first['cancelled']);

        // The caller creates its contract, then closes the window — exactly what
        // PolicyController does once addRealpayPaymentForInstantProduct returns.
        $guard->endCreation($this->policy);

        // Second press: RealPay now reports nothing active (the fake has
        // exhausted its first response and keeps returning the last one).
        $second = $guard->ensureSingleActiveContract($this->policy, 'reprocess-payment');

        $this->assertTrue($second['ok']);
        $this->assertSame([], $second['identified'], 'the second run must find nothing to cancel');
        $this->assertSame([], $second['cancelled']);
        $this->assertSame(['C1001'], $realpay->deleted, 'exactly one cancellation across both runs');
    }

    /** @test */
    public function a_concurrent_second_submit_is_turned_away_rather_than_cancelling_again(): void
    {
        $this->seedLocalContract('C1001');

        $realpayA = $this->fakeRealpay(
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            ['reachable' => true, 'contracts' => []]
        );
        $realpayB = $this->fakeRealpay(['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]]);

        $guardA = new RealPayDuplicateContractGuard($realpayA);
        $guardB = new RealPayDuplicateContractGuard($realpayB);

        // A is mid-journey: it has cancelled and is about to create.
        $first = $guardA->ensureSingleActiveContract($this->policy, 'reprocess-payment');
        $this->assertTrue($first['ok']);

        // B arrives before A has created anything — a double-click.
        $second = $guardB->ensureSingleActiveContract($this->policy, 'reprocess-payment');

        $this->assertFalse($second['ok'], 'the second submit must not proceed to create');
        $this->assertStringContainsString('already in progress', $second['message']);
        $this->assertSame([], $realpayB->deleted, 'and must not cancel anything of its own');

        // B's refusal must not have freed A's window.
        $this->assertFalse($guardB->beginCreation($this->policy));

        // Once A finishes, the policy is available again.
        $guardA->endCreation($this->policy);
        $this->assertTrue($guardB->beginCreation($this->policy));
    }

    // ──────────────────────────────────────────────────────────────────
    // Escape hatch
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function the_guard_can_be_switched_off(): void
    {
        Config::set('realpay.duplicate_guard.enabled', false);
        $this->seedLocalContract('C1001');

        $realpay = $this->fakeRealpay(['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]]);

        $result = (new RealPayDuplicateContractGuard($realpay))
            ->ensureSingleActiveContract($this->policy, 'reprocess-payment');

        $this->assertTrue($result['ok']);
        $this->assertSame([], $realpay->deleted, 'the switch means no RealPay calls at all');
    }

    // ──────────────────────────────────────────────────────────────────
    // The pure classifier the guard leans on
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function active_contract_numbers_takes_the_union_of_both_active_signals(): void
    {
        $controller = new RealPayController();

        $numbers = $controller->activeContractNumbers([
            $this->portalContract('C-ACTIVE', 'A'),                 // contract active
            $this->portalContract('C-INSTALMENT', 'C', 'A'),        // instalment active
            $this->portalContract('C-DEAD', 'C', 'I'),              // neither
            ['ContractStatus' => 'A'],                              // no number — ignored
            $this->portalContract('C-ACTIVE', 'A'),                 // duplicate — collapsed
        ]);

        $this->assertSame(['C-ACTIVE', 'C-INSTALMENT'], $numbers);
    }

    // ──────────────────────────────────────────────────────────────────
    // Fixtures
    // ──────────────────────────────────────────────────────────────────

    /**
     * A RealPayController whose two outbound calls are canned.
     *
     * Portal responses are consumed in order and the last one repeats, which is
     * what lets a single fake express "active, then cancelled".
     */
    private function fakeRealpay(array ...$portalResponses): RealPayController
    {
        $fake = new class extends RealPayController {
            /** @var array<int, array> */
            public array $portalResponses = [];
            /** @var array<int, string> */
            public array $deleted = [];
            public bool $deleteSucceeds = true;

            public function fetchPortalContracts($policy_id, $clientNumber): array
            {
                if (count($this->portalResponses) > 1) {
                    return array_shift($this->portalResponses);
                }

                return $this->portalResponses[0] ?? ['reachable' => false, 'contracts' => []];
            }

            public function deleteRealpayContractByNumber($policy, $contractNumber): array
            {
                if (! $this->deleteSucceeds) {
                    return ['ok' => false, 'message' => 'RealPay refused', 'attempts' => []];
                }

                $this->deleted[] = (string) $contractNumber;

                return ['ok' => true, 'message' => 'cancelled', 'attempts' => []];
            }
        };

        $fake->portalResponses = $portalResponses;

        return $fake;
    }

    /** One contract as RealPay's ContractGetResponse renders it. */
    private function portalContract(string $number, string $contractStatus, string $instalmentStatus = 'I'): array
    {
        return [
            'ContractNumber'      => $number,
            'ClientNumber'        => $this->policy->policyNumber,
            'ContractStatus'      => $contractStatus,
            'ContractInstalments' => [
                ['InstalmentSequence' => 1, 'InstalmentStatus' => $instalmentStatus],
            ],
        ];
    }

    private function seedLocalContract(string $contractNumber): void
    {
        RealpayClientContracts::insert([
            'policy_id'       => $this->policy->id,
            'contract_number' => $contractNumber,
            'client_number'   => $this->policy->policyNumber,
            'status'          => 1,
        ]);
    }

    private function seedActiveInstalment(string $contractNumber): void
    {
        RealpayContractInstallments::insert([
            'policy_id'                 => $this->policy->id,
            'clientNumber'              => $this->policy->policyNumber,
            'contractNumber'            => $contractNumber,
            'InstalmentReferenceNumber' => 'REF-1',
            'InstalmentSequence'        => 1,
            'InstalmentStatus'          => 'A',
        ]);
    }

    private function makePolicy(): Policy
    {
        $id = DB::table('policies')->insertGetId([
            'policyNumber' => 'AD-TEST-0001',
            'product_id'   => 1,
            'customer_id'  => 1,
        ]);

        return Policy::find($id);
    }

    private function buildSchema(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->create('policies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
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
            $table->timestamps();
        });

        $schema->create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('realPayTransaction_id')->nullable();
            $table->string('policyNumber')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }
}
