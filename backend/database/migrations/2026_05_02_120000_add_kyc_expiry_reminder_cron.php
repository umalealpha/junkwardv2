<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Register the kyc:expiry-reminders command with V2's DB-driven
     * cron scheduler (CronKernel model). The cron runner picks rows
     * up automatically — no Kernel.php edit needed.
     *
     * Disabled on insert (status=0) so ops can sanity-check on prod
     * with --dry-run first, then flip status=1 when ready to send
     * real reminders.
     */
    public function up(): void
    {
        DB::table('cron_kernel')->insert([
            'cron_name'     => 'kyc:expiry-reminders',
            'run_type'      => 'Daily',
            'run_time'      => '08:00',
            'status'        => 0, // disabled — flip to 1 after dry-run on prod
            'run_on_server' => 'bw_server',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('cron_kernel')->where('cron_name', 'kyc:expiry-reminders')->delete();
    }
};
