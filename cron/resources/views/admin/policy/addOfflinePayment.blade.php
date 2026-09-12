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
                        Add Offline Payment
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
                            <form id="policyForm1" action="{{ route('admin.policy.addOfflinePaymentPolicyRenewal') }}" method="POST" enctype="multipart/form-data" class="kt-form">
                                <!-- CSRF Token -->
                                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                <input type="hidden" name="policyNumber" value="{{ $policy->policyNumber }}" />
                                <input type="hidden" name="policy_id" value="{{ $policy->id }}" />
                                <input type="hidden" name="paymentMethod" value="Cash" />
                                <input type="hidden" id="new_premium" name="new_premium" value="{{ $new_premium }}">
                                <input type="hidden" name="term_start_date" value="{{ $term_start_date }}">
                                <input type="hidden" name="agent_id" value="{{ $agent_id }}">
                                <input type="hidden" name="name" value="{{$name}}">
                                <input type="hidden" name="reinstate_type" value="{{ $reinstate_type }}">


                                <div class="kt-portlet__body">
                                    @if(\Illuminate\Support\Facades\Auth::user()->hasPermissionTo('back-dated-transactions'))
                                        <div class="form-group row">
                                            <label class="col-3 col-form-label">Date of payment :</label>
                                            <div class="col-9">
                                                <input type="text" class="form-control kt_datepicker_1 required validateGroup1"
                                                    name="paymentDate" autocomplete="off" placeholder="Select date of payment" title="Please select date of payment" />
                                                <span class="form-text text-muted"></span>
                                            </div>
                                        </div>
                                    @else
                                        <p>Note : You can select Date of Payment for current month.</p>
                                        <div class="form-group row">
                                            <label class="col-3 col-form-label">Date of payment :</label>
                                            <div class="col-9">
                                                <input type="text" class="form-control required validateGroup1"
                                                id="date_of_refund_for_current_month" name="paymentDate" autocomplete="off" placeholder="Select date of payment" title="Please select date of payment"/>
                                                <span class="form-text text-muted"></span>
                                            </div>
                                        </div>
                                    @endif
                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Payment Amount :</label>
                                        <div class="col-9 payment-amount">
                                            <input class="form-control required validateGroup1 paymentAmountDiv" id="paymentAmount" name="paymentAmount" value="" title="Please provide amount" placeholder="Please provide amount">
                                            <span class="labelled" id="paymentNote" style="color: red;"></span>
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Payment Frequency :</label>
                                        <div class="col-9">
                                            <select id="paymentFreq" class="form-control required kt_selectpicker" title="Please select payment frequency" name="paymentFreq">
                                                <option value="" selected disabled>Select payment frequency</option>
                                                <option value="1">Monthly Installments</option>
                                                <option value="2">Three Installments in a year</option>
                                                <option value="3">Annual Installment</option>
                                            </select>
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Receipt number:</label>
                                        <div class="col-9">
                                            <input class="form-control required validateGroup1" name="receiptNumber" value="" title="Please provide payment receipt" placeholder="Please provide payment receipt number">
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Payment Recieved By :</label>
                                        <div class="col-9">
                                            <input class="form-control required validateGroup1" name="paymentRecievedBy" value="" title="Please provide contract sequence" placeholder="Please provide payment recipient">
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Numbers of Installments paid :</label>
                                        <div class="col-9">
                                            <input class="form-control required validateGroup1" name="numberOfInstalmentsPaid" value="" title="Please provide the number of Installments paid" placeholder="Please provide the number of Installments paid">
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>


                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Note :</label>
                                        <div class="col-9">
                                            <textarea class="form-control required validateGroup1" name="paymentNote" value="" title="Please provide note" placeholder="Please provide note"></textarea>
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="example-text-input" class="col-3 col-form-label">Upload Payment Proof</label>
                                        <div class="col-2">
                                            <div class="kt-avatar" id="product_image" style="float: left; clear: left;">

                                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>

                                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                                    <i class="fa fa-pen"></i>
                                                    <input type='file' name="payment_image" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                                </label>
                                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image">
                                                <i class="fa fa-times"></i> </span>
                                            </div>
                                        </div>

                                    </div>
                                    <div class="form-group row" id="addRealpaySwitch">
                                        <label for="example-text-input" class="col-3 col-form-label">Add payment on Realpay</label>
                                        <div class="col-9">
                                            <span class="kt-switch">
                                                 <label>
                                                    <input id="addPaymentRealpay" type="checkbox" name="addRealpay" value="1" onchange="addPaymentRealpayPay()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                            <h4 id="paymentMsgRealpay" style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">
                                                No</h4>

                                                </label>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="kt-portlet__body" id="paymentInfoDiv" style="margin-top:-50px !important;">
                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Installment Start Date :</label>
                                        <div class="col-9">
                                            <input type="text" class="form-control required kt_datepicker_1 validateGroup1" name="billingDay" autocomplete="off" placeholder="Select payment start date" />
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>
                                    {{-- <div class="form-group row" id="first_premium_row">
                                        <label  class="col-3 col-form-label">First Instalment Amount :</label>
                                        <div class="col-9">
                                            <input  class="form-control" name="first_premium" id="first_premium" title="Please provide premium" placeholder="Please provide premium">
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div> --}}
                                    {{-- <div class="form-group row">
                                        <label  class="col-3 col-form-label">First Collection Date :</label>
                                        <div class="col-9">

                                            <input type="text" required class="form-control kt_datepicker_1 dob" name="first_collection_date" id="first_collection_date" autocomplete="off"  placeholder="Select date" title="Please select first collection date"/>

                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div> --}}
                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Please select bank :</label>
                                        <div class="col-9">
                                            <select id="RPBanks" class="form-control required kt_selectpicker" title="Please select bank" name="RPBanks">
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
                                            <select id="RPBankBranch" class="form-control required kt_selectpicker" title="Please select bank branch" name="RPBankBranch">

                                            </select>
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>

                                    <input type="hidden" value="{{ $user->cellphone }}" name="billingCell" />
                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Account Type :</label>
                                        <div class="col-9">
                                            <select id="RPBankBranch" class="form-control required kt_selectpicker" title="Please select account type" name="accountType">
                                                <option value="1">Cheque</option>
                                                <option value="2">Savings</option>
                                            </select>
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-3 col-form-label">Account Number :</label>
                                        <div class="col-9">
                                            <input class="form-control required validateGroup1" name="accountNumber" value="" title="Please provide account number" placeholder="Please provide account number">
                                            <span class="form-text text-muted"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="kt-portlet__foot kt-portlet__foot--solid">
                                    <div class="kt-form__actions">
                                        <div class="row">
                                            <div class="col-3"></div>
                                            <div class="col-9">
                                                <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Submit</button>
                                                <button class="btn btn-brand" type="button" id="loadBtn" style="display:none"> <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
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
    var KTAvatarDemo = function() {
        return {
// Init demos
            init: function() {
                var avatar1 = new KTAvatar('product_image');
            }
        };
    }();

