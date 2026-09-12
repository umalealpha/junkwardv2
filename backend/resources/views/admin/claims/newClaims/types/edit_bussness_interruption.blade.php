<input type="hidden" name="bi_id" value="{{ $bi->id }}" />
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
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">What is the nature of your interruption?</label>
            <input type="text" class="form-control" name="natureinterruption" value="{{ $bi->nature_of_interruption }}"  placeholder="Enter What is the nature of your interruption?">
        </div>
    </div> --}}
</div>

<div class="row">
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Please give details of and estimated amount of loss for each item to beclaimed.</label>
            <textarea type="text" class="form-control" name="details_and_estimated_amount_of_loss"  placeholder="Description of Loss">{{ $bi->details_and_estimated_amount_of_loss }}</textarea>
        </div>
    </div> --}}
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Have you previously suffered loss/damage?</label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="previously_loss"  {{ $bi->previously_suffered_loss == 1 ? 'checked' : '' }} class="form-control condition" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="previously_loss"  {{ $bi->previously_suffered_loss == 0 ? 'checked' : '' }} class="form-control  condition"
                        value="0">No<span></span>
            </label>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="form-group">
            <label for="example-text-input">Has any other party have an interest in the insured property, e.g. credit agreement?</label>
            <input type="text" class="form-control" name="other_party_interest" value="{{ $bi->other_party_interest }}"  placeholder="Has any other party have an interest in the insured property, e.g. credit agreement?">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Is there any other insurance covering this loss/damage?</label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="other_insurance_covering"  {{ $bi->other_insurance_covering == 1 ? 'checked' : '' }} class="form-control condition" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="other_insurance_covering" {{ $bi->other_insurance_covering == 0 ? 'checked' : '' }}  class="form-control  condition"
                       value="0">No<span></span>
            </label>
        </div>
    </div>
</div>
