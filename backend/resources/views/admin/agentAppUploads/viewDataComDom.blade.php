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
                        <a href="{{Route('admin.customerKyc')}}" class="kt-subheader__breadcrumbs-link"> Customer KYC </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View Data</span> </a>
                    </div>
                </div>
                <div>
                    <span style="color:black;font-size:120%">NOTE: Drivers license is mandatory for the customers holding policies with vehicle</span>
                </div>
            </div>
            <!-- end:: Subheader -->

            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">

                        {{-- @foreach ($custPolicyNo as $policy)
                            <h5>Policy Number: {{$policy->policyNumber}}  ({{$policy->name}})
                            </h5>
                        @endforeach --}}
                        @if ($custPolicyNo->filter(function ($policy) {
                                return isset($policy->product_id) && in_array($policy->product_id, [7, 8]);
                            })->isNotEmpty())
                            @foreach ($custPolicyNo->filter(function ($policy) {
                                return isset($policy->product_id) && in_array($policy->product_id, [7, 8]);
                            }) as $policy)
                                <h5>Policy Number: {{$policy->policyNumber}}  ({{$policy->name}})
                                </h5>
                            @endforeach
                        @else
                            <p>No policies available to display.</p>
                        @endif

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
                        {{--<div style="text-align: end;">
                            <a href="{{Route('admin.customerKycActivityLog',$data->id)}}" target="_blank">
                                <button style="padding-bottom: 0px; margin-bottom: 15px; padding-top: 4px; height: 42px;"
                                type="button" style="margin-top: 0px; margin-left: 6px;" id="activity_log" class="btn btn-primary">Check Activity Log</button>
                            </a>
                        </div>--}}

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

                        <!--begin: Datatable -->
                        <!-- begin:: Content -->
                        <form id="updateCustomerKYC" action="{{ route('admin.customer.verifyKYCInfoForDomCom') }}"
                              method="POST" enctype="multipart/form-data" class="kt-form">
                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <input type="hidden" name="data_id" value="{{ $data->id }}" />
                        <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                            <thead>
                            <tr>
                                {{-- <th>Customer Name:</th>
                                <td>{{ ucwords($customer->firstName.' '.$customer->middleName.' '.$customer->lastName) }}</td> --}}
                                <th>Customer Name:</th>
                                <td>{{ ucwords($domComCustname) }}</td>
                                {{-- @if ($policydetails->product_id == 7)
                                    @if ($customer->entity_type == 'Organisation' && isset($company->name))
                                        <td>{{ ucwords($company->name) }}</td>
                                    @else
                                        <td>{{ ucwords($customer->firstName.' '.$customer->middleName.' '.$customer->lastName) }}</td>
                                    @endif
                                @else
                                    <td>{{ ucwords($customer->firstName.' '.$customer->middleName.' '.$customer->lastName) }}</td>
                                @endif --}}
                            </tr>
                            <tr>
                                <th>Date of Birth:</th>
                                @if($AdiPolicy > 0)
                                    <td>
                                        <input type="text" class="form-control dob_with_limitation" name="dob"
                                        value="{{ $customer->dob }}"
                                        autocomplete="off" placeholder="Select date" />
                                    </td>
                                @elseif ($LegalPolicy > 0)
                                    <td>
                                        <input type="text" class="form-control dob_with_limitation" name="dob"
                                        value="{{ $customer->dob }}"
                                        autocomplete="off" placeholder="Select date" />
                                    </td>
                                @else
                                    <td>
                                        <input type="text" class="form-control dob" name="dob"
                                        value="{{ $customer->dob }}"
                                        autocomplete="off" placeholder="Select date" />
                                    </td>
                                @endif

                            </tr>
                            @foreach($groupedPolicies as $productId => $policies)
                            @if ($productId == 8)
                            <tr>
                                <th>Omang Front: <br>Omang: {{ $customer->omang }} <br>Expiry: {{ ($data->omangExpiry) ? $data->omangExpiry : '-' }} </th>
                                @if(isset($data->omang))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" style="height:100px;width:100px;"  >-->
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

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($data->omangFrontStatus))
                                                    @if($data->omangFrontStatus==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($data->omangFrontStatus==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="omangFrontStatus" @if($data->omangFrontStatus == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="omangFrontStatus" @if($data->omangFrontStatus == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                                <br>
                                                <div class="form-group expiryDateDiv" style="display: none;">
                                                    <label for="exampleSelect1">Date of omang expiry</label>
                                                    <input type="text" id="omangExp" class="form-control  kt_datepicker_1 expiryDateClass" @isset($data->omangExpiry) value="{{$data->omangExpiry}}" @endisset name="date_of_omangExpiry" autocomplete="off" placeholder="Enter expiry date">
                                                </div>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide omang front remark" class="form-control" name="omangFrontRemark">@if($data->omangFrontRemark) {{ $data->omangFrontRemark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="omang_front" id="omang_front" value="omang_front">
                                               <a href="#" role="button" id="omang_front_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                          {{--  <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omang) }}" target= "_blank"> Omang Front Picture Download link </a></td>--}}
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Omang Back: <br>Omang: {{ $customer->omang }} <br>Expiry: {{ ($data->omangExpiry) ? $data->omangExpiry : '-' }}</th>
                                @if(isset($data->omangBack))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omangBack) }}" target= "_blank">
                                        <!--   <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omangBack) }}" style="height:100px;width:100px;"  >-->
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

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($data->omangBackStatus))
                                                    @if($data->omangBackStatus==0)
                                                      <span style="color:red;">Rejected</span>
                                                    @elseif($data->omangBackStatus==2)
                                                      <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif
                                                </h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="omangBackStatus" @if($data->omangBackStatus == '1') checked @endif class="premiumField condition"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="omangBackStatus" @if($data->omangBackStatus == '0') checked @endif class="premiumField condition"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide omang back remark" class="form-control" name="omangBackRemark">@if($data->omangBackRemark) {{ $data->omangBackRemark }} @endif</textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="omang_back" id="omang_back" value="omang_back">
                                                <a href="#" id="omang_back_delete" role="button" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete"> <i class="la la-trash"></i></a>
                                             </div>
                                        </div>
                                    </td>
                           {{--    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->omangBack) }}" target= "_blank"> Omang Back Picture Download link </a></td>--}}
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Passport: <br>Passport Number: {{ $customer->passport }}<br>Passport Issuing Country: {{ $pic }} <br>Expiry: {{ ($data->passportExpiry) ? $data->passportExpiry : '-' }}</th>
                                @if(isset($data->passport))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->passport) }}" target= "_blank">
                                            <!--    <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->passport) }}" style="height:100px;width:100px;"  >-->
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

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($data->passportStatus))
                                                     @if($data->passportStatus==0)
                                                       <span style="color:red;">Rejected</span>
                                                     @elseif($data->passportStatus==2)
                                                       <span style="color:blue;">Recheck</span>
                                                     @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="passportStatus" @if($data->passportStatus == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="passportStatus" @if($data->passportStatus == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                                <br>
                                                <div class="form-group" class="expiryDateDiv" style="display: none;">
                                                    <label for="exampleSelect1">Date of passport expiry</label>
                                                    <input type="text" id="passportExp" class="form-control  kt_datepicker_1 expiryDateClass"  @isset($data->passportExpiry) value="{{$data->passportExpiry}}" @endisset  name="date_of_passportExpiry" autocomplete="off" placeholder="Enter expiry date">
                                                </div>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide passport remark" class="form-control" name="passportRemark">@if($data->passportRemark) {{ $data->passportRemark }} @endif</textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="data_passport" id="data_passport" value="data_passport">
                                                <a href="#" role="button" id="passport_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete"> <i class="la la-trash"></i></a>
                                             </div>
                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>

                            <tr>
                                <th>Proof Of Residence:</th>
                                @if(isset($data->proof_residence))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->proof_residence) }}" target= "_blank">
