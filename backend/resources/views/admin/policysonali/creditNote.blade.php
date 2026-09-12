<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet"
    type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}"
    rel="stylesheet" type="text/css" />
<!-- begin::Body -->

<style>
    .th {
        width: 50%;
}
</style>
<body
    class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
<!-- begin:: Header Mobile -->
<div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed ">
    <div class="kt-header-mobile__logo">
        <a>
            <img alt="Logo" src="{{asset('images/logo.png')}}"/>
        </a>
    </div>
    <div class="kt-header-mobile__toolbar">
        <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left"
                id="kt_aside_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
        <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i
                class="flaticon-more"></i></button>
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
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">

                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title">
                            Credit Note
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet_body">
                    <div class="kt-section">
                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                            <form class="calculate kt-form" id="calculate">
                                <input type="hidden" id="premium" value="{{ $invoice_amount }}">
                                <input type="hidden" id="premium_freq" value="{{ $policy->premium_freq }}">
                                <input type="hidden" id="one_day_premium" value="{{ $one_day_premium }}">
                                <input type="hidden" id="calculate_transaction_effective_date" value="{{ Carbon::parse($ledger->invoice_date)->format('d/m/Y') }}">
                                <table class="table table-striped m-table">
                                    <tbody>
                                        <tr>
                                            <th>Policy Activated Date</th>
                                            <td>
                                                @if ($policy_activated_date != null)
                                                {!! \Carbon\Carbon::parse($policy_activated_date)->format('d/m/Y') !!}
                                                @else
                                                N/A
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Policy Cancelled Date</th>
                                            <td>
                                                @if ($policy_cancelled_date != null)
                                                {!! \Carbon\Carbon::parse($policy_cancelled_date)->format('d/m/Y') !!}
                                                @else
                                                N/A
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Premium</th>
                                            <td>
                                                @if ($policy && $policy->premium != null)
                                                P{!! number_format($policy->premium,2,'.',',') !!}
                                                @else
                                                N/A
                                                @endif
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>Premium Frequency</th>
                                            @if ($policy && $policy->premium_freq != null)
                                                @if ($policy->premium_freq == 1)
                                                <td>Monthly Instalments</td>
                                                @elseif ($policy->premium_freq == 2)
                                                <td>Three Instalments in a year</td>
                                                @elseif ($policy->premium_freq == 3)
                                                <td>Annual Instalments</td>
                                                @else
                                                <td>NA</td>
                                                @endif
                                            @else
                                                N/A
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Transaction Effective Date</th>
                                            <td>
                                                @if ($credit_note && $credit_note->transaction_effective_date != null)
                                                     {!! Carbon::parse($credit_note->transaction_effective_date)->format('d/m/Y') !!}
                                                @else
                                                    {!! Carbon::parse($ledger->invoice_date)->format('d/m/Y') !!}
                                                    <input type="hidden" class="form-control required kt_datepicker_1 validateGroup1"
                                                    name="transactionEffectiveDate" id="transactionEffectiveDate" autocomplete="off" placeholder="Transaction Effective Date" />
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Transaction End Date</th>
                                            <td>
                                                @if ($credit_note && $credit_note->transaction_end_date != null)
                                                {!! Carbon::parse($credit_note->transaction_end_date)->format('d/m/Y') !!}
                                                @else
                                                    <input type="text" class="form-control required kt_datepicker_2 validateGroup1"
                                                    name="transactionEndDate" id="transactionEndDate" autocomplete="off" placeholder="Select Transaction End Date" />
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Generate Credit Note for complete Term ?</th>
                                            <td>
                                                <div class="kt-radio-inline">
                                                    <label class="kt-radio" for="chkYes">
                                                        <input type="radio" class="yes_value" name="complete_term" id="chkYes" onclick="ShowHideDiv()" value="1" >Yes <span></span>
                                                    </label>
                                                    <label class="kt-radio" for="chkNo">
                                                        <input type="radio" class="yes_value" name="complete_term" id="chkNo" onclick="ShowHideDiv()" value="2" checked >No <span></span>
                                                    </label>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div id="total_month" style="padding: 5px;display: none">
                                    <div class="form-group row mb-4">
                                        <label  class="col-6 col-form-label" style="font-size: 14px;font-weight: bold; color: #212529;">For how many months do you want to generate the Credit Note ?</label>
                                        <div class="col-1">
                                        </div>
                                        <div class="col-5">
                                            <input type="text" required  class="form-control" id="months" name="months" value="1" placeholder="Please enter months">
                                        </div>
                                    </div>
                               </div>

                                <hr>
                                <div class="kt-form__actions">
                                    <div class="row" style="padding: 20px;">
                                        <div class="col-5"></div>
                                        <div class="col-7">
                                            <button type="button" value="Submit" id="calculateDays" class="btn btn-brand">Calculate</button>
                                            <div class="calculate_error"></div>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <form class="calculate kt-form" id="calculate_submit" enctype="multipart/form-data" action="{{ URL::to('admin/creditNoteStatementSonali',[$policy->id,$ledger->id]) }}"  method="POST">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                <input type="hidden" name="earned_premium" id="earned_premium" value="">
                                <input type="hidden" name="unearned_premium" id="unearned_premium" value="">
                                <input type="hidden" name="start_date" id="start_date" value="">
                                <input type="hidden" name="end_date" id="end_date" value="">
                                <input type="hidden" name="vat" id="vat" value="">
                                <input type="hidden" name="before_vat" id="before_vat" value="">
                                <input type="hidden" name="no_of_days" id="no_of_days" value="">
                                <table class="table table-striped m-table" id="old_calculation">
                                    <tbody>
                                        <tr>
                                            <th>No. of Active Days</th>
                                            <td>
                                                @if ($credit_note && $credit_note->no_of_days != null)
                                                    {!! $credit_note->no_of_days !!}
                                                @else
                                                    @if ($no_of_active_days != null)
                                                    {!! $no_of_active_days !!}
                                                    @else
                                                    N/A
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>One Day Premium</th>
                                            <td id="one_day_premium">
                                                @if ($one_day_premium != null)
                                                P {!! number_format($one_day_premium,2,'.',',') !!}
                                                @else
                                                N/A
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Earned Premium</th>
                                            <td>
                                                @if ($credit_note && $credit_note->earned_premium != null)
                                                   P {!! number_format($credit_note->earned_premium,2,'.',',') !!}
                                                @else
                                                    @if ($earned_premium != null)
                                                    P {!! number_format($earned_premium,2,'.',',') !!}
                                                    @else
                                                    N/A
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Unearned Premium</th>
                                            <td>
                                                @if ($credit_note && $credit_note->unearned_premium != null)
                                                    P {!! number_format($credit_note->unearned_premium,2,'.',',') !!}
                                                @else
                                                    @if ($unearned_premium != null)
                                                    P {!! number_format($unearned_premium,2,'.',',') !!}
                                                    @else
                                                    N/A
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table class="table table-striped m-table"  id="new_calculation" style="display: none">
                                    <tbody>
                                        <tr>
                                            <th>No. of Active Days </th>
                                             <td id="no_of_days_show"> </td>
                                        </tr>
                                        <tr>
                                            <th>One Day Premium </th>
                                             <td id="one_day_premium_show"> </td>
                                        </tr>
                                        <tr>
                                            <th>Earned Premium </th>
                                             <td id="earned_premium_show"> </td>
                                        </tr>
                                        <tr>
                                            <th>Unearned Premium </th>
                                             <td id="unearned_premium_show"> </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="kt-form__actions">
                                    <div class="row" style="padding: 15px;">
                                        <div class="col-3">
                                            @if ($credit_note && $credit_note->credit_note_file != null)
                                            <a class="btn btn-success" href="{!! \AlphaDirect\Helper::getCloudFrontURL($credit_note->credit_note_file) !!}" target="_blank">VIEW GENERATED DOCUMENT</a>
                                            @endif
                                        </div>
                                        <div class="col-3">
                                            @if ($credit_note && $credit_note->status == 1)
                                            <span></span>
                                            @else
                                            <button type="submit" id="generate_note" class="btn btn-primary">GENERATE CREDIT NOTE</button>
                                            @endif
                                        </div>
                                        <div class="col-3">
                                            <a class="btn btn-success" id="" href="{{ route('admin.policy.creditSendNoteMail',$policy->id) }}">SEND MAIL TO CUSTOMER</a>
                                        </div>
                                        <div class="col-3">
                                            <a class="btn btn-info" href="{{ url()->previous() }}">Back</a>
                                        </div>
                                    </div>
                               </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Portlet-->
        </div>
        <!-- end:: Content -->
    </div>
{{--   @endif --}}
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
<div id="kt_scrolltop" class="kt-scrolltop"><i class="la la-arrow-up"></i></div>
<!-- end:: Scrolltop -->


