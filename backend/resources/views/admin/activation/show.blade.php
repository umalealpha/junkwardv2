<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
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
    {{--  @if (Auth::user()->default_password == "111111")
     @include('includes.reset')
     @else --}}
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                    View Activation Code
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{ URL::to('admin/activation') }}" class="kt-subheader__breadcrumbs-link"> Activation Code </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                    <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View</span>
                </div>
            </div>
        </div>
        <!-- end:: Subheader -->
        <!-- begin:: Content -->
        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
            <!--begin::Portlet-->
            <div class="tab-content" id="labelDiv">
                <div class="tab-pane active" id="kt_portlet_base_demo_3_1_tab_content" role="tabpanel">
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">
                                    Activation Code Details
                                </h3>
                            </div>
                        </div>
                        <div class="kt-portlet_body">
                            <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    <table class="table table-striped m-table">
                                        <tbody>
                                        <tr>
                                            <th>Activation Code</th>
                                            @if($activation && $activation->activation_code != null)
                                                <td>{!! $activation->activation_code !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Serial Code</th>
                                            @if($activation && $activation->serial_code != null)
                                                <td>{!! $activation->serial_code !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            @if($activation && $activation->status == 1)
                                                <td style="color:red;">{{ "Used" }}</td>
                                            @else
                                                <td style="color:#008000;">{{ "Still not used" }}</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Product Plan</th>
                                            @if($productPlan && $productPlan->name != null)
                                                <td>{!! $productPlan->name !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Product Type</th>
                                            @if($productType && $productType->name != null)
                                                <td>{!! $productType->name !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif
                                        </tr>

                                        <tr>
                                            <th>Branch</th>
                                            @if($branch && $branch->name != null)
                                                <td>{!! $branch->name !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Vendor</th>
                                            @if($vendor && $vendor->name != null)
                                                <td>{!! $vendor->name !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Rack Number</th>
                                            @if($activation && $activation->rack_no != null)
                                                <td>{!! $activation->rack_no !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Trial periods(In Days)</th>
                                            @if($activation && $activation->trial_periods != null)
                                                <td>{!! $activation->trial_periods !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Trial periods Coverage</th>
                                            @if($activation && $activation->trial_coverage != null)
                                                <td>{!! $activation->trial_coverage !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>City</th>
                                            @if($activation && $activation->city != null)
                                                <td>{!! $activation->city !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif

                                        </tr>
                                        <tr>
                                            <th>State</th>
                                            @if($activation && $activation->state != null)
                                                <td>{!! $activation->state !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <th>Country</th>
                                            @if($activation && $activation->country != null)
                                                <td>{!! $activation->country !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif

                                        </tr>
                                        <tr>
                                            <th>Email</th>
                                            @if($activation && $activation->email != null)
                                                <td>{!! $activation->email !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif

                                        </tr>
                                        <tr>
                                            <th>Cellphone</th>
                                            @if($activation && $activation->cellphone != null)
                                                <td>{!! $activation->cellphone !!}</td>
                                            @else
                                                <td>-</td>
                                            @endif

                                        </tr>

                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="kt-portlet__foot">
                            <div class="row">
                                <div class="col-12">
                                    <a class="btn btn-secondary" href="{{ route('admin.activation.index') }}" >Cancel</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- end:: Content -->
    </div>
    {{--  @endif --}}
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
<script src="{{ asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/app/custom/general/components/forms/layouts/repeater.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>

<script src="{{  asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>

<script>
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
                templates: arrows
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
        //Motor Items
        //group add limit
        var maxGroup = 10;
        //add more fields group
        $(".addMore").click(function(){
            if($('body').find('.fieldGroup').length < maxGroup){
                var fieldHTML = '<tr class="fieldGroup">'+$(".fieldGroupCopy").html()+'</tr>';
                $('body').find('.fieldGroup:last').before(fieldHTML);
            }else{
                alert('Maximum '+maxGroup+' groups are allowed.');
            }
        });
        //remove fields group
        $("body").on("click",".remove",function(){
            $(this).parents(".fieldGroup").remove();
        });
    });
    $('#addMember').click(function() {
        $('#addMemberDiv').toggle();
        $('#addMember').toggle();
    });
</script>






</body>
<!-- end::Body -->
</html>