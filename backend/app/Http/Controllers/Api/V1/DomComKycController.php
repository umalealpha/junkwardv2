<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Support\KycDomComProducts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DOM/COM-tier KYC review (products 7, 8, 16, 17, 18, 19, 20, 22).
 *
 * Mirrors the MIS-tier CustomerKycController API shape so the React
 * frontend can share UI patterns. Reads document statuses from
 * customer_kyc_dom_com primarily, plus a few shared columns
 * (proof_residence, proof_income, omang, passport) that also live on
 * customer_kyc and feed product-7/8 compliance per V8's
 * CustomerController::checkKycComplianceStatus().
 *
 * Endpoints registered in routes/api_v1.php:
 *   GET  /api/v1/dom-com-kyc                          → index()
 *   GET  /api/v1/dom-com-kyc/{kycId}/detail           → detail()
 *   POST /api/v1/dom-com-kyc/{kycId}/verify-document  → verifyDocument()
 *   POST /api/v1/dom-com-kyc/{kycId}/status           → updateStatus()
 *
 * NOTE: the {kycId} route param is customer_kyc.id (the KYC row PK), NOT
 * customer.id — the list links each row by its KYC row, and a customer
 * can own several customer_kyc rows. detail() resolves the DomCom sibling
 * via customer_kyc_dom_com.customer_kyc_id.
 */
class DomComKycController extends Controller
{
    /**
     * Document catalogue. Each entry maps the API key to the underlying
     * file / status / remark columns. The set rendered for a given
     * customer depends on whether their DOM/COM policies are Domestic
     * (individual) or Commercial (corporate) — see DOMESTIC_KEYS /
     * COMMERCIAL_KEYS / SHARED_KEYS below. V8's
     * `verifyKYCInfoForDomCom` + `checkKycComplianceStatus`
     * (CustomerController.php:2712 / 3210) use the same per-product
     * branching.
     */
    /**
     * Doc catalogue. `expiry` (where present) maps the catalogue key to
     * the underlying column that stores the document's expiry date.
     * Front/Back of the same ID share a single expiry column because
     * they represent the same physical document — V8 stored Omang
     * expiry once (omangExpiry) regardless of which side was uploaded.
     */
    private const DOC_CATALOGUE = [
        // ── Shared identity docs (stored on customer_kyc) ─────────────
        'omangFront'      => ['table' => 'kyc',         'label' => 'Omang (Front)',                'file' => 'omang',           'status' => 'omangFrontStatus',        'remark' => 'omangFrontRemark',         'expiry' => 'omangExpiry'],
        'omangBack'       => ['table' => 'kyc',         'label' => 'Omang (Back)',                 'file' => 'omangBack',       'status' => 'omangBackStatus',         'remark' => 'omangBackRemark',          'expiry' => 'omangExpiry'],
        'passport'        => ['table' => 'kyc',         'label' => 'Passport',                     'file' => 'passport',        'status' => 'passportStatus',          'remark' => 'passportRemark',           'expiry' => 'passportExpiry'],
        'driving_license' => ['table' => 'kyc',         'label' => 'Driving License',              'file' => 'driving_license', 'status' => 'driving_licenseStatus',   'remark' => 'driving_licenseRemark',    'expiry' => 'licenseExpiry'],
        'proof_residence' => ['table' => 'kyc',         'label' => 'Proof of Residence',           'file' => 'proof_residence', 'status' => 'proof_residenceStatus',   'remark' => 'proof_residenceRemark'],
        'proof_income'    => ['table' => 'kyc',         'label' => 'Proof of Income',              'file' => 'proof_income',    'status' => 'proof_incomeStatus',      'remark' => 'proof_incomeRemark'],

        // ── Corporate / entity docs (stored on customer_kyc_dom_com) ──
        'kyc_form'                     => ['table' => 'kyc_dom_com', 'label' => 'KYC Form',                       'file' => 'kyc_form',                     'status' => 'kyc_form_status',                     'remark' => 'kyc_form_remark'],
        'data_protection_form'         => ['table' => 'kyc_dom_com', 'label' => 'Data Protection Form',           'file' => 'data_protection_form',         'status' => 'data_protection_form_status',         'remark' => 'data_protection_form_remark'],
        'certificate_of_incorporation' => ['table' => 'kyc_dom_com', 'label' => 'Certificate of Incorporation/Registration', 'file' => 'certificate_of_incorporation', 'status' => 'certificate_of_incorporation_status', 'remark' => 'certificate_of_incorporation_remark'],
        'extract_controllers'          => ['table' => 'kyc_dom_com', 'label' => 'Extract Controllers and Ownership Structure', 'file' => 'extract_controllers', 'status' => 'extract_controllers_status',          'remark' => 'extract_controllers_remark'],
        'resolution'                   => ['table' => 'kyc_dom_com', 'label' => 'Resolution',                     'file' => 'resolution',                   'status' => 'resolution_status',                   'remark' => 'resolution_remark'],
        'proof_business_address'       => ['table' => 'kyc_dom_com', 'label' => 'Proof of Business Address',      'file' => 'proof_business_address',       'status' => 'proof_business_address_status',       'remark' => 'proof_business_address_remark'],
        'proof_residential_address'    => ['table' => 'kyc_dom_com', 'label' => 'Proof of Residential Address for Directors and Shareholders', 'file' => 'proof_residential_address',    'status' => 'proof_residential_address_status',    'remark' => 'proof_residential_address_remark'],
        'directors_id_front'           => ['table' => 'kyc_dom_com', 'label' => 'Directors ID Front',             'file' => 'directors_id_front',           'status' => 'directors_id_front_status',           'remark' => 'directors_id_front_remark',           'expiry' => 'directorsIDExp'],
        'directors_id_back'            => ['table' => 'kyc_dom_com', 'label' => 'Directors ID Back',              'file' => 'directors_id_back',            'status' => 'directors_id_back_status',            'remark' => 'directors_id_back_remark',            'expiry' => 'directorsIDExp'],
        'directors_passport'           => ['table' => 'kyc_dom_com', 'label' => 'Directors Passport',             'file' => 'directors_passport',           'status' => 'directors_passport_status',           'remark' => 'directors_passport_remark',           'expiry' => 'directorsPassportExp'],
        'shareholders_id_front'        => ['table' => 'kyc_dom_com', 'label' => 'Shareholders ID Front',          'file' => 'shareholders_id_front',        'status' => 'shareholders_id_front_status',        'remark' => 'shareholders_id_front_remark',        'expiry' => 'shareholdersIDExp'],
        'shareholders_id_back'         => ['table' => 'kyc_dom_com', 'label' => 'Shareholders ID Back',           'file' => 'shareholders_id_back',         'status' => 'shareholders_id_back_status',         'remark' => 'shareholders_id_back_remark',         'expiry' => 'shareholdersIDExp'],
        'shareholders_passport'        => ['table' => 'kyc_dom_com', 'label' => 'Shareholders Passport',          'file' => 'shareholders_passport',        'status' => 'shareholders_passport_status',        'remark' => 'shareholders_passport_remark',        'expiry' => 'shareholdersPassportExp'],
    ];

