<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
        <!-- begin::Body -->
<body class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading" >

<!-- Download File -->
@if(Session::has('download_file'))
    <meta http-equiv="refresh" content="1;url={{ Session::get('download_file') }}">
@endif

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
   {{--  @if (Auth::user()->default_password == "111111")     
    @include('includes.reset') 
    @else --}}
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    Create Activation Code
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{ URL::to('admin/activation') }}" class="kt-subheader__breadcrumbs-link">Activation Code </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                </div>
                @if(Session::has('download_file'))
                    <div style="position: absolute; right: 20px; display: flex;" class="pull-right">
                        <h6 style="margin: 10px 10px 0 0;">Download Last Generated File</h6><a href="{{ Session::get('download_file') }}" class="btn btn-brand">Download</a>
                    </div>
                @endif
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                <form id="activationCreate" action="{{ route('admin.activation.store') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Product Type</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker" name="product_type" title="Please choose product type" data-live-search="true" id="customer">
                                    @foreach($productTypes as $productType)
                                        <option value="{{$productType->id}}">{{$productType->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!--Product-->
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Product</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker product" name="product" title="Please choose Product" data-live-search="true">
                                    @foreach($products as $product)
                                        <option value="{{$product->id}}">{{$product->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Product Plan</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker" id="productplan" name="plan" title="Please choose product Plan" data-live-search="true">
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Vendor</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker" name="vendor" title="Please choose vendor" data-live-search="true" id="customer">
                                    @foreach($vendors as $vendor)
                                        <option value="{{$vendor->id}}">{{$vendor->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Branch</label>
                            <div class="col-9">
                                <select class="form-control kt_selectpicker" name="branch" title="Please choose branch" data-live-search="true" id="customer">
                                    @foreach($branches as $branch)
                                        <option value="{{$branch->id}}">{{$branch->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Rack Number</label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="rack_no"  placeholder="Enter Rack No">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Number of Codes to Generate</label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="noofcodes"  placeholder="Enter No of codes">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Trial periods(In Days)</label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="trial_periods"  placeholder="Enter days">
                                <span class="form-text text-muted"></span>

                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Trial periods Coverage</label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="trial_coverage"  placeholder="Enter Coverage">
                                <span class="form-text text-muted"></span>

                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">City</label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="city"  placeholder="Enter City">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">State</label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="state"  placeholder="Enter State">
                                <span class="form-text text-muted"></span>
                            </div>
                        </div>

                    <div class="form-group row">
                        <label for="example-text-input" class="col-3 col-form-label">Country</label>
                        <div class="col-9">
                            <input type="text" class="form-control" name="country"  placeholder="Enter Country">
                            <span class="form-text text-muted"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="example-text-input" class="col-3 col-form-label">Email Id</label>
                        <div class="col-9">
                            <input type="text" class="form-control" name="email"  placeholder="Enter Email Id">
                            <span class="form-text text-muted"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="example-text-input" class="col-3 col-form-label">Cellphone</label>
                        <div class="col-9">
                            <input type="text" class="form-control" name="cellphone"  placeholder="Enter Cellphone Number">
                            <span class="form-text text-muted"></span>
                        </div>
                    </div>
                    </div>

                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" id="btn" value="Submit" class="btn btn-brand">Submit</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.activation.index') }}" >Cancel</a>
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
   {{--  @endif --}}
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

<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#activationCreate" ).validate({
                rules: {
                    product_type: {
                        required: true
                    },
                    vendor: {
                        required: true
                    },
                    branch: {
                        required: true
                    },
                    rack_no: {
                        required: true
                    },
                    noofcodes: {
                        required: true
                    },
                    city: {
                        required: true
                    },
                    state: {
                        required: true,
                    },
                    country: {
                        required: true,
                    },
                    product:{
                        required: true,
                    },
                    email: {
                        required: true,
                        email: true,
                        maxlength: 50
                    },
                     cellphone:  {
                        required: true,
                        minlength: 8,
                        maxlength: 8,
                        number: true
                    },
                    plan: {
                        required: true
                    },
                    trial_coverage: {
                        required: true,
                        number:true,
                    },
                    trial_periods:{
                        required: true,
                        number:true,
                    },
                },
                messages: {
                    product_type: "Please select Product Type",
                    plan: "Please select Product Plan",
                    vendor: "Please select Vendor",
                    branch: "Please select Branch",
                    rack_no: "Please enter Rack No.",
                    product:"Please select Product",
                    noofcodes: "Please enter No. of codes to be generated",
                    city: "Please enter City",
                    state: "Please enter State",
                    country: "Please enter Country",
                    email: {
                        required: "Email is required",
                        email: "Email must be a valid email address",
                        maxlength: "Email cannot be more than 50 characters",
                    },
                    cellphone:{
                        required: "Phone number is required",
                        minlength: "Phone number must be of 8 digits",
                        maxlength: "Phone number must be of 8 digits",
                    },
                    trial_coverage:{
                        required: "Trial Coverage is required",
                        number: "Trial Coverage must be Number"
                    },
                    trial_periods:{
                        required: "Trial Periods is required",
                        number: "Trial Periods must be Number"
                    },
                },

//display error alert on form submit
                invalidHandler: function(event, validator) {
                    $('html, body').animate({
                        scrollTop: $(validator.errorList[0].element).offset().top - 200
                    }, 1000);
                    $('#btn').show();
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

        $('.product').change(function (e) {
            e.preventDefault();

            var product_id = $('.product option:selected').val();
            $.ajax({
                type: 'post',
                url: '{{route('admin.product.productPlans')}}',
                data: {
                    "_token": "{{ csrf_token() }}",
                    product_id : product_id,
                },
                dataType: "JSON",
                success: function (data){

                    if(data != ''){
                        $.each(data,function(key,value){
                            var option = '<option value="'+ value.id + '">' + value.name + '</option>';
                            $('#productplan').append(option);

                            $('#productplan').rules('add', {required: true, messages: {required: "Please Select Plan"}});
                        });

                        $('#productplan').selectpicker('refresh');

                    }else{
                        $('#productplan').rules('remove', 'required');

                    }
                },
                error:function(response){
                    console.log(response.responseJSON);

                },

            });

        });
</script>

</body>
<!-- end::Body -->
</html>
