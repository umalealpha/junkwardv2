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
                        Pay with DPO
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a href="{{Route('admin.policy.index')}}" class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Pay with DPO</span> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->

            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <form action="{{ route('admin.policy.payWithDpoStore') }}" id="payWithDpoForm" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="form-group">
                                        <label for="policyNumber">Policy Number</label>
                                        <input type="text" class="form-control" name="policyNumber" id="policyNumber" placeholder="Please enter policy number">
                                        <span id="policyNumberNotice"></span>
                                    </div>

                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="text" class="form-control" name="amount" id="amount" placeholder="Please enter amount">
                                    </div>

                                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                                        <div class="kt-form__actions">
                                            <div class="row">
                                                <div class="col-3"></div>
                                                <div class="col-9">
                                                    <button type="submit" id="btn" value="Submit" class="btn btn-brand">Submit</button>
                                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                                    <button class="btn btn-warning" type ="reset" >Reset</button>
                                                    <a class="btn btn-secondary" href="{{-- {{ route('admin.dashboard') }} --}}" >Cancel</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </form>

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







</div>

@include('admin.layouts.scripts')
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script>
    $(document).ready(function(){
        jQuery.validator.addMethod("policyNumberVal", function(value, element) {
                return this.optional(element) || /^[a-zA-Z]{3}[0-9]{10}$/.test(value);
        }, 'Please enter valid policy number ex. MIS2022004214');

        jQuery.validator.addMethod("money", function(value, element) {
                return this.optional(element) || /^\d{0,10}(\.\d{0,2})?$/.test(value);
        }, 'Please enter valid amount');

        $( "#payWithDpoForm" ).validate({
            rules: {
                policyNumber:{
                    required:true,
                    policyNumberVal:true,
                    maxlength:13,
                },
                amount:{
                    required:true,
                    money:true,
                }
            },
            messages:{}
        });

        $('#policyNumber').change(function() {
            console.log($(this).val().length);
            if($(this).val().length == 13){
                var policyNumber = $(this).val();
                $.ajax({
                    type: 'POST',
                    headers: {
                        'api-token': "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9",
                    },
                    url: "{{ route('admin.policy.check') }}",
                    beforeSend: function() {
                        $("#policyNumberNotice").attr('class', '');
                        $('#policyNumberNotice').addClass('text-secondary');
                        $('#policyNumberNotice').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...');
                        $('.modelSpinner').show();
                    },
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "policyNumber": policyNumber,
                    },
                    dataType: 'json',
                    success: function(data) {
                        $("#policyNumberNotice").attr('class', '');
                        $('#policyNumberNotice').addClass('text-success');
                        $('#policyNumberNotice').html('Policy belongs to: ' + data.customer.firstName + ' ' + data.customer.lastName);
                    },
                    error: function(error) {
                        $("#policyNumberNotice").attr('class', '');
                        $('#policyNumberNotice').addClass('text-danger');
                        $('#policyNumberNotice').html('Policy number does not exists');
                    },
                    complete: function(error) {
                        $('#loader').hide();
                    }
                });
            }
        });
    });
</script>
</body>
<!-- end::Body -->
</html>
