<?php

namespace AlphaDirect\Http\Livewire\Policy;

use AlphaDirect\Customer;
use AlphaDirect\DiscountSurcharge;
use AlphaDirect\Http\Controllers\Admin\DocumentController;
use AlphaDirect\Http\Controllers\SmsMessaging;
use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Policy;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Region;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use Illuminate\Support\Facades\App;
use AlphaDirect\Models\PolicyCoverageDetail;
use AlphaDirect\Helper;
use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\Models\User;
use Illuminate\Support\Facades\Auth;
use AlphaDirect\Models\ValidationRuleGroupMaster;
use AlphaDirect\Models\ValidationRuleGroupMasterDetail;
use AlphaDirect\Models\ValidationRuleMaster;
use AlphaDirect\Models\ValidationRuleDetail;
use AlphaDirect\Models\ReinsuranceGroup;
use AlphaDirect\Models\ReinsuranceGroupCoverage;
use AlphaDirect\Role;
use AlphaDirect\Models\Motor;
use AlphaDirect\Models\MotorTradersInternal;
use AlphaDirect\Models\MotorTraders;
use AlphaDirect\Models\PolicyExtentionDetails;
use AlphaDirect\Models\PolicyCoveragesData;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Models\policyActionEndorse;
use AlphaDirect\Ledger;
use AlphaDirect\SubLedger;
use AlphaDirect\CustomerBanking;
use AlphaDirect\Models\V2PdfJob;
use AlphaDirect\Jobs\GenerateQuotationPdfJob;
use AlphaDirect\Models\CarCoverage;
use AlphaDirect\Models\EarCoverage;
use AlphaDirect\Models\ParCoverage;
use AlphaDirect\Models\MedicalMalpracticeCoverage as MedicalMalpracticeCoverageModel;
use AlphaDirect\Models\ProfessionalIndemnityCoverage as ProfessionalIndemnityCoverageModel;
use AlphaDirect\Models\TravelCoverage as TravelCoverageModel;

use DB;
class Submit extends Component
{
    public Policy $policy;
    public $actionId;
    public $termId;
    public $action;
    public $customer;
    public $previousActionId;
    public $dataShowForActionId;
     public $status;
      public $pdfJobId;
      public $downloadLink;
      public $fileName;
      public $hasActivityAfterPdf = false;
      public $showSubmitButtons = false; // Flag to show Submit To Issue and Submit To Approval buttons
    public function mount()
    {
        $this->action = PolicyAction::find($this->actionId);
        $this->customer = $this->policy->customer;
        $this->dataShowForActionId = $this->previousActionId ?? $this->actionId;
        $this->check_invoice_exists = Ledger::where('policy_id', $this->policy->id)
            ->where('action_id', $this->actionId)
            ->where('trans_type', 'Invoice')
            // ->where('invoice_date', $date)
            ->count();
        $this->rateFLag = 0;
        
        // Load PDF job status from session if exists
        $sessionKey = "pdf_job_{$this->policy->id}_{$this->termId}_{$this->dataShowForActionId}";
        $pdfJobId = session($sessionKey);
        
        // Also check if there's a completed job that wasn't cleared
        if (!$pdfJobId) {
            $existingJob = \AlphaDirect\Models\V2PdfJob::where('policy_id', $this->policy->id)
                ->where('term_id', $this->termId)
                ->where('action_id', $this->dataShowForActionId)
                ->where('status', 'completed')
                ->whereNotNull('file_name')
                ->orderBy('id', 'desc')
                ->first();
                
            if ($existingJob) {
                $this->pdfJobId = $existingJob->id;
                session()->put($sessionKey, $existingJob->id);
            }
        }
        
        // Always check status if we have a pdfJobId (from session or found existing job)
        if ($this->pdfJobId ?? $pdfJobId) {
            $this->checkPdfJobStatus();
        } else {
            // Initialize hasActivityAfterPdf to false if no PDF job
            $this->hasActivityAfterPdf = false;
        }
    }

    public function render()
    {
        return view('v2.livewire.policy.submit');
    }

    /**
     * Helper function to parse numeric values (handles comma-separated numbers)
     */
    private function parseNumericValue($value)
    {
        // Handle null, empty string, or false
        if ($value === null || $value === false || $value === '') return 0;
        
        // If already a number, return it
        if (is_numeric($value) && !is_string($value)) {
            return (float)$value;
        }
        
        // Convert to string
        $value = trim((string)$value);
        
        // Handle empty after trim
        if ($value === '' || $value === '-') return 0;
        
        // Remove all commas (thousands separators)
        $value = str_replace(',', '', $value);
        
        // Remove currency symbols and spaces
        $value = str_replace(['P', 'p', '$', '€', '£', ' ', 'R'], '', $value);
        
        // Remove any non-numeric characters except decimal point and minus sign
        $cleaned = preg_replace('/[^0-9.-]/', '', $value);
        
        // Handle empty result or invalid formats
        if (empty($cleaned) || $cleaned === '-' || $cleaned === '.' || $cleaned === '-.') return 0;
        
        // Convert to float
        $result = (float)$cleaned;
        
        // Check for NaN or infinity
        if (is_nan($result) || is_infinite($result)) return 0;
        
        return $result;
    }

    /**
     * Calculate total premium for CAR, EAR, and PAR coverages
     */
    private function calculateCarEarParPremiums($coverages)
    {
        $totalPremium = 0;
        
        foreach ($coverages as $coverage) {
            $coverageCode = $coverage->coverage->s_CoverageCode ?? '';
            
            // CAR Coverage
            if (($coverageCode == "CONTRACTORSALLRISKS" || $coverageCode == "CAR") && $coverage->carCoverage) {
                $carCoverage = $coverage->carCoverage;
                
                // Section 1 Items
                $carSection1Items = is_array($carCoverage->section1_items) 
                    ? $carCoverage->section1_items 
                    : (json_decode($carCoverage->section1_items ?? '[]', true) ?? []);
                foreach ($carSection1Items as $item) {
                    $totalPremium += $this->parseNumericValue($item['premium'] ?? '');
                }
                
                // Section 2 Items
                $carSection2Items = is_array($carCoverage->section2_items) 
                    ? $carCoverage->section2_items 
                    : (json_decode($carCoverage->section2_items ?? '[]', true) ?? []);
                foreach ($carSection2Items as $item) {
                    $totalPremium += $this->parseNumericValue($item['premium'] ?? '');
                }

                // Section 3 Items
                $carSection3Items = is_array($carCoverage->section3_items) 
                    ? $carCoverage->section3_items 
                    : (json_decode($carCoverage->section3_items ?? '[]', true) ?? []);
                foreach ($carSection3Items as $item) {
                    $totalPremium += $this->parseNumericValue($item['premium'] ?? '');
                }
                
                // Section 3 - Gross Profit Premium
                if (!empty($carCoverage->section3_gross_profit_premium)) {
                    $totalPremium += $this->parseNumericValue($carCoverage->section3_gross_profit_premium);
                }
                
                // Section 3 - Increased Cost of Working Premium
                if (!empty($carCoverage->section3_increased_cost_premium)) {
                    $totalPremium += $this->parseNumericValue($carCoverage->section3_increased_cost_premium);
                }
                
                // Plant List Items
                // $carPlantListItems = is_array($carCoverage->plant_list_items) 
                //     ? $carCoverage->plant_list_items 
                //     : (json_decode($carCoverage->plant_list_items ?? '[]', true) ?? []);
                // foreach ($carPlantListItems as $item) {
                //     $totalPremium += $this->parseNumericValue($item['premium'] ?? '');
                // }
                
                // Risk premiums (Earthquake and Storm)
                //$totalPremium += $this->parseNumericValue($carCoverage->section1_earthquake_premium ?? '');
                //$totalPremium += $this->parseNumericValue($carCoverage->section1_storm_premium ?? '');
            }
            // EAR Coverage
            elseif (($coverageCode == "ERECTIONALLRISKS" || $coverageCode == "EAR") && $coverage->earCoverage) {
                $earCoverage = $coverage->earCoverage;
                
                // Section 1 Items
                $earSection1Items = is_array($earCoverage->section1_items) 
                    ? $earCoverage->section1_items 
                    : (json_decode($earCoverage->section1_items ?? '[]', true) ?? []);
                foreach ($earSection1Items as $item) {
                    $totalPremium += $this->parseNumericValue($item['premium'] ?? '');
                }
                
                // Section 3 Items
                $earSection3Items = is_array($earCoverage->section3_items) 
                    ? $earCoverage->section3_items 
                    : (json_decode($earCoverage->section3_items ?? '[]', true) ?? []);
                foreach ($earSection3Items as $item) {
                    $totalPremium += $this->parseNumericValue($item['premium'] ?? '');
                }
                
                // Risk premiums (Earthquake and Storm)
                $totalPremium += $this->parseNumericValue($earCoverage->risk_earthquake_premium ?? '');
                $totalPremium += $this->parseNumericValue($earCoverage->risk_storm_premium ?? '');
            }
            // PAR Coverage
            elseif (($coverageCode == "PLANTALLRISKS" || $coverageCode == "PAR") && $coverage->parCoverage) {
                $parCoverage = $coverage->parCoverage;
                
                // Insured Items
                $parInsuredItems = is_array($parCoverage->insured_items) 
                    ? $parCoverage->insured_items 
                    : (json_decode($parCoverage->insured_items ?? '[]', true) ?? []);
                foreach ($parInsuredItems as $item) {
                    $totalPremium += $this->parseNumericValue($item['premium'] ?? '');
                }
                
                // Section 2 Items
                $parSection2Items = is_array($parCoverage->section2_items) 
                    ? $parCoverage->section2_items 
                    : (json_decode($parCoverage->section2_items ?? '[]', true) ?? []);
                foreach ($parSection2Items as $item) {
                    $totalPremium += $this->parseNumericValue($item['premium'] ?? '');
                }
            }
        }
        
        return round($totalPremium, 2);
    }

