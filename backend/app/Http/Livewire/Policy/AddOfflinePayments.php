<?php

namespace AlphaDirect\Http\Livewire\Policy;

use AlphaDirect\Models\User;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\Policy;
use Livewire\Component;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

class AddOfflinePayments extends Component
{
    use WithFileUploads;

    public $policy;
    public $policyNumber;
    public $termId;
    public $actionId;
    public $previousActionId;

    public $paymentDate;
    public $paymentAmount;
    public $receiptNumber;
    public $paymentRecievedBy;
    public $numberOfInstalmentsPaid;
    public $paymentNote;
    public $updatePolicyStatus = false;
    public $payment_image;

    public $imageTitle;
    public $imageField;
    public $imageExist;

    protected $rules = [
        'paymentDate' => 'required',
        'paymentAmount' => 'required',
        'receiptNumber' => 'required|string',
        'paymentRecievedBy' => 'required|string',
        'numberOfInstalmentsPaid' => 'nullable|numeric',
        'paymentNote' => 'nullable|string',
    ];

    // protected $listeners = [
    //     'refreshParent'
    // ];

    // public function refreshParent($actionId = null)
    // {
    //     if ($actionId !== null) {
    //         $this->actionId = $actionId;
    //     }
    // }

    public function mount($imageExist = null)
    {
        $this->imageExist = $imageExist;
    }

    public function render()
    {
        return view('v2.livewire.policy.add-offline-payments');
    }

    public function submit()
    {
        $this->validate();
        if (Auth::user()->hasPermissionTo('offline-payments-list') || Auth::user()->hasPermissionTo('offline-payments-edit')) {
            // $policy = Policy::where('policyNumber', $this->policy->policyNumber)->first(array('id', 'policyNumber', 'customer_id', 'status', 'quoteNumber','product_id'));
            // if ($this->policy->status != 1) {
            //     $update = $this->updatePolicyDates($this->policy->policyNumber, 1);
            // }

            $entry = new PaymentTransaction();
            $entry->policyNumber = $this->policy->policyNumber;
            $entry->referenceNumber = Carbon::now()->timestamp.'/'.$this->receiptNumber;
            $entry->amount = (float)(str_replace(',', '', ($this->paymentAmount??''))) ?? null;
            $entry->status = 'SUCCESS';
            $entry->paymentDate = Carbon::createFromFormat('d/m/Y', $this->paymentDate)->format('Y-m-d H:i:s');
            $entry->new_payment_date = Carbon::createFromFormat('d/m/Y', $this->paymentDate)->format('Y-m-d');
            $entry->policy_id = $this->policy->id;
            $entry->paymentMethod = 'CASH';
            $entry->is_ledger = 0;
            $entry->cashRecipient = $this->paymentRecievedBy;
            // $entry->paymentFrequency = $this->paymentFreq;
            $entry->numberOfInstalmentsPaid = isset($this->numberOfInstalmentsPaid) ? $this->numberOfInstalmentsPaid : null;
            $entry->note = $this->paymentNote;
            $entry->paymentLoggedBy = auth()->user()->id;
            if ($this->payment_image) {
                $file = $this->payment_image;
                $name = preg_replace('/\s+/', '_', $file->getClientOriginalName());
                $filePath = 'PolicyPayment/' . $this->policy->policyNumber . '-' . $entry->referenceNumber;
                $tempPath = $file->getRealPath();
                Storage::disk('s3')->put($filePath, file_get_contents($tempPath), 'public');
                $entry->payment_proof_link = $filePath;
            }
            $entry->save();
        } else {
            $this->dispatchBrowserEvent('alert', ['type' => 'error',  'message' => 'Sorry! You do not have permission to access this page!']);
        }

        activity('Payment')
        ->performedOn($this->policy)
        ->causedBy(User::where('id', auth()->user()->id)->first())
        ->log('Payment Details Added.');

        $this->reset(['paymentDate', 'paymentAmount', 'receiptNumber', 'paymentRecievedBy', 'numberOfInstalmentsPaid', 'paymentNote', 'payment_image']);

        $this->dispatchBrowserEvent('alert', ['type' => 'success',  'message' => 'Payment details submitted successfully.']);

    }
}
