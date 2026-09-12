<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete support on `claims` for the Claims Tracker replica's "Del" action.
 *
 * The legacy tracker hard-deleted a claim from its standalone SQLite. In Graphite
 * a claim is a live financial record (linked reserves / payments / policy), so
 * "Del" is implemented as a REVERSIBLE soft-delete instead of a physical destroy:
 * it stamps `deleted_at` (+ who), the list endpoints hide stamped rows, and the
 * data — reserves, payments, history — is never touched and can be restored.
 *
 * Additive, nullable, guarded, reversible. No data migrated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claims')) {
            return;
        }

        Schema::table('claims', function (Blueprint $table) {
            if (!Schema::hasColumn('claims', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->index();
            }
            if (!Schema::hasColumn('claims', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('claims')) {
            return;
        }

        Schema::table('claims', function (Blueprint $table) {
            if (Schema::hasColumn('claims', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
            if (Schema::hasColumn('claims', 'deleted_by')) {
                $table->dropColumn('deleted_by');
            }
        });
    }
};