    public function calculatePremium()
    {
        // dd($this->actionId);
        // now store calculated values in policy_Action table
        $policyAction = PolicyAction::where('id', $this->actionId)->first();
        $policyTerm = PolicyTerm::where('id', $this->termId)
            //->where('status','Active')
            ->first();

        $policyActionPrev = PolicyAction::where('policy_id', '=', $policyAction->policy_id)
            ->where('id', '<', $this->actionId)
            ->where('transaction_type', '=', 'RENEW')
            ->where('effective_to', '=', $policyAction->effective_to)
            ->orderBy('id', 'desc')->take(1)
            ->first();
        $diff_in_days_new_coverage = 0;
        $diff_in_days_main = 0;
        $policyActionCnt = PolicyAction::where('policy_id', $policyAction->policy_id)->count();
        if ($policyActionCnt > 1) {
            if (!isset($policyActionPrev) || $policyActionPrev == NULL) {
                $policyActionPrev = PolicyAction::where('policy_id', '=', $policyAction->policy_id)
                    ->where('id', '<', $this->actionId)
                    ->where('transaction_type', '=', 'NEWBUSINESS')
                    ->orderBy('id', 'desc')->take(1)
                    ->first();
            }
            // $diff_in_days_main = Carbon::parse($policyTerm->term_end_date)->diffInDays(Carbon::parse($this->policy->term_start_date));
            if (isset($policyActionPrev) && $policyActionPrev != null) {
                $datetime1 = strtotime($policyActionPrev->effective_from); // convert to timestamps

                $datetime2 = strtotime($policyActionPrev->effective_to); // convert to timestamps
                $diff_in_days_main = (int) (($datetime2 - $datetime1) / 86400) + 1;
            }
        }
        if ($this->policy->term_start_date) {
            $fromDate = (new Carbon($this->policy->term_start_date))->format(config('constants.date.format'));
        } else {
            $fromDate = null;
        }

        if ($this->policy->expiry_date) {
            $toDate = (new Carbon($this->policy->expiry_date))->format(config('constants.date.format'));
            $anniversaryDate = (new Carbon($this->policy->expiry_date))->format(config('constants.date.format'));
        } else {
            $toDate = null;
            $anniversaryDate = null;
        }
        if ($policyAction->transaction_type == 'CANCEL') {
            $premium = 0;
            $diff_in_days_new_coverage = 0;
            $diff_in_days_main = 0;
            if ($this->policy->premium_freq == 3) {
                $policyActionPrev = PolicyAction::where('policy_id', '=', $this->policy->id)
                    ->whereIn('transaction_type', ['NEWBUSINESS', 'ANNIVERSARY-RENEW'])
                    ->whereDate('effective_from', '<=', $policyAction->effective_from)
                    ->whereNull('deleted_at')
                    ->orderBy('id', 'desc')->take(1)
                    ->first();
            } else if ($this->policy->premium_freq == 1 || $this->policy->premium_freq == 2 || $this->policy->premium_freq == 5) {
                $policyActionPrev = PolicyAction::where('policy_id', $this->policy->id)
                    ->whereIn('transaction_type', ['NEWBUSINESS', 'RENEW', 'ANNIVERSARY-RENEW'])
                    ->whereDate('effective_from', '<=', $policyAction->effective_from)
                    ->orderBy('effective_from', 'desc')
                    ->first();
            }
            // Pro-rata refund — frequency-wise as per dates.
            $annualPremium = ($this->policy->annual_premium > 0)
                ? $this->policy->annual_premium
                : ($policyActionPrev->premium ?? 0);

            $cancelFrom = \Carbon\Carbon::parse($policyAction->effective_from);

            if ($this->policy->premium_freq == 3) {
                // ANNUAL: refund = annualPremium × (daysFromCancel→termEnd / totalTermDays)
                $termStart = strtotime($this->policy->term_start_date);
                $termEnd   = strtotime($this->policy->expiry_date);
                $diff_in_days_main         = (int) (($termEnd - $termStart) / 86400) + 1;
                $diff_in_days_new_coverage = (int) (($termEnd - $cancelFrom->getTimestamp()) / 86400) + 1;

                $premium = ($diff_in_days_main > 0)
                    ? $annualPremium * ($diff_in_days_new_coverage / $diff_in_days_main)
                    : 0;
            } else {
                // MONTHLY (freq=1,2,5): refund = (annualPremium ÷ 12) × (daysLeftInMonth / daysInMonth)
                $daysInMonth        = $cancelFrom->daysInMonth;
                $endOfMonth         = $cancelFrom->copy()->endOfMonth();
                $daysLeftInMonth    = $endOfMonth->day - $cancelFrom->day + 1;

                $diff_in_days_main         = $daysInMonth;
                $diff_in_days_new_coverage = $daysLeftInMonth;

                $monthlyPremium = $annualPremium / 12;
                $premium = ($daysInMonth > 0)
                    ? $monthlyPremium * ($daysLeftInMonth / $daysInMonth)
                    : 0;
            }
            $endorsements = PolicyAction::where('policy_id', $this->policy->id)
                ->where('transaction_type', '=', 'ENDORSE')
                ->whereNull('deleted_at')
                ->where('status', '=', 'ISSUED')
                ->orderBy('effective_from')
                ->get();

            // An ISSUED transaction effective ON OR AFTER the cancel date never
            // took effect, so it must not feed the refund calculation.
            //
            // This used to be done by SOFT-DELETING those actions right here —
            // inside a premium CALCULATION. Rating a cancel quote the operator
            // might never issue permanently discarded issued ENDORSE /
            // ENDORSE-RENEW / RENEW transactions, with no confirmation, no
            // payment check and no audit row. Voiding post-cancel transactions
            // is legitimate, but it belongs at cancel ISSUE time, where
            // PostCancelCleanupService already does it with the payment /
            // credit-note guards. Rate now only EXCLUDES them from the sum.
            $excludePostCancel = function ($q) use ($policyAction) {
                $q->where(function ($w) use ($policyAction) {
                    $w->where('status', '!=', 'ISSUED')
                        ->orWhere('effective_from', '<', $policyAction->effective_from);
                });
            };

            $endorsementsMonth = PolicyAction::where('policy_id', $this->policy->id)
                ->whereIn('transaction_type', ['ENDORSE', 'ENDORSE-RENEW'])
                ->whereDate('effective_to', '=', $policyAction->effective_to)
                ->whereNull('deleted_at')
                ->where($excludePostCancel)
                ->orderBy('effective_from')
                ->get();
            $refundPremium = 0;
            $endorsements = PolicyAction::where('policy_id', $this->policy->id)
                ->where('transaction_type', '=', 'ENDORSE')
                ->whereNull('deleted_at')
                ->where($excludePostCancel)
                ->orderBy('effective_from')
                ->get();
            if ($this->policy->premium_freq == 3) {
                $refundPremium = $this->calculateRefundPremiumWithEndorsements(
                    $premium,
                    $policyActionPrev->effective_from,
                    $policyAction->effective_from,
                    $diff_in_days_new_coverage,
                    $endorsements->map(function ($endorsement) {
                        return [
                            'premium' => $endorsement->premium,
                            'effective_date' => $endorsement->effective_from,
                            'end_date' => $endorsement->effective_to
                        ];
                    })->toArray()
                );
            } else if ($this->policy->premium_freq == 1 || $this->policy->premium_freq == 5 || $this->policy->premium_freq == 2) {
                $refundPremium = $this->calculateMonthlyRefundPremiumWithEndorsements(
                    $premium,
                    $policyActionPrev->effective_from,
                    $policyAction->effective_from,
                    $diff_in_days_new_coverage,
                    $endorsementsMonth->map(function ($endorsement) {
                        return [
                            'premium' => $endorsement->premium,
                            'effective_date' => $endorsement->effective_from,
                            'end_date' => $endorsement->effective_to,
                            'id' => $endorsement->id
                        ];
                    })->toArray()
                );
            }
            $proratapremium = -$refundPremium;
            $premiumNew = 0;
        } else if ($policyAction->transaction_type == 'ENDORSE' && $policyAction->transaction_reason == 'COVERAGECANCEL') {

            $datetimeact = strtotime($policyAction->effective_from); // convert to timestamps
            $datetimeact2 = strtotime($policyAction->effective_to); // convert to timestamps
            $diff_in_days_new_coverage = (int) (($datetimeact2 - $datetimeact) / 86400) + 1;
            $coverages = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $this->actionId)->where('term_id', $this->termId)->where('status', '1')->pluck('id');
            $coverages_coverage_id = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $this->actionId)->where('term_id', $this->termId)->where('status', '1')->pluck('coverage_id');
            $personal_motor_coverages = PolicyCoverage::where(function ($query) {
                $query->where('coverage_id', 15)
                    ->orWhere('coverage_id', 16)
                    ->orWhere('coverage_id', 27)
                    ->orWhere('coverage_id', 22);
            })->where('policy_id', $this->policy->id)
                ->where('action_id', $this->actionId)
                ->where('term_id', $this->termId)
                ->first();
            $coverages_cancel = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $this->actionId)->where('term_id', $this->termId)->pluck('id');

            $premiumNew = 0;
            $proratapremium = 0;
            $proratapremiumMain = 0;
            $internal_motor_sum_calculated_value = 0;
            $external_motor_sum_calculated_value = 0;
            $motor_sum_calculated_value = 0;
            $sum_specified_items = PolicySpecifiedItem::whereIn('policy_coverage_id', $coverages)->sum('calculated_value');
            $sum_calculated_value = PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')->whereNull('tb_cvgpccoverages.policy_id')->whereIn('policy_coverage_id', $coverages)->sum('calculated_value');
            $sum_exts_calculated_value = DB::table('policy_extention_detail as ped')
            ->join('policy_coverages as p', function ($join) {
                $join->on('p.id', '=', 'ped.policy_coverage_id')
            ->on('ped.s_ParentCoverageID', '=', 'p.coverage_id');
            })
            ->whereIn('ped.policy_coverage_id', $coverages)->whereIn('ped.s_ParentCoverageID', $coverages_coverage_id)->where('ped.type', 'Extention')
            ->whereNull('ped.deleted_at')
            ->where('ped.previousActionIdCov', $this->actionId)
            ->where('p.policy_id', $this->policy->id)
            ->where('ped.endors_flag', '1')
            ->whereNull('p.deleted_at')
            ->whereNull('ped.deleted_at')
            ->sum('ped.extention_calculated_value');
            // Fidelity Guarantee (coverage_id 9) stores its premium in
            // policy_coverages_data, which is pinned to the `mysql_system`
            // connection. A cross-CONNECTION join to policy_coverages (default
            // `mysql` connection) can silently return nothing when the two
            // resolve to different databases. So resolve the cancelled Fidelity
            // coverage ids on the default connection first (cancel signal =
            // parent status='1' + soft-deleted, scoped to this action/term;
            // a reinstated coverage has deleted_at NULL and is excluded), then
            // sum the premium on policy_coverages_data. Full premium is summed
            // here; the pro-rata day factor is applied below.
            $cancelledFidelityCoverageIds = PolicyCoverage::withTrashed()
                ->where('policy_id', $this->policy->id)
                ->where('action_id', $this->actionId)
                ->where('term_id', $this->termId)
                ->where('coverage_id', 9)
                ->where('status', '1')
                ->whereNotNull('deleted_at')
                ->pluck('id')
                ->all();
            $policyCoveragesDataSum = empty($cancelledFidelityCoverageIds)
                ? 0
                : PolicyCoveragesData::whereIn('policyCoverageID', $cancelledFidelityCoverageIds)->sum('premium');

            if (!empty($personal_motor_coverages)) {
                if ($personal_motor_coverages->status == '0') {
                    $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages_cancel)->whereNotNull('deleted_at')->where('previousActionIdCov', $this->actionId)->sum('calculated_value');
                    $internal_motor_sum_calculated_value = MotorTradersInternal::whereIn('policy_coverage_id', $coverages)
                        ->get(array(DB::raw('SUM(loss_or_damage_calculated_value + third_party_liability_calculated_value + medical_benefits_calculated_value) as internal_motor_sum_calculated_value')))
                        ->toArray();
                    $external_motor_sum_calculated_value = MotorTraders::whereIn('policy_coverage_id', $coverages)
                        ->get(array(DB::raw('SUM(loss_or_damage_calculated_value + third_party_liability_calculated_value + medical_benefits_calculated_value) as external_motor_sum_calculated_value')))
                        ->toArray();
                    $internal_motor_sum_calculated_value = isset($internal_motor_sum_calculated_value[0]['internal_motor_sum_calculated_value']) ? $internal_motor_sum_calculated_value[0]['internal_motor_sum_calculated_value'] : 0;
                    $external_motor_sum_calculated_value = isset($external_motor_sum_calculated_value[0]['external_motor_sum_calculated_value']) ? $external_motor_sum_calculated_value[0]['external_motor_sum_calculated_value'] : 0;

                    $premiumNew = $sum_calculated_value + $sum_specified_items + $sum_exts_calculated_value + $motor_sum_calculated_value + $internal_motor_sum_calculated_value + $external_motor_sum_calculated_value + $policyCoveragesDataSum;
                } else if ($personal_motor_coverages->status == '1') {
                    $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages)->sum('calculated_value');
                    $internal_motor_sum_calculated_value = MotorTradersInternal::whereIn('policy_coverage_id', $coverages)
                        ->get(array(DB::raw('SUM(loss_or_damage_calculated_value + third_party_liability_calculated_value + medical_benefits_calculated_value) as internal_motor_sum_calculated_value')))
                        ->toArray();
                    $external_motor_sum_calculated_value = MotorTraders::whereIn('policy_coverage_id', $coverages)
                        ->get(array(DB::raw('SUM(loss_or_damage_calculated_value + third_party_liability_calculated_value + medical_benefits_calculated_value) as external_motor_sum_calculated_value')))
                        ->toArray();
                    $internal_motor_sum_calculated_value = isset($internal_motor_sum_calculated_value[0]['internal_motor_sum_calculated_value']) ? $internal_motor_sum_calculated_value[0]['internal_motor_sum_calculated_value'] : 0;
                    $external_motor_sum_calculated_value = isset($external_motor_sum_calculated_value[0]['external_motor_sum_calculated_value']) ? $external_motor_sum_calculated_value[0]['external_motor_sum_calculated_value'] : 0;

                    $premiumNew = $sum_calculated_value + $sum_specified_items + $sum_exts_calculated_value + $motor_sum_calculated_value + $internal_motor_sum_calculated_value + $external_motor_sum_calculated_value + $policyCoveragesDataSum;
                }
            } else {
                $premiumNew = $sum_calculated_value + $sum_exts_calculated_value + $sum_specified_items + $policyCoveragesDataSum;
            }
            
            // Add CAR, EAR, PAR premiums for COVERAGECANCEL
            $carEarParCoverages = PolicyCoverage::where('policy_id', $this->policy->id)
                ->where('action_id', $this->actionId)
                ->where('term_id', $this->termId)
                ->where('status', '1')
                ->with(['coverage', 'carCoverage', 'earCoverage', 'parCoverage'])
                ->get();
            
            $carEarParPremium = $this->calculateCarEarParPremiums($carEarParCoverages);
            $premiumNew += $carEarParPremium;

            $proratapremium = -(($diff_in_days_new_coverage) / ($diff_in_days_main)) * $premiumNew;

            $premiumNew = 0;


        } else if ($policyAction->transaction_type == 'ENDORSE' && $policyAction->transaction_reason != 'COVERAGECANCEL' && $policyAction->transaction_reason != 'WRITEOFFCVG') {

            $datetimeact = strtotime($policyAction->effective_from); // convert to timestamps
            $datetimeact2 = strtotime($policyAction->effective_to); // convert to timestamps
            $diff_in_days_new_coverage = (int) (($datetimeact2 - $datetimeact) / 86400) + 1;
            // $diff_in_days_new_coverage = Carbon::parse($policyAction->effective_to)->diffInDays(Carbon::parse($policyAction->effective_from));
            $coverages = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $this->actionId)->where('term_id', $this->termId)->pluck('id');
            $coverages_coverage_id = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $this->actionId)->where('term_id', $this->termId)->where('endors_flag', '1')->pluck('coverage_id');
            $coverages1 = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $this->actionId)->where('term_id', $this->termId)->pluck('id');

            $personal_motor_coverages = PolicyCoverage::where(function ($query) {
                $query->where('coverage_id', 15)
                    ->orWhere('coverage_id', 16)
                    ->orWhere('coverage_id', 27)
                    ->orWhere('coverage_id', 22);
            })->where('policy_id', $this->policy->id)
                ->where('action_id', $this->actionId)
                ->where('term_id', $this->termId)
                ->first();

            $internal_motor_sum_calculated_value = 0;
            $external_motor_sum_calculated_value = 0;
            $motor_sum_calculated_value = 0;
            $sum_specified_items = 0;
             $check_negative = 0;
            $sum_specified_items = PolicySpecifiedItem::whereNull('deleted_at')
                    ->whereIn('policy_coverage_id', $coverages)
                    ->where('action_id', $this->actionId)
                    ->where('endors_flag', '1')
                    ->sum('calculated_value');   
             // added for deleted items on 28-10-25
            $sum_specified_items_deleted = PolicySpecifiedItem::onlyTrashed()
                ->whereIn('policy_coverage_id', $coverages)
                ->where('action_id', $this->actionId)
                ->where('endors_flag', '1')
                ->sum('calculated_value');
            $sum_calculated_value = PolicyCoverageDetail::whereIn('policy_coverage_id', $coverages)->where('endors_flag', '1')->where('previousActionIdCov', $this->actionId)->sum('pro_rate_premium');
            $sum_exts_calculated_value = DB::table('policy_extention_detail as ped')
                ->join('policy_coverages as p', function ($join) {
                    $join->on('p.id', '=', 'ped.policy_coverage_id')
                        ->on('ped.s_ParentCoverageID', '=', 'p.coverage_id');
                })
                ->whereIn('ped.policy_coverage_id', $coverages)
                ->where('ped.type', 'Extention')
                ->where('ped.endors_flag', '1')
                ->where('ped.previousActionIdCov', $this->actionId)
                ->whereNull('ped.deleted_at')
                ->whereNull('p.deleted_at')
                ->sum('ped.pro_rate_premium');
                $policyCoveragesDataSum = PolicyCoveragesData::join(
                    'policy_coverages',
                    'policy_coverages.id',
                    '=',
                    'policy_coverages_data.policyCoverageID'
                )
                ->where('policy_coverages.policy_id', $this->policy->id)
                ->where('policy_coverages.action_id', $this->actionId)
                 ->where('policy_coverages.coverage_id', 9)
                ->whereIn('policy_coverages_data.policyCoverageID', $coverages)
                ->where('policy_coverages_data.previousActionIdCov', $this->actionId)
                ->where('policy_coverages_data.endors_flag', '1')
                ->whereNull('policy_coverages.deleted_at')
                ->sum('policy_coverages_data.premium');          
      // $this->action->premium = PolicyCoverageDetail::whereIn('policy_coverage_id', $coverages)->sum('calculated_value');
            if($sum_calculated_value <0 )
            {
                $sum_calculated_value = $sum_calculated_value * -1;
                $check_negative = 1;
            }
            if (!empty($personal_motor_coverages)) {
                $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages1)->where('endors_flag', '1')->where('previousActionIdCov', $this->actionId)->whereNull('deleted_at')->sum('pro_rate_premium');
                $internal_motor_sum_calculated_value = MotorTradersInternal::whereIn('policy_coverage_id', $coverages1)->where('endors_flag', '1')->where('previousActionIdCov', $this->actionId)
                    ->get('pro_rate_premium')
                    ->toArray();
                $external_motor_sum_calculated_value = MotorTraders::whereIn('policy_coverage_id', $coverages1)->where('endors_flag', '1')->where('previousActionIdCov', $this->actionId)
                    ->get('pro_rate_premium')
                    ->toArray();
                $internal_motor_sum_calculated_value = isset($internal_motor_sum_calculated_value[0]['pro_rate_premium']) ? $internal_motor_sum_calculated_value[0]['pro_rate_premium'] : 0;
                $external_motor_sum_calculated_value = isset($external_motor_sum_calculated_value[0]['pro_rate_premium']) ? $external_motor_sum_calculated_value[0]['pro_rate_premium'] : 0;
                if($motor_sum_calculated_value <0 )
                {
                    $motor_sum_calculated_value = $motor_sum_calculated_value * -1;
                    $check_negative = 1;
                }
                $proratapremiumMain = $sum_calculated_value + $sum_specified_items + $sum_specified_items_deleted + $sum_exts_calculated_value + $motor_sum_calculated_value + $internal_motor_sum_calculated_value + $external_motor_sum_calculated_value + $policyCoveragesDataSum;
            } else {
                $proratapremiumMain = $sum_calculated_value + $sum_specified_items + $sum_specified_items_deleted + $policyCoveragesDataSum + $sum_exts_calculated_value;
            }
            
            // Add CAR, EAR, PAR premiums for ENDORSE
            $carEarParCoverages = PolicyCoverage::where('policy_id', $this->policy->id)
                ->where('action_id', $this->actionId)
                ->where('term_id', $this->termId)
                ->where('endors_flag', '1')
                ->with(['coverage', 'carCoverage', 'earCoverage', 'parCoverage'])
                ->get();
            
            $carEarParPremium = $this->calculateCarEarParPremiums($carEarParCoverages);
            $proratapremiumMain += $carEarParPremium;
            
            //dd($this->actionId,$sum_calculated_value, $sum_specified_items, $sum_specified_items_deleted, $sum_exts_calculated_value, $motor_sum_calculated_value, $internal_motor_sum_calculated_value, $external_motor_sum_calculated_value, $policyCoveragesDataSum);
            // dd($diff_in_days_new_coverage, $proratapremiumMain ,$diff_in_days_main);
            $proratapremium = (($diff_in_days_new_coverage) / ($diff_in_days_main)) * $proratapremiumMain;
            if ($sum_specified_items_deleted > 0 || $check_negative == 1) {
                $proratapremium = (-1) * $proratapremium;
                $check_negative = 1;
            }
            // dd( $proratapremium);
            $premiumNew = 0;
        } else if($policyAction->transaction_type == 'ENDORSE' && $policyAction->transaction_reason == 'WRITEOFFCVG'){
            $proratapremium = 0;
            $premiumNew = 0;
        }
        else {

            $coverages = PolicyCoverage::where('policy_id', $this->policy->id)->whereNull('deleted_at')->where('action_id', $this->actionId)->where('term_id', $this->termId)->whereNull('deleted_at')->where('status', '0')->pluck('id');
            $coverages_coverage_id = PolicyCoverage::where('policy_id', $this->policy->id)->whereNull('deleted_at')->where('action_id', $this->actionId)->where('term_id', $this->termId)->whereNull('deleted_at')->where('status', '0')->pluck('coverage_id');
            $personal_motor_coverages = PolicyCoverage::where(function ($query) {
                $query->where('coverage_id', 15)
                    ->orWhere('coverage_id', 16)
                    ->orWhere('coverage_id', 27)
                    ->orWhere('coverage_id', 22);
            })->where('policy_id', $this->policy->id)
                ->where('action_id', $this->actionId)
                ->where('term_id', $this->termId)
                ->where('status', '0')
                ->first();
            $premiumNew = 0;
            $proratapremium = 0;
            $proratapremiumMain = 0;
            $internal_motor_sum_calculated_value = 0;
            $external_motor_sum_calculated_value = 0;
            $motor_sum_calculated_value = 0;
            $sum_specified_items = PolicySpecifiedItem::whereIn('policy_coverage_id', $coverages)->whereNull('deleted_at')->sum('calculated_value');
            $sum_calculated_value = PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                 ->whereNull('tb_cvgpccoverages.policy_id')->whereIn('policy_coverage_id', $coverages)->whereNull('deleted_at')->sum('calculated_value');
            $sum_exts_calculated_value = DB::table('policy_extention_detail as ped')
                ->join('policy_coverages as p', function ($join) {
                    $join->on('p.id', '=', 'ped.policy_coverage_id')
                        ->on('ped.s_ParentCoverageID', '=', 'p.coverage_id');
                })
                ->whereIn('ped.policy_coverage_id', $coverages)
                ->where('ped.type', 'Extention')
                ->where('p.action_id', $this->actionId)
                ->whereNull('p.deleted_at')
                ->whereNull('ped.deleted_at')
                ->sum('ped.extention_calculated_value');
                $policyCoveragesDataSum =  PolicyCoveragesData::join('policy_coverages', 'policy_coverages.id', '=', 'policy_coverages_data.policyCoverageID')
                                    ->where('policy_coverages.policy_id', $this->policy->id)
                                    ->where('policy_coverages.coverage_id', 9)
                                    ->whereNull('policy_coverages.deleted_at')
                                    ->where('policy_coverages.action_id', $this->actionId)
                                    ->sum('policy_coverages_data.premium');
            
            //PolicyCoveragesData::where('policy_id', $this->policy->id)->whereIn('policyCoverageID', $coverages)->sum('premium');
            // $this->action->premium = PolicyCoverageDetail::whereIn('policy_coverage_id', $coverages)->sum('calculated_value');
        
            if (!empty($personal_motor_coverages)) {

                // added by snehal on 28-10-25
                if ($coverages_coverage_id->contains(22)) {
                    $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages)
                        ->whereNull('deleted_at')
                        ->selectRaw('
                                                        SUM( calculated_value +
                                                            premium_passenger_liability +
                                                            premium_unorthorised_passanger_liability +
                                                            premium_parking_facilities +
                                                            premium_com_windscreen +
                                                            premium_riot_strike +
                                                            premium_locks_keys +
                                                            premium_wreckage_removal +
                                                            premium_credit_shortfall +
                                                            premium_third_party_liability
                                                        ) as total_premium
                                                    ')
                        ->first();

                }
                if ($coverages_coverage_id->contains(27)) {
                    $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages)
                        ->whereNull('deleted_at')
                        ->selectRaw('
                                            SUM(
                                            calculated_value +
                                                premium_passenger_liability +
                                                premium_unorthorised_passanger_liability +
                                                premium_parking_facilities +
                                                premium_com_windscreen +
                                                premium_riot_strike +
                                                premium_locks_keys +
                                                premium_wreckage_removal +
                                                premium_window_glass +
                                                premium_parts_accessories+
                                                premium_audio_accessories+
                                                premium_credit_shortfall+
                                                premium_car_hire_theft+
                                                premium_insured_driver+
                                                premium_insured_family+
                                                premium_medical_expenses+
                                                premium_specified_accessories+
                                                premium_third_party_liability
                                            ) as total_premium
                                        ')
                        ->first();
                }
                //old code commented by snehal on 28-10-25
                // $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages)->whereNull('deleted_at')->sum('calculated_value');

                $internal_motor_sum_calculated_value = MotorTradersInternal::whereIn('policy_coverage_id', $coverages)
                    ->selectRaw('
                        SUM(
                            loss_or_damage_calculated_value +
                            third_party_liability_calculated_value +
                            medical_benefits_calculated_value +
                            vehicle_lent_hire_calculated_value +
                            social_domestic_pleasure_calculated_value +
                            unauthoried_use_calculated_value +
                            windscreen_calculated_value +
                            contigent_liability_calculated_value +
                            wreckage_removal_calculated_value +
                            Loss_of_use_of_customer_calculated_value +
                            loss_of_key_calculated_value +
                            motor_cycle_motor_tricycle_calculated_value +
                            special_type_vehicle_calculated_value +
                            passanger_liability_respect_of_motor_calculated_value
                        ) as grand_total
                    ')
                    ->first();

                $external_motor_sum_calculated_value = MotorTraders::whereIn('policy_coverage_id', $coverages)
                    ->selectRaw('
                    SUM(
                        loss_or_damage_calculated_value +
                        third_party_liability_calculated_value +
                        medical_benefits_calculated_value +
                        vehicle_lent_hire_calculated_value +
                        social_domestic_pleasure_calculated_value +
                        unauthoried_use_calculated_value +
                        windscreen_calculated_value +
                        contigent_liability_calculated_value +
                        wreckage_removal_calculated_value +
                        Loss_of_use_of_customer_calculated_value +
                        loss_of_key_calculated_value +
                        motor_cycle_motor_tricycle_calculated_value +
                        special_type_vehicle_calculated_value +
                        passanger_liability_respect_of_motor_calculated_value
                    ) as grand_total
                ')
                    ->first();
                if ($external_motor_sum_calculated_value) {
                    $external_motor_sum_calculated_value = $external_motor_sum_calculated_value->grand_total ?? 0;
                }
                if ($internal_motor_sum_calculated_value) {
                    $internal_motor_sum_calculated_value = $internal_motor_sum_calculated_value->grand_total ?? 0;
                }
                if ($motor_sum_calculated_value) {
                    $motor_sum_calculated_value = $motor_sum_calculated_value->total_premium ?? 0;
                }

                $premiumNew = $sum_calculated_value + $sum_specified_items + $sum_exts_calculated_value + $motor_sum_calculated_value + $internal_motor_sum_calculated_value + $external_motor_sum_calculated_value + $policyCoveragesDataSum;
                // dd($sum_calculated_value, $sum_specified_items ,$sum_exts_calculated_value , $motor_sum_calculated_value , $internal_motor_sum_calculated_value , $external_motor_sum_calculated_value , $policyCoveragesDataSum);
            } else {
                $premiumNew = $sum_calculated_value + $sum_specified_items + $policyCoveragesDataSum + $sum_exts_calculated_value;

            }
           // dd($premiumNew);
            if($this->policy->product_id == 16){
                // Add CAR, EAR, PAR premiums
                $carEarParCoverages = PolicyCoverage::where('policy_id', $this->policy->id)
                    ->whereNull('deleted_at')
                    ->where('action_id', $this->actionId)
                    ->where('term_id', $this->termId)
                    ->whereNull('deleted_at')
                    ->where('status', '0')
                    ->with(['coverage', 'carCoverage', 'earCoverage', 'parCoverage'])
                    ->get();
                
                $carEarParPremium = $this->calculateCarEarParPremiums($carEarParCoverages);
                $premiumNew += $carEarParPremium;
            }
            if($this->policy->product_id == 17){
                $getMedicalTotal = MedicalMalpracticeCoverageModel::getMedicalTotal($this->policy->id);
                $getProfessionalIndemnityTotal = ProfessionalIndemnityCoverageModel::getProfessionalIndemnityTotal($this->policy->id);
                $getTravelTotal = TravelCoverageModel::getTravelTotal($this->policy->id);
                
                $premiumNew = $getMedicalTotal + $getProfessionalIndemnityTotal + $getTravelTotal;
                $proratapremium = 0;
               
                
            }
        }
        $this->action->premium = $proratapremium + $premiumNew;
        $this->action->save();
        $this->rateFLag = 1;
        
        // Check if CAR/PAR/EAR coverages with 24/36 month periods are approved
        $this->checkCarParEarApprovals();
        
        activity('Policy Rated')
            ->performedOn($this->policy)
            ->causedBy(auth()->user())
            ->log('Rate : P ' . number_format($this->action->premium ?? "", 2, '.', ','));
    }
    
    /**
     * Check if CAR, PAR, or EAR coverages with 24/36 month periods are approved
     * Sets showSubmitButtons flag to true:
     * - If no CAR/PAR/EAR coverages with 24/36 months exist, show buttons (no approval needed)
     * - If CAR/PAR/EAR coverages with 24/36 months exist, only show if all are approved
     */
    public function checkCarParEarApprovals()
    {
        // Get all policy coverages for this policy, action, and term
        $policyCoverages = PolicyCoverage::where('policy_id', $this->policy->id)
            ->where('action_id', $this->actionId)
            ->where('term_id', $this->termId)
            ->with(['carCoverage', 'earCoverage', 'parCoverage'])
            ->get();
        
        $hasCarParEarCoverageWithLongPeriod = false;
        $allApproved = true;
        
        foreach ($policyCoverages as $coverage) {
            // Check CAR coverage
            if ($coverage->carCoverage) {
                $policyPeriod = $coverage->carCoverage->policy_period_months ?? '';
                if (in_array($policyPeriod, ['24', '36'])) {
                    $hasCarParEarCoverageWithLongPeriod = true;
                    if (empty($coverage->carCoverage->approved_by)) {
                        $allApproved = false;
                        break;
                    }
                }
            }
            
            // Check EAR coverage
            if ($coverage->earCoverage) {
                $policyPeriod = $coverage->earCoverage->policy_period_months ?? '';
                if (in_array($policyPeriod, ['24', '36'])) {
                    $hasCarParEarCoverageWithLongPeriod = true;
                    if (empty($coverage->earCoverage->approved_by)) {
                        $allApproved = false;
                        break;
                    }
                }
            }
            
            // Check PAR coverage
            if ($coverage->parCoverage) {
                $policyPeriod = $coverage->parCoverage->policy_period_months ?? '';
                if (in_array($policyPeriod, ['24', '36'])) {
                    $hasCarParEarCoverageWithLongPeriod = true;
                    if (empty($coverage->parCoverage->approved_by)) {
                        $allApproved = false;
                        break;
                    }
                }
            }
        }
        
        // If no CAR/PAR/EAR coverage with 24/36 months exists, show buttons (no approval needed)
        // If CAR/PAR/EAR coverage with 24/36 months exists, only show if all are approved
        $this->showSubmitButtons = !$hasCarParEarCoverageWithLongPeriod || ($hasCarParEarCoverageWithLongPeriod && $allApproved);
    }
    public function calculatePremiumRenew($actionId)
    {
        // dd($actionId);
        // now store calculated values in policy_Action table
        $policyAction = PolicyAction::where('id', $actionId)->first();
        $policyTerm = PolicyTerm::where('id', $this->termId)
            //->where('status','Active')
            ->first();

        $policyActionPrev = PolicyAction::where('policy_id', '=', $policyAction->policy_id)
            ->where('id', '<', $actionId)
            ->where('transaction_type', '=', 'RENEW')
            ->where('effective_to', '=', $policyAction->effective_to)
            ->orderBy('id', 'desc')->take(1)
            ->first();
        $diff_in_days_new_coverage = 0;
        $diff_in_days_main = 0;
        $policyActionCnt = PolicyAction::where('policy_id', $policyAction->policy_id)->count();
        if ($policyActionCnt > 1) {
            if (!isset($policyActionPrev) || $policyActionPrev == NULL) {
                $policyActionPrev = PolicyAction::where('policy_id', '=', $policyAction->policy_id)
                    ->where('id', '<', $actionId)
                    ->where('transaction_type', '=', 'NEWBUSINESS')
                    ->orderBy('id', 'desc')->take(1)
                    ->first();
            }
            // $diff_in_days_main = Carbon::parse($policyTerm->term_end_date)->diffInDays(Carbon::parse($this->policy->term_start_date));
            if (isset($policyActionPrev) && $policyActionPrev != null) {
                $datetime1 = strtotime($policyActionPrev->effective_from); // convert to timestamps

                $datetime2 = strtotime($policyActionPrev->effective_to); // convert to timestamps
                $diff_in_days_main = (int) (($datetime2 - $datetime1) / 86400) + 1;
            }
        }
        if ($this->policy->term_start_date) {
            $fromDate = (new Carbon($this->policy->term_start_date))->format(config('constants.date.format'));
        } else {
            $fromDate = null;
        }

        if ($this->policy->expiry_date) {
            $toDate = (new Carbon($this->policy->expiry_date))->format(config('constants.date.format'));
            $anniversaryDate = (new Carbon($this->policy->expiry_date))->format(config('constants.date.format'));
        } else {
            $toDate = null;
            $anniversaryDate = null;
        }
        if ($policyAction->transaction_type == 'CANCEL') {
            $premium = 0;
            $diff_in_days_new_coverage = 0;
            $diff_in_days_main = 0;
            if ($this->policy->premium_freq == 3) {
                $policyActionPrev = PolicyAction::where('policy_id', '=', $this->policy->id)
                    ->whereIn('transaction_type', ['NEWBUSINESS', 'ANNIVERSARY-RENEW'])
                    ->orderBy('id', 'desc')->take(1)
                    ->first();
            } else if ($this->policy->premium_freq == 1 || $this->policy->premium_freq == 2 || $this->policy->premium_freq == 5) {
                $policyActionPrev = PolicyAction::where('policy_id', $this->policy->id)
                    ->whereIn('transaction_type', ['NEWBUSINESS', 'RENEW', 'ANNIVERSARY-RENEW'])
                    ->whereDate('effective_from', '<=', $policyAction->effective_from)
                    ->orderBy('effective_from', 'desc')
                    ->first();
            }
            $datetime1 = strtotime($policyActionPrev->effective_from); // convert to timestamps
            $datetime2 = strtotime($policyActionPrev->effective_to); // convert to timestamps
            $diff_in_days_main = (int) (($datetime2 - $datetime1) / 86400) + 1;

            $datetimeact = strtotime($policyAction->effective_from); // convert to timestamps
            $datetimeact2 = strtotime($policyAction->effective_to); // convert to timestamps
            $diff_in_days_new_coverage = (int) (($datetimeact2 - $datetimeact) / 86400) + 1;
            $fromDays = \Carbon\Carbon::parse($policyAction->effective_from);
            $toDays = \Carbon\Carbon::parse($policyAction->effective_to);
            $daysInMonth = $fromDays->daysInMonth;
            if ($fromDays->day == 1 && $toDays->day == $daysInMonth) {
                // Full month
                $premium = $policyActionPrev->premium;
            } else {
                $premium = ($policyActionPrev->premium) * ($diff_in_days_new_coverage / $diff_in_days_main);
            }
            //dd($premium);
            $endorsements = PolicyAction::where('policy_id', $this->policy->id)
                ->where('transaction_type', '=', 'ENDORSE')
                ->where('status', '=', 'ISSUED')
                ->orderBy('effective_from')
                ->get();

            // Exclude — do not delete — transactions effective on or after the
            // cancel date. See the matching note in calculatePremium(): the old
            // code soft-deleted ISSUED actions from inside a rating call.
            $excludePostCancel = function ($q) use ($policyAction) {
                $q->where(function ($w) use ($policyAction) {
                    $w->where('status', '!=', 'ISSUED')
                        ->orWhere('effective_from', '<', $policyAction->effective_from);
                });
            };

            $endorsementsMonth = PolicyAction::where('policy_id', $this->policy->id)
                ->whereIn('transaction_type', ['ENDORSE', 'ENDORSE-RENEW'])
                ->whereDate('effective_to', '=', $policyAction->effective_to)
                ->where($excludePostCancel)
                ->orderBy('effective_from')
                ->get();
            $refundPremium = 0;
            $endorsements = PolicyAction::where('policy_id', $this->policy->id)
                ->where('transaction_type', '=', 'ENDORSE')
                ->where($excludePostCancel)
                ->orderBy('effective_from')
                ->get();
            if ($this->policy->premium_freq == 3) {
                $refundPremium = $this->calculateRefundPremiumWithEndorsements(
                    $premium,
                    $policyActionPrev->effective_from,
                    $policyAction->effective_from,
                    $diff_in_days_new_coverage,
                    $endorsements->map(function ($endorsement) {
                        return [
                            'premium' => $endorsement->premium,
                            'effective_date' => $endorsement->effective_from,
                            'end_date' => $endorsement->effective_to
                        ];
                    })->toArray()
                );
            } else if ($this->policy->premium_freq == 1 || $this->policy->premium_freq == 5 || $this->policy->premium_freq == 2) {
                $refundPremium = $this->calculateMonthlyRefundPremiumWithEndorsements(
                    $premium,
                    $policyActionPrev->effective_from,
                    $policyAction->effective_from,
                    $diff_in_days_new_coverage,
                    $endorsementsMonth->map(function ($endorsement) {
                        return [
                            'premium' => $endorsement->premium,
                            'effective_date' => $endorsement->effective_from,
                            'end_date' => $endorsement->effective_to,
                            'id' => $endorsement->id
                        ];
                    })->toArray()
                );
            }
            $proratapremium = -$refundPremium;
            $premiumNew = 0;
        } else if ($policyAction->transaction_type == 'ENDORSE' && $policyAction->transaction_reason == 'COVERAGECANCEL') {

            $datetimeact = strtotime($policyAction->effective_from); // convert to timestamps
            $datetimeact2 = strtotime($policyAction->effective_to); // convert to timestamps
            $diff_in_days_new_coverage = (int) (($datetimeact2 - $datetimeact) / 86400) + 1;
            $coverages = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $actionId)->where('term_id', $this->termId)->where('status', '1')->pluck('id');
            $coverages_coverage_id = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $actionId)->where('term_id', $this->termId)->where('status', '1')->pluck('coverage_id');
            $personal_motor_coverages = PolicyCoverage::where(function ($query) {
                $query->where('coverage_id', 15)
                    ->orWhere('coverage_id', 16)
                    ->orWhere('coverage_id', 27)
                    ->orWhere('coverage_id', 22);
            })->where('policy_id', $this->policy->id)
                ->where('action_id', $actionId)
                ->where('term_id', $this->termId)
                ->first();
            $coverages_cancel = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $actionId)->where('term_id', $this->termId)->pluck('id');

            $premiumNew = 0;
            $proratapremium = 0;
            $proratapremiumMain = 0;
            $internal_motor_sum_calculated_value = 0;
            $external_motor_sum_calculated_value = 0;
            $motor_sum_calculated_value = 0;
            $sum_specified_items = PolicySpecifiedItem::whereIn('policy_coverage_id', $coverages)->sum('calculated_value');
            $sum_calculated_value = PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')->whereNull('tb_cvgpccoverages.policy_id')->whereIn('policy_coverage_id', $coverages)->sum('calculated_value');
            $sum_exts_calculated_value = DB::table('policy_extention_detail as ped')
                ->join('policy_coverages as p', function ($join) {
                    $join->on('p.id', '=', 'ped.policy_coverage_id')
                        ->on('ped.s_ParentCoverageID', '=', 'p.coverage_id');
                })
                ->whereIn('ped.policy_coverage_id', $coverages)
                ->where('ped.type', 'Extention')
                 ->where('p.action_id', $actionId)
                ->whereNull('p.deleted_at')
                ->whereNull('ped.deleted_at')
                ->where('ped.endors_flag', '1')
                 ->where('ped.previousActionIdCov', $actionId)
                ->sum('ped.pro_rate_premium');
                // See the COVERAGECANCEL note above: resolve cancelled Fidelity
                // (coverage_id 9) coverage ids on the default connection, then
                // sum on policy_coverages_data (mysql_system) — no cross-
                // connection join, which can silently return nothing.
                $cancelledFidelityCoverageIds = PolicyCoverage::withTrashed()
                    ->where('policy_id', $this->policy->id)
                    ->where('action_id', $actionId)
                    ->where('term_id', $this->termId)
                    ->where('coverage_id', 9)
                    ->where('status', '1')
                    ->whereNotNull('deleted_at')
                    ->pluck('id')
                    ->all();
                $policyCoveragesDataSum = empty($cancelledFidelityCoverageIds)
                    ? 0
                    : PolicyCoveragesData::whereIn('policyCoverageID', $cancelledFidelityCoverageIds)->sum('premium');
                // $this->action->premium = PolicyCoverageDetail::whereIn('policy_coverage_id', $coverages)->sum('calculated_value');

            if (!empty($personal_motor_coverages)) {
                if ($personal_motor_coverages->status == '0') {
                    $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages_cancel)->whereNotNull('deleted_at')->where('previousActionIdCov', $actionId)->sum('calculated_value');
                    $internal_motor_sum_calculated_value = MotorTradersInternal::whereIn('policy_coverage_id', $coverages)
                        ->get(array(DB::raw('SUM(loss_or_damage_calculated_value + third_party_liability_calculated_value + medical_benefits_calculated_value) as internal_motor_sum_calculated_value')))
                        ->toArray();
                    $external_motor_sum_calculated_value = MotorTraders::whereIn('policy_coverage_id', $coverages)
                        ->get(array(DB::raw('SUM(loss_or_damage_calculated_value + third_party_liability_calculated_value + medical_benefits_calculated_value) as external_motor_sum_calculated_value')))
                        ->toArray();
                    $internal_motor_sum_calculated_value = isset($internal_motor_sum_calculated_value[0]['internal_motor_sum_calculated_value']) ? $internal_motor_sum_calculated_value[0]['internal_motor_sum_calculated_value'] : 0;
                    $external_motor_sum_calculated_value = isset($external_motor_sum_calculated_value[0]['external_motor_sum_calculated_value']) ? $external_motor_sum_calculated_value[0]['external_motor_sum_calculated_value'] : 0;

                    $premiumNew = $sum_calculated_value + $sum_specified_items + $sum_exts_calculated_value + $motor_sum_calculated_value + $internal_motor_sum_calculated_value + $external_motor_sum_calculated_value + $policyCoveragesDataSum;
                } else if ($personal_motor_coverages->status == '1') {
                    $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages)->sum('calculated_value');
                    $internal_motor_sum_calculated_value = MotorTradersInternal::whereIn('policy_coverage_id', $coverages)
                        ->get(array(DB::raw('SUM(loss_or_damage_calculated_value + third_party_liability_calculated_value + medical_benefits_calculated_value) as internal_motor_sum_calculated_value')))
                        ->toArray();
                    $external_motor_sum_calculated_value = MotorTraders::whereIn('policy_coverage_id', $coverages)
                        ->get(array(DB::raw('SUM(loss_or_damage_calculated_value + third_party_liability_calculated_value + medical_benefits_calculated_value) as external_motor_sum_calculated_value')))
                        ->toArray();
                    $internal_motor_sum_calculated_value = isset($internal_motor_sum_calculated_value[0]['internal_motor_sum_calculated_value']) ? $internal_motor_sum_calculated_value[0]['internal_motor_sum_calculated_value'] : 0;
                    $external_motor_sum_calculated_value = isset($external_motor_sum_calculated_value[0]['external_motor_sum_calculated_value']) ? $external_motor_sum_calculated_value[0]['external_motor_sum_calculated_value'] : 0;

                    $premiumNew = $sum_calculated_value + $sum_specified_items + $sum_exts_calculated_value + $motor_sum_calculated_value + $internal_motor_sum_calculated_value + $external_motor_sum_calculated_value + $policyCoveragesDataSum;
                }
            } else {
                $premiumNew = $sum_calculated_value + $sum_exts_calculated_value + $sum_specified_items + $policyCoveragesDataSum;
            }
            $proratapremium = -(($diff_in_days_new_coverage) / ($diff_in_days_main)) * $premiumNew;

            $premiumNew = 0;
        } else if ($policyAction->transaction_type == 'ENDORSE' && $policyAction->transaction_reason != 'COVERAGECANCEL') {

            $datetimeact = strtotime($policyAction->effective_from); // convert to timestamps
            $datetimeact2 = strtotime($policyAction->effective_to); // convert to timestamps
            $diff_in_days_new_coverage = (int) (($datetimeact2 - $datetimeact) / 86400) + 1;
            // $diff_in_days_new_coverage = Carbon::parse($policyAction->effective_to)->diffInDays(Carbon::parse($policyAction->effective_from));
            $coverages = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $actionId)->where('term_id', $this->termId)->pluck('id');
            $coverages_coverage_id = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $actionId)->where('term_id', $this->termId)->where('endors_flag', '1')->pluck('coverage_id');
            $coverages1 = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $actionId)->where('term_id', $this->termId)->pluck('id');

            $personal_motor_coverages = PolicyCoverage::where(function ($query) {
                $query->where('coverage_id', 15)
                    ->orWhere('coverage_id', 16)
                    ->orWhere('coverage_id', 27)
                    ->orWhere('coverage_id', 22);
            })->where('policy_id', $this->policy->id)
                ->where('action_id', $actionId)
                ->where('term_id', $this->termId)
                ->first();

            $internal_motor_sum_calculated_value = 0;
            $external_motor_sum_calculated_value = 0;
            $motor_sum_calculated_value = 0;
            $sum_specified_items = PolicySpecifiedItem::whereIn('policy_coverage_id', $coverages)->where('action_id', $actionId)->sum('calculated_value');//->where('previousActionIdCov',$actionId) need yo addd this
            // added for deleted items on 28-10-25
            $sum_specified_items_deleted = PolicySpecifiedItem::onlyTrashed()
                ->whereIn('policy_coverage_id', $coverages)
                ->where('action_id', $actionId)
                ->sum('calculated_value');
            $sum_calculated_value = PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')->whereNull('tb_cvgpccoverages.policy_id')->whereIn('policy_coverage_id', $coverages)->where('endors_flag', '1')->where('previousActionIdCov', $actionId)->sum('pro_rate_premium');
            $sum_exts_calculated_value = DB::table('policy_extention_detail as ped')
                ->join('policy_coverages as p', function ($join) {
                    $join->on('p.id', '=', 'ped.policy_coverage_id')
                        ->on('ped.s_ParentCoverageID', '=', 'p.coverage_id');
                })
                ->whereIn('ped.policy_coverage_id', $coverages)
                ->where('ped.type', 'Extention')
                ->where('ped.endors_flag', '1')
                ->whereNull('ped.deleted_at')
                ->whereNull('p.deleted_at')
                ->where('ped.previousActionIdCov', $actionId)
                 ->where('p.action_id', $actionId)
                ->sum('ped.pro_rate_premium');
                $policyCoveragesDataSum = PolicyCoveragesData::join(
                    'policy_coverages',
                    'policy_coverages.id',
                    '=',
                    'policy_coverages_data.policyCoverageID'
                )
                ->where('policy_coverages.policy_id', $this->policy->id)
                ->where('policy_coverages.action_id', $actionId)
                 ->where('policy_coverages.coverage_id', 9)
                ->whereIn('policy_coverages_data.policyCoverageID', $coverages)
                ->where('policy_coverages_data.previousActionIdCov', $actionId)
                ->where('policy_coverages_data.endorsement_flag', '1')
                ->whereNull('policy_coverages.deleted_at')
                ->sum('policy_coverages_data.premium');           
     // $this->action->premium = PolicyCoverageDetail::whereIn('policy_coverage_id', $coverages)->sum('calculated_value');

            if (!empty($personal_motor_coverages)) {
                $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages1)->where('endors_flag', '1')->where('previousActionIdCov', $actionId)->whereNull('deleted_at')->sum('pro_rate_premium');
                $internal_motor_sum_calculated_value = MotorTradersInternal::whereIn('policy_coverage_id', $coverages1)->where('endors_flag', '1')->where('previousActionIdCov', $actionId)
                    ->get('pro_rate_premium')
                    ->toArray();
                $external_motor_sum_calculated_value = MotorTraders::whereIn('policy_coverage_id', $coverages1)->where('endors_flag', '1')->where('previousActionIdCov', $actionId)
                    ->get('pro_rate_premium')
                    ->toArray();
                $internal_motor_sum_calculated_value = isset($internal_motor_sum_calculated_value[0]['pro_rate_premium']) ? $internal_motor_sum_calculated_value[0]['pro_rate_premium'] : 0;
                $external_motor_sum_calculated_value = isset($external_motor_sum_calculated_value[0]['pro_rate_premium']) ? $external_motor_sum_calculated_value[0]['pro_rate_premium'] : 0;
                $proratapremiumMain = $sum_calculated_value + $sum_specified_items + $sum_specified_items_deleted + $sum_exts_calculated_value + $motor_sum_calculated_value + $internal_motor_sum_calculated_value + $external_motor_sum_calculated_value + $policyCoveragesDataSum;
            } else {
                $proratapremiumMain = $sum_calculated_value + $sum_specified_items + $sum_specified_items_deleted + $policyCoveragesDataSum + $sum_exts_calculated_value;
            }
            $proratapremium = (($diff_in_days_new_coverage) / ($diff_in_days_main)) * $proratapremiumMain;
            if ($sum_specified_items_deleted > 0) {
                $proratapremium = '-' . $proratapremium;
            }
            // dd( $proratapremium);
            $premiumNew = 0;
        } else {

            $coverages = PolicyCoverage::where('policy_id', $this->policy->id)->whereNull('deleted_at')->where('action_id', $actionId)->where('term_id', $this->termId)->whereNull('deleted_at')->where('status', '0')->pluck('id');
            $coverages_coverage_id = PolicyCoverage::where('policy_id', $this->policy->id)->whereNull('deleted_at')->where('action_id', $actionId)->where('term_id', $this->termId)->whereNull('deleted_at')->where('status', '0')->pluck('coverage_id');
            $personal_motor_coverages = PolicyCoverage::where(function ($query) {
                $query->where('coverage_id', 15)
                    ->orWhere('coverage_id', 16)
                    ->orWhere('coverage_id', 27)
                    ->orWhere('coverage_id', 22);
            })->where('policy_id', $this->policy->id)
                ->where('action_id', $actionId)
                ->where('term_id', $this->termId)
                ->where('status', '0')
                ->first();
            $premiumNew = 0;
            $proratapremium = 0;
            $proratapremiumMain = 0;
            $internal_motor_sum_calculated_value = 0;
            $external_motor_sum_calculated_value = 0;
            $motor_sum_calculated_value = 0;
            $sum_specified_items = PolicySpecifiedItem::whereIn('policy_coverage_id', $coverages)->whereNull('deleted_at')->sum('calculated_value');
            $sum_calculated_value = PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                 ->whereNull('tb_cvgpccoverages.policy_id')->whereIn('policy_coverage_id', $coverages)->whereNull('deleted_at')->sum('calculated_value');
            $sum_exts_calculated_value = DB::table('policy_extention_detail as ped')
            ->join('policy_coverages as p', function ($join) {
                $join->on('p.id', '=', 'ped.policy_coverage_id')
            ->on('ped.s_ParentCoverageID', '=', 'p.coverage_id');
            })
            ->whereIn('ped.policy_coverage_id', $coverages)->whereIn('ped.s_ParentCoverageID', $coverages_coverage_id)->where('ped.type', 'Extention')
            ->whereNull('ped.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.action_id', $actionId)
            ->sum('ped.extention_calculated_value');
            $policyCoveragesDataSum = PolicyCoveragesData::join('policy_coverages', 'policy_coverages.id', '=', 'policy_coverages_data.policyCoverageID')
                                    ->where('policy_coverages.policy_id', $this->policy->id)
                                    ->where('policy_coverages.coverage_id', 9)
                                    ->where('policy_coverages.action_id', $actionId)
                                    ->whereNull('policy_coverages.deleted_at')
                                    ->sum('policy_coverages_data.premium');
            // $this->action->premium = PolicyCoverageDetail::whereIn('policy_coverage_id', $coverages)->sum('calculated_value');
            if (!empty($personal_motor_coverages)) {

                // added by snehal on 28-10-25
                if ($coverages_coverage_id->contains(22)) {
                    $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages)
                        ->whereNull('deleted_at')
                        ->selectRaw('
                                                        SUM( calculated_value +
                                                            premium_passenger_liability +
                                                            premium_unorthorised_passanger_liability +
                                                            premium_parking_facilities +
                                                            premium_com_windscreen +
                                                            premium_riot_strike +
                                                            premium_locks_keys +
                                                            premium_wreckage_removal +
                                                            premium_credit_shortfall +
                                                            premium_third_party_liability
                                                        ) as total_premium
                                                    ')
                        ->first();

                }
                if ($coverages_coverage_id->contains(27)) {
                    $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages)
                        ->whereNull('deleted_at')
                        ->selectRaw('
                                            SUM(
                                            calculated_value +
                                                premium_passenger_liability +
                                                premium_unorthorised_passanger_liability +
                                                premium_parking_facilities +
                                                premium_com_windscreen +
                                                premium_riot_strike +
                                                premium_locks_keys +
                                                premium_wreckage_removal +
                                                premium_window_glass +
                                                premium_parts_accessories+
                                                premium_audio_accessories+
                                                premium_credit_shortfall+
                                                premium_car_hire_theft+
                                                premium_insured_driver+
                                                premium_insured_family+
                                                premium_medical_expenses+
                                                premium_specified_accessories+
                                                premium_third_party_liability
                                            ) as total_premium
                                        ')
                        ->first();
                }
                //old code commented by snehal on 28-10-25
                // $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages)->whereNull('deleted_at')->sum('calculated_value');

                $internal_motor_sum_calculated_value = MotorTradersInternal::whereIn('policy_coverage_id', $coverages)
                    ->selectRaw('
                        SUM(
                            loss_or_damage_calculated_value +
                            third_party_liability_calculated_value +
                            medical_benefits_calculated_value +
                            vehicle_lent_hire_calculated_value +
                            social_domestic_pleasure_calculated_value +
                            unauthoried_use_calculated_value +
                            windscreen_calculated_value +
                            contigent_liability_calculated_value +
                            wreckage_removal_calculated_value +
                            Loss_of_use_of_customer_calculated_value +
                            loss_of_key_calculated_value +
                            motor_cycle_motor_tricycle_calculated_value +
                            special_type_vehicle_calculated_value +
                            passanger_liability_respect_of_motor_calculated_value
                        ) as grand_total
                    ')
                    ->first();

                $external_motor_sum_calculated_value = MotorTraders::whereIn('policy_coverage_id', $coverages)
                    ->selectRaw('
                    SUM(
                        loss_or_damage_calculated_value +
                        third_party_liability_calculated_value +
                        medical_benefits_calculated_value +
                        vehicle_lent_hire_calculated_value +
                        social_domestic_pleasure_calculated_value +
                        unauthoried_use_calculated_value +
                        windscreen_calculated_value +
                        contigent_liability_calculated_value +
                        wreckage_removal_calculated_value +
                        Loss_of_use_of_customer_calculated_value +
                        loss_of_key_calculated_value +
                        motor_cycle_motor_tricycle_calculated_value +
                        special_type_vehicle_calculated_value +
                        passanger_liability_respect_of_motor_calculated_value
                    ) as grand_total
                ')
                    ->first();
                if ($external_motor_sum_calculated_value) {
                    $external_motor_sum_calculated_value = $external_motor_sum_calculated_value->grand_total ?? 0;
                }
                if ($internal_motor_sum_calculated_value) {
                    $internal_motor_sum_calculated_value = $internal_motor_sum_calculated_value->grand_total ?? 0;
                }
                if ($motor_sum_calculated_value) {
                    $motor_sum_calculated_value = $motor_sum_calculated_value->total_premium ?? 0;
                }

                $premiumNew = $sum_calculated_value + $sum_specified_items + $sum_exts_calculated_value + $motor_sum_calculated_value + $internal_motor_sum_calculated_value + $external_motor_sum_calculated_value + $policyCoveragesDataSum;
                // dd($sum_calculated_value, $sum_specified_items ,$sum_exts_calculated_value , $motor_sum_calculated_value , $internal_motor_sum_calculated_value , $external_motor_sum_calculated_value , $policyCoveragesDataSum);
            } else {
                $premiumNew = $sum_calculated_value + $sum_specified_items + $policyCoveragesDataSum + $sum_exts_calculated_value;

            }
        }
        PolicyAction::where('id', $actionId)
            ->update([
                'premium' => $premiumNew
            ]);
        activity('Policy Rated')
            ->performedOn($this->policy)
            ->causedBy(auth()->user())
            ->log('Rate : P ' . number_format($premiumNew ?? "", 2, '.', ','));
    }

    public function inApproval()
    {
        $user = auth()->user();
        $roles = $user->getRoleNames();
        $userRole = $roles->first();
        $role = Role::where('name', $userRole)->orderByDesc('id')->first();// roles table => super admin => rule_group = 3

        if (isset($role->rule_group)) {
            if (!empty($this->policy->id) && !empty($this->actionId) && !empty($this->termId)) {
                $policy = Policy::find($this->policy->id);
                $today = now()->format('Y-m-d');

                $validationRuleGroupIds = ValidationRuleGroupMasterDetail::where('n_PrValidationRuleGroupMasters_FK', $role->rule_group)
                    ->where('n_PrValidationRuleMasters_FK', '!=', 0)
                    ->pluck('n_PrValidationRuleMasters_FK');

                $validationRules = ValidationRuleMaster::whereIn('n_PrValidationRuleMaster_PK', $validationRuleGroupIds)
                    ->where('n_Product_FK', $policy->product_id)
                    ->where('d_EffectiveDateFrom', '<=', $today)
                    ->where('d_EffectiveDateTo', '>=', $today)
                    ->get();

                // $validationRules = ValidationRuleGroupMasterDetail::select('tb_prvalidationrulemasters.*')
                //                                             ->join('tb_prvalidationrulemasters', 'tb_prvalidationrulemasters.n_PrValidationRuleMaster_PK', '=', 'tb_prvalidationrulegroupdetails.n_PrValidationRuleMasters_FK')
                //                                             ->where('tb_prvalidationrulegroupdetails.n_PrValidationRuleGroupMasters_FK', $role->rule_group)
                //                                             ->where('tb_prvalidationrulegroupdetails.n_PrValidationRuleMasters_FK', '!=', 0)
                //                                             ->where('tb_prvalidationrulemasters.n_Product_FK', $policy->product_id)
                //                                             ->where('tb_prvalidationrulemasters.d_EffectiveDateFrom', '<=', $today)
                //                                             ->where('tb_prvalidationrulemasters.d_EffectiveDateTo', '>=', $today)
                //                                             ->get();

                $allErrors = [];
                foreach ($validationRules as $validationRule) {
                    $error_msg = $validationRule->s_ScreenErrorMsg; // Initialize $error_msg here
                    $validationRuleDetails = ValidationRuleDetail::where('n_PrValidationRuleMaster_FK', $validationRule->n_PrValidationRuleMaster_PK)
                        ->get();

                    if (count($validationRuleDetails) > 0) {
                        foreach ($validationRuleDetails as $validationRuleDetail) {
                            $reinsuranceGroupCoverage = ReinsuranceGroupCoverage::join('reinsurance_group', 'reinsurance_group_coverage.group_id', '=', 'reinsurance_group.id')
                                ->where('reinsurance_group.id', $validationRuleDetail->n_PrValidationCodeMasters_FK)
                                ->pluck('reinsurance_group_coverage.coverage_id');

                            $sub_coverages = PolicyCoverageDetail::whereIn('coverage_id', $reinsuranceGroupCoverage)->sum('calculated_value');

                            $formulaExpression = $validationRuleDetail->s_FormulaExpression;
                            $compareValue = $validationRuleDetail->s_CompareValue;
                            $compareValueBetween = $validationRuleDetail->s_CompareValueBetween;

                            if (($formulaExpression == '61') && ($sub_coverages == $compareValue)) {
                                array_push($allErrors, $error_msg);
                            } elseif (($formulaExpression == '60') && ($sub_coverages < $compareValue)) {
                                array_push($allErrors, $error_msg);
                            } elseif (($formulaExpression == '8804') && ($sub_coverages <= $compareValue)) {
                                array_push($allErrors, $error_msg);
                            } elseif (($formulaExpression == '62') && ($sub_coverages > $compareValue)) {
                                array_push($allErrors, $error_msg);
                            } elseif (($formulaExpression == '8805') && ($sub_coverages >= $compareValue)) {
                                array_push($allErrors, $error_msg);
                            } elseif (($formulaExpression == '8800') && ($sub_coverages != $compareValue)) {
                                array_push($allErrors, $error_msg);
                            } elseif (($formulaExpression == '8801') && (($sub_coverages <= $compareValue) && ($sub_coverages >= $compareValueBetween))) {
                                array_push($allErrors, $error_msg);
                            } elseif (($formulaExpression == '8802') && (($sub_coverages >= $compareValue) && ($sub_coverages <= $compareValueBetween))) {
                                array_push($allErrors, $error_msg);
                            }
                        }
                    }
                }

                if (!empty($allErrors)) {
                    $combinedErrors = implode("\n\n", $allErrors);
                    $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => $combinedErrors]);
                } else {
                    // start approve code
                    $coverages = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $this->actionId)->where('term_id', $this->termId)->pluck('id');
                    $sum = PolicyCoverageDetail::whereIn('policy_coverage_id', $coverages)->sum('coverage_value');

                    $userRole = Auth()->user()->roles[0]->id;

                    $discountSurcharge = DiscountSurcharge::where('role', $userRole)->first(['sumInsured']);

                    // if($discountSurcharge->sumInsured != null && $discountSurcharge->sumInsured < $sum){
                    //     // return view('v2.livewire.Modals.MyExampleModal');
                    //     $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'You cannot submit policy greater than sum insured of '.$discountSurcharge->sumInsured.' . Please contact Admin for more details.']);
                    // }else{ code comment because facing issue of suminssured null
                    $this->action->status = "IN_APPROVAL";
                    $this->action->save();
                    $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Process In Approval']);
                    //}
                    // end approve code
                }
            }
        } else {
            // start approve code
            $coverages = PolicyCoverage::where('policy_id', $this->policy->id)->where('action_id', $this->actionId)->where('term_id', $this->termId)->pluck('id');
            $sum = PolicyCoverageDetail::whereIn('policy_coverage_id', $coverages)->sum('coverage_value');
            // dd($sum);
            $userRole = Auth()->user()->roles[0]->id;
            $discountSurcharge = DiscountSurcharge::where('role', $userRole)->first(['sumInsured']);

            if ($discountSurcharge != NULL && $discountSurcharge->sumInsured != null && $discountSurcharge->sumInsured < $sum) {
                // return view('v2.livewire.Modals.MyExampleModal');
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'You cannot submit policy greater than sum insured of ' . $discountSurcharge->sumInsured . ' . Please contact Admin for more details.']);
            } else {
                $this->action->status = "IN_APPROVAL";
                $this->action->save();
                $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Process In Approval']);
            }
            // end approve code
        }

        activity('Policy In Approval')
            ->performedOn($this->policy)
            ->causedBy(auth()->user())
            ->log('Status : ' . $this->action->status);
    }

    public function submitToApproval()
    {
        $this->action->status = 'APPROVED';
        $this->action->save();
        $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Approval submited Successfully']);
        $this->emitUp('refreshParent');

        activity('Policy Approved')
            ->performedOn($this->policy)
            ->causedBy(auth()->user())
            ->log('Status : ' . $this->action->status);
    }

    public function submitToReject()
    {
        $this->action->status = 'REJECTED';
        $this->action->save();
        $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Rejected Successfully']);

        activity('Policy Rejected')
            ->performedOn($this->policy)
            ->causedBy(auth()->user())
            ->log('Status : ' . $this->action->status);
    }

    public function issuePolicy()
    {
         ini_set('max_execution_time', 0);
        set_time_limit(0);

        \DB::transaction(function () {
            
            if ($this->action->status == 'APPROVED') {
                if ($this->action->transaction_type != "CANCEL") {
                    //$this->calculatePremium();
                }

                // proRataPremium Main Coverage & Risk Address
                $policy_coverages = PolicyCoverage::Policy($this->policy->id)->Term($this->termId)->Action($this->actionId)->orderBy('risk_address_id', 'asc')->orderBy('coverage_id', 'asc')->get();
                $total_coverage_prorated_premium = 0;
                
                foreach ($policy_coverages as $coverages) {
                    $total_calculated_prorated_premium = 0;
                    foreach ($coverages->coverageDetail as $newIndex => $sub_coverages) {
                        if ($coverages->row_type == 'NEW' && $this->action->transaction_type != 'NEWBUSINESS') {
                            $start_date = Carbon::parse($this->action->effective_from);
                            $end_date = Carbon::parse($this->action->effective_to);
                            $premium = round(($sub_coverages->calculated_value) / 12, 2);
                            $dateDiff = $start_date->diffInDays($end_date);
                            $proRataPremium = round(($premium / 30.4375) * $dateDiff, 2); //30.4375 is an average days in month for a year
                            //  $sub_coverages->pro_rate_premium = $proRataPremium;
                            $sub_coverages->save();
                            $sub_coverages->proRataPremium = $proRataPremium;
                            $total_calculated_prorated_premium = $total_calculated_prorated_premium + $proRataPremium;
                        } else {
                            $sub_coverages->proRataPremium = 0;
                        }
                    }
                    if ($coverages->row_type == 'NEW' && $this->action->transaction_type != 'NEWBUSINESS') {
                        $total_coverage_prorated_premium = $total_coverage_prorated_premium + $total_calculated_prorated_premium;
                        $coverages->total_calculated_prorated_premium = $total_calculated_prorated_premium;
                    } else {
                        $coverages->total_calculated_prorated_premium = 0;
                    }
                }

                $regionVat = Region::where('id', 7)->first('vat')?->vat;
                $policyActivatedDate = Carbon::now()->format('Y-m-d');
                $anual_premium = $this->action->premium;
             
                // Issuing a transaction is what owns policies.status:
                //   CANCEL / EXPIRE  -> 2  cover has ended, policy is NOT active
                //   everything else  -> 1  NEWBUSINESS, RENEW, ANNIVERSARY-RENEW,
                //                          ENDORSE, REINSTATE, REISSUE all mean
                //                          cover is in force.
                // EXPIRE was previously missing and fell through to 1, so
                // issuing an expiry marked the policy ACTIVE. It is offered
                // straight off NEWBUSINESS / REINSTATE / REISSUE in the
                // transaction dropdown (AddTransaction transition map), so it
                // is reachable. Keep in lockstep with the V2 API issue path
                // (Api/V1/PolicyCreateController::issuePolicy).
                if (in_array($this->action->transaction_type, ['CANCEL', 'EXPIRE'], true)) {
                    $this->policy->status = 2; // cover ended - cancelled / expired
                } else {
                    $this->policy->status = 1; // activate policy
                }
                // ── Which POLICY-level columns this transaction may move ─────
                // Identical guard to the V2 API issue path
                // (Api/V1/PolicyCreateController::issuePolicy) — keep the two in
                // lockstep. Previously every column below was written for EVERY
                // transaction type, so issuing an ENDORSE / CANCEL / mid-term
                // RENEW re-stamped the policy's term window, billing anchors,
                // activation date and premium from that one action — and an
                // endorse premium is a PRO-RATA DELTA, not a premium. See the
                // full rationale (and the COMG2024127810 example) on the API copy.
                $txType     = $this->action->transaction_type;
                // Periodic = billed in slices SHORTER than the policy year, so a
                // RENEW is the next billing slice rather than a new term.
                //   1 MONTHLY · 2 3-INSTALMENT · 4 SEMIANNUAL · 5 QUARTERLY
                // 3 (ANNUAL) is deliberately absent: on an annual policy a RENEW
                // IS a new year and must be allowed to move the term window.
                // An empty premium_freq counts as monthly — that is how the rest
                // of the codebase reads it (PolicyLedger.php treats
                // `premium_freq == 1 || NULL || ''` as one case), and without it
                // (int) null = 0 falls through as "annual" and a RENEW re-stamps
                // the term — the exact bug this guard exists to prevent.
                // 6 (MANUAL INPUT) is left out pending a business answer.
                $isPeriodic = in_array((int) $this->policy->premium_freq, [1, 2, 4, 5], true)
                    || empty($this->policy->premium_freq);

                $establishesTerm = in_array($txType, ['NEWBUSINESS', 'ANNIVERSARY-RENEW'], true)
                    || ($txType === 'RENEW' && !$isPeriodic);
                $fullPeriodPremium = in_array($txType, ['NEWBUSINESS', 'ANNIVERSARY-RENEW', 'RENEW'], true);

                $this->policy->premium_freq = $this->policy->premium_freq;

                if ($fullPeriodPremium) {
                    $this->policy->premium = $anual_premium;//$premium;
                    $this->policy->annual_premium = $anual_premium;
                    $this->policy->vat = $regionVat;
                    $this->policy->vat_percent = $this->action->vat_percent;
                    // Current billing-period anchor — advances with every full
                    // period (including a monthly/quarterly RENEW).
                    $this->policy->billingStartDate = $this->action->effective_from;
                }

                if ($establishesTerm) {
                    $this->policy->term_start_date = $this->action->effective_from;
                    $this->policy->term_end_date = $this->action->effective_to;
                    $this->policy->expiry_date = Carbon::parse($this->action->effective_from)->addYear(1)->subDay(1)->format('Y-m-d');
                }

                // Write-once columns — never overwritten by a later transaction.
                if ($txType === 'NEWBUSINESS' || empty($this->policy->ori_billingStartDate)) {
                    $this->policy->ori_billingStartDate = $this->action->effective_from;
                }
                if (in_array($txType, ['NEWBUSINESS', 'REINSTATE', 'REISSUE'], true) || empty($this->policy->policyActivatedDate)) {
                    $this->policy->policyActivatedDate = $policyActivatedDate;
                }

                $this->policy->save();
               
                $this->action->status = "ISSUED"; // make issued status in policy-action
                $this->action->save();
                // Invoice Create after issued
                $invoiceTypes = ['Invoice', 'Invoice VAT', 'Invoice Premium'];
                // Step 1: Find and delete existing invoices for the given policy and action
                Ledger::where('policy_id', $this->policy->id)
                    ->where('action_id', $this->actionId)
                    ->whereIn('trans_type', $invoiceTypes)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => now()]);
                
                // Step 2: Always generate a new invoice
                if ($anual_premium != 0) {
                    
                    Helper::generateInvoiceDomComIssued(
                        $this->policy->id,
                        $this->actionId,
                        $this->action->effective_from
                    );
                }
                
                // ── Post-CANCEL cleanup ──────────────────────────────────
                // Cover has ended: no RENEW / ANNIVERSARY-RENEW may exist after
                // this cancel. Identical call to the V2 API issue path
                // (Api/V1/PolicyCreateController::issuePolicy) — all rules live
                // in the service. Try/catch so a cleanup failure never rolls
                // back the cancel; the nightly `policy:cleanup-after-cancel`
                // sweep re-checks the policy anyway.
                if ($this->action->transaction_type == "CANCEL") {
                    try {
                        $cleanup = (new \AlphaDirect\Services\Cancel\PostCancelCleanupService())
                            ->cleanupAfterIssuedCancel($this->action);
                        \Log::info('Submit::issuePolicy: post-cancel cleanup', $cleanup);
                    } catch (\Throwable $e) {
                        \Log::error('Submit::issuePolicy: post-cancel cleanup failed: ' . $e->getMessage(), [
                            'policy_id' => $this->policy->id,
                            'action_id' => $this->actionId,
                        ]);
                    }
                }

                // Back-dated ENDORSE *and* back-dated RENEW both propagate
                // forward to later batches (next RENEWs + the ANNIVERSARY-RENEW
                // quote) by date. Additive/idempotent — a normal forward renew
                // with no later batches yet is a harmless no-op.
                if ($this->action->transaction_type == "ENDORSE" || $this->action->transaction_type == "RENEW") {

                    $invoiceTypes = ['Invoice', 'Invoice VAT', 'Invoice Premium'];
                    $trans_type_ann = $this->action->transaction_type;
                    if ($trans_type_ann == "ANNIVERSARY-RENEW" || $trans_type_ann == "ENDORSE" || $trans_type_ann == "RENEW" || $trans_type_ann == "NEWBUSINESS") {

                        //if($this->action->transaction_type == "ANNIVERSARY-RENEW"){

                        // added by snehal for anniversary renew - replica

                        // TARGETS strictly after this action in
                        // (effective_from, id) order — same rule as the V2 API
                        // (PolicyAction::applyForwardWindow). The old `>=` on
                        // the date alone also matched SAME-DATE actions with a
                        // LOWER id, pushing this action's data backward into
                        // earlier same-day endorsements.
                        $annRenewExists = PolicyAction::query()
                            ->where('policy_id', $this->action->policy_id)
                            ->tap(fn ($q) => PolicyAction::applyForwardWindow(
                                $q,
                                $this->action,
                                \Carbon\Carbon::parse($this->action->effective_from)->toDateString()
                            ))
                            ->whereIn('transaction_type', ['RENEW', 'ANNIVERSARY-RENEW', 'ENDORSE'])
                            ->whereNull('deleted_at')
                            ->where('id', '!=', $this->action->id)
                            //  ->where('id',28822)
                            ->tap(fn ($q) => PolicyAction::applyChronoOrder($q))
                            ->get();
                        // dd($annRenewExists);
                        if ($annRenewExists->isNotEmpty()) {

                            foreach ($annRenewExists as $renewAction) {
                                // Skip if the same record
                                if ($renewAction->id == $this->action->id) {
                                    continue;
                                }

                                // Call replace method for each record
                                PolicyAction::newPolicyActionReplace($renewAction, $this->action->id);
                                if ($renewAction->transaction_type == "RENEW") {
                                    $invoiceTypes = ['Invoice', 'Invoice VAT', 'Invoice Premium'];

                                    // Soft delete related ledger entries
                                    Ledger::where('policy_id', $this->policy->id)
                                        ->where('action_id', $renewAction->id)
                                        ->whereIn('trans_type', $invoiceTypes)
                                        ->whereNull('deleted_at')
                                        ->update(['deleted_at' => now()]);

                                    //   Recalculate and regenerate invoice
                                    $this->calculatePremiumRenew($renewAction->id);
                                    $path = Helper::generateInvoiceDomComIssued(
                                        $this->policy->id,
                                        $renewAction->id,
                                        $renewAction->effective_from
                                    );
                                }
                            }
                        }
                        // till  this by snehal 
                        //commneted on 12-11-2025 for adding refersh functionality
                        // $tempRateArr = [];
                        // $dateExists = PolicyAction::where('policy_id', $this->action->policy_id)
                        //     ->where('effective_from', '>', $this->action->effective_from)
                        //     ->whereIn('transaction_type', ['RENEW', 'ANNIVERSARY-RENEW']) // ✅ updated
                        //     ->whereNull('deleted_at')
                        //     ->orderBy('effective_from')
                        //     ->get()
                        //     ->groupBy('effective_from');

                        // foreach ($dateExists as $groupedActions) {
                        //     foreach ($groupedActions as $previousAction) {
                        //         PolicyAction::where('id', $previousAction->id)->delete();
                        //         policyCoverage::where('action_id', $previousAction->id)->delete();

                        //         $invoiceTypes = ['Invoice', 'Invoice VAT', 'Invoice Premium'];

                        //         Ledger::where('policy_id', $this->policy->id)
                        //             ->where('action_id', $previousAction->id)
                        //             ->whereIn('trans_type', $invoiceTypes)
                        //             ->whereNull('deleted_at')
                        //             ->update(['deleted_at' => now()]);
                        //         $status = $previousAction->transaction_type == "RENEW" ? "ISSUED" : 'QUOTE';
                        //         $policyActionIds = PolicyAction::create([
                        //             'policy_id' => $previousAction->policy_id,
                        //             'term_id' => $previousAction->term_id,
                        //             'transaction_type' => $previousAction->transaction_type, // maintain original type (RENEW or ANNIVERSARY-RENEW)
                        //             'policy_quote_no' => $previousAction->policy_quote_no,
                        //             'effective_from' => $previousAction->effective_from,
                        //             'effective_to' => $previousAction->effective_to,
                        //             'status' => $status,
                        //             'note' => $this->action->note,
                        //             'transaction_date' => $previousAction->transaction_date,
                        //         ]);

                        //         PolicyAction::newPolicyAction($policyActionIds, $this->action->id);
                        //     }
                        // }
                        //}
                    }
                }
                // $tempRateArr = PolicyAction::where('id', '>', $this->action->id)->where('policy_id', $this->policy->id)->where('transaction_type', '=', 'RENEW')->get();

                // foreach ($tempRateArr as $tempRate) {
                //     $this->calculatePremiumRenew($tempRate->id);
                //     $path = Helper::generateInvoiceDomComIssued($this->policy->id, $tempRate->id, $tempRate->effective_from);
                // }
                activity('Policy Issued')
                    ->performedOn($this->policy)
                    ->causedBy(auth()->user())
                    ->log('Status : ' . $this->action->status);

                // Commented code only for testing cause s3 not working
                // if(env('APP_ENV') != 'local') {
                //     $d = new DocumentController();
                //     if ($this->customer->email != null ) {
                //         if ($this->policy->product_id != 3) {
                //             $isGenerated = $d->generatePolicyDocument($this->policy->id);
                //             if ($isGenerated != null) {
                //                 $sent = $d->sendPolicyDocument($this->policy->id, "Agent");
                //             }
                //         }
                //         activity('Policy document sent on mail')
                //         ->performedOn($this->policy)
                //         ->causedBy(auth()->user())
                //         ->log('Customer Email : '.$this->customer->email);
                //     }
                // }
                $todays_date = Carbon::now()->format('Y-m-d');

                if ($this->action->transaction_type == 'NEWBUSINESS') {
                    //$path = Helper::generateInvoiceDomComIssued($this->policy->id,$this->actionId,$this->action->effective_from);
                } else {
                    // $path = Helper::addInvoiceToLedger($this->policy->id,$this->termId,$this->actionId,$todays_date);
                }
                // $PolicyReinsuranceCalculations = PolicyCoverage::getReinsuranceCoverageCalculations($this->policy->id,$this->termId,$this->actionId);
                // $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Policy Reinsurance Calculations are Created Successfully']);
                // return Redirect::route('policy.edit', [\Crypt::encrypt($this->policy->id)]);
            } else {
                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'Please Approve the Policy to Issue']);
            }
        });
        $this->emitUp('refreshParent', $this->actionId);
    }

    public function refreshEndorse()
    {
         ini_set('max_execution_time', 0);
        set_time_limit(0);

        $invoiceTypes = ['Invoice', 'Invoice VAT', 'Invoice Premium'];
        $trans_type_ann = $this->action->transaction_type;
        // dd($trans_type_ann,$this->action->id);
        if ($trans_type_ann == "ANNIVERSARY-RENEW" || $trans_type_ann == "ENDORSE" || $trans_type_ann == "RENEW" || $trans_type_ann == "NEWBUSINESS") {

            // SOURCE = latest ISSUED action by id (any type — ENDORSE /
            // back-dated RENEW …); it holds the newest coverage truth. The
            // selected action is NOT the source: an operator sitting on the
            // ANNIVERSARY-RENEW QUOTE (cron-generated later from pre-endorse
            // NEWBUSINESS data) must still pull the endorse's vehicle in.
            $source = PolicyAction::where('policy_id', $this->action->policy_id)
                ->where('status', 'ISSUED')
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first();

            // TARGETS by date ("as per date"): every batch effective on/after
            // the source — later RENEW batches + the ANNIVERSARY-RENEW quote.
            // …and SAME-DATE batches by action id: an action sharing the
            // source's effective date is a target only when its id is higher
            // (transacted later). Mirrors PolicyAction::applyForwardWindow used
            // by the V2 refresh runner.
            $annRenewExists = $source
                ? PolicyAction::query()
                    ->where('policy_id', $this->action->policy_id)
                    ->tap(fn ($q) => PolicyAction::applyForwardWindow(
                        $q,
                        $source,
                        \Carbon\Carbon::parse($source->effective_from)->toDateString()
                    ))
                    ->where('id', '!=', $source->id)
                    ->whereNull('deleted_at')
                    ->tap(fn ($q) => PolicyAction::applyChronoOrder($q))
                    ->get()
                : collect();

            if ($source && $annRenewExists->isNotEmpty()) {

                foreach ($annRenewExists as $renewAction) {
                    // Skip if the same record
                    if ($renewAction->id == $source->id) {
                        continue;
                    }

                    // Replicate-if-missing: additive, preserves existing
                    // anniversary edits, only appends the missing endorse rows.
                    PolicyAction::newPolicyActionReplace($renewAction, $source->id);
                    if ($renewAction->transaction_type == "RENEW") {

                        $invoiceTypes = ['Invoice', 'Invoice VAT', 'Invoice Premium'];

                        // Soft delete related ledger entries
                        Ledger::where('policy_id', $this->policy->id)
                            ->where('action_id', $renewAction->id)
                            ->whereIn('trans_type', $invoiceTypes)
                            ->whereNull('deleted_at')
                            ->update(['deleted_at' => now()]);

                        //   Recalculate and regenerate invoice
                        $this->calculatePremiumRenew($renewAction->id);
                        $path = Helper::generateInvoiceDomComIssued(
                            $this->policy->id,
                            $renewAction->id,
                            $renewAction->effective_from
                        );
                    }
                }
            }

        }
        $this->emitUp('refreshParent', $this->actionId);
        activity('Policy Refreshed')
            ->performedOn($this->policy)
            ->causedBy(auth()->user())
            ->log('Status : ' . $this->action->id);
    }
    public function generateInvoice()
    {

        try {

            $today = Carbon::now()->format('d');//Carbon::today();            
            $date = Carbon::now()->format('Y-m-d');
            //whereDay('created_at', $today) 
            $policiesbyCreteated = Policy::
                whereIn('product_id', [7, 8])
                ->where('status', 1)
                ->where('id', '=', $this->policy->id)
                ->select(array('id', 'customer_id', 'product_id', 'plan_id', 'premium_freq', 'created_at', 'updated_at', 'first_premium', 'annual_premium', 'premium', 'vat', 'vat_percent', 'policyNumber', 'policyActivatedDate', 'is_sys_act_generated', 'billingStartDate', 'status'))
                ->get();
            //whereDay('billingStartDate', $today) 
            $policiesbyBilled = Policy::
                whereIn('product_id', [7, 8])
                ->where('status', 1)
                ->where('id', $this->policy->id)
                ->select(array('id', 'customer_id', 'product_id', 'plan_id', 'premium_freq', 'created_at', 'updated_at', 'first_premium', 'annual_premium', 'premium', 'vat', 'vat_percent', 'policyNumber', 'policyActivatedDate', 'is_sys_act_generated', 'billingStartDate', 'status'))
                ->get();

            $merged = $policiesbyBilled->merge($policiesbyCreteated)->collect();
            $policies = $merged->chunk(1000);

            if ($policies->isEmpty()) {
                Log::info('Invoices data Not found');
                $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Invoices Data not found!']);
            } else {
                foreach ($policies as $records) {
                    foreach ($records as $policy) {
                        $policy_id = $policy->id;

                        $policyAction = PolicyAction::where('policy_id', $policy_id)->where('id', $this->actionId)->where('status', 'ISSUED')->get();
                        foreach ($policyAction as $policyActions) {
                            $termId = isset($policyActions->term_id) ? $policyActions->term_id : 0;
                            $actionId = isset($policyActions->id) ? $policyActions->id : 0;

                            $check_invoice_exists = Ledger::where('policy_id', $policy_id)
                                ->where('action_id', $policyActions->id)
                                ->where('trans_type', 'Invoice')
                                // ->where('invoice_date', $date)
                                ->count();
                            if ($check_invoice_exists == 0 && ($policy->premium_freq == 1 || $policy->premium_freq == 2 || $policy->premium_freq == 3 || $policy->premium_freq == 5)) {
                                $ledger = Ledger::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                                $ledger_count = Ledger::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                                // if($ledger == NULL)
                                // {
                                //     $ledger = LedgerArchive::where('policy_id', $policy_id)->orderBy('id', 'DESC')->where('trans_type', 'Invoice')->first(array('invoice_no', 'banking_id', 'invoice_date'));
                                //     $ledger_count = LedgerArchive::where('policy_id', $policy_id)->where('trans_type', 'Invoice')->count();
                                //     //$created_date = Carbon::parse($ledger->invoice_date)->addMonthsNoOverflow()->format('Y-m-d');.
                                // }
                                $policy = Policy::where('id', $policy_id)->first();
                                if ($ledger != NULL) {
                                    $invoice_no = $ledger->invoice_no;
                                    $invoice_no++;
                                    $banking_id = $ledger->banking_id;

                                } else {
                                    $invoice_no = $policy->policyNumber . '-' . sprintf('%03d', 1);
                                    $banking_id = CustomerBanking::where('customer_id', $policy->customer_id)->first(array('id'));
                                    if ($banking_id != NULL)
                                        $banking_id = $banking_id->id;
                                    else
                                        $banking_id = NULL;
                                }
                                $balance = 0;
                                $balance = Ledger::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                                if ($balance != null) {
                                    $balance = $balance->balance;
                                } else {
                                    //$balance = LedgerArchive::where('policy_id', $policy->id)->orderBy('id', 'DESC')->first(array('balance'));
                                    if ($balance != null) {
                                        $balance = $balance->balance;
                                    } else {
                                        $balance = 0;
                                    }
                                }
                                $data = array();
                                $record = array();
                                $subData = array();
                                $subRecord = array();

                                //-------------------------------PREMIUM--------------------------------------
                                $record['customer_id'] = $policy->customer_id;
                                $record['account_id'] = NULL;
                                $record['policy_id'] = $policy->id;
                                $record['term_id'] = $termId;
                                $record['action_id'] = $actionId;
                                $record['claim_id'] = NULL;
                                $record['banking_id'] = $banking_id;
                                $record['account_name'] = NULL;
                                $record['accounting_date'] = Carbon::parse($date);
                                $record['trans_type'] = 'Invoice Premium';
                                $record['amount_type'] = NULL;
                                $record['trans_ref'] = NULL;
                                $record['orig_trans'] = NULL;
                                $record['unallocated'] = NULL;
                                $record['system_date'] = Carbon::parse($date);
                                $record['trans_sub_type'] = NULL;
                                $record['eff_date'] = Carbon::parse($date);
                                $record['invoice_file'] = NULL;
                                $record['invoice_date'] = NULL;
                                $record['invoice_no'] = NULL;
                                $record['invoice_amount'] = NULL;
                                $record['premium'] = $policyActions->premium;
                                $record['other_charges'] = NULL;
                                $record['due_amount'] = NULL;
                                $record['pmts_adjust'] = NULL;
                                $record['due_date'] = NULL;
                                $record['status'] = 'Pending';
                                $record['credit'] = NULL;

                                $amt = str_replace(',', '', number_format(((float) $policyActions->premium - (float) $policy->vat), 2));
                                $record['debit'] = str_replace(',', '', $amt);
                                $balance = number_format(((float) str_replace(',', '', $balance) - (float) str_replace(',', '', $amt)), 2);
                                $record['balance'] = str_replace(',', '', $balance);

                                $data[] = $record;


                                //----SUB-LEDGER
                                $subRecord['customer_id'] = $policy->customer_id;
                                $subRecord['account_id'] = NULL;
                                $subRecord['policy_id'] = $policy->id;
                                $subRecord['term_id'] = $termId;
                                $subRecord['action_id'] = $actionId;
                                $subRecord['claim_id'] = NULL;
                                $subRecord['banking_id'] = $banking_id;
                                $subRecord['account_name'] = 'Insurance Sales A/C';
                                $subRecord['accounting_date'] = Carbon::parse($date);
                                $subRecord['trans_type'] = 'Insurance Premium';
                                $subRecord['trans_ref'] = NULL;
                                $subRecord['system_date'] = Carbon::parse($date);
                                $subRecord['credit'] = $amt;
                                $subRecord['debit'] = NULL;

                                $subData[] = $subRecord;

                                //-------------------------------PREMIUM--------------------------------------
                                //-------------------------------VAT--------------------------------------

                                $record = array();

                                $record['customer_id'] = $policy->customer_id;
                                $record['account_id'] = NULL;
                                $record['policy_id'] = $policy->id;
                                $record['term_id'] = $termId;
                                $record['action_id'] = $actionId;
                                $record['claim_id'] = NULL;
                                $record['banking_id'] = $banking_id;
                                $record['account_name'] = NULL;
                                $record['accounting_date'] = Carbon::parse($date);
                                $record['trans_type'] = 'Invoice VAT';
                                $record['amount_type'] = NULL;
                                $record['trans_ref'] = NULL;
                                $record['orig_trans'] = NULL;
                                $record['unallocated'] = NULL;
                                $record['system_date'] = Carbon::parse($date);
                                $record['trans_sub_type'] = NULL;
                                $record['eff_date'] = Carbon::parse($date);
                                $record['invoice_file'] = NULL;
                                $record['invoice_date'] = NULL;
                                $record['invoice_no'] = NULL;
                                $record['invoice_amount'] = NULL;
                                $record['premium'] = $policyActions->premium;
                                $record['other_charges'] = NULL;
                                $record['due_amount'] = NULL;
                                $record['pmts_adjust'] = NULL;
                                $record['due_date'] = NULL;
                                $record['status'] = 'Pending';
                                $record['credit'] = NULL;

                                $amt = floatval($policy->vat);

                                $record['debit'] = str_replace(',', '', $amt);
                                $balance = str_replace(',', '', number_format(((float) str_replace(',', '', $balance) - (float) str_replace(',', '', $amt)), 2));
                                $record['balance'] = str_replace(',', '', $balance);

                                $data[] = $record;

                                //----SUB-LEDGER

                                $subRecord = array();

                                $subRecord['customer_id'] = $policy->customer_id;
                                $subRecord['account_id'] = NULL;
                                $subRecord['policy_id'] = $policy->id;
                                $subRecord['term_id'] = $termId;
                                $subRecord['action_id'] = $actionId;
                                $subRecord['claim_id'] = NULL;
                                $subRecord['banking_id'] = $banking_id;
                                $subRecord['account_name'] = 'VAT Control A/C';
                                $subRecord['accounting_date'] = Carbon::parse($date);
                                $subRecord['trans_type'] = 'VAT on Insurance Premium';
                                $subRecord['trans_ref'] = NULL;
                                $subRecord['system_date'] = Carbon::parse($date);
                                $subRecord['credit'] = $amt;
                                $subRecord['debit'] = NULL;

                                $subData[] = $subRecord;

                                //-------------------------------VAT--------------------------------------
                                //-------------------------------INVOICE--------------------------------------

                                $record = array();
                                $record['customer_id'] = $policy->customer_id;
                                $record['account_id'] = NULL;
                                $record['policy_id'] = $policy->id;
                                $record['term_id'] = $termId;
                                $record['action_id'] = $actionId;
                                $record['claim_id'] = NULL;
                                $record['banking_id'] = $banking_id;
                                $record['account_name'] = NULL;
                                $record['accounting_date'] = Carbon::parse($date);
                                $record['trans_type'] = 'Invoice';
                                $record['amount_type'] = NULL;
                                $record['trans_ref'] = NULL;
                                $record['orig_trans'] = NULL;
                                $record['unallocated'] = NULL;
                                $record['system_date'] = Carbon::parse($date);
                                $record['trans_sub_type'] = NULL;
                                $record['eff_date'] = Carbon::parse($date);
                                $record['invoice_file'] = 1;
                                $record['invoice_date'] = Carbon::parse($policyActions->effective_from);
                                $record['invoice_no'] = $invoice_no;
                                $record['invoice_amount'] = $policyActions->premium;
                                $record['premium'] = $policyActions->premium;
                                $record['other_charges'] = NULL;
                                $record['due_amount'] = $policyActions->premium;
                                $record['pmts_adjust'] = NULL;
                                $record['due_date'] = NULL;
                                $record['status'] = 'Pending';
                                $record['credit'] = NULL;

                                $amt = number_format(((float) str_replace(',', '', $policyActions->premium) - (float) str_replace(',', '', $policy->vat)), 2);

                                $record['debit'] = $policyActions->premium;
                                $record['balance'] = str_replace(',', '', $balance);

                                $data[] = $record;

                                //----SUB-LEDGER

                                $subRecord = array();
                                $subRecord['customer_id'] = $policy->customer_id;
                                $subRecord['account_id'] = NULL;
                                $subRecord['policy_id'] = $policy->id;
                                $subRecord['term_id'] = $termId;
                                $subRecord['action_id'] = $actionId;
                                $subRecord['claim_id'] = NULL;
                                $subRecord['banking_id'] = $banking_id;
                                $subRecord['account_name'] = 'Accounts Receivable A/C';
                                $subRecord['accounting_date'] = Carbon::parse($date);
                                $subRecord['trans_type'] = 'Accounts Receivable';
                                $subRecord['trans_ref'] = NULL;
                                $subRecord['system_date'] = Carbon::parse($date);
                                $subRecord['credit'] = NULL;
                                $subRecord['debit'] = $policyActions->premium;

                                $subData[] = $subRecord;

                                $subData[0]['trans_ref'] = $invoice_no;
                                $subData[1]['trans_ref'] = $invoice_no;
                                $subData[2]['trans_ref'] = $invoice_no;
                                //-------------------------------INVOICE-------------------------------------
                                Ledger::insert($data);
                                SubLedger::insert($subData);
                                // Log::info('Invoices data',$data);
                            }



                        }
                    }
                }
                $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Create Invoices Added Successfully!']);
                $this->dispatchBrowserEvent('page-refresh');

            }

        } catch (\Exception $e) {
            return false;
        }
    }

    function calculateRefundPremiumWithEndorsements($newBusinessAnnualPremium, $policyEffectiveDate, $cancellationDate, $cancellationDays, $endorsements)
    {
        $refundAmount = 0.0;

        // Convert dates to timestamps
        $policyStart = strtotime($policyEffectiveDate);
        $cancelDate = strtotime($cancellationDate);

        // Calculate cancellation days
        //$cancellationDays = (int)(($cancelDate - $policyStart) / 86400) + 1;

        // 1. New Business Premium Refund (Pro-Rated) 
        $refundNewBusiness = $newBusinessAnnualPremium;/// 365) * $cancellationDays;
        $refundAmount += $refundNewBusiness;
        // 2. Loop through each endorsement
        foreach ($endorsements as $endorsement) {
            $endorsementPremium = $endorsement['premium'];
            $endorsementEffective = strtotime($endorsement['effective_date']);
            $endorsementEnd = strtotime($endorsement['end_date']);
            // Total pro-rata days for this endorsement
            $endorsementDuration = (int) (($endorsementEnd - $endorsementEffective) / 86400) + 1;
            if ($endorsementEffective < $cancelDate) {
                // If endorsement started before cancellation, refund up to cancellation
                // $coveredDays = min($endorsementEnd, $cancelDate) - $endorsementEffective;
                // $coveredDays = (int)($coveredDays / 86400) + 1;
                $refund = ($endorsementPremium / $endorsementDuration) * $cancellationDays;
            } else {
                // If endorsement starts after cancellation, refund full amount
                $refund = $endorsementPremium;
            }

            $refundAmount += $refund;
        }
        return round($refundAmount, 2);
    }

    function calculateMonthlyRefundPremiumWithEndorsements($monthlyPremium, $policyEffectiveDate, $cancellationDate, $cancellationDays, $endorsements)
    {

        $refundAmount = 0.0;
        $cancelDate = strtotime($cancellationDate);
        $refundAmount = $monthlyPremium;
        //dd( $refundAmount);
        // Handle endorsements for this month
        foreach ($endorsements as $endorsement) {
            $endorsementId = $endorsement['id'];
            $endorsementPremium = $endorsement['premium'];
            $endorsementEffective = strtotime($endorsement['effective_date']);
            $endorsementEnd = strtotime($endorsement['end_date']);
            // Total pro-rata days for this endorsement
            $endorsementDuration = (int) (($endorsementEnd - $endorsementEffective) / 86400) + 1;
            if ($endorsementEffective < $cancelDate) {
                // If endorsement started before cancellation, refund up to cancellation
                // $coveredDays = min($endorsementEnd, $cancelDate) - $endorsementEffective;
                // $coveredDays = (int)($coveredDays / 86400) + 1;
                $refund = ($endorsementPremium / $endorsementDuration) * $cancellationDays;
                $refundAmount += $refund;
            } else {
                // Endorsement starts on or after the cancel date, so it never
                // ran and contributes nothing to the refund. Skip it. It used to
                // be SOFT-DELETED here — inside a refund helper called from a
                // rating pass — which permanently discarded issued transactions
                // as a side effect of working out a number.
                continue;
            }
        }
        //dd('test'.$refundAmount);
        return round($refundAmount, 2);
    }

    public function startPdfGeneration()
{
  
    $job = V2PdfJob::create([
        'policy_id' => $this->policy->id,
        'term_id' => $this->termId,
        'action_id' => $this->dataShowForActionId,
        'status' => 'queued',
    ]);

$sessionKey = "pdf_job_{$this->policy->id}_{$this->termId}_{$this->dataShowForActionId}";

session()->put($sessionKey, $job->id);
$this->pdfJobId = $job->id;

    $this->pdfJobId = $job->id;
    $this->status = 'queued';
    $this->fileName = null;
    $this->downloadLink = null;

    \Log::info("Dispatching GenerateQuotationPdfJob", [
    'policyId' => $job->policy_id,
    'termId'   => $job->term_id,
    'actionId' => $job->action_id,
    'pdfJobId' => $job->id,
]);
  
// uncomment for local testing
// GenerateQuotationPdfJob::dispatch($job->policy_id,$job->term_id,$job->action_id,$job->id)
    // ->onQueue('pdf'); 
   GenerateQuotationPdfJob::dispatch($job->policy_id,$job->term_id,$job->action_id,$job->id);
}
                           
