<div class="kt-portlet" id="claimLabelDiv">
    <div class="kt-portlet__head row">
        <div class="kt-portlet__head-label col-lg-12">
            <div class="col-lg-10">
                <h3 class="kt-portlet__head-title">
                    Claim Details
                </h3>
            </div>

        </div>
    </div>
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
                        <td>{!! $claimAccident->date_of_accident !!}</td>

                    </tr>
                    </tbody>
                </table>
                <br>
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
                        <td>{{ $accidentDriver->dob }}</td>
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
                <br>
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
                @if($claimAccident->third_party == 1)
                    @foreach($thirdparty as $key => $third)
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

                @if($policy->kyc_recipient != 0)
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
                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($recipientKyc->driving_license)}}" width="100%" height="auto" >
                                            @endif
                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                        </div>


                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                            @if($recipientKyc->omang == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                            @else
                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($recipientKyc->omang)}}" width="100%" height="auto" >
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
                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($recipientKyc->passport)}}" width="100%" height="auto" >
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
                @endif
                <div style="border:1px solid lightslategray;" class="kt-separator kt-separator--space-lg kt-separator--border-dashed"></div>

                <h4 style="color: black;font-size: 17px;">Other Information</h4>
                <br>
                <table class="table table-striped m-table">
                    <tbody>
                    <tr>
                        <th>Claim sub-type</th>
                        <td>{!! $claimAccident->claim_sub_type !!}</td>
                        <th>Service Representative</th>
                        <td>{!! $claimAccident->representative !!}</td>

                    </tr>
                    <tr>
                        <th>Recovery involved</th>
                        @if($claimAccident->recovery_involved == "on")
                            <td>Yes</td>
                        @else
                            <td>No</td>
                        @endif
                        <th>Attorney involved</th>
                        @if($claimAccident->attorney_involved == "on")
                            <td>Yes</td>
                        @else
                            <td>No</td>
                        @endif
                    </tr>

                    <tr>
                        <th>Date of loss</th>
                        <td>{!! $claimAccident->incident_date !!}</td>
                        <th>Type of loss</th>
                        <td>{!! $claimAccident->loss_type !!}</td>

                    </tr>

                    <tr>
                        <th>Which Party is at fault ?</th>
                        @if($claimAccident->fault_party == "Owner")
                            <td>Owner</td>
                        @elseif($claimAccident->fault_party == "Other Party")
                            <td>Other Party</td>
                        @else
                            <td>Not Defined</td>
                        @endif
                        <th>Catastrophe loss</th>
                        @if($claimAccident->catastrophe_loss == 1)
                            <td>Yes</td>
                        @else
                            <td>No</td>
                        @endif

                    </tr>
                    <tr>
                        <th>DFS Complaint</th>
                        @if($claimAccident->dfs_complaint == 1)
                            <td>Yes</td>
                        @else
                            <td>No</td>
                        @endif
                        <th>Date first visited</th>
                        <td>{!! $claimAccident->first_visit !!}</td>

                    </tr>
                    <tr>
                        <th>Description of loss</th>
                        <td style="width:40%;">{!! $claimAccident->loss_description !!}</td>
                        <th>Event Name</th>
                        <td>{!! $claimAccident->event_name !!}</td>

                    </tr>
                    <tr>
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
                    </tr>
                    <tr>
                        <th>Assigned Date:</th>
                        <td>{!! $claimAccident->attorney_assigned_date !!}</td>
                        <th>Claim reported by</th>
                        <td>{!! $claimAccident->relation !!}</td>

                    </tr>
                    <tr>
                        <th>Claim Number</th>
                        <td>{!! $claims->claim_number !!}</td>
                        <th></th>
                        <td></td>

                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <div class="kt-portlet__foot kt-portlet__foot--solid">
        <div class="kt-form__actions">
            <div class="row">
                <div class="col-5"></div>

            </div>
        </div>
    </div>
</div>

