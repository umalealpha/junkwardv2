<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Central notification dispatcher — routes notifications to appropriate channels.
 *
 * Channels: in-app (notifications table), email (SendMail event), SMS (SendSms event), WhatsApp (future)
 * Priority chain per event type is configurable via notification_templates table.
 * All dispatches are logged to notification_logs for delivery tracking.
 */
class NotificationDispatcher
{
    /**
     * Default channel priority when no template overrides it.
     */
    private const DEFAULT_CHANNELS = ['in_app', 'email'];

    /**
     * Event types that must never send an email, only an in-app ping.
     *
     * `policy_approved`: the approval email goes out ONCE, at "Submit for
     * Approval" (approvalRequested). Approving is an internal decision the
     * approver already knows they made, so the second mail was pure noise.
     * Suppressing it here covers BOTH approve doors — the policy workflow
     * button (PolicyCreateController::approvePolicy) and the UW decide queue
     * (UnderwritingController) — because both fire the same PolicyEvent and
     * route through policyEvent() below. The in-app notification, the
     * activity log and the policy_lifecycle audit row are untouched.
     */
    private const NO_EMAIL_TYPES = ['policy_approved'];

    /**
     * Send a notification to a user.
     *
     * @param int         $userId     Target user ID (from users table)
     * @param string      $type       Notification type (e.g., 'policy_activated', 'payment_failed')
     * @param array       $data       Template variables: ['title' => '', 'message' => '', 'policy_id' => '', ...]
     * @param string|null $action     Click-through URL (e.g., '/policies/123')
     * @param array|null  $channels   Override channels ['in_app', 'email', 'sms'] — null = use template default
     * @param array       $recipients Override recipients: ['email' => '...', 'phone' => '...']
     */
    public static function send(
        int     $userId,
        string  $type,
        array   $data,
        ?string $action = null,
        ?array  $channels = null,
        array   $recipients = []
    ): void {
        try {
            // Resolve channels from template or default
            $activeChannels = $channels ?? self::resolveChannels($type);

            foreach ($activeChannels as $channel) {
                $logId = self::logDispatch($userId, $type, $channel, $data);

                switch ($channel) {
                    case 'in_app':
                        self::sendInApp($userId, $type, $data, $action);
                        self::updateLog($logId, 'sent');
                        break;

                    case 'email':
                        $email = $recipients['email'] ?? self::getUserEmail($userId);
                        if ($email) {
                            self::sendEmail($email, $data);
                            self::updateLog($logId, 'sent');
                        } else {
                            self::updateLog($logId, 'skipped', 'No email address');
                        }
                        break;

                    case 'sms':
                        $phone = $recipients['phone'] ?? self::getUserPhone($userId);
                        if ($phone) {
                            self::sendSms($phone, $data);
                            self::updateLog($logId, 'sent');
                        } else {
                            self::updateLog($logId, 'skipped', 'No phone number');
                        }
                        break;

                    default:
                        self::updateLog($logId, 'skipped', "Unknown channel: {$channel}");
                }
            }
        } catch (\Exception $e) {
            Log::error("NotificationDispatcher failed for user {$userId}, type {$type}: " . $e->getMessage());
        }
    }

