<?php

namespace Tests\Feature;

use AlphaDirect\Events\CreateTokenEvent;
use AlphaDirect\Events\SendMail;
use AlphaDirect\Http\Controllers\Admin\RealPayController;
use AlphaDirect\Services\MisRecurringPaymentFailureNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SMOKE: drives the real production entry points end to end and checks the
 * failed-recurring-payment SMS actually comes out the other side.
 *
 *   RealPay -> RealPayController::updateInstallment() with a genuine
 *              InstalmentGetResponse webhook body (status 'F').
 *   DPO     -> `policy:processDPOpayment`   (the scheduled recurring cron)
 *              `policy:chargeRecurrentToken` (the older recurring cron)
 *
 * Unlike MisRecurringPaymentFailureSmsTest (which unit-tests the notifier),
 * nothing here is stubbed on the way in — the assertions prove the wiring at
 * each call site, not just the service.
 *
 * SAFETY — no money moves and no message leaves the building:
 *   * `policy:chargeRecurrentToken` gets a fixture with NULL DPO tokens, so it
 *     takes its "missing subscription token" branch: the failure is recorded
 *     without DPO ever being called.
 *   * `policy:processDPOpayment` always dispatches CreateTokenEvent (a real DPO
 *     call), so that event is faked. The command then sees no token, takes its
 *     "missing required tokens" branch, and again nothing is charged.
 *   * INFOBIP_URL points at a closed local port, so SendSmsFired's cURL fails
 *     instantly. That is still the path that writes the sms_email_log row,
 *     which is what we assert.
 *   * The fixture customer has NO email address, which is what the RealPay
 *     failure branch checks before firing the one-time-payment-link mail.
 *     SendMail is faked as a second line of defence (the DPO crons also email
 *     a daily report to staff).
 *   * `Storage::fake('s3')` keeps the crons' PDF reports off the real bucket.
 *   * The two legacy senders in the RealPay failure branch
 *     (sendOneTimePaymentLink -> a direct InfobipSms::send, and
 *     sendPaymentStatusSMS) are pinned OFF in sms_controls for the duration,
 *     so this test isolates the new SMS and makes no outbound Infobip call.
 *
 * Rows created by the code under test are removed on the way out by id
 * high-water mark, since these suites run against a shared MySQL.
 *
 * PROCESS LAYOUT — this PHP build (8.2 ZTS on Windows) dies silently, exit 255
 * with no diagnostic, as soon as a second full app boot in the same process
 * starts tearing down against the remote DB. So every test but one carries
 * `@runInSeparateProcess`, and `scheduled_dpo_cron_failure_sends_the_sms` is
 * left in the parent — it is then the only test booting the app there. (That
 * one cannot use isolation: its child reliably dies during PHPUnit's own child
 * shutdown, after the test body has completed and every assertion has passed.)
 *
 * Isolation also turns any stray child stdout into a test error, hence the
 * file-only log channel in setUp().
 */
class MisRecurringPaymentFailureSmsSmokeTest extends TestCase
{
    private const MIS_POLICY = 'MIS2099000002';
    private const CELLPHONE  = '71000002';
    private const NAME_FIRST = 'Boitumelo';
    private const NAME_LAST  = 'Sesinyi';

    /** Tables the code under test appends to; cleaned by id > high-water. */
    private const APPEND_ONLY_TABLES = [
        'transactions',
        'payment_transactions',
        'realpay_webhook_response',
        'one_time_payment_link',
        'dpo_transaction_reports',
        'dpo_payments_done_data',
        'activity_log',
        'audits',
        'cron_status',
        'sms_email_log',
        'mis_recurring_payment_failure_sms',
    ];

    /** Senders pinned to a fixed status for the duration; name => value. */
    private const CONTROLS = [
        'sendMisRecurringPaymentFailedSMS' => 1,
        'sendOneTimePaymentLink'           => 0,
        'sendPaymentStatusSMS'             => 0,
    ];

    private int $customerId;
    private int $policyId;
    private array $highWater = [];
    private array $originalControls = [];

