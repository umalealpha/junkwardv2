<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Claim;
use AlphaDirect\Customer;
use AlphaDirect\CustomerProfile;
use AlphaDirect\DiscountSurcharge;
use AlphaDirect\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\QuoteController;
use AlphaDirect\Models\PolicyDiscountSurcharge;
use AlphaDirect\Models\PolicyRenewal;
use AlphaDirect\MotorComprehensiveQuotes;
use AlphaDirect\Policy;
use AlphaDirect\PolicyPremiumReratingLog;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Product;
use AlphaDirect\QuoteSettings;
use AlphaDirect\ReratedPremiumQuote;
use AlphaDirect\Stores;
use AlphaDirect\User;
use AlphaDirect\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rerate Premium — React/API port of graphiteBWV8's
 * Admin\PolicyController rerate flow (reratePolicyPremium, acceptNewRate,
 * policyDiscountSurchargeAPI, customPolicyDiscountSurchargeAPI,
 * premiumUpdateHistory), scoped to MIS Motor Comprehensive (product_id = 3).
 *
 * Two-step (V8 parity): recalculate() logs a PENDING rerate and previews the
 * new rates; accept() commits them and flips the log to ACCEPTED. Payment
 * capture on accept is intentionally delegated to the existing V2 endpoints
 * (POST /policies/{id}/realpay/contracts, POST /payments/{id}/offline) which
 * the React tab calls after a successful accept — we do not re-port V8's
 * inline payment writes here.
 *
 * Column names mirror the live shared DB exactly (the same tables V8 writes):
 *   policy_premium_rerating_log, motor_comp_quotes, rerated_premium_quotes,
 *   policy_discount_surcharge.
 */
class PolicyReratePremiumController extends Controller
{
    /** Rating engine endpoint — same origin every other V2 caller pins. */
    private const RATING_URL = 'https://rate.alphadirect.co.bw/api/calculation';

    /** Only MIS Motor Comprehensive is in scope for this controller. */
    private const RERATE_PRODUCT_ID = 3;

    /** V8 marital code → rating-engine label (PolicyController::reratePolicyPremium). */
    private const MARITAL_LABELS = [
        1 => 'Never Married', 2 => 'Married Before', 3 => 'Married Before',
        4 => 'Married Before', 5 => 'Never Married', 6 => 'Married Before',
    ];

    // ─── 1. loadRerateData ───────────────────────────────────────────────────

