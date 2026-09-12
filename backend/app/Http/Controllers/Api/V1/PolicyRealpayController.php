<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\RealpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PolicyRealpayController
 *
 * Backs the three RealPay tabs on the Policy Details page:
 *   - GET    /api/v1/policies/{policyId}/realpay/contracts      (list)
 *   - POST   /api/v1/policies/{policyId}/realpay/contracts      (create)
 *   - GET    /api/v1/policies/{policyId}/realpay/installments   (transactions)
 *
 * Plus bank/branch lookups used by the Add form:
 *   - GET    /api/v1/realpay/banks
 *   - GET    /api/v1/realpay/banks/{bankId}/branches
 *
 * The behavioural model mirrors graphiteBWV8 AddRealpayContract Livewire:
 *   1. Find or create the RealPay client (one per cellphone/policy customer).
 *   2. Cancel any active contract on the policy.
 *   3. Create a new contract on RealPay; mark active locally.
 *   4. Persist banking, contract, and (when the API returns them) installments.
 *
 * The RealPay API is always called directly through RealpayService —
 * .env credentials (sandbox vs production) per environment determine
 * which RealPay endpoint is hit. graphiteBWV8 parity, no live/stub flag.
 */
class PolicyRealpayController extends Controller
{
    public function __construct(private RealpayService $rp) {}

