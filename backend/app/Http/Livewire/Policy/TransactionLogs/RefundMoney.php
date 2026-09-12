<?php

namespace AlphaDirect\Http\Livewire\Policy\TransactionLogs;

use AlphaDirect\Ledger;
use Livewire\Component;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Models\PaymentTransactionArchive;
use AlphaDirect\Models\User;
use AlphaDirect\Policy;
use AlphaDirect\SubLedger;
use Illuminate\Support\Facades\Validator;
use Auth;
use DB;

class RefundMoney extends Component
{
    public $policyNumber;
    public $reference_number;
    public $date_of_refund;
    public $amount;
    public $reason;
    public $refunded_by;

    public $policy;

    public $totalRefundCount = 0;
    public $totalRefundAmount = 0;

    protected $listeners = ['updateDateOfRefund' => 'setDateOfRefund','openRefundModal' => 'setModalData', 'transactionRefund' => 'refreshTable',];

    public function refreshTable()
    {
        $this->emitSelf('$refresh');
    }

    public function setModalData()
    {
        $this->dispatchBrowserEvent('show-modal', ['target' => '#refundModal']);
    }

    public function setDateOfRefund($date)
    {
        $this->date_of_refund = $date;
    }

    public function openReverseModal()
    {
        if (!$this->policy) {
            $this->policy = Policy::where('policyNumber', $this->policyNumber)->first();
        }
        
        if ($this->policy) {
            $this->emit('openReverseModalFromButton', $this->policy->id, $this->policy->customer_id, $this->policy->product_id, $this->policyNumber);
        }
    }

    public function mount($policyNumber, $policy = null)
    {
        $this->policyNumber = $policyNumber;
        $this->policy = $policy;
        $this->calculateRefundStats();
    }

    public function calculateRefundStats()
    {
        $graphite = PaymentTransaction::where('policyNumber', $this->policyNumber)
            ->whereIn('status', ['Success', 'SUCCESS', 'success', 1])
            ->where('is_refund', 1)->get();

        $archive = PaymentTransactionArchive::where('policyNumber', $this->policyNumber)
            ->whereIn('status', ['Success', 'SUCCESS', 'success', 1])
            ->where('is_refund', 1)->get();

        $merged = $archive->merge($graphite);
        $this->totalRefundCount = $merged->count();
        $this->totalRefundAmount = $merged->sum('amount');
    }

    public function submit()
    {
        $this->validate([
            'reference_number' => 'required|string|max:30',
            'date_of_refund' => 'required|date',
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|max:300',
            'refunded_by' => 'required|string|max:30',
        ]);

        $this->makeRefund();

        session()->flash('message', 'Refund processed successfully.');
        $this->reset(['reference_number', 'date_of_refund', 'amount', 'reason', 'refunded_by']);
        $this->calculateRefundStats();
        $this->dispatchBrowserEvent('hide-modal');
        $this->emit('transactionRefunded');

    }

