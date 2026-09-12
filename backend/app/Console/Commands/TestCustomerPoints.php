<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Customer;
use AlphaDirect\CustomerPoint;

class TestCustomerPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:customer-points {customer_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test customer points functionality';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $customerId = $this->argument('customer_id');
        
        if ($customerId) {
            $customer = Customer::find($customerId);
            if (!$customer) {
                $this->error("Customer with ID {$customerId} not found.");
                return 1;
            }
            
            // Ask if user wants to add sample points
            if ($this->confirm('Do you want to add sample points to this customer?')) {
                $this->addSamplePoints($customer);
            }
            
            $this->displayCustomerPoints($customer);
        } else {
            // Show sample data for first few customers
            $customers = Customer::with('customerPoints')->take(5)->get();
            
            foreach ($customers as $customer) {
                $this->displayCustomerPoints($customer);
                $this->line('---');
            }
        }
        
        return 0;
    }

    private function addSamplePoints($customer)
    {
        // Clear existing points
        CustomerPoint::where('customer_id', $customer->id)->delete();
        
        // Add sample points
        $samplePoints = [
            ['name' => 'Welcome Bonus', 'point' => 500, 'expire_at' => now()->addYear()],
            ['name' => 'First Policy Bonus', 'point' => 1000, 'expire_at' => now()->addMonths(6)],
            ['name' => 'Loyalty Points', 'point' => 750, 'expire_at' => null],
            ['name' => 'Referral Bonus', 'point' => 300, 'expire_at' => now()->addMonths(3)],
        ];
        
        foreach ($samplePoints as $pointData) {
            CustomerPoint::create([
                'customer_id' => $customer->id,
                'name' => $pointData['name'],
                'point' => $pointData['point'],
                'expire_at' => $pointData['expire_at'],
            ]);
        }
        
        $this->info("Added sample points to customer {$customer->firstName} {$customer->lastName}");
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
