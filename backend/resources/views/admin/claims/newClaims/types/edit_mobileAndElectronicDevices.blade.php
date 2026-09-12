<input type="hidden" name="mobileAndElectDev_id" value="{{ $mobileAndElectDev->id }}" />
<div class="row">
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Claim Sub Type</label>
            <select class="form-control" id="ClaimSubType" name="ClaimSubType">
                @foreach($claimSubType as $type)
                    <option value="{{$type->id}}" {{ $type->id == $newclaim->claim_sub_type_id ? 'selected' : '' }}>{{$type->value}}</option>
                @endforeach
            </select>
        </div>
    </div> --}}
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input" class="col-3 col-form-label">Insured’s Name</label>
            <input type="text" class="form-control" name="insured_name" value="{{ $mobileAndElectDev->insured_name }}"  placeholder="Insured’s Name">
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input" class="col-3 col-form-label">E-mail Address</label>
            <input type="email" class="form-control" name="email_address" value="{{ $mobileAndElectDev->email_address }}"  placeholder="E-mail Address">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input" class="col-3 col-form-label">Address</label>
            <input type="text" class="form-control" name="address" value="{{ $mobileAndElectDev->address }}"  placeholder="Address">
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input" class="col-3 col-form-label">Telephone No.</label>
            <input type="text" class="form-control" name="telephone_no" value="{{ $mobileAndElectDev->telephone_no }}"  placeholder="Telephone No.">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Has the property been stolen or damaged?</label>
            <label class="kt-radio" style="margin-left: 10px;">
                {{-- <input type="text" class="form-control" name="property_stolen_damaged" value=""  placeholder="Has the property been stolen or damaged?"> --}}
                <input type="radio" name="property_stolen_damaged"  {{ $mobileAndElectDev->property_stolen_damaged == 1 ? 'checked' : '' }}  class="form-control propertyStolenDamaged" value="1">Damaged<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="property_stolen_damaged" {{ $mobileAndElectDev->property_stolen_damaged == 0 ? 'checked' : '' }} class="form-control propertyStolenDamaged"
                        value="0">Stolen<span></span>
            </label>
        </div>
    </div>
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input" class="col-3 col-form-label">Address of the premises or place, where loss or damage occurred (If lost) from premises please state use of premises)</label>
            <textarea type="text" class="form-control" name="premises_address" value=""  placeholder="Address of the premises or place, where loss or damage occurred (If lost) from premises please state use of premises)">{{ $mobileAndElectDev->premises_address }}</textarea>
        </div>
    </div> --}}
</div>
<div class="row">
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Full particulars of circumstances of the loss or damage</label>
            <textarea type="text" class="form-control" name="circumstances_loss_damage" value=""  placeholder="Full particulars of circumstances of the loss or damage">{{ $mobileAndElectDev->circumstances_loss_damage }}</textarea>
        </div>
    </div> --}}
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input" class="col-3 col-form-label">Date and time when loss or damage was discovered</label>
            <input type="datetime-local" class="form-control " name="date_time_loss_discovered" value="{{ $mobileAndElectDev->date_time_loss_discovered }}"  placeholder="Date and time when loss or damage was discovered">
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">By whom discovered?</label>
            <input type="text" class="form-control" name="whom_discovered" value="{{ $mobileAndElectDev->whom_discovered }}"  placeholder="By whom discovered?">
        </div>
    </div>
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input" class="col-3 col-form-label">Date and time when articles(s) last seen</label>
            <input type="datetime-local" class="form-control " name="articles_last_seen" value="{{ $mobileAndElectDev->articles_last_seen }}"  placeholder="Date and time when articles(s) last seen">
        </div>
    </div> --}}
</div>
<div class="row">
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">By whom last seen and where?</label>
            <input type="text" class="form-control" name="whom_last_seen_and_where" value="{{ $mobileAndElectDev->whom_last_seen_and_where }}"  placeholder="By whom last seen and where?">
        </div>
    </div> --}}
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input" class="col-3 col-form-label">When were the police notified</label>
            <input type="datetime-local" class="form-control" name="when_police_notified" value="{{ $mobileAndElectDev->when_police_notified }}"  placeholder="When were the police notified">
        </div>
    </div> --}}
