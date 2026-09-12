<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Help Desk — lets any logged-in Graphite V2 user quickly report a system
 * issue (description + up to 3 screenshots + an optional attachment). The
 * reporter is captured automatically from the authenticated (SSO) session,
 * and an admin/developer can assign the ticket to whoever can solve it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_desk_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_ref', 20)->unique();         // e.g. HD-7F3K9Q
            $table->text('description');                         // min 100 chars (enforced in request validation)

            // Reporter — captured from auth()->user(), not user-supplied.
            $table->unsignedBigInteger('reporter_id')->index();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_email')->nullable();

            // Assignment — the resource/developer who will solve it.
            $table->unsignedBigInteger('assignee_id')->nullable()->index();
            $table->string('assignee_name')->nullable();

            // Lifecycle.
            $table->string('status', 20)->default('open')->index(); // open | in_progress | resolved | closed

            // Up to 3 screenshots + 1 general attachment, stored as an array
            // of { slot, original_name, path, mime, size } on the s3 disk
            // (which falls back to the local driver in dev).
            $table->json('attachments')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_tickets');
    }
};
