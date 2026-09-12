<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

<style>
    .form-group label {
        font-size: 1.2rem !important;
        font-weight: 400;
    }
    .kt-heading.kt-heading--md {
        font-size: 2.0rem !important;
    }

    .se-pre-con {
        position: fixed;
        left: 0px;
        top: 0px;
        width: 100%;
        height: 100%;
        z-index: 9999;
        background: url(../img/loading.gif) center no-repeat #fff;
        }
</style>
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >
<!-- begin:: Header Mobile -->
<div class="se-pre-con">Loading&#8230;</div> 


<!-- end:: Header Mobile -->
<!-- begin:: Root -->
<div class="kt-grid kt-grid--hor kt-grid--root">
    <!-- begin:: Page -->
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">

{{--         @include('admin.layouts.sidebar')
 --}}{{--         @include('admin.layouts.topNav')
 --}}

    </div>
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

    @if ($successMessage = session('successMessage'))
    <div class="alert alert-success fade show" role="alert">
        <div class="alert-text"><strong>Success:</strong> {{ $successMessage }}</div>
        <div class="alert-close">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true"><i class="la la-close"></i></span>
            </button>
        </div>
    </div> 
    @endif  
 
    {{ session()->get('successMessage') }}
    @if(session()->get('successMessage'))
        <div class="alert alert-success alert-block">
            <button type="button" class="close" data-dismiss="alert">×</button>	
            <strong>Policy Created</strong>
        </div>   
    @endif
                

    
   
    @if( !empty($status))
    
    <div class="alert alert-success mt-2  fade show"  role="alert" id="success">
        <p class="text ">Success</p>
        <button type="button" class="close" data-dismiss="alert">×</button>
    </div>
    @else

    @endif



    <div class="alert alert-warning mt-2  fade show" role="alert" id="error" style="display:none;">

        <p class="error-message"></p>
        <button type="button" class="close" data-dismiss="alert">×</button>
    </div>
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->

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
                <!-- action="https://graphite.alphadirect.co.bw/api/activationTempStore"-->
                <form id="policyForm" action="{{env('GRAPHITE_URL')}}/api/activationTempStore"
                          method="POST" enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <div class="row">
                            <div class="col-md-8 col-sm-12 col-xs-12">
                                <div class="form-group">
                                    <label>Omang ID</label>
                                    <input id="omang" type="text" class="form-control id-type validateGroup1" name="omang" placeholder="Please enter omang number" pattern="[0-9]{9}" maxlength="9"  value="{{ !empty($selectedCustomer) ? $selectedCustomer->profile->omang : old('omang') }}" >
                                    <p class="existingOmangError" style="color:#e61c30;display:none;" >Customer with this Omang ID already exist</p>
                                </div>
                                <div class="form-group">
                                    <label>Passport Number</label>
                                    <input id="passport" type="text" class="form-control id-type validateGroup1" name="passport" placeholder="Please enter passport number" maxlength="12" value="{{ !empty($selectedCustomer) ? $selectedCustomer->profile->passport : old('passport') }}" >
                                    <p class="existingPassportError" style="color:#e61c30; display: none;">Customer with this Passport Number already exist</p>
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
                            <div class="col-lg-9">
                                <div class="form-group">
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
                                            <option data-subtext="@if($product->type){!! $product->type->name !!} @endif" value="{!! $product->id !!}">{!! $product->name !!}</option>
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
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <label>Vehicle Number</label>
                                        <input type="text" class="form-control vehiclePlate" name="vehiclePlate"  id="vehicle_number" placeholder="Enter vehicle number">
                                        <p id="vehicleError" style="display:none; color:red;">Policy already taken for this Vehicle</p>
                                    </div>
                                </div>
                            </div>
                        </div>



                        
              


                        <div class="kt-portlet__foot">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" id="submitBtn" value="Submit" class="btn btn-brand" data-toggle="tooltip" data-placement="top" title="Create Policy">Submit</button>
                                        <a class="btn btn-secondary" href="{{ route('tempActivation') }}" >Cancel</a>
                                    </div>
                                </div>
                            </div>

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
    <div class="kt-footer kt-grid__item kt-grid kt-grid--desktop kt-grid--ver-desktop">
        <div class="kt-footer__copyright"> 2018&nbsp;&copy;&nbsp;<a href="#" target="_blank" class="kt-link">Alpha Direct</a> </div>
    </div>
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
                    activation_code: {
                        required: true
                    },
                  /*email: {
                        required: true
                    }, */
                    address: {
                        required: true
                    },
                    omang: {
                        require_from_group: [1, '.validateGroup1'],
                    },
                    passport: {
                        require_from_group: [1, '.validateGroup1'],
                    },
                    dob: {
                        required: true,
                        future: true,
                    },
                    billingMethod:{
                        required:true
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
                    email: "Please enter your email",
                    address: "Please enter your address",
                    activation_code: "Please enter your clients activation code",
                    omang: {
                        require_from_group: "Please provide either your Omang Id or Passport",
                        max: "Your Omang Id can be max 9 characters long",
                        maxlength: "Your Omang Id can be max 9 characters long"
                    },
                    passport: {
                        require_from_group: "Please provide either your Omang Id or Passport",
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

    jQuery(document).ready(function() {

        $(".se-pre-con").css("display", "none");

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
                }else {
                    $('<p>Activation Code Generated Not Generated store Activation Code</p>').appendTo('#codeDiv');
                }
            },
        });
    });
    $('#product').on('change', function (e, data) {
        var omang = $('#omang').val();
        var passport = $('#passport').val();
        var product_id = this.value; 
        var urlValue = '{{ \Config::get('values.graphite_url') }}' 
        console.log(urlValue);
        if(data != null)
            var selected_plan_id = data.plan_id;
        var selected = '';
        $.ajax({
           
            //product_factors
            url: urlValue+'api/product_factors-Api',
            data: {
                //"_token": "{{ csrf_token() }}",
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
                        $('#existingProduct').text('Please enter Omang or Passport and Activation Code of Customer to Select Product')
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
                            append += '<select class="form-control plan" name="plan" title="Please choose Plan">';
                            append += '<option value="0">Select Plan</option>';
                            if (data.plans.length > 0) {
                                data.plans.forEach(function ($value) {
                                    if(selected_plan_id == $value.id){
                                        selected = 'selected';
                                    }else{
                                        selected = '';
                                    }
                                    append += '<option value="' + $value.id + '" '+ selected + ' >' + $value.name + ' | Premium : ' + $value.premium + ' | Sum Assured : ' + $value.sum_assured + '</option>';
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
                            $('.plan').prop('readOnly', true);

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
                            $('.chassisNo').rules('add', {
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
                            $('.model').rules('add', {required: true, messages: {required: "Please enter Model"}});
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
                                    '<td><input type="number" name="cover_value[]"></td>' +
                                    '<td><select name="type[]" required><option value="0">Select Discount/Surcharge</option><option value="1">Discount</option><option value="2">Surcharge</option></select></td>' +
                                    '<td><select name="disccount_type[]" required><option value="0">Select Flat/%</option><option value="1">Flat</option><option value="2">%</option></select></td>' +
                                    '<td><input type="number" name="type_value[]"></td>' +
                                    '</tr>';
                        });
                        $('#coverageDiv').empty();
                        $('#coverageDiv').append(coverage);
                        $("[name^='cover_value']").each(function () {
                            $(this).rules("add",
                                    {
                                        required: true,
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
                            $('.beneficiaryDOB').rules('add', {required: true, future:true});
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
            var urlValue = '{{ \Config::get('values.graphite_url') }}'
            if(value != '')
            {
                clearTimeout(ajaxRequest);
                ajaxRequest = setTimeout(function(sn) {
                    $.ajax({

                        //checkUserVehicle-Api
                        url: urlValue+'api/checkUserVehicle-Api',
                        data: {
                            //"_token": "{{ csrf_token() }}",
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
            var urlValue = '{{ \Config::get('values.graphite_url') }}'
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({

                    //checkUserOmang
                    url: urlValue+'api/checkUserOmang-Api',
                    data: {
                        //"_token": "{{ csrf_token() }}",
                        "omang": omang,
                        "passport": passport
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if( data.count != null) {
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

                            if(omang != '')
                                $('.existingOmangError').css('display','block');
                            else
                                $('.existingPassportError').css('display','block');
                        } else {
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
        var urlValue = '{{ \Config::get('values.graphite_url') }}'
        var ajaxRequest;
        $('#make').change(function() {
            var make = $('#make').val();
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({

                    //checkMakeModel
                    url: urlValue+'api/checkMakeModel-Api',
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
                            /* remind that 'data' is the response of the AjaxController */
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

        $(".se-pre-con").css("display", "block");



        $code = $(this).val(); 
        var urlValue = '{{ \Config::get('values.graphite_url') }}'
        ajaxRequest = setTimeout(function (sn) {
            $.ajax({
                url: urlValue+'api/checkActivation-Api',
                data: {
                   // "_token": "{{ csrf_token() }}",
                    "code": $code
                },
                type: 'post',
                datatype: 'json',
                success: function (data) {
                    if (data.count != null) {

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
                        $('#product').prop('readOnly', true);
                        $(".se-pre-con").css("display", "none");

                        console.log('PLAN DISABLED');

                    }
                }
            });
        }, 100);
    });
</script>

<script>
$(document).ready(function () {
        var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');


        $('#saveLead').click(function (e) {
            e.preventDefault();
            var validator = $( "#policyForm" ).validate();
            validator.element('.firstName');
            validator.element('.lastName');
            validator.element('.cellphone');

            if(validator.valid()){
                console.log(validator);
                var firstName= $('.firstName').val();
                var lastName =$('.lastName').val();
                var email =$('.email').val();
                var cellphone =$('.cellphone').val();

                 $.ajax({
                type: 'POST',
                url: '{{route('lead.save')}}',
                data: {
                    _token: CSRF_TOKEN,
                    firstName: firstName,
                    lastName:lastName,
                    email:email,
                    cellphone:cellphone
                },
                dataType: 'JSON',
                success: function (response) {
                    if(response){
                        $("#carSpinner").css("display", "none");
                        $("#success").css("display", "block");
                        $("html, body").animate({ scrollTop: 0 }, "slow");

                       // console.log(response);
                    }
                },
                error: function (error) {
                    console.log(error.responseText);
                    $("#error").css("display", "block");
                    $('.error-message').text(error.responseJSON.message);
                    $("html, body").animate({ scrollTop: 0 }, "slow");
                }
            });
        }else{
             $("html, body").animate({ scrollTop: 0 }, "slow");
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
                    $('.error-message').text(error.responseJSON.message);
                    $("html, body").animate({ scrollTop: 0 }, "slow");

                }
            });

            }else{
                 $("html, body").animate({ scrollTop: 0 }, "slow");
            }

        });
    });
</script>

</body>
<!-- end::Body -->
</html>
