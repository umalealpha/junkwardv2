<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off data reconciliation for HCB-001: HospitalCashbackController's
 * direct-creation flow used to write co-applicants to `policy_members`
 * instead of the canonical `hospital_Cashback_coapplicants` (the table the
 * policy-detail page actually reads, and the table all new writes — fixed
 * in this same change — now go through). This command:
 *
 *   1. Copies any `policy_members` rows belonging to product_id=9 (HCB)
 *      policies into `hospital_Cashback_coapplicants`, skipping rows that
 *      already have a matching (policy_id, first_name, last_name) row there.
 *   2. Normalises any `hospital_Cashback_coapplicants.relation` values left
 *      as legacy numeric (2=spouse, 3=child) by the bundle creation path,
 *      to the string form ('spouse'/'child') everything else uses.
 *
 * Always run with --dry-run first and review the output before applying.
 * Idempotent — safe to re-run; already-reconciled rows are skipped.
 */
class ReconcileHcbCoapplicants extends Command
{
    protected $signature = 'hcb:reconcile-coapplicants {--dry-run : Show what would change without writing}';

    protected $description = 'Move HCB co-applicants stuck in policy_members into hospital_Cashback_coapplicants, and normalise legacy numeric relation values.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun ? 'DRY RUN — no changes will be written.' : 'Applying changes.');

        $this->reconcileSplitTable($dryRun);
        $this->normaliseNumericRelations($dryRun);

        return self::SUCCESS;
    }

    private function reconcileSplitTable(bool $dryRun): void
    {
        $rows = DB::table('policy_members')
            ->join('policies', 'policies.id', '=', 'policy_members.policy_id')
            ->where('policies.product_id', 9)
            ->select('policy_members.*')
            ->get();

        $this->line("Found {$rows->count()} policy_members row(s) belonging to HCB policies.");

        $moved = 0;
        foreach ($rows as $row) {
            $exists = DB::table('hospital_Cashback_coapplicants')
                ->where('policy_id', $row->policy_id)
                ->where('first_name', $row->first_name)
                ->where('last_name', $row->last_name)
                ->exists();
            if ($exists) {
                continue;
            }

            $relation = strtolower((string) $row->relation);
            if (!in_array($relation, ['spouse', 'child'], true)) {
                $relation = ((int) $row->relation === 2) ? 'spouse' : 'child';
            }

            $this->line(sprintf(
                '  policy_id=%d "%s %s" (%s) -> hospital_Cashback_coapplicants',
                $row->policy_id,
                (string) $row->first_name,
                (string) $row->last_name,
                $relation
            ));

            if (!$dryRun) {
                DB::table('hospital_Cashback_coapplicants')->insert([
                    'policy_id'   => $row->policy_id,
                    'relation'    => $relation,
                    'first_name'  => $row->first_name,
                    'middle_name' => $row->middle_name ?? null,
                    'last_name'   => $row->last_name,
                    'gender'      => $row->gender,
                    'dob'         => $row->dob,
                    'omang'       => $row->omang    ?? null,
                    'passport'    => $row->passport ?? null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
            $moved++;
        }

        $this->info(($dryRun ? 'Would move ' : 'Moved ') . "{$moved} row(s).");
    }

    private function normaliseNumericRelations(bool $dryRun): void
    {
        $rows = DB::table('hospital_Cashback_coapplicants')
            ->whereIn('relation', ['2', '3', 2, 3])
            ->get(['id', 'relation']);

        $this->line("Found {$rows->count()} hospital_Cashback_coapplicants row(s) with a legacy numeric relation.");

        foreach ($rows as $row) {
            $newRelation = ((int) $row->relation === 2) ? 'spouse' : 'child';
            $this->line("  id={$row->id}: {$row->relation} -> {$newRelation}");
            if (!$dryRun) {
                DB::table('hospital_Cashback_coapplicants')->where('id', $row->id)->update(['relation' => $newRelation]);
            }
        }

        $this->info(($dryRun ? 'Would normalise ' : 'Normalised ') . "{$rows->count()} row(s).");
    }
}
