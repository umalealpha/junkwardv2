<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

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
                    View Reinsurance Group Coverage
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin.reinsuranceGroupCoverage.index')}}" class="kt-subheader__breadcrumbs-link">Reinsurance Group Coverage</a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                    <div class="kt-portlet__body">
                        <table class="table table-striped m-table">
                            <tbody>
                            <tr>
                                <th>Group Code</th>
                                <td>{{ $reinsuranceGroup->group_code }}</td>
                            </tr>
                            <tr>
                                <th>Group Name</th>
                                <td>{{ $reinsuranceGroup->group_name }}</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                @if($reinsuranceGroup->status)
                                <td>Active</td>
                                @else
                                    <td>Inactive</td>
                                @endif
                            </tr>

                            <tr>
                                <th>Product Name</th>
                                @foreach($products as $prod)
                                    @if($reinsuranceGroup->product_id == $prod->id)
                                        <td> {!! $prod->name !!}</td>
                                    @endif
                                @endforeach
                            </tr>
                            </tbody>
                        </table>

                        {{-- @if($groupCoverages->group_id.length() != 0)--}}
                        <div class="row">
                            <table class="table">
                                <tbody>
                                <tr style="background-color: #f7f8fa; border: none;">
                                    <th>COVERAGE NAME</th>
                                    <th>SI/PREMIUM</th>
                                    <th>RI LIMIT</th>
                                    <th>LIMIT</th>
                                </tr>
                                </tbody>
                                {{-- @endif--}}

                                @foreach($groupCoverages as $key => $cover)
                                    <tr style="border: none;">
                                        <td><input type="hidden"  name="groupCoverage_id[]" value="{!! $cover->id !!}">{!! $cover->coverage_name !!}</td>
                                        <td>
                                            @if($cover->si_premium == 1) <p class="form-control">SumInsured</p> @endif
                                            @if($cover->si_premium == 2) <p class="form-control">Premium</p> @endif

                                        </td>
                                        <td>
                                                @if($cover->ri_limit == 1) <p class="form-control">SumInsured</p> @endif>
                                                @if($cover->ri_limit == 2) <p class="form-control">Skip</p> @endif>
                                                @if($cover->ri_limit == 3) <p class="form-control">Other</p> @endif>
                                        </td>
                                        <td>
                                            <p @if($cover->ri_limit != 3)style="display:none;"@endif>{{ $cover->limit_value }}</p>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>

                    </div>
                    <div class="kt-portlet__foot kt-portlet__foot--solid">
                        <div class="kt-form__actions">
                            <div class="row">
                                <div class="col-3"></div>
                                <div class="col-9">
                                    <a class="btn btn-secondary" href="{{ route('admin.reinsuranceGroupCoverage.index') }}" >Back</a>
                                </div>
                            </div>
                        </div>
                    </div>
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
<script>
    "use strict";
    // Class definition

    var KTFormControls = function () {
// Private functions

        var demo1 = function () {
            $( "#reinsuranceGroupEdit" ).validate({
// define validation rules
                rules: {
                    group_code: {
                        required: true
                    },
                    group_name:{
                        required:true
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

    function AlertVal(e,id){
        var limit_id= e.options[e.selectedIndex].value;
        var input_id ='#limit_'+id;
        if(limit_id == 3 ){
            $(input_id).css('display','block');
        }
        else {
            $(input_id).css('display','none');
        }
    };
</script>




</body>
<!-- end::Body -->
</html>