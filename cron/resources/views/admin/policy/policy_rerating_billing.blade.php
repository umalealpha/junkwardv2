<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

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
                    Add Contract For {{ $policy->policyNumber }}
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.accounts.index')}}" class="kt-subheader__breadcrumbs-link"> Realpay </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{Route('admin.accounts.index')}}" class="kt-subheader__breadcrumbs-link"> Add New Contract</a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                <form id="contractCreate" action="{{ route('admin.addReratingPaymentRealpay') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <input type="hidden" name="policy_id" value="{{ $policy->id }}" />
                    <input type="hidden" name="rate_id" id="rate_id" value="{{ $reratelog->ratings_id }}" />
                    <input type="hidden" id="monthly_ins" value="{{ $reratelog->month_ins }}" />
                    <input type="hidden" id="three_ins" value="{{ $reratelog->three_ins }}" />
                    <input type="hidden" id="annual_ins" value="{{ $reratelog->annual_ins }}" />
                    <input type="hidden" name="policyNumber" value="{{ $policy->policyNumber }}" />

                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Policy Number :</label>
                            <div class="col-9">
                                <label class="form-control">{{ $policy->policyNumber }}</label>
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Premium Rate Log ID :</label>
                            <div class="col-9">
                                <label class="form-control">{{ $reratelog->ratings_id }}</label>
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

                        <div class="form-group row" id="diff_day">
                            <label  class="col-3 col-form-label">Difference in days :</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker" title="Please select difference in days" data-live-search="true" id="days" name="days">
                                    <option value="">Please select difference in days</option>
                                    @for($i = 1;$i<=31;$i++) <option value="{{ $i }}">{{ $i }}</option>@endfor
                                </select>
                            </div>
                        </div>

                        <div class="form-group row" id="per_day_premium_calc">
                            <label  class="col-3 col-form-label"></label>
                            <div class="col-4">
                                <label class="col-form-label">Per Day Premium :<span id="per_day_premium"></span></label>
                            </div>
                            <div class="col-4">
                                <label class="col-form-label">Premium for selected days :<span id="overall_premium"></span></label>
                            </div>
                        </div>

                        <div class="form-group row firstCollectionDate">
                            <label  class="col-3 col-form-label">First Collection Date :</label>
                            <div class="col-9">
                                <input type="text" required class="form-control kt_datepicker_1 dob" name="first_collection_date" id="first_collection_date" autocomplete="off"  placeholder="Select date" />
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
                        <div class="form-group row" id="first_premium_row">
                            <label  class="col-3 col-form-label">First Instalment Amount :</label>
                            <div class="col-9">
                                <input  class="form-control" name="first_premium" id="first_premium" title="Please provide pro rata premium" placeholder="Please provide pro rata premium">
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
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Bank :</label>
                            <div class="col-9">
                                <select class="form-control" name="bank" id="banks">
                                    <option value="" disabled selected>Select Bank</option>
                                    @foreach($banks as $key=>$bank)
                                        <option value="{{ $bank->bank_number }}" @if($bankingDetails->bankName == $bank->bank_number) selected @endif>{{ $bank->bank_name }}</option>
                                    @endforeach
                                </select>
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label  class="col-3 col-form-label">Bank Branch :</label>
                            <div class="col-9">
                                <select class="form-control" name="branches" id="branches">
                                    <option value="" disabled selected>Select Branch</option>
                                    @foreach($branches as $key=>$branch)
                                        <option value="{{ $branch->branch_id }}" @if($bankingDetails->branchCode == $branch->branch_id) selected @endif>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Account Type:</label>
                            <div class="col-9">
                                <select class="form-control" name="AccountType">
                                    <option value="" disabled selected>Select Account Type</option>
                                    <option value="1" @if($bankingDetails->accountType == 1) selected @endif>Cheque</option>
                                    <option value="2" @if($bankingDetails->accountType == 2) selected @endif>Savings</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Account Number:</label>
                            <div class="col-9">
                                <input class="form-control" value="{{ $bankingDetails->accountNumber }}" name="AccountNumber"  placeholder="Please enter account number" title="Client number id required">
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

