<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
 <link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" /> 
 <link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
 <link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="https://cdn.datatables.net/buttons/1.6.0/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css" />

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
                        Policy Reminder Sms Log
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a href="{{Route('admin.policyReminderSmsLog')}}" class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Policy Reminder Sms Log</span> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader --> 
            
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">

                        <!--begin: Filter -->
                        {{--<div class="row">--}}
                            {{--<div class="col-sm-6">--}}
                                {{--<label>Filter By Policy Status</label>--}}
                                {{--<select name="policyStatus_filter" class="form-control" id="policyStatus_filter">--}}
                                    {{--<option value="-1">All</option>--}}
                                    {{--<option style="text-transform: capitalize" value="0">Deactivated</option>--}}
                                    {{--<option style="text-transform: capitalize" value="1">Activated</option>--}}
                                    {{--<option style="text-transform: capitalize" value="2">Cancel</option>--}}
                                {{--</select>--}}
                            {{--</div>--}}
                            {{--<div class="col-sm-6">--}}
                                {{--<label>Filter By Product</label>--}}
                                {{--<select name="product_filter" class="form-control" id="product_filter">--}}
                                    {{--<option value="-1">All</option>--}}
                                    {{--@foreach($products as $product)--}}
                                        {{--<option style="text-transform: capitalize" value="{!! $product->id !!}">{!! $product->name !!}</option>--}}
                                    {{--@endforeach--}}
                                {{--</select>--}}
                            {{--</div> --}}
                            {{--<div class="col-sm-5">--}}
                                {{--<label>Filter By Customer CellPhone</label>--}}
                                {{--<input id="cellphone_filter" type="text" class="form-control id-type validateGroup1" name="cellphone_filter" placeholder="Please enter cellphone number">--}}
                            {{--</div>--}}
                            {{--<div class="col-sm-1">--}}
                                {{--<button id="search_cellphone" class="btn btn-primary" style="margin-top:33%">Search</button>--}}
                            {{--</div>--}}
                           {{-- <div class="col-sm-5">--}}
                                {{--<label>Filter By Reference Number</label>--}}
                                {{--<input id="reference_filter" type="text" class="form-control id-type validateGroup1" name="reference_filter" placeholder="Please enter reference number">--}}
                            {{--</div>--}}
                            {{--<div class="col-sm-1">--}}
                                {{--<button id="search_reference" class="btn btn-primary" style="margin-top:33%">Search</button>--}}
                            {{--</div>--}}
                            {{--<div class="col-sm-6">--}}
                                {{--<label>Filter By Agent</label>--}}
                                {{--<select class="form-control kt_selectpicker" id="agent_filter" name="agent_filter" data-live-search="true" title="Select Agent">--}}
                                    {{--<option value="-1">All</option>--}}
                                    {{--@foreach($agents as $agent)--}}
                                        {{--@if($agent->user != null)--}}
                                            {{--<option  value="{{$agent->agent_id}}">{{$agent->user->firstName}} {{$agent->user->lastName}}</option>--}}
                                        {{--@endif--}}
                                    {{--@endforeach--}}
                                {{--</select>--}}
                            {{--</div>--}}
                        {{--</div>--}}
                        {{--<br>--}}
                        <!--end: Filter -->


                        <!--begin: Datatable -->
                        <table class="table table-striped table-bordered table-hover table-checkable" id="policy_reminder_smslog_table">
                            <thead>
                            <tr>
                                <th>Policy Number</th>
                                <th>Cellphone</th>
                                <th>Status</th>
                                <th>Sms Text</th>
                                <th>Sms send count</th>
                                <th>Is limit send</th>
                                <th>Created By</th>
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
        //commented temporary_By Karthik
       /* if($(this).is(".key_loss")) {
            $("#type").append($("<option></option>").attr("value", "key_loss").text("Loss of key"));
            count = count + 1;
            type = 'key_loss';
        }*/
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

    var oldExportAction = function (self, e, dt, button, config) {
        if (button[0].className.indexOf('buttons-excel') >= 0) {
            if ($.fn.dataTable.ext.buttons.excelHtml5.available(dt, config)) {
                $.fn.dataTable.ext.buttons.excelHtml5.action.call(self, e, dt, button, config);
            }
            else {
                $.fn.dataTable.ext.buttons.excelFlash.action.call(self, e, dt, button, config);
            }
        } else if (button[0].className.indexOf('buttons-print') >= 0) {
            $.fn.dataTable.ext.buttons.print.action(e, dt, button, config);
        }
    };

    var newExportAction = function (e, dt, button, config) {
        var self = this;
        var oldStart = dt.settings()[0]._iDisplayStart;

        dt.one('preXhr', function (e, s, data) {
            // Just this once, load all data from the server...
            data.start = 0;
            data.length = 2147483647;

            dt.one('preDraw', function (e, settings) {
                // Call the original action function
                oldExportAction(self, e, dt, button, config);

                dt.one('preXhr', function (e, s, data) {
                    // DataTables thinks the first item displayed is index 0, but we're not drawing that.
                    // Set the property to what it was before exporting.
                    settings._iDisplayStart = oldStart;
                    data.start = oldStart;
                });

                // Reload the grid with the original page. Otherwise, API functions like table.cell(this) don't work properly.
                setTimeout(dt.ajax.reload, 0);

                // Prevent rendering of the full data to the DOM
                return false;
            });
        });

        // Requery the server with the new one-time export settings
        dt.ajax.reload();
    };

    "use strict";
    var policyStatus_filter = -1;
    var product_filter = -1; 
    var cellphone_filter = '';
    var reference_filter = '';
    var agent_filter = -1;
    var lead_agent =-1;
    var KTDatatablesDataSourceAjaxServer = function() {
        var table = '';
        var initTable1 = function() {
            table = $('#policy_reminder_smslog_table').DataTable({
                responsive: true,
                searchDelay: 500,
                processing: true, 
                language:{
                    processing : $("#loader").show()
                },
                serverSide: true,
                ajax: {
                    url: '{!! route('admin.policyReminderSmsLog.data') !!}',
//                    data: function (d) {
//                        d.policyStatus_filter = policyStatus_filter;
//                        d.product_filter = product_filter;
//                        d.cellphone_filter = cellphone_filter;
//                        d.reference_filter = reference_filter;
//                        d.agent_filter = agent_filter;
//                        d.lead_agent =lead_agent;
//                    }
                    },
//                lengthMenu: [[25, 100, -1], [25, 100, "All"]],
                pageLength: 25,
//                dom: 'Bfrtip',
//                buttons: [
//                    {
//                        extend: 'excel',
//                        messageTop:'This excel is about policy payment status information',
//                        action: newExportAction
//                    },
//                ],
                order: [0, 'DESC'],
                columns: [
                    {data: 'policyNumber'},
                    {data: 'cellphone'},
                    {data: 'sms_status'},
                    {data: 'sms_text'},
                    {data: 'sms_limit'},
                    {data: 'is_limit_send'},
                    {data: 'created_by'},
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
    $('#policyStatus_filter').on('change',function(){
        policyStatus_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $('#product_filter').on('change',function(){
        product_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });
    $('#search_cellphone').on('click',function(){
        cellphone_filter = $('#cellphone_filter').val();
        KTDatatablesDataSourceAjaxServer.draw();
    });
    $('#search_reference').on('click',function(){
        reference_filter = $('#reference_filter').val();
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
    console.log(policy_id);
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
