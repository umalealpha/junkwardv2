<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Register the policy:renew-annual-specialist command with V2's
     * DB-driven cron scheduler (CronKernel model). The cron-server
     * scheduler reads cron_kernel rows where run_on_server is
     * 'cron_server' (or null) automatically — no Kernel.php edit needed.
     *
     * Specialist twin of policy:renew-annual (DOM/COM products 7/8).
     * Scoped to specialist products (16,17,18,19,20,22) and disjoint from
     * the DOM/COM anniversary cron, so a policy is never renewed twice.
     *
     * Created DISABLED (status=0): the scheduler only ever runs cron_kernel
     * rows where status=1 (cron Kernel.php: CronKernel::where('status',1)),
     * so this cron will NOT fire automatically. It runs ONLY when you flip
     * status=1 from the Cron Portal — or when triggered manually for a single
     * policy from the DOM/COM Batch Renew screen. Use the manual run to verify
     * during testing; activate the schedule only when you're ready.
     *
     * Insert-only-if-missing (not updateOrInsert): once you activate it
     * (status=1), a re-run of this migration must NOT silently reset it back
     * to disabled. We therefore only seed the row when it doesn't exist and
     * never touch an existing row's status.
     *
     * Time 19:58 groups it with the other evening renewal crons
     * (monthly 19:50, quarterly 19:55) without colliding.
     */
    public function up(): void
    {
        $exists = DB::table('cron_kernel')
            ->where('cron_name', 'policy:renew-annual-specialist')
            ->exists();

        if (!$exists) {
            DB::table('cron_kernel')->insert([
                'cron_name'     => 'policy:renew-annual-specialist',
                'run_type'      => 'Daily',
                'run_time'      => '19:58',
                'status'        => 0, // disabled — flip to 1 from Cron Portal when ready
                'run_on_server' => 'cron_server',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cron_kernel')->where('cron_name', 'policy:renew-annual-specialist')->delete();
    }
};
