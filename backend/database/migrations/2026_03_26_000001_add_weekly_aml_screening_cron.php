<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddWeeklyAmlScreeningCron extends Migration
{
    public function up()
    {
        // Insert cron entry as DISABLED (status=0) — enable in production when AML service is deployed
        DB::table('cron_kernel')->insert([
            'cron_name'     => 'aml:weekly-screening',
            'run_type'      => 'weekly_sundays',
            'run_time'      => '03:00',
            'status'        => 0, // Disabled by default
            'run_on_server' => 'bw_server',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down()
    {
        DB::table('cron_kernel')->where('cron_name', 'aml:weekly-screening')->delete();
    }
}
