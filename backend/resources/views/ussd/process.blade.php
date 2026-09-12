<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<!-- begin::Body -->
<body>
<!-- begin:: Header Mobile -->
<div class="container mt-3 pd-2 ">

    <div class="col bg-light">
    <div class="d-flex justify-content-center">
            <a ><img  alt="Logo" src="{{asset('images/logo.png')}}"/></a>
        </div>
            @if( $transaction == null || $transaction->status == 'FAILED' )
            <form id="policyForm" class="kt-form mt-2 mb-5" action="{{route('processPayment',base64_encode($paymentURL_ID))}}"  method="POST" enctype="multipart/form-data">
                        <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="form-group mt-3">
                            <ul>
                            @if($customer->profile->omang != NULL)
                                <li><label class="font-weight-bold">Omang Number : {{$customer->profile->omang}}</label></li>
                            @else
                                <li><label class="font-weight-bold">Passport Number: {{$customer->profile->passport}}</label></li>
                            @endif
                               <li><label class="font-weight-bold">Policy Number: {{$policy->policyNumber}}</label></li>
                               <li><label class="font-weight-bold">Premium Amount: BWP {{$premium}} (Vat included)</label></li>
                            </ul>
                    </div>
                    <div class="form-group">
                         <h6 class="kt-heading kt-heading--md">Product Name: {{$product->name}}
                        </h6>
                    </div>

                    <!-- If product is life display -->
                        @if($product->has_member == '1')
                            @if($product->has_subApplicant == '1')
                                 <div class="members_section" >
                                    <button type="button" id="addMember" class="form-control btn btn-primary mb-2"><i class="fas fa-plus-circle"></i>Add Family Members</button>
                                </div>
                            <div class="row" id="addMemberDiv" style="display: none;">
                                <div class="col-lg-12">
                                <h6 class="kt-heading kt-heading--md">
                                    Add Family Members:
                                </h6>
                                <div class="kt-repeater">
                                    <div data-repeater-list="members">
                                        <div data-repeater-item class="kt-repeater__item">
                                            <h6 class="kt-heading kt-heading--md kt-heading--no-top-margin">
                                                Member Info
                                            </h6>
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
                                            <div class="border border-dashed mb-2"></div>
                                            <div class="kt-repeater__add-data">
                                                <span data-repeater-create="" class="btn btn-info btn-sm mb-1 " > <i class="la la-plus"></i> Add More Members</span>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                            @endif
                           <!-- <div>
                                <button type="button" id="addBeneficiary" class="form-control btn btn-primary mb-2 "><i class="fas fa-plus-circle"></i>Add Beneficiary</button>
                            </div> -->

                            <div class="row" id="addBeneficiaryDiv" style="display:block;">
                                <div class="col-lg-12">
                                    <div class="kt-repeater">
                                        <div data-repeater-list="beneficiaries">
                                            <div data-repeater-item class="kt-repeater__item">
                                                <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin text-danger" >
                                               At least one Beneficiary Information is required*
                                                </h3>
                                                <div class="form-group{{ $errors->has('beneficiaries.*.beneficiaryRelation') ? ' has-error' : '' }}" id="beneficiaryRelation">
                                                    <div class="col-lg-12">
                                                        <label>Which beneficiary would you like to add ?(Specify relationship e.g Friend)</label>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control beneficiaryRelation" value="{{ old('beneficiaryRelation[]') }}" placeholder="Please specify your relation with the beneficiary"  name="beneficiaryRelation">

                                                        </div>
                                                        @if ($errors->has('beneficiaries.*.beneficiaryRelation'))
                                                        <span class="col-12 error">
                                                            <strong class="text text-danger">{{ $errors->first('beneficiaries.*.beneficiaryRelation') }}</strong>
                                                        </span>
                                                        @endif
                                                    </div>

                                                </div>
                                                <div class="form-group{{ $errors->has('beneficiaries.*.beneficiaryOmang') ? ' has-error' : '' }}">
                                                    <div class="col-lg-12">
                                                        <label>Omang ID</label>
                                                        <div class="input-group">
                                                            <input id="omang" type="text" class="form-control id-type beneficiaryOmang" value="{{ old('beneficiaries.*.beneficiaryOmang') }}" name="beneficiaryOmang" placeholder="Please enter omang number" pattern="[0-9]{9}" maxlength="9">
                                                        </div>

                                                        @if ($errors->has('beneficiaries.*.beneficiaryOmang'))
                                                        <span class="col-12 error">
                                                            <strong class="text text-danger">{{ $errors->first('beneficiaries.*.beneficiaryOmang') }}</strong>
                                                        </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="form-group{{ $errors->has('beneficiaries.*.beneficiaryPassport') ? ' has-error' : '' }}">
                                                    <div class="col-lg-12">
                                                        <label>Passport Number</label>
                                                        <div class="input-group">
                                                            <input id="passport" type="text" class="form-control id-type validateGroup1"  value="{{ old('beneficiaries.*.beneficiaryPassport') }}" name="beneficiaryPassport" placeholder="Please enter passport number" maxlength="12" >
                                                        </div>

                                                        @if ($errors->has('beneficiaries.*.beneficiaryPassport'))
                                                        <span class="col-12 error">
                                                            <strong class="text text-danger">{{ $errors->first('beneficiaries.*.beneficiaryPassport') }}</strong>
                                                        </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="form-group {{ $errors->has('beneficiaries.*.beneficiaryFName') ? ' has-error' : '' }}" id="beneficiaryFName">
                                                    <div class="col-lg-12">
                                                        <label>First name</label>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control beneficiaryFName" value="{{ old('beneficiaryFName[]') }}" placeholder="First name" name="beneficiaryFName">
                                                        </div>
                                                        @if ($errors->has('beneficiaries.*.beneficiaryFName'))
                                                        <span class="col-12 error">
                                                            <strong class="text text-danger">{{ $errors->first('beneficiaries.*.beneficiaryFName') }}</strong>
                                                        </span>
                                                        @endif

                                                    </div>

                                                </div>
                                                <div class="form-group{{ $errors->has('beneficiaries.*.beneficiaryLName') ? ' has-error' : '' }}"  id="beneficiaryFName">
                                                    <div class="col-lg-12">
                                                        <label>Last name</label>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control beneficiaryLName" value="{{ old('beneficiaryLName') }}" name="beneficiaryLName" placeholder="Last name">
                                                        </div>
                                                        @if ($errors->has('beneficiaries.*.beneficiaryLName'))
                                                        <span class="col-12 help-block">
                                                            <strong class="text text-danger">{{ $errors->first('beneficiaries.*.beneficiaryLName') }}</strong>
                                                        </span>
                                                        @endif


                                                    </div>

                                                </div>
                                                <div class="form-group{{ $errors->has('beneficiaries.*.beneficiaryDOB') ? ' has-error' : '' }}">
                                                    <div class="col-lg-12">
                                                        <label>Date Of Birth</label>
                                                        <input type="text" class="form-control kt_datepicker_1 beneficiaryDOB" name="beneficiaryDOB" autocomplete="off" placeholder="Select date"/>
                                                    </div>

                                                    @if ($errors->has('beneficiaries.*.beneficiaryDOB'))
                                                        <span class="col-12 help-block">
                                                            <strong class="text text-danger">{{ $errors->first('beneficiaries.*.beneficiaryDOB') }}</strong>
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="form-group{{ $errors->has('beneficiaries.*.beneficiaryGender') ? ' has-error' : '' }}">
                                                    <div class="col-lg-12">
                                                        <label>Gender</label>
                                                        <div class="kt-radio-inline">
                                                            <label class="kt-radio">
                                                                <input type="radio" name="beneficiaryGender" value="1" class="beneficiaryGender">
                                                                Male <span></span>
                                                            </label>
                                                            <label class="kt-radio">
                                                                <input type="radio" name="beneficiaryGender" class="beneficiaryGender" value="0">
                                                                Female<span></span>
                                                            </label>
                                                        </div>

                                                        @if ($errors->has('beneficiaries.*.beneficiaryGender'))
                                                        <span class="col-12 help-block">
                                                            <strong class="text text-danger">{{ $errors->first('beneficiaries.*.beneficiaryGender') }}</strong>
                                                        </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="form-group{{ $errors->has('beneficiaries.*.beneficiaryPayment') ? ' has-error' : '' }}">
                                                    <div class="col-lg-12">
                                                        <label>Payment(%)</label>
                                                        <div class="input-group">
                                                            <input type="number"  class="form-control beneficiaryPayment" name="beneficiaryPayment" placeholder="Payment">
                                                        </div>
                                                         <p class="text text-danger beneficiaryPaymentError mb-2" style="display:none;">This field is required,NB:Total of Payments for all Beneficiaries cannot be more than 100</p>
                                                         @if ($errors->has('beneficiaries.*.beneficiaryPayment'))
                                                         <span class="col-12 help-block">
                                                             <strong class="text text-danger">{{ $errors->first('beneficiaries.*.beneficiaryPayment') }}</strong>
                                                         </span>
                                                     @endif
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
                                            <span data-repeater-create="" class="btn btn-brand btn-sm " > <i class="la la-plus"></i> Add Beneficiary </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        @else
                        <div class="row">


                        </div>
                        @endif
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <h3 class="kt-heading kt-heading--md">
                             Billing Details:
                        </h3>
                        <div class="row">
                            <div id="billingMethodField" class="col-lg-6" >
                                <div class="form-group">
                                    <label for="exampleSelect1">Billing Method:</label>
                                    <select id="billing" class="form-control kt_selectpicker" title="Available billing methods" name="billingMethod" id="exampleSelect1" >
                                        @foreach($vendors as $vendor)
                                                 <option value="{{$vendor->vendorName}}">{{$vendor->vendorLabel}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @if($vendor->vendorName == 'Orange' && $vendor->status == 1 )
                            <div id="billingCellField" class="col-lg-6">
                                <div class="form-group">
                                    <label>Myzaka/Orange Money Cell:</label>
                                    <input id="billingCell" type="text" class="form-control" name="billingCell" aria-describedby="emailHelp" title="Billing number should only have 8 numbers" placeholder="Myzaka/Orange Cell" value="{{old('billingCell')}}" minlength="8" maxlength="8" pattern="[0-9]{8}">
                                </div>
                            </div>
                            @endif
                        </div>
                        <input id="billingOptionHidden" type="hidden" class="form-control" name="billingOption">
                        @if($vendor->vendorName == 'RealPay' && $vendor->status == 1 )
                        <div class="row">
                            <div id="bankNameField" class="col-lg-6">
                                <div class="form-group">
                                    <label for="exampleSelect1">Bank Name:</label>

                                    <div id="bankNameSpinner" style="display:none;" class="form-group kt-spinner kt-spinner--sm kt-spinner--success kt-spinner--left kt-spinner--input"></div>
                                    <select id="bankNumDropDown" class="form-control kt_selectpicker" title="Please select bank" name="bankName" ></select>
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
                                    <div id="bankBranchSpinner" style="display:none;" class="form-group kt-spinner kt-spinner--sm kt-spinner--success kt-spinner--left kt-spinner--input"></div>
                                    <select id="bankBranchDropDown" class="form-control kt_selectpicker" title="Please select branch" name="branchCode"></select>
                                </div>
                            </div>
                            <div id="accountNumberField" class="col-lg-6">
                                <div class="form-group">
                                    <label>Account Number:</label>
                                    <input id="accountNumber" type="text" class="form-control" name="accountNumber" title="Please enter account number" value="{{old('accountNumber')}}" aria-describedby="emailHelp" placeholder="Account Number" pattern="[0-9]{1,15}"  maxlength="15">
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
                        </div>
                        @endif

                        <div class="row">

                            <div class="col-12">
                                <button type="submit" value="Submit" class="form-control btn btn-block text text-light submit" style="background-color:#FD7E0B;">Submit</button>
                            </div>

                        </div>
                    </form>
                    @elseif($transaction->status == 'SUCCESS')
                     <div class="row bg-success mt-5">
                            <div class="col text-light">
                                <h1 class="text text-center">THANK YOU</h1>
                                <h1 class="text text-center"><i class="far fa-check-circle"></i></h1>
                                <p class="text text-center"><strong>Transaction for this Policy has been processed</strong>  </p><hr><p>
                            </div>

                    </div>

                    @endif



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

$(document).ready(function () {
    KTFormControls.init();
});
</script>
<script>
  "use strict";
    // Class definition
    var KTFormControls = function () {
// Private functions
        jQuery.validator.addMethod("future", function(value, element) {
            return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
        }, "Please enter only past dates");

        jQuery.validator.addMethod(
                "sum",
                function (value, element, params) {
                    var sumOfVals = 0;
                    $('.beneficiaryPayment').each(function() {
                        sumOfVals += Number($(this).val());
                    });
                    if (sumOfVals <= params) return true;
                    return false;
                },
                'Total of Payments for all Beneficiaries cannot be more than 100'
        );

        var demo1 = function () {
            $( "#policyForm" ).validate({
                ignore: [],
                rules:{
                    'beneficiaries.beneficiaryGender':{
                        required:true
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
                messages:{
                    'beneficiaries.beneficiaryGender':{
                        required:"Please select gender"
                    }
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
</script>



<script>

    $('#addMember').click(function() {
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
    $('.kt-repeater__add-data').on('click','.btn-brand', function(){
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




</script>



<script >
    $('#billing').on('change',function(){
        var options = $('#billing').val();
        switch(options){
            case 'Orange':
                $("#billing").selectpicker('refresh');
                $("#billingOptionHidden").val(options);
                $('#bankNumDropDown').prop("hide",true);
                $('#branchDropdown').hide();
                $('#accountNumberField').hide();
                $('#accountTypeField').hide();
                $('#bankNameField').hide();
                $('#bankBranchField').hide();
                $('#billingCell').prop("required",true);
                $("#bankNameSpinner").css("display", "none");
                $('#billingCellField').show();
                $('#branchDropdown').rules('remove',  'required');
                $('#bankNameDropdown').rules('remove',  'required');
                $('#accountNumberField').rules('remove',  'required');
                $('#billingCell').rules('remove',  'required');
                $('#billingCellField').addClass('required');
                $("#billingMethodField").removeClass( 'col-lg-12' ).addClass( 'col-lg-6' );

                break;
            case 'VCS':
                $("#billing").selectpicker('refresh');
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
                $('#accountNumberField').rules('remove',  'required');beneficiaryFName
                $('#billingCellField').removeClass('required');
                $("#billingMethodField").removeClass( 'col-lg-6' ).addClass( 'col-lg-12' );
                break;
            case 'RealPay':
                $("#billing").selectpicker('refresh');
                $("#billingOptionHidden").val(options);
                $('#branchDropdown').show();
                $('#bankNameDropdown').show();
                $('#accountNumberField').show();
                $('#accountTypeField').show();
                $('#bankNameField').show();
                $('#bankBranchField').show();
                $('#billingCellField').hide();
                $("#billingMethodField").removeClass( 'col-lg-6' ).addClass( 'col-lg-12' );
                /* $('#accountNumber').prop("required",true);*/
                $('#accountType').prop("required",true);
                $('#bankBranchDropDown').prop("required",true);
                $('#bankNumDropDown').prop("required",true);
                $('#accountType').prop("required",true);
                $('#accountTypeDropdown').prop("disabled",true);
                $('#billingCellField').removeClass('required');
                $("#billingMethodField").removeClass( 'col-lg-12' ).addClass( 'col-lg-6' );

                let branches = [];
                $("#bankNameSpinner").css("display", "contents");
                var urlValue = '{{ \Config::get('values.graphite_url') }}'
                console.log(urlValue);

                $.ajax({
                    /* the route pointing to the post function */

                    url: urlValue+'realpay/getBanks',
                    type: 'GET',
                    /* send the csrf-token and the input to the controller */
                    data: {},
                    beforeSend: function(){
                         // Show image container
                         $("#bankNameSpinner").css('display', 'block');

                        },
                    cors: true ,
                    dataType: 'JSON',
                    /* remind that 'data' is the response of the AjaxController */
                    success: function (data) {
                        if (data){

                             $("#bankNameSpinner").css("display", "none");
                            $.each(data, function(key, value){
                                console.log('bank name' + value["ns0:bankNum"],'bank desc'+ value["ns0:bankDesc"] );
                                $('#bankNumDropDown').append('<option value="' + value["ns0:bankNum"] + '">' + value["ns0:bankDesc"] + '</option>');
                                $("#bankNumDropDown").selectpicker('refresh');

                            });

                        }
                    }
                });
                console.log('loading branches');
                $("#bankNumDropDown").change(function(){
                    const bankNum = $(this).val();beneficiaryFName
                    let filterArray = [];
                    $.ajax({
                        /* the route pointing to the post function */
                        url: urlValue+'realpay/getBankBranches',
                        type: 'GET',
                        /* send the csrf-token and the input to the controller */
                        data: {},
                        beforeSend: function(){
                         // Show image container
                         $("#bankBranchSpinner").css('display', 'block');

                        },
                        dataType: 'JSON',
                        /* remind that 'data' is the response of the AjaxController */
                        success: function (data) {
                            if (data) {

                                $("#bankBranchSpinner").css("display", "none");
                                console.log(data)
                                branches = data;
                                branches.forEach((bank) => {
                                    if (bank['ns0:bankNum'] == bankNum) {
                                    filterArray.push(bank);
                                    }
                                 });
                                 $("#bankBranchDropDown").empty();
                                filterArray.forEach((value) => {
                                 console.log(value["ns0:bankBranchDesc"] );
                                 $("#bankBranchDropDown").append('<option value="' + value["ns0:bankBranchNum"] + '">' + value["ns0:bankBranchDesc"] + '</option>');
                                 $("#bankBranchDropDown").selectpicker('refresh');
                                });
                            }
                        },
                        error:function(e){
                            $("#bankBranchSpinner").css("display", "none");
                            console.log(e);
                        },

                    });

                });
                break;
            default:
                console.log('default Reached');
                break;
        }
    });
</script>

<script>
    $(document).ready(function () {
        var universalValid = true;

        function scrollToInvalidDiv(valid){
                if(valid == false){
                    $('html, body').animate({
                     scrollTop: $('#beneficiaryRelation').offset().top
                     },3000);

                }
            }

        $('.submit').click(function (e) {
            $('#policyForm').validate();
            console.log('click 1');
            var totalShare = 0;
            var sum = 100;
                //for dynamic fields


            $('.beneficiaryPayment').each(function(){

                $(this).rules('add', {
                    required: true,sum:true,
                     messages:
                     {
                        required: "Please enter Beneficiary Payment",


                    }
                });

            });
            $('.beneficiaryRelation').each(function () {
                // element == this
                $(this).rules('add', {
                    required: true,lettersonly:true, maxlength: 25,
                     messages:
                     {
                        required: "Please enter Beneficiary Relation",
                        lettersonly: "Sorry!! we accept characters only, no spaces, numbers or symbols",
                        maxlength: "Oops,First Name exceeds length, can you shorten it?",

                    }
                });

            });

            $('.beneficiaryOmang').each(function (index, element) {
                // element == this
                $(this).rules('add', {required: true,
                 messages: {
                     required: "Please enter either Beneficiary Omang or Passport Number",
                        }});

            });
            $('.beneficiaryFName').each(function () {
                 // element == this
                 $(this).rules('add', {required: true,lettersonly:true, maxlength: 25,
                  messages: {
                     required: "Please enter Beneficiary Firstname",
                     lettersonly: "Sorry!! we accept characters only, no spaces, numbers or symbols",
                    maxlength: "Oops,First Name exceeds length, can you shorten it?",
                     }});

            });
            $('.beneficiaryLName').each(function () {
                 // element == this
                 $(this).rules('add', {required: true,required: true,lettersonly:true, maxlength: 25,
                  messages: {
                      required: "Please enter Beneficiary Lastname",
                      lettersonly: "Sorry!! we accept characters only, no spaces, numbers or symbols",
                    maxlength: "Oops,First Name exceeds length, can you shorten it?",
                    }});

            });
            $('.beneficiaryDOB').each(function () {
                // element == this
                $(this).rules('add', {required: true, future: true,
                 messages: {required: "Please enter Beneficiary Date of Birth"}});

            });
            $(".beneficiaryGender").each(function (){
                // element == this
                $(this).rules('add', {required: true,
                 messages: {required: "Please select Beneficiary Gender"}});

            });


        });

        $('.kt-repeater__add-data').on('click','.btn-brand', function(){

            jQuery.validator.addMethod("sum",function (value, element, params) {
                var sumOfVals = 0;
                    $('.beneficiaryPayment').each(function() {
                     sumOfVals += Number($(this).val());
                    });
                    if (sumOfVals <= params)
                        return true;

                return false;
                },
                'Total of Payments for all Beneficiaries cannot be more than 100'
            );


        });


    });

    </script>

</body>

<!-- end::Body -->
</html>
