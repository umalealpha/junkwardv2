<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comment-status workflow + priority reminders (Claims Tracker -> Graphite,
 * flag `claims_comment_status`, default OFF). Three purely-additive changes,
 * each individually guarded so a re-run — or an env where a column was already
 * added by hand — is a no-op:
 *
 *  1. claim_comment_statuses      — 1:1 per-claim "comment status" + an
 *     "Awaiting — what?" sub-reason. A dedicated side table (NOT new columns on
 *     the very large prod `claims` table) so the migration never takes a slow /
 *     locking ALTER on that table. Mirrors the claim_tracker_workflow shape.
 *  2. claim_review_notes.priority — the note's reminder priority
 *     (urgent/high/normal/low/none) that drives the unread-@mention reminder
 *     tick threshold.
 *  3. claim_note_mentions.{notification_id, notified_at, reminder_sent_at} —
 *     lets the reminder tick tell whether a priority @mention is still unread
 *     (join to the in-app notification's read_at) and remember that it has
 *     already reminded (so it never re-sends).
 *
 * Nothing here changes existing behaviour: the columns/table are only read and
 * written by the new flag-gated API + tick. Reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Per-claim comment status + sub-reason (1:1 side table).
        if (!Schema::hasTable('claim_comment_statuses')) {
            Schema::create('claim_comment_statuses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('claim_id')->unique(); // -> claims.id (1:1)
                $table->string('comment_status', 60)->nullable();
                $table->string('comment_sub_reason', 60)->nullable(); // only meaningful when status = Awaiting
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->string('updated_by_name', 150)->nullable();
                $table->timestamps();
            });
        }

        // 2. Priority on review notes (drives the reminder threshold).
        if (Schema::hasTable('claim_review_notes') && !Schema::hasColumn('claim_review_notes', 'priority')) {
            Schema::table('claim_review_notes', function (Blueprint $table) {
                $table->string('priority', 20)->nullable()->after('note'); // urgent|high|normal|low|none
            });
        }

        // 3. Unread + reminder tracking on mentions.
        if (Schema::hasTable('claim_note_mentions')) {
            Schema::table('claim_note_mentions', function (Blueprint $table) {
                if (!Schema::hasColumn('claim_note_mentions', 'notification_id')) {
                    // -> notifications.id (mysql_system) so the tick can read read_at.
                    $table->unsignedBigInteger('notification_id')->nullable()->after('mentioned_user_id');
                }
                if (!Schema::hasColumn('claim_note_mentions', 'notified_at')) {
                    $table->timestamp('notified_at')->nullable()->after('notification_id');
                }
                if (!Schema::hasColumn('claim_note_mentions', 'reminder_sent_at')) {
                    $table->timestamp('reminder_sent_at')->nullable()->after('notified_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('claim_note_mentions')) {
            Schema::table('claim_note_mentions', function (Blueprint $table) {
                foreach (['reminder_sent_at', 'notified_at', 'notification_id'] as $col) {
                    if (Schema::hasColumn('claim_note_mentions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('claim_review_notes') && Schema::hasColumn('claim_review_notes', 'priority')) {
            Schema::table('claim_review_notes', function (Blueprint $table) {
                $table->dropColumn('priority');
            });
        }

        Schema::dropIfExists('claim_comment_statuses');
    }
};
