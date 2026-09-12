<?php

namespace Tests\Feature\Api;

use AlphaDirect\Events\ChargeTokenRecurrentEvent;
use AlphaDirect\Events\CreateTokenEvent;
use AlphaDirect\Events\PullAccountEvent;
use AlphaDirect\Events\VerifyTokenEvent;
use AlphaDirect\Services\PayNowService;
use AlphaDirect\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Collect Now — selecting multiple outstanding premiums (MIS policies).
 *
 * Covers the acceptance criteria for the feature:
 *   - the outstanding premiums are listed with their status
 *   - one or more can be selected and are totalled server-side
 *   - only the selected premiums are collected; the rest are untouched
 *   - the debit is refused unless it was explicitly confirmed
 *   - the attempt and its result land in the transaction history
 *   - duplicate / unauthorised deductions are refused (already-settled rows,
 *     ids belonging to another policy, a total that no longer matches)
 *
 * NO GATEWAY IS CONTACTED. The DPO mandate events are unregistered and replaced
 * with closures returning a success payload, so PayNowService walks its real
 * charge path (createToken -> charge -> verify -> settle) without a live debit.
 * That also means the RealPay branch is never entered here — its behaviour on a
 * one-off post is asserted in the RealPay suite.
 *
 * Fixtures are inserted and removed by hand (shared MySQL — RefreshDatabase is
 * not an option), matching the convention in tests/Feature/Api.
 */
class CollectNowOutstandingPremiumsTest extends TestCase
{
    private const PREMIUM = 350.00;

    private ?User $user = null;
    private int $customerId;
    private int $misPolicyId;
    private string $misPolicyNumber;
    private int $domPolicyId;
    private string $domPolicyNumber;
    /** scheduled_transactions ids, installment 1..4 */
    private array $scheduleIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        set_time_limit(0);

        $suffix = substr((string) microtime(true), -6);
        $this->misPolicyNumber = 'MIS2099CN' . $suffix;
        $this->domPolicyNumber = 'DOM2099CN' . $suffix;

