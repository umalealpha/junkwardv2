<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<style>
/* Banking Details Styling */
.banking-details-container {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin: 10px 0;
}

.banking-info-section {
    background: white;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.banking-field {
    padding: 8px 0;
}

.banking-label {
    display: block;
    font-weight: 600;
    color: #495057;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.banking-value {
    color: #212529;
    font-size: 14px;
    font-weight: 500;
    padding: 6px 0;
}

.account-number {
    font-family: 'Courier New', monospace;
    background: #e9ecef;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 13px;
}

.badge {
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 500;
}

.badge-primary {
    background-color: #007bff;
    color: white;
}

.badge-info {
    background-color: #17a2b8;
    color: white;
}

/* Document Review Section */
.document-review-section {
    margin-top: 20px;
}

.document-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: box-shadow 0.2s ease;
}

.document-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.document-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 12px 16px;
    border-radius: 8px 8px 0 0;
}

.document-title {
    margin: 0;
    color: #495057;
    font-size: 14px;
    font-weight: 600;
}

.document-title i {
    margin-right: 8px;
    color: #6c757d;
}

.document-content {
    padding: 16px;
    display: flex;
    gap: 16px;
}

.document-preview {
    flex: 0 0 80px;
    text-align: center;
}

.document-preview a {
    display: block;
    text-decoration: none;
}

.document-icon {
    width: 60px;
    height: 60px;
    object-fit: contain;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 8px;
    background: white;
}

