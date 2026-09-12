<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExtentionSumInsuredToPolicyExtentionDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_extention_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_extention_detail', 'extention_sum_insured')) {
                $table->decimal('extention_sum_insured', 12, 2)->nullable()->after('extention_limit_id');
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
        Schema::table('policy_extention_detail', function (Blueprint $table) {
            $table->dropColumn(['extention_sum_insured']);
        });
    }
}
