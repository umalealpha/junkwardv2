<?php

namespace AlphaDirect\Http\Livewire\Policy\Realpay;

// use Livewire\Component;
use AlphaDirect\Models\User;
use AlphaDirect\Policy;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use AlphaDirect\Models\PaymentTransactionArchive as PaymentTxArchive;
use AlphaDirect\PaymentTransaction;
use AlphaDirect\RealpayClientContracts;
use AlphaDirect\RealpayContractInstallments;
use AlphaDirect\RealpayPaymentRequest;
use Carbon\Carbon;

class RealpayContractLists extends DataTableComponent
{
    public Policy $policy;
    public $policydetails;

    public function boot(): void
    {
        config(['livewire-tables.theme' => 'bootstrap-5']);
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make('ID','id')
            ->sortable()->searchable(),
            Column::make('Client Number','client_number')
            ->sortable()->searchable(),
            Column::make('Contract Number','contract_number')
            ->sortable(),
            Column::make('Status','status')
            ->sortable()->searchable()
            ->format(function($value, $row) {
                $realpayInstallments = RealpayContractInstallments::where('contractNumber',$row->contract_number)->get();
                $status = '';
                foreach ($realpayInstallments as $key => $contractInstl) {
                    if ($contractInstl['InstalmentStatus'] == 'I') {
                        $status =  '<span class="kt-font-bold kt-font-danger">Cancelled</span>';
                    } else {
                        $status =  '<span class="kt-font-bold kt-font-info">Active</span>';
                    }
                }
                return $status;
            })->html(),
            // Column::make('Action','action')
            // ->sortable()
            // ->format(function($value, $row) {
            //     $realpayInstallments = RealpayContractInstallments::where('contractNumber',$row->contract_number)->get();
            //     $status = '';
            //     $actions = '-';
            //     foreach ($realpayInstallments as $key => $contractInstl) {
            //         if ($contractInstl['InstalmentStatus'] == 'I') {
            //             $status =  'Cancelled';
            //         } else {
            //             $status =  'Active';
            //         }
            //     }

            //     if ($status != 'Cancelled') {
            //         $policyProd = Policy::where('id',$row->policy_id)->first('product_id');
            //         if ($policyProd->product_id == 3) {
            //             $actions = '<a href="' . route('admin.cancelContract',$row->id) . '" class="btn btn-sm btn-elevate btn-danger btn-elevate" title="Cancel Contract">
            //                 <span class="kt-opacity-11" id="">Cancel</span>
            //             </a>';
            //         } else {
            //             $actions = '<a href="' . route('admin.cancelContractForInsProd',$row->id) . '" class="btn btn-sm btn-elevate btn-danger btn-elevate" title="Cancel Contract">
            //                 <span class="kt-opacity-11" id="">Cancel</span>
            //             </a>';
            //         }
            //     }

            //     return $actions;
            // })->html(),

        ];
    }

    public function builder(): Builder
    {
        $client = RealpayPaymentRequest::where('policy_id', $this->policy->id)->first(array('clientNumber'));
        $realpayContracts = null;
        $realpayContracts = RealpayClientContracts::where('policy_id',$this->policy->id);

        return $realpayContracts;

    }

    // public function render()
    // {
    //     return view('v2.livewire.policy.realpay.realpay-contract-lists');
    // }
}
