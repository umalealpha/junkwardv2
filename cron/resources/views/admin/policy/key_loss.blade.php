
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Financial Interest</label>
    <div class="col-9">
        <input type="text" class="form-control" id="financial_interest" name="financial_interest" placeholder="Please enter financial interest" autocomplete="off" title="Please provide financial interest">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Chassis Number</label>
    <div class="col-9">
        <input type="text" class="form-control" id="chassis_num" name="chassis_num" placeholder="Please enter chassis number" autocomplete="off" title="Please provide chassis number">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">
        Purpose of use</label>
    <div class="col-9">
        <select class="form-control kt_selectpicker" title="Please choose purpose of use"
                data-live-search="true"
                id="purpose"  name="purpose" required>
            @foreach($vehicle_purpose as $purpose)
                <option value="{{ $purpose->id }}">{{ $purpose->value }}</option>
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
                <option value="{{ $reason->id }}">{{ $reason->value }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Replacement Estimate</label>
    <div class="col-9">
        <input type="text" class="form-control required" id="estimate" name="estimate" placeholder="Please enter Replacement Estimate" autocomplete="off" title="Please provide Replacement Estimate">
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label" id="dateOfLoss">Date of Loss/stolen/Damage</label>
    <div class="col-9">
        <input type="text" id="lossDate" name="lossDate" class="form-control kt_datepicker_1 dob" autocomplete="off" title="Please select date of loss/stolen/damage" value="" placeholder="Enter date of death">
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Description</label>
    <div class="col-9">
        <textarea name="descriptionofLoss" minlength="20" name="descriptionofLoss" id="descriptionofLoss" class="form-control required" value="" placeholder="Enter description"></textarea>
    </div>
</div>

<div class="form-group row">
    <label for="exampleSelect1" class="col-3 col-form-label">Date of claim registered</label>
    <div class="col-9">
        <input type="text" class="form-control  kt_datepicker_1"   placeholder="Please select date" name="registered_claim" autocomplete="off">
    </div>
</div>

<div class="kt-portlet">
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
</div>


<div class="kt-portlet__foot kt-portlet__foot--solid">
    <div class="kt-form__actions">
        <div class="row">
            <div class="col-3"></div>
            <div class="col-9">
                <button type="submit"  value="Submit" class="btn btn-brand">Submit</button>
                <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}" >Cancel</a>
            </div>
        </div>
    </div>
</div>
