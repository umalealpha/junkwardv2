<?php

namespace Tests\Feature;

use AlphaDirect\Http\Controllers\Api\V1\PolicyCreateController;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Policy;
use AlphaDirect\PolicyTerm;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Editing the policy period from Edit Policy on an Anniversary Renewal.
 *
 * REPORTED: on Engineering products the dates under Edit Policy could not be
 * changed for an Anniversary Renewal, while Edit Transaction changed them fine.
 *
 * The cause was a front-end gate: PolicyCreatePage's `datesEditable` required
 * `transaction_type === 'NEWBUSINESS'`, so the Expiry field was rendered
 * disabled on an ANNIVERSARY-RENEW quote. The backend was already correct — it
 * falls back to the newest QUOTE action of any type for the DATE_SYNC products
 * (16/17/18/20/22/23/24) and propagates to all three rows.
 *
 * These tests pin the backend half, which the unlocked UI now depends on:
 * dates sent for an ANNIVERSARY-RENEW quote must land on `policies`, on the
 * action's `effective_from`/`effective_to`, and on the term's
 * `term_start_date`/`term_end_date` — and the backwards-period guard must still
 * refuse an expiry before the start, since the UI can now send one.
 *
 * The controller is driven directly rather than over HTTP: the route is behind
 * auth middleware that would need a full user/RBAC fixture, and the logic under
 * test is entirely inside update().
 */
class AnniversaryRenewDateEditTest extends TestCase
{
    private const CONNECTION = 'anniv_date_test';

    private string $previousConnection;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('audit.enabled', false);
        Config::set('activitylog.enabled', false);
        Policy::disableAuditing();
        PolicyAction::disableAuditing();
        PolicyTerm::disableAuditing();

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
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection($this->previousConnection);
        DB::purge(self::CONNECTION);

