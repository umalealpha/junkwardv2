<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

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
    {{--  @if (Auth::user()->default_password == "111111")
         @include('includes.reset')
     @else --}}
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                   Update Realpay Client
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> RealPay </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{route('admin.branch.index')}}" class="kt-subheader__breadcrumbs-link"> Edit Client </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="clientUpdate" action="{{ route('admin.updateClient') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <input type="hidden" name="ClientNumber2" value="{{ $clientInfo['ClientNumber'] }}">
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Client Number:</label>
                            <div class="col-9">
                                <input class="form-control" disabled value="{{ $clientInfo['ClientNumber'] }}"  placeholder="Please provide client number" title="Client number id required">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Client Name:</label>
                            <div class="col-9">
                                <input class="form-control" name="ClientName" value="{{ $clientInfo['ClientName'] }}"  placeholder="Please enter client name" title="Client number id required">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">ID Type:</label>
                            <div class="col-9">
                            <select class="form-control" name="IDType">
                                <option value="" disabled selected>Select ID Type</option>
                                <option value="I">Omang</option>
                                <option value="P">Passport</option>
                            </select>
                            </div>
                        </div>


                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">ID Number:</label>
                            <div class="col-9">
                                <input class="form-control" name="IDNumber" value="{{ $clientInfo['IDNumber'] }}"  placeholder="Client ID number required" title="Client ID number required">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Cellphone Number:</label>
                            <div class="col-9">
                                <input class="form-control" name="CellphoneNumber" value="{{ $clientInfo['CellphoneNumber'] }}"  placeholder="Client number id required" title="Client number id required">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">EMail:</label>
                            <div class="col-9">
                                <input class="form-control" name="EMail" value="{{ $clientInfo['EMail'] }}"  placeholder="Client number id required" title="Client number id required">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Bank:</label>
                            <div class="col-9">
                                <select class="form-control" name="BankCode" id="banks">
                                    <option value="" disabled selected>Select Bank</option>
                                    @foreach($banks as $b)
                                        <option @if($clientInfo['BankCode'] == $b['BankCode']) selected  @endif value="{{ $b['BankCode'] }}">{{ $b['BankName'] }}</option>
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
                            <label for="example-text-input" class="col-3 col-form-label">Account Type:</label>
                            <div class="col-9">
                                <select class="form-control" name="AccountType">
                                    <option value="" disabled selected>Select Account Type</option>
                                    <option @if($clientInfo['AccountType'] == 1) selected @endif value="1">Cheque</option>
                                    <option @if($clientInfo['AccountType'] == 2) selected @endif value="2">Savings</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Account Number:</label>
                            <div class="col-9">
                                <input class="form-control" name="AccountNumber" value="{{ $clientInfo['AccountNumber'] }}"  placeholder="Client number id required" title="Client number id required">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Account Holder Name:</label>
                            <div class="col-9">
                                <input class="form-control" name="AccountHolderName" value="{{ $clientInfo['AccountHolderName'] }}"  placeholder="Client number id required" title="Client number id required">
                            </div>
                        </div>

                        <input type="hidden" name="employeeType" value="{{ $clientInfo['EmployeeGroupCode'] }}">


                        <p></p>

                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9">
                                        <button type="submit" value="Submit" id="btn" class="btn btn-brand">Submit</button>
                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                        <a class="btn btn-secondary" href="{{ route('admin.branch.index') }}" >Cancel</a>
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
{{--   @endif --}}
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

<script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
        // Private functions

        var demo1 = function () {
            $( "#clientUpdate" ).validate({
                // define validation rules
                rules: {
                    name: {
                        required: true
                    },

                },

                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    KTUtil.scrollTo("clientUpdate", -200);
                    $('#btn').show();
                },

                submitHandler: function (form) {
                    $('#btn').hide();
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

        var option = $('<option value="" selected>Please Select Bank</option>');
        $("#branches").append(option);

        KTFormControls.init();
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
    })

</script>
</body>
<!-- end::Body -->
</html>