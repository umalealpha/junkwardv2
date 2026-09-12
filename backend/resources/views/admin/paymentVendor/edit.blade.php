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
    
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Edit Payment Vendor
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{ URL::to('admin/paymentVendor') }}" class="kt-subheader__breadcrumbs-link">Payment Vendor </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="productCreate" action="{{ route('admin.paymentVendors.update',$paymentVendor->id) }}"
                      method="post" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    @csrf
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Payment Vendor Name</label>
                            <div class="col-9">
                                <input type="text" class="form-control" value="{{ $paymentVendor->vendorName }}" title="Payment Vendor name is required" maxlength="25" name="vendorName" value="{!! old('vendorName') !!}" placeholder="Enter Payment Vendor name">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Payment Vendor Label</label>
                            <div class="col-9">
                                <input type="text" class="form-control" value="{{ $paymentVendor->vendorLabel }}" title="Payment Vendor name is required" maxlength="25" name="vendorLabel" value="{!! old('vendorLabel') !!}" placeholder="Enter Payment Vendor name">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Payment Vendor Email</label>
                            <div class="col-9">
                                <input type="email" class="form-control" value="{{ $paymentVendor->email }}" title="Payment Vendor email is required" name="vendorEmail" value="{!! old('vendorEmail') !!}" placeholder="Enter Payment Vendor email">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Payment Vendor Telephone</label>
                            <div class="col-9">
                                <input type="text" class="form-control" value="{{ $paymentVendor->telephone }}" title="Payment Vendor Telephone is required" maxlength="20" name="vendorTelephone" value="{!! old('vendorTelephone') !!}" placeholder="Enter Payment Vendor telephone">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Off Time Start</label>
                            <div class="col-9">
                                <input type="time" class="form-control" title="off time start"  name="offtimestart" value="{{ $paymentVendor->offtimestart }}" >
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Off Time End</label>
                            <div class="col-9">
                                <input type="time" class="form-control" title="off time end"  name="offtimeend" value="{{ $paymentVendor->offtimeend }}" >
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>
                   
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Payment Vendor Status</label>

                            <div class="col-9">
                                <select class="form-control kt_selectpicker" title="Status"
                                        id="region"  name="status" required>  
                                        @if($paymentVendor->status == 1)  
                                            <option value="{{ $paymentVendor->status }}" selected> Activated</option>
                                            <option value="0">Deactivated</option>  
                                        @elseif($paymentVendor->status == 0) 
                                            <option value="{{ $paymentVendor->status }}" selected>Deactivated</option>   
                                            <option value="1">Activated</option>  
                                        @endif
                                        
                                 
                                </select>
                            </div>
                        </div>
                  
                   
                
                
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    @can('payment-vendor-edit')
                                    <button type="submit" value="Submit" class="btn btn-brand">Submit</button>
                                    @endcan
                                    <a class="btn btn-secondary" href="{{ route('admin.product.index') }}" >Cancel</a>
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
<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>

<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#productCreate" ).validate({
// define validation rules
                rules: {
                    vendorName: {
                        required: true
                    },
                    vendorEmail: {
                        required: true
                    },
                    vendorTelephone: {
                        required: true
                    },

                },

//display error alert on form submit
                invalidHandler: function(event, validator) {
                    KTUtil.scrollTo("productCreate", -200);
                },

                submitHandler: function (form) {
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
    function memberMsg(){
        var isChecked=document.getElementById("memberValue").checked;
        if (isChecked){
            document.getElementById("msg1").innerHTML="Yes";
            document.getElementById("msg1").style.color="cornflowerblue";

        }
        else {
            document.getElementById("msg1").innerHTML="No";
            document.getElementById("msg1").style.color="#ff4d4d";
        }

    }
</script>
{{-- Member Checkbox ends--}}




</body>
<!-- end::Body -->
</html>