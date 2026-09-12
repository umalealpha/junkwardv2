<?php

namespace AlphaDirect\Services;

use AlphaDirect\Mail\HelpDeskNotificationMail;
use AlphaDirect\Models\HelpDeskEmailTemplate;
use AlphaDirect\Models\HelpDeskNotification;
use AlphaDirect\Models\HelpDeskSla;
use AlphaDirect\Models\HelpDeskTicket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends SLA warning / breach emails. Reuses the existing Help Desk template +
 * dedup infrastructure (help_desk_email_templates, help_desk_notifications,
 * HelpDeskNotificationMail) so wording is editable and duplicates are
 * impossible. Recipients = ticket assignee + escalation recipients; dedup is
 * per recipient email so a person who is both is emailed once.
 *
 * All sends are best-effort — a mail failure is logged and recorded, never
 * thrown.
 */
class SlaNotifier
{
    public function __construct(private SlaEscalationResolver $escalation)
    {
    }

    public function warning(HelpDeskTicket $ticket, HelpDeskSla $sla, string $kind, int $percent, ?Carbon $dueAt, int $remainingMinutes): void
    {
        $event = "sla_{$kind}_warning"; // sla_response_warning | sla_resolution_warning
        $ctx   = $this->context($ticket, $kind, $dueAt, $percent, $remainingMinutes);
        foreach ($this->recipients($ticket) as $email => $name) {
            // ref = threshold so 75% and 90% each send once per recipient.
            $this->send($ticket, $event, $email, $name, (string) $percent, $ctx);
        }
    }

    public function breach(HelpDeskTicket $ticket, HelpDeskSla $sla, string $kind): void
    {
        $event = "sla_{$kind}_breach"; // sla_response_breach | sla_resolution_breach
        $dueAt = $kind === 'response' ? $sla->response_due_at : $sla->resolution_due_at;
        $ctx   = $this->context($ticket, $kind, $dueAt, 100, 0);
        foreach ($this->recipients($ticket) as $email => $name) {
            $this->send($ticket, $event, $email, $name, '', $ctx); // one breach email per recipient
        }
    }

    /** @return array<string,string> email => display name (unique by email) */
    private function recipients(HelpDeskTicket $ticket): array
    {
        $list = [];
        if (!empty($ticket->assignee_email)) {
            $list[$ticket->assignee_email] = $ticket->assignee_name ?: $ticket->assignee_email;
        }
        foreach ($this->escalation->recipientsForLevel(1) as $r) {
            $list[$r['email']] = $r['name'] ?? $r['email'];
        }
        return $list;
    }

    private function send(HelpDeskTicket $ticket, string $event, string $toEmail, ?string $toName, string $ref, array $ctx): void
    {
        try {
            $role = 'stakeholder';

            $already = HelpDeskNotification::where([
                'ticket_id' => $ticket->id, 'event' => $event,
                'recipient_role' => $role, 'to_email' => $toEmail, 'ref' => $ref,
            ])->where('status', 'sent')->exists();
            if ($already) {
                return;
            }

            $template = HelpDeskEmailTemplate::where('key', "{$event}_{$role}")->where('active', true)->first();
            if (!$template) {
                Log::warning('help_desk.sla_notify.no_template', ['key' => "{$event}_{$role}"]);
                return;
            }

            $subject = $this->render($template->subject, $ctx);
            $body    = $this->render($template->body, $ctx);

            $status = 'sent';
            $error  = null;
            try {
                Mail::to($toEmail)->send(new HelpDeskNotificationMail($subject, $body, $ctx['ticket_link']));
            } catch (\Throwable $e) {
                $status = 'failed';
                $error  = $e->getMessage();
                Log::error('help_desk.sla_notify.send_failed', [
                    'ticket' => $ticket->ticket_ref, 'event' => $event, 'to' => $toEmail, 'msg' => $e->getMessage(),
                ]);
            }

            HelpDeskNotification::updateOrCreate(
                ['ticket_id' => $ticket->id, 'event' => $event, 'recipient_role' => $role, 'to_email' => $toEmail, 'ref' => $ref],
                ['recipient_name' => $toName, 'subject' => $subject, 'status' => $status, 'error' => $error],
            );
        } catch (\Throwable $e) {
            Log::error('help_desk.sla_notify.unexpected', ['msg' => $e->getMessage()]);
        }
    }

    private function context(HelpDeskTicket $ticket, string $kind, ?Carbon $dueAt, int $percent, int $remainingMinutes): array
    {
        $base = rtrim((string) config('help_desk.ticket_url_base'), '/');
        $tz   = (string) config('help_desk.timezone', 'Africa/Gaborone');

        return [
            'ticket_number' => $ticket->ticket_ref,
            'title'         => (string) $ticket->title,
            'priority'      => ucfirst((string) $ticket->priority),
            'assignee_name' => (string) ($ticket->assignee_name ?: $ticket->assignee_email ?: 'Unassigned'),
            'breach_type'   => $kind === 'response' ? 'Response' : 'Resolution',
            'percent'       => (string) $percent,
            'due_at'        => $dueAt ? $dueAt->copy()->setTimezone($tz)->format('d M Y, H:i') : '—',
            'remaining_time'=> $this->humanMinutes($remainingMinutes),
            'ticket_link'   => $base ? "{$base}/{$ticket->id}" : '',
        ];
    }

    private function humanMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h > 0 && $m > 0) return "{$h}h {$m}m";
        if ($h > 0) return "{$h}h";
        return "{$m}m";
    }

    private function render(string $template, array $ctx): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($m) use ($ctx) {
            return array_key_exists($m[1], $ctx) ? $ctx[$m[1]] : $m[0];
        }, $template);
    }
}
