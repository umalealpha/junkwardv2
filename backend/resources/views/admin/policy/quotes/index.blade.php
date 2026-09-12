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
                    Quote List
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Quotes</span>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                {{--@can('quote-create')
                <div class="kt-subheader__wrapper">
                    <a href="{{ URL::to('admin/policy/create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                </div>
                @endcan--}}
                    @can('quote-Export')
                <div class="kt-subheader__wrapper">
                    <a href="{{ URL::to('qoutes/export') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Export"> <span class="kt-opacity-11" id="">Export</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                </div>
                    @endcan
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <!--begin: Filter -->
                    <div class="row">
                    <div class="col-4 mt-2 mb-2">
                                <label>Filter By Agent</label>
                                <select class="select2 form-control kt_selectpicker" id="agent_filter" name="agent_filter" data-live-search="true" title="Select Agent">
                                    <option value="-1">All</option>
                                    @foreach($agents as $agent)
                                        @if($agent->id != null)
                                            <option  value="{{$agent->id}}">{{$agent->firstName}} {{$agent->lastName}}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                    </div>
{{--                    <div class="row">--}}
{{--                        <div class="col-sm-4">--}}
{{--                            <label>Filter By date from</label>--}}
{{--                            <input type="text" class="form-control  kt_datepicker_1 restrictDate"  id="filterDateFrom" placeholder="Select date from" name="filterDateFrom" autocomplete="off">--}}
{{--                        </div>--}}
{{--                        <div class="col-sm-4">--}}
{{--                            <label>Filter By date to</label>--}}
{{--                            <input type="text" class="form-control  kt_datepicker_1 restrictDate"  id="filterDateto" placeholder="Select date to" name="filterDateto" autocomplete="off">--}}
{{--                        </div>--}}
{{--                    </div>--}}
                    {{--<br>--}}
                    <!--end: Filter -->

                    <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="quotes_table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer Names</th>
                            <th>Search Name</th> <!--It is for searching purpose-->
                            <th>Quote Code</th>
                            <th>Policy Number</th>
                            <th>Product</th>
                            <th>Created By</th>
                            <th>Premium Rate</th>
                            {{--<th>Type</th>--}}
                            <th>IP Address</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>

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
    var agent_filter = -1;
    var KTDatatablesDataSourceAjaxServer = function() {
       
        var table = '';
        var initTable1 = function() {
            table = $('#quotes_table').DataTable({
        
                responsive: true,
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
               
                ajax: {
                    url: '{!! route('quote.data') !!}',
                    data: function (d) {
                        d.agent_filter = agent_filter;
                     }
                    },
                order: [0, 'DESC'],
                columns: [
                    {data: 'id',name:'id'},
                    {data:'fname', name:'customerQuote.firstName'},
                    {data:'lname', name:'customerQuote.lastName',"visible":false},                   
                    {data: 'quoteCode',name:'quoteCode'},
                    {data: 'policyNumber',name:'policy.policyNumber'},
                    {data: 'productId',name:'productId'},
                    {data: 'agentID',name:'createdByAgent.firstName'},
                    {data: 'premiumRate',name:'id'},
                    /*{data: 'type',name:'type'},*/
                    {data: 'userIPAddress',name:'id'},
                    {data: 'status',name:'status'},
                    {data: 'created_at',name:'created_at'},
                    {data: 'actions'},
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
    $('#agent_filter').on('change',function(){
        agent_filter = $(this).val();
        console.log(agent_filter);
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
