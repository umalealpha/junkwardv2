<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\InflationAppliedLog;
use AlphaDirect\Models\InflationRateMaster;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\PolicyCoverageDetail;
use AlphaDirect\Services\Inflation\InflationRateResolver;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Inflationary sum-insured uplift on renewal quotes.
 *
 * TWO MODES
 *
 *   1. OPTION MODE (the original Paul Beka run) — the scope is typed on the
 *      command line: --pct=10 --product=8 --section=Houseowner-Buildings
 *      --descriptions="Sum Insured". One percent, one section, one product.
 *      Unchanged; still the default, so nothing that runs today moves.
 *
 *   2. MASTER MODE (--master) — the scope and the percent come from
 *      `inflation_rate_master`, a table UW edits. Every rule says "for this
 *      product / this section / this line / this sum-insured band, uplift by
 *      this percent", so a different figure per product, per coverage, or per
 *      band needs a row, not a code change or a different command line. The
 *      command finds its own targets from the rules — no --product / --section
 *      / --pct is read in this mode.
 *
 * Applies the uplift to the sum insured of matched sub-coverage lines on
 * renewal quotes, then re-prices each action through the canonical recipe so
 * the quote total agrees with the Rate banner.
 *
 * WHAT IT TOUCHES
 *   policy_coverage_detail.coverage_value    → × (1 + pct/100)
 *   policy_coverage_detail.calculated_value  → recomputed as SI × rate / 100
 *                                              (the exact FE formula in
 *                                               StepCoverages.tsx:431 — the
 *                                               backend has no rating engine,
 *                                               the wizard posts this value)
 *   policy_actions.premium / annual_premium  → via PolicyAction::calculatePremiumRenew
 *                                              (delegates to the canonical
 *                                               recomputeActionTotals)
 *   policy_actions.note                      → append the run marker
 *   inflation_applied_log                    → one row per uplifted line (master mode)
 *
 * WHICH LINES INSIDE A SECTION
 *   A section holds several sub-coverage lines — "Sum Insured", "Rent
 *   Receivable", "Legal Liability", … The line is identified by its
 *   Description, i.e. the sub-coverage's s_ScreenName (tb_cvgpccoverages,
 *   reached through policy_coverage_detail.coverage_id) — the same value the
 *   wizard prints in the Description box. Option mode takes that list from
 *   --descriptions (default "Sum Insured"). Master mode takes it from each
 *   rule's sub_coverage_id / sub_coverage_name, and a rule that names no line
 *   uplifts every line in its section. Matching ignores case and extra spaces.
 *
 * WHAT IT DELIBERATELY DOES NOT TOUCH
 *   - Any line no rule (or no --descriptions entry) claims. They are counted
 *     and named per policy in the report, never modified.
 *   - ISSUED / any non-QUOTE action. An issued anniversary is already invoiced;
 *     re-pricing it here would move money with no endorsement trail. Those are
 *     reported as skipped so they can be handled as endorsements instead.
 *   - policy_extention_detail (extension sums insured) and policy_specified_items.
 *     Extension counts are reported per policy so UW can see what was left
 *     alone. inflation_applied_log.source_table is there for when they join.
 *
 * IDEMPOTENCY
 *   Option mode: every uplifted action gets the marker (see MARKER_PREFIX)
 *   appended to its note, and an action already carrying it is skipped — one
 *   percent per action, so an action-level guard is enough.
 *   Master mode: the percent varies LINE by LINE, so the guard is line-level —
 *   inflation_applied_log answers "has this rule already touched this row of
 *   this action". A rule added in October can therefore still land on an action
 *   September already went through, without re-uplifting September's lines.
 *   Either way a re-run can never compound the increase to 121%. Use --force
 *   only to deliberately re-apply on top of an existing uplift.
 *
 * NO WORK-LIST TO PREPARE
 *   The command finds its own targets — findTargetActions() below IS the extract
 *   query (product + section + anniversary date). Run it again next September and
 *   it picks up that year's renewals by itself; only --from moves (master mode:
 *   and the rules' effective_from / effective_to windows). Narrow to specific
 *   policies with --policy when you want to work one at a time.
 *
 * OPT-OUT (client says "keep my current sum insured")
 *   Pass --skip=<policy numbers>. Those policies are reported with reason
 *   "opted out" and left alone. Nothing here decides on the client's behalf.
 *
 * SAFE BY DEFAULT: without --apply this is a read-only dry run — it computes and
 * reports every change, writes a preview CSV, and touches nothing.
 *
 * Usage:
 *   php artisan policy:inflate-buildings-si                                     # dry run, option mode
 *   php artisan policy:inflate-buildings-si --apply                             # apply, option mode
 *   php artisan policy:inflate-buildings-si --master                            # dry run off the UW master
 *   php artisan policy:inflate-buildings-si --master --apply                    # apply every active rule
 *   php artisan policy:inflate-buildings-si --master --rule=3 --apply           # one rule only
 *   php artisan policy:inflate-buildings-si --master --list-rules               # show the master, run nothing
 *   php artisan policy:inflate-buildings-si --policy=DOMG/2025/01234 --apply    # one policy
 *   php artisan policy:inflate-buildings-si --skip=P7,P9 --apply                # honour two declines
 *   php artisan policy:inflate-buildings-si --from=2027-09-01 --apply           # next year's run
 */
