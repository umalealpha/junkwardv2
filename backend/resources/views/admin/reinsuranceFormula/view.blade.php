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
                    View Reinsurance Formula
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.reinsuranceFormula.index')}}" class="kt-subheader__breadcrumbs-link"> Reinsurance Formula </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <!--begin::Form-->
                    <div class="kt-portlet__body">

                        <table class="table table-striped m-table">
                            <tbody>
                            <tr>
                                <th>Formula Name</th>
                                <td>{!! ucwords($reinsuranceformula->formula_name) !!}</td>

                            </tr>
                            <tr>
                                <th>Formula Code</th>
                                <td>{!! $reinsuranceformula->formula_code !!}</td>

                            </tr>
                            <tr>
                                <th>Reinsurance Type</th>
                                @if($reinsuranceTypes != null)
                                    @foreach($reinsuranceTypes as $reinsuranceType)
                                        @if($reinsuranceType->id == $reinsuranceformula->reinsurance_type_id)
                                <td>{!! $reinsuranceformula->type_name !!}</td>
                                        @endif
                                    @endforeach
                                @else
                                <td>No data found</td>
                                @endif
                            </tr>

                            <tr>
                                <th>Type</th>
                                @if($types != null)
                                    @foreach($types as $type)
                                        @if($type->id == $reinsuranceformula->type_id)
                                <td>{!! $reinsuranceformula->formula_code !!}</td>
                                        @endif
                                    @endforeach
                                @else
                                <td>No data found</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Status</th>
                                @if($reinsuranceformula->status)
                                <td>Active</td>
                                @else
                                <td>Inactive</td>
                                    @endif
                            </tr>

                            <tr>
                                <th>Product Name</th>
                                @foreach($products as $prod)
                                    @if($reinsuranceformula->product_id == $prod->id)
                                        <td>{!! $prod->name !!}</td>
                                    @endif
                                @endforeach
                            </tr>
                            </tbody>
                        </table>



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
                                        <td style="width:15%;">
                                                @if($groups != null)
                                                @foreach($groups as $group)
                                                    @if($group->id == $reinsuranceformula->group_id)
                                                        <p class="form-control">{{ $group->group_name }}</p>
                                                    @endif
                                                @endforeach
                                                    @else
                                                <p class="form-control">No data found</p>
                                                    @endif
                                        </td>
                                        <td style="width:15%;">
                                                    @if($reinsuranceformula->operator == '=')<p class="form-control">==</p>@endif
                                                    @if($reinsuranceformula->operator == '<<')<p class="form-control"><<</p>@endif
                                                    @if($reinsuranceformula->operator == '<=')<p class="form-control"><=</p>@endif
                                                    @if($reinsuranceformula->operator == '=>')<p class="form-control">=></p>@endif
                                                    @if($reinsuranceformula->operator == '!=')<p class="form-control">!=</p>@endif
                                                    @if($reinsuranceformula->operator == 'between')<p class="form-control">Between</p>@endif
                                                    @if($reinsuranceformula->operator == 'not between')<p class="form-control">Not Between</p>@endif
                                        </td>

                                        <td id="vehicle_type" @if( $reinsuranceformula->type_id != 35)style="display: none;" @endif> <select name="vehicle_type"  class="form-control">
                                                <option value="0">select</option>
                                                <option value="1" @if($reinsuranceformula->vehicle_type == 1) selected @endif>Motor</option>
                                                <option value="2" @if($reinsuranceformula->vehicle_type == 2) selected @endif>Motor Cycle</option>
                                                <option value="3" @if($reinsuranceformula->vehicle_type == 3) selected @endif>Caraven</option>
                                            </select>
                                        </td>

                                        <td style="width:15%;">
                                            <p class="form-control">{{ $reinsuranceformula->si_allocation }}</p>
                                        </td>
                                        <td style="width:15%;">
                                            <p class="form-control">{{ $reinsuranceformula->percentage }}</p>
                                        </td>
                                        <td style="width:15%;">
                                            <p  class="form-control">{{ $reinsuranceformula->date_from }}</p>
                                        </td>
                                        <td style="width:15%;">
                                            <p class="form-control">{{ $reinsuranceformula->date_to }}</p>
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
                                    <a class="btn btn-secondary" href="{{ route('admin.reinsuranceFormula.index') }}" >Back</a>
                                </div>
                            </div>


                        </div>
                    </div>
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
