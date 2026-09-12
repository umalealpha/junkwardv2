<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Customer;
use AlphaDirect\Models\DeduplicationCheck;
use AlphaDirect\Models\DuplicateCustomer;
use AlphaDirect\CustomerBanking;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProcessCustomerDeduplication extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:process-deduplication {--dry-run : Show what would be processed without actually processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all customers for deduplication checks and store data in deduplication_checks table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('Starting customer deduplication processing...');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No data will be stored');
        }
        
        // Get all customers with their banking information
        $customers = Customer::with(['banking', 'profile'])
            ->whereHas('banking', function($query) {
                $query->whereIn('billing', ['realpay', 'Paym8']);
            })->limit(300)
            ->get();
        
        $this->info("Found {$customers->count()} customers with realpay/Paym8 billing");
        
        $processedCount = 0;
        $duplicateOmangPassport = [];
        $duplicateAccountNumbers = [];
        $duplicateCellphoneEmail = [];
        $storedCount = 0;
        
        // Track processed account numbers per customer
        $customerAccountNumbers = [];
        
        foreach ($customers as $customer) {
            $this->line("Processing Customer ID: {$customer->id} - {$customer->firstName} {$customer->lastName}");
            
            // Check for existing deduplication check
            $existingCheck = DeduplicationCheck::where('customer_id', $customer->id)->first();
            if ($existingCheck) {
                $this->line("  - Skipping: Already has deduplication check");
                continue;
            }
            
            // Get customer banking information
            $banking = $customer->banking;
            if (!$banking) {
                $this->line("  - Skipping: No banking information");
                continue;
            }
            
            // Get bank details from customer banking
            $bankName = $banking->bankName ?? null;
            $bankBranch = $banking->branchCode ?? null;
            
            // Check for duplicate Omang or Passport
            $omangNumber = $customer->profile->omang_number ?? null;
            $passportNumber = $customer->profile->passport_number ?? null;
            
            $hasDuplicateOmangPassport = false;
            if ($omangNumber || $passportNumber) {
                $duplicateQuery = DeduplicationCheck::query();
                
                if ($omangNumber) {
                    $duplicateQuery->orWhere('omang_number', $omangNumber);
                }
                if ($passportNumber) {
                    $duplicateQuery->orWhere('passport_number', $passportNumber);
                }
                
                $duplicates = $duplicateQuery->get();
                
                if ($duplicates->count() > 0) {
                    $hasDuplicateOmangPassport = true;
                    
                    // Store in duplicate_customers table
                    if (!$isDryRun) {
                        DuplicateCustomer::create([
                            'customer_id' => $customer->id,
                            'first_name' => $customer->firstName,
                            'last_name' => $customer->lastName,
                            'email' => $customer->email,
                            'cellphone' => $customer->cellphone,
                            'omang_number' => $omangNumber,
                            'passport_number' => $passportNumber,
                            'bank_account_number' => $banking->accountNumber,
                            'bank_name' => $bankName,
                            'bank_branch' => $bankBranch,
                            'billing' => $banking->billing,
                            'duplicate_type' => 'omang_passport',
                            'duplicate_reason' => 'Omang or Passport already exists in deduplication_checks table',
                            'duplicate_details' => [
                                'existing_records' => $duplicates->pluck('id')->toArray(),
                                'omang_number' => $omangNumber,
                                'passport_number' => $passportNumber
                            ],
                            'status' => 'pending',
                            'notes' => 'Automatically detected by cron command'
                        ]);
                    }
                    
                    // Also add to CSV array for export
                    $duplicateOmangPassport[] = [
                        'customer_id' => $customer->id,
                        'first_name' => $customer->firstName,
                        'last_name' => $customer->lastName,
                        'email' => $customer->email,
                        'cellphone' => $customer->cellphone,
                        'omang_number' => $omangNumber,
                        'passport_number' => $passportNumber,
                        'billing' => $banking->billing,
                        'account_number' => $banking->accountNumber,
                        'bank_name' => $bankName,
                        'bank_branch' => $bankBranch,
                        'duplicate_reason' => 'Omang or Passport already exists',
                        'processed_at' => now()->toISOString()
                    ];
                    
                    $this->line("  - Duplicate found: Omang/Passport already exists");
                }
            }
            
            // Check for duplicate Cellphone or Email
            $cellphone = $customer->cellphone;
            $email = $customer->email;
            
            $hasDuplicateCellphoneEmail = false;
            if ($cellphone || $email) {
                $duplicateQuery = DeduplicationCheck::query();
                
                if ($cellphone) {
                    $duplicateQuery->orWhere('cellphone', $cellphone);
                }
                if ($email) {
                    $duplicateQuery->orWhere('email', $email);
                }
                
                $duplicates = $duplicateQuery->get();
                
                if ($duplicates->count() > 0) {
                    $hasDuplicateCellphoneEmail = true;
                    
                    // Store in duplicate_customers table
                    if (!$isDryRun) {
                        DuplicateCustomer::create([
                            'customer_id' => $customer->id,
                            'first_name' => $customer->firstName,
                            'last_name' => $customer->lastName,
                            'email' => $email,
                            'cellphone' => $cellphone,
                            'omang_number' => $omangNumber,
                            'passport_number' => $passportNumber,
                            'bank_account_number' => $banking->accountNumber,
                            'bank_name' => $bankName,
                            'bank_branch' => $bankBranch,
                            'billing' => $banking->billing,
                            'duplicate_type' => 'cellphone_email',
                            'duplicate_reason' => 'Cellphone or Email already exists in deduplication_checks table',
                            'duplicate_details' => [
                                'existing_records' => $duplicates->pluck('id')->toArray(),
                                'cellphone' => $cellphone,
                                'email' => $email
                            ],
                            'status' => 'pending',
                            'notes' => 'Automatically detected by cron command'
                        ]);
                    }
                    
                    // Also add to CSV array for export
                    $duplicateCellphoneEmail[] = [
                        'customer_id' => $customer->id,
                        'first_name' => $customer->firstName,
                        'last_name' => $customer->lastName,
                        'email' => $email,
                        'cellphone' => $cellphone,
                        'omang_number' => $omangNumber,
                        'passport_number' => $passportNumber,
                        'billing' => $banking->billing,
                        'account_number' => $banking->accountNumber,
                        'bank_name' => $bankName,
                        'bank_branch' => $bankBranch,
                        'duplicate_reason' => 'Cellphone or Email already exists',
                        'processed_at' => now()->toISOString()
                    ];
                    
                    $this->line("  - Duplicate found: Cellphone/Email already exists");
                }
            }
            
            // Check for duplicate account numbers for same customer
            $accountNumber = $banking->accountNumber;
            if ($accountNumber) {
                if (!isset($customerAccountNumbers[$customer->id])) {
                    $customerAccountNumbers[$customer->id] = [];
                }
                
                if (in_array($accountNumber, $customerAccountNumbers[$customer->id])) {
                    // Store in duplicate_customers table
                    if (!$isDryRun) {
                        DuplicateCustomer::create([
                            'customer_id' => $customer->id,
                            'first_name' => $customer->firstName,
                            'last_name' => $customer->lastName,
                            'email' => $customer->email,
                            'cellphone' => $customer->cellphone,
                            'omang_number' => $omangNumber,
                            'passport_number' => $passportNumber,
                            'bank_account_number' => $accountNumber,
                            'bank_name' => $bankName,
                            'bank_branch' => $bankBranch,
                            'billing' => $banking->billing,
                            'duplicate_type' => 'multiple_accounts',
                            'duplicate_reason' => 'Multiple account numbers for same customer',
                            'duplicate_details' => [
                                'existing_accounts' => $customerAccountNumbers[$customer->id],
                                'duplicate_account' => $accountNumber
                            ],
                            'status' => 'pending',
                            'notes' => 'Automatically detected by cron command'
                        ]);
                    }
                    
                    // Also add to CSV array for export
                    $duplicateAccountNumbers[] = [
                        'customer_id' => $customer->id,
                        'first_name' => $customer->firstName,
                        'last_name' => $customer->lastName,
                        'email' => $customer->email,
                        'cellphone' => $customer->cellphone,
                        'billing' => $banking->billing,
                        'account_number' => $accountNumber,
                        'bank_name' => $bankName,
                        'bank_branch' => $bankBranch,
                        'duplicate_reason' => 'Multiple account numbers for same customer',
                        'processed_at' => now()->toISOString()
                    ];
                    
                    $this->line("  - Duplicate found: Multiple account numbers for same customer");
                    continue;
                }
                
                $customerAccountNumbers[$customer->id][] = $accountNumber;
            }
            
            // If no duplicates found, store in deduplication_checks table
            if (!$hasDuplicateOmangPassport && !$hasDuplicateCellphoneEmail) {
                if (!$isDryRun) {
                    try {
                        $deduplicationCheck = DeduplicationCheck::create([
                            'customer_id' => $customer->id,
                            'omang_number' => $omangNumber,
                            'passport_number' => $passportNumber,
                            'bank_account_number' => $accountNumber,
                            'bank_name' => $bankName,
                            'bank_branch' => $bankBranch,
                            'cellphone' => $customer->cellphone,
                            'email' => $customer->email,
                            'status' => 'active',
                            'notes' => 'Processed via cron command - ' . $banking->billing . ' billing'
                        ]);
                        
                        $storedCount++;
                        $this->line("  - Stored in deduplication_checks table");
                        
                    } catch (\Exception $e) {
                        $this->error("  - Error storing: " . $e->getMessage());
                    }
                } else {
                    $this->line("  - Would store in deduplication_checks table");
                    $storedCount++;
                }
            }
            
            $processedCount++;
        }
        
        // Generate CSV files for duplicates
        $this->generateCsvFiles($duplicateOmangPassport, $duplicateAccountNumbers, $duplicateCellphoneEmail, $isDryRun);
        
        // Summary
        $this->info("\n=== PROCESSING SUMMARY ===");
        $this->info("Total customers processed: {$processedCount}");
        $this->info("Successfully stored: {$storedCount}");
        $this->info("Omang/Passport duplicates: " . count($duplicateOmangPassport));
        $this->info("Account number duplicates: " . count($duplicateAccountNumbers));
        $this->info("Cellphone/Email duplicates: " . count($duplicateCellphoneEmail));
        
        if ($isDryRun) {
            $this->warn('DRY RUN COMPLETED - No data was actually stored');
        } else {
            $this->info('Processing completed successfully');
        }
        
        return 0;
    }
    
    /**
     * Generate CSV files for duplicate data
     */
    private function generateCsvFiles(array $omangPassportDuplicates, array $accountDuplicates, array $cellphoneEmailDuplicates, bool $isDryRun): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        
        // Generate Omang/Passport duplicates CSV
        if (!empty($omangPassportDuplicates)) {
            $filename = "duplicate_omang_passport_{$timestamp}.csv";
            $this->generateCsv($omangPassportDuplicates, $filename, $isDryRun);
            $this->info("Omang/Passport duplicates exported to: {$filename}");
        }
        
        // Generate Account number duplicates CSV
        if (!empty($accountDuplicates)) {
            $filename = "duplicate_account_numbers_{$timestamp}.csv";
            $this->generateCsv($accountDuplicates, $filename, $isDryRun);
            $this->info("Account number duplicates exported to: {$filename}");
        }
        
        // Generate Cellphone/Email duplicates CSV
        if (!empty($cellphoneEmailDuplicates)) {
            $filename = "duplicate_cellphone_email_{$timestamp}.csv";
            $this->generateCsv($cellphoneEmailDuplicates, $filename, $isDryRun);
            $this->info("Cellphone/Email duplicates exported to: {$filename}");
        }
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
        
        $csvData = [];
        
        // Add headers
        if (!empty($data)) {
            $csvData[] = array_keys($data[0]);
            
            // Add data rows
            foreach ($data as $row) {
                $csvData[] = array_values($row);
            }
        }
        
        // Write to storage
        $csvContent = '';
        foreach ($csvData as $row) {
            $csvContent .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }
        
        Storage::disk('s3')->put("duplicates/{$filename}", $csvContent);
    }
}
