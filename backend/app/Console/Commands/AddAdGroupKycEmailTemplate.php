<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use AlphaDirect\EmailBroadcasting;

class AddAdGroupKycEmailTemplate extends Command
{
    protected $signature = 'add:ad-group-kyc-email-template';
    protected $description = 'Add AD Group KYC email template to email_broadcasting table';

    public function handle()
    {
        $this->info('Adding AD Group KYC Email Template...');
        
        $templateText = 'Dear [[Customers Firstname_5]]

We need to verify your KYC information for compliance with regulatory requirements.

URL :[[AD Group Kyc Url_73]]

Otp Code: [[AD Group Kyc Otp Code_74]]

Otp Code Expiry: [[AD Group Kyc Otp Expiry_75]]

Link Exprire: [[AD Group Kyc Link Expiry Days_76]]

[[AD Group Kyc Custom Message_77]]

Important: If you didn\'t request this verification, please contact our support team immediately.

Best regards,
Alpha Direct Compliance Team';

        $template = EmailBroadcasting::updateOrCreate(
            ['hook_slug' => 'ad_group_kyc_link'],
            [
                'subject' => 'AD Group Insurance - KYC Verification Required',
                'text' => $templateText,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if ($template) {
            $this->info('✅ AD Group KYC email template added successfully!');
            $this->info('Hook: ad_group_kyc_link');
            $this->info('Subject: ' . $template->subject);
        } else {
            $this->error('❌ Failed to add email template');
        }
    }
}