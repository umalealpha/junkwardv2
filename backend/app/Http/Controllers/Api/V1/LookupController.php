<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Agency;
use AlphaDirect\Banks;
use AlphaDirect\City;
use AlphaDirect\Config;
use AlphaDirect\Country;
use AlphaDirect\KycCompliance;
use AlphaDirect\DeviceMakeModel;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Lookup;
use AlphaDirect\Models\Company;
use AlphaDirect\Models\CoverageMaster;
use AlphaDirect\Product;
use AlphaDirect\Productplan;
use AlphaDirect\Services\CacheService;
use AlphaDirect\State;
use AlphaDirect\User;
use AlphaDirect\VehicleMake;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LookupController extends Controller
{
    /**
     * All lookup data needed for the DOMG/COMG policy creation wizard.
     * Fetches construction types from lookups table for all 15+ available types.
     */
    public function policyCreateData(): JsonResponse
    {
        $data = CacheService::remember('policy_create_data_v2', function () {
            return [
                'products' => Product::whereIn('id', [7, 8, 16, 17, 18, 19, 20, 22, 23, 24])
                    ->select('id', 'name')
                    ->get(),

                'premium_frequencies' => [
                    ['id' => '1', 'name' => 'MONTHLY'],
                    ['id' => '3', 'name' => 'ANNUAL'],
                    ['id' => '5', 'name' => 'QUARTERLY'],
                    ['id' => '6', 'name' => 'MANUAL INPUT'],
                ],

                'source_of_income' => [
                    ['id' => 'unemployed', 'name' => 'Unemployed'],
                    ['id' => 'employment', 'name' => 'Employment'],
                    ['id' => 'pensioner_retired', 'name' => 'Pensioner/Retired'],
                    ['id' => 'bussiness', 'name' => 'Self-Employment/Business'],
                    ['id' => 'inheritance', 'name' => 'Inheritance'],
                    ['id' => 'gifts', 'name' => 'Gifts'],
                    ['id' => 'investments', 'name' => 'Investments'],
                ],

                'entity_types' => [
                    ['id' => 'Individual', 'name' => 'Individual'],
                    ['id' => 'Organisation', 'name' => 'Organisation'],
                ],

                'genders' => [
                    ['id' => 'Male', 'name' => 'Male'],
                    ['id' => 'Female', 'name' => 'Female'],
                ],

                'marital_statuses' => [
                    ['id' => 'Single', 'name' => 'Single'],
                    ['id' => 'Married', 'name' => 'Married'],
                    ['id' => 'Divorced', 'name' => 'Divorced'],
                    ['id' => 'Widowed', 'name' => 'Widowed'],
                ],

                'currently_insured' => Lookup::where('key', 'are_you_currently_insured')
                    ->select('id', 'value as name')
                    ->get(),

                'hear_about_alpha' => Lookup::where('key', 'hear_about_alphadirect')
                    ->select('id', 'value as name')
                    ->get(),

                'states' => State::where('country_id', 28)
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get(),

                'construction_types' => Lookup::get()->where('key', 'risk_construction_type')
                    ->keyBy('value')
                    ->map(function($d) {
                        return [
                            'id' => $d->value,
                            'name' => $d->value
                        ];
                    })->values(),

                'vehicle_purposes' => [
                    ['id' => 'Private', 'name' => 'Private'],
                    ['id' => 'Commercial', 'name' => 'Commercial'],
                    ['id' => 'Public', 'name' => 'Public'],
                ],

                'vehicle_conditions' => [
                    ['id' => 'Very Poor', 'name' => 'Very Poor'],
                    ['id' => 'Poor', 'name' => 'Poor'],
                    ['id' => 'Good', 'name' => 'Good'],
                    ['id' => 'Very Good', 'name' => 'Very Good'],
                ],

                'member_relations' => [
                    ['id' => 'Spouse', 'name' => 'Spouse'],
                    ['id' => 'Child', 'name' => 'Child'],
                    ['id' => 'Parent', 'name' => 'Parent'],
                    ['id' => 'Sibling', 'name' => 'Sibling'],
                    ['id' => 'Other', 'name' => 'Other'],
                ],

                'beneficiary_relations' => [
                    ['id' => 'Spouse', 'name' => 'Spouse'],
                    ['id' => 'Child', 'name' => 'Child'],
                    ['id' => 'Parent', 'name' => 'Parent'],
                    ['id' => 'Sibling', 'name' => 'Sibling'],
                    ['id' => 'Friend', 'name' => 'Friend'],
                    ['id' => 'Business Partner', 'name' => 'Business Partner'],
                    ['id' => 'Other', 'name' => 'Other'],
                ],

                'billing_methods' => [
                    ['id' => 'DPO', 'name' => 'DPO (Debit Order)'],
                    ['id' => 'RealPay', 'name' => 'RealPay (Direct Debit)'],
                    ['id' => 'Orange', 'name' => 'Orange Money'],
                    ['id' => 'NGenius', 'name' => 'NGenius (Card)'],
                    ['id' => 'Cash', 'name' => 'Cash'],
                ],

                'account_types' => [
                    ['id' => 'Savings', 'name' => 'Savings'],
                    ['id' => 'Current', 'name' => 'Current/Cheque'],
                ],

                'banks' => Banks::select('id', 'bank_name as name')->orderBy('bank_name')->get(),
            ];
        }, CacheService::CACHE_TTL_VERY_LONG, [CacheService::TAG_LOOKUPS]);

        return response()->json(['data' => $data]);
    }

    /**
     * All departments for agent/user creation form.
     */
    public function departments(): JsonResponse
    {
        $departments = CacheService::remember('departments_all', function () {
            return \AlphaDirect\Department::select('id', 'name')
                ->orderBy('name')
                ->get();
        }, CacheService::CACHE_TTL_LONG, [CacheService::TAG_LOOKUPS]);

        return response()->json(['departments' => $departments]);
    }

    /**
     * All roles for agent/user creation form.
     */
    public function roles(): JsonResponse
    {
        $roles = CacheService::remember('roles_all', function () {
            return \Spatie\Permission\Models\Role::select('id', 'name')
                ->orderBy('name')
                ->get();
        }, CacheService::CACHE_TTL_LONG, [CacheService::TAG_LOOKUPS]);

        return response()->json(['roles' => $roles]);
    }

    /**
     * Active agencies — supports search-as-you-type.
     */
    public function agencies(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $cacheKey = 'agencies_search_' . md5($search);
        $ttl = $search ? CacheService::CACHE_TTL_SHORT : CacheService::CACHE_TTL_LONG;

        $agencies = CacheService::remember($cacheKey, function () use ($search) {
            // No row cap: agencies are a small bounded set and this lookup
            // backs full A–Z agency dropdowns/filters (Users, Agents,
            // Commission) plus the cached useAgencies() hook. A limit(50) here
            // silently truncated a name-sorted list to the first 50 — agencies
            // from ~M onward vanished from every consumer once >50 were active.
            // The `search` filter remains for type-ahead narrowing.
            return Agency::where('status', 1)
                ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }, $ttl, [CacheService::TAG_LOOKUPS]);

        return response()->json(['agencies' => $agencies, 'data' => $agencies]);
    }

    /**
     * Agents belonging to an agency.
     */
    public function agentsByAgency(int $agencyId): JsonResponse
    {
        $agents = CacheService::remember("agents_agency_{$agencyId}", function () use ($agencyId) {
            return User::where('agency_id', $agencyId)
                ->select('id', 'firstName', 'lastName')
                ->get()
                ->map(fn($u) => [
                    'id'   => $u->id,
                    'name' => trim($u->firstName . ' ' . $u->lastName),
                ]);
        }, CacheService::CACHE_TTL_LONG, [CacheService::TAG_LOOKUPS]);

        return response()->json(['data' => $agents]);
    }

    /**
     * Agents who have written at least one policy — backs the policy list
     * "Filter By Agent" dropdown. Mirrors the legacy Graphite dropdown
     * (Policy::join('users', ...)->where('users.active', 1)->groupBy('agent_id')),
     * which only lists agents actually referenced by policies.agent_id
     * rather than every user with an agency.
     */
    public function agents(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $cacheKey = 'lookup_agents_' . md5($search);
        $ttl = $search ? CacheService::CACHE_TTL_SHORT : CacheService::CACHE_TTL_LONG;

        $agents = CacheService::remember($cacheKey, function () use ($search) {
            return User::where('active', 1)
                ->whereIn('id', function ($q) {
                    $q->select('agent_id')->from('policies')->whereNotNull('agent_id');
                })
                ->when($search, fn($q) => $q->where(fn($q2) =>
                    $q2->where('firstName', 'like', "%{$search}%")
                       ->orWhere('lastName', 'like', "%{$search}%")
                ))
                ->select('id', 'firstName', 'lastName')
                ->orderBy('firstName')
                ->get()
                ->map(fn($u) => ['id' => $u->id, 'name' => trim("{$u->firstName} {$u->lastName}")]);
        }, $ttl, [CacheService::TAG_LOOKUPS]);

        return response()->json(['data' => $agents]);
    }

    /**
     * Products visible on the customer-facing start site. Mirrors the legacy
     * `MobileAppV2Controller::getProductsForStart()` which filters on
     * status=1 AND isForStart=1 — Alpha Direct only sells MIS (Tsosologo)
     * and Motor Comprehensive on the public site, so the rest of the
     * catalogue (commercial, group, engineering, etc.) must stay hidden
     * even though they exist as products in the DB.
     *
     * Returned shape:
     *   { products: [{ id, name, slug?, image?, has_activation_code?,
     *                  has_member?, has_vehicle?, premium_type_id? }, ...] }
     * Cached aggressively — products only change when ops toggles isForStart.
     */
    public function productsForStart(): JsonResponse
    {
        // Cache key v2 — bumped after `type` was added to the SELECT list so
        // existing cached payloads (missing the column) get invalidated.
        $products = CacheService::remember('public_products_for_start_v2', function () {
            // Always-present columns
            $cols = ['id', 'name'];
            // Optional columns — only project what exists in this deployment's
            // schema. The legacy graphite product table has all of these;
            // V2 deployments may differ.
            // `type` drives product-specific UI on /start ("Cellphone",
            // "Legal", "Tyre" — see InstantConfirm + StartPolicy).
            foreach (['slug', 'type', 'premium_type_id', 'image', 'has_activation_code',
                      'has_member', 'has_vehicle', 'description'] as $opt) {
                if (Schema::hasColumn('products', $opt)) $cols[] = $opt;
            }
            $query = Product::select($cols);
            if (Schema::hasColumn('products', 'status'))     $query->where('status', 1);
            if (Schema::hasColumn('products', 'isForStart')) $query->where('isForStart', 1);
            return $query->orderBy('name')->get();
        }, CacheService::CACHE_TTL_LONG, [CacheService::TAG_LOOKUPS]);

        return response()->json(['products' => $products]);
    }

    /**
     * Countries — never change. Long TTL.
     * Returned: { countries: [{ id, name, code? }, ...] }
     */
    public function countries(): JsonResponse
    {
        $countries = CacheService::remember('public_countries', function () {
            $cols = ['id', 'name'];
            if (Schema::hasColumn('country', 'code')) $cols[] = 'code';
            return Country::select($cols)->orderBy('name')->get();
        }, CacheService::CACHE_TTL_VERY_LONG, [CacheService::TAG_LOOKUPS]);

        return response()->json(['countries' => $countries]);
    }

    /**
     * States in a country — only loaded when the customer picks a country.
     * Returned: { states: [{ id, name, country_id }, ...] }
     */
    public function statesByCountry(int $countryId): JsonResponse
    {
        $states = CacheService::remember("public_states_country_{$countryId}", function () use ($countryId) {
            return State::where('country_id', $countryId)
                ->select('id', 'name', 'country_id')
                ->orderBy('name')
                ->get();
        }, CacheService::CACHE_TTL_VERY_LONG, [CacheService::TAG_LOOKUPS]);

        return response()->json(['states' => $states]);
    }

    /**
     * Public bundle-discount settings — used by the Bundled Products
     * tile. Returns the discount rate by product count so the FE can
     * show a running "save P{x}" total as the cart fills.
     *
     * Falls back to a sensible BW default if the Config row is missing
     * (5/10/15/20 % at 2/3/4/5+ products) so the customer site can
     * still quote.
     */
    /**
     * Resolve MATI config for a product: [bool $enabled, string $flowId].
     * Mirrors FrontendPay/CustomerController flow-id resolution (~:3060-3067):
     * the per-product flow id comes from its KycCompliance row, with a live
     * default fallback; `enable_mati` is the global Config toggle. Read live
     * (outside the lookup caches) so admin toggles take effect immediately.
     */
    private function matiConfig(?int $productId): array
    {
        $enabled = false;
        try {
            $cfg = Config::where('key', 'enable_mati')->first(['value']);
            $enabled = $cfg && (int) $cfg->value === 1;
        } catch (\Throwable $e) {
            $enabled = false;
        }

        $flowId = '616ac99406694f001be574c7'; // AdvanceKYC LIVE (default)
        try {
            $product = $productId ? Product::find($productId) : null;
            if ($product && $product->kyc_compliance) {
                $compliance = KycCompliance::where('id', $product->kyc_compliance)->first(['flow_id']);
                if ($compliance && $compliance->flow_id) {
                    $flowId = $compliance->flow_id;
                }
            }
        } catch (\Throwable $e) {
            // keep default flow id
        }

        return [$enabled, $flowId];
    }

    public function publicBundleSettings(): JsonResponse
    {
        $defaults = [
            'two_products'             => 5,
            'three_products'           => 10,
            'four_products'            => 15,
            'more_than_four_products'  => 20,
            'motor_comprehensive_included' => true,
        ];

        $rates = CacheService::remember('public_bundle_settings', function () use ($defaults) {
            try {
                $row = \AlphaDirect\Config::where('key', 'bundled_products_settings')->first();
                if (!$row) return $defaults;
                $bundle = (json_decode($row->value, true)[0] ?? null);
                if (!$bundle) return $defaults;
                return [
                    'two_products'             => (float) ($bundle['two_products']   ?? $defaults['two_products']),
                    'three_products'           => (float) ($bundle['three_products'] ?? $defaults['three_products']),
                    'four_products'            => (float) ($bundle['four_products']  ?? $defaults['four_products']),
                    'more_than_four_products'  => (float) ($bundle['more_than_four'] ?? $defaults['more_than_four_products']),
                    'motor_comprehensive_included' => (bool) ($bundle['motor_comprehensive'] ?? true),
                ];
            } catch (\Throwable $e) {
                return $defaults;
            }
        }, CacheService::CACHE_TTL_LONG, [CacheService::TAG_LOOKUPS]);

        // MATI gate config for the bundle create flow. Bundles span multiple
        // products, so the flow id uses the default live KYC flow (matching
        // the legacy grouped/instant behaviour). Computed live (uncached).
        [$matiEnabled, $matiFlowId] = $this->matiConfig(null);
        $rates = array_merge($rates, [
            'enable_mati'  => $matiEnabled ? 1 : 0,
            'mati_flow_id' => $matiFlowId,
        ]);

        return response()->json(['data' => $rates]);
    }

    /**
     * Cities in a state.
     */
    public function citiesByState(int $stateId): JsonResponse
    {
        $cities = CacheService::remember("cities_state_{$stateId}", function () use ($stateId) {
            return City::where('state_id', $stateId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }, CacheService::CACHE_TTL_VERY_LONG, [CacheService::TAG_LOOKUPS]);

        return response()->json(['data' => $cities]);
    }

    /**
     * Motor types for a product. Mirrors legacy AddVehicle::mount() which
     * loaded MotorType::where('product_id', ...) for the vehicle form
     * "Please choose the motor type" dropdown.
     */
    public function motorTypesByProduct(int $productId): JsonResponse
    {
        $types = CacheService::remember("motor_types_product_{$productId}", function () use ($productId) {
            return DB::table('motor_type')
                ->where('product_id', $productId)
                ->select('id', 'motor_name as name')
                ->orderBy('motor_name')
                ->get();
        }, CacheService::CACHE_TTL_VERY_LONG, [CacheService::TAG_LOOKUPS]);

        return response()->json(['data' => $types]);
    }

    /**
     * Plans for a product.
     *
     * The product_plans.premium column stores the ex-VAT amount (e.g.
     * 42.98 for the P49 cellphone plan). Customers expect to see the
     * all-in price the legacy site has always shown (P49 / P79 / P99),
     * so we add a `premium_inc_vat` field with Botswana 14% VAT applied
     * and round-to-cents. Keeping the raw `premium` field too so any
     * downstream consumer that does its own VAT math (invoicing,
     * accounting) doesn't double-charge.
     */
    public function plansByProduct(int $productId): JsonResponse
    {
        // Bumped cache key suffix so existing entries (which were
        // cached without premium_inc_vat) get re-built on first hit.
        $plans = CacheService::remember("plans_product_v2_{$productId}", function () use ($productId) {
            $vatPct = (float) env('BW_VAT_PERCENT', 14);
            $factor = 1 + ($vatPct / 100);

            return Productplan::where('product_id', $productId)
                ->where('status', 1)
                ->select('id', 'product_id', 'name', 'sum_assured', 'premium', 'billing')
                ->orderBy('premium')
                ->get()
                ->map(function ($p) use ($factor, $vatPct) {
                    $base   = (float) ($p->premium ?? 0);
                    $incVat = round($base * $factor, 2);
                    return [
                        'id'              => $p->id,
                        'product_id'      => $p->product_id,
                        'name'            => $p->name,
                        'sum_assured'     => $p->sum_assured,
                        'billing'         => $p->billing,
                        // Customer-facing all-in price (the P49 / P79 / P99 number).
                        'premium'         => $incVat,
                        'premium_inc_vat' => $incVat,
                        // Ex-VAT base, for invoicing / accounting consumers.
                        'premium_ex_vat'  => $base,
                        'vat_percent'     => $vatPct,
                    ];
                });
        }, CacheService::CACHE_TTL_LONG, [CacheService::TAG_LOOKUPS]);

        // Attach per-product MATI gate config to each plan row (the FE reads
        // plan.enable_mati / plan.mati_flow_id). Computed live (uncached) so an
        // admin enable_mati toggle reflects without busting the plan cache.
        [$matiEnabled, $matiFlowId] = $this->matiConfig($productId);
        $plans = collect($plans)->map(function ($p) use ($matiEnabled, $matiFlowId) {
            $row = (array) $p;
            $row['enable_mati']  = $matiEnabled ? 1 : 0;
            $row['mati_flow_id'] = $matiFlowId;
            return $row;
        })->values();

        return response()->json(['data' => $plans, 'plans' => $plans]);
    }

    /**
     * Companies — supports search-as-you-type.
     */
    public function companies(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $companies = Company::select('id', 'name')
            ->where('status', 1)
            ->where('parent_id', null)
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json(['data' => $companies]);
    }

    /**
     * Create a new company (from policy wizard).
     */
    public function createCompany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                        => 'required|string|max:255',
            'VAT_registration_number'     => 'required|string|max:50',
            'company_registration_number' => 'required|string|max:50',
            'head_office_physical_address'=> 'required|string|max:500',
            'postal_address'              => 'required|string|max:500',
            'city'                        => 'required|string|max:100',
            'state'                       => 'required|string|max:100',
            'pincode'                     => 'nullable|string|max:20',
            'contact_person_first_name'   => 'required|string|max:100',
            'contact_person_last_name'    => 'required|string|max:100',
            'contact_person_number'       => 'required|string|max:20',
            'primary_email'               => 'required|email|max:255',
            'secondary_email'             => 'nullable|email|max:255',
            'broker_email'                => 'nullable|email|max:255',
        ]);

        $payload = [
            'name'                        => $validated['name'],
            'VAT_registration_number'     => $validated['VAT_registration_number'],
            'company_registration_number' => $validated['company_registration_number'],
            'head_office_physical_address'=> $validated['head_office_physical_address'],
            'postal_address'              => $validated['postal_address'],
            'city'                        => $validated['city'],
            'state'                       => $validated['state'],
            'pincode'                     => $validated['pincode'] ?? '',
            'contact_person_number'       => $validated['contact_person_number'],
            'primary_email'               => $validated['primary_email'],
            'secondary_email'             => $validated['secondary_email'] ?? '',
            'broker_email'                => $validated['broker_email'] ?? '',
            'status'                      => 1,
        ];
        // Legacy schemas have a NOT-NULL `address` column — mirror
        // head_office_physical_address into it only when the column exists,
        // so current schemas (companies w/o address) don't get a bad insert.
        if (\Schema::hasColumn((new Company)->getTable(), 'address')) {
            $payload['address'] = $validated['head_office_physical_address'];
        }
        $company = Company::create($payload);

        // Create customer linked to company
        $customer = \AlphaDirect\Customer::create([
            'company_id' => $company->id,
            'firstName'  => $validated['contact_person_first_name'],
            'lastName'   => $validated['contact_person_last_name'],
            'email'      => $validated['primary_email'],
            'cellphone'  => $validated['contact_person_number'],
        ]);

        \AlphaDirect\CustomerProfile::create([
            'customer_id' => $customer->id,
            'entity_type' => 'Organisation',
            'company_id'  => $company->id,
        ]);

        // Clear lookup caches (policy wizard uses company lists)
        Cache::forget('login_lookups_v2');
        CacheService::forgetLookups('policy_create_data');

        return response()->json([
            'data'    => ['id' => $company->id, 'name' => $company->name],
            'message' => 'Company created successfully.',
        ], 201);
    }

    /**
     * Company details for display/confirmation.
     */
    public function companyDetails(int $id): JsonResponse
    {
        $company = Company::find($id);
        if (!$company) return response()->json(['error' => 'Not found'], 404);

        return response()->json(['data' => [
            'id' => $company->id,
            'name' => $company->name,
            'VAT_registration_number' => $company->VAT_registration_number,
            'company_registration_number' => $company->company_registration_number,
            'head_office_physical_address' => $company->head_office_physical_address ?? $company->address,
            'postal_address' => $company->postal_address,
            'city' => $company->city,
            'state' => $company->state,
            'primary_email' => $company->primary_email,
            'contact_person_number' => $company->contact_person_number,
            'status' => $company->status,
        ]]);
    }

    /**
     * Update company details.
     */
    public function updateCompany(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);

        // Partial update — the policy wizard's Company Details card sends only
        // the handful of fields it shows, so every rule is `sometimes`. They
        // mirror createCompany() so an edit cannot write something the create
        // form would have rejected (and so a too-long value comes back as a
        // clean 422 instead of a DB error).
        $validated = $request->validate([
            'name'                         => 'sometimes|required|string|max:255',
            'VAT_registration_number'      => 'sometimes|nullable|string|max:50',
            'company_registration_number'  => 'sometimes|nullable|string|max:50',
            'head_office_physical_address' => 'sometimes|nullable|string|max:500',
            'postal_address'               => 'sometimes|nullable|string|max:500',
            'city'                         => 'sometimes|nullable|string|max:100',
            'state'                        => 'sometimes|nullable|string|max:100',
            'primary_email'                => 'sometimes|nullable|email|max:255',
            'contact_person_number'        => 'sometimes|nullable|string|max:20',
            'status'                       => 'sometimes|nullable|in:0,1',
        ]);

        $fields = array_filter($validated, fn($v) => $v !== null);

        // Legacy schemas keep a NOT-NULL `address` column, and companyDetails()
        // falls back to it when head_office_physical_address is empty. Mirror
        // the edit into it or the card can keep showing the stale address.
        if (array_key_exists('head_office_physical_address', $fields)
            && \Schema::hasColumn($company->getTable(), 'address')) {
            $fields['address'] = $fields['head_office_physical_address'];
        }

        $company->update($fields);
        Cache::forget('login_lookups_v2');
        CacheService::forgetLookups('policy_create_data');
        return response()->json(['message' => 'Company updated.', 'data' => $company->fresh()]);
    }

    /**
     * Sub-companies for a company.
     */
    public function subCompanies(int $companyId): JsonResponse
    {
        $company = Company::find($companyId);
        if (!$company) {
            return response()->json(['data' => []]);
        }

        $subCompanies = $company->subCompanies()
            ->where('status', 1)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $subCompanies]);
    }

    /**
     * Vehicle makes.
     */
    public function vehicleMakes(): JsonResponse
    {
        $makes = CacheService::remember('vehicle_makes', function () {
            return VehicleMake::select('id', 'name')->orderBy('name')->get();
        }, CacheService::CACHE_TTL_VERY_LONG, [CacheService::TAG_LOOKUPS]);
        return response()->json(['data' => $makes]);
    }

    /**
     * Vehicle models for a make.
     */
    public function vehicleModels(int $makeId): JsonResponse
    {
        $models = CacheService::remember("vehicle_models_make_{$makeId}", function () use ($makeId) {
            return \AlphaDirect\VehiclePopularity::where('make_id', $makeId)
                ->select('id', 'model_name as name')
                ->orderBy('model_name')
                ->get();
        }, CacheService::CACHE_TTL_VERY_LONG, [CacheService::TAG_LOOKUPS]);
        return response()->json(['data' => $models]);
    }

    /**
     * Device brands.
     */
    public function deviceBrands(): JsonResponse
    {
        $brands = CacheService::remember('device_brands', function () {
            return DeviceMakeModel::select('brand')->distinct()->orderBy('brand')->get()
                ->map(fn($d) => ['id' => $d->brand, 'name' => $d->brand]);
        }, CacheService::CACHE_TTL_VERY_LONG, [CacheService::TAG_LOOKUPS]);
        return response()->json(['data' => $brands]);
    }

    /**
     * Device models for a brand.
     */
    public function deviceModels(string $brand): JsonResponse
    {
        $models = CacheService::remember('device_models_' . md5($brand), function () use ($brand) {
            return DeviceMakeModel::where('brand', $brand)
                ->select('id', 'model as name', 'price')
                ->orderBy('model')
                ->get();
        }, CacheService::CACHE_TTL_VERY_LONG, [CacheService::TAG_LOOKUPS]);
        return response()->json(['data' => $models]);
    }

    /**
     * Bank branches.
     */
    public function bankBranches(int $bankId): JsonResponse
    {
        $branches = CacheService::remember("bank_branches_{$bankId}", function () use ($bankId) {
            return \AlphaDirect\BankBranches::where('bank_id', $bankId)
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get();
        }, CacheService::CACHE_TTL_VERY_LONG, [CacheService::TAG_LOOKUPS]);
        return response()->json(['data' => $branches]);
    }

    /**
     * Product details (has_vehicle, has_member, etc.).
     */
    public function productDetails(int $productId): JsonResponse
    {
        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['data' => null], 404);
        }
        return response()->json(['data' => [
            'id' => $product->id,
            'name' => $product->name,
            'has_vehicle' => (bool) $product->has_vehicle,
            'has_member' => (bool) $product->has_member,
            'has_device' => (bool) $product->has_device,
            'has_risk_address' => (bool) optional($product->coverageMaster()->where('has_risk_address', 1)->first())->has_risk_address,
            'kyc_customer' => (bool) $product->kyc_customer,
            'is_motor_items' => (bool) $product->is_motor_items,
        ]]);
    }

    /**
     * Coverage masters for a product (used in Step 3 of wizard).
     */
    public function coveragesByProduct(int $productId): JsonResponse
    {
        $coverages = CacheService::remember("coverages_product_{$productId}", function () use ($productId) {
            $product = Product::findOrFail($productId);
            return $product->coverageMaster()
                ->select('tb_cvgpccoverages.id', 'tb_cvgpccoverages.s_CoverageName', 'tb_cvgpccoverages.s_CoverageCode',
                    'tb_cvgpccoverages.s_CoverageGroupCode', 'tb_cvgpccoverages.s_UsageType',
                    'tb_cvgpccoverages.has_risk_address', 'tb_cvgpccoverages.n_DisplaySequence')
                // DOMG (product 8): the coverage-type picker (Add/Edit coverage)
                // must offer exactly what the V2 quote sheet's Index of Sections
                // renders — the same PARENT + s_DISPLAYTOUSER filter and the same
                // commercial-only exclusion list used in
                // PolicyCreateController ($domgExcludedCoverageIds) and
                // GenerateQuotationPdfJob. Without this the picker offered
                // coverages (commercial sections + House Holders id 17) that the
                // sheet then dropped, so a coverage could be added and charged but
                // never appear on the quote / policy schedule. Keep this list in
                // sync with those two files.
                ->when($productId === 8, function ($q) {
                    $q->where('tb_cvgpccoverages.s_UsageType', 'PARENT')
                      ->where('tb_cvgpccoverages.s_DISPLAYTOUSER', '1')
                      ->whereNotIn('tb_cvgpccoverages.id', [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 16, 17]);
                })
                ->orderBy('tb_cvgpccoverages.n_DisplaySequence')
                ->get();
        }, CacheService::CACHE_TTL_LONG, [CacheService::TAG_LOOKUPS]);

        return response()->json(['data' => $coverages]);
    }

    /**
     * Accepted spellings for a coverage code when matching parent-code columns.
     *
     * STRICTLY Business Interruption only: it is stored inconsistently across
     * tables as BUSINESSINTERUPTION (one R) in some and BUSINESSINTERRUPTION
     * (two R's) in others, which breaks exact-match parent-code lookups. For a
     * BI code this returns BOTH spellings so the lookup matches whichever
     * variant a row was stored with, in either direction. EVERY other coverage
     * code returns only itself — no other coverage's behaviour changes.
     */
    private static function biCoverageCodeVariants(?string $code): array
    {
        $bi = ['BUSINESSINTERUPTION', 'BUSINESSINTERRUPTION'];
        return in_array((string) $code, $bi, true) ? $bi : [(string) $code];
    }

    /**
     * GET /lookups/coverages/{id}/subcoverages
     *
     * Return child coverages (subcoverages) for a given parent coverage.
     * Mirrors the old Graphite ManageCoverages subCoverage relationship.
     */
    public function subcoverages(int $coverageId): JsonResponse
    {
        $parent = CoverageMaster::findOrFail($coverageId);

        // Debug: check if records exist without strict filters
        $allRecords = DB::table('tb_cvgpccoverages')
            ->where('s_ParentCoverageCode', $parent->s_CoverageCode)
            ->count();

        // Return distinct subcoverages (one per s_ScreenName group) — excludes policy-specific clones
        // Handle spelling variations (e.g., BUSINESSINTERRUPTION vs BUSINESSINTERUPTION)
        $subcoverages = DB::table('tb_cvgpccoverages')
            ->select(
                DB::raw('MIN(id) as id'),
                's_CoverageName', 's_CoverageCode', 's_ScreenName',
                's_CoverageGroupName', 's_SubCoverageMainName', 's_ParentCoverageCode',
                'rate'
            )
            // BI is stored as one-R or two-R across tables — match either
            // spelling (BI only; all other codes match just themselves).
            ->whereIn('s_ParentCoverageCode', self::biCoverageCodeVariants($parent->s_CoverageCode))
            ->where('s_DISPLAYTOUSER', '1')
            ->where('s_UsageType', 'CHILD')
            ->whereNull('policy_id')
            ->groupBy('s_ScreenName', 's_CoverageGroupName', 's_SubCoverageMainName',
                's_CoverageName', 's_CoverageCode', 's_ParentCoverageCode', 'rate')
            ->orderBy(DB::raw('MIN(n_DisplaySequence)'), 'asc')
            ->get();

        // If no results, return debug info
        if (count($subcoverages) === 0) {
            // Find some sample parent codes that DO exist
            $sampleParents = DB::table('tb_cvgpccoverages')
                ->select('s_ParentCoverageCode')
                ->distinct()
                ->limit(10)
                ->pluck('s_ParentCoverageCode')
                ->toArray();

            return response()->json([
                'data' => [],
                'debug' => [
                    'parentCoverageCode' => $parent->s_CoverageCode,
                    'totalRecordsWithParent' => $allRecords,
                    'message' => 'No subcoverages found with current filters',
                    'sampleParentCodesInDb' => $sampleParents
                ]
            ]);
        }

        // Fetch s_LimitTypeCode and options for each subcoverage (post-process)
        // This avoids complex joins that cause groupBy issues
        foreach ($subcoverages as $sub) {
            // Get the limit type code
            $limitTypeCode = DB::table('tb_validoptions')
                ->join('tb_cvgpclimits', 'tb_validoptions.n_SourceTwoFK', '=', 'tb_cvgpclimits.n_PCLimitId_PK')
                ->where('tb_validoptions.n_SourceOneFK', $sub->id)
                ->where('tb_validoptions.s_OptionType', 'CVG_LIMIT')
                ->value('tb_cvgpclimits.s_LimitTypeCode');

            $sub->s_LimitTypeCode = $limitTypeCode ?? null;

            // If DROPDOWN type, fetch available options
            if ($limitTypeCode === 'DROPDOWN') {
                $options = DB::table('tb_validoptions')
                    ->join('tb_cvgpclimits', 'tb_validoptions.n_SourceTwoFK', '=', 'tb_cvgpclimits.n_PCLimitId_PK')
                    ->where('tb_validoptions.n_SourceOneFK', $sub->id)
                    ->where('tb_validoptions.s_OptionType', 'CVG_LIMIT')
                    ->select('tb_cvgpclimits.n_PCLimitId_PK as id', 'tb_cvgpclimits.s_LimitScreenName as name')
                    ->get()
                    ->toArray();

                $sub->dropdown_options = $options;
            }

            // If RADIO type, fetch available radio options
            if ($limitTypeCode === 'RADIO') {
                $radioOptions = DB::table('tb_validoptions')
                    ->join('tb_cvgpclimits', 'tb_validoptions.n_SourceTwoFK', '=', 'tb_cvgpclimits.n_PCLimitId_PK')
                    ->where('tb_validoptions.n_SourceOneFK', $sub->id)
                    ->where('tb_validoptions.s_OptionType', 'CVG_LIMIT')
                    ->select('tb_cvgpclimits.n_PCLimitId_PK as id', 'tb_cvgpclimits.s_LimitScreenName as name')
                    ->get()
                    ->toArray();

                $sub->radio_options = $radioOptions;
            }
        }

        return response()->json(['data' => $subcoverages]);
    }

    /**
     * GET /lookups/coverages/{id}/extensions
     *
     * Return extensions (and perils) available for a given coverage.
     * Each extension has an extention_type (NUMBER, DROPDOWN, RADIO, NOEDIT)
     * that determines the input type in the UI.
     */
    public function extensions(int $coverageId): JsonResponse
    {
        $parent = CoverageMaster::findOrFail($coverageId);

        $extensions = DB::table('extentions')
            // BI is stored as one-R or two-R across tables — match either
            // spelling (BI only; all other codes match just themselves).
            ->whereIn('s_ParentCoverageCode', self::biCoverageCodeVariants($parent->s_CoverageCode))
            ->where('s_DISPLAYTOUSER', '1')
            ->where('type', '!=', 'Excess')  // Exclude excess items (they are handled separately)
            ->orderBy('type') // Extention first, then Perils
            ->orderBy('n_DisplaySequence')
            ->get([
                'id', 's_ScreenName', 's_CoverageCode', 's_CoverageName',
                's_ExtensionsGroupName', 'type', 'extention_type', 'rate',
                's_ParentCoverageCode',
            ]);

        // Batch-load all limit options for DROPDOWN and RADIO extensions in one query
        // For RADIO type, use 'RADIOBOXYES' and 'RADIOBOXNO' limit codes (Yes/No options)
        // For DROPDOWN type, use the extension's s_CoverageCode as the limit code
        $limitCodes = collect();
        foreach ($extensions as $e) {
            if (strtoupper($e->extention_type) === 'RADIO') {
                $limitCodes->push('RADIOBOXYES');
                $limitCodes->push('RADIOBOXNO');
            } else {
                $limitCodes->push($e->s_CoverageCode);
            }
        }
        $limitCodes = $limitCodes->unique()->filter()->toArray();

        $allLimits = !empty($limitCodes)
            ? DB::table('tb_cvgpclimits')
                ->whereIn('s_LimitCode', $limitCodes)
                ->orderBy('n_LimitDisplaySeq')
                ->get(['s_LimitCode', 'n_PCLimitId_PK as id', 's_LimitScreenName as name', 's_LimitTypeCode as type'])
                ->groupBy('s_LimitCode')
            : collect();

        $parentCoverageCode = $parent->s_CoverageCode;
        $withLimits = $extensions->map(function ($ext) use ($allLimits, $parentCoverageCode) {
            $extentionTypeUpper = strtoupper($ext->extention_type);

            if ($extentionTypeUpper === 'RADIO') {
                // For RADIO type, merge both RADIOBOXYES and RADIOBOXNO
                $yesLimits = $allLimits->get('RADIOBOXYES', collect())
                    ->map(fn($l) => ['id' => $l->id, 'name' => $l->name, 'type' => $l->type])
                    ->values()->toArray();
                $noLimits = $allLimits->get('RADIOBOXNO', collect())
                    ->map(fn($l) => ['id' => $l->id, 'name' => $l->name, 'type' => $l->type])
                    ->values()->toArray();
                $ext->limits = array_merge($yesLimits, $noLimits);
            } elseif ($extentionTypeUpper === 'DROPDOWN') {
                // For DROPDOWN type, use the extension's s_CoverageCode
                $ext->limits = $allLimits->get($ext->s_CoverageCode, collect())
                    ->map(fn($l) => ['id' => $l->id, 'name' => $l->name, 'type' => $l->type])
                    ->values()->toArray();
            } else {
                $ext->limits = [];
            }

            // Set extention_limit_type: from limits if DROPDOWN/RADIO, else from extention_type
            if (count($ext->limits) > 0) {
                $ext->extention_limit_type = $ext->limits[0]['type'];
            } else {
                $ext->extention_limit_type = $extentionTypeUpper;
            }
            // Add predefined extension value if available
            $ext->predefined_value = $this->getPredefinedExtensionValue($ext->s_ScreenName, $parentCoverageCode);
            \Log::info('Extension data', [
                'coverage_code' => $ext->s_CoverageCode,
                'extention_type' => $ext->extention_type,
                'limits_count' => count($ext->limits),
                'extention_limit_type' => $ext->extention_limit_type,
                'limits' => $ext->limits,
                'predefined_value' => $ext->predefined_value
            ]);
            return $ext;
        });

        return response()->json(['data' => $withLimits]);
    }

    /**
     * GET /lookups/extensions/{extensionCode}/limits
     *
     * Fetch limit options for a specific extension (RADIO or DROPDOWN type).
     * Used when extension limits are not available from the master extensions API.
     */
    public function extensionLimits(string $extensionCode): JsonResponse
    {
        $limits = DB::table('tb_cvgpclimits')
            ->where('s_LimitCode', strtoupper($extensionCode))
            ->orderBy('n_LimitDisplaySeq')
            ->get(['n_PCLimitId_PK as id', 's_LimitScreenName as name', 's_LimitTypeCode as type'])
            ->toArray();

        return response()->json(['data' => $limits]);
    }

    /**
     * Get predefined extension coverage value based on extension name.
     * Maps extension names to their default values from the legacy system.
     */
    private function getPredefinedExtensionValue(string $extensionName, string $parentCoverageCode = ''): ?string
    {
        $name = strtolower(trim($extensionName));
        $code = strtoupper(trim($parentCoverageCode));

        $predefinedValues = [
            'water leakage' => 5000,
            'television equipment maintenance' => 5000,
            'loss of money' => ['default' => 3000, 'exclude' => ['PERSONALALLRISKS']],
            'refrigerator or deep freeze contents' => 5000,
            'veterinary fees' => 2000,
            'goods in the open' => 5000,
            'locks and keys' => 5000,
            'golfers hole-in-one' => ['default' => 2000, 'exclude' => ['PERSONALALLRISKS']],
            'property of domestic employees' => 5000,
            'personal effects of guests' => 5000,
            'medical expenses' => 5000,
            'death by accident' => 10000,
            'fatal injury - death by accident' => 10000,
            'death by thieves or fire' => 15000,
            'fatal injury - death by thieves or fire' => 15000,
            'temporary repairs and other measures' => 5000,
            'repairs and measures after a loss - temporary repairs and other measures' => 5000,
            'emergency accommodation' => 5000,
            'repairs and measures after a loss - emergency accommodation' => 5000,
        ];

        if (isset($predefinedValues[$name])) {
            $value = $predefinedValues[$name];

            if (is_array($value)) {
                if (in_array($code, $value['exclude'] ?? [], true)) {
                    return null;
                }
                return (string) $value['default'];
            }

            return (string) $value;
        }

        return null;
    }

    /**
     * GET /lookups/coverages/{id}/specified-items
     *
     * Returns master "Miscellaneous Items" configured against a coverage.
     * Mirrors graphiteBWV8 SpecifiedCoveragesItems::EffectiveItemOnly scope:
     *   effective_from <= today AND effective_to >= today
     * Rows with NULL dates (never-expiring) are treated as always-effective.
     *
     * Prior implementation had an orWhere precedence bug that could leak
     * rows from other coverages.
     */
    public function specifiedItems(int $coverageId, \Illuminate\Http\Request $request): JsonResponse
    {
        // Scoping model: Only show specified items attached to the parent coverage
        // being edited (coverage_id matches the requested coverage).
        // This excludes items attached to child subcoverages.
        //
        // ?effective=1 keeps the legacy EffectiveItemOnly filter for new
        // business; edit mode calls without it so past-effective items stay
        // visible and operators can re-pick them.
        $effectiveOnly = $request->boolean('effective', false);
        $today = now()->toDateString();

        // Only show items for the parent coverage being edited (not child subcoverages)
        $allowedCovIds = [$coverageId];

        $query = DB::table('specified_coverage_items')
            ->whereIn('coverage_id', $allowedCovIds);

        if (\Schema::hasColumn('specified_coverage_items', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        // Always filter out expired items (effective_to < today) regardless of mode.
        // In add/edit coverage, operators should only see currently valid items.
        if (\Schema::hasColumn('specified_coverage_items', 'effective_to')) {
            $query->where(function ($q) use ($today) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
            });
        }

        // ?effective=1 for new business also requires effective_from check
        if ($effectiveOnly && \Schema::hasColumn('specified_coverage_items', 'effective_from')) {
            $query->where(function ($q) use ($today) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $today);
            });
        }

        $cols = ['id', 'specified_name as name', 'rate', 'coverage_id'];
        if (\Schema::hasColumn('specified_coverage_items', 'sub_coverage_id')) {
            $cols[] = 'sub_coverage_id';
        }

        $items = $query->orderBy('specified_name')->get($cols);

        return response()->json(['data' => $items]);
    }

    /**
     * GET /lookups/transaction-types?current_type=NEWBUSINESS&policy_id=123
     *
     * Return the list of allowed next transaction types given the current action's
     * transaction_type.  Mirrors the getTransactionTypes() logic from old Graphite's
     * AddTransaction Livewire component.
     *
     * Additionally, when policy_id is supplied, the result is filtered to hide
     * options that the server will reject anyway because a QUOTE-status action
     * is already in flight (operator rules, 2026-05-25):
     *   - CANCEL in QUOTE  → hides REINSTATE / REISSUE (no policy to reinstate
     *                        yet — cancel is unfinished).
     *   - ENDORSE in QUOTE → hides ENDORSE / ENDORSE-RENEW / EXTENSION-COVER,
     *                        but KEEPS CANCEL (operator must be able to cancel
     *                        the policy even mid-endorse).
     *   - REINSTATE / REISSUE in QUOTE → hides everything endorsement-class
     *                        until that QUOTE is finished or deleted.
     */
    public function transactionTypes(\Illuminate\Http\Request $request): JsonResponse
    {
        $currentType = strtoupper(trim($request->query('current_type', 'NEWBUSINESS')));
        $policyId    = (int) $request->query('policy_id', 0);

        // Allowed next-step transitions — copied from old Graphite AddTransaction::getTransactionTypes()
        $rules = [
            'NEWBUSINESS'       => ['CANCEL', 'ENDORSE', 'EXPIRE', 'ANNIVERSARY-RENEW'],
            'ANNIVERSARY-RENEW' => ['CANCEL', 'ENDORSE', 'ANNIVERSARY-RENEW'],
            'REISSUE'           => ['CANCEL', 'ENDORSE', 'EXPIRE'],
            'REINSTATE'         => ['CANCEL', 'ENDORSE', 'EXPIRE'],
            'CANCEL'            => ['REISSUE', 'REINSTATE'],
            'ENDORSE'           => ['ENDORSE', 'CANCEL', 'ANNIVERSARY-RENEW'],
            'ENDORSE-RENEW'     => ['CANCEL', 'ENDORSE'],
            'EXPIRE'            => ['REISSUE', 'REINSTATE'],
            'RENEW'             => ['CANCEL', 'ENDORSE', 'ANNIVERSARY-RENEW'],
            'EXTENSION-COVER'   => ['CANCEL', 'ENDORSE'],
        ];

        // Mirror V1 AddTransaction::getTransactionTypes (graphiteBWV8):
        // resolve the SELECTED action's status (by action_id), and when it
        // is LAPSED or NTU, route through the CANCEL rule so the dropdown
        // shows REINSTATE + REISSUE only. Fall back to latest action when
        // no action_id is passed (backward compat for older callers).
        $selectedActionId = (int) $request->query('action_id', 0);
        $action = null;
        if ($selectedActionId > 0) {
            $action = \AlphaDirect\Models\PolicyAction::where('id', $selectedActionId)
                ->whereNull('deleted_at')->first();
        }
        if (!$action && $policyId > 0) {
            $action = \AlphaDirect\Models\PolicyAction::where('policy_id', $policyId)
                ->whereNull('deleted_at')->orderByDesc('id')->first();
        }
        if ($action && in_array($action->status, ['LAPSED', 'NTU'], true)) {
            $currentType = 'CANCEL';
        }

        $allowed = $rules[$currentType] ?? ['ENDORSE', 'CANCEL'];

        // Policy-level in-flight QUOTE filter — mirrors the server-side guard
        // in PolicyCreateController::store (around line 8379) so the UI never
        // offers an option the API will reject. Silent no-op when policy_id
        // is missing (kept for backward compatibility with older callers).
        if ($policyId > 0) {
            $endorseClass   = ['ENDORSE', 'ENDORSE-RENEW', 'EXTENSION-COVER'];
            $cancelClass    = ['CANCEL'];
            $reinstateClass = ['REINSTATE', 'REISSUE'];
            $endorsementTypes = array_merge($endorseClass, $cancelClass, $reinstateClass);

            $inFlight = \AlphaDirect\Models\PolicyAction::where('policy_id', $policyId)
                ->where('status', 'QUOTE')
                ->whereIn('transaction_type', $endorsementTypes)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first();

            if ($inFlight) {
                $existingType = $inFlight->transaction_type;
                $isExistingEndorse   = in_array($existingType, $endorseClass, true);
                $isExistingCancel    = in_array($existingType, $cancelClass, true);
                $isExistingReinstate = in_array($existingType, $reinstateClass, true);

                $allowed = array_values(array_filter($allowed, function ($t) use (
                    $isExistingEndorse, $isExistingCancel, $isExistingReinstate,
                    $endorseClass, $cancelClass, $reinstateClass
                ) {
                    // ENDORSE in QUOTE → drop endorse-class; keep CANCEL.
                    if ($isExistingEndorse) {
                        return !in_array($t, $endorseClass, true);
                    }
                    // CANCEL in QUOTE → drop everything endorsement-class.
                    if ($isExistingCancel) {
                        return !in_array($t, array_merge(
                            $endorseClass, $cancelClass, $reinstateClass
                        ), true);
                    }
                    // REINSTATE / REISSUE in QUOTE → same: drop everything.
                    if ($isExistingReinstate) {
                        return !in_array($t, array_merge(
                            $endorseClass, $cancelClass, $reinstateClass
                        ), true);
                    }
                    return true;
                }));
            }
        }

        // Try to get screen names from tb_prtrantypes; fall back to code if table missing
        try {
            $rows = \DB::table('tb_prtrantypes')
                ->whereIn('TranTypeCode', $allowed)
                ->get(['TranTypeCode', 'TranTypeScreenName']);

            $mapped = collect($allowed)->map(function ($code) use ($rows) {
                $row = $rows->firstWhere('TranTypeCode', $code);
                return ['id' => $code, 'name' => $row?->TranTypeScreenName ?? $code];
            })->values();
        } catch (\Exception $e) {
            $mapped = collect($allowed)->map(fn ($c) => ['id' => $c, 'name' => $c])->values();
        }

        return response()->json(['data' => $mapped]);
    }

    /**
     * GET /lookups/transaction-subtypes/{type}
     *
     * Return transaction reasons for the selected transaction type.
     * Reads from tb_prtransubtypes.
     *
     * ENDORSE: the dropdown is restricted to the 8 reasons UW currently uses
     * (Add Coverage / Change Limit / Cancel Coverage / Cancel Sub Coverage /
     * Change Location / Alter Term / Add Spouse / Other NPE). Other DB-seeded
     * subtypes (Add Discount, Add Mortgagee, Change Deductible, etc.) are
     * filtered out at the API layer so the Add Transaction + Edit Transaction
     * modals both see the same trimmed list. To expand the list later, add
     * names to ENDORSE_REASON_WHITELIST below — no FE change needed.
     */
    private const ENDORSE_REASON_WHITELIST = [
        'Add Coverage',
        'Change Limit',
        'Cancel Coverage',
        'Cancel Sub Coverage',
        'Change Location',
        'Alter Term',
        'Add Spouse',
        'Other NPE',
    ];

    public function transactionSubTypes(string $type): JsonResponse
    {
        $type = strtoupper(trim($type));
        try {
            $rows = \DB::table('tb_prtransubtypes')
                ->where('TranTypeCode', $type)
                ->get(['TranSubTypeCode', 'TranSubtypeScreenName']);

            if ($type === 'ENDORSE') {
                // Case-insensitive match on display name; preserve whitelist order.
                $whitelistLc = array_map('strtolower', self::ENDORSE_REASON_WHITELIST);
                $byName = [];
                foreach ($rows as $r) {
                    $key = strtolower(trim((string) $r->TranSubtypeScreenName));
                    $byName[$key] = $r;
                }
                $data = collect();
                foreach ($whitelistLc as $wl) {
                    if (isset($byName[$wl])) {
                        $r = $byName[$wl];
                        $data->push(['id' => $r->TranSubTypeCode, 'name' => $r->TranSubtypeScreenName]);
                    }
                }
            } else {
                $data = $rows->map(fn ($r) => [
                    'id'   => $r->TranSubTypeCode,
                    'name' => $r->TranSubtypeScreenName,
                ])->values();
            }
        } catch (\Exception $e) {
            $data = collect();
        }

        return response()->json(['data' => $data]);
    }
}
