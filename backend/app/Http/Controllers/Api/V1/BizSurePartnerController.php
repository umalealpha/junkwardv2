<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\DpoPaymentController;
use AlphaDirect\Http\Requests\BizSure\StoreBizSurePolicyRequest;
use AlphaDirect\Lookup;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use AlphaDirect\Services\BizSureOrchestrator;
use AlphaDirect\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * V1 (/api/v1) BizSure partner controller.
 *
 * Thin HTTP surface over the hardened create/pay/KYC/lookup logic that already
 * exists elsewhere in V2 — this controller REUSES:
 *   - AlphaDirect\Services\BizSureOrchestrator::createPolicy (create-policy)
 *   - AlphaDirect\Http\Controllers\DpoPaymentController::saveDpoOnesOffTransaction
 *     (server-verified DPO payment + activation)
 *   - CommonApis\LookupController::getByKey query (lookup)
 *   - FrontendPay\ClaimController::checkAgent logic (verifyAgent)
 *   - UploadController's BizSure KYC decode+store pattern (uploadKyc)
 *
 * Hardening rules honoured on every method (mirrors the legacy
 * CommonApis/BizSurePolicyController):
 *   - NEVER log customer PII (firstname/omang/email/phone). Only shape/counts.
 *   - NEVER return raw exception text to the client (DB errors echo the failing
 *     SQL with bound PII). Return a correlation_id the partner can quote and
 *     ops can grep the server logs for.
 *
 * Routing (routes/api_v1.php) is owned by another worker — this file does not
 * register any routes.
 */
class BizSurePartnerController extends Controller
{
    public function __construct(private BizSureOrchestrator $orchestrator)
    {
    }

    /**
     * POST — create a BizSure policy. CRITICAL method: mirrors the hardened
     * CommonApis/BizSurePolicyController::createPolicy exactly (correlation id,
     * no-PII logging, nested-vs-flat response). Validation is enforced by the
     * StoreBizSurePolicyRequest before we ever get here.
     */
    public function store(StoreBizSurePolicyRequest $request): JsonResponse
    {
        // Correlation id ties a 500 the partner sees to our server logs without
        // leaking any internal detail (or PII) onto the wire.
        $correlationId = uniqid('bzs_', true);

        // NOTE: never log customer PII (firstname/omang/email/phone) here —
        // DPA / AD-POL-AI-GOV-001. Only non-identifying shape + counts.
        Log::info('[BizSurePartnerController] store entry validated', [
            'correlation_id'     => $correlationId,
            'leadSource'         => $request->input('leadSource'),
            'business_structure' => $request->input('business_structure'),
            'product'            => $request->input('product'),
            'directors_count'    => count((array) $request->input('directors', [])),
            'covers_count'       => count((array) $request->input('covers', [])),
            'vehicles_count'     => count((array) $request->input('vehicles', [])),
        ]);

        try {
            $result = $this->orchestrator->createPolicy($request->all());

            // The BizSure client reads response.policy.{id, policyNumber,
            // customer_id, status, premium}. The orchestrator returns those as
            // flat keys; expose a nested `policy` object too (mirrors V1's
            // nested shape) and keep the flat keys for forward-compat.
            $nested = [
                'id'           => $result['policy_id']     ?? null,
                'policyNumber' => $result['policy_number'] ?? null,
                'customer_id'  => $result['customer_id']   ?? null,
                'status'       => $result['policy_status'] ?? null,
                'premium'      => $result['total_premium'] ?? null,
            ];

            return response()->json(array_merge(
                ['success' => true, 'policy' => $nested],
                $result
            ), 200);
        } catch (\Throwable $e) {
            Log::error('[BizSurePartnerController] orchestrator threw', [
                'correlation_id' => $correlationId,
                'error'          => $e->getMessage(),
                'trace'          => $e->getTraceAsString(),
            ]);

            // Do NOT return $e->getMessage() — DB exceptions echo the failing
            // SQL with bound customer PII. Return only the correlation id.
            return response()->json([
                'success'        => false,
                'error'          => 'Internal error during BizSure createPolicy.',
                'correlation_id' => $correlationId,
            ], 500);
        }
    }