    /**
     * Point the RealpayService at the right credential platform for a policy:
     * START for instant MIS products, legacy otherwise.
     *
     * The product→platform rule now lives on RealpayService so that every
     * caller that posts against a contract picks the same merchant. It used to
     * be a private const here, which is how PayNowService's "Collect Now"
     * one-off debit came to post instant-product instalments at the legacy
     * merchant.
     */
    private function selectRealpayPlatform($productId): void
    {
        $this->rp->usePlatform(RealpayService::platformForProduct($productId));
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/realpay/banks
    // ──────────────────────────────────────────────────────────────
    public function banks(): JsonResponse
    {
        $rows = DB::table('banks')
            ->orderBy('bank_name')
            ->get(['id', 'bank_number', 'bank_name']);
        return response()->json(['data' => $rows]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/realpay/banks/{bankNumber}/branches
    //
    // graphiteBWV8 parity (AddRealpayContract:187): the bank dropdown
    // submits banks.bank_number — the RealPay numeric bank code — NOT
    // banks.id. bankBranches.bank_id stores the same bank_number, so
    // the join is direct with no id↔bank_number translation.
    // ──────────────────────────────────────────────────────────────
    public function branches(int $bankNumber): JsonResponse
    {
        $rows = DB::table('bankBranches')
            ->where('bank_id', $bankNumber)
            ->orderBy('name')
            ->get(['branch_id', 'name']);
        return response()->json(['data' => $rows]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/policies/{policyId}/realpay/contracts
    // ──────────────────────────────────────────────────────────────
    public function listContracts(int $policyId): JsonResponse
    {
        $policy = DB::table('policies')->where('id', $policyId)->first(['id', 'policyNumber']);
        if (!$policy) return response()->json(['message' => 'Policy not found.'], 404);

        // Match by policy_id OR client_number (= policyNumber). Some contracts
        // are imported with only client_number set (policy_id null), which made
        // them invisible here even though the data was present.
        $contracts = DB::table('realpay_client_contracts')
            ->where(function ($q) use ($policyId, $policy) {
                $q->where('policy_id', $policyId);
                if (!empty($policy->policyNumber)) {
                    $q->orWhere('client_number', $policy->policyNumber);
                }
            })
            ->orderByDesc('id')
            ->get(['id', 'client_number', 'contract_number', 'rate_id', 'status', 'created_at', 'updated_at']);

        // Decorate each contract with the latest installment status — V8
        // shows a contract as "Cancelled" if its latest installment has
        // InstalmentStatus = 'I'.
        $contractNumbers = $contracts->pluck('contract_number')->filter()->values()->toArray();
        $latestStatusByContract = [];
        if (!empty($contractNumbers)) {
            $latestStatusByContract = DB::table('realpay_contract_installments')
                ->whereIn('contractNumber', $contractNumbers)
                ->select('contractNumber', DB::raw('MAX(id) as max_id'))
                ->groupBy('contractNumber')
                ->get()
                ->keyBy('contractNumber');
            $latestIds = collect($latestStatusByContract)->pluck('max_id')->all();
            $latestRows = DB::table('realpay_contract_installments')
                ->whereIn('id', $latestIds)
                ->pluck('InstalmentStatus', 'contractNumber');
            $latestStatusByContract = $latestRows->toArray();
        }

        return response()->json([
            'data' => $contracts->map(function ($c) use ($latestStatusByContract) {
                $latest = $latestStatusByContract[$c->contract_number] ?? null;
                $displayStatus = $latest === 'I' ? 'Cancelled' : ((int) $c->status === 1 ? 'Active' : 'Inactive');
                return [
                    'id'             => $c->id,
                    'clientNumber'   => $c->client_number,
                    'contractNumber' => $c->contract_number,
                    'rateId'         => $c->rate_id,
                    'status'         => (int) $c->status,
                    'statusLabel'    => $displayStatus,
                    'latestInstalmentStatus' => $latest,
                    'createdAt'      => $c->created_at,
                ];
            }),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/policies/{policyId}/realpay/installments
    //
    // Lists installments for the LATEST RealPay contract on the policy.
    // Mirrors V8 RealpayTransactions Livewire DataTable: each new
    // contract supersedes the previous one (the Add form explicitly
    // cancels the existing contract before creating a new one), so only
    // the most recent contract's schedule is operationally relevant.
    //
    // Previously this returned every installment ever created for the
    // policy_id — re-issuing a contract 7 times left 84 rows in the
    // UI with duplicate sequence numbers, which is what reviewers saw.
    // ──────────────────────────────────────────────────────────────
    public function listInstallments(int $policyId): JsonResponse
    {
        $policy = DB::table('policies')->where('id', $policyId)->first(['id', 'policyNumber']);
        if (!$policy) return response()->json(['message' => 'Policy not found.'], 404);

        // Latest contract for this policy = highest id. The Add form's
        // cancel-then-create flow means newer ids are always the active
        // schedule; old contracts are kept for audit but not displayed.
        $latestContract = DB::table('realpay_client_contracts')
            ->where(function ($q) use ($policyId, $policy) {
                $q->where('policy_id', $policyId);
                if (!empty($policy->policyNumber)) {
                    $q->orWhere('client_number', $policy->policyNumber);
                }
            })
            ->orderByDesc('id')
            ->first(['id', 'client_number', 'contract_number', 'status', 'created_at']);

        if (!$latestContract) {
            return response()->json([
                'data'     => [],
                'contract' => null,
            ]);
        }

        // Installments are keyed by clientNumber (= policyNumber) + contractNumber,
        // NOT policy_id — RealPay's debit-file/webhook import only writes those two
        // columns, leaving policy_id null on the installment rows. Filtering by
        // policy_id silently dropped every installment even though the data exists
        // (the contract list, which joins on contractNumber, still showed). This
        // matches V8, which always joins realpay_contract_installments.clientNumber
        // = realpay_client_contracts.client_number (e.g. ReconciliationRun).
        $clientNumber = $latestContract->client_number ?: $policy->policyNumber;

        $rows = DB::table('realpay_contract_installments')
            ->where('clientNumber', $clientNumber)
            ->where('contractNumber', $latestContract->contract_number)
            // InstalmentSequence is stored as varchar — a plain ORDER BY
            // sorts lexicographically (1, 10, 11, 12, 2, 3, …). Cast to
            // UNSIGNED so the schedule reads 1, 2, 3, …, 12 in the UI.
            ->orderByRaw('CAST(InstalmentSequence AS UNSIGNED) ASC')
            ->orderByDesc('id')
            ->get([
                'id', 'clientNumber', 'contractNumber',
                'InstalmentReferenceNumber', 'InstalmentSequence',
                'CTCAmount', 'InstalmentActionDate', 'TrackingCode',
                'InstalmentAmount', 'InstalmentStatus', 'retry_count',
                'instalmentResponse', 'note', 'created_at',
            ]);

        // RealPay's single-character installment status codes (V8 parity).
        $labelMap = [
            'S' => 'Success', 'F' => 'Failed', 'W' => 'Processing',
            'R' => 'Retry',   'A' => 'Active', 'I' => 'Cancelled', 'E' => 'Error',
        ];

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'id'                        => $r->id,
                'clientNumber'              => $r->clientNumber,
                'contractNumber'            => $r->contractNumber,
                'installmentReferenceNumber'=> $r->InstalmentReferenceNumber,
                'installmentSequence'       => (int) $r->InstalmentSequence,
                'ctcAmount'                 => $r->CTCAmount,
                'installmentActionDate'     => $r->InstalmentActionDate,
                'trackingCode'              => $r->TrackingCode,
                'installmentAmount'         => $r->InstalmentAmount,
                'installmentStatus'         => $r->InstalmentStatus,
                'installmentStatusLabel'    => $labelMap[$r->InstalmentStatus] ?? $r->InstalmentStatus,
                'retryCount'                => (int) ($r->retry_count ?? 0),
                'bankResponse'              => $r->instalmentResponse,
                'note'                      => $r->note,
                'createdAt'                 => $r->created_at,
            ]),
            // FE can render "Showing schedule for contract {contractNumber}"
            // and link back to the contract list for previous schedules.
            'contract' => [
                'id'             => $latestContract->id,
                'clientNumber'   => $latestContract->client_number,
                'contractNumber' => $latestContract->contract_number,
                'status'         => (int) $latestContract->status,
                'createdAt'      => $latestContract->created_at,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/policies/{policyId}/realpay/contracts/{contractId}/cancel
    //
    // Cancel a single RealPay contract on the policy. Resolves the
    // local realpay_client_contracts row to get the client/contract
    // numbers, fetches an OAuth token, then delegates to the service's
    // cancelContractByNumber() which DELETEs against each product and
    // updates the local row + installments on success.
    // ──────────────────────────────────────────────────────────────
    public function cancelContract(int $policyId, int $contractId): JsonResponse
    {
        $policy = DB::table('policies')->where('id', $policyId)->first(['id', 'policyNumber', 'product_id']);
        if (!$policy) return response()->json(['message' => 'Policy not found.'], 404);

        // Cancel on the same platform the contract was created on.
        $this->selectRealpayPlatform($policy->product_id);

        $contract = DB::table('realpay_client_contracts')
            ->where('id', $contractId)
            ->where('policy_id', $policyId)
            ->first(['id', 'client_number', 'contract_number', 'status']);
        if (!$contract) return response()->json(['message' => 'Contract not found on this policy.'], 404);

        $tokenResult = $this->rp->getAccessTokenWithDebug();
        if (!$tokenResult['token']) {
            return response()->json([
                'message' => 'Failed to authenticate with Realpay API. ' . ($tokenResult['error'] ?? ''),
            ], 422);
        }

        $result = $this->rp->cancelContractByNumber(
            $tokenResult['token'],
            (string) $contract->client_number,
            (string) $contract->contract_number,
            $policyId,
        );

        if (!$result['ok']) {
            return response()->json([
                'message' => 'Failed to cancel Realpay contract: ' . ($result['error'] ?? 'unknown error'),
            ], 422);
        }

        try {
            $policyModel = \AlphaDirect\Policy::find($policy->id);
            if ($policyModel) {
                activity('RealPay contract cancelled')
                    ->performedOn($policyModel)
                    ->causedBy(auth()->user())
                    ->log('RealPay debit-order contract cancelled: '
                        . 'ContractNumber - ' . $contract->contract_number
                        . ', ClientNumber - ' . $contract->client_number);
            }
        } catch (\Throwable $e) {
            Log::warning('realpay contract cancel activity log failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Realpay contract cancelled successfully.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/policies/{policyId}/realpay/sync-from-portal
    //
    // Checks the LIVE RealPay portal for an existing (active) contract on the
    // policy. RealPay is the source of truth — a contract can exist on the
    // portal without a local row (e.g. created on a run that didn't sync back).
    // When found, it is synced into our DB (contract + installments) via the
    // legacy RealPayController's idempotent fetchAndStoreRealpayContract, then
    // the freshly-stored contracts + installments are returned in the same
    // shape as listContracts / listInstallments so the FE can reuse its tables.
    // ──────────────────────────────────────────────────────────────
    public function syncFromPortal(int $policyId): JsonResponse
    {
        $policy = DB::table('policies')->where('id', $policyId)->first(['id', 'policyNumber']);
        if (!$policy) return response()->json(['message' => 'Policy not found.'], 404);

        // Guard the slow external RealPay round-trip — on any failure return a
        // clean 502 instead of a 30s timeout / 500.
        $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();
        try {
            $existing = $realpay->getExistingRealpayContract($policy->id, $policy->policyNumber);
            $synced   = $existing !== null ? $realpay->fetchAndStoreRealpayContract($policy, $existing) : 0;
        } catch (\Throwable $e) {
            Log::error('PolicyRealpay.syncFromPortal failed', [
                'policy_id' => $policyId,
                'message'   => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Could not reach RealPay to check the contract. Please try again.',
            ], 502);
        }

        Log::info('PolicyRealpay.syncFromPortal', [
            'policy_id' => $policyId,
            'on_portal' => $existing !== null,
            'synced'    => $synced,
        ]);

        // Reuse the existing read endpoints' formatting verbatim.
        return response()->json([
            'exists'       => $existing !== null,
            'synced'       => $synced,
            'contracts'    => $this->listContracts($policyId)->getData()->data ?? [],
            'installments' => $this->listInstallments($policyId)->getData()->data ?? [],
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/policies/{policyId}/realpay/contracts
    //
    // Mirrors graphiteBWV8 AddRealpayContract::submit() exactly,
    // restructured for V2's service+controller pattern. Steps:
    //
    //   1. Validate input + resolve policy / bank / branch.
    //   2. Cancel any existing contracts on RealPay (via API, both
    //      products — default + FNB).
    //   3. Check if the RealPay client exists (GET clients).
    //      - If not → create client (POST clients).
    //      - If yes → update client (PUT clients) with the latest
    //        banking details.
    //   4. Re-fetch OAuth token, build contract payload.
    //   5. POST the contract.
    //   6. **DB writes ONLY if RealPay responds with `ContractPostResponse[0].Successful`
    //      populated.** On any failure (token, client, contract) the
    //      controller returns an error and writes NOTHING locally —
    //      no realpay_client_contracts row, no installments, no
    //      customer_banking update. Same as V8.
    //   7. On success, persist:
    //        - realpay_client_contracts row (status=1)
    //        - customer_banking (insert or update)
    //        - policies (premium + billingStartDate)
    //        - realpay_contract_installments from the response's
    //          ContractInstalments (real RealPay-allocated dates +
    //          reference numbers, NOT a skeleton).
    // ──────────────────────────────────────────────────────────────
    public function createContract(Request $request, int $policyId): JsonResponse
    {
        // V8 validation rules (V8 AddRealpayContract::rules()).
        $validated = $request->validate([
            'id_type'                  => 'required|string|in:omang,passport',
            'id_number'                => 'required|string|max:50',
            'email'                    => 'required|email|max:120',
            'cellphone'                => 'required|string|max:20',
            'payment_frequency'        => 'required|string|in:monthly,quarterly,annual',
            'premium'                  => 'required|numeric|min:0.01',
            'billing_date'             => 'required|date_format:Y-m-d',
            'is_first_collection_same' => 'sometimes|boolean',
            'first_collection_date'    => 'nullable|date_format:Y-m-d',
            'is_first_instalment_same' => 'sometimes|boolean',
            'first_instalment_amount'  => 'nullable|numeric|min:0.01',
            'bank_id'                  => 'required|integer',
            'branch_id'                => 'required|integer',
            'account_number'           => ['required', 'string', 'min:4', 'max:30', 'regex:/^[0-9]+$/'],
            // V8 parity: 1 = Cheque, 2 = Savings. Stored as int in
            // customer_banking.accountType; sent to RealPay as the same
            // numeric string. All V8 admin views + the mobile app read
            // accountType using this convention, so the V2 RealPay form
            // must produce the same values or those readers see "N/A".
            'account_type'             => 'required|in:1,2',
        ]);

        // V8 parity — if user chose to mirror billing date / premium,
        // copy values before validation. Done here AFTER request->validate
        // because Laravel's validate doesn't mutate the input bag.
        $firstCollection = !empty($validated['is_first_collection_same'])
            ? $validated['billing_date']
            : ($validated['first_collection_date'] ?? null);
        $firstInstalmentAmount = !empty($validated['is_first_instalment_same'])
            ? $validated['premium']
            : ($validated['first_instalment_amount'] ?? null);

        // Resolve policy + bank + branch.
        // graphiteBWV8 parity: the FE submits bank_id = banks.bank_number
        // (the RealPay numeric bank code), NOT the surrogate banks.id.
        $policy = DB::table('policies')->where('id', $policyId)->first(['id', 'policyNumber', 'customer_id', 'product_id']);
        if (!$policy) return response()->json(['message' => 'Policy not found.'], 404);

        // Instant MIS products (1,2,4,5,6,9,10) create on the START platform
        // (merchant 19413); all other products stay on legacy. Must be set
        // BEFORE the first RealPay call (token/cancel/client/contract all use it).
        $this->selectRealpayPlatform($policy->product_id);

        $bank = DB::table('banks')->where('bank_number', $validated['bank_id'])->first();
        if (!$bank) return response()->json(['message' => 'Bank not found.'], 422);
        $branch = DB::table('bankBranches')
            ->where('bank_id', $bank->bank_number)
            ->where('branch_id', $validated['branch_id'])
            ->first();
        if (!$branch) return response()->json(['message' => 'Branch not found for selected bank.'], 422);

        $customer = DB::table('customer')->where('id', $policy->customer_id)
            ->first(['id', 'firstName', 'lastName']);
        $customerName = trim(($customer->firstName ?? '') . ' ' . ($customer->lastName ?? '')) ?: $policy->policyNumber;

        // Frequency mapping + V8 billing-day edge cases
        $freq = $this->rp->frequencyMap($validated['payment_frequency']);
        $collectionDay = (int) Carbon::parse($validated['billing_date'])->format('d');
        // V8: for monthly, days 29/30/31 → 99 ("month-end") so RealPay handles short months.
        if ($freq['code'] === 'MNTH' && in_array($collectionDay, [29, 30, 31], true)) {
            $collectionDay = 99;
        }

        // V8 contract number: "{policy_id}/{count+1}"
        $clientNumber   = (string) $policy->policyNumber;
        $existingCount  = DB::table('realpay_client_contracts')->where('policy_id', $policy->id)->count();
        $contractNumber = $policy->id . '/' . ($existingCount + 1);

        $debugTrail = [];

        // ── Step 1: OAuth token ────────────────────────────────────
        $tokenResult = $this->rp->getAccessTokenWithDebug();
        $debugTrail['oauth'] = [
            'token_obtained' => (bool) $tokenResult['token'],
            'http_status'    => $tokenResult['http_status'],
            'error'          => $tokenResult['error'],
            'url'            => $tokenResult['url'],
            'body'           => $tokenResult['http_body'],
        ];
        if (!$tokenResult['token']) {
            return response()->json([
                'message' => 'Failed to authenticate with Realpay API. ' . ($tokenResult['error'] ?? ''),
                'data'    => ['realpayDebug' => $debugTrail],
            ], 422);
        }
        $token = $tokenResult['token'];

        // ── Step 2: Cancel existing contracts on RealPay (both products) ──
        // V8 parity — always called regardless of local state.
        $cancelResult = $this->rp->cancelExistingContracts($token, $clientNumber, $policy->id);
        $debugTrail['cancel_existing'] = [
            'cancelled' => $cancelResult['cancelled'],
            'failed'    => $cancelResult['failed'],
            'details'   => $cancelResult['details'],
        ];

        // ── Step 3: Check / create / update client ────────────────
        $clientCheck = $this->rp->checkClientExists($token, $clientNumber, (int) $validated['bank_id']);
        $debugTrail['client_check'] = [
            'exists'      => $clientCheck['exists'],
            'http_status' => $clientCheck['http_status'],
        ];
        if ($clientCheck['exists'] === null) {
            return response()->json([
                'message' => 'Failed to check client existence on RealPay. Please try again.',
                'data'    => ['realpayDebug' => $debugTrail],
            ], 422);
        }

        $clientPayload = [
            'ClientNumber'      => $clientNumber,
            'ClientName'        => $customerName,
            'IDType'            => $validated['id_type'] === 'omang' ? 'I' : 'P',
            'IDNumber'          => $validated['id_number'],
            'CellphoneNumber'   => $validated['cellphone'],
            'EMail'             => $validated['email'],
            'BankCode'          => (string) $validated['bank_id'],
            'BranchCode'        => (string) $branch->branch_id,
            'AccountType'       => $validated['account_type'],
            'AccountNumber'     => $validated['account_number'],
            'AccountHolderName' => $customerName,
            'EmployeeGroupCode' => 'OT',
        ];
        if ($clientCheck['exists'] === false) {
            $clientWrite = $this->rp->createClient($token, $clientPayload, (int) $validated['bank_id']);
            $debugTrail['client_write'] = ['op' => 'create'] + $clientWrite;
            if (!$clientWrite['ok']) {
                return response()->json([
                    'message' => 'Failed to create Realpay client: ' . $clientWrite['error'],
                    'data'    => ['realpayDebug' => $debugTrail],
                ], 422);
            }
        } else {
            $clientWrite = $this->rp->updateClient($token, $clientPayload, (int) $validated['bank_id']);
            $debugTrail['client_write'] = ['op' => 'update'] + $clientWrite;
            if (!$clientWrite['ok']) {
                return response()->json([
                    'message' => 'Failed to update Realpay client: ' . $clientWrite['error'],
                    'data'    => ['realpayDebug' => $debugTrail],
                ], 422);
            }
        }

        // ── Step 4: Create the contract ────────────────────────────
        $contractResult = $this->rp->createContract($token, [
            'client_number'           => $clientNumber,
            'contract_number'         => $contractNumber,
            'frequency_code'          => $freq['code'],
            'collection_day'          => (string) $collectionDay,
            'first_collection_date'   => $firstCollection,
            'first_collection_amount' => $firstInstalmentAmount,
            'instalment_start_date'   => $validated['billing_date'],
            'instalment_amount'       => $validated['premium'],
            'number_of_instalments'   => (string) $freq['count'],
            'bank_id'                 => (int) $validated['bank_id'],
        ]);
        $debugTrail['contract_create'] = $contractResult['debug'] + [
            'ok'          => $contractResult['ok'],
            'http_status' => $contractResult['http_status'],
            'error'       => $contractResult['error'],
        ];

        if (!$contractResult['ok'] || empty($contractResult['successful'])) {
            // No local persistence — V8 parity. Return the RealPay error.
            return response()->json([
                'message' => 'Failed to create Realpay contract: ' . ($contractResult['error'] ?? 'unknown error'),
                'data'    => [
                    'realpayOk'    => false,
                    'realpayError' => $contractResult['error'],
                    'realpayStatus'=> $contractResult['http_status'],
                    'realpayRaw'   => $contractResult['raw'],
                    'realpayDebug' => $debugTrail,
                ],
            ], 422);
        }

        // ── Step 5: Persist locally (RealPay accepted the contract) ──
        // Single DB transaction so any failure rolls everything back.
        $successful = $contractResult['successful'];
        $now        = Carbon::now();

        try {
            $contractId = DB::transaction(function () use ($policy, $clientNumber, $contractNumber, $successful, $validated, $bank, $branch, $collectionDay, $firstInstalmentAmount, $now) {
                // realpay_client_contracts
                $newContractId = DB::table('realpay_client_contracts')->insertGetId([
                    'policy_id'       => $policy->id,
                    'client_number'   => $clientNumber,
                    'contract_number' => $contractNumber,
                    'rate_id'         => null,
                    'status'          => 1,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);

                // customer_banking (upsert) — V8 parity
                $bankingRow = [
                    'customer_id'      => $policy->customer_id,
                    'policy_id'        => $policy->id,
                    'billing'          => 'RealPay',
                    'billingCell'      => $validated['cellphone'],
                    'bankName'         => $bank->bank_name,
                    'branchCode'       => (string) $branch->branch_id,
                    'accountType'      => $validated['account_type'],
                    'billingStartDate' => $validated['billing_date'],
                    'billing_day'      => $collectionDay,
                    'accountNumber'    => $validated['account_number'],
                    'client_number'    => $clientNumber,
                    'contract_number'  => $contractNumber,
                    'active'           => 1,
                    'updated_at'       => $now,
                ];
                $existingBanking = DB::table('customer_banking')
                    ->where('policy_id', $policy->id)
                    ->first(['id']);
                if ($existingBanking) {
                    DB::table('customer_banking')->where('id', $existingBanking->id)->update($bankingRow);
                } else {
                    $bankingRow['created_at'] = $now;
                    DB::table('customer_banking')->insert($bankingRow);
                }

                // policies — update first_premium / premium / billing_start_date (V8 parity)
                DB::table('policies')->where('id', $policy->id)->update([
                    'premium'          => !empty($validated['is_first_instalment_same']) ? $firstInstalmentAmount : $validated['premium'],
                    'first_premium'    => !empty($validated['is_first_instalment_same']) ? $firstInstalmentAmount : $validated['premium'],
                    'billingStartDate' => $validated['billing_date'],
                    'updated_at'       => $now,
                ]);

                // realpay_contract_installments — V8 storeInstallments(): use the
                // ContractInstalments array RealPay returned, not a fabricated
                // skeleton. This guarantees the dates + references match what
                // RealPay scheduled on their side.
                if (!empty($successful['ContractInstalments']) && is_array($successful['ContractInstalments'])) {
                    $instRows = [];
                    foreach ($successful['ContractInstalments'] as $inst) {
                        $instRows[] = [
                            'policy_id'                 => $policy->id,
                            'clientNumber'              => $successful['ClientNumber']     ?? $clientNumber,
                            'contractNumber'            => $successful['ContractNumber']   ?? $contractNumber,
                            'InstalmentReferenceNumber' => $inst['InstalmentReferenceNumber'] ?? null,
                            'InstalmentSequence'        => $inst['InstalmentSequence']     ?? null,
                            'CTCAmount'                 => $inst['CTCAmount']              ?? 0,
                            'InstalmentActionDate'      => $inst['InstalmentActionDate']   ?? null,
                            'TrackingCode'              => $inst['TrackingCode']           ?? '44',
                            'InstalmentAmount'          => $inst['InstalmentAmount']       ?? 0,
                            'InstalmentStatus'          => $inst['InstalmentStatus']       ?? 'A',
                            'retry_count'               => 0,
                            'created_at'                => $now,
                            'updated_at'                => $now,
                        ];
                    }
                    if (!empty($instRows)) {
                        DB::table('realpay_contract_installments')->insert($instRows);
                    }
                }

                return $newContractId;
            });
        } catch (\Throwable $e) {
            Log::error('policy_realpay.persist_failed', [
                'policy_id' => $policy->id, 'contract_number' => $contractNumber, 'msg' => $e->getMessage(),
            ]);
            // RealPay already accepted the contract upstream — surface that
            // clearly so ops knows the local DB is out of sync.
            return response()->json([
                'message' => 'Contract created on RealPay but DB persistence failed: ' . $e->getMessage()
                           . ' (ClientNumber=' . $clientNumber . ', ContractNumber=' . $contractNumber . ')',
                'data'    => [
                    'realpayOk'         => true,
                    'clientNumber'      => $clientNumber,
                    'contractNumber'    => $contractNumber,
                    'realpayDebug'      => $debugTrail,
                ],
            ], 500);
        }

        // Audit trail — write to activity_log so the Policy > Audit Trail tab
        // picks it up. Wrapped in try/catch so a logging hiccup can't break a
        // contract that RealPay already accepted upstream.
        try {
            $policyModel = \AlphaDirect\Policy::find($policy->id);
            if ($policyModel) {
                activity('RealPay contract created')
                    ->performedOn($policyModel)
                    ->causedBy(auth()->user())
                    ->log('RealPay debit-order contract created: '
                        . 'ContractNumber - ' . $contractNumber
                        . ', ClientNumber - ' . $clientNumber
                        . ', Premium - P ' . number_format((float) $validated['premium'], 2, '.', '')
                        . ', Frequency - ' . $validated['payment_frequency']
                        . ', Bank - ' . ($bank->bank_name ?? '?')
                        . ', Billing Date - ' . $validated['billing_date']);
            }
        } catch (\Throwable $e) {
            Log::warning('realpay contract activity log failed: ' . $e->getMessage());
        }

        // Keep the structured log too — useful for grepping production logs
        // independent of the DB audit trail.
        Log::info('policy_realpay.contract_created', [
            'policy_id'       => $policy->id,
            'policy_number'   => $policy->policyNumber,
            'contract_number' => $contractNumber,
            'caused_by'       => auth()->user()?->id,
        ]);

        // The policy `show()` response (customer_banking.billing, premium,
        // billingStartDate — all updated above) is cached; without this the
        // Policy Details page keeps showing the pre-contract state until the
        // cache TTL expires.
        \AlphaDirect\Services\CacheService::forgetPolicy($policy->id);

        return response()->json([
            'message' => 'Realpay contract created successfully.',
            'data' => [
                'contractId'            => $contractId,
                'clientNumber'          => $clientNumber,
                'contractNumber'        => $contractNumber,
                'frequency'             => $validated['payment_frequency'],
                'numberOfInstalments'   => $freq['count'],
                'firstCollectionDate'   => $firstCollection,
                'firstInstalmentAmount' => $firstInstalmentAmount,
                'realpayOk'             => true,
                'realpayError'          => null,
                'realpayStatus'         => $contractResult['http_status'],
                'realpayDebug'          => $debugTrail,
            ],
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/policies/{policyId}/realpay/installments
    //
    // "Add New Installment" — graphiteBWV8 PolicyController::addNewRealpayInstallment
    // parity. Makes a LIVE RealPay API call to register the instalment, then
    // persists the returned row into realpay_contract_installments. Reuses the
    // legacy Admin\RealPayController store methods verbatim (product-aware):
    //   - product 3 (Motor Comprehensive): storeNewRealpayInstallmentForMotor
    //     (falls back to storeNewRealpayInstallment when the contract isn't on
    //      the START/motor product) — same branching as V8.
    //   - other products: storeNewInstallmentForInstantProduct.
    //
    // contractSequence is resolved server-side from the RealPay contract-info
    // API (V8 prefilled it into the modal from the same source); the FE only
    // supplies client/contract numbers (prefilled from the latest contract),
    // the instalment date and the premium.
    // ──────────────────────────────────────────────────────────────
    public function addInstallment(Request $request, int $policyId): JsonResponse
    {
        $validated = $request->validate([
            'client_number'      => 'required|string|max:100',
            'contract_number'    => 'required|string|max:100',
            'installment_date'   => 'required|date',
            'installment_premium'=> 'required|numeric|min:0.01',
            // V8 parity: the modal supplies the ContractSequence (prefilled
            // hidden field in graphiteBWV8's addNewRealpayInstallment). Optional
            // here — falls back to the contract-info lookup when absent.
            'contract_sequence'  => 'nullable',
        ]);

        $policy = DB::table('policies')->where('id', $policyId)
            ->first(['id', 'policyNumber', 'product_id']);
        if (!$policy) return response()->json(['message' => 'Policy not found.'], 404);

        try {
            $realpay = new \AlphaDirect\Http\Controllers\Admin\RealPayController();

            // Resolve the contract sequence from the RealPay contract-info API,
            // exactly the data source V8's modal prefill used.
            $infoReq = new Request([
                'policy_id'     => $policyId,
                'policy_number' => $policy->policyNumber,
                'clientNumber'  => $validated['client_number'],
            ]);

            $ins = [
                'clientNumber'     => $validated['client_number'],
                'contractNumber'   => $validated['contract_number'],
                'instalmentDate'   => $validated['installment_date'],
                'instalmentAmount' => $validated['installment_premium'],
                'contractSequence' => null,
                'policy_id'        => $policyId,
            ];

            // V8 parity (addNewRealpayInstallment): the ContractSequence comes
            // from the modal form (a prefilled hidden field). Use it directly
            // when supplied; the contract-info lookup is only a fallback for
            // clients that don't send it. The lookup is still called to decide
            // which store method (motor vs non-motor) to use, exactly as V8 did.
            // A null sequence would build a malformed RealPay body
            // ("ContractSequence": ,) → the gateway returns an empty body,
            // surfacing as the unhelpful "RealPay rejected the instalment: []".
            $formSequence = $request->input('contract_sequence');
            $hasFormSequence = $formSequence !== null && $formSequence !== '';
            $isMotor = (int) $policy->product_id === 3;

            if ($isMotor) {
                $getContracts = $realpay->getContractInfoForMotorComp($infoReq);
                $useMotorStore = isset($getContracts) && !($getContracts instanceof \Throwable) && is_array($getContracts);
                $ins['contractSequence'] = $hasFormSequence
                    ? $formSequence
                    : $this->pickContractSequence($getContracts, $validated['contract_number']);
            } else {
                $info = $realpay->getContractInfo($infoReq);
                $useMotorStore = false;
                $ins['contractSequence'] = $hasFormSequence
                    ? $formSequence
                    : $this->pickContractSequence($info, $validated['contract_number']);
            }
            
            if ($ins['contractSequence'] === null || $ins['contractSequence'] === '') {
                Log::warning('PolicyRealpay.addInstallment: could not resolve ContractSequence', [
                    'policy_id'       => $policyId,
                    'product_id'      => $policy->product_id,
                    'client_number'   => $validated['client_number'],
                    'contract_number' => $validated['contract_number'],
                    'form_sequence'   => $formSequence,
                    'contracts_raw'   => $isMotor ? ($getContracts ?? null) : ($info ?? null),
                ]);
                return response()->json([
                    'message' => 'Could not resolve the RealPay ContractSequence for contract ' . $validated['contract_number']
                        . '. Enter the Contract Sequence in the form, or "Sync from portal" on the Realpay Contract Lists tab, then retry.',
                ], 422);
            }

            if ($isMotor) {
                $saved = $useMotorStore
                    ? $realpay->storeNewRealpayInstallmentForMotor($ins)
                    : $realpay->storeNewRealpayInstallment($ins);
            } else {
                $saved = $realpay->storeNewInstallmentForInstantProduct($ins);
            }

            // The store methods return null ONLY when the RealPay token fetch
            // fails (clientAuthForMotorComp / clientAuth / clientAuthForInstantProduct)
            // or — for motor — the contract-info lookup returned no client.
            // Surface that instead of an empty "Failed..." message.
            if ($saved === null) {
                Log::error('PolicyRealpay.addInstallment: RealPay auth/contract lookup returned null', [
                    'policy_id'         => $policyId,
                    'product_id'        => $policy->product_id,
                    'contract_number'   => $validated['contract_number'],
                    'contract_sequence' => $ins['contractSequence'],
                ]);
                return response()->json([
                    'message' => 'Could not authenticate with RealPay or locate the contract. Verify the RealPay contract exists for this policy and that RealPay credentials/connectivity are configured, then retry.',
                ], 422);
            }

            $body = method_exists($saved, 'getData') ? $saved->getData() : null;
            if (($body->type ?? 'error') === 'success') {
                return response()->json(['message' => $body->message ?? 'RealPay installment added successfully.']);
            }
            $msg = $body->message ?? 'RealPay rejected the installment.';
            Log::warning('PolicyRealpay.addInstallment: store returned error', [
                'policy_id'         => $policyId,
                'contract_sequence' => $ins['contractSequence'],
                'detail'            => $msg,
            ]);
            return response()->json(['message' => 'Failed to add RealPay installment: ' . $msg], 422);
        } catch (\Throwable $e) {
            Log::error('PolicyRealpay.addInstallment failed', ['policy_id' => $policyId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not add the installment. Please try again.'], 500);
        }
    }

    /**
     * Extract the ContractSequence for a given contract number from a RealPay
     * ContractGetResponse array (returned by getContractInfo*). Falls back to
     * the first contract's sequence, then null.
     */
    private function pickContractSequence($contracts, string $contractNumber)
    {
        if (!is_array($contracts)) return null;

        // RealPay's ContractGetResponse comes back in several shapes: a flat
        // list of contracts, a single contract object, or wrapped under a
        // 'Contract'/'Contracts' key. Normalise to a list before scanning.
        $list = $contracts;
        if (isset($contracts['Contract']))  $list = $contracts['Contract'];
        elseif (isset($contracts['Contracts'])) $list = $contracts['Contracts'];
        if (isset($list['ContractNumber'])) $list = [$list]; // single object
        if (!is_array($list)) return null;

        foreach ($list as $c) {
            if (is_array($c) && isset($c['ContractNumber']) && (string) $c['ContractNumber'] === $contractNumber) {
                return $c['ContractSequence'] ?? null;
            }
        }
        $first = is_array($list) ? reset($list) : null;
        return is_array($first) ? ($first['ContractSequence'] ?? null) : null;
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/policies/{policyId}/realpay/installments/update
    //
    // "Update Installment" — graphiteBWV8 PolicyController::updateRealpayInstallment
    // parity. Does NOT hit RealPay synchronously; it queues the change by writing
    // a realpay_logs row (event=3, status=0). The ProcessRealpayPayment cron picks
    // it up (within the hour) and calls the appropriate update* method. Because the
    // cron switches single-vs-all on the PRESENCE of realpay_installment_number,
    // this endpoint includes it.
    // ──────────────────────────────────────────────────────────────
    public function updateInstallment(Request $request, int $policyId): JsonResponse
    {
        $validated = $request->validate([
            'client_number'       => 'required|string|max:100',
            'contract_number'     => 'required|string|max:100',
            'installment_number'  => 'required|string|max:100',
            'installment_date'    => 'nullable|date',
            'installment_premium' => 'nullable|numeric|min:0.01',
        ]);

        return $this->queueInstallmentUpdate($policyId, $validated, true);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/policies/{policyId}/realpay/installments/update-all
    //
    // "Update All Installment" — graphiteBWV8
    // PolicyController::updateRealpayAllInstallmentData parity. Same realpay_logs
    // (event=3) queue, but WITHOUT realpay_installment_number so the cron applies
    // the change across every installment on the contract.
    // ──────────────────────────────────────────────────────────────
    public function updateAllInstallments(Request $request, int $policyId): JsonResponse
    {
        $validated = $request->validate([
            'client_number'       => 'required|string|max:100',
            'contract_number'     => 'required|string|max:100',
            'installment_date'    => 'nullable|date',
            'installment_premium' => 'nullable|numeric|min:0.01',
        ]);

        return $this->queueInstallmentUpdate($policyId, $validated, false);
    }

    /**
     * Shared queue-write for the two update actions. Builds the input_data with
     * the exact realpay_* keys ProcessRealpayPayment reads, omitting
     * realpay_installment_number for the "all" variant (the cron keys single-vs-
     * all off its presence).
     */
    private function queueInstallmentUpdate(int $policyId, array $v, bool $single): JsonResponse
    {
        $policy = DB::table('policies')->where('id', $policyId)->first(['id', 'product_id']);
        if (!$policy) return response()->json(['message' => 'Policy not found.'], 404);

        $payload = [
            'realpay_client_number'   => $v['client_number'],
            'realpay_contract_number' => $v['contract_number'],
            'realpay_installment_date'    => $v['installment_date'] ?? null,
            'realpay_installment_premium' => $v['installment_premium'] ?? null,
            'policy_id'  => $policyId,
            'product_id' => $policy->product_id,
        ];
        if ($single) {
            $payload['realpay_installment_number'] = $v['installment_number'];
        }

        try {
            $log = new \AlphaDirect\RealpayLogs();
            $log->policy_id  = $policyId;
            $log->event      = 3;
            $log->status     = 0;
            $log->input_data = json_encode($payload);
            $log->save();

            return response()->json([
                'message' => 'Update queued. Installments will be updated within the hour.',
            ]);
        } catch (\Throwable $e) {
            Log::error('PolicyRealpay.queueInstallmentUpdate failed', ['policy_id' => $policyId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not queue the update. Please try again.'], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/policies/{policyId}/payment-conversions
    //
    // GRA-0182 parity: the graphiteBWV8 policy edit page had a "Payment
    // Update Contract" tab (admin/policy/edit.blade.php →
    // admin.policy.PaymentUpdateContractData) that listed every time a
    // policy was switched between payment methods (e.g. DPO → RealPay):
    // who did it (agent), the old + new payment method, whether the old
    // contract was cancelled, and when.
    //
    // Source table `update_contract` is still written on every conversion
    // by the V2 payment controllers (PayM8 / N-Genius / VCS), so the data
    // exists — V2 just never exposed a read endpoint or a tab for it. This
    // restores the read path. Keyed by policyNumber, exactly as V8.
    // ──────────────────────────────────────────────────────────────
    public function paymentConversions(int $policyId): JsonResponse
    {
        $policy = DB::table('policies')->where('id', $policyId)->first(['id', 'policyNumber']);
        if (!$policy) return response()->json(['message' => 'Policy not found.'], 404);

        $rows = DB::table('update_contract')
            ->where('policyNumber', $policy->policyNumber)
            ->orderByDesc('id')
            ->get(['id', 'policyNumber', 'old_payment_method', 'new_payment_method', 'old_contract_cancel', 'agent', 'status', 'created_at']);

        // Resolve agent id → "First Last" in one query (V8 resolved per-row).
        $agentIds = $rows->pluck('agent')->filter()->unique()->values()->all();
        $agents = [];
        if (!empty($agentIds)) {
            $agents = DB::table('users')
                ->whereIn('id', $agentIds)
                ->get(['id', 'firstName', 'lastName'])
                ->keyBy('id');
        }

        $data = $rows->map(function ($r) use ($agents) {
            $agentName = null;
            if ($r->agent && isset($agents[$r->agent])) {
                $a = $agents[$r->agent];
                $agentName = trim(ucwords((string) $a->firstName) . ' ' . ucwords((string) $a->lastName)) ?: null;
            }
            return [
                'id'                => $r->id,
                'policyNumber'      => $r->policyNumber,
                'agentName'         => $agentName,
                'oldPaymentMethod'  => $r->old_payment_method,
                'newPaymentMethod'  => $r->new_payment_method,
                'oldContractCancel' => $r->old_contract_cancel,
                'status'            => $r->status,
                'createdAt'         => $r->created_at,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }
}
