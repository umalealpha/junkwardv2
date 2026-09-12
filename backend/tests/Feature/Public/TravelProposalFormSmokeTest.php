<?php

namespace Tests\Feature\Public;

use AlphaDirect\Http\Controllers\Api\V1\PublicTravelProposalController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Smoke test for the digital Travel Insurance Proposal Form
 * (PublicTravelProposalController) — the paper form signed by OTP.
 *
 * Deliberately DB-free, like TravelMapfreEndpointsSmokeTest beside it: what is
 * asserted here is the contract with the Start portal and the ORDER of the
 * gates, both of which are decided before any query runs —
 *
 *   - the declaration endpoint serves the canonical wording (the portal renders
 *     this text, so a drift here is a drift in what customers sign);
 *   - a signature step with no customer OTP session is refused;
 *   - the "If Yes please give details" rule from the paper form is enforced,
 *     and an unanswered question can't slip through as a No.
 *
 * The persistence half (draft → OTP → signed row → stored PDF → contract
 * linkage) needs public_otps, users + permissions and the ops DB, and is
 * covered by the portal e2e run against a live environment.
 */
class TravelProposalFormSmokeTest extends TestCase
{
    // ─── Declaration wording ────────────────────────────────────────────────

    /** The four health questions and four declaration clauses, keyed and versioned. */
    public function test_declaration_endpoint_serves_the_canonical_form_wording(): void
    {
        $response = $this->getJson('/api/v1/public/travel/proposal/declaration');

        $response->assertOk()
            ->assertJson([
                'ok'      => true,
                'version' => PublicTravelProposalController::DECLARATION_VERSION,
            ]);

        $body = $response->json();

        $this->assertCount(4, $body['questions'], 'The paper form asks four health questions.');
        $this->assertSame(
            ['accidents', 'physical_defect', 'chronic_illness', 'other_condition'],
            array_column($body['questions'], 'key'),
        );
        $this->assertCount(4, $body['clauses'], 'The declaration on page 2 has four clauses.');
        $this->assertStringContainsString(
            'pre-existing medical conditions are not covered',
            $body['clauses'][0],
            'Clause 1 carries the pre-existing-conditions exclusion the customer is bound by.',
        );
        $this->assertStringContainsString(
            'medical records',
            $body['clauses'][3],
            'Clause 4 is the consent to access medical records for a claim.',
        );
    }

    /** The static wording route is not swallowed by the numeric {id} route. */
    public function test_declaration_route_is_not_captured_by_the_proposal_id_route(): void
    {
        $this->getJson('/api/v1/public/travel/proposal/declaration')->assertOk();
        // A non-numeric id has no route at all thanks to whereNumber('id').
        $this->getJson('/api/v1/public/travel/proposal/not-a-number')->assertNotFound();
    }

    // ─── The customer's OTP session gates every write ────────────────────────

    /** A complete draft with no Bearer is refused before anything is stored. */
    public function test_saving_a_draft_without_the_customer_session_is_refused(): void
    {
        $this->postJson('/api/v1/public/travel/proposal', $this->validPayload())
            ->assertStatus(401)
            ->assertJson(['ok' => false, 'error' => 'session_required']);
    }

    /** So is asking for a signature code. */
    public function test_sending_the_signature_code_without_the_customer_session_is_refused(): void
    {
        $this->postJson('/api/v1/public/travel/proposal/1/otp/send', ['agent_id' => '99'])
            ->assertStatus(401)
            ->assertJson(['ok' => false, 'error' => 'session_required']);
    }

    /** And so is signing. */
    public function test_signing_without_the_customer_session_is_refused(): void
    {
        $this->postJson('/api/v1/public/travel/proposal/1/otp/verify', ['agent_id' => '99', 'code' => '123456'])
            ->assertStatus(401)
            ->assertJson(['ok' => false, 'error' => 'session_required']);
    }

    /** A malformed code never reaches the OTP service. */
    public function test_a_malformed_signature_code_is_rejected_by_validation(): void
    {
        $this->postJson('/api/v1/public/travel/proposal/1/otp/verify', ['agent_id' => '99', 'code' => '12'])
            ->assertStatus(422);
    }

    // ─── Proposer fields the paper form requires ─────────────────────────────

