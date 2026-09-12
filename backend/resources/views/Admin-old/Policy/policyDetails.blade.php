<!DOCTYPE html>

<html lang="en">
<!-- begin::Head -->
@include('Admin.Layout.header')


<!-- end::Head -->
<!-- begin::Body -->


<!-- end:: Header Mobile -->
<!-- begin:: Root -->
<div class="kt-grid kt-grid--hor kt-grid--root">
    <!-- begin:: Page -->
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">


    @include('Admin.Layout.sidebar')
    @include('Admin.Layout.topNav')
    <!-- begin:: Content -->
    <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
        <div class="loading">Loading&#8230;</div>
        <div class="row ol-lg-12">
            <div class=" col-lg-3 text-left policyno"><br>
                <h2>Policy No # {{$policies->policyNumber}}</h2>
            </div>

            <div class="col-lg-3 text-center">
                <br>
                <label>
                    @if ($customerKYC->compliance == 1)
                    <h5>Compliance: <span style="color:#00C851"> Compliant</span></h5>
                    @elseif($customerKYC->compliance == 2)
                        <h5>Compliance: <span style="color:#CC0000"> Non-Compliant</span></h5>
                    @elseif($customerKYC->compliance == 0)
                        <h5>Compliance: <span style="color:#CC0000"> Pending</span></h5>
                    @else
                    <h5>Compliance: <span style="color:#CC0000"> Status not found</span></h5>
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
                                            <td><a href="{{Route('viewCustomerDetails',['id'=>$userDetails->id ])}}"> {{$userDetails->firstName}} {{$userDetails->lastName}}</a></td>
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
                                                        @if ($customerKYC->driversLicense == null)

                                                        <div class="file btn  btn-primary">
                                                            <form action="{{Route('uploadDriversLicense')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                {{csrf_field()}}
                                                                <i class="la la-upload"></i>Upload Drivers License
                                                                <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                                                <input type="hidden" name="lastName" value="{{$userDetails->lastName}}" />
                                                                <input type="hidden" name="id" value="{{$userDetails->id}}" />
                                                                <input type="hidden" name="vehiclePlate" value="{{$vehicle->vehiclePlate}}" />


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
                                                        @if ($customerKYC->omang == null)


                                                        <div class="file btn  btn-primary">
                                                            <form action="{{Route('uploadOmang')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                {{csrf_field()}}
                                                                <i class="la la-upload"></i>Upload Omang
                                                                <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                                                <input type="hidden" name="lastName" value="{{$userDetails->lastName}}" />
                                                                <input type="hidden" name="id" value="{{$userDetails->id}}" />
                                                                <input type="hidden" name="vehiclePlate" value="{{$vehicle->vehiclePlate}}" />


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
                                                        @if ($vehicle->vehicleRegistration == null)

                                                        <div class="file btn  btn-primary">
                                                            <form action="{{Route('uploadBlueBook')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                {{csrf_field()}}
                                                                <i class="la la-upload"></i>Upload Vehicle Registration Document
                                                                <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                                                <input type="hidden" name="lastName" value="{{$userDetails->lastName}}" />
                                                                <input type="hidden" name="id" value="{{$userDetails->id}}" />
                                                                <input type="hidden" name="vehiclePlate" value="{{$vehicle->vehiclePlate}}" />

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
                                        <tr>
                                            <th>Proof Of Residence:</th>
                                            <td><span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--success">
                                                    <label>
                                                        @if ($customerKYC->proofResidence == null)

                                                        <div class="file btn  btn-primary">
                                                            <form action="{{Route('uploadProofOfResidence')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                 {{csrf_field()}}
                                                                <i class="la la-upload"></i>Upload Proof Of Residence
                                                                <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                                                <input type="hidden" name="lastName" value="{{$userDetails->lastName}}" />
                                                                <input type="hidden" name="id" value="{{$userDetails->id}}" />
                                                                <input type="hidden" name="vehiclePlate" value="{{$vehicle->vehiclePlate}}" />

                                                                <input type="file" name="residence" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" />

                                                            </form>
                                                        </div>

                                                        @else
                                                        <input type="checkbox" checked="checked" disabled>
                                                        <button id="porPreview" data-toggle="modal" class="btn btn-info" data-target="#image-gallery">View {{$userDetails->firstName}}'s Proof Of Residence</button>

                                                        @endif
                                                        <span></span>
                                                    </label>
                                                </span></td>
                                        </tr>
                                        <tr>
                                            <th>Proof Of Income:</th>
                                            <td><span class="kt-switch kt-switch--outline kt-switch--icon kt-switch--success">
                                                    <label>
                                                        @if ($customerKYC->proofIncome == null)

                                                        <div class="file btn  btn-primary">
                                                            <form action="{{Route('uploadProofOfIncome')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                {{csrf_field()}}
                                                                <i class="la la-upload"></i>Upload Proof Of Income
                                                                <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                                                <input type="hidden" name="lastName" value="{{$userDetails->lastName}}" />
                                                                <input type="hidden" name="id" value="{{$userDetails->id}}" />
                                                                <input type="hidden" name="vehiclePlate" value="{{$vehicle->vehiclePlate}}" />

                                                                <input type="file" name="income" accept=".jpg,.jpeg,.png" onchange="this.form.submit()" />

                                                            </form>
                                                        </div>
                                                        @else
                                                        <input type="checkbox" checked="checked" disabled>
                                                        <button id="poiPreview" data-toggle="modal" class="btn btn-info" data-target="#image-gallery">View {{$userDetails->firstName}}'s Proof Of Income</button>

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
                                            <th>Vehicle Year:</th>
                                            @if($vehicle->year == null)
                                            <td>N/A</td>

                                            @else
                                            <td>{{$vehicle->year}} </td>

                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Vehicle Licence Plate</th>
                                            <td>{{$vehicle->vehiclePlate}}</td>
                                        </tr>
                                        <tr>
                                            <th>Vehicle Condition</th>
                                            @if($vehicle->condition == null)
                                            <td>N/A</td>
                                            @else
                                            <td>{{$vehicle->condition}}</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Vehicle Milage</th>
                                            @if($vehicle->odometer == null)
                                            <td>N/A</td>
                                            @else
                                            <td>{{$vehicle->odometer}}</td>
                                            @endif
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
                                                    <form id="uploadFrontImage" action="{{Route('uploadFrontImage')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
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
                                            <img id="s3Front" class="blockUI" src="{{Storage::disk('s3')->url($vehicle->front)}}" width="275px" height="auto" title="Invoice" alt="Front Image" style="padding:1px">
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
                                            <img id="s3Back" class="blockUI" src="{{Storage::disk('s3')->url($vehicle->back)}}" width="275px" height="auto" title="Invoice" alt="Back Image" style="padding:1px">
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
                                            <img id="s3Left" class="blockUI" src="{{Storage::disk('s3')->url($vehicle->left)}}" width="275px" height="auto" title="Invoice" alt="Left Image" style="padding:1px">
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
                                        <a class="thumbnail" href="#" id="rightViewModal" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-src="{{Storage::disk('s3')->url($vehicle->right)}}" data-target="#image-gallery">
                                            <img id="s3Right" class="blockUI" src="{{Storage::disk('s3')->url($vehicle->right)}}" width="275px" height="auto" title="Invoice" alt="Right Image" style="padding:1px">
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
                                                        <th>Vat (12%)</th>
                                                        <td style="font-weight: bold; border-bottom:1px solid gray;">P {{12/100 * $selectedPolicyPlan->premium }}</td>
                                                    </tr>
                                                    <tr class="total">
                                                        <th>Total</th>
                                                        <td style="font-weight: bold;">P {{12/100 * $selectedPolicyPlan->premium + $selectedPolicyPlan->premium}}</td>
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
                            <form action="{{Route('deactivatePolicy')}}" method="POST">
                                    <button class=" btn btn-warning btn-block" onclick="this.form.submit()" >Deactivate Policy</button>
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

                                    @if($vehicle->front == null && $vehicle->back == null && $vehicle->left == null && $vehicle->right == null)
                                    <button id="activatePolicyButton" class=" btn btn-success btn-block" disabled>Activate Policy</button>

                                    @else
                                    <button id="activatePolicyButton" class=" btn btn-success btn-block">Activate Policy</button>

                                    @endif

                                    {{csrf_field()}}

                                    <input type="hidden" name="id" value="{{$userDetails->id}}" />
                                    <input type="hidden" name="policyId" value="{{$policies->id}}" />
                                    <input type="hidden" name="firstName" value="{{$userDetails->firstName}}" />
                                    <input type="hidden" name="policyNumber" value="{{$policies->policyNumber}}" />
                                    <input type="hidden" name="billingStartDate" value="{{$customerBanking->billingStartDate}}" />
                                    <input type="hidden" name="cellphone" value="{{$userDetails->cellphone}}" />

                                </form>


                              {{-- <form id="realpayActivationForm" action="{{ \Config::get('values.graphite_url') }}/realpay/addClient" method="POST">
                                                           <input type="hidden" name="clientNumber" value="{{$policies->policyNumber}}" />
                                </form> --}}


                            </div>
                            @endif


                        </div>

                        <!--end::Portlet-->
                    </div>
                    <!--end::Biling Date-->
                </div>



                <!-- Edit Policy Details Begin -->
                <div class="row editForm" style="display:none">
                    <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                        <div class="loading">Loading&#8230;</div>

                        <div class="kt-portlet">
                            <div class="kt-portlet__body kt-portlet__body--fit">
                                <div class="kt-wizard-v3" id="kt_wizard_v3" data-ktwizard-state="step-first">


                                    <!--begin: Form Wizard Form-->

                                    <form id="kt_form" name="ClaimForm" class="kt-form" action="{{Route('admin-updatePolicy')}}" method="POST" accept="image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
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
                                                            <input id="passport" type="text" class="form-control" name="passport" placeholder="Please enter customer passport number" title="Passport Number" maxlength="20" minlength="3" value="{{$userDetails->passport}}">
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
                                                            <select class="form-control kt_selectpicker " name="model" title="Please choose vehicle model..." data-live-search="true" id="carModelDropDown" >

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
                                                <div class="kt-heading kt-heading--md">Customer KYC</div>
                                                <div class="row">
                                                    <div class="col-lg-6">

                                                        <div class="form-group">
                                                            <label>Drivers License</label>
                                                            <div></div>
                                                            <div class="custom-file">
                                                                <input type="file" class="custom-file-input" name="front" id="driversUploadEdit" accept=".jpg,.jpeg,.png" onchange="validateFileTypeFront()">
                                                                <label class="custom-file-label" for="customFile">Choose file</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">

                                                        @if($customerKYC->driversLicense != null)

                                                        <a class="front" href="#" id="driversLicenseModalEdit" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
                                                       <!--  <img id="driversLicensePreviewEdit" src="{{Storage::disk('s3')->url($customerKYC->driversLicense)}}" alt="DriversLicenseNotFound" width="280px" height="155px" /> -->
                                                        </a>
                                                        @else

                                                        <a class="front" href="#" id="driversLicenseModalEdit" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
                                                           <!-- <img id="driversLicensePreviewEdit" src="#" alt="DriversLicenseNotFound" width="280px" height="155px" />-->
                                                        </a>
                                                        @endif


                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Omang</label>
                                                            <div></div>
                                                            <div class="custom-file">
                                                                <input type="file" class="custom-file-input" name="back" id="backViewUpload" accept=".jpg,.jpeg,.png" onchange="validateFileTypeBack()">
                                                                <label class="custom-file-label" for="customFile">Choose file</label>
                                                            </div>
                                                        </div>

                                                    </div>
                                                    <div class="col-lg-6">
                                                        <a class="thumbnail" href="#" id="omangPreviewModalEdit" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
                                                            <!--<img id="omangPreviewEdit" src="#" class="gridimg" alt="OmangNotFound" width="280px" height="155px" />-->
                                                        </a>

                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-6">

                                                        <div class="form-group">
                                                            <label>Vehicle Registration</label>
                                                            <div></div>
                                                            <div class="custom-file">
                                                                <!-- <input type="file" class="custom-file-input" name="right" id="rightViewUpload" accept=".jpg,.jpeg,.png" onchange="validateFileTypeRight()"> -->
                                                                <label class="custom-file-label" for="customFile">Choose file</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <a class="thumbnail" href="#" id="vehicleRegistrationPreviewModalEdit" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">


                                                           <!-- <img id="vehicleRegistrationPreviewEdit" src="#" class="gridimg" alt="BluebookNotFound" width="280px" height="155px" />-->
                                                        </a>

                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-6">

                                                        <div class="form-group">
                                                            <label>Proof Of Residence</label>
                                                            <div></div>
                                                            <div class="custom-file">
                                                                <input type="file" class="custom-file-input" name="left" id="leftViewUpload" accept=".jpg,.jpeg,.png" onchange="validateFileTypeLeft()">
                                                                <label class="custom-file-label" for="customFile">Choose file</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <a class="thumbnail" href="#" id="porPreviewModalEdit" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="{{asset('images/cracked windscreen.jpg')}}" data-target="#image-gallery">
{{--                                                             <img id="porPreviewEdit" src="#" class="gridimg" alt="ProofOfIncome" width="280px" height="155px" />
 --}}                                                        </a>

                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-lg-6">

                                                        <div class="form-group">
                                                            <label>Proof Of Income</label>
                                                            <div></div>
                                                            <div class="custom-file">
                                                                <input type="file" class="custom-file-input" name="left" id="leftViewUpload" accept=".jpg,.jpeg,.png" onchange="validateFileTypeLeft()">
                                                                <label class="custom-file-label" for="customFile">Choose file</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <a class="thumbnail" href="#" id="poiPreviewModalEdit" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="{{asset('images/cracked windscreen.jpg')}}" data-target="#image-gallery">
{{--                                                             <img id="poiPreviewEdit" src="#" class="gridimg" alt="ProofOfIncome" width="280px" height="155px" />
 --}}                                                        </a>

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
                                                            <a class="front" href="#" id="frontViewModalEdit" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
                                                                <img id="frontViewEdit" src="#" alt="Front Car Image" width="280px" height="155px" />

                                                            </a>

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
                                                        <a class="thumbnail" href="#" id="backViewModalEdit" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
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
                                                        <a class="thumbnail" href="#" id="rightViewModalEdit" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="" data-target="#image-gallery">
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
                                                        <a class="thumbnail" href="#" id="leftViewModalEdit" data-toggle="modal" data-title="This is my title" data-caption="Some lovely red flowers" data-image="{{asset('images/cracked windscreen.jpg')}}" data-target="#image-gallery">
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

                                                            @if($selectedPolicyPlan->premium == $plan->premium)
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
                                                            <input id="billingCell" type="text" class="form-control" name="billingCell" aria-describedby="emailHelp" title="Billing number should only have 8 numbers" placeholder="Myzaka/Orange Cell" value="{{$customerBanking->billingCell}}" minlength="8" maxlength="8" pattern="[0-9]{8}" disabled>
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
                                                            <select id="bankBranchDropDown" class="form-control kt_selectpicker" title="Please select customers branch" name="branchCode" ></select>

                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Account Number:</label>
                                                            <input id="accountNumber" type="text" class="form-control" name="accountNumber" title="account number should only be numeric characters" value="{{$customerBanking->accountNumber}}" aria-describedby="emailHelp" placeholder="Account Number" pattern="[0-9]{1,25}" maxlength="25" >
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="form-group">
                                                            <label>Account Type:</label>
                                                            <select id="accountType" class="form-control kt_selectpicker" title="Please Select the Bank Account Type" name="bankAccountType" >

                                                                @if($customerBanking->accountType == 1)
                                                                <option value="1" selected>Cheque</option>
                                                                <option value="2">Savings</option>
                                                                @else
                                                                <option value="1">Cheque</option>
                                                                <option value="2" selected>Savings</option>
                                                                @endif


                                                            </select>


                                                            <!--Policy Details -->


                                                        <input type="hidden" name="policyNumber" value="{{$policies->policyNumber}}">
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

        <div class="col-lg-12 col-xl-12 order-lg-1 order-xl-1">
            <div class="kt-portlet kt-portlet--tabs kt-portlet--height-fluid">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title">
                            Claim History
                        </h3>
                    </div>
                    <div class="kt-portlet__head-toolbar">
                        <ul class="nav nav-tabs nav-tabs-line nav-tabs-line-brand nav-tabs-bold" role="tablist">
                            <li class="nav-item"> <a class="nav-link active" data-toggle="tab" href="#kt_portlet_tabs_1_1_1_content" role="tab"> Month </a> </li>

                        </ul>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="tab-content">
                        <div class="tab-pane fade active show" id="kt_portlet_tabs_1_1_1_content" role="tabpanel">
                            <div class="kt-scroll" data-scroll="true" style="height: 420px;" data-mobile-height="350">
                                <!--Begin::Timeline -->
                                <div class="kt-timeline">
                                    @foreach ($glassClaims as $glassClaim)

                                    <!--Begin::Item -->
                                    <div class="kt-timeline__item kt-timeline__item--success">
                                        <div class="kt-timeline__item-section">
                                            <div class="kt-timeline__item-section-border">
                                                <div class="kt-timeline__item-section-icon" > <i class="flaticon-bell kt-font-success"></i> </div>
                                            </div>
                                        <span class="kt-timeline__item-datetime">{{$glassClaim->incidentDate}}</span>
                                        </div>
                                    <a class="kt-timeline__item-text"> {{$glassClaim->causeOfDamage}}</a>
                                    </div>
                                    <!--End::Item -->

                                    @endforeach

                                </div>
                                <!--End::Timeline 1 -->
                            </div>
                        </div>
                    </div>
                </div>


            </div>
        </div>
    </div>

    <div class="tab-pane" id="transactions" role="tabpanel">
    <div class="kt-portlet kt-portlet--height-fluid">
                                        <div class="kt-portlet__head kt-portlet__head--lg kt-portlet__head--noborder kt-portlet__head--break-sm">
                                            <div class="kt-portlet__head-label">
                                                <h3 class="kt-portlet__head-title">
                                                    Installments
                                                </h3>
                                            </div>
                                            <div class="kt-portlet__head-toolbar">
                                                <div class="kt-portlet__head-wrapper d-none">

                                                <button class="btn btn-secondary mr-4" type="button" id="kt_datatable_check_all">Select all rows</button>
                                                <button class="btn btn-secondary mr-4" type="button" id="kt_datatable_uncheck_all">Unselect all rows</button>

                                                    <div id="checkedActions" class="">
                                                    <!-- <input   id="checking" class="kt-checkbox kt-checkbox--single kt-checkbox--solid" type="checkbox"> -->
                                                        <button type="button" id="schedule" class="btn btn-outline-success active" title="Pay Selected Installments Now">Pay off Mutiple Installments</button>
                                                      <!-- part payment button for realpay      -->
                                                      <!-- <button id="payNow" type="button"  data-toggle="modal" data-target="#installmentsModal" class="btn btn-outline-primary active"title="Part Payment">Part Payment</button> -->
                                                    </div>

                                                     <!-- <button type="button" class="btn btn-outline-brand" > <a href="" class="kt-nav__link"><i class="flaticon2-add-circular-button"></i></a></button> -->
                                                </div>
                                            </div>
                                        </div>

                <div class="kt-portlet__body kt-portlet__body--fit">
                        <!--Doc: For the datatable initialization refer to "recentOrdersInit" function in "src\theme\app\scripts\custom\dashboard.js" -->
                        <div class="kt-datatable" id="clientinstallmentDt"></div>
                </div>
                </div>
    </div>

    <div class="tab-pane" id="activity_log" role="tabpanel">

            <div class="col-lg-12 col-xl-12 order-lg-1 order-xl-1">
                    <div class="kt-portlet kt-portlet--tabs kt-portlet--height-fluid">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Activity Log
                                </h3>
                            </div>
                            <div class="kt-portlet__head-toolbar">
                                <ul class="nav nav-tabs nav-tabs-line nav-tabs-line-brand nav-tabs-bold" role="tablist">
                                    <li class="nav-item"> <a class="nav-link active" data-toggle="tab" href="#kt_portlet_tabs_1_1_1_content" role="tab"> Today </a> </li>

                                </ul>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                            <div class="tab-content">
                                <div class="tab-pane fade active show" id="kt_portlet_tabs_1_1_1_content" role="tabpanel">
                                    <div class="kt-scroll" data-scroll="true" style="height: 420px;" data-mobile-height="350">
                                        <!--Begin::Timeline -->
                                        <div class="kt-timeline">
                                            @foreach ($policyActivityLog as $activityLog)

                                            <!--Begin::Item -->
                                            <div class="kt-timeline__item kt-timeline__item--success">
                                                <div class="kt-timeline__item-section">
                                                    <div class="kt-timeline__item-section-border">
                                                        <div class="kt-timeline__item-section-icon" > <i class="flaticon-bell kt-font-success"></i> </div>
                                                    </div>
                                                <span class="kt-timeline__item-datetime">{{$activityLog->created_at}}</span>
                                                </div>
                                            <a href="" class="kt-timeline__item-text"> {{$activityLog->description}}</a>
                                                <div class="kt-timeline__item-info"> Security, Fieewall </div>
                                            </div>
                                            <!--End::Item -->

                                            @endforeach

                                        </div>
                                        <!--End::Timeline 1 -->
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>



    </div>
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
                            <div class="kt-timeline__item-info"> Security, Firewall </div>
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
    <script src="{{asset('css/app/custom/general/components/extended/blockui.min.js')}}" type="text/javascript"></script>
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

        @if(Session::has('policyDeactivated'))
        Toastify({
            text: "{{ Session::get('policyDeactivated') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#0099CC",
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

        @if(Session::has('residence'))
        Toastify({
            text: "{{ Session::get('residence') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
        }).showToast();

        @endif

        @if(Session::has('income'))
        Toastify({
            text: "{{ Session::get('income') }}",
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

            @if($vehicle->front != null)

            $('#frontViewModal').on('click', function() {

                $('#image-gallery-image').attr('src', 'https://s3.ap-south-1.amazonaws.com/alphadirect/' + "{{$vehicle->front}}");

            });
            @endif


            @if($vehicle->back != null)

            $('#backViewModal').on('click', function() {

                $('#image-gallery-image').attr('src', 'https://s3.ap-south-1.amazonaws.com/alphadirect/' + "{{$vehicle->back}}");

            });
            @endif


            @if($vehicle->right != null)

            $('#rightViewModal').on('click', function() {

                $('#image-gallery-image').attr('src', 'https://s3.ap-south-1.amazonaws.com/alphadirect/' + "{{$vehicle->right}}");

            });
            @endif


            @if($vehicle->left != null)


            $('#leftViewModal').on('click', function() {

                $('#image-gallery-image').attr('src', 'https://s3.ap-south-1.amazonaws.com/alphadirect/' + "{{$vehicle->left}}");

            });
            @endif



            //Preview of uploaded documents

            @if($customerKYC->driversLicense != null)

            $('#driversPreview').on('click', function(){

                $('#image-gallery-image').attr('src', 'https://s3.ap-south-1.amazonaws.com/alphadirect/' + "{{$customerKYC->driversLicense}}");

            });

            @endif


            @if($customerKYC->omang != null)

            $('#omangPreview').on('click', function() {

                $('#image-gallery-image').attr('src', 'https://s3.ap-south-1.amazonaws.com/alphadirect/' + "{{$customerKYC->omang}}");

            });
            @endif

            @if($vehicle->vehicleRegistration != null)

            $('#bluebookPreview').on('click', function() {

                $('#image-gallery-image').attr('src', 'https://s3.ap-south-1.amazonaws.com/alphadirect/' + "{{$vehicle->vehicleRegistration}}");

            });
            @endif

            @if($customerKYC->proofResidence != null)

            $('#porPreview').on('click', function() {

                $('#image-gallery-image').attr('src', 'https://s3.ap-south-1.amazonaws.com/alphadirect/' + "{{$customerKYC->proofResidence}}");

            });
            @endif

            @if($customerKYC->proofIncome != null)

            $('#poiPreview').on('click', function() {

                $('#image-gallery-image').attr('src', 'https://s3.ap-south-1.amazonaws.com/alphadirect/' + "{{$customerKYC->proofIncome}}");

            });
            @endif

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


            $(document).ready(function(){

                var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
                var make = $("#carMakeDropDown").val();
                if(make != null){
                $("#carSpinner").css("display", "contents");
                $.ajax({
                  /* the route pointing to the post function */
                  url: '{{Route('getCarModel')}}',
                  type: 'POST',
                  /* send the csrf-token and the input to the controller */
                  data: {_token: CSRF_TOKEN, make:make},
                  dataType: 'JSON',
                  /* remind that 'data' is the response of the AjaxController */
                  success: function (data) {
                      if(data){
                      $("#carModelDropDown").removeAttr('title');
                      $("#carModelDropDown").removeAttr('disabled');

                      $('#carModelDropDown').empty();
                      $.each(data, function(key, value){
                          $('#carModelDropDown').append('<option value="'+value.s_Variant+'">' + value.s_Variant +'</option>');
                          $('#carModelDropDown').val("{{$vehicle->model}}");
                          $("#carModelDropDown").selectpicker('refresh');

                      });

                      $("#carSpinner").css("display", "none");

                   }else{

                      $('#carModelDropDown').empty();
                   }
                  }

                     });
                    }
                  });


            $(document).ready(function(){

                $("#carSpinner").css("display", "none");

                  var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
                  $("#carMakeDropDown").change(function(){

                  $("#carSpinner").css("display", "contents");
                  var carMake =  $("#carMakeDropDown").val();

                $.ajax({
                    /* the route pointing to the post function */
                    url: '{{Route('getCarModel')}}',
                    type: 'POST',
                    /* send the csrf-token and the input to the controller */
                    data: {_token: CSRF_TOKEN, make:carMake},
                    dataType: 'JSON',
                    /* remind that 'data' is the response of the AjaxController */
                    success: function (data) {
                        if(data){
                        $("#carModelDropDown").removeAttr('title');
                        $("#carModelDropDown").removeAttr('disabled');

                        $('#carModelDropDown').empty();
                        $.each(data, function(key, value){
                            $('#carModelDropDown').append('<option value="'+value.s_Variant+'">' + value.s_Variant +'</option>');
                            $("#carModelDropDown").selectpicker('refresh');
                        });

                        $("#carSpinner").css("display", "none");

                     }else{

                        $('#carModelDropDown').empty();
                     }
                    }
                });
            });
        });

        $(document).ready(function(){

  var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
  var make = $("#carMakeDropDown").val();
  if(make != null){
  $("#carSpinner").css("display", "contents");
 $.ajax({
    /* the route pointing to the post function */
    url: '{{Route('getCarModel')}}',
    type: 'POST',
    /* send the csrf-token and the input to the controller */
    data: {_token: CSRF_TOKEN, make:make},
    dataType: 'JSON',
    /* remind that 'data' is the response of the AjaxController */
    success: function (data) {
        if(data){
        $("#carModelDropDown").removeAttr('title');
        $("#carModelDropDown").removeAttr('disabled');

        $('#carModelDropDown').empty();
        $.each(data, function(key, value){
            $('#carModelDropDown').append('<option value="'+value.s_Variant+'">' + value.s_Variant +'</option>');
            $('#carModelDropDown').val("{{$vehicle->model}}");
            $("#carModelDropDown").selectpicker('refresh');

        });

        $("#carSpinner").css("display", "none");

     }else{

        $('#carModelDropDown').empty();
     }
    }

});
  }
});

$(document).ready(function(){

        $(function(){
            $("#frontViewModalEdit").hide();
            $("#frontViewUpload").change(function(){
                $("#frontViewModalEdit").show();
            });
        });

        $(function(){
            $("#backViewModalEdit").hide();
            $("#backViewUpload").change(function(){
                $("#backViewModalEdit").show();
            });
        });

        $(function(){
            $("#rightViewModalEdit").hide();
            $("#rightViewUpload").change(function(){
                $("#rightViewModalEdit").show();
            });
        });

        $(function(){
            $("#leftViewModalEdit").hide();
            $("#leftViewUpload").change(function(){
                $("#leftViewModalEdit").show();
            });
        });

     /*    $(function(){
            $("#driversLicenseModalEdit").hide();
            $("#leftViewUpload").change(function(){
                $("#driversLicenseModalEdit").show();
            });
        });
 */

        var preferedBanking =  $("#billing").find("option[selected='']").val();

        if(preferedBanking == 'Bank'){


            $("#billingCell").prop('disabled', true);
            $("#bankNumDropDown").prop('disabled', false);
            $("#bankBranchDropDown").prop('disabled', false);
            $("#accountNumber").prop('disabled', false);
            $("#accountType").prop('disabled', false);
        }else{

            $("#billingCell").prop('disabled', false);
            $("#bankNumDropDown").prop('disabled', true);
            $("#bankBranchDropDown").prop('disabled', true);
            $("#accountNumber").prop('disabled', true);
            $("#accountType").prop('disabled', true);

        }
        console.log(preferedBanking);

    });


                function readURL(input) {

            if (input.files && input.files[0]) {
            var reader = new FileReader();

            reader.onload = function(e) {
                $('#frontViewEdit').attr('src', e.target.result);
                $('#image-gallery-image').attr('src', e.target.result);
                $('#frontViewModal').on('click', function(){

                $('#image-gallery-image').attr('src', e.target.result);

                });
            }

            reader.readAsDataURL(input.files[0]);
             }
            }

            function readURLBack(input) {

            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function(e) {
                    $('#backView').attr('src', e.target.result);
                    $('#image-gallery-image').attr('src', e.target.result);
                    $('#backViewModal').on('click', function(){

                $('#image-gallery-image').attr('src', e.target.result);

                });



                }

                reader.readAsDataURL(input.files[0]);
                }
            }
            function readURLRight(input) {

            if (input.files && input.files[0]) {
            var reader = new FileReader();

            reader.onload = function(e) {
                $('#rightView').attr('src', e.target.result);
                $('#image-gallery-image').attr('src', e.target.result);

                $('#rightViewModal').on('click', function(){

            $('#image-gallery-image').attr('src', e.target.result);

            });

            }


            reader.readAsDataURL(input.files[0]);
            }
            }
            function readURLLeft(input) {

            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function(e) {
                    $('#leftView').attr('src', e.target.result);
                    $('#image-gallery-image').attr('src', e.target.result);

                    $('#leftViewModal').on('click', function(){

                    $('#image-gallery-image').attr('src', e.target.result);

                    });
                }

                reader.readAsDataURL(input.files[0]);
                }
            }


        //Front view preview
        $("#frontViewUpload").change(function() {
            readURL(this);
            });

            //Back view preview
            $("#backViewUpload").change(function() {
            readURLBack(this);
            });

            //Right view preview
            $("#rightViewUpload").change(function() {
             readURLRight(this);
            });

            //Left view preview
            $("#leftViewUpload").change(function() {
                readURLLeft(this);
            });


            function alphaOnly(event) {
        var key = event.keyCode;
        return ((key >= 65 && key <= 90) || key == 8);
        };
        function isNumberKey(evt){
				var charCode = (evt.which) ? evt.which : event.keyCode
				if (charCode > 31 && (charCode < 48 || charCode > 57))
					return false;
				return true;
			}
        function clearForm(evt){
            document.getElementById('kt_form').reset();
            document.getElementById('kt_form').scrollIntoView();
		}
        function validateFileTypeFront(){
            var fileName = document.getElementById("front").value;
            var idxDot = fileName.lastIndexOf(".") + 1;
            var extFile = fileName.substr(idxDot, fileName.length).toLowerCase();
            if (extFile=="jpg" || extFile=="jpeg" || extFile=="png"){
                //TO DO
            }else{
                alert("Only jpg/jpeg and png files are allowed!");
            }
    }
        function validateFileTypeBack(){
            var fileName = document.getElementById("back").value;
            var idxDot = fileName.lastIndexOf(".") + 1;
            var extFile = fileName.substr(idxDot, fileName.length).toLowerCase();
            if (extFile=="jpg" || extFile=="jpeg" || extFile=="png"){
                //TO DO
            }else{
                alert("Only jpg/jpeg and png files are allowed!");
            }
    }
        function validateFileTypeRight(){
            var fileName = document.getElementById("right").value;
            var idxDot = fileName.lastIndexOf(".") + 1;
            var extFile = fileName.substr(idxDot, fileName.length).toLowerCase();
            if (extFile=="jpg" || extFile=="jpeg" || extFile=="png"){
                //TO DO
            }else{
                alert("Only jpg/jpeg and png files are allowed!");
            }
    }
        function validateFileTypeLeft(){
            var fileName = document.getElementById("left").value;
            var idxDot = fileName.lastIndexOf(".") + 1;
            var extFile = fileName.substr(idxDot, fileName.length).toLowerCase();
            if (extFile=="jpg" || extFile=="jpeg" || extFile=="png"){
                //TO DO
            }else{
                alert("Only jpg/jpeg and png files are allowed!");
            }
    }



    /*BLOCK UI FOR S3 DOWNLOADED IMAGES*/

 /*    $('#kt_blockui_1_1').click(function() {
            KTApp.block('#kt_blockui_1_content', {});

            setTimeout(function() {
                KTApp.unblock('#kt_blockui_1_content');
            }, 2000);
        }); */

        $(".blockUI").one("load", function() {

            KTApp.block('#s3Front', {});
            KTApp.block('#s3Back', {});
            KTApp.block('#s3Left', {});
            KTApp.block('#s3Right', {});

        // do stuff
        }).each(function() {
        if(this.complete) {

            KTApp.unblock('#s3Front');
            KTApp.unblock('#s3Back');
            KTApp.unblock('#s3Left');
            KTApp.unblock('#s3Right');

         }
        });



// Upload front image
var urlValue = '{{ \Config::get('values.graphite_url') }}'



                        @if($customerBanking->bankName != null)


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

                        @endif

            $('#uploadFrontButton').on('click', function() {

            });

            $("#activatePolicyButton").on("click", function() {

                @if($customerBanking->bankName != null)
                $.ajax({
                                // the route pointing to the post function
                                url: urlValue+'realpay/activateClient',
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
                                     empCode:"OT",
                                     firstActionDate:"{{str_replace(' 00:00:00',' 00:00',$customerBanking->billingStartDate)}}",
                                     installmentFrequency:"M",
                                     product:"FNBNDOBW",
                                     installmentAmount:"{{12/100 * $selectedPolicyPlan->premium + $selectedPolicyPlan->premium}}"

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

                @else
                    console.log('Nothing');
                @endif






            });
        });
    </script>

    <script type="text/javascript">

    function disableOptions(evt){

var billingMethod = document.getElementById("billing").value;


    if(billingMethod == 'Bank'){

        $("#billingCell").prop('disabled', true);
                $("#bankNumDropDown").prop('disabled', false);
                $("#bankBranchDropDown").prop('disabled', false);
                $("#accountNumber").prop('disabled', false);
                $("#accountType").prop('disabled', false);

    } else{

            document.getElementById("billingCell").disabled = false;
            $("#bankNumDropDown").prop('readonly', true);
            $("#bankBranchDropDown").prop('readonly', true);
            $("#accountNumber").prop('disabled', true);
            $("#accountType").prop('disabled', true);


    }

/*if (billingMethod === 'MyZaka'){
        document.getElementById("billingCell").disabled = false;
        document.getElementById("bankName").disabled = true;
        document.getElementById("branchCode").disabled = true;
        document.getElementById("accountNumber").disabled = true;
}

if (billingMethod === 'orangeMoney'){
        document.getElementById("billingCell").disabled = false;
        document.getElementById("bankName").disabled = true;
        document.getElementById("branchCode").disabled = true;
        document.getElementById("accountNumber").disabled = true;
}*/

}

function requireOmangOrPass(){
            let omang = $("#omang");
            let pass = $("#passport");

            if(omang.val().trim()=="") {
                pass.attr("required", "")
            } else if(pass.val().trim()=="") {
                pass.attr("required", "")
                omang.attr("required", "")
            } else {
                pass.attr("required", "")
            }
        }

        requireOmangOrPass();

    </script>


<!-- datatable for clients installments -->
<script>


// Conrfirmation Swal for print outs
       $(document).ready(function() {
            // var table = $('#clientinstallmentDt').DataTable();
            $("#clientinstallmentDt").on("click", "tr .printReciept",
            function (e) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You are about to print a Reciept",
                    type: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, Print This Reciept'
                }).then((result) => {
                    if (result.value) {

                        Swal.fire({
                            title:'Printed',
                            text:'Reciept Printed',
                            type:'success'
                        }) .then((result) => {
                            $(this).parent().submit();

                        });
                    }
                });
        });
        });

        $(document).ready(function() {
            // var table = $('#clientinstallmentDt').DataTable();
            $("#clientinstallmentDt").on("click", "tr .printInvoice",
            function (e) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You are about to print a Invoice",
                    type: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, Print This Invoice'
                }).then((result) => {
                    if (result.value) {

                        Swal.fire({
                            title:'Printed',
                            text:'Invoice Printed',
                            type:'success'
                        }) .then((result) => {
                            $(this).parent().submit();

                        });
                    }
                });
        });
        });

    $.urlParam = function(name){
        var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
        if (results==null) {
            return null;
        }
        return decodeURI(results[1]) || 0;
    }
    var urlValue = '{{ \Config::get('values.graphite_url') }}'
