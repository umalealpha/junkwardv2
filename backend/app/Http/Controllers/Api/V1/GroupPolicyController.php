<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|integer|in:0,1,2,3',
            'product_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('company_policies as cp')
            ->join('policies as p', 'cp.policyNumber', '=', 'p.policyNumber')
            ->leftJoin('customer as c', 'c.id', '=', 'p.customer_id')
            ->leftJoin('products as pr', 'pr.id', '=', 'p.product_id')
            ->select([
                'p.id', 'p.policyNumber', 'p.status', 'p.premium', 'p.created_at',
                'c.firstName', 'c.lastName', 'c.cellphone',
                'pr.name as product_name', 'cp.company_id',
            ])
            ->when($validated['status'] ?? null, fn($q, $v) => $q->where('p.status', $v))
            ->when($validated['product_id'] ?? null, fn($q, $v) => $q->where('p.product_id', $v))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $search = trim(preg_replace('/\s+/', ' ', $search));
                $like = "%{$search}%";
                $words = count(explode(' ', $search)) >= 2
                    ? array_values(array_filter(explode(' ', $search)))
                    : [];
                $q->where(function ($q) use ($like, $words) {
                    $q->where('p.policyNumber', 'like', $like)
                      ->orWhere('c.firstName', 'like', $like)
                      ->orWhere('c.lastName', 'like', $like)
                      ->orWhereRaw("CONCAT_WS(' ', TRIM(c.firstName), TRIM(c.lastName)) LIKE ?", [$like])
                      ->orWhere('c.cellphone', 'like', $like);
                    if (count($words) >= 2) {
                        $q->orWhere(fn($i) =>
                            $i->where('c.firstName', 'like', "%{$words[0]}%")
                              ->where('c.lastName', 'like', "%{$words[1]}%")
                        )->orWhere(fn($i) =>
                            $i->where('c.firstName', 'like', "%{$words[1]}%")
                              ->where('c.lastName', 'like', "%{$words[0]}%")
                        );
                    }
                });
            })
            ->orderBy('p.id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'id' => $r->id,
                'policyNumber' => $r->policyNumber,
                'status' => $r->status,
                'premium' => $r->premium,
                'customerName' => trim(($r->firstName ?? '') . ' ' . ($r->lastName ?? '')),
                'cellphone' => $r->cellphone,
                'productName' => $r->product_name,
                'companyId' => $r->company_id,
                'createdAt' => $r->created_at,
            ]),
            'meta' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem(),
            ],
        ]);
    }
}
