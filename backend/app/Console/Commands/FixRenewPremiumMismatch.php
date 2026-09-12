<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\PolicyAction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-off remediation: correct DOM/COM RENEW actions whose stored premium does
 * NOT match their full-period source (the latest non-deleted ISSUED action with
 * an earlier effective_from). Applies the canonical verbatim rule
 * (setRenewPremiumFromSource) retroactively — the same thing the cron/backend
 * now do at renewal time — and optionally regenerates the RENEW invoice.
 *
 * SAFETY:
 *   - DRY-RUN by default. Nothing is written unless --apply is passed.
 *   - Processes oldest-first (effective_from, id ASC) so a renewal chained onto
 *     an earlier wrong renewal is corrected only after its source is fixed.
 *   - Source is the nearest previous ISSUED action. A FULL-PERIOD source
 *     (renew/anniversary/newbusiness) is copied VERBATIM, but only within the
 *     SAME FREQUENCY (compared by frequency, not exact days — calendar quarters
 *     vary 90/91/92 days). An ENDORSE source means an endorse sits between this
 *     renew and the previous period, so the renew is recomputed to the ENDORSED
 *     full-period VALUE (not the pro-rata delta) via setRenewPremiumFromSource.
 *   - OVERLAPPING-PERIOD guard: a policy with two ISSUED RENEW rows covering the
 *     same span is a data anomaly the source logic cannot resolve — every renew
 *     on it is skipped and the policy is listed for manual review, never written.
 *   - --regenerate-invoice is opt-in (ledger writes).
 *
 * Usage:
 *   php artisan renewals:fix-premium-mismatch                 # dry-run, all
 *   php artisan renewals:fix-premium-mismatch --policy=127503 # dry-run, one
 *   php artisan renewals:fix-premium-mismatch --apply         # write premiums
 *   php artisan renewals:fix-premium-mismatch --apply --regenerate-invoice
 */
class FixRenewPremiumMismatch extends Command
{
    protected $signature = 'renewals:fix-premium-mismatch
        {--apply : Write the corrections (omit for a dry-run)}
        {--regenerate-invoice : Also delete + regenerate the RENEW invoice}
        {--policy= : Limit to a single policy id or number}
        {--limit=0 : Cap the number of actions processed (0 = no cap)}';

    protected $description = 'Correct DOM/COM RENEW premiums that do not match their full-period source (verbatim rule).';

    private const FULL_PERIOD = ['NEWBUSINESS', 'ANNIVERSARY-RENEW', 'RENEW', 'REINSTATE', 'REISSUE'];
    private const INVOICE_TYPES = ['Invoice', 'Invoice VAT', 'Invoice Premium'];

