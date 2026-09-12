<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use AlphaDirect\Models\AdGroupKycLink;
use AlphaDirect\Models\AdGroupKycCampaign;
use AlphaDirect\Services\AdGroupKycService;
use AlphaDirect\Services\AdGroupKycNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AdGroupKycLinkController extends Controller
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
     * Get campaign links data for DataTable - Shows all employees from employer group
     */
    public function getCampaignLinks(Request $request, $id)
    {
        try {
            Log::info('AD Group KYC getCampaignLinks called', ['campaign_id' => $id]);
            
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
            
            Log::info('Campaign found', ['campaign_id' => $id, 'employer_group_id' => $employerGroupId]);

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
            Log::info('Total records found', ['total_records' => $totalRecords]);

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
            // Fix ambiguous column names in ORDER BY
            $orderColumn = $columnName;
            if ($columnName === 'id') {
                $orderColumn = 'egp.policy_id'; // Use policy_id as the main ID for ordering
            } elseif ($columnName === 'customer') {
                $orderColumn = 'c.firstName'; // Order by customer name
            } elseif ($columnName === 'cellphone') {
                $orderColumn = 'c.cellphone'; // Order by cellphone
            } elseif ($columnName === 'status') {
                $orderColumn = 'kyc.status'; // Order by KYC status
            } elseif ($columnName === 'sent') {
                $orderColumn = 'kyc.sent_at'; // Order by sent date
            } elseif ($columnName === 'opened') {
                $orderColumn = 'kyc.status'; // Order by opened status
            } elseif ($columnName === 'otp_verified') {
                $orderColumn = 'kyc.status'; // Order by OTP status
            } elseif ($columnName === 'completed') {
                $orderColumn = 'kyc.status'; // Order by completed status
            } elseif ($columnName === 'expires_at') {
                $orderColumn = 'kyc.expires_at'; // Order by expires date
            }

            $records = $baseQuery->orderBy($orderColumn, $columnSortOrder)
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

                // Format customer name and email like ReKYC
                $customerName = $record->employee_name;
                $customerEmail = $record->email ?: 'N/A';
                $customerCellphone = $record->cellphone ?: 'N/A';

                // Format status indicators with better visual consistency
                $sentStatus = $record->sent_at ? 
                    '<div class="text-center"><i class="fas fa-check-circle text-success" title="Sent"></i><br><small class="text-muted">' . \Carbon\Carbon::parse($record->sent_at)->format('M d, H:i') . '</small></div>' :
                    '<div class="text-center"><i class="fas fa-times text-danger" title="Not Sent"></i></div>';

                $openedStatus = $record->kyc_status === 'opened' || $record->kyc_status === 'otp_verified' || $record->kyc_status === 'completed' ? 
                    '<div class="text-center"><i class="fas fa-check-circle text-success" title="Opened"></i><br><small class="text-muted">' . \Carbon\Carbon::parse($record->opened_at ?? $record->sent_at)->format('M d, H:i') . '</small></div>' :
                    '<div class="text-center"><i class="fas fa-times text-danger" title="Not Opened"></i></div>';

                $otpStatus = $record->kyc_status === 'otp_verified' || $record->kyc_status === 'completed' ? 
                    '<div class="text-center"><i class="fas fa-check-circle text-success" title="OTP Verified"></i><br><small class="text-muted">' . \Carbon\Carbon::parse($record->otp_verified_at ?? $record->sent_at)->format('M d, H:i') . '</small></div>' :
                    '<div class="text-center"><i class="fas fa-times text-danger" title="OTP Not Verified"></i></div>';

                $completedStatus = $record->kyc_status === 'completed' ? 
                    '<div class="text-center"><i class="fas fa-check-circle text-success" title="Completed"></i><br><small class="text-muted">' . \Carbon\Carbon::parse($record->completed_at)->format('M d, H:i') . '</small></div>' :
                    '<div class="text-center"><i class="fas fa-times text-danger" title="Not Completed"></i></div>';

                $expiresAt = $record->expires_at ? 
                    '<div class="text-center"><span class="text-' . (\Carbon\Carbon::parse($record->expires_at)->isPast() ? 'danger' : (\Carbon\Carbon::parse($record->expires_at)->isToday() ? 'warning' : 'muted')) . '">' . 
                    \Carbon\Carbon::parse($record->expires_at)->format('M d, H:i') . '</span></div>' :
                    '<div class="text-center"><span class="text-muted">Never</span></div>';

                $data_arr[] = [
                    'checkbox' => '<input type="checkbox" class="link-checkbox" value="' . ($record->kyc_link_id ?: $record->policy_id) . '">',
                    'id' => $record->kyc_link_id ?: $record->policy_id,
                    'customer' => '<strong>' . $customerName . '</strong>' . 
                                 ($customerEmail !== 'N/A' ? '<br><small class="text-muted">' . $customerEmail . '</small>' : ''),
                    'cellphone' => $customerCellphone !== 'N/A' ? '<i class="fas fa-phone text-primary mr-1"></i>' . $customerCellphone : '<span class="text-muted">N/A</span>',
                    'status' => $kycStatusBadge,
                    'sent' => $sentStatus,
                    'opened' => $openedStatus,
                    'otp_verified' => $otpStatus,
                    'completed' => $completedStatus,
                    'expires_at' => $expiresAt,
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
            Log::error('AD Group KYC campaign employee data error: ' . $e->getMessage(), [
                'campaign_id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to load employee data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get specific link details
     */
    public function getLinkDetails($id)
    {
        try {
            $link = AdGroupKycLink::with(['customer', 'policy', 'campaign', 'activities'])
                ->findOrFail($id);

            $activities = $link->activities()->orderBy('created_at', 'desc')->get();

            return view('admin.ad-group-kyc.link.show', compact('link', 'activities'));

        } catch (\Exception $e) {
            Log::error('Error getting link details: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load link details');
        }
    }

    /**
     * Get links by status
     */
    public function getLinksByStatus(Request $request, $id)
    {
        try {
            $status = $request->input('status', 'all');
            
            $query = AdGroupKycLink::with(['customer', 'policy'])
                ->where('campaign_id', $id);

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            $links = $query->orderBy('created_at', 'desc')->get();

            $data = $links->map(function ($link) {
                return [
                    'id' => $link->id,
                    'employee_name' => $link->customer ? $link->customer->firstName . ' ' . $link->customer->lastName : 'N/A',
                    'employee_email' => $link->customer ? $link->customer->email : 'N/A',
                    'status' => $link->status,
                    'created_at' => $link->created_at->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $links->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting links by status: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load links'], 500);
        }
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
     * Get action buttons HTML for employees
     */
    private function getEmployeeActionButtons($record)
    {
        $buttons = '<div class="btn-group btn-group-sm" role="group">';
        
        // If KYC link exists, show KYC-related actions
        if ($record->kyc_link_id) {
            // View KYC Link button (like ReKYC)
            $buttons .= '<a href="' . route('admin.ad-group-kyc.link.details', $record->kyc_link_id) . '" 
                           class="btn btn-info" title="View Details" style="padding: 4px 8px;">
                <i class="fas fa-eye"></i>
            </a>';
            
            // Resend button (like ReKYC)
            $buttons .= '<button type="button" class="btn btn-success" 
                            onclick="resendNotification(' . $record->kyc_link_id . ')" title="Resend Notification" style="padding: 4px 8px;">
                <i class="fas fa-paper-plane"></i>
            </button>';
        } else {
            // If no KYC link exists, show generate link button
            $buttons .= '<button class="btn btn-primary generate-link-btn" data-policy-id="' . $record->policy_id . '" data-customer-id="' . $record->customer_id . '" title="Generate KYC Link" style="padding: 4px 8px;">
                <i class="fas fa-plus"></i>
            </button>';
        }
        
        $buttons .= '</div>';
        return $buttons;
    }
}