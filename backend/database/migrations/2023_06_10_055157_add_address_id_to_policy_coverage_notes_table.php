<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAddressIdToPolicyCoverageNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_coverage_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_coverage_notes', 'risk_address_id')) {
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
        Schema::table('policy_coverage_notes', function (Blueprint $table) {
            $table->dropColumn('risk_address_id');
        });
    }
}
