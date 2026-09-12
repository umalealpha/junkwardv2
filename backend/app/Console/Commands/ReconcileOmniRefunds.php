<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\RefundRequest;
use AlphaDirect\Services\Refunds\OmniHandoffService;
use AlphaDirect\Services\Refunds\RefundRequestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * refunds:reconcile-omni — the Customer Refund Engine's reconciliation
 * backstop (the GRA-0203 failure class: money moved, callback lost).
 *
 * Mirrors the RecoverRealpaySuccessMissingTx discipline:
 *   - No --commit  => DRY-RUN: writes nothing, prints what WOULD be done.
 *   - --commit     => performs the fixes. Every path is idempotent, so a
 *                     re-run (or a race with a late Omni callback) never
 *                     double-sends or double-posts.
 *   - --request=RFND-000123 => restrict to one request for a spot-check.
 *
 * What it reconciles:
 *   1. RETRY failed handoffs — approved/cfo_approved with omni_status=failed
 *      re-sent to Omni (idempotent on graphite_ref; a duplicate is a 200
 *      "Already received" no-op on their side). Only when the omni_refunds
 *      integration is enabled.
 *   2. SELF-HEAL ledger posting — requests stuck in 'paid' (callback landed
 *      but the policy post failed mid-flight) re-run the same markPaid path;
 *      postExternalRefund de-dupes on REFUND-{payment_refund_id}.
 *   3. REPORT stale handoffs — handed_off with no paid callback after
 *      --stale-hours (default 24). VISIBILITY ONLY: Omni exposes no
 *      server-token "paid since X" endpoint yet (raised with their team,
 *      2026-07-25), so these need a human eye on the Omni queue until it
 *      exists. Their callback also fires exactly once with no retry — which
 *      is why this command exists.
 *
 * Scheduling: hourly in backend Kernel AND registered in the cron/ twin app
 * (the live scheduler runs from the graphite-cron container — a command only
 * here never fires in prod).
 */
class ReconcileOmniRefunds extends Command
{
    protected $signature = 'refunds:reconcile-omni
        {--commit : Actually perform fixes. Without this flag the command is a read-only dry-run.}
        {--request= : Restrict to a single graphite_ref (e.g. RFND-000123) for a spot-check.}
        {--stale-hours=24 : Age after which an unanswered handoff is reported as stale.}';

    protected $description = 'Reconcile Customer Refund Engine state with Omni: retry failed handoffs, self-heal unposted paid refunds, report stale handoffs (dry-run by default)';

    public function handle(RefundRequestService $service, OmniHandoffService $omni): int
    {
        $commit = (bool) $this->option('commit');
        $only   = trim((string) $this->option('request'));
        $stale  = max(1, (int) $this->option('stale-hours'));

        if (!$commit) {
            $this->warn('No --commit flag: nothing will be written — previewing what WOULD be done.');
        }

        $scope = fn () => RefundRequest::query()
            ->when($only !== '', fn ($q) => $q->where('graphite_ref', $only));

        // ── 1. Handoffs to (re)send ───────────────────────────────────────────
        // Includes NOT_SENT, not just FAILED. Every refund approved while the
        // integration was disabled parks as approved + not_sent; with only FAILED
        // in scope none of them ever flushed when the flag was switched on — they
        // sat approved forever and needed DB surgery to release. maybeSend() is
        // itself gated on the integration flag and on omni_status, so this is
        // still a no-op while the money leg is off.
        $failed = $scope()
            ->whereIn('status', [RefundRequest::STATUS_APPROVED, RefundRequest::STATUS_CFO_APPROVED])
            ->whereIn('omni_status', [RefundRequest::OMNI_FAILED, RefundRequest::OMNI_NOT_SENT])
            ->orderBy('id')->get();
        $retried = 0;
        foreach ($failed as $r) {
            if (!$commit) {
                $this->line("WOULD retry handoff: {$r->graphite_ref} ({$r->area}, P" . number_format((float) $r->refund_amount, 2) . ')');
                continue;
            }
            $result = $omni->maybeSend($r);
            $ok = (bool) ($result['sent'] ?? false);
            $retried += $ok ? 1 : 0;
            $this->line(($ok ? 'RETRIED' : 'RETRY FAILED') . ": {$r->graphite_ref}"
                . ($ok ? '' : ' — ' . ($result['reason'] ?? $result['skipped'] ?? 'unknown')));
        }

        // ── 2. Paid but not posted → re-run the idempotent paid path ────────
        $unposted = $scope()->where('status', RefundRequest::STATUS_PAID)
            ->orderBy('id')->get();
        $healed = 0;
        foreach ($unposted as $r) {
            if (!$commit) {
                $this->line("WOULD re-post ledger: {$r->graphite_ref} (paid " . ($r->omni_paid_at?->toDateTimeString() ?? '?') . ')');
                continue;
            }
            try {
                $r2 = $service->markPaid($r, ['fnb_reference' => (string) $r->omni_paid_ref], 'reconcile');
                $ok = $r2->status === RefundRequest::STATUS_POSTED;
                $healed += $ok ? 1 : 0;
                $this->line(($ok ? 'POSTED' : 'STILL PAID (posting failed — see log)') . ": {$r->graphite_ref}");
            } catch (\Throwable $e) {
                $this->error("RE-POST FAILED: {$r->graphite_ref} — " . $e->getMessage());
            }
        }

        // ── 3. Stale handoffs (visibility only — no Omni poll endpoint yet) ─
        $staleRows = $scope()->where('status', RefundRequest::STATUS_HANDED_OFF)
            ->where('handed_off_at', '<', now()->subHours($stale))
            ->orderBy('handed_off_at')->get();
        foreach ($staleRows as $r) {
            $this->warn("STALE: {$r->graphite_ref} handed off " . $r->handed_off_at?->toDateTimeString()
                . " — no paid callback after {$stale}h. Check the Omni Finance queue "
                . '(their callback fires once with no retry).');
        }

        // Orphan check: succeeded omni payment_refunds whose request never posted.
        $orphans = DB::table('payment_refunds')
            ->where('source', 'omni')->where('status', 'succeeded')
            ->whereNotIn('refund_request_id', function ($q) {
                $q->select('id')->from('refund_requests')->where('status', RefundRequest::STATUS_POSTED);
            })
            ->when($only !== '', fn ($q) => $q->where('graphite_ref', $only))
            ->count();

        $summary = sprintf(
            'reconcile-omni%s: handoff-retries=%d/%d, ledger-heals=%d/%d, stale=%d, succeeded-not-posted=%d',
            $commit ? '' : ' (dry-run)',
            $retried, $failed->count(), $healed, $unposted->count(), $staleRows->count(), $orphans
        );
        $this->info($summary);
        Log::info('refunds:reconcile-omni summary', ['summary' => $summary, 'commit' => $commit]);

        if (!$commit && ($failed->count() || $unposted->count())) {
            $this->warn('Dry-run only. Re-run with --commit to fix. Spot-check one first: --request=RFND-000123 --commit');
        }
        return self::SUCCESS;
    }
}
