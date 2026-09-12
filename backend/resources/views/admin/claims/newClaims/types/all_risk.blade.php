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
        <label for="example-text-input" class="col-3 col-form-label">Full particulars of circumstances of the loss or damage</label>
        <div class="col-8">
            <textarea type="text" class="form-control" name="circumstances_loss_damage" value=""  placeholder="Full particulars of circumstances of the loss or damage"></textarea>
        </div>

    </div> --}}

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

    <div id="classStolen" style="display: none;">
        <div class="form-group row">
            <label for="example-text-input" class="col-3 col-form-label">Was the property stolen from a car or unlocked
                premises?</label>
            <div class="col-8">
                {{-- <input type="text" class="form-control" name="stolenfromcar_unlockedpremises" value=""  placeholder="Was the property stolen from a car or unlocked premises? provide details"> --}}
                <label class="kt-radio" style="margin-left: 10px;">
                    <input type="radio" name="stolenfromcar_unlockedpremises"  checked  class="form-control condition" value="1">Yes<span></span>
                </label>
                <label class="kt-radio" style="margin-left: 10px;">
                    <input type="radio" name="stolenfromcar_unlockedpremises"  class="form-control  condition"
                            value="0">No<span></span>
                </label>
            </div>
        </div>
        <div class="form-group row">
            <label for="example-text-input" class="col-3 col-form-label">Has a thorough search been made for the article(s)</label>
            <div class="col-8">
                {{-- <input type="text" class="form-control" name="thorough_search_made_for_article" value=""  placeholder="Has a thorough search been made for the article(s)"> --}}
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
            <label for="example-text-input" class="col-3 col-form-label"> Are you the sole owner of the property? If not give the
                name of the owner</label>
            <div class="col-8">
                {{-- <input type="text" class="form-control" name="sole_owner_of_property" value=""  placeholder=" Are you the sole owner of the property? If not give the name of the owner"> --}}
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
    </div>
