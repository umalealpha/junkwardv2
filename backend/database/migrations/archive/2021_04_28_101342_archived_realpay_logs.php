<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ArchivedRealpayLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('archived_realpay_logs', function (Blueprint $table) {
            $table->integer('id')->nullable(false);
            $table->string('policy_id')->nullable(true);
            $table->string('event')->nullable(true);
            $table->string('status')->nullable(true);

            $table->string('created_at')->nullable(true);
            $table->string('updated_at')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('archived_realpay_logs');
    }
}
