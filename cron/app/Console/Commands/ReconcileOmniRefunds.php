<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * refunds:reconcile-omni (CRON TWIN) — Customer Refund Engine backstop.
 *
 * The graphite-cron container runs THIS Laravel app, not backend/ — a command
 * only in backend/ never fires in prod (the GRA-0117 lesson). This twin is
 * deliberately THIN: it monitors stuck refund states and, with --commit,
 * re-triggers the backend's own idempotent paid-callback endpoint rather than
 * carrying a second copy of the ledger-posting logic (cron/ copies of money
 * code drift — the exact failure class we're avoiding).
 *
 * What it does:
 *   1. MONITOR (always): counts + logs approved/cfo_approved requests whose
 *      Omni handoff failed, 'paid' requests whose ledger post hasn't landed,
 *      and handed_off requests with no paid callback after --stale-hours.
 *      Handoff RETRIES are backend-only (need the full service chain) — run
 *      backend `php artisan refunds:reconcile-omni --commit` for those.
 *   2. SELF-HEAL (--commit): for each 'paid'-but-not-posted request, POST the
 *      same paid payload to our own backend webhook
 *      ({GRAPHITE_BACKEND_URL}/api/v1/webhooks/omni/refund-paid, bearer
 *      OMNI_REFUND_CALLBACK_TOKEN). The backend handler is idempotent on
 *      graphite_ref and REFUND-{id}, so a replay can never double-post.
 *      Skips loudly if the env pair isn't configured.
 *
 * Dry-run by default. Requires the refund_requests table (no-ops before the
 * engine's migrations run).
 */
class ReconcileOmniRefunds extends Command
{
    protected $signature = 'refunds:reconcile-omni
        {--commit : Re-trigger the backend paid-callback for paid-but-unposted refunds.}
        {--stale-hours=24 : Age after which an unanswered handoff is reported as stale.}';

    protected $description = 'Customer Refund Engine backstop (cron twin): monitor stuck Omni refunds; re-trigger the idempotent backend paid path';

    public function handle(): int
    {
        if (!Schema::hasTable('refund_requests')) {
            $this->info('refund_requests table not present yet — nothing to do.');
            return self::SUCCESS;
        }

        $commit = (bool) $this->option('commit');
        $stale  = max(1, (int) $this->option('stale-hours'));

        $failedHandoffs = DB::table('refund_requests')
            ->whereIn('status', ['approved', 'cfo_approved'])
            ->where('omni_status', 'failed')->whereNull('deleted_at')->count();

        $staleHandoffs = DB::table('refund_requests')
            ->where('status', 'handed_off')->whereNull('deleted_at')
            ->where('handed_off_at', '<', now()->subHours($stale))->count();

        $unposted = DB::table('refund_requests')
            ->where('status', 'paid')->whereNull('deleted_at')
            ->orderBy('id')->get(['id', 'graphite_ref', 'policy_number', 'refund_amount', 'omni_paid_ref']);

        $healed = 0;
        if ($commit && $unposted->count()) {
            $base  = rtrim((string) env('GRAPHITE_BACKEND_URL', ''), '/');
            $token = (string) env('OMNI_REFUND_CALLBACK_TOKEN', '');
            if ($base === '' || $token === '') {
                $this->warn('GRAPHITE_BACKEND_URL / OMNI_REFUND_CALLBACK_TOKEN not set on the cron task — cannot re-trigger; run the backend command instead.');
            } else {
                foreach ($unposted as $r) {
                    try {
                        $resp = Http::withToken($token)->timeout(30)->acceptJson()
                            ->post($base . '/api/v1/webhooks/omni/refund-paid', [
                                'graphite_ref'  => $r->graphite_ref,
                                'policy_number' => $r->policy_number,
                                'amount'        => (string) $r->refund_amount,
                                'fnb_reference' => (string) ($r->omni_paid_ref ?? ''),
                                'status'        => 'paid',
                            ]);
                        $ok = $resp->successful();
                        $healed += $ok ? 1 : 0;
                        $this->line(($ok ? 'RE-TRIGGERED' : 'RE-TRIGGER FAILED (' . $resp->status() . ')') . ': ' . $r->graphite_ref);
                    } catch (\Throwable $e) {
                        $this->error('RE-TRIGGER ERROR: ' . $r->graphite_ref . ' — ' . $e->getMessage());
                    }
                }
            }
        } elseif ($unposted->count()) {
            foreach ($unposted as $r) {
                $this->line('WOULD re-trigger paid path: ' . $r->graphite_ref);
            }
        }

        $summary = sprintf(
            'reconcile-omni cron twin%s: failed-handoffs=%d (backend-only retry), paid-not-posted=%d (re-triggered %d), stale-handoffs=%d',
            $commit ? '' : ' (dry-run)', $failedHandoffs, $unposted->count(), $healed, $staleHandoffs
        );
        $this->info($summary);
        Log::info('refunds:reconcile-omni (cron twin) summary', ['summary' => $summary, 'commit' => $commit]);

        if ($failedHandoffs > 0) {
            Log::warning("refunds:reconcile-omni: {$failedHandoffs} refund handoff(s) to Omni FAILED — run backend 'php artisan refunds:reconcile-omni --commit' to retry.");
        }
        if ($staleHandoffs > 0) {
            Log::warning("refunds:reconcile-omni: {$staleHandoffs} handoff(s) stale >{$stale}h with no Omni paid callback — check the Omni Finance queue (their callback fires once, no retry).");
        }
        return self::SUCCESS;
    }
}
