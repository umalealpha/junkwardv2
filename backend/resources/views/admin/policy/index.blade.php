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
                        Policy List
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a href="{{Route('admin.policy.index')}}" class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Policy</span> </a>
                    </div>
                </div>

                <div class="kt-subheader__toolbar">
                    {{--@can('policy-create')
                    <div class="kt-subheader__wrapper">
                        <a href="{{ URL::to('admin/policy/create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                    </div>
                    @endcan--}}
                    @can('policy-archive')
                    <div class="kt-subheader__wrapper">
                        <a href="{{ URL::to('admin/policy/viewArchivedPolicies') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="View Archived Policies"> <span class="kt-opacity-11" id="">Archived Policies</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                    </div>
                    @endcan
                </div>
            </div>
            <!-- end:: Subheader -->

            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <!--begin: Filter -->
                        <div class="row">
                            <div class="col-6">
                                <label>Filter By Policy Status</label>
                                <select name="policyStatus_filter" class="select2 form-control" id="policyStatus_filter">
                                    <option value="-1">All</option>
                                    <option style="text-transform: capitalize" value="0">In-Active</option>
                                    <option style="text-transform: capitalize" value="1">Activated</option>
                                    <option style="text-transform: capitalize" value="2">Cancel</option>
                                    <option style="text-transform: capitalize" value="3">Expired</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label>Filter By Product</label>
                                <select name="product_filter" class="select2 form-control" id="product_filter">
                                    <option value="-1">All</option>
                                    @foreach($products as $product)
                                        <option style="text-transform: capitalize" value="{!! $product->id !!}">{!! $product->name !!}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div><br>
                        <div class="row">
                            <div class="col-6 mt-2">
                               <!-- <div class="row"> -->
                                    <label class="">Filter By </label>
                                    <div>
                                        <select name="FilterBy" class="select2 form-control " id="FilterBy">
                                            <option value="-1">Select</option>
                                            <option style="text-transform: capitalize" value="cellphoneFilter">Customer CellPhone</option>
                                            <option style="text-transform: capitalize" value="referenceFilter">Reference Number</option>
                                         <!--   <option style="text-transform: capitalize" value="vehiclePlateFilter">Vehicle Plate Number</option> -->
                                        </select>
                                    </div>
                                <!-- </div> -->
                            </div>
                            <div class="col-5 mt-2">
                                <label>Filter By Value</label>
                                <input id="value_filter" type="text" class="form-control id-type validateGroup1" name="value_filter" placeholder="Please enter">
                            </div>
                            <div class="col-1">
                                <button id="search_value_filter" class="btn btn-primary" style="margin-top:33%">Search</button>
                            </div>
                        </div><br>
                        <div class="row">
                            @can('policy-Full List')
                            <div class="col-6 mt-2">
                                <label>Filter By Agent</label>
                                <select class="select2 form-control kt_selectpicker" id="agent_filter" name="agent_filter" data-live-search="true" title="Select Agent">
                                    <option value="-1">All</option>
                                    @foreach($agents as $agent)
                                        <option  value="{{$agent->id}}">{{$agent->firstName}} {{$agent->lastName}}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endcan
                            <div class="col-6 mt-2">
                                <label>Filter By Payment Method</label>
                                <select name="paymentMethod_filter" class="select2 form-control" id="paymentMethod_filter">
                                    <option value="-1">All</option>
                                    <option style="text-transform: capitalize" value="DPO">DPO</option>
                                    <option style="text-transform: capitalize" value="RealPay">RealPay</option>
                                    <option style="text-transform: capitalize" value="VCS">VCS</option>
                                    <option style="text-transform: capitalize" value="CASH">Cash</option>
                                    <option style="text-transform: capitalize" value="orangeMoney">Orange Money</option>
                                    <option style="text-transform: capitalize" value="N-Genius">N-Genius</option>
                                </select>
                            </div>
                        </div>
                        <br>
                        <div class="row">
                            <div class="col-6">
                                <label>Filter By Product Plan</label>
                                <select name="product_plan_filter" class="select2 form-control" id="product_plan_filter">
                                    <option value="-1">All</option>
                                    @foreach($product_plans as $productPlan)
                                        <option style="text-transform: capitalize" value="{!! $productPlan->id !!}">{!! $productPlan->slug !!}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <!--end: Filter -->

                        <div class="row" style="margin:10px">
                            <div class="col-6"></div>
                            <div class="col-6">
                                <span class="greendot"></span>  Low Risk &nbsp&nbsp&nbsp
                                <span class="graydot"></span>  Moderate Risk &nbsp&nbsp&nbsp
                                <span class="blackdot"></span>  High Risk &nbsp&nbsp&nbsp
                            </div>
                        </div>

                        <!--begin: Datatable -->
                        <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Policy Number</th>
                                <th>Customer Name</th>
                                <th>Agent Name</th>
                                <th>CellPhone Number</th>
                                <th>Product</th>
                                <th>Plan Name</th>
                                <th>Group</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                        </table>
                        <div id="jump-to-page" style="margin-bottom: 10px;">
                            Jump to page: <input type="number" id="jump-to-input" style="width: 50px;"> 
                            <button onclick="jumpToPage()">Go</button>
                        </div>
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
                    <a href="" id="submitType" type="button" class="btn btn-brand">Confirm</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="change_payment_status" tabindex="-1" role="dialog" aria-labelledby="user_change_payment_status" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Change Payment Status</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
            </div>
            <div class="modal-body">
              Are you sure you want change Policy Payment status to Success?
              <input type="hidden" name="policy_id" id="policyId" value=""/>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                <a href="" id="submitPaymentStatus" type="button" class="btn btn-brand">Confirm</a>
            </div>
            </div>
        </div>
    </div>
