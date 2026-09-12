<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\RekycCampaign;
use AlphaDirect\Models\RekycLink;
use AlphaDirect\Models\RekycActivity;
use AlphaDirect\Models\RekycDocument;
use AlphaDirect\Services\RekycService;
use AlphaDirect\Services\RekycNotificationService;
use AlphaDirect\Services\RekycAuditService;
use AlphaDirect\Services\RekycLogService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use AlphaDirect\Mail\MailTemplate;
use AlphaDirect\EmailBroadcasting;
use AlphaDirect\Http\Controllers\SmsMessaging;


class RekycAdminController extends Controller
{
    protected $rekycService;
    protected $notificationService;
    protected $auditService;

    public function __construct(
        RekycService $rekycService,
        RekycNotificationService $notificationService,
        RekycAuditService $auditService
    ) {
        $this->rekycService = $rekycService;
        $this->notificationService = $notificationService;
        $this->auditService = $auditService;
    }

    /**
     * Display Re-KYC admin dashboard
     */
    public function index(Request $request)
    {
        try {
            $campaigns = RekycCampaign::withCount(['links', 'activities'])
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

            return view('admin.rekyc.index', compact('campaigns', 'stats', 'recentActivities'));

        } catch (\Exception $e) {
            Log::error('Re-KYC admin dashboard error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load Re-KYC dashboard');
        }
    }

    /**
     * Show campaign details
     */
    public function showCampaign($id)
    {
        try {
            $campaign = RekycCampaign::with(['links.customer', 'activities'])
                ->findOrFail($id);

            $stats = $this->getCampaignStats($campaign);

            return view('admin.rekyc.campaign.show', compact('campaign', 'stats'));

        } catch (\Exception $e) {
            Log::error('Re-KYC campaign show error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load campaign details');
        }
    }