    protected function setUp(): void
    {
        parent::setUp();

        // These tests drive whole crons against a remote RDS, so a single test
        // easily runs past a few minutes. Something in the boot path caps
        // execution at 180s; when that fires mid-query the fatal surfaces only
        // as PHPUnit's "ended unexpectedly". Lift both limits — the PDF report
        // tail is memory-hungry as well (Snappy falls back to DomPDF here).
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1024M');

        // The default `stack` channel includes `stderr`; under process isolation
        // any child stdout/stderr output is reported as a test error.
        config(['logging.default' => 'single']);

        $this->cleanupFixtures();

        $this->customerId = DB::table('customer')->insertGetId([
            'firstName'  => self::NAME_FIRST,
            'lastName'   => self::NAME_LAST,
            'cellphone'  => self::CELLPHONE,
            'email'      => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->policyId = DB::table('policies')->insertGetId([
            'policyNumber' => self::MIS_POLICY,
            'customer_id'  => $this->customerId,
            'product_id'   => 1,
            'status'       => 1,
            'premium'      => 350.00,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        foreach (self::CONTROLS as $function => $status) {
            $this->originalControls[$function] = $this->controlStatus($function);
            $this->setControlStatus($function, $status);
        }

        foreach (self::APPEND_ONLY_TABLES as $table) {
            $this->highWater[$table] = (int) DB::table($table)->max('id');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->highWater as $table => $maxId) {
            DB::table($table)->where('id', '>', $maxId)->delete();
        }
        foreach ($this->originalControls as $function => $status) {
            if ($status === null) {
                DB::table('sms_controls')->where('function_name', $function)->delete();
            } else {
                $this->setControlStatus($function, $status);
            }
        }
        $this->cleanupFixtures();

        parent::tearDown();
    }

    private function cleanupFixtures(): void
    {
        DB::table('mis_recurring_payment_failure_sms')->where('policyNumber', self::MIS_POLICY)->delete();
        DB::table('sms_email_log')->where('policyNumber', self::MIS_POLICY)->delete();
        DB::table('payment_transactions')->where('policyNumber', self::MIS_POLICY)->delete();
        DB::table('scheduled_transactions')->where('policy_number', self::MIS_POLICY)->delete();
        DB::table('one_time_payment_link')->where('policyNumber', self::MIS_POLICY)->delete();
        DB::table('realpay_webhook_response')->where('policyNumber', self::MIS_POLICY)->delete();
        DB::table('dpo_transaction_reports')->where('policy_number', self::MIS_POLICY)->delete();
        DB::table('dpo_payments_done_data')->where('policy_number', self::MIS_POLICY)->delete();
        DB::table('transactions')->where('policyNumber', self::MIS_POLICY)->delete();
        DB::table('policies')->where('policyNumber', self::MIS_POLICY)->delete();
        DB::table('customer')->where('cellphone', self::CELLPHONE)->where('firstName', self::NAME_FIRST)->delete();
    }

    private function controlStatus(string $function): ?int
    {
        $status = DB::table('sms_controls')->where('function_name', $function)->value('status');

        return $status === null ? null : (int) $status;
    }

    private function setControlStatus(string $function, int $status): void
    {
        DB::table('sms_controls')->updateOrInsert(
            ['function_name' => $function],
            ['status' => $status, 'updated_at' => now()]
        );
    }

    private function ledgerQuery(string $gateway)
    {
        return DB::table('mis_recurring_payment_failure_sms')
            ->where('policyNumber', self::MIS_POLICY)
            ->where('gateway', $gateway);
    }

    private function smsLogQuery()
    {
        return DB::table('sms_email_log')
            ->where('policyNumber', self::MIS_POLICY)
            ->where('hook', MisRecurringPaymentFailureNotifier::TEMPLATE_SLUG);
    }

    /** Assertions shared by all three entry points. */
    private function assertOneSmsSent(string $gateway, string $expectedEventReference): void
    {
        $this->assertSame(1, $this->ledgerQuery($gateway)->count(), 'Exactly one ledger row');

        $ledger = $this->ledgerQuery($gateway)->first();
        $this->assertSame('SENT', $ledger->status);
        $this->assertSame($expectedEventReference, $ledger->event_reference);
        $this->assertSame('+267' . self::CELLPHONE, $ledger->to_cellphone);

        $this->assertSame(1, $this->smsLogQuery()->count(), 'Exactly one SMS/Email Logs entry');

        $log = $this->smsLogQuery()->first();
        $this->assertSame('SMS', $log->type);                                   // recipient
        $this->assertSame('+267' . self::CELLPHONE, $log->to_cellphone);
        $this->assertStringContainsString(self::NAME_FIRST . ' ' . self::NAME_LAST, $log->message);
        $this->assertStringContainsString(self::MIS_POLICY, $log->message);      // content
        $this->assertStringContainsString('was unsuccessful', $log->message);
        $this->assertStringContainsString('Payment Failed', $log->message);
        $this->assertNotEmpty($log->status);                                     // status
        $this->assertNotEmpty($log->created_at);                                 // timestamp
        $this->assertSame((int) $log->id, (int) $ledger->sms_email_log_id);
    }

    private function assertFailedTransactionRecorded(string $paymentMethod): void
    {
        $this->assertTrue(
            DB::table('payment_transactions')
                ->where('policyNumber', self::MIS_POLICY)
                ->where('status', 'FAILED')
                ->where('paymentMethod', $paymentMethod)
                ->exists(),
            "Expected a FAILED {$paymentMethod} transaction — the failure path did not run"
        );
    }

    /** A scheduled_transactions row the recurring crons will pick up by id. */
    private function scheduleFixture(int $installment, int $retryCount, ?string $reason = null): int
    {
        return DB::table('scheduled_transactions')->insertGetId([
            'policy_id'          => $this->policyId,
            'policy_number'      => self::MIS_POLICY,
            'customer_id'        => $this->customerId,
            'installment'        => $installment,
            'retry_count'        => $retryCount,
            'premium'            => 350.00,
            'email'              => null,
            'token'              => null,
            'subscription_token' => null,
            'customer_token'     => null,
            'billing_date'       => now()->format('Y-m-d H:i:s'),
            'payment_method'     => 'DPO',
            'reason'             => $reason,
            'status'             => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    /** The RealPay webhook body for one failed installment on our MIS policy. */
    private function realpayFailureRequest(string $instalmentRef, int $sequence): Request
    {
        return Request::create('/api/realpay/updateInstallmentInfo', 'POST')
            ->replace([
                'InstalmentGetResponse' => [[
                    'ClientNumber'              => self::MIS_POLICY,
                    'ContractNumber'            => self::MIS_POLICY,
                    'InstalmentReferenceNumber' => $instalmentRef,
                    'InstalmentStatus'          => 'F',
                    'InstalmentActionDate'      => now()->format('Y-m-d'),
                    'TrackingCode'              => 'SMOKETRK1',
                    'InstalmentAmount'          => 350.00,
                    'InstalmentSequence'        => $sequence,
                    'ResponseCode'              => '01',
                ]],
            ]);
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function realpay_installment_failure_webhook_sends_the_sms(): void
    {
        Event::fake([SendMail::class]);

        $instalmentRef = 'SMOKERP' . now()->format('YmdHis');

        $this->withEnv($this->offlineInfobipEnv(), function () use ($instalmentRef) {
            $response = app(RealPayController::class)->updateInstallment(
                $this->realpayFailureRequest($instalmentRef, 3)
            );

            $this->assertSame(200, $response->getStatusCode(), 'Webhook should still ack RealPay');
        });

        $this->assertFailedTransactionRecorded('RealPay');
        $this->assertOneSmsSent(
            MisRecurringPaymentFailureNotifier::GATEWAY_REALPAY,
            MisRecurringPaymentFailureNotifier::realpayEventReference($instalmentRef, 3)
        );
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function replaying_the_same_realpay_webhook_does_not_resend(): void
    {
        Event::fake([SendMail::class]);

        // ProcessWebhookBuffer re-posts buffered RealPay bodies every minute;
        // the same installment arriving three times must produce one SMS.
        $instalmentRef = 'SMOKERPDUP' . now()->format('YmdHis');

        $this->withEnv($this->offlineInfobipEnv(), function () use ($instalmentRef) {
            for ($i = 0; $i < 3; $i++) {
                app(RealPayController::class)->updateInstallment(
                    $this->realpayFailureRequest($instalmentRef, 5)
                );
            }
        });

        $this->assertOneSmsSent(
            MisRecurringPaymentFailureNotifier::GATEWAY_REALPAY,
            MisRecurringPaymentFailureNotifier::realpayEventReference($instalmentRef, 5)
        );
    }

    /**
     * The DPO recurring cron that is actually scheduled (cron_kernel rows for
     * `policy:processDPOpayment`).
     *
     * Deliberately NOT isolated — see the class docblock. Runs in the parent
     * process, where it is the only test to boot the app.
     *
     * @test
     */
    public function scheduled_dpo_cron_failure_sends_the_sms(): void
    {
        // CreateTokenEvent is the command's first move and it calls DPO for real.
        // Faking it makes the command find no token and take its
        // "missing required tokens" failure branch — nothing is charged.
        Event::fake([CreateTokenEvent::class, SendMail::class]);
        Storage::fake('s3');
        $this->stubPdfRenderer();

        // A `reason` that matches no dpo_error_codes row, so the command does not
        // divert the policy to status 5 (HOLD) instead of failing it.
        $scheduleId = $this->scheduleFixture(6, 1, 'SMOKE-NO-MATCHING-DPO-CODE');

        $reportingError = $this->runCron('policy:processDPOpayment', $scheduleId);

        $schedule = DB::table('scheduled_transactions')->where('id', $scheduleId)->first();
        $this->assertSame(3, (int) $schedule->status, 'Schedule row should be marked failed');
        $this->assertSame(2, (int) $schedule->retry_count, 'Retry counter should have advanced');

        $this->assertOneSmsSent(
            MisRecurringPaymentFailureNotifier::GATEWAY_DPO,
            MisRecurringPaymentFailureNotifier::dpoEventReference($scheduleId, 6, 2)
        );

        $this->warnOnReportingError($reportingError);
    }

    /**
     * The older recurring cron, still runnable by hand.
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function legacy_dpo_recurring_cron_failure_sends_the_sms(): void
    {
        Event::fake([SendMail::class]);
        Storage::fake('s3');

        // NULL tokens => the cron's "missing subscription token" branch, which
        // records the failure WITHOUT contacting DPO.
        $scheduleId = $this->scheduleFixture(4, 1);

        $reportingError = $this->runCron('policy:chargeRecurrentToken', $scheduleId);

        $schedule = DB::table('scheduled_transactions')->where('id', $scheduleId)->first();
        $this->assertSame(3, (int) $schedule->status, 'Schedule row should be marked failed');
        $this->assertSame(2, (int) $schedule->retry_count, 'Retry counter should have advanced');

        $this->assertFailedTransactionRecorded('DPO');
        $this->assertOneSmsSent(
            MisRecurringPaymentFailureNotifier::GATEWAY_DPO,
            MisRecurringPaymentFailureNotifier::dpoEventReference($scheduleId, 4, 2)
        );

        $this->warnOnReportingError($reportingError);
    }

    /**
     * Replace the PDF renderer with a stub for the command's report tail.
     *
     * `policy:processDPOpayment` renders a full A3 landscape report. wkhtmltopdf
     * is not installed here, so Snappy falls back to DomPDF — and rendering that
     * document kills the isolated child process outright (PHPUnit only reports
     * "ended unexpectedly"; raising memory_limit does not help). The report is
     * not what this test covers, and it runs after the SMS has already gone out,
     * so stubbing the renderer keeps the command's control flow intact while
     * removing a crash that has nothing to do with the notification.
     *
     * The facade resolves through app()->make() on every call, so binding an
     * instance is enough to swap it.
     */
    private function stubPdfRenderer(): void
    {
        app()->instance('alphadirect.pdf', new class {
            public function loadView($view, $data = [], $mergeData = [])
            {
                return $this;
            }

            public function setPaper($paper, $orientation = 'portrait')
            {
                return $this;
            }

            public function output(): string
            {
                return '%PDF-1.4 smoke-test stub';
            }
        });
    }

    /**
     * Both DPO crons end by rendering a PDF report and mailing it. That is
     * downstream of the notify() call under test, so a failure there must not
     * fail this smoke — but it is surfaced as a warning rather than swallowed.
     */
    private function runCron(string $command, int $scheduleId): ?\Throwable
    {
        $error = null;

        // Admin\PolicyController carries `ini_set('max_execution_time', 180)` at
        // FILE scope — between its use statements and the class declaration — so
        // the cap lands the instant anything autoloads that class, mid-cron and
        // invisibly. Force the autoload here, then clear the limit it set; these
        // crons need longer than 3 minutes against the remote RDS.
        class_exists(\AlphaDirect\Http\Controllers\Admin\PolicyController::class);
        set_time_limit(0);

        $this->withEnv($this->offlineInfobipEnv(), function () use ($command, $scheduleId, &$error) {
            try {
                Artisan::call($command, ['id' => $scheduleId]);
            } catch (\Throwable $e) {
                $error = $e;
            }
        });

        return $error;
    }

    private function warnOnReportingError(?\Throwable $error): void
    {
        if ($error !== null) {
            $this->addWarning('Cron report tail threw after the SMS was sent: ' . $error->getMessage());
        }
    }

    /**
     * Delivery has to be armed for SendSmsFired to reach its logging code, and
     * the endpoint has to be a dead port so no request leaves the machine.
     */
    private function offlineInfobipEnv(): array
    {
        return [
            'INFOBIP_URL'        => 'http://127.0.0.1:1/sms/2/text/advanced',
            'OTP_FORCE_DELIVERY' => 'true',
        ];
    }

    /** SendSmsFired reads these through env(), so they must be in the process env. */
    private function withEnv(array $overrides, callable $callback): void
    {
        $previous = [];
        foreach ($overrides as $key => $value) {
            $previous[$key] = getenv($key) === false ? null : getenv($key);
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }

        try {
            $callback();
        } finally {
            foreach ($previous as $key => $value) {
                if ($value === null) {
                    putenv($key);
                    unset($_ENV[$key], $_SERVER[$key]);
                } else {
                    putenv("{$key}={$value}");
                    $_ENV[$key]    = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}
