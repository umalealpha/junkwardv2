<?php

namespace AlphaDirect\Http\Controllers;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlphaDirect\Models\CustomerBenefit;
use AlphaDirect\Models\Benefit;
use AlphaDirect\Models\RewardTier;
use AlphaDirect\Customer;
use AlphaDirect\CustomerPoint;
use Illuminate\Support\Facades\DB;

class CustomerRewardController extends Controller
{
    public function index()
    {
        $rewards = CustomerBenefit::with(['benefit', 'tier'])->get();
        return view('admin.customer_rewards.index', compact('rewards'));
    }

    public function create()
    {
        $benefits = Benefit::all();
        return view('admin.customer_rewards.create', compact('benefits'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'benefit_id' => 'required|integer|exists:benefits,id',
            'status' => 'required|in:active,claimed,expired',
            'expiry_date' => 'nullable|date',
        ]);
        $data = $validated;
        if ($request->status === 'claimed') {
            $data['claim_date'] = $request->input('claim_date');
        } else {
            $data['claim_date'] = null;
        }
        CustomerBenefit::create($data);
        return redirect()->route('customer-rewards.index')->with('success', 'Customer reward created successfully.');
    }

    public function show($id)
    {
        $reward = CustomerBenefit::with(['benefit'])->findOrFail($id);
        return view('admin.customer_rewards.show', compact('reward'));
    }

    public function edit($id)
    {
        $customer = Customer::findOrFail($id);
        $customerRewards = CustomerBenefit::where('customer_id', $id)->get();
        $benefits = Benefit::all();
        return view('admin.customer_rewards.edit', compact('customer', 'customerRewards', 'benefits'));
    }

