<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
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
    <!--If Password default, show edit details -->
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        View Vehicle Preinspection Data
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Vehicle Preinspection</span> </a>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View Data</span> </a>
                    </div>
                </div>
                <div style="text-align: end;">
                @if(\AlphaDirect\Models\VehicleDelete::where('vehicle_id',$data->id)->exists())
                        <a href="{{Route('admin.customerVehicleDeleteRecordes',$data->id)}}" target="_blank">
                                <button style="padding-bottom: 0px; margin-bottom: 15px; padding-top: 4px; height: 42px;"
                                        type="button" style="margin-top: 0px; margin-left: 6px;" class="btn btn-warning">Check Delete Data</button>
                       </a>
               @endif
                            <a href="{{Route('admin.VehiclePreinspectionActivityLogRecordes',$data->id)}}" target="_blank">
                                <button style="padding-bottom: 0px; margin-bottom: 15px; padding-top: 4px; height: 42px;"
                                type="button" style="margin-top: 0px; margin-left: 6px;" id="activity_log" class="btn btn-primary">Check Activity Log</button>
                            </a>
                        </div>
            </div>
            <!-- end:: Subheader -->

            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <!--begin: Datatable -->
                        <!-- begin:: Content -->
                        <form id="updateCustomerKYC" action="{{ route('admin.customerInspection.update') }}"
                              method="POST" enctype="multipart/form-data" class="kt-form">
                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <input type="hidden" name="data_id" value="{{ $data->id }}" />
                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                                <thead>
                                <tr>
                                    <th>Policy Number:</th>
                                    <td>{{ strtoupper($data->policy_id != null ? $data->policy_id : 'n/a') }}</td>
                                </tr>
                                <tr>
                                    <th>Make:</th>
                                    <td>{{ strtoupper($data->make != null ? $data->make : 'n/a') }}</td>
                                </tr>
                                <tr>
                                    <th>Model:</th>
                                    <td>{{ strtoupper($data->model != null ? $data->model : 'n/a') }}</td>
                                </tr>
                                <tr>
                                    <th>Manufacturing Year:</th>
                                    <td>{{ strtoupper($data->year != null ? $data->year : 'n/a') }}</td>
                                </tr>
                                <tr>
                                    <th>Vehicle Registration Number:</th>
                                    <td>{{ strtoupper($data->vehiclePlate != null ? $data->vehiclePlate : 'n/a') }}</td>
                                </tr>

                                <tr>
                                    <th>Vehicle Front Image: </th>
                                    @if($data->front)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->front) }}" target= "_blank">
{{--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->front) }}" style="height:100px;width:100px;"  >--}}
                                                        @if(pathinfo($data->front,
                                                                               PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->front,
                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                        pathinfo($data->front, PATHINFO_EXTENSION)
                                                        == 'doc' || pathinfo($data->front,
                                                        PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->front,
                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                        pathinfo($data->front, PATHINFO_EXTENSION)
                                                        == 'xlsx' || pathinfo($data->front,
                                                        PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}"
                                                                 width="100%" height="auto">
                                                        @elseif(pathinfo($data->front,
                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                        pathinfo($data->front, PATHINFO_EXTENSION)
                                                        == 'jpg' || pathinfo($data->front,
                                                        PATHINFO_EXTENSION) == 'png')
                                                            <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->front) !!}"
                                                                 width="100%" height="auto">
                                                        @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                 height="auto">
                                                        @endif
                                                    </a>
                                                </div>

                                                <div class="col-4">
                                                    <h6>Update Image Status:
                                                        @if(isset($data->front_status))
                                                            @if($data->front_status==2)
                                                            <span style="color:blue;">Recheck</span>
                                                            @endif
                                                        @endif
                                                    </h6>
                                                    <label class="kt-radio">
                                                        <input type="radio" name="front_status" @if($data->front_status == '1') checked @endif class="premiumField condition"
                                                               value="1">Approve<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="front_status" @if($data->front_status == '0') checked @endif class="premiumField condition"
                                                               value="0">Unapprove<span></span>
                                                    </label>
                                                </div>

                                                <div class="col-4">
                                                    <textarea placeholder="Please provide vehile front image remark" class="form-control" name="front_image_remark">@if($data->front_image_remark) {{ $data->front_image_remark }} @endif</textarea>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" target= "_blank"> Omang Front Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Vehicle Back Image: </th>
                                    @if($data->back)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->back) }}" target= "_blank">
