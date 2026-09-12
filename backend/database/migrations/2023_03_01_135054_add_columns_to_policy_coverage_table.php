<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToPolicyCoverageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('policy_coverage', function (Blueprint $table) {
            if (!Schema::hasColumn('policy_coverage', 'term_id')) {
                $table->integer('term_id')->nullable();
            }
            if (!Schema::hasColumn('policy_coverage', 'rate')) {
                $table->decimal('rate',10,4)->nullable();
            }

            if (!Schema::hasColumn('policy_coverage', 'calculated_value')) {
                $table->decimal('calculated_value',10,4)->nullable();
            }
//            Changing columns for table "policy_coverage" requires Doctrine DBAL. Please install the doctrine/dbal package.
//            $table->enum('entity_type',['Vehicle','Risk Address','Devices','Members'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('policy_coverage', function (Blueprint $table) {
            $table->dropColumn('term_id');
            $table->dropColumn('rate');
            $table->dropColumn('calculated_value');
//            Changing columns for table "policy_coverage" requires Doctrine DBAL. Please install the doctrine/dbal package.
//            $table->string('entity_type')->nullable()->change();
        });
    }
}
