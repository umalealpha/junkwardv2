<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />

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
                     Payment Vendor List
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Payment Vendors</span>
                </div>
            </div>
            @can('payment-vendor-create')
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <a href="{{ URL::to('admin/paymentVendor/create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                </div>
            </div>
            @endcan
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vendor Name</th>
                            <th>Vendor Email</th>
                            <th>Vendor Telephone</th>
                            <th>Off Time Start</th>
                            <th>Off Time End</th>
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
                    <option value="Glass">Glass</option>
                    <option value="Life">Life</option>
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
<script>

    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });

    });
    $("#policy_table").on("click", "a.claimTypeModal" , function(event) {
        event.preventDefault();
        $("#submitType").attr("href", $(this).attr('href'));
        $('#claimTypeModal').modal('show');
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

    $(document).ready(function(){
      $("input:checkbox").change(function() {
      var user_id = $(this).closest('tr').attr('id');

          $.ajax({
            type:'POST',
            url:'',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: { "user_id" : user_id },
            success: function(data){
              if(data.data.success){
                //do something
              }
            }
        });
    });
});

    "use strict";
    var KTDatatablesDataSourceAjaxServer = function() {

        var initTable1 = function() {
            var table = $('#policy_table');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500, 
                language:{ 
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                ajax: '{!! route('admin.paymentvendor.data') !!}',
                order: [0, 'DESC'],
                columns: [
                    {data: 'id'},
                    {data: 'vendorName'},
                    {data: 'email'},
                    {data: 'telephone'},
                    {data: 'offtimestart'},
                    {data: 'offtimeend'},
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
            }
        };
    }();

    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
    });
</script>

</body>
<!-- end::Body -->
</html>