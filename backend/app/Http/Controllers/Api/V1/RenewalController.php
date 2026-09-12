<?php
namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\PolicyRenewal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RenewalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'is_renewed' => 'nullable|integer|in:0,1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = PolicyRenewal::with(['policy:id,policyNumber,customer_id,premium,status', 'policy.customer:id,firstName,lastName,cellphone,email'])
            ->when(isset($validated['is_renewed']), fn($q) => $q->where('is_renewed', $validated['is_renewed']))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $search = trim(preg_replace('/\s+/', ' ', $search));
                $like = "%{$search}%";
                $words = count(explode(' ', $search)) >= 2
                    ? array_values(array_filter(explode(' ', $search)))
                    : [];
                $q->where('policyNumber', 'like', $like)
                  ->orWhereHas('policy.customer', function ($c) use ($like, $words) {
                      $c->where('firstName', 'like', $like)
                        ->orWhere('lastName', 'like', $like)
                        ->orWhereRaw("CONCAT_WS(' ', TRIM(firstName), TRIM(lastName)) LIKE ?", [$like])
                        ->orWhere('cellphone', 'like', $like);
                      if (count($words) >= 2) {
                          $c->orWhere(fn($i) =>
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

        return response()->json([
            'data' => $results->map(function ($r) {
                $customer = $r->policy->customer ?? null;
                return [
                    'id' => $r->id,
                    'policyNumber' => $r->policyNumber,
                    'policyId' => $r->policy_id,
                    'customerName' => $customer ? trim($customer->firstName . ' ' . $customer->lastName) : null,
                    'cellphone' => $customer->cellphone ?? null,
                    'email' => $customer->email ?? null,
                    'oldPremium' => $r->old_premium,
                    'newPremium' => $r->new_premium,
                    'expiryDate' => $r->expiry_date,
                    'paymentMethod' => $r->paymentMethod,
                    'paymentFrequency' => $r->paymentFrequency,
                    'rerated' => (bool) $r->rerated,
                    'isRenewed' => (bool) $r->is_renewed,
                    'createdAt' => optional($r->created_at)->toIso8601String(),
                ];
            }),
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

    // ─── MIS (product_id 3) renew flow ───────────────────────────────────────
    //
    // These endpoints expose the legacy Blade "Policy Renewal" page
    // (resources/views/admin/policy/renewPolicy.blade.php) as JSON so the React
    // frontend can drive Motor Comprehensive (product_id 3) renewals end to end.
    // Each delegates to the existing, V8-parity Admin controller method noted in
    // its docblock so the heavy business logic (rating API, RealPay contracts,
    // ledger/term generation) is reused unchanged rather than re-implemented.

    /**
     * GET /renewals/{policyId}/mis-renew-flow
     *
     * Renew-flow page data: vehicle details + premium breakdown. Mirrors
     * Admin\PolicyController::renewPolicy($id) (PolicyController.php:10830) and
     * getCalculatedPreminumForRenew (PolicyController.php:893), returning JSON
     * instead of rendering renewPolicy.blade.php.
     */
    public function misRenewFlow(int $policyId): JsonResponse
    {
        $policy = \AlphaDirect\Policy::find($policyId);
        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }
        if ((int) $policy->product_id !== 3) {
            return response()->json(['error' => 'The MIS renew flow applies to Motor Comprehensive (product_id 3) policies only.'], 422);
        }

        // V8 parity: renewPolicy() only renders once the policy has been "moved
        // to renew" — i.e. a policy_renewals row with is_renewed = 0 exists.
        $hasRenewalRow = PolicyRenewal::where('policyNumber', $policy->policyNumber)
            ->where('is_renewed', 0)->exists();
        if (!$hasRenewalRow) {
            return response()->json([
                'canRenew'       => false,
                'canMoveToRenew' => true,
                'needsRerate'    => false,
                'message'        => "This policy can't renew. First move policy to renew then try again.",
            ]);
        }

        // Faithful replica of renewPolicy()'s join (latest renewal row first).
        $data = DB::table('policies')
            ->leftJoin('customer', 'customer.id', '=', 'policies.customer_id')
            ->leftJoin('motor_comp_quotes', 'motor_comp_quotes.quoteNumber', '=', 'policies.quoteNumber')
            ->leftJoin('customer_profile', 'customer_profile.customer_id', '=', 'customer.id')
            ->leftJoin('vehicle', 'vehicle.policy_id', '=', 'policies.id')
            ->leftJoin('policy_renewals', 'policy_renewals.policyNumber', '=', 'policies.policyNumber')
            ->where('policies.id', $policyId)
            ->orderBy('policy_renewals.id', 'desc')
            ->first();

        if (!$data) {
            return response()->json(['error' => 'Renewal data not found for this policy.'], 404);
        }

        // priorAccidents = claims on this policy + the quote's stored count (V8).
        $claimCount = DB::table('claims')->where('policy_id', $policyId)->count();
        $priorAccidents = $claimCount + (int) ($data->priorAccidents ?? 0);

        $discount = DB::table('policy_discount_surcharge')
            ->where('policy_id', $policyId)->orderByDesc('id')->first(['new_value']);

        $responseData = null;
        if (!empty($data->response_data)) {
            $decoded = @unserialize($data->response_data, ['allowed_classes' => false]);
            if (is_array($decoded)) {
                $responseData = $decoded;
            }
        }
        $isRerated = (int) ($data->rerate_update_renewal_rates ?? 0) === 1;

        $legacy = app(\AlphaDirect\Http\Controllers\Admin\PolicyController::class);

        $needsRerate = false;
        $newMonthlyPremium = null;
        $newThreeInstlPremium = null;
        $newSumAssured = null;

        if ($discount && $isRerated) {
            $newMonthlyPremium    = round($legacy->getMonthlyPrem(3, $discount->new_value), 2);
            $newThreeInstlPremium = round($discount->new_value / 3, 2);
        } elseif (is_array($responseData)) {
            $newMonthlyPremium    = $responseData['month_ins'] ?? null;
            $newThreeInstlPremium = $responseData['three_ins'] ?? null;
        } else {
            $needsRerate = true;
        }

        // V8: New Sum Insured always comes from the serialized rerate response;
        // without it the legacy flow forces a rerate before allowing renewal.
        if (is_array($responseData)) {
            $newSumAssured = $responseData['sum_assured'] ?? null;
        } else {
            $needsRerate = true;
        }

        // New Premium display value (renewPolicy.blade.php "New Premium" row).
        if ($discount && $isRerated) {
            $newPremium = $discount->new_value;
        } elseif (isset($data->new_premium)) {
            $newPremium = $data->new_premium;
        } else {
            $newPremium = $policy->premium;
        }

        $freqLabels = [1 => 'Monthly', 2 => 'Three Installments', 3 => 'Annual'];

        $agents = DB::table('users')->where('active', 1)->orderBy('firstName')
            ->get(['id', 'firstName', 'lastName'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => trim($u->firstName . ' ' . $u->lastName)]);

        return response()->json([
            'canRenew'       => !$needsRerate,
            'canMoveToRenew' => false,
            'needsRerate'    => $needsRerate,
            'message'        => $needsRerate ? 'Rerate policy first then try to renew.' : null,
            'policy' => [
                'id'           => $policy->id,
                'policyNumber' => $policy->policyNumber,
                'productId'    => (int) $policy->product_id,
            ],
            'vehicle' => [
                'japaneseImport'    => ((int) ($data->is_imported ?? 0) === 1) ? 'Yes' : 'No',
                'make'              => $data->make ?? null,
                'manufacturingYear' => $data->manufacturingYear ?? null,
                'model'             => $data->model ?? null,
                'estimatedValue'    => $data->estimatedValue ?? null,
                'priorAccidents'    => $priorAccidents,
                'condition'         => 'Excellent',
                'mileage'           => 'Low',
                'purpose'           => 'Personal',
            ],
            'premium' => [
                'oldPremium'                 => $data->old_premium ?? null,
                'oldFrequency'               => (int) $policy->premium_freq,
                'oldFrequencyLabel'          => $freqLabels[(int) $policy->premium_freq] ?? 'N/A',
                'oldSumInsured'              => $data->estimatedValue ?? null,
                'newPremium'                 => $newPremium,
                'newMonthlyPremium'          => $newMonthlyPremium,
                'newThreeInstallmentPremium' => $newThreeInstlPremium,
                'newSumInsured'              => $newSumAssured,
            ],
            'agents' => $agents,
        ]);
    }

    /**
     * POST /renewals/{policyId}/move-to-renew
     *
     * "Move to Renew Table". Mirrors Admin\PolicyController::policyRenewalOperations
     * (PolicyController.php:10895): creates the policy_renewals row and, for MIS,
     * runs the rating API to seed the new premium.
     */
    public function moveToRenew(Request $request, int $policyId): JsonResponse
    {
        $policy = \AlphaDirect\Policy::find($policyId);
        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        $request->merge(['policyId' => $policyId, 'productId' => $policy->product_id]);
        $legacy = app(\AlphaDirect\Http\Controllers\Admin\PolicyController::class);

        return $this->legacyResultToJson(
            fn () => $legacy->policyRenewalOperations($request),
            'Policy has been moved to renew table successfully.'
        );
    }

    /**
     * POST /renewals/{policyId}/rerate
     *
     * "Rerate Premium" / "Add Discount/Surcharge". Mirrors
     * Admin\DiscountSurchargeController::policyRenewalDiscountSurcharge
     * (DiscountSurchargeController.php:165). Body: { type, value_type, value }.
     */
    public function rerate(Request $request, int $policyId): JsonResponse
    {
        $policy = \AlphaDirect\Policy::find($policyId);
        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        $request->merge(['policyId' => $policyId]);
        $legacy = app(\AlphaDirect\Http\Controllers\Admin\DiscountSurchargeController::class);

        return $this->legacyResultToJson(
            fn () => $legacy->policyRenewalDiscountSurcharge($request),
            'Premium rerated successfully.'
        );
    }

    /**
     * POST /renewals/{policyId}/generate-link
     *
     * "Generate Renew Link". Mirrors
     * Admin\PolicyController::generateAndSendRenewalLink (PolicyController.php:20249).
     */
    public function generateLink(int $policyId): JsonResponse
    {
        $policy = \AlphaDirect\Policy::find($policyId);
        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        $legacy = app(\AlphaDirect\Http\Controllers\Admin\PolicyController::class);

        return $this->legacyResultToJson(
            fn () => $legacy->generateAndSendRenewalLink($policy->policyNumber),
            'Renew link generated successfully.'
        );
    }

    /**
     * POST /renewals/{policyId}/pay-cash
     *
     * "Pay with Cash". Routes through Admin\PolicyController::addOfflinePaymentPolicyRenewal
     * (PolicyController.php:18499) — the V8 orchestrator that BOTH records the
     * cash payment (storeCashPaymentForRenewal) AND completes the renewal
     * (PolicyRenewal: new term, dates, is_renewed=1). This is exactly what the
     * Blade "Add Offline Payment" form posts (paymentMethod=Cash). Body:
     * paymentAmount, paymentDate, paymentFreq, receiptNumber, paymentRecievedBy,
     * numberOfInstalmentsPaid, paymentNote, payment_image, new_premium,
     * term_start_date, agent_id + optional RealPay banking fields when addRealpay=1.
     */
    public function payCash(Request $request, int $policyId): JsonResponse
    {
        $policy = \AlphaDirect\Policy::find($policyId);
        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        $request->merge([
            'policyNumber'  => $policy->policyNumber,
            'policy_id'     => $policy->id,
            'policyID'      => $policy->id,
            'paymentMethod' => 'Cash',
        ]);
        $legacy = app(\AlphaDirect\Http\Controllers\Admin\PolicyController::class);

        return $this->legacyResultToJson(
            fn () => $legacy->addOfflinePaymentPolicyRenewal($request),
            'Policy renewed successfully.'
        );
    }

    /**
     * POST /renewals/{policyId}/pay-realpay
     *
     * "Pay with Realpay". Routes through addOfflinePaymentPolicyRenewal
     * (paymentMethod=Realpay) so the RealPay debit-order contract is created
     * (storeRealPayPaymentForRenewal) AND the renewal is completed. Body:
     * BankName, BranchCode, accountType, accountNumber, first_premium,
     * first_collection_date, billingDay/frequency, new_premium, term_start_date,
     * agent_id.
     */
    public function payRealpay(Request $request, int $policyId): JsonResponse
    {
        $policy = \AlphaDirect\Policy::find($policyId);
        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        $request->merge([
            'policyNumber'  => $policy->policyNumber,
            'policy_id'     => $policy->id,
            'policyID'      => $policy->id,
            'paymentMethod' => 'Realpay',
        ]);
        $legacy = app(\AlphaDirect\Http\Controllers\Admin\PolicyController::class);

        return $this->legacyResultToJson(
            fn () => $legacy->addOfflinePaymentPolicyRenewal($request),
            'Policy renewed successfully.'
        );
    }

    /**
     * POST /renewals/{policyId}/renew-no-payment
     *
     * "Renew Without Payment". Renews the policy term using the supplied
     * (editable) premium and frequency WITHOUT recording any payment. Unlike
     * payCash/payRealpay it deliberately bypasses storeCashPaymentForRenewal —
     * so no PaymentTransaction is created. The policy is still activated (as the
     * cash flow does); only the money movement is skipped.
     *
     * The renewal engine (PolicyController::PolicyRenewal) is invoked directly.
     * paymentMethod is set to 'Cash' purely so the term's billing date and
     * frequency are computed the same way the cash flow would — there is still
     * no money movement. The policy is activated the same way the cash flow
     * does (updatePolicyDates logs the activation date); only the payment
     * transaction is skipped.
     *
     * Body: new_premium (required), paymentFreq (1|2|3, required),
     *       term_start_date (required), agent_id (optional).
     */
    public function payNone(Request $request, int $policyId): JsonResponse
    {
        $policy = \AlphaDirect\Policy::find($policyId);
        if (!$policy) {
            return response()->json(['error' => 'Policy not found.'], 404);
        }

        $validated = $request->validate([
            'new_premium'     => 'required|numeric|min:0',
            'paymentFreq'     => 'required|in:1,2,3',
            'term_start_date' => 'required|date',
            'agent_id'        => 'nullable',
        ]);

        $premium = $validated['new_premium'];

        // Populate exactly the request fields PolicyRenewal reads. The premium
        // doubles as term_permium / first_premium / calculated_premium because
        // there is no installment split to derive when no payment is taken.
        $request->merge([
            'policyNumber'       => $policy->policyNumber,
            'policy_id'          => $policy->id,
            'policyID'           => $policy->id,
            'paymentMethod'      => 'Cash',          // drives billing/frequency calc only
            'stored_payment_method' => 'No Payment', // label recorded on the new term
            'term_permium'       => $premium,
            'first_premium'      => $premium,
            'calculated_premium' => $premium,
            'paymentDate'        => $validated['term_start_date'],
        ]);

        $legacy = app(\AlphaDirect\Http\Controllers\Admin\PolicyController::class);

        // Activate the policy the same way the cash flow does (storeCashPaymentForRenewal
        // calls this when the policy isn't already active). Records the activation
        // date via PolicyStatusLogs; still no payment transaction.
        if ((int) $policy->status !== 1) {
            $legacy->updatePolicyDates($policy->policyNumber, 1);
        }

        try {
            $result = $legacy->PolicyRenewal($request);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }

        $payload = $result instanceof JsonResponse ? $result->getData(true) : [];
        $ok = (string) ($payload['status'] ?? '') === '200';

        if (!$ok) {
            return response()->json([
                'status'  => 'error',
                'message' => $payload['message'] ?? 'Failed to renew policy.',
            ], 422);
        }

        // PolicyRenewal marks a future-dated term In-Active. For the no-payment
        // flow there's no later payment event to flip it, so when the renewed
        // term's coverage has already started (start date today or earlier) we
        // activate it here — otherwise an in-force term would read In-Active.
        $startDate = $validated['term_start_date'];
        if (\Carbon\Carbon::parse($startDate)->startOfDay()->lte(\Carbon\Carbon::now()->startOfDay())) {
            $newTerm = DB::table('policy_term')
                ->where('policy_id', $policy->id)
                ->where('trans_type', 'RENEW')
                ->whereDate('term_start_date', $startDate)
                ->orderByDesc('id')
                ->first();
            if ($newTerm) {
                DB::table('policy_term')->where('id', $newTerm->id)
                    ->update(['status' => 'Active', 'updated_at' => now()]);

                // Term is now live, so mark the renewal completed — same as the
                // policyRenewUpdateData cron does once a renewed term becomes
                // current (PolicyRenewUpdateData.php:118-122). Keeps the cron
                // from reprocessing this already-activated renewal.
                $renewalRow = PolicyRenewal::where('policy_id', $policy->id)
                    ->where('is_renewed', 1)
                    ->where('renew_completed', 0)
                    ->orderByDesc('id')
                    ->first();
                if ($renewalRow) {
                    $renewalRow->renew_completed = 1;
                    $renewalRow->save();
                }
            }
        }

        // Mirror the cash path's post-renewal lifecycle event so renewal
        // dashboards / downstream listeners update the same way.
        event(new \AlphaDirect\Events\policyLifecycle($policy->id, 'Renewed'));

        return response()->json([
            'status'        => 'success',
            'message'       => 'Policy renewed successfully (no payment recorded).',
            'policy_number' => $payload['policy_number'] ?? $policy->policyNumber,
        ]);
    }

    /**
     * Run a legacy Admin-controller method and normalise its result to JSON.
     *
     * The legacy renewal methods either already return a JsonResponse (the
     * payment workers, using the {status:'200'|'401'} convention) or a
     * RedirectResponse carrying a flashed success/error message. API routes are
     * stateless, so before invoking we bind an in-memory session — otherwise the
     * legacy `redirect()->back()->with(...)` would fatal on a null session.
     */
    private function legacyResultToJson(callable $call, string $okMessage): JsonResponse
    {
        try {
            app('redirect')->setSession(app('session.store'));
        } catch (\Throwable $e) {
            // best effort — if a real session is already bound this is a no-op
        }

        try {
            $response = $call();
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }

        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
            $legacyStatus = (string) ($payload['status'] ?? '200');
            $ok = in_array($legacyStatus, ['200', '', 'success', 'true'], true);
            return response()->json($payload, $ok ? 200 : 422);
        }

        if ($response instanceof \Illuminate\Http\RedirectResponse) {
            $session = $response->getSession();
            $error   = $session ? $session->get('error') : null;
            $success = $session ? $session->get('success') : null;
            if (!empty($error)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => is_array($error) ? implode(' ', $error) : $error,
                ], 422);
            }
            return response()->json([
                'status'  => 'success',
                'message' => $success ?: $okMessage,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => $okMessage]);
    }
}
