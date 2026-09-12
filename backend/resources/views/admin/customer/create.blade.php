<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/tether/dist/css/tether.css') }}" rel="stylesheet" type="text/css" />
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
                    Create customer
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{route('admin.customer.index')}}" class="kt-subheader__breadcrumbs-link"> Customer </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->

        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="customerCreate"  action="{{ route('admin.customer.store') }}" method="POST" enctype="multipart/form-data" class="">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        
                        <div class="form-group row">
                            <div class="col-3"><label for="example-text-input" class="form-label">Entity Type  <span class="red-star">*</span></label></div>
                            <div class="col-9">
                                <select name="entity_type" id="entity_type" class="form-control entity_type" >
                                    <option value="" >-- Select Entity Type --</option>
                                    <option value="Organisation" >Organisation</option>
                                    <option value="Person" >Person</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="organisation_div">
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Organisation Name <span class="red-star">*</span></label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="org_name" value="" id="org_name" placeholder="Enter Organisation Name">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">VAT Registration Number <span class="red-star">*</span></label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="vat_reg" value=""  placeholder="Enter VAT Registration Number">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Company Registration Number <span class="red-star">*</span></label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="reg_no" value=""  placeholder="Enter Company Registration Number">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Tax ID<span class="red-star">*</span></label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="tax_id" value=""  placeholder="Enter Tax ID">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Organisation Phone Number <span class="red-star">*</span></label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="org_phone_no" value=""  placeholder="Enter Organisation Phone Number">
                                </div>
                            </div>

                        </div>

                        <div id="person_div">
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">First Name <span class="red-star">*</span></label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="first_name" value=""  placeholder="Enter first name">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Middle Name</label>
                                <div class="col-9">
                                    <input type="text" class="form-control"  name="middle_name" value=""  placeholder="Enter middle name">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Last Name <span class="red-star">*</span></label>
                                <div class="col-9">
                                    <input type="text" class="form-control" name="last_name" value=""   placeholder="Enter last name">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-3 col-form-label">Gender <span class="red-star">*</span></label>
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
                            </div>

                            <div class="form-group row">
                                <label for="example-date-input" class="col-3 col-form-label">Date of Birth <span class="red-star">*</span></label>
                                <div class="col-9">
                                    <input type="text" class="form-control kt_datepicker_1" id="dob_datepicker"  name="dob"
                                        placeholder="Select date"/>
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-3 col-form-label">
                                    <label  for="sourceOfIncome">Source of Income/Funds <span class="red-star">*</span>:</label>
                                </div>
                                <div class="col-9">
                                    <select class="form-control" name="sourceOfIncome" id="sourceOfIncome">
                                        <option value="">Please select source of Income/Funds</option>
                                        <option value="unemployed">Unemployed</option>
                                        <option value="employment">Employment</option>
                                        <option value="pensioner_retired">Pensioner/Retired</option>
                                        <option value="bussiness">Self-Employment/Business</option>
                                        <option value="inheritance">Inheritance</option>
                                        <option value="gifts">Gifts</option>
                                        <option value="investments">Investments</option>
                                    </select>
                                </div>
                            </div>

                            <div id="sourceOfIncomeDiv">
                            </div>

                            <div class="form-group row">
                                <label for="example-date-input" class="col-3 col-form-label">Marital Status <span class="red-star">*</span></label>
                                <div class="col-9">
                                <select class="form-control  maritalstatus" name="maritalstatus" id="marital">
                                    <option value=""> ---- Select ---- </option>
                                    <option value="1" >Single</option>
                                    <option value="2" >Married</option>
                                    <option value="3" >Divorced</option>
                                    <option value="4" >Widowed</option>
                                    <option value="5" >Living Together</option>
                                    <option value="6" >Living Separately</option>
                                </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Omang <span class="red-star">*</span></label>
                                <div class="col-9">
                                    <input type="text" class="form-control" value="" id="omang" name="omang" pattern="[0-9]{9}" maxlength="9"  placeholder="Enter Omang">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Passport <span class="red-star">*</span></label>
                                <div class="col-9">
                                    <input type="text" class="form-control passport" id="passport"  name="passport" value="" minlength="3" maxlength="20" placeholder="Enter passport number">
                                </div>
                            </div>

                            
                            <div class="form-group row">
                                <div class="col-3"><label for="example-text-input" class="form-label">Passport Issuing Country <span class="red-star">*</span></label></div>
                                <div class="col-9">
                                    <select name="countryId" class="form-control">
                                        <option value="0" >-- Select Country --</option>
                                        @foreach($countries as $country)
                                            <option value="{{ $country->id }}" >{{$country->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            
                        </div>
                     
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Email </span></label>
                            <div class="col-9">
                                <input type="text" class="form-control" id="cutomer_email" name="cutomer_email"  value=""  placeholder="Enter email" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Mobile No. <span class="red-star">*</span></label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="mobile" value=""  pattern="[0-9]{1,25}" maxlength="8" placeholder="Enter mobile no.">
                            </div>
                        </div>
                      
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">District <span class="red-star">*</span></label>
                           <div class="col-9">
                                <select class="form-control " name="state" id="state">
                                <option value="">--Select District--</option>
                                    @foreach ($states as $state)
                                        <option value="{{ $state->id }}">{{ $state->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">City <span class="red-star">*</span></label>
                            <div class="col-9">
                                <select class="form-control " name="city"  id="city">

                                @isset($cities)
                                    @foreach ($cities as $city)
                                    <option value="{{ $city->name }}" >{{ $city->name }}</option>
                                    @endforeach
                                @endisset

                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Address <span class="red-star">*</span></label>
                            <div class="col-9">
                                <input type="textarea" class="form-control" name="address" value=""   placeholder="Enter address">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Password</label>
                            <div class="col-9">
                                <input type="password" class="form-control" name="password" value=""  placeholder="Enter password" autocomplete="off" id="password">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Confirm Password</label>
                            <div class="col-9">
                                <input type="password" class="form-control" name="confirmPass" value=""   placeholder="Re-Enter password" autocomplete="off" >
                            </div>
                        </div>
                        
                    </div>



                    <div class="kt-portlet__body">
                       <!-- <h3 class="kt-heading kt-heading--md">
                            Customer KYC
                        </h3>
                        <div class="form-group row">
                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                <div class="kt-avatar" id="driving"  style="float: left; clear: left;">
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file'  name="driving"  <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>


                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Omang Id Front</h3>
                                <div class="kt-avatar" id="omang"  style="float: left; clear: left;">
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file'  name="omang" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Omang Id Back</h3>
                                <div class="kt-avatar" id="omangBack"  style="float: left; clear: left;">
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file'  name="omangBack" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>



                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                <div class="kt-avatar" id="residence"  style="float: left; clear: left;">
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file'  name="residence" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Proof Of Income</h3>
                                <div class="kt-avatar" id="income"   style="float: left; clear: left;">
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file'  name="income" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Passport</h3>
                                <div class="kt-avatar" id="passport"   style="float: left; clear: left;">
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file' name="passport"  <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
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
                                        <button type="submit" id="btn" value="Submit" class="btn btn-brand">Submit</button>
                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                        <a class="btn btn-secondary" href="{{ route('admin.customer.index') }}" >Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </form>
                <!--end::Form-->
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

@include('admin.layouts.scripts')
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datetime-picker/js/bootstrap-datetimepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-timepicker/js/bootstrap-timepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-timepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.js') }}" type="text/javascript"></script>

<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>

<script>
        $(document).ready(function(){
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


        $( "#customerCreate" ).validate({
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
        });

        jQuery.validator.addMethod("passport", function(value, element) {
        return this.optional(element) || /^[a-zA-Z0-9]+$/gi.test(value);
        }, 'Sorry ! This passport number is not valid');

        jQuery.validator.addMethod("omangRegex", function(value, element) {
            return this.optional( element ) || /^[0-9]{4}[1-2][0-9]{4}$/.test( value );
        }, 'Sorry ! This omang number is not valid');

        jQuery.validator.addMethod("cutomer_email", function(value, element) {
            return this.optional( element ) || /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test( value );
        }, 'Sorry ! This cutomer email is not valid');

</script>


<script src="{{ asset('assets/app/custom/general/components/forms/layouts/repeater.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
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

    var KTAvatarDemo = function() {

        return {
            // Init demos
            init: function() {
                var avatar1 = new KTAvatar('driving');
                var avatar2 = new KTAvatar('omang');
                var avatar3 = new KTAvatar('residence');
                var avatar4 = new KTAvatar('income');
                var avatar5 = new KTAvatar('passport');
                var avatar6 = new KTAvatar('omangBack');
            }
        };
    }();


    jQuery(document).ready(function(){
        KTAvatarDemo.init();
    });
    </script>


    <script>

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
<script>
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
            str +=      '<div class="col-9"><input type="text"   onchange="validateFloatKeyPress(this);" name="sourceOfIncome[employment][what_is_your_monthly_salary_?]" class="form-control required sourceOfIncomeInput" value="" placeholder="What is your monthly salary?"  min="1" step=".01" required></div>';
            str +=  '</div>';
        }
        else if(val == 'pensioner_retired')
        {
            str += '<div class="form-group row">';
            str +=       '<div class="col-3"><label class="form-label" for="sourceOfIncome[pensioner_retired][how_much_is_your_monthly_pension_?]">How much is your monthly Pension?<span class="red-star">*</span></label></div>';
            str +=       '<div class="col-9"><input class=" form-control required sourceOfIncomeInput" type="text" name="sourceOfIncome[pensioner_retired][how_much_is_your_monthly_pension_?]" value="" placeholder="How much is your monthly Pension?" minlength="3" maxlength="255"></div>';
             str +=  '</div>';
        }
        else if(val == 'bussiness')
        {
             str += '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[bussiness][name_of_your_bussiness]">Name of your business<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text"  name="sourceOfIncome[bussiness][name_of_your_bussiness]"  class="form-control required sourceOfIncomeInput" value="" placeholder="Name of your business" minlength="3" maxlength="255" required></div>';
             str +=  '</div>';

             str +=  '<div class="form-group row">';
            str +=      '<div class="col-3"><label class="form-label" for="sourceOfIncome[bussiness][bussiness_address]">Business location/address<span class="red-star">*</span></label></div>';
            str +=      '<div class="col-9"><input  type="text" name="sourceOfIncome[bussiness][bussiness_address]" class="form-control required sourceOfIncomeInput" value="" placeholder="Business location/address" minlength="3" maxlength="255" required></div>';
             str +=  '</div>';

             str +=  '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[bussiness][what_is_your_monthly_income_?]">What is your monthly income?<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text"   onchange="validateFloatKeyPress(this);" name="sourceOfIncome[bussiness][what_is_your_monthly_income_?]" class="form-control required sourceOfIncomeInput" value="" placeholder="What is your monthly income?" min="1" step=".01" required></div>';
             str +=  '</div>';
        }
        else if(val == 'inheritance')
        {
             str += '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[inheritance][who_did_you_inherit_these_funds_from_?]">Who did you inherit these funds from?<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text"  name="sourceOfIncome[inheritance][who_did_you_inherit_these_funds_from_?]" class="form-control required sourceOfIncomeInput" value="" placeholder="Who did you inherit these funds from?"  minlength="3" maxlength="255" ></div>';
             str +=  '</div>';
        }
        else if(val == 'gifts')
        {
             str += '<div class="form-group row">';
            str +=    '<div class="col-3"><label class="form-label" for="sourceOfIncome[gifts][who_gifted_you_these_funds_?]">Who gifted you these funds?<span class="red-star">*</span></label></div>';
            str +=    '<div class="col-9"><input type="text" name="sourceOfIncome[gifts][who_gifted_you_these_funds_?]" class="form-control required sourceOfIncomeInput" value="" placeholder="Who gifted you these funds?" minlength="3" maxlength="255" required></div>';
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
            str +=    '<div class="col-9"><input type="text" onchange="validateFloatKeyPress(this);" name="sourceOfIncome[investments][what_is_the_amount_of_funds_invested_?]"  class="form-control required sourceOfIncomeInput" value="" placeholder="What is the amount of funds invested?"  min="1" step="0.01" required></div>';
             str +=  '</div>';
             str +=  '<div class="form-group row">';
            str +=      '<div class="col-3"><label class="form-label" for="sourceOfIncome[investments][how_much_do_you_earn_from_these_investments_per_month_?]">How much do you earn from these investments per month?<span class="red-star">*</span></label></div>';
            str +=      '<div class="col-9"><input type="text" onchange="validateFloatKeyPress(this);" name="sourceOfIncome[investments][how_much_do_you_earn_from_these_investments_per_month_?]"  class="form-control required sourceOfIncomeInput" value="" placeholder="How much do you earn from these investments per month?"  min="1" step="0.01" required></div>';
            str +=  '</div>';
        }
        str += '<br>';
        sourceOfIncomeDiv.html(str);
    });


</script>







</body>
<!-- end::Body -->
</html>
