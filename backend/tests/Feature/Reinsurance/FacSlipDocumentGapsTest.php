<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Http\Controllers\Api\V1\FacRegisterApiController;
use AlphaDirect\Models\FacPlacement;
use AlphaDirect\Models\FacSlip;
use AlphaDirect\Services\Reinsurance\FacSlipService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * DOCUMENTED FAILING REPRODUCTIONS of gaps found while testing the FAC slip
 * feature (branch fix/fac-slip-generation-on-save) against:
 *
 *  - tests/Unit/Reinsurance/FacArithmeticTest.php (pinned figures from the real
 *    June FY26 workbook and signed slip 2026-007),
 *  - the FacSlip model's term-sheet columns
 *    (2026_07_30_100006_add_slip_terms_to_fac.php), and
 *  - D:\reinsurence_25-26\Alpha-Direct-Capacities-Table-2026-27.xlsx (the
 *    conditions attaching to the treaty capacity).
 *
 * ALL FOUR GAPS ARE NOW CLOSED. Every test here started life as a documented
 * failing reproduction, marked incomplete so the suite stayed green while the
 * gap was real. Each has since been fixed in the product and its assertion
 * inverted, so the file now guards the fixes rather than recording the faults:
 *
 *   Gap 1  the slip printed every figure as Pula      -> prints the line currency
 *   Gap 2  the term-sheet wording could not be typed  -> PUT .../slips/{id}/terms
 *   Gap 3  a slip could span two currencies           -> generate() refuses it
 *   Gap 4  filing a signed slip left the panel open   -> storeSignedSlip closes it
 *
 * The convention still holds for anything found next: pin it as a failing
 * reproduction, mark it incomplete, and invert the assertion when it is fixed.
 * Do not loosen an assertion to make a gap go away.
 *
 * ONE TEST HERE IS NOT A GAP BUT A REVERSED RULE, and it is worth knowing which.
 * Basis of cover used to be REFUSED with a 422 wherever the policy stated one
 * (Reinsurance, 24 August 2026 — the slip follows the policy). On 7 September
 * they asked for the underwriter to state it as agreed with the reinsurer, so
 * the refusal became a recorded override: the policy still defaults it,
 * `basis_of_cover_source` says who stated it, and the trail carries both values
 * when they disagree. That is a changed requirement, not a defect fixed, and the
 * assertion was inverted because Reinsurance changed their mind — not because
 * the old behaviour was wrong.
 *
 * SAFETY: same two hazards as tests/Feature/Reinsurance/FacCaptureSplitTest.php
 * — .env's default connection is the PRODUCTION RDS, and the owen-it audit
 * driver falls back to it via mysql_system. setUp() forces in-memory sqlite and
 * neutralises auditing.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/FacSlipDocumentGapsTest.php
 */
