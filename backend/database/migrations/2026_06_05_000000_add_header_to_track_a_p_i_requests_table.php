<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `header` column the API-request tracker now writes.
 *
 * commit eedb2bcbf ("track api request") started persisting
 * $trackApi->header (and added it to TrackAPIRequestModel::$fillable), but no
 * migration ever created the column. The local `graphite_before_update` DB had
 * it added by hand, so inserts worked locally; on staging/live the column is
 * absent, so every insert throws "Unknown column 'header'", which the
 * try/catch in TrackAPIRequest::terminate() swallows into a Log::warning — so
 * nothing was stored and no error surfaced. That is why tracking recorded
 * locally but not on staging/live.
 *
 * Also widens `input`/`output` from VARCHAR(255) to LONGTEXT: the middleware
 * stores full request bodies and response payloads, which were silently
 * truncated (mysql2 runs strict=false).
 *
 * Runs against the `mysql2` connection (the table lives in
 * `graphite_before_update`, NOT the primary DB). Additive + nullable +
 * idempotent (hasColumn guard) — safe to run on the shared DB.
 */
return new class extends Migration
{
    private string $conn = 'mysql2';

    public function up(): void
    {
        Schema::connection($this->conn)->table('track_a_p_i_requests', function (Blueprint $table) {
            if (!Schema::connection($this->conn)->hasColumn('track_a_p_i_requests', 'header')) {
                $table->longText('header')->nullable()->after('method');
            }
            // Full bodies were being truncated at 255 chars under strict=false.
            $table->longText('input')->nullable()->change();
            $table->longText('output')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection($this->conn)->table('track_a_p_i_requests', function (Blueprint $table) {
            if (Schema::connection($this->conn)->hasColumn('track_a_p_i_requests', 'header')) {
                $table->dropColumn('header');
            }
            $table->string('input')->nullable()->change();
            $table->string('output')->nullable()->change();
        });
    }
};
