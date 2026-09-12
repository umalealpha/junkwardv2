<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
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
                    KYC Delete Records
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{Route('admin.customerKyc')}}" class="kt-subheader__breadcrumbs-link"> Customer KYC </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Customer KYC Delete Data</span> </a>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                   {{-- <a href="{{ URL::to('admin/region/create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>--}}
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->

        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    @if(isset($data) && $data->customer_id != null)
                    @php 
                        $customer = \AlphaDirect\Customer::where('id',$data->customer_id)->first(['firstName','middleName','lastName']);
                    @endphp
                    @if($customer)
                    <h5>Customer Name:  {{ ucwords($customer->firstName.' '.$customer->middleName.' '.$customer->lastName) }}</h5>
                    @endif
                    @endif
                    <br>
                    <!--begin: Datatable -->
                    @if($data->passport != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>Passport</th>
                        <th>Passport Number</th>
                      
                        <th>Remark</th>
                        <th>Expiry</th>
                       
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->passport) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->passport) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->passport,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->passport,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->passport, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->passport,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->passport,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->passport, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->passport,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->passport,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->passport, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->passport,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->passport) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                         <td>{{ $passdata[0]->passportNumber }}</td>

                        
                         <td>{{ $passdata[0]->passportRemark }}</td>
                         <td>{{ $passdata[0]->passportExpiry }}</td>
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif
                    @if($data->omang != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>omang</th>
                        <th>Omang Number</th>
                      
                        <th>Remark</th>
                        <th>Expiry</th>
                       
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->omang) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->omang) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->omang,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->omang,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->omang, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->omang,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->omang,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->omang, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->omang,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->omang,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->omang, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->omang,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->omang) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                         <td>{{ $passdata[0]->omangNumber }}</td>

                        
                         <td>{{ $passdata[0]->omangFrontRemark }}</td>
                         <td>{{ $passdata[0]->omangExpiry }}</td>
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif
                    @if($data->omangBack != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>omang Back</th>
                        <th>Omang Number</th>
                      
                        <th>Remark</th>
                        <th>Expiry</th>
                       
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->omangBack) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->omangBack) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->omangBack,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->omangBack,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->omangBack, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->omangBack,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->omangBack,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->omangBack, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->omangBack,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->omangBack,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->omangBack, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->omangBack,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->omangBack) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                         <td>{{ $passdata[0]->omangNumber }}</td>

                        
                         <td>{{ $passdata[0]->omangBackRemark }}</td>
                         <td>{{ $passdata[0]->omangExpiry }}</td>
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif
                    @if($data->driving_license != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>Driving License</th>
                       
                      
                        <th>Remark</th>
                        <th>Expiry</th>
                       
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->driving_license) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->driving_license) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->driving_license,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->driving_license,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->driving_license, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->driving_license,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->driving_license,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->driving_license, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->driving_license,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->driving_license,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->driving_license, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->driving_license,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->driving_license) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                         

                        
                         <td>{{ $passdata[0]->driving_licenseRemark }}</td>
                         <td>{{ $passdata[0]->licenseExpiry }}</td>
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif
                    @if($data->proof_income != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>Proof Income</th>
                       
                      
                        <th>Remark</th>
                        <th>Expiry</th>
                       
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->proof_income) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->proof_income) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->proof_income,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->proof_income,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->proof_income, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->proof_income,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->proof_income,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->proof_income, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->proof_income,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->proof_income,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->proof_income, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->proof_income,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->proof_income) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                        

                        
                         <td>{{ $passdata[0]->proof_incomeRemark }}</td>
                         <td>{{ $passdata[0]->incomeExpiry }}</td>
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif
                    @if($data->proof_residence != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>Proof Residence</th>
                       
                      
                        <th>Remark</th>
                        <th>Expiry</th>
                       
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->proof_residence) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->proof_residence) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->proof_residence,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->proof_residence,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->proof_residence, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->proof_residence,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->proof_residence,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->proof_residence, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->proof_residence,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->proof_residence,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->proof_residence, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->proof_residence,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->proof_residence) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                        

                        
                         <td>{{ $passdata[0]->proof_residenceRemark }}</td>
                         <td>{{ $passdata[0]->residenceExpiry }}</td>
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif

                    @if($data->Canceled_document != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>Canceled Document</th>
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->Canceled_document) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->Canceled_document) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->Canceled_document,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->Canceled_document,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->Canceled_document, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->Canceled_document,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->Canceled_document,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->Canceled_document, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->Canceled_document,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->Canceled_document,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->Canceled_document, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->Canceled_document,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->Canceled_document) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                        

                        
                        
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif
                    @if($data->data_protection_consent != null)
                    <table class="table table-striped table-bordered table-hover " >
                       
                        <tr>
                        <th>Data Protection Consent</th>
                        <th>Performed By</th>
                        <th>Deleted At</th>
                        <th>
                        @foreach(json_decode($data->data_protection_consent) as $passdata)
                        <tr>
                         <td>
                         <a href ="{{ \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->data_protection_consent) }}" target= "_blank">
                                      
                                      @if(pathinfo($passdata[0]->data_protection_consent,
                                  PATHINFO_EXTENSION) == 'pdf')
                                          <img src="{{asset('images/pdf.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->data_protection_consent,
                                      PATHINFO_EXTENSION) == 'docx' ||
                                      pathinfo($passdata[0]->data_protection_consent, PATHINFO_EXTENSION)
                                      == 'doc' || pathinfo($passdata[0]->data_protection_consent,
                                      PATHINFO_EXTENSION) == 'docm')
                                          <img src="{{asset('images/word.ico')}}" width="70px"
                                              height="auto">
                                      @elseif(pathinfo($passdata[0]->data_protection_consent,
                                      PATHINFO_EXTENSION) == 'xls' ||
                                      pathinfo($passdata[0]->data_protection_consent, PATHINFO_EXTENSION)
                                      == 'xlsx' || pathinfo($passdata[0]->data_protection_consent,
                                      PATHINFO_EXTENSION) == 'csv')
                                          <img src="{{asset('images/excel.png')}}"
                                              width="70px" height="auto">
                                      @elseif(pathinfo($passdata[0]->data_protection_consent,
                                      PATHINFO_EXTENSION) == 'jpeg' ||
                                      pathinfo($passdata[0]->data_protection_consent, PATHINFO_EXTENSION)
                                      == 'jpg' || pathinfo($passdata[0]->data_protection_consent,
                                      PATHINFO_EXTENSION) == 'png')
                                          <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($passdata[0]->data_protection_consent) !!}"
                                              width="70px" height="auto">
                                      @else
                                          <img src="{{asset('images/doc.png')}}" width="70px"
                                              height="auto">
                                      @endif

                                          </a>



                         </td>
                        

                        
                        
                         <td>@php
                             if($passdata[0] != null &&$passdata[0]->performed_by !=null)
                              {
                                $user=AlphaDirect\User::where('id',$passdata[0]->performed_by)->first(array('firstName' , 'lastName'));
                              }
                             @endphp
                            @if(isset($user) && ($user->firstName || $user->lastName))
                                <b>{{ $user->firstName }}  {{ $user->lastName }}</b>
                            @endif   



                         </td>
                         <td>{{ \Carbon\Carbon::parse($passdata[0]->updated_at)->format('Y-m-d')  }}</td>
                         </tr>
                        @endforeach
                         </th>
                        </tr>


                       
                        
                       
                    </table>
                    @endif
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

@include('admin.layouts.scripts')

<div class="modal fade" id="delete_confirm" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
        </div>
    </div>
</div>
<script>
    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });
    });



</script>

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>


</body>
<!-- end::Body -->
</html>
