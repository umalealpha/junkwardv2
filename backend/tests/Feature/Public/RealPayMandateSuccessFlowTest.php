<?php

namespace Tests\Feature\Public;

use AlphaDirect\Policy;
use AlphaDirect\RealpayMandate;
use AlphaDirect\RealpayMandateEvent;
use AlphaDirect\Services\RealPayMandateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The RealPay mandate lifecycle around a payment result.
 *
 * Covers the five scenarios the change has to get right:
 *
 *   1. new payment      — a registered contract is a mandate, not an active one
 *   2. successful payment — InstalmentStatus 'S' marks the mandate active
 *   3. failed payment   — 'F' records the failure and marks nothing successful
 *   4. pending payment  — 'W' / 'A' / 'R' do not run the success action at all
 *   5. redo payment / existing mandate — reuse rather than a second contract
 *
 * Plus the two invariants that matter most in production:
 *   - a replayed webhook is a no-op (ProcessWebhookBuffer replays every minute)
 *   - the mandate layer never writes policy.status
 *
 * These run against an in-memory SQLite schema rather than the shared RDS the
 * rest of the suite uses. The service is pure state manipulation with no
 * outbound RealPay call, so a throwaway database exercises it completely — and
 * it keeps a test that writes mandate + policy rows off a live database.
 */
class RealPayMandateSuccessFlowTest extends TestCase
{
    private const CONNECTION = 'realpay_mandate_test';

    private RealPayMandateService $service;
    private string $previousConnection;

    protected function setUp(): void
    {
        parent::setUp();

        // Auditing writes to an `audits` table on save; not what is under test.
        Config::set('audit.enabled', false);
        RealpayMandate::disableAuditing();
        RealpayMandateEvent::disableAuditing();
        Policy::disableAuditing();

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

        $this->service = new RealPayMandateService();
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection($this->previousConnection);
        DB::purge(self::CONNECTION);

        parent::tearDown();
    }

    /**
     * Only the columns these code paths touch. A full mirror of `policies`
     * would be 200+ columns of noise, and Policy::booted() only needs the term
     * dates it back-fills for MIS-prefixed numbers.
     */
    private function buildSchema(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber', 32)->nullable();
            $table->integer('status')->default(0);
            $table->integer('product_id')->nullable();
            $table->integer('customer_id')->nullable();
            $table->decimal('premium', 12, 2)->nullable();
            $table->decimal('first_premium', 12, 2)->nullable();
            $table->date('policyActivatedDate')->nullable();
            $table->date('billingStartDate')->nullable();
            $table->date('term_start_date')->nullable();
            $table->date('term_end_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });

        Schema::create('realpay_client_contracts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('client_number', 64)->nullable();
            $table->string('contract_number', 64)->nullable();
            $table->integer('rate_id')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        // Mirrors the two migrations under test.
        Schema::create('realpay_mandates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id');
            $table->string('policy_number', 32);
            $table->string('contract_number', 64)->nullable();
            $table->string('provider_mandate_id', 128)->nullable();
            $table->string('provider_reference', 128)->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('mandate_type', 8)->nullable();
            $table->string('tracking_code', 8)->nullable();
            $table->decimal('collection_amount', 12, 2)->nullable();
            $table->decimal('max_collection_amount', 12, 2)->nullable();
            $table->unsignedTinyInteger('collection_day')->nullable();
            $table->date('first_collection_date')->nullable();
            $table->string('frequency_code', 8)->default('MNTH');
            $table->text('emandate_url')->nullable();
            $table->timestamp('emandate_url_expires_at')->nullable();
            $table->timestamp('redirected_at')->nullable();
            $table->timestamp('authenticated_at')->nullable();
            $table->timestamp('first_collection_succeeded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->text('provider_payload')->nullable();
            $table->timestamps();
            $table->unique(['policy_id', 'contract_number'], 'uq_realpay_mandates_policy_contract');
        });

        Schema::create('realpay_mandate_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('mandate_id')->nullable();
            $table->string('event_id', 128)->nullable();
            $table->string('event_type', 64);
            $table->string('policy_number', 32)->nullable();
            $table->string('contract_number', 64)->nullable();
            $table->text('payload');
            $table->string('processing_status', 24)->default('pending');
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('deliveries')->default(1);
            $table->timestamps();
            $table->unique('event_id', 'uq_realpay_mandate_events_event_id');
        });
    }