    /**
     * Documents shown for Domestic (individual) policies. Mirrors the
     * V8 DOMG blade screenshot: Omang + Passport + Proof Residence +
     * Proof Income + KYC Form + Data Protection Form. (driving_license
     * is included only when the customer holds a motor-bearing policy —
     * V8 surfaces it as "Drivers license is mandatory for the customers
     * holding policies with vehicle" but doesn't show the doc itself
     * here.) Order matches the V8 blade.
     */
    private const DOMESTIC_KEYS = [
        'omangFront',
        'omangBack',
        'passport',
        'proof_residence',
        'proof_income',
        'kyc_form',
        'data_protection_form',
    ];

    /**
     * Documents shown for Commercial (corporate) policies. Mirrors the
     * V8 COMG blade screenshot exactly. Order matches V8.
     */
    private const COMMERCIAL_KEYS = [
        'certificate_of_incorporation',
        'extract_controllers',
        'resolution',
        'proof_business_address',
        'proof_residential_address',
        'directors_id_front',
        'directors_id_back',
        'directors_passport',
        'shareholders_id_front',
        'shareholders_id_back',
        'shareholders_passport',
        'kyc_form',
        'data_protection_form',
    ];

    /**
     * GET /dom-com-kyc — paginated list of customers with at least one
     * DOM/COM-tier policy.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'     => 'nullable|string|max:50',
            'compliance' => 'nullable|integer',
            'search'     => 'nullable|string|max:100',
            'per_page'   => 'nullable|integer|min:5|max:100',
        ]);

        // Customers with DOM/COM-tier policies, joined to their KYC
        // (left join — a DOM/COM customer may not have a customer_kyc
        // row yet on a brand-new policy).
        $customerIds = DB::table('policies')
            ->whereIn('product_id', KycDomComProducts::IDS)
            ->where('status', 1)
            ->distinct()
            ->pluck('customer_id');

        if ($customerIds->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'total' => 0, 'per_page' => $validated['per_page'] ?? 25,
                    'current_page' => 1, 'last_page' => 1, 'from' => null, 'to' => null,
                ],
            ]);
        }

        $query = DB::table('customer as c')
            ->leftJoin('customer_kyc as k', 'k.customer_id', '=', 'c.id')
            ->leftJoin('customer_kyc_dom_com as kd', 'kd.customer_id', '=', 'c.id')
            // Pull entity_type + company_id so the row can swap to the
            // company name when the customer is registered as an
            // Organisation (V8 verifyDomCom parity).
            ->leftJoin('customer_profile as cp', 'cp.customer_id', '=', 'c.id')
            ->whereIn('c.id', $customerIds)
            ->select([
                'c.id as customer_id', 'c.firstName', 'c.lastName', 'c.cellphone', 'c.email',
                'c.company_id',
                'cp.entity_type',
                'k.id as kyc_id', 'k.compliance', 'k.status', 'k.remark', 'k.updated_at as kyc_updated_at',
                'kd.id as dom_com_id', 'kd.kyc_form', 'kd.certificate_of_incorporation',
                'kd.directors_id_front', 'kd.shareholders_id_front',
            ])
            ->when($validated['status'] ?? null, fn($q, $v) => $q->where('k.status', $v))
            ->when(isset($validated['compliance']), fn($q) => $q->where('k.compliance', $validated['compliance']))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $search = trim(preg_replace('/\s+/', ' ', $search));
                $like = "%{$search}%";
                $words = count(explode(' ', $search)) >= 2
                    ? array_values(array_filter(explode(' ', $search)))
                    : [];
                $q->where(function ($q) use ($like, $words) {
                    $q->where('c.firstName', 'like', $like)
                      ->orWhere('c.lastName', 'like', $like)
                      ->orWhereRaw("CONCAT_WS(' ', TRIM(c.firstName), TRIM(c.lastName)) LIKE ?", [$like])
                      ->orWhere('c.cellphone', 'like', $like)
                      ->orWhere('c.email', 'like', $like);
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
            ->orderBy('c.id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        // Batch-resolve company names for the visible page only — cheap
        // and avoids N+1 vs per-row lookups.
        $companyIds = collect($results->items())
            ->pluck('company_id')->filter()->unique()->values()->all();
        $companyNameById = (!empty($companyIds) && \Schema::hasTable('companies'))
            ? DB::table('companies')->whereIn('id', $companyIds)->pluck('name', 'id')->toArray()
            : [];

        return response()->json([
            'data' => collect($results->items())->map(function ($r) use ($companyNameById) {
                $personName = trim(($r->firstName ?? '') . ' ' . ($r->lastName ?? ''));
                $companyName = (($r->entity_type ?? null) === 'Organisation' && !empty($r->company_id))
                    ? ($companyNameById[$r->company_id] ?? null)
                    : null;
                $displayName = $companyName ? ucwords($companyName) : $personName;
                return [
                    'id'              => $r->kyc_id ?? $r->customer_id,
                    'customerId'      => $r->customer_id,
                    'customerName'    => $displayName,
                    'companyName'     => $companyName,
                    'isCompany'       => !empty($companyName),
                    'cellphone'       => $r->cellphone,
                    'email'           => $r->email,
                    'compliance'      => $r->compliance,
                    'status'          => $r->status,
                    'remark'          => $r->remark,
                    'hasKycForm'                  => !empty($r->kyc_form),
                    'hasCertificateIncorporation' => !empty($r->certificate_of_incorporation),
                    'hasDirectorsIdFront'         => !empty($r->directors_id_front),
                    'hasShareholdersIdFront'      => !empty($r->shareholders_id_front),
                    'updatedAt'       => $r->kyc_updated_at,
                ];
            }),
            'meta' => [
                'total'        => $results->total(),
                'per_page'     => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
                'from'         => $results->firstItem(),
                'to'           => $results->lastItem(),
            ],
        ]);
    }

    /**
     * GET /dom-com-kyc/{customerId}/detail — full DOM/COM KYC record.
     */
    public function detail(int $kycId): JsonResponse
    {
        // Anchor on customer_kyc.id (the {kycId} route param), mirroring
        // the MIS CustomerKycController. A customer can own several
        // customer_kyc rows (a registration stub + the policy-upload row),
        // so keying the lookup on the row PK — not customer_id — is what
        // guarantees we land on the exact row the reviewer clicked in the
        // list, instead of an ambiguous ->first() that often picked the
        // empty stub and hid the identity docs.
        $kyc = DB::table('customer_kyc')->where('id', $kycId)->first();
        if (!$kyc) {
            return response()->json(['error' => 'KYC record not found'], 404);
        }
        $customerId = (int) $kyc->customer_id;

        // Resolve the DomCom row exactly like the policy "KYC Documents" tab
        // (PolicyController::kycDocuments): the LATEST customer_kyc_dom_com
        // row for the CUSTOMER. The review page previously anchored on the
        // back-link (customer_kyc_dom_com.customer_kyc_id = the clicked
        // customer_kyc.id), but when a customer re-uploads stale docs the
        // fresh files land on a newer DomCom row — so the back-linked row
        // went stale and the review page showed older/rejected documents
        // than the policy tab. Reading newest-by-customer_id keeps the two
        // views in lockstep (verifyDocument() resolves the same row, so the
        // verdict is written where detail() reads it).
        $kycDomCom = DB::table('customer_kyc_dom_com')
            ->where('customer_id', $customerId)
            ->orderByDesc('id')
            ->first();

        $customer = DB::table('customer')->where('id', $customerId)
            ->first(['id', 'firstName', 'lastName', 'cellphone', 'email', 'is_blocked', 'block_reason']);

        $profile = DB::table('customer_profile')->where('customer_id', $customerId)
            ->first(['address', 'dob', 'gender', 'company_id', 'entity_type']);

        // DOM/COM customers registered as an Organisation are surfaced
        // under their company name (V8 verifyDomCom parity — the legacy
        // blade title reads the company name when entity_type ==
        // 'Organisation'). Falls through to firstName+lastName for
        // individual-registered DOMG customers.
        $companyName = null;
        if (!empty($profile->company_id)
            && ($profile->entity_type ?? null) === 'Organisation'
            && \Schema::hasTable('companies')
        ) {
            $companyRow = DB::table('companies')
                ->where('id', $profile->company_id)
                ->first(['name']);
            if ($companyRow && !empty($companyRow->name)) {
                $companyName = ucwords($companyRow->name);
            }
        }
        $personName  = trim(($customer->firstName ?? '') . ' ' . ($customer->lastName ?? ''));
        $displayName = $companyName ?: $personName;

        // DOM/COM-tier policies only.
        $policies = DB::table('policies as p')
            ->leftJoin('products as pr', 'pr.id', '=', 'p.product_id')
            ->where('p.customer_id', $customerId)
            ->whereIn('p.product_id', KycDomComProducts::IDS)
            ->select(['p.id', 'p.policyNumber', 'p.product_id', 'pr.name as productName', 'p.status', 'p.policyActivatedDate', 'p.premium'])
            ->orderByDesc('p.id')->limit(20)->get();

        // Determine which doc set(s) apply for this customer.
        //
        // V8 parity:
        //   - Domestic policies (DOMG-prefixed) → Omang/Passport/Proof
        //     of Residence/Proof of Income + KYC Form + Data Protection
        //     Form (7 docs).
        //   - Commercial policies (COMG-prefixed) → Certificate of
        //     Incorporation, Extract Controllers, Resolution, Proof of
        //     business/residential address, Directors / Shareholders
        //     IDs + passports + KYC Form + Data Protection Form (13
        //     docs).
        //   - Mixed customer → union of both sets (KYC Form + Data
        //     Protection Form appear once).
        //
        // Source of truth: the policy number PREFIX (`DOMG` / `COMG`),
        // not `product_id`. Real data shows the same product_id can
        // back either flavor (e.g., product 19 holds 71 DOMG + 3 COMG
        // policies). The prefix is what's printed on the policy
        // schedule, so it's the right hook for "what KYC docs apply".
        // The product_id-based KycDomComProducts::isDomestic /
        // ::isCommercial helpers stay around for general DOM/COM-tier
        // filtering elsewhere, but are not the tier-routing input here.
        $hasDomestic   = false;
        $hasCommercial = false;
        foreach ($policies as $p) {
            $prefix = strtoupper(substr((string) $p->policyNumber, 0, 4));
            if ($prefix === 'DOMG') $hasDomestic = true;
            elseif ($prefix === 'COMG') $hasCommercial = true;
            else {
                // Unknown prefix (MIS2 / hand-issued etc) — fall back
                // to product-id classification so we still pick a set.
                if (KycDomComProducts::isDomestic($p->product_id))   $hasDomestic = true;
                if (KycDomComProducts::isCommercial($p->product_id)) $hasCommercial = true;
            }
        }
        // Defensive fallback: customer has no active DOM/COM policies
        // but has a customer_kyc_dom_com row (cancelled policy etc.).
        // Use uploaded-file fingerprint to guess which doc set was
        // historically in scope so reviewers can audit history.
        if (!$hasDomestic && !$hasCommercial) {
            if ($kycDomCom && (
                !empty($kycDomCom->certificate_of_incorporation) ||
                !empty($kycDomCom->directors_id_front) ||
                !empty($kycDomCom->shareholders_id_front)
            )) {
                $hasCommercial = true;
            } else {
                $hasDomestic = true;
            }
        }

        $keys = [];
        if ($hasDomestic)   $keys = array_merge($keys, self::DOMESTIC_KEYS);
        if ($hasCommercial) $keys = array_merge($keys, self::COMMERCIAL_KEYS);
        $keys = array_values(array_unique($keys));

        $cdnBase = env('AWS_CLOUDFRONT', 'https://d20dgglp0tqnyi.cloudfront.net');

        // Resolve each doc with a per-COLUMN merge that mirrors the policy
        // KYC tab (PolicyController::kycDocuments' $pullCol): the DomCom row
        // wins per-column and customer_kyc fills any gap. File path, status,
        // remark and expiry are each pulled INDEPENDENTLY. The previous code
        // tied status/remark to whichever row held the file and, when NEITHER
        // row carried the file, fell back to the customer_kyc stub's status —
        // which surfaced a stale "Rejected" verdict on proof_residence /
        // proof_income even though the live file (and its real verdict) sat
        // on the DomCom row. Per-column merge removes that coupling and keeps
        // the review page identical to the policy tab. property_exists guards
        // columns present on only one table (corporate columns on DomCom;
        // driving_license on customer_kyc).
        $hasVal = function ($row, string $col): bool {
            return $row && property_exists($row, $col)
                && $row->{$col} !== null && $row->{$col} !== '';
        };
        // DomCom-wins-else-customer_kyc, applied per column.
        $pull = function (string $col) use ($kyc, $kycDomCom, $hasVal) {
            if ($hasVal($kycDomCom, $col)) return $kycDomCom->{$col};
            if ($hasVal($kyc, $col))       return $kyc->{$col};
            return null;
        };

        $documents = [];
        foreach ($keys as $key) {
            if (!isset(self::DOC_CATALOGUE[$key])) continue;
            $meta = self::DOC_CATALOGUE[$key];
            $filePath = $pull($meta['file']);
            // Optional expiry — only the 7 ID/passport/license docs carry
            // one; everything else (proof_residence, kyc_form, certificate
            // of incorporation, etc.) doesn't expire so we omit the field.
            $expiryValue = null;
            if (!empty($meta['expiry'])) {
                $raw = $pull($meta['expiry']);
                // Normalise to YYYY-MM-DD so the FE's `<input type=date>`
                // can pre-fill without parsing — V8 sometimes stored Carbon
                // datetimes here (e.g. "2027-04-12 00:00:00").
                if (!empty($raw)) {
                    $expiryValue = substr((string) $raw, 0, 10);
                }
            }
            $documents[] = [
                'key'        => $key,
                'label'      => $meta['label'],
                'table'      => $meta['table'], // 'kyc' or 'kyc_dom_com' — FE renders a small tag
                'uploaded'   => !empty($filePath),
                'url'        => $filePath ? rtrim($cdnBase, '/') . '/' . ltrim($filePath, '/') : null,
                'status'     => (int) ($pull($meta['status']) ?? 0),
                'remark'     => $pull($meta['remark']),
                // Null when this doc type doesn't have an expiry column;
                // FE uses presence-of-`expiryField` to decide whether to
                // render the date input.
                'expiryField' => $meta['expiry'] ?? null,
                'expiryDate'  => $expiryValue,
            ];
        }

        // Tier hint used by the FE to render the right section labels
        // ("Domestic" / "Commercial" / "Mixed").
        $policyTier = $hasDomestic && $hasCommercial ? 'MIXED'
                    : ($hasCommercial ? 'COMMERCIAL'
                    : ($hasDomestic ? 'DOMESTIC' : 'UNKNOWN'));

        // Sparse multi-director / multi-shareholder document set (BizSure).
        $extraDocs = [];
        if (\Schema::hasTable('policy_kyc_documents')) {
            $extraDocs = DB::table('policy_kyc_documents')
                ->where('customer_id', $customerId)
                ->orderBy('doc_type')->orderBy('doc_index')
                ->get(['id', 'policy_id', 'doc_type', 'doc_index', 'file_path', 'created_at'])
                ->map(fn($d) => [
                    'id'        => $d->id,
                    'policyId'  => $d->policy_id,
                    'docType'   => $d->doc_type,
                    'docIndex'  => $d->doc_index,
                    'url'       => $d->file_path ? rtrim($cdnBase, '/') . '/' . ltrim($d->file_path, '/') : null,
                    'createdAt' => $d->created_at,
                ])
                ->toArray();
        }

        // Activity log — same kyc_activity_log table as MIS, since
        // verifyKYCInfoForDomCom also writes to it.
        $activityLogs = DB::table('kyc_activity_log')
            ->where('customer_id', $customerId)
            ->orderByDesc('id')->limit(50)->get();

        $userIds = $activityLogs->pluck('action_perfomed_by')->filter()->unique()->values()->toArray();
        $users = !empty($userIds)
            ? DB::table('users')->whereIn('id', $userIds)
                ->pluck(DB::raw("CONCAT(firstName, ' ', lastName)"), 'id')->toArray()
            : [];

        // Before-change snapshot column — V8 ships old_data_json; some envs
        // were migrated to old_values. Detect once so the row map below
        // can return it consistently with CustomerKycController.
        $oldCol = \Schema::hasColumn('kyc_activity_log', 'old_data_json')
            ? 'old_data_json'
            : (\Schema::hasColumn('kyc_activity_log', 'old_values') ? 'old_values' : null);
        $decodeJson = function ($value) {
            if ($value === null || $value === '') return null;
            if (is_array($value)) return $value;
            $decoded = json_decode((string) $value, true);
            return is_array($decoded) ? $decoded : null;
        };

        $activityLog = $activityLogs->map(fn($log) => [
            'id'          => $log->id,
            'action'      => $log->log_name,
            'status'      => $log->status,
            'compliance'  => $log->compliance ?? null,
            'description' => $log->description,
            'reason'      => $log->reason ?? null,
            'performedBy' => $users[$log->action_perfomed_by] ?? $log->action_perfomed_by,
            'performedAt' => $log->action_performed_at,
            'oldValues'   => $oldCol ? $decodeJson($log->{$oldCol} ?? null) : null,
        ]);

        return response()->json([
            'customer' => [
                'id'             => $customer->id,
                // Company-first so Organisation-registered DOM/COM
                // customers render as their company on the review header.
                'name'           => $displayName,
                'companyName'    => $companyName,
                'individualName' => $personName,
                'isCompany'      => !empty($companyName),
                'firstName'      => $customer->firstName,
                'lastName'       => $customer->lastName,
                'cellphone'      => $customer->cellphone,
                'email'          => $customer->email,
                'isBlocked'      => $customer->is_blocked == '1',
                'blockReason'    => $customer->block_reason,
                'address'        => $profile->address ?? null,
                'dob'            => $profile->dob ?? null,
                'gender'         => $profile->gender ?? null,
            ],
            'kyc' => [
                'id'             => $kyc->id ?? null,
                'omangNumber'    => $kyc->omangNumber ?? $kycDomCom->omangNumber ?? null,
                'passportNumber' => $kyc->passportNumber ?? $kycDomCom->passportNumber ?? null,
                'compliance'     => $kyc->compliance ?? null,
                'status'         => $kyc->status ?? null,
                'remark'         => $kyc->remark ?? null,
                'performedBy'    => $kyc->performed_by ?? null,
                'approvedDate'   => $kyc->approved_date ?? null,
                'createdAt'      => $kyc->created_at ?? null,
                'updatedAt'      => $kyc->updated_at ?? null,
            ],
            'kycDomCom' => $kycDomCom ? [
                'id'                       => $kycDomCom->id,
                'customerKycId'            => $kycDomCom->customer_kyc_id ?? null,
                'compliance'               => $kycDomCom->compliance,
                'status'                   => $kycDomCom->status,
                'remark'                   => $kycDomCom->remark,
                'reason'                   => $kycDomCom->reason ?? null,
                'approvedDate'             => $kycDomCom->approved_date ?? null,
                'directorsIdExpiry'        => $kycDomCom->directorsIDExp ?? null,
                'directorsPassportExpiry'  => $kycDomCom->directorsPassportExp ?? null,
                'shareholdersIdExpiry'     => $kycDomCom->shareholdersIDExp ?? null,
                'shareholdersPassportExpiry' => $kycDomCom->shareholdersPassportExp ?? null,
            ] : null,
            'documents'   => $documents,
            'extraDocs'   => $extraDocs,
            'policies'    => $policies,
            'activityLog' => $activityLog,
            'policyTier'  => $policyTier, // 'DOMESTIC' | 'COMMERCIAL' | 'MIXED' | 'UNKNOWN'
        ]);
    }

