<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
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
    <!-- check if is first time login -->
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        User List
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">User</span>
                    </div>
                </div>
                @can('user-create')
                <div class="kt-subheader__toolbar">
                    <div class="kt-subheader__wrapper">
{{--                        <div class="kt-subheader__wrapper">--}}
{{--                            <a href="{{ route('admin.user.export') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Export"> <span class="kt-opacity-11" id="">Export</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>--}}
{{--                        </div>--}}
                        <button type="button" class="btn btn-sm btn-elevate btn-success btn-elevate p-1" id="refreshRoles" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="refresh user roles">
                            <img  class="mr-1 loader" style="display:none" src="{{ asset('img/loading.gif') }}" class="img-responsive" width=20 height=20 />
                            <span class="kt-opacity-11" id="">Refresh User Roles</span>&nbsp;
                            <i class="fas fa-reply"></i>
                        </button>
                        <a href="{{ URL::to('admin/user/create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>


                    </div>
                </div>
                @endcan
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                    <div class="row">
                            <div class="col-6">
                                <label>Filter By Users Status</label>
                                <select name="Status_filter" class=" form-control" id="Status_filter">
                                    <option value="-1">All</option>Suspended
                                    <option style="text-transform: capitalize" value="0">In-Active Users</option>
                                    <option style="text-transform: capitalize" value="1">Active Users</option>
                                    <option style="text-transform: capitalize" value="2">Suspended</option>
                                  
                                </select>
                            </div>
                            <div class="col-6">
                                <label>Filter By role</label>
                                <select name="role_filter" class=" form-control" id="role_filter">
                                    <option value="-1">All</option>
                                    @foreach($roles as $role)
                                        <option style="text-transform: capitalize" value="{!! $role->id !!}">{!! $role->name !!}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div><br>
                        <div class="row">
                            <div class="col-6">
                                <label>Filter By Agency</label>
                                <select name="agency_filter" class=" form-control" id="agency_filter">
                                    <option value="-1">All</option>
                                    @foreach($agencys as $agency)
                                        <option style="text-transform: capitalize" value="{!! $agency->id !!}">{!! $agency->name !!}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                               
                            </div>
                        </div><br><br>

                        <!--begin: Datatable -->
                        <table class="table table-striped table-bordered table-hover table-checkable" id="user_table">

                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Names</th>
                                <th>Email</th>
                                <th>Agency</th>
                                <th>User Role</th>
                                <th>Commission</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                        </table>
                        <table class="table table-striped table-bordered table-hover table-checkable" id="exportTable" style="display: none;">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Email</th>
                                <th>Commission</th>
                                <th>Status</th>
                                <th>User Role</th>
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



{{--<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->--}}

@include('admin.layouts.scripts')

<div class="modal fade" id="delete_confirm" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">

        </div>
    </div>
</div>

<div class="modal fade" id="suspend_confirm" tabindex="-1" role="dialog" aria-labelledby="user_suspend_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">

        </div>
    </div>
