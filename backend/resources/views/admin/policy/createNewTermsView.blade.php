<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
 <link href="{{  asset('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
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
     <!--If Password default -->
     @if (Auth::user()->password == null)
        @include('includes.reset')
    @else
        <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
            <!-- begin:: Subheader -->
            <div class="kt-subheader kt-grid__item" id="kt_subheader">
                <div class="kt-subheader__main">
                    <h3 class="kt-subheader__title">
                        Create New Terms
                    </h3>
                    <span class="kt-subheader__separator kt-hidden"></span>
                    <div class="kt-subheader__breadcrumbs">
                        <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                        <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span>
                        <a href="{{Route('admin.policy.index')}}" class="kt-subheader__breadcrumbs-link"><span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">Create New Terms</span> </a>
                    </div>
                    <div>
                        <a class="btn btn-info" href="{{ URL::previous() }}" style="margin-left: 1130px;" >Back</a>
                    </div>
                </div>
            </div>
            <!-- end:: Subheader -->

            <!-- begin:: Content -->
            <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">
                <div class="kt-portlet kt-portlet--mobile">
                    <div class="kt-portlet__body">
                        <div class="kt-portlet__body">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title">Premium Details</h3>
                            </div>
                            <table class="table table-striped table-bordered table-hover table-checkable" id="policy_table3">
                                <thead>
                                <tr>
                                    <th>Old Premium:</th>
                                    <td width="50%">
                                        {{ $policy_renewal->old_premium }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Old Frequency:</th>
                                    <td width="50%">
                                        @if($policy->premium_freq == 1) Monthly
                                        @elseif($policy->premium_freq == 2) Three Installments
                                        @elseif($policy->premium_freq == 3) Annual
                                        @else N/A
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Old Sum Insured:</th>
                                    <td width="50%">
                                        @if (isset($motorCompQuotes->estimatedValue))
                                            {{ $motorCompQuotes->estimatedValue }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>New Premium:</th>
                                    <td width="50%">
                                        {{-- @isset($premium->new_value) --}}
                                            @if (isset($premium->new_value))
                                                {{ $premium->new_value }}
                                                {{-- <input type="hidden" name="new_premium" value="{{ $premium->new_value }}"> --}}
                                            @elseif (isset($policy_renewal->new_premium))
                                                {{ $policy_renewal->new_premium }}
                                                {{-- <input type="hidden" name="new_premium" value="{{ $policy_renewal->new_premium }}"> --}}
                                            @else
                                                {{ $policy->premium }}
                                                {{-- <input type="hidden" name="new_premium" value="{{ $policy_premium->premium }}"> --}}
                                            @endif
                                        {{-- @endisset --}}
                                    </td>
                                </tr>
                                <tr>
                                    <th>New Monthly Premium:</th>
                                    <td width="50%">
                                        @if (isset($newMonthlyPremium))
                                            {{ $newMonthlyPremium }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>New Three Installment Premium:</th>
                                    <td width="50%">
                                        @if (isset($newThreeInstlPremium))
                                            {{ $newThreeInstlPremium }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>New Sum Insured:</th>
                                    <td width="50%">
                                        @if (isset($newSumAssured))
                                            {{ $newSumAssured }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Term Expiry Date:</th>
                                    <td width="50%">
                                        @if (isset($policy_term))
                                            {{ $policy_term->term_end_date }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                </thead>
                            </table>
                            <br><br>
                            <form action="{{ route('admin.policy.addTermsToPoliciesWithButton') }}" id="createNewTermsForm" method="POST">
                                @csrf
                                <div class="row">
                                    <div class="col-lg-8">
                                        <div class="form-group">
                                            <label for="policyNumber">Policy Number</label>
                                            <input type="text" class="form-control" name="policyNumber" id="policyNumber" placeholder="Please enter policy number" value="{{$policyNumber}}" readonly>
                                            <span id="policyNumberNotice"></span>
                                        </div>

                                        <div class="form-group">
                                            <label for="term_start">Term Start Date</label>
                                            <input type="text" class="form-control kt_datepicker_1 required validateGroup1"
                                            name="term_start_date" id="term_start_date" autocomplete="off" placeholder="Select start date of policy" />
                                        </div>

                                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                                            <div class="kt-form__actions">
                                                <div class="row">
                                                    <div class="col-3"></div>
                                                    <div class="col-9">
                                                        <button type="submit" id="btn" value="Submit" class="btn btn-brand">Submit</button>
                                                        {{-- <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button> --}}
                                                        {{-- <button class="btn btn-warning" type ="reset" >Reset</button> --}}
                                                        <a class="btn btn-secondary" href="{{-- {{ route('admin.dashboard') }} --}}" >Cancel</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </form>
                        </div>
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







</div>

@include('admin.layouts.scripts')
<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script>
    $(document).ready(function(){
        $('.kt_datepicker_1').datepicker({
            rtl: KTUtil.isRTL(),
            todayHighlight: true,
            orientation: "bottom left",
            // templates: arrows,
            format: 'yyyy-mm-dd',
            minDate : 0,
        });

        jQuery.validator.addMethod("policyNumberVal", function(value, element) {
                return this.optional(element) || /^[a-zA-Z]{3}[0-9]{10}$/.test(value);
        }, 'Please enter valid policy number ex. MIS2022004214');

        jQuery.validator.addMethod("money", function(value, element) {
                return this.optional(element) || /^\d{0,10}(\.\d{0,2})?$/.test(value);
        }, 'Please enter valid amount');

        $( "#createNewTermsForm" ).validate({
            rules: {
                policyNumber:{
                    required:true,
                    policyNumberVal:true,
                    maxlength:13,
                },
                term_start_date:{
                    required:true,
                }
            },
            messages:{}
        });
    });
</script>
</body>
<!-- end::Body -->
</html>
