<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datetime-picker/css/bootstrap-datetimepicker.css')}}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-timepicker/css/bootstrap-timepicker.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

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

    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Edit Customer
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{route('admin.customer.index')}}" class="kt-subheader__breadcrumbs-link"> Customer </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Add to Black List</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form  action="{{ route('admin.customer.add_to_block_list_update') }}" method="POST" enctype="multipart/form-data" class="kt-form">

                    <!-- CSRF Token -->
                    
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />

                    <div class="kt-portlet__body">

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">First Name</label>
                            <div class="col-9">
                               <input type="text" class="form-control"  name="first_name" title="First name is required" placeholder="Enter first name" required>
                            </div>
                        </div>
                        <!-- Middle Name Column added -->
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Middle Name</label>
                            <div class="col-9">
                                <input type="text" class="form-control"  name="middle_name" title="middle name is required" placeholder="Enter middle name" >
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Last Name</label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="last_name" title="Last name is required"  placeholder="Enter last name" required>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Email</label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="email" placeholder="Enter email" >
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Omang Id</label>
                            <div class="col-9">
                               <input type="text" class="form-control" name="omang" pattern="[0-9]{9}" maxlength="9" placeholder="Enter Omang">
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Passport Number</label>
                            <div class="col-9">
                               <input type="textarea" class="form-control" name="passport" maxlength="12" placeholder="Enter passport number">
                           </div>
                        </div>
                        <!-- passport country dropdown complete -->
                     <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Mobile No.</label>
                            <div class="col-9">
                                 <input type="text" class="form-control" name="mobile" pattern="[0-9]{1,25}" maxlength="8"  placeholder="Enter mobile no." >
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Reasons of Cancellation </label>
                            <div class="col-9">
                               <input type="text" class="form-control" name="block_reason" maxlength="255" placeholder="Reasons of Cancellation" required>
                            </div>
                        </div>
                     


                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>


                                <div class="col-9">
                                   
                                        <button type="submit" id="btn"  class="btn btn-brand">Add to Black List</button>
                                   
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.customer.index') }}" >Cancel</a>
                                </div>


                            </div>
                        </div>
                    </div>
                </div>
             </form>
                <!--end::Form-->
            </form>
            <!--end::Portlet-->
        </div>
        <!-- end:: Content -->
    </div>

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
<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>



<script>
    var mytextbox = document.getElementById('displayCountry');
    var mydropdown = document.getElementById('selectCountry');
    mydropdown.onchange = function(){
        mytextbox.value = mytextbox.value  + this.value; //to appened
        mytextbox.innerHTML = this.value;
    }
</script>


<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#customerEdit" ).validate({
// define validation rules
                rules: {
                    first_name: {
                        required: true
                    },
                    last_name: {
                        required: true
                    },

                    address: {
                        required: true
                    },

                    block_reason: {
                        required: function(element){
                            return  $("input[name='is_blocked']:checked").val() == 1;
                        },
                    },

                    mobile: {
                        required: true
                    },

                },

                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    KTUtil.scrollTo("customerEdit", -200);
                    $('#btn').show();
                },
                submitHandler: function (form) {
                    $('#btn').hide();
                    $('#loadBtn').show()
                    form[0].submit(); // submit the form
                }
            });
        }

        return {
// public functions
            init: function() {
                demo1();
            }
        };
    }();

    jQuery(document).ready(function() {
        KTFormControls.init();
    });
</script>


<script>
     function ShowHideDiv() {
        var chkYes = document.getElementById("chkYes");
        var reason_for_block = document.getElementById("reason_for_block");
        reason_for_block.style.display = chkYes.checked ? "block" : "none";
        // if( $(this).is(':checked') && chkYes=='2'){
        //   $('#txtBox input').removeClass('required');
        // }
    }
</script>

<script>

    var KTAvatarDemo = function() {

        return {
            // Init demos
            init: function() {
                var avatar1 = new KTAvatar('driversLicense');
                var avatar2 = new KTAvatar('omangFront');
                var avatar3 = new KTAvatar('proofResidence');
                var avatar4 = new KTAvatar('proofIncome');
                var avatar5 = new KTAvatar('passportImg');
                var avatar6 = new KTAvatar('omangBack');
            }
        };
    }();

    jQuery(document).ready(function(){
        KTAvatarDemo.init();
    });

    $('#state').change(function() {
        $('#city').empty();
        $('#loader').css("display", "block");
        var state_id = $(this).val();
        $.ajax({
            type: 'POST',
            beforeSend: function() {
                $('#city').hide();
                //$('#valueLoader').show();
                $('.modelSpinner').show();
            },
            data: {
                "_token": "{{ csrf_token() }}",
                "state_id": state_id,
            },
            headers: {
                'api-token': "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9",
            },
            url: "https://graphite.alphadirect.co.bw/api/frontendpay/getCities",
            dataType: 'json',
            success: function(data) {

                var append = '';
                append += '<option value="">Select City</option>';
                if (data.cities.length > 0) {
                    jQuery.each(data.cities, function(i, val) {
                        append += '<option value="' + val.name + '">' + val.name + '</option>';
                    });
                }
                console.log(append);
                $('#city').append(append);
                $('#city').show();
                $('#loader').css("display", "none");

            },
            error: function(error) {
                if (error.status == 401) {
                    toastr.error("Unauthorized Access");
                }

            }
        });
    });
</script>


</body>
<!-- end::Body -->
</html>
