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
                    Claim
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Claim </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit</span>
                </div>
            </div>
            @if($claims && $claims->status == 'Pending')
                <a href="{!! route('admin.claims.approved',[$claims->id]) !!}" class="btn btn-sm btn-success btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="right" data-original-title="Approved"> <span class="kt-opacity-11" id="">Claim Approved</span>&nbsp; </a>
                <a href="{!! route('admin.claims.rejects',[$claims->id]) !!}" class="btn btn-sm btn-danger btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Reject"> <span class="kt-opacity-11" id="">Claim Reject</span>&nbsp; </a>
            @endif
            @if($claims && $claims->status == 'Approved')

                <a href="{!! route('admin.claims.rejects',[$claims->id]) !!}" class="btn btn-sm btn-danger btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Reject"> <span class="kt-opacity-11" id="">Claim Reject</span>&nbsp; </a>
            @endif
            @if($claims && $claims->status == 'Rejects')
                <a href="{!! route('admin.claims.approved',[$claims->id]) !!}" class="btn btn-sm btn-success btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="right" data-original-title="Approved"> <span class="kt-opacity-11" id="">Claim Approved</span>&nbsp; </a>

            @endif
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet kt-portlet--tabs">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-toolbar">
                        <ul class="nav nav-tabs nav-tabs-line nav-tabs-line-brand nav-tabs-line-2x nav-tabs-line-right nav-tabs-bold" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#kt_portlet_base_demo_3_2_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Claim Details </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="tab-content">

                <div class="tab-pane active" id="kt_portlet_base_demo_3_2_tab_content" role="tabpanel">

                    <!--begin::Portlet-->
                    <div class="kt-portlet kt-portlet--height-fluid">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Claims Information
                                </h3>
                            </div>

                        </div>
                        <div class="kt-portlet__body">
                            <div class="kt-widget-4">
                                <form action="{!! action('Admin\ClaimsController@updateQuote', ['id' => $quotes->id]) !!}" method="POST" enctype="multipart/form-data">
                                    {{csrf_field()}}
                                    <div class="row">
                                            <input id="policy_id" class="form-control" type="hidden" name="policy_id" value="{!! $policy->id !!}">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label>Date of Damage</label>
                                                    <input id="incidentDate" class="form-control kt_datepicker_1" type="text" name="incidentDate" @if($claims) value="{!! \Carbon\Carbon::parse($claims->incidentDate)->format('d/m/Y') !!}" @endif @if(!$claims) required @endif readonly>
                                                </div>
                                            </div>
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label>Damage Extent</label>
                                                    <div class="kt-radio-inline">
                                                        <label class="kt-radio">

                                                                <input type="radio" name="extent" value="Cracked" @if($claims->extent == 'Cracked') checked @endif readonly>

                                                            Cracked <span></span>
                                                        </label>
                                                        <label class="kt-radio">

                                                                <input type="radio" name="extent" value="Shattered" @if($claims->extent == 'Shattered') checked @endif readonly>



                                                            Shattered <span></span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label>Cause of Damage</label>
                                                    <div class="form-group form-group-last">
                                                        <textarea class="form-control" name="cause" id="cause" rows="3" required readonly>@if($claims){!! $claims->cause !!}@endif</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>




                                        <!--begin::Portlet-->

                                        <div class="kt-portlet">
                                            <div class="kt-invoice-v2">
                                                <div class="kt-invoice-v2__header grid">
                                                    <div class="kt-invoice-v2__header-right">
                                                        <div class="kt-invoice-v2__logo thumb">
                                                            <h3>Before</h3>
                                                            <div class="row">
                                                                <div class="col-lg-3">
                                                                    <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicleDetails->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicleDetails->front)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                                    </a>
                                                                </div>
                                                                <div class="col-lg-3">
                                                                    <a class="thumbnail" href="#" id="backViewModal" data-image-id="backView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicleDetails->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicleDetails->back)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                                    </a>
                                                                </div>
                                                                <div class="col-lg-3">
                                                                    <a class="thumbnail" href="#" id="rightViewModal" data-image-id="rightView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicleDetails->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicleDetails->right)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                                    </a>
                                                                </div>
                                                                <div class="col-lg-3">
                                                                    <a class="thumbnail" href="#" id="leftViewModal" data-image-id="leftView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicleDetails->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicleDetails->left)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="kt-invoice-v2__header-left">
                                                        <div class="kt-invoice-v2__logo thumb">
                                                            <h3>After</h3>

                                                            @if($claimVehicle)
                                                                <div class="row">
                                                                    <div class="col-lg-3">
                                                                        <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicleDetails->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claimVehicle->front_image)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">

                                                                        </a>
                                                                    </div>
                                                                    <div class="col-lg-3">
                                                                        <a class="thumbnail" href="#" id="backViewModal" data-image-id="backView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicleDetails->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claimVehicle->back_image)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                                        </a>
                                                                    </div>
                                                                    <div class="col-lg-3">
                                                                        <a class="thumbnail" href="#" id="rightViewModal" data-image-id="rightView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicleDetails->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claimVehicle->right_image)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                                        </a>
                                                                    </div>
                                                                    <div class="col-lg-3">
                                                                        <a class="thumbnail" href="#" id="leftViewModal" data-image-id="leftView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicleDetails->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claimVehicle->left_image)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            @endif


                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!--end::Portlet-->



                                        <div class="row">
                                            <div class="col-lg-3">
                                                <div class="form-group">
                                                    <label>Quote Amount</label>
                                                    <input id="quote_amount" class="form-control" name="quote_amount" />
                                                </div>
                                            </div>
                                            {{--<div class="col-lg-3">
                                                <span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--metal" style="margin:35px">
                                                                                <label>
                                                                                    <div id="uploadFrontButton" class="file btn btn-primary">

                                                                                        <i class="la la-upload"></i>Upload Incident Photo Right

                                                                                        <input id="quote_image" type="file" name="quote_image"  accept=".pdf,.doc"  required/>
                                                                                    </div>
                                                                                </label>
                                                                            </span>
                                            </div>--}}
                                        </div>


                                    {!! Form::close() !!}
                            </div>
                        </div>
                    </div>
                    <!--end::Portlet-->


                </div>

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
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script>
    "use strict";
    // Class definition

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
                templates: arrows
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
</script>
</body>
<!-- end::Body -->
</html>