<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')

<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="https://cdn.datatables.net/buttons/1.6.0/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css" />

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
    <!--If Password default, show edit details -->
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Reinsurance Risk Profiles Summary
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-separator">Reports</span>
                        <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Reinsurance Risk Profiles Summary</span>
                    </div>
                </div>
                <div class="kt-subheader__toolbar">
                    <div class="kt-subheader__wrapper">
                         <a href="{{ URL::to('admin/report/reinsuranceRiskSummaryPDF') }}" class="btn btn-sm btn-elevate btn-brand btn-elevate" id="" data-toggle="kt-tooltip" title="" data-placement="left" data-original-title="Export to PDF"> <span class="kt-opacity-11" id="">PDF</span>&nbsp; <i class="flaticon2-add-1 kt-padding-l-5 kt-padding-r-0"></i> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <h3>Accidental</h3>
                        <!--begin: Datatable -->
                        <table class="table table-striped table-bordered table-hover table-checkable" id="claim_report">
                            <thead>
                            <tr>
                                <th>Policy Count</th>
                                <th>Premium</th>
                                <th>Claim Count</th>
                                <th>Payments</th>
                                <th>Total Reserve</th>
                            </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{!! $life['policy_count'] !!}</td>
                                    <td>P {!! $life['premium'] !!}</td>
                                    <td>{!! $life['claim_count'] !!}</td>
                                    <td>P {!! $life['payment'] !!}</td>
                                    <td>P {!! $life['reserve'] !!}</td>
                                </tr>
                            </tbody>
                        </table>
                        <!--end: Datatable -->
                    <br><br>
                    <h3>Motor Third Party</h3>
                     <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="claim_reports">
                            <thead>
                            <tr>
                                <th>Risk Band</th>
                                <th>Sum Insured</th>
                                <th>Policy Count</th>
                                <th>Premium</th>
                                <th>Claim Count</th>
                                <th>Payments</th>
                                <th>Total Reserve</th>
                            </tr>
                            </thead>
                            <tbody>
                                @foreach($vehicle as $v)
                                <tr>
                                    <td>{!! $v['risk_band'] !!}</td>
                                    <td>P {!! round($v['sum_assured'], 2) !!}</td>
                                    <td>{!! $v['policy_count'] !!}</td>
                                    <td>P {!! $v['premium'] !!}</td>
                                    <td>{!! $v['claim_count'] !!}</td>
                                    <td>P {!! $v['payment'] !!}</td>
                                    <td>P {!! $v['reserve'] !!}</td>
                                </tr>
                                @endforeach
                            </tbody>
                    </table>
                    <br><br>
                    <h3>Motor Comprehensive</h3>
                     <!--begin: Datatable -->
                    <table class="table table-striped table-bordered table-hover table-checkable" id="claim_reports">
                            <thead>
                            <tr>
                                <th>Risk Band</th>
                                <th>Sum Insured</th>
                                <th>Policy Count</th>
                                <th>Premium</th>
                                <th>Claim Count</th>
                                <th>Payments</th>
                                <th>Total Reserve</th>
                            </tr>
                            </thead>
                            <tbody>
                                @foreach($motorComp as $v)
                                <tr>
                                    <td>{!! $v['risk_band'] !!}</td>
                                    <td>P {!! round($v['sum_assured'], 2) !!}</td>
                                    <td>{!! $v['policy_count'] !!}</td>
                                    <td>P {!! $v['premium'] !!}</td>
                                    <td>{!! $v['claim_count'] !!}</td>
                                    <td>P {!! $v['payment'] !!}</td>
                                    <td>P {!! $v['reserve'] !!}</td>
                                </tr>
                                @endforeach
                            </tbody>
                    </table>
                        <!--end: Datatable -->
                        </div>
                </div>
            </div>
            <!-- end:: Content -->
        </div>
@endif
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

<div class="modal fade" id="delete_confirm" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
        </div>
    </div>
</div>
<script>
    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });
    });


</script>

<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script src="https://cdn.datatables.net/buttons/1.6.0/js/dataTables.buttons.min.js" ></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.0/js/buttons.html5.min.js"></script>


<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script>
    $(document).ready(function () {
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
    });
</script>


</body>
<!-- end::Body -->
</html>