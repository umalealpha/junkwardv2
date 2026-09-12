<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closing workflow + lifecycle dates.
 *
 * When a ticket is moved to "closed" the developer must record a short
 * closing summary and attach a screenshot proving the issue is resolved.
 * We also stamp closed_at so the open→closed turnaround is queryable
 * (opened date = created_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            $table->string('closing_summary', 50)->nullable()->after('status');
            $table->timestamp('closed_at')->nullable()->after('closing_summary')->index();
        });
    }

    public function down(): void
    {
        Schema::table('help_desk_tickets', function (Blueprint $table) {
            $table->dropColumn(['closing_summary', 'closed_at']);
        });
    }
};
