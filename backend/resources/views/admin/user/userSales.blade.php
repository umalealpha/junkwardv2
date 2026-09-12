<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="https://cdn.datatables.net/buttons/1.6.0/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css" /> 
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<style>

    .FontColorBlue{
        color:cornflowerblue;
    }
    button.dt-button{
        background-color: red !important;
    }
</style>
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
                    User Policy Sales List
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{Route('admin.policy.index')}}" class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Report</span> </a>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                {{-- <div class="kt-subheader__wrapper">
                     <a href="{{ URL::to('admin/policy/create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                 </div>--}}
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                        <thead>
                        <tr>
                            <th>Policy Agent Id</th>
                            <th>Names</th>
                            <th>Total Sales</th>
                           
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

<!-- end:: Root -->



<!-- begin:: Scrolltop -->
<div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
<!-- end:: Scrolltop -->

</div> 

@include('admin.layouts.scripts')


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="https://cdn.datatables.net/buttons/1.6.0/js/dataTables.buttons.min.js" ></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.0/js/buttons.html5.min.js"></script>  


<script>


    "use strict";
    var policyStatus_filter = -1;
    var product_filter = -1;
    var product_plan = -1;
    var policy_number = -1;
    var payment_status = -1;
    var filterDateFrom = -1;
    var filterDateto =-1;
    var KTDatatablesDataSourceAjaxServer = function() {
        var table = '';
        var initTable1 = function() {
            table = $('#policy_table').DataTable({
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
                ajax: {
                    url: '{!! route('user.userPolicySalesData') !!}',
                    data: function (d) {
                        d.policyStatus_filter = policyStatus_filter;
                        d.product_filter = product_filter;
                        d.product_plan = product_plan;
                        d.policy_number = policy_number;
                        d.payment_status = payment_status;
                        d.filterDateFrom = filterDateFrom;
                        d.filterDateto = filterDateto;
                    }
                },
              
                columns: [
                    {data: 'agent_id'},
                    {data: 'name'},
                    {data: 'total_agent_sale'},
                  

                ], 
                error:function(error){
                    console.log(error);
                },

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

  

   

    "use strict";
    // Class definition
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

    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
       
    });
    $('#policyStatus_filter').on('change',function(){
        policyStatus_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
       
    });

    $('#product_filter').on('change',function(){
        product_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
       
    });
    $('#product_plan').on('change',function(){
        product_plan = $(this).val();
        /* alert(product_plan);*/
        KTDatatablesDataSourceAjaxServer.draw();
        KTDatatablesDataSourceAjaxServer2.draw();
    });
    $('#policy_number').keyup(function(){
        policy_number = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
        
    });
    $('#payment_status').on('change',function(){
        payment_status = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
        KTDatatablesDataSourceAjaxServer2.draw();
    });
    $('#filterDateFrom').on('change',function(){
        filterDateFrom = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
      
    });
    $('#filterDateto').on('change',function(){
        filterDateto = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
      
    });

</script>



</body>
<!-- end::Body -->
</html>
