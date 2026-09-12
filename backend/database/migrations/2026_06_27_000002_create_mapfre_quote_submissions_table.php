<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MAPFRE/MAWDY "maip-travel" integration — outbound contract (bind) audit log.
 *
 * Mirrors the swiftly_invoice_submissions idea: an audit + idempotency trail
 * for outbound contract/bind calls so the same policy/quote reference can
 * never be double-bound against MAPFRE. `reference` (the ref we generate) is
 * unique; a resubmission of the same reference is reconciled rather than
 * double-submitted. Secrets (tokens / credentials) are NEVER written here.
 *
 * Created on the SAME connection the MapfreService reads/writes — the V2 ops
 * DB (mysql_system), matching SwiftlyService's audit-table convention
 * (DB::connection('mysql_system')). Guarded per the self-healing migration
 * standard. Subject to the audit-log retention policy.
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('mysql_system');

        if (!$schema->hasTable('mapfre_quote_submissions')) {
            $schema->create('mapfre_quote_submissions', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();             // our policy/quote ref — never double-bind
                $table->string('product_id')->nullable();
                $table->string('mapfre_quote_id')->nullable();
                $table->string('mapfre_contract_number')->nullable();
                $table->string('status', 20)->default('pending')->index(); // pending | submitted | failed
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->string('error', 500)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_system')->dropIfExists('mapfre_quote_submissions');
    }
};
