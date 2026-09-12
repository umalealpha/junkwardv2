

<div class="tab-content" id="vehicleLabelDiv">
    <div class="tab-pane active" id="kt_portlet_base_demo_3_1_tab_content" role="tabpanel">
        <div class="kt-portlet">
            <div class="kt-portlet__head row" >
                <div class="kt-portlet__head-label col-lg-12">
                    <div class="col-lg-4">
                        <h3 class="kt-portlet__head-title">
                            Vehicle Claim Information
                        </h3>
                    </div>
                    <div class="col-lg-8">
                        @can('claim-edit')
                            @if($claimAssessmentReport && $claimAssessmentReport->assessment_report != null)
                        @if($claims->status == 'Approved' || $claims->status == 'Rejected' || $claims->status == 'Closed')
                            <button class="btn btn-brand" style="float: right" onclick="setVehicleToEdit()" hidden>Set to edit</button>
                            @else
                            <button class="btn btn-brand" style="float: right" onclick="setVehicleToEdit()">Set to edit</button>
                        @endif
                            @endif
                            @endcan
                    </div>
                </div>
            </div>

            <div class="kt-portlet_body">
                <div class="kt-section">
                    <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                        <table class="table table-striped m-table">
                            <tbody>
                            <tr>
                                <th>Date of Damage</th>
                                <td>{!! \Carbon\Carbon::parse($claimVehicle->date_of_damage)->format('Y-m-d') !!}</td>
                                <th>Damage Extent</th>
                                  @if($claimVehicle->damage_extent == 'Cracked')
                                     <td>Cracked</td>
                                      @else
                                     <td>Shattered</td>
                                  @endif
                            </tr>
                            <tr>
                                <th>Cause of Damage</th>
                                <td style="width:30%;">{!! $claimVehicle->damage_cause !!}</td>
                                <th>Date Of Claim Registered</th>
                                @if ($claims->registered_claim)
                                    <td>{!! $claims->registered_claim !!}</td>
                                @else
                                    <td>N/A</td>
                                @endif

                            </tr>
                            {{-- <tr>
                                <th>Replacement Estimate</th>
                                <td style="width:30%;">{!! $claimVehicle->replacement_estimate !!}</td>
                                <th>Address where glass is situated</th>
                                @if ($claimVehicle->situated_glass_address)
                                    <td>{!! $claimVehicle->situated_glass_address !!}</td>
                                @else
                                    <td>N/A</td>
                                @endif

                            </tr> --}}
                            {{-- <tr>
                                <th>Size of the plate broken</th>
                                <td style="width:30%;">{!! $claimVehicle->broken_plate_size !!}</td>
                            </tr> --}}
                            </tbody>

                        </table>
                        {{-- <hr>
                        <h3>Insured Details</h3>
                        <table class="table table-striped m-table">
                            <tbody>
                            <tr>
                                <th>Name of Insured</th>
                                <td>{!! $claimVehicle->name_of_insured !!}</td>
                                <th>Address</th>
                                  <td>{!! $claimVehicle->insured_address !!}</td>
                            </tr>
                            <tr>
                                <th>Occupation</th>
                                <td style="width:30%;">{!! $claimVehicle->insured_occupation !!}</td>
                            </tr>
                            </tbody>

                        </table>
                        <hr>
                        <h3>Vehicle Details</h3>
                        <table class="table table-striped m-table">
                            <tbody>
                            <tr>
                                <th>Registration Number</th>
                                <td>{!! $vehicle->vehiclePlate !!}</td>
                                <th>Is Imported?</th>
                                  @if($vehicle->is_imported == 'Yes' || $vehicle->is_imported == 1)
                                     <td>Yes</td>
                                      @else
                                     <td>No</td>
                                  @endif
                            </tr>
                            <tr>
                                <th>Make</th>
                                <td style="width:30%;">{!! $vehicle->make !!}</td>
                                <th>Manufacturing Year</th>
                                <td>{!! $vehicle->year !!}</td>
                            </tr>
                            <tr>
                                <th>Model</th>
                                <td style="width:30%;">{!! $vehicle->model !!}</td>
                                <th>Purpose of use</th>
                                @if ($vehicle->purpose == 1)
                                    <td>BUSINESS</td>
                                @elseif ($vehicle->purpose == 2)
                                    <td>PRIVATE</td>
                                @elseif ($vehicle->purpose == 3)
                                    <td>FINANCIAL INTEREST</td>
                                @else
                                    <td>N/A</td>
                                @endif

                            </tr>
                            <tr>
                                <th>Chassis Number</th>
                                <td style="width:30%;">{!! $vehicle->chassisNo !!}</td>
                                <th>Type Of Glass</th>
                                <td>{!! $claimVehicle->type_of_glass !!}</td>
                            </tr>
                            </tbody>

                        </table> --}}
                        <hr>
                        <div class="kt-portlet">
                            <div class="kt-invoice-v2">
                                <div class="kt-invoice-v2__header grid">
                                    <div class="kt-invoice-v2__header-right">
                                        <div class="kt-invoice-v2__logo thumb">
                                            <h3>Before</h3>
                                            <div class="row col-lg-15">
                                                <div class="col-lg-2" >
                                                    <h3 class="col-form-label" style="float: left;">Front Side</h3>
                                                    <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
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
                                                    <a class="thumbnail" href="#" id="backViewModal" data-image-id="backView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
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
                                                    <a class="thumbnail" href="#" id="rightViewModal" data-image-id="rightView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
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
                                                    <a class="thumbnail" href="#" id="leftViewModal" data-image-id="leftView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
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
                                                    <a class="thumbnail" href="#" id="leftViewModal" data-image-id="leftView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
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
                                        </div>
                                    </div>
                                    <div id="myModal" class="modal">
                                        <span class="close" id="close">&times;</span>
                                        <img class="modal-content" id="img01">
                                    </div>
                                    <div class="kt-invoice-v2__header-left">
                                        <div class="kt-invoice-v2__logo thumb">
                                            <br/><br/>
                                            <h3>After</h3>
                                            <div class="row">
                                                <div class="col-lg-3" >
                                                    <h3 class="col-form-label" style="float: left;">Front Side</h3>
                                                    <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
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
                                                    <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
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
                                                    <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
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
                                                    <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
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
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
                </div>
                @if($policy->kyc_recipient != 0)
                    <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">
                                Recipient KYC
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet_body">
                        <div class="kt-section">
                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                <table class="table table-striped m-table">
                                    <tbody>
                                    <div class="form-group row">
                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Driving License</h3>
                                            @if($recipientKyc->driving_license == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                            @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->driving_license) !!}" target="_blank" download>
                                                    @if(pathinfo($recipientKyc->driving_license,PATHINFO_EXTENSION) == 'pdf')
                                                        <img src="{{asset('images/pdf.ico')}}" width="100%" height="auto">
                                                    @elseif(pathinfo($recipientKyc->driving_license,PATHINFO_EXTENSION) == 'docx' ||
                                                    pathinfo($recipientKyc->driving_license, PATHINFO_EXTENSION)
                                                    == 'doc' || pathinfo($recipientKyc->driving_license,
                                                    PATHINFO_EXTENSION) == 'docm')
                                                        <img src="{{asset('images/word.ico')}}" width="100%"
                                                             height="auto">
                                                    @elseif(pathinfo($recipientKyc->driving_license,
                                                    PATHINFO_EXTENSION) == 'xls' ||
                                                    pathinfo($recipientKyc->driving_license, PATHINFO_EXTENSION)
                                                    == 'xlsx' || pathinfo($recipientKyc->driving_license,
                                                    PATHINFO_EXTENSION) == 'csv')
                                                        <img src="{{asset('images/excel.png')}}"
                                                             width="100%" height="auto">
                                                    @elseif(pathinfo($recipientKyc->driving_license,
                                                    PATHINFO_EXTENSION) == 'jpeg' ||
                                                    pathinfo($recipientKyc->driving_license, PATHINFO_EXTENSION)
                                                    == 'jpg' || pathinfo($recipientKyc->driving_license,
                                                    PATHINFO_EXTENSION) == 'png')
                                                        <img src="{!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->driving_license) !!}"
                                                             width="100%" height="auto">
                                                    @else
                                                        <img src="{{asset('images/doc.png')}}" width="100%"
                                                             height="auto">
                                                    @endif
{{--                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($recipientKyc->driving_license)}}" width="100%" height="auto" >--}}
                                                </a>
                                            @endif
                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                        </div>


                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                                            @if($recipientKyc->omang == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                            @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->omang) !!}" target="_blank" download>
                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($recipientKyc->omang)}}" width="100%" height="auto" >
                                                </a>
                                            @endif
                                            <div class="kt-avatar" style="float: left; clear: left;"></div>
                                        </div>

                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                                            @if($recipientKyc->proof_residence == NULL)

                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                            @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->proof_residence) !!}" target="_blank" download>
                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($recipientKyc->proof_residence)}}" width="100%" height="auto" >
                                                </a>
                                            @endif
                                            <div class="kt-avatar" style="float: left; clear: left;">
                                            </div>
                                        </div>

                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Proof of Income</h3>
                                            @if($recipientKyc->proof_income == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                            @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->proof_income) !!}" target="_blank" download>
                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($recipientKyc->proof_income)}}" width="100%" height="auto" >
                                                </a>
                                            @endif
                                            <div class="kt-avatar" style="float: left; clear: left;">
                                            </div>
                                        </div>

                                        <div class="col-md-2">
                                            <h3 class="col-form-label" style="float: left;">Passport</h3>
                                            @if($recipientKyc->passport == NULL)
                                                <img src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" >
                                            @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->passport) !!}" target="_blank" download>
                                                <img src="{{\AlphaDirect\Helper::getCloudFrontURL($recipientKyc->passport)}}" width="100%" height="auto" >
                                                </a>
                                            @endif
                                            <div class="kt-avatar" style="float: left; clear: left;">
                                            </div>
                                        </div>
                                    </div>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
            <div class="kt-portlet__foot">
                <div class="row">
                    <div class="col-12">
                        <div class="col-3"></div>
                        <div class="col-9">
                            @can('claim-edit')
                                @if($claimAssessmentReport && $claimAssessmentReport->assessment_report)
                            @if($claims->status == 'Approved' || $claims->status == 'Rejected' || $claims->status == 'Closed')
                                <button class="btn btn-brand" style="margin-left: 500px;" onclick="setVehicleToEdit()" hidden>Set to edit</button>
                                @else
                                <button class="btn btn-brand" style="margin-left: 500px;" onclick="setVehicleToEdit()">Set to edit</button>
                            @endif
                                    @endif
                                @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--begin::Portlet-->
