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
                @if(\AlphaDirect\Models\CellphoneDelete::where('policy_cellphone_id',$data->id)->exists())
                        <a href="{{Route('admin.CellphoneDeleteRecordes',$data->id)}}" target="_blank">
                                <button style="padding-bottom: 0px; margin-bottom: 15px; padding-top: 4px; height: 42px;"
                                        type="button" style="margin-top: 0px; margin-left: 6px;" class="btn btn-warning">Check Delete Data</button>
                       </a>
               @endif
                           
                        </div>
            </div>
            <!-- end:: Subheader -->

            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <!--begin: Datatable -->
                       
                        <!-- begin:: Content -->
                        <form id="updateCustomerKYC" action="{{ route('admin.deviceInspection.updateDeviceStatus',$data->id) }}"
                              method="POST" enctype="multipart/form-data" class="kt-form">
                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <input type="hidden" name="data_id" value="{{ $data->id }}" />
                            
                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                                <thead>
                                <tr>
                                    <th>Policy Number:</th>
                                    <td>{{ strtoupper($data->policyNumber != null ? $data->policyNumber : 'n/a') }}</td>
                                </tr>
                                
                                <tr>
                                    <th>Devive Type:</th>
                                    <td>{{ strtoupper($data->device_type != null ? $data->device_type : 'n/a') }}</td>
                                </tr>
                                <tr>
                                    <th>Make:</th>
                                    <td>{{ strtoupper($data->cell_phone_make != null ? $data->cell_phone_make : 'n/a') }}</td>
                                </tr>
                                <tr>
                                    <th>Model:</th>
                                    <td>{{ strtoupper($data->cell_phone_model != null ? $data->cell_phone_model : 'n/a') }}</td>
                                </tr>
                                <tr>
                                    <th>IMEI:</th>
                                    <td>{{ strtoupper($data->imei != null ? $data->imei : 'n/a') }}</td>
                                </tr>

                                <tr>
                                    <th>Device Front Image: </th>
                                    @if($data->cell_phone_front)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_front) }}" target= "_blank">
                                                        @if(pathinfo($data->cell_phone_front,
                                                                               PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->cell_phone_front,
                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                        pathinfo($data->cell_phone_front, PATHINFO_EXTENSION)
                                                        == 'doc' || pathinfo($data->cell_phone_front,
                                                        PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->cell_phone_front,
                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                        pathinfo($data->cell_phone_front, PATHINFO_EXTENSION)
                                                        == 'xlsx' || pathinfo($data->cell_phone_front,
                                                        PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}"
                                                                 width="100%" height="auto">
                                                        @elseif(pathinfo($data->cell_phone_front,
                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                        pathinfo($data->cell_phone_front, PATHINFO_EXTENSION)
                                                        == 'jpg' || pathinfo($data->cell_phone_front,
                                                        PATHINFO_EXTENSION) == 'png')
                                                            <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_front) !!}"
                                                                 width="100%" height="auto">
                                                        @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                 height="auto">
                                                        @endif
                                                    </a>
                                                </div>

                                                <div class="col-4">
                                                    <h6>Update Image Status:</h6>
                                                    <label class="kt-radio">
                                                        <input type="radio" name="cell_phone_front_status" @if($data->cell_phone_front_status == '1') checked @endif class="premiumField condition"
                                                               value="1">Approve<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="cell_phone_front_status" @if($data->cell_phone_front_status == '0') checked @endif class="premiumField condition"
                                                               value="0">Unapprove<span></span>
                                                    </label>
                                                </div>

                                                <div class="col-4">
                                                    <textarea placeholder="Please provide vehile front image remark" class="form-control" name="cell_phone_front_image_remark">@if($data->cell_phone_front_image_remark) {{ $data->cell_phone_front_image_remark }} @endif</textarea>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" target= "_blank"> Omang Front Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Device Back Image: </th>
                                    @if($data->cell_phone_back)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_back) }}" target= "_blank">
                                                    <!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_back) }}" style="height:100px;width:100px;"  >-->
                                                        @if(pathinfo($data->cell_phone_back,
                                                    PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->cell_phone_back,
                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                        pathinfo($data->cell_phone_back, PATHINFO_EXTENSION)
                                                        == 'doc' || pathinfo($data->cell_phone_back,
                                                        PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->cell_phone_back,
                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                        pathinfo($data->cell_phone_back, PATHINFO_EXTENSION)
                                                        == 'xlsx' || pathinfo($data->cell_phone_back,
                                                        PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}"
                                                                 width="100%" height="auto">
                                                        @elseif(pathinfo($data->cell_phone_back,
                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                        pathinfo($data->cell_phone_back, PATHINFO_EXTENSION)
                                                        == 'jpg' || pathinfo($data->cell_phone_back,
                                                        PATHINFO_EXTENSION) == 'png')
                                                            <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_back) !!}"
                                                                 width="100%" height="auto">
                                                        @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                 height="auto">
                                                        @endif

                                                    </a>
                                                </div>

                                                <div class="col-4">
                                                    <h6>Update Image Status:</h6>
                                                    <label class="kt-radio">
                                                        <input type="radio" name="cell_phone_back_status" @if($data->cell_phone_back_status == '1') checked @endif class="premiumField condition"
                                                               value="1">Approve<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="cell_phone_back_status" @if($data->cell_phone_back_status == '0') checked @endif class="premiumField condition"
                                                               value="0">Unapprove<span></span>
                                                    </label>
                                                </div>

                                                <div class="col-4">
                                                    <textarea placeholder="Please provide cell_phone_back front image remark" class="form-control" name="cell_phone_back_image_remark">@if($data->cell_phone_back_image_remark) {{ $data->cell_phone_back_image_remark }} @endif</textarea>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" target= "_blank"> Omang Front Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Device Right Image: </th>
                                    @if($data->cell_phone_right)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_right) }}" target= "_blank">
                                                    <!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_right) }}" style="height:100px;width:100px;"  >-->
                                                        @if(pathinfo($data->cell_phone_right,
                        PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->cell_phone_right,
                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                        pathinfo($data->cell_phone_right, PATHINFO_EXTENSION)
                                                        == 'doc' || pathinfo($data->cell_phone_right,
                                                        PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->cell_phone_right,
                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                        pathinfo($data->cell_phone_right, PATHINFO_EXTENSION)
                                                        == 'xlsx' || pathinfo($data->cell_phone_right,
                                                        PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}"
                                                                 width="100%" height="auto">
                                                        @elseif(pathinfo($data->cell_phone_right,
                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                        pathinfo($data->cell_phone_right, PATHINFO_EXTENSION)
                                                        == 'jpg' || pathinfo($data->cell_phone_right,
                                                        PATHINFO_EXTENSION) == 'png')
                                                            <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_right) !!}"
                                                                 width="100%" height="auto">
                                                        @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                 height="auto">
                                                        @endif

                                                    </a>
                                                </div>

                                                <div class="col-4">
                                                    <h6>Update Image Status:</h6>
                                                    <label class="kt-radio">
                                                        <input type="radio" name="cell_phone_right_status" @if($data->cell_phone_right_status == '1') checked @endif class="premiumField condition"
                                                               value="1">Approve<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="cell_phone_right_status" @if($data->cell_phone_right_status == '0') checked @endif class="premiumField condition"
                                                               value="0">Unapprove<span></span>
                                                    </label>
                                                </div>

                                                <div class="col-4">
                                                    <textarea placeholder="Please provide vehile cell_phone_right image remark" class="form-control" name="cell_phone_right_image_remark">@if($data->cell_phone_right_image_remark) {{ $data->cell_phone_right_image_remark }} @endif</textarea>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" target= "_blank"> Omang Front Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Device Left Image: </th>
                                    @if($data->cell_phone_left)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_left) }}" target= "_blank">
                                                        @if(pathinfo($data->cell_phone_left,
                        PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->cell_phone_left,
                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                        pathinfo($data->cell_phone_left, PATHINFO_EXTENSION)
                                                        == 'doc' || pathinfo($data->cell_phone_left,
                                                        PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->cell_phone_left,
                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                        pathinfo($data->cell_phone_left, PATHINFO_EXTENSION)
                                                        == 'xlsx' || pathinfo($data->cell_phone_left,
                                                        PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}"
                                                                 width="100%" height="auto">
                                                        @elseif(pathinfo($data->cell_phone_left,
                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                        pathinfo($data->cell_phone_left, PATHINFO_EXTENSION)
                                                        == 'jpg' || pathinfo($data->cell_phone_left,
                                                        PATHINFO_EXTENSION) == 'png')
                                                            <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_left) !!}"
                                                                 width="100%" height="auto">
                                                        @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                 height="auto">
                                                        @endif

                                                    </a>
                                                </div>

                                                <div class="col-4">
                                                    <h6>Update Image Status:</h6>
                                                    <label class="kt-radio">
                                                        <input type="radio" name="cell_phone_left_status" @if($data->cell_phone_left_status == '1') checked @endif class="premiumField condition"
                                                               value="1">Approve<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-cell_phone_left: 10px;">
                                                        <input type="radio" name="cell_phone_left_status" @if($data->cell_phone_left_status == '0') checked @endif class="premiumField condition"
                                                               value="0">Unapprove<span></span>
                                                    </label>
                                                </div>

                                                <div class="col-4">
                                                    <textarea placeholder="Please provide vehile cell_phone_left image remark" class="form-control" name="cell_phone_left_image_remark">@if($data->cell_phone_left_image_remark) {{ $data->cell_phone_left_image_remark }} @endif</textarea>
                                                </div>
                                            </div>
                                        </td>
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Device Top Image: </th>
                                    @if($data->cell_phone_top)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_top) }}" target= "_blank">
                                                        @if(pathinfo($data->cell_phone_top,
                       PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->cell_phone_top,
                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                        pathinfo($data->cell_phone_top, PATHINFO_EXTENSION)
                                                        == 'doc' || pathinfo($data->cell_phone_top,
                                                        PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->cell_phone_top,
                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                        pathinfo($data->cell_phone_top, PATHINFO_EXTENSION)
                                                        == 'xlsx' || pathinfo($data->cell_phone_top,
                                                        PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}"
                                                                 width="100%" height="auto">
                                                        @elseif(pathinfo($data->cell_phone_top,
                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                        pathinfo($data->cell_phone_top, PATHINFO_EXTENSION)
                                                        == 'jpg' || pathinfo($data->cell_phone_top,
                                                        PATHINFO_EXTENSION) == 'png')
                                                            <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_top) !!}"
                                                                 width="100%" height="auto">
                                                        @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                 height="auto">
                                                        @endif

                                                    </a>
                                                </div>

                                                <div class="col-4">
                                                    <h6>Update Image Status:</h6>
                                                    <label class="kt-radio">
                                                        <input type="radio" name="cell_phone_top_status" @if($data->cell_phone_top_status == '1') checked @endif class="premiumField condition"
                                                               value="1">Approve<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="cell_phone_top_status" @if($data->cell_phone_top_status == '0') checked @endif class="premiumField condition"
                                                               value="0">Unapprove<span></span>
                                                    </label>
                                                </div>

                                                <div class="col-4">
                                                    <textarea placeholder="Please provide vehile front image remark" class="form-control" name="cell_phone_top_remark">@if($data->cell_phone_top_remark) {{ $data->cell_phone_top_remark }} @endif</textarea>
                                                </div>
                                            </div>
                                        </td>
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Device Bottom Image: </th>
                                    @if($data->cell_phone_bottom)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_bottom) }}" target= "_blank">
                                                        <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_bottom) }}" target= "_blank">
                                                            @if(pathinfo($data->cell_phone_bottom,PATHINFO_EXTENSION) == 'pdf')
                                                                <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto">
                                                            @elseif(pathinfo($data->cell_phone_bottom,PATHINFO_EXTENSION) == 'docx' ||
                                                            pathinfo($data->cell_phone_bottom, PATHINFO_EXTENSION)
                                                            == 'doc' || pathinfo($data->cell_phone_bottom,
                                                            PATHINFO_EXTENSION) == 'docm')
                                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                                     height="auto">
                                                            @elseif(pathinfo($data->cell_phone_bottom,
                                                            PATHINFO_EXTENSION) == 'xls' ||
                                                            pathinfo($data->cell_phone_bottom, PATHINFO_EXTENSION)
                                                            == 'xlsx' || pathinfo($data->cell_phone_bottom,
                                                            PATHINFO_EXTENSION) == 'csv')
                                                                <img src="{{asset('images/excel.png')}}"
                                                                     width="100%" height="auto">
                                                            @elseif(pathinfo($data->cell_phone_bottom,
                                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                                            pathinfo($data->cell_phone_bottom, PATHINFO_EXTENSION)
                                                            == 'jpg' || pathinfo($data->cell_phone_bottom,
                                                            PATHINFO_EXTENSION) == 'png')
                                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->cell_phone_bottom) !!}"
                                                                     width="100%" height="auto">
                                                            @else
                                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                                     height="auto">
                                                            @endif

                                                        </a>

                                                    </a>
                                                </div>
                                                <div class="col-4">
                                                    <h6>Update Image Status:</h6>
                                                    <label class="kt-radio">
                                                        <input type="radio" name="cell_phone_bottom_status" @if($data->cell_phone_bottom_status == '1') checked @endif class="premiumField condition"
                                                               value="1">Approve<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="cell_phone_bottom_status" @if($data->cell_phone_bottom_status == '0') checked @endif class="premiumField condition"
                                                               value="0">Unapprove<span></span>
                                                    </label>
                                                </div>

                                                <div class="col-4">
                                                    <textarea placeholder="Please provide vehile front image remark" class="form-control" name="cell_phone_bottom_remark">@if($data->cell_phone_bottom_remark) {{ $data->cell_phone_bottom_remark }} @endif</textarea>
                                                </div>
                                            </div>
                                        </td>
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>

                                <tr>
                                    <th>Status:</th>
                                    @if($data->status == 1)
                                        <td style="color:green">Approved</td>
                                    @elseif($data->status == 0)
                                        <td style="color:red">Unapproved</td>
                                    @else
                                        <td style="color:cornflowerblue">N/A</td>
                                    @endif
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
                                            <a class="btn btn-secondary" href="{{ URL::to('admin/deviceData') }}" >Cancel</a>
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


</body>
<!-- end::Body -->
</html>
