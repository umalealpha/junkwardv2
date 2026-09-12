<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use AlphaDirect\Services\Reinsurance\StatementDocumentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The rendered statement of account — RI-18 step 8, BR-ACC-03.
 *
 * THE ONE FAILURE THAT MUST NEVER LEAVE THE BUILDING is an account whose lines
 * do not add up to its balance. Everything else here is layout; that is money.
 *
 * The second is subtler: an account that is arithmetically right but cannot be
 * issued, because BR-ACC-03 wants it broken down by share and the panel is not
 * placed. That is exactly the document somebody sends by mistake, so it has to
 * declare itself on its face.
 *
 * SAFETY: .env's default connection is a live server. setUp() forces in-memory
 * sqlite and fails loudly otherwise.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/StatementDocumentServiceTest.php
 */
class StatementDocumentServiceTest extends TestCase
{
    /** The connection in force before setUp() switched it, restored in tearDown(). */
    private string $connectionBefore = 'mysql';

    /**
     * PUT THE CONNECTION BACK.
     *
     * setUp() switches the DEFAULT connection to in-memory sqlite, and that is
     * global state rather than something scoped to this file. Left switched,
     * every test running after it in the same process resolves against a
     * database holding none of its tables — which is how FacArithmeticTest
     * passed alone at 18 of 18 and errored inside the full suite, on an FX
     * lookup that had nothing to read.
     */
    protected function tearDown(): void
    {
        DB::purge('sqlite');
        DB::setDefaultConnection($this->connectionBefore);

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionBefore = (string) config('database.default');

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

        $this->buildSchema();
    }

    private function service(): StatementDocumentService
    {
        return app(StatementDocumentService::class);
    }

    // ───────────────────────────────────────────── what it refuses

    /**
     * AN ACCOUNT THAT DOES NOT FOOT IS NOT RENDERED AT ALL. summaryFor()
     * computes the settling total and the balance by different routes on
     * purpose; if they ever disagree, the document must not exist for somebody
     * to send.
     */
    public function test_a_statement_that_does_not_foot_is_refused(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 100_000);

        // Force the two routes apart the only way they can be: an item type the
        // balance scope excludes but the settling sum includes would be a code
        // change, so this asserts the guard fires on a genuine mismatch by
        // corrupting the stored balance path.
        $mock = $this->partialMock(\AlphaDirect\Services\Reinsurance\StatementBalanceBuilder::class);
        $mock->shouldReceive('summaryFor')->andReturn([
            'by_type' => [], 'settling' => 100_000.0, 'memorandum' => 0.0,
            'balance' => 97_500.0, 'foots' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not foot');

        app(StatementDocumentService::class)->viewData($s);
    }

    // ───────────────────────────────────────────── what it declares

    /**
     * WITHOUT THE PANEL IT IS A WORKING COPY, NOT AN ACCOUNT. BR-ACC-03 requires
     * the breakdown by share and BR-SEC-08 requires every ceded amount allocated
     * per reinsurer. The figures are right; the allocation is missing — so it is
     * produced for internal checking and says so.
     */
    public function test_without_a_panel_the_document_is_not_issuable(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 100_000);

        $d = $this->service()->viewData($s);

        $this->assertFalse($d['issuable']);
        $this->assertStringContainsString('signing schedule', $d['blockers'][0]);
    }

    /** With a panel on every line, it becomes issuable. */
    public function test_with_a_panel_the_document_becomes_issuable(): void
    {
        $s = $this->statement();
        $item = $this->item($s, TreatyStatementItem::PREMIUM, 100_000);
        $this->share($item->id, 'FMRE', 11.9, 25_000);

        $d = $this->service()->viewData($s);

        $this->assertTrue($d['issuable']);
        $this->assertSame([], $d['blockers']);
    }

    /** An empty statement is not a document either. */
    public function test_a_statement_with_no_items_is_not_issuable(): void
    {
        $d = $this->service()->viewData($this->statement());

        $this->assertFalse($d['issuable']);
        $this->assertStringContainsString('no items', implode(' ', $d['blockers']));
    }

    // ───────────────────────────────────────────── what it prints

    /**
     * THE SECTION TOTAL IS THE SUM OF THE PRINTED LINES, not the rate applied to
     * a total. Where those differ by a cent the printed one has to be right,
     * because a reinsurer adds up the column in front of them.
     */
    public function test_a_section_total_is_the_sum_of_its_printed_lines(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 33_333.33, 'Property');
        $this->item($s, TreatyStatementItem::PREMIUM, 33_333.33, 'Motor');
        $this->item($s, TreatyStatementItem::PREMIUM, 33_333.34, 'Guarantee');

