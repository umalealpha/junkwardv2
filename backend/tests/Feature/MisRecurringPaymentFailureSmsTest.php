<?php

namespace Tests\Feature;

use AlphaDirect\Events\SendSms;
use AlphaDirect\Services\MisRecurringPaymentFailureNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Failed-recurring-payment SMS for MIS policies (DPO + RealPay).
 *
 * Covers the acceptance criteria:
 *   - MIS only (a DOM policy gets nothing)
 *   - both gateways trigger it
 *   - body carries the customer name + policy number
 *   - the send is recorded in the SMS/Email Logs (`sms_email_log`)
 *   - the same failed collection never sends twice
 *
 * Fixtures are inserted/removed by hand (no RefreshDatabase — these suites run
 * against a shared MySQL, wiping it is not an option), matching the convention
 * in tests/Feature/Public.
 *
 * No network hop: the dispatch assertions use Event::fake(), and the one test
 * that exercises the real SendSmsFired listener points INFOBIP_URL at a closed
 * local port so cURL fails instantly — which is exactly the path that still
 * writes the sms_email_log row.
 */
class MisRecurringPaymentFailureSmsTest extends TestCase
{
    private const MIS_POLICY = 'MIS2099000001';
    private const DOM_POLICY = 'DOM2099000001';
    private const CELLPHONE  = '71000001';
    private const CONTROL    = 'sendMisRecurringPaymentFailedSMS';

    private int $customerId;
    private ?int $originalControlStatus = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanup();

