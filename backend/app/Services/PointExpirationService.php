<?php

namespace AlphaDirect\Services;

use Carbon\Carbon;
use AlphaDirect\CustomerPoint;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Models\PaymentTransactionArchive;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Config;

class PointExpirationService
{
    /**
     * Point expiration rules based on point type
     */
    private const EXPIRATION_RULES = [
        'Transaction History Points' => [
            'description' => 'Points from transaction history expire based on configured duration from first transaction'
        ],
        '12-Month Loyalty Bonus' => [
            'description' => 'Loyalty bonuses expire based on configured duration from first transaction'
        ],
        '24-Month Loyalty Bonus' => [
            'description' => 'Loyalty bonuses expire based on configured duration from first transaction'
        ],
        'Single Policy Bonus' => [
            'description' => 'Single policy bonuses expire based on configured duration from first transaction'
        ],
        'Bundled Policy Bonus' => [
            'description' => 'Bundled policy bonuses expire based on configured duration from first transaction'
        ],
        'Multiple Policy Bonus' => [
            'description' => 'Multiple policy bonuses expire based on configured duration from first transaction'
        ],
        'DomG-ComG Product Bonus (12+ months)' => [
            'description' => 'DomG-ComG product bonuses expire based on configured duration from first transaction'
        ],
        'DomG-ComG Product Bonus (24+ months)' => [
            'description' => 'DomG-ComG product bonuses expire based on configured duration from first transaction'
        ],
        'Welcome Bonus' => [
            'description' => 'Welcome bonuses expire based on configured duration from first transaction'
        ],
        'Referral Bonus' => [
            'description' => 'Referral bonuses expire based on configured duration from first transaction'
        ],
        'First Policy Bonus' => [
            'description' => 'First policy bonuses expire based on configured duration from first transaction'
        ],
        'Loyalty Points' => [
            'duration' => null, // Never expire
            'description' => 'Loyalty points never expire'
        ]
    ];

    /**
     * Get customer point expiration duration in days from config
     *
     * @return int|null
     */
    public function getPointExpirationDays(): ?int
    {
        try {
            $config = Config::where('key', 'customer_point_expire_in')->first();
            
            if (!$config) {
                Log::warning("Config key 'customer_point_expire_in' not found, using default 1095 days (3 years)");
                return 1095; // Default fallback (3 years = 1095 days)
            }

            $days = (int) $config->value;
            
            if ($days <= 0) {
                Log::warning("Invalid expiration days configured: {$days}, using default 1095 days");
                return 1095; // Default fallback
            }

            return $days;
        } catch (\Exception $e) {
            Log::error("Error getting point expiration days from config: " . $e->getMessage());
            return 1095; // Default fallback
        }
    }

    /**
     * Calculate expiration date for a point type based on customer's first transaction
     *
     * @param string $pointName
     * @param int $customerId
     * @param Carbon|null $startDate
     * @return Carbon|null
     */
    public function calculateExpirationDate(string $pointName, int $customerId, ?Carbon $startDate = null): ?Carbon
    {
        if (!isset(self::EXPIRATION_RULES[$pointName])) {
            Log::warning("No expiration rule found for point type: {$pointName}");
            return null;
        }

        $rule = self::EXPIRATION_RULES[$pointName];
        
        if (isset($rule['duration']) && $rule['duration'] === null) {
            return null; // Never expire (Loyalty Points)
        }

        // If startDate is provided, use it; otherwise find first transaction
        if ($startDate) {
            $baseDate = $startDate;
        } else {
            $baseDate = $this->getCustomerFirstTransactionDate($customerId);
        }

        if (!$baseDate) {
            // If no transaction found, use current date
            $baseDate = now();
        }

        $expirationDays = $this->getPointExpirationDays();
        return $baseDate->copy()->addDays($expirationDays);
    }

    /**
     * Get customer's first transaction date
     *
     * @param int $customerId
     * @return Carbon|null
     */
    public function getCustomerFirstTransactionDate(int $customerId): ?Carbon
    {
        // Get customer's policies
        $policies = \AlphaDirect\Policy::where('customer_id', $customerId)->pluck('policyNumber');
        
        if ($policies->isEmpty()) {
            return null;
        }

        // Get first transaction from live transactions
        $firstLiveTransaction = PaymentTransaction::whereIn('policyNumber', $policies)
            ->orderBy('paymentDate', 'asc')
            ->first();

        // Get first transaction from archived transactions
        $firstArchivedTransaction = PaymentTransactionArchive::whereIn('policyNumber', $policies)
            ->orderBy('paymentDate', 'asc')
            ->first();

        // Compare and return the earliest
        if ($firstLiveTransaction && $firstArchivedTransaction) {
            $liveDate = Carbon::parse($firstLiveTransaction->paymentDate);
            $archivedDate = Carbon::parse($firstArchivedTransaction->paymentDate);
            
            return $liveDate->lt($archivedDate) ? $liveDate : $archivedDate;
        } elseif ($firstLiveTransaction) {
            return Carbon::parse($firstLiveTransaction->paymentDate);
        } elseif ($firstArchivedTransaction) {
            return Carbon::parse($firstArchivedTransaction->paymentDate);
        }

        return null;
    }

