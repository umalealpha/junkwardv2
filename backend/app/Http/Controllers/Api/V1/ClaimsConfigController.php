<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\ClaimsConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Claims Master-Data — tracker-only config store CRUD.
 *
 * Admin-CRUD over the claims_config table: the Graphite home for the
 * Claims-Tracker-only master lists that have no existing Graphite table
 * (Comment Priorities, Mention Allowed Domains, Notification Settings,
 * Notification Pilot Numbers, Policy-Library Branches/Products/Coverages
 * tags, FAC Clients). Everything must live in Graphite because the tracker
 * DB is deleted after migration.
 *
 * Category set is whitelisted (ClaimsConfig::CATEGORIES) so the surface is
 * predictable. Role-gated (Admin | Super Admin) at the route layer.
 */
class ClaimsConfigController extends Controller
{
    /** GET /claims-config — the whitelisted categories with live counts. */
    public function categories(): JsonResponse
    {
        $counts = ClaimsConfig::query()
            ->selectRaw('category, COUNT(*) as cnt')
            ->groupBy('category')
            ->pluck('cnt', 'category')->toArray();

        $data = [];
        foreach (ClaimsConfig::CATEGORIES as $slug => $label) {
            $data[] = [
                'category' => $slug,
                'label'    => $label,
                'count'    => (int) ($counts[$slug] ?? 0),
            ];
        }

        return response()->json(['data' => $data]);
    }

    /** GET /claims-config/{category} — entries in a category. */
    public function index(Request $request, string $category): JsonResponse
    {
        if (!ClaimsConfig::isValidCategory($category)) {
            return response()->json(['message' => 'Unknown config category.'], 404);
        }

        $query = ClaimsConfig::where('category', $category)
            ->orderBy('sort_order')->orderBy('label');

        if ($request->filled('search')) {
            $query->where('label', 'like', '%' . $request->input('search') . '%');
        }
        if ($request->has('active')) {
            $query->where('is_active', (int) (bool) $request->input('active'));
        }

        return response()->json([
            'data'  => $query->get()->map(fn($r) => $this->present($r)),
            'label' => ClaimsConfig::CATEGORIES[$category],
        ]);
    }

    /** POST /claims-config/{category} */
    public function store(Request $request, string $category): JsonResponse
    {
        if (!ClaimsConfig::isValidCategory($category)) {
            return response()->json(['message' => 'Unknown config category.'], 404);
        }

        $data = $request->validate([
            'label' => [
                'required', 'string', 'max:191',
                Rule::unique('claims_config', 'label')->where(fn($q) => $q->where('category', $category)),
            ],
            'value'      => 'nullable|string|max:2000',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
        ]);

        $row = ClaimsConfig::create([
            'category'   => $category,
            'label'      => $data['label'],
            'value'      => $data['value'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return response()->json(['message' => 'Entry created.', 'data' => $this->present($row)], 201);
    }

    /** PUT /claims-config/{category}/{id} */
    public function update(Request $request, string $category, int $id): JsonResponse
    {
        if (!ClaimsConfig::isValidCategory($category)) {
            return response()->json(['message' => 'Unknown config category.'], 404);
        }
        $row = ClaimsConfig::where('category', $category)->where('id', $id)->first();
        if (!$row) {
            return response()->json(['message' => 'Entry not found.'], 404);
        }

        $data = $request->validate([
            'label' => [
                'required', 'string', 'max:191',
                Rule::unique('claims_config', 'label')
                    ->where(fn($q) => $q->where('category', $category))
                    ->ignore($id),
            ],
            'value'      => 'nullable|string|max:2000',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
        ]);

        $row->label = $data['label'];
        $row->value = $data['value'] ?? null;
        if ($request->has('sort_order')) $row->sort_order = (int) $data['sort_order'];
        if ($request->has('is_active'))  $row->is_active  = $request->boolean('is_active');
        $row->save();

        return response()->json(['message' => 'Entry updated.', 'data' => $this->present($row)]);
    }

    /** DELETE /claims-config/{category}/{id} */
    public function destroy(string $category, int $id): JsonResponse
    {
        if (!ClaimsConfig::isValidCategory($category)) {
            return response()->json(['message' => 'Unknown config category.'], 404);
        }
        $deleted = ClaimsConfig::where('category', $category)->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['message' => 'Entry not found.'], 404);
        }
        return response()->json(['message' => 'Entry deleted.']);
    }

    private function present(ClaimsConfig $r): array
    {
        return [
            'id'         => $r->id,
            'category'   => $r->category,
            'label'      => $r->label,
            'value'      => $r->value,
            'sort_order' => $r->sort_order,
            'is_active'  => (bool) $r->is_active,
        ];
    }
}
