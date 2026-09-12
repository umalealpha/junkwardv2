<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAmlColumnsToCustomerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer', function (Blueprint $table) {
            if (!Schema::hasColumn('customer', 'is_aml_verification_done')) {
                $table->integer('is_aml_verification_done')->nullable()->default(0)->after('category_reason');
            }
            if (!Schema::hasColumn('customer', 'aml_verification_response')) {
                $table->longText('aml_verification_response')->nullable()->after('is_aml_verification_done');
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
        Schema::table('customer', function (Blueprint $table) {
            $table->dropColumn(['is_aml_verification_done', 'aml_verification_response']);
        });
    }
}
