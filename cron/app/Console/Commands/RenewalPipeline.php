<?php

/**
 * Required DB table (run once):
 *
 * CREATE TABLE renewal_pipeline (
 *     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     policy_id INT NOT NULL,
 *     policy_number VARCHAR(100) NOT NULL,
 *     customer_id INT NOT NULL,
 *     customer_name VARCHAR(200),
 *     customer_phone VARCHAR(20),
 *     product_name VARCHAR(100),
 *     premium DECIMAL(10,2),
 *     expiry_date DATE NOT NULL,
 *     days_until_expiry INT,
 *     stage ENUM('30_day','15_day','7_day','expired','renewed','dismissed') DEFAULT '30_day',
 *     sms_sent_at TIMESTAMP NULL,
 *     email_sent_at TIMESTAMP NULL,
 *     reminder_count INT DEFAULT 0,
 *     status ENUM('pending','contacted','renewed','lapsed') DEFAULT 'pending',
 *     created_at TIMESTAMP NULL,
 *     updated_at TIMESTAMP NULL,
 *     INDEX idx_policy (policy_id),
 *     INDEX idx_stage (stage, status),
 *     INDEX idx_expiry (expiry_date)
 * ) ENGINE=InnoDB;
 */

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;
use Carbon\Carbon;

class RenewalPipeline extends Command
{
    protected $signature = 'renewal:pipeline {--dry-run : Read-only mode, no DB writes}';
    protected $description = 'Daily renewal pipeline — finds expiring policies, progresses stages, and auto-resolves renewals/lapses';

    private $dryRun = false;
    private $stats = [
        'new_entries'   => 0,
        'transitions'   => [],
        'auto_renewed'  => 0,
        'auto_lapsed'   => 0,
    ];

