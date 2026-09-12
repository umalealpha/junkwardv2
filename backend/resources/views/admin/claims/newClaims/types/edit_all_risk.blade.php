<input type="hidden" name="allrisk_id" value="{{ $allRisk->id }}" />
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
            <label for="example-text-input">Has the property been stolen or damaged?</label>
            <label class="kt-radio" style="margin-left: 10px;">
                {{-- <input type="text" class="form-control" name="property_stolen_damaged" value=""  placeholder="Has the property been stolen or damaged?"> --}}
                <input type="radio" name="property_stolen_damaged"  {{ $allRisk->property_stolen_damaged == 1 ? 'checked' : '' }}  class="form-control propertyStolenDamaged" value="1">Damaged<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="property_stolen_damaged" {{ $allRisk->property_stolen_damaged == 0 ? 'checked' : '' }} class="form-control propertyStolenDamaged"
                        value="0">Stolen<span></span>
            </label>
        </div>
    </div>
</div>

{{-- <div class="row"> --}}
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Full particulars of circumstances of the loss or damage</label>
            <textarea type="text" class="form-control" name="circumstances_loss_damage" value=""  placeholder="Full particulars of circumstances of the loss or damage">{{ $allRisk->circumstances_loss_damage }}</textarea>
        </div>
    </div> --}}
{{-- </div> --}}

<div class="row">
    <div class="col-lg-12">
        <div class="form-group">
            <label for="example-text-input">Have you ever before sustained previous</label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="loss_cause" {{ $allRisk->loss_cause == 1 ? 'checked' : '' }}  class="form-control condition lossCause" value="1">Loss by theft?<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="loss_cause" {{ $allRisk->loss_cause == 0 ? 'checked' : '' }} class="form-control  condition lossCause"
                        value="0">Loss of, or damage to, any article of value from any other cause?(if so, please state particulars)<span></span>
            </label>
        </div>
    </div>
</div>
<div class="row" id="otherCauseInput" style='display:none'>
    <div class="col-lg-8">
        <div class="form-group">
            <label for="example-text-input">Other cause</label>
            <input type="text" class="form-control" name="loss_by_other_cause" value="{{ $allRisk->loss_by_other_cause }}"  placeholder="Other cause">
        </div>
    </div>
</div>

<div id="classStolen" style="display: none;">
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Was the property stolen from a car or unlocked
            premises?</label>
        <div class="col-8">
            {{-- <input type="text" class="form-control" name="stolenfromcar_unlockedpremises" value=""  placeholder="Was the property stolen from a car or unlocked premises? provide details"> --}}
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="stolenfromcar_unlockedpremises" {{ $allRisk->stolenfromcar_unlockedpremises == 1 ? 'checked' : '' }} checked  class="form-control condition" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="stolenfromcar_unlockedpremises" {{ $allRisk->stolenfromcar_unlockedpremises == 0 ? 'checked' : '' }} class="form-control  condition"
                        value="0">No<span></span>
            </label>
        </div>
    </div>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Has a thorough search been made for the article(s)</label>
        <div class="col-8">
            {{-- <input type="text" class="form-control" name="thorough_search_made_for_article" value=""  placeholder="Has a thorough search been made for the article(s)"> --}}
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="thorough_search_made_for_article"  {{ $allRisk->thorough_search_made_for_article == 1 ? 'checked' : '' }}  class="form-control" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="thorough_search_made_for_article"  {{ $allRisk->thorough_search_made_for_article == 0 ? 'checked' : '' }} class="form-control"
                        value="0">No<span></span>
            </label>
        </div>
    </div>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label"> Are you the sole owner of the property? If not give the
            name of the owner</label>
        <div class="col-8">
            {{-- <input type="text" class="form-control" name="sole_owner_of_property" value=""  placeholder=" Are you the sole owner of the property? If not give the name of the owner"> --}}
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="is_sole_owner_of_property"  {{ $allRisk->is_sole_owner_of_property == 1 ? 'checked' : '' }}  class="form-control soleOwner" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="is_sole_owner_of_property" {{ $allRisk->is_sole_owner_of_property == 0 ? 'checked' : '' }} class="form-control soleOwner"
                        value="0">No<span></span>
            </label>
        </div>
    </div>
    <div class="form-group row" id="soleOwnerDiv" style="display: none;">
        <label for="example-text-input" class="col-3 col-form-label">Name of the owner</label>
        <div class="col-8">
            <input type="text" class="form-control" name="sole_owner_of_property" value="{{ $allRisk->sole_owner_of_property }}"  placeholder="Name of the owner">
        </div>
    </div>
</div>
