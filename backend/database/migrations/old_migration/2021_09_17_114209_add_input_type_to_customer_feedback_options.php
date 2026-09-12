<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInputTypeToCustomerFeedbackOptions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_feedback_options', function (Blueprint $table) {
            $table->integer('input_type')->nullable();  // 1=>radio button 2=>select dropdowns
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_feedback_options', function (Blueprint $table) {
            $table->dropColumn('input_type');
        });
    }
}
