<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Models\FacFxRate;
use AlphaDirect\Models\FacPlacement;
use AlphaDirect\Models\FacPlacementEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The FAC register's engine room.
 *
 *  · reference numbering
 *  · the Graphite policy lookup (nothing is re-typed)
 *  · the client-premium receipt read, straight off policy_ledger
 *  · the arithmetic, with a reconciliation guard that refuses a bad save
 *  · the event / notification writer
 *  · the PPW watch
 *
 * Settlement of the FAC premium is paid and tracked in omni, where the ledger
 * and the payment run sit. This service holds the register and the status; it
 * is not an accounting system.
 */
class FacRegisterService
{
    /** Thrown when the arithmetic does not tie. */
    public const RECONCILE_MESSAGE =
        'Gross does not equal commission plus net. The placement was not saved.';

    /** One thebe. Rounding drift below this is not a reconciliation failure. */
    public const RECONCILE_TOLERANCE = 0.01;

    /** Botswana VAT, where the placement carries it. */
    public const VAT_RATE = 0.14;

    /**
     * A config value, or the default where there is no container to ask.
     *
     * The arithmetic in computeAmounts() is pinned to the June workbook and the
     * signed slips, and is tested WITHOUT booting the framework — deliberately,
     * so that the figures Finance relies on can never be reached through a live
     * connection by a test. A bare config() call defeats that: it resolves
     * through the container and throws "Target class [config] does not exist"
     * the moment there is no application. Everything off the arithmetic path
     * still calls config() directly, because it only ever runs inside a request.
     *
     * @param  mixed  $default
     * @return mixed
     */
    private function setting(string $key, $default)
    {
        return app()->bound('config') ? config($key, $default) : $default;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Reference numbering
    // ─────────────────────────────────────────────────────────────────────

    /**
     * FAC-2026-000001. Sequential within the calendar year of creation, taken
     * under a row lock so two underwriters saving at once cannot collide.
     */
    public function nextReference(?int $year = null): string
    {
        $year   = $year ?: (int) now()->format('Y');
        $prefix = "FAC-{$year}-";

        return DB::transaction(function () use ($prefix) {
            $last = DB::table('fac_placements')
                ->where('fac_reference', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('fac_reference')
                ->value('fac_reference');

            $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

            return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }

    /**
     * 2026-001 — the slip numbering Reinsurance already uses on the master sheet
     * (2024-149, 2025-113), sequential within the calendar year.
     *
     * A slip could only be produced for a placement that ALREADY carried a number,
     * because generate() works by grouping the lines that share one. Reinsurance
     * captured three placements — two with numbers, one without — and the third
     * could never have a slip: the button that generates one is not even shown
     * without a number, and generateMissing() skips lines that have none. So the
     * register has to be able to allocate one, exactly as it allocates the
     * placement reference.
     *
     * Taken under a row lock over BOTH tables that can hold a slip number, so a
     * number is never handed out twice: fac_placements is where a manually typed
     * one lands, and fac_slips is where a generated document keeps it even if
     * every line that used it is later deleted.
     */
    public function nextSlipNo(?int $year = null): string
    {
        $year   = $year ?: (int) now()->format('Y');
        $prefix = "{$year}-";

        return DB::transaction(function () use ($prefix) {
            $highest = 0;
            // The width the existing series is written in, so a generated number
            // looks like its neighbours and sorts with them. Reinsurance flagged
            // that a system-generated format could conflict with the series
            // already in use: if the sheet runs 2026-005 the next is 2026-006, and
            // if it runs 2026-0005 the next is 2026-0006. Three is the floor, not
            // the rule.
            $width = 3;

            foreach ([['fac_placements', 'fac_slip_no'], ['fac_slips', 'slip_no']] as [$table, $column]) {
                $rows = DB::table($table)
                    ->where($column, 'like', $prefix . '%')
                    ->lockForUpdate()
                    ->pluck($column);

                foreach ($rows as $value) {
                    $tail = substr((string) $value, strlen($prefix));
                    // Only ever count OUR shape. A hand-typed "2026-113b" or
                    // "2026-1/2" must not be read as a sequence number and push
                    // the next allocation somewhere unpredictable.
                    if (preg_match('/^\d+$/', $tail)) {
                        $highest = max($highest, (int) $tail);
                        $width   = max($width, strlen($tail));
                    }
                }
            }

            return $prefix . str_pad((string) ($highest + 1), $width, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Botswana insurance financial year runs July → June.
     * A placement dated 15 Sep 2025 sits in FY2025-26.
     */
    public function financialYearFor(?string $date = null): string
    {
        $d     = $date ? Carbon::parse($date) : now();
        $start = $d->month >= 7 ? $d->year : $d->year - 1;

        return sprintf('FY%d-%02d', $start, ($start + 1) % 100);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Graphite policy lookup
    // ─────────────────────────────────────────────────────────────────────

    /**
     * The Basis of Cover for a policy, as the slip must state it.
     *
     * Reinsurance's rule: the slip follows the POLICY. If the policy is written on
     * a claims-made basis the slip says claims made; if claims occurring, claims
     * occurring. So this is read, never typed — a slip cannot then contradict the
     * policy it reinsures, and nobody has to remember to set it.
     *
     * WHERE IT LIVES. It is a sub-coverage selection carried solely in
     * policy_coverage_detail.limit_id, pointing at tb_cvgpclimits. The row holds no
     * money — sum insured and premium sit at zero, because the selection IS the
     * value. See PolicyCreateController's note on the Public Liability "Basis of
     * cover" field. The stored labels are "Claims Occurring" and "Claims Made";
     * the slip words it with "Basis" on the end.
     *
     * AMBIGUITY IS NOT RESOLVED BY GUESSING. A combined policy can carry both — a
     * liability section on claims made beside a fire section on claims occurring.
     * Where the policy holds more than one distinct basis this returns null rather
     * than picking the first, because printing one basis on a slip that reinsures
     * the other is a misstatement of cover. The slip then prints nothing and the
     * underwriter states it.
     */
    public function basisOfCoverFor(?int $policyId): ?string
    {
        if (!$policyId) {
            return null;
        }

        try {
            $labels = DB::table('policy_coverages as pc')
                ->join('policy_coverage_detail as pcd', 'pcd.policy_coverage_id', '=', 'pc.id')
                ->join('tb_cvgpclimits as lim', 'lim.n_PCLimitId_PK', '=', 'pcd.limit_id')
                ->where('pc.policy_id', $policyId)
                ->whereNull('pcd.deleted_at')
                // limit_id is a varchar where BOTH "" and "0" mean "nothing
                // selected". Without this the join matches a phantom limit row.
                ->whereNotIn('pcd.limit_id', ['', '0'])
                ->where(fn ($q) => $q
                    ->where('lim.s_LimitScreenName', 'like', '%Claims Occurring%')
                    ->orWhere('lim.s_LimitScreenName', 'like', '%Claims Made%'))
                ->distinct()
                ->pluck('lim.s_LimitScreenName')
                ->map(fn ($l) => trim((string) $l))
                ->filter()
                ->unique()
                ->values();
        } catch (\Throwable $e) {
            // The v1 coverage tables are Graphite's, not the register's. A slip must
            // still generate if they move or a column is renamed — the basis is a
            // printed term, not a figure, so its absence is a gap on the document
            // rather than a reason to refuse the placement.
            Log::warning('FAC basis of cover lookup failed', [
                'policy_id' => $policyId,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }

        if ($labels->count() !== 1) {
            return null;
        }

        $label = $labels->first();

        // "Claims Occurring" as stored becomes "Claims Occurring Basis" as the
        // signed slips word it. Left alone where the label already says Basis.
        return stripos($label, 'basis') === false ? $label . ' Basis' : $label;
    }

    /**
     * Pull a policy out of Graphite by its number so nobody re-types it.
     *
     * Always returns an array. `inGraphite = false` is a legitimate answer —
     * 27 of the 138 policies on the June master sheet do not exist in Graphite
     * (legacy COM… numbers plus the MEDMAL / PI / MAR / ENVI specialty numbers)
     * and they must still be registerable, flagged rather than dropped.
     */
    public function lookupPolicy(string $policyNumber): array
    {
        $policyNumber = trim($policyNumber);

        // Period and the transaction the reinsurance split was computed against
        // come off the LATEST policy_action, falling back to its policy_term —
        // the same COALESCE the policy detail screen uses. The `policies` row
        // itself only carries the current term, so it is not a period source.
        $p = DB::table('policies as p')
            ->leftJoin('customer as c', 'c.id', '=', 'p.customer_id')
            ->leftJoin('customer_profile as cp', 'cp.customer_id', '=', 'c.id')
            ->leftJoin('companies as co', 'co.id', '=', 'cp.company_id')
            ->leftJoin('products as pr', 'pr.id', '=', 'p.product_id')
            ->leftJoin('policy_actions as pa', 'pa.id', '=', DB::raw(
                '(SELECT id FROM policy_actions WHERE policy_id = p.id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1)'
            ))
            ->leftJoin('policy_term as ptm', 'ptm.id', '=', 'pa.term_id')
            ->where('p.policyNumber', $policyNumber)
            // NOTE: `policies` has NO deleted_at column — verified against prod
            // 31 Jul 2026. Filtering on it throws
            // "Unknown column 'deleted_at' in 'WHERE'". Soft deletes exist on
            // policy_actions, policy_reinsurance and policy_ledger, but NOT on
            // policies, policy_term, customer, customer_profile or products.
            ->orderByDesc('p.id')
            ->select(
                'p.id', 'p.policyNumber', 'p.status', 'p.product_id',
                'p.premium', 'p.annual_premium', 'p.premium_freq', 'p.customer_id',
                'c.firstName as cust_first', 'c.lastName as cust_last',
                'co.name as company_name', 'cp.entity_type as entity_type',
                'pr.name as product_name',
                'pa.id as action_id', 'pa.transaction_type as action_type',
                DB::raw('COALESCE(pa.effective_from, ptm.term_start_date) as period_from'),
                DB::raw('COALESCE(pa.effective_to,   ptm.term_end_date)   as period_to')
            )
            ->first();

        if (!$p) {
            return [
                'inGraphite'   => false,
                'policyId'     => null,
                'policyNumber' => $policyNumber,
                'insuredName'  => null,
                'message'      => 'Policy number not found in Graphite. It can still be registered — it will be flagged as unmatched.',
            ];
        }

        return [
            'inGraphite'     => true,
            'policyId'       => (int) $p->id,
            'policyNumber'   => $p->policyNumber,
            'policyActionId' => $p->action_id ?? null,
            'insuredName'    => $this->insuredNameFor($p),
            'policyType'     => $this->premiumFreqLabel($p->premium_freq),
            'productName'    => $p->product_name ?? null,
            'periodFrom'     => $p->period_from ?? null,
            'periodTo'       => $p->period_to ?? null,
            'policyStatus'   => $this->policyStatusLabel($p->status),
            'isActive'       => (int) $p->status === 1,
            'premium'        => $p->premium !== null ? (float) $p->premium : null,
            'annualPremium'  => $p->annual_premium !== null ? (float) $p->annual_premium : null,
        ];
    }

    /**
     * Commercial policies are written in the company's name; personal policies
     * in the individual's. Product ids 8 / 17 / 19 are the COM counterparts, and
     * an explicitly Organisation profile counts too.
     *
     * Every FAC placement on the master sheet is commercial, so in practice this
     * resolves to the company name — but the fallback keeps it honest.
     */
    private function insuredNameFor($p): ?string
    {
        $comProductIds = [8, 17, 19];
        $personal      = trim(($p->cust_first ?? '') . ' ' . ($p->cust_last ?? ''));
        $isCom         = in_array((int) $p->product_id, $comProductIds, true)
            || ($p->entity_type ?? null) === 'Organisation';

        $name = ($isCom && !empty($p->company_name)) ? $p->company_name : $personal;

        return trim((string) $name) !== '' ? trim((string) $name) : null;
    }

    private function premiumFreqLabel($freq): ?string
    {
        return match (strtolower((string) $freq)) {
            '1', 'monthly'    => 'Monthly',
            '3', 'quarterly'  => 'Quarterly',
            '6', 'halfyearly' => 'Half-Yearly',
            '12', 'annual', 'annually', 'yearly' => 'Annual',
            default => $freq !== null && $freq !== '' ? (string) $freq : null,
        };
    }

    /** Matches the labels used on the policy list screen. */
    private function policyStatusLabel($status): string
    {
        return match ((int) $status) {
            1       => 'Active',
            0       => 'Inactive / not activated',
            2       => 'Cancelled',
            default => 'Unknown (' . $status . ')',
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // Leg A — has the client actually paid us?
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Read the client's premium receipt straight out of Graphite.
     *
     * Uses the validated policy_ledger method, NOT the summary_age_analyst_*
     * caches (stale / partial) and NOT SUM(debit) (double-counts, because an
     * invoice posts a header row AND its Premium + VAT children).
     *
     *   received = Σ Payment − Σ Reverse Payment − Σ Refund
     *
     * Everything else in the register keys off this, so it has to be the same
     * arithmetic Finance uses.
     */
    public function readClientReceipts(int $policyId, ?string $since = null): array
    {
        // MONEY LANDS ON DIFFERENT SIDES DEPENDING ON trans_type. Counted against
        // prod on 31 Jul 2026:
        //   Payment          → credit  (2,330,900 rows; SUM credit 300,008,301.56)
        //   Reverse Payment  → debit   (729 rows; SUM CREDIT IS EXACTLY 0.00)
        //   Refund           → debit   (23,995 rows; SUM CREDIT IS EXACTLY 0.00)
        //
        // An earlier version summed `credit` for all three. Because the reversal
        // and refund legs live in `debit`, both subtractions returned zero — so a
        // policy whose only payment had BOUNCED still reported money received,
        // the sweep moved its placement to ready_to_settle, and the RI team was
        // told to pay a reinsurer out of premium the company had given back.
        // P15.9m of reversals and P6.8m of refunds were invisible to it.
        $rows = DB::table('policy_ledger')
            ->where('policy_id', $policyId)
            ->whereNull('deleted_at')
            ->when($since, fn ($q) => $q->whereDate('accounting_date', '>=', $since))
            ->select(
                'trans_type',
                DB::raw('SUM(COALESCE(credit,0)) as credit_total'),
                DB::raw('SUM(COALESCE(debit,0))  as debit_total'),
                DB::raw('MAX(accounting_date) as last_date')
            )
            ->groupBy('trans_type')
            ->get();

        /** @param 'credit_total'|'debit_total' $side */
        $sum = static function ($rows, array $types, string $side) {
            $t = 0.0;
            foreach ($rows as $r) {
                if (in_array(strtolower(trim((string) $r->trans_type)), $types, true)) {
                    $t += (float) $r->{$side};
                }
            }
            return $t;
        };

        $paid     = $sum($rows, ['payment'], 'credit_total');
        $reversed = $sum($rows, ['reverse payment', 'payment reversal'], 'debit_total');
        $refunded = $sum($rows, ['refund'], 'debit_total');

        $net = round($paid - $reversed - $refunded, 2);

        $lastPayment = null;
        foreach ($rows as $r) {
            if (strtolower(trim((string) $r->trans_type)) === 'payment') {
                $lastPayment = $r->last_date;
            }
        }

        return [
            'received'      => $net,
            'paid'          => round($paid, 2),
            'reversed'      => round($reversed, 2),
            'refunded'      => round($refunded, 2),
            'lastPaymentAt' => $lastPayment,
            'hasReceipt'    => $net > 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // The premium payment warranty
    // ─────────────────────────────────────────────────────────────────────

    /**
     * The date the client's premium must reach us.
     *
     * Reinsurance pointed out on 10 Aug 2026 that the warranty negotiated on a
     * slip is a PERIOD — "within 90 days", "monthly", "quarterly" — running from
     * the day the slip was signed, and that asking for a fixed date made the
     * capturer do that arithmetic by hand. So the period is what gets captured
     * and the date is derived from it.
     *
     * `ppw_due_date` remains the one column the breach alarm, the register flags
     * and the month-end snapshot read, so nothing downstream had to change.
     *
     * A date given on its own is still honoured: the imported history has no
     * signing dates at all (there is no PPW anywhere in the master sheet), and a
     * placement whose slip date is not to hand can still be dated by hand.
     */
    public function resolvePpwDueDate(?string $signedDate, $ppwDays, ?string $explicitDueDate): ?string
    {
        if ($signedDate !== null && $signedDate !== '' && $ppwDays !== null && $ppwDays !== '') {
            return Carbon::parse($signedDate)->addDays((int) $ppwDays)->toDateString();
        }

        return $explicitDueDate !== '' ? $explicitDueDate : null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Arithmetic — must reconcile
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Compute every derived money column from gross + commission % + VAT + FX.
     *
     * A net figure supplied by the caller is IGNORED. A register that can hold a
     * net disagreeing with its own gross is worse than no register.
     *
     * @throws \RuntimeException when gross ≠ commission + net beyond tolerance.
     */
    public function computeAmounts(array $in): array
    {
        $tolerance = (float) $this->setting('fac.reconcile_tolerance', self::RECONCILE_TOLERANCE);

        $gross         = $this->money($in['gross_ceded_premium'] ?? 0);
        $commissionPct = isset($in['commission_pct']) && $in['commission_pct'] !== null
            ? (float) $in['commission_pct']
            : 0.0;

        // Commission is a share of the premium, so it cannot exceed 100%. A rate
        // above 1 is a decimal-point slip, not a term — and it produces a
        // NEGATIVE net, which would otherwise be stored as though it meant
        // something. Confirmed by Reinsurance on 31 Jul 2026 against two lines on
        // the June workbook: one carried 2.75 (i.e. 275%) and one carried 1.0,
        // both of which should have read 0.275.
        // 100% is rejected as well as anything above it: a full commission leaves
        // the reinsurer with nothing, which is not a placement. (Do not confuse
        // this with "Risk Ceded to Re-Insurer(s) 100%" on the slip — that is the
        // cession share, a different figure entirely.)
        if ($commissionPct >= 1 || $commissionPct < 0) {
            throw new \RuntimeException(sprintf(
                'Commission of %s%% cannot be right — it must be under 100%% and not negative. '
                . 'A rate like 2.75 is usually 0.275 typed without the decimal point.',
                number_format($commissionPct * 100, 2)
            ));
        }

        $vatApplicable = (bool) ($in['vat_applicable'] ?? true);
        $vatRate       = $vatApplicable
            ? (float) ($in['vat_rate'] ?? $this->setting('fac.vat_rate', self::VAT_RATE))
            : 0.0;

        $commission = $this->money($gross * $commissionPct);
        $net        = $this->money($gross - $commission);

        // The gross captured on the master sheet is VAT-INCLUSIVE where VAT
        // applies; the "Excl VAT" column is gross ÷ (1 + rate). Verified:
        // 8,500.00 ÷ 1.14 = 7,456.14 and 28,752.79 ÷ 1.14 = 25,221.75.
        $divisor       = 1 + $vatRate;
        $grossExclVat  = $this->money($gross / $divisor);
        $commExclVat   = $this->money($commission / $divisor);

        // ── The guard ────────────────────────────────────────────────────
        //
        // Two distinct checks. The first catches rounding drift: commission and
        // net are each rounded to the thebe, so their sum can land a thebe away
        // from gross.
        if (abs($gross - ($commission + $net)) > $tolerance) {
            throw new \RuntimeException(self::RECONCILE_MESSAGE);
        }

        // The second is the one that actually matters, and an earlier version of
        // this method did not have it. Because `net` is DERIVED from gross above,
        // the check on the previous line can never fail on bad input — it was
        // arithmetically unreachable, so it gave false assurance while a caller
        // passing an inconsistent net was silently corrected instead of refused.
        //
        // A net supplied by a caller is still never STORED, but if it disagrees
        // with the gross it came with, the row is wrong at source and the save is
        // rejected rather than quietly fixed. That is the case worth catching: an
        // import whose net column does not agree with its own gross and
        // commission columns is corrupt data, not a rounding question.
        if (array_key_exists('net_ceded_premium', $in) && $in['net_ceded_premium'] !== null) {
            $supplied = $this->money($in['net_ceded_premium']);
            if (abs($supplied - $net) > $tolerance) {
                throw new \RuntimeException(sprintf(
                    '%s Gross %s less commission %s is %s, but %s was supplied.',
                    self::RECONCILE_MESSAGE,
                    number_format($gross, 2),
                    number_format($commission, 2),
                    number_format($net, 2),
                    number_format($supplied, 2)
                ));
            }
        }

        $out = [
            'gross_ceded_premium'          => $gross,
            'commission_pct'               => $commissionPct,
            'commission_amount'            => $commission,
            'net_ceded_premium'            => $net,
            'vat_applicable'               => $vatApplicable,
            'vat_rate'                     => $vatRate,
            'gross_ceded_premium_excl_vat' => $grossExclVat,
            'commission_excl_vat'          => $commExclVat,
        ];

        // ── Foreign currency ────────────────────────────────────────────
        $currency = strtoupper((string) ($in['currency'] ?? 'BWP'));
        $out['currency'] = $currency;

        if ($currency === 'BWP') {
            $out['fx_rate']                 = null;
            $out['fx_rate_date']            = null;
            $out['fx_rate_source']          = null;
            $out['gross_ceded_premium_bwp'] = $gross;
            $out['commission_amount_bwp']   = $commission;
            $out['net_ceded_premium_bwp']   = $net;

            return $out;
        }

        $rate       = $in['fx_rate'] ?? null;
        $rateDate   = $in['fx_rate_date'] ?? null;
        $rateSource = $in['fx_rate_source'] ?? null;

        // Nothing supplied — try the rate table before giving up.
        if ($rate === null) {
            $lookup = $this->lookupFxRate($currency, $in['period_from'] ?? null);
            if ($lookup) {
                $rate       = $lookup->rate;
                $rateDate   = $lookup->rate_date;
                $rateSource = $lookup->source;
            }
        }

        $out['fx_rate']        = $rate !== null ? (float) $rate : null;
        $out['fx_rate_date']   = $rateDate;
        $out['fx_rate_source'] = $rateSource;

        if ($rate === null) {
            // Blank Pula values plus a visible flag. NEVER treated as Pula.
            $out['gross_ceded_premium_bwp'] = null;
            $out['commission_amount_bwp']   = null;
            $out['net_ceded_premium_bwp']   = null;
        } else {
            $out['gross_ceded_premium_bwp'] = $this->money($gross * (float) $rate);
            $out['commission_amount_bwp']   = $this->money($commission * (float) $rate);
            $out['net_ceded_premium_bwp']   = $this->money($net * (float) $rate);
        }

        return $out;
    }

    /** Most recent rate on or before the given date. */
    public function lookupFxRate(string $currency, ?string $onOrBefore = null): ?FacFxRate
    {
        // No resolver means no framework, which in practice means the pinned
        // arithmetic test — see setting(). It does NOT mean the database is down:
        // a real outage still throws, because a placement quietly converting at
        // no rate would be worse than one that fails to save.
        // A RESOLVER IS NOT A BOOTED APPLICATION. Model::getConnectionResolver()
        // is static and survives the test that set it, so a plain PHPUnit test
        // running after a Laravel one finds a resolver still in place pointing
        // at a container that has been torn down. The guard then passed and the
        // query died on "Target class [config] does not exist" — invisible for
        // as long as the unit suite could not load at all, and the single error
        // left once it could.
        if (Model::getConnectionResolver() === null || ! app()->bound('config')) {
            return null;
        }

        return FacFxRate::query()
            ->where('currency', strtoupper($currency))
            ->when($onOrBefore, fn ($q) => $q->whereDate('rate_date', '<=', $onOrBefore))
            ->orderByDesc('rate_date')
            ->first();
    }

    /** Round half-up to the thebe, the way the workbook does. */
    private function money($v): float
    {
        return round((float) $v, 2);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Status transitions
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Mark the client premium as received and tell Debtors AND the RI team.
     * Debtors carry the tracking; RI make the payment.
     */
    public function markClientPaid(
        FacPlacement $p,
        string $source,
        ?float $amount = null,
        ?string $paidAt = null
    ): FacPlacement {
        if ($p->status === 'cancelled') {
            throw new \RuntimeException('This placement is cancelled. It cannot be marked as paid.');
        }

        // A SETTLED line must not come back. Without this an edit user could mark
        // an already-settled placement as client-paid, dropping it from `settled`
        // to `ready_to_settle` — back into the settle queue, and the RI team is
        // told to pay the same reinsurer a second time.
        if ($p->status === 'settled') {
            throw new \RuntimeException(
                'This placement has already been settled. Recording the client premium again would '
                . 'put it back in the queue to be paid. Reverse the settlement in omni first.'
            );
        }

        // AN UNSIGNED LINE MUST NOT BE QUEUED FOR PAYMENT. The two guards above
        // both ask what has already happened to the placement; neither asks the
        // prior question of whether the reinsurer ever agreed to carry it. A
        // draft is by definition a placement nobody has signed, so without this
        // the manual endpoint and the routine sweep alike promote it to
        // `ready_to_settle` and the RI team is told to pay a counterparty who is
        // not on risk.
        //
        // THE GUARD IS ON THE DATE, NOT THE STATUS. `placed` is an assertion by
        // whoever captured the row that the panel was placed; the signed slip is
        // the evidence of it. Guarding on `status !== 'draft'` would let a
        // `placed` row with no slip on file through, which is the same exposure
        // wearing a better label.
        if (! $p->slip_signed_date) {
            throw new \RuntimeException(
                'No signed slip is on file for this placement, so the reinsurer is not yet on risk. '
                . 'File the signed slip before recording the client premium, otherwise the RI team '
                . 'is queued to pay a counterparty who has not agreed to the cession.'
            );
        }

        $p->client_paid_at        = $paidAt ? Carbon::parse($paidAt) : now();
        $p->client_paid_source    = $source;
        $p->client_paid_amount    = $amount;
        $p->client_paid_marked_by = Auth::id();
        $p->status                = 'ready_to_settle';

        if (!$p->settlement_due_date) {
            $days = $this->settlementDaysFor($p);
            $p->settlement_due_date = $p->client_paid_at->copy()->addDays($days)->toDateString();
        }

        $p->save();

        $this->recordEvent($p, 'client_paid', sprintf(
            'Client premium received (%s). %s %s now due to %s.',
            $source,
            $p->currency,
            number_format((float) $p->gross_ceded_premium, 2),
            $p->counterparty_name ?? 'the counterparty'
        ), ['source' => $source, 'amount' => $amount], ['debtors', 'ri_team']);

        return $p;
    }

    private function settlementDaysFor(FacPlacement $p): int
    {
        if ($p->counterparty_id) {
            $terms = DB::table('reinsurer')->where('id', $p->counterparty_id)->value('settlement_terms_days');
            if ($terms) {
                return (int) $terms;
            }
        }
        return (int) config('fac.default_settlement_days', 30);
    }

    /** Record that we have settled the counterparty. The money moved in omni. */
    public function settle(FacPlacement $p, string $reference, float $amount, ?string $settledAt = null): FacPlacement
    {
        if ($p->status === 'cancelled') {
            throw new \RuntimeException('This placement is cancelled. It cannot be settled.');
        }

        $p->settled_at            = $settledAt ? Carbon::parse($settledAt) : now();
        $p->settlement_reference  = $reference;
        $p->settled_amount        = round($amount, 2);
        $p->settled_by            = Auth::id();
        $p->status                = 'settled';
        $p->save();

        $this->recordEvent($p, 'settled', sprintf(
            'Settled %s %s to %s. Reference %s.',
            $p->currency,
            number_format($amount, 2),
            $p->counterparty_name ?? 'the counterparty',
            $reference
        ), ['reference' => $reference, 'amount' => $amount], ['debtors']);

        return $p;
    }

    /**
     * Cancel a placement.
     *
     * A cancellation is a STATUS CHANGE — never a deletion. The reversing
     * amounts are written as their own row so the payable rolls back the way
     * the workbook does it, and the RI team is told before any payment can be
     * raised.
     */
    public function cancel(FacPlacement $p, string $reason): FacPlacement
    {
        if ($p->status === 'cancelled') {
            throw new \RuntimeException('This placement is already cancelled.');
        }
        if ($p->status === 'settled') {
            throw new \RuntimeException(
                'This placement has already been settled. Reverse it in omni first, then cancel here.'
            );
        }

        return DB::transaction(function () use ($p, $reason) {
            $p->status              = 'cancelled';
            $p->cancelled_at        = now();
            $p->cancelled_by        = Auth::id();
            $p->cancellation_reason = $reason;
            $p->save();

            // The reversal row. Same counterparty, same policy, amounts negated.
            $reversal = $p->replicate([
                'fac_reference', 'created_at', 'updated_at', 'deleted_at',
                'slip_generated_at', 'slip_sent_at',
            ]);
            $reversal->fac_reference                 = $this->nextReference();
            $reversal->status                        = 'cancelled';
            $reversal->is_reversal                   = true;
            $reversal->reverses_placement_id         = $p->id;
            $reversal->cancellation_reason           = $reason;
            $reversal->cancelled_at                  = now();
            $reversal->cancelled_by                  = Auth::id();
            $reversal->source                        = 'auto';
            $reversal->source_ref                    = 'Reversal of ' . $p->fac_reference;
            $reversal->client_paid_at                = null;
            $reversal->settled_at                    = null;
            $reversal->settlement_reference          = null;
            $reversal->settled_amount                = null;

            foreach ([
                'gross_ceded_premium', 'commission_amount', 'net_ceded_premium',
                'gross_ceded_premium_excl_vat', 'commission_excl_vat',
                'gross_ceded_premium_bwp', 'commission_amount_bwp', 'net_ceded_premium_bwp',
                'cession_sum_insured',
            ] as $col) {
                if ($reversal->{$col} !== null) {
                    $reversal->{$col} = -1 * (float) $reversal->{$col};
                }
            }
            $reversal->save();

            $this->recordEvent($p, 'cancelled', sprintf(
                'DO NOT SETTLE. %s cancelled — %s. Reversal raised as %s.',
                $p->fac_reference,
                $reason,
                $reversal->fac_reference
            ), ['reason' => $reason, 'reversal_reference' => $reversal->fac_reference], ['ri_team']);

            return $p;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Events + notifications
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Write the trail entry, then try to send. In that order, always.
     *
     * If the mail fails the event still exists with the intended recipients and
     * the error against it. The trail must never depend on mail delivery.
     *
     * @param string[] $recipientGroups keys of config('fac.recipients')
     */
    public function recordEvent(
        ?FacPlacement $p,
        string $event,
        string $summary,
        array $payload = [],
        array $recipientGroups = []
    ): FacPlacementEvent {
        $recipients = $this->resolveRecipients($recipientGroups);

        $row = FacPlacementEvent::create([
            'fac_placement_id'  => $p?->id,
            'event'             => $event,
            'summary'           => $summary,
            'payload'           => $payload ?: null,
            'notified_to'       => $recipients ? implode(', ', $recipients) : null,
            'notification_sent' => false,
            'actor_id'          => Auth::id(),
            'actor_name'        => Auth::user()->name ?? Auth::user()->firstName ?? null,
        ]);

        if (!$recipients) {
            return $row;
        }

        try {
            Mail::to($recipients)->send(
                new \AlphaDirect\Mail\FacNotificationMail($event, $summary, $p, $payload)
            );
            $row->update(['notification_sent' => true]);
        } catch (\Throwable $e) {
            Log::warning('FAC notification failed', [
                'event' => $event,
                'placement' => $p?->fac_reference,
                'error' => $e->getMessage(),
            ]);
            $row->update(['notification_error' => $e->getMessage()]);
        }

        return $row;
    }

    /** @return string[] */
    public function resolveRecipients(array $groups): array
    {
        $all = [];
        foreach ($groups as $g) {
            foreach ((array) config('fac.recipients.' . $g, []) as $addr) {
                $addr = trim((string) $addr);
                if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                    $all[] = $addr;
                }
            }
        }
        return array_values(array_unique($all));
    }

    // ─────────────────────────────────────────────────────────────────────
    // PPW watch
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Warn once, breach once.
     *
     * PPW is the deadline by which the client's premium must reach us or cover
     * can be voided. Nobody is watching it today. Idempotent by design — the
     * ppw_warned_at / ppw_breached_at stamps are the guard, so re-running the
     * job never re-notifies.
     *
     * @return array{warned:int, breached:int}
     */
    public function runPpwWatch(): array
    {
        $warnDays = (int) config('fac.ppw.warn_days_before', 7);
        $today    = now()->startOfDay();
        $warned   = 0;
        $breached = 0;

        // ── Falling due within the warning window, premium not in ──────
        FacPlacement::query()
            ->whereNull('deleted_at')
            ->whereNull('ppw_warned_at')
            ->whereNull('client_paid_at')
            ->whereNotNull('ppw_due_date')
            ->where('status', '!=', 'cancelled')
            ->whereDate('ppw_due_date', '>=', $today->toDateString())
            ->whereDate('ppw_due_date', '<=', $today->copy()->addDays($warnDays)->toDateString())
            ->chunkById(200, function ($rows) use (&$warned) {
                foreach ($rows as $p) {
                    $this->recordEvent($p, 'ppw_warned', sprintf(
                        'Premium Payment Warranty on %s (%s) falls due %s and the client premium is not in. Cover can be voided.',
                        $p->policy_number,
                        $p->insured_name ?? 'insured',
                        optional($p->ppw_due_date)->format('d M Y')
                    ), ['ppw_due_date' => optional($p->ppw_due_date)->toDateString()], ['uw_manager']);

                    $p->update(['ppw_warned_at' => now()]);
                    $warned++;
                }
            });

        // ── Passed, premium still not in ───────────────────────────────
        FacPlacement::query()
            ->whereNull('deleted_at')
            ->whereNull('ppw_breached_at')
            ->whereNull('client_paid_at')
            ->whereNotNull('ppw_due_date')
            ->where('status', '!=', 'cancelled')
            ->whereDate('ppw_due_date', '<', $today->toDateString())
            ->chunkById(200, function ($rows) use (&$breached) {
                foreach ($rows as $p) {
                    $this->recordEvent($p, 'ppw_breached', sprintf(
                        'PPW BREACHED. %s (%s) — premium was due %s and has not been received. Cover may be voidable.',
                        $p->policy_number,
                        $p->insured_name ?? 'insured',
                        optional($p->ppw_due_date)->format('d M Y')
                    ), ['ppw_due_date' => optional($p->ppw_due_date)->toDateString()], ['uw_manager', 'ri_team']);

                    $p->update(['ppw_breached_at' => now()]);
                    $breached++;
                }
            });

        return ['warned' => $warned, 'breached' => $breached];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Automatic client-paid sweep
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Move placements to "client paid" when Graphite shows the money.
     *
     * This is the routine path. A manual proof-of-payment upload is the
     * EXCEPTION, for premiums Graphite does not show — direct transfers,
     * foreign currency, off-system settlements.
     *
     * @return array{checked:int, marked:int}
     */
    public function sweepClientPayments(): array
    {
        $checked = 0;
        $marked  = 0;

        FacPlacement::query()
            ->whereNull('deleted_at')
            ->whereNull('client_paid_at')
            ->whereNotNull('policy_id')
            ->whereIn('status', ['placed', 'awaiting_premium'])
            // Imported history is EXCLUDED. Those lines were placed and settled
            // months ago; their policies have long since paid, so the sweep would
            // "discover" the whole June book as freshly received, move it all to
            // ready_to_settle and tell the RI team that ~P10.28m was newly due.
            // The sweep is for placements captured going forward.
            ->where('source', '!=', 'import')
            // UNSIGNED LINES ARE SKIPPED, NOT FAILED. markClientPaid now refuses
            // a placement with no signed slip on file, and that refusal is a
            // RuntimeException thrown inside this chunk callback — one unsigned
            // row would abort the whole sweep and leave every later chunk
            // unprocessed. Filtering here keeps the sweep's contract (it moves
            // what it can) and leaves the unsigned lines where they are, to be
            // picked up on the run after their slip is filed.
            ->whereNotNull('slip_signed_date')
            ->chunkById(200, function ($rows) use (&$checked, &$marked) {
                foreach ($rows as $p) {
                    $checked++;
                    $r = $this->readClientReceipts((int) $p->policy_id);
                    if (!$r['hasReceipt']) {
                        continue;
                    }
                    $this->markClientPaid($p, 'graphite', $r['received'], $r['lastPaymentAt']);
                    $marked++;
                }
            });

        return ['checked' => $checked, 'marked' => $marked];
    }
}
