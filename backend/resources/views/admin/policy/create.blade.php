<!DOCTYPE html>
<html lang="en" >
<?php /*dd($products); */?>
@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />


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

    @if(!empty($selectedCustomer))
        <div class="alert alert-success fade show" role="alert">
            <div class="alert-text"><strong>Success:</strong> Customer Selected Successfully !</div>
            <div class="alert-close">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true"><i class="la la-close"></i></span>
                </button>
            </div>
        </div>
    @endif

    <div class="alert alert-success mt-2  fade show"  role="alert" id="success" style="display:none;">
        {{ Session::get('success') }}
        <p class="text ">Success</p>
        <button type="button" class="close" data-dismiss="alert">×</button>
    </div>
    <div class="alert alert-warning mt-2  fade show" role="alert" id="error" style="display:none;">

        <p class="error-message"></p>
        <button type="button" class="close" data-dismiss="alert">×</button>
    </div>
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Create Policy
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{ route('admin.policy.index') }}" class="kt-subheader__breadcrumbs-link"> Policy </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">

            <!--begin::Portlet-->
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title">
                            Add New Policy
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin">
                        1. Customer Details:
                    </h3>
                    <!--begin::Form-->
                    <form id="policyForm" action="{{ route('admin.policy.store') }}"
                          method="POST" enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="form-group">
                                    <label>Omang ID</label>
                                    <input id="omang" type="text" class="form-control id-type validateGroup1" name="omang" placeholder="Please enter omang number" onkeypress="return isNumberKey(event)"  pattern="[0-9]{9}" maxlength="9"  value="{{ !empty($selectedCustomer) ? $selectedCustomer->profile->omang : old('omang') }}">
                                    <p class="existingOmangError" style="color:#e61c30;display:none;" >Customer with this Omang ID already exist</p>
                                    <p class="OmangError" style="color:#e61c30;display:none;" >Please Input numbers only</p>
                                </div>
                                <div class="form-group">
                                    <label>Passport Number</label>
                                    <input id="passport" type="text" class="form-control id-type" name="passport" placeholder="Please enter passport number" maxlength="12" value="{{ !empty($selectedCustomer) ? $selectedCustomer->profile->passport : old('passport') }}" >
                                    <p class="existingPassportError" style="color:#e61c30; display: none;">Customer with this Passport Number already exist</p>
                                    <p class="invalidPassportError" style="color:#e61c30; display: none;">Please provide a valid passsport number</p>
                                </div>
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" class="form-control firstName"  name="fname" placeholder="First Name" title="Please enter customer first name" pattern="[A-Za-z]{1,25}" maxlength="25" value="{{ !empty($selectedCustomer) ? $selectedCustomer->firstName : old('fname') }}"  required>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" class="form-control lastName" name="lname" title="Please enter customer last name" placeholder="Last Name" value="{{ !empty($selectedCustomer) ? $selectedCustomer->lastName : old('lname') }}" pattern="[A-Za-z]{1,25}" maxlength="25" required>
                                </div>
                                <div class="form-group">
                                    <label>Gender</label>
                                    <div class="kt-radio-inline">
                                        <label class="kt-radio">
                                            <input type="radio" name="gender" class="input-group gender" id="male" value="1" @if(!empty($selectedCustomer) && $selectedCustomer->profile->gender == 1) checked @endif
                                            class="premiumField">
                                            Male <span></span>
                                        </label>
                                        <label class="kt-radio">
                                            <input type="radio" name="gender" class="input-group gender" id="female" value="0" @if(!empty($selectedCustomer) && $selectedCustomer->profile->gender == 0) checked @endif class="premiumField">
                                            Female<span></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Cellphone Number</label>
                                    <input type="text" class="form-control cellphone" name="cellphone"  placeholder="Please enter customers cellphone" value="{{ !empty($selectedCustomer) ? $selectedCustomer->profile->cellphone : old('cellphone') }}" pattern="[0-9]{1,25}" maxlength="8" required>
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" class="form-control email" name="email" placeholder="Please enter customer email address" value="{{ !empty($selectedCustomer) ? $selectedCustomer->email : old('email') }}">
                                </div>
                                <div class="form-group">
                                    <label>Physical Address</label>
                                    <input type="text" class="form-control address" name="address" placeholder="Please enter customers physical address" value="{{ !empty($selectedCustomer) ? $selectedCustomer->profile->address : old('address') }}"  maxlength="60" required>
                                </div>
                                <div class="form-group">
                                    <label>Date Of Birth</label>
                                    <input type="text" class="form-control kt_datepicker_1 dob" name="dob" id="dob" autocomplete="off" value="{{ !empty($selectedCustomer) ? $selectedCustomer->profile->dob : old('dob') }}"  placeholder="Select date"/>
                                </div>
                            </div>
                            {{--Data from ajax request--}}
                            <div class="col-lg-4" style="border:1px dashed gray; text-align: center; padding: 0px; display: none; height: fit-content;" id="customerDiv">
                                <table class="table table-striped m-table" style="text-align: left;">
                                    <tbody id="customerTableBody">
                                    <tr>
                                        <th colspan="2" style="text-align: center;">Customer Details</th>
                                    </tr>
                                    </tbody>
                                </table>
                                <a class="btn btn-brand"  id="select" style="text-align: center; color: #FFF; margin-bottom: 10px;"
                                   data-toggle="tooltip">Select</a>
                            </div>
                        </div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="row">
                            <div class="col-lg-3" style="margin-bottom: 15px;">
                                <h6>Do you have Activation Code?</h6>
                                <label class="kt-radio">
                                    <input type="radio" name="check_activation" class="premiumField condition"
                                           value="Yes">Yes<span></span>
                                </label>
                                <label class="kt-radio" style="margin-left: 10px;">
                                    <input type="radio" name="check_activation" class="premiumField condition"
                                           value="No">No<span></span>
                                </label>
                            </div>
                            <div class="col-lg-9">
                                <div class="form-group" id="input_activation" style="display:none;">
                                    <label>Activation Code</label>
                                    <input type="text" name="activation_code" id="activation_code"
                                           class="form-control col-md-6" placeholder="Enter Activation Code">
                                </div>
                                <input type="button" id="generate_code" value="Generate Activation Code"
                                       class="btn btn-primary col-md-3"
                                       style="float: left; clear: left; display:none;">
                                <div class="col-lg-9" id="codeDiv" style="margin: 0.5% 0 0 20%;"></div>
                            </div>
                        </div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <h3 class="kt-heading kt-heading--md">
                            2. Product Selection:
                        </h3>
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="form-group">
                                    <label>Please choose the product</label>
                                    <select class="form-control kt_selectpicker" id="product" name="product" title="Please choose product" data-live-search="true" id="product" required>
                                        @foreach($products as $product)
                                            <option data-subtext="@if($product->type){!! $product->type->name !!} @endif" value="{!! $product->id !!}" content="{!! $product->product_type_id !!}">{!! $product->name !!}</option>
                                        @endforeach
                                    </select>
                                    <p id="existingProduct" style="color:#e61c30;display:none;" >Selected Customer already has a Policy for this Product</p>
                                </div>
                            </div>
                        </div>
                        <div class="row" id="factor_main"></div>
                        <div id="vehicle_section" style="display: none;">
                            <h3 class="kt-heading kt-heading--md">
                                Vehicle Selection:
                            </h3>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Vehicle Number</label>
                                        <input type="text" class="form-control vehiclePlate" name="vehiclePlate"  id="vehicle_number" placeholder="Enter vehicle number">
                                        <p id="vehicleError" style="display:none; color:red;">Policy already taken for this Vehicle</p>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Chassis Number(VIN Code)</label>
                                        <input type="text" class="form-control chassisNo" name="chassisNo"  placeholder="Enter chassis number">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Odometer</label>
                                        <input type="text" class="form-control odometer" name="odometer"  placeholder="Enter odometer number">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Purpose</label>
                                        <select class="form-control kt_selectpicker purpose" name="purpose" title="Please select purpose" data-live-search="true" id="purpose">
                                            @foreach($vehicle_purpose as $purpose)
                                                <option value="{!! $purpose->id !!}">{!! $purpose->value !!}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label class="errorLabel">Condition</label>
                                        <div class="kt-radio-inline">
                                            <label class="kt-radio">
                                                <input type="radio" name="condition" value="very poor" class="premiumField condition">
                                                Very Poor <span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="condition" value="poor" class="premiumField condition">
                                                Poor<span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="condition" value="good" class="premiumField condition">
                                                Good <span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="condition" value="very good" class="premiumField condition">
                                                Very Good <span></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div id="condition-error" class="error invalid-feedback" style="display: none;"></div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Year of Manufacture</label>
                                        <select class="form-control  kt_selectpicker"  name="date" title="Please choose Year" data-live-search="true" data-size="5">
                                            <?php
                                            for($i = date("Y"); $i >=date('1901'); $i--){
                                                echo "<option>" . $i . "</option>";
                                            }
                                            ?>

                                        </select>

                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Make</label>
                                        <select class="form-control kt_selectpicker make"
                                                title="Please choose make" data-live-search="true"
                                                name="make" id="make" data-size="5">
                                            @foreach($vehicleMakes as $vehicleMake)
                                                <option value="{{$vehicleMake->s_Make}}">{{$vehicleMake->s_Make}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Model</label>
                                        <select class="form-control kt_selectpicker model"
                                                title="Please choose model" data-live-search="true"
                                                id="model" name="model" data-size="5">


                                        </select>
                                    </div>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Engine Number</label>
                                        <input type="text" class="form-control engineNo" name="engineNo" autocomplete="off" placeholder="Enter Engine Number" title="Enter numbers only">

                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>No Of Seats</label>
                                        <input type="text" class="form-control seats" name="seats" title="Enter numbers only" placeholder="Enter No Of Seats"/>

                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Number of Cylinders</label>
                                        <input type="text" class="form-control cylinders" name="cylinders" title="Enter numbers only"
                                               placeholder="Enter Number of Cylinders"/>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label> Cubic Capacity</label>
                                        <input type="text" class="form-control cubic_capacity" name="cubic_capacity"  placeholder="Enter Cubic Capacity"/>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Is it imported?</label>
                                        <div class="kt-radio-inline">
                                            <label class="kt-radio">
                                                <input type="radio" name="is_imported" value="1" class="premiumField ">
                                                Yes <span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="is_imported" value="0" class="premiumField ">
                                                No<span></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Is the vehicle fitted with tracking device?</label>
                                        <div class="kt-radio-inline">
                                            <label class="kt-radio">
                                                <input type="radio" name="is_tracking" value="1" class="premiumField ">
                                                Yes <span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="is_tracking" value="0" class="premiumField ">
                                                No<span></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Is the vehicle used for private use?</label>
                                        <div class="kt-radio-inline">
                                            <label class="kt-radio">
                                                <input type="radio" name="is_private" value="1" class="premiumField ">
                                                Yes <span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="is_private" value="0" class="premiumField ">
                                                No<span></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Is the vehicle modified in any way?</label>
                                        <div class="kt-radio-inline">
                                            <label class="kt-radio">
                                                <input type="radio" name="is_modified" value="1" class="premiumField ">
                                                Yes <span></span>
                                            </label>
                                            <label class="kt-radio">
                                                <input type="radio" name="is_modified" value="0" class="premiumField ">
                                                No<span></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <h3 class="kt-heading kt-heading--md">
                                Vehicle Images
                            </h3>
                            <div class="form-group row">
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Left<span id="leftError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                    <div class="kt-avatar" id="left" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="left" name="left" id="left1" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Right<span id="rightError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                    <div class="kt-avatar" id="right" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="right" name="right" id="right1" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Back<span id="backError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                    <div class="kt-avatar" id="back" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="back" name="back" id="back1" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Front<span id="frontError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                    <div class="kt-avatar" id="front" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="front" name="front" id="front1" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Vehicle Registration<span id="registrationError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                    <div class="kt-avatar" id="vehicleRegistration" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="vehicleRegistration" title="please select Image"  name="vehicleRegistration" id="registration1" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Vehicle Invoice<span id="invoiceError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                    <div class="kt-avatar" id="vehicle_valuation" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="vehicle_valuation" title="please select Image"  name="vehicle_valuation" id="vehicle_valuation1" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                                <!--vehicle_valuation-->
                            </div>
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                            <h3 class="kt-heading kt-heading--md">
                                Coverages
                            </h3>
                            <div class="row">
                                <table class="table table-striped m-table">
                                    <tbody id="coverageDiv"></tbody>
                                </table>
                            </div>
                            <div id="motors" style="display:none;">
                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                <h3 class="kt-heading kt-heading--md">
                                    Specified Motor Items
                                </h3>
                                <div class="row">
                                    <table class="table table-striped m-table">
                                        <tbody>
                                        <tr>
                                            <th>Description Of Items</th>
                                            <th>Sum Insured</th>
                                            <th>Actions</th>
                                        </tr>
                                        <tr class="fieldGroup">
                                            <td>
                                                <select class="form-control kt_selectpicker" name="item_name[]" title="Please choose Items" data-live-search="true" data-dropup-auto="false" data-size="5">
                                                    @foreach($motor_items as $motor_item)
                                                        <option value="{{ $motor_item->n_PRAppsSuppPersprop_PK }}">{{ $motor_item->s_PersPropScreenName }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td><input type='text' class="form-control" name="item_value[]" /></td>
                                            <td><a class="btn btn-primary addMore" style="color:#fff;">Add</a> </td>
                                        </tr>
                                        <!-- copy of input fields group -->
                                        <tr class="fieldGroupCopy" style="display: none;">
                                            <td>
                                                <select class="form-control motorItems" name="item_name[]" title="Please choose Items" data-live-search="true" data-dropup-auto="false" data-size="5">
                                                    @foreach($motor_items as $motor_item)
                                                        <option value="{{ $motor_item->n_PRAppsSuppPersprop_PK }}">{{ $motor_item->s_PersPropScreenName }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td><input type='text' class="form-control" name="item_value[]" /></td>
                                            <td><a class="btn btn-danger remove" style="color:#fff;">Remove</a> </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="members_section" style="display: none;">
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                            <button type="button" id="addMember" class="btn btn-primary">Add Sub Applicant</button>
                        </div>

                        <div class="row" id="addCellphoneDiv" style="display: none;">
                            <div class="kt-separator kt-separator&#45;&#45;space-sm kt-separator&#45;&#45;border-dashed"></div>
                            <div class="col-lg-12">
                                <h3 class="kt-heading kt-heading&#45;&#45;md">
                                    6. Cellphone Details :
                                </h3>
                                <div class="kt-repeater">
                                    <div data-repeater-list="policycellphone">
                                        <div data-repeater-item class="kt-repeater__item">
                                            <div class="row cellphonesection" id="cellphonesection">
                                                <div class="form-group col-lg-6">
                                                    <label>Device Type <span class="red-star">*</span></label>
                                                    <select class="form-control deviceType" name="device_type" >
                                                        <option selected="" disabled="">Please select device type</option>
                                                        <option value="cellphone">Cellphone</option>
                                                        <option value="tablet">Tablet</option>
                                                        <option value="laptop">Laptop</option>
                                                    </select>
                                                </div>
                                                <div class="form-group col-lg-6">
                                                    <label class="imei">IMEI</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control cellPhoneImei imei" placeholder="Please enter IMEI"  name="imei">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div id="cellPhoneMakeField" class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="device">Device Make : </label>
                                                        <select class="form-control cell_phone_make" name="cell_phone_make">
                                                            <option selected="" disabled="">Please select device make</option>
                                                            <option value="Other">Other</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group make_other" style="display:none;">
                                                        <label>Other Make<span class="red-star">*</span> <i class="icon-info-circle" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="Select other make" ."="" data-original-title="" title="" aria-describedby="popover509230" style="font-size: 18px;width: 14px;color: #fe7f0c;vertical-align:bottom;height:24px;"></i>
                                                        </label>
                                                        <input type="text" class="form-control" name="other_make" autocomplete="off" value="" placeholder="Please select Other Make" >
                                                    </div>
                                                </div>
                                                <div id="cellPhoneModelField" class="col-lg-6">
                                                    <div class="form-group">
                                                        <label class="model">Device Model : </label>
                                                        <select class="form-control cell_phone_model" name="cell_phone_model" title="Please select Cellphone Model">
                                                            <option selected="" disabled="">Please select Device Model</option>
                                                            <option value="Other">Other</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group model_other" style="display:none;">
                                                        <label>Other Model<span class="red-star">*</span> <i class="icon-info-circle" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="Select other make" ."="" data-original-title="" title="" aria-describedby="popover509230" style="font-size: 18px;width: 14px;color: #fe7f0c;vertical-align:bottom;height:24px;"></i></label>
                                                        <input type="text" class="form-control" name="other_model" autocomplete="off" value="" placeholder="Please enter Value of the phone" >
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="form-group col-6">
                                                    <label>Value Of The Phone <span class="red-star">*</span> <i class="icon-info-circle" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="" data-original-title="" title="" aria-describedby="popover509230" style="font-size: 18px;width: 14px;color: #fe7f0c;vertical-align:bottom;height:24px;"></i></label>
                                                    <input type="number" class="form-control" name="phone_value" autocomplete="off" value="" placeholder="Please enter Value of the phone" required>
                                                </div>
                                            </div>
                                            <h3 class="kt-heading kt-heading&#45;&#45;md">
                                                Cellphone Pre-inspection Photos
                                            </h3>
                                            <div class="form-group row">
                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Cellphone Front<span id="leftError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                                    <div class="kt-avatar" id="left" style="float: left; clear: left;">
                                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                            <i class="fa fa-pen"></i>
                                                            <input type='file' class="left" name="cell_phone_front" id="cell_phone_front" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                                        </label>
                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Cellphone Back<span id="rightError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                                    <div class="kt-avatar" id="right" style="float: left; clear: left;">
                                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                            <i class="fa fa-pen"></i>
                                                            <input type='file' class="right" name="cell_phone_back" id="cell_phone_back" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                                        </label>
                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Cellphone Left<span id="rightError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                                    <div class="kt-avatar" id="right" style="float: left; clear: left;">
                                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                            <i class="fa fa-pen"></i>
                                                            <input type='file' class="right" name="cell_phone_left" id="cell_phone_left" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                                        </label>
                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Cellphone Right<span id="rightError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                                    <div class="kt-avatar" id="right" style="float: left; clear: left;">
                                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                            <i class="fa fa-pen"></i>
                                                            <input type='file' class="right" name="cell_phone_right" id="cell_phone_right" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                                        </label>
                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Cellphone Top<span id="rightError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                                    <div class="kt-avatar" id="right" style="float: left; clear: left;">
                                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                            <i class="fa fa-pen"></i>
                                                            <input type='file' class="right" name="cell_phone_top" id="cell_phone_top" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                                        </label>
                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <h3 class="col-form-label" style="float: left;">Cellphone Bottom<span id="rightError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                                    <div class="kt-avatar" id="right" style="float: left; clear: left;">
                                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                            <i class="fa fa-pen"></i>
                                                            <input type='file' class="right" name="cell_phone_bottom" id="cell_phone_bottom" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                                        </label>
                                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="kt-repeater__data form-group">
                                                <span data-repeater-delete="" class="btn btn-warning btn-sm"> <i class="la la-close"></i> Remove </span>
                                            </div>
                                            <div class="kt-separator kt-separator&#45;&#45;border-dashed"></div>
                                            <div class="kt-separator kt-separator&#45;&#45;height-sm"></div>
                                        </div>
                                    </div>
                                    <div class="kt-repeater__add-data">
                                        <span data-repeater-create="" class="btn btn-brand btn-sm" > <i class="la la-plus"></i>Add Another Device</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row" id="addMemberDiv" style="display: none;">
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                            <div class="col-lg-12">
                                <h3 class="kt-heading kt-heading--md">
                                    3. Add Sub Applicant:
                                </h3>
                                <div class="kt-repeater">
                                    <div data-repeater-list="members">
                                        <div data-repeater-item class="kt-repeater__item">
                                            <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin">
                                                Sub Applicant Info
                                            </h3>
                                            <div class="form-group">
                                                <label>Which family member would you like to add ?</label>
                                                <div class="col-lg-12">
                                                    <select class="form-control memberRelatives" name="relation" id="memberRelatives" >
                                                        <option value="">Please Choose...</option>
                                                        <option value="Spouse">Spouse</option>
                                                        <option value="Child">Child</option>
                                                        <option value="Parent">Parent</option>
                                                        <option value="Parent-in-law">Parent-in-law</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>First name</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" placeholder="First name" name="memberFName">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>Last name</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" name="memberLName" placeholder="Last name">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>Date Of Birth</label>
                                                    <input type="text" class="form-control kt_datepicker_1" name="memberDOB" autocomplete="off" placeholder="Select date"/>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>Gender</label>
                                                    <div class="kt-radio-inline">
                                                        <label class="kt-radio">
                                                            <input type="radio" name="memberGender" value="1">
                                                            Male <span></span>
                                                        </label>
                                                        <label class="kt-radio">
                                                            <input type="radio" name="memberGender" value="0">
                                                            Female<span></span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="kt-repeater__data form-group">
                                                <span data-repeater-delete="" class="btn btn-warning btn-sm"> <i class="la la-close"></i> Remove </span>
                                            </div>
                                            <div class="kt-separator kt-separator--border-dashed"></div>
                                            <div class="kt-separator kt-separator--height-sm"></div>
                                        </div>
                                    </div>
                                    <div class="kt-repeater__add-data">
                                        <span data-repeater-create="" class="btn btn-brand btn-sm" > <i class="la la-plus"></i> Add Sub Applicant</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row" id="addBeneficiaryDiv" style="display: none;">
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                            <div class="col-lg-12">
                                <h3 class="kt-heading kt-heading--md">
                                    4. Add Beneficiaries:
                                </h3>
                                <div class="kt-repeater">
                                    <div data-repeater-list="beneficiaries">
                                        <div data-repeater-item class="kt-repeater__item">
                                            <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin">
                                                Beneficiary Info
                                            </h3>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>Which beneficiary would you like to add ?</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control beneficiaryRelation" placeholder="Please specify your relation with the beneficiary"  name="beneficiaryRelation">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>Omang</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control validateGroup1" placeholder="Omang" name="beneficiaryOmangName">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>Passport</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control validateGroup1" placeholder="Passport" name="beneficiaryPassName">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>First name</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control beneficiaryFName" placeholder="First name" name="beneficiaryFName">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>Last name</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control beneficiaryLName" name="beneficiaryLName" placeholder="Last name">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>Date Of Birth</label>
                                                    <input type="text" class="form-control kt_datepicker_1 beneficiaryDOB" name="beneficiaryDOB" autocomplete="off" placeholder="Select date"/>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>Gender</label>
                                                    <div class="kt-radio-inline">
                                                        <label class="kt-radio">
                                                            <input type="radio" name="beneficiaryGender" value="1" class="beneficiaryGender">
                                                            Male <span></span>
                                                        </label>
                                                        <label class="kt-radio">
                                                            <input type="radio" name="beneficiaryGender" value="0" class="beneficiaryGender">
                                                            Female<span></span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label>Payment(%)</label>
                                                    <div class="input-group">
                                                        <input type="number"  class="form-control beneficiaryPayment" name="beneficiaryPayment" placeholder="Payment">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="kt-repeater__data form-group">
                                                <span data-repeater-delete="" class="btn btn-warning btn-sm"> <i class="la la-close"></i> Remove </span>
                                            </div>
                                            <div class="kt-separator kt-separator--border-dashed"></div>
                                            <div class="kt-separator kt-separator--height-sm"></div>
                                        </div>
                                    </div>
                                    <div class="kt-repeater__add-data">
                                        <span data-repeater-create="" class="btn btn-brand btn-sm" > <i class="la la-plus"></i> Add Beneficiary</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="form-group">
                                    <h3 class="kt-heading kt-heading--md">
                                        Note
                                    </h3>
                                    <textarea class="form-control" name="note" placeholder="Add Note" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        <div id="customerKYCDiv" style="display:none;">
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                            <h3 class="kt-heading kt-heading--md">
                                Customer KYC
                            </h3>
                            <div class="form-group row">
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                    <div class="kt-avatar" id="driving_license" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="driving_license" name="driving_license" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                    <div class="kt-avatar" id="omang_pic" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="omang" name="omang" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                    <div class="kt-avatar" id="proof_residence" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="proof_residence" name="proof_residence" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>

                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Proof Of Income</h3>
                                    <div class="kt-avatar" id="proof_income" style="float: left; clear: left;">

                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="proof_income" id="proof_income_id" name="proof_income" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>

                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <h3 class="col-form-label" style="float: left;">Passport</h3>
                                    <div class="kt-avatar" id="passport_pic" style="float: left; clear: left;">
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                            <i class="fa fa-pen"></i>
                                            <input type='file' class="passport" name="passport" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                        </label>
                                        <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <h3 class="kt-heading kt-heading--md">
                            5. Customer Bank Details:
                        </h3>
                        <div class="row">
                            <div id="billingMethodField" class="col-lg-6">
                                <div class="form-group">
                                    <label for="exampleSelect1">Billing Method:</label>
                                    <select id="billing" class="form-control kt_selectpicker" title="Please select customers preferred billing method" name="billingMethod" id="exampleSelect1" >

                                    </select>
                                </div>
                            </div>
                            <div id="billingStartDateField" class="col-lg-6">
                                <div class="form-group">
                                    <label for="exampleSelect1">Billing Start Date:</label>
                                    <input id="billingStartDate" type="text" class="form-control kt_datepicker_1 beneficiaryDOB" name="billingStartDate" autocomplete="off" placeholder="Select date"/>
                                </div>
                            </div>

                        </div>
                        <div class="row">
                            <div id="bankNameField" class="col-lg-6">
                                <div class="form-group">
                                    <label for="exampleSelect1">Bank Name:</label>

                                    <div id="bankNameSpinner" style="display:contents;" class="form-group kt-spinner kt-spinner--sm kt-spinner--success kt-spinner--left kt-spinner--input"></div>
                                    <select id="bankNumDropDown" class="form-control kt_selectpicker" title="Please select customers bank" name="bankName" >
                                        @foreach ($banks as $banks)

                                            <option value="{{$banks->bank_number}}">{{$banks->bank_name}} </option>

                                        @endforeach

                                    </select>
                                </div>
                            </div>

                            <div id="bankBranchField" class="col-lg-6">
                                <div class="form-group">
                                    <label>Branch Code:</label>
                                    <style>
                                        .kt-spinner.kt-spinner--input.kt-spinner--left::before {
                                            z-index:9999;
                                            margin-left: 24px;
                                        }
                                    </style>
                                    <div id="bankBranchSpinner" style="display:contents;" class="form-group kt-spinner kt-spinner--sm kt-spinner--success kt-spinner--left kt-spinner--input"></div>
                                    <select id="bankBranchDropDown" class="form-control kt_selectpicker" title="Please select customers branch" name="branchCode"></select>
                                </div>
                            </div>
                            <div id="accountNumberField" class="col-lg-6">
                                <div class="form-group">
                                    <label>Account Number:</label>
                                    <input id="accountNumber" type="text" class="form-control" name="accountNumber" title="Please enter account number" value="{{old('accountNumber')}}" aria-describedby="emailHelp" placeholder="Account Number" pattern="[0-9]{1,15}"  maxlength="15">
                                </div>
                            </div>

                            <input id="billingOptionHidden" type="hidden" class="form-control" name="billingOption">

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
                        </div>
                        <div class="row">

                            <div id="billingCellField" class="col-lg-6">
                                <div class="form-group">
                                    <label>Myzaka/Orange Money Cell:</label>
                                    <input id="billingCell" type="text" class="form-control" name="billingCell" aria-describedby="emailHelp" title="Billing number should only have 8 numbers" placeholder="Myzaka/Orange Cell" value="{{old('billingCell')}}" minlength="8" maxlength="8" pattern="[0-9]{8}">
                                </div>

                            </div>
                        </div>

                        <div class="kt-portlet__foot">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" value="Submit" class="btn btn-brand" data-toggle="tooltip" data-placement="top" title="Create Policy">Submit</button>
                                        <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}" >Cancel</a>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                            {{-- <button type="button" id="saveLead" class="btn btn-success" data-toggle="tooltip" data-placement="top" title="Basic Customer Information Required">Save as Lead</button>
                             <button type="button" id="saveQuote" class="btn btn-warning" data-toggle="tooltip" data-placement="top" title="Basic Customer Information and Product Required">Save as Quote</button>--}}
                        </div>
                    </form>
                    <!--end::Form-->
                </div>
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