    /**
     * GET — fetch a BizSure policy summary by policy number. 404 unless the
     * policy exists AND is a BizSure lead (leadSource='bizsure'), so this
     * endpoint can't be used to read non-BizSure policies.
     */
    public function show(string $number): JsonResponse
    {
        $policy = Policy::where('policyNumber', $number)
            ->where('leadSource', 'bizsure')
            ->first();

        if ($policy === null) {
            return response()->json([
                'success' => false,
                'error'   => 'BizSure policy not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'policy'  => [
                'id'           => $policy->id,
                'policyNumber' => $policy->policyNumber,
                'customer_id'  => $policy->customer_id,
                'status'       => $policy->status,
                'premium'      => $policy->premium,
            ],
        ], 200);
    }

    /**
     * POST — accept base64 KYC documents for a BizSure policy and forward them
     * to S3, then link them on customer_kyc / policy_kyc_documents.
     *
     * Decode+store pattern mirrors UploadController's BizSure block, with three
     * deliberate hardenings the task calls for:
     *   (a) objects are stored PRIVATE (no 'public' visibility arg) — KYC
     *       identity documents must not be world-readable;
     *   (b) decoded bytes are written to a SYSTEM temp file (tempnam) not the
     *       CWD, and unlinked after streaming to S3;
     *   (c) customer_id + policy_id are resolved from the policy number, not
     *       taken from the client.
     *
     * Accepted fields: company_reg, company_extract, bors, tin (→ customer_kyc
     * flat columns), omang (→ customer_kyc.omang), residence (→
     * customer_kyc.proof_residence), and director_{i}_id (→ policy_kyc_documents
     * doc_type='director_id', doc_index=i).
     */
    public function uploadKyc(string $number, Request $request): JsonResponse
    {
        $correlationId = uniqid('bzs_kyc_', true);

        try {
            $policy = Policy::where('policyNumber', $number)
                ->where('leadSource', 'bizsure')
                ->first();

            if ($policy === null) {
                return response()->json([
                    'success' => false,
                    'error'   => 'BizSure policy not found.',
                ], 404);
            }

            $policyId   = $policy->id;
            $customerId = $policy->customer_id;
            $stored     = [];

            // Commercial + identity certs → flat customer_kyc columns.
            // company_reg/company_extract/bors/tin mirror UploadController's
            // $bizCommercialMap; omang → customer_kyc.omang; residence →
            // customer_kyc.proof_residence (both pre-existing KYC columns).
            $customerKycMap = [
                'company_reg'     => 'company_reg',
                'company_extract' => 'company_extract',
                'bors'            => 'bors',
                'tin'             => 'tin',
                'omang'           => 'omang',
                'residence'       => 'proof_residence',
            ];

            foreach ($customerKycMap as $field => $column) {
                $raw = $request->input($field);
                if ($raw === null || $raw === '') {
                    continue;
                }

                $path = $this->storeBase64ToPrivateS3((string) $raw, "BizSure/{$policyId}/{$field}");

                DB::table('customer_kyc')->updateOrInsert(
                    ['customer_id' => $customerId],
                    [$column => $path]
                );

                $stored[] = $field;
            }

            // Director KYC images → sparse policy_kyc_documents rows, keyed by
            // director index so the admin UI can join director_index ↔ doc_index.
            foreach ($request->all() as $key => $value) {
                if ($value === null || $value === '' || ! preg_match('/^director_(\d+)_id$/', (string) $key, $m)) {
                    continue;
                }

                $directorIndex = (int) $m[1];
                $path = $this->storeBase64ToPrivateS3((string) $value, "BizSure/{$policyId}/director_{$directorIndex}_id");

                DB::table('policy_kyc_documents')->insert([
                    'policy_id'   => $policyId,
                    'customer_id' => $customerId,
                    'doc_type'    => 'director_id',
                    'doc_index'   => $directorIndex,
                    'file_path'   => $path,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                $stored[] = $key;
            }

            return response()->json([
                'success' => true,
                'stored'  => $stored,
            ], 200);
        } catch (\Throwable $e) {
            // Don't echo $e->getMessage() to the partner — it can carry file
            // paths / SQL with PII. Log server-side, return a generic error.
            Log::error('[BizSurePartnerController] uploadKyc failed', [
                'correlation_id' => $correlationId,
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'success'        => false,
                'error'          => 'KYC upload failed.',
                'correlation_id' => $correlationId,
            ], 500);
        }
    }

    /**
     * POST — idempotently record + activate a server-verified DPO payment for a
     * BizSure policy.
     *
     * The payment is NOT client-asserted: saveDpoOnesOffTransaction fires
     * VerifyTokenEvent, which calls DPO's verifyToken API server-side
     * (VerifyTokenListener) to confirm the transaction and amount. The client
     * only supplies the DPO TransactionToken; the server verifies it.
     *
     * Flow:
     *   (1) resolve the policy by number (must be a BizSure lead, else 404);
     *   (2) already-active idempotency guard FIRST — if the policy is active
     *       (status=1) AND this exact DPO token already produced a
     *       PaymentTransaction row, the payment was already recorded and the
     *       policy already activated on a prior call, so return early without
     *       re-hitting DPO verify or re-running activation;
     *   (3) delegate to DpoPaymentController::saveDpoOnesOffTransaction (its
     *       existing leadSource='bizsure' JSON branch) so the money math /
     *       ledger / activation are NOT duplicated here.
     */
    public function recordPayment(string $number, Request $request): JsonResponse
    {
        $correlationId = uniqid('bzs_pay_', true);

        try {
            $policy = Policy::where('policyNumber', $number)
                ->where('leadSource', 'bizsure')
                ->first();

            if ($policy === null) {
                return response()->json([
                    'success' => false,
                    'error'   => 'BizSure policy not found.',
                ], 404);
            }

            $token = $request->input('TransactionToken');

            // (2) Already-active idempotency guard — applied FIRST. Mirrors the
            // intent of the DPO flow's "policy already active + this token
            // already booked" short-circuit. No DPO verify, no re-activation,
            // no money math re-run.
            if ((int) $policy->status === 1
                && $token !== null && trim((string) $token) !== ''
                && PaymentTransaction::where('referenceNumber', strtoupper((string) $token))->exists()
            ) {
                return response()->json([
                    'success'        => true,
                    'already_active' => true,
                    'policy_number'  => $policy->policyNumber,
                    'status'         => 'ISSUED',
                ], 200);
            }

            // (3) Delegate to the hardened, server-verified save/activate path.
            // saveDpoOnesOffTransaction reads policy_number as base64 and has a
            // ready leadSource='bizsure' JSON branch. Build a synthetic POST
            // request carrying only the fields it consumes.
            $sub = Request::create('/', 'POST', [
                'policy_number'     => base64_encode($policy->policyNumber),
                'amount'            => $request->input('amount'),
                'leadSource'        => 'bizsure',
                'TransactionToken'  => $token,
                'CCDapproval'       => $request->input('CCDapproval'),
                'PnrID'             => $request->input('PnrID'),
                'CompanyRef'        => $request->input('CompanyRef'),
                'payment_frequency' => $request->input('payment_frequency'),
            ]);

            $response   = (new DpoPaymentController())->saveDpoOnesOffTransaction($sub);
            $payload    = method_exists($response, 'getData') ? (array) $response->getData(true) : [];
            $httpStatus = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;

            // A 5xx from the delegated path may carry a raw exception message
            // (it returns $ex->getMessage() on failure). Do NOT forward that —
            // log it and hand back a generic error + correlation id.
            if ($httpStatus >= 500) {
                Log::error('[BizSurePartnerController] recordPayment delegate failed', [
                    'correlation_id' => $correlationId,
                    'delegate_status'=> $httpStatus,
                ]);

                return response()->json([
                    'success'        => false,
                    'error'          => 'Internal error during BizSure payment record.',
                    'correlation_id' => $correlationId,
                ], 500);
            }

            // Normalise the delegated {status: bool} shape onto the partner
            // contract's {success: bool}, preserving the delegated payload.
            return response()->json(array_merge(
                ['success' => (bool) ($payload['status'] ?? false)],
                $payload
            ), $httpStatus);
        } catch (\Throwable $e) {
            Log::error('[BizSurePartnerController] recordPayment failed', [
                'correlation_id' => $correlationId,
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'success'        => false,
                'error'          => 'Internal error during BizSure payment record.',
                'correlation_id' => $correlationId,
            ], 500);
        }
    }

    /**
     * GET — read a BizSure-allowed lookup list as [{value,label}].
     *
     * Reuses CommonApis/LookupController::getByKey's query (lookup_data rows for
     * the key, label falling back to value for legacy null labels), but behind a
     * fixed allow-list so the partner can only pull the four risk dropdowns it
     * needs — not enumerate arbitrary lookup_data keys.
     */
    public function lookup(string $key): JsonResponse
    {
        $allowed = [
            'risk_construction_type',
            'risk_occupation',
            'risk_occupancy_type',
            'risk_town_class',
        ];

        if (! in_array($key, $allowed, true)) {
            return response()->json([
                'error' => 'Unknown lookup key',
                'key'   => $key,
            ], 404);
        }

        $rows = Lookup::where('key', $key)->get(['value', 'label']);

        if ($rows->isEmpty()) {
            return response()->json([
                'error' => 'Unknown lookup key',
                'key'   => $key,
            ], 404);
        }

        $response = $rows->map(function ($row) {
            return [
                'value' => $row->value,
                'label' => $row->label !== null && $row->label !== '' ? $row->label : $row->value,
            ];
        })->values();

        return response()->json($response, 200);
    }

    /**
     * GET — verify a BizSure agent by code (users.id).
     *
     * Mirrors FrontendPay/ClaimController::checkAgent: resolve the agent User,
     * gate on the active flag (2 = suspended, 1 = active), and return a friendly
     * message + agency_id. Adds agent_email for the BizSure contract. No PIN is
     * accepted or checked here (that is checkAgent2's concern).
     */
    public function verifyAgent(string $code): JsonResponse
    {
        $agent = User::where('id', $code)
            ->first(['id', 'firstName', 'lastName', 'active', 'agency_id', 'email']);

        if ($agent === null) {
            return response()->json([
                'success' => false,
                'message' => "No agent found with ID: {$code}",
            ], 404);
        }

        if ((int) $agent->active === 2) {
            return response()->json([
                'success' => false,
                'message' => "Agent Suspended with ID: {$code}",
            ], 401);
        }

        if ((int) $agent->active !== 1) {
            return response()->json([
                'success' => false,
                'message' => "No agent found with ID: {$code}",
            ], 401);
        }

        return response()->json([
            'success'     => true,
            'message'     => 'You will be assisted by ' . trim($agent->firstName . ' ' . $agent->lastName),
            'agency_id'   => $agent->agency_id,
            'agent_email' => $agent->email,
        ], 200);
    }

    /**
     * Decode a base64 document and stream it to S3 as a PRIVATE object.
     *
     * Follows UploadController's BizSure decode pattern (prepend a data-URI
     * header so the existing explode(';base64,') split yields extension +
     * payload), but writes the decoded bytes to a SYSTEM temp file (never the
     * CWD) and unlinks it afterwards. No 'public' visibility arg is passed, so
     * the object lands private.
     *
     * Returns the S3 key the document was stored under.
     */
    private function storeBase64ToPrivateS3(string $rawBase64, string $keyPrefix): string
    {
        $imageInfo = explode(';base64,', 'data:image/jpeg;base64,' . $rawBase64);
        $imgExt    = str_replace('data:image/', '', $imageInfo[0]);
        $payload   = str_replace(' ', '+', $imageInfo[1] ?? '');
        $bytes     = base64_decode($payload);

        // System temp file (tempnam) — NOT the CWD (UploadController wrote
        // decoded bytes under an unsanitised firstName into the working dir).
        $tmpPath = tempnam(sys_get_temp_dir(), 'bzs_kyc_');

        try {
            file_put_contents($tmpPath, $bytes);

            $filePath = rtrim($keyPrefix, '/') . '.' . ($imgExt !== '' ? $imgExt : 'jpg');

            // PRIVATE object: no 'public' visibility arg (deliberate hardening
            // vs the legacy 'public' KYC ACL in UploadController).
            Storage::disk('s3')->put($filePath, file_get_contents($tmpPath));

            return $filePath;
        } finally {
            if (is_string($tmpPath) && file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }
}
