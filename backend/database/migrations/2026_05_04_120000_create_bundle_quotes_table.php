<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bundle quotes — parallel of motor_quotes for multi-product purchases.
 *
 * The customer adds 2+ products to the bundle on /start (Bundled
 * Products tile), the server stages a single bundle_quotes row + DPO
 * CompanyRef, on payment success a materialisation worker promotes
 * each line into a real Policy + Customer + Product-specific row.
 *
 * Why a single table vs per-product: keeps the bundle-discount
 * accounting honest (one CompanyRef = one DPO charge = one row),
 * customer can cancel the whole bundle in one click, audit trail is
 * crystal clear about which lines came from one decision.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('bundle_quotes')) return;

        Schema::create('bundle_quotes', function (Blueprint $t) {
            $t->id();
            $t->string('quote_number', 32)->unique();
            $t->string('cellphone', 16)->index();
            $t->string('auth_token_hash', 64)->nullable();

            // Customer + lines (denormalised JSON — full snapshot of
            // what the customer saw, so a rate change between quote and
            // materialisation doesn't mutate the deal).
            $t->json('customer_payload');
            $t->json('lines_payload'); // [{ product_id, plan_id, premium, ... }]

            // Pricing breakdown — everything in BWP, all VAT-inclusive.
            $t->decimal('subtotal',         12, 2);
            $t->decimal('discount_rate_pct', 5, 2)->default(0);
            $t->decimal('discount_amount',  12, 2)->default(0);
            $t->decimal('total',            12, 2);

            // State machine. 'pending_pay' until DPO IPN flips to 'paid';
            // 'expired' on TTL; 'cancelled' on customer abandon.
            $t->enum('status', ['pending_pay', 'paid', 'expired', 'cancelled'])
              ->default('pending_pay')->index();
            $t->string('dpo_trans_token', 64)->nullable();
            $t->timestamp('expires_at')->index();
            $t->timestamp('paid_at')->nullable();

            // Materialisation outcomes — array of policy ids per line,
            // plus a top-level customer_id for fast joins.
            $t->json('materialised_policy_ids')->nullable();
            $t->unsignedBigInteger('materialised_customer_id')->nullable()->index();

            // Forensics
            $t->string('client_ip', 45)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_quotes');
    }
};
