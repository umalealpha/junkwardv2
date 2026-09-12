<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MotoLink (motolink.app) INBOUND assessment mirror columns on `claims`.
 *
 * Ported from the Claims Tracker (js/db.js). A scheduled bridge
 * (claims:motolink-sync) pulls vehicle assessments from motolink.app and writes
 * them onto the matching claim (matched on claims.claim_number). These
 * motolink_* columns are a DEDICATED machine mirror of the assessment so the
 * integration NEVER overwrites a value the claims team typed by hand — the sync
 * only fills a manual stage field (claim_tracker_workflow.assessment_report_date)
 * when it is still empty.
 *
 * Additive, nullable, guarded and reversible. No data migrated here. The bridge
 * ships OFF (no-op until MOTOLINK_API_KEY is set), so these columns simply sit
 * empty until it is enabled.
 */
class AddMotolinkColumnsToClaimsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('claims')) {
            return;
        }

        Schema::table('claims', function (Blueprint $table) {
            if (!Schema::hasColumn('claims', 'motolink_assessment_id')) {
                $table->string('motolink_assessment_id', 64)->nullable()->index(); // e.g. ALPHA-0000002758
            }
            if (!Schema::hasColumn('claims', 'motolink_status')) {
                $table->string('motolink_status', 60)->nullable(); // New|Request Auth|Authorised|Completed|Cancelled|Total Loss
            }
            if (!Schema::hasColumn('claims', 'motolink_final_cost')) {
                $table->decimal('motolink_final_cost', 15, 2)->nullable(); // authorised / final repair cost
            }
            if (!Schema::hasColumn('claims', 'motolink_total_loss')) {
                $table->boolean('motolink_total_loss')->default(false); // write-off flag
            }
            if (!Schema::hasColumn('claims', 'motolink_write_off_alert')) {
                $table->string('motolink_write_off_alert', 255)->nullable(); // "possible write-off" alert text
            }
            if (!Schema::hasColumn('claims', 'motolink_vin')) {
                $table->string('motolink_vin', 40)->nullable();
            }
            if (!Schema::hasColumn('claims', 'motolink_registration')) {
                $table->string('motolink_registration', 40)->nullable();
            }
            if (!Schema::hasColumn('claims', 'motolink_make')) {
                $table->string('motolink_make', 60)->nullable();
            }
            if (!Schema::hasColumn('claims', 'motolink_model')) {
                $table->string('motolink_model', 120)->nullable();
            }
            if (!Schema::hasColumn('claims', 'motolink_updated_at')) {
                $table->string('motolink_updated_at', 40)->nullable(); // motolink's own updatedAt (as reported)
            }
            if (!Schema::hasColumn('claims', 'motolink_synced_at')) {
                $table->timestamp('motolink_synced_at')->nullable(); // last successful sync (our clock)
            }
            if (!Schema::hasColumn('claims', 'motolink_sync_error')) {
                $table->string('motolink_sync_error', 255)->nullable();
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('claims')) {
            return;
        }

        Schema::table('claims', function (Blueprint $table) {
            foreach ([
                'motolink_assessment_id',
                'motolink_status',
                'motolink_final_cost',
                'motolink_total_loss',
                'motolink_write_off_alert',
                'motolink_vin',
                'motolink_registration',
                'motolink_make',
                'motolink_model',
                'motolink_updated_at',
                'motolink_synced_at',
                'motolink_sync_error',
            ] as $col) {
                if (Schema::hasColumn('claims', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
