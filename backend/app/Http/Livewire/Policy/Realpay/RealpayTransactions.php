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
use Carbon\Carbon;

class RealpayTransactions extends DataTableComponent
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
            Column::make('Instalment Sequence','InstalmentSequence')
            ->sortable()->searchable(),
            Column::make('Instalment Reference Number','InstalmentReferenceNumber')
            ->sortable()->searchable(),
            Column::make('CTC Amount','CTCAmount')
            ->sortable(),
            Column::make('Action Date','InstalmentActionDate')
            ->sortable()->searchable(),
            Column::make('Tracking Code','TrackingCode')
            ->sortable(),
            Column::make('Installment Amount','InstalmentAmount')
            ->sortable(),
            // ->format(function ($value, $row) {
            //     if ($row->amount != null) {
            //         return  number_format((float)$row->amount ?? "", 2, '.', ',');
            //     } else {
            //         return '-';
            //     }
            // })->html(),
            Column::make('Bank Response','instalmentResponse')
            ->sortable(),
            Column::make('Installment Status','InstalmentStatus')
            ->sortable()
            ->format(function ($value, $row) {
                switch ($row->InstalmentStatus){
                        case 'S':
                            $status =  '<span class="kt-font-bold kt-font-success">Success</span>';
                            break;
                        case 'W':
                            $status =  '<span class="kt-font-bold kt-font-info">Processing</span>';
                            break;

                        case 'F':
                            $status =  '<span class="kt-font-bold kt-font-danger">Failed</span>';
                            break;
                        case 'R':
                            $status =  '<span class="kt-font-bold kt-font-info">Retry</span>';
                            break;
                        case 'A':
                            $status =  '<span class="kt-font-bold kt-font-info">Active</span>';
                            break;
                        case 'I':
                            $status =  '<span class="kt-font-bold kt-font-info">Cancelled</span>';
                            break;
                        case 'E':
                            $status =  '<span class="kt-font-bold kt-font-warning">Error</span>';
                            break;
                        default :
                            $status =  '<span class="kt-font-bold kt-font-danger">Status not found</span>';
                            break;
                    }

                    return $status;
            })->html(),
            Column::make('Retry Count','retry_count')
            ->sortable(),
            // Column::make('Action','action')
            // ->sortable()
            // ->format(function($value, $row) {
            //     $policyProd = Policy::where('policyNumber',$row->clientNumber)->first('product_id');
            //     switch ($row->InstalmentStatus) {
            //         case 'S':
            //             $actions = '<span class="kt-font-bold kt-font-success">Success</span>';
            //             break;
            //         case 'W':
            //             $actions = '<span class="kt-font-bold kt-font-info">Processing</span>';
            //             break;

            //         case 'F':
            //             $d = $row->toArray();
            //             if ($policyProd->product_id == 3) {
            //                 $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
            //                 <span class="kt-opacity-11" id="">Retry</span>
            //                 </a>';
            //             } else {
            //                 $actions = '<a href="' . route('admin.updateStatusForInstantProduct',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
            //                 <span class="kt-opacity-11" id="">Retry</span>
            //                 </a>';
            //             }
            //             break;
            //         case 'R':
            //             $d = $row->toArray();
            //             if ($policyProd->product_id == 3) {
            //                 $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" class="btn btn-sm btn-elevate btn-danger btn-elevate" title="Cancel Instalment">
            //                 <span class="kt-opacity-11" id="">Cancel</span>
            //                 </a>';
            //             } else {
            //                 $actions = '<a href="' . route('admin.updateStatusForInstantProduct',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" class="btn btn-sm btn-elevate btn-danger btn-elevate" title="Cancel Instalment">
            //                 <span class="kt-opacity-11" id="">Cancel</span>
            //                 </a>';
            //             }
            //             break;
            //         case 'A':
            //             $d = $row->toArray();
            //             if ($policyProd->product_id == 3) {
            //                 $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" value="'.$d['InstalmentReferenceNumber'].'" class="btn btn-sm btn-elevate btn-danger btn-elevate confirm-cancel" title="Cancel Instalment">
            //                 <span class="kt-opacity-11" id="">Cancel</span>
            //                 </a>';
            //             } else {
            //                 $actions = '<a href="' . route('admin.updateStatusForInstantProduct',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'I']) . '" value="'.$d['InstalmentReferenceNumber'].'" class="btn btn-sm btn-elevate btn-danger btn-elevate confirm-cancel" title="Cancel Instalment">
            //                 <span class="kt-opacity-11" id="">Cancel</span>
            //                 </a>';
            //             }
            //             break;
            //         case 'I':
            //             $actions = '<span class="kt-font-bold kt-font-info">Cancelled</span>';
            //             break;
            //         case 'E':
            //             $d = $row->toArray();
            //             if ($policyProd->product_id == 3) {
            //                 $actions = '<a href="' . route('admin.updateStatus',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
            //                 <span class="kt-opacity-11" id="">Retry</span>
            //                 </a>';
            //             } else {
            //                 $actions = '<a href="' . route('admin.updateStatusForInstantProduct',['ref' => $d['InstalmentReferenceNumber'], 'Status' => 'R']) . '" class="btn btn-sm btn-elevate btn-warning btn-elevate" title="Cancel Instalment">
            //                 <span class="kt-opacity-11" id="">Retry</span>
            //                 </a>';
            //             }
            //             break;
            //         default :
            //             $actions = '<span class="kt-font-bold kt-font-danger">Status not found</span>';
            //             break;
            //     }
            //     return $actions;
            // })->html(),

        ];
    }

    public function builder(): Builder
    {
        $policy = Policy::where('id',$this->policy->id)->first(array('id','policyNumber'));
        $clientContracts = RealpayClientContracts::where('policy_id',$this->policy->id)->where('status',1)->orderBy('id','desc')->first();

        if($clientContracts == null){
            return RealpayContractInstallments::where('contractNumber',$this->policy->id);
        }else{
            return RealpayContractInstallments::where('contractNumber',$clientContracts->contract_number);
        }

        // return $data;

    }


    // public function render()
    // {
    //     return view('v2.livewire.policy.realpay.realpay-transactions');
    // }
}