    /**
     * GET /policies/{id}/rerate-premium
     * Mirrors the ReratePremium view component: customer + product + vehicle +
     * current premium calc details + make/model option lists + edit allowance.
     */
    public function loadRerateData(int $policyId): JsonResponse
    {
        $policy = Policy::with(['customer', 'product'])->find($policyId);
        if (!$policy) {
            return response()->json(['message' => 'Policy not found.'], 404);
        }
        if ((int) $policy->product_id !== self::RERATE_PRODUCT_ID) {
            return response()->json([
                'message' => 'Rerate Premium is only available for Motor Comprehensive policies.',
            ], 422);
        }

        $customer = Customer::find($policy->customer_id);
        $profile  = CustomerProfile::where('customer_id', $policy->customer_id)->first();
        $vehicle  = Vehicle::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
        $product  = Product::find($policy->product_id);
        $store    = $policy->store_id ? Stores::find($policy->store_id) : null;

        // Current premium calc details: prefer the motor_comp_quotes row, fall
        // back to the policy/vehicle (V8 ReratePremium component, lines 44-96).
        $mcq = $policy->quoteNumber
            ? MotorComprehensiveQuotes::QuoteNo($policy->quoteNumber)->orderBy('id', 'desc')->first()
            : null;

        $isImported = $mcq->is_imported
            ?? (($vehicle && $vehicle->is_imported !== null)
                ? ($vehicle->is_imported == 0 ? 'No' : 'Yes')
                : null);
        $make  = $mcq->make ?? ($vehicle->make ?? null);
        $model = $mcq->model ?? ($vehicle->model ?? null);
        $year  = $mcq->manufacturingYear ?? ($vehicle->year ?? null);

        $reratedQuote = ($mcq && $mcq->ratings_id)
            ? ReratedPremiumQuote::RateId($mcq->ratings_id)->orderBy('id', 'desc')->first()
            : null;

        // Vehicle make/model option lists — reuse the legacy QuoteController
        // helpers the V8 component used (proven against the rating catalogue).
        [$makes, $models] = $this->vehicleOptions($isImported, $make, $year);

        // Edit allowance: number of rerate logs vs QuoteSettings limit.
        $countLog = PolicyPremiumReratingLog::PolicyNumber($policy->policyNumber)->count();
        $setting  = QuoteSettings::orderBy('id', 'desc')->first(['policy_premium_edit_limit']);
        $canEdit  = $setting ? ($countLog < (int) $setting->policy_premium_edit_limit) : true;

        $isRenewal = PolicyRenewal::where('policyNumber', $policy->policyNumber)
            ->orderBy('id', 'desc')->value('is_renewed');

        return response()->json([
            'data' => [
                'policyId'     => $policy->id,
                'policyNumber' => $policy->policyNumber,
                'status'       => (int) $policy->status,
                'canEdit'      => $canEdit,
                'isRenewal'    => (int) ($isRenewal ?? 0),
                'customer' => [
                    'id'            => $customer->id ?? null,
                    'name'          => trim(($customer->firstName ?? '') . ' ' . ($customer->lastName ?? '')),
                    'omang'         => $profile->omang ?? null,
                    'passport'      => $profile->passport ?? null,
                    'email'         => $customer->email ?? null,
                    'cellphone'     => $customer->cellphone ?? null,
                    'gender'        => $profile->gender ?? null,
                    'dob'           => $profile->dob ?? null,
                    'maritalstatus' => $profile->maritalstatus ?? null,
                    'storeName'     => $store->name ?? null,
                ],
                'product' => [
                    'name'       => $product->name ?? null,
                    'plan'       => $product->name ?? null,
                    'sumInsured' => $policy->sum_assured,
                ],
                'vehicle' => [
                    'isImported'     => $isImported,
                    'make'           => $make,
                    'model'          => $model,
                    'year'           => $year,
                    'estimatedValue' => $mcq->estimatedValue ?? $policy->sum_assured,
                    'priorAccidents' => Claim::where('policy_id', $policy->id)->count(),
                ],
                'premium' => [
                    'ratingsId'             => $mcq->ratings_id ?? null,
                    'monthly'               => $mcq->premiumMonthly ?? $policy->premium,
                    'threeInstalment'       => $mcq->premium3Inst ?? $policy->premium,
                    'annual'                => $mcq->premiumAnnually ?? $policy->premium,
                    'discountSurcharge'     => $mcq->discount_surcharge ?? null,
                    'premiumRate'           => $mcq->premium_rate ?? null,
                    'reason'                => $reratedQuote->reason ?? null,
                ],
                'options' => [
                    'makes'  => $makes,
                    'models' => $models,
                    'years'  => range((int) date('Y'), (int) date('Y') - 15),
                ],
            ],
        ]);
    }

    // ─── 2. recalculate ──────────────────────────────────────────────────────

