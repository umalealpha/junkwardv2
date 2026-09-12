<?php

namespace Tests\Feature\Api;

use AlphaDirect\Exports\UnionMembersTemplateExport;
use AlphaDirect\Http\Controllers\Api\V1\UnionSchemeController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Union module — business-rule tests (registration + members + Excel import).
 *
 * SELF-CONTAINED + SAFE: runs entirely on an in-memory SQLite connection built
 * in setUp(), so it never touches MySQL / the live RDS. Drives
 * UnionSchemeController directly (bypassing route auth/permission middleware).
 *
 * Covered: union registration + auto-mint group policy + policy-number override,
 * validation (name/code unique, premium > 0, expiry ≥ effective), member
 * registration + duplicate-ID rejection + active-only guard, delete-with-members
 * guard, premium calc, audit-log write, and Excel import (preview + commit +
 * summary with in-file/cross-union duplicate + validation-error handling).
 */
class UnionModuleTest extends TestCase
{
    private UnionSchemeController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'audit.enabled' => true,
            'audit.drivers.database.connection' => 'sqlite',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        $this->buildSchema();
        $this->controller = new UnionSchemeController();
    }

    private function buildSchema(): void
    {
        Schema::create('unions', function ($t) {
            $t->id();
            $t->string('union_name');
            $t->string('union_code', 50);
            $t->string('description', 1000)->nullable();
            $t->unsignedBigInteger('product_id')->default(4);
            $t->unsignedBigInteger('plan_id')->nullable();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->string('policy_number', 100)->nullable();
            $t->decimal('monthly_premium', 12, 2)->default(0);
            $t->date('effective_date')->nullable();
            $t->date('expiry_date')->nullable();
            $t->string('contact_person')->nullable();
            $t->string('contact_number', 50)->nullable();
            $t->string('email')->nullable();
            $t->string('address', 500)->nullable();
            $t->unsignedTinyInteger('status')->default(1);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('union_members', function ($t) {
            $t->id();
            $t->unsignedBigInteger('union_id');
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('id_number', 50);
            $t->string('member_name');
            $t->string('member_type', 100)->nullable();
            $t->date('date_of_birth')->nullable();
            $t->tinyInteger('gender')->nullable();
            $t->string('contact_number', 50)->nullable();
            $t->string('email')->nullable();
            $t->string('nationality', 100)->nullable();
            $t->unsignedTinyInteger('status')->default(1);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('customer', function ($t) {
            $t->id();
            $t->string('firstName')->nullable();
            $t->string('lastName')->nullable();
            $t->string('email')->nullable();
            $t->string('cellphone')->nullable();
            $t->timestamps();
        });

        Schema::create('customer_profile', function ($t) {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->string('omang')->nullable();
            $t->string('id_type')->nullable();
            $t->date('dob')->nullable();
            $t->integer('gender')->nullable();
            $t->string('nationality')->nullable();
            $t->timestamps();
        });

        Schema::create('policies', function ($t) {
            $t->id();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('plan_id')->nullable();
            $t->string('policyNumber')->nullable();
            $t->tinyInteger('status')->default(0);
            $t->decimal('premium', 12, 2)->default(0);
            $t->decimal('annual_premium', 12, 2)->default(0);
            $t->string('premium_freq')->nullable();
            $t->tinyInteger('has_member')->default(0);
            $t->tinyInteger('has_vehicle')->default(0);
            $t->string('leadSource')->nullable();
            $t->date('term_start_date')->nullable();
            $t->date('term_end_date')->nullable();
            $t->date('expiry_date')->nullable();
            $t->timestamps();
        });

        Schema::create('policy_members', function ($t) {
            $t->id();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('omang')->nullable();
            $t->date('dob')->nullable();
            $t->integer('gender')->nullable();
            $t->string('cellphone')->nullable();
            $t->string('email')->nullable();
            $t->timestamps();
        });

        // Legal Insurance products a union maps to. `premium` is ex-VAT, as in
        // production — P65.79 + 14% = the P75.00 BONU scheme premium, and
        // P42.98 + 14% = P49.00 for BOWASEWU.
        Schema::create('product_plans', function ($t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->string('name');
            $t->decimal('premium', 12, 2)->default(0);
            $t->string('sum_assured')->nullable();
            $t->string('billing')->nullable();
            $t->unsignedTinyInteger('status')->default(1);
            $t->timestamps();
        });

        DB::table('product_plans')->insert([
            ['id' => 5,  'product_id' => 4,  'name' => 'P49_Legal_bronze', 'premium' => 42.98, 'status' => 1],
            ['id' => 6,  'product_id' => 4,  'name' => 'P75_Legal_silver', 'premium' => 65.79, 'status' => 1],
            ['id' => 7,  'product_id' => 4,  'name' => 'P99_Legal_gold',   'premium' => 86.84, 'status' => 1],
            ['id' => 14, 'product_id' => 4,  'name' => 'Retired_Legal',    'premium' => 30.00, 'status' => 0],
            ['id' => 21, 'product_id' => 12, 'name' => 'Health_plan',      'premium' => 65.79, 'status' => 1],
        ]);

        Schema::create('audits', function ($t) {
            $t->id();
            $t->string('user_type')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('event');
            $t->string('auditable_type');
            $t->unsignedBigInteger('auditable_id');
            $t->text('old_values')->nullable();
            $t->text('new_values')->nullable();
            $t->text('url')->nullable();
            $t->string('ip_address')->nullable();
            $t->string('user_agent', 1023)->nullable();
            $t->string('tags')->nullable();
            $t->timestamps();
        });
    }

    private function register(array $overrides = []): array
    {
        // Registers against the Legal product (plan 6 = P75 all-in), which is
        // how the Register Union screen posts now. Tests that need the legacy
        // premium-only path pass 'plan_id' => null with a monthly_premium.
        $payload = array_merge([
            'union_name'      => 'Botswana Nurses Union',
            'union_code'      => 'BONU',
            'plan_id'         => 6,
            'effective_date'  => '2026-01-01',
            'expiry_date'     => '2026-12-31',
        ], $overrides);
        $resp = $this->controller->store(Request::create('/unions', 'POST', $payload));
        return ['status' => $resp->getStatusCode(), 'body' => $resp->getData(true)];
    }

    private function memberPayload(array $o = []): array
    {
        return array_merge([
            'id_number'      => '419217634',
            'member_name'    => 'Kefilwe Moloi',
            'member_type'    => 'Principal',
            'date_of_birth'  => '1990-05-14',
            'gender'         => 'Female',
            'contact_number' => '72345678',
            'nationality'    => 'Motswana',
        ], $o);
    }

    // ─── Union registration ──────────────────────────────────────────────────

    public function test_register_union_auto_mints_group_policy_and_audits(): void
    {
        $r = $this->register();
        $this->assertSame(201, $r['status']);
        $this->assertSame('MISBONU', $r['body']['policy_number']);
        $this->assertDatabaseHas('unions', ['union_code' => 'BONU', 'policy_number' => 'MISBONU', 'monthly_premium' => 75, 'status' => 1]);
        $this->assertDatabaseHas('policies', ['policyNumber' => 'MISBONU', 'product_id' => 4, 'status' => 1, 'premium' => 75]);
        $this->assertDatabaseHas('audits', ['auditable_type' => \AlphaDirect\Models\Union::class, 'event' => 'created']);
    }

    public function test_policy_number_override_is_honored(): void
    {
        $r = $this->register(['union_code' => 'ABC', 'union_name' => 'ABC Union', 'policy_number' => 'CUSTOMPOL1']);
        $this->assertSame(201, $r['status']);
        $this->assertSame('CUSTOMPOL1', $r['body']['policy_number']);
    }

    public function test_premium_must_be_greater_than_zero(): void
    {
        $this->expectException(ValidationException::class);
        $this->register(['plan_id' => null, 'monthly_premium' => 0]);
    }

    // ─── Union → Legal Insurance product mapping ─────────────────────────────

    public function test_registration_maps_the_legal_product_and_derives_the_premium(): void
    {
        $r = $this->register(); // plan 6 = P75_Legal_silver, 65.79 ex-VAT
        $this->assertSame(201, $r['status']);

        // The mapping is persisted on the union AND inherited by the group policy.
        $this->assertDatabaseHas('unions', ['union_code' => 'BONU', 'plan_id' => 6, 'monthly_premium' => 75]);
        $this->assertDatabaseHas('policies', ['policyNumber' => 'MISBONU', 'plan_id' => 6, 'premium' => 75]);

        // …and surfaces on both read paths, so the Edit screen can pre-select it.
        $detail = $this->controller->show($r['body']['id'])->getData(true)['data'];
        $this->assertSame(6, $detail['plan_id']);
        $this->assertSame('P75_Legal_silver', $detail['plan_name']);

        $row = collect($this->controller->index(Request::create('/unions', 'GET'))->getData(true)['data'])
            ->firstWhere('union_code', 'BONU');
        $this->assertSame(6, $row['plan_id']);
        $this->assertSame('P75_Legal_silver', $row['plan_name']);
    }

    public function test_product_price_wins_over_a_posted_premium(): void
    {
        // A caller sending both must not be able to sell P75 cover at P5.
        $this->register(['plan_id' => 5, 'monthly_premium' => 5]);
        $this->assertDatabaseHas('unions', ['union_code' => 'BONU', 'plan_id' => 5, 'monthly_premium' => 49]);
    }

    public function test_product_from_another_product_family_is_rejected(): void
    {
        // Plan 21 is a Health plan (product 12) — not selectable for a union.
        $r = $this->register(['plan_id' => 21]);
        $this->assertSame(422, $r['status']);
        $this->assertStringContainsString('not available', $r['body']['error']);
        $this->assertSame(0, DB::table('unions')->count());
    }

    public function test_inactive_product_is_rejected(): void
    {
        $r = $this->register(['plan_id' => 14]); // status 0
        $this->assertSame(422, $r['status']);
        $this->assertSame(0, DB::table('unions')->count());
    }

    public function test_switching_product_reprices_union_and_group_policy(): void
    {
        $unionId = $this->register()['body']['id']; // plan 6 → 75.00

        $resp = $this->controller->update(
            Request::create("/unions/$unionId", 'PUT', ['plan_id' => 5]), $unionId
        );
        $this->assertSame(200, $resp->getStatusCode());

        $union = DB::table('unions')->where('id', $unionId)->first();
        $this->assertSame(5, (int) $union->plan_id);
        $this->assertEqualsWithDelta(49.0, (float) $union->monthly_premium, 0.001);

        $policy = DB::table('policies')->where('id', $union->policy_id)->first();
        $this->assertSame(5, (int) $policy->plan_id);
        $this->assertEqualsWithDelta(49.0, (float) $policy->premium, 0.001);
        $this->assertEqualsWithDelta(588.0, (float) $policy->annual_premium, 0.001);
    }

    public function test_update_rejects_a_product_outside_legal(): void
    {
        $unionId = $this->register()['body']['id'];

        $resp = $this->controller->update(
            Request::create("/unions/$unionId", 'PUT', ['plan_id' => 21]), $unionId
        );
        $this->assertSame(422, $resp->getStatusCode());

        // Rejected outright — the original mapping and price are untouched.
        $union = DB::table('unions')->where('id', $unionId)->first();
        $this->assertSame(6, (int) $union->plan_id);
        $this->assertEqualsWithDelta(75.0, (float) $union->monthly_premium, 0.001);
    }

    public function test_premium_only_registration_is_still_accepted(): void
    {
        // Back-compat path for API callers with no product to map (see the
        // required_without pair in store()).
        $r = $this->register(['plan_id' => null, 'monthly_premium' => 60]);
        $this->assertSame(201, $r['status']);

        $union = DB::table('unions')->where('union_code', 'BONU')->first();
        $this->assertNull($union->plan_id);
        $this->assertEqualsWithDelta(60.0, (float) $union->monthly_premium, 0.001);
    }

    public function test_registration_requires_a_product_or_a_premium(): void
    {
        $this->expectException(ValidationException::class);
        $this->register(['plan_id' => null]);
    }

    public function test_sync_command_backfills_the_product_mapping(): void
    {
        $this->artisan('unions:sync-legal-schemes --commit')->assertExitCode(0);

        // BONU → P75 plan, BOWASEWU → P49 plan, resolved from the agreed prices.
        $this->assertSame(6, (int) DB::table('unions')->where('union_code', 'BONU')->value('plan_id'));
        $this->assertSame(5, (int) DB::table('unions')->where('union_code', 'BOWASEWU')->value('plan_id'));
    }

    /**
     * End-to-end smoke: the two live schemes, registered the way the Register
     * Union screen does it (product picked, no amount typed), must land on
     * their agreed rates — BONU P75.00 and BOWASEWU P49.00 per member per
     * month — through the union row, the group policy and the dashboard.
     */
    public function test_bonu_and_bowasewu_land_on_their_agreed_rates(): void
    {
        $schemes = [
            ['code' => 'BONU',     'name' => 'Botswana Nurses Union',       'plan' => 6, 'premium' => 75.0, 'members' => 3],
            ['code' => 'BOWASEWU', 'name' => 'Botswana Water Sector Union', 'plan' => 5, 'premium' => 49.0, 'members' => 2],
        ];

        foreach ($schemes as $i => $s) {
            $r = $this->register([
                'union_name' => $s['name'],
                'union_code' => $s['code'],
                'plan_id'    => $s['plan'],
            ]);
            $this->assertSame(201, $r['status'], "{$s['code']} did not register");
            $unionId = $r['body']['id'];

            for ($m = 0; $m < $s['members']; $m++) {
                $resp = $this->controller->storeMember(
                    Request::create("/unions/$unionId/members", 'POST', $this->memberPayload([
                        'id_number'   => "90{$i}00{$m}",
                        'member_name' => "Member {$i}{$m}",
                    ])),
                    $unionId
                );
                $this->assertSame(201, $resp->getStatusCode());
            }

            // 1. Union row: mapped product + its price.
            $union = DB::table('unions')->where('union_code', $s['code'])->first();
            $this->assertSame($s['plan'], (int) $union->plan_id);
            $this->assertEqualsWithDelta($s['premium'], (float) $union->monthly_premium, 0.001,
                "{$s['code']} per-member premium");

            // 2. Group policy: same plan, same premium, ×12 annually.
            $policy = DB::table('policies')->where('id', $union->policy_id)->first();
            $this->assertSame("MIS{$s['code']}", $policy->policyNumber);
            $this->assertSame($s['plan'], (int) $policy->plan_id);
            $this->assertEqualsWithDelta($s['premium'], (float) $policy->premium, 0.001);
            $this->assertEqualsWithDelta($s['premium'] * 12, (float) $policy->annual_premium, 0.001);

            // 3. Dashboard: active members × per-member premium.
            $stats = $this->controller->dashboard($unionId)->getData(true)['data'];
            $this->assertSame($s['members'], $stats['active_members']);
            $this->assertEqualsWithDelta($s['premium'], $stats['monthly_premium'], 0.001);
            $this->assertEqualsWithDelta($s['premium'] * $s['members'], $stats['total_monthly_premium'], 0.001,
                "{$s['code']} total monthly premium");
        }

        // The two schemes stay independent — no shared plan, no shared price.
        $rows = collect($this->controller->index(Request::create('/unions', 'GET'))->getData(true)['data'])
            ->keyBy('union_code');
        $this->assertEqualsWithDelta(75.0, $rows['BONU']['monthly_premium'], 0.001);
        $this->assertEqualsWithDelta(49.0, $rows['BOWASEWU']['monthly_premium'], 0.001);
        $this->assertSame('P75_Legal_silver', $rows['BONU']['plan_name']);
        $this->assertSame('P49_Legal_bronze', $rows['BOWASEWU']['plan_name']);
        $this->assertEqualsWithDelta(225.0, $rows['BONU']['total_monthly_premium'], 0.001);
        $this->assertEqualsWithDelta(98.0,  $rows['BOWASEWU']['total_monthly_premium'], 0.001);
    }

    public function test_duplicate_union_name_rejected(): void
    {
        $this->register();
        $this->expectException(ValidationException::class);
        $this->register(['union_code' => 'BONU2']);
    }

    public function test_duplicate_union_code_rejected(): void
    {
        $this->register();
        $this->expectException(ValidationException::class);
        $this->register(['union_name' => 'Another Union']);
    }

    public function test_expiry_before_effective_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->register(['effective_date' => '2026-12-31', 'expiry_date' => '2026-01-01']);
    }

    // ─── Members (manual) ──────────────────────────────────────────────────────

    public function test_member_registration_creates_customer_and_roster(): void
    {
        $unionId = $this->register()['body']['id'];
        $resp = $this->controller->storeMember(
            Request::create("/unions/$unionId/members", 'POST', $this->memberPayload()), $unionId
        );
        $this->assertSame(201, $resp->getStatusCode());
        $this->assertDatabaseHas('union_members', ['id_number' => '419217634', 'union_id' => $unionId, 'member_type' => 'Principal', 'nationality' => 'Motswana']);
        $this->assertDatabaseHas('customer', ['firstName' => 'Kefilwe', 'lastName' => 'Moloi']);
        $this->assertDatabaseHas('customer_profile', ['omang' => '419217634', 'gender' => 0, 'nationality' => 'Motswana']);
    }

    public function test_duplicate_id_number_rejected_across_unions(): void
    {
        $u1 = $this->register()['body']['id'];
        $u2 = $this->register(['union_code' => 'BOWASEWU', 'union_name' => 'Water Union', 'plan_id' => 5])['body']['id'];

        $mk = fn($uid) => $this->controller->storeMember(
            Request::create("/unions/$uid/members", 'POST', $this->memberPayload(['id_number' => '500000000'])), $uid
        );
        $this->assertSame(201, $mk($u1)->getStatusCode());
        $second = $mk($u2);
        $this->assertSame(422, $second->getStatusCode());
        $this->assertStringContainsString('already exists', $second->getData(true)['error']);
    }

    public function test_inactive_union_rejects_new_members(): void
    {
        $unionId = $this->register(['status' => 0])['body']['id'];
        $resp = $this->controller->storeMember(
            Request::create("/unions/$unionId/members", 'POST', $this->memberPayload(['id_number' => '600000000'])), $unionId
        );
        $this->assertSame(422, $resp->getStatusCode());
        $this->assertStringContainsString('inactive', strtolower($resp->getData(true)['error']));
    }

    public function test_delete_blocked_when_members_exist_then_allowed(): void
    {
        $unionId = $this->register()['body']['id'];
        $this->controller->storeMember(
            Request::create("/unions/$unionId/members", 'POST', $this->memberPayload(['id_number' => '700000000'])), $unionId
        );

        $this->assertSame(422, $this->controller->destroy($unionId)->getStatusCode());

        $memberId = DB::table('union_members')->where('union_id', $unionId)->value('id');
        $this->controller->destroyMember(Request::create('/', 'DELETE'), $unionId, $memberId);
        $ok = $this->controller->destroy($unionId);
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertNotNull(DB::table('unions')->where('id', $unionId)->value('deleted_at'));
    }

    public function test_premium_calculation_active_members_times_premium(): void
    {
        $unionId = $this->register()['body']['id']; // plan 6 → P75.00 / member
        foreach ([['801', 1], ['802', 1], ['803', 0]] as [$omang, $status]) {
            $this->controller->storeMember(
                Request::create("/unions/$unionId/members", 'POST', $this->memberPayload(['id_number' => $omang, 'status' => $status])),
                $unionId
            );
        }
        $stats = $this->controller->dashboard($unionId)->getData(true)['data'];
        $this->assertSame(3, $stats['total_members']);
        $this->assertSame(2, $stats['active_members']);
        $this->assertEqualsWithDelta(150.0, $stats['total_monthly_premium'], 0.001);
    }

    // ─── Excel import ──────────────────────────────────────────────────────────

    public function test_excel_import_preview_then_commit(): void
    {
        $unionId = $this->register()['body']['id'];

        $csv = "ID Number,Name,Type,Date Of Birth,Gender,Contact No,Email Address,Nationality\n"
             . "111,John Doe,Principal,1990-01-01,Male,72000000,john@x.bw,Motswana\n"   // valid
             . "111,John Dup,Principal,1990-01-01,Male,72000001,,Motswana\n"            // in-file duplicate
             . ",No Id,Principal,1990-01-01,Female,72000002,,Motswana\n";               // missing ID → error

        $path = sys_get_temp_dir() . '/union_import_test.csv';
        file_put_contents($path, $csv);

        // Preview (commit not set → dry run).
        $file = new UploadedFile($path, 'members.csv', 'text/csv', null, true);
        $preview = $this->controller->importMembers(
            Request::create("/unions/$unionId/members/import", 'POST', [], [], ['file' => $file]), $unionId
        )->getData(true);

        $this->assertFalse($preview['committed']);
        $this->assertSame(3, $preview['summary']['total']);
        $this->assertSame(1, $preview['summary']['valid']);
        $this->assertSame(1, $preview['summary']['duplicates']);
        $this->assertSame(1, $preview['summary']['failed']);
        $this->assertSame(0, $preview['summary']['imported']);
        $this->assertDatabaseMissing('union_members', ['id_number' => '111']); // nothing persisted on preview

        // Commit.
        $file2 = new UploadedFile($path, 'members.csv', 'text/csv', null, true);
        $commit = $this->controller->importMembers(
            Request::create("/unions/$unionId/members/import", 'POST', ['commit' => '1'], [], ['file' => $file2]), $unionId
        )->getData(true);

        $this->assertTrue($commit['committed']);
        $this->assertSame(1, $commit['summary']['imported']);
        $this->assertDatabaseHas('union_members', ['id_number' => '111', 'union_id' => $unionId, 'member_name' => 'John Doe']);
        $this->assertDatabaseHas('customer_profile', ['omang' => '111', 'nationality' => 'Motswana']);

        @unlink($path);
    }

    public function test_import_accepts_rows_with_missing_member_details(): void
    {
        $unionId = $this->register()['body']['id'];

        // Only the ID Number is enforced. Everything else imports as supplied.
        $csv = "ID Number,Name,Type,Date Of Birth,Gender,Contact No,Email Address,Nationality\n"
             . "201,Naledi Phiri,,,,,,\n"                                   // name + ID only
             . "202,,Spouse,not-a-date,X,N/A,rubbish-email,\n"              // junk in every optional column
             . "203,Tebogo Kgosi,Principal,1988-03-02,Male,72000003,t@x.bw,Motswana\n" // complete
             . ",Nameless,Principal,1990-01-01,Female,72000004,,Motswana\n"; // no ID → still rejected

        $path = sys_get_temp_dir() . '/union_import_relaxed.csv';
        file_put_contents($path, $csv);

        $file = new UploadedFile($path, 'members.csv', 'text/csv', null, true);
        $result = $this->controller->importMembers(
            Request::create("/unions/$unionId/members/import", 'POST', ['commit' => '1'], [], ['file' => $file]), $unionId
        )->getData(true);

        $this->assertSame(4, $result['summary']['total']);
        $this->assertSame(3, $result['summary']['imported'], 'sparse rows must import');
        $this->assertSame(1, $result['summary']['failed'], 'only the row with no ID Number fails');

        // Sparse row landed, with the empty columns stored as null.
        $sparse = DB::table('union_members')->where('id_number', '201')->first();
        $this->assertSame('Naledi Phiri', $sparse->member_name);
        $this->assertNull($sparse->member_type);
        $this->assertNull($sparse->date_of_birth);
        $this->assertNull($sparse->gender);
        $this->assertNull($sparse->nationality);
        $this->assertSame(1, (int) $sparse->status);

        // Unreadable values are nulled, not rejected — and the bad email is
        // dropped rather than written to the member or the customer record.
        $junk = DB::table('union_members')->where('id_number', '202')->first();
        $this->assertNull($junk->date_of_birth);
        $this->assertNull($junk->gender);
        $this->assertNull($junk->email);
        $this->assertSame('N/A', $junk->contact_number);
        $this->assertSame('', $junk->member_name);

        // …but the operator is told, on an otherwise-valid row.
        $row202 = collect($result['preview'])->firstWhere('id_number', '202');
        $this->assertSame('valid', $row202['status']);
        $this->assertContains('Email ignored (not a valid address)', $row202['messages']);

        // The row with no ID Number is the only rejection.
        $noId = collect($result['preview'])->firstWhere('name', 'Nameless');
        $this->assertSame('error', $noId['status']);
        $this->assertContains('ID Number is required', $noId['messages']);

        @unlink($path);
    }

    /**
     * A large import used to time out because every row re-read the schema:
     * persistMember() called getColumnListing / hasTable five times per member,
     * so the query count grew with the roster instead of staying flat. This
     * pins the fix — the per-row cost must be inserts only.
     */
    public function test_large_import_does_not_re_read_the_schema_per_row(): void
    {
        $unionId = $this->register()['body']['id'];

        $rowCount = 150;
        $csv = "ID Number,Name,Type,Date Of Birth,Gender,Contact No,Email Address,Nationality\n";
        for ($i = 0; $i < $rowCount; $i++) {
            $csv .= sprintf("4%06d,Member %d,Principal,1990-01-01,Male,7200%04d,,Motswana\n", $i, $i, $i);
        }

        $path = sys_get_temp_dir() . '/union_import_large.csv';
        file_put_contents($path, $csv);

        $queries = 0;
        $schemaQueries = 0;
        $schemaSql = [];
        DB::listen(function ($q) use (&$queries, &$schemaQueries, &$schemaSql) {
            $queries++;
            if (preg_match('/pragma|sqlite_master|information_schema|show (columns|tables)/i', $q->sql)) {
                $schemaSql[$q->sql] = ($schemaSql[$q->sql] ?? 0) + 1;
            }
            // sqlite reports introspection as pragma / sqlite_master reads;
            // MySQL as "show columns" / information_schema.
            if (preg_match('/pragma|sqlite_master|information_schema|show (columns|tables)/i', $q->sql)) {
                $schemaQueries++;
            }
        });

        $file = new UploadedFile($path, 'members.csv', 'text/csv', null, true);
        $result = $this->controller->importMembers(
            Request::create("/unions/$unionId/members/import", 'POST', ['commit' => '1'], [], ['file' => $file]), $unionId
        )->getData(true);

        $this->assertSame($rowCount, $result['summary']['imported'], 'every row should import');

        // Schema lookups are memoised per request: a handful of tables, not
        // five per member. 150 rows once cost ~750 introspection round trips.
        $this->assertLessThan(20, $schemaQueries,
            "schema introspection ran $schemaQueries times for $rowCount rows — the per-row memo has regressed\n"
            . json_encode($schemaSql, JSON_PRETTY_PRINT));

        // Overall: a bounded number of statements per member (customer,
        // profile, member, audit, policy link), not a multiple that grows.
        $this->assertLessThan($rowCount * 8, $queries,
            "$queries queries for $rowCount rows is more than the per-member insert budget");

        @unlink($path);
    }

    public function test_import_still_blocks_duplicate_ids(): void
    {
        $unionId = $this->register()['body']['id'];

        $csv = "ID Number,Name,Type,Date Of Birth,Gender,Contact No,Email Address,Nationality\n"
             . "301,First Entry,,,,,,\n"
             . "301,Same Id Again,,,,,,\n";

        $path = sys_get_temp_dir() . '/union_import_dupe.csv';
        file_put_contents($path, $csv);

        $file = new UploadedFile($path, 'members.csv', 'text/csv', null, true);
        $result = $this->controller->importMembers(
            Request::create("/unions/$unionId/members/import", 'POST', ['commit' => '1'], [], ['file' => $file]), $unionId
        )->getData(true);

        // Relaxing the field rules must not let a member be billed twice.
        $this->assertSame(1, $result['summary']['imported']);
        $this->assertSame(1, $result['summary']['duplicates']);
        $this->assertSame(1, DB::table('union_members')->where('id_number', '301')->count());

        @unlink($path);
    }

    // ─── unions:sync-legal-schemes command ─────────────────────────────────────

    public function test_sync_legal_schemes_dry_run_writes_nothing(): void
    {
        $this->artisan('unions:sync-legal-schemes')->assertExitCode(0);

        $this->assertSame(0, DB::table('unions')->whereIn('union_code', ['BONU', 'BOWASEWU'])->count());
    }

    public function test_sync_legal_schemes_commit_registers_both_schemes(): void
    {
        $this->artisan('unions:sync-legal-schemes --commit')->assertExitCode(0);

        foreach ([['BONU', 'MISBONU', 75.0], ['BOWASEWU', 'MISBOWASEWU', 49.0]] as [$code, $policyNo, $premium]) {
            $union = DB::table('unions')->where('union_code', $code)->first();
            $this->assertNotNull($union, "$code was not registered");
            $this->assertEqualsWithDelta($premium, (float) $union->monthly_premium, 0.001);
            $this->assertSame($policyNo, $union->policy_number);
            $this->assertSame('Legal Insurance Group Scheme', $union->description);
            $this->assertSame(4, (int) $union->product_id);

            // The group policy must exist and carry the same premium.
            $policy = DB::table('policies')->where('policyNumber', $policyNo)->first();
            $this->assertNotNull($policy, "$policyNo policy was not minted");
            $this->assertEqualsWithDelta($premium, (float) $policy->premium, 0.001);
        }
    }

    public function test_sync_legal_schemes_is_idempotent(): void
    {
        $this->artisan('unions:sync-legal-schemes --commit')->assertExitCode(0);
        $this->artisan('unions:sync-legal-schemes --commit')->assertExitCode(0);

        $this->assertSame(1, DB::table('unions')->where('union_code', 'BONU')->count());
        $this->assertSame(1, DB::table('policies')->where('policyNumber', 'MISBONU')->count());
    }

    public function test_sync_legal_schemes_corrects_a_wrong_premium_and_syncs_the_policy(): void
    {
        // Registered at the wrong rate with no product mapping — how a union
        // registered before the Legal-product dropdown looks.
        $this->register(['union_name' => 'BONU', 'union_code' => 'BONU', 'plan_id' => null, 'monthly_premium' => 10]);

        $this->artisan('unions:sync-legal-schemes --commit --code=BONU')->assertExitCode(0);

        $union = DB::table('unions')->where('union_code', 'BONU')->first();
        $this->assertEqualsWithDelta(75.0, (float) $union->monthly_premium, 0.001);
        $this->assertSame('Legal Insurance Group Scheme', $union->description);

        $policy = DB::table('policies')->where('id', $union->policy_id)->first();
        $this->assertEqualsWithDelta(75.0, (float) $policy->premium, 0.001);
        $this->assertEqualsWithDelta(900.0, (float) $policy->annual_premium, 0.001);

        // BOWASEWU was excluded by --code, so it must still be absent.
        $this->assertSame(0, DB::table('unions')->where('union_code', 'BOWASEWU')->count());
    }

    /**
     * End-to-end on a REAL .xlsx (every other import test uses CSV, but the
     * users upload spreadsheets): generate the very template the Members page
     * hands out, feed it straight back into the importer, and commit it.
     */
    public function test_import_round_trips_the_real_xlsx_template(): void
    {
        $unionId = $this->register()['body']['id'];

        $path = sys_get_temp_dir() . '/union_members_template_smoke.xlsx';
        file_put_contents($path, Excel::raw(new UnionMembersTemplateExport(), ExcelFormat::XLSX));
        $this->assertGreaterThan(0, filesize($path), 'template xlsx is empty');

        $file    = new UploadedFile($path, 'union_members_template.xlsx', null, null, true);
        $preview = $this->controller->importMembers(
            Request::create("/unions/$unionId/members/import", 'POST', [], [], ['file' => $file]), $unionId
        );
        $this->assertSame(200, $preview->getStatusCode(), 'xlsx was rejected: '
            . json_encode($preview->getData(true)));

        $body = $preview->getData(true);
        $this->assertSame(1, $body['summary']['total'], 'the sample row did not parse');
        $this->assertSame(1, $body['summary']['valid'], json_encode($body['preview']));
        $this->assertSame('valid', $body['preview'][0]['status']);
        $this->assertSame('419217634', $body['preview'][0]['id_number']);

        // Commit it for real.
        $file2  = new UploadedFile($path, 'union_members_template.xlsx', null, null, true);
        $commit = $this->controller->importMembers(
            Request::create("/unions/$unionId/members/import", 'POST', ['commit' => '1'], [], ['file' => $file2]), $unionId
        )->getData(true);

        $this->assertSame(1, $commit['summary']['imported']);
        $this->assertDatabaseHas('union_members', [
            'id_number'   => '419217634',
            'union_id'    => $unionId,
            'member_name' => 'Kefilwe Moloi',
        ]);
        @unlink($path);
    }

    public function test_import_rejects_file_that_is_not_the_template(): void
    {
        $unionId = $this->register()['body']['id'];
        $path = sys_get_temp_dir() . '/union_import_wrong_headings.csv';
        file_put_contents($path, "Surname,Omang,Cell\nMoloi,111,72000000\n");

        $file = new UploadedFile($path, 'legal list.csv', 'text/csv', null, true);
        $resp = $this->controller->importMembers(
            Request::create("/unions/$unionId/members/import", 'POST', [], [], ['file' => $file]), $unionId
        );

        $this->assertSame(422, $resp->getStatusCode());
        $this->assertStringContainsString('does not match the member import template', $resp->getData(true)['error']);
        $this->assertStringContainsString('ID Number', $resp->getData(true)['error']);
        @unlink($path);
    }

    public function test_import_rejects_sheet_with_no_data_rows(): void
    {
        $unionId = $this->register()['body']['id'];
        $path = sys_get_temp_dir() . '/union_import_headings_only.csv';
        file_put_contents($path, "ID Number,Name,Type,Date Of Birth,Gender,Contact No,Email Address,Nationality\n");

        $file = new UploadedFile($path, 'members.csv', 'text/csv', null, true);
        $resp = $this->controller->importMembers(
            Request::create("/unions/$unionId/members/import", 'POST', [], [], ['file' => $file]), $unionId
        );

        $this->assertSame(422, $resp->getStatusCode());
        $this->assertStringContainsString('no data rows', $resp->getData(true)['error']);
        @unlink($path);
    }

    /** Email is optional, so a file without that column must still import. */
    public function test_import_accepts_file_without_optional_email_column(): void
    {
        $unionId = $this->register()['body']['id'];
        $path = sys_get_temp_dir() . '/union_import_no_email.csv';
        file_put_contents($path, "ID Number,Name,Type,Date Of Birth,Gender,Contact No,Nationality\n"
            . "222,Jane Doe,Principal,1991-02-02,Female,72000003,Motswana\n");

        $file = new UploadedFile($path, 'members.csv', 'text/csv', null, true);
        $resp = $this->controller->importMembers(
            Request::create("/unions/$unionId/members/import", 'POST', ['commit' => '1'], [], ['file' => $file]), $unionId
        )->getData(true);

        $this->assertSame(1, $resp['summary']['imported']);
        $this->assertDatabaseHas('union_members', ['id_number' => '222', 'union_id' => $unionId]);
        @unlink($path);
    }

    public function test_import_blocked_for_inactive_union(): void
    {
        $unionId = $this->register(['status' => 0])['body']['id'];
        $path = sys_get_temp_dir() . '/union_import_inactive.csv';
        file_put_contents($path, "ID Number,Name,Type,Date Of Birth,Gender,Contact No,Email Address,Nationality\n999,X Y,Principal,1990-01-01,Male,72000000,,Motswana\n");
        $file = new UploadedFile($path, 'members.csv', 'text/csv', null, true);
        $resp = $this->controller->importMembers(
            Request::create("/unions/$unionId/members/import", 'POST', ['commit' => '1'], [], ['file' => $file]), $unionId
        );
        $this->assertSame(422, $resp->getStatusCode());
        @unlink($path);
    }
}

