<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
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
                        Realpay Bank List
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        {{--                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active"><a href="{{Route('admin.accounts.index')}}" class="kt-subheader__breadcrumbs-link"> Accounts </a></span>--}}
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <!--begin: Datatable -->
                        <form id="bankListForm" action="{{ route('admin.seletRealpayBank') }}" method="POST">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Bank:</label>
                                <div class="col-9">
                                    <select class="form-control" name="bankId" id="banks">
                                        <option value="" disabled selected>Select Bank</option>
                                        @foreach($banks as $b)
                                            <option value="{{ $b->id }}">{{ $b->bank_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="kt-portlet__foot kt-portlet__foot--solid">
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-3"></div>
                                        <div class="col-9">
                                            <button type="submit" name="submitBtn" value="add" id="submitbtn" class="btn btn-brand">Add Bank</button>
                                            <button type="submit" name="submitBtn" value="remove" id="submitbtn" class="btn btn-danger">Remove Bank</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered table-hover table-checkable" id="bank_table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Bank Name</th>
                                {{-- <th>Action</th> --}}
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

    $("#accounts_table").on("click", "a.confirm-delete" , function(event) {
        event.preventDefault();
        var account_id = $(this).attr('value');
        $.ajax({
            url: '{{ route('admin.accounts.confirm-delete') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": account_id
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {
                $('#modal-content').append(append);
                var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Delete Account</h5>' +
                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                    '</div> ' +
                    '<div class="modal-body"> ' +
                    '<p>'+data.body+'</p> ' +
                    '</div> ' +
                    '<div class="modal-footer"> ' +
                    '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';

                if(data.status == 'success'){

                    var url = '{{ route("admin.accounts.delete", ":id") }}';
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
<script>
    "use strict";
    var KTDatatablesDataSourceAjaxServer = function() {

        var initTable1 = function() {
            var table = $('#bank_table');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                ajax: '{!! route('admin.fetchRealpayBankList') !!}',
                order: [0, 'DESC'],
                columns: [
                    {data: 'id'},
                    {data: 'bank_name'},
                    // {data: 'actions'},
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

    function selectBankToShow(e){
        var idGet = $(e).attr("id");
        var isChecked=document.getElementById(idGet).checked;
        if (isChecked){
            ajaxRequest = setTimeout(function (sn) {
                $.ajax({
                    url: '{{ route('admin.seletRealpayBank') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "id": e.value,
                    },
                    type: 'post',
                    datatype: 'json',
                    success: function (data) {
                        Toastify({
                            text: data.message,
                            duration: 6000,
                            newWindow: true,
                            gravity: "top", // `top` or `bottom`
                            center: true, // `true` or `false`
                            backgroundColor: "#1dc9b7",
                        }).showToast();
                    },
                    error: function(data) {
                        Toastify({
                            text: data.responseJSON.message,
                            duration: 6000,
                            newWindow: true,
                            gravity: "top", // `top` or `bottom`
                            center: true, // `true` or `false`
                            backgroundColor: "red",
                        }).showToast();
                    }
                });
            }, 200);
        }
    }

</script>

</body>
<!-- end::Body -->
</html>
