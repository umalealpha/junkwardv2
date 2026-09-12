<?php

namespace AlphaDirect\Http\Controllers\Api\Public;

use AlphaDirect\Helper;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\sentPolicyDocumentLogs;
use AlphaDirect\Services\PublicOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Customer self-service ("/me") API surface.
 *
 *   GET  /api/v1/public/me/policies                          — list customer's active policies
 *   GET  /api/v1/public/me/policies/{policyNumber}            — full detail for one policy
 *   GET  /api/v1/public/me/payments                           — payment history (last 24 months)
 *   GET  /api/v1/public/me/policies/{policyNumber}/documents  — document links + last-sent info
 *   POST /api/v1/public/me/policies/{policyNumber}/resend-documents — re-email documents
 *
 * Auth: Bearer session token (purpose=customer_auth). The session's
 * cellphone is the identity — we resolve it to a customer_id once and
 * scope every query to that ID. No PII leaks across customers because
 * a token owner can never query a different cellphone.
 *
 * Response shape favours flat, ready-to-render objects so the FE
 * doesn't have to do any further joins for the portal cards.
 */
class MeController extends Controller
{
    public function __construct(private PublicOtpService $otp) {}

    /**
     * GET /api/v1/public/me/policies
     * Returns: { customer: {...}, policies: [{policyNumber, productName, status,
     *           premium, premium_freq, billing_date, vehicleReg?, ...}] }
     */
    public function policies(Request $request): JsonResponse
    {
        [$session, $errResp] = $this->resolveSession($request);
        if ($errResp) return $errResp;
        $cellphone = $this->normalize((string) ($session['cellphone'] ?? ''));

        // NB: omang / passport live on customer_profile, NOT customer — selecting
        // them here threw "Unknown column 'omang'". They aren't used in the
        // response anyway, so we only read the columns the customer table has.
        $customer = DB::table('customer')->where('cellphone', $cellphone)->first([
            'id', 'firstName', 'lastName', 'email', 'cellphone',
        ]);
        if (!$customer) {
            return response()->json([
                'customer' => null,
                'policies' => [],
            ]);
        }

        // Fetch policies + product name in a single query.
        $policies = DB::table('policies')
            ->leftJoin('products', 'products.id', '=', 'policies.product_id')
            ->where('policies.customer_id', $customer->id)
            ->orderByDesc('policies.id')
            ->limit(100)
            ->get([
                'policies.id',
                'policies.policyNumber',
                'policies.product_id',
                'products.name as product_name',
                'policies.status',
                'policies.premium',
                'policies.premium_freq',
                'policies.billingStartDate',
                'policies.term_start_date as policyStartDate',
                'policies.term_end_date as policyEndDate',
                'policies.created_at',
            ]);

        // Billing method lives on customer_banking (keyed by policy_id), NOT on
        // policies — selecting policies.billing 500s with "Unknown column". A
        // policy can have several banking rows, so take the latest per policy.
        if (\Schema::hasTable('customer_banking') && $policies->isNotEmpty()) {
            $billingByPolicy = DB::table('customer_banking as cb')
                ->joinSub(
                    DB::table('customer_banking')
                        ->whereIn('policy_id', $policies->pluck('id'))
                        ->groupBy('policy_id')
                        ->select('policy_id', DB::raw('MAX(id) as max_id')),
                    'latest',
                    'latest.max_id', '=', 'cb.id'
                )
                ->pluck('cb.billing', 'cb.policy_id');
            $policies = $policies->map(function ($p) use ($billingByPolicy) {
                $p->billing = $billingByPolicy[$p->id] ?? null;
                return $p;
            });
        } else {
            $policies = $policies->map(function ($p) {
                $p->billing = null;
                return $p;
            });
        }

        // Best-effort vehicle reg join — only when the schema has it.
        // The motor product stashes the plate on `vehicle.vehiclePlate` keyed
        // by policy_id (see v_policy_vehicles view). Soft-deleted rows skipped.
        if (\Schema::hasTable('vehicle') && \Schema::hasColumn('vehicle', 'policy_id')) {
            $motorByPolicy = DB::table('vehicle')
                ->whereIn('policy_id', $policies->pluck('id'))
                ->whereNull('deleted_at')
                ->pluck('vehiclePlate', 'policy_id');
            $policies = $policies->map(function ($p) use ($motorByPolicy) {
                $p->vehicle_reg = $motorByPolicy[$p->id] ?? null;
                return $p;
            });
        }

        return response()->json([
            'customer' => [
                'id'        => $customer->id,
                'firstName' => $customer->firstName,
                'lastName'  => $customer->lastName,
                'cellphone' => $customer->cellphone,
                'email'     => $customer->email,
            ],
            'policies' => $policies->map(fn ($p) => $this->shapePolicySummary($p))->values(),
        ]);
    }

