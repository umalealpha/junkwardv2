<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckReinsuranceSetup extends Command
{
    protected $signature = 'reinsurance:check-setup {policy_id}';
    protected $description = 'Diagnostic check for reinsurance calculation issues';

    public function handle()
    {
        $policyId = $this->argument('policy_id');
        $policy = DB::table('policies')->where('id', $policyId)->first();
        
        if (!$policy) {
            $this->error("Policy {$policyId} not found");
            return 1;
        }

        $this->info("Policy: {$policy->policy_number} | Product: {$policy->product_id}");

        // Check 1: Treaties (treaties are shared across products via formulas)
        $treatiesQuery = DB::table('reinsurance_treaty as rt')
            ->join('reinsurance_treaty_details as td', 'rt.id', '=', 'td.treaty_id')
            ->join('reinsurance_formula as fm', 'td.formula_attached', '=', 'fm.id')
            ->where('fm.product_id', $policy->product_id)
            ->distinct('rt.id');
        // Only filter by deleted_at if column exists
        if (Schema::hasColumn('reinsurance_treaty', 'deleted_at')) {
            $treatiesQuery->whereNull('rt.deleted_at');
        }
        $treaties = $treatiesQuery->count();
        $this->line("✓ Reinsurance Treaties (for this product): {$treaties}");
        
        // Check 2: Formulas
        $formulasQuery = DB::table('reinsurance_formula')
            ->where('product_id', $policy->product_id);
        // Only filter by deleted_at if column exists
        if (Schema::hasColumn('reinsurance_formula', 'deleted_at')) {
            $formulasQuery->whereNull('deleted_at');
        }
        $formulas = $formulasQuery->count();
        $this->line("✓ Reinsurance Formulas: {$formulas}");
        
        // Check 3: Coverage groups
        $groups = DB::table('reinsurance_group')
            ->count();
        $this->line("✓ Reinsurance Coverage Groups: {$groups}");
        
        // Check 4: Active coverages on policy
        $action = DB::table('policy_actions')
            ->where('policy_id', $policyId)
            ->orderByDesc('id')
            ->first();
            
        if (!$action) {
            $this->error("No policy action found");
            return 1;
        }
        
        $coverages = DB::table('policy_coverages')
            ->where('policy_id', $policyId)
            ->where('action_id', $action->id)
            ->whereNull('deleted_at')
            ->count();
        $this->line("✓ Active Coverage Rows: {$coverages}");
        
        // Check 5: Policy term
        $term = DB::table('policy_term')
            ->where('policy_id', $policyId)
            ->first();
        if ($term) {
            $this->line("✓ Policy Term: {$term->term_start_date} to {$term->term_end_date}");
        } else {
            $this->warn("⚠ No policy term found");
        }
        
        if ($treaties === 0 || $formulas === 0) {
            $this->warn("\n⚠ Reinsurance setup incomplete — check Reinsurance admin section");
        }
        
        return 0;
    }
}