    public function handle()
    {
        $this->dryRun = $this->option('dry-run');

        // Track cron status
        $cronStatus = null;
        if (!$this->dryRun) {
            try {
                $cronStatus = CronStatus::create([
                    'name'  => 'renewal:pipeline',
                    'start' => now(),
                ]);
            } catch (\Exception $e) {
                // CronStatus tracking is optional
            }
        }

        $this->info("========================================");
        $this->info("RENEWAL PIPELINE" . ($this->dryRun ? ' [DRY RUN]' : ''));
        $this->info("Started: " . now());
        $this->info("========================================\n");

        if ($this->dryRun) {
            $this->warn("[DRY RUN] No data will be written to the database.\n");
        }

        try {
            $this->findExpiringPolicies();
            $this->progressStages();
            $this->autoResolve();
            $this->printSummary();

            if ($cronStatus) {
                $cronStatus->update(['end' => now()]);
            }

            return 0;

        } catch (\Exception $e) {
            if ($cronStatus) {
                $cronStatus->update(['end' => now()]);
            }
            $this->error("FAILED: " . $e->getMessage());
            Log::error('RenewalPipeline failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Step 1: Find active policies expiring within 30 days and insert into pipeline.
     */
    private function findExpiringPolicies()
    {
        $this->info("--- Step 1: Finding expiring policies ---");

        $today = Carbon::today();
        $thirtyDaysOut = Carbon::today()->addDays(30);

        // Get policy IDs already in the pipeline (non-terminal stages)
        $existingPolicyIds = DB::table('renewal_pipeline')
            ->whereNotIn('stage', ['renewed', 'dismissed'])
            ->whereNotIn('status', ['renewed', 'lapsed'])
            ->pluck('policy_id')
            ->toArray();

        // Find active policies expiring within 30 days not already tracked
        $expiring = DB::table('policies')
            ->join('customer', 'policies.customer_id', '=', 'customer.id')
            ->leftJoin('products', 'policies.product_id', '=', 'products.id')
            ->where('policies.status', 1)
            ->whereBetween('policies.expiry_date', [$today->toDateString(), $thirtyDaysOut->toDateString()])
            ->when(count($existingPolicyIds) > 0, function ($query) use ($existingPolicyIds) {
                $query->whereNotIn('policies.id', $existingPolicyIds);
            })
            ->select([
                'policies.id as policy_id',
                'policies.policyNumber as policy_number',
                'policies.customer_id',
                DB::raw("CONCAT(customer.firstName, ' ', customer.lastName) as customer_name"),
                'customer.cellphone as customer_phone',
                'products.name as product_name',
                'policies.premium',
                'policies.expiry_date',
            ])
            ->limit(1000)
            ->get();

        $this->info("  Found {$expiring->count()} new expiring policies.");

        foreach ($expiring as $policy) {
            $daysUntil = Carbon::today()->diffInDays(Carbon::parse($policy->expiry_date), false);
            $stage = $this->determineStage($daysUntil);

            if (!$this->dryRun) {
                DB::table('renewal_pipeline')->insert([
                    'policy_id'        => $policy->policy_id,
                    'policy_number'    => $policy->policy_number,
                    'customer_id'      => $policy->customer_id,
                    'customer_name'    => $policy->customer_name,
                    'customer_phone'   => $policy->customer_phone,
                    'product_name'     => $policy->product_name,
                    'premium'          => $policy->premium,
                    'expiry_date'      => $policy->expiry_date,
                    'days_until_expiry' => $daysUntil,
                    'stage'            => $stage,
                    'status'           => 'pending',
                    'reminder_count'   => 0,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            $this->stats['new_entries']++;
        }

        $this->info("  Inserted: {$this->stats['new_entries']} entries.\n");
    }

    /**
     * Step 2: Progress existing pipeline entries through stages based on current days until expiry.
     */
    private function progressStages()
    {
        $this->info("--- Step 2: Progressing stages ---");

        $today = Carbon::today();

        // Active pipeline entries (not yet resolved)
        $entries = DB::table('renewal_pipeline')
            ->whereIn('stage', ['30_day', '15_day', '7_day'])
            ->whereIn('status', ['pending', 'contacted'])
            ->get();

        foreach ($entries as $entry) {
            $daysUntil = Carbon::today()->diffInDays(Carbon::parse($entry->expiry_date), false);
            $newStage = $this->determineStage($daysUntil);

            // Only progress forward (never backward)
            if ($newStage !== $entry->stage && $this->stageOrder($newStage) > $this->stageOrder($entry->stage)) {
                $transition = "{$entry->stage} -> {$newStage}";

                if (!$this->dryRun) {
                    DB::table('renewal_pipeline')
                        ->where('id', $entry->id)
                        ->update([
                            'stage'            => $newStage,
                            'days_until_expiry' => $daysUntil,
                            'updated_at'       => now(),
                        ]);
                }

                $this->stats['transitions'][$transition] = ($this->stats['transitions'][$transition] ?? 0) + 1;
            } else {
                // Just update days_until_expiry
                if (!$this->dryRun) {
                    DB::table('renewal_pipeline')
                        ->where('id', $entry->id)
                        ->update([
                            'days_until_expiry' => $daysUntil,
                            'updated_at'       => now(),
                        ]);
                }
            }
        }

        $totalTransitions = array_sum($this->stats['transitions']);
        $this->info("  Processed {$entries->count()} entries, {$totalTransitions} stage transitions.");

        foreach ($this->stats['transitions'] as $transition => $count) {
            $this->info("    {$transition}: {$count}");
        }

        $this->info("");
    }

    /**
     * Step 3: Auto-resolve entries where the policy was renewed or cancelled.
     */
    private function autoResolve()
    {
        $this->info("--- Step 3: Auto-resolving ---");

        // Get all unresolved pipeline entries
        $entries = DB::table('renewal_pipeline')
            ->whereIn('status', ['pending', 'contacted'])
            ->get();

        foreach ($entries as $entry) {
            // Check if policy was renewed: a newer active policy exists for same customer + product
            $renewed = DB::table('policies')
                ->join('renewal_pipeline as rp', function ($join) use ($entry) {
                    // We need the original policy to know the product
                    $join->on('rp.policy_id', '=', DB::raw($entry->policy_id));
                })
                ->where('policies.customer_id', $entry->customer_id)
                ->where('policies.status', 1)
                ->where('policies.id', '!=', $entry->policy_id)
                ->where('policies.created_at', '>', $entry->expiry_date)
                ->whereExists(function ($query) use ($entry) {
                    $query->select(DB::raw(1))
                        ->from('policies as original')
                        ->where('original.id', $entry->policy_id)
                        ->whereColumn('policies.product_id', 'original.product_id');
                })
                ->exists();

            if ($renewed) {
                if (!$this->dryRun) {
                    DB::table('renewal_pipeline')
                        ->where('id', $entry->id)
                        ->update([
                            'stage'      => 'renewed',
                            'status'     => 'renewed',
                            'updated_at' => now(),
                        ]);
                }
                $this->stats['auto_renewed']++;
                continue;
            }

            // Check if the original policy was cancelled (status != 1)
            $policyCancelled = DB::table('policies')
                ->where('id', $entry->policy_id)
                ->where('status', '!=', 1)
                ->exists();

            if ($policyCancelled) {
                if (!$this->dryRun) {
                    DB::table('renewal_pipeline')
                        ->where('id', $entry->id)
                        ->update([
                            'status'     => 'lapsed',
                            'updated_at' => now(),
                        ]);
                }
                $this->stats['auto_lapsed']++;
            }
        }

        $this->info("  Auto-renewed: {$this->stats['auto_renewed']}");
        $this->info("  Auto-lapsed:  {$this->stats['auto_lapsed']}\n");
    }

    /**
     * Print pipeline summary by stage.
     */
    private function printSummary()
    {
        $this->info("========================================");
        $this->info("RENEWAL PIPELINE SUMMARY");
        $this->info("========================================");
        $this->info("New entries added:  {$this->stats['new_entries']}");
        $this->info("Auto-renewed:       {$this->stats['auto_renewed']}");
        $this->info("Auto-lapsed:        {$this->stats['auto_lapsed']}");

        if (count($this->stats['transitions']) > 0) {
            $this->info("Stage transitions:");
            foreach ($this->stats['transitions'] as $transition => $count) {
                $this->info("  {$transition}: {$count}");
            }
        } else {
            $this->info("Stage transitions:  0");
        }

        // Current pipeline counts by stage
        $stageCounts = DB::table('renewal_pipeline')
            ->select('stage', DB::raw('COUNT(*) as count'))
            ->whereIn('status', ['pending', 'contacted'])
            ->groupBy('stage')
            ->pluck('count', 'stage')
            ->toArray();

        $this->info("\nCurrent pipeline:");
        $stages = ['30_day', '15_day', '7_day', 'expired'];
        foreach ($stages as $stage) {
            $count = $stageCounts[$stage] ?? 0;
            $this->info("  {$stage}: {$count}");
        }

        $totalActive = array_sum($stageCounts);
        $this->info("  TOTAL active: {$totalActive}");

        // Resolved totals
        $resolvedCounts = DB::table('renewal_pipeline')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereIn('status', ['renewed', 'lapsed'])
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $this->info("\nResolved:");
        $this->info("  Renewed: " . ($resolvedCounts['renewed'] ?? 0));
        $this->info("  Lapsed:  " . ($resolvedCounts['lapsed'] ?? 0));

        $this->info("\nCompleted: " . now());
        $this->info("========================================\n");
    }

    /**
     * Determine the pipeline stage based on days until expiry.
     */
    private function determineStage(int $daysUntil): string
    {
        if ($daysUntil <= 0) {
            return 'expired';
        }
        if ($daysUntil <= 7) {
            return '7_day';
        }
        if ($daysUntil <= 15) {
            return '15_day';
        }
        return '30_day';
    }

    /**
     * Numeric ordering for stages to ensure we only progress forward.
     */
    private function stageOrder(string $stage): int
    {
        $order = [
            '30_day'  => 1,
            '15_day'  => 2,
            '7_day'   => 3,
            'expired' => 4,
        ];

        return $order[$stage] ?? 0;
    }
}
