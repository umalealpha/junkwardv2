<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Models\EmployerGroup;
use AlphaDirect\Models\HrUser;
use AlphaDirect\Models\AdGroupKycLink;
use AlphaDirect\Models\AdGroupKycCampaign;
use AlphaDirect\Customer;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use AlphaDirect\User;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Mail\MailTemplate;
use Carbon\Carbon;

class EmployerGroupController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = EmployerGroup::latest()->get();
            return DataTables::of($data)
                ->addColumn('hr_emails', function($row){
                    $emails = [];
                    if (!empty($row->bulk_emails)) {
                        // Handle comma-separated string format
                        if (is_string($row->bulk_emails)) {
                            // First try to decode as JSON
                            $decoded = json_decode($row->bulk_emails, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $emails = $decoded;
                            } else {
                                // If not JSON, treat as comma-separated string
                                $emails = array_filter(array_map('trim', explode(',', $row->bulk_emails)));
                            }
                        } else {
                            $emails = $row->bulk_emails;
                        }
                    }
                    return count($emails) . ' email(s)';
                })
                ->addColumn('action', function($row){
                    $btn = '<div class="btn-group" role="group">';
                    $btn .= '<a href="'.route('admin.employer-groups.show', $row->id).'" class="btn btn-sm btn-primary" title="View Employer Group Details">';
                    $btn .= '<i class="fa fa-eye"></i> View';
                    $btn .= '</a>';

                    // $btn .= '<a href="'.route('admin.employer-groups.edit', $row->id).'" class="btn btn-sm btn-primary" title="Edit Employer Group">';
                    // $btn .= '<i class="fa fa-edit"></i> Edit';
                    // $btn .= '</a>';

                    $btn .= '<button type="button" class="btn btn-sm btn-info" onclick="sendOnboardingEmail('.$row->id.')" title="Send Onboarding Email">';
                    $btn .= '<i class="fa fa-paper-plane"></i> Send Onboarding';
                    $btn .= '</button>';

                    $btn .= '<button type="button" class="btn btn-sm btn-success" onclick="sendHrCredentials('.$row->id.')" title="Send HR Login Credentials">';
                    $btn .= '<i class="fa fa-envelope"></i> Send HR Access';
                    $btn .= '</button>';

                    // $btn .= '<button type="button" class="btn btn-sm btn-danger" onclick="deleteEmployerGroup('.$row->id.')" title="Delete Employer Group">';
                    // $btn .= '<i class="fa fa-trash"></i> Delete';
                    // $btn .= '</button>';
                    $btn .= '</div>';
                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('employerGroups.index');
    }

    public function create()
    {
        return view('employerGroups.create');
    }

    public function show($id)
    {
        $employerGroup = EmployerGroup::findOrFail($id);
        
        // Fetch KYC submission for this employer group
        $kycSubmission = \AlphaDirect\Models\AdGroupKycSubmission::where('employer_group_id', $employerGroup->id)
            ->with(['directors', 'shareholders', 'documents'])
            ->latest()
            ->first();

        return view('employerGroups.show', compact('employerGroup', 'kycSubmission'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'industry' => 'nullable|string',
            'address' => 'nullable|string',
            'town' => 'nullable|string',
            'postal_code' => 'nullable',
            'contact_name' => 'nullable|string',
            'contact_phone' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'broker' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'status' => 'nullable|string',
            'no_of_employees' => 'nullable|integer',
            'bulk_emails' => 'nullable|string',
        ]);

        // Generate unique employer group code
        $employerGroupId = $this->generateUniqueEmployerGroupCode();

        $employerGroup = EmployerGroup::create([
            'employer_group_id' => $employerGroupId,
            'name' => $request->name,
            'industry' => $request->industry,
            'address' => $request->address,
            'town' => $request->town,
            'postal_code' => $request->postal_code,
            'contact_name' => $request->contact_name,
            'contact_phone' => $request->contact_phone,
            'contact_email' => $request->contact_email,
            'broker' => $request->broker,
            'payment_method' => $request->payment_method,
            'status' => $request->status,
            'no_of_employees' => $request->no_of_employees,
            'bulk_emails' => $request->bulk_emails, // store as JSON
        ]);

        return redirect()->route('admin.employer-groups.index')->with('success','Employer Group created successfully with ID: ' . $employerGroupId);
    }

    public function edit($id)
    {
        $employerGroup = EmployerGroup::findOrFail($id);
        return view('employerGroups.edit', compact('employerGroup'));
    }

    public function update(Request $request, EmployerGroup $employerGroup)
    {
        $request->validate([
            'name' => 'required',
            'industry' => 'nullable|string',
            'address' => 'nullable|string',
            'town' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'contact_name' => 'nullable|string',
            'contact_phone' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'broker' => 'nullable|string',
            // 'primary_agent_name' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'status' => 'nullable|string',
            'no_of_employees' => 'nullable|integer',
            'bulk_emails' => 'nullable|string', // added validation
        ]);

        $employerGroup->update([
            'name' => $request->name,
            'industry' => $request->industry,
            'address' => $request->address,
            'town' => $request->town,
            'postal_code' => $request->postal_code,
            'contact_name' => $request->contact_name,
            'contact_phone' => $request->contact_phone,
            'contact_email' => $request->contact_email,
            'broker' => $request->broker,
            // 'primary_agent_name' => $request->primary_agent_name,
            'payment_method' => $request->payment_method,
            'status' => $request->status,
            'no_of_employees' => $request->no_of_employees,
            'bulk_emails' => $request->bulk_emails, // store as JSON
        ]);

        return redirect()->route('admin.employer-groups.index')->with('success','Employer Group updated successfully.');
    }

    public function destroy($id)
    {
        try {
            $employerGroup = EmployerGroup::findOrFail($id);
            $employerGroup->delete();
            return redirect()->route('admin.employer-groups.index')->with('success','Employer Group deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.employer-groups.index')->with('error','Error deleting employer group: ' . $e->getMessage());
        }
    }

    /**
     * Generate a unique employer group code automatically
     * Completely random alphanumeric code with variable length
     */
    private function generateUniqueEmployerGroupCode()
    {
        do {
            // Generate random length between 6-10 characters
            $length = rand(6, 10);

            // Generate completely random alphanumeric string using only capital letters
            $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $employerGroupId = '';

            for ($i = 0; $i < $length; $i++) {
                $employerGroupId .= $characters[rand(0, strlen($characters) - 1)];
            }

            // Check if this code already exists
            $exists = EmployerGroup::where('employer_group_id', $employerGroupId)->exists();
        } while ($exists);

        return $employerGroupId;
    }

    /**
     * Send HR login credentials to all HR emails for an employer group
     */
    public function sendHrCredentials($id)
    {
        try {
            $employerGroup = EmployerGroup::findOrFail($id);

            // Parse bulk_emails - handle both JSON and comma-separated string formats
            $hrEmails = [];
            if (!empty($employerGroup->bulk_emails)) {
                if (is_string($employerGroup->bulk_emails)) {
                    // First try to decode as JSON
                    $decoded = json_decode($employerGroup->bulk_emails, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $hrEmails = $decoded;
                    } else {
                        // If not JSON, treat as comma-separated string
                        $hrEmails = array_filter(array_map('trim', explode(',', $employerGroup->bulk_emails)));
                    }
                } else {
                    $hrEmails = $employerGroup->bulk_emails;
                }
            }

            if (empty($hrEmails)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No HR emails found for this employer group.'
                ], 400);
            }

            $sentEmails = [];
            $failedEmails = [];

            foreach ($hrEmails as $email) {
                try {
                    // Check if HR user already exists
                    $existingHrUser = HrUser::where('email', $email)->first();

                    if ($existingHrUser) {
                        // HR user exists, send password reset link
                        $expires = now()->addHours(48)->timestamp;
                        $signature = hash_hmac('sha256', $email.'|'.$expires, config('app.key'));
                        $resetLink = url('/hr/set-password?email=' . base64_encode($email) . '&expires=' . $expires . '&signature=' . $signature);
                        $loginLink = url('/hr/login');

                        try {
                            // Use existing email system - following ReKYC pattern
                            $emailData = new \stdClass();
                            $emailData->user_id = $existingHrUser->id;
                            $emailData->customer_id = null;
                            $emailData->hook = 'hr_login_new';
                            $emailData->email = $email;
                            $emailData->attachment = null;

                            // Add HR-specific data
                            $emailData->employer_group_name = $employerGroup->name;
                            $emailData->hr_reset_link = $resetLink;
                            $emailData->hr_login_link = $loginLink;
                            $emailData->hr_email = $email;

                            $emailTemplate = EmailBroadcasting::where('hook_slug', $emailData->hook)->first();
                            if (!$emailTemplate) {
                                \Log::error('HR Email Template Not Found', [
                                    'hook' => $emailData->hook,
                                    'available_templates' => EmailBroadcasting::pluck('hook_slug')->toArray()
                                ]);
                                throw new \Exception('Email template not found for hook: ' . $emailData->hook);
                            }

                            $markdown = new MailTemplate($emailData);
                            $html = $markdown->render('Mail.mailTemplate', ['data' => $emailData]);
                            event(new \AlphaDirect\Events\SendMail($email, $emailTemplate->subject, "", $html, null, ['hook' => $emailData->hook]));

                        } catch (\Exception $mailException) {
                            // Log the specific mail error
                            \Log::error('HR Email Send Error', [
                                'email' => $email,
                                'error' => $mailException->getMessage()
                            ]);

                            // If email system fails, try to save to log as fallback
                            \Log::info('HR Email Content (Email System Failed)', [
                                'email' => $email,
                                'employer_group' => $employerGroup->name,
                                'reset_link' => $resetLink,
                                'login_link' => $loginLink
                            ]);

                            throw $mailException;
                        }

                        $sentEmails[] = $email;
                    } else {
                        // Create new HR user
                        $hrUser = HrUser::create([
                            'email' => $email,
                            'first_name' => 'HR',
                            'last_name' => 'User',
                            'password' => null, // Will be set when they reset password
                            'employer_group_id' => $employerGroup->id,
                            'is_active' => true
                        ]);
                        
                        $expires = now()->addHours(48)->timestamp;
                        $signature = hash_hmac('sha256', $email.'|'.$expires, config('app.key'));
                        $resetLink = url('/hr/set-password?email=' . base64_encode($email) . '&expires=' . $expires . '&signature=' . $signature);
                        $loginLink = url('/hr/login');

                        try {
                            // Use existing email system - following ReKYC pattern
                            $emailData = new \stdClass();
                            $emailData->user_id = $hrUser->id;
                            $emailData->customer_id = null;
                            $emailData->hook = 'hr_login_new';
                            $emailData->email = $email;
                            $emailData->attachment = null;

                            // Add HR-specific data
                            $emailData->employer_group_name = $employerGroup->name;
                            $emailData->hr_reset_link = $resetLink;
                            $emailData->hr_login_link = $loginLink;
                            $emailData->hr_email = $email;

                            $emailTemplate = EmailBroadcasting::where('hook_slug', $emailData->hook)->first();
                            if (!$emailTemplate) {
                                \Log::error('HR Email Template Not Found', [
                                    'hook' => $emailData->hook,
                                    'available_templates' => EmailBroadcasting::pluck('hook_slug')->toArray()
                                ]);
                                throw new \Exception('Email template not found for hook: ' . $emailData->hook);
                            }

                            $markdown = new MailTemplate($emailData);
                            $html = $markdown->render('Mail.mailTemplate', ['data' => $emailData]);
                            event(new \AlphaDirect\Events\SendMail($email, $emailTemplate->subject, "", $html, null, ['hook' => $emailData->hook]));

                        } catch (\Exception $mailException) {
                            // Log the specific mail error
                            \Log::error('HR Email Send Error', [
                                'email' => $email,
                                'error' => $mailException->getMessage()
                            ]);

                            // If email system fails, try to save to log as fallback
                            \Log::info('HR Email Content (Email System Failed)', [
                                'email' => $email,
                                'employer_group' => $employerGroup->name,
                                'reset_link' => $resetLink,
                                'login_link' => $loginLink
                            ]);

                            throw $mailException;
                        }

                        $sentEmails[] = $email;
                    }
                } catch (\Exception $e) {
                    \Log::error('HR Email Send Failed', [
                        'email' => $email,
                        'employer_group_id' => $employerGroup->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    $failedEmails[] = [
                        'email' => $email,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $message = 'HR credentials sent to ' . count($sentEmails) . ' email(s)';
            if (!empty($failedEmails)) {
                $message .= '. Failed to send to ' . count($failedEmails) . ' email(s)';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'sent_emails' => $sentEmails,
                'failed_emails' => $failedEmails
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending HR credentials: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send onboarding email to employer group contact (called via button click)
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendOnboardingEmail($id)
    {
        try {
            $employerGroup = EmployerGroup::findOrFail($id);
            
            if (empty($employerGroup->contact_email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No contact email found for this employer group.'
                ], 400);
            }

            // Send the onboarding email
            $this->sendOnboardingEmailToContact($employerGroup);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding email sent successfully to ' . $employerGroup->contact_email,
                'data' => [
                    'employer_group_id' => $employerGroup->employer_group_id,
                    'employer_group_name' => $employerGroup->name,
                    'contact_email' => $employerGroup->contact_email,
                    'onboarding_link' => env('START_URL') . 'employer-group/onboarding/' . base64_encode($employerGroup->id)
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Send Onboarding Email Button Click Failed', [
                'employer_group_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send onboarding email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send onboarding email to employer group contact
     *
     * @param  \AlphaDirect\Models\EmployerGroup  $employerGroup
     * @return void
     */
    private function sendOnboardingEmailToContact($employerGroup)
    {
        try {
            if (empty($employerGroup->contact_email)) {
                \Log::warning('No contact email found for employer group', [
                    'employer_group_id' => $employerGroup->id,
                    'employer_group_name' => $employerGroup->name
                ]);
                return;
            }

            // Generate onboarding link with encoded employer ID
            $encodedEmployerId = base64_encode($employerGroup->id);
            $onboardingLink = env('START_URL') . 'employer-group/onboarding/' . $encodedEmployerId;

            // Generate and store KYC link for employer group (same as employee KYC links)
            $kycLinkResult = $this->generateEmployerGroupKycLink($employerGroup);
            $kycLinkUrl = is_array($kycLinkResult) ? $kycLinkResult['url'] : $kycLinkResult;
            $kycLinkObject = is_array($kycLinkResult) ? $kycLinkResult['link'] : null;

            // Calculate expiry days (same format as AD Group KYC email)
            $expiryDays = '30 days'; // Default
            if ($kycLinkObject && $kycLinkObject->expires_at) {
                $expiryDays = $kycLinkObject->expires_at->diffInDays(Carbon::now()) . ' days';
            }

            // Use existing email system - following HR email pattern
            $emailData = new \stdClass();
            $emailData->user_id = null;
            $emailData->customer_id = null;
            $emailData->hook = 'employer_group_onboarding';
            $emailData->email = $employerGroup->contact_email;
            $emailData->attachment = null;

            // Add employer group specific data
            $emailData->employer_group_name = $employerGroup->name;
            $emailData->employer_group_id = $employerGroup->employer_group_id;
            $emailData->contact_name = $employerGroup->contact_name;
            $emailData->onboarding_link = $onboardingLink;
            $emailData->kyc_link = $kycLinkUrl;
            $emailData->kyc_link_expiry_days = $expiryDays;
            $emailData->contact_email = $employerGroup->contact_email;

            $emailTemplate = EmailBroadcasting::where('hook_slug', $emailData->hook)->first();
            if (!$emailTemplate) {
                \Log::error('Employer Group Onboarding Email Template Not Found', [
                    'hook' => $emailData->hook,
                    'available_templates' => EmailBroadcasting::pluck('hook_slug')->toArray()
                ]);
                
                // Fallback: Send a simple email without template
                $this->sendFallbackOnboardingEmail($employerGroup, $onboardingLink);
                return;
            }

            $markdown = new MailTemplate($emailData);
            $html = $markdown->render('Mail.mailTemplate', ['data' => $emailData]);
            event(new \AlphaDirect\Events\SendMail($employerGroup->contact_email, $emailTemplate->subject, "", $html, null, ['hook' => $emailData->hook]));

            \Log::info('Employer Group Onboarding Email Sent', [
                'employer_group_id' => $employerGroup->id,
                'employer_group_name' => $employerGroup->name,
                'contact_email' => $employerGroup->contact_email,
                'onboarding_link' => $onboardingLink,
                'kyc_link' => $kycLinkUrl,
                'kyc_link_expiry_days' => $expiryDays
            ]);

        } catch (\Exception $e) {
            \Log::error('Employer Group Onboarding Email Send Failed', [
                'employer_group_id' => $employerGroup->id,
                'employer_group_name' => $employerGroup->name,
                'contact_email' => $employerGroup->contact_email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback: Send a simple email without template
            $onboardingLink = $onboardingLink ?? env('START_URL') . 'employer-group/onboarding/' . base64_encode($employerGroup->id);
            $this->sendFallbackOnboardingEmail($employerGroup, $onboardingLink);
        }
    }

    /**
     * Send fallback onboarding email when template is not available
     *
     * @param  \AlphaDirect\Models\EmployerGroup  $employerGroup
     * @param  string  $onboardingLink
     * @return void
     */
    private function sendFallbackOnboardingEmail($employerGroup, $onboardingLink)
    {
        try {
            // Generate and store KYC link for employer group
            $kycLinkResult = $this->generateEmployerGroupKycLink($employerGroup);
            $kycLinkUrl = is_array($kycLinkResult) ? $kycLinkResult['url'] : $kycLinkResult;
            $kycLinkObject = is_array($kycLinkResult) ? $kycLinkResult['link'] : null;

            // Calculate expiry days (same format as AD Group KYC email)
            $expiryDays = '30 days'; // Default
            if ($kycLinkObject && $kycLinkObject->expires_at) {
                $expiryDays = $kycLinkObject->expires_at->diffInDays(Carbon::now()) . ' days';
            }

            $subject = 'Welcome to Alpha Direct Health - Employer Group Onboarding';
            $html = "
                <html>
                <body>
                    <h2>Welcome to Alpha Direct Health!</h2>
                    <p>Dear {$employerGroup->contact_name},</p>
                    <p>Thank you for registering your employer group <strong>{$employerGroup->name}</strong> with Alpha Direct Health.</p>
                    <p>Your Employer Group ID is: <strong>{$employerGroup->employer_group_id}</strong></p>
                    <p>To complete your onboarding process, please click on the link below:</p>
                    <p><a href='{$onboardingLink}' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Complete Onboarding</a></p>
                    <p>Or copy and paste this link in your browser: {$onboardingLink}</p>
                    <br>
                    <p><strong>KYC Verification Required:</strong></p>
                    <p>Please complete the KYC form for your employer group:</p>
                    <p><a href='{$kycLinkUrl}' style='background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Complete KYC Form</a></p>
                    <p>Or copy and paste this link in your browser: {$kycLinkUrl}</p>
                    <p style='background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;'><strong>Important:</strong> This KYC verification link will expire in <strong>{$expiryDays}</strong>. Please complete your KYC verification as soon as possible.</p>
                    <p>If you have any questions, please contact our support team.</p>
                    <p>Best regards,<br>Alpha Direct Health Team</p>
                </body>
                </html>
            ";

            event(new \AlphaDirect\Events\SendMail($employerGroup->contact_email, $subject, "", $html, null, ['hook' => 'employer_group_onboarding_fallback']));

            \Log::info('Fallback Employer Group Onboarding Email Sent', [
                'employer_group_id' => $employerGroup->id,
                'contact_email' => $employerGroup->contact_email,
                'onboarding_link' => $onboardingLink,
                'kyc_link' => $kycLinkUrl,
                'kyc_link_expiry_days' => $expiryDays
            ]);

        } catch (\Exception $e) {
            \Log::error('Fallback Employer Group Onboarding Email Failed', [
                'employer_group_id' => $employerGroup->id,
                'contact_email' => $employerGroup->contact_email,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate and store KYC link for employer group (same format as employee KYC links)
     *
     * @param  \AlphaDirect\Models\EmployerGroup  $employerGroup
     * @return array|string  Returns array with 'url' and 'link' keys, or string URL as fallback
     */
    private function generateEmployerGroupKycLink($employerGroup)
    {
        try {
            // Get or create campaign for employer group
            $campaign = AdGroupKycCampaign::where('employer_group_id', $employerGroup->employer_group_id)
                ->where('status', 'active')
                ->first();

            if (!$campaign) {
                // Create a campaign for employer group KYC
                $kycService = app(\AlphaDirect\Services\AdGroupKycService::class);
                $campaign = $kycService->createDefaultAdGroupCampaign(
                    $employerGroup->employer_group_id,
                    $employerGroup->name
                );
            }

            // Find or create customer for contact email (required for KYC link)
            $customer = Customer::where('email', $employerGroup->contact_email)->first();
            
            if (!$customer) {
                $customer = Customer::create([
                    'firstName' => $employerGroup->contact_name ? explode(' ', $employerGroup->contact_name)[0] : 'Employer',
                    'lastName' => $employerGroup->contact_name ? implode(' ', array_slice(explode(' ', $employerGroup->contact_name), 1)) : 'Group',
                    'email' => $employerGroup->contact_email,
                    'cellphone' => $employerGroup->contact_phone ?? null,
                ]);
            }

            // Check if KYC link already exists for this employer group (by campaign and customer, no policy)
            $existingLink = AdGroupKycLink::whereHas('campaign', function($query) use ($employerGroup) {
                $query->where('employer_group_id', $employerGroup->employer_group_id);
            })
            ->where('customer_id', $customer->id)
            ->whereNull('policy_id') // Employer group links have no policy
            ->first();

            if ($existingLink && !$existingLink->isExpired()) {
                \Log::info('Employer Group KYC link already exists, using existing unique_token', [
                    'employer_group_id' => $employerGroup->employer_group_id,
                    'link_id' => $existingLink->id,
                    'unique_token' => $existingLink->unique_token
                ]);
                // Return array with URL and link object for expiry calculation
                return [
                    'url' => $existingLink->getAccessUrl(),
                    'link' => $existingLink
                ];
            }

            // Create new KYC link for employer group with unique_token
            $kycLink = AdGroupKycLink::create([
                'campaign_id' => $campaign->id,
                'customer_id' => $customer->id,
                'policy_id' => null, // No policy for employer group KYC
                'unique_token' => Str::random(12), // Will be auto-generated if empty due to boot()
                'otp_code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                'status' => 'pending',
                'expires_at' => Carbon::now()->addDays(30),
            ]);

            // Refresh to get the unique_token that was generated
            $kycLink->refresh();

            \Log::info('Employer Group KYC link created with unique_token', [
                'employer_group_id' => $employerGroup->employer_group_id,
                'link_id' => $kycLink->id,
                'unique_token' => $kycLink->unique_token,
                'kyc_url' => $kycLink->getAccessUrl()
            ]);

            // Return array with URL and link object for expiry calculation
            return [
                'url' => $kycLink->getAccessUrl(),
                'link' => $kycLink
            ];

        } catch (\Exception $e) {
            \Log::error('Failed to generate Employer Group KYC link', [
                'employer_group_id' => $employerGroup->employer_group_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback: Return a basic URL if link creation fails
            return env('START_URL') . 'adgroupkyc/employer-group/' . base64_encode($employerGroup->id);
        }
    }
}
