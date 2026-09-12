<h3>Give the name of defaulting employees and their respective positions:</h3>
<input type="hidden" name="coverage_claim_id" value="{{ $coverageClaimData->id }}" />
<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Claim Sub Type</label>
            <select class="form-control" id="ClaimSubType" name="ClaimSubType">
                @foreach($claimSubType as $type)
                    <option value="{{$type->id}}" {{ $type->id == $newclaim->claim_sub_type_id ? 'selected' : '' }}>{{$type->value}}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">BROKER/AGENT</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="broker_agent" value="{{$coverageClaimData->broker_agent}}" >
        </div>
    </div>
</div> --}}

{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">POLICY NUMBER</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="policy_no" value="{{$coverageClaimData->policy_no}}" >
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">ID number</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="id_number" value="{{$coverageClaimData->id_number}}" >
        </div>
    </div>
</div> --}}

{{-- <h5>Insured:</h5>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name and occupation</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="name_occupation" >{{$coverageClaimData->policy_no}}</textarea>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Address and (day) telephone number</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="address_tele_no" >{{$coverageClaimData->address_tele_no}}</textarea>
        </div>
    </div>
</div> --}}

<h5>Loss/damage occurrence:</h5>
<hr>
{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Date and time of loss/damage</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="date_time_of_loss_damage" >{{$coverageClaimData->date_time_of_loss_damage}}</textarea>
        </div>
    </div>
</div> --}}

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">When was loss/damage discovered?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="loss_damage_discovered"  >{{$coverageClaimData->loss_damage_discovered}}</textarea>
        </div>
    </div>
</div>


<h5>Loss/damage place:</h5>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Place where loss/damage occurred</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="loss_damage_occurred" >{{$coverageClaimData->loss_damage_occurred}}</textarea>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Were premises occupied? By whom?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="premises_occupied"   >{{$coverageClaimData->premises_occupied}}</textarea>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">If not occupied, when last occupied?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="last_occupied"  >{{$coverageClaimData->last_occupied}}</textarea>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Purpose of occupation</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="purpose_of_occupation"   >{{$coverageClaimData->purpose_of_occupation}}</textarea>
        </div>
    </div>
</div>

<h5>Cause of loss/damage:</h5>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">What is the nature of your interruption?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="nature_interruption"  >{{$coverageClaimData->nature_interruption}}</textarea>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Please give details of and estimated amount of loss for each item to beclaimed.</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="loss_for_each_item"  >{{$coverageClaimData->loss_for_each_item}}</textarea>
        </div>
    </div>
</div>

<h5>Previous loss/damage:</h5>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Have you previously suffered loss/damage?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="previously_suffered_loss" >{{$coverageClaimData->previously_suffered_loss}}</textarea>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">If so, give details</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="give_details"   >{{$coverageClaimData->give_details}}</textarea>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">If insured, provide name of insurer</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="name_of_insurer"  >{{$coverageClaimData->name_of_insurer}}</textarea>
        </div>
    </div>
</div>

<h5>Police:</h5>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Police reference number and station and date reported</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="reference_no_station" >{{$coverageClaimData->reference_no_station}}</textarea>
        </div>
    </div>
</div>

<h5>Other interest:</h5>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Has any other party have an interest in the insured property, e.g. credit agreement?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="interest_insured_property" >{{$coverageClaimData->interest_insured_property}}</textarea>
        </div>
    </div>
</div>

<h5>Other insurance:</h5>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Is there any other insurance covering this loss/damage?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="other_insurance_covering"  >{{$coverageClaimData->other_insurance_covering}}</textarea>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">If so, give name of insurer</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="give_name_insurer"  >{{$coverageClaimData->give_name_insurer}}</textarea>
        </div>
    </div>
</div>

<h5>Value:</h5>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Estimated total value of all the property insured under the policy</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="value_all_property"  >{{$coverageClaimData->value_all_property}}</textarea>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">When last valued?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="when_last_valued" >{{$coverageClaimData->when_last_valued}}</textarea>
        </div>
    </div>
</div>

{{-- <h5>Payment method:</h5>
<hr>
<p>You may select, for added security, for payment of any amount due to you to be made directly into a bank account. Please specify the name
of the bank, branch, name of account and account number.</p>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name of bank</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="name_of_bank" value="{{$coverageClaimData->name_of_bank}}" >
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Branch</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="branch" value="{{$coverageClaimData->branch}}" >
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name of account</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="name_of_account" value="{{$coverageClaimData->name_of_account}}" >
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Account number</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="account_number" value="{{$coverageClaimData->account_number}}" >
        </div>
    </div>
</div> --}}









