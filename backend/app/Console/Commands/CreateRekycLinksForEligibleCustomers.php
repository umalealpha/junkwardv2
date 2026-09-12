<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Customer;
use AlphaDirect\KYC;
use AlphaDirect\Models\RekycLink;
use AlphaDirect\Models\RekycCampaign;
use AlphaDirect\Services\RekycNotificationService;
use AlphaDirect\Services\RekycService;
use AlphaDirect\Services\RekycAuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class CreateRekycLinksForEligibleCustomers extends Command
{
    protected $rekycService;
    protected $notificationService;
    protected $auditService;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rekyc:create-links-for-eligible-customers 
                            {--campaign-id= : Specific campaign ID to use}
                            {--dry-run : Run without creating links or sending notifications}
                            {--channels=email,sms,whatsapp : Notification channels to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create rekyc links for customers with KYC compliance status 1 and KYC updated more than 2 years ago (excluding those with completed ReKYC)';

    /**
     * Create a new command instance.
     */
    public function __construct(
        RekycService $rekycService,
        RekycNotificationService $notificationService,
        RekycAuditService $auditService
    ) {
        parent::__construct();
        $this->rekycService = $rekycService;
        $this->notificationService = $notificationService;
        $this->auditService = $auditService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting Re-KYC link creation for eligible customers...');
        
        //try {
            // Get campaign ID
            $campaignId = $this->option('campaign-id');
            if (!$campaignId) {
                $campaignId = $this->getOrCreateDefaultCampaign();
            }
            
            // Get notification channels
            $channels = explode(',', $this->option('channels'));
            $isDryRun = $this->option('dry-run');
            
            $this->info("Notification channels configured: " . implode(', ', $channels));
            
            if ($isDryRun) {
                $this->warn('Running in DRY RUN mode - no links will be created or notifications sent');
            }
            
            // Find eligible customers
            $eligibleCustomers = $this->getEligibleCustomers();
            
            if ($eligibleCustomers->isEmpty()) {
                $this->info('No eligible customers found.');
                return 0;
            }
            
            $this->info("Found {$eligibleCustomers->count()} eligible customers");
            
            // Process each customer
            $processed = 0;
            $errors = 0;
            
            foreach ($eligibleCustomers as $customer) {
                //try {
                    if ($isDryRun) {
                        $this->line("DRY RUN: Would create link for customer ID: {$customer->id} - {$customer->fullName} ({$customer->email})");
                        $processed++;
                        continue;
                    }
                    
                    // Check if customer already has an active rekyc link
                    $existingLink = RekycLink::where('customer_id', $customer->id)
                        ->where('campaign_id', $campaignId)
                        ->where('status', '!=', 'expired')
                        ->where('expires_at', '>', now())
                        ->first();
                    
                    if ($existingLink) {
                        $this->warn("Customer {$customer->id} already has an active rekyc link. Skipping...");
                        continue;
                    }
                    
                    // Create rekyc link
                    $link = $this->createRekycLink($customer, $campaignId);
                    
                    if ($link) {
                        // Send notifications
                        $this->sendNotifications($link, $channels);
                        $customMessage = null;
                        $rekycAdminController = app(\AlphaDirect\Http\Controllers\Admin\RekycAdminController::class);
                        $adminResults = $rekycAdminController->sendNotificationMain($link->id,$channels,$customMessage);
                        
                        $this->info("Admin notification results: " . ($adminResults ? 'Success' : 'Failed'));
                        
                        // Refresh link to get updated delivery method
                        $link->refresh();
                        $this->info("Delivery method: " . ($link->delivery_method ?: 'Not set'));
                        
                        $processed++;
                        
                        $this->info("Created rekyc link for customer {$customer->id} - {$customer->fullName}");
                    }
                    
                // } catch (Exception $e) {
                //     $errors++;
                //     $this->error("Error processing customer {$customer->id}: " . $e->getMessage());
                //     Log::error("Rekyc link creation error for customer {$customer->id}", [
                //         'customer_id' => $customer->id,
                //         'error' => $e->getMessage(),
                //         'trace' => $e->getTraceAsString()
                //     ]);
                // }
            }
            
            $this->info("Processing completed. Processed: {$processed}, Errors: {$errors}");
            
            return 0;
            
        // } catch (Exception $e) {
        //     $this->error("Command failed: " . $e->getMessage());
        //     Log::error("Rekyc link creation command failed", [
        //         'error' => $e->getMessage(),
        //         'trace' => $e->getTraceAsString()
        //     ]);
        //     return 1;
        // }
    }
    
    /**
     * Get eligible customers based on criteria
     */
    private function getEligibleCustomers()
    {
        $twoYearsAgo = Carbon::now()->subYears(2);
        
        return Customer::with(['KYC'])
            ->whereHas('KYC', function ($query) use ($twoYearsAgo) {
                $query->where('compliance', 1) // KYC compliance status = 1
                      ->where('updated_at', '<', $twoYearsAgo); // KYC updated more than 2 years ago
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('rekyc_links')
                      ->whereColumn('rekyc_links.customer_id', 'customer.id')
                      ->where('rekyc_links.status', 'completed'); // Exclude customers with completed ReKYC
            })
            // ->whereNotNull('email') // Must have email
            // ->where('email', '!=', '') // Email must not be empty
            ->get();
    }
    
    /**
     * Get or create default campaign
     */
    private function getOrCreateDefaultCampaign()
    {
        $currentMonth = Carbon::now()->format('F Y'); // e.g., "January 2024"
        $campaignName = "Auto Re-KYC Campaign - {$currentMonth}";
        
        $campaign = RekycCampaign::where('name', $campaignName)
            ->where('status', 'active')
            ->first();
            
        if (!$campaign) {
            $campaign = RekycCampaign::create([
                'name' => $campaignName,
                'description' => "Automated campaign for customers with KYC compliance status 1 and KYC updated more than 2 years ago (excluding those with completed ReKYC) - {$currentMonth}",
                'status' => 'active',
                'settings' => [
                    'link_expiry_days' => 30,
                    'otp_expiry_minutes' => 15,
                    'max_attempts' => 3,
                    'auto_escalation' => true
                ],
                'escalation_days' => 30,
                'reminder_days' => [3, 7, 14],
                'created_by' => auth()->user()->id ?? 1, // System user
                'updated_by' => auth()->user()->id ?? 1
            ]);
            
            $this->info("Created default campaign with ID: {$campaign->id} - {$campaignName} - ".auth()->user()->id ?? 1);
        }
        
        return $campaign->id;
    }
    
    /**
     * Create rekyc link for customer
     */
    private function createRekycLink(Customer $customer, $campaignId)
    {
        $expiresAt = Carbon::now()->addDays(30); // 30 days expiry
        
        return RekycLink::create([
            'campaign_id' => $campaignId,
            'customer_id' => $customer->id,
            'status' => 'pending',
            'expires_at' => $expiresAt,
            'otp_attempts' => 0,
            'consent_data' => [
                'customer_name' => $customer->fullName,
                'customer_email' => $customer->email,
                'created_at' => now()->toISOString()
            ]
        ]);
    }
    
    /**
     * Send notifications via specified channels
     */
    private function sendNotifications(RekycLink $link, array $channels)
    {
        try {
            $this->info("Sending notifications for customer {$link->customer_id} via channels: " . implode(', ', $channels));
            
            $notificationService = app(RekycNotificationService::class);
            $results = $notificationService->sendRekycLink($link, $channels);
            
            // Log results
            foreach ($results as $channel => $result) {
                if ($result['success']) {
                    $this->line("  ✓ {$channel}: {$result['message']}");
                } else {
                    $this->error("  ✗ {$channel}: {$result['error']}");
                }
            }
            
            // Also log the results for debugging
            Log::info("ReKYC notification results for link {$link->id}", [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'channels' => $channels,
                'results' => $results
            ]);
            
        } catch (Exception $e) {
            $this->error("Failed to send notifications: " . $e->getMessage());
            Log::error("Notification sending failed for link {$link->id}", [
                'link_id' => $link->id,
                'customer_id' => $link->customer_id,
                'channels' => $channels,
                'error' => $e->getMessage()
            ]);
        }
    }
}
