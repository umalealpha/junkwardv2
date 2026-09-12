<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assessor-appointment feature.
 *
 * - `assessor_category` lets an assessor be tagged Motor / Non-Motor / Both so
 *   the claim Assessor tab can filter the dropdown by the claim's workflow.
 *   Added to BOTH `users` (internal assessors, role "Accessor") and `suppliers`
 *   (external assessors, supplierType "Assessor").
 * - `claim_assessment` gains columns to remember whether the appointed assessor
 *   was an internal user or an external supplier, and the list of email
 *   recipients used for the Non-Motor "send email to the assessor" flow.
 *
 * Defensive (hasColumn) throughout — several of these tables were hand-built on
 * some environments, so a blind addColumn would crash on re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $t) {
                if (!Schema::hasColumn('users', 'assessor_category')) {
                    $t->string('assessor_category', 20)->nullable()
                        ->comment('motor | non_motor | both — for users with the Accessor role');
                }
            });
        }

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $t) {
                if (!Schema::hasColumn('suppliers', 'assessor_category')) {
                    $t->string('assessor_category', 20)->nullable()
                        ->comment('motor | non_motor | both — for suppliers of type Assessor');
                }
            });
        }

        if (Schema::hasTable('claim_assessment')) {
            Schema::table('claim_assessment', function (Blueprint $t) {
                if (!Schema::hasColumn('claim_assessment', 'assessor_source')) {
                    $t->string('assessor_source', 20)->nullable()
                        ->comment('user | supplier — origin of the appointed assessor');
                }
                if (!Schema::hasColumn('claim_assessment', 'assessor_supplier_id')) {
                    $t->unsignedInteger('assessor_supplier_id')->nullable()
                        ->comment('suppliers.id when the appointed assessor is external');
                }
                if (!Schema::hasColumn('claim_assessment', 'recipient_emails')) {
                    $t->text('recipient_emails')->nullable()
                        ->comment('JSON array of email addresses notified for this appointment');
                }
                if (!Schema::hasColumn('claim_assessment', 'assessor_category')) {
                    $t->string('assessor_category', 20)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // No-op: columns may have predated this migration on hand-built tables.
    }
};
