<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression guard for the union_members schema drift.
 *
 * 2026_07_25_000011_create_union_members_table.php was rewritten in place after
 * it had already run on some environments. Laravel keys migrations by filename
 * and runs each exactly once, so those databases kept the ORIGINAL columns
 * (first_name / omang_passport / mobile_number …) and the member import died on
 * "Unknown column 'id_number'". UnionModuleTest never caught it because it
 * builds the CURRENT schema by hand.
 *
 * This test builds the ORIGINAL table, runs the repair migration over it, and
 * asserts the result can carry the current code.
 *
 * SAFE: in-memory SQLite, same as UnionModuleTest — never touches MySQL/RDS.
 * The MySQL-only steps (CONCAT_WS name backfill, NOT NULL relax via ->change(),
 * SHOW INDEX / unique index) self-skip on SQLite and are called out below.
 */
class UnionMembersSchemaRepairTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');
    }

    /** The union_members table exactly as the FIRST version of the migration built it. */
    private function buildLegacyTable(): void
    {
        Schema::create('union_members', function ($t) {
            $t->id();
            $t->unsignedBigInteger('union_id')->index();
            $t->unsignedBigInteger('policy_id')->nullable()->index();
            $t->unsignedBigInteger('customer_id')->nullable()->index();

            $t->string('employee_number', 100)->nullable();
            $t->string('first_name', 100);
            $t->string('last_name', 100);
            $t->date('date_of_birth')->nullable();
            $t->tinyInteger('gender')->nullable();
            $t->string('omang_passport', 50)->nullable();
            $t->string('mobile_number', 50)->nullable();
            $t->string('email')->nullable();
            $t->string('physical_address', 500)->nullable();
            $t->string('employment_status', 100)->nullable();
            $t->date('joining_date')->nullable();
            $t->unsignedTinyInteger('status')->default(1)->index();

            $t->timestamps();
            $t->softDeletes();
        });
    }

    private function runRepair(): void
    {
        (require __DIR__ . '/../../../database/migrations/2026_07_29_000010_repair_union_members_member_columns.php')->up();
    }

    public function test_repair_adds_every_column_the_current_code_needs(): void
    {
        $this->buildLegacyTable();

        // Sanity: the legacy table really is missing the new columns.
        $this->assertFalse(Schema::hasColumn('union_members', 'id_number'));
        $this->assertFalse(Schema::hasColumn('union_members', 'member_name'));

        $this->runRepair();

        foreach ([
            'union_id', 'policy_id', 'customer_id', 'id_number', 'member_name', 'member_type',
            'date_of_birth', 'gender', 'contact_number', 'email', 'nationality', 'status',
            'created_by', 'updated_by',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('union_members', $column),
                "repair did not add '$column'"
            );
        }
    }

    public function test_repair_reproduces_the_reported_query(): void
    {
        $this->buildLegacyTable();

        // The exact SELECT from UnionSchemeController::importMembers that blew up:
        //   SQLSTATE[42S22] Unknown column 'id_number' in 'SELECT'
        try {
            DB::table('union_members')->whereNull('deleted_at')->pluck('union_id', 'id_number');
            $this->fail('expected the legacy schema to reject the id_number select');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('id_number', $e->getMessage());
        }

        $this->runRepair();

        // Same query, now fine.
        $this->assertCount(0, DB::table('union_members')->whereNull('deleted_at')->pluck('union_id', 'id_number'));
    }

    public function test_repair_carries_existing_member_data_across(): void
    {
        $this->buildLegacyTable();

        DB::table('union_members')->insert([
            'union_id'       => 1,
            'first_name'     => 'Kefilwe',
            'last_name'      => 'Moloi',
            'omang_passport' => '419217634',
            'mobile_number'  => '72345678',
            'email'          => 'k.moloi@example.bw',
            'status'         => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->runRepair();

        $row = DB::table('union_members')->first();
        $this->assertSame('419217634', $row->id_number, 'omang_passport did not carry to id_number');
        $this->assertSame('72345678', $row->contact_number, 'mobile_number did not carry to contact_number');
        $this->assertSame('k.moloi@example.bw', $row->email);

        // member_name is rebuilt with CONCAT_WS — MySQL only, skipped on SQLite.
        // The legacy first_name/last_name are still there for it to run against.
        $this->assertSame('Kefilwe', $row->first_name);
        $this->assertSame('Moloi', $row->last_name);
    }

    public function test_repair_is_idempotent_and_does_not_clobber_edits(): void
    {
        $this->buildLegacyTable();
        DB::table('union_members')->insert([
            'union_id' => 1, 'first_name' => 'A', 'last_name' => 'B',
            'omang_passport' => '111', 'mobile_number' => '72000000',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->runRepair();
        DB::table('union_members')->update(['id_number' => 'EDITED-BY-HAND']);
        $this->runRepair();   // second pass must be a no-op
        $this->runRepair();   // and a third

        $this->assertSame(1, DB::table('union_members')->count());
        $this->assertSame('EDITED-BY-HAND', DB::table('union_members')->value('id_number'));
    }

    public function test_repair_is_a_no_op_on_a_current_schema(): void
    {
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
        DB::table('union_members')->insert([
            'union_id' => 1, 'id_number' => '111', 'member_name' => 'John Doe',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->runRepair();

        $row = DB::table('union_members')->first();
        $this->assertSame('111', $row->id_number);
        $this->assertSame('John Doe', $row->member_name);
        $this->assertFalse(Schema::hasColumn('union_members', 'first_name'), 'repair invented legacy columns');
    }

    public function test_repair_skips_cleanly_when_the_table_is_absent(): void
    {
        $this->assertFalse(Schema::hasTable('union_members'));

        $this->runRepair();   // fresh install — the create migration owns this

        $this->assertFalse(Schema::hasTable('union_members'), 'repair should not create the table');
    }
}
