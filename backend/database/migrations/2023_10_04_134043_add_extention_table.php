<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExtentionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('extentions')) {
            Schema::create('extentions', function (Blueprint $table) {
                $table->id();
                $table->integer('s_ParentCoverageID')->nullable();
                $table->string('s_ParentCoverageCode')->nullable();
                $table->string('extention_type')->nullable();
                $table->string('s_CoverageName')->nullable();
                $table->string('s_CoverageCode')->nullable();
                $table->string('s_ScreenName')->nullable();
                $table->string('s_CoverageDesc')->nullable();
                $table->string('rate')->nullable();
                $table->string('d_EffectiveDt')->nullable();
                $table->string('d_ExpirationDt')->nullable();
                $table->string('s_RatingMethod')->nullable();
                $table->string('s_DISPLAYTOUSER')->nullable();
                $table->string('n_DisplaySequence')->nullable();
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
        //
    }
}
