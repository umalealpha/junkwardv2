<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_conversations')) {
            Schema::create('whatsapp_conversations', function (Blueprint $table) {
                $table->id();
                $table->string('phone', 30)->index();
                $table->string('flow', 50)->index();   // e.g. ai_exco, ai_agent, ai_customer
                $table->string('step', 50)->default('active');
                $table->json('data')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
