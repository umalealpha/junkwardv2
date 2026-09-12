<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTermIdInManyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_beneficiary', function (Blueprint $table) {
            $table->integer('term_id')->after('policy_id')->nullable();
        });

        Schema::table('vehicle', function (Blueprint $table) {
            $table->integer('term_id')->after('policy_id')->nullable();
        });

        Schema::table('policy_cellphone', function (Blueprint $table) {
            $table->integer('term_id')->after('policy_id')->nullable();
        });

        Schema::table('risk_address', function (Blueprint $table) {
            $table->integer('term_id')->after('policy_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('policy_beneficiary', 'term_id')){
            Schema::table('policy_beneficiary', function (Blueprint $table){
                $table->dropColumn('term_id');
            });
        }

        if (Schema::hasColumn('vehicle', 'term_id')){
            Schema::table('vehicle', function (Blueprint $table){
                $table->dropColumn('term_id');
            });
        }

        if (Schema::hasColumn('policy_cellphone', 'term_id')){
            Schema::table('policy_cellphone', function (Blueprint $table){
                $table->dropColumn('term_id');
            });
        }

        if (Schema::hasColumn('risk_address', 'term_id')){
            Schema::table('risk_address', function (Blueprint $table){
                $table->dropColumn('term_id');
            });
        }
    }
}
