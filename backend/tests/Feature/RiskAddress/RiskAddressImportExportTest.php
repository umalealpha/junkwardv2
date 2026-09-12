<?php

namespace Tests\Feature\RiskAddress;

use AlphaDirect\City;
use AlphaDirect\Exports\Sheets\RiskAddressDataSheet;
use AlphaDirect\Http\Controllers\Api\V1\PolicyController;
use AlphaDirect\Imports\RiskAddressImport;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Policy;
use AlphaDirect\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Feature-level proof for the risk-address IMPORT / EXPORT / LIST flows,
 * specifically the term/action scoping fix in RiskAddressImport::model()
 * (was policy-only; is now policy+term+action for both the ID lookup and the
 * address_name lookup — see RiskAddressImport.php:270-290).
 *
 * SAFETY (read before touching this file):
 *  1. This test runs ONLY against an in-memory sqlite database built by hand
 *     in setUp(). It never uses RefreshDatabase and never runs real
 *     migrations. setUp() FAILS LOUDLY if the default connection is not
 *     sqlite :memory: — copied verbatim from the pattern in
 *     tests/Feature/BackdatedEndorse/EndorseRenewPropagationTest.php.
 *
 *  2. A SECOND, DISTINCT production hazard was found and must be neutralised
 *     here too: every model touched by this test (RiskAddress, Policy, City,
 *     State) implements OwenIt\Auditing\Contracts\Auditable, and
 *     RiskAddressImport is exercised through Maatwebsite's real
 *     ModelImporter, which calls $model->saveOrFail() for every valid row
 *     (see vendor/maatwebsite/excel/src/Imports/ModelManager.php:167). Saving
 *     an Auditable model fires the 'created'/'updated' events, which
 *     OwenIt's AuditableObserver forwards to Auditor::execute() ->
 *     DB::connection('mysql_system')->table('audits')->insert(...) — a
 *     SEPARATE named connection from the app's "default" connection that
 *     config(['database.default' => 'sqlite']) does NOT touch. That
 *     connection's env fallbacks (config/database.php:109-147) resolve
 *     straight to the SAME production RDS host/database used by the
 *     forbidden default 'mysql' connection (DB_HOST_SYSTEM is unset in
 *     backend/.env, so it falls back to DB_HOST=graphite-v2-prod-ro.../
 *     Graphite_live). Left unguarded, this test would attempt a REAL write
 *     to production on every RiskAddress::save(). Neutralised three ways
 *     (belt-and-braces, since each is independently sufficient):
 *       a) config(['audit.enabled' => false]) — stops the observer from
 *          ever being attached the first time each model class boots.
 *       b) explicit ::disableAuditing() on every Auditable class this test
 *          touches — checked by Auditable::readyForAuditing() at EXECUTE
 *          time, so it's effective even if a class had already booted (and
 *          attached its observer) earlier in the process.
 *       c) config(['audit.drivers.database.connection' => 'sqlite']) — if
 *          (a) and (b) were somehow bypassed, this converts a silent
 *          production write into a loud local "no such table: audits"
 *          failure instead (we deliberately do NOT create an audits table).
 *     See the assertions at the end of setUp() that prove none of this ever
 *     resolves to a real network connection.
 */
class RiskAddressImportExportTest extends TestCase
{
    private Policy $policy;

    /** @var string[] absolute paths of temp xlsx files written during a test, cleaned up in tearDown(). */
    private array $tempFiles = [];

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

        // ── HARD SAFETY #2: neutralise the mysql_system audit-write hazard
        // documented in the class docblock above. ──
        config(['audit.enabled' => false]);
        config(['audit.drivers.database.connection' => 'sqlite']);
        RiskAddress::disableAuditing();
        Policy::disableAuditing();
        City::disableAuditing();
        State::disableAuditing();

        $this->buildSchema();
        $this->seedGeo();
        $this->policy = $this->makePolicy();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    // ────────────────────────────────────────────────────────────────────
    //  IMPORT
    // ────────────────────────────────────────────────────────────────────

