<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourceOfIncomeToCustomerProfileTable extends Migration
{
    /**
     * Add KYC/AML "Source of Income/Funds" capture to customer_profile.
     *   source_of_income          – the selected option key (e.g. "employment")
     *   source_of_income_details  – JSON of the conditional sub-answers
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_profile', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_profile', 'source_of_income')) {
                $table->string('source_of_income')->nullable();
            }
            if (!Schema::hasColumn('customer_profile', 'source_of_income_details')) {
                $table->text('source_of_income_details')->nullable();
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
        Schema::table('customer_profile', function (Blueprint $table) {
            if (Schema::hasColumn('customer_profile', 'source_of_income')) {
                $table->dropColumn('source_of_income');
            }
            if (Schema::hasColumn('customer_profile', 'source_of_income_details')) {
                $table->dropColumn('source_of_income_details');
            }
        });
    }
}