{{--Second Edit div starts--}}
<form id="claimsUpdate"  action="{{ route('admin.claims.update',$claims->id) }}" method="POST" enctype="multipart/form-data" class="kt-form">
    <input type="hidden" name="_method" value="PUT">
    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
    <div class="kt-portlet" id="claimEditDiv" style="display:none">
        <div class="kt-portlet__head row">
            <div class="kt-portlet__head-label col-lg-12">
                <div class="col-lg-10">
                    <h3 class="kt-portlet__head-title">
                        Claim Details
                    </h3>
                </div>
                <div class="col-lg-2">
                    <a class="btn btn-warning" style="color: black;float:right" onclick="setClaimToView()">Set to view</a>
                </div>
            </div>
        </div>
        <div class="kt-portlet_body"  style="margin-top: 20px;">
            <div class="kt-section">
                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                    <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin" style="padding-top:30px;">
                        Accident Details
                    </h3>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="exampleSelect1">Date of accident</label>
                                <input type="text" class="form-control  kt_datepicker_1"  value="{!! $claimAccident->date_of_accident !!}"  name="date_of_accident" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="exampleSelect1">Place of accident</label>
                                <input type="text" class="form-control"  placeholder="Please enter place" value="{!! $claimAccident->place_of_accident !!}" title="Please enter place of accident" name="place_of_accident" aria-describedby="emailHelp"  >
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="exampleSelect1">Time of accident</label>
                                <input type="text" class="form-control"  placeholder="Please enter time" value="{!! $claimAccident->time_of_accident !!}" name="time_of_accident" aria-describedby="emailHelp" >
                            </div>
                        </div>
                    </div>
                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>




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

                    <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin" style="margin-top:20px;">
                        Passengers Injured
                    </h3>
                    <div class="passenger_injury">
                        @foreach($accidentPassenger as $key => $passenger)
                            <input type="hidden" name="old_passengerinjury[]" value="{!! $passenger->id !!}">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Name</label>
                                        <input type="text" class="form-control" name="old_passengerinjuryName[]"  value="{{ $passenger->name }}" placeholder="Enter injured passenger name">
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Address</label>
                                        <input type="text" class="form-control" name="old_passengerinjuryAddress[]"  value="{{ $passenger->address  }}" placeholder="Enter injured passenger address">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Injury(If Any)</label>
                                        <input type="text" class="form-control" value="{{ $passenger->injury  }}" name="old_passengerinjuryInjury[]"  placeholder="Mention injury of passenger">
                                    </div>
                                </div>
                            </div>
                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        @endforeach
                        <div>
                            <button type="button" id="addPassenger" class="btn btn-brand"><i class="la la-plus"></i>Add Other Details </button>
                        </div>
                    </div>

                    {{-- Passengers reapeater starts--}}
                    <div class="row" id="addPassengerDiv" style="display: none;">
                        <div class="col-lg-12">
                            <div class="kt-repeater">
                                <div class="kt-repeater__data-set">
                                    <div data-repeater-list="passenger_injuries">
                                        <div data-repeater-item class="kt-repeater__item">
                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label for="exampleSelect1">Name</label>
                                                        <input type="text" class="form-control" name="passenger_name"   placeholder="Enter injured passenger name">
                                                    </div>
                                                </div>

                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label for="exampleSelect1">Address</label>
                                                        <input type="text" class="form-control" name="passenger_address"  placeholder="Enter injured passenger address">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label for="exampleSelect1">Injury</label>
                                                        <input type="text" class="form-control" name="passenger_injury"  placeholder="Mention injury of passenger">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="kt-repeater__data form-group">
                                                <span data-repeater-delete="" class="btn btn-warning btn-sm"> <i class="la la-close"></i> Remove </span>
                                            </div>

                                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                        </div>

                                    </div>
                                </div>
                                <div class="kt-repeater__add-data">
                                    <span data-repeater-create="" class="btn btn-info btn-sm"> <i class="la la-plus"></i> Add Other Details </span>
                                </div>

                            </div>
                        </div>
                    </div>


                    {{-- Passengers reapeater ends--}}
                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                    <br>
                    <br>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="exampleSelect1">Is Other party involved</label>
                                <div>
                                <span class="kt-switch" >
                                    <label>
                                        <input id="thirdPartyValue" type="checkbox" name="third_party"  @if($claimAccident->third_party == 1) checked="checked" @endif name="is_vehicle" value="1"
                                               onchange="thirdPartyMsg()">
                                        <span style="margin-top: 10px;margin-left: 10px;"></span>
                                         @if($claimAccident->third_party == 1)
                                            <h4 id="Msg" style="display:inline;float:left;margin-top: 14px;margin-left: 5px;
                                        color:cornflowerblue">Yes</h4>
                                        @else
                                            <h4 id="Msg" style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">No</h4>
                                        @endif
                                    </label>
                                </span>
                                </div>
                            </div>
                        </div>
                    </div>


                    {{--Third party details reapeater starts--}}
                    <div class="col-lg-12" id="details"  @if(!$claimAccident->third_party) style="display: none;" @endif>
                        @foreach($thirdparty as $key => $third)
                            <input type="hidden" name="old_thirdMemberId[]" value="{!! $third->id !!}">
                            <h5 style="color:black;">Other Party Details</h5>
                            <br>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">First Name</label>
                                        <input type="text" class="form-control" name="old_thirdMemberName[]" value="{{ $third->first_name }}"  placeholder="Enter first name">
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Last Name</label>
                                        <input type="text" class="form-control" name="old_thirdMemberlast_Name[]" value="{{ $third->last_name }}"  placeholder="Enter last name">
                                    </div>
                                </div>


                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Mobile Number</label>
                                        <input type="text" class="form-control" name="old_thirdMemberNumber[]" value="{{ $third->cellphone }}" placeholder="Enter mobile number">

                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Address</label>
                                        <input type="text" class="form-control" name="old_thirdMemberAddress[]" value="{{ $third->address }}" placeholder="Enter address">
                                    </div>
                                </div>
                            </div>
                            <br>
                            <h5 style="color:black;">Vehicle Details</h5>
                            <br>

                            <div class="row">

                                <div class="col-lg-6 rowformake">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Make</label>
                                        <input type="text" class="form-control oldmake" name="old_thirdMemberMake[]" value="{{ $third->make }}">
                                    </div>
                                </div>

                                <div class="col-lg-6 rowOfmodel">
                                    <div class="form-group">
                                        <label for="exampleSelect1" class="modelLabel">Model</label>
                                        <input type="text" class="form-control oldmodel" name="old_thirdMemberModel[]" value="{{ $third->model }}">
                                    </div>
                                </div>



                            </div>

                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Vehicle Registration Number</label>
                                        <input type="text" class="form-control" name="old_thirdMemberReg[]" value="{{ $third->registration_no }}" placeholder="Enter vehicle registration number">
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Damage Details </label>
                                        <textarea class="form-control" name="old_thirdMemberDetails[]" cols="20" rows="3">{{ $third->damage_details }}</textarea>
                                    </div>
                                </div>


                            </div>
                            <h5 style="color:black;">Personal Injury</h5>
                            <br>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Name of Injured</label>
                                        <input type="text" class="form-control" name="old_thirdMemberInjured_name[]"  value="{{ $third->injured_name }}" placeholder="Enter injured name">
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Relationship to Injured</label>
                                        <input type="text" class="form-control" name="old_thirdMemberInjured_relationship[]"  value="{{ $third->relationship }}" placeholder="Mention relationship to injured">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Name of Hospital (If applicable)</label>
                                        <input type="text" class="form-control" name="old_thirdMemberInjured_hospital[]"  value="{{ $third->hospital_name }}" placeholder="Mention relationship to injured">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Details of Injured </label>
                                        <textarea class="form-control" name="old_thirdMemberInjured_details[]" cols="20" rows="3">{{ $third->injured_details }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="kt-separator kt-separator--height-sm"></div>

                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        @endforeach
                        <div>
                            <button type="button" id="detailsBtn" class="btn btn-brand"><i class="la la-plus"></i> Add Other Details</button>
                        </div>
                        <br>
                    </div>


                    <div class="col-lg-12" id="detailDiv" style="display: none;">
                        <div class="kt-repeater">
                            <div class="kt-repeater__data-set">
                                <div data-repeater-list="thirdMember">
                                    <div data-repeater-item class="kt-repeater__item">
                                        <h5 style="color:black;">Other Party Details</h5>

                                        <div class="row">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label for="exampleSelect1">First Name</label>
                                                    <input type="text" class="form-control" name="thirdMemberName"   placeholder="Enter first name">
                                                </div>
                                            </div>

                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label for="exampleSelect1">Last Name</label>
                                                    <input type="text" class="form-control" name="thirdMemberlast_Name"   placeholder="Enter last name">
                                                </div>
                                            </div>


                                        </div>
                                        <div class="row">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label for="exampleSelect1">Mobile Number</label>
                                                    <input type="text" class="form-control" name="thirdMemberCellphone"  placeholder="Enter mobile number">
                                                </div>
                                            </div>
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label for="exampleSelect1">Address</label>
                                                    <input type="text" class="form-control" name="thirdMemberAddress"  placeholder="Enter address">
                                                </div>
                                            </div>
                                        </div>

                                        <h5 style="color:black;">Vehicle Details</h5>
                                        <br>
                                        <div class="row">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label for="exampleSelect1">Registration Number</label>
                                                    <input type="text" class="form-control" name="thirdMemberReg"  placeholder="Enter vehicle registration number">
                                                </div>
                                            </div>

                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label for="exampleSelect1">Damage Details </label>
                                                    <textarea class="form-control" name="thirdMemberDetail" cols="20" rows="3"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label class="makelabel">Make</label>
                                                    <select class="form-control make"
                                                            title="Please choose product" data-live-search="true"
                                                            name="thirdMemberMake">
                                                        @foreach($vehicleMakes as $vehicleMake)
                                                            <option value="{{$vehicleMake}}">{{$vehicleMake}}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-lg-6 makerow">

                                            </div>
                                        </div>
                                        <h5 style="color:black;">Personal Injury</h5>
                                        <br>
                                        <div class="row">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label for="exampleSelect1">Name of Injured</label>
                                                    <input type="text" class="form-control" name="thirdMemberInjured_name"   placeholder="Enter injured name">
                                                </div>
                                            </div>

                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label for="exampleSelect1">Relationship to Injured</label>
                                                    <input type="text" class="form-control" name="thirdMemberRelationship"  placeholder="Mention relationship to injured">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label for="exampleSelect1">Name of Hospital (If applicable)</label>
                                                    <input type="text" class="form-control" name="thirdMemberHospital_name"  placeholder="Mention relationship to injured">
                                                </div>
                                            </div>
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label for="exampleSelect1">Details of Injured </label>
                                                    <textarea class="form-control" name="thirdMemberInjured_details" cols="20" rows="3"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="kt-repeater__data form-group">
                                            <span data-repeater-delete="" class="btn btn-warning btn-sm"> <i class="la la-close"></i> Remove </span>
                                        </div>


                                        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                    </div>

                                </div>
                            </div>
                            <div class="kt-repeater__add-data addOtherDetails">
                                <span data-repeater-create="" class="btn btn-info btn-sm"> <i class="la la-plus"></i> Add Other Details </span>
                            </div>
                            <br>
                        </div>
                    </div>
                    {{--Third party details reapeater ends--}}
                </div>
            </div>
        </div>
    </div>

    {{--portet for recipient kyc starts--}}
    <div class="kt-portlet" id="recipientDiv" style="display: none;">
        <div class="kt-portlet__body">
            @if($policy->kyc_recipient == 1)
                @include('admin.claims.recipient_kyc')
            @endif
        </div>
    </div>
    {{--portet for recipient kyc ends--}}

    {{--portet for other information starts--}}
    <div class="kt-portlet" id="otherInfoDiv" style="display: none;">
        <div class="kt-portlet__head">
            <div class="kt-portlet__head-label">
                <h3 class="kt-portlet__head-title">
                    Other Information
                </h3>
            </div>
        </div>
        <div class="kt-portlet__body">

            <div class="row">
                <div class="col-lg-6">
                    <div class="form-group">
                        <label for="exampleSelect1">Claim Subtype:</label>
                        <select class="form-control kt_selectpicker" title="Please select claim subtype" name="claim_sub_type">
                            @foreach($claimSubTypes as $claimSubType)
                                <option value="{{$claimSubType->sub_type}}" @if($claimAccident->claim_sub_type == $claimSubType->sub_type) { selected } @endif>{{$claimSubType->sub_type}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="form-group">
                        <label> Service Representative:</label>
                        <select class="form-control kt_selectpicker" data-live-search="true" title="Please select service representative" name="representative" id="exampleSelect1"    >
                            @foreach($users as $user)
                                <option value="{{$user->firstName}} {{$user->lastName}}" @if( $claimAccident->representative == $user->firstName.' '.$user->lastName) selected @endif >{{$user->firstName}} {{$user->lastName}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>


            </div>
            <div class="row">
                <div class="col-lg-6">
                    <div class="form-group">
                        <label class="kt-checkbox kt-checkbox--brand">
                            <input type="checkbox" name="pa_involved" @if($claimAccident->recovery_involved =="on") checked @endif>
                            Recovery involved<span></span>
                        </label>

                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="form-group">
                        <label class="kt-checkbox kt-checkbox--brand">
                            <input id="attorneyValue" type="checkbox" name="attorney_involved" @if($claimAccident->attorney_involved =="on") checked @endif onchange="checkAttorney()">
                            Attorney involved<span></span>
                        </label>
                    </div>
                </div>
            </div>


            <div class="row" style=" @if($claimAccident->attorney_involved =="on") display:block; @endif" id="attorneyDiv">
                <div class="col-lg-12">
                    <div class="form-group">
                        <label> Attorney List</label>
                        <select class="form-control kt_selectpicker" title="Please select attorney" name="attorney">
                            @foreach($attorneyRole as $a)
                                <option value="{{$a->id}}" @if($a->id == $claims->attorney_id ) selected @endif>{{$a->firstName}} {{$a->lastName}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>


            <div class="row">
                <div class="col-lg-6">
                    <div class="form-group">
                        <label for="exampleSelect1">Date of loss:</label>
                        <input type="text" class="form-control  kt_datepicker_1"  name="incident_date" value="{!!  $claimAccident->incident_date !!}">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="form-group">
                        <label> Type of loss:</label>
                        <select class="form-control kt_selectpicker" title="Please select type of loss" name="loss_type">
                            @foreach($lossTypes as $lossType)
                                <option value="{{$lossType->value}}" @if($claimAccident->loss_type == $lossType->value) selected @endif>{{$lossType->value}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-4" style="margin-top:20px">
                    <div class="form-group">
                        <label>Which Party is at fault ?</label>
                        <div class="kt-radio-inline" style="display: inline;">
                            &nbsp&nbsp&nbsp&nbsp<label class="kt-radio  kt-radio--brand">
                                <input type="radio" value="Owner" name="fault_party" @if($claimAccident->fault_party == "Owner") checked @endif>
                                Owner<span></span>
                            </label>
                            <label class="kt-radio  kt-radio--brand">
                                <input type="radio" value="Other Party" name="fault_party" @if($claimAccident->fault_party == "Other Party") checked @endif>
                                Other Party<span></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4" style="margin-top:20px">
                    <div class="form-group">
                        <label>Catastrophe loss:</label>
                        <div class="kt-radio-inline" style="display: inline;">
                            &nbsp&nbsp&nbsp&nbsp<label class="kt-radio  kt-radio--brand">
                                <input type="radio" value="1" name="catastrophe" @if($claimAccident->catastrophe_loss == 1) checked @endif>
                                Yes<span></span>
                            </label>
                            <label class="kt-radio  kt-radio--brand">
                                <input type="radio" value="0" name="catastrophe" @if($claimAccident->catastrophe_loss == 0) checked @endif>
                                No<span></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4" style="margin-top:20px">
                    <div class="form-group">
                        <label>DFS Complaint:</label>
                        <div class="kt-radio-inline" style="display: inline;">
                            <label class="kt-radio  kt-radio--brand">
                                <input type="radio" value="1" name="dfs_complaint" @if($claimAccident->dfs_complaint == 1) checked @endif>
                                Yes<span></span>
                            </label>
                            <label class="kt-radio  kt-radio--brand">
                                <input type="radio" value="0" name="dfs_complaint" @if($claimAccident->dfs_complaint == 0) checked @endif>
                                No<span></span>
                            </label>
                        </div>
                    </div>
                </div>

            </div>
            <div class="row">
                <div class="col-lg-6">
                    <div class="form-group">
                        <label for="exampleSelect1">Description of loss:</label>
                        <textarea class="form-control" name="loss_description" title="Please provide loss description" >{!! $claimAccident->loss_description !!}</textarea>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="form-group">
                        <label> Event name:</label>
                        <select class="form-control kt_selectpicker" data-live-search="true" title="Please select event name" name="event_name">
                            @foreach($eventNames as $eventName)
                                <option value="{{$eventName->value}}" @if($claimAccident->event_name == $eventName->value){ selected }@endif >{{$eventName->value}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6">
                    <div class="form-group">
                        <label for="exampleSelect1">Primary Attorney Assigned:</label>
                        <div class="kt-radio-inline" style="display: inline;">
                            &nbsp&nbsp&nbsp&nbsp<label class="kt-radio  kt-radio--brand">
                                <input type="radio" value="1" name="primary_attorney" @if($claimAccident->primary_attorney == 1) checked @endif>
                                Yes<span></span>
                            </label>
                            <label class="kt-radio  kt-radio--brand">
                                <input type="radio" value="0" name="primary_attorney" @if($claimAccident->primary_attorney == 0) checked @endif>
                                No<span></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="form-group">
                        <label> Co-Attorney Assigned:</label>
                        {{-- <select class="form-control kt_selectpicker" data-live-search="true" title="Please select Yes/No" name="co_attorney">
                             <option value="1" @if($claimAccident->co_attorney_assigned  == "1") selected @endif>yes</option>
                             <option value="0" @if($claimAccident->co_attorney_assigned  == "0") selected @endif>No</option>
                         </select>--}}
                        <div class="kt-radio-inline" style="display: inline;">
                            &nbsp&nbsp&nbsp&nbsp<label class="kt-radio  kt-radio--brand">
                                <input type="radio" value="1" name="co_attorney" @if($claimAccident->co_attorney_assigned == 1) checked @endif>
                                Yes<span></span>
                            </label>
                            <label class="kt-radio  kt-radio--brand">
                                <input type="radio" value="0" name="co_attorney" @if($claimAccident->co_attorney_assigned == 0) checked @endif>
                                No<span></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6">
                    <div class="form-group">
                        <label>Assigned Date:</label>
                        <input type="text" class="form-control  kt_datepicker_1" value="{!! $claimAccident->co_attorney_assigned_date !!}" placeholder="Please Select Date" name="co_attorney_assigned_date">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="form-group">
                        <label for="exampleSelect1">Claim Reported by:</label>
                        <select class="form-control kt_selectpicker"  title="Please select relation" name="reported_by">
                            @foreach($users as $user)
                                <option value="{{$user->firstName}} {{$user->lastName}}" @if( $claimAccident->relation == $user->firstName.' '.$user->lastName) selected @endif >{{$user->firstName}} {{$user->lastName}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6">
                    <div class="form-group">
                        <label for="exampleSelect1">Claim Number:</label>
                        <input  type="text" class="form-control" name="claim_number" value="{!! $claims->claim_number !!}"  title="claim number is required"  readonly>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="form-group">
                        <label for="exampleSelect1">Date first visited:</label>
                        <input class="form-control kt_datepicker_1"  autocomplete="off" name="first_visit"  value="{!! $claimAccident->first_visit !!}">
                    </div>
                </div>
            </div>


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
                        <a class="btn btn-warning" style="color:black" onclick="setClaimToView()">Set to view</a>
                        <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}" >Cancel</a>
                    </div>
                </div>
            </div>
        </div>

    </div>
    {{--portet for other information ends--}}







</form>
