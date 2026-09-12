<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GRA-0117 — control + audit tables for the temporary PolicyLedgerBackfill:cron.
 *
 *   gra0117_backfill_control   singleton (id=1): master ON/OFF flag, id cursor,
 *                              last-run high-water (for rollback) + run stats.
 *   gra0117_backfill_policies  per-policy audit: queued / done / skipped(+reason).
 *
 * Also registers the cron in cron_kernel DISABLED (status=0) so it never fires
 * until Finance verifies the affected-policy report and we flip both gates ON.
 * Idempotent + guarded so it is safe under the fail-loud migrate entrypoint.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('gra0117_backfill_control')) {
            Schema::create('gra0117_backfill_control', function (Blueprint $t) {
                $t->unsignedBigInteger('id')->primary();
                $t->boolean('enabled')->default(false);            // master gate
                $t->unsignedBigInteger('scan_cursor')->default(0); // last policy id examined (NOT 'cursor' — reserved word in MariaDB)
                $t->dateTime('last_run_started_at')->nullable();
                $t->dateTime('last_run_finished_at')->nullable();
                $t->unsignedBigInteger('last_run_ledger_highwater')->nullable();
                $t->integer('last_run_posted')->default(0);
                $t->timestamps();
            });
        }

        // singleton control row, OFF by default
        DB::table('gra0117_backfill_control')->updateOrInsert(
            ['id' => 1],
            ['enabled' => 0, 'scan_cursor' => 0, 'created_at' => now(), 'updated_at' => now()]
        );

        if (!Schema::hasTable('gra0117_backfill_policies')) {
            Schema::create('gra0117_backfill_policies', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('policy_id')->unique();
                $t->string('policy_number', 60)->nullable();
                $t->integer('product_id')->nullable();
                $t->string('status', 20)->index();               // queued|done|skipped
                $t->text('reason')->nullable();                  // skip reason for Finance
                $t->timestamps();
            });
        }

        // register the cron DISABLED — flip status=1 from the Cron Portal only
        // after Finance signs off the affected-policy report.
        $exists = DB::table('cron_kernel')->where('cron_name', 'PolicyLedgerBackfill:cron')->exists();
        if (!$exists) {
            DB::table('cron_kernel')->insert([
                'cron_name'     => 'PolicyLedgerBackfill:cron',
                'run_type'      => 'Hourly',         // window-guarded inside the command
                'run_time'      => null,
                'status'        => 0,                // DISABLED
                'run_on_server' => 'cron_server',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cron_kernel')->where('cron_name', 'PolicyLedgerBackfill:cron')->delete();
        Schema::dropIfExists('gra0117_backfill_policies');
        Schema::dropIfExists('gra0117_backfill_control');
    }
};
