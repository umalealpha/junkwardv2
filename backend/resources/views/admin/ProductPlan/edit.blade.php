<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />


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
                    Edit Product Plan
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{  URL::to('admin/productPlan') }}" class="kt-subheader__breadcrumbs-link"> Product Plan </a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                <form id="planForm" action="{{ route('admin.productPlan.update', $productPlan->id) }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <input type="hidden" name="_method" value="PUT" />
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Product Name:</label>
                            <div class="col-9">
                                <select name="product" id="product_id" class="form-control kt_selectpicker" data-live-search="true">
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" @if($productPlan->product_id == $product->id) selected
                                                @endif>{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Product Plan Name:</label>
                            <div class="col-9">
                                <input type="name" class="form-control" name="name" value="{!! $productPlan->name !!}" >
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Product Plan Customer Friendly Name:</label>
                            <div class="col-9">
                                <input type="text" class="form-control" name="slug" value="{!! $productPlan->slug !!}" >
                            </div>
                        </div>
                        @if ($productTypeId->product_type_id != 4)
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Premium:</label>
                                <div class="col-9">
                                    <input type="name" class="form-control" name="premium" value="{!! $productPlan->premium !!}" >
                                </div>
                            </div>
                        @endif
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">FlutterWave Plan Id:</label>
                            <div class="col-9">
                                <input class="form-control" name="flutter_plan_id"  id="flutterPlanId" value="{!! $productPlan->flutter_plan_id !!}" title="Please enter flutter wave plan id">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Billing (in days):</label>
                            <div class="col-9">
                                <input class="form-control" name="billing"  id="billing" title="Please enter value in days" @if($productPlan->billing) value="{!! $productPlan->billing !!}" @endif placeholder="Please enter value in days">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Sum Insured/Asssured:</label>
                            <div class="col-9">
                                <input id="sum" class="form-control" name="sum" value="{!! $productPlan->sum_assured !!}" title="sum assured is required">
                                <p id="sumError" style="display:none;color:red"> Sum Insured/Assured value should be less than or Equal to <span id="sum_value"></span></p>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Status</label>
                            <div class="col-9">
                                    <span class="kt-switch" >
                                        <label>
                                            <input id="switchValue" type="checkbox" @if($productPlan->status) checked="checked" @endif name="status" value="1" onchange="statusMsg()">
                                            <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                @if($productPlan->status)
                                                <p id="switchMsg" style="display:inline;float:left;margin-top: 15px;margin-left: 5px;color:cornflowerblue">Active</p>

                                            @else
                                                <p id="switchMsg" style="display:inline;float:left;margin-top: 15px;margin-left: 5px;color:#ff4d4d">Inactive</p>
                                            @endif

                                        </label>
                                    </span>
                            </div>
                        </div>

                        @if ($productTypeId->product_type_id == 4)
                            <!-- Repeater Section -->
                            <div id="coapplicant_repeater">
                                <!-- Existing repeater item -->
                                @foreach($productPlan->premiumAndRelation as $index => $data)
                                    <div class="form-group repeater-item">
                                        <div class="row">
                                            <div class="col-md-2">
                                                <label for="coapplicant_id">Sr No.</label>
                                                <input type="text" name="coapplicant_id[]" class="form-control" placeholder="Enter ID" value="{{ isset($data['id']) ? $data['id'] : '' }}" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="coapplicant_relation">Relation</label>
                                                <input type="text" name="coapplicant_relation[]" class="form-control" placeholder="Enter relation" value="{{ $data['relation'] }}">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="coapplicant_premium">Premium</label>
                                                <input type="text" name="coapplicant_premium[]" class="form-control" placeholder="Enter premium" value="{{ $data['premium'] }}">
                                            </div>
                                            <div class="col-md-2">
                                                <label>&nbsp;</label>
                                                @if($index == 0)
                                                    <button type="button" id="addBtn" class="btn btn-success">Add</button>
                                                @else
                                                    <button type="button" class="btn btn-danger remove-btn">Remove</button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    @can('product-plan-edit')
                                        <button type="submit" id="submit" value="Submit" class="btn btn-brand">Update</button>
                                    @endcan
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
              <div class="col-md-5">
                <label for="coapplicant_relation">Relation</label>
                <input type="text" name="coapplicant_relation[]" class="form-control" placeholder="Enter relation">
              </div>
              <div class="col-md-5">
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
                    product: {
                        required: true
                    },
                    premium_type: {
                        required: true
                    },
                    // premium: {
                    //     required: true
                    // },
                    sum: {
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
