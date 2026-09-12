<div class="kt-portlet" id="newClaimLabelDiv">
    <div class="kt-portlet__head row">
        <div class="kt-portlet__head-label col-lg-12">
            <div class="col-lg-10">
                <h3 class="kt-portlet__head-title">
                    Claim Details
                </h3>
            </div>
            <div class="col-lg-2">
                @if($claims->status == 'Approved' || $claims->status == 'Rejected' || $claims->status == 'Closed')
                    @can('claim-edit')
                        <button class="btn btn-brand" style="float:right" id="newEditButton" onclick="setNewClaimToEdit()" hidden>Set to edit</button>
                    @endcan
                @elseif($claims->status == 'Pending' || $claims->status == 'Reopen')
                    @can('claim-edit')
                        <button class="btn btn-brand" style="float:right" id="newEditButton" onclick="setNewClaimToEdit()" >Set to edit</button>
                    @endcan
                @endif
            </div>
        </div>
    </div>
    <div class="kt-portlet_body"  style="margin-top: 20px;">
        <div class="kt-section">
            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                {{-- <h4 style="color: black; font-size: 17px;">Accident Details</h4> --}}
                <br>
                <table class="table table-striped m-table">
                    <tbody>
                        <tr>
                            <th>Location</th>
                            @if(isset($riskAddress) && $riskAddress != null && $newclaim->location_id != null)
                                @foreach ($riskAddress as $address)
                                    @if ($address->id == $newclaim->location_id)
                                        <td>{!! $address->address_name  !!}</td>
                                    @endif
                                @endforeach
                            @else
                                <td>-</td>
                            @endif
                            <th>Claim Reported by</th>
                            @foreach($claimReportedBy as $opts)
                                @if ($newclaim->claim_reported_by == $opts->value)
                                    <td>{!! $opts->value  !!}</td>
                                @endif
                            @endforeach
                        </tr>
                        <tr>
                            @if ($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL' || $claims->claim_type == 'Glass' || $claims->claim_type == 'Key Loss')
                                <th>Is this motor claim</th>
                                <td>
                                    @if ($newclaim->is_motor_claim == 1)
                                        Yes
                                    @else
                                        No
                                    @endif
                                </td>
                            @endif
                            @if ($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL' || $claims->claim_type == 'Glass' || $claims->claim_type == 'Key Loss')
                                @if (isset($newclaim->vehicle_plate))
                                    <th>Motor</th>
                                    <td>{!! $newclaim->vehicle_plate !!}</td>
                                @endif
                            @endif
                            {{-- <th>PA Involved</th>
                            <td>
                                @if ($newclaim->pa_involved == 1)
                                    Yes
                                @else
                                    No
                                @endif
                            </td> --}}


                        </tr>
                        <tr>
                            <th>Type of Loss</th>
                            @php
                                $type_of_loss = "-";
                                    if($newclaim->type_of_loss == 1){
                                        $type_of_loss = "Property";
                                    }else if($newclaim->type_of_loss == 2){
                                        $type_of_loss = "Liability";
                                    }
                           @endphp
                            <td>{!! $type_of_loss !!}</td>
                            @if ($claims->claim_type != 'GOODSINTRANSIT' && $claims->claim_type != 'FIRE' && $claims->claim_type != 'GLASS' && $claims->claim_type != 'PROPERTYDAMAGE')
                                <th>Date of Loss</th>
                                <td>{!! $newclaim->date_of_loss !!}</td>
                            @endif
                        </tr>
                        <tr>
                            @if ($claims->claim_type != 'BUSINESSINTERRUPTION')
                                <th>Service Representative</th>
                                @php
                                    $agent = \AlphaDirect\User::where('id',$newclaim->service_representative_id)->first(['firstName','lastName']);
                                        if($agent){
                                            $agent_name = $agent->firstName.' '.$agent->lastName;
                                        }else{
                                            $agent_name = "-";
                                        }
                                @endphp
                                <td>{!! $agent_name !!}</td>
                            @endif
                            <th>Catastrophe Loss</th>
                            <td>
                                @if ($newclaim->catastrophe_loss == 1)
                                    Yes
                                @else
                                    No
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Primary Attorney Involved</th>
                            <td>
                                @if ($newclaim->attorney_involved == 1)
                                    Yes
                                @else
                                    No
                                @endif
                            </td>
                            @if ($newclaim->attorney_involved == 1)
                                <th>Primary Attorney Assigned</th>

                                    @php
                                        $pa = "-";
                                        if($newclaim->primary_attorney_assigned_id == 8413 ){
                                            $pa = "AKHEEL JINABHAI &amp; ASSOCIATES";
                                        }else if($newclaim->primary_attorney_assigned_id == 4468 ){
                                            $pa = "KELOBANG GODISANG ATTORNEYS";
                                        }else if($newclaim->primary_attorney_assigned_id == 6778 ){
                                            $pa = "DESAI LAW GROUP";
                                        }else if($newclaim->primary_attorney_assigned_id == 15249 ){
                                            $pa = "Legal Freedom Insurance Services (Pty) Ltd";
                                        }else if($newclaim->primary_attorney_assigned_id == 38438 ){
                                            $pa = "SALBANY &amp; TORTO ATTORNEYS";
                                        }else if($newclaim->primary_attorney_assigned_id == 38528 ){
                                            $pa = "LAURENCE KHUPE ATTORNEYS";
                                        }else if($newclaim->primary_attorney_assigned_id == 38529 ){
                                            $pa = "MINCHIN &amp; KELLY BOTSWANA";
                                        }else if($newclaim->primary_attorney_assigned_id == 42348 ){
                                            $pa = "COLLECTION AFRICA";
                                        }else if($newclaim->primary_attorney_assigned_id == 43219 ){
                                            $pa = "WOODWARD LEGAL SERVICES";
                                        }else if($newclaim->primary_attorney_assigned_id == 43281 ){
                                            $pa = "RAMALEPA ATTORNEY, NOTARIES &amp; CONVEYANCERS";
                                        }else if($newclaim->primary_attorney_assigned_id == 47318 ){
                                            $pa = "TSHEPHE LEGAL FIRM";
                                        }else if($newclaim->primary_attorney_assigned_id == 47830 ){
                                            $pa = "KOLE LAW PRACTICE";
                                        }else if($newclaim->primary_attorney_assigned_id == 47949 ){
                                            $pa = "JEREMIAH TLADI &amp; CO.";
                                        }
                                    @endphp
                                <td>{!! $pa !!}</td>
                            @endif
                        </tr>
                        <tr>
                            @if ($newclaim->attorney_involved == 1)
                                <th>Primary Attorney Assigned Date</th>
                                <td>{!! $newclaim->p_a_assigned_date !!}</td>
                            @endif
                            <th>Co-Attorney Involved</th>
                            <td>
                                @if ($newclaim->co_attorney_involved == 1)
                                    Yes
                                @else
                                    No
                                @endif
                            </td>
                        </tr>
                        <tr>
                            @if ($newclaim->co_attorney_involved == 1)
                                <th>Co-Attorney Assigned</th>

                                @php
                                $ca = "-";
                                if($newclaim->co_attorney_assigned_id == 15249 ){
                                    $ca = "Legal Freedom Insurance Services (Pty) Ltd";
                                }else if($newclaim->co_attorney_assigned_id == 39989 ){
                                    $ca = "LAERENCE KHUPE ATTORNEYS";
                                }else if($newclaim->co_attorney_assigned_id == 43220 ){
                                    $ca = "WOODWARD LEGAL SERVICES";
                                }else if($newclaim->co_attorney_assigned_id == 43281 ){
                                    $ca = "RAMALEPA ATTORNEY, NOTARIES &amp; CONVEYANCERS";
                                }else if($newclaim->co_attorney_assigned_id == 47318 ){
                                    $ca = "TSHEPHE LEGAL FIRM";
                                    }


                                @endphp
                                <td>{!! $ca !!}</td>
                                <th>Co-Attorney Assigned Date</th>
                                <td>{!! $newclaim->c_a_assigned_date !!}</td>
                            @endif
                        </tr>
                        <tr>
                            <th>DFS Complaint</th>
                            <td>
                                @if ($newclaim->dfs_complaint == 1)
                                    Yes
                                @else
                                    No
                                @endif
                            </td>
                            @if ($claims->claim_type != 'THEFT' && $claims->claim_type != 'MONEY' && $claims->claim_type != 'BUSINESSINTERRUPTION' && $claims->claim_type != 'LIABILITY' && $claims->claim_type != 'GOODSINTRANSIT' && $claims->claim_type != 'FIRE' && $claims->claim_type != 'WORKERSCOMPENSATION' && $claims->claim_type != 'PROPERTYDAMAGE' && $claims->claim_type != 'ACCIDENTALDAMAGE' && $claims->claim_type != 'MOBILEELECTRONICDEVICES' && $claims->claim_type != 'OFFICECONTENTS' && $claims->claim_type != 'Glass' && $claims->claim_type != 'MOTORACCIDENT' && $claims->claim_type != 'HOUSEHOLDERS' && $claims->claim_type != 'HOUSEOWNERS' && $claims->claim_type != 'HOUSEOWNER-BUILDINGS' && $claims->claim_type != 'HOUSEHOLDERS-CONTENTS')
                                <th>Event Name</th>
                                <td>{!! $newclaim->event_name !!}</td>
                            @endif

                        </tr>
                        <tr>
                            @if ($claims->claim_type != 'LIABILITY' && $claims->claim_type != 'GOODSINTRANSIT' && $claims->claim_type != 'FIRE' && $claims->claim_type != 'GLASS')
                                <th>Description of Loss</th>
                            @endif
                            <td>{!! $newclaim->description_of_loss !!}</td>
                            <th>Date First Visited</th>
                            <td>{!! \Carbon\Carbon::parse($newclaim->date_first_visited)->format('Y-m-d') !!}</td>
                        </tr>

                        @if ($claims->claim_type != 'THEFT' && $claims->claim_type != 'MONEY' && $claims->claim_type != 'BUSINESSALLRISKS' && $claims->claim_type != 'ELECTRONICEQUIPMENT' && $claims->claim_type != 'PERSONALALLRISKS' && $claims->claim_type != 'FIDELITYGUARANTEE' && $claims->claim_type != 'DEFECTIVEWORKMANSHIP' && $claims->claim_type != 'GOODSINTRANSIT' && $claims->claim_type != 'WORKERSCOMPENSATION' && $claims->claim_type != 'Glass')
                            <tr>
                                <th>Reported by Broker/Agent</th>
                                @if($agent_broker)
                                    <td>{!! $agent_broker->firstName.' '.$agent_broker->lastName !!}</td>
                                @else
                                    <td>NA</td>
                                @endif

                                {{-- @php
                                $agent_broker = \AlphaDirect\User::where('id',$newclaim->reportedByBrokerAgent)->first(['firstName','lastName']);
                                    if($agent_broker){
                                        $agent_broker = $agent_broker->firstName.' '.$agent_broker->lastName;
                                    }else{
                                        $agent_broker = "-";
                                    }
                                @endphp --}}
                            </tr>
                            @if ($claims->claim_type != 'THEFT' && $claims->claim_type != 'MONEY' && $claims->claim_type != 'BUSINESSALLRISKS' && $claims->claim_type != 'ELECTRONICEQUIPMENT' && $claims->claim_type != 'PERSONALALLRISKS' && $claims->claim_type != 'FIDELITYGUARANTEE' && $claims->claim_type != 'DEFECTIVEWORKMANSHIP' && $claims->claim_type != 'BUSINESSINTERRUPTION' && $claims->claim_type != 'LIABILITY' && $claims->claim_type != 'GOODSINTRANSIT' && $claims->claim_type != 'FIRE' && $claims->claim_type != 'WORKERSCOMPENSATION' && $claims->claim_type != 'PROPERTYDAMAGE' && $claims->claim_type != 'ACCIDENTALDAMAGE' && $claims->claim_type != 'MOBILEELECTRONICDEVICES' && $claims->claim_type != 'OFFICECONTENTS' && $claims->claim_type != 'Glass' && $claims->claim_type != 'HOUSEHOLDERS' && $claims->claim_type != 'HOUSEOWNERS' && $claims->claim_type != 'HOUSEOWNER-BUILDINGS' && $claims->claim_type != 'HOUSEHOLDERS-CONTENTS')
                                <tr>
                                    <th>Third party is insured elsewhere ?</th>
                                    <td>
                                        @if ($newclaim->third_party_insured_elsewhere == 1)
                                            Yes
                                        @else
                                            No
                                        @endif
                                    </td>
                                    @if ($newclaim->third_party_insured_elsewhere == 1)
                                        <th>Insurer's Email</th>
                                        <td>{!! $newclaim->tp_insured_elsewhere_email !!}</td>
                                    @endif
                                </tr>
                                <tr>
                                    <th>Driver as the insured</th>
                                    <td>
                                        @if ($newclaim->driver_as_insured == 1)
                                            Yes
                                        @else
                                            No
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endif
                    </tbody>
                </table>
                @if ($claims->claim_type != 'THEFT' && $claims->claim_type != 'MONEY' && $claims->claim_type != 'BUSINESSALLRISKS' && $claims->claim_type != 'ELECTRONICEQUIPMENT' && $claims->claim_type != 'PERSONALALLRISKS' && $claims->claim_type != 'FIDELITYGUARANTEE' && $claims->claim_type != 'DEFECTIVEWORKMANSHIP' && $claims->claim_type != 'BUSINESSINTERRUPTION' && $claims->claim_type != 'LIABILITY' && $claims->claim_type != 'GOODSINTRANSIT' && $claims->claim_type != 'FIRE' && $claims->claim_type != 'WORKERSCOMPENSATION' && $claims->claim_type != 'PROPERTYDAMAGE' && $claims->claim_type != 'ACCIDENTALDAMAGE' && $claims->claim_type != 'MOBILEELECTRONICDEVICES' && $claims->claim_type != 'OFFICECONTENTS' && $claims->claim_type != 'Glass' && $claims->claim_type != 'HOUSEHOLDERS' && $claims->claim_type != 'HOUSEOWNERS' && $claims->claim_type != 'HOUSEOWNER-BUILDINGS' && $claims->claim_type != 'HOUSEHOLDERS-CONTENTS')
                    @if ($newclaim->driver_as_insured == 0 && isset($accidentDriver))
                    <div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div>

                    <h4 style="color: black;font-size: 17px;">Driver Details</h4>
                        <br>
                        <table class="table table-striped m-table">
                            <tbody>
                            <tr>
                                <th>Name</th>
                                <td>{{ $accidentDriver->name }}</td>
                                <th>Mobile Number</th>
                                <td>{{ $accidentDriver->cellphone }}</td>
                            </tr>
                            <tr>
                                <th>Date of Birth</th>
                                <td>{{ \Carbon\Carbon::parse($accidentDriver->dob)->format('d-m-Y')  }}</td>
                                <th>Address</th>
                                <td style="width:40%;">{{ $accidentDriver->address }}</td>
                            </tr>
                            <tr>
                                <th>License</th>
                                <td>{{ $accidentDriver->license }}</td>
                                <th>Purpose</th>
                                <td style="width:40%;">{{ $accidentDriver->purpose }}</td>
                            </tr>
                            </tbody>
                        </table>
                    @endif
                @endif
                <br>
                <div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div>

                <h4 style="color: black;font-size: 17px;">Other Information</h4>
                <br>
                    @if ($claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS')
                        @include('admin.claims.newClaims.types.view_all_risk')
                    @elseif ($claims->claim_type == 'BUSINESSINTERRUPTION')
                        @include('admin.claims.newClaims.types.view_bussness_interruption')
                    @elseif ($claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY')
                        @include('admin.claims.newClaims.types.view_burglary')
                    @elseif ($claims->claim_type == 'WORKERSCOMPENSATION')
                        @include('admin.claims.newClaims.types.view_workers_compensation')
                    @elseif ($claims->claim_type == 'DEFECTIVEWORKMANSHIP')
                        @include('admin.claims.newClaims.types.view_defective_workmanship')
                    @elseif ($claims->claim_type == 'FIDELITYGUARANTEE')
                        @include('admin.claims.newClaims.types.view_fidelity_guarantee')
                    @elseif ($claims->claim_type == 'TRAVELINSURANCE')
                        @include('admin.claims.newClaims.types.view_travel_insurance')
                    @elseif ($claims->claim_type == 'GOODSINTRANSIT')
                        @include('admin.claims.newClaims.types.view_goods_in_transit')
                    @elseif ($claims->claim_type == 'FIRE')
                        @include('admin.claims.newClaims.types.view_fire')
                    @elseif ($claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'HOUSEHOLDERS' || $claims->claim_type == 'HOUSEOWNERS' || $claims->claim_type == 'HOUSEOWNER-BUILDINGS' || $claims->claim_type == 'HOUSEHOLDERS-CONTENTS')
                        @include('admin.claims.newClaims.types.view_property_loss_damage')
                    @elseif ($claims->claim_type == 'LIABILITY')
                        @include('admin.claims.newClaims.types.view_public_liability')
                    @elseif ($claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS')
                        @include('admin.claims.newClaims.types.view_mobileAndElectronicDevices')
                    @elseif ($claims->claim_type == 'Glass')
                        @include('admin.claims.newClaims.types.view_glass')
                    @elseif ($claims->claim_type == 'Accident')
                        @include('admin.claims.newClaims.types.view_accident')
                    @elseif ($claims->claim_type == 'Key Loss')
                        @include('admin.claims.newClaims.types.view_key_loss')
                    @endif
            </div>
        </div>
    </div>


    <div class="kt-portlet__foot kt-portlet__foot--solid">
        <div class="kt-form__actions">
            <div class="row">
                <div class="col-5"></div>
                <div class="col-7">

                    @if($claims->status == 'Rejected' || $claims->status == 'Approved' || $claims->status == 'Closed')
                        <button class="btn btn-brand"  id="newEditButton" onclick="setNewClaimToEdit()" hidden>Set to edit</button>


                    @endif
                    @if($claims->status == 'Pending')
                        <button class="btn btn-brand"  id="newEditButton" onclick="setNewClaimToEdit()">Set to edit</button>

                    @endif
                    <a class="btn btn-secondary" href="{{ route('admin.claims.index') }}" >Cancel</a>
                </div>
            </div>
        </div>
    </div>
</div>

{{--Second Edit div starts--}}
<form id="claimsUpdate"  action="{{ route('admin.claims.update',$claims->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">
    <input type="hidden" name="_method" value="PUT">
    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
    <input type="hidden" name="newclaim_id" value="{{ $newclaim->id }}" />
    <div class="kt-portlet" id="newClaimEditDiv" style="display:none">
        <div class="kt-portlet__head row">
            <div class="kt-portlet__head-label col-lg-12">
                <div class="col-lg-10">
                    <h3 class="kt-portlet__head-title">
                        Claim Details
                    </h3>
                </div>
                <div class="col-lg-2">
                    <a class="btn btn-warning" style="color: black;float:right" onclick="setNewClaimToView()">Set to view</a>
                </div>
            </div>
        </div>
        <div class="kt-portlet_body"  style="margin-top: 20px;">
            <div class="kt-section">
                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="example-text-input">Select Location</label>
                                <select class="form-control kt_selectpicker" data-live-search="true" id="Location" name="Location">
                                    @foreach($riskAddress as $address)
                                        <option value="{{ $address->id }}" {{ $address->id == $newclaim->location_id ? 'selected' : '' }}>{{ $address->address_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @if($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL' || $claims->claim_type == 'Glass' || $claims->claim_type == 'Key Loss')
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="example-text-input">Is this motor claim</label>
                                    <label class="kt-radio" style="margin-left: 10px;">
                                        <input type="radio" name="Isthismotorclaim"  {{ $newclaim->is_motor_claim == 1 ? 'checked' : '' }}  class="form-control Isthismotorclaim required" value="1">Yes<span></span>
                                    </label>
                                    <label class="kt-radio" style="margin-left: 10px;">
                                        <input type="radio" name="Isthismotorclaim"  {{ $newclaim->is_motor_claim == 0 ? 'checked' : '' }} class="form-control  Isthismotorclaim required"
                                                value="0">No<span></span>
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="row">
                        @if($claims->claim_type == 'Accident' || $claims->claim_type == 'MOTORTRADERSEXTERNAL' || $claims->claim_type == 'MOTORTRADERSINTERNAL' || $claims->claim_type == 'Glass' || $claims->claim_type == 'Key Loss')
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="example-text-input">Select Motor</label>
                                <select class="form-control kt_selectpicker" data-live-search="true" id="vehiclePlate" name="vehiclePlate">
                                    <option value="">Please Select</option>
                                    @if(isset($vehiclePlateNos) && $vehiclePlateNos != null)
                                        @foreach ($vehiclePlateNos as $vehiclePlate)
                                            <option value="{{ $vehiclePlate->registration_no }}" {{ $vehiclePlate->registration_no == $newclaim->vehiclePlate ? 'selected' : '' }}>{{ $vehiclePlate->registration_no }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>
                        @endif
                        @if ($claims->claim_type != 'THEFT' && $claims->claim_type != 'MONEY' && $claims->claim_type != 'BUSINESSINTERRUPTION' && $claims->claim_type != 'LIABILITY' && $claims->claim_type != 'GOODSINTRANSIT' && $claims->claim_type != 'FIRE' && $claims->claim_type != 'WORKERSCOMPENSATION' && $claims->claim_type != 'PROPERTYDAMAGE' && $claims->claim_type != 'ACCIDENTALDAMAGE' && $claims->claim_type != 'MOBILEELECTRONICDEVICES' && $claims->claim_type != 'OFFICECONTENTS' && $claims->claim_type != 'Glass' && $claims->claim_type != 'MOTORACCIDENT' && $claims->claim_type != 'HOUSEHOLDERS' && $claims->claim_type != 'HOUSEOWNERS' && $claims->claim_type != 'HOUSEOWNER-BUILDINGS' && $claims->claim_type != 'HOUSEHOLDERS-CONTENTS')
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="example-text-input">Event Name</label>
                                    <input type="text" class="form-control" name="EventName" value="{{ $newclaim->event_name }}"  placeholder="Enter event name">
                                </div>
                            </div>
                        @endif
                        {{-- <div class="col-lg-6">
                            <div class="form-group">
                                <label for="example-text-input">PA Involved</label>
                                <label class="kt-radio" style="margin-left: 10px;">
                                    <input type="radio" name="PAInvolved" {{ $newclaim->pa_involved == 1 ? 'checked' : '' }}  class="form-control condition" value="1">Yes<span></span>
                                </label>
                                <label class="kt-radio" style="margin-left: 10px;">
                                    <input type="radio" name="PAInvolved" {{ $newclaim->pa_involved == 0 ? 'checked' : '' }}   class="form-control  condition"
                                            value="0">No<span></span>
                                </label>
                            </div>
                        </div> --}}
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="example-text-input">Claim Reported by</label>
                                <select class="form-control kt_selectpicker" data-live-search="true" id="ClaimReportedby" name="ClaimReportedby">
                                    <option value="" {{ $newclaim->claim_reported_by == "" ? 'selected' : '' }}>Select Relation</option>
                                    @foreach($claimReportedBy as $opts)
                                        <option value="{{$opts->value}}" @if( $newclaim->claim_reported_by == $opts->value) selected @endif >{{$opts->value}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @if ($claims->claim_type != 'LIABILITY' && $claims->claim_type != 'GOODSINTRANSIT' && $claims->claim_type != 'FIRE' && $claims->claim_type != 'GLASS')
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="example-text-input">Description of Loss</label>
                                    <textarea type="text" class="form-control" name="description_of_loss" value=""  placeholder="Description of Loss.">{{ $newclaim->description_of_loss }}</textarea>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="example-text-input">Type of Loss</label>
                                <select class="form-control kt_selectpicker" data-live-search="true" id="TypeofLoss" name="TypeofLoss">
                                    <option value="" {{ $newclaim->type_of_loss == "" ? 'selected' : '' }}>Select</option>
                                    <option value="1" {{ $newclaim->type_of_loss == "1" ? 'selected' : '' }}>Property</option>
                                    <option value="2" {{ $newclaim->type_of_loss == "2" ? 'selected' : '' }}>Liability</option>
                                </select>
                            </div>
                        </div>
                        @if ($claims->claim_type != 'GOODSINTRANSIT' && $claims->claim_type != 'FIRE' && $claims->claim_type != 'GLASS' && $claims->claim_type != 'PROPERTYDAMAGE')
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="example-text-input">Date of Loss</label>
                                    <input type="text" class="form-control kt_datepicker_1" name="DateofLoss" value="{{ $newclaim->date_of_loss }}" autocomplete="off"  placeholder="Select date" >
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="row">
                        @if ($claims->claim_type != 'BUSINESSINTERRUPTION')
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="example-text-input">Service Representative</label>
                                    <select class="form-control kt_selectpicker" data-live-search="true" id="ServiceRepresentative" name="ServiceRepresentative">
                                    <option value="" selected="selected">Select</option>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" {{ $newclaim->service_representative_id == $agent->id ? 'selected' : '' }}>{{ $agent->firstName }} {{ $agent->lastName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="example-text-input">Catastrophe Loss</label>
                                <select class="form-control condition kt_selectpicker" data-live-search="true" id="CatastropheLoss" name="CatastropheLoss">
                                    <option value="" selected="selected">Select</option>
                                        <option value="1" {{ $newclaim->catastrophe_loss == 1 ? 'selected' : '' }}>Yes</option>
                                        <option value="0" {{ $newclaim->catastrophe_loss == 0 ? 'selected' : '' }}>No</option>
                                    </select>
                                {{-- <label class="kt-radio" style="margin-left: 10px;">
                                    <input type="radio" name="CatastropheLoss"   {{ $newclaim->catastrophe_loss == 1 ? 'checked' : '' }}    class="form-control condition" value="1">Yes<span></span>
                                </label>
                                <label class="kt-radio" style="margin-left: 10px;">
                                    <input type="radio" name="CatastropheLoss"   {{ $newclaim->catastrophe_loss == 0 ? 'checked' : '' }}   class="form-control  condition"
                                            value="0">No<span></span>
                                </label> --}}
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="example-text-input">Primary Attorney Involved</label>
                                <label class="kt-radio" style="margin-left: 10px;">
                                    <input type="radio" name="AttorneyInvolved"  {{ $newclaim->attorney_involved == 1 ? 'checked' : '' }}   class="form-control attorney_radio" value="1">Yes<span></span>
                                </label>
                                <label class="kt-radio" style="margin-left: 10px;">
                                    <input type="radio" name="AttorneyInvolved" {{ $newclaim->attorney_involved == 0 ? 'checked' : '' }}   class="form-control  attorney_radio"
                                            value="0">No<span></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-lg-6" id="primary_attorney_assigned" >
                            <div class="form-group">
                                <label for="example-text-input">Primary Attorney Assigned</label>
                                <select class="form-control kt_selectpicker" data-live-search="true" id="PrimaryAttorneyAssigned" name="PrimaryAttorneyAssigned">
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
                    </div>

                    <div class="row" id="primary_attorney_assigned_date" >
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="example-text-input">Primary Attorney Assigned Date</label>
                                <input type="text" class="form-control kt_datepicker_1" name="p_a_AssignedDate" value="{{ $newclaim->p_a_assigned_date }}"  autocomplete="off"  placeholder="Select date">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="example-text-input">Co-Attorney Involved</label>
                                <label class="kt-radio" style="margin-left: 10px;">
                                    <input type="radio" name="co_attorney_involved"  {{ $newclaim->co_attorney_involved == 1 ? 'checked' : '' }}   class="form-control co_attorney_radio" value="1">Yes<span></span>
                                </label>
                                <label class="kt-radio" style="margin-left: 10px;">
                                    <input type="radio" name="co_attorney_involved" {{ $newclaim->co_attorney_involved == 0 ? 'checked' : '' }}   class="form-control  co_attorney_radio"
                                            value="0">No<span></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-lg-6" id="co_attorney_assigned" >
                            <div class="form-group">
                                <label for="example-text-input">Co-Attorney Assigned</label>
                                <select class="form-control kt_selectpicker" data-live-search="true" id="CoAttorneyAssigned" name="CoAttorneyAssigned">
                                    <option value="" {{ $newclaim->co_attorney_assigned_id == "" ? 'selected' : '' }}>Select</option>
                                    <option value="15249" {{ $newclaim->co_attorney_assigned_id == "15249" ? 'selected' : '' }}>Legal Freedom Insurance Services (Pty) Ltd</option>
                                    <option value="39989" {{ $newclaim->co_attorney_assigned_id == "39989" ? 'selected' : '' }}>LAERENCE KHUPE ATTORNEYS</option>
                                    <option value="43220" {{ $newclaim->co_attorney_assigned_id == "43220" ? 'selected' : '' }}>WOODWARD LEGAL SERVICES</option>
                                    <option value="43281" {{ $newclaim->co_attorney_assigned_id == "43281" ? 'selected' : '' }}>RAMALEPA ATTORNEY, NOTARIES &amp; CONVEYANCERS</option>
                                    <option value="47318" {{ $newclaim->co_attorney_assigned_id == "47318" ? 'selected' : '' }}>TSHEPHE LEGAL FIRM</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6" id="co_attorney_assigned_date" >
                            <div class="form-group">
                                <label for="example-text-input">Co-Attorney Assigned Date</label>
                                <input type="text" class="form-control kt_datepicker_1" name="c_a_AssignedDate" value="{{  $newclaim->c_a_assigned_date }}"  autocomplete="off"  placeholder="Select date">
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="example-text-input">DFS Complaint</label>
                                <label class="kt-radio" style="margin-left: 10px;">
                                    <input type="radio" name="DFSComplaint"  {{ $newclaim->dfs_complaint == 1 ? 'checked' : '' }}  class="form-control condition" value="1">Yes<span></span>
                                </label>
                                <label class="kt-radio" style="margin-left: 10px;">
                                    <input type="radio" name="DFSComplaint" {{ $newclaim->dfs_complaint == 0 ? 'checked' : '' }}  class="form-control  condition"
                                            value="0">No<span></span>
                                </label>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="example-text-input">Date First Visited</label>
                                <input type="text" class="form-control kt_datepicker_1" name="DateFirstVisited" value="{{ $newclaim->date_first_visited }}"  autocomplete="off"  placeholder="Select date">
                            </div>
                        </div>
                        @if ($claims->claim_type != 'THEFT' && $claims->claim_type != 'MONEY' && $claims->claim_type != 'BUSINESSALLRISKS' && $claims->claim_type != 'ELECTRONICEQUIPMENT' && $claims->claim_type != 'PERSONALALLRISKS' && $claims->claim_type != 'FIDELITYGUARANTEE' && $claims->claim_type != 'DEFECTIVEWORKMANSHIP' && $claims->claim_type != 'GOODSINTRANSIT' && $claims->claim_type != 'WORKERSCOMPENSATION' && $claims->claim_type != 'Glass')
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="example-text-input">Reported by Broker/Agent</label>
                                    <select class="form-control kt_selectpicker" data-live-search="true" id="reportedByBrokerAgent" name="reportedByBrokerAgent">
                                        <option value="" selected="selected">Select Broker/Agent</option>
                                        {{-- @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" {{ $newclaim->reportedByBrokerAgent == $agent->id ? 'selected' : '' }}>{{ $agent->firstName }} {{ $agent->lastName }}</option>
                                        @endforeach --}}
                                        @foreach($agents_options as $option)
                                            <option
                                                value="{{ $option['id'] }}"
                                                {{ $option['id'] == $selectedReportedBy ? 'selected' : '' }}>
                                                {{ $option['name'] }} ({{ $option['type'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if ($claims->claim_type != 'THEFT' && $claims->claim_type != 'MONEY' && $claims->claim_type != 'BUSINESSALLRISKS' && $claims->claim_type != 'ELECTRONICEQUIPMENT' && $claims->claim_type != 'PERSONALALLRISKS' && $claims->claim_type != 'FIDELITYGUARANTEE' && $claims->claim_type != 'DEFECTIVEWORKMANSHIP' && $claims->claim_type != 'BUSINESSINTERRUPTION' && $claims->claim_type != 'LIABILITY' && $claims->claim_type != 'GOODSINTRANSIT' && $claims->claim_type != 'FIRE' && $claims->claim_type != 'WORKERSCOMPENSATION' && $claims->claim_type != 'PROPERTYDAMAGE' && $claims->claim_type != 'ACCIDENTALDAMAGE' && $claims->claim_type != 'MOBILEELECTRONICDEVICES' && $claims->claim_type != 'OFFICECONTENTS' && $claims->claim_type != 'Glass' && $claims->claim_type != 'HOUSEHOLDERS' && $claims->claim_type != 'HOUSEOWNERS' && $claims->claim_type != 'HOUSEOWNER-BUILDINGS' && $claims->claim_type != 'HOUSEHOLDERS-CONTENTS')
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="example-text-input">Third party is insured elsewhere ?</label>
                                    <select class="form-control kt_selectpicker third_party_insured_elsewhere" data-live-search="true" id="third_party_insured_elsewhere" name="third_party_insured_elsewhere">
                                        <option value="">Please Select</option>
                                        <option value="1" {{ $newclaim->third_party_insured_elsewhere == 1 ? 'selected' : '' }}>Yes</option>
                                        <option value="0" {{ $newclaim->third_party_insured_elsewhere == 0 ? 'selected' : '' }}>No</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-6" id="tp_insured_elsewhere_email_div" style="display: none;">
                                <div class="form-group">
                                    <label for="example-text-input">Email</label>
                                    <input type="email" class="form-control" name="tp_insured_elsewhere_email" value="{{$newclaim->tp_insured_elsewhere_email}}"  placeholder="Enter email">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="example-text-input" class="col-3 col-form-label">Driver as the insured</label>
                                    <select class="form-control kt_selectpicker driver_as_insured" data-live-search="true" id="driver_as_insured" name="driver_as_insured">
                                        <option value="">Please Select</option>
                                        <option value="1" {{ $newclaim->driver_as_insured == 1 ? 'selected' : '' }}>Yes</option>
                                        <option value="0" {{ $newclaim->driver_as_insured == 0 ? 'selected' : '' }}>No</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

                        @if (isset($accidentDriver))

                        <div id="driver_as_insured_div" style="display: none;">
                            {{--Driver section starts--}}
                            <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin" style="margin-top:20px;">
                                Driver Details
                            </h3>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Name</label>
                                        <input type="text" class="form-control"  placeholder="Please enter your full name" value="{{ $accidentDriver->name }}" name="driver_name" aria-describedby="emailHelp"  >
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Date of Birth</label>
                                        <input type="text" class="form-control  kt_datepicker_1"  name="driver_dob" autocomplete="off" value="{{ $accidentDriver->dob }}">
                                    </div>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Mobile Number</label>
                                        <input type="text" class="form-control"  placeholder="Please enter contact number" name="driver_num" value="{{ $accidentDriver->cellphone }}" aria-describedby="emailHelp" >
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Address</label>
                                        <input type="text" class="form-control"  placeholder="Please enter your address" value="{{ $accidentDriver->address }}" name="driver_address" aria-describedby="emailHelp" >
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Purpose</label>
                                        <input type="text" class="form-control"  placeholder="Please enter purpose here" value="{{ $accidentDriver->purpose }}" name="driver_purpose" aria-describedby="emailHelp" >
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">License</label>
                                        <input type="text" class="form-control"  placeholder="Please enter license number" value="{{ $accidentDriver->license }}" name="driver_license" aria-describedby="emailHelp" >
                                    </div>
                                </div>
                            </div>
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                            {{--Driver section ends--}}
                        </div>
                        @endif
                    @endif
                    <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin" style="padding-top:30px;">
                        Other Information
                    </h3>
                    @if ($claims->claim_type == 'BUSINESSALLRISKS' || $claims->claim_type == 'ELECTRONICEQUIPMENT' || $claims->claim_type == 'PERSONALALLRISKS')
                        @include('admin.claims.newClaims.types.edit_all_risk')
                    @elseif ($claims->claim_type == 'BUSINESSINTERRUPTION')
                        @include('admin.claims.newClaims.types.edit_bussness_interruption')
                    @elseif ($claims->claim_type == 'THEFT' || $claims->claim_type == 'MONEY')
                        @include('admin.claims.newClaims.types.edit_burglary')
                    @elseif ($claims->claim_type == 'WORKERSCOMPENSATION')
                        @include('admin.claims.newClaims.types.edit_workers_compensation')
                    @elseif ($claims->claim_type == 'DEFECTIVEWORKMANSHIP')
                        @include('admin.claims.newClaims.types.edit_defective_workmanship')
                    @elseif ($claims->claim_type == 'FIDELITYGUARANTEE')
                        @include('admin.claims.newClaims.types.edit_fidelity_guarantee')
                    @elseif ($claims->claim_type == 'TRAVELINSURANCE')
                        @include('admin.claims.newClaims.types.edit_travel_insurance')
                    @elseif ($claims->claim_type == 'GOODSINTRANSIT')
                        @include('admin.claims.newClaims.types.edit_goods_in_transit')
                    @elseif ($claims->claim_type == 'FIRE')
                        @include('admin.claims.newClaims.types.edit_fire')
                    @elseif ($claims->claim_type == 'PROPERTYDAMAGE' || $claims->claim_type == 'ACCIDENTALDAMAGE' || $claims->claim_type == 'HOUSEHOLDERS' || $claims->claim_type == 'HOUSEOWNERS' || $claims->claim_type == 'HOUSEOWNER-BUILDINGS' || $claims->claim_type == 'HOUSEHOLDERS-CONTENTS')
                        @include('admin.claims.newClaims.types.edit_property_loss_damage')
                    @elseif ($claims->claim_type == 'LIABILITY')
                        @include('admin.claims.newClaims.types.edit_public_liability')
                    @elseif ($claims->claim_type == 'MOBILEELECTRONICDEVICES' || $claims->claim_type == 'OFFICECONTENTS')
                        @include('admin.claims.newClaims.types.edit_mobileAndElectronicDevices')
                    @elseif ($claims->claim_type == 'Glass')
                        @include('admin.claims.newClaims.types.edit_glass')
                    @elseif ($claims->claim_type == 'Accident')
                        @include('admin.claims.newClaims.types.edit_accident')
                    @elseif ($claims->claim_type == 'Key Loss')
                        @include('admin.claims.newClaims.types.edit_key_loss')
                    @endif
                </div>

                <div class="kt-portlet__foot kt-portlet__foot--solid">
                    <div class="kt-form__actions">
                        <div class="row">
                            <div class="col-5"></div>
                            <div class="col-7">

                                <button type="submit" value="Submit" class="btn btn-brand">Update Claim</button>
                                {{--id="sbtBtn"--}}
                                {{--
                                                        @can('claim-edit')
                                                        <button type="submit" value="Submit" id="sbtBtn" class="btn btn-brand">Update Claim</button>
                                                        @endcan

                                                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                                --}}
                                <a class="btn btn-warning" style="color:black" onclick="setNewClaimToView()">Set to view</a>
                                <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}" >Cancel</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
