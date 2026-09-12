<input type="hidden" name="goods_in_transit_id" value="{{ $goodsInTransit->id }}" />
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Address of premises where loss occurred</label>
    <div class="col-8">
        <textarea type="text" class="form-control" name="address_of_premises_loss" value=""  placeholder=" Address of premises where loss occurred.">{{$goodsInTransit->address_of_premises_loss}}</textarea>
    </div>

</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Details of the carrier/ driver</label>
    <div class="col-8">
        <textarea type="text" class="form-control" name="details_of_driver" value=""  placeholder="Details of the carrier/ driver">{{$goodsInTransit->details_of_driver}}</textarea>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">When was the property last seen by you?</label>
    <div class="col-8">
        <input type="text" class="form-control" name="property_last_seen" value="{{$goodsInTransit->property_last_seen}}"  placeholder="When was the property last seen by you?">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Date and time of loss.</label>
    <div class="col-8">
        <input type="datetime-local" class="form-control " name="date_time_of_loss" value="{{$goodsInTransit->date_time_of_loss}}"  placeholder="Date and time of loss.">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Brief description of incident</label>
    <div class="col-8">
        <textarea type="text" class="form-control" name="brief_description_incident" value=""  placeholder="Brief description of incident">{{$goodsInTransit->brief_description_incident}}</textarea>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Date and time the police were advised of loss.</label>
    <div class="col-8">
        <input type="datetime-local" class="form-control " name="date_time_police_advised" value="{{$goodsInTransit->date_time_police_advised}}"  placeholder="Date and time the police were advised of loss.">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name of police station</label>
    <div class="col-8">
        <input type="text" class="form-control" name="police_station_name" value="{{$goodsInTransit->police_station_name}}"  placeholder="Name of police station">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Names of witnesses</label>
    <div class="col-8">
        <input type="text" class="form-control" name="witnesses_name" value="{{$goodsInTransit->witnesses_name}}"  placeholder="Names of witnesses">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Witnesses mobile number</label>
    <div class="col-8">
        <input type="text" class="form-control" name="witnesses_mobile_number" value="{{$goodsInTransit->witnesses_mobile_number}}"  placeholder="Witnesses mobile number">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">What is the total value of the loss?</label>
    <div class="col-8">
        <input type="text" class="form-control" name="total_value_of_loss" value="{{$goodsInTransit->total_value_of_loss}}"  placeholder="What is the total value of the loss?">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Where was the consignment being transported to?</label>
    <div class="col-8">
        <input type="text" class="form-control" name="consignment_transported_to" value="{{$goodsInTransit->consignment_transported_to}}"  placeholder="Where was the consignment being transported to?">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Where was the consignment from?</label>
    <div class="col-8">
        <input type="text" class="form-control" name="consignment_from" value="{{$goodsInTransit->consignment_from}}"  placeholder="Where was the consignment from?">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Registration number of the vehicle carrying the good?</label>
    <div class="col-8">
        <input type="text" class="form-control" name="vehicle_registration_number" value="{{$goodsInTransit->vehicle_registration_number}}"  placeholder="Registration number of the vehicle carrying the good?">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Is the carrier contracted?</label>
    <div class="col-8">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="is_carrier_contracted" {{ $goodsInTransit->is_carrier_contracted == 1 ? 'checked' : '' }} class="form-control condition is_carrier_contracted" value="1">Yes<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="is_carrier_contracted" {{ $goodsInTransit->is_carrier_contracted == 0 ? 'checked' : '' }} class="form-control  condition is_carrier_contracted"
                    value="0">No<span></span>
        </label>
    </div>
</div>

<div id="is_carrier_contracted_div" style='display:none'>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Please provide a copy of the contract.</label>
        <div class="col-8">
            <div class="kt-avatar" id="copy_of_contract" style="float: left; clear: left;">
                <div class="kt-avatar" id="copy_of_contract"
                    style="float: left; clear: left;">
                    @if (!isset($goodsInTransit->copy_of_contract))
                        <div class="kt-avatar__holder"
                            style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                        </div>
                    @else
                        <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($goodsInTransit->copy_of_contract) !!}"
                            target="_blank" download>
                            <div class="kt-avatar__holder"
                                style="background-image:url('{!! \AlphaDirect\Helper::getCloudFrontURL($goodsInTransit->copy_of_contract) !!}')">
                            </div>
                        </a>
                    @endif
                    <label class="kt-avatar__upload"
                        data-toggle="kt-tooltip"
                        title="copy_of_contract 11">
                        <i class="fa fa-pen"></i>
                        <input type='file' class="copy_of_contract"
                            name="copy_of_contract"
                            <?php echo config('app.accept_attr'); ?>
                            <?php echo config('app.accept_msg'); ?> />
                        </label>
                    <span class="kt-avatar__cancel"
                        data-toggle="kt-tooltip" title="Cancel Image">
                        <i class="fa fa-times"></i>
                    </span>
                </div>
                {{-- @if($goodsInTransit->copy_of_contract == NULL)
                    <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                @else
                    <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($goodsInTransit->copy_of_contract) !!}" target="_blank" download>
                        <img src="{{\AlphaDirect\Helper::getCloudFrontURL($goodsInTransit->copy_of_contract)}}" width="100%" height="auto" >
                    </a>
                @endif --}}
                {{-- <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Please provide a copy of the contract">
                    <i class="fa fa-pen"></i>
                    <input type='file' class="form-control" name="copy_of_contract" value="" id="copy_of_contract" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                </label>
                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span> --}}
            </div>
            {{-- <input type="file" class="form-control" name="copy_of_contract" value=""  placeholder="Please provide a copy of the contract."> --}}
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Does the carrier have their own GIT insurance</label>
    <div class="col-8">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="carrier_has_own_GIT_ins" {{ $goodsInTransit->carrier_has_own_GIT_ins == 1 ? 'checked' : '' }} class="form-control" value="1">Yes<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="carrier_has_own_GIT_ins" {{ $goodsInTransit->carrier_has_own_GIT_ins == 0 ? 'checked' : '' }} class="form-control"
                    value="0">No<span></span>
        </label>
    </div>
</div>

<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Are there any other Insurances against theft upon the same property?</label>
    <div class="col-8">
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="other_insurance_against_theft" {{ $goodsInTransit->other_insurance_against_theft == 1 ? 'checked' : '' }} class="form-control other_insurance_against_theft" value="1">Yes<span></span>
        </label>
        <label class="kt-radio" style="margin-left: 10px;">
            <input type="radio" name="other_insurance_against_theft" {{ $goodsInTransit->other_insurance_against_theft == 0 ? 'checked' : '' }} class="form-control other_insurance_against_theft"
                    value="0">No<span></span>
        </label>
    </div>
</div>
<div id="other_insurance_against_theft_div" style='display:none'>
    <div class="form-group row">
        <label for="example-text-input" class="col-3 col-form-label">Give details in brief</label>
        <div class="col-8">
            <textarea type="text" class="form-control" name="insurance_against_theft_details" value=""  placeholder="Give details in brief">{{$goodsInTransit->insurance_against_theft_details}}</textarea>
        </div>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Give details of records of previous loss in the premises or any other premises on similar goods</label>
    <div class="col-8">
        <input type="text" class="form-control" name="details_of_previous_loss_records" value="{{$goodsInTransit->details_of_previous_loss_records}}"  placeholder="Give details of records of previous loss in the premises or any other premises on similar goods">
    </div>
</div>

