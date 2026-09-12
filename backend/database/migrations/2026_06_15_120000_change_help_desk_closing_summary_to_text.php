<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Closing remarks: allow unlimited length (minimum is enforced in request
 * validation at 50 characters). The column was originally VARCHAR(50), which
 * hard-capped remarks at 50 characters — widen it to TEXT.
 *
 * Raw ALTER (MySQL) avoids requiring doctrine/dbal for ->change().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('help_desk_tickets', 'closing_summary')) {
            DB::statement('ALTER TABLE help_desk_tickets MODIFY closing_summary TEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('help_desk_tickets', 'closing_summary')) {
            DB::statement('ALTER TABLE help_desk_tickets MODIFY closing_summary VARCHAR(50) NULL');
        }
    }
};
