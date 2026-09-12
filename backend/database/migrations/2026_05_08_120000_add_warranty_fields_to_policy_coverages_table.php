<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWarrantyFieldsToPolicyCoveragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_coverages', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_coverages', 'property_business_being')) {
                $table->longText('property_business_being')->nullable()->after('discount_value');
            }
            if (!Schema::hasColumn('policy_coverages', 'burglar_alarm_warranty')) {
                $table->longText('burglar_alarm_warranty')->nullable()->after('property_business_being');
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
        Schema::table('policy_coverages', function (Blueprint $table) {
            $table->dropColumn(['property_business_being', 'burglar_alarm_warranty']);
        });
    }
}
