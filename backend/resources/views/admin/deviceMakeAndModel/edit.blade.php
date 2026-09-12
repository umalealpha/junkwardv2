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
    @if (Auth::user()->default_password == "111111")
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Edit Device Make And Model
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('device_make_model')}}" class="kt-subheader__breadcrumbs-link"> Device Make And Model </a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                    <form id="ruleEdit" action="{{ route('device_make_model_update',$deviceMakeModelEdit->id) }}"
                          method="POST" enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_method" value="PUT">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <div class="kt-portlet__body">
                            <div class="form-group row">
                            </div>
                            <div class="form-group row">
                                <label  class="col-3 col-form-label">Device Make:</label>
                                <div class="col-9">
                                    <select class="form-control kt_selectpicker"  data-live-search="true" name="make_id" >
                                        @foreach($deviceMakeModel as $makeModel)
                                            @if($makeModel->make_id == null || ($deviceMakeModelEdit->id == $makeModel->id))
                                                <option value="{{$makeModel->id}}" @if($deviceMakeModelEdit->make_id == $makeModel->id) selected
                                                        @endif >{{$makeModel->name}}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label  class="col-3 col-form-label">Add Another Model:</label>
                                <div class="col-9">
                                    <input class="form-control kt_selectpicker" title="Please choose name" data-live-search="true" name="name" value="{{$deviceMakeModelEdit->name}}">
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label  class="col-3 col-form-label">Device Type:</label>
                                <div class="col-9">

                                    <select class="form-control kt_selectpicker entry_type" title="Please choose entry type"  id="device_type" name="device_type">
                                        <option value="Cellphone" @if($deviceMakeModelEdit->device_type == 'Cellphone' ) selected @endif>Cellphone</option>
                                        <option value="Mobile" @if($deviceMakeModelEdit->device_type == 'Mobile' ) selected @endif>Mobile</option>
                                        <option value="Laptop" @if($deviceMakeModelEdit->device_type == 'Laptop' ) selected @endif>Laptop</option>
                                        <option value="Tablet" @if($deviceMakeModelEdit->device_type == 'Tablet' ) selected @endif>Tablet</option>
                                    </select>
                                    <span class="form-text text-muted"></span>
                                </div>
                            </div>
                        </div>
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    @can('rule-edit')
                                        <div class="col-9">
                                            <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Update</button>
                                            <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                            <a class="btn btn-secondary" href="{{ route('device_make_model') }}" >Cancel</a>
                                        </div>
                                    @endcan
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
@endif
<!-- begin:: Footer -->
    <div class="kt-footer kt-grid__item kt-grid kt-grid--desktop kt-grid--ver-desktop">
        <div class="kt-footer__copyright"> 2018&nbsp;&copy;&nbsp;<a href="#" target="_blank" class="kt-link">Alpha Direct</a> </div>
    </div>
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

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#ruleEdit" ).validate({
// define validation rules
                rules: {
                    name:{
                        required:true
                    },
                    device_type:{
                        required:true
                    },
                },

//display error alert on form submit
                invalidHandler: function(event, validator) {

                    $('#submit').hide();
                    KTUtil.scrollTo("ruleEdit", -200);
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

</body>
<!-- end::Body -->
</html>
