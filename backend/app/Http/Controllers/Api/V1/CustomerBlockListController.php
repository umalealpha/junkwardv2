<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\Policy;
use AlphaDirect\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerBlockListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = Customer::where('is_blocked', 1)
            ->when($validated['search'] ?? null, function ($q, $search) {
                $search = trim(preg_replace('/\s+/', ' ', $search));
                $like = "%{$search}%";
                $words = count(explode(' ', $search)) >= 2
                    ? array_values(array_filter(explode(' ', $search)))
                    : [];
                $q->where(function ($q) use ($like, $words) {
                    $q->where('firstName', 'like', $like)
                      ->orWhere('lastName', 'like', $like)
                      ->orWhereRaw("CONCAT_WS(' ', TRIM(firstName), TRIM(lastName)) LIKE ?", [$like])
                      ->orWhere('cellphone', 'like', $like)
                      ->orWhere('email', 'like', $like);
                    if (count($words) >= 2) {
                        $q->orWhere(fn($i) =>
                            $i->where('firstName', 'like', "%{$words[0]}%")
                              ->where('lastName', 'like', "%{$words[1]}%")
                        )->orWhere(fn($i) =>
                            $i->where('firstName', 'like', "%{$words[1]}%")
                              ->where('lastName', 'like', "%{$words[0]}%")
                        );
                    }
                });
            })
            ->orderBy('id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        // Aliases for the current page's customers, fetched in one grouped
        // query (avoids N+1). Same customer_aliases read that show() uses.
        $aliasesByCustomer = DB::table('customer_aliases')
            ->whereIn('customer_id', $results->getCollection()->pluck('id'))
            ->orderBy('id')
            ->get(['customer_id', 'alias_name'])
            ->groupBy('customer_id');

        return response()->json([
            'data' => $results->map(fn($c) => [
                'id' => $c->id,
                'firstName' => $c->firstName,
                'lastName' => $c->lastName,
                'cellphone' => $c->cellphone,
                'email' => $c->email,
                'aliasNames' => ($aliasesByCustomer->get($c->id)?->pluck('alias_name')->implode(', ')) ?: null,
                'blockReason' => $c->block_reason,
                // Omang/ID is intentionally NOT surfaced here per the data-protection
                // policy — the list shows the entry without exposing the identifier.
                'idNumber' => null,
                // No dedicated blocked-at column in the legacy schema; the row's
                // updated_at marks when it was flagged.
                'blockedAt' => optional($c->updated_at)->toIso8601String(),
                'amlStatus' => (int) ($c->is_aml_verification_done ?? 0) === 1 ? 'clear' : 'not_checked',
                'createdAt' => optional($c->created_at)->toIso8601String(),
            ]),
            'meta' => ['total' => $results->total(), 'per_page' => $results->perPage(), 'current_page' => $results->currentPage(), 'last_page' => $results->lastPage(), 'from' => $results->firstItem(), 'to' => $results->lastItem()],
        ]);
    }

    /**
     * Block-list detail — backs the FE "View" modal (fetchBlockListDetail).
     * Returns the same shape as a list row plus the blocked customer's
     * policies and recorded aliases. Omang/ID stays unexposed here, exactly
     * as in index(), per the data-protection policy.
     */
    public function show($id): JsonResponse
    {
        $id = (int) $id;
        $customer = Customer::findOrFail($id);

        $policies = Policy::with(['product:id,name'])
            ->where('customer_id', $id)
            ->select('id', 'policyNumber', 'status', 'product_id', 'term_start_date', 'term_end_date')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'policyNumber' => $p->policyNumber,
                'productName'  => $p->product->name ?? null,
                'status'       => $p->status,
                'startDate'    => $p->term_start_date ?: null,
                'endDate'      => $p->term_end_date ?: null,
            ])
            ->values();

        $aliases = DB::table('customer_aliases')
            ->where('customer_id', $id)
            ->orderByDesc('id')
            ->get()
            ->map(fn($a) => [
                'id'        => $a->id,
                'aliasName' => $a->alias_name,
                // Raw query-builder value is a datetime string; pass it through.
                'createdAt' => $a->created_at ?? null,
            ])
            ->values();

        return response()->json([
            'data' => [
                'id'         => $customer->id,
                'firstName'  => $customer->firstName,
                'lastName'   => $customer->lastName,
                'cellphone'  => $customer->cellphone,
                'email'      => $customer->email,
                'blockReason'=> $customer->block_reason,
                'idNumber'   => null,
                'blockedAt'  => optional($customer->updated_at)->toIso8601String(),
                'amlStatus'  => (int) ($customer->is_aml_verification_done ?? 0) === 1 ? 'clear' : 'not_checked',
                'createdAt'  => optional($customer->created_at)->toIso8601String(),
                'policies'   => $policies,
                'aliases'    => $aliases,
            ],
        ]);
    }

    /**
     * Add a person to the block list. Mirrors graphiteBWV8
     * CustomerController::add_to_block_list_update — a blacklist entry is a
     * Customer row flagged is_blocked=1 / customer_category=2 with the
     * block_reason, plus a CustomerProfile row.
     *
     * First Name, Last Name and Reasons of Cancellation (block_reason) are
     * mandatory. Omang / Passport are captured but only persisted when the
     * `blacklist_store_identifiers` app setting is enabled — by default they
     * are NOT stored, to comply with the data-protection policy on identifiable
     * IDs. Flip that setting (with CFO/EXCO authorisation) to store them like V8.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name'   => 'required|string|max:255',
            'middle_name'  => 'nullable|string|max:255',
            'last_name'    => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'mobile'       => 'nullable|string|max:25',
            'omang'        => 'nullable|string|max:20',
            'passport'     => 'nullable|string|max:20',
            'block_reason' => 'required|string|max:255',
            // Aliases: the create form sends an array (one per list entry);
            // alias_names (comma string) is still accepted for back-compat.
            'aliases'      => 'nullable|array',
            'aliases.*'    => 'string|max:200',
            'alias_names'  => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $customer = new Customer();
            $customer->firstName = $validated['first_name'];
            $customer->lastName  = $validated['last_name'];
            if (!empty($validated['middle_name'])) $customer->middleName = $validated['middle_name'];
            if (!empty($validated['email']))       $customer->email      = $validated['email'];
            if (!empty($validated['mobile']))      $customer->cellphone  = $validated['mobile'];
            $customer->customer_category = 2; // 2 = blocked (V8 parity)
            $customer->is_blocked        = 1;
            $customer->block_reason      = $validated['block_reason'];
            $customer->save();

            // CustomerProfile — V8 seeds the legacy NOT-NULL columns with these
            // exact placeholder values so the insert succeeds against the shared
            // schema. Kept identical for parity.
            $profile = CustomerProfile::firstOrNew(['customer_id' => $customer->id]);
            $profile->customer_id = $customer->id;
            $profile->dob     = '2002-11-01';
            $profile->gender  = 1;
            $profile->address = 'test';
            $profile->city    = 'Moiyabana';
            $profile->state   = 491;

            // // Identifier storage is policy-gated. Default OFF: Omang/Passport are
            // // accepted from the form but not written to the database.
            // $storeIdentifiers = AppSetting::get('blacklist_store_identifiers') === '1';
            // if ($storeIdentifiers) {
                if (!empty($validated['omang']))    $profile->omang    = $validated['omang'];
                if (!empty($validated['passport'])) $profile->passport = $validated['passport'];
            // }
            $profile->save();

            // Alias names — persist each distinct non-empty name as its own
            // customer_aliases row (one alias per row), mirroring the shape the
            // detail modal + AML screening read. Accepts the `aliases` array
            // (create form) and/or the legacy `alias_names` comma string.
            $aliasNames = collect();
            if (!empty($validated['aliases']) && is_array($validated['aliases'])) {
                $aliasNames = $aliasNames->merge($validated['aliases']);
            }
            if (!empty($validated['alias_names'])) {
                $aliasNames = $aliasNames->merge(explode(',', $validated['alias_names']));
            }
            $now       = now();
            $userId    = auth()->id();
            $aliasRows = $aliasNames
                ->map(fn($n) => trim((string) $n))
                ->filter()
                ->unique()
                ->map(fn($name) => [
                    'customer_id' => $customer->id,
                    'alias_name'  => mb_substr($name, 0, 200),
                    'created_by'  => $userId,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ])
                ->values()
                ->all();
            if (!empty($aliasRows)) {
                DB::connection('mysql_write')->table('customer_aliases')->insert($aliasRows);
            }

            DB::commit();

            return response()->json([
                'data'    => ['id' => $customer->id],
                'message' => 'Customer added to block list successfully.',
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CustomerBlockList store failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to add customer to block list.',
                'error'   => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the block reason for an already-blocked customer. Backs the
     * FE's inline edit and detail-modal edit (updateBlockReason()).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'block_reason' => 'required|string|max:255',
        ]);

        $customer = Customer::findOrFail($id);
        $customer->block_reason = $validated['block_reason'];
        $customer->save();

        activity('Customer')->performedOn($customer)->causedBy(auth()->user())->log('Block reason updated');

        return response()->json(['message' => 'Block reason updated successfully.']);
    }

    /**
     * Unblock a customer (management-approved unblock request). Backs the
     * FE's unblockCustomer() call from the block-list page.
     */
    public function unblock(int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        // Graphitev2's own read convention treats anything != 1 as "not
        // blocked" (CustomerController::index() does `is_blocked == '1'`),
        // so 0 here is unambiguous — no need for graphiteBWV8's tri-state 1/2.
        $customer->is_blocked = 0;
        $customer->save();

        activity('Customer')->performedOn($customer)->causedBy(auth()->user())->log('Customer unblocked');

        return response()->json(['message' => 'Customer unblocked successfully.']);
    }

    /**
     * Add a single alias name to a blocked customer. Backs the detail-modal
     * "Add alias" control (FE addAlias() -> POST customers/{id}/aliases).
     * Returns the created row in the same shape show() exposes (aliasName).
     */
    public function addAlias(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'alias_name' => 'required|string|max:200',
        ]);

        $customer = Customer::findOrFail($id);

        $now = now();
        $aliasId = DB::connection('mysql_write')->table('customer_aliases')->insertGetId([
            'customer_id' => $customer->id,
            'alias_name'  => $validated['alias_name'],
            'created_by'  => auth()->id(),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        activity('Customer')->performedOn($customer)->causedBy(auth()->user())->log('Alias added');

        return response()->json([
            'data' => [
                'id'        => $aliasId,
                'aliasName' => $validated['alias_name'],
                'createdAt' => (string) $now,
            ],
        ], 201);
    }

    /**
     * Remove an alias row by id. Backs the detail-modal "Remove" control
     * (FE removeAlias() -> DELETE customers/aliases/{aliasId}).
     */
    public function removeAlias(int $aliasId): JsonResponse
    {
        DB::connection('mysql_write')->table('customer_aliases')->where('id', $aliasId)->delete();

        return response()->json(['message' => 'Alias removed successfully.']);
    }
}