<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->
<div class="modal fade" id="purposeModal" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Transport Policy</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
            </div>
            <div class="modal-body">
                <h6>We are not providing Transport Vehicle Policy for Now. Sorry, fot the In-convenience caused.</h6>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
    <input type="hidden" value="0" id="triggered">
</div>

@include('admin.layouts.scripts')

<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/layouts/repeater.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}" type="text/javascript"></script>

<script>
    $("#purpose").change(function(){
        var selectedPurpose = $(this).children("option:selected").val();
        if(selectedPurpose == 29){
            $('#purposeModal').modal('show');
            $("#purpose").val([]);
        }
        else{
            $('#purposeModal').modal('hide');
        }
    });
    function imageValidator(){
        document.getElementById("proof_income_id").value;
    }
    /*function statusMsg(){
     var isChecked=document.getElementById("switchValue").checked;
     if (isChecked){
     document.getElementById("switchMsg").innerHTML="NEW";
     document.getElementById("switchMsg").style.color="cornflowerblue";
     }
     else {
     document.getElementById("switchMsg").innerHTML="EXITSING";
     document.getElementById("switchMsg").style.color="#ff4d4d";
     }
     $('#customers').toggle();
     }*/
    "use strict";
    // Class definition
    var KTFormControls = function () {
// Private functions
        jQuery.validator.addMethod("future", function(value, element) {
            return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
        }, "Please enter only past dates");
        //Vehicle Registration
        jQuery.validator.addMethod("license", function(value, element) {
            return this.optional( element ) || /^[Bb]{1}\d{3}[a-zA-Z]{3}$/.test( value );
        }, 'Sorry, We only accept Botswana registered vehicles Eg: B123ABC');
        jQuery.validator.addMethod(
            "sum",
            function (value, element, params) {
                var sumOfVals = 0;
                $('#addBeneficiaryDiv .beneficiaryPayment').each(function() {
                    sumOfVals += Number($(this).val());
                });
                if (sumOfVals <= params)
                    return true;
                return false;
            },
            'Total of Payments for all Beneficiaries cannot be more than 100'
        );
        var demo1 = function () {
            $( "#policyForm" ).validate({
                ignore: [],
// define validation rules
                rules: {
                    fname: {
                        required: true
                    },
                    lname: {
                        required: true
                    },
                    gender: {
                        required: true
                    },
                    cellphone: {
                        required: true
                    },
                    address: {
                        required: true
                    },
                    passport: {
                        required: /* '#omang:blank', */ function () {
                            return $('input[name="omang"]').val().length == 0;
                        }
                    },
                    omang: {
                        required: /* '#passport:blank', */ function () {
                            return $('input[name="passport"]').val().length == 0;
                        }
                    },
                    dob: {
                        required: true,
                        future: true,
                    },
                    /*billingMethod:{
                        required:true
                    },*/
                    billingStartDate :{
                        required:{
                            depends: function(element) {
                                return ($('#billing').val() == 'VCS');
                            },
                        }
                    },
                    bankName :{
                        required:{
                            depends: function(element) {
                                return ($('#billing').val() == 'RealPay');
                            },
                        }
                    },
                    branchCode:{
                        required:{
                            depends: function(element) {
                                return ($('#billing').val() == 'RealPay');
                            },
                        }
                    },
                    accountNumber:{
                        required:{
                            depends: function(element) {
                                return ($('#billing').val() == 'RealPay');
                            },
                        },
                        number: true
                    },
                    bankAccountType:{
                        required:{
                            depends: function(element) {
                                return ($('#billing').val() == 'RealPay');
                            },
                        }
                    }
                },
                groups: {
                    validateGroup1: "omang passport"
                },
                messages: {
                    fname: "Please enter your First Name",
                    lname: "Please enter your Last Name",
                    gender: "Please select Gender",
                    cellphone: "Please enter your Cell phone number",
                    //  email: "Please enter your email",
                    address: "Please enter your address",
                    omang: {
                        require_from_group: "Please provide your Omang Id",
                        max: "Your Omang Id can be max 9 characters long",
                        maxlength: "Your Omang Id can be max 9 characters long"
                    },
                    passport: {
                        require_from_group: "Please provide your Omang Id",
                        max: "Your Passport can be max 12 characters long"
                    },
                },
                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    $('html, body').animate({
                        scrollTop: $(validator.errorList[0].element).offset().top - 200
                    }, 1000);
                },
                submitHandler: function (form) {
                    form.submit(); // submit the form
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
    /*$('#left1').on('change', function () {
     $('#leftError').css('display', 'none');
     });
     $('#right1').on('change', function () {
     $('#rightError').css('display', 'none');
     });
     $('#front1').on('change', function () {
     $('#frontError').css('display', 'none');
     });
     $('#back1').on('change', function () {
     $('#backError').css('display', 'none');
     })
     $('#registration1').on('change', function () {
     $('#registrationError').css('display', 'none');
     });*/
    jQuery(document).ready(function() {
        KTFormControls.init();
        //group add limit
        var maxGroup = 10;
        //add more fields group
        $(".addMore").click(function(){
            var length = $('body').find('.fieldGroup').length;
            if(length < maxGroup){
                var fieldHTML = '<tr class="fieldGroup">'+$(".fieldGroupCopy").html()+'</tr>';
                $('body').find('.fieldGroup:last').before(fieldHTML);
                //$(".motorItems").selectpicker('refresh');
            }else{
                alert('Maximum '+maxGroup+' groups are allowed.');
            }
        });
        //remove fields group
        $("body").on("click",".remove",function(){
            $(this).parents(".fieldGroup").remove();
        });
    });
    $("#factor_main").on('click', '#calculate', function(){
        var selectOption = ($("[name^='factor_']").find(":selected").val());
        var CheckRoadioArray = $("[name^='factor_']:checked, [name^='factor_']:selected").map(function() {
            return $(this).val();
        }).get();
        var InputArray = $("input[type='text'][name^='factor_']").map(function() {
            return $(this).attr('name')+'_'+$(this).val();
        }).get();
        $("#policyForm").validate().settings.ignore = ":input:not([name^='factor_'])";
        if($("#policyForm").valid())
        {
            $.ajax({
                url: '{{ route('admin.policy.calculatePremium') }}',
                data: {
                    "_token": "{{ csrf_token() }}",
                    "product_id": $('#product').val(),
                    "factors": CheckRoadioArray+','+selectOption,
                    "input": InputArray,
                },
                type: 'post',
                datatype : 'json',
                success: function(data) {
                    var append = '';
                    $('#premiumDiv').empty();
                    if(data.response == 1){
                        $('<h3>Your Calculated Premium is : '+data.premium+' P (Incl. VAT)</h3><input type="hidden" name="premium" value="'+data.premium+'">').appendTo('#premiumDiv');
                    }else{
                        var url = '{{ route("admin.product.formula",":id") }}';
                        url = url.replace(':id', $.trim($('#product').val()));
                        $('<h4>Formula is not defined. You can set the formula using <a href="'+url+'" title="Formula" target="_blank">THIS LINK</a></h4>').appendTo('#premiumDiv');
                    }
                },
            });
            $("#policyForm").validate().settings.ignore = [];
            KTFormControls.init();
        }
    });
    $("#generate_code").on('click', function(){
        $.ajax({
            url: '{{ route('admin.policy.generateActivationCode') }}',
            data: {
                "_token": "{{ csrf_token() }}",
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {
                var append = '';
                $('#codeDiv').empty();
                if(data.status == 'success'){
                    $('<p id="activation_code_msg" style="font-size: 20px; font-weight:bolder; margin-left:10%;">  Activation Code Generated : '+data.code+' </p><input type="hidden"  name="activation_code" value="'+data.code+'">').appendTo('#codeDiv');
                } else {
                    $('<p>Activation Code Generated Not Generated store Activation Code</p>').appendTo('#codeDiv');
                }
            },
        });
    });
    $('#product').on('change', function (e, data) {
        var omang = $('#omang').val();
        var passport = $('#passport').val();
        var product_id = this.value;
        // cellphone product_id == 5
        if (product_id == 5) {
            $('#addCellphoneDiv').show();
        }
        else {
            $('#addCellphoneDiv').hide();
        }
        //end cellphone product
        if(data != null)
            var selected_plan_id = data.plan_id;
        var selected = '';
        $.ajax({
            url: '{{ route('admin.policy.product_factors') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": product_id,
                "passport": passport,
                "omang": omang
            },
            type: 'post',
            datatype: 'json',
            success: function (data) {
                var append = '';
                var coverage = '';
                if (data) {
                    if (data.status === 'existing') {
                        $('#existingProduct').css('display','block');
                    } else if (data.status === 'noCustomer') {
                        $('#existingProduct').text('Please enter Omang or Passport of Customer to Select Product')
                        $('#existingProduct').css('display','block');
                    } else {
                        $('#existingProduct').css('display','none');
                        data.factors.forEach(function ($factor) {
                            append += '<div class="col-lg-6">' +
                                '<div class="form-group">' +
                                '<label>' + $factor.name + '</label>';
                            if ($factor.type == 'Select') {
                                append += '<select class="form-control kt_selectpicker" name="factor_' + $factor.id + '" data-live-search="true" title="Please choose factor">';
                                append += '<option value="0">Select Option</option>';
                                if ($factor.value.length > 0) {
                                    $factor.value.forEach(function ($value) {
                                        append += '<option value="' + $value.id + '">' + $value.name + '</option>';
                                    });
                                }
                                append += '</select></div></div>';
                            } else if ($factor.type == 'Radio') {
                                append += '<div class="kt-radio-inline">';
                                if ($factor.value.length > 0) {
                                    $factor.value.forEach(function ($value) {
                                        append += '<label class="kt-radio"><input type="radio" name="factor_' + $factor.id + '[]" value="' + $value.id + '">' + $value.name + ' <span></span> </label>';
                                    });
                                }
                                append += '</div></div></div>';
                            } else if ($factor.type == 'Checkbox') {
                                append += '<div class="kt-checkbox-inline">';
                                if ($factor.value.length > 0) {
                                    $factor.value.forEach(function ($value) {
                                        append += '<label class="kt-checkbox"><input type="checkbox" name="factor_' + $factor.id + '[]" value="' + $value.id + '">' + $value.name + ' <span></span> </label>';
                                    });
                                }
                                append += '</div></div></div>';
                            } else if ($factor.type == 'Input Field') {
                                append += '<input type="text" class="form-control" id="' + $factor.name + '" name="factor_' + $factor.id + '"';
                                if ($factor.name.indexOf('age') != -1 && $('#dob').val() != '') {
                                    var dob = new Date($('#dob').val());
                                    var today = new Date();
                                    var age = Math.floor((today - dob) / (365.25 * 24 * 60 * 60 * 1000));
                                    append += 'value="' + age + '"';
                                }
                                append += '></div></div>';
                            }
                        });
                        $('#factor_main').empty();

                        /*15 is the id of dynamic  preminum_type  which is coming from lookup data table*/
                        if (data.product.premium_type_id === 15) {

                            append += '<div class="col-lg-12">' +
                                '<button type="button" id="calculate" class="btn btn-primary col-lg-2" style="float: left; clear: left;">Calculate Premium</button>' +
                                ' ' +
                                '<div class="col-lg-10" id="premiumDiv" style="margin: 0.5% 0 0 20%;">' +
                                '</div></div>';
                            append += '<div class="col-lg-8" style="margin-top: 20px;">';
                            if (data.product.sum_insured != null)
                                append += '<h5>Sum Assured/Insured : P ' + data.product.sum_insured + '</h5>';
                            else
                                append += '<h5>Sum Assured/Insured Limit not set for this product.</h5>';
                            append += '<input type="hidden" class="form-control" name="sum_assured" value="' + data.product.sum_insured + '" placeholder="Please enter Sum Assured/Insured">' +
                                '</div>';
                        }
                        /* 11 is the id of manual preminum_type  which is coming from lookup data table */
                        else if (data.product.premium_type_id === 11) {
                            append += '<div class="col-lg-6">' +
                                '<label>Product Plans</label>';
                            append += '<select class="form-control plan" name="plan" id="plan" title="Please choose Plan">';
                            append += '<option value="0">Select Plan</option>';
                            if (data.plans.length > 0) {
                                data.plans.forEach(function ($value) {
                                    if(selected_plan_id == $value.id){
                                        selected = 'selected';
                                    }else{
                                        selected = '';
                                    }
                                    append += '<option value="' + $value.id + '" '+ selected + ' content="' + $value.sum_assured + '" >' + $value.name + ' | Premium : ' + $value.premium + ' | Sum Assured : ' + $value.sum_assured + '</option>';
                                });
                            }
                            append += '</select></div></div>';
                        }
                        $('#factor_main').html(append);
                        jQuery.validator.addMethod(
                            "notEqualTo",
                            function (elementValue, element, param) {
                                return elementValue != param;
                            },
                            "Value cannot be {0}"
                        );
                        // adding rules for inputs with class 'comment'
                        $("[name^='factor_']").each(function () {
                            $(this).rules("add",
                                {
                                    required: true,
                                    notEqualTo: 0,
                                    messages: {
                                        notEqualTo: "Please select value",
                                    }
                                })
                        });
                        if (data.product.premium_type_id === 11) {
                            $('.plan').rules('add', {required: true, messages: {required: "Please Select Plan"}});
                            $('.plan').rules('add', {notEqualTo: 0, messages: {required: "Please Select Plan"}});
                        }
                        if (data.product.has_vehicle == 1) {
                            $('#vehicle_section').show();
                            $('.vehiclePlate').rules('add', {
                                required: true,
                                license: true,
                                messages: {required: "Please enter Vehicle No."}
                            });
                            /*             $('.chassisNo').rules('add', {
                                            required: true,
                                            messages: {required: "Please enter Chassis No."}
                                        });
                                        $('.odometer').rules('add', {required: true, messages: {required: "Please enter Odometer"}});
                                        $('#purpose').rules('add', {required: true, messages: {required: "Please select Purpose"}});
                                        $('.condition').rules('add', {required: true, messages: {required: "Please select condition"}});
                                        $('.vehicleDate').rules('add', {
                                            required: true,
                                            messages: {required: "Please enter Vehicle purchase date"}
                                        });
                                        $('.make').rules('add', {required: true, messages: {required: "Please enter Make"}});
                                        $('.model').rules('add', {required: true, messages: {required: "Please enter Model"}}); */
                        } else {
                            $('#vehicle_section').hide();
                            $('.vehiclePlate').rules('remove', 'required');
                            $('.chassisNo').rules('remove', 'required');
                            $('.odometer').rules('remove', 'required');
                            $('#purpose').rules('remove', 'required');
                            $('.condition').rules('remove', 'required');
                            $('.vehicleDate').rules('remove', 'required');
                            $('.make').rules('remove', 'required');
                            $('.model').rules('remove', 'required');
                            $('.left').rules('remove', 'required');
                            $('.right').rules('remove', 'required');
                            $('.front').rules('remove', 'required');
                            $('.back').rules('remove', 'required');
                            $('.vehicleRegistration').rules('remove', 'required');
                        }
                        if (data.product.is_motor_items == 1) {
                            $('#motors').show();
                        } else {
                            $('#motors').hide();
                        }
                        //Coverage
                        coverage += '<tr>' +
                            '<th>Main</th>' +
                            '<th>Coverage Value</th>' +
                            '<th>Disc/Surcharge</th>' +
                            '<th>Flat/%</th>' +
                            '<th>Discount Value</th>' +
                            '</tr>';
                        data.coverage.forEach(function ($cover) {
                            coverage += '<tr>' +
                                '<td><input type="hidden" name="main[]" value="' + $cover.name + '">' + $cover.name + '</td>' +
                                '<td><input type="number"  class="cover_value" name="cover_value[]" min="0"></td>' +
                                '<td><select name="type[]" required><option value="0">Select Discount/Surcharge</option><option value="1">Discount</option><option value="2">Surcharge</option></select></td>' +
                                '<td><select name="disccount_type[]" required><option value="0">Select Flat/%</option><option value="1">Flat</option><option value="2">%</option></select></td>' +
                                '<td><input type="number" name="type_value[]" min="0" value="' + $cover.name + '"></td>' +
                                '</tr>';
                        });
                        $('#coverageDiv').empty();
                        $('#coverageDiv').append(coverage);
                        $("[name^='cover_value']").each(function () {
                            $(this).rules("add",
                                {
                                    //required: true,
                                    messages: {
                                        notEqualTo: "Please select value",
                                    }
                                })
                        });
                        if (data.product.has_member == 1) {
                            $('#addBeneficiaryDiv').show();
                            jQuery.validator.addMethod("future", function(value, element) {
                                return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
                            }, "Please enter only past dates");
                            $('.beneficiaryRelation').rules('add', {required: true, messages: {required: "Please enter relationship"}});
                            $('.beneficiaryFName').rules('add', {required: true, messages: {required: "Please enter first name"}});
                            $('.beneficiaryLName').rules('add', {required: true, messages: {required: "Please enter last name"}});
                            //  $('.beneficiaryDOB').rules('add', {required: true, future:true});
                            $('.beneficiaryGender').rules('add', {required: true, messages: {required: "Please select gender"}});
                            $(".beneficiaryPayment").rules('add', {sum: 100,required: true, messages: {required: "Please select payment"}} );
                        } else{
                            $('#addBeneficiaryDiv').hide();
                            $('.beneficiaryRelation').rules('remove', 'required');
                            $('.beneficiaryFName').rules('remove', 'required');
                            $('.beneficiaryLName').rules('remove', 'required');
                            $('.beneficiaryDOB').rules('remove', 'required');
                            $('.beneficiaryGender').rules('remove', 'required');
                            $('.beneficiaryPayment').rules('remove', 'required');
                        }
                        if (data.product.kyc_customer == 1) {
                            $('#customerKYCDiv').show();
                        } else {
                            $('#customerKYCDiv').hide();
                        }
                        if (data.product.has_subApplicant != 0) {
                            $('.members_section').show();
                        } else {
                            $('.members_section').hide();
                        }
                        KTFormControls.init();
                    }
                }
                else {
                    $('#factor_main').empty();
                    $('#factor_main').append("Nothing to Show");
                }
            },
        });
        $('.kt_selectpicker').selectpicker('render');
        $('.kt_selectpicker').selectpicker('refresh');
        //Set Product Plan from Activation Code Value
        //$(".plan").val(data.plan_id);
        //$('.plan option[value=' + data.plan_id + ']').attr('selected', 'selected');
    });
    "use strict";
    $('#addMember').click(function () {
        $('#addMemberDiv').toggle();
        $('#addMember').toggle();
    });
    $('#addBeneficiary').click(function () {
        $('#addBeneficiaryDiv').toggle();
        $('#addBeneficiary').toggle();
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
            $('#dob').datepicker({
                rtl      : KTUtil.isRTL(),
                templates: arrows,
                /* todayHighlight: true,
                minDate    : new Date(1900,1-1,1), */
                maxDate    : '-18Y',
                orientation: "bottom left",
                startDate  : '-110y',
                endDate    : '-18y',
                format     : "yyyy-mm-dd",
                changeMonth: true,
                changeYear : true,
                defaultDate: null,
                yearRange  : "-100:+0",
                autoclose  : true
            });
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
    //For Dynamic Fields
    $('.kt-repeater__add-data').on('click',".btn-brand", function(){
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
        $('.kt_datepicker_1').datepicker({
            rtl: KTUtil.isRTL(),
            todayHighlight: true,
            orientation: "bottom left",
            templates: arrows,
            format: 'yyyy-mm-dd'
        });
        KTBootstrapDatepicker.init();
    });
    // Avatar Class definition
    var KTAvatarDemo = function() {
        return {
            // Init demos
            init: function() {
                var avatar1 = new KTAvatar('left');
                var avatar2 = new KTAvatar('right');
                var avatar3 = new KTAvatar('back');
                var avatar4 = new KTAvatar('front');
                var avatar5 = new KTAvatar('vehicleRegistration');
                var avatar6 = new KTAvatar('driving_license');
                var avatar7 = new KTAvatar('omang_pic');
                var avatar8 = new KTAvatar('proof_residence');
                var avatar9 = new KTAvatar('proof_income');
                var avatar10 = new KTAvatar('passport_pic');
                var avatar11 = new KTAvatar('vehicle_valuation');
            }
        };
    }();
    // Class initialization on page load
    jQuery(document).ready(function() {
        KTAvatarDemo.init();
    });
    $(document).ready(function() {
        var ajaxRequest;
        $('#vehicle_number, #omang, #passport').keyup(function() {
            var value = $('#vehicle_number').val();
            var omang = $('#omang').val();
            var passport = $('#passport').val();
            if(value != '')
            {
                clearTimeout(ajaxRequest);
                ajaxRequest = setTimeout(function(sn) {
                    $.ajax({
                        url: '{{ route('admin.policy.checkUserVehicle') }}',
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "vehiclePlate": value,
                            "omang": omang,
                            "passport": passport
                        },
                        type: 'post',
                        datatype : 'json',
                        success: function (data) {
                            if(data.count > 0) {
                                $('#vehicleError').css('display','block');
                            } else {
                                $('#vehicleError').css('display','none');
                            }
                        }
                    });
                }, 500, value);
            }
        });
    });
    $(document).ready(function() {
        var ajaxRequest;
        var customerData;
        $('#omang, #passport').change(function() {
            var omang = $('#omang').val();
            var passport = $('#passport').val();
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.policy.checkUserOmang') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "omang": omang,
                        "passport": passport
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if( data.count != null) {
                            console.log(data.count.omang);
                            customerData = data;
                            $('#customerTableBody').empty();
                            var append = '<tr>' +
                                '<th>First Name</th>' +
                                '<td>'+data.customerData.firstName+'</td>' +
                                '</tr>' +
                                '<tr>' +
                                '<th>Last Name</th>' +
                                '<td>'+data.customerData.lastName+'</td>' +
                                '</tr>' +
                                '<tr>' +
                                '<th>Email</th>' +
                                '<td>'+data.customerData.email+'</td>' +
                                '</tr>' +
                                '<tr>' +
                                '<th>Omang ID</th>' +
                                '<td>'+data.count.omang+'</td>' +
                                '</tr>' +
                                '<tr>' +
                                '<th>Passport Number</th>' +
                                '<td>'+data.count.passport+'</td>' +
                                '</tr>' +
                                '<th>Cellphone Number</th>' +
                                '<td>'+data.customerData.cellphone+'</td>' +
                                '</tr>' +
                                '<tr>' +
                                '<th>Physical Address</th>' +
                                '<td>'+data.count.address+'</td>' +
                                '</tr>' +
                                '<tr>' +
                                '<th>Date Of Birth</th>' +
                                '<td>'+data.count.dob+'</td>' +
                                '</tr>';
                            $('#customerTableBody').append(append);
                            $('#customerDiv').css('display','block');
                            if(data.count.omang == omang)
                            {
                                $('.existingOmangError').css('display','block');
                            }
                            else if(data.count.passport == passport)
                            {
                                $('.existingPassportError').css('display','block');
                            }
                        }
                        else {
                            $('#customerDiv').css('display','none');
                            $('.existingOmangError').css('display','none');
                            $('.existingPassportError').css('display','none');
                        }
                    }
                });
            }, 200);
        });
        $("#select").on('click',function(){
            $(".firstName").val(customerData.customerData.firstName);
            $(".lastName").val(customerData.customerData.lastName);
            $(".email").val(customerData.customerData.email);
            $(".dob").val(customerData.count.dob);
            var gender = customerData.count.gender;
            if(gender == 0)
            {
                $("#female").attr('checked', 'checked');
            }
            else{
                $("#male").attr('checked', 'checked');
            }
            $(".address").val(customerData.count.address);
            $(".cellphone").val(customerData.customerData.cellphone);
            $("#passport").val(customerData.count.passport);
            $('.existingOmangError').css('display','none');
            $('.existingPassportError').css('display','none');
            $('#customerDiv').css('display','none');
        });
    });
