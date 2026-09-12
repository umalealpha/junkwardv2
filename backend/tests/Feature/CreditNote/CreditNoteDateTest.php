<?php

namespace Tests\Feature\CreditNote;

use AlphaDirect\Services\CreditNotes\InvoiceCreditNoteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression cover for the credit-note DATE reported on DOMG2024121023
 * (Keetile, 24 Aug 2026): the operator picked 01/07/2025 on the credit-note
 * screen, but the note was recorded — and printed on the Statement of Account —
 * under 24 Aug 2026. Only the credited PERIOD was ever client-supplied; the
 * posting date (policy_ledger.accounting_date / system_date, which is what the
 * statement's Date column prints and sorts by) was hardcoded to now() in both
 * the legacy screen and the first V2 cut.
 *
 * Covered here:
 *   1. post() honours a back-dated `credit_note_date` on the ledger row AND on
 *      the sub-ledger legs, instead of stamping today.
 *   2. omitting it still defaults to today (no behaviour change for the normal
 *      case).
 *   3. updateDate() re-dates an already-posted note — the only way to correct
 *      the notes already in PROD — and moves the sub-ledger with it, while
 *      leaving every amount and the credited period untouched.
 *   4. preview() reports the posting date back, so the screen can show and edit
 *      it (credit_note.created_at is only when the note was RAISED, which is
 *      what previously disagreed with the statement).
 *
 * SAFETY (same two hazards as tests/Feature/AccountStatement/RefundBalanceTest.php):
 *
 *  1. backend/.env's default connection points at a live RDS and phpunit.xml's
 *     sqlite lines are commented out. setUp() forcibly rebinds the "sqlite"
 *     connection to :memory:, makes it the default, and FAILS LOUDLY if the
 *     resulting connection isn't actually sqlite :memory:.
 *
 *  2. Auditing writes to the separate 'mysql_system' connection, which falls
 *     back to the same live host when DB_HOST_SYSTEM is unset. Neutralised via
 *     audit.enabled=false and audit.drivers.database.connection=sqlite.
 *
 * The PDF wrapper and the s3 disk are both faked, so nothing leaves the box.
 *
 * Run ONLY this file:
 *   vendor/bin/phpunit tests/Feature/CreditNote/CreditNoteDateTest.php
 */
class CreditNoteDateTest extends TestCase
{
    private const POLICY_ID   = 901;
    private const CUSTOMER_ID = 555;
    private const INVOICE_ID  = 7001;

    protected function setUp(): void
    {
        parent::setUp();

        // ── HARD SAFETY #1: force in-memory sqlite; refuse anything else. ──
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

        // ── HARD SAFETY #2: neutralise the mysql_system audit-write hazard. ──
        config(['audit.enabled' => false]);
        config(['audit.drivers.database.connection' => 'sqlite']);

        // Neither the document nor the upload is under test: swap the dompdf
        // wrapper the \PDF facade resolves for a stub (dompdf would otherwise
        // render the real blade, remote logo and all) and fake the s3 disk.
        $this->app->instance('dompdf.wrapper', new class {
            public function loadView($view, $data = [], $mergeData = [], $encoding = null) { return $this; }
            public function output() { return '%PDF-1.4 stub'; }
        });
        Storage::fake('s3');

        $this->buildSchema();
        $this->seedFixtures();
    }

    private function buildSchema(): void
    {
        Schema::create('policies', function ($t) {
            $t->increments('id');
            $t->integer('product_id')->nullable();
            $t->integer('customer_id')->nullable();
            $t->string('policyNumber')->nullable();
            $t->decimal('premium', 15, 2)->nullable();
            $t->integer('premium_freq')->nullable();
            $t->date('policyActivatedDate')->nullable();
        });

        Schema::create('customer', function ($t) {
            $t->increments('id');
            $t->string('firstName')->nullable();
            $t->string('lastName')->nullable();
        });

        Schema::create('customer_profile', function ($t) {
            $t->increments('id');
            $t->integer('customer_id')->nullable();
            $t->string('address')->nullable();
        });

        Schema::create('products', function ($t) {
            $t->increments('id');
            $t->string('line_of_business')->nullable();
        });

        Schema::create('vehicle', function ($t) {
            $t->increments('id');
            $t->integer('policy_id')->nullable();
            $t->string('vehiclePlate')->nullable();
        });

        Schema::create('policyactivatecancelleddates', function ($t) {
            $t->increments('id');
            $t->string('policyNumber')->nullable();
            $t->date('cancelled_date')->nullable();
        });

        Schema::create('credit_note', function ($t) {
            $t->increments('id');
            $t->integer('status')->nullable();
            $t->integer('no_of_days')->nullable();
            $t->string('credit_note_no')->nullable();
            $t->integer('customer_id')->nullable();
            $t->integer('policy_id')->nullable();
            $t->string('invoice_no')->nullable();
            $t->integer('invoice_id')->nullable();
            $t->date('transaction_effective_date')->nullable();
            $t->date('transaction_end_date')->nullable();
            $t->decimal('earned_premium', 15, 2)->nullable();
            $t->decimal('unearned_premium', 15, 2)->nullable();
            $t->string('credit_note_file')->nullable();
            $t->timestamps();
        });

        Schema::create('policy_ledger', function ($t) {
            $t->increments('id');
            $t->integer('customer_id')->nullable();
            $t->integer('account_id')->nullable();
            $t->integer('policy_id')->nullable();
            $t->integer('claim_id')->nullable();
            $t->integer('banking_id')->nullable();
            $t->integer('action_id')->nullable();
            $t->string('account_name')->nullable();
            $t->date('accounting_date')->nullable();
            $t->string('trans_type')->nullable();
            $t->string('amount_type')->nullable();
            $t->string('trans_ref')->nullable();
            $t->string('orig_trans')->nullable();
            $t->decimal('unallocated', 15, 2)->nullable();
            $t->date('system_date')->nullable();
            $t->string('trans_sub_type')->nullable();
            $t->date('eff_date')->nullable();
            $t->string('invoice_file')->nullable();
            $t->date('invoice_date')->nullable();
            $t->string('invoice_no')->nullable();
            $t->decimal('invoice_amount', 15, 2)->nullable();
            $t->decimal('premium', 15, 2)->nullable();
            $t->decimal('other_charges', 15, 2)->nullable();
            $t->decimal('due_amount', 15, 2)->nullable();
            $t->decimal('pmts_adjust', 15, 2)->nullable();
            $t->date('due_date')->nullable();
            $t->string('status')->nullable();
            $t->decimal('debit', 15, 2)->nullable();
            $t->decimal('credit', 15, 2)->nullable();
            $t->decimal('balance', 15, 2)->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });

        Schema::create('policy_subledger', function ($t) {
            $t->increments('id');
            $t->integer('customer_id')->nullable();
            $t->integer('account_id')->nullable();
            $t->integer('policy_id')->nullable();
            $t->integer('claim_id')->nullable();
            $t->integer('banking_id')->nullable();
            $t->string('account_name')->nullable();
            $t->date('accounting_date')->nullable();
            $t->string('trans_type')->nullable();
            $t->string('trans_ref')->nullable();
            $t->date('system_date')->nullable();
            $t->decimal('debit', 15, 2)->nullable();
            $t->decimal('credit', 15, 2)->nullable();
            $t->timestamps();
        });
    }

    private function seedFixtures(): void
    {
        DB::table('policies')->insert([
            'id' => self::POLICY_ID, 'product_id' => 7, 'customer_id' => self::CUSTOMER_ID,
            'policyNumber' => 'DOMG2024121023', 'premium' => 778.12, 'premium_freq' => 1,
            'policyActivatedDate' => '2024-12-07',
        ]);
        DB::table('customer')->insert([
            'id' => self::CUSTOMER_ID, 'firstName' => 'Edith', 'lastName' => 'Motshegare',
        ]);
        DB::table('customer_profile')->insert(['customer_id' => self::CUSTOMER_ID, 'address' => 'Gaborone']);
        DB::table('products')->insert(['id' => 7, 'line_of_business' => 'Domestic']);

        // The invoice being credited — the same shape as a DomCom monthly row.
        DB::table('policy_ledger')->insert([
            'id' => self::INVOICE_ID, 'customer_id' => self::CUSTOMER_ID, 'policy_id' => self::POLICY_ID,
            'accounting_date' => '2025-07-07', 'trans_type' => 'Invoice',
            'invoice_date' => '2025-07-07', 'invoice_no' => 'DOMG2024121023-007',
            'invoice_amount' => 778.12, 'debit' => 778.12, 'balance' => 778.12, 'status' => 'Paid',
        ]);
    }

    /** The invoice flow, with nextCreditNoteNo() stubbed: its ORDER BY uses
     *  MySQL's SUBSTRING()/CAST AS UNSIGNED, which sqlite has no equivalent for.
     *  The number itself is irrelevant to the dates under test. */
    private function service(): InvoiceCreditNoteService
    {
        return new class extends InvoiceCreditNoteService {
            protected function nextCreditNoteNo(): string { return 'CR001390'; }
        };
    }

    private function postNote(array $input): array
    {
        return $this->service()->post(self::POLICY_ID, self::INVOICE_ID, $input, null);
    }

    private function ledgerRow()
    {
        return DB::table('policy_ledger')->where('trans_type', 'Credit Note')->first();
    }

    /** 1. A back-dated note lands on the date Finance picked, not today. */
    public function test_posting_date_is_honoured_on_the_ledger_and_sub_ledger(): void
    {
        $this->postNote(['end_date' => '2025-07-20', 'credit_note_date' => '2025-07-01']);

        $cnRow = $this->ledgerRow();
        $this->assertNotNull($cnRow, 'the credit note should post a policy_ledger row');
        $this->assertSame('2025-07-01', substr((string) $cnRow->accounting_date, 0, 10));
        $this->assertSame('2025-07-01', substr((string) $cnRow->system_date, 0, 10));
        $this->assertNotSame(now()->format('Y-m-d'), substr((string) $cnRow->accounting_date, 0, 10));

        $sub = DB::table('policy_subledger')->where('trans_ref', 'CR001390')->get();
        $this->assertNotEmpty($sub, 'the reversal should post sub-ledger legs');
        foreach ($sub as $leg) {
            $this->assertSame('2025-07-01', substr((string) $leg->accounting_date, 0, 10));
            $this->assertSame('2025-07-01', substr((string) $leg->system_date, 0, 10));
        }

        // The credited PERIOD is a separate thing and must not have moved: it
        // still runs from the invoice date to the chosen end date.
        $cn = DB::table('credit_note')->where('credit_note_no', 'CR001390')->first();
        $this->assertSame('2025-07-07', substr((string) $cn->transaction_effective_date, 0, 10));
        $this->assertSame('2025-07-20', substr((string) $cn->transaction_end_date, 0, 10));
    }

    /** 2. Omitting the date keeps the previous behaviour — today. */
    public function test_posting_date_defaults_to_today_when_not_supplied(): void
    {
        $this->postNote(['end_date' => '2025-07-20']);

        $this->assertSame(
            now()->format('Y-m-d'),
            substr((string) $this->ledgerRow()->accounting_date, 0, 10)
        );
    }

    /** 3. The already-wrong rows in PROD can be corrected in place. */
    public function test_update_date_re_dates_the_ledger_and_sub_ledger_without_moving_money(): void
    {
        // Reproduce the reported row: raised today, so stamped today.
        $this->postNote(['end_date' => '2025-07-20']);
        $before = $this->ledgerRow();

        $result = $this->service()->updateDate(self::POLICY_ID, self::INVOICE_ID, '2025-07-01', null);

        $this->assertSame('CR001390', $result['credit_note_no']);
        $this->assertSame('2025-07-01', $result['credit_note_date']);
        $this->assertSame(now()->format('Y-m-d'), $result['previous_date']);
        $this->assertSame(1, $result['ledger_rows']);
        $this->assertGreaterThan(0, $result['sub_ledger_rows']);

        $after = $this->ledgerRow();
        $this->assertSame('2025-07-01', substr((string) $after->accounting_date, 0, 10));
        $this->assertSame('2025-07-01', substr((string) $after->system_date, 0, 10));
        // Nothing but the date may move.
        $this->assertEquals($before->debit, $after->debit);
        $this->assertEquals($before->balance, $after->balance);
        $this->assertSame($before->trans_ref, $after->trans_ref);

        foreach (DB::table('policy_subledger')->where('trans_ref', 'CR001390')->get() as $leg) {
            $this->assertSame('2025-07-01', substr((string) $leg->accounting_date, 0, 10));
        }
    }

    /** 4. The screen can read the posting date back (and so show/edit it). */
    public function test_preview_reports_the_posted_date_of_an_existing_note(): void
    {
        $this->postNote(['end_date' => '2025-07-20', 'credit_note_date' => '2025-07-01']);

        $preview = $this->service()->preview(self::POLICY_ID, self::INVOICE_ID);

        $this->assertNotNull($preview['existing']);
        $this->assertSame('CR001390', $preview['existing']['creditNoteNo']);
        $this->assertSame('2025-07-01', $preview['existing']['postedDate']);
    }

    /** An invoice with no posted note cannot be re-dated. */
    public function test_update_date_refuses_when_no_note_was_raised(): void
    {
        $this->expectExceptionMessage('No posted credit note was found against invoice DOMG2024121023-007.');
        $this->service()->updateDate(self::POLICY_ID, self::INVOICE_ID, '2025-07-01', null);
    }
}
