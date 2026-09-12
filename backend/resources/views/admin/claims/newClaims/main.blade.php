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
                 CLAIM FORM
                </h3>
                <span class="kt-subheader__separator kt-hidden"></span>
                <div class="kt-subheader__breadcrumbs">
                    <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
                    <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{Route('admin-dashboard')}}" class="kt-subheader__breadcrumbs-link"> Dashboard </a> <span class="kt-subheader__breadcrumbs-separator"></span> <a href="{{route('admin.cron.index')}}" class="kt-subheader__breadcrumbs-link"> Claims Forms</a> <span class="kt-subheader__breadcrumbs-separator"></span>
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
                <form id="cron"  action="{{ route('admin.policy.storeClaim') }}" method="POST" enctype="multipart/form-data" class="">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <input type="hidden" name="policy_id" value="{{ $policy->id }}" />
                    <input type="hidden" name="policyNumber" value="{{ $policy->policyNumber }}" />
                    <input type="hidden" name="type" value="{{ $claimType }}" />

                    <div class="kt-portlet__body">

                    <div class="kt-section">
                                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                    <h4 style="color: black; font-size: 17px;">Claims Management System</h4>
                                    <br>
                                    <table class="table table-striped m-table">
                                        <tbody>
                                        <tr>

                                            <th>Insured Name</th>
                                            <td>
                                                @if($policy->profile->entity_type=='Organisation')
                                                    {!! $policy->profile->company->name??"" !!}
                                                @else
                                                    {!! $policy->customer->firstName??"" !!} {!! $policy->customer->lastName??"" !!}
                                                @endif
                                            </td>
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
                            <select class="form-control kt_selectpicker" data-live-search="true" id="Location" name="Location">
                                @foreach($riskAddress as $address)
                                    <option value="{{ $address->id }}">{{ $address->address_name }}</option>
                                @endforeach
                            </select>

                            </div>
                        </div>
                        @if($claimType == 'MOTORACCIDENT' || $claimType == 'MOTORTRADERSEXTERNAL' || $claimType == 'MOTORTRADERSINTERNAL' || $claimType == 'GLASS' || $claimType == 'LOCKSANDKEYS')
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Is this motor claim</label>
                                <div class="col-8">
                                <label class="kt-radio" style="margin-left: 10px;">
                                        <input type="radio" name="Isthismotorclaim"    class="form-control Isthismotorclaim required" value="1">Yes<span></span>
                                    </label>
                                    <label class="kt-radio" style="margin-left: 10px;">
                                        <input type="radio" name="Isthismotorclaim"  class="form-control  Isthismotorclaim required"
                                                value="0">No<span></span>
                                    </label>

                                </div>
                            </div>

                            <div class="form-group row" id="vehiclePlateDiv" style="display: none;">
                                <label for="example-text-input" class="col-3 col-form-label">Select Motor</label>
                                <div class="col-8">
                                <select class="form-control kt_selectpicker" data-live-search="true" id="vehiclePlate" name="vehiclePlate">
                                    <option value="">Please Select</option>
                                    @if(isset($vehiclePlateNos) && $vehiclePlateNos != null)
                                        @foreach ($vehiclePlateNos as $vehiclePlate)
                                            <option value="{{ $vehiclePlate->registration_no }}">{{ $vehiclePlate->registration_no }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                </div>
                            </div>
                       @endif
                        {{-- <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">PA Involved</label>
                            <div class="col-8">
                            <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="PAInvolved"    class="form-control condition" value="1">Yes<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="PAInvolved"  class="form-control  condition"
                                                               value="0">No<span></span>
                                                    </label>

                            </div>
                        </div> --}}


                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Claim Reported by</label>
                            <div class="col-8">
                               <select class="form-control kt_selectpicker" data-live-search="true" id="ClaimReportedby" name="ClaimReportedby">
                                        <option value="" selected="selected">Select Relation</option>
                                        @foreach($claimReportedBy as $opts)
                                            <option value="{{$opts->value}}">{{$opts->value}}</option>
                                        @endforeach
                                </select>

                            </div>
                        </div>
                        @if ($claimType == 'BUSINESSINTERRUPTION' || $claimType == 'BUSINESSALLRISKS' || $claimType == 'ELECTRONICEQUIPMENT' || $claimType == 'PERSONALALLRISKS' || $claimType == 'THEFT' || $claimType == 'DEFECTIVEWORKMANSHIP' || $claimType == 'FIDELITYGUARANTEE' || $claimType == 'WORKERSCOMPENSATION' || $claimType == 'STATEDBENEFITS' || $claimType == 'MONEY')
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Claim Sub Type</label>
                                <div class="col-8">
                                <select class="form-control kt_selectpicker" data-live-search="true" id="ClaimSubType" name="ClaimSubType">
                                    @foreach($claimSubType as $type)
                                        <option value="{{$type->id}}">{{$type->value}}</option>
                                    @endforeach
                                </select>
                                </div>
                            </div>
                        @endif
<hr>


                        @if ($claimType == 'BUSINESSINTERRUPTION')
                            @include('admin.claims.newClaims.types.bussness_interruption')
                        @elseif ($claimType == 'BUSINESSALLRISKS' || $claimType == 'ELECTRONICEQUIPMENT' || $claimType == 'PERSONALALLRISKS')
                            @include('admin.claims.newClaims.types.all_risk')
                        @elseif ($claimType == 'THEFT' || $claimType == 'MONEY')
                            @include('admin.claims.newClaims.types.burglary')
                        @elseif ($claimType == 'WORKERSCOMPENSATION' || $claimType == 'STATEDBENEFITS')
                            @include('admin.claims.newClaims.types.workers_compensation')
                        @elseif ($claimType == 'DEFECTIVEWORKMANSHIP')
                            @include('admin.claims.newClaims.types.defective_workmanship')
                        @elseif ($claimType == 'FIDELITYGUARANTEE')
                            @include('admin.claims.newClaims.types.fidelity_guarantee')
                        @elseif ($claimType == 'TRAVELINSURANCE')
                            @include('admin.claims.newClaims.types.travel_insurance')
                        @elseif ($claimType == 'GOODSINTRANSIT')
                            @include('admin.claims.newClaims.types.goods_in_transit')
                        @elseif ($claimType == 'FIRE')
                            @include('admin.claims.newClaims.types.fire')
                        @elseif ($claimType == 'PROPERTYDAMAGE' || $claimType == 'ACCIDENTALDAMAGE' || $claimType == 'HOUSEHOLDERS' || $claimType == 'HOUSEOWNERS' || $claimType == 'HOUSEOWNER-BUILDINGS' || $claimType == 'HOUSEHOLDERS-CONTENTS')
                            @include('admin.claims.newClaims.types.property_loss_damage')
                        @elseif ($claimType == 'LIABILITY')
                            @include('admin.claims.newClaims.types.public_liability')
                        @elseif ($claimType == 'MOBILEELECTRONICDEVICES' || $claimType == 'OFFICECONTENTS')
                            @include('admin.claims.newClaims.types.mobileAndElectronicDevices')
                        @elseif ($claimType == 'GLASS')
                            @include('admin.claims.newClaims.types.glass')
                        @elseif ($claimType == 'MOTORACCIDENT' || $claimType == 'MOTORTRADERSEXTERNAL' || $claimType == 'MOTORTRADERSINTERNAL')
                            @include('admin.claims.newClaims.types.accident')
                        @elseif ($claimType == 'LOCKSANDKEYS')
                            @include('admin.claims.newClaims.types.key_loss')
                        @else
                        <script>
                            window.location.href = "{{ url()->previous() }}";
                        </script>
                        @endif



                       <!-------->

                       <!----------------->

                       <hr>
                       @if ($claimType != 'GLASS' && $claimType != 'MOTORACCIDENT')

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Total Reserve amount:</label>
                                <div class="col-8">
                                    <input type="text" class="form-control"  title="Please enter the amount" name="reserve_amount" autocomplete="off" id="reserve_amount" aria-describedby="emailHelp"  placeholder="P0.00"   >
                                    <p style="display:none; color:red;" id="reserveMsg"></p>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Total Paid amount:</label>
                                <div class="col-8">
                                    <input type="text" class="form-control"  title="Please enter the amount" name="paid_amount" autocomplete="off" id="paid_amount" aria-describedby="emailHelp"  placeholder="P0.00"   >
                                    <p style="display:none; color:red;" id="paidmsg"></p>
                                </div>
                            </div>

                        @endif

                        @if ($claimType != 'THEFT' && $claimType != 'BUSINESSALLRISKS' && $claimType != 'ELECTRONICEQUIPMENT' && $claimType != 'PERSONALALLRISKS' && $claimType != 'FIDELITYGUARANTEE' && $claimType != 'DEFECTIVEWORKMANSHIP' && $claimType != 'GOODSINTRANSIT' && $claimType != 'WORKERSCOMPENSATION' && $claimType != 'STATEDBENEFITS' && $claimType != 'Glass')
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Reported by Broker/Agent</label>
                                <div class="col-8">
                                <select class="form-control kt_selectpicker" data-live-search="true" id="reportedByBrokerAgent" name="reportedByBrokerAgent">
                                            <option value="" selected="selected">Select Broker/Agent</option>
                                            @foreach($agents_options as $option)
                                            {{-- <option value="{{ $agent->id }}" >{{ $agent->firstName }} {{ $agent->lastName }}</option> --}}
                                            <option value="{{ $option['id'] }}">
                                                {{ $option['name'] }} ({{ $option['type'] }})
                                            </option>
                                            @endforeach
                                    </select>

                                </div>
                            </div>
                        @endif

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Type of Loss</label>
                            <div class="col-8">
                            <select class="form-control kt_selectpicker" data-live-search="true" id="TypeofLoss" name="TypeofLoss">
                                <option value="" selected="selected">Select</option>
                                <option value="1">Property</option>
                                <option value="2">Liability</option>
                            </select>
                            </div>
                        </div>

                        @if ($claimType != 'GOODSINTRANSIT' && $claimType != 'FIRE' && $claimType != 'GLASS' && $claimType != 'PROPERTYDAMAGE')
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Date of Loss</label>
                                <div class="col-8">
                                    <input type="text" class="form-control kt_datepicker_1" name="DateofLoss" value="" autocomplete="off"  placeholder="Select date" >

                                </div>
                            </div>
                        @endif

                        @if ($claimType != 'BUSINESSINTERRUPTION')
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Service Representative</label>
                                <div class="col-8">
                                        <select class="form-control kt_selectpicker" data-live-search="true" id="ServiceRepresentative" name="ServiceRepresentative">
                                        <option value="" selected="selected">Select</option>
                                            @foreach($agents as $agent)
                                            <option value="{{ $agent->id }}" >{{ $agent->firstName }} {{ $agent->lastName }}</option>
                                            @endforeach
                                        </select>

                                </div>
                            </div>
                        @endif
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Catastrophe Loss</label>
                            <div class="col-8">
                                <select class="form-control condition kt_selectpicker" data-live-search="true" id="CatastropheLoss" name="CatastropheLoss">
                                    <option value="" selected="selected">Select</option>
                                        <option value="1" >Yes</option>
                                        <option value="0" >No</option>
                                    </select>
                                    {{-- <label class="kt-radio" style="margin-left: 10px;">
                                        <input type="radio" name="CatastropheLoss"    class="form-control condition" value="1">Yes<span></span>
                                    </label>
                                    <label class="kt-radio" style="margin-left: 10px;">
                                        <input type="radio" name="CatastropheLoss"  class="form-control  condition"
                                                value="0">No<span></span>
                                    </label> --}}
                            </div>
                        </div>

                        @if ($claimType != 'LIABILITY' && $claimType != 'GOODSINTRANSIT' && $claimType != 'FIRE' && $claimType != 'GLASS')
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Description of Loss</label>
                                <div class="col-8">
                                    <textarea type="text" class="form-control" name="description_of_loss" value=""  placeholder="Description of Loss."></textarea>
                                </div>
                            </div>
                        @endif
                        @if ($claimType != 'THEFT' && $claimType != 'BUSINESSINTERRUPTION' && $claimType != 'LIABILITY' && $claimType != 'GOODSINTRANSIT' && $claimType != 'FIRE' && $claimType != 'WORKERSCOMPENSATION' && $claimType != 'STATEDBENEFITS' && $claimType != 'PROPERTYDAMAGE' &&  $claimType != 'ACCIDENTALDAMAGE' && $claimType != 'MOBILEELECTRONICDEVICES' && $claimType != 'OFFICECONTENTS' && $claimType != 'GLASS' && $claimType != 'MOTORACCIDENT' && $claimType != 'HOUSEHOLDERS' && $claimType != 'HOUSEOWNERS' && $claimType != 'HOUSEOWNER-BUILDINGS' && $claimType != 'HOUSEHOLDERS-CONTENTS')
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Event Name</label>
                                <div class="col-8">
                                    <input type="text" class="form-control" name="EventName" value=""  placeholder="Enter event name">

                                </div>
                            </div>
                        @endif

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Primary Attorney Involved</label>
                            <div class="col-8">
                            <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="AttorneyInvolved"    class="form-control attorney_radio" value="1">Yes<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="AttorneyInvolved"  class="form-control  attorney_radio"
                                                               value="0">No<span></span>
                                                    </label>

                            </div>
                        </div>
                        <div class="form-group row" id="primary_attorney_assigned" style="display: none;">
                            <label for="example-text-input" class="col-3 col-form-label">Primary Attorney Assigned</label>
                            <div class="col-8">
                            <select class="form-control kt_selectpicker" data-live-search="true" id="PrimaryAttorneyAssigned" name="PrimaryAttorneyAssigned">
                                <option value="" selected="selected">Select</option>
                                <option value="8413">AKHEEL JINABHAI &amp; ASSOCIATES</option>
                                <option value="4468">KELOBANG GODISANG ATTORNEYS</option>
                                <option value="6778">DESAI LAW GROUP</option>
                                <option value="15249">Legal Freedom Insurance Services (Pty) Ltd</option>
                                <option value="38438">SALBANY &amp; TORTO ATTORNEYS</option>
                                <option value="38528">LAURENCE KHUPE ATTORNEYS</option>
                                <option value="38529">MINCHIN &amp; KELLY BOTSWANA</option>
                                <option value="42348">COLLECTION AFRICA</option>
                                <option value="43219">WOODWARD LEGAL SERVICES</option>
                                <option value="43281">RAMALEPA ATTORNEY, NOTARIES &amp; CONVEYANCERS</option>
                                <option value="47318">TSHEPHE LEGAL FIRM</option>
                                <option value="47830">KOLE LAW PRACTICE</option>
                                <option value="47949">JEREMIAH TLADI &amp; CO.</option>
                            </select>
                            </div>
                        </div>
                        <div class="form-group row"  id="primary_attorney_assigned_date" style="display: none;">
                            <label for="example-text-input" class="col-3 col-form-label">Primary Attorney Assigned Date</label>
                            <div class="col-8">
                                <input type="text" class="form-control kt_datepicker_1" name="p_a_AssignedDate" value=""  autocomplete="off"  placeholder="Select date">

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Co-Attorney Involved</label>
                            <div class="col-8">
                            <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="co_attorney_involved"    class="form-control co_attorney_radio" value="1">Yes<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="co_attorney_involved"  class="form-control  co_attorney_radio"
                                                               value="0">No<span></span>
                                                    </label>

                            </div>
                        </div>
                        <div class="form-group row" id="co_attorney_assigned" style="display: none;">
                            <label for="example-text-input" class="col-3 col-form-label">Co-Attorney Assigned</label>
                            <div class="col-8">
                            <select class="form-control kt_selectpicker" data-live-search="true" id="CoAttorneyAssigned" name="CoAttorneyAssigned">
                                <option value="" selected="selected">Select</option>
                                <option value="15249">Legal Freedom Insurance Services (Pty) Ltd</option>
                                <option value="39989">LAERENCE KHUPE ATTORNEYS</option>
                                <option value="43220">WOODWARD LEGAL SERVICES</option>
                                <option value="43281">RAMALEPA ATTORNEY, NOTARIES &amp; CONVEYANCERS</option>
                                <option value="47318">TSHEPHE LEGAL FIRM</option>
                            </select>
                            </div>
                        </div>

                        <div class="form-group row" id="co_attorney_assigned_date" style="display: none;">
                            <label for="example-text-input" class="col-3 col-form-label">Co-Attorney Assigned Date</label>
                            <div class="col-8">
                                <input type="text" class="form-control kt_datepicker_1" name="c_a_AssignedDate" value=""  autocomplete="off"  placeholder="Select date">

                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">DFS Complaint</label>
                            <div class="col-8">
                            <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="DFSComplaint"    class="form-control condition" value="1">Yes<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="DFSComplaint"  class="form-control  condition"
                                                               value="0">No<span></span>
                                                    </label>

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Claims Allocated To</label>
                            <div class="col-8">

                            <select class="form-control kt_selectpicker" data-live-search="true" id="ClaimsAllocatedTo" name="ClaimsAllocatedTo">
                                    <option value="" selected="selected">Select</option>
                                        @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" >{{ $agent->firstName }} {{ $agent->lastName }}</option>
                                        @endforeach


                            </select>

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Claims Allocated On</label>
                            <div class="col-8">
                                <input type="text" class="form-control kt_datepicker_1_restrict" name="claimsAllocatedOn" value=""  autocomplete="off"  placeholder="Select date">

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Date First Visited</label>
                            <div class="col-8">
                                <input type="text" class="form-control kt_datepicker_1" name="DateFirstVisited" value=""  autocomplete="off"  placeholder="Select date">

                            </div>
                        </div>

                        @if ($claimType != 'THEFT' && $claimType != 'BUSINESSALLRISKS' && $claimType != 'ELECTRONICEQUIPMENT' &&  $claimType != 'PERSONALALLRISKS' && $claimType != 'FIDELITYGUARANTEE' && $claimType != 'DEFECTIVEWORKMANSHIP' &&  $claimType != 'BUSINESSINTERRUPTION' && $claimType != 'LIABILITY' && $claimType != 'GOODSINTRANSIT' && $claimType != 'FIRE' && $claimType != 'WORKERSCOMPENSATION' && $claimType != 'STATEDBENEFITS' && $claimType != 'PROPERTYDAMAGE' && $claimType != 'ACCIDENTALDAMAGE' && $claimType != 'MOBILEELECTRONICDEVICES' && $claimType != 'OFFICECONTENTS' && $claimType != 'GLASS' && $claimType != 'HOUSEHOLDERS' && $claimType != 'HOUSEOWNERS' && $claimType != 'HOUSEOWNER-BUILDINGS' && $claimType != 'HOUSEHOLDERS-CONTENTS')
                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Third party is insured elsewhere ?</label>
                                <div class="col-8">
                                    <select class="form-control kt_selectpicker third_party_insured_elsewhere" data-live-search="true" id="third_party_insured_elsewhere" name="third_party_insured_elsewhere">
                                        <option value="">Please Select</option>
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                            </div>


                            <div class="form-group row" id="tp_insured_elsewhere_email_div" style="display: none;">
                                <label for="example-text-input" class="col-3 col-form-label">Email</label>
                                <div class="col-8">
                                    <input type="text" class="form-control" name="tp_insured_elsewhere_email" placeholder="Enter email">
                                </div>
                            </div>


                            <div class="form-group row">
                                <label for="example-text-input" class="col-3 col-form-label">Driver as the insured</label>
                                <div class="col-8">
                                <select class="form-control kt_selectpicker driver_as_insured" data-live-search="true" id="driver_as_insured" name="driver_as_insured">
                                    <option value="">Please Select</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                                </div>
                            </div>

                            <div id="driver_as_insured_div" style="display: none;">
                                {{--Driver section starts--}}
                                <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin" style="margin-top:20px;">
                                    Driver Details
                                </h3>
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="exampleSelect1">Name</label>
                                            <input type="text" class="form-control"  placeholder="Please enter your full name" value="" name="driver_name" aria-describedby="emailHelp"  >
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="exampleSelect1">Date of Birth</label>
                                            <input type="text" class="form-control  kt_datepicker_1"  name="driver_dob" autocomplete="off" value="">
                                        </div>
                                    </div>

                                </div>
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="exampleSelect1">Mobile Number</label>
                                            <input type="text" class="form-control"  placeholder="Please enter contact number" name="driver_num" value="" aria-describedby="emailHelp" >
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="exampleSelect1">Address</label>
                                            <input type="text" class="form-control"  placeholder="Please enter your address" value="" name="driver_address" aria-describedby="emailHelp" >
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="exampleSelect1">Purpose</label>
                                            <input type="text" class="form-control"  placeholder="Please enter purpose here" value="" name="driver_purpose" aria-describedby="emailHelp" >
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="exampleSelect1">License</label>
                                            <input type="text" class="form-control"  placeholder="Please enter license number" value="" name="driver_license" aria-describedby="emailHelp" >
                                        </div>
                                    </div>
                                </div>
                                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                {{--Driver section ends--}}
                            </div>

                        @endif
                        {{-- <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Claim Approved</label>
                            <div class="col-8">
                            <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="ClaimApproved"    class="form-control condition" value="1">Yes<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="ClaimApproved"  class="form-control  condition"
                                                               value="0">No<span></span>
                                                    </label>

                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Claim Status</label>
                            <div class="col-8">
                            <select class="form-control" id="ClaimStatus" name="ClaimStatus">
                                    <option value="Open">Open</option>
                                    <option value="Close">Close</option>
                                    <option value="Re-Open">Re-Open</option>

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
<script src="{{ asset('assets/app/custom/general/components/forms/widgets/bootstrap-timepicker.js') }}" type="text/javascript"></script>


<script src="{{  asset('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript"></script>
<script src="{{  asset('assets/app/custom/general/components/forms/widgets/bootstrap-select.js') }}" type="text/javascript"></script>

<script src="{{ asset('assets/vendors/general/jquery-validation/dist/jquery.validate.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendors/custom/components/vendors/jquery-validation/init.js') }}" type="text/javascript"></script>

<script src="{{asset('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/app/custom/general/components/forms/layouts/repeater.js')}}" type="text/javascript"></script>


<script>
    {{-- script for vehicle make dropdown ends--}}
       $(document).ready(function()
    {
        var  ajaxRequest;
        $(".addOtherDetails").on("click",function(){
            $('.make').selectpicker("refresh");
        });
    });
    /* script for vehicle make dropdown ends*/

    /*script for vehicle model dropdown starts*/
    $(document).ready(function()
    {
        var ajaxRequest;
        $('#details').on('change','.make', function()
        {
            var make = $(this).val();
            var append = '';
            var $t = $(this);
            ajaxRequest = setTimeout(function(sn)
            {
                $.ajax({
                    url: '{{ route('admin.policy.checkMakeModel') }}',
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "make": make,
                    },
                    type: 'post',
                    datatype : 'json',
                    success: function (data)
                    {
                        if (data)
                        {
                            console.log(data);
                            append += '<div class="form-group">';
                            append += '<label>Model </label>';
                            append += '<select class="form-control kt_selectpicker model" name="model[]" data-live-search="true" title="Please choose model">';
                            $.each(data.count, function(key, modal)
                            {
                                append += '<option value="' + modal + '">' + modal + '</option>';

                            });
                            append += '</select></div>';
                            $t.parent().parent().next().children().replaceWith(append);
                        }

                        else
                        {
                            append += '<h3>No data is available</h3>';
                        }
                    }
                });
            }, 200);
        });
    });

    function isRecoveryInvolved(){
        var isChecked=document.getElementById("recovery_involved").checked;
        if (isChecked)
        {
            $('#recovery_involved_div').delay(100).slideDown(500);
        }
        else
        {
            $('#recovery_involved_div').delay(100).slideUp(500);
        }
    }

    function checkAttorney()
    {
        var attorneyChecked=document.getElementById("attorneyValue").checked;
        if (attorneyChecked)
        {
            $('#attorneyDiv').delay(100).slideDown(500);
        }
        else
        {
            $('#attorneyDiv').delay(100).slideUp(500);
        }

    }

</script>

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

jQuery(document).ready(function()
{
    KTFormControls.init();
    KTBootstrapDatepicker.init();

    // Class initialization on page load
    KTAvatarDemo.init();
    $('#addBeneficiary').click(function ()
    {
        KTBootstrapDatepicker.init();
        $('#addBeneficiaryDiv').toggle();
        $('#addBeneficiary').toggle();
    });
    $(".col-12").on('click', '.kt-repeater__add-data', function()
    {
        KTBootstrapDatepicker.init();
    });
});


function thirdPartyMsg()
    {
        var isChecked=document.getElementById("thirdPartyValue").checked;
        if (isChecked)
        {
            document.getElementById("Msg").innerHTML="Yes";
            document.getElementById("Msg").style.color="cornflowerblue";
            $('#details').delay(100).slideDown(500);
            $('#members_section').delay(100).slideDown(500);
            $('.coverage').rules('add',  { required: true, messages: { required: "Please Select Coverages" } });
        }
        else
        {
            document.getElementById("Msg").innerHTML="No";
            document.getElementById("Msg").style.color="#ff4d4d";
            $('#details').delay(100).slideUp(500);
            $('#members_section').delay(100).slideUp(500);
            $('.coverage').rules('remove',  'required');
        }
    }

    $(document).ready(function (){
            $(document).on('click', '.thirdPartyInsured', function () {
        // var isChecked=document.getElementsByClassName("thirdPartyInsured").checked;
        // if ("input:checkbox[class=thirdPartyInsured]:checked")
            var isChecked=$(this).is(":checked");
            // var thisVal = $(this);
            if (isChecked == true) {
                $(this).parent().find('.tpInsuredMsg').html("Yes");
                $(this).parent().find('.tpInsuredMsg').css('color','cornflowerblue');
                $(this).parent().parent().parent().parent().parent().parent().parent().find('.tpInsuredDetailsDiv').delay(100).slideDown(500);
                // $(this).parent().find('.tpInsuredDetailsDiv').delay(100).slideDown(500);
                // $(this).$(".tpInsuredMsg").html("Yes");
            } else {
                $(this).parent().find('.tpInsuredMsg').html("No");
                $(this).parent().find('.tpInsuredMsg').css('color','#ff4d4d');
                $(this).parent().parent().parent().parent().parent().parent().parent().find('.tpInsuredDetailsDiv').delay(100).slideUp(500);
                // $(this).$(".tpInsuredMsg").html("No");
            }
        });
    });

"use strict";
// Class definition
var KTBootstrapDatepicker = function ()
{
    var arrows;
    if (KTUtil.isRTL())
    {
        arrows = {
            leftArrow: '<i class="la la-angle-right"></i>',
            rightArrow: '<i class="la la-angle-left"></i>'
        }
    }
    else
    {
        arrows = {
            leftArrow: '<i class="la la-angle-left"></i>',
            rightArrow: '<i class="la la-angle-right"></i>'
        }
    }
    // Private functions
    var demos = function ()
    {
        // minimum setup
        $('.kt_datepicker_1').datepicker({
            rtl: KTUtil.isRTL(),
            todayHighlight: true,
            orientation: "bottom left",
            templates: arrows,
            format: 'yyyy-mm-dd'
        });

        $('.child_dob').datepicker({
            rtl: KTUtil.isRTL(),
            todayHighlight: true,
            orientation: "bottom left",
            // templates: arrows,
            format: 'dd-mm-yyyy',
            endDate: "today",
        });
    }
    return {
        // public functions
        init: function() {
            demos();
        }
    };
}();

$(document).ready(function() {
    $('#otherCauseInput').hide();
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
   $('#travel_insurence_ifYes').hide();
   $('.other_insurance_policy').click(function() {
    var Checkval = $(this).val();
       if(Checkval == 'Yes') {
            $('#travel_insurence_ifYes').show();
       } else {
            $('#travel_insurence_ifYes').hide();
       }
   });
});

$(document).ready(function() {
    $('#classStolen').hide();
   $('.propertyStolenDamaged').click(function() {
    var Checkval = $(this).val();
       if(Checkval == '0') {
            $('#classStolen').show();
       } else {
            $('#classStolen').hide();
       }
   });
});


$(document).ready(function() {
    $('#soleOwnerDiv').hide();
   $('.soleOwner').click(function() {
    var Checkval = $(this).val();
       if(Checkval == '0') {
            $('#soleOwnerDiv').show();
       } else {
            $('#soleOwnerDiv').hide();
       }
   });
});

$(document).ready(function() {
    $('#detailsInBriefInput').hide();
   $('.anyone_on_premises').click(function() {
    var Checkval = $(this).val();
       if(Checkval == '1') {
            $('#detailsInBriefInput').show();
       } else {
            $('#detailsInBriefInput').hide();
       }
   });
});

$(document).ready(function() {
    $('#is_carrier_contracted_div').hide();
   $('.is_carrier_contracted').click(function() {
    var Checkval = $(this).val();
       if(Checkval == '1') {
            $('#is_carrier_contracted_div').show();
       } else {
            $('#is_carrier_contracted_div').hide();
       }
   });
});

$(document).ready(function() {
    $('#other_insurance_against_theft_div').hide();
   $('.other_insurance_against_theft').click(function() {
    var Checkval = $(this).val();
       if(Checkval == '1') {
            $('#other_insurance_against_theft_div').show();
       } else {
            $('#other_insurance_against_theft_div').hide();
       }
   });
});

$(document).ready(function() {
    $('#anyone_during_burglary_div').hide();
   $('.anyone_during_burglary').click(function() {
    var Checkval = $(this).val();
       if(Checkval == '1') {
            $('#anyone_during_burglary_div').show();
       } else {
            $('#anyone_during_burglary_div').hide();
       }
   });
});

$(document).ready(function() {
    $('#premises_guarded_by_watchman_div').hide();
   $('.premises_guarded_by_watchman').click(function() {
    var Checkval = $(this).val();
       if(Checkval == '1') {
            $('#premises_guarded_by_watchman_div').show();
       } else {
            $('#premises_guarded_by_watchman_div').hide();
       }
   });
});

$(document).ready(function() {
    $('#suspect_any_person_div').hide();
   $('.suspect_any_person').click(function() {
    var Checkval = $(this).val();
       if(Checkval == '1') {
            $('#suspect_any_person_div').show();
       } else {
            $('#suspect_any_person_div').hide();
       }
   });
});

$(document).ready(function() {
    $('#other_insurance_against_fire_div').hide();
   $('.other_insurance_against_fire').click(function() {
    var Checkval = $(this).val();
       if(Checkval == '1') {
            $('#other_insurance_against_fire_div').show();
       } else {
            $('#other_insurance_against_fire_div').hide();
       }
   });
});

$(document).ready(function() {
    // var radioVal = $('.attorney_radio').val();
    // if(radioVal == '1') {
    //     $('#primary_attorney_assigned').show();
    //     $('#primary_attorney_assigned_date').show();
    // } else {
    //     $('#primary_attorney_assigned').hide();
    //     $('#primary_attorney_assigned_date').hide();
    // }

    $('.attorney_radio').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '1') {
            $('#primary_attorney_assigned').show();
            $('#primary_attorney_assigned_date').show();
        } else {
            $('#primary_attorney_assigned').hide();
            $('#primary_attorney_assigned_date').hide();
        }
    });
});

