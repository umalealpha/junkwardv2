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
                    Customer KYC
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Customer KYC</span> </a>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <div class="row">
                        <div class="col-sm-12 mb-3">
                            <a href="{{ route('admin.sanctioned-customers') }}" class="btn btn-warning">
                                <i class="la la-shield"></i> View Sanctioned Customers
                            </a>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4 mb-2">
                            <label>Filter by Compliance status</label>
                            <select name="compliance_status" id="compliance_status" class="form-control id-type validateGroup1">
                                <option style="text-transform: capitalize" value="">Select Compliance Status</option>
                                <option style="text-transform: capitalize" value="0">Pending Verification</option>
                                <option style="text-transform: capitalize" value="1">Compliant</option>
                                <option style="text-transform: capitalize" value="2">Non-Compliant</option>
                                <option style="text-transform: capitalize" value="3">No Id - No Documents</option>
                            </select>
                        </div>
                        <div class="col-sm-4 mb-2">
                            <label>Filter by status</label>
                            <!-- {{-- <input id="customer_name" type="text" class="form-control id-type validateGroup1" name="customer_name" placeholder="Please enter customer name"> --}} -->
                            <select name="policy_status" id="policy_status" class="form-control id-type validateGroup1">
                                <option style="text-transform: capitalize" value="">Select</option>
                                <option style="text-transform: capitalize" value="Unchecked">Unchecked</option>
                                <option style="text-transform: capitalize" value="Approve">Approved</option>
                                <option style="text-transform: capitalize" value="Unapprove">Rejected</option>
                                <option style="text-transform: capitalize" value="Recheck">Recheck</option>
                                <option style="text-transform: capitalize" value="Recheck(KYC Expired)">Recheck(KYC Expired)</option>
                                <option style="text-transform: capitalize" value="Cancelled">Cancelled</option>
                                <option style="text-transform: capitalize" value="Renew">Renew</option>
                            </select>
                        </div>
                        <!-- {{-- <div class="col-sm-4">
                            <button  class="btn btn-primary" id="search_policy_status" style="margin-top: 2%;">Search</button>
                        </div> --}} -->
                        <!-- {{-- <div class="col-sm-4">
                            <label>Filter By customer name</label>
                            <input id="customer_name" type="text" class="form-control id-type validateGroup1" name="customer_name" placeholder="Please enter customer name">
                        </div>
                        <div class="col-sm-2">
                            <button id="search_customer_name" class="btn btn-primary" style="margin-top: 2%;">Search</button>
                        </div>

                        <div class="col-sm-4">
                            <label>Filter By Policy Number</label>
                            <input id="policy_number" type="text" class="form-control id-type validateGroup1" name="policy_number" placeholder="Please enter policy number">
                        </div>

                        <div class="col-sm-2">
                            <button id="search_policy_number" class="btn btn-primary" style="margin-top: 2%;">Search</button>
                        </div> --}} -->
                    </div>
                <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                        <thead>
                        <tr>
                            {{-- <th>Customer ID</th>
                            <th>Omang Number</th>
                            <th>Passport Number</th> --}}
                            <th>Customer Name</th>
                            <th>Omang(Front)</th>
                            <th>Omang(Back)</th>
                            <th>Passport</th>
                            <th>Drivers License</th>
                            <th>Proof Of Residence</th>
                            <th>Proof Of Income</th>
                            <th>Compliance</th>
                            <th>Status</th>
                            <th>Updated At</th>
                            <th>Action</th>
                            {{--<th>Created At</th>
                            <th>Updated At</th>--}}
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
    // var customer_name = $('#customer_name').val();
    // var policy_number = $('#policy_number').val();
    var status= $('#policy_status').val();
    var compliance_status= $('#compliance_status').val();
    var KTDatatablesDataSourceAjaxServer = function() {
        var table = '';
        var initTable1 = function() {
            table = $('#policy_table').DataTable({
                responsive: true,
                searchDelay: 500,
                processing: true,
                language:{
                    processing : $("#loader").show()
                },
                serverSide: true,
                ajax: {
                    url: "{!! route('admin.customerKycData') !!}",
                    data: function (d) {
                        // d.customer_name = customer_name;
                        // d.policy_number = policy_number;
                        d.status=status;
                        d.compliance_status=compliance_status;
                    }

                },
                order: [0, 'DESC'],
                columns: [
                    // {data: 'id'},
                    // {data: 'omangNumber'},
                    // {data: 'passportNumber'},
                    {data: 'customer_name'},
                    {data: 'omang'},
                    {data: 'omangBack'},
                    {data: 'passport'},
                    {data: 'driving_license'},
                    {data: 'proof_residence'},
                    {data: 'proof_income'},
                    {data: 'compliance'},
                    {data: 'status'},
                    {data:'updated_at'},
                    {data: 'action'},
                    /*{data: 'created_at'},
                    {data: 'updated_at'},'proof_residence', 'proof_income', 'driving_license'*/
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
    // $('#search_customer_name').on('click',function(){
    //     customer_name = $('#customer_name').val();
    //     policy_number = $('#policy_number').val();
    //     KTDatatablesDataSourceAjaxServer.draw();
    // });

    // $('#search_policy_number').on('click',function(){
    //     customer_name = $('#customer_name').val();
    //     policy_number = $('#policy_number').val();
    //     KTDatatablesDataSourceAjaxServer.draw();
    // });


    $('#policy_status').on('change',function(){
        status = $(this).val();

        //policy_number = $('#policy_number').val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $('#compliance_status').on('change',function(){
        compliance_status = $(this).val();
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
