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
    <!--If Password default, show edit details -->
     @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Vehicle Preinspection
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Vehicle Preinspection</span> </a>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <div class="row">
                        <div class="col-sm-4">
                            <label>Filter By customer name</label>
                            <input id="customer_name" type="text" class="form-control id-type validateGroup1" name="customer_name" placeholder="Please enter customer name">
                        </div>
                        <div class="col-sm-2">
                            <button id="search_customer_name" class="btn btn-primary" style="margin-top: 10%;">Search</button>
                        </div>

                        <div class="col-sm-4">
                            <label>Filter By Policy Number</label>
                            <input id="policy_number" type="text" class="form-control id-type validateGroup1" name="policy_number" placeholder="Please enter policy number">
                        </div>

                        <div class="col-sm-2">
                            <button id="search_policy_number" class="btn btn-primary" style="margin-top: 10%;">Search</button>
                        </div>
                        <div class="col-sm-4">
                            <label>Filter By Vehicle Plate</label>
                            <input id="vehicle_plate" type="text" class="form-control id-type validateGroup1" name="vehicle_plate" placeholder="Please enter vehicle plate number">
                        </div>

                        <div class="col-sm-2">
                            <button id="search_vehicle_plate" class="btn btn-primary" style="margin-top: 10%;">Search</button>
                        </div>
                    </div>
<br>
                    <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Policy ID</th>
                            <th>Make</th>
                            <th>Model</th>
                            <th>Manufacturing Year</th>
                            <th>Vehicle Plate Number</th>
                            <th>Front</th>
                            <th>Back</th>
                            <th>Right</th>
                            <th>left</th>
                            <th>Vehicle Registration Book</th>
                            <th>Vehicle Invoice</th>
                            <th>Status</th>
                            <th>Action</th>
                            <th>Created At</th>
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

        $("#type").find("option:gt(0)").remove();
        var count = 0;
        var type;

        if($(this).is(".life")) {
            $("#type").append($("<option></option>").attr("value", "Life").text("Life"));
            count = count + 1;
            type = 'Life';
        }
        if($(this).is(".glass")) {
            $("#type").append($("<option></option>").attr("value", "Glass").text("Glass"));
            count = count + 1;
            type = 'Glass';
        }
        if($(this).is(".accident")) {
            $("#type").append($("<option></option>").attr("value", "Accident").text("Motor Accident"));
            count = count + 1;
            type = 'Accident';
        }

        //If Only one option then don't open modal pop up
        if(count == 1)
        {
            window.location.href = $(this).attr('href')+ '/' + type;
        } else {
            $("#submitType").attr("href", $(this).attr('href'));
            $('#claimTypeModal').modal('show');
        }
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

    "use strict";
    var customer_name = $('#customer_name').val();
    var policy_number = $('#policy_number').val();
    var vehicle_plate = $('#vehicle_plate').val();
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
                ajax: {
                    url: '{!! route('admin.customerInspectionData') !!}',
                    data: function (d) {
                        d.customer_name = customer_name;
                        d.policy_number = policy_number;
                        d.vehicle_plate = vehicle_plate;
                    }

                },
                order: [0, 'DESC'],
                columns: [
                    {data: 'id'},
                    {data: 'policy_id'},
                    {data: 'make'},
                    {data: 'model'},
                    {data: 'year'},
                    {data: 'vehiclePlate'},
                    {data: 'front'},
                    {data: 'back'},
                    {data: 'right'},
                    {data: 'left'},
                    {data: 'vehicleRegistration'},
                    {data: 'vehicle_valuation'},
                    {data: 'status'},
                    {data: 'actions'},
                    {data: 'created_at'},


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

    $('#search_customer_name').on('click',function(){
        policy_number = $('#policy_number').val();
        customer_name = $('#customer_name').val();
        vehicle_plate = $('#vehicle_plate').val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $('#search_vehicle_plate').on('click',function(){
        policy_number = $('#policy_number').val();
        customer_name = $('#customer_name').val();
        vehicle_plate = $('#vehicle_plate').val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $('#search_policy_number').on('click',function(){
        policy_number = $('#policy_number').val();
        customer_name = $('#customer_name').val();
        vehicle_plate = $('#vehicle_plate').val();
        KTDatatablesDataSourceAjaxServer.draw();
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
    </script>

</body>
<!-- end::Body -->
</html>
