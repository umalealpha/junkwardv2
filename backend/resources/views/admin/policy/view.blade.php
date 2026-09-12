<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
 <link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />

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
                    Policy # {{ $policy->policyNumber }}
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{Route('admin.policy.index')}}" class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Policy</span> </a>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <a href="{{ URL::to('admin/policy/create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="row ol-lg-12">
                <div class=" col-lg-3 text-left policyno"><br>
                    <h5>Policy No # {{$policy->policyNumber}} <br>

                        @if($transaction !=null)
                            <span class="kt-badge  kt-badge--primary kt-badge--inline kt-badge--pill" style="font-size:15px">Ref: {{$transaction->referenceNumber}}</span>
                        @else
                            <span class="kt-badge  kt-badge--primary kt-badge--inline kt-badge--pill" style="font-size:15px">Ref: Not yet assigned</span>
                        @endif
                    </h5>
                </div>
                @if($kyc == NULL)
                    <div class="col-lg-3 text-center">
                        <br>
                        <label> <h5>Compliance: <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill" style="font-size:15px"> KYC Non Compliant</span></h5></label>
                    </div>
                @else
                    <div class="col-lg-3">
                        <br>
                        <label>
                            @if($kyc->compliance == 1)
                                <h5>Compliance: <span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill" style="font-size:15px"> KYC Compliant</span></h5>
                            @else
                                <h5>Compliance: <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill" style="font-size:15px"> KYC Non Compliant</span></h5>
                            @endif
                        </label>
                    </div>
                @endif
                <div class="col-lg-4 text-center">
                    <br>
                    <label>
                        @if ($policy->status == 1)
                            <h5>Policy Status: <span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill" style="font-size:15px"> Activated</span>@if($transaction && $transaction->status == 'SUCCESS')<span  class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill" style="font-size:15px"> Payment Success</span>@else<span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill" style="font-size:15px"> Payment Failed</span>@endif</h5>
                        @elseif ($policy->status == 2)
                            <h5>Policy Status: <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill" style="font-size:15px"> Cancelled</span>@if($transaction && $transaction->status == 'SUCCESS')<span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill" style="font-size:15px"> Payment Success</span>@else<span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill" style="font-size:15px"> Payment Failed</span>@endif</h5>
                        @else
                            <h5>Policy Status: <span class="kt-badge  kt-badge--focus kt-badge--inline kt-badge--pill" style="font-size:15px"> Deactivated</span>@if($transaction && $transaction->status == 'SUCCESS')<span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill" style="font-size:15px"> Payment Success</span>@else<span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill" style="font-size:15px"> Payment Failed</span>@endif</h5>
                        @endif
                    </label>
                </div>
                <div class=" col-lg-2 "><br>
                    @if($policy->agent_id != NULL)
                        @if($agent_name != null)
                            <h5>Agent: {!! $agent_name->firstName !!} {!! $agent_name->lastName !!}</h5>
                        @elseif($agent_name->firstName == null)
                            <h5>Agent: {!! $agent_name->lastName !!}</h5>
                        @elseif($agent_name->lastName == null)
                            <h5>Agent: {!! $agent_name->firstName !!}</h5>
                        @endif
                    @else
                        <h5>Agent: Not assigned</h5>
                    @endif
                </div>

            </div>
                    <div class="kt-portlet kt-portlet--mobile">
                         <div class="kt-portlet__body">
                                <div class="tab-content">
                                        <div class="tab-pane active" id="kt_portlet_base_demo_3_1_policy_content" role="tabpanel">
                                            <div class="kt-portlet" id="labelDiv">
                                                <div class="kt-portlet__head">
                                                    <div class="kt-portlet__head-label">
                                                        <h3 class="kt-portlet__head-title">
                                                            Customer Details
                                                        </h3>

                                                    </div>

                                                </div>
                                                <div class="kt-portlet_body">
                                                    <div class="kt-section">
                                                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                            <table class="table table-striped m-table">
                                                                <tbody>
                                                                    <tr>
                                                                        <th>Name</th>
                                                                        @if($user != null)
                                                                            <td>{!! $user->firstName !!} {!! $user->lastName !!}</td>
                                                                        @elseif($user->firstName == null)
                                                                            <td>{!! $user->lastName !!}</td>
                                                                        @elseif($user->lastName == null)
                                                                            <td>{!! $user->firstName !!}</td>
                                                                        @endif
                                                                        <th>Email</th>
                                                                        @if($user != null && $user->email)
                                                                            <td>{!! $user->email !!}</td>
                                                                        @endif
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Cellphone</th>
                                                                        <td>{!! $user->cellphone !!}</td>
                                                                        <th>Address</th>
                                                                        <td>{!! $user->profile->address !!}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Omang ID</th>
                                                                        <td>{!! $user->profile->omang !!}</td>
                                                                        <th>Passport</th>
                                                                        <td>{!! $user->profile->passport !!}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Date of Birth</th>
                                                                        <td>{!! \Carbon\Carbon::parse($user->profile->dob)->format('d/m/y') !!}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Payment Vendor</th>
                                                                        @if ($transaction != null)
                                                                            <td>{{ $transaction->transactionType }} </td>
                                                                        @else
                                                                            <td>N/A</td>
                                                                        @endif

                                                                        <th>Billing Start Date</th>
                                                                        @if($policy->billingStartDate != null)
                                                                            <td> {{ $policy->billingStartDate }} </td>
                                                                        @else
                                                                            <td>N/A</td>
                                                                        @endif

                                                                    </tr>
                                                                    <tr>
                                                                        <th><h3>Source of income:</h3></th>
                                                                            @if(isset($user->profile->sourceOfIncome))
                                                                                @foreach($user->profile->sourceOfIncome as $key => $source_Income)
                                                                                    @if($key != null)
                                                                                        <tr>
                                                                                            <th><h4>{{ Str::ucfirst($key) }} :</h4></th>
                                                                                            @if (isset($source_Income))
                                                                                                @foreach ($source_Income as $sourceKey=>$source)
                                                                                                    <tr>
                                                                                                        <th>
                                                                                                            @if ($source != null)
                                                                                                            {{ Str::ucfirst(Str::replace('_', ' ', $sourceKey)) }}
                                                                                                            @endif
                                                                                                        </th>
                                                                                                        <td>
                                                                                                            @if ($source != null)
                                                                                                            {{ Str::ucfirst($source) }}
                                                                                                            @endif
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                @endforeach
                                                                                            @endif
                                                                                        </tr>
                                                                                    @endif
                                                                                @endforeach
                                                                            @else
                                                                            {{ '-' }}
                                                                            @endif
                                                                    </tr>

                                                                    </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                                @if($policy->kyc_customer)
                                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                                    <div class="kt-portlet__head">
                                                        <div class="kt-portlet__head-label">
                                                            <h3 class="kt-portlet__head-title">
                                                                Customer KYC
                                                            </h3>
                                                        </div>
                                                    </div>
                                                    <div class="kt-portlet_body">
                                                        <div class="kt-section">
                                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                                <table class="table table-striped m-table">
                                                                    <tbody>
                                                                    <div class="form-group row">
                                                                        <div class="col-md-2">
                                                                            <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                                                            @if($kyc->driving_license == NULL)
                                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                            @else


                                                                                <a href="{!!\AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license) !!}" target="_blank" download>
                                                                                    @if(pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'pdf')
                                                                                        <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'docm')
                                                                                        <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'csv')
                                                                                        <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->driving_license, PATHINFO_EXTENSION) == 'png')
                                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license)}}" width="100%" height="auto" >
                                                                                    @else
                                                                                        <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                                    @endif
                                                                                </a>


                                                                                {{--<img src="{{Storage::disk('s3')->url($kyc->driving_license)}}" width="100%" height="auto" >--}}
                                                                            @endif
                                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                                        </div>
                                                                        <div class="col-md-2">
                                                                            <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                                                            @if($kyc->omang == NULL)
                                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                            @else

                                                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang) !!}" target="_blank" download>
                                                                                    @if(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'pdf')
                                                                                        <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'docm')
                                                                                        <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'csv')
                                                                                        <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->omang, PATHINFO_EXTENSION) == 'png')
                                                                                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($kyc->omang)}}" width="100%" height="auto" >
                                                                                    @else
                                                                                        <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                                    @endif
                                                                                </a>
                                                                                {{--<img src="{{Storage::disk('s3')->url($kyc->omang)}}" width="100%" height="auto" >--}}
                                                                            @endif
                                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                                        </div>

                                                                        <div class="col-md-2">
                                                                            <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                                                            @if($kyc->proof_residence == NULL)
                                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                            @else

                                                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) !!}" target="_blank" download>
                                                                                    @if(pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'pdf')
                                                                                        <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'docm')
                                                                                        <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'csv')
                                                                                        <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->proof_residence, PATHINFO_EXTENSION) == 'png')
                                                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) }}" width="100%" height="auto" >
                                                                                    @else
                                                                                        <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                                    @endif
                                                                                </a>
                                                                               {{-- <img src="{{Storage::disk('s3')->url($kyc->proof_residence)}}" width="100%" height="auto" >--}}
                                                                            @endif
                                                                            <div class="kt-avatar" style="float: left; clear: left;">
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-md-2">
                                                                            <h3 class="col-form-label" style="float: left;">Proof of Income</h3>
                                                                            @if($kyc->proof_income == NULL)
                                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                            @else


                                                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) !!}" target="_blank" download>
                                                                                    @if(pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'pdf')
                                                                                        <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'docm')
                                                                                        <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'csv')
                                                                                        <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->proof_income, PATHINFO_EXTENSION) == 'png')
                                                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income)}}" width="100%" height="auto" >
                                                                                    @else
                                                                                        <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                                    @endif
                                                                                </a>
                                                                               {{-- <img src="{{Storage::disk('s3')->url($kyc->proof_income)}}" width="100%" height="auto" >--}}
                                                                            @endif
                                                                            <div class="kt-avatar" style="float: left; clear: left;">
                                                                            </div>
                                                                        </div>

                                                                        <div class="col-md-2">
                                                                            <h3 class="col-form-label" style="float: left;">Passport</h3>
                                                                            @if($kyc->passport == NULL)
                                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                            @else

                                                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport) !!}" target="_blank" download>
                                                                                    @if(pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'pdf')
                                                                                        <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'docx' || pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'doc' || pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'docm')
                                                                                        <img src="{{asset('images/word.ico')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'xls' || pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'csv')
                                                                                        <img src="{{asset('images/excel.png')}}" width="100%" height="auto" >
                                                                                    @elseif(pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'jpg' || pathinfo($kyc->passport, PATHINFO_EXTENSION) == 'png')
                                                                                        <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($kyc->passport)}}" width="100%" height="auto" >
                                                                                    @else
                                                                                        <img src="{{asset('images/doc.png')}}" width="100%" height="auto" >
                                                                                    @endif
                                                                                </a>
                                                                                {{--<img src="{{Storage::disk('s3')->url($kyc->passport)}}" width="100%" height="auto" >--}}
                                                                            @endif
                                                                            <div class="kt-avatar" style="float: left; clear: left;">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif

                                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                                <div class="kt-portlet__head">
                                                    <div class="kt-portlet__head-label">
                                                        <h3 class="kt-portlet__head-title">
                                                            Product Details
                                                        </h3>
                                                    </div>
                                                </div>
                                                <div class="kt-portlet_body">
                                                    <div class="kt-section">
                                                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                            <table class="table table-striped m-table">
                                                                <tbody>
                                                                <tr>
                                                                    <th>Product Name</th>
                                                                    <td>{!! $product->name !!}</td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Premium</th>
                                                                    <td>P{!! $premium !!}</td>
                                                                </tr>
                                                                @if($policy->plan_id != NULL)
                                                                    <tr>
                                                                        <th>Product Plan</th>
                                                                        <td>{!! $productPlan->name !!}</td>
                                                                    </tr>
                                                                @endif
                                                                <tr>
                                                                    <th>Sum Assured / Insured</th>
                                                                    <td>{!! $policy->sum_assured !!}</td>
                                                                </tr>
                                                                @foreach($productFactors as $productFactor)
                                                                    @if($productFactor->type == 'Select')
                                                                        <tr>
                                                                            <th>{!! $productFactor->name !!}</th>
                                                                            <td>{!! implode(',', $productFactor->policyFactorsValueName) !!}</td>
                                                                        </tr>
                                                                    @elseif($productFactor->type == 'Radio')
                                                                        <tr>
                                                                            <th>{!! $productFactor->name !!}</th>
                                                                            <td>{!! implode(',', $productFactor->policyFactorsValueName) !!}</td>
                                                                        </tr>
                                                                    @elseif($productFactor->type == 'Checkbox')
                                                                        <tr>
                                                                            <th>{!! $productFactor->name !!}</th>
                                                                            <td>{!! implode(', ', $productFactor->policyFactorsValueName) !!}</td>
                                                                        </tr>
                                                                    @elseif($productFactor->type == 'Input Field')
                                                                        <tr>
                                                                            <th>{!! $productFactor->name !!}</th>
                                                                            <td>{!! implode(',', $productFactor->policyFactorsValueName) !!}</td>
                                                                        </tr>
                                                                    @endif
                                                                @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                                @if($policy->has_vehicle != 0)
                                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                                    <div class="kt-portlet__head">
                                                        <div class="kt-portlet__head-label">
                                                            <h3 class="kt-portlet__head-title">
                                                                Vehicle Details
                                                            </h3>
                                                        </div>
                                                    </div>
                                                    <div class="kt-portlet_body">
                                                        <div class="kt-section">
                                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                                <table class="table table-striped m-table">
                                                                    <tbody>
                                                                    <tr>
                                                                        <th>Vehicle Number</th>
                                                                        <td>{!! $vehicle->vehiclePlate !!}</td>
                                                                        <th>Chassis Number(VIN)</th>
                                                                        <td>{!! $vehicle->chassisNo !!}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Odometer</th>
                                                                        <td>{!! $vehicle->odometer !!}</td>
                                                                        <th>Purpose</th>
                                                                        <td>
                                                                            @foreach($vehicle_purpose as $purpose)
                                                                                @if($purpose->id == $vehicle->purpose)
                                                                                    {!! $purpose->value !!}
                                                                                @endif
                                                                            @endforeach
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Condition</th>
                                                                        <td>{!! $vehicle->condition !!}</td>
                                                                        <th>Year of Manufacturing</th>
                                                                        <td>{!! $vehicle->year !!}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Make</th>
                                                                        <td>{!! $vehicle->make !!}</td>
                                                                        <th>Model</th>
                                                                        <td>{!! $vehicle->model !!}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Engine Number</th>
                                                                        <td>{!! $vehicle->engineNo !!}</td>
                                                                        <th>Number of Seats</th>
                                                                        <td>{!! $vehicle->seats !!}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Number of Cylinder</th>
                                                                        <td>{!! $vehicle->cylinders !!}</td>
                                                                        <th>Cubic Capacity</th>
                                                                        <td>{!! $vehicle->cubic_capacity !!}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Is it imported?</th>
                                                                        @if($vehicle->is_imported == 0)
                                                                            <td>No</td>
                                                                        @else
                                                                            <td>Yes</td>
                                                                        @endif
                                                                        <th>Is the vehicle fitted with tracking device?</th>
                                                                        @if($vehicle->is_tracking == 0)
                                                                            <td>No</td>
                                                                        @else
                                                                            <td>Yes</td>
                                                                        @endif
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Is the vehicle used for private use?</th>
                                                                        @if($vehicle->is_private == 0)
                                                                            <td>No</td>
                                                                        @else
                                                                            <td>Yes</td>
                                                                        @endif
                                                                        <th>Is the vehicle modified in any way?</th>
                                                                        @if($vehicle->is_modified == 0)
                                                                            <td>No</td>
                                                                        @else
                                                                            <td>Yes</td>
                                                                        @endif
                                                                    </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                                    <div class="kt-portlet__head">
                                                        <div class="kt-portlet__head-label">
                                                            <h3 class="kt-portlet__head-title">
                                                                Vehicle Images
                                                            </h3>
                                                        </div>
                                                    </div>
                                                    <div class="kt-portlet_body">
                                                        <div class="kt-section">
                                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                                <table class="table table-striped m-table">
                                                                    <tbody>
                                                                    <div class="form-group row">
                                                                        <div class="col-md-2" style="margin-left: 30px;">
                                                                            <h3 class="col-form-label" style="float: left;">Left</h3>
                                                                            @if($vehicle->left == NULL)
                                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                            @else
                                                                                <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->left) }}" width="100%" height="auto" >
                                                                            @endif
                                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                                        </div>
                                                                        <div class="col-md-2">
                                                                            <h3 class="col-form-label" style="float: left;">Right</h3>
                                                                            @if($vehicle->right == NULL)
                                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                            @else
                                                                                <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->right) }}" width="100%" height="auto" >

                                                                            @endif
                                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                                        </div>
                                                                        <div class="col-md-2">
                                                                            <h3 class="col-form-label" style="float: left;">Back</h3>
                                                                            @if($vehicle->back == NULL)
                                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                            @else
                                                                                <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->back)}}" width="100%" height="auto" >
                                                                            @endif
                                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                                        </div>
                                                                        <div class="col-md-2">
                                                                            <h3 class="col-form-label" style="float: left;">Front</h3>
                                                                            @if($vehicle->front == NULL)
                                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                            @else
                                                                                <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->front) }}" width="100%" height="auto" >

                                                                            @endif
                                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                                        </div>


                                                                        <div class="col-md-2">
                                                                            <h3 class="col-form-label" style="float: left;">Vehicle Registration</h3>
                                                                            @if($vehicle->vehicleRegistration == NULL)
                                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                                            @else
                                                                                <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicleRegistration) }}" width="100%" height="auto" >

                                                                            @endif
                                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>

                                                                        </div>
                                                                    </div>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @if($policy->has_vehicle != 0)
                                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                                    <div class="kt-portlet__head">
                                                        <div class="kt-portlet__head-label">
                                                            <h3 class="kt-portlet__head-title">
                                                                Coverages
                                                            </h3>
                                                        </div>
                                                    </div>
                                                    <div class="kt-portlet_body">
                                                        <div class="kt-section">
                                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                                <table class="table table-striped m-table">
                                                                    <tbody>
                                                                    <th>Main</th>
                                                                    <th>Coverage Value</th>
                                                                    <th>Disc/Surcharge</th>
                                                                    <th>Flat/%</th>
                                                                    <th>Value</th>
                                                                    @foreach($policyCover as $key => $cover)
                                                                        <tr>
                                                                            <td>{!! $cover->main !!}</td>
                                                                            @if($cover->coverage_value != NULL)
                                                                                <td>{!! $cover->coverage_value	!!}</td>
                                                                            @else
                                                                                <td>-</td>
                                                                            @endif

                                                                            @if($cover->discount != NULL && $cover->discount != 0)
                                                                                @if($cover->discount == 1)
                                                                                    <td>Discount</td>
                                                                                @elseif($cover->discount == 2)
                                                                                    <td>Surcharge</td>
                                                                                @endif
                                                                            @else
                                                                                <td>-</td>
                                                                            @endif

                                                                            @if($cover->type != NULL)
                                                                                @if($cover->type == 1)
                                                                                    <td>Flat</td>
                                                                                @elseif($cover->type == 2)
                                                                                    <td>%</td>
                                                                                @endif
                                                                            @else
                                                                                <td>-</td>
                                                                            @endif

                                                                            @if($cover->value != NULL)
                                                                                <td>{!! $cover->value !!}</td>
                                                                            @else
                                                                                <td>-</td>
                                                                            @endif

                                                                        </tr>
                                                                    @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @endif
                                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                                @endif

                                                @if($policy->is_motor_items != 0)
                                                    <div class="kt-portlet__head">
                                                        <div class="kt-portlet__head-label">
                                                            <h3 class="kt-portlet__head-title">
                                                                Specified Motor Items
                                                            </h3>
                                                        </div>
                                                    </div>
                                                    <div class="kt-portlet_body">
                                                        <div class="kt-section">
                                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                                <table class="table table-striped m-table">
                                                                    <tbody>
                                                                    <th>Description Of Items</th>
                                                                    <th>Sum Insured</th>
                                                                    @if($count != 0)
                                                                    @foreach($policyMotorItems as $policyMotorItem)
                                                                        @foreach($motor_items as $motor_item)
                                                                            @if( $policyMotorItem->item_name == $motor_item->n_PRAppsSuppPersprop_PK)
                                                                                <tr>
                                                                                    <td>{{ $motor_item->s_PersPropScreenName }}</td>
                                                                                    <td>{{ $policyMotorItem->item_value }}</td>
                                                                                </tr>
                                                                            @endif
                                                                        @endforeach
                                                                    @endforeach
                                                                        @endif
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                                @if($policy->note != 0)
                                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

                                                <div class="kt-portlet__head">
                                                    <div class="kt-portlet__head-label">
                                                        <h3 class="kt-portlet__head-title">
                                                            Note
                                                        </h3>
                                                    </div>
                                                </div>
                                                <div class="kt-portlet_body">
                                                    <div class="kt-section">
                                                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                            <table class="table ">
                                                                <tbody>
                                                                <tr>
                                                                    <td>{!! $policy->note !!} </td>
                                                                </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endif
                                                @if($policy->has_subApplicant != 0)
                                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                                    <div class="kt-portlet__head">
                                                        <div class="kt-portlet__head-label">
                                                            <h3 class="kt-portlet__head-title">
                                                                Sub Applicant Details
                                                            </h3>
                                                        </div>
                                                    </div>
                                                    <div class="kt-portlet_body">
                                                        <div class="kt-section">
                                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                                <table class="table table-striped m-table">
                                                                    <tbody>
                                                                    @foreach($members as $key => $member)
                                                                        <tr style="border-top: solid 2px #666;">
                                                                            <th>Relation</th>
                                                                            <td>{!! $member->relation !!}</td>
                                                                            <th>Name</th>
                                                                            <td>{!! $member->first_name !!} {!! $member->last_name !!}</td>
                                                                        </tr>
                                                                        <tr style="border-bottom: solid 2px #666;">
                                                                            <th>Date of Birth</th>
                                                                            <td>{!! $member->dob !!}</td>
                                                                            <th>Gender</th>
                                                                            @if($member->gender == 0)
                                                                                <td>Female </td>
                                                                            @else
                                                                                <td>Male</td>
                                                                            @endif
                                                                        </tr>
                                                                    @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                                @if($policy->has_member != 0)
                                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                                    <div class="kt-portlet__head">
                                                        <div class="kt-portlet__head-label">
                                                            <h3 class="kt-portlet__head-title">
                                                                Beneficiary Details
                                                            </h3>
                                                        </div>
                                                    </div>
                                                    <div class="kt-portlet_body">
                                                        <div class="kt-section">
                                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                                <table class="table table-striped m-table">
                                                                    <tbody>
                                                                    @foreach($beneficiaries as $key => $beneficiary)
                                                                        <tr style="border-top: solid 2px #666;">
                                                                            <th>Relation with beneficiary</th>
                                                                            <td>{!! $beneficiary->relation !!}</td>
                                                                            <th>Name</th>
                                                                            <td>{!! ucwords($beneficiary->first_name) !!}
                                                                                @if($beneficiary->middle_name) {!! ucwords($beneficiary->middle_name) !!} @endif
                                                                                {!! ucwords($beneficiary->last_name) !!}</td>
                                                                        </tr>
                                                                        <tr >
                                                                            <th>Date of Birth</th>
                                                                            <td>{!! $beneficiary->dob !!}</td>
                                                                            <th>Gender</th>
                                                                            @if($beneficiary->gender == 0)
                                                                                <td>Female </td>
                                                                            @else
                                                                                <td>Male</td>
                                                                            @endif
                                                                        </tr>
                                                                        <tr>
                                                                            <th>Available Identity Type</th>
                                                                            <td>
                                                                                @if($beneficiary->omang != null)
                                                                                    Omang
                                                                                @elseif($beneficiary->passport != null)
                                                                                    Passport
                                                                                @endIf
                                                                            </td>
                                                                            <th>Number</th>
                                                                            <td>
                                                                                @if($beneficiary->omang != null)
                                                                                    {{ $beneficiary->omang }}
                                                                                @elseif($beneficiary->passport != null)
                                                                                {{ $beneficiary->passport }}
                                                                            @endIf
                                                                            </td>
                                                                        </tr>
                                                                        <tr style="border-bottom: solid 2px #666;">
                                                                            <th>Payment</th>
                                                                            <td>{!! $beneficiary->payment !!} %</td>
                                                                            <th>&nbsp</th>
                                                                            <td></td>
                                                                        </tr>

                                                                    @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                                <div class="kt-portlet__head">
                                                    <div class="kt-portlet__head-label">
                                                        <h3 class="kt-portlet__head-title">
                                                            Customer Bank  Details
                                                        </h3>
                                                    </div>
                                                </div>
                                                <div class="kt-portlet_body">
                                                    <div class="kt-section">
                                                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                            <table class="table table-striped m-table">
                                                                <tbody>
                                                                <tr>
                                                                    @if($banking != NULL )
                                                                        <th>Myzaka/Orange Money Cell</th>

                                                                        <td>{!! $banking->billingCell !!}</td>

                                                                        <th>Bank Name</th>
                                                                        <td><span class="bankNumRealPay"> {!! $banking->bankName !!}</span> </td>
                                                                    @else
                                                                        <th>Myzaka/Orange Money Cell</th>
                                                                        <td></td>
                                                                        <th>Bank Name</th>
                                                                        <td></td>
                                                                    @endif
                                                                </tr>
                                                                <tr>
                                                                    @if($banking != NULL )
                                                                        <th>Branch Code</th>
                                                                        <td>{!! $banking->branchCode !!}</td>
                                                                        <th>Account Number</th>
                                                                        @if($banking->accountNumber != NULL)
                                                                            <td>{!! str_repeat("*", strlen($banking->accountNumber)-4) . substr($banking->accountNumber, -4) !!}</td>
                                                                        @endif
                                                                    @else
                                                                        <th>Branch Code</th>
                                                                        <td></td>
                                                                        <th>Account Number</th>
                                                                        <td></td>
                                                                    @endif
                                                                </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="kt-portlet__foot">
                                                    <div class="row">
                                                        <div class="col-12">

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                        <div class="tab-pane" id="kt_portlet_base_demo_3_2_transactions_content" role="tabpanel">
                                            <div class="kt-portlet__body" style="padding-top:0px !important">
                                                <!--begin: Datatable -->
                                                <!--begin: Datatable -->
                                                <ul class="nav nav-pills nav-tabs-btn nav-pills-btn-info" role="tablist">
                                                    <li class="nav-item">
                                                        <a class="nav-link active" data-toggle="tab" href="#kt_tabs_9_1" role="tab">
                                                            <span class="nav-link-icon"><i class="flaticon-clipboard"></i></span>
                                                            <span class="nav-link-title">Account View</span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" data-toggle="tab" href="#kt_tabs_9_2" role="tab">
                                                            <span class="nav-link-icon"><i class="flaticon-globe"></i></span>
                                                            <span class="nav-link-title">Recievable View</span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" data-toggle="tab" href="#kt_tabs_9_3" role="tab">
                                                            <span class="nav-link-icon"><i class="flaticon-layers"></i></span>
                                                            <span class="nav-link-title">Invoicing </span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" data-toggle="tab" href="#kt_tabs_9_4" role="tab">
                                                            <span class="nav-link-icon"><i class="flaticon-layers"></i></span>
                                                            <span class="nav-link-title">Sub Ledger </span>
                                                        </a>
                                                    </li>
                                                </ul>
                                                <div class="tab-content">
                                                    {{--Tab content 1--}}
                                                    <div class="tab-pane fade active show" id="kt_tabs_9_1" role="tabpanel">
                                                        <table class="table table-striped table-bordered table-hover table-checkable" id="account_table">
                                                            <thead>
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>ACCOUNTING DT.</th>
                                                                <th>TRANS TYPE</th>
                                                                <th>TRANS REF</th>
                                                                <th>USER NAME</th>
                                                                <th>ORIG TRANS</th>
                                                                <th>UNALLOCATED</th>
                                                                <th>DEBIT</th>
                                                                <th>CREDIT</th>
                                                                <th>BALANCE</th>
                                                                <th>SYSTEM DT.</th>
                                                            </tr>
                                                            </thead>
                                                        </table>
                                                    </div>
                                                    {{--Tab content 2--}}
                                                    <div class="tab-pane fade" id="kt_tabs_9_2" role="tabpanel">
                                                        <table class="table table-striped table-bordered table-hover table-checkable" id="recievable_table">
                                                            <thead>
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>ACCOUNTING DATE</th>
                                                                <th>TRANS TYPE</th>
                                                                <th>TRANS-SUB-TYPE</th>
                                                                <th>TRANS REF</th>
                                                                <th>EFF DATE</th>
                                                                <th>DEBIT</th>
                                                                <th>CREDIT</th>
                                                                <th>BALANCE</th>
                                                            </tr>
                                                            </thead>
                                                        </table>
                                                    </div>
                                                    {{--Tab content 3--}}
                                                    <div class="tab-pane fade" id="kt_tabs_9_3" role="tabpanel">
                                                        <table class="table table-striped table-bordered table-hover table-checkable" id="invoicing_table">
                                                            <thead>
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>INVOICE DT.</th>
                                                                <th>INVOICE NO.</th>
                                                                <th>PREMIUM</th>
                                                                <th>OTHER CHARGES</th>
                                                                <th>DUE AMOUNT</th>
                                                                <th>BALANCE</th>
                                                                <th>PMTS/ADJUST</th>
                                                                <th>INVOICE AMT.</th>
                                                                <th>DUE DATE</th>
                                                                <th>STATUS</th>
                                                                <th>ACTION</th>
                                                            </tr>
                                                            </thead>
                                                        </table>
                                                    </div>
                                                    <div class="tab-pane fade" id="kt_tabs_9_4" role="tabpanel">
                                                        <table class="table table-striped table-bordered table-hover table-checkable" id="sub_ledger_table">
                                                            <thead>
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>System Date</th>
                                                                <th>TRANS TYPE</th>
                                                                <th>TRANS REF</th>
                                                                <th>DEBIT</th>
                                                                <th>CREDIT</th>

                                                                <th>SUB-LEDGER</th>
                                                            </tr>
                                                            </thead>
                                                        </table>
                                                    </div>
                                                </div>
                                                <!--end: Datatable -->
                                            </div>
                                        </div>
                                        <div class="tab-pane" id="kt_portlet_base_demo_3_3_claims_content" role="tabpanel">
                                            <br>

                                            <div class="kt-portlet__body">
                                                <!--begin: Datatable -->
                                                <table class="table table-striped table-bordered table-hover table-checkable" id="claims_table">
                                                    <thead>
                                                    <tr>
                                                        <th>Claim Number</th>
                                                        <th>Claim Type</th>
                                                        <th>Status</th>
                                                        <th>Sub-Status</th>
                                                    </tr>
                                                    </thead>
                                                </table>
                                                <!--end: Datatable -->
                                            </div>
                                        </div>
                                        <div class="tab-pane" id="kt_portlet_base_demo_3_4_actionlog_content" role="tabpanel">
                                            <div class="kt-portlet__body">
                                                <!--begin: Datatable -->
                                                <!--begin: Datatable -->
                                                <table class="table table-striped table-bordered table-hover table-checkable" id="activity_table">
                                                    <thead>
                                                    <tr>
                                                        <th>Activity on</th>
                                                        <th>Description</th>
                                                        <th>Activity By</th>
                                                        <th>Activity Done</th>
                                                    </tr>
                                                    </thead>
                                                </table>
                                                <!--end: Datatable -->
                                            </div>
                                        </div>
                                        <div class="tab-pane" id="kt_portlet_base_demo_3_4_generateURL_content" role="tabpanel">
                                            <div class="kt-portlet__body">
                                                 <div class="tab-content">
                                                    <div class="kt-portlet">
                                                        <form action="#" id="urlPaymentForm" class="kt-form">
                                                            <div class="form-group">
                                                                <label for="urlCellphone">Confirm Customer CellPhone:</label>
                                                                <input  type="text" class="form-control col-6 urlCellphone" name="urlCellphone" aria-describedby="emailHelp"
                                                                                   placeholder="" value="{{$user->cellphone}}"  minlength="8" maxlength="8" pattern="[0-9]{8}">
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="urlPolicyNumber">Policy Number:</label>
                                                                <input  type="text" class="form-control col-6" name="urlPolicyNumber" aria-describedby="emailHelp"
                                                                                value="{{$policy->policyNumber}}">
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="urlAmount">Requested Amount:</label>
                                                                <input  type="text" class="form-control col-6 urlAmount" name="urlAmount" title="Enter the amount" placeholder="Enter the Amount" aria-describedby="emailHelp"
                                                            pattern="[0-9]{8}" value="{{$policy->premium}}" readonly>
                                                            </div>

                                                            <div class="form-group">
                                                                <style>
                                                                    .loader {
                                                                        border: 5px solid #f3f3f3 ; /* Light grey */
                                                                        border-top: 5px solid #1dc9b7; /* Blue */
                                                                            border-radius: 50%;
                                                                            width: 30px;
                                                                            height: 30px;
                                                                             animation: spin 2s linear infinite;
                                                                             display:none;
                                                                    }

                                                                        @keyframes spin {
                                                                        0% { transform: rotate(0deg); }
                                                                                100% { transform: rotate(360deg); }
                                                                        }
                                                                </style>
                                                                <div class="loader"></div>
                                                                <button type="button" class="btn btn-brand sendURL">Generate Payment URL</button>
                                                            </div>
                                                        </form>

                                                        <div class="alert alert-outline-success  alert-dismissible fade show" role="alert" id="urlalert" style="display:none" >
                                                              <strong class="text text-warning"><i class="flaticon-warning"></i> Excellent!! </strong>Payment Url Sent
                                                              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                                     <span aria-hidden="true">&times;</span>
                                                              </button>
                                                        </div>
                                                        <table class="table table-striped table-bordered table-hover table-checkable" id="paymentUrlTable">
                                                            <thead>
                                                            <tr>
                                                            <th>Id</th>
                                                            <th>Created on</th>
                                                            <th>Amount</th>
                                                            <th>Sent From</th>
                                                            <th>Url</th>
                                                            <th>Status</th>
                                                            <th>Actions</th>
                                                            </tr>
                                                            </thead>
                                                        </table>
                                                    </div>
                                                 </div>

                                            </div>
                                        </div>
                                        <div class="tab-pane" id="kt_portlet_base_demo_3_5_bank_content" role="tabpanel">
                                            <div class="kt-portlet__body">
                                                <div class="tab-content">
                                                    <div class="kt-portlet">
                                                        <form id="bankingForm" action="{{ route('admin.policy.updateBanking',$policy->id) }}"
                                                              method="POST" class="kt-form">
                                                            <input type="hidden" name="_method" value="POST">
                                                            <!-- CSRF Token -->
                                                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                                            <div class="kt-portlet__body">
                                                                <h3 class="kt-heading kt-heading--md">
                                                                    Customer Bank Details:
                                                                </h3>
                                                                <input type="hidden" name="policy_id" value="{{$policy->id}}">
                                                            <input type="hidden" name="premium" value="{{$policy->premium}}">
                                                                <div class="row">
                                                                    <div id="billingMethodField" class="col-lg-6">
                                                                        <div class="form-group">
                                                                            <label for="exampleSelect1">Billing Method:</label>
                                                                            <select id="billing" class="form-control kt_selectpicker" title="Please select customers preferred billing method" name="billingMethod">
                                                                            </select>

                                                                        </div>
                                                                    </div>
                                                                    <div id="billingCellField" class="col-lg-6">
                                                                        <div class="form-group">
                                                                            <label>Myzaka/Orange Money Cell:</label>
                                                                            <input id="billingCell" type="text" class="form-control" name="billingCell" aria-describedby="emailHelp" title="Enter orange money cell" placeholder="Myzaka/Orange Cell" value="{!! $banking->billingCell !!}" minlength="8" maxlength="8" pattern="[0-9]{8}">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div id="bankNameField" class="col-lg-6">
                                                                        <div class="form-group">
                                                                            <label for="bankname">Bank Name:</label>
                                                                            <style>
                                                                                .kt-spinner.kt-spinner--input.kt-spinner--left::before {
                                                                                    z-index:9999;
                                                                                    margin-left: 24px;
                                                                                }
                                                                            </style>
                                                                            <div id="bankNameSpinner" style="display:contents;" class="form-group kt-spinner kt-spinner--sm kt-spinner--success kt-spinner--left kt-spinner--input"></div>
                                                                            <select id="bankNumDropDown" class="form-control kt_selectpicker" value="{!! $banking->bankName !!}" title="Please select customers bank" name="bankName" ></select>
                                                                        </div>
                                                                    </div>
                                                                    <div id="bankBranchField" class="col-lg-6">
                                                                        <div class="form-group">
                                                                            <label>Branch Code:</label>
                                                                            <div id="branchNameSpinner" style="display:contents;" class="form-group kt-spinner kt-spinner--sm kt-spinner--success kt-spinner--left kt-spinner--input"></div>
                                                                            <select id="bankBranchDropDown" value="{!! $banking->branchCode !!}" class="form-control kt_selectpicker" title="Please select customers branch" name="branchCode"></select>
                                                                        </div>
                                                                    </div>
                                                                    <div id="accountNumberField" class="col-lg-6">
                                                                        <div class="form-group">
                                                                            <label>Account Number:</label>
                                                                            <input id="accountNumber" type="password" class="form-control" value="{!! $banking->accountNumber !!}" name="accountNumber" title="Please enter customer account number" value="}" autocomplete="off" aria-describedby="emailHelp" placeholder="Account Number" pattern="[0-9]{1,15}"  maxlength="15" >
                                                                        </div>
                                                                    </div>
                                                                    <div id="accountTypeField" class="col-lg-6">
                                                                        <div class="form-group">
                                                                            <label>Account Type:</label>
                                                                            <select id="accountType" class="form-control kt_selectpicker" name="bankAccountType" >
                                                                                <option>Please Select the Bank Account Type</option>
                                                                                @if(old('bankAccountType') == 1)
                                                                                    <option value="1" selected>Cheque</option>
                                                                                    <option value="2" >Savings</option>
                                                                                @else
                                                                                    <option value="1" >Cheque</option>
                                                                                    <option value="2" selected >Savings</option>
                                                                                @endif
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <input id="billingOptionHidden" type="hidden" class="form-control" name="billingOption">
                                                                </div>
                                                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                                            </div>
                                                            <div class="kt-portlet__foot">
                                                                <div class="kt-form__actions">
                                                                    <div class="row">
                                                                        <div class="col-12">

                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                        </div>
                    </div>
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


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

