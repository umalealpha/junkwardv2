<div class="kt-portlet">
    {{-- <form id="updateCustomerKYC" action="{{ route('quote.getUpdatedPremium') }}" --}}
    {{-- method="POST" enctype="multipart/form-data" class="kt-form"> --}}
    <!-- CSRF Token -->
    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
    <input type="hidden" name="quoteCode" value="{{ $premiumCalcDetails->quoteNumber ?? '' }}" />
    {{-- <input type="hidden" name="generatePaymentUrl" value="" id="generatePaymentUrlId" /> --}}
    <div class="kt-portlet__body">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">Customer Details
                @isset($quote_id->quoteCode)
                    <span style="float: right">
                        <a href="{{ route('quote.download',[$quote_id->quoteCode,'100']) }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Download"> <span class="kt-opacity-11" id="">Download</span>&nbsp; <i class="flaticon-download-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                    </span>
                @endisset
            </h3>
        </div>
        <form id="updateCustomerDetails" action="" method="POST" enctype="multipart/form-data"
            class="kt-form">
            <table class="table table-striped table-bordered table-hover table-checkable"
                id="policy_table">
                <thead>
                    <tr>
                        <th>Name:</th>
                        <td width="50%">
                            {{ ucwords(strtolower($user->firstName . ' ' . $user->middleName . ' ' . $user->lastName)) }}
                        </td>
                    </tr>
                    <tr>
                        <th>Omang Number:</th>
                        @if ($user->profile->omang)
                            <td width="50%">{{ $user->profile->omang }}</td>
                        @else
                            <td width="50%">N/A</td>
                        @endif
                    </tr>
                    <tr>
                        <th>Passport Number:</th>
                        @if ($user->profile->passport)
                            <td width="50%">{{ $user->profile->passport }}</td>
                        @else
                            <td width="50%">N/A</td>
                        @endif
                    </tr>
                    <tr>
                        <th>Email:</th>
                        @if ($user->email)
                            <td width="50%">{{ $user->email }}</td>
                        @else
                            <td width="50%">N/A</td>
                        @endif
                    </tr>
                    <tr>
                        <th>Cellphone:</th>
                        @if ($user->cellphone)
                            <td width="50%">{{ $user->cellphone }}</td>
                        @else
                            <td width="50%">N/A</td>
                        @endif
                    </tr>
                    <tr>
                        <th>Gender:</th>
                        <td width="50%">
                            <select class="form-control" required name="gender" id="gender">
                                <option value="">Please Select</option>
                                <option value="0" @if ($user->profile->gender == 0) selected @endif>Female</option>
                                <option value="1" @if ($user->profile->gender == 1) selected @endif>Male</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Date Of Birth:</th>
                        @if ($user->profile->dob)
                            <td width="50%">
                                <input type="text" required class="form-control kt_datepicker_1 dob"
                                    name="dob" id="rerate_dob" autocomplete="off" data-date-end-date="-18y"
                                    value="{{ $user->profile->dob }}" placeholder="Select date" />
                            </td>
                        @else
                            <td width="50%">N/A</td>
                        @endif
                    </tr>
                    <tr>
                        <th>Marital Status:</th>
                        <td width="50%">
                            <select class="form-control" required name="marital" id="marital_status">
                                <option value="">Please Select</option>
                                <option value="1" @if ($user->profile->maritalstatus == 1) selected @endif>Single</option>
                                <option value="2" @if ($user->profile->maritalstatus == 2) selected @endif>Married</option>
                                <option value="3" @if ($user->profile->maritalstatus == 3) selected @endif>Divorced</option>
                                <option value="4" @if ($user->profile->maritalstatus == 4) selected @endif>Widowed</option>
                                <option value="5" @if ($user->profile->maritalstatus == 5) selected @endif>Living Together</option>
                                <option value="6" @if ($user->profile->maritalstatus == 6) selected @endif>Living Separately</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>Store Name:</th>
                        @if ($storeName != null)
                            <td width="50%">{{ $storeName }}</td>
                        @else
                            <td width="50%">-</td>
                        @endif
                    </tr>
                </thead>
            </table>
        </form>
        <!--end: Datatable -->
    </div>
    <div class="kt-portlet__body">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">Product Details</h3>
        </div>
        <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table2">
            <thead>
                <tr>
                    <th>Product:</th>
                    <td width="50%">
                        @if ($product->name != null)
                            {{ $product->name }}
                        @else
                            NA
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Product Plan:</th>
                    <td width="50%">
                        @if ($productPlan && $productPlan->name != null)
                            {{ $productPlan->name }}
                        @else
                            NA
                        @endif
                    </td>
                </tr>

                <tr>
                    <th>Sum Insured:</th>
                    <td width="50%">P {{ $policy->sum_assured }}</td>
                    {{-- <td width="50%">P {!! number_format($policy->sum_assured, 0, '.', ',') !!}</td> --}}
                </tr>

            </thead>
        </table>
        <!--end: Datatable -->
    </div>

    <div class="kt-portlet__body">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title">Vehicle Details</h3>
        </div>

        <form id="updateVehicleDetails" action="" method="POST" enctype="multipart/form-data"
            class="kt-form">

            <table class="table table-striped table-bordered table-hover table-checkable"
                id="policy_table3">
                <thead>
                    <tr>
                        <th>Japenese Import:</th>
                        <td width="50%">
                            <select class="form-control kt_selectpicker select" required name="is_imported"
                                title="Please select type" data-live-search="true" id="is_imported">
                                <option value="Yes" @if ($premiumCalcDetails->is_imported == 'Yes') selected @endif>Yes</option>
                                <option value="No" @if ($premiumCalcDetails->is_imported == 'No') selected @endif>No</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Make:</th>
                        <td width="50%">
                            <select class="form-control kt_selectpicker" required name="make"
                                title="Please select type" data-live-search="true" id="make_rerate">
                                @foreach ($dataMake as $key => $make)
                                    @if ($premiumCalcDetails->is_imported == 'Yes')
                                        <option value="{{ $make->s_Make }}" @if ($premiumCalcDetails->make == $make->s_Make) selected @endif>
                                            {{ ucwords(strtoupper($make->s_Make)) }}</option>
                                    @endif
                                    @if ($premiumCalcDetails->is_imported == 'No')
                                        <option value="{{ $make }}" @if ($premiumCalcDetails->make == $make) selected @endif>
                                            {{ ucwords(strtoupper($make)) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Manufacturing Year:</th>
                        <td width="50%">
                            <select class="form-control kt_selectpicker" required name="year"
                                title="Please select manufacturing year" data-live-search="true"
                                id="year_rerate">
                                @foreach ($years as $key => $year)
                                    <option value="{{ $year }}" @if ($vehicle->year == $year) selected @endif>
                                        {{ $year }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Model:</th>
                        <td width="50%" height="72px">
                            <select class="form-control kt_selectpicker" required name="model"
                                title="Please select vehicle model" data-live-search="true"
                                id="model_rerate">
                                @if ($premiumCalcDetails->is_imported == 'Yes')
                                    @foreach ($dataModel as $key => $model)
                                        <option value="{{ $model['model'] }}" @if ($premiumCalcDetails->model == $model['model']) selected @endif>
                                            {{ ucwords(strtoupper($model['model'])) }}</option>
                                    @endforeach
                                @endif

                                @if ($premiumCalcDetails->is_imported == 'No')
                                    @foreach ($dataModel as $key => $model)
                                        <option value="{{ $model['Model'] }}" @if ($premiumCalcDetails->model == $model['Model']) selected @endif>
                                            {{ ucwords(strtoupper($model['Model'])) }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Estimated value of vehicle:</th>
                        <td width="50%">
                            <span class="spinner-border spinner-border-sm" id="valueLoader"></span>
                            <input type="number" class="form-control" required min="20000" max="500000"
                                name="estimatedValue" id="estimatedValue"
                                value="{{ $policy->sum_assured }}"
                                placeholder="Enter estimated value" />
                            {{-- <span id="estimated" class="error" style="display: none; font-size: 12px; color:red;">This field is required.</span> --}}
                        </td>
                    </tr>
                    <tr>
                        <th>Number of prior accidents:</th>
                        <td width="50%">
                            <input type="number" class="form-control" required min="0" max="3" name="prior_accidents"
                                    id="prior_accidents" title="Enter number of prior accidents"
                                    value="{{ $premiumCalcDetails->priorAccidents }}"
                                    placeholder="Enter number of prior accidents" />
                            {{-- @if ($premiumCalcDetails != null)
                                <input type="hidden" required min="0" max="3" name="prior_accidents"
                                    id="prior_accidents" title="Enter number of prior accidents"
                                    value="{{ $premiumCalcDetails->priorAccidents }}"
                                    placeholder="Enter number of prior accidents" />
                                <span
                                    class="form-control">{{ $premiumCalcDetails->priorAccidents }}</span>
                            @else
                                <input type="hidden" required min="0" max="3" name="prior_accidents"
                                    id="prior_accidents" title="Enter number of prior accidents" value="0"
                                    placeholder="Enter number of prior accidents" />
                                <span class="form-control">0</span>
                            @endif --}}
                        </td>
                    </tr>
                    <tr>
                        <th>Condition:</th>
                        <td width="50%">Excellent</td>
                    </tr>
                    <tr>
                        <th>Mileage:</th>
                        <td width="50%">Low</td>
                    </tr>
                    <tr>
                        <th>Purpose:</th>
                        <td width="50%">Personal</td>
                    </tr>

                </thead>
            </table>
        </form>
        <!--end: Datatable -->
    </div>
    <div class="kt-portlet__body">
        <h3 class="kt-portlet__head-title" style="display:inline">Premium Calculation Details</h3>
        @can('quotes-Update Premium')
            <div class="kt-portlet__head-label">
                <!--  <a href="{{ route('quote.perDayPremium', $policy->policyNumber) }}" target="_blank" style="margin-left:1%;display:inline;float:right;" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Calculate per day premium"> <span class="kt-opacity-11" id="">Calculate Per Day Premium</span>&nbsp; <i class="flaticon-edit kt-padding-l-5 kt-padding-r-0"></i> </a>-->
                <a href="{{ route('admin.policy.premiumUpdateHistory', $policy->policyNumber) }}"
                    target="_blank" style="margin-left:1%;display:inline;float:right;"
                    class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip"
                    title="" data-placement="left" data-original-title="Edit & Update History"> <span
                        class="kt-opacity-11" id="">Premium Update History</span>&nbsp; <i
                        class="flaticon-edit kt-padding-l-5 kt-padding-r-0"></i> </a>
                {{-- <p style="margin-left:1%;display:inline;float:right;" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="edit-premium-button" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Re-rate premium"> <span class="kt-opacity-11" id="">Add Discount/Surcharge</span>&nbsp; <i class="flaticon-edit kt-padding-l-5 kt-padding-r-0"></i> </p> --}}

            </div>
        @endcan

        <div style="margin-bottom:2%" id="rerate_div_premium">
            <form id="updateCustomerPremium"  action="{{ route('quote.updatePremium', $policy->policyNumber) }}" method="POST" enctype="multipart/form-data" class="kt-form">
                <!-- CSRF Token -->
                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                <table class="table table-striped table-bordered table-hover table-checkable">
                    <thead>
                        <tr>
                            <th>Please select type:</th>
                            <td width="50%">
                                <select class="form-control kt_selectpicker" name="type"
                                    title="Please select type" data-live-search="true" id="type">
                                    <option value="discount">Discount</option>
                                    <option value="surcharge">Surcharge</option>
                                </select>
                                <span id="discountError" class="error"
                                    style="display: none; font-size: 12px; color:red;">This field is
                                    required.</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Please select value type:</th>
                            <td width="50%">
                                <select class="form-control kt_selectpicker" name="value_type"
                                    title="Please select value type" data-live-search="true"
                                    id="value_type">
                                    <option value="1">Flat value</option>
                                    <option value="2">Percent(%) value</option>
                                </select>
                                <span id="valueTypError" class="error"
                                    style="display: none; font-size: 12px; color:red;">This field is
                                    required.</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Value:</th>
                            <td width="50%">
                                <input type="number" class="form-control" name="value"
                                    title="Enter the value" placeholder="Enter value" id="value" />
                                <span id="valueError" class="error"
                                    style="display: none; font-size: 12px; color:red;">This field is
                                    required.</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Reason:</th>
                            <td width="50%">
                                <input type="text" class="form-control" name="reason"
                                    title="Please provide the reason" placeholder="Please provide reason"
                                    id="reason" />
                                <span id="reasonError" class="error"
                                    style="display: none; font-size: 12px; color:red;">This field is
                                    required.</span>
                            </td>
                        </tr>
                    </thead>
                </table>
                <div class="kt-form__actions">
                    <div class="row">
                        <div class="col-5"></div>
                        <div class="col-7">
                            <button type="button" class="btn btn-info"
                                id="submitEditPremium">Submit</button>
                            <p class="btn btn-secondary" id="rerate_hide" style="margin-top:2%">Cancel</p>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <table class="table table-striped table-bordered table-hover table-checkable">
            <thead>
                <tr>
                    <th>Ratings Calculation Log ID:</th>
                    <td width="50%">
                        @if (isset($premiumCalcDetails->ratings_id))
                            {{ $premiumCalcDetails->ratings_id }}
                        @else
                            N/A
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Monthly:</th>
                    <td width="50%">P {{ $premiumCalcDetails->premiumMonthly }}</td>
                </tr>
                <tr>
                    <th>3 Installments:</th>
                    <td width="50%">P {{ $premiumCalcDetails->premium3Inst }}</td>
                </tr>

                <tr>
                    <th>Annual:</th>
                    <td width="50%">P {{ $premiumCalcDetails->premiumAnnually }}
                        <input type="hidden" id="annual_premium1"
                            value="{{ $premiumCalcDetails->premiumAnnually }}" />
                        <input type="hidden" id="annual_premium2" name="annual_premium" value="" />
                    </td>
                </tr>
                <tr>
                    <th>Discount/Surcharge:</th>
                    @if ($premiumCalcDetails->discount_surcharge != null && $premiumCalcDetails->discount_surcharge != 0)
                        <td width="50%">P
                            {{ $premiumCalcDetails->discount_surcharge . ' (' . $premiumCalcDetails->percent_discount_surcharge . '%)' }}
                        </td>
                    @else
                        <td width="50%">-</td>
                    @endif
                </tr>
                <tr>
                    <th>Ratings Premium Rate:</th>
                    @if ($premiumCalcDetails->premium_rate > 2 && $premiumCalcDetails->premium_rate < 100)
                        <td width="50%" style="color: green">
                            {{ number_format((float) $premiumCalcDetails->premium_rate, 2, '.', '') }}%
                        </td>
                    @else
                        <td width="50%" style="color: red">
                            {{ number_format((float) $premiumCalcDetails->premium_rate, 2, '.', '') }}%
                        </td>
                    @endif
                </tr>
                <tr>
                    <th>Premium Rate after discount/surcharge:</th>
                    @if ($premiumCalcDetails->ratio != 0)
                        @if ($premiumCalcDetails->ratio > 2 && $premiumCalcDetails->ratio < 100)
                            <td width="50%" style="color: green">
                                {{ number_format((float) $premiumCalcDetails->ratio, 2, '.', '') }}%
                            </td>
                        @else
                            <td width="50%" style="color: red">
                                {{ number_format((float) $premiumCalcDetails->ratio, 2, '.', '') }}%
                            </td>
                        @endif
                    @else
                        <td width="50%">-</td>
                    @endif
                </tr>
                <tr>
                    <th>Reason:</th>
                    @if ($reratedPremiumQuotes)
                        @if ($reratedPremiumQuotes->reason != null)
                            <td width="50%">{{ $reratedPremiumQuotes->reason }}</td>
                        @else
                            <td width="50%">-</td>
                        @endif
                    @else
                        <td width="50%">-</td>
                    @endif
                </tr>
            </thead>
        </table>

        <div class="kt-portlet__foot kt-portlet__foot--solid" id="page_footer">
            {{-- @if ($premiumCalcDetails->can_edit == 1 && $premiumCalcDetails->priorAccidents <= 3) // edit limit condition --}}
                <div class="kt-form__actions">
                    <div class="row">
                        <div class="col-5"></div>
                        <div class="col-7">
                            <button type="submit" class="btn btn-info"
                                id="submit_rerate_button">Update</button>
                                @if($product->id == 3 && isset($is_renewal->is_renewed)  && $is_renewal->is_renewed== 0)
                                    <a href="{{ route('admin.policy.renew', $policy->id) }}" class="btn btn-success">Renew</a>
                                @endif
                            <a class="btn btn-secondary"
                                href="{{ route('admin.policy.index') }}">Cancel</a>
                        </div>
                    </div>
                </div>
            {{-- @endif --}}
        </div>
    </div>
    <div class="kt-portlet__body">
        <div style="margin-bottom:2%; display: none;" id="new_rate_div">
            <div class="kt-portlet__head-label">
                <h3 class="kt-portlet__head-title">New Premium Calculation Details</h3>
            </div>
            {{-- <form id="newPremiumRate"
                action="{{ route('admin.policy.acceptReratedPremium', $policy->policyNumber) }}"
                method="POST" enctype="multipart/form-data" class="kt-form">
                <!-- CSRF Token -->
                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                <input type="hidden" name="rateID" id="rateID" />
                <input type="hidden" name="annual_premium_rerate" id="annual_premium_rerate" value=""/>
                <input type="hidden" name="generatePaymentUrl" value="" id="generatePaymentUrlId" /> --}}
                <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table4">
                    <thead>
                        <tr>
                            <th>Ratings Calculation Log ID:</th>
                            <td width="50%" id="rate_id"></td>
                            <input type="hidden" id="rate_id_enc" value="" />
                        </tr>
                        <tr>
                            <th>Monthly:</th>
                            <td width="50%" id="monthly_ins"></td>
                        </tr>
                        <tr>
                            <th>3 Installments:</th>
                            <td width="50%" id="three_ins"></td>
                        </tr>

                        <tr>
                            <th>Annual:</th>
                            <td width="50%" id="annual_ins"></td>
                        </tr>
                        <tr>
                            <th>Ratings Premium Rate:</th>
                            <td width="50%" id="premium_rate"></td>
                        </tr>
                        <tr>
                            <th>Please select frequency:</th>
                            <td width="50%">
                                <select class="form-control kt_selectpicker" name="new_frequency"
                                    title="Please select frequency" data-live-search="true"
                                    id="new_frequency">
                                    <option value="1">Monthly Installments</option>
                                    <option value="2">Three Installmentsin a year</option>
                                    <option value="3">Annual Installments</option>
                                </select>
                                <span id="newFrequencyError" class="error"
                                    style="display: none; font-size: 12px; color:red;">This field is
                                    required.</span>
                            </td>
                        </tr>
                    </thead>
                </table>
                <div class="form-group row">
                    <div class="col-3">
                        <h5>Add Discount / Surcharge</h5>
                    </div>
                    <div class="col-3">
                        <span class="kt-switch">
                            <label>
                                <input id="discountSurchargeValue" type="checkbox" name="addDiscSurc" value="1"
                                    onchange="discountSurcharge()">
                                <span style="margin-top: 5px;margin-left: 10px;"></span>
                                <h4 id="MsgDiscountSurcharge"
                                    style="display:inline;float:left;margin-top: 5px;margin-left: 5px;color:#ff4d4d;">
                                    No</h4>
                            </label>
                        </span>
                    </div>
                </div>

                <table class="table table-striped table-bordered table-hover table-checkable"
                    id="discount_surcharge_div" style="display: none;">
                    <thead>
                        <tr>
                            <th>Please select type:</th>
                            <td width="50%">
                                <select class="form-control kt_selectpicker" name="type"
                                    title="Please select type" data-live-search="true" id="dis_sur_type">
                                    <option value="discount">Discount</option>
                                    <option value="surcharge">Surcharge</option>
                                </select>
                                <span id="dis_sur_typeError" class="error"
                                    style="display: none; font-size: 12px; color:red;">This field is
                                    required.</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Please select value type:</th>
                            <td width="50%">
                                <select class="form-control kt_selectpicker" name="value_type"
                                    title="Please select value type" data-live-search="true"
                                    id="dis_sur_value_type">
                                    <option value="1">Flat value</option>
                                    <option value="2">Percent(%) value</option>
                                </select>
                                <span id="dis_sur_value_typeError" class="error"
                                    style="display: none; font-size: 12px; color:red;">This field is
                                    required.</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Value:</th>
                            <td width="50%">
                                <input type="text" class="form-control" name="value" id="dis_sur_value"
                                    title="Enter the value" placeholder="Enter value" />
                                <span id="dis_sur_valueError" class="error"
                                style="display: none; font-size: 12px; color:red;">This field is
                                required.</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Reason:</th>
                            <td width="50%">
                                <input type="text" class="form-control" name="reason" id="dis_sur_reason"
                                    title="Please provide the reason" placeholder="Please provide reason" />
                                <span id="dis_sur_reasonError" class="error"
                                style="display: none; font-size: 12px; color:red;">This field is
                                required.</span>
                                {{-- <span id="reasonError" class="error" style="display: none; font-size: 12px; color:red;">This field is required.</span> --}}
                            </td>
                        </tr>
                        <tr>
                            <th></th>
                            <td>
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-5"></div>
                                        <div class="col-7">
                                            {{-- <a href="{{ route('admin.policy.createRealpayPayment') }}"> --}}
                                                <button type="button" class="btn btn-info"
                                                id="addDiscSurcRerate">Proceed</button>
                                            {{-- </a> --}}
                                            <p class="btn btn-secondary" id="cancel_rerate_button"
                                                style="margin-top:2%">Close</p>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </thead>
                </table>


                <div class="form-group row" id="dis_sur_added_div" style="display: none;">
                    <h3>Added Discount/Surcharge:</h3>
                    <table class="table table-striped table-bordered table-hover table-checkable" id="addedDiscSurPrem_table">
                        <thead>
                            {{-- <tr>
                                <th>Ratings Calculation Log ID:</th>
                                <td width="50%" id="dis_sur_rate_id"></td>
                                <input type="hidden" id="dis_sur_rate_id_enc" value="" />
                                <input type="hidden" id="dis_sur_annual_premium_rerate" value=""/>
                            </tr> --}}
                            <tr>
                                <th>Monthly:</th>
                                <td width="50%" id="dis_sur_monthly_ins"></td>
                            </tr>
                            <tr>
                                <th>3 Installments:</th>
                                <td width="50%" id="dis_sur_three_ins"></td>
                            </tr>

                            <tr>
                                <th>Annual:</th>
                                <td width="50%" id="dis_sur_annual_ins"></td>
                            </tr>
                            {{-- <tr>
                                <th>Ratings Premium Rate:</th>
                                <td width="50%" id="dis_sur_premium_rate"></td>
                            </tr> --}}
                        </thead>
                    </table>
                </div>

                <div>
                    <h5 id="rerate_disSur_error_div" style="color: red; display: none;"></h5>
                </div>

                <form id="newPremiumRate" action="{{ route('admin.policy.acceptReratedPremium', $policy->policyNumber) }}"
                    method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <input type="hidden" name="rateID" id="rateID" />
                    <input type="hidden" name="annual_premium_rerate" id="annual_premium_rerate" value=""/>
                    <input type="hidden" name="dis_sur_annual_premium" id="dis_sur_annual_premium" value=""/>
                    <input type="hidden" name="frequency" id="dis_sur_new_frequency" value=""/>
                    <input type="hidden" name="trans_type" id="trans_type" value="rerate"/>
                    <input type="hidden" name="generatePaymentUrl" value="" id="generatePaymentUrlId" />
                    <div class="form-group row" id="realpaySwitchDiv">
                        <div class="col-3">
                            <h5>Add payment on realpay</h5>
                        </div>
                        <div class="col-3">
                            <span class="kt-switch">
                                <label>
                                    <input id="paymentValue" type="checkbox" name="addPayment" value="1"
                                        onchange="addPaymentButton()" title="Please select payment">
                                    <span style="margin-top: 5px;margin-left: 10px;"></span>
                                    <h4 id="paymentMsg"
                                        style="display:inline;float:left;margin-top: 5px;margin-left: 5px;color:#ff4d4d;">
                                        No</h4>
                                </label>
                            </span>
                        </div>
                    </div>
                    <div style="margin-bottom:2%;display: none;" id="rerate_new_div">
                        {{-- <form id="policyForm1" action="{{ route('admin.policy.addOfflinePaymentPolicyRenewal') }}" method="POST"
                            enctype="multipart/form-data" class="kt-form"> --}}
                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <input type="hidden" name="policyNumber" value="{{ $policy->policyNumber }}" />
                            <input type="hidden" name="policyID" value="{{ $policy->id }}" />
                            <input type="hidden" name="paymentMethod" value="Realpay" />
                            {{-- <input type="hidden" id="new_premium" name="new_premium" value="{{ $new_premium }}"> --}}
                            {{-- <input type="hidden" name="term_start_date" value="{{ $term_start_date }}"> --}}
                            {{-- <input type="hidden" name="agent_id" value="{{ $agent_id }}"> --}}
                            {{-- <input type="hidden" name="name" value="{{$name}}"> --}}
                            {{-- <input type="hidden" name="reinstate_type" value="{{ $reinstate_type }}"> --}}

                            <div class="kt-portlet__body" id="paymentInfoDivRerate">
                                <div class="form-group row">
                                    <label class="col-3 col-form-label">Instalment Start Date :</label>
                                    <div class="col-9">
                                        <input type="text" class="form-control kt_datepicker_1 validateGroup1"
                                            name="billingDay" autocomplete="off"
                                            placeholder="Select payment start date" title="Please select instalment start date"/>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>

                                {{-- <div class="form-group row">
                                    <label class="col-3 col-form-label">Payment Frequency :</label>
                                    <div class="col-9">
                                        <select id="paymentFreq" class="form-control required kt_selectpicker" title="Please select payment frequency" name="frequency" required>
                                            <option value="" selected disabled>Select payment frequency</option>
                                            <option value="1">Monthly Installments</option>
                                            <option value="2">Three Installments in a year</option>
                                            <option value="3">Annual Installment</option>
                                        </select>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div> --}}

                                <div class="form-group row" id="first_collection_date_rerate_div">
                                    <label  class="col-3 col-form-label">First Collection Date :</label>
                                    <div class="col-9">

                                        <input type="text" required class="form-control kt_datepicker_1 dob" name="first_collection_date" id="first_collection_date_rerate" autocomplete="off"  placeholder="Select date" title="Please select first collection date"/>
                                        <span style="color: red;">Note: Please note that First Collection Date should be always less than Instalment Start Date.</span>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>

                                <div class="form-group row" id="first_premium_row_rerate_div">
                                    <label  class="col-3 col-form-label">First Instalment Amount :</label>
                                    <div class="col-9">
                                        <input  class="form-control" name="first_premium" id="first_premium_rerate" title="Please provide premium" placeholder="Please provide premium">
                                        <span class="labelled" id="paymentNote" style="color: red;"></span>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>

                                <div class="form-group row" id="first_premium_row">
                                    <label  class="col-3 col-form-label">Premium :</label>
                                    <div class="col-9">
                                        <input  class="form-control" name="rerate_premium" id="rerate_premium" title="Please provide premium" placeholder="Please provide premium">
                                        <span class="labelled" id="paymentNote" style="color: red;"></span>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label class="col-3 col-form-label">Please select bank :</label>
                                    <div class="col-9">
                                        <select id="RPBanksRerate" class="form-control kt_selectpicker"
                                            title="Please select bank" name="BankName">
                                            @if (isset($banks['names']) && count($banks) > 0)
                                                @foreach ($banks['names'] as $bank)
                                                    <option value="{{ $bank->bank_number }}">{{ $bank->bank_name }}</option>
                                                @endforeach
                                            @else
                                                @foreach($banks as $bank)
                                                    <option value="{{ $bank->bank_number }}">{{ $bank->bank_name }}</option>
                                                @endforeach
                                            @endif

                                        </select>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-3 col-form-label">Please select bank branch :</label>
                                    <div class="col-9">
                                        <select id="RPBankBranchRerate" class="form-control kt_selectpicker"
                                            title="Please select bank branch" name="BranchCode">

                                        </select>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>
                                <input type="hidden" value="{{ $user->cellphone }}" name="billingCell" />
                                <div class="form-group row">
                                    <label class="col-3 col-form-label">Account Type :</label>
                                    <div class="col-9">
                                        <select class="form-control kt_selectpicker"
                                            title="Please select account type" name="accountType">
                                            <option value="1">Cheque</option>
                                            <option value="2">Savings</option>
                                        </select>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-3 col-form-label">Account Number :</label>
                                    <div class="col-9">
                                        <input class="form-control validateGroup1" name="accountNumber" value=""
                                            title="Please provide account number"
                                            placeholder="Please provide account number">
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>

                            </div>
                            {{-- <div class="kt-portlet__foot kt-portlet__foot--solid">
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-3"></div>
                                        <div class="col-9">
                                            <button type="submit" value="Submit" id="submitbtn"
                                                class="btn btn-brand">Submit</button>
                                            <button class="btn btn-brand" type="button" id="loadBtn" style="display:none">
                                                <span class="spinner-border spinner-border-sm" role="status"
                                                    aria-hidden="true"></span> Loading... </button>
                                            <a class="btn btn-secondary" href="{{ URL::previous() }}">Cancel</a>
                                        </div>
                                    </div>
                                </div>
                            </div> --}}
                        {{-- </form> --}}
                    </div>
                    <div class="form-group row" id="dpoSwitchDiv">
                        <div class="col-3">
                            <h5>Add payment on DPO</h5>
                        </div>
                        <div class="col-3">
                            <span class="kt-switch">
                                <label>
                                    <input id="paymentValueDpo" type="checkbox" name="addPaymentDpo" value="1"
                                        onchange="addPaymentButtonDpo()">
                                    <span style="margin-top: 5px;margin-left: 10px;"></span>
                                    <h4 id="paymentMsgDpo"
                                        style="display:inline;float:left;margin-top: 5px;margin-left: 5px;color:#ff4d4d;">
                                        No</h4>
                                </label>
                            </span>
                        </div>
                    </div>
                    <div style="margin-bottom:2%;display: none;" id="rerate_new_div_dpo">
                        {{-- <form id="policyForm1" action="{{ route('admin.policy.addOfflinePaymentPolicyRenewal') }}" method="POST"
                            enctype="multipart/form-data" class="kt-form"> --}}
                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <input type="hidden" name="policyNumber" value="{{ $policy->policyNumber }}" />
                            <input type="hidden" name="policyID" value="{{ $policy->id }}" />
                            <input type="hidden" name="paymentMethod" value="Dpo" />
                            {{-- <input type="hidden" id="new_premium" name="new_premium" value="{{ $new_premium }}"> --}}
                            {{-- <input type="hidden" name="term_start_date" value="{{ $term_start_date }}"> --}}
                            {{-- <input type="hidden" name="agent_id" value="{{ $agent_id }}"> --}}
                            {{-- <input type="hidden" name="name" value="{{$name}}"> --}}
                            {{-- <input type="hidden" name="reinstate_type" value="{{ $reinstate_type }}"> --}}

                            <div class="kt-portlet__body" id="paymentInfoDivRerate">
                                <div class="form-group row">
                                    <label class="col-3 col-form-label">Installment Start Date :</label>
                                    <div class="col-9">
                                        <input type="text" class="form-control kt_datepicker_1 validateGroup1"
                                            name="billingDayDpo" autocomplete="off"
                                            placeholder="Select payment start date" title="Please select instalment start date" required>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>

                                <div class="form-group row" id="first_collection_date_rerate_div">
                                    <label  class="col-3 col-form-label">First Collection Date :</label>
                                    <div class="col-9">
                                        <input type="text" required class="form-control kt_datepicker_1 dob" name="first_collection_dateDpo" id="first_collection_date_rerate" autocomplete="off"  placeholder="Select date" title="Please select first collection date"/>
                                        <span style="color: red;">Note: Please note that First Collection Date should be always less than Instalment Start Date.</span>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>

                                <div class="form-group row" id="first_premium_row_rerate_div">
                                    <label  class="col-3 col-form-label">First Installment Amount :</label>
                                    <div class="col-9">
                                        <input  class="form-control" name="first_premiumDpo" id="first_premium_rerate" title="Please provide premium" placeholder="Please provide first premium" required>
                                        <span class="labelled" id="paymentNote" style="color: red;"></span>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>

                                <div class="form-group row" id="first_premium_row">
                                    <label  class="col-3 col-form-label">Premium :</label>
                                    <div class="col-9">
                                        <input  class="form-control" name="rerate_premiumDpo" id="rerate_premium" title="Please provide premium" placeholder="Please provide premium" required>
                                        <span class="labelled" id="paymentNote" style="color: red;"></span>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>
                                <input type="hidden" value="{{ $user->cellphone }}" name="billingCell" />
                            </div>
                        {{-- </form> --}}
                    </div>
                    <div class="form-group row" id="cashSwitchDiv">
                        <div class="col-3">
                            <h5>Pay with Cash</h5>
                        </div>
                        <div class="col-3">
                            <span class="kt-switch">
                                <label>
                                    <input id="paymentValueCash" type="checkbox" name="addPaymentCash" value="1"
                                        onchange="addPaymentButtonCash()" title="Please select payment">
                                    <span style="margin-top: 5px;margin-left: 10px;"></span>
                                    <h4 id="paymentMsgCash"
                                        style="display:inline;float:left;margin-top: 5px;margin-left: 5px;color:#ff4d4d;">
                                        No</h4>
                                </label>
                            </span>
                        </div>
                    </div>

                    <div class="form-group row" id="payWithCashAlreadyDone" style="display: none;">
                        <div class="col-3">
                            <h5>Payment is already logged in system</h5>
                        </div>
                        <div class="col-3">
                            <span class="kt-switch">
                                <label>
                                    <input id="cashPaymentDone" type="checkbox" name="cashPaymentDone" value="1"
                                        onchange="addPaymentButtonCashAlreadyDone()" title="Please select payment">
                                    <span style="margin-top: 5px;margin-left: 10px;"></span>
                                    <h4 id="paymentMsgCashDone"
                                        style="display:inline;float:left;margin-top: 5px;margin-left: 5px;color:#ff4d4d;">
                                        No</h4>
                                </label>
                            </span>
                        </div>
                    </div>

                    <div class="form-group row" id="addPaymentButtonCashAlreadyDoneDiv" style="display: none;">
                        <div class="kt-portlet__body">
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Please select Payment :</label>
                                <div class="col-9">
                                    <select id="selectPaymentData" class="form-control kt_selectpicker"
                                        title="Please select payment" name="selectPaymentData">
                                        @if (isset($paymentDetails) && count($paymentDetails) > 0)
                                            @foreach ($paymentDetails as $payment)
                                                <option value="{{ $payment->id }}">{{ $payment->referenceNumber.'/'.$payment->amount.'/'.$payment->paymentDate }}</option>
                                            @endforeach
                                        @endif

                                    </select>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div id="payWithCashForm" style="display: none;">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <input type="hidden" name="policyNumber" value="{{ $policy->policyNumber }}" />
                        <input type="hidden" name="policy_id" value="{{ $policy->id }}" />
                        <input type="hidden" name="paymentMethod" value="Cash" />
                        {{-- <input type="hidden" id="new_premium" name="new_premium" value="{{ $new_premium }}">
                        <input type="hidden" name="term_start_date" value="{{ $term_start_date }}">
                        <input type="hidden" name="agent_id" value="{{ $agent_id }}">
                        <input type="hidden" name="name" value="{{$name}}">
                        <input type="hidden" name="reinstate_type" value="{{ $reinstate_type }}"> --}}

                        <div class="kt-portlet__body">
                            @if(\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('back-dated-transactions'))
                                <div class="form-group row">
                                    <label class="col-3 col-form-label">Date of payment :</label>
                                    <div class="col-9">
                                        <input type="text" class="form-control kt_datepicker_1 required validateGroup1"
                                            name="paymentDate" autocomplete="off" placeholder="Select date of payment" title="Please select date of payment" />
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>
                            @else
                                <p>Note : You can select Date of Payment for current month.</p>
                                <div class="form-group row">
                                    <label class="col-3 col-form-label">Date of payment :</label>
                                    <div class="col-9">
                                        <input type="text" class="form-control required validateGroup1"
                                        id="date_of_refund_for_current_month" name="paymentDate" autocomplete="off" placeholder="Select date of payment" title="Please select date of payment"/>
                                        <span class="form-text text-muted"></span>
                                    </div>
                                </div>
                            @endif
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Payment Amount :</label>
                                <div class="col-9 payment-amount">
                                    <input class="form-control required validateGroup1 paymentAmountDiv" id="paymentAmount" name="paymentAmount" value="" title="Please provide amount" placeholder="Please provide amount">
                                    <span class="labelled" id="paymentNote" style="color: red;"></span>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            {{-- <div class="form-group row">
                                <label class="col-3 col-form-label">Payment Frequency :</label>
                                <div class="col-9">
                                    <select id="paymentFreq" class="form-control required kt_selectpicker" title="Please select payment frequency" name="paymentFreq">
                                        <option value="" selected disabled>Select payment frequency</option>
                                        <option value="1">Monthly Installments</option>
                                        <option value="2">Three Installments in a year</option>
                                        <option value="3">Annual Installment</option>
                                    </select>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div> --}}
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Receipt number:</label>
                                <div class="col-9">
                                    <input class="form-control required validateGroup1" name="receiptNumber" value="" title="Please provide payment receipt" placeholder="Please provide payment receipt number">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-3 col-form-label">Payment Recieved By :</label>
                                <div class="col-9">
                                    <input class="form-control required validateGroup1" name="paymentRecievedBy" value="" title="Please provide contract sequence" placeholder="Please provide payment recipient">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-3 col-form-label">Numbers of Installments paid :</label>
                                <div class="col-9">
                                    <input class="form-control required validateGroup1" name="numberOfInstalmentsPaid" value="" title="Please provide the number of Installments paid" placeholder="Please provide the number of Installments paid">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>


                            <div class="form-group row">
                                <label class="col-3 col-form-label">Note :</label>
                                <div class="col-9">
                                    <textarea class="form-control required validateGroup1" name="paymentNote" value="" title="Please provide note" placeholder="Please provide note"></textarea>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Upload Payment Proof</label>
                                <div class="col-2">
                                    <div class="kt-avatar" id="product_image" style="float: left; clear: left;">

                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>

                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' name="payment_image" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                        <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <br><br>
                    <div>
                        <h5 id="rerate_payment_note" style="color: red;">Note * : Please select payment method to accept above premium.</h5>
                    </div>
                    {{-- @if ($premiumCalcDetails->can_edit == 1) // edit limit condition --}}
                    {{-- @if (isset($reratelogData) && $reratelogData->status != 'Completed') --}}
                        <div class="kt-form__actions" id="acceptPremiumDiv">
                            <div class="row">
                                <div class="col-5"></div>
                                <div class="col-7">
                                    {{-- <a href="{{ route('admin.policy.createRealpayPayment') }}"> --}}
                                        <button type="submit" class="btn btn-info"
                                        id="acceptPremium">Accept</button>
                                    {{-- </a> --}}
                                    <p class="btn btn-secondary" id="cancel_rerate_button"
                                        style="margin-top:2%">Close</p>
                                </div>
                            </div>
                        </div>
                    {{-- @endif --}}
                    {{-- @endif --}}
                </form>
        </div>
    </div>
    {{-- </form> --}}
</div>


