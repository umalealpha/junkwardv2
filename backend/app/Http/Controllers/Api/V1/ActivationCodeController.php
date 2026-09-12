<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivationCodeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('activation as a')
            ->leftJoin('products as p', 'p.id', '=', 'a.product_id')
            ->orderByDesc('a.id');

        if ($request->has('status'))  $query->where('a.status', $request->input('status'));
        if ($request->has('search')) {
            $s = $request->input('search');
            $query->where(fn($q) => $q->where('a.serial_code', 'like', "%{$s}%")->orWhere('a.activation_code', 'like', "%{$s}%"));
        }
        if ($request->has('product_id')) $query->where('a.product_id', $request->input('product_id'));

        $results = $query->select([
            'a.id', 'a.serial_code', 'a.activation_code', 'a.product_id',
            'p.name as product_name', 'a.status', 'a.printed', 'a.recycle',
            'a.rack_no', 'a.city', 'a.vendor', 'a.branch', 'a.created_at',
        ])->simplePaginate($request->input('per_page', 25));

        return response()->json([
            'data' => collect($results->items())->map(fn($a) => [
                'id'             => $a->id,
                'serialCode'     => $a->serial_code,
                'activationCode' => $a->activation_code,
                'productId'      => $a->product_id,
                'productName'    => $a->product_name,
                'status'         => $a->status ? 'Activated' : 'Available',
                'statusRaw'      => $a->status,
                'printed'        => $a->printed,
                'recycled'       => $a->recycle,
                'rackNo'         => $a->rack_no,
                'city'           => $a->city,
                'createdAt'      => $a->created_at,
            ]),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ],
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:50']);

        $code = DB::table('activation as a')
            ->leftJoin('products as p', 'p.id', '=', 'a.product_id')
            ->where('a.activation_code', $data['code'])
            ->orWhere('a.serial_code', $data['code'])
            ->first([
                'a.id', 'a.serial_code', 'a.activation_code', 'a.product_id',
                'p.name as product_name', 'a.status', 'a.created_at',
            ]);

        if (!$code) return response()->json(['message' => 'Code not found.', 'valid' => false], 404);

        return response()->json([
            'data' => [
                'id'             => $code->id,
                'serialCode'     => $code->serial_code,
                'activationCode' => $code->activation_code,
                'productName'    => $code->product_name,
                'status'         => $code->status ? 'Activated' : 'Available',
                'valid'          => $code->status == 0,
            ],
        ]);
    }

    public function activated(Request $request): JsonResponse
    {
        $query = DB::table('activation as a')
            ->leftJoin('products as p', 'p.id', '=', 'a.product_id')
            ->leftJoin('policies as pol', function ($j) {
                $j->on('pol.activation_code', '=', 'a.activation_code');
            })
            ->where('a.status', 1)
            ->orderByDesc('a.updated_at');

        if ($request->has('search')) {
            $s = $request->input('search');
            $query->where(fn($q) => $q->where('a.activation_code', 'like', "%{$s}%")
                ->orWhere('pol.policyNumber', 'like', "%{$s}%"));
        }

        $results = $query->select([
            'a.id', 'a.serial_code', 'a.activation_code',
            'p.name as product_name',
            'pol.id as policy_id', 'pol.policyNumber',
            'a.updated_at as activated_at',
        ])->simplePaginate($request->input('per_page', 25));

        return response()->json([
            'data' => collect($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ],
        ]);
    }
}
