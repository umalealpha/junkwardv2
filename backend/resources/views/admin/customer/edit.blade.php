<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datetime-picker/css/bootstrap-datetimepicker.css')}}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-timepicker/css/bootstrap-timepicker.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

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

{{-- @empty($customerProfile->sourceOfIncome) --}}
@if ($customerProfile->sourceOfIncome != null)
    @if (!empty($i))
        @php $m = "what_is_your_monthly_income_?"; if(isset($i->$m)){ $i->$m = $i->$m; }else{ $i->$m = '';} @endphp
        @php $m1 = "where_are_you_employed_?"; if(isset($i->$m1)){ $i->$m1 = $i->$m1; }else{ $i->$m1 = '';} @endphp
        @php $m2 = "what_is_your_monthly_salary_?"; if(isset($i->$m2)){ $i->$m2 = $i->$m2; }else{ $i->$m2 = '';} @endphp
        @php $m3 = "who_did_you_inherit_these_funds_from_?"; if(isset($i->$m3)){ $i->$m3 = $i->$m3; }else{ $i->$m3 = '';} @endphp
        @php $m4 = "who_gifted_you_these_funds_?"; if(isset($i->$m4)){ $i->$m4 = $i->$m4; }else{ $i->$m4 = '';} @endphp
        @php $m5 = "how_much_were_you_gifted_?"; if(isset($i->$m5)){ $i->$m5 = $i->$m5; }else{ $i->$m5 = '';} @endphp
        @php $m6 = "what_is_the_amount_of_funds_invested_?"; if(isset($i->$m6)){ $i->$m6 = $i->$m6; }else{ $i->$m6 = '';} @endphp
        @php $m7 = "how_much_do_you_earn_from_these_investments_per_month_?"; if(isset($i->$m7)){ $i->$m7 = $i->$m7; }else{ $i->$m7 = '';} @endphp
        @php $m8 = "name_of_your_bussiness"; if(isset($i->$m8)){ $i->$m8 = $i->$m8; }else{ $i->$m8 = '';} @endphp
        @php $m9 = "bussiness_address"; if(isset($i->$m9)){ $i->$m9 = $i->$m9; }else{ $i->$m9 = '';} @endphp
        @php $m10 = "how_much_is_your_monthly_pension_?"; if(isset($i->$m10)){ $i->$m = $i->$m10; }else{ $i->$m10 = '';} @endphp
    @else
        @php
            $i = null;
        @endphp
    @endif

