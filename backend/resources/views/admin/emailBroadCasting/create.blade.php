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
    
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Compose new Email Template
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.emailBroadCasting.index')}}" class="kt-subheader__breadcrumbs-link"> Email Template </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Compose</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="emailForm" action="{{ route('admin.emailBroadCasting.store') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">

                    <div class="form-group row" >
                        <label for="example-text-input"  class="col-2 col-form-label">Enter Name:</label>
                        <div class="col-10">
                            <input  class="form-control" name="name" title="Name is required">

                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="example-text-input"  class="col-2 col-form-label">Select Field:</label>
                        <div class="col-10">
                            <select  class="form-control kt_selectpicker" name="field" id="user_label"
                                     data-live-search="true" title="Please select template field">

                                @foreach($template_fields as $template_field)
                                    <option value="{{ $template_field->id }}" >{{ $template_field->field }}</option>
                                @endforeach

                            </select>

                        </div>
                    </div>
                        <div class="form-group row">
                            <label for="example-text-input"  class="col-2 col-form-label">Hook</label>
                            <div class="col-10">
                                <select  class="form-control kt_selectpicker" name="hook"
                                         id="user_label" data-live-search="true" title="Please select hook ">

                                    @foreach($hooks as $hook)
                                        <option value="{{ $hook->slug }}" >{{ $hook->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row" >
                            <label for="example-text-input"  class="col-2 col-form-label">Subject</label>
                            <div class="col-10">
                                <input  class="form-control" name="subject" title="Subject is required">

                            </div>
                        </div>

                        <div class="form-group row" >
                            <label for="example-text-input"  class="col-2 col-form-label">Message</label>
                            <div class="col-10">
                                 <textarea class="form-control" id="summary-ckeditor"
                                           name="summary-ckeditor"></textarea>

                            </div>
                        </div>
                    </div>

                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" style="margin-left: 190px;" id="btn" value="Submit" class="btn btn-brand">Submit</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>

                                    <a class="btn btn-secondary" href="{{ route('admin.emailBroadCasting.index') }}" >Cancel</a>
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
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('vendor/unisharp/laravel-ckeditor/ckeditor.js') }}"></script>
<script>
    //CKEDITOR.replace( 'summary-ckeditor' );
    CKEDITOR.replace( 'summary-ckeditor', {
        filebrowserBrowseUrl: '/ckfinder/ckfinder.html',
        filebrowserUploadUrl: '/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Files',
        uploadUrl: "{!! route('admin.emailBroadCasting.ckeupload',['_token' => csrf_token()]) !!}",

    } );

</script>

<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
        // Private functions

        var demo1 = function () {
            $( "#emailForm" ).validate({
                // define validation rules
                rules: {
                    name: {
                        required: true
                    },

                },

                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    KTUtil.scrollTo("#emailForm", -200);
                    $('#btn').show();
                },

                submitHandler: function (form) {
                    form[0].submit(); // submit the form
                    $('#btn').hide();
                    $('#loadBtn').show();
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
    //Insert Label
    $('#user_label').on('change',function(){
        var user_label = $(this).val();
        var optionText = $("#user_label option:selected").text();
        if(user_label != -1){
            var dyanmic_field = '[['+optionText+ '_' + user_label+ ']]';
            var $txt = jQuery("#summary-ckeditor");
            var caretPos = $txt[0].selectionStart;
            myValue = dyanmic_field.trim();
            CKEDITOR.instances['summary-ckeditor'].insertText(myValue);
        }

    });

    CKEDITOR.replace( 'summary-ckeditor', {
        toolbar: 'full',
        height: '800px',
    });

</script>




</body>
<!-- end::Body -->
</html>





