<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\Claims\OmniPurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * The Omni purchase orders raised for a claim — read-only, rendered live.
 *
 * Dark behind the `omni_po` runtime flag (Admin > Integrations): 404 while
 * off, matching the premium-confirmations convention so the frontend treats
 * it as "feature absent", not an error. Role gating is on the route.
 *
 * The Omni key never leaves the server — this proxy exists precisely so the
 * browser never holds it.
 */
class ClaimPurchaseOrdersController extends Controller
{
    public function forClaim(int $id, OmniPurchaseOrderService $svc): JsonResponse
    {
        if (!OmniPurchaseOrderService::enabled()) {
            return response()->json(['message' => 'Not enabled.'], 404);
        }

        $exists = DB::table('claims')->where('id', $id)->exists()
            || DB::table('new_claims')->where('id', $id)->exists();
        if (!$exists) {
            return response()->json(['message' => 'Claim not found.'], 404);
        }

        return response()->json($svc->forClaim($id));
    }
}
