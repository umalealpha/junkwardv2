<?php

namespace AlphaDirect\Services;

use AlphaDirect\Mail\HelpDeskNotificationMail;
use AlphaDirect\Models\HelpDeskEmailTemplate;
use AlphaDirect\Models\HelpDeskNotification;
use AlphaDirect\Models\HelpDeskTicket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends + logs Help Desk notification emails on ticket lifecycle events.
 *
 * Design notes:
 *  - Templates are editable rows in help_desk_email_templates, keyed
 *    "{event}_{role}", rendered with {{placeholders}}.
 *  - Every send is logged in help_desk_notifications (audit). The unique key
 *    (ticket, event, role, to_email, ref) + a pre-send "already sent" check
 *    prevent duplicate emails for the same event.
 *  - All sends are best-effort: a mail/template failure is logged and never
 *    bubbles up to break the ticket operation that triggered it.
 */
class HelpDeskNotifier
{
    private const STATUS_LABELS = [
        'new' => 'New', 'open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed', 'reopened' => 'Reopened',
    ];

    /** Ticket created → reporter (always) + assignee (if assigned at creation). */
    public function ticketCreated(HelpDeskTicket $ticket): void
    {
        $this->notify($ticket, 'created', 'reporter', $ticket->reporter_email, $ticket->reporter_name, '');
        if (!empty($ticket->assignee_email)) {
            $this->notify($ticket, 'created', 'assignee', $ticket->assignee_email, $ticket->assignee_name, $ticket->assignee_email);
        }
    }

    /** Ticket (re)assigned → reporter + the (new) assignee. */
    public function ticketAssigned(HelpDeskTicket $ticket, bool $reassigned = false): void
    {
        if (empty($ticket->assignee_email)) {
            return; // unassignment — nothing to notify
        }
        $event = $reassigned ? 'reassigned' : 'assigned';
        $ref   = $ticket->assignee_email; // recurring event → dedup per distinct assignee
        $this->notify($ticket, $event, 'reporter', $ticket->reporter_email, $ticket->reporter_name, $ref);
        $this->notify($ticket, $event, 'assignee', $ticket->assignee_email, $ticket->assignee_name, $ref);
    }

    /** Ticket closed → reporter. */
    public function ticketClosed(HelpDeskTicket $ticket): void
    {
        $this->notify($ticket, 'closed', 'reporter', $ticket->reporter_email, $ticket->reporter_name, '');
    }

    /**
     * Ticket reopened → reporter + assignee (so the dev who owns it knows it's
     * active again). Best-effort; needs `reopened_reporter` / `reopened_assignee`
     * templates seeded to actually send — otherwise it logs a warning and skips.
     */
    public function ticketReopened(HelpDeskTicket $ticket): void
    {
        $this->notify($ticket, 'reopened', 'reporter', $ticket->reporter_email, $ticket->reporter_name, '');
        if (!empty($ticket->assignee_email)) {
            $this->notify($ticket, 'reopened', 'assignee', $ticket->assignee_email, $ticket->assignee_name, '');
        }
    }

    // ── internals ────────────────────────────────────────────────────────

    private function notify(HelpDeskTicket $ticket, string $event, string $role, ?string $toEmail, ?string $toName, string $ref): void
    {
        try {
            if (empty($toEmail)) {
                return;
            }

            // Dedup: skip if we already successfully sent this exact notification.
            $already = HelpDeskNotification::where([
                'ticket_id' => $ticket->id, 'event' => $event,
                'recipient_role' => $role, 'to_email' => $toEmail, 'ref' => $ref,
            ])->where('status', 'sent')->exists();
            if ($already) {
                return;
            }

            $template = HelpDeskEmailTemplate::where('key', "{$event}_{$role}")
                ->where('active', true)->first();
            if (!$template) {
                Log::warning('help_desk.notify.no_template', ['key' => "{$event}_{$role}"]);
                return;
            }

            $ctx     = $this->context($ticket);
            $subject = $this->render($template->subject, $ctx);
            $body    = $this->render($template->body, $ctx);

            $status = 'sent';
            $error  = null;
            try {
                Mail::to($toEmail)->send(new HelpDeskNotificationMail($subject, $body, $ctx['ticket_link']));
            } catch (\Throwable $e) {
                $status = 'failed';
                $error  = $e->getMessage();
                Log::error('help_desk.notify.send_failed', [
                    'ticket' => $ticket->ticket_ref, 'event' => $event, 'role' => $role, 'msg' => $e->getMessage(),
                ]);
            }

            // updateOrCreate keyed on the dedup tuple — lets a previously
            // failed send be retried (row updated) without violating the
            // unique index, while a successful send is recorded for audit.
            HelpDeskNotification::updateOrCreate(
                [
                    'ticket_id' => $ticket->id, 'event' => $event,
                    'recipient_role' => $role, 'to_email' => $toEmail, 'ref' => $ref,
                ],
                ['recipient_name' => $toName, 'subject' => $subject, 'status' => $status, 'error' => $error],
            );
        } catch (\Throwable $e) {
            // Never let a notification problem break the ticket operation.
            Log::error('help_desk.notify.unexpected', ['msg' => $e->getMessage()]);
        }
    }

    private function context(HelpDeskTicket $ticket): array
    {
        $base = rtrim((string) config('help_desk.ticket_url_base'), '/');
        return [
            'ticket_number' => $ticket->ticket_ref,
            'title'         => (string) $ticket->title,
            'reporter_name' => (string) ($ticket->reporter_name ?: $ticket->reporter_email),
            'assignee_name' => (string) ($ticket->assignee_name ?: $ticket->assignee_email ?: 'Unassigned'),
            'status'        => self::STATUS_LABELS[$ticket->status] ?? ucfirst((string) $ticket->status),
            'ticket_link'   => $base ? "{$base}/{$ticket->id}" : '',
        ];
    }

    private function render(string $template, array $ctx): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($m) use ($ctx) {
            return array_key_exists($m[1], $ctx) ? $ctx[$m[1]] : $m[0];
        }, $template);
    }
}
