<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRemovedknowNameTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_profile', function (Blueprint $table) {
            if (Schema::hasColumn('customer_profile', 'know_name')) {
                $table->dropColumn('know_name');
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
        Schema::table('customer_profile', function($table) {
            if (!Schema::hasColumn('customer_profile', 'know_name')) {
              $table->string('know_name');
            }
        });
    }
}
