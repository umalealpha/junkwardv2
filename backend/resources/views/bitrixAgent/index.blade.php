<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" /> 
<link href="https://cdn.datatables.net/buttons/1.6.0/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css" />  
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

<!-- begin::Body -->

<body
    class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
    <!-- begin:: Header Mobile -->
    <div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed ">
        <div class="kt-header-mobile__logo">
            <a>
                <img alt="Logo" src="{{asset('images/logo.png')}}" />
            </a>
        </div>
        <div class="kt-header-mobile__toolbar">
            <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left"
                id="kt_aside_mobile_toggler"><span></span></button>
            <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
            <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i
                    class="flaticon-more"></i></button>
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
   
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                       Bitrix Agent
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href=""
                            class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span
                            class="kt-subheader__breadcrumbs-separator"></span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">User</span>
                    </div>
                </div>
                <div class="kt-subheader__toolbar">
                    <div class="kt-subheader__wrapper">
                    <a href="{{ route('bitrixAgent.create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
          
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body"> 
                         <!--begin: Filter -->
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <label>Filter By Agent Product</label>
                                <select name="bitrix_agent_product" class="form-control" id="bitrix_agent_product">
                                    <option value="-1">All</option> 
                                    <option style="text-transform: capitalize" value="Commercial">Commercial</option>
                                    <option style="text-transform: capitalize" value="Domestic">Domestic</option>
                                   
                                   
                                </select>
                            </div> 
                        </div> 
                        <div class="ml-4 loader" style="display:none;"><img src="{{ asset('img/loading.gif') }}" class="img-responsive" width=40 height=40></div>
                   
                        <!--begin: Datatable -->
                      
                       
                        <table class="table table-striped table-bordered table-hover table-checkable" id="bitrix_agents"> 
                           
                            <thead>
                                <tr>
                                    <th>Bitrix ID</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Commercial Agent</th> 
                                    <th>Domestic Agent</th> 
                                    <th>Active</th>
                                  
                                </tr>
                            </thead>
                            
                        </table>
                       

                        <!--end: Datatable -->
                   
                    </div>
                </div>
            </div>
            <!-- end:: Content -->
        </div> 
    
        <!-- begin:: Footer -->
        <div class="kt-footer kt-grid__item kt-grid kt-grid--desktop kt-grid--ver-desktop">
            <div class="kt-footer__copyright"> 2018&nbsp;&copy;&nbsp;<a href="#" target="_blank" class="kt-link">Alpha
                    Direct</a> </div>
        </div>
        <!-- end:: Footer -->
    </div>
    <!-- end:: Wrapper -->
    </div>
    <!-- end:: Page -->
    </div>
    <!-- end:: Root -->




@include('admin.layouts.scripts')

<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/layouts/repeater.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/additional-methods.js') }}" type="text/javascript"></script> 
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>


<script>
$(document).ready(function(){
  $("#search").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#user_table tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
});
</script> 

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>
    "use strict"; 

    var bitrix_agent_product =-1;
    var KTDatatablesDataSourceAjaxServer = function() {
        var table = '';
        var initTable1 = function() {

            // begin first table
           table = $('#bitrix_agents').DataTable({
                responsive: true,
                searchDelay: 500, 
                language:{ 
                    processing : "<img src='{{asset('img/loading.gif')}}'>"
                },
                processing: true,
                serverSide: true,
                ajax: { 
                    url :'{!! route('bitrixAgent.data') !!}', 
                    data: function (d) { 
                        d.bitrix_agent_product = bitrix_agent_product;

                    }
                },
                columns: [
                    {data: 'id'},
                    {data: 'email'},
                    {data: 'department'}, 
                    {data: 'commercial'}, 
                    {data: 'domestic'}, 
                    {data: 'active'}
                  
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
    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
    });  

    $('#bitrix_agent_product').on('change',function(){
        bitrix_agent_product = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });
</script>  
<script> 
        function Success(){ 
        Toastify({
            text: "Agent Status Changed",
            duration: 6000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            center: true, // `true` or `false`
            backgroundColor: "#1fcf08",
        }).showToast();   
    }  
    function Failed(){ 
        Toastify({
            text: "Something went wrong please try again later",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            center: true, // `true` or `false`
            backgroundColor: "#CC0000",
        }).showToast();   
    }


</script>
<script>  
$(document).ready(function(){ console.log('clickfsdaf');
   // $('#bitrix_agents input[type=checkbox]').click(function () { 
    $( "#bitrix_agents tbody" ).on( "click", ".agent_type", function() {
            
            console.log('click');
            var bitrixId = $(this).attr('data-agentid');  
            var agent_type = $(this).attr('data-agent-type'); 
            var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');  


            if ($(this).is(':checked')) {
                var checkedVal = '1'; 
                var selectedAgentType = $(this).val();
                console.log(checkedVal);
                $.ajax({ 
                    beforeSend: function(){ 
                        $('.loader').css('display', 'block');
                    },
                    type: 'post',
                    url: '{{ route('bitrixAgent.updateBitrixAgent') }}',
                    data: {  
                        _token: CSRF_TOKEN,
                        id: bitrixId,
                        checkedVal : checkedVal , 
                        agent_type :agent_type, 
                        selectedAgentType : selectedAgentType,
                    },
                    dataType: "JSON",
                    success: function (response) {
                        if(response) { 
                            Success(); 
                            $('.loader').css('display', 'none'); 
                            location.reload(); 
                           console.log(response)  
                        }else{ 
                            Failed();   
                            console.log(response)   
                            $('.loader').css('display', 'none');
                        }
                          
                    },
                    error:function(error){ 
                        console.log(error); 
                        Failed(); 
                        $('.loader').css('display', 'none');
                    }
                });
                console.log('click');
            }  else if($(this).is(":not(:checked)")){
                var checkedVal = '0'; 
                var selectedAgentType = $(this).val();
                console.log(checkedVal);
                $.ajax({ 
                    beforeSend: function(){ 
                        $('.loader').css('display', 'block');
                    },
                    type: 'post',
                    url: '{{ route('bitrixAgent.updateBitrixAgent') }}',
                    data: {  
                        _token: CSRF_TOKEN,
                        id: bitrixId,
                        checkedVal : checkedVal, 
                        agent_type :agent_type, 
                        selectedAgentType : selectedAgentType,
                    },
                    dataType: "JSON",
                    success: function (response) {
                        if(response.status ==200) { 
                            Success(); 
                            $('.loader').css('display', 'none'); 
                            location.reload();
                        }else{ 
                            Failed();     
                            $('.loader').css('display', 'none');
                        }
                    },
                    error:function(error){ 
                        console.log(error); 
                        $('.loader').css('display', 'none');
                    }
                });
            }

          
            
        }); 
    }); 
</script>

<script>
$(document).ready(function(){
            $('.agentType').click(function(event){
                event.preventDefault();

                var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
                var bitrixId = $(this).attr('data-bitrixid');
                var agentType = $(this).attr('data-type');

                if ($(this).prop('checked')){

                    var checked = $(this).val();
                    console.log(bitrixId,agentType);

                    $.ajax({
                        type: 'POST',
                        url: "#",
                        data: {
                            _token: CSRF_TOKEN,
                            bitrixId : bitrixId,
                            agentType : agentType,
                        },
                        dataType: 'JSON',
                        success: function(response){

                            $('.success').append('<div id = "newElement"> <span class="text-success">Agent Saved</span> <button  class="btn btn-danger-sm delete"> <i class="la la-close"></i> Close </button></div>');
                        },
                        error: function (e) {
                            console.log(e);
                        }
                    });
                }
            });

});
</script>

</body>
</html>
