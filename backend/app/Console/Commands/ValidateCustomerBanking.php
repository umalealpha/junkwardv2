<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Banks;
use Illuminate\Console\Command;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Customer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ValidateCustomerBanking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'banking:validate {--dry-run : Show what would be processed without actually processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate customer banking data: one customer should have only one account number, one account number should be assigned to only one customer';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Increase memory limit for large datasets
        ini_set('memory_limit', '2G');
        
        // Enable garbage collection
        if (function_exists('gc_enable')) {
            gc_enable();
        }
        
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No data will be stored');
        }
        
        // Get total count of banking records for reporting
        $totalRecords = CustomerBanking::whereNotNull('accountNumber')
            ->where('accountNumber', '!=', '')
            ->whereNotNull('customer_id')
            ->where('customer_id', '!=', '')
            ->count();
        
        // Use file-based storage for large datasets to avoid memory issues
        $violations = [
            'multiple_accounts_per_customer' => [],
            'multiple_customers_per_account' => [],
            'valid_records' => []
        ];
        
        // Create temporary files for large datasets
        $tempFiles = [
            'multiple_accounts_per_customer' => tempnam(sys_get_temp_dir(), 'banking_violations_1_'),
            'multiple_customers_per_account' => tempnam(sys_get_temp_dir(), 'banking_violations_2_'),
            'valid_records' => tempnam(sys_get_temp_dir(), 'banking_valid_')
        ];
        
        // Initialize CSV headers for temp files
        foreach ($tempFiles as $type => $file) {
            $headers = $this->getCsvHeaders($type);
            file_put_contents($file, implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $headers)) . "\n");
        }
        
        // Check 1: One customer should not have more than one account number
        // Use database query to find customers with multiple accounts
        $multipleAccountCustomers = DB::table('customer_banking')
            ->select('customer_id', DB::raw('COUNT(DISTINCT accountNumber) as account_count'))
            ->whereNotNull('accountNumber')
            ->where('accountNumber', '!=', '')
            ->whereNotNull('customer_id')
            ->where('customer_id', '!=', '')
            ->groupBy('customer_id')
            ->having('account_count', '>', 1)
            ->get()
            ->keyBy('customer_id');
        
        // Process customers in chunks for better performance
        $customerIds = $multipleAccountCustomers->keys()->chunk(100);
        foreach ($customerIds as $customerIdChunk) {
            // Get customer details for this chunk
            $customers = Customer::whereIn('id', $customerIdChunk)->get()->keyBy('id');
            
            foreach ($customerIdChunk as $customerId) {
                $data = $multipleAccountCustomers[$customerId];
                $accountCount = $data->account_count;
                
                $customer = $customers->get($customerId);
                
                // Get all account numbers for this customer
                $accountNumbers = DB::table('customer_banking')
                    ->where('customer_id', $customerId)
                    ->whereNotNull('accountNumber')
                    ->where('accountNumber', '!=', '')
                    ->pluck('accountNumber')
                    ->unique()
                    ->values();
                
                // Get all records for this customer in chunks and write directly to temp file
                CustomerBanking::with(['Policy'])
                    ->where('customer_id', $customerId)
                    ->whereNotNull('accountNumber')
                    ->where('accountNumber', '!=', '')
                    ->chunk(100, function ($customerRecords) use ($customer, $customerId, $accountNumbers, $accountCount, $tempFiles) {
                        $csvData = [];
                        foreach ($customerRecords as $record) {
                            // if($record->bankName != null ){
                            //     dd($record);
                            // }
                         
                              $bankName = Banks::where('bank_number', $record->bankName)->first();
                              $branches = \AlphaDirect\BankBranches::where('branch_id', $record->branchCode)->first();
                            $csvData[] = [
                                'customer_id' => $customerId,
                                'customer_name' => $customer ? "{$customer->firstName} {$customer->lastName}" : "Unknown Customer",
                                'customer_email' => $customer ? $customer->email : null,
                                'customer_cellphone' => $customer ? $customer->cellphone : null,
                                'account_number' => $record->accountNumber,
                                'bank_name' => $bankName ? $bankName->bank_name : $record->bankName,
                                'bank_branch' => $branches ? $branches->name : $record->branchCode,
                                'billing' => $record->billing,
                                'policy_id' => $record->policy_id,
                                'policy_number' => $record->Policy ? $record->Policy->policyNumber : null,
                                'violation_type' => 'Multiple account numbers per customer',
                                'all_account_numbers' => $accountNumbers->implode(', '),
                                'account_count' => $accountCount,
                                'created_at' => $record->created_at,
                                'updated_at' => $record->updated_at,
                                'processed_at' => now()->toISOString()
                            ];
                        }
                        
                        // Write to temp file
                        $this->writeToTempFile($tempFiles['multiple_accounts_per_customer'], $csvData);
                        
                        // Clear memory
                        unset($csvData);
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    });
            }
        }
        
        // Check 2: One account number should not be assigned to multiple customers
        // Use database query to find account numbers with multiple customers
        $multipleCustomerAccounts = DB::table('customer_banking')
            ->select('accountNumber', DB::raw('COUNT(DISTINCT customer_id) as customer_count'))
            ->whereNotNull('accountNumber')
            ->where('accountNumber', '!=', '')
            ->whereNotNull('customer_id')
            ->where('customer_id', '!=', '')
            ->groupBy('accountNumber')
            ->having('customer_count', '>', 1)
            ->get()
            ->keyBy('accountNumber');
        
        // Process account numbers in chunks for better performance
        $accountNumbers = $multipleCustomerAccounts->keys()->chunk(100);
        foreach ($accountNumbers as $accountNumberChunk) {
            foreach ($accountNumberChunk as $accountNumber) {
                $data = $multipleCustomerAccounts[$accountNumber];
                $customerCount = $data->customer_count;
                
                // Get all customer IDs for this account number
                $customerIds = DB::table('customer_banking')
                    ->where('accountNumber', $accountNumber)
                    ->whereNotNull('customer_id')
                    ->where('customer_id', '!=', '')
                    ->pluck('customer_id')
                    ->unique();
                
                // Get customer details for this chunk
                $customers = Customer::whereIn('id', $customerIds)->get()->keyBy('id');
                
                // Get all records for this account number in chunks and write directly to temp file
                CustomerBanking::with(['Policy'])
                    ->where('accountNumber', $accountNumber)
                    ->whereNotNull('customer_id')
                    ->where('customer_id', '!=', '')
                    ->chunk(100, function ($accountRecords) use ($accountNumber, $customerCount, $customerIds, $customers, $tempFiles) {
                        $csvData = [];
                        foreach ($accountRecords as $record) {
                            $customerId = $record->customer_id;
                            $customer = $customers->get($customerId);
                            $bankName = Banks::where('bank_number', $record->bankName)->first();
                            $branches = \AlphaDirect\BankBranches::where('branch_id', $record->branchCode)->first();
                            $csvData[] = [
                                'customer_id' => $record->customer_id,
                                'customer_name' => $customer ? "{$customer->firstName} {$customer->lastName}" : "Unknown Customer",
                                'customer_email' => $customer ? $customer->email : null,
                                'customer_cellphone' => $customer ? $customer->cellphone : null,
                                'account_number' => $accountNumber,
                                'bank_name' => $bankName ? $bankName->bank_name : $record->bankName,
                                'bank_branch' => $branches ? $branches->name : $record->branchCode,
                              
                                'billing' => $record->billing,
                                'policy_id' => $record->policy_id,
                                'policy_number' => $record->Policy ? $record->Policy->policyNumber : null,
                                'violation_type' => 'Multiple customers per account number',
                                'all_customer_ids' => $customerIds->implode(', '),
                                'customer_count' => $customerCount,
                                'created_at' => $record->created_at,
                                'updated_at' => $record->updated_at,
                                'processed_at' => now()->toISOString()
                            ];
                        }
                        
                        // Write to temp file
                        $this->writeToTempFile($tempFiles['multiple_customers_per_account'], $csvData);
                        
                        // Clear memory
                        unset($csvData);
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    });
            }
        }
        
        // Identify valid records (customers with single account numbers and accounts assigned to single customers)
        $this->info("\n=== IDENTIFYING VALID RECORDS ===");
        
        // Get valid customers (those with only one account)
        $validCustomerIds = DB::table('customer_banking')
            ->select('customer_id')
            ->whereNotNull('accountNumber')
            ->where('accountNumber', '!=', '')
            ->whereNotNull('customer_id')
            ->where('customer_id', '!=', '')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(DISTINCT accountNumber) = 1')
            ->pluck('customer_id');
        
        // Get valid account numbers (those assigned to only one customer)
        $validAccountNumbers = DB::table('customer_banking')
            ->select('accountNumber')
            ->whereNotNull('accountNumber')
            ->where('accountNumber', '!=', '')
            ->whereNotNull('customer_id')
            ->where('customer_id', '!=', '')
            ->groupBy('accountNumber')
            ->havingRaw('COUNT(DISTINCT customer_id) = 1')
            ->pluck('accountNumber');
        
        // Process valid records directly to temp file without accumulating in memory
        $validRecordsCount = 0;
        CustomerBanking::with(['Policy'])
            ->whereIn('customer_id', $validCustomerIds)
            ->whereIn('accountNumber', $validAccountNumbers)
            ->whereNotNull('accountNumber')
            ->where('accountNumber', '!=', '')
            ->whereNotNull('customer_id')
            ->where('customer_id', '!=', '')
            ->chunk(100, function ($records) use ($tempFiles, &$validRecordsCount) {
                // Get all customer IDs for this chunk
                $customerIds = $records->pluck('customer_id')->unique();
                
                // Get customer details for this chunk
                $customers = Customer::whereIn('id', $customerIds)->get()->keyBy('id');
                
                $csvData = [];
                foreach ($records as $record) {
                    $customer = $customers->get($record->customer_id);
                    $bankName = Banks::where('bank_number', $record->bankName)->first();
                    $branches = \AlphaDirect\BankBranches::where('branch_id', $record->branchCode)->first();
                            
                    $csvData[] = [
                        'customer_id' => $record->customer_id,
                        'customer_name' => $customer ? "{$customer->firstName} {$customer->lastName}" : "Unknown Customer",
                        'customer_email' => $customer ? $customer->email : null,
                        'customer_cellphone' => $customer ? $customer->cellphone : null,
                        'account_number' => $record->accountNumber,
                        'bank_name' => $bankName ? $bankName->bank_name : $record->bankName,
                        'bank_branch' => $branches ? $branches->name : $record->branchCode,
                        'billing' => $record->billing,
                        'policy_id' => $record->policy_id,
                        'policy_number' => $record->Policy ? $record->Policy->policyNumber : null,
                        'status' => 'Valid',
                        'created_at' => $record->created_at,
                        'updated_at' => $record->updated_at,
                        'processed_at' => now()->toISOString()
                    ];
                    
                    $validRecordsCount++;
                }
                
                // Write to temp file
                $this->writeToTempFile($tempFiles['valid_records'], $csvData);
                
                // Clear memory
                unset($csvData);
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            });
        
        $this->info("Found {$validRecordsCount} valid banking records");
        
        // Generate CSV files from temporary files
        $this->generateCsvFromTempFiles($tempFiles, $isDryRun);
        
        // Summary
        $this->info("\n=== VALIDATION SUMMARY ===");
        $this->info("Total banking records processed: {$totalRecords}");
        $this->info("Valid records: {$validRecordsCount}");
        $this->info("Customers with multiple accounts: {$multipleAccountCustomers->count()}");
        $this->info("Account numbers with multiple customers: {$multipleCustomerAccounts->count()}");
        $this->info("Total violations: " . (count($violations['multiple_accounts_per_customer']) + count($violations['multiple_customers_per_account'])));
        
        if ($isDryRun) {
            $this->warn('DRY RUN COMPLETED - No data was actually stored');
        } else {
            $this->info('Validation completed successfully');
        }
        
        return 0;
    }
    
    /**
     * Generate CSV files for validation results
     */
    private function generateCsvFiles(array $violations, bool $isDryRun): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        
        // Generate violations CSV
        $allViolations = array_merge(
            $violations['multiple_accounts_per_customer'],
            $violations['multiple_customers_per_account']
        );
        
        if (!empty($allViolations)) {
            $filename = "banking_violations_{$timestamp}.csv";
            $this->generateCsv($allViolations, $filename, $isDryRun);
            $this->info("Banking violations exported to: {$filename}");
        }
        
        // Generate valid records CSV
        if (!empty($violations['valid_records'])) {
            $filename = "banking_valid_records_{$timestamp}.csv";
            $this->generateCsv($violations['valid_records'], $filename, $isDryRun);
            $this->info("Valid banking records exported to: {$filename}");
        }
        
        // Generate summary CSV
        $summary = [
            [
                'report_type' => 'Banking Validation Summary',
                'generated_at' => now()->toISOString(),
                'total_records' => count($violations['valid_records']) + count($violations['multiple_accounts_per_customer']) + count($violations['multiple_customers_per_account']),
                'valid_records' => count($violations['valid_records']),
                'customers_with_multiple_accounts' => count($violations['multiple_accounts_per_customer']),
                'accounts_with_multiple_customers' => count($violations['multiple_customers_per_account']),
                'total_violations' => count($violations['multiple_accounts_per_customer']) + count($violations['multiple_customers_per_account'])
            ]
        ];
        
        $filename = "banking_validation_summary_{$timestamp}.csv";
        $this->generateCsv($summary, $filename, $isDryRun);
        $this->info("Validation summary exported to: {$filename}");
    }
    
    /**
     * Generate CSV file from data array
     */
    private function generateCsv(array $data, string $filename, bool $isDryRun): void
    {
        if ($isDryRun) {
            $this->line("Would generate CSV: {$filename} with " . count($data) . " records");
            return;
        }
        
        if (empty($data)) {
            return;
        }
        
        // Process data in chunks to avoid memory issues
        $chunkSize = 1000;
        $csvContent = '';
        
        // Add headers
        $csvContent .= implode(',', array_map(function($field) {
            return '"' . str_replace('"', '""', $field) . '"';
        }, array_keys($data[0]))) . "\n";
        
        // Process data in chunks
        $chunks = array_chunk($data, $chunkSize);
        foreach ($chunks as $chunk) {
            foreach ($chunk as $row) {
                $csvContent .= implode(',', array_map(function($field) {
                    return '"' . str_replace('"', '""', $field) . '"';
                }, array_values($row))) . "\n";
            }
            
            // Force garbage collection after each chunk
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        // Store in storage/app/public for easy access
        Storage::disk('public')->put("banking-validation/{$filename}", $csvContent);
        
        // Also store in S3 with timestamped folder
        try {
            $timestamp = \Carbon\Carbon::now()->timestamp;
            $s3Path = "banking-validation/Created-{$timestamp}/{$filename}";
            Storage::disk('s3')->put($s3Path, $csvContent);
            $this->info("File uploaded to S3: {$s3Path}");
        } catch (\Exception $e) {
            $this->warn("Could not upload to S3: " . $e->getMessage());
        }
        
        // Clear memory
        unset($csvContent, $chunks);
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
    
    /**
     * Get CSV headers for different violation types
     */
    private function getCsvHeaders(string $type): array
    {
        switch ($type) {
            case 'multiple_accounts_per_customer':
                return [
                    'customer_id', 'customer_name', 'customer_email', 'customer_cellphone',
                    'account_number', 'bank_name', 'bank_branch', 'billing', 'policy_id',
                    'policy_number', 'violation_type', 'all_account_numbers', 'account_count',
                    'created_at', 'updated_at', 'processed_at'
                ];
            case 'multiple_customers_per_account':
                return [
                    'customer_id', 'customer_name', 'customer_email', 'customer_cellphone',
                    'account_number', 'bank_name', 'bank_branch', 'billing', 'policy_id',
                    'policy_number', 'violation_type', 'all_customer_ids', 'customer_count',
                    'created_at', 'updated_at', 'processed_at'
                ];
            case 'valid_records':
                return [
                    'customer_id', 'customer_name', 'customer_email', 'customer_cellphone',
                    'account_number', 'bank_name', 'bank_branch', 'billing', 'policy_id',
                    'policy_number', 'status', 'created_at', 'updated_at', 'processed_at'
                ];
            default:
                return [];
        }
    }
    
    /**
     * Write data to temporary file
     */
    private function writeToTempFile(string $filePath, array $data): void
    {
        $csvContent = '';
        foreach ($data as $row) {
            $csvContent .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, array_values($row))) . "\n";
        }
        
        file_put_contents($filePath, $csvContent, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Generate CSV files from temporary files
     */
    private function generateCsvFromTempFiles(array $tempFiles, bool $isDryRun): void
    {
        if ($isDryRun) {
            foreach ($tempFiles as $type => $file) {
                $lineCount = count(file($file)) - 1; // Subtract header
                $this->line("Would generate CSV for {$type} with {$lineCount} records");
            }
            return;
        }
        
        $timestamp = now()->format('Y-m-d_H-i-s');
        
        foreach ($tempFiles as $type => $tempFile) {
            if (file_exists($tempFile)) {
                $filename = "banking_{$type}_{$timestamp}.csv";
                
                // Copy temp file to final location
                $localPath = "banking-validation/{$filename}";
                Storage::disk('public')->put($localPath, file_get_contents($tempFile));
                
                // Upload to S3
                try {
                    $s3Timestamp = \Carbon\Carbon::now()->timestamp;
                    $s3Path = "banking-validation/Created-{$s3Timestamp}/{$filename}";
                    Storage::disk('s3')->put($s3Path, file_get_contents($tempFile));
                    $this->info("File uploaded to S3: {$s3Path}");
                } catch (\Exception $e) {
                    $this->warn("Could not upload to S3: " . $e->getMessage());
                }
                
                $this->info("Generated CSV: {$filename}");
                
                // Clean up temp file
                unlink($tempFile);
            }
        }
    }
}