    /** Occupation, next of kin and passport are on the paper form, so they're required. */
    public function test_the_paper_forms_own_fields_are_required(): void
    {
        $payload = $this->validPayload();
        unset(
            $payload['proposer']['occupation'],
            $payload['proposer']['passport_no'],
            $payload['next_of_kin']['relationship'],
        );

        $this->postJson('/api/v1/public/travel/proposal', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'proposer.occupation',
                'proposer.passport_no',
                'next_of_kin.relationship',
            ]);
    }

    // ─── Health answers ──────────────────────────────────────────────────────

    /** "If Yes please give details" — a Yes with no details is refused. */
    public function test_a_yes_health_answer_without_details_is_refused(): void
    {
        $medical = $this->answers();
        $medical['chronic_illness'] = ['answer' => true, 'details' => '   '];

        $result = $this->normaliseMedical($medical);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(422, $result->getStatusCode());
        $this->assertSame('medical_details_required', $result->getData(true)['error']);
    }

    /** An unanswered question cannot pass as a No. */
    public function test_an_unanswered_health_question_is_refused(): void
    {
        $medical = $this->answers();
        unset($medical['other_condition']);

        $result = $this->normaliseMedical($medical);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame('medical_answers_incomplete', $result->getData(true)['error']);
    }

    /** A clean set normalises to one entry per question, details only on a Yes. */
    public function test_answers_normalise_to_one_entry_per_question(): void
    {
        $medical = $this->answers();
        $medical['accidents'] = ['answer' => true, 'details' => '  Broken ankle, 2019  '];
        // Details typed against a No are dropped rather than stored.
        $medical['physical_defect'] = ['answer' => false, 'details' => 'ignore me'];

        $result = $this->normaliseMedical($medical);

        $this->assertSame([
            'accidents'       => ['answer' => true,  'details' => 'Broken ankle, 2019'],
            'physical_defect' => ['answer' => false, 'details' => null],
            'chronic_illness' => ['answer' => false, 'details' => null],
            'other_condition' => ['answer' => false, 'details' => null],
        ], $result);
    }

    // ─── Trip dates ──────────────────────────────────────────────────────────

    /** Returning before departing is not a trip. */
    public function test_a_return_before_departure_is_refused(): void
    {
        $request = Request::create('/', 'POST', [
            'trip' => ['departure_date' => '2026-09-10', 'return_date' => '2026-09-03'],
        ]);

        $method = new ReflectionMethod(PublicTravelProposalController::class, 'validateTripDates');
        $method->setAccessible(true);
        $result = $method->invoke(app(PublicTravelProposalController::class), $request);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame('invalid_trip_dates', $result->getData(true)['error']);
    }

    // ─── The generated document ──────────────────────────────────────────────

    /**
     * The signed form renders, and it prints the OTP evidence in place of the
     * ink signature. This is the artefact that goes on file, so what it must
     * carry is asserted rather than eyeballed.
     */
    public function test_the_signed_proposal_form_renders_with_the_otp_evidence(): void
    {
        $html = view('v2.livewire.pdf.travel-proposal-form', [
            'proposal'   => (object) [
                'id' => 7, 'reference' => 'AD-TPF-DEADBEEF', 'status' => 'signed',
                'surname' => 'Molefe', 'first_names' => 'Thabo James', 'dob' => '1990-04-11',
                'passport_no' => 'BW1234567', 'occupation' => 'Accountant',
                'address' => 'Plot 123, Block 8, Gaborone', 'email' => 'thabo@example.com',
                'mobile' => '71234567',
                'departure_date' => '2026-09-01', 'return_date' => '2026-09-14',
                'destination' => 'EUROPE', 'trip_type' => 'Holiday',
                'next_of_kin_name' => 'Naledi Molefe', 'next_of_kin_phone' => '72345678',
                'next_of_kin_relationship' => 'Spouse',
                'declaration_version' => PublicTravelProposalController::DECLARATION_VERSION,
                'declaration_accepted_at' => '2026-08-27 09:15:00',
                'otp_id' => 4242, 'otp_cellphone' => '71234567', 'otp_channel' => 'sms',
                'otp_sent_at' => '2026-08-27 09:15:10', 'otp_verified_at' => '2026-08-27 09:15:48',
                'otp_attempts' => 0, 'signed_at' => '2026-08-27 09:15:48',
                'signature_method' => 'otp', 'evidence_hash' => str_repeat('a', 64),
                'contract_number' => 'MAP-0099', 'quote_id' => null,
                'product_name' => 'Travel Plus', 'premium' => '412.50', 'currency' => 'BWP',
                'agent_id' => '99',
            ],
            'travellers' => [
                ['full_name' => 'Naledi Molefe', 'dob' => '1992-02-02', 'passport_no' => 'BW7654321', 'relationship' => 'Spouse'],
            ],
            'medical'    => [
                'accidents'       => ['answer' => true,  'details' => 'Broken ankle, 2019'],
                'physical_defect' => ['answer' => false, 'details' => null],
                'chronic_illness' => ['answer' => false, 'details' => null],
                'other_condition' => ['answer' => false, 'details' => null],
            ],
            'questions'  => PublicTravelProposalController::QUESTIONS,
            'clauses'    => PublicTravelProposalController::DECLARATION_CLAUSES,
        ])->render();

        // The form itself.
        $this->assertStringContainsString('AD-TPF-DEADBEEF', $html);
        $this->assertStringContainsString('Molefe', $html);
        $this->assertStringContainsString('Naledi Molefe', $html);
        $this->assertStringContainsString('Broken ankle, 2019', $html);
        $this->assertStringContainsString('pre-existing medical conditions are not covered', $html);
        // The policy it ended up on.
        $this->assertStringContainsString('MAP-0099', $html);
        // The signature: OTP evidence, not a signature line.
        $this->assertStringContainsString('Signed &amp; verified', $html);
        $this->assertStringContainsString('27/08/2026 09:15:48', $html);
        $this->assertStringContainsString('OTP #4242', $html);
        $this->assertStringContainsString(str_repeat('a', 64), $html);
    }

    /** An unsigned draft renders too — stamped so it can't pass for a proposal. */
    public function test_an_unsigned_draft_is_stamped_as_not_valid(): void
    {
        $html = view('v2.livewire.pdf.travel-proposal-form', [
            'proposal'   => (object) [
                'id' => 8, 'reference' => 'AD-TPF-0000FEED', 'status' => 'draft',
                'surname' => 'Molefe', 'first_names' => 'Thabo', 'dob' => '1990-04-11',
                'passport_no' => null, 'occupation' => null, 'address' => null,
                'email' => 'thabo@example.com', 'mobile' => '71234567',
                'departure_date' => '2026-09-01', 'return_date' => '2026-09-14',
                'destination' => 'EUROPE', 'trip_type' => null,
                'next_of_kin_name' => null, 'next_of_kin_phone' => null,
                'next_of_kin_relationship' => null,
                'declaration_version' => null, 'declaration_accepted_at' => null,
                'otp_id' => null, 'otp_cellphone' => null, 'otp_channel' => null,
                'otp_sent_at' => null, 'otp_verified_at' => null, 'otp_attempts' => null,
                'signed_at' => null, 'signature_method' => null, 'evidence_hash' => null,
                'contract_number' => null, 'quote_id' => null,
                'product_name' => null, 'premium' => null, 'currency' => null,
                'agent_id' => '99',
            ],
            'travellers' => [],
            'medical'    => [],
            'questions'  => PublicTravelProposalController::QUESTIONS,
            'clauses'    => PublicTravelProposalController::DECLARATION_CLAUSES,
        ])->render();

        $this->assertStringContainsString('Unsigned draft', $html);
        $this->assertStringContainsString('Not a valid proposal', $html);
        $this->assertStringContainsString('No other persons travelling', $html);
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    /** Run the controller's health-answer normaliser over a posted `medical` map. */
    private function normaliseMedical(array $medical)
    {
        $request = Request::create('/', 'POST', ['medical' => $medical]);

        $method = new ReflectionMethod(PublicTravelProposalController::class, 'normaliseMedical');
        $method->setAccessible(true);

        return $method->invoke(app(PublicTravelProposalController::class), $request);
    }

    /** All four questions answered No. */
    private function answers(): array
    {
        return [
            'accidents'       => ['answer' => false],
            'physical_defect' => ['answer' => false],
            'chronic_illness' => ['answer' => false],
            'other_condition' => ['answer' => false],
        ];
    }

    /** A schema-valid proposal body — used to prove the GATES fire, not the schema. */
    private function validPayload(): array
    {
        return [
            'agent_id' => '99',
            'proposer' => [
                'surname'     => 'Molefe',
                'first_names' => 'Thabo James',
                'dob'         => '1990-04-11',
                'passport_no' => 'BW1234567',
                'occupation'  => 'Accountant',
                'address'     => 'Plot 123, Block 8, Gaborone',
                'email'       => 'thabo@example.com',
                'mobile'      => '71234567',
            ],
            'trip' => [
                'departure_date' => '2026-09-01',
                'return_date'    => '2026-09-14',
                'destination'    => 'EUROPE',
                'trip_type'      => 'Holiday',
            ],
            'next_of_kin' => [
                'name'         => 'Naledi Molefe',
                'phone'        => '72345678',
                'relationship' => 'Spouse',
            ],
            'travellers' => [
                ['full_name' => 'Naledi Molefe', 'dob' => '1992-02-02', 'passport_no' => 'BW7654321', 'relationship' => 'Spouse'],
            ],
            'medical'              => $this->answers(),
            'declaration_accepted' => true,
        ];
    }
}
