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
                        View Quote
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Quotes</span> </a>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View Quote</span> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->



            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">

                <div class="row ol-lg-12">
                    <div class=" col-lg-3 text-left policyno">
                        <h5> Quote No<span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"  style="font-size:15px"> # {{$data->quoteNumber}}</span></h5> <br>
                    </div>

                    <div class=" col-lg-2 text-left policyno">
                       @if($agent != null)
                            <h5> Agent: {{ ucwords(strtolower($agent->firstName.' '.$agent->lastName)) }} <br>
                        @else
                                    <h5> Agent: N/A <br>
                        @endif

                    </div>

                    <div class=" col-lg-2 text-left policyno">
                        <h5> Status:
                            @if($data->quote_status == 2)
                                <span class="kt-badge  kt-badge--success kt-badge--inline kt-badge--pill"  style="font-size:15px"> Used</span>@if($policyNumber && $policyNumber->policyNumber != null) &nbsp({{ $policyNumber->policyNumber }})  @else &nbsp @endif <br>
                            @elseif($data->quote_status == 3)
                                <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"  style="font-size:15px"> Expired</span> <br>
                            @elseif($data->quote_status == 1)
                                <span class="kt-badge  kt-badge--info kt-badge--inline kt-badge--pill"  style="font-size:15px"> Active</span> <br>
                            @elseif($data->quote_status == 4)
                                <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"  style="font-size:15px"> Rejected</span> <br>
                            @else
                                <span class="kt-badge  kt-badge--danger kt-badge--inline kt-badge--pill"  style="font-size:15px"> Status not found</span> <br>
                            @endif
                    </div>


                    <div class=" col-lg-2 text-left policyno">
                        @if($data->quote_status != 2)
                            <h5> Expire on: {{$expiryDate}} <br>
                        @else
                                    <h5> Expire on: - <br>
                        @endif
                    </div>
                    @if($data->quote_status == 1)
                        <div class="col-lg-3 kt-subheader__wrapper">
                            <a href="{{ route('quote.download',[$data->quoteCode,'010']) }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Send quote in email to customer"> <span class="kt-opacity-11" id="">Send to customer</span>&nbsp; <i class="flaticon-attachment kt-padding-l-5 kt-padding-r-0"></i> </a>
                            <a href="{{ route('quote.download',[$data->quoteCode,'001']) }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Send quote in email to agent"> <span class="kt-opacity-11" id="">Send to agent</span>&nbsp; <i class="flaticon-attachment kt-padding-l-5 kt-padding-r-0"></i> </a>
                        </div>
                    @endif
                </div>
                <br>

                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">Customer Details
                                <span style="float: right">
                            <a href="{{ route('quote.download',[$data->quoteCode,'100']) }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Download"> <span class="kt-opacity-11" id="">Download</span>&nbsp; <i class="flaticon-download-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                                </span>
                            </h3>
                        </div>
                        <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                            <thead>
                            <tr>
                                <th>Name:</th>
                                <td width="50%">{{ ucwords(strtolower($data->firstName.' '.$data->middleName.' '.$data->lastName)) }}</td>
                            </tr>
                            <tr>
                                <th>Omang Number:</th>
                                @if($data->omang)
                                    <td width="50%">{{ $data->omang }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Passport Number:</th>
                                @if($data->passport)
                                    <td width="50%">{{ $data->passport }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Email:</th>
                                @if($data->email)
                                    <td width="50%">{{ $data->email }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Cellphone:</th>
                                @if($data->cellphone)
                                    <td width="50%">{{ $data->cellphone }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Gender:</th>
                                @if($data->gender == 0)
                                    <td width="50%">Female</td>
                                @elseif($data->gender == 1)
                                    <td width="50%">Male</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Date Of Birth:</th>
                                @if($data->dob)
                                    <td width="50%">{{ $data->dob }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Marital Status:</th>
                                @if($data->maritalstatus == 1)
                                    <td width="50%">Single</td>
                                @elseif($data->maritalstatus == 2)
                                    <td width="50%">Married</td>
                                @elseif($data->maritalstatus == 3)
                                    <td width="50%">Divorced</td>
                                @elseif($data->maritalstatus == 4)
                                    <td width="50%">Widowed</td>
                                @elseif($data->maritalstatus == 5)
                                    <td width="50%">Living Together(NOT Married)</td>
                                @elseif($data->maritalstatus == 6)
                                    <td width="50%">Living Separately(Married)</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>

                            <tr>
                                <th>Store Name:</th>
                                @if($storeName != null)
                                    <td width="50%">{{ $storeName }}</td>
                                @else
                                    <td width="50%">-</td>
                                @endif
                            </tr>

                            <tr>
                                <th>User IP Address:</th>
                                @if($data->userIPAddress)
                                    <td width="50%">{{ $data->userIPAddress }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Quote Sent:</th>
                                @if($data->quoteSent == 1)
                                    <td width="50%" style="color: green">Yes</td>
                                @else
                                    <td width="50%" style="color: red">No</td>
                                @endif
                            </tr>
                            </thead>
                        </table>
                        <!--end: Datatable -->
                    </div>
                    <div class="kt-portlet__body">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">Product Details</h3>
                        </div>
                        <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table2">
                            <thead>
                                <tr>
                                    <th>Product:</th>
                                    <td width="50%">{{ $data->name }}</td>
                                </tr>
                                <tr>
                                    <th>Product Plan:</th>
                                    <td width="50%">{{ $data->plan_name }}</td>
                                </tr>

                                <tr>
                                    <th>Sum Insured:</th>
                                    <td width="50%">P {{ $data->estimatedValue }}</td>
                                </tr>

                            </thead>
                        </table>
                        <!--end: Datatable -->
                    </div>

                    <div class="kt-portlet__body">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">Vehicle Details</h3>
                        </div>
                        <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table3">
                            <thead>
                            <tr>
                                <th>Make:</th>
                                <td width="50%">{{ ucwords(strtolower($data->make)) }}</td>
                            </tr>
                            <tr>
                                <th>Model:</th>
                                <td width="50%">{{ ucwords(strtolower($data->model)) }}</td>
                            </tr>
                            <tr>
                                <th>Manufacturing Year:</th>
                                <td width="50%">{{ $data->manufacturingYear }}</td>
                            </tr>
                            <tr>
                                <th>Japanese Import:</th>
                                @if($data->is_imported == 'Yes' || $data->is_imported == 'No')
                                    <td width="50%">{{ $data->is_imported }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Condition:</th>
                                <td width="50%">Excellent</td>
                            </tr>
                            <tr>
                                <th>Mileage:</th>
                                <td width="50%">Low</td>
                            </tr>
                            <tr>
                                <th>Estimated value of vehicle:</th>
                                <td width="50%">P {{ $data->estimatedValue }}</td>
                            </tr>
                            <tr>
                                <th>Number of prior accidents:</th>
                                <td width="50%">{{ $data->priorAccidents }}</td>
                            </tr>
                            <tr>
                                <th>Purpose:</th>
                                <td width="50%">Personal</td>
                            </tr>

                            </thead>
                        </table>
                        <!--end: Datatable -->
                    </div>
                    <div class="kt-portlet__body">
                        <h3 class="kt-portlet__head-title" style="display:inline">Premium Calculation Details</h3>
                        @can('quotes-Update Premium')
                            <div class="kt-portlet__head-label">
                                <a href="{{ route('quote.perDayPremium',$data->quoteNumber) }}" target="_blank" style="margin-left:1%;display:inline;float:right;" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Calculate per day premium"> <span class="kt-opacity-11" id="">Calculate Per Day Premium</span>&nbsp; <i class="flaticon-edit kt-padding-l-5 kt-padding-r-0"></i> </a>
                                <a href="{{ route('quote.viewHistory',$data->quoteNumber) }}" target="_blank" style="margin-left:1%;display:inline;float:right;" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Edit & Update History"> <span class="kt-opacity-11" id="">Premium Update History</span>&nbsp; <i class="flaticon-edit kt-padding-l-5 kt-padding-r-0"></i> </a>
                                @if($data->quote_status == 1)
                                    <p style="margin-left:1%;display:inline;float:right;" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="edit-premium-button" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Re-rate premium"> <span class="kt-opacity-11" id="">Edit Premium</span>&nbsp; <i class="flaticon-edit kt-padding-l-5 kt-padding-r-0"></i> </p>
                                @endif
                            </div>
                        @endcan
                        <div style="margin-bottom:2%" id="rerate_div">
                            <form id="updateCustomerKYC" action="{{ route('quote.updatePremium',$data->quoteNumber) }}"
                                  method="POST" enctype="multipart/form-data" class="kt-form">
                                <!-- CSRF Token -->
                                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <table class="table table-striped table-bordered table-hover table-checkable">
                                <thead>
                                <tr>
                                    <th>Please select type:</th>
                                    <td width="50%">
                                        <select class="form-control kt_selectpicker" name="type" title="Please select type" data-live-search="true" id="type">
                                            <option value="discount">Discount</option>
                                            <option value="surcharge">Surcharge</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Please select value type:</th>
                                    <td width="50%">
                                        <select class="form-control kt_selectpicker" name="value_type" title="Please select value type" data-live-search="true" id="value_type">
                                            <option value="1">Flat value</option>
                                            <option value="2">Percent(%) value</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Value:</th>
                                    <td width="50%">
                                        <input type="text" class="form-control" name="value" title="Enter the value" placeholder="Enter value"/>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Reason:</th>
                                    <td width="50%">
                                        <input type="text" class="form-control" name="reason" title="Please provide the reason" placeholder="Please provide reason"/>
                                    </td>
                                </tr>
                                </thead>
                            </table>
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-5"></div>
                                    <div class="col-7">
                                        <button type="submit" class="btn btn-info">Submit</button>
                                        <p class="btn btn-secondary" id="rerate_hide" style="margin-top:2%">Cancel</p>
                                    </div>
                                </div>
                            </div>
                            </form>
                        </div>

                        <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table3">
                            <thead>
                            <tr>
                                <th>Ratings Calculation Log ID:</th>
                                <td width="50%">@if($data->ratings_id) {{ $data->ratings_id }} @else N/A @endif</td>
                            </tr>
                            <tr>
                                <th>Monthly:</th>
                                <td width="50%">P {{ $data->premiumMonthly }}</td>
                            </tr>
                            <tr>
                                <th>3 Instalments:</th>
                                <td width="50%">P {{ $data->premium3Inst }}</td>
                            </tr>

                            <tr>
                                <th>Annual:</th>
                                <td width="50%">P {{ $data->premiumAnnually }}</td>
                            </tr>
                            <tr>
                                <th>Discount/Surcharge:</th>
                                @if($data->discount_surcharge != null && $data->discount_surcharge != 0)
                                    <td width="50%">P {{ $data->discount_surcharge.' ('. $data->percent_discount_surcharge .'%)' }}</td>
                                @else
                                    <td width="50%">-</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Ratings Premium Rate:</th>
                                @if($orig_rate > 2 && $orig_rate < 100)
                                    <td width="50%" style="color: green">{{ number_format((float)$orig_rate, 2, '.', '') }}%</td>
                                @else
                                    <td width="50%" style="color: red">{{ number_format((float)$orig_rate, 2, '.', '') }}%</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Premium Rate after discount/surcharge:</th>
                                @if($data->ratio != 0)
                                    @if($data->ratio > 2 && $data->ratio < 100)
                                        <td width="50%" style="color: green">{{ number_format((float)$data->ratio, 2, '.', '') }}%</td>
                                    @else
                                        <td width="50%" style="color: red">{{ number_format((float)$data->ratio, 2, '.', '') }}%</td>
                                    @endif
                                @else
                                    <td width="50%">-</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Reason:</th>
                                @if($reason != null)
                                    <td width="50%">{{ $reason }}</td>
                                @else
                                    <td width="50%">-</td>
                                @endif
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
                <div class="kt-portlet__foot kt-portlet__foot--solid">
                    @if($data->quote_status == 1)
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-5"></div>
                                <div class="col-7">
                                    <a class="btn btn-danger" href="{{ route('quote.reject',$data->quoteNumber) }}" >Reject</a>
                                    <a class="btn btn-secondary" href="{{ route('quote.index') }}" >Cancel</a>
                                </div>
                            </div>
                        </div>
                    @endif
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
<div class="modal fade" id="claimTypeModal" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Type of Claim</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
            </div>
            <div class="modal-body">
                <h5>What type of Claim you want to process ?</h5>
                <select name="type" class="form-control" id="type">
                    <option value="0">Please select claim type</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                <a href="" id="submitType" type="button" class="btn btn-brand">Confirm</a></div>
        </div>
    </div>
</div>
</div>
@include('admin.layouts.scripts')


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>
    $('#rerate_div').slideUp();
    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });

    });
    $("#policy_table").on("click", "a.claimTypeModal" , function(event) {
        event.preventDefault();

        $("#type").find("option:gt(0)").remove();
        var count = 0;
        var type;

        if($(this).is(".life")) {
            $("#type").append($("<option></option>").attr("value", "Life").text("Life"));
            count = count + 1;
            type = 'Life';
        }
        if($(this).is(".glass")) {
            $("#type").append($("<option></option>").attr("value", "Glass").text("Glass"));
            count = count + 1;
            type = 'Glass';
        }
        if($(this).is(".accident")) {
            $("#type").append($("<option></option>").attr("value", "Accident").text("Motor Accident"));
            count = count + 1;
            type = 'Accident';
        }

        //If Only one option then don't open modal pop up
        if(count == 1)
        {
            window.location.href = $(this).attr('href')+ '/' + type;
        } else {
            $("#submitType").attr("href", $(this).attr('href'));
            $('#claimTypeModal').modal('show');
        }
    });

    $('#type').on('change', function() {

    });

    $('#edit-premium-button').click(function(e) {
        $('#rerate_div').slideDown();
    });

    $('#rerate_hide').click(function(e) {
        $('#rerate_div').slideUp();
    });

    $('#submitType').click(function(e) {

        if($('#type').val() == 0)
        {
            alert('Please select Claim Type');
            return false;
        }
        var _href = $("#submitType").attr("href");
        $("#submitType").attr("href", _href + '/' + $('#type').val());

    });

    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
    });
    $('#policyStatus_filter').on('change',function(){
        policyStatus_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $('#product_filter').on('change',function(){
        product_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

</script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script>
    $(document).ready(function () {
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
    });
</script>


</body>
<!-- end::Body -->
</html>
