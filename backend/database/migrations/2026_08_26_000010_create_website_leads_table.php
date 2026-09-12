<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Captures every submission from the public marketing website
 * (alphadirect-website-bw): quotation enquiries for products with no
 * self-service rating flow (commercial, domestic, instant, non-health),
 * contact messages, claim notifications, career applications and
 * newsletter sign-ups.
 *
 * Deliberately separate from public_leads: that table's `kind` is a
 * closed enum owned by the start_fe callback/issue flows, while this one
 * is a generic enquiry layer that sits BEFORE rating — anything that
 * matures into a rated quote continues through the existing
 * motor_quotes / bundle_quotes lifecycle instead of being duplicated
 * here. `type` and `status` are strings (not enums) so new website
 * forms never need an ALTER.
 *
 * Health quotations do NOT land here — the website routes those to the
 * Health Graphite portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_leads', function (Blueprint $table) {
            $table->id();
            // quotation | contact | claim | career | newsletter
            $table->string('type', 20)->index();
            $table->string('full_name', 160)->nullable();
            $table->string('email', 160)->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->string('product', 160)->nullable();
            $table->text('message')->nullable();
            // Form-specific fields (id numbers, DOB, broker codes, file
            // paths…) stay in one json blob so every form shares a row shape.
            $table->json('details')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->string('source', 64)->default('website');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_leads');
    }
};
