<table class="table table-striped m-table">
    <tbody>
    <tr>
        <th>Date of Damage</th>
        <td>{{$claimVehicle->date_of_damage}}</td>
        <th>Damage Extent</th>
        <td>{{$claimVehicle->damage_extent}}</td>
    </tr>

    <tr>
        <th>Cause of Damage</th>
        <td>
            {{$claimVehicle->damage_cause}}
        </td>
        {{-- <th>Date Of Claim Registered</th>
        @if ($claims->registered_claim)
            <td>{!! $claims->registered_claim !!}</td>
        @else
            <td>N/A</td>
        @endif --}}
    </tr>

    <tr>
        <th>Claim Number</th>
        <td>{!! $claims->claim_number !!}</td>
    </tr>

    </tbody>
</table>
<table class="table table-striped m-table">
    <tbody>
    {{-- <tr>
        <td>
            <br/><br/>
            <h3>Before</h3>
            <div class="row col-lg-15">
                <div class="col-lg-2" >
                    <h3 class="col-form-label" style="float: left;">Front Side</h3>
                    <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicle->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                        @if($vehicle->front != NULL)
                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->front) !!}" target="_blank" download>
                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->front)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                            </a>
                        @else
                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                        @endif
                    </a>
                </div>
                <div class="col-lg-2">
                    <h3 class="col-form-label" style="float: left;">Back Side</h3>
                    <a class="thumbnail" href="#" id="backViewModal" data-image-id="backView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicle->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                        @if($vehicle->back != NULL)
                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->back) !!}" target="_blank" download>
                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->back)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                            </a>
                        @else
                            <img  src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                        @endif
                    </a>
                </div>
                <div class="col-lg-2">
                    <h3 class="col-form-label" style="float: left;">Right Side</h3>
                    <a class="thumbnail" href="#" id="rightViewModal" data-image-id="rightView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicle->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                        @if($vehicle->right != NULL)
                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->right) !!}" target="_blank" download>
                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->right)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                            </a>
                        @else
                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                        @endif
                    </a>

                </div>
                <div class="col-lg-2">
                    <h3 class="col-form-label" style="float: left;">Left Side</h3>
                    <a class="thumbnail" href="#" id="leftViewModal" data-image-id="leftView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicle->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                        @if($vehicle->left != NULL)
                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->left) !!}" target="_blank" download>
                            <img  src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->left)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                            </a>
                        @else
                            <img  src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                        @endif
                    </a>
                </div>

                <div class="col-lg-2">
                    <h3 class="col-form-label" style="float: left;">Vehicle Registration</h3>
                    <a class="thumbnail" href="#" id="leftViewModal" data-image-id="leftView" data-toggle="modal" data-title="This is my title" data-caption="{{$vehicle->make}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                        @if($vehicle->vehicleRegistration != NULL)
                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicleRegistration) !!}" target="_blank" download>
                            <img  src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicleRegistration)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                            </a>
                        @else
                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                        @endif
                    </a>
                </div>
            </div>
        </td>
    </tr> --}}
    <tr>
        <td>
            <br/><br/>
            <h3>After</h3>
            <div class="row">
                <div class="col-lg-3" >
                    <h3 class="col-form-label" style="float: left;">Front Side</h3>
                    <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{isset($vehicle->make) ? $vehicle->make : null}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                        @if($claimVehicle->front_image != NULL)
                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimVehicle->front_image) !!}" target="_blank" download>
                            <img style="height: auto;" src="{{\AlphaDirect\Helper::getCloudFrontURL($claimVehicle->front_image)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                            </a>
                        @else
                            <img style="height: auto;" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                        @endif
                    </a>
                    <h5>@if($claimVehicle){!! $claimVehicle->front_image_description !!}@endif</h5>
                </div>
                <div class="col-lg-3" >
                    <h3 class="col-form-label" style="float: left;">Back Side</h3>
                    <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{isset($vehicle->make) ? $vehicle->make : null}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                        @if($claimVehicle->back_image != NULL)
                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimVehicle->back_image) !!}" target="_blank" download>
                            <img src="{{\AlphaDirect\Helper::getCloudFrontURL($claimVehicle->back_image)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                            </a>
                        @else
                            <img style="height:auto" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                        @endif
                    </a>
                    <h5>@if($claimVehicle){!! $claimVehicle->back_image_description !!}@endif</h5>
                </div>
                <div class="col-lg-3" >
                    <h3 class="col-form-label" style="float: left;">Right Side</h3>
                    <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{isset($vehicle->make) ? $vehicle->make : null}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                        @if($claimVehicle->right_image != NULL)
                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimVehicle->right_image) !!}" target="_blank" download>
                            <img  src="{{\AlphaDirect\Helper::getCloudFrontURL($claimVehicle->right_image)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                            </a>
                        @else
                            <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                        @endif
                    </a>
                    <h5>@if($claimVehicle){!! $claimVehicle->right_image_description !!}@endif</h5>
                </div>
                <div class="col-lg-3" >
                    <h3 class="col-form-label" style="float: left;">Left Side</h3>
                    <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{isset($vehicle->make) ? $vehicle->make : null}} Front Windscreen" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                        @if($claimVehicle->left_image != NULL)
                            <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimVehicle->left_image) !!}" target="_blank" download>
                            <img  src="{{\AlphaDirect\Helper::getCloudFrontURL($claimVehicle->left_image)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                            </a>
                        @else
                            <img style="height:auto" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                        @endif
                    </a>
                    <h5>@if($claimVehicle){!! $claimVehicle->left_image_description !!}@endif</h5>
                </div>
            </div>
        </td>
    </tr>
    </tbody>
</table>
{{-- <table>
    <tbody>
        @if($claims->customer_selected && $supplier != NULL)
        <div class="kt-portlet__head">
            <div class="kt-portlet__head-label">
                <h3 class="kt-portlet__head-title">
                    Supplier Details
                </h3>
            </div>
        </div>
        <div class="kt-portlet_body">
            <div class="kt-section">
                <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                    <table class="table table-striped m-table">
                        <tbody>
                        <tr>
                            <th>Supplier Name</th>
                            <td>{!! $supplier->supplierName !!}</td>
                            <th>Email</th>
                            <td>{!! $supplier->email !!}</td>
                        </tr>
                        <tr>
                            <th>Contact Number</th>
                            <td>{!! $supplier->telephone !!}</td>
                            <th>Supplier Type</th>
                            <td>{!! $supplier->supplierType !!}</td>
                        </tr>
                        <tr>
                            <th>VAT number</th>
                            <td>{!! $supplier->vat_no !!}</td>
                            <th>supplier Location</th>
                            <td>{!! $supplier->supplierLocation !!}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
    </tbody>
</table> --}}