<!--                                                    <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->proof_residence) }}" style="height:100px;width:100px;"  >-->
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

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($data->proof_residenceStatus))
                                                    @if($data->proof_residenceStatus==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($data->proof_residenceStatus==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif
                                                </h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="proof_residenceStatus" @if($data->proof_residenceStatus == '1') checked @endif class="premiumField condition"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="proof_residenceStatus" @if($data->proof_residenceStatus == '0') checked @endif class="premiumField condition"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide proof of residence remark" class="form-control" name="proof_residenceRemark">@if($data->proof_residenceRemark) {{ $data->proof_residenceRemark }} @endif</textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="proof_residence" id="proof_residence" value="proof_residence">
                                                <a href="#" role="button" id="proof_residence_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete"> <i class="la la-trash"></i></a>
                                            </div>
                                        </div>
                                    </td>
                                 {{-- <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->proof_residence) }}" target= "_blank"> Proof Of Residence Picture Download link </a></td>--}}
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Proof Of Income:</th>
                                @if(isset($data->proof_income))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->proof_income) }}" target= "_blank">
<!--                                                    <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->proof_income) }}" style="height:100px;width:100px;"  >-->
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

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($data->proof_incomeStatus))
                                                    @if($data->proof_incomeStatus==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($data->proof_incomeStatus==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="proof_incomeStatus" @if($data->proof_incomeStatus == '1') checked @endif class="premiumField condition"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="proof_incomeStatus" @if($data->proof_incomeStatus == '0') checked @endif class="premiumField condition"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide proof of income remark" class="form-control" name="proof_incomeRemark">@if($data->proof_incomeRemark) {{ $data->proof_incomeRemark }} @endif</textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="proof_income" id="proof_income" value="proof_income">
                                                <a href="#" role="button" id="proof_income_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete"> <i class="la la-trash"></i></a>
                                             </div>
                                        </div>
                                    </td>
{{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->proof_income) }}" target= "_blank"> Proof Of Income Picture Download link </a></td>--}}
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            @endif

                            @if ($productId == 7)
                            </tr>
                            <tr>
                                <th>Certificate of Incorporation/Registration </th>
                                @if(isset($kycDomCom->certificate_of_incorporation))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->certificate_of_incorporation) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->certificate_of_incorporation) }}" style="height:100px;width:100px;"  >-->
                                            @if(pathinfo($kycDomCom->certificate_of_incorporation,
                                        PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->certificate_of_incorporation,
                                            PATHINFO_EXTENSION) == 'docx' ||
                                            pathinfo($kycDomCom->certificate_of_incorporation, PATHINFO_EXTENSION)
                                            == 'doc' || pathinfo($kycDomCom->certificate_of_incorporation,
                                            PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->certificate_of_incorporation,
                                            PATHINFO_EXTENSION) == 'xls' ||
                                            pathinfo($kycDomCom->certificate_of_incorporation, PATHINFO_EXTENSION)
                                            == 'xlsx' || pathinfo($kycDomCom->certificate_of_incorporation,
                                            PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}"
                                                    width="100%" height="auto">
                                            @elseif(pathinfo($kycDomCom->certificate_of_incorporation,
                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                            pathinfo($kycDomCom->certificate_of_incorporation, PATHINFO_EXTENSION)
                                            == 'jpg' || pathinfo($kycDomCom->certificate_of_incorporation,
                                            PATHINFO_EXTENSION) == 'png')
                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->certificate_of_incorporation) !!}"
                                                    width="100%" height="auto">
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                    height="auto">
                                            @endif

                                                </a>
                                            </div>

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($kycDomCom->certificate_of_incorporation_status))
                                                    @if($kycDomCom->certificate_of_incorporation_status==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($kycDomCom->certificate_of_incorporation_status==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="certificate_of_incorporation_status" @if($kycDomCom->certificate_of_incorporation_status == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="certificate_of_incorporation_status" @if($kycDomCom->certificate_of_incorporation_status == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide certificate of incorporation remark" class="form-control" name="certificate_of_incorporation_remark">@if($kycDomCom->certificate_of_incorporation_remark) {{ $kycDomCom->certificate_of_incorporation_remark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="certificate_of_incorporation" id="certificate_of_incorporation" value="certificate_of_incorporation">
                                               <a href="#" role="button" id="certificate_of_incorporation_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Extract controllers and ownership structure</th>
                                @if(isset($kycDomCom->extract_controllers))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->extract_controllers) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->extract_controllers) }}" style="height:100px;width:100px;"  >-->
                                            @if(pathinfo($kycDomCom->extract_controllers,
                                        PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->extract_controllers,
                                            PATHINFO_EXTENSION) == 'docx' ||
                                            pathinfo($kycDomCom->extract_controllers, PATHINFO_EXTENSION)
                                            == 'doc' || pathinfo($kycDomCom->extract_controllers,
                                            PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->extract_controllers,
                                            PATHINFO_EXTENSION) == 'xls' ||
                                            pathinfo($kycDomCom->extract_controllers, PATHINFO_EXTENSION)
                                            == 'xlsx' || pathinfo($kycDomCom->extract_controllers,
                                            PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}"
                                                    width="100%" height="auto">
                                            @elseif(pathinfo($kycDomCom->extract_controllers,
                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                            pathinfo($kycDomCom->extract_controllers, PATHINFO_EXTENSION)
                                            == 'jpg' || pathinfo($kycDomCom->extract_controllers,
                                            PATHINFO_EXTENSION) == 'png')
                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->extract_controllers) !!}"
                                                    width="100%" height="auto">
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                    height="auto">
                                            @endif

                                                </a>
                                            </div>

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($kycDomCom->extract_controllers_status))
                                                    @if($kycDomCom->extract_controllers_status==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($kycDomCom->extract_controllers_status==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="extract_controllers_status" @if($kycDomCom->extract_controllers_status == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="extract_controllers_status" @if($kycDomCom->extract_controllers_status == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide extract controllers remark" class="form-control" name="extract_controllers_remark">@if($kycDomCom->extract_controllers_remark) {{ $kycDomCom->extract_controllers_remark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="extract_controllers" id="extract_controllers" value="extract_controllers">
                                               <a href="#" role="button" id="extract_controllers_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Resolution</th>
                                @if(isset($kycDomCom->resolution))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->resolution) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->resolution) }}" style="height:100px;width:100px;"  >-->
                                            @if(pathinfo($kycDomCom->resolution,
                                        PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->resolution,
                                            PATHINFO_EXTENSION) == 'docx' ||
                                            pathinfo($kycDomCom->resolution, PATHINFO_EXTENSION)
                                            == 'doc' || pathinfo($kycDomCom->resolution,
                                            PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->resolution,
                                            PATHINFO_EXTENSION) == 'xls' ||
                                            pathinfo($kycDomCom->resolution, PATHINFO_EXTENSION)
                                            == 'xlsx' || pathinfo($kycDomCom->resolution,
                                            PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}"
                                                    width="100%" height="auto">
                                            @elseif(pathinfo($kycDomCom->resolution,
                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                            pathinfo($kycDomCom->resolution, PATHINFO_EXTENSION)
                                            == 'jpg' || pathinfo($kycDomCom->resolution,
                                            PATHINFO_EXTENSION) == 'png')
                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->resolution) !!}"
                                                    width="100%" height="auto">
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                    height="auto">
                                            @endif

                                                </a>
                                            </div>

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($kycDomCom->resolution_status))
                                                    @if($kycDomCom->resolution_status==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($kycDomCom->resolution_status==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="resolution_status" @if($kycDomCom->resolution_status == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="resolution_status" @if($kycDomCom->resolution_status == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide resolution remark" class="form-control" name="resolution_remark">@if($kycDomCom->resolution_remark) {{ $kycDomCom->resolution_remark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="resolution" id="resolution" value="resolution">
                                               <a href="#" role="button" id="resolution_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Proof of business address</th>
                                @if(isset($kycDomCom->proof_business_address))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->proof_business_address) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->proof_business_address) }}" style="height:100px;width:100px;"  >-->
                                            @if(pathinfo($kycDomCom->proof_business_address,
                                        PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->proof_business_address,
                                            PATHINFO_EXTENSION) == 'docx' ||
                                            pathinfo($kycDomCom->proof_business_address, PATHINFO_EXTENSION)
                                            == 'doc' || pathinfo($kycDomCom->proof_business_address,
                                            PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->proof_business_address,
                                            PATHINFO_EXTENSION) == 'xls' ||
                                            pathinfo($kycDomCom->proof_business_address, PATHINFO_EXTENSION)
                                            == 'xlsx' || pathinfo($kycDomCom->proof_business_address,
                                            PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}"
                                                    width="100%" height="auto">
                                            @elseif(pathinfo($kycDomCom->proof_business_address,
                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                            pathinfo($kycDomCom->proof_business_address, PATHINFO_EXTENSION)
                                            == 'jpg' || pathinfo($kycDomCom->proof_business_address,
                                            PATHINFO_EXTENSION) == 'png')
                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->proof_business_address) !!}"
                                                    width="100%" height="auto">
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                    height="auto">
                                            @endif

                                                </a>
                                            </div>

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($kycDomCom->proof_business_address_status))
                                                    @if($kycDomCom->proof_business_address_status==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($kycDomCom->proof_business_address_status==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="proof_business_address_status" @if($kycDomCom->proof_business_address_status == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="proof_business_address_status" @if($kycDomCom->proof_business_address_status == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide proof of business address remark" class="form-control" name="proof_business_address_remark">@if($kycDomCom->proof_business_address_remark) {{ $kycDomCom->proof_business_address_remark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="proof_business_address" id="proof_business_address" value="proof_business_address">
                                               <a href="#" role="button" id="proof_business_address_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Proof of residential address for Directors and Shareholders</th>
                                @if(isset($kycDomCom->proof_residential_address))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->proof_residential_address) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->proof_residential_address) }}" style="height:100px;width:100px;"  >-->
                                            @if(pathinfo($kycDomCom->proof_residential_address,
                                        PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->proof_residential_address,
                                            PATHINFO_EXTENSION) == 'docx' ||
                                            pathinfo($kycDomCom->proof_residential_address, PATHINFO_EXTENSION)
                                            == 'doc' || pathinfo($kycDomCom->proof_residential_address,
                                            PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->proof_residential_address,
                                            PATHINFO_EXTENSION) == 'xls' ||
                                            pathinfo($kycDomCom->proof_residential_address, PATHINFO_EXTENSION)
                                            == 'xlsx' || pathinfo($kycDomCom->proof_residential_address,
                                            PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}"
                                                    width="100%" height="auto">
                                            @elseif(pathinfo($kycDomCom->proof_residential_address,
                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                            pathinfo($kycDomCom->proof_residential_address, PATHINFO_EXTENSION)
                                            == 'jpg' || pathinfo($kycDomCom->proof_residential_address,
                                            PATHINFO_EXTENSION) == 'png')
                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->proof_residential_address) !!}"
                                                    width="100%" height="auto">
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                    height="auto">
                                            @endif

                                                </a>
                                            </div>

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($kycDomCom->proof_residential_address_status))
                                                    @if($kycDomCom->proof_residential_address_status==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($kycDomCom->proof_residential_address_status==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="proof_residential_address_status" @if($kycDomCom->proof_residential_address_status == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="proof_residential_address_status" @if($kycDomCom->proof_residential_address_status == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide proof of residential address remark" class="form-control" name="proof_residential_address_remark">@if($kycDomCom->proof_residential_address_remark) {{ $kycDomCom->proof_residential_address_remark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="proof_residential_address" id="proof_residential_address" value="proof_residential_address">
                                               <a href="#" role="button" id="proof_residential_address_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Directors ID front</th>
                                @if(isset($kycDomCom->directors_id_front))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->directors_id_front) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->directors_id_front) }}" style="height:100px;width:100px;"  >-->
                                            @if(pathinfo($kycDomCom->directors_id_front,
                                        PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->directors_id_front,
                                            PATHINFO_EXTENSION) == 'docx' ||
                                            pathinfo($kycDomCom->directors_id_front, PATHINFO_EXTENSION)
                                            == 'doc' || pathinfo($kycDomCom->directors_id_front,
                                            PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->directors_id_front,
                                            PATHINFO_EXTENSION) == 'xls' ||
                                            pathinfo($kycDomCom->directors_id_front, PATHINFO_EXTENSION)
                                            == 'xlsx' || pathinfo($kycDomCom->directors_id_front,
                                            PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}"
                                                    width="100%" height="auto">
                                            @elseif(pathinfo($kycDomCom->directors_id_front,
                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                            pathinfo($kycDomCom->directors_id_front, PATHINFO_EXTENSION)
                                            == 'jpg' || pathinfo($kycDomCom->directors_id_front,
                                            PATHINFO_EXTENSION) == 'png')
                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->directors_id_front) !!}"
                                                    width="100%" height="auto">
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                    height="auto">
                                            @endif

                                                </a>
                                            </div>

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($kycDomCom->directors_id_front_status))
                                                    @if($kycDomCom->directors_id_front_status==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($kycDomCom->directors_id_front_status==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="directors_id_front_status" @if($kycDomCom->directors_id_front_status == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="directors_id_front_status" @if($kycDomCom->directors_id_front_status == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                                <br>
                                                <div class="form-group" class="directorsIDexpiryDateDiv" style="display: none;">
                                                    <label for="exampleSelect1">Date of directors ID expiry</label>
                                                    <input type="text" id="directorsIDExp" class="form-control  kt_datepicker_1 expiryDateClass"  @isset($kycDomCom->directorsIDExp) value="{{$kycDomCom->directorsIDExp}}" @endisset  name="directorsIDExp" autocomplete="off" placeholder="Enter expiry date">
                                                </div>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide directors id front remark" class="form-control" name="directors_id_front_remark">@if($kycDomCom->directors_id_front_remark) {{ $kycDomCom->directors_id_front_remark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="directors_id_front" id="directors_id_front" value="directors_id_front">
                                               <a href="#" role="button" id="directors_id_front_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Directors ID back</th>
                                @if(isset($kycDomCom->directors_id_back))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->directors_id_back) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->directors_id_back) }}" style="height:100px;width:100px;"  >-->
                                            @if(pathinfo($kycDomCom->directors_id_back,
                                        PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->directors_id_back,
                                            PATHINFO_EXTENSION) == 'docx' ||
                                            pathinfo($kycDomCom->directors_id_back, PATHINFO_EXTENSION)
                                            == 'doc' || pathinfo($kycDomCom->directors_id_back,
                                            PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->directors_id_back,
                                            PATHINFO_EXTENSION) == 'xls' ||
                                            pathinfo($kycDomCom->directors_id_back, PATHINFO_EXTENSION)
                                            == 'xlsx' || pathinfo($kycDomCom->directors_id_back,
                                            PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}"
                                                    width="100%" height="auto">
                                            @elseif(pathinfo($kycDomCom->directors_id_back,
                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                            pathinfo($kycDomCom->directors_id_back, PATHINFO_EXTENSION)
                                            == 'jpg' || pathinfo($kycDomCom->directors_id_back,
                                            PATHINFO_EXTENSION) == 'png')
                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->directors_id_back) !!}"
                                                    width="100%" height="auto">
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                    height="auto">
                                            @endif

                                                </a>
                                            </div>

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($kycDomCom->directors_id_back_status))
                                                    @if($kycDomCom->directors_id_back_status==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($kycDomCom->directors_id_back_status==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="directors_id_back_status" @if($kycDomCom->directors_id_back_status == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="directors_id_back_status" @if($kycDomCom->directors_id_back_status == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide directors id back remark" class="form-control" name="directors_id_back_remark">@if($kycDomCom->directors_id_back_remark) {{ $kycDomCom->directors_id_back_remark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="directors_id_back" id="directors_id_back" value="directors_id_back">
                                               <a href="#" role="button" id="directors_id_back_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Directors passport</th>
                                @if(isset($kycDomCom->directors_passport))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->directors_passport) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->directors_passport) }}" style="height:100px;width:100px;"  >-->
                                            @if(pathinfo($kycDomCom->directors_passport,
                                        PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->directors_passport,
                                            PATHINFO_EXTENSION) == 'docx' ||
                                            pathinfo($kycDomCom->directors_passport, PATHINFO_EXTENSION)
                                            == 'doc' || pathinfo($kycDomCom->directors_passport,
                                            PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->directors_passport,
                                            PATHINFO_EXTENSION) == 'xls' ||
                                            pathinfo($kycDomCom->directors_passport, PATHINFO_EXTENSION)
                                            == 'xlsx' || pathinfo($kycDomCom->directors_passport,
                                            PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}"
                                                    width="100%" height="auto">
                                            @elseif(pathinfo($kycDomCom->directors_passport,
                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                            pathinfo($kycDomCom->directors_passport, PATHINFO_EXTENSION)
                                            == 'jpg' || pathinfo($kycDomCom->directors_passport,
                                            PATHINFO_EXTENSION) == 'png')
                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->directors_passport) !!}"
                                                    width="100%" height="auto">
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                    height="auto">
                                            @endif

                                                </a>
                                            </div>

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($kycDomCom->directors_passport_status))
                                                    @if($kycDomCom->directors_passport_status==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($kycDomCom->directors_passport_status==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="directors_passport_status" @if($kycDomCom->directors_passport_status == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="directors_passport_status" @if($kycDomCom->directors_passport_status == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                                <br>
                                                <div class="form-group" class="expiryDateDiv" style="display: none;">
                                                    <label for="exampleSelect1">Date of directors passport expiry</label>
                                                    <input type="text" id="directorsPassportExp" class="form-control  kt_datepicker_1 expiryDateClass"  @isset($kycDomCom->directorsPassportExp) value="{{$kycDomCom->directorsPassportExp}}" @endisset  name="directorsPassportExp" autocomplete="off" placeholder="Enter expiry date">
                                                </div>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide directors passport remark" class="form-control" name="directors_passport_remark">@if($kycDomCom->directors_passport_remark) {{ $kycDomCom->directors_passport_remark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="directors_passport" id="directors_passport" value="directors_passport">
                                               <a href="#" role="button" id="directors_passport_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Shareholders ID front</th>
                                @if(isset($kycDomCom->shareholders_id_front))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->shareholders_id_front) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->shareholders_id_front) }}" style="height:100px;width:100px;"  >-->
                                            @if(pathinfo($kycDomCom->shareholders_id_front,
                                        PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->shareholders_id_front,
                                            PATHINFO_EXTENSION) == 'docx' ||
                                            pathinfo($kycDomCom->shareholders_id_front, PATHINFO_EXTENSION)
                                            == 'doc' || pathinfo($kycDomCom->shareholders_id_front,
                                            PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->shareholders_id_front,
                                            PATHINFO_EXTENSION) == 'xls' ||
                                            pathinfo($kycDomCom->shareholders_id_front, PATHINFO_EXTENSION)
                                            == 'xlsx' || pathinfo($kycDomCom->shareholders_id_front,
                                            PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}"
                                                    width="100%" height="auto">
                                            @elseif(pathinfo($kycDomCom->shareholders_id_front,
                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                            pathinfo($kycDomCom->shareholders_id_front, PATHINFO_EXTENSION)
                                            == 'jpg' || pathinfo($kycDomCom->shareholders_id_front,
                                            PATHINFO_EXTENSION) == 'png')
                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->shareholders_id_front) !!}"
                                                    width="100%" height="auto">
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                    height="auto">
                                            @endif

                                                </a>
                                            </div>

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($kycDomCom->shareholders_id_front_status))
                                                    @if($kycDomCom->shareholders_id_front_status==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($kycDomCom->shareholders_id_front_status==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="shareholders_id_front_status" @if($kycDomCom->shareholders_id_front_status == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="shareholders_id_front_status" @if($kycDomCom->shareholders_id_front_status == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                                <br>
                                                <div class="form-group" class="expiryDateDiv" style="display: none;">
                                                    <label for="exampleSelect1">Date of shareholders ID expiry</label>
                                                    <input type="text" id="shareholdersIDExp" class="form-control  kt_datepicker_1 expiryDateClass"  @isset($kycDomCom->shareholdersIDExp) value="{{$kycDomCom->shareholdersIDExp}}" @endisset  name="shareholdersIDExp" autocomplete="off" placeholder="Enter expiry date">
                                                </div>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide shareholders id front remark" class="form-control" name="shareholders_id_front_remark">@if($kycDomCom->shareholders_id_front_remark) {{ $kycDomCom->shareholders_id_front_remark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="shareholders_id_front" id="shareholders_id_front" value="shareholders_id_front">
                                               <a href="#" role="button" id="shareholders_id_front_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Shareholders ID back</th>
                                @if(isset($kycDomCom->shareholders_id_back))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->shareholders_id_back) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->shareholders_id_back) }}" style="height:100px;width:100px;"  >-->
                                            @if(pathinfo($kycDomCom->shareholders_id_back,
                                        PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->shareholders_id_back,
                                            PATHINFO_EXTENSION) == 'docx' ||
                                            pathinfo($kycDomCom->shareholders_id_back, PATHINFO_EXTENSION)
                                            == 'doc' || pathinfo($kycDomCom->shareholders_id_back,
                                            PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->shareholders_id_back,
                                            PATHINFO_EXTENSION) == 'xls' ||
                                            pathinfo($kycDomCom->shareholders_id_back, PATHINFO_EXTENSION)
                                            == 'xlsx' || pathinfo($kycDomCom->shareholders_id_back,
                                            PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}"
                                                    width="100%" height="auto">
                                            @elseif(pathinfo($kycDomCom->shareholders_id_back,
                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                            pathinfo($kycDomCom->shareholders_id_back, PATHINFO_EXTENSION)
                                            == 'jpg' || pathinfo($kycDomCom->shareholders_id_back,
                                            PATHINFO_EXTENSION) == 'png')
                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->shareholders_id_back) !!}"
                                                    width="100%" height="auto">
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                    height="auto">
                                            @endif

                                                </a>
                                            </div>

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($kycDomCom->shareholders_id_back_status))
                                                    @if($kycDomCom->shareholders_id_back_status==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($kycDomCom->shareholders_id_back_status==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="shareholders_id_back_status" @if($kycDomCom->shareholders_id_back_status == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="shareholders_id_back_status" @if($kycDomCom->shareholders_id_back_status == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide shareholders id back remark" class="form-control" name="shareholders_id_back_remark">@if($kycDomCom->shareholders_id_back_remark) {{ $kycDomCom->shareholders_id_back_remark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="shareholders_id_back" id="shareholders_id_back" value="shareholders_id_back">
                                               <a href="#" role="button" id="shareholders_id_back_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Shareholders passport</th>
                                @if(isset($kycDomCom->shareholders_passport))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->shareholders_passport) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->shareholders_passport) }}" style="height:100px;width:100px;"  >-->
                                            @if(pathinfo($kycDomCom->shareholders_passport,
                                        PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->shareholders_passport,
                                            PATHINFO_EXTENSION) == 'docx' ||
                                            pathinfo($kycDomCom->shareholders_passport, PATHINFO_EXTENSION)
                                            == 'doc' || pathinfo($kycDomCom->shareholders_passport,
                                            PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->shareholders_passport,
                                            PATHINFO_EXTENSION) == 'xls' ||
                                            pathinfo($kycDomCom->shareholders_passport, PATHINFO_EXTENSION)
                                            == 'xlsx' || pathinfo($kycDomCom->shareholders_passport,
                                            PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}"
                                                    width="100%" height="auto">
                                            @elseif(pathinfo($kycDomCom->shareholders_passport,
                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                            pathinfo($kycDomCom->shareholders_passport, PATHINFO_EXTENSION)
                                            == 'jpg' || pathinfo($kycDomCom->shareholders_passport,
                                            PATHINFO_EXTENSION) == 'png')
                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->shareholders_passport) !!}"
                                                    width="100%" height="auto">
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                    height="auto">
                                            @endif

                                                </a>
                                            </div>

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($kycDomCom->shareholders_passport_status))
                                                    @if($kycDomCom->shareholders_passport_status==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($kycDomCom->shareholders_passport_status==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="shareholders_passport_status" @if($kycDomCom->shareholders_passport_status == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="shareholders_passport_status" @if($kycDomCom->shareholders_passport_status == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                                <br>
                                                <div class="form-group" class="expiryDateDiv" style="display: none;">
                                                    <label for="exampleSelect1">Date of shareholders passport expiry</label>
                                                    <input type="text" id="shareholdersPassportExp" class="form-control  kt_datepicker_1 expiryDateClass"  @isset($kycDomCom->shareholdersPassportExp) value="{{$kycDomCom->shareholdersPassportExp}}" @endisset  name="shareholdersPassportExp" autocomplete="off" placeholder="Enter expiry date">
                                                </div>
                                            </div>
                                            <div class="col-5">
                                                <textarea placeholder="Please provide shareholders passport remark" class="form-control" name="shareholders_passport_remark">@if($kycDomCom->shareholders_passport_remark) {{ $kycDomCom->shareholders_passport_remark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="shareholders_passport" id="shareholders_passport" value="shareholders_passport">
                                               <a href="#" role="button" id="shareholders_passport_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            @endif
                            @endforeach
                            <tr>
                                <th>KYC form</th>
                                @if(isset($kycDomCom->kyc_form))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->kyc_form) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->kyc_form) }}" style="height:100px;width:100px;"  >-->
                                            @if(pathinfo($kycDomCom->kyc_form,
                                        PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->kyc_form,
                                            PATHINFO_EXTENSION) == 'docx' ||
                                            pathinfo($kycDomCom->kyc_form, PATHINFO_EXTENSION)
                                            == 'doc' || pathinfo($kycDomCom->kyc_form,
                                            PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->kyc_form,
                                            PATHINFO_EXTENSION) == 'xls' ||
                                            pathinfo($kycDomCom->kyc_form, PATHINFO_EXTENSION)
                                            == 'xlsx' || pathinfo($kycDomCom->kyc_form,
                                            PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}"
                                                    width="100%" height="auto">
                                            @elseif(pathinfo($kycDomCom->kyc_form,
                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                            pathinfo($kycDomCom->kyc_form, PATHINFO_EXTENSION)
                                            == 'jpg' || pathinfo($kycDomCom->kyc_form,
                                            PATHINFO_EXTENSION) == 'png')
                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->kyc_form) !!}"
                                                    width="100%" height="auto">
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                    height="auto">
                                            @endif

                                                </a>
                                            </div>

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($kycDomCom->kyc_form_status))
                                                    @if($kycDomCom->kyc_form_status==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($kycDomCom->kyc_form_status==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif
                                            </h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="kyc_form_status" @if($kycDomCom->kyc_form_status == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="kyc_form_status" @if($kycDomCom->kyc_form_status == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide kyc form remark" class="form-control" name="kyc_form_remark">@if($kycDomCom->kyc_form_remark) {{ $kycDomCom->kyc_form_remark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="kyc_form" id="kyc_form" value="kyc_form">
                                               <a href="#" role="button" id="kyc_form_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Data Protection form</th>
                                @if(isset($kycDomCom->data_protection_form))
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->data_protection_form) }}" target= "_blank">
                                       <!--      <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->data_protection_form) }}" style="height:100px;width:100px;"  >-->
                                            @if(pathinfo($kycDomCom->data_protection_form,
                                        PATHINFO_EXTENSION) == 'pdf')
                                                <img src="{{asset('images/pdf.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->data_protection_form,
                                            PATHINFO_EXTENSION) == 'docx' ||
                                            pathinfo($kycDomCom->data_protection_form, PATHINFO_EXTENSION)
                                            == 'doc' || pathinfo($kycDomCom->data_protection_form,
                                            PATHINFO_EXTENSION) == 'docm')
                                                <img src="{{asset('images/word.ico')}}" width="100%"
                                                    height="auto">
                                            @elseif(pathinfo($kycDomCom->data_protection_form,
                                            PATHINFO_EXTENSION) == 'xls' ||
                                            pathinfo($kycDomCom->data_protection_form, PATHINFO_EXTENSION)
                                            == 'xlsx' || pathinfo($kycDomCom->data_protection_form,
                                            PATHINFO_EXTENSION) == 'csv')
                                                <img src="{{asset('images/excel.png')}}"
                                                    width="100%" height="auto">
                                            @elseif(pathinfo($kycDomCom->data_protection_form,
                                            PATHINFO_EXTENSION) == 'jpeg' ||
                                            pathinfo($kycDomCom->data_protection_form, PATHINFO_EXTENSION)
                                            == 'jpg' || pathinfo($kycDomCom->data_protection_form,
                                            PATHINFO_EXTENSION) == 'png')
                                                <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($kycDomCom->data_protection_form) !!}"
                                                    width="100%" height="auto">
                                            @else
                                                <img src="{{asset('images/doc.png')}}" width="100%"
                                                    height="auto">
                                            @endif

                                                </a>
                                            </div>

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($kycDomCom->data_protection_form_status))
                                                    @if($kycDomCom->data_protection_form_status==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($kycDomCom->data_protection_form_status==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif</h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="data_protection_form_status" @if($kycDomCom->data_protection_form_status == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="data_protection_form_status" @if($kycDomCom->data_protection_form_status == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide data protection remark" class="form-control" name="data_protection_form_remark">@if($kycDomCom->data_protection_form_remark) {{ $kycDomCom->data_protection_form_remark }} @endif</textarea>
                                            </div>

                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="data_protection_form" id="data_protection_form" value="data_protection_form">
                                               <a href="#" role="button" id="data_protection_form_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete">
                                                <i class="la la-trash"></i>
                                               </a>
                                            </div>

                                        </div>
                                    </td>
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Compliance:</th>
                                @if($data->compliance == 2)
                                    <td style="color:red">Non-Compliant</td>
                                @elseif($data->compliance == 1)
                                    <td style="color:green">Compliance</td>
                                @elseif($data->compliance == 0)
                                    <td style="color:green">Pending Verification</td>
                                @elseif($data->compliance == 3)
                                    <td style="color:green">No ID - No Documents</td>
                                @else
                                    <td style="color:green">Status not found</td>
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
                                <th>Status:</th>
                                <td>
<!--                                    <select class="form-control kt_selectpicker"
                                            title="Please choose status"
                                            name="status">
                                        <option value="Approve" @if($data->status == 'Approve') selected @endif>Approved</option>
                                        <option value="Unapprove" @if($data->status == 'Unapprove') selected @endif>Unapproved</option>
                                    </select>-->
                                    <span>{{ $data->status }}</span>
                                </td>
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

                                @if($activePolicy > 0)
                                    <div class="row">
                                        <div class="col-5"></div>
                                        @if($data->compliance != 1)
                                            <div class="col-7">
                                                <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Update</button>
                                                <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                                <a class="btn btn-secondary" href="{{ URL::to('admin/customerKyc') }}" >Cancel</a>
                                            </div>
                                        @else
                                            <div class="col-7">
                                                @can('kyc-update')
                                                    <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Update</button>
                                                @endcan
                                                <a class="btn btn-secondary" href="{{ URL::to('admin/customerKyc') }}" >Back</a>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span style="color: #dc3545">Note : KYC cannot be updated as Policy for this Customer is Cancelled.</span>
                                @endif

                            </div>
                        </div>
                        </form>
                            <div>
                                <span style="display:inline;text-align:left"><a href="{{ route('admin.viewCustomerKycData', ($data->id)-1) }}" class="btn btn-brand">Previous</a></span>
                                <span style="display:inline;text-align:right"><a href="{{ route('admin.viewCustomerKycData', ($data->id)+1) }}" class="btn btn-brand">Next</a></span>
                            </div>
                            <!--end: Datatable -->
                    </div>
                </div>
            </div>
            <!-- end:: Content -->
        </div>

        {{-- ///////////////////////////////////////////////// --}}

            <div id="myModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form id="" action="{{ route('admin.customerKyc.delete',$data->id) }}"
                              method="POST" enctype="multipart/form-data" class="kt-form">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <input type="hidden" name="image_data" value="" id="image_data" />
                            <div class="modal-header">
                                <h3 class="modal-title" id="myModalLabel">Delete customer kyc</h3>
                                <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>

                            </div>
                            <div class="modal-body">
                                <p>Do you want to delete ?</p>
                            </div>
                            <div class="modal-footer">
                                <button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
                                <button class="btn btn-primary" type="submit">Delete</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        {{-- //////////////////////////////////////////////////// --}}
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

    $('#omang_front_delete').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#image_data').val('omang_front');
        $('#myModal').data('id', id).modal('show');
    });
    $('#omang_back_delete').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#image_data').val('omang_back');
        $('#myModal').data('id', id).modal('show');
    });
    $('#passport_delete').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#image_data').val('data_passport');
        $('#myModal').data('id', id).modal('show');
    });
    $('#drivers_license_delete').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#image_data').val('drivers_license');
        $('#myModal').data('id', id).modal('show');
    });
    $('#proof_residence_delete').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#image_data').val('proof_residence');
        $('#myModal').data('id', id).modal('show');
    });
    $('#proof_income_delete').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $('#image_data').val('proof_income');
        $('#myModal').data('id', id).modal('show');
    });


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
                    format: 'yyyy-mm-dd',
                    startDate: "today",
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
<script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}" type="text/javascript"></script>