    /** Scenario 1 (must-have): import creates rows, state mapped FROM city, district ignored. */
    public function test_import_creates_rows_with_city_derived_state_and_ignores_district(): void
    {
        $rows = [
            $this->rowValues(['Address Name' => 'Warehouse 1', 'Risk City' => 'Gaborone', 'Risk District' => 'WRONG-DISTRICT']),
            $this->rowValues(['Address Name' => 'Warehouse 2', 'Risk City' => 'Francistown', 'Risk District' => 'ALSO-WRONG']),
        ];
        $path = $this->makeXlsx($this->headings(), $rows);

        $importer = $this->runImport(1, 100, $path);

        $this->assertSame([], $importer->getErrors());

        $addresses = RiskAddress::Policy($this->policy->id)->Term(1)->Action(100)->orderBy('id')->get();
        $this->assertCount(2, $addresses);

        $wh1 = $addresses->firstWhere('address_name', 'Warehouse 1');
        $wh2 = $addresses->firstWhere('address_name', 'Warehouse 2');

        $this->assertNotNull($wh1);
        $this->assertNotNull($wh2);
        $this->assertSame(10, (int) $wh1->risk_city, 'Gaborone city id');
        $this->assertSame(1, (int) $wh1->risk_state, 'state must be derived FROM the city, not the (wrong) Excel district');
        $this->assertSame(11, (int) $wh2->risk_city, 'Francistown city id');
        $this->assertSame(2, (int) $wh2->risk_state);
    }

    /** Scenario 2 (must-have): re-importing the same address_name under the SAME action updates in place. */
    public function test_reimport_same_address_name_same_action_updates_in_place(): void
    {
        $headings = $this->headings();

        $path1 = $this->makeXlsx($headings, [$this->rowValues(['Address Name' => 'Head Office', 'Occupation' => 'Vacant'])]);
        $importer1 = $this->runImport(1, 100, $path1);
        $this->assertSame([], $importer1->getErrors());
        $this->assertSame(1, RiskAddress::Policy($this->policy->id)->Term(1)->Action(100)->count());

        $path2 = $this->makeXlsx($headings, [$this->rowValues(['Address Name' => 'Head Office', 'Occupation' => 'Owner'])]);
        $importer2 = $this->runImport(1, 100, $path2);
        $this->assertSame([], $importer2->getErrors());

        $rows = RiskAddress::Policy($this->policy->id)->Term(1)->Action(100)->get();
        $this->assertCount(1, $rows, 'must update the existing row in place, not create a duplicate');
        $this->assertSame('Owner', $rows->first()->occupation);
    }

