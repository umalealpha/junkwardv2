<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RealPayContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('realpay_client_contracts as rc')
            ->leftJoin('policies as p', 'p.id', '=', 'rc.policy_id')
            ->leftJoin('customer as c', 'c.id', '=', 'p.customer_id')
            ->orderByDesc('rc.id');

        if ($request->has('search')) {
            $s = $request->input('search');
            $query->where(fn($q) => $q->where('p.policyNumber', 'like', "%{$s}%")
                ->orWhereRaw("CONCAT(c.firstName,' ',c.lastName) LIKE ?", ["%{$s}%"]));
        }
        if ($request->has('status')) $query->where('rc.status', $request->input('status'));

        $results = $query->select([
            'rc.*', 'p.policyNumber',
            DB::raw("CONCAT(c.firstName,' ',c.lastName) as customer_name"),
            'c.cellphone as customer_phone',
        ])->simplePaginate($request->input('per_page', 25));

        return response()->json([
            'data' => collect($results->items()),
            'meta' => ['current_page' => $results->currentPage(), 'per_page' => $results->perPage(), 'has_more' => $results->hasMorePages()],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $contract = DB::table('realpay_client_contracts as rc')
            ->leftJoin('policies as p', 'p.id', '=', 'rc.policy_id')
            ->where('rc.id', $id)
            ->first(['rc.*', 'p.policyNumber']);
        if (!$contract) return response()->json(['message' => 'Not found.'], 404);

        // Installments join by clientNumber + contractNumber (the
        // table has no contract_id FK). Ordered by sequence so the
        // detail view shows them in schedule order.
        $installments = DB::table('realpay_contract_installments')
            ->where('clientNumber', $contract->client_number)
            ->where('contractNumber', $contract->contract_number)
            ->orderBy('InstalmentSequence')
            ->get();

        return response()->json(['data' => ['contract' => $contract, 'installments' => $installments]]);
    }
}