</script>

<script>
    $(document).ready(function() {
        var ajaxRequest;
        $('#make').change(function() {
            var make = $('#make').val();
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.policy.checkMakeModel') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "make": make,
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if (data) {
                            $('#model').empty();
                            $.each(data.count, function(key, modal){
                                $('#model').append('<option value="' + modal + '">' + modal + '</option>');
                            });
                        } else {
                            $('#model').empty();
                        }
                        $("#model").selectpicker('refresh');
                    }
                });
            }, 200);
        });
    });
</script>

<script>
    $(document).ready(function() {
        $("#bankBranchSpinner").css("display", "none");
        $("#bankNameSpinner").css("display", "none");
        $.ajax({
            url: "{{route('admin.paymentvendor.list')}}",
            type: 'GET',
            success: function (data) {
                if (data) {
                    $.each(data, function(key, value){
                        $('#billing').append('<option value="' + value.vendorName + '">' + value.vendorLabel + '</option>');
                        $("#billing").selectpicker('refresh');
                    });
                } else {
                    $('#billing').empty();
                }
            }
        });
        $.ajax({
            type: 'post',
            url: '{{route('getMotorItems')}}',
            data: { "_token": "{{ csrf_token() }}"},
            dataType: 'JSON',
            success: function (response) {
                //console.log(response);
                if(response){
                    $.each(response, function(key, value){
                        //append to option
                    });
                }
            },
            error: function(e){
                console.log(e.responseJSON)
            }
        });
    });
