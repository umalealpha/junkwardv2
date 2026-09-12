<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    const CACHE_TTL_SHORT = 300;      // 5 minutes
    const CACHE_TTL_MEDIUM = 1800;     // 30 minutes
    const CACHE_TTL_LONG = 3600;       // 1 hour
    const CACHE_TTL_VERY_LONG = 86400; // 24 hours

    const TAG_POLICIES = 'policies';
    const TAG_CUSTOMERS = 'customers';
    const TAG_PRODUCTS = 'products';
    const TAG_VEHICLES = 'vehicles';
    const TAG_LEDGERS = 'ledgers';
    const TAG_QUOTES = 'quotes';
    const TAG_LOOKUPS = 'lookups';   // reference data: states, cities, plans, agencies, banks

    /**
     * Check if the current cache driver supports tags (Redis, Memcached do; file, database do not).
     */
    private static function supportsTags(): bool
    {
        return in_array(config('cache.default'), ['redis', 'memcached']);
    }

    /**
     * Get cached data or execute callback and cache result.
     * Falls back to tag-less caching when driver doesn't support tags.
     */
    public static function remember(string $key, callable $callback, int $ttl = self::CACHE_TTL_MEDIUM, array $tags = [])
    {
        try {
            if (!empty($tags) && self::supportsTags()) {
                return Cache::tags($tags)->remember($key, $ttl, $callback);
            }
            return Cache::remember($key, $ttl, $callback);
        } catch (\Exception $e) {
            Log::warning("Cache error for key: {$key}", ['error' => $e->getMessage()]);
            return $callback();
        }
    }

    public static function rememberPolicy(int $policyId, callable $callback)
    {
        return self::remember("policy_{$policyId}", $callback, self::CACHE_TTL_MEDIUM, [self::TAG_POLICIES]);
    }

    public static function rememberPolicyByNumber(string $policyNumber, callable $callback)
    {
        return self::remember("policy_number_{$policyNumber}", $callback, self::CACHE_TTL_MEDIUM, [self::TAG_POLICIES]);
    }

    public static function rememberCustomer(int $customerId, callable $callback)
    {
        return self::remember("customer_{$customerId}", $callback, self::CACHE_TTL_MEDIUM, [self::TAG_CUSTOMERS]);
    }

    public static function rememberProduct(int $productId, callable $callback)
    {
        return self::remember("product_{$productId}", $callback, self::CACHE_TTL_LONG, [self::TAG_PRODUCTS]);
    }

    public static function rememberVehicle(int $vehicleId, callable $callback)
    {
        return self::remember("vehicle_{$vehicleId}", $callback, self::CACHE_TTL_MEDIUM, [self::TAG_VEHICLES]);
    }

    public static function rememberQuery(string $queryKey, callable $callback, int $ttl = self::CACHE_TTL_SHORT)
    {
        return self::remember("query_{$queryKey}", $callback, $ttl);
    }

    /**
     * Invalidate policy cache.
     */
    public static function forgetPolicy(?int $policyId = null)
    {
        try {
            if ($policyId) {
                Cache::forget("policy_{$policyId}");
            }
            if (self::supportsTags()) {
                Cache::tags([self::TAG_POLICIES])->flush();
            }
        } catch (\Exception $e) {
            Log::warning("Cache invalidation error for policy: {$policyId}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Invalidate customer cache.
     */
    public static function forgetCustomer(?int $customerId = null)
    {
        try {
            if ($customerId) {
                Cache::forget("customer_{$customerId}");
            }
            if (self::supportsTags()) {
                Cache::tags([self::TAG_CUSTOMERS])->flush();
            }
        } catch (\Exception $e) {
            Log::warning("Cache invalidation error for customer: {$customerId}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Invalidate all lookup / reference data cache (states, cities, plans, agencies, banks).
     */
    public static function forgetLookups(?string $specificKey = null)
    {
        try {
            if ($specificKey) {
                Cache::forget($specificKey);
            }
            if (self::supportsTags()) {
                Cache::tags([self::TAG_LOOKUPS])->flush();
            }
        } catch (\Exception $e) {
            Log::warning("Cache invalidation error for lookups", ['error' => $e->getMessage()]);
        }
    }

    public static function flushAll()
    {
        try {
            Cache::flush();
        } catch (\Exception $e) {
            Log::warning("Cache flush error", ['error' => $e->getMessage()]);
        }
    }

    public static function generateKey(string $prefix, array $params = []): string
    {
        $key = $prefix;
        if (!empty($params)) {
            $key .= '_' . md5(serialize($params));
        }
        return $key;
    }
}
