<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_group_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('employer_group_id', 20)->nullable()->index();
            $table->string('event', 100);          // e.g. created, updated, deleted
            $table->string('actor')->nullable();    // "user:ID name"
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_group_audit_logs');
    }
};
