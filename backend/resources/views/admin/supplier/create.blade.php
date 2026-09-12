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
     <!-- check if is first time login -->
    
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Create New Supplier
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{ URL::to('admin/supplier')  }}" class="kt-subheader__breadcrumbs-link"> Supplier </a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                <form id="supplierForm" action="{{ route('admin.supplier.store') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Supplier Name:</label>
                            <div class="col-9">
                                <input class="form-control" name="sname"  placeholder="Enter Supplier Name" title="Supplier Name is required">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Supplier Type:</label>
                            <div class="col-9">
                                <select name="stype"  class="form-control kt_selectpicker" title="Select supplier type" data-live-search="true">

                                    @foreach($supplierTypes as $supplierType)
                                        <option value="{{ $supplierType->value }}">{{ $supplierType->value }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div><p></p>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Account Number :</label>
                            <div class="col-9">
                                <input  class="form-control" name="account_no"  placeholder="Enter Account Number" title="Supplier Account Number is required">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">VAT No. :</label>
                            <div class="col-9">
                                <input  class="form-control" name="vat"  placeholder="Enter VAT Number" title="Supplier VAT No. is required">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Contact Number:</label>
                            <div class="col-9">
                                <input class="form-control" name="snumber"  placeholder="Enter Contact Number" title="Supplier contact number is required">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Email:</label>
                            <div class="col-9">
                                <input class="form-control" name="semail"  placeholder="Enter Supplier Email" title="Supplier Email address is required">
                            </div>
                        </div>


                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Location:</label>
                            <div class="col-9">
                                <input  class="form-control" name="slocation"  placeholder="Enter Supplier Location" title="Supplier location is required">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Address:</label>
                            <div class="col-9">
                                <input class="form-control" name="address"  placeholder="Enter Supplier Address" title="Supplier Address is required">
                            </div>
                        </div>
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" value="Submit" id="btn" class="btn btn-brand">Submit</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>

                                    <a class="btn btn-secondary" href="{{ route('admin.supplier.index') }}" >Cancel</a>
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
            $( "#supplierForm" ).validate({
                // define validation rules
                rules: {
                    sname: {
                        required: true
                    },
                    stype: {
                        required: true
                    },
                    vat: {
                        required: true
                    },

                    snumber: {
                        required: true
                    },
                    semail: {
                        required: true,
                        email:true
                    },
                    slocation: {
                        required: true
                    },
                    account_no:{
                        required:true
                    },
                    address:{
                      required:true
                    },
                },

                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    $('#btn').show();
                    KTUtil.scrollTo("supplierForm", -200);
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
        KTFormControls.init();
    });
</script>
</body>
<!-- end::Body -->
</html>