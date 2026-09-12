<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fraud-review columns on refund_requests (native Graphite fraud engine).
 * Mirrors Omni customer_refunds.fraud_flags / fraud_score.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('refund_requests', 'fraud_flags')) {
                $table->json('fraud_flags')->nullable()->after('ai_evidence');
            }
            if (! Schema::hasColumn('refund_requests', 'fraud_score')) {
                $table->unsignedInteger('fraud_score')->default(0)->index()->after('fraud_flags');
            }
            if (! Schema::hasColumn('refund_requests', 'fraud_reviewed_at')) {
                $table->timestamp('fraud_reviewed_at')->nullable()->after('fraud_score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            foreach (['fraud_flags', 'fraud_score', 'fraud_reviewed_at'] as $column) {
                if (Schema::hasColumn('refund_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
