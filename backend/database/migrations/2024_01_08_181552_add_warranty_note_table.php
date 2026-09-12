<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWarrantyNoteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_coverage_notes', function($table) {
            $table->longText('cash_warranty')->nullable()->after('burglar_warranty');
            $table->longText('memoranda_warranty')->nullable()->after('cash_warranty');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('policy_coverage_notes', function($table) {
            $table->dropColumn('cash_warranty');
            $table->dropColumn('memoranda_warranty');
        });
    }
}
