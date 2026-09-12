<?php

namespace AlphaDirect\Services\Reinsurance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared logic for cloning a reinsurance_treaty + its treaty_details rows
 * into a new period. Used by both:
 *   - `php artisan treaty:rollover` (CLI for ops)
 *   - ReinsuranceApiController::rolloverTreaty (UI button on treaty page)
 *
 * The service does NOT read from request / auth / input itself — callers
 * must prepare the options array + any auth checks. That keeps it testable
 * and reusable from CLI where there's no HTTP context.
 */
class TreatyRolloverService
{
    /**
     * Perform the rollover.
     *
     * @param  int   $sourceId  reinsurance_treaty.id to clone
     * @param  array $opts      keys:
     *                            name           string  new treaty_name (default: bumpName(source))
     *                            number         string  new treaty_number (default: same as name)
     *                            effective_from Y-m-d   default: source.effective_to + 1 day
     *                            effective_to   Y-m-d   default: +1 year -1 day from new from
     *                            dry_run        bool    default false
     *                            force          bool    default false — override duplicate-name / overlap checks
     *                            actor          mixed   auth()->id() or CLI sentinel — written to added_by
     *
     * @return array{
     *   source_id: int, source_name: string,
     *   new_treaty_id: int|null, new_name: string, new_number: string,
     *   effective_from: string, effective_to: string,
     *   cloned_details: int, dry_run: bool,
     *   log: array<int,string>
     * }
     *
     * @throws TreatyRolloverException with user-facing message when inputs are invalid.
     */
    public function rollover(int $sourceId, array $opts = []): array
    {
        if ($sourceId <= 0) {
            throw new TreatyRolloverException('sourceId must be > 0');
        }

        $source = DB::table('reinsurance_treaty')->where('id', $sourceId)->first();
        if (!$source) {
            throw new TreatyRolloverException("Source treaty #{$sourceId} not found");
        }

        // ─── Resolve new attributes ──────────────────────────────────────
        $newName = $opts['name'] ?: self::bumpName((string) $source->treaty_name);
        if (!$newName) {
            throw new TreatyRolloverException('Could not derive new treaty name from source. Pass an explicit name.');
        }
        $newNumber = $opts['number'] ?: $newName;

        $newFrom = $opts['effective_from']
            ?: (new \DateTime((string) $source->effective_to))->modify('+1 day')->format('Y-m-d');
        $newTo   = $opts['effective_to']
            ?: (new \DateTime($newFrom))->modify('+1 year -1 day')->format('Y-m-d');

        if (strtotime($newFrom) >= strtotime($newTo)) {
            throw new TreatyRolloverException("effective_from ({$newFrom}) must be before effective_to ({$newTo})");
        }

        $force  = (bool) ($opts['force']   ?? false);
        $dry    = (bool) ($opts['dry_run'] ?? false);
        $actor  = $opts['actor'] ?? null;

        // ─── Safety checks ──────────────────────────────────────────────
        $existing = DB::table('reinsurance_treaty')->where('treaty_name', $newName)->first(['id']);
        if ($existing && !$force) {
            throw new TreatyRolloverException("A treaty named \"{$newName}\" already exists (id={$existing->id}). Use force=true or pass a different name.");
        }
        if (strtotime($newFrom) <= strtotime((string) $source->effective_from) && !$force) {
            throw new TreatyRolloverException("New effective_from ({$newFrom}) must be after source.effective_from ({$source->effective_from}). Use force=true to override.");
        }

        $log           = [];
        $log[]         = "Source #{$sourceId} \"{$source->treaty_name}\" [{$source->effective_from} → {$source->effective_to}]";
        $log[]         = "New:   \"{$newName}\" [{$newFrom} → {$newTo}]";

        $detailsToClone = DB::table('reinsurance_treaty_details')->where('treaty_id', $sourceId)->count();
        $log[]          = "treaty_details rows to clone: {$detailsToClone}";

        // ─── Execute transactionally ─────────────────────────────────────
        $newTreatyId = null;
        $clonedDetails = 0;

        DB::beginTransaction();
        try {
            // Clone header row
            $treatyCols = Schema::getColumnListing('reinsurance_treaty');
            $newRow = [];
            foreach ($treatyCols as $col) {
                if ($col === 'id') continue;
                $newRow[$col] = $source->{$col} ?? null;
            }
            $newRow['treaty_name']    = $newName;
            $newRow['treaty_number']  = $newNumber;
            $newRow['effective_from'] = $newFrom;
            $newRow['effective_to']   = $newTo;
            $newRow['status']         = 1;
            if (in_array('created_at', $treatyCols)) $newRow['created_at'] = now();
            if (in_array('updated_at', $treatyCols)) $newRow['updated_at'] = now();
            if (in_array('added_by',   $treatyCols)) $newRow['added_by']   = $actor !== null ? (string) $actor : 'cli';

            $newTreatyId = DB::table('reinsurance_treaty')->insertGetId($newRow);
            $log[] = "reinsurance_treaty → new id {$newTreatyId}";

            // Clone details
            $detailCols = Schema::getColumnListing('reinsurance_treaty_details');
            foreach (DB::table('reinsurance_treaty_details')->where('treaty_id', $sourceId)->get() as $d) {
                $row = [];
                foreach ($detailCols as $col) {
                    if ($col === 'id') continue;
                    $row[$col] = $d->{$col} ?? null;
                }
                $row['treaty_id'] = $newTreatyId;
                if (in_array('created_at', $detailCols)) $row['created_at'] = now();
                if (in_array('updated_at', $detailCols)) $row['updated_at'] = now();
                DB::table('reinsurance_treaty_details')->insert($row);
                $clonedDetails++;
            }
            $log[] = "treaty_details cloned: {$clonedDetails}";

            if ($dry) {
                DB::rollBack();
                $log[] = "DRY-RUN — rolled back. No changes committed.";
            } else {
                DB::commit();
                $log[] = "Committed.";
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new TreatyRolloverException('Rollover failed: ' . $e->getMessage(), 0, $e);
        }

        return [
            'source_id'      => $sourceId,
            'source_name'    => (string) $source->treaty_name,
            'new_treaty_id'  => $dry ? null : $newTreatyId,
            'new_name'       => $newName,
            'new_number'     => $newNumber,
            'effective_from' => $newFrom,
            'effective_to'   => $newTo,
            'cloned_details' => $clonedDetails,
            'dry_run'        => $dry,
            'log'            => $log,
        ];
    }

    /**
     * Auto-derive next-period name from source.
     *   MUNICH_DOM_2024_2025  →  MUNICH_DOM_2025_2026
     *   MUNICH_COM_2024-2025  →  MUNICH_COM_2025-2026
     * Returns null if no YYYY[_-]YYYY pattern.
     */
    public static function bumpName(string $sourceName): ?string
    {
        if (preg_match('/(.+?)(\d{4})([_\-])(\d{4})(.*)$/', $sourceName, $m)) {
            return $m[1] . ((int) $m[2] + 1) . $m[3] . ((int) $m[4] + 1) . $m[5];
        }
        return null;
    }
}
