<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Services\NotificationDispatcher;
use AlphaDirect\Models\CronStatus;
use Carbon\Carbon;

/**
 * Renewal notification pipeline — sends staged reminders to customers, agents, and sales heads.
 *
 * Stages: 30 → 15 → 7 → 0 (expiry day) → -7 (overdue)
 * Channels: WhatsApp → Email → SMS (via NotificationDispatcher)
 *
 * Also notifies:
 *  - Agent: when their policy is due for renewal
 *  - Sales head: daily summary of overdue/missed renewals
 *
 * Runs daily via cron.
 */
class RenewalNotifications extends Command
{
    protected $signature   = 'renewal:notify {--dry-run : Preview without sending}';
    protected $description = 'Send renewal reminders to customers, agents, and sales heads';

    private const REMINDER_DAYS = [30, 15, 7, 0, -7]; // positive = before expiry, 0 = expiry day, negative = overdue

    public function handle(): int
    {
        $cron = new CronStatus();
        $cron->name  = 'renewal:notify';
        $cron->start = Carbon::now();
        $cron->save();

        $dryRun = $this->option('dry-run');
        $today  = Carbon::today()->toDateString();
        $sent   = 0;

        foreach (self::REMINDER_DAYS as $daysBefore) {
            $targetDate = Carbon::today()->addDays($daysBefore)->toDateString();
            $stage = $this->stageLabel($daysBefore);

            // Find policies expiring on target date that haven't been notified for this stage
            $policies = DB::select("
                SELECT
                    p.id as policy_id,
                    p.policyNumber,
                    p.premium,
                    p.agent_id,
                    p.customer_id,
                    p.added_by,
                    pa.effective_to as expiry_date,
                    CONCAT(c.firstName, ' ', c.lastName) as customer_name,
                    c.cellphone as customer_phone,
                    c.email as customer_email,
                    CONCAT(ag.firstName, ' ', ag.lastName) as agent_name,
                    ag.email as agent_email,
                    ag.cellPhone as agent_phone,
                    prod.name as product_name,
                    pr.new_premium,
                    pr.old_premium,
                    pr.is_rated
                FROM policies p
                JOIN policy_actions pa ON pa.id = (
                    SELECT pa2.id FROM policy_actions pa2
                    WHERE pa2.policy_id = p.id
                      AND pa2.status = 'ISSUED'
                      AND pa2.deleted_at IS NULL
                    ORDER BY pa2.id DESC LIMIT 1
                )
                LEFT JOIN customer c ON c.id = p.customer_id
                -- V2 stores agents as rows in the users table (with agency_id
                -- set). The V1-era agents table was never migrated to the V2
                -- schema, so JOINing it threw SQLSTATE 42S02 and the entire
                -- renewal-notify run aborted. See CalculateCommissions.php:199
                -- for the canonical V2 pattern.
                LEFT JOIN users ag ON ag.id = p.agent_id
                LEFT JOIN products prod ON prod.id = p.product_id
                LEFT JOIN policy_renewals pr ON pr.policy_id = p.id AND pr.is_renewed = 0
                WHERE p.status = 1
                  AND DATE(pa.effective_to) = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM notification_logs nl
                      WHERE nl.type = ?
                        AND JSON_EXTRACT(nl.data, '$.policy_id') = p.id
                        AND nl.status IN ('sent', 'dispatched')
                  )
            ", [$targetDate, "renewal_{$stage}"]);

            $this->info("{$stage}: {$this->count($policies)} policies expiring on {$targetDate}");

            foreach ($policies as $policy) {
                if ($dryRun) {
                    $this->line("  [DRY] {$policy->policyNumber} → {$policy->customer_name} ({$policy->customer_email})");
                    continue;
                }

                $data = [
                    'title'         => $this->notificationTitle($daysBefore, $policy),
                    'message'       => $this->notificationMessage($daysBefore, $policy),
                    'policy_id'     => $policy->policy_id,
                    'policy_number' => $policy->policyNumber,
                    'expiry_date'   => $policy->expiry_date,
                    'premium'       => $policy->premium,
                    'new_premium'   => $policy->new_premium,
                    'customer_name' => $policy->customer_name,
                ];

                // 1. Notify customer (WhatsApp → Email → SMS)
                if ($policy->customer_email || $policy->customer_phone) {
                    $channels = ['email'];
                    if ($policy->customer_phone) $channels[] = 'sms';

                    NotificationDispatcher::send(
                        $policy->added_by ?? 0,
                        "renewal_{$stage}",
                        $data,
                        "/policies/{$policy->policy_id}",
                        array_merge(['in_app'], $channels),
                        [
                            'email' => $policy->customer_email,
                            'phone' => $policy->customer_phone,
                        ]
                    );
                    $sent++;
                }

                // 2. Notify agent
                if ($policy->agent_id) {
                    NotificationDispatcher::send(
                        $policy->agent_id,
                        "renewal_{$stage}_agent",
                        array_merge($data, [
                            'title'   => "Renewal Due: {$policy->policyNumber}",
                            'message' => "{$policy->customer_name}'s policy {$policy->policyNumber} expires on {$policy->expiry_date}. Please follow up.",
                        ]),
                        "/policies/{$policy->policy_id}",
                        ['in_app', 'email'],
                        ['email' => $policy->agent_email, 'phone' => $policy->agent_phone]
                    );
                }

                // 3. On overdue: escalate to sales head
                if ($daysBefore <= 0) {
                    $this->notifySalesHead($policy, $stage, $data);
                }
            }
        }

        $this->info("Total notifications sent: {$sent}");
        Log::info("renewal:notify completed. Sent: {$sent}");

        $cron->end = Carbon::now();
        $cron->save();

        return 0;
    }