</script>

<script>
    $('#planPremium').on('change', function(){
        var premium = $('#planPremium').val();
        console.log(premium);
    });
    $('#billing').on('change',function(){
        console.log('billing changed!');
        var options = $('#billing').val();
        switch(options){
            case 'Orange':
                $("#billingOptionHidden").val(options);
                $('#bankNumDropDown').prop("hide",true);
                $('#branchDropdown').css("display", "none");
                $('#accountNumberField').css("display", "none");
                $('#accountTypeField').css("display", "none");
                $('#bankNameField').css("display", "none");
                $('#bankBranchField').css("display", "none");
                $('#billingCell').prop("required",true);
                $('#billingCell').prop("disabled",false);
                $("#bankNameSpinner").css("display", "none");
                $('#billingCellField').show();
                $('#branchDropdown').rules('remove',  'required');
                $('#bankNameDropdown').rules('remove',  'required');
                $('#accountNumberField').rules('remove',  'required');
                $("#billingMethodField").removeClass( "col-lg-12" ).addClass( "col-lg-6" );
                break;
            case 'VCS':
                $("#billingOptionHidden").val(options);
                $('#accountNumberField').hide();
                $('#bankNameField').hide();
                $('#bankBranchField').hide();
                $("#bankNameSpinner").css("display", "none");
                $('#billingCellField').hide();
                $('#accountTypeField').hide();
                $('#billingCell').rules('remove',  'required');
                $('#branchDropdown').rules('remove',  'required');
                $('#bankNameDropdown').rules('remove',  'required');
                $('#accountType').rules('remove',  'required');
                $('#accountNumber').rules('remove',  'required');
                $("#billingMethodField").removeClass( "col-lg-6" ).addClass( "col-lg-12" );
                $("#billingStartDateField").removeClass( "col-lg-6" ).addClass( "col-lg-12" );
                break;
            case 'RealPay':
                $("#billingOptionHidden").val(options);
                $('#branchDropdown').show();
                $('#bankNameDropdown').show();
                $('#accountNumberField').show();
                $('#accountTypeField').show();
                $('#bankNameField').show();
                $('#bankBranchField').show();
                $('#billingCellField').hide();
                $('#branchDropdown').rules('add',  'required');
                $('#bankNameDropdown').rules('add',  'required');
                $('#accountType').rules('add',  'required');
                $('#accountNumber').rules('add',  'required');
                $('#billingCell').rules('remove',  'required');
                $("#billingMethodField").removeClass( "col-lg-6" ).addClass( "col-lg-12" );
                $("#billingStartDate").removeClass( "col-lg-6" ).addClass( "col-lg-12" );
                /* $('#accountNumber').prop("required",true);*/
                $('#accountType').prop("required",true);
                $('#bankBranchDropDown').prop("required",true);
                $('#bankNumDropDown').prop("required",true);
                $('#accountType').prop("required",true);
                $('#accountTypeDropdown').prop("disabled",true);
                break;
            default:
                console.log('default Reached');
                break;
        }
    });
    $("#bankNumDropDown").change(function(){
        var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
        const bankNum = $('#bankNumDropDown').val();
        $.ajax({
            /* the route pointing to the post function */
            url: '{{route('branches')}}',
            type: 'POST',
            /* send the csrf-token and the input to the controller */
            data: {
                _token: CSRF_TOKEN,
                bank_id:bankNum,
            },
            dataType: 'JSON',
            /* remind thactivation_code
""
at 'data' is the response of the AjaxController */
            success: function (data) {
                if (data) {
                    $('#bankBranchDropDown').empty();
                    $.each(data.branches, function(key, value){
                        $('#bankBranchDropDown').append('<option value="' + value.id + '">' + value.name + '</option>');
                        $('#bankBranchDropDown').selectpicker('refresh');
                    });
                    $("#carSpinner").css("display", "none");
                } else {
                    console.log('ERROR OCCURED');
                    $('#bankBranchDropDown').empty();
                }
            }
        });
    });
