<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')
<!------>
<link href="{{ asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet"
    type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}"
    rel="stylesheet" type="text/css" />
<link href="https://cdn.datatables.net/buttons/1.6.0/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css" />
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.css">
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.css">
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<!------>
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
                        Edit Quote
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Quotes</span> </a>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit Quote</span> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->



            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">

                <div class="row ol-lg-12">
                    <div class=" col-lg-3 text-left policyno">
                        <h5> Quote No<span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"  style="font-size:15px"> # {{$data->quoteNumber}}</span></h5> <br>
                    </div>

                    <div class=" col-lg-2 text-left policyno">
                        @if($agent != null)
                            <h5> Agent: {{ ucwords(strtolower($agent->firstName.' '.$agent->lastName)) }} <br>
                                @else
                                    <h5> Agent: N/A <br>
                        @endif

                    </div>

                    <div class=" col-lg-2 text-left policyno">
                        <h5> Status:
                            @if($data->quote_status == 2)
                                <span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill"  style="font-size:15px"> Used</span>@if($policyNumber && $policyNumber->policyNumber != null) &nbsp({{ $policyNumber->policyNumber }})  @else &nbsp @endif <br>
                            @elseif($data->quote_status == 3)
                                <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"  style="font-size:15px"> Expired</span> <br>
                            @elseif($data->quote_status == 1)
                                <span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"  style="font-size:15px"> Active</span> <br>
                            @elseif($data->quote_status == 4)
                                <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"  style="font-size:15px"> Rejected</span> <br>
                            @else
                                <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"  style="font-size:15px"> Status not found</span> <br>
                        @endif
                    </div>


                    <div class=" col-lg-2 text-left policyno">
                        @if($data->quote_status != 2)
                            <h5> Expire on: {{$expiryDate}} <br>
                                @else
                                    <h5> Expire on: - <br>
                        @endif
                    </div>
                    @if($data->quote_status == 1)
                        <div class="col-lg-3 kt-subheader__wrapper">
                            <a href="{{ route('quote.download',[$data->quoteCode,'010']) }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Send quote in email to customer"> <span class="kt-opacity-11" id="">Send to customer</span>&nbsp; <i class="flaticon-attachment kt-padding-l-5 kt-padding-r-0"></i> </a>
                            <a href="{{ route('quote.download',[$data->quoteCode,'001']) }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Send quote in email to agent"> <span class="kt-opacity-11" id="">Send to agent</span>&nbsp; <i class="flaticon-attachment kt-padding-l-5 kt-padding-r-0"></i> </a>
                        </div>
                    @endif
                </div>
                <br>

                <div class="kt-portlet kt-portlet--mobile">
                    <form id="updateCustomerKYC" action="{{ route('quote.getUpdatedPremium') }}"
                          method="POST" enctype="multipart/form-data" class="kt-form updateCustomerKYC">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <input type="hidden" id="quoteCode" name="quoteCode" value="{{ $data->quoteCode }}" />
                        {{-- <input type="hidden" id="discSurAdd" name="discSurAdd" value="" /> --}}
                        @if ($data->discount_surcharge != null && $data->discount_surcharge != 0)
                            <input type="hidden" id="discountSurchargeAdded" name="discountSurchargeAdded" value="{{ $data->discount_surcharge }}" />
                            <input type="hidden" id="discountSurchargePerAdded" name="discountSurchargePerAdded" value="{{ $data->percent_discount_surcharge }}" />
                        @endif
                       <div class="kt-portlet__body">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Customer Details
                                    <span style="float: right">
                                <a href="{{ route('quote.download',[$data->quoteCode,'100']) }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Download"> <span class="kt-opacity-11" id="">Download</span>&nbsp; <i class="flaticon-download-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                                    </span>
                                </h3>
                            </div>
                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                                <thead>
                                <tr>
                                    <th>First Name:</th>
                                    <td width="50%">
                                        @if($data && $data->firstName != NULL)
                                        <input type="text" class="form-control"  name="first_name" title="First name is required" value="{{ ucwords(strtolower($data->firstName)) }}" placeholder="Enter first name">
                                        @else
                                        <input type="text" class="form-control"  name="first_name" title="First name is required" placeholder="Enter first name">
                                        @endif
                                    </td>
                                    {{-- <td width="50%">{{ ucwords(strtolower($data->firstName.' '.$data->middleName.' '.$data->lastName)) }}</td> --}}
                                </tr>
                                <tr>
                                    <th>Middle Name:</th>
                                    <td width="50%">
                                        @if($data && $data->middleName != NULL)
                                        <input type="text" class="form-control"  name="middle_name" value="{{ ucwords(strtolower($data->middleName)) }}" placeholder="Enter middle name">
                                        @else
                                        <input type="text" class="form-control"  name="middle_name" placeholder="Enter middle name">
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Last Name:</th>
                                    <td width="50%">
                                        @if($data && $data->lastName != NULL)
                                        <input type="text" class="form-control" name="last_name" title="Last name is required" value="{{ ucwords(strtolower($data->lastName)) }}"  placeholder="Enter last name">
                                        @else
                                        <input type="text" class="form-control" name="last_name" title="Last name is required"  placeholder="Enter last name">
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Omang Number:</th>
                                    <td width="50%">
                                        @if($data->omang)
                                            <input type="text" class="form-control" name="omang" pattern="[0-9]{9}" maxlength="9"  value="{{ $data->omang }}" placeholder="Enter Omang">
                                        @else
                                            <input type="text" class="form-control" name="omang" pattern="[0-9]{9}" maxlength="9" placeholder="Enter Omang">
                                        @endif
                                </td>
                                </tr>
                                <tr>
                                    <th>Passport Number:</th>
                                    <td width="50%">
                                        @if($data->passport != NULL)
                                            <input type="textarea" class="form-control" name="passport" maxlength="12" value="{{ $data->passport }}" placeholder="Enter passport number">
                                        @else
                                            <input type="textarea" class="form-control" name="passport" maxlength="12" placeholder="Enter passport number">
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td width="50%">
                                        @if($data->email != NULL)
                                        <input type="text" class="form-control" name="email"  value="{{ $data->email }}"placeholder="Enter email">
                                        @else
                                        <input type="text" class="form-control" name="email" placeholder="Enter email">                                    @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Cellphone:</th>
                                    <td width="50%">
                                        @if($data->cellphone != NULL)
                                        <input type="text" class="form-control" name="mobile" value="{{ $data->cellphone }}" pattern="[0-9]{1,25}" maxlength="8"  placeholder="Enter mobile no.">
                                        @else
                                        <input type="text" class="form-control" name="mobile" pattern="[0-9]{1,25}" maxlength="8"  placeholder="Enter mobile no.">
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Gender:</th>
                                    <td width="50%">
                                        <select class="form-control required" name="gender">
                                            <option value="">Please Select</option>
                                            <option value="0" @if($data->gender == 0) selected @endif>Female</option>
                                            <option value="1" @if($data->gender == 1) selected @endif>Male</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Date Of Birth:</th>
                                    @if($data->dob)
                                        <td width="50%">
                                                <input type="text" class="form-control kt_datepicker_1 dob" name="dob" id="dob" autocomplete="off" value="{{ $data->dob }}" data-date-end-date="-18y" placeholder="Select date"/>
                                        </td>
                                    @else
                                        <td width="50%">N/A</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Marital Status:</th>
                                    <td>
                                    <select class="form-control required" name="marital">
                                        <option value="">Please Select</option>
                                        <option value="1" @if($data->maritalstatus == 1) selected @endif>Single</option>
                                        <option value="2" @if($data->maritalstatus == 2) selected @endif>Married</option>
                                        <option value="3" @if($data->maritalstatus == 3) selected @endif>Divorced</option>
                                        <option value="4" @if($data->maritalstatus == 4) selected @endif>Widowed</option>
                                        <option value="5" @if($data->maritalstatus == 5) selected @endif>Living Together</option>
                                        <option value="6" @if($data->maritalstatus == 6) selected @endif>Living Separately</option>
                                    </select>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Store Name:</th>
                                    @if($storeName != null)
                                        <td width="50%">{{ $storeName }}</td>
                                    @else
                                        <td width="50%">-</td>
                                    @endif
                                </tr>

                                <tr>
                                    <th>User IP Address:</th>
                                    @if($data->userIPAddress)
                                        <td width="50%">{{ $data->userIPAddress }}</td>
                                    @else
                                        <td width="50%">N/A</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Quote Sent:</th>
                                    @if($data->quoteSent == 1)
                                        <td width="50%" style="color: green">Yes</td>
                                    @else
                                        <td width="50%" style="color: red">No</td>
                                    @endif
                                </tr>
                                </thead>
                            </table>
                         <!--end: Datatable -->
                        </div>
                        <div class="kt-portlet__body">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Product Details</h3>
                            </div>
                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table2">
                                <thead>
                                <tr>
                                    <th>Product:</th>
                                    <td width="50%">{{ $data->name }}</td>
                                </tr>
                                <tr>
                                    <th>Product Plan:</th>
                                    <td width="50%">{{ $data->plan_name }}</td>
                                </tr>

                                <tr>
                                    <th>Sum Insured:</th>
                                    <td width="50%">P {{ $data->estimatedValue }}</td>
                                </tr>

                                </thead>
                            </table>
                            <!--end: Datatable -->
                        </div>

                        <div class="kt-portlet__body">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Vehicle Details</h3>
                            </div>
                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table3">
                                <thead>
                                <tr>
                                    <th>Japenese Import:{{$data->is_imported }}</th>
                                    <td width="50%">
                                        <select class="form-control kt_selectpicker" name="is_imported" title="Please select type" data-live-search="true" id="is_imported">
                                            <option value="Yes" @if($data->is_imported == 'Yes') selected @endif>Yes</option>
                                            <option value="No" @if($data->is_imported == 'No') selected @endif>No</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Make:</th>
                                    <td width="50%">
                                        <select class="form-control " name="make" title="Please select type" data-live-search="true" id="make">
                                            <option value="">Please Select make</option>
                                            @foreach($dataMake as $key=>$make)
                                                @if($data->is_imported == 'Yes')
                                                    @if ($data->make == $make->s_Make)
                                                        <option value="{{ $make->s_Make }}" @if($data->make == $make->s_Make) selected @endif>{{ ucwords(strtolower($make->s_Make)) }}</option>
                                                    @else
                                                        <option value="{{ $make->s_Make }}">{{ ucwords(strtolower($make->s_Make)) }}</option>
                                                    @endif
                                                @endif
                                                @if($data->is_imported == 'No')
                                                    @if ($data->make == $make)
                                                        <option value="{{ $make }}" @if($data->make == $make) selected @endif>{{ ucwords(strtolower($make)) }}</option>
                                                    @else
                                                        <option value="{{ $make }}">{{ ucwords(strtolower($make)) }}</option>
                                                    @endif
                                                @endif
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th>Model:</th>
                                    <td width="50%">
                                        <select class="form-control " name="model" title="Please select vehicle model" data-live-search="true" id="modelm">
                                            <option value="" @if($data->model == '') selected @endif>Please Select model</option>
                                            @if($data->is_imported == 'Yes')
                                            @foreach($dataModel as $key=>$model)
                                                @if ($data->model == $model['model'])
                                                    <option value="{{ $model['model'] }}" @if($data->model == $model['model']) selected @endif>{{ ucwords(strtolower($model['model'])) }}</option>
                                                @else
                                                    <option value="{{ $model['model'] }}">{{ ucwords(strtolower($model['model'])) }}</option>
                                                @endif
                                            @endforeach
                                                <option value="other">Other</option>
                                            @endif

                                                @if($data->is_imported == 'No')
                                                
                                            @foreach($dataModel as $key=>$model)
                                                @if ($data->model == $model['Model'])
                                                    <option value="{{ $model['Model'] }}" @if($data->model == $model['Model']) selected @endif>{{ ucwords(strtolower($model['Model'])) }}</option>
                                                @else
                                                    <option value="{{ $model['Model'] }}">{{ ucwords(strtolower($model['Model'])) }}</option>
                                                @endif
                                            @endforeach
                                            <option value="other" @if($data->other_model == '1') selected @endif >Other</option>
                                                    @endif
                                        </select>
                                    </td>
                                </tr>
                                <tr id="model_other_div" style="display: none">
                                    <th>Other Model:</th>
                                    <td width="50%">
                                        <input type="text" class="form-control model_other" id="model_other" name="model_other" autocomplete="off" value="" placeholder="Please enter other Model"  style="text-transform:uppercase">
                                    </td>
                                </tr>
                                <tr>
                                    <th>Manufacturing Year:</th>
                                    <td width="50%">
                                        <select class="form-control " name="year" title="Please select manufacturing year" data-live-search="true" id="year">
                                            <?php
                                            for($i = date("1990"); $i < date("Y")+1; $i++){
                                                echo "<option>" . $i . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="model_Variant_div" @if($data->is_imported == 'Yes') style="display: none" @endif>
                                    <th>Variant:</th>
                                    <td width="50%">
                                    <select class=" form-control   select-1" id="variant" name="variant" >
                                    <option value="">Select Variant</option>
                                    @if($data->is_imported == 'No')
                                    <option value="{{ $data->variant }}" selected>{{$data->variant}}</option>
                                    @endif

                                       
                                </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Estimated value of vehicle:</th>
                                    <td width="50%">
                                        <span class="spinner-border spinner-border-sm" id="valueLoader"></span>
                                        <input type="text" class="form-control" name="estimatedValue" id="estimatedValue" title="Enter estimated value" value="{{ $data->estimatedValue }}" placeholder="Enter estimated value"/>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Number of prior accidents:</th>
                                    <td width="50%">
                                        <input type="text" class="form-control"  min="0" max="3" name="prior_accidents" title="Enter number of prior accidents" value="{{ $data->priorAccidents }}" placeholder="Enter number of prior accidents"/>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Condition:</th>
                                    <td width="50%">Excellent</td>
                                </tr>
                                <tr>
                                    <th>Mileage:</th>
                                    <td width="50%">Low</td>
                                </tr>
                                <tr>
                                    <th>Purpose:</th>
                                    <td width="50%">Personal</td>
                                </tr>

                                </thead>
                            </table>
                            <!--end: Datatable -->
                        </div>
                        <div class="kt-portlet__body">
                            <h3 class="kt-portlet__head-title" style="display:inline">Premium Calculation Details</h3>
                            @can('quotes-Update Premium')
                                <div class="kt-portlet__head-label">
                                    <a href="{{ route('quote.perDayPremium',$data->quoteNumber) }}" target="_blank" style="margin-left:1%;display:inline;float:right;" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Calculate per day premium"> <span class="kt-opacity-11" id="">Calculate Per Day Premium</span>&nbsp; <i class="flaticon-edit kt-padding-l-5 kt-padding-r-0"></i> </a>
                                    <a href="{{ route('quote.viewHistory',$data->quoteNumber) }}" target="_blank" style="margin-left:1%;display:inline;float:right;" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Edit & Update History"> <span class="kt-opacity-11" id="">Premium Update History</span>&nbsp; <i class="flaticon-edit kt-padding-l-5 kt-padding-r-0"></i> </a>
                                    @if($data->quote_status == 1)
                                        <p style="margin-left:1%;display:inline;float:right;" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="edit-premium-button" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Re-rate premium"> <span class="kt-opacity-11" id="">Edit Premium</span>&nbsp; <i class="flaticon-edit kt-padding-l-5 kt-padding-r-0"></i> </p>
                                    @endif
                                    @can('quotes-custom rate premium')
                                        <p style="margin-left:1%;display:inline;float:right;" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="edit-custom-rate-button" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Re-customrate premium"> <span class="kt-opacity-11" id="">Apply Custom Rate</span>&nbsp; <i class="flaticon-edit kt-padding-l-5 kt-padding-r-0"></i> </p>
                                    @endcan
                                </div>
                            @endcan
                            <div style="margin-bottom:2%" id="rerate_div">
                                {{--                            <form id="updateCustomerKYC" action="{{ route('quote.updatePremium',$data->quoteNumber) }}"--}}
                                {{--                                  method="POST" enctype="multipart/form-data" class="kt-form">--}}
                                {{--                                <!-- CSRF Token -->--}}
                                {{--                                <input type="hidden" name="_token" value="{{ csrf_token() }}" />--}}
                                    <table class="table table-striped table-bordered table-hover table-checkable">
                                        <thead>
                                        <tr>
                                            <th>Please select type:</th>
                                            <td width="50%">
                                                <select class="form-control kt_selectpicker" name="type" title="Please select type" data-live-search="true" id="type">
                                                    <option value="discount">Discount</option>
                                                    <option value="surcharge">Surcharge</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Please select value type:</th>
                                            <td width="50%">
                                                <select class="form-control kt_selectpicker" name="value_type" title="Please select value type" data-live-search="true" id="value_type">
                                                    <option value="1">Flat value</option>
                                                    <option value="2">Percent(%) value</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Value:</th>
                                            <td width="50%">
                                                <input type="text" class="form-control" name="value" id="value" title="Enter the value" placeholder="Enter value"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Reason:</th>
                                            <td width="50%">
                                                <input type="text" class="form-control reason" name="reason" id="reason" title="Please provide the reason" placeholder="Please provide reason"/>
                                            </td>
                                        </tr>
                                        </thead>
                                    </table>
                                    <div class="kt-form__actions">
                                        <div class="row">
                                            <div class="col-5"></div>
                                            <div class="col-7">
                                                <p id="submitRerate" class="btn btn-info">Submit</p>
                                                <p class="btn btn-secondary" id="rerate_hide">Cancel</p>
                                            </div>
                                        </div>
                                    </div>
                                     {{-- </form>--}}
                            </div>

                            <div style="margin-bottom:2%" id="custom_rate_div">
                                    <table class="table table-striped table-bordered table-hover table-checkable">
                                        <thead>
                                            <tr>
                                                <th>Please select value type:</th>
                                                <td width="50%">
                                                    <select class="form-control kt_selectpicker" name="rate_value_type"
                                                        title="Please select value type" data-live-search="true"
                                                        id="cus_dis_sur_rate_value_type">
                                                        <option value="1">Flat value</option>
                                                        <option value="2">Percent(%) value</option>
                                                    </select>
                                                    <span id="cus_dis_sur_value_typeError" class="error"
                                                        style="display: none; font-size: 12px; color:red;">This field is
                                                        required.</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Value:</th>
                                                <td width="50%">
                                                    <input type="text" class="form-control" name="value" id="rate_value" title="Enter the value" placeholder="Enter value"/>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Reason:</th>
                                                <td width="50%">
                                                    <input type="text" class="form-control reason" name="reason" id="rate_reason" title="Please provide the reason" placeholder="Please provide reason"/>
                                                </td>
                                            </tr>
                                        </thead>
                                    </table>
                                    <div class="kt-form__actions">
                                        <div class="row">
                                            <div class="col-5"></div>
                                            <div class="col-7">
                                                <p id="submitCustomRate" class="btn btn-info">Submit</p>
                                                <p class="btn btn-secondary" id="rate_hide">Cancel</p>
                                            </div>
                                        </div>
                                    </div>
                            </div>

                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table3">
                                <thead>
                                <tr>
                                    <th>Ratings Calculation Log ID:</th>
                                    <td width="50%">@if($data->ratings_id) {{ $data->ratings_id }} @else N/A @endif</td>
                                </tr>
                                <tr>
                                    <th>Monthly:</th>
                                    <td width="50%">P {{ $data->premiumMonthly }}</td>
                                </tr>
                                <tr>
                                    <th>3 Instalments:</th>
                                    <td width="50%">P {{ $data->premium3Inst }}</td>
                                </tr>
                                <tr>
                                    <th>Annual:</th>
                                    <td width="50%">P {{ $data->premiumAnnually }}</td>
                                </tr>
                                <tr>
                                    <th>Discount/Surcharge:</th>
                                    @if($data->discount_surcharge != null && $data->discount_surcharge != 0)
                                        <td width="50%">P {{ $data->discount_surcharge.' ('. $data->percent_discount_surcharge .'%)' }}</td>
                                    @else
                                        <td width="50%">-</td>
                                    @endif
                                </tr>

                                <tr>
                                   <th>Ratings Premium Rate:</th>
                                    @if($data->ratio != 0)
                                        @if($data->ratio > 2 && $data->ratio < 100)
                                            <td width="50%" style="color: green">{{ number_format((float)$data->ratio, 2, '.', '') }}%</td>
                                        @else
                                            <td width="50%" style="color: red">{{ number_format((float)$data->ratio, 2, '.', '') }}%</td>
                                        @endif
                                    @else
                                    <td width="50%">-</td>
                                    @endif
                                </tr>
                                    {{--                            <tr>--}}
                                    {{--                                <th>Premium Rate after discount/surcharge:</th>--}}
                                    {{--                                @if($data->ratio != 0)--}}
                                    {{--                                    @if($data->ratio > 2 && $data->ratio < 100)--}}
                                    {{--                                        <td width="50%" style="color: green">{{ number_format((float)$data->ratio, 2, '.', '') }}%</td>--}}
                                    {{--                                    @else--}}
                                    {{--                                        <td width="50%" style="color: red">{{ number_format((float)$data->ratio, 2, '.', '') }}%</td>--}}
                                    {{--                                    @endif--}}
                                    {{--                                @else--}}
                                    {{--                                    <td width="50%">-</td>--}}
                                    {{--                                @endif--}}
                                    {{--                            </tr>--}}
                                <tr>
                                    <th>Reason:</th>
                                    @if($reason != null)
                                        <td width="50%">{{ $reason }}</td>
                                    @else
                                        <td width="50%">-</td>
                                    @endif
                                </tr>
                                </thead>
                            </table>
                            <div class="kt-portlet__foot kt-portlet__foot--solid">
                                @if($data->quote_status == 1)
                                    <div class="kt-form__actions">
                                        <div class="row">
                                            <div class="col-5"></div>
                                            <div class="col-7">
                                                <button type="submit" class="btn btn-info">Update</button>
                                                <a class="btn btn-secondary" href="{{ route('quote.index') }}" >Cancel</a>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </form>
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

@include('admin.layouts.scripts')
<!------------>
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.min.js') }}"
                type="text/javascript"></script>
        <script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}"
                type="text/javascript"></script>
        <script src="{{ asset('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
        <script src="{{ asset('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript">
        </script>
        <script src="{{ asset('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script>
        <script src="{{ asset('assets/app/custom/general/components/forms/layouts/repeater.js') }}" type="text/javascript">
        </script>
        <script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"
                type="text/javascript"></script>
        <script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}"
                type="text/javascript"></script>

        <script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}"
                type="text/javascript"></script>
        <script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}"
                type="text/javascript"></script>
        <script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}"
                type="text/javascript"></script>

        <script src="{{ asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
        <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

        <script src="https://cdn.datatables.net/buttons/1.6.0/js/dataTables.buttons.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/1.6.0/js/buttons.html5.min.js"></script>