    /**
     * POST /policies/{id}/rerate-premium/recalculate
     * Calls the rating engine and records a PENDING rerate log + updates the
     * motor_comp_quotes / customer_profile / rerated_premium_quotes rows.
     * Faithful to PolicyController::reratePolicyPremium (lines 9341-9544).
     */
    public function recalculate(Request $request, int $policyId): JsonResponse
    {
        $policy = Policy::find($policyId);
        if (!$policy) {
            return response()->json(['success' => false, 'message' => 'Policy not found.'], 404);
        }
        if ((int) $policy->product_id !== self::RERATE_PRODUCT_ID) {
            return response()->json(['success' => false, 'message' => 'Rerate is only available for Motor Comprehensive.'], 422);
        }

        $validated = $request->validate([
            'make'            => 'required|string|max:100',
            'model'           => 'required|string|max:150',
            'year'            => ['required', 'digits:4', 'integer',
                'min:' . (int) (date('Y') - 15), 'max:' . (int) (date('Y') + 1)],
            'dob'             => ['required', 'date_format:Y-m-d',
                'before:' . now()->subYears(18)->toDateString(),
                'after_or_equal:' . now()->subYears(75)->toDateString()],
            'estimatedValue'  => 'required|numeric|min:20000|max:500000',
            'is_imported'     => 'required|string|in:Yes,No',
            'marital'         => 'required|integer|between:1,6',
            'prior_accidents' => 'required|integer|min:0|max:3',
            'gender'          => 'required|integer|in:0,1',
        ]);

        $maritalLabel = self::MARITAL_LABELS[$validated['marital']];
        $genderLabel  = $validated['gender'] == 1 ? 'Male' : 'Female';
        $dobDmy       = date('d/m/Y', strtotime($validated['dob']));

        // Existing-policy rating inputs (claim/loss history) — V8 parity.
        $adminCustomer = new AdminCustomerController();
        $stats         = $adminCustomer->rateLossStats();
        $claimPayment  = $adminCustomer->getPolicyClaimPayments($policy->id);

        $engine = $this->callRatingEngine([
            'make'               => $validated['make'],
            'model'              => $validated['model'],
            'manufacturing_year' => (string) $validated['year'],
            'dob'                => $dobDmy,
            'sum_insured'        => (string) $validated['estimatedValue'],
            'status'             => $validated['is_imported'],
            'policy_number'      => $policy->policyNumber,
            'claimPayment'       => (string) $claimPayment,
            'P1'                 => (string) ($stats['P1'] ?? 0),
            'C1'                 => (string) ($stats['C1'] ?? 0),
            'marital_status'     => $maritalLabel,
            'claim_count'        => (string) $validated['prior_accidents'],
            'gender'             => $genderLabel,
        ]);

        if ($engine['error']) {
            return response()->json(['success' => false, 'message' => $engine['message']], $engine['code']);
        }
        $data = $engine['data'];

        $annual  = $data['result'];
        $monthly = $data['monthly_premium_vat'];
        $three   = $data['threemonthly_preminum_vat'];
        $rateId  = $data['rate_id'];

        try {
            DB::beginTransaction();

            PolicyPremiumReratingLog::addReratingLog([
                'ratings_id'              => $rateId,
                'policy_number'           => $policy->policyNumber,
                'month_ins'               => $monthly,
                'three_ins'               => $three,
                'annual_ins'              => $annual,
                'customer_marital_status' => $validated['marital'],
                'customer_dob'            => Carbon::createFromFormat('d/m/Y', $dobDmy)->format('Y-m-d'),
                'customer_gender'         => $validated['gender'],
                'japnese_import'          => $validated['is_imported'],
                'make'                    => $validated['make'],
                'model'                   => $validated['model'],
                'sum_assured'             => $validated['estimatedValue'],
                'claim_count'             => $validated['prior_accidents'],
                'manufacturing_year'      => $validated['year'],
                'rerated_by'              => Auth::id(),
                'status'                  => 'Pending',
            ]);

            $mcq = $policy->quoteNumber
                ? MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)->orderBy('id', 'desc')->first()
                : null;

            if ($mcq) {
                $mcq->ratings_id                 = $rateId;
                $mcq->premiumMonthly             = $monthly;
                $mcq->premium3Inst               = $three;
                $mcq->premiumAnnually            = $annual;
                $mcq->premium_rate               = $validated['estimatedValue'] > 0
                    ? ($annual / $validated['estimatedValue']) * 100 : null;
                $mcq->make                       = $validated['make'];
                $mcq->model                      = $validated['model'];
                $mcq->manufacturingYear          = $validated['year'];
                $mcq->estimatedValue             = $validated['estimatedValue'];
                $mcq->priorAccidents             = $validated['prior_accidents'];
                $mcq->is_imported                = $validated['is_imported'];
                $mcq->discount_surcharge         = null;
                $mcq->percent_discount_surcharge = null;
                $mcq->save();
            }

            $profile = CustomerProfile::where('customer_id', $policy->customer_id)->first();
            if ($profile) {
                $profile->dob           = $validated['dob'];
                $profile->maritalstatus = $validated['marital'];
                $profile->gender        = $validated['gender'];
                $profile->save();
            }

            $rerated               = new ReratedPremiumQuote();
            $rerated->quote_number = $policy->quoteNumber;
            $rerated->rate_id      = $rateId;
            $rerated->old_value    = $policy->premium;
            $rerated->new_value    = $annual;
            $rerated->old_premium  = $policy->premium;
            $rerated->old_frequency = $policy->premium_freq;
            $rerated->old_first_premium = $policy->first_premium;
            $rerated->old_sum_insured   = $policy->sum_assured;
            $rerated->reason       = 'Premium Rerating';
            $rerated->added_by     = Auth::id();
            $rerated->ip           = $request->ip();
            $rerated->save();

            $renewal = PolicyRenewal::where('policyNumber', $policy->policyNumber)
                ->orderBy('id', 'desc')->first();
            if ($renewal) {
                $renewal->new_premium = $annual;
                $renewal->is_rated    = 1;
                $renewal->sum_assured = $validated['estimatedValue'];
                $renewal->save();
            }

            // Fresh rerate clears any pending discount/surcharge (V8 line 9534).
            PolicyDiscountSurcharge::where('policy_id', $policy->id)->delete();

            DB::commit();
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error('Rerate recalculate persist failed: ' . $ex->getMessage(), ['line' => $ex->getLine()]);
            return response()->json(['success' => false, 'message' => 'Failed to record rerate.'], 500);
        }

