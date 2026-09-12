<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Insured’s Name</label>
    <div class="col-8">
        <input type="text" class="form-control" name="insured_name" value=""  placeholder="Insured’s Name">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">E-mail Address</label>
    <div class="col-8">
        <input type="email" class="form-control" name="email_address" value=""  placeholder="E-mail Address">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Address</label>
    <div class="col-8">
        <input type="text" class="form-control" name="address" value=""  placeholder="Address">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Telephone No.</label>
    <div class="col-8">
        <input type="text" class="form-control" name="telephone_no" value=""  placeholder="Telephone No.">
    </div>
</div>

    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Has the property been stolen or damaged?</label>
        <div class="col-8">
            <label class="kt-radio" style="margin-left: 10px;">
                {{-- <input type="text" class="form-control" name="property_stolen_damaged" value=""  placeholder="Has the property been stolen or damaged?"> --}}
                <input type="radio" name="property_stolen_damaged"  checked  class="form-control propertyStolenDamaged" value="1">Damaged<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="property_stolen_damaged"  class="form-control propertyStolenDamaged"
                        value="0">Stolen<span></span>
            </label>
        </div>
    </div>
    {{-- <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Address of the premises or place, where loss or damage occurred (If lost) from premises please state use of premises)</label>
        <div class="col-8">
            <textarea type="text" class="form-control" name="premises_address" value=""  placeholder="Address of the premises or place, where loss or damage occurred (If lost) from premises please state use of premises)"></textarea>
        </div>
    </div> --}}
    {{-- <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Full particulars of circumstances of the loss or damage</label>
        <div class="col-8">
            <textarea type="text" class="form-control" name="circumstances_loss_damage" value=""  placeholder="Full particulars of circumstances of the loss or damage"></textarea>
        </div>
    </div> --}}
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Date and time when loss or damage was discovered</label>
        <div class="col-8">
            <input type="datetime-local" class="form-control " name="date_time_loss_discovered" value=""  placeholder="Date and time when loss or damage was discovered">
        </div>
    </div>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">By whom discovered?</label>
        <div class="col-8">
            <input type="text" class="form-control" name="whom_discovered" value=""  placeholder="By whom discovered?">
        </div>
    </div>
    {{-- <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Date and time when articles(s) last seen</label>
        <div class="col-8">
            <input type="datetime-local" class="form-control " name="articles_last_seen" value=""  placeholder="Date and time when articles(s) last seen">
        </div>
    </div> --}}
    {{-- <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">By whom last seen and where?</label>
        <div class="col-8">
            <input type="text" class="form-control" name="whom_last_seen_and_where" value=""  placeholder="By whom last seen and where?">
        </div>
    </div> --}}
    {{-- <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">When were the police notified</label>
        <div class="col-8">
            <input type="datetime-local" class="form-control" name="when_police_notified" value=""  placeholder="When were the police notified">
        </div>
    </div> --}}
    {{-- <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Name of police station</label>
        <div class="col-8">
            <input type="text" class="form-control" name="police_station_name" value=""  placeholder="Name of police station">
        </div>
    </div>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Attending officer name</label>
        <div class="col-8">
            <input type="text" class="form-control" name="officer_name" value=""  placeholder="Attending officer name">
        </div>
    </div> --}}
    {{-- <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Report number</label>
        <div class="col-8">
            <input type="text" class="form-control" name="report_number" value=""  placeholder="Report number">
        </div>
    </div>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Has a thorough search been made for the article(s)</label>
        <div class="col-8">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="thorough_search_made_for_article"  checked  class="form-control" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="thorough_search_made_for_article"  class="form-control"
                        value="0">No<span></span>
            </label>
        </div>
    </div>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Have you ever before sustained previous</label>
        <div class="col-8">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="loss_cause"  checked  class="form-control condition lossCause" value="1">Loss by theft?<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="loss_cause"  class="form-control  condition lossCause"
                        value="0">Loss of, or damage to, any article of value from any other cause?(if so, please state particulars)<span></span>
            </label>
        </div>
    </div>

    <div id="otherCauseInput" style='display:none'>
        <div class="form-group row">
            <label for="example-text-input" class="col-3 col-form-label">Other cause</label>
            <div class="col-8">
                <input type="text" class="form-control" name="loss_by_other_cause" value=""  placeholder="Other cause">
            </div>
        </div>
    </div>

    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Is the property for which you are claiming insured against Burglary, Theft Loss or Damage, with any other Company or underwriter?</label>
        <div class="col-8">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="property_insured_against"  checked  class="form-control property_insured_against" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="property_insured_against"  class="form-control property_insured_against"
                        value="0">No<span></span>
            </label>
        </div>
    </div>

    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Is pre inspection images uploaded?</label>
        <div class="col-8">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="preinspection_images_uploaded"  checked  class="form-control preinspection_images_uploaded" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="preinspection_images_uploaded"  class="form-control preinspection_images_uploaded"
                        value="0">No<span></span>
            </label>
        </div>
    </div> --}}
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label"> Are you the sole owner of the property? If not give the
            name of the owner</label>
        <div class="col-8">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="is_sole_owner_of_property"  checked  class="form-control soleOwner" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="is_sole_owner_of_property"  class="form-control soleOwner"
                        value="0">No<span></span>
            </label>
        </div>
    </div>
    <div class="form-group row" id="soleOwnerDiv" style="display: none;">
        <label for="example-text-input" class="col-3 col-form-label">Name of the owner</label>
        <div class="col-8">
            <input type="text" class="form-control" name="sole_owner_of_property" value=""  placeholder="Name of the owner">
        </div>
    </div>
