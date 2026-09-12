<?php

namespace Tests\Feature\Reinsurance;

use AlphaDirect\Http\Controllers\Api\V1\FacRegisterApiController;
use AlphaDirect\Models\FacPlacement;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The split at point of entry: capturing a NEW placement going out for signature
 * versus recording one the reinsurer has ALREADY signed.
 *
 * What is being proved is that the choice is not cosmetic. It decides whether the
 * line counts as money owed, whether a signing date is demanded, and which single
 * step comes next — and the SERVER decides those, not the form. A caller that
 * says "new" and posts a signing date anyway must not get a payable placement
 * with a warranty clock already running.
 *
 * SAFETY: the same two hazards as every feature test here — .env's default
 * connection points at the PRODUCTION RDS, and the owen-it audit driver is
 * configured against `mysql_system`, which falls back to the same production host
 * when DB_HOST_SYSTEM is unset. setUp() forces in-memory sqlite and FAILS LOUDLY
 * otherwise, and neutralises auditing three ways. See
 * tests/Feature/SpecifiedItems/SpecifiedItemsCrudTest.php for the original note.
 *
 * Run ONLY this file (`php artisan test` cannot load the suite — see
 * tests/Unit/BusinessHoursCalculatorTest.php):
 *   vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *     tests/Feature/Reinsurance/FacCaptureSplitTest.php
 */
class FacCaptureSplitTest extends TestCase
{
    private const COUNTERPARTY_ID = 7;

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
        FacPlacement::disableAuditing();

        config(['fac.vat_rate' => 0.14, 'fac.reconcile_tolerance' => 0.01]);
        // No recipients, so nothing tries to send mail. The trail is written either
        // way — that is the point of recordEvent.
        config(['fac.recipients.ri_team' => []]);

        // The signed-slip step writes to S3. Faked, so the test never reaches a
        // real bucket.
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
    //  Process A — a new placement, going out for signature
    // ────────────────────────────────────────────────────────────────────

