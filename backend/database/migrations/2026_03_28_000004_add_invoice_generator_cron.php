<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddInvoiceGeneratorCron extends Migration
{
    public function up()
    {
        // New optimized invoice generator — disabled, for testing on dev DB only
        DB::table('cron_kernel')->insert([
            'cron_name'     => 'invoice:generate --mode=daily --whatsapp',
            'run_type'      => 'Daily',
            'run_time'      => '20:00',
            'status'        => 0,
            'run_on_server' => 'bw_server',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down()
    {
        DB::table('cron_kernel')->where('cron_name', 'like', 'invoice:generate%')->delete();
    }
}
