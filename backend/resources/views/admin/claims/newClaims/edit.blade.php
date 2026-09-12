<!DOCTYPE html>
<html lang="en" >

@include('admin.layouts.header')
<link href="{{ asset('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/tether/dist/css/tether.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datetime-picker/css/bootstrap-datetimepicker.css')}}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-timepicker/css/bootstrap-timepicker.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />

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
    <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--hor">
        <!-- begin:: Subheader -->
        <div class="kt-subheader kt-grid__item" id="kt_subheader">
            <div class="kt-subheader__main">
                <h3 class="kt-subheader__title">
                 CLAIM FORM Edit
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{route('admin.cron.index')}}" class="kt-subheader__breadcrumbs-link"> Claims Forms</a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                <form id="cron"  action="{{ route('admin.newclaims.update') }}" method="PUT" enctype="multipart/form-data" class="">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <input type="hidden" name="policyNumber" value="{{ $policy->policyNumber }}" />
                    <input type="hidden" name="newclaim_id" value="{{ $newclaim->id }}" />
                    <div class="kt-portlet__body">

                    <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    <h4 style="color: black; font-size: 17px;">Claims Management System</h4>
                                    <br>
                                    <table class="table table-striped m-table">
                                        <tbody>
                                        <tr>

                                            <th>Insured Name</th>
                                            <td></td>
                                            <th>Agency Name</th>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th>Property Address</th>
                                            <td></td>
                                            <th>Agency Address</th>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th>PolicyNumber</th>
                                            <td>{{ $policy->policyNumber }}</td>
                                            <th>Agent Name</th>
                                            <td>{{ $policy->user ? $policy->user->firstName: "-" }} {{  $policy->user ? $policy->user->lastName:"-" }}</td>

                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <hr><hr>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Select Location</label>
                            <div class="col-8">
                            <select class="form-control" id="Location" name="Location">
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}" {{ $store->id == $newclaim->location_id ? 'selected' : '' }}>{{ $store->name }}</option>
                            @endforeach
                            </select>

                            </div>
                        </div>
                      {{--  <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Is this motor claim</label>
                            <div class="col-8">
                            <label class="kt-radio" style="margin-left: 10px;">
                                    <input type="radio" name="Isthismotorclaim"  {{ $newclaim->is_motor_claim == 1 ? 'checked' : '' }}  class="form-control condition" value="1">Yes<span></span>
                                </label>
                                <label class="kt-radio" style="margin-left: 10px;">
                                    <input type="radio" name="Isthismotorclaim"  {{ $newclaim->is_motor_claim == 0 ? 'checked' : '' }} class="form-control  condition"
                                            value="0">No<span></span>
                                </label>

                            </div>
                        </div>
                        @if($policy->is_vehicle == 1)
                        <div class="form-group row" style="display: none;">
                            <label for="example-text-input" class="col-3 col-form-label">Select Motor</label>
                            <div class="col-8">
                            <select class="form-control" id="vehiclePlate" name="vehiclePlate">
                                @if(isset($policy->vehicle) && $policy->vehicle->vehiclePlate != null)
                                    <option value="{{ $policy->vehicle->vehiclePlate }}" selected>{{ $policy->vehicle->vehiclePlate }}</option>
                                @endif
                            </select>
                            </div>
                        </div>
                       @endif    --}}
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">PA Involved</label>
                            <div class="col-8">
                            <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="PAInvolved" {{ $newclaim->pa_involved == 1 ? 'checked' : '' }}  class="form-control condition" value="1">Yes<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="PAInvolved" {{ $newclaim->pa_involved == 0 ? 'checked' : '' }}   class="form-control  condition"
                                                               value="0">No<span></span>
                                                    </label>

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Attorney Involved</label>
                            <div class="col-8">
                            <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="AttorneyInvolved"  {{ $newclaim->attorney_involved == 1 ? 'checked' : '' }}   class="form-control condition" value="1">Yes<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="AttorneyInvolved" {{ $newclaim->attorney_involved == 0 ? 'checked' : '' }}   class="form-control  condition"
                                                               value="0">No<span></span>
                                                    </label>

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Claim Reported by</label>
                            <div class="col-8">
                               <select class="form-control" id="ClaimReportedby" name="ClaimReportedby">
                                        <option value="" {{ $newclaim->claim_reported_by == "" ? 'selected' : '' }}>Select Relation</option>
                                        <option value="1" {{ $newclaim->claim_reported_by == "1" ? 'selected' : '' }}>Insured</option>
                                        <option value="2" {{ $newclaim->claim_reported_by == "2" ? 'selected' : '' }}>Co-Insured</option>
                                        <option value="3" {{ $newclaim->claim_reported_by == "3" ? 'selected' : '' }}>Public Adjuster</option>
                                        <option value="4" {{ $newclaim->claim_reported_by == "4" ? 'selected' : '' }}>Others</option>
                                        <option value="5" {{ $newclaim->claim_reported_by == "5" ? 'selected' : '' }}>Agent</option>
                                </select>

                            </div>
                        </div>
