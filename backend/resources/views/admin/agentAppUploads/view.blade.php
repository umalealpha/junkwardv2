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
                        View Customer KYC Data
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Customer KYC</span> </a>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View Data</span> </a>
                    </div>
                </div>
                <div>
                    <span style="color:black;font-size:120%">NOTE: Drivers license is mandatory for the customers holding policies with vehicle</span>
                </div>
            </div>
            <!-- end:: Subheader -->

            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body clearfix">
                        <!--begin: Datatable -->
                        @if(isset($rekycLink) && $rekycLink != null)
                                    <h5 style="color: {{ $rekycLink->status == 'completed' ? 'green' : 'blue' }}">Re-KYC Status : {{ $rekycLink->status }}</h5>
                        @endif
                        <!-- begin:: Content -->
                        <span style="text-align:right">@if($data != null && $data->compliance == 1)
                                <h5>Compliance: <span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill"
                                                      style="font-size:15px"> KYC Compliant</span></h5>
                            @elseif($data != null && $data->compliance == 0)
                                <h5>Compliance: <span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"
                                                      style="font-size:15px"> KYC Verification Pending</span></h5>
                            @elseif($data != null && $data->compliance == 3)
                                <h5>Compliance: <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill" style="font-size:15px"> No ID - No Documents</span></h5>
                            @else
                                <h5>Compliance: <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"
                                                      style="font-size:15px"> KYC Non Compliant</span></h5>
                            @endif
                        </span>
                        <div style="text-align: end;">
                        @if(\AlphaDirect\Models\CustomerKycDelete::where('customer_id',$data->customer_id)->exists())
                        <a href="{{Route('admin.customerKycDeleteRecordes',$data->customer_id)}}" target="_blank">
                                <button style="padding-bottom: 0px; margin-bottom: 15px; padding-top: 4px; height: 42px;"
                                        type="button" style="margin-top: 0px; margin-left: 6px;" id="activity_log" class="btn btn-warning">Check Delete Data</button>
                            </a>
                        @endif
                            <a href="{{Route('admin.customerKycActivityLogRecordes',$data->id)}}" target="_blank">
                                <button style="padding-bottom: 0px; margin-bottom: 15px; padding-top: 4px; height: 42px;"
                                        type="button" style="margin-top: 0px; margin-left: 6px;" id="activity_log" class="btn btn-primary">Check Activity Log</button>
                            </a>
                        </div>
                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                                <thead>
                                <tr>
                                    <th>Customer Name:</th>
                                    <td style="width:50%">{{ ucwords($customer->firstName.' '.$customer->middleName.' '.$customer->lastName) }}</td>
                                </tr>
                                <tr>
                                    <th>Date of Birth:</th>
                                    <td style="width:50%">
                                    @if(!empty($customer->dob))
                                       {{ \Carbon\Carbon::createFromFormat('Y-m-d', $customer->dob)->format('d M, Y') }}
                                    @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Omang Front: <br>Omang: {{ $customer->omang }} <br>Expiry: {{ ($data->omangExpiry) ? $data->omangExpiry : '-' }} </th>
                                    @if($data->omang)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" target= "_blank">
<!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" style="height:100px;width:100px;"  >-->
    @if(pathinfo($data->omang,
PATHINFO_EXTENSION) == 'pdf')
        <img src="{{asset('images/pdf.ico')}}" width="100%"
             height="auto">
    @elseif(pathinfo($data->omang,
    PATHINFO_EXTENSION) == 'docx' ||
    pathinfo($data->omang, PATHINFO_EXTENSION)
    == 'doc' || pathinfo($data->omang,
    PATHINFO_EXTENSION) == 'docm')
        <img src="{{asset('images/word.ico')}}" width="100%"
             height="auto">
    @elseif(pathinfo($data->omang,
    PATHINFO_EXTENSION) == 'xls' ||
    pathinfo($data->omang, PATHINFO_EXTENSION)
    == 'xlsx' || pathinfo($data->omang,
    PATHINFO_EXTENSION) == 'csv')
        <img src="{{asset('images/excel.png')}}"
             width="100%" height="auto">
    @elseif(pathinfo($data->omang,
    PATHINFO_EXTENSION) == 'jpeg' ||
    pathinfo($data->omang, PATHINFO_EXTENSION)
    == 'jpg' || pathinfo($data->omang,
    PATHINFO_EXTENSION) == 'png')
        <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->omang) !!}"
             width="100%" height="auto">
    @else
        <img src="{{asset('images/doc.png')}}" width="100%"
             height="auto">
    @endif

                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" target= "_blank"> Omang Front Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Omang Back: <br>Omang: {{ $customer->omang }} <br>Expiry: {{ ($data->omangExpiry) ? $data->omangExpiry : '-' }}</th>
                                    @if($data->omangBack)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omangBack) }}" target= "_blank">
