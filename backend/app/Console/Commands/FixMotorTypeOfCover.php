<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Support\MotorCoverType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ONE-TIME data fix for vehicles saved through the V2 wizard's coverage grid.
 *
 * That grid submitted the human LABEL for Type of Cover ("Third party only",
 * "Third party, fire and theft") while every consumer of
 * motor.type_of_cover matches the canonical token ('third_party_only',
 * 'Third_fire_and_theft'). Consequences on an affected row:
 *   - the quotation's "Summary Of Vehicles" printed an EMPTY Type of Cover
 *     cell (no branch of the blade's if/elseif ladder matched), and
 *   - every `type_of_cover != "third_party_only"` gate in the quote sheet /
 *     policy schedule treated a third-party vehicle as comprehensive.
 * (Write path fixed in PolicyCreateController + the wizard select; this
 * corrects the rows already stored.)
 *
 * Intentionally safe:
 *   - DRY RUN by default; pass --fix to write.
 *   - Only rewrites values MotorCoverType recognises as an alias of a
 *     canonical token. Anything unrecognised is reported and left alone.
 *   - Covers both type_of_cover and the type_of_cover_main baseline column.
 *
 * Run on any container sharing the DB (backend or cron):
 *   php artisan policy:fix-motor-type-of-cover                         # dry run, all
 *   php artisan policy:fix-motor-type-of-cover --policy=COMG2024108721 # one policy
 *   php artisan policy:fix-motor-type-of-cover --fix                   # apply
 */
class FixMotorTypeOfCover extends Command
{
    protected $signature = 'policy:fix-motor-type-of-cover
        {--policy= : Restrict to a single policy (id or policyNumber) — use for verification first}
        {--fix : Apply the fix. Without this flag the command only reports (dry run)}';

    protected $description = 'One-time fix: rewrite motor.type_of_cover / type_of_cover_main labels ("Third party only") to the canonical tokens the quotation matches on.';

    public function handle()
    {
        $apply     = (bool) $this->option('fix');
        $policyRef = trim((string) $this->option('policy'));

        $hasMainColumn = \Schema::hasColumn('motor', 'type_of_cover_main');

        $rows = DB::table('motor as m')
            ->join('policy_coverages as pc', 'pc.id', '=', 'm.policy_coverage_id')
            ->join('policies as p', 'p.id', '=', 'pc.policy_id')
            ->when($policyRef !== '', function ($q) use ($policyRef) {
                $q->where(function ($w) use ($policyRef) {
                    $w->where('p.id', $policyRef)->orWhere('p.policyNumber', $policyRef);
                });
            })
            ->select(array_merge(
                ['m.id as motor_id', 'm.registration_no', 'm.type_of_cover', 'p.policyNumber', 'pc.action_id'],
                $hasMainColumn ? ['m.type_of_cover_main'] : []
            ))
            ->orderBy('m.id')
            ->get();

        $this->info(($apply ? '[FIX] ' : '[DRY RUN] ') . "Scanned {$rows->count()} motor rows"
            . ($policyRef !== '' ? " for policy {$policyRef}" : '') . '.');

        $fixed     = 0;
        $unknown   = 0;
        $policies  = [];

        foreach ($rows as $row) {
            $update = [];

            $canonical = MotorCoverType::normalize($row->type_of_cover);
            if ($canonical !== $row->type_of_cover && MotorCoverType::isCanonical($canonical)) {
                $update['type_of_cover'] = $canonical;
            } elseif (!empty($row->type_of_cover) && !MotorCoverType::isCanonical($canonical)) {
                $unknown++;
                Log::warning('fix-motor-type-of-cover: unrecognised type_of_cover — left alone', (array) $row);
                $this->warn("  motor #{$row->motor_id} ({$row->policyNumber} / {$row->registration_no}): unrecognised value '{$row->type_of_cover}' — skipped");
            }

            if ($hasMainColumn) {
                $canonicalMain = MotorCoverType::normalize($row->type_of_cover_main);
                if ($canonicalMain !== $row->type_of_cover_main && MotorCoverType::isCanonical($canonicalMain)) {
                    $update['type_of_cover_main'] = $canonicalMain;
                }
            }

            if (empty($update)) {
                continue;
            }

            $fixed++;
            $policies[$row->policyNumber] = true;

            Log::info('fix-motor-type-of-cover: row', [
                'motor_id'        => $row->motor_id,
                'policyNumber'    => $row->policyNumber,
                'action_id'       => $row->action_id,
                'registration_no' => $row->registration_no,
                'from'            => $row->type_of_cover,
                'to'              => $update['type_of_cover'] ?? $row->type_of_cover,
                'applied'         => $apply,
            ]);

            $this->line("  motor #{$row->motor_id} ({$row->policyNumber} / {$row->registration_no}): "
                . "'{$row->type_of_cover}' -> '" . ($update['type_of_cover'] ?? $row->type_of_cover) . "'");

            if ($apply) {
                // updated_at only — no premium column is touched, so no
                // pro-rata recompute and no endors_flag stamp is warranted.
                $update['updated_at'] = now();
                DB::table('motor')->where('id', $row->motor_id)->update($update);
            }
        }

        $this->line('');
        $this->info('Distinct policies affected: ' . count($policies));
        $this->info(($apply ? 'Rows updated: ' : 'Rows fixable now: ') . $fixed);
        if ($unknown) {
            $this->warn("Rows with an unrecognised value (needs manual review): {$unknown}");
        }
        if (!$apply && $fixed) {
            $this->comment('Dry run — re-run with --fix to apply.');
        }

        return 0;
    }
}
