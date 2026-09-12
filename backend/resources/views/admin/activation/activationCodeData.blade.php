<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" /> 

<!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >
<!-- begin:: Header Mobile -->
{{-- <div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed " >
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
</div> --}}
<!-- end:: Header Mobile -->
<!-- begin:: Root -->
<div class="kt-grid kt-grid--hor kt-grid--root">
    <!-- begin:: Page -->
   {{--  <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">

        @include('admin.layouts.sidebar')
        @include('admin.layouts.topNav')

    </div>  --}}
   {{--  @if (Auth::user()->default_password == "111111")     
    @include('includes.reset') 
    @else --}}
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor mt-5">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Activation Data
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
               {{--  <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Activation</span>
                </div> --}}
            </div>
           {{--  <div class="kt-subheader__toolbar">
                <div class="kt-subheader__wrapper">
                    <a href="{{ URL::to('admin/activation/create') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Add New"> <span class="kt-opacity-11" id="">Add New</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                </div>
            </div> --}}
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <div class="kt-portlet kt-portlet--mobile">
                <div class="kt-portlet__body">
                    <!--begin: Datatable --> 
                    <div class="form-group row"> 
                            <div class="col-4"> 
                                <label for="activation_code" class="form-label">Activation Code</label>
                                <input type="text" id="activation_code" class="form-control" title="Activatio Code is required" name="activation_code" value="{!! old('activation_code') !!}" placeholder="Enter Activation Code">
                                <span class="form-text text-muted"></span>
                            </div> 
                            <div class="col-4"> 
                                    <label for="serial_code" class="form-label">Serial Code</label>
                                    <input type="text" id="serial_code" class="form-control" title="Serial Code is required" name="serial_code" value="{!! old('serial_code') !!}" placeholder="Enter Serial Code">
                                    <span class="form-text text-muted"></span>
                            </div>
                    </div>  
                    <div class="form-group row">  
                        <button class="btn btn-sm btn-elevate btn-brand btn-elevate ml-3" id="load_activationCodeData" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Check Activation Code"> <span class="kt-opacity-11" id="">Load Information</span> </button> 
                    </div> 
                    <div id="alert-error" >  
                        <div class="alert alert-outline-danger alert-dismissible fade show" id="activation_invalid" role="alert" style="display: none;"> 
                            <div class="alert-icon"><i class="flaticon-warning"></i></div>
                            <div class="alert-text"><strong>Error!!</strong> Invalid activation code or serial code, please try again. </div> 
                            <div class="alert-close">
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                      <span aria-hidden="true"><i class="la la-close"></i></span>
                                    </button>
                              </div>
                        </div> 
                        
                    </div>
                    <table class="table table-striped m-table" id="activation_table">
                            <tbody>
                            </tbody>
                        </table>
                    <!--end: Datatable -->
                </div> 
             
            </div>
        </div>
        <!-- end:: Content -->
    </div> 
    {{-- @endif --}}
    <!-- begin:: Footer -->
   {{--  @include('includes.footer') --}}
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

@include('admin.layouts.scripts')


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>

<script>   

$(document).ready(function () {  
 
    $('#load_activationCodeData').click(function (e) { 
       
        e.preventDefault(); 
        console.log('clicked'); 
        var activation_code = $('#activation_code').val(); 
        var serial_code = $('#serial_code').val();  
        console.log(activation_code); 
        console.log(serial_code);

         
            $.ajax({
            type: 'GET',
            url: '{{ route('activation.checkActivationCodeData') }}',
            data: {  
                "_token": "{{ csrf_token() }}", 
                activation_code : activation_code, 
                serial_code: serial_code
            },
            dataType: 'JSON',
            success: function (data) { 
                var content = '';
                console.log(data);  
                if(data.length > 0){
                    $.each(data, function (indexInArray, valueOfElement) { 
                     console.log(valueOfElement); 
                     if(valueOfElement.status == 1){ 
                       
                     
                        content += '<div class="alert alert-outline-warning alert-dismissible fade show" role="alert">'; 
                        content += '<div class="alert-icon"><i class="flaticon-warning"></i></div>';
                        content += '<div class="alert-text"><strong>Error!!</strong> Activation has already been used. </div> ';
                        content += ' <div class="alert-close">';
                        content +=  ' <button type="button" class="close" data-dismiss="alert" aria-label="Close"> <span aria-hidden="true"><i class="la la-close"></i></span></button></div></div>';
                                                    
                        //$('#alert-error').html(content);
                     } else{
                        content += '<tr><th>Activation Code</th><td>'+valueOfElement.activation_code+'</td></tr>';
                        content +='<tr><th>Serial Code</th><td>'+valueOfElement.serial_code+'</td></tr>';
                        content +='<tr><th>Status</th><td style="color:#008000;">Still not used</td></tr>';
                        content +='<tr><th>Product</th><td>'+valueOfElement.product.name+'</td></tr>';
                        content +='<tr><th>Product Plan</th><td>'+valueOfElement.productplans.name+'</td></tr>';
                        content +='<tr><th>Product Type</th><td>'+valueOfElement.product_type.name+'</td></tr>';
                        content +='<tr><th>Rack Number</th><td>'+valueOfElement.rack_no+'</td></tr>';
                        content +='<tr><th>Trial periods(In Days)</th><td>'+valueOfElement.trial_periods+'</td></tr>';
                        content +='<tr><th>Trial periods Coverage</th><td>'+valueOfElement.trial_coverage+'</td></tr>';   
                        content +='<tr><th>Branch</th><td>'+valueOfElement.branch.name+'</td></tr>'; 
                        content +='<tr><th>Vendor</th><td>'+valueOfElement.vendor.name+'</td></tr>'; 
                        content +='<tr><th>City</th><td>'+valueOfElement.city+'</td></tr>';
                        content +='<tr><th>State</th><td>'+valueOfElement.state+'</td></tr>';
                        content +='<tr><th>Country</th><td>'+valueOfElement.country+'</td></tr>'; 
                       
                     }
                   
                }); 
                   
                $('#activation_table tbody').html(content); 
                }else{ 
                        content += '<div class="alert alert-outline-danger alert-dismissible fade show" role="alert">'; 
                        content += '<div class="alert-icon"><i class="flaticon-warning"></i></div>';
                        content += '<div class="alert-text"><strong>Error!!</strong> Invalid activation code,please try again</div> ';
                        content += ' <div class="alert-close">';
                        content +=  ' <button type="button" class="close" data-dismiss="alert" aria-label="Close"> <span aria-hidden="true"><i class="la la-close"></i></span></button></div></div>'; 
                        $('#activation_table tbody').html(content);
                }
            },
            error: function (error) {
                console.log(error);
                var content = ''; 
                        content += '<div class="alert alert-outline-danger alert-dismissible fade show" role="alert">'; 
                        content += '<div class="alert-icon"><i class="flaticon-warning"></i></div>';
                        content += '<div class="alert-text"><strong>Error!!</strong> Invalid activation code, please try again</div> ';
                        content += ' <div class="alert-close">';
                        content +=  ' <button type="button" class="close" data-dismiss="alert" aria-label="Close"> <span aria-hidden="true"><i class="la la-close"></i></span></button></div></div>'; 
                        $('#activation_table tbody').html(content);
            }
            }); 
    });
    
});

</script>
</body>
<!-- end::Body -->
</html>