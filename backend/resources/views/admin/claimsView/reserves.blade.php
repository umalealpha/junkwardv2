
<div class="kt-portlet kt-portlet--height-fluid">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">
               Reserves/Payments
            </h3>
        </div>

    </div>
    <div class="kt-portlet__body">
        <div class="form-group row collapse" id="reserveCollapse">
            <div class="col-md-12">
                <div class="kt-checkbox-inline">
                    <form action="{{ url('admin/claims/storeReserve/'.$claims->id) }}" method="POST" id="reserveForm" enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <input type="hidden" id="claim_id" value= "{{ $claims->id }}" />
                        <input type="hidden" name="product_id" value= "{{ $policyProduct }}" />
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
                                    <select class="form-control kt_selectpicker" data-live-search="true" title="Please select payee" name="payee" id="exampleSelect1">
                                        @foreach( $transPayees as  $transPayee)
                                            <option value="{{ $transPayee->id}}">{{ $transPayee->supplierName }}</option>
                                        @endforeach
                                    </select>
                                </div>
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
                                    <td><input type="text" id="amt" name="amt[]" class="amt" autocomplete="off"/>
                                    <p id="sumError" style="display:none;color:red">Reserve allocation should be equal to <span class="sum_value"></span> or less  </p>
                                    <p id="sumError2" style="display:none;color:red">Sum of reserve allocation should be equal to <span class="sum_value"></span> or less  </p></td>
                                </tr>
                                @endforeach
                            @else
                               <td>No coverage added for the product</td>
                            @endif
                            </tbody>
                        </table>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9" style="margin-left: 480px">
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
            </tr>
            </thead>
        </table>
    </div>
</div>
