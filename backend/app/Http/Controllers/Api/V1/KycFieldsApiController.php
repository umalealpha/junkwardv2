<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class KycFieldsApiController extends Controller
{
    /**
     * GET /kyc-fields — List all KYC fields.
     */
    public function index(): JsonResponse
    {
        $fields = DB::table('kyc_fields')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'customer_kyc_column', 'created_at', 'updated_at']);

        return response()->json(['data' => $fields]);
    }

    /**
     * GET /kyc-fields/columns — Available customer_kyc columns for mapping.
     */
    public function columns(): JsonResponse
    {
        $columns = Schema::getColumnListing('customer_kyc');

        // Filter out system columns
        $exclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'customer_id', 'user_id'];
        $columns = array_values(array_diff($columns, $exclude));

        return response()->json(['data' => $columns]);
    }

    /**
     * POST /kyc-fields — Create a new KYC field.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                => 'required|string|max:30|unique:kyc_fields,name',
            'customer_kyc_column' => 'required|string|max:100',
        ]);

        $id = DB::table('kyc_fields')->insertGetId([
            'name'                => $data['name'],
            'slug'                => Str::slug($data['name']),
            'customer_kyc_column' => $data['customer_kyc_column'],
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return response()->json([
            'message' => 'KYC field created.',
            'data'    => ['id' => $id],
        ], 201);
    }

    /**
     * PUT /kyc-fields/{id} — Update a KYC field.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $field = DB::table('kyc_fields')->where('id', $id)->first();
        if (!$field) {
            return response()->json(['message' => 'KYC field not found.'], 404);
        }

        $data = $request->validate([
            'name'                => "required|string|max:30|unique:kyc_fields,name,{$id}",
            'customer_kyc_column' => 'required|string|max:100',
        ]);

        DB::table('kyc_fields')->where('id', $id)->update([
            'name'                => $data['name'],
            'slug'                => Str::slug($data['name']),
            'customer_kyc_column' => $data['customer_kyc_column'],
            'updated_at'          => now(),
        ]);

        return response()->json(['message' => 'KYC field updated.']);
    }

    /**
     * DELETE /kyc-fields/{id} — Delete a KYC field.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = DB::table('kyc_fields')->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['message' => 'KYC field not found.'], 404);
        }
        return response()->json(['message' => 'KYC field deleted.']);
    }
}
