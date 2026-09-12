<?php

namespace AlphaDirect\Services\Inflation;

use AlphaDirect\Models\InflationRateMaster;
use Illuminate\Support\Collection;

/**
 * Turns the inflation master into a percent for one sum-insured line.
 *
 * The command (and anything else that later wants the same number — a renewal
 * quote screen, an API, a what-if report) asks two questions:
 *
 *   1. rulesFor($productId, $effectiveDate, $transactionType)
 *      → the candidate rules, loaded once per action rather than per line.
 *
 *   2. resolve($rules, $sectionId, $sectionName, $lineId, $lineName, $si)
 *      → the single winning rule for that line, or null when nothing matches
 *        (null means LEAVE THE LINE ALONE — never "assume 10%").
 *
 * Scope of the master is decided by scopeOf(): which products and which section
 * names any active rule mentions, so the command can find its own targets
 * without being told --product / --section on the command line.
 */
class InflationRateResolver
{
    /** Rules cached per "product|date|type" so a run touches the table once per combination. */
    private array $cache = [];

    /** @var array<int,int>|null When set, only these rule ids are considered (the --rule option). */
    private ?array $onlyRuleIds = null;

    /**
     * Run one rule (or a few) instead of the whole master — how UW tries a new
     * rule out before letting it join the September run.
     *
     * @param array<int,int|string> $ids
     */
    public function restrictTo(array $ids): self
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric'))));

        $this->onlyRuleIds = empty($ids) ? null : $ids;
        $this->cache       = [];

        return $this;
    }

    /**
     * Candidate rules for an action, most specific first.
     *
     * @param  int|null    $productId        policies.product_id
     * @param  string|null $effectiveDate    the action's effective_from (Y-m-d)
     * @param  string|null $transactionType  e.g. ANNIVERSARY-RENEW
     * @return Collection<int,InflationRateMaster>
     */
    public function rulesFor(?int $productId, ?string $effectiveDate, ?string $transactionType): Collection
    {
        $key = ($productId ?? '*') . '|' . ($effectiveDate ?? '*') . '|' . ($transactionType ?? '*');

        if (!\array_key_exists($key, $this->cache)) {
            $query = InflationRateMaster::query()
                ->active()
                ->forProduct($productId)
                ->forTransactionType($transactionType)
                ->when($this->onlyRuleIds, fn ($q, array $ids) => $q->whereIn('id', $ids));

            if ($effectiveDate !== null && $effectiveDate !== '') {
                $query->effectiveOn($effectiveDate);
            }

            $this->cache[$key] = $query->get()
                ->sortByDesc(fn (InflationRateMaster $r) => [$r->specificity(), $r->id])
                ->values();
        }

        return $this->cache[$key];
    }

    /**
     * The winning rule for one line, or null when no rule claims it.
     *
     * $rules is already narrowed by product / date / transaction type, and
     * already ordered most-specific-first, so the first full match wins — a
     * "Sum Insured on Houseowner-Buildings" rule beats a "whole of product 8"
     * rule without either knowing about the other.
     *
     * @param Collection<int,InflationRateMaster> $rules
     */
    public function resolve(
        Collection $rules,
        ?int $sectionId,
        ?string $sectionName,
        ?int $lineId,
        ?string $lineName,
        float $sumInsured
    ): ?InflationRateMaster {
        foreach ($rules as $rule) {
            if (!$rule->matchesCoverage($sectionId, $sectionName)) {
                continue;
            }

            if (!$rule->matchesSubCoverage($lineId, $lineName)) {
                continue;
            }

            if (!$rule->matchesSumInsured($sumInsured)) {
                continue;
            }

            return $rule;
        }

        return null;
    }

    /**
     * What the master, as it stands, is asking to be uplifted.
     *
     * Used by the command to build its own work-list: there is no CSV and no
     * operator-supplied product list — the rules ARE the scope.
     *
     * A rule with a NULL product (or a NULL section) is a wildcard, reported
     * here as `null` in the corresponding list so the caller can tell the
     * difference between "these products" and "every product".
     *
     * @param  string|null $onOrAfter  only consider rules that can still fire on/after this date
     * @return array{
     *   products: array<int,int|null>,
     *   sections: array<int,string|null>,
     *   section_ids: array<int,int>,
     *   transaction_types: array<int,string|null>,
     *   rules: Collection<int,InflationRateMaster>
     * }
     */
    public function scopeOf(?string $onOrAfter = null): array
    {
        $rules = InflationRateMaster::query()
            ->active()
            ->when($this->onlyRuleIds, fn ($q, array $ids) => $q->whereIn('id', $ids))
            ->when($onOrAfter, fn ($q) => $q->where(function ($w) use ($onOrAfter) {
                $w->whereNull('effective_to')->orWhereDate('effective_to', '>=', $onOrAfter);
            }))
            ->get();

        $products = $rules->map(fn ($r) => $r->product_id ? (int) $r->product_id : null)
            ->unique()->values()->all();

        // Section names a rule can be matched by NAME on. A rule that names its
        // section by id contributes to section_ids instead; a rule that names no
        // section at all contributes a null (= every section).
        $sections = $rules->map(function ($r) {
            if ($r->coverage_id) {
                return false;                                   // handled via section_ids
            }
            $name = trim((string) $r->coverage_name);
            return $name === '' ? null : $name;
        })->filter(fn ($v) => $v !== false)->unique()->values()->all();

        $sectionIds = $rules->pluck('coverage_id')->filter()->map(fn ($v) => (int) $v)
            ->unique()->values()->all();

        $types = $rules->map(fn ($r) => trim((string) $r->transaction_type) === '' ? null : (string) $r->transaction_type)
            ->unique()->values()->all();

        return [
            'products'          => $products,
            'sections'          => $sections,
            'section_ids'       => $sectionIds,
            'transaction_types' => $types,
            'rules'             => $rules,
        ];
    }

    /**
     * Human-readable one-liner for a rule, for the console header and the CSV.
     */
    public function describe(InflationRateMaster $rule): string
    {
        $bits = [];
        $bits[] = 'product ' . ($rule->product_id ?: 'ANY');
        $bits[] = 'section ' . ($rule->coverage_id
            ? '#' . $rule->coverage_id
            : (trim((string) $rule->coverage_name) !== '' ? $rule->coverage_name : 'ANY'));
        $bits[] = 'line ' . ($rule->sub_coverage_id
            ? '#' . $rule->sub_coverage_id
            : (trim((string) $rule->sub_coverage_name) !== '' ? $rule->sub_coverage_name : 'ALL'));

        if ($rule->si_from !== null || $rule->si_to !== null) {
            $bits[] = 'SI ' . ($rule->si_from !== null ? number_format((float) $rule->si_from, 2) : '0')
                . ' → ' . ($rule->si_to !== null ? number_format((float) $rule->si_to, 2) : '∞');
        }

        $window = [];
        if ($rule->effective_from) {
            $window[] = 'from ' . substr((string) $rule->effective_from, 0, 10);
        }
        if ($rule->effective_to) {
            $window[] = 'to ' . substr((string) $rule->effective_to, 0, 10);
        }
        if (!empty($window)) {
            $bits[] = implode(' ', $window);
        }

        return sprintf(
            '%s: %s%% — %s',
            $rule->label(),
            rtrim(rtrim(number_format((float) $rule->pct, 4, '.', ''), '0'), '.'),
            implode(', ', $bits)
        );
    }
}