<hr>


                        @if ($claimType == 'BUSINESSINTERRUPTION')
                            @include('admin.claims.newClaims.types.bussness_interruption')
                        @elseif ($claimType == 'BUSINESSALLRISKS')
                            @include('admin.claims.newClaims.types.all_risk')
                        @elseif ($claimType == 'THEFT')
                            @include('admin.claims.newClaims.types.burglary')
                        @elseif ($claimType == 'WORKERSCOMPENSATION')
                            @include('admin.claims.newClaims.types.defective_workmanship')
                        @elseif ($claimType == 'FIDELITYGUARANTEE')
                            @include('admin.claims.newClaims.types.fidelity_guarantee')
                        @else
                        <script>
                            window.location.href = "{{ url()->previous() }}";
                        </script>
                        @endif



                       <!-------->

                       <!----------------->

                       <hr><hr>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Type of Loss</label>
                            <div class="col-8">
                            <select class="form-control" id="TypeofLoss" name="TypeofLoss">
                                <option value="" {{ $newclaim->type_of_loss == "" ? 'selected' : '' }}>Select</option>
                                <option value="1" {{ $newclaim->type_of_loss == "1" ? 'selected' : '' }}>Property</option>
                                <option value="2" {{ $newclaim->type_of_loss == "2" ? 'selected' : '' }}>Liability</option>
                            </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Date of Loss</label>
                            <div class="col-8">
                                <input type="text" class="form-control kt_datepicker_1" name="DateofLoss" value="{{ $newclaim->date_of_loss }}" autocomplete="off"  placeholder="Select date" >

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Service Representative</label>
                            <div class="col-8">
                                    <select class="form-control" id="ServiceRepresentative" name="ServiceRepresentative">
                                    <option value="" selected="selected">Select</option>
                                        @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" {{ $newclaim->service_representative_id == $agent->id ? 'selected' : '' }}>{{ $agent->firstName }} {{ $agent->lastName }}</option>
                                        @endforeach
                                    </select>

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Catastrophe Loss</label>
                            <div class="col-8">
                            <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="CatastropheLoss"   {{ $newclaim->catastrophe_loss == 1 ? 'checked' : '' }}    class="form-control condition" value="1">Yes<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="CatastropheLoss"   {{ $newclaim->catastrophe_loss == 0 ? 'checked' : '' }}   class="form-control  condition"
                                                               value="0">No<span></span>
                                                    </label>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Event Name</label>
                            <div class="col-8">
                            <select class="form-control" id="EventName" name="EventName">
                                <option value="" selected="selected">Select</option>

                            </select>

                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Primary Attorney Assigned</label>
                            <div class="col-8">
                            <select class="form-control" id="PrimaryAttorneyAssigned" name="PrimaryAttorneyAssigned">
                                <option value="" {{ $newclaim->primary_attorney_assigned_id == "" ? 'selected' : '' }}>Select</option>
                                <option value="8413" {{ $newclaim->primary_attorney_assigned_id == "8413" ? 'selected' : '' }}>AKHEEL JINABHAI &amp; ASSOCIATES</option>
                                <option value="4468" {{ $newclaim->primary_attorney_assigned_id == "4468" ? 'selected' : '' }}>KELOBANG GODISANG ATTORNEYS</option>
                                <option value="6778" {{ $newclaim->primary_attorney_assigned_id == "6778" ? 'selected' : '' }}>DESAI LAW GROUP</option>
                                <option value="15249" {{ $newclaim->primary_attorney_assigned_id == "15249" ? 'selected' : '' }}>Legal Freedom Insurance Services (Pty) Ltd</option>
                                <option value="38438" {{ $newclaim->primary_attorney_assigned_id == "38438" ? 'selected' : '' }}>SALBANY &amp; TORTO ATTORNEYS</option>
                                <option value="38528" {{ $newclaim->primary_attorney_assigned_id == "38528" ? 'selected' : '' }}>LAURENCE KHUPE ATTORNEYS</option>
                                <option value="38529" {{ $newclaim->primary_attorney_assigned_id == "38529" ? 'selected' : '' }}>MINCHIN &amp; KELLY BOTSWANA</option>
                                <option value="42348" {{ $newclaim->primary_attorney_assigned_id == "42348" ? 'selected' : '' }}>COLLECTION AFRICA</option>
                                <option value="43219" {{ $newclaim->primary_attorney_assigned_id == "43219" ? 'selected' : '' }}>WOODWARD LEGAL SERVICES</option>
                                <option value="43281" {{ $newclaim->primary_attorney_assigned_id == "43281" ? 'selected' : '' }}>RAMALEPA ATTORNEY, NOTARIES &amp; CONVEYANCERS</option>
                                <option value="47318" {{ $newclaim->primary_attorney_assigned_id == "47318" ? 'selected' : '' }}>TSHEPHE LEGAL FIRM</option>
                                <option value="47830" {{ $newclaim->primary_attorney_assigned_id == "47830" ? 'selected' : '' }}>KOLE LAW PRACTICE</option>
                                <option value="47949" {{ $newclaim->primary_attorney_assigned_id == "47949" ? 'selected' : '' }}>JEREMIAH TLADI &amp; CO.</option>
                            </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Primary Attorney Assigned Date</label>
                            <div class="col-8">
                                <input type="text" class="form-control kt_datepicker_1" name="p_a_AssignedDate" value="{{ $newclaim->p_a_assigned_date }}"  autocomplete="off"  placeholder="Select date">

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Co-Attorney Assigned</label>
                            <div class="col-8">
                            <select class="form-control" id="CoAttorneyAssigned" name="CoAttorneyAssigned">
                                <option value="" {{ $newclaim->co_attorney_assigned_id == "" ? 'selected' : '' }}>Select</option>
                                <option value="15249" {{ $newclaim->co_attorney_assigned_id == "15249" ? 'selected' : '' }}>Legal Freedom Insurance Services (Pty) Ltd</option>
                                <option value="39989" {{ $newclaim->co_attorney_assigned_id == "39989" ? 'selected' : '' }}>LAERENCE KHUPE ATTORNEYS</option>
                                <option value="43220" {{ $newclaim->co_attorney_assigned_id == "43220" ? 'selected' : '' }}>WOODWARD LEGAL SERVICES</option>
                                <option value="43281" {{ $newclaim->co_attorney_assigned_id == "43281" ? 'selected' : '' }}>RAMALEPA ATTORNEY, NOTARIES &amp; CONVEYANCERS</option>
                                <option value="47318" {{ $newclaim->co_attorney_assigned_id == "47318" ? 'selected' : '' }}>TSHEPHE LEGAL FIRM</option>
                            </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Co-Attorney Assigned Date</label>
                            <div class="col-8">
                                <input type="text" class="form-control kt_datepicker_1" name="c_a_AssignedDate" value="{{  $newclaim->c_a_assigned_date }}"  autocomplete="off"  placeholder="Select date">

                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">DFS Complaint</label>
                            <div class="col-8">
                            <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="DFSComplaint"  {{ $newclaim->dfs_complaint == 1 ? 'checked' : '' }}  class="form-control condition" value="1">Yes<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="DFSComplaint" {{ $newclaim->dfs_complaint == 0 ? 'checked' : '' }}  class="form-control  condition"
                                                               value="0">No<span></span>
                                                    </label>

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Claims Allocated To</label>
                            <div class="col-8">

                            <select class="form-control" id="ClaimsAllocatedTo" name="ClaimsAllocatedTo">
                                    <option value="" selected="selected">Select</option>
                                        @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}"  {{ $newclaim->claims_allocated_to_id == $agent->id ? 'selected' : '' }} >{{ $agent->firstName }} {{ $agent->lastName }}</option>
                                        @endforeach


                            </select>

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Claims Allocated On</label>
                            <div class="col-8">
                                <input type="text" class="form-control kt_datepicker_1" name="claimsAllocatedOn" value="{{ $newclaim->claims_allocated_on }}"  autocomplete="off"  placeholder="Select date">

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Date First Visited</label>
                            <div class="col-8">
                                <input type="text" class="form-control kt_datepicker_1" name="DateFirstVisited" value="{{ $newclaim->date_first_visited }}"  autocomplete="off"  placeholder="Select date">

                            </div>
                        </div>


                        {{-- <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Claim Approved</label>
                            <div class="col-8">
                            <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="ClaimApproved" {{ $newclaim->claim_approved == 1 ? 'checked' : '' }}  class="form-control condition" value="1">Yes<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="ClaimApproved" {{ $newclaim->claim_approved == 0 ? 'checked' : '' }}  class="form-control  condition"
                                                               value="0">No<span></span>
                                                    </label>

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Claim Status</label>
                            <div class="col-8">
                            <select class="form-control" id="ClaimStatus" name="ClaimStatus">
                                    <option value="Open" {{ $newclaim->claim_status == "Open" ? 'selected' : '' }}>Open</option>
                                    <option value="Close" {{ $newclaim->claim_status == "Close" ? 'selected' : '' }}>Close</option>
                                    <option value="Re-Open" {{ $newclaim->claim_status == "Re-Open" ? 'selected' : '' }}>Re-Open</option>

                            </select>

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Claim sub Status</label>
                            <div class="col-8">
                            <select class="form-control" id="ClaimsubStatus" name="ClaimsubStatus">
                                <option value="" selected="selected">Select</option>
                            </select>
                            </div>
                        </div> --}}






                        </div>



                    <div class="kt-portlet__body">


                        <div class="kt-portlet__foot kt-portlet__foot--solid">
                            <div class="kt-form__actions">
                                <div class="row">
                                    <div class="col-3"></div>
                                    <div class="col-9">
                                        <button type="submit" id="btn" value="Submit" class="btn btn-brand">Submit</button>
                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                        <a class="btn btn-secondary" href="{{ route('admin.customer.index') }}" >Cancel</a>
                                    </div>
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
<script src="{{ asset('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-datepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-datetime-picker/js/bootstrap-datetimepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-timepicker/js/bootstrap-timepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/bootstrap-timepicker/init.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/general/bootstrap-daterangepicker/daterangepicker.js') }}" type="text/javascript"></script>

