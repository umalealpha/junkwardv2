<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Register the specialist monthly + quarterly auto-renew commands with
     * V2's DB-driven cron scheduler (CronKernel model). The cron-server
     * scheduler reads cron_kernel rows where run_on_server is 'cron_server'
     * (or null) automatically — no Kernel.php edit needed.
     *
     * Specialist twins of DomComMonthlyAutoRenew / DomComQuaterlyAutoRenew
     * (DOM/COM motor products 7/8). Scoped to specialist products
     * (16,17,18,19,20,22) and disjoint from the DOM/COM renewal crons, so a
     * policy is never renewed twice:
     *
     *   - DomComMonthlyAutoRenew:cron       → products 7, 8      (DOM/COM motor)
     *   - DomComQuaterlyAutoRenew:cron      → products 7, 8      (DOM/COM motor)
     *   - SpecialistMonthlyAutoRenew:cron   → products 16..22    (specialist)
     *   - SpecialistQuaterlyAutoRenew:cron  → products 16..22    (specialist)
     *
     * Created DISABLED (status=0): the scheduler only ever runs cron_kernel
     * rows where status=1, so these crons will NOT fire automatically. They
     * run ONLY when you flip status=1 from the Cron Portal — or when invoked
     * manually for a single policy with --policy=. Use the manual run to
     * verify during testing; activate the schedule only when you're ready.
     *
     * Insert-only-if-missing (not updateOrInsert): once you activate a cron
     * (status=1), a re-run of this migration must NOT silently reset it back
     * to disabled. We therefore only seed each row when it doesn't exist and
     * never touch an existing row's status.
     *
     * Times group them with the other evening renewal crons (DOM/COM monthly
     * 19:50, quarterly 19:55, specialist anniversary 19:58) without colliding.
     */
    public function up(): void
    {
        $crons = [
            ['cron_name' => 'SpecialistMonthlyAutoRenew:cron',  'run_time' => '20:00'],
            ['cron_name' => 'SpecialistQuaterlyAutoRenew:cron', 'run_time' => '20:05'],
        ];

        foreach ($crons as $cron) {
            $exists = DB::table('cron_kernel')
                ->where('cron_name', $cron['cron_name'])
                ->exists();

            if (!$exists) {
                DB::table('cron_kernel')->insert([
                    'cron_name'     => $cron['cron_name'],
                    'run_type'      => 'Daily',
                    'run_time'      => $cron['run_time'],
                    'status'        => 0, // disabled — flip to 1 from Cron Portal when ready
                    'run_on_server' => 'cron_server',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('cron_kernel')
            ->whereIn('cron_name', [
                'SpecialistMonthlyAutoRenew:cron',
                'SpecialistQuaterlyAutoRenew:cron',
            ])
            ->delete();
    }
};
