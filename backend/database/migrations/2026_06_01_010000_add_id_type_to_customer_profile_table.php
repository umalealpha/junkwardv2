<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hospital Cash Assurance form gap: capture the customer's "ID Type"
 * (Omang / Passport) the start.alphadirect.co.bw form now collects.
 *
 * customer_profile already stores the omang and passport NUMBERS in
 * separate columns, but had no column recording WHICH identity document
 * the customer presented. Adding `id_type` lets the HCB create path persist
 * the explicit choice instead of leaving reviewers to infer it.
 *
 * Nullable varchar so existing rows/callers that omit it stay valid; the
 * HCB controller writes it via array_intersect_key, so the create path is
 * safe on environments where this migration hasn't run yet.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customer_profile')) return;

        Schema::table('customer_profile', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_profile', 'id_type')) {
                $table->string('id_type', 20)->nullable()->after('passport');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_profile')) return;

        Schema::table('customer_profile', function (Blueprint $table) {
            if (Schema::hasColumn('customer_profile', 'id_type')) {
                $table->dropColumn('id_type');
            }
        });
    }
};