    /**
     * Get campaign links data for DataTable
     */
    public function getCampaignLinksData(Request $request, $id)
    {
        try {
            // Log the request for debugging
            Log::info('DataTable request received', [
                'campaign_id' => $id,
                'request_data' => $request->all()
            ]);

            $draw = $request->get('draw');
            $start = $request->get("start");
            $rowperpage = $request->get("length"); // Rows display per page

            $columnIndex_arr = $request->get('order');
            $columnName_arr = $request->get('columns');
            $order_arr = $request->get('order');
            $search_arr = $request->get('search');

            $columnIndex = $columnIndex_arr[0]['column']; // Column index
            $columnName = $columnName_arr[$columnIndex]['data']; // Column name
            $columnSortOrder = $order_arr[0]['dir']; // asc or desc
            $searchValue = $search_arr['value']; // Search value

            // Total records
            $totalRecords = RekycLink::where('campaign_id', $id)->count();

            // Fetch records
            $records = RekycLink::where('campaign_id', $id)
                ->with('customer')
                ->orderBy($columnName, $columnSortOrder);

            // Search functionality
            if ($searchValue != null) {
                $records->where(function($query) use ($searchValue) {
                    $query->where('id', 'like', '%' . $searchValue . '%')
                        ->orWhereHas('customer', function($q) use ($searchValue) {
                            $q->where('firstName', 'like', '%' . $searchValue . '%')
                              ->orWhere('lastName', 'like', '%' . $searchValue . '%')
                              ->orWhere('email', 'like', '%' . $searchValue . '%')
                              ->orWhere('cellphone', 'like', '%' . $searchValue . '%');
                        })
                        ->orWhere('status', 'like', '%' . $searchValue . '%');
                });
            }

            $totalRecordswithFilter = $records->count();

            $records = $records->skip($start)
                ->take($rowperpage)
                ->get();

            $data_arr = array();

            foreach ($records as $record) {
                $customerName = $record->customer ? 
                    $record->customer->firstName . ' ' . $record->customer->lastName : 
                    'Unknown';
                
                $customerEmail = $record->customer ? $record->customer->email : '';
                $customerCellphone = $record->customer ? $record->customer->cellphone : '';

                $statusBadge = $this->getStatusBadge($record->status);
                
                $sentStatus = $record->sent_at ? 
                    '<i class="fas fa-check text-success"></i><br><small>' . $record->sent_at->format('M d, H:i') . '</small>' :
                    '<i class="fas fa-times text-danger"></i>';
                
                $openedStatus = $record->opened_at ? 
                    '<i class="fas fa-check text-success"></i><br><small>' . $record->opened_at->format('M d, H:i') . '</small>' :
                    '<i class="fas fa-times text-danger"></i>';
                
                $otpStatus = $record->otp_verified_at ? 
                    '<i class="fas fa-check text-success"></i><br><small>' . $record->otp_verified_at->format('M d, H:i') . '</small>' :
                    '<i class="fas fa-times text-danger"></i>';
                
                $completedStatus = $record->completed_at ? 
                    '<i class="fas fa-check text-success"></i><br><small>' . $record->completed_at->format('M d, H:i') . '</small>' :
                    '<i class="fas fa-times text-danger"></i>';
                
                $expiresAt = $record->expires_at ? 
                    '<span class="text-' . ($record->expires_at->isPast() ? 'danger' : ($record->expires_at->isToday() ? 'warning' : 'muted')) . '">' . 
                    $record->expires_at->format('M d, H:i') . '</span>' :
                    '<span class="text-muted">Never</span>';

                $actions = '<div class="btn-group" role="group">
                    <a href="' . route('admin.rekyc.link.show', $record->id) . '" 
                       class="btn btn-sm btn-info" title="View Details">
                        <i class="fas fa-eye"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-success" 
                            onclick="resendNotification(' . $record->id . ')" title="Resend Notification">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>';

                $data_arr[] = array(
                    "checkbox" => '<input type="checkbox" class="link-checkbox" value="' . $record->id . '">',
                    "id" => $record->id,
                    "customer" => '<strong>' . $customerName . '</strong>' . 
                                 ($customerEmail ? '<br><small class="text-muted">' . $customerEmail . '</small>' : ''),
                    "cellphone" => $customerCellphone ? '<i class="fas fa-phone text-primary mr-1"></i>' . $customerCellphone : '<span class="text-muted">N/A</span>',
                    "status" => $statusBadge,
                    "sent" => $sentStatus,
                    "opened" => $openedStatus,
                    "otp_verified" => $otpStatus,
                    "completed" => $completedStatus,
                    "expires_at" => $expiresAt,
                    "actions" => $actions
                );
            }

            $response = array(
                "draw" => intval($draw),
                "iTotalRecords" => $totalRecords,
                "iTotalDisplayRecords" => $totalRecordswithFilter,
                "aaData" => $data_arr
            );

            // Log the response for debugging
            Log::info('DataTable response', [
                'campaign_id' => $id,
                'total_records' => $totalRecords,
                'filtered_records' => $totalRecordswithFilter,
                'data_count' => count($data_arr)
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Re-KYC campaign links data error: ' . $e->getMessage());
            return response()->json([
                "draw" => 0,
                "iTotalRecords" => 0,
                "iTotalDisplayRecords" => 0,
                "aaData" => []
            ]);
        }
    }

    /**
     * Get status badge HTML
     */
    private function getStatusBadge($status)
    {
        $badgeClass = match($status) {
            'completed' => 'success',
            'pending' => 'warning',
            'expired' => 'danger',
            default => 'info'
        };
        
        return '<span class="badge badge-' . $badgeClass . '">' . ucfirst($status) . '</span>';
    }

    /**
     * Show campaign edit form
     */
    public function editCampaign($id)
    {
        try {
            $campaign = RekycCampaign::findOrFail($id);
            return view('admin.rekyc.campaign.edit', compact('campaign'));

        } catch (\Exception $e) {
            Log::error('Re-KYC campaign edit error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load campaign edit form');
        }
    }

    /**
     * Update campaign
     */
    public function updateCampaign(Request $request, $id)
    {
        // Process reminder_days from comma-separated string to array
        $reminderDays = [];
        if ($request->reminder_days) {
            $reminderDays = array_map('intval', array_filter(explode(',', $request->reminder_days)));
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:draft,active,paused,completed,cancelled',
            'settings' => 'nullable|array',
            'escalation_days' => 'nullable|integer|min:1|max:30',
            'reminder_days' => 'nullable|string',
        ]);

        // Add custom validation for reminder days
        if (!empty($reminderDays)) {
            foreach ($reminderDays as $day) {
                if ($day < 1 || $day > 30) {
                    $validator->errors()->add('reminder_days', 'Each reminder day must be between 1 and 30.');
                    break;
                }
            }
        }

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $campaign = RekycCampaign::findOrFail($id);
            
            $campaign->update([
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status,
                'settings' => $request->settings ?? $campaign->settings,
                'escalation_days' => $request->escalation_days ?? $campaign->escalation_days,
                'reminder_days' => !empty($reminderDays) ? $reminderDays : $campaign->reminder_days,
                'updated_by' => auth()->id(),
            ]);

            $this->auditService->logActivity(
                null,
                null,
                'campaign_updated',
                'Campaign updated by admin',
                [
                    'campaign_id' => $id,
                    'changes' => $request->only(['name', 'description', 'status', 'settings', 'escalation_days', 'reminder_days'])
                ]
            );

            return redirect()->route('admin.rekyc.campaign.show', $id)
                ->with('success', 'Campaign updated successfully');

        } catch (\Exception $e) {
            Log::error('Re-KYC campaign update error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to update campaign')
                ->withInput();
        }
    }

