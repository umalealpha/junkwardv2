<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\FacPlacement;
use AlphaDirect\Models\FacPlacementAttachment;
use AlphaDirect\Models\FacPlacementScheduleItem;
use AlphaDirect\Models\FacSlip;
use AlphaDirect\Models\FacSlipAcceptance;
use AlphaDirect\Services\Reinsurance\FacCoverageService;
use AlphaDirect\Services\Reinsurance\FacIntelligenceService;
use AlphaDirect\Services\Reinsurance\FacRegisterService;
use AlphaDirect\Services\Reinsurance\FacBordereauService;
use AlphaDirect\Services\Reinsurance\FacMandateService;
use AlphaDirect\Services\Reinsurance\FacSlipService;
use AlphaDirect\Services\Reinsurance\FacSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The FAC register's API.
 *
 * Follows the conventions already used by ReinsuranceApiController — query
 * builder, camelCase JSON keys, a `meta` block for pagination.
 *
 * Capture, settlement and cancellation are separately permissioned on the
 * routes: the underwriter who raises a line must not be able to sign off its
 * own payment.
 */
class FacRegisterApiController extends Controller
{
    /**
     * The only statuses a capture or an edit may set directly.
     *
     * Everything past this point is a money event with its own permission and its
     * own side effects — settle writes a reference and an actor, cancel writes a
     * reversing row and alerts the RI team. Allowing them through the ordinary
     * validator would let one right do another right's job.
     */
    /**
     * The cycle a facultative placement actually goes through, in order.
     *
     * Four working stages plus one dead end. It is deliberately NOT the status
     * list: `placed` and `awaiting_premium` are the same stage of work (we are
     * waiting on the client's money), and `client_paid` and `ready_to_settle` are
     * both "we owe the reinsurer now". Collapsing them is the whole point — the
     * question being answered is what is outstanding, not what the row says.
     */
    private const STAGE_LABELS = [
        'awaiting_signature' => 'Awaiting signature',
        'awaiting_premium'   => 'Awaiting client premium',
        'ready_to_settle'    => 'Ready to settle',
        'complete'           => 'Complete',
        'cancelled'          => 'Cancelled',
    ];

    /** Position on the ladder. Cancelled is 0 — it is off the ladder, not at the end of it. */
    private const STAGE_STEPS = [
        'awaiting_signature' => 1,
        'awaiting_premium'   => 2,
        'ready_to_settle'    => 3,
        'complete'           => 4,
        'cancelled'          => 0,
    ];

    /**
     * Which stage a row is at.
     *
     * Reads the money events rather than trusting the status alone, because the
     * status can be any of three values while the work is in one place. A line
     * whose client premium is confirmed received is "ready to settle" whether the
     * status says client_paid or ready_to_settle.
     */
    private function stageOf(object $r): string
    {
        if ($r->status === 'cancelled' || $r->is_reversal) {
            return 'cancelled';
        }
        if ($r->status === 'settled' || $r->settled_at) {
            return 'complete';
        }
        if ($r->status === 'draft') {
            return 'awaiting_signature';
        }

        return ($r->client_paid_at || in_array($r->status, ['client_paid', 'ready_to_settle'], true))
            ? 'ready_to_settle'
            : 'awaiting_premium';
    }

    private const CAPTURABLE_STATUSES = ['draft', 'placed', 'awaiting_premium'];

    public function __construct(
        private FacRegisterService $register,
        private FacSummaryService $summary,
        private FacCoverageService $coverage,
        private FacSlipService $slips,
        private FacIntelligenceService $intelligence,
        private FacMandateService $mandates,
        private FacBordereauService $bordereaux,
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────
    // List + totals
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $v = $request->validate([
            'search'          => 'nullable|string|max:120',
            'status'          => 'nullable|string|max:40',
            'placement_type'  => ['nullable', Rule::in(['fac', 'auto_fac'])],
            'counterparty_id' => 'nullable|integer',
            'financial_year'  => 'nullable|string|max:9',
            'currency'        => 'nullable|string|max:3',
            'flag'            => ['nullable', Rule::in([
                'unmatched_policy', 'inactive_policy', 'rate_missing',
                'ppw_breached', 'ppw_due', 'awaiting_premium', 'ready_to_settle',
            ])],
            // Where in the cycle, as its own filter — "show me everything waiting
            // on a signature" is the question the stage column exists to answer.
            'stage'           => ['nullable', Rule::in([
                'awaiting_signature', 'awaiting_premium', 'ready_to_settle', 'complete', 'cancelled',
            ])],
            // Reinsurance reads the register against the slip series, which runs
            // upwards. Ascending is therefore the default; newest-first stays
            // available for working the queue.
            'sort'            => ['nullable', Rule::in(['oldest', 'newest'])],
            'per_page'        => 'nullable|integer|min:5|max:200',
        ]);

        $q = $this->baseQuery($v);

        // Ascending by default: the register is read against the slip series, which
        // runs upwards, and a numbered series shown backwards is hard to reconcile
        // against the master sheet. Newest-first is still one click away for
        // working the queue.
        $q = ($v['sort'] ?? 'oldest') === 'newest'
            ? $q->orderByDesc('fac_placements.id')
            : $q->orderBy('fac_placements.id');

        $results = $q->paginate($v['per_page'] ?? 25);

        // Totals for the CURRENT filter, not the page — a payable that changes
        // when you turn the page is not a payable.
        // Drafts are counted and shown, but never added to the payable — same
        // rule as FacSummaryService. An unconfirmed auto-generated line is not a
        // liability.
        // One clock for the breach count.
        //
        // This was CURDATE(), while rowJson() compares the very same flag against
        // now()->toDateString() — so the tile and the rows it counts were reading
        // the database's clock and the application's. Where those sit in different
        // timezones they disagree for the hours between the two midnights, and the
        // count on the scoreboard would not match the flagged rows underneath it.
        $breachedBefore = now()->toDateString();

        $totals = (clone $q)->reorder()->select(
            DB::raw("SUM(CASE WHEN status = 'draft' THEN 0 ELSE COALESCE(gross_ceded_premium_bwp, CASE WHEN currency = 'BWP' THEN gross_ceded_premium ELSE 0 END) END) as payable_bwp"),
            DB::raw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count"),
            DB::raw("SUM(CASE WHEN status IN ('placed','awaiting_premium') THEN 1 ELSE 0 END) as awaiting_premium"),
            DB::raw("SUM(CASE WHEN status = 'ready_to_settle' THEN 1 ELSE 0 END) as ready_to_settle"),
            DB::raw("SUM(CASE WHEN currency <> 'BWP' AND fx_rate IS NULL THEN 1 ELSE 0 END) as rate_missing"),
            DB::raw('SUM(CASE WHEN policy_in_graphite = 0 THEN 1 ELSE 0 END) as unmatched_policy'),
            DB::raw("SUM(CASE WHEN ppw_due_date IS NOT NULL AND client_paid_at IS NULL AND status <> 'cancelled' AND ppw_due_date < ? THEN 1 ELSE 0 END) as ppw_breached"),
            DB::raw('COUNT(*) as line_count')
        )->addBinding($breachedBefore, 'select')->first();

        return response()->json([
            'data' => collect($results->items())->map(fn ($r) => $this->rowJson($r)),
            'totals' => [
                'payableBwp'      => round((float) ($totals->payable_bwp ?? 0), 2),
                'draftCount'      => (int) ($totals->draft_count ?? 0),
                'awaitingPremium' => (int) ($totals->awaiting_premium ?? 0),
                'readyToSettle'   => (int) ($totals->ready_to_settle ?? 0),
                'rateMissing'     => (int) ($totals->rate_missing ?? 0),
                'unmatchedPolicy' => (int) ($totals->unmatched_policy ?? 0),
                'ppwBreached'     => (int) ($totals->ppw_breached ?? 0),
                'lineCount'       => (int) ($totals->line_count ?? 0),
            ],
            'meta' => [
                'total'        => $results->total(),
                'per_page'     => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
                'from'         => $results->firstItem(),
                'to'           => $results->lastItem(),
            ],
        ]);
    }

    private function baseQuery(array $v)
    {
        return DB::table('fac_placements')
            ->whereNull('deleted_at')
            ->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($v['placement_type'] ?? null, fn ($q, $t) => $q->where('placement_type', $t))
            ->when($v['counterparty_id'] ?? null, fn ($q, $c) => $q->where('counterparty_id', $c))
            ->when($v['financial_year'] ?? null, fn ($q, $f) => $q->where('financial_year', $f))
            ->when($v['currency'] ?? null, fn ($q, $c) => $q->where('currency', strtoupper($c)))
            // Stage is derived, so it filters on the same conditions stageOf()
            // reads rather than on a stored column. Kept beside it deliberately:
            // if one changes, the other has to change in the same commit.
            ->when($v['stage'] ?? null, function ($q, $stage) {
                match ($stage) {
                    'awaiting_signature' => $q->where('status', 'draft')
                                              ->where('is_reversal', false),
                    'awaiting_premium'   => $q->whereIn('status', ['placed', 'awaiting_premium'])
                                              ->whereNull('client_paid_at')
                                              ->where('is_reversal', false),
                    'ready_to_settle'    => $q->whereNotIn('status', ['settled', 'cancelled', 'draft'])
                                              ->where('is_reversal', false)
                                              ->where(fn ($w) => $w->whereNotNull('client_paid_at')
                                                  ->orWhereIn('status', ['client_paid', 'ready_to_settle'])),
                    'complete'           => $q->where('status', 'settled'),
                    'cancelled'          => $q->where(fn ($w) => $w->where('status', 'cancelled')
                                              ->orWhere('is_reversal', true)),
                    default              => $q,
                };
            })
            ->when($v['search'] ?? null, function ($q, $s) {
                $q->where(function ($w) use ($s) {
                    $w->where('policy_number', 'like', "%{$s}%")
                      ->orWhere('insured_name', 'like', "%{$s}%")
                      ->orWhere('fac_reference', 'like', "%{$s}%")
                      ->orWhere('fac_slip_no', 'like', "%{$s}%")
                      ->orWhere('counterparty_name', 'like', "%{$s}%")
                      ->orWhere('risk_carrier', 'like', "%{$s}%");
                });
            })
            ->when($v['flag'] ?? null, function ($q, $flag) {
                match ($flag) {
                    'unmatched_policy' => $q->where('policy_in_graphite', false),
                    'inactive_policy'  => $q->where('policy_in_graphite', true)
                                            ->where('policy_active_in_graphite', false),
                    'rate_missing'     => $q->where('currency', '!=', 'BWP')->whereNull('fx_rate'),
                    'ppw_breached'     => $q->whereNotNull('ppw_due_date')
                                            ->whereNull('client_paid_at')
                                            ->where('status', '!=', 'cancelled')
                                            ->whereDate('ppw_due_date', '<', now()->toDateString()),
                    'ppw_due'          => $q->whereNotNull('ppw_due_date')
                                            ->whereNull('client_paid_at')
                                            ->where('status', '!=', 'cancelled')
                                            ->whereBetween('ppw_due_date', [
                                                now()->toDateString(),
                                                now()->addDays((int) config('fac.ppw.warn_days_before', 7))->toDateString(),
                                            ]),
                    'awaiting_premium' => $q->whereIn('status', ['placed', 'awaiting_premium']),
                    'ready_to_settle'  => $q->where('status', 'ready_to_settle'),
                    default            => $q,
                };
            });
    }