<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>

<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#contractCreate" ).validate({
                ignore:":not(:visible)",
                // define validation rules
                rules: {
                    AccountType: {
                        required: true
                    },
                    AccountNumber: {
                        required: true
                    },
                    branches:{
                        required:true
                    },
                    bank:{
                        required:true
                    },
                    premium:{
                        required:true
                    },
                    billingDay:{
                        required:true
                    },
                    frequency:{
                        required:true
                    },
                },

//display error alert on form submit
                invalidHandler: function(event, validator) {

                    $('#submit').hide();
                    KTUtil.scrollTo("contractCreate", -200);
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
        $('#diff_day').slideUp();
        $('#first_premium_row').slideUp();
        $('.firstCollectionDate').slideUp();
        $('#first_co').slideUp();
        $('#per_day_premium_calc').slideUp();

        KTFormControls.init();
        $('#banks').on('change',function(){
            var bank = $(this).val();
            var ajaxRequest = setTimeout(function(sn) {
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
                            $('#branches').empty();
                        }
                    }
                });
            }, 100);
        })

        $('#frequency').on('change',function(){
            var freq = $(this).val();
            $('#premium').val('');
            $('#first_premium').val('');

            if(freq == 1){
                $('#diff_day').slideDown();
                $('#first_premium_row').slideDown();
                $('.firstCollectionDate').slideDown();
            }else{
                $('#first_premium_row').slideUp();
                $('#diff_day').slideUp();
                $('#per_day_premium_calc').slideUp();
                $('.firstCollectionDate').slideUp();
            }

            var month = $('#monthly_ins').val();
            var three = $('#three_ins').val();
            var annual = $('#annual_ins').val();

            switch(freq){
                case '1':
                    $('#premium').val(month);
                    break;
                case '2':
                    $('#premium').val(three);
                    break;
                case '3':
                    $('#premium').val(annual);
                    break;
                    default:
                    $('#premium').val('');
                    break;
            }

            {{--switch (freq){--}}
            {{--    case 1:--}}
            {{--        //$('#premium').val($reratelog->month_ins);--}}
            {{--        $('#diff_day').slideDown();--}}
            {{--        $('#first_premium_row').slideDown();--}}
            {{--        break;--}}
            {{--    case 2:--}}
            {{--        //$('#premium').val('{{ $reratelog->three_ins }}');--}}
            {{--        $('#first_premium_row').slideUp();--}}
            {{--        $('#diff_day').slideUp();--}}
            {{--        break;--}}
            {{--    case 3:--}}
            {{--        //$('#premium').val('{{ $reratelog->annual_ins }}');--}}
            {{--        $('#first_premium_row').slideUp();--}}
            {{--        $('#diff_day').slideUp();--}}
            {{--        break;--}}
            {{--    default:--}}
            {{--        $('#first_premium_row').slideUp();--}}
            {{--        $('#diff_day').slideUp();--}}
            {{--        break;--}}
            {{--}--}}

        });

        $('#days').on('change',function(){
            var diff = $(this).val();
            var freq = $('#frequency').val();
            var rate_id = $('#rate_id').val();

            if(freq == 1){
            //Ajax call for the calculation of PER DAY PREMIUM
                var ajaxRequest = setTimeout(function(sn) {
                    $.ajax({
                        url: "{{ route('admin.policy.calculatePerDayPremiumRatingID') }}",
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "rate_id": rate_id,
                            "diff_days": diff,
                        },
                        type: 'post',
                        datatype: 'json',
                        success: function(data) {
                            if (data) {
                                $('#per_day_premium_calc').slideDown();
                                $('#per_day_premium').text(' '+data.perDay);
                                $('#overall_premium').text(' '+data.overall);
                            } else {

                            }
                        }
                    });
                }, 100);

            }else{
                $('#first_premium_row').slideUp();
                $('#diff_day').slideUp();
            }
        });

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