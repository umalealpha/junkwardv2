<?php

namespace AlphaDirect\Repositories;

use AlphaDirect\Policy;
use AlphaDirect\Services\CacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PolicyRepository — centralizes all Policy DB queries.
 * Controllers should use this instead of querying Policy directly,
 * ensuring consistent caching and eager loading.
 */
class PolicyRepository
{
    /**
     * Returns counts by status from cache (or DB on miss).
     * Used by dashboard and V1 DashboardController.
     */
    public function getStatusCounts(): array
    {
        return (array) CacheService::remember(
            'dashboard_policy_counts',
            fn() => DB::table('policies')->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as expired
            ')->first(),
            CacheService::CACHE_TTL_SHORT,
            [CacheService::TAG_POLICIES]
        );
    }

    /**
     * Finds a policy with its full set of relations, cached per ID.
     */
    public function findWithRelations(int $id): ?Policy
    {
        return CacheService::rememberPolicy($id, fn() =>
            Policy::with(['customer', 'product', 'vehicle', 'claim', 'kyc', 'transactions'])
                ->find($id)
        );
    }

    /**
     * Paginated, filtered policy list with lean eager loading.
     * Suitable for policy table and API index endpoints.
     */
    public function getPaginatedFiltered(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Policy::with([
                'customer:id,firstName,lastName,cellphone',
                'product:id,name',
                'vehicle:id,policy_id,vehiclePlate,status',
            ])
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['product_id'] ?? null, fn($q, $v) => $q->where('product_id', $v))
            ->when($filters['agent_id'] ?? null, fn($q, $v) => $q->where('agent_id', $v))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where('policyNumber', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn($c) =>
                      $c->where('firstName', 'like', "%{$search}%")
                        ->orWhere('lastName', 'like', "%{$search}%")
                        ->orWhere('cellphone', 'like', "%{$search}%")
                  );
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Recent policies for dashboard widget.
     */
    public function getRecent(int $limit = 5): Collection
    {
        return CacheService::remember(
            'dashboard_recent_policies',
            fn() => Policy::with(['customer:id,firstName,lastName', 'product:id,name'])
                ->select('id', 'policyNumber', 'status', 'premium', 'customer_id', 'product_id', 'created_at')
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get(),
            CacheService::CACHE_TTL_SHORT,
            [CacheService::TAG_POLICIES]
        );
    }
}