        $this->customerId = DB::table('customer')->insertGetId([
            'firstName'  => 'Collect',
            'lastName'   => 'Tester',
            'cellphone'  => '71000003',
            'email'      => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->misPolicyId = $this->makePolicy($this->misPolicyNumber);
        $this->domPolicyId = $this->makePolicy($this->domPolicyNumber);

        // Four outstanding installments: two failed, two pending; the last one
        // is not yet due. Every row carries the DPO mandate tokens, which is how
        // PayNowService resolves the gateway.
        $statuses = [3, 3, 0, 0];
        foreach ($statuses as $i => $status) {
            $this->scheduleIds[] = DB::table('scheduled_transactions')->insertGetId([
                'policy_id'          => $this->misPolicyId,
                'policy_number'      => $this->misPolicyNumber,
                'customer_id'        => $this->customerId,
                'installment'        => $i + 1,
                'retry_count'        => $status === 3 ? 1 : 0,
                'premium'            => self::PREMIUM,
                'email'              => 'collect-tester@yopmail.com',
                'token'              => 'tok-' . $i . $suffix,
                'subscription_token' => 'sub-' . $i . $suffix,
                'customer_token'     => 'cus-' . $i . $suffix,
                'billing_date'       => now()->addMonths($i - 2)->format('Y-m-d H:i:s'),
                'payment_method'     => 'DPO',
                'status'             => $status,
                'reason'             => $status === 3 ? 'INSUFFICIENT FUNDS' : null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }

        $this->user = User::query()->first() ?? new User();
        Sanctum::actingAs($this->user, ['*']);
    }

    protected function tearDown(): void
    {
        $policyIds     = [$this->misPolicyId, $this->domPolicyId];
        $policyNumbers = [$this->misPolicyNumber, $this->domPolicyNumber];

        DB::table('payment_collection_events')->whereIn('policy_id', $policyIds)->delete();
        DB::table('payment_transactions')->whereIn('policyNumber', $policyNumbers)->delete();
        DB::table('scheduled_transactions')->whereIn('policy_number', $policyNumbers)->delete();
        DB::table('activity_log')->where('subject_type', 'like', '%Policy')->whereIn('subject_id', $policyIds)->delete();
        DB::table('audits')->where('auditable_type', 'like', '%Policy')->whereIn('auditable_id', $policyIds)->delete();
        DB::table('policies')->whereIn('id', $policyIds)->delete();
        DB::table('customer')->where('id', $this->customerId)->delete();

        parent::tearDown();
    }

    private function makePolicy(string $policyNumber): int
    {
        return DB::table('policies')->insertGetId([
            'policyNumber' => $policyNumber,
            'customer_id'  => $this->customerId,
            'product_id'   => 1,
            'status'       => 1,
            'premium'      => self::PREMIUM,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /**
     * Swap the DPO mandate events for closures that report success.
     * Event::forget() drops the real listeners, so no DPO call can happen.
     */
    private function stubDpoGatewaySuccess(string $token = 'STUBTOKEN123'): void
    {
        foreach ([CreateTokenEvent::class, ChargeTokenRecurrentEvent::class, VerifyTokenEvent::class, PullAccountEvent::class] as $event) {
            Event::forget($event);
        }

        Event::listen(CreateTokenEvent::class, fn() => [
            'status' => 1, 'TransactionToken' => $token, 'token' => $token, 'reason' => 'ok',
        ]);
        Event::listen(ChargeTokenRecurrentEvent::class, fn() => ['status' => 1, 'reason' => 'charged']);
        Event::listen(VerifyTokenEvent::class, fn() => ['status' => 1, 'reason' => 'verified']);
        Event::listen(PullAccountEvent::class, fn() => ['status' => 1]);
    }

    private function scheduleStatus(int $scheduleId): int
    {
        return (int) DB::table('scheduled_transactions')->where('id', $scheduleId)->value('status');
    }

    // ──────────────────────────────────────────────────────────────

    /** @test */
    public function outstanding_endpoint_lists_every_outstanding_premium_with_its_status(): void
    {
        $response = $this->getJson("/api/v1/policies/{$this->misPolicyId}/collect-now/outstanding");

        $response->assertOk();
        $body = $response->json();

        $this->assertTrue($body['policy']['isMis']);
        $this->assertTrue($body['policy']['selectionSupported']);
        $this->assertCount(4, $body['data']);
        $this->assertSame(4, $body['totals']['count']);
        $this->assertEquals(4 * self::PREMIUM, $body['totals']['amount']);

        // Oldest first, statuses surfaced, and the future installment flagged.
        $this->assertSame(1, $body['data'][0]['installment']);
        $this->assertSame('Failed', $body['data'][0]['status']);
        $this->assertSame('INSUFFICIENT FUNDS', $body['data'][0]['reason']);
        $this->assertSame('Pending', $body['data'][3]['status']);
        $this->assertTrue($body['data'][0]['isDue']);
        $this->assertFalse($body['data'][3]['isDue']);
    }

    /** @test */
    public function a_settled_premium_is_not_offered_as_outstanding(): void
    {
        DB::table('scheduled_transactions')->where('id', $this->scheduleIds[0])->update(['status' => 2]);

        $ids = collect($this->getJson("/api/v1/policies/{$this->misPolicyId}/collect-now/outstanding")->json('data'))
            ->pluck('id')->all();

        $this->assertNotContains($this->scheduleIds[0], $ids);
        $this->assertCount(3, $ids);
    }

    /** @test */
    public function only_the_selected_premiums_are_collected_and_settled(): void
    {
        $this->stubDpoGatewaySuccess('TOKENSELECTED');

        $selected = [$this->scheduleIds[0], $this->scheduleIds[1]];
        $expected = 2 * self::PREMIUM;

        $response = $this->postJson("/api/v1/policies/{$this->misPolicyId}/collect-now", [
            'schedule_ids'    => $selected,
            'expected_amount' => $expected,
            'confirmed'       => true,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success'            => true,
            'method'             => 'DPO',
            'premiums_collected' => 2,
        ]);
        $this->assertEquals($expected, $response->json('total_amount'));

        // Selected premiums settled; unselected ones untouched.
        $this->assertSame(2, $this->scheduleStatus($this->scheduleIds[0]));
        $this->assertSame(2, $this->scheduleStatus($this->scheduleIds[1]));
        $this->assertSame(0, $this->scheduleStatus($this->scheduleIds[2]));
        $this->assertSame(0, $this->scheduleStatus($this->scheduleIds[3]));

        // Transaction history: one row per premium, summing to the single debit.
        $txns = DB::table('payment_transactions')
            ->where('policyNumber', $this->misPolicyNumber)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $txns);
        $this->assertEqualsWithDelta($expected, $txns->sum(fn($t) => (float) $t->amount), 0.01);
        foreach ($txns as $txn) {
            $this->assertSame('SUCCESS', $txn->status);
            $this->assertSame('DPO', $txn->paymentMethod);
            $this->assertEqualsWithDelta(self::PREMIUM, (float) $txn->amount, 0.01);
        }

        // Collection event records how many premiums the debit covered.
        $event = DB::table('payment_collection_events')
            ->where('policy_id', $this->misPolicyId)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame('success', $event->status);
        $this->assertSame(2, (int) $event->premium_count);
        $this->assertEqualsWithDelta($expected, (float) $event->amount, 0.01);
        $this->assertEquals($selected, json_decode($event->schedule_ids, true));
    }

    /** @test */
    public function the_debit_is_refused_without_an_explicit_confirmation(): void
    {
        $this->stubDpoGatewaySuccess();

        $response = $this->postJson("/api/v1/policies/{$this->misPolicyId}/collect-now", [
            'schedule_ids'    => [$this->scheduleIds[0]],
            'expected_amount' => self::PREMIUM,
            // no 'confirmed'
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('confirmed', strtolower($response->json('message')));

        // Nothing moved.
        $this->assertSame(3, $this->scheduleStatus($this->scheduleIds[0]));
        $this->assertSame(0, DB::table('payment_transactions')->where('policyNumber', $this->misPolicyNumber)->count());
    }

    /** @test */
    public function a_total_that_no_longer_matches_is_refused(): void
    {
        $this->stubDpoGatewaySuccess();

        // Operator confirmed one premium's worth but ticked two — or the premium
        // was re-rated while the tab sat open. Either way: do not debit.
        $response = $this->postJson("/api/v1/policies/{$this->misPolicyId}/collect-now", [
            'schedule_ids'    => [$this->scheduleIds[0], $this->scheduleIds[1]],
            'expected_amount' => self::PREMIUM,
            'confirmed'       => true,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('no longer matches', $response->json('message'));
        $this->assertSame(3, $this->scheduleStatus($this->scheduleIds[0]));
        $this->assertSame(0, DB::table('payment_transactions')->where('policyNumber', $this->misPolicyNumber)->count());
    }

    /** @test */
    public function premiums_belonging_to_another_policy_are_refused(): void
    {
        $this->stubDpoGatewaySuccess();

        $foreignId = DB::table('scheduled_transactions')->insertGetId([
            'policy_id'     => $this->domPolicyId,
            'policy_number' => $this->domPolicyNumber,
            'customer_id'   => $this->customerId,
            'installment'   => 1,
            'premium'       => 9999.00,
            'status'        => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $response = $this->postJson("/api/v1/policies/{$this->misPolicyId}/collect-now", [
            'schedule_ids'    => [$this->scheduleIds[0], $foreignId],
            'expected_amount' => self::PREMIUM + 9999.00,
            'confirmed'       => true,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('do not belong to this policy', $response->json('message'));
        $this->assertSame(3, $this->scheduleStatus($this->scheduleIds[0]));
        $this->assertSame(0, $this->scheduleStatus($foreignId));
    }

    /** @test */
    public function an_already_collected_premium_cannot_be_collected_again(): void
    {
        $this->stubDpoGatewaySuccess();

        // Simulates the second operator submitting a list that has moved on.
        DB::table('scheduled_transactions')->where('id', $this->scheduleIds[0])->update(['status' => 2]);

        $response = $this->postJson("/api/v1/policies/{$this->misPolicyId}/collect-now", [
            'schedule_ids'    => [$this->scheduleIds[0]],
            'expected_amount' => self::PREMIUM,
            'confirmed'       => true,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('no longer outstanding', $response->json('message'));
        $this->assertSame(0, DB::table('payment_transactions')->where('policyNumber', $this->misPolicyNumber)->count());
    }

    /** @test */
    public function selection_is_rejected_on_a_non_mis_policy(): void
    {
        $this->stubDpoGatewaySuccess();

        $domScheduleId = DB::table('scheduled_transactions')->insertGetId([
            'policy_id'     => $this->domPolicyId,
            'policy_number' => $this->domPolicyNumber,
            'customer_id'   => $this->customerId,
            'installment'   => 1,
            'premium'       => self::PREMIUM,
            'status'        => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $response = $this->postJson("/api/v1/policies/{$this->domPolicyId}/collect-now", [
            'schedule_ids'    => [$domScheduleId],
            'expected_amount' => self::PREMIUM,
            'confirmed'       => true,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('MIS policies', $response->json('message'));
        $this->assertSame(0, $this->scheduleStatus($domScheduleId));

        // And the endpoint tells the UI not to offer selection there.
        $body = $this->getJson("/api/v1/policies/{$this->domPolicyId}/collect-now/outstanding")->json();
        $this->assertFalse($body['policy']['isMis']);
        $this->assertFalse($body['policy']['selectionSupported']);
    }

    /** @test */
    public function a_failed_debit_leaves_the_premiums_collectable(): void
    {
        // Gateway refuses at the charge step; the claimed rows must go back to
        // exactly the status they had, or the premiums would be stranded.
        foreach ([CreateTokenEvent::class, ChargeTokenRecurrentEvent::class, VerifyTokenEvent::class, PullAccountEvent::class] as $event) {
            Event::forget($event);
        }
        Event::listen(CreateTokenEvent::class, fn() => ['status' => 1, 'TransactionToken' => 'TOKFAIL', 'reason' => 'ok']);
        Event::listen(ChargeTokenRecurrentEvent::class, fn() => ['status' => 0, 'reason' => 'INSUFFICIENT FUNDS']);
        Event::listen(PullAccountEvent::class, fn() => ['status' => 1]);

        $response = $this->postJson("/api/v1/policies/{$this->misPolicyId}/collect-now", [
            'schedule_ids'    => [$this->scheduleIds[0], $this->scheduleIds[2]],
            'expected_amount' => 2 * self::PREMIUM,
            'confirmed'       => true,
        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));

        // Prior statuses restored (3 and 0) — not both collapsed to one value.
        $this->assertSame(3, $this->scheduleStatus($this->scheduleIds[0]));
        $this->assertSame(0, $this->scheduleStatus($this->scheduleIds[2]));

        // The failed attempt is still on the record.
        $event = DB::table('payment_collection_events')
            ->where('policy_id', $this->misPolicyId)
            ->orderByDesc('id')
            ->first();
        $this->assertSame('failed', $event->status);
        $this->assertSame(2, (int) $event->premium_count);
        $this->assertNotEmpty($event->failure_reason);
    }

    /** @test */
    public function the_service_totals_the_selection_itself_rather_than_trusting_the_caller(): void
    {
        $this->stubDpoGatewaySuccess('TOKENSUM');

        // Re-rate one installment after the "UI" read it, then submit with no
        // expected_amount at all: the debit must use the DB sum, not a guess.
        DB::table('scheduled_transactions')->where('id', $this->scheduleIds[1])->update(['premium' => 125.50]);

        $response = $this->postJson("/api/v1/policies/{$this->misPolicyId}/collect-now", [
            'schedule_ids' => [$this->scheduleIds[0], $this->scheduleIds[1]],
            'confirmed'    => true,
        ]);

        $response->assertOk();
        $this->assertEqualsWithDelta(self::PREMIUM + 125.50, (float) $response->json('total_amount'), 0.01);

        $event = DB::table('payment_collection_events')
            ->where('policy_id', $this->misPolicyId)
            ->orderByDesc('id')
            ->first();
        $this->assertEqualsWithDelta(self::PREMIUM + 125.50, (float) $event->amount, 0.01);
    }

    /** @test */
    public function outstanding_statuses_are_the_same_set_the_crons_treat_as_collectable(): void
    {
        // Guards against the two definitions drifting apart: if someone adds a
        // status to one list and not the other, a premium becomes either
        // invisible here or chargeable twice.
        $this->assertSame([0, 1, 3], PayNowService::OUTSTANDING_STATUSES);
    }
}