class InflateBuildingsSumInsured extends Command
{
    protected $signature = 'policy:inflate-buildings-si
        {--master : Take the percentages and the scope from inflation_rate_master instead of --pct/--product/--section/--descriptions}
        {--rule= : Master mode only. Restrict to these inflation_rate_master ids, comma-separated}
        {--list-rules : Master mode only. Print the rules that would be used and exit without touching anything}
        {--pct=10 : Option mode only. Percentage uplift to apply to the sum insured}
        {--from=2026-09-01 : Only anniversary actions effective on/after this date}
        {--to= : Optional upper bound on the anniversary effective date (inclusive)}
        {--policy= : Restrict to one or more policies (id or policyNumber, comma-separated). Omit to run every policy the query finds}
        {--skip= : Policy numbers to leave alone, comma-separated (clients who declined the increase)}
        {--section=Houseowner-Buildings : Option mode only. Coverage s_ScreenName to uplift}
        {--descriptions=Sum Insured : Option mode only. Sub-coverage Descriptions to uplift, comma-separated. Every other line in the section is left alone. Pass * for all lines}
        {--product=8 : Option mode only. Product id (8 = Domestic)}
        {--apply : Persist the changes. Without this flag the command is a read-only dry run}
        {--force : Re-apply even if this action/line already carries an uplift (compounds the increase)}
        {--report= : Filename for the decision CSV under storage/app/}
        {--no-report : Suppress the decision CSV}';

    protected $description = 'Apply an inflationary sum-insured uplift to renewal quotes — from the UW inflation master (--master) or from the command-line options (default 10% on Domestic Houseowner-Buildings)';

    /** Note marker written on every uplifted action — the option-mode idempotency key. */
    private const MARKER_PREFIX = 'INFL-SI';

    /** Only a quote may be re-priced in place; anything issued needs an endorsement. */
    private const EDITABLE_STATUSES = ['QUOTE'];

    /** The transaction type option mode has always worked on. */
    private const DEFAULT_TRANSACTION_TYPE = 'ANNIVERSARY-RENEW';

    /** @var array<int,array<string,mixed>> decision rows for the console table + CSV */
    private array $rows = [];

    /** Sub-coverage Descriptions left alone across the whole run, for the summary. */
    private array $skippedDescriptions = [];

    private int $policiesChanged = 0;
    private int $policiesSkipped = 0;
    private int $detailRowsChanged = 0;
    private float $siBefore = 0.0;
    private float $siAfter = 0.0;
    private float $premiumBefore = 0.0;
    private float $premiumAfter = 0.0;

    /** Rule id => number of lines it uplifted, for the summary. */
    private array $ruleUsage = [];

    private ?InflationRateResolver $resolver = null;

