<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyReinsurance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('policy_reinsurance')) {
            Schema::create('policy_reinsurance', function (Blueprint $table) {
                $table->id();
                $table->integer('product_id')->nullable();
                $table->integer('risk_id')->nullable();
                $table->integer('policy_id')->nullable();
                $table->integer('term_id')->nullable();
                $table->integer('action_id')->nullable();
                $table->integer('transaction_id')->nullable();
                $table->integer('group_id')->nullable();
                $table->integer('formula_id')->nullable();
                $table->integer('coverage_id')->nullable();
                $table->integer('treaty_id')->nullable();
                $table->integer('type_id')->nullable();
                $table->string('totalSumInsured')->nullable();
                $table->string('totalPremium')->nullable();
                $table->string('treatySI')->nullable();
                $table->string('treatyPercentage')->nullable();
                $table->string('treatyPremium')->nullable();
                $table->string('UsedFormula')->nullable();
                $table->string('PORIFacMasters')->nullable();
                $table->string('PORIFacDetails')->nullable();
                $table->string('added_by')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('policy_reinsurance');
    }
}