    public function handle(): int
    {
        $apply       = (bool) $this->option('apply');
        $regenInvoice = (bool) $this->option('regenerate-invoice');
        $policyOpt   = trim((string) $this->option('policy'));
        $limit       = (int) $this->option('limit');

        DB::connection()->disableQueryLog();

        $this->info($apply ? '*** APPLY MODE — writing corrections ***' : '--- DRY RUN (no writes) — pass --apply to write ---');

        // Candidate RENEW actions, oldest-first so chains cascade correctly.
        $renews = PolicyAction::query()
            ->join('policies as p', 'p.id', '=', 'policy_actions.policy_id')
            ->whereIn('p.product_id', [7, 8])
            ->where('policy_actions.transaction_type', 'RENEW')
            ->where('policy_actions.status', 'ISSUED')
            ->whereNull('policy_actions.deleted_at')
            ->when($policyOpt !== '', function ($q) use ($policyOpt) {
                $q->where(function ($w) use ($policyOpt) {
                    $w->where('p.id', is_numeric($policyOpt) ? (int) $policyOpt : 0)
                        ->orWhere('p.policyNumber', $policyOpt);
                });
            })
            ->orderBy('policy_actions.policy_id')
            ->orderBy('policy_actions.effective_from')
            ->orderBy('policy_actions.id')
            ->select('policy_actions.*', 'p.policyNumber')
            ->get();

        $checked = 0; $fixed = 0; $skipped = 0; $invoices = 0; $endorseDry = 0;
        $overlapSkipped = 0; $overlapReview = [];

        // Overlapping-period guard. Some policies carry TWO (or more) ISSUED
        // RENEW rows whose date ranges overlap — a data anomaly that violates the
        // one-renew-per-period assumption the source logic depends on. On such a
        // policy the "nearest previous ISSUED action" is ambiguous and the fix
        // writes oscillating wrong values (e.g. 1,710 ↔ 1,777.50 ↔ 1,881). We
        // detect these policies up-front (in memory, no extra DB round-trips) and
        // skip EVERY renew on them, listing the policies for manual review instead.
        $overlapPolicies = static::detectOverlappingRenewPolicies($renews);

        foreach ($renews as $renew) {
            if ($limit > 0 && $checked >= $limit) break;
            $checked++;

            // Overlapping-period policy — never auto-fix; hand to manual review.
            if (isset($overlapPolicies[$renew->policy_id])) {
                $overlapSkipped++;
                $overlapReview[$renew->policyNumber] = true;
                continue;
            }

            // Source = the NEAREST PREVIOUS PERIOD's ISSUED action — order by
            // effective_from DESC (id DESC only as a same-date tiebreak), NOT by
            // id. A pure id-DESC pick grabs a later-CREATED but earlier-dated
            // action (a back-dated re-issue / out-of-order RENEW), which is not
            // this renewal's real predecessor and would copy the wrong premium
            // onto a correct row. Re-read fresh each time so an already-corrected
            // earlier renew in the same chain is seen.
            $source = PolicyAction::where('policy_id', $renew->policy_id)
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->where('effective_from', '<', $renew->effective_from)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if (!$source) { $skipped++; continue; }

            $endorseSource = !in_array($source->transaction_type, self::FULL_PERIOD, true);

            // Frequency gate applies ONLY to the verbatim (full-period) copy: a
            // quarterly renew must not inherit a monthly period, or vice-versa.
            // Compare by FREQUENCY, not exact days — calendar quarters vary
            // 90/91/92 days and months 28-31, so a day-equality guard wrongly
            // rejects a valid same-frequency copy. An endorse source is recomputed
            // to a full period, so its own partial span is irrelevant there.
            if (!$endorseSource && !static::sameFrequencyPeriod($renew, $source)) {
                $skipped++;
                continue;
            }

            $before = (float) $renew->premium;

            // A full-period source's value can be previewed (its premium verbatim).
            // An ENDORSE source means the renew must carry the ENDORSED full-period
            // VALUE (not the pro-rata delta); that figure is only derivable by
            // recompute (a write), so dry-run states the intent rather than a number.
            if (!$endorseSource) {
                $target = (float) $source->premium;
                if (abs($before - $target) <= 0.01) { continue; } // already correct
            }

            if (!$apply) {
                if ($endorseSource) {
                    $endorseDry++;
                    $this->line(sprintf(
                        '%s  action %d  %s→%s  premium %s  =>  [recompute from endorse %d on --apply]',
                        $renew->policyNumber, $renew->id, $renew->effective_from, $renew->effective_to,
                        number_format($before, 2), $source->id
                    ));
                } else {
                    $fixed++;
                    $this->line(sprintf(
                        '%s  action %d  %s→%s  premium %s  =>  %s   (source %d %s)',
                        $renew->policyNumber, $renew->id, $renew->effective_from, $renew->effective_to,
                        number_format($before, 2), number_format($target, 2),
                        $source->id, $source->transaction_type
                    ));
                }
                continue;
            }

            // APPLY — the canonical rule handles BOTH cases:
            //   full-period source → verbatim copy (also sets annual_premium)
            //   endorse source     → recompute full-period value from the tree
            PolicyAction::setRenewPremiumFromSource($renew->id, $source);
            $after = (float) PolicyAction::where('id', $renew->id)->value('premium');

            if (abs($before - $after) <= 0.01) { continue; } // no net change
            $fixed++;
            $this->line(sprintf(
                '%s  action %d  %s→%s  premium %s  =>  %s   (source %d %s%s)',
                $renew->policyNumber, $renew->id, $renew->effective_from, $renew->effective_to,
                number_format($before, 2), number_format($after, 2),
                $source->id, $source->transaction_type,
                $endorseSource ? ' recomputed' : ''
            ));

            if ($regenInvoice) {
                try {
                    \AlphaDirect\Ledger::where('policy_id', $renew->policy_id)
                        ->where('action_id', $renew->id)
                        ->whereIn('trans_type', self::INVOICE_TYPES)
                        ->whereNull('deleted_at')
                        ->update(['deleted_at' => now()]);
                    \AlphaDirect\Helper::generateInvoiceDomComIssued($renew->policy_id, $renew->id, $renew->effective_from);
                    $invoices++;
                } catch (\Throwable $e) {
                    Log::warning("FixRenewPremiumMismatch: invoice regen failed for action {$renew->id}: " . $e->getMessage());
                    $this->warn("  invoice regen failed for action {$renew->id}: " . $e->getMessage());
                }
            }
        }

        $this->newLine();
        $endorseNote = (!$apply && $endorseDry > 0) ? "   Endorse-recompute candidates: {$endorseDry}" : '';
        $this->info("Checked: {$checked}   " . ($apply ? 'Corrected' : 'Mismatched') . ": {$fixed}{$endorseNote}   Skipped(no source/freq): {$skipped}   Invoices regenerated: {$invoices}");

        if (!empty($overlapReview)) {
            $this->newLine();
            $this->warn('OVERLAPPING-PERIOD policies skipped — MANUAL REVIEW (not auto-fixed):');
            foreach (array_keys($overlapReview) as $pn) {
                $this->warn("  - {$pn}");
            }
            $this->warn(sprintf('  => %d policies, %d renew actions skipped by the overlap guard.', count($overlapReview), $overlapSkipped));
        }

        $this->info($apply ? 'Corrections applied.' : 'Dry run only — re-run with --apply to write.');

        return self::SUCCESS;
    }

