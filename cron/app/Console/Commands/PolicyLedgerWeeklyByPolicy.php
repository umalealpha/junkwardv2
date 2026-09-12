<?php

namespace AlphaDirect\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * On-demand, single-policy version of policyledger:cron (PolicyLedger.php) —
 * the weekly all-policies sweep. Mirrors PolicyLedgerByPolicy's relationship
 * to PolicyLedgerDaily: reuses PolicyLedger's per-policy invoice generation
 * loop untouched, only overriding which policy feeds into it.
 */
class PolicyLedgerWeeklyByPolicy extends PolicyLedger
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'PolicyLedgerWeeklyByPolicy:cron {policy : Policy ID or policy number, e.g. 12345 or MIS2026214867}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Run policyledger:cron's invoice-generation sweep for a single policy by ID or policy number";

    protected ?object $targetPolicy = null;

    public function handle()
    {
        $result = parent::handle();

        if ($result !== 0) {
            $this->error("PolicyLedgerWeeklyByPolicy failed for '{$this->argument('policy')}' — see the application log for details.");
            return $result;
        }

        if ($this->targetPolicy !== null) {
            $this->info("PolicyLedgerWeeklyByPolicy finished for {$this->targetPolicy->policyNumber} (id={$this->targetPolicy->id})");
        }

        return $result;
    }

    protected function cronName(): string
    {
        return 'PolicyLedgerWeeklyByPolicy:cron';
    }

    protected function selectPolicies(Carbon $today)
    {
        $identifier = trim((string) $this->argument('policy'));

        $columns = ['id', 'customer_id', 'product_id', 'plan_id', 'premium_freq',
            'created_at', 'updated_at', 'first_premium', 'premium', 'vat',
            'vat_percent', 'policyNumber', 'policyActivatedDate', 'is_sys_act_generated',
            'billingStartDate', 'ori_billingStartDate', 'status'];

        $policy = ctype_digit($identifier)
            ? DB::table('policies')->select($columns)->where('id', $identifier)->first()
            : DB::table('policies')->select($columns)->where('policyNumber', $identifier)->first();

        if ($policy === null) {
            throw new \RuntimeException("PolicyLedgerWeeklyByPolicy: no policy found for '{$identifier}'");
        }

        if (in_array((int) $policy->product_id, [7, 8], true)) {
            throw new \RuntimeException("PolicyLedgerWeeklyByPolicy: policy {$policy->policyNumber} (product_id={$policy->product_id}) is a DOM/COM product and isn't supported by this command");
        }

        $this->targetPolicy = $policy;

        $this->info("Processing ledger for policy {$policy->policyNumber} (id={$policy->id})");
        Log::info("PolicyLedgerWeeklyByPolicy: resolved '{$identifier}' to policy {$policy->policyNumber} (id={$policy->id})");

        return collect([$policy])->chunk(1000);
    }
}
