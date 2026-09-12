<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\FacPlacement;
use AlphaDirect\Models\FacSlip;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The printed slip, against Reinsurance's review of 24 August 2026.
 *
 * They compared the Graphite slip for Strides of Success against the real signed
 * Automatic Facultative slip 2026-002 and reported eight content errors. This pins
 * the five that need no further input from them:
 *
 *   1. the logo was the brand name set in text, not the actual mark
 *   3. the panel read "17% of 17%" where the signed slip reads "17% of 100% Cession"
 *   4. the heading read "FAC SLIP" on an Automatic Facultative placement
 *   6. "PREPARED BY" printed blank, and the PPW terms must not appear at all
 *
 * The three still open are asserted as documented incomplete, so they cannot be
 * quietly forgotten: Basis of Cover (their instruction contradicts their own
 * attachment), quarterly payments, and the Fire & Allied Perils / Business
 * Interruption schedule.
 *
 * Renders the Blade template directly and asserts on the HTML. That is what the PDF
 * engine consumes, so it proves the text without needing a PDF renderer — which is
 * not installed here anyway.
 */
class FacSlipContentTest extends TestCase
{
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
            $this->fail('Refusing to run: expected in-memory sqlite, got '
                . $conn->getDriverName() . ' / ' . $conn->getDatabaseName());
        }

        config(['audit.enabled' => false]);
        config(['audit.drivers.database.connection' => 'sqlite']);
        FacPlacement::disableAuditing();

        $this->buildSchema();
        Auth::setUser($this->actor(41, 'Kefilwe Mokwena'));
    }

    protected function tearDown(): void
    {
        FacPlacement::enableAuditing();
        parent::tearDown();
    }

    /**
     * Render the slip template the way FacSlipService does.
     *
     * @param  array<string,mixed>  $slipAttrs
     * @param  array<int,array<string,mixed>>  $lineSets
     */
    private function render(array $slipAttrs = [], array $lineSets = [[]]): string
    {
        $lines = collect($lineSets)->map(fn ($o, $i) => (object) array_merge([
            'id'                           => $i + 1,
            'counterparty_name'            => 'Grand Re',
            'risk_carrier'                 => 'Grand Re',
            'risk_pct'                     => 0.17,
            'cession_sum_insured'          => 50000000.00,
            'gross_ceded_premium'          => 38657.66,
            'gross_ceded_premium_excl_vat' => 33910.23,
            'net_ceded_premium'            => 26093.92,
            'commission_pct'               => 0.325,
            'commission_amount'            => 12563.74,
            'currency'                     => 'BWP',
            'source_premium'               => 227398.00,
            'underwriter_name'             => 'Elaine Mokone',
            'ppw_terms'                    => '90 days',
            'ppw_days'                     => 90,
            'period_from'                  => '2026-01-01',
            'period_to'                    => '2026-12-31',
            'ri_group_label'               => 'FIRE & ALLIED PERILS AND BUSINESS INTERRUPTION COMBINED',
            'insured_name'                 => 'STRIDES OF SUCCESS (PTY) LTD',
            'is_reversal'                  => false,
            'deleted_at'                   => null,
            // The template filters live lines on status, so a fixture without it
            // renders nothing and every assertion would pass for the wrong reason.
            'status'                       => 'placed',
        ], $o))->values();

        $slip = new FacSlip(array_merge([
            'slip_no'            => '2026-001',
            'version'            => 1,
            'placement_type'     => 'auto_fac',
            'insured_name'       => 'STRIDES OF SUCCESS (PTY) LTD',
            'counterparty_name'  => 'Grand Re',
            'cover_granted'      => 'FIRE & ALLIED PERILS AND BUSINESS INTERRUPTION COMBINED',
            'broker_agent'       => 'DIRECT',
            'period_from'        => '2026-01-01',
            'period_to'          => '2026-12-31',
            'limit_of_indemnity' => 50000000.00,
            'prepared_by_name'   => null,
            'status'             => 'generated',
        ], $slipAttrs));
        $slip->setRelation('acceptances', collect());

        return View::make('Reinsurance.fac-slip', [
            'slip'        => $slip,
            'lines'       => $lines,
            'generatedAt' => now(),
        ])->render();
    }

    // ───────────────────────────── 1. the logo

    /** The real mark, embedded — not the brand name set in text. */
    public function test_the_slip_carries_the_embedded_logo(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data:image/png;base64,', $html, 'the logo is not embedded');
        $this->assertStringContainsString('class="logo"', $html);
    }

    /**
     * Inlined, never linked. A remote URL is fetched by a PDF engine with no
     * session, so it would return nothing useful — or the login page.
     */
    public function test_the_logo_is_not_a_remote_url(): void
    {
        $html = $this->render();

        $this->assertDoesNotMatchRegularExpression(
            '/<img[^>]+src="https?:/i',
            $html,
            'the logo is linked rather than embedded'
        );
    }

    // ───────────────────────────── 4. the heading names the basis

    /** An Auto FAC placement must say so. This was defect 4. */
    public function test_an_auto_fac_placement_is_headed_automatic_facultative(): void
    {
        $html = $this->render(['placement_type' => 'auto_fac']);

        $this->assertStringContainsString('AUTOMATIC FACULTATIVE SLIP NO 2026-001', $html);
    }

    /** An ordinary FAC placement keeps its own heading. */
    public function test_a_plain_fac_placement_is_headed_fac_slip(): void
    {
        $html = $this->render(['placement_type' => 'fac']);

        $this->assertStringContainsString('FAC SLIP NO 2026-001', $html);
        $this->assertStringNotContainsString('AUTOMATIC FACULTATIVE', $html);
    }

    // ───────────────────────────── 3. the acceptance panel

    /**
     * The defect verbatim: the panel read "17% of 17%". The signed slip reads
     * "17.00% of 100.00% Cession" — a sole reinsurer holds the whole cession.
     */
    public function test_a_sole_reinsurer_holds_one_hundred_per_cent_of_the_cession(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression('/17(\.00)?%\s*of\s*100%\s*Cession/', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/17(\.00)?%\s*of\s*17(\.00)?%/',
            $html,
            'the panel still prints the cession total twice'
        );
    }

    /**
     * A panel splitting the cession shows each share of it, against the same
     * first figure. 8.5 / 5.1 / 3.4 of a ceded 17% is 50% / 30% / 20%.
     */
    public function test_a_panel_shows_each_reinsurers_share_of_the_cession(): void
    {
        $html = $this->render([], [
            ['counterparty_name' => 'Grand Re', 'risk_pct' => 0.085],
            ['counterparty_name' => 'FBC Re',   'risk_pct' => 0.051],
            ['counterparty_name' => 'Zep Re',   'risk_pct' => 0.034],
        ]);

        $this->assertMatchesRegularExpression('/8\.5%\s*of\s*50%\s*Cession/', $html);
        $this->assertMatchesRegularExpression('/5\.1%\s*of\s*30%\s*Cession/', $html);
        $this->assertMatchesRegularExpression('/3\.4%\s*of\s*20%\s*Cession/', $html);
    }

    /** A nil cession must not print 0% and understate the reinsurer's commitment. */
    public function test_a_nil_cession_total_does_not_print_a_nil_share(): void
    {
        $html = $this->render([], [['risk_pct' => 0.0]]);

        $this->assertStringNotContainsString('of 0% Cession', $html);
    }

    // ───────────────────────────── 6. prepared by, and no PPW

    /** The underwriter in charge, not whoever generated it. This printed blank. */
    public function test_prepared_by_names_the_underwriter_in_charge(): void
    {
        $html = $this->render([], [['underwriter_name' => 'Elaine Mokone']]);

        $this->assertStringContainsString('PREPARED BY ELAINE MOKONE', $html);
        $this->assertStringNotContainsString('PREPARED BY KEFILWE', $html,
            'the logged-in user was printed instead of the underwriter');
    }

    /**
     * The PPW terms must NOT appear. They are the reinsurer's to set and arrive on
     * the countersigned slip; printing ours invites them to sign against a window
     * we invented. Default assumption where they state nothing is 90 days.
     */
    public function test_the_premium_payment_warranty_is_not_printed(): void
    {
        $html = $this->render([], [['ppw_terms' => '90 days', 'ppw_days' => 90]]);

        $this->assertStringNotContainsString('PPW', $html);
        $this->assertStringNotContainsString('Subject to', $html);
        $this->assertStringNotContainsString('from inception', $html);
    }

    /** The register still HOLDS the warranty — this removed it from the document only. */
    public function test_the_register_still_holds_the_warranty_it_no_longer_prints(): void
    {
        $id = DB::table('fac_placements')->insertGetId([
            'fac_reference'       => 'FAC-2026-000001',
            'fac_slip_no'         => '2026-001',
            'financial_year'      => 'FY2026-27',
            'placement_type'      => 'auto_fac',
            'policy_number'       => 'COMG2024129691',
            'counterparty_name'   => 'Grand Re',
            'currency'            => 'BWP',
            'gross_ceded_premium' => 38657.66,
            'ppw_terms'           => '90 days',
            'ppw_days'            => 90,
            'ppw_due_date'        => '2026-04-01',
            'status'              => 'placed',
            'source'              => 'manual',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $p = FacPlacement::find($id);
        $this->assertSame(90, (int) $p->ppw_days);
        $this->assertSame('90 days', $p->ppw_terms);
        $this->assertNotNull($p->ppw_due_date, 'the breach alarm still needs this date');
    }

    // ───────────────────────────── still open, so they cannot be forgotten

    /**
     * Defect 2 — Basis of Cover. Now read from the policy rather than typed, so the
     * slip cannot contradict the policy it reinsures. Where the policy holds no
     * selection, or holds two different ones, the slip prints a dash and the
     * underwriter states it — see FacBasisOfCoverTest for the lookup rules.
     */
    public function test_the_basis_of_cover_read_from_the_policy_is_printed(): void
    {
        $html = $this->render(['basis_of_cover' => 'Claims Occurring Basis']);

        $this->assertStringContainsString('Basis of Cover', $html);
        $this->assertStringContainsString('Claims Occurring Basis', $html);
    }

    /** A claims-made policy prints claims made. Both real labels are exercised. */
    public function test_a_claims_made_policy_prints_claims_made(): void
    {
        $html = $this->render(['basis_of_cover' => 'Claims Made Basis']);

        $this->assertStringContainsString('Claims Made Basis', $html);
        $this->assertStringNotContainsString('Claims Occurring', $html);
    }

    /**
     * An unknown or ambiguous basis prints a dash rather than an assumption. A
     * guessed basis on a contract document is a misstatement of cover.
     */
    public function test_an_unknown_basis_prints_a_dash_rather_than_a_default(): void
    {
        $html = $this->render(['basis_of_cover' => null]);

        $this->assertStringContainsString('Basis of Cover', $html);
        $this->assertStringNotContainsString('Claims Occurring', $html);
        $this->assertStringNotContainsString('Claims Made', $html);
    }

    /**
     * Defect 5 — the payment frequency and the instalment figure now print. The
     * arithmetic and the frequency variants are proved in FacInstalmentPremiumTest;
     * this only asserts the printed slip carries them.
     */
    public function test_a_quarterly_placement_prints_its_frequency(): void
    {
        $html = $this->render([], [['premium_frequency' => 'quarterly']]);

        $this->assertStringContainsString('(Quarterly Payments)', $html);
        $this->assertStringContainsString('Quarterly Premium Due to Re-insurer(s)', $html);
    }

    /**
     * A slip with no frequency stays silent rather than asserting a single annual
     * payment nobody agreed. This is also what every pre-existing placement will do,
     * so the change cannot restate a slip already sent.
     */
    public function test_a_slip_with_no_frequency_prints_no_instalment(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('Payments)', $html);
        $this->assertStringNotContainsString('Premium Due to Re-insurer(s)', $html);
    }

    /**
     * Defect 8 — the schedule prints only where one was captured. The figures are
     * proved against slip 2026-002 in FacScheduleTest; here the point is that a
     * placement WITHOUT a schedule renders exactly as it did before, with no empty
     * headings and the original single-figure limit row intact.
     */
    public function test_a_slip_without_a_schedule_is_unchanged(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('FIRE AND ALLIED PERILS', $html);
        $this->assertStringNotContainsString('TOTAL LIMITS OF INDEMNITY', $html);
        $this->assertStringContainsString('Limit of Indemnity', $html);
    }

    /**
     * NOTHING PRINTED ON A SLIP MAY BE NON-ASCII.
     *
     * The PDF facade tries wkhtmltopdf first and falls back to DomPDF, and the
     * two disagree on Unicode: wkhtmltopdf prints an em dash, DomPDF prints a
     * replacement character. So the SAME template yields a clean slip on a box
     * with wkhtmltopdf and a contractual document with mojibake on one without,
     * with no error either way. Slips issued to date are clean only because
     * wkhtmltopdf happens to be installed where they are generated.
     *
     * ENTITIES ARE DECODED FIRST. &rsquo; and &middot; are ASCII in this file
     * and pass a naive scan, but DomPDF expands them to U+2019 and U+00B7 and
     * prints replacement characters — three survived the first sweep of this
     * template exactly that way.
     *
     * Pinning the template rather than the output, because the output only
     * shows the fault on whichever engine happens to be installed.
     */
    public function test_nothing_printed_on_the_slip_is_non_ascii(): void
    {
        $blade = file_get_contents(
            resource_path('views/Reinsurance/fac-slip.blade.php')
        );

        // Comments, @php blocks and CSS never reach the page, and prose in a
        // comment is worth keeping readable.
        $printed = preg_replace(
            '/@php.*?@endphp|\{\{--.*?--\}\}|<style>.*?<\/style>/s',
            '',
            $blade
        );

        $this->assertSame(
            0,
            preg_match('/[^\x00-\x7F]/u', $printed),
            'The rendered part of the slip must be ASCII: the DomPDF fallback turns '
            . 'anything else into a replacement character.'
        );

        $this->assertSame(
            0,
            preg_match(
                '/[^\x00-\x7F]/u',
                html_entity_decode($printed, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            ),
            'An HTML entity in the slip expands to a non-ASCII character.'
        );
    }

    // ───────────────── notes: the placement terms with no named row ──────────

    /**
     * Reinsurance, 7 September 2026: "some areas that need to be defined as per
     * the placement terms in the slip ... should be given under the notes which
     * would be input by the underwriter."
     */
    public function test_the_notes_the_underwriter_typed_are_printed(): void
    {
        $html = $this->render(['slip_notes' => 'Cover excludes subsidence and heave.']);

        // The rendered HEADING, not the bare word: `notes-head` and `.notes`
        // appear in the stylesheet on every slip, so asserting on those would
        // pass whether or not anything was printed.
        $this->assertStringContainsString('>Notes<', $html);
        $this->assertStringContainsString('Cover excludes subsidence and heave.', $html);
    }

    /**
     * NO HEADING WHEN THERE ARE NO NOTES. An empty "Notes" section on a document
     * a reinsurer signs reads as terms omitted, not terms absent.
     */
    public function test_a_slip_with_no_notes_prints_no_notes_section(): void
    {
        $this->assertStringNotContainsString('>Notes<', $this->render(['slip_notes' => null]));
        $this->assertStringNotContainsString('>Notes<', $this->render(['slip_notes' => '   ']));
    }

    /** Line breaks survive, because the underwriter types terms one per line. */
    public function test_notes_keep_the_line_breaks_they_were_typed_with(): void
    {
        $html = $this->render(['slip_notes' => "First condition.\nSecond condition."]);

        $this->assertMatchesRegularExpression('/First condition\.\s*<br\s*\/?>/i', $html);
    }

    /**
     * The underwriter types this, so it is escaped. A slip is not a place to
     * render markup nobody intended — and the ASCII test above would not catch
     * it, because tags are ASCII.
     */
    public function test_notes_are_escaped_and_cannot_inject_markup(): void
    {
        $html = $this->render(['slip_notes' => 'Excess <b>P5,000</b> & subject to survey']);

        $this->assertStringNotContainsString('<b>P5,000</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
        $this->assertStringContainsString('&amp;', $html);
    }

    // ───────────────────────────── harness

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

    private function buildSchema(): void
    {
        foreach ([
            '2026_07_30_100002_create_fac_placements_table.php',
            '2026_07_30_100003_create_fac_placement_attachments_table.php',
            '2026_07_30_100004_create_fac_placement_events_table.php',
            '2026_07_30_100005_create_fac_slips_and_period_snapshots.php',
            '2026_07_30_100006_add_slip_terms_to_fac.php',
            '2026_08_11_100007_add_ppw_terms_and_source_premium_to_fac.php',
            // show() reports the schedule, so the table has to exist.
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
    }
}