    /**
     * Send notification for a policy event — resolves agent + customer from policy.
     */
    /**
     * Cross-user notification: a policy needs approval. Routes one
     * `approval_needed` notification to each user that holds the
     * Underwriter role (or any role whose name contains "underwriter"
     * case-insensitively) so any of them can pick it up. Falls back to
     * users with the Spatie permission `approve_policy` when no role
     * matches. Logs and continues if neither configuration exists —
     * never blocks the submit itself.
     */
    public static function approvalRequested(int $policyId, array $extraData = []): void
    {
        $policy = DB::table('policies')->where('id', $policyId)
            ->select('id', 'policyNumber', 'product_id', 'agent_id', 'added_by')
            ->first();
        if (!$policy) return;

        // Resolve approvers — try roles first, then permissions. Matches the
        // Spatie tables Graphite v2 already uses (model_has_roles +
        // role_has_permissions). Wrapped in try/catch so a Spatie
        // misconfiguration doesn't blow up the submit-for-approval path.
        $approverIds = collect();
        try {
            $roleIds = DB::table('roles')
                ->whereRaw('LOWER(name) LIKE ?', ['%underwriter%'])
                ->orWhere('name', 'Approver')
                ->pluck('id');
            if ($roleIds->isNotEmpty()) {
                $approverIds = DB::table('model_has_roles')
                    ->whereIn('role_id', $roleIds)
                    ->where('model_type', 'AlphaDirect\\User')
                    ->pluck('model_id');
            }
            if ($approverIds->isEmpty()) {
                $permId = DB::table('permissions')->where('name', 'approve_policy')->value('id');
                if ($permId) {
                    $approverIds = DB::table('model_has_permissions')
                        ->where('permission_id', $permId)
                        ->where('model_type', 'AlphaDirect\\User')
                        ->pluck('model_id');
                }
            }
        } catch (\Throwable $e) {
            Log::warning("approvalRequested: approver lookup failed: " . $e->getMessage());
            return;
        }

        if ($approverIds->isEmpty()) {
            Log::info("approvalRequested for policy {$policyId}: no approvers configured (no Underwriter role + no approve_policy permission). Skipping.");
            return;
        }

        $data = array_merge([
            'title'         => 'Approval needed',
            'message'       => "Policy {$policy->policyNumber} is pending your approval.",
            'policy_id'     => $policy->id,
            'policy_number' => $policy->policyNumber,
        ], $extraData);
        $action = "/policies/{$policy->id}";

        foreach ($approverIds->unique() as $userId) {
            // Don't ping the submitter back — they know they just submitted.
            if ((int) $userId === (int) ($policy->added_by ?? 0)) continue;
            self::send((int) $userId, 'approval_needed', $data, $action);
        }
    }

    /**
     * Claim coverage write-off alert. Fired when a Loss Reserve / "Claim
     * Expense" line item is flagged as a write-off. Notifies every Finance
     * and Underwriting user in-app, plus the configurable department mailboxes
     * (AppSetting keys `claim_writeoff_finance_email` /
     * `claim_writeoff_underwriting_email`) by email. Each call covers one
     * claimed item so the recipients see exactly which coverage was written
     * off on which policy. Best-effort: logged and swallowed on failure so the
     * reserve save it follows is never affected.
     */
    public static function claimWriteOff(
        int     $claimId,
        ?string $claimNumber,
        ?string $policyNumber,
        string  $coverageName,
        array   $emailRecipients = [],
        ?string $emailSubject = null,
        ?string $claimType = null,
        ?string $customerName = null
    ): void {
        try {
            $claimRef = $claimNumber ? "claim {$claimNumber}" : "claim #{$claimId}";
            $policyRef = $policyNumber ? " (policy {$policyNumber})" : '';
            $data = [
                'title'         => 'Coverage declared a write-off',
                'message'       => "Coverage \"{$coverageName}\" on {$claimRef}{$policyRef} has been declared a write-off. Please review and take appropriate action.",
                'claim_id'      => $claimId,
                'claim_number'  => $claimNumber,
                'policy_number' => $policyNumber,
                'coverage_name' => $coverageName,
                'claim_type'    => $claimType,
                'customer_name' => $customerName,
            ];
            // When the caller supplies a business-formatted subject
            // ("WRITE OFF - {Policy} {Customer} {Item} {Type} CLAIM#: …"),
            // use it verbatim for the email; sendEmail falls back to the
            // default "Alpha Direct | {title}" when absent.
            if ($emailSubject !== null && trim($emailSubject) !== '') {
                $data['subject'] = trim($emailSubject);
            }
            $action = "/claims/{$claimId}";

            // In-app: every Finance + Underwriting user.
            $recipientIds = self::departmentUserIds(['finance', 'underwrit']);
            foreach ($recipientIds as $uid) {
                self::send((int) $uid, 'claim_write_off', $data, $action, ['in_app']);
            }

            // Email: the recipient addresses the user typed on the form take
            // precedence (single or multiple). When none were entered we fall
            // back to the configurable department mailboxes in app_settings.
            // Blank on both sides simply skips the email channel — in-app still
            // fires — so the feature degrades gracefully.
            $emails = array_filter(array_map('trim', $emailRecipients), fn($e) => $e !== '');
            if (empty($emails)) {
                $emails = array_filter([
                    \AlphaDirect\Models\AppSetting::get('claim_writeoff_finance_email'),
                    \AlphaDirect\Models\AppSetting::get('claim_writeoff_underwriting_email'),
                ]);
            }
            foreach (array_unique($emails) as $email) {
                self::send(0, 'claim_write_off', $data, $action, ['email'], ['email' => $email]);
            }
        } catch (\Throwable $e) {
            Log::error("claimWriteOff notification failed for claim {$claimId}: " . $e->getMessage());
        }
    }