    /**
     * Calculate transaction-based points with new logic - store each transaction separately
     *
     * @param string $policyNumber
     * @return array
     */
    public function calculateTransactionPoints(string $policyNumber): array
    {
        // Merge transactions from both tables
        $liveTransactions = PaymentTransaction::where('policyNumber', $policyNumber)
            ->orderBy('paymentDate', 'asc')
            ->get();
        $archivedTransactions = PaymentTransactionArchive::where('policyNumber', $policyNumber)
            ->orderBy('paymentDate', 'asc')
            ->get();

        // Merge and sort all transactions by paymentDate
        $transactions = $liveTransactions->merge($archivedTransactions)->sortBy('paymentDate')->values();

        if ($transactions->isEmpty()) {
            return [
                'points' => 0,
                'firstTransactionDate' => null,
                'successfulTransactions' => 0,
                'totalPoints' => 0,
                'transactionPoints' => []
            ];
        }

        // Get first transaction date for expiration calculation
        $firstTransactionDate = Carbon::parse($transactions->first()->paymentDate);

        $successfulTransactions = 0;
        $totalPoints = 0;
        $transactionPoints = [];

        // Process each transaction individually
        foreach ($transactions as $transaction) {
            // Check if transaction is successful
            if (in_array($transaction->status, [1, 'success', 'Success', 'SUCCESS'])) {
                $successfulTransactions++;
                $points = 50; // Each successful transaction is worth 50 points
                $totalPoints += $points;
                
                // Store transaction point details
                $transactionPoints[] = [
                    'transaction_id' => $transaction->id ?? null,
                    'payment_date' => $transaction->paymentDate,
                    'amount' => $transaction->amount ?? 0,
                    'status' => $transaction->status,
                    'points' => $points,
                    'description' => "Transaction on " . Carbon::parse($transaction->paymentDate)->format('Y-m-d'),
                    'created_at' => $transaction->created_at ?? null
                ];
            }
        }

        return [
            'points' => $totalPoints,
            'firstTransactionDate' => $firstTransactionDate,
            'successfulTransactions' => $successfulTransactions,
            'totalPoints' => $totalPoints,
            'transactionPoints' => $transactionPoints
        ];
    }

    /**
     * Store individual transaction points for a customer
     *
     * @param int $customerId
     * @param string $policyNumber
     * @return array
     */
    public function storeTransactionPoints(int $customerId, string $policyNumber): array
    {
        // Calculate transaction points
        $transactionData = $this->calculateTransactionPoints($policyNumber);
       // dd($transactionData);
        
        if (empty($transactionData['transactionPoints'])) {
            return [
                'success' => false,
                'message' => 'No successful transactions found',
                'points_stored' => 0
            ];
        }

        $pointsStored = 0;

        // Store each transaction point separately
        foreach ($transactionData['transactionPoints'] as $transactionPoint) {
            // Calculate expiration based on transaction's created_at date
            if ($transactionPoint['created_at']) {
                $expireAt = Carbon::parse($transactionPoint['created_at'])->addDays($this->getPointExpirationDays());
            } else {
                // Fallback to payment date if created_at not available
                $expireAt = Carbon::parse($transactionPoint['payment_date'])->addDays($this->getPointExpirationDays());
            }
            
            // Create individual point entry for each transaction
            CustomerPoint::create([
                'customer_id' => $customerId,
                'name' => $transactionPoint['description'],
                'point' => $transactionPoint['points'],
                'expire_at' => $expireAt,
            ]);
            
            $pointsStored++;
        }

        return [
            'success' => true,
            'message' => "Stored {$pointsStored} transaction points",
            'points_stored' => $pointsStored,
            'total_points' => $transactionData['totalPoints'],
            'first_transaction_date' => $transactionData['firstTransactionDate']
        ];
    }



    /**
     * Store all transaction points for a customer across all their policies
     *
     * @param int $customerId
     * @return array
     */
    public function storeAllCustomerTransactionPoints(int $customerId): array
    {
        // Get all policies for the customer
        $policies = \AlphaDirect\Policy::where('customer_id', $customerId)
            ->where('status', 1)
            ->pluck('policyNumber');

        if ($policies->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No active policies found for customer',
                'total_points_stored' => 0,
                'policies_processed' => 0
            ];
        }

