<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Lead-capture table for the customer-facing start.alphadirect.co.bw site.
     *
     * Two flows hit this table:
     *  - "Get a callback" lead requests (kind = 'callback')
     *  - "Report issue" support tickets (kind = 'issue')
     *
     * Kept separate from the existing `leads` table (agent-managed CRM lead
     * codes tied to customers) — a website visitor doesn't have a customer
     * record yet, and ops triages these in a different queue.
     */
    public function up(): void
    {
        Schema::create('public_leads', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['callback', 'issue'])->index();
            $table->string('full_name', 120);
            $table->string('cellphone', 24);
            $table->string('email', 160)->nullable();
            $table->string('product', 120)->nullable();
            $table->string('policy_number', 64)->nullable();
            $table->string('category', 64)->nullable(); // for 'issue' kind
            $table->text('message')->nullable();

            // Triage state: new → in_progress → resolved | spam
            $table->enum('status', ['new', 'in_progress', 'resolved', 'spam'])
                  ->default('new')->index();
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();

            // Anti-abuse / forensics
            $table->string('ip', 45)->nullable()->index();
            $table->string('user_agent', 500)->nullable();
            $table->string('source', 32)->default('start_fe');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_leads');
    }
};
