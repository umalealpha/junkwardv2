<div class="kt-portlet kt-portlet--height-fluid">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <div class="d-flex flex-row justify-content-between">
                <div class="col-4">
                    <h3 class="kt-portlet__head-title">
                        Scheduled Transactions
                    </h3>
                </div>
                <div class="col-2 text-right">
                    @if (auth::user()->hasPermissionTo('policy-suspend_payment_all_dpo') )
                        <button class="btn btn-danger m-3" id="suspendPaymentDpoAll" data-policyNumber="{{ $policy->policyNumber }}">Cancel all transactions</button>
                    @endif
                </div>
                <div class="col-2 text-right">
                    @if (auth::user()->hasPermissionTo('policy-add_schedule_transaction') )
                        <button class="btn btn-primary m-3" id="addScheduleTransaction" data-policyNumber="{{ $policy->policyNumber }}" data-toggle="modal" data-target="#addScheduleTransactionModel">Add schedule transaction</button>
                    @endif
                </div>
                <div class="col-2 text-right">
                    @if (auth::user()->hasPermissionTo('policy-update_billng_date_schedule_transaction') )
                        <button class="btn btn-primary m-3" id="updateBillingDateScheduleTransaction" data-policyNumber="{{ $policy->policyNumber }}" data-toggle="modal" data-target="#updateBillingDateScheduleTransactionModel">Update biilng date</button>
                    @endif
                </div>
                <div class="col-2 text-right">
                    @if (auth::user()->hasPermissionTo('policy-update_billng_date_schedule_transaction') )
                        <button class="btn btn-primary m-3" id="updatePremiumScheduleTransaction" data-policyNumber="{{ $policy->policyNumber }}" data-toggle="modal" data-target="#updatePremiumScheduleTransactionModel">Update Premium</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="kt-portlet__body">
        <!--begin: Datatable -->
        <table class="table table-striped table-bordered table-hover table-checkable" id="scheduled_trasactions_table">
            <thead>
                <tr>
                    <th>Id</th>
                    <th>Policy Number</th>
                    <th>Installment</th>
                    <th>Billing date</th>
                    <th>Amount</th>
                    <th>Reference Number</th>
                    <th>Retry count</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Added By</th>
                    @if (auth::user()->hasPermissionTo('policy-make_payment_dpo') || auth::user()->hasPermissionTo('policy-suspend_payment_dpo') )
                    <th>Action</th>
                    @endif
                    <th>Created at</th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <th>Id</th>
                    <th>Policy Number</th>
                    <th>Installment</th>
                    <th>Billing date</th>
                    <th>Amount</th>
                    <th>Reference Number</th>
                    <th>Retry count</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Added By</th>
                    @if (auth::user()->hasPermissionTo('policy-make_payment_dpo'))
                    <th>Action</th>
                    @endif
                    <th>Created at</th>
                </tr>
            </tfoot>
        </table>
        <!--end: Datatable -->
    </div>
</div>
