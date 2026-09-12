<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('/assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />

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
                    Create Reinsurance Formula
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.reinsuranceFormula.index')}}" class="kt-subheader__breadcrumbs-link"> Reinsurance Formula </a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                <form id="reinsuranceFormulaCreate" action="{{ route('admin.reinsuranceFormula.store') }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <div class="form-group row" >
                            <label for="example-text-input" class="col-3 col-form-label">Formula Name</label>
                            <div class="col-9">
                                <input  class="form-control" name="formulaname"  placeholder="Enter Formula Name" title="Product type is required">

                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Formula Code</label>
                            <div class="col-9">
                                <input  class="form-control" name="formulacode" style="margin-left: 2px;"  placeholder="Enter Formula Code" title="Formula Code is required">
                            </div>
                        </div>
                        <div class="form-group row" >
                            <label for="example-text-input" class="col-3 col-form-label">Product</label>
                            <div class="col-9">
                                <select name="product" id="product_id" class="form-control kt_selectpicker"  data-live-search="true" title="Select product name">
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" >{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row" >
                            <label for="example-text-input" class="col-3 col-form-label">Reinsurance Type</label>
                            <div class="col-9">
                                <select  name="reinsurancetype"  class="form-control kt_selectpicker"  data-live-search="true" title="Select reinsurance type">
                                    @foreach($reinsuranceTypes as $reinsuranceType)
                                        <option value="{{ $reinsuranceType->id }}" >{{ $reinsuranceType->type_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row" >
                            <label for="example-text-input" class="col-3 col-form-label"> Type</label>
                            <div class="col-9">
                                <select  name="type" id="type" class="form-control kt_selectpicker"  data-live-search="true" title="Select type" onchange="SelectMotor(this);">
                                    @foreach($types as $type)
                                        <option value="{{ $type->id }}" >{{ $type->value }}</option>
                                    @endforeach
                                </select>

                            </div>
                        </div>
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
                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        <div class="row">
                            <table class="table table-striped m-table">
                                <tbody id="groupDiv"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <button type="submit" value="Submit" id="submit" class="btn btn-brand">Submit</button>
                                    <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                    <a class="btn btn-secondary" href="{{ route('admin.reinsuranceFormula.index') }}" >Cancel</a>
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



<script>
    "use strict";
    // Class definition
    var KTFormControls = function () {
// Private functions
        var demo1 = function () {
            $( "#reinsuranceFormulaCreate" ).validate({
// define validation rules
                rules: {
                    formulaname: {
                        required: true
                    },
                    formulacode: {
                        required: true
                    },
                    product: {
                        required: true
                    },
                    reinsurancetype: {
                        required: true
                    },
                    type: {
                        required: true
                    },
                },

//display error alert on form submit
                invalidHandler: function(event, validator) {
                    KTUtil.scrollTo("reinsuranceTypeCreate", -200);
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
            document.getElementById("switchMsg").style.color="cornflowerblue";

        }
        else {
            document.getElementById("switchMsg").innerHTML="Inactive";
            document.getElementById("switchMsg").style.color="#ff4d4d";
        }

    }

    /*onclick function on product*/
    $('#product_id').on('change', function (e, data) {
        var product_id = this.value;
        var selected = '';
        $.ajax({
            url: '{{ route('admin.reinsuranceFormula.group') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "id": product_id,
            },
            type: 'post',
            datatype: 'json',
            success: function (data) {

                var append = '';
                var groups = '';
                if(data.groups.length != 0) {
                    groups += '<tr>' +
                        '<th>Reinsurance Group</th>' +
                        '<th>Operator</th>' +
                        '<th id="vehicle_th" style="display: none;">Motor</th>' +
                        '<th>SI Allocation</th>' +
                        '<th>Percentage</th>' +
                        '<th>From</th>' +
                        '<th>To</th>' +
                        '</tr>';
                    groups += '<tr>' +
                        '<td><select class="form-control" name="group_name" id="group_name">' ;
                    $.each(data.groups, function () {
                        groups += '<option value="">select</option>' +
                            '<option value="'+this.id+'">'+this.group_name+'</option>';
                    });
                    groups +='</select></td>' +
                        '<td><select class="form-control"  name="operator" >' +
                        '<option value="">select</option>' +
                        '<option value="=">=</option>' +
                        '<option value="<<"><</option>' +
                        '<option value="<="><=</option>' +
                        '<option value="=>">=></option>' +
                        '<option value="!=">!=</option>' +
                        '<option value="between">Between</option>' +
                        '<option value="not between">Not Between</option>' +
                        '</select></td>'+
                        '<td id="vehicle_type" style="display:none;"><select class="form-control" name="vehicle_type" >' +
                        '<option value="0">select</option>' +
                        '<option value="1">Motor</option>' +
                        '<option value="2">Motor Cycle</option>' +
                        '<option value="3">Caraven</option>' +
                        '</select></td>'+
                        '<td><input class="form-control" name="si_allocation" id="si_allocation"></td>'+
                        '<td><input class="form-control" name="percentage" id="percentage" placeholder="(%)"></td>'+
                        '<td><input type="text" class="form-control kt_datepicker_1" name="datefrom" id="datefrom" autocomplete="off" placeholder="Select Start date"/></td>'+
                        '<td><input type="text" class="form-control kt_datepicker_1" name="dateto" id="dateto" autocomplete="off" placeholder="Select end date"/></td>'+
                        '</tr>';

                    $('#groupDiv').empty();
                    $('#groupDiv').append(groups);
                    $('#datefrom').rules('add',  { required: true,  messages: { required: "Please select end date" } });
                    $('#dateto').rules('add',  { required: true,  messages: { required: "Please select start date" } });
                    $('#si_allocation').rules('add', { required: true, messages: { required: "Please enter SI allocation" } });
                    $('#percentage').rules('add',  { required: true,  messages: { required: "Please enter percentage" } });
                    KTFormControls.init();
                    KTBootstrapDatepicker.init();
                }
                else{
                    $('#groupDiv').empty();
                    $('#datefrom').rules('remove','required');
                    $('#si_allocation').rules('remove','required');
                    $('#percentage').rules('remove','required');
                    $('#dateto').rules('remove','required');
                    $('<p style="font-weight: bold;">No Groups are available</p>').appendTo('#groupDiv');
                }
            }
        });

    });

    function SelectMotor(e){
        var type_id= e.options[e.selectedIndex].value;
        /* Id */
        if(type_id == 35){
            $("#vehicle_type").css('display','block');
            $("#vehicle_th").css('display','block');
        }
        else{
            $("#vehicle_type").css('display','none');
            $("#vehicle_th").css('display','none');

        }

    };
    </script>
{{--
<script>
    let inputElement = document.querySelector("#si_allocation");
         inputElement.addEventListener("keyup",(event)=>{
      var tempNumber = inputElement.value.replace(/,/gi, "");
      var commaSeparatedNumber = tempNumber.split(/(?=(?:\d{3})+$)/).join(",");
      inputElement.value = commaSeparatedNumber;
     })

</script> --}}
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

<script>
"use strict";
    // Class definition
    var KTBootstrapDatepicker = function () {
        var arrows;
        if (KTUtil.isRTL()) {
            arrows = {
                leftArrow: '<i class="la la-angle-right"></i>',
                rightArrow: '<i class="la la-angle-left"></i>'
            }
        } else {
            arrows = {
                leftArrow: '<i class="la la-angle-left"></i>',
                rightArrow: '<i class="la la-angle-right"></i>'
            }
        }
        // Private functions
        var demos = function () {
            // minimum setup
            $('.kt_datepicker_1').datepicker({
                rtl: KTUtil.isRTL(),
                todayHighlight: true,
                orientation: "bottom left",
                templates: arrows,
                format: 'yyyy-mm-dd'
            });
        }
        return {
            // public functions
            init: function() {
                demos();
            }
        };
    }();
    jQuery(document).ready(function() {
        KTBootstrapDatepicker.init();
    });


</script>







</body>
<!-- end::Body -->
</html>
