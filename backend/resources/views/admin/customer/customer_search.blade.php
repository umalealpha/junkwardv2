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
     <!--If Password default -->
     @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Customer Search
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a href="{{Route('admin.customer.index')}}" class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Customers</span> </a>
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
                                <label for="example-text-input" class="form-label">Customer search by Name</label>
                                <input type="text" class="form-control"  id="name" name="name" value="" placeholder="Customer search by First Name & Last Name" autocomplete="off">
                            </div>
                            <div class="col-sm-4">
                                <label for="example-text-input" class="form-label">Customer search by Email</label>
                                <input type="text" class="form-control" id="email" name="email" placeholder="Customer search by Email" autocomplete="off">
                            </div>
                        </div>
                        <br><br>
                        <div class="row">
                            <div class="col-sm-4">
                                <label for="example-text-input" class="form-label">Customer search by Cellphone number</label>
                                <input type="text" class="form-control"  id="cellphone" name="cellphone" value="" placeholder="Customer search by Cellphone" autocomplete="off">
                            </div>
                            <div class="col-sm-4">
                                <label for="example-text-input" class="form-label">Customer search by Omang/passport</label>
                                <input type="text" class="form-control" id="omangpassport" name="omangpassport" value="" placeholder="Customer search by Omang/passport" autocomplete="off">
                            </div>
                        </div>
                        <br><br>
                        <!--end: Filter -->
                        <!--begin: Datatable -->
                        <table class="table table-striped table-bordered table-hover table-checkable" id="customer_table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer Name</th>
                                <th>Cellphone</th>
                                <th>Email</th>
                                <th>Policy Number</th>
                                <th>Product Name</th>
                                <th>Policy Status</th>
                                <th>Customer Status</th>
                                {{-- <th>Created At</th> --}}
                                {{-- <th>Actions</th> --}}
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

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

<script>
    "use strict";
   var name = $('#name').val();
   var email = $('#email').val();
   var cellphone = $('#cellphone').val();
   var omangpassport = $('#omangpassport').val();

   var KTDatatablesDataSourceAjaxServer = function() {
       var table = '';
       var initTable1 = function() {
           table = $('#customer_table').DataTable({
               responsive: true,
               searchDelay: 500,
               language:{
                   // processing : "<img src='{{asset('img/loading.gif')}}'>"
                   processing :  $("#loader").show()
               },
               processing: true,
               serverSide: true,
               dom: 'Blfrtip',
               buttons: [
                   'excelHtml5',
                   'csvHtml5',
               ],
               // "order": [[ 1, "desc" ]],
               ajax: {
                   url: '{!! route('admin.customer.customer_search_data') !!}',
                   data: function (d) {
                       d.name = name;
                       d.email = email;
                       d.cellphone = cellphone;
                       d.omangpassport = omangpassport;
                   }
               },
               order: [0, 'DESC'],
               columns: [
                   {data: 'id',name:'id'},
                   {data: 'firstName',name:'firstName'},
                   {data: 'cellphone',name:'cellphone'},
                   {data: 'email',name:'email'},
                   {data: 'policyNumber',name:'policyNumber'},
                   {data: 'name',name:'name'},
                   {data: 'status',name:'status'},
                   {data: 'is_blocked',name:'is_blocked'},
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

    // $('#name').on('change',function(){
    //      name = $('#name').val();
    //     KTDatatablesDataSourceAjaxServer.draw();
    // });

    $('#name').keyup(function(){
         name = $('#name').val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $('#email').keyup(function(){
         email = $('#email').val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $('#cellphone').keyup(function(){
        cellphone = $('#cellphone').val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $('#omangpassport').keyup(function(){
        omangpassport = $('#omangpassport').val();
        KTDatatablesDataSourceAjaxServer.draw();
    });
</script>

</body>
<!-- end::Body -->
</html>