public function resetPdfJob()
{
    $this->pdfJobId = null;
    $this->status = null;
    $this->fileName = null;
    $this->downloadLink = null;

    $sessionKey = "pdf_job_{$this->policy->id}_{$this->termId}_{$this->dataShowForActionId}";
    \Log::info('Resetting PDF job for session key: ' . $sessionKey);
    session()->forget($sessionKey);
}
public function checkPdfJobStatus()
{
    $sessionKey = "pdf_job_{$this->policy->id}_{$this->termId}_{$this->dataShowForActionId}";
    $pdfJobId = $this->pdfJobId ?? session($sessionKey);
 
    if (!$pdfJobId) {
        // Try to find an existing completed job
        $existingJob = \AlphaDirect\Models\V2PdfJob::where('policy_id', $this->policy->id)
            ->where('term_id', $this->termId)
            ->where('action_id', $this->dataShowForActionId)
            ->where('status', 'completed')
            ->whereNotNull('file_name')
            ->orderBy('id', 'desc')
            ->first();
        if ($existingJob) {
            $pdfJobId = $existingJob->id;
            $this->pdfJobId = $existingJob->id;
            session()->put($sessionKey, $existingJob->id);
        } else {
            return;
        }
    }
 
    $job = \AlphaDirect\Models\V2PdfJob::find($pdfJobId);
 
    if (!$job) {
        $this->status = null;
        $this->pdfJobId = null;
        $this->downloadLink = null;
        $this->fileName = null;
        $this->hasActivityAfterPdf = false;
        session()->forget($sessionKey);
        return;
    }
 
     $previousStatus = $this->status; // ⭐ track old status
    $this->status = $job->status ?? null;
    $this->pdfJobId = $job->id;
 
    if ($job->status === 'completed' && $job->file_name) {
        // Verify file exists
        $filePath = storage_path('app/public/quote_sheet/' . $job->file_name);
        if (file_exists($filePath)) {
            $this->downloadLink = route('admin.policy.downloadQuotePdf', ['pdfJobId' => $job->id]);
            $this->fileName = $job->file_name;
            // Also update session to persist the job ID
            session()->put($sessionKey, $job->id);
            // Check if there's been activity after PDF generation
            $this->hasActivityAfterPdf = $this->checkActivityAfterPdf($job);
            session()->put($sessionKey, $job->id);



            // ✅ REFRESH ONLY ONCE

            $refreshKey = "pdf_refresh_done_{$job->id}";



            if (

                $previousStatus !== 'completed' &&

                !session()->has($refreshKey)

            ) {

                session()->put($refreshKey, true);

                 // 🔥 trigger browser refresh ONCE

                $this->dispatchBrowserEvent('pdf-download-ready-refresh');

            }
        } else {
            \Log::warning('PDF file not found for completed job', [
                'pdfJobId' => $job->id,
                'file_name' => $job->file_name,
                'filePath' => $filePath,
            ]);
            // If file doesn't exist for completed job, reset state to allow regeneration
            $this->downloadLink = null;
            $this->fileName = null;
            $this->hasActivityAfterPdf = false;
            $this->status = null;
            $this->pdfJobId = null;
            session()->forget($sessionKey);
        }
    } else {
        // For non-completed statuses, keep the status but ensure other fields are null
        $this->downloadLink = null;
        $this->fileName = null;
        $this->hasActivityAfterPdf = false;
        // If status is null or unexpected, reset to allow button to show
        if (!$this->status || !in_array($this->status, ['queued', 'processing', 'failed', 'completed'])) {
            $this->status = null;
            $this->pdfJobId = null;
            session()->forget($sessionKey);
        }
    }
}
public function checkActivityAfterPdf($pdfJob)
{
    // Get the PDF generation time (use updated_at when status changed to completed)
    $pdfGeneratedAt = $pdfJob->updated_at;
    
    // Get the PolicyAction record to check its updated_at
    $policyAction = PolicyAction::where('id', $this->dataShowForActionId)
        ->where('policy_id', $this->policy->id)
        ->first();
    
    if (!$policyAction || !$policyAction->updated_at) {
        // No PolicyAction found - default to showing download button
        \Log::info('PolicyAction not found - defaulting to download button', [
            'pdfJobId' => $pdfJob->id,
            'actionId' => $this->dataShowForActionId,
        ]);
        return false;
    }
    
    // Check if PolicyAction was updated AFTER PDF generation
    // policy_action updated_at > v2_pdf_job updated_at means activity occurred
    $hasActionActivity = $policyAction->updated_at->gt($pdfGeneratedAt);
    
    \Log::info('Checking activity after PDF generation', [
        'pdfJobId' => $pdfJob->id,
        'policyId' => $this->policy->id,
        'actionId' => $this->dataShowForActionId,
        'pdfJobUpdatedAt' => $pdfGeneratedAt->format('Y-m-d H:i:s'),
        'policyActionUpdatedAt' => $policyAction->updated_at->format('Y-m-d H:i:s'),
        'hasActionActivity' => $hasActionActivity,
        'willShowButton' => $hasActionActivity ? 'Generate V2-1.6 Quote Sheet' : 'Download V2-1.6 Quote Sheet',
    ]);
    
    // Return true if PolicyAction was updated after PDF generation (show Generate button)
    // Return false if no activity (show Download button)
    return $hasActionActivity;
}

