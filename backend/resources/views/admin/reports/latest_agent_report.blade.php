<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="https://cdn.datatables.net/buttons/1.6.0/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css" />
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<style>

    .FontColorBlue{
        color:cornflowerblue;
    }
    button.dt-button{
        background-color: red !important;
    }
</style>
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
    <!-- check if is first time login -->
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Agent Report
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a href="{{Route('admin.policy.index')}}" class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Report</span> </a>
                    </div>
                </div>
                <div class="kt-subheader__toolbar">
                    <div class="row">
                    <div class="col-sm-3">
                        {{-- <a href="{{url('admin/report/agent-report-data-export')}}" type="button" class="btn btn-primary">Export</a> --}}
                    </div>
                </div>
                    {{-- <div class="kt-subheader__wrapper">
                         <a href="{{ URL::to('admin/policy/create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                     </div>--}}
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <!--begin: Filter -->
                        <div class="row">
                            <div class="col-sm-3">
                                <label>Filter By Product</label>
                                <select name="product_filter" class="form-control" id="product_filter">
                                    <option value="-1">All</option>
                                    @foreach($products as $product)
                                        {{-- @if ($product->id == 2) --}}
                                            <option style="text-transform: capitalize" value="{!! $product->id !!}">{!! $product->name !!}</option>
                                        {{-- @endif --}}
                                    @endforeach
                                </select>
                            </div>
                            {{-- <div class="col-sm-3">
                                <label>Filter By Product Plan</label>
                                <select name="product_plan" class="form-control" id="product_plan">
                                    <option value="-1">All</option>
                                    @foreach($productPlans as $productPlan)
                                        @if ($productPlan->id == 2)
                                            <option style="text-transform: capitalize" value="{!! $productPlan->id !!}">{!! $productPlan->name !!}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div> --}}
                            <div class="col-sm-3">
                                <label>Filter By date from</label>
                                <input type="text" class="form-control  kt_datepicker_1 restrictDate"  id="filterDateFrom" placeholder="Select date from" name="filterDateFrom" autocomplete="off">
                            </div>
                            <div class="col-sm-3">
                                <label>Filter By date to</label>
                                <input type="text" class="form-control  kt_datepicker_1 restrictDate"  id="filterDateto" placeholder="Select date to" name="filterDateto" autocomplete="off">
                            </div>
                            <div class="col-sm-3">
                                <label>Filter By Agent</label>
                                <select name="agent_id" class="form-control" id="agent_id">
                                    <option value="-1">All</option>
                                    @foreach ($policyAgents as $agent)
                                        @if($agent->user != null)
                                            <option style="text-transform: capitalize" value="{!! $agent->agent_id !!}">{!! $agent->user->firstName !!} {!! $agent->user->lastName  !!}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>        
                        </div>
                        <br>
                        <!--end: Filter -->
                        <!--begin: Datatable -->
                        <table class="table table-striped table-bordered table-hover table-checkable mt-2" id="exportTable">
                            <thead>
                            <tr>
                                <th>Agent Name</th>
                                <th>P1 Million Accidental Death Insurance</th>
                                <th>Third Party Car Insurance</th>
                                <th>Motor Comprehensive</th>
                                <th>Mobile and electronic device insurance</th>
                                {{-- <th>Store Name</th> --}}
                                {{-- <th>Product Name</th> --}}
                                {{-- <th>Product Plan</th> --}}
                                <th>Policy Count</th>
                            </tr>
                            </thead>
                        </table>
                        {{--<table class="table table-striped table-bordered table-hover table-checkable mt-2" id="policyExportTable" style="display: none;">--}}
                            {{--<thead>--}}
                            {{--<tr>--}}
                                {{--<th>Policy Number</th>--}}
                                {{--<th>Product</th>--}}
                                {{--<th>Product Plan</th>--}}
                                {{--<th>Premium Value</th>--}}
                                {{--<th>Agent</th>--}}
                                {{--<th>Reference Number</th>--}}
                                {{--<th>Transaction Type</th>--}}
                                {{--<th>Transaction</th>--}}
                                {{--<th>Transaction Reference</th>--}}
                                {{--<th>Date Of Transaction</th>--}}
                                {{--<th>Time Of Transaction</th>--}}
                            {{--</tr>--}}
                            {{--</thead>--}}
                        {{--</table>--}}
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>
            <!-- end:: Content -->
        </div>
@endif
<!-- begin:: Footer -->
@include('includes.footer')
<!-- end:: Footer -->
</div>
<!-- end:: Wrapper -->

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
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="https://cdn.datatables.net/buttons/1.6.0/js/dataTables.buttons.min.js" ></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.0/js/buttons.html5.min.js"></script>


<script>

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

    $('#submitType').click(function(e) {

        if($('#type').val() == 0)
        {
            alert('Please select Claim Type');
            return false;
        }
        var _href = $("#submitType").attr("href");
        $("#submitType").attr("href", _href + '/' + $('#type').val());

    });

    "use strict";
    var product_filter = -1;
    var product_plan = -1;
    var filterDateFrom = -1;
    var filterDateto =-1;
    var agent_id =-1;
    var KTDatatablesDataSourceAjaxServer = function() {
        var table = '';
        var initTable1 = function() {
            table = $('#exportTable').DataTable({
                responsive: true,
                searchDelay: 500,
                language:{
                    // processing : "<img src='{{asset('img/loading.gif')}}'>"
                    processing :  $("#loader").show()
                },
                processing: true,
                serverSide: true,
                // "order": [[ 1, "desc" ]],
                ajax: {
                    url: '{!! url('admin/report/latestAgentReportData') !!}',
                    data: function (d) {
                        d.product_filter = product_filter;
                        d.product_plan = product_plan;
                        d.filterDateFrom = filterDateFrom;
                        d.filterDateto = filterDateto;
                        d.agent_id =agent_id;
                        // d.policy_number = policy_number;
                        // d.filterDateFrom = filterDateFrom;
                        // d.filterDateto = filterDateto;
                    }
                },
                order: [0, 'DESC'],
                columns: [
                    {data: 'agent_name',name:'Agent Name'},
                    // {data: 'agent_name',name:'P1 Million Accidental Death Insurance'},
                    // {data: 'agent_name',name:'Third Party Car Insurance'},
                    // {data: 'agent_name',name:'Motor Comprehensive'},
                    // {data: 'agent_name',name:'Mobile and electronic device insurance'},
                    // {data: 'store_name',name:'Store Name'},
                    // {data: 'product_name',name:'Product Name'},
                    // {data: 'product_plan',name:'Product Plan'},
                    // {data: 'policy_count',name:'Policy Count'},
                ],

            });
        };

        return {
            //main function to initiate the module
            init: function() {
                initTable1();
            },
            draw: function(){
                table.draw();
            }
        };
    }();

    /*Table for exporting all policy data */

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
                format: 'yyyy-mm-dd',
                autoclose: true
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

    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
        // KTDatatablesDataSourceAjaxServer2.init();
    });
    $('#product_filter').on('change',function(){
        product_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
        // KTDatatablesDataSourceAjaxServer2.draw();
    });
    $('#product_plan').on('change',function(){
        product_plan = $(this).val();
        /* alert(product_plan);*/
        KTDatatablesDataSourceAjaxServer.draw();
        // KTDatatablesDataSourceAjaxServer2.draw();
    });
    $('#filterDateFrom').on('change',function(){
        filterDateFrom = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
        // KTDatatablesDataSourceAjaxServer2.draw();
    });
    $('#filterDateto').on('change',function(){
        filterDateto = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
        // KTDatatablesDataSourceAjaxServer2.draw();
    });
    $('#agent_id').on('change',function(){
        agent_id = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
        // KTDatatablesDataSourceAjaxServer2.draw();
    });


</script>



</body>
<!-- end::Body -->
</html>