var datatable = $('#clientinstallmentDt').KTDatatable({
  // datasource definition
  data: {
    type: 'remote',
    source: {
      read: {
        method:'GET',
        url: urlValue+'realpay/getInstallments?clientNumber=MIS2019000101'+"{{$policies->policyNumber}}",
        map: function(raw) {
            $('.loading').css("display","none");
            $('.kt-portlet__head-wrapper').removeClass("d-none");
          // sample data mapping
           dataSet = raw;
          if (typeof raw.data !== 'undefined') {
            dataSet = raw.data;
          }
          return dataSet;
        },
      },
    },
    pageSize: 10,
    serverPaging: true,
    serverFiltering: false,
    // serverSorting: true,

  },

  // layout definition
  layout: {
    scroll: false,
    footer: false,
  },

  // column sorting
  sortable: true,

  pagination: true,

  search: {
    input: $('#query'),


  },


  // columns definition
  columns: [
    // {
    //     field: 'checkbox',
    //     title: '#',
    //     sortable: false,
    //     width: 30,
    //     type: 'number',
    //     selector: { class: 'kt-checkbox--solid', id: "actionCheck" },
    //     textAlign: 'center',
	// },

    {
        field: 'CheckBox',
        title: '#',
        sortable: false,
        width: 30,
        template: function(row, index, datatable) {

            if(row['ns0:status'] == 'I' ){

            return '<i class="flaticon2-accept"></i>';

            }

            if(row['ns0:status'] == 'A' ){
                return '<input id="checking" class="checkBoxClass kt-checkbox kt-checkbox--single kt-checkbox--solid" type="checkbox" data-date="'+row['ns0:actionDate']+'" data-id="'+row['ns0:installmentReferenceNumber']+'" data-amount="'+row['ns0:totalInstallmentAmount']+'" >';
            }
      },
    },

    {
      field: 'actionDate',
      title: 'Billing Date',
      template: function(row, index, datatable) {
        return row['ns0:actionDate'];
      },
    },

    {
      field: 'totalInstallmentAmount',
      title: 'Total Billing Amount',
      template: function(row, index, datatable) {
        return row['ns0:totalInstallmentAmount'];
      },
    },


    {
      field: 'status',
      title: 'Status',
      template: function(row, index, datatable) {
          let future="SCHEDULED";
          let cancelled="PAID";
          let successfull="SUCCESSFUL";

        if(row['ns0:status'] == 'A' ){
        return future;
        }

        if(row['ns0:status'] == 'I' ){
        return cancelled;
        }

        if(row['ns0:status'] == 'S' ){
        return successfull;
        }
      },
    },

    {
        width: 150,
      field: 'activate',
      title: 'Actions',
      sortable: false,
      template: function(row, index, datatable) {

        if(row['ns0:status'] == 'A'){

            $('#payButton').click(function(e){

            Swal.fire({
                title: 'Are you sure?',
                text: "You are about to pay for a Clients Installment!",
                type: 'question',

                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Pay For Installment!'
                }).then((result) => {
                if (result.value) {
                    document.getElementById('rpTransactions').submit();

                    Swal.fire({
                    title:'PAID',
                    text:'Installment Paid',
                    type:'success'
                    }) .then((result) => {

                        window.location.reload();
                    })
                }
             })


       });
         return  '<form method="POST" id="rpTransactions" class="kt-form" action="{{ \Config::get('values.graphite_url') }}/realpay/editInstallment">\
                                <input type="hidden" name="status" value="I">\
                                <input type="hidden" name="installmentReferenceNumber" value="'+row['ns0:installmentReferenceNumber']+'">\
                                <input type="hidden" name="red" value="/admin/ClientsContracts?id='+$.urlParam("id")+'"/>\
                                <button id="payButton" class="btn btn-outline-primary active" type="button">Pay</button>\
                  </form>'
            }

            if(row['ns0:status'] == 'I'){
                return  '<button type="button" class="btn btn-outline-success" disabled="disabled">Paid</button>'
            }

      },
    },

    {
        width: 150,
        field: 'printouts',
        title: 'Print Outs',
        sortable: false,
        template: function(row, index, datatable) {


    //    $('#invoiceAlert').click(function(e){

    // Swal.fire({
    //             title: 'Are you sure?',
    //             text: "You are about to print an invoice!",
    //             type: 'question',

    //             showCancelButton: true,
    //             confirmButtonColor: '#3085d6',
    //             cancelButtonColor: '#d33',
    //             confirmButtonText: 'Yes, Print This Invoice!'
    //             }).then((result) => {
    //             if (result.value) {
    //                 document.getElementById('rpInvoice').submit();

    //                 Swal.fire({
    //                 title:'Printed',
    //                 text:'Invoice Printed',
    //                 type:'success'
    //                 }) .then((result) => {

    //                     window.location.reload();
    //                 })

    //             }
    //                 })


    //    });

        if(row['ns0:status'] == 'A'){


         return  '<form method="POST" id="rpInvoice" class="kt-form" action="{{Route('printInvoice')}}">\
                                {{csrf_field()}}\
                                <input type="hidden" name="status" value="I">\
                                <input type="hidden" name="customerName" value="{{$userDetails->firstName}} {{$userDetails->lastName}}">\
                                <input type="hidden" name="date" value="'+row['ns0:actionDate']+'">\
                                <input type="hidden" name="amount" value="'+row['ns0:totalInstallmentAmount']+'">\
                                <button class="btn btn-outline-success active printInvoice" type="button">Print Invoice</button>\
                  </form>'
            }

        if(row['ns0:status'] == 'I'){


            return  '<form method="POST" id="rpReciept" action="{{Route('printReciept')}}" class="kt-form">\
                    {{csrf_field()}}\
                    <input type="hidden" name="customerName" value="{{$userDetails->firstName}} {{$userDetails->lastName}}">\
                    <input type="hidden" name="date" value="'+row['ns0:actionDate']+'">\
                    <input type="hidden" name="amount" value="'+row['ns0:totalInstallmentAmount']+'">\
                <button type="button" class="btn btn-outline-warning active printReciept">Print Receipts</button>\
                </form>'
        }





      },
    },


    /*{
      field: 'status',
      title: 'Status',
      // callback function support for column rendering
      template: function(row) {
        var status = {
          1: {'title': 'Pending', 'class': 'kt-badge--brand'},
          2: {'title': 'Delivered', 'class': ' kt-badge--metal'},
          3: {'title': 'Canceled', 'class': ' kt-badge--primary'},
          4: {'title': 'Success', 'class': ' kt-badge--success'},
          5: {'title': 'Info', 'class': ' kt-badge--info'},
          6: {'title': 'Danger', 'class': ' kt-badge--danger'},
          7: {'title': 'Warning', 'class': ' kt-badge--warning'},
        };


        return '<span class="kt-badge ' + status[row.status].class + ' kt-badge--inline kt-badge--pill">' + status[row.status].title + '</span>';
      },
    }, {
      field: 'type',
      title: 'Type',
      // callback function support for column rendering
      template: function(row) {
        var status = {
          1: {'title': 'Online', 'state': 'danger'},
          2: {'title': 'Retail', 'state': 'primary'},
          3: {'title': 'Direct', 'state': 'accent'},
        };
        return '<span class="kt-badge kt-badge--' + status[row.type].state + ' kt-badge--dot"></span>&nbsp;<span class="kt-font-bold kt-font-' + status[row.type].state + '">' +
            status[row.type].title + '</span>';
      },
    }, */

    // {
    //   field: 'Actions',
    //   title: 'Actions',
    //   sortable: true,
    //   width: 150,
    //   overflow: 'visible',
    //   textAlign: 'center',
    //   template: function(row, index, datatable) {
    //     var dropup = (datatable.getPageSize() - index) <= 4 ? 'dropup' : '';

    //     let currentEditData;
    //     $(".showEditModal").on("click", function() {
    //         console.log("Click");
    //         currentEditData = JSON.parse($(this).data("data")+"\"}");

    //         $("#id").val(currentEditData.id);
    //         $("#firstName").val(currentEditData.firstName);
    //         $("#lastName").val(currentEditData.lastName);
    //         $("#cellphone").val(currentEditData.cellphone);
    //         $("#dob").val(currentEditData.dob);
    //         $("#licensePlate").val(currentEditData.licensePlate);
    //         $("#omang").val(currentEditData.omang);
    //         $("#billing").val(currentEditData.billing);
    //         $("#billingCell").val(currentEditData.billingCell);
    //         $("#bankName").val(currentEditData.bankName);
    //         $("#branchCode").val(currentEditData.branchCode);
    //         $("#accountNumber").val(currentEditData.accountNumber);
    //     });

    //     $(".showModal").on(".hidden.bs.modal", ()=>{
    //         currentEditData = null;
    //     });

    // return '<div class="dropdown ' + dropup + '">\
    //                     <a href="#" class="btn btn-hover-brand btn-icon btn-pill" data-toggle="dropdown">\
    //                         <i class="flaticon2-arrow-down"></i>\
    //                     </a>\
    //                     <div class="dropdown-menu dropdown-menu-right">\
    //                             <form class="dropdown-item" method="POST" action="{{ \Config::get('values.graphite_url') }}/realpay/editInstallment">\
    //                                 <input type="hidden" name="status" value="I">\
    //                                 <input type="hidden" name="installmentReferenceNumber" value="'+row['ns0:installmentReferenceNumber']+'"/>\
    //                                 <input type="hidden" name="red" value="/admin/ClientsContracts?id='+$.urlParam("id")+'"/>\
    //                                 <input type="submit" value="Cancel" class="dropdown-item"/>\
    //                             </form>\
    //                     </div>\
    //                 </div>\
    //                 ';

    //   },
    // }

],
});







