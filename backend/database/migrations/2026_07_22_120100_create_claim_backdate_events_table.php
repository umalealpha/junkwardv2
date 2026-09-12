<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * claim_backdate_events — one row per successful backdate of claim stage dates
 * (feeds the Backdate Control dashboard + alerts). Ported from the Claims
 * Tracker's `backdate_events` table.
 *
 * Additive, guarded and reversible. Only ever written when the
 * `claims_backdate_governance` flag is on and an allowed backdate occurs.
 */
class CreateClaimBackdateEventsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('claim_backdate_events')) {
        Schema::create('claim_backdate_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grant_id')->nullable();  // -> grants.id (NULL = admin override)
            $table->unsignedBigInteger('claim_id')->index();
            $table->string('claim_number', 191)->default('');
            $table->string('username', 191);
            $table->string('user_role', 191)->default('');
            $table->longText('changes_json')->nullable();        // [{field, old, new}]
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index('username');
        });
        }
    }

    public function down()
    {
        Schema::dropIfExists('claim_backdate_events');
    }
}