@endif
{{-- @endempty --}}



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
                    Edit Customer
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{route('admin.customer.index')}}" class="kt-subheader__breadcrumbs-link"> Customer </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="customerEdit" action="{{ route('admin.customer.update', $customer->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">

                    <!-- CSRF Token -->
                    <input type="hidden" name="_method" value="PUT">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <input type="hidden" name="incomeSource" value="{{ $incomeSource }}" id="incomeSource"/>

                    <div class="kt-portlet__body">

                        <div class="form-group row">
                            <div class="col-3"><label for="example-text-input" class="form-label">Entity Type</label></div>
                            <div class="col-9">
                                <select name="entity_type" id="entity_type" class="form-control entity_type">
                                    <option value="" >-- Select Entity Type --</option>
                                    <option value="Organisation"  @if($customerProfile->entity_type !=null) @if($customerProfile->entity_type =='Organisation') {{'selected'}} @endif @endif>Organisation</option>
                                    <option value="Person"  @if($customerProfile->entity_type !=null) @if($customerProfile->entity_type =='Person') {{'selected'}} @endif @endif>Person</option>
                                </select>
                            </div>
                        </div>

                        <!-- organisation_div start -->
                        <div id="organisation_div">
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Organisation Name</label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="org_name" value="{{$customerProfile->org_name}}" id="org_name"  placeholder="Enter Organisation Name">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">VAT Registration Number</label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="vat_reg" value="{{$customerProfile->vat_reg}}"  placeholder="Enter VAT Registration Number">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Company Registration Number</label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="reg_no" value="{{$customerProfile->reg_no}}"  placeholder="Enter Company Registration Number">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Tax ID<span class="red-star">*</span></label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="tax_id" value="{{$customerProfile->tax_id}}"  placeholder="Enter Tax ID">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Organisation Phone Number <span class="red-star">*</span></label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="org_phone_no" value="{{$customerProfile->org_phone_no}}"  placeholder="Enter Organisation Phone Number">
                                </div>
                            </div>
                        </div>
                        <!-- organisation_div end -->

                       <!-- person_div start -->
                        <div id="person_div">

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">First Name</label>
                                <div class="col-9">
                                    @if($customer != NULL)
                                    <input type="text" class="form-control"  name="first_name" title="First name is required" value="{{$customer->firstName}}" placeholder="Enter first name">
                                    @else
                                    <input type="text" class="form-control"  name="first_name" title="First name is required" placeholder="Enter first name">
                                    @endif
                                </div>
                            </div>

                            <!-- Middle Name Column added -->
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Middle Name</label>
                                <div class="col-9">
                                    @if($customer != NULL)
                                    <input type="text" class="form-control"  name="middle_name" title="middle name is required" value="{{$customer->middleName}}" placeholder="Enter middle name">
                                    @else
                                    <input type="text" class="form-control"  name="middle_name" title="middle name is required" placeholder="Enter middle name">
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Last Name</label>
                                <div class="col-9">
                                @if($customer != NULL)
                                    <input type="text" class="form-control" name="last_name" title="Last name is required" value="{{$customer->lastName}}"  placeholder="Enter last name">
                                    @else
                                    <input type="text" class="form-control" name="last_name" title="Last name is required"  placeholder="Enter last name">
                                @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Gender</label>
                                @if($customer != NULL)
                                    <div class="col-9">
                                    <div class="kt-radio-inline">
                                        <label class="kt-radio">
                                            <input type="radio" name="gender" value="1" @if($customerProfile->gender == '1') checked @endif>Male <span></span>
                                        </label>
                                        <label class="kt-radio">
                                            <input type="radio" name="gender" value="0" @if($customerProfile->gender == '0') checked @endif>Female <span></span>
                                        </label>
                                    </div>
                                        </div>
                                @else
                                    <div class="col-9">
                                    <div class="kt-radio-inline">
                                        <label class="kt-radio">
                                            <input type="radio" name="gender" value="1">Male <span></span>
                                        </label>
                                        <label class="kt-radio">
                                            <input type="radio" name="gender" value="0">Female <span></span>
                                        </label>
                                    </div>
                                        </div>
                                @endif
                            </div>

                            <div class="form-group row">
                                <label for="example-date-input" class="col-3 col-form-label">Date of Birth</label>
                                <div class="col-9">
                                @if($customer && $customerProfile && $customerProfile->dob != null)
                                    <input type="text" class="form-control kt_datepicker_1"  name="dob" value="{!!  \Carbon\Carbon::parse($customerProfile->dob)->format('d-m-Y')  !!}"
                                        placeholder="Select date"
                                        @if ( \AlphaDirect\Policy::where("customer_id", $customer->id)
                                                                                                ->where('product_id', 1)
                                                                                                ->where('status', '!=', 2)
                                                                                                ->exists() 
                                                                                                && !\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('adi_customer_dob_change') ) disabled 
                                                                            @endif 
                                        />
                                @else
                                <input type="text" class="form-control kt_datepicker_1" name="dob"  placeholder="Select date"/>
                                @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-date-input" class="col-3 col-form-label">Marital Status</label>
                                <div class="col-9">
                                <select class="form-control  maritalstatus" name="maritalstatus" id="marital">
                                    <option value=""> ---- Select ---- </option>
                                    <option value="1" <?php echo (($customerProfile->maritalstatus)== 1 ?"selected":"") ?>>Single</option>
                                    <option value="2" <?php echo (($customerProfile->maritalstatus)== 2 ?"selected":"") ?>>Married</option>
                                    <option value="3" <?php echo (($customerProfile->maritalstatus)== 3 ?"selected":"") ?>>Divorced</option>
                                    <option value="4" <?php echo (($customerProfile->maritalstatus)== 4 ?"selected":"") ?>>Widowed</option>
                                    <option value="5" <?php echo (($customerProfile->maritalstatus)== 5 ?"selected":"") ?>>Living Together</option>
                                    <option value="6" <?php echo (($customerProfile->maritalstatus)== 6 ?"selected":"") ?>>Living Separately</option>
                                </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Omang</label>
                                <div class="col-9">
                                @if($customer != NULL)
                                    <input type="text" class="form-control" id="omang" name="omang" pattern="[0-9]{9}" maxlength="9"  value="{{$customerProfile->omang}}" placeholder="Enter Omang">
                                @else
                                <input type="text" class="form-control"  id="omang" name="omang" pattern="[0-9]{9}" maxlength="9" placeholder="Enter Omang">
                                @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Passport</label>
                                <div class="col-9">
                                    @if($customer != NULL)
                                        <input type="textarea" class="form-control passport" name="passport" id="passport" minlength="3" maxlength="20" value="{{$customerProfile->passport}}" placeholder="Enter passport number">
                                    @else
                                    <input type="textarea" class="form-control passport" name="passport"  id="passport" minlength="3" maxlength="20" placeholder="Enter passport number">
                                    @endif
                                </div>
                            </div>

                            <!-- passport country dropdown complete -->
                            <div class="form-group row">
                                <div class="col-3"><label for="example-text-input" class="form-label">Passport Issuing Country</label></div>
                                <div class="col-9">
                                    <select name="country"  class="form-control" >
                                        <option value="0" >-- Select Country --</option>
                                        @foreach($countries as $country)
                                            <option value="{{ $country->id }}" @if($customerProfile->countryId !=null) @if($customerProfile->countryId == $country->id) {{'selected'}} @endif @endif>{{$country->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row">
                            <div class="col-3">
                                <label for="example-text-input" class="form-label">Source of Income/Funds</label></div>
                                <div class="col-9">
                                    <select class="form-control" name="sourceOfIncome" id="sourceOfIncome">
                                        <option value="">Please select source of Income/Funds</option>
                                        <option value="unemployed" @if($incomeSource == "unemployed") selected @endif>Unemployed</option>
                                        <option value="employment" @if($incomeSource == "employment") selected @endif>Employment</option>
                                        <option value="pensioner_retired" @if($incomeSource == "pensioner_retired") selected @endif>Pensioner/Retired</option>
                                        <option value="bussiness" @if($incomeSource == "bussiness") selected @endif>Self-Employment/Business</option>
                                        <option value="inheritance" @if($incomeSource == "inheritance") selected @endif>Inheritance</option>
                                        <option value="gifts" @if($incomeSource == "gifts") selected @endif>Gifts</option>
                                        <option value="investments" @if($incomeSource == "investments") selected @endif>Investments</option>
                                    </select>
                                </div>
                            </div>
                            <div id="sourceOfIncomeDiv">
                            </div>

                        </div>
                        <!-- person_div end -->


                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Email</label>
                            <div class="col-9">
                                @if($customer != NULL)
                                  <input type="text" class="form-control" name="cutomer_email" id="cutomer_email"  value="{{$customer->email}}"placeholder="Enter email">
                                @else
                                  <input type="text" class="form-control" name="cutomer_email" id="cutomer_email" placeholder="Enter email">
                                @endif
                            </div>
                        </div>

                        <div class="form-group row">
                           <div class="col-3"><label for="example-text-input" class="form-label">Mobile No.</label></div>
                            <div class="col-9">
                            @if($customer != NULL)
                                <input type="text" class="form-control" name="mobile" value="{{$customer->cellphone}}" pattern="[0-9]{1,25}" maxlength="8"  placeholder="Enter mobile no.">
                            @else
                            <input type="text" class="form-control" name="mobile" pattern="[0-9]{1,25}" maxlength="8"  placeholder="Enter mobile no.">
                            @endif
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Address</label>
                            <div class="col-9">
                            @if($customer != NULL)
                                <input type="textarea" class="form-control" name="address" value="{{$customerProfile->address}}" placeholder="Enter address">
                            @else
                                <input type="textarea" class="form-control" name="address" placeholder="Enter address">
                            @endif
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">District</label>
                           <div class="col-9">
                                <select class="form-control required" name="state" id="state">
                                    @foreach ($states as $state)
                                        <option value="{{$state->id}}" {{ $customerProfile->state == $state->id ? 'selected' : '' }}>{{ $state->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">City</label>
                            <div class="col-9">
                                <select class="form-control required" name="city" id="city">

                                @isset($cities)
                                    @foreach ($cities as $city)
                                    <option value="{{$city->name}}" {{ $customerProfile && $customerProfile->city == $city->name ? 'selected' : '' }}>{{ $city->name }}</option>
                                    @endforeach
                                @endisset

                                </select>
                            </div>
                        </div>

                        <p style="color:#ff4d4d">
                            If you don't want to change password... please leave them empty
                        </p>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Password</label>
                            <div class="col-9">
                                <input type="password" class="form-control" id="password" name="password" autocomplete="off"  placeholder="Enter password">

                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Confirm Password</label>
                            <div class="col-9">
                                <input type="password" class="form-control" name="confirmPass"  placeholder="Re-Enter password">

                            </div>
                        </div>


                        <!-- Date picker complete
                        <div class="form-group row">
                        <label class="col-3 col-form-label">Passport Expiry Date</label>
                            <div class= "col-9">
{{--                                @if($kyc->passportExpiry != null && $kyc->passportExpiry != '')--}}
{{--                                    <input type="text" class="form-control kt_datepicker_1" name="passportExpiry" value="{!! $kyc->passportExpiry !!}"--}}
{{--                                           readonly placeholder="Select date"/>--}}
{{--                                @else--}}
{{--                                    <input type="text" class="form-control kt_datepicker_1" name="passportExpiry"--}}
{{--                                           readonly placeholder="Select date"/>--}}
{{--                                @endif--}}
                        </div>
                        </div> -->



                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Is Blocked?</label>
                            @if($customer != NULL)
                                <div class="col-9">
                                    <div class="kt-radio-inline">
                                        <label class="kt-radio" for="chkYes">
                                            <input type="radio" name="is_blocked" id="chkYes" onclick="ShowHideDiv()" value="1" @if($customer->is_blocked == '1') checked @endif>Yes <span></span>
                                        </label>
                                        <label class="kt-radio" for="chkNo">
                                            <input type="radio" name="is_blocked" id="chkNo" onclick="ShowHideDiv()" value="2" @if($customer->is_blocked == '2') checked @endif>No <span></span>
                                        </label>
                                    </div>
                                </div>
                            @else
                                <div class="col-9">
                                    <div class="kt-radio-inline">
                                        <label class="kt-radio" for="chkYes">
                                            <input type="radio" name="is_blocked" id="chkYes" onclick="ShowHideDiv()" value="1">Yes<span></span>
                                        </label>
                                        <label class="kt-radio" for="chkNo">
                                            <input type="radio" name="is_blocked" id="chkNo" onclick="ShowHideDiv()"  value="2">No<span></span>
                                        </label>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div  id="reason_for_block" @if($customer->is_blocked == '2')  style="display: none" @else  style="display: block" @endif>
                            <div class="form-group row mb-4">
                                <label  class="col-3 col-form-label" for="example-text-input">Reason for block</label>
                                <div class="col-9">
                                    <input type="text" required  class="form-control" name="block_reason" @if($customer != NULL) value="{{ $customer->block_reason }}" @endif placeholder="Enter Reason for block">
                                </div>
                            </div>
                       </div>
                        <div class="form-group row" id="category" >
                            <label for="example-text-input" class="col-3 col-form-label">Category</label>
                            <div class="col-9">
                            <select class="form-control" name="customer_category" id="customer_category">

                                <option value="" @if($customer->customer_category == '') selected @endif>--Select Category--</option>
                                <option value="0" @if($customer->customer_category == 0) selected @endif>Low risk</option>
                                <option value="1" @if($customer->customer_category == 1) selected @endif>Moderate risk</option>
                                <option value="2" @if($customer->customer_category == 2) selected @endif>High rish</option>

                            </select>
                             </div>
                        </div>
                        <div class="form-group row" id="reason_for_category" >
                            <label for="example-text-input" class="col-3 col-form-label">Category Reason</label>
                            <div class="col-9">
                                <input type="textarea"  id="txtBox" class="form-control" name="category_reason" @if($reason != NULL) value="{{ $reason }}" @endif placeholder="Enter Reason for block">

                            </div>
                        </div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                     <!--   <h3 class="kt-heading kt-heading--md">
                            Customer KYC
                        </h3>
                        <div class="form-group row">

                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                <div class="kt-avatar" id="driversLicense" style="float: left; clear: left;">
                                    @if($kyc == NULL) --> <!-- first check if kyc object is null, if null display default image-->
                         <!--               <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    @else  --> <!--If not null, get the property -->
                                   <!--        @if($kyc->driving_license == null) -->   <!-- Check if the property is null, if null display default image -->
                                    <!-- <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        @else --> <!-- If not null display the the image from S3 -->
                                      <!--        @if(strpos($kyc->driving_license, 'amazonaws') !== false)
                                              <a href="{!! $kyc->driving_license !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder" style="background-image: url({!! $kyc->driving_license !!})"></div>
                                                </a>
                                            @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license) !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($kyc->driving_license) !!})"></div>
                                                </a>
                                            @endif
                                        @endif
                                    @endif
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file'  name="driversLicense" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>


                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Omang Id Front</h3>
                                <div class="kt-avatar" id="omangFront" style="float: left; clear: left;">
                                    @if($kyc == NULL)
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    @else
                                         @if($kyc->omang == NULL)
                                            <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                         @else
                                            @if(strpos($kyc->omang, 'amazonaws') !== false)
                                                <a href="{!! $kyc->omang !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder" style="background-image: url({!! $kyc->omang !!})"></div>
                                                </a>
                                            @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang) !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omang) !!})"></div>
                                                </a>
                                            @endif
                                        @endif
                                    @endif
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file'  name="omangFront" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Omang Id Back</h3>
                                <div class="kt-avatar" id="omangBack" style="float: left; clear: left;">
                                    @if($kyc == NULL)
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    @else
                                        @if($kyc->omangBack == NULL)
                                            <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        @else
                                            @if(strpos($kyc->omangBack, 'amazonaws') !== false)
                                                <a href="{!! $kyc->omangBack !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder" style="background-image: url({!! $kyc->omang !!})"></div>
                                                </a>
                                            @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omangBack) !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($kyc->omangBack) !!})"></div>
                                                </a>
                                            @endif
                                        @endif
                                    @endif
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file'  name="omangBack" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                <div class="kt-avatar" id="proofResidence" style="float: left; clear: left;">
                                    @if($kyc == NULL) --> <!-- first check if kyc object is null, if null display default image-->
                                    <!--    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    @else  --><!--If not null, get the Proof of residence property and check  -->
                                      <!--   @if($kyc->proof_residence == NULL)  -->  <!-- Check if it is null, if null display default image -->
                                     <!--    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                         @else  --><!-- If not null display the the image from S3 -->
                                        <!--    @if(strpos($kyc->proof_residence, 'amazonaws') !== false)
                                                <a href="{!! $kyc->proof_residence !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder" style="background-image: url({!! $kyc->proof_residence !!})"></div>
                                                </a>
                                            @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_residence) !!})"></div>
                                                </a>
                                            @endif
                                        @endif
                                    @endif
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file'  name="proofResidence" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>



                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Proof of Income</h3>
                                <div class="kt-avatar" id="proofIncome" style="float: left; clear: left;">
                                    @if($kyc == NULL)
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    @else
                                        @if($kyc->proof_income == NULL)
                                            <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        @else
                                            @if(strpos($kyc->proof_income, 'amazonaws') !== false)
                                                <a href="{!! $kyc->proof_income !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder" style="background-image: url({!! $kyc->proof_income !!})"></div>
                                                </a>
                                            @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($kyc->proof_income) !!})"></div>
                                                </a>
                                            @endif
                                        @endif
                                    @endif
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file'  name="proofIncome" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>


                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Passport</h3>
                                <div class="kt-avatar" id="passportImg" style="float: left; clear: left;">
                                    @if($kyc == null)
                                        <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    @else
                                        @if($kyc->passport == null)
                                            <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                        @else
                                            @if(strpos($kyc->passport, 'amazonaws') !== false)
                                                <a href="{!! $kyc->passport !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder" style="background-image: url({!! $kyc->passport !!})"></div>
                                                </a>
                                            @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport) !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($kyc->passport) !!})"></div>
                                                </a>
                                            @endif
                                        @endif
                                    @endif
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file'  name="passportImg" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>

                    </div>
              -->
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>


                                <div class="col-9">
                                    @can('customer-edit')
                                        <button type="submit" id="btn"  class="btn btn-brand">Update</button>
                                    @endcan
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.customer.index') }}" >Cancel</a>
                                </div>


                            </div>
                        </div>
                    </div>
                </div>
             </form>
                <!--end::Form-->
            </form>
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

