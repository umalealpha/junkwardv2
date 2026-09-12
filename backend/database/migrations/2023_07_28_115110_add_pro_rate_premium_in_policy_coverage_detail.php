<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProRatePremiumInPolicyCoverageDetail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_coverage_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_coverage_detail', 'pro_rate_premium')) {
                $table->decimal('pro_rate_premium',11,2)->after('calculated_value')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('policy_coverage_detail', function (Blueprint $table) {
            $table->decimal('pro_rate_premium',11,2)->after('calculated_value');
        });
    }
}
