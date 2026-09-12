<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

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
    {{--  @if (Auth::user()->default_password == "111111")
         @include('includes.reset')
     @else --}}
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Deactive Policy Activate if Payment Success
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span></a>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Deactive Policy Activate if Payment Success</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
              
                    <!-- CSRF Token -->
                   
                    <div class="kt-portlet__body">
                         
                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                           
                                
                             
                                    <form action="{{ route('admin.deactivepolicyupdate') }}" method="POST" enctype="multipart/form-data" class="kt-form">
                                        <!-- CSRF Token -->
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Policy Number</label>
                            <div class="col-8">
                                <input type="text" class="form-control" id="policyNumber" name="policyNumber" value=""  placeholder="Enter Policy Number">

                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-5" id="policy"></div>
                            <div class="col-6" id="trxn"></div>
                           
                           
                           </div> 
                           <div class="form-group row" id="btn-submit" >
                            <div class="col-3" ></div>
                            <div class="col-8">
                            <button type="submit"  class="btn btn-brand">Policy Activate</button> 
                              <button style="background-color:#777; color:#fff;" class="btn btn-secendory" onclick="history.back()">Go Back</button> 
                            </div>
                            
                           
                           </div> 
                        </div>
                    </div>
                
                <!--end::Form-->
            </div>
            <!--end::Portlet-->
        </div>
        <!-- end:: Content -->
    </div>
{{--   @endif --}}
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

@include('admin.layouts.scripts')

<script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>
<script>



    jQuery(document).ready(function(){
        $('#btn-submit').hide();
     $('#policyNumber').change(function() {
       
        var policyNumber = $('#policyNumber').val();
      
        if (policyNumber.length == 13) {
       
        $.ajax({
            type: 'POST',
            beforeSend: function() {
                $("#loader").show();
               
            },
            data: {
                "_token": "{{ csrf_token() }}",
                "policyNumber": policyNumber,
            },
          
            url: '{{ route("admin.deactivepolicyactivatedata") }}',
            dataType: 'json',
            success: function(data) {
                $('#policy').text('');
                $('#trxn').text('');
                if(data.data.policy.premium_freq == 3){
                    premium_freq = "Yearly";
                }else if(data.data.policy.premium_freq == 2){
                    premium_freq = "3 Installment";
                }else{
                    premium_freq = "Monthly";
                }
                if(data.data.policy.status == 0){
                    policy_status = "Deactivated";
                }else if(data.data.policy.status == 1){
                    policy_status = "Policy Activated";
                 }else if(data.data.policy.status == 2){
                    policy_status = "Cancelled";
                }else if(data.data.policy.status == 3){
                    policy_status = "Expired";
                }else{
                    policy_status = "-";
                }

                                   
                $('#policy').append('<table class="table"><tr><td><b>Policy Number :</b></td><td>'+data.data.policy.policyNumber+'</td></tr>'+
                '<td><b>Customer Name :</b></td><td>'+data.data.customer.firstName+' '+ data.data.customer.lastName+'</td></tr>'+
                '<td><b>Product Name :</b></td><td>'+data.data.product.name+'</td></tr>'+
                '<td><b>Premium :</b></td><td> P '+data.data.policy.premium+'</td></tr>'+
                '<td><b>Frequncy :</b></td><td>'+premium_freq+'</td></tr>'+
                '<td><b>Policy Status :</b></td><td>'+policy_status+'</td></tr>'+
                '<td><b>Policy Created at :</b></td><td>'+data.data.policy.created_at+'</td></tr>'+
                '</table>');
                $('#trxn').append('<table class="table"><tr><td><b>Reference Number :</b></td><td>'+data.data.trxn.referenceNumber+'</td></tr>'+
                '<td><b>Payment Status :</b></td><td>'+data.data.trxn.status+'</td></tr>'+
                '<td><b>Payment Date :</b></td><td>'+data.data.trxn.paymentDate+'</td></tr>'+
                '<td><b>Payment Method :</b></td><td>'+data.data.trxn.paymentMethod+'</td></tr>'+
               
                
                '<td><b>Amount:</b></td><td> P '+data.data.trxn.amount+'</td></tr>'+
                '</table>');
                if(data.data.trxn.status == "success" || data.data.trxn.status == "Success" || data.data.trxn.status == "SUCCESS"){
                    $('#btn-submit').show();
                }else{
                    $('#btn-submit').hide();
                }
               
             
               

            },
            error: function(error) {
                $('#policy').text('');
                $('#policy').append('<h5 style="color:red;">'+error.responseJSON.message+'</h5>');
               
                $('#btn-submit').hide();
            },
            complete: function (data) {
                    $("#loader").hide();
                },
           
        });
    };
    });
});

</script>

</body>
<!-- end::Body -->
</html>
