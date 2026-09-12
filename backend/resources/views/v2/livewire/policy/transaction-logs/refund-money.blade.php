<div>
    <div class="row">
        <div class="col-12 py-3">
            <button type="button" class="btn btn-primary" wire:click ="setModalData">
                Refund Money
            </button>
        </div>
    </div>

    <div wire:loading.delay wire:target="setModalData" class="text-center my-3" id="refund-loading">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    <br>
    {{-- Summary of Success Transactions --}}
    @php

        $refundedRefs = \AlphaDirect\PaymentTransaction::where('policyNumber', $this->policyNumber)
        ->where('is_refund', 1)
        ->pluck('referenceNumber')
        ->merge(
            \AlphaDirect\Models\PaymentTransactionArchive::where('policyNumber', $this->policyNumber)
                ->where('is_refund', 1)
                ->pluck('referenceNumber')
        )
        ->unique()
        ->toArray();

        $successTransactions = \AlphaDirect\PaymentTransaction::where('policyNumber', $this->policyNumber)
            ->whereIn('status', ['Success', 'SUCCESS', 'success', 1])
            ->whereNull('deleted_at')
            // ->where(function ($q) {
            //     $q->whereNull('is_refund')->orWhere('is_refund', '!=', 1);
            // })
            ->whereNotIn('referenceNumber', $refundedRefs)
            ->where(function ($q) {
                $q->whereNull('CompanyRef')->orWhere('CompanyRef', '!=', 'Reversed');
            })
            ->where(function ($q) {
                $q->whereNull('is_reverse')->orWhere('is_reverse', '!=', 1);
            })
            ->whereNull('reveral_transaction_id') // Exclude duplicate reversal entries
            ->get()
            ->merge(
                \AlphaDirect\Models\PaymentTransactionArchive::where('policyNumber', $this->policyNumber)
                    ->whereIn('status', ['Success', 'SUCCESS', 'success', 1])
                    ->whereNull('deleted_at')
                    // ->where(function ($q) {
                    //     $q->whereNull('is_refund')->orWhere('is_refund', '!=', 1);
                    // })
                    ->whereNotIn('referenceNumber', $refundedRefs)
                    ->where(function ($q) {
                        $q->whereNull('CompanyRef')->orWhere('CompanyRef', '!=', 'Reversed');
                    })
                    ->where(function ($q) {
                        $q->whereNull('is_reverse')->orWhere('is_reverse', '!=', 1);
                    })
                    ->whereNull('reveral_transaction_id') // Exclude duplicate reversal entries
                    ->get()
            );

        $successAmount = $successTransactions->sum('amount');
    @endphp

    <div class="row">
        <div class="col-4 mt-2">
            <h5>Success Transaction Count: {{ $successTransactions->count() }}</h5>
        </div>
        <div class="col-4 mt-2">
            <h5>Total Successful Transactions In Amount: P {{ number_format($successAmount, 2) }}</h5>
        </div>
    </div>

    {{-- Summary of Failed Transactions --}}
    @php
        $failedTransactions = \AlphaDirect\PaymentTransaction::where('policyNumber', $this->policyNumber)
            ->whereNotIn('status', ['Success', 'SUCCESS', 'success', 1])
            ->whereNull('deleted_at')
            ->get()
            ->merge(
                \AlphaDirect\Models\PaymentTransactionArchive::where('policyNumber', $this->policyNumber)
                    ->whereNotIn('status', ['Success', 'SUCCESS', 'success', 1])
                    ->whereNull('deleted_at')
                    ->get()
            );
        $failedAmount = $failedTransactions->sum('amount');
    @endphp

    <div class="row">
        <div class="col-4 mt-2">
            <h5>Failed Transaction Count: {{ $failedTransactions->count() }}</h5>
        </div>
        <div class="col-4 mt-2">
            <h5>Total Failed Transactions In Amount: P {{ number_format($failedAmount, 2) }}</h5>
        </div>
    </div>
    <div class="row">
        <div class="col-4 mt-2">
            <h5>Total Refunded Transactions Count: {{ $totalRefundCount }}</h5>
        </div>
        <div class="col-4 mt-2">
            <h5>Total Refund Transactions In Amount: P {{ number_format($totalRefundAmount, 2) }}</h5>
        </div>
    </div>
    {{-- Summary of Reversed Transactions --}}
    @php
        $reversedTransactions = \AlphaDirect\PaymentTransaction::where('policyNumber', $this->policyNumber)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('is_reverse', 1)
                  ->orWhere('CompanyRef', 'Reversed');
            })
            ->whereNull('reveral_transaction_id') // Exclude duplicate reversal entries
            ->get()
            ->merge(
                \AlphaDirect\Models\PaymentTransactionArchive::where('policyNumber', $this->policyNumber)
                    ->whereNull('deleted_at')
                    ->where(function ($q) {
                        $q->where('is_reverse', 1)
                          ->orWhere('CompanyRef', 'Reversed');
                    })
                    ->whereNull('reveral_transaction_id') // Exclude duplicate reversal entries
                    ->get()
            );
        $reversedAmount = $reversedTransactions->sum('amount');
    @endphp

    <div class="row">
        <div class="col-4 mt-2">
            <h5>Total Reversal Transactions Count: {{ $reversedTransactions->count() }}</h5>
        </div>
        <div class="col-4 mt-2">
            <h5>Total Reversal Transactions In Amount: P {{ number_format($reversedAmount, 2) }}</h5>
        </div>
    </div>

    {{-- Total Balance (Total successful amount minus refund, failed, and reversal amounts) --}}
        @php
            // Total balance = Total successful amount - refund amount - failed amount - reversal amount
            $totalBalance = $successAmount - $this->totalRefundAmount - $failedAmount - $reversedAmount;
        @endphp

        <div class="row">
            <div class="col-4 mt-2">
                <h5>Total Balance: P {{ number_format($totalBalance, 2) }}</h5>
            </div>
        </div>

