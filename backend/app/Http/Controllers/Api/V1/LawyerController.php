<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lawyers master-data CRUD.
 *
 * Backs the Lawyers admin page (sidebar). Mirrors AssessorController — uses the
 * query builder (no model) to match the SupplierController convention in this
 * codebase.
 */
class LawyerController extends Controller
{
    /**
     * GET /lawyers
     * ?search= filters name/email/company. ?active=1 returns only active.
     * Paginated for the admin grid; pass per_page large to fetch the full list.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('lawyers')->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(fn($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhere('company', 'like', "%{$s}%"));
        }
        if ($request->filled('category')) {
            $cat = $request->input('category');
            // Match the requested category plus "both" and untagged rows.
            $query->where(fn($q) => $q
                ->where('category', $cat)
                ->orWhere('category', 'both')
                ->orWhereNull('category')
                ->orWhere('category', ''));
        }
        if ($request->has('active')) {
            $query->where('is_active', (int) (bool) $request->input('active'));
        }

        $results = $query->simplePaginate($request->input('per_page', 25));

        return response()->json([
            'data' => collect($results->items())->map(fn($a) => $this->present($a)),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ],
        ]);
    }

    /** GET /lawyers/{id} */
    public function show(int $id): JsonResponse
    {
        $row = DB::table('lawyers')->where('id', $id)->first();
        if (!$row) return response()->json(['message' => 'Lawyer not found.'], 404);
        return response()->json(['data' => $this->present($row)]);
    }

    /** POST /lawyers */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $id = DB::table('lawyers')->insertGetId($data);
        return response()->json(['message' => 'Lawyer created.', 'data' => ['id' => $id]], 201);
    }

    /** PUT /lawyers/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        if (!DB::table('lawyers')->where('id', $id)->exists()) {
            return response()->json(['message' => 'Lawyer not found.'], 404);
        }
        $data = $this->validated($request);
        $data['updated_at'] = now();
        DB::table('lawyers')->where('id', $id)->update($data);
        return response()->json(['message' => 'Lawyer updated.']);
    }

    /** DELETE /lawyers/{id} */
    public function destroy(int $id): JsonResponse
    {
        DB::table('lawyers')->where('id', $id)->delete();
        return response()->json(['message' => 'Lawyer deleted.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'      => 'required|string|max:200',
            'email'     => 'nullable|email|max:200',
            'phone'     => 'nullable|string|max:50',
            'company'   => 'nullable|string|max:200',
            'category'  => 'nullable|in:motor,non_motor,both',
            'is_active' => 'nullable|boolean',
            'notes'     => 'nullable|string|max:2000',
        ]);
        $data['is_active'] = (int) ($request->input('is_active', true));
        return $data;
    }

    private function present(object $a): array
    {
        return [
            'id'        => $a->id,
            'name'      => $a->name,
            'email'     => $a->email,
            'phone'     => $a->phone,
            'company'   => $a->company,
            'category'  => $a->category,
            'is_active' => (bool) $a->is_active,
            'notes'     => $a->notes,
        ];
    }
}
