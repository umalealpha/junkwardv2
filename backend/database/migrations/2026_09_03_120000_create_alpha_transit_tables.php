<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alpha Transit Cover (courier goods-in-transit) — supporting tables (V2-only).
 *
 *  - atc_webhook_events: inbound event log + idempotency. idempotency_key is
 *    unique so an ATC retry of the same event can never double-ingest; the raw
 *    envelope is kept so events that fail processing can be replayed.
 *  - atc_couriers: courier partner registry — maps the ATC company_code (EGC,
 *    KTU, ARX, ...) to the Graphite agencies row the partner's policies are
 *    scoped under.
 *  - atc_shipments: the transit risk record — route, goods, sender/receiver
 *    and financials for each ATC policy. The legacy `risk_address` table is
 *    property-shaped and cannot hold a shipment, so the shipment detail lives
 *    here, keyed to the legacy policies row by policy_id / policy_number.
 *  - atc_payments: monthly courier remittances (payment.received /
 *    recon.monthly_settled). One row per remittance; the per-policy money legs
 *    land in legacy payment_transactions.
 *  - atc_claims: ATC-side claim detail (claim amounts have no column on the
 *    legacy `claims` table; they live here alongside the ATC status verbatim).
 *
 * Guarded per the self-healing migration standard. Runs on the default
 * connection (the V2 ops DB / mysql_system in deployed envs) — same pattern
 * as the Swiftly integration tables.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('atc_webhook_events')) {
            Schema::create('atc_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('idempotency_key')->unique();       // X-Idempotency-Key — idempotent dedupe
                $table->string('event_type', 64)->index();         // policy.created | payment.received | ...
                $table->string('status', 20)->default('received')->index(); // received | processed | duplicate | failed
                $table->longText('payload')->nullable();           // raw verified JSON envelope
                $table->string('entity_type', 32)->nullable();     // policy | payment | claim
                $table->unsignedBigInteger('graphite_id')->nullable(); // id of the row the event produced/matched
                $table->string('error', 500)->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('atc_couriers')) {
            Schema::create('atc_couriers', function (Blueprint $table) {
                $table->id();
                $table->string('company_code', 16)->unique();      // ATC company code, e.g. EGC
                $table->string('name');
                $table->unsignedBigInteger('agency_id')->nullable(); // legacy agencies.id the courier maps to
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('atc_shipments')) {
            Schema::create('atc_shipments', function (Blueprint $table) {
                $table->id();
                // Nullable: only webhook-ingested shipments carry the ATC
                // platform's id; policies sold directly on start.alphadirect
                // (channel='start') have none. MySQL allows multiple NULLs
                // under a unique index.
                $table->unsignedBigInteger('atc_policy_id')->nullable()->unique();
                $table->string('channel', 8)->default('atc')->index(); // atc | start
                $table->unsignedBigInteger('policy_id')->index();      // legacy policies.id
                $table->string('policy_number', 32)->index();          // ATC-9000001 / GIT2026...
                $table->string('company_code', 16)->index();           // courier code, or DIRECT for start sales
                $table->string('payment_status', 20)->default('unpaid')->index(); // unpaid | paid | settled
                $table->string('issued_by_email')->nullable();
                $table->string('issued_by_name')->nullable();
                $table->string('sender_name');
                $table->string('sender_phone', 32)->nullable();
                $table->string('sender_email')->nullable();
                $table->string('receiver_name')->nullable();
                $table->string('receiver_phone', 32)->nullable();
                $table->string('receiver_email')->nullable();
                $table->string('from_zone', 8);
                $table->string('from_town')->nullable();
                $table->string('to_zone', 8);
                $table->string('to_town')->nullable();
                $table->string('goods_category', 8);               // STD | ELE
                $table->string('goods_description', 500)->nullable();
                $table->decimal('declared_value', 12, 2);
                $table->decimal('weight_kg', 8, 2)->nullable();
                $table->decimal('sum_insured', 12, 2);
                $table->decimal('premium', 12, 2);
                $table->decimal('excess', 12, 2)->nullable();
                $table->decimal('rate', 8, 5)->nullable();
                $table->string('currency', 8)->default('BWP');
                $table->date('cover_start');
                $table->date('cover_end');
                $table->string('courier_waybill')->nullable();
                $table->string('service_type', 32)->nullable();
                $table->decimal('courier_fee', 10, 2)->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('atc_payments')) {
            Schema::create('atc_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('atc_payment_id')->unique(); // payment id on the ATC platform
                $table->string('company_code', 16)->index();
                $table->string('payment_type', 32)->nullable();    // monthly_recon
                $table->string('reference_month', 7)->nullable()->index(); // 2026-06
                $table->string('bank_reference')->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 8)->default('BWP');
                $table->date('payment_date')->nullable();
                $table->longText('policies_settled')->nullable();  // JSON array of ATC policy numbers
                $table->unsignedInteger('policies_count')->default(0);
                $table->string('status', 20)->default('recorded')->index(); // recorded | partial (recon arrived first)
                $table->string('recorded_by_email')->nullable();
                $table->string('notes', 500)->nullable();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('atc_claims')) {
            Schema::create('atc_claims', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('atc_claim_id')->unique(); // claim id on the ATC platform
                $table->unsignedBigInteger('claim_id')->nullable()->index(); // legacy claims.id
                $table->string('claim_number', 32)->index();          // ATC-CLM-9000001
                $table->string('policy_number', 32)->index();
                $table->string('company_code', 16)->nullable();
                $table->string('incident_type', 32)->nullable();
                $table->string('status', 32)->default('open')->index(); // ATC status verbatim
                $table->decimal('claim_amount', 12, 2)->nullable();
                $table->decimal('settled_amount', 12, 2)->nullable(); // no column on legacy claims — lives here
                $table->string('claimant_name')->nullable();
                $table->string('claimant_phone', 32)->nullable();
                $table->string('claimant_email')->nullable();
                $table->string('filed_by_email')->nullable();
                $table->timestamp('filed_at')->nullable();
                $table->timestamps();
            });
        }

        // Seed the courier registry with the three partners the ATC platform
        // ships with (agency_id is attached lazily at first event — the
        // agencies table lives on the legacy connection).
        foreach ([
            ['company_code' => 'EGC', 'name' => 'EG Couriers'],
            ['company_code' => 'KTU', 'name' => 'KTU Express'],
            ['company_code' => 'ARX', 'name' => 'Aramex Botswana'],
        ] as $courier) {
            if (!DB::table('atc_couriers')->where('company_code', $courier['company_code'])->exists()) {
                DB::table('atc_couriers')->insert($courier + [
                    'status'     => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('atc_claims');
        Schema::dropIfExists('atc_payments');
        Schema::dropIfExists('atc_shipments');
        Schema::dropIfExists('atc_couriers');
        Schema::dropIfExists('atc_webhook_events');
    }
};
