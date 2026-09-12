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
                        Terms
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Policies</span> </a>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View</span> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->

            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="row ol-lg-12">
                    <div class="col-lg-3 text-left policyno">
                        <h5> Policy No # {{$policy->policyNumber}}</h5> <br>
                    </div>
                    <div style="right: 0px; position: absolute; margin-right: 40px;">
                        <a class="btn btn-info" href="{{ route('admin.policy.policyView', $policy->id) }}" >Back</a>
                    </div>
                </div>
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">Term Details</h3>
                        </div>
                        <br>
                        <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table3">
                            <thead>
                            <tr>
                                <th>Term Start Date:</th>
                                <td width="50%">
                                    @if (isset($policy_term->term_start_date))
                                       {{ \Carbon\Carbon::createFromFormat('Y-m-d', $policy_term->term_start_date)->format('d-m-Y') }}
                                    @else
                                        {{ 'N/A' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Term End Date:</th>
                                <td width="50%">
                                    @if (isset($policy_term->term_end_date))
                                        {{ \Carbon\Carbon::createFromFormat('Y-m-d', $policy_term->term_end_date)->format('d-m-Y') }}
                                    @else
                                        {{ 'N/A' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Premium:</th>
                                <td width="50%">
                                    @if (isset($policy_term->premium))
                                       {{ $policy_term->premium }}
                                    @else
                                        {{ 'N/A' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Frequency:</th>
                                <td width="50%">
                                    @if (isset($policy_term->frequency))
                                       @if ($policy_term->frequency == 1)
                                            {{ 'Monthly Installments' }}
                                       @elseif($policy_term->frequency == 2)
                                            {{ 'Three Installments in a year' }}
                                       @elseif($policy_term->frequency == 3)
                                            {{ 'Annual Installment' }}
                                       @else
                                           {{ 'Not Found' }}
                                       @endif
                                    @else
                                        {{ 'N/A' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>First Premium:</th>
                                <td width="50%">
                                    @if (isset($policy_term->first_premium))
                                       {{ $policy_term->first_premium }}
                                    @else
                                        {{ 'N/A' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Billing Start Date:</th>
                                <td width="50%">
                                    @if (isset($policy_term->billing_start_date))
                                       {{ \Carbon\Carbon::createFromFormat('Y-m-d', $policy_term->billing_start_date)->format('d-m-Y') }}
                                    @else
                                        {{ 'N/A' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Policy Activated Date:</th>
                                <td width="50%">
                                    @if (isset($policy_term->policyActivatedDate))
                                       {{ \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $policy_term->policyActivatedDate)->format('d-m-Y') }}
                                    @else
                                        {{ 'N/A' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td width="50%">
                                    @if (isset($policy_term->status))
                                       @if ($policy_term->status == 'Active')
                                            <span class="kt-font-bold kt-font-accent">Active</span>
                                       @else
                                            <span class="kt-font-bold kt-font-danger">Deactive</span>
                                       @endif
                                    @else
                                        {{ 'N/A' }}
                                    @endif
                                </td>
                            </tr>
                            {{-- <tr>
                                <th>Purpose:</th>
                                <td width="50%">
                                    @if (isset($policy_term->first_premium))
                                       {{ $policy_term->first_premium }}
                                    @else
                                        {{ 'N/A' }}
                                    @endif
                                </td>
                            </tr> --}}

                            </thead>
                        </table>

                        <br>
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">Vehicle Images</h3>
                        </div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    <div class="form-group row">
                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Left</h3>
                                            <div class="kt-avatar" id="left" style="float: left; clear: left;">
                                                @if(!isset($vehicleOld->left))
                                                <div class="kt-avatar__holder"
                                                    style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                </div>
                                                @else
                                                <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->left) }}">
                                                    <div class="kt-avatar__holder"
                                                        style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->left) !!}')">
                                                    </div>
                                                </a>
                                                @endif


                                            </div>
                                        </div>


                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Right</h3>
                                            <div class="kt-avatar" id="right" style="float: left; clear: left;">
                                                @if(!isset($vehicleOld->right))
                                                <div class="kt-avatar__holder"
                                                    style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                </div>
                                                @else
                                                <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->right) }}">
                                                    <div class="kt-avatar__holder"
                                                        style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->right) !!}')">
                                                    </div>
                                                </a>
                                                @endif

                                            </div>
                                        </div>

                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Back</h3>
                                            <div class="kt-avatar" id="back" style="float: left; clear: left;">
                                                @if(!isset($vehicleOld->back))
                                                <div class="kt-avatar__holder"
                                                    style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                </div>
                                                @else
                                                <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->back) }}">
                                                    <div class="kt-avatar__holder"
                                                        style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->back) !!}')">
                                                    </div>
                                                </a>
                                                @endif

                                            </div>
                                        </div>

                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Front</h3>
                                            <div class="kt-avatar" id="front" style="float: left; clear: left;">
                                                @if(!isset($vehicleOld->front))
                                                <div class="kt-avatar__holder"
                                                    style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                </div>
                                                @else
                                                <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->front) }}">
                                                    <div class="kt-avatar__holder"
                                                        style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->front) !!}')">
                                                    </div>
                                                </a>
                                                @endif

                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Vehicle
                                                Registration</h3>
                                            <div class="kt-avatar" id="vehicleRegistration"
                                                style="float: left; clear: left;">
                                                @if(!isset($vehicleOld->vehicleRegistration))
                                                <div class="kt-avatar__holder"
                                                    style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                </div>
                                                @else
                                                <a
                                                    href="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->vehicleRegistration) }}">
                                                    <div class="kt-avatar__holder"
                                                        style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->vehicleRegistration) !!}')">

                                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->vehicleRegistration) !!}"
                                                            target="_blank" download>
                                                            @if(pathinfo($vehicleOld->vehicleRegistration,
                                                            PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($vehicleOld->vehicleRegistration,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($vehicleOld->vehicleRegistration, PATHINFO_EXTENSION)
                                                            == 'doc' || pathinfo($vehicleOld->vehicleRegistration,
                                                            PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($vehicleOld->vehicleRegistration,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($vehicleOld->vehicleRegistration, PATHINFO_EXTENSION)
                                                            == 'xlsx' || pathinfo($vehicleOld->vehicleRegistration,
                                                            PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($vehicleOld->vehicleRegistration,
                                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                                            pathinfo($vehicleOld->vehicleRegistration, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($vehicleOld->vehicleRegistration,
                                                            PATHINFO_EXTENSION) == 'png')
                                                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicleOld->vehicleRegistration)}}"
                                                                width="100%" height="auto">
                                                            @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                height="auto">
                                                            @endif
                                                        </a>
                                                    </div>
                                                </a>
                                                @endif

                                            </div>

                                        </div>
                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Vehicle Invoice</h3>
                                            <div class="kt-avatar" id="vehicle_valuation"
                                                style="float: left; clear: left;">
                                                @if(!isset($vehicleOld->vehicle_valuation))
                                                <div class="kt-avatar__holder"
                                                    style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                </div>
                                                @else
                                                <a
                                                    href="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->vehicle_valuation) }}">
                                                    <div class="kt-avatar__holder"
                                                        style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->vehicle_valuation) !!}')">

                                                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicleOld->vehicle_valuation) !!}"
                                                            target="_blank" download>
                                                            @if(pathinfo($vehicleOld->vehicle_valuation,
                                                            PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($vehicleOld->vehicle_valuation,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($vehicleOld->vehicle_valuation, PATHINFO_EXTENSION)
                                                            == 'doc' || pathinfo($vehicleOld->vehicle_valuation,
                                                            PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($vehicleOld->vehicle_valuation,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($vehicleOld->vehicle_valuation, PATHINFO_EXTENSION)
                                                            == 'xlsx' || pathinfo($vehicleOld->vehicle_valuation,
                                                            PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}" width="100%"
                                                                height="auto">
                                                            @elseif(pathinfo($vehicleOld->vehicle_valuation,
                                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                                            pathinfo($vehicleOld->vehicle_valuation, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($vehicleOld->vehicle_valuation,
                                                            PATHINFO_EXTENSION) == 'png')
                                                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicleOld->vehicle_valuation)}}"
                                                                width="100%" height="auto">
                                                            @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                height="auto">
                                                            @endif
                                                        </a>
                                                    </div>
                                                </a>
                                                @endif

                                            </div>

                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
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
{{-- <script>
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
</script> --}}

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
