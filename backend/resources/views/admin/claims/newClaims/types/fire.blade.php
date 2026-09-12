
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Address of premises where fire occurred</label>
    <div class="col-8">
        <textarea type="text" class="form-control" name="address_of_theft_occurred" value=""  placeholder="Address of premises where fire occurred."></textarea>
    </div>

</div>
{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">State the exact location in the premises From where the articles stolen were removed</label>
    <div class="col-8">
        <textarea type="text" class="form-control" name="location_article_stolen_removed" value=""  placeholder="State the exact location in the premises From where the articles stolen were removed"></textarea>
    </div>
</div> --}}
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">When was the property last seen by you?</label>
    <div class="col-8">
        <input type="text" class="form-control" name="property_last_seen" value=""  placeholder="When was the property last seen by you?">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Date and time of fire</label>
    <div class="col-8">
        <input type="datetime-local" class="form-control " name="date_time_of_theft" value=""  placeholder="Date and time of fire">
    </div>
</div>
{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Date and time loss was discovered</label>
    <div class="col-8">
        <input type="datetime-local" class="form-control " name="date_time_loss_discovered" value=""  placeholder="Date and time loss was discovered">
    </div>
</div> --}}
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
    <label for="example-text-input" class="col-3 col-form-label">Was anyone at the premises during the loss?</label>
    <div class="col-8">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="anyone_during_burglary" class="form-control anyone_during_burglary" value="1">Yes<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="anyone_during_burglary" class="form-control anyone_during_burglary"
                    value="0">No<span></span>
        </label>
    </div>
</div>
<div id="anyone_during_burglary_div" style='display:none'>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Give details in brief who was in the premises during the burglary?</label>
        <div class="col-8">
            <textarea type="text" class="form-control" name="details_during_burglary" value=""  placeholder="Give details in brief who was in the premises during the burglary?"></textarea>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">How many days have the premises been unoccupied During the past Twelve months</label>
    <div class="col-8">
        <input type="text" class="form-control" name="days_premises_unoccupied" value=""  placeholder="How many days have the premises been unoccupied During the past Twelve months">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Is the premises guarded by a watchman?</label>
    <div class="col-8">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="premises_guarded_by_watchman" class="form-control premises_guarded_by_watchman" value="1">Yes<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="premises_guarded_by_watchman" class="form-control premises_guarded_by_watchman"
                    value="0">No<span></span>
        </label>
    </div>
</div>
<div id="premises_guarded_by_watchman_div" style='display:none'>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Name of guard</label>
        <div class="col-8">
            <input type="text" class="form-control" name="name_of_guard" value=""  placeholder="Name of guard">
        </div>
    </div>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Telephone number of guard</label>
        <div class="col-8">
            <input type="text" class="form-control" name="telephone_of_guard" value=""  placeholder="Telephone number of guard">
        </div>
    </div>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Where was the guard during the fire?</label>
        <div class="col-8">
            <textarea type="text" class="form-control" name="guard_during_fire" value=""  placeholder="Where was the guard during the fire?"></textarea>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">State name of security agent</label>
    <div class="col-8">
        <input type="text" class="form-control" name="name_of_security_agent" value=""  placeholder="State name of security agent">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Please provide contract of agreement.</label>
    <div class="col-8">
        <div class="kt-avatar" id="contract_of_agreement" style="float: left; clear: left;">
            <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Please provide a copy of the contract">
                <i class="fa fa-pen"></i>
                <input type='file' class="form-control" name="contract_of_agreement" id="contract_of_agreement" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
            </label>
            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
        </div>
        {{-- <input type="file" class="form-control" name="contract_of_agreement" value=""  placeholder="Please provide a copy of the contract."> --}}
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Were all means of access of the premises properly secured at the time of the fire?</label>
    <div class="col-8">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="premises_properly_secured" class="form-control premises_properly_secured" value="1">Yes<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="premises_properly_secured" class="form-control  premises_properly_secured"
                    value="0">No<span></span>
        </label>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Do you suspect any person?</label>
    <div class="col-8">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="suspect_any_person" class="form-control suspect_any_person" value="1">Yes<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="suspect_any_person" class="form-control  suspect_any_person"
                    value="0">No<span></span>
        </label>
    </div>
</div>

<div id="suspect_any_person_div" style='display:none'>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Give details of suspect person</label>
        <div class="col-8">
            <textarea type="text" class="form-control" name="suspect_person_details" value=""  placeholder="Give details suspect person"></textarea>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">What was the total value of the buildings of your premises at the time of the loss?</label>
    <div class="col-8">
        <input type="text" class="form-control" name="total_value_premises_buildings" value=""  placeholder="What was the total value of the buildings of your premises at the time of the loss?">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Are there any other Insurances against fire upon the same property?</label>
    <div class="col-8">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="other_insurance_against_fire" class="form-control other_insurance_against_fire" value="1">Yes<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="other_insurance_against_fire" class="form-control other_insurance_against_fire"
                    value="0">No<span></span>
        </label>
    </div>
</div>
<div id="other_insurance_against_fire_div" style='display:none'>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Give details for other Insurances against fire upon the same property</label>
        <div class="col-8">
            <textarea type="text" class="form-control" name="insurance_against_fire_details" value=""  placeholder="Give details for other Insurances against fire upon the same property"></textarea>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">What is the estimated amount of the damaged property?</label>
    <div class="col-8">
        <input type="text" class="form-control" name="estimated_amount_of_damaged" value=""  placeholder="What is the estimated amount of the damaged property?">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Give details of records of previous loss in the premises or any other premises owned by you</label>
    <div class="col-8">
        <textarea type="text" class="form-control" name="details_of_previous_loss" value=""  placeholder="Give details of records of previous loss in the premises or any other premises owned by you"></textarea>
    </div>
</div>