        $totalPointsStored = 0;
        $policiesProcessed = 0;
        $results = [];

        // Process each policy
        foreach ($policies as $policyNumber) {
            $result = $this->storeTransactionPoints($customerId, $policyNumber);
            $results[$policyNumber] = $result;
            
            if ($result['success']) {
                $totalPointsStored += $result['points_stored'];
                $policiesProcessed++;
            }
        }

        return [
            'success' => true,
            'message' => "Processed {$policiesProcessed} policies, stored {$totalPointsStored} transaction points",
            'total_points_stored' => $totalPointsStored,
            'policies_processed' => $policiesProcessed,
            'policy_results' => $results
        ];
    }

    /**
     * Calculate extra points based on total points after 12 successful transactions
     *
     * @param int $totalPoints
     * @param int $successfulTransactions
     * @return int
     */
    public function calculateExtraPoints(int $totalPoints, int $successfulTransactions): int
    {
        if ($successfulTransactions < 12) {
            return 0;
        }

        $extraPoints = 0;
        
        if ( 2000 > $totalPoints && $totalPoints >= 1000) {
            $extraPoints += 5*($successfulTransactions-12);
        }
        if (3000 > $totalPoints && $totalPoints >= 2000) {
            $extraPoints += 10*($successfulTransactions-12);
        }
        if (4000 > $totalPoints && $totalPoints >= 3000) {
            $extraPoints += 15*($successfulTransactions-12);
        }
        if ($totalPoints >= 4000 && $successfulTransactions >= 24) {
            $extraPoints += 20*($successfulTransactions-24);
        }

        return $extraPoints;
    }

    /**
     * Get expiration rule for a point type
     *
     * @param string $pointName
     * @return array|null
     */
    public function getExpirationRule(string $pointName): ?array
    {
        if (!isset(self::EXPIRATION_RULES[$pointName])) {
            return null;
        }

        $rule = self::EXPIRATION_RULES[$pointName];
        
        // Add dynamic duration information
        if (isset($rule['duration']) && $rule['duration'] === null) {
            $rule['duration'] = null; // Never expire
            $rule['duration_days'] = null;
        } else {
            $days = $this->getPointExpirationDays();
            $rule['duration'] = "{$days} days";
            $rule['duration_days'] = $days;
        }

        return $rule;
    }

    /**
     * Get all expiration rules
     *
     * @return array
     */
    public function getAllExpirationRules(): array
    {
        $rules = self::EXPIRATION_RULES;
        $days = $this->getPointExpirationDays();

        // Add dynamic duration information to all rules
        foreach ($rules as $pointName => &$rule) {
            if (isset($rule['duration']) && $rule['duration'] === null) {
                $rule['duration'] = null; // Never expire
                $rule['duration_days'] = null;
            } else {
                $rule['duration'] = "{$days} days";
                $rule['duration_days'] = $days;
            }
        }

        return $rules;
    }

    /**
     * Check if points are expiring soon (within specified days)
     *
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPointsExpiringSoon(int $days = 30)
    {
        $expiryDate = now()->addDays($days);
        
        return CustomerPoint::where('expire_at', '<=', $expiryDate)
            ->where('expire_at', '>', now())
            ->with('customer')
            ->get();
    }

    /**
     * Get expired points
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getExpiredPoints()
    {
        return CustomerPoint::where('expire_at', '<=', now())
            ->with('customer')
            ->get();
    }

    /**
     * Clean up expired points (optional - can be used for maintenance)
     *
     * @param bool $softDelete
     * @return int
     */
    public function cleanupExpiredPoints(bool $softDelete = true): int
    {
        $expiredPoints = $this->getExpiredPoints();
        $count = $expiredPoints->count();

        if ($count > 0) {
            if ($softDelete) {
                // Log expired points instead of deleting
                foreach ($expiredPoints as $point) {
                    Log::info("Point expired", [
                        'customer_id' => $point->customer_id,
                        'point_name' => $point->name,
                        'point_value' => $point->point,
                        'expired_at' => $point->expire_at
                    ]);
                }
            } else {
                // Actually delete expired points
                CustomerPoint::where('expire_at', '<=', now())->delete();
            }
        }

        return $count;
    }

    /**
     * Get points summary for a customer
     *
     * @param int $customerId
     * @return array
     */
    public function getCustomerPointsSummary(int $customerId): array
    {
        $customer = \AlphaDirect\Customer::find($customerId);
        
        if (!$customer) {
            return [];
        }

        $activePoints = $customer->customerPoints()->active()->get();
        $expiredPoints = $customer->customerPoints()->expired()->get();
        $expiringSoon = $customer->customerPoints()
            ->where('expire_at', '<=', now()->addDays(30))
            ->where('expire_at', '>', now())
            ->get();

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->firstName . ' ' . $customer->lastName,
                'email' => $customer->email
            ],
            'total_active_points' => $activePoints->sum('point'),
            'total_expired_points' => $expiredPoints->sum('point'),
            'points_expiring_soon' => $expiringSoon->sum('point'),
            'active_points_count' => $activePoints->count(),
            'expired_points_count' => $expiredPoints->count(),
            'expiring_soon_count' => $expiringSoon->count(),
            'active_points' => $activePoints,
            'expired_points' => $expiredPoints,
            'expiring_soon' => $expiringSoon
        ];
    }

    /**
     * Get points that will expire in the next X days
     *
     * @param int $days
     * @return array
     */
    public function getExpiringPointsReport(int $days = 30): array
    {
        $expiringPoints = $this->getPointsExpiringSoon($days);
        $expirationDays = $this->getPointExpirationDays();
        
        $report = [
            'total_points_expiring' => $expiringPoints->sum('point'),
            'total_customers_affected' => $expiringPoints->unique('customer_id')->count(),
            'expiration_date' => now()->addDays($days)->format('Y-m-d'),
            'configured_expiration_days' => $expirationDays,
            'points_by_type' => [],
            'points_by_customer' => []
        ];

        // Group by point type
        foreach ($expiringPoints as $point) {
            $type = $point->name;
            if (!isset($report['points_by_type'][$type])) {
                $report['points_by_type'][$type] = [
                    'count' => 0,
                    'total_points' => 0,
                    'customers' => []
                ];
            }
            $report['points_by_type'][$type]['count']++;
            $report['points_by_type'][$type]['total_points'] += $point->point;
            $report['points_by_type'][$type]['customers'][] = $point->customer_id;
        }

        // Group by customer
        foreach ($expiringPoints as $point) {
            $customerId = $point->customer_id;
            if (!isset($report['points_by_customer'][$customerId])) {
                $report['points_by_customer'][$customerId] = [
                    'customer_name' => $point->customer->firstName . ' ' . $point->customer->lastName,
                    'total_points' => 0,
                    'points' => []
                ];
            }
            $report['points_by_customer'][$customerId]['total_points'] += $point->point;
            $report['points_by_customer'][$customerId]['points'][] = [
                'name' => $point->name,
                'points' => $point->point,
                'expires_at' => $point->expire_at->format('Y-m-d')
            ];
        }

        return $report;
    }

    /**
     * Get detailed transaction information for a policy
     *
     * @param string $policyNumber
     * @return array
     */
    public function getTransactionDetails(string $policyNumber): array
    {
        // Merge transactions from both tables
        $liveTransactions = PaymentTransaction::where('policyNumber', $policyNumber)
            ->orderBy('paymentDate', 'asc')
            ->get();
        $archivedTransactions = PaymentTransactionArchive::where('policyNumber', $policyNumber)
            ->orderBy('paymentDate', 'asc')
            ->get();

        // Merge and sort all transactions by paymentDate
        $transactions = $liveTransactions->merge($archivedTransactions)->sortBy('paymentDate')->values();

        if ($transactions->isEmpty()) {
            return [
                'policy_number' => $policyNumber,
                'total_transactions' => 0,
                'successful_transactions' => 0,
                'failed_transactions' => 0,
                'total_points' => 0,
                'transactions' => []
            ];
        }

        $successfulTransactions = 0;
        $failedTransactions = 0;
        $totalPoints = 0;
        $transactionDetails = [];

        foreach ($transactions as $transaction) {
            $isSuccessful = in_array($transaction->status, [1, 'success', 'Success', 'SUCCESS']);
            
            if ($isSuccessful) {
                $successfulTransactions++;
                $points = 50;
                $totalPoints += $points;
            } else {
                $failedTransactions++;
                $points = 0;
            }

            $transactionDetails[] = [
                'id' => $transaction->id ?? null,
                'payment_date' => $transaction->paymentDate,
                'amount' => $transaction->amount ?? 0,
                'status' => $transaction->status,
                'is_successful' => $isSuccessful,
                'points' => $points,
                'source' => $transaction instanceof PaymentTransactionArchive ? 'archived' : 'live'
            ];
        }

        return [
            'policy_number' => $policyNumber,
            'total_transactions' => $transactions->count(),
            'successful_transactions' => $successfulTransactions,
            'failed_transactions' => $failedTransactions,
            'total_points' => $totalPoints,
            'first_transaction_date' => $transactions->first() ? Carbon::parse($transactions->first()->paymentDate) : null,
            'last_transaction_date' => $transactions->last() ? Carbon::parse($transactions->last()->paymentDate) : null,
            'transactions' => $transactionDetails
        ];
    }
} 