</div>
{{-- <div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Name of police station?</label>
            <input type="text" class="form-control" name="police_station_name" value="{{ $mobileAndElectDev->police_station_name }}"  placeholder="Name of police station">
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input" class="col-3 col-form-label">Attending officer name</label>
            <input type="text" class="form-control" name="officer_name" value="{{ $mobileAndElectDev->officer_name }}"  placeholder="Attending officer name">
        </div>
    </div>
</div> --}}
{{-- <div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Report number</label>
            <input type="text" class="form-control" name="report_number" value="{{ $mobileAndElectDev->report_number }}"  placeholder="Report number">
        </div>
    </div>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Has a thorough search been made for the article(s)</label>
        <div class="col-8">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="thorough_search_made_for_article"  {{ $mobileAndElectDev->thorough_search_made_for_article == 1 ? 'checked' : '' }}  class="form-control" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="thorough_search_made_for_article" {{ $mobileAndElectDev->thorough_search_made_for_article == 0 ? 'checked' : '' }} class="form-control"
                        value="0">No<span></span>
            </label>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-12">
        <div class="form-group">
            <label for="example-text-input">Have you ever before sustained previous</label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="loss_cause" {{ $mobileAndElectDev->loss_cause == 1 ? 'checked' : '' }}  class="form-control condition lossCause" value="1">Loss by theft?<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="loss_cause" {{ $mobileAndElectDev->loss_cause == 0 ? 'checked' : '' }} class="form-control  condition lossCause"
                        value="0">Loss of, or damage to, any article of value from any other cause?(if so, please state particulars)<span></span>
            </label>
        </div>
    </div>
</div>
<div class="row" id="otherCauseInput" style='display:none'>
    <div class="col-lg-8">
        <div class="form-group">
            <label for="example-text-input">Other cause</label>
            <input type="text" class="form-control" name="loss_by_other_cause" value="{{ $mobileAndElectDev->loss_by_other_cause }}"  placeholder="Other cause">
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Is the property for which you are claiming insured against Burglary, Theft Loss or Damage, with any other Company or underwriter?</label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="property_insured_against"  {{ $mobileAndElectDev->property_insured_against == 1 ? 'checked' : '' }}  class="form-control property_insured_against" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="property_insured_against" {{ $mobileAndElectDev->property_insured_against == 0 ? 'checked' : '' }} class="form-control property_insured_against"
                        value="0">No<span></span>
            </label>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input" class="col-3 col-form-label">Is pre inspection images uploaded?</label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="preinspection_images_uploaded"  {{ $mobileAndElectDev->preinspection_images_uploaded == 1 ? 'checked' : '' }}  class="form-control preinspection_images_uploaded" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="preinspection_images_uploaded" {{ $mobileAndElectDev->preinspection_images_uploaded == 0 ? 'checked' : '' }} class="form-control preinspection_images_uploaded"
                        value="0">No<span></span>
            </label>
        </div>
    </div>
</div> --}}
<div class="row">
    <div class="col-lg-12">
        <div class="form-group">
            <label for="example-text-input" class="col-3 col-form-label"> Are you the sole owner of the property? If not give the
                name of the owner</label>
                <label class="kt-radio" style="margin-left: 10px;">
                    <input type="radio" name="is_sole_owner_of_property"  {{ $mobileAndElectDev->is_sole_owner_of_property == 1 ? 'checked' : '' }}  class="form-control soleOwner" value="1">Yes<span></span>
                </label>
                <label class="kt-radio" style="margin-left: 10px;">
                    <input type="radio" name="is_sole_owner_of_property" {{ $mobileAndElectDev->is_sole_owner_of_property == 0 ? 'checked' : '' }}  class="form-control soleOwner"
                            value="0">No<span></span>
                </label>
        </div>
    </div>
</div>
<div class="row" id="soleOwnerDiv" style='display:none'>
    <div class="col-lg-8">
        <div class="form-group">
            <label for="example-text-input" class="col-3 col-form-label">Name of the owner</label>
            <input type="text" class="form-control" name="sole_owner_of_property" value="{{ $mobileAndElectDev->sole_owner_of_property }}"  placeholder="Name of the owner">
        </div>
    </div>
</div>