.document-image {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.document-actions {
    flex: 1;
}

.status-section {
    margin-bottom: 12px;
}

.status-label {
    font-size: 12px;
    font-weight: 600;
    color: #6c757d;
    margin-bottom: 4px;
    display: block;
}

.status-badge {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-rejected {
    background-color: #dc3545;
    color: white;
}

.status-pending {
    background-color: #ffc107;
    color: #212529;
}

.radio-group {
    margin-bottom: 15px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.kt-radio {
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    padding: 10px 18px;
    border-radius: 25px;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
    background: #f8f9fa;
    min-width: 110px;
    text-align: center;
    white-space: nowrap;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    font-weight: 500;
}

.kt-radio:hover {
    background: #e9ecef;
    border-color: #ced4da;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.kt-radio input[type="radio"] {
    margin: 0;
    margin-right: 8px;
    width: 14px;
    height: 14px;
    accent-color: #007bff;
    flex-shrink: 0;
}

.kt-radio input[type="radio"]:checked + .radio-text {
    color: #007bff;
    font-weight: 600;
}

.kt-radio:has(input[type="radio"]:checked) {
    background: #e7f3ff;
    border-color: #007bff;
    color: #007bff;
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
}

.radio-text {
    margin: 0;
    color: #495057;
    font-weight: 500;
    font-size: 13px;
    line-height: 1.3;
    white-space: nowrap;
}


.remark-textarea {
    font-size: 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    resize: vertical;
    min-height: 60px;
}

.remark-textarea:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.no-document {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.no-document i {
    font-size: 48px;
    margin-bottom: 12px;
    display: block;
    color: #dee2e6;
}

.no-document span {
    font-size: 14px;
    font-weight: 500;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .banking-info-section .row {
        margin: 0;
    }
    
    .banking-info-section .col-md-4 {
        padding: 0 8px;
    }
    
    .document-content {
        flex-direction: column;
        gap: 12px;
    }
    
    .document-preview {
        flex: none;
        text-align: left;
    }
}
</style>


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
                        @if ($custPolicyNo->reject(function ($policy) {
                            return isset($policy->product_id) && in_array($policy->product_id, [7, 8]);
                            })->isNotEmpty())
                                @foreach ($custPolicyNo->reject(function ($policy) {
                                    return isset($policy->product_id) && in_array($policy->product_id, [7, 8]);
                                }) as $policy)
                                    <h5>Policy Number: {{$policy->policyNumber}}  ({{$policy->name}})
                                    </h5>
                                @endforeach
                        @else
                            <p>No policies available to display.</p>
                        @endif
                        @if(isset($rekycLink) && $rekycLink != null)
                                    <h5 style="color: {{ $rekycLink->status == 'completed' ? 'green' : 'blue' }}">Re-KYC Status : {{ $rekycLink->status }}</h5>
                        @endif
                        {{-- Sanctioned Countries Section --}}
                        @php
                            // Get KYC case and latest AML result for sanctioned countries
                            $kycCaseForCountries = \AlphaDirect\Models\KycCase::where('customer_id', $customer->id)->first();
                            $sanctionedCountries = [];
                        @endphp

                            @php
                            if ($kycCaseForCountries) {
                                $latestAmlResult = $kycCaseForCountries->amlResults()->latest()->first();
                                if ($latestAmlResult && isset($latestAmlResult->datasets) && is_array($latestAmlResult->datasets)) {
                                    $datasets = $latestAmlResult->datasets;

                                    // Process datasets dynamically with intelligent country name conversion
                                    foreach ($datasets as $dataset) {
                                        // Split dataset by underscore to get parts
                                        $parts = explode('_', $dataset);

                                        if (count($parts) >= 2) {
                                            $countryCode = strtolower($parts[0]);
                                            $agency = strtoupper($parts[1]);

                                            // Convert country code to proper name dynamically
                                            $countryName = ucwords(str_replace('_', ' ', $countryCode));

                                            // Format as "Country (Agency)"
                                            $sanctionedCountries[] = $countryName . ' (' . $agency . ')';
                                        } else {
                                            // Fallback for single part datasets
                                            $formatted = str_replace('_', ' ', $dataset);
                                            $sanctionedCountries[] = ucwords($formatted);
                                        }
                                    }

                                    // Remove duplicates
                                    $sanctionedCountries = array_unique($sanctionedCountries);
                                }
                            }
                        @endphp

                        <div class="row" style="margin-top: 10px;">
                            <div class="col-md-8">
                                @if(!empty($sanctionedCountries))
                                    <div style="padding: 8px 12px; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                                        <h6 style="color: #dc3545; margin: 0 0 8px 0; font-size: 14px; font-weight: 600;">
                                            <i class="la la-globe" style="margin-right: 6px; font-size: 12px;"></i>
                                            Sanctioned Countries/Regions
                                            <span class="kt-badge kt-badge--danger kt-badge--inline kt-badge--pill" style="margin-left: 8px; font-size: 10px; padding: 2px 6px;">
                                                {{ count($sanctionedCountries) }}
                                            </span>
                                        </h6>
                                        <div style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">
                                            @foreach($sanctionedCountries as $country)
                                                <span class="kt-badge kt-badge--warning kt-badge--inline kt-badge--pill"
                                                     style="font-size: 14px; padding: 6px 12px; margin-bottom: 4px;
                                                            border: 1px solid #ffc107;
                                                            background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
                                                            color: #212529; font-weight: 600;">
                                                        <i class="la la-flag" style="margin-right: 6px; font-size: 12px;"></i>
                                                        {{ $country }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-4">
                                <div style="text-align: right;">
                                    @if($data != null && $data->compliance == 1)
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

                                    {{-- Last Scan Badge --}}
                                    @php
                                        // Get any KYC case for this customer (not just sanctioned ones)
                                        $kycCase = \AlphaDirect\Models\KycCase::where('customer_id', $customer->id)->first();

                                        // Get the latest AML result for last scan timestamp
                                        $latestAmlResult = null;
                                        if ($kycCase) {
                                            $latestAmlResult = $kycCase->amlResults()->latest()->first();
                                        }

                                        // Check if customer is sanctioned
                                        $sanctionedCase = \AlphaDirect\Models\KycCase::where('customer_id', $customer->id)
                                            ->where('sanctions_max', '>', 0)
                                            ->first();
                                    @endphp

                                    @if($latestAmlResult)
                                        <h5 style="margin-top: 10px;">Last Scan: <span class="kt-badge kt-badge--info kt-badge--inline kt-badge--pill" style="font-size:15px">
                                            <i class="la la-clock"></i> {{ $latestAmlResult->created_at->format('M d, Y H:i') }}
                                        </span></h5>
                                    @endif

                                    {{-- Sanctioned Badge --}}

                                    @if($sanctionedCase)
                                        <h5 style="margin-top: 10px;">Sanctions Status: <span class="kt-badge kt-badge--danger kt-badge--inline kt-badge--pill" style="font-size:15px">
                                            <i class="la la-shield"></i> Sanctioned
                                        </span></h5>
                                    @endif
                                </div>
                            </div>
                        </div>
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
                            <button style="padding-bottom: 0px; margin-bottom: 15px; padding-top: 4px; height: 42px;"
                                type="button" style="margin-top: 0px; margin-left: 6px;" id="runOpenSanctions" class="btn btn-danger">
                                <i class="la la-shield"></i> Run OpenSanctions Check
                            </button>
                        </div>

                        <!--begin: Datatable -->
                        <!-- begin:: Content -->
                        <form id="updateCustomerKYC" action="{{ route('admin.customer.verifyKYCInfo') }}"
                              method="POST" enctype="multipart/form-data" class="kt-form">
                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <input type="hidden" name="data_id" value="{{ $data->id }}" />
                        <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                            <thead>
                            <tr>
                                <th>Customer Name:</th>
                                <td>{{ ucwords($customer->firstName.' '.$customer->middleName.' '.$customer->lastName) }}</td>
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
                            <tr>
                                <th>Omang Front: <br>Omang: {{ $customer->omang }} <br>Expiry: {{ ($data->omangExpiry) ? $data->omangExpiry : '-' }} </th>
                                @if($data->omang)
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
                                @if($data->omangBack)
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
                                @if($data->passport)
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
                                <th>Drivers License <br>Expiry: {{ ($data->licenseExpiry) ? $data->licenseExpiry : '-' }}</th>
                                @if($data->driving_license)
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->driving_license) }}" target= "_blank">
                                    {{--   <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->driving_license) }}" style="height:100px;width:100px;"  >--}}
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

                                            <div class="col-3">
                                                <h6>Update Document Status:
                                                @if(isset($data->driving_licenseStatus))
                                                    @if($data->driving_licenseStatus==0)
                                                    <span style="color:red;">Rejected</span>
                                                    @elseif($data->driving_licenseStatus==2)
                                                    <span style="color:blue;">Recheck</span>
                                                    @endif
                                                @endif
                                                </h6>
                                                <label class="kt-radio">
                                                    <input type="radio" name="driving_licenseStatus" @if($data->driving_licenseStatus == '1') checked @endif class="premiumField condition" onchange="approveFun(this)"
                                                           value="1">Approve<span></span>
                                                </label>
                                                <label class="kt-radio" style="margin-left: 10px;">
                                                    <input type="radio" name="driving_licenseStatus" @if($data->driving_licenseStatus == '0') checked @endif class="premiumField condition" onchange="unapproveFun(this)"
                                                           value="0">Unapprove<span></span>
                                                </label>
                                                <br>
                                                <div class="form-group" class="expiryDateDiv" style="display: none;">
                                                    <label for="exampleSelect1">Date of driving license expiry</label>
                                                    <input type="text" id="drivingLicExp" class="form-control  kt_datepicker_1 expiryDateClass"  @isset($data->licenseExpiry) value="{{$data->licenseExpiry}}" @endisset  name="date_of_drivingLicenseExpiry" autocomplete="off" placeholder="Enter expiry date">
                                                </div>
                                            </div>

                                            <div class="col-5">
                                                <textarea placeholder="Please provide driving license remark" class="form-control" name="driving_licenseRemark">@if($data->driving_licenseRemark) {{ $data->driving_licenseRemark }} @endif</textarea>
                                            </div>
                                            <div class="col-1" style="text-align: end;">
                                                <input type="hidden" name="drivers_license" id="drivers_license" value="drivers_license">
                                                <a href="#" role="button" id="drivers_license_delete" style="background-color:#dc3545;padding-right: 7px" class="btn btn-danger" data-id="1" data-toggle="modal"  title="Delete"> <i class="la la-trash"></i></a>
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
                                @if($data->proof_income)
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

                            <tr>
                                <th>Canceled Document:</th>
                                @if($data->Canceled_document)
                                    <td>
                                        <div class="row">
                                            <div class="col-3">
                                                <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->Canceled_document) }}" target= "_blank">
