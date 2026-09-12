<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use AlphaDirect\Models\DeduplicationChecks;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use AlphaDirect\Services\DeduplicationStatsService;
use AlphaDirect\Services\GenericNotificationService;

class DeduplicationChecksAdminController extends Controller
{
    protected $statsService;

    public function __construct(DeduplicationStatsService $statsService)
    {
        $this->statsService = $statsService;
    }
    /**
     * Display DeduplicationChecks admin dashboard
     */
    public function index(Request $request)
    {
        try {
            $stats = $this->statsService->getDashboardStats();
            $recentActivities = $this->statsService->getRecentActivities(10);

            return view('admin.deduplication.index', compact('stats', 'recentActivities'));

        } catch (\Exception $e) {
            Log::error('DeduplicationChecks admin dashboard error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load DeduplicationChecks dashboard');
        }
    }

    /**
     * Get deduplication checks data for DataTable
     */
    public function getData(Request $request)
    {
        try {
            Log::info('DeduplicationChecks getData called', $request->all());
            
            $query = DeduplicationChecks::with(['customer', 'policy', 'verifier']);

            // Apply filters
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            if ($request->has('document_upload_status') && $request->document_upload_status) {
                $query->where('document_upload_status', $request->document_upload_status);
            }
            if ($request->has('manual_verification_status') && $request->manual_verification_status) {
                $query->where('manual_verification_status', $request->manual_verification_status);
            }
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Search functionality
            if ($request->has('search') && $request->search && $request->search['value']) {
                $search = $request->search['value'];
                $query->where(function($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('cellphone', 'like', "%{$search}%")
                      ->orWhere('bank_name', 'like', "%{$search}%")
                      ->orWhere('bank_account_number', 'like', "%{$search}%")
                      ->orWhereHas('customer', function($customerQuery) use ($search) {
                          $customerQuery->where('firstName', 'like', "%{$search}%")
                                       ->orWhere('lastName', 'like', "%{$search}%");
                      });
                });
            }

            // Get total count before pagination
            $totalRecords = $query->count();

            // Apply pagination
            $perPage = $request->get('length', 10);
            $page = ($request->get('start', 0) / $perPage) + 1;
            
            $deduplicationChecks = $query->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            // Transform data for DataTable
            $data = $deduplicationChecks->getCollection()->map(function ($check) {
                try {
                    $customer = $check->customer;
                    $customerName = $customer ? $customer->firstName . ' ' . $customer->lastName : 'N/A';
                    
                    return [
                        'id' => $check->id,
                        'customer_name' => $customerName,
                        'email' => $check->email ?? 'N/A',
                        'phone' => $check->cellphone ?? 'N/A',
                        'status' => $this->getStatusBadge($check->status),
                        'document_status' => $this->getDocumentStatusBadge($check->document_upload_status),
                        'verification_status' => $this->getVerificationStatusBadge($check->manual_verification_status),
                        'bank_info' => $this->getBankInfo($check),
                        'created_at' => $check->created_at->format('M d, Y H:i'),
                        'days_since_created' => $check->created_at->diffInDays(Carbon::now()),
                        'is_expired' => $check->link_expires_at && $check->link_expires_at->isPast(),
                        'completion_status' => $this->getCompletionStatus($check),
                        'actions' => $this->getActionButtons($check)
                    ];
                } catch (\Exception $e) {
                    Log::error('Error transforming check data: ' . $e->getMessage(), ['check_id' => $check->id]);
                    return [
                        'id' => $check->id,
                        'customer_name' => 'Error',
                        'email' => 'N/A',
                        'phone' => 'N/A',
                        'status' => '<span class="badge badge-danger">ERROR</span>',
                        'document_status' => '<span class="badge badge-danger">ERROR</span>',
                        'verification_status' => '<span class="badge badge-danger">ERROR</span>',
                        'bank_info' => 'Error',
                        'created_at' => 'N/A',
                        'days_since_created' => 0,
                        'is_expired' => false,
                        'completion_status' => 'error',
                        'actions' => '<span class="text-danger">Error</span>'
                    ];
                }
            });

            $response = [
                'draw' => intval($request->get('draw')),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords,
                'data' => $data
            ];
            
         
            
            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('DeduplicationChecks getData error: ' . $e->getMessage());
            return response()->json([
                'draw' => intval($request->get('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Failed to load data'
            ], 500);
        }
    }

    /**
     * Show deduplication check details
     */
    public function show($id)
    {
        try {
            $deduplicationCheck = DeduplicationChecks::with(['customer', 'policy', 'verifier'])
                ->findOrFail($id);

            $stats = $this->statsService->getCheckStats($deduplicationCheck);
            $activityLogs = $this->statsService->getCheckActivityLogs($deduplicationCheck);

            return view('admin.deduplication.show', compact('deduplicationCheck', 'stats', 'activityLogs'));

        } catch (\Exception $e) {
            Log::error('DeduplicationChecks show error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load deduplication check details');
        }
    }

    /**
     * Edit deduplication check
     */
    public function edit($id)
    {
        try {
            $deduplicationCheck = DeduplicationChecks::with(['customer', 'policy', 'verifier'])
                ->findOrFail($id);

            return view('admin.deduplication.edit', compact('deduplicationCheck'));

        } catch (\Exception $e) {
            Log::error('DeduplicationChecks edit error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load deduplication check for editing');
        }
    }

    /**
     * Update deduplication check
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'manual_verification_status' => 'required|in:pending,approved,rejected',
            'verification_notes' => 'nullable|string|max:1000',
            'suspension_reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $deduplicationCheck = DeduplicationChecks::findOrFail($id);
            
            $updateData = [
                'manual_verification_status' => $request->manual_verification_status,
                'verification_notes' => $request->verification_notes,
                'notes' => $request->notes,
                'verified_by' => auth()->id(),
                'verified_at' => Carbon::now()
            ];

            // Handle suspension
            if ($request->manual_verification_status === 'rejected') {
                $updateData['is_suspended'] = true;
                $updateData['suspended_at'] = Carbon::now();
                $updateData['suspension_reason'] = $request->suspension_reason;
                $updateData['status'] = 'suspended';
            } elseif ($request->manual_verification_status === 'approved') {
                $updateData['is_suspended'] = false;
                $updateData['suspended_at'] = null;
                $updateData['suspension_reason'] = null;
                $updateData['status'] = 'completed';
            }

            $deduplicationCheck->update($updateData);

            // Log activity
            $this->logActivity($deduplicationCheck, 'updated', 'Deduplication check updated by admin');

            return redirect()->route('admin.deduplication.show', $id)
                ->with('success', 'Deduplication check updated successfully');

        } catch (\Exception $e) {
            Log::error('DeduplicationChecks update error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update deduplication check');
        }
    }

    /**
     * Resend notification
     */
    public function resendNotification(Request $request, $id)
    {
       // try {
            $check = DeduplicationChecks::with('customer')->findOrFail($id);
            
            // Check if customer exists
            if (!$check->customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found for this check'
                ], 404);
            }
            
            // Get notification channels from request
            $channels = $request->input('channels', ['email']);
            $customMessage = $request->input('message', '');
            
            // Validate channels
            $validChannels = ['email', 'whatsapp', 'sms'];
            $channels = array_intersect($channels, $validChannels);
            
            if (empty($channels)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select at least one valid notification channel'
                ], 400);
            }
            
            // Generate new access token if not exists
            if (!$check->unique_access_token) {
                $check->update([
                    'unique_access_token' => Str::random(13),
                    'link_expires_at' => Carbon::now()->addDays(30),
                    'status' => 'link_generated'
                ]);
            }
            
           
            $notificationService = new GenericNotificationService();


            $results = $notificationService->sendMultiChannel(
                //'bank_statement_upload',  // hook_slug
                'rekyc_verification',
                $check->customer_id,                      // customer_id
                $channels, // channels
                $customMessage, // custom message
                [                       
                    'policyNumber' => $check->policy_number,
                    'rekyc_url' => $check->getAccessUrl(),
                    'rekyc_link_expiry_days' => $check->link_expires_at->format('M d, Y H:i'),
                    'template_id' => 48
                ]
            );
            //dd($results);
            $successCount = 0;
            $errors = [];
            
            foreach ($results as $channel => $result) {
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errors[] = "{$channel}: " . ($result['error'] ?? 'Unknown error');
                }
            }
            
            // Log the resend activity
            $this->logActivity($check, 'notification_resent', "Notification resent via: " . implode(', ', $channels), [
                'channels' => $channels,
                'custom_message' => $customMessage,
                'success_count' => $successCount,
                'errors' => $errors
            ]);
            
            if ($successCount > 0) {
                return response()->json([
                    'success' => true,
                    'message' => "Notification sent successfully via {$successCount} channel(s): " . implode(', ', $channels),
                    'results' => $results
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send notification via any channel',
                    'errors' => $errors
                ], 500);
            }
            
        // } catch (\Exception $e) {
        //     Log::error('Failed to resend notification: ' . $e->getMessage());
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Failed to resend notification: ' . $e->getMessage()
        //     ], 500);
        // }
    }

    /**
     * Export data
     */
    public function export(Request $request)
    {
        try {
            $query = DeduplicationChecks::with(['customer', 'policy', 'verifier']);

            // Apply filters
            if ($request->status) {
                $query->where('status', $request->status);
            }
            if ($request->document_upload_status) {
                $query->where('document_upload_status', $request->document_upload_status);
            }
            if ($request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $deduplicationChecks = $query->get();

            // Generate CSV
            $filename = 'deduplication_checks_' . date('Y-m-d_H-i-s') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function() use ($deduplicationChecks) {
                $file = fopen('php://output', 'w');
                
                // Headers
                fputcsv($file, [
                    'ID', 'Customer Name', 'Email', 'Phone', 'Status', 
                    'Document Upload Status', 'Verification Status', 'Created At', 
                    'Link Opened At', 'Verified At', 'Bank Name', 'Account Number'
                ]);

                // Data
                foreach ($deduplicationChecks as $check) {
                    fputcsv($file, [
                        $check->id,
                        $check->customer ? $check->customer->firstName . ' ' . $check->customer->lastName : 'N/A',
                        $check->email ?? 'N/A',
                        $check->cellphone ?? 'N/A',
                        $check->status,
                        $check->document_upload_status,
                        $check->manual_verification_status,
                        $check->created_at->format('Y-m-d H:i:s'),
                        $check->link_opened_at ? $check->link_opened_at->format('Y-m-d H:i:s') : 'N/A',
                        $check->verified_at ? $check->verified_at->format('Y-m-d H:i:s') : 'N/A',
                        $check->bank_name ?? 'N/A',
                        $check->bank_account_number ?? 'N/A'
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Export error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export data');
        }
    }


    /**
     * Get completion status for a check
     */
    private function getCompletionStatus($check)
    {
        if ($check->status === 'completed') return 'completed';
        if ($check->status === 'suspended') return 'suspended';
        if ($check->status === 'expired') return 'expired';
        if ($check->document_upload_status === 'uploaded') return 'uploaded';
        if ($check->link_opened_at) return 'opened';
        return 'pending';
    }

    /**
     * Get status badge HTML
     */
    private function getStatusBadge($status)
    {
        $badges = [
            'active' => '<span class="badge badge-primary">ACTIVE</span>',
            'completed' => '<span class="badge badge-success">COMPLETED</span>',
            'suspended' => '<span class="badge badge-danger">SUSPENDED</span>',
            'expired' => '<span class="badge badge-secondary">EXPIRED</span>',
            'cancelled' => '<span class="badge badge-warning">CANCELLED</span>'
        ];
        
        return $badges[$status] ?? '<span class="badge badge-secondary">' . strtoupper($status) . '</span>';
    }

    /**
     * Get document status badge HTML
     */
    private function getDocumentStatusBadge($status)
    {
        $badges = [
            'pending' => '<span class="badge badge-warning">PENDING</span>',
            'uploaded' => '<span class="badge badge-success">UPLOADED</span>',
            'processing' => '<span class="badge badge-info">PROCESSING</span>',
            'verified' => '<span class="badge badge-success">VERIFIED</span>',
            'rejected' => '<span class="badge badge-danger">REJECTED</span>',
            'failed' => '<span class="badge badge-danger">FAILED</span>'
        ];
        
        return $badges[$status] ?? '<span class="badge badge-secondary">' . strtoupper($status) . '</span>';
    }

    /**
     * Get verification status badge HTML
     */
    private function getVerificationStatusBadge($status)
    {
        $badges = [
            'pending' => '<span class="badge badge-warning">PENDING</span>',
            'approved' => '<span class="badge badge-success">APPROVED</span>',
            'rejected' => '<span class="badge badge-danger">REJECTED</span>'
        ];
        
        return $badges[$status] ?? '<span class="badge badge-secondary">' . strtoupper($status) . '</span>';
    }

    /**
     * Get bank information HTML
     */
    private function getBankInfo($check)
    {
        if (!$check->bank_name) {
            return '<span class="text-muted">N/A</span>';
        }
        
        $bankInfo = '<strong>' . e($check->bank_name) . '</strong>';
        
        if ($check->bank_account_number) {
            $maskedAccount = '****' . substr($check->bank_account_number, -4);
            $bankInfo .= '<br><small class="text-muted">' . $maskedAccount . '</small>';
        }
        
        return $bankInfo;
    }

    /**
     * Get action buttons HTML
     */
    private function getActionButtons($check)
    {
        $buttons = '';
        
        // View button
        $buttons .= '<a href="' . route('admin.deduplication.show', $check->id) . '" 
                        class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View Details">
                        <i class="fas fa-eye" style="color: #5d78ff;"></i>
                    </a>';
        
        // Edit button
        $buttons .= '<a href="' . route('admin.deduplication.edit', $check->id) . '" 
                        class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit Check">
                        <i class="fas fa-edit" style="color: #ffb822;"></i>
                    </a>';
        
        // Resend notification button (if applicable)
        if ($check->status === 'active' && !$check->otp_verified_at) {
            $buttons .= '<button type="button" class="btn btn-sm btn-clean btn-icon btn-icon-md" 
                            onclick="resendNotification(' . $check->id . ')" title="Resend Notification">
                            <i class="fas fa-paper-plane" style="color: #1dc9b7;"></i>
                        </button>';
        }
        
        return $buttons;
    }

    /**
     * Log activity
     */
    private function logActivity($check, $action, $description)
    {
        // Implement activity logging based on your system
        Log::info("DeduplicationCheck #{$check->id}: {$action} - {$description}");
    }
}