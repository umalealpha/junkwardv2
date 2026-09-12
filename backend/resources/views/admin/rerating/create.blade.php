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
                    Create
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}"
                                                                                 class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span
                            class="kt-subheader__breadcrumbs-separator"></span> <a
                            href="{{route('admin.branch.index')}}" class="kt-subheader__breadcrumbs-link"> Store </a>
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
                <form id="storeForm" action="{{ route('admin.re-rating.store') }}" method="POST"
                      enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">

                        <div class="form-group row" style="margin-bottom: 1rem;">
                            <label for="example-text-input" class="col-3 col-form-label">Role :</label>
                            <div class="col-6">
                                <select class="form-control selectpicker" name="role"
                                        placeholder="Select Role" title="Select Role">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row" style="margin-bottom: 1rem;">
                            <label for="example-text-input" class="col-3 col-form-label">Rerating Type :</label>
                            <div class="col-6">
                                <select class="form-control selectpicker" name="type" id="rerating_type">
                                    <option value="">Select Type</option>
                                    <option value="1">Quote</option>
                                    <option value="2">Policy</option>
                                    <option value="3">Policy Renewal</option>
                                    <option value="4">Sum Insured Upto (Pula)</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group row" id="discountDiv">
                            <label for="example-text-input" class="col-3 col-form-label">Discount (%):</label>
                            <div class="col-6">
                                <input type="text" class="form-control" name="discount"
                                       placeholder="Please provide discount value in %" title="Please provide discount value in %" />

                            </div>
                        </div>
                        <div class="form-group row" id="surchargeDiv">
                            <label for="example-text-input" class="col-3 col-form-label">Surcharge (%):</label>
                            <div class="col-6">
                                <input type="text" class="form-control" name="surcharge"
                                       placeholder="Please provide surchage value in %" title="Please provide surchage value in %" />
                            </div>
                        </div>
                        <div class="form-group row" id="sumInsuredDiv">
                            <label for="example-text-input" class="col-3 col-form-label">Sum Insured (Pula):</label>
                            <div class="col-6">
                                <input type="text" class="form-control" name="sumInsured"
                                    placeholder="Please provide sum insured" title="Please provide sum insured" />
                            </div>
                        </div>
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
                                        <a class="btn btn-secondary" href="{{ route('admin.re-rating') }}">Cancel</a>
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
                    partner: {
                        required: true,
                        notEqualTo:""
                    },
                    state: {
                        required: true,
                        notEqualTo:""
                    },
                    city: {
                        required: true,
                        notEqualTo:""
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
                    },
                    city: {
                        required: "Please select city",
                        notEqualTo: "Please select city",
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
        $("#sumInsuredDiv").css('display','none');
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

                url: "{{ env('GRAPHITE_URL') }}" + "/api/frontendpay/getCities",
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
                        append += '<option value="">Select City</option>';
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

            }
            else {
                document.getElementById("switchMsg").innerHTML="Inactive";
                document.getElementById("switchMsg").style.color="#ff4d4d";
            }

        }

        $("#rerating_type").on("change",function(){
            // rerating_type = $(this).val();
            if ($(this).val() == 4) {
                $("#discountDiv").hide();
                $("#surchargeDiv").hide();
                $("#sumInsuredDiv").show();
            } else {
                $("#discountDiv").show();
                $("#surchargeDiv").show();
                $("#sumInsuredDiv").hide();
            }
        });
</script>
</body>
<!-- end::Body -->

</html>
