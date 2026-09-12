<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Repair union_members on environments that ran the FIRST version of
 * 2026_07_25_000011_create_union_members_table.php.
 *
 * That migration was later rewritten in place (commit 440e3a4be) from the
 * original spec to the Members-module spec:
 *
 *   employee_number / first_name / last_name / omang_passport /
 *   mobile_number / physical_address / employment_status / joining_date
 *      ↓
 *   id_number / member_name / member_type / contact_number / nationality
 *
 * Laravel runs a migration file exactly once — keyed by filename in the
 * `migrations` table — so any database that had already run the original never
 * executes the rewritten body, including its "top up missing columns" branch.
 * Those databases still carry the ORIGINAL columns, which is why the member
 * import dies with:
 *
 *   SQLSTATE[42S22]: Unknown column 'id_number' in 'SELECT'
 *
 * Fresh databases created after the rewrite are already correct and skip
 * everything here (every step is guarded by hasColumn).
 *
 * Legacy columns are kept, not dropped — they still hold the only copy of
 * employee_number / physical_address / employment_status / joining_date. They
 * are only relaxed to NULL-able so new inserts (which write the new column set)
 * can succeed.
 */
return new class extends Migration {
    private const INDEX = 'union_members_union_id_number_unique';

    public function up(): void
    {
        // Fresh install: the create migration already built the correct table.
        if (!Schema::hasTable('union_members')) return;

        $this->addMissingColumns();
        $this->backfillFromLegacyColumns();
        $this->relaxLegacyNotNullColumns();
        $this->addUniqueIndex();
    }

    /** Columns the current code reads/writes but the original table lacks. */
    private function addMissingColumns(): void
    {
        // The full current spec, not just the seven the original table lacked —
        // guarded per column, so this also repairs any other partial variant.
        $columns = [
            'union_id'       => fn(Blueprint $t) => $t->unsignedBigInteger('union_id')->nullable(),
            'policy_id'      => fn(Blueprint $t) => $t->unsignedBigInteger('policy_id')->nullable(),
            'customer_id'    => fn(Blueprint $t) => $t->unsignedBigInteger('customer_id')->nullable(),
            'id_number'      => fn(Blueprint $t) => $t->string('id_number', 50)->nullable(),
            'member_name'    => fn(Blueprint $t) => $t->string('member_name')->nullable(),
            'member_type'    => fn(Blueprint $t) => $t->string('member_type', 100)->nullable(),
            'date_of_birth'  => fn(Blueprint $t) => $t->date('date_of_birth')->nullable(),
            'gender'         => fn(Blueprint $t) => $t->tinyInteger('gender')->nullable(),
            'contact_number' => fn(Blueprint $t) => $t->string('contact_number', 50)->nullable(),
            'email'          => fn(Blueprint $t) => $t->string('email')->nullable(),
            'nationality'    => fn(Blueprint $t) => $t->string('nationality', 100)->nullable(),
            'status'         => fn(Blueprint $t) => $t->unsignedTinyInteger('status')->default(1),
            'created_by'     => fn(Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable(),
            'updated_by'     => fn(Blueprint $t) => $t->unsignedBigInteger('updated_by')->nullable(),
        ];

        $missing = array_filter(
            $columns,
            fn($col) => !Schema::hasColumn('union_members', $col),
            ARRAY_FILTER_USE_KEY
        );
        if (!$missing) return;

        Schema::table('union_members', function (Blueprint $table) use ($missing) {
            foreach ($missing as $add) $add($table);
        });
    }

    /**
     * Carry existing rows over to the new columns. Only touches rows whose new
     * column is still NULL, so re-running never overwrites edited data.
     */
    private function backfillFromLegacyColumns(): void
    {
        if (Schema::hasColumn('union_members', 'omang_passport')) {
            DB::table('union_members')
                ->whereNull('id_number')->whereNotNull('omang_passport')
                ->update(['id_number' => DB::raw('omang_passport')]);
        }

        if (Schema::hasColumn('union_members', 'mobile_number')) {
            DB::table('union_members')
                ->whereNull('contact_number')->whereNotNull('mobile_number')
                ->update(['contact_number' => DB::raw('mobile_number')]);
        }

        // member_name = "first last". CONCAT_WS is MySQL-only, and so is this
        // whole branch in practice — first_name only exists on legacy MySQL.
        if (Schema::hasColumn('union_members', 'first_name') && DB::getDriverName() === 'mysql') {
            DB::table('union_members')
                ->whereNull('member_name')
                ->update([
                    'member_name' => DB::raw("NULLIF(TRIM(CONCAT_WS(' ', first_name, last_name)), '')"),
                ]);
        }
    }

    /**
     * first_name / last_name were NOT NULL in the original table, but the
     * current insert path (UnionMember::create) does not write them — so every
     * new member would fail with "Field 'first_name' doesn't have a default
     * value" even once the new columns exist.
     */
    private function relaxLegacyNotNullColumns(): void
    {
        foreach (['first_name' => 100, 'last_name' => 100] as $column => $length) {
            if (!Schema::hasColumn('union_members', $column)) continue;

            Schema::table('union_members', function (Blueprint $table) use ($column, $length) {
                $table->string($column, $length)->nullable()->change();
            });
        }
    }

    /** UNIQUE (union_id, id_number) — the duplicate-ID guard for the importer. */
    private function addUniqueIndex(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        if (!Schema::hasColumn('union_members', 'id_number')) return;

        $exists = DB::select('SHOW INDEX FROM union_members WHERE Key_name = ?', [self::INDEX]);
        if ($exists) return;

        // A duplicate would abort the whole migration. Report and skip instead —
        // the app-side duplicate check still runs on every import.
        $duplicates = DB::select(
            'SELECT union_id, id_number, COUNT(*) AS n FROM union_members
              WHERE id_number IS NOT NULL AND deleted_at IS NULL
              GROUP BY union_id, id_number HAVING n > 1'
        );
        if ($duplicates) {
            Log::warning('union_members: skipped unique index, duplicate ids present', [
                'duplicates' => array_map(fn($d) => "{$d->union_id}/{$d->id_number} x{$d->n}", $duplicates),
            ]);
            return;
        }

        Schema::table('union_members', function (Blueprint $table) {
            $table->unique(['union_id', 'id_number'], self::INDEX);
        });
    }

    public function down(): void
    {
        // Intentionally irreversible: dropping these would destroy the migrated
        // member data, and the legacy columns it came from may since have been
        // removed. Roll forward instead.
    }
};
