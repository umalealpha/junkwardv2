
<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label>Date of Damage</label>
            <input id="incidentDate" class="form-control kt_datepicker_1" type="text" name="date_of_damage" @if($claimVehicle) value="{!! \Carbon\Carbon::parse($claimVehicle->date_of_damage)->format('Y-m-d') !!}" @endif @if(!$claims) required @endif autocomplete="off">
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-group" >
            <label style="margin-left: 130px;">Damage Extent</label>
            <div class="kt-radio-inline"  style="margin-left: 130px;">
                <label class="kt-radio" style="margin-top: 5px">
                    <input type="radio" name="extent" value="Cracked" @if($claimVehicle->damage_extent == 'Cracked') checked @endif>
                    Cracked <span></span>
                </label>
                <label class="kt-radio">
                    <input type="radio" name="extent" value="Shattered" @if($claimVehicle->damage_extent == 'Shattered') checked @endif>
                    Shattered <span></span>
                </label>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label>Cause of Damage</label>
            <div class="form-group form-group-last">
                <textarea class="form-control" name="cause" id="cause" rows="3" required>@if($claimVehicle){!! $claimVehicle->damage_cause !!}@endif</textarea>
            </div>
        </div>
    </div>
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <div class="form-group">
                <label for="exampleSelect1">Date of claim registered</label>
                <input type="text" class="form-control  kt_datepicker_1" value="{!! \Carbon\Carbon::parse($claims->registered_claim)->format('Y-m-d') !!}"  placeholder="Please select date" name="registered_claim" autocomplete="off">
            </div>
        </div>
    </div> --}}
</div>
<div class="row">
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <label>Replacement Estimate</label>
            <div class="form-group form-group-last">
                <textarea class="form-control" name="replacement_estimate" id="replacement_estimate" rows="3"></textarea>
            </div>
        </div>
    </div> --}}
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <div class="form-group">
                <label for="exampleSelect1">Address where glass is situated</label>
                <input type="text" class="form-control"   placeholder="Please enter address where glass is situated" name="situated_glass_address" autocomplete="off">
            </div>
        </div>
    </div> --}}
</div>
{{-- <div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label>Size of the plate broken</label>
            <div class="form-group form-group-last">
                <input type="text" class="form-control" name="broken_plate_size"  placeholder="Enter size of the plate broken">
            </div>
        </div>
    </div>
</div>
<hr> --}}

{{-- <h3>Insured Details</h3>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Name of Insured</label>
    <div class="col-9">
        <input type="text" class="form-control" id="name_of_insured" name="name_of_insured" placeholder="Please enter name of insured" autocomplete="off" title="Please provide name of insured">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Address</label>
    <div class="col-9">
        <input type="text" class="form-control" id="insured_address" name="insured_address" placeholder="Please enter address" autocomplete="off" title="Please provide address">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Occupation</label>
    <div class="col-9">
        <input type="text" class="form-control" id="insured_occupation" name="insured_occupation" placeholder="Please enter occupation" autocomplete="off" title="Please provide occupation">
    </div>
</div>

<hr> --}}

