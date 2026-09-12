<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\DeduplicationChecks;
use AlphaDirect\Customer;
use AlphaDirect\Services\BankStatementService;
use AlphaDirect\Services\DeduplicationStatsService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GenerateBankStatementLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bankstatement:generate-links 
                            {--dry-run : Show what would be processed without actually processing}
                            {--customer-id= : Process specific customer ID}
                            {--limit=100 : Limit number of customers to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate bank statement upload links for customers in deduplication_checks table';

    protected $bankStatementService;
    protected $statsService;

    public function __construct(BankStatementService $bankStatementService, DeduplicationStatsService $statsService)
    {
        parent::__construct();
        $this->bankStatementService = $bankStatementService;
        $this->statsService = $statsService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $customerId = $this->option('customer-id');
        $limit = (int) $this->option('limit');
        
        $this->info('Starting bank statement link generation...');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No links will be generated');
        }
        
        try {
            // Get customers that need bank statement links
            $query = DeduplicationChecks::with(['customer'])
                ->where('status', 'active')
                ->whereNull('unique_access_token')
                ->whereNull('bank_statement_upload_url');
            
            if ($customerId) {
                $query->where('customer_id', $customerId);
            }
            
            $deduplicationChecks = $query->limit($limit)->get();
            
            $this->info("Found {$deduplicationChecks->count()} customers needing bank statement links");
            
            if ($deduplicationChecks->isEmpty()) {
                $this->info('No customers found that need bank statement links');
                return 0;
            }
            
            $processedCount = 0;
            $successCount = 0;
            $errorCount = 0;
            $skippedCount = 0;
            
            foreach ($deduplicationChecks as $check) {
                $this->line("Processing Customer ID: {$check->customer_id}");
                
                // Check if customer exists and has required data
                if (!$check->customer) {
                    $this->line("  - Skipping: Customer not found");
                    $skippedCount++;
                    continue;
                }
                
                $customer = $check->customer;
                
                // Check if customer has required contact information
                if (!$customer->cellphone && !$customer->email) {
                    $this->line("  - Skipping: No contact information (phone/email)");
                    $skippedCount++;
                    continue;
                }
                
                // Check if customer already has a valid token
                if ($check->unique_access_token && $check->link_expires_at && $check->link_expires_at->isFuture()) {
                    $this->line("  - Skipping: Already has valid token");
                    $skippedCount++;
                    continue;
                }
                
                if (!$isDryRun) {
                    try {
                        // Generate unique token and update the check
                        $token = $this->generateUniqueToken();
                        $expiresAt = Carbon::now()->addDays(7); // 7 days expiry
                        
                        $check->update([
                            'unique_access_token' => $token,
                            'link_expires_at' => $expiresAt,
                            'status' => 'active'
                        ]);
                        
                        // Log the activity
                        $this->logActivity($check, 'link_generated', 'Bank statement upload link generated via cron');
                        
                        $this->line("  - Generated token: {$token}");
                        $this->line("  - Expires at: {$expiresAt->format('Y-m-d H:i:s')}");
                        
                        $successCount++;
                        
                    } catch (\Exception $e) {
                        $this->error("  - Error generating link: " . $e->getMessage());
                        Log::error("Bank statement link generation error for customer {$customer->id}: " . $e->getMessage());
                        $errorCount++;
                    }
                } else {
                    $this->line("  - Would generate token for customer");
                    $successCount++;
                }
                
                $processedCount++;
            }
            
            // Generate summary statistics
            $this->displaySummary($processedCount, $successCount, $errorCount, $skippedCount, $isDryRun);
            
            // Log the cron execution
            $this->logCronExecution($processedCount, $successCount, $errorCount, $skippedCount);
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Fatal error: ' . $e->getMessage());
            Log::error('Bank statement link generation cron fatal error: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Generate unique token for bank statement upload
     */
    private function generateUniqueToken(): string
    {
        do {
            $token = 'BST_' . strtoupper(bin2hex(random_bytes(8)));
        } while (DeduplicationChecks::where('unique_access_token', $token)->exists());
        
        return $token;
    }
    
    /**
     * Log activity for a check
     */
    private function logActivity(DeduplicationChecks $check, string $action, string $description): void
    {
        // Update access logs
        $accessLogs = $check->access_logs ?? [];
        $accessLogs[] = [
            'action' => $action,
            'description' => $description,
            'timestamp' => Carbon::now()->toISOString(),
            'source' => 'cron_command'
        ];
        
        $check->update(['access_logs' => $accessLogs]);
        
        Log::info("DeduplicationCheck #{$check->id}: {$action} - {$description}");
    }
    
    /**
     * Log cron execution
     */
    private function logCronExecution(int $processed, int $success, int $error, int $skipped): void
    {
        $logData = [
            'command' => 'bankstatement:generate-links',
            'executed_at' => Carbon::now()->toISOString(),
            'processed' => $processed,
            'success' => $success,
            'error' => $error,
            'skipped' => $skipped
        ];
        
        Log::info('Bank statement link generation cron executed', $logData);
    }
    
    /**
     * Display summary of processing
     */
    private function displaySummary(int $processed, int $success, int $error, int $skipped, bool $isDryRun): void
    {
        $this->info("\n=== PROCESSING SUMMARY ===");
        $this->info("Total customers processed: {$processed}");
        $this->info("Successfully processed: {$success}");
        $this->info("Errors: {$error}");
        $this->info("Skipped: {$skipped}");
        
        if ($isDryRun) {
            $this->warn('DRY RUN COMPLETED - No links were actually generated');
        } else {
            $this->info('Link generation completed successfully');
        }
        
        // Display current statistics
        $stats = $this->statsService->getDashboardStats();
        $this->info("\n=== CURRENT STATISTICS ===");
        $this->info("Total checks: {$stats['total_checks']}");
        $this->info("Active checks: {$stats['active_checks']}");
        $this->info("Completed checks: {$stats['completed_checks']}");
        $this->info("Uploaded documents: {$stats['uploaded_documents']}");
        $this->info("Verified documents: {$stats['verified_documents']}");
        $this->info("Completion rate: {$stats['completion_rate']}%");
    }
}