<!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omangBack) }}" style="height:100px;width:100px;"  >-->
    @if(pathinfo($data->omangBack,
PATHINFO_EXTENSION) == 'pdf')
        <img src="{{asset('images/pdf.ico')}}" width="100%"
             height="auto">
    @elseif(pathinfo($data->omangBack,
    PATHINFO_EXTENSION) == 'docx' ||
    pathinfo($data->omangBack, PATHINFO_EXTENSION)
    == 'doc' || pathinfo($data->omangBack,
    PATHINFO_EXTENSION) == 'docm')
        <img src="{{asset('images/word.ico')}}" width="100%"
             height="auto">
    @elseif(pathinfo($data->omangBack,
    PATHINFO_EXTENSION) == 'xls' ||
    pathinfo($data->omangBack, PATHINFO_EXTENSION)
    == 'xlsx' || pathinfo($data->omangBack,
    PATHINFO_EXTENSION) == 'csv')
        <img src="{{asset('images/excel.png')}}"
             width="100%" height="auto">
    @elseif(pathinfo($data->omangBack,
    PATHINFO_EXTENSION) == 'jpeg' ||
    pathinfo($data->omangBack, PATHINFO_EXTENSION)
    == 'jpg' || pathinfo($data->omangBack,
    PATHINFO_EXTENSION) == 'png')
        <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->omangBack) !!}"
             width="100%" height="auto">
    @else
        <img src="{{asset('images/doc.png')}}" width="100%"
             height="auto">
    @endif

                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omangBack) }}" target= "_blank"> Omang Back Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Passport: <br>Passport Number: {{ $customer->passport }}<br>Passport Issuing Country: {{ $pic }} <br>Expiry: {{ ($data->passportExpiry) ? $data->passportExpiry : '-' }}</th>
                                    @if($data->passport)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->passport) }}" target= "_blank">
<!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->passport) }}" style="height:100px;width:100px;"  >-->
    @if(pathinfo($data->passport,
PATHINFO_EXTENSION) == 'pdf')
        <img src="{{asset('images/pdf.ico')}}" width="100%"
             height="auto">
    @elseif(pathinfo($data->passport,
    PATHINFO_EXTENSION) == 'docx' ||
    pathinfo($data->passport, PATHINFO_EXTENSION)
    == 'doc' || pathinfo($data->passport,
    PATHINFO_EXTENSION) == 'docm')
        <img src="{{asset('images/word.ico')}}" width="100%"
             height="auto">
    @elseif(pathinfo($data->passport,
    PATHINFO_EXTENSION) == 'xls' ||
    pathinfo($data->passport, PATHINFO_EXTENSION)
    == 'xlsx' || pathinfo($data->passport,
    PATHINFO_EXTENSION) == 'csv')
        <img src="{{asset('images/excel.png')}}"
             width="100%" height="auto">
    @elseif(pathinfo($data->passport,
    PATHINFO_EXTENSION) == 'jpeg' ||
    pathinfo($data->passport, PATHINFO_EXTENSION)
    == 'jpg' || pathinfo($data->passport,
    PATHINFO_EXTENSION) == 'png')
        <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->passport) !!}"
             width="100%" height="auto">
    @else
        <img src="{{asset('images/doc.png')}}" width="100%"
             height="auto">
    @endif

                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Drivers License <br>Expiry: {{ ($data->licenseExpiry) ? $data->licenseExpiry : '-' }}</th>
                                    @if($data->driving_license)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->driving_license) }}" target= "_blank">