<!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->back) }}" style="height:100px;width:100px;"  >-->
    @if(pathinfo($data->back,
PATHINFO_EXTENSION) == 'pdf')
        <img src="{{asset('images/pdf.ico')}}" width="100%"
             height="auto">
    @elseif(pathinfo($data->back,
    PATHINFO_EXTENSION) == 'docx' ||
    pathinfo($data->back, PATHINFO_EXTENSION)
    == 'doc' || pathinfo($data->back,
    PATHINFO_EXTENSION) == 'docm')
        <img src="{{asset('images/word.ico')}}" width="100%"
             height="auto">
    @elseif(pathinfo($data->back,
    PATHINFO_EXTENSION) == 'xls' ||
    pathinfo($data->back, PATHINFO_EXTENSION)
    == 'xlsx' || pathinfo($data->back,
    PATHINFO_EXTENSION) == 'csv')
        <img src="{{asset('images/excel.png')}}"
             width="100%" height="auto">
    @elseif(pathinfo($data->back,
    PATHINFO_EXTENSION) == 'jpeg' ||
    pathinfo($data->back, PATHINFO_EXTENSION)
    == 'jpg' || pathinfo($data->back,
    PATHINFO_EXTENSION) == 'png')
        <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->back) !!}"
             width="100%" height="auto">
    @else
        <img src="{{asset('images/doc.png')}}" width="100%"
             height="auto">
    @endif

                                                    </a>
                                                </div>

                                                <div class="col-4">
                                                    <h6>Update Image Status: 
                                                        @if(isset($data->back_status))
                                                            @if($data->back_status==2)
                                                            <span style="color:blue;">Recheck</span>
                                                            @endif
                                                        @endif
                                                    </h6>
                                                    <label class="kt-radio">
                                                        <input type="radio" name="back_status" @if($data->back_status == '1') checked @endif class="premiumField condition"
                                                               value="1">Approve<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="back_status" @if($data->back_status == '0') checked @endif class="premiumField condition"
                                                               value="0">Unapprove<span></span>
                                                    </label>
                                                </div>

                                                <div class="col-4">
                                                    <textarea placeholder="Please provide back front image remark" class="form-control" name="back_image_remark">@if($data->back_image_remark) {{ $data->back_image_remark }} @endif</textarea>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" target= "_blank"> Omang Front Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Vehicle Right Image: </th>
                                    @if($data->right)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->right) }}" target= "_blank">
<!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->right) }}" style="height:100px;width:100px;"  >-->
                                                        @if(pathinfo($data->right,
                        PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->right,
                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                        pathinfo($data->right, PATHINFO_EXTENSION)
                                                        == 'doc' || pathinfo($data->right,
                                                        PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->right,
                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                        pathinfo($data->right, PATHINFO_EXTENSION)
                                                        == 'xlsx' || pathinfo($data->right,
                                                        PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}"
                                                                 width="100%" height="auto">
                                                        @elseif(pathinfo($data->right,
                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                        pathinfo($data->right, PATHINFO_EXTENSION)
                                                        == 'jpg' || pathinfo($data->right,
                                                        PATHINFO_EXTENSION) == 'png')
                                                            <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->right) !!}"
                                                                 width="100%" height="auto">
                                                        @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                 height="auto">
                                                        @endif

                                                    </a>
                                                </div>

                                                <div class="col-4">
                                                    <h6>Update Image Status:
                                                        @if(isset($data->right_status))
                                                            @if($data->right_status==2)
                                                            <span style="color:blue;">Recheck</span>
                                                            @endif
                                                        @endif
                                                    </h6>
                                                    <label class="kt-radio">
                                                        <input type="radio" name="right_status" @if($data->right_status == '1') checked @endif class="premiumField condition"
                                                               value="1">Approve<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="right_status" @if($data->right_status == '0') checked @endif class="premiumField condition"
                                                               value="0">Unapprove<span></span>
                                                    </label>
                                                </div>

                                                <div class="col-4">
                                                    <textarea placeholder="Please provide vehile right image remark" class="form-control" name="right_image_remark">@if($data->right_image_remark) {{ $data->right_image_remark }} @endif</textarea>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" target= "_blank"> Omang Front Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Vehicle Left Image: </th>
                                    @if($data->left)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->left) }}" target= "_blank">
