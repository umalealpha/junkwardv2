<?php

namespace Tests\Feature;

use AlphaDirect\Policy;
use AlphaDirect\Services\PayNowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * DEF-002 / DEF-003 regression guard — Collect Now DPO token resolution.
 *
 * The DPO mandate (subscription/customer/charge tokens) lives on the policy's
 * tokenised scheduled_transactions rows, including SUCCEEDED ones (status=2).
 * A healthy, up-to-date DPO policy carries its token only on a succeeded row;
 * the old resolver excluded succeeded rows and so found no gateway. These
 * tests lock in that the token is resolved regardless of installment status,
 * and that cancelled/expired policies are never debited.
 *
 * NOTE: authored without a local PHP runtime — run in CI/staging
 * (php artisan test --filter=CollectNowDpoToken).
 */
class CollectNowDpoTokenTest extends TestCase
{
    use RefreshDatabase;

    private function seedScheduledRow(int $policyId, int $status, bool $withTokens, int $retry = 0): void
    {
        DB::table('scheduled_transactions')->insert([
            'policy_id'          => $policyId,
            'status'             => $status,
            'retry_count'        => $retry,
            'token'              => $withTokens ? 'tok-' . uniqid() : null,
            'subscription_token' => $withTokens ? 'sub-' . uniqid() : null,
            'customer_token'     => $withTokens ? 'cus-' . uniqid() : null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    private function makePolicy(int $status = 1): Policy
    {
        $id = DB::table('policies')->insertGetId([
            'policyNumber' => 'TEST' . uniqid(),
            'status'       => $status,
            'premium'      => 79.00,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        return Policy::find($id);
    }

    public function test_token_is_resolved_from_a_succeeded_row(): void
    {
        $policy = $this->makePolicy(1);
        // The real-world shape: future installments carry no token, the token
        // lives only on the succeeded (status=2) row.
        $this->seedScheduledRow($policy->id, 0, false);
        $this->seedScheduledRow($policy->id, 0, false);
        $this->seedScheduledRow($policy->id, 2, true);  // succeeded, holds the token

        $m = new ReflectionMethod(PayNowService::class, 'getDpoScheduledTransaction');
        $m->setAccessible(true);
        $row = $m->invoke(app(PayNowService::class), $policy);

        $this->assertNotNull($row, 'token must be resolved from the succeeded row');
        $this->assertSame(2, (int) $row->status);
        $this->assertNotEmpty($row->token);
    }

    public function test_no_token_when_policy_has_none(): void
    {
        $policy = $this->makePolicy(1);
        $this->seedScheduledRow($policy->id, 0, false); // cash-style: no tokens anywhere

        $m = new ReflectionMethod(PayNowService::class, 'getDpoScheduledTransaction');
        $m->setAccessible(true);
        $this->assertNull($m->invoke(app(PayNowService::class), $policy));
    }

    public function test_cancelled_policy_is_not_collected(): void
    {
        $policy = $this->makePolicy(2); // 2 = Cancelled
        $this->seedScheduledRow($policy->id, 2, true); // even with a live token

        $res = app(PayNowService::class)->collect($policy, null);

        $this->assertFalse($res['success']);
        $this->assertSame('NONE', $res['method']);
        $this->assertStringContainsStringIgnoringCase('cancelled', $res['message']);
    }
}