$('#submit').click(function(e){

    console.log("hello");

    Swal.fire({
                title: 'Are you sure?',
                text: "You are about to submit your quote!",
                type: 'question',

                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Pay For Installment!'
                }).then((result) => {
                if (result.value) {
/*                     document.getElementById('rptransaction').submit();
 */
                    Swal.fire({
                    title:'PAID',
                    text:'Installment Paid',
                    type:'success'
                    }) .then((result) => {


                        window.location.reload();
                    })
                }
            })


       });










$('#schedule').on('click', function() {
    var urlValue = '{{ \Config::get('values.graphite_url') }}'
    Swal.fire({
                title: 'Are you sure?',
                text: "You are about to pay for multiple Installment!",
                type: 'question',

                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Pay these Installment!'
                }).then((result) => {
                    if (result.value) {
                    $('.loading').css("display","");

                                var arr =  [];
                                var checkedArray =
                                Array.of(
                                    $(".kt-datatable__body")
                                    .find(":checked")
                                );

                                for(var i = 0; i < checkedArray[0].length; i++) {
                                    arr.push($(checkedArray[0][i]).data("id"));
                                }

                                $.ajax({
                                    type: "POST",
                                    url: urlValue+"realpay/editInstallment/m",
                                    data: {"refs": arr, "action": "I"},
                                    dataType: 'text',
                                    success: function(){
                                        console.log("Success");
                                        document.location.reload();
                                    },
                                    fail: ()=>{
                                        console.log("Failed");
                                        $('.loading').css("display","none");

                                    }
                                });

                                Swal.fire({
                    title:'PAID',
                    text:'Selected Installments Paid',
                    type:'success'
                    }) .then((result) => {


                        window.location.reload();
                    })

                    }
                    })



 });