$(document).ready(function() {
    // var radioVal = $('.co_attorney_involved').val();
    // if(radioVal == '1') {
    //     $('#co_attorney_assigned').show();
    //     $('#co_attorney_assigned_date').show();
    // } else {
    //     $('#co_attorney_assigned').hide();
    //     $('#co_attorney_assigned_date').hide();
    // }

    $('.your_direct_employ').change(function() {
        var selectedValue = $('#your_direct_employ option:selected').val();
        if(selectedValue == '1') {
            $('#your_direct_employ_div').show();
        } else {
            $('#your_direct_employ_div').hide();
        }
    });

    $('.driver_as_insured').change(function() {
        var selectedValue = $('#driver_as_insured option:selected').val();
        if(selectedValue == '0') {
            $('#driver_as_insured_div').show();
        } else {
            $('#driver_as_insured_div').hide();
        }
    });

    $('.third_party_insured_elsewhere').change(function() {
        var selectedValue = $('#third_party_insured_elsewhere option:selected').val();
        if(selectedValue == '1') {
            $('#tp_insured_elsewhere_email_div').show();
        } else {
            $('#tp_insured_elsewhere_email_div').hide();
        }
    });

    $('.Isthismotorclaim').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '1') {
            $('#vehiclePlateDiv').show();
            $('#vehiclePlate').attr('required', true);
        } else {
            $('#vehiclePlateDiv').hide();
            $('#vehiclePlate').removeAttr('required');
        }
    });

    $('.co_attorney_radio').click(function() {
        var Checkval = $(this).val();
        if(Checkval == '1') {
            $('#co_attorney_assigned').show();
            $('#co_attorney_assigned_date').show();
        } else {
            $('#co_attorney_assigned').hide();
            $('#co_attorney_assigned_date').hide();
        }
    });
});
var restrictDate = "{{ config('constants.policy.restrictionDate') }}";

