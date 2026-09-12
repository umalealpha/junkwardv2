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

{{-- <h5>EMPLOYER:</h5>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="employer_name" value="{{ $coverageClaimData->employer_name }}" placeholder="Enter Name">
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Policy Number</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="employer_policy_no" value="{{ $coverageClaimData->employer_policy_no }}" placeholder="Enter Policy Number">
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Address</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="employer_Address" >{{ $coverageClaimData->employer_Address }}</textarea>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Phone No</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="employer_phone_no" value="{{ $coverageClaimData->employer_phone_no }}" placeholder="Enter Phone No">
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Trade or Business</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="employer_trade_or_business" value="{{ $coverageClaimData->employer_trade_or_business }}" placeholder="Enter Trade or Business">
        </div>
    </div>
</div> --}}


<h5>THE INJURED PERSON:</h5>
<hr>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="injured_name" value="{{ $coverageClaimData->injured_name }}" placeholder="Enter Name">
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Age</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="injured_age" value="{{ $coverageClaimData->injured_age }}" placeholder="Enter Age">
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Address</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="injured_address">{{ $coverageClaimData->injured_address }}</textarea>
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Status</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="injured_status"  class="form-control injured_status"  value="Married" @if($coverageClaimData->injured_status == 'Married') checked @endif >Married<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="injured_status"  class="form-control  injured_status"  value="Single" @if($coverageClaimData->injured_status == 'Single') checked @endif >Single<span></span>
        </label>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Normal Occupation</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="injured_occupation" value="{{ $coverageClaimData->injured_occupation }}" placeholder="Enter Normal Occupation">
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Nationality</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="injured_nationality" value="{{ $coverageClaimData->injured_nationality }}" >
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Period of service</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="injured_service_period" value="{{ $coverageClaimData->injured_service_period }}" >
        </div>
    </div>
</div>

<!-- <h5>Is he/she in your direct employ?</h5> -->
<!-- <hr> -->
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Is he/she in your direct employ?</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <select class="form-control kt_selectpicker your_direct_employ" data-live-search="true" id="your_direct_employ" name="your_direct_employ">
                <option value="">Please Select</option>
                <option value="1" {{ $coverageClaimData->your_direct_employ == 1 ? 'selected' : '' }}>Yes</option>
                <option value="0" {{ $coverageClaimData->your_direct_employ == 0 ? 'selected' : '' }}>No</option>
            </select>
            {{-- <input type="text" class="form-control" name="your_direct_employ" value="{{ $coverageClaimData->your_direct_employ }}"> --}}
        </div>
    </div>
</div>

<div class="form-group row" id="your_direct_employ_div" style="display: none;">
    <label for="example-text-input" class="col-3 col-form-label">If not, give name and address of Contractor.</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="address_of_contractor"  >{{ $coverageClaimData->address_of_contractor }}</textarea>
        </div>
    </div>
</div>


{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label"> State fully the work upon which he/she was engage at the time of the accident</label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <textarea type="text" class="form-control" name="time_of_accident"  >{{ $coverageClaimData->time_of_accident }}</textarea>
        </div>
    </div>
</div> --}}


<h5>THE ACCIDENT:</h5>
<hr>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Date </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control kt_datepicker_1" name="date" value="{{ $coverageClaimData->date }}" placeholder="Enter Date">
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Time </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="time" value="{{ $coverageClaimData->time }}" placeholder="Enter Time">
        </div>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Place </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="place" value="{{ $coverageClaimData->place }}" placeholder="Enter Place">
        </div>
    </div>
</div>


{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Date the injured person ceased work </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="injured_person_ceased_work" value="{{ $coverageClaimData->injured_person_ceased_work }}">
        </div>
    </div>
</div> --}}


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label"> How did the accident occur? </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="how_accident_occur" value="{{ $coverageClaimData->how_accident_occur }}" >
        </div>
    </div>
</div>


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">When and whom did he/she first report the accident? </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="first_report_accident" value="{{ $coverageClaimData->first_report_accident }}" >
        </div>
    </div>
</div>


{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label"> If accident happened in connection with any machinery give name of machine and state part causing accident </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="machine_state_part_causing" value="{{ $coverageClaimData->machine_state_part_causing }}" >
        </div>
    </div>
</div> --}}


{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label"> State names of any witness </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="state_names_witness" value="{{ $coverageClaimData->state_names_witness }}" >
        </div>
    </div>
</div> --}}


{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label"> State the nature of the injuries </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="state_nature_injuries" value="{{ $coverageClaimData->state_nature_injuries }}" >
        </div>
    </div>
</div> --}}



{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label"> Was he/she under the influence of drugs or drink or was he/she guilty of any misconduct or breach of orders or rules? </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="influence_drugs_or_drink" value="{{ $coverageClaimData->influence_drugs_or_drink }}" >
        </div>
    </div>
</div> --}}


{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label"> If so, please explain fully? </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="please_explain" value="{{ $coverageClaimData->please_explain }}" >
        </div>
    </div>
</div> --}}


{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label"> Was the accident due to anyone's negligence? </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="anyone_negligence" value="{{ $coverageClaimData->anyone_negligence }}" >
        </div>
    </div>
</div> --}}


{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label"> If so, give particulars. </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="give_particulars" value="{{ $coverageClaimData->give_particulars }}" >
        </div>
    </div>
</div> --}}


{{-- <div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">  Is he/she able to perform any part of his/her duties? </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="perform_any_part_duties" value="{{ $coverageClaimData->perform_any_part_duties }}" >
        </div>
    </div>
</div> --}}


<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label"> What is the probable period of disablement in your opinion? </label>
    <div class="Production col-8" style="margin-left: 10px;">
        <div class="form-group row">
            <input type="text" class="form-control" name="period_of_disablement" value="{{ $coverageClaimData->period_of_disablement }}" >
        </div>
    </div>
</div>

