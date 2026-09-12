<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
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
    <!--If Password default, show edit details -->
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Add RealPay Payment
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Policies</span> </a>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit Policy</span> </a>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Renew Policy</span> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->

            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <div style="margin-bottom:2%" id="rerate_div">
                            <form id="policyForm1" action="{{ route('admin.policy.addOfflinePaymentPolicyRenewal') }}" method="POST"
                                enctype="multipart/form-data" class="kt-form">
                                <!-- CSRF Token -->
                                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                <input type="hidden" name="policyNumber" value="{{ $policy->policyNumber }}" />
                                <input type="hidden" name="policyID" value="{{ $policy->id }}" />
                                <input type="hidden" name="paymentMethod" value="Realpay" />
                                <input type="hidden" id="new_premium" name="new_premium" value="{{ $new_premium }}">
                                <input type="hidden" name="term_start_date" value="{{ $term_start_date }}">
                                <input type="hidden" name="agent_id" value="{{ $agent_id }}">
                                <input type="hidden" name="name" value="{{$name}}">
                                <input type="hidden" name="reinstate_type" value="{{ $reinstate_type }}">

                                <div class="kt-portlet__body" id="paymentInfoDiv">
                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Instalment Start Date :</label>
                                        <div class="col-9">
                                            <input type="text" class="form-control required kt_datepicker_1 validateGroup1"
                                                name="billingDay" autocomplete="off"
                                                placeholder="Select payment start date" title="Please select instalment start date"/>
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Payment Frequency :</label>
                                        <div class="col-9">
                                            <select id="paymentFreq" class="form-control required kt_selectpicker" title="Please select payment frequency" name="frequency">
                                                <option value="" selected disabled>Select payment frequency</option>
                                                <option value="1">Monthly Installments</option>
                                                <option value="2">Three Installments in a year</option>
                                                <option value="3">Annual Installment</option>
                                            </select>
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>

                                    <div class="form-group row" id="first_premium_row">
                                        <label  class="col-3 col-form-label">First Instalment Amount :</label>
                                        <div class="col-9">
                                            <input  class="form-control" name="first_premium" id="first_premium" title="Please provide premium" placeholder="Please provide premium">
                                            <span class="labelled" id="paymentNote" style="color: red;"></span>
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label  class="col-3 col-form-label">First Collection Date :</label>
                                        <div class="col-9">

                                            <input type="text" required class="form-control kt_datepicker_1 dob" name="first_collection_date" id="first_collection_date" autocomplete="off"  placeholder="Select date" title="Please select first collection date"/>

                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Please select bank :</label>
                                        <div class="col-9">
                                            <select id="RPBanks" class="form-control required kt_selectpicker"
                                                title="Please select bank" name="BankName">
                                                @foreach($banks as $bank)
                                                <option value="{{ $bank->bank_number }}">{{ $bank->bank_name }}</option>
                                                @endforeach

                                            </select>
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Please select bank branch :</label>
                                        <div class="col-9">
                                            <select id="RPBankBranch" class="form-control required kt_selectpicker"
                                                title="Please select bank branch" name="BranchCode">

                                            </select>
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>
                                    <input type="hidden" value="{{ $user->cellphone }}" name="billingCell" />
                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Account Type :</label>
                                        <div class="col-9">
                                            <select id="RPBankBranch" class="form-control required kt_selectpicker"
                                                title="Please select account type" name="accountType">
                                                <option value="1">Cheque</option>
                                                <option value="2">Savings</option>
                                            </select>
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Account Number :</label>
                                        <div class="col-9">
                                            <input class="form-control required validateGroup1" name="accountNumber" value=""
                                                title="Please provide account number"
                                                placeholder="Please provide account number">
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>

                                </div>
                                <div class="kt-portlet__foot kt-portlet__foot--solid">
                                    <div class="kt-form__actions">
                                        <div class="row">
                                            <div class="col-3"></div>
                                            <div class="col-9">
                                                <button type="submit" value="Submit" id="submitbtn"
                                                    class="btn btn-brand">Submit</button>
                                                <button class="btn btn-brand" type="button" id="loadBtn" style="display:none">
                                                    <span class="spinner-border spinner-border-sm" role="status"
                                                        aria-hidden="true"></span> Loading... </button>
                                                <a class="btn btn-secondary" href="{{ URL::previous() }}">Cancel</a>
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
@endif
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
<script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}"
        type="text/javascript"></script>
    <script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}"
        type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}"
        type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script>
    $('.kt_datepicker_1').datepicker({
        rtl: KTUtil.isRTL(),
        todayHighlight: true,
        orientation: "bottom left",
        // templates: arrows,
        format: 'yyyy-mm-dd',
        minDate : 0,
    });

    $(document).ready(function() {
        $('#submitbtn').on('click',function () {
            $("#loader").show();
        });
        // $("#RPBanks").selectpicker('refresh');
        // $("#paymentFreq").selectpicker('refresh');
    });

    $('#RPBanks').on('change', function() {
            var bank = $(this).val();
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: "{{ route('admin.policy.rpGetBranches') }}",
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "bank_id": bank
                    },
                    type: 'post',
                    datatype: 'json',
                    success: function(data) {
                        if (data) {
                            console.log(data);

                            $('#RPBankBranch').empty();
                            $.each(data.branches, function(index, value) {
                                $option = $('<option value="' + this.branch_id + '">' + this.name + '</option>');
                                $("#RPBankBranch").append($option);
                            });

                            $("#RPBankBranch").selectpicker('refresh');

                        } else {
                            $('#RPBankBranch').empty();
                        }
                    }
                });
            }, 100);
        })

    function addPaymentRealpayPay() {
        var isChecked = document.getElementById("addPaymentRealpay").checked;

        if (isChecked) {
            $('#paymentInfoDiv').slideDown();
            document.getElementById("paymentMsgRealpay").innerHTML = "Yes";
            document.getElementById("paymentMsgRealpay").style.color = "cornflowerblue";
        } else {
            $('#paymentInfoDiv').slideUp();
            document.getElementById("paymentMsgRealpay").innerHTML = "No";
            document.getElementById("paymentMsgRealpay").style.color = "#ff4d4d";
        }
    }

