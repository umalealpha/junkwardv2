<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Services\PointExpirationService;
use AlphaDirect\CustomerPoint;

class ManagePointExpiration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:manage-expiration 
                            {action : Action to perform (report|cleanup|rules|summary)}
                            {--days=30 : Number of days for expiring soon report}
                            {--customer-id= : Specific customer ID for summary}
                            {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage customer point expiration';

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
        $action = $this->argument('action');

        switch ($action) {
            case 'report':
                return $this->generateExpirationReport();
            case 'cleanup':
                return $this->cleanupExpiredPoints();
            case 'rules':
                return $this->showExpirationRules();
            case 'summary':
                return $this->showCustomerSummary();
            default:
                $this->error("Invalid action: {$action}");
                $this->info("Available actions: report, cleanup, rules, summary");
                return 1;
        }
    }

    /**
     * Generate expiration report
     */
    private function generateExpirationReport(): int
    {
        $days = (int) $this->option('days');
        
        $this->info("Generating expiration report for points expiring in the next {$days} days...");
        
        $report = $this->expirationService->getExpiringPointsReport($days);
        
        if ($report['total_points_expiring'] === 0) {
            $this->info("No points are expiring in the next {$days} days.");
            return 0;
        }

        $this->info("📊 EXPIRATION REPORT");
        $this->info("Points expiring by: " . $report['expiration_date']);
        $this->info("Total points expiring: " . $report['total_points_expiring']);
        $this->info("Customers affected: " . $report['total_customers_affected']);
        $this->newLine();

        // Show points by type
        $this->info("📋 POINTS BY TYPE:");
        $typeHeaders = ['Point Type', 'Count', 'Total Points', 'Customers'];
        $typeRows = [];
        
        foreach ($report['points_by_type'] as $type => $data) {
            $typeRows[] = [
                $type,
                $data['count'],
                $data['total_points'],
                count(array_unique($data['customers']))
            ];
        }
        
        $this->table($typeHeaders, $typeRows);
        $this->newLine();

        // Show top customers
        $this->info("👥 TOP CUSTOMERS WITH EXPIRING POINTS:");
        $customerHeaders = ['Customer', 'Total Points Expiring', 'Point Types'];
        $customerRows = [];
        
        $sortedCustomers = collect($report['points_by_customer'])
            ->sortByDesc('total_points')
            ->take(10);
            
        foreach ($sortedCustomers as $customerId => $customerData) {
            $pointTypes = collect($customerData['points'])->pluck('name')->unique()->implode(', ');
            $customerRows[] = [
                $customerData['customer_name'],
                $customerData['total_points'],
                $pointTypes
            ];
        }
        
        $this->table($customerHeaders, $customerRows);

        return 0;
    }

    /**
     * Cleanup expired points
     */
    private function cleanupExpiredPoints(): int
    {
        $force = $this->option('force');
        
        $this->info("Checking for expired points...");
        
        $expiredPoints = $this->expirationService->getExpiredPoints();
        $count = $expiredPoints->count();
        
        if ($count === 0) {
            $this->info("No expired points found.");
            return 0;
        }

        $this->warn("Found {$count} expired points:");
        
        // Show expired points summary
        $expiredHeaders = ['Customer', 'Point Type', 'Points', 'Expired Date'];
        $expiredRows = [];
        
        foreach ($expiredPoints->take(10) as $point) {
            $expiredRows[] = [
                $point->customer->firstName . ' ' . $point->customer->lastName,
                $point->name,
                $point->point,
                $point->expire_at->format('Y-m-d H:i:s')
            ];
        }
        
        $this->table($expiredHeaders, $expiredRows);
        
        if ($count > 10) {
            $this->info("... and " . ($count - 10) . " more expired points.");
        }

        if (!$force) {
            if (!$this->confirm("Do you want to log these expired points? (This will not delete them)")) {
                $this->info("Operation cancelled.");
                return 0;
            }
        }

        $loggedCount = $this->expirationService->cleanupExpiredPoints(true);
        $this->info("✅ Successfully logged {$loggedCount} expired points.");
        
        return 0;
    }

    /**
     * Show expiration rules
     */
    private function showExpirationRules(): int
    {
        $this->info("📋 POINT EXPIRATION RULES:");
        
        $rules = $this->expirationService->getAllExpirationRules();
        
        $headers = ['Point Type', 'Duration', 'Description'];
        $rows = [];
        
        foreach ($rules as $pointType => $rule) {
            $duration = $rule['duration'] ?? 'Never expires';
            $rows[] = [
                $pointType,
                $duration,
                $rule['description']
            ];
        }
        
        $this->table($headers, $rows);
        
        return 0;
    }

    /**
     * Show customer summary
     */
    private function showCustomerSummary(): int
    {
        $customerId = $this->option('customer-id');
        
        if (!$customerId) {
            $this->error("Please provide a customer ID using --customer-id option");
            return 1;
        }

        $summary = $this->expirationService->getCustomerPointsSummary((int) $customerId);
        
        if (empty($summary)) {
            $this->error("Customer not found or no points data available.");
            return 1;
        }

        $this->info("👤 CUSTOMER POINTS SUMMARY");
        $this->info("Customer: " . $summary['customer']['name']);
        $this->info("Email: " . $summary['customer']['email']);
        $this->newLine();

        $this->info("📊 POINTS OVERVIEW:");
        $this->info("Total Active Points: " . $summary['total_active_points']);
        $this->info("Total Expired Points: " . $summary['total_expired_points']);
        $this->info("Points Expiring Soon (30 days): " . $summary['points_expiring_soon']);
        $this->newLine();

        $this->info("Active Points: " . $summary['active_points_count']);
        $this->info("Expired Points: " . $summary['expired_points_count']);
        $this->info("Expiring Soon: " . $summary['expiring_soon_count']);
        $this->newLine();

        if ($summary['active_points_count'] > 0) {
            $this->info("✅ ACTIVE POINTS:");
            $activeHeaders = ['Point Type', 'Points', 'Created', 'Expires'];
            $activeRows = [];
            
            foreach ($summary['active_points'] as $point) {
                $activeRows[] = [
                    $point->name,
                    $point->point,
                    $point->created_at->format('Y-m-d'),
                    $point->expire_at ? $point->expire_at->format('Y-m-d') : 'Never'
                ];
            }
            
            $this->table($activeHeaders, $activeRows);
        }

        if ($summary['expiring_soon_count'] > 0) {
            $this->warn("⚠️  POINTS EXPIRING SOON:");
            $expiringHeaders = ['Point Type', 'Points', 'Expires'];
            $expiringRows = [];
            
            foreach ($summary['expiring_soon'] as $point) {
                $expiringRows[] = [
                    $point->name,
                    $point->point,
                    $point->expire_at->format('Y-m-d')
                ];
            }
            
            $this->table($expiringHeaders, $expiringRows);
        }

        return 0;
    }
}
