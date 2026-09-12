<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSExtensionsGroupNameExtentionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('extentions', function($table) {
            $table->string('s_ExtensionsGroupName')->nullable()->after('s_CoverageDesc');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('extentions', function($table) {
            $table->dropColumn('s_ExtensionsGroupName');
        });
    }
}
