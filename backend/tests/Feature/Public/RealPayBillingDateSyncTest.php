<?php

namespace Tests\Feature\Public;

use AlphaDirect\CustomerBanking;
use AlphaDirect\Policy;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayLogs;
use AlphaDirect\Services\RealPayBillingDateSynchroniser;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A policy's billing date must be the date RealPay debits on.
 *
 * The bug (MIS2026213635): the billing date was edited 2026-05-18 → 2026-05-28,
 * Graphite showed the 28th, and RealPay kept debiting on the 18th. Nothing was
 * wrong with contract creation — RealPay holds the instalment schedule and
 * debits on its own InstalmentActionDate, and the policy-edit path wrote
 * policies.billingStartDate and nothing else: not customer_banking.billing_day,
 * not the instalments, and not RealPay.
 *
 * What has to hold, and is asserted below:
 *
 *   1. a billing-date change on a policy with a live contract queues an
 *      instalment move onto the new day
 *   2. it moves instalments — it never cancels or creates a contract, so a
 *      duplicate debit order is impossible
 *   3. the local mirror (customer_banking.billing_day / billingStartDate) is
 *      corrected too, since that is what every RealPay-facing reader uses
 *   4. a policy with no live contract is a no-op, not an error
 *   5. a schedule already on the right day queues nothing
 *   6. running it twice queues one move, not two
 *   7. a back-dated edit schedules the NEXT occurrence, never a past debit
 *   8. RealPay's month-end convention (29/30/31 → 99) is mirrored locally
 *
 * Runs against in-memory SQLite. The synchroniser makes no HTTP calls by
 * design — it writes the realpay_logs job that processrealpaypayment:cron
 * drains — so the whole unit under test is real here.
 */
class RealPayBillingDateSyncTest extends TestCase
{
    private const CONNECTION = 'realpay_billing_date_test';

    private string $previousConnection;
    private Policy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('audit.enabled', false);
        Policy::disableAuditing();
        RealpayClientContracts::disableAuditing();
        RealpayContractInstallments::disableAuditing();
        RealpayLogs::disableAuditing();
        CustomerBanking::disableAuditing();

        Config::set('realpay.billing_date_sync.enabled', true);

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
    // The reported case
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function changing_the_billing_date_moves_the_realpay_schedule(): void
    {
        // MIS2026213635 as it stood: contract collecting on the 18th, billing
        // date edited to the 28th.
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2026-10-18', 'A');
        $this->seedInstalment('C1001', 2, '2026-11-18', 'A');

        $result = $this->sync()->syncForPolicy($this->policy, '2026-10-28', 'policy-edit');

        $this->assertTrue($result['synced'], $result['message']);
        $this->assertSame(18, $result['from_day']);
        $this->assertSame(28, $result['to_day']);
        $this->assertSame('2026-10-28', $result['effective_date']);

        $queued = RealpayLogs::where('policy_id', $this->policy->id)->get();
        $this->assertCount(1, $queued, 'exactly one instalment-move job');

        $payload = json_decode((string) $queued->first()->input_data, true);
        $this->assertSame(3, (int) $queued->first()->event, 'event 3 is the instalment-update job');
        $this->assertSame(0, (int) $queued->first()->status, 'queued, for processrealpaypayment:cron');
        $this->assertSame('C1001', $payload['realpay_contract_number']);
        $this->assertSame('2026-10-28', $payload['realpay_installment_date']);
        $this->assertArrayNotHasKey(
            'realpay_installment_number',
            $payload,
            'omitting the instalment number is what makes the cron update ALL of them'
        );
    }

    /** @test */
    public function the_contract_itself_is_never_touched(): void
    {
        // Cancel-and-recreate is the fix that produces two live contracts and a
        // double debit. The schedule is moved in place instead.
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2026-10-18', 'A');

        $this->sync()->syncForPolicy($this->policy, '2026-10-28', 'policy-edit');

        $this->assertSame(1, RealpayClientContracts::where('policy_id', $this->policy->id)->count(),
            'no second contract');
        $this->assertSame(1, (int) RealpayClientContracts::where('contract_number', 'C1001')->value('status'),
            'and the existing one stays live');
    }

