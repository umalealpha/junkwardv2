<?php

namespace AlphaDirect\Http\Livewire\Policy;

use AlphaDirect\Models\PolicyAction;
use Livewire\Component;
use AlphaDirect\Models\TbPrTranTypes;
use AlphaDirect\Models\TbPrTranSubTypes;
use AlphaDirect\Policy;
use AlphaDirect\PolicyTerm;
use Carbon\Carbon;
use AlphaDirect\Vehicle;
use AlphaDirect\Models\RiskAddress;
use AlphaDirect\Models\policyActionEndorse;
use AlphaDirect\Helper;
use AlphaDirect\Models\User;

class AddTransaction extends Component
{
    public PolicyAction $policyAction;
    public PolicyTerm $policy_term;
    public bool $showModal = false;
    public $policy;
    public $termId;
    public $actionId;
    public $subTransactionTypes;
    public $effective_from;
    public $effective_to;
    public $transaction_date;
    public $lastPolicyActionId;
    public $dataShowForActionId;
    public $previousActionId;

    public $disableEffectiveTo = false;
    protected $listeners = ['transactionTypeChanged'];

    public array $rules = [
        'policyAction.transaction_type' => 'required',
        'policyAction.transaction_reason' => 'required',
        'effective_from' => 'required',
        'policy_term.term_end_date' => 'date',
        'effective_to' => 'required|date_format:d/m/Y',//|date_equals:policy_term.term_end_date date|after:effective_from
        'transaction_date' => 'required',
        'policyAction.note' => '',
    ];

    public function mount()
    {


        $this->dataShowForActionId = $this->previousActionId ?? $this->actionId;
        $this->policyAction = new PolicyAction();
        $this->policy_term = PolicyTerm::find($this->termId);

        if ($this->policy->premium_freq == 1 || $this->policy->premium_freq == 2 || $this->policy->premium_freq == 5) {

            $this->policyActionMonth = PolicyAction::find($this->dataShowForActionId);
            $this->effective_from = Carbon::create($this->policyActionMonth->effective_from)->format(config('constants.date.format'));

            $this->effective_to = Carbon::create($this->policyActionMonth->effective_to)->format(config('constants.date.format'));
            $this->transaction_date = now()->format(config('constants.date.format'));
        } else {

            $this->effective_from = Carbon::create($this->policy->term_start_date)->format(config('constants.date.format'));

            $this->effective_to = Carbon::create($this->policy_term->term_end_date)->format(config('constants.date.format'));
            $this->transaction_date = now()->format(config('constants.date.format'));
        }

    }


    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        return view('v2.livewire.policy.add-transaction');
    }


    // function added by snehal for - to change effective to onchnage of effective from 
    // public function updatedEffectiveFrom($value)
    // {
    //     if($this->policy->premium_freq==3 && $this->policyAction->transaction_type=='RENEW')
    //     {

    //             try {

    //                 $fromDate = \Carbon\Carbon::createFromFormat(config('constants.date.format'), $value);
    //                 $this->effective_to = $fromDate->copy()->addYear()->subDay()->format(config('constants.date.format'));
    //                 $this->disableEffectiveTo = true;
    //             } 
    //             catch (\Exception $e) {
    //                 $this->addError('effective_from', 'Invalid date format.');
    //                 $this->disableEffectiveTo = false;
    //             }
    //     }
    // }

    // public function transactionTypeChanged($value)
// {
//   // added by snehal 
//         if($this->policy->premium_freq==3 && $value=="RENEW")
//         {

    //             $this->effective_from = Carbon::create($this->policy->term_end_date)->addDay()->format(config('constants.date.format'));
//             //dd($this->effective_from);
//             $this->effective_to = Carbon::create($this->policy_term->term_end_date)->addYear()->format(config('constants.date.format'));
//             $this->transaction_date = now()->format(config('constants.date.format'));

    //             // $this->effective_from = Carbon::now()->format(config('constants.date.format'));

    //             // Send date to JavaScript
//             $this->dispatchBrowserEvent('update-datepicker', [
//                 'id' => 'policyAction_effective_from',
//                 'date' => $this->effective_from,
//             ]);
//         } 
//         else{  

    //         $this->effective_from = Carbon::create($this->policy->term_start_date)->format(config('constants.date.format'));
