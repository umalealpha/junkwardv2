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
                    Edit Reinsurance Formula
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.reinsuranceFormula.index')}}" class="kt-subheader__breadcrumbs-link"> Reinsurance Formula </a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                <form id="reinsuranceFormulaEdit" action="{{ route('admin.reinsuranceFormula.update', $reinsuranceformula->id) }}"
                      method="POST" enctype="multipart/form-data" class="kt-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <div class="kt-portlet__body">
                        <div class="form-group row" >
                            <label for="example-text-input" class="col-3 col-form-label">Formula Name:</label>
                            <div class="col-9">
                                <input  class="form-control" name="formulaname" value="{!!ucwords($reinsuranceformula->formula_name) !!}"  placeholder="Enter Formula Name" title="Product type is required">

                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Formula Code:</label>
                            <div class="col-9">
                                <input  class="form-control" name="formulacode" value="{!! $reinsuranceformula->formula_code !!}" style="margin-left: 2px;"  placeholder="Enter Formula Code" title="Formula Code is required">
                            </div>
                        </div>

                        <div class="form-group row" >
                            <label for="example-text-input" class="col-3 col-form-label">Reinsurance Type</label>
                            <div class="col-9">
                                <select  name="reinsurancetype"  class="form-control kt_selectpicker"  data-live-search="true" title="Select reinsurance type">
                                    @foreach($reinsuranceTypes as $reinsuranceType)
                                        <option value="{{ $reinsuranceType->id }}" @if($reinsuranceType->id == $reinsuranceformula->reinsurance_type_id) selected @endif >{{ $reinsuranceType->type_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row" >
                            <label for="example-text-input" class="col-3 col-form-label"> Type</label>
                            <div class="col-9">
                                <select  name="type" id="type" class="form-control kt_selectpicker"  data-live-search="true" title="Select type" onchange="SelectMotor(this);">
                                    @foreach($types as $type)
                                        <option value="{{ $type->id }}" @if($type->id == $reinsuranceformula->type_id) selected @endif>{{ $type->value }}</option>
                                    @endforeach
                                </select>

                            </div>
                        </div>


                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Status</label>
                            <div class="col-9">
                                <span class="kt-switch" >
                                    <label>
                                        <input id="switchValue" type="checkbox" @if($reinsuranceformula->status) checked="checked" @endif name="status" value="1" onchange="statusMsg()">
                                        <span style="margin-top: 10px;margin-left: 10px;"></span>
                                        @if($reinsuranceformula->status)
                                            <p id="switchMsg" style="display:inline;float:left;margin-top: 15px;margin-left: 5px;color:cornflowerblue">Active</p>
                                        @else
                                            <p id="switchMsg" style="display:inline;float:left;margin-top: 15px;margin-left: 5px;color:#ff4d4d">Inactive</p>
                                        @endif
                                    </label>
                                </span>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-9">
                                <h3 class="kt-heading kt-heading--md">
                                    @foreach($products as $prod)
                                        @if($reinsuranceformula->product_id == $prod->id)
                                            <div class="form-group row">
                                                <label for="example-text-input" class="col-3 col-form-label">Product Name</label>
                                                <div class="col-9">
                                                    <p style="font-size: 16px; font-weight: normal;padding-left: 80px;"> {!! $prod->name !!}</p>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </h3>
                            </div>
                        </div>

                        @if($reinsuranceformula->group_id != NULL)
                        <div class="row">
                            <table class="table">
                                <tbody>
                                <tr style="background-color: #f7f8fa; border: none;">
                                    <th style="width:15%;">Reinsurance Group</th>
                                    <th>Operator</th>

                                    <th id="vehicle_th" @if( $reinsuranceformula->type_id != 35)style="display: none;"@endif >Motor</th>

                                    <th >SI Allocation</th>
                                    <th >Percentage</th>
                                    <th>From</th>
                                    <th >To</th>
                                </tr>
                                </tbody>
                                <tr style="border: none;">
                                    <td style="width:15%;"> <select name="group_name" class="form-control">
                                             @foreach($groups as $group)
                                                <option value="{{ $group->id }}" @if($group->id == $reinsuranceformula->group_id) selected @endif>{{ $group->group_name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td style="width:15%;"> <select name="operator" class="form-control">
                                            <option value="=" @if($reinsuranceformula->operator == '=') selected @endif>=</option>
                                            <option value="<<" @if($reinsuranceformula->operator == '<<') selected @endif><<</option>
                                                <option value="<=" @if($reinsuranceformula->operator == '<=') selected @endif><=</option>
                                                <option value="=>" @if($reinsuranceformula->operator == '=>') selected @endif>=></option>
                                                <option value="!=" @if($reinsuranceformula->operator == '!=') selected @endif>!=</option>
                                                <option value="between" @if($reinsuranceformula->operator == 'between') selected @endif>Between</option>
                                                <option value="not between" @if($reinsuranceformula->operator == 'not between') selected @endif>Not Between</option>
                                        </select>
                                    </td>

                                    <td id="vehicle_type" @if( $reinsuranceformula->type_id != 35)style="display: none;" @endif> <select name="vehicle_type"  class="form-control">
                                            <option value="0">select</option>
                                            <option value="1" @if($reinsuranceformula->vehicle_type == 1) selected @endif>Motor</option>
                                            <option value="2" @if($reinsuranceformula->vehicle_type == 2) selected @endif>Motor Cycle</option>
                                            <option value="3" @if($reinsuranceformula->vehicle_type == 3) selected @endif>Caraven</option>
                                        </select>
                                    </td>

                                    <td style="width:15%;">
                                        <input  type="text" class="form-control" name="si_allocation"  title="Enter si allocation" value="{{ $reinsuranceformula->si_allocation }}">
                                    </td>
                                    <td style="width:15%;">
                                        <input  type="text" class="form-control" name="percentage"   title="Enter percentage" value="{{ $reinsuranceformula->percentage }}">
                                    </td>
                                    <td style="width:15%;">
                                        <input  type="text" class="form-control kt_datepicker_1" autocomplete="off" title="Enter start date" name="datefrom"  value="{{ $reinsuranceformula->date_from }}">
                                    </td>
                                    <td style="width:15%;">
                                        <input  type="text" class="form-control kt_datepicker_1" name="dateto" title="Enter end date" autocomplete="off" value="{{ $reinsuranceformula->date_to }}">
                                    </td>
                                </tr>
                            </table>
                        </div>
                        @endif

                    </div>
                    {{--kt prtlet body ends--}}
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    @can('reinsurance-formula-edit')
                                    <button type="submit" value="Submit" id="submit" class="btn btn-brand">Update</button>
                                    @endcan
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

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>



<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#reinsuranceFormulaEdit" ).validate({
// define validation rules
                rules: {
                    formulaname: {
                        required: true
                    },
                    formulacode: {
                        required: true
                    },
                    reinsurancetype: {
                        required: true
                    },
                    type: {
                        required: true
                    },
                    si_allocation:{
                        required: true
                    },
                    percentage:{
                        required: true
                    },
                    datefrom:{
                        required: true
                    },
                    dateto:{
                        required: true
                    },

                },

//display error alert on form submit
                invalidHandler: function(event, validator) {
                    $('#submit').show();
                    KTUtil.scrollTo("regionEdit", -200);
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


    function SelectMotor(e){
        var type_id= e.options[e.selectedIndex].value;
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