    /** @test */
    public function the_local_billing_day_mirror_is_corrected(): void
    {
        // customer_banking.billing_day is what a new contract, an added
        // instalment and the billing reports all read. The edit path leaving it
        // stale is half of why this bug was invisible.
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2026-10-18', 'A');
        $bankingId = DB::table('customer_banking')->insertGetId([
            'policy_id'        => $this->policy->id,
            'billing'          => 'RealPay',
            'billing_day'      => 18,
            'billingStartDate' => '2026-05-18',
        ]);

        $this->sync()->syncForPolicy($this->policy, '2026-10-28', 'policy-edit');

        $banking = DB::table('customer_banking')->where('id', $bankingId)->first();
        $this->assertSame(28, (int) $banking->billing_day);
        $this->assertSame('2026-10-28', $banking->billingStartDate);
    }

    /** @test */
    public function a_month_end_billing_day_is_stored_as_realpays_99(): void
    {
        // RealPay's convention: for a monthly contract, days 29/30/31 are sent
        // as CollectionDay 99 so short months still collect. The local mirror
        // has to say the same thing or the next contract creation disagrees
        // with the live one.
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2026-10-18', 'A');
        $bankingId = DB::table('customer_banking')->insertGetId([
            'policy_id'   => $this->policy->id,
            'billing'     => 'RealPay',
            'billing_day' => 18,
        ]);

        $this->sync()->syncForPolicy($this->policy, '2026-10-31', 'policy-edit');

        $this->assertSame(99, (int) DB::table('customer_banking')->where('id', $bankingId)->value('billing_day'));
    }

    // ──────────────────────────────────────────────────────────────────
    // Nothing to do
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function a_policy_with_no_live_contract_is_a_no_op(): void
    {
        // A cash or DPO policy. Its billingStartDate is the only schedule there
        // is, so there is nothing to reconcile.
        $result = $this->sync()->syncForPolicy($this->policy, '2026-10-28', 'policy-edit');

        $this->assertFalse($result['synced']);
        $this->assertStringContainsString('no active RealPay contract', $result['message']);
        $this->assertSame(0, RealpayLogs::count());
    }

    /** @test */
    public function a_cancelled_contract_is_not_treated_as_live(): void
    {
        $this->seedContract('C0900', 0);
        $this->seedInstalment('C0900', 1, '2026-10-18', 'I');

        $result = $this->sync()->syncForPolicy($this->policy, '2026-10-28', 'policy-edit');

        $this->assertFalse($result['synced']);
        $this->assertSame(0, RealpayLogs::count());
    }

    /** @test */
    public function a_schedule_already_on_the_right_day_queues_nothing(): void
    {
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2026-10-28', 'A');

        $result = $this->sync()->syncForPolicy($this->policy, '2026-10-28', 'policy-edit');

        $this->assertTrue($result['synced'], 'already correct counts as in sync');
        $this->assertNull($result['effective_date']);
        $this->assertSame(0, RealpayLogs::count(), 'no pointless round of PUTs against RealPay');
    }

    /** @test */
    public function only_active_instalments_define_the_current_debit_day(): void
    {
        // Past collections keep their own dates forever; the day RealPay will
        // debit next is the earliest ACTIVE one.
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2026-08-05', 'S');
        $this->seedInstalment('C1001', 2, '2026-10-18', 'A');

        $result = $this->sync()->syncForPolicy($this->policy, '2026-10-28', 'policy-edit');

        $this->assertSame(18, $result['from_day'], 'the settled 5th must not be read as the schedule');
    }

    // ──────────────────────────────────────────────────────────────────
    // Idempotency
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function saving_the_same_edit_twice_queues_one_move(): void
    {
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2026-10-18', 'A');

        $sync = $this->sync();
        $sync->syncForPolicy($this->policy, '2026-10-28', 'policy-edit');
        $second = $sync->syncForPolicy($this->policy, '2026-10-28', 'policy-edit');

        $this->assertTrue($second['synced']);
        $this->assertStringContainsString('already queued', $second['message']);
        $this->assertSame(1, RealpayLogs::count(), 'each queued row is another round of PUTs — never stack them');
    }

    /** @test */
    public function a_processed_job_does_not_block_a_later_genuine_change(): void
    {
        // The dedupe is on PENDING jobs only. Once the cron has applied a move,
        // a further edit must be able to queue another one.
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2026-10-18', 'A');

        $this->sync()->syncForPolicy($this->policy, '2026-10-28', 'policy-edit');
        RealpayLogs::query()->update(['status' => 1]);

        $this->sync()->syncForPolicy($this->policy, '2026-10-15', 'policy-edit');

        $this->assertSame(2, RealpayLogs::count());
    }

