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
                        Policy Renewal
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
                    <div class="kt-portlet__head-label" style="padding: 10px">
                        <h3 class="kt-portlet__head-title">Vehicle Details
                            <a href="{{ route('admin.policy.addDiscountSurcharge',$data->policy_id) }}">
                                <button class="btn btn-info" id="submit_discount_surcharge" style="float: right;">Add Discount/Surcharge</button>
                            </a>
                        </h3>
                    </div>
                    <form id="policyForm1" action="{{ route('admin.policy.processRenewalPolicy') }}" method="POST" enctype="multipart/form-data" class="kt-form">

                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <input type="hidden" id="quoteCode" name="quoteCode" {{--value="{{ $data->quoteCode }}"--}} />
                        <input type="hidden" name="policy_id" value="{{$data->policy_id}}">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   @if($data->product_id == 3)
                        <div class="kt-portlet__body">
                            <br>
                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table3">
                                <thead>
                                <tr>
                                    <th>Japenese Import:</th>
                                    <td width="50%">
                                        @if($data->is_imported == 1) Yes @else No @endif
                                        <input type="hidden" @if($data->is_imported == 1) value="Yes" @else value="No" @endif id="is_imported" />

                                    </td>
                                </tr>
                                <tr>
                                    <th>Make:</th>
                                    <td width="50%">
                                        {{ $data->make }}
                                        <input type="hidden" id="make_rerate" value="{{ $data->make }}" />

                                    </td>
                                </tr>
                                <tr>
                                    <th>Manufacturing Year:</th>
                                    <td width="50%">
                                        {{ $data->manufacturingYear }}

                                    </td>
                                </tr>
                                <tr>
                                    <th>Model:</th>
                                    <td width="50%">
                                        {{ $data->model }}
                                        <input type="hidden" id="model_rerate" value="{{ $data->model }}" />

                                    </td>
                                </tr>
                                <tr>
                                    <th>Estimated value of vehicle:</th>
                                    <td width="50%">
                                        {{ $data->estimatedValue }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Number of prior accidents:</th>
                                    <td width="50%">
                                        {{ $data->priorAccidents }}

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

                        </div>

                        <div class="kt-portlet__body">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Premium Details</h3>
                            </div>
                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table3">
                                <thead>
                                <tr>
                                    <th>Old Premium:</th>
                                    <td width="50%">
                                        {{ $data->old_premium }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>New Premium:</th>
                                    <td width="50%">
                                        {{-- @isset($premium->new_value) --}}
                                            @if (isset($premium->new_value))
                                                {{ $premium->new_value }}
                                                <input type="hidden" name="new_premium" value="{{ $premium->new_value }}">
                                            @elseif (isset($data->new_premium))
                                                {{ $data->new_premium }}
                                                <input type="hidden" name="new_premium" value="{{ $data->new_premium }}">
                                            @else
                                                {{ $policy_premium->premium }}
                                                <input type="hidden" name="new_premium" value="{{ $policy_premium->premium }}">
                                            @endif
                                        {{-- @endisset --}}
                                    </td>
                                </tr>
                                </thead>
                            </table>

                            <br><br>
                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table3">
                                <thead>
                                <tr>
                                    <th>Term Start Date:</th>
                                    <td width="50%">
                                        <input type="text" class="form-control kt_datepicker_1 required validateGroup1"
                                        name="term_start_date" id="term_start_date" autocomplete="off" placeholder="Select start date of policy" />
                                        <span class="form-text text-muted"></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Renew By:</th>
                                    <td width="50%">
                                        <select class="select2 form-control kt_selectpicker" name="agent_id" id="agent_id" data-live-search="true" title="Select Agent">
                                            @foreach($agents as $agent)
                                                <option value="{{$agent->id}}" @if($data->agent_id == $agent->id) selected
                                                        @endif>{{$agent->firstName}}
                                                    {{$agent->lastName}}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                </thead>
                            </table>

                            <br><br>
                            <div class="row">
                                <div class="col-5">
                                    <span style="font-size: 20px; font-weight: bold">Payment Method:</span>
                                </div>
                                <div class="col-7">
                                    <a href="{{ route('admin.policy.edit', [$data->policy_id,'#kt_portlet_base_demo_3_7_rerate_premium']) }}" class="btn btn-success">Rerate Premium</a>
                                    {{-- @if($product->id == 3 && isset($is_renewal->is_renewed)  && $is_renewal->is_renewed == 0) --}}
                                    @if(auth()->user()->hasRole(['Super Admin']))
                                        <a href="{{ route('admin.policy.generateAndSendRenewalLink', $data->policyNumber) }}" class="btn btn-success">Generate Renew Link</a>
                                    @endif
                                    {{-- @endif --}}
                                    <button type="submit" name="action" class="btn btn-info" value="cash" id="submit_cash_payment">Pay with Cash</button>
                                    {{-- <a href="{{ route('admin.policy.addOfflinePayment',$data->policy_id) }}"><button class="btn btn-info" id="submit_cash_payment">Pay with Cash</button></a> --}}
                                    <button type="submit" name="action" class="btn btn-info" value="realpay" id="submit_rerate_button">Pay with Realpay</button>
                                </div>
                            </div>
                        </div>
                    @endif
                    </form>
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
<script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}"
        type="text/javascript"></script>
    <script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}"
        type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}"
        type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script>
    $(document).ready(function () {
        $('.kt_datepicker_1').datepicker({
            rtl: KTUtil.isRTL(),
            todayHighlight: true,
            orientation: "bottom left",
            // templates: arrows,
            format: 'yyyy-mm-dd',
            minDate : 0,
        });

        $('#valueLoader').hide();
        $('#new_rate_div').slideUp();
        $('#agent_id').selectpicker('refresh');

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
                },
                beforeSend: function() {
                    // setting a timeout
                    $('#loader').show();
                    $("#new_rate_div").slideUp();
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

{{-- <script>
    $('#submit_cash_payment').on('click',function(){
    var new_premium = "{!! $data->new_premium !!}";
    var term_start_date = $('#term_start_date').val();
    var agent_id = $('#agent_id').val();
    $.ajax({
        type: "POST",
        datatype : 'json',
        url: "{!! route('admin.policy.addOfflinePayment',$data->policy_id) !!}",
        dataType: "json",
        data: {
            new_premium: new_premium,
            term_start_date: term_start_date,
            agent_id: agent_id,
        },
        beforeSend: function() {
            $("#loader").show();
        },
        success: function(data){
            console.log(data);
        }
    });
    });
</script> --}}

<script>
    $("#policyForm1").validate({
            ignore: [],
            ignore: ".ignore",
            // define validation rules
            rules: {
                term_start_date: {
                    required: true
                },
                agent_id: {
                    required: true
                },
            },
            messages: {
                term_start_date: {
                    required: 'Please select policy start date.'
                },
                agent_id: {
                    required: 'Please select renewed by.'
                },
            },

            //display error alert on form submit
            invalidHandler: function(event, validator) {
                $('html, body').animate({
                    scrollTop: $(validator.errorList[0].element).offset().top - 200
                }, 1000);
            },

            submitHandler: function(form) {
                form.submit(); // submit the form
            }
        });
</script>

</body>
<!-- end::Body -->
</html>