</script>

<script type="text/javascript">
    function restrictAlphabets(event) {
        var key = event.keyCode;
        return ((key >= 48 && key <= 57) || key == 8 || key>=35 && key<=40 || key==46);
    };
</script>

<script type="text/javascript">
    $("input[name=check_activation]:radio").on("change", function () {
        $Checkval = $(this).val();
        if ($Checkval == 'Yes') {
            $('#input_activation').css('display', 'block');
            $('#generate_code').css('display', 'none');
            $('#activation_code_msg').css('display', 'none');
            $('#activation_code').rules('add', {required: true, messages: {required: "Please enter activation code"}});
        }
        else {
            $('#input_activation').css('display', 'none');
            $('#generate_code').css('display', 'block');
            $('#activation_code').rules('remove', 'required');
        }
    });
    $("#activation_code").on("change", function () {
        $code = $(this).val();
        ajaxRequest = setTimeout(function (sn) {
            $.ajax({
                url: '{{ route('admin.policy.checkActivation') }}',
                data: {
                    "_token": "{{ csrf_token() }}",
                    "code": $code
                },
                type: 'post',
                datatype: 'json',
                success: function (data) {
                    if (data.count != null){
                        if(data.count.status == 1) {
                            $("#activation_code").val([]);
                            $('#activation_code').rules('add', {required: true, messages: {required: "Please enter activation code"}});
                            alert('Activation Code Already Used !');
                        }
                        $('#product').val(data.count.product_id);
                        $('#product').trigger("change", [{plan_id:data.count.product_plan_id}]);
                        $('#product').selectpicker('render');
                        $('#product').selectpicker('refresh');
                        $('.kt_selectpicker').selectpicker('render');
                        $('.kt_selectpicker').selectpicker('refresh');
                    }
                }
            });
        }, 100);
    });
