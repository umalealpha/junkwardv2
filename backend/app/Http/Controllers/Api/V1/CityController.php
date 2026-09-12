<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\City;
use AlphaDirect\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * Cities master-data (child of a State).
 *
 * Backs the States & Cities admin page and, indirectly, the City dropdown in
 * the Edit Risk Address modal (which reads the /lookups/states/{id}/cities
 * cache). The list is always scoped by state_id — the cities table holds tens
 * of thousands of rows across every country.
 *
 * Add & edit only — no destroy: risk_address.risk_city and
 * customer_profile.city reference these ids with no FK constraint. Uses the
 * Eloquent model to keep the OwenIt audit trail.
 */
class CityController extends Controller
{
    /** GET /master/cities?state_id=  — state_id required; ?search= filters name. */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['state_id' => 'required|integer']);

        $query = City::with('state:id,name')
            ->where('state_id', $request->input('state_id'))
            ->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where('name', 'like', "%{$s}%");
        }

        $results = $query->simplePaginate($request->input('per_page', 25));

        return response()->json([
            'data' => collect($results->items())->map(fn($c) => $this->present($c)),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ],
        ]);
    }

    /** POST /master/cities */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $city = City::create(['name' => $data['name'], 'state_id' => $data['state_id']]);
        $this->bustLookupCache((int) $data['state_id']);
        return response()->json(['message' => 'City created.', 'data' => ['id' => $city->id]], 201);
    }

    /** PUT /master/cities/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $city = City::find($id);
        if (!$city) return response()->json(['message' => 'City not found.'], 404);

        $oldStateId = (int) $city->state_id;
        $data = $this->validated($request, $id);
        $city->update(['name' => $data['name'], 'state_id' => $data['state_id']]);
        // Bust both the old and new state's list — an edit can move a city.
        $this->bustLookupCache($oldStateId, (int) $data['state_id']);
        return response()->json(['message' => 'City updated.']);
    }

    /**
     * Bust the cities lookup cache for the affected state(s).
     * CacheService::forgetLookups() only flushes the TAG_LOOKUPS tag on
     * tag-capable drivers (Redis); on file/db drivers we must forget the exact
     * `cities_state_{id}` keys LookupController caches under, or the Risk
     * Address City dropdown serves stale data.
     */
    private function bustLookupCache(int ...$stateIds): void
    {
        foreach (array_unique($stateIds) as $sid) {
            Cache::forget("cities_state_{$sid}");
        }
        CacheService::forgetLookups();
    }

    /**
     * name is unique within its parent state and capped at the cities.name
     * column width (varchar(30)). state_id must reference an existing state.
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $stateId = $request->input('state_id');
        return $request->validate([
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'name'     => [
                'required', 'string', 'max:30',
                Rule::unique('cities', 'name')
                    ->where(fn($q) => $q->where('state_id', $stateId))
                    ->ignore($ignoreId),
            ],
        ]);
    }

    private function present(City $c): array
    {
        return [
            'id'         => $c->id,
            'name'       => $c->name,
            'state_id'   => $c->state_id,
            'state_name' => optional($c->state)->name,
        ];
    }
}
