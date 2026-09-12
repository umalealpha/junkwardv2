
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label"> Address of premises where theft occurred.</label>
        <div class="col-8">
            <textarea type="text" class="form-control" name="address_of_premises" value=""  placeholder=" Address of premises where theft occurred."></textarea>
        </div>

    </div>
    {{-- <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Brief description of incident</label>
        <div class="col-8">
            <textarea type="text" class="form-control" name="description_of_incident" value=""  placeholder="Brief description of incident"></textarea>
        </div>
    </div> --}}
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Date the police were advised of loss.</label>
        <div class="col-8">
            <input type="date" class="form-control" name="date_time_police_advised" value=""  placeholder="Date the police were advised of loss.">
        </div>
    </div>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Was anyone at the premises during the burglary? If yes, provide details</label>
        <div class="col-8">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="anyone_on_premises" class="form-control condition anyone_on_premises" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="anyone_on_premises" class="form-control  condition anyone_on_premises"
                        value="0">No<span></span>
            </label>
        </div>
    </div>

    <div id="detailsInBriefInput" style='display:none'>
        <div class="form-group row">
            <label for="example-text-input" class="col-3 col-form-label">Details in brief</label>
            <div class="col-8">
                <input type="text" class="form-control" name="anyone_on_premises_brief" value=""  placeholder="Details in brief">
            </div>
        </div>
    </div>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Is the premises guarded by a watchman?</label>
        <div class="col-8">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="guarded_by_watchman" class="form-control" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="guarded_by_watchman" class="form-control"
                        value="0">No<span></span>
            </label>
        </div>
    </div>

    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Were all means of access of the premises properly secured at the time of the theft?</label>
        <div class="col-8">
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="premises_properly_secured" class="form-control" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="premises_properly_secured" class="form-control"
                        value="0">No<span></span>
            </label>
        </div>
    </div>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">What was the total value of the contents of your premises at the time of the theft?</label>
        <div class="col-8">
            <textarea type="text" class="form-control" name="total_value_contents_of_premises" value=""  placeholder="What was the total value of the contents of your premises at the time of the theft?"></textarea>
        </div>
    </div>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">State where the stock books and records were located at the time of the theft.</label>
        <div class="col-8">
            <input type="text" class="form-control" name="stock_books_records_located" value=""  placeholder="State where the stock books and records were located at the time of the theft.">

        </div>
    </div>

