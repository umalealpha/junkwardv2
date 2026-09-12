<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAddedQuotaionPolicyTermTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_term', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_term', 'quotation_doc')) {
                $table->string('quotation_doc')->after('added_by')->nullable();
            }
            if (!Schema::hasColumn('policy_term', 'rate_doc')) {
                $table->string('rate_doc')->after('quotation_doc')->nullable();
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
            $table->dropColumn('quotation_doc');
            $table->dropColumn('rate_doc');
        });
    }
}