        $section = $this->service()->viewData($s)['sections'][0];

        $this->assertCount(3, $section['rows']);
        $this->assertSame(
            100_000.0,
            round(array_sum(array_column($section['rows'], 'amount')), 2)
        );
        $this->assertSame(100_000.0, $section['total']);
    }

    /** Memorandum lines print below the balance, never inside it. */
    public function test_memorandum_lines_are_kept_out_of_the_balance(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 100_000);
        $this->item($s, TreatyStatementItem::OUTSTANDING_LOSSES, 500_000, 'Motor', 2026);

        $d = $this->service()->viewData($s);

        $this->assertSame(100_000.0, $d['balance']);
        $this->assertCount(1, $d['memorandum']);
        $this->assertStringContainsString('Outstanding losses', $d['memorandum'][0]['label']);
        $this->assertStringContainsString('2026', $d['memorandum'][0]['label']);
    }

    /** The axis is named, because the same claim sits in different buckets. */
    public function test_the_outstanding_axis_is_named(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 1_000);
        $item = $this->item($s, TreatyStatementItem::OUTSTANDING_LOSSES, 500, 'Motor', 2026);
        $item->update(['period_axis' => TreatyStatementItem::AXIS_UNDERWRITING]);

        $this->assertSame('underwriting year', $this->service()->viewData($s)['outstandingAxis']);
    }

    /** A negative balance is owed the other way, and the document says which. */
    public function test_a_negative_balance_is_due_from_reinsurers(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 10_000);
        $this->item($s, TreatyStatementItem::CLAIMS_PAID, -60_000);

        $this->assertSame(-50_000.0, $this->service()->viewData($s)['balance']);
    }

    /** Shares total per reinsurer across the lines they participate in. */
    public function test_a_reinsurer_is_totalled_across_the_lines_it_shares(): void
    {
        $s = $this->statement();
        $premium = $this->item($s, TreatyStatementItem::PREMIUM, 100_000);
        $comm    = $this->item($s, TreatyStatementItem::COMMISSION, -37_500);
        $this->share($premium->id, 'FMRE', 11.9, 11_900);
        $this->share($comm->id, 'FMRE', 11.9, -4_462.50);

        $shares = $this->service()->viewData($s)['shares'];

        $this->assertCount(1, $shares);
        $this->assertSame(7_437.50, $shares[0]['amount'], 'both lines, one reinsurer');
        $this->assertSame(11.9, $shares[0]['share_pct'], 'quoted on the 100% basis');
    }

    // ───────────────────────────────────────────── the document itself

    /**
     * IT ACTUALLY RENDERS. A template that only ever passes an array assertion
     * is one that breaks the first time somebody asks for the PDF — and this one
     * is a contractual document.
     */
    public function test_the_statement_renders_to_a_pdf(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 384_858.87, 'Property');
        $this->item($s, TreatyStatementItem::COMMISSION, -144_322.09, 'Property');
        $this->item($s, TreatyStatementItem::BROKERAGE, -9_621.46, 'Property');
        $this->item($s, TreatyStatementItem::CLAIMS_PAID, -17_174.50, 'Property');

        $pdf = $this->service()->renderPdf($s);

        $this->assertStringStartsWith('%PDF', $pdf, 'a real PDF, not HTML at a .pdf path');
        $this->assertGreaterThan(2000, strlen($pdf));
    }

    /**
     * NOTHING THAT REACHES THE PAGE MAY BE NON-ASCII.
     *
     * The PDF facade tries wkhtmltopdf first and falls back to DomPDF. The two
     * do not agree on Unicode: wkhtmltopdf prints an em dash and DomPDF prints a
     * replacement character. So the SAME code produces a clean document on a box
     * with wkhtmltopdf and a contractual document with mojibake in its headings
     * on one without — which is how this was found, the title reading
     * "GENERAL QUOTA SHARE ? STATEMENT OF ACCOUNT".
     *
     * Pinning the template rather than the output, because the output only shows
     * the fault on whichever engine happens to be installed.
     */
    public function test_nothing_printed_on_the_statement_is_non_ascii(): void
    {
        $blade = file_get_contents(
            resource_path('views/Reinsurance/treaty-statement.blade.php')
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
            'The rendered part of the statement template must be ASCII: the DomPDF '
            . 'fallback turns anything else into a replacement character.'
        );

        // AND NO ENTITY THAT EXPANDS TO ONE. &bull; is ASCII in this file and so
        // slips past the check above, but DomPDF expands it to U+2022 and prints
        // a replacement character — a single mojibake byte survived the first
        // fix exactly this way. Decode first, then look again.
        $this->assertSame(
            0,
            preg_match('/[^\x00-\x7F]/u', html_entity_decode($printed, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'An HTML entity in the template expands to a non-ASCII character.'
        );
    }

    /** And the labels the service builds, which are printed just the same. */
    public function test_the_service_builds_only_ascii_labels(): void
    {
        $s = $this->statement();
        $this->item($s, TreatyStatementItem::PREMIUM, 1_000);
        $this->item($s, TreatyStatementItem::OUTSTANDING_LOSSES, 500, 'Motor', 2026);

        $d = $this->service()->viewData($s);

        $printed = json_encode([
            array_column($d['sections'], 'heading'),
            array_column($d['sections'], 'total_label'),
            array_column($d['memorandum'], 'label'),
            $d['blockers'],
        ], JSON_UNESCAPED_UNICODE);

        $this->assertSame(0, preg_match('/[^\x00-\x7F]/u', $printed), $printed);
    }

    // ───────────────────────────────────────────── fixtures

    private function item(
        TreatyStatement $s,
        string $type,
        float $amount,
        string $class = 'Property',
        ?int $year = null
    ): TreatyStatementItem {
        return $s->items()->create([
            'item_type'        => $type,
            'regulatory_class' => $class,
            'period_year'      => $year,
            'amount'           => $amount,
        ]);
    }

    private function share(int $itemId, string $reinsurer, float $ofHundred, float $amount): void
    {
        DB::table('treaty_statement_shares')->insert([
            'treaty_statement_item_id' => $itemId,
            'reinsurer'                => $reinsurer,
            'share_of_hundred_pct'     => $ofHundred,
            'share_of_cession_pct'     => round($ofHundred / 0.70, 6),
            'amount'                   => $amount,
        ]);
    }

    private function statement(string $treaty = 'general'): TreatyStatement
    {
        $p = TreatyStatement::periodFor(2026, 1);

        return TreatyStatement::create([
            'treaty' => $treaty, 'underwriting_year' => 2026, 'quarter' => 1,
            'period_start' => $p['start'], 'period_end' => $p['end'],
            'render_due' => $p['render_due'], 'status' => TreatyStatement::STATUS_DRAFT,
        ]);
    }

    private function buildSchema(): void
    {
        Schema::create('treaty_statements', function ($t) {
            $t->id();
            $t->string('treaty', 32);
            $t->unsignedSmallInteger('underwriting_year');
            $t->unsignedTinyInteger('quarter');
            $t->date('period_start');
            $t->date('period_end');
            $t->date('render_due');
            $t->date('confirm_due')->nullable();
            $t->string('status', 32)->default('draft');
            $t->timestamp('rendered_at')->nullable();
            $t->string('rendered_by', 191)->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->string('confirmed_by', 191)->nullable();
            $t->string('cession_basis', 16)->default('legacy');
            $t->text('note')->nullable();
            $t->string('added_by', 191)->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('treaty_statement_items', function ($t) {
            $t->id();
            $t->unsignedBigInteger('treaty_statement_id');
            $t->string('item_type', 32);
            $t->string('regulatory_class', 64)->nullable();
            $t->unsignedSmallInteger('period_year')->nullable();
            $t->string('period_axis', 24)->nullable();
            $t->decimal('amount', 20, 2)->default(0);
            $t->decimal('basis_amount', 20, 2)->nullable();
            $t->decimal('rate', 12, 8)->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('treaty_statement_shares', function ($t) {
            $t->id();
            $t->unsignedBigInteger('treaty_statement_item_id');
            $t->string('reinsurer', 191);
            $t->decimal('share_of_cession_pct', 9, 6)->nullable();
            $t->decimal('share_of_hundred_pct', 9, 6)->nullable();
            $t->decimal('amount', 20, 2)->default(0);
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::create('treaty_reserve_deposits', function ($t) {
            $t->id();
            $t->unsignedBigInteger('treaty_statement_id')->nullable();
            $t->string('reinsurer', 191)->nullable();
            $t->boolean('is_domestic')->default(false);
            $t->boolean('has_letter_of_credit')->default(false);
            $t->decimal('premium_base', 20, 2)->default(0);
            $t->decimal('retained_pct', 9, 6)->nullable();
            $t->decimal('retained', 20, 2)->default(0);
            $t->decimal('released', 20, 2)->default(0);
            $t->decimal('call_rate_pct', 9, 6)->nullable();
            $t->decimal('interest_margin_pct', 9, 6)->nullable();
            $t->decimal('interest_accrued', 20, 2)->default(0);
            $t->decimal('balance_carried', 20, 2)->default(0);
            $t->text('note')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
