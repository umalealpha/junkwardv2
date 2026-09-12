<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\AdGroupKycCampaign;
use AlphaDirect\Models\AdGroupKycLink;
use AlphaDirect\Models\AdGroupKycActivity;
use AlphaDirect\Models\EmployerGroup;
use AlphaDirect\Services\AdGroupKycService;
use AlphaDirect\Services\AdGroupKycNotificationService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdGroupKycAdminController extends Controller
{
    protected $kycService;
    protected $notificationService;

    public function __construct(
        AdGroupKycService $kycService,
        AdGroupKycNotificationService $notificationService
    ) {
        $this->kycService = $kycService;
        $this->notificationService = $notificationService;
    }

    /**
     * Display AD Group KYC admin dashboard
     */
    public function index(Request $request)
    {
        try {
            $campaigns = AdGroupKycCampaign::withCount(['links', 'activities'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            // Add completion rate for each campaign
            $campaigns->getCollection()->transform(function ($campaign) {
                $completedLinks = $campaign->links()->where('status', 'completed')->count();
                $totalLinks = $campaign->links_count;
                $campaign->completion_rate = $totalLinks > 0 ? round(($completedLinks / $totalLinks) * 100, 1) : 0;
                return $campaign;
            });

            $stats = $this->getDashboardStats();
            $recentActivities = $this->getRecentActivities(10);
            $employerGroups = EmployerGroup::select('employer_group_id', 'name')->get();

            return view('admin.ad-group-kyc.index', compact('campaigns', 'stats', 'recentActivities', 'employerGroups'));

        } catch (\Exception $e) {
            Log::error('AD Group KYC admin dashboard error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load AD Group KYC dashboard');
        }
    }

    /**
     * Show campaign details
     */
    public function showCampaign($id)
    {
        try {
            $campaign = AdGroupKycCampaign::with(['links.customer', 'links.policy', 'activities'])
                ->findOrFail($id);

            $stats = $this->kycService->getCampaignStats($campaign);

            return view('admin.ad-group-kyc.campaign.show', compact('campaign', 'stats'));

        } catch (\Exception $e) {
            Log::error('AD Group KYC campaign show error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load campaign details');
        }
    }

    /**
     * Get campaign links data for DataTable - Shows all employees from employer group
     */
    public function getCampaignLinksData(Request $request, $id)
    {
        try {
            $draw = $request->get('draw');
            $start = $request->get("start");
            $rowperpage = $request->get("length");

            $columnIndex_arr = $request->get('order');
            $columnName_arr = $request->get('columns');
            $order_arr = $request->get('order');
            $search_arr = $request->get('search');

            $columnIndex = $columnIndex_arr[0]['column'];
            $columnName = $columnName_arr[$columnIndex]['data'];
            $columnSortOrder = $order_arr[0]['dir'];
            $searchValue = $search_arr['value'];

            // Get the campaign to find the employer group
            $campaign = AdGroupKycCampaign::findOrFail($id);
            $employerGroupId = $campaign->employer_group_id;

            // Get all employees from the employer group through policies
            $baseQuery = \DB::table('employer_group_policy as egp')
                ->join('policies as p', 'egp.policy_id', '=', 'p.id')
                ->join('customer as c', 'p.customer_id', '=', 'c.id')
                ->leftJoin('customer_profile as cp', 'c.id', '=', 'cp.customer_id')
                ->leftJoin('ad_group_kyc_links as kyc', function($join) use ($id) {
                    $join->on('p.id', '=', 'kyc.policy_id')
                         ->where('kyc.campaign_id', '=', $id);
                })
                ->where('egp.employer_group_id', $employerGroupId)
                ->where('p.product_id', 12) // AD Group product
                ->select([
                    'egp.employee_id',
                    'egp.policy_id',
                    'p.policyNumber as policy_number',
                    'c.id as customer_id',
                    'c.firstName',
                    'c.lastName',
                    'c.email',
                    'c.cellphone',
                    'cp.dob',
                    'cp.gender',
                    'cp.omang',
                    'cp.passport',
                    'cp.address',
                    'kyc.id as kyc_link_id',
                    'kyc.status as kyc_status',
                    'kyc.otp_code',
                    'kyc.sent_at',
                    'kyc.completed_at',
                    'kyc.expires_at',
                    \DB::raw('CONCAT(c.firstName, " ", c.lastName) as employee_name')
                ]);

            // Total records
            $totalRecords = $baseQuery->count();

            // Apply search functionality
            if ($searchValue != null) {
                $baseQuery->where(function($query) use ($searchValue) {
                    $query->where('c.firstName', 'like', '%' . $searchValue . '%')
                          ->orWhere('c.lastName', 'like', '%' . $searchValue . '%')
                          ->orWhere('c.email', 'like', '%' . $searchValue . '%')
                          ->orWhere('c.cellphone', 'like', '%' . $searchValue . '%')
                          ->orWhere('p.policyNumber', 'like', '%' . $searchValue . '%')
                          ->orWhere('egp.employee_id', 'like', '%' . $searchValue . '%')
                          ->orWhere('kyc.status', 'like', '%' . $searchValue . '%');
                });
            }

            $totalRecordswithFilter = $baseQuery->count();

            // Apply ordering and pagination
            $records = $baseQuery->orderBy($columnName, $columnSortOrder)
                ->skip($start)
                ->take($rowperpage)
                ->get();

            $data_arr = [];
            foreach ($records as $record) {
                // Determine KYC status
                $kycStatus = 'Not Generated';
                $kycStatusBadge = '<span class="badge badge-secondary">Not Generated</span>';
                
                if ($record->kyc_link_id) {
                    $kycStatus = $record->kyc_status;
                    $kycStatusBadge = $this->getStatusBadge($record->kyc_status);
                }

                // Generate KYC URL if link exists
                $kycUrl = '';
                if ($record->kyc_link_id) {
                    // Get the unique token for the KYC link
                    $kycLink = \AlphaDirect\Models\AdGroupKycLink::find($record->kyc_link_id);
                    if ($kycLink && $kycLink->unique_token) {
                        $kycUrl = '<a href="' . url('/ad-group-kyc/verify/' . $kycLink->unique_token) . '" target="_blank" class="btn btn-sm btn-info">View Link</a>';
                    } else {
                        $kycUrl = '<span class="text-muted">No Token</span>';
                    }
                } else {
                    $kycUrl = '<span class="text-muted">No Link</span>';
                }

                // Format date of birth
                $dob = 'N/A';
                if ($record->dob) {
                    $dob = \Carbon\Carbon::parse($record->dob)->format('Y-m-d');
                }

                // Format gender
                $gender = 'N/A';
                if ($record->gender !== null) {
                    $gender = $record->gender ? 'Male' : 'Female';
                }

                // Format ID number (OMANG or Passport)
                $idNumber = 'N/A';
                if ($record->omang) {
                    $idNumber = 'OMANG: ' . $record->omang;
                } elseif ($record->passport) {
                    $idNumber = 'Passport: ' . $record->passport;
                }

                $data_arr[] = [
                    'id' => $record->kyc_link_id ?: $record->policy_id, // Use KYC link ID if exists, otherwise policy ID
                    'employee_id' => $record->employee_id,
                    'employee_name' => $record->employee_name,
                    'employee_email' => $record->email ?: 'N/A',
                    'employee_phone' => $record->cellphone ?: 'N/A',
                    'policy_number' => $record->policy_number,
                    'date_of_birth' => $dob,
                    'gender' => $gender,
                    'id_number' => $idNumber,
                    'status' => $kycStatusBadge,
                    'otp_code' => $record->otp_code ?: 'N/A',
                    'kyc_url' => $kycUrl,
                    'sent_at' => $record->sent_at ? \Carbon\Carbon::parse($record->sent_at)->format('Y-m-d H:i:s') : 'Not sent',
                    'completed_at' => $record->completed_at ? \Carbon\Carbon::parse($record->completed_at)->format('Y-m-d H:i:s') : 'Not completed',
                    'actions' => $this->getEmployeeActionButtons($record)
                ];
            }

            $response = [
                "draw" => intval($draw),
                "iTotalRecords" => $totalRecords,
                "iTotalDisplayRecords" => $totalRecordswithFilter,
                "aaData" => $data_arr
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('AD Group KYC campaign employee data error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load employee data'], 500);
        }
    }

    /**
     * Create a new campaign
     */
    public function storeCampaign(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'employer_group_id' => 'required|string',
                'link_expiry_hours' => 'required|integer|min:1|max:720',
                'otp_expiry_minutes' => 'required|integer|min:1|max:60',
                'max_attempts' => 'required|integer|min:1|max:10',
                'escalation_days' => 'required|integer|min:1|max:30',
                'reminder_days' => 'required|array',
                'reminder_days.*' => 'integer|min:1|max:30',
                'notification_channels' => 'required|array',
                'notification_channels.*' => 'in:email,sms,whatsapp'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $campaignData = $request->all();
            $campaignData['status'] = 'active';
            $campaignData['product_id'] = 12; // AD Group product
            $campaignData['created_by'] = auth()->id();

            $campaign = $this->kycService->createCampaign($campaignData);

            return response()->json([
                'success' => true,
                'message' => 'Campaign created successfully',
                'campaign' => $campaign
            ]);

        } catch (\Exception $e) {
            Log::error('AD Group KYC campaign creation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create campaign'
            ], 500);
        }
    }

    /**
     * Generate KYC links for a campaign
     */
    public function generateLinks(Request $request, $campaignId)
    {
        try {
            $campaign = AdGroupKycCampaign::findOrFail($campaignId);

            $validator = Validator::make($request->all(), [
                'policy_ids' => 'required|array',
                'policy_ids.*' => 'integer|exists:policies,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $policyIds = $request->policy_ids;
            $links = $this->kycService->generateLinksForPolicies($campaign, $policyIds);

            return response()->json([
                'success' => true,
                'message' => 'KYC links generated successfully',
                'links_count' => count($links)
            ]);

        } catch (\Exception $e) {
            Log::error('AD Group KYC link generation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate KYC links'
            ], 500);
        }
    }

    /**
     * Send KYC links
     */
    public function sendLinks(Request $request, $campaignId)
    {
        try {
            Log::info('AD Group KYC sendLinks called', [
                'campaign_id' => $campaignId,
                'request_data' => $request->all()
            ]);

            $validator = Validator::make($request->all(), [
                'link_ids' => 'required|array',
                'link_ids.*' => 'integer|exists:ad_group_kyc_links,id',
                'channels' => 'required|array',
                'channels.*' => 'in:email,sms,whatsapp,all',
                'immediate' => 'boolean'
            ]);

            if ($validator->fails()) {
                Log::error('AD Group KYC sendLinks validation failed', [
                    'errors' => $validator->errors()
                ]);
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $linkIds = $request->link_ids;
            $channels = $request->channels;
            $immediate = $request->get('immediate', true);

            Log::info('AD Group KYC sendLinks processing', [
                'link_ids' => $linkIds,
                'channels' => $channels,
                'immediate' => $immediate
            ]);

            $results = $this->kycService->sendKycLinks($linkIds, $channels, $immediate);
            
            // Count success/failure from nested channel results
            $successCount = 0;
            $failureCount = 0;
            
            foreach ($results as $linkId => $linkResults) {
                foreach ($linkResults as $channel => $channelResult) {
                    if (isset($channelResult['success'])) {
                        if ($channelResult['success']) {
                            $successCount++;
                        } else {
                            $failureCount++;
                        }
                    }
                }
            }
            
            Log::info('AD Group KYC sendLinks completed', [
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'results' => $results
            ]);

            return response()->json([
                'success' => true,
                'message' => "KYC links sent successfully. Success: {$successCount}, Failed: {$failureCount}",
                'results' => [
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                    'details' => $results
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AD Group KYC link sending error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send KYC links: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    private function getDashboardStats()
    {
        $totalCampaigns = AdGroupKycCampaign::count();
        $activeCampaigns = AdGroupKycCampaign::where('status', 'active')->count();
        $totalLinks = AdGroupKycLink::count();
        $completedLinks = AdGroupKycLink::where('status', 'completed')->count();
        $pendingLinks = AdGroupKycLink::where('status', 'pending')->count();
        $expiredLinks = AdGroupKycLink::where('status', 'expired')->count();
        $sentLinks = AdGroupKycLink::whereNotNull('sent_at')->count();
        $openedLinks = AdGroupKycLink::whereIn('status', ['opened', 'otp_verified', 'completed'])->count();
        
        // Calculate average response time (time between sent and opened)
        $responseTime = 0;
        $openedLinksWithSentTime = AdGroupKycLink::whereNotNull('sent_at')
            ->whereIn('status', ['opened', 'otp_verified', 'completed'])
            ->whereNotNull('opened_at')
            ->get();
            
        if ($openedLinksWithSentTime->count() > 0) {
            $totalMinutes = $openedLinksWithSentTime->sum(function ($link) {
                return $link->sent_at->diffInMinutes($link->opened_at);
            });
            $responseTime = round($totalMinutes / $openedLinksWithSentTime->count(), 0);
        }

        return [
            'total_campaigns' => $totalCampaigns,
            'active_campaigns' => $activeCampaigns,
            'total_links' => $totalLinks,
            'completed_links' => $completedLinks,
            'pending_links' => $pendingLinks,
            'expired_links' => $expiredLinks,
            'sent_links' => $sentLinks,
            'opened_links' => $openedLinks,
            'response_time' => $responseTime,
            'completion_rate' => $totalLinks > 0 ? round(($completedLinks / $totalLinks) * 100, 1) : 0
        ];
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities($limit = 10)
    {
        return AdGroupKycActivity::with(['customer', 'policy'])
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get status badge HTML
     */
    private function getStatusBadge($status)
    {
        $badges = [
            'pending' => '<span class="badge badge-warning">Pending</span>',
            'sent' => '<span class="badge badge-info">Sent</span>',
            'opened' => '<span class="badge badge-primary">Opened</span>',
            'otp_verified' => '<span class="badge badge-secondary">OTP Verified</span>',
            'completed' => '<span class="badge badge-success">Completed</span>',
            'expired' => '<span class="badge badge-danger">Expired</span>',
            'failed' => '<span class="badge badge-danger">Failed</span>'
        ];

        return $badges[$status] ?? '<span class="badge badge-secondary">Unknown</span>';
    }

    /**
     * Get action buttons HTML
     */
    private function getActionButtons($link)
    {
        $buttons = '<div class="btn-group" role="group">';

        if ($link->status === 'pending') {
            $buttons .= '<button class="btn btn-sm btn-primary send-link-btn" data-link-id="' . $link->id . '">Send</button>';
        }

        if ($link->status === 'sent' || $link->status === 'opened') {
            $buttons .= '<button class="btn btn-sm btn-warning resend-link-btn" data-link-id="' . $link->id . '">Resend</button>';
        }

        $buttons .= '<button class="btn btn-sm btn-info view-link-btn" data-link-id="' . $link->id . '">View</button>';
        $buttons .= '</div>';

        return $buttons;
    }

    /**
     * Get action buttons HTML for employees
     */
    private function getEmployeeActionButtons($record)
    {
        $buttons = '<div class="btn-group" role="group">';
        
        // If KYC link exists, show KYC-related actions
        if ($record->kyc_link_id) {
            if ($record->kyc_status === 'sent' || $record->kyc_status === 'opened') {
                $buttons .= '<button class="btn btn-sm btn-warning resend-link-btn" data-link-id="' . $record->kyc_link_id . '" title="Resend KYC Link">
                    <i class="flaticon2-refresh"></i>
                </button>';
            }
            
            $buttons .= '<button class="btn btn-sm btn-info view-link-btn" data-link-id="' . $record->kyc_link_id . '" title="View KYC Details">
                <i class="flaticon2-eye"></i>
            </button>';
        } else {
            // If no KYC link exists, show generate link button
            $buttons .= '<button class="btn btn-sm btn-primary generate-link-btn" data-policy-id="' . $record->policy_id . '" data-customer-id="' . $record->customer_id . '" title="Generate KYC Link">
                <i class="flaticon2-plus"></i>
            </button>';
        }
        
        // Always show employee details button
        $buttons .= '<button class="btn btn-sm btn-secondary view-employee-btn" data-customer-id="' . $record->customer_id . '" title="View Employee Details">
            <i class="flaticon2-user"></i>
        </button>';
        
        $buttons .= '</div>';
        return $buttons;
    }

    /**
     * Show edit campaign form
     */
    public function editCampaign($id)
    {
        try {
            $campaign = AdGroupKycCampaign::findOrFail($id);
            
            return view('admin.ad-group-kyc.campaign.edit', compact('campaign'));
            
        } catch (\Exception $e) {
            Log::error('AD Group KYC campaign edit error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Campaign not found');
        }
    }

    /**
     * Update campaign
     */
    public function updateCampaign(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:active,completed,paused',
            'escalation_days' => 'nullable|integer|min:1|max:365',
            'reminder_days' => 'nullable|array',
            'reminder_days.*' => 'integer|min:1|max:30'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $campaign = AdGroupKycCampaign::findOrFail($id);
            
            $campaign->update([
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status,
                'escalation_days' => $request->escalation_days,
                'reminder_days' => $request->reminder_days
            ]);

            return redirect()->route('admin.ad-group-kyc.campaign.show', $id)
                ->with('success', 'Campaign updated successfully');

        } catch (\Exception $e) {
            Log::error('AD Group KYC campaign update error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to update campaign')
                ->withInput();
        }
    }

    /**
     * Resend notification for a specific link
     */
    public function resendNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'link_id' => 'required|exists:ad_group_kyc_links,id',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:email,whatsapp,sms',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $linkId = $request->link_id;
            $channels = $request->channels;
            $customMessage = $request->message;

            $link = AdGroupKycLink::with(['customer', 'campaign'])->findOrFail($linkId);
            
            $results = $this->notificationService->sendKycLink($link, $channels, true);

            return response()->json([
                'success' => true,
                'message' => 'Notifications sent successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('AD Group KYC notification resend error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send bulk notifications
     */
    public function sendBulkNotifications(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:ad_group_kyc_campaigns,id',
            'link_ids' => 'required|array|min:1',
            'link_ids.*' => 'exists:ad_group_kyc_links,id',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:email,whatsapp,sms',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $campaign = AdGroupKycCampaign::findOrFail($request->campaign_id);
            $linkIds = $request->link_ids;
            $channels = $request->channels;
            $customMessage = $request->message;

            $results = $this->notificationService->sendBulkNotifications($campaign, $linkIds, $channels, $customMessage);

            return response()->json([
                'success' => true,
                'message' => 'Bulk notifications sent successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('AD Group KYC bulk notification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send reminder notifications
     */
    public function sendReminders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:ad_group_kyc_campaigns,id',
            'days_since_sent' => 'integer|min:1|max:30',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:email,whatsapp,sms',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $campaign = AdGroupKycCampaign::findOrFail($request->campaign_id);
            $daysSinceSent = $request->get('days_since_sent', 3);
            $channels = $request->channels;

            $results = $this->notificationService->sendReminderNotifications($campaign, $daysSinceSent, $channels);

            return response()->json([
                'success' => true,
                'message' => 'Reminder notifications sent successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('AD Group KYC reminder notification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reminder notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send escalation notifications
     */
    public function sendEscalations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:ad_group_kyc_campaigns,id',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:email,whatsapp,sms',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $campaign = AdGroupKycCampaign::findOrFail($request->campaign_id);
            $channels = $request->channels;

            $results = $this->notificationService->sendEscalationNotifications($campaign, $channels);

            return response()->json([
                'success' => true,
                'message' => 'Escalation notifications sent successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('AD Group KYC escalation notification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send escalation notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export campaign data
     */
    public function exportCampaignData(Request $request)
    {
        try {
            $campaignId = $request->get('campaign_id');
            $format = $request->get('format', 'csv');

            if (!$campaignId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign ID is required'
                ], 400);
            }

            $campaign = AdGroupKycCampaign::findOrFail($campaignId);
            
            // Get campaign statistics
            $stats = $this->getCampaignStats($campaignId);
            
            // Get all links for the campaign
            $links = AdGroupKycLink::with(['customer', 'policy'])
                ->where('campaign_id', $campaignId)
                ->get();

            if ($format === 'csv') {
                $filename = 'ad_group_kyc_campaign_' . $campaignId . '_' . now()->format('Y-m-d_H-i-s') . '.csv';
                
                $headers = [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ];

                $callback = function() use ($links, $stats) {
                    $file = fopen('php://output', 'w');
                    
                    // Write campaign info
                    fputcsv($file, ['Campaign Export']);
                    fputcsv($file, ['Campaign Name', $stats['campaign_name'] ?? 'N/A']);
                    fputcsv($file, ['Total Links', $stats['total_links'] ?? 0]);
                    fputcsv($file, ['Completed', $stats['completed_links'] ?? 0]);
                    fputcsv($file, ['Completion Rate', ($stats['completion_rate'] ?? 0) . '%']);
                    fputcsv($file, []);
                    
                    // Write headers
                    fputcsv($file, [
                        'Link ID', 'Employee ID', 'Employee Name', 'Email', 'Phone', 
                        'Policy Number', 'Status', 'OTP Code', 'Sent At', 'Completed At', 'Expires At'
                    ]);
                    
                    // Write data
                    foreach ($links as $link) {
                        fputcsv($file, [
                            $link->id,
                            $link->policy->customer->employee_id ?? 'N/A',
                            $link->customer ? $link->customer->firstName . ' ' . $link->customer->lastName : 'N/A',
                            $link->customer ? $link->customer->email : 'N/A',
                            $link->customer ? $link->customer->cellphone : 'N/A',
                            $link->policy ? $link->policy->policyNumber : 'N/A',
                            $link->status,
                            $link->otp_code ?? 'N/A',
                            $link->sent_at ? $link->sent_at->format('Y-m-d H:i:s') : 'Not sent',
                            $link->completed_at ? $link->completed_at->format('Y-m-d H:i:s') : 'Not completed',
                            $link->expires_at ? $link->expires_at->format('Y-m-d H:i:s') : 'N/A'
                        ]);
                    }
                    
                    fclose($file);
                };

                return response()->stream($callback, 200, $headers);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unsupported export format'
            ], 400);

        } catch (\Exception $e) {
            Log::error('AD Group KYC export error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export campaign data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
