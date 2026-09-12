<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * claim_backdate_requests — a request-role user's self-service request for a
 * time-limited backdate window; an admin approves (which mints a grant) or
 * denies. Ported from the Claims Tracker's `backdate_requests` table.
 *
 * Additive, guarded and reversible.
 */
class CreateClaimBackdateRequestsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('claim_backdate_requests')) {
        Schema::create('claim_backdate_requests', function (Blueprint $table) {
            $table->id();
            $table->string('approve_token', 64)->unique();       // random token for email-link flow
            $table->string('requester_id', 64)->index();
            $table->string('requester_username', 191);
            $table->string('requester_name', 191)->default('');
            $table->string('requester_role', 191)->default('');
            $table->longText('claim_ids_json')->nullable();      // JSON array of claim IDs
            $table->text('claim_numbers')->nullable();           // comma-separated for display
            $table->text('reason')->nullable();
            $table->unsignedInteger('duration_hours')->default(24);
            $table->string('urgency', 16)->default('normal');    // normal | urgent
            $table->string('status', 16)->default('pending')->index(); // pending|approved|denied|expired
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_by', 191)->nullable();
            $table->text('decision_note')->nullable();
            $table->unsignedBigInteger('grant_id')->nullable();  // -> grants.id when approved
        });
        }
    }

    public function down()
    {
        Schema::dropIfExists('claim_backdate_requests');
    }
}