{{-- <h3>Vehicle Details</h3>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Registration Number</label>
    <div class="col-9">
        <input type="text" class="form-control" name="vehicle_plate"  placeholder="Enter vehicle registration number">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Is Imported?</label>
    <div class="col-9">
        <select class="form-control kt_selectpicker select" required name="is_imported"
                    title="Please select type" data-live-search="true" id="is_imported">
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Make</label>
    <div class="col-9">
        <select class="form-control make kt_selectpicker"
                title="Please choose make" data-live-search="true"
                name="make" id="make_rerate">
            @foreach($vehicle_make as $vehicleMake)
                @if ($vehicle->is_imported == 'Yes' || $vehicle->is_imported == 1)
                    <option value="{{ $vehicleMake->s_Make }}">
                        {{ ucwords(strtoupper($vehicleMake->s_Make)) }}</option>
                @endif
                @if ($vehicle->is_imported == 'No' || $vehicle->is_imported == 0)
                    <option value="{{ $vehicleMake }}">
                        {{ ucwords(strtoupper($vehicleMake)) }}</option>
                @endif
            @endforeach

        </select>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Manufacturing Year</label>
    <div class="col-9">
        <select class="form-control kt_selectpicker" required name="year"
            title="Please select manufacturing year" data-live-search="true"
            id="year_rerate">
            @foreach ($years as $key => $year)
                <option value="{{ $year }}">
                    {{ $year }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Model</label>
    <div class="col-9">
        <select class="form-control kt_selectpicker" required name="model"
            title="Please select model" data-live-search="true"
            id="model_rerate">
            @if ($vehicle->is_imported == 'Yes' || $vehicle->is_imported == 1)
                    @foreach ($vehicle_model as $key => $model)
                        <option value="{{ $model['model'] }}">
                            {{ ucwords(strtoupper($model['model'])) }}</option>
                    @endforeach
                @endif

                @if ((is_array($vehicle_model) && $vehicle->is_imported == 'No') || $vehicle->is_imported == 0)
                    @foreach ($vehicle_model as $key => $model)
                        <option value="{{ $model['Model'] }}">
                            {{ ucwords(strtoupper($model['Model'])) }}</option>
                    @endforeach
                @endif
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Purpose Of Use</label>
    <div class="col-9">
        <select class="form-control kt_selectpicker" required name="purpose_use"
            title="Please select purpose of use" data-live-search="true"
            id="purpose_use">
            <option value="">Please Select</option>
            <option value="1">BUSINESS</option>
            <option value="2">PRIVATE</option>
            <option value="3">FINANCIAL INTEREST</option>
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Chassis Number</label>
    <div class="col-9">
        <input type="text" class="form-control" name="chassisNo"  placeholder="Enter vehicle chassis number">
    </div>
</div>
<div class="form-group row">
    <label for="example-text-input" class="col-3 col-form-label">Type Of Glass</label>
    <div class="col-9">
        <input type="text" class="form-control" name="type_of_glass"  placeholder="Enter vehicle type of glass">
    </div>
</div> --}}

<hr>
<!--begin::Portlet-->

{{-- <div class="kt-portlet">
    <div class="kt-invoice-v2">
        <div class="kt-invoice-v2__header grid"> --}}
            {{-- <div class="kt-invoice-v2__header-right">
                <div class="kt-invoice-v2__logo thumb"> --}}
                    {{-- <h3>Before</h3>
                    <div class="row">
                        <div class="col-lg-3" >
                            <h3 class="col-form-label" style="float: left;">Front Side</h3>
                            <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                @if($vehicle && $vehicle->front != NULL)
                                    <img style="height:72.5%" src="{{ \AlphaDirect\Helper::getCloudFrontURL($policy->policyDocument) }}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                @else
                                    <img style="height:72.5%" src="http://placehold.it/300x300" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                @endif
                            </a>
                        </div>
                        <div class="col-lg-3">
                            <h3 class="col-form-label" style="float: left;">Back Side</h3>
                            <a class="thumbnail" href="#" id="backViewModal" data-image-id="backView" data-toggle="modal" data-title="This is my title" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                @if($vehicle && $vehicle->back != NULL)
                                <img style="height:72.5%" src="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->back) }}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                @else
                                    <img  style="height:72.5%" src="http://placehold.it/300x300" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                @endif
                            </a>
                        </div>
                        <div class="col-lg-3">
                            <h3 class="col-form-label" style="float: left;">Right Side</h3>
                            <a class="thumbnail" href="#" id="rightViewModal" data-image-id="rightView" data-toggle="modal" data-title="This is my title" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                @if($vehicle && $vehicle->right != NULL)
                                <img style="height:72.5%" src="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->right) }}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                @else
                                    <img style="height:72.5%" src="http://placehold.it/300x300" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                @endif
                            </a>

                        </div>
                        <div class="col-lg-3">
                            <h3 class="col-form-label" style="float: left;">Left Side</h3>
                            <a class="thumbnail" href="#" id="leftViewModal" data-image-id="leftView" data-toggle="modal" data-title="This is my title" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                @if($vehicle && $vehicle->left != NULL)
                                <img style="height:72.5%" src="{{ \AlphaDirect\Helper::getCloudFrontURL($vehicle->left) }}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                @else
                                    <img style="height:72.5%" src="http://placehold.it/300x300" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                @endif
                            </a>
                        </div>
                    </div> --}}
                {{-- </div>
            </div> --}}
            <div id="myModal" class="modal">
                <span class="close" id="close">&times;</span>
                <img class="modal-content" id="img01">
                {{-- <div id="caption"></div>--}}
            </div>


            <div class="kt-invoice-v2__header-left">
                <div class="kt-invoice-v2__logo thumb">
                    <h3>After</h3>
                    <div class="row">
                        <div class="col-md-3">
                            <h3 class="col-form-label" style="float: left;">Front Side</h3>
                            <div class="kt-avatar" id="incidentFront" style="float: left; clear: left;">
                                @if($claimVehicle->front_image == NULL)
                                    <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                @else
                                    <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($claimVehicle->front_image) !!})"></div>
                                @endif
                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                    <i class="fa fa-pen"></i>
                                    <input type='file' class="incidentFront" name="incidentFront" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                </label>
                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                            </div>
                            <textarea class="form-control"  style="margin-top: 170px;" placeholder="Add Description" name="front_image_description" id="front_image_description">{!! $claimVehicle->front_image_description !!}</textarea>
                        </div>
                        <div class="col-md-3">
                            <h3 class="col-form-label" style="float: left;">Back Side</h3>
                            <div class="kt-avatar" id="incidentBack" style="float: left; clear: left;">
                                @if($claimVehicle->back_image == NULL)
                                    <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                @else
                                    <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($claimVehicle->back_image) !!})"></div>
                                @endif
                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                    <i class="fa fa-pen"></i>
                                    <input type='file' class="incidentBack" name="incidentBack" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                </label>
                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                            </div>
                            <textarea  style="margin-top: 170px;" class="form-control" placeholder="Add Description" name="back_image_description" id="back_image_description">{!! $claimVehicle->back_image_description !!}</textarea>

                        </div>

                        <div class="col-md-3">
                            <h3 class="col-form-label" style="float: left;">Right Side</h3>
                            <div class="kt-avatar" id="incidentRight" style="float: left; clear: left;">
                                @if($claimVehicle->right_image == NULL)
                                    <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                @else
                                    <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($claimVehicle->right_image) !!})"></div>
                                @endif
                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                    <i class="fa fa-pen"></i>
                                    <input type='file' class="incidentRight" name="incidentRight" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                </label>
                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                            </div>
                            <textarea class="form-control"  style="margin-top: 170px;" placeholder="Add Description" name="right_image_description" id="right_image_description">{!! $claimVehicle->right_image_description !!}</textarea>
                        </div>

                        <div class="col-md-3">
                            <h3 class="col-form-label" style="float: left;">Left Side</h3>
                            <div class="kt-avatar" id="incidentLeft" style="float: left; clear: left;">
                                @if($claimVehicle->left_image == NULL)
                                    <div class="kt-avatar__holder" style="background-image:url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                                @else
                                    <div class="kt-avatar__holder" style="background-image:url({!! \AlphaDirect\Helper::getCloudFrontURL($claimVehicle->left_image) !!})"></div>
                                @endif
                                <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="Change Image">
                                    <i class="fa fa-pen"></i>
                                    <input type='file' class="incidentLeft" name="incidentLeft" <?php echo config('app.accept_attr'); ?> <?php echo config('app.accept_msg'); ?> />
                                </label>
                                <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                            </div>
                            <textarea class="form-control"  style="margin-top: 170px;" placeholder="Add Description" name="left_image_description" id="left_image_description">{!! $claimVehicle->left_image_description !!}</textarea>
                        </div>
                    </div>
                    {{-- @if($policy->kyc_recipient)
                        @include('admin.policy.recipient_kyc')
                    @endif --}}
                    {{-- <div class="form-group row" style="padding-top:40px; padding-left: 20px;">
                        <h4 class="col-md-3 col-form-label">Does customer selected supplier?</h4>
                        <div class="col-md-3">
                                <span class="kt-switch" >
                                    <label>
                                    <input id="vehicleValue" type="checkbox" id="customer_selected" name="customer_selected" value="1"
                                           onchange="vehicleMsg()" @if($claims->customer_selected) checked @endif>
                                            <span></span>
                                        <h4 id="Msg" style="display:inline;float:left;margin-top: 9px;margin-left: 5px;">@if($claims->customer_selected) Yes @else No @endif</h4>
                                    </label>
                                </span>
                        </div>

                    </div>

                    <div class="kt-portlet__body" id="sup" @if(!$claims->customer_selected)style="display: none;"@endif>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Supplier Name:</label>
                            <div class="col-9">
                                <input class="form-control" name="sname"  id="sname" placeholder="Enter Supplier Name" title="Supplier Name is required" @if($supplier) value="{!! $supplier->supplierName !!}" @endif>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Supplier Type:</label>
                            <div class="col-9">
                                <select name="stype"  class="form-control kt_selectpicker"  id="stype" title="Select supplier type" data-live-search="true">
                                    @foreach($supplierTypes as $supplierType)
                                        <option value="{{ $supplierType->value }}" @if($supplier && $supplierType->value == $supplier->supplierType) selected @endif>{{ $supplierType->value }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">VAT No. :</label>
                            <div class="col-9">
                                <input  class="form-control"  id="vat"  name="vat" @if($supplier) value="{!! $supplier->vat_no !!}" @endif placeholder="Enter VAT Number" title="Supplier VAT No. is required">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Supplier Contact Name:</label>
                            <div class="col-9">
                                <input type="text" class="form-control" id="snumber"  name="snumber" @if($supplier) value="{!! $supplier->telephone !!}" @endif  placeholder="Enter Contact Number" title="Supplier contact number is required">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Supplier Email:</label>
                            <div class="col-9">
                                <input type="text" class="form-control"  id="semail" name="semail" @if($supplier) value="{!! $supplier->email !!}" @endif placeholder="Enter Supplier Email" title="Supplier Email address is required">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Supplier Location:</label>
                            <div class="col-9">
                                <input type="text" class="form-control"  id="slocation" name="slocation" @if($supplier) value="{!! $supplier->supplierLocation !!}" @endif placeholder="Enter Supplier Location" title="Supplier location is required">
                            </div>
                        </div>
                    </div> --}}
                </div>
            </div>
        {{-- </div>
    </div>
</div> --}}
<!--end::Portlet-->
{{-- <div class="kt-portlet__foot kt-portlet__foot--solid">
    <div class="kt-form__actions">
        <div class="row">
            <div class="col-3"></div>
            <div class="col-9">
                <button type="submit"  value="Submit" class="btn btn-brand">Submit</button>
                <a class="btn btn-secondary" href="{{ route('admin.policy.index') }}" >Cancel</a>
            </div>
        </div>
    </div>
</div> --}}


