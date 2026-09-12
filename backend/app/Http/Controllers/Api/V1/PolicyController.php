<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Services\Reinsurance\CessionSource;
use AlphaDirect\Customer;
use AlphaDirect\CustomerFeedback;
use AlphaDirect\CustomerMati;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Http\Resources\V1\ClaimResource;
use AlphaDirect\Http\Resources\V1\PolicyCollection;
use AlphaDirect\Http\Resources\V1\PolicyResource;
use AlphaDirect\KYC;
use AlphaDirect\KycCompliance;
use AlphaDirect\Mail\MatiLink;
use AlphaDirect\Product;
use AlphaDirect\Models\Audits;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Policy;
use AlphaDirect\PolicyActivateCancelledDate;
use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class PolicyController extends Controller
{
    /**
     * Max number of policy_subledger rows the ledger() endpoint returns for a
     * single policy. A handful of legacy policies have enormous subledgers
     * (100k+ rows); loading them all blew the PHP memory limit and 500'd the
     * entire ledger response. The Sub Ledger tab shows the most recent rows up
     * to this cap and flags when more exist.
     */
    private const SUB_LEDGER_CAP = 500;

    /**
     * Build a CloudFront CDN URL from a relative S3 path.
     * Does NOT depend on S3 disk being configured — just env vars.
     */
    private function cdnUrl(?string $path): ?string
    {
        if (empty($path)) return null;
        $path = str_replace(['\/', ' '], ['/', '%20'], $path);
        $cdn = env('AWS_CLOUDFRONT');
        if ($cdn) {
            return rtrim($cdn, '/') . '/' . ltrim($path, '/');
        }
        // Fallback: generate S3 URL directly
        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');
        if ($bucket && $region) {
            return "https://{$bucket}.s3.{$region}.amazonaws.com/" . ltrim($path, '/');
        }
        return $path; // Return raw path if nothing configured
    }

    /**
     * Paginated policy list (unchanged).
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status'           => 'nullable|integer|in:0,1,2,3',
            'draft'            => 'nullable|integer|in:0,1',
            'product_id'       => 'nullable|integer',
            'exclude_products' => 'nullable|string|max:50',
            'agent_id'         => 'nullable|integer|exists:users,id',
            'agency_id'        => 'nullable|integer|exists:agencies,id',
            // Matches the legacy Graphite "Filter By Payment Method" dropdown
            // values (DPO / RealPay / VCS / CASH / orangeMoney / N-Genius).
            'payment_method'   => 'nullable|string|max:20',
            'search'           => 'nullable|string|max:100',
            'per_page'         => 'nullable|integer|min:5|max:100',
            // DomCom-only filter: narrows by the latest policy_action status
            // (QUOTE / IN_APPROVAL / APPROVED / ISSUED / REJECTED). Combined
            // with product_id so the listing can distinguish "Issued" from
            // "In Quote" etc. — the four states ops track for DOMG/COMG.
            'action_status'    => 'nullable|string|max:20',
        ]);

        // Cache key based on all filter params + page
        $cacheKey = CacheService::generateKey('policy_list', [
            $validated['status'] ?? '',
            $validated['draft'] ?? '',
            $validated['product_id'] ?? '',
            $validated['exclude_products'] ?? '',
            $validated['agent_id'] ?? '',
            $validated['agency_id'] ?? '',
            $validated['payment_method'] ?? '',
            $validated['search'] ?? '',
            $validated['action_status'] ?? '',
            $validated['per_page'] ?? 25,
            $request->get('page', 1),
        ]);

        $excludeProducts = !empty($validated['exclude_products'])
            ? array_map('intval', explode(',', $validated['exclude_products']))
            : [];

        $policies = CacheService::rememberQuery($cacheKey, function () use ($validated, $excludeProducts) {
            $paginated = Policy::select('id', 'policyNumber', 'status', 'is_draft', 'premium', 'customer_id', 'product_id', 'created_at')
                ->with([
                    'customer:id,firstName,lastName',
                    'customer.profile:id,customer_id,entity_type,company_id',
                    'customer.profile.company:id,name',
                    'product:id,name,product_type_id',
                    'product.type:id,name',
                ])
                ->when(isset($validated['status']), fn($q) => $q->where('status', $validated['status']))
                ->when(isset($validated['draft']), fn($q) => $q->where('is_draft', $validated['draft']))
                ->when($validated['product_id'] ?? null, fn($q, $v) => $q->where('product_id', $v))
                ->when(!empty($excludeProducts), fn($q) => $q->whereNotIn('product_id', $excludeProducts))
                ->when($validated['agent_id'] ?? null, fn($q, $v) => $q->where('agent_id', $v))
                ->when($validated['agency_id'] ?? null, fn($q, $v) => $q->where('agency_id', $v))
                // Payment method isn't a policies column — it's set on
                // customer_banking (current billing choice) and/or logged on
                // payment_transactions (actual charges, e.g. CASH entries that
                // never get a banking row). Match either, case-insensitively,
                // same as the legacy Graphite filter matched payment_transactions.
                ->when($validated['payment_method'] ?? null, function ($q, $method) {
                    $lower = strtolower($method);
                    $q->where(function ($q) use ($lower) {
                        $q->whereIn('id', function ($sub) use ($lower) {
                            $sub->select('policy_id')->from('customer_banking')
                                ->whereRaw('LOWER(billing) = ?', [$lower]);
                        })->orWhereIn('id', function ($sub) use ($lower) {
                            $sub->select('policy_id')->from('payment_transactions')
                                ->whereRaw('LOWER(paymentMethod) = ?', [$lower]);
                        });
                    });
                })
                // Filter by latest policy_action status using a correlated
                // subquery — scales without an additional join even on large
                // tables. Only DomCom rows carry policy_actions so others
                // are filtered out naturally when action_status is set.
                ->when($validated['action_status'] ?? null, function ($q, $status) {
                    $q->whereRaw("(
                        SELECT pa.status FROM policy_actions pa
                        WHERE pa.policy_id = policies.id AND pa.deleted_at IS NULL
                        ORDER BY pa.id DESC LIMIT 1
                    ) = ?", [$status]);
                })
                ->when($validated['search'] ?? null, function ($q, $search) {
                    $search = trim(preg_replace('/\s+/', ' ', $search));
                    $normalizedSearch = str_replace(' ', '', $search);
                    $like = "%{$search}%";
                    // Split into individual words for partial multi-word matching
                    // e.g. "Joh Smi" → firstName LIKE '%Joh%' AND lastName LIKE '%Smi%'
                    $words = count(explode(' ', $search)) >= 2
                        ? array_values(array_filter(explode(' ', $search)))
                        : [];
                    $q->where(function ($q) use ($like, $normalizedSearch, $words) {
                        $q->where('policyNumber', 'like', $like)
                          ->orWhereHas('customer', function ($c) use ($like, $words) {
                              $c->where('firstName', 'like', $like)
                                ->orWhere('lastName', 'like', $like)
                                ->orWhereRaw("CONCAT_WS(' ', TRIM(firstName), TRIM(lastName)) LIKE ?", [$like])
                                ->orWhereRaw("CONCAT_WS(' ', TRIM(firstName), TRIM(middleName), TRIM(lastName)) LIKE ?", [$like])
                                ->orWhere('cellphone', 'like', $like)
                                ->orWhereHas('profile.company', fn($co) =>
                                    $co->where('name', 'like', $like));
                              if (count($words) >= 2) {
                                  // "Joh Smi" → John Smith (first-last order)
                                  $c->orWhere(fn($i) =>
                                      $i->where('firstName', 'like', "%{$words[0]}%")
                                        ->where('lastName', 'like', "%{$words[1]}%")
                                  // "Smith Jo" → also finds John Smith (reversed input)
                                  )->orWhere(fn($i) =>
                                      $i->where('firstName', 'like', "%{$words[1]}%")
                                        ->where('lastName', 'like', "%{$words[0]}%")
                                  );
                              }
                          })
                          ->orWhereHas('agency', fn($a) =>
                              $a->where('name', 'like', $like))
                          ->orWhereHas('vehicle', fn($v) =>
                              $v->where('vehiclePlate', 'like', $like)
                                ->orWhere('vehiclePlate', 'like', "%{$normalizedSearch}%"))
                          ->orWhereHas('risk_address', fn($r) =>
                              $r->where('address_name', 'like', $like)
                                ->orWhere('physical_address', 'like', $like));
                    });
                })
                ->orderBy('id', 'desc')
                ->paginate($validated['per_page'] ?? 25);

            // Batch-fetch latest action state for DomCom policies on this page (avoids N+1)
            $domcomProductIds = [7, 8, 16,17,18,20,22,23,24];
            $domcomPolicyIds = $paginated->getCollection()
                ->filter(fn($p) => in_array($p->product_id, $domcomProductIds))
                ->pluck('id')
                ->toArray();

            $actionStates = [];
            if (!empty($domcomPolicyIds)) {
                $actionStates = \Illuminate\Support\Facades\DB::table('policy_actions')
                    ->select('policy_id', 'status')
                    ->whereIn('policy_id', $domcomPolicyIds)
                    ->whereNull('deleted_at')
                    ->orderBy('id', 'desc')
                    ->get()
                    ->unique('policy_id')
                    ->pluck('status', 'policy_id')
                    ->toArray();
            }

            $paginated->getCollection()->transform(function ($p) use ($actionStates) {
                $p->action_status = $actionStates[$p->id] ?? null;
                return $p;
            });

            return $paginated;
        }, CacheService::CACHE_TTL_SHORT);

        // COM (commercial) product IDs — show organisation/company name for these
        // instead of the individual customer's first+last name. DOM counterparts
        // (7, 16, 18) stay on personal names.
        $comProductIds = [8, 17, 19];

        $items = $policies->getCollection()->map(function ($p) use ($comProductIds) {
            $personalName = trim(($p->customer->firstName ?? '') . ' ' . ($p->customer->lastName ?? ''));
            $companyName = $p->customer?->profile?->company?->name;
            $entityType = $p->customer?->profile?->entity_type;

            // Prefer company name for COM products or when the profile is
            // explicitly Organisation — falls back to personal name if the
            // company relation isn't populated for whatever reason.
            $isCom = in_array($p->product_id, $comProductIds) || $entityType === 'Organisation';
            $displayName = ($isCom && !empty($companyName)) ? $companyName : $personalName;

            return [
                'id'          => $p->id,
                'policyNumber'=> $p->policyNumber,
                'status'      => $p->status,
                'statusLabel' => match((int) $p->status) { 0 => 'inactive', 1 => 'active', 2 => 'cancelled', default => 'unknown' },
                'premium'     => $p->premium,
                'createdAt'   => $p->created_at?->toISOString(),
                'customer'    => $p->customer ? [
                    'id' => $p->customer->id,
                    'fullName' => $displayName ?: $personalName,
                    'isOrganisation' => $isCom && !empty($companyName),
                ] : null,
                'product'     => $p->product ? ['id' => $p->product->id, 'name' => $p->product->name] : null,
                'actionStatus'=> $p->action_status ?? null,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'total'        => $policies->total(),
                'per_page'     => $policies->perPage(),
                'current_page' => $policies->currentPage(),
                'last_page'    => $policies->lastPage(),
                'from'         => $policies->firstItem(),
                'to'           => $policies->lastItem(),
            ],
            'links' => [
                'first' => null,
                'last'  => null,
                'prev'  => null,
                'next'  => null,
            ],
        ]);
    }

    /**
     * Main policy detail — loads only core info (policy fields + customer + product + agent + kyc summary).
     * Tab data (vehicles, transactions, claims, etc.) is fetched lazily via separate endpoints.
     */
    public function show(int $id): PolicyResource
    {
        $policy = CacheService::rememberPolicy($id, function () use ($id) {
            // Single query with JOINs to avoid multiple DB roundtrips (remote DB has ~1.3s latency per query)
            $row = DB::selectOne("
                SELECT
                    p.*,
                    c.id as c_id, c.firstName as c_firstName, c.middleName as c_middleName,
                    c.lastName as c_lastName, c.email as c_email, c.cellphone as c_cellphone,
                    cb.id as cb_id, cb.customer_id as cb_customer_id, cb.bankName as cb_bankName,
                    cb.branchCode as cb_branchCode, cb.accountType as cb_accountType,
                    cb.accountNumber as cb_accountNumber, cb.billing as cb_billing,
                    cb.billingCell as cb_billingCell, cb.orangeMoney as cb_orangeMoney,
                    cb.myzaka as cb_myzaka, cb.client_number as cb_client_number,
                    cb.contract_number as cb_contract_number,
                    cp.id as cp_id, cp.customer_id as cp_customer_id, cp.address as cp_address,
                    cp.omang as cp_omang, cp.dob as cp_dob, cp.passport as cp_passport,
                    cp.maritalstatus as cp_maritalstatus, cp.driving_license_number as cp_driving_license_number,
                    cp.license_valid_till as cp_license_valid_till, cp.city as cp_city,
                    cp.gender as cp_gender, cp.countryId as cp_countryId,
                    cp.sourceOfIncome as cp_sourceOfIncome, cp.tax_id as cp_tax_id,
                    cp.e_name as cp_e_name, cp.emp_no as cp_emp_no,
                    cp.emp_phone as cp_emp_phone, cp.salary_pay_date as cp_salary_pay_date,
                    cp.entity_type as cp_entity_type, cp.company_id as cp_company_id,
                    cp.is_pep as cp_is_pep, cp.pep_type as cp_pep_type,
                    cp.is_pep_related as cp_is_pep_related, cp.pep_relationship as cp_pep_relationship,
                    cp.pep_relationship_specify as cp_pep_relationship_specify,
                    co.id as co_id, co.name as co_name,
                    co.VAT_registration_number as co_vat_number,
                    co.company_registration_number as co_company_registration_number,
                    pr.id as pr_id, pr.name as pr_name, pr.slug as pr_slug,
                    pt.name as pr_type, pr.has_vehicle as pr_has_vehicle, pr.has_member as pr_has_member,
                    pp.id as pp_id, pp.product_id as pp_product_id, pp.name as pp_name,
                    pp.sum_assured as pp_sum_assured, pp.premium as pp_premium,
                    u.id as u_id, u.firstName as u_firstName, u.lastName as u_lastName, u.email as u_email,
                    ag.id as ag_id, ag.name as ag_name,
                    st.id as st_id, st.name as st_name,
                    k.id as k_id, k.customer_id as k_customer_id, k.compliance as k_compliance,
                    k.omang as k_omang, k.omangBack as k_omangBack, k.passport as k_passport,
                    k.passport_back as k_passport_back, k.driving_license as k_driving_license,
                    k.driving_license_back as k_driving_license_back, k.proof_residence as k_proof_residence,
                    k.proof_income as k_proof_income, k.debit_authorization_form as k_debit_authorization_form,
                    k.bank_statement_file_path as k_bank_statement_file_path,
                    pacd.cancelled_date as cancelled_date_value,
                    cf.cancelled_by as cancelled_by_value,
                    pa.id as pa_id,
                    COALESCE(pa.effective_from, ptm.term_start_date) as pa_effective_from,
                    COALESCE(pa.effective_to,   ptm.term_end_date)   as pa_effective_to,
                    pa.transaction_type as pa_transaction_type
                FROM policies p
                LEFT JOIN customer c ON c.id = p.customer_id
                LEFT JOIN customer_banking cb ON cb.id = COALESCE(
                    (SELECT id FROM customer_banking WHERE policy_id = p.id ORDER BY id DESC LIMIT 1),
                    (SELECT id FROM customer_banking WHERE customer_id = c.id AND active = 1 ORDER BY id DESC LIMIT 1)
                )
                LEFT JOIN customer_profile cp ON cp.customer_id = c.id
                LEFT JOIN companies co ON co.id = cp.company_id
                LEFT JOIN products pr ON pr.id = p.product_id
                LEFT JOIN product_types pt ON pt.id = pr.product_type_id
                LEFT JOIN product_plans pp ON pp.id = p.plan_id
                LEFT JOIN users u ON u.id = p.agent_id
                LEFT JOIN agencies ag ON ag.id = p.agency_id
                LEFT JOIN stores st ON st.id = p.storeID
                LEFT JOIN customer_kyc k ON k.customer_id = c.id
                LEFT JOIN policyactivatecancelleddates pacd ON pacd.policyNumber = p.policyNumber
                LEFT JOIN customer_feedback cf ON cf.id = (
                    SELECT id FROM customer_feedback
                    WHERE policy_id = p.id
                    ORDER BY id DESC
                    LIMIT 1
                )
                LEFT JOIN policy_actions pa ON pa.id = (
                    SELECT id FROM policy_actions
                    WHERE policy_id = p.id
                    ORDER BY id DESC
                    LIMIT 1
                )
                LEFT JOIN policy_term ptm ON ptm.id = pa.term_id
                WHERE p.id = ?
                LIMIT 1
            ", [$id]);

            if (!$row) {
                abort(404, 'Policy not found');
            }

            return $this->hydrateFromRow($row);
        });

        return new PolicyResource($policy);
    }

    /**
     * Hydrate a Policy model + relations from a single joined row.
     */
    private function hydrateFromRow(object $row): Policy
    {
        $policy = new Policy();
        $policy->setRawAttributes((array) $row, true);
        $policy->exists = true;

        // Hydrate customer
        if ($row->c_id) {
            $customer = new \AlphaDirect\Customer();
            $customer->setRawAttributes([
                'id' => $row->c_id, 'firstName' => $row->c_firstName,
                'middleName' => $row->c_middleName, 'lastName' => $row->c_lastName,
                'email' => $row->c_email, 'cellphone' => $row->c_cellphone,
            ], true);
            $customer->exists = true;

            // Hydrate banking onto customer
            if ($row->cb_id) {
                $banking = new \AlphaDirect\CustomerBanking();
                $banking->setRawAttributes([
                    'id' => $row->cb_id, 'customer_id' => $row->cb_customer_id,
                    'bankName' => $row->cb_bankName, 'branchCode' => $row->cb_branchCode,
                    'accountType' => $row->cb_accountType, 'accountNumber' => $row->cb_accountNumber,
                    'billing' => $row->cb_billing, 'billingCell' => $row->cb_billingCell,
                    'orangeMoney' => $row->cb_orangeMoney, 'myzaka' => $row->cb_myzaka,
                    'client_number' => $row->cb_client_number, 'contract_number' => $row->cb_contract_number,
                ], true);
                $banking->exists = true;
                $customer->setRelation('banking', $banking);
            }

            $policy->setRelation('customer', $customer);
        }

        // Hydrate profile
        if ($row->cp_id) {
            $profile = new \AlphaDirect\CustomerProfile();
            $profile->setRawAttributes([
                'id' => $row->cp_id, 'customer_id' => $row->cp_customer_id,
                'address' => $row->cp_address, 'omang' => $row->cp_omang, 'dob' => $row->cp_dob,
                'passport' => $row->cp_passport, 'maritalstatus' => $row->cp_maritalstatus,
                'driving_license_number' => $row->cp_driving_license_number,
                'license_valid_till' => $row->cp_license_valid_till, 'city' => $row->cp_city,
                'gender' => $row->cp_gender, 'countryId' => $row->cp_countryId,
                'sourceOfIncome' => $row->cp_sourceOfIncome, 'tax_id' => $row->cp_tax_id,
                'e_name' => $row->cp_e_name, 'emp_no' => $row->cp_emp_no,
                'emp_phone' => $row->cp_emp_phone, 'salary_pay_date' => $row->cp_salary_pay_date,
                'entity_type' => $row->cp_entity_type ?? null,
                'company_id' => $row->cp_company_id ?? null,
                // Prominent/Influential Person (PEP) declarations — drive the
                // "High Risk Customer" badge on the policy view header.
                'is_pep' => $row->cp_is_pep ?? null,
                'pep_type' => $row->cp_pep_type ?? null,
                'is_pep_related' => $row->cp_is_pep_related ?? null,
                'pep_relationship' => $row->cp_pep_relationship ?? null,
                'pep_relationship_specify' => $row->cp_pep_relationship_specify ?? null,
            ], true);
            $profile->exists = true;

            // High-risk country flag — set when the customer's country
            // (customer_profile.countryId) is on the AML watch-list managed via
            // Compliance > High Risk Countries. Drives the "High Risk Customer"
            // badge on the policy view (alongside the PEP declarations).
            $hrCountry = null;
            if (!empty($row->cp_countryId) && \Schema::hasTable('high_risk_countries')) {
                $hrCountry = DB::table('high_risk_countries as h')
                    ->leftJoin('countries as c', 'c.id', '=', 'h.country_id')
                    ->where('h.country_id', $row->cp_countryId)
                    ->value('c.name');
            }
            $profile->is_high_risk_country   = $hrCountry !== null;
            $profile->high_risk_country_name = $hrCountry;

            if (!empty($row->co_id)) {
                $company = new \AlphaDirect\Models\Company();
                $company->setRawAttributes([
                    'id'                          => $row->co_id,
                    'name'                        => $row->co_name,
                    'VAT_registration_number'     => $row->co_vat_number,
                    'company_registration_number' => $row->co_company_registration_number,
                ], true);
                $company->exists = true;
                $profile->setRelation('company', $company);
            }

            $policy->setRelation('profile', $profile);
        }

        // Hydrate product
        if ($row->pr_id) {
            $product = new \AlphaDirect\Product();
            $product->setRawAttributes([
                'id' => $row->pr_id, 'name' => $row->pr_name, 'slug' => $row->pr_slug,
                'type' => $row->pr_type, 'has_vehicle' => $row->pr_has_vehicle, 'has_member' => $row->pr_has_member,
            ], true);
            $product->exists = true;
            $policy->setRelation('product', $product);
        }

        // Hydrate plan
        if ($row->pp_id) {
            $plan = new \AlphaDirect\Productplan();
            $plan->setRawAttributes([
                'id' => $row->pp_id, 'product_id' => $row->pp_product_id,
                'name' => $row->pp_name, 'sum_assured' => $row->pp_sum_assured,
                'premium' => $row->pp_premium,
            ], true);
            $plan->exists = true;
            $policy->setRelation('plan', $plan);
        }

        // Hydrate user (agent)
        if ($row->u_id) {
            $user = new \AlphaDirect\User();
            $user->setRawAttributes([
                'id' => $row->u_id, 'firstName' => $row->u_firstName,
                'lastName' => $row->u_lastName, 'email' => $row->u_email,
            ], true);
            $user->exists = true;
            $policy->setRelation('user', $user);
        }

        // Hydrate agency
        if ($row->ag_id) {
            $agency = new \AlphaDirect\Agency();
            $agency->setRawAttributes(['id' => $row->ag_id, 'name' => $row->ag_name], true);
            $agency->exists = true;
            $policy->setRelation('agency', $agency);
        }

        // Hydrate store
        if ($row->st_id) {
            $store = new \AlphaDirect\Stores();
            $store->setRawAttributes(['id' => $row->st_id, 'name' => $row->st_name], true);
            $store->exists = true;
            $policy->setRelation('store', $store);
        }

        // Hydrate KYC
        if ($row->k_id) {
            $kyc = new \AlphaDirect\KYC();
            $kyc->setRawAttributes([
                'id' => $row->k_id, 'customer_id' => $row->k_customer_id,
                'compliance' => $row->k_compliance, 'omang' => $row->k_omang,
                'omangBack' => $row->k_omangBack, 'passport' => $row->k_passport,
                'passport_back' => $row->k_passport_back, 'driving_license' => $row->k_driving_license,
                'driving_license_back' => $row->k_driving_license_back,
                'proof_residence' => $row->k_proof_residence, 'proof_income' => $row->k_proof_income,
                'debit_authorization_form' => $row->k_debit_authorization_form,
                'bank_statement_file_path' => $row->k_bank_statement_file_path,
            ], true);
            $kyc->exists = true;
            $policy->setRelation('kyc', $kyc);
        }

        // Set cancelled info
        $policy->cancelled_date_value = $row->cancelled_date_value ?? null;
        $policy->cancelled_by_value = $row->cancelled_by_value ?? null;

        // Latest policy_action effective dates — used by specialist coverage
        // forms (Medical Malpractice, etc.) to prefill inception/expiry from
        // the active endorsement window rather than the parent term.
        $policy->latest_action_id              = $row->pa_id ?? null;
        $policy->latest_action_effective_from  = $row->pa_effective_from ?? null;
        $policy->latest_action_effective_to    = $row->pa_effective_to ?? null;
        $policy->latest_action_transaction_type = $row->pa_transaction_type ?? null;

        return $policy;
    }

    /**
     * Lazy tab: vehicles — skip DB query if product has no vehicles
     */
    public function vehicles(int $id): JsonResponse
    {
        $policy = Policy::select('id', 'has_vehicle', 'product_id')->findOrFail($id);

        // DomCom policies store vehicles even when has_vehicle=0 — always query.
        // Product set matches ClaimsController's DOM/COM list (incl. 19, which
        // was missing here and returned [] for has_vehicle=0 policies).
        $isDomCom = in_array($policy->product_id, [7, 8, 16, 17, 18, 19, 20, 22, 23, 24]);
        if (!$policy->has_vehicle && !$isDomCom) {
            return response()->json(['data' => []]);
        }

        // An explicit ?action_id= must belong to THIS policy — a foreign
        // action id silently returned an empty list before, which reads as
        // "term has no vehicles" to the caller.
        if (request()->filled('action_id')) {
            $ownsAction = DB::table('policy_actions')
                ->where('id', (int) request()->input('action_id'))
                ->where('policy_id', $id)
                ->exists();
            if (!$ownsAction) {
                return response()->json(['message' => 'Selected policy term was not found on this policy.'], 422);
            }
        }

        // Filter by action_id to avoid duplicates — every endorsement replicates
        // vehicle rows via PolicyAction::newPolicyAction. Default to the latest
        // action; allow ?action_id= override so the wizard can target a specific
        // endorsement (mirrors coverages / risk-addresses pattern).
        $latestActionId = request()->input('action_id') ?: DB::table('policy_actions')
            ->where('policy_id', $id)
            ->orderByDesc('id')
            ->value('id');

        $rows = DB::table('vehicle as v')
            ->leftJoin('risk_address as ra', 'ra.id', '=', 'v.risk_id')
            ->where('v.policy_id', $id)
            ->whereNull('v.deleted_at')
            ->when($latestActionId, fn($q) => $q->where('v.action_id', $latestActionId))
            ->select([
                'v.*',
                'ra.address_name as riskAddressName',
                'ra.physical_address as riskAddressPhysical',
            ])
            ->orderBy('v.id')
            ->get();

        // Fallback: if current action has no vehicles, try the most recent
        // action that has vehicles. Only when NO explicit action_id was
        // requested — an operator-selected action must show exactly its own
        // rows (an empty list is the correct answer there).
        if ($rows->isEmpty() && !request()->filled('action_id')) {
            $rows = DB::table('vehicle as v')
                ->leftJoin('risk_address as ra', 'ra.id', '=', 'v.risk_id')
                ->where('v.policy_id', $id)
                ->whereNull('v.deleted_at')
                ->select(['v.*', 'ra.address_name as riskAddressName', 'ra.physical_address as riskAddressPhysical'])
                ->orderByDesc('v.id')
                ->get()
                ->unique('vehiclePlate')
                ->values();
        }

        $pending = $rows->where('approval_status', 'PENDING')->values();

        // Helper: safe property access on stdClass (returns null if missing)
        $get = fn($obj, $prop) => property_exists($obj, $prop) ? $obj->$prop : null;

        return response()->json([
            'data' => $rows->map(function ($v) use ($get) {
                return [
                    'id'                => $get($v, 'id'),
                    'vehiclePlate'      => $get($v, 'vehiclePlate'),
                    'chassisNo'         => $get($v, 'vinnumber') ?? $get($v, 'chassisNo'),
                    'odometer'          => $get($v, 'odometer'),
                    'purpose'           => $get($v, 'purpose'),
                    'condition'         => $get($v, 'condition'),
                    'make'              => $get($v, 'make'),
                    'model'             => $get($v, 'model'),
                    'engineNo'          => $get($v, 'engineNo'),
                    'seats'             => $get($v, 'seats'),
                    'cylinders'         => $get($v, 'cylinders'),
                    'year'              => $get($v, 'year'),
                    'colour'            => $get($v, 'colour'),
                    'bodyType'          => $get($v, 'bodyType'),
                    'fuelType'          => $get($v, 'fuelType'),
                    'transmission'      => $get($v, 'transmission'),
                    'estimatedValue'    => $get($v, 'estimated_value') ?? $get($v, 'estimatedValue'),
                    'registeredOwner'   => $get($v, 'registeredOwner') ?? $get($v, 'registeredowner'),
                    'registrationNumber'=> $get($v, 'registrationNumber') ?? $get($v, 'registrationnumber'),
                    'antiTheftDevice'   => $get($v, 'antiTheftDevice'),
                    'trackerDevice'     => $get($v, 'trackerDevice') ?? $get($v, 'trackerdevice'),
                    'trackerDeviceType' => $get($v, 'trackerDeviceType'),
                    'isImported'        => (bool) ($get($v, 'is_imported') ?? false),
                    'claimCount'        => $get($v, 'claim_count'),
                    'vehicleType'       => $get($v, 'vehicle_type'),
                    'riskId'            => $get($v, 'risk_id'),
                    'riskAddressName'   => $get($v, 'riskAddressName'),
                    'riskAddressPhysical' => $get($v, 'riskAddressPhysical'),
                    'approvalStatus'    => $get($v, 'approval_status'),
                    'approvalComment'   => $get($v, 'approval_request_comment'),
                    'status'            => $get($v, 'status'),
                    // Preinspection images
                    'inspectionStatus'  => match((int) ($get($v, 'status') ?? 0)) {
                        1 => 'Approved', 2 => 'Unapproved', 3 => 'Recheck', default => 'Pending',
                    },
                    'documents'         => array_values(array_filter([
                        !empty($get($v, 'front')) ? ['label' => 'Front Photo', 'url' => $this->cdnUrl($get($v, 'front')), 'status' => $get($v, 'front_status')] : null,
                        !empty($get($v, 'back')) ? ['label' => 'Back Photo', 'url' => $this->cdnUrl($get($v, 'back')), 'status' => $get($v, 'back_status')] : null,
                        !empty($get($v, 'right')) ? ['label' => 'Right Photo', 'url' => $this->cdnUrl($get($v, 'right')), 'status' => $get($v, 'right_status')] : null,
                        !empty($get($v, 'left')) ? ['label' => 'Left Photo', 'url' => $this->cdnUrl($get($v, 'left')), 'status' => $get($v, 'left_status')] : null,
                        !empty($get($v, 'vehicleRegistration')) ? ['label' => 'Registration Book', 'url' => $this->cdnUrl($get($v, 'vehicleRegistration')), 'status' => $get($v, 'vehicle_registration_status')] : null,
                        !empty($get($v, 'vehicle_valuation')) ? ['label' => 'Vehicle Invoice/Valuation', 'url' => $this->cdnUrl($get($v, 'vehicle_valuation')), 'status' => $get($v, 'vehicle_invoice_status')] : null,
                    ])),
                    // Keyed image map (parallel to devices) so the edit modal can
                    // pre-load each slot by position; null when not uploaded.
                    'images'            => [
                        'front'        => !empty($get($v, 'front')) ? $this->cdnUrl($get($v, 'front')) : null,
                        'back'         => !empty($get($v, 'back')) ? $this->cdnUrl($get($v, 'back')) : null,
                        'right'        => !empty($get($v, 'right')) ? $this->cdnUrl($get($v, 'right')) : null,
                        'left'         => !empty($get($v, 'left')) ? $this->cdnUrl($get($v, 'left')) : null,
                        'registration' => !empty($get($v, 'vehicleRegistration')) ? $this->cdnUrl($get($v, 'vehicleRegistration')) : null,
                        'valuation'    => !empty($get($v, 'vehicle_valuation')) ? $this->cdnUrl($get($v, 'vehicle_valuation')) : null,
                    ],
                ];
            })->values(),
            'pendingApprovals' => $pending->map(fn($v) => [
                'id'            => $v->id,
                'vehiclePlate'  => $v->vehiclePlate,
                'make'          => $v->make,
                'model'         => $v->model,
                'comment'       => $v->approval_request_comment ?? null,
                'requestedBy'   => $v->approval_requested_by ?? null,
            ])->values(),
        ]);
    }

    /**
     * Lazy tab: members & beneficiaries — skip DB query if product has no members
     */
    public function members(int $id): JsonResponse
    {
        $policy = Policy::select('id', 'has_member', 'product_id')->findOrFail($id);

        if (!$policy->has_member) {
            return response()->json(['data' => ['members' => [], 'beneficiaries' => []]]);
        }

        $policy->load(['policyMembers', 'policyBeneficiaries']);

        return response()->json([
            'data' => [
                'members' => ($policy->policyMembers ?? collect())->map(fn($m) => [
                    'id'        => $m->id,
                    'relation'  => $m->relation,
                    'firstName' => $m->first_name,
                    'middleName'=> $m->middle_name,
                    'lastName'  => $m->last_name,
                    'dob'       => $m->dob,
                    'gender'    => $m->gender == 0 ? 'Female' : 'Male',
                    'omang'     => $m->omang,
                    'passport'  => $m->passport,
                    'cellphone' => $m->cellphone,
                    'email'     => $m->email,
                    'payment'   => $m->payment,
                    'createdAt' => $m->created_at?->toIso8601String(),
                ]),
                'beneficiaries' => ($policy->policyBeneficiaries ?? collect())->map(fn($b) => [
                    'id'        => $b->id,
                    'relation'  => $b->relation,
                    'firstName' => $b->first_name,
                    'middleName'=> $b->middle_name,
                    'lastName'  => $b->last_name,
                    'dob'       => $b->dob,
                    'gender'    => $b->gender == 0 ? 'Female' : 'Male',
                    'omang'     => $b->omang,
                    'passport'  => $b->passport,
                    'cellphone' => $b->cellphone,
                    'email'     => $b->email,
                    'payment'   => $b->payment,
                    'createdAt' => $b->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Lazy tab: devices — skip DB query if product is not Cellphone type
     */
    public function devices(int $id): JsonResponse
    {
        $policy = Policy::select('id', 'product_id')->findOrFail($id);
        $productType = \AlphaDirect\ProductType::join('products', 'products.product_type_id', '=', 'product_types.id')
            ->where('products.id', $policy->product_id)
            ->value('product_types.name');

        // graphiteBWV8 parity: the cellphone / Mobile & Electronic Device line is
        // canonically identified by product_id == 5. The product_types.name is not
        // always the literal 'Cellphone' for this product, so gate on the product id
        // (keeping the type-string check as a fallback for any other cellphone lines).
        if ($policy->product_id != 5 && $productType !== 'Cellphone') {
            return response()->json(['data' => []]);
        }

        $policy->load('devices');

        // Pre-inspection photos live on the policy_cellphone row itself and are
        // stored as S3 keys — convert to CloudFront URLs only when present (v8
        // MobileAppV2Controller::policyDetails does the same null-guarded mapping).
        $cf = fn($key) => $key ? \AlphaDirect\Helper::getCloudFrontURL($key) : null;

        return response()->json([
            'data' => ($policy->devices ?? collect())->map(fn($d) => [
                'id'        => $d->id,
                'deviceType'=> $d->device_type,
                'imei'      => $d->imei,
                'make'      => $d->cell_phone_make,
                'model'     => $d->cell_phone_model,
                'value'     => $d->phone_value,
                'status'    => $d->status,
                'images'    => [
                    'front'   => $cf($d->cell_phone_front),
                    'back'    => $cf($d->cell_phone_back),
                    'left'    => $cf($d->cell_phone_left),
                    'right'   => $cf($d->cell_phone_right),
                    'top'     => $cf($d->cell_phone_top),
                    'bottom'  => $cf($d->cell_phone_bottom),
                    'invoice' => $cf($d->cell_phone_invoice),
                ],
            ]),
        ]);
    }

    /**
     * Lazy tab: coverages — supports both MIS (policy_coverage) and DomCom (policy_coverages + tb_cvgpccoverages)
     */
    public function coverages(int $id, Request $request): JsonResponse
    {
        $policy = Policy::select('id', 'product_id')->findOrFail($id);
        // Canonical V2 product list — keep in sync with $v2SupportedProducts
        // in PolicyCreateController and $domcomProductIds at the top of this
        // controller. Adding product here makes /policies/{id}/coverages
        // return type=domcom, which is the flag the FE PolicyDetailPage uses
        // to render the rich coverage view (per-coverage schedule buttons,
        // edit/delete, details table). Without 20/22 here, Commercial
        // Liabilities and Marine policies fell through to the legacy
        // type=mis branch and the Policy Actions tab showed nothing but
        // "+ Add Coverage" and Action History.
        $isDomCom = in_array($policy->product_id, [7, 8, 16, 17, 18, 19, 20, 22]);
        // Default to the latest action_id when the caller doesn't pass one.
        // policy_coverages are versioned per action — without this filter
        // the cumulative set across all actions is returned, which surfaces
        // duplicates and stale rows from previous endorsements.
        $actionId = $request->query('action_id') ?: DB::table('policy_actions')
            ->where('policy_id', $id)
            ->orderByDesc('id')
            ->value('id');

        if ($isDomCom) {
            // Include cancelled (soft-deleted) parent coverages so the
            // frontend can render them with a Reinstate button. Expose
            // pc.deleted_at so the UI can branch on Cancelled vs Active.
            $coverages = DB::table('policy_coverages as pc')
                ->leftJoin('tb_cvgpccoverages as master', 'master.id', '=', 'pc.coverage_id')
                ->leftJoin('risk_address as ra', 'ra.id', '=', 'pc.risk_address_id')
                ->where('pc.policy_id', $id)
                ->when($actionId, fn($q) => $q->where('pc.action_id', $actionId))
                ->select([
                    'pc.id', 'pc.coverage_id', 'pc.risk_address_id', 'pc.action_id', 'pc.term_id',
                    'pc.row_type', 'pc.status', 'pc.deleted_at',
                    'master.s_CoverageName as coverageName', 'master.s_ScreenName as screenName',
                    'master.s_CoverageGroupName as groupName', 'master.s_CoverageCode as coverageCode',
                    'master.rate as masterRate', 'master.has_risk_address',
                    'ra.address_name as riskAddressName', 'ra.physical_address as riskAddress',
                ])
                ->orderBy('master.n_DisplaySequence')
                ->get();

            // Get detail rows, notes, extensions, and motor vehicles for each coverage
            $coverageIds = $coverages->pluck('id')->toArray();

            $details = !empty($coverageIds)
                ? DB::table('policy_coverage_detail as pcd')
                    ->leftJoin('tb_cvgpccoverages as sub', 'sub.id', '=', 'pcd.coverage_id')
                    ->whereIn('pcd.policy_coverage_id', $coverageIds)
                    ->whereNull('pcd.deleted_at')
                    ->select('pcd.*', 'sub.s_ScreenName as description', 'sub.s_CoverageName as coverageName')
                    ->get()
                    ->groupBy('policy_coverage_id')
                : collect();

            // Notes live in policy_coverage_notes with (pc_id, motor_id) key.
            // motor_id IS NULL (or 0) => coverage-level note; otherwise a
            // per-vehicle note (MOTOR coverages store one note per motor).
            // Exclude soft-deleted notes (DB::table bypasses the SoftDeletes
            // scope) — a stale deleted row would otherwise resurface, and on
            // coverages with multiple historic rows keyBy must keep the live
            // latest, so order by id asc.
            $notesRaw = !empty($coverageIds)
                ? DB::table('policy_coverage_notes')->whereIn('policy_coverage_id', $coverageIds)
                    ->whereNull('deleted_at')->orderBy('id')->get()
                : collect();
            // A coverage can carry MORE THAN ONE coverage-level row: the Money
            // warranty writes used to resolve the row by `motor_id IS NULL`
            // only, so a note row stored with motor_id = 0 was missed and a
            // second row was inserted. keyBy() keeps just the newest, so a
            // note saved on the older row read back blank. Fold every
            // coverage-level row into one — last non-empty value wins per
            // column — so the note, the Money warranties and the WC benefits
            // text all show regardless of which row ended up holding them.
            $notes = $notesRaw->filter(fn($n) => empty($n->motor_id))
                ->groupBy('policy_coverage_id')
                ->map(function ($rows) {
                    $merged = (object) [];
                    foreach ($rows as $row) {
                        foreach (get_object_vars($row) as $col => $val) {
                            if (!property_exists($merged, $col) || trim((string) ($val ?? '')) !== '') {
                                $merged->{$col} = $val;
                            }
                        }
                    }
                    return $merged;
                });
            $notesByMotorId = $notesRaw->filter(fn($n) => !empty($n->motor_id))
                ->keyBy('motor_id')
                ->map(fn($n) => $n->note);

            // Extension label lookup mirrors legacy TbCvgpcLimits relationship:
            //   policy_extention_detail.extention_limit_id -> tb_cvgpclimits.n_PCLimitId_PK
            // and the master label comes from `extentions.s_CoverageName`.
            $extensions = !empty($coverageIds)
                ? DB::table('policy_extention_detail as ped')
                    ->leftJoin('extentions as ext', 'ext.id', '=', 'ped.extentions_id')
                    ->leftJoin('tb_cvgpclimits as lim', 'lim.n_PCLimitId_PK', '=', 'ped.extention_limit_id')
                    ->whereIn('ped.policy_coverage_id', $coverageIds)
                    ->whereNull('ped.deleted_at')
                    ->orderBy('ped.n_DisplaySequence')
                    ->select(
                        'ped.*',
                        'ext.s_CoverageName as master_name',
                        'ext.s_CoverageCode as master_code',
                        'lim.s_LimitScreenName as limit_label'
                    )
                    ->get()->groupBy('policy_coverage_id')
                : collect();

            // All specified items — grouped by policy_coverage_id then further filterable by motor_id
            $specifiedItemsAll = !empty($coverageIds)
                ? DB::table('policy_specified_items as psi')
                    ->leftJoin('specified_coverage_items as sci', 'sci.id', '=', 'psi.specified_coverage_id')
                    ->whereIn('psi.policy_coverage_id', $coverageIds)
                    ->whereNull('psi.deleted_at')
                    ->select('psi.*', 'sci.specified_name as item_name')
                    ->get()
                    ->groupBy('policy_coverage_id')
                : collect();

            // Motor vehicles (COMMERCIALMOTOR, MOTORTRADERSEXTERNAL) — stored in `motor` table.
            //
            // The `motor` table is keyed on policy_coverage_id, not action_id. Legacy
            // endorsements are supposed to replicate motor rows alongside the new pc
            // rows, but when that replication fails (or hasn't run on older data)
            // the current action's pc row has zero motors, and Monika sees "All
            // motor not coming to Edit". Fallback: per motor-coverage, if the
            // current pc_id has no motors, reuse the most-recent sibling pc_id
            // (same coverage_id, same risk_address_id) that does.
            $motorVehicles = !empty($coverageIds)
                ? DB::table('motor')
                    ->whereIn('policy_coverage_id', $coverageIds)
                    ->whereNull('deleted_at')
                    ->get()
                    ->groupBy('policy_coverage_id')
                : collect();

            $motorCoverageCodes = ['COMMERCIALMOTOR', 'MOTORTRADERSEXTERNAL', 'MOTORTRADERSINTERNAL'];
            foreach ($coverages as $c) {
                $code = strtoupper($c->coverageCode ?? '');
                if (!in_array($code, $motorCoverageCodes, true)) continue;
                $currentRows = $motorVehicles->get($c->id, collect());
                if ($currentRows->isNotEmpty()) continue;

                // Find the newest pc_id with the same coverage_id (ignore risk
                // address — some envs leave it null on the older pc row) that
                // has motors, and surface those rows under the current pc_id.
                $fallbackPcId = DB::table('policy_coverages as pc')
                    ->join('motor as m', 'm.policy_coverage_id', '=', 'pc.id')
                    ->where('pc.policy_id', $id)
                    ->where('pc.coverage_id', $c->coverage_id)
                    ->whereNull('pc.deleted_at')
                    ->whereNull('m.deleted_at')
                    ->where('pc.id', '!=', $c->id)
                    ->orderByDesc('pc.action_id')
                    ->orderByDesc('pc.id')
                    ->value('pc.id');
                if ($fallbackPcId) {
                    $fallbackRows = DB::table('motor')
                        ->where('policy_coverage_id', $fallbackPcId)
                        ->whereNull('deleted_at')
                        ->get();
                    if ($fallbackRows->isNotEmpty()) {
                        $motorVehicles->put($c->id, $fallbackRows);
                    }
                }
            }

            // Motor Traders External vehicles — stored in `motor_traders` table
            $motorTradersExt = !empty($coverageIds)
                ? DB::table('motor_traders')
                    ->whereIn('policy_coverage_id', $coverageIds)
                    ->whereNull('deleted_at')
                    ->get()
                    ->groupBy('policy_coverage_id')
                : collect();

            // Motor Traders Internal vehicles — stored in `motor_traders_internal` table
            $motorTradersInt = !empty($coverageIds)
                ? DB::table('motor_traders_internal')
                    ->whereIn('policy_coverage_id', $coverageIds)
                    ->whereNull('deleted_at')
                    ->get()
                    ->groupBy('policy_coverage_id')
                : collect();

            // Fidelity Guarantee data — stored in `policy_coverages_data`
            // (keyed by policyCoverageID), NOT in policy_coverage_detail /
            // motor / specialist tables. Without surfacing it here the Policy
            // Actions tab fell through every branch and showed "No premium
            // detail recorded yet" for Fidelity even though the row carries an
            // amount-to-be-guaranteed + premium (the V2 Quote Sheet renders it).
            // Query mirrors PolicyCreateController's editData fetch so shapes line up.
            $fidelityByPc = !empty($coverageIds) && \Schema::hasTable('policy_coverages_data')
                ? DB::table('policy_coverages_data')
                    ->whereIn('policyCoverageID', $coverageIds)
                    ->where('policy_id', $id)
                    ->get()
                    ->groupBy('policyCoverageID')
                : collect();

            // Specialist coverages (CAR/PAR/EAR/Travel/MM/PI/Machinery/Marine)
            // keep their premium in their own one-to-one table, NOT in
            // policy_coverage_detail / motor / extensions. The per-coverage
            // build below therefore reports 0 for them, so the Added Coverages
            // list + local-sum total showed P0 on an endorse QUOTE before Rate
            // (COM/DOM instead shows its replicated child-row premiums). Surface
            // each specialist coverage's own annual premium keyed by
            // policy_coverage_id so the row reads like COM/DOM. Registry-driven,
            // so all ten specialist products are covered; a no-op for COM/DOM
            // coverages (their pc ids never appear in a specialist table).
            $specialistPremiumByPc = [];
            if (!empty($coverageIds)) {
                foreach (\AlphaDirect\Services\SpecialistEndorse\SpecialistCoverageRegistry::TABLES as $sTable => $sMeta) {
                    if (!\Schema::hasTable($sTable)) continue;
                    $sCols = \Schema::getColumnListing($sTable);
                    $premiumCols = array_values(array_filter(
                        $sMeta['premium_cols'],
                        fn($col) => in_array($col, $sCols, true)
                    ));
                    if (empty($premiumCols)) continue;
                    DB::table($sTable)
                        ->whereIn('policy_coverage_id', $coverageIds)
                        ->when(in_array('deleted_at', $sCols, true), fn($q) => $q->whereNull('deleted_at'))
                        ->get()
                        ->each(function ($row) use (&$specialistPremiumByPc, $premiumCols) {
                            $premium = 0.0;
                            foreach ($premiumCols as $col) {
                                $premium += (float) ($row->{$col} ?? 0);
                            }
                            $specialistPremiumByPc[(int) $row->policy_coverage_id] = $premium;
                        });
                }
            }

            // Canonical Total Coverage Premium — read directly from
            // policy_actions.annual_premium for the resolved action.
            // recomputeActionTotals / calculatePremium keep that column
            // fresh after every wizard save, cancel, reinstate, and Rate
            // click using the correct bucket scope (Fidelity-only covData,
            // motor isolation for coverage_id 22/27, motor.premium_*
            // extensions, specialist product guard). Re-summing the
            // buckets here would drift from Rate / V2 Quote whenever a
            // bucket-scope rule changes — e.g. policy 213363 saw 9,837
            // here vs 4,054 on Rate because this path didn't apply the
            // Fidelity gate or the motor-detail isolation.
            $totalPremium = $actionId
                ? (float) (DB::table('policy_actions')->where('id', $actionId)->value('annual_premium') ?? 0)
                : 0.0;

            // Endorsement reason — from the current action's note when transaction_type = ENDORSE
            $endorsementReason = null;
            if ($actionId) {
                $action = DB::table('policy_actions')->where('id', $actionId)->first();
                if ($action && $action->transaction_type === 'ENDORSE') {
                    $endorsementReason = $action->note ?? $action->transaction_reason ?? null;
                }
            }

            // Group by risk address for frontend
            $grouped = $coverages->groupBy('risk_address_id');

            return response()->json([
                'type' => 'domcom',
                'endorsementReason' => $endorsementReason,
                'totalPremium' => $totalPremium,
                'data' => $coverages->map(function ($c) use ($details, $notes, $notesByMotorId, $extensions, $specifiedItemsAll, $motorVehicles, $motorTradersExt, $motorTradersInt, $specialistPremiumByPc, $fidelityByPc) {
                    $covDetails = $details->get($c->id, collect());
                    $note = $notes->get($c->id);
                    $exts = $extensions->get($c->id, collect());
                    $covSpecifiedItems = $specifiedItemsAll->get($c->id, collect());

                    // Collect vehicles from the appropriate motor table based on coverage code
                    $coverageCodeUpper = strtoupper($c->coverageCode ?? '');
                    $isMotorTraders = in_array($coverageCodeUpper, ['MOTORTRADERSEXTERNAL', 'MOTORTRADERSINTERNAL'], true);
                    if ($coverageCodeUpper === 'MOTORTRADERSEXTERNAL') {
                        $vehicleRows = $motorTradersExt->get($c->id, collect());
                    } elseif ($coverageCodeUpper === 'MOTORTRADERSINTERNAL') {
                        $vehicleRows = $motorTradersInt->get($c->id, collect());
                    } else {
                        // COMMERCIALMOTOR and any other motor-type coverages
                        $vehicleRows = $motorVehicles->get($c->id, collect());
                    }

                    return [
                        'id' => $c->id,
                        'coverageId' => $c->coverage_id,
                        'actionId' => $c->action_id,
                        'coverageName' => $c->coverageName,
                        'screenName' => $c->screenName,
                        'groupName' => $c->groupName,
                        'coverageCode' => $c->coverageCode,
                        'masterRate' => $c->masterRate,
                        'riskAddressId' => $c->risk_address_id,
                        'riskAddressName' => $c->riskAddressName,
                        'riskAddress' => $c->riskAddress,
                        'rowType' => $c->row_type,
                        'status' => $c->status,
                        'hasRiskAddress' => $c->has_risk_address,
                        // Specialist coverages have no detail/motor/ext/SI child
                        // rows — their premium lives in their own table. Surface
                        // it as the coverage-level calculated_value (snake_case,
                        // matching what the wizard reads) so the Added Coverages
                        // row + local-sum total show the replicated premium just
                        // like COM/DOM. Null for COM/DOM coverages → the wizard
                        // keeps summing their child rows as before.
                        'calculated_value' => $specialistPremiumByPc[$c->id] ?? null,
                        // Fidelity Guarantee rows (policy_coverages_data). Empty
                        // for every non-Fidelity coverage — the FE renders this
                        // table only when present, otherwise falls through to the
                        // existing details/motor/vehicle branches.
                        'fidelityData' => $fidelityByPc->get($c->id, collect())->map(fn($f) => [
                            'id'                   => $f->id,
                            'coverType'            => $f->cover_type ?? null,
                            'coverArea'            => $f->cover_area ?? null,
                            'nameAndPosition'      => $f->name_and_position ?? null,
                            'designation'          => $f->designation ?? null,
                            'lengthOfService'      => $f->length_of_service ?? null,
                            'amountToBeGuaranteed' => $f->amount_to_be_guaranteed,
                            'premium'              => $f->premium,
                        ])->values(),
                        'details' => $covDetails
                            ->filter(fn($d) => (float) ($d->coverage_value ?? 0) > 0 || (float) ($d->calculated_value ?? 0) > 0)
                            ->map(fn($d) => [
                            'id' => $d->id,
                            'description' => $d->description ?? $d->coverageName ?? $d->coverage_value_string ?? null,
                            'coverageValue' => $d->coverage_value,
                            'rate' => $d->rate,
                            'calculatedValue' => $d->calculated_value,
                            'proRatePremium' => $d->pro_rate_premium,
                            'discountSurcharge' => $d->discount_surcharge,
                            'discountSurchargeType' => $d->discount_surcharge_type ?? null,
                            'discountSurchargeValue' => $d->discount_surcharge_value ?? null,
                            'coverageValueString' => $d->coverage_value_string ?? null,
                        ])->values(),
                        // Motor vehicles (COMMERCIALMOTOR only) — Motor Traders has its own section-level shape below
                        'vehicles' => $isMotorTraders ? [] : $vehicleRows->map(fn($v) => [
                            'id'                => $v->id,
                            'vehicleName'       => $v->vehicle_name ?? null,
                            'coverageValue'     => $v->coverage_value ?? null,
                            'calculatedValue'   => $v->calculated_value ?? null,
                            'registrationNo'    => $v->registration_no ?? null,
                            'estimatedValue'    => $v->estimated_value ?? null,
                            'use'               => $v->use ?? null,
                            'make'              => $v->make ?? null,
                            'model'             => $v->model ?? null,
                            'engineNumber'      => $v->engine_number ?? null,
                            'chassisNumber'     => $v->chassis_number ?? null,
                            'typeOfCover'       => $v->type_of_cover ?? null,
                            'trackingDevice'    => $v->tracking_device ?? null,
                            'securityFeatures'  => $v->security_features ?? null,
                            // Sub-coverages (boolean flags)
                            'wreckageRemoval'   => $v->wreckage_removal ?? null,
                            'windowGlass'       => $v->window_glass ?? null,
                            'locksKeys'         => $v->locks_keys ?? null,
                            'partsAccessories'  => $v->parts_accessories ?? null,
                            'audioAccessories'  => $v->audio_accessories ?? null,
                            'riotStrike'        => $v->riot_strike ?? null,
                            'carHireTheft'      => $v->car_hire_theft ?? null,
                            'creditShortfall'   => $v->credit_shortfall ?? null,
                            'insuredDriver'     => $v->insured_driver ?? null,
                            'insuredFamily'     => $v->insured_family ?? null,
                            'medicalExpenses'   => $v->medical_expenses ?? null,
                            'passengerLiability'=> $v->passenger_liability ?? null,
                            'thirdPartyLiability'=> $v->third_party_liability ?? null,
                            'specifiedAccessories'=> $v->specified_accessories ?? null,
                            // Sub-coverage premiums
                            'premiumWreckageRemoval'   => $v->premium_wreckage_removal ?? null,
                            'premiumWindowGlass'       => $v->premium_window_glass ?? null,
                            'premiumLocksKeys'         => $v->premium_locks_keys ?? null,
                            'premiumPartsAccessories'  => $v->premium_parts_accessories ?? null,
                            'premiumAudioAccessories'  => $v->premium_audio_accessories ?? null,
                            'premiumRiotStrike'        => $v->premium_riot_strike ?? null,
                            'premiumCarHireTheft'      => $v->premium_car_hire_theft ?? null,
                            'premiumCreditShortfall'   => $v->premium_credit_shortfall ?? null,
                            'premiumInsuredDriver'     => $v->premium_insured_driver ?? null,
                            'premiumInsuredFamily'     => $v->premium_insured_family ?? null,
                            'premiumMedicalExpenses'   => $v->premium_medical_expenses ?? null,
                            'premiumPassengerLiability'=> $v->premium_passenger_liability ?? null,
                            'premiumThirdPartyLiability'=> $v->premium_third_party_liability ?? null,
                            'premiumSpecifiedAccessories'=> $v->premium_specified_accessories ?? null,
                            // Additional sub-coverage premiums that also feed the canonical
                            // motor bucket (v2 quote sheet / recomputeActionTotals). Surfaced
                            // so the Policy Actions per-coverage total reconciles with the
                            // quote sheet instead of understating motor premium.
                            'premiumUnorthorisedPassangerLiability' => $v->premium_unorthorised_passanger_liability ?? null,
                            'premiumParkingFacilities'   => $v->premium_parking_facilities ?? null,
                            'premiumComWindscreen'       => $v->premium_com_windscreen ?? null,
                            'premiumContigentLiability'  => $v->premium_contigent_liability ?? null,
                            // Excess/deductibles
                            'ownDamage'                => $v->own_damage ?? null,
                            'ownDamageMinPercent'      => $v->own_damage_minimun_percent ?? null,
                            'ownDamageMinAmount'       => $v->own_damage_minimum_amount ?? null,
                            'windscreen'               => $v->windscreen ?? null,
                            'windscreenMinPercent'     => $v->windscreen_minimun_percent ?? null,
                            'windscreenMinAmount'      => $v->windscreen_minimum_amount ?? null,
                            'lossOfKeys'               => $v->loss_of_keys ?? null,
                            'lossOfKeysMinPercent'     => $v->loss_of_keys_minimun_percent ?? null,
                            'lossOfKeysMinAmount'      => $v->loss_of_keys_minimum_amount ?? null,
                            // Per-motor note and specified items
                            'note'           => $notesByMotorId[$v->id] ?? ($v->note ?? null),
                            'specifiedItems' => $covSpecifiedItems
                                ->filter(fn($s) => (int) ($s->motor_id ?? 0) === (int) $v->id)
                                ->map(fn($s) => [
                                    'id'             => $s->id,
                                    'name'           => $s->item_name ?? 'Item #' . $s->specified_coverage_id,
                                    'sumInsured'     => $s->sum_insured,
                                    'rate'           => $s->rate,
                                    'calculatedValue'=> $s->calculated_value,
                                ])->values(),
                        ])->values(),
                        // Motor Traders (External / Internal) — section-level shape: each row
                        // carries a type_of_cover plus 14 sub-coverage value/premium pairs and
                        // own-damage / windscreen excess fields. Not vehicle-keyed.
                        'motorTraders' => $isMotorTraders ? $vehicleRows->map(function ($v) {
                            $pairs = [
                                ['label' => 'Loss or Damage',                'cv' => $v->loss_or_damage_coverage_value,                          'pv' => $v->loss_or_damage_calculated_value],
                                ['label' => 'Third Party Liability',         'cv' => $v->third_party_liability_coverage_value,                   'pv' => $v->third_party_liability_calculated_value],
                                ['label' => 'Medical Benefits',              'cv' => $v->medical_benefits_coverage_value,                        'pv' => $v->medical_benefits_calculated_value],
                                ['label' => 'Vehicle Lent / Hire',           'cv' => $v->vehicle_lent_hire_coverage_value,                       'pv' => $v->vehicle_lent_hire_calculated_value],
                                ['label' => 'Social, Domestic & Pleasure',   'cv' => $v->social_domestic_pleasure_coverage_value,                'pv' => $v->social_domestic_pleasure_calculated_value],
                                ['label' => 'Unauthorised Use',              'cv' => $v->unauthoried_use_coverage_value,                         'pv' => $v->unauthoried_use_calculated_value],
                                ['label' => 'Windscreen',                    'cv' => $v->windscreen_coverage_value,                              'pv' => $v->windscreen_calculated_value],
                                ['label' => 'Contingent Liability',          'cv' => $v->contigent_liability_coverage_value,                     'pv' => $v->contigent_liability_calculated_value],
                                ['label' => 'Wreckage Removal',              'cv' => $v->wreckage_removal_coverage_value,                        'pv' => $v->wreckage_removal_calculated_value],
                                ['label' => 'Loss of Key',                   'cv' => $v->loss_of_key_coverage_value,                             'pv' => $v->loss_of_key_calculated_value],
                                ['label' => 'Loss of Use of Customer',       'cv' => $v->Loss_of_use_of_customer_coverage_value,                 'pv' => $v->Loss_of_use_of_customer_calculated_value],
                                ['label' => 'Motor Cycle / Motor Tricycle',  'cv' => $v->motor_cycle_motor_tricycle_coverage_value,              'pv' => $v->motor_cycle_motor_tricycle_calculated_value],
                                ['label' => 'Passenger Liability (Motor)',   'cv' => $v->passanger_liability_respect_of_motor_coverage_value,    'pv' => $v->passanger_liability_respect_of_motor_calculated_value],
                                ['label' => 'Special Type Vehicle',          'cv' => $v->special_type_vehicle_coverage_value,                    'pv' => $v->special_type_vehicle_calculated_value],
                            ];
                            $subCoverages = [];
                            foreach ($pairs as $p) {
                                if ((float) ($p['cv'] ?? 0) > 0 || (float) ($p['pv'] ?? 0) > 0) {
                                    $subCoverages[] = [
                                        'label'           => $p['label'],
                                        'coverageValue'   => $p['cv'],
                                        'calculatedValue' => $p['pv'],
                                    ];
                                }
                            }
                            return [
                                'id'              => $v->id,
                                'typeOfCover'     => $v->type_of_cover,
                                'subCoverages'    => $subCoverages,
                                'totalCoverageValue'   => array_sum(array_map(fn($r) => (float) ($r['coverageValue'] ?? 0), $subCoverages)),
                                'totalCalculatedValue' => array_sum(array_map(fn($r) => (float) ($r['calculatedValue'] ?? 0), $subCoverages)),
                                'excess' => [
                                    'ownDamageMinPercent'  => $v->own_damage_minimun_percent,
                                    'ownDamageMinAmount'   => $v->own_damage_minimum_amount,
                                    'windscreenMinPercent' => $v->windscreen_minimun_percent,
                                    'windscreenMinAmount'  => $v->windscreen_minimum_amount,
                                ],
                            ];
                        })->values() : null,
                        'note' => $note->note ?? null,
                        'extensions' => $exts->map(function ($e) {
                            // Display name resolution (mirrors legacy blade):
                            //   DROPDOWN/RADIO -> the limit's screen name
                            //   NOEDIT / NUMBER / free text -> fall back to master extension name + the stored text/number
                            $displayName = null;
                            $extType = strtoupper((string) ($e->extention_type ?? ''));
                            if (in_array($extType, ['DROPDOWN', 'RADIO'])) {
                                $displayName = $e->limit_label ?: $e->master_name;
                            } else {
                                $displayName = $e->master_name;
                            }
                            return [
                                'id'              => $e->id,
                                'name'            => $displayName,
                                'code'            => $e->master_code,
                                'type'            => $e->type,
                                'extentionType'   => $e->extention_type,
                                'textValue'       => $e->extention_text_value ?? null,
                                'sumInsured'      => $e->extention_sum_insured,
                                'rate'            => $e->extention_rate,
                                'calculatedValue' => $e->extention_calculated_value,
                                'coverageValue'   => $e->extention_coverage_value ?? null,
                                'proRatePremium'  => $e->pro_rate_premium,
                                // Extension edit fields — returned so frontend can re-populate on edit
                                'extentions_id'              => $e->extentions_id,
                                's_ScreenName'               => $e->s_ScreenName ?? $displayName,
                                'extention_coverage_value'   => $e->extention_coverage_value ?? 0,
                                'extention_text_value'       => $e->extention_text_value ?? '',
                                'extention_limit_id'         => $e->extention_limit_id ?? null,
                                'extention_excess_min_value' => $e->extention_excess_min_value ?? 0,
                                'extention_excess_max_value' => $e->extention_excess_max_value ?? 0,
                                'extention_discount_surcharge'       => $e->extention_discount_surcharge ?? '',
                                'extention_discount_surcharge_type'  => $e->extention_discount_surcharge_type ?? '',
                                'extention_discount_surcharge_value' => $e->extention_discount_surcharge_value ?? 0,
                                'extention_calculated_value' => $e->extention_calculated_value ?? 0,
                            ];
                        })->values(),
                        // Coverage-level specified items (motor_id = null — not
                        // vehicle-specific). For coverages with NO vehicles (e.g.
                        // Fidelity Guarantee's "Miscellaneous Items"), motor_id is
                        // meaningless — include the item even if it carries a stray
                        // motor_id, otherwise it falls through both this filter and
                        // the per-vehicle one (motor_id === vehicle.id) and vanishes
                        // from the display while still counting in the rated premium.
                        'specifiedItems' => $covSpecifiedItems
                            ->filter(fn($s) => empty($s->motor_id) || $vehicleRows->isEmpty())
                            ->map(fn($s) => [
                                'id'             => $s->id,
                                'name'           => $s->item_name ?? 'Item #' . $s->specified_coverage_id,
                                'sumInsured'     => $s->sum_insured,
                                'rate'           => $s->rate,
                                'calculatedValue'=> $s->calculated_value,
                            ])->values(),
                    ];
                }),
                'riskAddresses' => $grouped->keys()->map(function ($raId) use ($grouped) {
                    $first = $grouped[$raId]->first();
                    return ['id' => $raId, 'name' => $first->riskAddressName, 'address' => $first->riskAddress];
                })->values(),
            ]);
        }

        // MIS / other products — original logic
        $policy->load('coverage');
        return response()->json([
            'type' => 'mis',
            'data' => ($policy->coverage ?? collect())->map(fn($c) => [
                'id'       => $c->id,
                'vehicleId'=> $c->vehicle_id,
                'main'     => $c->main,
                'value'    => $c->coverage_value,
                'discount' => $c->discount,
                'type'     => $c->type,
            ]),
        ]);
    }

    /**
     * Lazy tab: policy actions (state history — NEWBUSINESS, ENDORSE, RENEW, etc.)
     */
    public function actions(int $id): JsonResponse
    {
        // Compute once per policy: does any specialist coverage table already
        // have a saved premium? Specialist forms (Marine Cargo Once-Off/Open,
        // Marine D&O, Machinery Breakdown, MM/PI/Travel/EAR/CAR/PAR) write
        // premium directly to their own tables on save — separately from
        // policy_actions.premium, which is only populated when the user clicks
        // Rate. The V2 Quote Sheet button gates on selected.premium > 0, so
        // for these covers the button stays disabled even after the operator
        // saved the form. Surface this flag so the frontend can also unlock
        // the button when specialist data is on disk.
        $hasSpecialistPremium = false;
        $specTables = [
            'ear_coverages'                      => ['total_premium', 'section1_total_premium', 'section3_total_premium'],
            'car_coverages'                      => ['section1_total_premium', 'section2_total_premium', 'section3_total_premium'],
            'par_coverages'                      => ['total_premium', 'section2_total_premium'],
            'professional_indemnity_coverages'   => ['premium'],
            'medical_malpractice_coverages'      => ['annual_premium', 'premium'],
            'marine_cargo_once_off_coverages'    => ['premium'],
            'marine_cargo_open_coverages'        => ['premium'],
            'marine_directors_officers_coverages'=> ['premium', 'annual_premium'],
            'machinery_breakdown_coverages'      => ['premium'],
            'medical_evacuation_coverages'       => ['premium'],
            'commercial_crime_coverages'         => ['premium'],
            'environmental_liability_coverages'  => ['premium'],
            'bonds_coverages'                    => ['premium'],
            'travel_coverages'                   => ['total'],
        ];
        foreach ($specTables as $table => $cols) {
            try {
                if (!\Schema::hasTable($table)) continue;
                $tableCols = \Schema::getColumnListing($table);
                $rows = DB::table($table)->where('policy_id', $id)->get();
                foreach ($rows as $row) {
                    foreach ($cols as $col) {
                        if (in_array($col, $tableCols, true) && (float) ($row->{$col} ?? 0) > 0) {
                            $hasSpecialistPremium = true;
                            break 3;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Best-effort flag — never break the actions endpoint over it
            }
        }

        // Latest completed V2 quote PDF per action — V2's async pipeline
        // (GenerateQuotationPdfJob) intentionally does NOT write back to
        // policy_actions.quotation_doc; the canonical store is v2_pdf_jobs.
        // Pre-fetch one MAX(id) per action_id so each action row can carry
        // the download URL without N+1 queries.
        $latestV2JobByAction = DB::connection('mysql_system')->table('v2_pdf_jobs')
            ->where('policy_id', $id)
            ->where('status', 'completed')
            ->whereNotNull('action_id')
            ->whereNotNull('file_name')
            // Quote sheets only. A Policy Document is written to the same
            // table with document_title = 'POLICY DOCUMENT', and MAX(id) per
            // action was handing that row's id to lastV2QuoteUrl — so
            // "Download Quote Sheet" served the schedule PDF whenever a
            // Policy Doc had been generated after the quote. Anything else
            // (NULL, or a legacy title) still counts as a quote sheet.
            ->where(function ($q) {
                $q->whereNull('document_title')
                  ->orWhere('document_title', '!=', 'POLICY DOCUMENT');
            })
            ->select('action_id', DB::raw('MAX(id) as job_id'))
            ->groupBy('action_id')
            ->pluck('job_id', 'action_id');

        $actionRows = DB::table('policy_actions')
            ->where('policy_id', $id)
            ->whereNull('deleted_at')
            ->orderBy('id', 'asc')
            ->get();

        // ── Action-wise premium frequency ─────────────────────────────────
        //
        // The frequency belongs to the TRANSACTION, not to the policy:
        // policy_actions.current_frequency_id is what the operator had
        // selected on that action (EditWizard/AddTransaction stamp it).
        // policies.premium_freq only ever holds the most recent edit, so
        // printing it against every row labelled a Quarterly NEWBUSINESS
        // "Annual" the moment an ANNIVERSARY-RENEW switched frequency.
        //
        // A NULL stamp means "unchanged" (cron-created RENEWs and every row
        // written before the column existed leave it NULL), so carry the last
        // stamped value forward along the (effective_from, id) chronology.
        // Resolved in-memory off the rows already fetched, so the whole tab
        // still costs one query rather than one per action.
        $storedPolicyFreq = DB::table('policies')->where('id', $id)->value('premium_freq');
        $freqByAction     = [];
        $carriedFreq      = null;
        foreach ($actionRows->sortBy(fn ($a) => sprintf('%s|%012d', $a->effective_from ?? '', (int) $a->id)) as $row) {
            if ((int) ($row->current_frequency_id ?? 0) > 0) {
                $carriedFreq = (int) $row->current_frequency_id;
            }
            $freqByAction[$row->id] = $carriedFreq;
        }
        // Policy-level frequency = the authoritative policies.premium_freq column.
        $policyFreqId    = (int) $storedPolicyFreq > 0 ? (int) $storedPolicyFreq : null;
        $policyFreqLabel = $policyFreqId
            ? \AlphaDirect\Models\PolicyAction::frequencyLabel($policyFreqId)
            : null;

        $actions = $actionRows
            ->map(function ($a) use ($id, $hasSpecialistPremium, $latestV2JobByAction, $freqByAction, $storedPolicyFreq) {
                $lastJobId = $latestV2JobByAction->get($a->id);
                $lastV2QuoteUrl = $lastJobId
                    ? url("/api/v1/policies/{$id}/download-quote-pdf/{$lastJobId}")
                    : null;
                $actionFreq = $freqByAction[$a->id]
                    ?? ((int) $storedPolicyFreq > 0 ? (int) $storedPolicyFreq : null);
                return [
                    'id' => $a->id,
                    'transactionType' => $a->transaction_type,
                    'status' => $a->status,
                    'policyQuoteNo' => $a->policy_quote_no,
                    'premium' => $a->premium,
                    'hasSpecialistPremium' => $hasSpecialistPremium,
                    // Surface the rated annual baseline alongside `premium` (which
                    // the rate engine writes as the annual for full-term actions
                    // and as the pro-rata charge for endorsements). Total Coverage
                    // Premium UI reads this so it matches the rated figure across
                    // both flows. Falls back to `premium` for actions written
                    // before the migration added the column.
                    'annualPremium' => $a->annual_premium ?? $a->premium,
                    'effectiveFrom' => $a->effective_from,
                    'effectiveTo' => $a->effective_to,
                    // Frequency as at THIS action. `currentFrequencyId` is the
                    // raw stamp (NULL = unchanged by this transaction);
                    // `frequencyId`/`frequencyLabel` are the resolved value the
                    // UI prints for the selected action.
                    'currentFrequencyId' => $a->current_frequency_id,
                    'frequencyId'    => $actionFreq,
                    'frequencyLabel' => $actionFreq
                        ? \AlphaDirect\Models\PolicyAction::frequencyLabel($actionFreq)
                        : null,
                    'transactionReason' => $a->transaction_reason,
                    'note' => $a->note,
                    'transactionDate' => $a->transaction_date,
                    'createdAt' => $a->created_at,
                    // Legacy V1 columns — still surfaced for any policy whose
                    // PDF was generated via the legacy synchronous DomPDF path.
                    'quotationDoc' => $a->quotation_doc ?? null,
                    'quotationDocUrl' => $a->quotation_doc ? $this->cdnUrl($a->quotation_doc) : null,
                    'rateDoc' => $a->rate_doc ?? null,
                    'rateDocUrl' => $a->rate_doc ? $this->cdnUrl($a->rate_doc) : null,
                    // V2 async PDF — latest completed v2_pdf_jobs row for this
                    // action. Frontend renders the "Last V2 Quote" download
                    // button when this is non-null.
                    'lastV2QuoteJobId' => $lastJobId,
                    'lastV2QuoteUrl'   => $lastV2QuoteUrl,
                ];
            });

        $current = $actions->last();

        // NTU eligibility flag: a NEWBUSINESS QUOTE can only be marked
        // Not Taken Up while no ledger/payment exists on the policy.
        // Once money has moved the operator must CANCEL instead — mirrors
        // graphiteBWV8 EditWizard::$hasFinancialActivity.
        $hasFinancialActivity = DB::table('policy_ledger')
                ->where('policy_id', $id)
                ->whereNull('deleted_at')->exists()
            || DB::table('payment_transactions')
                ->where('policy_id', $id)
                ->whereNull('deleted_at')->exists();

        return response()->json([
            'current' => $current,
            'history' => $actions,
            'hasFinancialActivity' => $hasFinancialActivity,
            // Policy-level frequency (policies.premium_freq) — printed next to
            // the selected action's own frequency when the two differ.
            'policyFrequencyId'    => $policyFreqId,
            'policyFrequencyLabel' => $policyFreqLabel,
        ]);
    }

    /**
     * Set the premium frequency ON ONE ACTION — and optionally push the same
     * value to policies.premium_freq.
     *
     * Super Admin only (gated at the route). This is the repair tool for the
     * stamps EditWizard used to corrupt: it wrote the outgoing frequency over
     * every action on/before the edited one, and re-stamped whatever action was
     * open on every save, so a full-year NEWBUSINESS could end up marked
     * Quarterly while its 3-month ANNIVERSARY-RENEW was marked Annual (policy
     * 129521). Both write paths are fixed, but existing rows still need setting
     * by hand and there is no SQL access in the ops portal.
     *
     * `apply_to_policy` is opt-in and deliberately separate: policies.premium_freq
     * drives invoicing and the renew cycles, so moving it is a bigger decision
     * than correcting one action's label.
     */
    public function updateActionFrequency(Request $request, int $id, int $actionId): JsonResponse
    {
        $data = $request->validate([
            // 1 Monthly, 2 Three Installments, 3 Annual, 4 Semiannual,
            // 5 Quarterly, 6 Manual Input — see PolicyAction::frequencyLabel().
            'frequency_id'     => 'required|integer|in:1,2,3,4,5,6',
            'apply_to_policy'  => 'sometimes|boolean',
        ]);

        $policy = Policy::findOrFail($id);
        $action = \AlphaDirect\Models\PolicyAction::where('policy_id', $id)
            ->where('id', $actionId)
            ->first();

        if (!$action) {
            return response()->json(['error' => 'Policy action not found for this policy.'], 404);
        }

        $freq          = (int) $data['frequency_id'];
        $applyToPolicy = (bool) ($data['apply_to_policy'] ?? false);
        $previousFreq  = $action->current_frequency_id === null
            ? null
            : (int) $action->current_frequency_id;
        $previousPolicyFreq = $policy->premium_freq === null ? null : (int) $policy->premium_freq;

        // Saved through the model (not a mass update) so the Auditable trait
        // records who changed the frequency and from what.
        $action->current_frequency_id = $freq;
        $action->save();

        if ($applyToPolicy && $previousPolicyFreq !== $freq) {
            $policy->premium_freq = $freq;
            $policy->save();
        }

        Log::info('Action frequency updated', [
            'policy_id'      => $id,
            'action_id'      => $actionId,
            'from'           => $previousFreq,
            'to'             => $freq,
            'applied_to_policy' => $applyToPolicy,
            'policy_freq_from'  => $previousPolicyFreq,
            'user_id'        => auth()->id(),
        ]);

        $label = \AlphaDirect\Models\PolicyAction::frequencyLabel($freq);

        return response()->json([
            'message' => $applyToPolicy
                ? "Frequency set to {$label} on this transaction and on the policy."
                : "Frequency set to {$label} on this transaction.",
            'actionId'            => $actionId,
            'frequencyId'         => $freq,
            'frequencyLabel'      => $label,
            'appliedToPolicy'     => $applyToPolicy,
            'policyFrequencyId'   => (int) $policy->fresh()->premium_freq,
        ]);
    }

    /**
     * Lazy tab: claims (paginated)
     */
    public function claims(int $id): JsonResponse
    {
        $policy = Policy::findOrFail($id);

        $claims = $policy->claim()
            ->select('id', 'claim_number', 'claim_type', 'status', 'created_at')
            ->orderBy('id', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => ClaimResource::collection($claims),
            'meta' => [
                'total'        => $claims->total(),
                'per_page'     => $claims->perPage(),
                'current_page' => $claims->currentPage(),
                'last_page'    => $claims->lastPage(),
            ],
        ]);
    }

    /**
     * Lazy tab: transactions (paginated)
     */
    public function transactions(int $id): JsonResponse
    {
        $policy = Policy::select('id', 'policyNumber')->findOrFail($id);

        $transactions = DB::table('payment_transactions')
            ->where('policyNumber', $policy->policyNumber)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'CANCELLED')
            ->whereNull('reveral_transaction_id')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'data' => collect($transactions->items())->map(function ($t) {
                // Resolve paymentLoggedBy user name
                $addedBy = null;
                if ($t->paymentLoggedBy) {
                    $user = DB::table('users')->where('id', $t->paymentLoggedBy)->select('firstName', 'lastName')->first();
                    if ($user) $addedBy = trim($user->firstName . ' ' . $user->lastName);
                }

                return [
                    'id'              => $t->id,
                    'policyNumber'    => $t->policyNumber ?? null,
                    'referenceNumber' => $t->referenceNumber ?? null,
                    'paymentMethod'   => $t->paymentMethod ?? null,
                    'amount'          => $t->amount ?? null,
                    'paymentDate'     => $t->paymentDate ?? null,
                    'settlementDate'  => $t->created_at
                        ? \Carbon\Carbon::parse($t->created_at)->format('Y-m-d H:i')
                        : null,
                    'installmentsPaid'=> $t->numberOfInstalmentsPaid ?? null,
                    'note'            => $t->note ?? null,
                    'status'          => $t->status ?? null,
                    'cashRecipient'   => $t->cashRecipient ?? null,
                    'addedBy'         => $addedBy,
                    'isReversed'      => ($t->CompanyRef === 'Reversed' || ($t->is_reverse ?? 0) == 1),
                    'isRefunded'      => ($t->is_refund ?? 0) == 1,
                ];
            }),
            'meta' => [
                'total'        => $transactions->total(),
                'per_page'     => $transactions->perPage(),
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
            ],
        ]);
    }

    /**
     * Lazy tab: risk addresses — skip if product has no risk-address coverages
     */
    public function riskAddresses(int $id): JsonResponse
    {
        $policy = Policy::select('id', 'product_id')->findOrFail($id);

        // Risk addresses are versioned per policy_action — every endorsement
        // / renewal copies them via PolicyAction::newPolicyAction. Without an
        // action filter the response returns the cumulative set across all
        // actions and the operator sees duplicate "Private bag 17" rows.
        // Default to the latest action when the caller doesn't pass one;
        // accept an explicit ?action_id= override so the wizard can target
        // a specific endorsement.
        $requestedActionId = request()->input('action_id');
        $actionId = $requestedActionId ?: DB::table('policy_actions')
            ->where('policy_id', $id)
            ->orderByDesc('id')
            ->value('id');

        $query = RiskAddress::where('policy_id', $id);

        if (request()->has('term_id') && request()->input('term_id')) {
            $query->where('term_id', request()->input('term_id'));
        }
        if ($actionId) {
            $query->where('action_id', $actionId);
        }

        // Get records with relations
        $addresses = $query
            ->with(['state', 'city'])
            ->orderBy('id')
            ->get();

        // Fallback: legacy policies whose latest action has no risk_address
        // rows (drift from older imports). Show de-duplicated cumulative set
        // so the dropdown isn't empty.
        if ($addresses->isEmpty() && !$requestedActionId) {
            $addresses = RiskAddress::where('policy_id', $id)
                ->with(['state', 'city'])
                ->orderByDesc('id')
                ->get()
                ->unique(fn($r) => trim(($r->address_name ?? '') . '|' . ($r->physical_address ?? '') . '|' . ($r->risk_state ?? '') . '|' . ($r->risk_city ?? '')))
                ->values();
        }

        return response()->json([
            'data' => $addresses->map(fn($r) => [
                'id'             => $r->id,
                'addressName'    => $r->address_name ?? null,
                'address'        => $r->physical_address ?? $r->address_name ?? null,
                'physical_address' => $r->physical_address ?? null,
                'risk_state'     => $r->risk_state ?? null,
                'risk_city'      => $r->risk_city ?? null,
                'state'          => $r->state?->name ?? null,
                'city'           => $r->city?->name ?? null,
                'zipCode'        => $r->zip_code ?? null,
                'extension'      => $r->extension ?? null,
                'occupation'     => $r->occupation ?? null,
                'town_class'     => $r->town_class ?? null,
                'risk_class'     => $r->risk_class ?? null,
                'iso_rcv'        => $r->iso_rcv ?? null,
                'year_built'     => $r->year_built ?? null,
                'area'           => $r->area ?? null,
                'structure_type' => $r->structure_type ?? null,
                'const_type'     => $r->const_type ?? null,
                'distance_to_water' => $r->distance_to_water ?? null,
                'distance_to_fire' => $r->distance_to_fire ?? null,
                'distance_to_hydrant' => $r->distance_to_hydrant ?? null,
                'usage'          => $r->usage ?? null,
                'occupancy_type' => $r->occupancy_type ?? null,
                'central_fire'   => $r->central_fire ?? null,
                'central_burglar'=> $r->central_burglar ?? null,
                'gated_community'=> $r->gated_community ?? null,
                'automatic'      => $r->automatic ?? null,
                'company_id'     => $r->company_id ?? null,
                'lat'            => $r->lat ?? null,
                'lng'            => $r->lng ?? null,
            ]),
        ]);
    }

    /**
     * Lazy: KYC documents with file paths.
     * Returns the per-tier V8 doc set (DOMG → 7 individual docs, COMG → 13
     * corporate docs, MIS → legacy individual+motor docs). Tier is decided
     * from the policy-number prefix because product_id alone can't separate
     * DOMG vs COMG when the same product (e.g. 19) is sold to both.
     */
    public function kycDocuments(int $id): JsonResponse
    {
        $policy = Policy::select('id', 'customer_id', 'product_id', 'has_vehicle', 'policyNumber')->findOrFail($id);

        // DomCom policies store KYC in customer_kyc_dom_com (uploadKycDocuments
        // writes there when the table exists). Legacy products use customer_kyc.
        // Read BOTH so uploaded docs surface regardless of which table the
        // writer chose — newer DomCom row wins per-column, legacy fills gaps.
        $kycDomCom = null;
        if (\Schema::hasTable('customer_kyc_dom_com')) {
            $kycDomCom = DB::table('customer_kyc_dom_com')
                ->where('customer_id', $policy->customer_id)
                ->orderBy('id', 'desc')
                ->first();
        }
        $kycLegacy = KYC::where('customer_id', $policy->customer_id)->first();

        // Don't 204 the FE when neither KYC row exists yet — a brand-new
        // policy with zero uploads still needs the tier-aware empty doc
        // cards so reviewers can click the pencil and upload for the
        // first time. The downstream $pullCol() handles null rows fine
        // (returns null per column, hasFile=false), so we fall through.

        // Tier resolution: policy-number prefix is the source of truth.
        // Fall back to product_id when prefix is missing (legacy/manual rows).
        $prefix = strtoupper(substr((string) ($policy->policyNumber ?? ''), 0, 4));
        $tier = match (true) {
            $prefix === 'DOMG' => 'DOMG',
            $prefix === 'COMG' => 'COMG',
            \AlphaDirect\Support\KycDomComProducts::isDomestic($policy->product_id)   => 'DOMG',
            \AlphaDirect\Support\KycDomComProducts::isCommercial($policy->product_id) => 'COMG',
            default            => 'MIS',
        };

        // Per-tier doc catalogue. Each entry lists the candidate columns
        // (legacy first, modern second) so old rows still surface.
        $domgDocs = [
            'kyc_form'               => ['columns' => ['kyc_form'],                         'label' => 'KYC form',                'status' => 'kyc_form_status'],
            'data_protection_form'   => ['columns' => ['data_protection_form'],             'label' => 'Data protection form',    'status' => 'data_protection_form_status'],
            'omang_front'            => ['columns' => ['omang', 'omang_front'],             'label' => 'Omang ID Front',          'status' => 'omangFrontStatus'],
            'omang_back'             => ['columns' => ['omangBack', 'omang_back'],          'label' => 'Omang ID Back',           'status' => 'omangBackStatus'],
            'proof_of_address_image' => ['columns' => ['proof_residence', 'proof_of_address_image'], 'label' => 'Proof of Residence', 'status' => 'proof_residenceStatus'],
            'proof_of_income'        => ['columns' => ['proof_income'],                     'label' => 'Proof Of Income',         'status' => 'proof_incomeStatus'],
            'passport_front_image'   => ['columns' => ['passport', 'passport_front_image'], 'label' => 'Passport',                'status' => 'passportStatus'],
        ];

        $comgDocs = [
            'certificate_of_incorporation' => ['columns' => ['certificate_of_incorporation'], 'label' => 'Certificate of Incorporation/Registration',                       'status' => 'certificate_of_incorporation_status'],
            'extract_controllers'          => ['columns' => ['extract_controllers'],          'label' => 'Extract controllers and ownership structure',                     'status' => 'extract_controllers_status'],
            'kyc_form'                     => ['columns' => ['kyc_form'],                     'label' => 'KYC form',                                                        'status' => 'kyc_form_status'],
            'data_protection_form'         => ['columns' => ['data_protection_form'],         'label' => 'Data protection form',                                            'status' => 'data_protection_form_status'],
            'resolution'                   => ['columns' => ['resolution'],                   'label' => 'Resolution',                                                      'status' => 'resolution_status'],
            'proof_business_address'       => ['columns' => ['proof_business_address'],       'label' => 'Proof of business address',                                       'status' => 'proof_business_address_status'],
            'proof_residential_address'    => ['columns' => ['proof_residential_address'],    'label' => 'Proof of residential address for Directors and Shareholders',    'status' => 'proof_residential_address_status'],
            'directors_id_front'           => ['columns' => ['directors_id_front'],           'label' => 'Directors ID front',                                              'status' => 'directors_id_front_status'],
            'directors_id_back'            => ['columns' => ['directors_id_back'],            'label' => 'Directors ID back',                                               'status' => 'directors_id_back_status'],
            'directors_passport'           => ['columns' => ['directors_passport'],           "label" => "Director's passport",                                             'status' => 'directors_passport_status'],
            'shareholders_id_front'        => ['columns' => ['shareholders_id_front'],        'label' => 'Shareholders ID front',                                           'status' => 'shareholders_id_front_status'],
            'shareholders_id_back'         => ['columns' => ['shareholders_id_back'],         'label' => 'Shareholders ID back',                                            'status' => 'shareholders_id_back_status'],
            'shareholders_passport'        => ['columns' => ['shareholders_passport'],        'label' => 'Shareholders passport',                                           'status' => 'shareholders_passport_status'],
        ];

        // V8 MIS Customer KYC tab — policyDetails_View.blade (line ~1183)
        // shows 8 doc slots: Driving License, Omang Front/Back, Proof of
        // Residence, Proof of Income, Passport, Data Protection Consent,
        // Canceled document. Banking / Vehicle Registration / Passport
        // Back / Driving License Back are NOT on the KYC tab in V8 —
        // they live under separate banking / vehicles screens. Drop
        // them here so V2 mirrors V8's MIS screen exactly.
        $misDocs = [
            'drivers_license_front_image'=> ['columns' => ['driving_license', 'drivers_license_front_image'], 'label' => 'Driving License', 'status' => 'driving_licenseStatus'],
            'omang_front'                => ['columns' => ['omang', 'omang_front'],                      'label' => 'Omang ID Front',             'status' => 'omangFrontStatus'],
            'omang_back'                 => ['columns' => ['omangBack', 'omang_back'],                   'label' => 'Omang ID Back',              'status' => 'omangBackStatus'],
            'proof_of_address_image'     => ['columns' => ['proof_residence', 'proof_of_address_image'], 'label' => 'Proof of Residence',         'status' => 'proof_residenceStatus'],
            'proof_of_income'            => ['columns' => ['proof_income'],                              'label' => 'Proof Of Income',            'status' => 'proof_incomeStatus'],
            'passport_front_image'       => ['columns' => ['passport', 'passport_front_image'],          'label' => 'Passport',                   'status' => 'passportStatus'],
            // Banking documents (Bank Statement / Debit Authorization Form)
            // are NOT on the KYC tab — they live on the Banking Details tab
            // and are stored on customer_banking (see PolicyCreateController
            // banking-documents endpoints).
            // V8 capitalisation kept verbatim ("Canceled_document") —
            // production schema uses that exact column name.
            'data_protection_consent'    => ['columns' => ['data_protection_consent'],                   'label' => 'Data Protection Consent',    'status' => null],
            'canceled_document'          => ['columns' => ['Canceled_document', 'canceled_document'],    'label' => 'Canceled document',          'status' => null],
        ];

        $allDocs = match ($tier) {
            'DOMG' => $domgDocs,
            'COMG' => $comgDocs,
            default => $misDocs,
        };

        // Helper: pull column value from whichever of the two rows has it
        // (DomCom wins, then legacy). Handles both stdClass (DomCom row from
        // DB::table) and the KYC model (property access on both works).
        $pullCol = function (string $col) use ($kycDomCom, $kycLegacy) {
            if ($kycDomCom && !empty($kycDomCom->{$col} ?? null)) return $kycDomCom->{$col};
            if ($kycLegacy && !empty($kycLegacy->{$col} ?? null)) return $kycLegacy->{$col};
            return null;
        };

        $documents = [];
        foreach ($allDocs as $field => $meta) {
            $path = null;
            foreach ($meta['columns'] as $col) {
                $val = $pullCol($col);
                if (!empty($val)) { $path = $val; break; }
            }
            $documents[] = [
                'field'    => $field,
                'label'    => $meta['label'],
                'hasFile'  => !empty($path),
                'url'      => $path ? $this->cdnUrl($path) : null,
                'status'   => $meta['status'] ? $pullCol($meta['status']) : null,
            ];
        }

        $compliance = $pullCol('compliance');
        return response()->json([
            'data' => [
                'tier'            => $tier, // 'DOMG' | 'COMG' | 'MIS'
                'compliance'      => $compliance,
                'complianceLabel' => match((int) ($compliance ?? -1)) {
                    0       => 'KYC Verification Pending',
                    1       => 'KYC Compliant',
                    3       => 'No ID - No Documents',
                    default => 'KYC Non Compliant',
                },
                'documents'       => $documents,
            ],
        ]);
    }

    /**
     * Lazy tab: Logs — SMS/Email logs + SMS logs + activity audit logs for this policy
     */
    public function logs(int $id): JsonResponse
    {
        $policy = Policy::select('id', 'policyNumber')->findOrFail($id);
        $policyNumber = $policy->policyNumber;

        // SMS/Email logs — query by policyNumber only (matches production)
        $smsEmailLogs = DB::table('sms_email_log')
            ->where('policyNumber', $policyNumber)
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get()
            ->map(function ($log) {
                // Extract HTML from serialized PHP content for email preview
                $htmlContent = null;
                if ($log->type === 'Email' && !empty($log->content)) {
                    try {
                        // M4 (pentest) — restrict to the ONLY class legitimately
                        // stored here (the serialized SendMail event, see
                        // SendMailFired). Blocks object-injection gadget chains
                        // while still reconstructing the email preview.
                        $unserialized = @unserialize($log->content, ['allowed_classes' => [\AlphaDirect\Events\SendMail::class]]);
                        if ($unserialized && is_object($unserialized) && isset($unserialized->htmlContent)) {
                            $htmlContent = $unserialized->htmlContent;
                        } elseif (is_string($log->content) && stripos($log->content, '<html') !== false) {
                            // Already raw HTML
                            $htmlContent = $log->content;
                        }
                    } catch (\Throwable $e) {
                        // If unserialize fails, check if it's raw HTML
                        if (stripos($log->content, '<html') !== false || stripos($log->content, '<body') !== false) {
                            $htmlContent = $log->content;
                        }
                    }
                }

                return [
                    'id'          => $log->id,
                    'type'        => $log->type ?? 'SMS',
                    'recipient'   => ($log->type === 'Email') ? ($log->to_email ?? null) : ($log->to_cellphone ?? null),
                    'message'     => $log->message ? strip_tags($log->message) : null,
                    'htmlContent' => $htmlContent,
                    'messageId'   => $log->message_id ? strip_tags($log->message_id) : null,
                    'hook'        => $log->hook ?? null,
                    'status'      => $log->status ?? null,
                    'createdAt'   => $log->created_at
                        ? \Carbon\Carbon::parse($log->created_at)->format('Y-m-d h:i A')
                        : null,
                ];
            });

        // SMS logs from sms_logs table — query by policyNumber only
        $smsLogs = DB::table('sms_logs')
            ->where('policyNumber', $policyNumber)
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get()
            ->map(fn($log) => [
                'id'        => $log->id,
                'type'      => 'SMS',
                'recipient' => $log->cellphone ?? null,
                'message'   => $log->sms_text ? strip_tags($log->sms_text) : null,
                'messageId' => null,
                'hook'      => null,
                'status'    => $log->sms_status == '1' ? 'Sent' : ($log->sms_status == '0' ? 'Failed' : ($log->sms_status ?? null)),
                'createdAt' => $log->created_at
                    ? \Carbon\Carbon::parse($log->created_at)->format('Y-m-d h:i A')
                    : null,
            ]);

        // Merge and sort SMS/email logs
        $allSmsEmailLogs = $smsEmailLogs->concat($smsLogs)
            ->sortByDesc('id')
            ->values()
            ->take(200);

        $activityLogs = $this->buildActivityLogRows($id, $policyNumber, 200);

        return response()->json([
            'data' => [
                'smsEmailLogs' => $allSmsEmailLogs,
                'activityLogs' => $activityLogs,
            ],
        ]);
    }

    /**
     * GET /v1/policies/{id}/logs/export
     *
     * Streams every activity-log entry for this policy (no 200-row cap,
     * unlike the on-screen tab) as an .xlsx — audit/investigation needs the
     * full history, not just the latest page.
     */
    public function logsExport(int $id)
    {
        $policy = Policy::select('id', 'policyNumber')->findOrFail($id);

        $rows = $this->buildActivityLogRows($id, $policy->policyNumber, null);

        $fileName = 'PolicyActivityLog_' . $policy->policyNumber . '_' . Carbon::now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new \AlphaDirect\Exports\PolicyActivityLogExport($rows), $fileName);
    }

    /**
     * Builds the merged, newest-first activity log (OwenIt audit diffs +
     * Spatie milestone entries) for a policy. Shared by the Logs tab
     * (`limit` = 200, matching the UI page) and the export endpoint
     * (`limit` = null, the full history).
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function buildActivityLogRows(int $id, string $policyNumber, ?int $limit = 200): \Illuminate\Support\Collection
    {
        // Activity/audit logs from audits table (OwenIt) — raw data
        $auditsQuery = Audits::where('policy_number', $policyNumber)
            ->select('id', 'event', 'auditable_id', 'auditable_type', 'old_values', 'new_values', 'user_id', 'user_type', 'tags', 'url', 'ip_address', 'created_at')
            ->orderBy('id', 'desc');
        if ($limit !== null) $auditsQuery->limit($limit);
        $rawAudits = $auditsQuery->get();

        // Build a fallback user-lookup from each auditable record's
        // updated_by / created_by / added_by column. Old audit rows written
        // before sanctum was wired into OwenIt's UserResolver have user_id
        // NULL, so without this fallback every historical action shows
        // "System". Batch by auditable_type to avoid N+1.
        $fallbackUserIdByAudit = [];
        $auditsByType = $rawAudits->groupBy('auditable_type');
        foreach ($auditsByType as $auditableType => $group) {
            if (empty($auditableType)) continue;
            $ids = $group->pluck('auditable_id')->filter()->unique()->values()->all();
            if (empty($ids)) continue;
            $model = null;
            try { $model = app($auditableType); } catch (\Throwable $e) { $model = null; }
            if (!$model || !method_exists($model, 'getTable')) continue;
            $table = $model->getTable();
            $col = null;
            foreach (['updated_by', 'created_by', 'added_by', 'issued_by'] as $c) {
                if (\Schema::hasColumn($table, $c)) { $col = $c; break; }
            }
            if (!$col) continue;
            $rows = DB::table($table)->whereIn('id', $ids)->pluck($col, 'id');
            foreach ($group as $a) {
                if (!empty($rows[$a->auditable_id])) {
                    $fallbackUserIdByAudit[$a->id] = $rows[$a->auditable_id];
                }
            }
        }

        // Preload all candidate user names in one query (union of audit.user_id
        // and fallback ids) — keeps the map cheap even for 200 rows.
        $allUserIds = $rawAudits->pluck('user_id')->filter()
            ->merge(collect($fallbackUserIdByAudit)->values())
            ->filter()->unique()->values()->all();
        $userNames = empty($allUserIds) ? collect() : DB::table('users')
            ->whereIn('id', $allUserIds)
            ->get(['id', 'firstName', 'lastName'])
            ->keyBy('id');

        $activityLogs = $rawAudits->map(function ($a) use ($fallbackUserIdByAudit, $userNames) {
            // Primary: OwenIt-resolved user_id on the audit row.
            $uid = $a->user_id ?: ($fallbackUserIdByAudit[$a->id] ?? null);
            $activityBy = 'System';
            if ($uid && isset($userNames[$uid])) {
                $u = $userNames[$uid];
                $name = trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? ''));
                if ($name !== '') $activityBy = $name;
            } elseif ($a->user_type) {
                $activityBy = class_basename($a->user_type);
            }

            return [
                'id'          => $a->id,
                'activityBy'  => $activityBy,
                'ipAddress'   => $a->ip_address ?? null,
                'doneFrom'    => $a->auditable_type ? class_basename($a->auditable_type) : null,
                'activityTag' => $a->tags ?? null,
                'url'         => $a->url ?? null,
                'oldValues'   => $a->old_values,
                'newValues'   => $a->new_values,
                'activityDone'=> $a->created_at
                    ? \Carbon\Carbon::parse($a->created_at)->format('d/m/Y H:i')
                    : null,
                // Raw epoch for merging with the milestone log below — stripped
                // before the response is sent.
                '_ts'         => $a->created_at ? \Carbon\Carbon::parse($a->created_at)->timestamp : 0,
            ];
        });

        // ── Human-readable milestone logs (Spatie activity_log) ──────────────
        // The lifecycle writes plain-language entries via activity()->log()
        // throughout PolicyCreateController — "Policy Issued", "Unissued — …",
        // "New ENDORSE: …", "Lapsed", "Deleted … QUOTE action", "Invoice
        // generated", "Policy Document generated", etc. They were never shown
        // here because this tab only read the OwenIt `audits` table. Merge them
        // in so every meaningful action appears, not just raw field diffs.
        // Subject is always the Policy model (performedOn($policy)).
        $milestoneLogs = collect();
        try {
            if (\Schema::hasTable('activity_log')) {
                $milestoneQuery = DB::table('activity_log')
                    ->where('subject_id', $id)
                    ->where('subject_type', \AlphaDirect\Policy::class)
                    ->orderBy('id', 'desc');
                if ($limit !== null) $milestoneQuery->limit($limit);
                $acts = $milestoneQuery->get();

                $causerIds = $acts->pluck('causer_id')->filter()->unique()->values()->all();
                $causerNames = empty($causerIds) ? collect() : DB::table('users')
                    ->whereIn('id', $causerIds)
                    ->get(['id', 'firstName', 'lastName'])
                    ->keyBy('id');

                $milestoneLogs = $acts->map(function ($a) use ($causerNames) {
                    $activityBy = 'System';
                    if ($a->causer_id && isset($causerNames[$a->causer_id])) {
                        $u = $causerNames[$a->causer_id];
                        $name = trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? ''));
                        if ($name !== '') $activityBy = $name;
                    }
                    // Spatie stores extra context in properties (JSON). Show it
                    // in the New Data column when it carries anything useful.
                    $props = null;
                    if (!empty($a->properties)) {
                        $decoded = json_decode($a->properties, true);
                        if (is_array($decoded) && !empty($decoded)) $props = $decoded;
                    }
                    return [
                        'id'          => 'act-' . $a->id, // string key avoids clashing with audit ids
                        'activityBy'  => $activityBy,
                        'ipAddress'   => null,
                        'doneFrom'    => $a->log_name ?: 'Activity',
                        'activityTag' => $a->description,
                        'url'         => null,
                        'oldValues'   => null,
                        'newValues'   => $props,
                        'activityDone'=> $a->created_at
                            ? \Carbon\Carbon::parse($a->created_at)->format('d/m/Y H:i')
                            : null,
                        '_ts'         => $a->created_at ? \Carbon\Carbon::parse($a->created_at)->timestamp : 0,
                    ];
                });
            }
        } catch (\Throwable $e) {
            \Log::warning('logs(): activity_log merge failed: ' . $e->getMessage());
        }

        // Merge audit diffs + milestones, newest first, then drop the sort key.
        return $activityLogs->concat($milestoneLogs)
            ->sortByDesc('_ts')
            ->values()
            ->map(function ($r) {
                unset($r['_ts']);
                return $r;
            });
    }

    /**
     * Lazy tab: Full ledger with 4 views (Account, Receivable, Invoicing, Sub Ledger)
     */
    public function ledger(int $id): JsonResponse
    {
        $policy = Policy::select('id', 'customer_id', 'product_id', 'policyNumber')->findOrFail($id);

        // Customer name
        $customer = DB::table('customer')
            ->where('id', $policy->customer_id)
            ->select('firstName', 'lastName')
            ->first();
        $customerName = $customer ? trim($customer->firstName . ' ' . $customer->lastName) : null;

        $fmt = fn($v) => number_format((float)($v ?? 0), 2, '.', ',');

        // Invoicing status is DISPLAY-derived from the billing date (debit-order
        // model): an invoice whose due/invoice date has already passed is shown
        // as "Paid" (premium auto-collected once that date arrives), and one still
        // upcoming stays "Pending". Explicit terminal states (Reversed/Cancelled)
        // and an already-reconciled "Paid" are preserved. This does NOT modify the
        // stored policy_ledger.status — it only changes what the Invoicing tab
        // shows, so the accounting record is untouched.
        $today = \Carbon\Carbon::today();
        $deriveInvStatus = function ($l) use ($today) {
            $raw = trim((string) ($l->status ?? ''));
            if (in_array($raw, ['Reversed', 'Cancelled', 'Paid'], true)) return $raw;
            // Due Date == invoice date; status follows it: passed → Paid, else Pending.
            $ref = $l->due_date ?: $l->invoice_date ?: $l->accounting_date;
            if ($ref) {
                try { return \Carbon\Carbon::parse($ref)->lt($today) ? 'Paid' : 'Pending'; }
                catch (\Throwable $e) { /* fall through to raw */ }
            }
            return $raw !== '' ? $raw : 'Pending';
        };

        // Optional action scoping — graphiteBWV8 AccountView\Table filters by
        // action_id. When the client passes ?action_id=, restrict to that action;
        // otherwise return the full policy ledger (all actions merged).
        $actionId = request()->query('action_id');

        // DOM/COM products (mirrors generateAccountStatementPdf's $isDomCom).
        $isDomCom = in_array((int) $policy->product_id, [7, 8, 16, 17, 18, 19, 20, 21, 22, 23], true);
        // MIS policies (identified by policy-number prefix; their products
        // aren't in the DOM/COM id list) also take Total Dues from the
        // statement Closing Balance so it matches the Account Statement PDF
        // and the Balance Owing widget.
        $isMis = str_starts_with(strtoupper((string) ($policy->policyNumber ?? '')), 'MIS');

        // Credit notes raised on this policy, keyed to the invoice each one
        // credits, so the Invoicing tab can show WHICH credit note reverses
        // WHICH invoice. Without this the only trace of a credit note is a
        // 'Credit Note' row in Account View carrying the CR number and nothing
        // that ties it back to the invoice it was raised against.
        //
        // Matched on invoice_id first (what the V2 flow stamps) with invoice_no
        // as a fallback — notes raised on the legacy /admin screen recorded only
        // the number. credit_note is a legacy table with no migration of its
        // own, so a missing column must not take the whole ledger endpoint down.
        $cnByInvoiceId = [];
        $cnByInvoiceNo = [];
        // CR number → the invoice number it credits. The Account Statement PDF
        // prints a credit note's reference as "<invoice no>_<CR no>", but the
        // policy_ledger 'Credit Note' row stores invoice_no as NULL (stamping it
        // would make the row show up as an invoice on the Invoicing tab), so the
        // Account View had no way to show WHICH invoice a note reverses — and
        // searching the ledger for the reference off the PDF found nothing.
        $cnRefToInvoiceNo = [];
        // CR number → the date the note was POSTED (policy_ledger.accounting_date,
        // the date the statement prints). credit_note.created_at is only the
        // moment the note was raised, so it disagreed with the statement for any
        // note that was back-dated or later re-dated.
        $cnRefToPostedDate = [];
        // CR number → the amount posted to the ledger for that note, so the
        // Credit Notes tab can show what was reversed without re-deriving it.
        $cnRefToAmount = [];
        // Every POSTED credit note on this policy, in its own right. The
        // Invoicing tab only ever matched a note to the invoice it credits,
        // which left Finance with no single place to answer "how many notes
        // were raised, for which invoice, when, and where is the PDF".
        $creditNotes = [];
        // Credit notes whose invoice has been discarded are treated as if they
        // were never raised — hidden from every tab here and excluded from the
        // statement math. See AccountStatementService::orphanCreditNoteRefs().
        $orphanCreditNotes = \AlphaDirect\Services\AccountStatementService::orphanCreditNoteRefs($id);
        try {
            $cnRows = DB::table('credit_note')->where('policy_id', $id)->orderBy('id')->get();
            $cnRefs = $cnRows->pluck('credit_note_no')->filter()->values()->all();
            if (!empty($cnRefs)) {
                foreach (DB::table('policy_ledger')
                    ->where('policy_id', $id)
                    ->where('trans_type', 'Credit Note')
                    ->whereIn('trans_ref', $cnRefs)
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->get(['trans_ref', 'accounting_date', 'debit', 'credit']) as $lr) {
                    if (!empty($lr->accounting_date)) {
                        $cnRefToPostedDate[(string) $lr->trans_ref] =
                            \Carbon\Carbon::parse($lr->accounting_date)->format('Y-m-d');
                    }
                    // The write path books the reversal as a debit; fall back to
                    // credit for any legacy row that booked it the other way.
                    $cnAmt = $lr->debit ?? null;
                    if ($cnAmt === null || $cnAmt === '') $cnAmt = $lr->credit ?? null;
                    if ($cnAmt !== null && $cnAmt !== '') {
                        $cnRefToAmount[(string) $lr->trans_ref] = $fmt(str_replace(',', '', (string) $cnAmt));
                    }
                }
            }
            foreach ($cnRows as $cn) {
                // status 1 = posted; anything else was never generated.
                if ((int) ($cn->status ?? 1) !== 1) continue;
                // The invoice this note reverses is gone — skip it entirely so
                // it shows on neither the Credit Notes tab nor the Invoicing
                // tab's CR badge, and never lands in $cnRefToInvoiceNo.
                if (in_array((string) ($cn->credit_note_no ?? ''), $orphanCreditNotes, true)) continue;
                // Legacy wrote number_format()'s comma-grouped strings into these
                // columns, and (float) "19,353.60" is 19.0 — strip before casting.
                $num = fn($v) => $v === null || $v === '' ? null : $fmt(str_replace(',', '', (string) $v));
                $entry = [
                    'id'       => (int) $cn->id,
                    'no'       => $cn->credit_note_no ?? null,
                    // Posting date first (what the statement shows), created_at
                    // only as a fallback for a note with no ledger row.
                    'date'     => $cnRefToPostedDate[(string) ($cn->credit_note_no ?? '')]
                        ?? ($cn->created_at ? \Carbon\Carbon::parse($cn->created_at)->format('Y-m-d') : null),
                    'earned'   => $num($cn->earned_premium ?? null),
                    'unearned' => $num($cn->unearned_premium ?? null),
                    'fileUrl'  => !empty($cn->credit_note_file)
                        ? \AlphaDirect\Helper::getCloudFrontURL($cn->credit_note_file)
                        : null,
                ];
                if (!empty($cn->invoice_id)) $cnByInvoiceId[(int) $cn->invoice_id] = $entry;
                if (!empty($cn->invoice_no)) $cnByInvoiceNo[(string) $cn->invoice_no] = $entry;
                // credit_note.invoice_no is written by both the V2 and the
                // legacy path; the invoice_id lookup covers the odd row that
                // carries only the id (same fallback the statement blade uses).
                $creditedInvoiceNo = $cn->invoice_no
                    ?: (!empty($cn->invoice_id)
                        ? DB::table('policy_ledger')->where('id', (int) $cn->invoice_id)->value('invoice_no')
                        : null);
                if (!empty($cn->credit_note_no)) {
                    $cnRefToInvoiceNo[(string) $cn->credit_note_no] = $creditedInvoiceNo;
                }

                // The Credit Notes tab's own row. The credited PERIOD is stored
                // as written — legacy rows carry d/m/Y strings, so a failed
                // parse falls back to the raw value rather than blanking it.
                $cnDate = function ($v) {
                    if (empty($v)) return null;
                    try { return \Carbon\Carbon::parse($v)->format('Y-m-d'); }
                    catch (\Throwable $e) { return (string) $v; }
                };
                // The re-date screen is addressed by INVOICE ledger id, so a
                // note that carries only invoice_no (raised on the legacy /admin
                // screen, which never stamped the id) needs it resolved here or
                // its date cannot be corrected from the tab. Matched on the row
                // that carries the invoiced amount — an invoice number also
                // appears on its VAT and Premium legs, and only the summary row
                // is the one the credit note screen accepts.
                $invoiceLedgerId = !empty($cn->invoice_id) ? (int) $cn->invoice_id : null;
                if ($invoiceLedgerId === null && !empty($creditedInvoiceNo)) {
                    $invoiceLedgerId = DB::table('policy_ledger')
                        ->where('policy_id', $id)
                        ->where('invoice_no', $creditedInvoiceNo)
                        ->where('invoice_amount', '>', 0)
                        ->whereNull('deleted_at')
                        ->orderBy('id')
                        ->value('id');
                    $invoiceLedgerId = $invoiceLedgerId ? (int) $invoiceLedgerId : null;
                }

                $creditNotes[] = $entry + [
                    'invoiceId'  => $invoiceLedgerId,
                    'invoiceNo'  => $creditedInvoiceNo,
                    'createdAt'  => $cnDate($cn->created_at ?? null),
                    'periodFrom' => $cnDate($cn->transaction_effective_date ?? null),
                    'periodTo'   => $cnDate($cn->transaction_end_date ?? null),
                    'noOfDays'   => isset($cn->no_of_days) && $cn->no_of_days !== null ? (int) $cn->no_of_days : null,
                    'amount'     => $cnRefToAmount[(string) ($cn->credit_note_no ?? '')] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('credit note lookup for ledger failed: ' . $e->getMessage());
        }

        // Account View. For DOM/COM the on-screen table must show EXACTLY the
        // data the Export Account Statement (PDF) renders — reversed/refunded/
        // test payments excluded, archive merged, chronological order — so it is
        // built by domComAccountStatementRows() which copies that PDF logic.
        // (The PDF endpoint itself is intentionally left untouched.) Other
        // products keep the legacy Livewire AccountView\Table filter below.
        $accountView = $isDomCom
            ? $this->domComAccountStatementRows($id, $actionId, $customerName, $fmt, $cnRefToInvoiceNo)
            : DB::table('policy_ledger')
            ->where('policy_id', $id)
            ->when($actionId, fn($q) => $q->where('action_id', $actionId))
            // Exclude discarded rows (e.g. invoices voided on un-issue) so they
            // disappear from the view, consistent with the totalDues query below.
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('invoice_file')
                  ->orWhereNotNull('credit')
                  ->orWhere('trans_type', 'Refund')
                  ->orWhere('trans_type', 'Credit Note');
            })
            // Orphaned credit notes (invoice discarded) are dropped here too —
            // the DOM/COM branch above gets this from AccountStatementService.
            ->when(!empty($orphanCreditNotes), fn($q) => $q->where(function ($w) use ($orphanCreditNotes) {
                $w->where('trans_type', '!=', 'Credit Note')
                  ->orWhereNull('trans_ref')
                  ->orWhereNotIn('trans_ref', $orphanCreditNotes);
            }))
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($l) => [
                'id'             => $l->id,
                'accountingDate' => $l->accounting_date ?? null,
                'transType'      => $l->trans_type ?? null,
                'transRef'       => $l->trans_ref ?? null,
                'customerName'   => $customerName,
                'origTrans'      => $l->orig_trans ?? null,
                'unallocated'    => $fmt($l->unallocated),
                'debit'          => $fmt($l->debit),
                'credit'         => $fmt($l->credit),
                'balance'        => $fmt($l->balance),
                'systemDate'     => $l->system_date ?? null,
            ] + $this->creditNoteRefFields($l->trans_type ?? null, $l->trans_ref ?? null, $cnRefToInvoiceNo));

        // Receivable View — credit rows.
        //
        // The tab used to be a bare whereNotNull('credit'), so ANY ledger row
        // carrying a credit showed as money received — including receipts for
        // payments that were later reversed, refunded, cancelled or soft-deleted,
        // and rows left behind by a soft-deleted policy action. The Transaction
        // Logs tab excludes exactly those, which is why the two tabs disagreed.
        // Payment rows are now restricted to the same "money actually received"
        // reference set Transaction Logs totals, so both views reconcile.
        // Non-payment credits (Credit Note / Refund) are accounting entries in
        // their own right and are deliberately left in.
        $receivedRefs = \AlphaDirect\Services\AccountStatementService::receivedPaymentRefs(
            $id,
            $policy->policyNumber
        );

        $receivableView = DB::table('policy_ledger')
            ->where('policy_id', $id)
            ->when($actionId, fn($q) => $q->where('action_id', $actionId))
            ->whereNull('deleted_at')
            ->whereNotNull('credit')
            // Same soft-deleted-action guard the Invoicing tab uses: deleting an
            // action doesn't always cascade-void its ledger rows.
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('policy_actions as pa')
                    ->whereColumn('pa.id', 'policy_ledger.action_id')
                    ->whereNotNull('pa.deleted_at');
            })
            // NULL = policy has no payment_transactions at all (legacy/migrated),
            // so there is nothing to reconcile against and rows stay untouched.
            ->when(is_array($receivedRefs), fn($q) => $q->where(function ($w) use ($receivedRefs) {
                $w->whereNull('trans_type')
                    ->orWhere('trans_type', '!=', 'Payment')
                    ->orWhere(function ($p) use ($receivedRefs) {
                        $p->whereIn('trans_ref', $receivedRefs)
                            ->where(function ($s) {
                                $s->whereNull('status')->orWhere('status', '!=', 'Reversed');
                            });
                    });
            }))
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($l) => [
                'id'           => $l->id,
                'accountingDate' => $l->accounting_date ?? null,
                'transType'    => $l->trans_type ?? null,
                'transSubType' => $l->trans_sub_type ?? null,
                'transRef'     => $l->trans_ref ?? null,
                'effDate'      => $l->eff_date ?? null,
                'debit'        => $fmt($l->debit),
                'credit'       => $fmt($l->credit),
                'balance'      => $fmt($l->balance),
            ]);

        // Invoicing — whereNotNull('invoice_file') or whereNotNull('invoice_no')
        $invoicing = DB::table('policy_ledger')
            ->where('policy_id', $id)
            ->when($actionId, fn($q) => $q->where('action_id', $actionId))
            ->whereNull('deleted_at')
            // Hide invoices that belong to a soft-deleted policy action —
            // deleting an action doesn't always cascade-void its ledger rows,
            // which left "extra" invoices on the tab. Rows with NULL action_id
            // (legacy) are kept.
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('policy_actions as pa')
                    ->whereColumn('pa.id', 'policy_ledger.action_id')
                    ->whereNotNull('pa.deleted_at');
            })
            // INVOICES ONLY. This used to be `invoice_file IS NOT NULL OR
            // invoice_no IS NOT NULL`, and a Payment row stores its receipt PDF
            // in invoice_file — so payments (and commission entries) listed as
            // invoices with a blank invoice number and a Paid badge, which is
            // what confused Underwriting. Money received belongs on the
            // Receivable and Account views, never here.
            ->whereNotNull('invoice_no')
            ->where('invoice_no', '!=', '')
            // trans_type is NULL on some legacy invoice rows, so exclude the
            // known non-invoice types rather than requiring 'Invoice'.
            ->where(function ($q) {
                $q->whereNull('trans_type')
                  ->orWhereNotIn('trans_type', ['Payment', 'Refund', 'Credit Note']);
            })
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($l) => [
                'id'            => $l->id,
                'invoiceDate'   => $l->invoice_date ?? null,
                'invoiceNo'     => $l->invoice_no ?? null,
                'premium'       => $fmt($l->premium),
                'otherCharges'  => $fmt($l->other_charges),
                'dueAmount'     => $fmt($l->due_amount),
                'balance'       => $fmt($l->balance),
                'pmtsAdjust'    => $fmt($l->pmts_adjust),
                'invoiceAmount' => $fmt($l->invoice_amount),
                // Due Date == invoice date: use the stored due_date if present,
                // else fall back to the invoice/accounting date. Keeps the Due Date
                // column and the Paid/Pending badge consistent (and fills the "—"
                // for legacy rows that never got a due_date).
                'dueDate'       => $l->due_date ?: $l->invoice_date ?: $l->accounting_date,
                'status'        => $deriveInvStatus($l),
                'invoiceFile'   => $l->invoice_file ?? null,
                // The credit note raised against THIS invoice, if any.
                'creditNote'    => $cnByInvoiceId[(int) $l->id]
                    ?? (!empty($l->invoice_no) ? ($cnByInvoiceNo[(string) $l->invoice_no] ?? null) : null),
            ]);

        // Sub Ledger. Capped + column-projected on purpose: a few policies have
        // pathologically large subledgers (e.g. MIS2020008338 has ~186k rows),
        // and the old unbounded `SELECT * ... ->get()->map()` exhausted PHP's
        // memory limit and 500'd the WHOLE ledger endpoint — which is why Account
        // View / Receivable / Invoicing all showed empty and Total Dues fell back
        // to 0.00. We now select only the columns the UI renders and return the
        // most-recent SUB_LEDGER_CAP rows, plus the true total so the frontend can
        // surface "showing latest N of M" instead of silently truncating.
        // Hide discarded GL legs. Un-issue / re-issue discards an invoice's
        // sub-ledger legs alongside its policy_ledger rows; without this filter
        // the discarded set stayed on screen and the invoice appeared twice.
        // policy_subledger is not guaranteed to have a deleted_at column (no
        // migration adds one, and the discard path hard-deletes when it is
        // absent) — so the filter is applied only when the column exists.
        $subLedgerSoftDeletes = \Illuminate\Support\Facades\Schema::hasColumn('policy_subledger', 'deleted_at');

        $subLedgerTotal = DB::table('policy_subledger')
            ->where('policy_id', $id)
            ->whereNotNull('policy_id')
            ->when($subLedgerSoftDeletes, fn($q) => $q->whereNull('deleted_at'))
            // GL legs of an orphaned credit note (trans_ref = CR number) —
            // hidden with the note itself.
            ->when(!empty($orphanCreditNotes), fn($q) => $q->where(function ($w) use ($orphanCreditNotes) {
                $w->whereNull('trans_ref')->orWhereNotIn('trans_ref', $orphanCreditNotes);
            }))
            ->count();

        $subLedger = DB::table('policy_subledger')
            ->where('policy_id', $id)
            ->whereNotNull('policy_id')
            ->when($subLedgerSoftDeletes, fn($q) => $q->whereNull('deleted_at'))
            // GL legs of an orphaned credit note (trans_ref = CR number) —
            // hidden with the note itself.
            ->when(!empty($orphanCreditNotes), fn($q) => $q->where(function ($w) use ($orphanCreditNotes) {
                $w->whereNull('trans_ref')->orWhereNotIn('trans_ref', $orphanCreditNotes);
            }))
            ->select('id', 'accounting_date', 'trans_type', 'trans_ref', 'account_name', 'debit', 'credit')
            ->orderBy('id', 'desc')
            ->limit(self::SUB_LEDGER_CAP)
            ->get()
            ->map(fn($l) => [
                'id'            => $l->id,
                'systemDate'    => $l->accounting_date ?? null,
                'transType'     => $l->trans_type ?? null,
                'transRef'      => $l->trans_ref ?? null,
                'accountName'   => $l->account_name ?? null,
                'debit'         => $fmt($l->debit),
                'credit'        => $fmt($l->credit),
            ]);

        // Total Dues. For DOM/COM it must equal the Statement of Account's
        // Closing Balance (invoiced − paid over the filtered statement rows) —
        // the same figure the PDF and the Balance Owing widget show.
        //
        // MIS policies take it from the same recomputed Closing Balance, but
        // with the MIS statement's refund handling (refund debits raise the
        // amount owed) so it matches the MIS account_statement PDF exactly.
        //
        // For remaining legacy products it is the FINAL running balance — the
        // `balance` carried on the most recent ledger row — NOT sum('balance').
        // policy_ledger maintains a single per-policy running balance: each
        // invoice posts an "Invoice Premium" and an "Invoice VAT" row that move
        // the balance, plus a zero-debit "Invoice" summary row that does NOT
        // move it (see InvoiceGenerator). Summing the per-row balances over the
        // Invoice/Credit-Note rows therefore added up the running balance once
        // per invoice over the policy's whole life and ignored payments — so a
        // fully-paid policy whose running balance had returned to 0.00 still
        // reported a phantom outstanding amount equal to the invoiced value.
        // Reading the last non-deleted row's balance is exactly how
        // InvoiceGenerator and PaymentController derive "current balance", and
        // it is, by construction, the bottom of the Running Balance column the
        // Account View shows — so Total Dues and Running Balance now reconcile.
        // EVERY product now derives Total Dues the same way. The old else-branch
        // read the last ledger row's `balance` column, which is stamped at write
        // time from debit/credit — the very columns that cannot be trusted (see
        // AccountStatementService::rowAmount()) — so on any non-DOM/COM, non-MIS
        // product Total Dues inherited the same understatement the DOM/COM
        // statements had, and additionally never re-derived after a reversal or
        // a discarded invoice. closingBalance() recomputes from the filtered row
        // set instead. $isMis selects the MIS refund handling; anything that is
        // not DOM/COM belongs to that tier (see AccountStatementService's
        // DOMCOM_PRODUCT_IDS note), so pass !$isDomCom rather than $isMis alone.
        $totalDues = \AlphaDirect\Services\AccountStatementService::closingBalance(
            $id,
            $actionId,
            !$isDomCom
        );

        return response()->json([
            'data' => [
                'totalDues'        => $fmt($totalDues),
                'accountView'      => $accountView,
                'receivableView'   => $receivableView,
                'invoicing'        => $invoicing,
                // Newest note first — $cnRows is ordered by id ascending.
                'creditNotes'      => array_reverse($creditNotes),
                'subLedger'        => $subLedger,
                // True count vs the (possibly capped) number of rows returned, so
                // the UI can show "showing latest N of M" when truncated.
                'subLedgerTotal'   => $subLedgerTotal,
                'subLedgerCapped'  => $subLedgerTotal > self::SUB_LEDGER_CAP,
            ],
        ]);
    }

    /**
     * Account View rows for DOM/COM policies, identical to the data the Export
     * Account Statement (PDF) renders.
     *
     * This is a deliberate COPY of the ledger-filtering in
     * PolicyCreateController::generateAccountStatementPdf (reversed / refunded /
     * test payments excluded, archived ledger merged, chronological sort).
     * The PDF endpoint is the source of truth and is intentionally NOT modified;
     * if its filtering ever changes, mirror the change here too.
     */
    private function domComAccountStatementRows(int $policyId, $actionId, ?string $customerName, callable $fmt, array $cnRefToInvoiceNo = [])
    {
        // Debit / credit are DISPLAYED from AccountStatementService::rowAmount(),
        // not read off the raw columns: policy_ledger.debit is stale on 'Invoice'
        // rows, so the on-screen Account View was showing invoice values that
        // neither summed to Total Dues nor matched the exported PDF. Payments sit
        // in the credit column, everything else (Invoice / Credit Note / Refund)
        // in debit — the same placement the raw columns produced.
        return \AlphaDirect\Services\AccountStatementService::rows($policyId, $actionId)->map(function ($l) use ($customerName, $fmt, $cnRefToInvoiceNo) {
            $amount    = \AlphaDirect\Services\AccountStatementService::rowAmount($l);
            $isPayment = ($l->trans_type ?? '') === 'Payment';

            return [
                'id'             => $l->id ?? null,
                'accountingDate' => $l->accounting_date ?? null,
                'transType'      => $l->trans_type ?? null,
                'transRef'       => $l->trans_ref ?? null,
                'customerName'   => $customerName,
                'origTrans'      => $l->orig_trans ?? null,
                'unallocated'    => $fmt($l->unallocated ?? 0),
                'debit'          => $fmt($isPayment ? 0 : $amount),
                'credit'         => $fmt($isPayment ? $amount : 0),
                'balance'        => $fmt($l->balance ?? 0),
                'systemDate'     => $l->system_date ?? null,
            ] + $this->creditNoteRefFields($l->trans_type ?? null, $l->trans_ref ?? null, $cnRefToInvoiceNo);
        });
    }

    /**
     * The invoice a 'Credit Note' ledger row reverses, plus the composite
     * reference the Account Statement PDF prints for it ("<invoice no>_<CR no>").
     *
     * The ledger row itself cannot carry the invoice number — stamping
     * policy_ledger.invoice_no on a credit note would make it match the
     * Invoicing tab's whereNotNull('invoice_no') filter and show up as an
     * invoice — so the link is resolved from credit_note for display only.
     * Returning the composite as a plain string also makes the ledger search box
     * (and Ctrl+F) find a note by the reference quoted on the statement, which
     * previously matched nothing on screen.
     */
    private function creditNoteRefFields(?string $transType, ?string $transRef, array $cnRefToInvoiceNo): array
    {
        if ($transType !== 'Credit Note' || empty($transRef)) {
            return ['creditedInvoiceNo' => null, 'creditNoteRef' => null];
        }
        $invoiceNo = $cnRefToInvoiceNo[(string) $transRef] ?? null;

        return [
            'creditedInvoiceNo' => $invoiceNo,
            'creditNoteRef'     => $invoiceNo ? $invoiceNo . '_' . $transRef : $transRef,
        ];
    }

    /**
     * Lazy tab: Schedule transactions (for DPO policies)
     */
    /**
     * Guard: DPO schedule MUTATIONS are only allowed while the policy is
     * CURRENTLY on DPO billing. Historical DPO schedules (the customer has
     * since moved to another method) are view-only — abort so a stale or
     * mistaken action can't alter them. Reading remains open.
     */
    private function assertDpoEditable(?int $customerId): void
    {
        $billing = $customerId
            ? DB::table('customer_banking')->where('customer_id', $customerId)->where('active', 1)->value('billing')
            : null;
        if ($billing !== 'DPO') {
            abort(422, 'This policy is not currently on DPO billing — the schedule is historical and read-only.');
        }
    }

    public function scheduleTransactions(int $id): JsonResponse
    {
        $policy = Policy::select('id', 'policyNumber')->findOrFail($id);

        $schedules = DB::table('scheduled_transactions')
            ->where('policy_number', $policy->policyNumber)
            ->orderBy('billing_date', 'asc')
            ->get()
            ->map(fn($s) => [
                'id'            => $s->id,
                'installment'   => $s->installment ?? null,
                'amount'        => $s->premium ?? null,
                'billingDate'   => $s->billing_date ?? null,
                'status'        => match((int) ($s->status ?? 0)) {
                    0 => 'Pending',
                    1 => 'In Progress',
                    2 => 'Successful',
                    3 => 'Failed',
                    4 => 'Cancelled',
                    default => 'Unknown',
                },
                'statusCode'    => $s->status ?? null,
                'retryCount'    => $s->retry_count ?? null,
                'reason'        => $s->reason ?? null,
                'paymentMethod' => $s->payment_method ?? null,
                'email'         => $s->email ?? null,
                'createdAt'     => $s->created_at,
            ]);

        return response()->json(['data' => $schedules]);
    }

    /**
     * Cancel a single DPO scheduled transaction. Mirrors
     * DpoPaymentController::suspendPaymentDpo — sets status=4.
     */
    public function cancelScheduleTransaction(Request $request, int $id, int $scheduleId): JsonResponse
    {
        $policy = Policy::findOrFail($id);
        // Cancelling an installment is allowed even on a historical DPO
        // schedule (customer since moved off DPO) — cancelling only stops a
        // pending charge, it doesn't mutate the schedule into something new.
        $row = DB::table('scheduled_transactions')
            ->where('id', $scheduleId)
            ->where('policy_number', $policy->policyNumber)
            ->first();
        if (!$row) {
            return response()->json(['error' => 'Scheduled transaction not found for this policy.'], 404);
        }
        DB::table('scheduled_transactions')->where('id', $scheduleId)->update([
            'status'     => 4,
            'added_by'   => auth()->id(),
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Scheduled transaction cancelled.']);
    }

    /**
     * Cancel ALL pending/failed DPO scheduled transactions for this policy.
     * Mirrors DpoPaymentController::suspendPaymentDpoAll.
     */
    public function cancelAllScheduleTransactions(int $id): JsonResponse
    {
        $policy = Policy::findOrFail($id);
        // Cancel All is allowed even on a historical DPO schedule — see
        // cancelScheduleTransaction(); cancelling only halts pending charges.
        $affected = DB::table('scheduled_transactions')
            ->where('policy_number', $policy->policyNumber)
            ->whereIn('status', [0, 1, 3])
            ->update([
                'status'     => 4,
                'added_by'   => auth()->id(),
                'updated_at' => now(),
            ]);
        return response()->json(['message' => "Cancelled $affected scheduled transactions.", 'count' => $affected]);
    }

    /**
     * Update billing date for a scheduled transaction.
     * Mirrors PolicyController::updateBillingDateScheduleTransaction.
     */
    public function updateScheduleBillingDate(Request $request, int $id, int $scheduleId): JsonResponse
    {
        $data = $request->validate([
            'billing_date' => 'required|date',
        ]);
        $policy = Policy::findOrFail($id);
        $this->assertDpoEditable($policy->customer_id);
        $row = DB::table('scheduled_transactions')
            ->where('id', $scheduleId)
            ->where('policy_number', $policy->policyNumber)
            ->first();
        if (!$row) {
            return response()->json(['error' => 'Scheduled transaction not found for this policy.'], 404);
        }
        DB::table('scheduled_transactions')->where('id', $scheduleId)->update([
            'billing_date' => $data['billing_date'],
            'added_by'     => auth()->id(),
            'updated_at'   => now(),
        ]);
        return response()->json(['message' => 'Billing date updated.']);
    }

    /**
     * Bulk-update billing dates for ALL pending DPO scheduled transactions
     * from a billing day (1-28). Mirrors Admin
     * PolicyController::updateBillingDateScheduleTransaction + setDate():
     * if the day hasn't passed this month it anchors this month, otherwise
     * next month; each subsequent pending installment is +1 month.
     */
    public function updateAllScheduleBillingDates(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'billing_day' => 'required|integer|min:1|max:28',
        ]);
        $policy = Policy::select('id', 'policyNumber', 'customer_id')->findOrFail($id);
        $this->assertDpoEditable($policy->customer_id);

        $rows = DB::table('scheduled_transactions')
            ->where('policy_number', $policy->policyNumber)
            ->where('status', 0)
            ->orderBy('installment')
            ->get(['id']);
        if ($rows->isEmpty()) {
            return response()->json(['error' => 'No pending scheduled transactions found for this policy.'], 404);
        }

        $day  = (int) $data['billing_day'];
        $base = $day > (int) now()->format('d')
            ? Carbon::create(now()->year, now()->month, $day)
            : Carbon::create(now()->year, now()->month, $day)->addMonthNoOverflow();

        DB::beginTransaction();
        try {
            foreach ($rows as $k => $row) {
                DB::table('scheduled_transactions')->where('id', $row->id)->update([
                    'billing_date' => $base->copy()->addMonthsNoOverflow($k)->format('Y-m-d'),
                    'added_by'     => auth()->id(),
                    'updated_at'   => now(),
                ]);
            }
            DB::commit();
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['error' => $ex->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Billing date for ' . $rows->count() . ' scheduled transactions updated successfully.',
            'count'   => $rows->count(),
        ]);
    }

    /**
     * Update premium for a scheduled transaction.
     * Mirrors PolicyController::updatePremiumScheduleTransaction.
     */
    public function updateSchedulePremium(Request $request, int $id, int $scheduleId): JsonResponse
    {
        $data = $request->validate([
            'premium' => 'required|numeric|min:0',
        ]);
        $policy = Policy::findOrFail($id);
        $this->assertDpoEditable($policy->customer_id);
        $row = DB::table('scheduled_transactions')
            ->where('id', $scheduleId)
            ->where('policy_number', $policy->policyNumber)
            ->first();
        if (!$row) {
            return response()->json(['error' => 'Scheduled transaction not found for this policy.'], 404);
        }
        DB::table('scheduled_transactions')->where('id', $scheduleId)->update([
            'premium'    => $data['premium'],
            'added_by'   => auth()->id(),
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Premium updated.']);
    }

    /**
     * Create a new scheduled transaction row for this policy.
     * Simplified version of DpoPaymentController::addScheduleTransaction.
     */
    public function addScheduleTransaction(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'billing_date' => 'required|date',
            'premium'      => 'required|numeric|min:0',
            'installment'  => 'nullable|integer|min:1',
        ]);
        $policy = Policy::with('customer:id,mobileNumber,email')->findOrFail($id);
        $this->assertDpoEditable($policy->customer_id);
        $newId = DB::table('scheduled_transactions')->insertGetId([
            'policy_id'     => $policy->id,
            'policy_number' => $policy->policyNumber,
            'customer_id'   => $policy->customer_id,
            'installment'   => $data['installment'] ?? 1,
            'premium'       => $data['premium'],
            'billing_date'  => $data['billing_date'],
            'email'         => $policy->customer->email ?? null,
            'status'        => 0,
            'retry_count'   => 0,
            'payment_method'=> 'DPO',
            'added_by'      => auth()->id(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        return response()->json(['message' => 'Scheduled transaction added.', 'id' => $newId], 201);
    }

    /**
     * Linked policies — other policies belonging to the same customer.
     * Mirrors graphiteBWV8 PolicyController::linkedPolicyData().
     */
    public function linkedPolicies(int $id): JsonResponse
    {
        $policy = Policy::select('id', 'customer_id')->findOrFail($id);
        if (!$policy->customer_id) {
            return response()->json(['data' => []]);
        }

        // Join the canonical `products` catalog, NOT `bundled_products` —
        // the latter does not exist on V2 PROD (no migration creates it)
        // and the LEFT JOIN crashed the endpoint with
        //   SQLSTATE[42S02]: Base table or view not found: 1146
        //   Table 'Graphite_live.bundled_products' doesn't exist
        // observed on PROD 2026-06-11 (linkedPolicies for customer_id 5966).
        // The `bundled_products` name appears to be a graphiteBWV8 legacy that
        // never carried over to V2; the V2 product catalog is `products`.
        $rows = DB::table('policies as p')
            ->leftJoin('customer as c', 'c.id', '=', 'p.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.agent_id')
            ->leftJoin('products as bp', 'bp.id', '=', 'p.product_id')
            ->where('p.customer_id', $policy->customer_id)
            ->where('p.id', '!=', $policy->id)
            ->whereNull('p.deleted_at')
            ->orderBy('p.id', 'desc')
            ->select(
                'p.id',
                'p.policyNumber',
                'p.product_id',
                'p.billing as paymentMethod',
                'p.payment_reference',
                'p.vehicle_plate',
                'p.status',
                'p.created_at',
                'c.firstName as cust_first',
                'c.lastName as cust_last',
                'c.mobileNumber as cellphone',
                'u.name as agentName',
                'bp.name as productName'
            )
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'policyNumber'    => $r->policyNumber,
                'customerName'    => trim(($r->cust_first ?? '') . ' ' . ($r->cust_last ?? '')),
                'agentName'       => $r->agentName,
                'cellphone'       => $r->cellphone,
                'product'         => $r->productName,
                'paymentMethod'   => $r->paymentMethod,
                'paymentReference'=> $r->payment_reference,
                'vehiclePlate'    => $r->vehicle_plate,
                'status'          => $r->status,
                'createdAt'       => $r->created_at,
            ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * Mati verification data for the customer linked to this policy
     */
    public function matiVerification(int $id): JsonResponse
    {
        $policy = Policy::select('id', 'customer_id')->findOrFail($id);

        // Get mati_identity from customer table
        $matiIdentity = DB::table('customer')
            ->where('id', $policy->customer_id)
            ->value('mati_identity');

        // customer_mati table has no customer_id column — lookup is via identity_id or verification_id
        $mati = null;

        if ($matiIdentity) {
            // 1. By identity_id matching customer.mati_identity
            $mati = DB::table('customer_mati')
                ->where('identity_id', $matiIdentity)
                ->orderBy('id', 'desc')
                ->first();

            // 2. By verification_id matching customer.mati_identity
            if (!$mati) {
                $mati = DB::table('customer_mati')
                    ->where('verification_id', $matiIdentity)
                    ->orderBy('id', 'desc')
                    ->first();
            }
        }

        // Determine country — if customer has omang, default to Botswana
        $hasOmang = DB::table('customer_profile')
            ->where('customer_id', $policy->customer_id)
            ->whereNotNull('omang')
            ->where('omang', '!=', '')
            ->exists();
        $defaultCountry = $hasOmang ? 'Botswana' : null;

        // If we have a full mati record, parse the stored MetaMap (Mati) JSON
        // response and return the V8-parity payload (details table, location,
        // device fingerprint, images). The rich data lives entirely in the
        // `response` column — mirrors graphiteBWV8 policyDetails_View.blade.php.
        if ($mati) {
            $parsed = $this->parseMatiResponse($mati->response ?? null, $defaultCountry);

            return response()->json([
                'data' => array_merge([
                    'id'             => $mati->id,
                    'matiId'         => $matiIdentity ?: ($mati->identity_id ?? null),
                    'identityId'     => $mati->identity_id ?? $matiIdentity,
                    'verificationId' => $mati->verification_id ?? null,
                    // Fetch is possible whenever we can resolve a verification
                    // to pull against (the customer identity OR the stored
                    // verification/identity id on the record itself).
                    'hasFetchData'   => (bool) ($matiIdentity ?: ($mati->verification_id ?? $mati->identity_id ?? null)),
                    'images'         => [
                        'passportUrl'       => $this->cdnUrl($mati->passport ?? null),
                        'omangUrl'          => $this->cdnUrl($mati->omang ?? null),
                        'omangBackUrl'      => $this->cdnUrl($mati->omangBack ?? null),
                        'drivingLicenseUrl' => $this->cdnUrl($mati->driving_license ?? null),
                        'proofResidenceUrl' => $this->cdnUrl($mati->proof_residence ?? null),
                        'selfieUrl'         => $this->cdnUrl($mati->selfie ?? null),
                    ],
                    'createdAt'      => $mati->created_at,
                    'updatedAt'      => $mati->updated_at,
                ], $parsed),
            ]);
        }

        // No customer_mati record — but if mati_identity exists on customer,
        // return a thin payload so the operator can still Fetch / Update IDs.
        if ($matiIdentity) {
            return response()->json([
                'data' => [
                    'id'             => 0,
                    'matiId'         => $matiIdentity,
                    'identityId'     => $matiIdentity,
                    'verificationId' => null,
                    'hasFetchData'   => true,
                    'status'         => null,
                    'documents'      => [],
                    'location'       => null,
                    'device'         => null,
                    'images'         => [
                        'passportUrl'       => null,
                        'omangUrl'          => null,
                        'omangBackUrl'      => null,
                        'drivingLicenseUrl' => null,
                        'proofResidenceUrl' => null,
                        'selfieUrl'         => null,
                    ],
                    'createdAt'      => null,
                    'updatedAt'      => null,
                ],
            ]);
        }

        return response()->json(['data' => null]);
    }

    /**
     * Parse the raw MetaMap (Mati) verification JSON into the structured shape
     * the V2 "Mati Verification" tab renders — V8 parity with the legacy
     * policyDetails_View.blade.php tab (status badge, documents table, location
     * details, device checks).
     *
     * @return array{status: ?string, documents: array<int, array<string, ?string>>, location: ?array, device: ?array}
     */
    private function parseMatiResponse(?string $responseJson, ?string $defaultCountry = null): array
    {
        $out = [
            'status'    => null,
            'documents' => [],
            'location'  => null,
            'device'    => null,
        ];

        if (empty($responseJson)) {
            return $out;
        }

        $resp = json_decode($responseJson);
        if (!is_object($resp)) {
            return $out;
        }

        // identity.status drives the header status badge (verified / rejected /
        // reviewNeeded / pending).
        $out['status'] = $resp->identity->status ?? null;

        // Documents → details table rows.
        if (isset($resp->documents) && is_array($resp->documents)) {
            foreach ($resp->documents as $doc) {
                $f = $doc->fields ?? null;
                $out['documents'][] = [
                    'type'           => isset($doc->type) ? ucfirst($doc->type) : null,
                    'fullName'       => isset($f->fullName->value) ? ucwords($f->fullName->value) : null,
                    'dateOfBirth'    => $f->dateOfBirth->value ?? null,
                    'firstName'      => isset($f->firstName->value) ? ucwords($f->firstName->value) : null,
                    'surname'        => isset($f->surname->value) ? ucwords($f->surname->value) : null,
                    'sex'            => $f->sex->value ?? null,
                    'documentNumber' => $f->documentNumber->value ?? null,
                    'expirationDate' => $f->expirationDate->value ?? null,
                    'issueCountry'   => $f->issueCountry->value ?? $defaultCountry,
                    'nationality'    => isset($f->nationality->value) ? ucfirst($f->nationality->value) : null,
                ];
            }
        }

        // Location details from the ip-validation step.
        if (isset($resp->steps) && is_array($resp->steps)) {
            foreach ($resp->steps as $step) {
                if (($step->id ?? null) === 'ip-validation' && isset($step->data)) {
                    $out['location'] = [
                        'country' => $step->data->country ?? null,
                        'region'  => $step->data->region ?? null,
                        'city'    => $step->data->city ?? null,
                        'zip'     => $step->data->zip ?? null,
                    ];
                    break;
                }
            }
        }

        // Device fingerprint → device checks table.
        if (isset($resp->deviceFingerprint) && $resp->deviceFingerprint !== null) {
            $df = $resp->deviceFingerprint;
            $platform = $df->app->platform ?? null;
            $deviceType = $platform === 'web_desktop' ? 'Desktop' : $platform;
            $os = trim(($df->os->name ?? '') . ' ' . ($df->os->version ?? '')) ?: null;
            $browser = trim(($df->browser->name ?? '') . ' ' . ($df->browser->major ?? '')) ?: null;
            $out['device'] = [
                'deviceType' => $deviceType,
                'os'         => $os,
                'browser'    => $browser,
                'ip'         => $df->ip ?? null,
            ];
        }

        return $out;
    }

    /**
     * POST /policies/{id}/mati/verification-link/{type} — send the MetaMap
     * (Mati) KYC verification link to the policy customer by email or SMS.
     * V8 parity: PolicyController::sendMatiVerificationLink.
     */
    public function matiVerificationLink(int $id, string $type): JsonResponse
    {
        $policy = Policy::select('id', 'customer_id', 'product_id')->findOrFail($id);
        $customer = Customer::where('id', $policy->customer_id)->first(['id', 'email', 'cellphone']);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found.'], 404);
        }

        $flowId = $this->resolveMatiFlowId($policy->product_id);
        $data = [
            'customer_id' => $customer->id,
            'email'       => $customer->email,
            'cellphone'   => $customer->cellphone,
            'flow_id'     => $flowId,
        ];

        try {
            if ($type === 'email') {
                if (empty($data['email'])) {
                    return response()->json(['message' => 'Email is not present for this customer.'], 422);
                }
                $markdown = new MatiLink($data);
                $html = $markdown->render('Mail.MatiLink', ['data' => $data]);
                event(new \AlphaDirect\Events\SendMail($data['email'], 'Alphadirect | Upload documents for Kyc completion', '', $html, null, []));
                return response()->json(['message' => 'Verification link sent to the customer email.']);
            }

            if ($type === 'sms') {
                if (empty($data['cellphone'])) {
                    return response()->json(['message' => 'Cellphone number is not present for this customer.'], 422);
                }
                $sms = new SmsMessaging();
                $sms->SendSMSMativerificationLink($data['cellphone'], $flowId, $data['customer_id']);
                return response()->json(['message' => 'Verification link sent to the customer cellphone.']);
            }

            return response()->json(['message' => 'Invalid verification link type.'], 422);
        } catch (\Throwable $e) {
            Log::error('matiVerificationLink failed', ['policy_id' => $id, 'type' => $type, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not send the verification link. Please try again.'], 500);
        }
    }

    /**
     * POST /policies/{id}/mati/fetch — pull the latest verification data from
     * the MetaMap (Mati) API for the customer's existing identity/verification
     * IDs, persist images to S3, and sync customer_kyc.
     * V8 parity: CustomerController::fetchMatiData.
     */
    public function matiFetch(int $id): JsonResponse
    {
        $policy = Policy::select('id', 'customer_id')->findOrFail($id);
        $matiIdentity = Customer::where('id', $policy->customer_id)->value('mati_identity');

        // Resolve the customer_mati record. customer.mati_identity may hold
        // either the identity_id or the verification_id, so try both columns.
        $mati = null;
        if ($matiIdentity) {
            $mati = CustomerMati::where('identity_id', $matiIdentity)->orderBy('id', 'desc')->first()
                ?? CustomerMati::where('verification_id', $matiIdentity)->orderBy('id', 'desc')->first();
        }
        if (!$mati) {
            return response()->json(['message' => 'Customer mati verification data not found.'], 404);
        }
        if (empty($mati->identity_id) || empty($mati->verification_id)) {
            return response()->json(['message' => 'Identity ID or Verification ID not found.'], 422);
        }

        try {
            $this->syncMatiFromApi($mati, $mati->verification_id);
            return response()->json(['message' => 'Data fetched successfully.']);
        } catch (\Throwable $e) {
            Log::error('matiFetch failed', ['policy_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not fetch Mati data. Please try again.'], 500);
        }
    }

    /**
     * POST /policies/{id}/mati/update — set/replace the customer's Mati
     * identity_id + verification_id. When the (identity_id) record is new, the
     * verification data is pulled from the MetaMap API and images persisted.
     * V8 parity: CustomerController::updateMatiData.
     */
    public function matiUpdate(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identity_id'     => 'required|string|max:255',
            'verification_id' => 'required|string|max:255',
        ]);

        $policy = Policy::select('id', 'customer_id')->findOrFail($id);
        $customer = Customer::where('id', $policy->customer_id)->first();
        if (!$customer) {
            return response()->json(['message' => 'Customer not found.'], 404);
        }

        try {
            $customer->mati_identity = $validated['identity_id'];
            $customer->save();

            $mati = CustomerMati::where('identity_id', $validated['identity_id'])->first();
            if ($mati) {
                // Record already exists — just point it at the new verification.
                $mati->verification_id = $validated['verification_id'];
                $mati->save();
            } else {
                $mati = new CustomerMati();
                $mati->identity_id = $validated['identity_id'];
                $mati->verification_id = $validated['verification_id'];
                $this->syncMatiFromApi($mati, $validated['verification_id']);
            }

            return response()->json(['message' => 'Mati Identity ID and Verification ID updated successfully.']);
        } catch (\Throwable $e) {
            Log::error('matiUpdate failed', ['policy_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not update Mati data. Please try again.'], 500);
        }
    }

    /**
     * Resolve the MetaMap flow_id for a product via its KYC compliance config,
     * falling back to the env-specific defaults. V8 parity.
     */
    private function resolveMatiFlowId($productId): string
    {
        $product = Product::where('id', $productId)->first(['kyc_compliance']);
        if ($product && $product->kyc_compliance) {
            $compliance = KycCompliance::where('id', $product->kyc_compliance)->first(['flow_id']);
            if ($compliance && $compliance->flow_id) {
                return $compliance->flow_id;
            }
        }
        return env('APP_STATUS') === 'Production'
            ? '616ac99406694f001be574c7'  // AdvanceKYC LIVE
            : '612c87b1ebca36001b310ea1'; // LiveQuote Test
    }

    /**
     * Shared MetaMap (Mati) fetch + persist routine used by matiFetch and
     * matiUpdate. Obtains an OAuth token, pulls the verification document set,
     * stores the raw response, uploads document/selfie images to S3, and syncs
     * the resulting paths onto customer_kyc. V8 parity:
     * CustomerController::fetchMatiData / updateMatiData.
     */
    private function syncMatiFromApi(CustomerMati $mati, string $verificationId): void
    {
        // 1. OAuth token (client_credentials).
        $curlToken = curl_init();
        curl_setopt_array($curlToken, [
            CURLOPT_URL            => 'https://api.getmati.com/oauth',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic NjExMjZjZmIzODNmZjgwMDFiMjdhNGJmOlBGN1VUWFZZMkdOUkZRQUs1QUE0VDRaWDFOS0VVSDgy',
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $tokenResp = json_decode(curl_exec($curlToken), true);
        curl_close($curlToken);

        $token = $tokenResp['access_token'] ?? null;
        if (!$token) {
            throw new \RuntimeException('Could not obtain Mati access token.');
        }

        // 2. Verification document set.
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://api.getmati.com/v2/verifications/' . $verificationId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        ]);
        $data = json_decode(curl_exec($curl), true);
        curl_close($curl);

        if (!is_array($data)) {
            throw new \RuntimeException('Unexpected Mati verification response.');
        }

        $mati->response = json_encode($data);

        // 3. Persist document images to S3.
        $s3 = \Illuminate\Support\Facades\Storage::disk('s3');
        $base = 'Mati/KYC/' . $verificationId;

        foreach ($data['documents'] ?? [] as $value) {
            $type = $value['type'] ?? null;
            $photos = $value['photos'] ?? [];

            if ($type === 'passport' && isset($photos[0])) {
                $mati->passport = $this->putMatiImage($s3, $base . '/passport', $photos[0]);
            } elseif ($type === 'driving-license' && isset($photos[0])) {
                $mati->driving_license = $this->putMatiImage($s3, $base . '/driving-license', $photos[0]);
            } elseif ($type === 'national-id') {
                if (isset($photos[0])) {
                    $mati->omang = $this->putMatiImage($s3, $base . '/national-id', $photos[0]);
                }
                if (isset($photos[1])) {
                    $mati->omangBack = $this->putMatiImage($s3, $base . '/national-id-back', $photos[1]);
                }
            } elseif ($type === 'proof-of-residency' && isset($photos[0])) {
                $mati->proof_residence = $this->putMatiImage($s3, $base . '/proof-of-residency', $photos[0]);
            }
        }

        // 4. Selfie image.
        foreach ($data['steps'] ?? [] as $step) {
            if (($step['id'] ?? null) === 'selfie' && isset($step['data']['selfiePhotoUrl'])) {
                $mati->selfie = $this->putMatiImage($s3, $base . '/selfie', $step['data']['selfiePhotoUrl']);
            }
        }

        $mati->save();

        // 5. Sync the persisted image paths onto customer_kyc.
        $customer = Customer::where('mati_identity', $mati->identity_id)->first();
        if ($customer) {
            $customerKyc = KYC::where('customer_id', $customer->id)->first();
            if ($customerKyc) {
                if (isset($mati->passport))         $customerKyc->passport = $mati->passport;
                if (isset($mati->driving_license))  $customerKyc->driving_license = $mati->driving_license;
                if (isset($mati->omang))            $customerKyc->omang = $mati->omang;
                if (isset($mati->omangBack))        $customerKyc->omangBack = $mati->omangBack;
                if (isset($mati->proof_residence))  $customerKyc->proof_residence = $mati->proof_residence;
                $customerKyc->save();
            }
        }
    }

    /**
     * Download a remote image, re-encode as JPG, and store it on the given S3
     * disk at $path (public). Returns the stored path. V8 parity.
     */
    private function putMatiImage($s3, string $path, string $photoUrl): string
    {
        $image = \Image::make($photoUrl);
        $image->encode('jpg');
        $s3->put($path, $image->__toString(), 'public');
        return $path;
    }

    /**
     * GET /policies/{id}/attachment-list — V8-shape attachment rows.
     *
     * Mirrors graphiteBWV8 PolicyController::attachmentData (line 5983)
     * which returns one row per policy_attachments record (NOT per file)
     * for the Attachment tab's DataTable. Each row may carry multiple
     * files (serialised in the `attachment` column); the FE renders them
     * as a horizontal strip of preview thumbnails.
     *
     * Kept separate from `attachments()` (which flattens user uploads +
     * policy docs + KYC into a single feed) so the V8 attachment-table UI
     * can iterate rows and surface deletes against the real row id.
     */
    public function attachmentList(int $id): JsonResponse
    {
        Policy::findOrFail($id);

        $rows = DB::table('policy_attachments')
            ->where('policy_id', $id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($a) {
                $paths = @unserialize($a->attachment ?? '', ['allowed_classes' => false]);
                if (!is_array($paths)) $paths = $a->attachment ? [$a->attachment] : [];

                $files = collect($paths)->filter()->map(function ($path) {
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    return [
                        'name'      => basename($path),
                        'url'       => $this->cdnUrl($path),
                        'extension' => $ext,
                        // Coarse kind so the FE can pick the right preview
                        // icon without re-parsing the extension client-side.
                        'kind'      => match (true) {
                            in_array($ext, ['jpg','jpeg','png','gif','webp','bmp'], true) => 'image',
                            $ext === 'pdf'                                                => 'pdf',
                            in_array($ext, ['doc','docx','docm'], true)                   => 'word',
                            in_array($ext, ['xls','xlsx','csv'], true)                    => 'excel',
                            default                                                       => 'other',
                        },
                    ];
                })->values();

                return [
                    'id'        => $a->id,
                    'name'      => $a->name ?? 'Attachment',
                    'type'      => $a->type ?? 'Documents',
                    'files'     => $files,
                    'createdAt' => $a->created_at,
                    'updatedAt' => $a->updated_at,
                ];
            });

        return response()->json(['data' => $rows->values()]);
    }

    /**
     * GET /policies/lookups/file-types — proxy to the same lookup feed
     * the claims Attachments tab uses (`lookup_data` key=file_type). Kept
     * under the /policies tree so the policy Attachments tab doesn't need
     * to import a claims URL.
     */
    public function fileTypesLookup(): JsonResponse
    {
        $fallback = collect(['Invoice', 'Documents'])
            ->map(fn($v) => ['id' => $v, 'name' => $v])->values();

        if (!\Schema::hasTable('lookup_data')) {
            return response()->json(['data' => $fallback]);
        }
        $rows = DB::table('lookup_data')
            ->where('key', 'file_type')
            ->orderBy('value')
            ->get(['value'])
            ->map(fn($r) => ['id' => $r->value, 'name' => $r->value])
            ->values();

        return response()->json(['data' => $rows->isNotEmpty() ? $rows : $fallback]);
    }

    /**
     * GET /policies/{id}/assign-agent
     *
     * Data for the Assign Agent tab (old Graphite policy edit page parity):
     * the selectable agents (all active users, like the v8 edit() screen) and
     * stores, plus the policy's current agent_id / storeID for preselection.
     */
    public function assignAgentData(int $id): JsonResponse
    {
        $policy = Policy::find($id, ['id', 'agent_id', 'storeID']);
        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        $agents = \AlphaDirect\User::where('active', 1)
            ->orderBy('firstName')
            ->get(['id', 'firstName', 'lastName'])
            ->map(fn($u) => [
                'id'   => $u->id,
                'name' => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')),
            ])->values();

        $stores = \AlphaDirect\Stores::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($s) => ['id' => $s->id, 'name' => $s->name])
            ->values();

        return response()->json([
            'data' => [
                'agents'  => $agents,
                'stores'  => $stores,
                'agentId' => $policy->agent_id !== null ? (int) $policy->agent_id : null,
                'storeId' => $policy->storeID !== null ? (int) $policy->storeID : null,
            ],
        ]);
    }

    /**
     * POST /policies/{id}/assign-agent
     *
     * Port of Admin\PolicyController::agentUpdate (admin.policy.agentUpdate):
     * updates the policy's agent_id and storeID. Gated on the same
     * assign-agent-policy-edit permission the old tab used, and records the
     * activity log entries the v8 method intended to write.
     */
    public function assignAgent(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        if ($user && method_exists($user, 'hasPermissionTo')) {
            try {
                if (!$user->hasPermissionTo('assign-agent-policy-edit')
                    && !$user->hasPermissionTo('assign-agent-policy-list')) {
                    return response()->json(['error' => 'You do not have permission to assign agents.'], 403);
                }
            } catch (\Throwable $e) {
                // permission not registered in this environment — fall through
            }
        }

        $validated = $request->validate([
            'agent_id' => 'nullable|integer|exists:users,id',
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        $policy = Policy::find($id);
        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        $agentChanged = array_key_exists('agent_id', $validated)
            && (int) $policy->agent_id !== (int) $validated['agent_id'];
        $storeChanged = array_key_exists('store_id', $validated)
            && (int) $policy->storeID !== (int) $validated['store_id'];

        if (array_key_exists('agent_id', $validated)) {
            $policy->agent_id = $validated['agent_id'];
        }
        if (array_key_exists('store_id', $validated)) {
            $policy->storeID = $validated['store_id'];
        }
        $policy->save();

        if ($agentChanged) {
            activity('Assign Agent')->performedOn($policy)->causedBy($user)->log('Agent Updated');
        }
        if ($storeChanged) {
            activity('Assign Store')->performedOn($policy)->causedBy($user)->log('Store Updated');
        }

        return response()->json([
            'message' => 'Updated Successfully',
            'data'    => [
                'agentId' => $policy->agent_id !== null ? (int) $policy->agent_id : null,
                'storeId' => $policy->storeID !== null ? (int) $policy->storeID : null,
            ],
        ]);
    }

    /**
     * GET /policies/{id}/wording-documents
     *
     * Policy wording documents for the Documents tab — a faithful port of the
     * old Graphite policy edit page's $emailDocs build (Admin\PolicyController
     * ::edit, the same selection reSendPolicyDocument emails):
     *
     *  1. Plan-specific wording first: documents rows matching the policy's
     *     product_id AND plan_id (each plan has its own wording).
     *  2. Plus the global docs (product_id = -1) that ship with every policy.
     *  3. If the product has no plan-specific rows, fall back to every active
     *     doc for the product (product_id = policy.product_id OR -1).
     *  4. Bundled policies also pull each bundled product's docs (plan-keyed
     *     via policy_bundled.plan_name, falling back to plan_id IS NULL rows).
     */
    public function wordingDocuments(int $id): JsonResponse
    {
        $policy = Policy::find($id, ['id', 'product_id', 'plan_id', 'is_bundled']);
        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        $globalIds = fn() => \AlphaDirect\Documents::where('product_id', -1)
            ->where('status', 1)->pluck('id')->all();

        $docsid = [];

        if ((int) $policy->is_bundled === 1) {
            $bundled = DB::table('policy_bundled')->where('policy_id', $policy->id)->get();
            foreach ($bundled as $b) {
                $planDocs = \AlphaDirect\Documents::where('product_id', $b->product_id)
                    ->where('status', 1)
                    ->whereNotNull('plan_id')
                    ->where('plan_id', $b->plan_name)
                    ->pluck('id')->all();
                if ($planDocs) {
                    $docsid = array_merge($docsid, $planDocs, $globalIds());
                } else {
                    $prodDocs = \AlphaDirect\Documents::where('product_id', $b->product_id)
                        ->where('status', 1)
                        ->whereNull('plan_id')
                        ->pluck('id')->all();
                    if ($prodDocs) {
                        $docsid = array_merge($docsid, $prodDocs, $globalIds());
                    }
                }
            }
        }

        $planDocs = \AlphaDirect\Documents::where('product_id', $policy->product_id)
            ->where('status', 1)
            ->whereNotNull('plan_id')
            ->where('plan_id', $policy->plan_id)
            ->pluck('id')->all();

        if ($planDocs) {
            $docsid = array_merge($docsid, $planDocs, $globalIds());
            $docs = \AlphaDirect\Documents::whereIn('id', array_unique($docsid))
                ->where('status', 1)
                ->get(['id', 'name', 'link', 'product_id']);
        } else {
            // v8 fallback: every active doc for the product + the globals.
            // (Unlike v8 we keep any bundled docs collected above instead of
            // dropping them — the old raw-SQL else branch lost those.)
            $docs = \AlphaDirect\Documents::where('status', 1)
                ->where(function ($q) use ($policy, $docsid) {
                    $q->where('product_id', $policy->product_id)
                      ->orWhere('product_id', -1);
                    if ($docsid) {
                        $q->orWhereIn('id', array_unique($docsid));
                    }
                })
                ->get(['id', 'name', 'link', 'product_id']);
        }

        return response()->json([
            'data' => $docs->unique('id')->values()->map(fn($d) => [
                'id'   => $d->id,
                'name' => $d->name,
                'url'  => $d->link ? \AlphaDirect\Helper::getCloudFrontURL($d->link) : null,
            ])->filter(fn($d) => $d['url'])->values(),
        ]);
    }

    /**
     * Policy attachments/documents
     */
    public function attachments(int $id): JsonResponse
    {
        $policy = Policy::findOrFail($id);

        // 1. User-uploaded attachments (policy_attachments — serialized array column)
        $userAttachments = DB::table('policy_attachments')
            ->where('policy_id', $id)
            ->orderBy('id', 'desc')
            ->get()
            ->flatMap(function ($a) {
                $paths = @unserialize($a->attachment ?? '', ['allowed_classes' => false]);
                if (!is_array($paths)) $paths = $a->attachment ? [$a->attachment] : [];

                return collect($paths)->map(fn($path, $i) => [
                    'id'          => 'att_' . $a->id . ($i > 0 ? "_$i" : ''),
                    'name'        => $a->name ?? 'Attachment',
                    'type'        => $a->type ?? 'Documents',
                    'category'    => 'attachment',
                    'url'         => $this->cdnUrl($path),
                    'createdAt'   => $a->created_at,
                ]);
            });

        // 2. Generated policy documents (policy_documents — schedules, endorsements)
        $policyDocs = DB::table('policy_documents')
            ->where('policy_id', $id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($d) => [
                'id'          => 'doc_' . $d->id,
                'name'        => $d->file_name ?: ($d->is_cancellation_note ? 'Cancellation Note' : 'Policy Schedule'),
                'type'        => $d->is_cancellation_note ? 'Cancellation' : 'Policy Document',
                'category'    => 'policy_document',
                'url'         => $this->cdnUrl($d->doc_path),
                'createdAt'   => $d->created_at,
            ]);

        // 3. KYC documents — individual (customer_kyc) + corporate (customer_kyc_dom_com)
        $kycDocs = collect();
        if ($policy->customer_id) {
            // Individual KYC
            $kyc = DB::table('customer_kyc')->where('customer_id', $policy->customer_id)->first();
            if ($kyc) {
                $kycFields = [
                    'omang' => 'Omang ID Front', 'omangBack' => 'Omang ID Back',
                    'passport' => 'Passport Front', 'passport_back' => 'Passport Back',
                    'driving_license' => 'Driving License Front', 'driving_license_back' => 'Driving License Back',
                    'proof_residence' => 'Proof of Residence', 'proof_income' => 'Proof of Income',
                    'bank_statement_file_path' => 'Bank Statement', 'debit_authorization_form' => 'Debit Authorization',
                ];
                foreach ($kycFields as $field => $label) {
                    $val = $kyc->{$field} ?? null;
                    if ($val) {
                        $kycDocs->push([
                            'id'       => 'kyc_' . $field,
                            'name'     => $label,
                            'type'     => 'KYC',
                            'category' => 'kyc',
                            'url'      => $this->cdnUrl($val),
                            'createdAt' => $kyc->created_at ?? null,
                        ]);
                    }
                }
            }

            // Corporate KYC (DOM/COM policies)
            $corpKyc = DB::table('customer_kyc_dom_com')->where('customer_id', $policy->customer_id)->first();
            if ($corpKyc) {
                $corpFields = [
                    'certificate_of_incorporation' => 'Certificate of Incorporation',
                    'extract_controllers'          => 'Extract Controllers & Ownership',
                    'kyc_form'                     => 'KYC Form',
                    'data_protection_form'         => 'Data Protection Form',
                    'resolution'                   => 'Resolution',
                    'proof_business_address'       => 'Proof of Business Address',
                    'proof_residential_address'    => 'Proof of Residential Address (Directors)',
                    'directors_id_front'           => 'Directors ID Front',
                    'directors_id_back'            => 'Directors ID Back',
                    'directors_passport'           => 'Directors Passport',
                    'shareholders_id_front'        => 'Shareholders ID Front',
                    'shareholders_id_back'         => 'Shareholders ID Back',
                    'shareholders_passport'        => 'Shareholders Passport',
                ];
                foreach ($corpFields as $field => $label) {
                    $val = $corpKyc->{$field} ?? null;
                    if ($val) {
                        $kycDocs->push([
                            'id'       => 'corp_kyc_' . $field,
                            'name'     => $label,
                            'type'     => 'Corporate KYC',
                            'category' => 'kyc',
                            'url'      => $this->cdnUrl($val),
                            'createdAt' => $corpKyc->created_at ?? null,
                        ]);
                    }
                }
            }
        }

        $all = $userAttachments->concat($policyDocs)->concat($kycDocs)->values();

        return response()->json(['data' => $all]);
    }

    /**
     * Policy terms (for Motor Comprehensive)
     */
    public function terms(int $id): JsonResponse
    {
        Policy::findOrFail($id);

        $terms = DB::table('policy_term')
            ->where('policy_id', $id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($t) => [
                'id'        => $t->id,
                'termNumber'=> $t->term_number ?? $t->term_no ?? null,
                // policy_term stores the effective dates as term_start_date /
                // term_end_date (see legacy getPolicyTermsData:18967). The old
                // start_date/end_date keys don't exist on the row, so they
                // always resolved to null and the UI showed blank dates.
                'startDate' => $t->term_start_date ?? null,
                'endDate'   => $t->term_end_date ?? null,
                'premium'   => $t->premium ?? null,
                'status'    => $t->status ?? null,
                'createdAt' => $t->created_at,
            ]);

        return response()->json(['data' => $terms]);
    }

    /**
     * Lazy tab: Reinsurance — pivoted by RI type, matching graphiteBWV8 TmpTable display.
     *
     * Returns rows grouped by coverage (motor) or risk+group (non-motor), with columns
     * for each RI type: Net Retention, Quota Share, Surplus, Facultative, Fac Placement.
     */
    public function reinsurance(int $id): JsonResponse
    {
        $policy = Policy::select('id')->findOrFail($id);

        // Get latest action_id with existing reinsurance rows
        $latestActionId = DB::table('policy_reinsurance')
            ->where('policy_id', $id)
            ->whereNull('deleted_at')
            ->max('action_id');

        // Lazy-compute: if no rows exist, run the legacy mapping once for
        // the policy's most recent ISSUED/APPROVED action. Mirrors
        // graphiteBWV8 Reinsurance/View.php:29.
        //
        // Cap at small policies only. Big policies (e.g. COMG2024112441
        // with 178 coverages) drive this past Cloudflare's 60 s gateway
        // timeout and produce a 504 on a plain page load — the user
        // never gets to click anything. For those, return the empty
        // response + hint; the Recalculate button already handles the
        // async path via dispatchAfterResponse in recalculateReinsurance.
        // Same threshold (80) as the recalculate endpoint so behaviour
        // is consistent between read-path and write-path triggers.
        $bigPolicyThreshold = 80;
        if (!$latestActionId) {
            $targetAction = DB::table('policy_actions')
                ->where('policy_id', $id)
                ->whereIn('status', ['ISSUED', 'APPROVED'])
                ->orderByDesc('id')->first(['id', 'term_id']);
            if ($targetAction && method_exists(\AlphaDirect\Models\PolicyCoverage::class, 'getReinsuranceCoverageCalculations')) {
                $coverageCount = DB::table('policy_coverages')
                    ->where('policy_id', $id)
                    ->where('action_id', $targetAction->id)
                    ->whereNull('deleted_at')
                    ->count();

                if ($coverageCount <= $bigPolicyThreshold) {
                    try {
                        \AlphaDirect\Models\PolicyCoverage::getReinsuranceCoverageCalculations(
                            $id, $targetAction->term_id, $targetAction->id
                        );
                        $latestActionId = DB::table('policy_reinsurance')
                            ->where('policy_id', $id)->whereNull('deleted_at')->max('action_id');
                    } catch (\Throwable $e) {
                        Log::warning("reinsurance lazy-compute failed for policy {$id}: " . $e->getMessage());
                    }
                } else {
                    Log::info("reinsurance GET: skipping lazy-compute for big policy", [
                        'policy_id' => $id, 'coverage_count' => $coverageCount,
                    ]);
                }
            }
        }

        if (!$latestActionId) {
            // Shape the hint so the frontend can tell "never computed" apart
            // from "big policy, click Recalculate". Either way the UI shows
            // the Compute button; this lets us tune the copy per case.
            $coverageCount = null;
            if (isset($targetAction)) {
                $coverageCount = DB::table('policy_coverages')
                    ->where('policy_id', $id)
                    ->where('action_id', $targetAction->id)
                    ->whereNull('deleted_at')
                    ->count();
            }
            return response()->json([
                'data' => [],
                'hint' => $coverageCount && $coverageCount > $bigPolicyThreshold
                    ? "Big policy ({$coverageCount} coverages) — lazy-compute skipped to avoid gateway timeout. Click Recalculate to run it in the background."
                    : 'No reinsurance mapping exists yet. Issue the policy or click Recalculate on the Reinsurance tab to generate it.',
                'coverage_count' => $coverageCount,
            ]);
        }

        // BOTH TAB QUERIES NOW LIVE IN CessionSource, MOVED NOT REWRITTEN --
        // including the parts that look wrong: the motor branch's SUM(DISTINCT),
        // which collapses two vehicles carrying identical figures into one, and
        // the non-motor branch's nil-premium filter that the motor branch does
        // not apply. Preserved exactly, because a move that fixes things on the
        // way past cannot tell you whether the move itself was safe.
        //
        // Proved identical to the original pair before wiring: 50 policy/action
        // pairs, zero differences -- database/manual/prove_action_seam_equivalence.php.
        // On the legacy basis this changes nothing; it follows the flag instead
        // of being hard-wired to one table.
        $rowsByBranch = app(CessionSource::class)
            ->layerRowsForAction((int) $id, (int) $latestActionId);
        $motorData    = $rowsByBranch['motor'];
        $nonMotorData = $rowsByBranch['non_motor'];

        $fmt = fn($v) => $v !== null ? number_format((float) $v, 2, '.', ',') : '0.00';

        $rows = collect(array_merge($motorData, $nonMotorData))->map(fn($r) => [
            'riskAddress'          => $r->address_name,
            'groupCode'            => $r->group_code,
            'formulaName'          => $r->formula_name,
            'treatyName'           => $r->treaty_name,
            'treatyNumber'         => $r->treaty_number,
            'totalSumInsured'      => $fmt($r->totalSumInsured),
            'totalPremium'         => $fmt($r->totalPremium),
            'netRetention'         => $fmt($r->NETRETENTION),
            'netRetentionSI'       => $fmt($r->NETRETENTION_SI),
            'quotaShare'           => $fmt($r->QUOTASHARING),
            'quotaShareSI'         => $fmt($r->QUOTASHARING_SI),
            'surplus'              => $fmt($r->SURPLUS),
            'surplusSI'            => $fmt($r->SURPLUS_SI),
            'facultative'          => $fmt($r->FACULTATIVE),
            'facultativeSI'        => $fmt($r->FACULTATIVE_SI),
            'facPlacement'         => $fmt($r->FACULATIVEPLACEMENT),
            'facPlacementSI'       => $fmt($r->FACULATIVEPLACEMENT_SI),
        ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * POST /policies/{id}/reinsurance/recalculate
     * Runs PolicyCoverage::getReinsuranceCoverageCalculations to regenerate
     * the per-policy treaty mapping (NetRetention / QuotaShare / Surplus /
     * Facultative / FacPlacement split by risk + coverage group).
     *
     * Optional body: action_id (defaults to latest ISSUED/APPROVED action).
     */
    public function recalculateReinsurance(Request $request, int $id): JsonResponse
    {
        // Increase timeout limits for reinsurance calculation
        @set_time_limit(300); // 5 minutes for script execution
        ini_set('max_execution_time', 300);

        $policy = Policy::findOrFail($id);
        $actionId = $request->input('action_id');
        if (!$actionId) {
            // Get the latest action for this policy (any status)
            $action = DB::table('policy_actions')
                ->where('policy_id', $id)
                ->orderByDesc('id')->first(['id', 'term_id']);
        } else {
            $action = DB::table('policy_actions')
                ->where('id', $actionId)->where('policy_id', $id)
                ->first(['id', 'term_id']);
        }
        if (!$action) {
            return response()->json(['error' => 'No policy action found for this policy.'], 404);
        }

        if (!method_exists(\AlphaDirect\Models\PolicyCoverage::class, 'getReinsuranceCoverageCalculations')) {
            return response()->json(['error' => 'Reinsurance calculator not available on this deployment.'], 500);
        }

        // Validate reinsurance configuration before proceeding
        try {
            $today = now()->format('Y-m-d');

            $treaties = DB::table('reinsurance_treaty')
                ->where('effective_from', '<=', $today)
                ->where('effective_to', '>=', $today)
                ->where('status', 1) // active only
                ->count();

            if ($treaties === 0) {
                Log::warning("Reinsurance calculation: no active treaties found for today", [
                    'policy_id' => $id,
                    'today' => $today
                ]);
                return response()->json([
                    'error' => 'No active reinsurance treaties for today. Check Reinsurance → Treaties (effective dates and status).',
                    'code' => 'NO_ACTIVE_TREATIES',
                    'hint' => "Check if any treaty is active between {$today}. Treaties must have status=1 and effective_from <= {$today} <= effective_to"
                ], 400);
            }

            // Also check formulas exist
            $formulas = DB::table('reinsurance_formula')->count();
            if ($formulas === 0) {
                Log::warning("Reinsurance calculation: no formulas configured", ['policy_id' => $id]);
                return response()->json([
                    'error' => 'No reinsurance formulas configured. Create formulas in Reinsurance → Formulas.',
                    'code' => 'NO_FORMULAS'
                ], 400);
            }

            Log::info("Reinsurance check passed: {$treaties} active treaties, {$formulas} formulas", ['policy_id' => $id]);
        } catch (\Exception $e) {
            Log::error("Error validating reinsurance config: " . $e->getMessage(), [
                'policy_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error validating reinsurance configuration: ' . $e->getMessage(),
                'code' => 'CONFIG_VALIDATION_ERROR'
            ], 500);
        }

        // Reinsurance calculations are slow and unpredictable depending on
        // number of coverages, treaties, and formula complexity. Always run
        // via dispatchAfterResponse (background) so the request returns
        // immediately and doesn't hit Cloudflare's gateway timeout.
        // Client polls /reinsurance to see updated rows.
        $coverageCount = DB::table('policy_coverages')
            ->where('policy_id', $id)
            ->where('action_id', $action->id)
            ->whereNull('deleted_at')
            ->count();

        // Capture these before entering the deferred closure:
        //   - auth() is gone once dispatchAfterResponse / App::terminating fires,
        //     so userId has to be resolved up front to address the notification.
        //   - policyNumber is used in the notification title for legibility.
        // Without this capture the notification silently no-ops (user_id=0)
        // and the user never learns the background job finished.
        $userId        = auth()->id();
        $policyNumber  = (string) ($policy->policyNumber ?? $policy->policy_number ?? "#{$id}");

        $runJob = function () use ($id, $action, $userId, $policyNumber) {
            // Serialize compute per policy+action. Overlapping runs collide on
            // policy_reinsurance row locks — the soft-delete UPDATE below locks
            // every row for the whole (slow) recompute, so a second concurrent
            // run for the same policy+action dies with MySQL 1205 (lock wait
            // timeout). A duplicate run is redundant anyway (it recomputes the
            // identical rows), so skip it instead of fighting for the lock.
            // File-cache atomic lock (supported in Laravel 8); 30-min safety
            // TTL sits well above worst-case compute and is released in finally
            // regardless. This changes NOTHING about the calculation itself —
            // when a run executes, it runs the exact same transaction as before.
            $lock = \Cache::lock("reinsurance:{$id}:{$action->id}", 1800);
            if (! $lock->get()) {
                Log::info("recalculateReinsurance skipped — a compute is already running", [
                    'policy_id' => $id, 'action_id' => $action->id,
                ]);
                if ($userId) {
                    try {
                        \AlphaDirect\Services\NotificationDispatcher::send(
                            $userId,
                            'reinsurance_empty',
                            [
                                'title'     => "Reinsurance already computing — {$policyNumber}",
                                'message'   => "A reinsurance calculation for {$policyNumber} is already running; this duplicate request was skipped. Refresh the Reinsurance tab shortly.",
                                'policy_id' => $id,
                                'action_id' => $action->id,
                            ],
                            "/policies/{$id}?tab=reinsurance",
                            ['in_app']
                        );
                    } catch (\Throwable) {
                        // best-effort — already logged above
                    }
                }
                return;
            }

            $started = microtime(true);
            try {
                DB::transaction(function () use ($id, $action) {
                    DB::table('policy_reinsurance')
                        ->where('policy_id', $id)
                        ->where('action_id', $action->id)
                        ->whereNull('deleted_at')
                        ->update(['deleted_at' => now(), 'updated_at' => now()]);

                    \AlphaDirect\Models\PolicyCoverage::getReinsuranceCoverageCalculations(
                        $id, $action->term_id, $action->id
                    );
                });

                // Success notification — persisted so it survives tab reloads,
                // surfaces in the bell + as a toast via the 60 s poll.
                $rowCount = DB::table('policy_reinsurance')
                    ->where('policy_id', $id)
                    ->where('action_id', $action->id)
                    ->whereNull('deleted_at')
                    ->count();
                $elapsed = round(microtime(true) - $started, 1);
                if ($userId) {
                    $data = [
                        'title'     => $rowCount > 0
                            ? "Reinsurance computed — {$policyNumber}"
                            : "Reinsurance finished with no rows — {$policyNumber}",
                        'message'   => $rowCount > 0
                            ? "{$rowCount} allocation row(s) written for policy {$policyNumber} in {$elapsed}s."
                            : "Calculation ran for {$policyNumber} but wrote 0 rows — check treaty/formula config on the Reinsurance tab.",
                        'policy_id' => $id,
                        'action_id' => $action->id,
                        'row_count' => $rowCount,
                        'elapsed_s' => $elapsed,
                    ];
                    // Use the dispatcher so the template + channel rules apply
                    // uniformly with other notifications. Fall back to a direct
                    // insert if the dispatcher throws — losing the notification
                    // would be worse than losing template formatting.
                    try {
                        \AlphaDirect\Services\NotificationDispatcher::send(
                            $userId,
                            $rowCount > 0 ? 'reinsurance_computed' : 'reinsurance_empty',
                            $data,
                            "/policies/{$id}?tab=reinsurance",
                            ['in_app'] // toast + bell only; no email spam
                        );
                    } catch (\Throwable $nx) {
                        DB::connection('mysql_system')->table('notifications')->insert([
                            'user_id'    => $userId,
                            'type'       => $rowCount > 0 ? 'reinsurance_computed' : 'reinsurance_empty',
                            'data'       => json_encode($data),
                            'action'     => "/policies/{$id}?tab=reinsurance",
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("recalculateReinsurance failed: " . $e->getMessage(), [
                    'policy_id' => $id, 'action_id' => $action->id,
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // A lock-wait timeout / deadlock is a transient contention
                // condition, not a config or data problem — surface a plain
                // "busy, retry" message instead of the raw SQLSTATE[HY000] 1205.
                $isLockWait  = \Illuminate\Support\Str::contains(
                    $e->getMessage(), ['Lock wait timeout', '1205', 'Deadlock']
                );
                $failMessage = $isLockWait
                    ? "Reinsurance is busy — another calculation is running for {$policyNumber}. Please retry in a moment."
                    : "Could not compute reinsurance for {$policyNumber}: "
                        . \Illuminate\Support\Str::limit($e->getMessage(), 200);

                // Failure notification so the user doesn't sit on a hung
                // "Computing…" UI forever. The only hint they'd otherwise
                // have is an empty tab on reload — exactly the regression
                // the user reported.
                if ($userId) {
                    try {
                        \AlphaDirect\Services\NotificationDispatcher::send(
                            $userId,
                            'reinsurance_failed',
                            [
                                'title'     => "Reinsurance failed — {$policyNumber}",
                                'message'   => $failMessage,
                                'policy_id' => $id,
                                'action_id' => $action->id,
                                'error'     => $e->getMessage(),
                            ],
                            "/policies/{$id}?tab=reinsurance",
                            ['in_app']
                        );
                    } catch (\Throwable) {
                        // best-effort — already logged above
                    }
                }
            } finally {
                // Release the per-policy compute lock as soon as this run ends
                // (success OR failure), regardless of the safety TTL, so the
                // next legitimate compute can start immediately. Also clear the
                // "running" flag the Reinsurance tab polls — so the UI flips to
                // "computed" only NOW, after the transaction has committed and
                // the new rows are visible (not while stale rows are still up).
                $lock->release();
                \Cache::forget("reinsurance:running:{$id}:{$action->id}");
            }
        };

        // Mark this policy+action as "computing" BEFORE the response returns,
        // so the tab's status poll can never race the deferred job's start (the
        // job runs on App::terminating, i.e. AFTER this response). The job clears
        // it in finally once its transaction commits. A skipped duplicate leaves
        // it set — the in-flight run that owns the lock clears it. TTL matches the
        // lock so a killed worker can't wedge the flag forever.
        \Cache::put("reinsurance:running:{$id}:{$action->id}", true, 1800);

        // Always run async via App::terminating so the request returns
        // immediately without hitting timeout limits. Client polls /reinsurance
        // tab to see results as they're calculated.
        @set_time_limit(0);
        ignore_user_abort(true);
        \Illuminate\Support\Facades\App::terminating($runJob);

        activity('Policy')->performedOn($policy)->causedBy(auth()->user())
            ->log("Reinsurance recalculation queued for action {$action->id} ({$coverageCount} coverages).");

        return response()->json([
            'message'        => "Reinsurance recalculation started in the background. Refresh the Reinsurance tab in a few moments to see the results.",
            'action_id'      => $action->id,
            'coverage_count' => $coverageCount,
            'queued'         => true,
        ], 202);
    }

    /**
     * GET /policies/{id}/reinsurance/status
     * Lightweight poll target for the Reinsurance tab. Returns whether a
     * background compute is still running for this policy's latest action
     * (the "reinsurance:running:*" cache flag set by recalculateReinsurance)
     * plus the current allocation row count.
     *
     * Why the tab needs this: a RE-compute soft-deletes the old rows INSIDE
     * its transaction, so a separate read still sees them until commit. The
     * tab therefore can't tell "the new run finished" from "rows exist" — it
     * would flash "computed" on stale rows. Polling `running` fixes that: the
     * flag is set before the recalc response returns and cleared only when the
     * compute's transaction commits, so the tab reflects the fresh numbers and
     * shows success at the same moment.
     */
    public function reinsuranceStatus(Request $request, int $id): JsonResponse
    {
        $actionId = $request->input('action_id');
        if (!$actionId) {
            $action = DB::table('policy_actions')
                ->where('policy_id', $id)
                ->orderByDesc('id')->first(['id']);
            $actionId = $action->id ?? null;
        }
        if (!$actionId) {
            return response()->json(['running' => false, 'rowCount' => 0, 'actionId' => null]);
        }

        $rowCount = DB::table('policy_reinsurance')
            ->where('policy_id', $id)
            ->where('action_id', $actionId)
            ->whereNull('deleted_at')
            ->count();

        return response()->json([
            'running'  => \Cache::has("reinsurance:running:{$id}:{$actionId}"),
            'rowCount' => $rowCount,
            'actionId' => (int) $actionId,
        ]);
    }

    /**
     * Lazy tab: Engineering/Specialist/Marine product-specific coverages.
     * Returns data from product-type-specific tables (ear_coverages, medical_malpractice_coverages, etc.)
     */
    public function specialistCoverages(int $id): JsonResponse
    {
        $policy = Policy::select('id', 'product_id')->findOrFail($id);

        // Map product_id to coverage table names
        $productTableMap = [
            // Engineering products
            16 => [
                ['table' => 'ear_coverages',               'label' => 'EAR Coverage'],
                ['table' => 'car_coverages',               'label' => 'CAR Coverage'],
                ['table' => 'par_coverages',               'label' => 'PAR Coverage'],
                ['table' => 'machinery_breakdown_coverages','label' => 'Machinery Breakdown'],
            ],
            18 => [
                ['table' => 'ear_coverages',               'label' => 'EAR Coverage'],
                ['table' => 'car_coverages',               'label' => 'CAR Coverage'],
                ['table' => 'par_coverages',               'label' => 'PAR Coverage'],
                ['table' => 'machinery_breakdown_coverages','label' => 'Machinery Breakdown'],
            ],
            // Specialist products
            17 => [
                ['table' => 'medical_malpractice_coverages',   'label' => 'Medical Malpractice'],
                ['table' => 'professional_indemnity_coverages', 'label' => 'Professional Indemnity'],
                ['table' => 'marine_cargo_once_off_coverages',  'label' => 'Marine Cargo Once Off'],
                ['table' => 'marine_cargo_open_coverages',      'label' => 'Marine Cargo Open'],
                ['table' => 'marine_directors_officers_coverages','label' => 'Directors & Officers'],
            ],
            19 => [
                ['table' => 'medical_malpractice_coverages',   'label' => 'Medical Malpractice'],
                ['table' => 'professional_indemnity_coverages', 'label' => 'Professional Indemnity'],
                ['table' => 'marine_cargo_once_off_coverages',  'label' => 'Marine Cargo Once Off'],
                ['table' => 'marine_cargo_open_coverages',      'label' => 'Marine Cargo Open'],
                ['table' => 'marine_directors_officers_coverages','label' => 'Directors & Officers'],
            ],
            // Commercial Liabilities
            20 => [
                ['table' => 'medical_malpractice_coverages',   'label' => 'Medical Malpractice'],
                ['table' => 'professional_indemnity_coverages', 'label' => 'Professional Indemnity'],
                ['table' => 'marine_directors_officers_coverages','label' => 'Directors & Officers'],
                ['table' => 'environmental_liability_coverages', 'label' => 'Environmental Liability'],
            ],
            // Miscellaneous
            24 => [
                ['table' => 'medical_evacuation_coverages', 'label' => 'Medical Evacuation'],
                ['table' => 'commercial_crime_coverages',   'label' => 'Commercial Crime'],
            ],
            // Guarantee
            23 => [
                ['table' => 'bonds_coverages', 'label' => 'Bonds and Guarantees'],
            ],
        ];

        $tables = $productTableMap[$policy->product_id] ?? [];
        $result = [];

        foreach ($tables as $spec) {
            try {
                $rows = DB::table($spec['table'])
                    ->where('policy_id', $id)
                    ->get();

                if ($rows->isNotEmpty()) {
                    $result[] = [
                        'label' => $spec['label'],
                        'table' => $spec['table'],
                        'rows'  => $rows->map(fn($r) => (array) $r)->values(),
                    ];
                }
            } catch (\Exception $e) {
                // Table might not exist in current DB — skip gracefully
            }
        }

        return response()->json(['data' => $result]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/policies/{id}/client-health   (new — full payload)
    // GET /api/v1/policies/{id}/health-summary  (legacy — subset alias)
    //
    // Both endpoints delegate to ClientHealthService::summarise() so the
    // numbers always agree. The legacy route maps the rich payload back
    // to the v1 shape so existing FE callers keep working unchanged.
    //
    // The rich payload (clientHealth) adds:
    //   - paymentMethod {method, bucket, source}
    //   - balanceOwing.headline + .source
    //   - activeClaims.totalReserve + .totalPayment
    //   - banner {severity, message}
    // ──────────────────────────────────────────────────────────────

    /**
     * Full Client Health Widget payload — payment-method aware.
     */
    public function clientHealth(int $id, \AlphaDirect\Services\ClientHealthService $service): JsonResponse
    {
        try {
            $payload = $service->summarise($id);
        } catch (\Throwable $e) {
            \Log::error('clientHealth.summarise_failed', [
                'policy_id' => $id,
                'error'     => $e->getMessage(),
                'at'        => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json([
                'message' => 'Client health summary temporarily unavailable.',
                'error'   => $e->getMessage(),
            ], 500);
        }
        if ($payload === null) {
            return response()->json(['message' => 'Policy not found.'], 404);
        }
        return response()->json(['data' => $payload]);
    }

    /**
     * Legacy alias — narrower payload preserved for any caller still on
     * the v1 endpoint. Internally identical computation.
     */
    public function healthSummary(int $id, \AlphaDirect\Services\ClientHealthService $service): JsonResponse
    {
        $payload = $service->summarise($id);
        if ($payload === null) {
            return response()->json(['message' => 'Policy not found.'], 404);
        }

        return response()->json([
            'data' => [
                'balanceOwing' => [
                    'realpayUnpaid' => $payload['balanceOwing']['realpayUnpaid'] ?? 0.0,
                    'ledgerNet'     => $payload['balanceOwing']['ledgerNet'],
                ],
                'daysInArrears' => $payload['daysInArrears'],
                'collectionStatus' => $payload['collectionStatus'],
                'activeClaims' => [
                    'count' => $payload['activeClaims']['count'],
                ],
            ],
        ]);
    }
}
