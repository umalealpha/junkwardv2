<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('/assets/vendors/general/perfect-scrollbar/css/perfect-scrollbar.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('/assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
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
                    Edit Reinsurance Treaty
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.reinsuranceType.index')}}" class="kt-subheader__breadcrumbs-link"> Reinsurance Type </a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                <form id="reinsuranceTreatyEdit" action="{{ route('admin.reinsuranceTreaty.update', $reinsurancetreaty->id) }}"
                      method="POST"  class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                                <div class="form-group row" >
                                <label for="example-text-input" class="col-3 col-form-label">Treaty Name</label>
                                <div class="col-9">
                                    <input  class="form-control" name="treatyname" value="{!!ucwords($reinsurancetreaty->treaty_name) !!}" placeholder="Enter Treaty Name" title="Treaty Name is required">
                                </div>
                                </div>
                                <div class="form-group row">
                                    <label for="example-text-input" class="col-3 col-form-label">Treaty number</label>
                                    <div class="col-9">
                                        <input  class="form-control" name="treatynumber" style="margin-left: 2px;" value="{!! $reinsurancetreaty->treaty_number !!}"  placeholder="Enter Treaty number" title="Treaty number is required">
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label for="example-text-input" class="col-3 col-form-label">Provisional Commission (%)</label>
                                    <div class="col-9">
                                        <input  class="form-control" name="provisional_commission" value="{!! $reinsurancetreaty->provisional_commission ?? 0 !!}" placeholder="Enter Provisional Commission" title="Provisional Commission">
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label for="example-text-input" class="col-3 col-form-label">Proportional Share (%)</label>
                                    <div class="col-9">
                                        <input  class="form-control" name="proportional_share" value="{!! $reinsurancetreaty->proportional_share ?? 0 !!}" placeholder="Enter Proportional Share" title="Proportional Share">
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label for="example-text-input" class="col-3 col-form-label">Cash Loss Advise</label>
                                    <div class="col-9">
                                        <input  class="form-control" name="cash_loss_advise" value="{!! $reinsurancetreaty->cash_loss_advise ?? 0 !!}" placeholder="Enter Cash Loss Advise" title="Cash Loss Advise">
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label for="example-text-input" class="col-3 col-form-label">Event Limit</label>
                                    <div class="col-9">
                                        <input  class="form-control" name="event_limit" value="{!! $reinsurancetreaty->event_limit ?? 0 !!}" placeholder="Enter Event Limit" title="Event Limit">
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label for="example-text-input" class="col-3 col-form-label">Exclusions</label>
                                    <div class="col-9">
                                        <textarea class="form-control" name="exclusions" placeholder="Enter Exclusions" title="Exclusions">{!! $reinsurancetreaty->exclusions ?? '' !!}</textarea>
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label for="example-text-input" class="col-3 col-form-label">Effective From</label>
                                    <div class="col-4">
                                        <input type="text" class="form-control kt_datepicker_1" value="{!! $effective_from !!}" name="effectivefrom" autocomplete="off" placeholder="Select date"/>                            </div>
                                </div>
                                <div class="form-group row">
                                    <label for="example-text-input" class="col-3 col-form-label">Effective to</label>
                                    <div class="col-4">
                                        <input type="text" class="form-control kt_datepicker_1" value="{!! $effective_to !!}" name="effectiveto" autocomplete="off" placeholder="Select date"/>                            </div>
                                </div>

                                <div class="form-group row">
                                    <label class="col-form-label col-lg-3 col-sm-12">Formula Attached</label>
                                    <div class="col-9">
                                        <select class="form-control kt_selectpicker" name="formula[]" multiple title="Select Formula Attached" data-live-search="true">
                                                <option value="">Select Formulas</option>
                                                @php
                                                    $attached_ids = array_filter(array_map('intval', explode(',', $reinsurancetreaty->formula_attached ?? '')));
                                                @endphp
                                                @foreach($reinsuranceformula as $reinsuranceformula)
                                                    <option value="{{ $reinsuranceformula->id }}"
                                                        @if(in_array($reinsuranceformula->id, $attached_ids)) selected @endif>
                                                        {{ $reinsuranceformula->formula_name }}
                                                    </option>
                                                @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group row" style="margin-top: 1%">
                                    <label for="example-text-input" class="col-3 col-form-label">Status</label>
                                    <div class="col-9">
                                        <span class="kt-switch" >
                                            <label>
                                                <input id="switchValue" type="checkbox" @if($reinsurancetreaty->status) checked="checked" @endif name="status" value="1" onchange="statusMsg()">
                                                <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                @if($reinsurancetreaty->status)
                                                    <p id="switchMsg" style="display:inline;float:left;margin-top: 15px;margin-left: 5px;color:cornflowerblue">Active</p>
                                                @else
                                                    <p id="switchMsg" style="display:inline;float:left;margin-top: 15px;margin-left: 5px;color:#ff4d4d">Inactive</p>
                                                @endif
                                            </label>
                                        </span>
                                    </div>
                                </div>

                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    @can('reinsurance-treaty-edit')
                                    <button type="submit" value="Submit" id="submit" class="btn btn-brand">Update</button>
                                    @endcan
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.reinsuranceTreaty.index') }}" >Cancel</a>
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
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>

<script>
    var KTBootstrapDatepicker = function () {
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
        var demos = function () {
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

    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#reinsuranceTreatyEdit" ).validate({
// define validation rules
                rules: {
                    treatyname: {
                        required: true
                    },
                    treatynumber:{
                        required:true
                    },
                    effectivefrom:{
                        required:true
                    },
                    effectiveto:{
                        required:true
                    },


                },

//display error alert on form submit
                invalidHandler: function(event, validator) {
                    $('#submit').show();
                    KTUtil.scrollTo("regionEdit", -200);
                },

                submitHandler: function (form) {
                    $('#submit').hide();
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




</body>
<!-- end::Body -->
</html>
