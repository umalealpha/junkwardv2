<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Query Optimizer Service
 * 
 * Provides optimized query methods using policy_id instead of policyNumber
 * and database views for common queries
 */
class QueryOptimizerService
{
    /**
     * Get policy details using optimized view
     *
     * @param int|string $identifier Policy ID or Policy Number
     * @return object|null
     */
    public static function getPolicyDetails($identifier)
    {
        $cacheKey = is_numeric($identifier) 
            ? "policy_details_id_{$identifier}"
            : "policy_details_number_{$identifier}";
            
        return CacheService::remember($cacheKey, function() use ($identifier) {
            if (is_numeric($identifier)) {
                return DB::table('v_policy_details')
                    ->where('policy_id', $identifier)
                    ->first();
            } else {
                return DB::table('v_policy_details')
                    ->where('policyNumber', $identifier)
                    ->first();
            }
        }, CacheService::CACHE_TTL_MEDIUM, [CacheService::TAG_POLICIES]);
    }

    /**
     * Get policy with latest payment transaction
     *
     * @param int|string $identifier Policy ID or Policy Number
     * @return object|null
     */
    public static function getPolicyWithLatestPayment($identifier)
    {
        $cacheKey = is_numeric($identifier) 
            ? "policy_latest_payment_id_{$identifier}"
            : "policy_latest_payment_number_{$identifier}";
            
        return CacheService::remember($cacheKey, function() use ($identifier) {
            if (is_numeric($identifier)) {
                return DB::table('v_policy_latest_transaction')
                    ->where('policy_id', $identifier)
                    ->first();
            } else {
                return DB::table('v_policy_latest_transaction')
                    ->where('policyNumber', $identifier)
                    ->first();
            }
        }, CacheService::CACHE_TTL_SHORT, [CacheService::TAG_POLICIES]);
    }

    /**
     * Get policies with payments (optimized join)
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getPoliciesWithPayments(array $filters = [], int $perPage = 50)
    {
        $query = DB::table('v_policy_payments');
        
        if (isset($filters['status'])) {
            $query->where('policy_status', $filters['status']);
        }
        
        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }
        
        if (isset($filters['payment_method'])) {
            $query->where('paymentMethod', $filters['payment_method']);
        }
        
        return $query->paginate($perPage);
    }

    /**
     * Get policy ledger summary (optimized)
     *
     * @param int $policyId
     * @return object|null
     */
    public static function getPolicyLedgerSummary(int $policyId)
    {
        return CacheService::remember("policy_ledger_summary_{$policyId}", function() use ($policyId) {
            return DB::table('v_policy_ledger_summary')
                ->where('policy_id', $policyId)
                ->first();
        }, CacheService::CACHE_TTL_SHORT, [CacheService::TAG_LEDGERS]);
    }

    /**
     * Get policies with vehicles (optimized)
     *
     * @param array $filters
     * @return \Illuminate\Support\Collection
     */
    public static function getPoliciesWithVehicles(array $filters = [])
    {
        $query = DB::table('v_policy_vehicles');
        
        if (isset($filters['status'])) {
            $query->where('policy_status', $filters['status']);
        }
        
        if (isset($filters['vehicle_status'])) {
            $query->where('vehicle_status', $filters['vehicle_status']);
        }
        
        return $query->get();
    }

    /**
     * Get policy KYC status (optimized)
     *
     * @param int $policyId
     * @return object|null
     */
    public static function getPolicyKycStatus(int $policyId)
    {
        return CacheService::remember("policy_kyc_status_{$policyId}", function() use ($policyId) {
            return DB::table('v_policy_kyc_status')
                ->where('policy_id', $policyId)
                ->first();
        }, CacheService::CACHE_TTL_MEDIUM, [CacheService::TAG_POLICIES]);
    }

    /**
     * Optimize join query - converts policyNumber join to policy_id join
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $table Table name to join
     * @param string $policyNumberColumn Column name in joining table
     * @return \Illuminate\Database\Query\Builder
     */
    public static function optimizeJoin($query, string $table, string $policyNumberColumn = 'policyNumber')
    {
        // Check if table has policy_id column
        $hasPolicyId = DB::select("
            SELECT COUNT(*) as count 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = 'policy_id'
        ", [DB::getDatabaseName(), $table]);
        
        if ($hasPolicyId[0]->count > 0) {
            // Use policy_id join (faster)
            return $query->join($table, "{$table}.policy_id", '=', 'policies.id');
        } else {
            // Fallback to policyNumber join
            Log::warning("Table {$table} does not have policy_id column, using policyNumber join");
            return $query->join($table, "{$table}.{$policyNumberColumn}", '=', 'policies.policyNumber');
        }
    }

    /**
     * Batch update policy_id in a table
     *
     * @param string $table
     * @param string $policyNumberColumn
     * @param int $batchSize
     * @return array Statistics
     */
    public static function batchUpdatePolicyIds(string $table, string $policyNumberColumn = 'policyNumber', int $batchSize = 1000)
    {
        if (!DB::getSchemaBuilder()->hasColumn($table, 'policy_id')) {
            throw new \Exception("Table {$table} does not have policy_id column");
        }

        $stats = [
            'total' => 0,
            'updated' => 0,
            'failed' => 0
        ];

        // Get total count
        $stats['total'] = DB::table($table)
            ->whereNull('policy_id')
            ->whereNotNull($policyNumberColumn)
            ->count();

        // Process in batches
        DB::table($table)
            ->whereNull('policy_id')
            ->whereNotNull($policyNumberColumn)
            ->orderBy('id')
            ->chunk($batchSize, function ($records) use ($table, $policyNumberColumn, &$stats) {
                foreach ($records as $record) {
                    try {
                        $policy = DB::table('policies')
                            ->where('policyNumber', $record->$policyNumberColumn)
                            ->first();
                        
                        if ($policy) {
                            DB::table($table)
                                ->where('id', $record->id)
                                ->update(['policy_id' => $policy->id]);
                            $stats['updated']++;
                        } else {
                            $stats['failed']++;
                            Log::warning("Policy not found for {$policyNumberColumn}: {$record->$policyNumberColumn}");
                        }
                    } catch (\Exception $e) {
                        $stats['failed']++;
                        Log::error("Error updating {$table}.id={$record->id}: " . $e->getMessage());
                    }
                }
            });

        return $stats;
    }
}