    public function handle(): int
    {
        $apply  = (bool) $this->option('apply');
        $force  = (bool) $this->option('force');
        $master = (bool) $this->option('master');

        try {
            $from = Carbon::parse((string) $this->option('from'))->startOfDay();
            $to   = $this->option('to') ? Carbon::parse((string) $this->option('to'))->endOfDay() : null;
        } catch (\Throwable $e) {
            $this->error('Could not parse --from / --to: ' . $e->getMessage());
            return self::FAILURE;
        }

        $ctx = [
            'apply'    => $apply,
            'force'    => $force,
            'master'   => $master,
            'optOuts'  => $this->splitList($this->option('skip')),
            'guard'    => [],
            'ruleIds'  => [],
        ];

        if ($master) {
            $prepared = $this->prepareMasterMode($from, $this->optionWasTyped('from'));
            if (!is_array($prepared)) {
                return $prepared;                       // listing-only, or a validation failure
            }
            $ctx += $prepared;

            // The MASTER owns the dates. Unless an operator typed --from/--to
            // for a one-off, the window is the rules' own effective_from /
            // effective_to — that is what makes an unattended daily run correct:
            // a rule starts firing the day it opens, stops the day it closes,
            // and next season needs a new row rather than a new command line.
            $from = $prepared['from'];
            $to   = $this->optionWasTyped('to') ? $to : $prepared['to'];
        } else {
            $prepared = $this->prepareOptionMode();
            if (!is_array($prepared)) {
                return $prepared;
            }
            $ctx += $prepared;
        }

        $actions = $this->findTargetActions(
            $ctx['products'],
            $ctx['sectionNames'],
            $ctx['sectionIds'],
            $ctx['txTypes'],
            $from,
            $to
        );

        if ($actions->isEmpty()) {
            $this->warn('No renewal quotes in scope. Nothing to do.');
            return self::SUCCESS;
        }

        $this->line($ctx['header'] . sprintf(
            ' — %d quote(s) effective %s%s%s.',
            $actions->count(),
            $from ? 'from ' . $from->toDateString() : 'on any date',
            $to ? ' to ' . $to->toDateString() : ($from ? ' onwards' : ''),
            $master ? ' (window from the master)' : ''
        ));

        // Line-level idempotency guard, loaded in one query for the whole batch
        // rather than per line (a September run is ~160 actions × several lines).
        if ($master) {
            $ctx['guard'] = InflationAppliedLog::guardMap($actions->pluck('action_id')->map('intval')->all());
        }

        foreach ($actions as $action) {
            $this->processAction($action, $ctx);
        }

        $this->renderSummary($apply, $master);

        if (!$this->option('no-report')) {
            $this->writeReport((string) ($this->option('report') ?: sprintf(
                'inflate_si%s%s_%s.csv',
                $master ? '_master' : '',
                $apply ? '' : '_dryrun',
                Carbon::now()->format('Y-m-d_His')
            )));
        }

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────
    // Mode setup
    // ──────────────────────────────────────────────────────────────────

    /**
     * Option mode — the original behaviour. One percent, one product, one
     * section, the Descriptions from --descriptions.
     *
     * @return array<string,mixed>|int  the run context, or an exit code
     */
    private function prepareOptionMode()
    {
        $pct     = (float) $this->option('pct');
        $section = (string) $this->option('section');
        $product = (int) $this->option('product');

        if ($pct <= 0 || $pct > 100) {
            $this->error("--pct must be between 0 and 100 (got {$pct}). For a reduction or a per-line figure use --master.");
            return self::FAILURE;
        }

        $wantLines = $this->descriptionFilter();

        return [
            'factor'       => 1 + ($pct / 100),
            'pct'          => $pct,
            'wantLines'    => $wantLines,
            'marker'       => self::MARKER_PREFIX . ':' . $this->fmtPct($pct) . '%',
            'products'     => [$product],
            'sectionNames' => [$section],
            'sectionIds'   => [],
            'txTypes'      => [self::DEFAULT_TRANSACTION_TYPE],
            'header'       => sprintf(
                '%sUplift %s%% on "%s" [%s line(s)] — product %d',
                $this->option('apply') ? '' : '[DRY RUN] ',
                $this->fmtPct($pct),
                $section,
                $wantLines === null ? 'ALL' : implode(' + ', $wantLines),
                $product
            ),
        ];
    }

    /**
     * Master mode — the scope IS the master. Products, sections and
     * transaction types are read off the active rules, so adding a rule for
     * Commercial (or for a second section) widens the run with no new
     * command-line arguments and no code change.
     *
     * @return array<string,mixed>|int  the run context, or an exit code
     */
    private function prepareMasterMode(Carbon $from, bool $fromWasTyped)
    {
        $this->resolver = app(InflationRateResolver::class);

        if ($ruleIds = $this->splitList($this->option('rule'))) {
            $this->resolver->restrictTo($ruleIds);
        }

        // Unattended runs consider rules that can still fire TODAY — a rule
        // whose effective_to has passed retires itself, with no one having to
        // deactivate it. A typed --from is an operator asking for a specific
        // season instead (including a past one), so it wins.
        $asOf  = $fromWasTyped ? $from->toDateString() : Carbon::now()->toDateString();
        $scope = $this->resolver->scopeOf($asOf);
        $rules = $scope['rules'];

        if ($rules->isEmpty()) {
            $this->warn('inflation_rate_master has no active rule in scope' .
                ($ruleIds ? ' for --rule=' . implode(',', $ruleIds) : '') .
                ' that can still fire on/after ' . $asOf . '. Nothing to do.');
            return self::SUCCESS;
        }

        // A percent outside ±100 is a data-entry slip (1000 instead of 10.00),
        // and it would be applied to live sums insured. Refuse the whole run
        // rather than quietly dropping the offending rule.
        $bad = $rules->filter(fn (InflationRateMaster $r) => abs((float) $r->pct) > 100);
        if ($bad->isNotEmpty()) {
            $this->error('These master rules carry a percent outside ±100 and must be corrected before any run:');
            foreach ($bad as $rule) {
                $this->line('  ' . $this->resolver->describe($rule));
            }
            return self::FAILURE;
        }

        $this->info('Inflation master — ' . $rules->count() . ' active rule(s) in scope:');
        foreach ($rules->sortByDesc(fn (InflationRateMaster $r) => [$r->specificity(), $r->id]) as $rule) {
            $this->line('  ' . $this->resolver->describe($rule));
        }

        if ($this->option('list-rules')) {
            $this->comment('--list-rules — nothing was read or written beyond the master itself.');
            return self::SUCCESS;
        }

        foreach (['pct', 'section', 'descriptions', 'product'] as $ignored) {
            if ($this->optionWasTyped($ignored)) {
                $this->warn('--' . $ignored . ' is ignored in --master mode; the master decides it.');
            }
        }

        [$windowFrom, $windowTo] = $this->masterWindow($rules, $fromWasTyped ? $from : null);

        if ($windowFrom === null) {
            $this->warn('At least one active rule has no effective_from, so the run has NO lower date bound '
                . 'and will consider every live renewal quote. Each rule still only touches dates inside its own '
                . 'window — set effective_from on the rules to narrow the sweep.');
        }

        return [
            'factor'       => null,
            'pct'          => null,
            'wantLines'    => null,
            'marker'       => null,                     // built per action from the rules that fired
            'products'     => $scope['products'],
            'sectionNames' => $scope['sections'],
            'sectionIds'   => $scope['section_ids'],
            'txTypes'      => $scope['transaction_types'],
            'from'         => $windowFrom,
            'to'           => $windowTo,
            'header'       => ($this->option('apply') ? '' : '[DRY RUN] ') . 'Master-driven uplift',
        ];
    }

    /**
     * The date window the master itself asks for.
     *
     * Widest span across the active rules: the earliest effective_from and the
     * latest effective_to. A rule with no effective_from (or no effective_to)
     * is unbounded on that side and makes the whole window unbounded, because
     * it genuinely claims every date there.
     *
     * This is only the QUERY bound — the per-line decision still comes from
     * each rule's own window inside the resolver, so a wide query can never
     * make a rule fire outside its dates.
     *
     * @param  \Illuminate\Support\Collection<int,InflationRateMaster> $rules
     * @return array{0:?Carbon,1:?Carbon}
     */
    private function masterWindow($rules, ?Carbon $typedFrom): array
    {
        $from = null;
        $to   = null;
        $unboundedFrom = false;
        $unboundedTo   = false;

        foreach ($rules as $rule) {
            if (!$rule->effective_from) {
                $unboundedFrom = true;
            } else {
                $start = Carbon::parse((string) $rule->effective_from)->startOfDay();
                if ($from === null || $start->lt($from)) {
                    $from = $start;
                }
            }

            if (!$rule->effective_to) {
                $unboundedTo = true;
            } else {
                $end = Carbon::parse((string) $rule->effective_to)->endOfDay();
                if ($to === null || $end->gt($to)) {
                    $to = $end;
                }
            }
        }

        // A typed --from is an explicit narrowing and must never be widened by
        // the master (an operator running one season should not sweep another).
        if ($typedFrom !== null && ($unboundedFrom || $from === null || $from->lt($typedFrom))) {
            return [$typedFrom, $unboundedTo ? null : $to];
        }

        return [$unboundedFrom ? null : $from, $unboundedTo ? null : $to];
    }

    /** Did the operator actually type this option, or is it just its default? */
    private function optionWasTyped(string $name): bool
    {
        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if (str_starts_with((string) $arg, '--' . $name . '=') || $arg === '--' . $name) {
                return true;
            }
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────────
    // Target selection
    // ──────────────────────────────────────────────────────────────────

    /**
     * Renewal actions carrying a live section in scope.
     *
     * Mirrors the extract query: product + section + effective date, excluding
     * cancelled policies. Per-action editability, opt-outs and the idempotency
     * guard are checked in processAction() so every exclusion is REPORTED
     * rather than silently filtered away — "which of the 160 didn't move, and
     * why" has to be answerable from the CSV alone.
     *
     * A null inside $products / $sectionNames / $txTypes is a WILDCARD from a
     * master rule that left that matcher blank, and drops the filter entirely.
     *
     * @param array<int,int|null>    $products
     * @param array<int,string|null> $sectionNames
     * @param array<int,int>         $sectionIds
     * @param array<int,string|null> $txTypes
     */
    private function findTargetActions(
        array $products,
        array $sectionNames,
        array $sectionIds,
        array $txTypes,
        ?Carbon $from,
        ?Carbon $to
    ) {
        $productIds  = array_values(array_filter($products, fn ($v) => $v !== null));
        $anyProduct  = count($productIds) !== count($products);

        $names       = array_values(array_filter($sectionNames, fn ($v) => $v !== null));
        $anySection  = count($names) !== count($sectionNames);

        $types       = array_values(array_filter($txTypes, fn ($v) => $v !== null));
        $anyTxType   = count($types) !== count($txTypes);

        return DB::table('policy_actions as a')
            ->join('policies as p', 'p.id', '=', 'a.policy_id')
            ->leftJoin('customer_profile as cp', 'cp.customer_id', '=', 'p.customer_id')
            ->when(!$anyProduct, fn ($q) => $q->whereIn('p.product_id', $productIds))
            ->where('p.status', '!=', 2)
            ->when(!$anyTxType, fn ($q) => $q->whereIn('a.transaction_type', $types))
            ->whereNull('a.deleted_at')
            ->when($from, fn ($q) => $q->whereDate('a.effective_from', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('a.effective_from', '<=', $to->toDateString()))
            ->when($this->splitList($this->option('policy')), function ($q, array $refs) {
                // Policy-wise run. Match on either key so an operator can paste
                // policy numbers or ids without caring which column they are.
                $q->where(function ($w) use ($refs) {
                    $w->whereIn('p.policyNumber', $refs);
                    $ids = array_filter($refs, 'is_numeric');
                    if (!empty($ids)) {
                        $w->orWhereIn('p.id', $ids);
                    }
                });
            })
            // must actually carry a section in scope
            ->when(!$anySection, function ($q) use ($names, $sectionIds) {
                $q->whereExists(function ($sub) use ($names, $sectionIds) {
                    $sub->select(DB::raw(1))
                        ->from('policy_coverages as pcv')
                        ->join('tb_cvgpccoverages as sec', 'sec.id', '=', 'pcv.coverage_id')
                        ->whereColumn('pcv.policy_id', 'p.id')
                        ->whereColumn('pcv.action_id', 'a.id')
                        ->whereNull('pcv.deleted_at')
                        ->where(function ($w) use ($names, $sectionIds) {
                            if (!empty($names)) {
                                $w->whereIn('sec.s_ScreenName', $names);
                            }
                            if (!empty($sectionIds)) {
                                $w->orWhereIn('pcv.coverage_id', $sectionIds);
                            }
                        });
                });
            })
            ->orderBy('a.effective_from')
            ->orderBy('p.policyNumber')
            ->select([
                'a.id as action_id',
                'a.status',
                'a.term_id',
                'a.transaction_type',
                'a.effective_from',
                'a.note',
                'a.premium',
                'p.id as policy_id',
                'p.product_id',
                'p.policyNumber',
                'p.premium_freq',
                'cp.Insure as insured_name',
            ])
            ->get();
    }

    /**
     * The live sections on this action that are in scope, grouped by section,
     * each carrying the policy_coverages ids underneath it.
     *
     * Grouped rather than flattened because master mode resolves per SECTION as
     * well as per line, and because one section can be captured more than once
     * on a policy (a second risk address, for instance).
     *
     * @param  array<int,string|null> $sectionNames  a null entry = every section
     * @param  array<int,int>         $sectionIds
     * @return array<int,array{coverage_id:int,name:string,pc_ids:array<int,int>}>
     */
    private function sectionCoverages(int $policyId, int $actionId, array $sectionNames, array $sectionIds): array
    {
        $names      = array_values(array_filter($sectionNames, fn ($v) => $v !== null));
        $anySection = count($names) !== count($sectionNames);

        $rows = DB::table('policy_coverages as pc')
            ->join('tb_cvgpccoverages as sec', 'sec.id', '=', 'pc.coverage_id')
            ->where('pc.policy_id', $policyId)
            ->where('pc.action_id', $actionId)
            ->whereNull('pc.deleted_at')
            ->when(!$anySection, function ($q) use ($names, $sectionIds) {
                $q->where(function ($w) use ($names, $sectionIds) {
                    if (!empty($names)) {
                        $w->whereIn('sec.s_ScreenName', $names);
                    }
                    if (!empty($sectionIds)) {
                        $w->orWhereIn('pc.coverage_id', $sectionIds);
                    }
                });
            })
            ->select('pc.id as pc_id', 'pc.coverage_id', 'sec.s_ScreenName as section_name')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $key = (int) $row->coverage_id;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'coverage_id' => $key,
                    'name'        => (string) $row->section_name,
                    'pc_ids'      => [],
                ];
            }
            $grouped[$key]['pc_ids'][] = (int) $row->pc_id;
        }

        return array_values($grouped);
    }