<div class="kt-portlet kt-portlet--height-fluid" id="vehicleEditDiv" style="display:none;">
    <div class="kt-portlet__head row" >
        <div class="kt-portlet__head-label col-lg-12">
            <div class="col-lg-4">
                <h3 class="kt-portlet__head-title">
                    Vehicle Claim Information
                </h3>
            </div>
            <div class="col-lg-8">
                <button class="btn btn-warning" style="float: right" onclick="setVehicleToView()">Set to view</button>
            </div>
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="kt-widget-4">
            <form action="{!! route('admin.claims.approved',[$claims->id]) !!}" method="POST" id="vehicleForm" enctype="multipart/form-data">
                {{ method_field('PATCH') }}
                {{csrf_field()}}
                <div class="row">
                    <input id="policy_id" class="form-control" type="hidden" name="policy_id" value="{!! $policy->id !!}">
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
                            <label>Replacement Estimate</label>
                            <div class="form-group form-group-last">
                                <textarea class="form-control" name="replacement_estimate" id="replacement_estimate" rows="3">{{$claimVehicle->replacement_estimate}}</textarea>
                            </div>
                        </div>
                    </div> --}}
                </div>
                {{-- <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label>Address where glass is situated</label>
                            <input type="text" class="form-control" value="{{$claimVehicle->situated_glass_address}}" placeholder="Please enter address where glass is situated" name="situated_glass_address" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group" >
                            <label style="margin-left: 130px;">Size of the plate broken</label>
                            <input type="text" class="form-control" name="broken_plate_size" value="{{$claimVehicle->broken_plate_size}}"  placeholder="Enter size of the plate broken">
                        </div>
                    </div>
                </div> --}}
                {{-- <hr> --}}

                {{-- <h3>Insured Details</h3>
                <div class="form-group row">
                    <label for="example-text-input" class="col-3 col-form-label">Name of Insured</label>
                    <div class="col-9">
                        <input type="text" class="form-control" id="name_of_insured" name="name_of_insured" value="{{$claimVehicle->name_of_insured}}" placeholder="Please enter name of insured" autocomplete="off" title="Please provide name of insured">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="example-text-input" class="col-3 col-form-label">Address</label>
                    <div class="col-9">
                        <input type="text" class="form-control" id="insured_address" name="insured_address" value="{{$claimVehicle->insured_address}}" placeholder="Please enter address" autocomplete="off" title="Please provide address">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="example-text-input" class="col-3 col-form-label">Occupation</label>
                    <div class="col-9">
                        <input type="text" class="form-control" id="insured_occupation" name="insured_occupation" value="{{$claimVehicle->insured_occupation}}" placeholder="Please enter occupation" autocomplete="off" title="Please provide occupation">
                    </div>
                </div>

                <hr> --}}

                {{-- <h3>Vehicle Details</h3>
                <div class="form-group row">
                    <label for="example-text-input" class="col-3 col-form-label">Registration Number</label>
                    <div class="col-9">
                        <input type="text" class="form-control" name="vehicle_plate" value="{{$vehicle->vehiclePlate}}"  placeholder="Enter vehicle registration number">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="example-text-input" class="col-3 col-form-label">Is Imported?</label>
                    <div class="col-9">
                        <select class="form-control kt_selectpicker select" required name="is_imported"
                                    title="Please select type" data-live-search="true" id="is_imported">
                                    <option value="Yes" @if ($vehicle->is_imported == 'Yes' || $vehicle->is_imported == 1) selected @endif>Yes</option>
                                    <option value="No" @if ($vehicle->is_imported == 'No' || $vehicle->is_imported == 0) selected @endif>No</option>
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
                                    <option value="{{ $vehicleMake->s_Make }}" @if ($vehicle->make == $vehicleMake->s_Make) selected @endif>
                                        {{ ucwords(strtoupper($vehicleMake->s_Make)) }}</option>
                                @endif
                                @if ($vehicle->is_imported == 'No' || $vehicle->is_imported == 0)
                                    <option value="{{ $vehicleMake }}"  @if ($vehicle->make == $vehicleMake) selected @endif>
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
                                <option value="{{ $year }}" @if ($vehicle->year == $year) selected @endif>
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
                                        <option value="{{ $model['model'] }}" @if ($vehicle->model == $model['model']) selected @endif>
                                            {{ ucwords(strtoupper($model['model'])) }}</option>
                                    @endforeach
                                @endif

                                @if ((is_array($vehicle_model) && $vehicle->is_imported == 'No') || $vehicle->is_imported == 0)
                                    @foreach ($vehicle_model as $key => $model)
                                        <option value="{{ $model['Model'] }}" @if ($vehicle->model == $model['Model']) selected @endif>
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
                            <option value="1" @if ($vehicle->purpose == 1) selected @endif>BUSINESS</option>
                            <option value="2" @if ($vehicle->purpose == 2) selected @endif>PRIVATE</option>
                            <option value="3" @if ($vehicle->purpose == 3) selected @endif>FINANCIAL INTEREST</option>
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="example-text-input" class="col-3 col-form-label">Chassis Number</label>
                    <div class="col-9">
                        <input type="text" class="form-control" name="chassisNo" value="{{$vehicle->chassisNo}}"  placeholder="Enter vehicle chassis number">
                    </div>
                </div>
                <div class="form-group row">
                    <label for="example-text-input" class="col-3 col-form-label">Type Of Glass</label>
                    <div class="col-9">
                        <input type="text" class="form-control" name="type_of_glass" value="{{$claimVehicle->type_of_glass}}" placeholder="Enter vehicle type of glass">
                    </div>
                </div> --}}

                <hr>
                <!--begin::Portlet-->
                <div class="kt-portlet">
                    <div class="kt-invoice-v2">
                        <div class="kt-invoice-v2__header grid">
                            <div class="kt-invoice-v2__header-right">
                                <div class="kt-invoice-v2__logo thumb">
                                    <h3>Before</h3>
                                    <div class="row">
                                        <div class="col-lg-2" >
                                            <h3 class="col-form-label" style="float: left;">Front Side</h3>
                                            <a class="thumbnail" href="#" id="frontViewModal" data-image-id="frontView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                @if($vehicle->front != NULL)
                                                    <img style="height:72.5%" src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->front)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @else
                                                    <img style="height:72.5%" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @endif
                                            </a>
                                        </div>
                                        <div class="col-lg-2">
                                            <h3 class="col-form-label" style="float: left;">Back Side</h3>
                                            <a class="thumbnail" href="#" id="backViewModal" data-image-id="backView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                @if($vehicle->back != NULL)
                                                    <img style="height:72.5%" src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->back)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @else
                                                    <img  style="height:72.5%" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @endif
                                            </a>
                                        </div>
                                        <div class="col-lg-2">
                                            <h3 class="col-form-label" style="float: left;">Right Side</h3>
                                            <a class="thumbnail" href="#" id="rightViewModal" data-image-id="rightView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                @if($vehicle->right != NULL)
                                                <img style="height:72.5%" src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->right)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @else
                                                    <img style="height:72.5%" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @endif
                                            </a>
                                        </div>
                                        <div class="col-lg-2">
                                            <h3 class="col-form-label" style="float: left;">Left Side</h3>
                                            <a class="thumbnail" href="#" id="leftViewModal" data-image-id="leftView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                @if($vehicle->left != NULL)
                                                <img style="height:72.5%" src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->left)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @else
                                                    <img style="height:72.5%" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @endif
                                            </a>
                                        </div>
                                        <div class="col-lg-2">
                                            <h3 class="col-form-label" style="float: left;">Vehicle Registration</h3>
                                            <a class="thumbnail" href="#" id="leftViewModal" data-image-id="leftView" data-toggle="modal" data-title="This is my title" data-caption="{{ isset($vehicle->make) ? $vehicle->make . ' Front Windscreen' : 'Front Windscreen' }}" data-image="{{asset('images/windscreen.jpg')}}" data-target="#image-gallery">
                                                @if($vehicle->vehicleRegistration != NULL)
                                                    <img style="height:72.5%" src="{{\AlphaDirect\Helper::getCloudFrontURL($vehicle->vehicleRegistration)}}" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @else
                                                    <img style="height:72.5%" src="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png" width="100%" height="auto" class="gridimg" title="Invoice" alt="Invoice">
                                                @endif
                                            </a>
                                        </div>

                                    </div>
                                </div>
                            </div>

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
                                </div>
                                @if($policy->kyc_recipient)
                                    @include('admin.claims.recipient_kyc')
                                @endif
                                <div class="form-group row" style="padding-top:40px; padding-left: 20px;">
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!--end::Portlet-->
                <div class="kt-portlet__foot kt-portlet__foot--solid">
                    <div class="kt-form__actions">
                        <div class="row">
                            <div class="col-3"></div>
                            <div class="col-9">
                                <button class="btn btn-brand" id="sbtBtn" type="submit">Update</button>
                                <button class="btn btn-brand" type="button" id="loadBtn" style="display:none" > <span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Loading... </button>
                                <a class="btn btn-warning" onclick="setVehicleToView()">Set to view</a>
                                <a class="btn btn-secondary" href="{{ route('admin.claims.index') }}" >Cancel</a>
                            </div>
                        </div>
                    </div>
                </div>
                {!! Form::close() !!}
            </form>
        </div>
    </div>
</div>
<!--end::Portlet-->

