<?php

namespace AlphaDirect\Services\Refunds;

use AlphaDirect\Models\RefundRequest;
use AlphaDirect\PaymentTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RefundFraudService — fraud / abuse signals on customer refunds, native to
 * Graphite. Mirrors the Omni customer_refunds fraud engine so both systems
 * reason the same way.
 *
 * Refunds pay money OUT, so they are a theft target — internal (staff / agent
 * collusion, control bypass) and external (fake cancellations, account swaps).
 * scan() runs every signal over a RefundRequest + its siblings + the policy's
 * payment history and returns ['flags' => [...], 'score' => int].
 *
 * Flagship: the SAME bank account paid out under DIFFERENT customer names —
 * matched on account_number_bindex (the keyed-HMAC blind index) so it works
 * without ever comparing the account number in clear.
 *
 * PII: only last-4 / policy / codes appear in flag details — never the full
 * account number.
 */
class RefundFraudService
{
    public const SEVERITY_SCORE = [
        'CRITICAL' => 25,
        'HIGH'     => 10,
        'MEDIUM'   => 4,
        'LOW'      => 1,
    ];

    private const VELOCITY_WINDOW_DAYS = 30;
    private const ACCOUNT_VELOCITY     = 3;
    private const AGENT_CONCENTRATION  = 12;
    private const AMOUNT_EPSILON       = 1.00;

    /**
     * "Just under" caps for structuring detection. Set TO the control thresholds
     * the splitting would be dodging — the two-approver rule (P5,000) and the CFO
     * gate (P50,000). The old MIS cap of 2,000 left a blind band: MIS refunds of
     * P2,000–4,999 were above the structuring cap AND below the dual-approval
     * threshold, so three P4,900 refunds drew no signal and needed one approver.
     */
    private const STRUCTURING_CAPS = [
        RefundRequest::AREA_MIS        => 5000,
        RefundRequest::AREA_DOMESTIC   => 5000,
        RefundRequest::AREA_COMMERCIAL => 50000,
    ];

    private const SUCCESS_STATUSES = ['SUCCESS', 'Success', 'success', '1'];