    /**
     * POST /dom-com-kyc/{customerId}/verify-document — approve/reject a
     * single document. Writes to the correct underlying table
     * (customer_kyc vs customer_kyc_dom_com) based on the document key.
     */
    public function verifyDocument(Request $request, int $kycId): JsonResponse
    {
        $allowedKeys = array_keys(self::DOC_CATALOGUE);
        $validated = $request->validate([
            'document'    => ['required', 'string', \Illuminate\Validation\Rule::in($allowedKeys)],
            'status'      => 'required|integer|in:0,1,2', // 0=pending, 1=approved, 2=rejected
            'remark'      => 'nullable|string|max:500',
            // Optional — only meaningful for docs that carry an expiry
            // column (omang/passport/driving_license/directors/
            // shareholders). Ignored silently for the others. Accepts
            // either a YYYY-MM-DD date or the empty string (to clear).
            'expiry_date' => 'nullable|date',
        ]);

        // Anchor on the KYC row PK to derive customer_id, then target the
        // SAME DomCom row detail() reads — the latest customer_kyc_dom_com
        // for the customer (parity with the policy KYC tab). Resolving the
        // verdict's target row identically to the read path guarantees an
        // approval/rejection lands where the review page surfaces it.
        $kyc = DB::table('customer_kyc')->where('id', $kycId)->first(['id', 'customer_id']);
        if (!$kyc) {
            return response()->json(['error' => 'Customer KYC row not found'], 404);
        }
        $customerId = (int) $kyc->customer_id;
        $domComId = DB::table('customer_kyc_dom_com')->where('customer_id', $customerId)->orderByDesc('id')->value('id');

        $meta = self::DOC_CATALOGUE[$validated['document']];

        // A single-row query builder for the given table: customer_kyc by
        // its PK, customer_kyc_dom_com by the located row id.
        $scope = fn(string $t) => $t === 'customer_kyc'
            ? DB::table('customer_kyc')->where('id', $kycId)
            : DB::table('customer_kyc_dom_com')->where('id', $domComId);

        // Resolve the target table by where the file actually lives so the
        // verdict is written beside the document detail() reads it from.
        // DOMG/COMG uploads land the shared identity docs on
        // customer_kyc_dom_com even though the catalogue's primary table is
        // customer_kyc; writing the status to the empty customer_kyc stub
        // would leave the approval invisible on the review page. Fall back
        // to the primary table when neither row carries the file yet
        // (preserves the stub-creation path below).
        $primaryTable  = $meta['table'] === 'kyc' ? 'customer_kyc' : 'customer_kyc_dom_com';
        $fallbackTable = $meta['table'] === 'kyc' ? 'customer_kyc_dom_com' : 'customer_kyc';
        $fileCol  = $meta['file'];
        $hasFileOn = function (string $t) use ($scope, $fileCol, $domComId) {
            if ($t === 'customer_kyc_dom_com' && !$domComId) return false;
            return \Schema::hasColumn($t, $fileCol)
                && $scope($t)->whereNotNull($fileCol)->where($fileCol, '<>', '')->exists();
        };
        $table = $hasFileOn($primaryTable) ? $primaryTable
               : ($hasFileOn($fallbackTable) ? $fallbackTable : $primaryTable);

        // Ensure the underlying row exists. customer_kyc_dom_com may be
        // missing for a brand-new DOM/COM customer; create a stub row
        // linked to this customer_kyc row if so.
        if ($table === 'customer_kyc_dom_com' && !$domComId) {
            $domComId = DB::table('customer_kyc_dom_com')->insertGetId([
                'customer_id'     => $customerId,
                'customer_kyc_id' => $kycId,
                'status'          => 'Unchecked',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        // Guard each column against the chosen table — the identity
        // status/remark columns exist on both tables, but hasColumn keeps
        // this safe on partially-migrated envs.
        $update = ['updated_at' => now()];
        if (\Schema::hasColumn($table, $meta['status'])) {
            $update[$meta['status']] = $validated['status'];
        }
        if (array_key_exists('remark', $validated) && \Schema::hasColumn($table, $meta['remark'])) {
            $update[$meta['remark']] = $validated['remark'];
        }
        // Expiry write-through. Only applied when the doc actually has
        // an expiry column AND the column physically exists on this env
        // (the migration may not have run yet). Front/Back ID docs map
        // to the same expiry column so writing from either side updates
        // the shared value — matches V8's omangExpiry semantics.
        if (array_key_exists('expiry_date', $validated) && !empty($meta['expiry'])) {
            $expiryCol = $meta['expiry'];
            if (\Schema::hasColumn($table, $expiryCol)) {
                $update[$expiryCol] = $validated['expiry_date'] ?: null;
            }
        }
        $scope($table)->update($update);

        // Activity log entry — same shape as MIS so the FE renders both.
        $user = Auth::user();
        $statusLabel = match ((int) $validated['status']) {
            1 => 'Approved',
            2 => 'Rejected',
            default => 'Reset to Pending',
        };

        try {
            DB::table('kyc_activity_log')->insert([
                'customer_id'         => $customerId,
                'log_name'            => 'document_verification',
                'status'              => $statusLabel,
                'action_perfomed_by'  => $user ? ($user->firstName . ' ' . $user->lastName) : 'System',
                'action_performed_at' => now(),
                'description'         => "[DOM/COM] {$meta['label']} {$statusLabel}"
                                        . (isset($validated['remark']) ? ": {$validated['remark']}" : ''),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("DomCom KYC activity log insert failed for customer {$customerId}: " . $e->getMessage());
        }

        return response()->json([
            'message'  => "Document {$statusLabel}",
            'document' => $validated['document'],
            'table'    => $meta['table'],
        ]);
    }

    /**
     * POST /dom-com-kyc/{kycId}/status — overall compliance verdict.
     * Writes to BOTH customer_kyc and customer_kyc_dom_com so downstream
     * code that reads either table sees a consistent result.
     */
    public function updateStatus(Request $request, int $kycId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:approved,rejected',
            'remark' => 'nullable|string|max:500',
        ]);

        // Anchor on the KYC row PK (see detail()); derive customer_id and
        // the linked DomCom row id so both tables are updated on the exact
        // rows behind this review.
        $kyc = DB::table('customer_kyc')->where('id', $kycId)->first(['id', 'customer_id']);
        if (!$kyc) {
            return response()->json(['error' => 'KYC record not found'], 404);
        }
        $customerId = (int) $kyc->customer_id;
        $domComId = DB::table('customer_kyc_dom_com')->where('customer_kyc_id', $kycId)->orderByDesc('id')->value('id')
            ?? DB::table('customer_kyc_dom_com')->where('customer_id', $customerId)->orderByDesc('id')->value('id');

        $compliance   = $validated['status'] === 'approved' ? 1 : 2;
        // Persisted status enum: reject standardized to lowercase 'rejected'
        // (the single canonical value, matching the MIS flow). Approve unchanged.
        $statusValue  = $validated['status'] === 'approved' ? 'Approve' : 'rejected';
        // Human-readable label for the activity log + API message — past-tense
        // 'Rejected' for parity with the MIS log. Approve unchanged.
        $statusLabel  = $validated['status'] === 'approved' ? 'Approve' : 'Rejected';
        $approvedDate = $validated['status'] === 'approved' ? now() : null;

        $payload = [
            'status'        => $statusValue,
            'compliance'    => $compliance,
            'remark'        => $validated['remark'] ?? null,
            'approved_date' => $approvedDate,
            'updated_at'    => now(),
        ];

        DB::table('customer_kyc')->where('id', $kycId)->update($payload);
        if ($domComId) {
            DB::table('customer_kyc_dom_com')->where('id', $domComId)->update($payload);
        }

        try {
            $user = Auth::user();
            DB::table('kyc_activity_log')->insert([
                'customer_id'         => $customerId,
                'log_name'            => 'overall_verification',
                'status'              => $statusLabel,
                'compliance'          => (string) $compliance,
                'action_perfomed_by'  => $user ? ($user->firstName . ' ' . $user->lastName) : 'System',
                'action_performed_at' => now(),
                'description'         => "[DOM/COM] Overall KYC {$statusLabel}"
                                        . (!empty($validated['remark']) ? " — {$validated['remark']}" : ''),
                'reason'              => $validated['remark'] ?? null,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("DomCom KYC overall log insert failed for customer {$customerId}: " . $e->getMessage());
        }

        return response()->json([
            'message'    => "DOM/COM KYC {$statusLabel}",
            'status'     => $validated['status'],
            'compliance' => $compliance,
        ]);
    }
}