    public function makeRefund()
    {
        try {
            $policy = Policy::where('policyNumber', $this->policyNumber)->first();

            if ($policy == null) {
                return redirect()->back()->with('error', 'Policy not found');
            }

            if ($policy->status == 0) {
                return redirect()->back()->with('error', 'Deactivated policy is not allowed to refund.');
            }

            DB::beginTransaction();

            $paymentRefund = new PaymentTransaction();
            $paymentRefund->paymentMethod = 'Cash';
            $paymentRefund->referenceNumber = $this->reference_number;
            $paymentRefund->refunded_by = $this->refunded_by;
            $paymentRefund->policyNumber = $this->policyNumber;
            $paymentRefund->policy_id = $policy->id;
            $paymentRefund->status = 'Success';
            $paymentRefund->paymentFrequency = 1;
            $paymentRefund->amount = $this->amount;
            $paymentRefund->reason = $this->reason;
            $paymentRefund->paymentDate = \Carbon\Carbon::parse($this->date_of_refund)->format('Y-m-d');
            $paymentRefund->is_refund = 1;
            $paymentRefund->is_ledger = 1;
            $paymentRefund->save();

            $ledger = Ledger::where('policy_id', $policy->id)->orderBy('id', 'desc')->first();
            $balance = $ledger ? (float) $ledger->balance : 0.0;
            $record = array();
                $record['customer_id'] = $policy->customer_id;
                $record['account_id'] = NULL;
                $record['policy_id'] = $policy->id;
                $record['claim_id'] = NULL;
                $record['banking_id'] = NULL;
                $record['account_name'] = NULL;
                $record['accounting_date'] = \Carbon\Carbon::parse($this->date_of_refund)->format('Y-m-d');
                $record['trans_type'] = 'Refund';
                $record['amount_type'] = NULL;
                $record['trans_ref'] = $this->reference_number;
                $record['orig_trans'] = $this->reference_number;
                $record['unallocated'] = NULL;
                $record['system_date'] = \Carbon\Carbon::parse($this->date_of_refund)->format('Y-m-d');
                $record['trans_sub_type'] = NULL;
                $record['eff_date'] = \Carbon\Carbon::parse($this->date_of_refund)->format('Y-m-d');
                $record['invoice_file'] = NULL;
                $record['invoice_date'] = NULL;
                $record['invoice_no'] = NULL;
                $record['invoice_amount'] = NULL;
                $record['premium'] = $policy->premium;
                $record['other_charges'] = NULL;
                $record['due_amount'] = NULL;
                $record['pmts_adjust'] = NULL;
                $record['due_date'] = NULL;
                $record['status'] = 'Paid';

                $amount = str_replace(',', '', $this->amount);
                $record['debit'] = $amount;
                $record['credit'] = NULL;

                if ($balance < 0) {
                    $balance = -1 * (str_replace(',', '', number_format(($amount + abs($balance)), 2)));
                } else {
                    $balance = str_replace(',', '', number_format(($balance - $amount), 2));
                }
                $record['balance'] = $balance;

                //----SUB-LEDGER
                $subData = [];

                $subRecord = array();
                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = NULL;
                $subRecord['account_name'] = NULL;
                $subRecord['accounting_date'] = \Carbon\Carbon::parse($this->date_of_refund)->format('Y-m-d');
                $subRecord['trans_type'] = 'Cash Refund';
                $subRecord['trans_ref'] = $this->reference_number;
                $subRecord['system_date'] = \Carbon\Carbon::parse($this->date_of_refund)->format('Y-m-d');
                $subRecord['credit'] = NULL;
                $subRecord['debit'] = number_format($this->amount, 2);
                $subData[] = $subRecord;

                $subRecord = array();
                $subRecord['customer_id'] = $policy->customer_id;
                $subRecord['account_id'] = NULL;
                $subRecord['policy_id'] = $policy->id;
                $subRecord['claim_id'] = NULL;
                $subRecord['banking_id'] = NULL;
                $subRecord['account_name'] = NULL;
                $subRecord['accounting_date'] = \Carbon\Carbon::parse($this->date_of_refund)->format('Y-m-d');
                $subRecord['trans_type'] = 'Cash Refund';
                $subRecord['trans_ref'] = $this->reference_number;
                $subRecord['system_date'] = \Carbon\Carbon::parse($this->date_of_refund)->format('Y-m-d');
                $subRecord['credit'] = number_format($this->amount, 2);
                $subRecord['debit'] = NULL;
                $subData[] = $subRecord;

                Ledger::insert($record);
                SubLedger::insert($subData);

                DB::commit();

                if (Auth::check()) {
                    activity('Made refund')
                        ->performedOn($policy)
                        ->causedBy(User::where('id', auth()->user()->id)->first())
                        ->log('Refund made of amount P ' . $paymentRefund->amount);
                }

                return redirect()->back()->with('success', 'Refund made successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('refund.failed', ['policyNumber' => $this->policyNumber, 'msg' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Refund not saved. Try again');
        }
    }

    public function render()
    {
        return view('v2.livewire.policy.transaction-logs.refund-money');
    }
}

