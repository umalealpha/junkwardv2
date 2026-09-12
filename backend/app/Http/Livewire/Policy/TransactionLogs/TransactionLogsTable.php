<?php

namespace AlphaDirect\Http\Livewire\Policy\TransactionLogs;

use AlphaDirect\Models\User;
use AlphaDirect\Policy;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use AlphaDirect\Models\PaymentTransactionArchive as PaymentTxArchive;
use AlphaDirect\PaymentTransaction;
use Carbon\Carbon;

class TransactionLogsTable extends DataTableComponent
{

        public Policy $policy;
        public $policydetails;
        public $restricted;

        protected $listeners = ['transactionRefunded' => 'refreshTable','transactionReverse' => 'refreshTable',];

        public function refreshTable()
        {
            $this->resetPage();
            $this->emitSelf('$refresh');
        }

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
                Column::make('Id','id')
                ->sortable()->searchable(),
                Column::make('Policy Number','policyNumber')
                ->sortable()->searchable(),
                Column::make('Reference Number','referenceNumber')
                ->sortable()->searchable(),
                Column::make('Payment Method','paymentMethod')
                ->sortable(),
                Column::make('Amount','amount')
                ->sortable()
                ->format(function ($value, $row) {
                    if ($row->amount != null) {
                        return  number_format((float)$row->amount ?? "", 2, '.', ',');
                    } else {
                        return '-';
                    }
                })->html(),
                Column::make('Payment Date','paymentDate')
                ->sortable(),
                Column::make('Payment Settlement Date','created_at')
                ->sortable()
                ->format(function ($value, $row) {
                    if ($row->created_at != null) {
                        return  Carbon::parse($row->created_at)->format('Y-m-d H:i');
                    } else {
                        return '-';
                    }
                })->html(),
                Column::make('No. of Installments Paid','numberOfInstalmentsPaid')
                ->sortable(),
                Column::make('Note','note')
                ->sortable(),
                Column::make('Status','status')
                ->sortable(),
                Column::make('Payment Recieved By','cashRecipient')
                ->sortable(),
                Column::make('Payment Added By','paymentLoggedBy')
                ->sortable()
                ->format(function($value, $row) {
                    if ($row->paymentLoggedBy != null) {
                        $user = User::where('id',$row->paymentLoggedBy)->first();
                        return $user->firstName.' '.$user->lastName;
                    } else {
                        return '-';
                    }
                })->html(),
                Column::make('Actions', 'id')
                    ->format(function ($value, $row) {
                        $action = '';

                        // View Payment Proof
                        if ($row->payment_proof_link) {
                            $action .= '<a href="' . \AlphaDirect\Helper::getCloudFrontURL($row->payment_proof_link) . '" target="_blank" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="View proof of payment">
                                <i class="la la-eye"></i>
                            </a>';
                        }
                        // Check if entry is reversed - if so, show only "Reversed" text, no delete buttons
                        if ($row->CompanyRef == 'Reversed' || $row->is_reverse == 1) {
                            $action .= '<span style="color: red;">Reversed</span>';
                        } elseif ($row->is_refund == 0 ) {
                            // Only show delete buttons if not reversed and not refunded
                            // $checkDate  = config('constants.policy.restrictionDate');
                            // $policyDate = Carbon::parse($row->paymentDate)->format('Y-m-d');
                            // if (strtotime($policyDate) >= strtotime($checkDate)) {
                            // Delete After Ledger via Livewire modal
                                if (\Auth::user()->hasPermissionTo('payment_delete_after_ledger') && $row->is_ledger == 1) {
                                    $action .= '<a href="#" wire:click.prevent="$emit(\'openReverseModal\', ' . $row->id . ', ' . $this->policy->id . ', ' . $this->policy->customer_id . ', ' . $this->policy->product_id . ', \'admin.policy.transaction_log_delete\')" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Delete">
                                        <i class="la la-trash" style="color:red;"></i>
                                    </a>';
                                }

                                // Delete Before Ledger via Livewire modal
                                if (\Auth::user()->hasPermissionTo('payment_delete_before_ledger') && $row->is_ledger != 1) {
                                    $action .= '<a href="#" wire:click.prevent="$emit(\'openReverseModal\', ' . $row->id . ', ' . $this->policy->id . ', ' . $this->policy->customer_id . ', ' . $this->policy->product_id . ', \'admin.policy.transaction_log_delete2\')" class="btn btn-sm btn-clean btn-icon btn-icon-md" title="Delete Before Ledger">
                                        <i class="la la-trash" style="color:blue;"></i>
                                    </a>';
                                }
                            // }
                        }

                        if ($row->is_refund == 1) {
                            $action .= '<span style="color: red;">Refunded</span>';
                        }

                        return $action;
                    })->html(),

            ];
        }

        // public function builder(): Builder
        // {
        //     $query1 = PaymentTransaction::where('policyNumber', $this->policy->policyNumber)
        //     ->where('status', '!=', 'CANCELLED')
        //     ->orderBy('created_at', 'desc');

        //     $query2 = PaymentTxArchive::where('policyNumber', $this->policy->policyNumber)
        //     ->where('status', '!=', 'CANCELLED')
        //     ->orderBy('created_at', 'desc');

        //     $combined_query = $query1->orWhere(function ($query) use ($query2) {
        //     $query->whereIn('id', $query2->pluck('id'));
        //     });

        //     return $combined_query;

        // }

        public function builder(): Builder
        {
            $query1 = PaymentTransaction::select([
                'id',
                'policyNumber',
                'referenceNumber',
                'paymentMethod',
                'amount',
                'paymentDate',
                'created_at',
                'numberOfInstalmentsPaid',
                'note',
                'status',
                'cashRecipient',
                'paymentLoggedBy',
                'payment_proof_link',
                'CompanyRef',
                'is_ledger',
                'is_refund',
                'is_reverse'
            ])
            ->where('policyNumber', $this->policy->policyNumber)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'CANCELLED')
            ->whereNull('reveral_transaction_id') // Exclude duplicate reversal entries
            ->orderBy('created_at', 'desc');

            $query2 = PaymentTxArchive::select([
                'id',
                'policyNumber',
                'referenceNumber',
                'paymentMethod',
                'amount',
                'paymentDate',
                'created_at',
                'numberOfInstalmentsPaid',
                'note',
                'status',
                'cashRecipient',
                'paymentLoggedBy',
                'payment_proof_link',
                'CompanyRef',
                'is_ledger',
                'is_refund',
                'is_reverse'
            ])
            ->where('policyNumber', $this->policy->policyNumber)
            ->where('status', '!=', 'CANCELLED')
            ->whereNull('deleted_at')
            ->whereNull('reveral_transaction_id') // Exclude duplicate reversal entries
            ->orderBy('created_at', 'desc');

            $combined_query = $query1->orWhere(function ($query) use ($query2) {
                $query->whereIn('id', $query2->pluck('id'));
            });

            return $combined_query;
        }

}
