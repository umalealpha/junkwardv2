<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the "Section 4 – Extensions/Endorsements" line-item list to Bonds and
 * Guarantees, positioned after Section 3 – Key Policy Conditions in the
 * capture form, V2 Quote Sheet, and Policy Document. Same JSON line-item
 * pattern as `bond_schedule` — each row is `{ description: string }`.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('bonds_coverages') && !Schema::hasColumn('bonds_coverages', 'extensions_endorsements')) {
            Schema::table('bonds_coverages', function (Blueprint $table) {
                $table->json('extensions_endorsements')->nullable()->after('bond_schedule');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bonds_coverages') && Schema::hasColumn('bonds_coverages', 'extensions_endorsements')) {
            Schema::table('bonds_coverages', function (Blueprint $table) {
                $table->dropColumn('extensions_endorsements');
            });
        }
    }
};
