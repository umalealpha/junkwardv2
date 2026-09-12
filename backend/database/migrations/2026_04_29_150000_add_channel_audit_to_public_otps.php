<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Audit + channel-preference upgrade for public_otps.
     *
     * Botswana context: data is expensive and unreliable, so we can't
     * default to WhatsApp. The flow now goes:
     *   1. Customer tells us at first contact what channel(s) work for them
     *   2. Server tries the chain — WhatsApp (if consented) → SMS → email
     *   3. Every send attempt is logged for compliance audit (who got what,
     *      when, on which channel, at what cost, with what provider ref).
     */
    public function up(): void
    {
        Schema::table('public_otps', function (Blueprint $table) {
            // Channel that actually delivered (or last channel attempted on
            // failure). Enum keeps reporting clean.
            $table->enum('delivered_via', [
                'whatsapp', 'sms', 'email', 'voice', 'missed_call', 'agent_assist',
            ])->nullable()->after('user_agent');

            // Did delivery succeed end-to-end?
            $table->enum('delivery_status', ['queued', 'sent', 'delivered', 'failed'])
                  ->default('queued')->after('delivered_via');

            // Per-provider correlation id (Infobip msgId, Meta wamid, etc.)
            $table->string('delivery_provider_ref', 100)->nullable()->after('delivery_status');

            // Cost in thebe (1/100 of a Pula) — populated from provider DLR.
            // Lets ops chargeback/forecast SMS spend per product.
            $table->unsignedInteger('delivery_cost_thebe')->nullable()->after('delivery_provider_ref');

            // Full chain we tried in order — JSON array of attempts:
            // [{channel:'whatsapp',status:'failed',error:'no_consent'},
            //  {channel:'sms',status:'sent',provider_ref:'...'}]
            $table->json('attempted_channels')->nullable()->after('delivery_cost_thebe');
        });

        // Customer contact preferences (per cellphone — keyed by phone since
        // start.alphadirect customers don't have an account before their
        // first policy). Captured at the consent step before the first OTP.
        Schema::create('customer_contact_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('cellphone', 24)->unique();
            $table->boolean('has_whatsapp')->default(false);
            $table->boolean('whatsapp_consent')->default(false); // explicit "yes, send me OTPs on WhatsApp"
            $table->boolean('sms_opt_in')->default(true);        // legal default — customer can opt out via STOP
            $table->boolean('email_opt_in')->default(false);
            $table->string('preferred_email', 160)->nullable();
            $table->enum('preferred_channel', ['whatsapp', 'sms', 'email', 'auto'])->default('auto');
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_ip', 45)->nullable();
            $table->string('consent_user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contact_preferences');
        Schema::table('public_otps', function (Blueprint $table) {
            $table->dropColumn(['delivered_via', 'delivery_status',
                'delivery_provider_ref', 'delivery_cost_thebe', 'attempted_channels']);
        });
    }
};
