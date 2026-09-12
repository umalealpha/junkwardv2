<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('suppliers')->where('customer_selected', 0)->orderBy('supplierName');

        if ($request->has('search')) {
            $s = $request->input('search');
            $query->where(fn($q) => $q->where('supplierName', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        if ($request->has('type')) $query->where('supplierType', $request->input('type'));

        $results = $query->simplePaginate($request->input('per_page', 25));

        return response()->json([
            'data' => collect($results->items())->map(fn($s) => [
                'id'       => $s->id,
                'name'     => $s->supplierName,
                'type'     => $s->supplierType ?? null,
                'email'    => $s->email,
                'phone'    => $s->telephone,
                'location' => $s->supplierLocation,
                'vatNo'    => $s->vat_no ?? null,
                'accountNo'=> $s->account_no ?? null,
                'address'  => $s->address ?? null,
            ]),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplierName'     => 'required|string|max:200',
            'supplierType'     => 'nullable|string|max:100',
            'email'            => 'nullable|email|max:200',
            'telephone'        => 'nullable|string|max:50',
            'supplierLocation' => 'nullable|string|max:200',
            'vat_no'           => 'nullable|string|max:50',
            'account_no'       => 'nullable|string|max:50',
            'address'          => 'nullable|string|max:500',
        ]);
        $data['customer_selected'] = 0;
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $id = DB::table('suppliers')->insertGetId($data);
        return response()->json(['message' => 'Supplier created.', 'data' => ['id' => $id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'supplierName'     => 'required|string|max:200',
            'supplierType'     => 'nullable|string|max:100',
            'email'            => 'nullable|email|max:200',
            'telephone'        => 'nullable|string|max:50',
            'supplierLocation' => 'nullable|string|max:200',
            'vat_no'           => 'nullable|string|max:50',
            'account_no'       => 'nullable|string|max:50',
            'address'          => 'nullable|string|max:500',
        ]);
        $data['updated_at'] = now();
        DB::table('suppliers')->where('id', $id)->update($data);
        return response()->json(['message' => 'Supplier updated.']);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table('suppliers')->where('id', $id)->delete();
        return response()->json(['message' => 'Supplier deleted.']);
    }
}
