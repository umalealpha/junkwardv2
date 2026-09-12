<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddWhatsappValidateCron extends Migration
{
    public function up()
    {
        // WhatsApp number validation — runs daily to validate unchecked numbers
        // Disabled by default — enable when WHATSAPP_TOKEN is configured
        DB::table('cron_kernel')->insert([
            'cron_name'     => 'whatsapp:validate-numbers',
            'run_type'      => 'Daily',
            'run_time'      => '04:00',
            'status'        => 0, // Disabled by default
            'run_on_server' => 'bw_server',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down()
    {
        DB::table('cron_kernel')->where('cron_name', 'whatsapp:validate-numbers')->delete();
    }
}
