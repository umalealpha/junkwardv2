<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css"/>
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet"
      type="text/css"/>
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}"
      rel="stylesheet" type="text/css"/>

<!-- begin::Body -->

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
    <!--If Password default, show edit details -->
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        KYC Fields List
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}"
                                                                                     class="kt-subheader__breadcrumbs-link">
                            Dashboard </a> <span
                            class="kt-subheader__breadcrumbs-separator"></span>
                        <span
                            class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">KYC Fields</span>
                    </div>
                </div>
                {{-- @can('repair_centers-create') --}}
                <div class="kt-subheader__toolbar">
                    <div class="kt-subheader__wrapper">
                        <a href="{{ route('admin.KycFields.create') }}"
                           class="btn btn-sm btn-elevate btn-brand btn-elevate"
                           id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New">
                            <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i
                                class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                    </div>
                </div>
                {{-- @endcan --}}
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <!--begin: Filter -->
                        {{--                        <div class="row">--}}
                        {{--                            <div class="col-sm-6">--}}
                        {{--                                <label>Filter By Partner</label>--}}
                        {{--                                <select name="partner_filter" class="form-control" id="partner_filter">--}}
                        {{--                                    <option value="-1">All</option>--}}
                        {{--                                    @foreach($partners as $partner)--}}
                        {{--                                    <option style="text-transform: capitalize" value="{!! $partner->id !!}">{!!--}}
                        {{--                                        $partner->name !!}</option>--}}
                        {{--                                    @endforeach--}}
                        {{--                                </select>--}}
                        {{--                            </div>--}}
                        {{--                        </div>--}}
                        <br>
                        <!--end: Filter -->
                        <!--begin: Datatable -->
                        <table class="table table-striped table-bordered table-hover table-checkable"
                               id="KycFields_table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
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
<div id="kt_scrolltop" class="kt-scrolltop"><i class="la la-arrow-up"></i></div>
<!-- end:: Scrolltop -->

@include('admin.layouts.scripts')

<div class="modal fade" id="delete_confirm" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title"
     aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
        </div>
    </div>
</div>


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>
    "use strict";
    var claimStatus_filter = -1;
    var partner_filter = -1;
    var KTDatatablesDataSourceAjaxServer = function () {
        var table = '';
        var initTable1 = function () {
            table = $('#KycFields_table').DataTable({
                responsive: true,
                searchDelay: 500,
                language: {
                    processing: "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{!! route('admin.KycFields.data') !!}',
                    data: function (d) {
                        d.partner_filter = partner_filter;
                    }
                },
                order: [0, 'DESC'],
                columns: [
                    {data: 'id'},
                    {data: 'name'},
                    {data: 'created_at'},
                    {data: 'actions'},
                ],
            });
        };
        return {
            //main function to initiate the module
            init: function () {
                initTable1();
            },
            draw: function () {
                table.draw();
            }
        };
    }();
    jQuery(document).ready(function () {
        KTDatatablesDataSourceAjaxServer.init();
    });
    $('#partner_filter').on('change', function () {
        partner_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });
</script>
<script>
    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });

    });

    $("#compliance_table").on("click", "a.confirm-delete", function (event) {
        event.preventDefault();
        var id = $(this).attr('value');
        $.ajax({
            url: '{{ route('admin.KycFields.confirm-delete') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": id
            },
            type: 'post',
            datatype: 'json',
            success: function (data) {

                $('#modal-content').append(append);
                var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Delete </h5>' +
                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                    '</div> ' +
                    '<div class="modal-body"> ' +
                    '<p>' + data.body + '</p> ' +
                    '</div> ' +
                    '<div class="modal-footer"> ' +
                    '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';

                if (data.status == 'success') {

                    var url = '{{ route("admin.KycFields.delete", ":id") }}';
                    url = url.replace(':id', data.id);

                    append += '<a href="' + url + '" type="button" class="btn btn-brand">Delete</a></div>';

                }

                append += '</div>';
                $('#modal-content').empty();
                $('#modal-content').append(append);
                $('#delete_confirm').modal('show');
            },
        });
    });


</script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"
        type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}"
        type="text/javascript"></script>
</body>
<!-- end::Body -->
</html>
