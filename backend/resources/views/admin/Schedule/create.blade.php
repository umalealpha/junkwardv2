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
                        Policy Schedule
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Schedule</span> </a>
                        <a class="kt-subheader__breadcrumbs-separator"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Motor Comprehensive</span> </a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->

            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <!--begin: Datatable -->
                        <!-- begin:: Content -->
                        <form id="store" action="{{ route('admin.schedule.store') }}"
                              method="POST" enctype="multipart/form-data" class="kt-form">
                            <!-- CSRF Token -->
                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                            <table class="table table-striped table-bordered table-hover table-checkable" id="fields_table">
                                <tr>
                                    <th>FIELD NAME</th>
                                    <th scope="col">VALUE</th>
                                </tr>
                                <tr>
                                    <th scope="row">Transaction Type</th>
                                    <td><input  class="form-control" name="transaction_type" placeholder="Please mention transaction type" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Version Number</th>
                                    <td><input  class="form-control" name="version_number" placeholder="Please mention version" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Territorial Limits</th>
                                    <td><input  class="form-control" name="territorial_limits" placeholder="Please mention territorial limits" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Type Of Contract</th>
                                    <td><input  class="form-control" name="contract_type" placeholder="Please mention type of contract" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Issued By</th>
                                    <td><input  class="form-control" name="isssued_by" placeholder="Please mention company name" /></td>
                                </tr>
                            </table>

                            <H5 style="margin-top: 2%;margin-bottom: 2%">First Amount Payable Section</H5>

                            <table class="table table-striped table-bordered table-hover table-checkable" id="first_amount_payable_table">
                                <tr>
                                    <th>DESCRIPTION</th>
                                    <th scope="col">MINIMUM %</th>
                                    <th scope="col">MINIMUM
                                        AMOUNT</th>
                                    <th scope="col">FAP BASIS</th>
                                </tr>
                                <tr>
                                    <th scope="row">Own Damage </th>
                                    <td><input  class="form-control" name="own_damage_min_perc"  /></td>
                                    <td><input  class="form-control" name="own_damage_min_amt" /></td>
                                    <td><input  class="form-control" name="own_damage_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Theft/Hijacking Excess (each claim)
                                    </th>
                                    <td><input  class="form-control" name="theft_min_perc"  /></td>
                                    <td><input  class="form-control" name="theft_min_amt" /></td>
                                    <td><input  class="form-control" name="theft_min_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Underage Driver <30 Years</th>
                                    <td><input  class="form-control" name="underage_min_perc"  /></td>
                                    <td><input  class="form-control" name="underage_min_amt" /></td>
                                    <td><input  class="form-control" name="underage_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">License Issue <2 Years from Policy Issue</th>
                                    <td><input  class="form-control" name="license_issue_min_perc"  /></td>
                                    <td><input  class="form-control" name="license_issue_min_amt" /></td>
                                    <td><input  class="form-control" name="license_issue_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Windscreen / Glass</th>
                                    <td><input  class="form-control" name="windscreen_min_perc"  /></td>
                                    <td><input  class="form-control" name="windscreen_min_amt" /></td>
                                    <td><input  class="form-control" name="windscreen_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Loss of Keys</th>
                                    <td><input  class="form-control" name="key_loss_min_perc"  /></td>
                                    <td><input  class="form-control" name="key_loss_min_amt" /></td>
                                    <td><input  class="form-control" name="key_loss_fap_basis" /></td>
                                </tr>
                            </table>

                            <H5 style="margin-top: 2%;margin-bottom: 2%">EXTENSIONS</H5>

                            <table class="table table-striped table-bordered table-hover table-checkable" id="extensions">
                                <tr>
                                    <th scope="col">DESCRIPTION</th>
                                    <th scope="col">INCLUDED</th>
                                    <th scope="col">SUM INSURED</th>
                                    <th scope="col">PREMIUM</th>
                                    <th scope="col">FAP %</th>
                                    <th scope="col">FAP AMOUNT</th>
                                    <th scope="col">FAP BASIS</th>
                                </tr>
                                <tr>
                                    <th scope="row">Riot And Strike </th>
                                    <td><input  class="form-control" name="riots_strikes_included"  /></td>
                                    <td><input  class="form-control" name="riots_strikes_sum_insured" /></td>
                                    <td><input  class="form-control" name="riots_strikes_premium" /></td>
                                    <td><input  class="form-control" name="riots_strikes_fap_perc"  /></td>
                                    <td><input  class="form-control" name="riots_strikes_fap_amt" /></td>
                                    <td><input  class="form-control" name="riots_strikes_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Fire And Explosion</th>
                                    <td><input  class="form-control" name="fire_explosion_included"  /></td>
                                    <td><input  class="form-control" name="fire_explosion_sum_insured" /></td>
                                    <td><input  class="form-control" name="fire_explosion_premium" /></td>
                                    <td><input  class="form-control" name="fire_explosion_fap_perc"  /></td>
                                    <td><input  class="form-control" name="fire_explosion_fap_amt" /></td>
                                    <td><input  class="form-control" name="fire_explosion_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Window Glass</th>
                                    <td><input  class="form-control" name="window_glass_included"  /></td>
                                    <td><input  class="form-control" name="window_glass_sum_insured" /></td>
                                    <td><input  class="form-control" name="window_glass_premium" /></td>
                                    <td><input  class="form-control" name="window_glass_fap_perc"  /></td>
                                    <td><input  class="form-control" name="window_glass_fap_amt" /></td>
                                    <td><input  class="form-control" name="window_glass_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Loss Of Keys (Locks and keys)</th>
                                    <td><input  class="form-control" name="keylock_loss_included"  /></td>
                                    <td><input  class="form-control" name="keylock_sum_insured" /></td>
                                    <td><input  class="form-control" name="keylock_premium" /></td>
                                    <td><input  class="form-control" name="keylock_fap_perc"  /></td>
                                    <td><input  class="form-control" name="keylock_fap_amt" /></td>
                                    <td><input  class="form-control" name="keylock_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Third Party Liability </th>

                                    <td><input  class="form-control" name="third_party_liability_included" /></td>
                                    <td><input  class="form-control" name="third_party_liability_sum_insured"  /></td>
                                    <td><input  class="form-control" name="third_party_liability_premium" /></td>
                                    <td><input  class="form-control" name="third_party_liability_fap_perc" /></td>
                                    <td><input  class="form-control" name="third_party_liability_fap_amt"  /></td>
                                    <td><input  class="form-control" name="third_party_liability_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Wreckage Removal</th>
                                    <td><input  class="form-control" name="wreckage_removal_included"  /></td>
                                    <td><input  class="form-control" name="wreckage_removal_sum_insured" /></td>
                                    <td><input  class="form-control" name="wreckage_removal_premium" /></td>
                                    <td><input  class="form-control" name="wreckage_removal_fap_perc"  /></td>
                                    <td><input  class="form-control" name="wreckage_removal_fap_amnt" /></td>
                                    <td><input  class="form-control" name="wreckage_removal_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Tow In Costs</th>
                                    <td><input  class="form-control" name="tow_in_cost_included"  /></td>
                        s            <td><input  class="form-control" name="tow_in_cost_sum_insured" /></td>
                                    <td><input  class="form-control" name="tow_in_cost_premium" /></td>
                                    <td><input  class="form-control" name="tow_in_cost_fap_perc"  /></td>
                                    <td><input  class="form-control" name="tow_in_cost_fap_amt" /></td>
                                    <td><input  class="form-control" name="tow_in_cost_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Parts or accessories not readily
                                        available</th>
                                    <td><input  class="form-control" name="accessories_included"  /></td>
                                    <td><input  class="form-control" name="accessories_sum_insured" /></td>
                                    <td><input  class="form-control" name="accessories_premium" /></td>
                                    <td><input  class="form-control" name="accessories_fap_perc"  /></td>
                                    <td><input  class="form-control" name="accessories_fap_amt" /></td>
                                    <td><input  class="form-control" name="accessories_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Audio accessories</th>
                                    <td><input  class="form-control" name="audio_accessories_included"  /></td>
                                    <td><input  class="form-control" name="audio_accessories_sum_insured" /></td>
                                    <td><input  class="form-control" name="audio_accessories_premium" /></td>
                                    <td><input  class="form-control" name="audio_accessories_fap_perc"  /></td>
                                    <td><input  class="form-control" name="audio_accessories_fap_amt" /></td>
                                    <td><input  class="form-control" name="audio_accessories_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Car hire – theft/hijack of the vehicle</th>
                                    <td><input  class="form-control" name="car_hire_included"  /></td>
                                    <td><input  class="form-control" name="car_hire_sum_insured" /></td>
                                    <td><input  class="form-control" name="car_hire_premium" /></td>
                                    <td><input  class="form-control" name="car_hire_fap_perc"  /></td>
                                    <td><input  class="form-control" name="car_hire_fap_fap_amt" /></td>
                                    <td><input  class="form-control" name="car_hire_fap_basis" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Medical Expenses (Bodily Injury)</th>
                                    <td><input  class="form-control" name="medical_expense_included"  /></td>
                                    <td><input  class="form-control" name="medical_expense_sum_insured" /></td>
                                    <td><input  class="form-control" name="medical_expense_premium" /></td>
                                    <td><input  class="form-control" name="medical_expense_fap_perc"  /></td>
                                    <td><input  class="form-control" name="medical_expense_fap_amt" /></td>
                                    <td><input  class="form-control" name="medical_expense_fap_basis" /></td>
                                </tr>

                            </table>
                            <table class="table table-striped table-bordered table-hover table-checkable" id="memo_table">
                                <tr>
                                    <th scope="row">MEMO</th>
                                    <td><textarea  class="form-control" name="memo" placeholder="Please mention memo if any"></textarea></td>
                                </tr>
                            </table>

                            <div class="kt-portlet__foot kt-portlet__foot--solid">
                                <div class="kt-form__actions">
                                    <div class="row">
                                        <div class="col-5"></div>
                                        <div class="col-7">
                                            <button type="submit" value="Submit" id="submitbtn" class="btn btn-brand">Submit</button>
                                            <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                            <a class="btn btn-secondary" href="{{ URL::to('admin/schedule') }}" >Cancel</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
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

    $(function () {
        $('body').on('hidden.bs.modal', '.modal', function () {
            $(this).removeData('bs.modal');
        });

    });
    $("#policy_table").on("click", "a.claimTypeModal" , function(event) {
        event.preventDefault();

        $("#type").find("option:gt(0)").remove();
        var count = 0;
        var type;

        if($(this).is(".life")) {
            $("#type").append($("<option></option>").attr("value", "Life").text("Life"));
            count = count + 1;
            type = 'Life';
        }
        if($(this).is(".glass")) {
            $("#type").append($("<option></option>").attr("value", "Glass").text("Glass"));
            count = count + 1;
            type = 'Glass';
        }
        if($(this).is(".accident")) {
            $("#type").append($("<option></option>").attr("value", "Accident").text("Motor Accident"));
            count = count + 1;
            type = 'Accident';
        }

        //If Only one option then don't open modal pop up
        if(count == 1)
        {
            window.location.href = $(this).attr('href')+ '/' + type;
        } else {
            $("#submitType").attr("href", $(this).attr('href'));
            $('#claimTypeModal').modal('show');
        }
    });

    $('#type').on('change', function() {

    });

    $('#submitType').click(function(e) {

        if($('#type').val() == 0)
        {
            alert('Please select Claim Type');
            return false;
        }
        var _href = $("#submitType").attr("href");
        $("#submitType").attr("href", _href + '/' + $('#type').val());

    });

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
