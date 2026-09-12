<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Generic key/value runtime settings, editable without a redeploy —
     * mirrors the integration_settings pattern (AlphaDirect\Services\
     * IntegrationSettings) but for arbitrary string values rather than
     * just enabled/disabled flags.
     *
     * First consumer: 'underwriting_referral_email', the mailbox that
     * receives high-value (>P500k) Motor Comprehensive callback referrals.
     */
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 120)->unique();
                $table->text('value')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->string('updated_by_name', 120)->nullable();
                $table->timestamps();
            });
        }

        // Seed only — never overwrite a value an ops admin may have already
        // changed via the settings UI on a re-run against drifted state.
        if (!DB::table('app_settings')->where('key', 'underwriting_referral_email')->exists()) {
            DB::table('app_settings')->insert([
                'key'        => 'underwriting_referral_email',
                'value'      => 'underwriting@alphadirect.co.bw',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