    /**
     * A new placement is a DRAFT, so it is not money owed.
     *
     * Capture used to hardcode `placed` for everything, which put a line the
     * reinsurer had not agreed to into the payable, the frozen month-end snapshot
     * and the journal Finance posts.
     */
    public function test_a_new_placement_is_captured_as_a_draft_and_is_not_payable(): void
    {
        $response = $this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-113',
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
            'ppw_terms'           => '90 days',
            'ppw_days'            => 90,
        ]));

        $this->assertSame(201, $response->getStatusCode());

        $row = DB::table('fac_placements')->first();
        $this->assertSame('draft', $row->status, 'an unsigned placement is not placed');
        $this->assertNull($row->slip_signed_date, 'nothing has been signed yet');
        $this->assertNull($row->ppw_due_date, 'a warranty cannot run from a signature that does not exist');

        // The register's own definition of what is owed excludes drafts.
        $payable = DB::table('fac_placements')->where('status', '!=', 'draft')->sum('gross_ceded_premium');
        $this->assertEqualsWithDelta(0.0, (float) $payable, 0.001,
            'a draft must not appear in the payable');
    }

    /**
     * A signing date sent alongside "new" is DROPPED.
     *
     * The form does not show the field, so anything arriving in it came from a
     * stale draft or a caller that has not been updated. Trusting it would start
     * the premium warranty counting down against an agreement nobody has made.
     */
    public function test_a_signing_date_is_refused_on_a_new_placement(): void
    {
        $this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-113',
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
            'ppw_days'            => 90,
            'slip_signed_date'    => '2026-03-11',   // must not be honoured
            'status'              => 'placed',       // nor this
        ]));

        $row = DB::table('fac_placements')->first();
        $this->assertNull($row->slip_signed_date);
        $this->assertNull($row->ppw_due_date);
        $this->assertSame('draft', $row->status,
            'the entry choice decides the status, not the payload');
    }

    /** The trail says which of the two processes was followed. */
    public function test_the_capture_process_is_recorded_on_the_trail(): void
    {
        $this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-113',
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
        ]));

        $event = DB::table('fac_placement_events')->where('event', 'created')->first();
        $this->assertStringContainsString('awaiting the reinsurer', $event->summary);
        $this->assertSame('new', json_decode($event->payload, true)['captureIntent']);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Reported defect: "slip generation does not work"
    //
    //  Reinsurance captured three placements — two carrying slip numbers, one
    //  without — and no slip was produced for the third. A slip is built by
    //  grouping the lines that share a number, so a line with no number could
    //  never have one: the button is not even rendered without it, and
    //  generateMissing() skips such lines.
    // ────────────────────────────────────────────────────────────────────

    /** A new placement produces its slip ON SAVE, without being asked twice. */
    public function test_a_new_placement_generates_its_slip_on_save(): void
    {
        $response = $this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-113',
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
            'ppw_days'            => 90,
        ]));

        $this->assertSame(201, $response->getStatusCode());

        $slip = DB::table('fac_slips')->where('slip_no', '2026-113')->first();
        $this->assertNotNull($slip, 'saving a new placement must produce its slip');
        $this->assertSame('generated', $slip->status);
        $this->assertSame(1, (int) $slip->version);

        // Retrievable, and in sendable form: the rendered document is on the disk
        // at the path the slip records, which is what download and send both read.
        $this->assertNotNull($slip->document_path, 'the slip must have a stored document');
        Storage::disk('s3')->assertExists($slip->document_path);

        // Reachable FROM the placement, not only from the slips screen.
        $row = DB::table('fac_placements')->first();
        $this->assertSame((int) $slip->id, (int) $row->fac_slip_id);
        $this->assertNotNull($row->slip_generated_at);
    }

    /**
     * The third placement — no slip number typed. The register allocates one and
     * still produces the slip, instead of leaving the line permanently slipless.
     */
    public function test_a_new_placement_with_no_slip_number_is_allocated_one_and_still_gets_a_slip(): void
    {
        $response = $this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
            'ppw_days'            => 90,
            // no fac_slip_no at all — the case that produced no slip
        ]));

        $this->assertSame(201, $response->getStatusCode());

        $row = DB::table('fac_placements')->first();
        $this->assertNotEmpty($row->fac_slip_no, 'a slip number must be allocated');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{3}$/', $row->fac_slip_no,
            'and it must follow the numbering Reinsurance already uses (2026-001)');

        $slip = DB::table('fac_slips')->where('slip_no', $row->fac_slip_no)->first();
        $this->assertNotNull($slip, 'the third placement must get a slip like the other two');
        Storage::disk('s3')->assertExists($slip->document_path);
    }

    /** Allocated numbers are sequential and never collide with a typed one. */
    public function test_allocated_slip_numbers_do_not_collide_with_typed_ones(): void
    {
        $year = (int) now()->format('Y');

        // A line already carrying a hand-typed number in this year's sequence,
        // plus one in a shape that is NOT ours and must be ignored rather than
        // read as a sequence number.
        $this->seedDraft(['fac_reference' => 'FAC-X-1', 'fac_slip_no' => $year . '-007']);
        $this->seedDraft(['fac_reference' => 'FAC-X-2', 'fac_slip_no' => $year . '-113b']);

        $allocated = app(\AlphaDirect\Services\Reinsurance\FacRegisterService::class)->nextSlipNo();

        $this->assertSame($year . '-008', $allocated,
            'the next number follows the highest well-formed one, ignoring 113b');
    }

    /**
     * "Retrievable and in sendable form" — proved on the bytes, not on a flag.
     *
     * An earlier version of the renderer returned raw HTML and stored it at a .pdf
     * path, so send() would have emailed a reinsurer a contractual document no PDF
     * reader could open, and the register would have recorded it as sent. A
     * document_path that exists is therefore not evidence of anything; what is on
     * the end of it is.
     */
    public function test_the_generated_slip_is_a_real_pdf_and_retrievable(): void
    {
        $this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-113',
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
        ]));

        $slip = DB::table('fac_slips')->where('slip_no', '2026-113')->first();
        $this->assertNotNull($slip->document_path);

        // A real PDF, not HTML wearing a .pdf name.
        $bytes = Storage::disk('s3')->get($slip->document_path);
        $this->assertStringStartsWith('%PDF', $bytes,
            'the stored slip must be a PDF a reinsurer can actually open');
        $this->assertGreaterThan(1000, strlen($bytes), 'and not an empty shell');

        // Retrievable through the register's own listing, which is what puts the
        // download and send controls in front of the user.
        $listing = json_decode(
            $this->controller()->slips($this->request([]))->getContent(),
            true
        );
        $row = collect($listing['data'])->firstWhere('slipNo', '2026-113');
        $this->assertNotNull($row, 'the slip must appear in the slips register');
        $this->assertTrue($row['hasDocument'], 'and offer its document');
        $this->assertSame('generated', $row['status'], 'i.e. ready to send');
    }

    /**
     * The whole slip flow with NO AWS credentials at all.
     *
     * The test environment's S3 key is rejected by AWS, which blocked every slip,
     * every signed slip and every attachment behind a problem that has nothing to
     * do with reinsurance. The disk is configuration, so it can point at the
     * server's own filesystem instead — this proves that path end to end rather
     * than assuming it: generated, stored, and handed back as a working link.
     *
     * The link matters because the local driver cannot sign a URL. temporaryUrl()
     * throws on it outright, so without a fallback every download button would
     * have returned a 500 on exactly the environments this is meant to unblock.
     */
    public function test_the_slip_flow_works_on_a_local_disk_with_no_aws_credentials(): void
    {
        Storage::fake('public');
        config(['fac.slips.disk' => 'public', 'fac.documents.disk' => 'public']);
        // Belt and braces: if anything still reaches for s3, the assertions below
        // fail rather than quietly passing against the earlier fake.
        config(['filesystems.disks.s3' => ['driver' => 'local', 'root' => storage_path('app/should-not-be-used')]]);

        $created = json_decode($this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
            'ppw_days'            => 90,
        ]))->getContent(), true);

        // Generated, despite there being no working bucket anywhere.
        $slip = DB::table('fac_slips')->first();
        $this->assertNotNull($slip, 'a slip must still be produced');
        $this->assertSame('generated', $slip->status);
        $this->assertNotNull($slip->document_path);

        Storage::disk('public')->assertExists($slip->document_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('public')->get($slip->document_path),
            'and it must still be a real PDF');

        // Retrievable. The local driver cannot sign, so this exercises the
        // plain-URL fallback rather than temporaryUrl().
        $download = $this->controller()->downloadSlip((int) $slip->id);
        $this->assertSame(200, $download->getStatusCode());
        $body = json_decode($download->getContent(), true);
        $this->assertNotEmpty($body['url'], 'a download link must come back, not an exception');
        $this->assertStringEndsWith('.pdf', $body['name']);

        // And the signed slip can be filed against it on the same disk.
        $this->controller()->storeSignedSlip($this->fileRequest([
            'slip_signed_date' => '2026-03-11',
        ]), (int) $created['id']);

        $doc = DB::table('fac_placement_attachments')
            ->where('fac_placement_id', $created['id'])
            ->where('doc_type', 'fac_slip')
            ->orderByDesc('id')
            ->first();
        Storage::disk('public')->assertExists($doc->path);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Reported defect: "no upload of the signed slip … needed on every
    //  placement, not only legacy ones"
    // ────────────────────────────────────────────────────────────────────

    /**
     * A placement captured a moment ago takes an attachment, exactly as an
     * imported one does. "Not only legacy ones" is the part being proved.
     */
    public function test_a_freshly_captured_placement_accepts_an_attachment(): void
    {
        $created = json_decode($this->controller()->store($this->request([
            'capture_intent'      => 'signed',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-113',
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
            'slip_signed_date'    => '2026-03-11',
            'ppw_days'            => 90,
        ]))->getContent(), true);

        $id = $created['id'];

        $request = Request::create("/api/v1/reinsurance/fac/{$id}/attachments", 'POST', [
            'doc_type' => 'fac_slip',
            'note'     => 'Executed slip received by email.',
        ], [], [
            'file' => UploadedFile::fake()->create('executed-slip.pdf', 32, 'application/pdf'),
        ]);
        $request->headers->set('Accept', 'application/json');

        $response = $this->controller()->storeAttachment($request, $id);
        $this->assertSame(200, $response->getStatusCode());

        // Visible ON the placement record, which is where it was asked for.
        $body = json_decode($response->getContent(), true);
        $doc  = collect($body['attachments'])->firstWhere('originalName', 'executed-slip.pdf');
        $this->assertNotNull($doc, 'the document must appear on the placement');
        $this->assertSame('fac_slip', $doc['docType']);
        $this->assertNotNull($doc['uploadedAt']);

        $stored = DB::table('fac_placement_attachments')->where('fac_placement_id', $id)->first();
        Storage::disk('s3')->assertExists($stored->path);
    }

    /**
     * A renderer failure must not lose the capture.
     *
     * The PDF renderer is the one part of this that depends on something outside
     * the database. A placement discarded because a renderer was down is a worse
     * outcome than a placement whose slip still has to be produced.
     */
    public function test_a_slip_failure_does_not_lose_the_placement(): void
    {
        // Point the slip disk at a driver that does not exist, so the store step
        // inside generate() throws after the PDF is rendered.
        config(['fac.slips.disk' => 'no-such-disk']);

        $response = $this->controller()->store($this->request([
            'capture_intent'      => 'new',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-113',
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
        ]));

        $this->assertSame(201, $response->getStatusCode(), 'the placement is still saved');
        $this->assertSame(1, DB::table('fac_placements')->count());

        $failure = DB::table('fac_placement_events')->where('event', 'slip_generation_failed')->first();
        $this->assertNotNull($failure, 'and the failure is on the trail, not swallowed');
    }

    // ────────────────────────────────────────────────────────────────────
    //  Process B — a placement already signed
    // ────────────────────────────────────────────────────────────────────

    /**
     * "Already signed" without the signing date is refused, naming the field.
     *
     * That date is what the premium payment warranty counts from. Without it the
     * line joins the payable with no deadline the register can enforce — which is
     * precisely the hole the old single screen left open.
     */
    public function test_an_already_signed_placement_demands_the_signing_date(): void
    {
        try {
            $this->controller()->store($this->request([
                'capture_intent'      => 'signed',
                'policy_number'       => 'COMG2026999888',
                'counterparty_id'     => self::COUNTERPARTY_ID,
                'fac_slip_no'         => '2026-113',
                'gross_ceded_premium' => 8500.00,
                'commission_pct'      => 0.275,
                // no slip_signed_date
            ]));
            $this->fail('a signed placement must not be capturable without its signing date');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slip_signed_date', $e->errors());
        }

        $this->assertSame(0, DB::table('fac_placements')->count(), 'nothing may be written');
    }

    /** With the date, it is payable immediately and the warranty date is derived. */
    public function test_an_already_signed_placement_is_payable_with_a_warranty_date(): void
    {
        $response = $this->controller()->store($this->request([
            'capture_intent'      => 'signed',
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'fac_slip_no'         => '2026-113',
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
            'slip_signed_date'    => '2026-03-11',
            'ppw_terms'           => '90 days',
            'ppw_days'            => 90,
        ]));

        $this->assertSame(201, $response->getStatusCode());

        $row = DB::table('fac_placements')->first();
        $this->assertSame('placed', $row->status);
        $this->assertSame('2026-03-11', substr((string) $row->slip_signed_date, 0, 10));
        $this->assertSame('2026-06-09', substr((string) $row->ppw_due_date, 0, 10),
            '11 March plus 90 days is 9 June — derived, never typed');
    }

    /** A future signing date is not a signing date. */
    public function test_a_future_signing_date_is_refused_on_the_signed_slip_step(): void
    {
        $id = $this->seedDraft();

        try {
            $this->controller()->storeSignedSlip($this->fileRequest([
                'slip_signed_date' => now()->addDay()->toDateString(),
            ]), $id);
            $this->fail('a slip cannot be signed in the future');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slip_signed_date', $e->errors());
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  The step that joins the two processes
    // ────────────────────────────────────────────────────────────────────

    /**
     * Filing the signed slip is what moves a draft into the payable — and it does
     * the document and the date together, so neither half can land without the
     * other.
     */
    public function test_filing_the_signed_slip_promotes_a_draft_and_sets_the_warranty(): void
    {
        $id = $this->seedDraft();

        $response = $this->controller()->storeSignedSlip($this->fileRequest([
            'slip_signed_date' => '2026-03-11',
            'note'             => 'Countersigned by Grand Re Botswana.',
        ]), $id);

        $this->assertSame(200, $response->getStatusCode());

        $row = DB::table('fac_placements')->where('id', $id)->first();
        $this->assertSame('placed', $row->status, 'the draft becomes payable');
        $this->assertSame('2026-03-11', substr((string) $row->slip_signed_date, 0, 10));
        $this->assertSame('2026-06-09', substr((string) $row->ppw_due_date, 0, 10),
            'the warranty date is worked out from signing date + window');

        $doc = DB::table('fac_placement_attachments')->where('fac_placement_id', $id)->first();
        $this->assertNotNull($doc, 'the document itself must be on file');
        $this->assertSame('fac_slip', $doc->doc_type);
        $this->assertSame('signed-slip.pdf', $doc->original_name);

        $event = DB::table('fac_placement_events')->where('event', 'slip_signed')->first();
        $this->assertNotNull($event);
        $this->assertStringContainsString('now payable', $event->summary);
        $payload = json_decode($event->payload, true);
        $this->assertTrue($payload['becamePayable']);
        $this->assertSame('2026-06-09', $payload['ppwDueDate']);
        $this->assertSame(41, (int) $event->actor_id);
    }

    /** Filing against a line that was already payable changes no figure. */
    public function test_filing_against_an_already_placed_line_does_not_re_promote_it(): void
    {
        $id = $this->seedDraft(['status' => 'awaiting_premium']);

        $this->controller()->storeSignedSlip($this->fileRequest([
            'slip_signed_date' => '2026-03-11',
        ]), $id);

        $row = DB::table('fac_placements')->where('id', $id)->first();
        $this->assertSame('awaiting_premium', $row->status, 'the status must not be dragged backwards');

        $payload = json_decode(
            DB::table('fac_placement_events')->where('event', 'slip_signed')->value('payload'),
            true
        );
        $this->assertFalse($payload['becamePayable']);
    }

    /** A closed placement takes no slip. */
    public function test_a_settled_placement_cannot_take_a_signed_slip(): void
    {
        $id = $this->seedDraft(['status' => 'settled']);

        $response = $this->controller()->storeSignedSlip($this->fileRequest([
            'slip_signed_date' => '2026-03-11',
        ]), $id);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, DB::table('fac_placement_attachments')->count());
    }

    // ────────────────────────────────────────────────────────────────────
    //  Backwards compatibility
    // ────────────────────────────────────────────────────────────────────

    /**
     * A caller that predates the split is left exactly as it was.
     *
     * The importer and the auto-entry service both post without an intent, and
     * changing what they produce would rewrite history and the reconciliation that
     * depends on it.
     */
    public function test_a_capture_without_an_intent_behaves_as_before(): void
    {
        $this->controller()->store($this->request([
            'policy_number'       => 'COMG2026999888',
            'counterparty_id'     => self::COUNTERPARTY_ID,
            'gross_ceded_premium' => 8500.00,
            'commission_pct'      => 0.275,
            'slip_signed_date'    => '2026-03-11',
            'ppw_days'            => 90,
            'status'              => 'placed',
        ]));

        $row = DB::table('fac_placements')->first();
        $this->assertSame('placed', $row->status);
        $this->assertSame('2026-03-11', substr((string) $row->slip_signed_date, 0, 10),
            'no intent means no interference');
    }

    // ────────────────────────────────────────────────────────────────────
    //  Enhancement: slip numbering continues the existing series
    // ────────────────────────────────────────────────────────────────────

    /** The next number follows the highest already in use, not a fresh count. */
    public function test_an_allocated_slip_number_continues_the_existing_series(): void
    {
        $this->seedDraft(['fac_reference' => 'FAC-2026-000900', 'fac_slip_no' => '2026-041']);
        $this->seedDraft(['fac_reference' => 'FAC-2026-000901', 'fac_slip_no' => '2026-007']);

        $this->assertSame('2026-042', $this->register()->nextSlipNo(2026),
            'it must continue from the highest in the series, not from the newest row');
    }

    /**
     * The generated number is written in the SAME width as the series it joins.
     *
     * Reinsurance flagged that a system format could conflict with the numbers
     * already in use. A four-digit series must not suddenly gain a three-digit
     * member — they would no longer sort together, which is how a register stops
     * reconciling against the master sheet.
     */
    public function test_an_allocated_slip_number_matches_the_series_width(): void
    {
        $this->seedDraft(['fac_reference' => 'FAC-2026-000902', 'fac_slip_no' => '2026-0125']);

        $this->assertSame('2026-0126', $this->register()->nextSlipNo(2026));
    }

    /** Anything not of the form YYYY-nnn is ignored rather than misread. */
    public function test_a_hand_typed_slip_number_does_not_derail_allocation(): void
    {
        $this->seedDraft(['fac_reference' => 'FAC-2026-000903', 'fac_slip_no' => '2026-113b']);
        $this->seedDraft(['fac_reference' => 'FAC-2026-000904', 'fac_slip_no' => '2026-004']);

        $this->assertSame('2026-005', $this->register()->nextSlipNo(2026),
            '"2026-113b" is not a sequence number and must not push the series to 114');
    }

    // ────────────────────────────────────────────────────────────────────
    //  Enhancement: the placement stage
    // ────────────────────────────────────────────────────────────────────

    /**
     * Every point in the cycle reports itself, and the two the request named —
     * awaiting signature and complete — sit at opposite ends of the ladder.
     */
    public function test_each_placement_reports_where_it_sits_in_the_cycle(): void
    {
        $cases = [
            ['awaiting_signature', 'Awaiting signature',      1, ['status' => 'draft']],
            ['awaiting_premium',   'Awaiting client premium', 2, ['status' => 'placed']],
            ['ready_to_settle',    'Ready to settle',         3, ['status' => 'placed', 'client_paid_at' => '2026-04-01 09:00:00']],
            ['ready_to_settle',    'Ready to settle',         3, ['status' => 'ready_to_settle']],
            ['complete',           'Complete',                4, ['status' => 'settled']],
            ['cancelled',          'Cancelled',               0, ['status' => 'cancelled']],
        ];

        foreach ($cases as $i => [$stage, $label, $step, $overrides]) {
            $id = $this->seedDraft(array_merge(
                ['fac_reference' => 'FAC-2026-0009' . (10 + $i)],
                $overrides
            ));

            $row = json_decode($this->controller()->show($id)->getContent(), true);

            $this->assertSame($stage, $row['stage'], "case {$i}");
            $this->assertSame($label, $row['stageLabel'], "case {$i}");
            $this->assertSame($step, $row['stageStep'], "case {$i}");
            $this->assertSame(4, $row['stageOf'], 'four working stages; cancelled is not one of them');
        }
    }

    /**
     * A reversal row reads as cancelled whatever its status says.
     *
     * A cancellation writes a reversing line to take the money back out of the
     * payable. Showing it anywhere on the working ladder would have it read as
     * outstanding work, and it is the opposite of that.
     */
    public function test_a_reversal_is_off_the_ladder(): void
    {
        $id = $this->seedDraft([
            'fac_reference' => 'FAC-2026-000930',
            'status'        => 'placed',
            'is_reversal'   => true,
        ]);

        $row = json_decode($this->controller()->show($id)->getContent(), true);
        $this->assertSame('cancelled', $row['stage']);
        $this->assertSame(0, $row['stageStep']);
    }

    /** The stage filters the register, matching exactly what the column shows. */
    public function test_the_register_can_be_filtered_by_stage(): void
    {
        $this->seedDraft(['fac_reference' => 'FAC-2026-000940', 'status' => 'draft']);
        $this->seedDraft(['fac_reference' => 'FAC-2026-000941', 'status' => 'settled']);
        $this->seedDraft(['fac_reference' => 'FAC-2026-000942', 'status' => 'placed']);

        foreach (['awaiting_signature' => 1, 'complete' => 1, 'awaiting_premium' => 1] as $stage => $expected) {
            $body = json_decode(
                $this->controller()->index($this->request(['stage' => $stage]))->getContent(),
                true
            );
            $this->assertCount($expected, $body['data'], "stage={$stage}");
            $this->assertSame($stage, $body['data'][0]['stage']);
        }
    }

    /**
     * Ascending by default, because the register is read against the slip series.
     */
    public function test_the_register_lists_lowest_number_first_by_default(): void
    {
        $this->seedDraft(['fac_reference' => 'FAC-2026-000950']);
        $this->seedDraft(['fac_reference' => 'FAC-2026-000951']);
        $this->seedDraft(['fac_reference' => 'FAC-2026-000952']);

        $asc = json_decode($this->controller()->index($this->request([]))->getContent(), true);
        $this->assertSame(
            ['FAC-2026-000950', 'FAC-2026-000951', 'FAC-2026-000952'],
            array_column($asc['data'], 'facReference'),
            'the default order must read up the series'
        );

        $desc = json_decode($this->controller()->index($this->request(['sort' => 'newest']))->getContent(), true);
        $this->assertSame('FAC-2026-000952', $desc['data'][0]['facReference'],
            'newest-first must still be available for working the queue');
    }

    // ────────────────────────────────────────────────────────────────────
    //  Plumbing
    // ────────────────────────────────────────────────────────────────────

    private function register(): \AlphaDirect\Services\Reinsurance\FacRegisterService
    {
        return app(\AlphaDirect\Services\Reinsurance\FacRegisterService::class);
    }

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

    /** A multipart request carrying a signed slip. @param array<string, mixed> $payload */
    private function fileRequest(array $payload): Request
    {
        $r = Request::create('/api/v1/reinsurance/fac/1/signed-slip', 'POST', $payload, [], [
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
            'ppw_terms'               => '90 days',
            'ppw_days'                => 90,
            'slip_signed_date'        => null,
            'ppw_due_date'            => null,
            'status'                  => 'draft',
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

        // ── The Graphite side of the policy lookup. V1 tables. ──
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
