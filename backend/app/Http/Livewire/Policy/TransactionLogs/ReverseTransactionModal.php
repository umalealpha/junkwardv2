<?php

namespace AlphaDirect\Http\Livewire\Policy\TransactionLogs;

use Livewire\Component;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Ledger;
use AlphaDirect\SubLedger;
use AlphaDirect\Policy;
use AlphaDirect\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReverseTransactionModal extends Component
{
    public $customerId, $productId, $policyId, $transactionLogId;
    public $submitToRoute = '';
    public $mode = 'after'; // 'before' or 'after'
    public $reference_number = '';
    public $reversal_date = '';
    public $comments = '';
    public $policyNumber = '';

    protected $listeners = ['openReverseModal' => 'setModalData', 'openReverseModalFromButton' => 'setModalDataFromButton'];

    public function setModalData($transactionLogId, $policyId, $customerId, $productId, $route)
    {
        $this->transactionLogId = $transactionLogId;
        $this->policyId = $policyId;
        $this->customerId = $customerId;
        $this->productId = $productId;
        $this->submitToRoute = $route;
        $this->mode = $route === 'admin.policy.transaction_log_delete2' ? 'before' : 'after';
        
        // Get reference number from transaction if transactionLogId is set
        if ($transactionLogId) {
            $transaction = PaymentTransaction::find($transactionLogId);
            if ($transaction) {
                $this->reference_number = $transaction->referenceNumber;
                $policy = Policy::find($policyId);
                if ($policy) {
                    $this->policyNumber = $policy->policyNumber;
                }
            }
        }
        
        $this->reversal_date = Carbon::now()->format('Y-m-d');
        $this->comments = '';

        $this->dispatchBrowserEvent('show-modal', ['target' => '#transactionLogDeleteModal']);
    }

    public function setModalDataFromButton($policyId, $customerId, $productId, $policyNumber = null)
    {
        $this->policyId = $policyId;
        $this->customerId = $customerId;
        $this->productId = $productId;
        $this->transactionLogId = null;
        $this->submitToRoute = 'admin.policy.transaction_log_delete';
        $this->mode = 'after';
        $this->policyNumber = $policyNumber;
        $this->reference_number = '';
        $this->reversal_date = Carbon::now()->format('Y-m-d');
        $this->comments = '';

        $this->dispatchBrowserEvent('show-modal', ['target' => '#transactionLogDeleteModal']);
    }

    public function submit()
    {
        // Create a unique session key for this reversal attempt
        $sessionKey = 'reversing_transaction_' . ($this->transactionLogId ?: ($this->reference_number . '_' . $this->policyId));
        
        // Prevent double submission
        if (session()->has($sessionKey)) {
            return redirect()->back()->with('error', 'Transaction reversal is already in progress.');
        }
        
        // Mark as processing
        session()->put($sessionKey, true);
        
        // Get transaction to validate payment date and for activity log
        $transaction = null;
        if ($this->transactionLogId) {
            $transaction = PaymentTransaction::find($this->transactionLogId);
            
            // Check if transaction is already reversed
            if ($transaction && ($transaction->is_reverse == 1 || $transaction->CompanyRef == 'Reversed')) {
                session()->forget($sessionKey);
                return redirect()->back()->with('error', 'This transaction has already been reversed.');
            }
        } elseif ($this->reference_number && $this->policyNumber) {
            $transaction = PaymentTransaction::where('policyNumber', $this->policyNumber)
                ->where('referenceNumber', $this->reference_number)
                ->first();
            
            // Check if transaction is already reversed
            if ($transaction && ($transaction->is_reverse == 1 || $transaction->CompanyRef == 'Reversed')) {
                session()->forget($sessionKey);
                return redirect()->back()->with('error', 'This transaction has already been reversed.');
            }
        }

        // Validate reversal date
        $this->validate([
            'reversal_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    $checkDate = config('constants.policy.restrictionDate');
                    
                    // Check if future date
                    if (strtotime($value) > strtotime(Carbon::now()->format('Y-m-d'))) {
                        $fail('Reversal date cannot be a future date.');
                    }
                    
                    // Check if date is before restriction date
                    if (strtotime($value) < strtotime($checkDate)) {
                        $fail('Reversal date cannot be before the restriction date (' . $checkDate . ').');
                    }
                },
            ],
            'comments' => 'required|string|max:500',
        ]);

        try { 
            // Use database transaction to prevent race conditions
            \DB::transaction(function () {
                if ($this->mode === 'after') {
                    $this->handleReverseAfterLedger();
                } else {
                    $this->handleReverseBeforeLedger();
                }
            });

            // Get policy for activity log
            $policy = Policy::find($this->policyId);
            
            // Log activity
            if (Auth::check() && $policy) {
                $logMessage = 'Transaction reversed';
                if ($transaction) {
                    $logMessage .= ': Reference Number - ' . $transaction->referenceNumber . ', Amount - ' . $transaction->amount;
                } elseif ($this->reference_number) {
                    $logMessage .= ': Reference Number - ' . $this->reference_number;
                }
                if ($this->reversal_date) {
                    $logMessage .= ', Reversal Date - ' . $this->reversal_date;
                }
                if ($this->comments) {
                    $logMessage .= ', Comments - ' . $this->comments;
                }
                
                activity('Transaction Reversed')
                    ->performedOn($policy)
                    ->causedBy(User::where('id', auth()->user()->id)->first())
                    ->log($logMessage);
            }

            // Clear the processing flag
            session()->forget($sessionKey);
            
            $this->dispatchBrowserEvent('hide-modal');
            // $this->emit('refreshDatatable');
            $this->emit('transactionRefund');
            $this->emit('transactionReverse');

            session()->flash('success', 'Transaction Reversed Successfully');

            $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Transaction Reversed Successfully']);

            return redirect()->back()->with('success', 'Transaction Reversed Successfully');

        } catch (\Exception $e) {
            // Clear the processing flag on error
            session()->forget($sessionKey);
            
            session()->flash('error', $e->getMessage());

            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => $e->getMessage()]);

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // public function submitReverseByReference()
    // {
    //     $this->validate([
    //         'reference_number' => 'required|string|max:50',
    //         'reversal_date' => 'required|date',
    //         'comments' => 'nullable|string|max:500',
    //     ]);
        
    //     try {
    //         // Find transaction by reference number
    //         if (!$this->policyNumber && $this->policyId) {
    //             $policy = Policy::find($this->policyId);
    //             if ($policy) {
    //                 $this->policyNumber = $policy->policyNumber;
    //             }
    //         }

    //         if (!$this->policyNumber) {
    //             throw new \Exception('Policy number is required to find transaction.');
    //         }

    //         $transaction = PaymentTransaction::where('policyNumber', $this->policyNumber)
    //             ->where('referenceNumber', $this->reference_number)
    //             ->whereIn('status', ['Success', 'SUCCESS', 'success', 1])
    //             ->whereNull('deleted_at')
    //             ->where(function ($q) {
    //                 $q->whereNull('CompanyRef')->orWhere('CompanyRef', '!=', 'Reversed');
    //             })
    //             ->first();
                
    //         if (!$transaction) {
    //             throw new \Exception('Transaction with reference number "' . $this->reference_number . '" not found or cannot be reversed.');
    //         }

    //         // // Update policyId if not set
    //         // if (!$this->policyId && $transaction->policy_id) {
    //         //     $this->policyId = $transaction->policy_id;
    //         //     $policy = Policy::find($this->policyId);
    //         //     if ($policy) {
    //         //         $this->customerId = $policy->customer_id;
    //         //         $this->productId = $policy->product_id;
    //         //     }
    //         // }

    //         // Call the new reverse function
    //         $this->handleReverseByReference($transaction);

    //         $this->dispatchBrowserEvent('hide-modal');
    //         $this->emit('transactionRefund');
    //         $this->emit('transactionReverse');

    //         session()->flash('success', 'Transaction Reversed Successfully');
    //         $this->dispatchBrowserEvent('alert', ['type' => 'success', 'message' => 'Transaction Reversed Successfully']);

    //         // Reset form
    //         $this->reference_number = '';
    //         $this->reversal_date = Carbon::now()->format('Y-m-d');
    //         $this->comments = '';

    //         return redirect()->back()->with('success', 'Transaction Reversed Successfully');

    //     } catch (\Exception $e) {
    //         session()->flash('error', $e->getMessage());
    //         $this->dispatchBrowserEvent('alert', ['type' => 'error', 'message' => $e->getMessage()]);
    //         return redirect()->back()->with('error', $e->getMessage());
    //     }
    // }

    protected function handleReverseAfterLedger()
    {
        if (!Auth::user()->hasPermissionTo('payment_delete_after_ledger')) {
            throw new \Exception('Sorry! You do not have permission to reverse before ledger.');
        }

        $policy = Policy::where('id', $this->policyId)->first();
        
        // Lock the transaction row to prevent concurrent reversals
        $transaction = PaymentTransaction::where('id', $this->transactionLogId)
            ->lockForUpdate()
            ->first();
        
        if (!$transaction) {
            throw new \Exception('Transaction not found.');
        }
        
        // Double-check if already reversed (race condition protection)
        if ($transaction->is_reverse == 1 || $transaction->CompanyRef == 'Reversed') {
            throw new \Exception('This transaction has already been reversed.');
        }
        
        // Check if duplicate already exists
        $existingDuplicate = PaymentTransaction::where('reveral_transaction_id', $transaction->id)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();
        
        if ($existingDuplicate) {
            throw new \Exception('A reversal entry for this transaction already exists.');
        }
        $ledger = Ledger::where('trans_ref', $transaction->referenceNumber)->where('policy_id', $this->policyId)->where('trans_type','Payment')->whereNull('deleted_at')->first();
        $banking_id = $ledger->banking_id;
        $balance = Ledger::where('policy_id', $this->policyId)->orderBy('id', 'DESC')->first(array('balance'));
        if ($balance != null) {
            $balance = $balance->balance;
        } else {
            $balance = 0;
        }
        $date = Carbon::now()->format('Y-m-d');
        $created_date = Carbon::now()->format('Y-m-d');
        $data = array();
        $record = array();
        $subData = array();
        $subRecord = array();

        $record['customer_id'] = $policy->customer_id;
        $record['account_id'] = NULL;
        $record['policy_id'] = $policy->id;
        $record['claim_id'] = NULL;
        $record['banking_id'] = $banking_id;
        $record['account_name'] = NULL;
        $record['accounting_date'] = $date;
        $record['trans_type'] = 'Reverse Payment';
        $record['amount_type'] = NULL;
        $record['trans_ref'] = $transaction->referenceNumber;
        $record['orig_trans'] = $transaction->referenceNumber;
        $record['unallocated'] = NULL;
        $record['system_date'] = $date;
        $record['trans_sub_type'] = NULL;
        $record['eff_date'] = $date;
        $record['invoice_file'] = NULL;
        $record['invoice_date'] = NULL;
        $record['invoice_no'] = NULL;
        $record['invoice_amount'] = NULL;
        $record['premium'] = $ledger->premium;
        $record['other_charges'] = NULL;
        $record['due_amount'] = NULL;
        $record['pmts_adjust'] = NULL;
        $record['due_date'] = NULL;
        $record['status'] = 'Reversed';
        $record['credit'] = NULL;


        $reverseAmount = (float) str_replace(',', '', (string) $transaction->amount);
        $record['debit'] = $reverseAmount;
        if($balance < 0)
        {
            $record['balance'] = str_replace(',', '',number_format((abs($balance) + $reverseAmount), 2));
            $balance = str_replace(',', '',number_format((abs($balance) + $reverseAmount), 2));
        } else {
            $record['balance'] = str_replace(',', '',number_format(($balance + $reverseAmount), 2));
            $balance = str_replace(',', '',number_format(($balance + $reverseAmount), 2));
        }

        $data[] = $record;

        //----SUB-LEDGER
        $subRecord = array();
        $subRecord['customer_id'] = $policy->customer_id;
        $subRecord['account_id'] = NULL;
        $subRecord['policy_id'] = $policy->id;
        $subRecord['claim_id'] = NULL;
        $subRecord['banking_id'] = $banking_id;
        $subRecord['account_name'] = 'Accounts Receivable A/C';
        $subRecord['accounting_date'] = Carbon::parse($created_date);
        $subRecord['trans_type'] = 'Reverse Accounts Receivable';
        $subRecord['trans_ref'] = $transaction->referenceNumber;
        $subRecord['system_date'] = Carbon::parse($created_date);
        $subRecord['debit'] = $reverseAmount;
        $subRecord['credit'] = NULL;

        $subData[] = $subRecord;
        //---------------------------------//
        $subRecord = array();

        $subRecord['customer_id'] = $policy->customer_id;
        $subRecord['account_id'] = NULL;
        $subRecord['policy_id'] = $policy->id;
        $subRecord['claim_id'] = NULL;
        $subRecord['banking_id'] = $banking_id;
        $subRecord['account_name'] = 'Bank A/C';
        $subRecord['accounting_date'] = Carbon::parse($created_date);
        $subRecord['trans_type'] = 'Reverse Cash Received';
        $subRecord['trans_ref'] = $transaction->referenceNumber;
        $subRecord['system_date'] = Carbon::parse($created_date);
        $subRecord['debit'] = NULL;
        $subRecord['credit'] = $reverseAmount;

        $subData[] = $subRecord;

        //----SUB-LEDGER

        Ledger::insert($data);
        SubLedger::insert($subData);

        // $transaction->CompanyRef = 'Reversed';
        // $transaction->save();

        // Update original transaction with reversal information (do NOT overwrite amount)
        $transaction->is_reverse = 1;
        $transaction->reversal_date = Carbon::parse($this->reversal_date)->format('Y-m-d');
        $transaction->reversal_by = auth()->user()->id;
        $transaction->reversal_comment = $this->comments;
        $transaction->save();

        // Check if duplicate already exists to prevent double creation
        $existingDuplicate = PaymentTransaction::where('reveral_transaction_id', $transaction->id)
            ->whereNull('deleted_at')
            ->first();
        
        if (!$existingDuplicate) {
            // Create duplicate transaction entry (reversal entry)
            $duplicateTransaction = new PaymentTransaction();
            $duplicateTransaction->policy_id = $transaction->policy_id;
            $duplicateTransaction->policyNumber = $transaction->policyNumber;
            $duplicateTransaction->referenceNumber = $transaction->referenceNumber;
            $duplicateTransaction->amount = $transaction->amount; // Negative amount for reversal
            $duplicateTransaction->status = $transaction->status;
            $duplicateTransaction->paymentDate = $transaction->paymentDate;
            $duplicateTransaction->new_payment_date = $transaction->new_payment_date;
            $duplicateTransaction->paymentMethod = $transaction->paymentMethod;
            $duplicateTransaction->is_ledger = $transaction->is_ledger;
            $duplicateTransaction->is_refund = $transaction->is_refund;
            $duplicateTransaction->numberOfInstalmentsPaid = $transaction->numberOfInstalmentsPaid;
            $duplicateTransaction->paymentFrequency = $transaction->paymentFrequency;
            $duplicateTransaction->paymentLoggedBy = $transaction->paymentLoggedBy;
            $duplicateTransaction->cashRecipient = $transaction->cashRecipient;
            $duplicateTransaction->note = $transaction->note;
            $duplicateTransaction->CompanyRef = $transaction->CompanyRef;
            $duplicateTransaction->reveral_transaction_id = $transaction->id; // Store original transaction ID
            $duplicateTransaction->save();
        }

        $ledger->action_by = auth()->user()->id;
        $ledger->action_at = Carbon::now();
        $ledger->status = 'Reversed';
        $ledger->save();

        // $policy = Policy::findOrFail($this->policyId);
        // $transaction = PaymentTransaction::findOrFail($this->transactionLogId);
        // $ledger = Ledger::where('trans_ref', $transaction->referenceNumber)->first();
        // $banking_id = $ledger->banking_id;
        // $balance = Ledger::where('policy_id', $policy->id)->latest('id')->value('balance') ?? 0;
        // $now = Carbon::now();

        // $amount = (float) str_replace(',', '', $transaction->amount);

        // $newBalance = $balance < 0
        //     ? number_format(abs($balance) + $amount, 2, '.', '')
        //     : number_format($balance + $amount, 2, '.', '');

        // $data = [[
        //     'customer_id' => $policy->customer_id,
        //     'policy_id' => $policy->id,
        //     'banking_id' => $banking_id,
        //     'accounting_date' => $now->format('Y-m-d'),
        //     'trans_type' => 'Reverse Payment',
        //     'trans_ref' => $transaction->referenceNumber,
        //     'orig_trans' => $transaction->referenceNumber,
        //     'system_date' => $now->format('Y-m-d'),
        //     'eff_date' => $now->format('Y-m-d'),
        //     'premium' => $ledger->premium,
        //     'status' => 'Reversed',
        //     'debit' => $amount,
        //     'balance' => $newBalance
        // ]];

        // $subData = [
        //     [
        //         'customer_id' => $policy->customer_id,
        //         'policy_id' => $policy->id,
        //         'banking_id' => $banking_id,
        //         'account_name' => 'Accounts Receivable A/C',
        //         'accounting_date' => $now,
        //         'trans_type' => 'Reverse Accounts Receivable',
        //         'trans_ref' => $transaction->referenceNumber,
        //         'system_date' => $now,
        //         'debit' => $amount,
        //     ],
        //     [
        //         'customer_id' => $policy->customer_id,
        //         'policy_id' => $policy->id,
        //         'banking_id' => $banking_id,
        //         'account_name' => 'Bank A/C',
        //         'accounting_date' => $now,
        //         'trans_type' => 'Reverse Cash Received',
        //         'trans_ref' => $transaction->referenceNumber,
        //         'system_date' => $now,
        //         'credit' => $amount,
        //     ]
        // ];

        // Ledger::insert($data);
        // SubLedger::insert($subData);

        // $transaction->CompanyRef = 'Reversed';
        // $transaction->save();

        // $ledger->update([
        //     'action_by' => Auth::id(),
        //     'action_at' => now(),
        //     'status' => 'Reversed'
        // ]);
    }

    protected function handleReverseBeforeLedger()
    {
        if (!Auth::user()->hasPermissionTo('payment_delete_before_ledger')) {
            throw new \Exception('Sorry! You do not have permission to reverse before ledger.');
        }
        
        $policy = Policy::findOrFail($this->policyId);
        
        // Lock the transaction row to prevent concurrent reversals
        $transaction = PaymentTransaction::where('policyNumber', $policy->policyNumber)
            ->where('id', $this->transactionLogId)
            ->lockForUpdate()
            ->first();
        
        if (!$transaction) {
            throw new \Exception('Transaction not found.');
        }
        
        // Double-check if already reversed (race condition protection)
        if ($transaction->is_reverse == 1 || $transaction->CompanyRef == 'Reversed') {
            throw new \Exception('This transaction has already been reversed.');
        }
        
        // Check if duplicate already exists
        $existingDuplicate = PaymentTransaction::where('reveral_transaction_id', $transaction->id)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();
        
        if ($existingDuplicate) {
            throw new \Exception('A reversal entry for this transaction already exists.');
        }

        // Update original transaction with reversal information
        $transaction->is_reverse = 1;
        $transaction->reversal_date = Carbon::parse($this->reversal_date)->format('Y-m-d');
        $transaction->reversal_by = auth()->user()->id;
        $transaction->reversal_comment = $this->comments;
        $transaction->save();

        // $transaction->delete();

        // Check if duplicate already exists to prevent double creation
        $existingDuplicate = PaymentTransaction::where('reveral_transaction_id', $transaction->id)
            ->whereNull('deleted_at')
            ->first();
        
        if (!$existingDuplicate) {
            // Create duplicate transaction entry
            $duplicateTransaction = new PaymentTransaction();
            $duplicateTransaction->policy_id = $transaction->policy_id;
            $duplicateTransaction->policyNumber = $transaction->policyNumber;
            $duplicateTransaction->referenceNumber = $transaction->referenceNumber;
            $duplicateTransaction->amount = $transaction->amount;
            $duplicateTransaction->status = $transaction->status;
            $duplicateTransaction->paymentDate = $transaction->paymentDate;
            $duplicateTransaction->new_payment_date = $transaction->new_payment_date;
            $duplicateTransaction->paymentMethod = $transaction->paymentMethod;
            $duplicateTransaction->is_ledger = $transaction->is_ledger;
            $duplicateTransaction->is_refund = $transaction->is_refund;
            $duplicateTransaction->numberOfInstalmentsPaid = $transaction->numberOfInstalmentsPaid;
            $duplicateTransaction->paymentFrequency = $transaction->paymentFrequency;
            $duplicateTransaction->paymentLoggedBy = $transaction->paymentLoggedBy;
            $duplicateTransaction->cashRecipient = $transaction->cashRecipient;
            $duplicateTransaction->note = $transaction->note;
            $duplicateTransaction->CompanyRef = $transaction->CompanyRef;
            $duplicateTransaction->reveral_transaction_id = $transaction->id; // Store original transaction ID
            $duplicateTransaction->save();
        }

        return;
    }

    protected function handleReverseByReference($transaction)
    {
        if (!Auth::user()->hasPermissionTo('payment_delete_after_ledger')) {
            throw new \Exception('Sorry! You do not have permission to reverse transaction.');
        }

        $policy = Policy::where('id', $this->policyId)->first();
        if (!$policy) {
            throw new \Exception('Policy not found.');
        }

        $ledger = Ledger::where('trans_ref', $transaction->referenceNumber)
            ->where('policy_id', $this->policyId)
            ->where('trans_type','Payment')
            ->whereNull('deleted_at')
            ->first();

        if (!$ledger) {
            // If ledger entry not found, soft delete the transaction and create duplicate
            // Copy logic from handleReverseBeforeLedger()
            if (!Auth::user()->hasPermissionTo('payment_delete_before_ledger')) {
                throw new \Exception('Sorry! You do not have permission to reverse before ledger.');
            }

            $policy = Policy::findOrFail($this->policyId);
            
            // Update original transaction with reversal information
            $transaction->is_reverse = 1;
            $transaction->reversal_date = Carbon::parse($this->reversal_date)->format('Y-m-d');
            $transaction->reversal_by = auth()->user()->id;
            $transaction->reversal_comment = $this->comments;
            $transaction->save();

            // Soft delete the transaction
            $transaction->delete();

            // Check if duplicate already exists to prevent double creation
            $existingDuplicate = PaymentTransaction::where('reveral_transaction_id', $transaction->id)
                ->whereNull('deleted_at')
                ->first();
            
            if (!$existingDuplicate) {
                // Create duplicate transaction entry
                $duplicateTransaction = new PaymentTransaction();
                $duplicateTransaction->policy_id = $transaction->policy_id;
                $duplicateTransaction->policyNumber = $transaction->policyNumber;
                $duplicateTransaction->referenceNumber = $transaction->referenceNumber;
                $duplicateTransaction->amount = $transaction->amount;
                $duplicateTransaction->status = $transaction->status;
                $duplicateTransaction->paymentDate = $transaction->paymentDate;
                $duplicateTransaction->new_payment_date = $transaction->new_payment_date;
                $duplicateTransaction->paymentMethod = $transaction->paymentMethod;
                $duplicateTransaction->is_ledger = $transaction->is_ledger;
                $duplicateTransaction->is_refund = $transaction->is_refund;
                $duplicateTransaction->numberOfInstalmentsPaid = $transaction->numberOfInstalmentsPaid;
                $duplicateTransaction->paymentFrequency = $transaction->paymentFrequency;
                $duplicateTransaction->paymentLoggedBy = $transaction->paymentLoggedBy;
                $duplicateTransaction->cashRecipient = $transaction->cashRecipient;
                $duplicateTransaction->note = $transaction->note;
                $duplicateTransaction->CompanyRef = $transaction->CompanyRef;
                $duplicateTransaction->reveral_transaction_id = $transaction->id; // Store original transaction ID
                $duplicateTransaction->save();
            }

            return;
        }

        $banking_id = $ledger->banking_id;
        $balance = Ledger::where('policy_id', $this->policyId)->orderBy('id', 'DESC')->first(array('balance'));
        if ($balance != null) {
            $balance = $balance->balance;
        } else {
            $balance = 0;
        }

        $date = Carbon::now()->format('Y-m-d');
        $created_date = Carbon::now()->format('Y-m-d');
        $data = array();
        $record = array();
        $subData = array();
        $subRecord = array();

        $record['customer_id'] = $policy->customer_id;
        $record['account_id'] = NULL;
        $record['policy_id'] = $policy->id;
        $record['claim_id'] = NULL;
        $record['banking_id'] = $banking_id;
        $record['account_name'] = NULL;
        $record['accounting_date'] = $date;
        $record['trans_type'] = 'Reverse Payment';
        $record['amount_type'] = NULL;
        $record['trans_ref'] = $transaction->referenceNumber;
        $record['orig_trans'] = $transaction->referenceNumber;
        $record['unallocated'] = NULL;
        $record['system_date'] = $date;
        $record['trans_sub_type'] = NULL;
        $record['eff_date'] = $date;
        $record['invoice_file'] = NULL;
        $record['invoice_date'] = NULL;
        $record['invoice_no'] = NULL;
        $record['invoice_amount'] = NULL;
        $record['premium'] = $ledger->premium;
        $record['other_charges'] = NULL;
        $record['due_amount'] = NULL;
        $record['pmts_adjust'] = NULL;
        $record['due_date'] = NULL;
        $record['status'] = 'Reversed';
        $record['credit'] = NULL;

        $reverseAmount = (float) str_replace(',', '', (string) $transaction->amount);
        $record['debit'] = $reverseAmount;
        if($balance < 0)
        {
            $record['balance'] = str_replace(',', '',number_format((abs($balance) + $reverseAmount), 2));
            $balance = str_replace(',', '',number_format((abs($balance) + $reverseAmount), 2));
        } else {
            $record['balance'] = str_replace(',', '',number_format(($balance + $reverseAmount), 2));
            $balance = str_replace(',', '',number_format(($balance + $reverseAmount), 2));
        }

        $data[] = $record;

        //----SUB-LEDGER
        $subRecord = array();
        $subRecord['customer_id'] = $policy->customer_id;
        $subRecord['account_id'] = NULL;
        $subRecord['policy_id'] = $policy->id;
        $subRecord['claim_id'] = NULL;
        $subRecord['banking_id'] = $banking_id;
        $subRecord['account_name'] = 'Accounts Receivable A/C';
        $subRecord['accounting_date'] = Carbon::parse($created_date);
        $subRecord['trans_type'] = 'Reverse Accounts Receivable';
        $subRecord['trans_ref'] = $transaction->referenceNumber;
        $subRecord['system_date'] = Carbon::parse($created_date);
        $subRecord['debit'] = $reverseAmount;
        $subRecord['credit'] = NULL;

        $subData[] = $subRecord;
        //---------------------------------//
        $subRecord = array();

        $subRecord['customer_id'] = $policy->customer_id;
        $subRecord['account_id'] = NULL;
        $subRecord['policy_id'] = $policy->id;
        $subRecord['claim_id'] = NULL;
        $subRecord['banking_id'] = $banking_id;
        $subRecord['account_name'] = 'Bank A/C';
        $subRecord['accounting_date'] = Carbon::parse($created_date);
        $subRecord['trans_type'] = 'Reverse Cash Received';
        $subRecord['trans_ref'] = $transaction->referenceNumber;
        $subRecord['system_date'] = Carbon::parse($created_date);
        $subRecord['debit'] = NULL;
        $subRecord['credit'] = $reverseAmount;

        $subData[] = $subRecord;

        //----SUB-LEDGER

        Ledger::insert($data);
        SubLedger::insert($subData);

        // Update original transaction with reversal information
        $transaction->is_reverse = 1;
        $transaction->reversal_date = Carbon::parse($this->reversal_date)->format('Y-m-d');
        $transaction->reversal_by = auth()->user()->id;
        $transaction->reversal_comment = $this->comments;
        $transaction->save();

        // Check if duplicate already exists to prevent double creation
        $existingDuplicate = PaymentTransaction::where('reveral_transaction_id', $transaction->id)
            ->whereNull('deleted_at')
            ->first();
        
        if (!$existingDuplicate) {
            // Create duplicate transaction entry (reversal entry)
            $duplicateTransaction = new PaymentTransaction();
            $duplicateTransaction->policy_id = $transaction->policy_id;
            $duplicateTransaction->policyNumber = $transaction->policyNumber;
            $duplicateTransaction->referenceNumber = $transaction->referenceNumber;
            $duplicateTransaction->amount = $transaction->amount; // Negative amount for reversal
            $duplicateTransaction->status = $transaction->status;
            $duplicateTransaction->paymentDate = $transaction->paymentDate;
            $duplicateTransaction->new_payment_date = $transaction->new_payment_date;
            $duplicateTransaction->paymentMethod = $transaction->paymentMethod;
            $duplicateTransaction->is_ledger = $transaction->is_ledger;;
            $duplicateTransaction->is_refund = $transaction->is_refund;
            $duplicateTransaction->numberOfInstalmentsPaid = $transaction->numberOfInstalmentsPaid;
            $duplicateTransaction->paymentFrequency = $transaction->paymentFrequency;
            $duplicateTransaction->paymentLoggedBy = $transaction->paymentLoggedBy;
            $duplicateTransaction->cashRecipient = $transaction->cashRecipient;
            $duplicateTransaction->note = $transaction->note;
            $duplicateTransaction->CompanyRef = $transaction->CompanyRef;
            $duplicateTransaction->reveral_transaction_id = $transaction->id; // Store original transaction ID
            $duplicateTransaction->save();
        }

        $ledger->action_by = auth()->user()->id;
        $ledger->action_at = Carbon::now();
        $ledger->status = 'Reversed';
        $ledger->save();
    }

    public function render()
    {
        return view('v2.livewire.policy.transaction-logs.reverse-transaction-modal');
    }
}