</script>
<script>
    $('.kt_datepicker_1').datepicker({
        rtl: KTUtil.isRTL(),
        todayHighlight: true,
        orientation: "bottom left",
        // templates: arrows,
        format: 'yyyy-mm-dd',
        minDate : 0,
    });

    jQuery(document).ready(function() {
        KTAvatarDemo.init();

    });

    $(document).ready(function() {
        // $('#submitbtn').on('click',function () {
        //     $("#loader").show();
        // });
        // KTAvatarDemo.init();
        $('#paymentInfoDiv').slideUp();
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
            ignore: ":hidden",
            // define validation rules
            rules: {
                paymentDate: {
                    required: true
                },
                paymentAmount: {
                    required: true
                },
                receiptNumber: {
                    required: true
                },
                paymentRecievedBy: {
                    required: true
                },
                numberOfInstallmentsPaid: {
                    required: true
                },
                paymentFreq: {
                    required: true
                },
                paymentNote: {
                    required: true
                },
                payment_image: {
                    required: true
                },
                paymentStartDate: {
                    required: true
                },
                RPBanks: {
                    required: true
                },
                RPBankBranch: {
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
                $("#loader").show();
                form.submit(); // submit the form
            }
        });
</script>
<script>
    $('#paymentFreq').on('change', function() {
        var paymentAmount = $('#paymentAmount').val();
        var paymentFreq = $('#paymentFreq').val();
        var premium = $('#new_premium').val();

        var baseURL = '{{ env('GRAPHITE_URL') }}';

        $.ajax({
            url: baseURL+'api/getPreminumForRenewPolicy',
            data: {
                "_token": "{{ csrf_token() }}",
                "paymentAmount": paymentAmount,
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
                    $("#paymentAmount").prop("readonly", false);
                    $("#paymentNote").empty();
                    $("#addRealpaySwitch").show();
                    if (paymentFreq == 1) {
                        $("#first_premium").val(data.amount);
                        $("#first_premium").prop("readonly", true);
                        $("#paymentNote").text("Note: First Installment amount is "+ paymentAmount + " , Second Installment amount is "+ data.amount + " and Regular Installment amount will be " + data.new_premium);
                    } else if (paymentFreq == 2) {
                        $("#first_premium").val(data.new_premium);
                        $("#first_premium").prop("readonly", true);
                        $("#paymentNote").text("Note: All Three Installments amount will be " + data.new_premium);
                    } else if (paymentFreq == 3) {
                        $("#addRealpaySwitch").hide();
                        $("#paymentInfoDiv").hide();
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