<!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->driving_license) }}" style="height:100px;width:100px;"  >-->
    @if(pathinfo($data->driving_license,
                PATHINFO_EXTENSION) == 'pdf')
        <img src="{{asset('images/pdf.ico')}}" width="100%"
             height="auto">
    @elseif(pathinfo($data->driving_license,
    PATHINFO_EXTENSION) == 'docx' ||
    pathinfo($data->driving_license, PATHINFO_EXTENSION)
    == 'doc' || pathinfo($data->driving_license,
    PATHINFO_EXTENSION) == 'docm')
        <img src="{{asset('images/word.ico')}}" width="100%"
             height="auto">
    @elseif(pathinfo($data->driving_license,
    PATHINFO_EXTENSION) == 'xls' ||
    pathinfo($data->driving_license, PATHINFO_EXTENSION)
    == 'xlsx' || pathinfo($data->driving_license,
    PATHINFO_EXTENSION) == 'csv')
        <img src="{{asset('images/excel.png')}}"
             width="100%" height="auto">
    @elseif(pathinfo($data->driving_license,
    PATHINFO_EXTENSION) == 'jpeg' ||
    pathinfo($data->driving_license, PATHINFO_EXTENSION)
    == 'jpg' || pathinfo($data->driving_license,
    PATHINFO_EXTENSION) == 'png')
        <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->driving_license) !!}"
             width="100%" height="auto">
    @else
        <img src="{{asset('images/doc.png')}}" width="100%"
             height="auto">
    @endif

                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->driving_license) }}" target= "_blank"> Drivers License Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>

                                <tr>
                                    <th>Proof Of Residence:</th>
                                    @if($data->proof_residence)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->proof_residence) }}" target= "_blank">
<!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->proof_residence) }}" style="height:100px;width:100px;"  >-->
    @if(pathinfo($data->proof_residence,
PATHINFO_EXTENSION) == 'pdf')
        <img src="{{asset('images/pdf.ico')}}" width="100%"
             height="auto">
    @elseif(pathinfo($data->proof_residence,
    PATHINFO_EXTENSION) == 'docx' ||
    pathinfo($data->proof_residence, PATHINFO_EXTENSION)
    == 'doc' || pathinfo($data->proof_residence,
    PATHINFO_EXTENSION) == 'docm')
        <img src="{{asset('images/word.ico')}}" width="100%"
             height="auto">
    @elseif(pathinfo($data->proof_residence,
    PATHINFO_EXTENSION) == 'xls' ||
    pathinfo($data->proof_residence, PATHINFO_EXTENSION)
    == 'xlsx' || pathinfo($data->proof_residence,
    PATHINFO_EXTENSION) == 'csv')
        <img src="{{asset('images/excel.png')}}"
             width="100%" height="auto">
    @elseif(pathinfo($data->proof_residence,
    PATHINFO_EXTENSION) == 'jpeg' ||
    pathinfo($data->proof_residence, PATHINFO_EXTENSION)
    == 'jpg' || pathinfo($data->proof_residence,
    PATHINFO_EXTENSION) == 'png')
        <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->proof_residence) !!}"
             width="100%" height="auto">
    @else
        <img src="{{asset('images/doc.png')}}" width="100%"
             height="auto">
    @endif

                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->proof_residence) }}" target= "_blank"> Proof Of Residence Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Proof Of Income:</th>
                                    @if($data->proof_income)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->proof_income) }}" target= "_blank">
<!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->proof_income) }}" style="height:100px;width:100px;"  >-->
                                                        @if(pathinfo($data->proof_income,
                                                                    PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->proof_income,
                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                        pathinfo($data->proof_income, PATHINFO_EXTENSION)
                                                        == 'doc' || pathinfo($data->proof_income,
                                                        PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->proof_income,
                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                        pathinfo($data->proof_income, PATHINFO_EXTENSION)
                                                        == 'xlsx' || pathinfo($data->proof_income,
                                                        PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}"
                                                                 width="100%" height="auto">
                                                        @elseif(pathinfo($data->proof_income,
                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                        pathinfo($data->proof_income, PATHINFO_EXTENSION)
                                                        == 'jpg' || pathinfo($data->proof_income,
                                                        PATHINFO_EXTENSION) == 'png')
                                                            <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->proof_income) !!}"
                                                                 width="100%" height="auto">
                                                        @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                 height="auto">
                                                        @endif

                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->proof_income) }}" target= "_blank"> Proof Of Income Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Canceled Document:</th>
                                    @if($data->Canceled_document)
                                        <td>
                                            <div class="row">
                                                <div class="col-3">
                                                    <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->Canceled_document) }}" target= "_blank">
