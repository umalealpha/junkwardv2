<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="https://cdn.datatables.net/buttons/1.6.0/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css" />

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
                        Claim Bordereaux Outstanding Payment Report
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-separator">Reports</span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Claim Bordereaux Outstanding Payment Report</span>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <!--begin: Filter -->
                        <div class="row">
                            <div class="col-sm-4">
                                <label>Filter By date from</label>
                                <input type="text" class="form-control  kt_datepicker_1 restrictDate"  id="filterDateFrom" placeholder="Select date from" name="filterDateFrom" autocomplete="off">
                            </div>
                            <div class="col-sm-4">
                                <label>Filter By date to</label>
                                <input type="text" class="form-control  kt_datepicker_1 restrictDate filterDateto"  id="filterDateto" placeholder="Select date to" name="filterDateto" autocomplete="off">
                            </div>
                        </div>
                        <br>
                        <!--end: Filter -->
                        <!--begin: Datatable -->
                        <table class="table table-striped table-bordered table-hover table-checkable" id="claim_report">
                            <thead>
                            <tr>
                                <th>Claim No</th>
                                <th>Policy Number</th>
                                <th>Product Name</th>
                                <th>Insured Name</th>
                                <th>Reported Date</th>
                                <th>Date Of Loss</th>
                                <th>Risk Address</th>
                                <th>s_Group Name</th>
                                <th>Motor Desc</th>
                                <th>Total Outstanding Amt</th>
                                <th>Net_percent</th>
                                <th>Quota_percent</th>
                                <th>Surplus_percent</th>
                                <th>Fac_percent</th>
                                <th>Netretention_Amt</th>
                                <th>Quotasharing_Amt</th>
                                <th>Surplus_Amt</th>
                                <th>Facultative_Amt</th>
                                <th>Treaty Name</th>
                                <th>Claim Sub Type</th>
                                <th>Regulatory Mapping Name</th>
                            </tr>
                            </thead>
                        </table>
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
<!-- end:: Page -->
<!-- end:: Root -->

<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')

<div class="modal fade" id="delete_confirm" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
        </div>
    </div>
</div>
<script>
    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });
    });

</script>

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="https://cdn.datatables.net/buttons/1.6.0/js/dataTables.buttons.min.js" ></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.0/js/buttons.html5.min.js"></script>

<script>
    "use strict";
    var policyStatus_filter = -1;
    var filterDateFrom = -1;
    var filterDateto =-1;
    var url = '{!! url('admin/report/claimBordereauxOutstandingPaymentExport/:filter') !!}';
    var KTDatatablesDataSourceAjaxServer = function() {
        var table = '';
        var initTable1 = function() {
            table = $('#claim_report').DataTable({
                responsive: true,
                searchDelay: 500,
                language:{
                    processing :  $("#loader").show()
                },
                processing: true,
                serverSide: true,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                dom: 'Bfrtlip',
                buttons: [
                    {
                        extend: 'excel',
                        messageTop:'This excel is about realpay transaction information',
                        action: function(e,dt,node,config){
                            // var url1 = url.replace(':filter','?policyStatus_filter='+policyStatus_filter);
                            var url1 = url.replace(':filter','?filterDateFrom='+filterDateFrom+'&filterDateto='+filterDateto);
                            window.location.href = url1;
                        }
                    },
                ],
                order : [1, 'DESC'],
                ajax: {
                    url: '{!! route('admin.report.getClaimBordereauxOutstandingPayment') !!}',
                    type: 'post',
                    data: function (d) {
                        d._token = "{{ csrf_token() }}";
                        d.filterDateFrom = filterDateFrom;
                        d.filterDateto = filterDateto;
                    }
                },
                columns: [
                    {data: 'claim_number'},
                    {data: 'policyNumber'},
                    {data: 'product_name'},
                    {data: 'customer_name'},
                    {data: 'reportedDate'},
                    {data: 'dateofLoss'},
                    {data: 'riskAddress'},
                    {data: 'GroupName'},
                    {data: 'motorDesc'},
                    {data: 'totalOutstandingAmt'},
                    {data: 'netPercent'},
                    {data: 'quotaPercent'},
                    {data: 'surplusPercent'},
                    {data: 'facPercent'},
                    {data: 'Netretention_Amt'},
                    {data: 'quotasharing_Amt'},
                    {data: 'Surplus_Amt'},
                    {data: 'Facultative_Amt'},
                    {data: 's_TreatyName'},
                    {data: 'claimSubType'},
                    {data: 'regulatoryMappingName'},
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
    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
    });
    $('#filterDateFrom').on('change',function(){
        filterDateFrom = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });
    $('#filterDateto').on('change',function(){
        filterDateto = $(this).val();
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