<script>

    $(document).ready(function () {
        var omang = $('#omangExp').val();
        var omangStatus = $('input[name=omangFrontStatus]:checked').val();
        if (omangStatus == 1) {
            $('#omangExp').parent().css('display','block');
            $('#omangExp').attr('required','true');
        }

        var drivingLinc = $('#drivingLicExp').val();
        var drivingStatus = $('input[name=driving_licenseStatus]:checked').val();
        if (drivingStatus == 1) {
            $('#drivingLicExp').parent().css('display','block');
            $('#drivingLicExp').attr('required','true');
        }

        var passport = $('#passportExp').val();
        var passportStatus = $('input[name=passportStatus]:checked').val();
        if (passportStatus == 1) {
            $('#passportExp').parent().css('display','block');
            $('#passportExp').attr('required','true');
        }

        var directorsID = $('#directorsIDExp').val();
        var directorsIdFrontStatus = $('input[name=directors_id_front_status]:checked').val();
        if (directorsIdFrontStatus == 1) {
            $('#directorsIDExp').parent().css('display','block');
            $('#directorsIDExp').attr('required','true');
        }

        var directorsPassport = $('#directorsPassportExp').val();
        var directorsPassportStatus = $('input[name=directors_passport_status]:checked').val();
        if (directorsPassportStatus == 1) {
            $('#directorsPassportExp').parent().css('display','block');
            $('#directorsPassportExp').attr('required','true');
        }

        var shareholdersID = $('#shareholdersIDExp').val();
        var shareholdersIdFrontStatus = $('input[name=shareholders_id_front_status]:checked').val();
        if (shareholdersIdFrontStatus == 1) {
            $('#shareholdersIDExp').parent().css('display','block');
            $('#shareholdersIDExp').attr('required','true');
        }

        var shareholdersPassport = $('#shareholdersPassportExp').val();
        var shareholdersPassportStatus = $('input[name=shareholders_passport_status]:checked').val();
        if (shareholdersPassportStatus == 1) {
            $('#shareholdersPassportExp').parent().css('display','block');
            $('#shareholdersPassportExp').attr('required','true');
        }

    });

    function approveFun(val) {

        var t = $(val);
        if (val.value == 1) {
            t.parent().next().next().next().css('display','block');
            t.parent().next().next().next().find('.expiryDateClass').attr('required','true');
        }
    }


    function unapproveFun(val) {

        var t = $(val);
        if (val.value == 0) {
            t.parent().next().next().css('display','none');
            t.parent().next().next().find('.expiryDateClass').removeAttr('required','true');
        }
    }


    $("#updateCustomerKYC").validate({
        ignore: [],
        ignore: ":hidden",
            rules: {
                date_of_omangExpiry: {
                    required: true,
                },
                date_of_passportExpiry:{
                    required: true,
                },
                date_of_drivingLicenseExpiry:{
                    required: true,
                },
            },
            messages: {
                date_of_omangExpiry: {
                    required: "Please select Omang Expiry Date",
                },
                date_of_passportExpiry: {
                    required: "Please select Passport Expiry Date",
                },
                date_of_drivingLicenseExpiry: {
                    required: "Please select Driving License Expiry Date",
                },
            },
        });

</script>

<script>
    $('.dob').datepicker({
        rtl: KTUtil.isRTL(),
        todayHighlight: true,
        orientation: "bottom left",
        format: 'yyyy-mm-dd',
        endDate: "-18y",
       // endDate: "today",
        // startDate: "today",
    });

    $('.dob_with_limitation').datepicker({
        rtl: KTUtil.isRTL(),
        todayHighlight: true,
        orientation: "bottom left",
        format: 'dd-mm-yyyy',
        startDate: "-65y",
        endDate: "-18y",
    });

</script>

</body>
<!-- end::Body -->
</html>