        parent::tearDown();
    }

    /** @test */
    public function engineering_anniversary_renewal_dates_save_from_edit_policy(): void
    {
        // An Engineering policy that has been issued and then put up for
        // anniversary renewal: the NEWBUSINESS action is ISSUED (which is why
        // the NEWBUSINESS-only lookup finds nothing) and the renewal is the
        // open QUOTE.
        [$policy, $renewAction, $term] = $this->engineeringPolicyOnRenewal();

        $response = $this->update($policy->id, [
            'term_start_date' => '2027-03-15',
            'expiry_date'     => '2028-03-14',
        ]);

        $this->assertSame(200, $response->getStatusCode(),
            'A dates-only edit must be accepted. Body: ' . $response->getContent());

        $policy->refresh();
        $this->assertSame('2027-03-15', $this->dateOf($policy->term_start_date));
        $this->assertSame('2028-03-14', $this->dateOf($policy->expiry_date));

        // The action — this is what stayed stale, and what Edit Transaction was
        // being used to fix by hand.
        $renewAction->refresh();
        $this->assertSame('2027-03-15', $this->dateOf($renewAction->effective_from),
            "The ANNIVERSARY-RENEW action's period must follow the policy term.");
        $this->assertSame('2028-03-14', $this->dateOf($renewAction->effective_to));

        // The term row the action points at, not simply the newest one.
        $term->refresh();
        $this->assertSame('2027-03-15', $this->dateOf($term->term_start_date));
        $this->assertSame('2028-03-14', $this->dateOf($term->term_end_date));
    }

    /** @test */
    public function the_issued_new_business_action_is_never_touched(): void
    {
        [$policy] = $this->engineeringPolicyOnRenewal();

        $this->update($policy->id, [
            'term_start_date' => '2027-03-15',
            'expiry_date'     => '2028-03-14',
        ]);

        $issued = PolicyAction::where('policy_id', $policy->id)
            ->where('transaction_type', 'NEWBUSINESS')->first();

        $this->assertSame('2026-01-01', $this->dateOf($issued->effective_from),
            'An ISSUED action is history — editing a renewal must not rewrite it.');
        $this->assertSame('2026-12-31', $this->dateOf($issued->effective_to));
    }

    /** @test */
    public function a_start_date_alone_moves_every_row(): void
    {
        // The UI recomputes expiry from the start date, but a client may send
        // just one. Each date is applied independently.
        [$policy, $renewAction, $term] = $this->engineeringPolicyOnRenewal();

        $this->update($policy->id, ['term_start_date' => '2027-02-01']);

        $this->assertSame('2027-02-01', $this->dateOf($policy->refresh()->term_start_date));
        $this->assertSame('2027-02-01', $this->dateOf($renewAction->refresh()->effective_from));
        $this->assertSame('2027-02-01', $this->dateOf($term->refresh()->term_start_date));
    }

    /** @test */
    public function an_expiry_before_the_start_is_still_refused(): void
    {
        // The UI can now send an expiry independently, so this guard is load
        // bearing: without it a backwards period is written to all three rows
        // and issuePolicy only repairs one of them.
        [$policy, $renewAction, $term] = $this->engineeringPolicyOnRenewal();

        $response = $this->update($policy->id, [
            'term_start_date' => '2027-05-05',
            'expiry_date'     => '2026-12-31',
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('must be after', $response->getContent());

        // Nothing moved.
        $this->assertSame('2026-01-01', $this->dateOf($policy->refresh()->term_start_date));
        $this->assertSame('2027-01-01', $this->dateOf($renewAction->refresh()->effective_from));
        $this->assertSame('2027-01-01', $this->dateOf($term->refresh()->term_start_date));
    }

    /** @test */
    public function an_expiry_before_an_unchanged_stored_start_is_also_refused(): void
    {
        // Sending only the expiry has nothing in the payload to compare against,
        // so the guard has to fall back to the stored start date.
        [$policy] = $this->engineeringPolicyOnRenewal();

        $response = $this->update($policy->id, ['expiry_date' => '2025-06-30']);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('2026-12-31', $this->dateOf($policy->refresh()->expiry_date));
    }

    /** @test */
    public function domcom_keeps_its_new_business_only_behaviour(): void
    {
        // The any-QUOTE fallback is scoped to DATE_SYNC_PRODUCT_IDS on purpose.
        // A DomCom policy (8) with only a RENEW quote must NOT have that action
        // re-synced — this pins the scoping so a later edit cannot widen it by
        // accident.
        [$policy, $renewAction] = $this->engineeringPolicyOnRenewal(8);

        $response = $this->update($policy->id, [
            'term_start_date' => '2027-03-15',
            'expiry_date'     => '2028-03-14',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('2027-03-15', $this->dateOf($policy->refresh()->term_start_date),
            'The policy row still moves for DomCom.');
        $this->assertSame('2027-01-01', $this->dateOf($renewAction->refresh()->effective_from),
            'DomCom has no any-QUOTE fallback, so its RENEW action keeps its own dates.');
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    /** @return array{0: Policy, 1: PolicyAction, 2: PolicyTerm} */
    private function engineeringPolicyOnRenewal(int $productId = 16): array
    {
        $policy = new Policy();
        $policy->policyNumber    = 'ENGG2026000123';
        $policy->product_id      = $productId;
        $policy->customer_id     = 501;
        $policy->status          = 1;
        $policy->is_draft        = 0;
        $policy->premium_freq    = 1;
        $policy->term_start_date = '2026-01-01';
        $policy->expiry_date     = '2026-12-31';
        $policy->save();

        // The original term + its issued action.
        $oldTerm = new PolicyTerm();
        $oldTerm->policy_id       = $policy->id;
        $oldTerm->term_start_date = '2026-01-01';
        $oldTerm->term_end_date   = '2026-12-31';
        $oldTerm->save();

        $issued = new PolicyAction();
        $issued->policy_id        = $policy->id;
        $issued->term_id          = $oldTerm->id;
        $issued->transaction_type = 'NEWBUSINESS';
        $issued->status           = 'ISSUED';
        $issued->effective_from   = '2026-01-01';
        $issued->effective_to     = '2026-12-31';
        $issued->save();

        // The renewal term + its open quote.
        $renewTerm = new PolicyTerm();
        $renewTerm->policy_id       = $policy->id;
        $renewTerm->term_start_date = '2027-01-01';
        $renewTerm->term_end_date   = '2027-12-31';
        $renewTerm->save();

        $renew = new PolicyAction();
        $renew->policy_id        = $policy->id;
        $renew->term_id          = $renewTerm->id;
        $renew->transaction_type = 'ANNIVERSARY-RENEW';
        $renew->status           = 'QUOTE';
        $renew->effective_from   = '2027-01-01';
        $renew->effective_to     = '2027-12-31';
        $renew->save();

        return [$policy, $renew, $renewTerm];
    }

    private function update(int $policyId, array $payload)
    {
        $request = Request::create("/api/v1/policies/{$policyId}", 'PUT', $payload);

        try {
            return (new PolicyCreateController())->update($request, $policyId);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    /** Column values arrive as Carbon or raw string depending on the cast; normalise. */
    private function dateOf($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
    }

    private function buildSchema(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('policyNumber')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->integer('status')->nullable();
            $table->tinyInteger('is_draft')->nullable()->default(0);
            $table->integer('premium_freq')->nullable();
            $table->date('term_start_date')->nullable();
            $table->date('term_end_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('policyActivatedDate')->nullable();
            $table->string('gfs_policy_no')->nullable();
            $table->timestamps();
        });

        Schema::create('policy_actions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->unsignedInteger('term_id')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('status')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        // update() resolves $policy->customer to apply any holder-detail edits.
        // Present but untouched here: this payload carries dates only.
        Schema::create('customer', function (Blueprint $table) {
            $table->increments('id');
            $table->string('firstName')->nullable();
            $table->string('middleName')->nullable();
            $table->string('lastName')->nullable();
            $table->string('email')->nullable();
            $table->string('cellphone')->nullable();
            $table->timestamps();
        });

        Schema::create('policy_term', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->date('term_start_date')->nullable();
            $table->date('term_end_date')->nullable();
            $table->timestamps();
        });
    }
}
