<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\IntegrationSettings;
use AlphaDirect\Services\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Priority-reminder tick for the comment-status workflow (Claims Tracker ->
 * Graphite, flag `claims_comment_status`). Emails the assignee of any review
 * note whose @mention is STILL UNREAD past the note's priority threshold
 * (Urgent 30m / High 2h / Normal 4h / Low 24h; "none" never reminds).
 *
 * SEND-GATED: if the `claims_comment_status` flag is OFF this is a complete
 * no-op — it sends nothing and stamps nothing — so it is safe to schedule while
 * the feature ships dark. Idempotent: once a mention has been reminded (or is
 * found already read) its `reminder_sent_at` is stamped so it is never
 * re-processed. Reuses the existing mail path (NotificationDispatcher -> the
 * SendMail event -> Mailgun); never throws.
 */
class ClaimMentionReminderTick extends Command
{
    protected $signature = 'claims:mention-reminder-tick {--dry-run : Report what would be sent without sending or stamping}';

    protected $description = 'Email unread priority @mention reminders past their threshold (flag: claims_comment_status).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // SEND GATE — nothing happens unless the feature is armed.
        if (!$dryRun && !IntegrationSettings::isEnabled('claims_comment_status', false)) {
            $this->info('claims_comment_status is OFF — nothing sent.');
            return self::SUCCESS;
        }

        // priority => threshold minutes (null = never remind).
        $thresholds = [];
        $labels = [];
        foreach ((array) config('claims_comment_status.priorities', []) as $p) {
            if (!isset($p['value'])) continue;
            $thresholds[$p['value']] = $p['threshold_minutes'] ?? null;
            $labels[$p['value']] = $p['label'] ?? $p['value'];
        }

        $now = Carbon::now();

        try {
            $rows = DB::table('claim_note_mentions as m')
                ->join('claim_review_notes as n', 'n.id', '=', 'm.review_note_id')
                ->whereNotNull('m.mentioned_user_id')
                ->whereNull('m.reminder_sent_at')
                ->whereNotNull('m.notification_id')
                ->whereNotNull('n.priority')
                ->where('n.priority', '!=', 'none')
                ->select(
                    'm.id as mention_id',
                    'm.claim_id',
                    'm.mentioned_user_id',
                    'm.notification_id',
                    'n.priority',
                    'n.created_at as note_created_at'
                )
                ->get();
        } catch (\Throwable $e) {
            Log::warning('[MentionReminder] query failed: ' . $e->getMessage());
            $this->error('Query failed: ' . $e->getMessage());
            return self::SUCCESS; // never hard-fail the scheduler
        }

        $base = rtrim((string) (env('REACT_SPA_URL') ?: env('FRONTEND_URL') ?: config('app.url')), '/');

        $sent = 0;
        $closedRead = 0;
        $pending = 0;

        foreach ($rows as $row) {
            $threshold = $thresholds[$row->priority] ?? null;
            if ($threshold === null) {
                continue; // unknown / none priority — no reminder
            }

            $noteAgeMin = $row->note_created_at ? $now->diffInMinutes(Carbon::parse($row->note_created_at)) : 0;
            if ($noteAgeMin < (int) $threshold) {
                $pending++;
                continue; // not yet due
            }

            // Is the in-app mention notification still unread?
            $readAt = null;
            $notifExists = true;
            try {
                $notif = DB::connection('mysql_system')->table('notifications')
                    ->where('id', $row->notification_id)
                    ->first(['read_at']);
                $notifExists = (bool) $notif;
                $readAt = $notif->read_at ?? null;
            } catch (\Throwable $e) {
                Log::warning('[MentionReminder] read-state check failed for mention ' . $row->mention_id . ': ' . $e->getMessage());
                continue;
            }

            // Already read (or notification gone) — close it out, no reminder.
            if (!$notifExists || $readAt !== null) {
                $closedRead++;
                if (!$dryRun) {
                    DB::table('claim_note_mentions')->where('id', $row->mention_id)
                        ->update(['reminder_sent_at' => $now]);
                }
                continue;
            }

            // Unread and past threshold → remind by email.
            $claimNumber = null;
            try {
                $claimNumber = DB::table('claims')->where('id', $row->claim_id)->value('claim_number');
            } catch (\Throwable $e) {
                // best-effort only
            }
            $claimRef  = $claimNumber ?: ('#' . $row->claim_id);
            $reviewUrl = "{$base}/claims/{$row->claim_id}?tab=reviewNotes";
            $prLabel   = $labels[$row->priority] ?? $row->priority;

            if ($dryRun) {
                $this->line("would remind user {$row->mentioned_user_id} — claim {$claimRef} — {$prLabel} — {$noteAgeMin}m old");
                $sent++;
                continue;
            }

            try {
                NotificationDispatcher::send(
                    (int) $row->mentioned_user_id,
                    'claim_mention_reminder',
                    [
                        'title'        => 'Reminder: unread claim mention',
                        'message'      => "You have an unread {$prLabel} priority review-note mention on claim {$claimRef}. Please review it: {$reviewUrl}",
                        'claim_id'     => $row->claim_id,
                        'claim_number' => $claimNumber,
                    ],
                    $reviewUrl,
                    ['email'] // email reminder — the in-app notification already exists
                );
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('[MentionReminder] send failed for mention ' . $row->mention_id . ': ' . $e->getMessage());
            }

            // Stamp regardless of send outcome so we never loop on the same row.
            DB::table('claim_note_mentions')->where('id', $row->mention_id)
                ->update(['reminder_sent_at' => $now]);
        }

        $this->info("Mention reminders — reminded: {$sent}, closed(read): {$closedRead}, not-yet-due: {$pending}"
            . ($dryRun ? ' [dry-run]' : ''));

        return self::SUCCESS;
    }
}
