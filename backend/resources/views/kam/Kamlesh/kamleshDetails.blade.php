<!DOCTYPE html>

<html lang="en">
<!-- begin::Head -->
@include('Admin.Layout.header')


<!-- end::Head -->
<!-- begin::Body -->

<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-page--loading">
    <!-- begin:: Header Mobile -->
    <div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed ">
        <div class="kt-header-mobile__logo">
            <a>
                <img alt="Logo" src="assets/media/logos/logo-3.png" />
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

    <!-- begin:: Page -->

    @include('Admin.Layout.sidebar')
    @include('Admin.Layout.topNav')
    <!-- begin:: Content -->
    <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
        <div class="loading">Loading&#8230;</div>
        <div class="row ol-lg-12">
            <div class=" col-lg-3 text-left policyno"><br>
                <h1>Policy No # {{$policies->policyNumber}}</h1>
            </div>

            <div class="col-lg-3 text-center">
                <br>
                <label>
                    @if ($policies->isActive ==1 )
                    <h5>Compliance: <span style="color:#00C851"> Done</span></h5>
                    @else

                    <h5>Compliance: <span style="color:#CC0000"> Not Done</span></h5>
                    @endif
                    <span></span>
                </label>
            </div>
            <div class="col-lg-3 text-center">
                <br>
                <label>
                    @if ($policies->isActive ==1 )
                    <h5>Policy Status: <span style="color:#00C851"> Activated</span></h5>
                    @else

                    <h5>Policy Status: <span style="color:#CC0000"> Deactivated</span></h5>
                    @endif
                    <span></span>
                </label>
            </div>

            <div class=" col-lg-3 text-right "><br>
                <h5>Agent: <span style="color:#00C851">{{$agentDetails->firstName}} {{$agentDetails->lastName}}</span></h5>
            </div>

        </div>
        <!--begin::Dashboard 4-->
        <!--begin::Row-->

        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#policy_details"> <i class="flaticon-time-2 kt-font-success"></i> Policy details </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#transactions"> <i class="la la-dollar kt-font-danger"></i> Transactions </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#claim_history"><i class="la la-exclamation-circle kt-font-success"></i> Claim History </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#activity_log"> <i class="la la-comment"></i> Activity Log </a>
            </li>
        </ul>

        <div class="tab-content">
            <!--begin: Form Wizard Form-->
            <div class="tab-pane active" id="policy_details" role="tabpanel">
            <div class="clearfix">
      
      <span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--metal float-right">
      <form class="kt-form">
					<div class="form-group row">
						<label class="col-form-label kt-font-danger"><h5>EDIT &nbsp;</h5></label>
					 
                        <label>
                            <input type="checkbox" name="setToEdit" id="setToEdit">
                            <span></span>
                        </label>
					</div>