<!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->left) }}" style="height:100px;width:100px;"  >-->
                                                        @if(pathinfo($data->left,
                        PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->left,
                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                        pathinfo($data->left, PATHINFO_EXTENSION)
                                                        == 'doc' || pathinfo($data->left,
                                                        PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->left,
                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                        pathinfo($data->left, PATHINFO_EXTENSION)
                                                        == 'xlsx' || pathinfo($data->left,
                                                        PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}"
                                                                 width="100%" height="auto">
                                                        @elseif(pathinfo($data->left,
                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                        pathinfo($data->left, PATHINFO_EXTENSION)
                                                        == 'jpg' || pathinfo($data->left,
                                                        PATHINFO_EXTENSION) == 'png')
                                                            <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->left) !!}"
                                                                 width="100%" height="auto">
                                                        @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                 height="auto">
                                                        @endif

                                                    </a>
                                                </div>

                                                <div class="col-4">
                                                    <h6>Update Image Status:
                                                        @if(isset($data->left_status))
                                                            @if($data->left_status==2)
                                                            <span style="color:blue;">Recheck</span>
                                                            @endif
                                                        @endif
                                                    </h6>
                                                    <label class="kt-radio">
                                                        <input type="radio" name="left_status" @if($data->left_status == '1') checked @endif class="premiumField condition"
                                                               value="1">Approve<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="left_status" @if($data->left_status == '0') checked @endif class="premiumField condition"
                                                               value="0">Unapprove<span></span>
                                                    </label>
                                                </div>

                                                <div class="col-4">
                                                    <textarea placeholder="Please provide vehile left image remark" class="form-control" name="left_image_remark">@if($data->left_image_remark) {{ $data->left_image_remark }} @endif</textarea>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" target= "_blank"> Omang Front Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Vehicle Registration Image: </th>
                                    @if($data->vehicleRegistration)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->vehicleRegistration) }}" target= "_blank">
<!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->vehicleRegistration) }}" style="height:100px;width:100px;"  >-->
                                                        @if(pathinfo($data->vehicleRegistration,
                       PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->vehicleRegistration,
                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                        pathinfo($data->vehicleRegistration, PATHINFO_EXTENSION)
                                                        == 'doc' || pathinfo($data->vehicleRegistration,
                                                        PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->vehicleRegistration,
                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                        pathinfo($data->vehicleRegistration, PATHINFO_EXTENSION)
                                                        == 'xlsx' || pathinfo($data->vehicleRegistration,
                                                        PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}"
                                                                 width="100%" height="auto">
                                                        @elseif(pathinfo($data->vehicleRegistration,
                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                        pathinfo($data->vehicleRegistration, PATHINFO_EXTENSION)
                                                        == 'jpg' || pathinfo($data->vehicleRegistration,
                                                        PATHINFO_EXTENSION) == 'png')
                                                            <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->vehicleRegistration) !!}"
                                                                 width="100%" height="auto">
                                                        @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                 height="auto">
                                                        @endif

                                                    </a>
                                                </div>

                                                <div class="col-4">
                                                    <h6>Update Image Status:
                                                        @if(isset($data->vehicle_registration_status))
                                                            @if($data->vehicle_registration_status==2)
                                                            <span style="color:blue;">Recheck</span>
                                                            @endif
                                                        @endif
                                                    </h6>
                                                    <label class="kt-radio">
                                                        <input type="radio" name="vehicle_registration_status" @if($data->vehicle_registration_status == '1') checked @endif class="premiumField condition"
                                                               value="1">Approve<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="vehicle_registration_status" @if($data->vehicle_registration_status == '0') checked @endif class="premiumField condition"
                                                               value="0">Unapprove<span></span>
                                                    </label>
                                                </div>

                                                <div class="col-4">
                                                    <textarea placeholder="Please provide vehile registration image remark" class="form-control" name="registration_image_remark">@if($data->registration_image_remark) {{ $data->registration_image_remark }} @endif</textarea>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" target= "_blank"> Omang Front Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Vehicle Invoice Image: </th>
                                    @if($data->vehicle_valuation)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->vehicle_valuation) }}" target= "_blank">
