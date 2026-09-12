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

<h5>INSURED'S DETAILS:</h5>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="insured_name" value="{{$coverageClaimData->insured_name}}" >
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Business or Trading name</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="insured_treding_name" value="{{$coverageClaimData->insured_treding_name}}" >
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Postal Address</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="insured_postal_address" value="{{$coverageClaimData->insured_postal_address}}" >
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Email address</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="insured_email" value="{{$coverageClaimData->insured_email}}" >
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Telephone no</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="insured_telephone_no" value="{{$coverageClaimData->insured_telephone_no}}" >
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Facsimile</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="insured_facsimile" value="{{$coverageClaimData->insured_facsimile}}" >
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Mobile no</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="insured_mobile_no" value="{{$coverageClaimData->insured_mobile_no}}" >
        </div>
    </div>
</div>

<h5>DETAILS OF THE ACCIDENT/INCIDENT:</h5>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Date</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control kt_datepicker_1" name="accident_date" value="{{$coverageClaimData->accident_date}}" >
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Time</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="accident_time" value="{{$coverageClaimData->accident_time}}" >
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Location of accident/ incident</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="accident_incident" >{{$coverageClaimData->accident_incident}}</textarea>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Please provide details of damaged property and / or injuries suffered</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="accident_injuries" >{{$coverageClaimData->accident_injuries}}</textarea>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Have you admitted responsibility/ liability for the incident?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <label class="kt-radio" style="margin-left: 10px;"> 
            <input type="radio" name="accident_liability"  class="form-control accident_liability" value="YES" @if($coverageClaimData->accident_liability == 'YES') checked @endif >YES<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="accident_liability"  class="form-control  accident_liability"  value="NO" @if($coverageClaimData->accident_liability == 'NO') checked @endif >NO<span> </span>
        </label>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Does the claim involve a product that you manufacture or services provided to another person?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <label class="kt-radio" style="margin-left: 10px;"> 
            <input type="radio" name="accident_person"  class="form-control accident_person"  value="YES" @if($coverageClaimData->accident_person == 'YES') checked @endif >YES<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="accident_person"  class="form-control  accident_person"  value="NO"  @if($coverageClaimData->accident_person == 'NO') checked @endif >NO<span> </span>
        </label>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Were emergency services such as ambulance, police or fire brigade contacted?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <label class="kt-radio" style="margin-left: 10px;"> 
            <input type="radio" name="accident_contacted"  class="form-control accident_contacted"  value="YES" @if($coverageClaimData->accident_contacted == 'YES') checked @endif >YES<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="accident_contacted"  class="form-control  accident_contacted"  value="NO"  @if($coverageClaimData->accident_contacted == 'NO') checked @endif >NO<span> </span>
        </label>
    </div>
</div>

<h5>If yes please provide details and attach reports:</h5>
<p>Please give the following information about the job at which the accident occurred</p>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">(a) Are you the head contractor? If not, who is?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="attach_contractor" value="{{$coverageClaimData->attach_contractor}}" >
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">(b) Was anyone other than yourself or employee involved?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="attach_employee" value="{{$coverageClaimData->attach_employee}}" >
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">If so, please give names and addresses and state by whom employed</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="attach_employed" >{{$coverageClaimData->attach_employed}}</textarea>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Do you think that (i) you or any of your employee (s) was to blame?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="attach_blame" value="{{$coverageClaimData->attach_blame}}" >
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">(ii)Has any other accident ever occurred to any person, or damage been done under similar circumstances at the same place?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="attach_circumstances" >{{$coverageClaimData->attach_circumstances}}</textarea>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Was there any damage to property?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="attach_property" value="{{$coverageClaimData->attach_property}}" >
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">If so, please give details</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="attach_details" >{{$coverageClaimData->attach_details}}</textarea>
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name and address of the property owner</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="attach_owner" >{{$coverageClaimData->attach_owner}}</textarea>
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Damage</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="attach_damage" >{{$coverageClaimData->attach_damage}}</textarea>
        </div>
    </div>
</div>


<h5>DETAILS OF PARTY OR PARTIES MAKING CLAIM AGAINST YOU:</h5>
<hr>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="claim_name" value="{{$coverageClaimData->claim_name}}" >
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Telephone</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="claim_telephone" value="{{$coverageClaimData->claim_telephone}}" >
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Mobile no</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="claim_mobile" value="{{$coverageClaimData->claim_mobile}}" >
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Postal address</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="claim_postal" value="{{$coverageClaimData->claim_postal}}" >
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Solicitor's name</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="claim_solicitor" value="{{$coverageClaimData->claim_solicitor}}" >
        </div>
    </div>
</div>

<h5>1. WITNESSES:</h5>
<hr>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="witness1_name" value="{{$coverageClaimData->witness1_name}}" >
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Telephone no</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="witness1_telephone" value="{{$coverageClaimData->witness1_telephone}}" >
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Mobile no</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="witness1_mobile" value="{{$coverageClaimData->witness1_mobile}}" >
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Postal address</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="witness1_postal" value="{{$coverageClaimData->witness1_postal}}" >
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Relationship (e.g. employee, family, friend)</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="witness1_relationship" value="{{$coverageClaimData->witness1_relationship}}" >
        </div>
    </div>
</div>


<h5>2. WITNESSES:</h5>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="witness2_name" value="{{$coverageClaimData->witness2_name}}" >
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Telephone no</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="witness2_telephone" value="{{$coverageClaimData->witness2_telephone}}" >
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Mobile no</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="witness2_mobile" value="{{$coverageClaimData->witness2_mobile}}" >
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Postal address</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="witness2_postal" value="{{$coverageClaimData->witness2_postal}}" >
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Relationship (e.g. employee, family, friend)</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="witness2_relationship" value="{{$coverageClaimData->witness2_relationship}}" >
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Was there any damage to property?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="witness2_damage" value="{{$coverageClaimData->witness2_damage}}" >
        </div>
    </div>
</div>


