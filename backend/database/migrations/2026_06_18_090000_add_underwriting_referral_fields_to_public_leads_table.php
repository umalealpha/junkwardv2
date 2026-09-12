<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Structured fields for the Motor Comprehensive high-value (>P500k)
     * underwriting-referral callback. Captured separately from the
     * free-text `message` column so LeadController can build the
     * Underwriting notification email without parsing prose.
     */
    public function up(): void
    {
        Schema::table('public_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('public_leads', 'vehicle_details')) {
                $table->string('vehicle_details', 255)->nullable()->after('product');
            }
            if (!Schema::hasColumn('public_leads', 'sum_insured')) {
                $table->decimal('sum_insured', 12, 2)->nullable()->after('vehicle_details');
            }
            if (!Schema::hasColumn('public_leads', 'quote_reference')) {
                $table->string('quote_reference', 64)->nullable()->after('sum_insured');
            }
            if (!Schema::hasColumn('public_leads', 'underwriting_notified_at')) {
                $table->timestamp('underwriting_notified_at')->nullable()->after('quote_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('public_leads', function (Blueprint $table) {
            $cols = array_filter(
                ['vehicle_details', 'sum_insured', 'quote_reference', 'underwriting_notified_at'],
                fn ($c) => Schema::hasColumn('public_leads', $c)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
