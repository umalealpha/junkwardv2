<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated `assessors` master-data table.
 *
 * Backs the new Assessors admin CRUD (sidebar) and feeds the Non-Motor
 * Assessor dropdown on the claim Assessor tab. This is a self-contained
 * list — separate from the legacy user(role=Accessor) / supplier(type=Assessor)
 * sources — so it can be managed in one place.
 *
 * `claim_assessment` gains `assessor_external_id` to remember which assessor
 * record was appointed (used alongside the existing "assessor:ID" token).
 *
 * Defensive (hasTable / hasColumn) throughout — several tables here were
 * hand-built on some environments.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('assessors')) {
            Schema::create('assessors', function (Blueprint $t) {
                $t->increments('id');
                $t->string('name', 200);
                $t->string('email', 200)->nullable();
                $t->string('phone', 50)->nullable();
                $t->string('company', 200)->nullable();
                $t->string('category', 20)->nullable()
                    ->comment('motor | non_motor | both');
                $t->boolean('is_active')->default(true);
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        if (Schema::hasTable('claim_assessment')) {
            Schema::table('claim_assessment', function (Blueprint $t) {
                if (!Schema::hasColumn('claim_assessment', 'assessor_external_id')) {
                    $t->unsignedInteger('assessor_external_id')->nullable()
                        ->comment('assessors.id when appointed from the Assessors master list');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessors');
        // Leave assessor_external_id in place — column may be referenced by data.
    }
};
