<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAddedByInPolicyTermTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_term', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_term', 'added_by')) {
                $table->string('added_by')->nullable();
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
        Schema::table('policy_term', function (Blueprint $table) {
            $table->dropColumn('added_by');
        });
    }
}
