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
                        Create New Repair Center
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}"
                            class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span
                            class="kt-subheader__breadcrumbs-separator"></span> <a
                            href="{{route('admin.branch.index')}}" class="kt-subheader__breadcrumbs-link"> Repair Center </a>
                        <span class="kt-subheader__breadcrumbs-separator"></span>
                        <span
                            class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <!--begin::Portlet-->
                <div class="kt-portlet">
                    <!--begin::Form-->
                    <form id="storeForm" action="{{ route('admin.repairCenters.store') }}" method="POST"
                        enctype="multipart/form-data" class="kt-form">
                        <!-- CSRF Token -->
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                        <div class="kt-portlet__body">

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Email:</label>
                                <div class="col-6">
                                    <input type="email" class="form-control" name="email"
                                           placeholder="Please provide Email Id" title="Please provide Email Id" />

                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Name:</label>
                                <div class="col-6">
                                    <input type="text" class="form-control" name="name"
                                           placeholder="Please provide store Name" title="Please provide store Name" />

                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Username:</label>
                                <div class="col-6">
                                    <input type="text" class="form-control" name="username"
                                           placeholder="Please provide Username" title="Please provide Username" />
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Mobile Number :</label>
                                <div class="col-6">
                                    <input type="number" class="form-control" name="mobile" title="Mobile Number is required" placeholder="Enter Mobile Number" autocomplete="off">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">VAT :</label>
                                <div class="col-6">
                                    <input  class="form-control" name="vat"  placeholder="Enter VAT Number" title="VAT is required">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Password:</label>
                                <div class="col-6">
                                    <input type="password" class="form-control" name="password" title="Password is required" placeholder="Enter password" autocomplete="off">
                                </div>
                            </div>


                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">State:</label>
                                <div class="col-6">
                                    <select class="form-control selectpicker" name="state" id="state"
                                        placeholder="Select state" title="Select state">
                                        @foreach($states as $state)
                                        <option value="{{ $state->id }}">{{ $state->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">City:</label>
                                <div class="col-6">
                                    <select class="form-control" name="city" id="city" placeholder="Select Partner Name"
                                            title="Select Partner Name">
                                    </select>
                                </div>
                            </div>

                            <p></p>


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
                                            <a class="btn btn-secondary"
                                                href="{{ route('admin.repairCenters.index') }}">Cancel</a>
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
        "use strict";
    // Class definition


            $('#partner-error').css("padding-top","15px");

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
                    email: {
                        required: true
                    },
                    username: {
                        required: true,
                        notEqualTo:""
                    },
                    mobile: {
                      required: true
                  },
                    password: {
                        required: true,
                        notEqualTo:""
                    },
                    state: {
                        required: true,
                        notEqualTo:""
                    },
                    city: {
                        required: true
                    },
                    vat: {
                        required: true
                    },
                },
                messages: {
                    email: {
                        required: "Please provide Email Id"
                    },
                    name: {
                        required: "Please provide repair center name"
                    },
                    username: {
                        required: "Please enter username",
                        notEqualTo: "Please enter username",
                    },
                    mobile: {
                       required: "Please provide mobile no.",
                    },
                    password: {
                        required: "Please enter Password",
                    },
                    state: {
                        required: "Please select state",
                    },
                    city: {
                        required: "Please select city"
                    },
                },



                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    KTUtil.scrollTo("branchForm", -200);
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
                url: "https://devgraphite.alphadirect.co.bw/api/frontendpay/getCities",
                dataType: 'json',
                success: function (data) {
                    $('.modelSpinner').hide();
                    $('#city').show();
                    var append = '';
                    if(data.cities.length <= 0 ){
                        // var stateName = $( "#state option:selected" ).text();
                        // append += '<option value="'+stateName+'">'+stateName+'</option>';
                        append += '<option value="">Cities are not available</option>';
                    }else{
                        append += '<option value="0">Select City</option>';
                        jQuery.each( data.cities, function( i, val ) {
                            append += '<option value="'+ val.id +'">'+ val.name +'</option>';
                        });
                    }

                    $('#city').append(append);
                },
                error:function(error){

                }
            });
        });
    });


    </script>
</body>
<!-- end::Body -->

</html>
