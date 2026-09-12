<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Models\DuplicateCustomer;
use AlphaDirect\Customer;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DuplicateCustomerController extends Controller
{
    /**
     * Display duplicate customers dashboard
     */
    public function index(Request $request)
    {
        try {
            $stats = $this->getDashboardStats();
            $recentDuplicates = $this->getRecentDuplicates(10);

            return view('admin.duplicate-customers.index', compact('stats', 'recentDuplicates'));

        } catch (\Exception $e) {
            Log::error('Duplicate customers dashboard error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load duplicate customers dashboard');
        }
    }

    /**
     * Show duplicate customer details
     */
    public function show($id)
    {
        try {
            $duplicate = DuplicateCustomer::with(['customer', 'resolver'])
                ->findOrFail($id);

            // Get all duplicate customers with the same duplicate criteria
            $relatedDuplicates = $this->getRelatedDuplicates($duplicate);
            
            // Get all policies for the related customers
            $customerPolicies = $this->getCustomerPolicies($relatedDuplicates);
            
            // Get all policies for the main duplicate customer
            $mainCustomerPolicies = $this->getMainCustomerPolicies($duplicate->customer_id);

            return view('admin.duplicate-customers.show', compact('duplicate', 'relatedDuplicates', 'customerPolicies', 'mainCustomerPolicies'));

        } catch (\Exception $e) {
            Log::error('Duplicate customer show error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load duplicate customer details');
        }
    }

    /**
     * Get related duplicate customers based on duplicate criteria
     */
    private function getRelatedDuplicates($duplicate)
    {
        $query = DuplicateCustomer::with(['customer', 'resolver']);

        switch ($duplicate->duplicate_type) {
            case 'omang_passport':
                $query->where(function($q) use ($duplicate) {
                    if ($duplicate->omang_number) {
                        $q->where('omang_number', $duplicate->omang_number);
                    }
                    if ($duplicate->passport_number) {
                        $q->orWhere('passport_number', $duplicate->passport_number);
                    }
                });
                break;

            case 'cellphone_email':
                $query->where(function($q) use ($duplicate) {
                    if ($duplicate->cellphone) {
                        $q->where('cellphone', $duplicate->cellphone);
                    }
                    if ($duplicate->email) {
                        $q->orWhere('email', $duplicate->email);
                    }
                });
                break;

            case 'multiple_accounts':
                $query->where('customer_id', $duplicate->customer_id);
                break;
        }

        return $query->where('id', '!=', $duplicate->id)->get();
    }

    /**
     * Get policies for related customers
     */
    private function getCustomerPolicies($relatedDuplicates)
    {
        $customerIds = $relatedDuplicates->pluck('customer_id')->unique();
        
        if ($customerIds->isEmpty()) {
            return collect();
        }

        // Get policies for these customers
        $policies = Policy::whereIn('customer_id', $customerIds)
            ->with(['product', 'customer'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('customer_id');

        return $policies;
    }

    /**
     * Get policies for the main duplicate customer
     */
    private function getMainCustomerPolicies($customerId)
    {
        return Policy::where('customer_id', $customerId)
            ->with(['product', 'customer'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Delete duplicate customer record
     */
    public function destroy($id)
    {
        try {
            $duplicate = DuplicateCustomer::findOrFail($id);
            $duplicate->delete();

            return response()->json([
                'success' => true,
                'message' => 'Duplicate customer record deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Delete duplicate customer error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete duplicate customer record'
            ], 500);
        }
    }

    /**
     * Show edit form for duplicate customer
     */
    public function edit($id)
    {
        try {
            $duplicate = DuplicateCustomer::with(['customer', 'resolver'])->findOrFail($id);
            
            return view('admin.duplicate-customers.edit', compact('duplicate'));

        } catch (\Exception $e) {
            Log::error('Duplicate customer edit error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load duplicate customer for editing');
        }
    }

    /**
     * Update duplicate customer record
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'cellphone' => 'nullable|string|max:255',
                'omang_number' => 'nullable|string|max:255',
                'passport_number' => 'nullable|string|max:255',
                'bank_account_number' => 'nullable|string|max:255',
                'bank_name' => 'nullable|string|max:255',
                'bank_branch' => 'nullable|string|max:255',
                'billing' => 'nullable|string|max:255',
                'duplicate_type' => 'required|in:omang_passport,cellphone_email,multiple_accounts',
                'duplicate_reason' => 'required|string|max:1000',
                'status' => 'required|in:pending,reviewed,resolved,ignored',
                'resolution_notes' => 'nullable|string|max:1000',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $duplicate = DuplicateCustomer::findOrFail($id);

            $updateData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'cellphone' => $request->cellphone,
                'omang_number' => $request->omang_number,
                'passport_number' => $request->passport_number,
                'bank_account_number' => $request->bank_account_number,
                'bank_name' => $request->bank_name,
                'bank_branch' => $request->bank_branch,
                'billing' => $request->billing,
                'duplicate_type' => $request->duplicate_type,
                'duplicate_reason' => $request->duplicate_reason,
                'status' => $request->status,
                'notes' => $request->notes
            ];

            if ($request->status === 'resolved') {
                $updateData['resolved_by'] = auth()->id();
                $updateData['resolved_at'] = now();
                $updateData['resolution_notes'] = $request->resolution_notes;
            }

            $duplicate->update($updateData);

            return redirect()->route('admin.duplicate-customers.show', $duplicate->id)
                ->with('success', 'Duplicate customer record updated successfully');

        } catch (\Exception $e) {
            Log::error('Update duplicate customer error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to update duplicate customer record')
                ->withInput();
        }
    }

    /**
     * Update duplicate customer status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,reviewed,resolved,ignored',
                'resolution_notes' => 'nullable|string|max:1000',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $duplicate = DuplicateCustomer::findOrFail($id);

            $updateData = [
                'status' => $request->status,
                'notes' => $request->notes
            ];

            if ($request->status === 'resolved') {
                $updateData['resolved_by'] = auth()->id();
                $updateData['resolved_at'] = now();
                $updateData['resolution_notes'] = $request->resolution_notes;
            }

            $duplicate->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Duplicate status updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Update duplicate status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update duplicate status'
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    private function getDashboardStats()
    {
        try {
            $stats = [
                'total_duplicates' => DuplicateCustomer::count(),
                'pending_duplicates' => DuplicateCustomer::pending()->count(),
                'reviewed_duplicates' => DuplicateCustomer::reviewed()->count(),
                'resolved_duplicates' => DuplicateCustomer::resolved()->count(),
                'ignored_duplicates' => DuplicateCustomer::ignored()->count(),
                'omang_passport_duplicates' => DuplicateCustomer::byType('omang_passport')->count(),
                'cellphone_email_duplicates' => DuplicateCustomer::byType('cellphone_email')->count(),
                'multiple_accounts_duplicates' => DuplicateCustomer::byType('multiple_accounts')->count(),
                'today_duplicates' => DuplicateCustomer::whereDate('created_at', today())->count(),
                'this_week_duplicates' => DuplicateCustomer::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'this_month_duplicates' => DuplicateCustomer::whereMonth('created_at', now()->month)->count(),
            ];

            // Calculate resolution rate
            $total = $stats['total_duplicates'];
            $resolved = $stats['resolved_duplicates'];
            $stats['resolution_rate'] = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;

            return $stats;

        } catch (\Exception $e) {
            Log::error('Get dashboard stats error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent duplicates
     */
    private function getRecentDuplicates($limit = 10)
    {
        try {
            return DuplicateCustomer::with(['customer'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

        } catch (\Exception $e) {
            Log::error('Get recent duplicates error: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get duplicates data for DataTable
     */
    public function data(Request $request)
    {
        try {
            ## Read value
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
            $totalRecords = DuplicateCustomer::select('count(*) as allcount')->count();

            // Fetch records
            $records = DuplicateCustomer::with(['customer', 'resolver']);

            // Apply search filter
            if ($searchValue != null) {
                $records->where(function($query) use ($searchValue) {
                    $query->where('first_name', 'like', '%' . $searchValue . '%')
                          ->orWhere('last_name', 'like', '%' . $searchValue . '%')
                          ->orWhere('email', 'like', '%' . $searchValue . '%')
                          ->orWhere('cellphone', 'like', '%' . $searchValue . '%')
                          ->orWhere('omang_number', 'like', '%' . $searchValue . '%')
                          ->orWhere('passport_number', 'like', '%' . $searchValue . '%')
                          ->orWhere('bank_account_number', 'like', '%' . $searchValue . '%')
                          ->orWhere('duplicate_type', 'like', '%' . $searchValue . '%');
                });
            }

            // Apply additional filters from request
            if ($request->filled('status')) {
                $records->where('status', $request->status);
            }

            if ($request->filled('duplicate_type')) {
                $records->where('duplicate_type', $request->duplicate_type);
            }

            if ($request->filled('date_from')) {
                $records->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $records->whereDate('created_at', '<=', $request->date_to);
            }

            // Get total records with filter
            $totalRecordswithFilter = $records->count();

            // Apply ordering
            if ($columnName && $columnSortOrder) {
                $records->orderBy($columnName, $columnSortOrder);
            } else {
                $records->orderBy('created_at', 'DESC');
            }

            // Apply pagination
            $records = $records->skip($start)->take($rowperpage)->get();

            $data_arr = array();

            foreach ($records as $record) {
                // Format name
                $name = '<strong>' . $record->first_name . ' ' . $record->last_name . '</strong><br><small class="text-muted">ID: ' . $record->customer_id . '</small>';

                // Format contact info
                $contactInfo = '';
                if ($record->email) {
                    $contactInfo .= '<i class="flaticon2-mail"></i> ' . $record->email . '<br>';
                }
                if ($record->cellphone) {
                    $contactInfo .= '<i class="flaticon2-phone"></i> ' . $record->cellphone;
                }
                if (empty($contactInfo)) {
                    $contactInfo = 'N/A';
                }

                // Format documents
                $documents = '';
                if ($record->omang_number) {
                    $documents .= '<span class="kt-badge kt-badge--info duplicate-type-badge">Omang: ' . $record->omang_number . '</span><br>';
                }
                if ($record->passport_number) {
                    $documents .= '<span class="kt-badge kt-badge--brand duplicate-type-badge">Passport: ' . $record->passport_number . '</span>';
                }
                if (empty($documents)) {
                    $documents = 'N/A';
                }

                // Format banking
                $banking = '';
                if ($record->bank_account_number) {
                    $banking .= '<strong>' . $record->bank_account_number . '</strong><br>';
                }
                if ($record->bank_name) {
                    $banking .= '<small class="text-muted">' . $record->bank_name . '</small>';
                }
                if (empty($banking)) {
                    $banking = 'N/A';
                }

                // Format duplicate type
                $typeBadgeClass = $record->duplicate_type == 'omang_passport' ? 'info' : ($record->duplicate_type == 'cellphone_email' ? 'success' : 'warning');
                $duplicateType = '<span class="' . $typeBadgeClass . '">' . ucfirst(str_replace('_', ' ', $record->duplicate_type)) . '</span>';

                // Format status
                $statusIcon = $record->status == 'pending' ? 'hourglass' : ($record->status == 'reviewed' ? 'eye' : ($record->status == 'resolved' ? 'check-mark' : 'close'));
                $status = '<span class="status-' . $record->status . '"><i class="flaticon2-' . $statusIcon . '"></i> ' . ucfirst($record->status) . '</span>';

                // Format created date
                $createdAt = $record->created_at->format('M d, Y H:i');

                // Get duplicate count for this customer
                $duplicateCount = $this->getDuplicateCount($record);
                
                // Get policy numbers for this customer
                $policyNumbers = $this->getCustomerPolicyNumbers($record->customer_id);

                // Format actions
                $actions = '<div class="btn-group" role="group">
                    <a href="' . route('admin.duplicate-customers.show', $record->id) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View Details">
                    <i class="fa fa-eye"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit" onclick="editDuplicate(' . $record->id . ')">
                        <i class="flaticon2-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Delete" onclick="deleteDuplicate(' . $record->id . ')">
                        <i class="flaticon2-trash"></i>
                    </button>
                </div>';

                $data_arr[] = array(
                    "id" => $record->id,
                    "customer_id" => $record->customer_id,
                    "name" => $name,
                    "contact_info" => $contactInfo,
                    "documents" => $documents,
                    "banking" => $banking,
                    "duplicate_type" => $duplicateType,
                    "status" => $status,
                    "duplicate_count" => $duplicateCount,
                    "policy_numbers" => $policyNumbers,
                    "created_at" => $createdAt,
                    "actions" => $actions
                );
            }

            $response = array(
                "draw" => intval($draw),
                "iTotalRecords" => $totalRecords,
                "iTotalDisplayRecords" => $totalRecordswithFilter,
                "aaData" => $data_arr
            );

            echo json_encode($response);
            exit;

        } catch (\Exception $e) {
            Log::error('Get duplicates data error: ' . $e->getMessage());
            
            $response = array(
                "draw" => intval($request->get('draw')),
                "iTotalRecords" => 0,
                "iTotalDisplayRecords" => 0,
                "aaData" => []
            );

            echo json_encode($response);
            exit;
        }
    }

    /**
     * Export duplicate customers data to CSV
     */
    public function export(Request $request)
    {
        try {
            // Get all duplicate customers with relationships
            $duplicates = DuplicateCustomer::with(['customer', 'resolver'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Set headers for CSV download
            $filename = 'duplicate_customers_' . date('Y-m-d_H-i-s') . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ];

            $callback = function() use ($duplicates) {
                $file = fopen('php://output', 'w');
                
                // CSV Headers
                fputcsv($file, [
                    'ID',
                    'Customer ID',
                    'Customer Name',
                    'Email',
                    'Cellphone',
                    'Omang Number',
                    'Passport Number',
                    'Bank Account Number',
                    'Bank Name',
                    'Bank Branch',
                    'Billing',
                    'Duplicate Type',
                    'Duplicate Count',
                    'Policy Count',
                    'Duplicate Reason',
                    'Status',
                    'Resolution Notes',
                    'Notes',
                    'Resolved By',
                    'Resolved At',
                    'Created At',
                    'Updated At'
                ]);

                // CSV Data
                foreach ($duplicates as $duplicate) {
                    // Get duplicate count for this record
                    $duplicateCount = $this->getDuplicateCountForExport($duplicate);
                    
                    // Get policy count for this customer
                    $policyCount = $this->getPolicyCountForExport($duplicate->customer_id);
                    
                    fputcsv($file, [
                        $duplicate->id,
                        $duplicate->customer_id,
                        $duplicate->first_name . ' ' . $duplicate->last_name,
                        $duplicate->email,
                        $duplicate->cellphone,
                        $duplicate->omang_number,
                        $duplicate->passport_number,
                        $duplicate->bank_account_number,
                        $duplicate->bank_name,
                        $duplicate->bank_branch,
                        $duplicate->billing,
                        ucfirst(str_replace('_', ' ', $duplicate->duplicate_type)),
                        $duplicateCount,
                        $policyCount,
                        $duplicate->duplicate_reason,
                        ucfirst($duplicate->status),
                        $duplicate->resolution_notes,
                        $duplicate->notes,
                        $duplicate->resolver ? $duplicate->resolver->name : '',
                        $duplicate->resolved_at ? $duplicate->resolved_at->format('Y-m-d H:i:s') : '',
                        $duplicate->created_at->format('Y-m-d H:i:s'),
                        $duplicate->updated_at->format('Y-m-d H:i:s')
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Export duplicate customers error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export duplicate customers data');
        }
    }

    /**
     * Get duplicate count for a specific duplicate record
     */
    private function getDuplicateCount($record)
    {
        try {
            $count = 0;
            
            switch ($record->duplicate_type) {
                case 'omang_passport':
                    $count = DuplicateCustomer::where(function($q) use ($record) {
                        if ($record->omang_number) {
                            $q->where('omang_number', $record->omang_number);
                        }
                        if ($record->passport_number) {
                            $q->orWhere('passport_number', $record->passport_number);
                        }
                    })->count();
                    break;
                    
                case 'cellphone_email':
                    $count = DuplicateCustomer::where(function($q) use ($record) {
                        if ($record->cellphone) {
                            $q->where('cellphone', $record->cellphone);
                        }
                        if ($record->email) {
                            $q->orWhere('email', $record->email);
                        }
                    })->count();
                    break;
                    
                case 'multiple_accounts':
                    $count = DuplicateCustomer::where('customer_id', $record->customer_id)
                        ->where('duplicate_type', 'multiple_accounts')
                        ->count();
                    break;
            }
            
            return '<span class="kt-badge kt-badge--info">' . $count . '</span>';
            
        } catch (\Exception $e) {
            Log::error('Get duplicate count error: ' . $e->getMessage());
            return '<span class="kt-badge kt-badge--secondary">N/A</span>';
        }
    }

    /**
     * Get policy count for a customer
     */
    private function getCustomerPolicyNumbers($customerId)
    {
        try {
            // Check if customer ID is valid
            if (empty($customerId) || !is_numeric($customerId)) {
                return '<span class="text-muted">Invalid customer</span>';
            }

            // Get total policy count for this customer
            $totalPolicies = Policy::where('customer_id', $customerId)->count();
            
            if ($totalPolicies == 0) {
                return '<span class="text-muted">No policies</span>';
            }
            
            // Return policy count with badge
            return $totalPolicies . ' policies';
            
        } catch (\Exception $e) {
            Log::error('Get policy count error for customer ' . $customerId . ': ' . $e->getMessage());
            return '<span class="text-muted">Error loading</span>';
        }
    }

    /**
     * Get duplicate count for export (returns numeric value)
     */
    private function getDuplicateCountForExport($record)
    {
        try {
            $count = 0;
            
            switch ($record->duplicate_type) {
                case 'omang_passport':
                    $count = DuplicateCustomer::where(function($q) use ($record) {
                        if ($record->omang_number) {
                            $q->where('omang_number', $record->omang_number);
                        }
                        if ($record->passport_number) {
                            $q->orWhere('passport_number', $record->passport_number);
                        }
                    })->count();
                    break;
                    
                case 'cellphone_email':
                    $count = DuplicateCustomer::where(function($q) use ($record) {
                        if ($record->cellphone) {
                            $q->where('cellphone', $record->cellphone);
                        }
                        if ($record->email) {
                            $q->orWhere('email', $record->email);
                        }
                    })->count();
                    break;
                    
                case 'multiple_accounts':
                    $count = DuplicateCustomer::where('customer_id', $record->customer_id)
                        ->where('duplicate_type', 'multiple_accounts')
                        ->count();
                    break;
            }
            
            return $count;
            
        } catch (\Exception $e) {
            Log::error('Get duplicate count for export error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get policy count for export (returns numeric value)
     */
    private function getPolicyCountForExport($customerId)
    {
        try {
            // Check if customer ID is valid
            if (empty($customerId) || !is_numeric($customerId)) {
                return 0;
            }

            // Get total policy count for this customer
            $totalPolicies = Policy::where('customer_id', $customerId)->count();
            
            return $totalPolicies;
            
        } catch (\Exception $e) {
            Log::error('Get policy count for export error for customer ' . $customerId . ': ' . $e->getMessage());
            return 0;
        }
    }
}
