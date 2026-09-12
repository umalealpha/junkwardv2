<div class="kt-portlet">
    <div class="kt-portlet_body"  >
        <div class="kt-section">
            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                    <input type="hidden" name="claim_type" value="Cellphone" />
                    <input type="hidden" name="policy_id" id="policy_id" value="{{$policy->id}}" />
                    <div class="row">
                        <div class="form-group col-6 col-sm-6">
                            <label>Device status <span class="red-star" style="color: red">*</span> </label>
                            <div class="btn-group btn-group-toggle d-flex vehicleDetails" style="width: 35%" data-toggle="buttons">
                                {{-- <label class="btn btn-outline-secondary ls0 nott">
                                    <input type="radio" name="damage_extent" autocomplete="off" value="Stolen" class="valid "> Stolen
                                </label> --}}
                                <label class="btn btn-outline-secondary ls0 nott">
                                    <input type="radio" name="damage_extent" autocomplete="off" value="Damaged" class="valid "> Damaged
                                </label>
                                {{-- <label class="btn btn-outline-secondary ls0 nott">
                                    <input type="radio" name="damage_extent" autocomplete="off" value="Lost" class="valid "> Lost
                                </label> --}}
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="form-group">
                                <label for="exampleSelect1">Date of Damage <span class="red-star" style="color: red">*</span></label>
                                <input type="text" class="form-control  kt_datepicker_1"  name="lossDate" autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group col-md-8">
                            <label>Cellphone Device <span class="red-star" style="color: red">*</span>
                                <i class="icon-info-circle" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="Select Cellphone make" data-original-title="" title="" aria-describedby="popover509230" style="font-size: 18px;width: 14px;color: #fe7f0c;vertical-align:bottom;height:24px;"></i>
                            </label>
                            <select class=" form-control" id="mileage" name="mileage">
                                @foreach($policyCellphone as $cell)
                                    <option value="{{$cell->cell_phone_make}} {{$cell->cell_phone_model}} ">{{$cell->cell_phone_make}}  {{$cell->cell_phone_model}} </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-8">
                            <label for="exampleSelect1">Date of claim registered</label>
                            <input type="text" class="form-control  kt_datepicker_1"   placeholder="Please select date" name="registered_claim" autocomplete="off">
                        </div>

                        <div class="form-group row col-12">
                            <h3 class="col-12 kt-heading kt-heading&#45;&#45;">
                                Damaged Device Photos
                            </h3>
                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Cellphone Front<span id="leftError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                <div class="kt-avatar" id="cell_phone_front" style="float: left; clear: left;">
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file' class="cell_phone_front" name="cell_phone_front" id="cell_phone_front" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Cellphone Back<span id="rightError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                <div class="kt-avatar" id="cell_phone_back" style="float: left; clear: left;">
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file' class="cell_phone_back" name="cell_phone_back" id="cell_phone_back" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Cellphone left<span id="rightError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                <div class="kt-avatar" id="cell_phone_left" style="float: left; clear: left;">
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file' class="cell_phone_left" name="cell_phone_left" id="cell_phone_left" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Cellphone right<span id="rightError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                <div class="kt-avatar" id="cell_phone_right" style="float: left; clear: left;">
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file' class="cell_phone_right" name="cell_phone_right" id="cell_phone_right" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Cellphone top<span id="rightError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                <div class="kt-avatar" id="cell_phone_top" style="float: left; clear: left;">
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file' class="cell_phone_top" name="cell_phone_top" id="cell_phone_top" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <h3 class="col-form-label" style="float: left;">Cellphone bottom<span id="rightError" style="font-size:10px; display:none; color:red;">Required</span></h3>
                                <div class="kt-avatar" id="cell_phone_bottom" style="float: left; clear: left;">
                                    <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                    <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                        <i class="fa fa-pen"></i>
                                        <input type='file' class="cell_phone_bottom" name="cell_phone_bottom" id="cell_phone_bottom" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                    </label>
                                    <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8 form-group">
                            <label>Description <span class="red-star" style="color: red">*</span><i class="icon-info-circle" data-container="body" data-trigger="hover" data-toggle="popover" data-placement="top" data-content="Please provide a detailed description of how the Device/Devices were lost,stolen or damaged." data-original-title="" title="" aria-describedby="popover509230" style="font-size: 18px;width: 14px;color: #F08021;vertical-align:bottom;height:24px;"></i></label>
                            <textarea name="descriptionofLoss" minlength="" id="descriptionofLoss" class="form-control" value="" placeholder="Enter description"></textarea>
                        </div>
                        <div class="row col-lg-12">
                            {{-- <div class="form-group col-lg-8">
                                <label>Police Station</label>
                                <input id="police_station" type="text" class="form-control id-type validateGroup1" name="police_station" placeholder="Please enter Police Station"    value="">
                            </div> --}}
                            {{-- <div class="form-group col-lg-8">
                                <label>Case Number</label>
                                <input id="case_number" type="text" class="form-control id-type" name="case_number" placeholder="Please enterCase Number" maxlength="12" value="" >
                            </div> --}}
                            <div class="form-group col-lg-8">
                                <label>Contact Number <span class="red-star" style="color: red">*</span></label>
                                <input id="contact_number" type="text" class="form-control id-type" name="contact_number" maxlength="8" placeholder="Please enterCase Number" value="" >
                            </div>
                           
                            {{-- <div class="form-group col-lg-8">
                                <label>ITC Reference Number</label>
                                <input id="ITC_reference_number" type="text" class="form-control id-type" name="ITC_reference_number" placeholder="Please enterCase Number" value="" >
                            </div> --}}
                            <div class="form-group col-lg-8">
                                <label>Date Reported to Alpha Direct <span class="red-star" style="color: red">*</span></label>
                                <input type="text" class="form-control kt_datepicker_1 dob" name="date_reported_to_alpha" id="date_reported_to_alpha" autocomplete="off" value=""  placeholder="Select date"/>
                            </div>
                        </div>

                    </div>


            </div>
        </div>
        @if($policy->kyc_recipient)
            @include('admin.policy.recipient_kyc')
        @endif
        <div class="kt-portlet__foot kt-portlet__foot--solid">
            <div class="kt-form__actions">
                <div class="row">
                    <div class="col-5"></div>
                    <div class="col-7">
                        <button type="submit" value="Submit" id="saveBtn" class="btn btn-brand">Save Claim</button>
                        <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading... </button>
                        <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}" >Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>