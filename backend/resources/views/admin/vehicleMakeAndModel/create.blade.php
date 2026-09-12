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
                    Create Vehicle Make And Model
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('vehicle_make_model')}}" class="kt-subheader__breadcrumbs-link"> Vehicle Make And Model </a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                <form id="ruleCreate" action="{{ route('vehicle_make_model_add_new_vehicle_store') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <table class="block block-rounded block-bordered table table-bordered" id="ruleDiv" >
                            <thead style="text-align: center;">
                            <th >Vehicle Make </th>
                            <th >Add Another Model</th>
                            <th >Vehicle Type Type</th>

                            </thead>
                            <tbody id="product_table_body table-data">
                            <thead>
                            <tr style="height: auto!important;" class="fieldGroup">
                                <td>
                                    <select class="form-control kt_selectpicker" title="Please choose Vehicle Make " data-live-search="true" name="s_Make" >
                                        @foreach($deviceMake as $makeModel)
                                            <option value="{{$makeModel->s_Make}}">{{$makeModel->s_Make}}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input class="form-control kt_selectpicker" title="Please choose name" data-live-search="true" name="s_Variant">
                                </td>
                                <td>
                                    <select class="form-control kt_selectpicker entry_type" title="Please choose vehicle type"   name="s_VehicleType">
                                        @foreach($deviceMakeModelType as $type)
                                            <option value="{{$type->s_VehicleType}}">{{$type->s_VehicleType}}</option>
                                        @endforeach
                                    </select>
                                </td>

                            </tr>
                            </thead>
                        </table>
                        <span style="text-align:center;margin-top:3%;color:red;display:none;font-size:15px" id="noteId" ><text style="font-weight: bold">Error:</text> &nbsp Atleast 1 Credit entry and 1 Debit entry is mandatory.</span>
                        <span style="text-align:center;margin-top:3%;color:red;display:none;font-size:15px" id="maxMsg" ><text style="font-weight: bold">Note:</text> &nbsp Maximum 11 groups are allowed.</span>
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9" style="margin-left:41% ">
                                    <button type="submit" value="Submit" id="sbtBtn" class="btn btn-brand " >Submit</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('vehicle_make_model') }}" >Cancel</a>
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
<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

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
            $( "#ruleCreate" ).validate({
// define validation rules
                rules: {
                    make_id:{
                        required:true
                    },
                    name:{
                        required:true
                    },
                    device_type:{
                        required:true
                    },
                },

//display error alert on form submit
                invalidHandler: function(event, validator) {
                    $('#submit').hide();
                    KTUtil.scrollTo("ruleCreate", -200);
                },

                submitHandler: function (form) {
                    $('#sbtBtn').hide();
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

    $(document).ready(function(){
//group add limit
        var maxGroup = 11;

//remove fields group
        $("body").on("click",".remove",function(){
            $(this).parents(".fieldGroup").remove();
            if($('body').find('.fieldGroup').length < maxGroup){
                $('#maxMsg').hide();
                $("#sbtBtn").prop("disabled", false);
            }
        });
    });

    $(document).on('change', '.entry_type', function(){
        $("#sbtBtn").prop("disabled",false);
        $("#noteId").hide();
    });

    

    $(document).ready(function(){
        var ajaxRequest;
        $('#transaction_type').on('change', function(){
            var trans_type = $(this).val();
            ajaxRequest = setTimeout(function(sn) {
                $.ajax({
                    url: '{{ route('admin.rules.getTransSubtype') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "trans_type": trans_type,
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data) {
                        if (data) {
                            $('#transaction_sub_type').empty();
                            $.each(data.count, function(key, subtype){
                                $('#transaction_sub_type').append('<option value="' + subtype.id + '">' + subtype.name + '</option>');
                            });
                        } else {
                            $('#transaction_sub_type').empty();
                        }
                        $("#transaction_sub_type").selectpicker('refresh');
                    }
                });
            }, 200);

        });
    });

</script>

</body>
<!-- end::Body -->
</html>