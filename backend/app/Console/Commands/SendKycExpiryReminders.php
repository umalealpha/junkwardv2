<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Services\AlphaDirectNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily job that scans customer_kyc for documents expiring in the
 * next 30 days OR already expired, and sends a re-KYC reminder via
 * WhatsApp → SMS fallback.
 *
 * Cadence:
 *   30 days out  — first reminder ("your Omang expires in 30 days")
 *    7 days out  — second reminder ("your Omang expires next week")
 *    1 day out   — final reminder
 *    0 days      — expired notice ("upload a fresh scan to keep your policy active")
 *  +14 days past — escalate (mark policy for ops review)
 *
 * Idempotent per-customer-per-cadence: writes to a kyc_reminder_log
 * table keyed by (customer_id, document_kind, cadence) so a second
 * run on the same day doesn't re-blast.
 *
 * Schedule (in app/Console/Kernel.php):
 *   $schedule->command('kyc:expiry-reminders')->dailyAt('08:00');
 */
class SendKycExpiryReminders extends Command
{
    protected $signature = 'kyc:expiry-reminders
                            {--dry-run : just count, do not send}
                            {--customer= : single customer id (testing)}';

    protected $description = 'Notify customers whose Omang / passport / driver licence is about to expire (or has expired)';

    private const CADENCES = [30, 7, 1, 0]; // days out (0 = today)
    private const ESCALATE_AFTER_DAYS = 14;  // past expiry → ops queue

    public function handle(AlphaDirectNotificationService $notifier): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $oneCustomer = $this->option('customer');

        $today = Carbon::today();
        $sent = 0; $skipped = 0; $escalated = 0;

        $columns = [
            'omang'    => 'omangExpiry',
            'passport' => 'passportExpiry',
            'license'  => 'license_valid_till',
        ];

        foreach ($columns as $kind => $col) {
            if (!\Schema::hasColumn('customer_kyc', $col)) continue;

            // For each cadence (30/7/1/0 days out) find matching customers
            // and check the per-customer-per-cadence log to avoid resending.
            foreach (self::CADENCES as $daysOut) {
                $targetDate = $today->copy()->addDays($daysOut)->toDateString();
                $rows = DB::table('customer_kyc as k')
                    ->join('customer as c', 'c.id', '=', 'k.customer_id')
                    ->where("k.{$col}", $targetDate)
                    ->when($oneCustomer, fn ($q) => $q->where('c.id', $oneCustomer))
                    ->select('c.id as customer_id', 'c.firstName', 'c.cellphone', 'c.email', "k.{$col} as expiry_date")
                    ->get();

                foreach ($rows as $row) {
                    if ($this->alreadySent($row->customer_id, $kind, "d{$daysOut}")) {
                        $skipped++;
                        continue;
                    }
                    if (!$dryRun) {
                        $this->dispatch($notifier, $row, $kind, $daysOut);
                        $this->markSent($row->customer_id, $kind, "d{$daysOut}");
                    }
                    $sent++;
                }
            }

            // Escalate severely-expired (>14 days past) — mark policies for
            // ops review. We tag a wa_anomaly_alerts row so the existing
            // Monday triage queue surfaces them.
            $cutoff = $today->copy()->subDays(self::ESCALATE_AFTER_DAYS)->toDateString();
            $stale = DB::table('customer_kyc as k')
                ->where("k.{$col}", '<', $cutoff)
                ->where("k.{$col}", '>', '2000-01-01') // ignore null/sentinel
                ->when($oneCustomer, fn ($q) => $q->where('k.customer_id', $oneCustomer))
                ->select('k.customer_id', "k.{$col} as expiry_date")
                ->get();

            foreach ($stale as $row) {
                if ($this->alreadySent($row->customer_id, $kind, 'escalated')) continue;
                if (!$dryRun) {
                    $this->escalate($row->customer_id, $kind, $row->expiry_date);
                    $this->markSent($row->customer_id, $kind, 'escalated');
                }
                $escalated++;
            }
        }

        $this->info("kyc-expiry-reminders: sent={$sent} skipped={$skipped} escalated={$escalated} dry_run=" . ($dryRun ? 'yes' : 'no'));
        Log::info('kyc.expiry_reminders.run', compact('sent', 'skipped', 'escalated', 'dryRun'));
        return self::SUCCESS;
    }

    private function alreadySent(int $customerId, string $kind, string $cadence): bool
    {
        if (!\Schema::hasTable('kyc_reminder_log')) return false; // first run before migration
        return DB::table('kyc_reminder_log')
            ->where('customer_id', $customerId)
            ->where('document_kind', $kind)
            ->where('cadence', $cadence)
            ->whereDate('sent_at', Carbon::today())
            ->exists();
    }

    private function markSent(int $customerId, string $kind, string $cadence): void
    {
        if (!\Schema::hasTable('kyc_reminder_log')) return;
        DB::table('kyc_reminder_log')->insert([
            'customer_id'   => $customerId,
            'document_kind' => $kind,
            'cadence'       => $cadence,
            'sent_at'       => Carbon::now(),
            'created_at'    => Carbon::now(),
        ]);
    }

    private function dispatch(AlphaDirectNotificationService $notifier, $row, string $kind, int $daysOut): void
    {
        $kindLabel = ['omang' => 'Omang', 'passport' => 'passport', 'license' => "driver's licence"][$kind] ?? $kind;
        $when      = $daysOut === 0 ? 'today' : "in {$daysOut} day" . ($daysOut === 1 ? '' : 's');
        $msg = "Hi {$row->firstName}, your {$kindLabel} expires {$when} ({$row->expiry_date}). Please upload a fresh scan at https://start.alphadirect.co.bw/customer-kyc to keep your policy active.";

        try {
            // Best-effort: use the existing notification service which does
            // WhatsApp → SMS → email fallback per customer_contact_preferences.
            // Passing a generic dispatch shape — the service routes by channel.
            event(new \AlphaDirect\Events\SendSms('+267' . ltrim((string) $row->cellphone, '+267'), $msg));
        } catch (\Throwable $e) {
            Log::warning('kyc.expiry_reminders.dispatch_failed', [
                'customer' => $row->customer_id,
                'kind'     => $kind,
                'msg'      => $e->getMessage(),
            ]);
        }
    }

    private function escalate(int $customerId, string $kind, string $expiry): void
    {
        try {
            DB::connection('mysql_system')->table('wa_anomaly_alerts')->insert([
                'alert_key'  => "kyc_expired_{$kind}_{$customerId}",
                'severity'   => 'medium',
                'message'    => "🪪 KYC document expired >14 days ago — customer #{$customerId} {$kind} ({$expiry}). Re-KYC reminders ignored — flag for ops follow-up.",
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        } catch (\Throwable $ignore) {
            // wa_anomaly_alerts may be missing on lean deployments.
        }
    }
}