    public function update(Request $request, $id)
    {
        //dd($id);
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'benefit_id' => 'required|integer|exists:benefits,id',
            'status' => 'required|in:active,claimed,expired',
            'expiry_date' => 'nullable|date',
        ]);
        $data = $validated;
        if ($request->status === 'claimed') {
            $data['claim_date'] = $request->input('claim_date');
        } else {
            $data['claim_date'] = null;
        }
        $reward = CustomerBenefit::findOrFail($id);
        $reward->update($data);
        return redirect()->route('customer-rewards.index')->with('success', 'Customer reward updated successfully.');
    }

    public function destroy($id)
    {
        $reward = CustomerBenefit::findOrFail($id);
        $reward->delete();
        return redirect()->route('customer-rewards.index')->with('success', 'Customer reward deleted successfully.');
    }

    public function all($customerId)
    {
        $rewards = CustomerBenefit::with('benefit')
            ->where('customer_id', $customerId)
            ->get()
            ->map(function($cb) {
                return [
                    'id' => $cb->id,
                    'tag' => $cb->benefit->tag,
                    'status' => $cb->status,
                    'expiry_date' => $cb->expiry_date,
                    'claim_date' => $cb->claim_date,
                ];
            });
        return response()->json($rewards);
    }

    public function active($customerId)
    {
        $rewards = CustomerBenefit::with('benefit')
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->get()
            ->map(function($cb) {
                return [
                    'id' => $cb->id,
                    'tag' => $cb->benefit->tag,
                    'status' => $cb->status,
                    'expiry_date' => $cb->expiry_date,
                    'claim_date' => $cb->claim_date,
                ];
            });
        return response()->json($rewards);
    }

    public function inactive($customerId)
    {
        $rewards = CustomerBenefit::with('benefit')
            ->where('customer_id', $customerId)
            ->whereIn('status', ['claimed', 'expired'])
            ->get()
            ->map(function($cb) {
                return [
                    'id' => $cb->id,
                    'tag' => $cb->benefit->tag,
                    'status' => $cb->status,
                    'expiry_date' => $cb->expiry_date,
                    'claim_date' => $cb->claim_date,
                ];
            });
        return response()->json($rewards);
    }

    public function history($customerId)
    {
        // You can expand this as needed
        return $this->all($customerId);
    }

    public function rewards($customerId)
    {
        // Get customer information
        $customer = Customer::findOrFail($customerId);
        $customerPoints = $customer->point ?? 0;
        
        // Get all available benefits where points required is less than or equal to customer's points
        $availableBenefits = Benefit::where('point', '<=', $customerPoints)
            ->where('status', 1) // Assuming status 1 means active
            ->get()
            ->map(function($benefit) {
                return [
                    'id' => $benefit->id,
                    'tag' => $benefit->tag,
                    'type' => $benefit->type,
                    'price' => $benefit->price,
                    'point' => $benefit->point,
                    'image' => $benefit->image ? \AlphaDirect\Helper::getCloudFrontURL($benefit->image) : null,
                    'status' => $benefit->status,
                ];
            });
        
        // Get customer's claimed benefits
        $customerBenefits = CustomerBenefit::with('benefit')
            ->where('customer_id', $customerId)
            ->get()
            ->map(function($cb) {
                return [
                    'id' => $cb->id,
                    'benefit_id' => $cb->benefit->id,
                    'tag' => $cb->benefit->tag,
                    'type' => $cb->benefit->type,
                    'price' => $cb->benefit->price,
                    'point' => $cb->benefit->point,
                    'image' => $cb->benefit->image ? \AlphaDirect\Helper::getCloudFrontURL($cb->benefit->image) : null,
                    'status' => $cb->status,
                    'expiry_date' => $cb->expiry_date,
                    'claim_date' => $cb->claim_date,
                ];
            });
            
        return response()->json([
            'customer_points' => $customerPoints,
            'available_benefits' => $availableBenefits,
            'customer_benefits' => $customerBenefits
        ]);
    }

    public function tier($customerId)
    {
        $customer = Customer::with('rewardTier')->findOrFail($customerId);

        // Get all customer points breakdown
        $customerPoints = CustomerPoint::where('customer_id', $customerId)->get();
        
        // Initialize point categories
        $pointsBreakdown = [
            'transaction_points' => [
                'total' => 0,
                'active' => 0,
                'expired' => 0,
                'details' => []
            ],
            'policy_bonuses' => [
                'total' => 0,
                'active' => 0,
                'expired' => 0,
                'details' => []
            ],
            'loyalty_bonuses' => [
                'total' => 0,
                'active' => 0,
                'expired' => 0,
                'details' => []
            ],
            'product_bonuses' => [
                'total' => 0,
                'active' => 0,
                'expired' => 0,
                'details' => []
            ],
            'extra_points' => [
                'total' => 0,
                'active' => 0,
                'expired' => 0,
                'details' => []
            ],
            'other_bonuses' => [
                'total' => 0,
                'active' => 0,
                'expired' => 0,
                'details' => []
            ]
        ];

        // Process each customer point
        foreach ($customerPoints as $point) {
            $isActive = !$point->expire_at || $point->expire_at > now();
            $category = $this->categorizePoint($point->name);
            
            $pointDetail = [
                'id' => $point->id,
                'name' => $point->name,
                'points' => $point->point,
                'expire_at' => $point->expire_at,
                'is_active' => $isActive,
                'created_at' => $point->created_at,
                'days_until_expiry' => $point->expire_at ? now()->diffInDays($point->expire_at, false) : null
            ];

            // Add to appropriate category
            $pointsBreakdown[$category]['details'][] = $pointDetail;
            $pointsBreakdown[$category]['total'] += $point->point;
            
            if ($isActive) {
                $pointsBreakdown[$category]['active'] += $point->point;
            } else {
                $pointsBreakdown[$category]['expired'] += $point->point;
            }
        }

        // Calculate tier progression
        $currentTier = $customer->rewardTier;
        $nextTier = RewardTier::where("level_point", '>', $customer->point)->orderBy('level_point', 'asc')->first();
        
        $pointsToNext = 0;
        $totalRequired = 0;
        $progressPercentage = 0;

        if ($nextTier) {
            $pointsToNext = $nextTier->level_point - $customer->point;
            $totalRequired = $nextTier->level_point;
            
            // Calculate progress percentage
            if ($currentTier) {
                $currentTierPoints = $currentTier->level_point;
                $tierRange = $totalRequired - $currentTierPoints;
                $customerProgress = $customer->point - $currentTierPoints;
                $progressPercentage = $tierRange > 0 ? round(($customerProgress / $tierRange) * 100, 2) : 0;
            } else {
                $progressPercentage = round(($customer->point / $totalRequired) * 100, 2);
            }
        } else {
            // Customer is at the highest tier
            $progressPercentage = 100;
        }

        // Calculate summary totals
        $totalActivePoints = array_sum(array_column($pointsBreakdown, 'active'));
        $totalExpiredPoints = array_sum(array_column($pointsBreakdown, 'expired'));
        $totalAllTimePoints = array_sum(array_column($pointsBreakdown, 'total'));

        // Get points expiring soon (within 30 days)
        $pointsExpiringSoon = CustomerPoint::where('customer_id', $customerId)
            ->where('expire_at', '>', now())
            ->where('expire_at', '<=', now()->addDays(30))
            ->sum('point');

        return response()->json([
            // Current tier information
            'current_tier' => [
                'tier_name' => $currentTier->name ?? 'No Tier',
                'tier_label' => $currentTier->label ?? '',
                'tier_id' => $currentTier->id ?? null,
                'image' => $currentTier && $currentTier->image ? \AlphaDirect\Helper::getCloudFrontURL($currentTier->image) : null,
                'level_point' => $currentTier->level_point ?? 0,
            ],
            
            // Next tier information
            'next_tier' => [
                'tier_name' => $nextTier->name ?? 'Max Tier Reached',
                'tier_id' => $nextTier->id ?? null,
                'image' => $nextTier && $nextTier->image ? \AlphaDirect\Helper::getCloudFrontURL($nextTier->image) : null,
                'level_point' => $nextTier->level_point ?? 0,
                'points_needed' => max(0, $pointsToNext),
                'total_required' => $totalRequired
            ],
            
            // Progress information
            'progress' => [
                'current_points' => $customer->point ?? 0,
                'progress_percentage' => $progressPercentage,
                'points_to_next' => max(0, $pointsToNext),
                'is_max_tier' => !$nextTier
            ],
            
            // Points summary
            'points_summary' => [
                'total_active_points' => $totalActivePoints,
                'total_expired_points' => $totalExpiredPoints,
                'total_used_points' => $customer->use_point ?? 0,
                'total_all_time_points' => $totalAllTimePoints,
                'points_expiring_soon' => $pointsExpiringSoon,
                'net_available_points' => $customer->point ?? 0
            ],
            
            // Detailed points breakdown by category
            'points_breakdown' => $pointsBreakdown,
            
            // Transaction statistics
            'transaction_stats' => $this->getTransactionStats($customerId),
            
            // Recent point activities (last 10)
            'recent_activities' => CustomerPoint::where('customer_id', $customerId)
                ->orderBy('created_at', 'desc')
                //->limit(10)
                ->get()
                ->map(function($point) {
                    return [
                        'id' => $point->id,
                        'name' => $point->name,
                        'points' => $point->point,
                        'created_at' => $point->created_at,
                        'expire_at' => $point->expire_at,
                        'is_active' => !$point->expire_at || $point->expire_at > now()
                    ];
                })
        ]);
    }

    /**
     * Categorize points based on their name/type
     */
    private function categorizePoint($pointName)
    {
        if (str_contains($pointName, 'Transaction on') || str_contains($pointName, 'Transaction History')) {
            return 'transaction_points';
        }
        
        if (str_contains($pointName, 'Policy Bonus') || str_contains($pointName, 'Single Policy') || str_contains($pointName, 'Bundled Policy') || str_contains($pointName, 'Multiple Policy')) {
            return 'policy_bonuses';
        }
        
        if (str_contains($pointName, 'Loyalty Bonus') || str_contains($pointName, 'Loyalty Points')) {
            return 'loyalty_bonuses';
        }
        
        if (str_contains($pointName, 'DomG-ComG Product') || str_contains($pointName, 'Product Bonus')) {
            return 'product_bonuses';
        }
        
        if (str_contains($pointName, 'Extra Points') || str_contains($pointName, 'Tier Bonus')) {
            return 'extra_points';
        }
        
        return 'other_bonuses'; // Welcome Bonus, Referral Bonus, First Policy Bonus, etc.
    }

    /**
     * Get transaction statistics for the customer
     */
    private function getTransactionStats($customerId)
    {
        $customer = Customer::find($customerId);
        $policies = $customer->policy()->where('status', 1)->pluck('policyNumber');
        
        if ($policies->isEmpty()) {
            return [
                'total_transactions' => 0,
                'successful_transactions' => 0,
                'failed_transactions' => 0,
                'success_rate' => 0,
                'total_amount_paid' => 0
            ];
        }

        // Get transactions from both live and archive tables
        $liveTransactions = \AlphaDirect\PaymentTransaction::whereIn('policyNumber', $policies)->get();
        $archivedTransactions = \AlphaDirect\Models\PaymentTransactionArchive::whereIn('policyNumber', $policies)->get();
        
        $allTransactions = $liveTransactions->merge($archivedTransactions);
        
        $totalTransactions = $allTransactions->count();
        $successfulTransactions = $allTransactions->filter(function($transaction) {
            return in_array($transaction->status, [1, 'success', 'Success', 'SUCCESS']);
        })->count();
        
        $failedTransactions = $totalTransactions - $successfulTransactions;
        $successRate = $totalTransactions > 0 ? round(($successfulTransactions / $totalTransactions) * 100, 2) : 0;
        $totalAmountPaid = $allTransactions->filter(function($transaction) {
            return in_array($transaction->status, [1, 'success', 'Success', 'SUCCESS']);
        })->sum('amount');

        return [
            'total_transactions' => $totalTransactions,
            'successful_transactions' => $successfulTransactions,
            'failed_transactions' => $failedTransactions,
            'success_rate' => $successRate,
            'total_amount_paid' => $totalAmountPaid
        ];
    }

    /**
     * Get simplified points summary for quick display
     * 
     * @param int $customerId Customer ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function pointsSummary($customerId)
    {
        $customer = Customer::with('rewardTier')->findOrFail($customerId);
        $customerPoints = CustomerPoint::where('customer_id', $customerId)->get();
        
        // Calculate category totals
        $summary = [
            'transaction_points' => 0,
            'policy_bonuses' => 0,
            'loyalty_bonuses' => 0,
            'product_bonuses' => 0,
            'other_bonuses' => 0,
            'expired_points' => 0,
            'expiring_soon' => 0
        ];

        foreach ($customerPoints as $point) {
            $isActive = !$point->expire_at || $point->expire_at > now();
            $category = $this->categorizePoint($point->name);
            
            if ($isActive) {
                if (isset($summary[$category])) {
                    $summary[$category] += $point->point;
                }
            } else {
                $summary['expired_points'] += $point->point;
            }

            // Check if expiring soon (within 30 days)
            if ($point->expire_at && $point->expire_at > now() && $point->expire_at <= now()->addDays(30)) {
                $summary['expiring_soon'] += $point->point;
            }
        }

        return response()->json([
            'customer_id' => $customerId,
            'customer_name' => $customer->firstName . ' ' . $customer->lastName,
            'current_tier' => $customer->rewardTier->name ?? 'No Tier',
            'tier_image' => $customer->rewardTier && $customer->rewardTier->image ? \AlphaDirect\Helper::getCloudFrontURL($customer->rewardTier->image) : null,
            'total_active_points' => $customer->point ?? 0,
            'total_used_points' => $customer->use_point ?? 0,
            'points_by_category' => $summary,
            'tier_progress' => [
                'current_points' => $customer->point ?? 0,
                'next_tier_points' => $this->getNextTierPoints($customer->point ?? 0),
                'progress_percentage' => $this->getTierProgressPercentage($customer)
            ]
        ]);
    }

    /**
     * Get points needed for next tier
     */
    private function getNextTierPoints($currentPoints)
    {
        $nextTier = RewardTier::where("level_point", '>', $currentPoints)->orderBy('level_point', 'asc')->first();
        return $nextTier ? $nextTier->level_point : null;
    }

    /**
     * Get tier progress percentage
     */
    private function getTierProgressPercentage($customer)
    {
        $currentTier = $customer->rewardTier;
        $nextTier = RewardTier::where("level_point", '>', $customer->point)->orderBy('level_point', 'asc')->first();
        
        if (!$nextTier) {
            return 100; // Max tier reached
        }

        if ($currentTier) {
            $currentTierPoints = $currentTier->level_point;
            $tierRange = $nextTier->level_point - $currentTierPoints;
            $customerProgress = $customer->point - $currentTierPoints;
            return $tierRange > 0 ? round(($customerProgress / $tierRange) * 100, 2) : 0;
        } else {
            return round(($customer->point / $nextTier->level_point) * 100, 2);
        }
    }

    /**
     * Claim a customer reward
     * 
     * @param int $customerId Customer ID
     * @param int $benefitId Benefit ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function claims2($customerId, $benefitId)
    {
        try {
            // Find the customer and benefit
            $customer = Customer::findOrFail($customerId);
            $benefit = Benefit::findOrFail($benefitId);
            
            // Get benefit points
            $benefitPoints = $benefit->point ?? 0;
            
            // Check if customer has enough points
            if ($customer->point < $benefitPoints) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient points to claim this reward.',
                    'data' => [
                        'benefit_name' => $benefit->name ?? '',
                        'required_points' => $benefitPoints,
                        'available_points' => $customer->point,
                    ]
                ], 400);
            }
            
            // Start database transaction
            DB::beginTransaction();
            
            try {
                // Update customer points and use_point
                $customer->update([
                    'point' => $customer->point - $benefitPoints,
                    'use_point' => ($customer->use_point ?? 0) + $benefitPoints,
                ]);
                
                // Create a new customer benefit record with the claimed benefit
                $newCustomerBenefit = CustomerBenefit::create([
                    'customer_id' => $customer->id,
                    'benefit_id' => $benefit->id,
                    'status' => 'active',
                    'expiry_date' => now()->addMonth(), // Set expiry date to 1 month from now
                    'claim_date' => null, // Reset claim date for the new record
                ]);
                
                // Commit transaction
                DB::commit();
                
                // Return success response with updated data
                return response()->json([
                    'success' => true,
                    'message' => 'Reward claimed successfully.',
                    'data' => [
                        'new_benefit_id' => $newCustomerBenefit->id,
                        'benefit_name' => $benefit->name ?? '',
                        'benefit_description' => $benefit->description ?? '',
                        'benefit_image' => $benefit->image ? \AlphaDirect\Helper::getCloudFrontURL($benefit->image) : null,
                        'benefit_points' => $benefitPoints,
                        'customer_name' => $customer->firstName . ' ' . $customer->lastName,
                        'customer_email' => $customer->email,
                        'status' => $newCustomerBenefit->status,
                        'expiry_date' => $newCustomerBenefit->expiry_date,
                        'updated_points' => $customer->point,
                        'total_used_points' => $customer->use_point,
                    ]
                ], 200);
                
            } catch (\Exception $e) {
                // Rollback transaction on error
                DB::rollback();
                throw $e;
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while claiming the reward.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add benefit to cart (create new customer benefit entry)
     * 
     * @param int $customerId Customer ID
     * @param int $benefitId Benefit ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function claims($customerId, $benefitId)
    {
        try {
            // Find the customer and benefit
            $customer = Customer::findOrFail($customerId);
            $benefit = Benefit::findOrFail($benefitId);
            
            // Get benefit points
            $benefitPoints = $benefit->point ?? 0;
            
            // Check if customer has enough points
            if ($customer->point < $benefitPoints) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient points to add this benefit to cart.',
                    'data' => [
                        'benefit_name' => $benefit->name ?? '',
                        'required_points' => $benefitPoints,
                        'available_points' => $customer->point,
                    ]
                ], 400);
            }
            
            // Check if benefit is active
            if ($benefit->status != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'This benefit is not available.',
                    'data' => [
                        'benefit_name' => $benefit->name ?? '',
                        'benefit_status' => $benefit->status,
                    ]
                ], 400);
            }
            
            // Start database transaction
            DB::beginTransaction();
            
            try {
                // Update customer points and use_point
                $customer->update([
                    'point' => $customer->point - $benefitPoints,
                    'use_point' => ($customer->use_point ?? 0) + $benefitPoints,
                ]);
                
                // Create a new customer benefit record
                $newCustomerBenefit = CustomerBenefit::create([
                    'customer_id' => $customer->id,
                    'benefit_id' => $benefit->id,
                    'status' => 'active',
                    'expiry_date' => now()->addMonth(), // Set expiry date to 1 month from now
                    'claim_date' => null,
                ]);
                
                // Commit transaction
                DB::commit();
                
                // Return success response with updated data
                return response()->json([
                    'success' => true,
                    'message' => 'Benefit added to cart successfully.',
                    'data' => [
                        'customer_benefit_id' => $newCustomerBenefit->id,
                        'benefit_name' => $benefit->name ?? '',
                        'benefit_description' => $benefit->description ?? '',
                        'benefit_image' => $benefit->image ? \AlphaDirect\Helper::getCloudFrontURL($benefit->image) : null,
                        'benefit_points' => $benefitPoints,
                        'customer_name' => $customer->firstName . ' ' . $customer->lastName,
                        'customer_email' => $customer->email,
                        'status' => $newCustomerBenefit->status,
                        'expiry_date' => $newCustomerBenefit->expiry_date,
                        'updated_points' => $customer->point,
                        'total_used_points' => $customer->use_point,
                    ]
                ], 200);
                
            } catch (\Exception $e) {
                // Rollback transaction on error
                DB::rollback();
                throw $e;
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while adding benefit to cart.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 