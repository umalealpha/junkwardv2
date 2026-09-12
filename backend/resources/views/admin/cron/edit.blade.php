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
                    Create Cron Mails
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{route('admin.cron.index')}}" class="kt-subheader__breadcrumbs-link"> cron </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Edit</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->

        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="cron"  action="{{ route('admin.cron.store') }}" method="POST" enctype="multipart/form-data" class="">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <input type="hidden" name="id" value="{{ $cronmail->id }}" />
                    <div class="kt-portlet__body">

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Cron Name</label>
                            <div class="col-8">
                                <input type="text" class="form-control" name="cron_name" value="{{ $cronmail->cron_name }}"  placeholder="Enter cron name">

                            </div>
                        </div>
                      
                        <div class="form-group row">

                            <label for="example-text-input" class="col-3 col-form-label">Production Emails</label>
                  
                         

                            <div class="Production col-8" style="margin-left: 10px;">
                       
                                @foreach(json_decode($cronmail->production_emails) as $i => $promail)
                                <div class="form-group row">
                                    <input type="email" class="form-control col-11" name="production_emails[]" value="{{ $promail }}" placeholder="Enter Production Emails">
                                    
                                 @if($i ==0 ) <a href="javascript:void(0);" class="add_button col-1" title="Add field"><i class="fas fa-plus" style="font-size:23px;"></i></a>  
                                 @else   <a href="javascript:void(0);" class="remove_button col-1" title="Add field"><i class="fas fa-minus" style="font-size:23px;  color:red;"></i></a>  
                                 @endif
                                </div>
                                @endforeach
                                   
                                
                           
                            </div>

                        </div>
                        <div class="form-group row">

                        <label for="example-text-input" class="col-3 col-form-label">Development Emails</label>



                                <div class="Production2 col-8" style="margin-left: 10px;">
                                @foreach(json_decode($cronmail->development_emails)  as $j => $devmail)
                                <div class="form-group row">
                                        <input type="email" class="form-control col-11" name="development_emails[]" value="{{ $devmail }}" placeholder="Enter Development Emails">
                                        
                                        @if($j == 0 ) <a href="javascript:void(0);" class="add_button2 col-1" title="Add field"><i class="fas fa-plus" style="font-size:23px;"></i></a>  
                                        @else  <a href="javascript:void(0);" class="remove_button2 col-1" title="Add field"><i class="fas fa-minus" style="font-size:23px;  color:red;"></i></a>   
                                        @endif
                                </div>
                                @endforeach
                                </div>

                                </div>
                        
                                <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Cron Emails Status</label>

                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="status" @if($cronmail->status == 1) checked @endif class="form-control condition" value="1">Activated<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="status"  @if($cronmail->status != 1) checked @endif  class="form-control  condition"
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




<script type="text/javascript">
$(document).ready(function(){
    var maxField = 30; //Input fields increment limitation
    var addButton = $('.add_button');
    var removeButton = $('.remove_button');  //Add button selector
    var wrapper = $('.Production'); //Input field wrapper
    var fieldHTML = '<div class="form-group row"><input type="email" class="form-control col-11" name="production_emails[]" value="" placeholder="Enter Production Emails" /><a href="javascript:void(0);" class="remove_button col-1"><i class="fas fa-minus " style="font-size:23px; color:red;"></a></div>'; //New input field html 
    var x = 1; //Initial field counter is 1
    
    //Once add button is clicked
    $(addButton).click(function(){
        //Check maximum number of input fields
        if(x < maxField){ 
            x++; //Increment field counter
            $(wrapper).append(fieldHTML); //Add field html
        }
    });
    
    //Once remove button is clicked
    $(wrapper).on('click', '.remove_button', function(e){
        e.preventDefault();
        $(this).parent('div').remove(); //Remove field html
        x--; //Decrement field counter
    });
});
$(document).ready(function(){
    var maxField = 30; //Input fields increment limitation
    var addButton2 = $('.add_button2');
    var removeButton2 = $('.remove_button2');  //Add button selector
    var wrapper2 = $('.Production2'); //Input field wrapper
    var fieldHTML2 = '<div class="form-group row"><input type="email" class="form-control col-11" name="development_emails[]" value="" placeholder="Enter Development Emails" /><a href="javascript:void(0);" class="remove_button2 col-1"><i class="fas fa-minus " style="font-size:23px; color:red;"></a></div>'; //New input field html 
    var x = 1; //Initial field counter is 1
    
    //Once add button is clicked
    $(addButton2).click(function(){
        //Check maximum number of input fields
        if(x < maxField){ 
            x++; //Increment field counter
            $(wrapper2).append(fieldHTML2); //Add field html
        }
    });
    
    //Once remove button is clicked
    $(wrapper2).on('click', '.remove_button2', function(e){
        e.preventDefault();
        $(this).parent('div').remove(); //Remove field html
        x--; //Decrement field counter
    });
});
</script>
<script>
    "use strict";
    // Class definition
 

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
                   
                    production_emails: {
                        required: true,
                        cutomer_email:true,
                    },
                    development_emails: {
                        required: true,
                        cutomer_email:true,
                    }
                   
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
       


</body>
<!-- end::Body -->
</html>
