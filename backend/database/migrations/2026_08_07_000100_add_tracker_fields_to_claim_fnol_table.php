<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Claims-Tracker "New Claim" Basic-Information fields to the claim_fnol
 * intake table so the unified tracker-style FNOL create form (gated behind the
 * `claims_fnol` runtime flag) can capture them 1:1 with the legacy tracker.
 *
 * Fully ADDITIVE + REVERSIBLE:
 *   - every column is nullable (or has a safe default) — no backfill, no data
 *     migration, existing rows are untouched;
 *   - each add is guarded by Schema::hasColumn so a re-run (or a hand-built V2
 *     environment) is idempotent — the repo standard;
 *   - down() drops only the columns this migration added (also guarded), so a
 *     rollback restores the table to its prior shape with no side effects.
 *
 * Nothing reads or writes these columns until the `claims_fnol` flag is ON;
 * while the flag is OFF the whole FNOL surface is inert (controller 404s), so
 * this migration changes no live behaviour on its own.
 *
 * Column notes:
 *   - broker_name / claims_handler collapse the tracker's "Others (Specify
 *     Below)" typed value into the single column on save (no separate _other
 *     column for these two).
 *   - customer_type / assessor / glass_supplier keep BOTH the select value and
 *     a *_other free-text column (mirrors the tracker's paired fields).
 *   - is_fac mirrors the tracker's auto FAC (facultative) flag.
 *   - comment_status / comment_sub_reason persist the tracker's "Comments"
 *     block (comment status + the "Awaiting — what?" sub-reason).
 */
return new class extends Migration
{
    /** Column name => Blueprint closure. Applied only if the column is absent. */
    private function columns(): array
    {
        return [
            'channel'              => fn (Blueprint $t) => $t->string('channel', 20)->nullable()->after('description'),
            'broker_name'          => fn (Blueprint $t) => $t->string('broker_name', 255)->nullable()->after('channel'),
            'claims_handler'       => fn (Blueprint $t) => $t->string('claims_handler', 255)->nullable()->after('broker_name'),
            'plate_number'         => fn (Blueprint $t) => $t->string('plate_number', 50)->nullable()->after('claims_handler'),
            'reserve_amount'       => fn (Blueprint $t) => $t->decimal('reserve_amount', 15, 2)->nullable()->after('plate_number'),
            'claim_paid_amount'    => fn (Blueprint $t) => $t->decimal('claim_paid_amount', 15, 2)->nullable()->after('reserve_amount'),
            'customer_type'        => fn (Blueprint $t) => $t->string('customer_type', 50)->nullable()->after('claim_paid_amount'),
            'customer_type_other'  => fn (Blueprint $t) => $t->string('customer_type_other', 255)->nullable()->after('customer_type'),
            'non_motor_sub_type'   => fn (Blueprint $t) => $t->string('non_motor_sub_type', 120)->nullable()->after('customer_type_other'),
            'assessor'             => fn (Blueprint $t) => $t->string('assessor', 255)->nullable()->after('non_motor_sub_type'),
            'assessor_other'       => fn (Blueprint $t) => $t->string('assessor_other', 255)->nullable()->after('assessor'),
            'glass_supplier'       => fn (Blueprint $t) => $t->string('glass_supplier', 255)->nullable()->after('assessor_other'),
            'glass_supplier_other' => fn (Blueprint $t) => $t->string('glass_supplier_other', 255)->nullable()->after('glass_supplier'),
            'reinsurer'            => fn (Blueprint $t) => $t->string('reinsurer', 255)->nullable()->after('glass_supplier_other'),
            'is_fac'               => fn (Blueprint $t) => $t->boolean('is_fac')->default(false)->after('reinsurer'),
            'comment_status'       => fn (Blueprint $t) => $t->string('comment_status', 120)->nullable()->after('is_fac'),
            'comment_sub_reason'   => fn (Blueprint $t) => $t->string('comment_sub_reason', 120)->nullable()->after('comment_status'),
        ];
    }

    public function up(): void
    {
        if (!Schema::hasTable('claim_fnol')) {
            return; // base table not present yet — its own migration will create it first
        }

        foreach ($this->columns() as $name => $add) {
            if (!Schema::hasColumn('claim_fnol', $name)) {
                Schema::table('claim_fnol', function (Blueprint $table) use ($add) {
                    $add($table);
                });
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('claim_fnol')) {
            return;
        }

        foreach (array_keys($this->columns()) as $name) {
            if (Schema::hasColumn('claim_fnol', $name)) {
                Schema::table('claim_fnol', function (Blueprint $table) use ($name) {
                    $table->dropColumn($name);
                });
            }
        }
    }
};