<!--                                                    <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->proof_income) }}" style="height:100px;width:100px;"  >-->
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

                                            <div class="col-3">

                                            </div>

                                            <div class="col-5">
                                                 </div>
                                            <div class="col-1" style="text-align: end;">
                                                 </div>
                                        </div>
                                    </td>
{{--                                    <td><a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($data->Canceled_document) }}" target= "_blank"> Proof Of Income Picture Download link </a></td>--}}
                                @else
                                    <td>Not Uploaded</td>
                                @endif
                            </tr>

                            {{-- Banking Details and Documents --}}
                            @if(isset($customerBanking) && $customerBanking != null)
                            <tr>
                                <th>Banking Details: </th>
                                <td>
                                    <div class="banking-details-container">
                                        {{-- Banking Details --}}
                                        <div class="banking-info-section">
                                            <div class="row">
                                                <div class="col-md-4 col-sm-6 mb-3">
                                                    <div class="banking-field">
                                                        <label class="banking-label">Billing Method</label>
                                                        <div class="banking-value">{{ $customerBanking->billing ?? 'N/A' }}</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 col-sm-6 mb-3">
                                                    <div class="banking-field">
                                                        <label class="banking-label">Billing Cell</label>
                                                        <div class="banking-value">{{ $customerBanking->billingCell ?? 'N/A' }}</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 col-sm-6 mb-3">
                                                    <div class="banking-field">
                                                        <label class="banking-label">Account Type</label>
                                                        <div class="banking-value">
                                                            @if(isset($customerBanking->accountType) && $customerBanking->accountType == 1)
                                                                <span class="badge badge-primary">Cheque</span>
                                                            @else
                                                                <span class="badge badge-info">Savings</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 col-sm-6 mb-3">
                                                    <div class="banking-field">
                                                        <label class="banking-label">Bank Name</label>
                                                        <div class="banking-value">
                                                            @if($customerBanking->billing == "RealPay")
                                                                {{ $customerBanking->bank_name ?? $customerBanking->bankName ?? 'N/A' }}
                                                            @else
                                                                {{ $customerBanking->bankName ?? 'N/A' }}
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 col-sm-6 mb-3">
                                                    <div class="banking-field">
                                                        <label class="banking-label">Branch Name</label>
                                                        <div class="banking-value">
                                                            @if($customerBanking->billing == "RealPay")
                                                                {{ $customerBanking->bank_branch ?? $customerBanking->branchCode ?? 'N/A' }}
                                                            @else
                                                                {{ $customerBanking->branchCode ?? 'N/A' }}
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 col-sm-6 mb-3">
                                                    <div class="banking-field">
                                                        <label class="banking-label">Account Number</label>
                                                        <div class="banking-value account-number">{{ $customerBanking->accountNumber ?? 'N/A' }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        {{-- Document Review Section --}}
                                        <div class="document-review-section">
                                            <div class="row">
                                                {{-- Bank Statement Document --}}
                                                <div class="col-md-6">
                                                    <div class="document-card">
                                                        <div class="document-header">
                                                            <h6 class="document-title">
                                                                <i class="la la-file-text"></i> Bank Statement
                                                            </h6>
                                                        </div>
                                                        @if($data->bank_statement_file_path)
                                                            <div class="document-content">
                                                                <div class="document-preview">
                                                                    <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($data->bank_statement_file_path) }}" target="_blank">
                                                                        @php $ext = pathinfo($data->bank_statement_file_path, PATHINFO_EXTENSION); @endphp
                                                                        @if(strtolower($ext) == 'pdf')
                                                                            <img src="{{asset('images/pdf.ico')}}" class="document-icon">
                                                                        @elseif(in_array(strtolower($ext), ['doc','docx','docm']))
                                                                            <img src="{{asset('images/word.ico')}}" class="document-icon">
                                                                        @elseif(in_array(strtolower($ext), ['xls','xlsx','csv']))
                                                                            <img src="{{asset('images/excel.png')}}" class="document-icon">
                                                                        @elseif(in_array(strtolower($ext), ['jpeg','jpg','png']))
                                                                            <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->bank_statement_file_path) }}" class="document-image">
                                                                        @else
                                                                            <img src="{{asset('images/doc.png')}}" class="document-icon">
                                                                        @endif
                                                                    </a>
                                                                </div>
                                                                <div class="document-actions">
                                                                    <div class="status-section">
                                                                        <label class="status-label">Document Status:</label>
                                                                        @if(isset($data->bankStatementFileStatus))
                                                                            @if($data->bankStatementFileStatus==0)
                                                                                <span class="status-badge status-rejected">Rejected</span>
                                                                            @elseif($data->bankStatementFileStatus==2)
                                                                                <span class="status-badge status-pending">Recheck</span>
                                                                            @endif
                                                                        @endif
                                                                    </div>
                                                                    <div class="radio-group">
                                                                        <label class="kt-radio">
                                                                            <input type="radio" name="bankStatementFileStatus" @if($data->bankStatementFileStatus == '1') checked @endif class="premiumField condition" value="1">
                                                                            <span class="radio-text">Approve</span>
                                                                            <span></span>
                                                                        </label>
                                                                        <label class="kt-radio">
                                                                            <input type="radio" name="bankStatementFileStatus" @if($data->bankStatementFileStatus == '0') checked @endif class="premiumField condition" value="0">
                                                                            <span class="radio-text">Reject</span>
                                                                            <span></span>
                                                                        </label>
                                                                    </div>
                                                                    <textarea placeholder="Bank statement remark" class="form-control remark-textarea" name="bankStatementFileRemark" rows="2">@if($data->bankStatementFileRemark) {{ $data->bankStatementFileRemark }} @endif</textarea>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div class="no-document">
                                                                <i class="la la-file-o"></i>
                                                                <span>No document uploaded</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                                
                                                {{-- Debit Authorization Form Document --}}
                                                <div class="col-md-6">
                                                    <div class="document-card">
                                                        <div class="document-header">
                                                            <h6 class="document-title">
                                                                <i class="la la-file-text"></i> Debit Authorization Form
                                                            </h6>
                                                        </div>
                                                        @if($data->debit_authorization_form)
                                                            <div class="document-content">
                                                                <div class="document-preview">
                                                                    <a href="{{ \AlphaDirect\Helper::getCloudFrontURL($data->debit_authorization_form) }}" target="_blank">
                                                                        @php $ext = pathinfo($data->debit_authorization_form, PATHINFO_EXTENSION); @endphp
                                                                        @if(strtolower($ext) == 'pdf')
                                                                            <img src="{{asset('images/pdf.ico')}}" class="document-icon">
                                                                        @elseif(in_array(strtolower($ext), ['doc','docx','docm']))
                                                                            <img src="{{asset('images/word.ico')}}" class="document-icon">
                                                                        @elseif(in_array(strtolower($ext), ['xls','xlsx','csv']))
                                                                            <img src="{{asset('images/excel.png')}}" class="document-icon">
                                                                        @elseif(in_array(strtolower($ext), ['jpeg','jpg','png']))
                                                                            <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($data->debit_authorization_form) }}" class="document-image">
                                                                        @else
                                                                            <img src="{{asset('images/doc.png')}}" class="document-icon">
                                                                        @endif
                                                                    </a>
                                                                </div>
                                                                <div class="document-actions">
                                                                    <div class="status-section">
                                                                        <label class="status-label">Document Status:</label>
                                                                        @if(isset($data->debitAuthFileStatus))
                                                                            @if($data->debitAuthFileStatus==0)
                                                                                <span class="status-badge status-rejected">Rejected</span>
                                                                            @elseif($data->debitAuthFileStatus==2)
                                                                                <span class="status-badge status-pending">Recheck</span>
                                                                            @endif
                                                                        @endif
                                                                    </div>
                                                                    <div class="radio-group">
                                                                        <label class="kt-radio">
                                                                            <input type="radio" name="debitAuthFileStatus" @if($data->debitAuthFileStatus == '1') checked @endif class="premiumField condition" value="1">
                                                                            <span class="radio-text">Approve</span>
                                                                            <span></span>
                                                                        </label>
                                                                        <label class="kt-radio">
                                                                            <input type="radio" name="debitAuthFileStatus" @if($data->debitAuthFileStatus == '0') checked @endif class="premiumField condition" value="0">
                                                                            <span class="radio-text">Reject</span>
                                                                            <span></span>
                                                                        </label>
                                                                    </div>
                                                                    <textarea placeholder="Debit authorization form remark" class="form-control remark-textarea" name="debitAuthFileRemark" rows="2">@if($data->debitAuthFileRemark) {{ $data->debitAuthFileRemark }} @endif</textarea>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div class="no-document">
                                                                <i class="la la-file-o"></i>
                                                                <span>No document uploaded</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endif

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

    // OpenSanctions Check functionality
    $('#runOpenSanctions').on('click', function() {
        if (confirm('Are you sure you want to run OpenSanctions check for this customer?')) {
            runOpenSanctionsCheck();
        }
    });

    function runOpenSanctionsCheck() {
        var button = $('#runOpenSanctions');
        var originalText = button.html();

        // Disable button and show loading
        button.prop('disabled', true).html('<i class="la la-spinner la-spin"></i> Running Check...');

        $.ajax({
            url: "{{ route('admin.kyc.run-opensanctions', $data->customer_id) }}",
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    alert('OpenSanctions check completed successfully!\n\n' +
                          'Max Score: ' + response.max_score + '\n' +
                          'Status: ' + response.status + '\n' +
                          'Datasets Count: ' + response.datasets_count);
                } else {
                    alert('Error: ' + (response.message || 'Unknown error occurred'));
                }
            },
            error: function(xhr) {
                var errorMessage = 'An error occurred while running OpenSanctions check.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                alert('Error: ' + errorMessage);
            },
            complete: function() {
                // Re-enable button
                button.prop('disabled', false).html(originalText);
            }
        });
    }


</script>

</body>
<!-- end::Body -->
</html>