    /**
     * Show link details
     */
    public function showLink($id)
    {
        try {
            $link = RekycLink::with(['customer', 'campaign', 'activities', 'documents'])
                ->findOrFail($id);

            $activities = $link->activities()->orderBy('occurred_at', 'desc')->get();
            $documents = $link->documents()->orderBy('created_at', 'desc')->get();

            return view('admin.rekyc.link.show', compact('link', 'activities', 'documents'));

        } catch (\Exception $e) {
            Log::error('Re-KYC link show error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load link details');
        }
    }

    /**
     * Resend notification
     */
    public function resendNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'link_id' => 'required|integer|exists:rekyc_links,id',
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
            $link = RekycLink::with(['customer', 'campaign'])->findOrFail($linkId);
            $channels = $request->channels;
            $customMessage = $request->message ?? '';
         
            $this->notificationService->sendRekycLink($link, $channels, $customMessage);
//dd($results);
            $results = [];
            if(in_array('email', $channels)){   
            $results['email']   = $this->emailSend($link, $customMessage);
            }
            if(in_array('sms', $channels)){
                $results['sms']   = $this->smsSend($link, $customMessage);
            }
            if(in_array('whatsapp', $channels)){
                $results['whatsapp']   = $this->whatsappSend($link, $customMessage); 
            }
           