class FacSlipDocumentGapsTest extends TestCase
{
    private const COUNTERPARTY_ID = 7;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.driver' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['database.connections.sqlite.foreign_key_constraints' => false]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $conn = DB::connection();
        if ($conn->getDriverName() !== 'sqlite' || $conn->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing to run: expected an in-memory sqlite connection, got '
                . $conn->getDriverName() . ' / ' . $conn->getDatabaseName());
        }

        config(['audit.enabled' => false]);
        config(['audit.drivers.database.connection' => 'sqlite']);
        FacPlacement::disableAuditing();

        config(['fac.vat_rate' => 0.14, 'fac.reconcile_tolerance' => 0.01]);
        config(['fac.recipients.ri_team' => []]);

        Storage::fake('s3');

        $this->buildSchema();
        $this->seedGraphite();

        Auth::setUser($this->actor(41, 'Kefilwe Mokwena'));
    }

    protected function tearDown(): void
    {
        FacPlacement::enableAuditing();
        parent::tearDown();
    }

    // ────────────────────────────────────────────────────────────────────
    //  Gap 1 — the printed slip ignores the placement's currency
    // ────────────────────────────────────────────────────────────────────

    /**
     * CLOSED. The slip now prints the currency the placement is actually in.
     *
     * The line is real: the June master sheet's USD tab states its block as
     * P68,508.74 against USD 5,172.41, an implied rate of 13.24503309. The
     * template used to prefix every figure with "P", so that line printed as
     * P5,172.41 — Dollars labelled as Pula on a document the reinsurer signs.
     */
    public function test_a_foreign_currency_slip_prints_its_own_currency(): void
    {
        $response = $this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-900',
            'gross_ceded_premium' => 5172.41,
            'commission_pct'      => 0.275,
            'currency'            => 'USD',
            'fx_rate'             => 13.24503309,
            'fx_rate_date'        => '2026-06-30',
            'fx_rate_source'      => 'FAC master sheet (manual)',
            'vat_applicable'      => false,
        ]));
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getContent());

        $html = $this->renderSlip('2026-900');

        $this->assertStringContainsString('USD 5,172.41', $html, 'the slip states US Dollars');
        $this->assertStringNotContainsString('P5,172.41', $html, 'no longer mislabelled as Pula');
    }

    /**
     * Pula keeps its "P". The twelve signed samples all print it that way and the
     * reinsurers accept that document, so the fix must not restyle the ordinary
     * case on its way past.
     */
    public function test_a_pula_slip_still_prints_the_pula_symbol(): void
    {
        $this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-901',
            'gross_ceded_premium' => 25532.91,
            'commission_pct'      => 0.275,
        ]));

        $html = $this->renderSlip('2026-901');

        $this->assertStringContainsString('P25,532.91', $html);
        $this->assertStringNotContainsString('BWP 25,532.91', $html, 'Pula prints as P, not as its code');
    }

    // ───────────────────────────────────────────────────────────────────
    //  Gap 2 — CLOSED. The term-sheet wording can now be typed onto a slip
    // ───────────────────────────────────────────────────────────────────

    /**
     * The 30 July migration added description_of_risk, territorial_scope,
     * risk_ceded_text and deductible_text to fac_slips because "without these the
     * generated slip would not match the document the reinsurers already accept".
     * The template printed them; nothing ever wrote them; and there was no slip
     * edit route at all. Every slip therefore printed the migration default
     * forever, whatever the placement had agreed.
     *
     * PUT reinsurance/fac/slips/{slipId}/terms is that missing wire.
     *
     * @dataProvider slipTermSheetFields
     */
    public function test_the_term_sheet_wording_is_captured(string $field, string $posted): void
    {
        $slipId = $this->generatedSlipId('2026-910');

        $response = $this->controller()->updateSlipTerms(
            $this->jsonRequest([$field => $posted]),
            $slipId
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame($posted, FacSlip::findOrFail($slipId)->{$field});
    }

    public static function slipTermSheetFields(): array
    {
        return [
            'Deductible'          => ['deductible_text', '10% of loss minimum P25,000.00 EEL'],
            'Description of Risk' => ['description_of_risk', 'PROFESSIONAL INDEMNITY INSURANCE'],
            'Territorial Scope'   => ['territorial_scope', 'Botswana and Republic of South Africa'],
            'Risk Ceded'          => ['risk_ceded_text', '100% (Nil Retention by Reinsured)'],
        ];
    }

    /** The typed wording has to reach the printed document, not just the row. */
    public function test_the_typed_wording_prints_on_the_slip(): void
    {
        $slipId = $this->generatedSlipId('2026-911');

        $this->controller()->updateSlipTerms($this->jsonRequest([
            'deductible_text'     => '10% of loss minimum P25,000.00 EEL',
            'description_of_risk' => 'PROFESSIONAL INDEMNITY INSURANCE',
            'territorial_scope'   => 'Botswana and Republic of South Africa',
        ]), $slipId);

        $html = $this->renderSlip('2026-911');

        $this->assertStringContainsString('10% of loss minimum P25,000.00 EEL', $html);
        $this->assertStringContainsString('PROFESSIONAL INDEMNITY INSURANCE', $html);
        $this->assertStringContainsString('Botswana and Republic of South Africa', $html);
        $this->assertStringNotContainsString('as per the original policy.', $html,
            'the generic deductible default is gone once a real one is stated');
    }

    /**
     * WAS A REFUSAL, NOW A RECORDED OVERRIDE.
     *
     * Reinsurance's rule of 24 August 2026 was that the slip follows the policy,
     * so a typed basis was refused with a 422 wherever the policy had an answer.
     * On 7 September they asked for the opposite — the underwriter states it as
     * agreed with the reinsurer, because a facultative cession can be written on
     * a different basis from the policy underneath it.
     *
     * The refusal is gone. The reason for it is not: the override is accepted,
     * `basis_of_cover_source` records that an underwriter stated it, and the
     * trail carries both values. A slip may now contradict its policy, but not
     * quietly.
     */
    public function test_basis_of_cover_can_be_overridden_and_the_override_is_recorded(): void
    {
        // Give the policy an answer. Without these tables the lookup returns null,
        // which is the OTHER case — covered by the test below.
        $this->seedPolicyBasisOfCover(902, 'Claims Occurring');

        $slipId = $this->generatedSlipId('2026-912');

        // The service appends "Basis" to the screen name, and does not double it
        // where the label already carries the word.
        $this->assertSame('Claims Occurring Basis', FacSlip::findOrFail($slipId)->basis_of_cover,
            'the policy still supplies the default');
        $this->assertSame('policy', FacSlip::findOrFail($slipId)->basis_of_cover_source);

        $response = $this->controller()->updateSlipTerms(
            $this->jsonRequest(['basis_of_cover' => 'Claims Made Basis']),
            $slipId
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $slip = FacSlip::findOrFail($slipId);
        $this->assertSame('Claims Made Basis', $slip->basis_of_cover, 'the override is taken');
        $this->assertSame('underwriter', $slip->basis_of_cover_source, 'and attributed');

        // The trail names BOTH, because the point of the event is that the two
        // disagree — recording only what was typed would lose the contradiction.
        $e = DB::table('fac_placement_events')
            ->where('event', 'basis_of_cover_overridden')->first();

        $this->assertNotNull($e, 'the override must reach the trail');
        $this->assertStringContainsString('Claims Made Basis', $e->summary);
        $this->assertStringContainsString('Claims Occurring Basis', $e->summary);
    }

    /**
     * Typing the policy's own answer back is NOT an override. Nothing
     * contradicts anything, so nothing is attributed to the underwriter and no
     * event is written — otherwise the trail fills with warnings about slips
     * that agree with their policies.
     */
    public function test_restating_the_policy_basis_is_not_an_override(): void
    {
        $this->seedPolicyBasisOfCover(902, 'Claims Occurring');

        $slipId = $this->generatedSlipId('2026-913');

        // PRECONDITION. Without this the test passes when the policy lookup
        // returns nothing at all — no answer means no contradiction, so the
        // source would read 'policy' and nothing about case would be tested.
        $this->assertSame('Claims Occurring Basis', FacSlip::findOrFail($slipId)->basis_of_cover,
            'the policy must actually state a basis for this test to mean anything');

        $this->controller()->updateSlipTerms(
            $this->jsonRequest(['basis_of_cover' => 'claims occurring basis']),
            $slipId
        );

        $this->assertSame('policy', FacSlip::findOrFail($slipId)->basis_of_cover_source,
            'case and spacing must not manufacture an override');
        $this->assertSame(
            0,
            DB::table('fac_placement_events')->where('event', 'basis_of_cover_overridden')->count()
        );
    }

    /**
     * AN OVERRIDE SURVIVES A REGENERATION. Letting the policy win on the new
     * version would silently revert an agreed reinsurance term, print it, and
     * leave nothing to say it had changed.
     */
    public function test_an_overridden_basis_is_not_reverted_by_regenerating(): void
    {
        $this->seedPolicyBasisOfCover(902, 'Claims Occurring');

        $slipId = $this->generatedSlipId('2026-914');
        $this->controller()->updateSlipTerms(
            $this->jsonRequest(['basis_of_cover' => 'Claims Made Basis']),
            $slipId
        );

        // Regenerate the same slip number: a new version supersedes the old.
        $this->controller()->generateSlip($this->request(['slip_no' => '2026-914']));

        $fresh = FacSlip::where('slip_no', '2026-914')
            ->where('status', '!=', 'superseded')
            ->orderByDesc('version')
            ->firstOrFail();

        $this->assertSame('Claims Made Basis', $fresh->basis_of_cover,
            'the underwriter-stated basis must carry forward');
        $this->assertSame('underwriter', $fresh->basis_of_cover_source);
    }

    /**
     * The narrow case the override exists for.
     *
     * Where the policy cannot state a basis — a combined policy carrying both, or
     * no selection at all — the slip printed a dash and the underwriter had
     * nowhere to say which applies. Now they can, and only then.
     */
    public function test_basis_of_cover_can_be_stated_where_the_policy_cannot(): void
    {
        $slipId = $this->generatedSlipId('2026-915');

        $this->assertNull(FacSlip::findOrFail($slipId)->basis_of_cover,
            'this policy cannot answer, which is what opens the override');

        $response = $this->controller()->updateSlipTerms(
            $this->jsonRequest(['basis_of_cover' => 'Claims Made Basis']),
            $slipId
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $this->assertSame('Claims Made Basis', FacSlip::findOrFail($slipId)->basis_of_cover);
        $this->assertStringContainsString('Claims Made Basis', $this->renderSlip('2026-915'));
    }

    /**
     * Wording an underwriter typed survives a regeneration.
     *
     * Regenerating rebuilds the figures from the placement lines. Losing the terms
     * with them would be worse than never capturing them, because the slip still
     * prints — just with the migration default back in place of what was agreed.
     */
    public function test_typed_wording_carries_forward_to_a_new_version(): void
    {
        $slipId = $this->generatedSlipId('2026-913');

        $this->controller()->updateSlipTerms($this->jsonRequest([
            'deductible_text'   => '10% of loss minimum P25,000.00 EEL',
            'territorial_scope' => 'Botswana and Republic of South Africa',
        ]), $slipId);

        $regenerated = app(FacSlipService::class)->generate('2026-913');

        $this->assertSame(2, (int) $regenerated->version, 'a new version was cut');
        $this->assertSame('10% of loss minimum P25,000.00 EEL', $regenerated->deductible_text);
        $this->assertSame('Botswana and Republic of South Africa', $regenerated->territorial_scope);
    }

    /**
     * A sent slip cannot be retyped. The reinsurer already holds that document;
     * correcting it means generating a new version.
     */
    public function test_a_sent_slip_refuses_a_wording_change(): void
    {
        $slipId = $this->generatedSlipId('2026-914');
        FacSlip::where('id', $slipId)->update(['status' => 'sent']);

        $response = $this->controller()->updateSlipTerms(
            $this->jsonRequest(['deductible_text' => 'Changed after sending']),
            $slipId
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('new version', (string) $response->getContent());
        $this->assertNull(FacSlip::findOrFail($slipId)->deductible_text, 'nothing was written');
    }

    // ────────────────────────────────────────────────────────────────────
    //  Gap 3 — a slip can group lines in two different currencies
    // ────────────────────────────────────────────────────────────────────

    /**
     * FacSlipService::generate() (FacSlipService.php:60-67) refuses to build a
     * slip whose lines carry more than one counterparty — "the slip number has
     * been reused for two different placements and that is a data problem the
     * underwriter has to fix". No equivalent guard exists for currency. Two
     * lines sharing a fac_slip_no in different currencies are silently summed
     * together in the blade template ($grossTotal = $live->sum(...) across
     * mixed-currency rows), producing a meaningless total, currently unlabelled
     * as Pula or anything else because of Gap 1 above.
     *
     * EXPECTED TO FAIL: generate() does not throw.
     */
    public function test_slip_generation_refuses_two_currencies_on_one_slip(): void
    {
        $this->seedDraft([
            'fac_reference' => 'FAC-2026-100001',
            'fac_slip_no'   => '2026-902',
            'currency'      => 'BWP',
        ]);
        $this->seedDraft([
            'fac_reference'       => 'FAC-2026-100002',
            'fac_slip_no'         => '2026-902',
            'currency'            => 'USD',
            'gross_ceded_premium' => 5172.41,
            'commission_amount'   => 1422.41,
            'net_ceded_premium'   => 3750.00,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('more than one currency');

        app(FacSlipService::class)->generate('2026-902');
    }

    /**
     * The guard must name both currencies. An underwriter told only that the slip
     * "has a problem" has to go and find which line is the odd one; told BWP and
     * USD, they already know what they are looking for.
     */
    public function test_the_currency_refusal_names_what_it_found(): void
    {
        $this->seedDraft([
            'fac_reference' => 'FAC-2026-100003',
            'fac_slip_no'   => '2026-906',
            'currency'      => 'BWP',
        ]);
        $this->seedDraft([
            'fac_reference'       => 'FAC-2026-100004',
            'fac_slip_no'         => '2026-906',
            'currency'            => 'USD',
            'gross_ceded_premium' => 5172.41,
            'commission_amount'   => 1422.41,
            'net_ceded_premium'   => 3750.00,
        ]);

        try {
            app(FacSlipService::class)->generate('2026-906');
            $this->fail('the mixed-currency slip should have been refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('BWP', $e->getMessage());
            $this->assertStringContainsString('USD', $e->getMessage());
            $this->assertStringContainsString('2026-906', $e->getMessage(), 'says which slip');
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Gap 4 — filing the countersigned slip never closes the acceptance panel
    // ────────────────────────────────────────────────────────────────────

    /**
     * CLOSED. storeSignedSlip() now closes the acceptance panel in the same
     * transaction that files the document.
     *
     * FacSlip::isAccepted() is explicit that "a slip that has been SENT is not
     * the same as a slip that has been ACCEPTED. Until a row here carries a
     * signatory and a date, the reinsurer has not committed and the cover is not
     * confirmed." Filing the countersigned slip IS that commitment, and it was
     * the one event that never recorded it — so every placement reported
     * "nobody on this panel has signed" against slips Reinsurance physically
     * held.
     */
    public function test_filing_the_signed_slip_closes_the_acceptance_panel(): void
    {
        $created = json_decode($this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-903',
            'gross_ceded_premium' => 10633.20,
            'commission_pct'      => 0.275,
            'ppw_days'            => 90,
        ]))->getContent(), true);

        $this->controller()->storeSignedSlip($this->fileRequest([
            'slip_signed_date' => '2026-08-11',
            'signatory_name'   => 'T. Chimidza',
        ], (int) $created['id']), (int) $created['id']);

        $slip = FacSlip::with('acceptances')->where('slip_no', '2026-903')->firstOrFail();

        $this->assertTrue($slip->isAccepted(), 'the panel closes when the signed slip is filed');
        $this->assertSame('accepted', $slip->status, 'the slip moves off generated');

        $row = $slip->acceptances->firstWhere('reinsurer_id', self::COUNTERPARTY_ID);
        $this->assertNotNull($row, 'the signer has a row on the panel');
        $this->assertSame('2026-08-11', $row->accepted_on->toDateString(),
            'the acceptance carries the date the reinsurer signed, not today');
        $this->assertSame('T. Chimidza', $row->signatory_name);
        $this->assertNotNull($row->signed_document_path,
            'the countersigned document is filed against the acceptance');
    }

    /**
     * The signatory is optional, and the panel must never read as signed by
     * nobody. Where no name is given the accepting company stands in for it —
     * the company is what the panel already holds, and a countersigned slip is
     * not always legible.
     */
    public function test_the_accepting_company_stands_in_for_a_missing_signatory(): void
    {
        $created = json_decode($this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-904',
            'gross_ceded_premium' => 4000.00,
            'commission_pct'      => 0.275,
            'ppw_days'            => 90,
        ]))->getContent(), true);

        $this->controller()->storeSignedSlip($this->fileRequest([
            'slip_signed_date' => '2026-08-11',
        ], (int) $created['id']), (int) $created['id']);

        $slip = FacSlip::with('acceptances')->where('slip_no', '2026-904')->firstOrFail();
        $row  = $slip->acceptances->firstWhere('reinsurer_id', self::COUNTERPARTY_ID);

        $this->assertTrue($slip->isAccepted());
        $this->assertNotNull($row->signatory_name, 'never signed by nobody');
        $this->assertSame($row->accepting_company, $row->signatory_name);
    }

    /**
     * Filing a second document must not restate the first signing date.
     *
     * The date on an acceptance is evidence of when that reinsurer committed.
     * A later filing — a re-scan, a correction to the note, a second document
     * against the same line — cannot move it, or the warranty clock and the
     * cover date both shift under a purely clerical act.
     */
    public function test_a_second_filing_does_not_restate_the_signing_date(): void
    {
        $created = json_decode($this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-905',
            'gross_ceded_premium' => 7500.00,
            'commission_pct'      => 0.275,
            'ppw_days'            => 90,
        ]))->getContent(), true);

        $id = (int) $created['id'];

        $this->controller()->storeSignedSlip($this->fileRequest([
            'slip_signed_date' => '2026-08-11',
            'signatory_name'   => 'First Signatory',
        ], $id), $id);

        $this->controller()->storeSignedSlip($this->fileRequest([
            'slip_signed_date' => '2026-08-20',
            'signatory_name'   => 'Second Signatory',
        ], $id), $id);

        $slip = FacSlip::with('acceptances')->where('slip_no', '2026-905')->firstOrFail();
        $row  = $slip->acceptances->firstWhere('reinsurer_id', self::COUNTERPARTY_ID);

        $this->assertSame('2026-08-11', $row->accepted_on->toDateString(),
            'the first signing date holds');
        $this->assertSame('First Signatory', $row->signatory_name,
            'the first signatory holds');
    }

    // ────────────────────────────────────────────────────────────────────
    //  Helpers (copied from FacCaptureSplitTest for the same fixture shape)
    // ────────────────────────────────────────────────────────────────────

    private function controller(): FacRegisterApiController
    {
        return app(FacRegisterApiController::class);
    }

    /** @param array<string, mixed> $payload */
    private function request(array $payload): Request
    {
        $r = Request::create('/api/v1/reinsurance/fac', 'POST', $payload);
        $r->headers->set('Accept', 'application/json');

        return $r;
    }

    /**
     * A multipart request carrying a signed slip.
     * @param array<string, mixed> $payload
     */
    /**
     * Give a policy a stated basis of cover, the way production holds it.
     *
     * The basis is a sub-coverage dropdown selection: policy_coverage_detail
     * .limit_id (a varchar) points at tb_cvgpclimits.n_PCLimitId_PK, and the
     * screen name is the wording. Same shape as FacBasisOfCoverTest.
     */
    private function seedPolicyBasisOfCover(int $policyId, string $screenName): void
    {
        Schema::create('policy_coverages', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('coverage_id')->nullable();
        });

        Schema::create('policy_coverage_detail', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_coverage_id')->nullable();
            $t->string('limit_id')->nullable();   // varchar, exactly as production holds it
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('tb_cvgpclimits', function ($t) {
            $t->integer('n_PCLimitId_PK');
            $t->string('s_LimitScreenName')->nullable();
            $t->string('s_LimitTypeCode')->nullable();
        });

        DB::table('tb_cvgpclimits')->insert([
            'n_PCLimitId_PK' => 11, 's_LimitScreenName' => $screenName, 's_LimitTypeCode' => 'DROPDOWN',
        ]);
        DB::table('policy_coverages')->insert(['id' => 1, 'policy_id' => $policyId, 'coverage_id' => 101]);
        DB::table('policy_coverage_detail')->insert([
            'id' => 1, 'policy_coverage_id' => 1, 'limit_id' => '11', 'deleted_at' => null,
        ]);
    }

    /** Capture a placement on a slip number, generate the slip, return its id. */
    private function generatedSlipId(string $slipNo): int
    {
        $this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => $slipNo,
            'gross_ceded_premium' => 10633.20,
            'commission_pct'      => 0.275,
        ]));

        return (int) FacSlip::where('slip_no', $slipNo)->orderByDesc('version')->firstOrFail()->id;
    }

    /** A JSON request carrying the given body, for the slip-terms endpoint. */
    private function jsonRequest(array $payload): Request
    {
        $r = Request::create('/api/v1/reinsurance/fac/slips/1/terms', 'PUT', $payload);
        $r->headers->set('Accept', 'application/json');

        return $r;
    }

    /** Render the slip document for a slip number, exactly as FacSlipService does. */
    /**
     * THE THREE UAT ALIGNMENTS OF 7 SEPTEMBER 2026, ON ONE DOCUMENT.
     *
     * Reinsurance generated a test slip, compared it against the signed
     * original, and reported three things. Each is covered on its own
     * elsewhere; this renders one slip with all three and reads it the way they
     * did, because the three interact on a single page and passing separately is
     * not the same as printing together.
     */
    public function test_the_three_uat_alignments_print_on_one_slip(): void
    {
        $this->seedPolicyBasisOfCover(902, 'Claims Occurring');

        $slipId = $this->generatedSlipId('2026-916');

        $this->controller()->updateSlipTerms($this->jsonRequest([
            // 1. the basis, stated by the underwriter against the policy's own
            'basis_of_cover'  => 'Claims Made Basis',
            // 2. the placement terms that fit none of the named rows
            'slip_notes'      => "Subject to survey within 30 days.\nWarranted no known losses.",
            'deductible_text' => '10% of each and every loss minimum P5,000.00',
        ]), $slipId);

        $html = $this->renderSlip('2026-916');

        // 1. Basis of cover — as the underwriter stated it, not the policy's.
        $this->assertStringContainsString('Claims Made Basis', $html);
        $this->assertStringNotContainsString('Claims Occurring Basis', $html,
            'the underwriter overrode the policy, so the policy wording must not print');

        // 2. Notes, with the deductible they were compared against.
        $this->assertStringContainsString('>Notes<', $html);
        $this->assertStringContainsString('Subject to survey within 30 days.', $html);
        $this->assertStringContainsString('Warranted no known losses.', $html);
        $this->assertStringContainsString('10% of each and every loss minimum P5,000.00', $html);
        $this->assertStringNotContainsString('as per the original policy.', $html);

        // 3. Who prepared it — the reported symptom was "PREPARED BY" and nothing.
        $this->assertMatchesRegularExpression(
            '/PREPARED BY\s+\S/',
            $html,
            'PREPARED BY printed with no name after it — the defect reported'
        );
    }

    /**
     * "Automatically recognize the account that generated the slip" — with a REAL
     * User, which is the only way this proves anything.
     *
     * THE REST OF THIS FILE CANNOT CATCH THE BUG REINSURANCE REPORTED. Its auth
     * stub is an anonymous class carrying a public `$name` property, so
     * `Auth::user()->name` reads that property and always answers. Production
     * hands you an AlphaDirect\User, and `users` HAS NO `name` COLUMN — it
     * carries firstName and lastName — so the real value was null, silently,
     * every time. That is why the slip printed "PREPARED BY" and stopped.
     *
     * So this test authenticates an actual AlphaDirect\User and asserts the name
     * survives the whole path: capture defaults `underwriter_name` from it,
     * generate copies it to `prepared_by_name`, and the template prints it.
     * UserDisplayNameTest proves the accessor in isolation; this proves the slip
     * path reaches it.
     */
    public function test_the_slip_names_the_real_account_that_generated_it(): void
    {
        // Not saved: there is no `users` table in this schema, and the accessor
        // is pure. Auditing off because AlphaDirect\User is Auditable and the
        // audit driver would otherwise reach for a connection.
        \AlphaDirect\User::disableAuditing();
        $user = new \AlphaDirect\User;
        $user->forceFill(['id' => 2134, 'firstName' => 'SNEHAL', 'lastName' => 'zunjarrao']);

        // Sanity: the column genuinely does not exist, so this is the real case.
        $this->assertFalse(
            Schema::hasColumn('fac_placements', 'name'),
            'guard against this test silently becoming vacuous'
        );
        $this->assertSame('Snehal Zunjarrao', $user->name, 'the accessor assembles and title-cases');

        Auth::setUser($user);

        // No underwriter_name passed, so capture must fall back to the account.
        $slipId = $this->generatedSlipId('2026-917');

        $this->assertSame('Snehal Zunjarrao', FacSlip::findOrFail($slipId)->prepared_by_name);
        $this->assertMatchesRegularExpression(
            '/PREPARED BY\s+SNEHAL ZUNJARRAO/',
            $this->renderSlip('2026-917'),
            'the account that generated the slip must be named on it'
        );

        \AlphaDirect\User::enableAuditing();
    }

    private function renderSlip(string $slipNo): string
    {
        return view('Reinsurance.fac-slip', [
            'slip'        => FacSlip::with('acceptances')->where('slip_no', $slipNo)->firstOrFail(),
            'lines'       => FacPlacement::where('fac_slip_no', $slipNo)->whereNull('deleted_at')->get(),
            'generatedAt' => now(),
        ])->render();
    }

    private function fileRequest(array $payload, int $id = 1): Request
    {
        $r = Request::create("/api/v1/reinsurance/fac/{$id}/signed-slip", 'POST', $payload, [], [
            'file' => UploadedFile::fake()->create('signed-slip.pdf', 64, 'application/pdf'),
        ]);
        $r->headers->set('Accept', 'application/json');

        return $r;
    }

    private function actor(int $id, string $name): Authenticatable
    {
        return new class($id, $name) implements Authenticatable {
            public function __construct(public int $id, public string $name)
            {
            }

            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return $this->id;
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value)
            {
            }

            public function getRememberTokenName()
            {
                return '';
            }
        };
    }

    /** A captured-but-unsigned line, as process A leaves it. @param array<string,mixed> $overrides */
    private function seedDraft(array $overrides = []): int
    {
        return DB::table('fac_placements')->insertGetId(array_merge([
            'fac_reference'           => 'FAC-2026-000001',
            'fac_slip_no'             => '2026-113',
            'financial_year'          => 'FY2025-26',
            'placement_type'          => 'fac',
            'policy_number'           => 'COMG2026999888',
            'insured_name'            => 'Kalahari Mining (Pty) Ltd',
            'counterparty_id'         => self::COUNTERPARTY_ID,
            'counterparty_name'       => 'Grand Re',
            'currency'                => 'BWP',
            'gross_ceded_premium'     => 8500.00,
            'commission_pct'          => 0.2750,
            'commission_amount'       => 2337.50,
            'net_ceded_premium'       => 6162.50,
            'vat_applicable'          => true,
            'vat_rate'                => 0.14,
            'gross_ceded_premium_bwp' => 8500.00,
            'slip_signed_date'        => null,
            'ppw_due_date'            => null,
            'status'                  => 'placed',
            'source'                  => 'manual',
            'created_by'              => 41,
            'updated_by'              => 41,
            'created_at'              => now(),
            'updated_at'              => now(),
        ], $overrides));
    }

    private function buildSchema(): void
    {
        foreach ([
            '2026_07_30_100002_create_fac_placements_table.php',
            '2026_07_30_100003_create_fac_placement_attachments_table.php',
            '2026_07_30_100004_create_fac_placement_events_table.php',
            '2026_07_30_100005_create_fac_slips_and_period_snapshots.php',
            '2026_07_30_100006_add_slip_terms_to_fac.php',
            '2026_08_11_100007_add_ppw_terms_and_source_premium_to_fac.php',
            '2026_08_24_100008_create_fac_placement_schedule_items_table.php',
            '2026_08_25_100009_add_premium_frequency_and_widen_risk_pct.php',
            '2026_09_07_100009_add_slip_notes_and_basis_source_to_fac.php',
        ] as $migration) {
            (include database_path('migrations/' . $migration))->up();
        }

        Schema::create('reinsurer', function ($t) {
            $t->id();
            $t->string('company_name')->nullable();
        });

        Schema::create('policies', function ($t) {
            $t->id();
            $t->string('policyNumber')->nullable();
            $t->integer('status')->nullable();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->decimal('premium', 14, 2)->nullable();
            $t->decimal('annual_premium', 14, 2)->nullable();
            $t->string('premium_freq')->nullable();
        });
        Schema::create('customer', function ($t) {
            $t->id();
            $t->string('firstName')->nullable();
            $t->string('lastName')->nullable();
        });
        Schema::create('customer_profile', function ($t) {
            $t->id();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->string('entity_type')->nullable();
        });
        Schema::create('companies', function ($t) {
            $t->id();
            $t->string('name')->nullable();
        });
        Schema::create('products', function ($t) {
            $t->id();
            $t->string('name')->nullable();
        });
        Schema::create('policy_actions', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('term_id')->nullable();
            $t->string('transaction_type')->nullable();
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->softDeletes();
        });
        Schema::create('policy_term', function ($t) {
            $t->id();
            $t->date('term_start_date')->nullable();
            $t->date('term_end_date')->nullable();
        });
        Schema::create('policy_reinsurance', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('action_id')->nullable();
            $t->softDeletes();
        });
        Schema::create('policy_ledger', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->string('trans_type')->nullable();
            $t->decimal('credit', 14, 2)->nullable();
            $t->decimal('debit', 14, 2)->nullable();
            $t->date('accounting_date')->nullable();
            $t->softDeletes();
        });
    }

    private function seedGraphite(): void
    {
        DB::table('reinsurer')->insert([
            ['id' => self::COUNTERPARTY_ID, 'company_name' => 'Grand Re'],
        ]);
        DB::table('companies')->insert(['id' => 300, 'name' => 'Kalahari Mining (Pty) Ltd']);
        DB::table('customer')->insert(['id' => 900, 'firstName' => 'Kalahari', 'lastName' => 'Mining']);
        DB::table('customer_profile')->insert([
            'id' => 1, 'customer_id' => 900, 'company_id' => 300, 'entity_type' => 'Organisation',
        ]);
        DB::table('products')->insert(['id' => 8, 'name' => 'Commercial Combined']);
        DB::table('policies')->insert([
            'id' => 902, 'policyNumber' => 'COMG2026999888', 'status' => 1,
            'product_id' => 8, 'customer_id' => 900, 'premium' => 49795.00, 'premium_freq' => '12',
        ]);
        DB::table('policy_term')->insert([
            'id' => 60, 'term_start_date' => '2026-03-01', 'term_end_date' => '2027-02-28',
        ]);
        DB::table('policy_actions')->insert([
            'id' => 70, 'policy_id' => 902, 'term_id' => 60, 'transaction_type' => 'New Business',
            'effective_from' => '2026-03-01', 'effective_to' => '2027-02-28',
        ]);
    }
}
