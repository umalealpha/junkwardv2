<div class="kt-portlet kt-portlet--height-fluid">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">
               Reserves/Payments
            </h3>
        </div>
        @if ($claims && $claims->status != 'Closed')
            <div class="col-lg-5">
                <button class="btn btn-brand" style="float:right; margin-top: 2%;" id="addButton" type="button" data-toggle="collapse" data-target="#reserveCollapse" aria-expanded="false" aria-controls="collapseExample"> Add  </button>
            </div>
        @endif
    </div>
    <div class="kt-portlet__body">
        <div class="col-md-12">
            <div class="row">
                <div class="form-group col-6">
                    <h4>Total Reserve : P {!! $reserve_amt !!}</h4>
                </div>
                <div class="form-group col-6">
                    <h4>Total Payment : P {!! $payment_amt !!}</h4>
                </div>
            </div>
        </div>

        <div class="form-group row collapse" id="reserveCollapse">
            <div class="col-md-12">
                <div class="kt-checkbox-inline">
                    <form action="{{ url('admin/claims/storeReserve/'.$claims->id) }}" method="POST" id="reserveForm" enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <input type="hidden" id="claim_id" value= "{{ $claims->id }}" />
                        <input type="hidden" name="product_id" value= "{{ $policyProduct->product_id }}" />

                        @if ($policy->product_id == 7 || $policy->product_id == 8)
                            <!-- Hidden field to store combined JSON data -->
                            <input type="hidden" name="combined_data" id="combined_data">
                        @endif
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="exampleSelect1">Date:</label>
                                    <input type="text" class="form-control  kt_datepicker_1"  autocomplete="off" placeholder="Please select Date" name="date">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label> Transaction Type:</label>
                                    <select class="form-control kt_selectpicker" onchange="TransactionType(this);" data-live-search="true" title="Please select Transaction type" placeholder="Please select transaction type" id="trans_type" name="trans_type">
                                        @foreach( $transTypes as  $transType)
                                            <option value="{{$transType->id}}">{{$transType->value}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label> Address:</label>
                                    <input type="text" class="form-control" name="address" title="Please enter  address" placeholder="Please enter address" maxlength="60" >
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label> Select Payee:</label>
                                    <select class="form-control kt_selectpicker" data-live-search="true" title="Please select payee" name="payee">
                                        @foreach( $transPayees as  $transPayee)
                                            <option value="{{ $transPayee->id}}">{{ $transPayee->supplierName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4" id="lossReserveDiv" style="display:none;">
                                <div class="form-group">
                                <label> Transaction Subtype:</label>
                                <select class="form-control kt_selectpicker" data-live-search="true" title="Please select transaction subtype" name="lossreserve_trans_subType" id="lossreserve_trans_subType">
                                    @foreach( $transSubTypes as  $transSubType)
                                        <option value="{{$transSubType->id}}">{{ $transSubType->value }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row" id="subTypeDiv" style="display:none">
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label for="exampleSelect1">Invoice Date:</label>
                                    <input type="text" class="form-control  kt_datepicker_1"  autocomplete="off" placeholder="Please enter invoice date" name="invoiceDate" id="invoiceDate">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label for="exampleSelect1">Invoice Due Date:</label>
                                    <input type="text" class="form-control  kt_datepicker_1"  placeholder="Please enter due date" autocomplete="off" name="dueDate" id="dueDate">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label> Invoice No:</label>
                                    <input type="text" class="form-control" name="invoiceNum" id="invoiceNum" title="Please enter invoice number" placeholder="Please enter  invoice number"    maxlength="60" >
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label> Memo On Check:</label>
                                    <input type="text" class="form-control" name="memoOnCheck" id="memoOnCheck" title="Please enter Memo On Check" placeholder="Please enter Memo On Check"    maxlength="60" >
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label> Transaction Subtype:</label>
                                    <select class="form-control kt_selectpicker" data-live-search="true" title="Please select transaction subtype" name="trans_subType" id="trans_subType">
                                        @foreach( $transSubTypes as  $transSubType)
                                            <option value="{{$transSubType->id}}">{{ $transSubType->value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label> Description:</label>
                                    <input type="text" class="form-control" name="description" id="description" title="Please enter description" placeholder="Please enter description"    maxlength="60" >
                                </div>
                            </div>
                            <div class="col-4">
                                <label class="kt-checkbox kt-checkbox--brand">Below amount include VAT
                                    <input id="amountVat" type="checkbox" name="includeVat" value="1">
                                    <span></span>
                                </label>
                            </div>
                            <div class="col-4">
                                <label class="kt-checkbox kt-checkbox--brand">Credit Note
                                    <input id="creditNote" type="checkbox" name="creditNote" value="1">
                                    <span></span>
                                </label>
                            </div>

                        </div>

                        <div class="search-container-reserves" style="display: none;">
                            <input
                            type="text"
                            class="search-input-reserves"
                            id="searchInputReserves"
                            placeholder="Search..."
                            >
                        </div>
                        @if ($policy->product_id == 7 || $policy->product_id == 8)
                            <table class="kt-invoice-v2__summary table table-striped m-table" id="coverageTable" style="display: none;">
                                <thead class="kt-invoice-v2__summary-header">
                                <tr>
                                    <th>Coverage Name</th>
                                    <th>Coverage Limit</th>
                                    <th>Reserve Created</th>
                                    <th>Payment Created</th>
                                    <th>Balance</th>
                                    <th id="allocationText" class="allocation" name="allocation">Payment Allocation</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if($coverages != null)
                                    @foreach($groupedRiskAddCoverages as $riskAddress => $coverages)
                                        <!-- Modified: Add a row for the risk address name -->
                                        <tr>
                                            <td colspan="6" class="text-center font-weight-bold" style="background-color: #f1f1f1;">
                                                {{ $riskAddress }}
                                            </td>
                                        </tr>

                                        @foreach($coverages as $coverage)
                                        <tr class="table-row">
                                            <td><input type="hidden" name="coverage_id[]" value="{!! $coverage->coverage_id !!}" /><input type="hidden" name="coverage_name[]" value="{!! $coverage->coverage_name !!}" />{{ $coverage->coverage_name }}</td>
                                            {{--<td>-</td>--}}
                                            <td class="coverage-limit"><input type="hidden" name="coverageLimit[]" value="{!! $coverage->coverage_limit !!}" />{!! $coverage->coverage_limit !!}</td>
                                            <td>{!! $coverage->reserve_amt !!}</td>
                                            <td>{!! $coverage->payment_amt !!}</td>
                                            <td>{!! $coverage->reserve_amt - $coverage->payment_amt !!}</td>
                                            {{--<td><span id="initReserve"></span></td>--}}
                                            <td><input type="text" name="amt[]" class="amt" autocomplete="off"/>
                                            <p id="sumError" style="display:none;color:red">Reserve allocation should be equal to <span class="sum_value"></span> or less  </p>
                                            <p id="sumError2" style="display:none;color:red">Sum of reserve allocation should be equal to <span class="sum_value"></span> or less  </p>
                                            <p id="checkSum"  style="display:none;color:red">Reserves/payments values can not be 0.</p>
                                            <p id="checkSum2"  style="display:none;color:red">Payment should be less than Balance.</p></td>
                                        </tr>
                                        @endforeach
                                    @endforeach
                                @else
                                <td>No coverage added for the product</td>
                                @endif
                                </tbody>
                            </table>
                        @else
                            <table class="kt-invoice-v2__summary table table-striped m-table" id="coverageTable" style="display: none;">
                                <thead class="kt-invoice-v2__summary-header">
                                <tr>
                                    <th>Coverage Name</th>
                                    <th>Reserve Created</th>
                                    <th>Payment Created</th>
                                    <th>Balance</th>
                                    <th id="allocationText" class="allocation" name="allocation">Payment Allocation</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if($coverages != null)
                                    @foreach($coverages as $coverage)
                                    <tr class="table-row">
                                        <td><input type="hidden" name="coverage_id[]" value="{!! $coverage->coverage_id !!}" /><input type="hidden" name="coverage_name[]" value="{!! $coverage->name !!}" />{{ $coverage->name }}</td>
                                        {{--<td>-</td>--}}
                                        <td>{!! $coverage->reserve_amt !!}</td>
                                        <td>{!! $coverage->payment_amt !!}</td>
                                        <td>{!! $coverage->reserve_amt - $coverage->payment_amt !!}</td>
                                        {{--<td><span id="initReserve"></span></td>--}}
                                        <td><input data-coverage-id="{{ $coverage->coverage_id }}" type="text" name="amt[]" class="amt" autocomplete="off"/>
                                        <p id="sumError" style="display:none;color:red">Reserve allocation should be equal to <span class="sum_value"></span> or less  </p>
                                        <p id="sumError2" style="display:none;color:red">Sum of reserve allocation should be equal to <span class="sum_value"></span> or less  </p>
                                        <p id="checkSum"  style="display:none;color:red">Reserves/payments values can not be 0.</p>
                                        <p id="checkSum2"  style="display:none;color:red">Payment should be less than Balance.</p></td>
                                    </tr>
                                    @endforeach
                                @else
                                <td>No coverage added for the product</td>
                                @endif
                                </tbody>
                            </table>
                        @endif
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9" style="margin-left: 480px">
                                        <button class="btn btn-brand" id="submitReserve2" style="display:none;" type="submit">Submit</button>
                                        <button class="btn btn-brand" id="submitReserve" type="submit">Submit</button>
                                        <button class="btn btn-brand" type="button" id="ReserveloadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!--end::Section-->
        <table class="table table-striped table-bordered table-hover table-checkable" id="coverage_table">
            <thead>
            <tr>
                <th>Date</th>
                <th>Trans Type</th>
                <th>Trans Sub Type</th>
                <th>(+)</th>
                <th>(-)</th>
                <th>Running Balance</th>
                <th>Inserted User</th>
                <th>Actions</th>
            </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="voidPaymentDetailsModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        {{-- <form id="claimsVoidPayment"  action="{{ route('admin.claims.voidPayment') }}" method="POST" enctype="multipart/form-data" class="kt-form"> --}}
            {{-- <input type="hidden" name="_token" value="{{ csrf_token() }}" /> --}}
            <div class="modal-body">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="myModalLabel">Reserve Information</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <div class="form-widget" data-loader="button">
                                {{-- <input type="hidden" value="" id="claimReserveCoverageId"
                                    name="claimReserveCoverageId"> --}}

                                <div class="row">
                                    <div class="col-6">
                                        <label for="Reference No"><strong>Reference No:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="reference_no"></span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="Date"><strong>Date:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="date_of_payment"></span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="Transaction Type"><strong>Transaction Type:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="transaction_type"></span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="Transaction Sub Type"><strong>Transaction Sub Type:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="transaction_sub_type"></span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="Description"><strong>Description:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="description_of_payment"></span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="Memo On Check"><strong>Memo On Check:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="memo_on_check"></span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="Payee Name"><strong>Payee Name:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="payee_name"></span>
                                    </div>
                                </div>
                                {{-- <div class="row">
                                    <div class="col-6">
                                        <label for="Check Number"><strong>Check Number:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="check_number"></span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="Printed"><strong>Printed:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="printed"></span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="Printed Date"><strong>Printed Date:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="printed_date"></span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="Printed By"><strong>Printed By:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="printed_by"></span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="Payment Status"><strong>Payment Status:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="payment_status"></span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="Approved By"><strong>Approved By:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="approved_by"></span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="Approved Date"><strong>Approved Date:</strong></label>
                                    </div>
                                    <div class="col-6">
                                        <span id="approved_date"></span>
                                    </div>
                                </div> --}}
                                <div></div>
                                <div class="col-6" style="float: right;">
                                    <button type="submit" name="modal-void-payment" id="modal-void-payment"
                                        class="btn btn-block confirmVoidPaymentBtn" style="color: white;  background-color: #403F86;">Void This Payment</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {{-- </form> --}}
    </div>
</div>

<div class="modal fade" id="voidPaymentStore" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <form id="claimsVoidPayment"  action="{{ route('admin.claims.voidPayment') }}" method="POST" enctype="multipart/form-data" class="kt-form">
            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
            <input type="hidden" value="" id="claimReserveCoverageId" name="claimReserveCoverageId">
            <input type="hidden" name="total_payment" id="total_payment" value="{{ $payment_amt }}">
            <div class="modal-body">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="myModalLabel">Void</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <div class="form-widget" data-loader="button">
                                <div class="row">
                                    <div class="col-8">
                                        <label for="Reason for void"><strong>Reason For Void</strong></label>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-8">
                                        <textarea name="reason_for_void" id="reason_for_void"  cols="50" rows="7"></textarea>
                                    </div>
                                </div>
                                <div></div>
                                <div class="row">
                                    <div class="col-6">
                                        <button type="submit" name="modal-void-payment-submit" id="modal-void-payment-submit"
                                            class="btn btn-block voidThisPaymentBtn" style="color: white;  background-color: #403F86;  float: right;">Void</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="voidedPaymentInfoModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-body">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="myModalLabel">Voided Payment Information</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                </div>
                <div class="modal-body">
                    <div>
                        <div class="form-widget" data-loader="button">
                            <div class="row">
                                <div class="col-6">
                                    <label for="Reference No"><strong>Reference No:</strong></label>
                                </div>
                                <div class="col-6">
                                    <span id="referenceNo"></span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <label for="Payment Date"><strong>Payment Date:</strong></label>
                                </div>
                                <div class="col-6">
                                    <span id="payment_void_date"></span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <label for="Payment Amount"><strong>Payment Amount:</strong></label>
                                </div>
                                <div class="col-6">
                                    <span id="voided_amount"></span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <label for="Payment Voided By"><strong>Payment Voided By:</strong></label>
                                </div>
                                <div class="col-6">
                                    <span id="payment_void_by"></span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <label for="Reason For Void"><strong>Reason For Void:</strong></label>
                                </div>
                                <div class="col-6">
                                    <span id="reason_for_void_payment"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