        return response()->json([
            'success' => true,
            'rerateStatus' => 'Pending',
            'data' => [
                'rateId'          => $rateId,
                'monthly'         => $monthly,
                'threeInstalment' => $three,
                'annual'          => $annual,
                'premiumRate'     => $validated['estimatedValue'] > 0
                    ? round(($annual / $validated['estimatedValue']) * 100, 2) : null,
            ],
        ]);
    }

    // ─── 3. applyDiscountSurcharge ────────────────────────────────────────────

    /**
     * POST /policies/{id}/rerate-premium/discount-surcharge
     * Faithful to PolicyController::policyDiscountSurchargeAPI (lines 9605-9774).
     */
    public function applyDiscountSurcharge(Request $request, int $policyId): JsonResponse
    {
        $validated = $request->validate([
            'type'                  => 'required|string|in:discount,surcharge',
            'value_type'            => 'required|integer|in:1,2',
            'value'                 => 'required|numeric|gt:0',
            'reason'                => 'required|string|max:500',
            'annual_premium_rerate' => 'required|numeric|gt:0',
        ]);

        $policy = Policy::find($policyId);
        if (!$policy) {
            return response()->json(['success' => 0, 'message' => 'Policy not found.'], 404);
        }

        try {
            $setting = QuoteSettings::first(['edit_limit']);
            $edited  = PolicyDiscountSurcharge::where('policy_id', $policy->id)
                ->where('discount', '!=', 'null')->where('surcharge', '!=', 'null')->count();
            if (!((int) $setting->edit_limit >= $edited + 1)) {
                return response()->json(['success' => 0, 'message' => 'You have exceeded maximum number of updates allowed'], 401);
            }

            $totalDisc = abs((float) PolicyDiscountSurcharge::where('policy_id', $policy->id)
                ->where('discount', '!=', null)->sum('discount'));
            $totalSurc = (float) PolicyDiscountSurcharge::where('policy_id', $policy->id)
                ->where('surcharge', '!=', null)->sum('surcharge');

            $user     = Auth::user();
            $userRole = $user->roles[0]->id ?? null;
            $limits   = DiscountSurcharge::select('id', 'discount', 'surcharge')->where('role', $userRole)->first();
            if (!$limits) {
                return response()->json(['success' => 0, 'message' => 'Values not found for role : ' . ($user->roles[0]->name ?? 'unknown')], 401);
            }

            $old = PolicyDiscountSurcharge::where('policy_id', $policy->id)->orderBy('id', 'desc')->first(['new_value']);
            $annualPremium = $validated['annual_premium_rerate'];
            $oldValue = $old ? $old->new_value : $annualPremium;
            if ($old) {
                $annualPremium = $old->new_value;
            }

            $type      = $validated['type'];
            $value     = (float) $validated['value'];
            $permitted = (float) $limits->$type; // max percent for this role

            if ($validated['value_type'] == 1) {        // flat
                $vPerc = ($value / $annualPremium) * 100;
                $vFlat = $value;
            } else {                                      // percent
                $vPerc = $value;
                $vFlat = ($value / 100) * $annualPremium;
            }

            if ($type === 'discount' && (($totalDisc + $vPerc) > $permitted)) {
                return response()->json(['success' => 0, 'message' => 'Can not exceed maximum discount value allowed'], 401);
            }
            if ($type === 'surcharge' && (($totalSurc + $vPerc) > $permitted)) {
                return response()->json(['success' => 0, 'message' => 'Can not exceed maximum surcharge value allowed'], 401);
            }
            if ($vPerc > $permitted) {
                return response()->json(['success' => 0, 'message' => 'You are only permitted to ' . $type . ' upto ' . $permitted . '% for this quote'], 401);
            }

            if ($type === 'discount') {
                $annual    = number_format((float) $annualPremium - $vFlat, 2, '.', '');
                $disValue  = $vPerc;  $surValue = 0;  $flatValue = -$vFlat;
            } else {
                $annual    = number_format((float) $annualPremium + $vFlat, 2, '.', '');
                $surValue  = $vPerc;  $disValue = 0;  $flatValue = $vFlat;
            }

            PolicyDiscountSurcharge::addLog([
                'policy_id'      => $policy->id,
                'discount'       => $disValue,
                'surcharge'      => $surValue,
                'old_value'      => $oldValue,
                'new_value'      => $annual,
                'total_dis_surc' => $flatValue,
                'ip_address'     => $request->ip(),
                'user_id'        => Auth::id(),
            ]);

            $monthly = number_format((float) $this->monthlyFromAnnual($annual), 2, '.', '');
            $three   = number_format((float) $annual / 3, 2, '.', '');

            return response()->json([
                'success'             => 1,
                'message'             => $type . ' added successfully',
                'annualPremium'       => $annual,
                'monthly_premium'     => $monthly,
                'threeintsll_premium' => $three,
            ]);
        } catch (\Exception $e) {
            Log::error('applyDiscountSurcharge failed: ' . $e->getMessage(), ['line' => $e->getLine()]);
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 401);
        }
    }

    // ─── 4. applyCustomRate ───────────────────────────────────────────────────

    /**
     * POST /policies/{id}/rerate-premium/custom-rate
     * Faithful to PolicyController::customPolicyDiscountSurchargeAPI (9895+):
     * a flat or percent-of-sum-insured override of the annual premium.
     */
    public function applyCustomRate(Request $request, int $policyId): JsonResponse
    {
        $validated = $request->validate([
            'value_type'            => 'required|integer|in:1,2',
            'value'                 => 'required|numeric|gt:0',
            'reason'                => 'required|string|max:500',
            'annual_premium_rerate' => 'required|numeric|gt:0',
        ]);

        $policy = Policy::find($policyId);
        if (!$policy) {
            return response()->json(['success' => 0, 'message' => 'Policy not found.'], 404);
        }

        try {
            $setting = QuoteSettings::first(['edit_limit']);
            $edited  = PolicyDiscountSurcharge::where('policy_id', $policy->id)
                ->where('discount', '!=', 'null')->where('surcharge', '!=', 'null')->count();
            if (!((int) $setting->edit_limit >= $edited + 1)) {
                return response()->json(['success' => 0, 'message' => 'You have exceeded maximum number of updates allowed'], 401);
            }

            $old = PolicyDiscountSurcharge::where('policy_id', $policy->id)->orderBy('id', 'desc')->first(['new_value']);
            $annualPremium = $validated['annual_premium_rerate'];
            $oldValue = $old ? $old->new_value : $annualPremium;
            if ($old) {
                $annualPremium = $old->new_value;
            }

            $value = (float) $validated['value'];
            if ($validated['value_type'] == 2) {  // percent of sum insured
                $vPerc       = $value;
                $newPremium  = ($value / 100) * $policy->sum_assured;
            } else {                               // flat
                $newPremium  = $value;
                $vPerc       = $value;
            }

            $annual = number_format((float) $newPremium, 2, '.', '');
            $isSurcharge = $newPremium > $annualPremium;

            PolicyDiscountSurcharge::addLog([
                'policy_id'       => $policy->id,
                'discount'        => $isSurcharge ? 0 : $vPerc,
                'surcharge'       => $isSurcharge ? $vPerc : 0,
                'old_value'       => $oldValue,
                'new_value'       => $annual,
                'total_dis_surc'  => number_format((float) $newPremium - (float) $annualPremium, 2, '.', ''),
                'custom_rate_per' => $validated['value_type'] == 2 ? $value : null,
                'custom_rate_flat'=> $validated['value_type'] == 1 ? $value : null,
                'custom_reason'   => $validated['reason'],
                'ip_address'      => $request->ip(),
                'user_id'         => Auth::id(),
            ]);

            $monthly = number_format((float) $this->monthlyFromAnnual($annual), 2, '.', '');
            $three   = number_format((float) $annual / 3, 2, '.', '');

            return response()->json([
                'success'             => 1,
                'message'             => 'Custom rate added successfully',
                'annualPremium'       => $annual,
                'monthly_premium'     => $monthly,
                'threeintsll_premium' => $three,
            ]);
        } catch (\Exception $e) {
            Log::error('applyCustomRate failed: ' . $e->getMessage(), ['line' => $e->getLine()]);
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 401);
        }
    }

    // ─── 5. accept ─────────────────────────────────────────────────────────────

    /**
     * POST /policies/{id}/rerate-premium/accept
     * Commits a PENDING rerate to the policy and flips the log to ACCEPTED.
     * Faithful to PolicyController::acceptNewRate (lines 10094-10293), minus the
     * inline payment writes — the React tab captures payment via the existing
     * RealPay / offline-payment endpoints after this returns.
     */
    public function accept(Request $request, int $policyId): JsonResponse
    {
        $validated = $request->validate([
            'rateID'                  => 'required|string',
            'frequency'               => 'required|integer|in:1,2,3',
            'annual_premium_rerate'   => 'required|numeric|gt:0',
            'dis_sur_annual_premium'  => 'nullable|numeric|gt:0',
            'first_premium'           => 'nullable|numeric|min:0',
            'billingDay'              => 'nullable|date_format:Y-m-d',
            'rerate_update_renew_term'=> 'nullable|boolean',
            'rerate_update_renewal_rates' => 'nullable|boolean',
            // V8 parity: when set, the rerate is recorded + customer/vehicle
            // updated, but the policy premium / term / renewal are NOT changed
            // (acceptNewRate wraps those in `if (!isset($request->rerate_without_payment))`).
            'rerate_without_payment'  => 'nullable|boolean',
        ]);

        $policy = Policy::with('customer')->find($policyId);
        if (!$policy) {
            return response()->json(['message' => 'Policy not found.'], 404);
        }

        $reratelog = PolicyPremiumReratingLog::where('ratings_id', $validated['rateID'])
            ->where('policy_number', $policy->policyNumber)
            ->orderBy('id', 'desc')->first();
        if (!$reratelog) {
            return response()->json(['message' => 'No updated premium found for this policy.'], 422);
        }

        $profile = CustomerProfile::where('customer_id', $policy->customer_id)->first();
        if (!$profile) {
            return response()->json(['message' => 'Customer not found.'], 422);
        }

        try {
            DB::beginTransaction();

            // Commit customer demographics + vehicle from the pending log.
            $profile->dob           = $reratelog->customer_dob;
            $profile->gender        = $reratelog->customer_gender;
            $profile->maritalstatus = $reratelog->customer_marital_status;
            $profile->save();

            $vehicle = Vehicle::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
            if ($vehicle) {
                $vehicle->estimated_value = $reratelog->sum_assured;
                $vehicle->make            = $reratelog->make;
                $vehicle->model           = $reratelog->model;
                $vehicle->year            = $reratelog->manufacturing_year;
                $vehicle->is_imported     = ($reratelog->japnese_import == 'Yes') ? 1 : 0;
                $vehicle->claim_count     = $reratelog->claim_count;
                $vehicle->save();
            }

            // Resolve the committed premium for the chosen frequency.
            $annualForFreq = $validated['dis_sur_annual_premium'] ?? null;
            if ($annualForFreq !== null) {
                $premium = $this->premiumForFrequency((int) $validated['frequency'], (float) $annualForFreq);
            } else {
                $premium = (int) $validated['frequency'] === 1 ? $reratelog->month_ins
                    : ((int) $validated['frequency'] === 2 ? $reratelog->three_ins : $reratelog->annual_ins);
            }

            // Reflect discount/surcharge premiums onto the quote row.
            if ($annualForFreq !== null && $policy->quoteNumber) {
                $mcq = MotorComprehensiveQuotes::where('quoteNumber', $policy->quoteNumber)
                    ->orderBy('id', 'desc')->first();
                if ($mcq) {
                    $mcq->premiumMonthly  = round($this->monthlyFromAnnual($annualForFreq), 2);
                    $mcq->premium3Inst    = round($annualForFreq / 3, 2);
                    $mcq->premiumAnnually = $annualForFreq;
                    $mcq->premium_rate    = $reratelog->sum_assured > 0
                        ? ($annualForFreq / $reratelog->sum_assured) * 100 : null;
                    $mcq->save();
                }
            }

            $reratelog->status = 'Accepted';
            $reratelog->save();

            $policyDisSur = PolicyDiscountSurcharge::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
            if ($policyDisSur) {
                $policyDisSur->new_sumInsured = $reratelog->sum_assured;
                $policyDisSur->save();
            }

            // V8 parity: acceptNewRate wraps the policy premium / term / renewal
            // commit in `if (!isset($request->rerate_without_payment))`. When the
            // operator ticks "Rerate without changing premium and payment
            // contract", we record the rerate + customer/vehicle but leave the
            // policy premium, term and renewal rates untouched.
            if (empty($validated['rerate_without_payment'])) {
                // Commit premium onto the policy.
                $policy->premium      = $premium;
                $policy->premium_freq = $validated['frequency'];
                $policy->sum_assured  = $reratelog->sum_assured;
                if (!empty($validated['first_premium'])) {
                    $policy->first_premium = $validated['first_premium'];
                }
                if (!empty($validated['billingDay'])) {
                    $policy->billingStartDate = $validated['billingDay'];
                }
                $policy->save();

                // Optionally push the new rate onto the active term (V8 parity).
                if (!empty($validated['rerate_update_renew_term'])) {
                    $term = PolicyTerm::where('policy_id', $policy->id)->where('status', 'Active')
                        ->orderBy('id', 'desc')->first();
                    if ($term) {
                        $term->premium        = $premium;
                        $term->annual_premium = $annualForFreq ?? $validated['annual_premium_rerate'];
                        $term->frequency      = $validated['frequency'];
                        $term->first_premium  = $validated['first_premium'] ?? $policy->first_premium;
                        $term->termDataUpdate = 1;
                        $term->save();
                    }
                }

                $renewal = PolicyRenewal::where('policyNumber', $policy->policyNumber)->where('is_renewed', 0)->first();
                if ($renewal) {
                    $renewal->rerate_update_renewal_rates = !empty($validated['rerate_update_renewal_rates']) ? 1 : null;
                    $renewal->save();
                }
            }

            DB::commit();
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error('Rerate accept failed: ' . $ex->getMessage(), ['line' => $ex->getLine()]);
            return response()->json(['message' => 'Failed to accept the new premium.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'New premium accepted.',
            'data'    => [
                'premium'   => $premium,
                'frequency' => (int) $validated['frequency'],
            ],
        ]);
    }

    // ─── 6. history ──────────────────────────────────────────────────────────

    /**
     * GET /policies/{id}/rerate-premium/history
     * Premium Update History — rerate log rows for the policy, newest first.
     */
    public function history(int $policyId): JsonResponse
    {
        $policy = Policy::find($policyId);
        if (!$policy) {
            return response()->json(['message' => 'Policy not found.'], 404);
        }

        $rows = PolicyPremiumReratingLog::PolicyNumber($policy->policyNumber)
            ->orderBy('id', 'desc')->get();

        $userNames = User::whereIn('id', $rows->pluck('rerated_by')->filter()->unique())
            ->get(['id', 'firstName', 'lastName'])
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->firstName . ' ' . $u->lastName)]);

        $data = $rows->map(fn ($r) => [
            'id'            => $r->id,
            'ratingsId'     => $r->ratings_id,
            'monthly'       => $r->month_ins,
            'threeInstalment' => $r->three_ins,
            'annual'        => $r->annual_ins,
            'sumAssured'    => $r->sum_assured,
            'discount'      => $r->discount ?? null,
            'surcharge'     => $r->surcharge ?? null,
            'status'        => $r->status,
            'reratedBy'     => $userNames[$r->rerated_by] ?? null,
            'createdAt'     => $r->created_at,
        ])->values();

        return response()->json(['data' => $data]);
    }

    // ─── helpers ───────────────────────────────────────────────────────────────

    /** Monthly from annual — V8 getMonthlyPrem(3, annual): (annual / 12) * 1.08. */
    private function monthlyFromAnnual($annual): float
    {
        return ((float) $annual / 12) * 1.08;
    }

    /** Premium for a frequency code (1=monthly, 2=3-inst, 3=annual). */
    private function premiumForFrequency(int $frequency, float $annual): float
    {
        if ($frequency === 1) {
            return round($this->monthlyFromAnnual($annual), 2);
        }
        if ($frequency === 2) {
            return round($annual / 3, 2);
        }
        return $annual;
    }

    /** Make/model option lists via the legacy QuoteController catalogue helpers. */
    private function vehicleOptions(?string $isImported, ?string $make, $year): array
    {
        $makes = [];
        $models = [];
        try {
            $qc = app(QuoteController::class);
            $makes  = $qc->getVehicleMakes($isImported);
            $models = $qc->getVehicleModels($make, $isImported, $year);
        } catch (\Exception $e) {
            Log::warning('Rerate vehicleOptions lookup failed: ' . $e->getMessage());
        }
        return [$makes, $models];
    }

    /**
     * Call the rating engine (HTTP/1.1, matches every other V2 caller).
     * Returns ['error'=>bool, 'message'=>?string, 'code'=>int, 'data'=>?array].
     */
    private function callRatingEngine(array $payload): array
    {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => self::RATING_URL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_ENCODING       => '',
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            ]);
            $response = curl_exec($ch);
            $error    = curl_error($ch);
            $errno    = curl_errno($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($error) {
                Log::error('Rerate rating engine curl error', ['errno' => $errno, 'error' => $error, 'http_code' => $httpCode]);
                return ['error' => true, 'message' => 'Failed to reach rating engine.', 'code' => 500, 'data' => null];
            }

            $data = json_decode($response, true);
            if (!isset($data['success']) || $data['success'] != 1) {
                return ['error' => true, 'message' => $data['message'] ?? 'Rating engine returned an error.', 'code' => 422, 'data' => null];
            }
            return ['error' => false, 'message' => null, 'code' => 200, 'data' => $data];
        } catch (\Exception $e) {
            Log::error('Rerate rating engine call failed: ' . $e->getMessage());
            return ['error' => true, 'message' => 'Failed to reach rating engine.', 'code' => 500, 'data' => null];
        }
    }
}