<div class="modal fade" id="archive_confirm" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content-archive">

        </div>
    </div>
</div>

</div>

@include('admin.layouts.scripts')


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>


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
        if($(this).is(".key_loss")) {
            $("#type").append($("<option></option>").attr("value", "key_loss").text("Loss of key"));
            count = count + 1;
            type = 'key_loss';
        }
        if($(this).is(".cellphone")) {
            $("#type").append($("<option></option>").attr("value", "cellphone").text("Cellphone"));
            count = count + 1;
            type = 'cellphone';
        }
        if($(this).is(".legal")) {
            $("#type").append($("<option></option>").attr("value", "Legal").text("Legal"));
            count = count + 1;
            type = 'Legal';
        }
        if($(this).is(".hospital_cash")) {
            $("#type").append($("<option></option>").attr("value", "hospital_cash").text("Hospital Cash"));
            count = count + 1;
            type = 'hospital_cash';
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
    function jumpToPage() {
        var table = $('#policy_table').DataTable(); 
        var pageNumber = $('#jump-to-input').val() - 1; 
        if (pageNumber >= 0 && pageNumber < table.page.info().pages) {
            table.page(pageNumber).draw(false); 
        } else {
            alert("Please enter a valid page number."); 
        }
    }

    "use strict";
    var policyStatus_filter = -1;
    var product_filter = -1;
    var FilterBy = -1;
    var value_filter = '';
    var agent_filter = -1;
    var lead_agent =-1;
    var paymentMethod_filter =-1;
    var product_plan_filter = -1;

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
                columnDefs: [
                    { width: 200, "targets": 5},
                    { width: 400, "targets": 9},
                ],
                serverSide: true,
                ajax: {
                    url: '{!! route('admin.policy.data') !!}',
                    data: function (d) {
                        d.policyStatus_filter = policyStatus_filter;
                        d.product_filter = product_filter;
                        d.FilterBy = FilterBy;
                        d.value_filter = value_filter;
                        d.agent_filter = agent_filter;
                        d.lead_agent =lead_agent;
                        d.paymentMethod_filter =paymentMethod_filter;
                        d.product_plan_filter = product_plan_filter;
                    }
                    },
                order: [0, 'DESC'],
                columns: [
                    {data: 'id'},
                    {data: 'view'},
                    {data: 'name'},
                    {data: 'agentName'},
                    {data: 'cellphone', "bVisible": false,},
                    {data: 'product_name'},
                    {data: 'productPlanName'},
                    {data: 'company'},
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
            },
            draw: function(){
                table.draw();
            }
        };
    }();

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

    $('#product_plan_filter').on('change',function(){
        product_plan_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $('#search_value_filter').on('click',function(){
        FilterBy = $('#FilterBy').val();
        value_filter = $('#value_filter').val();
        KTDatatablesDataSourceAjaxServer.draw();
    });
    $('#agent_filter').on('change',function(){
        agent_filter = $(this).val();
        console.log(agent_filter);
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $('#lead_agent').on('change',function(){
        lead_agent = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
        KTDatatablesDataSourceAjaxServer2.draw();
    });

    $('#paymentMethod_filter').on('change',function(){
        paymentMethod_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $("#policy_table").on("click", "a.confirm-archive" , function(event) {
        event.preventDefault();
        var policy_id = $(this).attr('value');
        $.ajax({
            url: '{{ route('admin.policy.confirm-archive') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": policy_id
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {
                $('#modal-content').append(append);
                var append = '<div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Archive Policy</h5>' +
                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> ' +
                    '</div> ' +
                    '<div class="modal-body"> ' +
                    '<p>'+data.body+'</p> ' +
                    '</div> ' +
                    '<div class="modal-footer"> ' +
                    '<button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button> ';

                if(data.status == 'success'){

                    var url = '{{ route("admin.policy.archive",":id") }}';
                    url = url.replace(':id', data.id);

                    append += '<a href="'+url+'" type="button" class="btn btn-brand">Archive</a></div>';

                }

                append += '</div>';
                $('#modal-content-archive').empty();
                $('#modal-content-archive').append(append);
                $('#archive_confirm').modal('show');
            },
        });
    });

</script>

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
<script>
 $("#policy_table").on("click", ".change_payment_status" , function(event) {
    var policy_id = $(this).val();
    // console.log(policy_id);
    $('#change_payment_status').modal('show');
    $(".modal-body #policyId").val( policy_id );

 });

$('#submitPaymentStatus').click(function (e) {
    e.preventDefault();
    var policy_id = $('#policyId').val();

    $.ajax({
        type: 'post',
        url: '{{ route('admin.policy.changePaymentStatus') }}',
        data: {
          "_token": "{{ csrf_token() }}",
          "policy_id":  policy_id
        },
        dataType: 'JSON',
        success: function (data) {
            console.log(data)
            if(data.status == 'success' ){
                $('#change_payment_status').modal('hide');
                location.reload();
            }
        },
        error: function (error){
            console.log(error);
            $('#change_payment_status').modal('close');
        }
    });

});

</script>

</body>
<!-- end::Body -->
</html>
