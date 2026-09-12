<?php

namespace AlphaDirect\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * UW approval SLA escalation (CFO 2026-08-27).
 *
 * When a new-business policy sits in the manager-approval queue
 * (`policy_actions.status = IN_APPROVAL`) past the SLA, email a summary to the
 * underwriting managers so it does not sit unactioned. Detection + a daily
 * digest — the "don't let approvals sit" control the CFO asked for.
 *
 * SAFETY — recipients are an EXPLICIT list, never a broad permission. The
 * `policy_approved` permission is held by 2,133 accounts (customers/agents
 * included), so gating on it would spam thousands. Recipients come from
 * UW_APPROVAL_SLA_RECIPIENTS (comma-separated); if it is empty the command logs
 * and sends NOTHING — fail-safe, never a mass blast. Read-only on policy data.
 *
 * Scheduled daily (see Console\Kernel). Idempotent: one daily run, one email.
 *     php artisan uw:approval-sla            # dry-run (prints, sends nothing)
 *     php artisan uw:approval-sla --send     # send the digest
 */
class UwApprovalSlaEscalate extends Command
{
    protected $signature = 'uw:approval-sla {--send : Actually send the digest (default is dry-run)}';

    protected $description = 'Email UW managers about policy approvals sitting past the SLA.';

    public function handle(): int
    {
        // Dark behind the `uw_bottleneck` runtime flag (Admin > Integrations):
        // the daily digest no-ops until the dashboard feature is switched on.
        if (!IntegrationSettings::isEnabled('uw_bottleneck', false)) {
            return self::SUCCESS;
        }

        $slaDays = (int) env('UW_APPROVAL_SLA_DAYS', 3);

        // updated_at is the "sitting" clock, not created_at: rows mutate in place
        // (a quote is submitted by flipping status), so created_at is when the
        // quote was raised. policy_actions is soft-deleted — DB::table bypasses
        // the scope, so exclude deleted rows explicitly.
        $breaches = DB::table('policy_actions')
            ->leftJoin('policies', 'policies.id', '=', 'policy_actions.policy_id')
            ->whereNull('policy_actions.deleted_at')
            ->where('policy_actions.status', 'IN_APPROVAL')
            ->whereRaw('DATEDIFF(NOW(), policy_actions.updated_at) >= ?', [$slaDays])
            ->orderBy('policy_actions.updated_at')
            ->get([
                'policies.policyNumber as policy_no',
                'policy_actions.annual_premium as premium',
                'policy_actions.updated_at as since',
            ]);

        $count = $breaches->count();
        $premium = (float) $breaches->sum('premium');
        $this->info("UW approval SLA ({$slaDays}d): {$count} breach(es), premium " . round($premium));

        if ($count === 0) {
            return self::SUCCESS;
        }

        $recipients = collect(explode(',', (string) env('UW_APPROVAL_SLA_RECIPIENTS', '')))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => $e !== '' && str_contains($e, '@'))
            ->values();

        if ($recipients->isEmpty()) {
            $this->warn('No UW_APPROVAL_SLA_RECIPIENTS configured — nothing sent (fail-safe).');
            Log::info('uw:approval-sla — breaches found but no recipients configured; skipped.');
            return self::SUCCESS;
        }

        if (!$this->option('send')) {
            $this->line('[dry-run] would email ' . $recipients->implode(', ') . ' — re-run with --send.');
            return self::SUCCESS;
        }

        $html = $this->buildHtml($breaches, $slaDays, $count, $premium);
        $subject = "Underwriting: {$count} approval(s) waiting over {$slaDays} days";

        foreach ($recipients as $to) {
            try {
                event(new \AlphaDirect\Events\SendMail(
                    $to, $subject, '', $html, '', ['hook' => 'uw_approval_sla']
                ));
            } catch (\Throwable $e) {   // one bad address must not lose the rest
                Log::warning("uw:approval-sla send failed for {$to}: " . $e->getMessage());
            }
        }
        $this->info('Sent to ' . $recipients->count() . ' recipient(s).');
        return self::SUCCESS;
    }

    private function buildHtml($breaches, int $slaDays, int $count, float $premium): string
    {
        $rows = '';
        $today = Carbon::today();
        foreach ($breaches as $b) {
            $age = $b->since ? Carbon::parse($b->since)->startOfDay()->diffInDays($today) : 0;
            $rows .= '<tr>'
                . '<td style="padding:6px 10px;border-bottom:1px solid #e5e7eb;">' . e($b->policy_no ?? '—') . '</td>'
                . '<td style="padding:6px 10px;border-bottom:1px solid #e5e7eb;text-align:right;">P ' . number_format((float) $b->premium) . '</td>'
                . '<td style="padding:6px 10px;border-bottom:1px solid #e5e7eb;text-align:right;">' . $age . ' days</td>'
                . '</tr>';
        }

        return '<div style="font-family:Arial,Helvetica,sans-serif;color:#1F2A37;">'
            . '<h2 style="color:#0D1B2A;">Underwriting approvals waiting too long</h2>'
            . '<p>' . $count . ' policy application(s) have been waiting more than ' . $slaDays
            . ' days for underwriting approval — total annual premium P ' . number_format($premium) . '.</p>'
            . '<table style="border-collapse:collapse;width:100%;font-size:14px;">'
            . '<tr style="background:#0D1B2A;color:#fff;text-align:left;">'
            . '<th style="padding:6px 10px;">Policy</th><th style="padding:6px 10px;text-align:right;">Premium</th>'
            . '<th style="padding:6px 10px;text-align:right;">Waiting</th></tr>'
            . $rows . '</table>'
            . '<p style="margin-top:14px;">Open the Underwriting Bottleneck dashboard to action these.</p></div>';
    }
}
