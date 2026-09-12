<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

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
    
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Create Answer
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                @can('product-region-create')
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.region.index')}}" class="kt-subheader__breadcrumbs-link"> Answer </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                </div> 
                
                    @endcan
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="answerCreate" action="{{ route('admin.review.answer.store') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body"> 
                        <div class="form-group row {{ $errors->has('question_id') ? 'has-error' : ''}}">
                            <label for="example-text-input" class="col-3 col-form-label">Select Question</label>
                                <div class="col-6">
                                    <select class="form-control kt_selectpicker" id="question" title="Please Choose Question"
                                            data-live-search="true"
                                            name="question" required>
                                        @foreach($questions as $question)
                                            <option value="{{$question->id}}">{{$question->question}}</option>
                                        @endforeach
                                    </select> 
                                @if ($errors->has('question'))
                                    <span class="col error">
                                        <p class="text text-danger">{{ $errors->first('question') }}</p>
                                    </span>
                                @endif    
                                </div>
                        </div>  
                       
                        <div class="kt-repeater">  
                                <div data-repeater-list="questionAnswers"> 
                                    <div data-repeater-item class="kt-repeater__item"> 
                                       
                                        <div class="form-group row {{ $errors->has('answer') ? 'has-error' : ''}}"> 
                                            <label for="example-text-input" class="col-3 col-form-label">Answer :</label>
                                            <div class="col-6">
                                                <input  class="form-control" name="answer" title="Answer is required" placeholder="Enter Answer">
                                                <span class="form-text text-muted"></span>  
                                                @if ($errors->has('questionAnswers.*.answer'))
                                                    <span class="col error">
                                                        <p class="text text-danger">{{ $errors->first('questionAnswers.*.answer') }}</p>
                                                    </span>
                                                 @endif    
                                            </div>  
                                        </div> 
                                        <div class="kt-repeater__data form-group row"> 
                                            <div class="col-3"></div>
                                            <div class="col-9"><span data-repeater-delete="" class="btn btn-warning btn-sm"> <i class="la la-close"></i> Remove </span></div>
                                        </div> 
                                   
                                    </div>
                                </div> 
                                <div class="kt-repeater__data form-group row"> 
                                    <div class="col-3"></div>
                                    <div class="col-9"><button data-repeater-create type="button" id="addAnswer" class="btn btn-brand">Add Answer </button></div>
                                </div>
                        </div>
                        
                        
                      
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" value="Submit" id="submit" class="btn btn-brand">Submit</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.review.index') }}" >Cancel</a>
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

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>

<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#answerCreate" ).validate({
// define validation rules
                rules: {
                    question_id: {
                        required: true
                    },
                    answer:{
                        required:true
                    },
                    answer_type:{
                        required:true
                    },

                },

//display error alert on form submit
                invalidHandler: function(event, validator) {

                   
                    KTUtil.scrollTo("answerCreate", -200);
                },

                submitHandler: function (form) {
                    $('#submit').hide();
                    //$('#loadBtn').show();
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
    function statusMsg(){
        var isChecked=document.getElementById("switchValue").checked;
        if (isChecked){
            document.getElementById("switchMsg").innerHTML="Active";
            document.getElementById("switchMsg").style.color="cornflowerblue";

        }
        else {
            document.getElementById("switchMsg").innerHTML="Inactive";
            document.getElementById("switchMsg").style.color="#ff4d4d";
        }

    }
</script>

<script>

    $('#productFactorType').on('change',function(){
        if($(this).val() !=1){
            $('#multipleValues').show();
        }else {
            $('#multipleValues').hide();
        }
    });
</script>

</body>
<!-- end::Body -->
</html>