<script>
    $(document).ready(function(){
        $('.sendURL').click(function(e) {
            const urlCellphone = $('.urlCellphone').val();
            const urlAmount = $('.urlAmount').val();
            // var policy_id = {
            //         $policy->id
            //     }

            // var encodePolicyId = $.base64.encode(policy_id)
            console.log("sendURL");
            e.preventDefault();
            $.ajax({

                type: 'post',
                beforeSend: function() {
                    $('.loader').css("display", "block");
                },
                url: "{{ route('sendPaymentUrlGraphite') }}",
                data: {
                    "_token": "{{ csrf_token() }}",
                    cellphone: urlCellphone,
                    policy_id: policy_id,
                    amount: urlAmount

                },
                dataType: 'JSON',
                success: function(response) {
                    console.log(response);
                    $('.loader').css("display", "none");

                    Toastify({
                        text: "Url generated and sent",
                        duration: 6000,
                        newWindow: true,
                        gravity: "top", // `top` or `bottom`
                        center: true, // `true` or `false`
                        backgroundColor: "#1dc9b7",
                    }).showToast();
                    location.reload();

                },
                error: function(error) {
                    $('.loader').css("display", "none");
                    Toastify({
                        text: "Problem with SMS service please try again later",
                        duration: 4000,
                        newWindow: true,
                        gravity: "top", // `top` or `bottom`
                        center: true, // `true` or `false`
                        backgroundColor: "#CC0000",
                    }).showToast();
                    console.log(error.responseJSON);
                }
            });
        });
    });
</script>
</body>
<!-- end::Body -->
</html>
