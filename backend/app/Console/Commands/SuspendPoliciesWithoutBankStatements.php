<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\DeduplicationCheck;
use Illuminate\Support\Facades\Log;

class SuspendPoliciesWithoutBankStatements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policies:suspend-without-bank-statements {--dry-run : Show what would be suspended without actually suspending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Suspend policies that have not uploaded bank statements within 90 days';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('Starting policy suspension check...');
        
        // Find all deduplication checks that are due for suspension
        $dueForSuspension = DeduplicationCheck::dueForSuspension()
            ->with(['customer', 'policy'])
            ->get();
        
        if ($dueForSuspension->isEmpty()) {
            $this->info('No policies found that are due for suspension.');
            return 0;
        }
        
        $this->info("Found {$dueForSuspension->count()} policies due for suspension:");
        
        $suspendedCount = 0;
        
        foreach ($dueForSuspension as $check) {
            $this->line("Policy ID: {$check->policy_id} - Customer: {$check->customer->first_name} {$check->customer->last_name}");
            $this->line("  - Policy Created: {$check->policy_created_at}");
            $this->line("  - Suspension Due: {$check->suspension_due_date}");
            $this->line("  - Document Status: {$check->document_upload_status}");
            
            if (!$isDryRun) {
                $check->suspendPolicy('Bank statement not uploaded within 90 days - Automated suspension');
                $suspendedCount++;
                
                // Log the suspension
                Log::info("Policy {$check->policy_id} suspended due to missing bank statement", [
                    'customer_id' => $check->customer_id,
                    'policy_id' => $check->policy_id,
                    'suspension_date' => now(),
                    'reason' => 'Bank statement not uploaded within 90 days'
                ]);
            }
            
            $this->line('');
        }
        
        if ($isDryRun) {
            $this->warn('DRY RUN: No policies were actually suspended.');
            $this->info("Would suspend {$dueForSuspension->count()} policies.");
        } else {
            $this->info("Successfully suspended {$suspendedCount} policies.");
        }
        
        return 0;
    }
}
