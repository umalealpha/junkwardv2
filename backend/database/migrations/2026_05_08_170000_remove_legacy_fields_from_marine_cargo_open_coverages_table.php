<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marine_cargo_open_coverages', function (Blueprint $table) {
            $table->dropColumn([
                'new_altered',
                'period_of_insurance',
                'is_renewable',
                'is_project_specific',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('marine_cargo_open_coverages', function (Blueprint $table) {
            $table->string('new_altered')->nullable();
            $table->string('period_of_insurance')->nullable();
            $table->string('is_renewable')->nullable();
            $table->string('is_project_specific')->nullable();
        });
    }
};
