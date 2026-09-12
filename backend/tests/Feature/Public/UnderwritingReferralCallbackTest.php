<?php

namespace Tests\Feature\Public;

use AlphaDirect\Mail\UnderwritingReferralMail;
use AlphaDirect\Models\AppSetting;
use AlphaDirect\Models\PublicLead;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Motor Comprehensive high-value (>P500k) underwriting referral.
 *
 * The HighValueCallbackCard on start-fe-react's MotorComprehensive page
 * posts vehicleDetails / sumInsured / quoteReference plus
 * notifyUnderwriting=true to POST /api/v1/public/leads/callback. This
 * covers the resulting LeadController::submitCallback behaviour: the
 * structured fields persist on the public_leads row, and the underwriting
 * mailbox (read from the app_settings table, not an env var, so ops can
 * change it without a redeploy) is notified via UnderwritingReferralMail.
 */
class UnderwritingReferralCallbackTest extends TestCase
{
    private array $cleanupLeadIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    protected function tearDown(): void
    {
        if (!empty($this->cleanupLeadIds)) {
            DB::table('public_leads')->whereIn('id', $this->cleanupLeadIds)->delete();
        }
        parent::tearDown();
    }

    /** @test */
    public function notify_underwriting_persists_structured_fields_and_queues_the_referral_email(): void
    {
        Mail::fake();

        $resp = $this->postJson('/api/v1/public/leads/callback', [
            'fullName'           => 'Lesego Segolodi',
            'cellphone'          => '75927903',
            'email'              => 'segolodil@gmail.com',
            'product'            => 'Motor Comprehensive — High Value (>P500k)',
            'message'            => 'Customer requested a callback for a vehicle with sum insured P850,000 which exceeds the online auto-quote ceiling of P500,000.',
            'vehicleDetails'     => '2023 Toyota Land Cruiser — B123ABC',
            'sumInsured'         => 850000,
            'quoteReference'     => 'MOTQ20260000123',
            'notifyUnderwriting' => true,
        ]);

        $resp->assertStatus(201)->assertJsonPath('ok', true);
        $id = (int) $resp->json('id');
        $this->cleanupLeadIds[] = $id;

        $lead = PublicLead::find($id);
        $this->assertSame('2023 Toyota Land Cruiser — B123ABC', $lead->vehicle_details);
        $this->assertSame(850000.00, (float) $lead->sum_insured);
        $this->assertSame('MOTQ20260000123', $lead->quote_reference);
        $this->assertNotNull($lead->underwriting_notified_at);

        $underwritingEmail = AppSetting::get('underwriting_referral_email', 'underwriting@alphadirect.co.bw');
        $underwritingCc    = AppSetting::get('underwriting_referral_cc');

        // UnderwritingReferralMail implements ShouldQueue, so Mail::fake()
        // tracks it as queued rather than synchronously sent.
        Mail::assertQueued(UnderwritingReferralMail::class, function (UnderwritingReferralMail $mail) use ($id, $underwritingEmail, $underwritingCc) {
            return $mail->hasTo($underwritingEmail)
                && (!$underwritingCc || $mail->hasCc($underwritingCc))
                && $mail->lead->id === $id
                && $mail->lead->vehicle_details === '2023 Toyota Land Cruiser — B123ABC'
                && $mail->lead->quote_reference === 'MOTQ20260000123';
        });
    }

    /** @test */
    public function callback_without_notify_flag_skips_the_underwriting_email(): void
    {
        Mail::fake();

        $resp = $this->postJson('/api/v1/public/leads/callback', [
            'fullName'  => 'Regular Caller',
            'cellphone' => '71111111',
            'product'   => 'Motor Comprehensive',
        ]);

        $resp->assertStatus(201);
        $id = (int) $resp->json('id');
        $this->cleanupLeadIds[] = $id;

        Mail::assertNotQueued(UnderwritingReferralMail::class);

        $lead = PublicLead::find($id);
        $this->assertNull($lead->underwriting_notified_at);
        $this->assertNull($lead->vehicle_details);
    }

    /** @test */
    public function callback_rejects_negative_sum_insured(): void
    {
        $resp = $this->postJson('/api/v1/public/leads/callback', [
            'fullName'           => 'Bad Sum Insured',
            'cellphone'          => '75927903',
            'sumInsured'         => -100,
            'notifyUnderwriting' => true,
        ]);

        $resp->assertStatus(422)
             ->assertJsonPath('ok', false)
             ->assertJsonStructure(['ok', 'errors' => ['sumInsured']]);
    }
}
