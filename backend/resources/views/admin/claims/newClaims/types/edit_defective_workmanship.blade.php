<h3>DETAILS OF THE ACCIDENT/INCIDENT</h3>
<input type="hidden" name="dw_id" value="{{ isset($dw->id) ? $dw->id : null }}" />
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
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Location of accident/ incident</label>
            <input type="text" class="form-control" name="Location_of_accident" value="{{ isset($dw->location_of_accident) ? $dw->location_of_accident : null }}"  placeholder="Enter Location of accident/ incident">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Accident Date Time</label>
            <input type="datetime-local" class="form-control " name="accident_date_time" value="{{ isset($dw->accident_date_time) ? $dw->accident_date_time : null }}"  placeholder="Enter Accident Date Time">
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Owners Name</label>
            <input type="text" class="form-control" name="owners_name" value="{{ isset($dw->owners_name) ? $dw->owners_name : null }}"  placeholder="Enter Owners Name">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="form-group">
            <label for="example-text-input">Telephone number</label>
            <input type="text" class="form-control" name="telephone_number" value="{{ isset($dw->telephone_number) ? $dw->telephone_number : null }}"  placeholder="Enter Telephone number">
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-12">
        <div class="form-group">
            <label for="example-text-input">Mobile number</label>
            <input type="text" class="form-control" name="mobile_number" value="{{ isset($dw->mobile_number) ? $dw->mobile_number : null }}"  placeholder="Enter Mobile number">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Address</label>
            <textarea type="text" class="form-control" name="address" value=""  placeholder="Address">{{ isset($dw->address) ? $dw->address : null }}</textarea>
        </div>
    </div>
</div>

<div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

<h3>CLAIMANTS VEHICLE</h3>

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Make</label>
            <input type="text" class="form-control" name="make" value="{{ isset($dw->make) ? $dw->make : null }}"  placeholder="Enter make">
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Model</label>
            <input type="text" class="form-control" name="model" value="{{ isset($dw->model) ? $dw->model : null }}"  placeholder="Enter model">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Registration</label>
            <input type="text" class="form-control" name="registration" value="{{ isset($dw->registration) ? $dw->registration : null }}"  placeholder="Enter Registration">
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Is the vehicle drivable?</label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="vehicle_drivable"  {{ (isset($dw->vehicle_drivable) && $dw->vehicle_drivable == 1) ? 'checked' : '' }}  class="form-control condition" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="vehicle_drivable"  {{ (isset($dw->vehicle_drivable) && $dw->vehicle_drivable == 0) ? 'checked' : '' }} class="form-control  condition"
                       value="0">No<span></span>
            </label>
        </div>
    </div>
</div>

<div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>

<h3>INCIDENT DETAILS</h3>

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Was the vehicle handed to the claimant?</label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="vehicle_handed_claimant" {{ (isset($dw->vehicle_handed_claimant) && $dw->vehicle_handed_claimant == 1) ? 'checked' : '' }}    class="form-control condition" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="vehicle_handed_claimant" {{ (isset($dw->vehicle_handed_claimant) && $dw->vehicle_handed_claimant == 0) ? 'checked' : '' }} class="form-control  condition"
                       value="0">No<span></span>
            </label>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">When was the vehicle handed?</label>
            <input type="datetime-local" class="form-control " name="when_vehicle_handed" value="{{ isset($dw->when_vehicle_handed) ? $dw->when_vehicle_handed : null }}"  placeholder="Has any other party have an interest in the insured property, e.g. credit agreement?">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Date and allegations received from claimant</label>
            <textarea type="text" class="form-control" name="allegations_received" value=""  placeholder="Date and allegations received from claimant">{{ isset($dw->allegations_received) ? $dw->allegations_received : null }}</textarea>
        </div>
    </div>
</div>
