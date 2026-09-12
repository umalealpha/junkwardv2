<input type="hidden" name="burglary_id" value="{{ $burglary->id ?? '' }}" />
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
            <label for="example-text-input">Address of premises where theft occurred.</label>
            <textarea type="text" class="form-control" name="address_of_premises" value=""  placeholder=" Address of premises where theft occurred.">{{ $burglary->address_of_premises ?? '' }}</textarea>
        </div>
    </div>
</div>

<div class="row">
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Brief description of incident</label>
            <textarea type="text" class="form-control" name="description_of_incident" value=""  placeholder="Brief description of incident">{{ $burglary->description_of_incident }}</textarea>
        </div>
    </div> --}}
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Date the police were advised of loss.</label>
            <input type="date" class="form-control" name="date_time_police_advised" value="{{ isset($burglary->date_time_police_advised) && $burglary->date_time_police_advised ? \Carbon\Carbon::parse($burglary->date_time_police_advised)->format('Y-m-d') : '' }}"  placeholder="Date the police were advised of loss.">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="form-group">
            <label for="example-text-input">Was anyone at the premises during the burglary? If yes, provide details</label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="anyone_on_premises" {{ isset($burglary) && $burglary->anyone_on_premises == 1 ? 'checked' : '' }}  class="form-control condition anyone_on_premises" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="anyone_on_premises" {{ isset($burglary) && $burglary->anyone_on_premises == 0 ? 'checked' : '' }} class="form-control  condition anyone_on_premises"
                        value="0">No<span></span>
            </label>
        </div>
    </div>
</div>
<div class="row" id="detailsInBriefInput" style='display:none'>
    <div class="col-lg-8">
        <div class="form-group">
            <label for="example-text-input">Details in brief</label>
            <input type="text" class="form-control" name="anyone_on_premises_brief" value="{{ $burglary->anyone_on_premises_brief ?? '' }}"  placeholder="Details in brief">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Is the premises guarded by a watchman?</label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="guarded_by_watchman"  {{ isset($burglary) && $burglary->guarded_by_watchman == 1 ? 'checked' : '' }}  class="form-control" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="guarded_by_watchman" {{ isset($burglary) && $burglary->guarded_by_watchman == 0 ? 'checked' : '' }} class="form-control"
                        value="0">No<span></span>
            </label>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Were all means of access of the premises properly secured at the time of the theft?</label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="premises_properly_secured"  {{ isset($burglary) && $burglary->premises_properly_secured == 1 ? 'checked' : '' }}  class="form-control" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="premises_properly_secured" {{ isset($burglary) && $burglary->premises_properly_secured == 0 ? 'checked' : '' }}  class="form-control"
                        value="0">No<span></span>
            </label>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">What was the total value of the contents of your premises at the time of the theft?</label>
            <textarea type="text" class="form-control" name="total_value_contents_of_premises" value=""  placeholder="What was the total value of the contents of your premises at the time of the theft?">{{ $burglary->total_value_contents_of_premises ?? '' }}</textarea>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">State where the stock books and records were located at the time of the theft.</label>
            <input type="text" class="form-control" name="stock_books_records_located" value="{{ $burglary->stock_books_records_located ?? '' }}"  placeholder="State where the stock books and records were located at the time of the theft.">
        </div>
    </div>
</div>
