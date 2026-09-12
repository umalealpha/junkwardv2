<?php

namespace Tests\Feature\Public;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Policy;
use AlphaDirect\RealpayCancelRequests;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayPaymentRequest;
use AlphaDirect\Services\RealPayPolicyCancellationService;
use AlphaDirect\Transaction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cancelling a policy must cancel its RealPay debit order.
 *
 * The bug: it did not. Every cancellation journey failed differently — the v8
 * port only touched RealPay when customer_banking.billing read exactly
 * 'RealPay' and then looped the DELETE over every contract the policy had ever
 * held; /policies/{id}/cancel-all made no RealPay call at all; the legacy
 * endpoint queued a row for a cron that is commented out. Back-office cancelled
 * each contract by hand, and until they did the customer kept being debited.
 *
 * What has to hold, and is asserted below:
 *
 *   1. an active contract on a cancelled policy is identified and cancelled
 *   2. the local records follow — contract inactive, cancel request 1,
 *      instalments 'I', transaction CANCELLED, isPaymentCancel set
 *   3. a policy with no RealPay arrangement is a no-op, not an error
 *   4. only ACTIVE contracts are cancelled — a dead one is never re-attempted
 *   5. running it twice cancels once
 *   6. a failed cancellation is recorded at cancel_status 2 for the retry
 *      command, and never throws into the caller
 *   7. a contract RealPay still reports active after the DELETE is reported as
 *      still outstanding, not as success
 *   8. RealPay being unreachable falls back to local records rather than
 *      silently doing nothing
 *   9. a policy whose contract died elsewhere has its stale local rows healed
 *
 * Runs against in-memory SQLite with a fake RealPayController: the service's own
 * logic is pure decision + bookkeeping, and every HTTP call it makes goes
 * through two controller methods that are stubbed here.
 */
class RealPayPolicyCancellationTest extends TestCase
{
    private const CONNECTION = 'realpay_policy_cancel_test';

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
        RealpayPaymentRequest::disableAuditing();
        CustomerBanking::disableAuditing();
        Transaction::disableAuditing();

        // The service serialises per policy through the cache; an in-process
        // store keeps that real without needing redis.
        Config::set('cache.default', 'array');

