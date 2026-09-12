<h3 class="kt-heading kt-heading--md kt-heading--no-top-margin" style="margin-top:20px;">
    Loss Of Key Claim Information
</h3>
{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Financial Interest</label>
    <div class="col-9">
        <input type="text" class="form-control" id="financial_interest" name="financial_interest" placeholder="Please enter financial interest" autocomplete="off" title="Please provide financial interest">
    </div>
</div> --}}
{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Chassis Number</label>
    <div class="col-9">
        <input type="text" class="form-control" id="chassis_num" name="chassis_num" placeholder="Please enter chassis number" autocomplete="off" title="Please provide chassis number">
    </div>
</div> --}}
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">
        Purpose of use</label>
    <div class="col-9">
        <select class="form-control kt_selectpicker" title="Please choose purpose of use"
                data-live-search="true"
                id="purpose"  name="purpose" required>
            @foreach($vehicle_purpose as $purpose)
                <option value="{{ $purpose->id }}" {{ $purpose->id == $keyloss->purpose ? 'selected' : '' }}>{{ $purpose->value }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">
        Is the key lost or damaged or stolen</label>
    <div class="col-9">
        <select class="form-control kt_selectpicker" title="Please choose reason"
                data-live-search="true"
                id="reason"  name="reason" required>
            @foreach($reasons as $reason)
                <option value="{{ $reason->id }}"  {{ $reason->id == $keyloss->reason ? 'selected' : '' }}>{{ $reason->value }}</option>
            @endforeach
        </select>
    </div>
</div>

{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Replacement Estimate</label>
    <div class="col-9">
        <input type="text" class="form-control required" id="estimate" name="estimate" placeholder="Please enter Replacement Estimate" autocomplete="off" title="Please provide Replacement Estimate">
    </div>
</div> --}}

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label" id="dateOfLoss">Date of Loss/stolen/Damage</label>
    <div class="col-9">
        <input type="text" id="lossDate" name="lossDate" class="form-control kt_datepicker_1 dob" autocomplete="off" title="Please select date of loss/stolen/damage" value="{{$keyloss->date_of_loss}}" placeholder="Date of Loss/stolen/Damage">
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Description</label>
    <div class="col-9">
        <textarea name="descriptionofLoss" minlength="20" name="descriptionofLoss" id="descriptionofLoss" class="form-control required" value="" placeholder="Enter description">{{$keyloss->description}}</textarea>
    </div>
</div>

<div class="form-group row">
    <label for="exampleSelect1" class="col-3 col-form-label">Date of claim registered</label>
    <div class="col-9">
        <input type="text" class="form-control  kt_datepicker_1" value="{{$claims->registered_claim}}" placeholder="Please select date" name="registered_claim" autocomplete="off">
    </div>
</div>