$('#payNow').on('click', function() {
    // $('#payNow').addClass("disabled");
    var urlValue = '{{ \Config::get('values.graphite_url') }}'
    let arr =  [];
    let total = 0;
    let checkedArray = Array.of(
        $(".kt-datatable__body")
        .find(":checked")
    );
    $(".modal-body").empty();
    for(let i = 0; i < checkedArray[0].length; i++) {
        let obj ={};
        total += Number($(checkedArray[0][i]).data("amount"));
        obj[$(checkedArray[0][i]).data("id")] = $(checkedArray[0][i]).data("amount");
        obj[$(checkedArray[0][i]).data("id")] = $(checkedArray[0][i]).data("date");

        arr.push(obj);
        $(".modal-body").append("<h6><label>Price of Installemt:</label></h6>");
        $(".modal-body").append("<input class='form-control' name='"+$(checkedArray[0][i]).data("id")+"' value='"+$(checkedArray[0][i]).data("amount")+"'>");
        $(".modal-body").append("<h6><label>Date of Installemt:</label></h6>");
        $(".modal-body").append("<input class='form-control' name='"+$(checkedArray[0][i]).data("id")+"' value='"+$(checkedArray[0][i]).data("date")+"'>");

    }
    // console.log(total);
    // $(".modal-body").append("<h1 class ='text-right'>Amount is  is: P"+total+"</h1>");
    // $(".modal-body").append("<h1 class ='text-right'>Total Amount is  is: P </h1>");
    $(".modal-body").append("<h1 class ='text-right'>Total Amount is  is: P"+total+"</h1>");
    // $(".modal-body").append("<p align='right'><button type='button' class='btn btn-warning button-right'>Calculate</button></p>");
});


