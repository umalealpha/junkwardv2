<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `middle_name` to the `policy_members` table.
 *
 * PolicyCreateController::addMember/updateMember (and the related member
 * insert paths) validate and persist `middle_name`, but `policy_members`
 * is a legacy table that predates Laravel migrations in this repo and never
 * had the column. Adding a member on the Policy view page therefore failed
 * with:
 *
 *   SQLSTATE[42S22]: Unknown column 'middle_name' in 'INSERT INTO'
 *
 * Additive + nullable, mirroring first_name/last_name (varchar 100), placed
 * right after first_name to match the insert column order.
 */
class AddMiddleNameToPolicyMembersTable extends Migration
{
    public function up(): void
    {
        Schema::table('policy_members', function (Blueprint $table) {
            if (! Schema::hasColumn('policy_members', 'middle_name')) {
                $table->string('middle_name', 100)->nullable()->after('first_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('policy_members', function (Blueprint $table) {
            if (Schema::hasColumn('policy_members', 'middle_name')) {
                $table->dropColumn('middle_name');
            }
        });
    }
}
