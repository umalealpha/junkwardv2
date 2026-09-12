<div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
    <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin" style="margin-top:20px;">
        Accident Details
    </h3>
    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Date of accident</label>
                <input type="text" class="form-control  kt_datepicker_1"   placeholder="Please select date" name="date_of_accident" autocomplete="off">
            </div>
        </div>
        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Place of accident</label>
                <input type="text" class="form-control"  placeholder="Please enter place" name="place_of_accident" title="Please enter location of accident" aria-describedby="emailHelp">
            </div>
        </div>
    </div>

    <div class="row">

            <div class="col-lg-6">
                <div class="form-group">
                    <label for="exampleSelect1">Time of accident</label>
                    <div class="input-group timepicker">
                        <div class="input-group-prepend">
                            <span class="input-group-text"> <i class="la la-clock-o"></i> </span>
                        </div>
                        <input type="text" class="form-control"  id="kt_timepicker_4_modal" name="time_of_accident" >

                    </div>
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
                <input type="text" class="form-control"  placeholder="Please enter your full name" name="driver_name" aria-describedby="emailHelp"  >
            </div>
        </div>
        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Date of Birth</label>
                <input type="text" class="form-control  kt_datepicker_1"  name="driver_dob" autocomplete="off">
            </div>
        </div>

    </div>
    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Mobile Number</label>
                <input type="text" class="form-control"  placeholder="Please enter contact number" name="driver_num" aria-describedby="emailHelp" >
            </div>
        </div>
        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Address</label>
                <input type="text" class="form-control"  placeholder="Please enter your address" name="driver_address" aria-describedby="emailHelp" >
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Purpose</label>
                <input type="text" class="form-control"  placeholder="Please enter purpose here" name="driver_purpose" aria-describedby="emailHelp" >
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">License</label>
                <input type="text" class="form-control"  placeholder="Please enter license number" name="driver_license" aria-describedby="emailHelp" >
            </div>
        </div>
    </div>
    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
    {{--Driver section ends--}}

    {{-- Passengers reapeater starts--}}
    <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin" style="margin-top:20px;">
        Passengers Injured
    </h3>
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

    {{-- Passengers reapeater ends--}}
    <br>
    <br>
    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
    <div class="row">

        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Is other party involved</label>
                <div>
                <span class="kt-switch" >
                    <label>
                        <input id="thirdPartyValue" type="checkbox" name="third_party" value="1"
                               onchange="thirdPartyMsg()">
                        <span style="margin-top: 10px;margin-left: 10px;"></span>
                        <h4 id="Msg" style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">No</h4>
                    </label>
                </span>
                </div>
            </div>
        </div>

        {{--Third party details reapeater starts--}}
        <div class="col-lg-12" id="details" style="display: none;">
            <div class="kt-repeater">
                <div class="kt-repeater__data-set">
                    <div data-repeater-list="other_details">
                        <div data-repeater-item class="kt-repeater__item">
                        <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Is third party insured</label>
                                        <div>
                                        <span class="kt-switch" >
                                            <label>
                                                <input class="thirdPartyInsured" type="checkbox" name="third_party_insured" value="1"
                                                       >
                                                <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                <h4 class="tpInsuredMsg" style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">No</h4>
                                            </label>
                                        </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <br>
                            {{--Third party Insured details reapeater starts--}}
                                <div class="col-lg-12 tpInsuredDetailsDiv" style="display: none;">
                                    {{-- <div class="kt-repeater">
                                        <div class="kt-repeater__data-set">
                                            <div data-repeater-list="thirdparty_insured_details">
                                                <div data-repeater-item class="kt-repeater__item"> --}}

                                                    <h5 style="color:black;">Third Party Insured Details</h5>
                                                    <br>
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label for="exampleSelect1">First Name</label>
                                                                <input type="text" class="form-control" name="first_name_insured"   placeholder="Enter First name">
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label for="exampleSelect1">Last Name</label>
                                                                <input type="text" class="form-control" name="last_name_insured"   placeholder="Enter Last name">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label for="exampleSelect1">Mobile Number</label>
                                                                <input type="text" class="form-control" name="cellphone_insured"  placeholder="Enter mobile number">
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label for="exampleSelect1">Email</label>
                                                                <input type="text" class="form-control" name="email_insured"  placeholder="Enter email">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <br>
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label for="exampleSelect1">Address</label>
                                                                <textarea class="form-control" name="address_insured" cols="20" rows="3"></textarea>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- <div class="kt-repeater__data form-group">
                                                        <span data-repeater-delete="" class="btn btn-warning btn-sm"> <i class="la la-close"></i> Remove </span>
                                                    </div>
                                                    <div class="kt-separator kt-separator--height-sm"></div>
                                                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kt-repeater__add-data">
                                            <span data-repeater-create="" class="btn btn-info btn-sm"> <i class="la la-plus"></i> Add Other Details </span>
                                        </div>
                                    </div> --}}
                                </div>
                            {{--Third party Insured details reapeater ends--}}
                            <br>
                            <h5 style="color:black;">Other Party Details</h5>
                            <br>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">First Name</label>
                                        <input type="text" class="form-control" name="first_name"   placeholder="Enter First name">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Last Name</label>
                                        <input type="text" class="form-control" name="last_name"   placeholder="Enter Last name">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Mobile Number</label>
                                        <input type="text" class="form-control" name="cellphone"  placeholder="Enter mobile number">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Address</label>
                                        <input type="text" class="form-control" name="address"  placeholder="Enter address">
                                    </div>
                                </div>
                            </div>

                            <h5 style="color:black;">Vehicle Details</h5>
                            <br>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Registration Number</label>
                                        <input type="text" class="form-control" name="plate"  placeholder="Enter vehicle registration number">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Damage Details </label>
                                        <textarea class="form-control" name="damage_details" cols="20" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">

                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label class="makelabel">Make</label>
                                        <select class="form-control  make "
                                                title="Please choose product" data-live-search="true"
                                                name="make">
                                            @foreach($vehicleMakes as $vehicleMake)
                                                <option value="{{$vehicleMake}}">{{$vehicleMake}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-6 makerow">
                                    <div class="form-group">
                                        <label class="makelabel">Model</label>
                                        <select class="form-control kt_selectpicker oldmodel" title="Please choose model" data-live-search="true">
                                        </select>
                                    </div>
                                    {{--<div id="dataSpinner" style="display:contents;" class="form-group kt-spinner kt-spinner--sm kt-spinner--success kt-spinner--left kt-spinner--input"></div>--}}
                                </div>

                            </div>
                            <h5 style="color:black;">Personal Injury</h5>
                            <br>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Name of Injured</label>
                                        <input type="text" class="form-control" name="injured_name"   placeholder="Enter injured name">
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Relationship to Injured</label>
                                        <input type="text" class="form-control" name="relationship"  placeholder="Mention relationship to injured">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Name of Hospital (If applicable)</label>
                                        <input type="text" class="form-control" name="hospital_name"  placeholder="Mention relationship to injured">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Details of Injured </label>
                                        <textarea class="form-control" name="injured_details" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="kt-repeater__data form-group">
                                <span data-repeater-delete="" class="btn btn-warning btn-sm"> <i class="la la-close"></i> Remove </span>
                            </div>
                            <div class="kt-separator kt-separator--height-sm"></div>

                            <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                        </div>

                    </div>
                </div>
                <div class="kt-repeater__add-data">
                    <span data-repeater-create="" class="btn btn-info btn-sm"> <i class="la la-plus"></i> Add Other Details </span>
                </div>

            </div>
        </div>
        {{--Third party details reapeater ends--}}

    </div>
    <br>
    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

    <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin" style="margin-top:20px;">
        Other Information
    </h3>
    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Claim Subtype:</label>
                <select class="form-control kt_selectpicker" title="Please select claim subtype" name="claim_sub_type">
                    $claimSubTypes
                    @foreach($claimSubTypes as $claimSubType)
                        <option value="{{$claimSubType->sub_type}}">{{$claimSubType->sub_type}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        {{-- <div class="col-lg-6">
            <div class="form-group">
                <label> Service Representative:</label>
                <select class="form-control kt_selectpicker" data-live-search="true" title="Please select service representative" name="representative" id="exampleSelect1"    >
                    @foreach($attorney as $a)
                        <option value="{{$a->firstName}} {{$a->lastName}}">{{$a->firstName}} {{$a->lastName}}</option>
                    @endforeach
                </select>
            </div>
        </div> --}}

    </div>
    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label class="kt-checkbox kt-checkbox--brand">
                    <input type="checkbox" name="pa_involved" id="recovery_involved" onchange="isRecoveryInvolved()">
                    Recovery involved<span></span>
                </label>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label class="kt-checkbox kt-checkbox--brand">
                    <input id="attorneyValue" type="checkbox" name="attorney_involved" onchange="checkAttorney()">
                    Attorney involved<span></span>
                </label>
            </div>
        </div>
    </div>

    {{--Recovery Involved section starts--}}
    <div id="recovery_involved_div" style="display: none">
        <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin" style="margin-top:20px;">
            Recovery Details
        </h3>
        <div class="row">
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="exampleSelect1">Name of third party</label>
                    <input type="text" class="form-control"  placeholder="Please enter your name" name="recovery_name" aria-describedby="emailHelp"  >
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="exampleSelect1">Address of third party</label>
                    <input type="text" class="form-control"  placeholder="Please enter your address" name="recovery_address" aria-describedby="emailHelp" >
                </div>
            </div>

        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="exampleSelect1">Phone number of third party</label>
                    <input type="text" class="form-control"  placeholder="Please enter contact number" name="recovery_phone" aria-describedby="emailHelp" >
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="exampleSelect1">Email address of third party</label>
                    <input type="text" class="form-control"  placeholder="Please enter your email" name="recovery_email" aria-describedby="emailHelp" >
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="form-group">
                    <label for="exampleSelect1">Place of employment of third party</label>
                    <input type="text" class="form-control"  placeholder="Please enter place of employment" name="recovery_place_employment" aria-describedby="emailHelp" >
                </div>
            </div>

            <div class="col-lg-6">
                <div class="form-group">
                    <label for="exampleSelect1">Work phone of third party</label>
                    <input type="text" class="form-control"  placeholder="Please enter work phone" name="recovery_work_phone" aria-describedby="emailHelp" >
                </div>
            </div>
        </div>
        <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
    </div>
    {{--Recovery Involved section ends--}}

    <div class="row" style="display:none;" id="attorneyDiv">
        <div class="col-lg-12">
            <div class="form-group">
                <label> Attorney List</label>
                <select class="form-control kt_selectpicker" title="Please select attorney" name="attorney">
                    @foreach($attorneyRole as $a)
                            <option value="{{$a->id}}">{{$a->firstName}} {{$a->lastName}}</option>
                        @endforeach
                </select>
            </div>
        </div>
    </div>


    <div class="row">
        {{-- <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Date of loss:</label>
                <input type="text" class="form-control  kt_datepicker_1"  autocomplete="off" name="incident_date">
            </div>
        </div> --}}
        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Date of claim registered</label>
                <input type="text" class="form-control  kt_datepicker_1"   placeholder="Please select date" name="registered_claim" autocomplete="off">
            </div>
        </div>
        <div class="col-lg-6">
            <div class="form-group">
                <label> Type of loss:</label>
                <select class="form-control kt_selectpicker" title="Please select type of loss" name="loss_type">
                    @foreach($lossTypes as $lossType)
                        <option value="{{$lossType->value}}">{{$lossType->value}}</option>
                    @endforeach
                </select>
            </div>
        </div>

    </div>
    <div class="row">
        {{-- <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Description of loss:</label>
                <textarea class="form-control" name="loss_description" title="Please provide loss description" ></textarea>
            </div>
        </div> --}}
        <div class="col-lg-6">
            <div class="form-group">
                <label> Weather Condition:</label>
                <select class="form-control kt_selectpicker" data-live-search="true" title="Please select event name" name="event_name">
                    @foreach($eventNames as $eventName)
                        <option value="{{$eventName->value}}">{{$eventName->value}}</option>
                    @endforeach
                </select>
            </div>
        </div>

    </div>
    <div class="row">
        <div class="col-lg-6" style="margin-top:20px">
            <div class="form-group">
                <label>Which Party is at fault ?</label>
                <div class="kt-radio-inline" style="display: inline;">
                    &nbsp&nbsp&nbsp&nbsp<label class="kt-radio  kt-radio--brand">
                        <input type="radio" value="Owner" name="fault_party">
                        Owner<span></span>
                    </label>
                    <label class="kt-radio  kt-radio--brand">
                        <input type="radio" value="Other Party" name="fault_party" >
                        Other Party<span></span>
                    </label>
                </div>
            </div>
        </div>
        {{-- <div class="col-lg-6" style="margin-top:20px">
            <div class="form-group">
                <label>Catastrophe loss:</label>
                <div class="kt-radio-inline" style="display: inline;">
                    &nbsp&nbsp&nbsp&nbsp<label class="kt-radio  kt-radio--brand">
                        <input type="radio" value="1" name="catastrophe">
                        Yes<span></span>
                    </label>
                    <label class="kt-radio  kt-radio--brand">
                        <input type="radio" value="0" name="catastrophe">
                        No<span></span>
                    </label>
                </div>
            </div>
        </div> --}}
    </div>

    <div class="row">
        {{-- <div class="col-lg-6" style="margin-top:20px">
            <div class="form-group">
                <label>Primary Attorney Assigned:</label>
                <div class="kt-radio-inline" style="display: inline;">
                    &nbsp&nbsp&nbsp&nbsp<label class="kt-radio  kt-radio--brand">
                        <input type="radio" value="1" name="primary_attorney">
                        Yes<span></span>
                    </label>
                    <label class="kt-radio  kt-radio--brand">
                        <input type="radio" value="0" name="primary_attorney">
                        No<span></span>
                    </label>
                </div>
            </div>
        </div> --}}
        {{-- <div class="col-lg-6" style="margin-top:20px">
            <div class="form-group">
                <label>Co-Attorney Assigned:</label>
                <div class="kt-radio-inline" style="display: inline;">
                    &nbsp&nbsp&nbsp&nbsp<label class="kt-radio  kt-radio--brand">
                        <input type="radio" value="1" name="co_attorney">
                        Yes<span></span>
                    </label>
                    <label class="kt-radio  kt-radio--brand">
                        <input type="radio" value="0" name="co_attorney">
                        No<span></span>
                    </label>
                </div>
            </div>
        </div> --}}
    </div>
    <div class="row">
        {{-- <div class="col-lg-6" style="margin-top:20px">
            <div class="form-group">
                <label>DFS Complaint:</label>
                <div class="kt-radio-inline" style="display: inline;">
                    &nbsp&nbsp&nbsp&nbsp<label class="kt-radio  kt-radio--brand">
                        <input type="radio" value="1" name="dfs_complaint">
                        Yes<span></span>
                    </label>
                    <label class="kt-radio  kt-radio--brand">
                        <input type="radio" value="0" name="dfs_complaint">
                        No<span></span>
                    </label>
                </div>
            </div>
        </div> --}}

        {{-- <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Assigned Date:</label>
                <input type="text" class="form-control  kt_datepicker_1"  autocomplete="off" placeholder="Please Select Date" name="attorney_assigned_date">
            </div>
        </div> --}}


    </div>
    <div class="row">


    </div>
    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
    <br>
    <br>
    {{-- <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Claim allocated to:</label>
                <select class="form-control kt_selectpicker" data-live-search="true" title="Please select claim allocated to" name="claim_allocated_to"     >
                    @foreach($attorney as $a)
                        <option value="{{$a->firstName}} {{$a->lastName}}">{{$a->firstName}} {{$a->lastName}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="form-group">
                <label>Claim allocated on:</label>
                <input class="form-control kt_datepicker_1"  autocomplete="off" placeholder="Please Select Date" name="claim_allocated_on">
            </div>
        </div>
    </div> --}}
    <div class="row">
        <div class="col-lg-12">
            <div class="form-group">
                <label for="exampleSelect1">Coverage:</label>
                <select class="form-control kt_selectpicker" data-live-search="true" title="Please select coverages" name="coverage">
                    @if(count($coverages)>0)
                        @foreach($coverages as $item)
                            <option value="{{$item->id}}:{{$item->name}}">{{$item->name}}</option>
                        @endforeach
                    @endif
                </select>
            </div>
        </div>
    </div>
    {{-- <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Total Reserve amount:</label>
                <input type="text" class="form-control"  title="Please enter the amount" name="reserve_amount" autocomplete="off" id="reserve_amount" aria-describedby="emailHelp"  placeholder="P0.00"   >
                <input type="hidden" value="{{ $policy->id }}" id="policyId">
                <p style="display:none; color:red;" id="reserveMsg"></p>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Total Paid amount:</label>
                <input type="text" class="form-control"  title="Please enter the amount" name="paid_amount" autocomplete="off" id="paid_amount" aria-describedby="emailHelp"  placeholder="P0.00"   >
                <p style="display:none; color:red;" id="paidmsg"></p>
            </div>
        </div>
    </div> --}}

    {{--<div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1" style="padding-right: 15px">Claim Status:</label>
                <select class="form-control kt_selectpicker reasonDiv" data-live-search="true" title="Please select claim status" name="claim_status">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1" style="padding-right: 15px">Claim Sub-status:</label>
                <select class="form-control kt_selectpicker" data-live-search="true" title="Please select claim sub status" name="claim_sub_status">
                    <option value="1">Active</option>
                    <option value="0">In-active</option>
                </select>
            </div>
        </div>
    </div>--}}


    {{-- <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Date first visited:</label>
                <input class="form-control kt_datepicker_1" autocomplete="off" name="first_visit" placeholder="Please Select Date">
            </div>
        </div>

        <div class="col-lg-6">
            <div class="form-group">
                <label for="exampleSelect1">Claim Reported by:</label>
                <select class="form-control kt_selectpicker"  title="Please select relation" name="reported_by">
                    @foreach($reportedByOpts as $opts)
                        <option value="{{ $opts->value }}">{{ $opts->value }}</option>
                    @endforeach
                </select>
            </div>
        </div>

    </div> --}}
</div>
