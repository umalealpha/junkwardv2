<?php

namespace AlphaDirect\Console\Commands;

use AlphaDirect\Models\RewardTier;
use AlphaDirect\PaymentTransaction;
use Illuminate\Console\Command;
use AlphaDirect\Policy;
use AlphaDirect\CustomerPoint;
use AlphaDirect\Services\PointExpirationService;

class CalculateCustomerTiers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate:customer-tiers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate customer tiers and store the results in customer_tier_calculations table.';

    /**
     * Point expiration service
     */
    private PointExpirationService $expirationService;

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
        // Import necessary models
        $customerModel = \AlphaDirect\Customer::class;

        // Get total count for progress bar
        $totalCustomers = $customerModel::where('created_at', '<=', now()->subMonth(12))->count();
        
        if ($totalCustomers === 0) {
            $this->info('No customers found to process.');
            return 0;
        }

        $this->info("Processing {$totalCustomers} customers...");
        
        // Create progress bar
        $progressBar = $this->output->createProgressBar($totalCustomers);
        $progressBar->start();

        // Process customers in chunks to avoid memory issues
        $customerModel::where('created_at', '<=', now()->subMonth(12))
                        ->chunk(100, function ($customers) use ($progressBar) {
                foreach ($customers as $customer) {
                    // Clear existing points for this customer
                    CustomerPoint::where('customer_id', $customer->id)->delete();
                    
                    $totalPoints = 0;
                    $firstTransactionDate = null;
                    $policies = Policy::where('customer_id', $customer->id)->where('status', 1)->get();
                    
                    if($policies->count() > 1){
                        foreach($policies as $policy){
                            // Store individual transaction points for this policy
                            $transactionResult = $this->expirationService->storeTransactionPoints($customer->id, $policy->policyNumber);
                            //dd($transactionResult);
                            if($transactionResult['success']) {
                                $totalPoints += $transactionResult['total_points'];
                                
                                // Set first transaction date if not set
                                if (!$firstTransactionDate) {
                                    $firstTransactionDate = $transactionResult['first_transaction_date'];
                                }
                            }
                            //dd($totalPoints);
                            if($totalPoints >= 600){
                                // if($totalPoints >= 1200){
                                //     $this->storeCustomerPoint(
                                //         $customer->id, 
                                //         '24-Month Loyalty Bonus', 
                                //         24,
                                //         $firstTransactionDate
                                //     );
                                //     $totalPoints += 24;
                                // } else {
                                //     $this->storeCustomerPoint(
                                //         $customer->id, 
                                //         '12-Month Loyalty Bonus', 
                                //         12,
                                //         $firstTransactionDate
                                //     );
                                //     $totalPoints += 12;
                                // }
                                
                                // Single policy points
                                $this->storeCustomerPoint(
                                    $customer->id, 
                                    'Single Policy Bonus', 
                                    400,
                                    $firstTransactionDate
                                );
                                $totalPoints += 400;
                                
                                if($policy->is_bundled == 1){
                                    $this->storeCustomerPoint(
                                        $customer->id, 
                                        'Bundled Policy Bonus', 
                                        1000,
                                        $firstTransactionDate
                                        );
                                    $totalPoints += 1000;
                                } else {
                                    $this->storeCustomerPoint(
                                        $customer->id, 
                                        'Multiple Policy Bonus', 
                                        2000,
                                        $firstTransactionDate
                                    );
                                    $totalPoints += 2000;
                                }
                                
                                // Domcom points
                                if($policy->product_id == 7 || $policy->product_id == 8){
                                    if($totalPoints >= 1200){
                                        $this->storeCustomerPoint(
                                            $customer->id, 
                                            'DomG-ComG Product Bonus (24+ months)', 
                                            1400,
                                            $firstTransactionDate
                                        );
                                        $totalPoints += 1400;
                                    } else {
                                        $this->storeCustomerPoint(
                                            $customer->id, 
                                            'DomG-ComG Product Bonus (12+ months)', 
                                            1000,
                                            $firstTransactionDate
                                        );
                                        $totalPoints += 1000;
                                    }
                                }
                            }
                            break; // Only process first policy for multiple policies
                        }
                    } elseif($policies->count() == 1){
                        $policy = $policies->first();
                        
                        // Store individual transaction points for this policy
                        $transactionResult = $this->expirationService->storeTransactionPoints($customer->id, $policy->policyNumber);
                        
                        if($transactionResult['success']) {
                            $totalPoints += $transactionResult['total_points'];
                            
                            // Set first transaction date
                            $firstTransactionDate = $transactionResult['first_transaction_date'];
                        }
                        
                        if($totalPoints >= 600){
                            // $this->storeCustomerPoint(
                            //     $customer->id, 
                            //     '12-Month Loyalty Bonus', 
                            //     12,
                            //     $firstTransactionDate
                            // );
                           // $totalPoints += 12;
                            
                            // Single policy points
                            $this->storeCustomerPoint(
                                $customer->id, 
                                'Single Policy Bonus', 
                                400,
                                $firstTransactionDate
                            );
                            $totalPoints += 400;
                            
                            if($policy->is_bundled == 1){
                                $this->storeCustomerPoint(
                                    $customer->id, 
                                    'Bundled Policy Bonus', 
                                    1000,
                                    $firstTransactionDate
                                );
                                $totalPoints += 1000;
                            } else {
                                if($policy->product_id == 7 || $policy->product_id == 8){
                                    $this->storeCustomerPoint(
                                        $customer->id, 
                                        'DomG-ComG Product Bonus (12+ months)', 
                                        1000,
                                        $firstTransactionDate
                                    );
                                    $totalPoints += 1000;
                                }
                            }
                        }
                    }

                    // Calculate extra points based on successful transactions
                    if ($policies->count() > 0) {
                        $policy = $policies->first();
                        $transactionData = $this->expirationService->calculateTransactionPoints($policy->policyNumber);
                        //dd($transactionData);
                        $extraPoints = $this->expirationService->calculateExtraPoints(
                            $totalPoints, 
                            $transactionData['successfulTransactions']
                        );
                        
                        if ($extraPoints > 0) {
                            $this->storeCustomerPoint(
                                $customer->id, 
                                'Extra Transaction Bonus', 
                                $extraPoints,
                                $firstTransactionDate
                            );
                            $totalPoints += $extraPoints;
                        }
                    }
                   
                    $tier_id = null;
                    
                    if ($customer->use_point && $customer->use_point > 0) {
                        $customer->point = $totalPoints - $customer->use_point;
                    } else {
                        $customer->point = $totalPoints;
                    }
                    
                    $customer->save();

                    if(2000 > $customer->point && $customer->point >= 1000){
                        $tier_id = 1;
                    }elseif(3000 > $customer->point && $customer->point >= 2000){
                        $tier_id = 2;
                    }elseif(4000 > $customer->point && $customer->point >= 3000){
                        $tier_id = 3;
                    }elseif($customer->point >= 4000){
                        $tier_id = 4;
                    }else{
                        $tier_id = null;
                    }
                    $customer->tier_id = $tier_id;
                    $customer->save();
                    
                    // Update progress bar
                    $progressBar->advance();
                }
            });
            
            // Finish progress bar
            $progressBar->finish();
            $this->newLine();
            $this->info('Customer tier calculation completed successfully!');
            
            return 0;
    }

    /**
     * Store customer point with name and calculated expiration date
     */
    private function storeCustomerPoint($customerId, $name, $points, $firstTransactionDate = null)
    {
        $expireAt = $this->expirationService->calculateExpirationDate($name, $customerId, $firstTransactionDate);
        
        CustomerPoint::create([
            'customer_id' => $customerId,
            'name' => $name,
            'point' => $points,
            'expire_at' => $expireAt,
        ]);
    }

    public function checkTrasactionlist($policyNumber)
    {
        // This method is now replaced by the new transaction calculation logic
        // Keeping for backward compatibility but using the new service method
        $transactionData = $this->expirationService->calculateTransactionPoints($policyNumber);
        return $transactionData['points'];
    }
}

