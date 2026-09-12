<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
 <link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" /> 
 <link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" /> 
 <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

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
    <div class="alert alert-success mt-2  fade show"  role="alert" id="success" style="display:none;">
        {{ Session::get('success') }}
        <p class="text ">Success</p>
        <button type="button" class="close" data-dismiss="alert">×</button>
    </div>
    <div class="alert alert-warning mt-2  fade show" role="alert" id="error" style="display:none;">

        <p class="error-message"></p>
        <button type="button" class="close" data-dismiss="alert">×</button>
    </div>
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    SMS List
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <a href="{{Route('admin.policy.index')}}" class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">SMS</span> </a>
                </div>
            </div>
            <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                   <!-- <a href="{{ URL::to('admin/policy/create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>-->
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid">
                <div class="kt-portlet kt-portlet--mobile">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Send SMS form
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet__body">
                    <form class="kt-form kt-form--fit kt-margin-b-20">
                            <div class="row kt-margin-b-20">
                            <div class="col-lg-3 kt-margin-b-10-tablet-and-mobile">
                                <label>Filter By Policy Status</label>
                                <select name="policyStatus_filter" class="form-control" id="policyStatus_filter" multiple>
                                    <option value="all" selected>All</option>
                                    <option style="text-transform: capitalize" value="0">Deactivated</option>
                                    <option style="text-transform: capitalize" value="1">Activated</option>
                                    <option style="text-transform: capitalize" value="2">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-lg-3 kt-margin-b-10-tablet-and-mobile">
                                <label>Filter By Product</label>
                                <select name="product_filter" class="form-control" id="product_filter" multiple>
                                    <option value="all" selected>All</option>
                                    @foreach($products as $product)
                                        <option style="text-transform: capitalize" value="{!! $product->id !!}">{!! $product->name !!}</option>
                                    @endforeach
                                </select>
                            </div> 
                            <div class="col-lg-3 kt-margin-b-10-tablet-and-mobile">
                                <label>Filter By Transaction Status</label>
                                <select name="transaction_filter" class="form-control" id="transaction_filter">
                                    <option value="all" selected>All</option>
                                    <option style="text-transform: capitalize" value="0">Success</option>
                                    <option style="text-transform: capitalize" value="1">Failed</option> 
                                    <option style="text-transform: capitalize" value="2">Pending</option>
                                    <option style="text-transform: capitalize" value="4">Pending + Failed</option> 
                                </select>
                            </div>  
                            <div class="col-lg-3 kt-margin-b-10-tablet-and-mobile">
                                    <label>Sms Template</label>
                                    <select name="sms_template" class="form-control" id="sms_template">
                                            <option value="all">Select</option>
                                       @foreach ($smstemplates as $template) 
                                       <option value="{{ $template->id }}">{{ $template->name }}</option>
                                           
                                       @endforeach
                                    </select>
                            </div>  
                            
                    </div>  
                   
                    <!--end: Filter -->
                 
                        <div class="row kt-margin-b-20">  
                                <div class="col-lg-3 kt-margin-b-10-tablet-and-mobile">
                                        <label>Filter By policy created Date / SMS sent date</label>
                                        <select name="filterByPolicySMS" class="form-control" id="filterByPolicySMS">
                                                <option value="select">Select</option>
                                            <option style="text-transform: capitalize" value="0">Policy Log</option>
                                            <option style="text-transform: capitalize" value="1">SMS log</option>
                                        </select>
                                    </div> 
                            
                            <div class="col-lg-3 kt-margin-b-10-tablet-and-mobile"> 
                                <label>Date 1</label>
                                <input type="text" class="form-control kt_datepicker_1 date_entry" name="before_date" id="before_date_entry" autocomplete="off" placeholder="Select date"/>
                            </div> 

                            <div class="col-lg-3 kt-margin-b-10-tablet-and-mobile"> 
                                <label>Date 2</label>
                                <input type="text" class="form-control kt_datepicker_1 date_entry" name="after_date" id="after_date_entry" autocomplete="off" placeholder="Select date"/>
                            </div>  

                            <div class="col-lg-3 kt-margin-b-10-tablet-and-mobile"> 
                                <label>Customer KYC status</label>
                                <select name="KYCstatus" class="form-control" id="KYCstatus">
                                    <option value="select">Select</option>
                                <option style="text-transform: capitalize" value="0">Not done</option>
                                <option style="text-transform: capitalize" value="1">Done</option>
                            </select>
                            </div> 
                        </div>  
                        <div class="row kt-margin-b-20">  
                            <div class="col-lg-3 kt-margin-b-10-tablet-and-mobile">  
                                    <label for="">SMS Sent</label>
                                    <input type="text" class="form-control sms_sent" id="sms_sent" title="Please choose column" name="sms_sent" data-live-search="true"> 
                            </div> 
                            <div class="col-lg-3 kt-margin-b-10-tablet-and-mobile"> 
                                    <label for="">Condition</label>
                                    <select class="form-control kt_selectpicker condition_compare" title="Please choose condition" name="condition_compare" data-live-search="true"> 
                                        <option value="=">equals</option>  
                                        <option value="!=">not equal to</option> 
                                        <option value="<">less than</option> 
                                        <option value=">">greater than</option> 
                                        <option value="<=">less than equal to</option> 
                                        <option value=">=">greater than equal to</option>    
                                    </select>
                                </div> 
                            </div>
                                <div class="kt-separator kt-separator--md kt-separator--dashed"></div>
                                <div class="row">
                                    <div class="col-sm-4">
                                        <button class="btn btn-brand kt-btn kt-btn--icon btn-lg" id="sendSMS">
                                            <span>
                                                <span>Send SMS</span>
                                            </span>
                                        </button>
                                        &nbsp;&nbsp;
                                        <button class="btn btn-secondary kt-btn kt-btn--icon btn-lg" id="kt_reset">
                                            <span>
                                                <i class="la la-close"></i>
                                                <span>Reset</span>
                                            </span>
                                        </button>
                                    </div>
                                    <div class="col-sm-6"> 
                                            <div class="ml-4 loader" style="display:none"><img src="{{ asset('img/loading.gif') }}" class="img-responsive" width=40 height=40></div>
                                            <div class="input-group">
                                                    <div class="input-group-prepend"><span class="input-group-text"><i class="la la-phone"></i></span></div>
                                                    <input type="text" class="form-control col-sm-3 sendTestSMSCellphone" value="" name="testPhone" id='' placeholder="Phone" aria-describedby="basic-addon1">
                                                    &nbsp;&nbsp;
                                                    <button class="btn btn-brand kt-btn kt-btn--icon " id="sendTestSms"> 
                                                            <span> 
                                                                <span>Send Test SMS</span>
                                                            </span>
                                                        </button>
                                                        
                                                </div>
                                        </div>
                                        <div class="col-sm-2">
                                        <div class="dropdown dropdown-inline">
                                                <button type="button" class="btn btn-brand btn-md" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="la la-plus"></i> Export
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <ul class="kt-nav">
                                                        <li class="kt-nav__section kt-nav__section--first">
                                                            <span class="kt-nav__section-text">Export Tools</span>
                                                        </li>
                                                        <li class="kt-nav__item">
                                                            <a href="#" class="kt-nav__link" id="export_print">
                                                                <i class="kt-nav__link-icon la la-print"></i>
                                                                <span class="kt-nav__link-text">Print</span>
                                                            </a>
                                                        </li>
                                                        <li class="kt-nav__item">
                                                            <a href="#" class="kt-nav__link" id="export_copy">
                                                                <i class="kt-nav__link-icon la la-copy"></i>
                                                                <span class="kt-nav__link-text">Copy</span>
                                                            </a>
                                                        </li>
                                                        <li class="kt-nav__item">
                                                            <a href="#" class="kt-nav__link" id="export_excel">
                                                                <i class="kt-nav__link-icon la la-file-excel-o"></i>
                                                                <span class="kt-nav__link-text">Excel</span>
                                                            </a>
                                                        </li>
                                                        <li class="kt-nav__item">
                                                            <a href="#" class="kt-nav__link" id="export_csv">
                                                                <i class="kt-nav__link-icon la la-file-text-o"></i>
                                                                <span class="kt-nav__link-text">CSV</span>
                                                            </a>
                                                        </li>
                                                        <li class="kt-nav__item">
                                                            <a href="#" class="kt-nav__link" id="export_pdf">
                                                                <i class="kt-nav__link-icon la la-file-pdf-o"></i>
                                                                <span class="kt-nav__link-text">PDF</span>
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>              
                                        </div>  
                            </div>  
                    </form>
                   
                  
                    <div class="kt-separator kt-separator--border-dashed kt-separator--space-md"></div>
                   
                    <!--begin: Datatable " sms_table --> 
                    <table class="table table-striped table-bordered table-hover mt-3" id="sms_table">
                            <thead>
                            <tr>    
                                <th><input type="checkbox" class="input-group kt-group-checkable" id="selectAllCheckBox" name="selectAllCheckBox" ></th> 
                                <th>Policy Number</th>
                                <th>Customer Name</th> 
                                <th>Customer Cellphone</th>  
                                <th>KYC Status</th> 
                                <th>Product</th> 
                                <th>Plan</th>
                                <th>Premium</th> 
                                <th>Created at</th> 
                                <th>No. of SMS Sent</th> 
                                <th>Date of Last SMS</th>
                            </tr>
                            </thead>  
                        </table>
                    
                    <!--end: Datatable -->
               
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
<script src="{{ asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script> 
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
 
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/2.5.0/jszip.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.20/js/dataTables.bootstrap4.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.6.1/js/dataTables.buttons.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.6.1/js/buttons.bootstrap4.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.6.1/js/buttons.colVis.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.6.1/js/buttons.flash.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.6.1/js/buttons.html5.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.6.1/js/buttons.print.js"></script>