</script>

<script>
    $(document).ready(function () {
        var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
        $('#saveLead').click(function (e) {
            e.preventDefault();
            var validator = $( "#policyForm" ).validate();
            validator.element('.firstName');
            validator.element('.lastName');
            validator.element('.cellphone');
            if(validator.valid()){
                console.log(validator);
                var firstName= $('.firstName').val();
                var lastName =$('.lastName').val();
                var email =$('.email').val();
                var cellphone =$('.cellphone').val();
                $.ajax({
                    type: 'POST',
                    url: '{{route('lead.save')}}',
                    data: {
                        _token: CSRF_TOKEN,
                        firstName: firstName,
                        lastName:lastName,
                        email:email,
                        cellphone:cellphone
                    },
                    dataType: 'JSON',
                    success: function (response) {
                        if(response){
                            $("#carSpinner").css("display", "none");
                            $("#success").css("display", "block");
                            $("html, body").animate({ scrollTop: 0 }, "slow");
                            // console.log(response);
                        }
                    },
                    error: function (error) {
                        console.log(error.responseText);
                        $("#error").css("display", "block");
                        $('.error-message').text(error.responseJSON.message);
                        $("html, body").animate({ scrollTop: 0 }, "slow");
                    }
                });
            }else{
                $("html, body").animate({ scrollTop: 0 }, "slow");
            }
        });
    });
