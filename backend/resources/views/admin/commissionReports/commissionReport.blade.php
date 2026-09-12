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
                    Commission Report List
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active"><a href="{{Route('admin.commissionReports.index')}}" class="kt-subheader__breadcrumbs-link"> Commission Reports </a></span>
                </div>
            </div>
    
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                     <!--begin: Filter -->
                        <!-- <div class="row">
                            <div class="col-sm-4">
                                <label>Filter By date from</label>
                                <input type="text" class="form-control  kt_datepicker_1 restrictDate"  id="filterDateFrom" placeholder="Select date from" name="filterDateFrom" autocomplete="off">

                            </div>
                            <div class="col-sm-4">
                                <label>Filter By date to</label>
                                <input type="text" class="form-control  kt_datepicker_1 restrictDate filterDateto"  id="filterDateto" placeholder="Select date to" name="filterDateto" autocomplete="off">
                            </div>
                        </div>
                        <br> -->
                        <!--end: Filter -->

                    <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="commission_table">
                        <thead>
                        <tr>
                            <th>Policy Number</th>
                            <th>User ID</th>
                            <th>Agent Name</th>
                            <th>Product</th>
                            <th>Premium</th>
                            <th>Payment Reference</th>
                            <th>Payment Success</th>
                            <th>KYC</th>
                            <th>Pre-Inspection</th>
                            <!-- <th>Commission Basis</th> -->
                            <!-- <th>Commission</th>
                            <th>Date of Transaction</th> -->
                        </tr>
                        </thead>
                    </table> 
                    <!--end: Datatable -->
                </div>
            </div>
        </div>
        <!-- end:: Content -->
    </div>
    <!-- begin:: Footer -->
     @endif
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

    $("#commission_table").on("click", "a.confirm-delete" , function(event) {
        event.preventDefault();
        var commission_id = $(this).attr('value');
        $.ajax({
            url: '{{ route('admin.commission.confirm-delete') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": commission_id
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {
                $('#modal-content').append(append);
                var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Delete Commission</h5>' +
                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                    '</div> ' +
                    '<div class="modal-body"> ' +
                    '<p>'+data.body+'</p> ' +
                    '</div> ' +
                    '<div class="modal-footer"> ' +
                    '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';

                if(data.status == 'success'){

                    var url = '{{ route("admin.commission.delete", ":id") }}';
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
<script src="https://cdn.datatables.net/buttons/1.6.0/js/dataTables.buttons.min.js" ></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.0/js/buttons.html5.min.js"></script>

<script>
    "use strict";
    var filterDateFrom = -1;
    var filterDateto =-1;
    // console.log({!! route('admin.commission.data') !!});
    var KTDatatablesDataSourceAjaxServer = function() {

        var initTable1 = function() {
            var table = $('#commission_table');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                dom:'<"m-t-10 pull-left"f><"m-t-10 pull-right"B>rti<"m-t-10 pull-left"><"m-t-10 pull-right"p>',
                buttons: [
                    'excelHtml5',
                    'csvHtml5',
                ],
                ajax: '{!! route('admin.commissionReports.reportData') !!}',
                columns: [
                    {data: 'policyNumber'},
                    {data: 'id'},
                    {data: 'firstName'},
                    {data: 'name'},
                    {data: 'premium'},
                    {data: 'payment_reference'},
                    {data: 'status'},
                    {data: 'kyc'},
                    {data: 'preinspection'},
                    // {data: 'commission_type'},
                    // {data: 'commission'},
                    // {data: 'created_at'},
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
    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
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
