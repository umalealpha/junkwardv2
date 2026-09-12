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
                    Create Product Plan
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>

                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{  URL::to('admin/productPlan') }}" class="kt-subheader__breadcrumbs-link"> Product Plan </a> <span class="kt-subheader__breadcrumbs-separator"></span>

                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">

                <form id="planForm" action="{{ route('admin.productPlan.store') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Product Name:</label>
                            <div class="col-9">
                                <select name="product_id" id="product_id" class="form-control kt_selectpicker" data-live-search="true" title="Select product name">
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" data-product_type="{{ $product->product_type_id }}">{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p></p>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Product plan Name:</label>
                            <div class="col-9">
                                <input  class="form-control" name="name" title="Please enter product plan name">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Product Plan Customer Friendly Name:</label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="slug" title="Please enter product plan customer friendly name" value="" >
                            </div>
                        </div>
                        <div class="form-group row" id="premiumDivId" style="display: none;">
                            <label for="example-text-input" class="col-3 col-form-label" >Premium:</label>
                            <div class="col-9">
                                <input  class="form-control" name="premium" title="Please enter premium" >
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Sum Insured/Assured:</label>
                            <div class="col-9">
                                <input class="form-control" name="sum"  id="sum" title="Please enter sum assured">
                                <p id="sumError" style="display:none;color:red"> Sum Insured/Assured value should be less than or Equal to <span id="sum_value"></span></p>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">FlutterWave Plan Id:</label>
                            <div class="col-9">
                                <input class="form-control" name="flutter_plan_id"  id="flutterPlanId" title="Please enter flutter wave plan id">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Billing (in days):</label>
                            <div class="col-9">
                                <input class="form-control"  name="billing"  id="billing" title="Please enter value in days" placeholder="Please enter value in days">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label" title="please select formula">Region:</label>
                            <div class="col-9">
                                <select name="region" class="form-control kt_selectpicker" data-live-search="true" title="Please select region ">
                                    @foreach($regions as $region)
                                        <option value="{{ $region->id }}">{{ $region->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p></p>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Status</label>
                            <div class="col-9">
                                <span class="kt-switch" >
                                    <label>
                                        <input id="switchValue" type="checkbox" checked="checked" name="status" value="1" onchange="statusMsg()">
                                        <span style="margin-top: 10px;margin-left: 10px;"></span>
                                         <p id="switchMsg" style="display:inline;float:left;margin-top: 15px;margin-left: 5px;color:cornflowerblue">Active</p>
                                    </label>
                                </span>
                            </div>
                        </div>

                        <!-- Repeater Section -->
                        <div id="repeater_section" style="display: none;">
                            <div id="coapplicant_repeater">
                                <!-- Existing repeater item -->
                                <div class="form-group repeater-item">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <label for="coapplicant_id">Sr No.</label>
                                            <input type="text" name="coapplicant_id[]" class="form-control" placeholder="Enter ID">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="coapplicant_relation">Relation</label>
                                            <input type="text" name="coapplicant_relation[]" class="form-control" placeholder="Enter relation">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="coapplicant_premium">Premium</label>
                                            <input type="text" name="coapplicant_premium[]" class="form-control" placeholder="Enter premium">
                                        </div>
                                        <div class="col-md-2">
                                            <label>&nbsp;</label>
                                            <!-- Button to add new repeater item -->
                                            <button type="button" id="addBtn" class="btn btn-success">Add</button>
                                        </div>
                                    </div>
                                </div>
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
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>

                                    <a class="btn btn-secondary" href="{{ route('admin.productPlan.index') }}" >Cancel</a>
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

<script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<!-- jQuery Script for Repeater Functionality -->
<script>
    $(document).ready(function(){
        // Function to check product type and toggle repeater section
        function checkProductType() {
            let selectedOption = $("#product_id option:selected");
            let productTypeId = selectedOption.data("product_type");

            if (productTypeId == 4) {
                $("#repeater_section").show();
                $("#premiumDivId").hide();
            } else {
                $("#repeater_section").hide();
                $("#premiumDivId").show();
            }
        }

        // Run on page load (if editing an existing product)
        checkProductType();

        // Run when product is changed
        $("#product_id").change(function() {
            checkProductType();
        });

      // When "Add Coapplicant" button is clicked
      $("#addBtn").click(function(){
        // New repeater item HTML
        var newRow = `
          <div class="form-group repeater-item">
            <div class="row">
              <div class="col-md-2">
                <label for="coapplicant_id">Sr No.</label>
                <input type="text" name="coapplicant_id[]" class="form-control" placeholder="Enter ID">
              </div>
              <div class="col-md-4">
                <label for="coapplicant_relation">Relation</label>
                <input type="text" name="coapplicant_relation[]" class="form-control" placeholder="Enter relation">
              </div>
              <div class="col-md-4">
                <label for="coapplicant_premium">Premium</label>
                <input type="text" name="coapplicant_premium[]" class="form-control" placeholder="Enter premium">
              </div>
              <div class="col-md-2">
                <label>&nbsp;</label>
                <button type="button" class="btn btn-danger remove-btn">Remove</button>
              </div>
            </div>
          </div>`;
        // Append the new repeater item to the container
        $("#coapplicant_repeater").append(newRow);
      });

      // When a remove button is clicked, remove the corresponding repeater item
      $(document).on('click', '.remove-btn', function(){
        $(this).closest('.repeater-item').remove();
      });
    });
</script>

<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
        // Private functions

        var demo1 = function () {
            $( "#planForm" ).validate({
                // define validation rules
                rules: {
                    name: {
                        required: true
                    },
                    slug: {
                        required: true
                    },
                    product_id: {
                        required: true
                    },
                    premium_type: {
                        required: true
                    },
                    // premium: {
                    //     required: true

                    // },
                    product_type_id:{
                        required: true
                    },

                    sum: {
                        required: true

                    },
                    formula: {
                        required: true

                    },
                    region: {
                        required: true

                    },

                },

                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    $('#submit').show();
                    KTUtil.scrollTo("planForm", -200);
                },

                submitHandler: function (form) {
                    $('#submit').hide();
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
            document.getElementById("switchMsg").style.color="cornflowerblue";/**/

        }
        else {
            document.getElementById("switchMsg").innerHTML="Inactive";
            document.getElementById("switchMsg").style.color="#ff4d4d";
        }

    }
</script>
<script>
    $(document).ready(function() {
        var ajaxRequest;
        $('#sum').keyup(function() {
            var value = $(this).val();
            var product_id = $("#product_id").val();
            clearTimeout(ajaxRequest);
            ajaxRequest = setTimeout(function(sn) {

                $.ajax({
                    url: '{{ route('admin.productPlan.checkSumAssured') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "sum": value,
                        "product_id": product_id
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if(data.error) {
                            $('#sum_value').html(data.value);
                            $('#sumError').css('display','block');
                            $('#submit').attr("disabled",true);
                        } else {
                            $('#sumError').css('display','none');
                            $('#submit').attr("disabled",false);
                        }
                    }
                });


            }, 500, value);
        });
    });
</script>

</body>
<!-- end::Body -->
</html>
