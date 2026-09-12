    <div class="kt-portlet" id="claimEditDiv">
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
                                <div class="input-group timepicker">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"> <i class="la la-clock-o"></i> </span>
                                    </div>

                                    <input type="text" class="form-control"  id="kt_timepicker_4_modal"  value="{!! $claimAccident->time_of_accident !!}"
                                           name="time_of_accident" aria-describedby="emailHelp" >
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
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="exampleSelect1">Is third party insured</label>
                                        <div>
                                        <span class="kt-switch" >
                                            <label>
                                                <input class="thirdPartyInsured" type="checkbox" name="old_third_party_insured[{{$key}}]" value="1"
                                                @if($claimAccident->third_party_insured == 1) checked="checked" @endif >
                                                <span style="margin-top: 10px;margin-left: 10px;"></span>
                                                @if($claimAccident->third_party_insured == 1)
                                                    <h4 id="Msg" style="display:inline;float:left;margin-top: 14px;margin-left: 5px;
                                                    color:cornflowerblue">Yes</h4>
                                                @else
                                                    <h4 id="Msg" style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">No</h4>
                                                @endif
                                                {{-- <h4 class="tpInsuredMsg" style="display:inline;float:left;margin-top: 14px;margin-left: 5px;color:#ff4d4d;">No</h4> --}}
                                            </label>
                                        </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <br>
                            {{-- @foreach($otherPartyInsured as $keys => $tpInsured) --}}
                            <input type="hidden" name="old_tp_insured_id[{{$key}}]" value="{!! isset($otherPartyInsured[$key]->id) !!}">
                            {{--Third party Insured details reapeater starts--}}
                                <div class="col-lg-12 tpInsuredDetailsDiv" @if(!$claimAccident->third_party_insured) style="display: none;" @endif >
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
                                                                <input type="text" class="form-control" name="old_first_name_insured[{{$key}}]" value="{{ isset($otherPartyInsured[$key]->first_name_insured) }}" placeholder="Enter First name">
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label for="exampleSelect1">Last Name</label>
                                                                <input type="text" class="form-control" name="old_last_name_insured[{{$key}}]" value="{{ isset($otherPartyInsured[$key]->last_name_insured) }}"  placeholder="Enter Last name">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label for="exampleSelect1">Mobile Number</label>
                                                                <input type="text" class="form-control" name="old_cellphone_insured[{{$key}}]" value="{{ isset($otherPartyInsured[$key]->cellphone_insured) }}" placeholder="Enter mobile number">
                                                            </div>
                                                        </div>
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label for="exampleSelect1">Email</label>
                                                                <input type="email" class="form-control" name="old_email_insured[{{$key}}]" value="{{ isset($otherPartyInsured[$key]->email_insured) }}" placeholder="Enter email">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <br>
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <div class="form-group">
                                                                <label for="exampleSelect1">Address</label>
                                                                <textarea class="form-control" name="old_address_insured[{{$key}}]" cols="20" rows="3">{{ isset($otherPartyInsured[$key]->address_insured) }}</textarea>
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
                            {{-- @endforeach --}}

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
                                                                            <input type="email" class="form-control" name="email_insured"  placeholder="Enter email">
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
                                                <div id="dataSpinner" style="display:contents;" class="form-group kt-spinner kt-spinner--sm kt-spinner--success kt-spinner--left kt-spinner--input"></div>
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
    {{-- <div class="kt-portlet" id="recipientDiv" style="display: none;">
        <div class="kt-portlet__body">
            @if($policy->kyc_recipient == 1)
                @include('admin.claims.recipient_kyc')
            @endif
        </div>
    </div> --}}
    {{--portet for recipient kyc ends--}}

    {{--portet for other information starts--}}
    <div class="kt-portlet" id="otherInfoDiv">
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
                {{-- <div class="col-lg-6">
                    <div class="form-group">
                        <label> Service Representative:</label>
                        <select class="form-control kt_selectpicker" data-live-search="true" title="Please select service representative" name="representative" id="exampleSelect1"    >
                            @foreach($users as $user)
                                <option value="{{$user->firstName}} {{$user->lastName}}" @if( $claimAccident->representative == $user->firstName.' '.$user->lastName) selected @endif >{{$user->firstName}} {{$user->lastName}}</option>
                            @endforeach
                        </select>
                    </div>
                </div> --}}


            </div>
            <div class="row">
                <div class="col-lg-6">
                    <div class="form-group">
                        <label class="kt-checkbox kt-checkbox--brand">
                            <input type="checkbox" name="pa_involved" id="recovery_involved" onchange="isRecoveryInvolved()" @if($claimAccident->recovery_involved =="on") checked @endif>
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

            {{--Recovery Involved section starts--}}
            <div id="recovery_involved_div" @if($claimAccident->recovery_involved !="on") style="display: none" @endif>
                <h3 class="kt-heading kt-heading--md kt-heading--no-top-margin" style="margin-top:20px;">
                    Recovery Details
                </h3>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="exampleSelect1">Name of third party</label>
                            <input type="text" class="form-control"  placeholder="Please enter your name" name="recovery_name" aria-describedby="emailHelp"  @if ($ClaimRecoveryDetails) value="{!! $ClaimRecoveryDetails->recovery_name !!}" @endif>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="exampleSelect1">Address of third party</label>
                            <input type="text" class="form-control"  placeholder="Please enter your address" name="recovery_address" aria-describedby="emailHelp" @if ($ClaimRecoveryDetails) value="{!! $ClaimRecoveryDetails->recovery_address !!}" @endif>
                        </div>
                    </div>

                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="exampleSelect1">Phone number of third party</label>
                            <input type="text" class="form-control"  placeholder="Please enter contact number" name="recovery_phone" aria-describedby="emailHelp" @if ($ClaimRecoveryDetails) value="{!! $ClaimRecoveryDetails->recovery_phone !!}" @endif>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="exampleSelect1">Email address of third party</label>
                            <input type="email" class="form-control"  placeholder="Please enter your email" name="recovery_email" aria-describedby="emailHelp" @if ($ClaimRecoveryDetails) value="{!! $ClaimRecoveryDetails->recovery_email !!}" @endif>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="exampleSelect1">Place of employment of third party</label>
                            <input type="text" class="form-control"  placeholder="Please enter place of employment" name="recovery_place_employment" aria-describedby="emailHelp" @if ($ClaimRecoveryDetails) value="{!! $ClaimRecoveryDetails->recovery_place_employment !!}" @endif>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="exampleSelect1">Work phone of third party</label>
                            <input type="text" class="form-control"  placeholder="Please enter work phone" name="recovery_work_phone" aria-describedby="emailHelp" @if ($ClaimRecoveryDetails) value="{!! $ClaimRecoveryDetails->recovery_work_phone !!}" @endif>
                        </div>
                    </div>
                </div>
                <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
            </div>
            {{--Recovery Involved section ends--}}

            <div class="row" style=" @if($claimAccident->attorney_involved =="on") display:block; @endif" id="attorneyDiv">
                <div class="col-lg-12">
                    <div class="form-group">
                        <label> Attorney List</label>
                        <select class="form-control kt_selectpicker" title="Please select attorney" name="attorney">
                            @foreach($attorneyRole as $a)
                                <option value="{{$a->id}}" @if($a->id == $claimAccident->attorney_id ) selected @endif>{{$a->firstName}} {{$a->lastName}} </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>


            <div class="row">
                <div class="col-lg-6">
                    {{-- <div class="form-group">
                        <label for="exampleSelect1">Date of loss:</label>
                        <input type="text" class="form-control  kt_datepicker_1"  name="incident_date" value="{!!  $claimAccident->incident_date !!}">
                    </div> --}}
                    <div class="form-group">
                        <label for="exampleSelect1">Date Of Claim Registered</label>
                        <input type="text" class="form-control  kt_datepicker_1"   placeholder="Please select date" name="registered_claim" autocomplete="off" @if ($claims) value="{!! $claims->registered_claim !!}" @endif>
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
                {{-- <div class="col-lg-4" style="margin-top:20px">
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
                </div> --}}
                {{-- <div class="col-lg-4" style="margin-top:20px">
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
                </div> --}}

            </div>
            <div class="row">
                {{-- <div class="col-lg-6">
                    <div class="form-group">
                        <label for="exampleSelect1">Description of loss:</label>
                        <textarea class="form-control" name="loss_description" title="Please provide loss description" >{!! $claimAccident->loss_description !!}</textarea>
                    </div>
                </div> --}}
                <div class="col-lg-6">
                    <div class="form-group">
                        <label> Weather Condition:</label>
                        <select class="form-control kt_selectpicker" data-live-search="true" title="Please select event name" name="event_name">
                            @foreach($eventNames as $eventName)
                                <option value="{{$eventName->value}}" @if($claimAccident->event_name == $eventName->value){ selected }@endif >{{$eventName->value}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            {{-- <div class="row">
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
            </div> --}}
            {{-- <div class="row">
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
                            @foreach($reportedByOpts as $opts)
                                <option value="{{$opts->value}}" @if( $claimAccident->relation == $opts->value) selected @endif >{{$opts->value}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div> --}}
            {{-- <div class="row">
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
            </div> --}}


        </div>
        {{-- <div class="kt-portlet__foot kt-portlet__foot--solid">
            <div class="kt-form__actions">
                <div class="row">
                    <div class="col-5"></div>
                    <div class="col-7">

                        <button type="submit" value="Submit" class="btn btn-brand">Update Claim</button>
                        <a class="btn btn-warning" style="color:black" onclick="setClaimToView()">Set to view</a>
                        <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}" >Cancel</a>
                    </div>
                </div>
            </div>
        </div> --}}

    </div>
    {{--portet for other information ends--}}

