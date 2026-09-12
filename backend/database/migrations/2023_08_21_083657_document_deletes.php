<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DocumentDeletes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('document_deletes', function (Blueprint $table) {
       
            $table->id();
            $table->string('policy_id')->nullable();
            $table->string('document_name')->nullable();
            $table->longText('document')->nullable();
            $table->string('activity_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('document_deletes');
    }
}