//     $('.loading').css("display","");
//     if(!$('#payNow').hasClass("disabled")) {
//         $('#payNow').addClass("disabled");
//         let arr =  [];
//         let checkedArray =
//         Array.of(
//             $(".kt-datatable__body")
//             .find(":checked")
//         );

//         for(let i = 0; i < checkedArray[0].length; i++) {
//             arr.push($(checkedArray[0][i]).data("id"));
//         }

//         $.ajax({
//             type: "POST",
//             url: urlValue+"realpay/editInstallment/m",
//             data: {
//                 "refs": arr,
//                 "action": "I"
//             },
//             success: ()=>{
//                 window.location = "/admin/ClientsContracts?id="+$.urlParam("id")
//             },
//             fail: ()=>{
//                 $('.loading').css("display","none");
//                 $('#payNow').removeClass("disabled");
//             }
//         });
//     }
// });

// function completePay() {
//     $('.loading').css("display","");
//     if(!$('#cancel').hasClass("disabled")) {
//         $('#cancel').addClass("disabled");
//         let arr =  [];
//         let checkedArray =
//         Array.of(
//             $(".kt-datatable__body")
//             .find(":checked")
//         );

//         for(let i = 0; i < checkedArray[0].length; i++) {
//             arr.push($(checkedArray[0][i]).data("id"));
//         }

//         $.ajax({
//             type: "POST",
//             url: urlValue"realpay/editInstallment/m",
//             data: {
//                 "refs": arr,
//                 "action": "I"
//             },
//             success: ()=>{
//                 window.location = "/admin/ClientsContracts?id="+$.urlParam("id")
//             },
//             fail: ()=>{
//                 $('.loading').css("display","none");
//                 $('#cancel').removeClass("disabled");
//             }
//         });
//     }
// }

$('#kt_datatable_check_all').on('click', function() {
			// datatable.setActiveAll(true);

            $(".checkBoxClass").prop('checked', true);
        });



		$('#kt_datatable_uncheck_all').on('click', function() {
			// datatable.setActiveAll(false);
			$(".checkBoxClass").prop('checked', false);
        });

        // $(document).ready(()=>{
        //     $(".checkBoxClass").on("click", ()=>{
        //     })
        // })

//         function getChecked() {

//                         var checker = document.getElementById('checking');
//                         var sendbtn = document.getElementById('checkbutton');
//                         // when unchecked or checked, run the function
//                         // checker.onchange = function(){

//                         // }
//   }


</script>



</body>
<!-- end::Body -->

</html>
