<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
 <link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.min.css') }}" rel="stylesheet" type="text/css" />
 
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
     @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Generic SMS
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{Route('admin.policy.index')}}" class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Generic SMS</span> </a>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                   <!-- <a href="{{ URL::to('admin/policy/create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a> -->
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body"> 
                    <!--Begin -->   
                    <form id="smsForm" action="{{ route('sendGenericSMS') }}" method="POST" enctype="multipart/form-data" class="kt-form">  
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        
                        <div class="kt-portlet__body"> 
                            <div class="form-group row"> 
                                <label for="example-text-input" class="col-3 col-form-label">Recepients</label>
                                <div class="col-sm-3 kt-checkbox-inline"> 
                                    <label class="kt-checkbox kt-checkbox--brand" for="users">Send to Users 
                                        <input type="radio" class="input-group kt-group-checkable" value="0" id="users" name="recepient_group"> 
                                        <span></span> 
                                    </label> 
                                </div> 

                                <div class="col-sm-3 kt-checkbox-inline"> 
                                    <label class="kt-checkbox kt-checkbox--brand" for="customers">Send to Customers 
                                        <input type="radio" class="input-group kt-group-checkable-lg" value="1" id="customers" name="recepient_group"> 
                                        <span></span> 
                                    </label> 
                                </div>
                            </div>  
                            
                            <div class="form-group row">               
                                    <label for="example-text-input" class="col-2 col-form-label">Sms Text</label> 
                                    <div class="col-4">
                                        <textarea type="text" class="form-control" name="sms_text" required></textarea>  
                                        @if ($errors->has('sms_text'))
                                            <span class="col-12 text text-danger">
                                                {{ $errors->first('sms_text') }}
                                            </span>
                                        @endif
                                    </div>
                            </div>
                        
                            <div class="form-group row">  
                                <span class="col-2"></span>
                                <div class="col-10">
                                    <button type="submit" value="Submit" class="btn btn-brand">Send Sms</button> 
                                </div>
                            
                            </div> 
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- end:: Content -->
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
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {  
        var demo1 = function () {
            $( "#smsForm" ).validate({
            // define validation rules  
            rules: {
                    sms_text: {
                        required: true,
                    } 
                },
                  
            });  
        } 
        return {
        // public functions
            init: function() {
                demo1();
            }
        };
     }(); 
</script>

</body>
<!-- end::Body -->
</html>
