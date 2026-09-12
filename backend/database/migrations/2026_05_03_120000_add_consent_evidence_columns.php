<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strong-evidence columns on customer_privacy_consents.
 *
 * Why now:
 * BW field agents close most start.alphadirect.co.bw policies on their
 * own phones with the customer beside them. A bare "I agree" checkbox
 * is too weak for AML / DPA audit — a single tap could be the agent
 * forging consent. The new columns let the agent-assisted UI capture:
 *   - signature_data_url: base64 PNG of finger-drawn signature on agent's screen
 *   - geo_lat / geo_lon / geo_accuracy_m: GPS at consent moment, proves
 *     the agent was at the customer's location
 *
 * All columns are nullable so self-serve customers (who don't get the
 * agent-assisted UI) and older clients pass through unchanged.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customer_privacy_consents')) return;
        Schema::table('customer_privacy_consents', function (Blueprint $t) {
            if (!Schema::hasColumn('customer_privacy_consents', 'signature_data_url')) {
                // longText so a fingerpaint signature (~10-50KB base64) fits.
                $t->longText('signature_data_url')->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('customer_privacy_consents', 'geo_lat')) {
                $t->decimal('geo_lat', 10, 7)->nullable()->after('signature_data_url');
            }
            if (!Schema::hasColumn('customer_privacy_consents', 'geo_lon')) {
                $t->decimal('geo_lon', 10, 7)->nullable()->after('geo_lat');
            }
            if (!Schema::hasColumn('customer_privacy_consents', 'geo_accuracy_m')) {
                $t->unsignedInteger('geo_accuracy_m')->nullable()->after('geo_lon');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_privacy_consents')) return;
        Schema::table('customer_privacy_consents', function (Blueprint $t) {
            foreach (['geo_accuracy_m', 'geo_lon', 'geo_lat', 'signature_data_url'] as $col) {
                if (Schema::hasColumn('customer_privacy_consents', $col)) $t->dropColumn($col);
            }
        });
    }
};