    // ──────────────────────────────────────────────────────────────────
    // Per-action work
    // ──────────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $ctx run context from prepareOptionMode/prepareMasterMode
     */
    private function processAction(object $action, array $ctx): void
    {
        $number  = (string) $action->policyNumber;
        $master  = (bool) $ctx['master'];
        $apply   = (bool) $ctx['apply'];
        $force   = (bool) $ctx['force'];
        $optOuts = $ctx['optOuts'];

        if ($optOuts !== null && in_array($number, $optOuts, true)) {
            $this->record($action, 'skipped', 'opted out — client keeps current sum insured');
            return;
        }

        // Guard 1 — quote only. An ISSUED anniversary is already invoiced.
        if (!in_array((string) $action->status, self::EDITABLE_STATUSES, true)) {
            $this->record($action, 'skipped', 'action is ' . $action->status . ' — needs an endorsement, not an in-place re-price');
            return;
        }

        // Guard 2 — already uplifted. Option mode guards the whole action on the
        // note marker (one percent per action). Master mode guards line by line
        // in planFromMaster(), so a later rule can still reach this action.
        if (!$master && !$force && str_contains((string) $action->note, (string) $ctx['marker'])) {
            $this->record($action, 'no change', 'already uplifted (' . $ctx['marker'] . ')');
            return;
        }

        // Guard 3 — un-cleared cancel / lapse. Same rule as RenewAnnualPolicies:
        // only a REINSTATE/REISSUE after the event revives the policy, never a renewal.
        if ($reason = $this->blockedByCancelOrLapse((int) $action->policy_id)) {
            $this->record($action, 'skipped', $reason);
            return;
        }

        $sections = $this->sectionCoverages(
            (int) $action->policy_id,
            (int) $action->action_id,
            $ctx['sectionNames'],
            $ctx['sectionIds']
        );

        if (empty($sections)) {
            $this->record($action, 'skipped', 'no live section in scope on this action');
            return;
        }

        $rules = $master
            ? $this->resolver->rulesFor(
                (int) $action->product_id,
                Carbon::parse($action->effective_from)->toDateString(),
                (string) $action->transaction_type
            )
            : null;

        $plan       = [];
        $leftAlone  = [];
        $unrated    = 0;
        $extensions = 0;
        $siBefore   = 0.0;
        $siAfter    = 0.0;
        $prBefore   = 0.0;
        $prAfter    = 0.0;
        $anySi      = false;

        foreach ($sections as $section) {
            $details = PolicyCoverageDetail::query()
                ->whereIn('policy_coverage_id', $section['pc_ids'])
                ->whereNull('deleted_at')
                ->where('coverage_value', '>', 0)
                ->get();

            if ($details->isEmpty()) {
                continue;
            }

            $anySi = true;

            // Extension rows are left untouched by design — count them so the
            // report shows what was NOT inflated on this policy.
            $extensions += (int) DB::table('policy_extention_detail')
                ->whereIn('policy_coverage_id', $section['pc_ids'])
                ->whereNull('deleted_at')
                ->count();

            $lines = $master
                ? $this->planFromMaster($action, $section, $details, $rules, $force, $ctx['guard'], $leftAlone)
                : $this->planFromOptions($section, $details, (float) $ctx['factor'], (float) $ctx['pct'], $ctx['wantLines'], $leftAlone);

            foreach ($lines as $line) {
                if ($line['rate'] <= 0) {
                    $unrated++;
                }

                $siBefore += $line['si_old'];
                $siAfter  += $line['si_new'];
                $prBefore += $line['calc_old'];
                $prAfter  += $line['calc_new'];

                $plan[] = $line;
            }
        }

        if (!$anySi) {
            $this->record($action, 'skipped', 'section(s) in scope carry no sum insured > 0');
            return;
        }

        if (empty($plan)) {
            $this->record($action, 'skipped', sprintf(
                'nothing in scope on this action (%s)',
                $this->leftAloneLabel($leftAlone) ?: 'no matching line'
            ));
            return;
        }

        $ruleIds = array_values(array_unique(array_filter(array_map(
            fn (array $line) => $line['rule'] ? (int) $line['rule']->id : null,
            $plan
        ))));

        $marker = $master
            ? self::MARKER_PREFIX . ':M' . implode('/', array_map(fn ($id) => '#' . $id, $ruleIds))
            : (string) $ctx['marker'];

        $note = $this->planNote($plan, $unrated, $extensions, $leftAlone);

        if (!$apply) {
            $this->record($action, 'would uplift', $note, $siBefore, $siAfter, $prBefore, $prAfter, null, $plan);
            $this->tally($plan, $siBefore, $siAfter, $prBefore, $prAfter);
            return;
        }

        try {
            DB::transaction(function () use ($plan, $action, $marker, $master) {
                foreach ($plan as $line) {
                    // Saved through Eloquent on purpose: PolicyCoverageDetail is
                    // Auditable, so every before/after lands in `audits` and shows
                    // on the policy Logs tab. A query-builder update would be silent.
                    $detail = $line['detail'];
                    $detail->coverage_value   = $line['si_new'];
                    $detail->calculated_value = $line['calc_new'];
                    $detail->save();

                    if ($master && $line['rule']) {
                        // The line-level idempotency guard AND the audit answer to
                        // "which rule moved this sum insured". Append-only.
                        InflationAppliedLog::create([
                            'rule_id'        => (int) $line['rule']->id,
                            'policy_id'      => (int) $action->policy_id,
                            'action_id'      => (int) $action->action_id,
                            'source_table'   => 'policy_coverage_detail',
                            'row_id'         => (int) $detail->id,
                            'coverage_id'    => $detail->coverage_id ? (int) $detail->coverage_id : null,
                            'pct'            => $line['pct'],
                            'si_before'      => $line['si_old'],
                            'si_after'       => $line['si_new'],
                            'premium_before' => $line['calc_old'],
                            'premium_after'  => $line['calc_new'],
                            'run_marker'     => $marker,
                        ]);
                    }
                }

                // Re-price the action through the canonical recipe (the same one
                // the renew crons and Refresh Endorsement funnel through), so the
                // quote total matches the Rate banner instead of drifting from it.
                //
                // $strict = true. Without it a throw inside the canonical recipe
                // is swallowed and execution falls through to the legacy inline
                // sum, which double-counts the motor base and over-scopes the
                // fidelity bucket — the very divergence that renewed a 13,575
                // anniversary at ~16k. Nothing would escape, so this action's
                // transaction would COMMIT the wrong premium, report the policy
                // as uplifted and stamp the run marker, so a re-run would skip it
                // without --force. Crons keep the lenient default deliberately;
                // an interactive, money-moving uplift must fail loudly instead,
                // and the catch below rolls this action back and records FAILED.
                PolicyAction::calculatePremiumRenew(
                    (int) $action->action_id,
                    (int) $action->term_id,
                    (int) $action->policy_id,
                    true
                );

                // Stamp the marker last — only a fully successful uplift is
                // recorded, so a rolled-back attempt stays re-runnable.
                $existing = trim((string) PolicyAction::where('id', $action->action_id)->value('note'));
                PolicyAction::where('id', $action->action_id)->update([
                    'note' => trim($existing . ' | ' . $marker . ' applied ' . Carbon::now()->toDateString()),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('InflateBuildingsSumInsured failed', [
                'policy'    => $number,
                'action_id' => $action->action_id,
                'master'    => $master,
                'error'     => $e->getMessage(),
            ]);
            $this->record($action, 'FAILED', $e->getMessage(), $siBefore, $siAfter, $prBefore, $prAfter, null, $plan);
            return;
        }

        $premiumAfterAction = (float) PolicyAction::where('id', $action->action_id)->value('premium');

        $this->record($action, 'uplifted', $note, $siBefore, $siAfter, $prBefore, $prAfter, $premiumAfterAction, $plan);
        $this->tally($plan, $siBefore, $siAfter, $prBefore, $prAfter);
    }

    // ──────────────────────────────────────────────────────────────────
    // Line-level planning
    // ──────────────────────────────────────────────────────────────────

    /**
     * Option mode: one percent for every line whose Description is in
     * --descriptions. Every other line in the section is named in $leftAlone
     * and never touched.
     *
     * @param  array{coverage_id:int,name:string,pc_ids:array<int,int>} $section
     * @param  array<int,string>|null $wantLines  null = every line (--descriptions=*)
     * @param  array<string,int>      $leftAlone  by reference, Description => rows skipped
     * @return array<int,array<string,mixed>>
     */
    private function planFromOptions(
        array $section,
        $details,
        float $factor,
        float $pct,
        ?array $wantLines,
        array &$leftAlone
    ): array {
        $names  = $this->descriptionNames($details);
        $wanted = $wantLines === null ? null : array_map([InflationRateMaster::class, 'normalise'], $wantLines);

        $plan = [];

        foreach ($details as $detail) {
            $name = trim((string) ($names[$detail->coverage_id] ?? ''));

            if ($wanted !== null && !in_array(InflationRateMaster::normalise($name), $wanted, true)) {
                $this->noteLeftAlone($leftAlone, $this->leftAloneKey($name, $detail, $section['name']));
                continue;
            }

            $plan[] = $this->plannedLine($detail, $factor, $pct, null, $name, $section);
        }

        return $plan;
    }

    /**
     * Master mode: the percent is resolved per line from the master, and a line
     * no rule claims is left exactly as UW captured it.
     *
     * The line-level idempotency guard lives here too: a line this rule has
     * already uplifted on this action is reported (with the percent that was
     * used) rather than uplifted again.
     *
     * @param  array{coverage_id:int,name:string,pc_ids:array<int,int>} $section
     * @param  \Illuminate\Support\Collection<int,InflationRateMaster>  $rules
     * @param  array<string,InflationAppliedLog>                       $guard
     * @param  array<string,int>                                       $leftAlone by reference
     * @return array<int,array<string,mixed>>
     */
    private function planFromMaster(
        object $action,
        array $section,
        $details,
        $rules,
        bool $force,
        array $guard,
        array &$leftAlone
    ): array {
        $names = $this->descriptionNames($details);
        $plan  = [];

        foreach ($details as $detail) {
            $name = trim((string) ($names[$detail->coverage_id] ?? ''));
            $si   = (float) $detail->coverage_value;

            $rule = $this->resolver->resolve(
                $rules,
                $section['coverage_id'],
                $section['name'],
                $detail->coverage_id ? (int) $detail->coverage_id : null,
                $name !== '' ? $name : null,
                $si
            );

            if ($rule === null) {
                $this->noteLeftAlone($leftAlone, $this->leftAloneKey($name, $detail, $section['name']) . ' — no rule');
                continue;
            }

            if (!$force) {
                $key = InflationAppliedLog::guardKey((int) $action->action_id, (int) $detail->id, (int) $rule->id);
                if (isset($guard[$key])) {
                    $before = $guard[$key];
                    $this->noteLeftAlone($leftAlone, sprintf(
                        '%s — already uplifted by rule #%d at %s%%%s',
                        $name !== '' ? $name : 'line ' . $detail->id,
                        $rule->id,
                        $this->fmtPct((float) $before->pct),
                        abs((float) $before->pct - (float) $rule->pct) > 0.0001
                            ? ' (rule now says ' . $this->fmtPct((float) $rule->pct) . '% — use --force to top up)'
                            : ''
                    ));
                    continue;
                }
            }

            // pct = 0 is a real UW decision ("hold this line"), not a no-op to
            // be uplifted by some fallback. Record it as held, not as changed.
            if (abs((float) $rule->pct) < 0.0001) {
                $this->noteLeftAlone($leftAlone, ($name !== '' ? $name : 'line ' . $detail->id) . ' — held at 0% by rule #' . $rule->id);
                continue;
            }

            $plan[] = $this->plannedLine($detail, 1 + ((float) $rule->pct / 100), (float) $rule->pct, $rule, $name, $section);
        }

        return $plan;
    }

    /**
     * One planned change.
     *
     * Mirrors the wizard: premium = sum insured × rate / 100. A row with no
     * rate is hand-priced by UW (or a nil-premium sub-line); we lift the sum
     * insured and leave the premium alone rather than invent a figure.
     *
     * @param array{coverage_id:int,name:string,pc_ids:array<int,int>} $section
     * @return array<string,mixed>
     */
    private function plannedLine($detail, float $factor, float $pct, ?InflationRateMaster $rule, string $description, array $section): array
    {
        $siOld   = (float) $detail->coverage_value;
        $rate    = (float) $detail->rate;
        $siNew   = round($siOld * $factor, 2);
        $calcOld = (float) $detail->calculated_value;

        return [
            'detail'      => $detail,
            'rule'        => $rule,
            'pct'         => $pct,
            'rate'        => $rate,
            'description' => $description,
            'section'     => $section['name'],
            'si_old'      => $siOld,
            'si_new'      => $siNew,
            'calc_old'    => $calcOld,
            'calc_new'    => $rate > 0 ? round($siNew * $rate / 100, 2) : $calcOld,
        ];
    }

    /**
     * Descriptions for a set of detail rows.
     *
     * The Description a user sees in the wizard is the sub-coverage's
     * s_ScreenName, reached through policy_coverage_detail.coverage_id — the row
     * carries no description column of its own.
     *
     * @return array<int,string>  coverage_id => s_ScreenName
     */
    private function descriptionNames($details): array
    {
        $ids = $details->pluck('coverage_id')->filter()->unique()->all();

        if (empty($ids)) {
            return [];
        }

        return DB::table('tb_cvgpccoverages')
            ->whereIn('id', $ids)
            ->pluck('s_ScreenName', 'id')
            ->toArray();
    }

    /**
     * Label for a line that was left alone.
     *
     * Rows whose master row is missing (or whose coverage_id is empty) can't be
     * identified, so they are reported as "unnamed": with a named scope from UW,
     * guessing is worse than reporting. Same for a row whose coverage_id points
     * at the PARENT section instead of a sub-coverage (a known save-path
     * fallback) — it is flagged for manual checking rather than assumed to be
     * the sum insured.
     */
    private function leftAloneKey(string $name, $detail, string $sectionName): string
    {
        if ($name === '') {
            return 'unnamed (coverage_id ' . (int) $detail->coverage_id . ')';
        }

        if (InflationRateMaster::normalise($name) === InflationRateMaster::normalise($sectionName)) {
            return $name . ' (section id on the row — check this line manually)';
        }

        return $name;
    }

    /** @param array<string,int> $leftAlone by reference */
    private function noteLeftAlone(array &$leftAlone, string $label): void
    {
        $leftAlone[$label] = ($leftAlone[$label] ?? 0) + 1;
        $this->skippedDescriptions[$label] = ($this->skippedDescriptions[$label] ?? 0) + 1;
    }

    /**
     * @return array<int,string>|null  null when --descriptions=* (uplift every line)
     */
    private function descriptionFilter(): ?array
    {
        $raw = trim((string) $this->option('descriptions'));

        if ($raw === '*') {
            return null;
        }

        // An empty --descriptions= falls back to the narrow default rather than
        // "everything": the safe direction is never uplifting more than asked.
        return $this->splitList($raw) ?? ['Sum Insured'];
    }

    /**
     * An ISSUED CANCEL or a LAPSED action with no later REINSTATE/REISSUE means
     * the cover is dead — do not re-price its renewal quote.
     */
    private function blockedByCancelOrLapse(int $policyId): ?string
    {
        $events = PolicyAction::where('policy_id', $policyId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where(fn ($w) => $w->where('status', 'ISSUED')->where('transaction_type', 'CANCEL'))
                  ->orWhere('status', 'LAPSED');
            })
            ->orderBy('id', 'DESC')
            ->get(['id', 'status', 'transaction_type']);

        foreach ($events as $event) {
            $revived = PolicyAction::where('policy_id', $policyId)
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->whereIn('transaction_type', ['REINSTATE', 'REISSUE'])
                ->where('id', '>', $event->id)
                ->exists();

            if (!$revived) {
                return $event->status === 'LAPSED'
                    ? 'policy LAPSED with no reinstatement'
                    : 'policy has an un-cleared ISSUED CANCEL';
            }
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $plan
     * @param array<string,int>              $leftAlone Description => row count, untouched by design
     */
    private function planNote(array $plan, int $unrated, int $extensions, array $leftAlone = []): string
    {
        $note = count($plan) . ' line(s): ' . implode(', ', array_map(
            fn (array $l) => ($l['description'] !== '' ? $l['description'] : 'line ' . $l['detail']->id)
                . ' +' . $this->fmtPct((float) $l['pct']) . '%'
                . ($l['rule'] ? ' [#' . $l['rule']->id . ']' : ''),
            $plan
        ));

        if (!empty($leftAlone)) {
            $note .= '; left alone: ' . $this->leftAloneLabel($leftAlone);
        }
        if ($unrated > 0) {
            $note .= '; ' . $unrated . ' with no rate — SI lifted, premium unchanged (UW to price)';
        }
        if ($extensions > 0) {
            $note .= '; ' . $extensions . ' extension row(s) NOT inflated';
        }

        return $note;
    }

    /** @param array<string,int> $leftAlone */
    private function leftAloneLabel(array $leftAlone): string
    {
        return implode(', ', array_map(
            fn ($name, $n) => $name . ($n > 1 ? ' ×' . $n : ''),
            array_keys($leftAlone),
            $leftAlone
        ));
    }

    private function fmtPct(float $pct): string
    {
        return rtrim(rtrim(number_format($pct, 4, '.', ''), '0'), '.');
    }

    // ──────────────────────────────────────────────────────────────────
    // Reporting
    // ──────────────────────────────────────────────────────────────────

    /**
     * @param array<int,array<string,mixed>> $plan
     */
    private function record(
        object $action,
        string $result,
        string $reason,
        ?float $siBefore = null,
        ?float $siAfter = null,
        ?float $prBefore = null,
        ?float $prAfter = null,
        ?float $actionPremiumAfter = null,
        array $plan = []
    ): void {
        if (in_array($result, ['skipped', 'no change', 'FAILED'], true)) {
            $this->policiesSkipped++;
        }

        $pcts  = array_values(array_unique(array_map(fn (array $l) => $this->fmtPct((float) $l['pct']) . '%', $plan)));
        $rules = array_values(array_unique(array_filter(array_map(
            fn (array $l) => $l['rule'] ? '#' . $l['rule']->id : null,
            $plan
        ))));

        $this->rows[] = [
            'policyNumber'        => (string) $action->policyNumber,
            'insured'             => (string) ($action->insured_name ?? ''),
            'product_id'          => (string) ($action->product_id ?? ''),
            'action_id'           => (string) $action->action_id,
            'transaction_type'    => (string) ($action->transaction_type ?? ''),
            'quote_status'        => (string) $action->status,
            'anniversary_from'    => Carbon::parse($action->effective_from)->toDateString(),
            'pct_applied'         => implode(' / ', $pcts),
            'rules_applied'       => implode(' ', $rules),
            'si_before'           => $siBefore === null ? '' : number_format($siBefore, 2, '.', ''),
            'si_after'            => $siAfter === null ? '' : number_format($siAfter, 2, '.', ''),
            'si_increase'         => ($siBefore === null || $siAfter === null) ? '' : number_format($siAfter - $siBefore, 2, '.', ''),
            'section_prem_before' => $prBefore === null ? '' : number_format($prBefore, 2, '.', ''),
            'section_prem_after'  => $prAfter === null ? '' : number_format($prAfter, 2, '.', ''),
            'action_prem_before'  => number_format((float) $action->premium, 2, '.', ''),
            'action_prem_after'   => $actionPremiumAfter === null ? '' : number_format($actionPremiumAfter, 2, '.', ''),
            'result'              => $result,
            'reason'              => $reason,
        ];
    }

    /** @param array<int,array<string,mixed>> $plan */
    private function tally(array $plan, float $siB, float $siA, float $prB, float $prA): void
    {
        $this->policiesChanged++;
        $this->detailRowsChanged += count($plan);
        $this->siBefore      += $siB;
        $this->siAfter       += $siA;
        $this->premiumBefore += $prB;
        $this->premiumAfter  += $prA;

        foreach ($plan as $line) {
            if ($line['rule']) {
                $id = (int) $line['rule']->id;
                $this->ruleUsage[$id] = ($this->ruleUsage[$id] ?? 0) + 1;
            }
        }
    }

    private function renderSummary(bool $apply, bool $master): void
    {
        $this->table(
            ['Policy', 'Effective', 'Status', 'Uplift', 'SI before', 'SI after', 'Prem before', 'Prem after', 'Result', 'Reason'],
            array_map(fn ($r) => [
                $r['policyNumber'], $r['anniversary_from'], $r['quote_status'],
                trim($r['pct_applied'] . ' ' . $r['rules_applied']),
                $r['si_before'], $r['si_after'],
                $r['section_prem_before'], $r['section_prem_after'],
                $r['result'], $r['reason'],
            ], $this->rows)
        );

        $verb = $apply ? 'Uplifted' : 'Would uplift';
        $this->info(sprintf(
            '%s %d policy/policies (%d line(s)). Sum insured %s → %s (%s). Section premium %s → %s (%s). Skipped: %d.',
            $verb,
            $this->policiesChanged,
            $this->detailRowsChanged,
            number_format($this->siBefore, 2),
            number_format($this->siAfter, 2),
            $this->signed($this->siAfter - $this->siBefore),
            number_format($this->premiumBefore, 2),
            number_format($this->premiumAfter, 2),
            $this->signed($this->premiumAfter - $this->premiumBefore),
            $this->policiesSkipped
        ));

        if ($master && !empty($this->ruleUsage)) {
            arsort($this->ruleUsage);
            $this->line('Rules that fired: ' . implode(', ', array_map(
                fn ($id, $n) => '#' . $id . ' → ' . $n . ' line(s)',
                array_keys($this->ruleUsage),
                $this->ruleUsage
            )));
        }

        if (!empty($this->skippedDescriptions)) {
            arsort($this->skippedDescriptions);
            $this->line('Lines left alone (out of scope): ' . implode(', ', array_map(
                fn ($name, $n) => $name . ' ×' . $n,
                array_keys($this->skippedDescriptions),
                $this->skippedDescriptions
            )));
        }

        if (!$apply) {
            $this->warn('Dry run — nothing written. Re-run with --apply once the list is validated.');
        }
    }

    private function signed(float $value): string
    {
        return ($value >= 0 ? '+' : '-') . number_format(abs($value), 2);
    }

    private function writeReport(string $filename): void
    {
        $path = storage_path('app/' . ltrim($filename, '/\\'));
        @mkdir(dirname($path), 0775, true);

        $handle = @fopen($path, 'w');
        if ($handle === false) {
            $this->warn('Could not write report to ' . $path);
            return;
        }

        fputcsv($handle, array_keys($this->rows[0] ?? ['policyNumber' => '']));
        foreach ($this->rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $this->info('Report: ' . $path);
    }

    // ──────────────────────────────────────────────────────────────────
    // Option parsing
    // ──────────────────────────────────────────────────────────────────

    /**
     * Split a comma-separated option into a clean list.
     *
     * Deliberately inline rather than a CSV file: the targets come from the
     * query, so the only thing an operator ever types is the handful of policies
     * they want to include or exclude. Tolerates spaces and stray semicolons so
     * a list pasted out of Excel or Teams works as-is.
     *
     * @return array<int,string>|null  null when the option is unset
     */
    private function splitList($option): ?array
    {
        if ($option === null || trim((string) $option) === '') {
            return null;
        }

        $parts = preg_split('/[,;
]+/', (string) $option) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($v) => $v !== ''));

        return empty($parts) ? null : array_values(array_unique($parts));
    }
}
