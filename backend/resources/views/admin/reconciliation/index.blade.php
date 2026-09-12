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

            <div id="loader" class="form-group loader" style="display:none"> 
                    <div class="ml-4"><img src="{{ asset('img/loading.gif') }}" class="img-responsive" width=40 height=40></div>
            </div>
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                   Reconciliation
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Reconciliation</span>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">

                      {{--<form id="csvUploadForm" method="post" action="{{ route('ReconciliationController.uploadCsv') }}" enctype="multipart/form-data">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />

                            <input type="file" id="upload" name="vcsReport" style="visibility: hidden; width: 1px; height: 1px" />
                            <a href="#" class="btn btn-sm btn-elevate btn-brand btn-elevate" onclick="uploadCsv()" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Upload CSV"> <span class="kt-opacity-11" id="">Upload CSV</span>&nbsp; <i class=" flaticon-upload  kt-padding-l-5 kt-padding-r-0"></i> </a>
                        </form> --}}
                </div>
                {{--<div class="kt-subheader__wrapper">

                            <a href="#" class="btn btn-sm btn-elevate btn-brand btn-elevate" onclick="uploadCsv()" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Upload CSV"> <span class="kt-opacity-11" id="">Generate</span>&nbsp; <i class=" flaticon-upload  kt-padding-l-5 kt-padding-r-0"></i> </a>
                </div>--}}
              
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">

                        <div class="row kt-margin-b-20">

                            <div class="col-lg-5 kt-margin-b-10-tablet-and-mobile">
                                    <label>Start Date</label>
                                    <input id="startDate" type="text" class="form-control kt_datepicker_1 restrictDate" name="startDate" autocomplete="off" placeholder="Select date"/>

                                </div>  
                            <div class="col-lg-5 kt-margin-b-10-tablet-and-mobile">
                                    <label>End Date</label>
                                    <input id="endDate" type="text" class="form-control kt_datepicker_1 restrictDate" name="endDate" autocomplete="off" placeholder="Select date"/>

                            </div>  
                            <div class="col-lg-2 kt-margin-b-10-tablet-and-mobile">
                                    <button class="btn btn-brand kt-btn kt-btn--icon btn-sm " id="transactionRange">
                                            <span>
                                                <span>Send SMS</span>
                                            </span>
                                    </button>
                             </div>  
                         </div>  
                    <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="document_table">
                        <thead>
                        <tr>
                            <th><input type="checkbox" class="input-group kt-group-checkable" id="selectAllCheckBox" name="selectAllCheckBox" ></th>
                            <th>Customer Name</th>
                            <th>Cellphone</th>
                            <th>Policy Number</th>
                            <th>Reference Number</th>
                            <th>Description Of Goods</th>
                            <th>Amount</th>
                            <th>Transaction Settled</th>
                            <th>Bank Response</th>
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


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>
    "use strict";
    var KTDatatablesDataSourceAjaxServer = function() {

        var initTable1 = function() {
            var table = $('#document_table');

            // begin first table
            table.DataTable({
                responsive: true,
                searchDelay: 500, 
                language:{ 
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                ajax: '{!! route('reconciliation.data') !!}',
                columns: [
                    {data: 'checkBox'},
                    {data: 'customerName'},
                    {data: 'cellphone'},
                    {data: 'policyNumber'},
                    {data: 'ReferenceNumber'},
                    {data: 'DescriptionOfGoods'},
                    {data: 'Amount'},
                    {data: 'TransactionSettled'},
                    {data: 'BankResponse'},

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

   async function uploadCsv(){


        document.getElementById('upload').click();


        document.getElementById("upload").onchange = function() {
            document.getElementById("csvUploadForm").submit();
        };
         return false

    }

    
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

    $('#transactionRange').on("click", function() {


        var startDate = $('#startDate').val();
        var endDate = $('#endDate').val();

        console.log("Start Date: " +startDate);
        console.log("End Date: " +endDate);


        ajaxRequest = setTimeout(function(sn) {
            $.ajax({
                url: '{{ route('vcsTransactiondata') }}',
                data: {
                    "_token": "{{ csrf_token() }}",
                    "startDate": startDate,
                    "endDate": endDate
                },
                type: 'post',
                datatype : 'json',
                success: function (data) {

                    if( data.status === 'Successful'){
                        $('#loader').css('display','block');
                        window.location.reload();
                    } else {

                        console.log("Cant connect to vcs");
                    }
                }
            });
        }, 200);
        });

    $('.restrictDate').datepicker({
        format: "dd/mm/yyyy",
        autoclose: true,
        orientation: "bottom",
        endDate: "today"

    });
    </script>




</body>
</html>
