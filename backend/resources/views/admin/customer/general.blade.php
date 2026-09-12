<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<!-- begin::Body -->
<body  class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >
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
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Claim </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                </div>
            </div>
            @if($claims && $claims->status == 'Pending')
                <a style="margin-left:800px" href="{!! route('admin.claims.approved',[$claims->id]) !!}" class="btn btn-sm btn-success btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="right" data-original-title="Approved"> <span class="kt-opacity-11" id="">Approve</span>&nbsp; </a>
                <a href="{!! route('admin.claims.rejects',[$claims->id]) !!}" class="btn btn-sm btn-danger btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Reject"> <span class="kt-opacity-11" id="">Reject</span>&nbsp; </a>
            @endif
            @if($claims && $claims->status == 'Approved')
                <a href="{!! route('admin.claims.rejects',[$claims->id]) !!}" class="btn btn-sm btn-danger btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Reject"> <span class="kt-opacity-11" id=""> Reject</span>&nbsp; </a>
            @endif
            @if($claims && $claims->status == 'Rejected')
                <a href="{!! route('admin.claims.approved',[$claims->id]) !!}" class="btn btn-sm btn-success btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="right" data-original-title="Approve"> <span class="kt-opacity-11" id="">Approve</span>&nbsp; </a>
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
                            @if($claims->claim_type == 'Life')
                            <li class="nav-item">
                                <a class="nav-link @if(!session()->has('step')) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_1_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Policy Details </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link @if(session()->get('step') == 2) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_2_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Process Claim  </a>
                            </li>
                            <li class="nav-item">

                            </li>
                            @endif
                            @if($claims->claim_type == 'Glass')
                            <li class="nav-item">
                                <a class="nav-link @if(!session()->has('step')) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_1_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Policy Details </a>
                            </li>
                            <li class="nav-item">
                                 <a class="nav-link @if(session()->get('step') == 2) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_2_tab_content" role="tab"> <i class="flaticon2-pie-chart-2" aria-hidden="true"></i>Process Claim  </a>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link @if(session()->get('step') == 3) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_3_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Supplier </a>
                            </li>
                            <li class="nav-item">
                                 <a class="nav-link @if(session()->get('step') == 4) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_4_tab_content" role="tab"> <i class="flaticon2-chronometer" aria-hidden="true"></i>Invoice </a>
                            </li>
                            @endif
                            @if($claims->claim_type == 'Accident')
                            <li class="nav-item">
                                 <a class="nav-link @if(!session()->has('step')) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_1_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Policy Details </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link @if(session()->get('step') == 5) active @endif" data-toggle="tab" href="#kt_portlet_base_demo_3_5_tab_content" role="tab"> <i class="flaticon2-heart-rate-monitor" aria-hidden="true"></i>Claim Details </a>
                                    </li>
                                @endif
                        </ul>
                    </div>
                </div>
            </div>
            <div class="tab-content">
                <div class="tab-pane @if(!session()->has('step')) active @endif" id="kt_portlet_base_demo_3_1_tab_content" role="tabpanel">
                    <div class="row">
                        <div class="col-md-12">
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
                                                    <td>{!! $user->firstName !!} {!! $user->lastName !!}</td>
                                                    <th>Email</th>
                                                    <td>{!! $user->email !!}</td>
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
                                                    <td>{!! $user->profile->dob !!}</td>
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
                                                            <img src="{{Storage::disk('s3')->url($kyc->driving_license)}}" width="100%" height="auto" >
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                    </div>


                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                                        @if($kyc->omang == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <img src="{{Storage::disk('s3')->url($kyc->omang)}}" width="100%" height="auto" >
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                                        @if($kyc->proof_residence == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <img src="{{Storage::disk('s3')->url($kyc->proof_residence)}}" width="100%" height="auto" >
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                        </div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Proof of Income</h3>
                                                        @if($kyc->proof_income == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <img src="{{Storage::disk('s3')->url($kyc->proof_income)}}" width="100%" height="auto" >
                                                        @endif
                                                        <div class="kt-avatar" style="float: left; clear: left;">
                                                        </div>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <h3 class="col-form-label" style="float: left;">Passport</h3>
                                                        @if($kyc->passport == NULL)
                                                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                        @else
                                                            <img src="{{Storage::disk('s3')->url($kyc->passport)}}" width="100%" height="auto" >
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
                                @if($policy->kyc_recipient != 0)
                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                    <div class="kt-portlet__head">
                                        <div class="kt-portlet__head-label">
                                            <h3 class="kt-portlet__head-title">
                                                Recipient KYC
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
                                                            @if($recipientKyc->driving_license == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <img src="{{Storage::disk('s3')->url($recipientKyc->driving_license)}}" width="100%" height="auto" >
                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                        </div>


                                                        <div class="col-md-2">
                                                            <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                                            @if($recipientKyc->omang == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <img src="{{Storage::disk('s3')->url($recipientKyc->omang)}}" width="100%" height="auto" >
                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                        </div>

                                                        <div class="col-md-2">
                                                            <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                                            @if($recipientKyc->proof_residence == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <img src="{{Storage::disk('s3')->url($recipientKyc->proof_residence)}}" width="100%" height="auto" >
                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;">
                                                            </div>
                                                        </div>

                                                        <div class="col-md-2">
                                                            <h3 class="col-form-label" style="float: left;">Proof of Income</h3>
                                                            @if($recipientKyc->proof_income == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <img src="{{Storage::disk('s3')->url($recipientKyc->proof_income)}}" width="100%" height="auto" >
                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;">
                                                            </div>
                                                        </div>

                                                        <div class="col-md-2">
                                                            <h3 class="col-form-label" style="float: left;">Passport</h3>
                                                            @if($recipientKyc->passport == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <img src="{{Storage::disk('s3')->url($recipientKyc->passport)}}" width="100%" height="auto" >
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
                                                    <td>P{!! $policy->premium !!}</td>
                                                </tr>
                                                @if($policy->plan_id != NULL)
                                                    <tr>
                                                        <th>product Plan</th>
                                                        <td>{!! $productPlan->name !!}</td>
                                                    </tr>
                                                @endif
                                                @foreach($details as $detail)
                                                    <tr>
                                                        <th>{!! $detail->name !!}</th>
                                                        <td>{!! $detail->value_name !!}</td>
                                                    </tr>
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
                                                        <td>{!! $vehicle->purpose !!}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Year of Manufacturing</th>
                                                        <td>{!! $vehicle->year !!}</td>
                                                        <th>Make</th>
                                                        <td>{!! $vehicle->make !!}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Model</th>
                                                        <td>{!! $vehicle->model !!}</td>
                                                        <th>Number of Cylinder</th>
                                                        <td>{!! $vehicle->cylinders !!}</td>

                                                    </tr>
                                                    <tr>
                                                        <th>Number of Seats</th>
                                                        <td>{!! $vehicle->seats !!}</td>
                                                        <th>Cubic Capacity</th>
                                                        <td>{!! $vehicle->cubic_capacity !!}</td>
                                                    </tr>

                                                    <tr>
                                                        <th>Engine Number</th>
                                                        <td>{!! $vehicle->engineNo !!}</td>
                                                        <th>Is the vehicle used for private use?</th>
                                                        @if($vehicle->is_private == 0)
                                                            <td>No</td>
                                                        @else
                                                            <td>Yes</td>
                                                        @endif
                                                    </tr>
                                                    <tr>
                                                        <th>Is it imported?</th>
                                                        @if($vehicle->is_imported == 0)
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
                                                                <img src="{{Storage::disk('s3')->url($vehicle->left)}}" width="100%" height="auto" >
                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <h3 class="col-form-label" style="float: left;">Right</h3>
                                                            @if($vehicle->right == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <img src="{{Storage::disk('s3')->url($vehicle->right)}}" width="100%" height="auto" >

                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <h3 class="col-form-label" style="float: left;">Back</h3>
                                                            @if($vehicle->back == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <img src="{{Storage::disk('s3')->url($vehicle->back)}}" width="100%" height="auto" >
                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <h3 class="col-form-label" style="float: left;">Front</h3>
                                                            @if($vehicle->front == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <img src="{{Storage::disk('s3')->url($vehicle->front)}}" width="100%" height="auto" >

                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                                        </div>


                                                        <div class="col-md-2">
                                                            <h3 class="col-form-label" style="float: left;">Vehicle Registration</h3>
                                                            @if($vehicle->vehicleRegistration == NULL)
                                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                                            @else
                                                                <img src="{{Storage::disk('s3')->url($vehicle->vehicleRegistration)}}" width="100%" height="auto" >

                                                            @endif
                                                            <div class="kt-avatar" style="float: left; clear: left;"></div>

                                                        </div>
                                                    </div>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

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
                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
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
                                                    @foreach($policyMotorItems as $policyMotorItem)
                                                        <tr>
                                                            @if($policyMotorItem->item_name == 1)
                                                                <td>Item 1 </td>
                                                            @elseif($policyMotorItem->item_name == 2)
                                                                <td>Item 2</td>
                                                            @else
                                                                <td>Item 3</td>
                                                            @endif
                                                            <td>{!! $policyMotorItem->item_value !!} </td>

                                                        </tr>
                                                    @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
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
                                @if($policy->has_member != 0)
                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                    <div class="kt-portlet__head">
                                        <div class="kt-portlet__head-label">
                                            <h3 class="kt-portlet__head-title">
                                                Family Details
                                            </h3>
                                        </div>
                                    </div>
                                    <div class="kt-portlet_body">
                                        <div class="kt-section">
                                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                                <table class="table table-striped m-table">
                                                    <tbody>
                                                    @foreach($members as $key => $member)
                                                        <tr style="border-top: solid 3px #666;">
                                                            <th>Relation</th>
                                                            <td>{!! $member->relation !!}</td>
                                                            <th>Name</th>
                                                            <td>{!! $member->first_name !!} {!! $member->last_name !!}</td>
                                                        </tr>
                                                        <tr style="border-bottom: solid 3px #666;">
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
                                                    <th>Myzaka/Orange Money Cell</th>
                                                    <td>{!! $banking->billingCell !!}</td>
                                                    <th>Bank Name</th>
                                                    <td>{!! $banking->bankName !!} </td>
                                                </tr>
                                                <tr>
                                                    <th>Branch Code</th>
                                                    <td>{!! $banking->branchCode !!}</td>
                                                    <th>Account Number</th>
                                                    <td>{!! substr_replace($banking->accountNumber, str_repeat("X", 10), 0, 9) !!}</td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="kt-portlet__foot">
                                    <div class="row">
                                        <div class="col-12">
                                            <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}" >Cancel</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane @if(session()->get('step') == 5) active @endif" id="kt_portlet_base_demo_3_5_tab_content" role="tabpanel">
                    @include('admin.claims.claim_details')
                </div>
                <div class="tab-pane @if(session()->get('step') == 2) active @endif" id="kt_portlet_base_demo_3_2_tab_content" role="tabpanel">
                    @if($claims->claim_type == 'Glass')
                        @include('admin.claims.vehicle')
                    @endif
                    @if($claims->claim_type == 'Life')
                        @include('admin.claims.life')
                    @endif
                </div>

                <div class="tab-pane @if(session()->get('step') == 3) active @endif" id="kt_portlet_base_demo_3_3_tab_content" role="tabpanel">
                    <!--begin::Portlet-->
                    <div class="kt-portlet kt-portlet--height-fluid">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Supplier Quotes
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <!--begin::Section-->
                            <div class="kt-section">
                                <div class="kt-section__content">
                                    <table class="table supplierTable table-head-noborder">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Supplier</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @if(count($supplierQuotes))
                                            @foreach($supplierQuotes as $key => $quote)
                                                <tr>
                                                    <th scope="row">{!! $key+1 !!}</th>
                                                    <td>{!! $quote->supplier->supplierName !!}</td>
                                                    <td>@if($quote->total != NULL)P {!! $quote->total !!} @else - @endif</td>
                                                    <td>
                                                        @if($quote->total == NULL)
                                                            <span class="kt-font-bold kt-font-danger">Not Received</span>
                                                        @elseif($quote->total != NULL && $quote->status == 0)
                                                            <span class="kt-font-bold kt-font-brand">Quote Received</span>
                                                        @else <span class="kt-font-bold kt-font-success">PO SENT</span>
                                                        @endif
                                                        @if($lowestQuote && $lowestQuote->id == $quote->id && $claims->customer_selected == 0)
                                                            <span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill">Lowest Quote</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($quote->total != NULL)
                                                            @if($claims->supplier_id == NULL && $quote->status != 1 && $claims->customer_selected == 0)
                                                                <a href="{!! route('admin.claims.acceptQuote',['quote_id'=>$quote->id]) !!}" class="btn btn-sm btn-success" @if($lowestQuote && $lowestQuote->id != $quote->id) id="selectOther" @endif>Select</a>
                                                            @endif
                                                            @if($claims->customer_selected == 1 && $quote->status != 1)
                                                                <a href="{!! route('admin.claims.acceptQuote',['quote_id'=>$quote->id]) !!}" class="btn btn-sm btn-success">Sent PO</a>
                                                            @endif
                                                            <a target="_blank" href="{!! route('admin.supplier.quoteView',['quote_id'=>$quote->id]) !!}" class="btn btn-sm btn-danger">View</a>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="5" style="text-align: center;">No Quote Request Sent</td>
                                            </tr>
                                        @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!--end::Section-->
                        </div>
                    </div>
                    <!--end::Portlet-->
                </div>
                <div class="tab-pane @if(session()->get('step') == 4) active @endif" id="kt_portlet_base_demo_3_4_tab_content" role="tabpanel">
                    <!--begin::Portlet-->
                    <div class="kt-portlet kt-portlet--height-fluid">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Upload Supplier Invoice
                                </h3>
                            </div>

                        </div>
                        <form id="claimsUpdate"  action="{{ url('admin/claims/invoiceUpload/'.$claims->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">

                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />

                            <div class="kt-portlet__body">
                                <div class="kt-widget-4">
                                    <div class="form-group row">
                                        <input id="policy_id" class="form-control" type="hidden" name="policy_id" value="{!! $policy->id !!}">
                                        <label for="example-text-input" class="col-3 col-form-label">Upload Invoice</label>
                                        <div class="col-2">
                                            <div class="kt-avatar" id="death_certificate" style="float: left; clear: left;">
                                                @if($claims->invoice == NULL)
                                                    <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                @else
                                                    <div class="kt-avatar__holder" style="background-image:url({!! Storage::disk('s3')->url($claims->invoice) !!})"></div>
                                                @endif
                                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                    <i class="fa fa-pen"></i>
                                                    <input type='file'  name="invoice" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                                </label>
                                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                            </div>

                                        </div>
                                        <div class="col-7">
                                            <textarea class="form-control" name="note" placeholder="Add Notes" id="description" rows="3" required>{!! $claims->note !!}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--solid">
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-3"></div>
                                        <div class="col-9" style="margin-left: 300px">
                                            <button class="btn btn-brand" type="submit">Submit</button>
                                            <a class="btn btn-secondary" href="{{ route('admin.claims.index') }}" >Cancel</a>

                                        </div>
                                    </div>
                                </div>
                            </div>

                        </form>
                    </div>
                    <!--end::Portlet-->
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
<input type="hidden" value="{!! $claims->status !!}" id="statusField">


<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

<div class="modal fade" id="select_supplier" tabindex="-1" role="dialog" aria-labelledby="supplier_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Select Supplier Manually</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
            </div>
            <form id="acceptQuote" action="" method="POST" enctype="multipart/form-data" class="kt-form">
                <!-- CSRF Token -->
                <input type="hidden" name="_token" value="{{ csrf_token() }}" >
                <div class="modal-body">
                    <h6>Why do you want to overwrite system selection ?</h6>
                    <textarea class="form-control" name="reason"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" value="Submit" class="btn btn-brand">Select Supplier</button>
                    <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('admin.layouts.scripts')
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>



<!--end:: Global Optional Vendors -->

<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script>

    $(".supplierTable").on("click", "a#selectOther" , function(event) {
        event.preventDefault();
        $("#acceptQuote").attr('action', $(this).attr("href"));
        $('#select_supplier').modal('show');
    });


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

    // Avatar Class definition
    var KTAvatarDemo = function() {

        return {
            // Init demos
            init: function() {
                var avatar1 = new KTAvatar('incidentFront');
                var avatar2 = new KTAvatar('incidentBack');
                var avatar3 = new KTAvatar('incidentRight');
                var avatar4 = new KTAvatar('incidentLeft');
                var death_certificate = new KTAvatar('death_certificate');
            }
        };
    }();

    jQuery(document).ready(function() {
        KTBootstrapDatepicker.init();

        // Class initialization on page load
        KTAvatarDemo.init();

    });
    @if(session()->has('message'))
    Toastify({
        text: "{{session()->get('message')}}",
        duration: 4000,
        newWindow: true,
        gravity: "top", // `top` or `bottom`
        positionRight: true, // `true` or `false`
        backgroundColor: "#00C851",
    }).showToast();


    @endif
</script>

<script>
    function setToEdit() {
        var x = document.getElementById("claimEditDiv");
        var y = document.getElementById("claimLabelDiv");
        x.style.display = "block";
        y.style.display = "none";
    }

    function setToView(){
        var x = document.getElementById("claimEditDiv");
        var y = document.getElementById("claimLabelDiv");
        x.style.display = "none";
        y.style.display = "block";
    }

</script>

<script>

    function setVehicleToEdit() {
        var x = document.getElementById("vehicleEditDiv");
        var y = document.getElementById("vehicleLabelDiv");
        x.style.display = "block";
        y.style.display = "none";

        // Class initialization on page load
        KTAvatarDemo.init();
    }

    function setVehicleToView(){
        var x = document.getElementById("vehicleEditDiv");
        var y = document.getElementById("vehicleLabelDiv");
        x.style.display = "none";
        y.style.display = "block";
    }

</script>

<script>

    function setClaimToEdit() {
        var x = document.getElementById("claimEditDiv");
        var y = document.getElementById("claimLabelDiv");
        x.style.display = "block";
        y.style.display = "none";

        // Class initialization on page load
        KTAvatarDemo.init();
    }

    function setClaimToView(){
        var x = document.getElementById("claimEditDiv");
        var y = document.getElementById("claimLabelDiv");
        x.style.display = "none";
        y.style.display = "block";
    }

</script>

<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
        // Private functions

        var demo1 = function () {
            $( "#claimsUpdate" ).validate({
                // define validation rules
                rules: {
                    claim_number: {
                        required: true
                    },

                    location: {
                        required: true
                    },
                    claim_sub_status: {
                        required: true
                    },
                    claim_status: {
                        required: true
                    },
                    paid_amount: {
                        required: true
                    },
                    reserve_amount: {
                        required: true
                    },
                    first_visit: {
                        required: true
                    },
                    claim_allocated_on: {
                        required: true
                    },
                    claim_allocated_to: {
                        required: true
                    },
                    co_attorney_assigned_date: {
                        required: true
                    },
                    attorney_assigned_date: {
                        required: true
                    },
                    co_attorney: {
                        required: true
                    },
                    primary_attorney: {
                        required: true
                    },
                    event_name: {
                        required: true
                    },
                    representative: {
                        required: true
                    },
                    incident_date: {
                        required: true
                    },
                    loss_type: {
                        required: true
                    },
                    claim_type: {
                        required: true
                    },
                    claim_sub_type: {
                        required: true
                    },
                    reported_by: {
                        required: true
                    },
                    loss_description: {
                        required: true
                    },
                },

                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    KTUtil.scrollTo("#claimsUpdate", -200);
                },

                submitHandler: function (form) {
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
    function vehicleMsg(){
        var isChecked=document.getElementById("vehicleValue").checked;
        if (isChecked){
            document.getElementById("Msg").innerHTML="Yes";
            document.getElementById("Msg").style.color="cornflowerblue";

            var x = document.getElementById("sup");
            x.style.display = "block";

            $('#sup').delay(100).slideDown(500);
            $('#sup').rules('add',  { required: true, messages: { required: "Please Select Coverages" } });
        }
        else {
            document.getElementById("Msg").innerHTML="No";
            document.getElementById("Msg").style.color="#ff4d4d";

            var x = document.getElementById("sup");
            x.style.display = "none";

            $('#sup').delay(100).slideUp(500);
            $('#sup').rules('remove',  'required');
        }

    }
</script>
</body>
<!-- end::Body -->
</html>