    // ──────────────────────────────────────────────────────────────────
    // Dates
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function a_back_dated_edit_schedules_the_next_occurrence_not_a_past_debit(): void
    {
        // MIS2026213635's real shape: the date was set to 2026-05-28 and the
        // problem surfaced months later. Sending RealPay a past date either
        // fails or debits immediately.
        Carbon::setTestNow(Carbon::parse('2026-09-03'));

        try {
            $this->seedContract('C1001');
            $this->seedInstalment('C1001', 1, '2026-09-18', 'A');

            $result = $this->sync()->syncForPolicy($this->policy, '2026-05-28', 'policy-edit');

            $this->assertSame('2026-09-28', $result['effective_date'],
                'the next 28th, not the one four months ago');
        } finally {
            Carbon::setTestNow();
        }
    }

    /** @test */
    public function a_billing_day_already_past_this_month_rolls_to_next_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29'));

        try {
            $this->seedContract('C1001');
            $this->seedInstalment('C1001', 1, '2026-10-18', 'A');

            $result = $this->sync()->syncForPolicy($this->policy, '2026-05-28', 'policy-edit');

            $this->assertSame('2026-10-28', $result['effective_date']);
        } finally {
            Carbon::setTestNow();
        }
    }

    /** @test */
    public function a_31st_billing_day_clamps_to_the_end_of_a_short_month(): void
    {
        // February has no 31st. Carbon's default addMonth would overflow into
        // March, which would skip a collection entirely.
        Carbon::setTestNow(Carbon::parse('2027-02-01'));

        try {
            $sync = $this->sync();
            $this->assertSame('2027-02-28', $sync->firstCollectionOnOrAfterToday('2026-05-31'));
        } finally {
            Carbon::setTestNow();
        }
    }

    /** @test */
    public function a_future_billing_date_is_honoured_as_given(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03'));

        try {
            $this->assertSame('2026-11-28', $this->sync()->firstCollectionOnOrAfterToday('2026-11-28'));
        } finally {
            Carbon::setTestNow();
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Escape hatch + wiring
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function the_sync_can_be_switched_off(): void
    {
        Config::set('realpay.billing_date_sync.enabled', false);
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2026-10-18', 'A');

        $result = $this->sync()->syncForPolicy($this->policy, '2026-10-28', 'policy-edit');

        $this->assertFalse($result['synced']);
        $this->assertSame(0, RealpayLogs::count());
    }

    /** @test */
    public function the_container_can_build_the_service_the_edit_path_resolves(): void
    {
        $this->assertInstanceOf(
            RealPayBillingDateSynchroniser::class,
            app(RealPayBillingDateSynchroniser::class)
        );
    }

    /** @test */
    public function an_unparseable_billing_date_is_refused_rather_than_queued(): void
    {
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2026-10-18', 'A');

        $result = $this->sync()->syncForPolicy($this->policy, 'not a date', 'policy-edit');

        $this->assertFalse($result['synced']);
        $this->assertSame(0, RealpayLogs::count());
    }

    // ──────────────────────────────────────────────────────────────────
    // The backlog sweep — policies that drifted before the fix existed
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function the_audit_reports_a_policy_whose_realpay_day_has_drifted(): void
    {
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2026-10-18', 'A');
        DB::table('policies')->where('id', $this->policy->id)->update(['billingStartDate' => '2026-05-28']);

        $exit   = \Illuminate\Support\Facades\Artisan::call('realpay:audit-billing-dates', ['--no-report' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertSame(1, $exit, 'a customer on the wrong debit day must fail the run, not pass quietly');
        $this->assertStringContainsString('configured day 28, RealPay collects day 18', $output);
        $this->assertStringContainsString('MIS2026213635', $output);
        $this->assertSame(0, RealpayLogs::count(), 'report-only queues nothing');
    }

    /** @test */
    public function the_audit_queues_the_correction_with_fix(): void
    {
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2026-10-18', 'A');
        DB::table('policies')->where('id', $this->policy->id)->update(['billingStartDate' => '2026-05-28']);

        $this->artisan('realpay:audit-billing-dates', ['--fix' => true, '--no-report' => true])->run();

        $this->assertSame(1, RealpayLogs::where('policy_id', $this->policy->id)->where('event', 3)->count());
        $this->assertSame(1, RealpayClientContracts::where('policy_id', $this->policy->id)->count(),
            'the correction moves instalments — it never adds a contract');
    }

    /** @test */
    public function a_schedule_in_step_is_not_reported(): void
    {
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2026-10-28', 'A');
        DB::table('policies')->where('id', $this->policy->id)->update(['billingStartDate' => '2026-05-28']);

        $exit = $this->artisan('realpay:audit-billing-dates', ['--no-report' => true])->run();

        $this->assertSame(0, $exit);
    }

    /** @test */
    public function a_month_end_collection_is_not_mistaken_for_drift(): void
    {
        // Billed on the 31st, collected on 28 Feb — RealPay's CollectionDay 99
        // doing exactly what it should. Flagging this would bury the real
        // mismatches under a monthly wave of false positives.
        $this->seedContract('C1001');
        $this->seedInstalment('C1001', 1, '2027-02-28', 'A');
        DB::table('policies')->where('id', $this->policy->id)->update(['billingStartDate' => '2026-05-31']);

        $exit = $this->artisan('realpay:audit-billing-dates', ['--no-report' => true])->run();

        $this->assertSame(0, $exit);
        $this->assertSame(0, RealpayLogs::count());
    }

    // ──────────────────────────────────────────────────────────────────
    // The PUT-response classifier the whole chain now depends on
    // ──────────────────────────────────────────────────────────────────

    /**
     * @test
     * @dataProvider putResponses
     */
    public function realpay_put_responses_are_classified_correctly($response, bool $expected, string $why): void
    {
        $method = new \ReflectionMethod(
            \AlphaDirect\Http\Controllers\Admin\RealPayController::class,
            'instalmentPutAccepted'
        );
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke(new \AlphaDirect\Http\Controllers\Admin\RealPayController(), $response), $why);
    }

    public static function putResponses(): array
    {
        return [
            'accepted' => [
                ['InstalmentPutResponse' => [['Successful' => [['InstalmentSequence' => 1]], 'Failed' => []]]],
                true,
                'a populated Successful with no Failed is the only acceptance',
            ],
            'rejected' => [
                ['InstalmentPutResponse' => [['Successful' => [], 'Failed' => [['Reason' => 'bad date']]]]],
                false,
                'a rejection must not be recorded as a moved schedule',
            ],
            'partial' => [
                ['InstalmentPutResponse' => [['Successful' => [['InstalmentSequence' => 1]], 'Failed' => [['Reason' => 'x']]]]],
                false,
                'a partial result leaves the schedule unknown — treat it as failed',
            ],
            'transport failure' => [null, false, 'a null decode is a rejection, not a silent success'],
            'unexpected shape'  => [['SomethingElse' => []], false, 'an unrecognised body proves nothing'],
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Fixtures
    // ──────────────────────────────────────────────────────────────────

    private function sync(): RealPayBillingDateSynchroniser
    {
        return new RealPayBillingDateSynchroniser();
    }

    private function seedContract(string $contractNumber, int $status = 1): void
    {
        RealpayClientContracts::insert([
            'policy_id'       => $this->policy->id,
            'contract_number' => $contractNumber,
            'client_number'   => $this->policy->policyNumber,
            'status'          => $status,
        ]);
    }

    private function seedInstalment(string $contractNumber, int $sequence, string $actionDate, string $status): void
    {
        RealpayContractInstallments::insert([
            'policy_id'                 => $this->policy->id,
            'clientNumber'              => $this->policy->policyNumber,
            'contractNumber'            => $contractNumber,
            'InstalmentReferenceNumber' => 'REF-' . $sequence,
            'InstalmentSequence'        => $sequence,
            'InstalmentActionDate'      => $actionDate,
            'InstalmentStatus'          => $status,
        ]);
    }

    private function makePolicy(): Policy
    {
        $id = DB::table('policies')->insertGetId([
            'policyNumber'     => 'MIS2026213635',
            'product_id'       => 2,
            'customer_id'      => 1,
            'status'           => 1,
            'billingStartDate' => '2026-05-18',
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
            $table->string('billingStartDate')->nullable();
            $table->string('billing_day')->nullable();
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

        $schema->create('realpay_contract_installments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('clientNumber')->nullable();
            $table->string('contractNumber')->nullable();
            $table->string('InstalmentReferenceNumber')->nullable();
            $table->string('InstalmentSequence')->nullable();
            $table->string('InstalmentActionDate')->nullable();
            $table->string('InstalmentStatus')->nullable();
            $table->timestamps();
        });

        $schema->create('realpay_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->integer('event')->nullable();
            $table->integer('status')->nullable();
            $table->text('input_data')->nullable();
            $table->timestamps();
        });

        $schema->create('customer_banking', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('policy_id')->nullable();
            $table->string('billing')->nullable();
            $table->string('billing_day')->nullable();
            $table->string('billingStartDate')->nullable();
            $table->timestamps();
        });
    }
}