    private function rowJson($r): array
    {
        $isBwp = strtoupper((string) $r->currency) === 'BWP';

        return [
            'id'                 => (int) $r->id,
            'facReference'       => $r->fac_reference,
            'facSlipNo'          => $r->fac_slip_no,
            'financialYear'      => $r->financial_year,
            'placementType'      => $r->placement_type,
            'placementTypeLabel' => $r->placement_type === 'auto_fac' ? 'Auto FAC' : 'FAC',
            'policyId'           => $r->policy_id ? (int) $r->policy_id : null,
            'policyNumber'       => $r->policy_number,
            'insuredName'        => $r->insured_name,
            'policyType'         => $r->policy_type,
            'periodFrom'         => $r->period_from,
            'periodTo'           => $r->period_to,
            'policyStatus'       => $r->policy_status,
            'riGroupLabel'       => $r->ri_group_label,
            'cessionSumInsured'  => $this->dec($r->cession_sum_insured),
            'sourcePremium'      => $this->dec($r->source_premium),
            'riskPct'            => $this->dec($r->risk_pct),
            'counterpartyId'     => $r->counterparty_id ? (int) $r->counterparty_id : null,
            'counterpartyName'   => $r->counterparty_name,
            'riskCarrier'        => $r->risk_carrier,
            'currency'           => $r->currency,
            'grossCededPremium'  => $this->dec($r->gross_ceded_premium),
            'commissionPct'      => $this->dec($r->commission_pct),
            'commissionAmount'   => $this->dec($r->commission_amount),
            'netCededPremium'    => $this->dec($r->net_ceded_premium),
            'vatApplicable'      => (bool) $r->vat_applicable,
            'vatRate'            => $this->dec($r->vat_rate),
            'grossExclVat'       => $this->dec($r->gross_ceded_premium_excl_vat),
            // The VAT carried inside the captured gross. Derived, never stored —
            // storing it would give the same number two homes that can disagree.
            'vatAmount'          => $r->gross_ceded_premium_excl_vat === null ? null
                : $this->dec((float) $r->gross_ceded_premium - (float) $r->gross_ceded_premium_excl_vat),
            'payableBwp'         => $isBwp ? $this->dec($r->gross_ceded_premium) : $this->dec($r->gross_ceded_premium_bwp),
            'fxRate'             => $this->dec($r->fx_rate),
            'fxRateDate'         => $r->fx_rate_date,
            'fxRateSource'       => $r->fx_rate_source,
            'slipSignedDate'     => $r->slip_signed_date,
            'ppwDueDate'         => $r->ppw_due_date,
            'ppwDays'            => $r->ppw_days !== null ? (int) $r->ppw_days : null,
            'ppwTerms'           => $r->ppw_terms,
            'premiumFrequency'   => $r->premium_frequency,
            'underwriterName'    => $r->underwriter_name,
            'status'             => $r->status,
            'clientPaidAt'       => $r->client_paid_at,
            'clientPaidSource'   => $r->client_paid_source,
            'settlementDueDate'  => $r->settlement_due_date,
            'settledAt'          => $r->settled_at,
            'settlementReference'=> $r->settlement_reference,
            'isReversal'         => (bool) $r->is_reversal,
            'source'             => $r->source,
            'slipGeneratedAt'    => $r->slip_generated_at,
            'slipSentAt'         => $r->slip_sent_at,

            // ── Where this placement sits in the cycle ──────────────────
            //
            // The raw status says what the RECORD is; it does not say how far
            // along the work is. Reinsurance asked for "awaiting signature versus
            // complete" because a register of nine statuses, three of which look
            // interchangeable from the outside, does not answer "what is
            // outstanding on this line?".
            //
            // Derived, never stored. A stored stage is a second source of truth
            // that drifts the first time a settlement is recorded by any route
            // other than the one that maintains it.
            'stage'      => $this->stageOf($r),
            'stageLabel' => self::STAGE_LABELS[$this->stageOf($r)],
            'stageStep'  => self::STAGE_STEPS[$this->stageOf($r)],
            'stageOf'    => count(self::STAGE_STEPS) - 1,   // cancelled is not a step

            // The flags that must be visible on the row, not buried.
            'flags' => array_values(array_filter([
                !$r->policy_in_graphite ? 'policy_not_in_graphite' : null,
                ($r->policy_in_graphite && !$r->policy_active_in_graphite) ? 'policy_not_active' : null,
                (!$isBwp && $r->fx_rate === null) ? 'fx_rate_missing' : null,
                ($r->ppw_due_date && !$r->client_paid_at && $r->status !== 'cancelled'
                    && $r->ppw_due_date < now()->toDateString()) ? 'ppw_breached' : null,
                !$r->ppw_due_date && $r->status !== 'cancelled' ? 'ppw_not_set' : null,

                // Treaty conditions, from the Capacities Table. Only the two that
                // can be tested from what the register holds — see FacMandateService
                // for why the other six are not here.
                ...$this->mandates->flagsFor($r),
            ])),
        ];
    }