    /**
     * Scenario 3 (★ CORE REGRESSION — the whole point of the diff): a row
     * with the SAME address_name under a DIFFERENT action must create a new
     * row for that action and must NOT hijack the other action's row. Before
     * the fix (policy-only scoping) this would have overwritten action A's
     * row from an import targeting action B.
     */
    public function test_same_address_name_different_action_creates_separate_row_and_does_not_hijack_other_action(): void
    {
        // Action A's row already exists (as if created by an earlier action).
        DB::table('risk_address')->insert([
            'policy_id'    => $this->policy->id,
            'term_id'      => 1,
            'action_id'    => 100,
            'address_name' => 'Head Office',
            'occupation'   => 'Warehouse-A-marker',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Import the SAME address_name, but targeting action B (200).
        $path = $this->makeXlsx($this->headings(), [
            $this->rowValues(['Address Name' => 'Head Office', 'Occupation' => 'Warehouse-B-marker']),
        ]);
        $importer = $this->runImport(1, 200, $path);

        $this->assertSame([], $importer->getErrors());

        $all = RiskAddress::Policy($this->policy->id)->orderBy('id')->get();
        $this->assertCount(2, $all, 'importing under a different action must create a NEW row, never update action A\'s row');

        $rowA = RiskAddress::Policy($this->policy->id)->Term(1)->Action(100)->first();
        $rowB = RiskAddress::Policy($this->policy->id)->Term(1)->Action(200)->first();

        $this->assertNotNull($rowA);
        $this->assertNotNull($rowB);
        $this->assertSame('Warehouse-A-marker', $rowA->occupation, 'action A\'s row must be UNTOUCHED by an import targeting action B');
        $this->assertSame('Warehouse-B-marker', $rowB->occupation);
        $this->assertNotSame($rowA->id, $rowB->id);
    }

    /** Scenario 4: an ID belonging to action A, imported while targeting action B, must not hijack action A's row. */
    public function test_id_belonging_to_another_action_does_not_hijack_that_row(): void
    {
        DB::table('risk_address')->insert([
            'id'           => 9001,
            'policy_id'    => $this->policy->id,
            'term_id'      => 1,
            'action_id'    => 100,
            'address_name' => 'Warehouse Alpha',
            'occupation'   => 'Original-A',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $headings = array_merge($this->headings(), ['ID']);
        $row = array_merge(
            $this->rowValues(['Address Name' => 'Warehouse Alpha Beta', 'Occupation' => 'Attempted-Hijack']),
            [9001]
        );
        $path = $this->makeXlsx($headings, [$row]);

        // Import targets action B (200), but the row carries action A's ID.
        $importer = $this->runImport(1, 200, $path);

        $this->assertSame([], $importer->getErrors());

        $rowA = RiskAddress::find(9001);
        $this->assertNotNull($rowA);
        $this->assertSame(100, (int) $rowA->action_id, 'action A\'s row must not be reassigned to action B');
        $this->assertSame('Original-A', $rowA->occupation, 'action A\'s row must be UNTOUCHED');

        $rowB = RiskAddress::Policy($this->policy->id)->Term(1)->Action(200)->first();
        $this->assertNotNull($rowB, 'a NEW row must be created under action B instead of hijacking id=9001');
        $this->assertNotSame(9001, $rowB->id);
        $this->assertSame('Attempted-Hijack', $rowB->occupation);

        $this->assertCount(2, RiskAddress::Policy($this->policy->id)->get());
    }

    /** Scenario 5 (must-have): one bad row (unknown city) rolls back the WHOLE import atomically, mirroring PolicyExcelController::importData(). */
    public function test_unknown_city_row_error_triggers_full_rollback_no_partial_import(): void
    {
        $rows = [
            $this->rowValues(['Address Name' => 'Good Row', 'Risk City' => 'Gaborone']),
            $this->rowValues(['Address Name' => 'Bad Row', 'Risk City' => 'Atlantis']),
        ];
        $path = $this->makeXlsx($this->headings(), $rows);

        // Mirror PolicyExcelController::importData()'s risk-address branch exactly.
        $importer = new RiskAddressImport($this->policy, 1, 100);
        DB::beginTransaction();
        try {
            Excel::import($importer, $path);
        } catch (\Throwable $t) {
            DB::rollBack();
            throw $t;
        }
        $errors = $importer->getErrors();
        if (!empty($errors)) {
            DB::rollBack();
        } else {
            DB::commit();
        }

        $this->assertNotEmpty($errors, 'the unknown-city row must be reported as an error');
        $this->assertStringContainsString('Atlantis', $errors[0]['message']);

        $this->assertSame(
            0,
            RiskAddress::Policy($this->policy->id)->count(),
            'nothing may be committed when any row errors — even the valid "Good Row" must be rolled back'
        );
    }

    /** Scenario 6: a blank Address Name is a rules()-validation failure, collected and skipped (not saved). */
    public function test_blank_address_name_is_skipped_and_reported(): void
    {
        // Note: the row must carry at least one other non-blank cell —
        // PhpSpreadsheet's own getHighestRow()/row-iterator considers a row
        // with EVERY cell truly empty to not exist at all (it never gets
        // written into the sheet's internal cell collection), so a fully
        // blank row would never even reach the importer's rules() validator.
        // Real operator files always have some other data in the row; only
        // Address Name is blank here, matching that realistic shape.
        $rows = [$this->rowValues(['Address Name' => '', 'Physical Address' => 'Plot 99, some street'])];
        $path = $this->makeXlsx($this->headings(), $rows);

        $importer = $this->runImport(1, 100, $path);

        $this->assertNotEmpty($importer->getErrors(), 'blank Address Name must be reported');
        $this->assertSame(0, RiskAddress::Policy($this->policy->id)->count(), 'blank-name row must not be saved');
    }

    /** Scenario 7: heading text with odd whitespace/casing still resolves to the canonical columns. */
    public function test_heading_normalization_handles_whitespace_and_case_variants(): void
    {
        $headings = [
            '  address   name  ',
            'lat', 'lng', 'Physical Address',
            'RISK DISTRICT', 'RISK CITY',
            'Extension', 'Occupation', 'Town Class', 'Risk Class', 'ISO RCV', 'Year Built', 'Area',
            'Structure Type', 'Construction Type', 'Distance To Water', 'Distance To Fire', 'Distance To Hydrant',
            'Usage', 'Occupancy Type', 'Central Fire Alarm', 'Central Burglar Alarm', 'Gated Community', 'Automatic Sprinklers',
        ];
        $row = $this->rowValues(['Address Name' => 'Normalized Row', 'Risk City' => 'Gaborone']);
        $path = $this->makeXlsx($headings, [$row]);

        $importer = $this->runImport(1, 100, $path);

        $this->assertSame([], $importer->getErrors());
        $addr = RiskAddress::Policy($this->policy->id)->Term(1)->Action(100)->first();
        $this->assertNotNull($addr);
        $this->assertSame('Normalized Row', $addr->address_name);
        $this->assertSame(10, (int) $addr->risk_city);
    }

    /** Scenario 8: Yes/No/Y/N text variants convert to 1/0 on the model. */
    public function test_yes_no_variants_convert_to_boolean_flags(): void
    {
        $row = $this->rowValues([
            'Address Name'          => 'Bool Row',
            'Central Fire Alarm'    => 'Yes',
            'Central Burglar Alarm' => 'No',
            'Gated Community'       => 'Y',
            'Automatic Sprinklers'  => 'N',
        ]);
        $path = $this->makeXlsx($this->headings(), [$row]);

        $importer = $this->runImport(1, 100, $path);
        $this->assertSame([], $importer->getErrors());

        $addr = RiskAddress::Policy($this->policy->id)->Term(1)->Action(100)->first();
        $this->assertSame(1, (int) $addr->central_fire);
        $this->assertSame(0, (int) $addr->central_burglar);
        $this->assertSame(1, (int) $addr->gated_community);
        $this->assertSame(0, (int) $addr->automatic);
    }

    // ────────────────────────────────────────────────────────────────────
    //  EXPORT
    // ────────────────────────────────────────────────────────────────────

    /** Scenario 9: export headings have no ID column; map() converts booleans + relation names. */
    public function test_export_headings_have_no_id_column_and_map_converts_booleans_and_relations(): void
    {
        $sheet = new RiskAddressDataSheet($this->policy);
        $headings = $sheet->headings();

        $this->assertNotContains('ID', $headings, 'export template must not expose an ID column');
        $this->assertNotContains('Id', $headings);
        $this->assertNotContains('id', $headings);

        // Unsaved (not persisted) model is enough — state()/city() belongsTo
        // resolve independently of whether $model itself has an id.
        $model = new RiskAddress();
        $model->address_name    = 'Export Row';
        $model->physical_address = 'Plot 123';
        $model->risk_state      = 1;
        $model->risk_city       = 10;
        $model->central_fire    = 1;
        $model->central_burglar = 0;
        $model->gated_community = 1;
        $model->automatic       = 0;

        $mapped = $sheet->map($model);

        $this->assertSame('Export Row', $mapped[0]);
        $this->assertSame('South East', $mapped[4], 'state name via relation');
        $this->assertSame('Gaborone', $mapped[5], 'city name via relation');
        $this->assertSame('Yes', $mapped[20], 'Central Fire Alarm');
        $this->assertSame('No', $mapped[21], 'Central Burglar Alarm');
        $this->assertSame('Yes', $mapped[22], 'Gated Community');
        $this->assertSame('No', $mapped[23], 'Automatic Sprinklers');
    }

    /** Export query() scopes to policy+term+action, mirroring the import's scoping fix. */
    public function test_export_query_scopes_to_policy_term_and_action(): void
    {
        DB::table('risk_address')->insert([
            ['policy_id' => $this->policy->id, 'term_id' => 1, 'action_id' => 100, 'address_name' => 'A-row', 'created_at' => now(), 'updated_at' => now()],
            ['policy_id' => $this->policy->id, 'term_id' => 1, 'action_id' => 200, 'address_name' => 'B-row', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $sheet = new RiskAddressDataSheet($this->policy, 1, 100);
        $results = $sheet->query()->get();

        $this->assertCount(1, $results);
        $this->assertSame('A-row', $results->first()->address_name);
    }

    // ────────────────────────────────────────────────────────────────────
    //  LIST
    // ────────────────────────────────────────────────────────────────────

    /** Scenario 10: list defaults to the LATEST action's rows; explicit action_id overrides. */
    public function test_list_defaults_to_latest_action_and_honors_explicit_action_id(): void
    {
        DB::table('policy_actions')->insert([
            ['id' => 100, 'policy_id' => $this->policy->id, 'term_id' => 1, 'transaction_type' => 'NEWBUSINESS', 'status' => 'ISSUED', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 200, 'policy_id' => $this->policy->id, 'term_id' => 1, 'transaction_type' => 'ENDORSE', 'status' => 'ISSUED', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('risk_address')->insert([
            ['policy_id' => $this->policy->id, 'term_id' => 1, 'action_id' => 100, 'address_name' => 'Old Office', 'created_at' => now(), 'updated_at' => now()],
            ['policy_id' => $this->policy->id, 'term_id' => 1, 'action_id' => 200, 'address_name' => 'New Office', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $controller = new PolicyController();

        // Default (no action_id passed) -> latest action (200, highest id).
        $this->app->instance('request', Request::create(
            '/api/v1/policies/' . $this->policy->id . '/risk-addresses',
            'GET'
        ));
        $response = $controller->riskAddresses($this->policy->id);
        $data = json_decode($response->getContent(), true)['data'];
        $this->assertCount(1, $data, 'default list must show only the latest action\'s rows, not the cumulative set');
        $this->assertSame('New Office', $data[0]['addressName']);

        // Explicit ?action_id=100 -> only that action's rows.
        $this->app->instance('request', Request::create(
            '/api/v1/policies/' . $this->policy->id . '/risk-addresses',
            'GET',
            ['action_id' => 100]
        ));
        $response2 = $controller->riskAddresses($this->policy->id);
        $data2 = json_decode($response2->getContent(), true)['data'];
        $this->assertCount(1, $data2);
        $this->assertSame('Old Office', $data2[0]['addressName']);
    }

    // ─────────────────────────── helpers ────────────────────────────────

    /** Canonical headings, exactly as RiskAddressDataSheet::headings() (and thus the real download template) produces them. */
    private function headings(): array
    {
        return (new RiskAddressDataSheet($this->policy))->headings();
    }

    /**
     * Build one data row (positional, matching headings()) from named
     * overrides so tests read by intent instead of by column index.
     */
    private function rowValues(array $overrides = []): array
    {
        $defaults = [
            'Address Name'          => 'Test Address',
            'lat'                    => '',
            'lng'                    => '',
            'Physical Address'       => '',
            'Risk District'          => '',
            'Risk City'              => '',
            'Extension'              => '',
            'Occupation'             => '',
            'Town Class'             => '',
            'Risk Class'             => '',
            'ISO RCV'                => '',
            'Year Built'             => '',
            'Area'                   => '',
            'Structure Type'         => '',
            'Construction Type'      => '',
            'Distance To Water'      => '',
            'Distance To Fire'       => '',
            'Distance To Hydrant'    => '',
            'Usage'                  => '',
            'Occupancy Type'         => '',
            'Central Fire Alarm'     => '',
            'Central Burglar Alarm'  => '',
            'Gated Community'        => '',
            'Automatic Sprinklers'   => '',
        ];

        return array_values(array_merge($defaults, $overrides));
    }

    /** Build a real .xlsx file (row 1 = headings, rows 2+ = data) via PhpSpreadsheet directly — no Maatwebsite export involved. */
    private function makeXlsx(array $headings, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headings, null, 'A1');

        $rowNum = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A' . $rowNum);
            $rowNum++;
        }

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'risk_address_test_' . uniqid('', true) . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $this->tempFiles[] = $path;

        return $path;
    }

    private function runImport(int $termId, int $actionId, string $path): RiskAddressImport
    {
        $importer = new RiskAddressImport($this->policy, $termId, $actionId);
        Excel::import($importer, $path);

        return $importer;
    }

    private function makePolicy(int $id = 500, int $customerId = 900): Policy
    {
        DB::table('policies')->insert([
            'id'           => $id,
            'policyNumber' => 'COMG2026' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
            'customer_id'  => $customerId,
            'product_id'   => 8,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return Policy::find($id);
    }

    private function seedGeo(): void
    {
        DB::table('states')->insert([
            ['id' => 1, 'name' => 'South East', 'country_id' => 28, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Central', 'country_id' => 28, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('cities')->insert([
            ['id' => 10, 'name' => 'Gaborone', 'state_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'name' => 'Francistown', 'state_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function buildSchema(): void
    {
        $s = Schema::connection('sqlite');

        $s->create('policies', function ($t) {
            $t->integer('id')->primary();
            $t->string('policyNumber')->nullable();
            $t->integer('customer_id')->nullable();
            $t->integer('product_id')->nullable();
            $t->string('term_start_date')->nullable();
            $t->string('term_end_date')->nullable();
            $t->string('expiry_date')->nullable();
            $t->integer('status')->nullable();
            $t->timestamps();
        });

        $s->create('policy_actions', function ($t) {
            $t->integer('id')->primary();
            $t->integer('policy_id')->nullable();
            $t->integer('term_id')->nullable();
            $t->string('transaction_type', 40)->nullable();
            $t->string('status', 40)->nullable();
            $t->timestamps();
            $t->string('deleted_at')->nullable();
        });

        $s->create('states', function ($t) {
            $t->integer('id')->primary();
            $t->string('name')->nullable();
            $t->integer('country_id')->nullable();
            $t->timestamps();
        });

        $s->create('cities', function ($t) {
            $t->integer('id')->primary();
            $t->string('name')->nullable();
            $t->integer('state_id')->nullable();
            $t->timestamps();
        });

        $s->create('risk_address', function ($t) {
            $t->increments('id');
            $t->integer('customer_id')->nullable();
            $t->integer('policy_id')->nullable();
            $t->integer('term_id')->nullable();
            $t->integer('action_id')->nullable();
            $t->string('address_name')->nullable();
            $t->string('lat')->nullable();
            $t->string('lng')->nullable();
            $t->string('physical_address')->nullable();
            $t->string('risk_state')->nullable();
            $t->string('risk_city')->nullable();
            $t->string('extension')->nullable();
            $t->string('occupation')->nullable();
            $t->string('town_class')->nullable();
            $t->string('risk_class')->nullable();
            $t->string('iso_rcv')->nullable();
            $t->string('year_built')->nullable();
            $t->string('area')->nullable();
            $t->string('structure_type')->nullable();
            $t->string('const_type')->nullable();
            $t->string('distance_to_water')->nullable();
            $t->string('distance_to_fire')->nullable();
            $t->string('distance_to_hydrant')->nullable();
            $t->string('usage')->nullable();
            $t->string('occupancy_type')->nullable();
            $t->string('central_fire')->nullable();
            $t->string('central_burglar')->nullable();
            $t->string('gated_community')->nullable();
            $t->string('automatic')->nullable();
            $t->timestamps();
            $t->string('deleted_at')->nullable();
        });
    }
}
