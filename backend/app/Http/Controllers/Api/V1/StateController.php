<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\State;
use AlphaDirect\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * States / Provinces master-data (Botswana, country_id = 28).
 *
 * Backs the States & Cities admin page and, indirectly, the Province/State
 * dropdown in the Edit Risk Address modal (which reads the /lookups/* cache).
 *
 * Add & edit only — no destroy: risk_address.risk_state and
 * customer_profile.state reference these ids with no FK constraint, so a
 * delete would orphan them. Uses the Eloquent model (not the query-builder
 * convention) to keep the OwenIt audit trail on this reference data.
 */
class StateController extends Controller
{
    /** Botswana — new provinces must sit under this country to appear in policy_create_data. */
    private const COUNTRY_ID = 28;

    /** GET /master/states  — ?search= filters name; paginated for the grid. */
    public function index(Request $request): JsonResponse
    {
        $query = State::where('country_id', self::COUNTRY_ID)
            ->withCount('cities')
            ->orderBy('name');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where('name', 'like', "%{$s}%");
        }

        $results = $query->simplePaginate($request->input('per_page', 25));

        return response()->json([
            'data' => collect($results->items())->map(fn($s) => $this->present($s)),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ],
        ]);
    }

    /** POST /master/states */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $state = State::create(['name' => $data['name'], 'country_id' => self::COUNTRY_ID]);
        $this->bustLookupCache();
        return response()->json(['message' => 'Province created.', 'data' => ['id' => $state->id]], 201);
    }

    /** PUT /master/states/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $state = State::find($id);
        if (!$state) return response()->json(['message' => 'Province not found.'], 404);

        $data = $this->validated($request, $id);
        $state->update(['name' => $data['name']]);
        $this->bustLookupCache();
        return response()->json(['message' => 'Province updated.']);
    }

    /**
     * Bust the lookup caches that surface states. CacheService::forgetLookups()
     * alone only clears the TAG_LOOKUPS tag on tag-capable drivers (Redis); on
     * file/db drivers we must forget the exact keys LookupController caches
     * under, or the Risk Address Province dropdown serves stale data.
     */
    private function bustLookupCache(): void
    {
        Cache::forget('public_states_country_' . self::COUNTRY_ID);
        Cache::forget('policy_create_data_v2');
        CacheService::forgetLookups();
    }

    /**
     * name is unique per country (case-insensitive via the DB collation) and
     * capped at the states.name column width (varchar(30)).
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:30',
                Rule::unique('states', 'name')
                    ->where(fn($q) => $q->where('country_id', self::COUNTRY_ID))
                    ->ignore($ignoreId),
            ],
        ]);
    }

    private function present(State $s): array
    {
        return [
            'id'           => $s->id,
            'name'         => $s->name,
            'country_id'   => $s->country_id,
            'cities_count' => $s->cities_count ?? null,
        ];
    }
}