</script>
<script>
    $("#policyForm1").validate({
            ignore: [],
            ignore: ".ignore",
            // define validation rules
            rules: {
                billingDay: {
                    required: true
                },
                frequency: {
                    required: true
                },
                first_premium: {
                    required: true
                },
                first_collection_date: {
                    required: true
                },
                BankName: {
                    required: true
                },
                BranchCode: {
                    required: true
                },
                accountType: {
                    required: true
                },
                accountNumber: {
                    required: true
                },
            },
            // messages: {
            //     term_start_date: {
            //         required: 'Please select policy start date.'
            //     },
            //     agent_id: {
            //         required: 'Please select renewed by.'
            //     },
            // },

            //display error alert on form submit
            invalidHandler: function(event, validator) {
                $('html, body').animate({
                    scrollTop: $(validator.errorList[0].element).offset().top - 200
                }, 1000);
            },

            submitHandler: function(form) {
                form.submit(); // submit the form
            }
        });
</script>
<script>
    $('#paymentFreq').on('change', function() {
        // var paymentAmount = $('#first_premium').val();
        var paymentFreq = $('#paymentFreq').val();
        var premium = $('#new_premium').val();

        var baseURL = '{{ env('GRAPHITE_URL') }}';

        $.ajax({
            url: baseURL+'api/getPreminumForRealpayRenew',
            data: {
                "_token": "{{ csrf_token() }}",
                "paymentAmount": null,
                "paymentFreq": paymentFreq,
                "premium": premium,
            },
            type: 'post',
            datatype: 'json',
            beforeSend: function() {
                $("#loader").show();
            },
            success: function (data) {
                if (data.status == 200) {
                    $("#first_premium").val(data.new_premium);
                    $("#first_premium").prop("readonly", true);
                    $("#paymentNote").empty();
                    if (paymentFreq == 1) {
                        $("#paymentNote").text("Note: Monthly Installment amount will be " + data.new_premium);
                    } else if (paymentFreq == 2) {
                        $("#paymentNote").text("Note: All Three Installments amount will be " + data.new_premium);
                    } else if (paymentFreq == 3) {
                        $("#paymentNote").text("Note: Annual Installment amount is " + data.new_premium);
                    }
                }
            },
            complete: function (data) {
                $("#loader").hide();
            }
        });
    });
</script>
</body>
<!-- end::Body -->
</html>