            $this->auditService->logActivity(
                $linkId,
                $link->customer_id,
                'notification_resent',
                'Notification resent by admin',
                [
                    'link_id' => $linkId,
                    'channels' => $channels,
                    'custom_message' => $customMessage,
                    'results' => $results
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Notifications sent successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Re-KYC notification resend error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function sendNotificationMain($linkId,$channels,$customMessage = null)
    {
         try {
            
            $link = RekycLink::with(['customer', 'campaign'])->findOrFail($linkId);
            $results = [];
            $successfulChannels = [];
            
            if(in_array('email', $channels)){   
                $results['email'] = $this->emailSend($link, $customMessage);
                if($results['email']) {
                    $successfulChannels[] = 'email';
                }
            }
            if(in_array('sms', $channels)){
                $results['sms'] = $this->smsSend($link, $customMessage);
                if($results['sms']) {
                    $successfulChannels[] = 'sms';
                }
            }
            if(in_array('whatsapp', $channels)){
                $results['whatsapp'] = $this->whatsappSend($link, $customMessage);
                if($results['whatsapp']) {
                    $successfulChannels[] = 'whatsapp';
                }
            }
            
            // Update delivery method with all successful channels
            if (!empty($successfulChannels)) {
                $link->update([
                    'delivery_method' => implode(',', $successfulChannels),
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
            
           $this->auditService->logActivity(
                $linkId,
                $link->customer_id,
                'notification_resent',
                'Notification resent by admin',
                [
                    'link_id' => $linkId,
                    'channels' => $channels,
                    'successful_channels' => $successfulChannels,
                    'custom_message' => $customMessage,
                    'results' => $results
                ]
            );

            return  true;

        } catch (\Exception $e) {
            Log::error('Re-KYC notification resend error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send bulk notifications
     */
    public function sendBulkNotifications(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:rekyc_campaigns,id',
            'link_ids' => 'required|array|min:1',
            'link_ids.*' => 'exists:rekyc_links,id',
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
            $campaign = RekycCampaign::findOrFail($request->campaign_id);
            $linkIds = $request->link_ids;
            $channels = $request->channels;
            $customMessage = $request->message;

            $results = $this->notificationService->sendBulkNotifications($campaign, $linkIds, $channels, $customMessage);

            $this->auditService->logActivity(
                null,
                null,
                'bulk_notification_sent',
                'Bulk notifications sent by admin',
                [
                    'campaign_id' => $request->campaign_id,
                    'link_count' => count($linkIds),
                    'channels' => $channels,
                    'results' => $results
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk notifications sent successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Re-KYC bulk notification error: ' . $e->getMessage());
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
            'campaign_id' => 'required|exists:rekyc_campaigns,id',
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
            $campaign = RekycCampaign::findOrFail($request->campaign_id);
            $daysSinceSent = $request->get('days_since_sent', 3);
            $channels = $request->channels;

            $results = $this->notificationService->sendReminderNotifications($campaign, $daysSinceSent, $channels);

            $this->auditService->logActivity(
                null,
                null,
                'reminder_notifications_sent',
                'Reminder notifications sent by admin',
                [
                    'campaign_id' => $request->campaign_id,
                    'days_since_sent' => $daysSinceSent,
                    'channels' => $channels,
                    'results' => $results
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Reminder notifications sent successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Re-KYC reminder notification error: ' . $e->getMessage());
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
            'campaign_id' => 'required|exists:rekyc_campaigns,id',
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
            $campaign = RekycCampaign::findOrFail($request->campaign_id);
            $channels = $request->channels;

            $results = $this->notificationService->sendEscalationNotifications($campaign, $channels);

            $this->auditService->logActivity(
                null,
                null,
                'escalation_notifications_sent',
                'Escalation notifications sent by admin',
                [
                    'campaign_id' => $request->campaign_id,
                    'channels' => $channels,
                    'results' => $results
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Escalation notifications sent successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Re-KYC escalation notification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send escalation notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats()
    {
        try {
            $stats = [
                'total_campaigns' => RekycCampaign::count(),
                'active_campaigns' => RekycCampaign::where('status', 'active')->count(),
                'total_links' => RekycLink::count(),
                'sent_links' => RekycLink::whereNotNull('sent_at')->count(),
                'opened_links' => RekycLink::whereNotNull('opened_at')->count(),
                'completed_links' => RekycLink::where('status', 'completed')->count(),
                'pending_links' => RekycLink::where('status', 'pending')->count(),
                'expired_links' => RekycLink::where('expires_at', '<', now())->count(),
                'completion_rate' => $this->getCompletionRate(),
                'response_time' => $this->getAverageResponseTime(),
            ];

            return $stats;

        } catch (\Exception $e) {
            Log::error('Re-KYC dashboard stats error: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * Get campaign statistics
     */
    public function getCampaignStats(RekycCampaign $campaign)
    {
        try {
            $links = $campaign->links();
            
            $stats = [
                'total_links' => $links->count(),
                'sent_links' => $links->whereNotNull('sent_at')->count(),
                'opened_links' => $links->whereNotNull('opened_at')->count(),
                'otp_verified' => $links->whereNotNull('otp_verified_at')->count(),
                'completed_links' => $links->where('status', 'completed')->count(),
                'pending_links' => $links->where('status', 'pending')->count(),
                'expired_links' => $links->where('expires_at', '<', now())->count(),
                'completion_rate' => $this->getCampaignCompletionRate($campaign),
                'response_time' => $this->getCampaignResponseTime($campaign),
            ];

            return $stats;

        } catch (\Exception $e) {
            Log::error('Re-KYC campaign stats error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent activities
     */
    public function getRecentActivities($limit = 10)
    {
        try {
            return RekycActivity::with(['link.customer', 'link.campaign'])
                ->orderBy('occurred_at', 'desc')
                ->limit($limit)
                ->get();

        } catch (\Exception $e) {
            Log::error('Re-KYC recent activities error: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get completion rate
     */
    protected function getCompletionRate()
    {
        $total = RekycLink::count();
        $completed = RekycLink::where('status', 'completed')->count();
        
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    /**
     * Get average response time
     */
    protected function getAverageResponseTime()
    {
        $links = RekycLink::whereNotNull('sent_at')
            ->whereNotNull('opened_at')
            ->get();
        
        if ($links->isEmpty()) {
            return 0;
        }
        
        $totalMinutes = $links->sum(function ($link) {
            return $link->sent_at->diffInMinutes($link->opened_at);
        });
        
        return round($totalMinutes / $links->count(), 2);
    }

    /**
     * Get campaign completion rate
     */
    protected function getCampaignCompletionRate(RekycCampaign $campaign)
    {
        $total = $campaign->links()->count();
        $completed = $campaign->links()->where('status', 'completed')->count();
        
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    /**
     * Get campaign response time
     */
    protected function getCampaignResponseTime(RekycCampaign $campaign)
    {
        $links = $campaign->links()
            ->whereNotNull('sent_at')
            ->whereNotNull('opened_at')
            ->get();
        
        if ($links->isEmpty()) {
            return 0;
        }
        
        $totalMinutes = $links->sum(function ($link) {
            return $link->sent_at->diffInMinutes($link->opened_at);
        });
        
        return round($totalMinutes / $links->count(), 2);
    }

    /**
     * Store new campaign
     */
    public function storeCampaign(Request $request)
    {
        // Process reminder_days from comma-separated string to array
        $reminderDays = [];
        if ($request->reminder_days) {
            $reminderDays = array_map('intval', array_filter(explode(',', $request->reminder_days)));
        } else {
            $reminderDays = [3, 7, 14]; // Default values
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'escalation_days' => 'nullable|integer|min:1|max:30',
            'reminder_days' => 'nullable|string',
        ]);

        // Add custom validation for reminder days
        if (!empty($reminderDays)) {
            foreach ($reminderDays as $day) {
                if ($day < 1 || $day > 30) {
                    $validator->errors()->add('reminder_days', 'Each reminder day must be between 1 and 30.');
                    break;
                }
            }
        }

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {

            $campaign = RekycCampaign::create([
                'name' => $request->name,
                'description' => $request->description,
                'status' => 'draft',
                'escalation_days' => $request->escalation_days ?? 7,
                'reminder_days' => $reminderDays,
                'settings' => [
                    'otp_required' => true,
                    'document_upload_required' => false,
                    'ocr_enabled' => false,
                    'max_attempts' => 3,
                    'link_expiry_days' => 30,
                    'email_enabled' => true,
                    'whatsapp_enabled' => true,
                    'sms_enabled' => false,
                ],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->auditService->logActivity(
                null,
                null,
                'campaign_created',
                'Campaign created by admin',
                ['campaign_id' => $campaign->id, 'name' => $campaign->name]
            );

            return redirect()->route('admin.rekyc.campaign.show', $campaign->id)
                ->with('success', 'Campaign created successfully');

        } catch (\Exception $e) {
            Log::error('Re-KYC campaign creation error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to create campaign')
                ->withInput();
        }
    }

    /**
     * Get campaign links for AJAX
     */
    public function getCampaignLinks(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:rekyc_campaigns,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid campaign ID',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $links = RekycLink::with('customer')
                ->where('campaign_id', $request->campaign_id)
                ->get(['id', 'customer_id', 'status', 'created_at']);

            return response()->json([
                'success' => true,
                'links' => $links
            ]);

        } catch (\Exception $e) {
            Log::error('Re-KYC campaign links error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load campaign links',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export campaign data
     */
    public function exportCampaignData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:rekyc_campaigns,id',
            'format' => 'nullable|in:csv,excel,pdf',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', 'Invalid export parameters');
        }

        try {
            $campaign = RekycCampaign::with(['links.customer', 'links.documents'])->findOrFail($request->campaign_id);
            $format = $request->get('format', 'csv');

            // Generate CSV content
            $csvData = $this->generateCampaignCsvData($campaign);
            
            // Set headers for file download
            $filename = 'campaign_' . $campaign->id . '_' . now()->format('Y-m-d_H-i-s') . '.csv';
            
            return response($csvData)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            Log::error('Re-KYC export error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export campaign data: ' . $e->getMessage());
        }
    }

    /**
     * Generate CSV data for campaign export
     */
    private function generateCampaignCsvData($campaign)
    {
        $output = fopen('php://temp', 'r+');
        
        // CSV Headers
        fputcsv($output, [
            'Link ID',
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Status',
            'Created At',
            'Sent At',
            'Opened At',
            'OTP Verified At',
            'Completed At',
            'Expires At',
            'Documents Count',
            'Response Time (minutes)'
        ]);

        // Campaign data
        foreach ($campaign->links as $link) {
            $responseTime = null;
            if ($link->opened_at && $link->sent_at) {
                $responseTime = $link->opened_at->diffInMinutes($link->sent_at);
            }

            fputcsv($output, [
                $link->id,
                $link->customer ? $link->customer->first_name . ' ' . $link->customer->last_name : 'N/A',
                $link->customer ? $link->customer->email : 'N/A',
                $link->customer ? $link->customer->cellphone : 'N/A',
                ucfirst($link->status),
                $link->created_at ? $link->created_at->format('Y-m-d H:i:s') : 'N/A',
                $link->sent_at ? $link->sent_at->format('Y-m-d H:i:s') : 'N/A',
                $link->opened_at ? $link->opened_at->format('Y-m-d H:i:s') : 'N/A',
                $link->otp_verified_at ? $link->otp_verified_at->format('Y-m-d H:i:s') : 'N/A',
                $link->completed_at ? $link->completed_at->format('Y-m-d H:i:s') : 'N/A',
                $link->expires_at ? $link->expires_at->format('Y-m-d H:i:s') : 'N/A',
                $link->documents ? $link->documents->count() : 0,
                $responseTime ?? 'N/A'
            ]);
        }

        // Add campaign summary at the end
        fputcsv($output, []); // Empty row
        fputcsv($output, ['CAMPAIGN SUMMARY']);
        fputcsv($output, ['Campaign Name', $campaign->name]);
        fputcsv($output, ['Campaign Description', $campaign->description]);
        fputcsv($output, ['Total Links', $campaign->links->count()]);
        fputcsv($output, ['Completed Links', $campaign->links->where('status', 'completed')->count()]);
        fputcsv($output, ['Pending Links', $campaign->links->where('status', 'pending')->count()]);
        fputcsv($output, ['Expired Links', $campaign->links->where('status', 'expired')->count()]);
        fputcsv($output, ['Created At', $campaign->created_at->format('Y-m-d H:i:s')]);

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }
    public function emailSend(RekycLink $link, $customMessage)
    {
      try {
        $email = new \stdClass();
        $email->user_id ="";
        $email->hook = 'rekyc_verification';
        $email->customer_id = $link->customer_id;
        $email->email = $link->customer->email;
        $email->attachment = null;
        
        // Wrap rekyc_url in HTML button code for clickable button
        $rekycUrl = $link->getAccessUrl();
        $email->rekyc_url = '<div style="text-align: center; margin: 20px 0;">
            <a href="' . $rekycUrl . '" style="background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; font-size: 16px;">
                Click Here to Access Re-KYC Portal
            </a>
        </div>';
        
        $email->rekyc_link_expiry_days = $link->expires_at;
        $email->custom_message = $customMessage ?? '';
        $emailTemplate = EmailBroadcasting::where('hook_slug', $email->hook)->first(array('subject'));
       
        $markdown = new MailTemplate($email);
        $html = $markdown->render('Mail.mailTemplate', ['data' => $email]);
       
        event(new \AlphaDirect\Events\SendMail( $link->customer->email, $emailTemplate->subject, "", $html, null, ['policyNumber' => '', 'hook' => $email->hook]));
        
        // Log successful email sending to database
        RekycLogService::logEmailSent(
            $link->id,
            $link->customer_id,
            $link->campaign_id,
            'Re-KYC email sent successfully with clickable button',
            [
                'email' => $link->customer->email,
                'subject' => $emailTemplate->subject,
                'custom_message' => $customMessage,
                'rekyc_url' => $rekycUrl
            ]
        );
        
       return true;
        } catch (\Exception $e) {
            Log::error('Re-KYC email notification error: ' . $e->getMessage());
            
            // Log email failure to database
            RekycLogService::logEmailFailed(
                $link->id,
                $link->customer_id,
                $link->campaign_id,
                $e->getMessage(),
                [
                    'email' => $link->customer->email,
                    'custom_message' => $customMessage,
                    'error_trace' => $e->getTraceAsString()
                ]
            );
            
            return false;
        }
    }
    public function smsSend(RekycLink $link, $customMessage)
    {
     try {
                $rekycUrl = $link->getAccessUrl();
                $rekycOtpExpiry = $link->expires_at;
                $sms = new SmsMessaging();
                $sms = $sms->rekycVerification(47,  
                                                $link->customer->cellphone, 
                                                $link->customer->firstName, 
                                                $rekycUrl,
                                                $rekycOtpExpiry
                );
                
                // Log successful SMS sending to database
                RekycLogService::logSmsSent(
                    $link->id,
                    $link->customer_id,
                    $link->campaign_id,
                    'Re-KYC SMS sent successfully',
                    [
                        'phone' => $link->customer->cellphone,
                        'customer_name' => $link->customer->firstName,
                        'rekyc_url' => $rekycUrl,
                        'otp_expiry' => $rekycOtpExpiry,
                        'custom_message' => $customMessage
                    ]
                );
                
                return true;
        } catch (\Exception $e) {
            Log::error('Re-KYC SMS notification error: ' . $e->getMessage());
            
            // Log SMS failure to database
            RekycLogService::logSmsFailed(
                $link->id,
                $link->customer_id,
                $link->campaign_id,
                $e->getMessage(),
                [
                    'phone' => $link->customer->cellphone,
                    'customer_name' => $link->customer->firstName,
                    'custom_message' => $customMessage,
                    'error_trace' => $e->getTraceAsString()
                ]
            );
            
            return false;
        }
    }
    public function whatsappSend(RekycLink $link, $customMessage)
    {
       try {
      
        $message = $customMessage ? $customMessage . "\n\n" : '';
       
        $whatsappController = app(\AlphaDirect\Http\Controllers\WhatsAppController::class);
        $whatsappController->sendMetaData([
            'mobileNumber' => $link->customer->cellphone,
           
            'message' => $message,
            'customer_id' => $link->customer_id,
            'policyNumber' => '',
            'delivery_method' => 'instant',
            'accessUrl'=>$link->unique_token,
            'expiryDays'=>$link->expires_at,
            'firstName'=>$link->customer->firstName,    

        ]);
        
        // Log successful WhatsApp sending to database
        RekycLogService::logWhatsappSent(
            $link->id,
            $link->customer_id,
            $link->campaign_id,
            'Re-KYC WhatsApp message sent successfully',
            [
                'phone' => $link->customer->cellphone,
                'customer_name' => $link->customer->firstName,
                'rekyc_url' => $link->getAccessUrl(),
                'expiry_days' => $link->expires_at,
                'custom_message' => $customMessage,
                'message_length' => strlen($message)
            ]
        );
        
        return true;
        } catch (\Exception $e) {
            Log::error('Re-KYC WhatsApp notification error: ' . $e->getMessage());
            
            // Log WhatsApp failure to database
            RekycLogService::logWhatsappFailed(
                $link->id,
                $link->customer_id,
                $link->campaign_id,
                $e->getMessage(),
                [
                    'phone' => $link->customer->cellphone,
                    'customer_name' => $link->customer->firstName,
                    'custom_message' => $customMessage,
                    'error_trace' => $e->getTraceAsString()
                ]
            );
            
            return false;
        }
    }
}