        Config::set('realpay.cancel_with_policy.enabled', true);
        Config::set('realpay.cancel_with_policy.verify_after_cancel', true);
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
    // The main path
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function cancelling_a_policy_cancels_its_active_realpay_contract(): void
    {
        $this->seedLocalContract('C1001');

        $realpay = $this->fakeRealpay(
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            // After the DELETE, RealPay reports the contract cancelled.
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'C')]]
        );

        $result = $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-immediate');

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertTrue($result['attempted']);
        $this->assertSame(['C1001'], $result['identified']);
        $this->assertSame(['C1001'], $result['cancelled']);
        $this->assertSame([], $result['failed']);
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

        $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-immediate');

        $this->assertSame(0, (int) RealpayClientContracts::where('contract_number', 'C1001')->value('status'));
        $this->assertSame(1, (int) RealpayCancelRequests::where('contract', 'C1001')->value('cancel_status'));
        $this->assertSame('CANCELLED', Transaction::find($transactionId)->status);
        $this->assertSame(
            'I',
            RealpayContractInstallments::where('contractNumber', 'C1001')->value('InstalmentStatus'),
            'a queued instalment must stop reading as active — that is what still takes money'
        );
        $this->assertSame(
            1,
            (int) Policy::find($this->policy->id)->isPaymentCancel,
            'the policy must record that its payment arrangement is cancelled'
        );
    }

    /** @test */
    public function a_contract_with_only_an_active_instalment_still_counts_as_live(): void
    {
        $this->seedLocalContract('C2002');

        // RealPay marks the contract itself 'C' but leaves an instalment 'A' —
        // that instalment still debits the customer.
        $realpay = $this->fakeRealpay(
            ['reachable' => true, 'contracts' => [$this->portalContract('C2002', 'C', 'A')]],
            ['reachable' => true, 'contracts' => []]
        );

        $result = $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-immediate');

        $this->assertSame(['C2002'], $result['identified']);
        $this->assertSame(['C2002'], $result['cancelled']);
    }

    // ──────────────────────────────────────────────────────────────────
    // Policies with nothing to cancel
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function a_policy_with_no_realpay_arrangement_is_a_no_op(): void
    {
        // A DPO policy. Nothing RealPay-shaped exists, so the portal is never
        // even probed — this is the majority of cancellations.
        CustomerBanking::insert([
            'policy_id' => $this->policy->id,
            'billing'   => 'DPO',
        ]);

        $realpay = $this->fakeRealpay(['reachable' => true, 'contracts' => []]);

        $result = $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-immediate');

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertFalse($result['attempted']);
        $this->assertSame(0, $realpay->portalCalls, 'a non-RealPay policy must not cost a RealPay round-trip');
        $this->assertSame([], $realpay->deleted);
    }

    /** @test */
    public function a_realpay_policy_with_no_local_contract_row_is_still_checked_on_the_portal(): void
    {
        // customer_banking says RealPay but no contract was ever recorded
        // locally — a contract created on a run that did not sync back. That is
        // exactly the policy nobody would otherwise cancel.
        CustomerBanking::insert([
            'policy_id' => $this->policy->id,
            'billing'   => 'RealPay',
        ]);

        $realpay = $this->fakeRealpay(
            ['reachable' => true, 'contracts' => [$this->portalContract('C-ORPHAN', 'A')]],
            ['reachable' => true, 'contracts' => []]
        );

        $result = $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-immediate');

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame(['C-ORPHAN'], $result['cancelled']);
    }

    /** @test */
    public function a_dead_contract_is_never_re_attempted(): void
    {
        // The policy's only contract was cancelled long ago. RealPay still
        // lists it, marked 'C'. Sending another DELETE would be rejected and,
        // under the old code, would report the whole cancellation as failed.
        $this->seedLocalContract('C0900', 0);

        $realpay = $this->fakeRealpay(['reachable' => true, 'contracts' => [$this->portalContract('C0900', 'C')]]);

        $result = $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-immediate');

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertFalse($result['attempted']);
        $this->assertSame([], $realpay->deleted);
    }

    // ──────────────────────────────────────────────────────────────────
    // Idempotency
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function cancelling_the_same_policy_twice_cancels_the_contract_once(): void
    {
        $this->seedLocalContract('C1001');

        $realpay = $this->fakeRealpay(
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            ['reachable' => true, 'contracts' => []]
        );

        $service = $this->service($realpay);

        $first = $service->cancelForPolicy($this->policy, 'cancel-immediate');
        $this->assertSame(['C1001'], $first['cancelled']);

        // Second press, or a second journey firing on the same policy.
        $second = $service->cancelForPolicy($this->policy, 'cancel-all');

        $this->assertTrue($second['ok']);
        $this->assertFalse($second['attempted']);
        $this->assertSame([], $second['identified'], 'the second run must find nothing to cancel');
        $this->assertSame(['C1001'], $realpay->deleted, 'exactly one cancellation across both runs');
    }

    /** @test */
    public function a_concurrent_second_cancellation_stands_down_rather_than_cancelling_again(): void
    {
        $this->seedLocalContract('C1001');

        // Stand in for a cancellation still mid-flight: take the per-policy
        // window and never give it back.
        $window = new \ReflectionMethod(RealPayPolicyCancellationService::class, 'beginCancellation');
        $window->setAccessible(true);
        $this->assertTrue($window->invoke($this->service($this->fakeRealpay()), $this->policy));

        $realpay = $this->fakeRealpay(['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]]);

        $second = $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-all');

        $this->assertTrue($second['ok'], 'standing down is not a failure — the other run is doing the work');
        $this->assertStringContainsString('already running', $second['message']);
        $this->assertSame([], $realpay->deleted);
    }

    // ──────────────────────────────────────────────────────────────────
    // Failure is recorded, never thrown
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function a_failed_cancellation_is_queued_for_reprocessing(): void
    {
        $this->seedLocalContract('C1001');

        $realpay = $this->fakeRealpay(['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]]);
        $realpay->deleteSucceeds = false;

        $result = $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-immediate');

        $this->assertFalse($result['ok']);
        $this->assertSame(['C1001'], $result['failed']);
        $this->assertStringContainsString('retry-policy-cancellations', $result['message']);

        $this->assertSame(2, (int) RealpayCancelRequests::where('contract', 'C1001')->value('cancel_status'),
            'cancel_status 2 is the worklist the retry command reads');
        $this->assertSame(1, (int) RealpayClientContracts::where('contract_number', 'C1001')->value('status'),
            'a contract that was not cancelled must not be marked inactive');
        $this->assertSame(0, (int) Policy::find($this->policy->id)->isPaymentCancel,
            'nothing may claim the payment arrangement is stopped while it is not');
    }

    /** @test */
    public function an_exploding_realpay_never_throws_into_the_cancellation_journey(): void
    {
        // The policy is already cancelled and committed by the time we run. A
        // RealPay outage must be reported, not propagated.
        $this->seedLocalContract('C1001');

        $realpay = new class extends RealPayController {
            public function fetchPortalContracts($policy_id, $clientNumber): array
            {
                throw new \RuntimeException('RealPay exploded');
            }
        };

        $result = $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-immediate');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('RealPay exploded', $result['message']);
    }

    /** @test */
    public function a_contract_still_active_after_the_delete_is_reported_as_outstanding(): void
    {
        // RealPay accepted the DELETE but still reports the contract active —
        // it can still take money, so this is not a success.
        $this->seedLocalContract('C1001');

        $realpay = $this->fakeRealpay(
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]]
        );

        $result = $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-immediate');

        $this->assertFalse($result['ok']);
        $this->assertSame(['C1001'], $result['failed']);
        $this->assertStringContainsString('still reports', $result['message']);

        // RealPay's own answer overrides the confirmation the DELETE gave us —
        // the local records must not read "cancelled" while the contract can
        // still debit.
        $this->assertSame(2, (int) RealpayCancelRequests::where('contract', 'C1001')->value('cancel_status'));
        $this->assertSame(1, (int) RealpayClientContracts::where('contract_number', 'C1001')->value('status'));
    }

    // ──────────────────────────────────────────────────────────────────
    // RealPay unreachable
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function an_unreachable_realpay_falls_back_to_the_local_record(): void
    {
        $this->seedLocalContract('C1001');

        $realpay = $this->fakeRealpay(['reachable' => false, 'contracts' => []]);

        $result = $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-immediate');

        $this->assertSame('local records', $result['source']);
        $this->assertSame(['C1001'], $result['identified'], 'the local record is the only evidence left');
        $this->assertSame(['C1001'], $realpay->deleted, 'a DELETE we can still send is worth sending');
    }

    /** @test */
    public function an_unreachable_realpay_does_not_heal_local_records_it_cannot_verify(): void
    {
        // Nothing local looks live and RealPay cannot be asked. Claiming the
        // policy is clear would be inventing evidence.
        $this->seedLocalContract('C0900', 0);
        $this->seedFailedCancelRequest('C0900');

        $realpay = $this->fakeRealpay(['reachable' => false, 'contracts' => []]);

        $result = $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-immediate');

        $this->assertSame([], $result['identified']);
        $this->assertSame(2, (int) RealpayCancelRequests::where('contract', 'C0900')->value('cancel_status'),
            'the outstanding record must survive for the retry command');
    }

    // ──────────────────────────────────────────────────────────────────
    // Converging on RealPay
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function stale_local_records_are_healed_when_realpay_says_the_policy_is_clear(): void
    {
        // An earlier cancellation failed and the contract has since died — by
        // the retry command, by a manual cancel on the portal, or by expiry.
        $this->seedLocalContract('C1001');
        $this->seedActiveInstalment('C1001');
        $this->seedFailedCancelRequest('C1001');

        $realpay = $this->fakeRealpay(['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'C')]]);

        $result = $this->service($realpay)->cancelForPolicy($this->policy, 'retry-command');

        $this->assertTrue($result['ok']);
        $this->assertSame([], $realpay->deleted, 'nothing is live, so nothing is cancelled again');

        $this->assertSame(0, (int) RealpayClientContracts::where('contract_number', 'C1001')->value('status'));
        $this->assertSame(1, (int) RealpayCancelRequests::where('contract', 'C1001')->value('cancel_status'),
            'the policy must stop showing as an outstanding cancellation');
        $this->assertSame('I', RealpayContractInstallments::where('contractNumber', 'C1001')->value('InstalmentStatus'));
    }

    /** @test */
    public function a_confirmed_cancellation_is_never_regressed_to_failed(): void
    {
        // The contract WAS cancelled. A later verification failing does not
        // un-cancel it, and must not put it back on the worklist.
        $this->seedLocalContract('C1001');
        RealpayCancelRequests::insert([
            'policy_id'     => $this->policy->id,
            'contract'      => 'C1001',
            'cancel_status' => 1,
        ]);

        $realpay = $this->fakeRealpay(['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]]);
        $realpay->deleteSucceeds = false;

        $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-immediate');

        $this->assertSame(1, (int) RealpayCancelRequests::where('contract', 'C1001')->value('cancel_status'));
    }

    // ──────────────────────────────────────────────────────────────────
    // Escape hatch
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function the_automatic_cancellation_can_be_switched_off(): void
    {
        Config::set('realpay.cancel_with_policy.enabled', false);
        $this->seedLocalContract('C1001');

        $realpay = $this->fakeRealpay(['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]]);

        $result = $this->service($realpay)->cancelForPolicy($this->policy, 'cancel-immediate');

        $this->assertTrue($result['ok']);
        $this->assertSame([], $realpay->deleted, 'the switch means no RealPay calls at all');
    }

    // ──────────────────────────────────────────────────────────────────
    // Callers hand this three different shapes of "policy"
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function a_bare_id_or_a_partial_row_resolves_to_the_full_policy(): void
    {
        $this->seedLocalContract('C1001');

        // deleteRealpayContractByNumber needs policyNumber and product_id;
        // CancelPaymentsForPolicy is handed rows that carry neither.
        $partial = (object) ['policy_id' => $this->policy->id];

        $realpay = $this->fakeRealpay(
            ['reachable' => true, 'contracts' => [$this->portalContract('C1001', 'A')]],
            ['reachable' => true, 'contracts' => []]
        );

        $result = $this->service($realpay)->cancelForPolicy($partial, 'cancel-payments-for-policy');

        $this->assertSame(['C1001'], $result['cancelled']);
        $this->assertSame($this->policy->policyNumber, $realpay->deletedFor[0]);
    }

    /** @test */
    public function the_container_can_build_the_service_the_cancellation_flows_resolve(): void
    {
        // Every caller reaches it as app(RealPayPolicyCancellationService::class)
        // with both constructor arguments left to the container. A service that
        // cannot be built there would take down the cancellation journeys it is
        // wired into.
        $this->assertInstanceOf(
            RealPayPolicyCancellationService::class,
            app(RealPayPolicyCancellationService::class)
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Fixtures
    // ──────────────────────────────────────────────────────────────────

    private function service(RealPayController $realpay): RealPayPolicyCancellationService
    {
        return new RealPayPolicyCancellationService($realpay);
    }

    /**
     * A RealPayController whose outbound calls are canned.
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
            /** @var array<int, string> policyNumber each DELETE was addressed to. */
            public array $deletedFor = [];
            public int $portalCalls = 0;
            public bool $deleteSucceeds = true;

            public function fetchPortalContracts($policy_id, $clientNumber): array
            {
                $this->portalCalls++;

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

                $this->deleted[]    = (string) $contractNumber;
                $this->deletedFor[] = (string) ($policy->policyNumber ?? '');

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

    private function seedLocalContract(string $contractNumber, int $status = 1): void
    {
        RealpayClientContracts::insert([
            'policy_id'       => $this->policy->id,
            'contract_number' => $contractNumber,
            'client_number'   => $this->policy->policyNumber,
            'status'          => $status,
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

    private function seedFailedCancelRequest(string $contractNumber): void
    {
        RealpayCancelRequests::insert([
            'policy_id'     => $this->policy->id,
            'contract'      => $contractNumber,
            'cancel_status' => 2,
        ]);
    }

    private function makePolicy(): Policy
    {
        $id = DB::table('policies')->insertGetId([
            'policyNumber'    => 'AD-TEST-0001',
            'product_id'      => 1,
            'customer_id'     => 1,
            // Cancelled — this service only ever runs on a cancelled policy.
            'status'          => 2,
            'isPaymentCancel' => 0,
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
            $table->integer('status')->nullable();
            $table->integer('isPaymentCancel')->nullable();
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
            $table->timestamps();
        });

        $schema->create('realpay_payment_request', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('contract')->nullable();
            $table->integer('status')->nullable();
            $table->timestamps();
        });

        $schema->create('customer_banking', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('billing')->nullable();
            $table->string('contract_number')->nullable();
            $table->integer('bankName')->nullable();
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