//         $this->effective_to = Carbon::create($this->policy_term->term_end_date)->format(config('constants.date.format'));
//         $this->transaction_date = now()->format(config('constants.date.format'));
//         }

    // }

    public function submit()
    {

        \DB::transaction(function () {
            $this->showModal = true;
            $this->validate();
            $date1 = strtotime(date("d-m-y"));

            $previousAction = PolicyAction::where('policy_id', $this->policy->id)->orderBy('id', 'DESC')->first();
            if ($previousAction->transaction_type == 'CANCEL') {
                if (($this->policyAction->transaction_type != 'REINSTATE' || $this->policyAction->transaction_type != 'REISSUE')) {
                    $previousActionCancel = PolicyAction::where('policy_id', $this->policy->id)
                        ->where('id', '<', $previousAction->id)
                        ->orderBy('id', 'DESC')->first();
                    $this->dataShowForActionId = $previousActionCancel->id;
                }
            }

            $date = date("Y-m-d", strtotime($this->effective_from));//str_replace('/', '-', $this->effective_from);
            // dd($previousAction->effective_from,$date );
            // if( $previousAction->effective_from > $date &&  $this->policyAction->transaction_type == 'ENDORSE'){
            //     $this->dispatchBrowserEvent('alert', [f'type' => 'error',  'message' => 'You cannot submit policy Because effective from date is less than previous effective to date ']);
            // }REINSTATE REISSUE
            // else

            if ($this->policyAction->transaction_type == 'ENDORSE' && $previousAction->status != null && $previousAction->status == 'QUOTE' && $previousAction->transaction_type != "ANNIVERSARY-RENEW") {

                $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => 'You cannot submit policy Because policy in Quote status ']);

            } else if (
                $this->policyAction->transaction_type != 'REINSTATE' &&
                $this->policyAction->transaction_type != 'REISSUE' &&
                $previousAction->transaction_type == 'CANCEL' &&
                $previousAction->status == 'ISSUED'
            ) {
                $this->dispatchBrowserEvent('alert', [
                    'type' => 'error',
                    'message' => 'You cannot submit policy because policy is already cancelled'
                ]);
            } else if ($this->policyAction->transaction_type == 'CANCEL') {

                $premium = 0;
                $diff_in_days_new_coverage = 0;
                $diff_in_days_main = 0;
                if ($this->policy->premium_freq == 3) {
                    $policyActionPrev = PolicyAction::where('policy_id', '=', $this->policy->id)
                        ->where('transaction_type', '=', 'NEWBUSINESS')
                        ->orderBy('id', 'desc')->take(1)
                        ->first();
                } else if ($this->policy->premium_freq == 1 || $this->policy->premium_freq == 2 || $this->policy->premium_freq == 5) {
                    $policyActionPrev = PolicyAction::where('policy_id', $this->policy->id)
                        ->whereIn('transaction_type', ['NEWBUSINESS', 'RENEW'])
                        ->whereDate('effective_from', '<=', Carbon::createFromFormat(config('constants.date.format'), $this->effective_from))
                        ->orderBy('effective_from', 'desc')
                        ->first();
                }
                $this->policyAction->status = 'QUOTE';
                $this->policyAction->policy_id = $this->policy->id;
                $this->policyAction->term_id = $this->termId;
                $this->policyAction->effective_from = Carbon::createFromFormat(config('constants.date.format'), $this->effective_from);
                $this->policyAction->effective_to = Carbon::createFromFormat(config('constants.date.format'), $this->effective_to);
                $this->policyAction->transaction_date = Carbon::createFromFormat(config('constants.date.format'), $this->transaction_date);

                // Pro-rata refund — frequency-wise as per dates.
                $annualPremium = ($this->policy->annual_premium > 0)
                    ? $this->policy->annual_premium
                    : ($policyActionPrev->premium ?? 0);

                $cancelFrom = Carbon::parse($this->policyAction->effective_from);

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
                    $daysInMonth     = $cancelFrom->daysInMonth;
                    $endOfMonth      = $cancelFrom->copy()->endOfMonth();
                    $daysLeftInMonth = $endOfMonth->day - $cancelFrom->day + 1;

                    $diff_in_days_main         = $daysInMonth;
                    $diff_in_days_new_coverage = $daysLeftInMonth;

                    $monthlyPremium = $annualPremium / 12;
                    $premium = ($daysInMonth > 0)
                        ? $monthlyPremium * ($daysLeftInMonth / $daysInMonth)
                        : 0;
                }
                $endorsements = PolicyAction::where('policy_id', $this->policy->id)
                    ->where('transaction_type', '=', 'ENDORSE')
                    ->orderBy('effective_from')
                    ->get();

                $endorsementsMonthDelete = PolicyAction::where('policy_id', $this->policy->id)
                    ->whereIn('transaction_type', ['ENDORSE', 'ENDORSE-RENEW', 'RENEW'])
                    ->where('effective_from', '>=', Carbon::createFromFormat(config('constants.date.format'), $this->effective_from))
                    ->orderBy('effective_from')
                    ->get();
                if (isset($endorsementsMonthDelete) && $endorsementsMonthDelete->count() > 0) {

                    foreach ($endorsementsMonthDelete as $endorsementMonthDelete) {

                        $endorsement = PolicyAction::where('id', $endorsementMonthDelete->id)
                            ->where('policy_id', $this->policy->id)
                            ->delete();
                    }
                }
                $endorsementsMonth = PolicyAction::where('policy_id', $this->policy->id)
                    ->whereIn('transaction_type', ['ENDORSE', 'ENDORSE-RENEW'])
                    ->whereDate('effective_to', '=', $this->policyAction->effective_to)
                    ->orderBy('effective_from')
                    ->get();
                $refundPremium = 0;
                $endorsements = PolicyAction::where('policy_id', $this->policy->id)
                    ->where('transaction_type', '=', 'ENDORSE')
                    ->orderBy('effective_from')
                    ->get();
                if ($this->policy->premium_freq == 3) {
                    $refundPremium = $this->calculateRefundPremiumWithEndorsements(
                        $premium,
                        $policyActionPrev->effective_from,
                        $this->policyAction->effective_from,
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
                        $this->policyAction->effective_from,
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
                $this->policyAction->premium = -$refundPremium;
                $this->policyAction->save();
                $lastInsertId = $this->policyAction->id; // or $policyAction->get('id');
                //  Helper::generateInvoiceDomComIssued($this->policy->id,$lastInsertId,$this->policyAction->effective_from);
                $this->emitUp('refreshParent', $this->policyAction->id);
                $this->policyAction = new PolicyAction();
                $this->dispatchBrowserEvent('closeTransactionModal');

            } else {

                $this->policyAction->status = 'QUOTE';
                $this->policyAction->current_frequency_id = $this->policy->premium_freq;
                $this->policyAction->policy_id = $this->policy->id;
                $this->policyAction->term_id = $this->termId;
                $policyNo = Policy::where('id', $this->policyAction->policy_id)->first(['policyNumber']);
                $latest = PolicyAction::Policy($this->policyAction->policy_id)
                    ->orderBy('id', 'desc')
                    ->first(array('policy_quote_no'));

                if ($latest['policy_quote_no'] != null) {
                    list($prefix, $numericPart) = explode('/', $latest->policy_quote_no);
                    $numericPart = str_pad((int) $numericPart + 1, strlen($numericPart), '0', STR_PAD_LEFT);
                    $policyQuoteNo = $prefix . '/' . $numericPart;
                } else {
                    $policyQuoteNo = 01;
                }

                if ($this->policyAction->transaction_type == 'RENEW') {
                    $this->policyAction->policy_quote_no = $policyQuoteNo;
                }
                if ($this->policyAction->transaction_type == 'ENDORSE' && $latest == null) {
                    $this->policyAction->policy_quote_no = $policyNo->policyNumber . '/01';
                }
                // else{
                //     $this->policyAction->policy_quote_no = $policyNo->policyNumber.'/01';
                // }

                $this->policyAction->effective_from = Carbon::createFromFormat(config('constants.date.format'), $this->effective_from);
                $this->policyAction->effective_to = Carbon::createFromFormat(config('constants.date.format'), $this->effective_to);
                $this->policyAction->transaction_date = Carbon::createFromFormat(config('constants.date.format'), $this->transaction_date);

                $this->policyAction->save();

                //         $this->lastPolicyActionId = PolicyAction::Policy($this->policyAction->policy_id)
                // //                ->Issued()
                //                 ->where('id','!=',$this->policyAction->id)->orderBy('id','desc')->first()->id ?? null;
                //         if(isset($this->lastPolicyActionId)){
                //             // Copy Last Transaction records to new transaction
                //             PolicyAction::newPolicyAction($this->policyAction,$this->lastPolicyActionId);
                //         }
                PolicyAction::newPolicyAction($this->policyAction, $this->dataShowForActionId);

                //     $fromRiskAddress = RiskAddress::where('action_id', $this->policyAction->id)->where('policy_id',$this->policy->id)->first();
                //     $vehicle = Vehicle::where('action_id', $this->policyAction->id)->where('policy_id',$this->policy->id)->get();
                //   // dd($vehicle,$fromRiskAddress);
                //     foreach ($vehicle as $vehicles) {

                //         Vehicle::where('id',$vehicles->id)->update(['risk_id'=>$fromRiskAddress->id]);

                //     }
                // $fromRiskAddress = RiskAddress::where('action_id', $this->policyAction->id)->first();
                // dd(  $fromRiskAddress);
                // $vehicle = Vehicle::where('action_id', $this->policyAction->id)->get();
                // foreach ($vehicle as $vehicles) {

                //     Vehicle::where('id',$vehicles->id)->update(['risk_id'=>$fromRiskAddress->id]);

                // }

                activity('Alter term/Date of Policy')
                    ->performedOn($this->policy)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log('Added term/Date of Policy current Transaction ID: ' . $this->policyAction->id);

                $this->emitUp('refreshParent', $this->policyAction->id);
                $this->policyAction = new PolicyAction();
                $this->dispatchBrowserEvent('closeTransactionModal');
            }
        });
    }

    public function getTransactionTypes()
    {
        $this->newActionDates = PolicyAction::where('id', $this->actionId)->first();
        $rules = [
                    'NEWBUSINESS'       => ['CANCEL', 'ENDORSE', 'EXPIRE'],
                    'ANNIVERSARY-RENEW' => ['CANCEL', 'ENDORSE'],
                    'REISSUE'           => ['CANCEL', 'ENDORSE', 'EXPIRE'],
                    'REINSTATE'         => ['CANCEL', 'ENDORSE', 'EXPIRE'],
                    'CANCEL'            => ['REISSUE', 'REINSTATE'],
                    'ENDORSE'           => ['ENDORSE', 'CANCEL'],
                    'EXPIRE'            => ['REISSUE', 'REINSTATE'],
                    'RENEW'             => ['CANCEL', 'ENDORSE'],
                ];
        $lastTransaction = $this->newActionDates->transaction_type;

        // Mirror V1: when the SELECTED action is LAPSED or NTU, route through
        // the CANCEL rule so the dropdown shows REINSTATE + REISSUE only.
        if (in_array($this->newActionDates->status, ['LAPSED', 'NTU'])) {
            $lastTransaction = 'CANCEL';
        }

        $allowed = $rules[$lastTransaction] ?? [];

        // If no allowed next transactions → return empty collection
        if (empty($allowed)) {
            return collect();
        }

        // Fetch using TranTypeCode (TEXT)
        return TbPrTranTypes::whereIn('TranTypeCode', $allowed)
            ->get()
            ->keyBy('TranTypeCode')
            ->map(function ($d) {
                return [
                    'id' => $d->TranTypeCode,
                    'name' => $d->TranTypeScreenName
                ];
            });

    }

    public function updatedPolicyActionTransactionType($value, $key)
    {
        if ($key == "transaction_type") {
            $trantypedata = TbPrTranSubTypes::where('TranTypeCode', $value)->get()->keyBy('TranSubTypeCode')->map(function ($trantypedata) {
                return [
                    'id' => $trantypedata->TranSubTypeCode,
                    'name' => $trantypedata->TranSubtypeScreenName
                ];
            });
            $this->subTransactionTypes = $trantypedata;
            $this->dispatchBrowserEvent('dropdown-changed', [
                'key' => 'transaction_reason',
                'data' => $trantypedata
            ]);
        }
    }


    public function getTransactionRequestBysProperty()
    {
        return [
            'INSURED' => 'Insured',
            'SYSTEM' => 'System',
            'UNDERWRITER' => 'Underwriter'
        ];
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
                $endorsement = PolicyAction::where('id', $endorsementId)
                    ->where('policy_id', $this->policy->id)
                    ->first();
                $endorsement = PolicyAction::where('id', $endorsementId)
                    ->where('policy_id', $this->policy->id)
                    ->delete();
            }
        }
        //dd('test'.$refundAmount);
        return round($refundAmount, 2);
    }
}
