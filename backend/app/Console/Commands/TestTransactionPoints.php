<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Customer;
use AlphaDirect\CustomerPoint;
use AlphaDirect\Services\PointExpirationService;
use Carbon\Carbon;

class TestTransactionPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:transaction-points {customer_id?} {--policy=} {--clear}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test individual transaction point storage functionality';

    protected $expirationService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(PointExpirationService $expirationService)
    {
        parent::__construct();
        $this->expirationService = $expirationService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $customerId = $this->argument('customer_id');
        $policyNumber = $this->option('policy');
        $clear = $this->option('clear');
        
        if ($customerId) {
            $customer = Customer::find($customerId);
            if (!$customer) {
                $this->error("Customer with ID {$customerId} not found.");
                return 1;
            }
            
            if ($clear) {
                CustomerPoint::where('customer_id', $customer->id)->delete();
                $this->info("Cleared all points for customer {$customer->firstName} {$customer->lastName}");
            }
            
            if ($policyNumber) {
                $this->testSinglePolicy($customer, $policyNumber);
            } else {
                $this->testAllPolicies($customer);
            }
        } else {
            // Show sample data for first few customers
            $customers = Customer::with('customerPoints')->take(3)->get();
            
            foreach ($customers as $customer) {
                $this->displayCustomerPoints($customer);
                $this->line('---');
            }
        }
        
        return 0;
    }

    private function testSinglePolicy($customer, $policyNumber)
    {
        $this->info("Testing transaction points for customer: {$customer->firstName} {$customer->lastName}");
        $this->info("Policy: {$policyNumber}");
        
        // Calculate transaction points
        $transactionData = $this->expirationService->calculateTransactionPoints($policyNumber);
        
        $this->info("Transaction Analysis:");
        $this->line("Total successful transactions: {$transactionData['successfulTransactions']}");
        $this->line("Total points: {$transactionData['totalPoints']}");
        $this->line("First transaction date: " . ($transactionData['firstTransactionDate'] ? $transactionData['firstTransactionDate']->format('Y-m-d') : 'N/A'));
        
        if (!empty($transactionData['transactionPoints'])) {
            $this->info("\nIndividual Transactions:");
            $headers = ['Date', 'Amount', 'Status', 'Points', 'Expires'];
            $rows = [];
            
            foreach ($transactionData['transactionPoints'] as $txn) {
                $expireDate = $txn['created_at'] 
                    ? Carbon::parse($txn['created_at'])->addDays(1000)->format('Y-m-d')
                    : Carbon::parse($txn['payment_date'])->addDays(1000)->format('Y-m-d');
                
                $rows[] = [
                    Carbon::parse($txn['payment_date'])->format('Y-m-d'),
                    $txn['amount'],
                    $txn['status'],
                    $txn['points'],
                    $expireDate
                ];
            }
            
            $this->table($headers, $rows);
        }
        
        // Store transaction points
        $result = $this->expirationService->storeTransactionPoints($customer->id, $policyNumber);
        
        $this->info("\nStorage Result:");
        $this->line("Success: " . ($result['success'] ? 'Yes' : 'No'));
        $this->line("Message: {$result['message']}");
        $this->line("Points stored: {$result['points_stored']}");
        
        // Display updated customer points
        $this->displayCustomerPoints($customer);
    }

    private function testAllPolicies($customer)
    {
        $this->info("Testing all policies for customer: {$customer->firstName} {$customer->lastName}");
        
        $result = $this->expirationService->storeAllCustomerTransactionPoints($customer->id);
        
        $this->info("Storage Result:");
        $this->line("Success: " . ($result['success'] ? 'Yes' : 'No'));
        $this->line("Message: {$result['message']}");
        $this->line("Total points stored: {$result['total_points_stored']}");
        $this->line("Policies processed: {$result['policies_processed']}");
        
        if (!empty($result['policy_results'])) {
            $this->info("\nPolicy Results:");
            foreach ($result['policy_results'] as $policyNumber => $policyResult) {
                $this->line("Policy {$policyNumber}: {$policyResult['message']}");
            }
        }
        
        // Display updated customer points
        $this->displayCustomerPoints($customer);
    }

    private function displayCustomerPoints($customer)
    {
        $this->info("Customer: {$customer->firstName} {$customer->lastName} (ID: {$customer->id})");
        $this->line("Email: {$customer->email}");
        $this->line("Total Points (from customer table): {$customer->point}");
        $this->line("Total Active Points (from customer_point table): {$customer->getTotalActivePoints()}");
        
        $points = $customer->getActivePoints();
        if ($points->count() > 0) {
            $this->line("\nPoint Details:");
            $headers = ['Name', 'Points', 'Created', 'Expires'];
            $rows = [];
            
            foreach ($points as $point) {
                $rows[] = [
                    $point->name,
                    $point->point,
                    $point->created_at->format('Y-m-d H:i:s'),
                    $point->expire_at ? $point->expire_at->format('Y-m-d') : 'Never'
                ];
            }
            
            $this->table($headers, $rows);
        } else {
            $this->line("No points found for this customer.");
        }
    }
} 