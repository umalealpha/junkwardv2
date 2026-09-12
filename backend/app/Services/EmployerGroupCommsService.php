<?php

namespace AlphaDirect\Services;

use AlphaDirect\Customer;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Events\SendMail;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\Models\AdGroupKycCampaign;
use AlphaDirect\Models\AdGroupKycLink;
use AlphaDirect\Models\EmployerGroup;
use AlphaDirect\Models\HrUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Employer-group communications: HR portal credentials + onboarding email.
 * Extracted from the legacy Blade EmployerGroupController (whose methods
 * were private and unreachable in V2 behind the blocked V1 admin) so the
 * Api/V1 controller stays thin.
 *
 * Responses never carry passwords or OTP codes — HR access is a signed
 * 48h set-password link only.
 */
class EmployerGroupCommsService
{
    /**
     * Send HR portal credentials to every address in the group's HR email
     * list. Finds-or-creates the HrUser per address (V8 behavior — kept,
     * but surfaced via created_hr_users so it's no longer silent).
     *
     * @return array{sent_emails: string[], failed_emails: array<array{email:string,error:string}>, created_hr_users: int}
     */
    public function sendHrCredentials(EmployerGroup $employerGroup): array
    {
        $hrEmails = $employerGroup->hr_emails;
        if (empty($hrEmails)) {
            throw new \InvalidArgumentException('No HR emails found for this employer group.');
        }

        $sent = [];
        $failed = [];
        $created = 0;

        foreach ($hrEmails as $email) {
            try {
                $hrUser = HrUser::where('email', $email)->first();
                if (!$hrUser) {
                    $hrUser = HrUser::create([
                        'email' => $email,
                        'first_name' => 'HR',
                        'last_name' => 'User',
                        'password' => null, // set via the signed link
                        'employer_group_id' => $employerGroup->id,
                        'is_active' => true,
                    ]);
                    $created++;
                }

                $this->sendHrAccessEmail($employerGroup, $hrUser, $email);
                $sent[] = $email;
            } catch (\Exception $e) {
                Log::error('HR credentials email failed', [
                    'email' => $email,
                    'employer_group_id' => $employerGroup->id,
                    'error' => $e->getMessage(),
                ]);
                $failed[] = ['email' => $email, 'error' => $e->getMessage()];
            }
        }

        return ['sent_emails' => $sent, 'failed_emails' => $failed, 'created_hr_users' => $created];
    }

    /**
     * Send the onboarding email to the group's contact, including the
     * employer-group KYC link (find-or-create campaign + link).
     *
     * @return array{contact_email: string, onboarding_link: string, kyc_link: ?string, kyc_link_expiry_days: string}
     */
    public function sendOnboardingEmail(EmployerGroup $employerGroup): array
    {
        if (empty($employerGroup->contact_email)) {
            throw new \InvalidArgumentException('No contact email found for this employer group.');
        }

        $onboardingLink = env('START_URL') . 'employer-group/onboarding/' . base64_encode($employerGroup->id);

        $kycLink = $this->findOrCreateKycLink($employerGroup);
        $kycLinkUrl = $kycLink?->getAccessUrl();
        $expiryDays = ($kycLink && $kycLink->expires_at)
            ? $kycLink->expires_at->diffInDays(Carbon::now()) . ' days'
            : '30 days';

        $template = EmailBroadcasting::where('hook_slug', 'employer_group_onboarding')->first();

        if ($template) {
            $emailData = new \stdClass();
            $emailData->user_id = null;
            $emailData->customer_id = null;
            $emailData->hook = 'employer_group_onboarding';
            $emailData->email = $employerGroup->contact_email;
            $emailData->attachment = null;
            $emailData->employer_group_name = $employerGroup->name;
            $emailData->employer_group_id = $employerGroup->employer_group_id;
            $emailData->contact_name = $employerGroup->contact_name;
            $emailData->onboarding_link = $onboardingLink;
            $emailData->kyc_link = $kycLinkUrl;
            $emailData->kyc_link_expiry_days = $expiryDays;
            $emailData->contact_email = $employerGroup->contact_email;

            $markdown = new MailTemplate($emailData);
            $html = $markdown->render('Mail.mailTemplate', ['data' => $emailData]);
            event(new SendMail($employerGroup->contact_email, $template->subject, '', $html, null, ['hook' => 'employer_group_onboarding']));
        } else {
            // Template row missing in this environment — plain inline HTML
            // fallback (legacy behavior) so the button still works.
            $this->sendFallbackOnboardingEmail($employerGroup, $onboardingLink, $kycLinkUrl, $expiryDays);
        }

        return [
            'contact_email' => $employerGroup->contact_email,
            'onboarding_link' => $onboardingLink,
            'kyc_link' => $kycLinkUrl,
            'kyc_link_expiry_days' => $expiryDays,
        ];
    }