<!--                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->Canceled_document) }}" style="height:100px;width:100px;"  >-->
                                                        @if(pathinfo($data->Canceled_document,
                                                                    PATHINFO_EXTENSION) == 'pdf')
                                                            <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->Canceled_document,
                                                        PATHINFO_EXTENSION) == 'docx' ||
                                                        pathinfo($data->Canceled_document, PATHINFO_EXTENSION)
                                                        == 'doc' || pathinfo($data->Canceled_document,
                                                        PATHINFO_EXTENSION) == 'docm')
                                                            <img src="{{asset('images/word.ico')}}" width="100%"
                                                                 height="auto">
                                                        @elseif(pathinfo($data->Canceled_document,
                                                        PATHINFO_EXTENSION) == 'xls' ||
                                                        pathinfo($data->Canceled_document, PATHINFO_EXTENSION)
                                                        == 'xlsx' || pathinfo($data->Canceled_document,
                                                        PATHINFO_EXTENSION) == 'csv')
                                                            <img src="{{asset('images/excel.png')}}"
                                                                 width="100%" height="auto">
                                                        @elseif(pathinfo($data->Canceled_document,
                                                        PATHINFO_EXTENSION) == 'jpeg' ||
                                                        pathinfo($data->Canceled_document, PATHINFO_EXTENSION)
                                                        == 'jpg' || pathinfo($data->Canceled_document,
                                                        PATHINFO_EXTENSION) == 'png')
                                                            <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($data->Canceled_document) !!}"
                                                                 width="100%" height="auto">
                                                        @else
                                                            <img src="{{asset('images/doc.png')}}" width="100%"
                                                                 height="auto">
                                                        @endif

                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        {{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->Canceled_document) }}" target= "_blank"> Proof Of Income Picture Download link </a></td>--}}
                                    @else
                                        <td>Not Uploaded</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Document Uploaded On:</th>
                                    @if($data->created_at)
                                        <td>{{ $data->created_at }}</td>
                                    @else
                                        <td>N/A</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Document Updated On:</th>
                                    @if($data->updated_at)
                                        <td>{{ $data->updated_at }}</td>
                                    @else
                                        <td>Never Updated</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Compliance:</th>
                                    @if($data->compliance == 2)
                                        <td style="color:red">No</td>
                                    @elseif($data->compliance == 1)
                                        <td style="color:green">Yes</td>
                                    @elseif($data->compliance == 0)
                                        <td style="color:green">Pending</td>
                                    @elseif($data->compliance == 3)
                                        <td style="color:green">No ID - No Documents</td>
                                    @else
                                        <td style="color:green">Status not found</td>
                                    @endif

                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    @if($data->status == 'Approve')
                                        <td style="color:green">Approved</td>
                                    @elseif($data->status == 'Unapprove')
                                        <td style="color:red">Unapproved</td>
                                    @elseif($data->status == 'Recheck')
                                        <td style="color:red">Recheck</td>
                                    @elseif($data->status == 'Renew')
                                        <td style="color:red">Renew</td>
                                    @else
                                        <td style="color:cornflowerblue">Unchecked</td>
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
                                    @if($data->remark != '')
                                        <td style="width:50%">{{ $data->remark }}</td>
                                    @else
                                        <td style="width:50%">--</td>
                                    @endif
                                </tr>
                                </thead>
                            </table>
                        <div>
                            <span style="display:inline;text-align:left"><a href="{{ route('admin.verifyCustomerKycData', ($data->id)-1) }}" class="btn btn-brand">Previous</a></span>
                            <span style="display:inline;text-align:right"><a href="{{ route('admin.verifyCustomerKycData', ($data->id)+1) }}" class="btn btn-brand">Next</a></span>
                        </div>
                    </div>
                </div>
            </div>
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
