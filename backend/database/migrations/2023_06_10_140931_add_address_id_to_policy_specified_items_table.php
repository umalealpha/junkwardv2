<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAddressIdToPolicySpecifiedItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_specified_items', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_specified_items', 'risk_address_id')) {
                $table->bigInteger('risk_address_id')->after('id')->nullable();
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
        Schema::table('policy_specified_items', function (Blueprint $table) {
            $table->dropColumn('risk_address_id');
        });
    }
}