    // ──────────────────────────────────────────────────────────────────
    // Fixtures
    // ──────────────────────────────────────────────────────────────────

    private function policy(string $policyNumber = 'MIS2026000123', int $status = 0): Policy
    {
        return Policy::create([
            'policyNumber' => $policyNumber,
            'status'       => $status,
            'product_id'   => 8,
            'customer_id'  => 501,
            'premium'      => 250.00,
        ]);
    }

    /** The local contract row the existing RealPay code writes via addLog(). */
    private function clientContract(Policy $policy, string $contractNumber, int $status = 1): void
    {
        DB::table('realpay_client_contracts')->insert([
            'policy_id'       => $policy->id,
            'client_number'   => $policy->policyNumber,
            'contract_number' => $contractNumber,
            'status'          => $status,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    /** A RealPay instalment webhook payload for one collection result. */
    private function instalment(Policy $policy, string $contractNumber, string $status, int $sequence = 1, string $reference = 'REF-0001'): array
    {
        return [
            'client_number'        => $policy->policyNumber,
            'contract_number'      => $contractNumber,
            'instalment_status'    => $status,
            'instalment_reference' => $reference,
            'sequence'             => $sequence,
            'amount'               => 250.00,
            'action_date'          => now()->format('Y-m-d'),
            'payload'              => [
                'ClientNumber'      => $policy->policyNumber,
                'ContractNumber'    => $contractNumber,
                'InstalmentStatus'  => $status,
                'TrackingCode'      => '44',
                'InstalmentAmount'  => 250.00,
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // 1. New payment
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function new_payment_registers_a_mandate_but_does_not_make_it_active(): void
    {
        $policy = $this->policy();

        $mandate = $this->service->claim($policy, '1/1', [
            'tracking_code'  => '44',
            'collection_day' => 15,
            'frequency_code' => 'MNTH',
        ]);

        $this->assertNotNull($mandate);
        $this->assertSame(RealpayMandate::PENDING, $mandate->status,
            'A claimed mandate starts pending — nothing has been sent to RealPay yet.');

        // RealPay accepted the ContractPostRequest.
        $this->service->markRegistered($mandate, ['provider_reference' => 'CALLSEQ-9']);

        $mandate->refresh();
        $this->assertSame(RealpayMandate::REGISTERED, $mandate->status);
        $this->assertNull($mandate->first_collection_succeeded_at,
            'A registered contract has collected nothing yet.');
        $this->assertSame('CALLSEQ-9', $mandate->provider_reference);

        $this->assertSame(0, (int) $policy->fresh()->status,
            'Creating a mandate must never activate the policy.');
    }

    // ──────────────────────────────────────────────────────────────────
    // 2. Successful payment
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function successful_instalment_marks_the_mandate_active_and_logs_the_event(): void
    {
        $policy = $this->policy();
        $this->clientContract($policy, '1/1');

        $mandate = $this->service->claim($policy, '1/1');
        $this->service->markRegistered($mandate);

        $result = $this->service->applyInstalmentOutcome($this->instalment($policy, '1/1', 'S'));

        $this->assertSame('activated', $result['outcome']);
        $this->assertSame((int) $policy->id, $result['policy_id']);

        $mandate->refresh();
        $this->assertSame(RealpayMandate::ACTIVE, $mandate->status);
        $this->assertNotNull($mandate->first_collection_succeeded_at,
            'The first successful collection is the timestamp a dispute is answered from.');
        // Compared as a number: SQLite hands back `250` where MySQL DECIMAL(12,2)
        // hands back `250.00`, and the amount is what matters here, not its
        // string formatting.
        $this->assertEquals(250.00, (float) $mandate->collection_amount);
        $this->assertSame('REF-0001', $mandate->provider_reference);

        // The payload and the decision are both recorded.
        $event = RealpayMandateEvent::where('contract_number', '1/1')->first();
        $this->assertNotNull($event, 'Every payload reaching the mandate layer is recorded.');
        $this->assertSame(RealpayMandateEvent::APPLIED, $event->processing_status);
        $this->assertSame('instalment.success', $event->event_type);
        $this->assertSame((int) $mandate->id, (int) $event->mandate_id);
        $this->assertSame('S', $event->payload['InstalmentStatus']);
    }

    /** @test */
    public function successful_instalment_does_not_touch_policy_status(): void
    {
        $policy = $this->policy();
        $this->clientContract($policy, '1/1');
        $this->service->markRegistered($this->service->claim($policy, '1/1'));

        $this->service->applyInstalmentOutcome($this->instalment($policy, '1/1', 'S'));

        $this->assertSame(0, (int) $policy->fresh()->status,
            'Policy activation belongs to RealPayController::updateInstallment(). '
            . 'A second code path that can set status = 1 is how activation dates drift.');
    }

    /** @test */
    public function a_successful_collection_backfills_a_mandate_for_a_contract_created_before_this_table_existed(): void
    {
        // Every contract already live in production has no mandate row. A
        // successful collection is proof the contract was registered, so the row
        // is seeded `registered` and then activated — the event is not lost.
        $policy = $this->policy();
        $this->clientContract($policy, '1/1');

        $result = $this->service->applyInstalmentOutcome($this->instalment($policy, '1/1', 'S'));

        $this->assertSame('activated', $result['outcome']);

        $mandate = RealpayMandate::where('policy_id', $policy->id)->first();
        $this->assertNotNull($mandate);
        $this->assertSame(RealpayMandate::ACTIVE, $mandate->status);
        $this->assertSame('1/1', $mandate->contract_number);
    }

    /** @test */
    public function replayed_successful_webhook_is_a_no_op(): void
    {
        // ProcessWebhookBuffer replays the same instalment payload every minute.
        $policy = $this->policy();
        $this->clientContract($policy, '1/1');
        $this->service->markRegistered($this->service->claim($policy, '1/1'));

        $first = $this->service->applyInstalmentOutcome($this->instalment($policy, '1/1', 'S'));
        $stamp = RealpayMandate::find($first['mandate_id'])->first_collection_succeeded_at;

        $second = $this->service->applyInstalmentOutcome($this->instalment($policy, '1/1', 'S'));

        $this->assertSame('activated', $first['outcome']);
        $this->assertSame('already_active', $second['outcome']);
        $this->assertSame(1, RealpayMandate::where('policy_id', $policy->id)->count(),
            'A replay must not open a second mandate.');
        $this->assertSame(
            $stamp->toDateTimeString(),
            RealpayMandate::find($first['mandate_id'])->first_collection_succeeded_at->toDateTimeString(),
            'The first-collection timestamp is stamped once and never moved.'
        );

        $event = RealpayMandateEvent::where('contract_number', '1/1')->first();
        $this->assertSame(1, RealpayMandateEvent::where('contract_number', '1/1')->count(),
            'A redelivery is recognised on the event id, not appended.');
        $this->assertSame(2, (int) $event->deliveries);
        $this->assertSame(RealpayMandateEvent::DUPLICATE, $event->processing_status);
    }

    // ──────────────────────────────────────────────────────────────────
    // 3. Failed payment
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function failed_instalment_does_not_run_the_success_action(): void
    {
        $policy = $this->policy();
        $this->clientContract($policy, '1/1');
        $mandate = $this->service->claim($policy, '1/1');
        $this->service->markRegistered($mandate);

        $result = $this->service->applyInstalmentOutcome($this->instalment($policy, '1/1', 'F'));

        $this->assertSame('not_success', $result['outcome']);

        $mandate->refresh();
        $this->assertSame(RealpayMandate::REGISTERED, $mandate->status,
            'A declined collection does not revoke the authority to collect, and does not activate it either.');
        $this->assertNull($mandate->first_collection_succeeded_at);
        $this->assertSame(1, (int) $mandate->attempts);
        $this->assertStringContainsString('returned F', (string) $mandate->last_error);

        $event = RealpayMandateEvent::where('contract_number', '1/1')->first();
        $this->assertSame(RealpayMandateEvent::IGNORED, $event->processing_status);
        $this->assertSame('instalment.failed', $event->event_type);

        $this->assertSame(0, (int) $policy->fresh()->status);
    }

    /** @test */
    public function pending_instalment_statuses_do_not_run_the_success_action(): void
    {
        // 'W' processing, 'A' scheduled, 'R' retry pending — none of these is
        // money received, so none may mark a mandate active.
        foreach (['W', 'A', 'R', 'D', 'Z'] as $i => $status) {
            $policy = $this->policy('MIS20260001' . $i);
            $contract = $policy->id . '/1';
            $this->clientContract($policy, $contract);
            $mandate = $this->service->claim($policy, $contract);
            $this->service->markRegistered($mandate);

            $result = $this->service->applyInstalmentOutcome(
                $this->instalment($policy, $contract, $status, 1, 'REF-' . $status)
            );

            $this->assertSame('not_success', $result['outcome'],
                "InstalmentStatus '{$status}' must not reach the success path.");
            $this->assertSame(RealpayMandate::REGISTERED, $mandate->fresh()->status,
                "InstalmentStatus '{$status}' must leave the mandate as it was.");
            $this->assertNull($mandate->fresh()->first_collection_succeeded_at);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // 4. RealPay API failure
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function a_realpay_failure_never_leaves_a_mandate_looking_complete(): void
    {
        $policy = $this->policy();
        $mandate = $this->service->claim($policy, '1/1');

        $this->service->markFailed($mandate, 'ContractPostRequest failed: cURL timeout');

        $mandate->refresh();
        $this->assertSame(RealpayMandate::FAILED, $mandate->status);
        $this->assertTrue($mandate->isTerminal());
        $this->assertFalse($mandate->isCollectable(),
            'A failed attempt must not read as a live authority to collect.');
        $this->assertStringContainsString('cURL timeout', (string) $mandate->last_error);
        $this->assertSame(1, (int) $mandate->attempts);

        // Terminal really is terminal: nothing can quietly promote it.
        $this->expectException(\DomainException::class);
        $mandate->moveTo(RealpayMandate::ACTIVE);
    }

    /** @test */
    public function a_collection_against_a_failed_mandate_opens_a_fresh_row_rather_than_reviving_it(): void
    {
        $policy = $this->policy();
        $this->clientContract($policy, '1/2');

        $failed = $this->service->claim($policy, '1/1');
        $this->service->markFailed($failed, 'first attempt rejected');

        // A later attempt got a new contract number and collected.
        $result = $this->service->applyInstalmentOutcome($this->instalment($policy, '1/2', 'S'));

        $this->assertSame('activated', $result['outcome']);
        $this->assertSame(RealpayMandate::FAILED, $failed->fresh()->status,
            'The failed attempt stays failed.');
        $this->assertNotSame((int) $failed->id, (int) $result['mandate_id']);
    }

    // ──────────────────────────────────────────────────────────────────
    // 5. Redo payment / existing mandate
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function claiming_twice_for_one_contract_returns_the_same_mandate(): void
    {
        // The front end has no duplicate-submit guard, so assume every endpoint
        // is called twice.
        $policy = $this->policy();

        $first  = $this->service->claim($policy, '1/1');
        $second = $this->service->claim($policy, '1/1');

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(1, RealpayMandate::where('policy_id', $policy->id)->count());
    }

    /** @test */
    public function redo_payment_is_refused_when_a_mandate_is_already_collecting(): void
    {
        $policy = $this->policy();
        $this->clientContract($policy, '1/1');
        $this->service->markRegistered($this->service->claim($policy, '1/1'));
        $this->service->applyInstalmentOutcome($this->instalment($policy, '1/1', 'S'));

        $guard = $this->service->guardContractCreation((int) $policy->id);

        $this->assertTrue($guard['block'],
            'An active mandate has already collected — a second contract is a double debit.');
        $this->assertSame(RealpayMandate::ACTIVE, $guard['mandate']->status);
        $this->assertStringContainsString('already holds a active mandate', (string) $guard['reason']);
    }

    /** @test */
    public function a_registered_mandate_only_blocks_a_redo_when_the_setting_is_enabled(): void
    {
        // Registered / redirected / authenticated states can be stale if a
        // contract was cancelled outside the paths that report back here, and a
        // false "already active" permanently blocks reprocessing. Off by default,
        // shadow-logged, one flag to enforce.
        $policy = $this->policy();
        $this->service->markRegistered($this->service->claim($policy, '1/1'));

        Config::set('realpay.mandate.block_on_registered', false);
        $this->assertFalse($this->service->guardContractCreation((int) $policy->id)['block']);

        Config::set('realpay.mandate.block_on_registered', true);
        $enforced = $this->service->guardContractCreation((int) $policy->id);
        $this->assertTrue($enforced['block']);
        $this->assertSame(RealpayMandate::REGISTERED, $enforced['mandate']->status);
    }

    /** @test */
    public function a_cancelled_mandate_does_not_block_a_new_contract(): void
    {
        $policy = $this->policy();
        $mandate = $this->service->claim($policy, '1/1');
        $this->service->markRegistered($mandate);

        $this->assertSame(1, $this->service->markCancelled((int) $policy->id, '1/1', 'customer changed bank'));

        $mandate->refresh();
        $this->assertSame(RealpayMandate::CANCELLED, $mandate->status);
        $this->assertNotNull($mandate->cancelled_at);

        $this->assertFalse($this->service->guardContractCreation((int) $policy->id)['block'],
            'Cancelling must release the policy, or a customer can never change bank again.');
        $this->assertNull($this->service->collectableMandateFor((int) $policy->id));
    }

    /** @test */
    public function a_cancelled_contract_row_stops_its_mandate_blocking_a_redo(): void
    {
        // The failure this guards: cancelling a contract left the mandate
        // `active`, and block_on_active (which ships ENABLED) then refused the
        // policy a replacement contract forever. markCancelled() is now wired
        // into the cancel path; this is the second line of defence, for a
        // contract cancelled somewhere that does not report back here.
        $policy = $this->policy();
        $this->clientContract($policy, '1/1');
        $this->service->markRegistered($this->service->claim($policy, '1/1'));
        $this->service->applyInstalmentOutcome($this->instalment($policy, '1/1', 'S'));

        // Sanity: it blocks while the contract is genuinely live.
        $this->assertTrue($this->service->guardContractCreation((int) $policy->id)['block']);

        // The contract is cancelled, but nothing updated the mandate.
        DB::table('realpay_client_contracts')
            ->where('policy_id', $policy->id)
            ->where('contract_number', '1/1')
            ->update(['status' => 0]);

        $this->assertSame(RealpayMandate::ACTIVE,
            RealpayMandate::where('policy_id', $policy->id)->first()->status,
            'Precondition: the mandate is still stale-active — that is the whole point.');

        $this->assertNull($this->service->collectableMandateFor((int) $policy->id),
            'A mandate whose contract is cancelled is not collecting anything.');
        $this->assertFalse($this->service->guardContractCreation((int) $policy->id)['block'],
            'A cancelled contract must not lock the policy out of a replacement.');
    }

    /** @test */
    public function a_mandate_with_no_contract_row_still_blocks(): void
    {
        // The narrowness guarantee. A mandate is only discounted on POSITIVE
        // evidence of cancellation — an existing contract row that is inactive.
        // A mandate with no contract row (claimed before creation, or backfilled
        // from a webhook) must still block, or the change would quietly weaken
        // the double-debit guard it sits next to.
        $policy = $this->policy();
        $this->service->markRegistered($this->service->claim($policy, '1/1'));
        $this->service->applyInstalmentOutcome($this->instalment($policy, '1/1', 'S'));

        $this->assertSame(0, DB::table('realpay_client_contracts')->count(),
            'Precondition: no contract row exists for this mandate.');

        $this->assertNotNull($this->service->collectableMandateFor((int) $policy->id));
        $this->assertTrue($this->service->guardContractCreation((int) $policy->id)['block'],
            'Absent evidence of cancellation, an active mandate still blocks.');
    }

    /** @test */
    public function creating_a_replacement_contract_cancels_the_superseded_mandate(): void
    {
        $policy = $this->policy();

        $old = $this->service->claim($policy, '1/1');
        $this->service->markRegistered($old);

        $new = $this->service->claim($policy, '1/2');
        $this->service->markRegistered($new);

        // Mirrors what the controller does after marking the old
        // realpay_client_contracts rows status = 0.
        $cancelled = $this->service->cancelSupersededMandates((int) $policy->id, '1/2', 'Superseded by contract 1/2');

        $this->assertSame(1, $cancelled);
        $this->assertSame(RealpayMandate::CANCELLED, $old->fresh()->status);
        $this->assertSame(RealpayMandate::REGISTERED, $new->fresh()->status,
            'The replacement must survive its own supersede sweep.');
    }

    // ──────────────────────────────────────────────────────────────────
    // Resolution + isolation
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function an_instalment_for_an_unknown_policy_is_recorded_and_dropped(): void
    {
        $result = $this->service->applyInstalmentOutcome([
            'client_number'        => 'MIS9999999999',
            'contract_number'      => '999999/1',
            'instalment_status'    => 'S',
            'instalment_reference' => 'REF-ORPHAN',
            'sequence'             => 1,
            'amount'               => 100.00,
            'action_date'          => now()->format('Y-m-d'),
            'payload'              => [],
        ]);

        $this->assertSame('no_policy', $result['outcome']);
        $this->assertSame(0, RealpayMandate::count(), 'No policy means no mandate to invent.');

        $event = RealpayMandateEvent::where('contract_number', '999999/1')->first();
        $this->assertNotNull($event, 'The payload is kept even when it cannot be applied.');
        $this->assertSame(RealpayMandateEvent::FAILED, $event->processing_status);
    }

    /** @test */
    public function the_policy_is_resolved_from_the_contract_number_convention_when_no_contract_row_exists(): void
    {
        // RealpayClientContracts::getContractNumber() mints "{policy_id}/{n}".
        $policy = $this->policy('MIS2026000999');

        $result = $this->service->applyInstalmentOutcome(
            $this->instalment($policy, $policy->id . '/3', 'S')
        );

        $this->assertSame('activated', $result['outcome']);
        $this->assertSame((int) $policy->id, $result['policy_id']);
    }

    /** @test */
    public function mandate_tracking_off_is_a_clean_no_op(): void
    {
        Config::set('realpay.mandate.enabled', false);
        $service = new RealPayMandateService();

        $policy = $this->policy();
        $this->clientContract($policy, '1/1');

        $this->assertNull($service->claim($policy, '1/1'));
        $this->assertSame('skipped', $service->applyInstalmentOutcome($this->instalment($policy, '1/1', 'S'))['outcome']);
        $this->assertSame(0, RealpayMandate::count());
        $this->assertSame(0, RealpayMandateEvent::count());
        $this->assertFalse($service->guardContractCreation((int) $policy->id)['block']);
    }

    /** @test */
    public function status_endpoint_distinguishes_an_active_mandate_from_an_active_policy(): void
    {
        $policy = $this->policy();
        $this->clientContract($policy, '1/1');
        $this->service->markRegistered($this->service->claim($policy, '1/1'));
        $this->service->applyInstalmentOutcome($this->instalment($policy, '1/1', 'S'));

        $status = $this->service->statusFor((string) $policy->policyNumber);

        $this->assertSame(RealpayMandate::ACTIVE, $status['mandate_status']);
        $this->assertSame(0, $status['policy_status']);
        $this->assertSame('await_activation', $status['next_action'],
            'An active mandate is not an active policy — the front end must not promise otherwise.');
    }

    // ──────────────────────────────────────────────────────────────────
    // State machine
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function the_state_machine_refuses_to_regress_an_active_mandate(): void
    {
        $policy = $this->policy();
        $mandate = $this->service->claim($policy, '1/1');
        $mandate->moveTo(RealpayMandate::REGISTERED);
        $mandate->moveTo(RealpayMandate::ACTIVE);

        // Re-entering the current state is the silent no-op that makes replays safe.
        $mandate->moveTo(RealpayMandate::ACTIVE);
        $this->assertSame(RealpayMandate::ACTIVE, $mandate->fresh()->status);

        $this->expectException(\DomainException::class);
        $mandate->moveTo(RealpayMandate::PENDING);
    }

    /** @test */
    public function a_pending_mandate_cannot_jump_straight_to_active(): void
    {
        // No registered contract means nothing could have collected.
        $policy = $this->policy();
        $mandate = $this->service->claim($policy, '1/1');

        $this->assertSame(RealpayMandate::PENDING, $mandate->status);
        $this->assertFalse($mandate->canMoveTo(RealpayMandate::ACTIVE));

        $this->expectException(\DomainException::class);
        $mandate->moveTo(RealpayMandate::ACTIVE);
    }
}