<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>





<script>
    "use strict";
    // Class definition
 $('#runtype').on('change', function() {
     var runtype = $('#runtype').val();
    if(runtype == "Hourly"){
          $('#runtime').hide();
    }else{
          $('#runtime').show();
    }


});

$(document).ready(function() {

    var val = $('input[name="loss_cause"]:checked').val();
    if(val == '0') {
        $('#otherCauseInput').show();
    } else {
        $('#otherCauseInput').hide();
    }

   $('.lossCause').click(function() {
    var Checkval = $(this).val();
       if(Checkval == '0') {
            $('#otherCauseInput').show();
       } else {
            $('#otherCauseInput').hide();
       }
   });
});

$(document).ready(function() {

var val = $('input[name="anyone_on_premises"]:checked').val();
if(val == '1') {
    $('#detailsInBriefInput').show();
} else {
    $('#detailsInBriefInput').hide();
}

$('.anyone_on_premises').click(function() {
var Checkval = $(this).val();
   if(Checkval == '1') {
        $('#detailsInBriefInput').show();
   } else {
        $('#detailsInBriefInput').hide();
   }
});
});

$('.kt_datepicker_1').datepicker({
                    rtl: KTUtil.isRTL(),
                    todayHighlight: true,
                    orientation: "bottom left",
                    // templates: arrows,
                    format: 'yyyy-mm-dd'
                });

    var KTFormControls = function () {

        jQuery.validator.addMethod("passport", function(value, element) {
            return this.optional(element) || /^[a-zA-Z0-9]+$/gi.test(value);
        }, 'Sorry ! This passport number is not valid');

        var demo1 = function () {
            $( "#cron" ).validate({

                rules: {
                    cron_name: {
                        required: true
                    },

                    run_type: {
                        required: true

                    },
                    run_time: {
                           required: function(element){
                            return $('#runtype option:selected').val() != 'Hourly';
                           }
                    },

                },

                //display error alert on form submit
                invalidHandler: function(event, validator) {
                    KTUtil.scrollTo("customerEdit", -200);
                    $('#btn').show();
                },
                submitHandler: function (form) {
                    $('#btn').hide();
                    $('#loadBtn').show()
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

    jQuery.validator.addMethod("cutomer_email", function(value, element) {
            return this.optional( element ) || /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test( value );
        }, 'Sorry ! This cutomer email is not valid');

    jQuery(document).ready(function() {
        KTFormControls.init();
    });


</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var claimStatus = document.getElementById('ClaimStatus');
        var claimSubStatus = document.getElementById('ClaimsubStatus');

        function updateClaimSubStatus() {
            var value = claimStatus.value;
            claimSubStatus.innerHTML = ''; // Clear current options

            var options = [];
            if (value === 'Open') {
                options = [
                    { value: "CLAIMSUBOPEN", text: "Open" },
                    { value: "FILEWITHACCTFORPAY", text: "File with Accounts for Payment" },
                    { value: "EXCESSTOBEPAID", text: "Excess to be paid" },
                    { value: "RECOVERYFRMTHIRDPAR", text: "Recovery from Third Party(ies)" },
                    { value: "CLIENTNOTRESPONDING", text: "Client not responding" },
                    { value: "AWAIT_EXC_PAY_OPEN", text: "Awaiting excess payment" },
                    { value: "DUPLICATE_CLM_OPEN", text: "Duplicate claim" },

                ];
            } else if (value === 'Close') {
                options = [
                    { value: "1", text: "Closed" },
                    { value: "2", text: "Subrogation" },
                    { value: "3", text: "Did not meet deductible" },
                    { value: "4", text: "Not covered Peril" },
                    { value: "5", text: "Withdrawn" },
                    { value: "6", text: "Forwarded to Attorney" },
                    { value: "PENDING_PAYMENT", text: "Pending Payment" },
                    { value: "AWAITING_SALV_PAY", text: "Awaiting Salvage payment" },
                    { value: "Repudiated", text: "Repudiated" },
                    { value: "AWAIT_EXE_PAY_CLOSE", text: "Awaiting excess payment" },
                    { value: "DUPLICATE_CLM_CLOSE", text: "Duplicate claim" },

                    { value: "SUBRTION_WRITE_CLOSE", text: "Subrogation Write-off" },


                ];
            } else if (value === 'Re-Open') {
                options = [
                    { value: "CLAIMSUBREOPEN", text: "Re-Open" },
                    { value: "AWAIT_EXE_PAY_ROPEN", text: "Awaiting excess payment" },
                    { value: "DUPLICATE_CLM_ROPEN", text: "Duplicate claim" },
                    { value: "RECOVERYFROMTP", text: "Recovery from Third Party(ies)" },
                    { value: "EXCESSTOBEPAID", text: "Excess to be paid" },
                    { value: "AWAITINGSP", text: "Awaiting salvage payment" },

                ];
            }
            var currentValue = "{{ $newclaim->claim_sub_status }}";
            options.forEach(function (option) {
                var opt = document.createElement('option');
                opt.value = option.value;
                opt.textContent = option.text;
                claimSubStatus.appendChild(opt);
                if (option.value === currentValue) {
                      opt.setAttribute('selected', 'selected');
                }
            });
        }

        claimStatus.addEventListener('change', updateClaimSubStatus);

        // Initialize on load
        updateClaimSubStatus();
    });
</script>
<script type="text/javascript">
$(document).ready(function(){
    var maxField = 30; //Input fields increment limitation
    var addButton = $('.add_button');
    var removeButton = $('.remove_button');  //Add button selector
    var wrapper = $('.Production'); //Input field wrapper
    var fieldHTML = '<div class="form-group row"><input type="text" class="form-control col-6" name="defaulting_employees_name[]" value="" placeholder="Enter Defaulting Employees Name"><input type="text" class="form-control col-5" name="defaulting_employees_position[]" value="" placeholder="Enter Defaulting Employees Position"><a href="javascript:void(0);" class="remove_button col-1"><i class="fas fa-minus " style="font-size:23px; color:red;"></a></div>'; //New input field html
    var x = 1; //Initial field counter is 1

    //Once add button is clicked
    $(addButton).click(function(){
        //Check maximum number of input fields
        if(x < maxField){
            x++; //Increment field counter
            $(wrapper).append(fieldHTML); //Add field html
        }
    });

    //Once remove button is clicked
    $(wrapper).on('click', '.remove_button', function(e){
        e.preventDefault();
        $(this).parent('div').remove(); //Remove field html
        x--; //Decrement field counter
    });
});
$(document).ready(function(){
    var maxField = 30; //Input fields increment limitation
    var addButton2 = $('.add_button2');
    var removeButton2 = $('.remove_button2');  //Add button selector
    var wrapper2 = $('.Production2'); //Input field wrapper
    var fieldHTML2 = '<div class="form-group row"> <input type="text" class="form-control col-6" name="defaulting_employees_name[]" value="" placeholder="Enter Defaulting Employees Name"><input type="text" class="form-control col-5" name="defaulting_employees_position[]" value="" placeholder="Enter Defaulting Employees Position"><a href="javascript:void(0);" class="remove_button2 col-1"><i class="fas fa-minus " style="font-size:23px; color:red;"></a></div>'; //New input field html
    var x = 1; //Initial field counter is 1

    //Once add button is clicked
    $(addButton2).click(function(){
        //Check maximum number of input fields
        if(x < maxField){
            x++; //Increment field counter
            $(wrapper2).append(fieldHTML2); //Add field html
        }
    });

    //Once remove button is clicked
    $(wrapper2).on('click', '.remove_button2', function(e){
        e.preventDefault();
        $(this).parent('div').remove(); //Remove field html
        x--; //Decrement field counter
    });
});
</script>

</body>
<!-- end::Body -->
</html>