<!------------->

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>
    $('#rerate_div').slideUp();
    $('#custom_rate_div').slideUp();

    $('#edit-premium-button').click(function(e) {
        $('#rerate_div').slideDown();
        $('#custom_rate_div').slideUp();
    });

    $('#edit-custom-rate-button').click(function(e) {
        $('#custom_rate_div').slideDown();
        $('#rerate_div').slideUp();
    });

    $('#rerate_hide').click(function(e) {
        $('#rerate_div').slideUp();
    });

    $('#rate_hide').click(function(e) {
        $('#custom_rate_div').slideUp();
    });

</script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}"  type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}" type="text/javascript"></script>


<script>
    $(document).ready(function () {

        $('.kt_datepicker_1').datepicker({
            rtl: KTUtil.isRTL(),
            todayHighlight: true,
            orientation: "bottom left",
            // templates: arrows,
            format: 'yyyy-mm-dd'
        });

        $('#valueLoader').hide();
        var vehicle_status = "{{ $data->is_imported }}";
        var vehicle_make = "{{ $data->make }}";
        var vehicle_model = "{{ $data->model }}";
        var vehicle_year = "{{ $data->manufacturingYear }}";
        $('#year').prop('value',vehicle_year);
        var other_model = "{{ $data->other_model }}";
        if(vehicle_status == "No"){
            if(other_model == '1') {
                $("#model_other_div").show();
                $('.model_other').val(vehicle_model);
            }else{
                $("#model_other_div").hide();
                $('.model_other').val('');
            }
        }

        //Get vehicle make model
        // getVehicleMakes(vehicle_status);
        // getVehicleModels(vehicle_status,vehicle_make,vehicle_year);

        $('#is_imported').on('change', function() {
            getVehicleMakes(this.value);
        });
        $('#is_imported').on('change', function() {
            var status = $('#is_imported').val();
            if(status == 'No'){
                $("#model_Variant_div").show();
            }else{
                $("#model_Variant_div").hide();
            }
        });

        setInputFilter(document.getElementById("estimatedValue"), function(value) {
            return /^\d*\.?\d*$/.test(value); // Allow digits and '.' only, using a RegExp
        });

        // Restricts input for the given textbox to the given inputFilter function.
        function setInputFilter(textbox, inputFilter) {
            ["input", "keydown", "keyup", "mousedown", "mouseup", "select", "contextmenu", "drop"].forEach(function(event) {
                textbox.addEventListener(event, function() {
                    if (inputFilter(this.value)) {
                        this.oldValue = this.value;
                        this.oldSelectionStart = this.selectionStart;
                        this.oldSelectionEnd = this.selectionEnd;
                    } else if (this.hasOwnProperty("oldValue")) {
                        this.value = this.oldValue;
                        this.setSelectionRange(this.oldSelectionStart, this.oldSelectionEnd);
                    } else {
                        this.value = "";
                    }
                });
            });
        }
        $('#year').on('change',function(){
           var status = $('#is_imported').val();
          
           getVehicleVariant(status);
        });
        $('#modelm').on('change',function(){
            
           var status = $('#is_imported').val();
           var make = $('#make option:selected').val();
           var model = $('#modelm option:selected').val();
          
               getVehicleYear(status,make,model);
        });

        $('#make').on('change',function(){
            var status = $('#is_imported').val();
            var make = this.value;
            //var year = $('#year').val();
            $('#estimatedValue').val('');
            $("#model_other_div").hide();
            $('.model_other').val('');
            getVehicleModels(status,make);
        });
        function getVehicleVariant(status)
        {
           var url = "{{env('GRAPHITE_URL')}}api/frontendpay/getTTVehicleVariant";
           var data = {  
                        make              : $('#make option:selected').val(),
                        vehicleModel: $('#modelm option:selected').val(),
                        manufacturing_year: $('#year option:selected').val(),
                    };
           

            if(status == 'No')
            {
                $.ajax({
                type: "POST",
                datatype: 'json',
                url: url,
                data: data,
                dataType: "json",
                beforeSend: function () {
                    $("#loader").show();
                    $("#variant").empty();
                },
                success: function (responseData) {
                    $("#loader").hide();
                    $("#variant").empty();
                        $("#variant").append('<option value="">Select Variant</option>');
                    $.each(responseData.variant, function (index, variant) {
                        if (year !== '') {
                            var $option = $('<option value="' + variant + '">' + variant + '</option>');
                            $("#variant").append($option);
                        }
                    });
                      
                       
                       
                },
                complete:function(){
                    $("#loader").hide();
                    $("#variant").show();
                },
                error:function(error){
                    if (error.status == 401) {
                        toastr.error("Unauthorized Access");
                    }
                },
            });
            }
        };

        function getVehicleMakes(e) {
            var baseURL = '{{ env('GRAPHITE_URL') }}';
           if(e == 'Yes'){
               var fetchURL = baseURL+'api/frontendpay/vehicleMake'
           }else{
               var fetchURL = baseURL+'api/frontendpay/getTTVehicleMakes'
           }
            $.ajax({
                type: "POST",
                datatype : 'json',
                url: fetchURL,
                dataType: "json",
                beforeSend: function() {
                    $("#loader").show();
                },
                success: function(data){
                    $("#loader").hide();
                    $("#make").html('');
                    $("#make").empty();
                    $("#make").append('<option value="">Select vehicle make</option>');
                    $("#model_other_div").hide();
                    $('.model_other').val('');
                    if(e == 'Yes') {
                        $.each(data.makes, function () {
                            $("#make").append('<option value="' + this.s_Make + '">' + this.s_Make + '</option>')
                        });
                    }else{
                        var makes = data.Makes;
                        $.each(makes, function (key,val) {
                            var makes = $('<option value="' + val + '">' + val + '</option>');
                            $("#make").append(makes);
                        });
                    }
                }
            });
        }

        // $('#year').on('change',function(){
        //    var status = $('#is_imported').val();
        //    var make = $('#make').val();
        //    var year = this.value;
        //     getVehicleModels(status,make,year);
        // });

        // $('#make').on('change',function(){
        //     var status = $('#is_imported').val();
        //     var make = this.value;
        //     var year = $('#year').val();
        //     $('#estimatedValue').val('');
        //     $("#model_other_div").hide();
        //     $('.model_other').val('');
        //     getVehicleModels(status,make,year);
        // });


        $('#modelm').on('change', function() {
            if($(this).val() === 'other') {
                $("#model_other_div").show();
                $('.model_other').rules('add', {required: true, messages: {required: "Please enter Model"}});
            } else {
                $("#model_other_div").hide();
                $('.model_other').rules('remove', 'required');
            }
        });


        $('#submitRerate').on('click',function(){

            if (!$('#value').val()) {
               alert('Please provide the value');
            }
            if (!$('#reason').val()) {
               alert('Please provide the reason');
            }
            if($('#value').val() != '' && $('#value').val() != null && $('#reason').val() != '' && $('#reason').val() != null){

                var quoteNumber = $('#quoteCode').val();
                $.ajax({
                    url: '{{ route("quote.updatePremiumAjax") }}',
                    data: {
                        "_token"       : "{{ csrf_token() }}",
                        "quoteNumber"     : quoteNumber,
                        "type": $('#type').val(),
                        "value_type": $('#value_type').val(),
                        "value": $('#value').val(),
                        "reason": $('#reason').val(),
                    },
                    beforeSend: function() {
                        $("#loader").show();
                    },
                    type: 'post',
                    datatype: 'json',
                    async: false,
                    success: function(data) {
                        // console.log(data);
                        alert('Success! '+data.message);
                        location.reload();
                        // $('#discSurAdd').val("Added");
                    },
                    error: function(data) {
                        // console.log(data);
                        alert('Unsuccessful! '+data.message);
                        location.reload();
                    },
                    complete: function(data) {
                        location.reload();
                        // $('#discSurAdd').val("Added");
                    },
                });
              // $('#discSurAdd').val("Added");

            }

        });

        $('#submitCustomRate').on('click',function(){
            if (!$('#rate_value').val()) {
               alert('Please provide the value');
            }
            if (!$('#rate_reason').val()) {
               alert('Please provide the reason');
            }
            if($('#rate_value').val() != '' && $('#rate_value').val() != null && $('#rate_reason').val() != '' && $('#rate_reason').val() != null){

                var quoteNumber = $('#quoteCode').val();
                $.ajax({
                    url: '{{ route("quote.updatePremiumRate") }}',
                    data: {
                        "_token"       : "{{ csrf_token() }}",
                        "quoteNumber"     : quoteNumber,
                        "value": $('#rate_value').val(),
                        "reason": $('#rate_reason').val(),
                        "value_type": $('#cus_dis_sur_rate_value_type').val(),
                    },
                    beforeSend: function() {
                        $("#loader").show();
                    },
                    type: 'post',
                    datatype: 'json',
                    async: false,
                    success: function(data) {
                        console.log(data);
                        alert('Success! '+data.message);
                        location.reload();
                    },
                    error: function(data) {
                        console.log(data);
                        alert('Unsuccessful! '+data.message);
                        location.reload();
                    },
                    complete: function(data) {
                        console.log(data);
                        location.reload();
                    },
                });

            }

        });

        function getVehicleModels(status,make) {
            var baseURL = '{{ env('GRAPHITE_URL') }}';
            if(status == 'Yes'){
                var fetchURL = baseURL+'api/frontendpay/vehicleModel'
            }else{
                var fetchURL = baseURL+'api/frontendpay/getTTVehicleModels'
            }
            if(status == 'Yes'){
               $.ajax({
                   type: "POST",
                   datatype: 'json',
                   url: fetchURL,
                   data: {
                       vehicle_make: make,
                   },
                   dataType: "json",
                   beforeSend: function () {
                       $("#loader").show();
                       $("#modelm").empty();
                       $('.manufacturing_year').prop('selectedIndex',0);
                   },
                   success: function (responseData) {
                       $("#loader").hide();
                       $("#modelm").html('');
                       $("#modelm").empty();
                       $("#modelm").append('<option value="">Select Model</option>');
                       $.each(responseData.makes, function () {
                           $option = $('<option value="' + this.s_Variant + '">' + this.s_Variant + '</option>');
                           $("#modelm").append($option);
                       });
                       $("#modelm").append('<option value="other">Other</option>');
                   },complete:function(){
                       $("#loader").hide();
                   }
               });
            }else{
               $.ajax({
                   type: "POST",
                   datatype: 'json',
                   url: fetchURL,
                   data: {

                       make: make,
                     
                   },
                   dataType: "json",
                   beforeSend: function () {
                       $("#loader").show();
                       $("#modelm").empty();
                       $('#modelm').prop('selectedIndex',0);
                   },
                   success: function (responseData) {
                       $("#loader").hide();
                       $("#modelm").html('');
                       $("#modelm").empty();
                       $("#modelm").append('<option value="">Select vehicle model</option>');
                       $.each(responseData.Models, function () {
                            if (this.Model) {
                                const $option = $('<option>', {
                                    value: this.Model,
                                    text: this.Model
                                });
                                $("#modelm").append($option);
                              
                            }
                        });
                       $("#modelm").append('<option value="other">Other</option>');
                   },
                   error:function(data){
                       $option = $('<option value="" disabled>No vehicle found</option>');
                       $("#modelm").append($option);
                   },
                   complete:function(){
                       $("#loader").hide();
                   }
               });
            }
        }
        function getVehicleYear(status,make,model)
        {
            
           var url = "{{env('GRAPHITE_URL')}}api/frontendpay/getTTVehicleYear";
           var data = {  
                    make  : $('#make option:selected').val(),
                    vehicleModel: $('#modelm option:selected').val() 
                };
           

            if(status == 'No')
            {
                $.ajax({
                type: "POST",
                datatype: 'json',
                url: url,
                data: data,
                dataType: "json",
                beforeSend: function () {
                    $("#loader").show();
                    $("#year").empty();
                },
                success: function (responseData) {
                    $("#loader").hide();
                    $.each(responseData.year, function (index, year) {
                        if (year !== '') {
                            var $option = $('<option value="' + year + '">' + year + '</option>');
                            $("#year").append($option);
                        }
                    });
                      
                       
                       
                },
                complete:function(){
                    $("#loader").hide();
                    $("#year").show();
                },
                error:function(error){
                    if (error.status == 401) {
                        toastr.error("Unauthorized Access");
                    }
                },
            });
            }
        }

        $('#variant').on('change',function(){
            var baseURL = '{{ env('GRAPHITE_URL') }}';
                $('#estimatedValue').val('');
                if(this.value && $('#is_imported').val() == 'No'){
                    $.ajax({
                        type: "POST",
                        datatype: 'json',
                        url: baseURL+'api/frontendpay/getTTValue',
                        data: {
                            vehicleMake: $('#make option:selected').val(),
                            vehicleModel: $('#modelm option:selected').val(),
                            manufacturing_year: $('#year option:selected').val(),
                            variant: $('#variant option:selected').val()
                            // condition: 'EX',
                            // mileage: 'LO',

                        },
                        dataType: "json",
                        beforeSend: function () {
                            $('#valueLoader').show();
                            $('#estimatedValue').hide();
                            $('#ratingsCalculation').prop('disabled', true);
                        },
                        success: function (data) {
                            $('#valueLoader').hide();
                            $('#estimatedValue').hide();
                            if(data.value){
                                var val = (data.value).toFixed(2)
                                $('#estimatedValue').val(val);
                                $('#ratingsCalculation').prop('disabled', false);
                                if(data.value > 500000){
                                    $('#ratingsCalculation').prop('disabled', true);
                                    $("#ratingsCalculation").hide();
                                    $("#requestcallback").show();
                                }
                            }
                        },
                        error: function () {
                            $('#valueLoader').hide();
                            $('#estimatedValue').show();
                            $('#estimatedValue').val('');
                            $('#ratingsCalculation').prop('disabled', false);
                        },
                        complete: function() {
                            $('#valueLoader').hide();
                            $('#ratingsCalculation').prop('disabled', false);
                            $('#estimatedValue').show();
                        },
                    });
                }
        });
    });
</script>

<script>
    $(".updateCustomerKYC").validate({
        rules: {
            is_imported: {
                required: true,
            },
            make: {
                required: true,
            },
            year: {
                required: true,
            },
            model: {
                required: true,
            },
            estimatedValue: {
                required: true,
            },
            model_other: {
                required: true,
            },
        },
        messages: {
            is_imported: {
                required: "Please select japenese import:."
            },
            make: {
                required: "Please select make model."
            },
            year: {
                required: "Please select year."
            },
            model: {
                required: "Please select model."
            },
            estimatedValue: {
                required: "Please enter estimated value."
            },
            model_other: {
                required: "Please enter other model."
            }
        }
    });
</script>
</body>
<!-- end::Body -->
</html>