public function resetPdfJobAndDownload()
{
    $sessionKey = "pdf_job_{$this->policy->id}_{$this->termId}_{$this->dataShowForActionId}";
    \Log::info("Preparing PDF download for session key: " . $sessionKey);

    $pdfJobId = $this->pdfJobId ?? session($sessionKey);

    $job = \AlphaDirect\Models\V2PdfJob::find($pdfJobId);

    if (! $job || $job->status !== 'completed' || empty($job->file_name)) {
        $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'File not found.']);
        return;
    }

    $filePath = storage_path('app/public/quote_sheet/' . $job->file_name);

    if (!file_exists($filePath)) {
        $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'PDF file does not exist on server.']);
        return;
    }

    // Generate download URL using route
    $downloadUrl = route('admin.policy.downloadQuotePdf', ['pdfJobId' => $job->id]);

    // Dispatch browser event to trigger download without page reload
    // DON'T reset the job state - keep the button visible until activity occurs
    // $this->dispatchBrowserEvent('download-file', [
    //     'url' => $downloadUrl,
    //     'filename' => $job->file_name
    // ]);
    
    // Refresh activity check after download (but don't reset state)
    // This ensures if activity happened during download, the button updates accordingly
    if ($this->pdfJobId) {
        $this->checkPdfJobStatus();
    }
       return redirect()->to(
        route('admin.policy.downloadQuotePdf', ['pdfJobId' => $job->id])
    );
}
}                   