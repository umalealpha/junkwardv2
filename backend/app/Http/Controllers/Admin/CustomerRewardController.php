<?php

namespace AlphaDirect\Http\Controllers\Admin;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlphaDirect\Models\CustomerBenefit;
use AlphaDirect\Models\Benefit;
use AlphaDirect\Models\RewardTier;
use AlphaDirect\Customer;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class CustomerRewardController extends Controller
{
    /**
     * Show a list of customers with points and tier_id
     *
     * @return View customer rewards index page
     */
    public function index()
    {
        if (auth()->user()->hasPermissionTo('customer-list')) {
            return view("admin.customer_rewards.index");
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * Get customers with customer_rewards for DataTable
     */
    public function data(Request $request)
    {
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

        // Total records - customers with points and tier_id
        $totalRecords = Customer::whereNotNull('point')
            ->whereNotNull('tier_id')
            ->where('point', '>', 0)
            ->count();

        // Fetch records - customers with points and tier_id
        $records = Customer::with(['rewardTier', 'profile', 'customerRewards.benefit'])
            ->whereNotNull('point')
            ->whereNotNull('tier_id')
            ->where('point', '>', 0)
            ->orderBy('id', 'DESC');

        if ($searchValue != null) {
            $cId = \AlphaDirect\Policy::where('policyNumber', 'like', '%' . $searchValue . '%')->pluck('customer_id')->toArray();
            $records->where(function($query) use ($searchValue, $cId) {
                $query->where('id', 'like', '%' . $searchValue . '%')
                    ->orWhere('firstName', 'like', '%' . $searchValue . '%')
                    ->orWhere('lastName', 'like', '%' . $searchValue . '%')
                    ->orWhere('email', 'like', '%' . $searchValue . '%')
                    ->orWhereIn('firstName', explode(" ", $searchValue))
                    ->orWhereIn('lastName', explode(" ", $searchValue))
                    ->orWhereIn('id', $cId);
            });
        }

        if ($searchValue == null) {
            $totalRecordswithFilter = Customer::whereNotNull('point')
                ->whereNotNull('tier_id')
                ->where('point', '>', 0)
                ->count();
        } else {
            $totalRecordswithFilter = $records->count();
        }

        $records = $records->skip($start)
            ->take($rowperpage)
            ->get([
                'id',
                'firstName',
                'lastName',
                'email',
                'cellphone',
                'point',
                'use_point',
                'tier_id',
                'created_at',
            ]);

        $data_arr = array();
        $sno = $start + 1;
        foreach ($records as $record) {
            $id = $record['id'] ?? 'N/A';
            
            // Get tier name
            $tierName = $record->rewardTier ? $record->rewardTier->name : 'N/A';
            
            // Get customer rewards count and info
            $customerRewards = $record->customerRewards;
            $rewardsCount = $customerRewards->count();
            $activeRewards = $customerRewards->where('status', 'active')->count();
            $claimedRewards = $customerRewards->where('status', 'claimed')->count();
            
            if ($rewardsCount > 0) {
                $rewardsInfo = "<strong>{$rewardsCount}</strong> total rewards<br>";
                $rewardsInfo .= "<span class='kt-badge kt-badge--success kt-badge--inline kt-badge--pill'>{$activeRewards} Active</span> ";
                $rewardsInfo .= "<span class='kt-badge kt-badge--warning kt-badge--inline kt-badge--pill'>{$claimedRewards} Claimed</span>";
            } else {
                $rewardsInfo = "<span class='kt-badge kt-badge--info kt-badge--inline kt-badge--pill'>No Rewards Assigned</span>";
            }
            
            // Format name
            $name = ucwords(strtolower($record['firstName'] . ' ' . $record['lastName']));

            // Actions
            $actions = '';
            if (auth()->user()->can('customer-edit')) {
                $actions .= '<a href="' . route('customer-rewards.edit', $record['id']) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Edit Benefits">
                                <i class="la la-edit"></i>
                            </a>';
            } else {
                $actions .= '<a href="' . route('customer-rewards.edit', $record['id']) . '" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View Benefits">
                                <i class="flaticon-eye"></i>
                            </a>';
            }

            $data_arr[] = array(
                "id" => $id,
                "fullName" => $name,
                "cellphone" => $record['cellphone'] ?? 'N/A',
                "email" => $record['email'],
                "rewards" => $rewardsInfo,
                "points" => number_format($record['point']),
                "use_point" => number_format($record['use_point'] ?? 0),
                "tier" => $tierName,
                "created_at" => Carbon::parse($record['created_at'])->timezone('Africa/Johannesburg')->format('Y-m-d H:i'),
                "actions" => $actions,
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
    }

    /**
     * Show the form for editing customer benefits
     */
    public function edit($id)
    {
        if (auth()->user()->hasPermissionTo('customer-edit')) {
            // Find customer (any customer can have rewards)
            $customer = Customer::with('rewardTier')->find($id);
            
            if (!$customer) {
                return \Illuminate\Support\Facades\Redirect::back()
                    ->with('error', 'Customer not found.');
            }
            
            $benefits = Benefit::all();
            $customerRewards = CustomerBenefit::with('benefit')
                ->where('customer_id', $id)
                ->get();
            
            return view("admin.customer_rewards.edit", compact('customer', 'benefits', 'customerRewards'));
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to access this page!');
        }
    }

    /**
     * Update customer benefits
     */
    public function update(Request $request, $id)
    {
        if (auth()->user()->hasPermissionTo('customer-edit')) {
            // Debug: Log the incoming request data
            // \Log::info('Customer Rewards Update Request', [
            //     'customer_id' => $id,
            //     'benefits_data' => $request->all(),
            //     'benefits_count' => $request->has('benefits') ? count($request->benefits) : 0
            // ]);

            $request->validate([
                'benefits' => 'array',
                'benefits.*.benefit_id' => 'required|exists:benefits,id',
                'benefits.*.status' => 'required|in:active,claimed,expired',
                'benefits.*.expiry_date' => 'nullable|date',
                'benefits.*.claim_date' => 'nullable|date',
            ]);

            //try {
                // Get existing benefit IDs for this customer
                $existingBenefitIds = CustomerBenefit::where('customer_id', $id)
                    ->pluck('id')
                    ->toArray();
                
               // \Log::info('Existing benefit IDs', ['ids' => $existingBenefitIds]);

                $updatedCount = 0;
                $createdCount = 0;
                $processedIds = [];

                if ($request->has('benefits') && is_array($request->benefits)) {
                    foreach ($request->benefits as $index => $benefitData) {
                      //  \Log::info('Processing benefit', ['index' => $index, 'data' => $benefitData]);
                        
                        // Skip if benefit_id is empty or null
                        if (empty($benefitData['benefit_id'])) {
                           // \Log::info('Skipping empty benefit_id', ['index' => $index]);
                            continue;
                        }
                        
                        $data = [
                            'customer_id' => $id,
                            'benefit_id' => $benefitData['benefit_id'],
                            'status' => $benefitData['status'],
                            'expiry_date' => !empty($benefitData['expiry_date']) ? $benefitData['expiry_date'] : null,
                            'claim_date' => !empty($benefitData['claim_date']) ? $benefitData['claim_date'] : null,
                        ];
                        
                        // If status is claimed and no claim_date provided, set current date
                        if ($benefitData['status'] === 'claimed' && empty($data['claim_date'])) {
                            $data['claim_date'] = now()->format('Y-m-d');
                        }
                        if ($benefitData['status'] ==='active' ) {
                            $data['claim_date'] = null;
                        }
                        // Check if this is an existing record (has ID) or new record
                        if (!empty($benefitData['id']) && in_array($benefitData['id'], $existingBenefitIds)) {
                            // Update existing record
                           // \Log::info('Updating existing benefit', ['id' => $benefitData['id'], 'data' => $data]);
                            CustomerBenefit::where('id', $benefitData['id'])->update($data);
                            $processedIds[] = $benefitData['id'];
                            $updatedCount++;
                        } else {
                            // Create new record
                           // \Log::info('Creating new benefit', ['data' => $data]);
                            CustomerBenefit::create($data);
                            $createdCount++;
                        }
                    }
                }

                // Delete benefits that are no longer in the form
                $benefitsToDelete = array_diff($existingBenefitIds, $processedIds);
                if (!empty($benefitsToDelete)) {
                   // \Log::info('Deleting removed benefits', ['ids' => $benefitsToDelete]);
                    CustomerBenefit::whereIn('id', $benefitsToDelete)->delete();
                }

                $totalCount = $updatedCount + $createdCount;
                $message = "Customer rewards updated successfully. ";
                $message .= $updatedCount > 0 ? "Updated: {$updatedCount} reward(s). " : "";
                $message .= $createdCount > 0 ? "Created: {$createdCount} new reward(s). " : "";
                $message .= count($benefitsToDelete) > 0 ? "Removed: " . count($benefitsToDelete) . " reward(s)." : "";

                if ($totalCount == 0 && count($benefitsToDelete) == 0) {
                    $message = "No changes made to customer rewards.";
                }

                // \Log::info('Update completed', [
                //     'updated_count' => $updatedCount,
                //     'created_count' => $createdCount,
                //     'deleted_count' => count($benefitsToDelete),
                //     'message' => $message
                // ]);

                return redirect()->route('customer-rewards.index')
                    ->with('success', $message);
                    
            // } catch (\Exception $e) {
            //     \Log::error('Error updating customer rewards', [
            //         'customer_id' => $id,
            //         'error' => $e->getMessage(),
            //         'trace' => $e->getTraceAsString()
            //     ]);
                
            //     return redirect()->back()
            //         ->with('error', 'Error updating customer rewards: ' . $e->getMessage())
            //         ->withInput();
            // }
        } else {
            return \Illuminate\Support\Facades\Redirect::back()
                ->with('error', 'Sorry! You do not have permission to perform this action!');
        }
    }

    /**
     * Get customer benefits for AJAX
     */
    public function getCustomerBenefits($customerId)
    {
        $benefits = CustomerBenefit::with('benefit')
            ->where('customer_id', $customerId)
            ->get()
            ->map(function($cb) {
                return [
                    'id' => $cb->id,
                    'benefit_id' => $cb->benefit_id,
                    'benefit_name' => $cb->benefit->tag,
                    'status' => $cb->status,
                    'expiry_date' => $cb->expiry_date,
                    'claim_date' => $cb->claim_date,
                ];
            });
            
        return response()->json($benefits);
    }


} 