        $this->customerId = DB::table('customer')->insertGetId([
            'firstName'  => 'Kgomotso',
            'lastName'   => 'Molefe',
            'cellphone'  => self::CELLPHONE,
            'email'      => 'mis-recurring-fail@yopmail.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([self::MIS_POLICY, self::DOM_POLICY] as $policyNumber) {
            DB::table('policies')->insert([
                'policyNumber' => $policyNumber,
                'customer_id'  => $this->customerId,
                'status'       => 1,
                'premium'      => 350.00,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        // The sender is gated on sms_controls; pin it ON and restore afterwards
        // so the test does not depend on how the environment is toggled.
        $this->originalControlStatus = $this->controlStatus();
        $this->setControlStatus(1);
    }

    protected function tearDown(): void
    {
        if ($this->originalControlStatus !== null) {
            $this->setControlStatus($this->originalControlStatus);
        }
        $this->cleanup();

        parent::tearDown();
    }

    private function cleanup(): void
    {
        $policyNumbers = [self::MIS_POLICY, self::DOM_POLICY];

        DB::table('mis_recurring_payment_failure_sms')->whereIn('policyNumber', $policyNumbers)->delete();
        DB::table('sms_email_log')->whereIn('policyNumber', $policyNumbers)->delete();
        DB::table('policies')->whereIn('policyNumber', $policyNumbers)->delete();
        DB::table('customer')->where('email', 'mis-recurring-fail@yopmail.com')->delete();
    }

    private function controlStatus(): ?int
    {
        $status = DB::table('sms_controls')->where('function_name', self::CONTROL)->value('status');

        return $status === null ? null : (int) $status;
    }

    private function setControlStatus(int $status): void
    {
        DB::table('sms_controls')->updateOrInsert(
            ['function_name' => self::CONTROL],
            ['status' => $status, 'updated_at' => now()]
        );
    }

    private function notifier(): MisRecurringPaymentFailureNotifier
    {
        return app(MisRecurringPaymentFailureNotifier::class);
    }

    private function ledgerRow(string $eventReference)
    {
        return DB::table('mis_recurring_payment_failure_sms')
            ->where('event_reference', $eventReference)
            ->first();
    }

    /** @test */
    public function dpo_recurring_failure_sends_sms_with_customer_name_and_policy_number(): void
    {
        Event::fake([SendSms::class]);

        $reference = MisRecurringPaymentFailureNotifier::dpoEventReference(9001, 3, 1);

        $sent = $this->notifier()->notify(
            self::MIS_POLICY,
            MisRecurringPaymentFailureNotifier::GATEWAY_DPO,
            $reference
        );

        $this->assertTrue($sent, 'DPO recurring failure should dispatch an SMS for an MIS policy');

        Event::assertDispatched(SendSms::class, function (SendSms $event) {
            $this->assertSame('+267' . self::CELLPHONE, $event->to);
            $this->assertStringContainsString('Kgomotso Molefe', $event->message);
            $this->assertStringContainsString(self::MIS_POLICY, $event->message);
            $this->assertStringContainsString('was unsuccessful', $event->message);
            $this->assertStringContainsString('Payment Failed', $event->message);
            // policyNumber + hook are what make the sms_email_log row identifiable.
            $this->assertSame(self::MIS_POLICY, $event->extradata['policyNumber']);
            $this->assertSame(MisRecurringPaymentFailureNotifier::TEMPLATE_SLUG, $event->extradata['hook']);

            return true;
        });

        $ledger = $this->ledgerRow($reference);
        $this->assertNotNull($ledger);
        $this->assertSame('SENT', $ledger->status);
        $this->assertSame(MisRecurringPaymentFailureNotifier::GATEWAY_DPO, $ledger->gateway);
        $this->assertSame('+267' . self::CELLPHONE, $ledger->to_cellphone);
    }

    /** @test */
    public function realpay_recurring_failure_sends_sms(): void
    {
        Event::fake([SendSms::class]);

        $reference = MisRecurringPaymentFailureNotifier::realpayEventReference('RPREF00099', 4);

        $sent = $this->notifier()->notify(
            self::MIS_POLICY,
            MisRecurringPaymentFailureNotifier::GATEWAY_REALPAY,
            $reference
        );

        $this->assertTrue($sent, 'RealPay installment failure should dispatch an SMS for an MIS policy');
        Event::assertDispatchedTimes(SendSms::class, 1);

        $ledger = $this->ledgerRow($reference);
        $this->assertNotNull($ledger);
        $this->assertSame('SENT', $ledger->status);
        $this->assertSame(MisRecurringPaymentFailureNotifier::GATEWAY_REALPAY, $ledger->gateway);
        $this->assertStringContainsString(self::MIS_POLICY, $ledger->message);
    }

    /** @test */
    public function the_same_failed_collection_never_sends_twice(): void
    {
        Event::fake([SendSms::class]);

        // Mirrors ProcessWebhookBuffer replaying the same RealPay installment.
        $reference = MisRecurringPaymentFailureNotifier::realpayEventReference('RPREF00100', 2);
        $gateway   = MisRecurringPaymentFailureNotifier::GATEWAY_REALPAY;

        $first  = $this->notifier()->notify(self::MIS_POLICY, $gateway, $reference);
        $second = $this->notifier()->notify(self::MIS_POLICY, $gateway, $reference);
        $third  = $this->notifier()->notify(self::MIS_POLICY, $gateway, $reference);

        $this->assertTrue($first);
        $this->assertFalse($second, 'A replay of the same failed collection must not re-send');
        $this->assertFalse($third);

        Event::assertDispatchedTimes(SendSms::class, 1);

        $this->assertSame(1, DB::table('mis_recurring_payment_failure_sms')
            ->where('event_reference', $reference)
            ->where('gateway', $gateway)
            ->count());
    }

    /** @test */
    public function a_distinct_failed_collection_on_the_same_policy_does_send(): void
    {
        Event::fake([SendSms::class]);

        // Retry 1 and retry 2 of the same installment are two real failures.
        $this->assertTrue($this->notifier()->notify(
            self::MIS_POLICY,
            MisRecurringPaymentFailureNotifier::GATEWAY_DPO,
            MisRecurringPaymentFailureNotifier::dpoEventReference(9002, 5, 1)
        ));
        $this->assertTrue($this->notifier()->notify(
            self::MIS_POLICY,
            MisRecurringPaymentFailureNotifier::GATEWAY_DPO,
            MisRecurringPaymentFailureNotifier::dpoEventReference(9002, 5, 2)
        ));

        Event::assertDispatchedTimes(SendSms::class, 2);
    }

    /** @test */
    public function non_mis_policies_are_ignored(): void
    {
        Event::fake([SendSms::class]);

        $reference = MisRecurringPaymentFailureNotifier::realpayEventReference('RPREF00101', 1);

        $sent = $this->notifier()->notify(
            self::DOM_POLICY,
            MisRecurringPaymentFailureNotifier::GATEWAY_REALPAY,
            $reference
        );

        $this->assertFalse($sent, 'Only MIS policies are in scope');
        Event::assertNotDispatched(SendSms::class);
        $this->assertNull($this->ledgerRow($reference));
    }

    /** @test */
    public function sms_controls_toggle_suppresses_the_send_and_records_why(): void
    {
        Event::fake([SendSms::class]);
        $this->setControlStatus(0);

        $reference = MisRecurringPaymentFailureNotifier::dpoEventReference(9003, 1, 0);

        $sent = $this->notifier()->notify(
            self::MIS_POLICY,
            MisRecurringPaymentFailureNotifier::GATEWAY_DPO,
            $reference
        );

        $this->assertFalse($sent);
        Event::assertNotDispatched(SendSms::class);

        $ledger = $this->ledgerRow($reference);
        $this->assertNotNull($ledger, 'The attempt is still recorded when Ops has the sender switched off');
        $this->assertSame('SUPPRESSED', $ledger->status);
    }

    /**
     * End-to-end through the real SendSmsFired listener, which is what writes
     * the SMS/Email Logs row. INFOBIP_URL points at a closed local port, so
     * cURL fails immediately and the listener takes its CURL_ERROR path — the
     * log row is still written, which is what we are asserting here.
     *
     * @test
     */
    public function the_sms_is_recorded_in_the_sms_email_logs(): void
    {
        $this->withEnv([
            'INFOBIP_URL'        => 'http://127.0.0.1:1/sms/2/text/advanced',
            'OTP_FORCE_DELIVERY' => 'true',
        ], function () {
            $reference = MisRecurringPaymentFailureNotifier::dpoEventReference(9004, 2, 0);

            $this->assertTrue($this->notifier()->notify(
                self::MIS_POLICY,
                MisRecurringPaymentFailureNotifier::GATEWAY_DPO,
                $reference
            ));

            $log = DB::table('sms_email_log')
                ->where('policyNumber', self::MIS_POLICY)
                ->orderByDesc('id')
                ->first();

            $this->assertNotNull($log, 'The SMS must appear in the SMS/Email Logs');
            $this->assertSame('SMS', $log->type);
            $this->assertSame('+267' . self::CELLPHONE, $log->to_cellphone);   // recipient
            $this->assertStringContainsString(self::MIS_POLICY, $log->message); // content
            $this->assertStringContainsString('Kgomotso Molefe', $log->message);
            $this->assertNotEmpty($log->status);                                // status
            $this->assertNotEmpty($log->created_at);                            // timestamp
            $this->assertSame(MisRecurringPaymentFailureNotifier::TEMPLATE_SLUG, $log->hook);

            // The failure ledger points back at that log row.
            $ledger = $this->ledgerRow($reference);
            $this->assertSame('SENT', $ledger->status);
            $this->assertSame((int) $log->id, (int) $ledger->sms_email_log_id);
        });
    }

    /**
     * SendSmsFired reads its delivery gate and Infobip endpoint through env(),
     * so overrides have to land in the process environment, not just config.
     */
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