@include('admin.layouts.scripts')
<script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}"
        type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}"
        type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}"
        type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}"
        type="text/javascript"></script>

<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"
        type="text/javascript"></script>

<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}"
        type="text/javascript"></script>


<script type="text/javascript">
               $('.kt_datepicker_1').datepicker({
                    rtl: KTUtil.isRTL(),
                    todayHighlight: true,
                    orientation: "top left",
                    // templates: arrows,
                    format: 'dd/mm/yyyy',
                    //format: 'yyyy-mm-dd',
                    // endDate: "today",
                });
                $('.kt_datepicker_2').datepicker({
                    rtl: KTUtil.isRTL(),
                    todayHighlight: true,
                    orientation: "top left",
                    // templates: arrows,
                    startDate : '{!! Carbon::parse($ledger->invoice_date)->format("d/m/Y") !!}',
                    format: 'dd/mm/yyyy',
                });

                $("#calculate").validate({
                        // define validation rules
                        ignore: ":not(:visible)",
                        rules: {
                            transactionEffectiveDate:{
                                            required:true,
                                        },
                            transactionEndDate:{
                                            required:true,
                                        },
                        },
                        //display error alert on form submit
                        invalidHandler: function(event, validator) {
                            $('html, body').animate({
                                scrollTop: $(validator.errorList[0].element).offset().top - 200
                            }, 1000);
                        },

                    });

                function ShowHideDiv() {
                    var chkYes = document.getElementById("chkYes");
                    var total_month = document.getElementById("total_month");
                    total_month.style.display = chkYes.checked ? "block" : "none";
                }

                $("#calculateDays").on("click", function() {
                    $('#old_calculation').hide();
                    $('#new_calculation').show();

                        if(document.getElementById('chkYes').checked) {
                            var policy_premium = $("#premium").val();
                            var premium_freq = $("#premium_freq").val();
                            var months = $("#months").val();
                            var earned_premium = (policy_premium * months);
                            var earned_roundup = earned_premium.toFixed(2);
                            var date1 =  $("#calculate_transaction_effective_date").val();
                            if (premium_freq == 3){
                                var calculate_month = moment(date1, "DD-MM-YYYY").add(months, 'years').format('DD/MM/YYYY');
                            }else{
                                var calculate_month = moment(date1, "DD-MM-YYYY").add(months, 'months').format('DD/MM/YYYY');
                            }
                            var date2 = moment(calculate_month, "DD-MM-YYYY").subtract(1, 'days').format('DD/MM/YYYY');

                            var transStartDate = moment(date1, "DD/MM/YYYY");
                            var transEndDate = moment(date2, "DD/MM/YYYY");
                            var result = transEndDate.diff(transStartDate, 'days');

                            var one_day_premium = $("#one_day_premium").val();
                            var one_day_value = parseFloat(one_day_premium);
                            var one_day_roundup = one_day_value.toFixed(2);

                            //Commented by Sanket to match Earned premium with Policy Premium
                            //var earned_premium = (one_day_premium * result);
                            //var earned_roundup = earned_premium.toFixed(2);

                            var unearned_premium = policy_premium - earned_roundup;
                            var unearned_roundup = unearned_premium.toFixed(2);

                            var before_vat  = (earned_roundup / 1.14);
                            var before_vat_roundup = before_vat.toFixed(2);

                            var vat = (earned_roundup - before_vat);
                            var vat_roundup = vat.toFixed(2);

                            $('#earned_premium_show').html('P '+earned_roundup);
                            $('#no_of_days_show').html(result);
                            $('#one_day_premium_show').html('P '+one_day_roundup);
                            if(unearned_roundup < 0){
                                $('#unearned_premium_show').html(0);
                            }else{
                                $('#unearned_premium_show').html('P '+unearned_roundup);
                            }

                            $("#no_of_days").val(result);
                            $("#earned_premium").val(earned_roundup);
                            $("#unearned_premium").val(unearned_roundup);
                            $("#start_date").val(date1);
                            $("#end_date").val(date2);
                            $("#vat").val(vat_roundup);
                            $("#before_vat").val(before_vat_roundup);
                            $("#one_vat").val(before_vat_roundup);
                        }else{
                            if($("#calculate").valid()){
                                var date1 = {!! json_encode(Carbon::parse($ledger->invoice_date)->format("d/m/Y")) !!};
                                var date2 = $("#transactionEndDate").val();
                                var transStartDate = moment(date1, "DD/MM/YYYY");
                                var transEndDate = moment(date2, "DD/MM/YYYY");
                                var result = transEndDate.diff(transStartDate, 'days');

                                var policy_premium = $("#premium").val();

                                var one_day_premium = $("#one_day_premium").val();
                                var one_day_value = parseFloat(one_day_premium);
                                var one_day_roundup = one_day_value.toFixed(2);

                                var earned_premium = (one_day_premium * result);
                                var earned_roundup = earned_premium.toFixed(2);

                                var unearned_premium = policy_premium - earned_roundup;
                                var unearned_roundup = unearned_premium.toFixed(2);

                                var before_vat  = (earned_roundup / 1.14);
                                var before_vat_roundup = before_vat.toFixed(2);

                                var vat = (earned_roundup - before_vat);
                                var vat_roundup = vat.toFixed(2);

                                $("#no_of_days").val(result);
                                $("#earned_premium").val(earned_roundup);
                                $("#unearned_premium").val(unearned_roundup);
                                $("#start_date").val(date1);
                                $("#end_date").val(date2);
                                $("#vat").val(vat_roundup);
                                $("#before_vat").val(before_vat_roundup);
                                $("#one_vat").val(before_vat_roundup);

                                if(unearned_roundup < 0){
                                    $('.calculate_error').html('<span style="color: red">Your calculation is not valid</span>').show();
                                    $('#generate_note').hide();
                                }else{
                                    $('#generate_note').show();
                                    $('.calculate_error').html('<span style="color: red">Your calculation is not valid</span>').hide();
                                }

                                $('#no_of_days_show').html(result);
                                $('#one_day_premium_show').html('P '+one_day_roundup);
                                $('#earned_premium_show').html('P '+earned_roundup);
                                $('#unearned_premium_show').html('P '+unearned_roundup);

                                // var url = $('#creditNoteStatementurl').attr('href');
                                // var str = url +'?one_day_premium='+one_day_roundup+'&earned_premium='+earned_roundup+'&unearned_premium=' + unearned_roundup+'&start_date='+date1+'&end_date='+date2+'&vat='+vat_roundup+'&before_vat='+before_vat_roundup;
                                // $('#creditNoteStatementurl').attr('href', str);
                            }

                        }
                });
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js" type="text/javascript"></script>
</body>
<!-- end::Body -->
</html>