{{-- <div class="kt-portlet">
    <div class="kt-invoice-v2">
        <div class="kt-invoice-v2__header grid">
            <div class="kt-invoice-v2__header-right">
                <div class="kt-invoice-v2__logo thumb">
                    <div class="row">
                        <div class="col-md-3">
                            <h3 class="col-form-label" style="float: left;">Police Affidavit</h3>
                            <div class="kt-avatar" id="police_affidavit" style="float: left; clear: left;">
                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                    <i class="fa fa-pen"></i>
                                    <input type='file' class="police_affidavit" name="police_affidavit" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                </label>
                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr>

            <h3>Insured Details</h3>
            <div class="form-group row">
                <label for="example-text-input" class="col-3 col-form-label">Name of Insured</label>
                <div class="col-9">
                    <input type="text" class="form-control" id="name_of_insured" name="name_of_insured" placeholder="Please enter name of insured" autocomplete="off" title="Please provide name of insured">
                </div>
            </div>
            <div class="form-group row">
                <label for="example-text-input" class="col-3 col-form-label">Address</label>
                <div class="col-9">
                    <input type="text" class="form-control" id="insured_address" name="insured_address" placeholder="Please enter address" autocomplete="off" title="Please provide address">
                </div>
            </div>
            <div class="form-group row">
                <label for="example-text-input" class="col-3 col-form-label">Occupation</label>
                <div class="col-9">
                    <input type="text" class="form-control" id="insured_occupation" name="insured_occupation" placeholder="Please enter occupation" autocomplete="off" title="Please provide occupation">
                </div>
            </div>
            <div class="form-group row">
                <label for="example-text-input" class="col-3 col-form-label">Email</label>
                <div class="col-9">
                    <input type="email" class="form-control" id="insured_email" name="insured_email" placeholder="Please enter email" autocomplete="off" title="Please provide email">
                </div>
            </div>
            <div class="form-group row">
                <label for="example-text-input" class="col-3 col-form-label">Contact No</label>
                <div class="col-9">
                    <input type="text" class="form-control" id="insured_contact_no" name="insured_contact_no" placeholder="Please enter contact no" autocomplete="off" title="Please provide contact no">
                </div>
            </div>

            <hr>

            <h3>Vehicle Details</h3>
            <div class="form-group row">
                <label for="example-text-input" class="col-3 col-form-label">Registration Number</label>
                <div class="col-9">
                    <input type="text" class="form-control" name="vehicle_plate"  placeholder="Enter vehicle registration number">
                </div>
            </div>
            <div class="form-group row">
                <label for="example-text-input" class="col-3 col-form-label">Is Imported?</label>
                <div class="col-9">
                    <select class="form-control kt_selectpicker select" required name="is_imported"
                                title="Please select type" data-live-search="true" id="is_imported">
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                </div>
            </div>
            <div class="form-group row">
                <label for="example-text-input" class="col-3 col-form-label">Make</label>
                <div class="col-9">
                    <select class="form-control make kt_selectpicker"
                            title="Please choose make" data-live-search="true"
                            name="make" id="make_rerate">
                        @foreach($vehicle_make as $vehicleMake)
                            @if ($vehicle->is_imported == 'Yes' || $vehicle->is_imported == 1)
                                <option value="{{ $vehicleMake->s_Make }}">
                                    {{ ucwords(strtoupper($vehicleMake->s_Make)) }}</option>
                            @endif
                            @if ($vehicle->is_imported == 'No' || $vehicle->is_imported == 0)
                                <option value="{{ $vehicleMake }}">
                                    {{ ucwords(strtoupper($vehicleMake)) }}</option>
                            @endif
                        @endforeach

                    </select>
                </div>
            </div>
            <div class="form-group row">
                <label for="example-text-input" class="col-3 col-form-label">Manufacturing Year</label>
                <div class="col-9">
                    <select class="form-control kt_selectpicker" required name="year"
                        title="Please select manufacturing year" data-live-search="true"
                        id="year_rerate">
                        @foreach ($years as $key => $year)
                            <option value="{{ $year }}">
                                {{ $year }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group row">
                <label for="example-text-input" class="col-3 col-form-label">Model</label>
                <div class="col-9">
                    <select class="form-control kt_selectpicker" required name="model"
                        title="Please select model" data-live-search="true"
                        id="model_rerate">
                        @if ($vehicle->is_imported == 'Yes' || $vehicle->is_imported == 1)
                                @foreach ($vehicle_model as $key => $model)
                                    <option value="{{ $model['model'] }}">
                                        {{ ucwords(strtoupper($model['model'])) }}</option>
                                @endforeach
                            @endif

                            @if ((is_array($vehicle_model) && $vehicle->is_imported == 'No') || $vehicle->is_imported == 0)
                                @foreach ($vehicle_model as $key => $model)
                                    <option value="{{ $model['Model'] }}">
                                        {{ ucwords(strtoupper($model['Model'])) }}</option>
                                @endforeach
                            @endif
                    </select>
                </div>
            </div>

            <hr>

            <h3>Quotation :</h3>
            <div class="kt-invoice-v2__header-left">
                <div class="kt-invoice-v2__logo thumb">
                    <br>
                    <p style="font-size: 172%;margin-bottom: 15px;font-weight: 510;">Quote 1</p>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="exampleSelect1">Name of company</label>
                                <input type="text" id="company_1" name="company_1" class="form-control required" autocomplete="off" title="Please enter name of company" value="" placeholder="Please enter name of company">
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="exampleSelect1">Amount of quote</label>
                                <input type="number" id="amount_quote_1" name="amount_quote_1" class="form-control required" autocomplete="off" title="Please enter amount of quote" value="" placeholder="Please enter amount of quote">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <h3 class="col-form-label" style="float: left;">Quote</h3>
                            <div class="kt-avatar" id="quote_1" style="float: left; clear: left;">
                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                    <i class="fa fa-pen"></i>
                                    <input type='file' class="quote_1" name="quote_1" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                </label>
                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <hr>
            <div class="kt-invoice-v2__header-left">
                <div class="kt-invoice-v2__logo thumb">
                    <br>
                    <p style="font-size: 172%;margin-bottom: 15px;font-weight: 510;">Quote 2</p>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="exampleSelect1">Name of company</label>
                                <input type="text" id="company_2" name="company_2" class="form-control required" autocomplete="off" title="Please enter name of company" value="" placeholder="Please enter name of company">
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label for="exampleSelect1">Amount of quote</label>
                                <input type="number" id="amount_quote_2" name="amount_quote_2" class="form-control required" autocomplete="off" title="Please enter amount of quote" value="" placeholder="Please enter amount of quote">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <h3 class="col-form-label" style="float: left;">Quote</h3>
                            <div class="kt-avatar" id="quote_2" style="float: left; clear: left;">
                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                    <i class="fa fa-pen"></i>
                                    <input type='file' class="quote_2" name="quote_2" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?>/>
                                </label>
                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> --}}


