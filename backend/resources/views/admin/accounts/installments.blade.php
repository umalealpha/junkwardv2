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
    <!-- check if is first time login -->
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                       Contract Installments
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a href="{{Route('admin.product.index')}}" class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Product</span> </a>
                    </div>

                </div>
                <div class="kt-subheader__toolbar">
                    <div class="kt-subheader__wrapper">
                        <a href="{!! route('admin.addInstalment',$contractId) !!}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add Instalment</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">

                        <!--begin: Filter -->

                        <!--end: Filter -->

                        <!--begin: Datatable -->
                        <table class="table table-striped table-bordered table-hover table-checkable" id="product_table">
                            <thead>
                            <tr>
                                <th>Sequence</th>
                                <th>Reference No.</th>
                                <th>CTC Amnt</th>
                                <th>Action Date</th>
                                <th>Tracking Code</th>
                                <th>Installment Amount</th>
                                <th>Bank Response</th>
                                <th>Installment Status</th>
                                <th>Retry Count</th>
                                <th>Note</th>
                                <th>Action</th>
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
<script>
    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });

    });

    $("#product_table").on("click", "a.confirm-delete" , function(event) {
        event.preventDefault();
        var product_id = $(this).attr('value');
        $.ajax({
            url: '{{ route('admin.product.confirm-delete') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": product_id
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {

                $('#modal-content').append(append);
                var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Delete Product</h5>' +
                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                    '</div> ' +
                    '<div class="modal-body"> ' +
                    '<p>'+data.body+'</p> ' +
                    '</div> ' +
                    '<div class="modal-footer"> ' +
                    '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';

                if(data.status == 'success'){

                    var url = '{{ route("admin.product.delete", ":id") }}';
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
    var productType_filter = -1;

    var KTDatatablesDataSourceAjaxServer = function() {
        var table = '';
        var initTable1 = function() {
            // begin first table
            table = $('#product_table').DataTable({
                responsive: true,
                processing: true,
                language:{
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                serverSide: true,
                ajax: {
                    url: '{!! route('admin.getContractInstallments',$contractId) !!}',
                    data: function (d) {
                        d.productType_filter = productType_filter;
                    }
                },
                columns: [
                    {data: 'InstalmentSequence'},
                    {data: 'InstalmentReferenceNumber'},
                    {data: 'CTCAmount'},
                    {data: 'InstalmentActionDate'},
                    {data: 'TrackingCode'},
                    {data: 'InstalmentAmount'},
                    {data: 'instalmentResponse'},
                    {data: 'InstalmentStatus'},
                    {data: 'retry_count'},
                    {data: 'note'},
                    {data: 'action'},
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
    $('#productType_filter').on('change',function(){
        productType_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    var dataID = '';

    $("#product_table").on("click", "a.confirm-cancel" , function(event) {
        event.preventDefault();
        var id = $(this).attr('value');
        $.ajax({
            url: '{{ route('confirm-cancel') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": id
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {
                $('#modal-content').append(append);
                var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Cancel Instalment</h5>' +
                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                    '</div> ' +
                    '<div class="modal-body"> ' +
                    '<p>'+data.body+'</p> ' +
                    '<p>Please provide the reason for cancellation</p> ' +
                    '<textarea id="cancellationreason" style="height:auto;width:100%"></textarea>'+
                    '</div> ' +
                    '<div class="modal-footer"> ' +
                    '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">No</button> ';

                if(data.status == 'success'){
                    dataID = data.id;
                    append += '<a href="#" id="confirm-cancel-button" type="button" class="btn btn-brand">Yes,Cancel</a></div>';

                }

                append += '</div>';
                $('#modal-content').empty();
                $('#modal-content').append(append);
                $('#delete_confirm').modal('show');
            },
        });
    });

    $(document).on('click', '#confirm-cancel-button', function(){
        var url = '{{ route("admin.updateStatus", [":id","I",":reason"]) }}';
        url = url.replace(':id', dataID);
        url = url.replace(':reason',$('textarea#cancellationreason').val());
        window.location.href = url;
    })
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