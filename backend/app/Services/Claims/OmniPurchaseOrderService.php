<?php

namespace AlphaDirect\Services\Claims;

use AlphaDirect\Services\IntegrationSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OmniPurchaseOrderService — the PO(s) Omni raised for a Graphite claim.
 *
 * PO-in-Graphite Phase 2 (CFO spec 12 Aug 2026). Omni OWNS purchase orders —
 * they post to the ledger there, carry dual-control approval and the audit
 * trail. Graphite RENDERS them live and NEVER persists PO status or amounts:
 * the only permitted hold is the short cache below (Prathap's own spec).
 * Do not "helpfully" write any of this response into a Graphite table — that
 * is the second-copy-of-the-accounting-truth the CFO explicitly banned.
 *
 * Contract (Omni endpoint LIVE on prod since 24 Aug 2026):
 *   GET {base}/api/v1/purchase-orders/by-claim/?claim_ref=<ref>
 *   Authorization: ApiKey <po-claim-read scoped key>   (the standard Omni
 *   header — same shape as SwiftlyService::authHeaders(), NOT a custom one)
 *   200 → { claim_ref, purchase_orders: [ 8 allow-listed fields ] }
 *   No POs → 200 with an empty list, never 404: "no PO yet" and "unknown
 *   reference" deliberately render as the same quiet state.
 *   400 blank ref · 401 bad key or non-GET · 403 key lacks the scope.
 *
 * The claim reference is an OPAQUE STRING to both sides. Omni trims,
 * upper-cases and matches case-insensitively; we send it as stored and never
 * parse it (the number tail is NOT the claim id, and tracker-migrated claims
 * carry legacy formats).
 *
 * Never throws into the claim page — Omni being slow or down must degrade to
 * "temporarily unavailable", not break the claim.
 */
class OmniPurchaseOrderService
{
    public const FLAG = 'omni_po';

    /** The eight allow-listed fields, re-filtered on OUR side too — a field
     *  Omni adds later must not flow to the browser unreviewed (no PII, ever). */
    private const FIELDS = [
        'uuid', 'po_number', 'status', 'total', 'currency', 'date', 'supplier_name', 'deep_link',
    ];

    public static function enabled(): bool
    {
        return IntegrationSettings::isEnabled(self::FLAG, (bool) config('services.omni_po.enabled', false));
    }

    /**
     * @return array{enabled:bool, available:bool, claim_ref:?string, purchase_orders:array}
     */
    public function forClaim(int $claimId): array
    {
        if (!self::enabled()) {
            return ['enabled' => false, 'available' => false, 'claim_ref' => null, 'purchase_orders' => []];
        }

        // `claims` is authoritative; fall back to `new_claims` (legacy), the
        // same order ClaimFormEmailListener uses.
        $ref = DB::table('claims')->where('id', $claimId)->value('claim_number')
            ?: DB::table('new_claims')->where('id', $claimId)->value('claim_number');
        if (!$ref) {
            // A claim with no reference has nothing to look up — an honest
            // empty list, not an error.
            return ['enabled' => true, 'available' => true, 'claim_ref' => null, 'purchase_orders' => []];
        }
        $ref = trim((string) $ref);

        $key = (string) config('services.omni_po.api_key');
        if ($key === '') {
            // Flag ON but the key hasn't landed (it arrives via the Vault →
            // SSM, never email/repo). Degrade, and say why in the log only.
            Log::warning('[OmniPO] flag on but OMNI_PO_API_KEY is not configured');

            return ['enabled' => true, 'available' => false, 'claim_ref' => $ref, 'purchase_orders' => []];
        }

        // Cache SUCCESSFUL lookups only — a cached failure would pin the
        // "unavailable" state for a minute after Omni recovers.
        $cacheKey = 'omni_po:by_claim:' . strtoupper($ref);
        $cached   = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $timeout = (int) config('services.omni_po.timeout', 2);
            $resp = Http::timeout($timeout)
                ->connectTimeout($timeout)
                ->withHeaders([
                    'Authorization' => 'ApiKey ' . $key,
                    'Accept'        => 'application/json',
                ])
                ->get(
                    rtrim((string) config('services.omni_po.base_url'), '/') . '/api/v1/purchase-orders/by-claim/',
                    ['claim_ref' => $ref],
                );

            if ($resp->status() === 200) {
                $orders = collect($resp->json('purchase_orders') ?? [])
                    ->map(function ($po) {
                        $row = [];
                        foreach (self::FIELDS as $f) {
                            $row[$f] = isset($po[$f]) ? (string) $po[$f] : null;
                        }

                        return $row;
                    })
                    ->values()
                    ->all();

                $out = ['enabled' => true, 'available' => true, 'claim_ref' => $ref, 'purchase_orders' => $orders];
                Cache::put($cacheKey, $out, (int) config('services.omni_po.cache_seconds', 60));

                return $out;
            }

            // 400/401/403 are OUR misconfiguration, never the user's — log the
            // status (never the key) and degrade honestly.
            Log::warning('[OmniPO] lookup failed', ['claim_id' => $claimId, 'http' => $resp->status()]);
        } catch (\Throwable $e) {
            Log::warning('[OmniPO] lookup error', ['claim_id' => $claimId, 'error' => $e->getMessage()]);
        }

        return ['enabled' => true, 'available' => false, 'claim_ref' => $ref, 'purchase_orders' => []];
    }
}
