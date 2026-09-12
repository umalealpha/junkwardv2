<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysql_write')->create('webhook_buffer', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30)->index();        // realpay, dpo, ngenius, orange_money
            $table->string('event_type', 50)->nullable();  // transaction, settlement, cancellation
            $table->longText('payload');                      // raw webhook body (JSON string)
            $table->string('status', 20)->default('pending')->index(); // pending, processing, done, failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'source', 'created_at']);
        });
    }

    public function down()
    {
        Schema::connection('mysql_write')->dropIfExists('webhook_buffer');
    }
};
