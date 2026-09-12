<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the remaining columns the member insert paths write to `policy_members`.
 *
 * PolicyCreateController::addMember/updateMember (and the Accidental Death /
 * Hospital Cashback member inserts) persist omang, passport, payment, risk_id,
 * term_id and action_id, but `policy_members` is a legacy table that predates
 * migrations in this repo and never had these columns. Adding a member on the
 * Policy view page failed with:
 *
 *   SQLSTATE[42S22]: Unknown column 'omang' in 'INSERT INTO'
 *
 * (and would fail on the next missing column after that). Types mirror the
 * controller validation: omang/passport string(25), payment numeric 0-100
 * (decimal(5,2)), the *_id columns are nullable foreign keys.
 *
 * Additive + nullable + idempotent (hasColumn guards) — safe on the shared DB.
 */
class AddMemberDetailColumnsToPolicyMembersTable extends Migration
{
    public function up(): void
    {
        Schema::table('policy_members', function (Blueprint $table) {
            if (! Schema::hasColumn('policy_members', 'omang')) {
                $table->string('omang', 25)->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('policy_members', 'passport')) {
                $table->string('passport', 25)->nullable()->after('omang');
            }
            if (! Schema::hasColumn('policy_members', 'payment')) {
                $table->decimal('payment', 5, 2)->nullable()->after('passport');
            }
            if (! Schema::hasColumn('policy_members', 'risk_id')) {
                $table->unsignedBigInteger('risk_id')->nullable()->after('payment');
            }
            if (! Schema::hasColumn('policy_members', 'term_id')) {
                $table->unsignedBigInteger('term_id')->nullable()->after('risk_id');
            }
            if (! Schema::hasColumn('policy_members', 'action_id')) {
                $table->unsignedBigInteger('action_id')->nullable()->after('term_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('policy_members', function (Blueprint $table) {
            foreach (['action_id', 'term_id', 'risk_id', 'payment', 'passport', 'omang'] as $col) {
                if (Schema::hasColumn('policy_members', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
