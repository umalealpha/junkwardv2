<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet"
    type="text/css" />
<link href="{{ asset('assets/vendors/general/tether/dist/css/tether.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}"
    rel="stylesheet" type="text/css" />

<!-- begin::Body -->

<body
    class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
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
                    Agent Pin Update
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}"
                            class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span
                            class="kt-subheader__breadcrumbs-separator"></span> <a href="{{  URL::to('admin/user') }}"
                            class="kt-subheader__breadcrumbs-link"> Agent </a> <span
                            class="kt-subheader__breadcrumbs-separator"></span>
                        <span
                            class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Pin Update</span>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <!--begin::Portlet-->
                <div class="kt-portlet">
                    <!--begin::Form-->
                    <form id="userpin" action="{{ route('admin.user.userPinUpdate') }}" method="POST"
                        enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <input type="hidden" name="id" value="{{ $user->id }}" />
                        <div class="kt-portlet__body">

                      
                        <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Agent ID:</label>
                                <div class="col-5">{{ $user->id}}</div>
                              

                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Agent Name:</label>
                                <div class="col-5">{{ $user->firstName }} {{ $user->lastName }}</div>
                              

                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Agent Email:</label>
                                <div class="col-5">{{ $user->email }}</div>
                              

                            </div>
                         

                          

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Agent Pin:</label>
                                <div class="col-5">
                                    <input type="text" class="form-control" name="pin" id="pin" value="{{ $user->pin }}"
                                        placeholder="Enter pin" autocomplete="off">
                                   
                                    
                                </div>
                              

                            </div>

                        </div>


                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9">
                                    <button type="submit" value="Submit" id="btn" class="btn btn-brand">Update</button>
                                       
                                        <a class="btn btn-secondary" href="{{ route('admin.user.userPinIndex') }}">Cancel</a>
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

    <!-- end:: Root -->


    <!-- begin:: Scrolltop -->
    <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
    <!-- end:: Scrolltop -->

    @include('admin.layouts.scripts')
  
   
    <script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}"
        type="text/javascript"></script>
    <script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}"
        type="text/javascript"></script>


  

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>

<script>
        "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demopin = function () {
            $( "#userpin" ).validate({
// define validation rules
                rules: {
                    pin: {
                        required: true,
                        number:true,
                        minlength: 4,
                        maxlength: 4,
                    }
                   },
                messages:{
                    pin:{
                        required: "Enter Pin",
                        number:"Pin should be number",
                        minlength:"Pin should at least be 4 characters long",
                        maxlength:"pin should not be more than 4 characters long",
                    }
                   
                },

                submitHandler: function (form) {
                    /* $('#btn').hide();
                     $('#loadBtn').show();*/
                    form[0].submit(); // submit the form
                }
            });
        }

        return {
// public functions
            init: function() {
                demopin();
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