    private function notifySalesHead(object $policy, string $stage, array $data): void
    {
        // Find users with 'sales-head' or 'admin' role
        $salesHeads = DB::table('users')
            ->join('model_has_roles', function ($j) {
                $j->on('model_has_roles.model_id', '=', 'users.id')
                  ->where('model_has_roles.model_type', 'AlphaDirect\\User');
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', ['sales-head', 'Sales Head', 'admin', 'Super Admin'])
            ->select('users.id', 'users.email')
            ->distinct()
            ->get();

        foreach ($salesHeads as $head) {
            NotificationDispatcher::send(
                $head->id,
                "renewal_{$stage}_escalation",
                array_merge($data, [
                    'title'   => "OVERDUE Renewal: {$policy->policyNumber}",
                    'message' => "Policy {$policy->policyNumber} ({$policy->customer_name}) is overdue for renewal. Agent: {$policy->agent_name}. Immediate action required.",
                ]),
                "/renewals/dashboard",
                ['in_app', 'email'],
                ['email' => $head->email]
            );
        }
    }

    private function stageLabel(int $days): string
    {
        return match (true) {
            $days === 30 => '30_day',
            $days === 15 => '15_day',
            $days === 7  => '7_day',
            $days === 0  => 'expiry_day',
            $days < 0    => 'overdue',
            default      => "{$days}_day",
        };
    }

    private function notificationTitle(int $days, object $policy): string
    {
        $num = $policy->policyNumber;
        return match (true) {
            $days === 30 => "Renewal Reminder: {$num} expires in 30 days",
            $days === 15 => "Renewal Reminder: {$num} expires in 15 days",
            $days === 7  => "URGENT: {$num} expires in 7 days",
            $days === 0  => "EXPIRY TODAY: {$num}",
            $days < 0    => "OVERDUE: {$num} has expired",
            default      => "Renewal: {$num}",
        };
    }

    private function notificationMessage(int $days, object $policy): string
    {
        $name = $policy->customer_name;
        $num  = $policy->policyNumber;
        $date = $policy->expiry_date;
        $prem = number_format((float) $policy->premium, 2);

        $base = "Dear {$name}, your policy {$num} (Premium: P{$prem})";

        if ($policy->is_rated && $policy->new_premium > $policy->old_premium) {
            $newPrem = number_format((float) $policy->new_premium, 2);
            $base .= " has been re-rated to P{$newPrem}. Your consent is required for the new rate.";
        }

        return match (true) {
            $days === 30 => "{$base} is due for renewal on {$date}. Please ensure your payment details are up to date.",
            $days === 15 => "{$base} expires on {$date}. Please renew to maintain your cover.",
            $days === 7  => "{$base} expires in 7 days ({$date}). URGENT: Renew now to avoid a lapse in cover.",
            $days === 0  => "{$base} expires TODAY. Renew immediately to stay covered.",
            $days < 0    => "{$base} has EXPIRED. Your cover has lapsed. Contact us to reinstate.",
            default      => "{$base} is due for renewal.",
        };
    }

    private function count($arr): int
    {
        return is_countable($arr) ? count($arr) : 0;
    }
}
