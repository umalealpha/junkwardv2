<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >
<!-- begin:: Header Mobile -->
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed " >
    <div class="kt-header-mobile__logo">
        <a>
            <img alt="Logo" src="{{asset('images/logo.png')}}"/>
        </a>
    </div>
    <div class="kt-header-mobile__toolbar">
        <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left" id="kt_aside_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i class="flaticon-more"></i></button>
    </div>
</div>
<!-- end:: Header Mobile -->
<!-- begin:: Root -->
<div class="kt-grid kt-grid--hor kt-grid--root">
    <!-- begin:: Page -->
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">

        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')

    </div>
    <!--If Password default, show edit details -->
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Policy Reinstate
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Policies</span> </a>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit Policy</span> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->

            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="row ol-lg-12">
                    <div class=" col-lg-3 text-left policyno">
                        <h5> Policy No<span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"  style="font-size:15px"> # {{$data->policyNumber}}</span></h5> <br>
                    </div>
                </div>

                <br>
                <div class="kt-portlet kt-portlet--mobile">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <input type="hidden" id="quoteCode" name="quoteCode" {{--value="{{ $data->quoteCode }}"--}} />
                        <div class="kt-portlet__body">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Customer Details
                                    <a href="{{ route('admin.policy.addDiscountSurcharge',$data->policy_id) }}">
                                        <button class="btn btn-info" id="submit_discount_surcharge" style="float: right;">Add Discount/Surcharge</button>
                                    </a>

                                    @if ($data->product_id == 3)
                                        <a href="{{ route('admin.policy.applyCustomRate',$data->policy_id) }}">
                                            <button class="btn btn-info" id="submit_custom_rate" style="float: right; margin: 0px 10px;">Apply Custom Rate</button>
                                        </a>
                                    @endif
                               </h3>
                               <br>
                            </div>
                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                                <thead>
                                <tr>
                                    <th>Name:</th>
                                    <td width="50%">{{ ucwords(strtolower($data->firstName.' '.$data->middleName.' '.$data->lastName)) }}</td>
                                </tr>
                                <tr>
                                    <th>Omang Number:</th>
                                    @if($data->omang)
                                        <td width="50%">{{ $data->omang }}</td>
                                    @else
                                        <td width="50%">N/A</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Passport Number:</th>
                                    @if($data->passport)
                                        <td width="50%">{{ $data->passport }}</td>
                                    @else
                                        <td width="50%">N/A</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    @if($data->email)
                                        <td width="50%">{{ $data->email }}</td>
                                    @else
                                        <td width="50%">N/A</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Cellphone:</th>
                                    @if($data->cellphone)
                                        <td width="50%">{{ $data->cellphone }}</td>
                                    @else
                                        <td width="50%">N/A</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Gender:</th>
                                    <td width="50%">
                                        <input type="hidden" value="{{ $data->gender }}" id="gender" />
                                        @if($data->gender == 0) Female @endif
                                            @if($data->gender == 1) Male @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Date Of Birth:</th>
                                    @if($data->dob)
                                        <td width="50%">
                                            {{ $data->dob }}
                                            <input type="hidden" value="{{ $data->dob }}" id="dob" />
                               {{--             <input type="text" class="form-control kt_datepicker_1 dob" name="dob" id="dob" autocomplete="off" value="{{ $data->dob }}"  placeholder="Select date"/>--}}
                                        </td>
                                    @else
                                        <td width="50%">N/A</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Marital Status:</th>
                                    <td>
                                        <select class="form-control required" name="marital" id="marital_status">
                                            <option value="">Please Select</option>
                                            <option value="1" @if($data->maritalstatus == 1) selected @endif>Single</option>
                                            <option value="2" @if($data->maritalstatus == 2) selected @endif>Married</option>
                                            <option value="3" @if($data->maritalstatus == 3) selected @endif>Divorced</option>
                                            <option value="4" @if($data->maritalstatus == 4) selected @endif>Widowed</option>
                                            <option value="5" @if($data->maritalstatus == 5) selected @endif>Living Together</option>
                                            <option value="6" @if($data->maritalstatus == 6) selected @endif>Living Separately</option>
                                        </select>
                                    </td>
                                </tr>
                                </thead>
                            </table>
                            <!--end: Datatable -->
                        </div>
{{--                        <div class="kt-portlet__body">--}}
{{--                            <div class="kt-portlet__head-label">--}}
{{--                                <h3 class="kt-portlet__head-title">Product Details</h3>--}}
{{--                            </div>--}}
{{--                            <table class="table table-striped table-bordered table-hover table-checkable" id="product_details">--}}
{{--                                <thead>--}}
{{--                                <tr>--}}
{{--                                    <th>Product Name:</th>--}}
{{--                                    @if($data->product_id)--}}
{{--                                    <td width="50%">N/A</td>--}}
{{--                                        --}}{{-- <td width="50%">{{ $data->product_id }}</td> --}}
{{--                                    @else--}}
{{--                                        <td width="50%">N/A</td>--}}
{{--                                    @endif--}}
{{--                                </tr>--}}
{{--                                <tr>--}}
{{--                                    <th>Premium:</th>--}}
{{--                                    @if($data->premium)--}}
{{--                                        <td width="50%"> P{{ number_format($policy->premium,2,'.',',') }}</td>--}}
{{--                                    @else--}}
{{--                                        <td width="50%">N/A</td>--}}
{{--                                    @endif--}}
{{--                                </tr>--}}

