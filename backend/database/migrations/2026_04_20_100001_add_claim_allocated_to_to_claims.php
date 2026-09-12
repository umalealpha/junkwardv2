<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claims Create — persist "claim allocated to" on the primary claims
 * table.
 *
 * Legacy keeps claim_allocated_to on new_claims (a parallel table with
 * its own id sequence). V2 writes claims to the `claims` table and
 * uses claims.id everywhere downstream, so the field never landed
 * anywhere queryable from the V2 claim detail endpoint.
 *
 * Add claim_allocated_to as a nullable bigint FK-shaped column. The
 * show() path already reads new_claims.claim_allocated_to when
 * present, so existing legacy rows stay visible; V2 Create starts
 * populating the new column going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claims')) return;
        if (Schema::hasColumn('claims', 'claim_allocated_to')) return;

        Schema::table('claims', function (Blueprint $t) {
            $t->unsignedBigInteger('claim_allocated_to')->nullable()->after('created_by');
            $t->date('claim_allocated_on')->nullable()->after('claim_allocated_to');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('claims')) return;

        Schema::table('claims', function (Blueprint $t) {
            foreach (['claim_allocated_on', 'claim_allocated_to'] as $col) {
                if (Schema::hasColumn('claims', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
