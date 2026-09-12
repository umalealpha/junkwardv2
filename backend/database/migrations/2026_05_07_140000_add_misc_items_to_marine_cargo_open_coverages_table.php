<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marine_cargo_open_coverages', function (Blueprint $table) {
            if (!Schema::hasColumn('marine_cargo_open_coverages', 'misc_items')) {
                $table->json('misc_items')->nullable()->after('clauses');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marine_cargo_open_coverages', function (Blueprint $table) {
            if (Schema::hasColumn('marine_cargo_open_coverages', 'misc_items')) {
                $table->dropColumn('misc_items');
            }
        });
    }
};
