<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A UW-owned inflation rule: "uplift this sum insured by this percent".
 *
 * Auditable on purpose — a row here moves premium on live renewals, so every
 * edit to a percent, a band or an effective date has to be answerable from the
 * `audits` table.
 *
 * The matching itself lives in
 * AlphaDirect\Services\Inflation\InflationRateResolver — this model only knows
 * how to narrow the candidate set (scopes) and how to judge a single line
 * against itself (matches* + specificity).
 *
 * @see \AlphaDirect\Services\Inflation\InflationRateResolver
 */
class InflationRateMaster extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table   = 'inflation_rate_master';
    protected $guarded = [];

    protected $casts = [
        'product_id'      => 'integer',
        'coverage_id'     => 'integer',
        'sub_coverage_id' => 'integer',
        'si_from'         => 'float',
        'si_to'           => 'float',
        'pct'             => 'float',
        'priority'        => 'integer',
        'is_active'       => 'boolean',
    ];

    // ──────────────────────────────────────────────────────────────────
    // Narrowing
    // ──────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /** Rules for this product plus the product-wildcard rules. */
    public function scopeForProduct($query, ?int $productId)
    {
        if ($productId === null) {
            return $query;
        }

        return $query->where(function ($q) use ($productId) {
            $q->whereNull('product_id')->orWhere('product_id', $productId);
        });
    }

    /**
     * Rules whose window contains $date, where $date is the ACTION's
     * effective_from — the date the uplifted sum insured starts to cover.
     */
    public function scopeEffectiveOn($query, $date)
    {
        $day = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : (string) $date;

        return $query
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $day))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day));
    }

    public function scopeForTransactionType($query, ?string $type)
    {
        if ($type === null || $type === '') {
            return $query;
        }

        return $query->where(function ($q) use ($type) {
            $q->whereNull('transaction_type')->orWhere('transaction_type', $type);
        });
    }

    // ──────────────────────────────────────────────────────────────────
    // Per-line judgement
    // ──────────────────────────────────────────────────────────────────

    /** Case- and whitespace-insensitive compare, same rule as the command. */
    public static function normalise(?string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value) ?? ''));
    }

    /**
     * Does this rule name the given SECTION?
     *
     * A rule with neither coverage_id nor coverage_name is a product-wide rule
     * and matches every section.
     */
    public function matchesCoverage(?int $coverageId, ?string $coverageName): bool
    {
        if ($this->coverage_id) {
            return (int) $this->coverage_id === (int) $coverageId;
        }

        if (trim((string) $this->coverage_name) !== '') {
            return self::normalise($this->coverage_name) === self::normalise($coverageName);
        }

        return true;
    }

    /**
     * Does this rule name the given LINE (the wizard Description)?
     *
     * A rule with neither sub_coverage_id nor sub_coverage_name uplifts every
     * line in the section it matched. Deliberately broad, and deliberately
     * something UW has to write on purpose.
     */
    public function matchesSubCoverage(?int $subCoverageId, ?string $subCoverageName): bool
    {
        if ($this->sub_coverage_id) {
            return (int) $this->sub_coverage_id === (int) $subCoverageId;
        }

        if (trim((string) $this->sub_coverage_name) !== '') {
            return self::normalise($this->sub_coverage_name) === self::normalise($subCoverageName);
        }

        return true;
    }

    /** Inclusive band on the line's CURRENT sum insured. */
    public function matchesSumInsured(float $sumInsured): bool
    {
        if ($this->si_from !== null && $sumInsured < (float) $this->si_from) {
            return false;
        }

        if ($this->si_to !== null && $sumInsured > (float) $this->si_to) {
            return false;
        }

        return true;
    }

    /**
     * How specific this rule is, for picking a winner when several match.
     *
     * Line beats section beats product; an id beats a name (an id can only
     * mean one master row, a name can be carried by several); a sum-insured
     * band beats no band. `priority` sits above the whole scale so UW always
     * has a way to force one rule to win without re-shaping the others.
     */
    public function specificity(): int
    {
        $score = $this->priority * 1000;

        if ($this->sub_coverage_id) {
            $score += 120;
        } elseif (trim((string) $this->sub_coverage_name) !== '') {
            $score += 100;
        }

        if ($this->coverage_id) {
            $score += 60;
        } elseif (trim((string) $this->coverage_name) !== '') {
            $score += 50;
        }

        if ($this->product_id) {
            $score += 30;
        }

        if ($this->si_from !== null && $this->si_to !== null) {
            $score += 20;
        } elseif ($this->si_from !== null || $this->si_to !== null) {
            $score += 10;
        }

        if (trim((string) $this->transaction_type) !== '') {
            $score += 5;
        }

        return $score;
    }

    /** Short human label for the console table / CSV / note marker. */
    public function label(): string
    {
        return trim((string) $this->name) !== ''
            ? '#' . $this->id . ' ' . $this->name
            : '#' . $this->id;
    }
}