</div>
<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
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

    $("#user_table").on("click", "a.confirm-delete" , function(event) {
        event.preventDefault();
        var user_id = $(this).attr('value');
        $.ajax({
            url: '{{ route('admin.user.confirm-delete') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": user_id
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {

                $('#modal-content').append(append);
                var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Delete User</h5>' +
                        '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                        '</div> ' +
                        '<div class="modal-body"> ' +
                        '<p>'+data.body+'</p> ' +
                        '</div> ' +
                        '<div class="modal-footer"> ' +
                        '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';

                if(data.status == 'success'){

                    var url = '{{ route("admin.user.delete", ":id") }}';
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

<script>
    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });

    });

    $("#user_table").on("click", "a.confirm-suspend" , function(event) {
        event.preventDefault();
        var user_id = $(this).attr('value');
        $.ajax({
            url: '{{ route('admin.user.confirm-suspend') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": user_id
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {

                $('#modal-content').append(append);
                var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Suspend User Account</h5>' +
                        '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                        '</div> ' +
                        '<div class="modal-body"> ' +
                        '<p>'+data.body+'</p> ' +
                        '</div> ' +
                        '<div class="modal-footer"> ' +
                        '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';

                if(data.status == 'success'){

                    var url = '{{ route("admin.user.suspend", ":id") }}';
                    url = url.replace(':id', data.id);

                    append += '<a href="'+url+'" type="button" class="btn btn-brand">Suspend</a></div>';

                }

                append += '</div>';
                $('#modal-content').empty();
                $('#modal-content').append(append);
                $('#delete_confirm').modal('show');
            },
        });
    });


</script>


<script>
    "use strict";
    var Status_filter = -1;
    var role_filter = -1;
    var agency_filter = -1;
    
    var KTDatatablesDataSourceAjaxServer = function() {

        var table = '';
        var initTable1 = function() {
          
            table = $('#user_table').DataTable({
                responsive: true,
                searchDelay: 500,
                processing: true,
                language:{
                    processing : $("#loader").show()
                },

           

            // begin first table
            // table.DataTable({
            //     responsive: true,
            //     searchDelay: 500,
            //     language:{
            //         processing : "<img src='{{asset('img/loading.gif')}}'>"
            //     },
                processing: true,
                serverSide: true,
                dom:'<"m-t-10 pull-left"f><"m-t-10 pull-right">rti<"m-t-10 pull-left"><"m-t-10 pull-right"p>',
                order: [0, 'DESC'],
                // buttons: [
                //     'excelHtml5',
                //     'csvHtml5',
                // ],
                //ajax: '{!! route('admin.user.data') !!}',
                ajax: {
                    url: '{!! route('admin.user.data') !!}',
                    data: function (d) {
                        d.Status_filter = Status_filter;
                        d.role_filter = role_filter;
                        d.agency_filter = agency_filter;
                      
                    }
                    },
                columns: [
                    {data: 'id'},
                    {data: 'names'},
                    {data: 'email'},
                    {data: 'agency_id'},
                    {data: 'role'},
                    {data: 'commission'},
                    {data: 'active'},
                    {data: 'actions'},
                    
                ],
                error:function(error){
                    console.log(error);
                },
            });
            jQuery.fn.DataTable.Api.register( 'buttons.exportData()', function ( options ) {
                if ( this.context.length ) {
                    var jsonResult = $.ajax({
                        url: '{!! route('admin.user.allUserDataJson') !!}',

                        //        order: this.order[0]},
                        success: function (result) {
                            console.log(result);
                            //Do nothings
                        },
                        async: false
                    });

                    return {body: jsonResult.responseJSON.data, header: $("#exportTable thead tr th").map(function() { return this.innerHTML; }).get()};
                }
            } );
          
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
    $('#Status_filter').on('change',function(){
        Status_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });
    $('#role_filter').on('change',function(){
        role_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });
    $('#agency_filter').on('change',function(){
        agency_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

</script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
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

    $('#refreshRoles').click(function (e) {
        e.preventDefault();
        console.log('clicked');
        $.ajax({
            type: 'get',
            beforeSend: function() {
                $('.loader').css("display","block");
            },
            url:  '{{ route('admin.user.hardResetUserRoles') }}',
            data: {},
            dataType: 'JSON',
            success: function (response) {
                $('.loader').css("display", "none");
                console.log(response);
                Toastify({
                    text: "User roles refreshed",
                    duration: 6000,
                    newWindow: true,
                    gravity: "top", // `top` or `bottom`
                    center: true, // `true` or `false`
                    backgroundColor: "#1dc9b7",
                }).showToast();
                location.reload();
            },
            error:function (error) {
                $('.loader').css("display", "none");
                console.log(error);
                Toastify({
                    text: "Something went wrong, please try again",
                    duration: 4000,
                    newWindow: true,
                    gravity: "top", // `top` or `bottom`
                    center: true, // `true` or `false`
                    backgroundColor: "#CC0000",
                }).showToast();
                location.reload();
            },
        });

    });

    $('.loaderButton').click(function(){
        $('div.loaderDiv2').removeClass('hidden');

    });
</script>

</body>
<!-- end::Body -->
</html>