<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API Error / Request Log — JSON-native document store on the mysql2 (tracking) DB.
 *
 * Every field that varies per request is stored as a JSON document, so you can
 * query any nested path without adding columns:
 *
 *   request_data  → {method, url, headers, query, body, user_agent, ip}
 *   response_data → {status, body, content_type, size_bytes}
 *   error_data    → {class, message, file, line, code, trace[{file,line,fn}]}
 *
 * Generated/virtual columns (error_class, is_error) allow fast indexing on
 * the most common filter paths without duplicating data.
 *
 * Log threshold is controlled by API_ERROR_LOG_THRESHOLD env (default 400).
 * Set to 0 to log every request; 500 for 5xx only.
 */
return new class extends Migration
{
    protected $connection = 'mysql2';

    public function up(): void
    {
        Schema::connection('mysql2')->create('api_error_log', function (Blueprint $table) {
            $table->id();
            $table->char('trace_id', 36)->comment('UUID – ties together multi-step flows');

            // ── Scalar fields indexed directly ──────────────────────────────
            $table->string('method', 10);
            $table->string('url', 2000);
            $table->string('route', 200)->nullable()->comment('Laravel named route or path pattern');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable()->comment('Wall-clock time in milliseconds');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Authenticated user id, null for guests');
            $table->string('user_name', 120)->nullable();

            // ── JSON document columns ────────────────────────────────────────
            $table->json('request_data') ->comment('headers (sanitised), query params, body (truncated 8 KB), user_agent, ip');
            $table->json('response_data')->comment('status, body snippet (4 KB), content_type, size_bytes');
            $table->json('error_data')   ->nullable()->comment('exception class/message/file/line + top-10 trace frames');

            // ── Virtual columns for fast filter/index ────────────────────────
            // is_error: 1 when an exception was thrown
            $table->tinyInteger('is_error')
                  ->virtualAs('IF(error_data IS NOT NULL, 1, 0)')
                  ->comment('Virtual: 1 when error_data is set');

            // error_class: e.g. "Illuminate\Database\QueryException"
            $table->string('error_class', 150)
                  ->virtualAs("JSON_UNQUOTE(JSON_EXTRACT(error_data, '$.class'))")
                  ->nullable()
                  ->comment('Virtual: extracted from error_data.class');

            // ── Investigation workflow ───────────────────────────────────────
            $table->tinyInteger('is_investigated')->default(0);
            $table->string('investigation_note', 1000)->nullable();
            $table->unsignedBigInteger('investigated_by')->nullable();
            $table->timestamp('investigated_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // ── Indexes ──────────────────────────────────────────────────────
            $table->index('trace_id');
            $table->index(['status_code', 'created_at']);
            $table->index(['is_investigated', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
            $table->index('is_error');
            $table->index('error_class');
            $table->index('route');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql2')->dropIfExists('api_error_log');
    }
};
