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
            <form id="paymentForm" action="{{route('processGraphitePayment',base64_encode($paymentUrl->id))}}" method="post" class="kt-form mt-2" enctype="multipart/form-data" >
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="form-group mt-3">
                            <ul>
                            @if($customer->profile->omang != NULL)
                                <li><label class="font-weight-bold">Omang Number : {{$customer->profile->omang}}</label></li>
                            @else
                                <li><label class="font-weight-bold">Passport Number : {{$customer->profile->passport}}</label></li>
                            @endif
                               <li><label class="font-weight-bold">Policy Number : {{$policy->policyNumber}}</label></li>
                               <li><label class="font-weight-bold">Amount :P {{$paymentUrl->amount}}</label></li>
                            </ul>
                    </div>
                    <div class="form-group">
                         <h6 class="kt-heading kt-heading--md">Product Name: {{$product->name}}</h6>
                    </div>
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <h3 class="kt-heading kt-heading--md">
                             Billing Details:
                        </h3>
                        <div class="row">
                            <div id="billingMethodField" class="col-lg-6">
                                <div class="form-group">
                                    <label for="exampleSelect1">Billing Method:</label>
                                    <select id="billing" class="form-control kt_selectpicker" title="Available billing methods" name="billingMethod" id="exampleSelect1" >
                                        @foreach($vendors as $vendor)
                                        <option value="{{$vendor->vendorName}}">{{$vendor->vendorLabel}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div id="billingCellField" class="col-lg-6">
                                <div class="form-group">
                                    <label>Myzaka/Orange Money Cell:</label>
                                    <input id="billingCell" type="text" class="form-control" name="billingCell" aria-describedby="emailHelp" title="Billing number should only have 8 numbers" placeholder="Myzaka/Orange Cell" value="{{old('billingCell')}}" minlength="8" maxlength="8" pattern="[0-9]{8}">
                                </div>
                            </div>
                        </div>
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
                            <div class="col-12"> @include('includes.kt-loader')
                                <button type="submit" id="Submit" value="Submit" class="form-control btn btn-block text text-light" style="background-color:#FD7E0B;">Submit</button>
                            </div>
                        </div>
                    </form>
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
        var demo1 = function () {
            $( "#paymentForm" ).validate({
                ignore: [],
                rules:{
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
                $('#accountNumberField').rules('remove',  'required');
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
                var urlValue = '{{ \Config::get('values.graphite_url') }}' 
                $("#bankNumDropDown").change(function(){
                    const bankNum = $(this).val();
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

</body>

<!-- end::Body -->
</html>
