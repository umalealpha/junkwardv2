<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Glass / Windscreen sub-claim table — V2 port of graphiteBWV8's
 * glass.blade.php fields. V8 itself never persists these fields (its
 * NewClaimController has no `GLASS` branch in the type-switch), so
 * V8-created Glass claims silently drop incident_date / extent / cause /
 * the four "After" photos with their descriptions. V2 closes that gap
 * with a dedicated sub-claim table.
 *
 * Table name `glass_claim` (singular) follows the V8 sub-claim naming
 * convention (fire_claim, goods_in_transit_claim, travel_insurance_claim)
 * and avoids colliding with the legacy `glass_claims` (plural) table
 * already in V2 — that one is a customer-facing artifact bound to
 * AlphaDirect\GlassClaim (different namespace from this sub-claim model).
 *
 * Includes V2 extensions (damage_location, replacement quotes) that
 * V2's existing Glass form already captured but never persisted — so
 * the wire-up doesn't drop any field the operator currently sees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glass_claim', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();

            // V8 glass.blade.php — Damage Details
            $table->date('incident_date')->nullable();
            $table->string('extent')->nullable();          // Cracked / Shattered
            $table->text('cause')->nullable();

            // V2 extension — Damage Location dropdown
            // (windscreen / rear / side). Kept as free-text so legacy
            // / future values round-trip without a CHECK constraint.
            $table->string('damage_location')->nullable();

            // V2 extension — two-quote comparison block
            $table->string('company_1')->nullable();
            $table->string('amount_quote_1')->nullable();
            $table->string('quote_1')->nullable();         // S3 path
            $table->string('company_2')->nullable();
            $table->string('amount_quote_2')->nullable();
            $table->string('quote_2')->nullable();         // S3 path

            // V8 "After" damage photos (4 sides) + descriptions
            $table->string('incidentFront')->nullable();   // S3 path
            $table->string('incidentBack')->nullable();    // S3 path
            $table->string('incidentRight')->nullable();   // S3 path
            $table->string('incidentLeft')->nullable();    // S3 path
            $table->text('front_image_description')->nullable();
            $table->text('back_image_description')->nullable();
            $table->text('right_image_description')->nullable();
            $table->text('left_image_description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glass_claim');
    }
};