<!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->vehicle_valuation) }}" style="height:100px;width:100px;"  >-->
                                                        <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->vehicle_valuation) }}" target= "_blank">
                                                            {{--                                                    <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->driving_license) }}" style="height:100px;width:100px;"  >--}}
                                                            @if(pathinfo($data->vehicle_valuation,
                        PATHINFO_EXTENSION) == 'pdf')
                                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                     height="auto">
                                                            @elseif(pathinfo($data->vehicle_valuation,
                                                            PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($data->vehicle_valuation, PATHINFO_EXTENSION)
                                                            == 'doc' || pathinfo($data->vehicle_valuation,
                                                            PATHINFO_EXTENSION) == 'docm')
                                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                                     height="auto">
                                                            @elseif(pathinfo($data->vehicle_valuation,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($data->vehicle_valuation, PATHINFO_EXTENSION)
                                                            == 'xlsx' || pathinfo($data->vehicle_valuation,
                                                            PATHINFO_EXTENSION) == 'csv')
                                                                <img src="{{asset('images/excel.png')}}"
                                                                     width="100%" height="auto">
                                                            @elseif(pathinfo($data->vehicle_valuation,
                                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                                            pathinfo($data->vehicle_valuation, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($data->vehicle_valuation,
                                                            PATHINFO_EXTENSION) == 'png')
                                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->vehicle_valuation) !!}"
                                                                     width="100%" height="auto">
                                                            @else
                                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                                     height="auto">
                                                            @endif

                                                        </a>

                                                    </a>
                                                </div>
                                                <div class="col-4">
                                                    <h6>Update Image Status:
                                                        @if(isset($data->vehicle_invoice_status))
                                                            @if($data->vehicle_invoice_status==2)
                                                            <span style="color:blue;">Recheck</span>
                                                            @endif
                                                        @endif
                                                    </h6>
                                                    <label class="kt-radio">
                                                        <input type="radio" name="vehicle_invoice_status" @if($data->vehicle_invoice_status == '1') checked @endif class="premiumField condition"
                                                               value="1">Approve<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="vehicle_invoice_status" @if($data->vehicle_invoice_status == '0') checked @endif class="premiumField condition"
                                                               value="0">Unapprove<span></span>
                                                    </label>
                                                </div>

                                                <div class="col-4">
                                                    <textarea placeholder="Please provide vehile invoice image remark" class="form-control" name="vehicle_invoice_remark">@if($data->vehicle_invoice_remark) {{ $data->vehicle_invoice_remark }} @endif</textarea>
                                                </div>

<!--                                                <div class="col-4">
                                                    <h6>Value:</h6>
                                                    <span>@if($data->estimated_value)P{{ $data->estimated_value }}@else N/A @endif</span>
                                                </div>-->
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" target= "_blank"> Omang Front Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Compliance:</th>
                                    @if($data->compliance == 0)
                                        <td style="color:cornflowerblue">Pending Verification</td>
                                    @elseif($data->compliance == 1)
                                        <td style="color:green">Compliant</td>
                                    @elseif($data->compliance == 2)
                                        <td style="color:red">Non-Compliant</td>
                                    @else
                                        <td style="color:green">Status not found</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    @if($data->status == 0)
                                        <td style="color:cornflowerblue">Pending</td>
                                    @elseif($data->status == 1)
                                        <td style="color:green">Approved</td>
                                    @elseif($data->status == 2)
                                        <td style="color:red">Unapproved</td>
                                    @elseif($data->status == 3)
                                    <td style="color:red">Recheck</td>
                                    @else
                                        <td style="color:red">Status not found</td>
                                    @endif
                                </tr>
                                <tr>
                                <th>Action performed by:</th>
                                <td>
                                    <span>@isset($performedBy['firstName']) {{ $performedBy['firstName'] }} @endisset @isset($performedBy['lastName']) {{ $performedBy['lastName'] }} @endisset</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Action performed at:</th>
                                <td>
                                    @isset($data->updated_at)
                                    <span>{{ $data->updated_at }}</span>
                                    @endisset
                                </td>
                            </tr>
                                <tr>
                                    <th>Remark:</th>
                                    <td>
                                        <textarea  class="form-control" name="remark" placeholder="Please mention remark if any">@if($data->remark != null) {{ $data->remark }} @endif</textarea>
                                    </td>
                                </tr>
                                </thead>
                            </table>
                            <div class="kt-portlet__foot kt-portlet__foot--solid">
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-5"></div>
                                        <div class="col-7">
                                            <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Update</button>

                                            <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                            <td><button class="sendemail btn btn-brand" id="sendemail" value="{{ $data->id }}" >Send SMS and Email</button></td>
                                            <a class="btn btn-secondary" href="{{ URL::to('admin/customerVehicleInspection') }}" >Cancel</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <!--end: Datatable -->
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
<div class="modal fade" id="claimTypeModal" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Type of Claim</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
            </div>
            <div class="modal-body">
                <h5>What type of Claim you want to process ?</h5>
                <select name="type" class="form-control" id="type">
                    <option value="0">Please select claim type</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                <a href="" id="submitType" type="button" class="btn btn-brand">Confirm</a></div>
        </div>
    </div>
</div>
</div>
@include('admin.layouts.scripts')


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>

    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });

    });
    $("#policy_table").on("click", "a.claimTypeModal" , function(event) {
        event.preventDefault();

        $("#type").find("option:gt(0)").remove();
        var count = 0;
        var type;

        if($(this).is(".life")) {
            $("#type").append($("<option></option>").attr("value", "Life").text("Life"));
            count = count + 1;
            type = 'Life';
        }
        if($(this).is(".glass")) {
            $("#type").append($("<option></option>").attr("value", "Glass").text("Glass"));
            count = count + 1;
            type = 'Glass';
        }
        if($(this).is(".accident")) {
            $("#type").append($("<option></option>").attr("value", "Accident").text("Motor Accident"));
            count = count + 1;
            type = 'Accident';
        }

        //If Only one option then don't open modal pop up
        if(count == 1)
        {
            window.location.href = $(this).attr('href')+ '/' + type;
        } else {
            $("#submitType").attr("href", $(this).attr('href'));
            $('#claimTypeModal').modal('show');
        }
    });

    $('#type').on('change', function() {

    });

    $('#submitType').click(function(e) {

        if($('#type').val() == 0)
        {
            alert('Please select Claim Type');
            return false;
        }
        var _href = $("#submitType").attr("href");
        $("#submitType").attr("href", _href + '/' + $('#type').val());

    });

    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
    });
    $('#policyStatus_filter').on('change',function(){
        policyStatus_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $('#product_filter').on('change',function(){
        product_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

</script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script>
    $(document).ready(function () {
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
    });
</script>
<script>
   $(document).ready(function(){
    $("#sendemail").click(function(event){
        event.preventDefault();
        var id = $('#sendemail').val();
         console.log(id);
        $.ajax({
            type: "get",
            datatype : 'json',
            url: '{{ route("admin.sendemailurllink") }}',
            data: {
               id:id,
            },

            success: function(data){
                alert("Email and SMS for uploading Vehicle pre-inspection images has been sent successfully");


            }
            });

            });
   });

</script>

</body>
<!-- end::Body -->
</html>
