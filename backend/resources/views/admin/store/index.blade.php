<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet"
    type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}"
    rel="stylesheet" type="text/css" />

<!-- begin::Body -->

<body
    class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
    <!-- begin:: Header Mobile -->
    <div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed ">
        <div class="kt-header-mobile__logo">
            <a>
                <img alt="Logo" src="{{asset('images/logo.png')}}" />
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
        <!--If Password default, show edit details -->
        @if (Auth::user()->password == null)
        @include('includes.reset')
        @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Stores List
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}"
                            class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span
                            class="kt-subheader__breadcrumbs-separator"></span>
                        <span
                            class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Stores</span>
                    </div>
                </div>
                @can('store-create')
                <div class="kt-subheader__toolbar">
                    <div class="kt-subheader__wrapper">
                        <a href="{{ route('admin.store.create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate"
                            id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New">
                            <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i
                                class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                        <a href="{{ route('admin.store.bundle.edit') }}"
                            class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title=""
                            data-placement="left" data-original-title="Update Stores">
                            <span class="kt-opacity-11" id="">Update Stores</span>&nbsp; <i
                                class="flaticon2-edit-1 kt-padding-l-5 kt-padding-r-0"></i>
                        </a>
                    </div>
                </div>
                @endcan
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <!--begin: Filter -->
                        <div class="row">
                            <div class="col-sm-6 mb-4">
                                <label>Filter By Partner</label>
                                <select name="partner_filter" class="form-control" id="partner_filter">
                                    <option value="-1">All</option>
                                    @foreach($partners as $partner)
                                    <option style="text-transform: capitalize" value="{!! $partner->id !!}">{!!
                                        $partner->name !!}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <br>
                        <!--end: Filter -->

                        <!--begin: Datatable -->
                        <table class="table table-striped table-bordered table-hover table-checkable" id="store_table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Store Name</th>
                                    <th>Partner</th>
                                    <th>City</th>
                                    <th>Status</th>
                                    <th>Created at</th>
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

    <!-- begin:: Scrolltop -->
    <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
    <!-- end:: Scrolltop -->

    @include('admin.layouts.scripts')

    {{-- !-- Delete Warning Modal -->  --}}
    <div class="modal fade" id="delete_confirm" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content" id="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Stores</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <h5 class="text-center">Are you sure you want to delete?</h5>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="deleteBtn">Yes, Delete </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="view_store_modal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
        <div class="modal-dialog" role="document" style="max-width: 800px">
            <div class="modal-content" id="modal-content">
                <div id="modal-inside">

                </div>
            </div>
        </div>
    </div>
    <script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"
        type="text/javascript"></script>
    <script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}"
        type="text/javascript"></script>
    <script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
    <script>
        "use strict";
    var claimStatus_filter = -1;
    var partner_filter = -1;
    var KTDatatablesDataSourceAjaxServer = function() {
        var table = '';
        var initTable1 = function() {
            table = $('#store_table').DataTable({
                responsive: true,                
                processing: true,
                serverSide: true,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                dom: 'lBfrtip',
                buttons: [
                'excelHtml5',                
                ],
                ajax: {
                    url: '{!! route('admin.store.data') !!}',
                    type:"GET",
                    data: function (d) {
                        d.partner_filter = partner_filter;
                    }
                },
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                order: [0, 'DESC'],
                columns: [
                    {data: 'id'},
                    {data: 'name'},
                    {data: 'partner_id'},
                    {data: 'city'},
                    {data: 'status'},
                    {data: 'created_at'},
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

    $('#partner_filter').on('change',function(){
        partner_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $("#store_table").on("click", "button.show", function (event) {
        event.preventDefault();
        var id = $(this).attr('data-id');
        var modal = $('#view_store_modal');
        var modal_body = $('#modal-inside')
        $.ajax({
            url: "{{ url('admin/store/show') }}"+'/'+id,
            type: "GET",            
            beforeSend: function() {
            $('.modelSpinner').show();
            },
            data: {
            "_token": "{{ csrf_token() }}",
            },
            success: function (data) {
                modal_body.html(data.model_body);
                modal.modal('show');
            }
        });
    });

    $("#store_table").on("click", "button.confirm-delete", function (event) {
        event.preventDefault();
        var id = $(this).attr('data-id');
        var delete_confirm = $('#delete_confirm');
        delete_confirm.modal('show');
        $('#deleteBtn').click(function(){
            $.ajax({
                url: "{{ url('admin/store/delete') }}"+'/'+id,
                type: "GET",
                beforeSend: function() {
                    $('.modelSpinner').show();
                },                
                data: {
                    "_token": "{{ csrf_token() }}",
                },
                success: function (data) {
                    var oTable = $('#store_table').dataTable();
                    oTable.fnDraw(false);
                    $('#alert-text').html('<strong>Success</strong> Store deleted successfully');
                    $('#alert').show();

                    setTimeout(function(){
                        $('#alert').hide();                      
                    }, 3000);
                    delete_confirm.modal('hide');
                },
                error: function (data) {
                    console.log('Error:', data);
                }
            });
        })
        
    }); 
    </script>
</body>
<!-- end::Body -->

</html>