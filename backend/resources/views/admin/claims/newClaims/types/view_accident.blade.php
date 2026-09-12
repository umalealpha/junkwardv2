<div class="kt-portlet" id="claimLabelDiv">
    <table class="table table-striped m-table">
        <tbody>
        <tr>
            <th>Claim sub-type</th>
            <td>{!! $claimAccident->claim_sub_type !!}</td>
            {{-- <th>Service Representative</th>
            <td>{!! $claimAccident->representative !!}</td> --}}
            <th>Recovery involved</th>
            @if($claimAccident->recovery_involved == "on")
                <td>Yes</td>
            @else
                <td>No</td>
            @endif
        </tr>
        <tr>
            <th>Attorney involved</th>
            @if($claimAccident->attorney_involved == "on")
                <td>Yes</td>
            @else
                <td>No</td>
            @endif
            {{-- <th>Date of loss</th>
            <td>{!! $claimAccident->incident_date !!}</td> --}}
            <th>Date Of Claim Registered</th>
            @if ($claims->registered_claim)
                <td>{!!  \Carbon\Carbon::parse($claims->registered_claim)->format('d-m-Y')  !!}</td>
            @else
                <td>N/A</td>
            @endif
        </tr>
        <tr>
            <th>Type of loss</th>
            <td>{!! $claimAccident->loss_type !!}</td>
            <th>Which Party is at fault ?</th>
            @if($claimAccident->fault_party == "Owner")
                <td>Owner</td>
            @elseif($claimAccident->fault_party == "Other Party")
                <td>Other Party</td>
            @else
                <td>Not Defined</td>
            @endif
            {{-- <th>Catastrophe loss</th>
            @if($claimAccident->catastrophe_loss == 1)
                <td>Yes</td>
            @else
                <td>No</td>
            @endif --}}

        </tr>
        {{-- <tr>
            <th>DFS Complaint</th>
            @if($claimAccident->dfs_complaint == 1)
                <td>Yes</td>
            @else
                <td>No</td>
            @endif
            <th>Date first visited</th>
            <td>{!! \Carbon\Carbon::parse($claimAccident->first_visit)->format('d-m-Y')  !!}</td>

        </tr> --}}
        <tr>
            {{-- <th>Description of loss</th>
            <td style="width:40%;">{!! $claimAccident->loss_description !!}</td> --}}
            <th>Weather Condition</th>
            <td>{!! $claimAccident->event_name !!}</td>
            <th>Claim Number</th>
            <td>{!! $claims->claim_number !!}</td>
        </tr>
        {{-- <tr>
            <th>Primary Attorney Assigned:</th>
            @if($claimAccident->primary_attorney_assigned == 1)
                <td>Yes</td>
            @else
                <td>No</td>
            @endif
            <th>Co-Attorney Assigned:</th>
            @if($claimAccident->co_attorney_assigned == 1)
                <td>Yes</td>
            @else
                <td>No</td>
            @endif
        </tr> --}}
        {{-- <tr>
            <th>Assigned Date:</th>
            <td>{!! \Carbon\Carbon::parse($claimAccident->attorney_assigned_date)->format('d-m-Y')  !!}</td>
            <th>Claim reported by</th>
            <td>{!! $claimAccident->relation !!}</td>

        </tr> --}}
        {{-- <tr>
            <th>Claim Number</th>
            <td>{!! $claims->claim_number !!}</td>
            <th></th>
            <td></td>

        </tr> --}}
        </tbody>
    </table>
    <br>
    <div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div>

    <div class="kt-portlet_body"  style="margin-top: 20px;">
        <div class="kt-section">
            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                <h4 style="color: black; font-size: 17px;">Accident Details</h4>
                <br>
                <table class="table table-striped m-table">
                    <tbody>
                    <tr>
                        <th>Place of accident</th>
                        <td>{!! $claimAccident->place_of_accident !!}</td>
                        <th>Time of accident</th>
                        <td>{!! $claimAccident->time_of_accident !!}</td>
                    </tr>
                    <tr>
                        <th>Date of accident</th>
                        <td>{{ \Carbon\Carbon::parse($claimAccident->date_of_accident)->format('d-m-Y')  }}</td>

                    </tr>
                    </tbody>
                </table>
                <br>
                {{-- <div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div>

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
                <br> --}}
                <div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div>

                <h4 style="color: black;font-size: 17px;">Passenger Injured</h4>
                <br>
                <table class="table table-striped m-table">
                    <tbody>
                    @foreach($accidentPassenger as $key => $passenger)
                        <tr>
                            <th>Name</th>
                            <td>{{ $passenger->name }}</td>
                            <th>Address</th>
                            <td style="width:40%;">{{ $passenger->address }}</td>
                        </tr>
                        <tr>
                            <th>Injury (If Any)</th>
                            <td style="width:40%;">{{ $passenger->injury }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <br>
                <div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div>

                <p style="font-size:15px;font-weight: bold;color: black; padding-left: 15px;">Is Other Party involved</p>
                @if($claimAccident->third_party == 1)
                    <p style="margin-top: -3%; margin-left: 20%; font-size: 15px;">Yes</p>
                @else
                    <p style="margin-top: -3%; margin-left: 20%; font-size: 15px;">No</p>
                @endif
                <br>
                <br>
                <p style="font-size:15px;font-weight: bold;color: black; padding-left: 15px;">Is third party insured</p>
                @if($claimAccident->third_party_insured == 1)
                    <p style="margin-top: -3%; margin-left: 20%; font-size: 15px;">Yes</p>
                @else
                    <p style="margin-top: -3%; margin-left: 20%; font-size: 15px;">No</p>
                @endif
                <br>
                {{-- <br> --}}
                @if($claimAccident->third_party == 1)
                    @foreach($thirdparty as $key => $third)
                        @if($claimAccident->third_party_insured == 1)
                            <h4 style="color: black;font-size: 17px;">Third Party Insured Details</h4>
                            <br>
                            <table class="table table-striped m-table">
                                <tbody>
                                    <tr>
                                        <th>First Name</th>
                                        <td>{{ isset($otherPartyInsured[$key]->first_name_insured) }}</td>
                                        <th>Last Name</th>
                                        <td>{{ isset($otherPartyInsured[$key]->last_name_insured) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Cellphone</th>
                                        <td>{{ isset($otherPartyInsured[$key]->cellphone_insured) }}</td>
                                        <th>Email</th>
                                        <td>{{ isset($otherPartyInsured[$key]->email_insured) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Address</th>
                                        <td>{{ isset($otherPartyInsured[$key]->address_insured) }}</td>
                                        <th></th>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        @endif
                        <h4 style="color: black;font-size: 17px;">Other Party Details</h4>
                        <br>
                        <table class="table table-striped m-table">
                            <tbody>
                            <tr>
                                <th>First Name</th>
                                <td>{{ $third->first_name }}</td>
                                <th>Last Name</th>
                                <td>{{ $third->last_name }}</td>
                            </tr>
                            <tr>
                                <th>Cellphone</th>
                                <td>{{ $third->cellphone }}</td>
                                <th>Address</th>
                                <td style="width:40%;">{{ $third->address }}</td>
                            </tr>
                            </tbody>
                        </table>
                        <br>
                        <h4 style="color: black;font-size: 17px;">Vehicle Details</h4>
                        <br>
                        <table class="table table-striped m-table">
                            <tbody>
                            <tr>
                                <th>Make</th>
                                <td style="width:40%;">{{ $third->make }}</td>
                                <th>Model </th>
                                <td style="width:40%;">{{ $third->model }}</td>
                            </tr>
                            <tr>
                                <th>Registration Number</th>
                                <td style="width:40%;">{{ $third->registration_no }}</td>
                                <th>Damage Details</th>
                                <td style="width:40%;">{{ $third->damage_details }}</td>
                            </tr>
                            </tbody>
                        </table>
                        <br>
                        <h4 style="color: black;font-size: 17px;">Personal Injury</h4>
                        <br>
                        <table class="table table-striped m-table">
                            <tbody>
                            <tr>
                                <th>Name of Injured </th>
                                <td>{{ $third->injured_name }}</td>
                                <th>Relationship to Injured</th>
                                <td>{{ $third->relationship }}</td>
                            </tr>
                            <tr>
                                <th>Name of Hospital (If applicable)</th>
                                <td style="width:40%;">{{ $third->hospital_name }}</td>
                                <th>Details of Injured</th>
                                <td style="width:40%;">{{ $third->injured_details }}</td>
                            </tr>
                            </tbody>
                        </table>
                        <br>
                    @endforeach
                @endif

                <br>
                <div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div>

                {{-- @if($policy->kyc_recipient != 0)
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">
                                Recipient KYC
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet_body">
                        <div class="kt-section">
                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                <table class="table table-striped m-table">
                                    <tbody>
                                    <div class="form-group row">
                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                            @if($recipientKyc->driving_license == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                            @else
                                                <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->driving_license) }}" width="100%" height="auto" >
                                            @endif
                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                        </div>


                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                            @if($recipientKyc->omang == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                            @else
                                                <img src="{{ \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->omang) }}" width="100%" height="auto" >
                                            @endif
                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                        </div>

                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                            @if($recipientKyc->proof_residence == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                            @else
                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($recipientKyc->proof_residence)}}" width="100%" height="auto" >
                                            @endif
                                            <div class="kt-avatar" style="float: left; clear: left;">
                                            </div>
                                        </div>

                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Proof of Income</h3>
                                            @if($recipientKyc->proof_income == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                            @else
                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($recipientKyc->proof_income)}}" width="100%" height="auto" >
                                            @endif
                                            <div class="kt-avatar" style="float: left; clear: left;">
                                            </div>
                                        </div>

                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Passport</h3>
                                            @if($recipientKyc->passport == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                            @else
                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($recipientKyc->passport) }}" width="100%" height="auto" >
                                            @endif
                                            <div class="kt-avatar" style="float: left; clear: left;">
                                            </div>
                                        </div>
                                    </div>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif --}}
                {{-- <div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div> --}}

                @if($claimAccident->recovery_involved == "on" && $ClaimRecoveryDetails != null)
                    <h4 style="color: black;font-size: 17px;">Recovery Involved Details</h4>
                    <br>
                    <table class="table table-striped m-table">
                        <tbody>
                            <tr>
                                <th>Name of third party</th>
                                <td>{!! $ClaimRecoveryDetails->recovery_name !!}</td>
                                <th>Address of third party</th>
                                <td>{!! $ClaimRecoveryDetails->recovery_address !!}</td>

                            </tr>
                            <tr>
                                <th>Phone number of third party</th>
                                <td>{!! $ClaimRecoveryDetails->recovery_phone !!}</td>
                                <th>Email address of third party</th>
                                <td>{!! $ClaimRecoveryDetails->recovery_email !!}</td>
                            </tr>

                            <tr>
                                <th>Place of employment of third party</th>
                                <td>{!! $ClaimRecoveryDetails->recovery_place_employment !!}</td>
                                <th>Work phone of third party</th>
                                <td>{!! $ClaimRecoveryDetails->recovery_work_phone !!}</td>

                            </tr>
                        </tbody>
                    </table>

                    <div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div>
                @endif
            </div>
        </div>
    </div>


    {{-- <div class="kt-portlet__foot kt-portlet__foot--solid">
        <div class="kt-form__actions">
            <div class="row">
                <div class="col-5"></div>
                <div class="col-7">

                    @if($claims->status == 'Rejected' || $claims->status == 'Approved' || $claims->status == 'Closed')
                        <button class="btn btn-brand"  id="editButton" onclick="setClaimToEdit()" hidden>Set to edit</button>


                    @endif
                    @if($claims->status == 'Pending')
                        <button class="btn btn-brand"  id="editButton" onclick="setClaimToEdit()">Set to edit</button>

                    @endif
                    <a class="btn btn-secondary" href="{{ route('admin.claims.index') }}" >Cancel</a>
                </div>
            </div>
        </div>
    </div> --}}
</div>

