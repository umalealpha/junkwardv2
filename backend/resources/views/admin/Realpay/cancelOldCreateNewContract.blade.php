<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
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
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Create New Contract
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.accounts.index')}}" class="kt-subheader__breadcrumbs-link"> Realpay </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create new contract</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!--If Password default, show edit details -->
    {{--    @if (Auth::user()->default_password == "111111")
       @include('includes.reset')
       @else --}}
    <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="createNewContract" action="{{ route('admin.cancelcreateNewContract') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Policy Number:</label>
                            <div class="col-9">
                                <input  class="form-control" name="policyNumber"  value="{{ $policy->policyNumber }}" title="Please provide policy number" disabled placeholder="Please provide policy number">
                                <input type="hidden" name="policyID" value="{{ $policy->id }}"/>
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Customer Name :</label>
                            <div class="col-9">
                                <input  class="form-control" name="customerName"  title="Please provide policy number" value="{{ $customer->firstName.' '.$customer->lastName }}" placeholder="Please provide policy number" disabled>
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">ID Type and Number:</label>
                            <div class="col-9">
                                <input  class="form-control" name="customerName"  title="Please provide policy number" value="{{ $id }}" placeholder="Please provide policy number" disabled>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">EMail :</label>
                            <div class="col-9">
                                <input  class="form-control" name="email"  title="Please provide email"  value="{{ $customer->email }}" placeholder="Please provide email" disabled>
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Cellphone :</label>
                            <div class="col-9">
                                <input  class="form-control" name="cellphone"  value="{{ $customer->cellphone }}" title="Please provide cellphone number" value="" placeholder="Please provide cellphone number">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Payment Frequency :</label>
                            <div class="col-9">
                                <select class="form-control" name="frequency" id="frequency">
                                    <option value="" disabled selected>Select billing frequency</option>
                                    <option value="1" >Monthly Instalments</option>
                                    <option value="2" >Three Instalments in a year</option>
                                    <option value="3" >Annual Instalment</option>
                                </select>
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row firstCollectionDate">
                            <label  class="col-3 col-form-label">First Collection Date :</label>
                            <div class="col-9">
                                <input type="text" required class="form-control kt_datepicker_1 dob" name="first_collection_date" id="first_collection_date" autocomplete="off"  placeholder="Select date" />
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row" id="first_premium_row">
                            <label  class="col-3 col-form-label">First Instalment Amount :</label>
                            <div class="col-9">
                                <input  class="form-control" name="first_premium" id="first_premium" title="Please provide pro rata premium" placeholder="Please provide pro rata premium">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Billing Date :</label>
                            <div class="col-9">

                                <input type="text" required class="form-control kt_datepicker_1 dob" name="billingDay" id="dob" autocomplete="off"  placeholder="Select date" />

                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Premium :</label>
                            <div class="col-9">
                                <input  class="form-control" name="premium" id="premium" title="Please provide premium" placeholder="Please provide premium">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>

