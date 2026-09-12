<!DOCTYPE html>
<html lang="en" >
@include('admin.layouts.header')
<link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
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
    <!--If Password default, show edit details -->
    @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Instalment Information
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Quotes</span> </a>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">View Quote</span> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <form id="updateInstalment" action="{{ route('admin.updateInstalmentData') }}"
                              method="POST" enctype="multipart/form-data" class="kt-form">
                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <input type="hidden" name="client_number" value="{{ $ins['ClientNumber'] }}" />
                            <input type="hidden" name="contract_number" value="{{ $ins['ContractNumber'] }}" />
                            <input type="hidden" name="contractSeq" value="{{ $ins['ContractSequence'] }}" />
                            <input type="hidden" name="instalmentSequence" value="{{ $ins['InstalmentSequence'] }}" />
                            <input type="hidden" name="instalmentReferenceNumber" value="{{ $ins['InstalmentReferenceNumber'] }}" />
                            <input type="hidden" name="instalmentStatus" value="{{ $ins['InstalmentStatus'] }}" />

                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table">
                            <thead>
                            <tr>
                                <th>Client Number:</th>
                                @if($ins['ClientNumber'])
                                    <td width="50%">{{ $ins['ClientNumber'] }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Contract Number:</th>
                                @if($ins['ContractNumber'])
                                    <td width="50%">{{ $ins['ContractNumber'] }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Contract Sequence:</th>
                                @if($ins['ContractSequence'])
                                    <td width="50%">{{ $ins['ContractSequence'] }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Instalment Sequence:</th>
                                @if($ins['InstalmentSequence'])
                                    <td width="50%">{{ $ins['InstalmentSequence'] }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Instalment Reference Number:</th>
                                @if($ins['InstalmentReferenceNumber'])
                                    <td width="50%">{{ $ins['InstalmentReferenceNumber'] }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Instalment Action Date:</th>
                                @if($ins['InstalmentActionDate'])
                                    <td width="50%">
                                        <input type="text" class="form-control kt_datepicker_1 dob" name="instalmentDate" id="dob" autocomplete="off" value="{{ $ins['InstalmentActionDate'] }}"  placeholder="Select date"/>
                                    </td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Instalment Amount:</th>
                                @if($ins['InstalmentAmount'] != null && $ins['InstalmentStatus'] == 'A')
                                    <td width="50%"><input type="number" class="form-control" name="insAmount" title="Please provide instalment amount" placeholder="Please provide instalment amount" value="{{ $ins['InstalmentAmount'] }}"/></td>
                                @else
                                    <td width="50%">{{ $ins['InstalmentAmount'] }}</td>
                                @endif
                            </tr>

                            <tr>
                                <th>Instalment Status:</th>
                                @if($ins['InstalmentStatus'])
                                    <td width="50%">{{ $ins['InstalmentStatus'] }}</td>
                                @else
                                    <td width="50%">N/A</td>
                                @endif
                            </tr>
                            <tr>
                                <th>Last Update Date:</th>
                                @if($ins['LastUpdateDate'])
                                    <td width="50%">{{ \Carbon::parse($ins['LastUpdateDate'])->format('Y-m-d') }}</td>
                                @else
                                    <td width="50%">-</td>
                                @endif
                            </tr>
                            </thead>
                        </table>
                            <div class="kt-portlet__foot kt-portlet__foot--solid">
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-5"></div>
                                        <div class="col-7">
                                            <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Update</button>
                                            <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                            <a class="btn btn-secondary" href="{{ URL::to('admin/customerVehicleInspection') }}" >Cancel</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <!--end: Datatable -->
                        </form>
                    </div>
                </div>
            </div>
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
<div class="modal fade" id="claimTypeModal" tabindex="-1" role="dialog" aria-labelledby="user_delete_confirm_title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" id="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="exampleModalLabel">Type of Claim</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
            </div>
            <div class="modal-body">
                <h5>What type of Claim you want to process ?</h5>
                <select name="type" class="form-control" id="type">
                    <option value="0">Please select claim type</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                <a href="" id="submitType" type="button" class="btn btn-brand">Confirm</a></div>
        </div>
    </div>
</div>
</div>
@include('admin.layouts.scripts')


<script src="{{  asset('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<script>

    jQuery(document).ready(function() {
        KTDatatablesDataSourceAjaxServer.init();
    });
    $('#policyStatus_filter').on('change',function(){
        policyStatus_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

    $('#product_filter').on('change',function(){
        product_filter = $(this).val();
        KTDatatablesDataSourceAjaxServer.draw();
    });

</script>
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
