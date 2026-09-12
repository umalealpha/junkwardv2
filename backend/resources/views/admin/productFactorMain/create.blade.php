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
     <!-- check if is first time login -->
    
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Create Product Factor Main
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.factorMain.index')}}" class="kt-subheader__breadcrumbs-link"> Product Factor Main </a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                <form id="productFactorMainCreate" action="{{ route('admin.factorMain.store') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">


                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Product</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker"
                                        title="Please choose product" data-live-search="true"
                                        id="factor" name="product_name">
                                    @if($product->count() > 0)
                                        @foreach($product as $products)
                                            <option value="{{$products->id}}">{{$products->name}}</option>
                                        @endforeach
                                    @else
                                        <option value="">No records found</option>
                                    @endif
                                </select>
                            </div>
                        </div>




                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label"> Product Factor Main </label>
                            <div class="col-9">
                                <input type="text" class="form-control" placeholder="Enter Product Factor Main"
                                       name="factor_name">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>


                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Type</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker" id="productFactorType"
                                        title="Please choose factor main" data-live-search="true"
                                          name="type">
                                    @if($lookup->count() > 0)
                                        @foreach($lookup as $lookups)
                                            <option value="{{$lookups->value}}">{{$lookups->value}}</option>
                                        @endforeach
                                    @else
                                        <option value="">No records found</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="kt-repeater" id="multipleValues" style="display: none">
                            <div class=" row">
                                <label class="col-lg-3 col-form-label"></label>
                                <div class="col-lg-3">
                                    <label class="col-lg-3 col-form-label" style="margin-bottom: 0px">Value</label>
                                </div>
                                <div class="col-lg-3">
                                    <label class="col-lg-3 col-form-label" style="margin-bottom: 0px">Factor</label>
                                </div>
                            </div>
                            <div class="kt-repeater__data-set">
                                <div data-repeater-list="factorProductValues">
                                    <div data-repeater-item class="kt-repeater__item">
                                        <div class="kt-repeater__close kt-repeater__close--align-right form-group" style="right: 10%;">
                                            <button data-repeater-delete="" class="btn btn-danger"> <i class="la la-close"></i> Close </button>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-lg-3 col-form-label"></label>
                                            <div class="col-lg-3">
                                                <input type="text" name="factor_type_values" class="form-control" placeholder="Enter Value">
                                            </div>
                                            <div class="col-lg-3">
                                                <input type="number" name="factor_type_factor" class="form-control"
                                                       placeholder="Enter Fatcor">
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <div class="kt-repeater__add-data">
                                <span data-repeater-create="" class="btn btn-info btn-sm" style="margin-left: 26%;"> <i class="la la-plus"></i> Add </span>
                            </div>
                        </div>

                        <br>
                        <br>
                        {{--End multiple values--}}

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Status</label>

                            <div class="col-9">
                                <span class="kt-switch" >
                                    <label>
                                        <input id="switchValue" type="checkbox" checked="checked" name="status" value="1" onchange="statusMsg()">
                                        <span style="margin-top: 10px;margin-left: 10px;"></span>
                                        <p id="switchMsg" style="display:inline;float:left;margin-top: 10px;margin-left: 5px;color:cornflowerblue">Active</p>
                                    </label>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" value="Submit" id="btn" class="btn btn-brand">Submit</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.factorMain.index') }}" >Cancel</a>
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
<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>
<script>

    $('#productFactorType').on('change',function(){
        if($(this).val() !=1){
           $('#multipleValues').show();
        }else {
            $('#multipleValues').hide();
        }
    });
</script>

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>


<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#productFactorMainCreate" ).validate({
// define validation rules
                rules: {
                    factor_name: {
                        required: true
                    },
                },

//display error alert on form submit
                invalidHandler: function(event, validator) {
                    $('#btn').show();
                    KTUtil.scrollTo("productFactorMainCreate", -200);
                },

                submitHandler: function (form) {
                    $('#btn').hide();
                    $('#loadBtn').show();
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


</body>
<!-- end::Body -->
</html>