{{--                                @if($data->plan_id != NULL)--}}
{{--                                <tr>--}}
{{--                                    <th>Product Plan :</th>--}}
{{--                                    <td width="50%">N/A</td>--}}
{{--                                    --}}{{-- <td>{!! $data->name !!}</td> --}}
{{--                                </tr>--}}
{{--                                @endif--}}
{{--                                </thead>--}}
{{--                            </table>--}}
{{--                            <!--end: Datatable -->--}}
{{--                        </div>--}}
                        {{--<div class="kt-portlet__body">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Payment Details : </h3>
                            </div>
                            <table class="table table-striped table-bordered table-hover table-checkable" id="product_details">
                                <thead>
                                <tr>
                                    <th>Billing Day:</th>
                                    @if($data->billing_day)
                                        <td width="50%">{{ $data->billing_day }}</td>
                                    @else
                                        <td width="50%">N/A</td>
                                    @endif
                                </tr>

                                <tr>
                                    <th>Billing Method :</th>
                                    <td width="50%">
                                        <select class="form-control kt_selectpicker" name="new_frequency" title="Please select frequency" data-live-search="true" id="new_frequency">
                                            <option value="">Please Select</option>
                                            <option value="VCS">Credit/Debit Card</option>
                                            <option value="RealPay">Bank Direct Debit Authorization</option>
                                        </select>
                                    </td>
                                </tr>

                                </thead>
                            </table>
                            <!--end: Datatable -->
                        </div>--}}
                    @if($data->product_id == 3)
                        <div class="kt-portlet__body">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Vehicle Details</h3>
                            </div>
                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table3">
                                <thead>
                                <tr>
                                    <th>Japenese Import:</th>
                                    <td width="50%">
                                        @if($data->is_imported == 1) Yes @else No @endif
                                        <input type="hidden" @if($data->is_imported == 1) value="Yes" @else value="No" @endif id="is_imported" />
                                        {{--<select class="form-control kt_selectpicker" name="is_imported" title="Please select type" data-live-search="true" id="is_imported">
                                            <option value="Yes" @if($data->is_imported == 'Yes') selected @endif>Yes</option>
                                            <option value="No" @if($data->is_imported == 'No') selected @endif>No</option>
                                        </select>--}}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Make:</th>
                                    <td width="50%">
                                        {{ $data->make }}
                                        <input type="hidden" id="make_rerate" value="{{ $data->make }}" />
                                        {{--<select class="form-control kt_selectpicker" name="make" title="Please select type" data-live-search="true" id="make">
                                            --}}{{--@foreach($dataMake as $key=>$make)--}}{{--
                                                @if($data->is_imported == 'Yes')
                                                    <option value="{{ $make->s_Make }}" @if($data->make == $make->s_Make) selected @endif>{{ ucwords(strtolower($make->s_Make)) }}</option>
                                                @endif
                                                @if($data->is_imported == 'No')
                                                    <option value="{{ $make }}" @if($data->make == $make) selected @endif>{{ ucwords(strtolower($make)) }}</option>
                                                @endif
                                            --}}{{--@endforeach--}}{{--
                                        </select>--}}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Manufacturing Year:</th>
                                    <td width="50%">
                                        {{ $data->manufacturingYear }}
                                        {{--<input type="hidden" id="year_rerate" value="{{ $data->manufacturingYear }}" />--}}
                                        {{--<select class="form-control kt_selectpicker" name="year" title="Please select manufacturing year" data-live-search="true" id="year">
                                            <?php
                                            for($i = date("1990"); $i < date("Y")+1; $i++){
                                                echo "<option>" . $i . "</option>";
                                            }
                                            ?>
                                        </select>--}}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Model:</th>
                                    <td width="50%">
                                        {{ $data->model }}
                                        <input type="hidden" id="model_rerate" value="{{ $data->model }}" />
                                        {{--<select class="form-control kt_selectpicker" name="model" title="Please select vehicle model" data-live-search="true" id="model">
                                            @if($data->is_imported == 'Yes')
                                                --}}{{--@foreach($dataModel as $key=>$model)--}}{{--
                                                    <option value="{{ $model['model'] }}" @if($data->model == $model['model']) selected @endif>{{ ucwords(strtolower($model['model'])) }}</option>
                                                --}}{{--@endforeach--}}{{--
                                            @endif

                                            @if($data->is_imported == 'No')
                                                --}}{{--@foreach($dataModel as $key=>$model)--}}{{--
                                                    <option value="{{ $model['Model'] }}" @if($data->model == $model['Model']) selected @endif>{{ ucwords(strtolower($model['Model'])) }}</option>
                                                --}}{{--@endforeach--}}{{--
                                            @endif
                                        </select>--}}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Estimated value of vehicle:</th>
                                    <td width="50%">
                                        {{--{{ $data->estimatedValue }}--}}
{{--                                        <input type="hidden" id="estimatedValue" value="{{ $data->estimatedValue }}" />--}}
                                        {{--<span class="spinner-border spinner-border-sm" id="valueLoader"></span>--}}
                                        <input type="text" class="form-control" name="estimatedValue" id="estimatedValue" title="Enter estimated value" value="{{ $data->estimatedValue }}" placeholder="Enter estimated value"/>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Number of prior accidents:</th>
                                    <td width="50%">
                                        {{ $data->priorAccidents }}
                                        {{--<input type="hidden" id="prior_accidents" value="{{ $data->priorAccidents }}" />--}}
                                        {{-- <input type="text" class="form-control"  min="0" max="3" name="prior_accidents" title="Enter number of prior accidents" value="{{ $data->priorAccidents }}" placeholder="Enter number of prior accidents"/> --}}
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
                            <!--end: Datatable -->
                            <div style="margin-bottom:2%" id="new_rate_div">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">New Premium Calculation Details</h3>
                                </div>
                                <form id="newPremiumRate" action="#" method="POST" enctype="multipart/form-data" class="kt-form">
                                    <!-- CSRF Token -->
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                    <input type="hidden" name="rateID" id="rateID" />
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
                                        {{--<tr>
                                            <th>Please select frequency:</th>
                                            <td width="50%">
                                                <select class="form-control kt_selectpicker" name="new_frequency" title="Please select frequency" data-live-search="true" id="new_frequency">
                                                    <option value="1">Monthly Installments</option>
                                                    <option value="2">Three Installmentsin a year</option>
                                                    <option value="3">Annual Installments</option>
                                                </select>
                                                <span id="discountError" class="error" style="display: none; font-size: 12px; color:red;">This field is required.</span>
                                            </td>
                                        </tr>--}}
                                        </thead>
                                    </table>
{{--                                    <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table5">--}}
{{--                                        <thead>--}}
{{--                                        <tr>--}}
{{--                                            <th>Please select payment method:</th>--}}
{{--                                            <td width="50%">--}}
{{--                                                <select class="form-control kt_selectpicker" name="new_frequency" title="Please select frequency" data-live-search="true" id="new_frequency">--}}
{{--                                                    <option value="VCS">Credit/Debit Card</option>--}}
{{--                                                    <option value="RealPay">Bank Direct Debit Authorization</option>--}}
{{--                                                </select>--}}
{{--                                            </td>--}}
{{--                                        </tr>--}}
{{--                                        <tr>--}}
{{--                                            <th>Please select frequency:</th>--}}
{{--                                            <td width="50%">--}}
{{--                                                <select class="form-control kt_selectpicker" name="new_frequency" title="Please select frequency" data-live-search="true" id="new_frequency">--}}
{{--                                                    <option value="1">Monthly Installments</option>--}}
{{--                                                    <option value="2">Three Installments in a year</option>--}}
{{--                                                    <option value="3">Annual Installments</option>--}}
{{--                                                </select>--}}
{{--                                                <span id="discountError" class="error" style="display: none; font-size: 12px; color:red;">This field is required.</span>--}}
{{--                                            </td>--}}
{{--                                        </tr>--}}
{{--                                        </thead>--}}
{{--                                    </table>--}}




                                </form>
                            </div>
                            <div class="row calculation">
                                <div class="col-5"></div>
                                <div class="col-7">
                                    <button class="btn btn-info" id="submit_rerate_button">Calculate</button>
                                    <p class="btn btn-secondary" id="cancel_rerate_button" style="margin-top:2%">Close</p>
                                </div>
                            </div>

                            <div class="row Payment_method">
                                <div class="col-5">
                                    <span style="font-size: 20px; font-weight: bold">Payment Method:</span>
                                </div>
                                <div class="col-7">
                                    <form  action="{{route('admin.policy.processRenewalPolicy')}}" method="POST">
                                         @csrf
                                         <input type="hidden" name="policy_id" value={{ $data->policy_id }}>
                                         <input type="hidden" name="name" value={{$name}}>
                                         <input type="hidden" name="reinstate_type" value="reinstate_fresh">
                                            @if (isset($premium->new_value))
                                                {{-- {{ $premium->new_value }} --}}
                                                <input type="hidden" name="new_premium" value="{{ $premium->new_value }}">
                                            @else
                                                {{-- {{ $policy_premium->premium }} --}}
                                                {{-- {{ $total_premium }} --}}
                                                {{-- <input type="hidden" name="new_premium" value="{{ $policy_premium->premium  }}"> --}}
                                                <input type="hidden" name="new_premium" value="{{ $total_premium }}">
                                            @endif
                                        <button type="submit" name="action" class="btn btn-info" value="cash" id="submit_cash_payment">Pay with Cash</button>
                                        <button type="submit" name="action" class="btn btn-info" value="realpay" id="submit_rerate_button">Pay with Realpay</button>

                                    </form>
                                   </div>
                            </div>

                        </div>
                    @endif
                </div>
            </div>
        </div>


</div>
</div>
@endif
<!-- begin:: Footer -->
@include('includes.footer')
<!-- end:: Footer -->
</div>
<!-- end:: Wrapper -->
</div>
<!-- end:: Page -->
</div>
<!-- end:: Root -->



<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>
    $('#rerate_div').slideUp();

    $('#edit-premium-button').click(function(e) {
        $('#rerate_div').slideDown();
    });

    $('#rerate_hide').click(function(e) {
        $('#rerate_div').slideUp();
    });
</script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script>
    $(document).ready(function () {
        $('#valueLoader').hide();
        $('#new_rate_div').slideUp();
        $('.Payment_method').hide();
        var vehicle_status = "{{ $data->is_imported }}";
        var vehicle_make = "{{ $data->make }}";
        var vehicle_model = "{{ $data->model }}";
        var vehicle_year = "{{ $data->manufacturingYear }}";

        //Get vehicle make model
        // getVehicleMakes(vehicle_status);
        // getVehicleModels(vehicle_status,vehicle_make,vehicle_year);

        $('#is_imported').on('change', function() {
            getVehicleMakes(this.value);
        });

        setInputFilter(document.getElementById("estimatedValue"), function(value) {
            return /^\d*\.?\d*$/.test(value); // Allow digits and '.' only, using a RegExp
        });

        // Restricts input for the given textbox to the given inputFilter function.
        function setInputFilter(textbox, inputFilter) {
            ["input", "keydown", "keyup", "mousedown", "mouseup", "select", "contextmenu", "drop"].forEach(function(event) {
                textbox.addEventListener(event, function() {
                    if (inputFilter(this.value)) {
                        this.oldValue = this.value;
                        this.oldSelectionStart = this.selectionStart;
                        this.oldSelectionEnd = this.selectionEnd;
                    } else if (this.hasOwnProperty("oldValue")) {
                        this.value = this.oldValue;
                        this.setSelectionRange(this.oldSelectionStart, this.oldSelectionEnd);
                    } else {
                        this.value = "";
                    }
                });
            });
        }

        function getVehicleMakes(e) {
            var baseURL = '{{ env('GRAPHITE_URL') }}';
            if(e == 'Yes'){
                var fetchURL = baseURL+'api/frontendpay/vehicleMake'
            }else{
                var fetchURL = baseURL+'api/frontendpay/getTTVehicleMakes'
            }
            $.ajax({
                type: "POST",
                datatype : 'json',
                url: fetchURL,
                dataType: "json",
                beforeSend: function() {
                    $("#loader").show();
                },
                success: function(data){
                    $("#loader").hide();
                    $("#make").html('');
                    $("#make").empty();
                    $("#make").append('<option value="">Select vehicle make</option>');
                    if(e == 'Yes') {
                        $.each(data.makes, function () {
                            $("#make").append('<option value="' + this.s_Make + '">' + this.s_Make + '</option>')
                        });
                    }else{
                        var makes = data.Makes;
                        $.each(makes, function (key,val) {
                            var makes = $('<option value="' + val + '">' + val + '</option>');
                            $("#make").append(makes);
                        });
                    }
                }
            });
        }

        $('#year').on('change',function(){
            var status = $('#is_imported').val();
            var make = $('#make').val();
            var year = this.value;
            getVehicleModels(status,make,year);
        });

        $('#make').on('change',function(){
            var status = $('#is_imported').val();
            var make = this.value;
            var year = $('#year').val();
            $('#estimatedValue').val('');
            getVehicleModels(status,make,year);
        });

        $('#submitRerate').on('click',function(){
            var quoteNumber = $('#quoteCode').val();
            $.ajax({
                url: '{{ route("quote.updatePremiumAjax") }}',
                data: {
                    "_token"       : "{{ csrf_token() }}",
                    "quoteNumber"     : quoteNumber,
                    "type": $('#type').val(),
                    "value_type": $('#value_type').val(),
                    "value": $('#value').val(),
                    "reason": $('#reason').val(),
                },
                beforeSend: function() {
                    $("#loader").show();
                },
                type: 'post',
                datatype: 'json',
                success: function(data) {
                    alert('Success! '+data.message);
                    location.reload();
                },
                error: function(data) {
                    alert('Unsuccessful! '+data.message);
                    location.reload();
                },
                complete: function(data) {
                    location.reload();
                },
            });
        });


        //Rerate--
        $('#submit_rerate_button').on('click', function() {

            //alert($('#gender').valid());
            var baseURL = '{{ env('GRAPHITE_URL') }}';
            // baseURL='https://devgraphite.alphadirect.co.bw/';
           var fetchURL = baseURL + 'api/frontendpay/renewPolicyQuote';
            // console.log(fetchURL);
            $.ajax({
                method: "POST",
                url: fetchURL,
                data: {
                    make: $('#make_rerate').val(),
                    year: $('#year_rerate').val(),
                    model: $('#model_rerate').val(),
                    dob: $('#dob').val(),
                    estimatedValue: $('#estimatedValue').val(),
                    is_imported: $('#is_imported').val(),
                    marital: $('#marital_status').val(),
                    prior_accidents: $('#prior_accidents').val(),
                    gender: $('#gender').val(),
                    policyNumber: '{{ $data->policyNumber }}',
                    customer_id: '{{ $data->customer_id }}',
                    user_id: '{{ auth::user()->id }}',
                    name:'{{$name}}'
                },
                beforeSend: function() {
                    // setting a timeout
                    $('#loader').show();
                    $("#new_rate_div").slideUp();
                    $('.calculation').hide();
                    $('.Payment_method').show();
                },
            }).done(function(response) {
                $('#loader').hide();
                $("#page_footer").slideUp();
                $("#new_rate_div").slideDown();

                $('#monthly_ins').html(response.data.monthly_premium_vat);
                $('#three_ins').html(response.data.threemonthly_preminum_vat);
                $('#annual_ins').html(response.data.result);
                $('#rate_id').html(response.data.rate_id);
                $('#rateID').val(response.data.rate_id);
                $('#premium_rate').html(((response.data.result / $('#estimatedValue').val()) * 100).toFixed(2) + '%');

            }).fail(function(response) {
                $("#new_rate_div").slideUp();
                $("#page_footer").slideDown();
            });
        });

        function getVehicleModels(status,make,year) {
            var baseURL = '{{ env('GRAPHITE_URL') }}';
            if(status == 'Yes'){
                var fetchURL = baseURL+'api/frontendpay/vehicleModel'
            }else{
                var fetchURL = baseURL+'api/frontendpay/getTTVehicleModels'
            }
            if(status == 'Yes'){
                $.ajax({
                    type: "POST",
                    datatype: 'json',
                    url: fetchURL,
                    data: {
                        vehicle_make: make,
                    },
                    dataType: "json",
                    beforeSend: function () {
                        $("#loader").show();
                        $("#model").empty();
                        $('.manufacturing_year').prop('selectedIndex',0);
                    },
                    success: function (responseData) {
                        $("#loader").hide();
                        $("#model").html('');
                        $("#model").empty();
                        $("#model").append('<option value="">Select Model</option>');
                        $.each(responseData.makes, function () {
                            $option = $('<option value="' + this.s_Variant + '">' + this.s_Variant + '</option>');
                            $("#model").append($option);
                        });
                    },complete:function(){
                        $("#loader").hide();
                    }
                });
            }else{
                $.ajax({
                    type: "POST",
                    datatype: 'json',
                    url: fetchURL,
                    data: {

                        make: make,
                        manufacturing_year: year
                    },
                    dataType: "json",
                    beforeSend: function () {
                        $("#loader").show();
                        $("#model").empty();
                        $('#model').prop('selectedIndex',0);
                    },
                    success: function (responseData) {
                        $("#loader").hide();
                        $("#model").html('');
                        $("#model").empty();
                        $("#model").append('<option value="">Select vehicle model</option>');
                        $.each(responseData.Models, function () {
                            $option = $('<option value="' + this.Model + '" data-vehicle="'+ this.IntroYear +'" data-vehicle-disc="'+ this.DisconYear +'" >' + this.Model + '</option>');
                            $("#model").append($option);
                        });
                    },
                    error:function(data){
                        $option = $('<option value="" disabled>No vehicle found</option>');
                        $("#model").append($option);
                    },
                    complete:function(){
                        $("#loader").hide();
                    }
                });
            }
        }

        $('#model').on('change',function(){
            var baseURL = '{{ env('GRAPHITE_URL') }}';
            $('#estimatedValue').val('');
            if(this.value && $('#is_imported').val() == 'No'){
                $.ajax({
                    type: "POST",
                    datatype: 'json',
                    url: baseURL+'api/frontendpay/getTTValue',
                    data: {
                        vehicleMake: $('#make').val(),
                        vehicleModel: $(this).val(),
                        manufacturing_year: $('#year').val(),
                        condition: 'EX',
                        mileage: 'LO',
                    },
                    dataType: "json",
                    beforeSend: function () {
                        $('#valueLoader').show();
                        $('#estimatedValue').hide();
                        $('#ratingsCalculation').prop('disabled', true);
                    },
                    success: function (data) {
                        $('#valueLoader').hide();
                        $('#estimatedValue').hide();
                        if(data.value){
                            var val = (data.value).toFixed(2)
                            $('#estimatedValue').val(val);
                            $('#ratingsCalculation').prop('disabled', false);
                            if(data.value > 500000){
                                $('#ratingsCalculation').prop('disabled', true);
                                $("#ratingsCalculation").hide();
                                $("#requestcallback").show();
                            }
                        }
                    },
                    error: function () {
                        $('#valueLoader').hide();
                        $('#estimatedValue').show();
                        $('#estimatedValue').val('');
                        $('#ratingsCalculation').prop('disabled', false);
                    },
                    complete: function() {
                        $('#valueLoader').hide();
                        $('#ratingsCalculation').prop('disabled', false);
                        $('#estimatedValue').show();
                    },
                });
            }
        });

        $('#year').prop('value',vehicle_year);
    });
</script>


</body>
<!-- end::Body -->
</html>