    /** @return array{flags: array<int, array{severity:string,code:string,detail:string}>, score: int} */
    public function scan(RefundRequest $r): array
    {
        $flags = [];
        $add = function (string $sev, string $code, string $detail) use (&$flags) {
            $flags[] = ['severity' => $sev, 'code' => $code, 'detail' => $detail];
        };

        $since  = Carbon::now()->subDays(self::VELOCITY_WINDOW_DAYS);
        $amount = (float) $r->refund_amount;
        $last4  = $r->account_last4 ?: '????';

        // ── 1. FLAGSHIP — same account, different customer names ──────────────
        $fp = $r->account_number_bindex;
        if (!empty($fp)) {
            // withTrashed: a soft-deleted refund must still count as history, or
            // deleting rows would whitewash the account's fraud record.
            $siblings = RefundRequest::withTrashed()
                ->where('account_number_bindex', $fp)
                ->when($r->id, fn ($q) => $q->where('id', '!=', $r->id))
                ->get(['customer_name', 'customer_id', 'policy_number', 'created_at']);

            $me = $this->norm($r->customer_name);
            $otherNames = [];
            foreach ($siblings as $s) {
                $n = $this->norm($s->customer_name);
                if ($n !== '' && $n !== $me) {
                    $otherNames[$n] = true;
                }
            }
            // Also key on the AUTHORITATIVE identifiers, not just the free-text
            // name: blanking or repeating customer_name used to disarm this
            // CRITICAL entirely. Distinct customer_id (or distinct policies where
            // the id is absent) on one bank account is the real signal.
            $otherCustomers = [];
            $otherPolicies  = [];
            foreach ($siblings as $s) {
                if (!empty($s->customer_id) && (int) $s->customer_id !== (int) $r->customer_id) {
                    $otherCustomers[(int) $s->customer_id] = true;
                }
                if (!empty($s->policy_number) && $s->policy_number !== $r->policy_number) {
                    $otherPolicies[$s->policy_number] = true;
                }
            }
            if (!empty($otherNames) || !empty($otherCustomers)) {
                $count = max(count($otherNames), count($otherCustomers)) + 1;
                $add('CRITICAL', 'account_shared_diff_names',
                    'Account …' . $last4 . ' refunded for ' . $count
                    . ' different customers/names.');
            } elseif (count($otherPolicies) >= 2) {
                // Same (or unknown) customer, but one account collecting refunds
                // for 3+ policies still warrants a human look.
                $add('HIGH', 'account_shared_many_policies',
                    'Account …' . $last4 . ' refunded across ' . (count($otherPolicies) + 1) . ' policies.');
            }
            $vel = $siblings->where('created_at', '>=', $since)->count();
            if ($vel >= self::ACCOUNT_VELOCITY) {
                $add('HIGH', 'account_velocity',
                    'Account …' . $last4 . ' on ' . ($vel + 1) . ' refunds in '
                    . self::VELOCITY_WINDOW_DAYS . ' days.');
            }
        }

        // ── 2. Premium invariant (payment history) ────────────────────────────
        if (!empty($r->policy_number)) {
            $stats = $this->premiumStats($r->policy_number);
            if ($stats === null) {
                // Can't judge (no payment feed at all) — skip, never fire blindly.
            } elseif ($stats['has_rows'] === false) {
                $add('CRITICAL', 'no_premium_history',
                    'Policy ' . $r->policy_number . ' has no successful premium payments — '
                    . 'refunding a policy that never paid in.');
            } else {
                $net = $stats['paid_in'] - $stats['refunded'];
                if ($amount > $net + self::AMOUNT_EPSILON) {
                    $add('CRITICAL', 'refund_exceeds_premiums_paid',
                        'Refund ' . number_format($amount, 2) . ' exceeds net premiums paid ('
                        . number_format($net, 2) . ') on policy ' . $r->policy_number . '.');
                }
                if ($stats['refunded'] >= $amount - self::AMOUNT_EPSILON && $stats['refunded'] > 0) {
                    $add('HIGH', 'duplicate_payout_cross_channel',
                        'Policy ' . $r->policy_number . ' already shows '
                        . number_format($stats['refunded'], 2) . ' refunded — possible double payout.');
                }
            }
        }

        // ── 3. Duplicate policy refund ────────────────────────────────────────
        if (!empty($r->policy_number)) {
            $dup = RefundRequest::withTrashed()
                ->where('policy_number', $r->policy_number)
                ->when($r->id, fn ($q) => $q->where('id', '!=', $r->id))
                ->count();
            if ($dup > 0) {
                $add('HIGH', 'duplicate_policy_refund',
                    'Policy ' . $r->policy_number . ' has ' . ($dup + 1) . ' refund requests.');
            }

            // IN-FLIGHT overlap is a different, harder signal: another refund on
            // this policy is already approved or on its way to the bank. The
            // premium invariant above cannot see it (nothing posts to
            // payment_transactions until POSTED), so two same-policy refunds
            // raised minutes apart both used to look clean and both could pay.
            $inFlight = RefundRequest::withTrashed()
                ->where('policy_number', $r->policy_number)
                ->when($r->id, fn ($q) => $q->where('id', '!=', $r->id))
                ->whereIn('status', [
                    RefundRequest::STATUS_APPROVAL_PENDING_2, RefundRequest::STATUS_APPROVED,
                    RefundRequest::STATUS_CFO_PENDING, RefundRequest::STATUS_CFO_APPROVED,
                    RefundRequest::STATUS_HANDED_OFF, RefundRequest::STATUS_PAID,
                    RefundRequest::STATUS_POSTED,
                ])
                ->get(['graphite_ref', 'refund_amount', 'status']);
            if ($inFlight->isNotEmpty()) {
                $sum = (float) $inFlight->sum('refund_amount');
                $add('CRITICAL', 'concurrent_refund_same_policy',
                    'Policy ' . $r->policy_number . ' already has ' . $inFlight->count()
                    . ' approved/paid refund(s) totalling ' . number_format($sum, 2)
                    . ' (' . $inFlight->pluck('graphite_ref')->implode(', ') . ') — possible double payout.');
            }
        }

        // ── 4. Structuring — split just under a soft cap ─────────────────────
        $cap = self::STRUCTURING_CAPS[$r->area] ?? null;
        if ($cap && $amount < $cap && (!empty($fp) || !empty($r->policy_number))) {
            $peers = RefundRequest::withTrashed()
                ->where('created_at', '>=', $since)
                ->when($r->id, fn ($q) => $q->where('id', '!=', $r->id))
                ->where(function ($q) use ($fp, $r) {
                    if (!empty($fp)) {
                        $q->orWhere('account_number_bindex', $fp);
                    }
                    if (!empty($r->policy_number)) {
                        $q->orWhere('policy_number', $r->policy_number);
                    }
                })
                ->get(['refund_amount']);
            if ($peers->count() >= 1) {
                $total = $amount + (float) $peers->sum('refund_amount');
                if ($total > $cap) {
                    $add('HIGH', 'structuring_below_threshold',
                        ($peers->count() + 1) . ' refunds on the same account/policy in '
                        . self::VELOCITY_WINDOW_DAYS . ' days sum to ' . number_format($total, 2)
                        . ' (each under ' . number_format($cap, 2) . ').');
                }
            }
        }

        // ── 5. Agent concentration ────────────────────────────────────────────
        if (!empty($r->agent_name)) {
            $ac = RefundRequest::withTrashed()
                ->where('agent_name', $r->agent_name)
                ->where('created_at', '>=', $since)
                ->when($r->id, fn ($q) => $q->where('id', '!=', $r->id))
                ->count();
            if ($ac >= self::AGENT_CONCENTRATION) {
                $add('MEDIUM', 'agent_concentration',
                    'Agent "' . $r->agent_name . '" on ' . ($ac + 1) . ' refunds in '
                    . self::VELOCITY_WINDOW_DAYS . ' days.');
            }
        }

        // ── 6. Serial refunder (same customer, many policies) ────────────────
        // Scope by customer_name in SQL (MySQL's default collation is
        // case-insensitive) — never load the whole table into PHP.
        $me = $this->norm($r->customer_name);
        if ($me !== '' && !empty($r->customer_name)) {
            $mine = RefundRequest::withTrashed()
                ->where('customer_name', $r->customer_name)
                ->when($r->id, fn ($q) => $q->where('id', '!=', $r->id))
                ->get(['policy_number', 'area']);
            if ($mine->count() >= 2) {
                $policies = $mine->pluck('policy_number')->push($r->policy_number)->unique();
                if ($policies->count() >= 3) {
                    $areas = $mine->pluck('area')->push($r->area)->unique();
                    $sev = $areas->count() >= 2 ? 'HIGH' : 'MEDIUM';
                    $add($sev, 'serial_refunder_customer',
                        'Customer "' . $r->customer_name . '" has ' . ($mine->count() + 1)
                        . ' refunds across ' . $policies->count() . ' policies'
                        . ($areas->count() >= 2 ? ' in multiple areas.' : '.'));
                }
            }
        }

        // ── 7. Off-hours action (local Gaborone time) ────────────────────────
        // Evaluated against the LATEST decision timestamp, not just approved_at:
        // the scan runs BEFORE approved_at is stamped, so keying on it alone
        // meant this signal could never fire on the single-approval path — an
        // 02:40 Sunday self-approval was invisible. now() is the moment of the
        // decision that triggered this scan, so it catches every pass.
        $decisionAt = $r->second_approved_at ?? $r->cfo_approved_at ?? $r->approved_at ?? now();
        $t = Carbon::parse($decisionAt)->setTimezone('Africa/Gaborone');
        if ($t->hour < 7 || $t->hour >= 18 || $t->isWeekend()) {
            $add('MEDIUM', 'off_hours_approval',
                'Action taken outside working hours / on a weekend (' . $t->format('D H:i') . ' Gaborone).');
        }

        $score = 0;
        foreach ($flags as $f) {
            $score += self::SEVERITY_SCORE[$f['severity']] ?? 0;
        }

        return ['flags' => $flags, 'score' => $score];
    }

