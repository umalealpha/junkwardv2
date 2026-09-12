<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * claim_backdate_grants — time-limited permission for a request-role user (or
 * ALL request-role users) to backdate claim stage dates. Ported from the Claims
 * Tracker's `backdate_grants` table.
 *
 * Additive, guarded and reversible. Part of the Claims Tracker -> Graphite
 * backdate-governance migration. Inert unless the `claims_backdate_governance`
 * flag is on — the presence of this table changes NO existing behaviour.
 */
class CreateClaimBackdateGrantsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('claim_backdate_grants')) {
        Schema::create('claim_backdate_grants', function (Blueprint $table) {
            $table->id();
            // specific users.id (as string) OR the 'ALL_CLAIMS_MANAGERS' sentinel
            $table->string('target_user_id', 64)->index();
            $table->string('target_label', 191)->default('');   // friendly display
            $table->string('granted_by', 191);                  // admin username/email
            $table->text('reason')->nullable();                 // mandatory justification (>=10 chars)
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();        // set if revoked early
            $table->string('revoked_by', 191)->nullable();
            $table->timestamps();
        });
        }
    }

    public function down()
    {
        Schema::dropIfExists('claim_backdate_grants');
    }
}