<script> 
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

     
</script>
<script>

    "use strict";
    var policyStatus_filter = 'all';
    var product_filter = 'all';  
    var transaction_filter = 'all'; 
    var sms_sent = null;
    var policySmsFilter = null; 
    var sms_template_id = null;
    var before_date = null; 
    var after_date = null; 
    var KycStatus = null; 
    var KTDatatablesDataSourceAjaxServer = function() {
         
        var table = '';
          var initTable1 = function() {
            table = $('#sms_table').DataTable({
                dom: 'frtip',
                     buttons: ['print','copy', 'excel','csv', 'pdf'],
                responsive: true,
                searchDelay: 500, 
                language:{ 
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true, 
                ordering: false, 
                async : false,
              
                ajax: {
                    url: '{!! route('admin.policy.sendSMSData') !!}',
                    data: function (d) {
                        d.policyStatus_filter = policyStatus_filter;
                        d.product_filter = product_filter; 
                        d.transaction_filter = transaction_filter; 
                        d.policySmsFilter = policySmsFilter;
                        d.sms_template_id = sms_template_id;
                        d.KycStatus = KycStatus;
                        d.before_date = before_date; 
                        d.after_date = after_date; 
                        d.sms_sent=sms_sent;
                    },
                    error: function(jqXHR, exception) { 
                       
                    }

                },
                //order: [0, 'DESC'],
                columns: [
                    {data: 'sendId'}, 
                    {data: 'policyNumber'},
                    {data: 'name'}, 
                    {data: 'cellphone'},  
                    {data: 'KycStatus'},  
                    {data: 'product_name'},
                    {data: 'plan_name'},
                    {data: 'premium'}, 
                    {data: 'created_at'},
                    {data: 'sms_count'}, 
                    {data: 'sms_date_entry'}, 
                ], 
                columnDefs: [{
                targets: 4,
                render: function(t, e, a, n) {
                    var s = {
                        1: {
                            title: "Done",
                            class: " kt-badge--success"
                        },
                        0: {
                            title: "Not Done",
                            class: " kt-badge--danger"
                        }
                    };
                    return void 0 === s[t] ? t : '<span class="kt-badge ' + s[t].class + ' kt-badge--inline kt-badge--pill">' + s[t].title + "</span>"
                }
            }]
            }),table.on("change", ".kt-group-checkable", function() {
            var table = $(this).closest("table").find("td:first-child .kt-checkable"),
                e = $(this).is(":checked");
            $(table).each(function() {
                e ? ($(this).prop("checked", !0), $(this).closest("tr").addClass("active")) : ($(this).prop("checked", !1), $(this).closest("tr").removeClass("active"))
            })
        }), table.on("change", "tbody tr .kt-checkbox", function() {
            $(this).parents("tr").toggleClass("active")
        });
         $('#export_print').on('click', function(e) {
			e.preventDefault();
			table.button(0).trigger();
		});

		$('#export_copy').on('click', function(e) {
			e.preventDefault();
			table.button(1).trigger();
		});

		$('#export_excel').on('click', function(e) {
			e.preventDefault();
			table.button(2).trigger();
		});

		$('#export_csv').on('click', function(e) {
			e.preventDefault();
			table.button(3).trigger();
		});

		$('#export_pdf').on('click', function(e) {
			e.preventDefault();
			table.button(4).trigger();
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
    $('#transaction_filter').on('change',function(){
        transaction_filter = $(this).val(); 
        
        KTDatatablesDataSourceAjaxServer.draw();
    });  

    $('#KYCstatus').on('change',function(){
        KycStatus = $(this).val(); 
        
        KTDatatablesDataSourceAjaxServer.draw();
    });  

    $('#sms_template').on('change',function(){
        sms_template_id = $(this).val();   
        if(sms_template_id == 'all'){  
            $('#sendSMS').prop('disabled', true);
            KTDatatablesDataSourceAjaxServer.draw();
        }else{  
            $('#sendSMS').prop('disabled', false);
            KTDatatablesDataSourceAjaxServer.draw();
        }
    });  
    $('#filterByPolicySMS').on('change',function () {   
        
        if(checkDropdowns()){ 
            policySmsFilter = $(this).val();
            KTDatatablesDataSourceAjaxServer.draw();
        }
         
    }); 
    $('#before_date_entry').on('change',function (e) {   
      
        if(checkDropdowns()){
            before_date = $(this).val(); 
            KTDatatablesDataSourceAjaxServer.draw();
        }
             
  }); 
  $('#after_date_entry').on('change',function (e) {   
        
    if(checkDropdowns()){  
        after_date = $(this).val(); 
        KTDatatablesDataSourceAjaxServer.draw();
    }
           
  }); 
  
    $('#sms_sent').on('change',function (e) {   
        
        sms_sent = $('#sms_sent').val(); 
        if(sms_sent != null){  
            KTDatatablesDataSourceAjaxServer.draw();
           
         }
        
    }); 
    
    function checkDropdowns(){
        policySmsFilter = $('#filterByPolicySMS').val();
        before_date = $('#before_date_entry').val();
        after_date = $('#after_date_entry').val();
        if(policySmsFilter != 'select' && before_date != ''  && after_date != '' ){

            before_date = new Date(before_date);
            after_date = new Date(after_date);
            if(after_date < before_date){
                alert(' After date should be greater than before date.')
                $('#before_date_entry').val('');
                $('#after_date_entry').val('');
                return false;
            }
             
            return true;
        }
        return false;
    }
   

</script>  


<script>  
//select all, unselect all
/* $("#selectAllCheckBox").click( function(e){
    var isChecked = $("#selectAllCheckBox").val();
    if(isChecked) {
		$('.kt-checkbox').each(function() { 
			this.checked = true; 
        });
	}  else {
		$('.kt-checkbox').each(function() { 
			this.checked = false;
		});
	} 

});
 */

</script> 

<script>  

/* $('#transaction_filter').on('change',function(){
   var transaction_filter = $(this).val(); 
        
      
        $.ajax({
            type: 'get',
            url: '{!! route('admin.policy.sendSMSData') !!}',
            data: {
                "transaction_filter": transaction_filter,
            },
            dataType: 'JSON',
            success: function (data) {
                
            },
            error:function(error){ 
               
            }
        });
       
});
 */
    

</script>

<script> 

    function smsSuccess(){ 
        Toastify({
            text: "InfoBip Api is working fine",
            duration: 6000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            center: true, // `true` or `false`
            backgroundColor: "#1fcf08",
        }).showToast();   
    }  
    function smsFailed(){ 
        Toastify({
            text: "Problem with SMS service please try again later",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            center: true, // `true` or `false`
            backgroundColor: "#CC0000",
        }).showToast();   
    }

 $(document).ready(function () { 
    $('#sendSMS').prop('disabled', true);
    //button to  send sms 
    
    $('#sendSMS').click(function (e) { 
        e.preventDefault();     
       
             
        var sms_template = $('#sms_template').val();   // get the sms template id
        var policyId =[]; //policy id array
        var policyNumber = [] ;  //policyNumber array
        var customerNames =[]; //customerNames array
        var customerCellphone = []; //customerCellphone array
        var product =[] ; //product array
        var plan =[]; //plan array
        var premium= [];  //premium array
        var data =[]; //  final array to store JSON object
        
       
        //get the checked values
        $("#sms_table input[type=checkbox]:checked").each(function () {

           // get the selected ids
            policyId.push($(this).attr('data-policyid'));  
            //
             if ($(this).prop('checked')) { 

                 policyNumber.push($(this).closest('tr').find('td:eq(1)').text());  
                 customerNames.push($(this).closest('tr').find('td:eq(2)').text()); 
                 customerCellphone.push($(this).closest('tr').find('td:eq(3)').text());  
                 product.push($(this).closest('tr').find('td:eq(4)').text());  
                 plan.push($(this).closest('tr').find('td:eq(5)').text());  
                 premium.push($(this).closest('tr').find('td:eq(6)').text());  
            
                 var len = policyId.length; // NB: aray length will always be the same, as
                 for (var x = 0; x < len; x++) {
                        var element = { //create the JSON obejct using the arrays
                        "policyId" : policyId[x], 
                        "policyNumber" :policyNumber[x],
                        "customerNames"    : customerNames[x],
                        "customerCellphone"    : customerCellphone[x], 
                        "product"    : product[x],
                        "plan"    : plan[x],
                        "premium"    : premium[x],
                        "sms_template_id" : sms_template
                    }; 
                    
                 } 
                 data.push(element); // push the element to the final array 
             } 
        });  
        //data.push(sms_template);
       
        //ajax call to send the sms 
        if(data.length === 0){  
           
            Toastify({
                        text: "Please select Policy Number to send SMS",
                        duration: 4000,
                        newWindow: true,
                        gravity: "top", // `top` or `bottom`
                        center: true, // `true` or `false`
                        backgroundColor: "#CC0000",
                    }).showToast();   
        }else{ 
            
            $.ajax({
                type: 'post',
                url: '{{ route('sendBulkSms') }}',
                data: { 
                    "_token": "{{ csrf_token() }}", 
                    "data" : data,   
                    "sms_template_id" : sms_template,
            
                },
                dataType: 'JSON',
                success: function (data) {
                    console.log(data);
                    if(data.status == 'success'){ 
                        //$("#success").css("display", "block");  
                        // Move element and append to body
                        smsSuccess();
                    
                    }else{  
                        smsFailed(); 
                    
                    }
            
                }, 
                error:function(error){ 
                    console.log(error);
                // $("#error").css("display", "block");  
                smsFailed();
                
                }
             });
        }
      
    }); 

    //send Test SMS 
    $('#sendTestSms').click(function (e) { 
        e.preventDefault(); 
        var testSMScellphone = $('.sendTestSMSCellphone').val();  
        console.log(testSMScellphone);

        $.ajax({
            type: 'post', 
            beforeSend: function(){ 
                $('.loader').css('display', 'block');
            },
            url: '{{ route('testInfobibSMS') }}',
            data: {  
                "_token": "{{ csrf_token() }}",
                "cellphone" :testSMScellphone,
            },
            dataType: 'JSON',
            success: function (data) {
                console.log(data[0]); 
                
                if(data[0] == 200){ 
                     smsSuccess();   
                     $('.loader').css('display', 'none');
                }else{ 
                    smsFailed(); 
                    $('.loader').css('display', 'none');
                }
            },
            error:function(error){ 
                console.log(error); 
                smsFailed(); 
                $('.loader').css('display', 'none');
            }
        });
        
    });
     
 });

</script> 



</body>
<!-- end::Body -->
</html>
