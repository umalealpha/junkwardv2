<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Locks & Keys / Key Loss sub-claim table — V2 port of graphiteBWV8's
 * key_loss.blade.php fields. V8 itself doesn't persist these to a
 * sub-table (its NewClaimController has no LOCKSANDKEYS branch in the
 * type-switch), so V8-created Key Loss claims silently drop everything
 * the operator entered. V2 closes that gap with a dedicated sub-claim
 * table.
 *
 * Table name `key_loss_claim` (singular) follows the V8 sub-claim
 * naming convention (fire_claim, goods_in_transit_claim, glass_claim)
 * and avoids colliding with the legacy `claim_key_loss` table already
 * present in V2 (bound to AlphaDirect\ClaimKeyLoss — different model
 * namespace and keyed on claim_id, not newclaim_id).
 *
 * Includes V2 form extensions (chassis_num, financial_interest,
 * key_reason, estimate, police_affidavit) so the operator's existing
 * input persists. `purpose` and `registered_claim` are V8 active
 * fields restored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_loss_claim', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('newclaim_id')->nullable();
            $table->string('policyNumber')->nullable();

            // V8 active fields
            $table->string('purpose')->nullable();            // Lookup id (vehicle_purpose)
            $table->string('reason')->nullable();             // V8 Lookup id (key_loss_claim_reason)
            $table->date('lossDate')->nullable();             // Date of Loss/Stolen/Damage
            $table->text('descriptionofLoss')->nullable();    // Description
            $table->date('registered_claim')->nullable();     // Date of claim registered

            // V2 extensions already on the existing FE form
            $table->string('chassis_num')->nullable();
            $table->string('financial_interest')->nullable();
            $table->string('key_reason')->nullable();         // V2 string variant ('lost'/'damaged'/'stolen')
            $table->string('estimate')->nullable();           // Replacement estimate
            $table->string('police_affidavit')->nullable();   // S3 path

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_loss_claim');
    }
};
