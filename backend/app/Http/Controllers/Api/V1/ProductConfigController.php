<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductConfigController extends Controller
{
    public function detail(int $id): JsonResponse
    {
        $product = DB::table('products')->where('id', $id)->first();
        if (!$product) return response()->json(['message' => 'Product not found.'], 404);

        $plans = DB::table('product_plans')->where('product_id', $id)->orderBy('plan')->get();
        $factors = DB::table('product_factors_main as fm')
            ->where('fm.product_id', $id)
            ->get();
        $factorValues = DB::table('product_factors_value as fv')
            ->whereIn('fv.factor_main_id', $factors->pluck('id'))
            ->get()
            ->groupBy('factor_main_id');

        return response()->json([
            'data' => [
                'product' => $product,
                'plans' => $plans,
                'factors' => $factors->map(fn($f) => [
                    'id' => $f->id,
                    'name' => $f->name ?? $f->factor_name ?? null,
                    'values' => $factorValues[$f->id] ?? [],
                ]),
            ],
        ]);
    }

    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'status'         => 'nullable|integer|in:0,1',
            'kyc_compliance' => 'nullable|integer',
        ]);
        $data['updated_at'] = now();
        DB::table('products')->where('id', $id)->update($data);
        return response()->json(['message' => 'Product updated.']);
    }

    public function storePlan(Request $request, int $productId): JsonResponse
    {
        $data = $request->validate([
            'plan'        => 'required|string|max:200',
            'sum_assured' => 'nullable|numeric',
            'premium'     => 'nullable|numeric',
            'status'      => 'nullable|integer|in:0,1',
        ]);
        $data['product_id'] = $productId;
        $data['status'] = $data['status'] ?? 1;
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $id = DB::table('product_plans')->insertGetId($data);
        return response()->json(['message' => 'Plan created.', 'data' => ['id' => $id]], 201);
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'plan'        => 'required|string|max:200',
            'sum_assured' => 'nullable|numeric',
            'premium'     => 'nullable|numeric',
            'status'      => 'nullable|integer|in:0,1',
        ]);
        $data['updated_at'] = now();
        DB::table('product_plans')->where('id', $id)->update($data);
        return response()->json(['message' => 'Plan updated.']);
    }

    public function deletePlan(int $id): JsonResponse
    {
        DB::table('product_plans')->where('id', $id)->delete();
        return response()->json(['message' => 'Plan deleted.']);
    }
}
