<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Resources\V1\ProductResource;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = CacheService::remember(
            'v1_products_active',
            fn() => Product::where('status', 1)
                ->select('id', 'name', 'slug', 'status', 'product_type_id', 'isForStart')
                ->orderBy('name')
                ->get(),
            CacheService::CACHE_TTL_VERY_LONG,
            [CacheService::TAG_PRODUCTS]
        );

        return response()->json(['data' => ProductResource::collection($products)]);
    }

    public function plans(int $id): JsonResponse
    {
        $plans = CacheService::remember(
            "v1_product_{$id}_plans",
            fn() => Productplan::where('product_id', $id)
                ->where('status', 1)
                ->select('id', 'product_id', 'plan', 'sum_assured', 'premium')
                ->orderBy('plan')
                ->get(),
            CacheService::CACHE_TTL_VERY_LONG,
            [CacheService::TAG_PRODUCTS]
        );

        return response()->json(['data' => $plans]);
    }

    /**
     * Toggle whether a product is visible on the customer-facing start site.
     * Mirrors the legacy graphite admin's product-edit form which also writes
     * isForStart 0/1 — Alpha Direct only sells MIS / Motor Comp on the public
     * site, so ops uses this to gate the catalogue.
     *
     * Busts both the products tag (admin lists) and lookups tag (the public
     * /lookups/products endpoint) so the change is reflected immediately on
     * start.alphadirect.co.bw.
     *
     * Mid-session safety: when ops disables a product while a customer is
     * already filling the form, the FE may still hold the old list in its
     * persister cache for up to 60s (cacheHints.products.staleTime). The
     * canonical safety net is server-side validation on policy create —
     * whoever wires the V2 replacement for getActivationCodeDataPay2 /
     * graphite/createPolicy MUST verify Product::where('id', $productId)
     *   ->where('isForStart', 1)
     *   ->where('status', 1)
     *   ->exists()
     * and return 422 { error: 'product_unavailable' } if not, so a stale
     * FE submission can't book a discontinued product.
     */
    public function setVisibility(Request $request, int $id): JsonResponse
    {
        $request->validate(['isForStart' => 'required|boolean']);
        $product = Product::findOrFail($id);
        $product->isForStart = $request->boolean('isForStart') ? 1 : 0;
        $product->save();

        // Bust both tags so admin lists and the public start-site catalogue
        // refresh on the next request. Wrapped because non-tagged cache
        // backends (file/database) throw on tags()->flush().
        try { Cache::tags([CacheService::TAG_PRODUCTS, CacheService::TAG_LOOKUPS])->flush(); }
        catch (\Throwable $e) { /* tags unsupported — let the TTL expire naturally */ }

        return response()->json([
            'id'         => $product->id,
            'name'       => $product->name,
            'isForStart' => (int) $product->isForStart,
        ]);
    }
}
