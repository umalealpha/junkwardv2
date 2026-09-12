<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyCoverageNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('policy_specified_items');
        Schema::dropIfExists('policy_specified_item_details');
        if (!Schema::hasTable('policy_coverage_notes')) {
            Schema::create('policy_coverage_notes', function (Blueprint $table) {
                $table->id();
                $table->integer('policy_id');
                $table->bigInteger('coverage_id');
                $table->integer('term_id')->nullable();
                $table->text('note');
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
        Schema::dropIfExists('policy_coverage_notes');
    }
}