</form>
                    </span>
       
    </div>
                <div class="row viewForm">
                    <!--begin::policy-->
                    <div class="col-lg-6 col-xl-6 order-lg-1 order-xl-1">
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--height-fluid kt-widget-17">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Policy
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <table class="table table-hover table-striped">
                                        <tr>
                                            <th>Name:</th>
                                            <td>{{$userDetails->firstName}} {{$userDetails->lastName}}</td>
                                        </tr>
                                        <tr>
                                            <th>Cellphone:</th>
                                            <td>{{$userDetails->cellphone}}</td>
                                        </tr>
                                        @if($userDetails->email == null)
                                        <tr>
                                            <th>Email:</th>
                                            <td>N/A</td>
                                        </tr>
                                        @else
                                        <tr>
                                            <th>Email:</th>
                                            <td>{{$userDetails->email}}</td>
                                        </tr>


                                        @endif
                                        <tr>
                                            <th>Address:</th>
                                            @if($userDetails->address == null)
                                            <td>N/A</td>

                                            @else
                                            <td>{{$userDetails->address}}</td>


                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Omang ID:</th>
                                            <td>{{$userDetails->omang}}</td>
                                        </tr>
                                        @if($userDetails->passport == null)
                                        <tr>
                                            <th>Passport number:</th>
                                            <td>N/A</td>
                                        </tr>
                                        @else
                                        <tr>
                                            <th>Passport number:</th>
                                            <td>{{$userDetails->passport}}</td>
                                        </tr>
                                        @endif
                                        <tr>
                                            <th>DOB:</th>
                                            <td>{{$userDetails->dob->format('d M Y')}}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--md">
                                <div class="kt-widget-17__foot">
                                    <div class="kt-widget-17__foot-info"></div>
                                    <div class="kt-widget-17__foot-toolbar"> </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Portlet-->
                    </div>
                    <!--end::policy-->
                    <!--begin::KYC-->
                    <div class="col-lg-6 col-xl-6 order-lg-1 order-xl-1">
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--height-fluid kt-widget-17">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        KYC
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <table class="table table-hover table-striped">
                                        <tr>
                                            <th>Drivers License:</th>
                                            <td><span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--success">
                                                    <label>
                                                        @if ($customerKYC->driversLicense == 'Not Submitted')

                                                        <div class="file btn  btn-primary">
                                                            <form action="{{Route('uploadDriversLicense')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                {{csrf_field()}}
                                                                <i class="la la-upload"></i>Upload Drivers License
                                                                <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                                                <input type="hidden" name="lastName" value="{{$userDetails->lastName}}" />
                                                                <input type="hidden" name="id" value="{{$userDetails->id}}" />

                                                                <input type="file" name="driversLicense" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" />

                                                            </form>
                                                        </div>

                                                        @else
                                                        <input type="checkbox" checked="checked" disabled>
                                                        <button id="driversPreview" data-toggle="modal" class="btn btn-info" data-target="#image-gallery">View {{$userDetails->firstName}}'s Drivers License</button>


                                                        @endif
                                                        <span></span>
                                                    </label>
                                                </span></td>
                                        </tr>
                                        <tr>
                                            <th>Omang ID:</th>
                                            <td><span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--success">
                                                    <label>
                                                        @if ($customerKYC->omang == 'Not Submitted')


                                                        <div class="file btn  btn-primary">
                                                            <form action="{{Route('uploadOmang')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                {{csrf_field()}}
                                                                <i class="la la-upload"></i>Upload Omang
                                                                <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                                                <input type="hidden" name="lastName" value="{{$userDetails->lastName}}" />
                                                                <input type="hidden" name="id" value="{{$userDetails->id}}" />

                                                                <input type="file" name="omang" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" />

                                                            </form>
                                                        </div>

                                                        @else
                                                        <input type="checkbox" checked="checked" disabled>
                                                        <button id="omangPreview" data-toggle="modal" class="btn btn-info" data-target="#image-gallery">View {{$userDetails->firstName}}'s Omang</button>

                                                        @endif
                                                        <span></span>
                                                    </label>
                                                </span></td>
                                        </tr>
                                        <tr>
                                            <th>Vehicle Registration:</th>
                                            <td><span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--success">
                                                    <label>
                                                        @if ($customerKYC->vehicleRegistration == 'Not Submitted')

                                                        <div class="file btn  btn-primary">
                                                            <form action="{{Route('uploadBlueBook')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                {{csrf_field()}}
                                                                <i class="la la-upload"></i>Upload Vehicle Registration Document
                                                                <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                                                <input type="hidden" name="lastName" value="{{$userDetails->lastName}}" />
                                                                <input type="hidden" name="id" value="{{$userDetails->id}}" />

                                                                <input type="file" name="bluebook" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" />

                                                            </form>
                                                        </div>



                                                        @else
                                                        <input type="checkbox" checked="checked" disabled>
                                                        <button id="bluebookPreview" data-toggle="modal" class="btn btn-info" data-target="#image-gallery">View {{$userDetails->firstName}}'s Vehicle Registration</button>

                                                        @endif
                                                        <span></span>
                                                    </label>
                                                </span></td>
                                        </tr>


                                    </table>
                                </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--md">
                                <div class="kt-widget-17__foot">
                                    <div class="kt-widget-17__foot-info"></div>
                                    <div class="kt-widget-17__foot-toolbar">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Portlet-->
                    </div>
                    <!--end::KYC-->

                    <!--begin::Policy Plan-->
                    <div class="col-lg-6 col-xl-6 order-lg-1 order-xl-1">
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--height-fluid kt-widget-17">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Vehicle Details
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <table class="table table-hover table-striped">
                                        <tr>
                                            <th>Vehicle Make</th>
                                            <td>
                                                <div class="dropdown">

                                                    {{$vehicle->make}}

                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Vehicle Model:</th>
                                            <td>{{$vehicle->model}} </td>
                                        </tr>
                                        <tr>
                                            <th>Vehicle Model:</th>
                                            <td>{{$vehicle->year}} </td>
                                        </tr>

                                        <tr>
                                            <th>Vehicle Licence Plate</th>
                                            <td>{{$vehicle->vehiclePlate}}</td>
                                        </tr>
                                        <tr>
                                            <th>Vehicle Condition</th>
                                            <td>{{$vehicle->condition}}</td>
                                        </tr>
                                        <tr>
                                            <th>Vehicle Milage</th>
                                            <td>{{$vehicle->odometer}}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--md">
                                <div class="kt-widget-17__foot">
                                    <div class="kt-widget-17__foot-info"></div>
                                    <div class="kt-widget-17__foot-toolbar"> </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Portlet-->
                    </div>
                    <!--end::Policy Plan-->
                    <!--begin::Policy Plan-->
                    <div class="col-lg-6 col-xl-6 order-lg-1 order-xl-1">
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--height-fluid kt-widget-17">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Vehicle Photos
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">

                                    <div class="col-lg-6">
                                        @if($vehicle->front == null)
                                        <span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--metal" style="margin:35px">
                                            <label>
                                                <div id="uploadFrontButton" class="file btn  btn-primary">
                                                    <form id="uploadFrontImageForm" action="{{Route('uploadFrontImage')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                        {{csrf_field()}}
                                                        <i class="la la-upload"></i>Upload Vehicle Front

                                                        <input type="hidden" name="id" value="{{$policies->id}}" />

                                                        <input type="file" name="front" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" required />

                                                    </form>
                                                </div>
                                                <span></span>
                                            </label>
                                        </span>
                                        @else
                                        <a class="thumbnail" href="#" id="frontViewModal" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-target="#image-gallery">
                                            <img src="data:image/;base64, {{$vehicle->front}}" width="275px" height="auto" title="Invoice" alt="Invoice" style="padding:1px">
                                        </a>
                                        @endif

                                    </div>

                                    <div class="col-lg-6">
                                        @if($vehicle->back == null)
                                        <span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--metal" style="margin:35px">
                                            <label>
                                                <div class="file btn  btn-primary">
                                                    <form action="{{Route('uploadBackImage')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                        {{csrf_field()}}
                                                        <i class="la la-upload"></i>Upload Vehicle Back

                                                        <input type="hidden" name="id" value="{{$policies->id}}" />

                                                        <input type="file" name="back" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" required />

                                                    </form>
                                                </div>
                                                <span></span>
                                            </label>
                                        </span>
                                        @else
                                        <a class="thumbnail" href="#" id="backViewModal" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
                                            <img src="data:image/;base64, {{$vehicle->back}}" width="275px" height="auto" title="Invoice" alt="Invoice" style="padding:1px">
                                        </a>
                                        @endif

                                    </div>

                                    <div class="col-lg-6">

                                        @if($vehicle->left == null)
                                        <span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--metal" style="margin:35px">
                                            <label>
                                                <div class="file btn  btn-primary">
                                                    <form action="{{Route('uploadLeftImage')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                        {{csrf_field()}}
                                                        <i class="la la-upload"></i>Upload Vehicle Left

                                                        <input type="hidden" name="id" value="{{$policies->id}}" />

                                                        <input type="file" name="left" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" required />

                                                    </form>
                                                </div>
                                                <span></span>
                                            </label>
                                        </span>
                                        @else
                                        <a class="thumbnail" href="#" id="leftViewModal" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
                                            <img src="data:image/;base64, {{$vehicle->left}}" width="275px" height="auto" title="Invoice" alt="Invoice" style="padding:1px">
                                        </a>
                                        @endif

                                    </div>

                                    <div class="col-lg-6">

                                        @if($vehicle->right == null)
                                        <span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--metal" style="margin:35px">
                                            <label>
                                                <div class="file btn  btn-primary">
                                                    <form action="{{Route('uploadRightImage')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                        {{csrf_field()}}
                                                        <i class="la la-upload"></i>Upload Vehicle Right

                                                        <input type="hidden" name="id" value="{{$policies->id}}" />

                                                        <input type="file" name="right" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" required />

                                                    </form>
                                                </div>
                                                <span></span>
                                            </label>
                                        </span>
                                        @else
                                        <a class="thumbnail" href="#" id="rightViewModal" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-src="data:image/;base64, {{$vehicle->left}}" data-target="#image-gallery">
                                            <img src="data:image/;base64, {{$vehicle->right}}" width="275px" height="auto" title="Invoice" alt="Invoice" style="padding:1px">
                                        </a>
                                        @endif

                                    </div>

                                </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--md">
                                <div class="kt-widget-17__foot">
                                    <div class="kt-widget-17__foot-info"></div>
                                    <div class="kt-widget-17__foot-toolbar"> </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Portlet-->
                    </div>
                    <!--end::Policy Plan-->

                    <!--begin::Policy Plan-->
                    <div class="col-lg-6 col-xl-6 order-lg-1 order-xl-1">
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--height-fluid kt-widget-17">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Policy Plan
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <table class="table table-hover table-striped">
                                        <tr>
                                            <th>Policy Plan</th>
                                            <td>
                                                <div class="dropdown">

                                                    <p style="font-weight: bold;">P{{$selectedPolicyPlan->premium}}/month – for P{{$selectedPolicyPlan->sumInsured}} of insusrance cover</p>

                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Sum Insured:</th>
                                            <td style="font-weight: bold;">P {{number_format($selectedPolicyPlan->sumInsured, 2)}}</td>
                                        </tr>
                                        <tr>
                                            <th>Premium</th>
                                            <td>
                                                <table class="preminum">
                                                    <tr>
                                                        <th>Name</th>
                                                        <td style="font-weight: bold;">P {{number_format($selectedPolicyPlan->premium, 2)}}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Vat (5%)</th>
                                                        <td style="font-weight: bold; border-bottom:1px solid gray;">P {{5/100 * $selectedPolicyPlan->premium }}</td>
                                                    </tr>
                                                    <tr class="total">
                                                        <th>Total</th>
                                                        <td style="font-weight: bold;">P {{5/100 * $selectedPolicyPlan->premium + $selectedPolicyPlan->premium}}</td>
                                                    </tr>
                                                </table>
                                            </td>

                                        </tr>

                                        <tr>
                                            <th>Assigned Agent</th>
                                            <td>{{$agentDetails->firstName}} {{$agentDetails->lastName}}</td>
                                        </tr>


                                        @if($policies->policyActivatedDate == null)
                                        <tr>
                                            <th>Policy Activated Date</th>
                                            <td>Policy is not active</td>
                                        </tr>
                                        @else
                                        <tr>
                                            <th>Policy Activated Date</th>
                                            <td>{{$policies->policyActivatedDate->format('d M Y')}}</td>
                                        </tr>

                                        @endif


                                    </table>
                                </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--md">
                                <div class="kt-widget-17__foot">
                                    <div class="kt-widget-17__foot-info"></div>
                                    <div class="kt-widget-17__foot-toolbar"> </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Portlet-->
                    </div>
                    <!--begin::Biling Date-->
                    <div class="col-lg-6 col-xl-6 order-lg-1 order-xl-1">
                        <!--begin::Portlet-->
                        <div class="kt-portlet kt-portlet--height-fluid kt-widget-17">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title">
                                        Billing
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="row">
                                    <table class="table table-hover table-striped">
                                        <tr>
                                            <th>Billing Date</th>
                                            <td>{{$policies->created_at->format('d M Y')}}</td>
                                        </tr>
                                        <tr>
                                            <th>Billing Cell</th>
                                            <td>Monthly</td>
                                        </tr>
                                        <tr>
                                            <th>Billing Activation Date</th>
                                            <td>{{$customerBanking->billingStartDate->format('d M Y')}}</td>
                                        </tr>


                                        <tr>
                                            <th>Premium</th>
                                            <td>
                                                <table class="preminum">
                                                    @if($customerBanking->prefered == 'Bank')

                                                    <tr>
                                                        <th>Bank Name</th>
                                                        <td id="realpayBankName"> </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Branch Code</th>
                                                        <td>{{$customerBanking->branchCode}}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Account Number</th>
                                                        <td>{{str_repeat("*", strlen($customerBanking->accountNumber)-4).substr($customerBanking->accountNumber,-4)}}</td>
                                                    </tr>


                                                    @else

                                                    <tr>
                                                        <th>Billing Method</th>
                                                        <td>{{$customerBanking->prefered}}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Billing Cell</th>
                                                        <td>{{$customerBanking->billingCell}}</td>
                                                    </tr>

                                                    @endif



                                                </table>
                                            </td>
                                        </tr>

                                    </table>

                                </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--md">
                                <div class="kt-widget-17__foot">
                                    <div class="kt-widget-17__foot-info"></div>
                                    <div class="kt-widget-17__foot-toolbar"> </div>
                                </div>
                            </div>


                            @if($policies->isActive == 1)

                            <div>
                                <form action="" method="POST">
                                    <button class=" btn btn-warning btn-block" onclick="this.form.submit()" disabled>Deactivate Policy</button>
                                    {{csrf_field()}}

                                    <input type="hidden" name="id" value="{{$userDetails->id}}" />
                                    <input type="hidden" name="policyId" value="{{$policies->id}}" />
                                    <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                    <input type="hidden" name="policyNumber" value="{{$policies->policyNumber}}" />
                                    <input type="hidden" name="billingStartDate" value="{{$customerBanking->billingStartDate}}" />
                                    <input type="hidden" name="cellphone" value="{{$userDetails->cellphone}}" />
                                </form>
                            </div>

                            @else
                            <div>
                                <form action="{{Route('activateUserPolicy')}}" method="POST">
                                    <button id="activatePolicyButton" class=" btn btn-success btn-block">Activate Policy</button>

                                    {{csrf_field()}}

                                    <input type="hidden" name="id" value="{{$userDetails->id}}" />
                                    <input type="hidden" name="policyId" value="{{$policies->id}}" />
                                    <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                    <input type="hidden" name="policyNumber" value="{{$policies->policyNumber}}" />
                                    <input type="hidden" name="billingStartDate" value="{{$customerBanking->billingStartDate}}" />
                                    <input type="hidden" name="cellphone" value="{{$userDetails->cellphone}}" />

                                </form>


                                {{-- <form id="realpayActivationForm" action="{{ \Config::get('values.graphite_url') }}/realpay/addClient" method="POST">
                                                           <input type="hidden" name="clientNumber" value="{{$policies->policyNumber}}" /> --}}
                                </form>


                            </div>
                            @endif


                        </div>

                        <!--end::Portlet-->
                    </div>
                    <!--end::Biling Date-->
                </div>

                <div class="row editForm" style="display:none">
                    <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                        <div class="loading">Loading&#8230;</div>

                        <div class="kt-portlet">
                            <div class="kt-portlet__body kt-portlet__body--fit">
                                <div class="kt-wizard-v3" id="kt_wizard_v3" data-ktwizard-state="step-first">


                                    <!--begin: Form Wizard Form-->

                                    <form id="kt_form" name="ClaimForm" class="kt-form" action="{{Route('admin-savePolicy')}}" method="POST" accept="image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                        {{csrf_field()}}
                                        <!--begin: Form Wizard Step 1-->
                                        <div class="kt-wizard-v3__content" data-ktwizard-type="step-content" data-ktwizard-state="current" style="width:80%">
                                             
                                            <div class="kt-heading kt-heading--md">Customer Details</div>
                                            <div class="kt-separator kt-separator--height-xs"></div>
                                            <div class="kt-form__section kt-form__section--first">
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>First Name</label>
                                                            <input type="text" class="form-control" name="fname" placeholder="Please Enter customer First Name" title="Customer first name must only contain alphabets" pattern="[A-Za-z]{1,25}" maxlength="25" value="{{$userDetails->firstName}}" required>
                                                        </div>


                                                    </div>
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Last Name</label>
                                                            <input type="text" class="form-control" name="lname" title="Customer last name must only contain alphabets" placeholder="Please enter customer Last Name" value="{{$userDetails->lastName}}" pattern="[A-Za-z]{1,25}" maxlength="25" required>
                                                        </div>
                                                    </div>

                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Cellphone Number</label>
                                                            <input type="text" class="form-control" name="cellphone" title="Customer cellhone should only contain digits Eg: 72394940 " placeholder="Please enter customer's cellphone number" value="{{$userDetails->cellphone}}" pattern="[0-9]{1,25}" minlength="8" maxlength="8" required>
                                                        </div>
                                                    </div>

                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Email</label>
                                                            <input type="email" class="form-control" name="email" placeholder="Please enter customer's email address" title="Customers Email" value="{{$userDetails->email}}">
                                                        </div>

                                                    </div>

                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Physical Address</label>
                                                            <input type="text" class="form-control" name="address" title="Physical address can contain alphabets and numeric characters" placeholder="Please enter customer's physical address" value="{{$userDetails->address}}" maxlength="60" required>
                                                        </div>
                                                    </div>

                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Omang ID</label>
                                                            <input id="omang" type="text" class="form-control" name="omang" placeholder="Please enter customer's omang number" title="Omang must have 9 digits only" pattern="[0-9]{9}" minlength="9" maxlength="9" value="{{$userDetails->omang}}" required>
                                                        </div>

                                                    </div>

                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Passport Number</label>
                                                            <input id="passport" type="text" class="form-control" name="passport" placeholder="Please enter customer passport number" title="Passport Number" maxlength="12" value="{{$userDetails->passport}}" required>
                                                        </div>

                                                    </div>
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label>Date Of Birth</label>
                                                            <!-- <input type="date" class="form-control" id="dob" name="dob" value="{{$userDetails->dob->format('Y m d')}}" title="Customers age, must be 18 years or older" placeholder="Pleae select DOB from Calendar" onclick="datePicker(event)" data-relmax="-18" required> -->
                                                            <div class='input-group date'>
                                                            <input type="text" class="form-control " readonly=""  id="kt_datepicker_2" name="dob" value="{{$userDetails->dob->format('Y m d')}}" title="Customers age, must be 18 years or older" placeholder="Pleae select DOB from Calendar"  required>
                                                                <div class="input-group-append">
                                                                    <span class="input-group-text">
                                                                        <i class="la la-calendar-check-o"></i>
                                                                    </span>
                                                                </div>
                                                                </div>
                                                        </div>
                                                    </div>
                                                </div>



                                                <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                <div class="kt-heading kt-heading--md">Vehicle Details</div>
                                                <div class="row">
                                                    <div class="col-lg-6">

                                                        <div class="form-group">
                                                            <label>Vehicle Make</label>

                                                            <select class="form-control kt_selectpicker" name="make" title="Please choose vehicle make..." data-live-search="true" id="carMakeDropDown" required>
                                                                @foreach ($carMake as $make)

                                                                @if($vehicle->make == $make->s_Make)
                                                                <option value="{{$make->s_Make}}" selected>{{$make->s_Make}}</option>
                                                                @else
                                                                <option value="{{$make->s_Make}}">{{$make->s_Make}}</option>

                                                                @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Vehicle Model</label>
                                                            <style>
                                                                .kt-spinner.kt-spinner--input.kt-spinner--left::before {
                                                                    z-index: 9999;
                                                                    margin-left: 24px;
                                                                }
                                                                .table th, .table td {
                                                                    border-top: none !important;
                                                                }
                                                            </style>
                                                            <div id="carSpinner" style="display:contents;" class="form-group kt-spinner kt-spinner--sm kt-spinner--success kt-spinner--left kt-spinner--input"></div>
                                                            <select class="form-control kt_selectpicker " name="model" title="Please choose vehicle model..." data-live-search="true" id="carModelDropDown" disabled>
                                                                @if($vehicle->model != null)
                                                                <option value="{{$vehicle->model}}"> {{$vehicle->model}}</option>
                                                                @endif
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">

                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Vehicle Year</label>
                                                            <select id="years" name="year" title="Please select vehicle year" data-live-search="true" class="form-control kt_selectpicker" required>
                                                                @if( $vehicle->year != null)
                                                                <option value="{{$vehicle->year}}" selected> {{$vehicle->year}}</option>
                                                                @endif

                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Registration Vehicle No.</label>
                                                            <input type="text" class="form-control" name="license" title="Required format : B 121 ABC" placeholder="Eg. B 111 AAA" pattern="^[Bb]{1}\d{3}[a-zA-Z]{3}$" minlength="7" maxlength="7" value="{{$vehicle->vehiclePlate}}" style="text-transform: uppercase" required>
                                                        </div>
                                                    </div>
                                                </div>


                                                <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Odometer</label>
                                                            <input type="text" class="form-control" name="odometer" title="Odometer should only have numeric values" value="{{$vehicle->odometer}}" placeholder="Please enter Odometer reading" maxlength="9">
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Condition</label>

                                                            <select class="form-control kt_selectpicker" title="Please select the condition of the vehicle" name="condition" id="exampleSelect1">


                                                                @if( $vehicle->condition == 'Very Good')
                                                                <option value="Very Good" selected>Very Good</option>
                                                                <option value="Good">Good</option>
                                                                <option value="Poor">Poor</option>
                                                                <option value="Very Poor">Very Poor</option>
                                                                @elseif( $vehicle->condition == 'Good')
                                                                <option value="Very Good">Very Good</option>
                                                                <option value="Good" selected>Good</option>
                                                                <option value="Poor">Poor</option>
                                                                <option value="Very Poor">Very Poor</option>

                                                                @elseif($vehicle->condition == 'Poor')

                                                                <option value="Very Good">Very Good</option>
                                                                <option value="Good" selected>Good</option>
                                                                <option value="Poor" selected>Poor</option>
                                                                <option value="Very Poor">Very Poor</option>

                                                                @elseif($vehicle->condition == 'Very Poor')
                                                                <option value="Very Good">Very Good</option>
                                                                <option value="Good" selected>Good</option>
                                                                <option value="Poor" selected>Poor</option>
                                                                <option value="Very Poor">Very Poor</option>

                                                                @elseif( $vehicle->condition == null)
                                                                <option value="Very Good">Very Good</option>
                                                                <option value="Good">Good</option>
                                                                <option value="Poor">Poor</option>
                                                                <option value="Very Poor">Very Poor</option>

                                                                @endif

                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                <div class="kt-heading kt-heading--md">Vehicle Images</div>
                                                <div class="row">
                                                    <div class="col-lg-6">

                                                        <div class="form-group">
                                                            <label>Front View</label>
                                                            <div></div>
                                                            <div class="custom-file">
                                                                <input type="file" class="custom-file-input" name="front" id="frontViewUpload" accept=".jpg,.jpeg,.png" onchange="validateFileTypeFront()">
                                                                <label class="custom-file-label" for="customFile">Choose file</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="thumbnail">
                                                            <a class="front" href="#" id="frontViewModal" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
                                                                <img id="frontView" src="#" alt="Front Car Image" width="280px" height="155px" />
                                                            </a>
                                                        </div>

                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Back View</label>
                                                            <div></div>
                                                            <div class="custom-file">
                                                                <input type="file" class="custom-file-input" name="back" id="backViewUpload" accept=".jpg,.jpeg,.png" onchange="validateFileTypeBack()">
                                                                <label class="custom-file-label" for="customFile">Choose file</label>
                                                            </div>
                                                        </div>

                                                    </div>
                                                    <div class="col-lg-6">
                                                        <a class="thumbnail" href="#" id="backViewModal" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
                                                            <img id="backView" src="#" class="gridimg" alt="Back Car Image" width="280px" height="155px" />
                                                        </a>

                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-6">

                                                        <div class="form-group">
                                                            <label>Right Side</label>
                                                            <div></div>
                                                            <div class="custom-file">
                                                                <input type="file" class="custom-file-input" name="right" id="rightViewUpload" accept=".jpg,.jpeg,.png" onchange="validateFileTypeRight()">
                                                                <label class="custom-file-label" for="customFile">Choose file</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <a class="thumbnail" href="#" id="rightViewModal" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
                                                            <img id="rightView" src="#" class="gridimg" alt="Right Car Image" width="280px" height="155px" />
                                                        </a>

                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-6">

                                                        <div class="form-group">
                                                            <label>Left Side</label>
                                                            <div></div>
                                                            <div class="custom-file">
                                                                <input type="file" class="custom-file-input" name="left" id="leftViewUpload" accept=".jpg,.jpeg,.png" onchange="validateFileTypeLeft()">
                                                                <label class="custom-file-label" for="customFile">Choose file</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <a class="thumbnail" href="#" id="leftViewModal" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="{{asset('images/cracked windscreen.jpg')}}" data-target="#image-gallery">
                                                            <img id="leftView" src="#" class="gridimg" alt="Right Car Image" width="280px" height="155px" />
                                                        </a>

                                                    </div>
                                                </div>






                                                <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                <div class="kt-heading kt-heading--md">Policy Plan</div>
                                                <div class="row">
                                                    <div class="col-lg-6">
                                                        <label>Policy Plan:</label>

                                                        <select class="form-control kt_selectpicker" title="Please select the policy plan the customer prefers" name="policyPlan" id="exampleSelect1">
                                                            @foreach ($policyPlan as $plan )

                                                            @if(old('policyPlan') == $plan->premium)
                                                            <option value="{{$plan->premium}}" selected>{{$plan->plan}}</option>
                                                            @else
                                                            <option value="{{$plan->premium}}">{{$plan->plan}}</option>

                                                            @endif


                                                            @endforeach
                                                        </select>
                                                    </div>


                                                </div>


                                                <div class="kt-separator kt-separator--border-dashed kt-separator--space-lg"></div>
                                                <div class="kt-heading kt-heading--md">Customer Bank Details</div>
                                                <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">

                                                            <label for="exampleSelect1">Billing Method:</label>
                                                            <select id="billing" class="form-control kt_selectpicker" title="Please select customers preferred billing method" name="billing" id="exampleSelect1" onchange="disableOptions(event)">
                                                                @if($customerBanking->prefered== null)
                                                                <option value="Bank">Bank</option>
                                                                <option value="MyZaka">MyZaka</option>
                                                                <option value="orangeMoney">Orange Money</option>
                                                                @elseif($customerBanking->prefered== 'Bank')
                                                                <option value="Bank" selected>Bank</option>
                                                                <option value="MyZaka">MyZaka</option>
                                                                <option value="orangeMoney">Orange Money</option>
                                                                @elseif($customerBanking->prefered== 'MyZaka')
                                                                <option value="Bank">Bank</option>
                                                                <option value="MyZaka" selected>MyZaka</option>
                                                                <option value="orangeMoney">Orange Money</option>
                                                                @elseif($customerBanking->prefered == 'orangeMoney')
                                                                <option value="Bank">Bank</option>
                                                                <option value="MyZaka">MyZaka</option>
                                                                <option value="orangeMoney" selected>Orange Money</option>
                                                                @endif


                                                            </select>


                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Myzaka/Orange Money Cell:</label>
                                                            <input id="billingCell" type="text" class="form-control" name="billingCell" aria-describedby="emailHelp" title="Billing number should only have 8 numbers" placeholder="Myzaka/Orange Cell" value="{{old('billingCell')}}" minlength="8" maxlength="8" pattern="[0-9]{8}" disabled>
                                                        </div>

                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">

                                                            <label for="exampleSelect1">Bank Name:</label>
                                                            <select id="bankNumDropDown" class="form-control kt_selectpicker" title="Please select customers bank" name="bankName"></select>
                                                        </div>

                                                    </div>

                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Branch Code:</label>
                                                            <select id="bankBranchDropDown" class="form-control kt_selectpicker" title="Please select customers branch" name="branchCode" required></select>

                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Account Number:</label>
                                                            <input id="accountNumber" type="text" class="form-control" name="accountNumber" title="account number should only be numeric characters" value="{{$customerBanking->accountNumber}}" aria-describedby="emailHelp" placeholder="Account Number" pattern="[0-9]{1,25}" maxlength="25" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Account Type:</label>
                                                            <select id="accountType" class="form-control kt_selectpicker" name="bankAccountType" required>
                                                                <option>Please Select the Bank Account Type</option>

                                                                @if(old('bankAccountType') == 1)
                                                                <option value="1" selected>Cheque</option>
                                                                <option value="2">Savings</option>
                                                                @else
                                                                <option value="1">Cheque</option>
                                                                <option value="2" selected>Savings</option>
                                                                @endif


                                                            </select>
                                                        </div>
                                                    </div>

                                                </div>
                                                <div class="row">

                                                    <div class="col-lg-3">
                                                    </div>
                                                    <div class="col-lg-3">
                                                    </div>
                                                    <div class="col-lg-3">
                                                        <button id="resetForm" type="button" class="btn btn-info btn-lg pull-right">Reset Form</button>
                                                    </div>
                                                    <div class="col-lg-3">
                                                        <button id="submitForm" type="submit" class="btn btn-success btn-lg pull-right" onclick="document.getElementById('kt_form').submit();">Update Policy information</button>
                                                    </div>

                                                </div>

                                    </form>

                                </div>
                            </div>
                            </form>
                        </div>
                    </div>
                    <!--end: Form Wizard Step 1-->

                    <!--begin: Form Actions -->

                    <!--end: Form Actions -->

                    <!--end: Form Wizard Form-->
                </div>
            </div>
        </div>
    </div>
    <div class="tab-pane" id="claim_history" role="tabpanel">
        <div class="row">
            It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.
        </div>
    </div>
    <div class="tab-pane" id="transactions" role="tabpanel"> Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged </div>
    <div class="tab-pane" id="activity_log" role="tabpanel"> Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and </div>
    </div>

    <!--end::Row-->
    <!--end::Dashboard 4-->

    <!-- end:: Content -->
    </div>
    <!-- begin:: Footer -->
    <div class="kt-footer kt-grid__item kt-grid kt-grid--desktop kt-grid--ver-desktop">
        <div class="kt-footer__copyright " align="center"> 2019&nbsp;&copy;&nbsp;<a href="#" target="_blank" class="kt-link">Alpha Direct in Partnership with : </a><a href="https://www.aig.com/individual" target="_blank"><img src="{{ asset('img/aig7.png') }}" alt="not found"></a> </div>
        <div class="kt-footer__menu"> <a href="https://alphadirect.co.bw/aboutus.html" target="_blank" class="kt-footer__menu-link kt-link">About</a> <a href="#" target="_blank" class="kt-footer__menu-link kt-link">Team</a> <a href="https://alphadirect.co.bw/contact.html" target="_blank" class="kt-footer__menu-link kt-link">Contact</a> </div>
    </div>
    <!-- end:: Footer -->


    <!-- end:: Root -->
    <!-- begin:: Topbar Offcanvas Panels -->
    <!-- begin::Offcanvas Toolbar Quick Actions -->
    <div id="kt_offcanvas_toolbar_quick_actions" class="kt-offcanvas-panel">
        <div class="kt-offcanvas-panel__head">
            <h3 class="kt-offcanvas-panel__title">
                Quick Actions
            </h3>
            <a href="#" class="kt-offcanvas-panel__close" id="kt_offcanvas_toolbar_quick_actions_close"><i class="flaticon2-delete"></i></a>
        </div>
        <div class="kt-offcanvas-panel__body">
            <div class="kt-grid-nav-v2">
                <a href="#" class="kt-grid-nav-v2__item">
                    <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-box"></i></div>
                    <div class="kt-grid-nav-v2__item-title">Orders</div>
                </a>
                <a href="#" class="kt-grid-nav-v2__item">
                    <div class="kt-grid-nav-v2__item-icon"><i class="flaticon-download-1"></i></div>
                    <div class="kt-grid-nav-v2__item-title">Uploades</div>
                </a>
                <a href="#" class="kt-grid-nav-v2__item">
                    <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-supermarket"></i></div>
                    <div class="kt-grid-nav-v2__item-title">Products</div>
                </a>
                <a href="#" class="kt-grid-nav-v2__item">
                    <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-avatar"></i></div>
                    <div class="kt-grid-nav-v2__item-title">Customers</div>
                </a>
                <a href="#" class="kt-grid-nav-v2__item">
                    <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-list"></i></div>
                    <div class="kt-grid-nav-v2__item-title">Blog Posts</div>
                </a>
                <a href="#" class="kt-grid-nav-v2__item">
                    <div class="kt-grid-nav-v2__item-icon"><i class="flaticon2-settings"></i></div>
                    <div class="kt-grid-nav-v2__item-title">Settings</div>
                </a>
            </div>
        </div>
    </div>
    <!-- end::Offcanvas Toolbar Quick Actions -->
    <!-- end:: Topbar Offcanvas Panels -->
    <!-- begin:: Quick Panel -->
    <div id="kt_quick_panel" class="kt-offcanvas-panel">
        <div class="kt-offcanvas-panel__nav">
            <ul class="nav nav-pills" role="tablist">
                <li class="nav-item active"> <a class="nav-link active" data-toggle="tab" href="#kt_quick_panel_tab_notifications" role="tab">Notifications</a> </li>
                <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#kt_quick_panel_tab_actions" role="tab">Actions</a> </li>
                <li class="nav-item"> <a class="nav-link" data-toggle="tab" href="#kt_quick_panel_tab_settings" role="tab">Settings</a> </li>
            </ul>
            <button class="kt-offcanvas-panel__close" id="kt_quick_panel_close_btn"><i class="flaticon2-delete"></i></button>
        </div>
        <div class="kt-offcanvas-panel__body">
            <div class="tab-content">
                <div class="tab-pane fade show kt-offcanvas-panel__content kt-scroll active" id="kt_quick_panel_tab_notifications" role="tabpanel">
                    <!--Begin::Timeline -->
                    <div class="kt-timeline">
                        <!--Begin::Item -->
                        <div class="kt-timeline__item kt-timeline__item--success">
                            <div class="kt-timeline__item-section">
                                <div class="kt-timeline__item-section-border">
                                    <div class="kt-timeline__item-section-icon"> <i class="flaticon-feed kt-font-success"></i> </div>
                                </div>
                                <span class="kt-timeline__item-datetime">02:30 PM</span>
                            </div>
                            <a href="" class="kt-timeline__item-text"> KeenThemes created new layout whith tens of new options for Keen Admin panel </a>
                            <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                        </div>
                        <!--End::Item -->
                        <!--Begin::Item -->
                        <div class="kt-timeline__item kt-timeline__item--danger">
                            <div class="kt-timeline__item-section">
                                <div class="kt-timeline__item-section-border">
                                    <div class="kt-timeline__item-section-icon"> <i class="flaticon-safe-shield-protection kt-font-danger"></i> </div>
                                </div>
                                <span class="kt-timeline__item-datetime">01:20 AM</span>
                            </div>
                            <a href="" class="kt-timeline__item-text"> New secyrity alert by Firewall & order to take aktion on User Preferences </a>
                            <div class="kt-timeline__item-info"> Security, Fieewall </div>
                        </div>
                        <!--End::Item -->
                        <!--Begin::Item -->
                        <div class="kt-timeline__item kt-timeline__item--brand">
                            <div class="kt-timeline__item-section">
                                <div class="kt-timeline__item-section-border">
                                    <div class="kt-timeline__item-section-icon"> <i class="flaticon2-box kt-font-brand"></i> </div>
                                </div>
                                <span class="kt-timeline__item-datetime">Yestardey</span>
                            </div>
                            <a href="" class="kt-timeline__item-text"> FlyMore design mock-ups been uploadet by designers Bob, Naomi, Richard </a>
                            <div class="kt-timeline__item-info"> PSD, Sketch, AJ </div>
                        </div>
                        <!--End::Item -->
                        <!--Begin::Item -->
                        <div class="kt-timeline__item kt-timeline__item--warning">
                            <div class="kt-timeline__item-section">
                                <div class="kt-timeline__item-section-border">
                                    <div class="kt-timeline__item-section-icon"> <i class="flaticon-pie-chart-1 kt-font-warning"></i> </div>
                                </div>
                                <span class="kt-timeline__item-datetime">Aug 13,2018</span>
                            </div>
                            <a href="" class="kt-timeline__item-text">
                                Meeting with Ken Digital Corp ot Unit14, 3 Edigor Buildings, George Street, Loondon
                                <br>
                                England, BA12FJ
                            </a>
                            <div class="kt-timeline__item-info"> Meeting, Customer </div>
                        </div>
                        <!--End::Item -->
                        <!--Begin::Item -->
                        <div class="kt-timeline__item kt-timeline__item--info">
                            <div class="kt-timeline__item-section">
                                <div class="kt-timeline__item-section-border">
                                    <div class="kt-timeline__item-section-icon"> <i class="flaticon-notepad kt-font-info"></i> </div>
                                </div>
                                <span class="kt-timeline__item-datetime">May 09, 2018</span>
                            </div>
                            <a href="" class="kt-timeline__item-text"> KeenThemes created new layout whith tens of new options for Keen Admin panel </a>
                            <div class="kt-timeline__item-info"> HTML,CSS,VueJS </div>
                        </div>
                        <!--End::Item -->
                        <!--Begin::Item -->
                        <div class="kt-timeline__item kt-timeline__item--accent">
                            <div class="kt-timeline__item-section">
                                <div class="kt-timeline__item-section-border">
                                    <div class="kt-timeline__item-section-icon"> <i class="flaticon-bell kt-font-success"></i> </div>
                                </div>
                                <span class="kt-timeline__item-datetime">01:20 AM</span>
                            </div>
                            <a href="" class="kt-timeline__item-text"> New secyrity alert by Firewall & order to take aktion on User Preferences </a>
                            <div class="kt-timeline__item-info"> Security, Fieewall </div>
                        </div>
                        <!--End::Item -->
                    </div>
                    <!--End::Timeline -->
                </div>
                <div class="tab-pane fade kt-offcanvas-panel__content kt-scroll" id="kt_quick_panel_tab_actions" role="tabpanel">
                    <!--begin::Portlet-->
                    <div class="kt-portlet kt-portlet--solid-success">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <span class="kt-portlet__head-icon kt-hide"><i class="flaticon-stopwatch"></i></span>
                                <h3 class="kt-portlet__head-title">
                                    Recent Bills
                                </h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                <div class="kt-portlet__head-group">
                                    <div class="dropdown dropdown-inline">
                                        <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#">Separated link</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                        </div>
                        <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                    </div>
                    <!--end::Portlet-->
                    <!--begin::Portlet-->
                    <div class="kt-portlet kt-portlet--solid-focus">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <span class="kt-portlet__head-icon kt-hide"><i class="flaticon-stopwatch"></i></span>
                                <h3 class="kt-portlet__head-title">
                                    Latest Orders
                                </h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                <div class="kt-portlet__head-group">
                                    <div class="dropdown dropdown-inline">
                                        <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#">Separated link</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                        </div>
                        <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                    </div>
                    <!--end::Portlet-->
                    <!--begin::Portlet-->
                    <div class="kt-portlet kt-portlet--solid-info">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Latest Invoices
                                </h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                <div class="kt-portlet__head-group">
                                    <div class="dropdown dropdown-inline">
                                        <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#">Separated link</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                        </div>
                        <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                    </div>
                    <!--end::Portlet-->
                    <!--begin::Portlet-->
                    <div class="kt-portlet kt-portlet--solid-warning">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    New Comments
                                </h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                <div class="kt-portlet__head-group">
                                    <div class="dropdown dropdown-inline">
                                        <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#">Separated link</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                        </div>
                        <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                    </div>
                    <!--end::Portlet-->
                    <!--begin::Portlet-->
                    <div class="kt-portlet kt-portlet--solid-brand">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Recent Posts
                                </h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                <div class="kt-portlet__head-group">
                                    <div class="dropdown dropdown-inline">
                                        <button type="button" class="btn btn-sm btn-font-light btn-outline-hover-light btn-circle btn-icon" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true"> <i class="flaticon-more"></i> </button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a class="dropdown-item" href="#">Action</a> <a class="dropdown-item" href="#">Another action</a> <a class="dropdown-item" href="#">Something else here</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#">Separated link</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="kt-portlet__content"> Lorem Ipsum is simply dummy text of the printing and typesetting simply dummy text of the printing industry. </div>
                        </div>
                        <div class="kt-portlet__foot kt-portlet__foot--sm kt-align-right"> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">Dismiss</a> <a href="#" class="btn btn-bold btn-upper btn-sm btn-font-light btn-outline-hover-light">View</a> </div>
                    </div>
                    <!--end::Portlet-->
                </div>
                <div class="tab-pane fade kt-offcanvas-panel__content kt-scroll" id="kt_quick_panel_tab_settings" role="tabpanel">
                    <form class="kt-form">
                        <div class="kt-heading kt-heading--space-sm">Notifications</div>
                        <div class="form-group form-group-xs row">
                            <label class="col-8 col-form-label">Enable notifications:</label>
                            <div class="col-4 kt-align-right">
                                <span class="kt-switch kt-switch--sm">
                                    <label>
                                        <input type="checkbox" checked="checked" name="quick_panel_notifications_1">
                                        <span></span>
                                    </label>
                                </span>
                            </div>
                        </div>
                        <div class="form-group form-group-xs row">
                            <label class="col-8 col-form-label">Enable audit log:</label>
                            <div class="col-4 kt-align-right">
                                <span class="kt-switch kt-switch--sm">
                                    <label>
                                        <input type="checkbox" name="quick_panel_notifications_2">
                                        <span></span>
                                    </label>
                                </span>
                            </div>
                        </div>
                        <div class="form-group form-group-last form-group-xs row">
                            <label class="col-8 col-form-label">Notify on new orders:</label>
                            <div class="col-4 kt-align-right">
                                <span class="kt-switch kt-switch--sm">
                                    <label>
                                        <input type="checkbox" checked="checked" name="quick_panel_notifications_2">
                                        <span></span>
                                    </label>
                                </span>
                            </div>
                        </div>
                        <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>
                        <div class="kt-heading kt-heading--space-sm">Orders</div>
                        <div class="form-group form-group-xs row">
                            <label class="col-8 col-form-label">Enable order tracking:</label>
                            <div class="col-4 kt-align-right">
                                <span class="kt-switch kt-switch--sm kt-switch--danger">
                                    <label>
                                        <input type="checkbox" checked="checked" name="quick_panel_notifications_3">
                                        <span></span>
                                    </label>
                                </span>
                            </div>
                        </div>
                        <div class="form-group form-group-xs row">
                            <label class="col-8 col-form-label">Enable orders reports:</label>
                            <div class="col-4 kt-align-right">
                                <span class="kt-switch kt-switch--sm kt-switch--danger">
                                    <label>
                                        <input type="checkbox" name="quick_panel_notifications_3">
                                        <span></span>
                                    </label>
                                </span>
                            </div>
                        </div>
                        <div class="form-group form-group-last form-group-xs row">
                            <label class="col-8 col-form-label">Allow order status auto update:</label>
                            <div class="col-4 kt-align-right">
                                <span class="kt-switch kt-switch--sm kt-switch--danger">
                                    <label>
                                        <input type="checkbox" checked="checked" name="quick_panel_notifications_4">
                                        <span></span>
                                    </label>
                                </span>
                            </div>
                        </div>
                        <div class="kt-separator kt-separator--space-md kt-separator--border-dashed"></div>
                        <div class="kt-heading kt-heading--space-sm">Customers</div>
                        <div class="form-group form-group-xs row">
                            <label class="col-8 col-form-label">Enable customer singup:</label>
                            <div class="col-4 kt-align-right">
                                <span class="kt-switch kt-switch--sm kt-switch--success">
                                    <label>
                                        <input type="checkbox" checked="checked" name="quick_panel_notifications_5">
                                        <span></span>
                                    </label>
                                </span>
                            </div>
                        </div>
                        <div class="form-group form-group-xs row">
                            <label class="col-8 col-form-label">Enable customers reporting:</label>
                            <div class="col-4 kt-align-right">
                                <span class="kt-switch kt-switch--sm kt-switch--success">
                                    <label>
                                        <input type="checkbox" name="quick_panel_notifications_5">
                                        <span></span>
                                    </label>
                                </span>
                            </div>
                        </div>
                        <div class="form-group form-group-last form-group-xs row">
                            <label class="col-8 col-form-label">Notifiy on new customer registration:</label>
                            <div class="col-4 kt-align-right">
                                <span class="kt-switch kt-switch--sm kt-switch--success">
                                    <label>
                                        <input type="checkbox" checked="checked" name="quick_panel_notifications_6">
                                        <span></span>
                                    </label>
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- end:: Quick Panel -->
    <!-- begin:: Scrolltop -->
    <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
    <!-- end:: Scrolltop -->

    <div class="modal fade bd-example-modal-xl" id="image-gallery" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="image-gallery-title">Uploaded Image</h4>

                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                </div>
                <div class="modal-body">
                    <img id="image-gallery-image" class="img-responsive" style="height:80vh;width:100%" src="">
                </div>
                <div class="modal-footer">


                </div>
            </div>
        </div>
    </div>



    <!-- begin::Global Config(global config for global JS sciprts) -->
    
    <script>
        var KTAppOptions = {

            "colors": {

                "state": {

                    "brand": "#5d78ff",

                    "metal": "#c4c5d6",

                    "light": "#ffffff",

                    "accent": "#00c5dc",

                    "primary": "#5867dd",

                    "success": "#34bfa3",

                    "info": "#36a3f7",

                    "warning": "#ffb822",

                    "danger": "#fd3995",

                    "focus": "#9816f4"
                },

                "base": {

                    "label": [

                        "#c5cbe3",

                        "#a1a8c3",

                        "#3d4465",

                        "#3e4466"
                    ],

                    "shape": [

                        "#f0f3ff",

                        "#d9dffa",

                        "#afb4d4",

                        "#646c9a"
                    ]
                }
            }
        };
    </script>
    <!-- end::Global Config -->
    @include('Admin.Layout.scripts')
    <script src="{{asset('css/app/custom/general/components/forms/widgets/bootstrap-select.js')}}" type="text/javascript"></script>
    <script src="{{asset('css/app/custom/general/components/forms/widgets/bootstrap-datepicker.js')}}" type="text/javascript"></script>    
    <script src="{{asset('css/app/custom/general/components/extended/sweetalert2.min.js')}}" type="text/javascript"></script>
    <script>
        @if(Session::has('policyActivated'))
        Toastify({
            text: "{{ Session::get('policyActivated') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
        }).showToast();

        @endif

        @if(Session::has('rightUploaded'))
        Toastify({
            text: "{{ Session::get('rightUploaded') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
        }).showToast();

        @endif

        @if(Session::has('leftUploaded'))
        Toastify({
            text: "{{ Session::get('leftUploaded') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
        }).showToast();

        @endif

        @if(Session::has('backUploaded'))
        Toastify({
            text: "{{ Session::get('backUploaded') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
        }).showToast();

        @endif

        @if(Session::has('frontUploaded'))
        Toastify({
            text: "{{ Session::get('frontUploaded') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
        }).showToast();

        @endif



        @if(Session::has('bluebook'))
        Toastify({
            text: "{{ Session::get('bluebook') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
        }).showToast();

        @endif


        @if(Session::has('driversLicense'))
        Toastify({
            text: "{{ Session::get('driversLicense') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
        }).showToast();

        @endif

        @if(Session::has('omang'))
        Toastify({
            text: "{{ Session::get('omang') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
        }).showToast();

        @endif


        @if(Session::has('policySaved'))
        Toastify({
            text: "{{ Session::get('policySaved') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
        }).showToast();

        @endif
    </script>

    <script>
        $(document).ready(function() {


            $('.loading').css("display", "none");

            $("#setToEdit").change(function() {
                if(this.checked) {
                    $('.viewForm').hide();
                    $('.editForm').show();
                }else{
                    $('.viewForm').show();
                    $('.editForm').hide();
                }
            });

            function datePicker(evt) {
			$('input[data-relmax]').each(function () {
				let oldVal = $(this).prop('value');
				let relmax = $(this).data('relmax');
				let max = new Date();
				max.setFullYear(max.getFullYear() + relmax);
				$.prop(this, 'max', $(this).prop('valueAsDate', max).val());
				$.prop(this, 'value', oldVal);
			});
		}

            $('#activatePolicyButton').on('click', function() {
                $('.loading').css("display", "block");

                $("#realpayActivationForm").submit();
            });


            $('#frontViewModal').on('click', function() {

                $('#image-gallery-image').attr('src', 'data:image/;base64,' + "{{$vehicle->front}}");

            });

            $('#backViewModal').on('click', function() {

                $('#image-gallery-image').attr('src', 'data:image/;base64,' + "{{$vehicle->back}}");

            });

            $('#rightViewModal').on('click', function() {

                $('#image-gallery-image').attr('src', 'data:image/;base64,' + "{{$vehicle->right}}");

            });

            $('#leftViewModal').on('click', function() {

                $('#image-gallery-image').attr('src', 'data:image/;base64,' + "{{$vehicle->left}}");

            });



            //Preview of uploaded documents 

            $('#driversPreview').on('click', function() {

                $('#image-gallery-image').attr('src', 'data:image/;base64,' + "{{$customerKYC->driversLicense}}");

            });

            $('#omangPreview').on('click', function() {

                $('#image-gallery-image').attr('src', 'data:image/;base64,' + "{{$customerKYC->omang}}");

            });

            $('#bluebookPreview').on('click', function() {

                $('#image-gallery-image').attr('src', 'data:image/;base64,' + "{{$customerKYC->vehicleRegistration}}");

            });

            //uplaoad front image via ajax

            $('#uploadFrontImageForm').on('submit', function(event) {
                event.preventDefault();
                $.ajax({
                    url: "{{ route('uploadFrontImage') }}",
                    method: "POST",
                    data: new FormData(this),
                    dataType: 'JSON',
                    contentType: false,
                    cache: false,
                    processData: false,
                    success: function(data) {
                        console.log('HELLO');
                    }
                })
            });
            var urlValue = '{{ \Config::get('values.graphite_url') }}' 
            /*
                        //get banks list
                        $.ajax({
                            // the route pointing to the post function
                            url: urlValue+'realpay/getBanks',
                            type: 'GET',
                            // send the csrf-token and the input to the controller  
                            data: {},
                            dataType: 'JSON',
                            // remind that 'data' is the response of the AjaxController 
                            success: function (data) { 
                                if(data){

                                console.log(data);
                        
                                $.each(data, function(key, value){


                                    if(value["ns0:bankNum"] == {{$customerBanking->bankName}}){

                                        $('#realpayBankName').text(value["ns0:bankDesc"]);
                                    }
                                });
                            }
                            }
                        });
            */
            $('#uploadFrontButton').on('click', function() {

            });
            var urlValue = '{{ \Config::get('values.graphite_url') }}' 
            $("#activatePolicyButton").on("click", function() {
                /*
                            $.ajax({
                                // the route pointing to the post function 
                                url: urlValue+'realpay/addClient',
                                type: 'POST',
                                // send the csrf-token and the input to the controller  
                                data: {
                                     fname:"{{$userDetails->firstName}}",
                                     lname:"{{$userDetails->lastName}}",
                                     idNumber:"{{$userDetails->omang}}",
                                     bankNum:"{{$customerBanking->bankName}}",
                                     bankBranchNum:"{{$customerBanking->branchCode}}",
                                     bankAccountNum:"{{$customerBanking->accountNumber}}",
                                     bankAccountType:"{{$customerBanking->accountType}}",
                                     clientNumber:"{{$policies->policyNumber}}",
                                     empCode:"OT"

                                },
                                dataType: 'JSON',
                                // remind that 'data' is the response of the AjaxController 
                                success: function (data) { 
                                    if(data){

                                    console.log(data);
                            
                                    $.each(data, function(key, value){


                                        if(value["ns0:bankNum"] == {{$customerBanking->bankName}}){

                                            $('#realpayBankName').text(value["ns0:bankDesc"]);
                                        }
                                    });
                                }
                                }
                            });

                */


            });
        });
    </script>

</body>
<!-- end::Body -->

</html>