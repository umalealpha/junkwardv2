<?php

namespace AlphaDirect\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\CronStatus;

class CalculateCommissions extends Command
{
    protected $signature = 'commission:calculate
                            {--days=1 : Look back N days for new policies (default 1, use 365+ for backfill)}
                            {--limit=0 : Max policies to process (0=unlimited)}';

    protected $description = 'Calculate daily commissions, check compliance gates, detect fraud';

    /** @var array Running totals for the end-of-run summary */
    private array $stats = [
        'commissions_created'   => 0,
        'payment_gates_met'     => 0,
        'commissions_approved'  => 0,
        'kyc_updates'           => 0,
        'clawbacks_created'     => 0,
        'bonuses_awarded'       => 0,
        'fraud_alerts_created'  => 0,
    ];

    public function handle(): int
    {
        $cronStatus = null;
        try {
            $cronStatus = CronStatus::create(['name' => 'commission:calculate', 'start' => now()]);
        } catch (\Exception $e) {
            // CronStatus logging is non-critical
        }

        $this->info("========================================");
        $this->info("COMMISSION CALCULATION ENGINE");
        $this->info("Started: " . now()->toDateTimeString());
        $this->info("========================================\n");

        try {
            $this->stepCalculateNewCommissions();
            $this->stepCheckPaymentGates();
            $this->stepCheckKycCompliance();
            $this->stepClawbackCancelledPolicies();
            $this->stepCheckBonusTargets();
            $this->stepFraudDetection();
        } catch (\Exception $e) {
            Log::error('commission:calculate — fatal error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error("Fatal error: " . $e->getMessage());
        }

        // Summary
        $this->info("\n========================================");
        $this->info("RUN COMPLETE — SUMMARY");
        $this->info("========================================");
        foreach ($this->stats as $key => $value) {
            $label = str_replace('_', ' ', ucfirst($key));
            $this->info("  {$label}: {$value}");
        }

        Log::info('commission:calculate completed', $this->stats);

        if ($cronStatus) {
            try {
                $cronStatus->update(['end' => now()]);
            } catch (\Exception $e) {
            }
        }

        return 0;
    }

    // =========================================================================
    //  Step 1 — Calculate commissions for newly issued policies (last 24 h)
    // =========================================================================
    private function stepCalculateNewCommissions(): void
    {
        $this->info("Step 1: Calculate commissions for new policies ...");

        try {
            $days = max(1, (int) $this->option('days'));
            $limit = (int) $this->option('limit');
            $since = Carbon::now()->subDays($days);

            // Policies issued since $since that have an agent and no ledger entry yet
            $query = DB::table('policies as p')
                ->leftJoin('commission_ledger as cl', function ($j) {
                    $j->on('cl.policy_id', '=', 'p.id')
                      ->where('cl.entry_type', '=', 'earned');
                })
                ->whereNull('cl.id')
                ->where('p.status', 1) // active
                ->where('p.agent_id', '>', 0)
                ->where('p.created_at', '>=', $since)
                ->select('p.id as policy_id', 'p.agent_id', 'p.product_id', 'p.premium', 'p.customer_id', 'p.created_at')
                ->orderBy('p.id');
            if ($limit > 0) $query->limit($limit);
            $policies = $query->get();
            $this->info("  Scanning {$days} day(s) back from {$since->toDateString()}, found {$policies->count()} eligible policies");

            $created = 0;

            foreach ($policies as $policy) {
                try {
                    $commission = $this->calculateForPolicy($policy);
                    if ($commission) {
                        $created++;
                    }
                } catch (\Exception $e) {
                    Log::warning("commission:calculate — Step 1 error for policy {$policy->policy_id}: " . $e->getMessage());
                }
            }

            $this->stats['commissions_created'] = $created;
            $this->info("  -> Processed {$policies->count()} policies, {$created} commissions created\n");
            Log::info("Step 1: Processed {$policies->count()} policies, {$created} commissions created");
        } catch (\Exception $e) {
            Log::error('commission:calculate — Step 1 failed: ' . $e->getMessage());
            $this->error("  Step 1 error: " . $e->getMessage());
        }
    }

    /**
     * Find the best matching rule for a policy and insert a ledger entry.
     */
    private function calculateForPolicy(object $policy): bool
    {
        $rules = DB::table('commission_rules')
            ->where(function ($q) use ($policy) {
                $q->where('product_id', $policy->product_id)
                  ->orWhereNull('product_id'); // "All Products" rules
            })
            ->where('status', 1)
            ->orderByDesc('priority')
            ->get();

        if ($rules->isEmpty()) {
            return false;
        }

        foreach ($rules as $rule) {
            $conditions = json_decode($rule->conditions ?? '{}', true) ?: [];

            // --- Condition: KYC required ---
            if (!empty($conditions['kyc_required'])) {
                $kycOk = DB::table('customer_kyc')
                    ->where('customer_id', $policy->customer_id)
                    ->where('compliance', 1)
                    ->exists();
                if (!$kycOk) {
                    continue; // rule doesn't match, try next
                }
            }

            // --- Condition: Pre-inspection required ---
            if (!empty($conditions['preinspection_required'])) {
                $inspOk = DB::table('vehicle')
                    ->where('policy_id', $policy->policy_id)
                    ->where('compliance', 1)
                    ->exists();
                if (!$inspOk) {
                    continue;
                }
            }

            // First matching rule wins — calculate commission
            $premium = (float) $policy->premium;
            if ($rule->commission_type === 'percentage') {
                $amount = round($premium * (float) $rule->commission_value / 100, 2);
            } else {
                $amount = (float) $rule->commission_value;
            }

            // Determine cooling period end
            $minActiveDays = (int) ($conditions['min_active_days'] ?? 0);
            $qualifyingDate = Carbon::parse($policy->created_at)->toDateString();
            $coolingEnd = $minActiveDays > 0
                ? Carbon::parse($qualifyingDate)->addDays($minActiveDays)->toDateString()
                : $qualifyingDate;

            // Check KYC compliance at insert time
            $kycCompliant = DB::table('customer_kyc')
                ->where('customer_id', $policy->customer_id)
                ->where('compliance', 1)
                ->exists() ? 1 : 0;

            // Check pre-inspection compliance at insert time
            $preinspectionCompliant = DB::table('vehicles')
                ->where('policy_id', $policy->policy_id)
                ->where('compliance', 1)
                ->exists() ? 1 : 0;

            // Look up agency_id from users table (agents are users with agency_id)
            $agencyId = DB::table('users')
                ->where('id', $policy->agent_id)
                ->value('agency_id');

            DB::table('commission_ledger')->insert([
                'policy_id'               => $policy->policy_id,
                'agent_id'                => $policy->agent_id,
                'agency_id'               => $agencyId,
                'rule_id'                 => $rule->id,
                'entry_type'              => 'earned',
                'commission_amount'       => $amount,
                'premium_amount'          => $premium,
                'commission_type'         => $rule->commission_type,
                'status'                  => 'pending',
                'qualifying_date'         => $qualifyingDate,
                'cooling_period_end'      => $coolingEnd,
                'payment_gate_met'        => 0,
                'kyc_compliant'           => $kycCompliant,
                'preinspection_compliant' => $preinspectionCompliant,
                'calculated_at'           => now(),
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);

            return true; // one rule per policy
        }

        return false; // no matching rule
    }

    // =========================================================================
    //  Step 2 — Check payment gate for pending commissions
    // =========================================================================
    private function stepCheckPaymentGates(): void
    {
        $this->info("Step 2: Check payment gates ...");

        try {
            $pendingEntries = DB::table('commission_ledger')
                ->where('status', 'pending')
                ->where('payment_gate_met', 0)
                ->where('entry_type', 'earned')
                ->select('id', 'policy_id', 'rule_id', 'cooling_period_end', 'kyc_compliant')
                ->get();

            $gatesMet = 0;
            $approved = 0;
            $today = Carbon::today()->toDateString();

            foreach ($pendingEntries as $entry) {
                try {
                    // Get min_payments from the rule's conditions
                    $minPayments = 0;
                    if ($entry->rule_id) {
                        $ruleConditions = DB::table('commission_rules')
                            ->where('id', $entry->rule_id)
                            ->value('conditions');
                        $conditions = json_decode($ruleConditions ?? '{}', true) ?: [];
                        $minPayments = (int) ($conditions['min_payments'] ?? 0);
                    }

                    // Count successful payments for this policy
                    $paymentCount = DB::table('payment_transactions')
                        ->where('policy_id', $entry->policy_id)
                        ->where('status', 'success')
                        ->count();

                    if ($paymentCount >= $minPayments) {
                        DB::table('commission_ledger')
                            ->where('id', $entry->id)
                            ->update(['payment_gate_met' => 1, 'updated_at' => now()]);
                        $gatesMet++;

                        // Check if commission can be approved
                        if ($entry->cooling_period_end <= $today && $entry->kyc_compliant == 1) {
                            DB::table('commission_ledger')
                                ->where('id', $entry->id)
                                ->update(['status' => 'approved', 'updated_at' => now()]);
                            $approved++;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("commission:calculate — Step 2 error for ledger {$entry->id}: " . $e->getMessage());
                }
            }

            // Also approve entries where payment_gate was already met but cooling period just passed
            $newlyApprovable = DB::table('commission_ledger')
                ->where('status', 'pending')
                ->where('payment_gate_met', 1)
                ->where('kyc_compliant', 1)
                ->where('cooling_period_end', '<=', $today)
                ->where('entry_type', 'earned')
                ->update(['status' => 'approved', 'updated_at' => now()]);

            $approved += $newlyApprovable;

            $this->stats['payment_gates_met'] = $gatesMet;
            $this->stats['commissions_approved'] = $approved;
            $this->info("  -> {$gatesMet} payment gates met, {$approved} commissions approved\n");
            Log::info("Step 2: {$gatesMet} payment gates met, {$approved} commissions approved");
        } catch (\Exception $e) {
            Log::error('commission:calculate — Step 2 failed: ' . $e->getMessage());
            $this->error("  Step 2 error: " . $e->getMessage());
        }
    }

    // =========================================================================
    //  Step 3 — Check KYC compliance updates
    // =========================================================================
    private function stepCheckKycCompliance(): void
    {
        $this->info("Step 3: Check KYC compliance updates ...");

        try {
            $nonCompliant = DB::table('commission_ledger as cl')
                ->join('policies as p', 'p.id', '=', 'cl.policy_id')
                ->where('cl.kyc_compliant', 0)
                ->where('cl.status', 'pending')
                ->where('cl.entry_type', 'earned')
                ->select('cl.id', 'p.customer_id')
                ->get();

            $updated = 0;

            foreach ($nonCompliant as $entry) {
                try {
                    $kycOk = DB::table('customer_kyc')
                        ->where('customer_id', $entry->customer_id)
                        ->where('compliance', 1)
                        ->exists();

                    if ($kycOk) {
                        DB::table('commission_ledger')
                            ->where('id', $entry->id)
                            ->update(['kyc_compliant' => 1, 'updated_at' => now()]);
                        $updated++;
                    }
                } catch (\Exception $e) {
                    Log::warning("commission:calculate — Step 3 error for ledger {$entry->id}: " . $e->getMessage());
                }
            }

            $this->stats['kyc_updates'] = $updated;
            $this->info("  -> {$updated} KYC flags updated out of {$nonCompliant->count()} checked\n");
            Log::info("Step 3: {$updated} KYC flags updated out of {$nonCompliant->count()} checked");
        } catch (\Exception $e) {
            Log::error('commission:calculate — Step 3 failed: ' . $e->getMessage());
            $this->error("  Step 3 error: " . $e->getMessage());
        }
    }

    // =========================================================================
    //  Step 4 — Claw-back for cancelled policies
    // =========================================================================
    private function stepClawbackCancelledPolicies(): void
    {
        $this->info("Step 4: Clawback for cancelled policies ...");

        try {
            $since = Carbon::now()->subHours(24);

            // Find policies cancelled in the last 24 h that have commission ledger entries
            $entries = DB::table('commission_ledger as cl')
                ->join('policies as p', 'p.id', '=', 'cl.policy_id')
                ->leftJoin('policyactivatecancelleddates as pacd', 'pacd.policyNumber', '=', 'p.policyNumber')
                ->where('p.status', 2) // cancelled
                ->whereIn('cl.status', ['pending', 'approved'])
                ->where('cl.entry_type', 'earned')
                ->where(function ($q) use ($since) {
                    $q->where('pacd.cancelled_date', '>=', $since->toDateString())
                      ->orWhere('p.updated_at', '>=', $since);
                })
                // Exclude policies that already have a clawback entry
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('commission_ledger as cb')
                        ->whereRaw('cb.policy_id = cl.policy_id')
                        ->where('cb.entry_type', 'clawback');
                })
                ->select(
                    'cl.id as ledger_id',
                    'cl.policy_id',
                    'cl.agent_id',
                    'cl.agency_id',
                    'cl.commission_amount',
                    'cl.rule_id',
                    'p.policyNumber',
                    'p.created_at as policy_created_at',
                    'pacd.cancelled_date',
                    'pacd.activated_date'
                )
                ->get();

            $clawbacks = 0;

            foreach ($entries as $entry) {
                try {
                    DB::transaction(function () use ($entry, &$clawbacks) {
                        // Determine how long the policy was active
                        $activatedDate = $entry->activated_date
                            ? Carbon::parse($entry->activated_date)
                            : Carbon::parse($entry->policy_created_at);
                        $cancelledDate = $entry->cancelled_date
                            ? Carbon::parse($entry->cancelled_date)
                            : Carbon::now();

                        $activeMonths = $activatedDate->diffInMonths($cancelledDate);

                        // Pro-rata clawback schedule
                        if ($activeMonths >= 12) {
                            $clawbackPct = 0;
                        } elseif ($activeMonths >= 9) {
                            $clawbackPct = 25;
                        } elseif ($activeMonths >= 6) {
                            $clawbackPct = 50;
                        } elseif ($activeMonths >= 3) {
                            $clawbackPct = 75;
                        } else {
                            $clawbackPct = 100;
                        }

                        if ($clawbackPct === 0) {
                            return; // no clawback needed
                        }

                        $clawbackAmount = round((float) $entry->commission_amount * $clawbackPct / 100, 2);

                        // Insert clawback entry (negative amount)
                        DB::table('commission_ledger')->insert([
                            'policy_id'          => $entry->policy_id,
                            'agent_id'           => $entry->agent_id,
                            'agency_id'          => $entry->agency_id,
                            'rule_id'            => $entry->rule_id,
                            'entry_type'         => 'clawback',
                            'commission_amount'  => -$clawbackAmount,
                            'premium_amount'     => null,
                            'commission_type'    => null,
                            'status'             => 'approved',
                            'qualifying_date'    => Carbon::today()->toDateString(),
                            'cooling_period_end' => null,
                            'payment_gate_met'   => 1,
                            'kyc_compliant'      => 1,
                            'notes'              => "Clawback {$clawbackPct}% — policy {$entry->policyNumber} active {$activeMonths} months",
                            'calculated_at'      => now(),
                            'created_at'         => now(),
                            'updated_at'         => now(),
                        ]);

                        // Mark original entry as cancelled
                        DB::table('commission_ledger')
                            ->where('id', $entry->ledger_id)
                            ->update(['status' => 'cancelled', 'updated_at' => now()]);

                        $clawbacks++;
                    });
                } catch (\Exception $e) {
                    Log::warning("commission:calculate — Step 4 error for ledger {$entry->ledger_id}: " . $e->getMessage());
                }
            }

            $this->stats['clawbacks_created'] = $clawbacks;
            $this->info("  -> {$entries->count()} cancelled policies checked, {$clawbacks} clawbacks created\n");
            Log::info("Step 4: {$entries->count()} cancelled policies checked, {$clawbacks} clawbacks created");
        } catch (\Exception $e) {
            Log::error('commission:calculate — Step 4 failed: ' . $e->getMessage());
            $this->error("  Step 4 error: " . $e->getMessage());
        }
    }

    // =========================================================================
    //  Step 5 — Check staged bonus targets
    // =========================================================================
    private function stepCheckBonusTargets(): void
    {
        $this->info("Step 5: Check bonus targets ...");

        try {
            $today = Carbon::today()->toDateString();

            $targets = DB::table('commission_targets')
                ->where('status', 1)
                ->where('effective_from', '<=', $today)
                ->where(function ($q) use ($today) {
                    $q->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $today);
                })
                ->get();

            $bonuses = 0;

            foreach ($targets as $target) {
                try {
                    $periodStart = $this->getTargetPeriodStart($target);
                    $periodEnd = $today;

                    // Determine which agents this target applies to
                    $agentIds = [];
                    if ($target->agent_id) {
                        $agentIds = [$target->agent_id];
                    } elseif ($target->agency_id) {
                        $agentIds = DB::table('agents')
                            ->where('agency_id', $target->agency_id)
                            ->pluck('id')
                            ->toArray();
                    } else {
                        // Global target — all active agents
                        $agentIds = DB::table('agents')
                            ->where('status', 1)
                            ->pluck('id')
                            ->toArray();
                    }

                    foreach ($agentIds as $agentId) {
                        try {
                            $achieved = $this->measureTargetAchievement($target, $agentId, $periodStart, $periodEnd);

                            if ($achieved < (float) $target->target_value) {
                                continue; // target not met
                            }

                            // Check if bonus already awarded for this target + agent + period
                            $alreadyAwarded = DB::table('commission_ledger')
                                ->where('target_id', $target->id)
                                ->where('agent_id', $agentId)
                                ->where('entry_type', 'bonus')
                                ->where('qualifying_date', '>=', $periodStart)
                                ->where('qualifying_date', '<=', $periodEnd)
                                ->exists();

                            if ($alreadyAwarded) {
                                continue;
                            }

                            // Calculate bonus amount
                            $bonusAmount = 0;
                            if ($target->bonus_type === 'amount') {
                                $bonusAmount = (float) $target->bonus_value;
                            } elseif ($target->bonus_type === 'percentage') {
                                // Percentage of total premiums earned in the period
                                $totalPremiums = DB::table('commission_ledger')
                                    ->where('agent_id', $agentId)
                                    ->where('entry_type', 'earned')
                                    ->whereBetween('qualifying_date', [$periodStart, $periodEnd])
                                    ->sum('premium_amount');
                                $bonusAmount = round((float) $totalPremiums * (float) $target->bonus_value / 100, 2);
                            }

                            if ($bonusAmount <= 0) {
                                continue;
                            }

                            $agencyId = DB::table('agents')
                                ->where('id', $agentId)
                                ->value('agency_id');

                            DB::table('commission_ledger')->insert([
                                'policy_id'          => 0,
                                'agent_id'           => $agentId,
                                'agency_id'          => $agencyId,
                                'rule_id'            => null,
                                'target_id'          => $target->id,
                                'entry_type'         => 'bonus',
                                'commission_amount'  => $bonusAmount,
                                'premium_amount'     => null,
                                'commission_type'    => $target->bonus_type,
                                'status'             => 'pending',
                                'qualifying_date'    => $today,
                                'cooling_period_end' => null,
                                'payment_gate_met'   => 1,
                                'kyc_compliant'      => 1,
                                'notes'              => "Bonus for target '{$target->name}' — achieved {$achieved} / {$target->target_value}",
                                'calculated_at'      => now(),
                                'created_at'         => now(),
                                'updated_at'         => now(),
                            ]);

                            $bonuses++;
                        } catch (\Exception $e) {
                            Log::warning("commission:calculate — Step 5 error for target {$target->id}, agent {$agentId}: " . $e->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("commission:calculate — Step 5 error for target {$target->id}: " . $e->getMessage());
                }
            }

            $this->stats['bonuses_awarded'] = $bonuses;
            $this->info("  -> {$targets->count()} targets evaluated, {$bonuses} bonuses awarded\n");
            Log::info("Step 5: {$targets->count()} targets evaluated, {$bonuses} bonuses awarded");
        } catch (\Exception $e) {
            Log::error('commission:calculate — Step 5 failed: ' . $e->getMessage());
            $this->error("  Step 5 error: " . $e->getMessage());
        }
    }

    /**
     * Measure an agent's achievement against a target within a period.
     */
    private function measureTargetAchievement(object $target, int $agentId, string $periodStart, string $periodEnd): float
    {
        $query = DB::table('policies')
            ->where('agent_id', $agentId)
            ->where('status', 1) // active
            ->whereBetween('created_at', [$periodStart, $periodEnd . ' 23:59:59']);

        if ($target->product_id) {
            $query->where('product_id', $target->product_id);
        }

        if ($target->target_type === 'policy_count') {
            return (float) $query->count();
        }

        // premium_amount
        return (float) $query->sum('premium');
    }

    /**
     * Get the start date for a target's current period.
     */
    private function getTargetPeriodStart(object $target): string
    {
        return match ($target->period_type) {
            'monthly'   => Carbon::now()->startOfMonth()->toDateString(),
            'quarterly' => Carbon::now()->firstOfQuarter()->toDateString(),
            'bimonthly' => Carbon::now()->startOfMonth()->subMonths((Carbon::now()->month - 1) % 2)->toDateString(),
            'annual'    => Carbon::now()->startOfYear()->toDateString(),
            'custom'    => Carbon::now()->subDays($target->period_days ?? 30)->toDateString(),
            default     => Carbon::now()->startOfMonth()->toDateString(),
        };
    }

    // =========================================================================
    //  Step 6 — Fraud detection
    // =========================================================================
    private function stepFraudDetection(): void
    {
        $this->info("Step 6: Fraud detection ...");

        $alertsCreated = 0;

        try {
            $alertsCreated += $this->detectChurning();
        } catch (\Exception $e) {
            Log::error('commission:calculate — Fraud 6a (churning) failed: ' . $e->getMessage());
            $this->error("  6a churning error: " . $e->getMessage());
        }

        try {
            $alertsCreated += $this->detectExcessiveCancellation();
        } catch (\Exception $e) {
            Log::error('commission:calculate — Fraud 6b (excessive cancellation) failed: ' . $e->getMessage());
            $this->error("  6b excessive cancellation error: " . $e->getMessage());
        }

        try {
            $alertsCreated += $this->detectReactivationFraud();
        } catch (\Exception $e) {
            Log::error('commission:calculate — Fraud 6c (reactivation) failed: ' . $e->getMessage());
            $this->error("  6c reactivation fraud error: " . $e->getMessage());
        }

        try {
            $alertsCreated += $this->detectSameDayMultiple();
        } catch (\Exception $e) {
            Log::error('commission:calculate — Fraud 6d (same-day multiple) failed: ' . $e->getMessage());
            $this->error("  6d same-day multiple error: " . $e->getMessage());
        }

        try {
            $alertsCreated += $this->detectNtu();
        } catch (\Exception $e) {
            Log::error('commission:calculate — Fraud 6e (NTU) failed: ' . $e->getMessage());
            $this->error("  6e NTU error: " . $e->getMessage());
        }

        $this->stats['fraud_alerts_created'] = $alertsCreated;
        $this->info("  -> {$alertsCreated} fraud alerts created\n");
        Log::info("Step 6: {$alertsCreated} fraud alerts created");
    }

    /**
     * 6a. Churning: Same customer cancelled then reactivated within 60 days.
     */
    private function detectChurning(): int
    {
        $rows = DB::select("
            SELECT p_new.id as new_policy_id, p_new.agent_id, p_new.customer_id,
                   p_old.id as old_policy_id, p_old.policyNumber as old_policy_number,
                   p_new.policyNumber as new_policy_number,
                   pacd.cancelled_date
            FROM policies p_new
            JOIN policies p_old ON p_old.customer_id = p_new.customer_id
                AND p_old.product_id = p_new.product_id
                AND p_old.id != p_new.id
                AND p_old.status = 2
            JOIN policyactivatecancelleddates pacd ON pacd.policyNumber = p_old.policyNumber
            WHERE p_new.status = 1
              AND p_new.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
              AND pacd.cancelled_date >= DATE_SUB(NOW(), INTERVAL 60 DAY)
              AND p_new.created_at > pacd.cancelled_date
              AND p_new.agent_id IS NOT NULL
              AND p_new.agent_id > 0
            LIMIT 200
        ");

        $created = 0;
        foreach ($rows as $row) {
            if ($this->fraudAlertExists($row->new_policy_id, $row->agent_id, 'churning')) {
                continue;
            }

            DB::table('commission_fraud_alerts')->insert([
                'policy_id'          => $row->new_policy_id,
                'agent_id'           => $row->agent_id,
                'alert_type'         => 'churning',
                'severity'           => 'high',
                'details'            => json_encode([
                    'customer_id'       => $row->customer_id,
                    'old_policy_id'     => $row->old_policy_id,
                    'old_policy_number' => $row->old_policy_number,
                    'new_policy_number' => $row->new_policy_number,
                    'cancelled_date'    => $row->cancelled_date,
                ]),
                'related_policy_ids' => json_encode([$row->old_policy_id, $row->new_policy_id]),
                'status'             => 'open',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $created++;
        }

        $this->info("  6a. Churning: {$created} alerts");
        return $created;
    }

    /**
     * 6b. Excessive cancellation: Agents with >30% cancel rate in last 90 days.
     */
    private function detectExcessiveCancellation(): int
    {
        $rows = DB::select("
            SELECT agent_id,
                   COUNT(*) as total_policies,
                   SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as cancelled_count,
                   ROUND(SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as cancel_rate
            FROM policies
            WHERE agent_id IS NOT NULL
              AND agent_id > 0
              AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY agent_id
            HAVING total_policies >= 5
               AND cancel_rate > 30
            LIMIT 200
        ");

        $created = 0;
        foreach ($rows as $row) {
            if ($this->fraudAlertExists(null, $row->agent_id, 'excessive_cancellation')) {
                continue;
            }

            DB::table('commission_fraud_alerts')->insert([
                'policy_id'          => null,
                'agent_id'           => $row->agent_id,
                'alert_type'         => 'excessive_cancellation',
                'severity'           => $row->cancel_rate > 50 ? 'critical' : 'high',
                'details'            => json_encode([
                    'total_policies'   => (int) $row->total_policies,
                    'cancelled_count'  => (int) $row->cancelled_count,
                    'cancel_rate'      => (float) $row->cancel_rate,
                    'period'           => '90 days',
                ]),
                'related_policy_ids' => null,
                'status'             => 'open',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $created++;
        }

        $this->info("  6b. Excessive cancellation: {$created} alerts");
        return $created;
    }

    /**
     * 6c. Reactivation fraud: Same customer + same product within 90 days.
     */
    private function detectReactivationFraud(): int
    {
        $rows = DB::select("
            SELECT p1.id as policy_id, p1.agent_id, p1.customer_id, p1.product_id,
                   p1.policyNumber, p2.id as prior_policy_id, p2.policyNumber as prior_policy_number
            FROM policies p1
            JOIN policies p2 ON p2.customer_id = p1.customer_id
                AND p2.product_id = p1.product_id
                AND p2.id != p1.id
                AND p2.created_at >= DATE_SUB(p1.created_at, INTERVAL 90 DAY)
                AND p2.created_at < p1.created_at
            WHERE p1.status = 1
              AND p1.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              AND p1.agent_id IS NOT NULL
              AND p1.agent_id > 0
            LIMIT 200
        ");

        $created = 0;
        foreach ($rows as $row) {
            if ($this->fraudAlertExists($row->policy_id, $row->agent_id, 'reactivation_fraud')) {
                continue;
            }

            DB::table('commission_fraud_alerts')->insert([
                'policy_id'          => $row->policy_id,
                'agent_id'           => $row->agent_id,
                'alert_type'         => 'reactivation_fraud',
                'severity'           => 'medium',
                'details'            => json_encode([
                    'customer_id'         => $row->customer_id,
                    'product_id'          => $row->product_id,
                    'prior_policy_id'     => $row->prior_policy_id,
                    'prior_policy_number' => $row->prior_policy_number,
                    'new_policy_number'   => $row->policyNumber,
                ]),
                'related_policy_ids' => json_encode([$row->prior_policy_id, $row->policy_id]),
                'status'             => 'open',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $created++;
        }

        $this->info("  6c. Reactivation fraud: {$created} alerts");
        return $created;
    }

    /**
     * 6d. Same-day multiple: Multiple policies for same customer on the same day.
     */
    private function detectSameDayMultiple(): int
    {
        $rows = DB::select("
            SELECT customer_id, agent_id, DATE(created_at) as policy_date,
                   COUNT(*) as policy_count,
                   GROUP_CONCAT(id) as policy_ids,
                   GROUP_CONCAT(policyNumber) as policy_numbers
            FROM policies
            WHERE agent_id IS NOT NULL
              AND agent_id > 0
              AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              AND status IN (1, 2)
            GROUP BY customer_id, agent_id, DATE(created_at)
            HAVING policy_count > 1
            LIMIT 200
        ");

        $created = 0;
        foreach ($rows as $row) {
            $policyIdList = array_map('intval', explode(',', $row->policy_ids));
            $firstPolicyId = $policyIdList[0] ?? null;

            if ($this->fraudAlertExists($firstPolicyId, $row->agent_id, 'same_day_multiple')) {
                continue;
            }

            DB::table('commission_fraud_alerts')->insert([
                'policy_id'          => $firstPolicyId,
                'agent_id'           => $row->agent_id,
                'alert_type'         => 'same_day_multiple',
                'severity'           => $row->policy_count > 3 ? 'high' : 'medium',
                'details'            => json_encode([
                    'customer_id'     => $row->customer_id,
                    'policy_date'     => $row->policy_date,
                    'policy_count'    => (int) $row->policy_count,
                    'policy_numbers'  => $row->policy_numbers,
                ]),
                'related_policy_ids' => json_encode($policyIdList),
                'status'             => 'open',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $created++;
        }

        $this->info("  6d. Same-day multiple: {$created} alerts");
        return $created;
    }

    /**
     * 6e. NTU (Not Taken Up): Policies issued 30+ days ago with zero payments.
     */
    private function detectNtu(): int
    {
        $rows = DB::select("
            SELECT p.id as policy_id, p.agent_id, p.customer_id,
                   p.policyNumber, p.premium, p.created_at as policy_created
            FROM policies p
            LEFT JOIN payment_transactions pt ON pt.policy_id = p.id AND pt.status = 'success'
            WHERE p.status = 1
              AND p.agent_id IS NOT NULL
              AND p.agent_id > 0
              AND p.created_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND p.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
              AND pt.id IS NULL
            LIMIT 200
        ");

        $created = 0;
        foreach ($rows as $row) {
            if ($this->fraudAlertExists($row->policy_id, $row->agent_id, 'ntu')) {
                continue;
            }

            DB::table('commission_fraud_alerts')->insert([
                'policy_id'          => $row->policy_id,
                'agent_id'           => $row->agent_id,
                'alert_type'         => 'ntu',
                'severity'           => 'medium',
                'details'            => json_encode([
                    'customer_id'    => $row->customer_id,
                    'policy_number'  => $row->policyNumber,
                    'premium'        => (float) $row->premium,
                    'issued_date'    => $row->policy_created,
                    'days_since'     => Carbon::parse($row->policy_created)->diffInDays(Carbon::now()),
                ]),
                'related_policy_ids' => json_encode([$row->policy_id]),
                'status'             => 'open',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $created++;
        }

        $this->info("  6e. NTU (zero payments): {$created} alerts");
        return $created;
    }

    /**
     * Check if a fraud alert already exists for the given policy + agent + type.
     * Prevents duplicate alerts on repeated runs (idempotency).
     */
    private function fraudAlertExists(?int $policyId, ?int $agentId, string $alertType): bool
    {
        $query = DB::table('commission_fraud_alerts')
            ->where('alert_type', $alertType)
            ->whereIn('status', ['open', 'reviewing']);

        if ($policyId) {
            $query->where('policy_id', $policyId);
        }

        if ($agentId) {
            $query->where('agent_id', $agentId);
        }

        return $query->exists();
    }
}