    // ──────────────────────────────────────────────────────────────

    private function sendHrAccessEmail(EmployerGroup $employerGroup, HrUser $hrUser, string $email): void
    {
        $expires = now()->addHours(48)->timestamp;
        $signature = hash_hmac('sha256', $email . '|' . $expires, config('app.key'));
        $resetLink = url('/hr/set-password?email=' . base64_encode($email) . '&expires=' . $expires . '&signature=' . $signature);
        $loginLink = url('/hr/login');

        $template = EmailBroadcasting::where('hook_slug', 'hr_login_new')->first();
        if (!$template) {
            throw new \Exception("Email template not found for hook: hr_login_new (run add:employer-group-email-templates)");
        }

        $emailData = new \stdClass();
        $emailData->user_id = $hrUser->id;
        $emailData->customer_id = null;
        $emailData->hook = 'hr_login_new';
        $emailData->email = $email;
        $emailData->attachment = null;
        $emailData->employer_group_name = $employerGroup->name;
        $emailData->hr_reset_link = $resetLink;
        $emailData->hr_login_link = $loginLink;
        $emailData->hr_email = $email;

        $markdown = new MailTemplate($emailData);
        $html = $markdown->render('Mail.mailTemplate', ['data' => $emailData]);
        event(new SendMail($email, $template->subject, '', $html, null, ['hook' => 'hr_login_new']));
    }

    /**
     * Employer-group KYC link: active campaign for the group's short code
     * (created if absent), a Customer for the contact email (created if
     * absent), and a non-expired policy-less AdGroupKycLink (reused when
     * one already exists). Returns null only when link creation fails —
     * the onboarding email still goes out without the KYC section.
     */
    private function findOrCreateKycLink(EmployerGroup $employerGroup): ?AdGroupKycLink
    {
        try {
            $campaign = AdGroupKycCampaign::where('employer_group_id', $employerGroup->employer_group_id)
                ->where('status', 'active')
                ->first();

            if (!$campaign) {
                $campaign = app(AdGroupKycService::class)->createDefaultAdGroupCampaign(
                    $employerGroup->employer_group_id,
                    $employerGroup->name
                );
            }

            $customer = Customer::where('email', $employerGroup->contact_email)->first();
            if (!$customer) {
                $nameParts = array_filter(explode(' ', trim((string) $employerGroup->contact_name)));
                $customer = Customer::create([
                    'firstName' => $nameParts[0] ?? 'Employer',
                    'lastName' => count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : 'Group',
                    'email' => $employerGroup->contact_email,
                    'cellphone' => $employerGroup->contact_phone ?? null,
                ]);
            }

            $existing = AdGroupKycLink::where('campaign_id', $campaign->id)
                ->where('customer_id', $customer->id)
                ->whereNull('policy_id')
                ->first();

            if ($existing && !$existing->isExpired()) {
                return $existing;
            }

            return AdGroupKycLink::create([
                'campaign_id' => $campaign->id,
                'customer_id' => $customer->id,
                'policy_id' => null,
                'unique_token' => Str::random(12),
                'otp_code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                'status' => 'pending',
                'expires_at' => Carbon::now()->addDays(30),
            ]);
        } catch (\Exception $e) {
            Log::error('Employer group KYC link generation failed', [
                'employer_group_id' => $employerGroup->employer_group_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function sendFallbackOnboardingEmail(EmployerGroup $employerGroup, string $onboardingLink, ?string $kycLinkUrl, string $expiryDays): void
    {
        $subject = 'Welcome to Alpha Direct — Employer Group Onboarding';
        $kycSection = $kycLinkUrl ? "
            <p><strong>KYC Verification Required:</strong></p>
            <p><a href='{$kycLinkUrl}'>Complete the KYC form</a> (link expires in {$expiryDays}).</p>
            <p>Or copy this link: {$kycLinkUrl}</p>" : '';

        $html = "
            <html><body>
                <h2>Welcome to Alpha Direct</h2>
                <p>Dear {$employerGroup->contact_name},</p>
                <p>Thank you for registering <strong>{$employerGroup->name}</strong> with Alpha Direct.</p>
                <p>Your Employer Group ID is: <strong>{$employerGroup->employer_group_id}</strong></p>
                <p><a href='{$onboardingLink}'>Complete your onboarding</a></p>
                <p>Or copy this link: {$onboardingLink}</p>
                {$kycSection}
                <p>Regards,<br>Alpha Direct Insurance</p>
            </body></html>";

        event(new SendMail($employerGroup->contact_email, $subject, '', $html, null, ['hook' => 'employer_group_onboarding_fallback']));
    }
}
