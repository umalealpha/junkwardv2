<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/tether/dist/css/tether.css') }}" rel="stylesheet" type="text/css" />
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
                    Create Cron Kernel
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{route('admin.cron.index')}}" class="kt-subheader__breadcrumbs-link"> cronkernel </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->

        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="cron"  action="{{ route('admin.cronkernel.store') }}" method="POST" enctype="multipart/form-data" class="">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                     <input type="hidden" name="id" value="{{ $cronkernel->id }}" />
                    <div class="kt-portlet__body">

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Cron schedule command  Name</label>
                            <div class="col-8">
                                <input type="text" class="form-control" name="cron_name" value="{{ $cronkernel->cron_name }}"  placeholder="Enter cron name">

                            </div>
                        </div>
                      
                        <div class="form-group row">

                            <label for="example-text-input" class="col-3 col-form-label">Run Type</label>
                  
                           <div class="Production col-8" >

                                <select class="form-control"  id="runtype"  name="run_type">
                                    <option value="Hourly" @if($cronkernel->run_type == "Hourly") selected @endif >Hourly</option>
                                    <option value="Daily"  @if($cronkernel->run_type == "Daily") selected @endif >Daily</option>
                                    <option value="weekly_sundays"  @if($cronkernel->run_type == "weekly_sundays") selected @endif  >Weekly Sundays</option>
                                    <option value="lastDayOfMonth"  @if($cronkernel->run_type == "lastDayOfMonth") selected @endif >last Day Of Month</option>
                                </select>



                           
                            </div>

                        </div>
                        <div class="form-group row" id="runtime">

                        <label for="example-text-input" class="col-3 col-form-label">Run Time</label>



                                <div class="col-8" >
                                <div class="form-group ">
                                        <input type="time" class="form-control " name="run_time" value="{{ $cronkernel->run_time }}" placeholder="Enter Development Emails">
                                      
                                </div>
                                </div>

                                </div>
                                <div class="form-group row">

                                    <label for="example-text-input" class="col-3 col-form-label">Run On Server</label>

                                    <div class=" col-8" >

                                        <select class="form-control" id="run_on_server" name="run_on_server">
                                            <option value="bw_server"  @if($cronkernel->run_on_server == "bw_server") selected @endif>BW Main server</option> 
                                            <option value="cron_server"  @if($cronkernel->run_on_server == "cron_server") selected @elseif($cronkernel->run_on_server == null) selected @endif>Cron server</option>
                                        
                                        </select>
                                     </div>

                                    </div>
                      <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Cron command Status</label>

                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="status" @if($cronkernel->status == 1) checked @endif class="form-control condition" value="1">Activated<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="status"  @if($cronkernel->status != 1) checked @endif  class="form-control  condition"
                                                               value="0">Deactivated<span></span>
                                                    </label>
                                </div>
                        

                        </div>



                    <div class="kt-portlet__body">
                 

                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9">
                                        <button type="submit" id="btn" value="Submit" class="btn btn-brand">Submit</button>
                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                        <a class="btn btn-secondary" href="{{ route('admin.customer.index') }}" >Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </form>
                <!--end::Form-->
            </div>
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
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datetime-picker/js/bootstrap-datetimepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-timepicker/js/bootstrap-timepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-timepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.js') }}" type="text/javascript"></script>

<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>





<script>
    "use strict";
    // Class definition
  $('#runtype').on('change', function() {
     var runtype = $('#runtype').val();
    if(runtype == "Hourly"){
          $('#runtime').hide();
    }else{
          $('#runtime').show();
    }
   
  
});

    var KTFormControls = function () {

        jQuery.validator.addMethod("passport", function(value, element) {
            return this.optional(element) || /^[a-zA-Z0-9]+$/gi.test(value);
        }, 'Sorry ! This passport number is not valid');

        var demo1 = function () {
            $( "#cron" ).validate({

              rules: {
                    cron_name: {
                        required: true
                    },
                   
                    run_type: {
                        required: true
                       
                    },
                    run_time: {
                           required: function(element){
                            return $('#runtype option:selected').val() != 'Hourly';
                           }
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

    jQuery.validator.addMethod("cutomer_email", function(value, element) {
            return this.optional( element ) || /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test( value );
        }, 'Sorry ! This cutomer email is not valid');

    jQuery(document).ready(function() {
        KTFormControls.init();
    });


</script>
       
<script>
    $(document).ready(function(){
        "use strict";
    var runtype = $('#runtype').val();
   
   if(runtype == "Hourly"){
          $('#runtime').hide();
    }else{
          $('#runtime').show();
    }

    });
    </script>   


</body>
<!-- end::Body -->
</html>