{{-- <div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <label>By Payment Status</label>
                <select name="FilterBy" class="form-control" id="FilterBy">
                    <option value="-1">All</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <div class="col-md-4">
                <label>By Refund Status</label>
                <select name="FilterByRefund" class="form-control" id="FilterByRefund">
                    <option value="-1">All</option>
                    <option value="1">Is Refund</option>
                </select>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4">
                <label>Date From</label>
                <input type="text" class="form-control trasectiondate restrictDate" id="filterDateFrom" placeholder="Select date" name="filterDateFrom" autocomplete="off">
            </div>
            <div class="col-md-4">
                <label>Date To</label>
                <input type="text" class="form-control trasectiondate restrictDate" id="filterDateTo" placeholder="Select date" name="filterDateTo" autocomplete="off">
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-3 offset-md-9 text-right">
                <button class="btn btn-primary" id="Filterdate">Apply Filters</button>
            </div>
        </div>
    </div>
</div> --}}

{{-- <div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <label>By Payment Status</label>
                <select wire:model="filterBy" class="form-control">
                    <option value="-1">All</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <div class="col-md-4">
                <label>By Refund Status</label>
                <select wire:model="filterByRefund" class="form-control">
                    <option value="-1">All</option>
                    <option value="1">Is Refund</option>
                </select>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4">
                <label>Date From</label>
                <input type="text" class="form-control"
                       wire:model.defer="filterDateFrom"
                       placeholder="YYYY-MM-DD"
                       autocomplete="off">
            </div>
            <div class="col-md-4">
                <label>Date To</label>
                <input type="text" class="form-control"
                       wire:model.defer="filterDateTo"
                       placeholder="YYYY-MM-DD"
                       autocomplete="off">
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-3 offset-md-9 text-right">
                <button class="btn btn-primary" wire:click="applyFilters">Apply Filters</button>
            </div>
        </div>
    </div>
</div> --}}

    {{-- <div class="modal" id="refundModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit.prevent="submit" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title">Refund Money</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        @csrf
                        <div class="form-group">
                            <label>Reference Number</label>
                            <input type="text" class="form-control" wire:model.lazy="reference_number">
                            @error('reference_number') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label>Date of Refund</label>
                            <input type="date" class="form-control" wire:model.lazy="date_of_refund">
                            @error('date_of_refund') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number" step="0.01" class="form-control" wire:model.lazy="amount">
                            @error('amount') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label>Reason</label>
                            <textarea class="form-control" wire:model.lazy="reason" rows="2"></textarea>
                            @error('reason') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label>Refunded By</label>
                            <input type="text" class="form-control" wire:model.lazy="refunded_by">
                            @error('refunded_by') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Process Refund</button>
                    </div>
                </form>
            </div>
        </div>
    </div> --}}

