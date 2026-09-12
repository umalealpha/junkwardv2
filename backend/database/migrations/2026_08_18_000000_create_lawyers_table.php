<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lawyers master-data table — mirrors `assessors` (see
 * 2026_06_26_130000_create_assessors_table). Backs the Lawyers admin CRUD page.
 * Idempotent create so re-runs / drifted envs are safe.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('lawyers')) {
            Schema::create('lawyers', function (Blueprint $t) {
                $t->increments('id');
                $t->string('name', 200);
                $t->string('email', 200)->nullable();
                $t->string('phone', 50)->nullable();
                $t->string('company', 200)->nullable();
                $t->string('category', 20)->nullable()->comment('motor | non_motor | both');
                $t->boolean('is_active')->default(true);
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lawyers');
    }
};