{{--                        <div class="form-group row">--}}
{{--                            <label  class="col-3 col-form-label">Billing Day :</label>--}}
{{--                            <div class="col-9">--}}
{{--                                <select  class="form-control" name="billing_day" id="billing_day" title="Please provide cellphone number"></select>--}}
{{--                                <span class="form-text text-muted"></span>--}}
{{--                            </div>--}}
{{--                        </div>--}}

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Bank:</label>
                            <div class="col-9">
                                <select class="form-control" name="BankCode" id="banks">
                                    <option value="" disabled selected>Select Bank</option>
                                    @foreach($banks as $b)
                                        <option value="{{ $b->bank_number }}">{{ $b->bank_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Branch:</label>
                            <div class="col-9">
                                <select class="form-control" name="BranchCode" id="branches"></select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Account Number:</label>
                            <div class="col-9">
                                <input  class="form-control" name="accountNumber" title="Please provide bank account number" placeholder="Please provide bank account number">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Account Type:</label>
                            <div class="col-9">
                                <select class="form-control" name="accountType" id="accountType">
                                    <option value="">Please select account type</option>
                                    <option value="1">Cheque</option>
                                    <option value="2">Savings</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Submit</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.accounts.index') }}" >Cancel</a>
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
        {{--  @endif --}}
    </div>
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

<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.min.js') }}" type="text/javascript"></script>

<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script>
      "use strict";
    // Class definition
    var KTBootstrapDatepicker = function ()
    {
        var arrows;
        if (KTUtil.isRTL())
        {
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>'
            }
        }
        else
        {
            arrows = {
                leftArrow: '<i class="la la-angle-left"></i>',
                rightArrow: '<i class="la la-angle-right"></i>'
            }
        }
        // Private functions
        var demos = function ()
        {
            // minimum setup
            $('.kt_datepicker_1').datepicker({
                rtl: KTUtil.isRTL(),
                todayHighlight: true,
                orientation: "bottom left",
                templates: arrows,
                format: 'yyyy-mm-dd',
                startDate: "today",
            });
        }
        return {
            // public functions
            init: function() {
                demos();
            }
        };
    }();

    jQuery(document).ready(function()
    {
        KTFormControls.init();
        KTBootstrapDatepicker.init();

    });
</script>
<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#createNewContract" ).validate({
// define validation rules
                rules: {
                    policy_id: {
                        required: true
                    },
                    frequency:{
                        required:true
                    },
                    billingDate:{
                        required:true
                    },
                    accountType: {
                        required: true
                    },
                    accountNumber:{
                        required:true
                    },
                    BranchCode:{
                        required:true
                    },
                    BankCode:{
                        required:true
                    },
                    billing_day:{
                        required:true
                    },
                },

//display error alert on form submit
                invalidHandler: function(event, validator) {

                    $('#submit').hide();
                    KTUtil.scrollTo("accountCreate", -200);
                },

                submitHandler: function (form) {
                    $('#submitbtn').hide();
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
</script>
<script>
    jQuery(document).ready(function() {
        KTFormControls.init();
        var now = new Date();
        var days = new Date(now.getFullYear(), now.getMonth()+1, 0).getDate();

        $('#billing_day').append('<option value="">Please select billing day</option>');

        $('#branches').append('<option value="" selected disabled>Please select bank branch</option>');

        for(var i = 1; i <= days; i++){
            $('#billing_day').append('<option value="'+ i +'">'+ i +'</option>');
        }
    });

    $('#banks').on('change',function(){
        var bank = $(this).val();
        ajaxRequest = setTimeout(function(sn) {
            $.ajax({
                url: "{{ route('admin.policy.rpGetBranches') }}",
                data: {
                    "_token": "{{ csrf_token() }}",
                    "bank_id": bank
                },
                type: 'post',
                datatype: 'json',
                success: function(data) {
                    if (data) {
                        $('#branches').empty();
                        $.each( data.branches, function( index, value ){
                            var option = $('<option value="' + this.branch_id + '" selected>' + this.name + '</option>');
                            $("#branches").append(option);
                        });



                    } else {
                        $('#RPBankBranch').empty();
                    }
                }
            });
        }, 100);
    });

    var KTBootstrapDatepicker = function() {
        var arrows;
        if (KTUtil.isRTL()) {
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>'
            }
        } else {
            arrows = {
                leftArrow: '<i class="la la-angle-left"></i>',
                rightArrow: '<i class="la la-angle-right"></i>'
            }
        }
        // Private functions
        var demos = function() {
            // minimum setup
            $('.kt_datepicker_1').datepicker({
                rtl: KTUtil.isRTL(),
                todayHighlight: true,
                orientation: "bottom left",
                templates: arrows,
                format: 'yyyy-mm-dd'
            });
        }
        return {
            // public functions
            init: function() {
                demos();
            }
        };
    }();
    jQuery(document).ready(function() {
        KTBootstrapDatepicker.init();
    });

    $('.kt_datepicker_1').datepicker({
        rtl: KTUtil.isRTL(),
        todayHighlight: true,
        orientation: "bottom left",
        templates: arrows,
        format: 'yyyy-mm-dd'
    });
    KTBootstrapDatepicker.init();

</script>

</body>
<!-- end::Body -->
</html>
