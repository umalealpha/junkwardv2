<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\Models\AdGroupKycCampaign;
use AlphaDirect\Models\AdGroupKycLink;
use AlphaDirect\Models\EmployerGroup;

class ViewKycLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adgroup:view-kyc-links {campaign_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'View AD Group KYC campaign links and their details';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $campaignId = $this->argument('campaign_id');
        
        if (!$campaignId) {
            // Show all campaigns
            $this->showAllCampaigns();
            return 0;
        }

        // Show specific campaign links
        $this->showCampaignLinks($campaignId);
        return 0;
    }

    /**
     * Show all KYC campaigns
     */
    private function showAllCampaigns()
    {
        $this->info("AD Group KYC Campaigns:");
        $this->line("");

        $campaigns = AdGroupKycCampaign::withCount('links')->get();

        if ($campaigns->isEmpty()) {
            $this->warn("No KYC campaigns found.");
            return;
        }

        $headers = ['ID', 'Name', 'Employer Group', 'Status', 'Links Count', 'Created'];
        $rows = [];

        foreach ($campaigns as $campaign) {
            $rows[] = [
                $campaign->id,
                $campaign->name,
                $campaign->employer_group_id,
                $campaign->status,
                $campaign->links_count,
                $campaign->created_at->format('Y-m-d H:i:s')
            ];
        }

        $this->table($headers, $rows);
        $this->line("");
        $this->info("To view links for a specific campaign, run:");
        $this->line("php artisan adgroup:view-kyc-links {campaign_id}");
    }

    /**
     * Show campaign links
     */
    private function showCampaignLinks($campaignId)
    {
        $campaign = AdGroupKycCampaign::with('links.customer', 'links.policy')->find($campaignId);

        if (!$campaign) {
            $this->error("Campaign with ID {$campaignId} not found.");
            return;
        }

        $this->info("KYC Campaign: {$campaign->name}");
        $this->info("Employer Group: {$campaign->employer_group_id}");
        $this->info("Status: {$campaign->status}");
        $this->line("");

        $links = $campaign->links;

        if ($links->isEmpty()) {
            $this->warn("No KYC links found for this campaign.");
            return;
        }

        $this->info("KYC Links ({$links->count()} total):");
        $this->line("");

        $headers = ['Link ID', 'Employee Name', 'Email', 'Policy Number', 'Status', 'OTP Code', 'KYC URL'];
        $rows = [];

        foreach ($links as $link) {
            $employeeName = $link->customer ? $link->customer->firstName . ' ' . $link->customer->lastName : 'N/A';
            $email = $link->customer ? $link->customer->email : 'N/A';
            $policyNumber = $link->policy ? $link->policy->policyNumber : 'N/A';
            $kycUrl = env('START_URL')."ad-group-kyc/verify/{$link->unique_token}";

            $rows[] = [
                $link->id,
                $employeeName,
                $email,
                $policyNumber,
                $link->status,
                $link->otp_code,
                $kycUrl
            ];
        }

        $this->table($headers, $rows);
        $this->line("");

        // Show status summary
        $statusCounts = $links->groupBy('status')->map->count();
        $this->info("Status Summary:");
        foreach ($statusCounts as $status => $count) {
            $this->line("- {$status}: {$count}");
        }
    }
}
