<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill blank term dates on Instant Insurance (MIS / MIB / BUN) policies.
 *
 * Root cause: several lightweight Instant creation flows saved the policy
 * without term_start_date / term_end_date / expiry_date, so the policy Start
 * Date and End Date columns rendered blank (a CRITICAL bug reported on the MIS
 * module — ~204k policies affected).
 *
 * Strategy (1-year term, agreed with finance):
 *   term_start_date := COALESCE(policyActivatedDate, billingStartDate, DATE(created_at))
 *   term_end_date   := term_start_date + 1 year
 *   expiry_date     := term_start_date + 1 year
 *
 * Safety:
 *   - Only writes columns that are currently NULL (idempotent; re-runnable).
 *   - Batched by primary key to avoid a long lock on the live policies table.
 *   - Scoped to the three Instant prefixes only.
 *   - Source date may be ISO ('Y-m-d' / 'Y-m-d H:i:s') OR legacy 'd/m/Y' string
 *     (Botswana date format used by older Instant flows). parseFlexibleDate()
 *     tries each format; if all fail, the row is skipped (not crashed) and the
 *     skipped count is logged so a follow-up manual pass can address residuals.
 */
class BackfillInstantPolicyTermDates extends Migration
{
    private const PREFIXES  = ['MIS', 'MIB', 'BUN'];
    private const BATCH     = 2000;

    public function up()
    {
        $maxId    = (int) DB::table('policies')->max('id');
        $updated  = 0;
        $skipped  = 0;
        $samples  = []; // first few skipped IDs (cap at 20)

        for ($from = 0; $from <= $maxId; $from += self::BATCH) {
            $to = $from + self::BATCH;

            $rows = DB::table('policies')
                ->where('id', '>', $from)->where('id', '<=', $to)
                ->where(function ($q) {
                    foreach (self::PREFIXES as $p) {
                        $q->orWhere('policyNumber', 'like', $p . '%');
                    }
                })
                ->where(function ($q) {
                    $q->whereNull('term_start_date')
                      ->orWhereNull('term_end_date')
                      ->orWhereNull('expiry_date');
                })
                ->select('id', 'term_start_date', 'term_end_date', 'expiry_date',
                         'policyActivatedDate', 'billingStartDate', 'created_at')
                ->get();

            foreach ($rows as $r) {
                $startRaw = $r->term_start_date
                    ?: $r->policyActivatedDate
                    ?: $r->billingStartDate
                    ?: $r->created_at;
                if (!$startRaw) {
                    continue; // nothing to anchor on — leave untouched (not counted as skip; no anchor at all)
                }

                $startC = $this->parseFlexibleDate($startRaw);
                if ($startC === null) {
                    $skipped++;
                    if (count($samples) < 20) {
                        $samples[] = ['id' => $r->id, 'raw' => $startRaw];
                    }
                    continue;
                }

                $start  = $startC->format('Y-m-d');
                $expiry = $startC->copy()->addYear()->format('Y-m-d');

                $set = [];
                if (empty($r->term_start_date)) $set['term_start_date'] = $start;
                if (empty($r->term_end_date))   $set['term_end_date']   = $expiry;
                if (empty($r->expiry_date))     $set['expiry_date']     = $expiry;

                if ($set) {
                    DB::table('policies')->where('id', $r->id)->update($set);
                    $updated++;
                }
            }
        }

        Log::info('BackfillInstantPolicyTermDates: completed', [
            'rows_updated'    => $updated,
            'rows_skipped'    => $skipped,
            'sample_skipped'  => $samples,
        ]);
    }

    /**
     * Parse a date that may be in ISO ('Y-m-d', 'Y-m-d H:i:s') or legacy 'd/m/Y'
     * format (the Botswana day-first string written by older Instant creation
     * flows). Returns a Carbon instance, or null if no known format applies.
     *
     * Order matters:
     *   1. ISO first — handles DATE / DATETIME columns + Carbon-formatted strings.
     *   2. d/m/Y — Botswana legacy. Strict format match to avoid mis-parsing.
     *   3. d-m-Y, d/m/y — common variants seen in legacy fields.
     *
     * Anything else returns null and the caller skips the row, logging the
     * sample so a follow-up manual pass can decide what to do with residuals.
     */
    private function parseFlexibleDate($value): ?\Carbon\Carbon
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        // 1. ISO — fast path for proper DATE / DATETIME column values
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            try {
                return \Carbon\Carbon::parse($value)->startOfDay();
            } catch (\Throwable $e) {
                // fall through
            }
        }

        // 2-3. Legacy Botswana day-first formats
        foreach (['d/m/Y', 'd-m-Y', 'd/m/y', 'd-m-y'] as $fmt) {
            try {
                $d = \Carbon\Carbon::createFromFormat($fmt, $value);
                // Strict re-format check — catches "26/02/2022" succeeding under
                // a wrong format that produced rubbish (Carbon is forgiving).
                if ($d !== false && $d->format($fmt) === $value) {
                    return $d->startOfDay();
                }
            } catch (\Throwable $e) {
                // try next format
            }
        }

        return null;
    }

    /**
     * No-op. We cannot distinguish backfilled dates from genuinely-entered ones,
     * so reversing would risk wiping legitimate data. Intentionally not reversed.
     */
    public function down()
    {
        // Intentionally irreversible — see note above.
    }
}