    /**
     * GET /api/v1/public/me/policies/{policyNumber}
     * Full detail for one policy — premium, billing, last 12 transactions,
     * upcoming installments, vehicle / motor / device-specific row.
     */
    public function policyDetail(Request $request, string $policyNumber): JsonResponse
    {
        [$session, $errResp] = $this->resolveSession($request);
        if ($errResp) return $errResp;
        $cellphone = $this->normalize((string) ($session['cellphone'] ?? ''));

        $customer = DB::table('customer')->where('cellphone', $cellphone)->first(['id']);
        if (!$customer) return response()->json(['error' => 'customer_not_found'], 404);

        $policy = DB::table('policies')
            ->leftJoin('products', 'products.id', '=', 'policies.product_id')
            ->where('policies.customer_id', $customer->id)
            ->where('policies.policyNumber', $policyNumber)
            ->first([
                'policies.id', 'policies.policyNumber', 'policies.product_id',
                'products.name as product_name',
                'policies.status', 'policies.premium', 'policies.premium_freq',
                'policies.billingStartDate',
                'policies.term_start_date as policyStartDate', 'policies.term_end_date as policyEndDate',
                'policies.created_at',
            ]);
        if (!$policy) return response()->json(['error' => 'policy_not_found'], 404);

        // Billing method lives on customer_banking (keyed by policy_id), NOT on
        // policies — selecting policies.billing 500s with "Unknown column".
        // Use the latest banking row for this policy.
        $policy->billing = \Schema::hasTable('customer_banking')
            ? DB::table('customer_banking')->where('policy_id', $policy->id)->orderByDesc('id')->value('billing')
            : null;

        // Last 12 payment transactions for the customer's policies (this one only).
        $payments = DB::table('payment_transactions')
            ->where('policyNumber', $policy->policyNumber)
            ->orderByDesc('paymentDate')
            ->limit(12)
            ->get(['id', 'paymentDate', 'amount', 'paymentMethod', 'status', 'numberOfInstalmentsPaid']);

        // Upcoming installments (RealPay if billing=DPO?? — actually billing
        // tells us which provider). Pull from schedule_transactions when
        // billing=DPO; from realpay_contract_installments when RealPay.
        $upcoming = collect();
        $billing = strtolower((string) $policy->billing);
        if (str_contains($billing, 'dpo') && \Schema::hasTable('scheduled_transactions')) {
            $upcoming = DB::table('scheduled_transactions')
                ->where('policy_number', $policy->policyNumber)
                ->where('status', 0)
                ->orderBy('billing_date')
                ->limit(6)
                ->get(['id', 'billing_date', 'premium', 'installment', 'status']);
        } elseif (str_contains($billing, 'realpay') && \Schema::hasTable('realpay_contract_installments')) {
            $upcoming = DB::table('realpay_contract_installments')
                ->where('clientNumber', $policy->policyNumber)
                ->where('InstalmentStatus', '!=', 'I')
                ->orderBy('InstalmentDate')
                ->limit(6)
                ->get(['id', 'InstalmentDate as billing_date', 'InstalmentAmount as premium', 'InstalmentSequence as installment', 'InstalmentStatus as status']);
        }

        // Motor / Device / Health-specific row (defensive — schemas vary).
        $productSpecific = null;
        if (\Schema::hasTable('vehicle')) {
            $productSpecific = DB::table('vehicle')
                ->where('policy_id', $policy->id)
                ->whereNull('deleted_at')
                ->first();
        }

        // Beneficiaries (payees on death — ADI and any product that writes to
        // policy_beneficiary). Select * and shape in PHP so a column that an
        // older snapshot lacks can't break the query. gender is int on this
        // table (1=Male, 0=Female) — coerce to a ready-to-render string so the
        // FE card needs no further mapping.
        $beneficiaries = [];
        if (\Schema::hasTable('policy_beneficiary')) {
            $beneficiaries = DB::table('policy_beneficiary')
                ->where('policy_id', $policy->id)
                ->orderBy('id')
                ->get()
                ->map(function ($b) {
                    $row = (array) $b;
                    return [
                        'id'          => $row['id']          ?? null,
                        'first_name'  => $row['first_name']  ?? null,
                        'middle_name' => $row['middle_name'] ?? null,
                        'last_name'   => $row['last_name']   ?? null,
                        'relation'    => $row['relation']    ?? null,
                        'gender'      => $this->genderLabel($row['gender'] ?? null),
                        'dob'         => $row['dob']         ?? null,
                        'omang'       => $row['omang']       ?? null,
                        'passport'    => $row['passport']    ?? null,
                        // payout method (e.g. EFT) — distinct from percentage.
                        'payment'     => $row['payment']     ?? null,
                        // share of proceeds (1–100).
                        'percentage'  => $row['percentage']  ?? null,
                    ];
                })
                ->values();
        }

        return response()->json([
            'policy'           => $this->shapePolicySummary($policy),
            'recent_payments'  => $payments,
            'upcoming_charges' => $upcoming,
            'product_specific' => $productSpecific,
            'beneficiaries'    => $beneficiaries,
        ]);
    }