    /**
     * Run the scan, persist the result, and record it in the append-only audit
     * trail. The audit row is what stops flag laundering: a CRITICAL raised at
     * submit used to be silently overwritten by a clean re-scan after the
     * creator edited the account/name and resubmitted, leaving no evidence it
     * ever existed. Every scan outcome is now permanently recorded.
     */
    public function applyScan(RefundRequest $r, $actor = null): array
    {
        $before = ['score' => (int) $r->fraud_score, 'codes' => $this->codes($r->fraud_flags)];
        $res = $this->scan($r);
        $r->fraud_flags       = $res['flags'];
        $r->fraud_score       = $res['score'];
        $r->fraud_reviewed_at = now();
        $r->save();

        $afterCodes = $this->codes($res['flags']);
        try {
            \AlphaDirect\Models\RefundRequestEvent::create([
                'refund_request_id' => $r->id,
                'from_status'       => $r->status,
                'to_status'         => $r->status,
                'action'            => 'fraud_scan',
                'actor_id'          => $actor?->id,
                'actor_name'        => $actor?->name ?? 'system',
                'meta'              => [
                    'score_from'  => $before['score'],
                    'score_to'    => $res['score'],
                    'codes'       => $afterCodes,
                    // A CRITICAL that was present and is now gone is the exact
                    // laundering fingerprint — call it out explicitly.
                    'cleared'     => array_values(array_diff($before['codes'], $afterCodes)),
                ],
                'created_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('fraud scan audit write failed', ['id' => $r->id, 'err' => $e->getMessage()]);
        }
        return $res;
    }

    /** @return array<int,string> */
    private function codes($flags): array
    {
        $out = [];
        foreach ((array) $flags as $f) {
            if (!empty($f['code'])) $out[] = $f['code'];
        }
        return $out;
    }

    /** True if this scan left any CRITICAL flag. */
    public function hasCritical(RefundRequest $r): bool
    {
        foreach ((array) $r->fraud_flags as $f) {
            if (($f['severity'] ?? null) === 'CRITICAL') {
                return true;
            }
        }
        return false;
    }

    /**
     * (paid_in, refunded, has_rows) for a policy from payment_transactions, or
     * null when the table is empty globally (can't judge). Only SUCCESS,
     * non-reversed rows count as money — a failed/reversed debit is NOT premium.
     *
     * @return array{paid_in: float, refunded: float, has_rows: bool}|null
     */
    private function premiumStats(string $policyNumber): ?array
    {
        if (!PaymentTransaction::query()->exists()) {
            return null;
        }
        $base = PaymentTransaction::query()
            ->where('policyNumber', $policyNumber)
            ->whereIn('status', self::SUCCESS_STATUSES)
            ->where(function ($q) {
                $q->whereNull('is_reverse')->orWhere('is_reverse', 0);
            });

        $hasRows = (clone $base)->exists();
        if (!$hasRows) {
            return ['paid_in' => 0.0, 'refunded' => 0.0, 'has_rows' => false];
        }
        $paidIn = (float) (clone $base)
            ->where(function ($q) { $q->whereNull('is_refund')->orWhere('is_refund', 0); })
            ->sum('amount');
        $refunded = (float) (clone $base)->where('is_refund', 1)->sum('amount');

        // The gateway feed is NOT the whole refund history. Legacy manual refunds
        // ("Refund Money" on the policy screen) wrote ONLY a policy_ledger row —
        // measured on PROD 2026-07-30: 23,995 ledger refunds vs 1,836 gateway
        // rows, i.e. ~12,900 policies holding ~BWP 5.39m of payouts this check
        // could not see. A second full refund on an already-refunded policy
        // therefore raised no flag at all. Add the ledger side (and the archived
        // gateway rows) so the invariant reflects every thebe actually paid out.
        $refunded += $this->ledgerRefunded($policyNumber);
        $refunded += $this->archivedRefunded($policyNumber);

        return ['paid_in' => $paidIn, 'refunded' => $refunded, 'has_rows' => true];
    }

    /**
     * Refunds visible only on the policy statement (legacy manual refunds).
     * Excludes rows this engine itself posted (ref REFUND-*) so engine refunds
     * are never counted twice — those already come from payment_transactions.
     */
    private function ledgerRefunded(string $policyNumber): float
    {
        try {
            return (float) DB::table('policy_ledger as l')
                ->join('policies as p', 'p.id', '=', 'l.policy_id')
                ->where('p.policyNumber', $policyNumber)
                ->where('l.trans_type', 'Refund')
                ->where(function ($q) {
                    $q->whereNull('l.trans_ref')->orWhere('l.trans_ref', 'not like', 'REFUND-%');
                })
                ->sum('l.debit');
        } catch (\Throwable $e) {
            Log::warning('fraud: ledger refund lookup failed', ['policy' => $policyNumber, 'err' => $e->getMessage()]);
            return 0.0;
        }
    }

    /** Archived gateway refunds (separate connection; absent in some envs). */
    private function archivedRefunded(string $policyNumber): float
    {
        try {
            if (!class_exists(\AlphaDirect\Models\PaymentTransactionArchive::class)) {
                return 0.0;
            }
            return (float) \AlphaDirect\Models\PaymentTransactionArchive::query()
                ->where('policyNumber', $policyNumber)
                ->whereIn('status', self::SUCCESS_STATUSES)
                ->where('is_refund', 1)
                ->where(function ($q) {
                    $q->whereNull('is_reverse')->orWhere('is_reverse', 0);
                })
                ->sum('amount');
        } catch (\Throwable $e) {
            // Archive DB not reachable in this environment — degrade, never crash.
            return 0.0;
        }
    }

    private function norm(?string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $s)));
    }
}