$('.kt_datepicker_1_restrict').datepicker('destroy'); // remove existing init
   $('.kt_datepicker_1_restrict').datepicker({
            todayHighlight: true,
            orientation: "bottom left",
            autoclose: true,
            format: 'yyyy-mm-dd',
           startDate: restrictDate,
        });
     
$('.kt_datepicker_1').datepicker({
                    rtl: KTUtil.isRTL(),
                    todayHighlight: true,
                    orientation: "bottom left",
                    // templates: arrows,
                    format: 'yyyy-mm-dd'
                });
    $('.kt_datepicker_2').datepicker({
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
{{-- <script>
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

            options.forEach(function (option) {
                var opt = document.createElement('option');
                opt.value = option.value;
                opt.textContent = option.text;
                claimSubStatus.appendChild(opt);
            });
        }

        claimStatus.addEventListener('change', updateClaimSubStatus);

        // Initialize on load
        updateClaimSubStatus();
    });
</script> --}}

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

$(document).ready(function() {

$('#is_imported').on('change', function() {
    $("#make_rerate").html('');
    $("#make_rerate").empty();
    $("#make_rerate").selectpicker('refresh');

    $("#model_rerate").html('');
    $("#model_rerate").empty();
    $("#model_rerate").selectpicker('refresh');

    getVehicleMakes(this.value);
});

function getVehicleMakes(e) {
    var baseURL = '{{ env('GRAPHITE_URL') }}';
    if (e == 'Yes') {
        var fetchURL = baseURL + 'api/frontendpay/vehicleMake'
    } else {
        var fetchURL = baseURL + 'api/frontendpay/getTTVehicleMakes'
    }

    $.ajax({
        type: "POST",
        datatype: 'json',
        url: fetchURL,
        dataType: "json",
        beforeSend: function() {
            $("#loader").show();
        },
        success: function(data) {
            $("#loader").hide();
            $("#make_rerate").html('');
            $("#make_rerate").empty();
            $("#make_rerate").append('<option value="">Select vehicle make</option>');
            if (e == 'Yes') {
                $.each(data.makes, function() {
                    $("#make_rerate").append('<option value="' + this.s_Make +
                        '">' + this.s_Make + '</option>')
                });
                console.log(data.makes);
            } else {
                var makes = data.Makes;
                $.each(makes, function(key, val) {
                    var makes = $('<option value="' + val + '">' + val +
                        '</option>');
                    $("#make_rerate").append(makes);
                });
            }
            //$('#year_rerate').prop('selectIndex', 0);
            $('#make_rerate').selectpicker('refresh');
            $('#year_rerate').selectpicker('refresh');
        }
    });
}

$('#year_rerate').on('change', function() {
    var status = $('#is_imported').val();
    var make = $('#make_rerate').val();
    var year = this.value;

    $("#model_rerate").html('');
    $("#model_rerate").empty();
    $("#model_rerate").selectpicker('refresh');
    //document.getElementById("year_rerate").selectedIndex = "0";
    $('#estimatedValue').val('');

    getVehicleModels(status, make, year);
});

$('#make_rerate').on('change', function() {
    $("#model_rerate").html('');
    $("#model_rerate").empty();
    $("#model_rerate").selectpicker('refresh');
    //$("select#year_rerate")[0].selectedIndex = 0;
    $('#estimatedValue').val('');

    var status = $('#is_imported').val();
    var make = this.value;
    var year = $('#year_rerate').val();
    //getVehicleModels(status,make,year);
});

function getVehicleModels(status, make, year) {
    $('#estimatedValue').val('');
    var baseURL = '{{ env('GRAPHITE_URL') }}';
    if (status == 'Yes') {
        var fetchURL = baseURL + 'api/frontendpay/vehicleModel'
    } else {
        var fetchURL = baseURL + 'api/frontendpay/getTTVehicleModels'
    }
    if (status == 'Yes') {
        $.ajax({
            type: "POST",
            datatype: 'json',
            url: fetchURL,
            data: {
                vehicle_make: make,
            },
            dataType: "json",
            beforeSend: function() {
                $("#loader").show();
                $("#model_rerate").empty();
                $('#make_rerate').selectpicker('refresh');
            },
            success: function(responseData) {
                $("#loader").hide();
                $("#model_rerate").html('');
                $("#model_rerate").empty();
                $('#make_rerate').selectpicker('refresh');
                $("#model_rerate").append('<option value="">Select Model</option>');
                $.each(responseData.makes, function() {
                    $option = $('<option value="' + this.s_Variant + '">' + this
                        .s_Variant + '</option>');
                    $("#model_rerate").append($option);
                });
                $("#model_rerate").selectpicker('refresh');
                $('#year_rerate').val(year);
                $("#year_rerate").selectpicker('refresh');
            },
            complete: function() {
                $("#loader").hide();
            }
        });
    } else {
        $.ajax({
            type: "POST",
            datatype: 'json',
            url: fetchURL,
            data: {

                make: make,
                manufacturing_year: year
            },
            dataType: "json",
            beforeSend: function() {
                $("#loader").show();
                $("#model_rerate").empty();
                $("#model_rerate").selectpicker('refresh');
            },
            success: function(responseData) {
                $("#loader").hide();
                $("#model_rerate").html('');
                $("#model_rerate").empty();
                $("#model_rerate").append('<option value="">Select vehicle model</option>');
                $.each(responseData.Models, function() {
                    $option = $('<option value="' + this.Model +
                        '" data-vehicle="' + this.IntroYear +
                        '" data-vehicle-disc="' + this.DisconYear + '" >' + this
                        .Model + '</option>');
                    $("#model_rerate").append($option);
                });
                $("#model_rerate").selectpicker('refresh');
            },
            error: function(data) {
                $option = $('<option value="" disabled>No vehicle found</option>');
                $("#model_rerate").append($option);
            },
            complete: function() {
                $("#loader").hide();
            }
        });
    }
}

$('#model_rerate').on('change', function() {

    $('#estimatedValue').val('');

    var baseURL = '{{ env('GRAPHITE_URL') }}';
    $('#estimatedValue').val('');
    if (this.value && $('#is_imported').val() == 'No') {
        $.ajax({
            type: "POST",
            datatype: 'json',
            url: baseURL + 'api/frontendpay/getTTValue',
            data: {
                vehicleMake: $('#make_rerate').val(),
                vehicleModel: $(this).val(),
                manufacturing_year: $('#year_rerate').val(),
                condition: 'EX',
                mileage: 'LO',
            },
            dataType: "json",
            beforeSend: function() {
                $('#valueLoader').show();
                $('#estimatedValue').hide();
                $('#ratingsCalculation').prop('disabled', true);
            },
            success: function(data) {
                $('#valueLoader').hide();
                $('#estimatedValue').hide();
                if (data.value) {
                    var val = (data.value).toFixed(2)
                    $('#estimatedValue').val(val);
                    $('#ratingsCalculation').prop('disabled', false);
                    if (data.value > 500000) {
                        $('#ratingsCalculation').prop('disabled', true);
                        $("#ratingsCalculation").hide();
                        $("#requestcallback").show();
                    }
                }

            },



            error: function() {
                $('#valueLoader').hide();
                $('#estimatedValue').show();
                $('#estimatedValue').val('');
                $('#ratingsCalculation').prop('disabled', false);
            },
            complete: function() {
                $('#valueLoader').hide();
                $('#ratingsCalculation').prop('disabled', false);
                $('#estimatedValue').show();
            },
        });
    }
});

//$('#year_rerate').prop('value', vehicle_year);
$('#year_rerate').selectpicker('refresh');
});

    function vehicleMsg()
    {
        var isChecked=document.getElementById("vehicleValue").checked;
        if (isChecked)
        {
            document.getElementById("Msg").innerHTML="Yes";
            document.getElementById("Msg").style.color="cornflowerblue";
            $('#sup').delay(100).slideDown(500);
            $('#sup').rules('add',  { required: true, messages: { required: "Please Select Coverages" } });
        }
        else
        {
            document.getElementById("Msg").innerHTML="No";
            document.getElementById("Msg").style.color="#ff4d4d";
            $('#sup').delay(100).slideUp(500);
            $('#sup').rules('remove',  'required');
        }
    }
