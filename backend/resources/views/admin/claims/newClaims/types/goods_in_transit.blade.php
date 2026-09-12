
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Address of premises where loss occurred</label>
    <div class="col-8">
        <textarea type="text" class="form-control" name="address_of_premises_loss" value=""  placeholder=" Address of premises where loss occurred."></textarea>
    </div>

</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Details of the carrier/ driver</label>
    <div class="col-8">
        <textarea type="text" class="form-control" name="details_of_driver" value=""  placeholder="Details of the carrier/ driver"></textarea>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">When was the property last seen by you?</label>
    <div class="col-8">
        <input type="text" class="form-control" name="property_last_seen" value=""  placeholder="When was the property last seen by you?">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Date and time of loss.</label>
    <div class="col-8">
        <input type="datetime-local" class="form-control " name="date_time_of_loss" value=""  placeholder="Date and time of loss.">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Brief description of incident</label>
    <div class="col-8">
        <textarea type="text" class="form-control" name="brief_description_incident" value=""  placeholder="Brief description of incident"></textarea>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Date and time the police were advised of loss.</label>
    <div class="col-8">
        <input type="datetime-local" class="form-control " name="date_time_police_advised" value=""  placeholder="Date and time the police were advised of loss.">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name of police station</label>
    <div class="col-8">
        <input type="text" class="form-control" name="police_station_name" value=""  placeholder="Name of police station">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Names of witnesses</label>
    <div class="col-8">
        <input type="text" class="form-control" name="witnesses_name" value=""  placeholder="Names of witnesses">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Witnesses mobile number</label>
    <div class="col-8">
        <input type="text" class="form-control" name="witnesses_mobile_number" value=""  placeholder="Witnesses mobile number">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">What is the total value of the loss?</label>
    <div class="col-8">
        <input type="text" class="form-control" name="total_value_of_loss" value=""  placeholder="What is the total value of the loss?">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Where was the consignment being transported to?</label>
    <div class="col-8">
        <input type="text" class="form-control" name="consignment_transported_to" value=""  placeholder="Where was the consignment being transported to?">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Where was the consignment from?</label>
    <div class="col-8">
        <input type="text" class="form-control" name="consignment_from" value=""  placeholder="Where was the consignment from?">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Registration number of the vehicle carrying the good?</label>
    <div class="col-8">
        <input type="text" class="form-control" name="vehicle_registration_number" value=""  placeholder="Registration number of the vehicle carrying the good?">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Is the carrier contracted?</label>
    <div class="col-8">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="is_carrier_contracted" class="form-control condition is_carrier_contracted" value="1">Yes<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="is_carrier_contracted" class="form-control  condition is_carrier_contracted"
                    value="0">No<span></span>
        </label>
    </div>
</div>

<div id="is_carrier_contracted_div" style='display:none'>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Please provide a copy of the contract.</label>
        <div class="col-8">
            <div class="kt-avatar" id="copy_of_contract" style="float: left; clear: left;">
                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Please provide a copy of the contract">
                    <i class="fa fa-pen"></i>
                    <input type='file' class="form-control" name="copy_of_contract" id="copy_of_contract" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                </label>
                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
            </div>
            {{-- <input type="file" class="form-control" name="copy_of_contract" value=""  placeholder="Please provide a copy of the contract."> --}}
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Does the carrier have their own GIT insurance</label>
    <div class="col-8">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="carrier_has_own_GIT_ins" class="form-control" value="1">Yes<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="carrier_has_own_GIT_ins" class="form-control"
                    value="0">No<span></span>
        </label>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Are there any other Insurances against theft upon the same property?</label>
    <div class="col-8">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="other_insurance_against_theft" class="form-control other_insurance_against_theft" value="1">Yes<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="other_insurance_against_theft" class="form-control other_insurance_against_theft"
                    value="0">No<span></span>
        </label>
    </div>
</div>
<div id="other_insurance_against_theft_div" style='display:none'>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Give details in brief</label>
        <div class="col-8">
            <textarea type="text" class="form-control" name="insurance_against_theft_details" value=""  placeholder="Give details in brief"></textarea>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Give details of records of previous loss in the premises or any other premises on similar goods</label>
    <div class="col-8">
        <input type="text" class="form-control" name="details_of_previous_loss_records" value=""  placeholder="Give details of records of previous loss in the premises or any other premises on similar goods">
    </div>
</div>

