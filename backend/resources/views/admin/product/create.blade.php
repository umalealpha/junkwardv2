<!DOCTYPE html>
<html lang="en">
@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet"
    type="text/css" />
<!-- begin::Body -->
<body>
    <div class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
    <!-- begin:: Header Mobile -->
    <div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed ">
        <div class="kt-header-mobile__logo">
            <a>
                <img alt="Logo" src="{{asset('images/logo.png')}}" />
            </a>
        </div>
        <div class="kt-header-mobile__toolbar">
            <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left"
                id="kt_aside_mobile_toggler"><span></span></button>
            <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
            <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i
                    class="flaticon-more"></i></button>
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
        <!-- check if is first time login -->

        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Create Product
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}"
                            class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span
                            class="kt-subheader__breadcrumbs-separator"></span> <a
                            href="{{Route('admin.product.index')}}" class="kt-subheader__breadcrumbs-link">Product </a>
                        <span class="kt-subheader__breadcrumbs-separator"></span>
                        <span
                            class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <!--begin::Portlet-->
                <div class="kt-portlet">
                    <!--begin::Form-->
                    <form id="productCreate" action="{{ route('admin.product.store') }}" method="POST"
                        enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <div class="kt-portlet__body">
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Product Name</label>
                                <div class="col-9">
                                    <input type="text" class="form-control" title="Product name is required"
                                        name="product_name" value="{!! old('name') !!}"
                                        placeholder="Enter Product name">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Policy No. Initial</label>
                                <div class="col-9">
                                    <input type="text" class="form-control" onkeyup="this.value = this.value.toUpperCase();" onkeypress="return /[a-z]/i.test(event.key)" title="Policy Initial is required" name="policy_initials"
                                           value="" placeholder="Enter first 3 Initials for Policy No.">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Product Customer Friendly
                                    Name</label>
                                <div class="col-9">
                                    <input type="text" class="form-control"
                                        title="Product customer friendly name is required" value="" name="slug"
                                        value="{!! old('slug') !!}" placeholder="Enter Product Customer Friendly Name">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Premium Type</label>
                                <div class="col-9">
                                    <select class="form-control kt_selectpicker" title="Please choose premium type"
                                        data-live-search="true" id="premium" name="premium_type" required>
                                        @foreach($premiumType as $type)
                                        <option value="{{$type->id}}">{{$type->value}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Product Type</label>
                                <div class="col-9">
                                    <select class="form-control kt_selectpicker" id="product_type_id"
                                        title="Please choose product type" data-live-search="true" id="Product"
                                        name="product_type_id" required>
                                        @foreach($productType as $prod)
                                        <option value="{{$prod->id}}">{{$prod->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Upload Product
                                    Image</label>
                                <div class="col-2">
                                    <div class="kt-avatar" id="product_image" style="float: left; clear: left;">

                                        <div class="kt-avatar__holder"
                                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>

                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' name="product_image"
                                                <?php echo config('app.accept_attr'); ?>
                                                <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                            <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>

                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Billing Cycle</label>
                                <div class="col-9">
                                    <select class="form-control kt_selectpicker" title="Please choose billing cycle"
                                        data-live-search="true" id="billing" name="billing_cycle" required>
                                        @foreach($billingCycles as $billingCycle)
                                        <option value="{{$billingCycle->value}}">{{$billingCycle->value}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Sum
                                    Insured/Asssured:</label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="sum" id="sum"
                                        title="Please enter sum assured">
                                    <p id="sumError" style="display:none;color:red"> Sum Insured/Assured value should be
                                        less than or Equal to <span id="sum_value"></span></p>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Region</label>
                                <div class="col-9">
                                    <select class="form-control kt_selectpicker" title="Please choose region"
                                        data-live-search="true" id="region" name="product_region" required>
                                        @foreach($regions as $region)
                                        <option value="{{$region->id}}">{{$region->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">KYC Compliance</label>
                                <div class="col-9">
                                    <select class="form-control kt_selectpicker" title="Please choose kyc compliance"
                                        data-live-search="true" id="kyc_compliance" name="flow_kyc_compliance" required>
                                        @isset($kycCompliance)
                                            @foreach($kycCompliance as $compliance)
                                                <option value="{{$compliance->id}}">{{$compliance->name}}</option>
                                            @endforeach
                                        @endisset
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row" style="display: none;" id="licenseDiv">
                                <label for="example-text-input" class="col-3 col-form-label">Licenses</label>
                                <div class="col-9">
                                    <div class="kt-checkbox-inline" id="licenseData">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-4">
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Wordings</label>
                                        <div class="col-9">
                                            <span class="kt-switch">
                                                <label>
                                                    <input id="wordingValue" type="checkbox" name="has_wordings" value="1"
                                                        onchange="wordingsMsg()">
                                                    <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                    <h4 id="wording"
                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                        No</h4>
                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Schedule</label>
                                        <div class="col-9">
                                            <span class="kt-switch">
                                                <label>
                                                    <input id="scheduleValue" type="checkbox" name="has_schedule" value="1"
                                                        onchange="scheduleMsg()">
                                                    <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                    <h4 id="schedule"
                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                        No</h4>
                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">KYC for Customer</label>
                                        <div class="col-9">
                                            <span class="kt-switch">
                                                <label>
                                                    <input id="customerValue" type="checkbox" name="customer" value="1"
                                                        onchange="customerMsg()">
                                                    <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                    <h4 id="cus1"
                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                        No</h4>
                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">KYC for Recipient</label>
                                        <div class="col-9">
                                            <span class="kt-switch">
                                                <label>
                                                    <input id="recipientValue" type="checkbox" name="recipient" value="1"
                                                        onchange="recipientMsg()">
                                                    <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                    <h4 id="rec1"
                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                        No</h4>
                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Vehicle Insurance</label>
                                        <div class="col-9">
                                            <span class="kt-switch">
                                                <label>
                                                    <input id="vehicleValue" type="checkbox" name="is_vehicle" value="1"
                                                        onchange="vehicleMsg()">
                                                    <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                    <h4 id="Msg"
                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                        No</h4>

                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Life Insurance</label>
                                        <div class="col-9">
                                            <span class="kt-switch">
                                                <label>
                                                    <input id="memberValue" type="checkbox" name="is_member" value="1"
                                                        onchange="memberMsg()">
                                                    <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                    <h4 id="msg1"
                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                        No</h4>
                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4" id="sub_applicant" style="display:none;">
                                    <div class="form-group row" >
                                        <label for="example-text-input" class="col-3 col-form-label">Sub Applicant</label>
                                        <div class="col-9">
                                            <span class="kt-switch">
                                                <label>
                                                    <input id="applicantValue" type="checkbox" name="is_applicant" value="1"
                                                        onchange="ApplicantMsg()">
                                                    <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                    <h4 id="app_msg"
                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                        No</h4>
                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Has Activation Code</label>
                                        <div class="col-9">
                                            <span class="kt-switch">
                                                <label>
                                                    <input id="activationValue" type="checkbox" name="has_activation" value="1"
                                                        onchange="activationMsg()">
                                                    <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                    <h4 id="msg2"
                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                        No</h4>
                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Specified Motor
                                            Items</label>
                                        <div class="col-9">
                                            <span class="kt-switch">
                                                <label>
                                                    <input id="motorValue" type="checkbox" name="is_motor_items" value="1"
                                                        onchange="motorMsg()">
                                                    <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                    <h4 id="motormsg2"
                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                        No</h4>
                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Preinspection</label>
                                        <div class="col-9">
                                            <span class="kt-switch">
                                                <label>
                                                    <input id="inspection" type="checkbox" name="inspection" value="1"
                                                        onchange="preinspection()">
                                                    <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                    <h4 id="inspectionMsg"
                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                        No</h4>
                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4" style="display:none" id="inspectDiv">
                                    <div class="form-group row" >
                                        <label for="example-text-input" class="col-3 col-form-label">Limit:</label>
                                        <div class="col-9">
                                            <input type="name" class="form-control" name="limit"
                                                title="Please enter preinspection limit">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">
                                        <br>Show on <br>
                                            start.alphadirect.co.bw?</label>
                                        <div class="col-9">
                                            <span class="kt-switch">
                                                <label>
                                                    <input id="isForStartValue" type="checkbox" name="isForStart" value="1"
                                                        onchange="isForStartMsg()">
                                                    <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                    <h4 id="startmsg2"
                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                        No</h4>
                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Status</label>
                                        <div class="col-9">
                                            <span class="kt-switch">
                                                <label>
                                                    <input id="switchValue" type="checkbox" checked="checked" name="status" value="1" 
                                                       onchange="statusMsg()">
                                                    <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                    <h4 id="switchMsg"
                                                        style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:cornflowerblue;">
                                                        Active</h4>
                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12" id="coverages">
                                    <div class="form-group row" >
                                        <label for="example-text-input" class="col-3 col-form-label">Coverages</label>
                                        <div class="col-9">
                                            <div class="kt-checkbox-inline">
                                                @foreach($coverages as $coverage)
                                                <label class="kt-checkbox col-3"><input type="checkbox" class="coverage"
                                                        name="coverage[]" value="{!! $coverage->id !!}">{!! $coverage->s_ScreenName
                                                    !!}<span></span></label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                             
                        </div>

                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9">
                                        <button type="submit" value="Submit" id="submit_btn"
                                            class="btn btn-brand">Submit</button>
                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none">
                                            <span class="spinner-border spinner-border-sm" role="status"
                                                aria-hidden="true"></span> Loading... </button>
                                        <a class="btn btn-secondary"
                                            href="{{ route('admin.product.index') }}">Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    <!--end::Form-->
                </div>
                <!--end::Portlet-->
            </div>
            <!-- end:: Content -->
        </div>

        <!-- begin:: Footer -->
        @include('includes.footer')
        <!-- end:: Footer -->
    </div>
    <!-- end:: Wrapper -->
    </div>
    <!-- end:: Page -->
    <!-- end:: Root -->
    <!-- begin:: Scrolltop -->
    <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
    <!-- end:: Scrolltop -->
    @include('admin.layouts.scripts')
    <script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}"
        type="text/javascript"></script>
    <script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}"
        type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}"
        type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}"
        type="text/javascript"></script>
    <script>
        $(document).ready(function() {
        var ajaxRequest;
        $('#sum').keyup(function() {
            var value = $(this).val();
            var product_id = $("#product_id").val();
            clearTimeout(ajaxRequest);
            ajaxRequest = setTimeout(function(sn) {

                $.ajax({
                    url: '{{ route('admin.product.checkSumAssured') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "sum": value,
                        "product_id": product_id
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if(data.error) {
                            $('#sum_value').html(data.value);
                            $('#sumError').css('display','block');
                            $('#submit_btn').attr("disabled",true);
                        } else {
                            $('#sumError').css('display','none');
                            $('#submit_btn').attr("disabled",false);
                        }
                    }
                });


            }, 500, value);
        });
    });
    </script>
    <script>
        $('#region').on('change', function() {
        var append = '';
        var region_id = this.value;
        $.ajax({
            url: '{{ route('admin.product.regionLicense') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": region_id
                },
            type: 'post',
            datatype: 'json',
            success: function (data) {
                if(data.regionLicenses.length != 0)
                {
                    data.regionLicenses.forEach(function ($regionLicense) {
                        append += '<label class="kt-checkbox col-3"><input type="checkbox" class="license" name="license[]" value='+ $regionLicense.id + '>'+ $regionLicense.license_name +'  :   '+ $regionLicense.license_number +'<span></span></label>';
                        //append += '<input style="margin-left:2%;margin-right:1%" class=" kt-checkbox--brand" type="checkbox" name="license[]" value="'+ $regionLicense.id +'" data-live-search="true" title="Please choose region license"/>'+ $regionLicense.license_name +'  :   '+ $regionLicense.license_number;
                    });
                } else {
                    append += '<label>No Licenses Added for this Region</label>';
                }

                $('#licenseData').html(append);
                if(data.regionLicenses.length != 0)
                    $('.license').rules('add',  { required: true, messages: { required: "Please Select License" } });
                else
                    $('.license').rules('remove',  'required');

            },
        });

        $('#licenseDiv').delay(100).slideDown(500);
        //$("#licenseData").selectpicker('refresh');

    });






    </script>
    <script>
        "use strict";
        // Class definition
    var KTFormControls = function () {
       // Private functions
        var demo1 = function () {
            $( "#productCreate" ).validate({
               // define validation rules
                rules: {
                    product_name: {
                        required: true,
                    },
                    policy_initials:{
                        lettersonly: true,
                        minlength:3,
                        maxlength:3,
                        required: true,
                    },
                    limit:{
                        number: true,
                    },
                    value:{required: true,},
                    product_id:{required: true,},
                    premium_type:{required: true,},
                    slug:{required: true,},
                    billing_cycle:{required: true,},
                    sum:{required: true,},
                    product_region:{required: true,},
                },
                messages : {
                    product_id:{
                        required: "Please select Product Type",
                    },
                    premium_type:{
                        required: "Please select Premium Type",
                    },
                    slug:{
                        required: "Please Enter Customer Friendly Name",
                    },
                    billing_cycle:{
                        required: "Please select Billing Cycle",
                    },
                    sum:{
                        required: "Please Enter Sum Insured/Asssured"
                    },
                    product_region:{
                        required: "Please Select Region",
                    },
                    policy_initials:{
                        lettersonly:"Only Letters are accepted",
                        minlength:"Please Enter atleast 3 Initials",
                        maxlength:"Please Enter 3 Initials only",
                    },
                },
                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    $('#submit_btn').show();
                    KTUtil.scrollTo("productCreate", -200);
                },
                submitHandler: function (form) {
                    $('#submit_btn').hide();
                    $('#loadBtn').show();
                    form[0].submit(); // submit the form
                }
            });
        }
        return {
       // public functions
            init: function() {
                demo1();
            }
        };
    }();
    jQuery(document).ready(function() {
        KTFormControls.init();
    });
    var KTAvatarDemo = function() {
        return {
            // Init demos
            init: function() {
                var avatar1 = new KTAvatar('product_image');
            }
        };
    }();
    jQuery(document).ready(function(){
        KTAvatarDemo.init();
    });
    </script>
    <script>
        function statusMsg(){
        var isChecked=document.getElementById("switchValue").checked;
        if (isChecked){
            document.getElementById("switchMsg").innerHTML="Active";
            document.getElementById("switchMsg").style.color="cornflowerblue";
        }
        else {
            document.getElementById("switchMsg").innerHTML="Inactive";
            document.getElementById("switchMsg").style.color="#ff4d4d";
        }

    }
    </script>
    <script>
        function preinspection(){
        var isChecked=document.getElementById("inspection").checked;
        if (isChecked){
            document.getElementById("inspectionMsg").innerHTML="Yes";
            document.getElementById("inspectionMsg").style.color="cornflowerblue";
            /*document.getElementById("inspectDiv").style.display="block";*/
            $('#inspectDiv').delay(100).slideDown(500);


        }
        else {
            document.getElementById("inspectionMsg").innerHTML="No";
            document.getElementById("inspectionMsg").style.color="#ff4d4d";
            /*document.getElementById("inspectDiv").style.display="none";*/
            $('#inspectDiv').delay(100).slideUp(500);
        }

    }
    </script>
    {{-- Vehicle Checkbox starts--}}
    <script>
        function vehicleMsg(){
        var isChecked=document.getElementById("vehicleValue").checked;
        if (isChecked){
            document.getElementById("Msg").innerHTML="Yes";
            document.getElementById("Msg").style.color="cornflowerblue";
            $('#coverages').delay(100).slideDown(500);
            $('.coverage').rules('add',  { required: true, messages: { required: "Please Select Coverages" } });
        }
        else {
            document.getElementById("Msg").innerHTML="No";
            document.getElementById("Msg").style.color="#ff4d4d";
            $('#coverages').delay(100).slideUp(500);
            $('.coverage').rules('remove',  'required');
        }

    }
    </script>
    {{-- Vehicle Checkbox ends--}}
    {{-- Member Checkbox starts--}}
    <script>
        function recipientMsg(){
        var isChecked=document.getElementById("recipientValue").checked;
        if (isChecked){
            document.getElementById("rec1").innerHTML="Yes";
            document.getElementById("rec1").style.color="cornflowerblue";

        }
        else {
            document.getElementById("rec1").innerHTML="No";
            document.getElementById("rec1").style.color="#ff4d4d";
        }

    }
    </script>
    <script>
        function wordingsMsg(){
            var isChecked=document.getElementById("wordingValue").checked;
            if (isChecked){
                document.getElementById("wording").innerHTML="Yes";
                document.getElementById("wording").style.color="cornflowerblue";
            }
            else {
                document.getElementById("wording").innerHTML="No";
                document.getElementById("wording").style.color="#ff4d4d";
            }
        }

        function scheduleMsg(){
            var isChecked=document.getElementById("scheduleValue").checked;
            if (isChecked){
                document.getElementById("schedule").innerHTML="Yes";
                document.getElementById("schedule").style.color="cornflowerblue";
            }
            else {
                document.getElementById("schedule").innerHTML="No";
                document.getElementById("schedule").style.color="#ff4d4d";
            }
        }

        function customerMsg(){
        var isChecked=document.getElementById("customerValue").checked;
        if (isChecked){
            document.getElementById("cus1").innerHTML="Yes";
            document.getElementById("cus1").style.color="cornflowerblue";

        }
        else {
            document.getElementById("cus1").innerHTML="No";
            document.getElementById("cus1").style.color="#ff4d4d";
        }

    }
    </script>
    <script>
        function memberMsg(){
        var isChecked=document.getElementById("memberValue").checked;
        if (isChecked){
            document.getElementById("msg1").innerHTML="Yes";
            document.getElementById("msg1").style.color="cornflowerblue";
            $('#sub_applicant').delay(100).slideDown(500);


        }
        else {
            document.getElementById("msg1").innerHTML="No";
            document.getElementById("msg1").style.color="#ff4d4d";
            $('#sub_applicant').delay(100).slideUp(500);

        }

    }

    function ApplicantMsg(){
        var isChecked=document.getElementById("applicantValue").checked;
        if (isChecked){
            document.getElementById("app_msg").innerHTML="Yes";
            document.getElementById("app_msg").style.color="cornflowerblue";

        }
        else {
            document.getElementById("app_msg").innerHTML="No";
            document.getElementById("app_msg").style.color="#ff4d4d";

        }

    }
    </script>
    {{-- Member Checkbox ends--}}
    <script>
        function activationMsg(){
        var isChecked=document.getElementById("activationValue").checked;
        if (isChecked){
            document.getElementById("msg2").innerHTML="Yes";
            document.getElementById("msg2").style.color="cornflowerblue";
        }
        else {
            document.getElementById("msg2").innerHTML="No";
            document.getElementById("msg2").style.color="#ff4d4d";
        }
    }
    </script>
    <script>
        function motorMsg(){
        var isChecked=document.getElementById("motorValue").checked;
        if (isChecked){
            document.getElementById("motormsg2").innerHTML="Yes";
            document.getElementById("motormsg2").style.color="cornflowerblue";
        }
        else {
            document.getElementById("motormsg2").innerHTML="No";
            document.getElementById("motormsg2").style.color="#ff4d4d";
        }
    }
    function isForStartMsg(){
        var isChecked=document.getElementById("isForStartValue").checked;
        if (isChecked){
            document.getElementById("startmsg2").innerHTML="Yes";
            document.getElementById("startmsg2").style.color="cornflowerblue";
        }
        else {
            document.getElementById("startmsg2").innerHTML="No";
            document.getElementById("startmsg2").style.color="#ff4d4d";
        }
    }
    </script>
</body>
<!-- end::Body -->
</html>