<div class="modal" id="refundModal" tabindex="-1" role="dialog" wire:ignore.self>
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form wire:submit.prevent="submit" id="refundForm" autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title" id="refundModalTitle">Refund Money</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <input type="hidden" wire:model="policyNumber">

                    <div class="form-group col-12">
                        <label for="reference_number">Reference Number</label>
                        <input type="text" class="form-control"
                               wire:model.defer="reference_number"
                               placeholder="Enter reference number"
                               maxlength="30" minlength="1">
                        @error('referenceNumber') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    @if(\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('back-dated-transactions'))
                        <div class="form-group col-12">
                            <label for="date_of_refund">Date of refund</label>
                            <input type="text" class="form-control kt_datepicker_123"
                                   wire:model.defer ="date_of_refund"
                                   placeholder="YYYY-MM-DD"
                                   autocomplete="off">
                        </div>
                    @else
                        <p>Note: You can select Date of Payment for current month.</p>
                        <div class="form-group col-12">
                            <label for="date_of_refund">Date of refund</label>
                            <input type="text" class="form-control"
                                   wire:model.defer ="date_of_refund"
                                   id="date_of_refund_for_current_month_refund"
                                   placeholder="YYYY-MM-DD"
                                   autocomplete="off">
                        </div>
                    @endif

                    <div class="form-group col-12">
                        <label for="amount">Amount</label>
                        <input type="number" step="0.01" class="form-control"
                               wire:model.defer="amount"
                               placeholder="Enter refund amount"
                               >
                        @error('amount') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group col-12">
                        <label for="reason">Reason</label>
                        <textarea class="form-control" rows="2" cols="8" maxlength="300"
                                  wire:model.defer="reason"
                                  placeholder="Enter reason for refund"
                                  required></textarea>
                        @error('reason') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group col-12">
                        <label for="refunded_by">Refunded by</label>
                        <input type="text" class="form-control"
                               wire:model.defer="refunded_by"
                               placeholder="Enter name"
                               maxlength="30" minlength="1">
                        @error('refundedBy') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>


    {{-- <div class="modal" id="refundModal" tabindex="-1" role="dialog">
        <div class="modal-dialog"  role="document">
            <div class="modal-content">
                <form action="{{ route('admin.policy.makeRefund') }}" method="post" id="refundForm" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title" id="refundModalTitle">Refund Money</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        @csrf
                        <input type="hidden" name="policy_number" id="policyNumber" value="{{ $this->policyNumber }}">
                        <div class="form-group col-12">
                            <label for="reference_number">Reference Number</label>
                            <input type="text" class="form-control text-uppercase" name="reference_number"
                                id="reference_number" maxlength="30" minlength="1">
                        </div>

                    @if(\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('back-dated-transactions'))
                        <div class="form-group col-12">
                            <label for="date_of_refund">Date of refund</label>
                            <input type="text" class="form-control kt_datepicker_1" name="date_of_refund"
                                id="date_of_refund" autocomplete="off">
                        </div>
                    @else
                        <p>Note : You can select Date of Payment for current month.</p>
                        <div class="form-group col-12">
                            <label for="date_of_refund">Date of refund</label>
                            <input type="text" class="form-control" name="date_of_refund"
                                id="date_of_refund_for_current_month_refund" autocomplete="off">
                        </div>
                    @endif

                        <div class="form-group col-12">
                            <label for="amount">Amount</label>
                            <input type="number" step="0.01" class="form-control" name="amount" id="amount" min="1">
                        </div>
                        <div class="form-group col-12">
                            <label for="reason">Reason </label>
                            <textarea class="form-control" name="reason" id="reason" rows="2" cols="8" maxlength="300"
                                required></textarea>

                        </div>
                        <div class="form-group col-12">
                            <label for="refunded_by">Refunded by</label>
                            <input type="text" class="form-control" name="refunded_by" id="refunded_by" maxlength="30"
                                minlength="1">

                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div> --}}
    @if (session()->has('message'))
        <div class="alert alert-success mt-3">{{ session('message') }}</div>
    @endif

    @livewire('policy.transaction-logs.reverse-transaction-modal')
</div>