    private function dec($v): ?float
    {
        return $v === null ? null : (float) $v;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Summary + coverage
    // ─────────────────────────────────────────────────────────────────────

    /** The SUMMARY tab, live — payable by counterparty. */
    public function summary(Request $request): JsonResponse
    {
        $v = $request->validate([
            'as_at'          => 'nullable|date',
            'financial_year' => 'nullable|string|max:9',
        ]);

        return response()->json(
            $this->summary->payableByCounterparty($v['as_at'] ?? null, $v['financial_year'] ?? null)
        );
    }

    public function variance(Request $request): JsonResponse
    {
        $v = $request->validate([
            'period_end'     => 'required|date',
            'financial_year' => 'nullable|string|max:9',
        ]);

        return response()->json(
            $this->summary->varianceToGl($v['period_end'], $v['financial_year'] ?? null)
        );
    }

    public function storeGl(Request $request): JsonResponse
    {
        $v = $request->validate([
            'period_end'        => 'required|date',
            'gl_premium'        => 'required|numeric',
            'gl_commission'     => 'nullable|numeric',
            'gl_vat_premium'    => 'nullable|numeric',
            'gl_vat_commission' => 'nullable|numeric',
            'gl_source'         => 'required|string|max:200',
            'gl_as_at'          => 'required|date',
        ]);

        DB::table('fac_period_gl')->updateOrInsert(
            ['period_end' => $v['period_end']],
            array_merge($v, [
                'captured_by'      => Auth::id(),
                'captured_by_name' => Auth::user()->name ?? null,
                'updated_at'       => now(),
                'created_at'       => now(),
            ])
        );

        return response()->json(['message' => 'General-ledger figures captured for this period.']);
    }

    public function closePeriod(Request $request): JsonResponse
    {
        $v = $request->validate([
            'period_end'     => 'required|date',
            'financial_year' => 'nullable|string|max:9',
        ]);

        return response()->json(
            $this->summary->closePeriod($v['period_end'], $v['financial_year'] ?? null)
        );
    }

    /** Is this policy FAC'ed? */
    public function coverageForPolicy(Request $request): JsonResponse
    {
        $v = $request->validate(['policy_number' => 'required|string|max:60']);

        return response()->json($this->coverage->forPolicy($v['policy_number']));
    }

    /** Every policy Graphite says needs FAC, against what the register carries. */
    public function coverageScan(Request $request): JsonResponse
    {
        $v = $request->validate([
            'exceptions_only' => 'nullable|boolean',
            'limit'           => 'nullable|integer|min:1|max:5000',
        ]);

        $rows = $this->coverage->scan(
            $v['exceptions_only'] ?? true,
            $v['limit'] ?? 5000
        );

        return response()->json([
            'data'    => $rows,
            'summary' => $this->coverage->scanSummary(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Lookup + CRUD
    // ─────────────────────────────────────────────────────────────────────

    /** Pull the policy out of Graphite before saving, so nothing is re-typed. */
    public function lookupPolicy(Request $request): JsonResponse
    {
        $v = $request->validate(['policy_number' => 'required|string|max:60']);
        $result = $this->register->lookupPolicy($v['policy_number']);

        if (!empty($result['inGraphite']) && $result['policyId']) {
            $result['receipts'] = $this->register->readClientReceipts((int) $result['policyId']);
            $result['coverage'] = $this->coverage->forPolicy($v['policy_number']);
        }

        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $this->validatePlacement($request);

        /**
         * The entry choice decides two things, and the server decides them — not
         * the form.
         *
         * `new`  — the reinsurer has not signed. The line is a DRAFT, so it is out
         *          of the payable, out of the frozen month-end snapshot and out of
         *          the journal Finance posts until the signed slip is filed. Any
         *          signing date that arrived with it is dropped: there is no
         *          signature to date, and a date here would start the premium
         *          warranty counting down against an agreement nobody has made.
         *
         * `signed` — already agreed, so it is payable from now and the warranty
         *          runs from the signing date the validator has just insisted on.
         *
         * Absent — an import or an API caller that predates the split. Left alone,
         * so nothing that worked before changes behaviour.
         */
        $intent = $v['capture_intent'] ?? null;
        if ($intent === 'new') {
            $v['status']           = 'draft';
            $v['slip_signed_date'] = null;
            $v['ppw_due_date']     = null;

            // A new placement must end up with a slip, and a slip is produced by
            // grouping the lines that share a number — so a line without one can
            // never have a slip generated for it. Reinsurance hit exactly that:
            // three placements captured, and the one with no number got no slip.
            // The register allocates one rather than refusing, the same way it
            // allocates the placement reference.
            if (trim((string) ($v['fac_slip_no'] ?? '')) === '') {
                $v['fac_slip_no'] = $this->register->nextSlipNo();
            }
        } elseif ($intent === 'signed') {
            $v['status'] = $v['status'] ?? 'placed';
        }

        try {
            $amounts = $this->register->computeAmounts($v);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $lookup = $this->register->lookupPolicy($v['policy_number']);

        // Reference allocation and the insert share ONE transaction. nextReference()
        // takes a row lock, but if it commits before the insert two concurrent
        // captures can mint the same reference and the unique index turns that into
        // a raw 500. (cancel() already gets this right.)
        $row = DB::transaction(fn () => FacPlacement::create(array_merge($amounts, [
            'fac_reference'             => $this->register->nextReference(),
            'fac_slip_no'               => $v['fac_slip_no'] ?? null,
            'financial_year'            => $v['financial_year']
                ?? $this->register->financialYearFor($v['period_from'] ?? null),
            'placement_type'            => $v['placement_type'] ?? 'fac',
            'policy_id'                 => $lookup['policyId'] ?? null,
            'policy_number'             => $v['policy_number'],
            'policy_action_id'          => $lookup['policyActionId'] ?? null,
            'insured_name'              => $lookup['insuredName'] ?? ($v['insured_name'] ?? null),
            'policy_type'               => $lookup['policyType'] ?? ($v['policy_type'] ?? null),
            'period_from'               => $v['period_from'] ?? ($lookup['periodFrom'] ?? null),
            'period_to'                 => $v['period_to'] ?? ($lookup['periodTo'] ?? null),
            'policy_status'             => $lookup['policyStatus'] ?? null,
            'policy_synced_at'          => now(),
            'policy_in_graphite'        => (bool) ($lookup['inGraphite'] ?? false),
            'policy_active_in_graphite' => (bool) ($lookup['isActive'] ?? false),
            'reinsurance_group_id'      => $v['reinsurance_group_id'] ?? null,
            'ri_group_label'            => $v['ri_group_label'] ?? null,
            'cession_sum_insured'       => $v['cession_sum_insured'] ?? null,
            'source_premium'            => $v['source_premium'] ?? null,
            'risk_pct'                  => $v['risk_pct'] ?? null,
            'counterparty_id'           => $v['counterparty_id'],
            'counterparty_name'         => DB::table('reinsurer')->where('id', $v['counterparty_id'])->value('company_name'),
            'risk_carrier'              => $v['risk_carrier'] ?? null,
            'slip_signed_date'          => $v['slip_signed_date'] ?? null,
            'ppw_days'                  => $v['ppw_days'] ?? null,
            'ppw_terms'                 => $v['ppw_terms'] ?? null,
            'premium_frequency'         => $v['premium_frequency'] ?? null,
            'ppw_due_date'              => $this->register->resolvePpwDueDate(
                $v['slip_signed_date'] ?? null,
                $v['ppw_days'] ?? null,
                $v['ppw_due_date'] ?? null
            ),
            // The underwriter who placed the risk owns the line. One accountable
            // owner — it defaults to whoever is capturing, not to "anyone".
            'underwriter_id'            => $v['underwriter_id'] ?? Auth::id(),
            'underwriter_name'          => $v['underwriter_name'] ?? (Auth::user()->name ?? null),
            'status'                    => $v['status'] ?? 'placed',
            'notes'                     => $v['notes'] ?? null,
            'source'                    => 'manual',
            'created_by'                => Auth::id(),
            'updated_by'                => Auth::id(),
        ])));

        $this->register->recordEvent($row, 'created', sprintf(
            'Placement %s captured on %s for %s%s.',
            $row->fac_reference,
            $row->policy_number,
            $row->counterparty_name ?? 'the counterparty',
            match ($intent) {
                'new'    => ' — new placement, awaiting the reinsurer\'s signature',
                'signed' => ' — already signed by the reinsurer',
                default  => '',
            }
        ), [
            'policyInGraphite' => $row->policy_in_graphite,
            // Which process was followed. A placement that turns out to be wrong
            // later is answered first by "which of the two was this captured as?"
            'captureIntent'    => $intent,
        ]);

        // The schedule BEFORE the slip is generated, or the slip prints an empty
        // one. This is why it is written here rather than after the response.
        if (array_key_exists('schedule', $v) && is_array($v['schedule'])) {
            $this->syncSchedule($row, $v['schedule']);
        }

        // ── The slip, on save ───────────────────────────────────────────────
        //
        // A new placement is one we are asking a reinsurer to sign, so the slip is
        // part of capturing it, not a second errand to remember. It is rendered,
        // stored and filed against the line here — retrievable from the placement
        // and ready for send().
        //
        // A failure must NOT lose the placement. The PDF renderer is the one part
        // of this that depends on something outside the database, and a capture
        // thrown away because a renderer was down is a worse outcome than a
        // placement with no slip yet. It is recorded on the trail and the "Next
        // step" panel keeps asking for it.
        if ($intent === 'new') {
            try {
                $this->slips->generate($row->fac_slip_no, $row->placement_type);
                $row->refresh();
            } catch (\Throwable $e) {
                $this->register->recordEvent($row, 'slip_generation_failed', sprintf(
                    'The slip for %s could not be generated: %s',
                    $row->fac_slip_no,
                    $e->getMessage()
                ));
            }
        }

        return response()->json($this->show($row->id)->getData(true), 201);
    }

    public function show(int $id): JsonResponse
    {
        $p = FacPlacement::with(['attachments', 'events' => fn ($q) => $q->orderByDesc('id')])->find($id);
        if (!$p) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $row = $this->rowJson(DB::table('fac_placements')->where('id', $id)->first());

        $row['notes']       = $p->notes;
        $row['attachments'] = $p->attachments->map(fn ($a) => [
            'id'           => $a->id,
            'docType'      => $a->doc_type,
            'originalName' => $a->original_name,
            'paymentDate'  => optional($a->payment_date)->toDateString(),
            'amount'       => $a->amount !== null ? (float) $a->amount : null,
            'note'         => $a->note,
            'uploadedBy'   => $a->uploaded_by_name,
            'uploadedAt'   => optional($a->created_at)->toDateTimeString(),
        ]);
        $row['events'] = $p->events->map(fn ($e) => [
            'id'         => $e->id,
            'event'      => $e->event,
            'summary'    => $e->summary,
            'notifiedTo' => $e->notified_to,
            'sent'       => (bool) $e->notification_sent,
            'error'      => $e->notification_error,
            'actor'      => $e->actor_name,
            'at'         => optional($e->created_at)->toDateTimeString(),
            // Field-by-field before/after for an amendment. Written at the time of
            // the edit and replayed verbatim — never re-derived from the row, which
            // has moved on since.
            'changes'    => array_values($e->payload['changes'] ?? []),
        ]);

        if ($p->policy_id) {
            $row['receipts'] = $this->register->readClientReceipts((int) $p->policy_id);
        }
        $row['coverage']     = $this->coverage->forPolicy($p->policy_number);
        $row['participants'] = $this->participantsFor($p);

        // The treaty conditions, in full rather than as bare flag codes — the row
        // needs a code, the placement's own screen needs the wording and the
        // authority it comes from.
        $row['mandates']    = $this->mandates->findingsFor($p);
        $row['policyMonths'] = $this->mandates->policyMonths($p);
        $row['schedule']    = $this->scheduleFor($p);

        return response()->json($row);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Bordereau
    // ─────────────────────────────────────────────────────────────────────

    /**
     * The cession bordereau for a period, grouped by reinsurer.
     *
     * Separate from export(): that is a flat extract of the register, this is a
     * statement of account a broker or reinsurer agrees line by line. Month-end
     * control C7 reconciles the cession to this document.
     */
    public function bordereau(Request $request): JsonResponse
    {
        return response()->json($this->bordereaux->build($this->bordereauFilters($request)));
    }

    /** The same bordereau as a CSV, which is the form it is actually sent in. */
    public function bordereauCsv(Request $request)
    {
        $filters = $this->bordereauFilters($request);
        $b       = $this->bordereaux->build($filters);

        $name = 'fac-cession-bordereau-'
            . ($filters['period_to'] ?? now()->format('Y-m-d')) . '.csv';

        return response()->stream(function () use ($b) {
            $out = fopen('php://output', 'w');

            // The header block first. A bordereau that arrives without whose it is
            // and what period it covers cannot be agreed against anything.
            fputcsv($out, [$b['header']['cedant']]);
            fputcsv($out, [$b['header']['statement']]);
            fputcsv($out, ['Period', trim(($b['header']['periodFrom'] ?? '') . ' to ' . ($b['header']['periodTo'] ?? ''))]);
            if ($b['header']['financialYear']) {
                fputcsv($out, ['Financial year', $b['header']['financialYear']]);
            }
            fputcsv($out, ['Prepared', $b['header']['preparedAt']]);
            fputcsv($out, ['Basis', $b['header']['basis']]);
            fputcsv($out, []);

            // NAMED AS THE MASTER SPREADSHEET NAMES THEM, so a reader can put the
            // two documents side by side. Policy Type there means Annually /
            // Monthly / Quarterly — the premium frequency, not the class, which is
            // their "RI Group". Underwriter is the person a broker's query has to
            // reach, and this document never carried either of them.
            //
            // THE SLIP HEADING FOLLOWS THE BASIS, because their workbook keeps a
            // SHEET per basis rather than two columns: "FAC Analysis" is headed
            // "Fac Slip No." and "Auto FAC Analysis" is headed "Auto Fac Slip No.".
            // Filtered to one basis this reproduces their heading; unfiltered it
            // stays generic rather than claiming a basis the rows do not share.
            $slipHeading = match ($b['header']['placementType']) {
                'auto_fac' => 'Auto Fac Slip No.',
                'fac'      => 'Fac Slip No.',
                default    => 'Slip No.',
            };

            $cols = [
                'FAC Reference', 'Type', $slipHeading,
                'Policy Number', 'Policy Type', 'Insured Name',
                'RI Group', 'Policy Period', 'Slip Signed', 'Reinsurer/RI Broker',
                'Underwriter', 'Risk %',
                'Sum Insured Ceded', 'Currency', 'Gross Ceded Premium', 'Pula',
                'Premium Excl VAT',
                'Commission %', 'Commission', 'Pula', 'Commission Excl VAT',
                'Net Ceded Premium', 'Pula',
            ];

            // WHERE THE MONEY STARTS, FOUND RATHER THAN COUNTED. The subtotal and
            // grand-total rows pad with empties up to the first money column, and
            // the padding used to be nine literal ''s — which is correct until
            // somebody inserts a column, and then silently puts every total under
            // the wrong heading. Derived from the header so it cannot drift.
            $moneyAt = array_search('Sum Insured Ceded', $cols, true);
            $lead    = fn (string $label) => array_replace(
                array_fill(0, $moneyAt, ''),
                [0 => $label]
            );

            foreach ($b['groups'] as $g) {
                fputcsv($out, [$g['counterpartyName'] . ' — ' . $g['currency']]);
                fputcsv($out, $cols);

                foreach ($g['lines'] as $l) {
                    fputcsv($out, $this->bordereauCells($l));
                }

                $s = $g['subtotals'];
                fputcsv($out, array_merge($lead('Subtotal — ' . $g['counterpartyName']), [
                    $s['cessionSumInsured'], $g['currency'],
                    $s['grossCededPremium'], $this->pulaTotal($s, 'grossCededPremiumBwp'),
                    $s['premiumExclVat'],
                    '', $s['commission'], $this->pulaTotal($s, 'commissionBwp'),
                    $s['commissionExclVat'],
                    $s['netCededPremium'], $this->pulaTotal($s, 'netCededPremiumBwp'),
                ]));
                fputcsv($out, []);
            }

            // Reversals last and apart. Netting them into a subtotal hides the
            // movement the recipient is trying to agree.
            if ($b['reversals']) {
                fputcsv($out, ['REVERSALS — not netted into the subtotals above']);
                fputcsv($out, $cols);
                foreach ($b['reversals'] as $l) {
                    fputcsv($out, $this->bordereauCells($l));
                }
                fputcsv($out, []);
            }

            $t     = $b['grandTotal'];
            $total = $lead('GRAND TOTAL');
            $total[1] = $t['lineCount'] . ' lines';
            fputcsv($out, array_merge($total, [
                $t['cessionSumInsured'], 'mixed',
                $t['grossCededPremium'], $this->pulaTotal($t, 'grossCededPremiumBwp'),
                $t['premiumExclVat'],
                '', $t['commission'], $this->pulaTotal($t, 'commissionBwp'),
                $t['commissionExclVat'],
                $t['netCededPremium'], $this->pulaTotal($t, 'netCededPremiumBwp'),
            ]));

            // A GRAND TOTAL ACROSS CURRENCIES IS ONLY MEANINGFUL IN PULA, and the
            // "mixed" in the currency column says the other figures beside it are
            // not. Worth stating rather than leaving a reader to add francs to
            // dollars because they were in one column.
            if ($t['bwpMissing'] > 0) {
                fputcsv($out, ['', sprintf(
                    'Pula totals omitted: %d line%s carry no exchange rate, so a Pula total '
                    . 'would understate by those lines rather than fail.',
                    $t['bwpMissing'],
                    $t['bwpMissing'] === 1 ? '' : 's'
                )]);
            }

            // AN EMPTY BORDEREAU SAYS WHY, on the document itself and not only on
            // the screen. Reinsurance reported this module as not recording
            // placements on the strength of a nil CSV; a file that explains its own
            // emptiness cannot be mistaken for a broken export.
            if ($b['emptyReason'] !== null) {
                fputcsv($out, []);
                fputcsv($out, ['NO CESSIONS IN THIS PERIOD']);
                fputcsv($out, [$b['emptyReason']['summary']]);
                foreach ($b['emptyReason']['reasons'] as $why) {
                    fputcsv($out, ['', $why]);
                }
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }

    /**
     * A Pula total, or blank where one line in the section has no rate.
     *
     * SUPPRESSED RATHER THAN PARTIAL. A Pula subtotal that quietly omits the
     * lines it could not convert reads as the section's Pula value, and it is the
     * figure somebody agrees to the ledger. Blank asks a question; a short total
     * answers one wrongly.
     *
     * @param array<string,mixed> $t
     */
    private function pulaTotal(array $t, string $key): string
    {
        return ($t['bwpMissing'] ?? 0) > 0 ? '' : (string) $t[$key];
    }

    /** @param array<string,mixed> $l @return array<int,mixed> */
    private function bordereauCells(array $l): array
    {
        return [
            $l['facReference'], $l['placementType'], $l['slipNo'],
            $l['policyNumber'], $l['policyType'], $l['insuredName'], $l['riGroupLabel'],
            trim(($l['periodFrom'] ?? '') . ' to ' . ($l['periodTo'] ?? '')),
            $l['slipSignedDate'], $l['riskCarrier'], $l['underwriter'], $l['riskPct'],
            $l['cessionSumInsured'], $l['currency'],
            $l['grossCededPremium'], $l['grossCededPremiumBwp'], $l['premiumExclVat'],
            $l['commissionPct'], $l['commission'], $l['commissionBwp'],
            $l['commissionExclVat'],
            $l['netCededPremium'], $l['netCededPremiumBwp'],
        ];
    }

    /** @return array<string,mixed> */
    private function bordereauFilters(Request $request): array
    {
        return $request->validate([
            'period_from'     => 'nullable|date',
            'period_to'       => 'nullable|date|after_or_equal:period_from',
            'financial_year'  => 'nullable|string|max:9',
            'counterparty_id' => 'nullable|integer',
            'placement_type'  => ['nullable', Rule::in(['fac', 'auto_fac'])],
            'currency'        => 'nullable|string|max:3',
        ]);
    }

    /**
     * Who is on this placement, and for what share.
     *
     * Reinsurance asked to see the panel on both bases — who is participating on an
     * Auto FAC placement, and who has signed a FAC slip and for what proportion.
     * They are NOT the same question and must not be collapsed into one list:
     *
     *  · `lines` is who we PLACED the risk with. It comes from the placement lines
     *    sharing the slip number, so it exists from capture onwards, before anybody
     *    has signed anything.
     *  · `acceptances` is who has COMMITTED. It comes from the slip's acceptance
     *    panel, and a row only counts as committed once it carries a signatory and
     *    a date — an unaccepted slip is not cover.
     *
     * Both are returned with their own totals so the screen can show the gap rather
     * than imply a placement is fully covered when it is not. `reconciles` is a
     * report, not a validation: nothing here refuses a placement, because whether a
     * panel must add to 100% is a Reinsurance rule we have not been given.
     *
     * @return array<string,mixed>
     */
    private function participantsFor(FacPlacement $p): array
    {
        $lines = $p->fac_slip_no
            ? FacPlacement::query()
                ->where('fac_slip_no', $p->fac_slip_no)
                ->whereNull('deleted_at')
                ->where('is_reversal', false)
                ->orderBy('id')
                ->get()
            : collect([$p]);

        // The current slip for this number, if one has been generated. Superseded
        // versions are excluded — the panel a reinsurer holds is the latest one.
        $slip = $p->fac_slip_no
            ? FacSlip::with('acceptances')
                ->where('slip_no', $p->fac_slip_no)
                ->where('status', '!=', 'superseded')
                ->orderByDesc('version')
                ->first()
            : null;

        $acceptances = $slip
            ? $slip->acceptances->map(fn ($a) => [
                'id'              => $a->id,
                'reinsurerId'     => $a->reinsurer_id,
                'acceptingCompany' => $a->accepting_company,
                'sharePct'        => $a->share_pct !== null ? (float) $a->share_pct : null,
                'amount'          => $a->amount !== null ? (float) $a->amount : null,
                'signatoryName'   => $a->signatory_name,
                'acceptedOn'      => optional($a->accepted_on)->toDateString(),
                // The distinction that matters: sent is not signed.
                'committed'       => (bool) $a->accepted_on,
            ])->values()
            : collect();

        $lineRows = $lines->map(fn ($l) => [
            'id'                => $l->id,
            'facReference'      => $l->fac_reference,
            'counterpartyId'    => $l->counterparty_id,
            'counterpartyName'  => $l->counterparty_name,
            'riskCarrier'       => $l->risk_carrier,
            'riskPct'           => $l->risk_pct !== null ? (float) $l->risk_pct : null,
            'cessionSumInsured' => $l->cession_sum_insured !== null ? (float) $l->cession_sum_insured : null,
            'grossCededPremium' => $l->gross_ceded_premium !== null ? (float) $l->gross_ceded_premium : null,
            'currency'          => $l->currency,
            'status'            => $l->status,
        ])->values();

        $lineShare     = (float) $lines->sum(fn ($l) => (float) $l->risk_pct);
        $acceptedShare = (float) $acceptances->sum(fn ($a) => (float) ($a['sharePct'] ?? 0));
        $committed     = $acceptances->filter(fn ($a) => $a['committed'])->count();

        return [
            'basis'       => $p->placement_type === 'auto_fac' ? 'auto_fac' : 'fac',
            'slipNo'      => $p->fac_slip_no,
            'slipId'      => $slip?->id,
            'slipVersion' => $slip?->version,
            'slipStatus'  => $slip?->status,
            'lines'       => $lineRows,
            'acceptances' => $acceptances,
            'totals'      => [
                'lineCount'       => $lineRows->count(),
                'lineSharePct'    => round($lineShare, 6),
                'acceptanceCount' => $acceptances->count(),
                'committedCount'  => $committed,
                'acceptedPct'     => round($acceptedShare, 6),
                // Whether the panel adds up. Reported, never enforced.
                'reconciles'      => $acceptances->isNotEmpty()
                    && abs($acceptedShare - $lineShare) <= 0.000001,
                'fullyCommitted'  => $acceptances->isNotEmpty() && $committed === $acceptances->count(),
            ],
        ];
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $p = FacPlacement::find($id);
        if (!$p) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if ($p->status === 'cancelled') {
            return response()->json(['message' => 'A cancelled placement cannot be edited.'], 422);
        }
        if ($p->status === 'settled') {
            return response()->json(['message' => 'A settled placement cannot be edited. Reverse it in omni first.'], 422);
        }

        $v = $this->validatePlacement($request, false);

        // A wrong policy number was the ONE capture error nothing could fix.
        //
        // The field was validated here and then silently dropped — it is not in the
        // editable set below — so correcting it returned 200 and changed nothing,
        // which is worse than a refusal. The only route left was to cancel the line
        // and recapture it, which puts a reversal on the payable for a typo.
        //
        // Re-pointing re-reads the Graphite snapshot exactly as syncPolicy() does,
        // so the insured, the period and the two "is it really there?" flags follow
        // the new number instead of describing the old one.
        //
        // NOTE: `financial_year` is deliberately NOT re-derived. It decides which
        // month-end snapshot and journal figure the line belongs to, and some of
        // those periods are already closed — moving a line between them is a
        // Finance decision, not a side effect of fixing a typo.
        $repoint = [];
        $newPolicyNumber = isset($v['policy_number']) ? trim($v['policy_number']) : '';
        if ($newPolicyNumber !== '' && $newPolicyNumber !== $p->policy_number) {
            $lookup  = $this->register->lookupPolicy($newPolicyNumber);
            $repoint = [
                'policy_number'             => $newPolicyNumber,
                'policy_id'                 => $lookup['policyId'] ?? null,
                'policy_action_id'          => $lookup['policyActionId'] ?? null,
                'insured_name'              => $lookup['insuredName'] ?? $p->insured_name,
                'policy_type'               => $lookup['policyType'] ?? $p->policy_type,
                'period_from'               => $lookup['periodFrom'] ?? $p->period_from,
                'period_to'                 => $lookup['periodTo'] ?? $p->period_to,
                'policy_status'             => $lookup['policyStatus'] ?? null,
                'policy_in_graphite'        => (bool) ($lookup['inGraphite'] ?? false),
                'policy_active_in_graphite' => (bool) ($lookup['isActive'] ?? false),
                'policy_synced_at'          => now(),
            ];
        }

        try {
            $amounts = $this->register->computeAmounts(array_merge([
                'gross_ceded_premium' => $p->gross_ceded_premium,
                'commission_pct'      => $p->commission_pct,
                'vat_applicable'      => $p->vat_applicable,
                'vat_rate'            => $p->vat_rate,
                'currency'            => $p->currency,
                'fx_rate'             => $p->fx_rate,
                'fx_rate_date'        => optional($p->fx_rate_date)->toDateString(),
                'fx_rate_source'      => $p->fx_rate_source,
                // The rate table is consulted against the risk period when no rate
                // is supplied, so it has to see the period the line ENDS UP with.
                'period_from'         => $repoint['period_from'] ?? $p->period_from,
            ], $v));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $editable = array_intersect_key($v, array_flip([
            'fac_slip_no', 'placement_type', 'reinsurance_group_id', 'ri_group_label',
            'cession_sum_insured', 'source_premium', 'risk_pct', 'counterparty_id',
            'risk_carrier', 'slip_signed_date', 'ppw_due_date', 'ppw_days', 'ppw_terms', 'premium_frequency',
            'underwriter_id', 'underwriter_name', 'status',
            'notes', 'period_from', 'period_to', 'policy_type',
        ]));

        // Re-derive the due date from whatever the line ends up holding — an edit
        // that touches only the window still has to move the date the breach
        // alarm reads, and an edit that touches neither must leave it alone.
        if (array_key_exists('slip_signed_date', $editable)
            || array_key_exists('ppw_days', $editable)
            || array_key_exists('ppw_due_date', $editable)) {
            $editable['ppw_due_date'] = $this->register->resolvePpwDueDate(
                array_key_exists('slip_signed_date', $editable)
                    ? $editable['slip_signed_date']
                    : optional($p->slip_signed_date)->toDateString(),
                array_key_exists('ppw_days', $editable) ? $editable['ppw_days'] : $p->ppw_days,
                array_key_exists('ppw_due_date', $editable)
                    ? $editable['ppw_due_date']
                    : optional($p->ppw_due_date)->toDateString()
            );
        }

        // Belt and braces, in BOTH directions.
        //
        // Forwards: a status belonging to a permissioned endpoint never lands via
        // update(), even if the validation rule above is ever widened.
        //
        // Backwards: a line that has already moved PAST capture cannot be dragged
        // back into it. Checking only the incoming value left a hole — a
        // `ready_to_settle` placement, whose client premium is confirmed received,
        // could be sent back to `draft`, which is a capturable status. It would then
        // drop straight out of the payable, out of the frozen month-end snapshot and
        // out of the journal figure Finance posts. Reversing a confirmed liability
        // is a settlement-desk action, not an edit.
        if (isset($editable['status'])
            && (!in_array($editable['status'], self::CAPTURABLE_STATUSES, true)
                || !in_array($p->status, self::CAPTURABLE_STATUSES, true))) {
            unset($editable['status']);
        }

        if (!empty($editable['counterparty_id'])) {
            $editable['counterparty_name'] = DB::table('reinsurer')
                ->where('id', $editable['counterparty_id'])->value('company_name');
        }

        // The snapshot is taken from what is STORED, on both sides of the save, and
        // the trail is written from the difference. Reading the request instead
        // would record what was asked for rather than what happened — it would miss
        // every derived figure (the commission, the net, the Pula payable, the
        // warranty date) and it would claim a change on a field that was re-typed
        // with the same value.
        //
        // `$repoint` goes last: if the policy number moved, the Graphite snapshot
        // it just read is the truth about the insured and the period, not whatever
        // the form was holding for the old number.
        $before = $this->auditSnapshot($p);
        $p->update(array_merge($amounts, $editable, $repoint, ['updated_by' => Auth::id()]));
        $changes = $this->auditChanges($before, $this->auditSnapshot($p->refresh()));

        /*
         * The schedule, only when the request actually carried one.
         *
         * array_key_exists, not a truthiness check: an empty array means "clear the
         * schedule", a MISSING key means "leave it alone". A form that does not
         * render the schedule must not wipe it, which is what treating both the
         * same would do on every unrelated edit.
         *
         * Recorded on the trail as its own event rather than inside the field-level
         * changes, because it is a set replacement rather than a before/after value
         * and would read as noise in a column of field diffs.
         */
        if (array_key_exists('schedule', $v) && is_array($v['schedule'])) {
            $wasCount = FacPlacementScheduleItem::where('fac_placement_id', $p->id)->count();
            $this->syncSchedule($p, $v['schedule']);
            $now = $this->scheduleFor($p);

            if ($wasCount !== $now['lineCount']
                || $wasCount > 0
                || $now['lineCount'] > 0) {
                $this->register->recordEvent($p, 'schedule_updated', sprintf(
                    'Schedule replaced — %d %s, total limits of indemnity %s.',
                    $now['lineCount'],
                    $now['lineCount'] === 1 ? 'line' : 'lines',
                    number_format($now['totalLimitsOfIndemnity'], 2)
                ), ['lineCount' => $now['lineCount'], 'total' => $now['totalLimitsOfIndemnity']]);
            }
        }

        // An edit that changed nothing is not an amendment. Recording one anyway
        // fills the trail with entries that a reader has to open to discover are
        // empty, which is how a trail stops being read.
        if ($changes) {
            $this->register->recordEvent(
                $p,
                'updated',
                $this->amendmentSummary($p, $changes),
                ['changes' => $changes]
            );
        }

        return $this->show($id);
    }

    /**
     * Every field a correction is recorded against, and how each one reads.
     *
     * Derived figures are in here as well as typed ones. A capturer who fixes the
     * risk % has, in the same breath, moved the commission, the net, the VAT split
     * and the Pula payable that Finance posts — so those are what the trail has to
     * show. Recording only the field that was touched would leave the reader to
     * redo the arithmetic to find out what the edit actually cost.
     *
     * `updated_at` and `updated_by` are deliberately absent: the event carries its
     * own actor and timestamp, so listing them as changed fields is noise.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const AUDITED_FIELDS = [
        'policy_number'                => ['Policy number', 'text'],
        'insured_name'                 => ['Insured', 'text'],
        'policy_type'                  => ['Policy type', 'text'],
        'period_from'                  => ['Period from', 'date'],
        'period_to'                    => ['Period to', 'date'],
        'placement_type'               => ['Basis', 'label'],
        'fac_slip_no'                  => ['FAC slip number', 'text'],
        'ri_group_label'               => ['RI group', 'text'],
        'cession_sum_insured'          => ['Sum insured ceded', 'money'],
        'risk_pct'                     => ['Total risk %', 'pct'],
        'counterparty_name'            => ['We pay', 'text'],
        'risk_carrier'                 => ['Risk carried by', 'text'],
        'underwriter_name'             => ['Underwriter', 'text'],
        'currency'                     => ['Currency', 'text'],
        'source_premium'               => ['Full policy premium', 'money'],
        'gross_ceded_premium'          => ['Gross ceded premium', 'money'],
        'commission_pct'               => ['Commission %', 'pct'],
        'commission_amount'            => ['Commission', 'money'],
        'net_ceded_premium'            => ['Net ceded premium', 'money'],
        'vat_applicable'               => ['Botswana VAT applies', 'bool'],
        'vat_rate'                     => ['VAT rate', 'pct'],
        'gross_ceded_premium_excl_vat' => ['Gross excluding VAT', 'money'],
        'gross_ceded_premium_bwp'      => ['Payable in Pula', 'money'],
        'fx_rate'                      => ['Exchange rate', 'rate'],
        'fx_rate_date'                 => ['Rate date', 'date'],
        'fx_rate_source'               => ['Rate source', 'text'],
        'slip_signed_date'             => ['Slip signed on', 'date'],
        'ppw_terms'                    => ['Payment window', 'text'],
        'premium_frequency'            => ['Premium payment frequency', 'text'],
        'ppw_days'                     => ['Payment window in days', 'int'],
        'ppw_due_date'                 => ['Premium due date', 'date'],
        'policy_in_graphite'           => ['Found in Graphite', 'bool'],
        'policy_active_in_graphite'    => ['Active in Graphite', 'bool'],
        'status'                       => ['Status', 'label'],
        'notes'                        => ['Notes', 'text'],
    ];

    /** @return array<string, string|null> */
    private function auditSnapshot(FacPlacement $p): array
    {
        $out = [];
        foreach (self::AUDITED_FIELDS as $field => [, $kind]) {
            $out[$field] = $this->auditValue($p->getAttribute($field), $kind);
        }

        return $out;
    }

    /**
     * One stored value, as it should read to whoever audits the change.
     *
     * Formatting happens HERE, once, rather than in the screen that renders the
     * trail: the trail is a record, and a record that is re-derived at display time
     * can be re-derived differently later. What is written is what will be read.
     */
    private function auditValue(mixed $raw, string $kind): ?string
    {
        // An empty string and a NULL are the same absence. Treating them as
        // different would report "Notes changed" every time a form posts '' over a
        // column that was already blank.
        if ($raw === null || $raw === '') {
            return $kind === 'bool' ? 'no' : null;
        }

        return match ($kind) {
            'money' => number_format((float) $raw, 2, '.', ','),
            'pct'   => $this->pctLabel((float) $raw),
            // Rates carry up to eight places but almost never use them. Trimming
            // the zeros keeps 12.9 from reading as 12.90000000 next to 12.91.
            'rate'  => rtrim(rtrim(number_format((float) $raw, 8, '.', ''), '0'), '.'),
            'date'  => $raw instanceof \DateTimeInterface ? $raw->format('Y-m-d') : (string) $raw,
            'bool'  => $raw ? 'yes' : 'no',
            'int'   => (string) (int) $raw,
            'label' => str_replace('_', ' ', (string) $raw),
            default => (string) $raw,
        };
    }

    /**
     * A stored share as a percentage.
     *
     * Two places minimum, so 27.5 reads as a rate rather than a count, and up to
     * four kept because risk_pct is decimal(12,6) — collapsing 17.0714% to 17.07%
     * would hide a real correction behind a rounding.
     */
    private function pctLabel(float $share): string
    {
        $shown = rtrim(rtrim(number_format($share * 100, 4, '.', ''), '0'), '.');
        $dot   = strpos($shown, '.');

        if ($dot === false) {
            $shown .= '.00';
        } elseif (strlen(substr($shown, $dot + 1)) === 1) {
            $shown .= '0';
        }

        return $shown . '%';
    }

    /**
     * @param  array<string, string|null>  $before
     * @param  array<string, string|null>  $after
     * @return list<array{field: string, label: string, from: string|null, to: string|null}>
     */
    private function auditChanges(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $field => $to) {
            $from = $before[$field] ?? null;
            if ($from === $to) {
                continue;
            }
            $changes[] = [
                'field' => $field,
                'label' => self::AUDITED_FIELDS[$field][0],
                'from'  => $from,
                'to'    => $to,
            ];
        }

        return $changes;
    }

    /**
     * The one-line summary, naming the fields so the trail is readable without
     * opening every entry. Capped because `summary` is 500 characters and a single
     * change to the risk % moves eight derived figures with it.
     *
     * @param  list<array{label: string}>  $changes
     */
    private function amendmentSummary(FacPlacement $p, array $changes): string
    {
        $labels = array_column($changes, 'label');
        $shown  = array_slice($labels, 0, 6);
        $extra  = count($labels) - count($shown);

        return sprintf(
            'Placement %s amended — %s%s.',
            $p->fac_reference,
            implode(', ', $shown),
            $extra > 0 ? sprintf(' and %d more field%s', $extra, $extra === 1 ? '' : 's') : ''
        );
    }

    private function validatePlacement(Request $request, bool $creating = true): array
    {
        /**
         * The signing date is REQUIRED when the capturer said the slip is already
         * signed.
         *
         * Capture used to ask one set of questions for two different jobs, so a
         * placement recorded off a signed slip could be saved with no signing
         * date — and that date is what the premium payment warranty counts from,
         * so the line joined the payable with no deadline anything could enforce.
         * The entry choice is what makes this knowable, so it is enforced here
         * rather than left to the form.
         */
        $signedDateRules = ($creating && $request->input('capture_intent') === 'signed')
            ? 'required|date'
            : 'nullable|date';

        return $request->validate([
            'policy_number'        => ($creating ? 'required' : 'nullable') . '|string|max:60',
            'counterparty_id'      => ($creating ? 'required' : 'nullable') . '|integer|exists:reinsurer,id',
            'gross_ceded_premium'  => ($creating ? 'required' : 'nullable') . '|numeric',
            'placement_type'       => ['nullable', Rule::in(['fac', 'auto_fac'])],
            'fac_slip_no'          => 'nullable|string|max:60',
            'financial_year'       => 'nullable|string|max:9',
            'insured_name'         => 'nullable|string|max:255',
            'policy_type'          => 'nullable|string|max:40',
            'period_from'          => 'nullable|date',
            'period_to'            => 'nullable|date|after_or_equal:period_from',
            'reinsurance_group_id' => 'nullable|integer',
            'ri_group_label'       => 'nullable|string|max:160',
            'cession_sum_insured'  => 'nullable|numeric',
            'source_premium'       => 'nullable|numeric|min:0',
            'risk_pct'             => 'nullable|numeric|min:0|max:1',
            'risk_carrier'         => 'nullable|string|max:255',
            'currency'             => 'nullable|string|size:3',
            'commission_pct'       => 'nullable|numeric|min:0|max:1',
            'vat_applicable'       => 'nullable|boolean',
            'vat_rate'             => 'nullable|numeric|min:0|max:1',
            'fx_rate'              => 'nullable|numeric|gt:0',
            'fx_rate_date'         => 'nullable|date',
            'fx_rate_source'       => 'nullable|string|max:120',
            'slip_signed_date'     => $signedDateRules,
            'ppw_due_date'         => 'nullable|date',
            // Which of the two capture processes the user picked at entry. Never a
            // free-text hint: it decides whether the line joins the payable.
            'capture_intent'       => ['nullable', Rule::in(['new', 'signed'])],
            'ppw_days'             => 'nullable|integer|min:1|max:1095',
            'ppw_terms'            => 'nullable|string|max:60',
            'underwriter_id'       => 'nullable|integer',
            'underwriter_name'     => 'nullable|string|max:160',
            // A state field accepted from the request IS a permission bypass. Only
            // the pre-settlement statuses may be set here; settled / client_paid /
            // ready_to_settle / cancelled are reached ONLY through their own
            // permissioned endpoints, which also write the reference, the actor and
            // (for a cancellation) the reversing row. Without this a
            // reinsurance-fac-edit user could PUT status=settled with no reference
            // and no settled_by, or status=cancelled with no reversal — silently
            // breaking the payable the register exists to get right.
            'status'               => ['nullable', Rule::in(self::CAPTURABLE_STATUSES)],
            'notes'                => 'nullable|string|max:2000',

            /*
             * The schedule the slip prints — what is actually insured.
             *
             * Sent whole, not line by line: the underwriter works from a schedule
             * and edits it as one thing, so the save replaces the set rather than
             * patching rows. Omitting the key leaves the existing schedule alone;
             * sending an empty array clears it. Those are different intentions and
             * must not be the same request.
             *
             * `amount` is nullable on purpose. "Indemnity period – 15 months" is a
             * real line that states no money, and 0 would be counted as nil cover.
             */
            /*
             * How the ceded premium is paid. NOT the premium payment warranty —
             * slip 2026-002 carries both and they are different terms: the warranty
             * is the deadline for the premium to reach us ("90 DAY PPW"), the
             * frequency is how it is broken up ("Quarterly Payments").
             */
            'premium_frequency'    => ['nullable', Rule::in(['annual', 'quarterly', 'monthly', 'semi_annual'])],

            'schedule'             => 'nullable|array|max:60',
            'schedule.*.section'   => ['required_with:schedule', Rule::in(FacPlacementScheduleItem::SECTIONS)],
            'schedule.*.label'     => 'required_with:schedule|string|max:160',
            'schedule.*.amount'    => 'nullable|numeric|min:0',
        ], self::VALIDATION_MESSAGES, self::FIELD_LABELS);
    }

    /**
     * Replace a placement's schedule with what was sent.
     *
     * REPLACE, not merge. The underwriter is working from one schedule and the
     * request carries the whole of it, so reconciling line by line would leave
     * orphans behind whenever a line was removed. Soft-deleted rather than hard,
     * because a line that was on a slip already sent to a reinsurer still has to be
     * explainable afterwards.
     *
     * Order is taken from the order sent, so the printed schedule reads like the
     * document the underwriter was copying from.
     *
     * @param  array<int,array<string,mixed>>  $schedule
     */
    private function syncSchedule(FacPlacement $p, array $schedule): void
    {
        DB::transaction(function () use ($p, $schedule) {
            FacPlacementScheduleItem::where('fac_placement_id', $p->id)->delete();

            foreach (array_values($schedule) as $i => $row) {
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '') {
                    continue;
                }

                FacPlacementScheduleItem::create([
                    'fac_placement_id' => $p->id,
                    'section'          => $row['section'],
                    'label'            => $label,
                    // Blank stays NULL. Only a figure actually given becomes 0.00,
                    // which on a schedule means nil cover rather than "not stated".
                    'amount'           => ($row['amount'] ?? '') === '' || ($row['amount'] ?? null) === null
                        ? null
                        : (float) $row['amount'],
                    'sort_order'       => $i,
                ]);
            }
        });
    }

    /**
     * The schedule as the slip and the screens read it, with its own total.
     *
     * The total is DERIVED here rather than stored. The signed slips prove the two
     * are the same figure — 273,800,000 of fire plus 26,780,000 of business
     * interruption is exactly the 300,580,000 slip 2026-002 states — so storing it
     * separately would only create something that could disagree with the lines it
     * totals.
     *
     * @return array<string,mixed>
     */
    private function scheduleFor(FacPlacement $p): array
    {
        // Delegated to the model so the API and the printed slip cannot compute a
        // different total from the same lines.
        return FacPlacementScheduleItem::scheduleFor((int) $p->id);
    }

    /**
     * Field names as the capture form labels them.
     *
     * The form shows the server's complaint against the field it belongs to, so
     * "The risk pct field is invalid" would appear under a field labelled
     * "Total risk %". Reinsurance reported on 10 Aug 2026 that a rejection said
     * only "The given data was invalid" and named nothing at all; naming the
     * wrong thing would be no better.
     */
    private const FIELD_LABELS = [
        'policy_number'       => 'policy number',
        'counterparty_id'     => 'who we pay',
        'gross_ceded_premium' => 'gross ceded premium',
        'source_premium'      => 'full policy premium',
        'placement_type'      => 'basis',
        'fac_slip_no'         => 'FAC slip number',
        'ri_group_label'      => 'RI group',
        'cession_sum_insured' => 'sum insured ceded',
        'risk_pct'            => 'total risk %',
        'risk_carrier'        => 'risk carried by',
        'commission_pct'      => 'commission %',
        'fx_rate'             => 'exchange rate',
        'fx_rate_date'        => 'rate date',
        'fx_rate_source'      => 'rate source',
        'slip_signed_date'    => 'slip signed date',
        'ppw_due_date'        => 'premium due date',
        'ppw_days'            => 'payment window',
        'ppw_terms'           => 'payment window',
    ];

    /**
     * Percentages are STORED as decimals (0.275 for 27.5%) because that is how
     * the arithmetic and the June reconciliation work. The capture form now takes
     * them as percentages and converts, so a value above 1 arriving here means
     * something bypassed the form — say so in those terms rather than showing a
     * bare "must not be greater than 1", which reads as nonsense against a field
     * labelled with a % sign.
     */
    private const VALIDATION_MESSAGES = [
        'risk_pct.max'       => 'The total risk % must be 100% or less.',
        'commission_pct.max' => 'The commission % must be under 100%.',
        'risk_pct.min'       => 'The total risk % cannot be negative.',
        'commission_pct.min' => 'The commission % cannot be negative.',
        'fx_rate.gt'         => 'The exchange rate must be greater than zero.',
        'ppw_days.max'       => 'The payment window cannot be longer than three years.',
        'counterparty_id.exists' => 'Select who we pay from the list.',
    ];

    /** Refresh the Graphite snapshot on a line. */
    public function syncPolicy(int $id): JsonResponse
    {
        $p = FacPlacement::find($id);
        if (!$p) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $lookup = $this->register->lookupPolicy($p->policy_number);
        $p->update([
            'policy_id'                 => $lookup['policyId'] ?? null,
            'policy_action_id'          => $lookup['policyActionId'] ?? $p->policy_action_id,
            'insured_name'              => $lookup['insuredName'] ?? $p->insured_name,
            'policy_type'               => $lookup['policyType'] ?? $p->policy_type,
            'period_from'               => $lookup['periodFrom'] ?? $p->period_from,
            'period_to'                 => $lookup['periodTo'] ?? $p->period_to,
            'policy_status'             => $lookup['policyStatus'] ?? $p->policy_status,
            'policy_in_graphite'        => (bool) ($lookup['inGraphite'] ?? false),
            'policy_active_in_graphite' => (bool) ($lookup['isActive'] ?? false),
            'policy_synced_at'          => now(),
        ]);

        return $this->show($id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Money legs
    // ─────────────────────────────────────────────────────────────────────

    public function markClientPaid(Request $request, int $id): JsonResponse
    {
        $p = FacPlacement::find($id);
        if (!$p) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $v = $request->validate([
            'source'  => ['nullable', Rule::in(['graphite', 'upload', 'manual'])],
            'amount'  => 'nullable|numeric',
            'paid_at' => 'nullable|date',
        ]);

        // Prefer Graphite's own record over anything typed in. The manual route
        // is the exception, for premiums Graphite does not show.
        if ($p->policy_id && ($v['source'] ?? 'manual') !== 'upload') {
            $r = $this->register->readClientReceipts((int) $p->policy_id);
            if ($r['hasReceipt']) {
                $v['source']  = 'graphite';
                $v['amount']  = $r['received'];
                $v['paid_at'] = $r['lastPaymentAt'] ?? ($v['paid_at'] ?? null);
            }
        }

        try {
            $this->register->markClientPaid(
                $p,
                $v['source'] ?? 'manual',
                isset($v['amount']) ? (float) $v['amount'] : null,
                $v['paid_at'] ?? null
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->show($id);
    }

    public function settle(Request $request, int $id): JsonResponse
    {
        $p = FacPlacement::find($id);
        if (!$p) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $v = $request->validate([
            'settlement_reference' => 'required|string|max:120',
            'settled_amount'       => 'required|numeric',
            'settled_at'           => 'nullable|date',
        ]);

        try {
            $this->register->settle(
                $p,
                $v['settlement_reference'],
                (float) $v['settled_amount'],
                $v['settled_at'] ?? null
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->show($id);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $p = FacPlacement::find($id);
        if (!$p) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $v = $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->register->cancel($p, $v['reason']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->show($id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Attachments
    // ─────────────────────────────────────────────────────────────────────

    public function storeAttachment(Request $request, int $id): JsonResponse
    {
        $p = FacPlacement::find($id);
        if (!$p) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $v = $request->validate([
            'file'         => 'required|file|max:20480|mimes:pdf,jpg,jpeg,png,xlsx,xls,csv,doc,docx',
            'doc_type'     => ['required', Rule::in(['client_pop', 'ri_payment_advice', 'fac_slip', 'other'])],
            'payment_date' => 'nullable|date',
            'amount'       => 'nullable|numeric',
            'note'         => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $path = Storage::disk($this->documentDisk())->putFileAs(
            'fac/' . $p->fac_reference,
            $file,
            now()->format('YmdHis') . '-' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName())
        );

        $a = FacPlacementAttachment::create([
            'fac_placement_id' => $p->id,
            'doc_type'         => $v['doc_type'],
            'original_name'    => $file->getClientOriginalName(),
            'path'             => $path,
            'mime'             => $file->getClientMimeType(),
            'size'             => $file->getSize(),
            'payment_date'     => $v['payment_date'] ?? null,
            'amount'           => $v['amount'] ?? null,
            'note'             => $v['note'] ?? null,
            'uploaded_by'      => Auth::id(),
            'uploaded_by_name' => Auth::user()->name ?? null,
        ]);

        // A proof of CLIENT payment moves the line and tells Debtors and RI.
        if ($v['doc_type'] === 'client_pop' && !$p->client_paid_at) {
            try {
                $this->register->markClientPaid(
                    $p,
                    'upload',
                    $v['amount'] ?? null,
                    $v['payment_date'] ?? null
                );
            } catch (\RuntimeException $e) {
                // THE DOCUMENT STAYS, THE PROMOTION DOES NOT. markClientPaid now
                // refuses a line with no signed slip on file, and a client proof
                // of payment arriving before the reinsurer has countersigned is a
                // normal sequence rather than an error — the client pays us on
                // our terms, the reinsurer signs on theirs.
                //
                // Both of the other courses are wrong. Letting the exception out
                // would 500 the request with the file already stored and the
                // attachment row already written; rejecting the upload would
                // throw away a real document because of a condition that has
                // nothing to do with it.
                //
                // So the POP is filed, the line is held, and the reason goes on
                // the event trail — which show() returns and the detail page
                // renders, so it is visible rather than silent. The line is
                // promoted by the sweep on the run after its slip is filed.
                $this->register->recordEvent(
                    $p,
                    'client_paid_withheld',
                    sprintf(
                        'Client proof of payment filed (%s), but the line was NOT queued for payment. %s',
                        $a->original_name,
                        $e->getMessage()
                    ),
                    ['attachmentId' => $a->id, 'reason' => $e->getMessage()],
                    ['debtors', 'ri_team']
                );
            }
        } else {
            $this->register->recordEvent(
                $p,
                'attachment_added',
                "Document attached: {$a->original_name} ({$v['doc_type']})."
            );
        }

        return $this->show($id);
    }

    /**
     * File the signed slip — the step that closes the "new placement" process.
     *
     * The two halves have to happen together, which is why this is one endpoint
     * and not an upload followed by an edit. Filing the document without the date
     * leaves a signed placement with no warranty deadline; setting the date
     * without the document leaves a payable with nothing behind it. Done
     * separately, either half can fail on its own and leave the line in a state
     * that reads as complete and is not.
     *
     * It is also what promotes a draft into the payable. Nothing else does: a
     * placement is owed when the reinsurer has signed for it, and this is the
     * moment the register learns that.
     *
     * It closes the acceptance panel too. The countersigned document IS the
     * reinsurer's commitment, so filing it is the only evidence the panel will
     * ever get — leaving `accepted_on` null afterwards left every placement
     * reporting "nobody on this panel has signed" against slips Reinsurance
     * physically held. Only the signer's rows close: a slip can carry a panel of
     * several reinsurers, and one countersigned document commits its signer and
     * nobody else.
     */
    public function storeSignedSlip(Request $request, int $id): JsonResponse
    {
        $p = FacPlacement::find($id);
        if (!$p) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if (in_array($p->status, ['settled', 'cancelled'], true)) {
            return response()->json([
                'message' => "A {$p->status} placement cannot take a signed slip.",
            ], 422);
        }

        $v = $request->validate([
            'file'             => 'required|file|max:20480|mimes:pdf,jpg,jpeg,png,doc,docx',
            'slip_signed_date' => 'required|date|before_or_equal:today',
            'note'             => 'nullable|string|max:500',
            // Who signed for the reinsurer. Optional, because the panel already
            // knows WHICH company accepted — the name is the person, and the
            // countersigned document is not always legible.
            'signatory_name'   => 'nullable|string|max:160',
        ], [
            'slip_signed_date.before_or_equal' => 'The slip cannot have been signed in the future.',
            'file.required'  => 'Attach the signed slip.',
            'file.mimes'     => 'The signed slip must be a PDF, an image or a Word document.',
        ], ['slip_signed_date' => 'date the reinsurer signed']);

        $file = $request->file('file');

        $result = DB::transaction(function () use ($p, $v, $file) {
            $path = Storage::disk($this->documentDisk())->putFileAs(
                'fac/' . $p->fac_reference,
                $file,
                now()->format('YmdHis') . '-' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName())
            );

            $a = FacPlacementAttachment::create([
                'fac_placement_id' => $p->id,
                'doc_type'         => 'fac_slip',
                'original_name'    => $file->getClientOriginalName(),
                'path'             => $path,
                'mime'             => $file->getClientMimeType(),
                'size'             => $file->getSize(),
                'note'             => $v['note'] ?? null,
                'uploaded_by'      => Auth::id(),
                'uploaded_by_name' => Auth::user()->name ?? null,
            ]);

            $wasDraft = $p->status === 'draft';

            $p->update([
                'slip_signed_date' => $v['slip_signed_date'],
                // The warranty deadline is derived, never typed — signing date plus
                // the window agreed on the slip. Until now the line had no date the
                // breach alarm could watch.
                'ppw_due_date'     => $this->register->resolvePpwDueDate(
                    $v['slip_signed_date'],
                    $p->ppw_days,
                    null
                ),
                'status'           => $wasDraft ? 'placed' : $p->status,
                'updated_by'       => Auth::id(),
            ]);

            $panel = $this->closeAcceptancePanel($p, $v, $path);

            return [$a, $wasDraft, $panel];
        });

        [$attachment, $wasDraft, $panel] = $result;

        $this->register->recordEvent($p, 'slip_signed', sprintf(
            'Signed slip filed for %s — signed %s%s.%s%s',
            $p->fac_reference,
            $v['slip_signed_date'],
            $p->ppw_due_date ? ', premium due ' . $p->ppw_due_date->toDateString() : '',
            $wasDraft ? ' The placement is now payable.' : '',
            $panel['closed'] > 0
                ? sprintf(
                    ' %d acceptance %s closed%s.',
                    $panel['closed'],
                    $panel['closed'] === 1 ? 'line' : 'lines',
                    $panel['slipAccepted']
                        ? ' — the whole panel has now signed'
                        : ($panel['outstanding'] > 0
                            ? ", {$panel['outstanding']} still outstanding on the panel"
                            : '')
                )
                : ''
        ), [
            'attachmentId'   => $attachment->id,
            'document'       => $attachment->original_name,
            'slipSignedDate' => $v['slip_signed_date'],
            'ppwDueDate'     => optional($p->ppw_due_date)->toDateString(),
            'becamePayable'  => $wasDraft,
            'panelClosed'    => $panel['closed'],
            'panelOutstanding' => $panel['outstanding'],
            'slipAccepted'   => $panel['slipAccepted'],
            // A draft becoming payable is the one moment here that changes what the
            // company owes, so the RI team is told. Filing a slip against a line
            // that was already payable changes no figure and needs no alert.
        ], $wasDraft ? ['ri_team'] : []);

        return $this->show($id);
    }

    /**
     * Record the reinsurer's commitment on the slip's acceptance panel.
     *
     * Called only from storeSignedSlip, inside its transaction, because the
     * countersigned document and the acceptance are the same fact: the panel must
     * not close unless the document filed, and the document must not file unless
     * the panel closes with it.
     *
     * SCOPED TO THE SIGNER. A slip carries a panel — several reinsurers each
     * taking a share of one risk — and a countersigned document commits the
     * company that signed it and nobody else. Rows are matched on reinsurer_id,
     * null included, so a placement with no counterparty closes the unattributed
     * row rather than the whole panel.
     *
     * ALREADY-SIGNED ROWS ARE LEFT ALONE. Filing a second document against a line
     * that has already accepted must not restate the first reinsurer's signing
     * date — that date is evidence, and the earlier one is the one that holds.
     *
     * The SLIP only reaches "accepted" when every row has signed. A partly signed
     * panel is not cover for the shares nobody has taken.
     *
     * @param  array<string,mixed>  $v      validated request
     * @param  string|bool          $path   stored path of the countersigned document
     * @return array{closed:int, outstanding:int, slipAccepted:bool}
     */
    private function closeAcceptancePanel(FacPlacement $p, array $v, $path): array
    {
        $none = ['closed' => 0, 'outstanding' => 0, 'slipAccepted' => false];

        if (!$p->fac_slip_id) {
            return $none;
        }

        $slip = FacSlip::find($p->fac_slip_id);
        if (!$slip) {
            return $none;
        }

        $rows = FacSlipAcceptance::where('fac_slip_id', $slip->id)
            ->whereNull('accepted_on')
            ->where(function ($q) use ($p) {
                $p->counterparty_id
                    ? $q->where('reinsurer_id', $p->counterparty_id)
                    : $q->whereNull('reinsurer_id');
            })
            ->get();

        foreach ($rows as $row) {
            $row->update([
                'accepted_on'          => $v['slip_signed_date'],
                // The company is what the panel already holds; the signatory is the
                // person. Fall back to the company so the row never reads as signed
                // by nobody.
                'signatory_name'       => $v['signatory_name'] ?? $row->accepting_company,
                'signed_document_path' => is_string($path) ? $path : null,
            ]);
        }

        $outstanding = FacSlipAcceptance::where('fac_slip_id', $slip->id)
            ->whereNull('accepted_on')
            ->count();

        $slipAccepted = false;
        if ($outstanding === 0 && $rows->isNotEmpty() && $slip->status !== 'accepted') {
            $slip->update(['status' => 'accepted']);
            $slipAccepted = true;
        }

        return [
            'closed'       => $rows->count(),
            'outstanding'  => $outstanding,
            'slipAccepted' => $slipAccepted,
        ];
    }

    public function downloadAttachment(int $id, int $attId)
    {
        $a = FacPlacementAttachment::where('fac_placement_id', $id)->find($attId);
        if (!$a) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json([
            'url'  => $this->documentUrl($this->documentDisk(), $a->path),
            'name' => $a->original_name,
        ]);
    }

    /** Where placement documents live. s3 in production; switchable for testing. */
    private function documentDisk(): string
    {
        return (string) config('fac.documents.disk', 's3');
    }

    /**
     * A link to a stored document, on whatever disk it is on.
     *
     * s3 gives a signed URL that expires. The local and public drivers cannot sign
     * anything and throw outright, which would make every download button on a
     * non-s3 environment return a 500 — so they fall back to a plain URL. The
     * fallback is for test environments; production is s3 and stays signed.
     */
    private function documentUrl(string $disk, string $path): string
    {
        try {
            return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(10));
        } catch (\Throwable $e) {
            return Storage::disk($disk)->url($path);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Slips
    // ─────────────────────────────────────────────────────────────────────

    public function generateSlip(Request $request): JsonResponse
    {
        $v = $request->validate([
            'slip_no'        => 'required|string|max:60',
            'placement_type' => ['nullable', Rule::in(['fac', 'auto_fac'])],
        ]);

        try {
            $slip = $this->slips->generate($v['slip_no'], $v['placement_type'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Slip generated.', 'data' => $this->slipJson($slip)]);
    }

    public function sendSlip(Request $request, int $slipId): JsonResponse
    {
        $slip = FacSlip::find($slipId);
        if (!$slip) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $v = $request->validate(['to' => 'nullable|email']);

        try {
            $slip = $this->slips->send($slip, $v['to'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Slip sent.', 'data' => $this->slipJson($slip)]);
    }

    /**
     * Type the term-sheet wording onto a slip.
     *
     * These five rows are legal terms on a document the reinsurer signs, and
     * until now none of them could be entered at all: the columns existed, the
     * template printed them, and nothing in the system ever wrote them — so every
     * slip printed the migration default forever, whatever the placement actually
     * agreed.
     *
     * BASIS OF COVER: THE POLICY DEFAULTS IT, THE UNDERWRITER MAY OVERRIDE IT.
     *
     * Reinsurance's rule of 24 August 2026 was that the slip follows the policy
     * — claims made on a claims-made policy — and a typed value was REFUSED with
     * a 422 wherever the policy had an answer. On 7 September they asked for the
     * opposite: the underwriter states the basis "as per the terms they have
     * agreed with the reinsurer", because a facultative cession can be written on
     * a different basis from the policy underneath it. Both positions are right
     * about their own risk.
     *
     * So the refusal is gone and the reason for it is not. The policy still
     * supplies the default, an override is accepted, and
     * `basis_of_cover_source` records which authority stated it — with an event
     * on the trail naming the policy's answer and what it was overridden to.
     * A slip that contradicts its policy is now possible, deliberately, but it
     * can no longer happen quietly.
     *
     * A SENT SLIP CANNOT BE RETYPED. The reinsurer already holds that document.
     * Correcting it means generating a new version, which is what the version
     * counter is for.
     */
    public function updateSlipTerms(Request $request, int $slipId): JsonResponse
    {
        $slip = FacSlip::with('acceptances')->find($slipId);
        if (!$slip) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (in_array($slip->status, ['sent', 'accepted', 'superseded'], true)) {
            return response()->json([
                'message' => "This slip is {$slip->status}, so its wording can no longer be changed. "
                    . 'Generate a new version to correct it.',
            ], 422);
        }

        $v = $request->validate([
            'description_of_risk' => 'nullable|string|max:2000',
            'territorial_scope'   => 'nullable|string|max:300',
            'deductible_text'     => 'nullable|string|max:300',
            'risk_ceded_text'     => 'nullable|string|max:200',
            'basis_of_cover'      => 'nullable|string|max:120',
            // The placement terms that do not fit one of the named rows above.
            // Free text, because that is what Reinsurance asked for: the
            // conditions vary per placement and are transcribed from the slip
            // the panel signed.
            'slip_notes'          => 'nullable|string|max:4000',
        ], [], [
            'description_of_risk' => 'description of risk',
            'territorial_scope'   => 'territorial scope',
            'deductible_text'     => 'deductible',
            'risk_ceded_text'     => 'risk ceded',
            'basis_of_cover'      => 'basis of cover',
            'slip_notes'          => 'notes',
        ]);

        /*
         * An override of the policy's basis is recorded, not refused.
         *
         * `$overrode` is only true where the policy HAS an answer and the typed
         * value differs from it. Typing the policy's own answer back is not an
         * override, and stating a basis on a policy that carries none was always
         * allowed — neither should read as a contradiction on the trail.
         */
        $policyBasis = $this->register->basisOfCoverFor(
            $slip->policy_id ? (int) $slip->policy_id : null
        );
        $typedBasis = array_key_exists('basis_of_cover', $v) ? ($v['basis_of_cover'] ?: null) : null;
        $overrode   = $typedBasis !== null
            && $policyBasis
            && strcasecmp(trim($typedBasis), trim($policyBasis)) !== 0;

        if (array_key_exists('basis_of_cover', $v)) {
            $v['basis_of_cover_source'] = $typedBasis === null
                ? ($policyBasis ? 'policy' : null)
                : ($overrode ? 'underwriter' : 'policy');
        }

        $before = $slip->only(array_keys($v));
        $slip->update($v);

        if ($overrode) {
            $this->register->recordEvent(
                $slip->placements()->first(),
                'basis_of_cover_overridden',
                sprintf(
                    'Basis of cover on slip %s stated by the underwriter as "%s". The policy states '
                    . '"%s". The slip and the policy it reinsures now differ on this term.',
                    $slip->slip_no,
                    $typedBasis,
                    $policyBasis
                ),
                ['slipId' => $slip->id, 'stated' => $typedBasis, 'policy' => $policyBasis],
                ['uw_manager', 'ri_team']
            );
        }
        // `basis_of_cover_source` is derived here, not typed, so it must not be
        // counted as a term the user changed — otherwise editing the basis alone
        // reports "2 terms changed".
        $changed = array_keys(array_filter(
            $v,
            fn ($value, $field) => $field !== 'basis_of_cover_source'
                && ($before[$field] ?? null) !== $value,
            ARRAY_FILTER_USE_BOTH
        ));

        return response()->json([
            'message' => $changed
                ? sprintf('Slip wording updated — %d %s changed.', count($changed),
                    count($changed) === 1 ? 'term' : 'terms')
                : 'Nothing was changed.',
            'data'    => $this->slipJson($slip->fresh('acceptances')),
        ]);
    }

    public function slips(Request $request): JsonResponse
    {
        $v = $request->validate([
            'status'   => 'nullable|string|max:20',
            'search'   => 'nullable|string|max:120',
            'per_page' => 'nullable|integer|min:5|max:200',
        ]);

        // Eager-loaded because slipJson() now prints the panel — without this the
        // list fires one query per row for the acceptances.
        $results = FacSlip::query()
            ->with('acceptances')
            ->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($v['search'] ?? null, fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('slip_no', 'like', "%{$s}%")
                  ->orWhere('policy_number', 'like', "%{$s}%")
                  ->orWhere('counterparty_name', 'like', "%{$s}%");
            }))
            ->orderByDesc('id')
            ->paginate($v['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn ($s) => $this->slipJson($s)),
            'meta' => [
                'total'        => $results->total(),
                'per_page'     => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
            ],
        ]);
    }

    private function slipJson(FacSlip $s): array
    {
        return [
            'id'               => $s->id,
            'slipNo'           => $s->slip_no,
            'version'          => $s->version,
            'placementType'    => $s->placement_type,
            'policyNumber'     => $s->policy_number,
            'insuredName'      => $s->insured_name,
            'counterpartyId'   => $s->counterparty_id,
            'counterpartyName' => $s->counterparty_name,
            'riskCarrier'      => $s->risk_carrier,
            'status'           => $s->status,
            'generatedAt'      => optional($s->generated_at)->toDateTimeString(),
            'sentAt'           => optional($s->sent_at)->toDateTimeString(),
            'sentTo'           => $s->sent_to,
            'sendError'        => $s->send_error,
            'lineCount'        => $s->placements()->whereNull('deleted_at')->count(),
            'hasDocument'      => (bool) $s->document_path,

            // The term-sheet wording, so the edit form can load what is on the slip
            // rather than starting blank and overwriting terms with nothing.
            'descriptionOfRisk' => $s->description_of_risk,
            'territorialScope'  => $s->territorial_scope,
            'deductibleText'    => $s->deductible_text,
            'riskCededText'     => $s->risk_ceded_text,
            'basisOfCover'      => $s->basis_of_cover,
            'slipNotes'         => $s->slip_notes,
            // What the POLICY says, and which authority the stored basis came
            // from. The form no longer disables its field — an underwriter may
            // state a basis the policy does not, as Reinsurance asked on
            // 7 September — but it shows the policy's answer beside it so an
            // override is a visible choice rather than an accident.
            'basisFromPolicy'   => (bool) $this->register->basisOfCoverFor(
                $s->policy_id ? (int) $s->policy_id : null
            ),
            'basisPolicyValue'  => $this->register->basisOfCoverFor(
                $s->policy_id ? (int) $s->policy_id : null
            ),
            'basisOfCoverSource' => $s->basis_of_cover_source,
            'termsEditable'     => !in_array($s->status, ['sent', 'accepted', 'superseded'], true),

            // The panel, so the slips list can show who is on a slip without a
            // second call per row. `committed` is the one that matters: a slip can
            // be sent and still carry nobody who has signed, and that is not cover.
            'acceptances'      => $s->acceptances->map(fn ($a) => [
                'id'               => $a->id,
                'reinsurerId'      => $a->reinsurer_id,
                'acceptingCompany' => $a->accepting_company,
                'sharePct'         => $a->share_pct !== null ? (float) $a->share_pct : null,
                'amount'           => $a->amount !== null ? (float) $a->amount : null,
                'signatoryName'    => $a->signatory_name,
                'acceptedOn'       => optional($a->accepted_on)->toDateString(),
                'committed'        => (bool) $a->accepted_on,
            ])->values(),
            'acceptedPct'      => round((float) $s->acceptances->sum(fn ($a) => (float) $a->share_pct), 6),
            'committedCount'   => $s->acceptances->filter(fn ($a) => $a->accepted_on)->count(),
        ];
    }

    public function downloadSlip(int $slipId): JsonResponse
    {
        $s = FacSlip::find($slipId);
        if (!$s || !$s->document_path) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json([
            'url'  => $this->documentUrl((string) config('fac.slips.disk', 's3'), $s->document_path),
            'name' => "FAC-Slip-{$s->slip_no}-v{$s->version}.pdf",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Intelligence
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Ask the assistant about the register.
     *
     * Every figure in the payload is computed here first and passed in. The
     * model reads and explains; it never calculates and never writes.
     */
    public function ask(Request $request): JsonResponse
    {
        $v = $request->validate([
            'question'       => 'required|string|max:1000',
            'financial_year' => 'nullable|string|max:9',
        ]);

        $facts = [
            'summary'   => $this->summary->payableByCounterparty(null, $v['financial_year'] ?? null),
            'coverage'  => $this->coverage->scanSummary(),
            'exceptions' => [
                'unmatchedPolicies' => DB::table('fac_placements')->whereNull('deleted_at')
                    ->where('policy_in_graphite', false)->count(),
                'inactivePolicies'  => DB::table('fac_placements')->whereNull('deleted_at')
                    ->where('policy_in_graphite', true)->where('policy_active_in_graphite', false)->count(),
                'rateMissing'       => DB::table('fac_placements')->whereNull('deleted_at')
                    ->where('currency', '!=', 'BWP')->whereNull('fx_rate')->count(),
                'ppwNotSet'         => DB::table('fac_placements')->whereNull('deleted_at')
                    ->whereNull('ppw_due_date')->where('status', '!=', 'cancelled')->count(),
                'ppwBreached'       => DB::table('fac_placements')->whereNull('deleted_at')
                    ->whereNotNull('ppw_due_date')->whereNull('client_paid_at')
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('ppw_due_date', '<', now()->toDateString())->count(),
            ],
        ];

        return response()->json($this->intelligence->explain($v['question'], $facts));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────

    /** CSV in the master-sheet column order, so it can be diffed against it. */
    public function export(Request $request)
    {
        $v = $request->validate([
            'status'         => 'nullable|string|max:40',
            'placement_type' => ['nullable', Rule::in(['fac', 'auto_fac'])],
            'financial_year' => 'nullable|string|max:9',
            'currency'       => 'nullable|string|max:3',
            'search'         => 'nullable|string|max:120',
            'flag'           => 'nullable|string|max:40',
            'counterparty_id'=> 'nullable|integer',
        ]);

        $rows = $this->baseQuery($v)->orderBy('counterparty_name')->orderBy('id')->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="fac-register-' . now()->format('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'FAC Reference', 'Type', 'Reinsurer/RI Broker', 'Reinsurer (risk carrier)',
                'Total Risk %', 'Fac Slip No.', 'Policy Period', 'Policy Type',
                'Policy Number', 'Insured Name', 'RI Group', 'Cession',
                'Currency', 'Gross Ceded Premium', 'Excl VAT', 'Net Ceded Premium',
                'Commission %', 'Commission', 'Commission Excl VAT',
                'FX Rate', 'FX Rate Date', 'FX Rate Source', 'Payable (BWP)',
                'Slip Signed', 'PPW Window', 'PPW Due Date', 'Underwriter', 'Status',
                'Client Paid At', 'Settled At', 'Settlement Reference',
                'In Graphite', 'Active In Graphite',
            ]);

            foreach ($rows as $r) {
                $isBwp = strtoupper((string) $r->currency) === 'BWP';
                fputcsv($out, [
                    $r->fac_reference,
                    $r->placement_type === 'auto_fac' ? 'Auto FAC' : 'FAC',
                    $r->counterparty_name, $r->risk_carrier, $r->risk_pct, $r->fac_slip_no,
                    trim(($r->period_from ?? '') . ' to ' . ($r->period_to ?? '')),
                    $r->policy_type, $r->policy_number, $r->insured_name,
                    $r->ri_group_label, $r->cession_sum_insured,
                    $r->currency, $r->gross_ceded_premium, $r->gross_ceded_premium_excl_vat,
                    $r->net_ceded_premium, $r->commission_pct, $r->commission_amount,
                    $r->commission_excl_vat,
                    $r->fx_rate, $r->fx_rate_date, $r->fx_rate_source,
                    $isBwp ? $r->gross_ceded_premium : $r->gross_ceded_premium_bwp,
                    $r->slip_signed_date, $r->ppw_terms, $r->ppw_due_date,
                    $r->underwriter_name, $r->status,
                    $r->client_paid_at, $r->settled_at, $r->settlement_reference,
                    $r->policy_in_graphite ? 'Yes' : 'No',
                    $r->policy_active_in_graphite ? 'Yes' : 'No',
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }
}
