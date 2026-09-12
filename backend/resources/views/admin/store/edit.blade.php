<!DOCTYPE html>
<html lang="en">

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet"
    type="text/css" />

<!-- begin::Body -->

<body
    class="kt-header--fixed kt-header-mobile--fixed kt-subheader--enabled kt-subheader--transparent kt-aside--enabled kt-aside--fixed kt-aside--minimize kt-page--loading">
    <!-- begin:: Header Mobile -->
    <div id="kt_header_mobile" class="kt-header-mobile kt-header-mobile--fixed ">
        <div class="kt-header-mobile__logo">
            <a>
                <img alt="Logo" src="{{asset('images/logo.png')}}" />
            </a>
        </div>
        <div class="kt-header-mobile__toolbar">
            <button class="kt-header-mobile__toolbar-toggler kt-header-mobile__toolbar-toggler--left"
                id="kt_aside_mobile_toggler"><span></span></button>
            <button class="kt-header-mobile__toolbar-toggler" id="kt_header_mobile_toggler"><span></span></button>
            <button class="kt-header-mobile__toolbar-topbar-toggler" id="kt_header_mobile_topbar_toggler"><i
                    class="flaticon-more"></i></button>
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
                        Edit Store
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}"
                            class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span
                            class="kt-subheader__breadcrumbs-separator"></span> <a
                            href="{{route('admin.branch.index')}}" class="kt-subheader__breadcrumbs-link"> Store </a>
                        <span class="kt-subheader__breadcrumbs-separator"></span>
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
                    {{--                <form id="storeForm" action="{{ route('admin.store.updateStore',$store->id) }}"
                    enctype="multipart/form-data" class="kt-form">--}}

                    <!-- CSRF Token -->
                    {{--                    <input type="hidden" name="_method" value="PUT">--}}
                    {{--                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />--}}

                    <form id="productEdit" action="{{ route('admin.store.updateStore',$store->id) }}" method="POST"
                        enctype="multipart/form-data" class="kt-form">
                        <input type="hidden" name="_method" value="PUT">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <input type="hidden" name="store_id" value="{{ $store->id }}" />
                        <input type="hidden" name="state_id" id="state_id" value="{{ $store->state_id }}" />
                        <input type="hidden" id="store_status" name="store_status" value="{{ $store->status }}" />

                        <div class="kt-portlet__body">
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Appearance Order:</label>
                                <div class="col-6">
                                    <input type="text" class="form-control" name="order"
                                           placeholder="Please provide store Name" value="{{ $store->appearance_order }}" title="Please provide appearance order" />

                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Store Name :</label>
                                <div class="col-6">
                                    <input type="text" class="form-control" name="name" value="{{ $store->name }}"
                                        placeholder="Please provide store Name" title="Partner is required" />
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Partner :</label>
                                <div class="col-6">
                                    <select class="form-control selectpicker" name="partner"
                                        placeholder="Select Partner Name" title="Please select partner">
                                        @foreach($partners as $partner)
                                        <option value="{{ $partner->id }}" @if($store->partner_id == $partner->id)
                                            {{ 'selected' }} @endif>{{ $partner->name }}</option>
                                        @endforeach
                                    </select>
                                    <a href="{{ route('admin.store.addPartner') }}" target="_blank"
                                        style="display: block;padding-top:15px"> Didn't
                                        find partner
                                        in the list? Add one.</a>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">State :</label>
                                <div class="col-6">
                                    <select class="form-control selectpicker" name="state" id="state"
                                        placeholder="Select Partner Name" title="Please select state">
                                        @foreach($states as $state)
                                        <option value="{{ $state->id }}" @if($state->id ==
                                            $store->state_id){{ 'selected' }} @endif>{{ $state->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">City :</label>
                                <div class="col-6">
                                    <select class="form-control" name="city" id="city" placeholder="Select select city"
                                        title="Please select city">
                                        <option value="0">Select City</option>
                                        @foreach($cities as $city)
                                        <option value="{{ $city->name }}"
                                            {{ $city->name == $store->city ? 'selected' : '' }}>{{ $city->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Address:</label>
                                <div class="col-9">
                                    <textarea class="form-control" name="address" placeholder="Please provide address"
                                        title="Please provide address" required
                                        maxlength="500">{{ $store->address }}</textarea>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Status</label>

                                <div class="col-9">
                                    <span class="kt-switch">
                                        <label>
                                            <input id="wordingValue" type="checkbox" @if($store->status)
                                            checked="checked" @endif name="status" value="1" onchange="wordingsMsg()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                            @if($store->status)
                                            <h4 id="wording"
                                                style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:cornflowerblue">
                                                Active</h4>
                                            @else
                                            <h4 id="wording"
                                                style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d">
                                                In-active</h4>
                                            @endif
                                        </label>
                                    </span>
                                </div>
                            </div>

                            <div class="form-group ">
                                <div class="row">

                                    <label for="example-text-input" class="col-3 col-form-label">Product</label>

                                    <label for="example-text-input" class="col-3 col-form-label">Plan</label>

                                    <label for="example-text-input" class="col-2 col-form-label">Minimum warehouse
                                        inventory</label>

                                    <label for="example-text-input" class="col-2 col-form-label">Maximum warehouse
                                        inventory</label>
                                    <label for="example-text-input" class="col-2 col-form-label">Active Count</label>
                                </div>

                                @foreach ($products as $index => $product)
                                @php
                                $i = 0;
                                @endphp
                                <div class="row mb-4">
                                    <input type="hidden" name="data[{{ $index }}][inventory_id]"
                                        value="{{ $product->id }}">
                                    <div class="col-3">
                                        <input type="hidden" name="data[{{ $index }}][product_id]"
                                            id="product_id_{{ $index }}" value="{{ $product->product_id }}">
                                        <input type="text" name=" data[{{ $index }}][product_name]"
                                            id="product_name_{{ $index }}" value="{{ $product->product_name }}"
                                            class="form-control" readonly>
                                    </div>

                                    <div class="col-3">
                                        <input type="hidden" name="data[{{ $index }}][plan_id]"
                                            id="plan_id_{{ $index }}" value="{{ $product->plan_id }}">
                                        <input type="text" name="data[{{ $index }}][plan_name]"
                                            id="plan_name_{{ $index }}" value="{{ $product->plan_name }}"
                                            class="form-control" readonly>
                                    </div>

                                    <div class="col-2">
                                        <input type="number" name="data[{{ $index }}][min_inventory]"
                                            id="min_inventory_{{ $index }}" class="form-control" min="0"
                                            value="{{ $product->min_inventory }}" required>
                                    </div>

                                    <div class="col-2">
                                        <input type="number" name="data[{{ $index }}][max_inventory]"
                                            id="max_inventory_{{ $index }}" class="form-control" min="0"
                                            value="{{ $product->max_inventory }}" required>
                                    </div>

                                    <div class="col-2">
                                        <input type="number" name="data[{{ $index }}][counter]"
                                            id="counter_{{ $index }}" class="form-control" min="0"
                                            value="{{ $product->counter }}" required>
                                    </div>
                                </div>
                                @endforeach
                            </div>


                            <div class="kt-portlet__foot kt-portlet__foot--solid">
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-3"></div>
                                        <div class="col-9">
                                            <button type="submit" value="Submit" id="btn"
                                                class="btn btn-brand">Submit</button>
                                            <button class="btn btn-brand" type="button" id="loadBtn"
                                                style="display:none"> <span class="spinner-border spinner-border-sm"
                                                    role="status" aria-hidden="true"></span> Loading... </button>
                                            <a class="btn btn-secondary" href="{{ route('admin.store') }}">Cancel</a>
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

    <script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}"
        type="text/javascript"></script>
    <script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}"
        type="text/javascript"></script>
    <script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}"
        type="text/javascript"></script>
    <script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}"
        type="text/javascript"></script>

    <script>
        function wordingsMsg(){
        var isChecked=document.getElementById("wordingValue").checked;
        if (isChecked){
            document.getElementById("wording").innerHTML="Active";
            document.getElementById("wording").style.color="cornflowerblue";

            $('#store_status').prop('value',1);
        }
        else {
            document.getElementById("wording").innerHTML="In-active";
            document.getElementById("wording").style.color="#ff4d4d";
            $('#store_status').prop('value',0);
        }

    }

    "use strict";
    // Class definition

    var KTFormControls = function () {
    // Private functions
    
    jQuery.validator.addMethod("notEqualTo", function(value, element, param) {
    return this.optional(element) || value != param;
    }, "Please select");
    
    var demo1 = function () {
    $( "#storeForm" ).validate({
    // define validation rules
        rules: {
            name: {
                required: true
            },
            partner: {
                required: true,
                notEqualTo:""
            },
            state: {
                required: true,
                notEqualTo:"",
                min:1
            },
            city: {
                required: true,
                notEqualTo:0,
                min:1
            },
                max_inventory:{
                required:true,
                number: true,
                min:151,
                max: 300
            },
                min_inventory:{
                required:true,
                number: true,
                min:0,
                max: 150
            },
            },
        messages: {
            name: {
                required: "Please provide store name"
            },
            partner: {
                required: "Please select partner",
                notEqualTo: "Please select partner",
            },
            state: {
                required: "Please select state",
                notEqualTo: "Please select state",
                min: "Please select state",
            },
            city: {
                required: "Please select city",
                notEqualTo: "Please select city",
                min:"Please select city",
            },
            max_inventory:{
                require:"Please provide maximum store inventory",
                number: "Invalid data. Only numbers are allowed",
                min:"Minimum value must be greater than or equals to 151",
                max: "Maximum value must be less than or equals to 300"
            },
            min_inventory:
            {
                require:"Please provide minimum store inventory",
                number: "Invalid data. Only numbers are allowed",
                min: "Minimum value must be greater than or equals to 0",
                max: "Maximum value must be less than or equals to 150"
            },
        },
    
    
    
    //display error alert on form submit
        invalidHandler: function(event, validator) {
            KTUtil.scrollTo("storeForm", -200);
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

        $('#state').change( function()
        {
            $('#city').empty();
            var state_id = $(this).val();
            $.ajax({
                type: 'POST',
                beforeSend: function() {
                    $('#city').hide();
                    $('.modelSpinner').show();
                },
                data: {
                    "_token": "{{ csrf_token() }}",
                    "state_id": state_id,
                },
                 url: "{{env('SELF_URL')}}/api/frontendpay/getCities",
                //url: "http://127.0.0.1:8000/api/frontendpay/getCities",
                dataType: 'json',
                success: function (data) {
                    $('.modelSpinner').hide();
                    $('#city').show();
                    var append = '';

                    $('#state_id').val(state_id);
                    if(data.cities.length <= 0 ){
                        // var stateName = $( "#state option:selected" ).text();
                        append += '<option value="0">City not available</option>';
                    }else{
                        append += '<option value="0">Select City</option>';
                        jQuery.each( data.cities, function( i, val ) {
                            append += '<option value="'+ val.name +'">'+ val.name +'</option>';
                        });
                    }

                    $('#city').append(append);
                },
                error:function(error){

                }
            });
        });
    });

    function statusMsg(){
        var isChecked=document.getElementById("switchValue").checked;
        if (isChecked){
            document.getElementById("switchMsg").innerHTML="Active";
            document.getElementById("switchMsg").style.color="cornflowerblue";
            $('#store_status').prop('value',1);
        }
        else {
            document.getElementById("switchMsg").innerHTML="Inactive";
            document.getElementById("switchMsg").style.color="#ff4d4d";
            $('#store_status').prop('value',0);
        }

    }
    </script>
</body>
<!-- end::Body -->

</html>