</script>


<script>
    $(document).ready(function () {
        var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
        $('#saveQuote').click(function (e) {
            e.preventDefault();
            var validator = $( "#policyForm" ).validate();
            validator.element('.firstName');
            validator.element('.lastName');
            validator.element('.email');
            validator.element('.cellphone');
            validator.element('.address');
            validator.element('.gender');
            validator.element('#product');
            validator.element('#dob');
            validator.element('.validateGroup1');
            if(validator.valid()){
                var firstName= $('.firstName').val();
                var lastName =$('.lastName').val();
                var email =$('.email').val();
                var cellphone =$('.cellphone').val();
                var omang =$("#omang").val();
                var passport =$("#passport").val();
                var gender =$('.gender').val();
                var address =$('.address').val();
                var dob =$("#dob").val();
                var product =$("#product :selected").val();
                $.ajax({
                    type: 'POST',
                    url: '{{route('quote.save')}}',
                    data: {
                        _token: CSRF_TOKEN,
                        firstName: firstName,
                        lastName:lastName,
                        email:email,
                        cellphone:cellphone,
                        omang:omang,
                        passport:passport,
                        gender: gender,
                        address:address,
                        dob:dob,
                        product:product,
                    },
                    dataType: 'JSON',
                    success: function (response) {
                        if(response){
                            $("#success").css("display", "block");
                            $("html, body").animate({ scrollTop: 0 }, "slow");
                        }
                    },
                    error: function (error) {
                        console.log(error.responseJSON.message);
                        $("#error").css("display", "block");
                        $('.error-message').text('Something went wrong,please try again');
                        $("html, body").animate({ scrollTop: 0 }, "slow");
                    }
                });
            }else{
                $("html, body").animate({ scrollTop: 0 }, "slow");
            }
        });
    });
    function isNumberKey(evt)
    {
        var charCode = (evt.which) ? evt.which : evt.keyCode;
        if (charCode != 46 && charCode > 31
            && (charCode < 48 || charCode > 57))
            return false;
        return true;
    }
    $('#passport').keyup(function(e){
        var str = $(this).val();
        if(/^[a-zA-Z0-9- ]*$/.test(str) == false) {
            $('.invalidPassportError').show();
            e.preventDefault();
        }
    });
    $('#passport').keydown(function(){
        var str = $(this).val();
        if(/^[a-zA-Z0-9- ]*$/.test(str) == false) {
            $('.invalidPassportError').hide();
        }
    });
    $( "#policyForm" ).submit(function( event ) {
        var str = $('#passport').val();
        if(/^[a-zA-Z0-9- ]*$/.test(str) == false) {
            alert('hey its if');
            $('.invalidPassportError').show();
            return false;
        }
        else{
            $('.invalidPassportError').hide();
            return true;
        }
    });
    $('#product').on('change',function(){
        var productTypeId = $(this).children("option:selected").attr('content');
        ajaxRequest = setTimeout(function (sn) {
            $.ajax({
                url: '{{ route('admin.policy.checkProductType') }}',
                data: {
                    "_token": "{{ csrf_token() }}",
                    "productTypeId": productTypeId
                },
                type: 'post',
                datatype: 'json',
                success: function (data) {
                    if (data.isInstant == 'yes'){
                        $('#triggered').attr('value','1');
                    }else{
                        $('#triggered').attr('value','0');
                    }
                }
            });
        }, 100);
    });
    $('#factor_main').on('change', '.plan', function() {
        var isProductTypeInstant = $('#triggered').val();
        if(isProductTypeInstant == '1') {
            var sumAssured = $(this).children("option:selected").attr('content');
            $("input.cover_value").empty();
            $("input.cover_value").prop("readonly", true);
            $('input.cover_value').val(sumAssured);
        }
    });
</script>

</body>
<!-- end::Body -->
</html>
