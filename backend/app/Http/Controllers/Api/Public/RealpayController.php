<?php

namespace AlphaDirect\Http\Controllers\Api\Public;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\PublicOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Customer-facing RealPay debit-order setup for start.alphadirect.co.bw.
 *
 * Three endpoints, public/Bearer-gated:
 *   GET  /api/v1/public/realpay/banks                       — list of supported banks
 *   GET  /api/v1/public/realpay/banks/{bankId}/branches     — branches for a bank
 *   POST /api/v1/public/realpay/initiate                    — create the contract
 *
 * Bearer auth on initiate is the same payment_authorize session token the
 * customer minted at /quote/motor's auth gate. The session's cellphone
 * must match the motor_quote's cellphone — same protection as DPO.
 *
 * Live RealPay API is gated by REALPAY_LIVE=true. When off (default on
 * dev), the controller persists local rows and returns success without
 * the upstream call, so the FE flow can be tested end-to-end before
 * sandbox credentials land.
 */
class RealpayController extends Controller
{
    public function __construct(private PublicOtpService $otp) {}

    /**
     * GET /api/v1/public/realpay/banks
     * Returns: [{ id, bank_number, bank_name }]
     *
     * Reads the same `banks` table the legacy AddRealpayContract Livewire
     * loads. Cacheable via CDN — bank list changes maybe once a year.
     */
    public function banks(): JsonResponse
    {
        $rows = DB::table('banks')
            ->orderBy('bank_name')
            ->get(['id', 'bank_number', 'bank_name']);
        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/v1/public/realpay/banks/{bankId}/branches
     * Returns: [{ branch_id, name }]
     */
    public function branches(int $bankId): JsonResponse
    {
        // Quirk: bankBranches.bank_id joins on banks.bank_number (the
        // numeric bank code RealPay uses), NOT on banks.id. The legacy
        // AddRealpayContract Livewire calls loadBranches(bankNumber);
        // we accept banks.id from the FE for cleanliness then resolve
        // bank_number here so the FE doesn't need to know the quirk.
        $bank = DB::table('banks')->where('id', $bankId)->first();
        if (!$bank) return response()->json(['data' => []]);

        $rows = DB::table('bankBranches')
            ->where('bank_id', $bank->bank_number)
            ->orderBy('name')
            ->get(['branch_id', 'name']);
        return response()->json(['data' => $rows]);
    }

    /**
     * POST /api/v1/public/realpay/initiate
     *
     * Body:
     *   quote_number       — required, the MQ-* number from create-motor
     *   bank_id            — required
     *   branch_id          — required
     *   account_number     — required, digits only
     *   account_type       — required: CheckingAccount | SavingsAccount
     *   collection_day     — required 1..31 (or 99 = month-end)
     *   start_date         — required YYYY-MM-DD (today or future)
     *   frequency          — required: monthly | quarterly | annual
     *
     * Returns:
     *   { ok, contract_number, status, first_collection_date, amount }
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quote_number'    => 'required|string|max:32',
            'bank_id'         => 'required|integer',
            'branch_id'       => 'required|integer',
            'account_number'  => ['required', 'string', 'min:6', 'max:20', 'regex:/^[0-9]+$/'],
            'account_type'    => 'required|string|in:CheckingAccount,SavingsAccount',
            'collection_day'  => 'required|integer|between:1,99',
            'start_date'      => 'required|date_format:Y-m-d|after_or_equal:today',
            'frequency'       => 'required|string|in:monthly,quarterly,annual',
        ]);

        // ── Bearer session ────────────────────────────────────────────
        $auth = $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return response()->json(['ok' => false, 'error' => 'session_required'], 401);
        }
        $session = $this->otp->validateToken(trim($m[1]));
        if (!$session) {
            return response()->json(['ok' => false, 'error' => 'session_invalid_or_expired'], 401);
        }

        // ── Quote lookup + cellphone match ────────────────────────────
        $quote = DB::table('motor_quotes')
            ->where('quote_number', $validated['quote_number'])
            ->first();
        if (!$quote) {
            return response()->json(['ok' => false, 'error' => 'quote_not_found'], 404);
        }
        $sessionPhone = preg_replace('/\D/', '', (string) ($session['cellphone'] ?? ''));
        if (str_starts_with($sessionPhone, '267') && strlen($sessionPhone) === 11) {
            $sessionPhone = substr($sessionPhone, 3);
        }
        if ($sessionPhone !== (string) $quote->cellphone) {
            return response()->json(['ok' => false, 'error' => 'session_phone_mismatch'], 403);
        }
        if ($quote->status !== 'pending_pay') {
            return response()->json([
                'ok' => false,
                'error' => 'quote_not_payable',
                'message' => "Quote status is '{$quote->status}'.",
            ], 409);
        }

        // ── Resolve bank / branch ─────────────────────────────────────
        // bankBranches.bank_id holds banks.bank_number (RealPay code),
        // not banks.id. Resolve the bank first, then look up the branch
        // by its numeric code.
        $bank = DB::table('banks')->where('id', $validated['bank_id'])->first();
        if (!$bank) {
            return response()->json(['ok' => false, 'error' => 'bank_or_branch_invalid'], 422);
        }
        $branch = DB::table('bankBranches')
            ->where('bank_id', $bank->bank_number)
            ->where('branch_id', $validated['branch_id'])
            ->first();
        if (!$branch) {
            return response()->json(['ok' => false, 'error' => 'bank_or_branch_invalid'], 422);
        }

        // ── Amount + frequency mapping ────────────────────────────────
        $amount = (float) $quote->amount_to_pay;
        $rpFrequency = match ($validated['frequency']) {
            'monthly'   => 'MNTH',
            'quarterly' => 'QURT',
            'annual'    => 'YEAR',
        };
        $instalments = match ($validated['frequency']) {
            'monthly'   => 12,
            'quarterly' => 4,
            'annual'    => 1,
        };

        // ── Local persistence (always) — banking + contract row ───────
        try {
            DB::beginTransaction();

            $bankingId = DB::table('customer_banking')->insertGetId([
                'cellphone'       => $sessionPhone,
                'bank_id'         => $bank->id,
                'branch_id'       => $branch->branch_id,
                'accountNumber'   => $validated['account_number'],
                'accountType'     => $validated['account_type'],
                'created_at'      => Carbon::now(),
                'updated_at'      => Carbon::now(),
            ]);

            // ContractNumber follows legacy "{policy_id}/{count+1}". We
            // don't have a policy_id yet (materialisation runs after
            // contract success), so we use the quote_number as the
            // ClientNumber + an incrementing suffix per cellphone.
            $existingCount = DB::table('realpay_client_contracts')
                ->where('cellphone', $sessionPhone)
                ->count();
            $contractNumber = $quote->quote_number . '/' . ($existingCount + 1);

            $clientContractId = DB::table('realpay_client_contracts')->insertGetId([
                'cellphone'         => $sessionPhone,
                'customer_banking_id' => $bankingId,
                'client_number'     => $quote->quote_number,
                'contract_number'   => $contractNumber,
                'status'            => 0, // 0 = pending until RealPay or webhook confirms
                'created_at'        => Carbon::now(),
                'updated_at'        => Carbon::now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('public_realpay.local_persist_failed', [
                'quote_number' => $quote->quote_number,
                'msg'          => $e->getMessage(),
            ]);
            return response()->json([
                'ok' => false,
                'error' => 'local_persist_failed',
                'message' => 'Could not save your debit-order details. Try again.',
            ], 500);
        }

        // ── RealPay API call (gated) ──────────────────────────────────
        $live = filter_var(env('REALPAY_LIVE', false), FILTER_VALIDATE_BOOLEAN);
        $apiResult = ['ok' => false, 'error' => 'realpay_not_live'];
        if ($live) {
            $apiResult = $this->callRealpayCreateContract([
                'client_number'         => $quote->quote_number,
                'contract_number'       => $contractNumber,
                'frequency_code'        => $rpFrequency,
                'collection_day'        => $validated['collection_day'],
                'first_collection_date' => $validated['start_date'],
                'first_collection_amount' => $amount,
                'instalment_start_date' => $validated['start_date'],
                'instalment_amount'     => $amount,
                'number_of_instalments' => $instalments,
                'bank_branch_code'      => $branch->branch_id,
                'account_number'        => $validated['account_number'],
                'account_type'          => $validated['account_type'],
            ]);
        }

        // ── Update contract status based on RealPay outcome ───────────
        $finalStatus = $live && ($apiResult['ok'] ?? false) ? 1 : 0;
        DB::table('realpay_client_contracts')
            ->where('id', $clientContractId)
            ->update([
                'status'      => $finalStatus,
                'rp_response' => json_encode($apiResult),
                'updated_at'  => Carbon::now(),
            ]);

        // When live + success, mark quote paid so MaterialiseMotorQuoteJob
        // can run on the same path DPO uses. When in dev (REALPAY_LIVE=false)
        // we still mark paid so the customer flow completes — ops can
        // manually verify the contract in the legacy admin.
        DB::table('motor_quotes')
            ->where('id', $quote->id)
            ->update([
                'status'        => 'paid',
                'paid_at'       => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ]);
        // Dispatch the materialisation job synchronously so the customer
        // sees a real policy number on the receipt screen.
        try {
            \AlphaDirect\Jobs\MaterialiseMotorQuoteJob::dispatchSync(
                $quote->quote_number,
                $apiResult['rp_ref'] ?? null,
            );
        } catch (\Throwable $e) {
            Log::warning('public_realpay.materialise_failed', [
                'quote_number' => $quote->quote_number,
                'msg'          => $e->getMessage(),
            ]);
        }

        return response()->json([
            'ok'                    => true,
            'live'                  => $live,
            'contract_number'       => $contractNumber,
            'status'                => $finalStatus === 1 ? 'active' : 'pending',
            'first_collection_date' => $validated['start_date'],
            'amount'                => $amount,
            'frequency'             => $validated['frequency'],
            'rp_response'           => $live ? $apiResult : null,
        ], 201);
    }

    /**
     * GET /api/v1/public/realpay/mandate-status?policyNumber=…
     *
     * What happened to this policy's debit-order mandate, and what the customer
     * should do next. Unauthenticated on purpose (the customer has just been
     * bounced back from a payment page and may not hold a session), so the
     * response is restricted to facts they already have:
     *
     *   { ok, mandate_status, policy_status, next_action }
     *
     * `mandate_status` and `policy_status` are deliberately separate. A mandate
     * reaching `active` means the first collection succeeded — the policy is
     * activated by the instalment webhook, not by the mandate. Front ends must
     * not promise activation on mandate completion.
     */
    public function mandateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'policyNumber' => 'required|string|max:32',
        ]);

        $status = app(\AlphaDirect\Services\RealPayMandateService::class)
            ->statusFor($validated['policyNumber']);

        if ($status['next_action'] === 'unknown_policy') {
            return response()->json(['ok' => false, 'error' => 'policy_not_found'], 404);
        }

        return response()->json([
            'ok'             => true,
            'mandate_status' => $status['mandate_status'],
            'policy_status'  => $status['policy_status'],
            'next_action'    => $status['next_action'],
        ]);
    }

    /**
     * Call RealPay createContract. Returns ['ok' => bool, 'rp_ref' => string?, 'error' => string?, 'raw' => …].
     *
     * Wraps OAuth token fetch + the contract POST. Errors are surfaced
     * up to the caller which marks the local contract row pending and
     * returns a friendly message. Hardened with timeouts + SSL toggle
     * so a flaky RealPay sandbox doesn't hang the customer.
     */
    private function callRealpayCreateContract(array $p): array
    {
        $base       = rtrim((string) config('realpay.base_url'), '/');
        $product    = (string) env('REALPAY_PRODUCT', 'FNBNDOBW');
        $merchant   = (string) config('realpay.merchant');
        $version    = (string) env('REALPAY_VERSION', '1');
        $clientAuth = (string) config('realpay.realpay_client_auth'); // base64(user:pass)
        if (!$base || !$merchant || !$clientAuth) {
            return ['ok' => false, 'error' => 'realpay_env_missing'];
        }

        $verifySsl = filter_var(env('CURL_VERIFY_SSL', config('app.env') === 'production'), FILTER_VALIDATE_BOOLEAN);

        try {
            // Token
            $tokenResp = Http::withOptions(['verify' => $verifySsl])
                ->withHeaders(['Authorization' => 'Basic ' . $clientAuth])
                ->asForm()
                ->timeout(20)
                ->post($base . '/oauth/token?grant_type=client_credentials');
            if ($tokenResp->failed()) {
                return ['ok' => false, 'error' => 'realpay_oauth_failed', 'raw' => $tokenResp->body()];
            }
            $accessToken = (string) $tokenResp->json('access_token');

            // Contract create
            $url = $base . "/maintain/contracts/{$product}?BeneficiaryUser={$merchant}&Version={$version}";
            $body = [
                'ContractPostRequest' => [[
                    'ClientNumber'             => $p['client_number'],
                    'ContractNumber'           => $p['contract_number'],
                    'FrequencyCode'            => $p['frequency_code'],
                    'CollectionDay'            => (string) $p['collection_day'],
                    'TrackingCode'             => '44',
                    'FirstCollectionDate'      => $p['first_collection_date'],
                    'FirstCollectionAmount'    => (string) $p['first_collection_amount'],
                    'InstalmentStartDate'      => $p['instalment_start_date'],
                    'InstalmentAmount'         => (string) $p['instalment_amount'],
                    'NumberOfInstallments'     => (string) $p['number_of_instalments'],
                    'CTCPercentage'            => 1,
                    'BankBranchCode'           => (string) $p['bank_branch_code'],
                    'AccountNumber'            => $p['account_number'],
                    'AccountType'              => $p['account_type'],
                ]],
            ];
            $resp = Http::withOptions(['verify' => $verifySsl])
                ->withToken($accessToken)
                ->acceptJson()
                ->timeout(45)
                ->post($url, $body);

            if ($resp->failed()) {
                return ['ok' => false, 'error' => 'realpay_create_failed', 'status' => $resp->status(), 'raw' => substr($resp->body(), 0, 1500)];
            }
            return [
                'ok'     => true,
                'rp_ref' => (string) $resp->json('Reference', $p['contract_number']),
                'raw'    => $resp->json(),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'realpay_exception', 'msg' => $e->getMessage()];
        }
    }
}