    /**
     * Detect policies whose ISSUED RENEW periods OVERLAP.
     *
     * Two renews cover the same span (a data anomaly) — the source selection
     * cannot decide which renew owns the period, so any auto-fix would write
     * oscillating wrong values. Computed in memory from the already-fetched
     * candidate set (no extra DB round-trips). Adjacent/touching periods
     * (one ends the day the next begins) are NOT overlaps.
     *
     * Returns a map [policy_id => true] of the offending policies.
     */
    private static function detectOverlappingRenewPolicies($renews): array
    {
        $byPolicy = [];
        foreach ($renews as $r) {
            $byPolicy[$r->policy_id][] = [
                substr((string) $r->effective_from, 0, 10),
                substr((string) $r->effective_to, 0, 10),
            ];
        }

        $dirty = [];
        foreach ($byPolicy as $pid => $rows) {
            $n = count($rows);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    // strict overlap: aFrom < bTo AND aTo > bFrom
                    if ($rows[$i][0] < $rows[$j][1] && $rows[$i][1] > $rows[$j][0]) {
                        $dirty[$pid] = true;
                        break 2;
                    }
                }
            }
        }

        return $dirty;
    }

    /**
     * Same-frequency guard for the verbatim (full-period) copy.
     *
     * A full-period renew may only inherit a source of the SAME billing
     * frequency (quarterly←quarterly, monthly←monthly). Prefer the stored
     * current_frequency_id; fall back to a whole-month span for legacy rows
     * without one so normal calendar variance (quarters 90/91/92 days, months
     * 28-31) never disqualifies a valid copy.
     */
    private static function sameFrequencyPeriod($renew, $source): bool
    {
        $rf = (int) ($renew->current_frequency_id ?? 0);
        $sf = (int) ($source->current_frequency_id ?? 0);
        if ($rf > 0 && $sf > 0) {
            return $rf === $sf;
        }

        return static::wholeMonths($renew->effective_from, $renew->effective_to)
            === static::wholeMonths($source->effective_from, $source->effective_to);
    }

    /** Day span rounded to whole months (absorbs 28-31 / 90-92 day variance). */
    private static function wholeMonths($from, $to): int
    {
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to));
        return (int) round(($days + 1) / 30);
    }
}