    /**
     * GET /api/v1/public/me/payments?from=YYYY-MM-DD&to=YYYY-MM-DD
     * Last 24 months of payments by default; date-range filterable.
     */
    public function payments(Request $request): JsonResponse
    {
        [$session, $errResp] = $this->resolveSession($request);
        if ($errResp) return $errResp;
        $cellphone = $this->normalize((string) ($session['cellphone'] ?? ''));

        $customer = DB::table('customer')->where('cellphone', $cellphone)->first(['id']);
        if (!$customer) return response()->json(['data' => []]);

        $policyNumbers = DB::table('policies')
            ->where('customer_id', $customer->id)
            ->pluck('policyNumber');

        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => 'nullable|date_format:Y-m-d',
        ]);
        $from = $request->input('from') ?: Carbon::now()->subMonths(24)->toDateString();
        $to   = $request->input('to')   ?: Carbon::now()->toDateString();

        $rows = DB::table('payment_transactions')
            ->whereIn('policyNumber', $policyNumbers)
            ->whereBetween('paymentDate', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderByDesc('paymentDate')
            ->limit(200)
            ->get(['id', 'policyNumber', 'paymentDate', 'amount', 'paymentMethod', 'status', 'numberOfInstalmentsPaid']);

        return response()->json([
            'from' => $from,
            'to'   => $to,
            'data' => $rows,
        ]);
    }

    /**
     * GET /api/v1/public/me/policies/{policyNumber}/documents
     * Returns downloadable links for the policy schedule + wording docs,
     * plus when (and to whom) documents were last emailed.
     */
    public function documents(Request $request, string $policyNumber): JsonResponse
    {
        [$session, $errResp] = $this->resolveSession($request);
        if ($errResp) return $errResp;
        $cellphone = $this->normalize((string) ($session['cellphone'] ?? ''));

        $customer = DB::table('customer')->where('cellphone', $cellphone)->first(['id']);
        if (!$customer) return response()->json(['error' => 'customer_not_found'], 404);

        $policy = DB::table('policies')
            ->where('customer_id', $customer->id)
            ->where('policyNumber', $policyNumber)
            ->first(['id', 'policyNumber', 'product_id', 'policyDocument']);
        if (!$policy) return response()->json(['error' => 'policy_not_found'], 404);

        $documents = [];
        if ($policy->policyDocument) {
            $documents[] = [
                'name' => 'Policy Schedule',
                'url'  => Helper::getCloudFrontURL($policy->policyDocument),
            ];
        }
        $wordingDocs = DB::table('documents')
            ->where('status', 1)
            ->where(function ($q) use ($policy) {
                $q->where('product_id', $policy->product_id)->orWhere('product_id', -1);
            })
            ->get(['name', 'link']);
        foreach ($wordingDocs as $doc) {
            if (!$doc->link) continue;
            $documents[] = [
                'name' => $doc->name ?: 'Policy Wording',
                'url'  => Helper::getCloudFrontURL($doc->link),
            ];
        }

        $lastSent = sentPolicyDocumentLogs::where('policyNumber', $policy->policyNumber)
            ->orderByDesc('id')
            ->first(['email', 'sentBy', 'created_at']);

        return response()->json([
            'documents' => $documents,
            'last_sent' => $lastSent ? [
                'email'  => $lastSent->email,
                'sentBy' => $lastSent->sentBy,
                'sentAt' => $lastSent->created_at,
            ] : null,
        ]);
    }

    /**
     * POST /api/v1/public/me/policies/{policyNumber}/resend-documents
     * Re-emails the policy schedule + applicable wording documents to the
     * customer's registered email. Generates the schedule PDF first if it
     * doesn't exist yet. The send is logged to sentPolicyDocumentLogs
     * (sentBy = 'Customer') inside DocumentController::sendPolicyDocument.
     */
    public function resendDocuments(Request $request, string $policyNumber): JsonResponse
    {
        [$session, $errResp] = $this->resolveSession($request);
        if ($errResp) return $errResp;
        $cellphone = $this->normalize((string) ($session['cellphone'] ?? ''));

        $customer = DB::table('customer')->where('cellphone', $cellphone)->first(['id', 'email']);
        if (!$customer) return response()->json(['error' => 'customer_not_found'], 404);
        if (!$customer->email) {
            return response()->json(['success' => false, 'message' => 'No email address on file for this account.'], 422);
        }

        $policy = DB::table('policies')
            ->where('customer_id', $customer->id)
            ->where('policyNumber', $policyNumber)
            ->first(['id', 'policyNumber', 'policyDocument']);
        if (!$policy) return response()->json(['error' => 'policy_not_found'], 404);

        try {
            $documentController = new DocumentController();
            if (!$policy->policyDocument) {
                $documentController->generatePolicyDocument($policy->id);
            }
            $sent = $documentController->sendPolicyDocument($policy->id, 'Customer', true);
        } catch (\Throwable $ex) {
            Log::error('Me::resendDocuments failed', [
                'policyNumber' => $policyNumber,
                'message'      => $ex->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Could not send documents right now — please try again shortly.'], 500);
        }

        if (!$sent) {
            return response()->json(['success' => false, 'message' => 'Could not send documents right now — please try again or contact support.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Documents sent to ' . $customer->email . '.']);
    }

    /**
     * Resolve and validate the Bearer session. Returns [sessionArray, null]
     * on success; [null, errorJsonResponse] on failure.
     */
    private function resolveSession(Request $request): array
    {
        $auth = $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return [null, response()->json(['error' => 'auth_required'], 401)];
        }
        $session = $this->otp->validateToken(trim($m[1]));
        if (!$session) {
            return [null, response()->json(['error' => 'session_invalid_or_expired'], 401)];
        }
        return [$session, null];
    }

    private function shapePolicySummary($p): array
    {
        return [
            'id'                 => $p->id,
            'policyNumber'       => $p->policyNumber,
            'product_id'         => $p->product_id,
            'product_name'       => $p->product_name ?? null,
            'status'             => (int) $p->status,
            'status_label'       => $this->statusLabel((int) $p->status),
            'premium'            => $p->premium ?? null,
            'premium_freq'       => $p->premium_freq ?? null,
            'premium_freq_label' => $this->freqLabel($p->premium_freq ?? null),
            'billing'            => $p->billing ?? null,
            'billing_start_date' => $p->billingStartDate ?? null,
            'policy_start_date'  => $p->policyStartDate ?? null,
            'policy_end_date'    => $p->policyEndDate ?? null,
            'vehicle_reg'        => $p->vehicle_reg ?? null,
            'created_at'         => $p->created_at ?? null,
        ];
    }

    private function statusLabel(int $status): string
    {
        return match ($status) {
            1       => 'Active',
            2       => 'Cancelled',
            3       => 'Lapsed',
            4       => 'Pending',
            5       => 'Expired',
            default => 'Unknown',
        };
    }

    private function freqLabel($freq): ?string
    {
        if ($freq === null) return null;
        return match ((int) $freq) {
            1       => 'Monthly',
            2       => 'Quarterly',
            3       => 'Annual',
            default => null,
        };
    }

    /**
     * Gender stored on policy_beneficiary is int(11): 1=Male, 0=Female
     * (mirrors customer_profile.gender). Older rows may carry a string —
     * pass those through so we never mislabel. Returns null when unset.
     */
    private function genderLabel($gender): ?string
    {
        if ($gender === null || $gender === '') return null;
        if (is_numeric($gender)) return ((int) $gender) === 1 ? 'Male' : 'Female';
        $g = strtolower(trim((string) $gender));
        if ($g === 'm' || $g === 'male')   return 'Male';
        if ($g === 'f' || $g === 'female') return 'Female';
        return (string) $gender;
    }

    private function normalize(string $cellphone): string
    {
        $digits = preg_replace('/\D/', '', $cellphone);
        if (str_starts_with($digits, '267') && strlen($digits) === 11) return substr($digits, 3);
        return $digits;
    }
}