@include('admin.layouts.scripts')
<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

<script>
     $(document).ready(function(){
        var entity_type = '{{$customerProfile->entity_type}}';
        if(entity_type=='Organisation'){
            $("#organisation_div").show();
            $("#person_div").hide();
        }else{
            $("#person_div").show();
            $("#organisation_div").hide();
        }

        $('#entity_type').on('change', function(){
            var demovalue = $(this).val();
            if(demovalue != 0){
                if(demovalue=='Organisation'){
                    $("#organisation_div").show();
                    $("#person_div").hide();
                }else{
                    $("#person_div").show();
                    $("#organisation_div").hide();
                }
            }else{
                $("#organisation_div").show();
                $("#person_div").show();
            }
        });
    });
</script>

<script>
    var KTBootstrapDatepicker = function () {

        var arrows;
        if (KTUtil.isRTL()) {
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>',
                format: 'yyyy-mm-dd'
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
                format: 'dd-mm-yyyy',
                orientation: "bottom left",
                endDate: "-18y",
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

    jQuery(document).ready(function() {
        KTBootstrapDatepicker.init();
    });

</script>

<script>
    var mytextbox = document.getElementById('displayCountry');
    var mydropdown = document.getElementById('selectCountry');
    mydropdown.onchange = function(){
        mytextbox.value = mytextbox.value  + this.value; //to appened
        mytextbox.innerHTML = this.value;
    }
</script>

<script>
    "use strict";
    // Class definition
    $(document).ready(function(){
        var val = $('#incomeSource').val();
        var sourceOfIncomeDiv = $('#sourceOfIncomeDiv');
        var str = '';

        if(val == '')
        {
            sourceOfIncomeDiv.empty();
        }
        else if(val == 'employment')
        {
            str += '<div class="form-group row">';
            str +=       '<div class="col-3"><label class="form-label" for="sourceOfIncome[employment][where_are_you_employed_?]">Where are you employed?<span class="red-star">*</span></label></div>';
            str +=       '<div class="col-9"><input class=" form-control required sourceOfIncomeInput" type="text" name="sourceOfIncome[employment][where_are_you_employed_?]" @if($i != null) value="{{ $i->$m1 }}"  @endif placeholder="Where are you employed?" minlength="3" maxlength="255"></div>';
             str +=  '</div>';

            str +=  '<div class="form-group row">';
            str +=      '<div class="col-3"><label class="form-label" for="sourceOfIncome[employment][what_is_your_monthly_salary_?]">What is your monthly salary?<span class="red-star">*</span></label></div>';
            str +=      '<div class="col-9"><input type="text"   onchange="validateFloatKeyPress(this);" name="sourceOfIncome[employment][what_is_your_monthly_salary_?]" class="form-control required sourceOfIncomeInput"  @if($i != null) value="{{ $i->$m2 }}" @endif placeholder="What is your monthly salary?"  min="1" step=".01"></div>';
            str +=  '</div>';
        }
        else if(val == 'pensioner_retired')
        {
            str += '<div class="form-group row">';
            str +=       '<div class="col-3"><label class="form-label" for="sourceOfIncome[pensioner_retired][how_much_is_your_monthly_pension_?]">How much is your monthly Pension?<span class="red-star">*</span></label></div>';
            str +=       '<div class="col-9"><input class="form-control required sourceOfIncomeInput" type="text" onchange="validateFloatKeyPress(this);" name="sourceOfIncome[pensioner_retired][how_much_is_your_monthly_pension_?]" @if($i != null) value="{{ $i->$m10 }}" @endif placeholder="How much is your monthly Pension?" minlength="3" maxlength="255"></div>';
             str +=  '</div>';
        }
        else if(val == 'bussiness')
        {
             str += '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[bussiness][name_of_your_bussiness]">Name of your business<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text"  name="sourceOfIncome[bussiness][name_of_your_bussiness]"  class="form-control required sourceOfIncomeInput"  @if($i != null) value="{{ $i->$m8 }}" @endif  placeholder="Name of your business" minlength="3" maxlength="255"></div>';
             str +=  '</div>';

             str +=  '<div class="form-group row">';
            str +=      '<div class="col-3"><label class="form-label" for="sourceOfIncome[bussiness][bussiness_address]">Business location/address<span class="red-star">*</span></label></div>';
            str +=      '<div class="col-9"><input  type="text" name="sourceOfIncome[bussiness][bussiness_address]" class="form-control required sourceOfIncomeInput"  @if($i != null) value="{{ $i->$m9 }}" @endif placeholder="Business location/address" minlength="3" maxlength="255"></div>';
             str +=  '</div>';

             str +=  '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[bussiness][what_is_your_monthly_income_?]">What is your monthly income?<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text"   onchange="validateFloatKeyPress(this);" name="sourceOfIncome[bussiness][what_is_your_monthly_income_?]" class="form-control required sourceOfIncomeInput"  @if($i != null) value="{{ $i->$m }}" @endif placeholder="What is your monthly income?" min="1" step=".01"></div>';
             str +=  '</div>';
        }
        else if(val == 'inheritance')
        {
             str += '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[inheritance][who_did_you_inherit_these_funds_from_?]">Who did you inherit these funds from?<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text"  name="sourceOfIncome[inheritance][who_did_you_inherit_these_funds_from_?]" class="form-control required sourceOfIncomeInput"  @if($i != null) value="{{ $i->$m3 }}" @endif placeholder="Who did you inherit these funds from?"  minlength="3" maxlength="255"></div>';
             str +=  '</div>';
        }
        else if(val == 'gifts')
        {
             str += '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[gifts][who_gifted_you_these_funds_?]">Who gifted you these funds?<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text" name="sourceOfIncome[gifts][who_gifted_you_these_funds_?]" class="form-control required sourceOfIncomeInput"  @if($i != null) value="{{ $i->$m4 }}" @endif placeholder="Who gifted you these funds?" minlength="3" maxlength="255"></div>';
             str +=  '</div>';

             str += '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[gifts][who_did_you_inherit_these_funds_from_?]">Who did you inherit these funds from?<span class="red-star">*</span></label></div>';
            str +=      '<div class="col-9"><select  class="form-control required sourceOfIncomeInput" name="sourceOfIncome[gifts][how_much_were_you_gifted_?]"  id="how_much_were_you_gifted">';
            str +=          '<option value="">Please select how much were you gifted</option>';
            str +=          '<option value="Once off gift"  @if($i != null) {{ $i->$m5 ='Once off gift' ? 'selected':'' }} @endif >Once off gift</option>';
            str +=          '<option value="Every month"  @if($i != null) {{ $i->$m5 ='Every month' ? 'selected':'' }} @endif >Every month</option>';
            str +=          '<option value="Every year"  @if($i != null) {{ $i->$m5 ='Every year' ? 'selected':'' }} @endif >Every year</option>';
            str +=      '</select></div>';
             str +=  '</div>';
        }
        else if(val == 'investments')
        {
             str += '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[investments][what_is_the_amount_of_funds_invested_?]">What is the amount of funds invested?<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text" onchange="validateFloatKeyPress(this);" name="sourceOfIncome[investments][what_is_the_amount_of_funds_invested_?]"  class="form-control required sourceOfIncomeInput"  @if($i != null) value="{{ $i->$m6 }}" @endif placeholder="What is the amount of funds invested?"  min="1" step="0.01"></div>';
             str +=  '</div>';
             str +=  '<div class="form-group row">';
            str +=      '<div class="col-3"><label class="form-label" for="sourceOfIncome[investments][how_much_do_you_earn_from_these_investments_per_month_?]">How much do you earn from these investments per month?<span class="red-star">*</span></label></div>';
            str +=      '<div class="col-9"><input type="text" onchange="validateFloatKeyPress(this);" name="sourceOfIncome[investments][how_much_do_you_earn_from_these_investments_per_month_?]"  class="form-control required sourceOfIncomeInput"  @if($i != null) value="{{ $i->$m7 }}" @endif placeholder="How much do you earn from these investments per month?"  min="1" step="0.01"></div>';
            str +=  '</div>';
        }
        str += '<br>';
        sourceOfIncomeDiv.html(str);

    })

    $('#sourceOfIncome').on('change', function(){
        var val = $(this).val();
        var sourceOfIncomeDiv = $('#sourceOfIncomeDiv');
        var str = '';
        if(val == '')
        {
            sourceOfIncomeDiv.empty();
        }
        else if(val == 'employment')
        {
            str += '<div class="form-group row">';
            str +=       '<div class="col-3"><label class="form-label" for="sourceOfIncome[employment][where_are_you_employed_?]">Where are you employed?<span class="red-star">*</span></label></div>';
            str +=       '<div class="col-9"><input class=" form-control required sourceOfIncomeInput" type="text" name="sourceOfIncome[employment][where_are_you_employed_?]" value="" placeholder="Where are you employed?" minlength="3" maxlength="255"></div>';
             str +=  '</div>';

            str +=  '<div class="form-group row">';
            str +=      '<div class="col-3"><label class="form-label" for="sourceOfIncome[employment][what_is_your_monthly_salary_?]">What is your monthly salary?<span class="red-star">*</span></label></div>';
            str +=      '<div class="col-9"><input type="text"   onchange="validateFloatKeyPress(this);" name="sourceOfIncome[employment][what_is_your_monthly_salary_?]" class="form-control required sourceOfIncomeInput" value="" placeholder="What is your monthly salary?"  min="1" step=".01"></div>';
            str +=  '</div>';
        }
        else if(val == 'pensioner_retired')
        {
            str += '<div class="form-group row">';
            str +=       '<div class="col-3"><label class="form-label" for="sourceOfIncome[pensioner_retired][how_much_is_your_monthly_pension_?]">How much is your monthly Pension?<span class="red-star">*</span></label></div>';
            str +=       '<div class="col-9"><input class="form-control required sourceOfIncomeInput" type="text" name="sourceOfIncome[pensioner_retired][how_much_is_your_monthly_pension_?]" value="" placeholder="How much is your monthly Pension?" minlength="3" maxlength="255"></div>';
             str +=  '</div>';
        }
        else if(val == 'bussiness')
        {
             str += '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[bussiness][name_of_your_bussiness]">Name of your business<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text"  name="sourceOfIncome[bussiness][name_of_your_bussiness]"  class="form-control required sourceOfIncomeInput" value="" placeholder="Name of your business" minlength="3" maxlength="255"></div>';
             str +=  '</div>';

             str +=  '<div class="form-group row">';
            str +=      '<div class="col-3"><label class="form-label" for="sourceOfIncome[bussiness][bussiness_address]">Business location/address<span class="red-star">*</span></label></div>';
            str +=      '<div class="col-9"><input  type="text" name="sourceOfIncome[bussiness][bussiness_address]" class="form-control required sourceOfIncomeInput" value="" placeholder="Business location/address" minlength="3" maxlength="255"></div>';
             str +=  '</div>';

             str +=  '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[bussiness][what_is_your_monthly_income_?]">What is your monthly income?<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text"   onchange="validateFloatKeyPress(this);" name="sourceOfIncome[bussiness][what_is_your_monthly_income_?]" class="form-control required sourceOfIncomeInput" value="" placeholder="What is your monthly income?" min="1" step=".01"></div>';
             str +=  '</div>';
        }
        else if(val == 'inheritance')
        {
             str += '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[inheritance][who_did_you_inherit_these_funds_from_?]">Who did you inherit these funds from?<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text"  name="sourceOfIncome[inheritance][who_did_you_inherit_these_funds_from_?]" class="form-control required sourceOfIncomeInput" value="" placeholder="Who did you inherit these funds from?"  minlength="3" maxlength="255"></div>';
             str +=  '</div>';
        }
        else if(val == 'gifts')
        {
             str += '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[gifts][who_gifted_you_these_funds_?]">Who gifted you these funds?<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text" name="sourceOfIncome[gifts][who_gifted_you_these_funds_?]" class="form-control required sourceOfIncomeInput" value="" placeholder="Who gifted you these funds?" minlength="3" maxlength="255"></div>';
             str +=  '</div>';

             str += '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[gifts][who_did_you_inherit_these_funds_from_?]">Who did you inherit these funds from?<span class="red-star">*</span></label></div>';
            str +=      '<div class="col-9"><select  class="form-control required sourceOfIncomeInput" name="sourceOfIncome[gifts][how_much_were_you_gifted_?]"  id="how_much_were_you_gifted">';
            str +=          '<option value="">Please select how much were you gifted</option>';
            str +=          '<option value="Once off gift">Once off gift</option>';
            str +=          '<option value="Every month">Every month</option>';
            str +=          '<option value="Every year">Every year</option>';
            str +=      '</select></div>';
             str +=  '</div>';
        }
        else if(val == 'investments')
        {
             str += '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[investments][what_is_the_amount_of_funds_invested_?]">What is the amount of funds invested?<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text" onchange="validateFloatKeyPress(this);" name="sourceOfIncome[investments][what_is_the_amount_of_funds_invested_?]"  class="form-control required sourceOfIncomeInput" value="" placeholder="What is the amount of funds invested?"  min="1" step="0.01"></div>';
             str +=  '</div>';
             str +=  '<div class="form-group row">';
            str +=      '<div class="col-3"><label class="form-label" for="sourceOfIncome[investments][how_much_do_you_earn_from_these_investments_per_month_?]">How much do you earn from these investments per month?<span class="red-star">*</span></label></div>';
            str +=      '<div class="col-9"><input type="text" onchange="validateFloatKeyPress(this);" name="sourceOfIncome[investments][how_much_do_you_earn_from_these_investments_per_month_?]"  class="form-control required sourceOfIncomeInput" value="" placeholder="How much do you earn from these investments per month?"  min="1" step="0.01"></div>';
            str +=  '</div>';
        }
        str += '<br>';
        sourceOfIncomeDiv.html(str);
    });

    var KTFormControls = function () {
// Private functions
        jQuery.validator.addMethod("passport", function(value, element) {
        return this.optional(element) || /^[a-zA-Z0-9]+$/gi.test(value);
        }, 'Sorry ! This passport number is not valid');

        jQuery.validator.addMethod("omangRegex", function(value, element) {
            return this.optional( element ) || /^[0-9]{4}[1-2][0-9]{4}$/.test( value );
        }, 'Sorry ! This omang number is not valid');

        jQuery.validator.addMethod("cutomer_email", function(value, element) {
            return this.optional( element ) || /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test( value );
        }, 'Sorry ! This cutomer email is not valid');


        var demo1 = function () {
            $( "#customerEdit" ).validate({
            // define validation rules
            rules: {
                entity_type: {
                    required: true,
                },
                org_name: {
                    required: function(element){
                        return $('#entity_type option:selected').val() == 'Organisation';
                    },
                },
                vat_reg: {
                    required: function(element){
                        return $('#entity_type option:selected').val() == 'Organisation';
                    },
                },
                reg_no: {
                    required: function(element){
                        return $('#entity_type option:selected').val() == 'Organisation';
                    },
                },
                tax_id: {
                    required: function(element){
                        return $('#entity_type option:selected').val() == 'Organisation';
                    },
                },
                org_phone_no: {
                    required: function(element){
                        return $('#entity_type option:selected').val() == 'Organisation';
                    },
                    minlength:8,
                },
                first_name: {
                    required: function(element){
                        return $('#entity_type option:selected').val() == 'Person';
                    },
                },
                last_name: {
                    required: function(element){
                        return $('#entity_type option:selected').val() == 'Person';
                    },
                },
                cutomer_email: {
                    required: false,
                    cutomer_email:true,
                },
                password: {
                    // required: true,
                    minlength:6,
                },
                confirmPass: {
                    minlength:6,
                    equalTo : "#password"
                },
                address: {
                    required: true,
                },
                passport: {
                    required: function(element){
                        return $("#omang").val()=="" && $('#entity_type option:selected').val() == 'Person';
                    },
                },
                omang: {
                    omangRegex:true,
                    minlength:9,
                    required: function(element){
                        return $("#passport").val()=="" && $('#entity_type option:selected').val() == 'Person';
                    },
                },
                countryId: {
                    required: function(element){
                        return $("#passport").val()!="" ;
                    },
                },
                maritalstatus: {
                    required: function(element){
                        return  $('#entity_type option:selected').val() == 'Person';
                    },
                },
                block_reason: {
                    required: function(element){
                        return  $("input[name='is_blocked']:checked").val() == 1;
                    },
                },
                mobile: {
                    required: true,
                    minlength:8,
                },
                state: {
                    required: true,
                },
                city: {
                    required: true,
                },
                gender: {
                    required: function(element){
                        return $('#entity_type option:selected').val() == 'Person';
                    },
                },
                dob: {
                    required: function(element){
                        return $('#entity_type option:selected').val() == 'Person';
                    },
                },
                sourceOfIncome:{
                    required: function(element){
                        return $('#entity_type option:selected').val() == 'Person';
                    },
                },
            },
            messages: {
                entity_type: {
                    required: "Select entity type",
                },
                org_name: {
                    required: "Please enter organisation name",
                },
                vat_reg: {
                    required: "Please enter VAT registration number",
                },
                reg_no: {
                    required: "Please enter company registration number",
                },
                tax_id: {
                    required: "Please enter tax id",
                },
                org_phone_no: {
                    required: "Please enter organisation phone number",
                },
                firstname: {
                    required: "Please enter customer firstname",
                },
                lastname: {
                    required:"Please enter customer lastname",
                },
                password: {
                    minlength: "Password must be at least 6 characters long",
                },
                confirmPass: {
                    minlength: "Password must be at least 6 characters long",
                },
                passport: {
                    required:"Please enter passport",
                },
                omang: {
                    required:"Please enter omang",
                },
                countryId: {
                    required:"Please select",
                },
                maritalstatus:{
                    required:"Please select marital status",
                },
                mobile: {
                    required: "Please provide a mobile",
                    minlength: "customer mobile number must be at least 8  long",
                },
                state: {
                    required: "Please select customer district",
                },
                city: {
                    required: "Please select customer city",
                },
                gender: {
                    required: "Please select customer gender",
                },
                dob: {
                    required: "Please select dob",
                },
                sourceOfIncome: {
                    required: "Please select source of income",
                },

            },

                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    KTUtil.scrollTo("customerEdit", -200);
                    $('#btn').show();
                },
                submitHandler: function (form) {
                    $('#btn').hide();
                    $('#loadBtn').show()
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

    jQuery.validator.addMethod("cutomer_email", function(value, element) {
            return this.optional( element ) || /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test( value );
        }, 'Sorry ! This cutomer email is not valid');

    jQuery(document).ready(function() {
        KTFormControls.init();
    });


</script>

<script>
     function ShowHideDiv() {
        var chkYes = document.getElementById("chkYes");
        var reason_for_block = document.getElementById("reason_for_block");
        reason_for_block.style.display = chkYes.checked ? "block" : "none";
        // if( $(this).is(':checked') && chkYes=='2'){
        //   $('#txtBox input').removeClass('required');
        // }
    }
</script>

<script>

    var KTAvatarDemo = function() {

        return {
            // Init demos
            init: function() {
                var avatar1 = new KTAvatar('driversLicense');
                var avatar2 = new KTAvatar('omangFront');
                var avatar3 = new KTAvatar('proofResidence');
                var avatar4 = new KTAvatar('proofIncome');
                var avatar5 = new KTAvatar('passportImg');
                var avatar6 = new KTAvatar('omangBack');
            }
        };
    }();

    jQuery(document).ready(function(){
        KTAvatarDemo.init();
    });

    $('#state').change(function() {
        $('#city').empty();
        $('#loader').css("display", "block");
        var state_id = $(this).val();
        $.ajax({
            type: 'POST',
            beforeSend: function() {
                $('#city').hide();
                //$('#valueLoader').show();
                $('.modelSpinner').show();
            },
            data: {
                "_token": "{{ csrf_token() }}",
                "state_id": state_id,
            },
            headers: {
                'api-token': "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9",
            },
            url: "https://graphite.alphadirect.co.bw/api/frontendpay/getCities",
            dataType: 'json',
            success: function(data) {

                var append = '';
                append += '<option value="">Select City</option>';
                if (data.cities.length > 0) {
                    jQuery.each(data.cities, function(i, val) {
                        append += '<option value="' + val.name + '">' + val.name + '</option>';
                    });
                }
                console.log(append);
                $('#city').append(append);
                $('#city').show();
                $('#loader').css("display", "none");

            },
            error: function(error) {
                if (error.status == 401) {
                    toastr.error("Unauthorized Access");
                }

            }
        });
    });
</script>


</body>
<!-- end::Body -->
</html>
