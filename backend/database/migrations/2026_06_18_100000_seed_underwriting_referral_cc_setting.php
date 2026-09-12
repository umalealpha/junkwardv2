<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * CC recipient for the Motor Comprehensive high-value (>P500k)
     * underwriting referral email — kept in app_settings alongside
     * underwriting_referral_email so both addresses are editable without
     * a redeploy.
     */
    public function up(): void
    {
        if (!DB::table('app_settings')->where('key', 'underwriting_referral_cc')->exists()) {
            DB::table('app_settings')->insert([
                'key'        => 'underwriting_referral_cc',
                'value'      => 'svispute@theriskco.com',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'underwriting_referral_cc')->delete();
    }
};
