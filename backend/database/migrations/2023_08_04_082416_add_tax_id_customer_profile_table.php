<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTaxIdCustomerProfileTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_profile', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_profile', 'tax_id')) {
                $table->string('tax_id')->after('business_note')->nullable();
            }
            if (!Schema::hasColumn('customer_profile', 'org_phone_no')) {
                $table->string('org_phone_no')->after('business_note')->nullable();
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
        Schema::table('customer_profile', function (Blueprint $table) {
            $table->dropColumn('tax_id');
            $table->dropColumn('org_phone_no');
        });
    }
}
