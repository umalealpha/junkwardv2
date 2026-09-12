<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Helper;
use AlphaDirect\Support\KycDomComProducts;
use AlphaDirect\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerKycController extends Controller
{
    /**
     * GET /kyc/{kycId}/detail — Full KYC record for review.
     *
     * V8 parity: the {kycId} route param is customer_kyc.id (the KYC
     * row's primary key), NOT customer.id. Customers can accumulate
     * multiple customer_kyc rows historically (registration stub +
     * later policy upload row), and the previous customer_id-keyed
     * lookup silently picked one via ->first() — often the empty
     * stub, leaving agent-uploaded docs invisible to the KYC team.
     * Mirrors V8 Admin\CustomerKycController::verify / verifyDomCom
     * which load via `KYC::where('id', $id)` against the exact row
     * the list table linked to.
     */
    public function detail(int $kycId): JsonResponse
    {
        $kyc = DB::table('customer_kyc')->where('id', $kycId)->first();
        if (!$kyc) return response()->json(['error' => 'KYC record not found'], 404);

        $customerId = (int) $kyc->customer_id;

        // Identity-doc columns (omang/passport/proof_residence/etc.) can
        // physically live on EITHER customer_kyc or its sibling row in
        // customer_kyc_dom_com — the policy KYC upload picks the table
        // based on the active policy's tier at upload time, so a MIS
        // customer who also touched a Domestic flow can end up with
        // proof_residence/proof_income on the DomCom row while omang/
        // driving_license sit on the customer_kyc row. The policy KYC
        // tab merges both (PolicyController::kycDocuments); without
        // matching that here the KYC review page renders half the docs
        // as "Not uploaded". Load the latest DomCom row for this
        // customer and use it as the fallback source per column.
        $kycDomCom = \Schema::hasTable('customer_kyc_dom_com')
            ? DB::table('customer_kyc_dom_com')
                ->where('customer_id', $customerId)
                ->orderByDesc('id')
                ->first()
            : null;

        // Merge helper: return $kyc->$col when populated, otherwise the
        // matching column on the DomCom row (when that row exists AND
        // has the column). property_exists() guards against missing
        // columns — driving_license, for example, only exists on
        // customer_kyc; touching it on a DomCom row would warn under
        // strict object access.
        $pullCol = function (string $col) use ($kyc, $kycDomCom) {
            $primary = $kyc->{$col} ?? null;
            if ($primary !== null && $primary !== '') return $primary;
            if ($kycDomCom && property_exists($kycDomCom, $col)) {
                $fallback = $kycDomCom->{$col};
                return ($fallback !== null && $fallback !== '') ? $fallback : null;
            }
            return null;
        };

        $customer = DB::table('customer')->where('id', $customerId)
            ->first(['id', 'firstName', 'lastName', 'cellphone', 'email', 'is_blocked', 'block_reason']);

        $profile = DB::table('customer_profile')->where('customer_id', $customerId)
            ->first(['address', 'dob', 'gender', 'company_id']);

        $policies = DB::table('policies as p')
            ->leftJoin('products as pr', 'pr.id', '=', 'p.product_id')
            ->where('p.customer_id', $customerId)
            ->select(['p.id', 'p.policyNumber', 'p.product_id', 'pr.name as productName', 'p.status', 'p.policyActivatedDate', 'p.premium'])
            ->orderBy('p.id', 'desc')->limit(10)->get();

        // Compute tier so the React frontend can redirect customers
        // whose only policies are DOM/COM-tier to the dedicated review
        // page (Identity / Corporate / Additional Uploads layout).
        $hasMis    = false;
        $hasDomCom = false;
        foreach ($policies as $p) {
            if (KycDomComProducts::includes($p->product_id)) $hasDomCom = true;
            else $hasMis = true;
        }
        $tier = $hasMis && $hasDomCom ? 'BOTH'
              : ($hasDomCom ? 'DOM_COM'
              : ($hasMis ? 'MIS' : 'NONE'));

        $cdnBase = env('AWS_CLOUDFRONT', 'https://d20dgglp0tqnyi.cloudfront.net');

        // Build documents array with status, remark, and CDN URLs.
        // The optional `expiry` column lets the FE render an expiry-date
        // input on docs that carry one (Omang / Passport / Driving
        // License). Front/Back ID docs share the same expiry column —
        // editing from either side writes to the shared value, matching
        // V8's omangExpiry semantic.
        $documents = [];
        $docFields = [
            ['field' => 'omang',             'label' => 'Omang (Front)',         'status' => 'omangFrontStatus',        'remark' => 'omangFrontRemark',        'expiry' => 'omangExpiry'],
            ['field' => 'omangBack',         'label' => 'Omang (Back)',          'status' => 'omangBackStatus',         'remark' => 'omangBackRemark',         'expiry' => 'omangExpiry'],
            ['field' => 'passport',          'label' => 'Passport',             'status' => 'passportStatus',          'remark' => 'passportRemark',          'expiry' => 'passportExpiry'],
            ['field' => 'driving_license',   'label' => 'Driving License',      'status' => 'driving_licenseStatus',   'remark' => 'driving_licenseRemark',   'expiry' => 'licenseExpiry'],
            ['field' => 'proof_residence',   'label' => 'Proof of Residence',   'status' => 'proof_residenceStatus',   'remark' => 'proof_residenceRemark',   'expiry' => null],
            ['field' => 'proof_income',      'label' => 'Proof of Income',      'status' => 'proof_incomeStatus',      'remark' => 'proof_incomeRemark',      'expiry' => null],
        ];

        foreach ($docFields as $doc) {
            // File path, status, remark, and expiry all resolve via the
            // customer_kyc → customer_kyc_dom_com fallback. Each column
            // is independently looked up so the row that holds the file
            // path also gets its own status/remark even when those live
            // on a different table than the file.
            $filePath  = $pullCol($doc['field']);
            $expiryCol = $doc['expiry'] ?? null;
            $documents[] = [
                'key'         => $doc['field'],
                'label'       => $doc['label'],
                'uploaded'    => !empty($filePath),
                'url'         => $filePath ? "{$cdnBase}/{$filePath}" : null,
                'status'      => (int) ($pullCol($doc['status']) ?? 0), // 0=pending, 1=approved, 2=rejected
                'remark'      => $pullCol($doc['remark']),
                'expiryField' => $expiryCol,
                'expiryDate'  => $expiryCol ? $pullCol($expiryCol) : null,
            ];
        }

        // Activity log — resolve user names from IDs.
        //
        // Prefer the kyc-scoped column when present (new rows tagged
        // with customer_kyc_id), and fall back to customer_id for
        // legacy rows that pre-date the column. Without the OR, every
        // historical entry would disappear from the Activity tab the
        // moment the column shipped. The hasColumn guard lets older
        // installs without the migration applied still serve the
        // endpoint cleanly.
        $logQuery = DB::table('kyc_activity_log');
        if (\Schema::hasColumn('kyc_activity_log', 'customer_kyc_id')) {
            $logQuery->where(function ($q) use ($kycId, $customerId) {
                $q->where('customer_kyc_id', $kycId)
                  ->orWhere(function ($qq) use ($customerId) {
                      $qq->whereNull('customer_kyc_id')->where('customer_id', $customerId);
                  });
            });
        } else {
            $logQuery->where('customer_id', $customerId);
        }
        $activityLogs = $logQuery->orderBy('id', 'desc')->limit(50)->get();

        // Collect every user-id candidate that needs resolution: the activity-log
        // performers AND the top-level kyc.performed_by field (legacy rows store
        // a numeric user id there; newer rows store the resolved name). Doing
        // them together keeps it to one users-table round-trip.
        $userIds = $activityLogs->pluck('action_perfomed_by')->all();
        if (is_numeric($kyc->performed_by ?? null)) {
            $userIds[] = $kyc->performed_by;
        }
        $userIds = array_values(array_filter(array_unique($userIds), fn($v) => is_numeric($v)));
        $users = !empty($userIds)
            ? DB::table('users')->whereIn('id', $userIds)->pluck(DB::raw("CONCAT(firstName, ' ', lastName)"), 'id')->toArray()
            : [];

        $resolvePerformer = function ($value) use ($users) {
            if ($value === null || $value === '') return $value;
            // Already a name (new rows write firstName + lastName here)
            if (!is_numeric($value)) return $value;
            return $users[(int) $value] ?? $value;
        };

        // V8-parity audit fields. graphiteBWV8's KYC Activity Log Records
        // page surfaces ip_address / tags / old_values / new_values per
        // row (sourced from the Laravel Auditing `audits` table there;
        // V2 reads them straight off kyc_activity_log when the columns
        // exist). The hasColumn guard keeps this safe on installs whose
        // schema hasn't been migrated yet — those rows just render the
        // new cells as empty in the FE.
        //
        // Column naming reality: the V8 schema stores the before-change
        // snapshot in `old_data_json` (not `old_values`) and never wrote
        // a new_values column. Probe `old_data_json` here so the FE can
        // surface the snapshot; "new" data is implicit in the description.
        $hasOld  = \Schema::hasColumn('kyc_activity_log', 'old_data_json')
                || \Schema::hasColumn('kyc_activity_log', 'old_values');
        $oldCol  = \Schema::hasColumn('kyc_activity_log', 'old_data_json')
                ? 'old_data_json'
                : 'old_values';
        $hasIp   = \Schema::hasColumn('kyc_activity_log', 'ip_address');
        $hasTags = \Schema::hasColumn('kyc_activity_log', 'tags');

        // Decode JSON columns once per row. Stored values may be a JSON
        // string (the normal case for a json column read raw via the
        // query builder) or already an array on some MySQL drivers —
        // is_string() picks the right branch.
        $decodeJson = function ($value) {
            if ($value === null || $value === '') return null;
            if (is_array($value)) return $value;
            $decoded = json_decode((string) $value, true);
            return is_array($decoded) ? $decoded : null;
        };

        $activityLog = $activityLogs->map(fn($log) => [
            'id' => $log->id,
            'action' => $log->log_name,
            'status' => $log->status,
            'compliance' => $log->compliance,
            'description' => $log->description,
            'reason' => $log->reason,
            'performedBy' => $resolvePerformer($log->action_perfomed_by),
            'performedAt' => $log->action_performed_at,
            'ipAddress' => $hasIp   ? ($log->ip_address ?? null)        : null,
            'tag'       => $hasTags ? ($log->tags ?? null)              : null,
            // Before-change snapshot from old_data_json (V8) — used by the
            // FE "View before snapshot" expandable.
            'oldValues' => $hasOld  ? $decodeJson($log->{$oldCol} ?? null) : null,
        ]);

        // Audits loaded lazily via separate endpoint to keep this fast

        // ─── AML / sanctions screening summary ───────────────────────
        // Mirrors graphiteBWV8 viewData.blade.php (the inline @php block
        // rendering the "Sanctioned Countries/Regions", "Last Scan" and
        // "Sanctions Status" badges). Keyed on customer_id — AML cases
        // are per-customer, not per customer_kyc row.
        $sanctions = [
            'countries'  => [],
            'programIds' => [],
            'maxScore'   => 0.0,
            'sanctioned' => false,
            'lastScan'   => null,
            'target'     => null,
        ];
        $kycCase = \AlphaDirect\Models\KycCase::where('customer_id', $customerId)->first();
        if ($kycCase) {
            $latestAml = $kycCase->amlResults()->latest()->first();
            if ($latestAml) {
                if (is_array($latestAml->datasets)) {
                    $sanctions['countries'] = array_values($this->extractSanctionCountries($latestAml->datasets));
                }
                if (is_array($latestAml->programId)) {
                    $sanctions['programIds'] = array_values($latestAml->programId);
                }
                $sanctions['target']   = $latestAml->target;
                $sanctions['lastScan'] = $latestAml->created_at;
            }
            $sanctions['maxScore']   = (float) ($kycCase->sanctions_max ?? 0);
            $sanctions['sanctioned'] = ((float) ($kycCase->sanctions_max ?? 0)) > 0;
        }

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => trim(($customer->firstName ?? '') . ' ' . ($customer->lastName ?? '')),
                'firstName' => $customer->firstName,
                'lastName' => $customer->lastName,
                'cellphone' => $customer->cellphone,
                'email' => $customer->email,
                'isBlocked' => $customer->is_blocked == '1',
                'blockReason' => $customer->block_reason,
                'address' => $profile->address ?? null,
                'dob' => $profile->dob ?? null,
                'gender' => $profile->gender ?? null,
            ],
            'kyc' => [
                'id' => $kyc->id,
                'omangNumber' => $kyc->omangNumber,
                'passportNumber' => $kyc->passportNumber,
                // V8-legacy expiry columns on customer_kyc — null-safe via ??
                // so older rows without the column still serialise cleanly.
                'omangExpiry'    => $kyc->omangExpiry    ?? null,
                'passportExpiry' => $kyc->passportExpiry ?? null,
                'licenseExpiry'  => $kyc->licenseExpiry  ?? null,
                'compliance' => $kyc->compliance,
                'status' => $kyc->status,
                'remark' => $kyc->remark,
                'performedBy' => $resolvePerformer($kyc->performed_by),
                'approvedDate' => $kyc->approved_date,
                'createdAt' => $kyc->created_at,
                'updatedAt' => $kyc->updated_at,
            ],
            'documents' => $documents,
            'policies' => $policies,
            'activityLog' => $activityLog,
            'sanctions' => $sanctions,
            'tier' => $tier,  // 'MIS' | 'DOM_COM' | 'BOTH' | 'NONE' — drives FE redirect
        ]);
    }

    /**
     * POST /kyc/{kycId}/verify-document — Approve/reject a single document.
     *
     * {kycId} is customer_kyc.id (see detail() comment).
     */
    public function verifyDocument(Request $request, int $kycId): JsonResponse
    {
        $validated = $request->validate([
            // Accept both the short verify-names (legacy callers) and the
            // exact document `key`s emitted by detail() — the review page
            // posts doc.key verbatim (e.g. 'omang'), so those must validate too.
            'document' => 'required|string|in:omangFront,omang,omangBack,passport,driving_license,proof_residence,proof_income',
            'status' => 'required|integer|in:0,1,2', // 0=pending, 1=approved, 2=rejected
            'remark' => 'nullable|string|max:500',
            // Only meaningful for omang/passport/driving_license.
            // Ignored silently for the others. Accepts a YYYY-MM-DD or
            // null/empty (to clear). Front/Back ID docs share one
            // expiry column — writing from either updates the shared
            // value (V8 omangExpiry semantics).
            'expiry_date' => 'nullable|date',
        ]);

        $fieldMap = [
            'omangFront'      => ['status' => 'omangFrontStatus',        'remark' => 'omangFrontRemark',        'expiry' => 'omangExpiry'],
            // detail() emits the Omang-front card with key 'omang'.
            'omang'           => ['status' => 'omangFrontStatus',        'remark' => 'omangFrontRemark',        'expiry' => 'omangExpiry'],
            'omangBack'       => ['status' => 'omangBackStatus',         'remark' => 'omangBackRemark',         'expiry' => 'omangExpiry'],
            'passport'        => ['status' => 'passportStatus',          'remark' => 'passportRemark',          'expiry' => 'passportExpiry'],
            'driving_license' => ['status' => 'driving_licenseStatus',   'remark' => 'driving_licenseRemark',   'expiry' => 'licenseExpiry'],
            'proof_residence' => ['status' => 'proof_residenceStatus',   'remark' => 'proof_residenceRemark',   'expiry' => null],
            'proof_income'    => ['status' => 'proof_incomeStatus',      'remark' => 'proof_incomeRemark',      'expiry' => null],
        ];

        // Resolve the exact KYC row first — gives us customer_id for the
        // activity-log entry and lets us 404 cleanly when an orphaned URL
        // is hit. Lookup by primary key avoids the legacy ->first() ambiguity
        // when multiple customer_kyc rows share a customer_id.
        $kyc = DB::table('customer_kyc')->where('id', $kycId)->first(['id', 'customer_id']);
        if (!$kyc) return response()->json(['error' => 'KYC record not found'], 404);
        $customerId = (int) $kyc->customer_id;

        $fields = $fieldMap[$validated['document']];
        $update = [$fields['status'] => $validated['status'], 'updated_at' => now()];
        if (isset($validated['remark'])) $update[$fields['remark']] = $validated['remark'];
        if (array_key_exists('expiry_date', $validated) && !empty($fields['expiry'])) {
            $expiryCol = $fields['expiry'];
            if (\Schema::hasColumn('customer_kyc', $expiryCol)) {
                $update[$expiryCol] = $validated['expiry_date'] ?: null;
            }
        }

        DB::table('customer_kyc')->where('id', $kycId)->update($update);

        // Log activity. Write customer_kyc_id additively when the column
        // exists so future audit reads can filter by the specific KYC
        // row; keep customer_id populated so legacy reads still work.
        $user = Auth::user();
        $statusLabel = match($validated['status']) { 1 => 'Approved', 2 => 'Rejected', default => 'Reset to Pending' };
        $logRow = [
            'customer_id' => $customerId,
            'log_name' => 'document_verification',
            'status' => $statusLabel,
            'action_perfomed_by' => $user ? ($user->firstName . ' ' . $user->lastName) : 'System',
            'action_performed_at' => now(),
            'description' => "{$validated['document']} {$statusLabel}" . ($validated['remark'] ? ": {$validated['remark']}" : ''),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (\Schema::hasColumn('kyc_activity_log', 'customer_kyc_id')) {
            $logRow['customer_kyc_id'] = $kycId;
        }
        DB::table('kyc_activity_log')->insert($logRow);

        return response()->json(['message' => "Document {$statusLabel}", 'document' => $validated['document']]);
    }
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|max:50',
            'compliance' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
            'tier' => 'nullable|string|in:MIS,DOMG,COMG',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        // Mirror graphiteBWV8 CustomerKyc\Table::builder() (Livewire
        // DataTable for the DOM/COM KYC listing, lines 412-481):
        //   • Base = `customer_kyc as k` — the listing anchor.
        //   • INNER JOIN policies via a customer-scoped MIN(id) subquery
        //     so each customer contributes exactly one policy row even
        //     when they hold many policies. Restricts to active policies
        //     (`status=1`) so cancelled customers don't clutter the list.
        //   • LEFT JOIN customer + companies + customer_profile so the
        //     display name can swap to company_name when entity_type =
        //     'Organisation' (V8 line 103).
        //   • LEFT JOIN customer_kyc_dom_com (latest row per customer)
        //     so the per-row Documents indicators can read corporate
        //     doc paths even when the row lives on DomCom only.
        //
        // The "either-table" approach was abandoned in favour of V8's
        // strict customer_kyc-anchored shape. To make DomCom-only
        // customers visible, uploadKycDocuments now creates a customer_kyc
        // stub alongside the DomCom row (see PolicyCreateController).
        $hasDomCom = \Schema::hasTable('customer_kyc_dom_com');
        $tier      = $validated['tier'] ?? null;
        $hasSearch = !empty($validated['search'] ?? null);

        // ── Rewritten Aug 2026 (staging perf regression) ─────────────────
        // The previous shape built a MIN(id)-per-customer derived table over
        // the WHOLE policies table plus a MAX(id) DomCom subquery and joined
        // both for every candidate, then COUNT/filesorted ~98k rows. For MIS
        // and All-Tiers that ran >30s and the browser aborted (blank list).
        // Now we drive from customer_kyc, express membership + "has documents"
        // as index-backed EXISTS, order via customer_kyc(updated_at,id) so the
        // LIMIT short-circuits, and fetch the display "anchored policy" for the
        // paged customers only. Behaviour (which rows appear, tier, name,
        // filters, response shape) is preserved.

        // Tier predicate as a raw SQL fragment (the product-id sets are
        // hardcoded class constants, so inlining them is injection-safe). One
        // source of truth, aliased for both the membership subquery (pe) and
        // the display-anchor query (policies). A fragment rather than a builder
        // closure so membership can be expressed as a SCALAR correlated
        // subquery (see $applyFilters) — the shape MariaDB keeps per-row
        // instead of flattening into a whole-table semi-join.
        $tierFrag = function (string $alias) use ($tier): string {
            if ($tier === 'COMG') {
                $ids = implode(',', KycDomComProducts::COMMERCIAL_IDS);
                return " AND (UPPER(LEFT({$alias}.policyNumber,4)) = 'COMG' OR {$alias}.product_id IN ({$ids}))";
            }
            if ($tier === 'DOMG') {
                $ids = implode(',', KycDomComProducts::DOMESTIC_IDS);
                return " AND (UPPER(LEFT({$alias}.policyNumber,4)) = 'DOMG' OR {$alias}.product_id IN ({$ids}))";
            }
            if ($tier === 'MIS') {
                $ids = implode(',', KycDomComProducts::IDS);
                return " AND {$alias}.product_id NOT IN ({$ids})";
            }
            return '';
        };

        // All filters, applied to BOTH the count and items queries. Search
        // (which references c/co) is only included when a term was sent; the
        // caller adds the c/co joins in that case.
        $applyFilters = function ($q) use ($validated, $tierFrag, $hasDomCom, $hasSearch) {
            // status — synonym set spanning V8 (mixed case) + V2 (lowercase)
            // writes; compare LOWER() on both sides.
            if (!empty($validated['status'])) {
                $aliases = [
                    'unchecked'             => ['unchecked', 'pending'],
                    'approve'               => ['approve', 'approved'],
                    'unapprove'             => ['unapprove', 'rejected', 'reject', 'unapproved'],
                    'recheck'               => ['recheck'],
                    'recheck(kyc expired)'  => ['recheck(kyc expired)'],
                    'renew'                 => ['renew'],
                    'cancelled'             => ['cancelled'],
                ];
                $needle = strtolower(trim((string) $validated['status']));
                $values = $aliases[$needle] ?? [$needle];
                $ph = implode(',', array_fill(0, count($values), '?'));
                $q->whereRaw("LOWER(k.status) IN ({$ph})", $values);
            }

            // compliance — isset (not truthy) so compliance=0 (Pending) matches.
            if (isset($validated['compliance'])) {
                $q->where('k.compliance', $validated['compliance']);
            }

            // membership + tier — customer has >=1 non-cancelled policy matching
            // the tier. Written as a SCALAR correlated subquery
            // ((SELECT 1 … LIMIT 1) IS NOT NULL) rather than EXISTS: MariaDB
            // flattens EXISTS into a semi-join that materialises the whole
            // ~200k policies set, which defeats the items query's ORDER BY +
            // LIMIT short-circuit (~11s). The scalar form stays a per-row ref
            // probe on policies(customer_id,status,id), so the items scan stops
            // at ~page size (~1.6s). Identical row set to the old INNER-JOIN
            // anchor.
            $q->whereRaw(
                "(SELECT 1 FROM policies pe WHERE pe.customer_id = k.customer_id AND pe.status <> 2"
                . $tierFrag('pe') . " LIMIT 1) IS NOT NULL"
            );

            // has-documents — >=1 personal doc on k, OR (DomCom installs) a
            // corporate doc on the customer's LATEST customer_kyc_dom_com row.
            // Personal ORs are checked first and short-circuit, so the DomCom
            // EXISTS rarely runs.
            $q->where(function ($w) use ($hasDomCom) {
                foreach (['k.omang', 'k.omangBack', 'k.passport', 'k.driving_license', 'k.proof_residence', 'k.proof_income'] as $col) {
                    $w->orWhere(fn($qq) => $qq->whereNotNull($col)->where($col, '<>', ''));
                }
                if ($hasDomCom) {
                    $w->orWhereExists(function ($sub) {
                        $corporate = [
                            'kyc_form', 'data_protection_form', 'certificate_of_incorporation',
                            'extract_controllers', 'resolution', 'proof_business_address', 'proof_residential_address',
                            'directors_id_front', 'directors_id_back', 'directors_passport',
                            'shareholders_id_front', 'shareholders_id_back', 'shareholders_passport',
                        ];
                        $sub->select(DB::raw(1))->from('customer_kyc_dom_com as kd')
                            ->whereColumn('kd.customer_id', 'k.customer_id')
                            // latest DomCom row for this customer only
                            ->where('kd.id', '=', function ($m) {
                                $m->selectRaw('MAX(kd2.id)')->from('customer_kyc_dom_com as kd2')
                                  ->whereColumn('kd2.customer_id', 'k.customer_id');
                            })
                            ->where(function ($d) use ($corporate) {
                                foreach ($corporate as $c) {
                                    $d->orWhere(fn($qq) => $qq->whereNotNull("kd.$c")->where("kd.$c", '<>', ''));
                                }
                            });
                    });
                }
            });

            // search — names / company / policy number. References c/co (joined
            // by the caller when searching). The old p.policyNumber term is
            // dropped as redundant: the orWhereExists on the customer's policies
            // already matches any policy number, including the anchored one.
            if ($hasSearch) {
                $search = trim(preg_replace('/\s+/', ' ', $validated['search']));
                $like = "%{$search}%";
                $words = count(explode(' ', $search)) >= 2
                    ? array_values(array_filter(explode(' ', $search)))
                    : [];
                $q->where(function ($w) use ($like, $words) {
                    $w->where('c.firstName', 'like', $like)
                      ->orWhere('c.middleName', 'like', $like)
                      ->orWhere('c.lastName', 'like', $like)
                      ->orWhere('c.cellphone', 'like', $like)
                      ->orWhere('c.email', 'like', $like)
                      ->orWhere('co.name', 'like', $like)
                      ->orWhereRaw("CONCAT_WS(' ', TRIM(c.firstName), TRIM(c.middleName), TRIM(c.lastName)) like ?", [$like])
                      ->orWhereRaw("CONCAT_WS(' ', TRIM(c.firstName), TRIM(c.lastName)) like ?", [$like])
                      ->orWhereExists(function ($sub) use ($like) {
                          $sub->select(DB::raw(1))->from('policies as sp')
                              ->whereColumn('sp.customer_id', 'k.customer_id')
                              ->where('sp.policyNumber', 'like', $like);
                      });
                    if (count($words) >= 2) {
                        $w->orWhere(fn($i) => $i->where('c.firstName', 'like', "%{$words[0]}%")->where('c.lastName', 'like', "%{$words[1]}%"))
                          ->orWhere(fn($i) => $i->where('c.firstName', 'like', "%{$words[1]}%")->where('c.lastName', 'like', "%{$words[0]}%"));
                    }
                });
            }
        };

        $perPage = (int) ($validated['per_page'] ?? 25);
        $page    = max(1, (int) $request->query('page', 1));

        // ── Count (lean, cached) ─────────────────────────────────────────
        // customer_kyc + the shared filters; joins c/co only when searching.
        // No GROUP BY / ORDER BY → index-probe COUNT, no temp table/filesort
        // (the old count materialised two derived tables and ran >30s). The
        // total barely moves between loads — rows are added on document
        // upload, not by status reviews — so it's cached on a short,
        // filter-scoped TTL and reused across pages and reviewers.
        $countQuery = DB::table('customer_kyc as k');
        if ($hasSearch) {
            $countQuery->leftJoin('customer as c', 'c.id', '=', 'k.customer_id')
                       ->leftJoin('companies as co', 'co.id', '=', 'c.company_id');
        }
        $applyFilters($countQuery);

        $total = (int) CacheService::remember(
            'kyc_list_total:' . md5(json_encode($validated)),
            fn() => (clone $countQuery)->count(),
            CacheService::CACHE_TTL_SHORT // 5 min
        );

        // ── Items (drive from customer_kyc, index-ordered LIMIT) ─────────
        // The display LEFT JOINs (c/co/cp) never filter, so they don't block
        // the ORDER-BY-index + LIMIT short-circuit — only the paged rows are
        // hydrated. Newest activity first; NULL updated_at sorts last on DESC;
        // k.id is the stable tiebreaker so pages stay stable across reloads.
        $items = collect();
        if ($total > 0) {
            $itemsQuery = DB::table('customer_kyc as k')
                ->leftJoin('customer as c', 'c.id', '=', 'k.customer_id')
                ->leftJoin('companies as co', 'co.id', '=', 'c.company_id')
                ->select([
                    'k.id', 'k.customer_id', 'k.compliance', 'k.status', 'k.remark',
                    'k.omang', 'k.omangBack', 'k.passport', 'k.driving_license',
                    'k.proof_residence', 'k.proof_income', 'k.updated_at',
                    'c.firstName', 'c.middleName', 'c.lastName', 'c.cellphone', 'c.email', 'c.company_id',
                    'co.name as company_name',
                    // entity_type via scalar subquery (latest profile) instead of a
                    // LEFT JOIN: customer_profile has a handful of customers with >1
                    // row, and joining them duplicated those customers as repeat list
                    // rows AND inflated the pre-rewrite count. The subquery keeps items
                    // strictly one-per-KYC-row, consistent with the lean count.
                    DB::raw('(SELECT cp.entity_type FROM customer_profile cp WHERE cp.customer_id = k.customer_id ORDER BY cp.id DESC LIMIT 1) as entity_type'),
                ]);
            $applyFilters($itemsQuery);

            $items = collect(
                $itemsQuery->orderByDesc('k.updated_at')
                           ->orderByDesc('k.id')
                           ->forPage($page, $perPage)
                           ->get()
            );

            // Anchored (MIN-id, non-cancelled, tier-matched) policy for the
            // paged customers only — one query keyed on <=per_page ids, so the
            // full anchor is never re-materialised. Supplies product_id /
            // policyNumber for tier resolution + display, using the SAME tier
            // predicate as the membership EXISTS so the chosen policy matches
            // inclusion.
            $custIds = $items->pluck('customer_id')->filter()->unique()->values()->all();
            if (!empty($custIds)) {
                $anchorSub = DB::table('policies')
                    ->select('customer_id', DB::raw('MIN(id) as min_id'))
                    ->whereRaw('status <> 2' . $tierFrag('policies'))
                    ->whereIn('customer_id', $custIds)
                    ->groupBy('customer_id');

                $anchors = DB::table('policies as p')
                    ->joinSub($anchorSub, 'pa', 'pa.min_id', '=', 'p.id')
                    ->select('pa.customer_id', 'p.product_id', 'p.policyNumber')
                    ->get()
                    ->keyBy('customer_id');

                $items->each(function ($r) use ($anchors) {
                    $a = $anchors->get($r->customer_id);
                    $r->product_id   = $a->product_id   ?? null;
                    $r->policyNumber = $a->policyNumber ?? null;
                });
            }
        }

        $lastPage = (int) max(1, (int) ceil($total / max(1, $perPage)));
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : null;
        $to   = ($from !== null && $items->count() > 0) ? $from + $items->count() - 1 : null;

        // Per-row tier is resolved from the anchored policy's prefix/product_id
        // (injected above from the single anchor-map lookup, not a per-customer
        // round-trip).
        $resolveTier = function ($r): string {
            $prefix = strtoupper(substr((string) ($r->policyNumber ?? ''), 0, 4));
            if ($prefix === 'COMG' || in_array((int) ($r->product_id ?? 0), KycDomComProducts::COMMERCIAL_IDS, true)) {
                return 'COMG';
            }
            if ($prefix === 'DOMG' || in_array((int) ($r->product_id ?? 0), KycDomComProducts::DOMESTIC_IDS, true)) {
                return 'DOMG';
            }
            return 'MIS';
        };

        return response()->json([
            'data' => $items->map(function ($r) use ($resolveTier) {
                $tier = $resolveTier($r);

                // Default: person name (firstName + middleName + lastName).
                $personName = trim(implode(' ', array_filter([
                    $r->firstName ?? null, $r->middleName ?? null, $r->lastName ?? null,
                ])));

                // DOMG/COMG + entity_type='Organisation' → company name.
                // Mirrors V8 CustomerKyc\Table::columns():104 — the check
                // is on entity_type, not on the policy prefix, so a DOMG
                // customer registered as an Organisation also shows the
                // company name. MIS keeps the person name.
                $customerName = $personName;
                if (($tier === 'DOMG' || $tier === 'COMG')
                    && ($r->entity_type ?? null) === 'Organisation'
                    && !empty($r->company_name)
                ) {
                    $customerName = (string) $r->company_name;
                }

                return [
                    'id' => $r->id,
                    'customerId' => $r->customer_id,
                    'customerName' => $customerName !== '' ? $customerName : null,
                    'tier' => $tier, // 'MIS' | 'DOMG' | 'COMG'
                    'cellphone' => $r->cellphone,
                    'email' => $r->email,
                    'compliance' => $r->compliance,
                    'status' => $r->status,
                    'remark' => $r->remark,
                    'hasOmang' => !empty($r->omang),
                    'hasOmangBack' => !empty($r->omangBack),
                    'hasPassport' => !empty($r->passport),
                    'hasDrivingLicense' => !empty($r->driving_license),
                    'hasProofResidence' => !empty($r->proof_residence),
                    'hasProofIncome' => !empty($r->proof_income),
                    'updatedAt' => $r->updated_at,
                ];
            }),
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function updateStatus(Request $request, int $kycId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:approved,rejected',
            'remark' => 'nullable|string|max:500',
        ]);

        $kyc = DB::table('customer_kyc')->where('id', $kycId)->first();
        if (!$kyc) {
            return response()->json(['error' => 'KYC record not found'], 404);
        }
        $customerId = (int) $kyc->customer_id;

        // Capture WHO made the decision and WHEN, alongside the status +
        // remark. Without this the result banner had nothing to render
        // beyond "KYC Approved" — no date, no reviewer, no remark.
        $user = Auth::user();
        $performedBy = $user
            ? trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? ''))
            : null;

        $compliance = $validated['status'] === 'approved' ? 1 : 2;

        DB::table('customer_kyc')->where('id', $kycId)->update([
            'status'        => $validated['status'],
            // Use the submitted remark whenever the field was sent — INCLUDING
            // when it was cleared (empty string → null via ConvertEmptyStrings-
            // ToNull). The old `?? $kyc->remark` fallback treated a cleared
            // remark as "not provided" and silently restored the previous text,
            // so an Overall Decision remark could never be deleted (it "came
            // back" on save). array_key_exists distinguishes "sent empty →
            // clear" from "omitted → keep".
            'remark'        => array_key_exists('remark', $validated) ? $validated['remark'] : $kyc->remark,
            'compliance'    => $compliance,
            'performed_by'  => $performedBy ?: $kyc->performed_by,
            'approved_date' => now(),
            'updated_at'    => now(),
        ]);

        // Activity log entry — same table that document-level verification
        // writes to, so the Activity Log tab captures overall decisions too.
        // description = headline, reason = the operator's remark (verbatim),
        // compliance mirrors the customer_kyc column for filtering parity.
        $statusLabel = $validated['status'] === 'approved' ? 'Approved' : 'Rejected';
        $logRow = [
            'customer_id'         => $customerId,
            'log_name'            => 'overall_decision',
            'status'              => $statusLabel,
            'compliance'          => $compliance,
            'description'         => "Overall KYC {$statusLabel}",
            'reason'              => $validated['remark'] ?? null,
            'action_perfomed_by'  => $performedBy ?: 'System',
            'action_performed_at' => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ];
        if (\Schema::hasColumn('kyc_activity_log', 'customer_kyc_id')) {
            $logRow['customer_kyc_id'] = $kycId;
        }
        DB::table('kyc_activity_log')->insert($logRow);

        return response()->json(['message' => 'KYC status updated', 'status' => $validated['status']]);
    }

    public function employerGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('employer_groups as eg')
            ->select(['eg.id', 'eg.employer_group_id', 'eg.name', 'eg.industry', 'eg.contact_email', 'eg.contact_phone', 'eg.status', 'eg.no_of_employees', 'eg.created_at', 'eg.updated_at'])
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('eg.name', 'like', "%{$search}%")
                      ->orWhere('eg.contact_email', 'like', "%{$search}%")
                      ->orWhere('eg.employer_group_id', 'like', "%{$search}%");
                });
            })
            ->orderBy('eg.id', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'id' => $r->id,
                'employerGroupId' => $r->employer_group_id,
                'name' => $r->name,
                'industry' => $r->industry,
                'contactEmail' => $r->contact_email,
                'contactPhone' => $r->contact_phone,
                'status' => $r->status ?? 'Pending',
                'noOfEmployees' => $r->no_of_employees,
                'createdAt' => $r->created_at,
                'updatedAt' => $r->updated_at,
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

    public function duplicates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,reviewed,resolved,ignored',
            'duplicate_type' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('duplicate_customers as dc')
            ->leftJoin('customer as c', 'c.id', '=', 'dc.customer_id')
            ->select(['dc.*', 'c.firstName', 'c.lastName', 'c.cellphone', 'c.email'])
            ->when($validated['status'] ?? null, fn($q, $v) => $q->where('dc.status', $v))
            ->when($validated['duplicate_type'] ?? null, fn($q, $v) => $q->where('dc.duplicate_type', $v))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('dc.first_name', 'like', "%{$search}%")
                      ->orWhere('dc.last_name', 'like', "%{$search}%")
                      ->orWhere('dc.cellphone', 'like', "%{$search}%")
                      ->orWhere('dc.email', 'like', "%{$search}%");
                });
            })
            ->orderBy('dc.created_at', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'id' => $r->id,
                'customerId' => $r->customer_id,
                'customerName' => trim(($r->firstName ?? $r->first_name ?? '') . ' ' . ($r->lastName ?? $r->last_name ?? '')),
                'cellphone' => $r->cellphone,
                'email' => $r->email,
                'omangNumber' => $r->omang_number ?? null,
                'passportNumber' => $r->passport_number ?? null,
                'duplicateType' => $r->duplicate_type,
                'duplicateReason' => $r->duplicate_reason ?? null,
                'status' => $r->status,
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

    public function deduplication(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = DB::table('deduplication_checks as dc')
            ->leftJoin('customer as c', 'c.id', '=', 'dc.customer_id')
            ->select(['dc.id', 'dc.customer_id', 'dc.omang_number', 'dc.passport_number', 'dc.bank_account_number', 'dc.cellphone', 'dc.email', 'dc.document_upload_status', 'dc.manual_verification_status', 'dc.status', 'dc.is_suspended', 'dc.created_at', 'c.firstName', 'c.lastName'])
            ->when($validated['status'] ?? null, fn($q, $v) => $q->where('dc.status', $v))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $search = trim(preg_replace('/\s+/', ' ', $search));
                $like = "%{$search}%";
                $words = count(explode(' ', $search)) >= 2
                    ? array_values(array_filter(explode(' ', $search)))
                    : [];
                $q->where(function ($q) use ($like, $words) {
                    $q->where('dc.email', 'like', $like)
                      ->orWhere('dc.cellphone', 'like', $like)
                      ->orWhere('c.firstName', 'like', $like)
                      ->orWhere('c.lastName', 'like', $like)
                      ->orWhereRaw("CONCAT_WS(' ', TRIM(c.firstName), TRIM(c.lastName)) LIKE ?", [$like]);
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
            ->orderBy('dc.created_at', 'desc');

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn($r) => [
                'id' => $r->id,
                'customerId' => $r->customer_id,
                'customerName' => trim(($r->firstName ?? '') . ' ' . ($r->lastName ?? '')),
                'email' => $r->email,
                'cellphone' => $r->cellphone,
                'omangNumber' => $r->omang_number,
                'passportNumber' => $r->passport_number,
                'bankAccountNumber' => $r->bank_account_number,
                'documentUploadStatus' => $r->document_upload_status,
                'verificationStatus' => $r->manual_verification_status,
                'status' => $r->status,
                'isSuspended' => (bool) $r->is_suspended,
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

    /**
     * GET /kyc/sanctioned — Customers flagged as sanctioned by the AML
     * screening (kyc_cases.sanctions_max > 0).
     *
     * JSON port of Admin\CustomerKycController::getSanctionedCustomers
     * (the Blade sanctioned_customers page). Same data shape, returned
     * as JSON for the React admin's Sanctioned Customers page. Not
     * paginated — the flagged set is small (a handful of cases), matching
     * the Blade view which loads them all into one DataTable.
     */
    public function sanctioned(): JsonResponse
    {
        $sanctionedCases = \AlphaDirect\Models\KycCase::where('sanctions_max', '>', 0)
            ->with(['amlResults' => function ($q) {
                $q->orderBy('created_at', 'desc');
            }])
            ->get();

        $rows = [];

        foreach ($sanctionedCases as $case) {
            $customer = \AlphaDirect\Customer::find($case->customer_id);
            if (!$customer) continue;

            $latest     = $case->amlResults->first();
            $datasets   = [];
            $programIds = [];
            $countries  = [];
            $target     = null;

            if ($latest) {
                if ($latest->datasets) {
                    $datasets  = $latest->datasets;
                    $countries = $this->extractSanctionCountries($datasets);
                }
                if ($latest->programId) {
                    $programIds = $latest->programId;
                }
                $target = $latest->target;
            }

            // kyc_id powers the row link to the KYC detail page (V8 parity:
            // the Blade view linked the name to admin.viewCustomerKycData).
            $kyc   = \AlphaDirect\KYC::where('customer_id', $customer->id)->first();
            $kycId = $kyc ? $kyc->id : null;

            $rows[] = [
                'customerId'   => $customer->id,
                'kycId'        => $kycId,
                'customerName' => trim($customer->firstName . ' ' . $customer->lastName),
                'maxScore'     => (float) $case->sanctions_max,
                'status'       => $case->status,
                'datasets'     => array_values($datasets),
                'countries'    => array_values($countries),
                'programIds'   => array_values($programIds),
                // target is stored as raw JSON ('true'/'false'/bool/null) —
                // pass it through unchanged so the FE can render the badge.
                'target'       => $target,
                'checkedAt'    => $case->updated_at,
            ];
        }

        // Highest-risk first, matching the Blade DataTable's default sort
        // (order by Max Score descending).
        usort($rows, fn($a, $b) => $b['maxScore'] <=> $a['maxScore']);

        return response()->json([
            'data' => $rows,
            'meta' => ['total' => count($rows)],
        ]);
    }

    /**
     * POST /kyc/{customerId}/run-opensanctions — Run an AML/sanctions
     * screening for a customer synchronously and return the fresh result.
     *
     * Port of Admin\CustomerKycController::runOpenSanctionsCheck. Keyed on
     * customer_id (AML cases are per-customer). Creates the kyc_cases row
     * if absent, runs RunOpenSanctionsJob inline, and returns the updated
     * score/status so the React KYC page can refresh its sanctions panel.
     */
    public function runSanctionsCheck(int $customerId): JsonResponse
    {
        $customer = \AlphaDirect\Customer::find($customerId);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found'], 404);
        }

        try {
            $kycCase = \AlphaDirect\Models\KycCase::firstOrCreate(
                ['customer_id' => $customerId],
                ['customer_id' => $customerId, 'status' => 'pending', 'sanctions_max' => 0, 'sanctions_count' => 0],
            );

            // Synchronous run — matches the Blade button's behaviour so the
            // reviewer sees the result immediately (not queued).
            $job = new \AlphaDirect\Jobs\RunOpenSanctionsJob($kycCase->id);
            $job->handle(app(\AlphaDirect\Services\OpenSanctionsClient::class));

            $kycCase->refresh();
            $latestAml = $kycCase->amlResults()->latest()->first();
            $datasetsCount = ($latestAml && is_array($latestAml->datasets)) ? count($latestAml->datasets) : 0;

            return response()->json([
                'success'       => true,
                'message'       => 'OpenSanctions check completed successfully!',
                'maxScore'      => (float) ($kycCase->sanctions_max ?? 0),
                'status'        => $kycCase->status ?? 'unknown',
                'datasetsCount' => $datasetsCount,
                'sanctioned'    => ((float) ($kycCase->sanctions_max ?? 0)) > 0,
                'kycCaseId'     => $kycCase->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error running OpenSanctions check: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build "Country (Agency)" labels from AML dataset names.
     * Ported verbatim from Admin\CustomerKycController::
     * extractCountriesFromDatasets / convertCountryCodeToName.
     */
    private function extractSanctionCountries(array $datasets): array
    {
        $countries = [];
        foreach ($datasets as $dataset) {
            $parts = explode('_', $dataset);
            if (count($parts) >= 2) {
                $countryName = ucwords(strtolower(str_replace('_', ' ', $parts[0])));
                $agency      = strtoupper($parts[1]);
                $countries[] = $countryName . ' (' . $agency . ')';
            } else {
                $countries[] = ucwords(str_replace('_', ' ', $dataset));
            }
        }
        return array_unique($countries);
    }
}
