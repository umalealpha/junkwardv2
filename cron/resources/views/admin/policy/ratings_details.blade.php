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
                        View Premium Rating Data
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Policy</span> </a>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit Policy</span> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->



            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">

                <div class="row ol-lg-12">
                <br>
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">Customer Details</h3>
                        </div>
                        <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                            <thead>
                            <tr>
                                <th>Gender:</th>
                                @if($data->customer_gender == 0)
                                    <td width="50%">Female</td>
                                @elseif($data->customer_gender == 1)
                                    <td width="50%">Male</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Marital Status:</th>
                                @if($data->customer_marital_status == 1)
                                    <td width="50%">Single</td>
                                @elseif($data->customer_marital_status == 2)
                                    <td width="50%">Married</td>
                                @elseif($data->customer_marital_status == 3)
                                    <td width="50%">Divorced</td>
                                @elseif($data->customer_marital_status == 4)
                                    <td width="50%">Widowed</td>
                                @elseif($data->customer_marital_status == 5)
                                    <td width="50%">Living Together(NOT Married)</td>
                                @elseif($data->customer_marital_status == 6)
                                    <td width="50%">Living Separately(Married)</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Date of birth:</th>
                                @if($data->customer_dob)
                                    <td width="50%">{{ $data->customer_dob }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
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
                                <td width="50%">{{ $data->manufacturing_year }}</td>
                            </tr>
                            <tr>
                                <th>Japanese Import:</th>
                                @if($data->japnese_import == 'Yes' || $data->is_imported == 'No')
                                    <td width="50%">{{ $data->japnese_import }}</td>
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
                                <td width="50%">P {{ $data->sum_assured }}</td>
                            </tr>
                            <tr>
                                <th>Number of prior accidents:</th>
                                <td width="50%">{{ $data->claim_count }}</td>
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

                        <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table3">
                            <thead>
                            <tr>
                                <th>Ratings Calculation Log ID:</th>
                                <td width="50%">@if($data->ratings_id) {{ $data->ratings_id }} @else N/A @endif</td>
                            </tr>
                            <tr>
                                <th>Monthly:</th>
                                <td width="50%">P {{ $data->month_ins }}</td>
                            </tr>
                            <tr>
                                <th>3 Instalments:</th>
                                <td width="50%">P {{ $data->three_ins }}</td>
                            </tr>

                            <tr>
                                <th>Annual:</th>
                                <td width="50%">P {{ $data->annual_ins }}</td>
                            </tr>
                            <tr>
                                <th>Discount:</th>
                                @if($data->discount != null && $data->discount != 0)
                                    <td width="50%">P {{ $data->discount.' ('. $data->discount .'%)' }}</td>
                                @else
                                    <td width="50%">-</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Surcharge:</th>
                                @if($data->surcharge != null && $data->surcharge != 0)
                                    <td width="50%">P {{ $data->surcharge.' ('. $data->surcharge .'%)' }}</td>
                                @else
                                    <td width="50%">-</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Ratings Premium Rate:</th>
{{--                                @if($orig_rate > 2 && $orig_rate < 100)--}}
                                    <td width="50%" style="color: green">{{ number_format((float)($data->annual_ins / $data->sum_assured) * 100, 2, '.', '') }}%</td>
{{--                                @else--}}
{{--                                    <td width="50%" style="color: red">{{ number_format((float)$orig_rate, 2, '.', '') }}%</td>--}}
{{--                                @endif--}}
                            </tr>
{{--                            <tr>--}}
{{--                                <th>Premium Rate after discount/surcharge:</th>--}}
{{--                                @if($data->ratio != 0)--}}
{{--                                    @if($data->ratio > 2 && $data->ratio < 100)--}}
{{--                                        <td width="50%" style="color: green">{{ number_format((float)$data->ratio, 2, '.', '') }}%</td>--}}
{{--                                    @else--}}
{{--                                        <td width="50%" style="color: red">{{ number_format((float)$data->ratio, 2, '.', '') }}%</td>--}}
{{--                                    @endif--}}
{{--                                @else--}}
{{--                                    <td width="50%">-</td>--}}
{{--                                @endif--}}
{{--                            </tr>--}}
{{--                            <tr>--}}
{{--                                <th>Reason:</th>--}}
{{--                                @if($reason != null)--}}
{{--                                    <td width="50%">{{ $reason }}</td>--}}
{{--                                @else--}}
{{--                                    <td width="50%">-</td>--}}
{{--                                @endif--}}
{{--                            </tr>--}}
                            </thead>
                        </table>
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


</body>
<!-- end::Body -->
</html>
