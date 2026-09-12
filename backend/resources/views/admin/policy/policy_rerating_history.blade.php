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
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Policy Premium Rerating List for {{ $id }}
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Quotes</span>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <br>
                        <table class="table table-striped table-bordered table-hover table-checkable" id="rerating_history">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Rate ID</th>
                                <th>Monthly Instalment Amt</th>
                                <th>Three Instalment Amt</th>
                                <th>Annual Instalment Amt</th>
                                <th>Sum Assured / Insured</th>
                                <th>Discount (%)</th>
                                <th>Surcharge (%)</th>
                                <th>Status</th>
                                <th>Payment Status</th>
                                <th>Rerated By</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                        </table>
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>
            <!-- end:: Content -->

            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <h5>Cash Payment Done Log</h5>
                        <br>
                        <table class="table table-striped table-bordered table-hover table-checkable" id="cashPaymentLogged">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Policy Number</th>
                                <th>Contract Number</th>
                                <th>Reference Number</th>
                                <th>Payment Method</th>
                                <th>Payment Frequency</th>
                                <th>Amount</th>
                                <th>Amount After Rerating</th>
                                <th>Payment Date</th>
                                <th>Payment Settlement Date</th>
                                <th>Is Ledger</th>
                                <th>No. of Installments Paid</th>
                                <th>Note</th>
                                <th>Status</th>
                                <th>Payment Recieved By</th>
                                <th>Payment Added By</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                        </table>
                        <!--end: Datatable -->
                    </div>
                </div>
            </div>
            <!-- end:: Content -->

            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <h5>Premium Logs</h5>
                        <br>
                        <table class="table table-striped table-bordered table-hover table-checkable" id="premium_logs_details">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Policy Number</th>
                                <th>Old Annual Premium</th>
                                <th>Old Premium</th>
                                <th>Old Frequency</th>
                                <th>Old First Premium</th>
                                <th>Old First Premium Wvat</th>
                                <th>Old Billing Start Date</th>
                                <th>New Annual Premium</th>
                                <th>New Premium</th>
                                <th>New Frequency</th>
                                <th>New First Premium</th>
                                <th>New First Premium Wvat</th>
                                <th>New Billing Start Date</th>
                                <th>Action Performed By</th>
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
</div>
<!-- end:: Page -->
</div>
<!-- end:: Root -->

@include('admin.layouts.scripts')
<div class="modal fade" id="delete_confirm" tabindex="-1" role="dialog" aria-labelledby="sms_delete_confirm_title" aria-hidden="true">
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

    $("#quotes_table").on("click", "a.confirm-delete" , function(event) {
        event.preventDefault();
        var quoteId = $(this).attr('value');
        $.ajax({
            url: '{{ route('quote.confirm-delete') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": quoteId
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {

                $('#modal-content').append(append);
                var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Delete Quote</h5>' +
                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                    '</div> ' +
                    '<div class="modal-body"> ' +
                    '<p>'+data.body+'</p> ' +
                    '</div> ' +
                    '<div class="modal-footer"> ' +
                    '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';

                if(data.status == 'success'){

                    var url = '{{ route("quote.delete", ":id") }}';
                    url = url.replace(':id', data.id);

                    append += '<a href="'+url+'" type="button" class="btn btn-brand">Delete</a></div>';

                }

                append += '</div>';
                $('#modal-content').empty();
                $('#modal-content').append(append);
                $('#delete_confirm').modal('show');
            },
        });
    });


</script>

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script>
    "use strict";
    var filterDateFrom = -1;
    var filterDateto =-1;
    var KTDatatablesDataSourceAjaxServer = function() {
        var initTable1 = function() {
            var table = $('#rerating_history');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                ajax: '{!! route('admin.policy.getPremiumUpdateHistory',$id) !!}',
                order: [0, 'DESC'],
                columns: [
                    {data: 'id',name:'id'},
                    {data: 'ratings_id',name:'ratings_id'},
                    {data: 'month_ins',name:'month_ins'},
                    {data: 'three_ins',name:'three_ins'},
                    {data: 'annual_ins',name:'annual_ins'},
                    {data: 'sum_assured',name:'sum_assured'},
                    {data: 'discount',name:'discount'},
                    {data: 'surcharge',name:'surcharge'},
                    {data: 'status',name:'status'},
                    {data: 'payment_status',name:'payment_status'},
                    {data: 'rerated_by',name:'rerated_by'},
                    {data: 'rating_detail',name:'rating_detail'},
                ],

            });
        };

        return {
            //main function to initiate the module
            init: function() {
                initTable1();
            }
        };
    }();


    "use strict";
    var filterDateFrom = -1;
    var filterDateto =-1;
    var KTDatatablesDataSourceAjaxServer1 = function() {
        var initTable2 = function() {
            var table = $('#cashPaymentLogged');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                ajax: '{!! route('admin.policy.getCashPaymentLoggedData',$id) !!}',
                order: [0, 'DESC'],
                columns: [
                    {
                    data: 'id'
                }, {
                    data: 'policyNumber'
                },{
                    data: 'contractNumber'
                },{
                    data: 'referenceNumber'
                }, {
                    data: 'paymentMethod'
                }, {
                    data: 'paymentFrequency'
                }, {
                    data: 'amount'
                }, {
                    data: 'amountAfterRerating'
                }, {
                    data: 'paymentDate'
                }, {
                    data: 'created_at'
                }, {
                    data: 'is_ledger'
                }, {
                    data: 'numberOfInstalmentsPaid'
                }, {
                    data: 'note'
                }, {
                    data: 'status'
                }, {
                    data: 'cashRecipient'
                },{
                    data: 'paymentLoggedBy'
                },{
                    data: 'actions'
                },
                ],

            });
        };

        return {
            //main function to initiate the module
            init: function() {
                initTable2();
            }
        };
    }();


    "use strict";
    var filterDateFrom = -1;
    var filterDateto =-1;
    var KTDatatablesDataSourceAjaxServer2 = function() {
        var initTable3 = function() {
            var table = $('#premium_logs_details');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                ajax: '{!! route('admin.policy.getPremiumLogsDataDetails',$id) !!}',
                order: [0, 'DESC'],
                columns: [
                    {
                    data: 'id'
                }, {
                    data: 'policyNumber'
                },{
                    data: 'old_annual_premium'
                },{
                    data: 'old_premium'
                }, {
                    data: 'old_premium_freq'
                }, {
                    data: 'old_first_premium'
                }, {
                    data: 'old_first_premium_wvat'
                }, {
                    data: 'old_billingStartDate'
                }, {
                    data: 'new_annual_premium'
                }, {
                    data: 'new_premium'
                }, {
                    data: 'new_premium_freq'
                }, {
                    data: 'new_first_premium'
                }, {
                    data: 'new_first_premium_wvat'
                }, {
                    data: 'new_billingStartDate'
                }, {
                    data: 'action_performed_by'
                },
                ],

            });
        };

        return {
            //main function to initiate the module
            init: function() {
                initTable3();
            }
        };
    }();

    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
        KTDatatablesDataSourceAjaxServer1.init();
        KTDatatablesDataSourceAjaxServer2.init();
    });

    $('#filterDateFrom').on('change',function(){
        filterDateFrom = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });
    $('#filterDateto').on('change',function(){
        filterDateto = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

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

    $('.restrictDate').datepicker({
        autoclose: true,
        orientation: "bottom",
        endDate: "today"

    });


</script>
</body>
</html>