    /**
     * Claim review note notification. Fired when a handler logs a review note
     * and tags recipients. Each tagged recipient gets an in-app notification
     * (when they map to a user) plus an email — copy from the editable
     * email_templates hook `claim_review_note`, with a preview of the note and
     * a "View Review" deep-link into the claim's Review Notes tab. The email
     * always references the CLAIM NUMBER as the primary reference.
     *
     * Best-effort: each recipient is wrapped in its own try/catch and the whole
     * method is swallowed on failure so the note save it follows is never
     * affected. Returns the list of email addresses that were emailed
     * successfully so the caller can stamp notified_at on those rows.
     *
     * @param array<int,array{user_id?:int|null,email:string,name?:string|null}> $recipients
     * @param array{author_name?:string,subject?:string,note_preview?:string} $noteData
     * @return string[] emails successfully notified
     */
    public static function claimReviewNote(
        int     $claimId,
        ?string $claimNumber,
        string  $reviewUrl,
        array   $noteData,
        array   $recipients
    ): array {
        $notified = [];
        try {
            $claimRef = $claimNumber ? "claim {$claimNumber}" : "claim #{$claimId}";
            $author   = $noteData['author_name'] ?? 'A claim handler';

            $action = "/claims/{$claimId}?tab=reviewNotes";
            // Lead the in-app message with the full claim reference so users can
            // identify and act on the notification at a glance:
            //   [Policy] [Customer] [Claimed Item] [Claim Type] CLAIM#: [Number]
            $reference = trim((string) ($noteData['claim_reference'] ?? ''));
            $message   = $reference !== ''
                ? "{$reference} — review note by {$author}"
                : "{$author} logged a review note on {$claimRef}.";
            $inAppData = [
                'title'        => 'New claim review note',
                'message'      => $message,
                'claim_id'     => $claimId,
                'claim_number' => $claimNumber,
                'claim_type'   => $noteData['claim_type'] ?? null,
                'policy_number' => $noteData['policy_number'] ?? null,
                'customer_name' => $noteData['customer_name'] ?? null,
            ];

            $mailData = array_merge($noteData, [
                'claim_number' => $claimNumber,
                'review_url'   => $reviewUrl,
            ]);

            foreach ($recipients as $r) {
                $email  = trim((string) ($r['email'] ?? ''));
                $userId = $r['user_id'] ?? null;

                // In-app for recipients that resolve to a real user.
                if ($userId) {
                    try {
                        self::send((int) $userId, 'claim_review_note', $inAppData, $action, ['in_app']);
                    } catch (\Throwable $e) {
                        Log::warning("claimReviewNote in-app failed for user {$userId}: " . $e->getMessage());
                    }
                }

                if ($email === '') continue;

                try {
                    \Illuminate\Support\Facades\Mail::to($email)
                        ->send(new \AlphaDirect\Mail\ClaimReviewNote($mailData));
                    $notified[] = $email;
                } catch (\Throwable $e) {
                    Log::error("claimReviewNote email failed for {$email} (claim {$claimId}): " . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::error("claimReviewNote notification failed for claim {$claimId}: " . $e->getMessage());
        }
        return $notified;
    }

    /**
     * Resolve the user IDs holding any role whose name matches one of the
     * given keywords (case-insensitive substring). Mirrors the Spatie role
     * lookup in approvalRequested(). Returns an empty collection (never throws)
     * if the role tables are misconfigured.
     */
    private static function departmentUserIds(array $roleKeywords): \Illuminate\Support\Collection
    {
        try {
            $roleIds = DB::table('roles')
                ->where(function ($q) use ($roleKeywords) {
                    foreach ($roleKeywords as $kw) {
                        $q->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($kw) . '%']);
                    }
                })
                ->pluck('id');
            if ($roleIds->isEmpty()) {
                Log::info('claimWriteOff: no Finance/Underwriting roles found — skipping in-app notifications.');
                return collect();
            }
            return DB::table('model_has_roles')
                ->whereIn('role_id', $roleIds)
                ->where('model_type', 'AlphaDirect\\User')
                ->pluck('model_id')
                ->unique()
                ->values();
        } catch (\Throwable $e) {
            Log::warning('departmentUserIds lookup failed: ' . $e->getMessage());
            return collect();
        }
    }

    public static function policyEvent(int $policyId, string $type, array $extraData = []): void
    {
        $policy = DB::table('policies')
            ->leftJoin('customer', 'customer.id', '=', 'policies.customer_id')
            ->leftJoin('agents', 'agents.id', '=', 'policies.agent_id')
            ->where('policies.id', $policyId)
            ->select([
                'policies.id', 'policies.policyNumber', 'policies.agent_id',
                'policies.customer_id', 'policies.product_id',
                'customer.firstName', 'customer.lastName', 'customer.email as customerEmail',
                'customer.cellphone as customerPhone',
                'agents.firstName as agentFirstName', 'agents.lastName as agentLastName',
                'agents.email as agentEmail', 'agents.cellPhone as agentPhone',
                'policies.added_by',
            ])
            ->first();

        if (!$policy) return;

        $data = array_merge([
            'title'         => self::typeTitle($type),
            'message'       => self::typeMessage($type, $policy),
            'policy_id'     => $policy->id,
            'policy_number' => $policy->policyNumber,
        ], $extraData);

        $action = "/policies/{$policy->id}";

        // Email-suppressed types (see NO_EMAIL_TYPES) drop the email channel
        // for every recipient below but keep the in-app notification.
        $noEmail = in_array($type, self::NO_EMAIL_TYPES, true);

        // Notify the user who created/owns the policy (agent or staff)
        if ($policy->added_by) {
            self::send(
                $policy->added_by,
                $type,
                $data,
                $action,
                $noEmail
                    ? array_values(array_diff(self::resolveChannels($type), ['email']))
                    : null
            );
        }

        // Notify agent if different from added_by
        if ($policy->agent_id && $policy->agentEmail) {
            // Agent gets in-app + email
            self::send(
                $policy->agent_id,
                $type,
                $data,
                $action,
                $noEmail ? ['in_app'] : ['in_app', 'email'],
                ['email' => $policy->agentEmail, 'phone' => $policy->agentPhone]
            );
        }
    }

    /**
     * Resolve channels from notification_templates table, or fall back to defaults.
     */
    private static function resolveChannels(string $type): array
    {
        $template = DB::table('notification_templates')
            ->where('type', $type)
            ->where('active', 1)
            ->first();

        if ($template && $template->channels) {
            return json_decode($template->channels, true) ?: self::DEFAULT_CHANNELS;
        }

        return self::DEFAULT_CHANNELS;
    }

    private static function sendInApp(int $userId, string $type, array $data, ?string $action): void
    {
        DB::connection('mysql_system')->table('notifications')->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'data'       => json_encode($data),
            'action'     => $action,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function sendEmail(string $email, array $data): void
    {
        $title   = $data['title'] ?? 'Notification';
        $message = $data['message'] ?? '';
        $html    = "<h3>{$title}</h3><p>{$message}</p>";

        if (isset($data['policy_number'])) {
            $html .= "<p><strong>Policy:</strong> {$data['policy_number']}</p>";
        }

        // A caller-supplied subject (e.g. the write-off "WRITE OFF - …" format)
        // takes precedence over the generic "Alpha Direct | {title}" subject.
        $subject = !empty($data['subject']) ? $data['subject'] : "Alpha Direct | {$title}";

        event(new \AlphaDirect\Events\SendMail(
            $email,
            $subject,
            '',
            $html,
            null,
            $data
        ));
    }

    private static function sendSms(string $phone, array $data): void
    {
        $message = $data['message'] ?? $data['title'] ?? 'Alpha Direct notification';

        // Ensure Botswana prefix
        if (!str_starts_with($phone, '+')) {
            $phone = '+267' . ltrim($phone, '0');
        }

        event(new \AlphaDirect\Events\SendSms($phone, $message, $data));
    }

    private static function getUserEmail(int $userId): ?string
    {
        return DB::table('users')->where('id', $userId)->value('email');
    }

    private static function getUserPhone(int $userId): ?string
    {
        return DB::table('users')->where('id', $userId)->value('cellphone');
    }

    private static function logDispatch(int $userId, string $type, string $channel, array $data): int
    {
        return DB::table('notification_logs')->insertGetId([
            'user_id'    => $userId,
            'type'       => $type,
            'channel'    => $channel,
            'data'       => json_encode($data),
            'status'     => 'dispatched',
            'created_at' => now(),
        ]);
    }

    private static function updateLog(int $logId, string $status, ?string $reason = null): void
    {
        DB::table('notification_logs')->where('id', $logId)->update([
            'status' => $status,
            'reason' => $reason,
            'updated_at' => now(),
        ]);
    }

    private static function typeTitle(string $type): string
    {
        return match ($type) {
            'policy_activated'  => 'Policy Activated',
            'policy_approved'   => 'Policy Approved',
            'policy_cancelled'  => 'Policy Cancelled',
            'policy_renewed'    => 'Policy Renewed',
            'policy_issued'     => 'Policy Issued',
            'claim_created'     => 'New Claim Created',
            'claim_settled'     => 'Claim Settled',
            'payment_received'  => 'Payment Received',
            'payment_failed'    => 'Payment Failed',
            'renewal_due'       => 'Renewal Due',
            'kyc_expired'       => 'KYC Expired',
            'document_ready'    => 'Document Ready',
            default             => ucwords(str_replace('_', ' ', $type)),
        };
    }

    private static function typeMessage(string $type, object $policy): string
    {
        $name = trim(($policy->firstName ?? '') . ' ' . ($policy->lastName ?? ''));
        $num  = $policy->policyNumber ?? "#{$policy->id}";

        return match ($type) {
            'policy_activated'  => "Policy {$num} for {$name} has been activated.",
            'policy_approved'   => "Policy {$num} has been approved.",
            'policy_cancelled'  => "Policy {$num} has been cancelled.",
            'policy_renewed'    => "Policy {$num} has been renewed.",
            'policy_issued'     => "Policy {$num} has been issued.",
            'claim_created'     => "A new claim has been created for policy {$num}.",
            'payment_received'  => "Payment received for policy {$num}.",
            'payment_failed'    => "Payment failed for policy {$num} — customer: {$name}.",
            'renewal_due'       => "Policy {$num} is due for renewal.",
            default             => "Notification for policy {$num}.",
        };
    }
}