</script>
@if($claimType == 'GLASS')
        <script>
            "use strict";
            // Class definition
            var KTFormControls = function () {
    // Private functions
                jQuery.validator.addMethod("future", function(value, element) {
                    return this.optional(element) || Date.parse(value) < new Date().getTime(); //use Date.now() if you can instead of getTime, it's nicer
                }, "Please enter only past dates");
                //Vehicle Registration
                jQuery.validator.addMethod("license", function(value, element) {
                    return this.optional( element ) || /^[Bb]{1}\d{3}[a-zA-Z]{3}$/.test( value );
                }, 'Sorry, We only accept Botswana registered vehicles Eg: B123ABC');
                var demo1 = function () {
                    $( "#storeClaim" ).validate({
                        ignore: [],
                        // define validation rules
                        rules: {
                            incidentDate: {
                                required: true,
                                future: true
                            },
                            cause: {
                                required: true
                            },

                        },
                        messages: {
                            incidentDate: {
                                required: "Please enter Date of Damage",
                                future: "Please enter only past dates"
                            },
                            cause: "Please enter Description",
                        },
                        //display error alert on form submit
                        invalidHandler: function(event, validator) {
                            $('html, body').animate({
                                scrollTop: $(validator.errorList[0].element).offset().top - 200
                            }, 1000);
                        },
                        submitHandler: function (form) {
                            form.submit(); // submit the form
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
        </script>
    @endif
</body>
<!-- end::Body -->
</html>
